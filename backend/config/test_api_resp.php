<?php
require_once __DIR__ . '/Database.php';
$db = \Config\Database::getConnection();

$input = [
    'esp_id' => 1,
    'us_id' => 1, // Assume valid, will test it
    'num_alumnos' => 10,
    'hora_ent' => '10:00:00',
    'hora_sal' => '12:00:00',
    'motivo' => 'Test frontend catch',
    'fechas_uso' => ['2030-02-01', '2030-02-02']
];

ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['us_id'] = 1; // Fake login

require_once __DIR__ . '/../api/index.php';

$out = ob_get_clean();
echo "OUTPUT WAS:\n---\n$out\n---\n";
