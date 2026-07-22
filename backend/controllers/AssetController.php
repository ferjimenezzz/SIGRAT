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
            // 0. Validación estricta en TAG_RFID (Maestra) - Auto-registro si no existe
            if (!empty($data['tag_id'])) {
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$data['tag_id']]);
                if (!$checkStmt->fetch()) {
                    // Auto-registrar el Tag
                    $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                    $insTag->execute([$data['tag_id']]);
                } else {
                    // Actualizamos el tipo a Activo ya que ahora está asignado
                    $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                    $updateRfid->execute([$data['tag_id']]);
                }
            }

            $query = "INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $descVal = !empty($data['descripcion']) ? trim($data['descripcion']) : (!empty($data['software_info']) ? trim($data['software_info']) : null);
            $stmt->execute([
                !empty($data['tipo']) && $data['tipo'] !== 'N/A' ? $data['tipo'] : 'Mobiliario',
                !empty($data['marca']) && $data['marca'] !== 'N/A' ? $data['marca'] : null,
                !empty($data['modelo']) && $data['modelo'] !== 'N/A' ? $data['modelo'] : null,
                !empty($data['num_serie']) && $data['num_serie'] !== 'N/A' ? $data['num_serie'] : null,
                $data['num_inv'] ?? null,
                !empty($data['estatus']) ? $data['estatus'] : 'Disponible',
                !empty($data['tag_id']) ? $data['tag_id'] : null,
                !empty($data['esp_asignado']) ? $data['esp_asignado'] : null,
                !empty($data['imagen_url']) ? trim($data['imagen_url']) : null,
                $descVal,
                !empty($data['responsable']) ? trim($data['responsable']) : null,
                !empty($data['nivel']) ? trim($data['nivel']) : null
            ]);
            
            $id = $this->db->lastInsertId();

            // Sincronizar en la tabla MOBILIARIO si aplica
            $tipoLower = strtolower($data['tipo']);
            $isFurniture = (strpos($tipoLower, 'silla') !== false || strpos($tipoLower, 'mesa') !== false || strpos($tipoLower, 'escritorio') !== false || strpos($tipoLower, 'pizarrón') !== false || strpos($tipoLower, 'pizarron') !== false || strpos($tipoLower, 'mobiliario') !== false || strpos($tipoLower, 'estante') !== false || strpos($tipoLower, 'archivero') !== false);

            if ($isFurniture) {
                $mobStmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, tag_id, act_id, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $mobStmt->execute([
                    !empty($data['tipo']) ? $data['tipo'] : 'Mobiliario',
                    !empty($data['tag_id']) ? $data['tag_id'] : null,
                    $id,
                    !empty($data['imagen_url']) ? trim($data['imagen_url']) : null,
                    $descVal,
                    !empty($data['responsable']) ? trim($data['responsable']) : null,
                    !empty($data['nivel']) ? trim($data['nivel']) : null
                ]);
            }

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
     * Actualiza un activo existente, incluyendo la URL de imagen externa de Cloudinary.
     * @param int $id ID único del activo.
     * @param array $data Datos del activo provenientes del formulario POST.
     * @return array Resultado con clave 'success' y 'error' si aplica.
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
                    // Auto-registrar el Tag
                    $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                    $insTag->execute([$newTag]);
                } else {
                    // Actualizamos el tipo a Activo del nuevo tag
                    $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                    $updateRfid->execute([$newTag]);
                }
            }

            // 3. Actualizamos el activo incluyendo imagen_url y descripcion
            $query = "UPDATE ACTIVO SET tipo=?, marca=?, modelo=?, num_serie=?, num_inv=?, esp_asignado=?, tag_id=?, imagen_url=?, descripcion=?, responsable=?, nivel=? WHERE act_id=?";
            $stmt = $this->db->prepare($query);
            $descVal = !empty($data['descripcion']) ? trim($data['descripcion']) : (!empty($data['software_info']) ? trim($data['software_info']) : null);
            $stmt->execute([
                !empty($data['tipo']) && $data['tipo'] !== 'N/A' ? $data['tipo'] : 'Mobiliario',
                !empty($data['marca']) && $data['marca'] !== 'N/A' ? $data['marca'] : null,
                !empty($data['modelo']) && $data['modelo'] !== 'N/A' ? $data['modelo'] : null,
                !empty($data['num_serie']) && $data['num_serie'] !== 'N/A' ? $data['num_serie'] : null,
                $data['num_inv'] ?? null,
                !empty($data['esp_asignado']) ? $data['esp_asignado'] : null,
                $newTag,
                !empty($data['imagen_url']) ? trim($data['imagen_url']) : null,
                $descVal,
                !empty($data['responsable']) ? trim($data['responsable']) : null,
                !empty($data['nivel']) ? trim($data['nivel']) : null,
                $id
            ]);
            
            $this->audit->log(1, "Actualizado activo ID: $id", "INVENTARIO");

            // Sincronizar actualización en la tabla MOBILIARIO
            $tipoLower = strtolower($data['tipo']);
            $isFurniture = (strpos($tipoLower, 'silla') !== false || strpos($tipoLower, 'mesa') !== false || strpos($tipoLower, 'escritorio') !== false || strpos($tipoLower, 'pizarrón') !== false || strpos($tipoLower, 'pizarron') !== false || strpos($tipoLower, 'mobiliario') !== false || strpos($tipoLower, 'estante') !== false || strpos($tipoLower, 'archivero') !== false);

            $checkMob = $this->db->prepare("SELECT mob_id FROM MOBILIARIO WHERE act_id = ?");
            $checkMob->execute([$id]);
            if ($checkMob->fetch()) {
                $updateMob = $this->db->prepare("UPDATE MOBILIARIO SET tipo = ?, tag_id = ?, imagen_url = ?, descripcion = ?, responsable = ?, nivel = ? WHERE act_id = ?");
                $updateMob->execute([
                    !empty($data['tipo']) ? $data['tipo'] : 'Mobiliario', 
                    $newTag, 
                    !empty($data['imagen_url']) ? trim($data['imagen_url']) : null, 
                    $descVal,
                    !empty($data['responsable']) ? trim($data['responsable']) : null,
                    !empty($data['nivel']) ? trim($data['nivel']) : null,
                    $id
                ]);
            } else if ($isFurniture) {
                $insMob = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, tag_id, act_id, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insMob->execute([
                    !empty($data['tipo']) ? $data['tipo'] : 'Mobiliario', 
                    $newTag, 
                    $id, 
                    !empty($data['imagen_url']) ? trim($data['imagen_url']) : null, 
                    $descVal,
                    !empty($data['responsable']) ? trim($data['responsable']) : null,
                    !empty($data['nivel']) ? trim($data['nivel']) : null
                ]);
            }
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => "Error al actualizar: " . $e->getMessage()];
        }
    }


