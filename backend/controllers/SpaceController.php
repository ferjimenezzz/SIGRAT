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
            $query = "INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad, estatus, acceso_tipo, division_restringida) VALUES (?, ?, ?, ?, 'Disponible', ?, ?)";
            $stmt = $this->db->prepare($query);
            
            $acceso_tipo = $data['acceso_tipo'] ?? 'General';
            $division = null;
            if ($acceso_tipo === 'Division') {
                $division = !empty($data['division_restringida']) ? $data['division_restringida'] : null;
            }

            // Ejecutamos la inserción usando parámetros seguros
            $stmt->execute([
                $data['edificio'],
                $data['nombre_numero'],
                $data['tipo'],
                $data['capacidad'],
                $acceso_tipo,
                $division
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
     * Actualiza un espacio existente.
     * @param int $esp_id El identificador del espacio.
     * @param array $data Los datos a actualizar.
     * @return array Resultado de la operación.
     */
    public function update($esp_id, $data) {
        $tiposPermitidos = $this->getTiposPermitidos();
        if (!in_array($data['tipo'], $tiposPermitidos)) {
            return ["success" => false, "error" => "Tipo de espacio no válido."];
        }

        try {
            $query = "UPDATE ESPACIO SET edificio = ?, nombre_numero = ?, tipo = ?, capacidad = ?, acceso_tipo = ?, division_restringida = ? WHERE esp_id = ?";
            $stmt = $this->db->prepare($query);
            
            $acceso_tipo = $data['acceso_tipo'] ?? 'General';
            $division = null;
            if ($acceso_tipo === 'Division') {
                $division = !empty($data['division_restringida']) ? $data['division_restringida'] : null;
            }

            $stmt->execute([
                $data['edificio'],
                $data['nombre_numero'],
                $data['tipo'],
                $data['capacidad'],
                $acceso_tipo,
                $division,
                $esp_id
            ]);

            $this->audit->log(1, "Actualizado espacio ID: " . $esp_id, "ESPACIOS");
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
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
