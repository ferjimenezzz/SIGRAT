<?php
/**
 * @file global_search.php
 * @summary Endpoint API para la búsqueda global en el sistema SIGRAT.
 * @description Permite buscar coincidencias rápidas en usuarios, activos, reservas y espacios mediante una consulta paramétrica 'q'. Requiere sesión activa y retorna un JSON con los resultados clasificados por categoría.
 * @package Backend\API
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN DE SESIÓN Y CARGA DE DEPENDENCIAS
// ============================================================================
// Iniciar o reanudar la sesión PHP para verificar los privilegios del usuario
session_start();

// Importar el Singleton de base de datos para ejecutar sentencias PDO
require_once '../config/Database.php';

// ============================================================================
// SECCIÓN 2: CONFIGURACIÓN DE CABECERAS HTTP Y SEGURIDAD CORS
// ============================================================================
// Habilitar peticiones desde el cliente web y definir respuesta en formato JSON UTF-8
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ============================================================================
// SECCIÓN 3: MIDDLEWARE DE AUTENTICACIÓN Y VALIDACIÓN DE ENTRADA
// ============================================================================
// 1. Verificar si el usuario cuenta con una sesión activa ('us_id')
if (!isset($_SESSION['us_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autorizado"]);
    exit();
}

// 2. Obtener y limpiar el término de búsqueda enviando por GET ('?q=...')
$query = $_GET['q'] ?? '';

// 3. Si la cadena de búsqueda tiene menos de 2 caracteres, retornar arreglo vacío por rendimiento
if (strlen(trim($query)) < 2) {
    echo json_encode([]);
    exit();
}

// ============================================================================
// SECCIÓN 4: EJECUCIÓN DE BÚSQUEDA MULTI-TABLA (ACTIVOS Y USUARIOS)
// ============================================================================
try {
    // Obtener conexión PDO activa en modo excepción
    $db = \Config\Database::getConnection();
    $results = [];
    $likeQuery = '%' . trim($query) . '%';

    // ------------------------------------------------------------------------
    // 4.1. BÚSQUEDA EN CATÁLOGO DE ACTIVOS
    // ------------------------------------------------------------------------
    // Buscar coincidencias en marca, modelo, número de serie o tarjeta RFID asociada
    $stmt = $db->prepare("SELECT act_id, marca, modelo, num_serie, tag_id FROM ACTIVO WHERE (marca LIKE ? OR modelo LIKE ? OR num_serie LIKE ? OR tag_id LIKE ?) AND estatus != 'Baja' LIMIT 5");
    $stmt->execute([$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            "type" => "activo",
            "title" => $row['marca'] . " " . $row['modelo'],
            "subtitle" => "Serie: " . $row['num_serie'] . ($row['tag_id'] ? " | TAG: " . $row['tag_id'] : ""),
            "url" => "inventario.php" // Enruta al módulo de inventario institucional
        ];
    }

    // ------------------------------------------------------------------------
    // 4.2. BÚSQUEDA EN PADRÓN DE USUARIOS
    // ------------------------------------------------------------------------
    // Buscar coincidencias en nombre, apellido, correo electrónico o matrícula/RFC
    $stmt = $db->prepare("SELECT us_id, nombre, apellido, correo, rfc_matricula FROM USUARIO WHERE (nombre LIKE ? OR apellido LIKE ? OR correo LIKE ? OR rfc_matricula LIKE ?) AND estatus != 'Inactivo' LIMIT 5");
    $stmt->execute([$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            "type" => "usuario",
            "title" => $row['nombre'] . " " . $row['apellido'],
            "subtitle" => "Correo: " . $row['correo'] . " | Matrícula: " . $row['rfc_matricula'],
            "url" => "usuarios.php" // Enruta al módulo de gestión de usuarios
        ];
    }

    // Retornar lista combinada y tipificada en formato JSON
    echo json_encode($results);
} catch (Exception $e) {
    // En caso de fallo de base de datos, retornar código HTTP 500
    http_response_code(500);
    echo json_encode(["error" => "Error del servidor"]);
}
