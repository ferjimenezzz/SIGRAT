<?php
/**
 * @file AuthController.php
 * @summary Controlador de Seguridad y Autenticación.
 * @description Gestiona el hashing de contraseñas, validación de credenciales y manejo de sesiones mediante JWT.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/EmailService.php';

use Config\Database;
use Services\EmailService;
use PDO;

class AuthController {
    private $db;
    private $secret_key = "SIGRAT_SECRET_KEY_2026_IPN_SECURE"; 

    public function __construct() {
        $this->db = Database::getConnection();
        // Cargar JWT_SECRET desde el archivo .env si existe
        $env_file = dirname(__DIR__) . '/.env';
        if (file_exists($env_file)) {
            $env = parse_ini_file($env_file);
            if (isset($env['JWT_SECRET']) && !empty($env['JWT_SECRET'])) {
                $this->secret_key = $env['JWT_SECRET'];
            }
        }
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60 * 8); 
        $payload_json = json_encode($payload);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload_json);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->secret_key, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public function validateJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        list($header, $payload, $signature) = $parts;

        $valid_signature = hash_hmac('sha256', $header . "." . $payload, $this->secret_key, true);
        $valid_signature_encoded = $this->base64UrlEncode($valid_signature);

        if ($signature !== $valid_signature_encoded) return false;

        // Decodificación robusta con padding para Base64Url
        $remainder = strlen($payload) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $payload .= str_repeat('=', $padlen);
        }
        
        $payload_data = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
            return false; 
        }

        return $payload_data;
    }

    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * @summary Registra un nuevo usuario en la base de datos.
     * @description Verifica si el correo ya existe, busca un rol por defecto, hashea la contraseña e inserta el registro.
     * @param string $nombre Nombre completo del usuario.
     * @param string $correo Correo institucional.
     * @param string $telefono Número telefónico.
     * @param string $carrera Carrera o área.
     * @param string $password Contraseña en texto plano.
     * @return array Arreglo asociativo con success (booleano) y message (string).
     */
    public function register($nombre, $correo, $telefono, $carrera, $password) {
        try {
            // 1. Verificar si el correo ya está en uso
            $stmt = $this->db->prepare("SELECT us_id FROM USUARIO WHERE correo = ?");
            $stmt->execute([$correo]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'El correo ya está registrado.'];
            }

            // 2. Determinar el rol_id por defecto (Usuario)
            $rol_id = 1; // Fallback
            $stmtRole = $this->db->prepare("SELECT rol_id FROM ROLES WHERE nombre = 'Usuario' LIMIT 1");
            $stmtRole->execute();
            if ($rol = $stmtRole->fetch()) {
                $rol_id = $rol['rol_id'];
            }



            // 3. Hashear la contraseña por seguridad
            $hashedPassword = self::hashPassword($password);

            // 4. Insertar el nuevo registro
            $stmtInsert = $this->db->prepare("INSERT INTO USUARIO (nombre, correo, telefono, carrera, contrasena, rol_id, estatus) VALUES (?, ?, ?, ?, ?, ?, 'Activo')");
            $success = $stmtInsert->execute([$nombre, $correo, $telefono, $carrera, $hashedPassword, $rol_id]);

            if ($success) {
                return ['success' => true, 'message' => 'Usuario registrado exitosamente.'];
            } else {
                return ['success' => false, 'message' => 'No se pudo insertar el usuario en la base de datos.'];
            }
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }
    }

    public static function logout() {
        // Limpiar cookie con ruta genérica
        // Limpiar cookie a nivel de dominio
        setcookie('auth_token', '', time() - 3600, '/');
        
        // Limpiar cookie en la ruta de script actual dinámicamente para mayor seguridad en subdirectorios
        if (isset($_SERVER['SCRIPT_NAME'])) {
            $script_dir = dirname($_SERVER['SCRIPT_NAME']);
            $script_dir = str_replace('\\', '/', $script_dir);
            $script_dir = rtrim($script_dir, '/');
            if (!empty($script_dir)) {
                // Codificar cada segmento del path para evitar errores por espacios en setcookie()
                $segments = explode('/', $script_dir);
                $encoded_segments = array_map('rawurlencode', $segments);
                $encoded_dir = implode('/', $encoded_segments);
                
                setcookie('auth_token', '', time() - 3600, $encoded_dir . '/');
                setcookie('auth_token', '', time() - 3600, $encoded_dir);
            }
        }
        
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_unset();
        session_destroy();
    }

    /**
     * @summary Solicita restablecer la contraseña (genera token y envía correo).
     */
    public function requestPasswordReset($correo) {
        $stmt = $this->db->prepare("SELECT us_id FROM usuario WHERE correo = ? AND estatus = 'Activo'");
        $stmt->execute([$correo]);
        $user = $stmt->fetch();

        if (!$user) {
            // Por seguridad no revelamos si el correo existe o no, pero retornamos success.
            return ["success" => true, "message" => "Si el correo está registrado, recibirás un enlace de recuperación."];
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora de vigencia

        $update = $this->db->prepare("UPDATE usuario SET reset_token = ?, reset_expires = ? WHERE us_id = ?");
        $update->execute([$token, $expires, $user['us_id']]);

        $emailService = new EmailService();
        $sent = $emailService->sendPasswordRecovery($correo, $token);

        if ($sent) {
            return ["success" => true, "message" => "Si el correo está registrado, recibirás un enlace de recuperación."];
        } else {
            return ["success" => false, "message" => "Ocurrió un error al intentar enviar el correo. Por favor contacta al administrador."];
        }
    }

    /**
     * @summary Restablece la contraseña si el token es válido.
     */
    public function resetPassword($token, $newPassword) {
        $stmt = $this->db->prepare("SELECT us_id FROM usuario WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return ["success" => false, "message" => "El enlace de recuperación es inválido o ha expirado."];
        }

        $hash = self::hashPassword($newPassword);
        
        $update = $this->db->prepare("UPDATE usuario SET contrasena = ?, reset_token = NULL, reset_expires = NULL WHERE us_id = ?");
        $update->execute([$hash, $user['us_id']]);

        return ["success" => true, "message" => "Tu contraseña ha sido restablecida exitosamente. Ya puedes iniciar sesión."];
    }
}
