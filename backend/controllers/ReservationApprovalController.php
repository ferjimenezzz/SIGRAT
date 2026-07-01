<?php
/**
 * @file ReservationApprovalController.php
 * @summary Controlador para la gestión y flujo de aprobación de reservas.
 * @description Maneja la aprobación, rechazo y auditoría de solicitudes de reserva de espacios, enviando notificaciones por correo electrónico y garantizando principios SOLID.
 * @package Backend\Controllers
 */

namespace Backend;

// ============================================================================
// SECCIÓN 1: IMPORTACIÓN DE DEPENDENCIAS Y SERVICIOS DE COMUNICACIÓN
// ============================================================================
use PDO;
use Exception;

// Importar servicio para notificaciones automáticas por correo (EmailService)
require_once __DIR__ . '/../services/EmailService.php';

// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE Y CONSTRUCTOR (INYECCIÓN DE DEPENDENCIAS)
// ============================================================================
class ReservationApprovalController
{
    /** @var PDO $pdo Instancia de conexión PDO a MySQL en modo estricto */
    private PDO $pdo;
    /** @var \Services\EmailService $emailService Servicio para despacho de correos SMTP */
    private $emailService;

    /**
     * Constructor de la clase de aprobación de reservas.
     * @param PDO $pdo Instancia PDO inyectada por el enrutador para operaciones ACID.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->emailService = new \Services\EmailService();
    }

    // ============================================================================
    // SECCIÓN 3: CONSULTAS DE SOLICITUDES Y LIMPIEZA DE EXPIRACIONES
    // ============================================================================
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
        // 1. Auto-cancel expired pending reservations
        $this->pdo->exec("UPDATE reserva SET status = 'cancelled', estatus = 'Cancelada', cancel_reason = 'Expirada automáticamente por falta de aprobación a tiempo' WHERE status = 'pending' AND (fecha_uso + hora_ent) < NOW()");

        // 2. Fetch based on status
        if ($status === 'cancelled') {
            $statusCondition = "r.status IN ('cancelled', 'rejected')";
            $params = [];
        } else {
            $statusCondition = "r.status = :status";
            $params = [':status' => $status];
        }

        $where = $isAdmin ? $statusCondition : "r.us_id = :uid AND " . $statusCondition;
        if (!$isAdmin) {
            $params[':uid'] = $userId;
        }

        // Fetch all matching
        $stmt = $this->pdo->prepare("
            SELECT r.re_id, r.fecha_uso, r.hora_ent, r.hora_sal, r.status, r.estatus, r.group_id, r.cancel_reason,
                   u.nombre AS usuario_nombre, e.nombre_numero AS espacio_nombre
            FROM reserva r
            LEFT JOIN usuario u ON r.us_id = u.us_id
            LEFT JOIN espacio e ON r.esp_id = e.esp_id
            WHERE $where
            ORDER BY r.fecha_uso DESC, r.hora_ent DESC
            LIMIT 500
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Grouping logic in PHP
        $grouped = [];
        $result = [];
        foreach ($rows as $row) {
            if (!empty($row['group_id'])) {
                $gid = $row['group_id'];
                if (!isset($grouped[$gid])) {
                    $grouped[$gid] = $row;
                    $grouped[$gid]['re_id'] = $gid; // Use group_id as the ID for frontend to act upon
                    $grouped[$gid]['fechas_agrupadas'] = [$row['fecha_uso']];
                    $grouped[$gid]['fecha_uso'] = 'Múltiples fechas';
                } else {
                    $grouped[$gid]['fechas_agrupadas'][] = $row['fecha_uso'];
                }
            } else {
                $result[] = $row;
            }
        }

        foreach ($grouped as $gid => $group) {
            $count = count($group['fechas_agrupadas']);
            $group['fecha_uso'] = "Múltiples fechas ($count días)";
            $result[] = $group;
        }

        return $result;
    }

    public function approve(string $reservationId, int $adminId, ?int $newEspId = null): void
    {
        try {
            $this->pdo->beginTransaction();

            $isGroup = strpos($reservationId, 'grp_') === 0;
            $idCol = $isGroup ? 'group_id' : 're_id';

            // Verify reservation exists and is pending
            $stmt = $this->pdo->prepare("SELECT re_id, status, esp_id, fecha_uso, hora_ent, hora_sal FROM reserva WHERE $idCol = :id FOR UPDATE");
            $stmt->execute([':id' => $reservationId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                throw new Exception('La reserva no existe.');
            }

            $targetEspId = $newEspId ? $newEspId : $rows[0]['esp_id'];

            foreach ($rows as $row) {
                if ($row['status'] !== 'pending') {
                    throw new Exception('Solo las reservas pendientes pueden ser aprobadas.');
                }
                $overlapStmt = $this->pdo->prepare("SELECT COUNT(*) FROM reserva WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso AND status = 'approved' AND hora_ent < :hora_sal AND hora_sal > :hora_ent");
                $overlapStmt->execute([':esp_id' => $targetEspId, ':fecha_uso' => $row['fecha_uso'], ':hora_sal' => $row['hora_sal'], ':hora_ent' => $row['hora_ent']]);
                if ($overlapStmt->fetchColumn() > 0) {
                    throw new Exception('El espacio seleccionado ya cuenta con una reservación aprobada para la fecha ' . $row['fecha_uso']);
                }
            }

            // Update status to approved, and update space if changed
            $update = $this->pdo->prepare("UPDATE reserva SET status = 'approved', estatus = 'Aprobada', esp_id = :esp_id, approved_by = :admin, approved_at = NOW() WHERE $idCol = :id");
            $update->execute([':esp_id' => $targetEspId, ':admin' => $adminId, ':id' => $reservationId]);
            $this->logAction($adminId, 'Aprobó reserva(s) ' . $reservationId . ($newEspId ? " (Reasignada a espacio $newEspId)" : ""), 'reserva');

            require_once __DIR__ . '/NotificationController.php';
            $notifCtrl = new \Controllers\NotificationController();

            foreach ($rows as $row) {
                // Automatically reject overlapping pending reservations
                $rejectStmt = $this->pdo->prepare("UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', cancel_reason = 'Rechazo automático por empalme', approved_by = :admin, approved_at = NOW() WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso AND status = 'pending' AND hora_ent < :hora_sal AND hora_sal > :hora_ent AND re_id != :id");
                $rejectStmt->execute([':admin' => $adminId, ':esp_id' => $targetEspId, ':fecha_uso' => $row['fecha_uso'], ':hora_sal' => $row['hora_sal'], ':hora_ent' => $row['hora_ent'], ':id' => $row['re_id']]);

                // Notify user
                try {
                    $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
                    $stmtUser->execute([':id' => $row['re_id']]);
                    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    if ($usuario) {
                        $notifCtrl->createNotification($usuario['us_id'], 'Reserva', 'Tu reserva #' . $row['re_id'] . ' ha sido aprobada.', 'espacios.php');
                        if ($usuario['correo']) {
                            $this->emailService->sendReservationApproved($usuario['correo'], $row['re_id']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error notificando aprobación: " . $e->getMessage());
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reject(string $reservationId, int $adminId, ?string $reason = null): void
    {
        $isGroup = strpos($reservationId, 'grp_') === 0;
        $idCol = $isGroup ? 'group_id' : 're_id';

        $stmt = $this->pdo->prepare("SELECT re_id, status FROM reserva WHERE $idCol = :id FOR UPDATE");
        $stmt->execute([':id' => $reservationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) throw new Exception('Reservation not found');
        foreach ($rows as $row) {
            if ($row['status'] !== 'pending') throw new Exception('Only pending reservations can be rejected');
        }

        $update = $this->pdo->prepare("UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', cancel_reason = :reason, approved_by = :admin, approved_at = NOW() WHERE $idCol = :id");
        $update->execute([':reason' => $reason, ':admin' => $adminId, ':id' => $reservationId]);
        $this->logAction($adminId, 'Rechazó reserva(s) ' . $reservationId . ($reason ? ': ' . $reason : ''), 'reserva');

        try {
            require_once __DIR__ . '/NotificationController.php';
            $notifCtrl = new \Controllers\NotificationController();
            foreach ($rows as $row) {
                $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
                $stmtUser->execute([':id' => $row['re_id']]);
                $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
                if ($usuario) {
                    $msg = 'Tu reserva #' . $row['re_id'] . ' ha sido rechazada.';
                    if ($reason) $msg .= ' Motivo: ' . $reason;
                    $notifCtrl->createNotification($usuario['us_id'], 'Reserva', $msg, 'espacios.php');
                    if ($usuario['correo']) $this->emailService->sendReservationRejected($usuario['correo'], $row['re_id'], $reason);
                }
            }
        } catch (Exception $e) {
            error_log("Error notificando rechazo: " . $e->getMessage());
        }
    }

    public function cancel(string $reservationId, int $userId, bool $isAdmin, string $reason): void
    {
        $isGroup = strpos($reservationId, 'grp_') === 0;
        $idCol = $isGroup ? 'group_id' : 're_id';

        $where = $isAdmin ? "$idCol = :id" : "$idCol = :id AND us_id = :uid";
        $stmt = $this->pdo->prepare("SELECT re_id, status FROM reserva WHERE $where FOR UPDATE");
        
        $params = [':id' => $reservationId];
        if (!$isAdmin) $params[':uid'] = $userId;
        $stmt->execute($params);
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) throw new Exception('Reservation not found or unauthorized');

        $update = $this->pdo->prepare("UPDATE reserva SET status = 'cancelled', estatus = 'Cancelada', cancel_reason = :reason WHERE $idCol = :id");
        $update->execute([':reason' => $reason, ':id' => $reservationId]);
        $this->logAction($userId, 'Canceló reserva(s) ' . $reservationId . ' Motivo: ' . $reason, 'reserva');

        try {
            require_once __DIR__ . '/NotificationController.php';
            $notifCtrl = new \Controllers\NotificationController();
            foreach ($rows as $row) {
                $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
                $stmtUser->execute([':id' => $row['re_id']]);
                $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
                if ($usuario) {
                    $msg = 'Tu reserva #' . $row['re_id'] . ' ha sido cancelada.';
                    if ($reason) $msg .= ' Motivo: ' . $reason;
                    $notifCtrl->createNotification($usuario['us_id'], 'Reserva', $msg, 'espacios.php');
                    if ($usuario['correo']) $this->emailService->sendReservationCancelled($usuario['correo'], $row['re_id'], $reason);
                }
            }
        } catch (Exception $e) {
            error_log("Error notificando cancelación: " . $e->getMessage());
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
