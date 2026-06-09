<?php
/**
 * @file enrolamiento.php
 * @summary Interfaz de enrolamiento masivo de activos RFID en PHP.
 * @description Permite la captura de tags en tiempo real y el registro por lotes.
 */
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
        header("Location: inventario.php?tab=inventario");
    }
    exit();
}
// Manejar creación rápida desde la vista
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_asset') {
    $res = $assetController->create($_POST);
    if (!$res['success']) {
        header("Location: inventario.php?tab=inventario&error=" . urlencode($res['error']));
    } else {
        header("Location: enrolamiento.php?tab=inventario");
    }
    exit();
}



// Manejar eliminación
if (isset($_GET['delete_id'])) {
    $assetController->delete($_GET['delete_id']);
    header("Location: inventario.php?tab=inventario");
    exit();
}

include 'header.php';
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

<!-- Cabecera Premium -->
<div class="premium-page-header">
    <div class="premium-header-left">
        <h1>Inventario</h1>
        <p>Gestiona y controla los activos y mobiliario institucional</p>
    </div>
    <div class="premium-header-right">
        <div class="premium-top-search">
            <i class="bi bi-search"></i>
            <input type="text" id="topSearchInput" placeholder="Buscar activo, serie, tag RFID...">
        </div>
        <button class="bell-btn" onclick="alert('No hay notificaciones nuevas')">
            <i class="bi bi-bell"></i>
            <span class="bell-badge">3</span>
        </button>
        <button class="btn-primary" onclick="document.getElementById('newAssetModal').style.display='flex'">
            <i class="bi bi-plus-lg"></i>
            Nuevo activo
        </button>
    </div>
</div>

<!-- Barra de Pestañas -->
<div class="tabs-row">
    <div class="tabs-container">
        <button onclick="switchAssetTab('inventario')" id="tab-inventario" class="btn-tab active">INVENTARIO</button>
        <button onclick="switchAssetTab('prestamos')" id="tab-prestamos" class="btn-tab">PRÉSTAMOS</button>
        <button onclick="switchAssetTab('mantenimiento')" id="tab-mantenimiento" class="btn-tab">MANTENIMIENTO</button>
    </div>
</div>

