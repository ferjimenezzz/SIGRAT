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

$query = $_GET['q'] ?? '';
if (strlen(trim($query)) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $db = \Config\Database::getConnection();
    $results = [];
    $likeQuery = '%' . trim($query) . '%';

    // 1. Buscar Activos
    $stmt = $db->prepare("SELECT act_id, marca, modelo, num_serie, tag_id FROM ACTIVO WHERE (marca LIKE ? OR modelo LIKE ? OR num_serie LIKE ? OR tag_id LIKE ?) AND estatus != 'Baja' LIMIT 5");
    $stmt->execute([$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            "type" => "activo",
            "title" => $row['marca'] . " " . $row['modelo'],
            "subtitle" => "Serie: " . $row['num_serie'] . ($row['tag_id'] ? " | TAG: " . $row['tag_id'] : ""),
            "url" => "inventario.php" // Se podría implementar un auto-filtro usando query params
        ];
    }

    // 2. Buscar Usuarios
    $stmt = $db->prepare("SELECT us_id, nombre, apellido, correo, rfc_matricula FROM USUARIO WHERE (nombre LIKE ? OR apellido LIKE ? OR correo LIKE ? OR rfc_matricula LIKE ?) AND estatus != 'Inactivo' LIMIT 5");
    $stmt->execute([$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            "type" => "usuario",
            "title" => $row['nombre'] . " " . $row['apellido'],
            "subtitle" => "Correo: " . $row['correo'] . " | Matrícula: " . $row['rfc_matricula'],
            "url" => "usuarios.php"
        ];
    }

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error del servidor"]);
}
