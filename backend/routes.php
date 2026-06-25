<?php
/**
 * routes.php
 *
 * Backend routing definitions for reservation approval module.
 * Registers API endpoints for approving and rejecting reservations.
 */

require_once __DIR__ . '/ReservationApprovalController.php';

// Simple router function to handle approval actions.
function handleReservationApproval(string $method, string $path)
{
    // Expected patterns:
    // POST /api/reservations/{id}/approve
    // POST /api/reservations/{id}/reject
    $segments = explode('/', trim($path, '/'));
    // Find the index of "reservations" in the URL.
    $resIdx = array_search('reservations', $segments);
    if ($resIdx === false || !isset($segments[$resIdx + 1])) {
        return false; // Not a reservation approval route.
    }

    $reservationId = $segments[$resIdx + 1] ?? null;
    $action = $segments[$resIdx + 2] ?? null;

    // Support GET /reservations/pending or /reservations/approved (no ID)
    if (in_array($reservationId, ['pending', 'approved']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $reservationId;
        $reservationId = null;
    } elseif (!ctype_digit($reservationId) || !in_array($action, ['approve', 'reject', 'cancel'])) {
        return false;
    }

    $controller = new Backend\ReservationApprovalController(getPDO());
    // The session is populated by login.php with 'us_id' and 'rol'.
    $adminId = $_SESSION['us_id'] ?? null;
    $role   = $_SESSION['rol'] ?? null;

    // Fallback: Validate JWT token directly if session is lost
    if (!$adminId && isset($_COOKIE['auth_token'])) {
        require_once __DIR__ . '/controllers/AuthController.php';
        $auth = new \Controllers\AuthController();
        $payload = $auth->validateJWT($_COOKIE['auth_token']);
        if ($payload) {
            $adminId = $payload['us_id'];
            $role = $payload['rol'];
            
            // Repopulate session
            $_SESSION['us_id'] = $adminId;
            $_SESSION['rol'] = $role;
            $_SESSION['nombre'] = $payload['nombre'];
            $_SESSION['permisos'] = $payload['permisos'];
        }
    }

    $userRol = strtoupper(trim((string)$role));
    $isAdmin = strpos($userRol, 'ADMIN') !== false;

    // Authorization check – allow any admin or 'Personal Académico'.
    // Note: Cancel can be done by non-admins as well, so we only restrict approve/reject to admins.
    if (!$isAdmin && $role !== 'Personal Académico' && !in_array($action, ['cancel', 'pending', 'approved'])) {
        http_response_code(403);
        echo json_encode(['error' => "Forbidden: insufficient role or expired session. User Role: " . ($role ?: 'None')]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($action, ['pending', 'approved'])) {
            $data = $controller->getByStatus((int)$adminId, $isAdmin, $action);
            http_response_code(200);
            echo json_encode($data);
        } elseif ($action === 'approve') {
            $newEspId = isset($input['esp_id']) ? (int)$input['esp_id'] : null;
            $controller->approve((int)$reservationId, (int)$adminId, $newEspId);
            $response = ['message' => 'Reservation approved'];
            http_response_code(200);
            echo json_encode($response);
        } elseif ($action === 'reject') {
            $reason = $input['reason'] ?? null;
            $controller->reject((int)$reservationId, (int)$adminId, $reason);
            $response = ['message' => 'Reservation rejected'];
            http_response_code(200);
            echo json_encode($response);
        } elseif ($action === 'cancel') {
            $reason = $input['reason'] ?? 'Cancelada por el usuario';
            $controller->cancel((int)$reservationId, (int)$adminId, $isAdmin, $reason);
            $response = ['message' => 'Reservation cancelled'];
            http_response_code(200);
            echo json_encode($response);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/**
 * Helper to obtain a PDO connection using the existing config.
 *
 * @return PDO
 */
function getPDO(): PDO
{
    require_once __DIR__ . '/config/Database.php';
    return \Config\Database::getConnection();
}
?>
