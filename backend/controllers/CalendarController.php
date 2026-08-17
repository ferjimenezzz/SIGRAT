<?php

/**
 * @file CalendarController.php
 * @summary Controlador para la visualización y filtrado del calendario.
 * @description Ajustado para compatibilidad con PostgreSQL (Supabase) usando minúsculas.
 */


// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
class CalendarController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (getEvents)
// ============================================================================
    /**
     * Obtiene todos los eventos (Reservas Aprobadas) con filtros opcionales.
     */

    public function getEvents($edificio = null, $esp_id = null) {
        // Usamos minúsculas para compatibilidad nativa con PostgreSQL / MySQL
        $query = "SELECT r.*, e.nombre_numero, e.edificio, COALESCE(u.nombre, v.nombre, 'Invitado') as usuario_nombre
                  FROM reserva r
                  JOIN espacio e ON r.esp_id = e.esp_id
                  LEFT JOIN usuario u ON r.us_id = u.us_id
                  LEFT JOIN visita v ON r.vis_id = v.vis_id
                  WHERE r.estatus IN ('Aprobada', 'Aprobado')
                    AND LOWER(r.status) NOT IN ('cancelled', 'cancelada')
                    AND LOWER(r.estatus) NOT IN ('cancelled', 'cancelada')";
        
        $params = [];
        if ($edificio) {
            $query .= " AND e.edificio = ?";
            $params[] = $edificio;
        }
        if ($esp_id) {
            $query .= " AND e.esp_id = ?";
            $params[] = $esp_id;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (getEventsFiltered)
// ============================================================================
    /**
     * Obtiene eventos del calendario con filtros avanzados y soporte de estados.
     */

    public function getEventsFiltered($edificio = null, $esp_id = null, $tipo = null, $fecha_inicio = null, $fecha_fin = null, $us_id = null, $status = null) {
        // 1. Auto-cancelar reservaciones pendientes expiradas (compatibilidad MySQL y SQL estándar)
        try {
            $this->db->exec("UPDATE reserva SET status = 'cancelled', estatus = 'Cancelada', cancel_reason = 'Expirada automáticamente por falta de aprobación a tiempo' WHERE (LOWER(status) = 'pending' OR LOWER(estatus) = 'pendiente') AND (fecha_uso < CURDATE() OR (fecha_uso = CURDATE() AND hora_sal <= CURRENT_TIME()))");
        } catch (\PDOException $e) {
            // Fallback por si la versión del motor de BD difiere
            error_log("Error en auto-cancelación del calendario: " . $e->getMessage());
        }

        $query = "SELECT r.*, e.nombre_numero, e.tipo as espacio_tipo, e.edificio, e.capacidad as espacio_capacidad, u.nombre as usuario_nombre, u.correo as usuario_correo
                  FROM reserva r
                  JOIN espacio e ON r.esp_id = e.esp_id
                  LEFT JOIN usuario u ON r.us_id = u.us_id
                  WHERE LOWER(r.status) NOT IN ('cancelled', 'cancelada') AND LOWER(r.estatus) NOT IN ('cancelled', 'cancelada')";
        
        $params = [];
        if ($edificio) {
            $query .= " AND e.edificio = ?";
            $params[] = $edificio;
        }
        if ($esp_id) {
            $query .= " AND e.esp_id = ?";
            $params[] = (int)$esp_id;
        }
        if ($tipo) {
            $query .= " AND e.tipo = ?";
            $params[] = $tipo;
        }
        if ($fecha_inicio) {
            $query .= " AND r.fecha_uso >= ?";
            $params[] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $query .= " AND r.fecha_uso <= ?";
            $params[] = $fecha_fin;
        }
        if ($us_id) {
            // Permitir ver reservaciones aprobadas de cualquier usuario (para consultar ocupación real) más las reservaciones propias del usuario en cualquier estado
            $query .= " AND (r.estatus IN ('Aprobada', 'Aprobado') OR r.status = 'approved' OR r.us_id = ?)";
            $params[] = (int)$us_id;
        }
        if ($status) {
            // Estatus puede ser Aprobada, Pendiente, Rechazada (soporte para estatus y status)
            $query .= " AND (r.estatus = ? OR r.status = ?)";
            $params[] = $status;
            $params[] = $status;
        }
        
        $query .= " ORDER BY r.fecha_uso ASC, r.hora_ent ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

