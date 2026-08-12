<?php
session_start();
require_once '../backend/config/Database.php';
require_once '../backend/controllers/AuthController.php';

$auth = new \Controllers\AuthController();
$payload = [
    'us_id' => 10,
    'nombre' => 'Laura Michelle Escamilla Barrera',
    'rol' => 'Maestro',
    'genero' => 'Femenino',
    'permisos' => []
];
$token = $auth->generateJWT($payload);
setcookie('auth_token', $token, time() + (60 * 60 * 8), '/', '', false, true);

$_SESSION['us_id'] = 10;
$_SESSION['nombre'] = 'Laura Michelle Escamilla Barrera';
$_SESSION['rol'] = 'Maestro';
$_SESSION['genero'] = 'Femenino';
$_SESSION['permisos'] = [];

header("Location: auditoria.php");
exit();
