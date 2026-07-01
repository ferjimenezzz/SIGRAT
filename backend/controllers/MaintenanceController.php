<?php

/**
 * @file MaintenanceController.php
 * @summary Controlador para la gestión de mantenimiento de equipo.
 * @description Registra mantenimientos preventivos y correctivos para asegurar la operatividad de los activos.
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
class MaintenanceController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (logMaintenance)
// ============================================================================
    /**
     * Registra un evento de mantenimiento.
     */

    public function logMaintenance($act_id, $descripcion, $responsable) {
        try {
            $query = "INSERT INTO MANTENIMIENTO (act_id, fecha, descripcion, responsable, estatus) VALUES (?, (CURRENT_DATE), ?, ?, 'En Proceso')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$act_id, $descripcion, $responsable]);
            
            // Marcar activo en mantenimiento
            $this->db->prepare("UPDATE ACTIVO SET estatus = 'Mantenimiento' WHERE act_id = ?")->execute([$act_id]);
            
            return ["success" => true];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
