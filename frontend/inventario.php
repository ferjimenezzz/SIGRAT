<?php
/**
 * @file enrolamiento.php
 * @summary Interfaz de enrolamiento masivo de activos RFID en PHP.
 * @description Permite la captura de tags en tiempo real y el registro por lotes.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

require_once 'seguridad.php';
require_once '../backend/config/Database.php';
require_once '../backend/controllers/AssetController.php';
require_once '../backend/controllers/SpaceController.php';
require_once '../backend/controllers/TagController.php';
require_once '../backend/controllers/BatchController.php';

$db = Config\Database::getConnection();

// Cargar .env si existe para Cloudinary CDN
$env_file = dirname(__DIR__) . '/backend/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), ';') === 0 || strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!empty($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}
$cloudinaryCloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: 'nakzcjqs';
$cloudinaryUploadPreset = getenv('CLOUDINARY_UPLOAD_PRESET') ?: 'inventario_sigrat';

$assetController = new Controllers\AssetController();
$spaceController = new Controllers\SpaceController();
$tagController = new Controllers\TagController();
$batchController = new Controllers\BatchController();

$allSpaces = $db->query("
    SELECT esp_id, nombre_numero, edificio::varchar FROM ESPACIO
    UNION ALL
    SELECT lug_id AS esp_id, nombre_numero, edificio::varchar FROM LUGARES
    ORDER BY edificio, nombre_numero
")->fetchAll();

$availableTagsResponse = $tagController->getAvailableTags();
$availableTags = $availableTagsResponse['success'] ? $availableTagsResponse['data'] : [];

// Capturar filtros
$filtro = $_GET['filtro'] ?? null;
$query = "
WITH AllSpaces AS (
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
";
if ($filtro === 'alerta') {
    $query .= " WHERE a.estatus IN ('Mantenimiento', 'Extraviado', 'Dañado') AND a.item_type = 'activo'";
}
$query .= " ORDER BY a.act_id DESC";
$assets = $db->query($query)->fetchAll();

// Calcular estadísticas para la barra lateral
$totalAssets = count($assets);
$stats = [
    'Disponible' => 0,
    'Prestado' => 0,
    'Mantenimiento' => 0,
    'Extraviado' => 0
];
$categories = [
    'Equipos electrónicos' => 0,
    'Mobiliario' => 0,
    'Herramientas' => 0,
    'Otros' => 0
];
$locations = [];

foreach ($assets as $asset) {
    $est = $asset['estatus'] ?? 'Disponible';
    if (isset($stats[$est])) {
        $stats[$est]++;
    } else {
        if ($est === 'En préstamo' || $est === 'Prestado') {
            $stats['Prestado']++;
        } else if ($est === 'En mantenimiento' || $est === 'Mantenimiento') {
            $stats['Mantenimiento']++;
        } else if ($est === 'Extraviado') {
            $stats['Extraviado']++;
        } else {
            $stats['Disponible']++; // default
        }
    }

    if (isset($asset['item_type']) && $asset['item_type'] === 'mobiliario') {
        $categories['Mobiliario']++;
    } else {
        $t = strtolower($asset['tipo'] ?? '');
        if (strpos($t, 'laptop') !== false || strpos($t, 'computer') !== false || strpos($t, 'computadora') !== false || strpos($t, 'proyector') !== false || strpos($t, 'bocina') !== false || strpos($t, 'monitor') !== false || strpos($t, 'impresora') !== false || strpos($t, 'cámara') !== false || strpos($t, 'pc') !== false || strpos($t, 'tv') !== false || strpos($t, 'pantalla') !== false || strpos($t, 'router') !== false || strpos($t, 'switch') !== false || strpos($t, 'equipo') !== false) {
            $categories['Equipos electrónicos']++;
        } elseif (strpos($t, 'herramienta') !== false || strpos($t, 'taladro') !== false || strpos($t, 'multímetro') !== false || strpos($t, 'cautín') !== false || strpos($t, 'osciloscopio') !== false || strpos($t, 'pinzas') !== false || strpos($t, 'kit') !== false) {
            $categories['Herramientas']++;
        } else {
            $categories['Otros']++;
        }
    }

    $locName = $asset['espacio_nombre'] ?? 'Sin asignar';
    if ($locName !== 'Sin asignar') {
        $edificio = $asset['edificio'] ?? '';
        $fullLoc = ($edificio ? $edificio . ' - ' : '') . $locName;
        if (!isset($locations[$fullLoc])) {
            $locations[$fullLoc] = [
                'name' => $locName,
                'edificio' => $edificio,
                'count' => 0
            ];
        }
        $locations[$fullLoc]['count']++;
    }
}

uasort($locations, function($a, $b) {
    return $b['count'] - $a['count'];
});
$topLocations = array_slice($locations, 0, 5, true);


// Manejar actualización
// Manejar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_asset') {
    $res = $assetController->update($_POST['act_id'], $_POST);
    $targetTab = !empty($_POST['target_tab']) ? $_POST['target_tab'] : 'inventario';
    if (!$res['success']) {
        header("Location: inventario.php?tab=" . urlencode($targetTab) . "&error=" . urlencode($res['error']));
    } else {
        header("Location: inventario.php?tab=" . urlencode($targetTab) . "&success=edited");
    }
    exit();
}
// Manejar creación rápida desde la vista
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_asset') {
    $targetTab = !empty($_POST['target_tab']) ? $_POST['target_tab'] : 'inventario';
    if (isset($_POST['batch_mode']) && $_POST['batch_mode'] !== 'single') {
        $res = $assetController->createBatch($_POST);
    } else {
        $res = $assetController->create($_POST);
    }
    if (!$res['success']) {
        header("Location: inventario.php?tab=" . urlencode($targetTab) . "&error=" . urlencode($res['error']));
    } else {
        $msg = isset($res['count']) ? "batch_created&qty=" . $res['count'] : "created";
        header("Location: inventario.php?tab=" . urlencode($targetTab) . "&success=" . $msg);
    }
    exit();
}


// Manejar eliminación
if (isset($_GET['delete_id'])) {
    $itemType = $_GET['item_type'] ?? 'activo';
    $assetController->delete($_GET['delete_id'], $itemType);
    header("Location: inventario.php?tab=inventario&success=deleted");
    exit();
}

include 'header.php';
// Add SweetAlert2


// ============================================================================
// SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API
// ============================================================================
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
.select2-container .select2-selection--single { height: 40px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 6px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
</style>';
?>

<style>
    /* Ajustar margen y padding de contenedor principal */
    .main-container {
        background-color: #f8fafc !important;
    }

    /* Pestañas estilizadas */
    .tabs-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .tabs-container {
        display: flex;
        background: #e2e8f0;
        padding: 4px;
        border-radius: 10px;
        gap: 2px;
    }
    .btn-tab {
        border: none;
        background: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-tab.active {
        background: white;
        color: #2563eb;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* Grid de Contenedor */
    .inventory-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 16px;
        align-items: start;
    }

    /* Filtros Rápidos */
    .filters-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        gap: 12px;
        flex-wrap: wrap;
    }
    .filters-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        flex-grow: 1;
        overflow-x: auto;
        padding-bottom: 4px; /* for scrollbar */
    }
    .filters-left::-webkit-scrollbar {
        height: 4px;
    }
    .filters-left::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .search-input-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 12px;
        width: 180px;
        flex-shrink: 0;
        min-width: 0;
    }
    .search-input-wrapper input {
        border: none;
        outline: none;
        background: transparent;
        width: 100%;
        font-size: 13px;
    }
    .select-filter {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 28px 8px 10px;
        font-size: 13px;
        color: #334155;
        outline: none;
        cursor: pointer;
        flex-shrink: 0;
        min-width: 100px;
        appearance: auto;
    }
    .filters-right {
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }
    .btn-outline {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-outline:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    /* Tabla Premium */
    .premium-table-card {
        background: white;
        border-radius: 16px;
        border: 2px solid #2563eb;
        overflow-y: auto;
        overflow-x: auto;
        max-height: calc(100vh - 250px);
        box-shadow: 0 4px 12px rgba(37,99,235,0.08);
    }
    .premium-table {
        width: 100%;
        min-width: 700px;
        border-collapse: collapse;
    }
    .premium-table th {
        background: #1e293b;
        padding: 14px 12px;
        position: sticky;
        top: 0;
        z-index: 10;
        font-size: 11px;
        font-weight: 600;
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: none;
        white-space: normal;
        line-height: 1.3;
    }
    .premium-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 13.5px;
        vertical-align: top;
        word-break: break-word;
        overflow-wrap: break-word;
    }
    .premium-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Badges */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }
    .type-badge.equipo {
        background: #eff6ff;
        color: #2563eb;
    }
    .type-badge.mobiliario {
        background: #ecfdf5;
        color: #10b981;
    }
    .status-badge {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 700;
        display: inline-block;
    }

    /* Paginación */
    .pagination-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 18px;
    }
    .pagination-info {
        font-size: 13px;
        color: #64748b;
    }
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .pagination-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        transition: all 0.2s;
    }
    .pagination-btn:hover {
        background: #f1f5f9;
    }
    .pagination-btn.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    /* Sidebar de Estadísticas */
    .stats-sidebar {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .sidebar-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .sidebar-card h3 {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .donut-chart-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    .donut-svg-wrapper {
        position: relative;
        width: 140px;
        height: 140px;
    }
    .donut-center-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
    .donut-number {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
    }
    .donut-label {
        font-size: 10px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
    }
    .donut-legends {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #475569;
        font-weight: 500;
    }
    .legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    /* Barra de Progreso Categorías */
    .category-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .category-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .category-header {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }
    .category-bar-bg {
        height: 6px;
        background: #f1f5f9;
        border-radius: 999px;
        overflow: hidden;
    }
    .category-bar-fill {
        height: 100%;
        border-radius: 999px;
    }

    /* Ubicaciones Listado */
    .location-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .location-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 8px;
        border-bottom: 1px solid #f1f5f9;
    }
    .location-item:last-child {
        border-bottom: none;
    }
    .location-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .location-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    .location-details {
        display: flex;
        flex-direction: column;
    }
    .location-title {
        font-size: 12.5px;
        font-weight: 600;
        color: #1e293b;
    }
    .location-count {
        font-size: 11px;
        color: #64748b;
    }

    /* Panel lateral de Filtros */
    .filters-sidebar {
        display: flex;
        flex-direction: column;
        gap: 12px;
        animation: filterSlideIn 0.3s ease;
    }
    @keyframes filterSlideIn {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    .filters-panel-card {
        display: flex;
        flex-direction: column;
        gap: 16px;
        max-height: none;
        overflow-y: visible;
    }
    .filters-panel-card::-webkit-scrollbar {
        width: 4px;
    }
    .filters-panel-card::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .filters-panel-card::-webkit-scrollbar-track {
        background: transparent;
    }
    /* Active state for filters button */
    .btn-outline.filters-active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }
    .btn-outline.filters-active:hover {
        background: #1d4ed8;
        border-color: #1d4ed8;
    }
    .drawer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 16px;
    }
    .drawer-header h3 {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
    }
    .close-drawer-btn {
        background: none;
        border: none;
        font-size: 20px;
        color: #94a3b8;
        cursor: pointer;
    }
    .drawer-section {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .drawer-section h4 {
        font-size: 11px;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #334155;
        cursor: pointer;
        text-transform: none;
        margin-bottom: 0;
    }
    .checkbox-label input {
        width: 16px;
        height: 16px;
        accent-color: #10b981;
    }
    .drawer-footer {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: auto;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    /* Switch toggle estilo iOS */
    .toggle-switch-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        text-transform: none;
        margin-bottom: 0;
        font-size: 13px;
        font-weight: 500;
        color: #334155;
    }
    .switch-input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .switch-slider {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        background-color: #cbd5e1;
        border-radius: 34px;
        transition: .4s;
    }
    .switch-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        border-radius: 50%;
        transition: .4s;
    }
    .switch-input:checked + .switch-slider {
        background-color: #2563eb;
    }
    .switch-input:checked + .switch-slider:before {
        transform: translateX(20px);
    }

    /* Estilos para el Autocompletado Personalizado */
    .custom-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        max-height: 220px;
        overflow-y: auto;
        z-index: 1100;
        display: none;
        margin-top: 8px;
    }
    .custom-dropdown-item {
        padding: 12px 16px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        color: #475569;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }
    .custom-dropdown-item:last-child {
        border-bottom: none;
    }
    .custom-dropdown-item:hover {
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 800;
        padding-left: 20px;
    }

    /* Modal Rediseñado en 3 secciones */
    .custom-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(4px);
    }
    .custom-modal-content {
        background: white;
        width: 100%;
        max-width: 680px;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 14px;
        margin-bottom: 20px;
    }
    .modal-header h3 {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
    }
    .modal-header button {
        border: none;
        background: none;
        cursor: pointer;
        font-size: 20px;
        color: #94a3b8;
    }
    .modal-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        margin-top: 18px;
        margin-bottom: 12px;
        border-bottom: 1px solid #f8fafc;
        padding-bottom: 6px;
    }
    .modal-section-title i {
        color: #2563eb;
    }
    .modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .modal-grid.full-width {
        grid-template-columns: 1fr;
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 1024px) {
        .inventory-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 768px) {
        .modal-grid {
            grid-template-columns: minmax(0, 1fr) !important;
        }
        .premium-table-card {
            max-height: 500px;
        }
        .premium-page-header {
            padding: 10px 16px;
            margin-left: -16px;
            margin-right: -16px;
            margin-top: -16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tabs-row {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .tabs-container {
            width: 100%;
            justify-content: center;
        }
        .tabs-row > .btn-primary {
            width: 100%;
            justify-content: center;
        }
        /* Filtros responsivos: grid de 2 columnas en vez de columna centrada */
        .filters-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }
        .filters-bar .search-input-wrapper {
            grid-column: 1 / -1;
            max-width: 100%;
            width: 100%;
        }
        .filters-selects-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
        }
        .filters-selects-grid .select-filter {
            width: 100%;
            min-width: 0 !important;
            flex: none !important;
        }
        .filters-clear-btn {
            width: 100%;
            justify-content: center;
        }
        .search-input-wrapper {
            max-width: 100%;
            width: 100%;
        }
        .filters-right {
            width: 100%;
            justify-content: flex-end;
        }
    }
    @media (max-width: 480px) {
        .premium-header-left h1 {
            font-size: 18px;
        }
    }
