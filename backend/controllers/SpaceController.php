<?php
/**
 * @file SpaceController.php
 * @summary Controlador para la gestión de espacios y áreas físicas.
 * @description Maneja el CRUD de la tabla ESPACIO (CIC/PIDET).
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;

class SpaceController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    /**
     * Crea un nuevo espacio.
     */
    public function create($data) {
        try {
            $query = "INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad, estatus) VALUES (?, ?, ?, ?, 'Disponible')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['edificio'],
                $data['nombre_numero'],
                $data['tipo'],
                $data['capacidad']
            ]);
            
            $this->audit->log(1, "Creado nuevo espacio: " . $data['nombre_numero'] . " (" . $data['edificio'] . ")", "ESPACIOS");
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Obtiene todos los espacios.
     */
    public function getAll() {
        return $this->db->query("SELECT * FROM ESPACIO ORDER BY edificio, nombre_numero")->fetchAll();
    }

    /**
     * Elimina un espacio.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM ESPACIO WHERE esp_id = ?");
            $stmt->execute([$id]);
            $this->audit->log(1, "Eliminado espacio ID: $id", "ESPACIOS");
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
