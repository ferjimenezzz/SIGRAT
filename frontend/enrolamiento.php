<?php
/**
 * @file enrolamiento.php
 * @summary Interfaz de enrolamiento masivo de activos RFID en PHP.
 * @description Permite la captura de tags en tiempo real y el registro por lotes.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/AssetController.php';
require_once '../backend/controllers/SpaceController.php';

$db = Config\Database::getConnection();

$assetController = new Controllers\AssetController();
$spaceController = new Controllers\SpaceController();

$allSpaces = $spaceController->getAll();

// Capturar filtros
$filtro = $_GET['filtro'] ?? null;
$db = Config\Database::getConnection(); // Inicialización crítica
$query = "SELECT a.*, e.nombre_numero as espacio_nombre, e.edificio FROM ACTIVO a LEFT JOIN ESPACIO e ON a.esp_asignado = e.esp_id";
if ($filtro === 'alerta') {
    $query .= " WHERE a.estatus IN ('Mantenimiento', 'Extraviado', 'Dañado')";
}
$query .= " ORDER BY a.act_id DESC";
$assets = $db->query($query)->fetchAll();

// Manejar creación rápida desde la vista
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_asset') {
    $assetController->create($_POST);
    header("Location: enrolamiento.php?tab=inventario");
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
        <main class="card" style="padding: 0; overflow: hidden;">
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
                            <a href="?delete_id=<?php echo $asset['act_id']; ?>" onclick="return confirm('¿Eliminar este activo?')" style="color: #ef4444; text-decoration: none; font-size: 10px; font-weight: 900;">ELIMINAR</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                    <div>
                        <label>UID TAG (RFID)</label>
                        <input type="text" name="tag_id" placeholder="Escanea el TAG..." class="form-control" style="font-family: 'JetBrains Mono', monospace; color: var(--active-blue);">
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
    <div id="section-enrolamiento" style="display: none; grid-template-columns: 1fr 2fr; gap: 32px;">
        <!-- ... contenido existente ... -->
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

<style>
    .btn-tab {
        border: none; background: none; padding: 8px 16px; border-radius: 10px;
        font-size: 11px; font-weight: 900; color: #94a3b8; cursor: pointer; transition: all 0.2s;
    }
    .btn-tab.active { background: white; color: var(--active-blue); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
</style>

<script>
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
    // ... resto del script toggleScan ...
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
