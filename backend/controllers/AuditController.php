<?php
/**
 * @file AuditController.php
 * @summary Controlador de Auditoría y Trazabilidad.
 * @description Ajustado para PostgreSQL (Supabase) con sintaxis de casteo de fechas ::DATE.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;

class AuditController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Registra una acción en la bitácora.
     */
    public function log($us_id, $accion, $modulo) {
        try {
            // Usamos minúsculas para PostgreSQL
            $query = "INSERT INTO bitacora (us_id, accion, modulo_afectado) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$us_id, $accion, $modulo]);
        } catch (\Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene registros filtrados para la bitácora.
     */
    public function getFiltered($fecha_inicio = null, $fecha_fin = null, $us_id = null, $modulo = null) {
        $query = "SELECT b.*, u.nombre as usuario_nombre 
                  FROM bitacora b 
                  LEFT JOIN usuario u ON b.us_id = u.us_id 
                  WHERE 1=1";
        $params = [];

        if ($fecha_inicio) {
            // En PostgreSQL usamos ::DATE para extraer la fecha del timestamp
            $query .= " AND b.fecha_hora::DATE >= ?";
            $params[] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $query .= " AND b.fecha_hora::DATE <= ?";
            $params[] = $fecha_fin;
        }
        if ($us_id) {
            $query .= " AND b.us_id = ?";
            $params[] = $us_id;
        }
        if ($modulo) {
            $query .= " AND b.modulo_afectado = ?";
            $params[] = $modulo;
        }

        $query .= " ORDER BY b.fecha_hora DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
