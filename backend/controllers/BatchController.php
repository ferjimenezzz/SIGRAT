<?php
/**
 * @file BatchController.php
 * @summary Controlador para operaciones masivas por lotes (Batch Processing).
 * @description Gestiona la asignación masiva de etiquetas RFID, importación e inserción por lotes de activos, usuarios y espacios, optimizando las transacciones PDO con bloques try/catch y rollbacks para garantizar la atomicidad (ACID).
 * @package Controllers
 */
namespace Controllers;

// ============================================================================
// SECCIÓN 1: IMPORTACIÓN DE DEPENDENCIAS Y CONTROLADORES BASE
// ============================================================================
require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;

// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE BATCHCONTROLLER E INICIALIZACIÓN
// ============================================================================
class BatchController {
    /** @var PDO Conexión a la base de datos MySQL en modo excepción */
    private $db;
    /** @var AuditController Instancia del registrador de eventos inmutables */
    private $audit;

    /**
     * Constructor del controlador de lotes.
     * Inicializa la conexión PDO Singleton y la auditoría forense.
     */
    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }

    // ============================================================================
    // SECCIÓN 3: MÉTODO DE ASIGNACIÓN MASIVA DE ETIQUETAS RFID CON TRANSACCIÓN ACID
    // ============================================================================
    /**
     * Asigna un arreglo masivo de etiquetas RFID hacia activos, llaves o mobiliario.
     * @param string $targetTable Tabla relacional destino ('LLAVE', 'MOBILIARIO', 'ACTIVO')
     * @param array $baseParams Parámetros compartidos para todo el lote (ej. ubicación)
     * @param array $tags Arreglo con los UIDs de las etiquetas a procesar
     * @return array Arreglo asociativo con el estado de éxito o error transaccional
     */
    public function assignBulkTags($targetTable, $baseParams, $tags) {
        // ------------------------------------------------------------------------
        // 3.1. INICIO DE TRANSACCIÓN ATÓMICA PDO (ACID)
        // ------------------------------------------------------------------------
        // Iniciar transacción para asegurar que si un tag falla, no se guarde ninguno a medias
        $this->db->beginTransaction();
        
        try {
            $inserted = 0;
            
            // ------------------------------------------------------------------------
            // 3.2. RECORRIDO Y VALIDACIÓN MAESTRA POR CADA TAG RFID
            // ------------------------------------------------------------------------
            foreach ($tags as $tag) {
                // Verificar que el tag esté registrado y catalogado en la tabla maestra TAG_RFID
                $checkStmt = $this->db->prepare("SELECT tag_id FROM TAG_RFID WHERE tag_id = ?");
                $checkStmt->execute([$tag]);
                if (!$checkStmt->fetch()) {
                    throw new \Exception("El TAG $tag no está enrolado en el sistema.");
                }
                
                // ------------------------------------------------------------------------
                // 3.3. DESPACHO POR TIPO DE ENTIDAD DESTINO
                // ------------------------------------------------------------------------
                if ($targetTable === 'LLAVE') {
                    // Procesar inserción y asignación de Llave Institucional
                    $espId = !empty($baseParams['esp_id']) ? $baseParams['esp_id'] : null;
                    if (!$espId) throw new \Exception("Para Llaves es obligatorio seleccionar el Espacio.");
                    
                    // Actualizar el catálogo de tags para reflejar su nueva naturaleza
                    $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Llave' WHERE tag_id = ?")->execute([$tag]);
                    
                    // Insertamos usando el mismo TAG como rfid_num para garantizar unicidad
                    $stmt = $this->db->prepare("INSERT INTO LLAVE (rfid_num, esp_id, tag_id, estatus) VALUES (?, ?, ?, 'Disponible')");
                    $stmt->execute([$tag, $espId, $tag]);

                } elseif ($targetTable === 'MOBILIARIO') {
                    // Procesar inserción de Mobiliario (Sillas, Mesas, Pizarrones)
                    $tipo = !empty($baseParams['tipo']) ? $baseParams['tipo'] : 'Genérico';
                    $dim = !empty($baseParams['dimensiones']) ? $baseParams['dimensiones'] : '';

                    $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Mobiliario' WHERE tag_id = ?")->execute([$tag]);
                    
                    $stmt = $this->db->prepare("INSERT INTO MOBILIARIO (tipo, dimensiones, tag_id) VALUES (?, ?, ?)");
                    $stmt->execute([$tipo, $dim, $tag]);

                } elseif ($targetTable === 'ACTIVO') {
                    // Procesar inserción de Activo Tecnológico (Laptops, Proyectores)
                    $tipo = !empty($baseParams['tipo']) ? $baseParams['tipo'] : 'Activo Genérico';
                    $espId = !empty($baseParams['esp_id']) ? $baseParams['esp_id'] : null;
                    
                    $this->db->prepare("UPDATE TAG_RFID SET tipo_tag = 'Activo' WHERE tag_id = ?")->execute([$tag]);
                    
                    // Generar número de inventario institucional único y determinista
                    $num_inv = "INV-" . strtoupper(substr(md5($tag . uniqid()), 0, 8));
                    
                    $stmt = $this->db->prepare("INSERT INTO ACTIVO (tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado) VALUES (?, 'Genérico', 'Genérico', ?, ?, 'Disponible', ?, ?)");
                    $stmt->execute([$tipo, "SN-" . $tag, $num_inv, $tag, $espId]);
                } else {
                    throw new \Exception("Tabla de destino no soportada en el sistema.");
                }
                
                $inserted++;
            }

            // ------------------------------------------------------------------------
            // 3.4. CONFIRMACIÓN DEFINITIVA Y AUDITORÍA FORENSE
            // ------------------------------------------------------------------------
            // Confirmar todas las inserciones y modificaciones en la base de datos
            $this->db->commit();
            
            // Registrar acción en la bitácora del sistema
            $this->audit->log(1, "Asignación masiva: $inserted items en $targetTable", "INVENTARIO");
            
            return ["success" => true, "count" => $inserted];
        } catch (\Exception $e) {
            // ------------------------------------------------------------------------
            // 3.5. MANEJO DE ERRORES Y ROLLBACK TRANSACCIONAL
            // ------------------------------------------------------------------------
            // Ante cualquier fallo, revertir todos los cambios realizados durante el bucle
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
