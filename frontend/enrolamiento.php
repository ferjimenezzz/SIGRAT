<?php
/**
 * @file enrolamiento.php
 * @summary Interfaz de enrolamiento masivo de activos RFID en PHP.
 * @description Permite la captura de tags en tiempo real y el registro por lotes.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/AssetController.php';
require_once '../backend/controllers/SpaceController.php';
require_once '../backend/controllers/TagController.php';

$db = Config\Database::getConnection();

$assetController = new Controllers\AssetController();
$spaceController = new Controllers\SpaceController();
$tagController = new Controllers\TagController();

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

// Manejar enrolamiento masivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_tags') {
    $tagsToEnroll = [];
    $mode = $_POST['enroll_mode'] ?? 'list';
    
    if ($mode === 'single' && !empty($_POST['single_tag'])) {
        $tagsToEnroll[] = trim($_POST['single_tag']);
    } elseif ($mode === 'range') {
        $prefixForm = trim($_POST['range_prefix'] ?? '');
        $startStrRaw = trim($_POST['range_start'] ?? '');
        $endStrRaw = trim($_POST['range_end'] ?? '');
        
        // Extracción inteligente para tags alfanuméricos (ej: 141C01972116)
        preg_match('/^(.*?)(\d+)$/', $startStrRaw, $startMatches);
        preg_match('/^(.*?)(\d+)$/', $endStrRaw, $endMatches);
        
        $startPrefix = $startMatches[1] ?? '';
        $startNumStr = $startMatches[2] ?? $startStrRaw;
        $endNumStr = $endMatches[2] ?? $endStrRaw;
        
        $finalPrefix = $prefixForm . $startPrefix;
        
        $start = (int)$startNumStr;
        $end = (int)$endNumStr;
        
        $padLength = strlen($startNumStr);
        
        if ($start > 0 && $end >= $start) {
            $limit = min($end, $start + 100000); // Límite de seguridad
            for ($i = $start; $i <= $limit; $i++) {
                // str_pad mantiene los ceros a la izquierda, y si no los necesita no altera el número.
                $tagsToEnroll[] = $finalPrefix . str_pad((string)$i, $padLength, '0', STR_PAD_LEFT);
            }
        }
    } elseif ($mode === 'list' && !empty($_POST['tags_text'])) {
        $tagsToEnroll = explode("\n", $_POST['tags_text']);
    }

    if (empty($tagsToEnroll)) {
        header("Location: enrolamiento.php?tab=enrolamiento&error=" . urlencode("Datos inválidos para enrolar."));
        exit();
    }

    $res = $tagController->enrollManualBatch($tagsToEnroll);
    if ($res['success']) {
        header("Location: enrolamiento.php?tab=enrolamiento&success=" . $res['enrolled']);
    } else {
        header("Location: enrolamiento.php?tab=enrolamiento&error=" . urlencode($res['error']));
    }
    exit();
}

// Manejar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_asset') {
    $res = $assetController->update($_POST['act_id'], $_POST);
    if (!$res['success']) {
        header("Location: enrolamiento.php?tab=inventario&error=" . urlencode($res['error']));
    } else {
        header("Location: enrolamiento.php?tab=inventario");
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_asset') {
    $res = $assetController->create($_POST);
    if (!$res['success']) {
        header("Location: enrolamiento.php?tab=inventario&error=" . urlencode($res['error']));
    } else {
        header("Location: enrolamiento.php?tab=inventario");
    }
    exit();
}

// Manejar eliminación
if (isset($_GET['delete_id'])) {
    $assetController->delete($_GET['delete_id']);
    header("Location: enrolamiento.php?tab=inventario");
    exit();
}

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gestión de Activos</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Control de inventario, préstamos y mantenimiento.</p>
        </div>
        <div style="display: flex; gap: 8px; background: #f1f5f9; padding: 4px; border-radius: 12px;">
            <button onclick="switchAssetTab('inventario')" id="tab-inventario" class="btn-tab active">INVENTARIO</button>
            <button onclick="switchAssetTab('enrolamiento')" id="tab-enrolamiento" class="btn-tab">ENROLAMIENTO</button>
            <button onclick="switchAssetTab('prestamos')" id="tab-prestamos" class="btn-tab">PRÉSTAMOS</button>
            <button onclick="switchAssetTab('mantenimiento')" id="tab-mantenimiento" class="btn-tab">MANTENIMIENTO</button>
        </div>
    </header>

    <!-- Sección: Lista de Inventario (CRUD) -->
    <div id="section-inventario" style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px;">
        <main style="display: flex; flex-direction: column; gap: 16px;">
            <?php if(isset($_GET['error']) && $_GET['tab'] === 'inventario'): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 16px; border-radius: 8px; font-weight: bold; font-size: 14px;">
                    Error: <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <div class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Activo / Marca</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Ubicación / TAG</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Núm. Inv</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px;">
                            <p style="font-size: 14px; font-weight: 700; color: #334155;"><?php echo $asset['tipo']; ?> <?php echo $asset['modelo']; ?></p>
                            <p style="font-size: 10px; font-weight: 700; color: #94a3b8;"><?php echo $asset['marca']; ?> • S/N: <?php echo $asset['num_serie']; ?></p>
                        </td>
                        <td style="padding: 16px 24px;">
                            <p style="font-size: 12px; font-weight: 700; color: #475569;"><?php echo $asset['espacio_nombre'] ?? 'Sin asignar'; ?></p>
                            <p style="font-size: 9px; font-weight: 800; color: #3b82f6; font-family: 'JetBrains Mono', monospace;"><?php echo $asset['tag_id'] ?? 'SIN TAG'; ?></p>
                        </td>
                        <td style="padding: 16px 24px;">
                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; color: #64748b;"><?php echo $asset['num_inv']; ?></span>
                        </td>
                        <td style="padding: 16px 24px;">
                            <span style="color: <?php echo $asset['estatus'] == 'Disponible' ? '#10b981' : '#f59e0b'; ?>; font-size: 10px; font-weight: 800;"><?php echo strtoupper($asset['estatus']); ?></span>
                        </td>
                        <td style="padding: 16px 24px; text-align: right;">
                            <a href="javascript:void(0)" onclick='openEditModal(<?php echo json_encode($asset); ?>)' style="color: #3b82f6; text-decoration: none; font-size: 10px; font-weight: 900; margin-right: 12px;">EDITAR</a>
                            <a href="?delete_id=<?php echo $asset['act_id']; ?>" onclick="return confirm('¿Eliminar este activo?')" style="color: #ef4444; text-decoration: none; font-size: 10px; font-weight: 900;">ELIMINAR</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </main>

        <aside class="card">
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 24px;">Nuevo Activo</h3>
            <form method="POST">
                <input type="hidden" name="action" value="new_asset">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label>Tipo de Activo</label>
                        <input type="text" name="tipo" required placeholder="Ej: Laptop, Monitor" class="form-control">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label>Marca</label>
                            <input type="text" name="marca" required class="form-control">
                        </div>
                        <div>
                            <label>Modelo</label>
                            <input type="text" name="modelo" required class="form-control">
                        </div>
                    </div>
                    <div>
                        <label>Número de Serie</label>
                        <input type="text" name="num_serie" required class="form-control">
                    </div>
                    <div>
                        <label>Número de Inventario</label>
                        <input type="text" name="num_inv" required placeholder="Ej: INV-2024-001" class="form-control">
                    </div>
                    <div style="position: relative;">
                        <label>UID TAG (RFID)</label>
                        <input type="text" name="tag_id" id="new_tag_id" autocomplete="off" placeholder="Busca o Escanea el TAG..." class="form-control" style="font-family: 'JetBrains Mono', monospace; color: var(--active-blue); width: 100%; box-sizing: border-box;" required>
                        <div id="new_tag_dropdown" class="custom-dropdown"></div>
                    </div>
                    <div>
                        <label>Espacio Asignado</label>
                        <select name="esp_asignado" class="form-control">
                            <option value="">Seleccionar Área...</option>
                            <?php foreach ($allSpaces as $sp): ?>
                            <option value="<?php echo $sp['esp_id']; ?>"><?php echo $sp['edificio']; ?> - <?php echo $sp['nombre_numero']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">REGISTRAR ACTIVO</button>
                </div>
            </form>
        </aside>
    </div>

    <!-- Sección: Enrolamiento -->
    <div id="section-enrolamiento" style="display: none; grid-template-columns: 1fr; gap: 32px;">
        <main class="card" style="max-width: 800px; margin: 0 auto; width: 100%;">
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 24px;">Enrolamiento Manual de TAGs</h3>
            
            <?php if(isset($_GET['success']) && $_GET['tab'] === 'enrolamiento'): ?>
                <div style="background: #dcfce3; color: #166534; padding: 16px; border-radius: 8px; margin-bottom: 16px; font-weight: bold;">
                    Se enrolaron exitosamente <?php echo htmlspecialchars($_GET['success']); ?> TAG(s).
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && $_GET['tab'] === 'enrolamiento'): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 16px; border-radius: 8px; margin-bottom: 16px; font-weight: bold;">
                    Error: <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <p style="font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.6;">
                Selecciona la modalidad para dar de alta los identificadores. Estos quedarán como "Disponibles" en tu base de datos listos para asociarse a equipos físicos o llaves.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="enroll_tags">
                
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Modo de Captura</label>
                    <select name="enroll_mode" id="enroll_mode" class="form-control" onchange="toggleEnrollMode()" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; width: 100%; font-weight: 700; color: #334155;">
                        <option value="range">Lote Secuencial (Rango Automático)</option>
                        <option value="single">Unidad Única (Captura Manual o Escáner)</option>
                        <option value="list">Lista Manual (Copiar/Pegar Lote)</option>
                    </select>
                </div>

                <!-- MODO RANGE -->
                <div id="mode-range" style="display: block; background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #334155; margin-bottom: 16px;">Generación Cíclica</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 6px;">Prefijo (Opcional)</label>
                            <input type="text" name="range_prefix" class="form-control" placeholder="Ej: TAG-" style="font-family: 'JetBrains Mono', monospace;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 6px;">Número Inicial</label>
                            <input type="text" name="range_start" class="form-control" placeholder="Ej: 001" style="font-family: 'JetBrains Mono', monospace;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 6px;">Número Final</label>
                            <input type="text" name="range_end" class="form-control" placeholder="Ej: 100" style="font-family: 'JetBrains Mono', monospace;">
                        </div>
                    </div>
                    <p style="font-size: 11px; color: #94a3b8; margin-top: 12px; font-weight: 600;">Ejemplo: Prefijo "TAG-" con inicio "001" y fin "100" generará 100 códigos desde TAG-001 hasta TAG-100 automáticamente.</p>
                </div>

                <!-- MODO SINGLE -->
                <div id="mode-single" style="display: none; background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 8px;">UID de la Tarjeta</label>
                    <input type="text" name="single_tag" class="form-control" placeholder="Haz clic aquí y pasa la tarjeta por la antena..." style="font-family: 'JetBrains Mono', monospace; font-size: 16px; padding: 16px; color: var(--active-blue);">
                </div>

                <!-- MODO LIST -->
                <div id="mode-list" style="display: none; background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 8px;">Pegar Lista de Códigos</label>
                    <textarea name="tags_text" rows="8" placeholder="E200001B&#10;A100001B&#10;K500001B" class="form-control" style="width: 100%; font-family: 'JetBrains Mono', monospace; padding: 16px; resize: vertical;"></textarea>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-primary" style="padding: 12px 32px; font-size: 14px;">EJECUTAR ENROLAMIENTO</button>
                </div>
            </form>
        </main>
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
    .btn-tab {
        border: none; background: none; padding: 8px 16px; border-radius: 10px;
        font-size: 11px; font-weight: 900; color: #94a3b8; cursor: pointer; transition: all 0.2s;
    }
    .btn-tab.active { background: white; color: var(--active-blue); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

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
        padding-left: 20px; /* Micro animación */
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
        document.getElementById('section-enrolamiento').style.display = tab === 'enrolamiento' ? 'grid' : 'none';
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
</script>

<style>
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.8; }
        100% { opacity: 1; }
    }
    .animate-pulse {
        animation: pulse 1.5s infinite;
    }
</style>

<?php include 'footer.php'; ?>
