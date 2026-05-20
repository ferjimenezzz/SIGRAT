<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['us_id'])) die("Acceso denegado.");

/**
 * @file audit_pdf.php
 * @summary Generador de reporte de auditoría en formato imprimible/PDF.
 */

require_once '../controllers/AuditController.php';
require_once '../config/Database.php';

$auditController = new Controllers\AuditController();

$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;
$us_id = $_GET['us_id'] ?? null;
$modulo = $_GET['modulo'] ?? null;

$logs = $auditController->getFiltered($fecha_inicio, $fecha_fin, $us_id, $modulo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Auditoría - SIGRAT</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #333; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e293b; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: 900; color: #1e293b; }
        .info { text-align: right; font-size: 12px; color: #666; }
        h1 { font-size: 20px; text-transform: uppercase; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 10px; text-transform: uppercase; border: 1px solid #e2e8f0; }
        td { padding: 10px; font-size: 11px; border: 1px solid #e2e8f0; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <div class="logo">SIGRAT</div>
        <div class="info">
            <h1>Reporte de Auditoría</h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
            <?php if ($fecha_inicio || $fecha_fin): ?>
                <p>Periodo: <?php echo $fecha_inicio ?? 'Inicio'; ?> al <?php echo $fecha_fin ?? 'Hoy'; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Usuario</th>
                <th>Módulo</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space: nowrap;"><?php echo $log['fecha_hora']; ?></td>
                <td><strong><?php echo $log['usuario_nombre'] ?? 'SISTEMA'; ?></strong></td>
                <td><?php echo $log['modulo_afectado']; ?></td>
                <td><?php echo $log['accion']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Este documento es un registro oficial del Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT).
    </div>

    <div class="no-print" style="margin-top: 30px; text-align: center; display: flex; justify-content: center; gap: 16px;">
        <button onclick="window.print()" style="padding: 12px 24px; cursor: pointer; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 800;">IMPRIMIR / GUARDAR PDF</button>
        <button onclick="window.close()" style="padding: 12px 24px; cursor: pointer; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 800;">CERRAR</button>
    </div>
</body>
</html>
