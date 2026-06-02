<?php
require_once __DIR__ . '/config/Database.php';
$db = Config\Database::getConnection();
$stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='visita'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
