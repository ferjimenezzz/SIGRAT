<?php
/**
 * @file NotificationController.php
 * @summary Controlador de Notificaciones
 * @description Maneja la creación, lectura y marcado de notificaciones. Incluye lógica de verificación de préstamos por vencer.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;

class NotificationController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * @param int $us_id ID del usuario destino
     * @param string $tipo Tipo de notificación (Prestamo, Reserva, Sistema)
     * @param string $mensaje El mensaje de la notificación
     * @param string $enlace URL relativa a la que debe redirigir
     */
    public function createNotification($us_id, $tipo, $mensaje, $enlace = '') {
        try {
            $query = "INSERT INTO NOTIFICACION (us_id, tipo, mensaje, enlace, leido) VALUES (?, ?, ?, ?, FALSE)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$us_id, $tipo, $mensaje, $enlace]);
            return true;
        } catch (\Exception $e) {
            error_log("Error creando notificación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @param int $us_id
     * @return array
     */
    public function getUnreadNotifications($us_id) {
        try {
            $query = "SELECT * FROM NOTIFICACION WHERE us_id = ? AND leido = FALSE ORDER BY fecha_creacion DESC LIMIT 50";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$us_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param int $us_id
     * @return array
     */
    public function getAllNotifications($us_id) {
        try {
            $query = "SELECT * FROM NOTIFICACION WHERE us_id = ? ORDER BY fecha_creacion DESC LIMIT 50";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$us_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param int $not_id
     * @param int $us_id Validar pertenencia
     */
    public function markAsRead($not_id, $us_id) {
        try {
            $query = "UPDATE NOTIFICACION SET leido = TRUE WHERE not_id = ? AND us_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$not_id, $us_id]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica préstamos activos con fecha_ent próxima (Hoy y Mañana)
     * y genera notificaciones para el usuario si no existen ya ese día.
     */
    public function checkExpiringLoans() {
        try {
            $query = "
                SELECT p.pres_id, p.us_id, p.fecha_ent, a.tipo as activo_tipo
                FROM PRESTAMO p
                JOIN ACTIVO a ON p.act_id = a.act_id
                WHERE p.estatus = 'Activo' AND p.fecha_ent IS NOT NULL
            ";
            $stmt = $this->db->query($query);
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($loans as $loan) {
                $fecha_ent = new \DateTime($loan['fecha_ent']);
                $hoy = new \DateTime();
                $hoy->setTime(0, 0, 0);
                
                $fecha_ent_date = clone $fecha_ent;
                $fecha_ent_date->setTime(0, 0, 0);

                $interval = $hoy->diff($fecha_ent_date);
                // Si interval is invert (hoy > fecha_ent), es negativo. 
                // format('%R%a') da "+1" (falta 1 dia), "+0" (es hoy), "-1" (pasó 1 día)
                $days = $interval->format('%R%a'); 

                $mensaje = "";
                if ($days === '+1') {
                    $mensaje = "Aviso: Tu préstamo del equipo '{$loan['activo_tipo']}' vence mañana.";
                } else if ($days === '+0' || $days === '-0') {
                    $mensaje = "URGENTE: Tu préstamo del equipo '{$loan['activo_tipo']}' vence hoy.";
                }

                if ($mensaje !== "") {
                    // Evitar duplicar notificaciones en el mismo día
                    $checkQuery = "SELECT not_id FROM NOTIFICACION WHERE us_id = ? AND mensaje = ? AND DATE(fecha_creacion) = CURRENT_DATE";
                    $checkStmt = $this->db->prepare($checkQuery);
                    $checkStmt->execute([$loan['us_id'], $mensaje]);
                    
                    if (!$checkStmt->fetchColumn()) {
                        $this->createNotification($loan['us_id'], 'Prestamo', $mensaje, 'prestamos.php');
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error en checkExpiringLoans: " . $e->getMessage());
        }
    }
}
