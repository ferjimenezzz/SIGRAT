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
        // 1. Auto-cancelar reservaciones pendientes expiradas (compatibilidad MySQL y SQL estándar)
        try {
            $this->pdo->exec("UPDATE reserva SET status = 'cancelled', estatus = 'Cancelada', cancel_reason = 'Expirada automáticamente por falta de aprobación a tiempo' WHERE (LOWER(status) = 'pending' OR LOWER(estatus) = 'pendiente') AND (fecha_uso < CURDATE() OR (fecha_uso = CURDATE() AND hora_sal <= CURRENT_TIME()))");
        } catch (\Exception $e) {
            error_log("Error auto-cancelación en ReservationApprovalController: " . $e->getMessage());
        }

        // 2. Fetch based on status
        if ($status === 'cancelled') {
            $statusCondition = "(
                LOWER(r.status) IN ('cancelled', 'rejected', 'expired', 'no_show') 
                OR LOWER(r.estatus) IN ('cancelada', 'cancelado', 'rechazada', 'rechazado', 'inasistencia', 'no asistió', 'sin asistencia', 'expirada', 'vencida')
                OR (r.cancel_reason IS NOT NULL AND TRIM(r.cancel_reason) != '' AND LOWER(r.status) NOT IN ('approved', 'pending') AND LOWER(r.estatus) NOT IN ('aprobada', 'pendiente'))
            )";
            $params = [];
        } elseif ($status === 'pending') {
            $statusCondition = "(LOWER(r.status) = 'pending' OR LOWER(r.estatus) = 'pendiente')";
            $params = [];
        } elseif ($status === 'approved') {
            $statusCondition = "(LOWER(r.status) = 'approved' OR LOWER(r.estatus) = 'aprobada')";
            $params = [];
        } else {
            $statusCondition = "(LOWER(r.status) = LOWER(:status) OR LOWER(r.estatus) = LOWER(:estatus_alt))";
            $params = [':status' => $status, ':estatus_alt' => $status];
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

        // Normalizar status en memoria para que el frontend React siempre reconozca el estado estándar
        foreach ($rows as &$row) {
            $st = strtolower($row['status'] ?? '');
            $est = strtolower($row['estatus'] ?? '');

            if (in_array($st, ['rejected']) || in_array($est, ['rechazada', 'rechazado'])) {
                $row['status'] = 'rejected';
            } elseif (in_array($st, ['cancelled', 'expired', 'no_show']) || in_array($est, ['cancelada', 'cancelado', 'inasistencia', 'no asistió', 'sin asistencia', 'expirada', 'vencida'])) {
                $row['status'] = 'cancelled';
            } elseif ($st === 'approved' || $est === 'aprobada') {
                $row['status'] = 'approved';
            } elseif ($st === 'pending' || $est === 'pendiente') {
                $row['status'] = 'pending';
            }
        }
        unset($row);

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

            $isGroup = (strpos($reservationId, 'grp_') === 0 || !ctype_digit($reservationId));
            $idCol = $isGroup ? 'group_id' : 're_id';

            // Verify reservation exists and is pending
            $stmt = $this->pdo->prepare("SELECT re_id, status, estatus, esp_id, fecha_uso, hora_ent, hora_sal FROM reserva WHERE $idCol = :id FOR UPDATE");
            $stmt->execute([':id' => $reservationId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                throw new Exception('La reserva no existe.');
            }

            $targetEspId = $newEspId ? $newEspId : $rows[0]['esp_id'];

            // Filtrar únicamente las fechas que permanecen pendientes en la solicitud
            $pendingRows = array_filter($rows, function($r) {
                $st = strtolower(trim($r['status'] ?? ''));
                $est = strtolower(trim($r['estatus'] ?? ''));
                return ($st === 'pending' || $est === 'pendiente');
            });

            if (empty($pendingRows)) {
                throw new Exception('No hay fechas pendientes por aprobar en esta solicitud (pueden haber expirado o sido procesadas).');
            }

            $targetEspId = $newEspId ? $newEspId : $rows[0]['esp_id'];

            foreach ($pendingRows as $row) {
                $overlapStmt = $this->pdo->prepare("SELECT COUNT(*) FROM reserva WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso AND (status = 'approved' OR estatus = 'Aprobada') AND hora_ent < :hora_sal AND hora_sal > :hora_ent AND re_id != :re_id AND (group_id IS NULL OR group_id != :id)");
                $overlapStmt->execute([':esp_id' => $targetEspId, ':fecha_uso' => $row['fecha_uso'], ':hora_sal' => $row['hora_sal'], ':hora_ent' => $row['hora_ent'], ':re_id' => $row['re_id'], ':id' => $reservationId]);
                if ($overlapStmt->fetchColumn() > 0) {
                    throw new Exception('El espacio seleccionado ya cuenta con una reservación aprobada para la fecha ' . $row['fecha_uso']);
                }
            }

            // Actualizar a Aprobada únicamente las reservaciones pendientes del grupo
            $update = $this->pdo->prepare("UPDATE reserva SET status = 'approved', estatus = 'Aprobada', esp_id = :esp_id, approved_by = :admin, approved_at = NOW() WHERE $idCol = :id AND (LOWER(status) = 'pending' OR LOWER(estatus) = 'pendiente')");
            $update->execute([':esp_id' => $targetEspId, ':admin' => $adminId, ':id' => $reservationId]);
            $this->logAction($adminId, 'Aprobó reserva(s) ' . $reservationId . ($newEspId ? " (Reasignada a espacio $newEspId)" : ""), 'reserva');

            require_once __DIR__ . '/NotificationController.php';
            $notifCtrl = new \Controllers\NotificationController();

            // Rechazar automáticamente reservaciones pendientes de otros usuarios que se empalmen en cada fecha
            foreach ($pendingRows as $row) {
                $rejectStmt = $this->pdo->prepare("UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', cancel_reason = 'Rechazo automático por empalme', approved_by = :admin, approved_at = NOW() WHERE esp_id = :esp_id AND fecha_uso = :fecha_uso AND (status = 'pending' OR estatus = 'Pendiente') AND hora_ent < :hora_sal AND hora_sal > :hora_ent AND re_id != :id");
                $rejectStmt->execute([':admin' => $adminId, ':esp_id' => $targetEspId, ':fecha_uso' => $row['fecha_uso'], ':hora_sal' => $row['hora_sal'], ':hora_ent' => $row['hora_ent'], ':id' => $row['re_id']]);
            }

            // Obtener nombre del espacio y datos del usuario solicitante para enviar UN ÚNICO correo y notificación
            try {
                $stmtEsp = $this->pdo->prepare("SELECT nombre_numero, edificio FROM espacio WHERE esp_id = :esp_id");
                $stmtEsp->execute([':esp_id' => $targetEspId]);
                $espInfo = $stmtEsp->fetch(PDO::FETCH_ASSOC);
                $espacioNombre = $espInfo ? trim(($espInfo['edificio'] ?? '') . ' - ' . ($espInfo['nombre_numero'] ?? 'Espacio'), ' -') : 'Espacio';

                $firstRow = reset($pendingRows);
                $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
                $stmtUser->execute([':id' => $firstRow['re_id']]);
                $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($usuario) {
                    $countDays = count($pendingRows);
                    $msgNotif = ($countDays > 1)
                        ? "Tu reserva por $countDays días para \"$espacioNombre\" ha sido aprobada."
                        : "Tu reserva para \"$espacioNombre\" ({$firstRow['fecha_uso']}) ha sido aprobada.";
                    $notifCtrl->createNotification($usuario['us_id'], 'Reserva', $msgNotif, 'espacios.php');

                    if (!empty($usuario['correo'])) {
                        if ($countDays > 1) {
                            $fechasGroup = array_values(array_unique(array_column($pendingRows, 'fecha_uso')));
                            $this->emailService->sendBulkReservationApproved(
                                $usuario['correo'],
                                $espacioNombre,
                                $fechasGroup,
                                $firstRow['hora_ent'] ?? '',
                                $firstRow['hora_sal'] ?? ''
                            );
                        } else {
                            $this->emailService->sendReservationApproved(
                                $usuario['correo'],
                                $firstRow['re_id'],
                                $espacioNombre,
                                $firstRow['fecha_uso'] ?? '',
                                $firstRow['hora_ent'] ?? '',
                                $firstRow['hora_sal'] ?? ''
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error enviando correo o notificación de aprobación: " . $e->getMessage());
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
        $isGroup = (strpos($reservationId, 'grp_') === 0 || !ctype_digit($reservationId));
        $idCol = $isGroup ? 'group_id' : 're_id';

        $stmt = $this->pdo->prepare("SELECT re_id, status, estatus FROM reserva WHERE $idCol = :id FOR UPDATE");
        $stmt->execute([':id' => $reservationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) throw new Exception('Reservation not found');
        $pendingRows = array_filter($rows, function($r) {
            $st = strtolower(trim($r['status'] ?? ''));
            $est = strtolower(trim($r['estatus'] ?? ''));
            return ($st === 'pending' || $est === 'pendiente');
        });

        if (empty($pendingRows)) {
            throw new Exception('No hay fechas pendientes por rechazar en esta solicitud.');
        }

        $update = $this->pdo->prepare("UPDATE reserva SET status = 'rejected', estatus = 'Rechazada', cancel_reason = :reason, approved_by = :admin, approved_at = NOW() WHERE $idCol = :id AND (LOWER(status) = 'pending' OR LOWER(estatus) = 'pendiente')");
        $update->execute([':reason' => $reason, ':admin' => $adminId, ':id' => $reservationId]);
        $this->logAction($adminId, 'Rechazó reserva(s) ' . $reservationId . ($reason ? ': ' . $reason : ''), 'reserva');

        try {
            require_once __DIR__ . '/NotificationController.php';
            $notifCtrl = new \Controllers\NotificationController();

            $firstRow = reset($pendingRows);
            $stmtEsp = $this->pdo->prepare("SELECT e.nombre_numero, e.edificio FROM reserva r JOIN espacio e ON r.esp_id = e.esp_id WHERE r.re_id = :id");
            $stmtEsp->execute([':id' => $firstRow['re_id']]);
            $espInfo = $stmtEsp->fetch(PDO::FETCH_ASSOC);
            $espacioNombre = $espInfo ? trim(($espInfo['edificio'] ?? '') . ' - ' . ($espInfo['nombre_numero'] ?? 'Espacio'), ' -') : 'Espacio';

            $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
            $stmtUser->execute([':id' => $firstRow['re_id']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $countDays = count($pendingRows);
                $msgNotif = ($countDays > 1)
                    ? "Tu reserva por $countDays días para \"$espacioNombre\" ha sido rechazada." . ($reason ? " Motivo: $reason" : "")
                    : "Tu reserva para \"$espacioNombre\" ({$firstRow['fecha_uso']}) ha sido rechazada." . ($reason ? " Motivo: $reason" : "");
                $notifCtrl->createNotification($usuario['us_id'], 'Reserva', $msgNotif, 'espacios.php');

                if (!empty($usuario['correo'])) {
                    if ($countDays > 1) {
                        $fechasGroup = array_values(array_unique(array_column($pendingRows, 'fecha_uso')));
                        $this->emailService->sendBulkReservationRejected(
                            $usuario['correo'],
                            $espacioNombre,
                            $reason ?? '',
                            $fechasGroup
                        );
                    } else {
                        $this->emailService->sendReservationRejected(
                            $usuario['correo'],
                            $firstRow['re_id'],
                            $reason ?? '',
                            $espacioNombre,
                            $firstRow['fecha_uso'] ?? ''
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error notificando rechazo: " . $e->getMessage());
        }
    }

    public function cancel(string $reservationId, int $userId, bool $isAdmin, string $reason): void
    {
        $isGroup = (strpos($reservationId, 'grp_') === 0 || !ctype_digit($reservationId));
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

            $firstRow = reset($rows);
            $stmtEsp = $this->pdo->prepare("SELECT e.nombre_numero, e.edificio FROM reserva r JOIN espacio e ON r.esp_id = e.esp_id WHERE r.re_id = :id");
            $stmtEsp->execute([':id' => $firstRow['re_id']]);
            $espInfo = $stmtEsp->fetch(PDO::FETCH_ASSOC);
            $espacioNombre = $espInfo ? trim(($espInfo['edificio'] ?? '') . ' - ' . ($espInfo['nombre_numero'] ?? 'Espacio'), ' -') : 'Espacio';

            $stmtUser = $this->pdo->prepare("SELECT r.us_id, u.correo FROM reserva r JOIN usuario u ON r.us_id = u.us_id WHERE r.re_id = :id");
            $stmtUser->execute([':id' => $firstRow['re_id']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $countDays = count($rows);
                $msgNotif = ($countDays > 1)
                    ? "Tu reserva por $countDays días para \"$espacioNombre\" ha sido cancelada." . ($reason ? " Motivo: $reason" : "")
                    : "Tu reserva para \"$espacioNombre\" ({$firstRow['fecha_uso']}) ha sido cancelada." . ($reason ? " Motivo: $reason" : "");
                $notifCtrl->createNotification($usuario['us_id'], 'Reserva', $msgNotif, 'espacios.php');

                if (!empty($usuario['correo'])) {
                    if ($countDays > 1) {
                        $fechasGroup = array_values(array_unique(array_column($rows, 'fecha_uso')));
                        $this->emailService->sendBulkReservationCancelled(
                            $usuario['correo'],
                            $espacioNombre,
                            $reason ?? '',
                            $fechasGroup
                        );
                    } else {
                        $this->emailService->sendReservationCancelled(
                            $usuario['correo'],
                            $firstRow['re_id'],
                            $reason ?? '',
                            $espacioNombre,
                            $firstRow['fecha_uso'] ?? ''
                        );
                    }
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
