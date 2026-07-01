<?php

/**
 * @file DashboardController.php
 * @summary Controlador para las estadísticas del panel de control.
 * @description Centraliza el conteo de datos dinámicos para el dashboard administrativo.
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
class DashboardController {
    private $db;

    /**
     * Constructor del controlador. Inicializa la conexión a la base de datos.
     */
    public function __construct() {
        $this->db = Database::getConnection();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (calculateGrowth)
// ============================================================================
    /**
     * Calcula el porcentaje de crecimiento entre dos valores.
     */

    private function calculateGrowth($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100);
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (getStats)
// ============================================================================
    /**
     * Obtiene el resumen de estadísticas para los contadores superiores.
     * @return array Conjunto de conteos para el dashboard.
     */

    public function getStats() {
        // 1. Reservas programadas para la fecha actual y ayer
        $resToday = $this->db->query("SELECT COUNT(*) FROM RESERVA WHERE fecha_uso = CURRENT_DATE")->fetchColumn();
        $resYesterday = $this->db->query("SELECT COUNT(*) FROM RESERVA WHERE fecha_uso = CURRENT_DATE - INTERVAL '1 day'")->fetchColumn();

        // 2. Activos cuyo estatus indica que no están disponibles (en uso, préstamo, etc.)
        $actInUse = $this->db->query("SELECT COUNT(*) FROM ACTIVO WHERE estatus != 'Disponible'")->fetchColumn();

        // 3. Alertas críticas (Activos en mantenimiento, extraviados o dañados)
        $stockAlerts = $this->db->query("SELECT COUNT(*) FROM ACTIVO WHERE estatus IN ('Mantenimiento', 'Extraviado', 'Dañado')")->fetchColumn();

        // 4. Incidentes de seguridad o errores críticos en la bitácora
        $incidents = $this->db->query("SELECT COUNT(*) FROM BITACORA WHERE accion LIKE '%ERROR%' OR accion LIKE '%FALLA%' OR modulo_afectado = 'SEGURIDAD'")->fetchColumn();

        return [
            'reservas_hoy' => (int)$resToday,
            'reservas_growth' => $this->calculateGrowth($resToday, $resYesterday),
            'activos_uso' => (int)$actInUse,
            'alertas_stock' => (int)$stockAlerts,
            'incidentes' => (int)$incidents
        ];
    }


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (getSpaceUsage)
// ============================================================================
    /**
     * Obtiene el uso de espacios por edificio.
     * @return array
     */

    public function getSpaceUsage() {
        $cic = $this->db->query("SELECT COUNT(*) FROM RESERVA r JOIN ESPACIO e ON r.esp_id = e.esp_id WHERE e.edificio = 'CIC'")->fetchColumn();
        $pidet = $this->db->query("SELECT COUNT(*) FROM RESERVA r JOIN ESPACIO e ON r.esp_id = e.esp_id WHERE e.edificio = 'PIDET'")->fetchColumn();
        
        return [
            'CIC' => (int)$cic,
            'PIDET' => (int)$pidet
        ];
    }


// ============================================================================
// SECCIÓN 6: LÓGICA DE NEGOCIO Y OPERACIÓN (getReservationsByDay)
// ============================================================================
    /**
     * Obtiene el total de reservas agrupado por los últimos 7 días.
     * @return array
     */

    public function getReservationsByDay() {
        $query = "
            SELECT fecha_uso, COUNT(*) as total 
            FROM reserva 
            WHERE fecha_uso >= CURRENT_DATE - INTERVAL '7 days'
            GROUP BY fecha_uso 
            ORDER BY fecha_uso ASC
        ";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }


// ============================================================================
// SECCIÓN 7: LÓGICA DE NEGOCIO Y OPERACIÓN (getVisitsStats)
// ============================================================================
    /**
     * Obtiene la distribución de estatus de las visitas.
     * @return array
     */

    public function getVisitsStats() {
        $query = "SELECT estatus, COUNT(*) as total FROM visita GROUP BY estatus";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }


// ============================================================================
// SECCIÓN 8: LÓGICA DE NEGOCIO Y OPERACIÓN (getClassroomUsageStats)
// ============================================================================
    /**
     * Calcula las estadísticas de uso de aulas (semana actual vs pasada).
     * @return array Arreglo con uso actual y crecimiento.
     */

    public function getClassroomUsageStats() {
        try {
            $totalSpaces = $this->db->query("SELECT COUNT(*) FROM ESPACIO")->fetchColumn();
            if ($totalSpaces == 0) return ['current' => 0, 'growth' => 0];

            $usedThisWeek = $this->db->query("SELECT COUNT(DISTINCT esp_id) FROM RESERVA WHERE fecha_uso >= CURRENT_DATE - INTERVAL '7 days'")->fetchColumn();
            $usedLastWeek = $this->db->query("SELECT COUNT(DISTINCT esp_id) FROM RESERVA WHERE fecha_uso >= CURRENT_DATE - INTERVAL '14 days' AND fecha_uso < CURRENT_DATE - INTERVAL '7 days'")->fetchColumn();

            $pctThisWeek = (int) round(($usedThisWeek / $totalSpaces) * 100);
            $pctLastWeek = (int) round(($usedLastWeek / $totalSpaces) * 100);

            return [
                'current' => $pctThisWeek,
                'growth' => $pctThisWeek - $pctLastWeek
            ];
        } catch (\Exception $e) {
            return ['current' => 0, 'growth' => 0];
        }
    }


// ============================================================================
// SECCIÓN 9: LÓGICA DE NEGOCIO Y OPERACIÓN (getRFIDAccessStats)
// ============================================================================
    /**
     * Obtiene estadísticas de accesos RFID de hoy vs ayer.
     * @return array
     */

    public function getRFIDAccessStats() {
        try {
            $today = (int) $this->db->query("SELECT COUNT(*) FROM LECTOR WHERE fecha = CURRENT_DATE")->fetchColumn();
            $yesterday = (int) $this->db->query("SELECT COUNT(*) FROM LECTOR WHERE fecha = CURRENT_DATE - INTERVAL '1 day'")->fetchColumn();
            
            return [
                'current' => $today,
                'growth' => $this->calculateGrowth($today, $yesterday)
            ];
        } catch (\Exception $e) {
            return ['current' => 0, 'growth' => 0];
        }
    }


// ============================================================================
// SECCIÓN 10: LÓGICA DE NEGOCIO Y OPERACIÓN (getLoanStats)
// ============================================================================
    /**
     * Obtiene estadísticas de préstamos.
     * @return array
     */

    public function getLoanStats() {
        try {
            $active = (int) $this->db->query("SELECT COUNT(*) FROM PRESTAMO WHERE estatus = 'Activo'")->fetchColumn();
            $createdToday = (int) $this->db->query("SELECT COUNT(*) FROM PRESTAMO WHERE CAST(fecha_prestamo AS DATE) = CURRENT_DATE")->fetchColumn();
            $createdYesterday = (int) $this->db->query("SELECT COUNT(*) FROM PRESTAMO WHERE CAST(fecha_prestamo AS DATE) = CURRENT_DATE - INTERVAL '1 day'")->fetchColumn();
            
            return [
                'current' => $active,
                'growth' => $this->calculateGrowth($createdToday, $createdYesterday)
            ];
        } catch (\Exception $e) {
            return ['current' => 0, 'growth' => 0];
        }
    }


// ============================================================================
// SECCIÓN 11: LÓGICA DE NEGOCIO Y OPERACIÓN (getSpaceUsageByName)
// ============================================================================
    /**
     * Obtiene el uso de cada espacio individual (para gráfica de barras).
     * @param string $rango Puede ser 'semana', 'mes' o 'ano'.
     * @return array Lista de espacios con su conteo de reservas.
     */

    public function getSpaceUsageByName($rango = 'semana') {
        $interval = "INTERVAL '7 days'";
        if ($rango === 'mes') {
            $interval = "INTERVAL '1 month'";
        } elseif ($rango === 'ano') {
            $interval = "INTERVAL '1 year'";
        }

        $query = "
            SELECT e.nombre_numero, e.tipo, COUNT(r.re_id) as total_reservas
            FROM ESPACIO e
            LEFT JOIN RESERVA r ON e.esp_id = r.esp_id AND r.fecha_uso >= CURRENT_DATE - $interval
            GROUP BY e.esp_id, e.nombre_numero, e.tipo
            ORDER BY total_reservas DESC
            LIMIT 8
        ";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }


// ============================================================================
// SECCIÓN 12: LÓGICA DE NEGOCIO Y OPERACIÓN (getInventoryStatus)
// ============================================================================
    /**
     * Obtiene la distribución de estatus del inventario de activos.
     * @return array Conteo de activos por estatus.
     */

    public function getInventoryStatus() {
        $query = "SELECT estatus, COUNT(*) as total FROM ACTIVO GROUP BY estatus";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }


// ============================================================================
// SECCIÓN 13: LÓGICA DE NEGOCIO Y OPERACIÓN (getTodayReservations)
// ============================================================================
    /**
     * Obtiene la lista de reservaciones programadas para hoy con detalles.
     * @return array Lista de reservaciones con información del espacio y usuario.
     */

    public function getTodayReservations() {
        $query = "
            SELECT r.re_id, r.hora_ent, r.hora_sal, r.estatus, r.num_alumnos,
                   e.nombre_numero, e.edificio, e.tipo as tipo_espacio,
                   COALESCE(u.nombre, v.nombre, 'Sin asignar') as solicitante
            FROM RESERVA r
            JOIN ESPACIO e ON r.esp_id = e.esp_id
            LEFT JOIN USUARIO u ON r.us_id = u.us_id
            LEFT JOIN VISITA v ON r.vis_id = v.vis_id
            WHERE r.fecha_uso = CURRENT_DATE
            ORDER BY r.hora_ent ASC
        ";
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
