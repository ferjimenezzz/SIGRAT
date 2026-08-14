<?php
/**
 * InventoryQueryBuilder.php
 * @summary Helper to construct SQL query for inventory export.
 * @description Generates a SELECT query based on provided filter parameters.
 *              Currently supports title for report naming; filters for future extensions.
 */

class InventoryQueryBuilder {
    /**
     * Build the SQL query for exporting inventory data.
     *
     * @param array $filters Associative array of filter criteria (e.g., title, date range, status).
     * @param array &$params Reference array to collect prepared statement parameters.
     * @return string The constructed SQL query.
     */
    public static function build(array $filters, array &$params): string {
        // For now, ignore filters and return full inventory query.
        // Future filter handling can be added here.
        $query = "WITH AllSpaces AS (
            SELECT esp_id AS space_id, nombre_numero, edificio::varchar FROM ESPACIO
            UNION ALL
            SELECT lug_id AS space_id, nombre_numero, edificio::varchar FROM LUGARES
        ),
        AllAssets AS (
            SELECT act_id AS act_id, tipo, marca, modelo, num_serie, num_inv, estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel, 'activo' AS item_type 
            FROM ACTIVO
            UNION ALL
            SELECT mob_id AS act_id, tipo, NULL AS marca, NULL AS modelo, NULL AS num_serie, num_inv, 'Disponible' AS estatus, tag_id, esp_asignado, imagen_url, descripcion, responsable, nivel, 'mobiliario' AS item_type 
            FROM MOBILIARIO
        )
        SELECT a.*, s.nombre_numero AS espacio_nombre, s.edificio 
        FROM AllAssets a 
        LEFT JOIN AllSpaces s ON a.esp_asignado = s.space_id 
        ORDER BY a.act_id DESC";
        // No parameters needed for the default query.
        $params = [];
        return $query;
    }
}
?>
