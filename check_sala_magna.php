<?php
require_once 'backend/config/Database.php';
$db = \Config\Database::getConnection();
$stmt = $db->query("SELECT esp_id, nombre_numero, edificio, tipo, acceso, capacidad, estatus FROM ESPACIO WHERE nombre_numero LIKE 'Sala Magna%' ORDER BY nombre_numero");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) { echo implode(' | ', $r) . PHP_EOL; }
