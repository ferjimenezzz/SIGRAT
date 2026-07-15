<?php
require_once 'backend/api/db.php';
$stmt = $pdo->query("SELECT esp_id, nombre_numero FROM espacios WHERE nombre_numero LIKE 'Sala Magna%' ORDER BY nombre_numero ASC");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
