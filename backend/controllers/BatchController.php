<?php
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;

class BatchController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    public function assignBulkTags($targetTable, $baseParams, $tags) {
        $this->db->beginTransaction();
        try {
            $inserted = 0;
            foreach ($tags as $tag) {
                // Validar existencia en la tabla maestra
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$tag]);
                if (!$checkStmt->fetch()) {
                    throw new \Exception("El TAG $tag no está enrolado en el sistema.");
                }
                
                if ($targetTable === 'LLAVE') {
                    $espId = !empty($baseParams['esp_id']) ? $baseParams['esp_id'] : null;
                    if (!$espId) throw new \Exception("Para Llaves es obligatorio seleccionar el Espacio.");
                    
                    $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Llave' WHERE tag_id = ?")->execute([$tag]);
                    
                    // Insertamos usando el mismo TAG como rfid_num para garantizar unicidad
                    $stmt = $this->db->prepare("INSERT INTO LLAVE (rfid_num, esp_id, tag_id, estatus) VALUES (?, ?, ?, 'Disponible')");
                    $stmt->execute([$tag, $espId, $tag]);

                } elseif ($targetTable === 'MOBILIARIO') {
                    $tipo = !empty($baseParams['tipo']) ? $baseParams['tipo'] : 'Genérico';
                    $dim = !empty($baseParams['dimensiones']) ? $baseParams['dimensiones'] : '';

                    $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Mobiliario' WHERE tag_id = ?")->execute([$tag]);
                    
                    $stmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, dimensiones, tag_id) VALUES (?, ?, ?)");
                    $stmt->execute([$tipo, $dim, $tag]);

                } elseif ($targetTable === 'ACTIVO') {
                    $tipo = !empty($baseParams['tipo']) ? $baseParams['tipo'] : 'Activo Genérico';
                    $espId = !empty($baseParams['esp_id']) ? $baseParams['esp_id'] : null;
                    
                    $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?")->execute([$tag]);
                    
                    $num_inv = "INV-" . strtoupper(substr(md5($tag . uniqid()), 0, 8));
                    
                    $stmt = $this->db->prepare("INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado) VALUES (?, 'Genérico', 'Genérico', ?, ?, 'Disponible', ?, ?)");
                    $stmt->execute([$tipo, "SN-" . $tag, $num_inv, $tag, $espId]);
                } else {
                    throw new \Exception("Tabla de destino no soportada.");
                }
                
                $inserted++;
            }

            $this->db->commit();
            $this->audit->log(1, "Asignación masiva: $inserted items en $targetTable", "INVENTARIO");
            
            return ["success" => true, "count" => $inserted];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
