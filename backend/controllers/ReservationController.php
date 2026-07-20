<?php
/**
 * @file ReservationController.php
 * @summary Controlador para la gestión de reservaciones en PHP.
 * @description Maneja la creación, disponibilidad y validación de reservaciones para internos y externos.
 */


// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
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


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
class ReservationController {
    private $db;
    private $audit;
    private $emailService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
        $this->emailService = new EmailService();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (create)
// ============================================================================
    /**
     * Crea una reservación (Inicia en estado 'Pendiente').
     */

    public function create($data, $skip_email = false) {
        $this->db->beginTransaction();
        try {
            $vis_id = $data['vis_id'] ?? null;
            $us_id = $data['us_id'] ?? null;

            // -------------------------------------------------------------------------
            // LÓGICA ESPECIAL: SALA MAGNA MODULAR (1, 2, 3 o 4 salas según aforo)
            // -------------------------------------------------------------------------
            if (($data['esp_id'] ?? '') === 'SALA_MAGNA_MODULAR' || !empty($data['is_sala_magna_modular'])) {
                $num_asistentes = max(1, intval($data['num_alumnos'] ?? 1));
                $salas_requeridas = max(1, min(4, (int) ceil($num_asistentes / 24)));

                $stmtSalas = $this->db->prepare("
                    SELECT esp_id, nombre_numero 
                    FROM ESPACIO 
                    WHERE edificio = 'PIDET' AND nombre_numero LIKE 'Sala Magna%' AND estatus = 'Disponible'
                      AND esp_id NOT IN (
                          SELECT esp_id FROM RESERVA 
                          WHERE status = 'approved' AND fecha_uso = ? 
                            AND ((hora_ent < ? AND hora_sal > ?) OR (hora_ent < ? AND hora_sal > ?) OR (? <= hora_ent AND ? >= hora_sal))
                      )
                    ORDER BY nombre_numero ASC
                ");
                $stmtSalas->execute([
                    $data['fecha_uso'],
                    $data['hora_sal'], $data['hora_ent'],
                    $data['hora_sal'], $data['hora_ent'],
                    $data['hora_ent'], $data['hora_sal']
                ]);
                $salas_disponibles = $stmtSalas->fetchAll(\PDO::FETCH_ASSOC);

                if (count($salas_disponibles) < $salas_requeridas) {
                    throw new \Exception("Disponibilidad insuficiente en Sala Magna para {$num_asistentes} asistentes: se requieren {$salas_requeridas} sala(s) pero solo hay " . count($salas_disponibles) . " disponible(s) en ese horario.");
                }

                $group_id = $data['group_id'] ?? ('MAGNA_' . uniqid());
                $primer_res_id = null;

                for ($i = 0; $i < $salas_requeridas; $i++) {
                    $sala = $salas_disponibles[$i];
                    $stmtIns = $this->db->prepare("
                        INSERT INTO RESERVA (esp_id, us_id, vis_id, num_alumnos, fecha_uso, hora_ent, hora_sal, estatus, status, motivo, group_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Aprobada', 'approved', ?, ?)
                    ");
                    $stmtIns->execute([
                        $sala['esp_id'],
                        $us_id,
                        $vis_id,
                        $num_asistentes,
                        $data['fecha_uso'],
                        $data['hora_ent'],
                        $data['hora_sal'],
                        ($data['motivo'] ?? 'Reserva modular Sala Magna') . " [{$sala['nombre_numero']}]",
                        $group_id
                    ]);
                    if ($i === 0) {
                        $primer_res_id = $this->db->lastInsertId();
                    }
                }

                $this->db->commit();
                $this->audit->log($us_id, "Reserva Modular Sala Magna ({$salas_requeridas} salas) Grupo ID: " . $group_id, "RESERVAS", $vis_id);

                if (!$skip_email && $us_id) {
                    try {
                        $stmtCorreo = $this->db->prepare("SELECT correo FROM USUARIO WHERE us_id = ?");
                        $stmtCorreo->execute([$us_id]);
                        $correo = $stmtCorreo->fetchColumn();
                        if ($correo) {
                            $espacio_nombre = "PIDET - Sala Magna Modular ({$salas_requeridas} sala(s) asignadas)";
                            $this->emailService->sendReservationCreated($correo, $primer_res_id, 'Aprobada', $espacio_nombre, $data['fecha_uso'], $data['hora_ent'], $data['hora_sal']);
                        }
                    } catch (\Exception $e) {
                        error_log("Error enviando correo de confirmación Sala Magna Modular: " . $e->getMessage());
                    }
                }

                return ["success" => true, "id" => $primer_res_id, "group_id" => $group_id, "salas_reservadas" => $salas_requeridas];
            }

            $conflictQuery = "SELECT re_id FROM RESERVA WHERE esp_id = ? AND status = 'approved' AND fecha_uso = ?
                              AND ((hora_ent < ? AND hora_sal > ?) OR (hora_ent < ? AND hora_sal > ?) OR (? <= hora_ent AND ? >= hora_sal))";
            $stmt = $this->db->prepare($conflictQuery);
            $stmt->execute([$data['esp_id'], $data['fecha_uso'], $data['hora_sal'], $data['hora_ent'], $data['hora_sal'], $data['hora_ent'], $data['hora_ent'], $data['hora_sal']]);
            if ($stmt->fetch()) {
                throw new \Exception("Conflicto de horario: El espacio ya se encuentra reservado en ese horario.");
            }

            // Obtener la carrera del usuario solicitante
            $usuario_carrera = '';
            if ($us_id) {
                $stmtUs = $this->db->prepare("SELECT carrera FROM USUARIO WHERE us_id = ?");
                $stmtUs->execute([$us_id]);
                $usuario_carrera = $stmtUs->fetchColumn();
            }

            // Revisar el tipo de acceso del espacio para determinar estatus inicial
            $stmtEspacio = $this->db->prepare("SELECT acceso, division_restringida, nombre_numero, edificio, capacidad FROM ESPACIO WHERE esp_id = ?");
            $stmtEspacio->execute([$data['esp_id']]);
            $espacio = $stmtEspacio->fetch();

            if (!$espacio) {
                throw new \Exception("El espacio no existe.");
            }

            // -------------------------------------------------------------------------
            // VALIDACIÓN DE CAPACIDAD MÁXIMA
            // -------------------------------------------------------------------------
            $numAlumnos = intval($data['num_alumnos'] ?? 0);
            $capacidadMaxima = intval($espacio['capacidad'] ?? 0);
            if ($capacidadMaxima > 0 && $numAlumnos > $capacidadMaxima) {
                throw new \Exception("El número de asistentes ({$numAlumnos}) supera la capacidad máxima del espacio \"{$espacio['nombre_numero']}\" ({$capacidadMaxima} personas). Por favor, reduce el número de asistentes o elige un espacio más grande.");
            }

            $acceso = strtolower(trim($espacio['acceso'] ?? 'general'));
            $estatus_inicial = 'Aprobada'; // Auto-aprobación para General y Por división
            $status_inicial = 'approved';

            if ($acceso === 'administrador') {
                $is_admin = false;
                if ($us_id) {
                    $stmtAdmin = $this->db->prepare("
                        SELECT COUNT(*) FROM USUARIO u 
                        JOIN ROLES r ON u.rol_id = r.rol_id 
                        WHERE u.us_id = ? AND UPPER(r.nombre) LIKE '%ADMIN%'
                    ");
                    $stmtAdmin->execute([$us_id]);
                    $is_admin = ($stmtAdmin->fetchColumn() > 0);
                }
                if (!$is_admin) {
                    throw new \Exception("Acceso restringido: Este espacio es de uso exclusivo para Administradores.");
                }
                if (empty($data['group_id']) && empty($data['is_cuatrimestre'])) {
                    throw new \Exception("Los espacios exclusivos para Administradores solo pueden reservarse por periodo cuatrimestral (recurrente), nunca por días individuales.");
                }
            } elseif ($acceso === 'por división') {
                $division = trim($espacio['division_restringida'] ?? '');
                if (strcasecmp(trim($usuario_carrera ?? ''), $division) !== 0) {
                    throw new \Exception("No tienes permiso para reservar este espacio. Sólo está permitido para la división: " . ($division ?: 'Ninguna'));
                }
            } elseif ($acceso === 'restringido') {
                $estatus_inicial = 'Pendiente'; // Requiere revisión del admin
                $status_inicial = 'pending';
            }

            // REGLA DE NEGOCIO: Si dura más de 2 horas, forzar a Pendiente (solicitado por usuario)
            $hora_ent_ts = strtotime($data['hora_ent']);
            $hora_sal_ts = strtotime($data['hora_sal']);
            if ($hora_ent_ts !== false && $hora_sal_ts !== false) {
                $duracion_horas = ($hora_sal_ts - $hora_ent_ts) / 3600;
                if ($duracion_horas > 2) {
                    $estatus_inicial = 'Pendiente';
                    $status_inicial = 'pending';
                }
            }

            $group_id = $data['group_id'] ?? null;
            $stmt = $this->db->prepare("INSERT INTO RESERVA (esp_id, us_id, vis_id, num_alumnos, fecha_uso, hora_ent, hora_sal, estatus, status, motivo, group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['esp_id'], $us_id, $vis_id, $data['num_alumnos'] ?? 0, $data['fecha_uso'], $data['hora_ent'], $data['hora_sal'], $estatus_inicial, $status_inicial, $data['motivo'] ?? null, $group_id]);
            
            $new_res_id = $this->db->lastInsertId();
            $this->db->commit();
            
            // Auditoría
            $this->audit->log($us_id, "Creada nueva reservación ID: " . $new_res_id, "RESERVAS", $vis_id);
            
            // Notificar a administradores si requiere aprobación
            if ($estatus_inicial === 'Pendiente') {
                try {
                    $notifCtrl = new NotificationController();
                    $stmtAdmins = $this->db->query("SELECT us_id, nombre, correo FROM USUARIO WHERE estatus = 'Activo' AND rol_id IN (SELECT rol_id FROM ROLES WHERE UPPER(nombre) LIKE '%ADMIN%')");
                    $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

                    // Obtener nombre del usuario solicitante
                    $stmtUser = $this->db->prepare("SELECT nombre FROM USUARIO WHERE us_id = ?");
                    $stmtUser->execute([$us_id]);
                    $usuario_solicitante = $stmtUser->fetchColumn() ?: 'Usuario Institucional';
                    $espacio_nombre_det = trim(($espacio['edificio'] ?? '') . ' - ' . ($espacio['nombre_numero'] ?? 'Espacio'), ' -');

                    foreach ($admins as $admin) {
                        $notifCtrl->createNotification($admin['us_id'], 'Reserva', "Nueva reserva pendiente de aprobación (ID: $new_res_id).", 'aprobacion_reservas.php');
                        if (!$skip_email && !empty($admin['correo'])) {
                            $this->emailService->sendPendingApprovalAlertToAdmin(
                                $admin['correo'],
                                $admin['nombre'] ?? 'Administrador',
                                $new_res_id,
                                $usuario_solicitante,
                                $espacio_nombre_det,
                                $data['fecha_uso'] ?? '',
                                $data['hora_ent'] ?? '',
                                $data['hora_sal'] ?? '',
                                $data['motivo'] ?? ''
                            );
                        }
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


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (approve)
// ============================================================================
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


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (getAvailability)
// ============================================================================
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

    public function getAllReservationsByDate($date) {
        try {
            $query = "SELECT esp_id, re_id, hora_ent, hora_sal, estatus 
                      FROM RESERVA 
                      WHERE fecha_uso = ? AND estatus != 'Rechazada'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$date]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("Error check all availability: " . $e->getMessage());
            return [];
        }
    }


// ============================================================================
// SECCIÓN 6: LÓGICA DE NEGOCIO Y OPERACIÓN (sendBulkEmail)
// ============================================================================
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
            if ($esp_id === 'SALA_MAGNA_MODULAR') {
                $espacio = ['acceso_tipo' => 'General', 'nombre_numero' => 'Sala Magna Modular', 'edificio' => 'PIDET'];
            } else {
                $stmtEspacio = $this->db->prepare("SELECT acceso_tipo, nombre_numero, edificio FROM ESPACIO WHERE esp_id = ?");
                $stmtEspacio->execute([$esp_id]);
                $espacio = $stmtEspacio->fetch();
                if ($espacio && $espacio['acceso_tipo'] === 'Restringido') {
                    $estatus_inicial = 'Pendiente';
                }
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

                if ($estatus_inicial === 'Pendiente') {
                    try {
                        $stmtAdmins = $this->db->query("SELECT us_id, nombre, correo FROM USUARIO WHERE estatus = 'Activo' AND rol_id IN (SELECT rol_id FROM ROLES WHERE UPPER(nombre) LIKE '%ADMIN%')");
                        $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
                        $stmtUser = $this->db->prepare("SELECT nombre FROM USUARIO WHERE us_id = ?");
                        $stmtUser->execute([$us_id]);
                        $usuario_solicitante = $stmtUser->fetchColumn() ?: 'Usuario Institucional';
                        $fechas_str = implode(', ', $fechas);
                        $ids_str = implode(', ', $re_ids);
                        
                        foreach ($admins as $admin) {
                            if (!empty($admin['correo'])) {
                                $this->emailService->sendPendingApprovalAlertToAdmin(
                                    $admin['correo'],
                                    $admin['nombre'] ?? 'Administrador',
                                    $ids_str,
                                    $usuario_solicitante,
                                    $espacio_nombre,
                                    $fechas_str,
                                    $hora_ent,
                                    $hora_sal,
                                    'Solicitud múltiple de reserva para ' . count($fechas) . ' fecha(s).'
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Error notificando solicitud masiva pendiente a admins: " . $e->getMessage());
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