</style>
<?php
// Evitar división por cero
$pctDisp = $totalAssets > 0 ? ($stats['Disponible'] / $totalAssets) * 100 : 0;
$pctPres = $totalAssets > 0 ? ($stats['Prestado'] / $totalAssets) * 100 : 0;
$pctMant = $totalAssets > 0 ? ($stats['Mantenimiento'] / $totalAssets) * 100 : 0;
$pctExtr = $totalAssets > 0 ? ($stats['Extraviado'] / $totalAssets) * 100 : 0;

$pctCat1 = $totalAssets > 0 ? ($categories['Equipos electrónicos'] / $totalAssets) * 100 : 0;
$pctCat2 = $totalAssets > 0 ? ($categories['Mobiliario'] / $totalAssets) * 100 : 0;
$pctCat3 = $totalAssets > 0 ? ($categories['Herramientas'] / $totalAssets) * 100 : 0;
$pctCat4 = $totalAssets > 0 ? ($categories['Otros'] / $totalAssets) * 100 : 0;
?>

<!-- Cabecera Estandar -->


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Inventario</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Gestiona y controla los activos y mobiliario institucional</p>
        </div>
    </header>

<!-- Barra de Pestañas y Acciones Globales -->
<div class="tabs-row" style="display: flex; justify-content: space-between; align-items: center;">
    <div class="tabs-container">
        <button onclick="switchAssetTab('inventario')" id="tab-inventario" class="btn-tab active"><i class="bi bi-laptop"></i> ACTIVOS (EQUIPOS)</button>
        <button onclick="switchAssetTab('mobiliario')" id="tab-mobiliario" class="btn-tab"><i class="bi bi-tablet-landscape"></i> MOBILIARIO</button>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn-outline" id="filtersBtn" onclick="toggleFiltersPanel()" style="height: 40px; border-radius: 8px; font-weight: 700; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px;">
            <i class="bi bi-funnel"></i> Filtros
        </button>
        <button type="button" class="btn-outline" onclick="window.open('../backend/reports/inventory_pdf.php', '_blank')" style="height: 40px; border-radius: 8px; font-weight: 700; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px; border-color: #ef4444; color: #ef4444;">
            <i class="bi bi-file-earmark-pdf"></i> PDF
        </button>
        <button type="button" class="btn-outline" onclick="exportTableToExcel('inventoryTable', 'Inventario_SIGRAT')" style="height: 40px; border-radius: 8px; font-weight: 700; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px; border-color: #10b981; color: #10b981;">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </button>
        <button class="btn-primary" type="button" onclick="openNewAssetModal()" style="height: 40px; border-radius: 8px; font-weight: 700; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px;">
            <i class="bi bi-plus-lg"></i> <span id="btnNewAssetText">Nuevo activo</span>
        </button>
    </div>
</div>

