<?php
/**
 * @file AssetController.php
 * @summary Controlador para la gestión de activos y mobiliario en PHP.
 * @description Permite el registro de activos y mobiliario en tablas separadas.
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

    private function isFurniture($data) {
        if (isset($data['item_type']) && strtolower($data['item_type']) === 'mobiliario') {
            return true;
        }
        $tipoLower = strtolower($data['tipo'] ?? '');
        return (strpos($tipoLower, 'silla') !== false || strpos($tipoLower, 'mesa') !== false || strpos($tipoLower, 'escritorio') !== false || strpos($tipoLower, 'pizarrón') !== false || strpos($tipoLower, 'pizarron') !== false || strpos($tipoLower, 'mobiliario') !== false || strpos($tipoLower, 'estante') !== false || strpos($tipoLower, 'archivero') !== false);
    }

    public function create($data) {
        try {
            if (!empty($data['tag_id'])) {
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$data['tag_id']]);
                if (!$checkStmt->fetch()) {
                    $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                    $insTag->execute([$data['tag_id']]);
                } else {
                    $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                    $updateRfid->execute([$data['tag_id']]);
                }
            }

            $descVal = !empty($data['descripcion']) ? trim($data['descripcion']) : (!empty($data['software_info']) ? trim($data['software_info']) : null);
            $isFurn = $this->isFurniture($data);

            if ($isFurn) {
                $mobStmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, tag_id, imagen_url, descripcion, responsable, nivel, esp_asignado, num_inv) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $mobStmt->execute([
                    !empty($data['tipo']) ? $data['tipo'] : 'Mobiliario',
                    !empty($data['tag_id']) ? $data['tag_id'] : null,
                    !empty($data['imagen_url']) ? trim($data['imagen_url']) : null,
                    $descVal,
                    !empty($data['responsable']) ? trim($data['responsable']) : null,
                    !empty($data['nivel']) ? trim($data['nivel']) : null,
                    !empty($data['esp_asignado']) ? (int)$data['esp_asignado'] : null,
                    $data['num_inv'] ?? null
                ]);
                $id = $this->db->lastInsertId();
                $this->audit->log(1, "Registrado nuevo mobiliario ID: $id (" . $data['tipo'] . ")", "INVENTARIO");
                return ["success" => true, "id" => $id, "type" => "mobiliario"];
            } else {
                $query = "INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    !empty($data['tipo']) && $data['tipo'] !== 'N/A' ? $data['tipo'] : 'Otro',
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
                $this->audit->log(1, "Registrado nuevo activo ID: $id (" . $data['tipo'] . ")", "INVENTARIO");
                return ["success" => true, "id" => $id, "type" => "activo"];
            }
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function update($id, $data) {
        try {
            $isFurn = $this->isFurniture($data);
            $newTag = !empty($data['tag_id']) ? $data['tag_id'] : null;

            if ($newTag !== null) {
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$newTag]);
                if (!$checkStmt->fetch()) {
                    $insTag = $this->db->prepare("INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES (?, 'Activo', 'Activo')");
                    $insTag->execute([$newTag]);
                } else {
                    $updateRfid = $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?");
                    $updateRfid->execute([$newTag]);
                }
            }

            $descVal = !empty($data['descripcion']) ? trim($data['descripcion']) : (!empty($data['software_info']) ? trim($data['software_info']) : null);

            if ($isFurn) {
                $updateMob = $this->db->prepare("UPDATE MOBILIARIO SET tipo = ?, tag_id = ?, imagen_url = ?, descripcion = ?, responsable = ?, nivel = ?, esp_asignado = ?, num_inv = ? WHERE mob_id = ?");
                $updateMob->execute([
                    !empty($data['tipo']) ? $data['tipo'] : 'Mobiliario', 
                    $newTag, 
                    !empty($data['imagen_url']) ? trim($data['imagen_url']) : null, 
                    $descVal,
                    !empty($data['responsable']) ? trim($data['responsable']) : null,
                    !empty($data['nivel']) ? trim($data['nivel']) : null,
                    !empty($data['esp_asignado']) ? (int)$data['esp_asignado'] : null,
                    $data['num_inv'] ?? null,
                    $id
                ]);
                $this->audit->log(1, "Actualizado mobiliario ID: $id", "INVENTARIO");
            } else {
                $query = "UPDATE ACTIVO SET tipo=?, marca=?, modelo=?, num_serie=?, num_inv=?, esp_asignado=?, tag_id=?, imagen_url=?, descripcion=?, responsable=?, nivel=? WHERE act_id=?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    !empty($data['tipo']) && $data['tipo'] !== 'N/A' ? $data['tipo'] : 'Otro',
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
            }
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => "Error al actualizar: " . $e->getMessage()];
        }
    }

    public function createBatch($data) {
        try {
            $mode = $data['batch_mode'] ?? 'folio';
            $tipo = !empty($data['tipo']) && $data['tipo'] !== 'N/A' ? $data['tipo'] : 'Otro';
            $isFurn = $this->isFurniture($data);
            
            $marca = !empty($data['marca']) && $data['marca'] !== 'N/A' ? $data['marca'] : null;
            $modelo = !empty($data['modelo']) && $data['modelo'] !== 'N/A' ? $data['modelo'] : null;
            $num_serie_base = trim($data['num_serie'] ?? '');
            if ($num_serie_base === 'N/A') $num_serie_base = '';
            $estatus = $data['estatus'] ?? 'Disponible';
            $esp_asignado = !empty($data['esp_asignado']) ? (int)$data['esp_asignado'] : null;
            $imagen_url = !empty($data['imagen_url']) ? trim($data['imagen_url']) : null;
            $descripcion = !empty($data['descripcion']) ? trim($data['descripcion']) : (!empty($data['software_info']) ? trim($data['software_info']) : null);
            $responsable = !empty($data['responsable']) ? trim($data['responsable']) : null;
            $nivel = !empty($data['nivel']) ? trim($data['nivel']) : null;
            $batch_tags = $data['batch_tags'] ?? [];

            $this->db->beginTransaction();

            $count = 0;

            if ($mode === 'folio') {
                $inv_base = trim($data['batch_inv_base'] ?? 'S/N');
                $qty = !empty($data['batch_quantity']) ? (int)$data['batch_quantity'] : 1;
                if ($qty <= 0) $qty = 1;
                if ($qty > 200) $qty = 200;

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

                    if ($isFurn) {
                        $mobStmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, tag_id, imagen_url, descripcion, responsable, nivel, esp_asignado, num_inv) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $mobStmt->execute([$tipo, $tag, $imagen_url, $descripcion, $responsable, $nivel, $esp_asignado, $inv_base]);
                    } else {
                        $stmt = $this->db->prepare("INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$tipo, $marca, $modelo, $serie, $inv_base, $estatus, $tag, $esp_asignado, $imagen_url, $descripcion, $responsable, $nivel]);
                    }
                    $count++;
                }
            } else if ($mode === 'range') {
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
                    throw new \Exception("El rango no puede exceder de 200 en una sola transacción.");
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
                    
                    if ($isFurn) {
                        $mobStmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, tag_id, imagen_url, descripcion, responsable, nivel, esp_asignado, num_inv) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $mobStmt->execute([$tipo, $tag, $imagen_url, $descripcion, $responsable, $nivel, $esp_asignado, $inv_base]);
                    } else {
                        $stmt = $this->db->prepare("INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$tipo, $marca, $modelo, $serie, $inv_base, $estatus, $tag, $esp_asignado, $imagen_url, $descripcion, $responsable, $nivel]);
                    }
                    $count++;
                }
            }

            $this->db->commit();
            $this->audit->log(1, "Registrado lote ($count items de tipo $tipo)", "INVENTARIO");

            return ["success" => true, "count" => $count];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function getAll() {
        // Now it's better to do the UNION in the frontend PHP directly or provide a union method.
        // For compatibility if used elsewhere, let's just return ACTIVO here or a combined query.
        $query = "
            SELECT act_id AS id, tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel, 'activo' AS item_type FROM ACTIVO
            UNION ALL
            SELECT mob_id AS id, tipo, NULL AS marca, NULL AS modelo, NULL AS num_serie, num_inv, 'Disponible' AS estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel, 'mobiliario' AS item_type FROM MOBILIARIO
            ORDER BY id DESC
        ";
        return $this->db->query($query)->fetchAll();
    }

    public function delete($id, $type = 'activo') {
        try {
            if ($type === 'mobiliario') {
                $stmt = $this->db->prepare("DELETE FROM MOBILIARIO WHERE mob_id = ?");
                $stmt->execute([$id]);
                $this->audit->log(1, "Eliminado mobiliario ID: $id", "INVENTARIO");
            } else {
                $stmt = $this->db->prepare("DELETE FROM ACTIVO WHERE act_id = ?");
                $stmt->execute([$id]);
                $this->audit->log(1, "Eliminado activo ID: $id", "INVENTARIO");
            }
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function bulkSave($assets, $tipo) {
        $this->db->beginTransaction();
        try {
            $isFurn = $this->isFurniture(['tipo' => $tipo]);
            
            foreach ($assets as $asset) {
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
                
                if ($isFurn) {
                    $stmt = $this->db->prepare("INSERT INTO MOBILIARIO (tag_id, tipo, num_inv) VALUES (?, ?, ?)");
                    $stmt->execute([$asset['tag_id'], $tipo, $num_inv]);
                } else {
                    $stmt = $this->db->prepare("INSERT INTO ACTIVO (tag_id, tipo, estatus, num_inv) VALUES (?, ?, 'Disponible', ?)");
                    $stmt->execute([$asset['tag_id'], $tipo, $num_inv]);
                }
            }

            $this->db->commit();
            return ["success" => true, "count" => count($assets)];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
