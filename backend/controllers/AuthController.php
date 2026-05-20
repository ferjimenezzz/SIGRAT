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
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Genera un token JWT firmado digitalmente.
     * @param array $payload Datos a incluir en el token.
     * @param int $duration Duración de validez en segundos (por defecto 2 horas).
     * @return string Token firmado en formato de tres partes (Header.Payload.Signature).
     */
    public function generateJWT($payload, $duration = 7200) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + $duration; 
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
     * Limpia las cookies de sesión (auth_token y refresh_token) y destruye la sesión PHP actual.
     */
    public static function logout() {
        // Limpiar cookie con múltiples variantes de ruta para asegurar efectividad en XAMPP
        $path = '/creaciones%20antigravity/Estadias/';
        setcookie('auth_token', '', time() - 3600, $path);
        setcookie('auth_token', '', time() - 3600, '/');
        setcookie('refresh_token', '', time() - 3600, $path);
        setcookie('refresh_token', '', time() - 3600, '/');
        
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_unset();
        session_destroy();
    }
}
