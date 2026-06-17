<?php
// 1. Iniciar sesión y guardar archivo de sesión manualmente
session_start();
$_SESSION['us_id'] = 1;
$session_id = session_id();
session_write_close();

// 2. Hacer la petición cURL usando esa sesión
$data = [
    'esp_id' => 1,
    'hora_ent' => '10:00:00',
    'hora_sal' => '12:00:00',
    'num_alumnos' => 10,
    'motivo' => 'Test curl multiple apache',
    'fechas_uso' => ['2032-05-01', '2032-05-02']
];

$ch = curl_init('http://localhost/creaciones%20antigravity/Estadias/backend/api/index.php/reservations');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: PHPSESSID=' . $session_id
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpcode\n";
echo "CURL Error: $error\n";
echo "Raw Response:\n[$response]\n";
