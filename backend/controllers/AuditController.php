<?php

/**
 * @file AuditController.php
 * @summary Controlador de Auditoría y Trazabilidad ampliado para nuevos reportes.
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
class AuditController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (log)
// ============================================================================
    /**
     * Registra una acción en la bitácora.
     */

    public function log($us_id, $accion, $modulo) {
        try {
            $query = "INSERT INTO bitacora (us_id, accion, modulo_afectado) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$us_id, $accion, $modulo]);
        } catch (\Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
            return false;
        }
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (getGeneralActivity)
// ============================================================================
    /**
     * Reporte: Actividad general del sistema (Bitácora raw)
     */

    public function getGeneralActivity($filters) {
        $query = "SELECT b.*, u.nombre as usuario_nombre 
                  FROM bitacora b 
                  LEFT JOIN usuario u ON b.us_id = u.us_id 
                  WHERE 1=1";
        $params = [];

        if (!empty($filters['fecha_inicio'])) {
            $query .= " AND DATE(b.fecha_hora) >= ?";
            $params[] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $query .= " AND DATE(b.fecha_hora) <= ?";
            $params[] = $filters['fecha_fin'];
        }
        if (!empty($filters['modulo'])) {
            $query .= " AND b.modulo_afectado = ?";
            $params[] = $filters['modulo'];
        }
        if (!empty($filters['buscar_usuario'])) {
            $query .= " AND u.nombre LIKE ?";
            $params[] = "%" . $filters['buscar_usuario'] . "%";
        }
        if (!empty($filters['estado']) && $filters['estado'] !== 'Todos') {
            if ($filters['estado'] === 'Exitoso') {
                $query .= " AND b.accion NOT LIKE '%error%' AND b.accion NOT LIKE '%falla%'";
            } else {
                $query .= " AND (b.accion LIKE '%error%' OR b.accion LIKE '%falla%')";
            }
        }

        $query .= " ORDER BY b.fecha_hora DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (getAttendanceReport)
// ============================================================================
    /**
     * Reporte: Asistencia a Aulas
     */

    public function getAttendanceReport($filters) {
        $query = "SELECT r.re_id, r.fecha_uso, r.hora_ent, r.hora_sal, r.num_alumnos,
                         e.nombre_numero as espacio, e.edificio,
                         u.nombre as responsable
                  FROM reserva r
                  JOIN espacio e ON r.esp_id = e.esp_id
                  JOIN usuario u ON r.us_id = u.us_id
                  WHERE r.estatus = 'Aprobada'";
        $params = [];

        if (!empty($filters['fecha_inicio'])) {
            $query .= " AND r.fecha_uso >= ?";
            $params[] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $query .= " AND r.fecha_uso <= ?";
            $params[] = $filters['fecha_fin'];
        }
        if (!empty($filters['edificio']) && $filters['edificio'] !== 'Todos') {
            $query .= " AND e.edificio = ?";
            $params[] = $filters['edificio'];
        }

        $query .= " ORDER BY r.fecha_uso DESC, r.hora_ent DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


// ============================================================================
// SECCIÓN 6: LÓGICA DE NEGOCIO Y OPERACIÓN (getTopSpaces)
// ============================================================================
    /**
     * Reporte: Aulas más utilizadas
     */

    public function getTopSpaces($filters) {
        $query = "SELECT e.esp_id, e.nombre_numero, e.edificio, e.tipo, 
                         COUNT(r.re_id) as total_reservas, 
                         SUM(COALESCE(r.num_alumnos, 0)) as total_asistencia
                  FROM espacio e
                  LEFT JOIN reserva r ON e.esp_id = r.esp_id AND r.estatus = 'Aprobada'";
        
        $params = [];

        if (!empty($filters['fecha_inicio'])) {
            $query .= " AND r.fecha_uso >= ?";
            $params[] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $query .= " AND r.fecha_uso <= ?";
            $params[] = $filters['fecha_fin'];
        }
        if (!empty($filters['edificio']) && $filters['edificio'] !== 'Todos') {
            $query .= " AND e.edificio = ?";
            $params[] = $filters['edificio'];
        }

        $query .= " GROUP BY e.esp_id, e.nombre_numero, e.edificio, e.tipo";
        
        if (!empty($filters['metrica']) && $filters['metrica'] === 'asistencia') {
            $query .= " ORDER BY total_asistencia DESC, total_reservas DESC";
        } else {
            $query .= " ORDER BY total_reservas DESC, total_asistencia DESC";
        }

        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


// ============================================================================
// SECCIÓN 7: LÓGICA DE NEGOCIO Y OPERACIÓN (getUsageByBuilding)
// ============================================================================
    /**
     * Reporte: Uso por edificio
     */

    public function getUsageByBuilding($filters) {
        $query = "SELECT e.edificio,
                         COUNT(DISTINCT e.esp_id) as total_espacios,
                         COUNT(r.re_id) as total_reservas, 
                         SUM(COALESCE(r.num_alumnos, 0)) as total_asistencia
                  FROM espacio e
                  LEFT JOIN reserva r ON e.esp_id = r.esp_id AND r.estatus = 'Aprobada'";
        
        $params = [];

        if (!empty($filters['fecha_inicio'])) {
            $query .= " AND r.fecha_uso >= ?";
            $params[] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $query .= " AND r.fecha_uso <= ?";
            $params[] = $filters['fecha_fin'];
        }

        $query .= " GROUP BY e.edificio ORDER BY total_reservas DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


// ============================================================================
// SECCIÓN 8: LÓGICA DE NEGOCIO Y OPERACIÓN (getAttendanceByUser)
// ============================================================================
    /**
     * Reporte: Asistencia por usuario (profesores)
     */

    public function getAttendanceByUser($filters) {
        $query = "SELECT u.us_id, u.nombre, u.rol,
                         COUNT(r.re_id) as total_reservas, 
                         SUM(COALESCE(r.num_alumnos, 0)) as total_asistencia
                  FROM usuario u
                  JOIN reserva r ON u.us_id = r.us_id AND r.estatus = 'Aprobada'";
        $params = [];

        if (!empty($filters['fecha_inicio'])) {
            $query .= " AND r.fecha_uso >= ?";
            $params[] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $query .= " AND r.fecha_uso <= ?";
            $params[] = $filters['fecha_fin'];
        }
        if (!empty($filters['buscar_usuario'])) {
            $query .= " AND u.nombre LIKE ?";
            $params[] = "%" . $filters['buscar_usuario'] . "%";
        }

        $query .= " GROUP BY u.us_id, u.nombre, u.rol ORDER BY total_asistencia DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


// ============================================================================
// SECCIÓN 9: LÓGICA DE NEGOCIO Y OPERACIÓN (getAssetLoans)
// ============================================================================
    /**
     * Reporte: Préstamos de activos
     */

    public function getAssetLoans($filters) {
        $query = "SELECT p.pres_id, p.fecha_pres, p.fecha_ent, p.estatus,
                         a.tipo as activo_tipo, a.marca as activo_marca, a.num_inv as activo_inv,
                         u.nombre as usuario_nombre
                  FROM prestamo p
                  JOIN activo a ON p.act_id = a.act_id
                  JOIN usuario u ON p.us_id = u.us_id
                  WHERE 1=1";
        $params = [];

        if (!empty($filters['fecha_inicio'])) {
            $query .= " AND DATE(p.fecha_pres) >= ?";
            $params[] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $query .= " AND DATE(p.fecha_pres) <= ?";
            $params[] = $filters['fecha_fin'];
        }
        if (!empty($filters['buscar_usuario'])) {
            $query .= " AND u.nombre LIKE ?";
            $params[] = "%" . $filters['buscar_usuario'] . "%";
        }
        if (!empty($filters['buscar_activo'])) {
            $query .= " AND (a.tipo LIKE ? OR a.num_inv LIKE ?)";
            $params[] = "%" . $filters['buscar_activo'] . "%";
            $params[] = "%" . $filters['buscar_activo'] . "%";
        }

        $query .= " ORDER BY p.fecha_pres DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


// ============================================================================
// SECCIÓN 10: LÓGICA DE NEGOCIO Y OPERACIÓN (getInventoryMovements)
// ============================================================================
    /**
     * Reporte: Movimientos de inventario
     */

    public function getInventoryMovements($filters) {
        $filters['modulo'] = 'ACTIVOS';
        return $this->getGeneralActivity($filters);
    }


// ============================================================================
// SECCIÓN 11: LÓGICA DE NEGOCIO Y OPERACIÓN (getIncidents)
// ============================================================================
    /**
     * Reporte: Incidencias, alertas y mantenimientos
     */

    public function getIncidents($filters) {
        $filters['modulo'] = 'MANTENIMIENTO'; // O buscar por LIKE %incidencia%
        return $this->getGeneralActivity($filters);
    }


// ============================================================================
// SECCIÓN 12: LÓGICA DE NEGOCIO Y OPERACIÓN (getAuditStats)
// ============================================================================
    /**
     * Estadísticas generales (KPIs base)
     */

    public function getAuditStats() {
        $stats = [
            'total' => 0,
            'hoy' => 0,
            'modulo_activo' => 'N/A',
            'usuarios_activos' => 0
        ];

        try {
            $stats['total'] = $this->db->query("SELECT COUNT(*) FROM bitacora")->fetchColumn() ?: 0;
            $stats['hoy'] = $this->db->query("SELECT COUNT(*) FROM bitacora WHERE DATE(fecha_hora) = CURRENT_DATE")->fetchColumn() ?: 0;
            $mod = $this->db->query("SELECT modulo_afectado FROM bitacora GROUP BY modulo_afectado ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
            if ($mod) $stats['modulo_activo'] = $mod;
            $stats['usuarios_activos'] = $this->db->query("SELECT COUNT(DISTINCT us_id) FROM bitacora")->fetchColumn() ?: 0;
        } catch (\Exception $e) {
            error_log("Error Audit Stats: " . $e->getMessage());
        }

        return $stats;
    }
}
