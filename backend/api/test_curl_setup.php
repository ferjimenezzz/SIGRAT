<?php
session_start();
$_SESSION['us_id'] = 1; // Fake login as admin
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/creaciones%20antigravity/Estadias/backend/api/index.php/reservations';

// Simulate JSON input
$input = [
    'esp_id' => 1,
    'hora_ent' => '10:00:00',
    'hora_sal' => '12:00:00',
    'num_alumnos' => 10,
    'motivo' => 'Test curl multiple',
    'fechas_uso' => ['2032-05-01', '2032-05-02']
];

// Mock php://input
$tmpFile = tempnam(sys_get_temp_dir(), 'mock_input');
file_put_contents($tmpFile, json_encode($input));

// Override input reading if possible... wait, index.php uses file_get_contents('php://input'). We can't override that easily without runkit.
// Let's just do an actual cURL request but pass the session cookie!
