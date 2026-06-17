<?php
// Mock PHP script to test the exact API response
$ch = curl_init('http://localhost/creaciones%20antigravity/Estadias/backend/api/index.php/reservations');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

$data = [
    'esp_id' => 1,
    'hora_ent' => '10:00:00',
    'hora_sal' => '12:00:00',
    'num_alumnos' => 10,
    'motivo' => 'Test bulk email',
    'fechas_uso' => ['2032-06-01', '2032-06-02']
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
echo "RESPONSE:\n[$response]\n";
