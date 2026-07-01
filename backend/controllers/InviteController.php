<?php
/**
 * @file InviteController.php
 * @summary Controlador para la gestión de códigos de invitación para visitas externas.
 * @description Ajustado para PostgreSQL (Supabase) con nombres de tabla en minúsculas.
 */


// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use PDO;


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
class InviteController {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (generate)
// ============================================================================
    /**
     * Genera un código de invitación y crea el registro de visita.
     */

    public function generate($nombre, $correo, $anfitrion_id) {
        try {
            $codigo = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            
            // Usamos minúsculas para PostgreSQL
            $query = "INSERT INTO visita (nombre, correo, codigo_acceso, us_anfitrion, estatus) VALUES (?, ?, ?, ?, 'Generado')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$nombre, $correo, $codigo, $anfitrion_id]);
            
            $vis_id = $this->db->lastInsertId();
            $this->audit->log($anfitrion_id, "Generado código de invitación: $codigo para $nombre", "VISITAS");
            
            return [
                "success" => true,
                "codigo" => $codigo,
                "vis_id" => $vis_id
            ];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (validate)
// ============================================================================
    /**
     * Valida un código de invitación.
     */

    public function validate($codigo) {
        $query = "SELECT * FROM visita WHERE codigo_acceso = ? AND estatus = 'Generado'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$codigo]);
        return $stmt->fetch();
    }


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (getAllActive)
// ============================================================================
    /**
     * Obtiene todas las invitaciones activas.
     */

    public function getAllActive() {
        $query = "SELECT v.*, u.nombre as anfitrion_nombre 
                  FROM visita v 
                  LEFT JOIN usuario u ON v.us_anfitrion = u.us_id 
                  WHERE v.codigo_acceso IS NOT NULL 
                  ORDER BY v.vis_id DESC";
        return $this->db->query($query)->fetchAll();
    }
}
