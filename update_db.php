<?php
require_once 'backend/config/Database.php';
$db = Config\Database::getConnection();

try {
    $db->exec("ALTER TABLE ESPACIO ADD COLUMN acceso_tipo VARCHAR(50) DEFAULT 'General'");
    echo "Columna acceso_tipo añadida.\n";
} catch (Exception $e) {
    echo "Error acceso_tipo: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE ESPACIO ADD COLUMN division_restringida VARCHAR(100) NULL");
    echo "Columna division_restringida añadida.\n";
} catch (Exception $e) {
    echo "Error division_restringida: " . $e->getMessage() . "\n";
}
