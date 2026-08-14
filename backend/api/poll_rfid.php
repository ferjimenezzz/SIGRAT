<?php
/**
 * @file poll_rfid.php
 * @summary Endpoint API para el sondeo (polling) asíncrono de movimientos y eventos RFID.
 * @description Consulta periódicamente nuevos registros en la base de datos detectados desde una marca de tiempo específica ('last_check'), permitiendo sincronizar la interfaz del usuario en tiempo real con las lecturas físicas de las antenas IP/ESP32.
 * @package Backend\API
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN DE SESIÓN Y ENLACE DE BASE DE DATOS
// ============================================================================
// Iniciar sesión para validar identidad del cliente web que consulta el polling
session_start();

// Importar patrón Singleton de conexión PDO
require_once '../config/Database.php';

// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CABECERAS HTTP PARA SONDEO REST
// ============================================================================
// Permitir solicitudes transversales (CORS) y especificar respuesta JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ============================================================================
// SECCIÓN 3: CONTROL DE ACCESO (MIDDLEWARE) Y PARÁMETRO TEMPORAL
// ============================================================================
// 1. Bloquear acceso si la petición no proviene de una sesión institucional logueada
if (!isset($_SESSION['us_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autorizado"]);
    exit();
}

// 2. Obtener la marca de tiempo de la última consulta realizada por el navegador
// Si no se especifica, por defecto busca los eventos de los últimos 5 segundos
$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-5 seconds'));

// ============================================================================
// SECCIÓN 4: CONSULTA Y RETORNO DE EVENTOS DE HARDWARE RECIENTES
// ============================================================================
try {
    // Obtener instancia de base de datos
    $db = \Config\Database::getConnection();
    
    // Consultar movimientos de RFID que ocurrieron exactamente después del 'last_check'
    // Se une con la tabla ACTIVO para retornar metadatos descriptivos (marca, modelo)
    $stmt = $db->prepare("
        SELECT m.mov_id, m.tipo_mov, m.fecha_hora, a.marca, a.modelo 
        FROM MOVIMIENTO_RFID m
        LEFT JOIN ACTIVO a ON m.act_id = a.act_id
        WHERE m.fecha_hora > ?
        ORDER BY m.fecha_hora ASC
    ");
    $stmt->execute([$last_check]);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retornar las lecturas y la hora oficial del servidor como nuevo punto de referencia para el próximo ciclo
    echo json_encode([
        "success" => true,
        "current_time" => date('Y-m-d H:i:s'),
        "scans" => $scans
    ]);
} catch (Exception $e) {
    // Capturar fallo transaccional o de conexión
    http_response_code(500);
    echo json_encode(["error" => "Error de base de datos"]);
}
