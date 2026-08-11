<?php

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['us_id'])) die("Acceso denegado.");

/**
 * @file audit_pdf.php
 * @summary Generador de reporte de auditoría y uso de espacios en formato imprimible/PDF.
 */

require_once '../controllers/AuditController.php';
require_once '../config/Database.php';

$auditController = new Controllers\AuditController();

$filtros = [
    'fecha_inicio' => $_GET['fecha_inicio'] ?? null,
    'fecha_fin' => $_GET['fecha_fin'] ?? null,
    'edificio' => $_GET['edificio'] ?? null,
    'modulo' => $_GET['modulo'] ?? null,
    'estado' => $_GET['estado'] ?? null,
    'buscar_usuario' => $_GET['buscar_usuario'] ?? null,
    'buscar_activo' => $_GET['buscar_activo'] ?? null,
    'metrica' => $_GET['metrica'] ?? 'reservas',
    'limit' => $_GET['limit'] ?? 10
];

$tipo_reporte = $_GET['tipo_reporte'] ?? 'actividad';

// Nombres de reporte
$nombres_reporte = [
    'actividad' => 'Actividad general del sistema',
    'asistencia' => 'Reporte de asistencia a aulas',
    'aulas_top' => 'Reporte de aulas más utilizadas',
    'uso_edificio' => 'Reporte de uso por edificio',
    'asistencia_usuario' => 'Reporte de asistencia por usuario',
    'prestamos' => 'Reporte de préstamos de activos',
    'inventario' => 'Reporte de movimientos de inventario'
];
$titulo_reporte = $nombres_reporte[$tipo_reporte] ?? 'Reporte de Auditoría';

