<?php
/**
 * @file invitations_pdf.php
 * @summary Generador de reporte de invitaciones y accesos temporales en formato imprimible/PDF.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['us_id'])) die("Acceso denegado.");

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../controllers/InviteController.php';

$inviteController = new Controllers\InviteController();
$invites = $inviteController->getAllActive();

$totalInvites = count($invites);
$activosCount = count(array_filter($invites, fn($i) => ($i['estatus'] ?? '') === 'Generado' || ($i['estatus'] ?? '') === 'Activo'));
$usadosCount  = count(array_filter($invites, fn($i) => ($i['estatus'] ?? '') === 'Usado' || ($i['estatus'] ?? '') === 'Ocupado'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Invitaciones - SIGRAT</title>
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
            margin-bottom: 28px;
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

        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 28px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        code {
            background: #eff6ff;
            color: #2563eb;
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            letter-spacing: 1px;
        }

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

<body>
    <div class="header">
        <div>
            <div class="logo">SIGRAT<span>Control de Invitaciones</span></div>
        </div>
        <div class="report-info">
            <h1>Reporte de Invitaciones y Accesos</h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>Vigencia estándar de códigos: 24 horas</p>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?php echo $totalInvites; ?></div>
            <div class="label">Total Invitaciones</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color: #16a34a;"><?php echo $activosCount; ?></div>
            <div class="label">Generados / Activos</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color: #0284c7;"><?php echo $usadosCount; ?></div>
            <div class="label">Usados / Completados</div>
        </div>
    </div>

    <!-- Tabla de Invitaciones -->
    <div class="section-title">🎟️ Registro de Invitaciones y Códigos Emitidos</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Invitado / Empresa</th>
                <th>Correo Electrónico</th>
                <th>Código de Acceso</th>
                <th>Anfitrión</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invites)): ?>
            <tr>
                <td colspan="6" style="text-align:center; padding: 30px; color: #94a3b8;">No hay invitaciones registradas.</td>
            </tr>
            <?php else: ?>
            <?php $i = 1; foreach ($invites as $inv): ?>
            <tr>
                <td style="color:#94a3b8; font-size:11px;"><?php echo $i++; ?></td>
                <td><strong><?php echo htmlspecialchars($inv['nombre'] ?? ''); ?></strong></td>
                <td><?php echo htmlspecialchars($inv['correo'] ?? 'Sin correo'); ?></td>
                <td><code><?php echo htmlspecialchars($inv['codigo_acceso'] ?? ''); ?></code></td>
                <td><?php echo htmlspecialchars($inv['anfitrion_nombre'] ?? 'N/A'); ?></td>
                <td>
                    <span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 20px; font-weight: 700; font-size: 11px;">
                        <?php echo htmlspecialchars($inv['estatus'] ?? 'Generado'); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Reporte de Invitaciones · Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT) · <?php echo date('Y'); ?>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
        <button class="btn-close" onclick="window.close()">Cerrar</button>
    </div>
</body>
</html>
