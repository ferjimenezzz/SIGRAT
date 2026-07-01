<?php
/**
 * @file AssetController.php
 * @summary Controlador para la gestión de activos (inventario) en PHP.
 * @description Permite el registro masivo de tags detectados y la administración de dispositivos.
 */


// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
class AssetController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (create)
// ============================================================================
    /**
     * Crea un nuevo activo.
     */

    public function create($data) {
        try {
            // 0. Validación estricta en TAG_RFID (Maestra)
            if (!empty($data['tag_id'])) {
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$data['tag_id']]);
                if (!$checkStmt->fetch()) {
                    return ["success" => false, "error" => "El TAG RFID especificado (" . $data['tag_id'] . ") no se encuentra enrolado en el sistema. Debe enrolarlo manualmente primero."];
                }
                
                // Actualizamos el tipo a Activo ya que ahora está asignado
                $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                $updateRfid->execute([$data['tag_id']]);
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


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (update)
// ============================================================================
    /**
     * Actualiza un activo existente.
     */

    public function update($id, $data) {
        try {
            // 1. Obtener el tag actual del activo
            $stmtCurrent = $this->db->prepare("SELECT tag_id FROM ACTIVO WHERE act_id = ?");
            $stmtCurrent->execute([$id]);
            $currentAsset = $stmtCurrent->fetch();
            $currentTag = $currentAsset ? $currentAsset['tag_id'] : null;

            $newTag = !empty($data['tag_id']) ? $data['tag_id'] : null;

            // 2. Si el tag cambia y el nuevo no es nulo, validamos que exista en TAG_RFID
            if ($newTag !== null && $newTag !== $currentTag) {
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$newTag]);
                if (!$checkStmt->fetch()) {
                    return ["success" => false, "error" => "El TAG RFID especificado (" . $newTag . ") no se encuentra enrolado en el sistema."];
                }
                
                // Actualizamos el tipo a Activo del nuevo tag
                $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                $updateRfid->execute([$newTag]);
            }

            // 3. Actualizamos el activo
            $query = "UPDATE ACTIVO SET tipo=?, marca=?, modelo=?, num_serie=?, num_inv=?, esp_asignado=?, tag_id=? WHERE act_id=?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['tipo'],
                $data['marca'],
                $data['modelo'],
                $data['num_serie'],
                $data['num_inv'],
                !empty($data['esp_asignado']) ? $data['esp_asignado'] : null,
                $newTag,
                $id
            ]);
            
            $this->audit->log(1, "Actualizado activo ID: $id", "INVENTARIO");
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => "Error al actualizar: " . $e->getMessage()];
        }
    }


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (getAll)
// ============================================================================
    /**
     * Obtiene todos los activos.
     */

    public function getAll() {
        return $this->db->query("SELECT * FROM ACTIVO ORDER BY act_id DESC")->fetchAll();
    }


// ============================================================================
// SECCIÓN 6: LÓGICA DE NEGOCIO Y OPERACIÓN (delete)
// ============================================================================
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


// ============================================================================
// SECCIÓN 7: LÓGICA DE NEGOCIO Y OPERACIÓN (bulkSave)
// ============================================================================
    /**
     * Registra un lote de activos.
     */

    public function bulkSave($assets, $tipo) {
        $this->db->beginTransaction();
        try {
            $query = "INSERT INTO ACTIVO (tag_id, tipo, estatus, num_inv) VALUES (?, ?, 'Disponible', ?)";
            $stmt = $this->db->prepare($query);

            foreach ($assets as $asset) {
                // Validación estricta en tabla maestra de RFID
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$asset['tag_id']]);
                if (!$checkStmt->fetch()) {
                    throw new \Exception("El TAG " . $asset['tag_id'] . " no está enrolado en el sistema.");
                }

                $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                $updateRfid->execute([$asset['tag_id']]);

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
