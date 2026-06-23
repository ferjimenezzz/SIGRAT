<?php
/**
 * @file LoanController.php
 * @summary Controlador para la gestión de préstamos de activos.
 * @description Maneja el flujo de salida, retorno y seguimiento de préstamos de herramientas y equipo.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/NotificationController.php';
require_once __DIR__ . '/../services/EmailService.php';

use Config\Database;
use Controllers\NotificationController;
use Services\EmailService;
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
            
            $new_pres_id = $this->db->lastInsertId();

            // Notificar a los administradores
            try {
                $notifCtrl = new NotificationController();
                $stmtAdmins = $this->db->query("SELECT us_id FROM USUARIO WHERE rol_id IN (SELECT rol_id FROM ROLES WHERE UPPER(nombre) LIKE '%ADMIN%')");
                $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
                foreach ($admins as $admin_id) {
                    $notifCtrl->createNotification($admin_id, 'Prestamo', 'Se ha registrado un nuevo préstamo en el sistema.', 'prestamos.php');
                }
            } catch (\Exception $e) {
                error_log("Error al enviar notificación de préstamo: " . $e->getMessage());
            }
            // Notificar al solicitante por correo
            try {
                $stmtDet = $this->db->prepare("SELECT u.correo, a.tipo, a.num_serie FROM USUARIO u, ACTIVO a WHERE u.us_id = ? AND a.act_id = ?");
                $stmtDet->execute([$us_id, $act_id]);
                $det = $stmtDet->fetch();
                if ($det && !empty($det['correo'])) {
                    $emailService = new EmailService();
                    $emailService->sendLoanCreated($det['correo'], $new_pres_id, $det['tipo'], $det['num_serie'], date('Y-m-d H:i:s'));
                }
            } catch (\Exception $e) {
                error_log("Error al enviar correo de préstamo al usuario: " . $e->getMessage());
            }

            return ["success" => true, "id" => $new_pres_id];
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

            $new_pres_id = $this->db->lastInsertId();

            // Notificar a los administradores
            try {
                $notifCtrl = new NotificationController();
                $stmtAdmins = $this->db->query("SELECT us_id FROM USUARIO WHERE rol_id IN (SELECT rol_id FROM ROLES WHERE UPPER(nombre) LIKE '%ADMIN%')");
                $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
                foreach ($admins as $admin_id) {
                    $notifCtrl->createNotification($admin_id, 'Prestamo', 'Se ha registrado un nuevo préstamo (Dinámico).', 'prestamos.php');
                }
            } catch (\Exception $e) {
                error_log("Error al enviar notificación de préstamo dinámico: " . $e->getMessage());
            }

            // Notificar al solicitante por correo
            try {
                $emailService = new EmailService();
                $emailService->sendLoanCreated($correo, $new_pres_id, $equipo, $serie, $fecha_pres, $fecha_ent_val);
            } catch (\Exception $e) {
                error_log("Error al enviar correo de préstamo al usuario: " . $e->getMessage());
            }

            $this->db->commit();
            return ["success" => true, "id" => $new_pres_id];
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
    public function getAllLoans($us_id = null, $isAdmin = true) {
        $query = "
            SELECT p.pres_id, p.fecha_pres, p.fecha_ent, p.estatus,
                   a.tipo, a.marca, a.modelo, a.num_serie, a.act_id,
                   u.nombre as solicitante_nombre, u.correo as solicitante_correo, u.us_id
            FROM PRESTAMO p
            JOIN ACTIVO a ON p.act_id = a.act_id
            JOIN USUARIO u ON p.us_id = u.us_id
        ";
        
        $params = [];
        if (!$isAdmin && $us_id) {
            $query .= " WHERE p.us_id = ? ";
            $params[] = $us_id;
        }

        $query .= " ORDER BY p.fecha_pres DESC";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    /**
     * @summary Crea un préstamo programado (a partir de una reservación).
     * @param int $act_id
     * @param int $us_id
     * @param string $fecha_pres Fecha y hora de inicio del préstamo (Y-m-d H:i:s).
     * @param string $fecha_ent Fecha y hora de entrega del préstamo (Y-m-d H:i:s).
     */
    public function createScheduledLoan($act_id, $us_id, $fecha_pres, $fecha_ent) {
        try {
            $query = "INSERT INTO PRESTAMO (act_id, us_id, fecha_pres, fecha_ent, estatus) VALUES (?, ?, ?, ?, 'Activo')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$act_id, $us_id, $fecha_pres, $fecha_ent]);
            
            $new_pres_id = $this->db->lastInsertId();

            return ["success" => true, "id" => $new_pres_id];
        } catch (\Exception $e) {
            error_log("Error creando préstamo programado: " . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
