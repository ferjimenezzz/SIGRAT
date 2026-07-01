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

$assetController = new Controllers\AssetController();
$spaceController = new Controllers\SpaceController();
$tagController = new Controllers\TagController();
$batchController = new Controllers\BatchController();

$allSpaces = $spaceController->getAll();

$availableTagsResponse = $tagController->getAvailableTags();
$availableTags = $availableTagsResponse['success'] ? $availableTagsResponse['data'] : [];

// Capturar filtros
$filtro = $_GET['filtro'] ?? null;
$query = "SELECT a.*, e.nombre_numero as espacio_nombre, e.edificio FROM ACTIVO a LEFT JOIN ESPACIO e ON a.esp_asignado = e.esp_id";
if ($filtro === 'alerta') {
    $query .= " WHERE a.estatus IN ('Mantenimiento', 'Extraviado', 'Dañado')";
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

    $t = strtolower($asset['tipo'] ?? '');
    if (strpos($t, 'laptop') !== false || strpos($t, 'computer') !== false || strpos($t, 'computadora') !== false || strpos($t, 'proyector') !== false || strpos($t, 'bocina') !== false || strpos($t, 'monitor') !== false || strpos($t, 'impresora') !== false || strpos($t, 'cámara') !== false || strpos($t, 'pc') !== false || strpos($t, 'tv') !== false || strpos($t, 'pantalla') !== false || strpos($t, 'router') !== false || strpos($t, 'switch') !== false || strpos($t, 'equipo') !== false) {
        $categories['Equipos electrónicos']++;
    } elseif (strpos($t, 'silla') !== false || strpos($t, 'mesa') !== false || strpos($t, 'escritorio') !== false || strpos($t, 'pizarrón') !== false || strpos($t, 'pizarron') !== false || strpos($t, 'mobiliario') !== false || strpos($t, 'estante') !== false || strpos($t, 'archivero') !== false) {
        $categories['Mobiliario']++;
    } elseif (strpos($t, 'herramienta') !== false || strpos($t, 'taladro') !== false || strpos($t, 'multímetro') !== false || strpos($t, 'cautín') !== false || strpos($t, 'osciloscopio') !== false || strpos($t, 'pinzas') !== false || strpos($t, 'kit') !== false) {
        $categories['Herramientas']++;
    } else {
        $categories['Otros']++;
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_asset') {
    $res = $assetController->update($_POST['act_id'], $_POST);
    if (!$res['success']) {
        header("Location: inventario.php?tab=inventario&error=" . urlencode($res['error']));
    } else {
        header("Location: inventario.php?tab=inventario&success=edited");
    }
    exit();
}
// Manejar creación rápida desde la vista
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_asset') {
    $res = $assetController->create($_POST);
    if (!$res['success']) {
        header("Location: inventario.php?tab=inventario&error=" . urlencode($res['error']));
    } else {
        header("Location: inventario.php?tab=inventario&success=created");
    }
    exit();
}


// Manejar eliminación
if (isset($_GET['delete_id'])) {
    $assetController->delete($_GET['delete_id']);
    header("Location: inventario.php?tab=inventario&success=deleted");
    exit();
}

include 'header.php';
// Add SweetAlert2


