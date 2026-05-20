<?php
/**
 * @file index.php
 * @summary Punto de entrada único (Router) para la API de SIGRAT en PHP.
 * @description Centraliza las peticiones, gestiona CORS, decodifica JSON y despacha a los controladores correspondientes.
 */

// Configuración de encabezados para API REST y CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, x-user-role");

// Manejo de peticiones preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoload manual (Simulando PSR-4 para simplicidad en XAMPP sin Composer)
require_once '../config/Database.php';
require_once '../controllers/InviteController.php';
require_once '../controllers/ReservationController.php';
require_once '../controllers/RFIDController.php';
require_once '../controllers/AssetController.php';
require_once '../controllers/LoanController.php';
require_once '../controllers/MaintenanceController.php';

use Controllers\InviteController;
use Controllers\ReservationController;
use Controllers\RFIDController;
use Controllers\AssetController;
use Controllers\LoanController;
use Controllers\MaintenanceController;

// Obtener la ruta de la petición
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

// Extraer el recurso (ej: invites, reservations, hardware)
// Dependiendo de la configuración de Apache, el índice puede variar. 
// Asumimos /Estadias/backend/api/index.php/recurso
$resource = $uri[array_search('api', $uri) + 1] ?? null;

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

        case 'reservations':
            $controller = new ReservationController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $status_code = 200;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri[count($uri)-1] === 'recent-scans') {
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
