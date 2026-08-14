<?php
/**
 * @file UsuarioController.php
 * @summary Controlador Maestro de Usuarios.
 * @description Gestiona el ciclo de vida de los usuarios en el sistema (CRUD).
 * Nota Arquitectónica: Este controlador ha sido desvinculado del hardware RFID,
 * los usuarios ya no utilizan tarjetas físicas; su control de acceso es estrictamente lógico.
 */


// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
use PDO;
use PDOException;


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
class UsuarioController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (getAllUsers)
// ============================================================================
    /**
     * Obtiene la lista completa de usuarios activos.
     * @return array Objeto JSON estándar con data o mensaje de error.
     */

    public function getAllUsers() {
        try {
            // Se utiliza LEFT JOIN para evitar descartar usuarios que momentáneamente no tengan rol
            $query = "
                SELECT u.us_id, u.nombre, u.correo, u.estatus, r.nombre as rol 
                FROM USUARIO u 
                LEFT JOIN ROLES r ON u.rol_id = r.rol_id 
                WHERE u.estatus = 'Activo'
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return ["success" => true, "data" => $stmt->fetchAll()];
        } catch (PDOException $e) {
            error_log("Error en getAllUsers: " . $e->getMessage());
            return ["success" => false, "error" => "Fallo al consultar usuarios."];
        }
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (createUser)
// ============================================================================
    /**
     * Crea un nuevo usuario en el sistema.
     * @param string $nombre Nombre completo.
     * @param string $correo Correo electrónico institucional.
     * @param string $contrasena_plana Contraseña sin hashear.
     * @param int $rol_id ID del rol asignado.
     * @return array
     */

    public function createUser($nombre, $correo, $contrasena_plana, $rol_id) {
        try {
            // Protección mediante password_hash nativo de PHP (Bcrypt/Argon2)
            $hash = password_hash($contrasena_plana, PASSWORD_DEFAULT);

            // Prepared statement para evitar Inyección SQL de manera estricta
            $query = "INSERT INTO USUARIO (nombre, correo, contrasena, rol_id, estatus) VALUES (?, ?, ?, ?, 'Activo')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$nombre, $correo, $hash, $rol_id]);

            return ["success" => true, "message" => "Usuario registrado correctamente.", "id" => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Error en createUser: " . $e->getMessage());
            // Manejo de error por correo duplicado (constraint UNIQUE en base de datos)
            if ($e->getCode() == 23505 || $e->getCode() == 23000) {
                return ["success" => false, "error" => "El correo electrónico ya está registrado."];
            }
            return ["success" => false, "error" => "No se pudo registrar el usuario."];
        }
    }


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (softDeleteUser)
// ============================================================================
    /**
     * Ejecuta un Soft Delete sobre el usuario.
     * @param int $us_id ID del usuario a eliminar.
     * @return array
     */

    public function softDeleteUser($us_id) {
        try {
            // Se actualiza el estatus a 'Inactivo' en lugar de un DELETE físico.
            // Regla de DBA: Nunca borres registros de usuarios para preservar el histórico de auditoría (reservas/logs).
            $query = "UPDATE USUARIO SET estatus = 'Inactivo' WHERE us_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$us_id]);

            if ($stmt->rowCount() > 0) {
                return ["success" => true, "message" => "Usuario dado de baja exitosamente (Soft Delete)."];
            }
            return ["success" => false, "error" => "Usuario no encontrado o ya estaba inactivo."];
        } catch (PDOException $e) {
            error_log("Error en softDeleteUser: " . $e->getMessage());
            return ["success" => false, "error" => "Fallo al dar de baja al usuario."];
        }
    }
}
?>
