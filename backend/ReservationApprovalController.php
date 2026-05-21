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
     * Get all pending reservations.
     *
     * @return array List of pending reservations.
     */
    public function getPending(): array
    {
        // Join with usuario and espacio to get readable names
        $stmt = $this->pdo->prepare("
            SELECT r.re_id, r.fecha_uso, r.hora_ent, r.hora_sal, r.status,
                   u.nombre AS usuario_nombre, e.nombre_numero AS espacio_nombre
            FROM reserva r
            LEFT JOIN usuario u ON r.us_id = u.us_id
            LEFT JOIN espacio e ON r.esp_id = e.esp_id
            WHERE r.status = 'pending'
            ORDER BY r.fecha_uso ASC, r.hora_ent ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        try {
            $this->pdo->beginTransaction();

            // Verify reservation exists and is pending
            $stmt = $this->pdo->prepare(
                "SELECT status, esp_id, fecha_uso, hora_ent, hora_sal FROM reserva WHERE re_id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $reservationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('La reserva no existe.');
            }
            if ($row['status'] !== 'pending') {
                throw new Exception('Solo las reservas pendientes pueden ser aprobadas.');
            }

            // Check for overlapping approved reservations
            $overlapStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM reserva 
                 WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso 
                 AND status = 'approved' AND hora_ent < :hora_sal AND hora_sal > :hora_ent"
            );
            $overlapStmt->execute([
                ':esp_id' => $row['esp_id'],
                ':fecha_uso' => $row['fecha_uso'],
                ':hora_sal' => $row['hora_sal'],
                ':hora_ent' => $row['hora_ent']
            ]);
            
            if ($overlapStmt->fetchColumn() > 0) {
                throw new Exception('El espacio ya cuenta con una reservación aprobada en este horario.');
            }

            // Update status to approved
            $update = $this->pdo->prepare(
                "UPDATE reserva SET status = 'approved', estatus = 'Aprobada', approved_by = :admin, approved_at = NOW() WHERE re_id = :id"
            );
            $update->execute([':admin' => $adminId, ':id' => $reservationId]);
            $this->logAction($adminId, 'Aprobó reserva #' . $reservationId, 'reserva');

            // Automatically reject overlapping pending reservations
            $rejectStmt = $this->pdo->prepare(
                "UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', approved_by = :admin, approved_at = NOW() 
                 WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso 
                 AND status = 'pending' AND hora_ent < :hora_sal AND hora_sal > :hora_ent AND re_id != :id"
            );
            $rejectStmt->execute([
                ':admin' => $adminId,
                ':esp_id' => $row['esp_id'],
                ':fecha_uso' => $row['fecha_uso'],
                ':hora_sal' => $row['hora_sal'],
                ':hora_ent' => $row['hora_ent'],
                ':id' => $reservationId
            ]);

            $rejectedCount = $rejectStmt->rowCount();
            if ($rejectedCount > 0) {
                $this->logAction($adminId, "Rechazo automático de $rejectedCount reserva(s) por empalme con reserva #" . $reservationId, 'reserva');
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
            "UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', approved_by = :admin, approved_at = NOW() WHERE re_id = :id"
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
