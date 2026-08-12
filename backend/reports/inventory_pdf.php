<?php
/**
 * @file inventory_pdf.php
 * @summary Generador de reporte de inventario de activos en formato imprimible/PDF.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['us_id'])) die("Acceso denegado.");

require_once __DIR__ . '/../config/Database.php';

$db = Config\Database::getConnection();

// Filtros opcionales por GET
$search   = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$tipo     = isset($_GET['tipo'])     ? trim($_GET['tipo'])     : '';
$estado   = isset($_GET['estado'])   ? trim($_GET['estado'])   : '';
$edificio = isset($_GET['edificio']) ? trim($_GET['edificio']) : '';
$espacio  = isset($_GET['espacio'])  ? trim($_GET['espacio'])  : '';
$tab      = isset($_GET['tab'])      ? trim($_GET['tab'])      : 'inventario';

$sql = "
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
WHERE 1=1
";

$params = [];

if ($tab === 'mobiliario') {
    $sql .= " AND a.item_type = 'mobiliario'";
} else {
    $sql .= " AND a.item_type = 'activo'";
}

if ($search !== '') {
    $sql .= " AND (a.tipo ILIKE ? OR a.marca ILIKE ? OR a.modelo ILIKE ? OR a.num_serie ILIKE ? OR a.num_inv ILIKE ? OR a.tag_id ILIKE ? OR s.nombre_numero ILIKE ?)";
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
}

if ($tipo !== '') {
    $sql .= " AND a.tipo = ?";
    $params[] = $tipo;
}

if ($estado !== '') {
    if ($estado === 'Prestado') {
        $sql .= " AND a.estatus IN ('Prestado', 'En préstamo', 'En uso')";
    } elseif ($estado === 'Mantenimiento') {
        $sql .= " AND a.estatus IN ('Mantenimiento', 'En mantenimiento')";
    } elseif ($estado === 'Extraviado') {
        $sql .= " AND a.estatus IN ('Extraviado', 'Inactivo', 'Baja')";
    } else {
        $sql .= " AND a.estatus = ?";
        $params[] = $estado;
    }
}

if ($edificio !== '') {
    $sql .= " AND s.edificio = ?";
    $params[] = $edificio;
}

if ($espacio !== '') {
    $sql .= " AND s.nombre_numero = ?";
    $params[] = $espacio;
}

$sql .= " ORDER BY a.act_id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($assets);

// Conteos por estado
$disponibles   = count(array_filter($assets, fn($a) => ($a['estatus'] ?? '') === 'Disponible'));
$prestados     = count(array_filter($assets, fn($a) => in_array($a['estatus'] ?? '', ['Prestado', 'En préstamo', 'En uso'])));
$mantenimiento = count(array_filter($assets, fn($a) => in_array($a['estatus'] ?? '', ['Mantenimiento', 'Dañado', 'Extraviado', 'En mantenimiento'])));
?>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - SIGRAT</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            color: #1e293b;
            background: #fff;
            font-size: 13px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 28px;
        }
        .logo { font-size: 30px; font-weight: 900; color: #2563eb; letter-spacing: -1px; }
        .logo span { font-size: 11px; display: block; color: #64748b; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
        .report-info { text-align: right; }
        .report-info h1 { font-size: 18px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; }
        .report-info p { font-size: 11px; color: #64748b; margin-top: 4px; }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
            text-align: center;
        }
        .stat-card .num { font-size: 28px; font-weight: 900; color: #2563eb; }
        .stat-card .label { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .stat-card.disponible .num { color: #16a34a; }
        .stat-card.prestado .num { color: #d97706; }
        .stat-card.alerta .num { color: #dc2626; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        thead th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 13px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        tbody td {
            padding: 10px 13px;
            font-size: 12px;
            color: #334155;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .badge-disponible { background: #dcfce7; color: #15803d; }
        .badge-prestado   { background: #fef3c7; color: #b45309; }
        .badge-mant       { background: #fee2e2; color: #b91c1c; }
        .badge-otro       { background: #f1f5f9; color: #475569; }

        .asset-name { font-weight: 700; color: #1e293b; }
        .asset-serie { font-size: 10px; color: #64748b; margin-top: 2px; }
        .mono { font-family: 'Courier New', monospace; font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }

        .footer {
            margin-top: 40px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }

        .actions {
            margin-top: 28px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .actions button {
            padding: 11px 28px;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            border: none;
        }
        .btn-print { background: #2563eb; color: white; }
        .btn-close { background: #f1f5f9; color: #1e293b; border: 1px solid #e2e8f0 !important; }

        @media print {
            .actions { display: none !important; }
            body { padding: 20px; }
        }
    </style>
</head>


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
<body >
    <div class="header">
        <div>
            <div class="logo">SIGRAT<span>Control Integral</span></div>
        </div>
        <div class="report-info">
            <h1>Reporte de Inventario</h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
            <?php if ($tipo || $estado): ?>
                <p>
                    <?php if ($tipo) echo "Tipo: <strong>$tipo</strong>  "; ?>
                    <?php if ($estado) echo "Estado: <strong>$estado</strong>"; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?php echo $total; ?></div>
            <div class="label">Total Activos</div>
        </div>
        <div class="stat-card disponible">
            <div class="num"><?php echo $disponibles; ?></div>
            <div class="label">Disponibles</div>
        </div>
        <div class="stat-card prestado">
            <div class="num"><?php echo $prestados; ?></div>
            <div class="label">Prestados</div>
        </div>
        <div class="stat-card alerta">
            <div class="num"><?php echo $mantenimiento; ?></div>
            <div class="label">En Alerta</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Activo</th>
                <th>Tipo</th>
                <th>Nº Inventario</th>
                <th>Tag RFID</th>
                <th>Ubicación</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assets)): ?>
            <tr>
                <td colspan="7" style="text-align:center; padding: 30px; color: #94a3b8;">No se encontraron activos con los filtros aplicados.</td>
            </tr>
            <?php else: ?>
            <?php $i = 1; foreach ($assets as $a): ?>
            <tr>
                <td style="color:#94a3b8; font-size:11px;"><?php echo $i++; ?></td>
                <td>
                    <?php 
                    $nameText = trim(($a['marca'] ?? '') . ' ' . ($a['modelo'] ?? ''));
                    if (empty($nameText)) {
                        $nameText = !empty($a['tipo']) ? $a['tipo'] : ('Item #' . $a['act_id']);
                    }
                    ?>
                    <div class="asset-name"><?php echo htmlspecialchars($nameText); ?></div>
                    <?php 
                    $numSerie = trim($a['num_serie'] ?? '');
                    if ($numSerie !== '' && strcasecmp($numSerie, 'N/A') !== 0): 
                    ?>
                        <div class="asset-serie">S/N: <?php echo htmlspecialchars($numSerie); ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($a['tipo']); ?></td>
                <td><span class="mono"><?php echo htmlspecialchars($a['num_inv'] ?? '—'); ?></span></td>
                <td><span class="mono"><?php echo htmlspecialchars($a['tag_id'] ?? '—'); ?></span></td>
                <td>
                    <?php if (!empty($a['espacio_nombre'])): ?>
                        <?php echo htmlspecialchars($a['espacio_nombre']); ?>
                        <?php if (!empty($a['edificio'])): ?>
                            <div class="asset-serie"><?php echo htmlspecialchars($a['edificio']); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $est = $a['estatus'];
                    $cls = match($est) {
                        'Disponible' => 'badge-disponible',
                        'Prestado'   => 'badge-prestado',
                        'Mantenimiento', 'Dañado', 'Extraviado' => 'badge-mant',
                        default      => 'badge-otro'
                    };
                    ?>
                    <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($est); ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Reporte de Inventario · Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT) · <?php echo date('Y'); ?>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
        <button class="btn-close" onclick="window.close()">Cerrar</button>
    </div>
</body>
</html>
