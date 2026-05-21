<?php
/**
 * routes.php
 *
 * Backend routing definitions for reservation approval module.
 * Registers API endpoints for approving and rejecting reservations.
 */

require_once __DIR__ . '/ReservationApprovalController.php';
require_once __DIR__ . '/../api/index.php'; // Ensure this file is included when API is accessed.

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

    // Support GET /reservations/pending (no ID)
    if ($reservationId === 'pending' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = 'pending';
        $reservationId = null;
    } elseif (!ctype_digit($reservationId) || ($action !== 'approve' && $action !== 'reject')) {
        return false;
    }

    $controller = new Backend\ReservationApprovalController(getPDO());
    // Assuming a session holds the admin user ID and role.
    $adminId = $_SESSION['user_id'] ?? null;
    $role   = $_SESSION['user_role'] ?? null;

    // Authorization check – only admin or manager may act.
    if (!in_array($role, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: insufficient role']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'pending') {
            $pending = $controller->getPending();
            http_response_code(200);
            echo json_encode($pending);
        } elseif ($action === 'approve') {
            $controller->approve((int)$reservationId, (int)$adminId);
            $response = ['message' => 'Reservation approved'];
            http_response_code(200);
            echo json_encode($response);
        } elseif ($action === 'reject') {
            $reason = $input['reason'] ?? null;
            $controller->reject((int)$reservationId, (int)$adminId, $reason);
            $response = ['message' => 'Reservation rejected'];
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
