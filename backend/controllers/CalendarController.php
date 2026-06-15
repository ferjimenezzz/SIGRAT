<?php
/**
 * @file CalendarController.php
 * @summary Controlador para la visualización y filtrado del calendario.
 * @description Ajustado para compatibilidad con PostgreSQL (Supabase) usando minúsculas.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;

class CalendarController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene todos los eventos (Reservas Aprobadas) con filtros opcionales.
     */
    public function getEvents($edificio = null, $esp_id = null) {
        // Usamos minúsculas para compatibilidad nativa con PostgreSQL
        $query = "SELECT r.*, e.nombre_numero, e.edificio, u.nombre as usuario_nombre
                  FROM reserva r
                  JOIN espacio e ON r.esp_id = e.esp_id
                  LEFT JOIN usuario u ON r.us_id = u.us_id
                  WHERE r.estatus IN ('Aprobada', 'Aprobado')";
        
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

    /**
     * Obtiene eventos del calendario con filtros avanzados y soporte de estados.
     */
    public function getEventsFiltered($edificio = null, $esp_id = null, $tipo = null, $fecha_inicio = null, $fecha_fin = null, $us_id = null, $status = null) {
        $query = "SELECT r.*, e.nombre_numero, e.tipo as espacio_tipo, e.edificio, e.capacidad as espacio_capacidad, u.nombre as usuario_nombre, u.correo as usuario_correo
                  FROM reserva r
                  JOIN espacio e ON r.esp_id = e.esp_id
                  LEFT JOIN usuario u ON r.us_id = u.us_id
                  WHERE 1=1";
        
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
            $query .= " AND r.us_id = ?";
            $params[] = (int)$us_id;
        }
        if ($status) {
            // Estatus puede ser Aprobada, Pendiente, Rechazada
            $query .= " AND (r.status = ? OR r.estatus = ?)";
            $params[] = $status;
            $params[] = $status;
        }
        
        $query .= " ORDER BY r.fecha_uso ASC, r.hora_ent ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

