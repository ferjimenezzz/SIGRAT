<?php
/**
 * @file index.php
 * @summary Punto de entrada único (Router) para la API de SIGRAT en PHP.
 * @description Centraliza las peticiones, gestiona CORS, decodifica JSON y despacha a los controladores correspondientes.
 */
session_start();

// Configuración de encabezados para API REST y CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, x-user-role");

// --- DEBUG LOG PARA ESP32 ---
$logData = date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
$logData .= "Cuerpo: " . file_get_contents("php://input") . "\n";
file_put_contents(__DIR__ . '/debug_esp32.log', $logData, FILE_APPEND);
// ----------------------------

// Manejo de peticiones preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoload manual (Simulando PSR-4 para simplicidad en XAMPP sin Composer)
require_once '../config/Database.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/InviteController.php';
require_once '../controllers/ReservationController.php';
require_once '../controllers/RFIDController.php';
require_once '../controllers/AssetController.php';
require_once '../controllers/LoanController.php';
require_once '../controllers/MaintenanceController.php';

use Controllers\AuthController;
use Controllers\InviteController;
use Controllers\ReservationController;
use Controllers\RFIDController;
use Controllers\AssetController;
use Controllers\LoanController;
use Controllers\MaintenanceController;

// --- CONTROL DE ACCESO MEDIANTE TOKENS (JWT) ---
$auth = new AuthController();
$token = null;

// Obtener token desde la cabecera Authorization o desde la Cookie
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
if ($authHeader && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
    $token = $matches[1];
} elseif (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
}

$userPayload = null;
if ($token) {
    $userPayload = $auth->validateJWT($token);
    if ($userPayload) {
        // Sincronizar payload del token con la sesión PHP para los controladores y rutas
        $_SESSION['us_id'] = $userPayload['us_id'];
        $_SESSION['rol'] = $userPayload['rol'];
        $_SESSION['nombre'] = $userPayload['nombre'];
        $_SESSION['permisos'] = $userPayload['permisos'];
    }
}

// Obtener la ruta de la petición
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

// Extraer el recurso (ej: invites, reservations, hardware)
$apiIndex = array_search('api', $uri);
$resource = null;
if ($apiIndex !== false) {
    if (isset($uri[$apiIndex + 1]) && $uri[$apiIndex + 1] === 'index.php') {
        $resource = $uri[$apiIndex + 2] ?? null;
    } else {
        $resource = $uri[$apiIndex + 1] ?? null;
    }
}

// Clasificar si el endpoint es público o privado
$is_public = false;

// 1. invites/validate es público (GET)
if ($resource === 'invites' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($uri[count($uri)-2]) && $uri[count($uri)-2] === 'validate') {
    $is_public = true;
}

// 2. Consultar disponibilidad es público (GET /reservations)
if ($resource === 'reservations' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['esp_id'])) {
    $is_public = true;
}

// 3. Procesar escaneo y monitor de RFID es público (/hardware/*)
if ($resource === 'hardware') {
    $is_public = true;
}

// 4. Registro de usuario es público (POST /auth/register)
if ($resource === 'auth' && $_SERVER['REQUEST_METHOD'] === 'POST' && end($uri) === 'register') {
    $is_public = true;
}

// Bloquear el acceso a endpoints protegidos si no hay un token válido
if (!$is_public) {
    if (!$userPayload) {
        http_response_code(401);
        echo json_encode(["error" => "No autorizado. Token JWT inválido, expirado o ausente."]);
        exit();
    }
}

// Obtener el cuerpo de la petición si es JSON
$input = json_decode(file_get_contents("php://input"), true);

$response = ["error" => "Recurso no encontrado"];
$status_code = 404;

try {
    switch ($resource) {
        case 'invites':
            $controller = new InviteController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri[count($uri)-1] === 'generate') {
                // Generar invitación (Requiere validación de rol en producción)
                $response = $controller->generate(1, $input['hours_valid'] ?? 24);
                $status_code = 201;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri[count($uri)-2] === 'validate') {
                // Validar código
                $code = $uri[count($uri)-1];
                $data = $controller->validate($code);
                $response = ["valid" => (bool)$data, "invite" => $data];
                $status_code = 200;
            }
            break;

        case 'auth':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri[count($uri)-1] === 'register') {
                $response = $auth->register(
                    $input['nombre'] ?? '', 
                    $input['correo'] ?? '', 
                    $input['telefono'] ?? '', 
                    $input['carrera'] ?? '', 
                    $input['password'] ?? ''
                );
                $status_code = $response['success'] ? 201 : 400;
            }
            break;

        case 'reservations':
            // Módulo de aprobación de reservas
            $action_url = end($uri);
            if (in_array($action_url, ['pending', 'approve', 'reject'])) {
                require_once '../routes.php';
                handleReservationApproval($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
                break;
            }

            $controller = new ReservationController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Asegurar la integridad inyectando el ID de usuario autenticado desde el token JWT
                if (isset($_SESSION['us_id'])) {
                    $input['us_id'] = $_SESSION['us_id'];
                }
                // Crear reservación - El input debe traer esp_id, fecha_uso, hora_ent, hora_sal, etc.
                $response = $controller->create($input);
                $status_code = $response['success'] ? 201 : 400;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['esp_id'])) {
                // Consultar disponibilidad
                $response = $controller->getAvailability($_GET['esp_id'], $_GET['date']);
                $status_code = 200;
            }
            break;

        case 'hardware':
            if ($uri[count($uri)-1] === 'rfid-scan') {
                $controller = new RFIDController();
                // El input debe traer tag_id y lec_id
                $response = $controller->processScan($input['tag_id'], $input['lec_id']);
                $status_code = (isset($response['success']) && $response['success']) ? 200 : 403;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri[count($uri)-1] === 'recent-scans') {
                $controller = new RFIDController();
                $response = $controller->getRecentScans();
                $status_code = 200;
            }
            break;

        case 'assets':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri[count($uri)-1] === 'bulk') {
                $controller = new AssetController();
                $response = $controller->bulkSave($input['assets'], $input['common_category']);
                $status_code = 201;
            }
            break;

        case 'loans':
            $controller = new LoanController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $response = $controller->create($input['act_id'], $input['us_id']);
                $status_code = 201;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                $response = $controller->returnAsset($input['pres_id']);
                $status_code = 200;
            }
            break;

        case 'maintenance':
            $controller = new MaintenanceController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $response = $controller->logMaintenance($input['act_id'], $input['descripcion'], $input['responsable']);
                $status_code = 201;
            }
            break;
    }
} catch (\Exception $e) {
    $response = ["error" => $e->getMessage()];
    $status_code = 500;
}

http_response_code($status_code);
echo json_encode($response);
