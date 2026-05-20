<?php
/**
 * @file TagController.php
 * @summary Controlador para la gestión y vinculación de tarjetas/tags RFID.
 * @description Administra el registro físico de los tags (TAG_RFID) y maneja la vinculación (PUT) hacia activos, llaves y mobiliario.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;
use PDOException;

class TagController {
    private $db;
    private $audit;

    /**
     * Constructor del controlador. Inicializa la conexión a la base de datos y el auditor.
     */
    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    /**
     * Registra un nuevo Tag RFID en el catálogo maestro (Fase 1: Alta de Hardware).
     * @param array $data Arreglo con 'tag_id', 'tipo_tag' ('Activo'|'Llave'|'Mobiliario'), y opcionalmente 'estado'.
     * @return array Estructura de respuesta indicando éxito o el error ocurrido.
     */
    public function create($data) {
        try {
            // Consulta para insertar el tag en la tabla maestra
            $query = "INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            
            // Si no se provee un estado, por defecto será 'Activo'
            $estado = $data['estado'] ?? 'Activo';
            
            $stmt->execute([
                $data['tag_id'],
                $data['tipo_tag'],
                $estado
            ]);

            // Registrar acción en la bitácora del sistema
            $this->audit->log(1, "Registrado nuevo Tag RFID: " . $data['tag_id'] . " de tipo " . $data['tipo_tag'], "RFID");
            
            return ["success" => true];
        } catch (PDOException $e) {
            error_log("Error al crear Tag RFID: " . $e->getMessage());
            return ["success" => false, "error" => "Error al registrar el tag en la base de datos (posible ID duplicado)."];
        }
    }

    /**
     * Obtiene el listado de todos los tags registrados en el catálogo.
     * @return array Colección con todos los registros de la tabla TAG_RFID.
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT * FROM TAG_RFID ORDER BY fecha_activacion DESC");
            return ["success" => true, "data" => $stmt->fetchAll()];
        } catch (PDOException $e) {
            error_log("Error al listar Tags RFID: " . $e->getMessage());
            return ["success" => false, "error" => "Error al obtener el listado de tags."];
        }
    }

    /**
     * Obtiene el detalle de un tag RFID específico por su ID.
     * @param string $id Identificador único (tag_id) del tag.
     * @return array Detalle del tag encontrado o estructura de error.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM TAG_RFID WHERE tag_id = ?");
            $stmt->execute([$id]);
            $tag = $stmt->fetch();
            
            if (!$tag) {
                return ["success" => false, "error" => "Tag no encontrado."];
            }
            
            return ["success" => true, "data" => $tag];
        } catch (PDOException $e) {
            error_log("Error al obtener detalle del Tag RFID: " . $e->getMessage());
            return ["success" => false, "error" => "Error al obtener el detalle del tag."];
        }
    }

    /**
     * Actualiza el estado físico de un tag (Activo, Inactivo, Extraviado).
     * @param string $id Identificador único del tag.
     * @param string $estado Nuevo estado a asignar.
     * @return array Resultado de la operación.
     */
    public function updateStatus($id, $estado) {
        try {
            $stmt = $this->db->prepare("UPDATE TAG_RFID SET estado = ? WHERE tag_id = ?");
            $stmt->execute([$estado, $id]);
            
            $this->audit->log(1, "Actualizado estado del Tag: $id a $estado", "RFID");
            return ["success" => true];
        } catch (PDOException $e) {
            error_log("Error al actualizar estado del Tag: " . $e->getMessage());
            return ["success" => false, "error" => "Error al actualizar el estado."];
        }
    }

    /**
     * Elimina un tag RFID del sistema.
     * @param string $id Identificador único del tag.
     * @return array Resultado de la operación.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM TAG_RFID WHERE tag_id = ?");
            $stmt->execute([$id]);
            
            $this->audit->log(1, "Eliminado Tag RFID del sistema: $id", "RFID");
            return ["success" => true];
        } catch (PDOException $e) {
            error_log("Error al eliminar Tag: " . $e->getMessage());
            return ["success" => false, "error" => "Error al eliminar el tag."];
        }
    }

    /**
     * Vincula un Tag RFID a un objeto de inventario (Fase 2: Vinculación - PUT).
     * @param string $tag_id Identificador del Tag RFID.
     * @param string $tipo_objeto Tipo del objeto al cual vincular ('Activo'|'Llave'|'Mobiliario').
     * @param int $objeto_id ID numérico del registro del objeto en su respectiva tabla.
     * @return array Estructura de respuesta indicando el éxito.
     */
    public function link($tag_id, $tipo_objeto, $objeto_id) {
        // Iniciamos una transacción para garantizar la atomicidad en la desvinculación previa y vinculación nueva
        $this->db->beginTransaction();
        try {
            // 1. Limpiar vinculaciones previas de este tag_id para evitar conflictos de unicidad
            $this->db->prepare("UPDATE ACTIVO SET tag_id = NULL WHERE tag_id = ?")->execute([$tag_id]);
            $this->db->prepare("UPDATE LLAVE SET tag_id = NULL WHERE tag_id = ?")->execute([$tag_id]);
            $this->db->prepare("UPDATE MOBILIARIO SET tag_id = NULL WHERE tag_id = ?")->execute([$tag_id]);

            // 2. Ejecutar la actualización en la tabla correspondiente al tipo de objeto
            switch ($tipo_objeto) {
                case 'Activo':
                    $query = "UPDATE ACTIVO SET tag_id = ? WHERE act_id = ?";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([$tag_id, $objeto_id]);
                    break;
                case 'Llave':
                    $query = "UPDATE LLAVE SET tag_id = ? WHERE llave_id = ?";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([$tag_id, $objeto_id]);
                    break;
                case 'Mobiliario':
                    $query = "UPDATE MOBILIARIO SET tag_id = ? WHERE mob_id = ?";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([$tag_id, $objeto_id]);
                    break;
                default:
                    throw new PDOException("Tipo de objeto no soportado para vinculación.");
            }

            // Confirmar transacción
            $this->db->commit();

            // Auditoría
            $this->audit->log(1, "Vinculado Tag RFID: $tag_id a $tipo_objeto ID: $objeto_id", "RFID");

            return ["success" => true];
        } catch (PDOException $e) {
            // Revertir cambios ante cualquier error
            $this->db->rollBack();
            error_log("Error en vinculación de Tag: " . $e->getMessage());
            return ["success" => false, "error" => "Error al realizar la vinculación en la base de datos."];
        }
    }
}
