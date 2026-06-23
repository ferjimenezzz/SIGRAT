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

try {
    // Se agregan las columnas necesarias para el flujo de aprobación de reservas
    $db->exec("ALTER TABLE reserva ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
    $db->exec("ALTER TABLE reserva ADD COLUMN approved_by INT(11) NULL");
    $db->exec("ALTER TABLE reserva ADD COLUMN approved_at DATETIME NULL");
    // Se ignora el error si las columnas ya existen
    echo "Columnas de workflow de reservas añadidas.\n";
} catch (Exception $e) {
    echo "Error en workflow de reservas: " . $e->getMessage() . "\n";
}
