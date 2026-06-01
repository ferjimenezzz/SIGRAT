<?php
/**
 * @file AuthController.php
 * @summary Controlador de Seguridad y Autenticación.
 * @description Gestiona el hashing de contraseñas, validación de credenciales y manejo de sesiones mediante JWT.
 */

namespace Controllers;

require_once __DIR__ . '/../config/Database.php';

use Config\Database;
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

    public static function logout() {
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
}
