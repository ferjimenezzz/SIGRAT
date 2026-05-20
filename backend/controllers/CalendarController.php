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
}
