<?php
/**
 * @file ReservationController.php
 * @summary Controlador para la gestión de reservaciones en PHP.
 * @description Maneja la creación, disponibilidad y validación de reservaciones para internos y externos.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;

class ReservationController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    /**
     * Crea una reservación (Inicia en estado 'Pendiente').
     */
    public function create($data) {
        $this->db->beginTransaction();
        try {
            $vis_id = $data['vis_id'] ?? null;
            $us_id = $data['us_id'] ?? null;

            $conflictQuery = "SELECT re_id FROM RESERVA WHERE esp_id = ? AND estatus = 'Aprobada' AND fecha_uso = ?
                              AND ((hora_ent < ? AND hora_sal > ?) OR (hora_ent < ? AND hora_sal > ?) OR (? <= hora_ent AND ? >= hora_sal))";
            $stmt = $this->db->prepare($conflictQuery);
            $stmt->execute([$data['esp_id'], $data['fecha_uso'], $data['hora_sal'], $data['hora_ent'], $data['hora_sal'], $data['hora_ent'], $data['hora_ent'], $data['hora_sal']]);
            if ($stmt->fetch()) throw new \Exception("Conflicto de horario.");

            $stmt = $this->db->prepare("INSERT INTO RESERVA (esp_id, us_id, vis_id, num_alumnos, fecha_uso, hora_ent, hora_sal, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
            $stmt->execute([$data['esp_id'], $us_id, $vis_id, $data['num_alumnos'] ?? 0, $data['fecha_uso'], $data['hora_ent'], $data['hora_sal']]);
            $this->db->commit();
            
            // Auditoría
            $this->audit->log($us_id, "Creada nueva reservación ID: " . $this->db->lastInsertId(), "RESERVAS", $vis_id);
            
            return ["success" => true, "id" => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function approve($id, $admin_id, $comments = '') {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE RESERVA SET estatus = 'Aprobada' WHERE re_id = ?")->execute([$id]);
            $this->db->prepare("INSERT INTO APROBACION (re_id, admin_id, estado, comentarios) VALUES (?, ?, 'Aprobado', ?)")->execute([$id, $admin_id, $comments]);

            $this->db->commit();

            // Auditoría
            $this->audit->log($admin_id, "Aprobada reservación ID: $id", "RESERVAS");

            return ["success" => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function getAvailability($esp_id, $date) {
        $stmt = $this->db->prepare("SELECT hora_ent, hora_sal, estatus FROM RESERVA WHERE esp_id = ? AND fecha_uso = ? AND estatus != 'Rechazada'");
        $stmt->execute([$esp_id, $date]);
        return $stmt->fetchAll();
    }
}
