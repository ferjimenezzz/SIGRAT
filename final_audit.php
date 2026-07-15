<?php
require_once 'backend/config/Database.php';
$db = Config\Database::getConnection();

echo "=== VALIDACIÓN FINAL POST-SINCRONIZACIÓN ===\n\n";

// 1. Conteo de espacios (debe ser 40)
$count = $db->query("SELECT COUNT(*) FROM espacio")->fetchColumn();
echo "1. Espacios en BD: $count " . ($count == 40 ? "✅" : "❌") . "\n";

// 2. Verificar espacios PIDET Planta Baja responsables
$maker = $db->query("SELECT responsable FROM espacio WHERE esp_id=16")->fetchColumn();
$talentos = $db->query("SELECT responsable FROM espacio WHERE esp_id=17")->fetchColumn();
echo "2. Maker Space responsable: $maker " . ($maker === 'Salomón' ? "✅" : "❌") . "\n";
echo "   Talentos responsable: " . var_export($talentos, true) . " " . (empty($talentos) ? "✅" : "❌") . "\n";

// 3. Verificar esp_id en map_data.json
$mapData = json_decode(file_get_contents('frontend/assets/map_data.json'), true);
$allPolys = 0;
$withEspId = 0;
foreach ($mapData as $k => $c) {
    foreach ($c['zones'] as $z) {
        $allPolys++;
        if (isset($z['esp_id'])) $withEspId++;
    }
}
echo "3. Polígonos con esp_id: $withEspId / $allPolys " . ($withEspId == $allPolys ? "✅" : "❌") . "\n";

echo "\nSincronización completa finalizada.\n";
