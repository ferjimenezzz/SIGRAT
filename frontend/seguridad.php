<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Importar el controlador de autenticación desde el backend
require_once '../backend/controllers/AuthController.php';
use Controllers\AuthController;

// 1. Si ni siquiera existe la cookie, se rechaza de inmediato
if (!isset($_COOKIE['auth_token'])) {
    header("Location: login.php");
    exit();
}

$authController = new AuthController();
// 2. Validar la firma y la expiración del JWT de la cookie
$userData = $authController->validateJWT($_COOKIE['auth_token']);

if (!$userData) {
    // Si el token es falso, fue manipulado o ya expiró, destruimos la sesión y limpiamos la cookie
    AuthController::logout();
    header("Location: login.php");
    exit();
}

// 3. Si el token es válido, vinculamos los datos a la sesión global
// Esto asegura que $_SESSION['us_id'] esté disponible para archivos como usuarios.php
if (!isset($_SESSION['us_id']) && isset($userData['us_id'])) {
    $_SESSION['us_id'] = $userData['us_id'];
}
?>