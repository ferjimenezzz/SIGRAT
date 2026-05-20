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
}
