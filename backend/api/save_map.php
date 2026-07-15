<?php
/**
 * API: save_map.php
 * Recibe un payload JSON con la configuración de los polígonos y actualiza assets/map_data.json
 */
header('Content-Type: application/json; charset=utf-8');

// Configuración de rutas (asume que este script está en backend/api/)
$json_file_path = __DIR__ . '/../../frontend/assets/map_data.json';

try {
    // Solo permitir POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST.');
    }

    // Obtener y decodificar el payload JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('El payload enviado no es un JSON válido.');
    }

    // Validar estructura básica
    if (!isset($data['PIDET_alta']) || !isset($data['CIC_alta'])) {
        throw new Exception('Estructura de mapa inválida.');
    }

    // Formatear JSON para que sea legible (pretty print)
    $json_string = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Escribir archivo
    $result = file_put_contents($json_file_path, $json_string);

    if ($result === false) {
        throw new Exception('No se pudo escribir en el archivo map_data.json. Verifica los permisos.');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Configuración de mapas guardada correctamente.',
        'bytes_written' => $result
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
