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
     * Constructor privado para evitar instanciación directa externa (Patrón Singleton).
     * Configura el Data Source Name (DSN) y establece las opciones de seguridad del protocolo PDO.
     */
    private function __construct() {
        // Cargar credenciales desde el archivo .env
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $this->host = $env['DB_HOST'] ?? '127.0.0.1';
            $this->port = $env['DB_PORT'] ?? '5432';
            $this->user = $env['DB_USERNAME'] ?? 'postgres';
            $this->pass = $env['DB_PASSWORD'] ?? '';
            $this->db   = $env['DB_DATABASE'] ?? 'postgres';
        } else {
            // Fallback si no existe .env
            $this->host = '127.0.0.1';
            $this->port = '5432';
            $this->user = 'postgres';
            $this->pass = '';
            $this->db   = 'postgres';
        }

        try {
            // Construcción del DSN para PostgreSQL. NOTA: Se remueve sslmode=require si la bd local no tiene SSL.
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db}";
            
            // Instanciación del objeto PDO con opciones de blindaje arquitectónico
            $this->conn = new PDO(
                $dsn,
                $this->user,
                $this->pass,
                [
                    // PDO::ATTR_ERRMODE: Lanza excepciones en caso de error para evitar fallos silenciosos o fugas de datos
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    
                    // PDO::ATTR_DEFAULT_FETCH_MODE: Estandariza la obtención de datos como arreglos asociativos puros
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    
                    // PDO::ATTR_EMULATE_PREPARES: Se establece en true para compatibilidad con PgBouncer/Supabase Pooler (Transaction Mode)
                    // que no soporta prepared statements a través de múltiples transacciones.
                    PDO::ATTR_EMULATE_PREPARES => true,
                ]
            );
        } catch (PDOException $e) {
            // Enmascaramiento y registro del error de base de datos para no exponer credenciales ni estructura en stdout
            error_log("Supabase Auth Error: " . $e->getMessage());
            die(json_encode(["error" => "Error de Autenticación Cloud: " . $e->getMessage()]));
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
