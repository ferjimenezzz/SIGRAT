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
}
