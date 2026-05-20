<?php
/**
 * @file logout.php
 * @summary Finaliza la sesión del usuario eliminando el token JWT.
 */
require_once __DIR__ . '/../backend/controllers/AuthController.php';
use Controllers\AuthController;

// Ejecutar limpieza profunda de cookies y sesión
AuthController::logout();

// Redirigir al login para confirmar que se ha salido
header("Location: login.php");
exit();
