<?php
require_once 'backend/config/Database.php';
$db = Config\Database::getConnection();

echo "--- BITACORA ---\n";
$stmt = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'bitacora'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- RESERVA ---\n";
$stmt = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'reserva'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
