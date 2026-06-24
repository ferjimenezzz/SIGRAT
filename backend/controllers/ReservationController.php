<?php
/**
 * @file ReservationController.php
 * @summary Controlador para la gestión de reservaciones en PHP.
 * @description Maneja la creación, disponibilidad y validación de reservaciones para internos y externos.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';
require_once 'NotificationController.php';
require_once __DIR__ . '/../services/EmailService.php';

use Config\Database;
use Controllers\AuditController;
use Controllers\NotificationController;
use Services\EmailService;
use PDO;

class ReservationController {
    private $db;
    private $audit;
    private $emailService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
        $this->emailService = new EmailService();
    }

    /**
     * Crea una reservación (Inicia en estado 'Pendiente').
     */
    public function create($data, $skip_email = false) {
        $this->db->beginTransaction();
        try {
            $vis_id = $data['vis_id'] ?? null;
            $us_id = $data['us_id'] ?? null;

            $conflictQuery = "SELECT re_id FROM RESERVA WHERE esp_id = ? AND estatus = 'Aprobada' AND fecha_uso = ?
                              AND ((hora_ent < ? AND hora_sal > ?) OR (hora_ent < ? AND hora_sal > ?) OR (? <= hora_ent AND ? >= hora_sal))";
            $stmt = $this->db->prepare($conflictQuery);
            $stmt->execute([$data['esp_id'], $data['fecha_uso'], $data['hora_sal'], $data['hora_ent'], $data['hora_sal'], $data['hora_ent'], $data['hora_ent'], $data['hora_sal']]);
            if ($stmt->fetch()) throw new \Exception("Conflicto de horario.");

            // Obtener la carrera del usuario solicitante
            $usuario_carrera = '';
            if ($us_id) {
                $stmtUs = $this->db->prepare("SELECT carrera FROM USUARIO WHERE us_id = ?");
                $stmtUs->execute([$us_id]);
                $usuario_carrera = $stmtUs->fetchColumn();
            }

            // Revisar el tipo de acceso del espacio para determinar estatus inicial
            $stmtEspacio = $this->db->prepare("SELECT acceso, division_restringida, nombre_numero, edificio FROM ESPACIO WHERE esp_id = ?");
            $stmtEspacio->execute([$data['esp_id']]);
            $espacio = $stmtEspacio->fetch();

            if (!$espacio) {
                throw new \Exception("El espacio no existe.");
            }

            $acceso = strtolower(trim($espacio['acceso'] ?? 'general'));
            $estatus_inicial = 'Aprobada'; // Auto-aprobación para General y Por división
            $status_inicial = 'approved';

            if ($acceso === 'por división') {
                $division = trim($espacio['division_restringida'] ?? '');
                if (strcasecmp(trim($usuario_carrera ?? ''), $division) !== 0) {
                    throw new \Exception("No tienes permiso para reservar este espacio. Sólo está permitido para la división: " . ($division ?: 'Ninguna'));
                }
            } elseif ($acceso === 'restringido') {
                $estatus_inicial = 'Pendiente'; // Requiere revisión del admin
                $status_inicial = 'pending';
            }

            $stmt = $this->db->prepare("INSERT INTO RESERVA (esp_id, us_id, vis_id, num_alumnos, fecha_uso, hora_ent, hora_sal, estatus, status, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['esp_id'], $us_id, $vis_id, $data['num_alumnos'] ?? 0, $data['fecha_uso'], $data['hora_ent'], $data['hora_sal'], $estatus_inicial, $status_inicial, $data['motivo'] ?? null]);
            
            $new_res_id = $this->db->lastInsertId();
            $this->db->commit();
            
            // Auditoría
            $this->audit->log($us_id, "Creada nueva reservación ID: " . $new_res_id, "RESERVAS", $vis_id);
            
            // Notificar a administradores si requiere aprobación
            if ($estatus_inicial === 'Pendiente') {
                try {
                    $notifCtrl = new NotificationController();
                    $stmtAdmins = $this->db->query("SELECT us_id FROM USUARIO WHERE rol_id IN (SELECT rol_id FROM ROLES WHERE UPPER(nombre) LIKE '%ADMIN%')");
                    $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($admins as $admin_id) {
                        $notifCtrl->createNotification($admin_id, 'Reserva', "Nueva reserva pendiente de aprobación (ID: $new_res_id).", 'aprobacion_reservas.php');
                    }
                } catch (\Exception $e) {
                    error_log("Error notificando reserva pendiente: " . $e->getMessage());
                }
            }
            
            // Notificar al usuario por correo de la nueva reserva
            if (!$skip_email) {
                try {
                    if ($us_id) {
                        $stmtCorreo = $this->db->prepare("SELECT correo FROM USUARIO WHERE us_id = ?");
                        $stmtCorreo->execute([$us_id]);
                        $correo = $stmtCorreo->fetchColumn();
                        if ($correo) {
                            $espacio_nombre = $espacio ? "{$espacio['edificio']} - {$espacio['nombre_numero']}" : "Espacio";
                            $this->emailService->sendReservationCreated($correo, $new_res_id, $estatus_inicial, $espacio_nombre, $data['fecha_uso'], $data['hora_ent'], $data['hora_sal']);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error enviando correo de confirmación: " . $e->getMessage());
                }
            }

            return ["success" => true, "id" => $new_res_id];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function approve($id, $admin_id, $comments = '') {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE RESERVA SET estatus = 'Aprobada' WHERE re_id = ?")->execute([$id]);
            $this->db->prepare("INSERT INTO APROBACION (re_id, admin_id, estado, comentarios) VALUES (?, ?, 'Aprobado', ?)")->execute([$id, $admin_id, $comments]);

            $this->db->commit();

            // Auditoría
            $this->audit->log($admin_id, "Aprobada reservación ID: $id", "RESERVAS");

            // Notificar al usuario (Push / Correo)
            try {
                $notifCtrl = new NotificationController();
                $stmtUs = $this->db->prepare("
                    SELECT r.us_id, u.correo, r.fecha_uso, r.hora_ent, r.hora_sal, e.edificio, e.nombre_numero 
                    FROM RESERVA r 
                    JOIN USUARIO u ON r.us_id = u.us_id 
                    JOIN ESPACIO e ON r.esp_id = e.esp_id
                    WHERE r.re_id = ?
                ");
                $stmtUs->execute([$id]);
                $usuario = $stmtUs->fetch();
                if ($usuario) {
                    $us_id = $usuario['us_id'];
                    $correo = $usuario['correo'];
                    $notifCtrl->createNotification($us_id, 'Reserva', "Tu reserva (ID: $id) ha sido aprobada.", 'espacios.php');
                    
                    if ($correo) {
                        $esp_nombre = $usuario['edificio'] . ' - ' . $usuario['nombre_numero'];
                        $this->emailService->sendReservationApproved($correo, $id, $esp_nombre, $usuario['fecha_uso'], $usuario['hora_ent'], $usuario['hora_sal']);
                    }
                }
            } catch (\Exception $e) {
                error_log("Error notificando aprobación de reserva: " . $e->getMessage());
            }

            return ["success" => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function getAvailability($esp_id, $date) {
        try {
            $query = "SELECT re_id, hora_ent, hora_sal, estatus 
                      FROM RESERVA 
                      WHERE esp_id = ? AND fecha_uso = ? AND estatus != 'Rechazada'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$esp_id, $date]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("Error check availability: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @summary Envía un solo correo confirmando múltiples reservaciones creadas a la vez.
     * 
     * @param int $us_id
     * @param array $re_ids Array de IDs de reservas creadas
     * @param array $fechas Array de fechas
     * @param int $esp_id
     */
    public function sendBulkEmail($us_id, $re_ids, $fechas, $esp_id) {
        try {
            if (!$us_id || empty($re_ids) || empty($fechas)) return false;
            
            // Determinar estatus inicial
            $estatus_inicial = 'Aprobada';
            $stmtEspacio = $this->db->prepare("SELECT acceso_tipo, nombre_numero, edificio FROM ESPACIO WHERE esp_id = ?");
            $stmtEspacio->execute([$esp_id]);
            $espacio = $stmtEspacio->fetch();
            if ($espacio && $espacio['acceso_tipo'] === 'Restringido') {
                $estatus_inicial = 'Pendiente';
            }

            $stmtCorreo = $this->db->prepare("SELECT correo FROM USUARIO WHERE us_id = ?");
            $stmtCorreo->execute([$us_id]);
            $correo = $stmtCorreo->fetchColumn();
            
            if ($correo) {
                $espacio_nombre = $espacio ? "{$espacio['edificio']} - {$espacio['nombre_numero']}" : "Espacio";
                // Get the first reservation to get times
                $hora_ent = ''; $hora_sal = '';
                if (!empty($re_ids)) {
                    $stmtTime = $this->db->prepare("SELECT hora_ent, hora_sal FROM RESERVA WHERE re_id = ?");
                    $stmtTime->execute([$re_ids[0]]);
                    $timeData = $stmtTime->fetch();
                    if ($timeData) {
                        $hora_ent = $timeData['hora_ent'];
                        $hora_sal = $timeData['hora_sal'];
                    }
                }
                
                return $this->emailService->sendBulkReservationCreated($correo, $re_ids, $fechas, $estatus_inicial, $espacio_nombre, $hora_ent, $hora_sal);
            }
        } catch (\Exception $e) {
            error_log("Error enviando correo masivo de confirmación: " . $e->getMessage());
        }
        return false;
    }
}
