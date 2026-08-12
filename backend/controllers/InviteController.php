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
require_once __DIR__ . '/../services/EmailService.php';
require_once 'AuditController.php';

use Config\Database;
use Controllers\AuditController;
use Services\EmailService;
use PDO;


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
class InviteController {
    private $db;
    private $audit;
    private $emailService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->audit = new AuditController();
        $this->emailService = new EmailService();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (generate)
// ============================================================================
    /**
     * Genera un código de invitación (vigencia: 24 horas), crea el registro de visita y envía correo al invitado.
     */

    public function generate($nombre, $correo, $anfitrion_id) {
        try {
            $codigo = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            
            // Obtener nombre del anfitrión para el correo
            $anfitrionNombre = '';
            if (!empty($anfitrion_id)) {
                $stmtHost = $this->db->prepare("SELECT CONCAT(nombre, ' ', COALESCE(apellido, '')) FROM usuario WHERE us_id = ?");
                $stmtHost->execute([$anfitrion_id]);
                $anfitrionNombre = trim($stmtHost->fetchColumn() ?: '');
            }

            // Usamos minúsculas para PostgreSQL e insertamos fecha de acceso actual
            $query = "INSERT INTO visita (nombre, correo, codigo_acceso, us_anfitrion, fecha_acceso, estatus) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 'Generado')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$nombre, $correo, $codigo, $anfitrion_id]);
            
            $vis_id = $this->db->lastInsertId();
            $this->audit->log($anfitrion_id, "Generado código de invitación: $codigo para $nombre", "VISITAS");
            
            // Enviar notificación por correo electrónico al invitado
            if (!empty($correo)) {
                $this->emailService->sendInvitationEmail($correo, $nombre, $codigo, $anfitrionNombre, 24);
            }

            return [
                "success" => true,
                "codigo" => $codigo,
                "vis_id" => $vis_id,
                "expira_horas" => 24
            ];
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (validate)
// ============================================================================
    /**
     * Valida un código de invitación verificando que su estatus sea 'Generado' y que no haya superado las 24 horas.
     */

    public function validate($codigo) {
        $query = "SELECT * FROM visita WHERE codigo_acceso = ? AND estatus = 'Generado'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$codigo]);
        $row = $stmt->fetch();

        if ($row && !empty($row['fecha_acceso'])) {
            $createdTime = strtotime($row['fecha_acceso']);
            if ((time() - $createdTime) > 86400) { // 24 horas = 86400 segundos
                // Código expirado
                $stmtExp = $this->db->prepare("UPDATE visita SET estatus = 'Expirado' WHERE vis_id = ?");
                $stmtExp->execute([$row['vis_id']]);
                return false;
            }
        }
        return $row;
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
