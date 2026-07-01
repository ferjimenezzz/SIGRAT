<?php
/**
 * @file RFIDController.php
 * @summary Controlador para el procesamiento de lecturas de hardware (RFID).
 * @description Gestiona el registro de movimientos estrictamente para activos, llaves y mobiliario.
 * Ya no procesa entradas/salidas de usuarios por regla de negocio actualizada.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;
use PDOException;

class RFIDController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Procesa un escaneo de RFID proveniente de una antena.
     * @param string $tag_id ID único del tag detectado.
     * @param int $lec_id ID del dispositivo lector donde se detectó.
     * @return array Resultado del procesamiento.
     */
    public function processScan($tag_id, $lec_id) {
        try {
            // 1. Verificar existencia y estatus del tag en el catálogo maestro
            // IMPORTANTE: Los usuarios ya no están contemplados en este bloque arquitectónico.
            $tagQuery = "SELECT tipo_tag, estado FROM TAG_RFID WHERE tag_id = ?";
            $stmt = $this->db->prepare($tagQuery);
            $stmt->execute([$tag_id]);
            $tag = $stmt->fetch();

            if (!$tag) {
                // Registrar el tag desconocido para posible enrolamiento
                $logLine = $tag_id . '|' . time() . "\n";
                file_put_contents(__DIR__ . '/../api/unknown_tags.log', $logLine, FILE_APPEND);
                return ["success" => false, "error" => "Tag no registrado en el sistema. Acceso denegado."];
            }

            if ($tag['estado'] !== 'Activo') {
                return ["success" => false, "error" => "Alerta: Se detectó un Tag con estado " . $tag['estado']];
            }

            // 2. Lógica de Dirección de Movimiento (Toggle ENTRADA/SALIDA basado en el histórico)
            $lastLogQuery = "SELECT tipo_mov FROM MOVIMIENTO_RFID WHERE tag_id = ? ORDER BY fecha_hora DESC LIMIT 1";
            $stmt = $this->db->prepare($lastLogQuery);
            $stmt->execute([$tag_id]);
            $lastLog = $stmt->fetch();
            
            $nextEvent = ($lastLog && $lastLog['tipo_mov'] === 'ENTRADA') ? 'SALIDA' : 'ENTRADA';

            // 3. Registrar el Evento en la Bitácora de Movimientos RFID
            $insertQuery = "INSERT INTO MOVIMIENTO_RFID (tag_id, lec_id, tipo_mov) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertQuery);
            $stmt->execute([$tag_id, $lec_id, $nextEvent]);

            // 4. Resolver Nombre del Elemento Detectado (Optimizado con Switch para escalabilidad)
            $entityName = 'Elemento Desconocido';
            switch ($tag['tipo_tag']) {
                case 'Activo':
                    $stmt = $this->db->prepare("SELECT CONCAT(tipo, ' ', marca, ' ', modelo) as nombre FROM ACTIVO WHERE tag_id = ?");
                    $stmt->execute([$tag_id]);
                    $asset = $stmt->fetch();
                    if ($asset) $entityName = $asset['nombre'];
                    break;
                case 'Llave':
                    $stmt = $this->db->prepare("SELECT CONCAT('Llave del Espacio ID: ', esp_id) as nombre FROM LLAVE WHERE tag_id = ?");
                    $stmt->execute([$tag_id]);
                    $llave = $stmt->fetch();
                    if ($llave) $entityName = $llave['nombre'];
                    break;
                case 'Mobiliario':
                    $stmt = $this->db->prepare("SELECT tipo as nombre FROM MOBILIARIO WHERE tag_id = ?");
                    $stmt->execute([$tag_id]);
                    $mob = $stmt->fetch();
                    if ($mob) $entityName = $mob['nombre'];
                    break;
            }

            return [
                "success" => true,
                "entity_name" => $entityName,
                "action" => $nextEvent,
                "timestamp" => date('Y-m-d H:i:s')
            ];
        } catch (PDOException $e) {
            error_log("Error en processScan (RFID): " . $e->getMessage());
            return ["success" => false, "error" => "Error interno en la base de datos."];
        }
    }

    /**
     * Obtiene los escaneos más recientes para el monitor en tiempo real.
     * @param int|null $ant_id ID de la antena para filtrar
     * @return array Lista de logs recientes con detalles del lector.
     */
    public function getRecentScans($ant_id = null) {
        try {
            $query = "SELECT m.*, l.ant_id, a.ubicacion 
                      FROM MOVIMIENTO_RFID m
                      JOIN LECTOR l ON m.lec_id = l.lec_id
                      LEFT JOIN ANTENA a ON l.ant_id = a.ant_id";
            
            $params = [];
            if ($ant_id !== null) {
                $query .= " WHERE a.ant_id = :ant_id";
                $params[':ant_id'] = $ant_id;
            }
            
            $query .= " ORDER BY m.fecha_hora DESC LIMIT 15";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return ["success" => true, "data" => $stmt->fetchAll()];
        } catch (PDOException $e) {
            error_log("Error en getRecentScans: " . $e->getMessage());
            return ["success" => false, "error" => "Error al obtener historial de escaneos."];
        }
    }

    /**
     * Obtiene el estado de conexión de todas las antenas.
     */
    public function getAntennasStatus() {
        try {
            // Si last_ping es menos de 2 minutos atrás (120 segundos), la consideramos conectada
            $query = "SELECT ant_id, ubicacion, estatus, last_ping,
                             CASE 
                                WHEN last_ping IS NOT NULL AND last_ping >= NOW() - INTERVAL '120 seconds' THEN 1 
                                ELSE 0 
                             END as is_connected
                      FROM ANTENA";
            $stmt = $this->db->query($query);
            return ["success" => true, "data" => $stmt->fetchAll()];
        } catch (PDOException $e) {
            error_log("Error en getAntennasStatus: " . $e->getMessage());
            return ["success" => false, "error" => "Error al obtener estado de antenas."];
        }
    }

    /**
     * Actualiza el last_ping de una antena (Heartbeat de hardware)
     */
    public function updateAntennaPing($ant_id) {
        if (!$ant_id) return ["success" => false, "error" => "Falta ID de antena"];
        try {
            $stmt = $this->db->prepare("UPDATE ANTENA SET last_ping = CURRENT_TIMESTAMP WHERE ant_id = :ant_id");
            $stmt->execute([':ant_id' => $ant_id]);
            return ["success" => true];
        } catch (PDOException $e) {
            error_log("Error en updateAntennaPing: " . $e->getMessage());
            return ["success" => false, "error" => "Error interno"];
        }
    }

    /**
     * Obtiene el último tag desconocido detectado en los últimos 60 segundos.
     * Útil para enrolamiento desde las antenas IP.
     * @return array
     */
    public function getLatestUnknownTag() {
        $logFile = __DIR__ . '/../api/unknown_tags.log';
        if (!file_exists($logFile)) return ["success" => true, "tag_id" => null];
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) return ["success" => true, "tag_id" => null];
        
        $lastLine = array_pop($lines);
        list($tag_id, $timestamp) = explode('|', $lastLine);
        
        // Retornar solo si fue escaneado en los últimos 60 segundos
        if (time() - $timestamp <= 60) {
            return ["success" => true, "tag_id" => $tag_id, "timestamp" => date('Y-m-d H:i:s', $timestamp)];
        }
        return ["success" => true, "tag_id" => null];
    }
}
?>
