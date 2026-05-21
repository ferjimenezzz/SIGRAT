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
     * Obtiene los valores permitidos del ENUM de la base de datos de manera centralizada.
     * @return array Lista de strings con los tipos válidos.
     */
    public function getTiposPermitidos() {
        return ['Aula', 'Laboratorio', 'Auditorio', 'Sala de juntas'];
    }

    /**
     * Crea un nuevo espacio.
     * @param array $data Arreglo con los datos del espacio (edificio, nombre_numero, tipo, capacidad).
     * @return array Retorna un arreglo asociativo con 'success' y opcionalmente 'error'.
     */
    public function create($data) {
        // Validamos que el tipo pertenezca a los valores permitidos del ENUM
        $tiposPermitidos = $this->getTiposPermitidos();
        if (!in_array($data['tipo'], $tiposPermitidos)) {
            // Retornamos error si el tipo no es válido
            return ["success" => false, "error" => "Tipo de espacio no válido."];
        }

        try {
            // Preparamos la consulta de inserción para el nuevo espacio
            $query = "INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad, estatus) VALUES (?, ?, ?, ?, 'Disponible')";
            $stmt = $this->db->prepare($query);
            // Ejecutamos la inserción usando parámetros seguros
            $stmt->execute([
                $data['edificio'],
                $data['nombre_numero'],
                $data['tipo'],
                $data['capacidad']
            ]);
            
            // Registramos el evento en bitácora (hardcodeando ID de admin = 1 por el momento)
            $this->audit->log(1, "Creado nuevo espacio: " . $data['nombre_numero'] . " (" . $data['edificio'] . ")", "ESPACIOS");
            // Retornamos operación exitosa
            return ["success" => true];
        } catch (\Exception $e) {
            // En caso de fallo en BD, capturamos el mensaje
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Obtiene todos los espacios.
     * @return array Un arreglo de registros con los espacios.
     */
    public function getAll() {
        // Realizamos un fetch de todos los registros ordenados por edificio y nombre
        return $this->db->query("SELECT * FROM ESPACIO ORDER BY edificio, nombre_numero")->fetchAll();
    }

    /**
     * Elimina un espacio por ID.
     * @param int $id El identificador único del espacio.
     * @return array Retorna un arreglo asociativo con 'success' y opcionalmente 'error'.
     */
    public function delete($id) {
        try {
            // Preparamos la eliminación por llave primaria
            $stmt = $this->db->prepare("DELETE FROM ESPACIO WHERE esp_id = ?");
            // Ejecutamos la consulta con el parámetro
            $stmt->execute([$id]);
            // Registramos la eliminación en el módulo de auditoría
            $this->audit->log(1, "Eliminado espacio ID: $id", "ESPACIOS");
            // Confirmamos éxito de la operación
            return ["success" => true];
        } catch (\Exception $e) {
            // Retornamos falso si la operación falló
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
