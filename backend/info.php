<?php
require_once 'c:/xampp/htdocs/creaciones antigravity/Estadias/backend/config/Database.php';
$db = Config\Database::getConnection();
$stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='visita'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
