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
    public function getFiltered($fecha_inicio = null, $fecha_fin = null, $us_id = null, $modulo = null, $extra_filters = []) {
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

        // Nuevos filtros avanzados (Mockup)
        if (!empty($extra_filters['buscar_usuario'])) {
            $query .= " AND u.nombre ILIKE ?";
            $params[] = "%" . $extra_filters['buscar_usuario'] . "%";
        }
        if (!empty($extra_filters['edificio']) && $extra_filters['edificio'] !== 'Todos') {
            $query .= " AND b.accion ILIKE ?";
            $params[] = "%" . $extra_filters['edificio'] . "%";
        }
        if (!empty($extra_filters['estado']) && $extra_filters['estado'] !== 'Todos') {
            $query .= " AND b.accion ILIKE ?";
            $params[] = "%" . $extra_filters['estado'] . "%";
        }
        if (!empty($extra_filters['incluir_prestamos']) && $extra_filters['incluir_prestamos'] !== 'Todos') {
            if ($extra_filters['incluir_prestamos'] === 'Si') {
                $query .= " AND b.modulo_afectado = 'PRESTAMOS'";
            } elseif ($extra_filters['incluir_prestamos'] === 'No') {
                $query .= " AND b.modulo_afectado != 'PRESTAMOS'";
            }
        }
        if (!empty($extra_filters['incluir_transferencias']) && $extra_filters['incluir_transferencias'] !== 'Todos') {
            if ($extra_filters['incluir_transferencias'] === 'Si') {
                $query .= " AND b.accion ILIKE '%transferencia%'";
            } elseif ($extra_filters['incluir_transferencias'] === 'No') {
                $query .= " AND b.accion NOT ILIKE '%transferencia%'";
            }
        }

        $query .= " ORDER BY b.fecha_hora DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene estadísticas de la bitácora para las tarjetas de KPIs.
     * @return array
     */
    public function getAuditStats() {
        $stats = [
            'total' => 0,
            'hoy' => 0,
            'modulo_activo' => 'N/A',
            'usuarios_activos' => 0
        ];

        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM bitacora");
            $stats['total'] = $stmt->fetchColumn() ?: 0;

            $stmt = $this->db->query("SELECT COUNT(*) FROM bitacora WHERE fecha_hora::DATE = CURRENT_DATE");
            $stats['hoy'] = $stmt->fetchColumn() ?: 0;

            $stmt = $this->db->query("SELECT modulo_afectado FROM bitacora GROUP BY modulo_afectado ORDER BY COUNT(*) DESC LIMIT 1");
            $mod = $stmt->fetchColumn();
            if ($mod) $stats['modulo_activo'] = $mod;

            $stmt = $this->db->query("SELECT COUNT(DISTINCT us_id) FROM bitacora");
            $stats['usuarios_activos'] = $stmt->fetchColumn() ?: 0;
        } catch (\Exception $e) {
            error_log("Error Audit Stats: " . $e->getMessage());
        }

        return $stats;
    }
}
