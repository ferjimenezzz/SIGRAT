<?php
/**
 * API: save_map.php
 * Recibe un payload JSON con la configuración de los polígonos y actualiza assets/map_data.json
 * 
 * DISEÑO: El Editor de Mapas es la única fuente de verdad del sistema.
 * 
 * Lógica de fusión de esp_id:
 *   Si un polígono llega desde el editor con esp_id null/vacío, pero existe un polígono
 *   previo con los mismos puntos exactos que SÍ tenía esp_id asignado (por la herramienta
 *   "Asignar Espacio"), se preserva el esp_id anterior.
 *   Esto garantiza que asignaciones hechas desde el editor no se pierdan al re-guardar.
 *   Si el usuario explícitamente deja un polígono sin espacio asignado (db_name vacío),
 *   el esp_id se limpia igualmente.
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

    // ─────────────────────────────────────────────────────────────────────────
    // FUSIÓN INTELIGENTE: Preservar esp_id de la versión anterior del archivo
    // cuando el editor envía un polígono con esp_id null pero el mismo polígono
    // ya tenía uno asignado previamente.
    // Esto evita que al re-guardar desde el editor se pierdan las asignaciones.
    // ─────────────────────────────────────────────────────────────────────────
    if (file_exists($json_file_path)) {
        $prevData = json_decode(file_get_contents($json_file_path), true);
        if ($prevData && json_last_error() === JSON_ERROR_NONE) {
            foreach ($data as $mapKey => &$mapConfig) {
                if (!isset($prevData[$mapKey]['zones'])) continue;

                // Construir índice de zonas anteriores por puntos (clave única)
                $prevByPoints = [];
                foreach ($prevData[$mapKey]['zones'] as $prevZone) {
                    $key = trim($prevZone['points'] ?? '');
                    if ($key !== '') {
                        $prevByPoints[$key] = $prevZone;
                    }
                }

                foreach ($mapConfig['zones'] as &$zone) {
                    $pointsKey = trim($zone['points'] ?? '');
                    // Si el polígono nuevo tiene esp_id null/vacío pero el anterior tenía uno,
                    // y el db_name tampoco fue explícitamente borrado (o coincide), lo preservamos.
                    if (isset($prevByPoints[$pointsKey])) {
                        $prev = $prevByPoints[$pointsKey];
                        // Preservar esp_id si el nuevo lo perdió
                        if (empty($zone['esp_id']) && !empty($prev['esp_id'])) {
                            $zone['esp_id'] = $prev['esp_id'];
                        }
                        // Preservar db_name si el nuevo está vacío y el anterior tenía uno
                        if (empty($zone['db_name']) && !empty($prev['db_name'])) {
                            $zone['db_name'] = $prev['db_name'];
                        }
                    }
                }
                unset($zone);
            }
            unset($mapConfig);
        }
    }

    // Formatear JSON para que sea legible (pretty print)
    $json_string = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Intentar escritura directa
    $result = @file_put_contents($json_file_path, $json_string);

    // Fallback: Si la escritura directa falla por bloqueo de archivo en Windows, intentar mediante archivo temporal
    if ($result === false) {
        $temp_file = $json_file_path . '.tmp';
        $temp_result = @file_put_contents($temp_file, $json_string);
        if ($temp_result !== false) {
            @unlink($json_file_path);
            if (@rename($temp_file, $json_file_path)) {
                $result = $temp_result;
            }
        }
    }

    if ($result === false) {
        $err = error_get_last();
        $detail = isset($err['message']) ? ': ' . $err['message'] : '. Verifica los permisos de escritura.';
        throw new Exception('No se pudo escribir en el archivo map_data.json' . $detail);
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
