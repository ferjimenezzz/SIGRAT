<?php
session_start();
require_once '../config/Database.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['us_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autorizado"]);
    exit();
}

$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-5 seconds'));

try {
    $db = \Config\Database::getConnection();
    
    // Obtener movimientos de RFID posteriores al last_check
    $stmt = $db->prepare("
        SELECT m.mov_id, m.tipo_mov, m.fecha_hora, a.marca, a.modelo 
        FROM MOVIMIENTO_RFID m
        LEFT JOIN ACTIVO a ON m.act_id = a.act_id
        WHERE m.fecha_hora > ?
        ORDER BY m.fecha_hora ASC
    ");
    $stmt->execute([$last_check]);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "current_time" => date('Y-m-d H:i:s'),
        "scans" => $scans
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de base de datos"]);
}
