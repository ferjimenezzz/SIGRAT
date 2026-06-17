<?php
$data = [
    'esp_id' => 1,
    'hora_ent' => '10:00:00',
    'hora_sal' => '12:00:00',
    'num_alumnos' => 10,
    'motivo' => 'Test curl',
    'fechas_uso' => ['2030-03-01', '2030-03-02']
];

$ch = curl_init('http://localhost/creaciones%20antigravity/Estadias/backend/api/index.php/reservations');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: PHPSESSID=test' // We might need a real session, but let's see if it just returns JSON
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpcode\n";
echo "Response: $response\n";
