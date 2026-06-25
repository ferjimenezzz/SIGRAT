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

require_once __DIR__ . '/../services/EmailService.php';

class ReservationApprovalController
{
    /** @var PDO $pdo Database connection */
    private PDO $pdo;
    private $emailService;

    /**
     * Constructor.
     *
     * @param PDO $pdo PDO instance connected to the SIGRAT database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->emailService = new \Services\EmailService();
    }

    /**
     * Get reservations by status for the approval module.
     *
     * @param int $userId ID of the user.
     * @param bool $isAdmin Whether the user is an admin.
     * @param string $status The status to filter by (e.g., 'pending', 'approved').
     * @return array List of reservations.
     */
    public function getByStatus(int $userId, bool $isAdmin, string $status = 'pending'): array
    {
        // Admins see all by status, regular users see their own filtered by status
        $where = $isAdmin ? "r.status = :status" : "r.us_id = :uid AND r.status = :status";
        // Join with usuario and espacio to get readable names
        $stmt = $this->pdo->prepare("
            SELECT r.re_id, r.fecha_uso, r.hora_ent, r.hora_sal, r.status,
                   u.nombre AS usuario_nombre, e.nombre_numero AS espacio_nombre
            FROM reserva r
            LEFT JOIN usuario u ON r.us_id = u.us_id
            LEFT JOIN espacio e ON r.esp_id = e.esp_id
            WHERE $where
            ORDER BY r.fecha_uso DESC, r.hora_ent DESC
            LIMIT 200
        ");
        
        $params = [':status' => $status];
        if (!$isAdmin) {
            $params[':uid'] = $userId;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve a reservation.
     *
     * @param int $reservationId ID of the reservation to approve.
     * @param int $adminId       ID of the admin performing the action.
     * @param int|null $newEspId  Optional new space ID to reassign.
     * @throws Exception If the reservation cannot be approved.
     */
    public function approve(int $reservationId, int $adminId, ?int $newEspId = null): void
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

            $targetEspId = $newEspId ? $newEspId : $row['esp_id'];

            // Check for overlapping approved reservations using target space
            $overlapStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM reserva 
                 WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso 
                 AND status = 'approved' AND hora_ent < :hora_sal AND hora_sal > :hora_ent"
            );
            $overlapStmt->execute([
                ':esp_id' => $targetEspId,
                ':fecha_uso' => $row['fecha_uso'],
                ':hora_sal' => $row['hora_sal'],
                ':hora_ent' => $row['hora_ent']
            ]);
            
            if ($overlapStmt->fetchColumn() > 0) {
                throw new Exception('El espacio seleccionado ya cuenta con una reservación aprobada en este horario.');
            }

            // Update status to approved, and update space if changed
            $update = $this->pdo->prepare(
                "UPDATE reserva SET status = 'approved', estatus = 'Aprobada', esp_id = :esp_id, approved_by = :admin, approved_at = NOW() WHERE re_id = :id"
            );
            $update->execute([':esp_id' => $targetEspId, ':admin' => $adminId, ':id' => $reservationId]);
            $this->logAction($adminId, 'Aprobó reserva #' . $reservationId . ($newEspId ? " (Reasignada a espacio $newEspId)" : ""), 'reserva');

            // Notify user
            try {
                require_once __DIR__ . '/NotificationController.php';
                $notifCtrl = new \Controllers\NotificationController();
                $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
                $stmtUser->execute([':id' => $reservationId]);
                $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
                if ($usuario) {
                    $resUsId = $usuario['us_id'];
                    $correo = $usuario['correo'];
                    $notifCtrl->createNotification($resUsId, 'Reserva', 'Tu reserva #' . $reservationId . ' ha sido aprobada.', 'espacios.php');
                    
                    if ($correo) {
                        $this->emailService->sendReservationApproved($correo, $reservationId);
                    }
                }
            } catch (Exception $e) {
                error_log("Error notificando aprobación (ApprovalController): " . $e->getMessage());
            }

            // Automatically reject overlapping pending reservations
            $rejectStmt = $this->pdo->prepare(
                "UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', approved_by = :admin, approved_at = NOW() 
                 WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso 
                 AND status = 'pending' AND hora_ent < :hora_sal AND hora_sal > :hora_ent AND re_id != :id"
            );
            $rejectStmt->execute([
                ':admin' => $adminId,
                ':esp_id' => $targetEspId,
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

        // Notify user
        try {
            require_once __DIR__ . '/NotificationController.php';
            $notifCtrl = new \Controllers\NotificationController();
            $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
            $stmtUser->execute([':id' => $reservationId]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($usuario) {
                $resUsId = $usuario['us_id'];
                $correo = $usuario['correo'];
                $msg = 'Tu reserva #' . $reservationId . ' ha sido rechazada.';
                if ($reason) $msg .= ' Motivo: ' . $reason;
                $notifCtrl->createNotification($resUsId, 'Reserva', $msg, 'espacios.php');
                
                if ($correo) {
                    $this->emailService->sendReservationRejected($correo, $reservationId, $reason);
                }
            }
        } catch (Exception $e) {
            error_log("Error notificando rechazo (ApprovalController): " . $e->getMessage());
        }
    }

    /**
     * Cancel a reservation.
     *
     * @param int    $reservationId ID of the reservation to cancel.
     * @param int    $userId        ID of the user performing the action.
     * @param bool   $isAdmin       Whether the user is an admin.
     * @param string $reason        Reason for cancellation.
     * @throws Exception If the reservation cannot be cancelled.
     */
    public function cancel(int $reservationId, int $userId, bool $isAdmin, string $reason): void
    {
        $stmt = $this->pdo->prepare("SELECT status, us_id FROM reserva WHERE re_id = :id FOR UPDATE");
        $stmt->execute([':id' => $reservationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            throw new Exception('La reserva no existe.');
        }
        
        if (!$isAdmin && $row['us_id'] != $userId) {
            throw new Exception('No tienes permiso para cancelar esta reserva.');
        }
        
        if (in_array($row['status'], ['cancelled', 'rejected'])) {
            throw new Exception('La reserva ya ha sido cancelada o rechazada.');
        }
        
        $update = $this->pdo->prepare(
            "UPDATE reserva SET status = 'cancelled', estatus = 'Cancelada', motivo_cancelacion = :motivo WHERE re_id = :id"
        );
        $update->execute([':motivo' => $reason, ':id' => $reservationId]);
        
        $this->logAction($userId, "Canceló reserva #$reservationId. Motivo: $reason", 'reserva');

        // Notify user via email
        try {
            $stmtUser = $this->pdo->prepare("SELECT correo FROM usuario WHERE us_id = :uid");
            $stmtUser->execute([':uid' => $row['us_id']]);
            $correo = $stmtUser->fetchColumn();
            
            if ($correo) {
                $this->emailService->sendReservationCancelled($correo, $reservationId, $reason);
            }
        } catch (Exception $e) {
            error_log("Error notificando cancelación (ApprovalController): " . $e->getMessage());
        }
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
