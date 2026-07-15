<?php
require_once 'backend/config/Database.php';
$db = Config\Database::getConnection();

$antes = (int)$db->query("SELECT COUNT(*) FROM espacio")->fetchColumn();
echo "=== Registros antes: $antes ===\n\n";

// 1. Agregar columna responsable si no existe
$cols = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='espacio'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('responsable', $cols)) {
    $db->exec("ALTER TABLE espacio ADD COLUMN responsable VARCHAR(150) NULL");
    echo "✅ Columna 'responsable' agregada.\n";
} else {
    echo "ℹ️  Columna 'responsable' ya existe.\n";
}

// 2. Renombrar Gevernova → GE Vernova (solo el nombre, nada más)
$r = $db->exec("UPDATE espacio SET nombre_numero='GE Vernova' WHERE esp_id=35 AND nombre_numero='Gevernova'");
echo "✅ Gevernova → GE Vernova ($r filas)\n";

// 3. Poblar responsable según reglas confirmadas
$updates = [
    [5,  'Leticia Vera'],
    [6,  'Leticia Vera'],
    [10, 'Andrea Sarahí López'],
    [11, 'Andrea Sarahí López'],
    [12, 'Andrea Sarahí López'],
    [13, 'Andrea Sarahí López'],
    [16, 'Salomón'],
    [22, 'Leticia Vera'],
    [23, 'Leticia Vera'],
];

foreach ($updates as [$id, $person]) {
    $personEsc = $db->quote($person);
    $db->exec("UPDATE espacio SET responsable=$personEsc WHERE esp_id=$id");
    $name = $db->query("SELECT nombre_numero FROM espacio WHERE esp_id=$id")->fetchColumn();
    echo "  ✅ ID:$id ($name) → responsable: $person\n";
}

// 4. Verificar integridad
$despues = (int)$db->query("SELECT COUNT(*) FROM espacio")->fetchColumn();
echo "\n=== Registros después: $despues ===\n";
echo ($antes === $despues) ? "✅ NINGÚN registro eliminado ni creado.\n" : "❌ ¡CAMBIO EN CONTEO! Antes:$antes Después:$despues\n";

// 5. Mostrar resultado final de responsables
echo "\n=== Responsables asignados ===\n";
$res = $db->query("SELECT esp_id, edificio, planta, nombre_numero, responsable FROM espacio WHERE responsable IS NOT NULL ORDER BY edificio, planta, nombre_numero");
foreach ($res->fetchAll() as $s) {
    echo "  [{$s['esp_id']}] {$s['edificio']}/{$s['planta']} | {$s['nombre_numero']} → {$s['responsable']}\n";
}
