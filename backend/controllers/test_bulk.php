<?php
require_once __DIR__ . '/ReservationController.php';

ob_start();
$db = \Config\Database::getConnection();
$us_id = $db->query("SELECT us_id FROM USUARIO LIMIT 1")->fetchColumn();

$controller = new \Controllers\ReservationController();
$fechas = ['2032-07-01', '2032-07-02'];
$results = [];

foreach ($fechas as $fecha) {
    $data = [
        'esp_id' => 1,
        'us_id' => $us_id,
        'num_alumnos' => 10,
        'hora_ent' => '10:00:00',
        'hora_sal' => '12:00:00',
        'motivo' => 'Test bulk email loop',
        'fecha_uso' => $fecha
    ];
    $res = $controller->create($data, true);
    if (!$res['success']) {
        echo "ERROR CREATING: " . json_encode($res) . "\n";
    }
    $results[] = $res['id'];
}

$controller->sendBulkEmail($us_id, $results, $fechas, 1);

$out = ob_get_clean();

$response = ["success" => true, "ids" => $results];
echo json_encode($response);
echo "\n--- OUTPUT BUFFER ---\n$out\n------------------\n";
