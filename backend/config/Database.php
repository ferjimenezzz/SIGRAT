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
     * Credenciales de conexión cloud (Supabase Pooler).
     */
    private $host = 'aws-1-us-east-1.pooler.supabase.com';
    private $port = '6543';
    private $user = 'postgres.ewxidsyynsvbhvodxowg';
    private $pass = 'Fjamnr050.1';
    private $db   = 'postgres';

    /**
     * Constructor privado para evitar instanciación directa externa (Patrón Singleton).
     * Configura el Data Source Name (DSN) y establece las opciones de seguridad del protocolo PDO.
     */
    private function __construct() {
        try {
            // Construcción del DSN para PostgreSQL requiriendo canal cifrado SSL/TLS de forma obligatoria
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db};sslmode=require";
            
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
                    
                    // PDO::ATTR_EMULATE_PREPARES: CRÍTICO DE SEGURIDAD. Al fijar en false, obliga al motor PostgreSQL
                    // a compilar el AST (Abstract Syntax Tree) de la consulta separadamente de los parámetros reales ($1, $2),
                    // inmunizando el backend contra ataques de Inyección SQL (SQL Injection).
                    PDO::ATTR_EMULATE_PREPARES => false,
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
