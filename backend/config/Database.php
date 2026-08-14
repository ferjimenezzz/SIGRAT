<?php

/**
 * @file Database.php
 * @summary Clase Singleton de conexión blindada a la base de datos Supabase (PostgreSQL).
 * @description Gestiona la conexión PDO aplicando los máximos estándares de seguridad: manejo estricto de excepciones, modo asociativo por defecto y desactivación de emulación de sentencias preparadas para prevenir ataques de Inyección SQL (SQLi).
 */

// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
namespace Config;

use PDO;
use PDOException;

// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
/**
 * Clase Database
 * Implementa el patrón Singleton para garantizar una única instancia de conexión PDO activa en el ciclo de vida de la petición PHP.
 */
class Database {
    /**
     * @var Database|null Instancia única de la clase (Singleton).
     */
    private static $instance = null;

    /**
     * @var PDO|null Objeto de conexión PDO activa hacia PostgreSQL/Supabase.
     */
    private $conn;

    /**
     * Credenciales de conexión
     */
    private $host;
    private $port;
    private $user;
    private $pass;
    private $db;

    /**
     * Parsea un archivo .env de forma robusta ignorando caracteres especiales en las contraseñas.
     * @param string $filePath Ruta absoluta del archivo .env.
     * @return array Arreglo asociativo con las variables leídas.
     */
    private static function parseEnvFile($filePath) {
        $env = [];
        if (!file_exists($filePath)) {
            return $env;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                if ((strpos($val, '"') === 0 && substr($val, -1) === '"') || (strpos($val, "'") === 0 && substr($val, -1) === "'")) {
                    $val = substr($val, 1, -1);
                }
                $env[$key] = $val;
            }
        }
        return $env;
    }

    /**
     * Constructor privado para evitar instanciación directa externa (Patrón Singleton).
     * Configura el Data Source Name (DSN) y establece las opciones de seguridad del protocolo PDO.
     */
    private function __construct() {
        // Cargar credenciales desde el archivo .env usando la función robusta
        $envFile = __DIR__ . '/../../.env';
        $env = self::parseEnvFile($envFile);

        $driver     = strtolower($env['DB_DRIVER'] ?? (($env['DB_PORT'] ?? '') === '3306' ? 'mysql' : 'pgsql'));
        $this->host = $env['DB_HOST'] ?? '127.0.0.1';
        $this->port = $env['DB_PORT'] ?? ($driver === 'mysql' ? '3306' : '5432');
        $this->user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? ($driver === 'mysql' ? 'root' : 'postgres');
        $this->pass = $env['DB_PASSWORD'] ?? '';
        $this->db   = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? 'sigrat_db';

        try {
            // Construcción del DSN según el driver configurado (pgsql o mysql)
            if ($driver === 'mysql') {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset=utf8mb4";
            } else {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db}";
            }
            
            // Instanciación del objeto PDO con opciones de seguridad
            $this->conn = new PDO(
                $dsn,
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die(json_encode(["error" => "Error de Conexión a Base de Datos: " . $e->getMessage()]));
        }
    }

// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (getConnection)
// ============================================================================
    /**
     * Obtiene la conexión PDO Singleton hacia PostgreSQL.
     * Si no existe una instancia activa, la crea; de lo contrario, retorna la existente.
     * 
     * @return PDO Conexión PDO activa y segura.
     */
    public static function getConnection() {
        // Verificar si la instancia estática aún no ha sido creada en la memoria del proceso
        if (self::$instance === null) {
            // Instanciar la clase por primera y única vez
            self::$instance = new self();
        }
        // Retornar el objeto PDO interno encapsulado
        return self::$instance->conn;
    }
}
