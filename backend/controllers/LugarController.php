<?php
/**
 * @file LugarController.php
 * @summary Controlador para la gestión de lugares.
 * @description Maneja el CRUD de la tabla lugares, orientada a espacios no reservables como pasillos, bodegas, oficinas, etc.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;

class LugarController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    /**
     * Obtiene los valores permitidos del ENUM tipo de la base de datos.
     * @return array Lista de strings con los tipos válidos.
     */
    public function getTiposPermitidos() {
        return ['Pasillo', 'Bodega', 'Oficina', 'Cubículo', 'Recepción', 'Cluster', 'Otro'];
    }

    /**
     * Obtiene los valores permitidos del ENUM planta.
     * @return array Lista de strings con las plantas válidas.
     */
    public function getPlantasPermitidas() {
        return ['Baja', 'Alta'];
    }

    /**
     * Crea un nuevo lugar.
     * @param array $data Arreglo con los datos del lugar.
     * @return array Retorna un arreglo asociativo con 'success' y opcionalmente 'error'.
     */
    public function create($data) {
        $tiposPermitidos = $this->getTiposPermitidos();
        if (!in_array($data['tipo'], $tiposPermitidos)) {
            return ["success" => false, "error" => "Tipo de lugar no válido."];
        }

        try {
            $query = "INSERT INTO lugares (edificio, planta, nombre_numero, tipo, capacidad, estatus) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            
            $stmt->execute([
                $data['edificio'],
                $data['planta'],
                $data['nombre_numero'],
                $data['tipo'],
                $data['capacidad'] !== '' ? $data['capacidad'] : null,
                $data['estatus'] ?? 'Disponible'
            ]);
            
            $us_id = $_SESSION['us_id'] ?? 1;
            $this->audit->log($us_id, "Creado nuevo lugar: " . $data['nombre_numero'] . " (" . $data['edificio'] . ")", "LUGARES");
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Obtiene todos los lugares activos.
     * @return array Un arreglo de registros con los lugares.
     */
    public function getAll() {
        return $this->db->query("SELECT * FROM lugares WHERE eliminado = false OR eliminado IS NULL ORDER BY edificio, nombre_numero")->fetchAll();
    }

    /**
     * Actualiza un lugar existente.
     * @param int $lug_id El identificador del lugar.
     * @param array $data Los datos a actualizar.
     * @return array Resultado de la operación.
     */
    public function update($lug_id, $data) {
        $tiposPermitidos = $this->getTiposPermitidos();
        if (!in_array($data['tipo'], $tiposPermitidos)) {
            return ["success" => false, "error" => "Tipo de lugar no válido."];
        }

        try {
            $query = "UPDATE lugares SET edificio = ?, planta = ?, nombre_numero = ?, tipo = ?, capacidad = ?, estatus = ? WHERE lug_id = ?";
            $stmt = $this->db->prepare($query);
            
            $stmt->execute([
                $data['edificio'],
                $data['planta'],
                $data['nombre_numero'],
                $data['tipo'],
                $data['capacidad'] !== '' ? $data['capacidad'] : null,
                $data['estatus'] ?? 'Disponible',
                $lug_id
            ]);

            $us_id = $_SESSION['us_id'] ?? 1;
            $this->audit->log($us_id, "Actualizado lugar ID: " . $lug_id, "LUGARES");
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Elimina lógicamente un lugar por ID.
     * @param int $id El identificador único del lugar.
     * @return array Retorna un arreglo asociativo con 'success' y opcionalmente 'error'.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE lugares SET eliminado = true WHERE lug_id = ?");
            $stmt->execute([$id]);
            
            $us_id = $_SESSION['us_id'] ?? 1;
            $this->audit->log($us_id, "Eliminado lugar ID: $id", "LUGARES");
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
