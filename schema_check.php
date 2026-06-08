<?php
require_once 'backend/config/Database.php';
$db = Config\Database::getConnection();
$stmt = $db->query("
    SELECT table_name, column_name, data_type, character_maximum_length 
    FROM information_schema.columns 
    WHERE table_name = 'espacio';
");
print_r($stmt->fetchAll());