// ============================================================================
// SECCIÓN 4.1: REGISTRO MASIVO EN LOTE / RANGO (createBatch)
// ============================================================================
    /**
     * Crea múltiples activos en lote (por rango correlativo o copias con el mismo folio patrimonial).
     * @param array $data Datos del formulario incluyendo batch_mode, batch_quantity, batch_inv_base, etc.
     * @return array Resultado con 'success' y 'count' o 'error'.
     */
    public function createBatch($data) {
        try {
            $mode = $data['batch_mode'] ?? 'folio';
            $tipo = !empty($data['tipo']) && $data['tipo'] !== 'N/A' ? $data['tipo'] : 'Otro';
            $marca = !empty($data['marca']) && $data['marca'] !== 'N/A' ? $data['marca'] : null;
            $modelo = !empty($data['modelo']) && $data['modelo'] !== 'N/A' ? $data['modelo'] : null;
            $num_serie_base = trim($data['num_serie'] ?? '');
            if ($num_serie_base === 'N/A') $num_serie_base = '';
            $estatus = $data['estatus'] ?? 'Disponible';
            $esp_asignado = !empty($data['esp_asignado']) ? (int)$data['esp_asignado'] : null;
            $imagen_url = !empty($data['imagen_url']) ? trim($data['imagen_url']) : null;
            $descripcion = !empty($data['descripcion']) ? trim($data['descripcion']) : (!empty($data['software_info']) ? trim($data['software_info']) : null);

            $this->db->beginTransaction();

            $query = "INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);

            $count = 0;

            $tipoLower = strtolower($tipo);
            $isFurniture = (strpos($tipoLower, 'silla') !== false || strpos($tipoLower, 'mesa') !== false || strpos($tipoLower, 'escritorio') !== false || strpos($tipoLower, 'pizarrón') !== false || strpos($tipoLower, 'pizarron') !== false || strpos($tipoLower, 'mobiliario') !== false || strpos($tipoLower, 'estante') !== false || strpos($tipoLower, 'archivero') !== false);
            
            $responsable = !empty($data['responsable']) ? trim($data['responsable']) : null;
            $nivel = !empty($data['nivel']) ? trim($data['nivel']) : null;
            $batch_tags = $data['batch_tags'] ?? [];
            
            $mobStmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, tag_id, act_id, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?)");

            if ($mode === 'folio') {
                // Modo mismo folio (ej. 50 Butacas con folio 18755)
                $inv_base = trim($data['batch_inv_base'] ?? 'S/N');
                $qty = !empty($data['batch_quantity']) ? (int)$data['batch_quantity'] : 1;
                if ($qty <= 0) $qty = 1;
                if ($qty > 200) $qty = 200; // Límite de seguridad de ráfaga

                for ($i = 1; $i <= $qty; $i++) {
                    $serie = !empty($num_serie_base) ? ($qty > 1 ? "{$num_serie_base}-{$i}" : $num_serie_base) : null;
                    
                    $tag = !empty($batch_tags[$i - 1]) ? trim($batch_tags[$i - 1]) : null;
                    if ($tag) {
                        $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                        $checkStmt->execute([$tag]);
                        if (!$checkStmt->fetch()) {
                            $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                            $insTag->execute([$tag]);
                        } else {
                            $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                            $updateRfid->execute([$tag]);
                        }
                    }

                    $stmt->execute([
                        $tipo, $marca, $modelo, $serie, $inv_base, $estatus, $tag, $esp_asignado, $imagen_url, $descripcion, $responsable, $nivel
                    ]);
                    $actId = $this->db->lastInsertId();
                    if ($isFurniture) {
                        $mobStmt->execute([$tipo, $tag, $actId, $imagen_url, $descripcion, $responsable, $nivel]);
                    }
                    $count++;
                }
            } else if ($mode === 'range') {
                // Modo rango numérico (ej. INV-001 a INV-030)
                $prefix = trim($data['batch_prefix'] ?? '');
                $start = !empty($data['batch_start']) ? (int)$data['batch_start'] : 1;
                $end = !empty($data['batch_end']) ? (int)$data['batch_end'] : 1;
                $digits = !empty($data['batch_digits']) ? (int)$data['batch_digits'] : 0;

                if ($start > $end) {
                    $temp = $start;
                    $start = $end;
                    $end = $temp;
                }
                if (($end - $start + 1) > 200) {
                    throw new \Exception("El rango no puede exceder de 200 activos en una sola transacción.");
                }

                for ($i = $start; $i <= $end; $i++) {
                    $formattedNum = str_pad($i, $digits, '0', STR_PAD_LEFT);
                    $inv_base = "{$prefix}{$formattedNum}";
                    $serie = !empty($num_serie_base) ? ($start !== $end ? "{$num_serie_base}-{$i}" : $num_serie_base) : null;
                    
                    $idx = $i - $start;
                    $tag = !empty($batch_tags[$idx]) ? trim($batch_tags[$idx]) : null;
                    if ($tag) {
                        $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                        $checkStmt->execute([$tag]);
                        if (!$checkStmt->fetch()) {
                            $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                            $insTag->execute([$tag]);
                        } else {
                            $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                            $updateRfid->execute([$tag]);
                        }
                    }
                    
                    $stmt->execute([
                        $tipo, $marca, $modelo, $serie, $inv_base, $estatus, $tag, $esp_asignado, $imagen_url, $descripcion, $responsable, $nivel
                    ]);
                    $actId = $this->db->lastInsertId();
                    if ($isFurniture) {
                        $mobStmt->execute([$tipo, $tag, $actId, $imagen_url, $descripcion, $responsable, $nivel]);
                    }
                    $count++;
                }
            }

            $this->db->commit();
            $this->audit->log(1, "Registrado lote por " . ($mode === 'folio' ? 'mismo folio' : 'rango') . " ($count activos de tipo $tipo)", "INVENTARIO");

            return ["success" => true, "count" => $count];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ["success" => false, "error" => $e->getMessage()];
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
                // Validación en tabla maestra de RFID con auto-registro
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$asset['tag_id']]);
                if (!$checkStmt->fetch()) {
                    $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                    $insTag->execute([$asset['tag_id']]);
                } else {
                    $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                    $updateRfid->execute([$asset['tag_id']]);
                }

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
