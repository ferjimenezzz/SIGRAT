<?php
/**
 * @file seguridad.php
 * @summary middleware y guardián de seguridad para acceso a páginas del Frontend.
 * @description Verifica la autenticidad de la cookie 'auth_token' y la validez de la sesión del usuario llamando al controlador de autenticación. Si el token expiró o es inválido, destruye la sesión y redirige inmediatamente a login.php.
 * @package Frontend\Middleware
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN DE SESIÓN Y CARGA DE CONTROLADORES
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Importar el controlador de autenticación para verificación criptográfica de tokens
require_once '../backend/controllers/AuthController.php';
use Controllers\AuthController;

// ============================================================================
// SECCIÓN 2: VERIFICACIÓN PRIMARIA DE COOKIE DE SESIÓN
// ============================================================================
// 1. Si no existe la cookie de seguridad 'auth_token', rechazar acceso de inmediato
if (!isset($_COOKIE['auth_token'])) {
    header("Location: login.php");
    exit();
}

// ============================================================================
// SECCIÓN 3: VALIDACIÓN CRIPTOGRÁFICA DEL TOKEN JWT Y MANEJO DE EXPIRACIÓN
// ============================================================================
$authController = new AuthController();
// 2. Validar la firma HMAC y la fecha de caducidad (exp) del JWT de la cookie
$userData = $authController->validateJWT($_COOKIE['auth_token']);

if (!$userData) {
    // Si el token es falso, fue manipulado o ya expiró, destruimos la sesión y limpiamos la cookie
    AuthController::logout();
    header("Location: login.php");
    exit();
}

// ============================================================================
// SECCIÓN 4: SINCRONIZACIÓN DE SESIÓN EN MEMORIA CON DATOS DEL TOKEN
// ============================================================================
// 3. Si el token es válido, vinculamos los datos a la variable superglobal $_SESSION
// Esto asegura que $_SESSION['us_id'] y $_SESSION['rol'] estén siempre disponibles en las vistas
if (!isset($_SESSION['us_id']) && isset($userData['us_id'])) {
    $_SESSION['us_id'] = $userData['us_id'];
    $_SESSION['rol'] = $userData['rol'] ?? '';
    $_SESSION['permisos'] = $userData['permisos'] ?? [];
}
?>