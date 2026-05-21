<?php
/**
 * ReservationApprovalController.php
 *
 * Handles approval and rejection of reservation requests.
 * Implements SOLID principles and uses PDO with prepared statements.
 */

namespace Backend;

use PDO;
use Exception;

class ReservationApprovalController
{
    /** @var PDO $pdo Database connection */
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PDO $pdo PDO instance connected to the SIGRAT database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Approve a reservation.
     *
     * @param int $reservationId ID of the reservation to approve.
     * @param int $adminId       ID of the admin performing the action.
     * @throws Exception If the reservation cannot be approved.
     */
    public function approve(int $reservationId, int $adminId): void
    {
        // Verify reservation exists and is pending
        $stmt = $this->pdo->prepare(
            "SELECT status FROM reserva WHERE re_id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $reservationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Reservation not found');
        }
        if ($row['status'] !== 'pending') {
            throw new Exception('Only pending reservations can be approved');
        }

        // Update status, audit fields
        $update = $this->pdo->prepare(
            "UPDATE reserva SET status = 'approved', approved_by = :admin, approved_at = NOW() WHERE re_id = :id"
        );
        $update->execute([':admin' => $adminId, ':id' => $reservationId]);
        $this->logAction($adminId, 'Aprobó reserva', 'reserva');
    }

    /**
     * Reject a reservation.
     *
     * @param int         $reservationId ID of the reservation to reject.
     * @param int         $adminId       ID of the admin performing the action.
     * @param string|null $reason        Optional reason for rejection.
     * @throws Exception If the reservation cannot be rejected.
     */
    public function reject(int $reservationId, int $adminId, ?string $reason = null): void
    {
        // Verify reservation exists and is pending
        $stmt = $this->pdo->prepare(
            "SELECT status FROM reserva WHERE re_id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $reservationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Reservation not found');
        }
        if ($row['status'] !== 'pending') {
            throw new Exception('Only pending reservations can be rejected');
        }

        // Update status, audit fields
        $update = $this->pdo->prepare(
            "UPDATE reserva SET status = 'rejected', approved_by = :admin, approved_at = NOW() WHERE re_id = :id"
        );
        $update->execute([':admin' => $adminId, ':id' => $reservationId]);
        $this->logAction($adminId, 'Rechazó reserva' . ($reason ? ': ' . $reason : ''), 'reserva');
    }

    /**
     * Log an action into the bitacora table.
     *
     * @param int    $userId   ID of the user performing the action.
     * @param string $action   Description of the action.
     * @param string $module   Module affected.
     */
    private function logAction(int $userId, string $action, string $module): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO bitacora (us_id, accion, modulo_afectado) VALUES (:uid, :act, :mod)"
        );
        $stmt->execute([':uid' => $userId, ':act' => $action, ':mod' => $module]);
    }
}
?>
