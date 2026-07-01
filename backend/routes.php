<?php
/**
 * @file routes.php
 * @summary Enrutador secundario y manejador de endpoints para la aprobación de reservas.
 * @description Define rutas modulares para gestionar la aprobación, rechazo y consulta de solicitudes de reservas pendientes por parte de los administradores e institucionales.
 * @package Backend
 */

require_once __DIR__ . '/controllers/ReservationApprovalController.php';

// ============================================================================
// SECCIÓN 1: ENRUTADOR PRINCIPAL PARA APROBACIÓN DE RESERVAS
// ============================================================================
/**
 * Analiza la URI e intercepta peticiones dirigidas al subsistema de reservas.
 * @param string $method Método HTTP (GET, POST, OPTIONS)
 * @param string $path URI o ruta solicitada al servidor
 * @return bool False si la ruta no corresponde a este módulo; termina la ejecución si la procesa.
 */
function handleReservationApproval(string $method, string $path)
{
    // ------------------------------------------------------------------------
    // 1.1. ANÁLISIS DE PATRONES DE RUTA (SEGMENTACIÓN DE URI)
    // ------------------------------------------------------------------------
    // Patrones esperados:
    // POST /api/reservations/{id}/approve
    // POST /api/reservations/{id}/reject
    // GET  /api/reservations/pending
    $segments = explode('/', trim($path, '/'));
    
    // Buscar la palabra clave "reservations" en los segmentos de la URL
    $resIdx = array_search('reservations', $segments);
    if ($resIdx === false || !isset($segments[$resIdx + 1])) {
        return false; // No es una ruta gestionada por este manejador
    }

    $reservationId = $segments[$resIdx + 1] ?? null;
    $action = $segments[$resIdx + 2] ?? null;

    // Soportar endpoints GET para listados: /reservations/pending, /approved, /cancelled
    if (in_array($reservationId, ['pending', 'approved', 'cancelled']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $reservationId;
        $reservationId = null;
    } elseif ((!ctype_digit($reservationId) && strpos($reservationId, 'grp_') !== 0) || !in_array($action, ['approve', 'reject', 'cancel'])) {
        return false; // Formato de ID o acción no válidos
    }

    // ============================================================================
    // SECCIÓN 2: CONTROL DE SESIÓN Y RECUPERACIÓN DE TOKEN JWT (FALLBACK)
    // ============================================================================
    $controller = new Backend\ReservationApprovalController(getPDO());
    $adminId = $_SESSION['us_id'] ?? null;
    $role   = $_SESSION['rol'] ?? null;

    // Fallback: Si la variable de sesión expiró o se perdió, intentar validar la cookie JWT
    if (!$adminId && isset($_COOKIE['auth_token'])) {
        require_once __DIR__ . '/controllers/AuthController.php';
        $auth = new \Controllers\AuthController();
        $payload = $auth->validateJWT($_COOKIE['auth_token']);
        
        if ($payload) {
            $adminId = $payload['us_id'];
            $role = $payload['rol'];
            
            // Restaurar sesión PHP en memoria
            $_SESSION['us_id'] = $adminId;
            $_SESSION['rol'] = $role;
            $_SESSION['nombre'] = $payload['nombre'];
            $_SESSION['permisos'] = $payload['permisos'];
        }
    }

    // ============================================================================
    // SECCIÓN 3: VERIFICACIÓN DE PRIVILEGIOS DE ACCESO (RBAC)
    // ============================================================================
    $userRol = strtoupper(trim((string)$role));
    $isAdmin = strpos($userRol, 'ADMIN') !== false;

    // Permitir acceso a administradores o personal institucional autorizado.
    // Nota: La cancelación ('cancel') puede ser invocada por el dueño de la reserva o directivos.
    if (!$isAdmin && $role !== 'Personal Académico' && !in_array($action, ['cancel', 'pending', 'approved'])) {
        http_response_code(403);
        echo json_encode(['error' => "Acceso denegado: rol insuficiente o sesión expirada. Rol actual: " . ($role ?: 'Ninguno')]);
        exit;
    }

    // ============================================================================
    // SECCIÓN 4: DESPACHO DE ACCIONES HACIA EL CONTROLADOR Y RESPUESTA JSON
    // ============================================================================
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($action, ['pending', 'approved', 'cancelled'])) {
            // 4.1. Consultar listado por estado
            $data = $controller->getByStatus((int)$adminId, $isAdmin, $action);
            http_response_code(200);
            echo json_encode($data);
        } elseif ($action === 'approve') {
            // 4.2. Aprobar solicitud y opcionalmente reasignar espacio físico
            $newEspId = $input['new_esp_id'] ?? null;
            $controller->approve($reservationId, (int)$adminId, $newEspId ? (int)$newEspId : null);
            http_response_code(200);
            echo json_encode(['message' => 'Reserva aprobada exitosamente']);
        } elseif ($action === 'reject') {
            // 4.3. Rechazar solicitud especificando motivo
            $reason = $input['reason'] ?? null;
            $controller->reject($reservationId, (int)$adminId, $reason);
            http_response_code(200);
            echo json_encode(['message' => 'Reserva rechazada']);
        } elseif ($action === 'cancel') {
            // 4.4. Cancelar reserva por parte del usuario o directivo
            $reason = $input['reason'] ?? 'Cancelada por el usuario';
            $controller->cancel($reservationId, (int)$adminId, $isAdmin, $reason);
            http_response_code(200);
            echo json_encode(['message' => 'Reserva cancelada exitosamente']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// SECCIÓN 5: FUNCIONES AUXILIARES DE CONEXIÓN A BASE DE DATOS
// ============================================================================
/**
 * Función auxiliar para obtener una instancia PDO utilizando el Singleton existente.
 * @return PDO Instancia de conexión a MySQL/MariaDB
 */
function getPDO(): PDO
{
    require_once __DIR__ . '/config/Database.php';
    return \Config\Database::getConnection();
}
?>
