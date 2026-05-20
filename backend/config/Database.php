<?php
/**
 * @file Database.php
 * @summary Clase de conexión a la base de datos Supabase (PostgreSQL).
 * @description Credenciales actualizadas tras reset de contraseña.
 */

namespace Config;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'aws-1-us-east-1.pooler.supabase.com';
    private $port = '6543';
    private $user = 'postgres.ewxidsyynsvbhvodxowg';
    private $pass = 'Fjamnr050.1'; // Contraseña Nueva
    private $db   = 'postgres';

    private function __construct() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db};sslmode=require";
            
            $this->conn = new PDO(
                $dsn,
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log("Supabase Auth Error: " . $e->getMessage());
            die(json_encode(["error" => "Error de Autenticación Cloud: " . $e->getMessage()]));
        }
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
}
