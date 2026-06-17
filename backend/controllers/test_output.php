<?php
require_once __DIR__ . '/ReservationController.php';

ob_start();
$controller = new \Controllers\ReservationController();
$data = [
    'esp_id' => 1,
    'us_id' => 1,
    'num_alumnos' => 10,
    'hora_ent' => '10:00:00',
    'hora_sal' => '12:00:00',
    'motivo' => 'Test',
    'fecha_uso' => '2031-01-01'
];
$res = $controller->create($data);
$out = ob_get_clean();

echo "OUTPUT:\n[$out]\nJSON:\n" . json_encode($res);
