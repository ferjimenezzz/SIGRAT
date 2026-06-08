<?php
/**
 * @file DashboardController.php
 * @summary Controlador para las estadísticas del panel de control.
 * @description Centraliza el conteo de datos dinámicos para el dashboard administrativo.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;

class DashboardController {
    private $db;

    /**
     * Constructor del controlador. Inicializa la conexión a la base de datos.
     */
    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene el resumen de estadísticas para los contadores superiores.
     * @return array Conjunto de conteos para el dashboard.
     */
    public function getStats() {
        // 1. Reservas programadas para la fecha actual
        $resToday = $this->db->query("SELECT COUNT(*) FROM RESERVA WHERE fecha_uso = CURRENT_DATE")->fetchColumn();

        // 2. Activos cuyo estatus indica que no están disponibles (en uso, préstamo, etc.)
        $actInUse = $this->db->query("SELECT COUNT(*) FROM ACTIVO WHERE estatus != 'Disponible'")->fetchColumn();

        // 3. Alertas críticas (Activos en mantenimiento, extraviados o dañados)
        $stockAlerts = $this->db->query("SELECT COUNT(*) FROM ACTIVO WHERE estatus IN ('Mantenimiento', 'Extraviado', 'Dañado')")->fetchColumn();

        // 4. Incidentes de seguridad o errores críticos en la bitácora
        $incidents = $this->db->query("SELECT COUNT(*) FROM BITACORA WHERE accion LIKE '%ERROR%' OR accion LIKE '%FALLA%' OR modulo_afectado = 'SEGURIDAD'")->fetchColumn();

        return [
            'reservas_hoy' => (int)$resToday,
            'activos_uso' => (int)$actInUse,
            'alertas_stock' => (int)$stockAlerts,
            'incidentes' => (int)$incidents
        ];
    }

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

    /**
     * Calcula el porcentaje de aulas utilizadas hoy.
     * @return int Porcentaje de uso (0-100).
     */
    public function getClassroomUsagePercent() {
        try {
            $totalSpaces = $this->db->query("SELECT COUNT(*) FROM ESPACIO")->fetchColumn();
            $usedToday = $this->db->query("SELECT COUNT(DISTINCT esp_id) FROM RESERVA WHERE fecha_uso = CURRENT_DATE")->fetchColumn();
            if ($totalSpaces == 0) return 0;
            return (int) round(($usedToday / $totalSpaces) * 100);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene el total de accesos RFID registrados hoy.
     * @return int Número de lecturas RFID del día.
     */
    public function getRFIDAccessToday() {
        try {
            return (int) $this->db->query("SELECT COUNT(*) FROM LECTOR WHERE fecha = CURRENT_DATE")->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene el total de préstamos activos actualmente.
     * @return int Número de préstamos con estatus 'Activo'.
     */
    public function getActiveLoanCount() {
        try {
            return (int) $this->db->query("SELECT COUNT(*) FROM PRESTAMO WHERE estatus = 'Activo'")->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene el uso de cada espacio individual (para gráfica de barras).
     * @return array Lista de espacios con su conteo de reservas.
     */
    public function getSpaceUsageByName() {
        $query = "
            SELECT e.nombre_numero, e.tipo, COUNT(r.re_id) as total_reservas
            FROM ESPACIO e
            LEFT JOIN RESERVA r ON e.esp_id = r.esp_id AND r.fecha_uso >= CURRENT_DATE - INTERVAL 7 DAY
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
