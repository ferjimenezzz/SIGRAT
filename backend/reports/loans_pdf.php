<?php
/**
 * @file loans_pdf.php
 * @summary Generador de reporte de préstamos en formato imprimible/PDF.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['us_id'])) die("Acceso denegado.");

require_once __DIR__ . '/../config/Database.php';

$db = Config\Database::getConnection();

$us_id = $_SESSION['us_id'];
$rol_id = $_SESSION['rol_id'] ?? null;
$stmtRol = $db->prepare("SELECT nombre FROM ROLES WHERE rol_id = ?");
$stmtRol->execute([$rol_id]);
$rol_nombre = strtoupper($stmtRol->fetchColumn() ?: '');
$isAdmin = (strpos($rol_nombre, 'ADMIN') !== false);

$query = "
    SELECT p.pres_id, p.fecha_pres, p.fecha_ent, p.estatus, p.esp_id,
           a.tipo, a.marca, a.modelo, a.num_serie, a.act_id,
           u.nombre as solicitante_nombre, u.correo as solicitante_correo, u.us_id,
           e.edificio, e.planta, e.nombre_numero as espacio_nombre
    FROM PRESTAMO p
    JOIN ACTIVO a ON p.act_id = a.act_id
    JOIN USUARIO u ON p.us_id = u.us_id
    LEFT JOIN ESPACIO e ON p.esp_id = e.esp_id
";

$params = [];
if (!$isAdmin) {
    $query .= " WHERE p.us_id = ? ";
    $params[] = $us_id;
}
$query .= " ORDER BY p.fecha_pres DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($loans);
$activos = count(array_filter($loans, fn($l) => $l['estatus'] === 'Activo'));
$finalizados = count(array_filter($loans, fn($l) => $l['estatus'] === 'Finalizado'));
?>

<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Préstamos - SIGRAT</title>
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
        .stat-card.activo .num { color: #d97706; }
        .stat-card.finalizado .num { color: #16a34a; }

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
        .badge-activo   { background: #fef3c7; color: #b45309; }
        .badge-finalizado { background: #dcfce7; color: #15803d; }

        .asset-name { font-weight: 700; color: #1e293b; }
        .asset-serie { font-size: 10px; color: #64748b; margin-top: 2px; }

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
<body>
    <div class="header">
        <div>
            <div class="logo">SIGRAT<span>Control Integral</span></div>
        </div>
        <div class="report-info">
            <h1>Reporte de Préstamos</h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?php echo $total; ?></div>
            <div class="label">Total Préstamos</div>
        </div>
        <div class="stat-card activo">
            <div class="num"><?php echo $activos; ?></div>
            <div class="label">Préstamos Activos</div>
        </div>
        <div class="stat-card finalizado">
            <div class="num"><?php echo $finalizados; ?></div>
            <div class="label">Préstamos Finalizados</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Equipo</th>
                <th>Ubicación Destino</th>
                <th>Usuario Asignado</th>
                <th>F. Préstamo</th>
                <th>F. Devolución</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($loans)): ?>
            <tr>
                <td colspan="7" style="text-align:center; padding: 30px; color: #94a3b8;">No se encontraron préstamos.</td>
            </tr>
            <?php else: ?>
            <?php $i = 1; foreach ($loans as $l): ?>
            <tr>
                <td style="color:#94a3b8; font-size:11px;"><?php echo $i++; ?></td>
                <td>
                    <div class="asset-name"><?php echo htmlspecialchars(trim($l['tipo'] . ' ' . $l['modelo'])); ?></div>
                    <div class="asset-serie">S/N: <?php echo htmlspecialchars($l['num_serie']); ?></div>
                </td>
                <td>
                    <?php if (!empty($l['espacio_nombre'])): ?>
                        <?php echo htmlspecialchars($l['espacio_nombre']); ?>
                        <?php if (!empty($l['edificio'])): ?>
                            <div class="asset-serie"><?php echo htmlspecialchars($l['edificio']); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;">N/A</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="asset-name"><?php echo htmlspecialchars($l['solicitante_nombre']); ?></div>
                    <div class="asset-serie"><?php echo htmlspecialchars($l['solicitante_correo']); ?></div>
                </td>
                <td><?php echo date('d/m/Y H:i', strtotime($l['fecha_pres'])); ?></td>
                <td><?php echo $l['fecha_ent'] ? date('d/m/Y H:i', strtotime($l['fecha_ent'])) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                <td>
                    <?php
                    $est = $l['estatus'];
                    $cls = ($est === 'Activo') ? 'badge-activo' : 'badge-finalizado';
                    ?>
                    <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($est); ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Reporte de Préstamos · Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT) · <?php echo date('Y'); ?>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
        <button class="btn-close" onclick="window.close()">Cerrar</button>
    </div>
</body>
</html>