<!-- Sección de Inventario Principal -->
<div id="section-inventario" class="inventory-grid">

    <!-- Columna Izquierda: Tabla y Filtros -->
    <div>
        <?php 
            // Data options dynamic for filters
            $tiposDB = array_unique(array_filter(array_column($assets, 'tipo')));
            sort($tiposDB);

            $estadosDB = array_unique(array_filter(array_column($assets, 'estatus')));
            sort($estadosDB);

            // Consultar todos los edificios y espacios directamente de la tabla ESPACIO registrada en BD ($allSpaces)
            $edificiosDB = array_unique(array_filter(array_column($allSpaces, 'edificio')));
            sort($edificiosDB);

            $allUniqueSpaces = array_unique(array_filter(array_column($allSpaces, 'nombre_numero')));
            sort($allUniqueSpaces);

            $spacesByBuilding = [];
            foreach ($allSpaces as $spRow) {
                $ed = trim($spRow['edificio'] ?? '');
                $sp = trim($spRow['nombre_numero'] ?? '');
                if ($ed && $sp) {
                    if (!isset($spacesByBuilding[$ed])) $spacesByBuilding[$ed] = [];
                    if (!in_array($sp, $spacesByBuilding[$ed])) {
                        $spacesByBuilding[$ed][] = $sp;
                    }
                }
            }
            foreach ($spacesByBuilding as &$spArray) {
                sort($spArray);
            }
            unset($spArray);
        ?>
        <!-- Barra de Filtros Rápidos -->
        <div class="filters-bar" style="display: flex; flex-wrap: nowrap; gap: 10px; align-items: center; overflow-x: auto;">
            <div class="search-input-wrapper">
                <i class="bi bi-search" style="color: #94a3b8;"></i>
                <input type="text" id="searchInventory" placeholder="Buscar activo o serie..." style="width: 100%;" oninput="applyFilters()" onchange="applyFilters()">
            </div>
            
            <div class="filters-selects-grid" style="display: contents;">
                <select id="quickTypeFilter" class="select-filter" style="flex: 0 1 auto; min-width: 110px;" onchange="applyFilters()">
                    <option value="">Tipo de activo</option>
                    <?php foreach($tiposDB as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="statusFilter" class="select-filter" style="flex: 0 1 auto; min-width: 100px;" onchange="applyFilters()">
                    <option value="">Estado</option>
                    <?php foreach($estadosDB as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="quickLocationFilter" class="select-filter" onchange="updateSpaceFilter(); applyFilters();" style="flex: 0 1 auto; min-width: 110px;">
                    <option value="">Ubicación</option>
                    <?php foreach($edificiosDB as $ed): ?>
                        <option value="<?php echo htmlspecialchars($ed); ?>"><?php echo htmlspecialchars($ed); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="quickSpaceFilter" class="select-filter" style="flex: 0 1 auto; min-width: 110px;" onchange="applyFilters()">
                    <option value="">Espacio</option>
                    <?php foreach($allUniqueSpaces as $sp): ?>
                        <option value="<?php echo htmlspecialchars($sp); ?>"><?php echo htmlspecialchars($sp); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" class="btn-outline filters-clear-btn" id="clearFiltersTopBtn" onclick="clearAllFilters()" style="padding: 8px 16px; border-radius: 8px; flex: 0 0 auto; white-space: nowrap;">
                <i class="bi bi-eraser"></i> Limpiar filtros
            </button>
        </div>

        <!-- Tabla de Inventario -->
        <div class="premium-table-card">
            <table id="inventoryTable" class="premium-table">
                <colgroup>
                    <col style="width: 6%;"><!-- Foto -->
                    <col style="width: 20%;"><!-- Activo -->
                    <col style="width: 10%;"><!-- Tipo -->
                    <col style="width: 12%;"><!-- Nº Inventario -->
                    <col style="width: 12%;"><!-- Tag RFID -->
                    <col style="width: 16%;"><!-- Ubicación -->
                    <col style="width: 13%;"><!-- Estado -->
                    <col style="width: 11%;"><!-- Acción -->
                </colgroup>
                <thead>
                    <tr>
                        <th style="text-align: center;">Foto</th>
                        <th>Activo</th>
                        <th>Tipo</th>
                        <th>Nº Inventario</th>
                        <th>Tag RFID</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): 
                        $isMobiliario = (($asset['item_type'] ?? '') === 'mobiliario');
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($asset['estatus']); ?>" data-tipo-cat="<?php echo $isMobiliario ? 'Mobiliario' : 'Equipo'; ?>" data-tipo="<?php echo htmlspecialchars($asset['tipo'] ?? ''); ?>" data-ubicacion="<?php echo htmlspecialchars($asset['espacio_nombre'] ?? ''); ?>" data-edificio="<?php echo htmlspecialchars($asset['edificio'] ?? ''); ?>">
                        <td style="text-align: center; vertical-align: middle;">
                            <?php if (!empty($asset['imagen_url'])): ?>
                                <img src="<?php echo htmlspecialchars($asset['imagen_url'] ?? ''); ?>" alt="Foto" onclick="viewAssetImage('<?php echo htmlspecialchars(addslashes($asset['imagen_url'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes(($asset['tipo'] ?? '') . ' ' . ($asset['modelo'] ?? ''))); ?>', '<?php echo htmlspecialchars(addslashes($asset['num_inv'] ?? '')); ?>')" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; border: 1px solid #cbd5e1; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                            <?php else: ?>
                                <div style="width: 42px; height: 42px; border-radius: 8px; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; border: 1px dashed #cbd5e1; margin: 0 auto;" title="Sin foto">
                                    <i class="bi bi-camera"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a; overflow-wrap: break-word; word-break: break-word;"><?php echo htmlspecialchars(trim(($asset['tipo'] ?? '') . ' ' . ($asset['modelo'] ?? ''))); ?></div>
                            <?php 
                            $numSerie = trim($asset['num_serie'] ?? '');
                            if ($numSerie !== '' && strcasecmp($numSerie, 'N/A') !== 0): 
                            ?>
                            <div style="font-size: 11px; color: #64748b; font-weight: 500; margin-top: 3px; word-break: break-all; overflow-wrap: anywhere; line-height: 1.5; white-space: normal;">Serie: <?php echo htmlspecialchars($numSerie); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding-top: 12px;">
                            <?php if ($isMobiliario): ?>
                                <span class="type-badge mobiliario" style="white-space: nowrap;"><i class="bi bi-tablet-landscape"></i> Mobiliario</span>
                            <?php else: ?>
                                <span class="type-badge equipo" style="white-space: nowrap;"><i class="bi bi-laptop"></i> Equipo</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; color: #475569;">
                            <?php echo htmlspecialchars($asset['num_inv'] ?? ''); ?>
                        </td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; color: #2563eb;">
                            <?php echo htmlspecialchars($asset['tag_id'] ?? 'Sin asignar'); ?>
                        </td>
                        <td>
                            <?php 
                                $espNombre = htmlspecialchars($asset['espacio_nombre'] ?? 'Sin asignar');
                                $edificio  = htmlspecialchars($asset['edificio'] ?? '');
                            ?>
                            <div style="font-weight: 600; color: #0f172a; overflow-wrap: break-word; word-break: break-word; line-height: 1.5; white-space: normal;"><?php echo $espNombre; ?></div>
                            <?php if ($edificio): ?>
                            <div style="font-size: 11px; color: #64748b; font-weight: 500; margin-top: 2px;"><?php echo $edificio; ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badgeColor = '#64748b';
                            $badgeBg = '#f1f5f9';
                            $est = $asset['estatus'] ?? 'Disponible';
                            switch($est) {
                                case 'Disponible':
                                    $badgeColor = '#10b981';
                                    $badgeBg = '#ecfdf5';
                                    break;
                                case 'Prestado':
                                case 'En préstamo':
                                    $badgeColor = '#d97706';
                                    $badgeBg = '#fffbeb';
                                    break;
                                case 'Mantenimiento':
                                case 'En mantenimiento':
                                    $badgeColor = '#3b82f6';
                                    $badgeBg = '#eff6ff';
                                    break;
                                case 'Extraviado':
                                    $badgeColor = '#ef4444';
                                    $badgeBg = '#fef2f2';
                                    break;
                            }
                            ?>
                            <span class="status-badge" style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>;">
                                <?php echo htmlspecialchars($est); ?>
                            </span>
                        </td>
                        <td style="white-space: nowrap;">
                            <button class="btn-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($asset), ENT_QUOTES, 'UTF-8'); ?>)" title="Editar" style="width: 32px; height: 32px; padding: 0; background: #3b82f6; border: none; border-radius: 8px; color: white; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; margin-right: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 0.2s;">
                                <i class="bi bi-pencil-square" style="margin: 0;"></i>
                            </button>
                            <button onclick="confirmDeleteAsset(<?php echo $asset['act_id']; ?>, '<?php echo $asset['item_type'] ?? 'activo'; ?>')" title="Eliminar" style="width: 32px; height: 32px; padding: 0; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 0.2s;">
                                <i class="bi bi-trash" style="margin: 0;"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="pagination-footer">
            <div class="pagination-info" id="paginationInfo">
                Mostrando ...
            </div>
            <div class="pagination-controls" id="paginationControls">
                <!-- Javascript will render this -->
            </div>
            <div>
                <select id="itemsPerPageSelect" class="select-filter" style="padding: 6px 12px; font-size: 12.5px;" onchange="updateItemsPerPage()">
                    <option value="8">8 por página</option>
                    <option value="15">15 por página</option>
                    <option value="30">30 por página</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Barra Lateral de Estadísticas -->
    <div class="stats-sidebar" id="statsSidebar">

        <!-- Panel 1: Estado del inventario (Donut Chart) -->


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
        <div class="sidebar-card">
            <h3>Estado del inventario</h3>
            <div class="donut-chart-container">
                <div style="width: 140px; height: 140px; position: relative; margin: 0 auto;">
                    <canvas id="inventoryDonutSidebar"></canvas>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                        <span class="donut-number"><?php echo $totalAssets; ?></span>
                        <span class="donut-label">Activos</span>
                    </div>
                </div>
                <div class="donut-legends" style="margin-top: 16px;">
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #10b981;"></div>
                        <span>Disponibles (<?php echo $stats['Disponible']; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #f59e0b;"></div>
                        <span>En préstamo (<?php echo $stats['Prestado']; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #3b82f6;"></div>
                        <span>En mantenimiento (<?php echo $stats['Mantenimiento']; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #ef4444;"></div>
                        <span>Extraviados (<?php echo $stats['Extraviado']; ?>)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 2: Activos por categoría -->
        <div class="sidebar-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin-bottom: 0;">Activos por categoría</h3>
            </div>
            <div class="category-list">
                <div class="category-item">
                    <div class="category-header">
                        <span>Equipos electrónicos</span>
                        <span><?php echo $categories['Equipos electrónicos']; ?> (<?php echo round($pctCat1); ?>%)</span>
                    </div>
                    <div class="category-bar-bg">
                        <div class="category-bar-fill" style="width: <?php echo $pctCat1; ?>%; background: #2563eb;"></div>
                    </div>
                </div>
                <div class="category-item">
                    <div class="category-header">
                        <span>Mobiliario</span>
                        <span><?php echo $categories['Mobiliario']; ?> (<?php echo round($pctCat2); ?>%)</span>
                    </div>
                    <div class="category-bar-bg">
                        <div class="category-bar-fill" style="width: <?php echo $pctCat2; ?>%; background: #10b981;"></div>
                    </div>
                </div>
                <div class="category-item">
                    <div class="category-header">
                        <span>Herramientas</span>
                        <span><?php echo $categories['Herramientas']; ?> (<?php echo round($pctCat3); ?>%)</span>
                    </div>
                    <div class="category-bar-bg">
                        <div class="category-bar-fill" style="width: <?php echo $pctCat3; ?>%; background: #8b5cf6;"></div>
                    </div>
                </div>
                <div class="category-item">
                    <div class="category-header">
                        <span>Otros</span>
                        <span><?php echo $categories['Otros']; ?> (<?php echo round($pctCat4); ?>%)</span>
                    </div>
                    <div class="category-bar-bg">
                        <div class="category-bar-fill" style="width: <?php echo $pctCat4; ?>%; background: #ef4444;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 3: Ubicación de activos -->
        <div class="sidebar-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin-bottom: 0;">Ubicación de activos</h3>
            </div>
            <div class="location-list">
                <?php if (empty($topLocations)): ?>
                    <p style="font-size: 12px; color: #64748b; text-align: center;">No hay activos asignados a espacios.</p>
                <?php else: ?>
                    <?php foreach ($topLocations as $loc): 
                        $lName = $loc['name'];
                        $iconClass = 'bi-geo-alt-fill';
                        if (strpos(strtolower($lName), 'aula') !== false) {
                            $iconClass = 'bi-people-fill';
                        } elseif (strpos(strtolower($lName), 'taller') !== false || strpos(strtolower($lName), 'lab') !== false) {
                            $iconClass = 'bi-wrench-adjustable';
                        } elseif (strpos(strtolower($lName), 'auditorio') !== false || strpos(strtolower($lName), 'sala') !== false) {
                            $iconClass = 'bi-display';
                        }
                    ?>
                    <div class="location-item">
                        <div class="location-info">
                            <div class="location-icon">
                                <i class="bi <?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="location-details">
                                <span class="location-title"><?php echo htmlspecialchars($loc['name']); ?></span>
                                <span class="location-count"><?php echo htmlspecialchars($loc['edificio']); ?></span>
                            </div>
                        </div>
                        <span style="font-size: 12px; font-weight: 700; color: #475569;"><?php echo $loc['count']; ?> activos</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Columna Derecha: Panel de Filtros (reemplaza stats al abrir) -->
    <div class="filters-sidebar" id="filtersSidebar" style="display: none;">
        <div class="sidebar-card filters-panel-card">
            <div class="drawer-header">
                <h3>Filtros de inventario</h3>
                <button class="close-drawer-btn" onclick="toggleFiltersPanel()">✕</button>
            </div>

            <!-- Estado del Activo y Edificio -->
            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px;">
                <div class="drawer-section">
                    <h4>Estado del activo</h4>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="Disponible">
                            Disponible
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="En uso">
                            En uso
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="Prestado">
                            Prestado
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="Mantenimiento">
                            Mantenimiento
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="Extraviado">
                            Inactivo / baja
                        </label>
                    </div>
                </div>
                <div class="drawer-section">
                    <h4>Edificio</h4>
                    <div class="checkbox-group">
                        <?php foreach($edificiosDB as $ed): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" class="edificio-checkbox" value="<?php echo htmlspecialchars($ed); ?>">
                                <?php echo htmlspecialchars($ed); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tipo de Activo -->
            <div class="drawer-section">
                <h4>Tipo de activo</h4>
                <select id="drawerTypeFilter" class="select-filter" style="width: 100%;">
                    <option value="">Seleccionar tipo</option>
                    <option value="Equipo">Equipo</option>
                    <option value="Mobiliario">Mobiliario</option>
                </select>
            </div>

            <!-- Espacio / Aula / Laboratorio -->
            <div class="drawer-section">
                <h4>Espacio / Aula / Laboratorio</h4>
                <select id="drawerLocationFilter" class="select-filter" style="width: 100%;">
                    <option value="">Seleccionar espacio</option>
                    <?php foreach($allSpaces as $sp): ?>
                        <option value="<?php echo htmlspecialchars($sp['nombre_numero']); ?>"><?php echo htmlspecialchars($sp['nombre_numero'] . ' (' . $sp['edificio'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Disponibilidad -->
            <div class="drawer-section">
                <h4>Disponibilidad</h4>
                <label class="toggle-switch-label">
                    Mostrar solo disponibles
                    <span style="position: relative; display: inline-block;">
                        <input type="checkbox" id="showOnlyAvailable" class="switch-input">
                        <span class="switch-slider"></span>
                    </span>
                </label>
            </div>

            <!-- RFID (Tag) -->
            <div class="drawer-section">
                <h4>RFID (Tag)</h4>
                <input type="text" id="drawerRfidInput" class="form-control" placeholder="Buscar por RFID o tag..." style="font-size: 13px; padding: 8px 12px;">
            </div>

            <!-- Fecha de registro -->
            <div class="drawer-section">
                <h4>Fecha de registro del activo</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div>
                        <label style="font-size: 9px; margin-bottom: 4px;">Desde</label>
                        <input type="date" class="form-control" style="font-size: 12px; padding: 6px 8px;">
                    </div>
                    <div>
                        <label style="font-size: 9px; margin-bottom: 4px;">Hasta</label>
                        <input type="date" class="form-control" style="font-size: 12px; padding: 6px 8px;">
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="drawer-footer">
                <button type="button" class="btn-secondary" onclick="clearDrawerFilters()" style="justify-content: center; padding: 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar filtros
                </button>
                <button type="button" class="btn-primary" onclick="applyFilters();" style="justify-content: center; padding: 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="bi bi-funnel"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>

</div>


    <!-- Sección: Mantenimiento -->
    <div id="section-mantenimiento" style="display: none;"></div>

<!-- Modal de Edición -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; padding: 32px; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <h3 style="margin-top: 0; color: #1e293b; font-weight: 800; font-size: 20px; margin-bottom: 24px;">Editar Activo</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_asset">
            <input type="hidden" name="act_id" id="edit_act_id">
            <input type="hidden" name="item_type" id="edit_item_type" value="">
            <input type="hidden" name="target_tab" id="edit_target_tab" value="inventario">
            
            <div style="display: grid; gap: 16px; grid-template-columns: 1fr 1fr;">
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Responsable (Opcional)</label>
                    <input type="text" name="responsable" id="edit_responsable" class="form-control" placeholder="Ej: DR. JUAN MANUEL...">
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Nivel / Piso (Opcional)</label>
                    <input type="text" name="nivel" id="edit_nivel" class="form-control" placeholder="Ej: Planta Baja">
                </div>
                <div style="position: relative;" id="edit_tipo_container">
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Tipo de Activo / Mobiliario</label>
                    <input type="text" name="tipo" id="edit_tipo" autocomplete="off" class="form-control" placeholder="Escribe o selecciona..." required>
                    <div id="edit_tipo_dropdown" class="custom-dropdown" style="top: 65px;"></div>
                </div>
                <div id="edit_estatus_container">
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Estado</label>
                    <select name="estatus" id="edit_estatus" class="form-control" required>
                        <option value="Disponible">Disponible</option>
                        <option value="Prestado">En préstamo</option>
                        <option value="Mantenimiento">En mantenimiento</option>
                        <option value="Extraviado">Extraviado</option>
                    </select>
                </div>
                <div id="edit_marca_container">
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Marca</label>
                    <input type="text" name="marca" id="edit_marca" class="form-control" required>
                </div>
                <div id="edit_modelo_container">
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Modelo</label>
                    <input type="text" name="modelo" id="edit_modelo" class="form-control" required>
                </div>
                <div id="edit_num_serie_container">
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Número de Serie</label>
                    <input type="text" name="num_serie" id="edit_num_serie" class="form-control" required>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Número de Inventario (Opcional)</label>
                    <input type="text" name="num_inv" id="edit_num_inv" class="form-control">
                </div>
                <div style="grid-column: span 2; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; position: relative;">
                    <label style="font-size: 11px; font-weight: 800; color: #3b82f6;">UID TAG (RFID) - Dejar vacío para desvincular</label>
                    <input type="text" name="tag_id" id="edit_tag_id" autocomplete="off" placeholder="Busca o Escanea el TAG..." class="form-control" style="font-family: 'JetBrains Mono', monospace; color: var(--active-blue); margin-top: 8px; width: 100%; box-sizing: border-box;">
                    <div id="edit_tag_dropdown" class="custom-dropdown" style="top: 75px;"></div>
                </div>
                <div style="grid-column: span 2; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 11px; font-weight: 800; color: #3b82f6;"><i class="bi bi-camera"></i> Foto del Activo (Cloudinary URL o Subida)</label>
                    <div style="display: flex; gap: 12px; align-items: center; margin-top: 8px;">
                        <input type="text" name="imagen_url" id="edit_imagen_url" class="form-control" placeholder="https://res.cloudinary.com/..." style="flex: 1;">
                        <label class="btn-primary" style="background: #10b981; cursor: pointer; white-space: nowrap; font-size: 12px; padding: 10px 16px; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="bi bi-cloud-upload"></i> Subir Foto
                            <input type="file" id="edit_upload_file" accept="image/*" style="display: none;" onchange="uploadToCloudinary(this, 'edit_imagen_url', 'edit_image_preview')">
                        </label>
                    </div>
                    <div id="edit_image_preview_container" style="margin-top: 10px; display: none;">
                        <img id="edit_image_preview" src="" alt="Vista previa" style="max-height: 100px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: cover;">
                    </div>
                </div>
                <div style="grid-column: span 2;">
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Descripción / Especificaciones</label>
                    <textarea name="descripcion" id="edit_descripcion" class="form-control" placeholder="Descripción física o técnica..." style="height: 65px; font-size: 12px;"></textarea>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Edificio</label>
                    <select id="edit_edificio" class="form-control">
                        <option value="">-- Seleccionar --</option>
                        <option value="CIC">CIC</option>
                        <option value="PIDET">PIDET</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Espacio Asignado (Opcional)</label>
                    <select name="esp_asignado" id="edit_esp_asignado" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach($allSpaces as $esp): ?>
                        <option value="<?php echo $esp['esp_id']; ?>" data-edificio="<?php echo htmlspecialchars($esp['edificio']); ?>"><?php echo htmlspecialchars($esp['nombre_numero']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-top: 32px; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn-primary" style="background: #e2e8f0; color: #475569;" onclick="closeEditModal()">CANCELAR</button>
                <button type="submit" class="btn-primary" style="background: #3b82f6;">GUARDAR CAMBIOS</button>
            </div>
        </form>
    </div>
</div>



<script>
    // Pasar los tags disponibles y espacios desde PHP a JS
    const availableTags = <?php echo json_encode($availableTags); ?>;
    const spacesByBuildingJS = <?php echo json_encode($spacesByBuilding ?? []); ?>;
    const allUniqueSpacesJS = <?php echo json_encode(array_values($allUniqueSpaces ?? [])); ?>;
    const allTiposJS = <?php echo json_encode(array_values($tiposDB ?? [])); ?>;

    function setupAutocomplete(inputId, dropdownId, sourceArray) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        if (!input || !dropdown) return;
        
        function renderOptions() {
            const val = input.value.toLowerCase();
            dropdown.innerHTML = '';
            
            // If empty, show all options. Otherwise, filter.
            const matches = val === '' ? sourceArray : sourceArray.filter(item => item.toLowerCase().includes(val));
            
            if (matches.length > 0) {
                // Limitamos a mostrar solo los primeros 15 para evitar lag en listas masivas
                matches.slice(0, 15).forEach(tag => {
                    const item = document.createElement('div');
                    item.className = 'custom-dropdown-item';
                    
                    if (val === '') {
                        item.textContent = tag;
                    } else {
                        // Resaltar coincidencia en azul oscuro
                        const regex = new RegExp(`(${val})`, "gi");
                        item.innerHTML = tag.replace(regex, "<span style='color: #1e40af; font-weight: 900; background: #dbeafe; padding: 0 2px; border-radius: 4px;'>$1</span>");
                    }
                    
                    item.addEventListener('click', function() {
                        input.value = tag;
                        dropdown.style.display = 'none';
                    });
                    dropdown.appendChild(item);
                });
                
                if (val === '') {
                    // Un pequeño mensaje extra para guiar al usuario
                    const hint = document.createElement('div');
                    hint.style = "padding: 8px 16px; font-size: 10px; color: #94a3b8; text-align: center; background: #f8fafc; font-weight: 700;";
                    hint.textContent = "Escribe para filtrar...";
                    dropdown.appendChild(hint);
                }
                
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        }

        input.addEventListener('input', renderOptions);
        input.addEventListener('focus', renderOptions);

        // Cerrar si hacen clic fuera
        document.addEventListener('click', function(e) {
            if (e.target !== input && e.target !== dropdown) {
                dropdown.style.display = 'none';
            }
        });
    }


    function toggleEnrollMode() {
        const mode = document.getElementById('enroll_mode').value;
        document.getElementById('mode-single').style.display = mode === 'single' ? 'block' : 'none';
        document.getElementById('mode-range').style.display = mode === 'range' ? 'block' : 'none';
        document.getElementById('mode-list').style.display = mode === 'list' ? 'block' : 'none';
    }


    let activeCategoryTab = 'inventario';
    let currentPage = 1;
    let itemsPerPage = 8;
    document.addEventListener("DOMContentLoaded", function() {
        const itemsPerPageSelect = document.getElementById("itemsPerPageSelect");
        if (itemsPerPageSelect) itemsPerPage = parseInt(itemsPerPageSelect.value) || 8;
    });

    function filterTypeDropdownOptions(tab) {
        const quickTypeSelect = document.getElementById('quickTypeFilter');
        if (!quickTypeSelect) return;
        const isMobTab = (tab === 'mobiliario');
        const furnitureKeywords = ['silla', 'mesa', 'escritorio', 'pizarrón', 'pizarron', 'mobiliario', 'estante', 'archivero', 'gabinete', 'podium', 'tarima', 'anaquel', 'banco'];

        Array.from(quickTypeSelect.options).forEach((opt, idx) => {
            if (idx === 0) return;
            const valLower = opt.value.toLowerCase();
            const isMobType = furnitureKeywords.some(kw => valLower.includes(kw));
            if (isMobTab) {
                opt.style.display = isMobType ? '' : 'none';
            } else {
                opt.style.display = !isMobType ? '' : 'none';
            }
        });

        const selectedOpt = quickTypeSelect.options[quickTypeSelect.selectedIndex];
        if (selectedOpt && selectedOpt.style.display === 'none') {
            quickTypeSelect.value = '';
        }
    }

    function switchAssetTab(tab) {
        if (tab === 'mantenimiento') tab = 'mobiliario';
        activeCategoryTab = tab;
        
        const btnNewText = document.getElementById('btnNewAssetText');
        const modalTitle = document.getElementById('newAssetModalTitle');
        if (tab === 'mobiliario') {
            if (btnNewText) btnNewText.textContent = "Nuevo mobiliario";
            if (modalTitle) modalTitle.textContent = "Nuevo mobiliario";
        } else {
            if (btnNewText) btnNewText.textContent = "Nuevo activo";
            if (modalTitle) modalTitle.textContent = "Nuevo activo";
        }
        
        document.getElementById('section-inventario').style.display = 'grid';
        const secMant = document.getElementById('section-mantenimiento');
        if (secMant) secMant.style.display = 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        const activeBtn = document.getElementById('tab-' + tab);
        if (activeBtn) activeBtn.classList.add('active');

        const editTarget = document.getElementById('edit_target_tab');
        if (editTarget) editTarget.value = tab;
        const newTarget = document.getElementById('new_target_tab');
        if (newTarget) newTarget.value = tab;

        const headerTitle = document.querySelector('header h1');
        const headerDesc = document.querySelector('header p');
        if (headerTitle && headerDesc) {
            if (tab === 'mobiliario') {
                headerTitle.textContent = 'Mobiliario Institucional';
                headerDesc.textContent = 'Control de sillas, butacas, mesas, escritorios, pizarrones y anaqueles';
            } else {
                headerTitle.textContent = 'Activos y Equipos Electrónicos';
                headerDesc.textContent = 'Control de computadoras, proyectores, impresoras y herramientas tecnológicas';
            }
        }

        filterTypeDropdownOptions(tab);

        if (typeof applyFilters === 'function') {
            applyFilters();
        }
    }

    // Mantener la pestaña activa después de recargar si viene en el GET
    const urlParams = new URLSearchParams(window.location.search);
    let activeTab = urlParams.get('tab') || 'inventario';
    if (activeTab === 'mantenimiento') activeTab = 'mobiliario';
    switchAssetTab(activeTab);


    function toggleBatchMode(mode) {
        document.querySelectorAll('.batch-mode-btn').forEach(l => {
            l.style.background = 'transparent';
            l.style.color = '#64748b';
            l.style.boxShadow = 'none';
        });
        const activeRadio = document.querySelector(`input[name="batch_mode"][value="${mode}"]`);
        if (activeRadio && activeRadio.parentElement) {
            activeRadio.parentElement.style.background = 'white';
            activeRadio.parentElement.style.color = '#1e293b';
            activeRadio.parentElement.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        }

        const batchCont = document.getElementById('batch_fields_container');
        const folioSec = document.getElementById('batch_folio_section');
        const rangeSec = document.getElementById('batch_range_section');
        const singleInvField = document.getElementById('single_num_inv_field');
        const rfidFieldSec = document.getElementById('rfid_field_section');
        
        if (mode === 'single') {
            if (batchCont) batchCont.style.display = 'none';
            if (singleInvField) singleInvField.style.display = 'block';
            if (rfidFieldSec) rfidFieldSec.style.display = 'block';
            const singleInvInput = document.querySelector('#single_num_inv_field input[name="num_inv"]');
            if (singleInvInput) singleInvInput.required = true;
            const newTagInput = document.getElementById('new_tag_id');
            if (newTagInput) newTagInput.required = true;
        } else {
            if (batchCont) batchCont.style.display = 'block';
            if (singleInvField) singleInvField.style.display = 'none';
            if (rfidFieldSec) rfidFieldSec.style.display = 'none';
            const singleInvInput = document.querySelector('#single_num_inv_field input[name="num_inv"]');
            if (singleInvInput) singleInvInput.required = false;
            const newTagInput = document.getElementById('new_tag_id');
            if (newTagInput) { newTagInput.required = false; newTagInput.value = ''; }

            if (mode === 'folio') {
                if (folioSec) folioSec.style.display = 'block';
                if (rangeSec) rangeSec.style.display = 'none';
                const invBase = document.getElementById('batch_inv_base');
                if (invBase) invBase.required = true;
            } else {
                if (folioSec) folioSec.style.display = 'none';
                if (rangeSec) rangeSec.style.display = 'block';
                const invBase = document.getElementById('batch_inv_base');
                if (invBase) invBase.required = false;
            }
            renderBatchTags();
        }
    }

    function renderBatchTags() {
        const mode = document.querySelector('input[name="batch_mode"]:checked').value;
        if (mode === 'single') return;

        const container = document.getElementById('batch_tags_container');
        let qty = 1;

        if (mode === 'folio') {
            qty = parseInt(document.getElementById('batch_quantity').value) || 1;
        } else if (mode === 'range') {
            const start = parseInt(document.getElementById('batch_start').value) || 1;
            const end = parseInt(document.getElementById('batch_end').value) || 1;
            qty = Math.max(1, end - start + 1);
        }

        if (qty > 200) qty = 200;

        let html = '<h4 style="margin: 16px 0 8px 0; color: #1e40af; font-size: 13px; font-weight: 800;"><i class="bi bi-upc-scan"></i> TAGs RFID para el Lote</h4>';
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">';
        
        for (let i = 1; i <= qty; i++) {
            html += `
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Tag RFID #${i}</label>
                    <input type="text" name="batch_tags[]" class="form-control" placeholder="Escanear Tag..." style="font-family: 'JetBrains Mono', monospace; color: #2563eb;">
                </div>
            `;
        }
        
        html += '</div>';
        if (container) container.innerHTML = html;
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        const batchInputs = ['batch_quantity', 'batch_start', 'batch_end'];
        batchInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', renderBatchTags);
                el.addEventListener('change', renderBatchTags);
            }
        });
    });

    function openNewAssetModal() {
        const modal = document.getElementById('newAssetModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            const tipoContainer = document.getElementById('new_tipo_container');
            const marcaContainer = document.getElementById('new_marca_container');
            const modeloContainer = document.getElementById('new_modelo_container');
            const serieContainer = document.getElementById('new_num_serie_container');
            const estatusContainer = document.getElementById('new_estatus_container');
            const tipoSelect = document.querySelector('#newAssetModal input[name="tipo"]');
            const marcaInput = document.querySelector('#newAssetModal input[name="marca"]');
            const modeloInput = document.querySelector('#newAssetModal input[name="modelo"]');
            const serieInput = document.querySelector('#newAssetModal input[name="num_serie"]');
            
            if (activeCategoryTab === 'mobiliario') {
                if(tipoContainer) tipoContainer.style.display = 'none';
                if(marcaContainer) marcaContainer.style.display = 'none';
                if(modeloContainer) modeloContainer.style.display = 'none';
                if(serieContainer) serieContainer.style.display = 'none';
                if(estatusContainer) estatusContainer.style.display = 'none';
                
                if (tipoSelect) { tipoSelect.required = false; tipoSelect.value = 'Mobiliario'; }
                if (marcaInput) { marcaInput.required = false; marcaInput.value = 'N/A'; }
                if (modeloInput) { modeloInput.required = false; modeloInput.value = 'N/A'; }
                if (serieInput) { serieInput.required = false; serieInput.value = 'N/A'; }
            } else {
                if(tipoContainer) tipoContainer.style.display = 'block';
                if(marcaContainer) marcaContainer.style.display = 'block';
                if(modeloContainer) modeloContainer.style.display = 'block';
                if(serieContainer) serieContainer.style.display = 'block';
                if(estatusContainer) estatusContainer.style.display = 'block';
                
                if (tipoSelect) { tipoSelect.required = true; tipoSelect.value = 'Laptop'; }
                if (marcaInput) { marcaInput.required = true; marcaInput.value = ''; }
                if (modeloInput) { modeloInput.required = true; modeloInput.value = ''; }
                if (serieInput) { serieInput.required = true; serieInput.value = ''; }
            }
            toggleBatchMode('single');
        }
    }

    // Funciones del Modal de Edición
    function openEditModal(asset) {
        document.getElementById('edit_act_id').value = asset.act_id;
        document.getElementById('edit_item_type').value = asset.item_type || 'activo';
        document.getElementById('edit_tipo').value = asset.tipo;
        document.getElementById('edit_marca').value = asset.marca;
        document.getElementById('edit_modelo').value = asset.modelo;
        document.getElementById('edit_num_serie').value = asset.num_serie;
        document.getElementById('edit_num_inv').value = asset.num_inv;
        document.getElementById('edit_tag_id').value = asset.tag_id || '';
        document.getElementById('edit_estatus').value = asset.estatus || 'Disponible';
        document.getElementById('edit_edificio').value = asset.edificio || '';
        
        const editResp = document.getElementById('edit_responsable');
        if (editResp) editResp.value = asset.responsable || '';
        const editNivel = document.getElementById('edit_nivel');
        if (editNivel) editNivel.value = asset.nivel || '';

        const editDesc = document.getElementById('edit_descripcion');
        if (editDesc) editDesc.value = asset.descripcion || asset.software_info || '';
        
        const tipoContainer = document.getElementById('edit_tipo_container');
        const estatusContainer = document.getElementById('edit_estatus_container');
        const marcaContainer = document.getElementById('edit_marca_container');
        const modeloContainer = document.getElementById('edit_modelo_container');
        const serieContainer = document.getElementById('edit_num_serie_container');
        
        if (activeCategoryTab === 'mobiliario') {
            if(tipoContainer) tipoContainer.style.display = 'none';
            if(estatusContainer) estatusContainer.style.display = 'none';
            if(marcaContainer) marcaContainer.style.display = 'none';
            if(modeloContainer) modeloContainer.style.display = 'none';
            if(serieContainer) serieContainer.style.display = 'none';
            
            document.getElementById('edit_tipo').required = false;
            document.getElementById('edit_marca').required = false;
            document.getElementById('edit_modelo').required = false;
            document.getElementById('edit_num_serie').required = false;
        } else {
            if(tipoContainer) tipoContainer.style.display = 'block';
            if(estatusContainer) estatusContainer.style.display = 'block';
            if(marcaContainer) marcaContainer.style.display = 'block';
            if(modeloContainer) modeloContainer.style.display = 'block';
            if(serieContainer) serieContainer.style.display = 'block';
            
            document.getElementById('edit_tipo').required = true;
            document.getElementById('edit_marca').required = true;
            document.getElementById('edit_modelo').required = true;
            document.getElementById('edit_num_serie').required = true;
        }

        // Disparar change en el edificio para poblar los espacios correctamente
        const edSelect = document.getElementById('edit_edificio');
        const spSelect = document.getElementById('edit_esp_asignado');
        const event = new Event('change');
        edSelect.dispatchEvent(event);

        document.getElementById('edit_esp_asignado').value = asset.esp_asignado || '';
        
        const editImgInput = document.getElementById('edit_imagen_url');
        const editImgPreview = document.getElementById('edit_image_preview');
        const editImgCont = document.getElementById('edit_image_preview_container');
        if (editImgInput) editImgInput.value = asset.imagen_url || '';
        if (editImgPreview && editImgCont) {
            if (asset.imagen_url) {
                editImgPreview.src = asset.imagen_url;
                editImgCont.style.display = 'block';
            } else {
                editImgPreview.src = '';
                editImgCont.style.display = 'none';
            }
        }

        document.getElementById('editModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Configuración Cloudinary JS
    const CLOUDINARY_CLOUD_NAME = "<?php echo htmlspecialchars($cloudinaryCloudName, ENT_QUOTES, 'UTF-8'); ?>";
    const CLOUDINARY_UPLOAD_PRESET = "<?php echo htmlspecialchars($cloudinaryUploadPreset, ENT_QUOTES, 'UTF-8'); ?>";

    async function uploadToCloudinary(fileInput, targetInputId, previewImgId) {
        if (!fileInput.files || fileInput.files.length === 0) return;
        const file = fileInput.files[0];
        
        const targetInput = document.getElementById(targetInputId);
        const previewImg = document.getElementById(previewImgId);
        const previewContainer = previewImg ? previewImg.parentElement : null;
        
        if (!CLOUDINARY_CLOUD_NAME || CLOUDINARY_CLOUD_NAME === 'tu_cloud_name') {
            Swal.fire({
                icon: 'warning',
                title: 'Falta configurar Cloudinary',
                text: 'Por favor, configura CLOUDINARY_CLOUD_NAME en el archivo backend/.env antes de subir fotos.'
            });
            return;
        }

        Swal.fire({
            title: 'Subiendo imagen a Cloudinary...',
            text: 'Por favor espera unos segundos mientras se optimiza y aloja la imagen',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('file', file);
        formData.append('upload_preset', CLOUDINARY_UPLOAD_PRESET);

        try {
            const response = await fetch(`https://api.cloudinary.com/v1_1/${CLOUDINARY_CLOUD_NAME}/image/upload`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.error?.message || 'Error al conectar con la API de Cloudinary');
            }

            const data = await response.json();
            const secureUrl = data.secure_url;

            if (targetInput) targetInput.value = secureUrl;
            if (previewImg && previewContainer) {
                previewImg.src = secureUrl;
                previewContainer.style.display = 'block';
            }

            Swal.fire({
                icon: 'success',
                title: '¡Imagen Subida con Éxito!',
                text: 'La imagen ha sido optimizada y alojada en Cloudinary CDN.',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Error Cloudinary:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de Subida',
                text: error.message || 'No se pudo subir la imagen. Verifica tu conexión o que el Preset esté en modo Unsigned.'
            });
        } finally {
            fileInput.value = '';
        }
    }

    function viewAssetImage(url, title, numInv) {
        Swal.fire({
            title: title,
            text: 'Nº Inventario: ' + (numInv || 'Sin asignar'),
            imageUrl: url,
            imageAlt: title,
            imageMaxHeight: 500,
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                popup: 'rounded-2xl shadow-2xl border border-slate-200'
            }
        });
    }

    // Funciones SweetAlert2
    function confirmDeleteAsset(id, type) {
        Swal.fire({
            title: '¿Eliminar activo/mobiliario?',
            text: 'Esta acción dará de baja el equipo o mobiliario permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `inventario.php?delete_id=${id}&item_type=${type}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if(typeof $ !== 'undefined') {
            $('#edit_esp_asignado').select2({ width: '100%' });
            $('#new_esp_asignado').select2({ width: '100%' });
        }
        setupAutocomplete('new_tag_id', 'new_tag_dropdown', availableTags);
        setupAutocomplete('edit_tag_id', 'edit_tag_dropdown', availableTags);
        setupAutocomplete('new_tipo_input', 'new_tipo_dropdown', allTiposJS);
        setupAutocomplete('edit_tipo', 'edit_tipo_dropdown', allTiposJS);

        // SweetAlert2 URL Handler
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const action = urlParams.get('success');
            let msg = 'Operación realizada correctamente.';
            let title = '¡Éxito!';
            let icon = 'success';
            
            if (action === 'created') msg = 'El activo se ha registrado correctamente en el inventario.';
            if (action === 'edited') msg = 'El activo ha sido actualizado con éxito.';
            if (action === 'deleted') { title = 'Eliminado'; msg = 'El activo fue dado de baja.'; icon = 'info'; }
            if (action === 'batch_created') {
                const qty = urlParams.get('qty') || 'múltiples';
                title = '¡Lote Registrado con Éxito!';
                msg = 'Se han generado ' + qty + ' activos en lote/rango heredando todas las especificaciones y fotografía.';
            }
            
            Swal.fire({ icon: icon, title: title, text: msg, timer: 3500, showConfirmButton: false });
            
            // Limpiar la URL de los parámetros de éxito para no repetir la alerta
            const url = new URL(window.location);
            url.searchParams.delete('success');
            url.searchParams.delete('qty');
            window.history.replaceState({}, document.title, url);
        }
        if (urlParams.has('error')) {
            let msg = 'Error: ' + urlParams.get('error');
            Swal.fire({ icon: 'error', title: 'Oops...', text: msg });
            const url = new URL(window.location);
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url);
        }
        if (!urlParams.has('filtro')) {
            if (typeof clearAllFilters === 'function') {
                clearAllFilters();
            } else {
                applyFilters();
            }
        } else {
            applyFilters();
        }
    });
    
    // Search inventory & Filters Logic
    
    function updateItemsPerPage() {
        const select = document.getElementById("itemsPerPageSelect");
        itemsPerPage = parseInt(select.value) || 8;
        currentPage = 1;
        applyFilters();
    }

    function goToPage(page) {
        currentPage = page;
        applyFilters();
    }

    function clearAllFilters() {
        try {
            const searchInventory = document.getElementById("searchInventory");
            const quickTypeFilter = document.getElementById("quickTypeFilter");
            const statusFilter = document.getElementById("statusFilter");
            const quickLocationFilter = document.getElementById("quickLocationFilter");
            const quickSpaceFilter = document.getElementById("quickSpaceFilter");
            const drawerTypeFilter = document.getElementById("drawerTypeFilter");
            const drawerLocationFilter = document.getElementById("drawerLocationFilter");
            const drawerRfidInput = document.getElementById("drawerRfidInput");
            const showOnlyAvailable = document.getElementById("showOnlyAvailable");

            if(searchInventory) searchInventory.value = "";
            if(quickTypeFilter) quickTypeFilter.value = "";
            if(statusFilter) statusFilter.value = "";
            if(quickLocationFilter) quickLocationFilter.value = "";
            if(quickSpaceFilter) quickSpaceFilter.value = "";
            if(drawerTypeFilter) drawerTypeFilter.value = "";
            if(drawerLocationFilter) drawerLocationFilter.value = "";
            if(drawerRfidInput) drawerRfidInput.value = "";
            if(showOnlyAvailable) showOnlyAvailable.checked = false;
            
            document.querySelectorAll('.status-checkbox, .edificio-checkbox').forEach(cb => cb.checked = false);
            
            currentPage = 1;
            
            if (typeof updateSpaceFilter === 'function') {
                updateSpaceFilter();
            } else {
                applyFilters();
            }
        } catch (e) {
            console.error("Error en clearAllFilters:", e);
            alert("Error al limpiar filtros: " + e.message);
        }
    }

    function renderPaginationControls(totalPages, totalItems, startIndex) {
        const controls = document.getElementById('paginationControls');
        const info = document.getElementById('paginationInfo');
        
        if (info) {
            if (totalItems === 0) {
                info.innerHTML = "No hay resultados";
            } else {
                const end = Math.min(startIndex + itemsPerPage, totalItems);
                info.innerHTML = `Mostrando ${startIndex + 1}-${end} de ${totalItems}`;
            }
        }
        
        if (!controls) return;
        let html = '';
        html += `<button class="pagination-btn" onclick="goToPage(${Math.max(1, currentPage - 1)})" ${currentPage === 1 ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}><i class="bi bi-chevron-left"></i></button>`;
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                // Avoid multiple ellipsis
                if (!html.endsWith('...</span>')) {
                    html += `<span style="color: #94a3b8; font-size: 13px; margin: 0 4px;">...</span>`;
                }
            }
        }
        
        html += `<button class="pagination-btn" onclick="goToPage(${Math.min(totalPages, currentPage + 1)})" ${currentPage === totalPages ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}><i class="bi bi-chevron-right"></i></button>`;
        controls.innerHTML = html;
    }

    function updateSpaceFilter() {
        try {
            const locFilter = document.getElementById('quickLocationFilter');
            const edVal = locFilter ? locFilter.value : '';
            const spaceSelect = document.getElementById('quickSpaceFilter');
            if(!spaceSelect) {
                currentPage = 1;
                applyFilters();
                return;
            }
            
            spaceSelect.innerHTML = '<option value="">Espacio</option>';
            
            let spacesToShow = [];
            if (edVal && spacesByBuildingJS[edVal]) {
                spacesToShow = spacesByBuildingJS[edVal];
            } else if (!edVal) {
                spacesToShow = Object.values(allUniqueSpacesJS);
            }
            
            spacesToShow.sort();
            spacesToShow.forEach(sp => {
                const opt = document.createElement('option');
                opt.value = sp;
                opt.textContent = sp;
                spaceSelect.appendChild(opt);
            });
            
            currentPage = 1;
            applyFilters();
        } catch (e) {
            console.error("Error en updateSpaceFilter:", e);
            alert("Error en espacio: " + e.message);
        }
    }

    function applyFilters() {
        try {
            const searchInventory = document.getElementById("searchInventory");
            const quickTypeFilter = document.getElementById("quickTypeFilter");
            const statusFilter = document.getElementById("statusFilter");
            const quickLocationFilter = document.getElementById("quickLocationFilter");
            const drawerTypeFilter = document.getElementById("drawerTypeFilter");
            const drawerLocationFilter = document.getElementById("drawerLocationFilter");
            const drawerRfidInput = document.getElementById("drawerRfidInput");
            const showOnlyAvailable = document.getElementById("showOnlyAvailable");
            const quickSpaceFilter = document.getElementById('quickSpaceFilter');

            const searchVal = searchInventory ? searchInventory.value.toLowerCase() : '';
            
            const statusBoxes = document.querySelectorAll('.status-checkbox:checked');
            const selectedStatuses = Array.from(statusBoxes).map(cb => cb.value);

            const edificioBoxes = document.querySelectorAll('.edificio-checkbox:checked');
            const selectedEdificios = Array.from(edificioBoxes).map(cb => cb.value);

            const typeVal = quickTypeFilter ? quickTypeFilter.value : '';
            const drawerTypeVal = drawerTypeFilter ? drawerTypeFilter.value : '';
            const statusVal = statusFilter ? statusFilter.value : '';
            
            const edifVal = quickLocationFilter ? quickLocationFilter.value : '';
            const espVal = quickSpaceFilter ? quickSpaceFilter.value : '';
            const locValDrawer = drawerLocationFilter ? drawerLocationFilter.value : '';
            
            const rfidVal = drawerRfidInput ? drawerRfidInput.value.toLowerCase() : '';
            const onlyAvail = showOnlyAvailable ? showOnlyAvailable.checked : false;

            const matchingRows = [];

            document.querySelectorAll("#inventoryTable tbody tr").forEach(row => {
                const text = (row.textContent || row.innerText || '').toLowerCase();
                const rowStatus = row.getAttribute('data-status') || '';
                const rowTipoCat = row.getAttribute('data-tipo-cat') || '';
                const rowTipoExacto = row.getAttribute('data-tipo') || '';
                const rowLoc = row.getAttribute('data-ubicacion') || '';
                const rowEdificio = row.getAttribute('data-edificio') || '';
                
                const matchesText = !searchVal || text.includes(searchVal);
                
                const matchesExactType = !typeVal || rowTipoExacto === typeVal;
                const targetCat = (activeCategoryTab === 'mobiliario') ? 'Mobiliario' : 'Equipo';
                const matchesCatType = (!drawerTypeVal || rowTipoCat === drawerTypeVal) && (rowTipoCat === targetCat);
                const matchesType = matchesExactType && matchesCatType;

                let matchesStatus = true;
                if (statusVal) {
                    if (statusVal === rowStatus) matchesStatus = true;
                    else if (statusVal === 'Disponible' && rowStatus === 'Disponible') matchesStatus = true;
                    else if (statusVal === 'Prestado' && (rowStatus === 'Prestado' || rowStatus === 'En préstamo' || rowStatus === 'En uso')) matchesStatus = true;
                    else if (statusVal === 'Mantenimiento' && (rowStatus === 'Mantenimiento' || rowStatus === 'En mantenimiento')) matchesStatus = true;
                    else if (statusVal === 'Extraviado' && (rowStatus === 'Extraviado' || rowStatus === 'Inactivo' || rowStatus === 'Baja')) matchesStatus = true;
                    else matchesStatus = false;
                } else if (selectedStatuses.length > 0) {
                    matchesStatus = selectedStatuses.some(sel => {
                        if (sel === 'Disponible' && rowStatus === 'Disponible') return true;
                        if (sel === 'En uso' && (rowStatus === 'En uso' || rowStatus === 'Prestado' || rowStatus === 'En préstamo')) return true;
                        if (sel === 'Prestado' && (rowStatus === 'Prestado' || rowStatus === 'En préstamo')) return true;
                        if (sel === 'Mantenimiento' && (rowStatus === 'Mantenimiento' || rowStatus === 'En mantenimiento')) return true;
                        if (sel === 'Extraviado' && (rowStatus === 'Extraviado' || rowStatus === 'Inactivo' || rowStatus === 'Baja')) return true;
                        return false;
                    });
                }

                const matchesEdificioTop = !edifVal || rowEdificio === edifVal;
                const matchesEdificioDrawer = selectedEdificios.length === 0 || selectedEdificios.includes(rowEdificio);
                const matchesEdificio = matchesEdificioTop && matchesEdificioDrawer;
                
                const matchesLocTop = !espVal || rowLoc === espVal;
                const matchesLocDrawer = !locValDrawer || rowLoc === locValDrawer;
                const matchesLoc = matchesLocTop && matchesLocDrawer;
                
                const matchesRfid = !rfidVal || text.includes(rfidVal);
                const matchesAvail = !onlyAvail || rowStatus === 'Disponible';

                if (matchesText && matchesType && matchesStatus && matchesEdificio && matchesLoc && matchesRfid && matchesAvail) {
                    matchingRows.push(row);
                } else {
                    row.style.display = "none";
                }
            });

            const totalItems = matchingRows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            
            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;

            matchingRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });

            renderPaginationControls(totalPages, totalItems, startIndex);
        } catch (e) {
            console.error("Error en applyFilters:", e);
            alert("Error al aplicar filtros: " + e.message);
        }
    }

    const allFilterElements = [
        document.getElementById("searchInventory"), 
        document.getElementById("quickTypeFilter"), 
        document.getElementById("statusFilter"), 
        document.getElementById("quickLocationFilter"), 
        document.getElementById('quickSpaceFilter'),
        document.getElementById("drawerTypeFilter"), 
        document.getElementById("drawerLocationFilter"), 
        document.getElementById("drawerRfidInput"), 
        document.getElementById("showOnlyAvailable")
    ];

    allFilterElements.forEach(el => {
        if (el) {
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        }
    });

    document.querySelectorAll('.status-checkbox, .edificio-checkbox').forEach(cb => {
        cb.addEventListener('change', applyFilters);
    });

    // Iniciar filtrado en tiempo real al cargar la página
    applyFilters();

    let _filtersPanelOpen = false;
    function toggleFiltersPanel() {
        const stats = document.getElementById("statsSidebar");
        const filters = document.getElementById("filtersSidebar");
        const btn = document.getElementById("filtersBtn");
        
        console.log("ToggleFiltersPanel ejecutado. Estado actual:", _filtersPanelOpen);
        
        if (!filters || !stats) {
            console.error("Paneles de filtros o stats no encontrados en el DOM.");
            return;
        }

        _filtersPanelOpen = !_filtersPanelOpen;
        
        if (_filtersPanelOpen) {
            // Mostrar filtros, ocultar stats
            stats.style.display = 'none';
            filters.style.display = 'flex';
            if (btn) btn.classList.add('filters-active');
        } else {
            // Ocultar filtros, mostrar stats
            filters.style.display = 'none';
            stats.style.display = 'flex';
            if (btn) btn.classList.remove('filters-active');
        }
    }
    
    // Asegurar el enlace del evento por si falla el atributo onclick
    document.addEventListener("DOMContentLoaded", () => {
        const fBtn = document.getElementById("filtersBtn");
        if(fBtn) {
            fBtn.onclick = function(e) {
                e.preventDefault();
                toggleFiltersPanel();
            };
        }
    });

    // Mover el panel de notificaciones del header oculto al bell-btn personalizado
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof $ !== 'undefined') {
            $('#edit_esp_asignado').select2({ width: '100%' });
            $('#new_esp_asignado').select2({ width: '100%' });
        }
        // Pre-cargar badge si hay mantenimientos
        const mainBadge = document.getElementById('mainSidebarMantBadge');
        const mainNotifPanel = document.getElementById("notifPanel");
        const invBellBtn = document.getElementById("invCustomBellBtn");
        const mainBadgeNotif = document.getElementById("notifBadge");
        
        if (mainNotifPanel && invBellBtn) {
            // Reposicionar estilos del panel
            mainNotifPanel.style.top = '45px';
            mainNotifPanel.style.right = '0';
            invBellBtn.appendChild(mainNotifPanel);
            
            invBellBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevenir que el listener global de header.php lo cierre inmediatamente
                if(e.target.closest('.notif-list')) return; 
                mainNotifPanel.classList.toggle('show');
            });
            
            document.addEventListener('click', function(e) {
                if (!invBellBtn.contains(e.target)) {
                    mainNotifPanel.classList.remove('show');
                }
            });
        }
        
        if (mainBadge && invBellBtn) {
            invBellBtn.appendChild(mainBadge);
        }
    });

    function clearDrawerFilters() {
        clearAllFilters();
    }

    function exportToCSV() {
        let csv = [];
        const rows = document.querySelectorAll("#inventoryTable tr");
        for (let i = 0; i < rows.length; i++) {
            if (rows[i].style.display === "none") continue;
            let row = [], cols = rows[i].querySelectorAll("td, th");
            for (let j = 0; j < cols.length - 1; j++) { // Skip action col
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                row.push('"' + data.replace(/"/g, '""') + '"');
            }
            csv.push(row.join(","));
        }
        let csvString = "\uFEFF" + csv.join("\n"); // UTF-8 BOM
        let downloadLink = document.createElement("a");
        let blob = new Blob([csvString], { type: "text/csv;charset=utf-8;" });
        let url = URL.createObjectURL(blob);
        downloadLink.href = url;
        downloadLink.download = "inventario_activos.csv";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }




    // Space filtering based on Building in Modals
    function setupSpaceFilter(edificioSelectId, spaceSelectId) {
        const edSelect = document.getElementById(edificioSelectId);
        const spSelect = document.getElementById(spaceSelectId);
        if(!edSelect || !spSelect) return;
        const allOptions = Array.from(spSelect.options);

        edSelect.addEventListener('change', function() {
            const ed = this.value;
            spSelect.innerHTML = '<option value="">-- Seleccionar Espacio --</option>';
            allOptions.forEach(opt => {
                const optEd = opt.dataset.edificio;
                if(!ed || !optEd || optEd === ed) {
                    spSelect.appendChild(opt);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if(typeof $ !== 'undefined') {
            $('#edit_esp_asignado').select2({ width: '100%' });
            $('#new_esp_asignado').select2({ width: '100%' });
        }
        setupSpaceFilter('new_edificio', 'new_esp_asignado');
        setupSpaceFilter('edit_edificio', 'edit_esp_asignado');
    });
</script>


<!-- Modal de Nuevo Activo Premium -->
<div id="newAssetModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header">
            <h3 id="newAssetModalTitle">Nuevo activo</h3>
            <button type="button" onclick="document.getElementById('newAssetModal').style.display='none'; document.body.style.overflow='';">✕</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="new_asset">
            <input type="hidden" name="item_type" id="new_item_type" value="">
            <input type="hidden" name="target_tab" id="new_target_tab" value="inventario">

            <!-- Selector de Modo de Registro Dual -->
            <div style="background: #f1f5f9; padding: 6px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 6px;">
                <label class="batch-mode-btn active" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; background: white; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <input type="radio" name="batch_mode" value="single" checked style="display: none;" onchange="toggleBatchMode(this.value)">
                    <i class="bi bi-file-earmark-plus"></i> 1 Activo (Individual)
                </label>
                <label class="batch-mode-btn" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; color: #64748b;">
                    <input type="radio" name="batch_mode" value="folio" style="display: none;" onchange="toggleBatchMode(this.value)">
                    <i class="bi bi-stack"></i> Lote: Mismo Folio (Ej: 18755)
                </label>
                <label class="batch-mode-btn" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; color: #64748b;">
                    <input type="radio" name="batch_mode" value="range" style="display: none;" onchange="toggleBatchMode(this.value)">
                    <i class="bi bi-123"></i> Lote: Rango Correlativo
                </label>
            </div>

            <!-- Campos adicionales para Registro en Lote (ocultos en modo individual) -->
            <div id="batch_fields_container" style="display: none; background: #eff6ff; border: 1.5px solid #3b82f6; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div id="batch_folio_section">
                    <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 13px; font-weight: 800;"><i class="bi bi-info-circle-fill"></i> Registro Múltiple con el Mismo No. de Inventario</h4>
                    <p style="margin: 0 0 12px 0; font-size: 11.5px; color: #3b82f6;">Se crearán varias copias idénticas en base de datos con el mismo folio (Ej: Butacas del Auditorio bajo el folio `18755`).</p>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #1e3a8a;">No. de Inventario Patrimonial Base</label>
                            <input type="text" name="batch_inv_base" id="batch_inv_base" placeholder="Ej: 18755" class="form-control" style="background: white;">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #1e3a8a;">Cantidad de Activos</label>
                            <input type="number" name="batch_quantity" id="batch_quantity" min="1" max="200" value="10" class="form-control" style="background: white; font-weight: 800;">
                        </div>
                    </div>
                </div>

                <div id="batch_range_section" style="display: none;">
                    <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 13px; font-weight: 800;"><i class="bi bi-info-circle-fill"></i> Registro por Rango Numérico Correlativo</h4>
                    <p style="margin: 0 0 12px 0; font-size: 11.5px; color: #3b82f6;">El sistema generará automáticamente números consecutivos en el rango especificado.</p>
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #1e3a8a;">Prefijo (Opcional)</label>
                            <input type="text" name="batch_prefix" id="batch_prefix" placeholder="Ej: INV- o UTEQ-" class="form-control" style="background: white;">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #1e3a8a;">Desde (No.)</label>
                            <input type="number" name="batch_start" id="batch_start" min="1" value="18755" class="form-control" style="background: white; font-weight: 800;">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #1e3a8a;">Hasta (No.)</label>
                            <input type="number" name="batch_end" id="batch_end" min="1" value="18780" class="form-control" style="background: white; font-weight: 800;">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #1e3a8a;">Dígitos Relleno</label>
                            <input type="number" name="batch_digits" id="batch_digits" min="0" max="6" value="0" placeholder="Ej: 4 para 0001" class="form-control" style="background: white;">
                        </div>
                    </div>
                </div>
                
                <div id="batch_tags_container"></div>
            </div>

            <!-- Sección 1: Información General -->
            <div class="modal-section-title">
                <i class="bi bi-box-seam-fill"></i> Información general
            </div>
            <div class="modal-grid">
                <div>
                    <label>Responsable (Opcional)</label>
                    <input type="text" name="responsable" placeholder="Ej: DR. JUAN MANUEL..." class="form-control">
                </div>
                <div>
                    <label>Nivel / Piso (Opcional)</label>
                    <input type="text" name="nivel" placeholder="Ej: Planta Baja" class="form-control">
                </div>
                <div style="position: relative;" id="new_tipo_container">
                    <label>Tipo de activo / Mobiliario</label>
                    <input type="text" name="tipo" id="new_tipo_input" autocomplete="off" class="form-control" placeholder="Escribe o selecciona..." required>
                    <div id="new_tipo_dropdown" class="custom-dropdown" style="top: 65px;"></div>
                </div>
                <div id="new_marca_container">
                    <label>Marca</label>
                    <input type="text" name="marca" placeholder="EPSON, Dell, etc." required class="form-control">
                </div>
                <div id="new_modelo_container">
                    <label>Modelo</label>
                    <input type="text" name="modelo" placeholder="Ej: X49, Latitude" required class="form-control">
                </div>
                <div id="new_num_serie_container">
                    <label>No. de serie</label>
                    <input type="text" name="num_serie" placeholder="Ej: EPX49B123" required class="form-control">
                </div>
                <div id="single_num_inv_field" class="modal-grid full-width" style="grid-column: span 2;">
                    <label>No. de inventario</label>
                    <input type="text" name="num_inv" placeholder="Ej: INV-2026-001" required class="form-control">
                </div>
            </div>

            <!-- Sección 2: RFID y Ubicación -->
            <div class="modal-section-title">
                <i class="bi bi-wifi"></i> RFID y ubicación
            </div>
            <div class="modal-grid">
                <div id="rfid_field_section" style="position: relative;">
                    <label>Tag RFID (Opcional)</label>
                    <input type="text" name="tag_id" id="new_tag_id" autocomplete="off" placeholder="Busca o escanea el TAG..." class="form-control" style="font-family: 'JetBrains Mono', monospace; color: #2563eb;">
                    <div id="new_tag_dropdown" class="custom-dropdown"></div>
                </div>
                <div id="new_estatus_container">
                    <label>Estado</label>
                    <select name="estatus" class="form-control" required>
                        <option value="Disponible" selected>Disponible</option>
                        <option value="Prestado">En préstamo</option>
                        <option value="Mantenimiento">En mantenimiento</option>
                        <option value="Extraviado">Extraviado</option>
                    </select>
                </div>
                <div>
                    <label>Edificio</label>
                    <select id="new_edificio" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="CIC">CIC</option>
                        <option value="PIDET">PIDET</option>
                    </select>
                </div>
                <div>
                    <label>Espacio asignado</label>
                    <select name="esp_asignado" id="new_esp_asignado" class="form-control" required>
                        <option value="">-- Seleccionar Espacio --</option>
                        <?php foreach ($allSpaces as $sp): ?>
                            <option value="<?php echo $sp['esp_id']; ?>" data-edificio="<?php echo htmlspecialchars($sp['edificio']); ?>">
                                <?php echo htmlspecialchars($sp['nombre_numero']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Sección 3: Información Adicional y Foto -->
            <div class="modal-section-title">
                <i class="bi bi-file-earmark-text-fill"></i> Información adicional y foto
            </div>
            <div class="modal-grid full-width" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label>Descripción / Observaciones</label>
                    <textarea name="descripcion" class="form-control" placeholder="Describe el activo o butaca (Ej: Butaca azul con mecanismo...)" style="height: 80px; font-weight: 500; font-size: 13.5px;" maxlength="250" oninput="document.getElementById('charCount').innerText = this.value.length + ' / 250'"></textarea>
                    <small id="charCount" class="text-muted" style="float: right; margin-top: 4px; font-weight: 600;">0 / 250</small>
                </div>
                <div style="background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
                    <label style="font-size: 12px; font-weight: 800; color: #3b82f6;"><i class="bi bi-camera"></i> Foto del Activo (Cloudinary)</label>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                        <input type="text" name="imagen_url" id="new_imagen_url" class="form-control" placeholder="https://res.cloudinary.com/..." style="flex: 1; font-size: 12px;">
                        <label class="btn-primary" style="background: #10b981; cursor: pointer; white-space: nowrap; font-size: 12px; padding: 8px 14px; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="bi bi-cloud-upload"></i> Subir
                            <input type="file" id="new_upload_file" accept="image/*" style="display: none;" onchange="uploadToCloudinary(this, 'new_imagen_url', 'new_image_preview')">
                        </label>
                    </div>
                    <div id="new_image_preview_container" style="margin-top: 8px; display: none; text-align: center;">
                        <img id="new_image_preview" src="" alt="Vista previa" style="max-height: 80px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: cover;">
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                <button type="button" class="btn-secondary" onclick="document.getElementById('newAssetModal').style.display='none'; document.body.style.overflow='';">Cancelar</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-box-seam"></i> Registrar activo
                </button>
            </div>
        </form>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var canvas = document.getElementById('inventoryDonutSidebar');
    if (canvas) {
        var ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Disponibles', 'En préstamo', 'En mantenimiento', 'Extraviados'],
                datasets: [{
                    data: [
                        <?php echo $stats['Disponible']; ?>, 
                        <?php echo $stats['Prestado']; ?>, 
                        <?php echo $stats['Mantenimiento']; ?>, 
                        <?php echo $stats['Extraviado']; ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 12, family: "'Inter', sans-serif" },
                        bodyFont: { size: 12, family: "'Inter', sans-serif" },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: true
                    }
                }
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
