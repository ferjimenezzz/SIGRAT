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
     * Obtiene los espacios que no tienen reservas en una fecha y rango horario específicos.
     * @param string $fecha Fecha de la consulta (formato YYYY-MM-DD).
     * @param string $hora_inicio Hora de inicio del bloque solicitado (formato HH:MM:SS).
     * @param string $hora_fin Hora de fin del bloque solicitado (formato HH:MM:SS).
     * @return array Estructura de respuesta indicando el éxito y el listado de espacios desocupados o el mensaje de error.
     */
    public function getUnoccupied($fecha, $hora_inicio, $hora_fin) {
        try {
            // Consulta SQL estructurada con subconsulta NOT IN para excluir los espacios que ya cuenten con reservaciones aprobadas que se traslapen con el horario solicitado
            $query = "
                SELECT e.esp_id, e.edificio, e.nombre_numero, e.tipo, e.capacidad
                FROM espacio e
                WHERE e.estatus = 'Disponible'
                  AND e.esp_id NOT IN (
                      SELECT r.esp_id
                      FROM reserva r
                      WHERE r.estatus IN ('Aprobada', 'Aprobado')
                        AND r.fecha_uso = ?
                        AND r.hora_ent < ?
                        AND r.hora_sal > ?
                  )
                ORDER BY e.edificio, e.nombre_numero
            ";
            
            // Preparación del statement para mitigar el riesgo de inyecciones SQL (Security Standard)
            $stmt = $this->db->prepare($query);
            
            // Ejecución enviando los parámetros en el orden requerido por los marcadores posicionales (fecha, hora_fin, hora_inicio)
            $stmt->execute([$fecha, $hora_fin, $hora_inicio]);
            
            // Retorno exitoso conteniendo la colección de registros
            return ["success" => true, "data" => $stmt->fetchAll()];
        } catch (\PDOException $e) {
            // Registro en el log del servidor ante fallas en la base de datos
            error_log("Error en getUnoccupied: " . $e->getMessage());
            return ["success" => false, "error" => "Error al consultar espacios desocupados."];
        }
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