<!-- Sección de Inventario Principal -->
<div id="section-inventario" class="inventory-grid">

    <!-- Columna Izquierda: Tabla y Filtros -->
    <div>
        <!-- Barra de Filtros Rápidos -->
        <div class="filters-bar">
            <div class="filters-left">
                <div class="search-input-wrapper">
                    <i class="bi bi-search" style="color: #94a3b8;"></i>
                    <input type="text" id="searchInventory" placeholder="Buscar activo, marca, modelo, serie o tag...">
                </div>
                <select id="quickTypeFilter" class="select-filter">
                    <option value="">Tipo de archivo</option>
                    <option value="Equipo">Equipo</option>
                    <option value="Mobiliario">Mobiliario</option>
                </select>
                <select id="statusFilter" class="select-filter">
                    <option value="">Estado</option>
                    <option value="Disponible">Disponible</option>
                    <option value="Prestado">Prestado</option>
                    <option value="Mantenimiento">Mantenimiento</option>
                    <option value="Extraviado">Extraviado</option>
                </select>
                <select id="quickLocationFilter" class="select-filter">
                    <option value="">Ubicación</option>
                    <?php 
                    $uniqueLocations = array_unique(array_filter(array_map(function($a) { return $a['espacio_nombre']; }, $assets)));
                    foreach($uniqueLocations as $loc): 
                    ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-right">
                <button class="btn-outline" onclick="toggleFiltersDrawer(true)">
                    <i class="bi bi-funnel"></i>
                    Filtros
                </button>
                <button class="btn-outline" onclick="exportToCSV()">
                    <i class="bi bi-download"></i>
                    Exportar
                </button>
                <div class="view-toggles">
                    <button class="view-btn active" title="Vista de Tabla"><i class="bi bi-list-ul"></i></button>
                    <button class="view-btn" title="Vista de Cuadrícula" onclick="alert('Vista de cuadrícula próximamente')"><i class="bi bi-grid-fill"></i></button>
                </div>
            </div>
        </div>

        <!-- Tabla de Inventario -->
        <div class="premium-table-card">
            <table id="inventoryTable" class="premium-table">
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
                    <tr data-status="<?php echo htmlspecialchars($asset['estatus']); ?>" data-tipo-cat="<?php echo $isMobiliario ? 'Mobiliario' : 'Equipo'; ?>" data-ubicacion="<?php echo htmlspecialchars($asset['espacio_nombre'] ?? ''); ?>" data-edificio="<?php echo htmlspecialchars($asset['edificio'] ?? ''); ?>">
                        <td>
                            <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($asset['tipo'] . ' ' . $asset['modelo']); ?></div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 500; margin-top: 2px;">Serie: <?php echo htmlspecialchars($asset['num_serie']); ?></div>
                        </td>
                        <td>
                            <?php if ($isMobiliario): ?>
                                <span class="type-badge mobiliario"><i class="bi bi-tablet-landscape"></i> Mobiliario</span>
                            <?php else: ?>
                                <span class="type-badge equipo"><i class="bi bi-laptop"></i> Equipo</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; color: #475569;">
                            <?php echo htmlspecialchars($asset['num_inv'] ?? ''); ?>
                        </td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; color: #2563eb;">
                            <?php echo htmlspecialchars($asset['tag_id'] ?? 'Sin asignar'); ?>
                        </td>
                        <td style="font-weight: 600; color: #475569;">
                            <?php echo htmlspecialchars($asset['espacio_nombre'] ?? 'Sin asignar'); ?>
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
                                    $badgeColor = '#ef4444';
                                    $badgeBg = '#fef2f2';
                                    break;
                                case 'Extraviado':
                                    $badgeColor = '#6b7280';
                                    $badgeBg = '#f9fafb';
                                    break;
                            }
                            ?>
                            <span class="status-badge" style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>;">
                                <?php echo htmlspecialchars($est); ?>
                            </span>
                        </td>
                        <td>
                            <div style="position: relative; display: inline-block;">
                                <button class="table-action" onclick="toggleRowMenu(event, <?php echo $asset['act_id']; ?>)" style="background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 4px;">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <div id="menu-<?php echo $asset['act_id']; ?>" class="row-action-menu" style="display: none; position: absolute; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); z-index: 10; width: 100px;">
                                    <a href="javascript:void(0)" onclick='openEditModal(<?php echo json_encode($asset); ?>)' style="display: block; padding: 8px 12px; font-size: 12.5px; color: #334155; text-decoration: none; font-weight: 600;">Editar</a>
                                    <a href="?delete_id=<?php echo $asset['act_id']; ?>" onclick="return confirm('¿Eliminar activo?')" style="display: block; padding: 8px 12px; font-size: 12.5px; color: #ef4444; text-decoration: none; font-weight: 600; border-top: 1px solid #f1f5f9;">Eliminar</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="pagination-footer">
            <div class="pagination-info">
                Mostrando 1 a <?php echo min(8, $totalAssets); ?> de <?php echo $totalAssets; ?> activos
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn"><i class="bi bi-chevron-left"></i></button>
                <button class="pagination-btn active">1</button>
                <button class="pagination-btn">2</button>
                <button class="pagination-btn">3</button>
                <span style="color: #94a3b8; font-size: 13px;">...</span>
                <button class="pagination-btn"><?php echo max(1, ceil($totalAssets / 8)); ?></button>
                <button class="pagination-btn"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div>
                <select class="select-filter" style="padding: 6px 12px; font-size: 12.5px;">
                    <option>8 por página</option>
                    <option>15 por página</option>
                    <option>30 por página</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Barra Lateral de Estadísticas -->
    <div class="stats-sidebar">

        <!-- Panel 1: Estado del inventario (Donut Chart) -->
        <div class="sidebar-card">
            <h3>Estado del inventario</h3>
            <div class="donut-chart-container">
                <!-- Gráfico de Donut usando CSS conic-gradient -->
                <div style="width: 120px; height: 120px; border-radius: 50%; background: conic-gradient(#10b981 0% <?php echo $pctDisp; ?>%, #2563eb <?php echo $pctDisp; ?>% <?php echo $pctDisp+$pctPres; ?>%, #ef4444 <?php echo $pctDisp+$pctPres; ?>% <?php echo $pctDisp+$pctPres+$pctMant; ?>%, #6b7280 <?php echo $pctDisp+$pctPres+$pctMant; ?>% 100%); display: flex; align-items: center; justify-content: center; position: relative;">
                    <div style="width: 90px; height: 90px; border-radius: 50%; background: white; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                        <span class="donut-number"><?php echo $totalAssets; ?></span>
                        <span class="donut-label">Activos</span>
                    </div>
                </div>
                <!-- Leyendas del Donut -->
                <div class="donut-legends">
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #10b981;"></div>
                        <span>Disponibles (<?php echo $stats['Disponible']; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #2563eb;"></div>
                        <span>En préstamo (<?php echo $stats['Prestado']; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #ef4444;"></div>
                        <span>En mantenimiento (<?php echo $stats['Mantenimiento']; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #6b7280;"></div>
                        <span>Extraviados (<?php echo $stats['Extraviado']; ?>)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 2: Activos por categoría -->
        <div class="sidebar-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin-bottom: 0;">Activos por categoría</h3>
                <a href="javascript:void(0)" onclick="alert('Detalle de categorías próximamente')" style="font-size: 11px; font-weight: 700; color: #2563eb; text-decoration: none;">Ver detalles</a>
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
                <a href="javascript:void(0)" onclick="alert('Mapa de ubicaciones próximamente')" style="font-size: 11px; font-weight: 700; color: #2563eb; text-decoration: none;">Ver mapa</a>
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

</div>



    <!-- Sección: Préstamos -->
    <div id="section-prestamos" style="display: none; grid-template-columns: 1fr 2fr; gap: 32px;">
        <aside class="card">
            <h3 style="font-weight: 800; color: #334155; margin-bottom: 24px;">Registrar Salida</h3>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Activo (ID/RFID)</label>
                    <input type="text" placeholder="Ej: LPT-001" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px;">
                </div>
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Usuario (Matrícula)</label>
                    <input type="text" placeholder="Ej: 20213045" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px;">
                </div>
                <button class="btn-primary" style="width: 100%; justify-content: center;">REGISTRAR PRÉSTAMO</button>
            </div>
        </aside>
        <main class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #334155; margin-bottom: 24px;">Préstamos Activos</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="padding: 16px; background: #f8fafc; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="font-size: 14px; font-weight: 800;">Laptop Dell Latitude (LPT-44)</p>
                        <p style="font-size: 11px; color: #94a3b8;">Prestado a: Juan Perez • Vence: 16:00</p>
                    </div>
                    <button class="btn-primary" style="background: #10b981; font-size: 9px; padding: 6px 12px;">RETORNAR</button>
                </div>
            </div>
        </main>
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
    <div style="background: white; padding: 32px; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <h3 style="margin-top: 0; color: #1e293b; font-weight: 800; font-size: 20px; margin-bottom: 24px;">Editar Activo</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_asset">
            <input type="hidden" name="act_id" id="edit_act_id">
            
            <div style="display: grid; gap: 16px;">
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Tipo de Equipo</label>
                    <input type="text" name="tipo" id="edit_tipo" class="form-control" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 800; color: #64748b;">Marca</label>
                        <input type="text" name="marca" id="edit_marca" class="form-control" required>
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 800; color: #64748b;">Modelo</label>
                        <input type="text" name="modelo" id="edit_modelo" class="form-control" required>
                    </div>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Número de Serie</label>
                    <input type="text" name="num_serie" id="edit_num_serie" class="form-control" required>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Número de Inventario (Opcional)</label>
                    <input type="text" name="num_inv" id="edit_num_inv" class="form-control">
                </div>
                <div style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; position: relative;">
                    <label style="font-size: 11px; font-weight: 800; color: #3b82f6;">UID TAG (RFID) - Dejar vacío para desvincular</label>
                    <input type="text" name="tag_id" id="edit_tag_id" autocomplete="off" placeholder="Busca o Escanea el TAG..." class="form-control" style="font-family: 'JetBrains Mono', monospace; color: var(--active-blue); margin-top: 8px; width: 100%; box-sizing: border-box;">
                    <div id="edit_tag_dropdown" class="custom-dropdown" style="top: 75px;"></div>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 800; color: #64748b;">Espacio Asignado (Opcional)</label>
                    <select name="esp_asignado" id="edit_esp_asignado" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach($allSpaces as $esp): ?>
                        <option value="<?php echo $esp['esp_id']; ?>"><?php echo htmlspecialchars($esp['nombre_numero'] . ' - ' . $esp['edificio']); ?></option>
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
    /* Ocultar barra superior por defecto */
    .top-bar {
        display: none !important;
    }

    /* Ajustar margen y padding de contenedor principal */
    .main-container {
        background-color: #f8fafc !important;
    }

    /* Cabecera Premium */
    .premium-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
        padding: 20px 28px;
        border-bottom: 1px solid #e2e8f0;
        margin-left: -28px;
        margin-right: -28px;
        margin-top: -24px;
        margin-bottom: 24px;
    }
    .premium-header-left h1 {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .premium-header-left p {
        font-size: 13px;
        color: #64748b;
        margin-top: 2px;
    }
    .premium-header-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .premium-top-search {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        width: 260px;
    }
    .premium-top-search i {
        color: #94a3b8;
    }
    .premium-top-search input {
        background: transparent;
        border: none;
        outline: none;
        width: 100%;
        font-size: 13px;
        font-weight: 500;
    }
    .bell-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        cursor: pointer;
        transition: all 0.2s;
    }
    .bell-btn:hover {
        background: #e2e8f0;
    }
    .bell-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #2563eb;
        color: white;
        font-size: 9px;
        font-weight: 800;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
    }

    /* Pestañas estilizadas */
    .tabs-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
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
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    /* Filtros Rápidos */
    .filters-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        gap: 12px;
    }
    .filters-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        flex-grow: 1;
    }
    .search-input-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 12px;
        width: 280px;
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
    .view-toggles {
        display: flex;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 10px;
        padding: 2px;
    }
    .view-btn {
        border: none;
        background: none;
        padding: 6px 10px;
        border-radius: 8px;
        cursor: pointer;
        color: #94a3b8;
    }
    .view-btn.active {
        background: #f1f5f9;
        color: #0f172a;
    }

    /* Tabla Premium */
    .premium-table-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .premium-table {
        width: 100%;
        border-collapse: collapse;
    }
    .premium-table th {
        background: #f8fafc;
        padding: 14px 18px;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
    }
    .premium-table td {
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 13.5px;
        vertical-align: middle;
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
        gap: 20px;
    }
    .sidebar-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .sidebar-card h3 {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 16px;
    }
    .donut-chart-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
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

    /* Cajón de Filtros Deslizante (Drawer) */
    .drawer-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(2px);
        z-index: 999;
        display: none;
    }
    .filters-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 350px;
        background: white;
        box-shadow: -10px 0 30px rgba(0, 0, 0, 0.08);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        overflow-y: auto;
    }
    .filters-drawer.open {
        transform: translateX(0);
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
        accent-color: #2563eb;
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

    document.addEventListener('DOMContentLoaded', () => {
        setupAutocomplete('new_tag_id', 'new_tag_dropdown');
        setupAutocomplete('edit_tag_id', 'edit_tag_dropdown');
    });

    function toggleEnrollMode() {
        const mode = document.getElementById('enroll_mode').value;
        document.getElementById('mode-single').style.display = mode === 'single' ? 'block' : 'none';
        document.getElementById('mode-range').style.display = mode === 'range' ? 'block' : 'none';
        document.getElementById('mode-list').style.display = mode === 'list' ? 'block' : 'none';
    }



    function switchAssetTab(tab) {
        document.getElementById('section-inventario').style.display = tab === 'inventario' ? 'grid' : 'none';

        document.getElementById('section-prestamos').style.display = tab === 'prestamos' ? 'grid' : 'none';
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
        document.getElementById('edit_esp_asignado').value = asset.esp_asignado || '';
        
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    // Search inventory & Filters Logic
    const searchInventory = document.getElementById("searchInventory");
    const topSearchInput = document.getElementById("topSearchInput");
    const quickTypeFilter = document.getElementById("quickTypeFilter");
    const statusFilter = document.getElementById("statusFilter");
    const quickLocationFilter = document.getElementById("quickLocationFilter");

    const drawerTypeFilter = document.getElementById("drawerTypeFilter");
    const drawerLocationFilter = document.getElementById("drawerLocationFilter");
    const drawerRfidInput = document.getElementById("drawerRfidInput");
    const showOnlyAvailable = document.getElementById("showOnlyAvailable");

    function applyFilters() {
        const topSearchVal = topSearchInput.value.toLowerCase();
        const searchVal = searchInventory.value.toLowerCase();
        
        const statusBoxes = document.querySelectorAll('.status-checkbox:checked');
        const selectedStatuses = Array.from(statusBoxes).map(cb => cb.value);

        const edificioBoxes = document.querySelectorAll('.edificio-checkbox:checked');
        const selectedEdificios = Array.from(edificioBoxes).map(cb => cb.value);

        const typeVal = quickTypeFilter.value || drawerTypeFilter.value;
        const statusVal = statusFilter.value;
        const locVal = quickLocationFilter.value || drawerLocationFilter.value;
        const rfidVal = drawerRfidInput.value.toLowerCase();
        const onlyAvail = showOnlyAvailable.checked;

        document.querySelectorAll("#inventoryTable tbody tr").forEach(row => {
            const text = row.innerText.toLowerCase();
            const rowStatus = row.dataset.status;
            const rowTipo = row.dataset.tipoCat; // 'Equipo' or 'Mobiliario'
            const rowLoc = row.dataset.ubicacion;
            const rowEdificio = row.dataset.edificio;
            
            const matchesText = text.includes(searchVal) && text.includes(topSearchVal);
            const matchesType = !typeVal || rowTipo === typeVal;

            let matchesStatus = true;
            if (statusVal) {
                matchesStatus = (rowStatus === statusVal);
            } else if (selectedStatuses.length > 0) {
                matchesStatus = selectedStatuses.includes(rowStatus);
            }

            const matchesEdificio = selectedEdificios.length === 0 || selectedEdificios.includes(rowEdificio);
            const matchesLoc = !locVal || rowLoc === locVal;
            const matchesRfid = !rfidVal || text.includes(rfidVal);
            const matchesAvail = !onlyAvail || rowStatus === 'Disponible';

            if (matchesText && matchesType && matchesStatus && matchesEdificio && matchesLoc && matchesRfid && matchesAvail) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    [searchInventory, topSearchInput, quickTypeFilter, statusFilter, quickLocationFilter].forEach(el => {
        if(el) {
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        }
    });

    function toggleFiltersDrawer(show) {
        const drawer = document.getElementById("filtersDrawer");
        const overlay = document.getElementById("drawerOverlay");
        if(show) {
            drawer.classList.add("open");
            overlay.style.display = "block";
        } else {
            drawer.classList.remove("open");
            overlay.style.display = "none";
        }
    }

    function clearDrawerFilters() {
        document.querySelectorAll('.status-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.edificio-checkbox').forEach(cb => cb.checked = false);
        drawerTypeFilter.value = "";
        drawerLocationFilter.value = "";
        drawerRfidInput.value = "";
        showOnlyAvailable.checked = false;
        applyFilters();
        toggleFiltersDrawer(false);
    }

    // Export Table to CSV
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

    // Row dropdown menu toggle
    function toggleRowMenu(event, id) {
        event.stopPropagation();
        const menu = document.getElementById('menu-' + id);
        const allMenus = document.querySelectorAll('.row-action-menu');
        allMenus.forEach(m => {
            if (m !== menu) m.style.display = 'none';
        });
        if(menu) {
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.row-action-menu').forEach(m => m.style.display = 'none');
    });

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

<!-- Cajón Lateral de Filtros (Drawer) -->
<div id="drawerOverlay" class="drawer-overlay" onclick="toggleFiltersDrawer(false)"></div>
<div id="filtersDrawer" class="filters-drawer">
    <div class="drawer-header">
        <h3>Filtros de inventario</h3>
        <button class="close-drawer-btn" onclick="toggleFiltersDrawer(false)">✕</button>
    </div>

    <!-- Sección 1: Estado del Activo -->
    <div class="drawer-section">
        <h4>Estado del activo</h4>
        <div class="checkbox-group">
            <label class="checkbox-label">
                <input type="checkbox" class="status-checkbox" value="Disponible" checked>
                Disponible
            </label>
            <label class="checkbox-label">
                <input type="checkbox" class="status-checkbox" value="Prestado" checked>
                En uso / Prestado
            </label>
            <label class="checkbox-label">
                <input type="checkbox" class="status-checkbox" value="Mantenimiento" checked>
                Mantenimiento
            </label>
            <label class="checkbox-label">
                <input type="checkbox" class="status-checkbox" value="Extraviado">
                Extraviado
            </label>
        </div>
    </div>

    <!-- Sección 2: Edificio -->
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

    <!-- Sección 3: Tipo de Activo -->
    <div class="drawer-section">
        <h4>Tipo de activo</h4>
        <select id="drawerTypeFilter" class="select-filter" style="width: 100%;">
            <option value="">Seleccionar tipo</option>
            <option value="Equipo">Equipo</option>
            <option value="Mobiliario">Mobiliario</option>
        </select>
    </div>

    <!-- Sección 4: Espacio / Aula / Laboratorio -->
    <div class="drawer-section">
        <h4>Espacio / Aula / Laboratorio</h4>
        <select id="drawerLocationFilter" class="select-filter" style="width: 100%;">
            <option value="">Seleccionar espacio</option>
            <?php foreach($allSpaces as $sp): ?>
                <option value="<?php echo htmlspecialchars($sp['nombre_numero']); ?>"><?php echo htmlspecialchars($sp['nombre_numero'] . ' (' . $sp['edificio'] . ')'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Sección 5: Disponibilidad -->
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

    <!-- Sección 6: RFID (Tag) -->
    <div class="drawer-section">
        <h4>RFID (Tag)</h4>
        <input type="text" id="drawerRfidInput" class="form-control" placeholder="Buscar por RFID o tag..." style="font-size: 13px; padding: 8px 12px;">
    </div>

    <!-- Sección 7: Fecha de registro -->
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

    <!-- Footer del Drawer -->
    <div class="drawer-footer">
        <button class="btn-secondary" onclick="clearDrawerFilters()" style="justify-content: center; padding: 10px; font-size: 12px;">
            <i class="bi bi-arrow-counterclockwise"></i> Limpiar
        </button>
        <button class="btn-primary" onclick="applyFilters(); toggleFiltersDrawer(false);" style="justify-content: center; padding: 10px; font-size: 12px;">
            <i class="bi bi-funnel"></i> Aplicar
        </button>
    </div>
</div>

<!-- Modal de Nuevo Activo Premium -->
<div id="newAssetModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header">
            <h3>Nuevo activo</h3>
            <button onclick="document.getElementById('newAssetModal').style.display='none'">✕</button>
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
                <button type="button" class="btn-secondary" onclick="document.getElementById('newAssetModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-box-seam"></i> Registrar activo
                </button>
            </div>
        </form>
    </div>
</div>


<?php include 'footer.php'; ?>