// Procesar según reporte
switch ($tipo_reporte) {
    case 'asistencia':
        $logs = $auditController->getAttendanceReport($filtros);
        break;
    case 'aulas_top':
        $logs = $auditController->getTopSpaces($filtros);
        break;
    case 'uso_edificio':
        $logs = $auditController->getUsageByBuilding($filtros);
        break;
    case 'asistencia_usuario':
        $logs = $auditController->getAttendanceByUser($filtros);
        break;
    case 'prestamos':
        $logs = $auditController->getAssetLoans($filtros);
        break;
    case 'inventario':
        $logs = $auditController->getInventoryMovements($filtros);
        break;
    case 'actividad':
    default:
        $logs = $auditController->getGeneralActivity($filtros);
        break;
}
?>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_reporte; ?> - SIGRAT</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #333; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e293b; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: 900; color: #1e293b; }
        .info { text-align: right; font-size: 12px; color: #666; }
        h1 { font-size: 20px; text-transform: uppercase; margin: 0; color: #0f172a; }
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


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
<body >
    <div class="header">
        <div class="logo">SIGRAT</div>
        <div class="info">
            <h1><?php echo $titulo_reporte; ?></h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
            <?php if (!empty($filtros['fecha_inicio']) || !empty($filtros['fecha_fin'])): ?>
                <p>Periodo: <?php echo $filtros['fecha_inicio'] ?? 'Inicio'; ?> al <?php echo $filtros['fecha_fin'] ?? 'Hoy'; ?></p>
            <?php endif; ?>
            <?php if (in_array($tipo_reporte, ['aulas_top', 'uso_edificio', 'asistencia']) && !empty($filtros['edificio']) && $filtros['edificio'] !== 'Todos'): ?>
                <p>Filtro Edificio: <?php echo htmlspecialchars($filtros['edificio']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
            <?php if (in_array($tipo_reporte, ['actividad', 'inventario'])): ?>
                <tr><th>FECHA Y HORA</th><th>USUARIO</th><th>MÓDULO</th><th>ACCIÓN REALIZADA</th></tr>
            <?php elseif ($tipo_reporte == 'asistencia'): ?>
                <tr><th>FECHA</th><th>HORARIO</th><th>ESPACIO</th><th>RESPONSABLE</th><th>ASISTENCIA</th></tr>
            <?php elseif ($tipo_reporte == 'aulas_top'): ?>
                <tr><th>ESPACIO</th><th>EDIFICIO</th><th>TOTAL RESERVAS</th><th>ASISTENCIA TOTAL</th></tr>
            <?php elseif ($tipo_reporte == 'uso_edificio'): ?>
                <tr><th>EDIFICIO</th><th>TOTAL ESPACIOS</th><th>TOTAL RESERVAS</th><th>ASISTENCIA TOTAL</th></tr>
            <?php elseif ($tipo_reporte == 'asistencia_usuario'): ?>
                <tr><th>FECHA DE USO</th><th>HORARIO</th><th>ESPACIO / EDIFICIO</th><th>ASISTENCIA</th><th>ESTADO / ASISTENCIA</th></tr>
            <?php elseif ($tipo_reporte == 'prestamos'): ?>
                <tr><th>FECHA PRESTAMO</th><th>USUARIO</th><th>ACTIVO / INVENTARIO</th><th>ESTATUS</th></tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">No hay registros para este periodo.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                    <?php if (in_array($tipo_reporte, ['actividad', 'inventario'])): 
                        $mod = $log['modulo_afectado'];
                    ?>
                        <td style="white-space: nowrap;">
                            <div style="font-weight: 600; color: #0f172a;"><?php echo date('d M Y', strtotime($log['fecha_hora'])); ?></div>
                            <div style="font-size: 11px; color: #64748b;"><?php echo date('H:i A', strtotime($log['fecha_hora'])); ?></div>
                        </td>
                        <td>
                            <b><?php echo htmlspecialchars($log['usuario_nombre'] ?? 'SISTEMA'); ?></b>
                        </td>
                        <td><?php echo htmlspecialchars($mod); ?></td>
                        <td style="color: #334155;"><?php echo htmlspecialchars($log['accion']); ?></td>
                    <?php elseif ($tipo_reporte == 'asistencia'): ?>
                        <td><?php echo date('d/m/Y', strtotime($log['fecha_uso'])); ?></td>
                        <td><?php echo htmlspecialchars($log['hora_ent'] . ' a ' . $log['hora_sal']); ?></td>
                        <td><b><?php echo htmlspecialchars($log['espacio']); ?></b> <br><small><?php echo htmlspecialchars($log['edificio']); ?></small></td>
                        <td><?php echo htmlspecialchars($log['responsable']); ?></td>
                        <td><b style="color: #2563eb;"><?php echo (int)$log['num_alumnos']; ?></b> alumnos</td>
                    <?php elseif ($tipo_reporte == 'aulas_top'): ?>
                        <td><b><?php echo htmlspecialchars($log['nombre_numero']); ?></b><br><small><?php echo htmlspecialchars($log['tipo']); ?></small></td>
                        <td><?php echo htmlspecialchars($log['edificio']); ?></td>
                        <td><b><?php echo (int)$log['total_reservas']; ?></b></td>
                        <td style="color: #2563eb;"><b><?php echo (int)$log['total_asistencia']; ?></b> personas</td>
                    <?php elseif ($tipo_reporte == 'uso_edificio'): ?>
                        <td><b><?php echo htmlspecialchars($log['edificio'] ?: 'Sin Edificio'); ?></b></td>
                        <td><?php echo (int)$log['total_espacios']; ?></td>
                        <td><b><?php echo (int)$log['total_reservas']; ?></b></td>
                        <td style="color: #2563eb;"><b><?php echo (int)$log['total_asistencia']; ?></b> personas</td>
                    <?php elseif ($tipo_reporte == 'asistencia_usuario'): ?>
                        <td><b><?php echo date('d/m/Y', strtotime($log['fecha_uso'])); ?></b></td>
                        <td><?php echo htmlspecialchars(date('H:i', strtotime($log['hora_ent'])) . ' - ' . date('H:i', strtotime($log['hora_sal']))); ?></td>
                        <td><b><?php echo htmlspecialchars($log['espacio']); ?></b><br><small><?php echo htmlspecialchars($log['edificio']); ?></small></td>
                        <td><b style="color: #2563eb;"><?php echo (int)($log['num_alumnos'] ?? 0); ?></b> personas</td>
                        <td>
                            <?php 
                                $numAsis = (int)($log['num_alumnos'] ?? 0);
                                $estatus = strtolower($log['estatus'] ?? '');
                                $asistio = ($numAsis > 0 || in_array($estatus, ['aprobada', 'completada', 'finalizada', 'asistio']));
                            ?>
                            <?php if ($asistio): ?>
                                <span style="color: #16a34a; font-weight: bold;">Asistió (<?php echo $numAsis; ?> personas)</span>
                            <?php else: ?>
                                <span style="color: #dc2626; font-weight: bold;">Sin Asistencia</span>
                            <?php endif; ?>
                        </td>
                    <?php elseif ($tipo_reporte == 'prestamos'): ?>
                        <td><?php echo date('d/m/Y H:i', strtotime($log['fecha_pres'])); ?></td>
                        <td><b><?php echo htmlspecialchars($log['usuario_nombre']); ?></b></td>
                        <td><?php echo htmlspecialchars($log['activo_tipo'] . ' - ' . $log['activo_marca']); ?><br><small><?php echo htmlspecialchars($log['activo_inv']); ?></small></td>
                        <td>
                            <?php if ($log['estatus'] == 'Activo'): ?>
                                <span style="color: #ea580c; font-weight: bold;">En Curso</span>
                            <?php else: ?>
                                <span style="color: #16a34a; font-weight: bold;">Finalizado</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
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