// ============================================================================
// SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API
// ============================================================================
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
?>

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
        <button onclick="switchAssetTab('inventario')" id="tab-inventario" class="btn-tab active">INVENTARIO</button>
        <button onclick="switchAssetTab('mantenimiento')" id="tab-mantenimiento" class="btn-tab">MANTENIMIENTO</button>
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
        <button class="btn-primary" type="button" onclick="document.getElementById('newAssetModal').style.display='flex'; document.body.style.overflow='hidden';" style="height: 40px; border-radius: 8px; font-weight: 700; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px;">
            <i class="bi bi-plus-lg"></i> Nuevo activo
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

            $edificiosDB = array_unique(array_filter(array_column($assets, 'edificio')));
            sort($edificiosDB);

            $spacesByBuilding = [];
            foreach ($assets as $a) {
                $ed = $a['edificio'];
                $sp = $a['espacio_nombre'];
                if ($ed && $sp) {
                    if (!isset($spacesByBuilding[$ed])) $spacesByBuilding[$ed] = [];
                    if (!in_array($sp, $spacesByBuilding[$ed])) {
                        $spacesByBuilding[$ed][] = $sp;
                    }
                }
            }
        ?>
        <!-- Barra de Filtros Rápidos -->
        <div class="filters-bar" style="display: flex; flex-wrap: nowrap; gap: 10px; align-items: center; overflow-x: auto;">
            <div class="search-input-wrapper" style="flex: 1 1 auto; min-width: 150px;">
                <i class="bi bi-search" style="color: #94a3b8;"></i>
                <input type="text" id="searchInventory" placeholder="Buscar activo, marca, modelo, serie o tag..." style="width: 100%;">
            </div>
            
            <div class="filters-selects-grid" style="display: contents;">
                <select id="quickTypeFilter" class="select-filter" style="flex: 0 1 auto; min-width: 110px;">
                    <option value="">Tipo de activo</option>
                    <?php foreach($tiposDB as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="statusFilter" class="select-filter" style="flex: 0 1 auto; min-width: 100px;">
                    <option value="">Estado</option>
                    <?php foreach($estadosDB as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="quickLocationFilter" class="select-filter" onchange="updateSpaceFilter()" style="flex: 0 1 auto; min-width: 110px;">
                    <option value="">Ubicación</option>
                    <?php foreach($edificiosDB as $ed): ?>
                        <option value="<?php echo htmlspecialchars($ed); ?>"><?php echo htmlspecialchars($ed); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="quickSpaceFilter" class="select-filter" style="flex: 0 1 auto; min-width: 110px;">
                    <option value="">Espacio</option>
                    <?php 
                    $allUniqueSpaces = array_unique(array_filter(array_column($assets, 'espacio_nombre')));
                    sort($allUniqueSpaces);
                    foreach($allUniqueSpaces as $sp): 
                    ?>
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
                    <col style="width: 20%;"><!-- Activo -->
                    <col style="width: 13%;"><!-- Tipo -->
                    <col style="width: 11%;"><!-- Nº Inventario -->
                    <col style="width: 13%;"><!-- Tag RFID -->
                    <col style="width: 15%;"><!-- Ubicación -->
                    <col style="width: 15%;"><!-- Estado -->
                    <col style="width: 13%;"><!-- Acción -->
                </colgroup>
                <thead>
                    <tr>
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
                        $tipoLower = strtolower($asset['tipo'] ?? '');
                        $isMobiliario = (strpos($tipoLower, 'silla') !== false || strpos($tipoLower, 'mesa') !== false || strpos($tipoLower, 'escritorio') !== false || strpos($tipoLower, 'pizarrón') !== false || strpos($tipoLower, 'pizarron') !== false || strpos($tipoLower, 'mobiliario') !== false || strpos($tipoLower, 'estante') !== false || strpos($tipoLower, 'archivero') !== false);
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($asset['estatus']); ?>" data-tipo-cat="<?php echo $isMobiliario ? 'Mobiliario' : 'Equipo'; ?>" data-tipo="<?php echo htmlspecialchars($asset['tipo'] ?? ''); ?>" data-ubicacion="<?php echo htmlspecialchars($asset['espacio_nombre'] ?? ''); ?>" data-edificio="<?php echo htmlspecialchars($asset['edificio'] ?? ''); ?>">
                        <td>
                            <div style="font-weight: 700; color: #0f172a; overflow-wrap: break-word; word-break: break-word;"><?php echo htmlspecialchars($asset['tipo'] . ' ' . $asset['modelo']); ?></div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 500; margin-top: 3px; word-break: break-all; overflow-wrap: anywhere; line-height: 1.5; white-space: normal;">Serie: <?php echo htmlspecialchars($asset['num_serie']); ?></div>
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
                            <button onclick="confirmDeleteAsset(<?php echo $asset['act_id']; ?>)" title="Eliminar" style="width: 32px; height: 32px; padding: 0; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 0.2s;">
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
                            <input type="checkbox" class="status-checkbox" value="Disponible" checked>
                            Disponible
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="En uso" checked>
                            En uso
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="Prestado" checked>
                            Prestado
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="status-checkbox" value="Mantenimiento" checked>
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
                        <label class="checkbox-label">
                            <input type="checkbox" class="edificio-checkbox" value="CIC" checked>
                            CIC
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" class="edificio-checkbox" value="PIDET" checked>
                            PIDET
                        </label>
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
    <div id="section-mantenimiento" style="display: none;">
        <div class="card">
            <h3 style="font-weight: 800; color: #334155; margin-bottom: 24px;">Bitácora de Mantenimiento</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; text-align: left;">
                    <tr>
                        <th style="padding: 16px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Activo</th>
                        <th style="padding: 16px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Tipo</th>
                        <th style="padding: 16px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Fecha</th>
                        <th style="padding: 16px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px; font-size: 14px; font-weight: 700;">Impresora 3D Ender</td>
                        <td style="padding: 16px;"><span style="background: #eff6ff; color: #2563eb; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 800;">PREVENTIVO</span></td>
                        <td style="padding: 16px; font-size: 12px; color: #94a3b8;">12/05/2026</td>
                        <td style="padding: 16px;"><span style="color: #10b981; font-weight: 800; font-size: 12px;">Completado</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Edición -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; padding: 32px; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <h3 style="margin-top: 0; color: #1e293b; font-weight: 800; font-size: 20px; margin-bottom: 24px;">Editar Activo</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_asset">
            <input type="hidden" name="act_id" id="edit_act_id">
            
            <div style="display: grid; gap: 16px; grid-template-columns: 1fr 1fr;">
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Tipo de Equipo</label>
                    <select name="tipo" id="edit_tipo" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="Laptop">Laptop</option>
                        <option value="Monitor">Monitor</option>
                        <option value="Impresora">Impresora</option>
                        <option value="Proyector">Proyector</option>
                        <option value="Bocina">Bocina</option>
                        <option value="Computadora">Computadora</option>
                        <option value="Silla">Silla</option>
                        <option value="Mesa">Mesa</option>
                        <option value="Pizarrón">Pizarrón</option>
                        <option value="Escritorio">Escritorio</option>
                        <option value="Otro">Otro / Herramienta</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Estado</label>
                    <select name="estatus" id="edit_estatus" class="form-control" required>
                        <option value="Disponible">Disponible</option>
                        <option value="Prestado">En préstamo</option>
                        <option value="Mantenimiento">En mantenimiento</option>
                        <option value="Extraviado">Extraviado</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Marca</label>
                    <input type="text" name="marca" id="edit_marca" class="form-control" required>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Modelo</label>
                    <input type="text" name="modelo" id="edit_modelo" class="form-control" required>
                </div>
                <div>
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
        width: 100%;
        max-width: 280px;
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
        padding: 8px 12px;
        font-size: 13px;
        color: #334155;
        outline: none;
        cursor: pointer;
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
        border: 1px solid #e2e8f0;
        overflow-y: auto;
        overflow-x: auto;
        max-height: calc(100vh - 250px);
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .premium-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .premium-table th {
        background: #f8fafc;
        padding: 8px 12px;
        position: sticky;
        top: 0;
        z-index: 10;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
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

<script>
    // Pasar los tags disponibles desde PHP a JS
    const availableTags = <?php echo json_encode($availableTags); ?>;

    function setupAutocomplete(inputId, dropdownId) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        if (!input || !dropdown) return;
        
        input.addEventListener('input', function() {
            const val = this.value.toLowerCase();
            dropdown.innerHTML = '';
            
            if (!val) {
                dropdown.style.display = 'none';
                return;
            }
            
            const matches = availableTags.filter(tag => tag.toLowerCase().includes(val));
            
            if (matches.length > 0) {
                // Limitamos a mostrar solo los primeros 15 para evitar lag en listas masivas
                matches.slice(0, 15).forEach(tag => {
                    const item = document.createElement('div');
                    item.className = 'custom-dropdown-item';
                    
                    // Resaltar coincidencia en azul oscuro
                    const regex = new RegExp(`(${val})`, "gi");
                    item.innerHTML = tag.replace(regex, "<span style='color: #1e40af; font-weight: 900; background: #dbeafe; padding: 0 2px; border-radius: 4px;'>$1</span>");
                    
                    item.addEventListener('click', function() {
                        input.value = tag;
                        dropdown.style.display = 'none';
                    });
                    dropdown.appendChild(item);
                });
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        });

        // Mostrar sugerencias al dar foco si está vacío
        input.addEventListener('focus', function() {
            if(this.value === '' && availableTags.length > 0) {
                dropdown.innerHTML = '';
                availableTags.slice(0, 5).forEach(tag => {
                    const item = document.createElement('div');
                    item.className = 'custom-dropdown-item';
                    item.textContent = tag;
                    item.addEventListener('click', function() {
                        input.value = tag;
                        dropdown.style.display = 'none';
                    });
                    dropdown.appendChild(item);
                });
                // Un pequeño mensaje extra para guiar al usuario
                const hint = document.createElement('div');
                hint.style = "padding: 8px 16px; font-size: 10px; color: #94a3b8; text-align: center; background: #f8fafc; font-weight: 700;";
                hint.textContent = "Teclea para ver más resultados...";
                dropdown.appendChild(hint);
                
                dropdown.style.display = 'block';
            } else {
                input.dispatchEvent(new Event('input'));
            }
        });

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


    function switchAssetTab(tab) {
        document.getElementById('section-inventario').style.display = tab === 'inventario' ? 'grid' : 'none';
        document.getElementById('section-mantenimiento').style.display = tab === 'mantenimiento' ? 'block' : 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
    }

    // Mantener la pestaña activa después de recargar si viene en el GET
    const urlParams = new URLSearchParams(window.location.search);
    let activeTab = urlParams.get('tab') || 'inventario';
    if (urlParams.get('filtro') === 'alerta') activeTab = 'inventario'; // Opcional: forzar pestaña
    switchAssetTab(activeTab);


    // Funciones del Modal de Edición
    function openEditModal(asset) {
        document.getElementById('edit_act_id').value = asset.act_id;
        document.getElementById('edit_tipo').value = asset.tipo;
        document.getElementById('edit_marca').value = asset.marca;
        document.getElementById('edit_modelo').value = asset.modelo;
        document.getElementById('edit_num_serie').value = asset.num_serie;
        document.getElementById('edit_num_inv').value = asset.num_inv;
        document.getElementById('edit_tag_id').value = asset.tag_id || '';
        document.getElementById('edit_estatus').value = asset.estatus || 'Disponible';
        document.getElementById('edit_edificio').value = asset.edificio || '';

        // Disparar change en el edificio para poblar los espacios correctamente
        const edSelect = document.getElementById('edit_edificio');
        const spSelect = document.getElementById('edit_esp_asignado');
        const event = new Event('change');
        edSelect.dispatchEvent(event);

        document.getElementById('edit_esp_asignado').value = asset.esp_asignado || '';
        
        document.getElementById('editModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Funciones SweetAlert2
    function confirmDeleteAsset(id) {
        Swal.fire({
            title: '¿Eliminar activo?',
            text: 'Esta acción dará de baja el equipo o mobiliario permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `inventario.php?delete_id=${id}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupAutocomplete('new_tag_id', 'new_tag_dropdown');
        setupAutocomplete('edit_tag_id', 'edit_tag_dropdown');

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
            
            Swal.fire({ icon: icon, title: title, text: msg, timer: 3000, showConfirmButton: false });
            
            // Limpiar la URL de los parámetros de éxito para no repetir la alerta
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url);
        }
        if (urlParams.has('error')) {
            let msg = 'Error: ' + urlParams.get('error');
            Swal.fire({ icon: 'error', title: 'Oops...', text: msg });
            const url = new URL(window.location);
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url);
        }
        applyFilters();
    });
    
    // Search inventory & Filters Logic
    
    let currentPage = 1;
    let itemsPerPage = 8;

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
        if(searchInventory) searchInventory.value = "";
        if(quickTypeFilter) quickTypeFilter.value = "";
        if(statusFilter) statusFilter.value = "";
        if(quickLocationFilter) quickLocationFilter.value = "";
        if(document.getElementById('quickSpaceFilter')) document.getElementById('quickSpaceFilter').value = "";
        if(drawerTypeFilter) drawerTypeFilter.value = "";
        if(drawerLocationFilter) drawerLocationFilter.value = "";
        if(drawerRfidInput) drawerRfidInput.value = "";
        if(showOnlyAvailable) showOnlyAvailable.checked = false;
        
        document.querySelectorAll('.status-checkbox, .edificio-checkbox').forEach(cb => cb.checked = false);
        
        currentPage = 1;
        applyFilters();
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

    const spacesByBuildingJS = <?php echo json_encode($spacesByBuilding ?? []); ?>;
    const allUniqueSpacesJS = <?php echo json_encode(array_unique(array_filter(array_column($assets ?? [], 'espacio_nombre')))); ?>;
    
    function updateSpaceFilter() {
        const edVal = document.getElementById('quickLocationFilter').value;
        const spaceSelect = document.getElementById('quickSpaceFilter');
        if(!spaceSelect) return;
        
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
    }

    const searchInventory = document.getElementById("searchInventory");
    const quickTypeFilter = document.getElementById("quickTypeFilter");
    const statusFilter = document.getElementById("statusFilter");
    const quickLocationFilter = document.getElementById("quickLocationFilter");
    
    const drawerTypeFilter = document.getElementById("drawerTypeFilter");
    const drawerLocationFilter = document.getElementById("drawerLocationFilter");
    const drawerRfidInput = document.getElementById("drawerRfidInput");
    const showOnlyAvailable = document.getElementById("showOnlyAvailable");

    function applyFilters() {
        const searchVal = searchInventory.value.toLowerCase();
        
        const statusBoxes = document.querySelectorAll('.status-checkbox:checked');
        const selectedStatuses = Array.from(statusBoxes).map(cb => cb.value);

        const edificioBoxes = document.querySelectorAll('.edificio-checkbox:checked');
        const selectedEdificios = Array.from(edificioBoxes).map(cb => cb.value);

        const typeVal = quickTypeFilter.value;
        const drawerTypeVal = drawerTypeFilter.value;
        const statusVal = statusFilter.value;
        
        const edifVal = quickLocationFilter.value;
        const espVal = document.getElementById('quickSpaceFilter') ? document.getElementById('quickSpaceFilter').value : '';
        const locValDrawer = drawerLocationFilter.value;
        
        const rfidVal = drawerRfidInput.value.toLowerCase();
        const onlyAvail = showOnlyAvailable.checked;

        const matchingRows = [];

        document.querySelectorAll("#inventoryTable tbody tr").forEach(row => {
            const text = row.innerText.toLowerCase();
            const rowStatus = row.dataset.status;
            const rowTipoCat = row.dataset.tipoCat; // 'Equipo' or 'Mobiliario'
            const rowTipoExacto = row.dataset.tipo;
            const rowLoc = row.dataset.ubicacion; // Espacio
            const rowEdificio = row.dataset.edificio; // Edificio
            
            const matchesText = text.includes(searchVal);
            
            // quickTypeFilter has exact types, drawerTypeFilter has 'Equipo'/'Mobiliario'
            const matchesExactType = !typeVal || rowTipoExacto === typeVal;
            const matchesCatType = !drawerTypeVal || rowTipoCat === drawerTypeVal;
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

        // Apply pagination
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
    }

    [searchInventory, quickTypeFilter, statusFilter, quickLocationFilter, document.getElementById('quickSpaceFilter')].forEach(el => {
        if(el) {
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        }
    });

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
    document.addEventListener("DOMContentLoaded", () => {
        const mainNotifPanel = document.getElementById("notifPanel");
        const invBellBtn = document.getElementById("invCustomBellBtn");
        const mainBadge = document.getElementById("notifBadge");
        
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
        document.querySelectorAll('.status-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.edificio-checkbox').forEach(cb => cb.checked = false);
        drawerTypeFilter.value = "";
        drawerLocationFilter.value = "";
        drawerRfidInput.value = "";
        showOnlyAvailable.checked = false;
        applyFilters();
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

    function exportToPDF() {
        const title = "Reporte de Inventario de Activos - SIGRAT";
        const headers = ["Activo", "Tipo", "Nº Inventario", "Tag RFID", "Ubicación", "Estado"];
        let rowsHtml = "";

        const rows = document.querySelectorAll("#inventoryTable tbody tr");
        rows.forEach(row => {
            if (row.style.display === "none") return;
            
            const cols = row.querySelectorAll("td");
            if (cols.length < 6) return;
            
            const activeName = cols[0].querySelector("div:first-child")?.innerText || "";
            const activeSerie = cols[0].querySelector("div:nth-child(2)")?.innerText || "";
            const activeCell = `<div><strong>${activeName}</strong></div><div style="font-size: 10px; color: #64748b;">${activeSerie}</div>`;
            
            const tipo = cols[1].innerText.trim();
            const invNum = cols[2].innerText.trim();
            const rfid = cols[3].innerText.trim();
            const location = cols[4].innerText.trim();
            
            const status = cols[5].innerText.trim();
            let badgeClass = "badge-inactivo";
            if (status.toLowerCase().includes("disponible")) {
                badgeClass = "badge-disponible";
            } else if (status.toLowerCase().includes("prestado")) {
                badgeClass = "badge-prestado";
            }
            const statusCell = `<span class="badge ${badgeClass}">${status}</span>`;
            
            rowsHtml += `
                <tr>
                    <td>${activeCell}</td>
                    <td>${tipo}</td>
                    <td><code style="font-family: monospace; font-size:12px;">${invNum}</code></td>
                    <td>${rfid}</td>
                    <td>${location}</td>
                    <td>${statusCell}</td>
                </tr>
            `;
        });

        const headersHtml = headers.map(h => `<th>${h}</th>`).join("");
        
        // Usar iframe oculto para evitar bloqueo de popups del navegador
        let printFrame = document.getElementById('_pdf_print_frame');
        if (printFrame) printFrame.remove();
        printFrame = document.createElement('iframe');
        printFrame.id = '_pdf_print_frame';
        printFrame.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;';
        document.body.appendChild(printFrame);
        const doc = printFrame.contentWindow.document;
        doc.open();
        doc.write(`
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>\${title}</title>
                <style>
                    body {
                        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                        color: #1e293b;
                        margin: 0;
                        padding: 40px;
                        background-color: #ffffff;
                    }
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        border-bottom: 2px solid #2563eb;
                        padding-bottom: 20px;
                        margin-bottom: 30px;
                    }
                    .logo-area h1 {
                        font-size: 28px;
                        font-weight: 800;
                        color: #2563eb;
                        margin: 0;
                        letter-spacing: -1px;
                    }
                    .logo-area p {
                        font-size: 11px;
                        color: #64748b;
                        margin: 4px 0 0 0;
                        font-weight: 600;
                        text-transform: uppercase;
                    }
                    .meta-info {
                        text-align: right;
                        font-size: 13px;
                        color: #475569;
                    }
                    .meta-info h2 {
                        font-size: 18px;
                        font-weight: 700;
                        color: #1e293b;
                        margin: 0 0 6px 0;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }
                    th {
                        background-color: #f8fafc;
                        color: #475569;
                        font-weight: 700;
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        padding: 12px 14px;
                        border: 1px solid #e2e8f0;
                        text-align: left;
                    }
                    td {
                        padding: 12px 14px;
                        font-size: 13px;
                        color: #334155;
                        border: 1px solid #e2e8f0;
                    }
                    tr:nth-child(even) td {
                        background-color: #f8fafc;
                    }
                    .footer {
                        margin-top: 50px;
                        font-size: 11px;
                        color: #94a3b8;
                        text-align: center;
                        border-top: 1px solid #e2e8f0;
                        padding-top: 20px;
                    }
                    .badge {
                        display: inline-block;
                        padding: 4px 8px;
                        border-radius: 6px;
                        font-size: 11px;
                        font-weight: 700;
                    }
                    .badge-disponible { background-color: #dcfce7; color: #15803d; }
                    .badge-prestado { background-color: #fef3c7; color: #d97706; }
                    .badge-inactivo { background-color: #f3f4f6; color: #4b5563; }
                    @media print {
                        body { padding: 0; }
                        .no-print { display: none !important; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="logo-area">
                        <h1>SIGRAT</h1>
                        <p>Control Integral</p>
                    </div>
                    <div class="meta-info">
                        <h2>\${title}</h2>
                        <div>Generado el: \${new Date().toLocaleString()}</div>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>\${headersHtml}</tr>
                    </thead>
                    <tbody>
                        \${rowsHtml}
                    </tbody>
                </table>
                <div class="footer">
                    Este documento es un reporte de inventario generado por el Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT).
                </div>
            </body>
            </html>
        `);
        doc.close();
        // Pequeño delay para que el iframe cargue el contenido antes de imprimir
        setTimeout(() => {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        }, 400);
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
        setupSpaceFilter('new_edificio', 'new_esp_asignado');
        setupSpaceFilter('edit_edificio', 'edit_esp_asignado');
    });
</script>


<!-- Modal de Nuevo Activo Premium -->
<div id="newAssetModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header">
            <h3>Nuevo activo</h3>
            <button type="button" onclick="document.getElementById('newAssetModal').style.display='none'; document.body.style.overflow='';">✕</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="new_asset">

            <!-- Sección 1: Información General -->
            <div class="modal-section-title">
                <i class="bi bi-box-seam-fill"></i> Información general
            </div>
            <div class="modal-grid">
                <div>
                    <label>Tipo de activo</label>
                    <select name="tipo" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="Laptop">Laptop</option>
                        <option value="Monitor">Monitor</option>
                        <option value="Impresora">Impresora</option>
                        <option value="Proyector">Proyector</option>
                        <option value="Bocina">Bocina</option>
                        <option value="Computadora">Computadora</option>
                        <option value="Silla">Silla</option>
                        <option value="Mesa">Mesa</option>
                        <option value="Pizarrón">Pizarrón</option>
                        <option value="Escritorio">Escritorio</option>
                        <option value="Otro">Otro / Herramienta</option>
                    </select>
                </div>
                <div>
                    <label>Marca</label>
                    <input type="text" name="marca" placeholder="EPSON, Dell, etc." required class="form-control">
                </div>
                <div>
                    <label>Modelo</label>
                    <input type="text" name="modelo" placeholder="Ej: X49, Latitude" required class="form-control">
                </div>
                <div>
                    <label>No. de serie</label>
                    <input type="text" name="num_serie" placeholder="Ej: EPX49B123" required class="form-control">
                </div>
                <div class="modal-grid full-width" style="grid-column: span 2;">
                    <label>No. de inventario</label>
                    <input type="text" name="num_inv" placeholder="Ej: INV-2026-001" required class="form-control">
                </div>
            </div>

            <!-- Sección 2: RFID y Ubicación -->
            <div class="modal-section-title">
                <i class="bi bi-wifi"></i> RFID y ubicación
            </div>
            <div class="modal-grid">
                <div style="position: relative;">
                    <label>Tag RFID</label>
                    <input type="text" name="tag_id" id="new_tag_id" autocomplete="off" placeholder="Busca o escanea el TAG..." class="form-control" style="font-family: 'JetBrains Mono', monospace; color: #2563eb;" required>
                    <div id="new_tag_dropdown" class="custom-dropdown"></div>
                </div>
                <div>
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

            <!-- Sección 3: Información Adicional -->
            <div class="modal-section-title">
                <i class="bi bi-file-earmark-text-fill"></i> Información adicional
            </div>
            <div class="modal-grid full-width">
                <div>
                    <label>Descripción</label>
                    <textarea class="form-control" placeholder="Describe el activo..." style="height: 80px; font-weight: 500; font-size: 13.5px;" maxlength="250" oninput="document.getElementById('charCount').innerText = this.value.length + ' / 250'"></textarea>
                    <small id="charCount" class="text-muted" style="float: right; margin-top: 4px; font-weight: 600;">0 / 250</small>
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
