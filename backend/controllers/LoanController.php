<?php
/**
 * @file LoanController.php
 * @summary Controlador para la gestión de préstamos de activos.
 * @description Maneja el flujo de salida, retorno y seguimiento de préstamos de herramientas y equipo.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;

class LoanController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Registra un nuevo préstamo.
     */
    public function create($act_id, $us_id) {
        try {
            $query = "INSERT INTO PRESTAMO (act_id, us_id, fecha_pres, estatus) VALUES (?, ?, NOW(), 'Activo')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$act_id, $us_id]);
            
            // Actualizar estado del activo
            $this->db->prepare("UPDATE ACTIVO SET estatus = 'Prestado' WHERE act_id = ?")->execute([$act_id]);
            
            return ["success" => true, "id" => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Crea un préstamo a partir de datos dinámicos (creando usuario y equipo si no existen).
     */
    public function createDynamicLoan($equipo, $categoria, $serie, $nombre, $correo, $area, $fecha_pres, $fecha_ent, $estatus, $obs) {
        try {
            $this->db->beginTransaction();

            // 1. Gestionar Usuario
            $stmtUs = $this->db->prepare("SELECT us_id FROM USUARIO WHERE correo = ?");
            $stmtUs->execute([$correo]);
            $us_id = $stmtUs->fetchColumn();

            if (!$us_id) {
                // Crear usuario
                $stmtNewUs = $this->db->prepare("INSERT INTO USUARIO (nombre, correo, carrera, contrasena, estatus) VALUES (?, ?, ?, '12345', 'Activo')");
                $stmtNewUs->execute([$nombre, $correo, $area]);
                $us_id = $this->db->lastInsertId();
            }

            // 2. Gestionar Equipo
            if (empty($serie)) {
                $serie = 'SIN-SERIE-' . date('YmdHis') . '-' . rand(1000, 9999);
            }

            $stmtAct = $this->db->prepare("SELECT act_id FROM ACTIVO WHERE num_serie = ?");
            $stmtAct->execute([$serie]);
            $act_id = $stmtAct->fetchColumn();

            if (!$act_id) {
                // Crear activo
                $estado_activo = ($estatus === 'Activo') ? 'Prestado' : 'Disponible';
                $stmtNewAct = $this->db->prepare("INSERT INTO ACTIVO (tipo, marca, num_serie, estatus) VALUES (?, ?, ?, ?)");
                // Trataremos "equipo" como tipo y "categoria" como marca (o agruparemos todo)
                $stmtNewAct->execute([$equipo, $categoria, $serie, $estado_activo]);
                $act_id = $this->db->lastInsertId();
            } else {
                // Si el activo existe, actualizar su estado a Prestado si el prestamo es Activo
                if ($estatus === 'Activo') {
                    $this->db->prepare("UPDATE ACTIVO SET estatus = 'Prestado' WHERE act_id = ?")->execute([$act_id]);
                }
            }

            // 3. Crear Préstamo
            $fecha_ent_val = empty($fecha_ent) ? null : $fecha_ent;
            
            // La BD actualmente no tiene campo para 'observaciones', se podría añadir,
            // pero si no hay, la ignoramos o la enviamos solo si se altera la BD.
            
            $queryPres = "INSERT INTO PRESTAMO (act_id, us_id, fecha_pres, fecha_ent, estatus) VALUES (?, ?, ?, ?, ?)";
            $stmtPres = $this->db->prepare($queryPres);
            $stmtPres->execute([$act_id, $us_id, $fecha_pres, $fecha_ent_val, $estatus]);

            $this->db->commit();
            return ["success" => true, "id" => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Registra el retorno de un activo.
     */
    public function returnAsset($pres_id) {
        try {
            // Obtener el act_id antes de cerrar el préstamo
            $stmt = $this->db->prepare("SELECT act_id FROM PRESTAMO WHERE pres_id = ?");
            $stmt->execute([$pres_id]);
            $act_id = $stmt->fetchColumn();

            $query = "UPDATE PRESTAMO SET fecha_ent = NOW(), estatus = 'Finalizado' WHERE pres_id = ?";
            $this->db->prepare($query)->execute([$pres_id]);
            
            // Liberar el activo
            $this->db->prepare("UPDATE ACTIVO SET estatus = 'Disponible' WHERE act_id = ?")->execute([$act_id]);
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Obtiene todos los préstamos con detalles del equipo y del usuario.
     */
    public function getAllLoans() {
        $query = "
            SELECT p.pres_id, p.fecha_pres, p.fecha_ent, p.estatus,
                   a.tipo, a.marca, a.modelo, a.num_serie, a.act_id,
                   u.nombre as solicitante_nombre, u.correo as solicitante_correo, u.us_id
            FROM PRESTAMO p
            JOIN ACTIVO a ON p.act_id = a.act_id
            JOIN USUARIO u ON p.us_id = u.us_id
            ORDER BY p.fecha_pres DESC
        ";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene activos disponibles para préstamo.
     */
    public function getAvailableAssets() {
        $query = "SELECT act_id, tipo, marca, modelo, num_serie FROM ACTIVO WHERE estatus = 'Disponible'";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene usuarios registrados para asignarles el préstamo.
     */
    public function getUsers() {
        $query = "SELECT us_id, nombre, correo, carrera FROM USUARIO WHERE estatus = 'Activo'";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Actualiza un préstamo existente (fechas y estado).
     */
    public function updateLoan($pres_id, $estatus, $fecha_pres, $fecha_ent) {
        try {
            // Manejar fechas vacías
            $fecha_ent = empty($fecha_ent) ? null : $fecha_ent;
            
            $query = "UPDATE PRESTAMO SET estatus = ?, fecha_pres = ?, fecha_ent = ? WHERE pres_id = ?";
            $this->db->prepare($query)->execute([$estatus, $fecha_pres, $fecha_ent, $pres_id]);
            
            // Actualizar estatus del activo según el estado del préstamo
            $stmt = $this->db->prepare("SELECT act_id FROM PRESTAMO WHERE pres_id = ?");
            $stmt->execute([$pres_id]);
            $act_id = $stmt->fetchColumn();

            if ($act_id) {
                if ($estatus === 'Finalizado' || $estatus === 'Devuelto') {
                    $this->db->prepare("UPDATE ACTIVO SET estatus = 'Disponible' WHERE act_id = ?")->execute([$act_id]);
                } else {
                    $this->db->prepare("UPDATE ACTIVO SET estatus = 'Prestado' WHERE act_id = ?")->execute([$act_id]);
                }
            }
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Elimina un préstamo y libera el activo.
     */
    public function deleteLoan($pres_id) {
        try {
            $stmt = $this->db->prepare("SELECT act_id FROM PRESTAMO WHERE pres_id = ?");
            $stmt->execute([$pres_id]);
            $act_id = $stmt->fetchColumn();

            $this->db->prepare("DELETE FROM PRESTAMO WHERE pres_id = ?")->execute([$pres_id]);
            
            if ($act_id) {
                $this->db->prepare("UPDATE ACTIVO SET estatus = 'Disponible' WHERE act_id = ?")->execute([$act_id]);
            }
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
