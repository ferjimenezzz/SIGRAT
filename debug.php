<?php
/**
 * @file debug.php
 * @summary Script de "Prueba de Humo" para verificación de integridad SIGRAT.
 */

header("Content-Type: text/plain");
echo "=== SIGRAT v3.0 - DIAGNÓSTICO DE INTEGRIDAD ===\n\n";

// 1. Verificar Carpetas
$folders = ['frontend', 'backend', 'backend/config', 'backend/controllers', 'backend/api', 'backend/reports'];
foreach ($folders as $f) {
    echo is_dir($f) ? "[OK] Carpeta encontrada: $f\n" : "[ERROR] Carpeta faltante: $f\n";
}

echo "\n";

// 2. Verificar Conexión a Base de Datos
try {
    require_once 'backend/config/Database.php';
    $db = Config\Database::getConnection();
    echo "[OK] Conexión a MySQL exitosa.\n";
    
    // 3. Verificar Conteo de Tablas (Deberían ser 18)
    $tables = $db->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'")->fetchAll(PDO::FETCH_COLUMN);
    $count = count($tables);
    echo ($count >= 18) ? "[OK] Se encontraron $count tablas (Estructura completa).\n" : "[WARNING] Solo se encontraron $count tablas. Verifique mysql_schema.sql.\n";
    
} catch (Exception $e) {
    echo "[CRITICAL] Error de Base de Datos: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Verificar Controladores Críticos
$controllers = [
    'backend/controllers/AuditController.php',
    'backend/controllers/InviteController.php',
    'backend/controllers/CalendarController.php',
    'backend/controllers/SpaceController.php'
];
foreach ($controllers as $c) {
    echo file_exists($c) ? "[OK] Controlador verificado: " . basename($c) . "\n" : "[ERROR] Falta controlador: " . basename($c) . "\n";
}

echo "\n";

// 5. Verificar Puntos de Entrada
echo file_exists('index.php') ? "[OK] Root index.php presente.\n" : "[ERROR] Root index.php faltante.\n";
echo file_exists('frontend/index.php') ? "[OK] Frontend index.php presente.\n" : "[ERROR] Frontend index.php faltante.\n";

echo "\n=== DIAGNÓSTICO FINALIZADO ===";
