<?php
/**
 * @file AssetController.php
 * @summary Controlador para la gestión de activos (inventario) en PHP.
 * @description Permite el registro masivo de tags detectados y la administración de dispositivos.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;

class AssetController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    /**
     * Crea un nuevo activo.
     */
    public function create($data) {
        try {
            // 0. Asegurar integridad en tarjeta_rfid (Maestra)
            if (!empty($data['tag_id'])) {
                $rfidStmt = $this->db->prepare("INSERT INTO tag_rfid (tag_id, tipo_tag) VALUES (?, 'Activo') ON CONFLICT (tag_id) DO NOTHING");
                $rfidStmt->execute([$data['tag_id']]);
            }

            $query = "INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['tipo'],
                $data['marca'],
                $data['modelo'],
                $data['num_serie'],
                $data['num_inv'],
                $data['estatus'] ?? 'Disponible',
                $data['tag_id'] ?? null,
                $data['esp_asignado'] ?? null
            ]);
            
            $id = $this->db->lastInsertId();
            $this->audit->log(1, "Registrado nuevo activo ID: $id (" . $data['tipo'] . ")", "INVENTARIO");
            
            return ["success" => true, "id" => $id];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Obtiene todos los activos.
     */
    public function getAll() {
        return $this->db->query("SELECT * FROM ACTIVO ORDER BY act_id DESC")->fetchAll();
    }

    /**
     * Elimina un activo.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM ACTIVO WHERE act_id = ?");
            $stmt->execute([$id]);
            $this->audit->log(1, "Eliminado activo ID: $id", "INVENTARIO");
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Registra un lote de activos.
     */
    public function bulkSave($assets, $tipo) {
        $this->db->beginTransaction();
        try {
            $query = "INSERT INTO ACTIVO (tag_id, tipo, estatus, num_inv) VALUES (?, ?, 'Disponible', ?)";
            $stmt = $this->db->prepare($query);

            foreach ($assets as $asset) {
                // Registrar en tabla maestra de RFID primero
                $rfidStmt = $this->db->prepare("INSERT INTO tag_rfid (tag_id, tipo_tag) VALUES (?, 'Activo') ON CONFLICT (tag_id) DO NOTHING");
                $rfidStmt->execute([$asset['tag_id']]);

                $num_inv = "INV-" . strtoupper(substr(md5($asset['tag_id']), 0, 8));
                $stmt->execute([$asset['tag_id'], $tipo, $num_inv]);
            }

            $this->db->commit();
            return ["success" => true, "count" => count($assets)];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
