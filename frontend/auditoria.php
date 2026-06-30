<?php
/**
 * @file auditoria.php
 * @summary Módulo de Auditoría y Reportes dinámicos.
 * @description Interfaz adaptativa según el reporte seleccionado.
 */
require_once 'seguridad.php';
require_once '../backend/controllers/AuditController.php';
require_once '../backend/config/Database.php';

$auditController = new Controllers\AuditController();
$db = Config\Database::getConnection();

// Capturar parámetros
$tipo_reporte = $_GET['tipo_reporte'] ?? 'actividad';
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

$logs = [];
$stats = $auditController->getAuditStats();

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
    case 'incidencias':
        $logs = $auditController->getIncidents($filtros);
        break;
    case 'actividad':
    default:
        $logs = $auditController->getGeneralActivity($filtros);
        break;
}

$usuarios = $db->query("SELECT us_id, nombre FROM usuario ORDER BY nombre")->fetchAll();
$edificios_db = $db->query("SELECT DISTINCT edificio FROM ESPACIO WHERE edificio IS NOT NULL ORDER BY edificio")->fetchAll(PDO::FETCH_COLUMN);

include 'header.php';
?>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1e3a8a;
        --secondary: #f1f5f9;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --bg-main: #f8fafc;
        --radius: 12px;
        --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }
    .audit-container { padding: 0 24px 40px 24px; font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 24px; }
    .card { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 24px; border: 1px solid var(--border); }
    .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end; }
    .filter-group { display: flex; flex-direction: column; gap: 8px; }
    .filter-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
    .filter-group input, .filter-group select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; }
    .filter-group input:focus, .filter-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    
    .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; border: none; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-secondary { background: var(--secondary); color: var(--text-muted); }
    .btn-outline { border: 1px solid var(--border); background: white; color: var(--text-main); }
    
    /* Preset Dates */
    .preset-dates { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
    .preset-chip { padding: 6px 12px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #475569; border-radius: 16px; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; }
    .preset-chip:hover, .preset-chip.active { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    
    /* Premium Table */
    .table-container { 
        overflow-x: auto; 
        max-height: 600px; 
        border: 1px solid var(--border); 
        border-radius: var(--radius); 
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.5); 
        background: white;
    }
    table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
    th { 
        padding: 16px 20px; 
        font-size: 11px; 
        font-weight: 700; 
        color: var(--text-muted); 
        background: #f8fafc; 
        border-bottom: 1px solid #cbd5e1; 
        position: sticky; 
        top: 0; 
        z-index: 10; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    td { 
        padding: 16px 20px; 
        font-size: 13px; 
        border-bottom: 1px solid var(--secondary); 
        color: var(--text-main); 
        transition: background-color 0.2s ease;
    }
    tbody tr:hover td { background-color: #f8fafc; }
    tbody tr:last-child td { border-bottom: none; }

    /* Print Styles */
    @media print {
        /* Ocultar elementos no deseados */
        .sidebar, .top-bar, form#filterForm, .btn-primary, .btn-secondary, .btn-outline, .preset-dates, .audit-container > div:first-child {
            display: none !important;
        }
        
        /* Ajustar contenedor principal */
        html, body, .main-container, main.content-padding {
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            position: static !important;
            height: auto !important;
            min-height: auto !important;
            overflow: visible !important;
            display: block !important;
            left: 0 !important;
            top: 0 !important;
            width: auto !important;
        }
        .audit-container {
            padding: 0 !important;
        }
        
        /* Ajustar la tarjeta de la tabla */
        .card {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        
        /* Quitar scroll y expandir tabla completa */
        .table-container {
            overflow: visible !important;
            max-height: none !important;
            border: none !important;
            box-shadow: none !important;
        }
        table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        td, th {
            white-space: normal !important;
            word-wrap: break-word !important;
            word-break: break-word !important;
        }
        th {
            position: static !important;
            background: #f8fafc !important;
            color: black !important;
            border-bottom: 2px solid black !important;
        }
        td {
            border-bottom: 1px solid #ccc !important;
        }
    }
</style>

<div class="audit-container">
    <div>
        <h1 style="font-size: 24px; margin:0 0 4px 0;">Módulo de Auditoría y Reportes</h1>
        <p style="color: #64748b; margin:0; font-size: 14px;">Centro de análisis y trazabilidad del sistema.</p>
    </div>

    <div class="card">
        <form method="GET" action="auditoria.php" id="filterForm">
            <!-- Selector Principal -->
            <div class="filter-group" style="margin-bottom: 24px; max-width: 400px;">
                <label>Selecciona el tipo de reporte</label>
                <select name="tipo_reporte" id="tipoReporte" style="font-size: 15px; font-weight: 600; padding: 12px;">
                    <option value="actividad" <?php echo $tipo_reporte == 'actividad' ? 'selected' : ''; ?>>Actividad general del sistema</option>
                    <option value="asistencia" <?php echo $tipo_reporte == 'asistencia' ? 'selected' : ''; ?>>Reporte de asistencia a aulas</option>
                    <option value="aulas_top" <?php echo $tipo_reporte == 'aulas_top' ? 'selected' : ''; ?>>Reporte de aulas más utilizadas</option>
                    <option value="uso_edificio" <?php echo $tipo_reporte == 'uso_edificio' ? 'selected' : ''; ?>>Reporte de uso por edificio</option>
                    <option value="asistencia_usuario" <?php echo $tipo_reporte == 'asistencia_usuario' ? 'selected' : ''; ?>>Reporte de asistencia por usuario</option>
                    <option value="prestamos" <?php echo $tipo_reporte == 'prestamos' ? 'selected' : ''; ?>>Reporte de préstamos de activos</option>
                    <option value="inventario" <?php echo $tipo_reporte == 'inventario' ? 'selected' : ''; ?>>Reporte de movimientos de inventario</option>
                    <option value="incidencias" <?php echo $tipo_reporte == 'incidencias' ? 'selected' : ''; ?>>Reporte de incidencias y mantenimientos</option>
                </select>
            </div>

            <!-- Rango de Fechas (Siempre visible) -->
            <div class="filters-grid" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px;">
                <div class="filter-group">
                    <label>Desde Fecha</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $filtros['fecha_inicio']; ?>">
                </div>
                <div class="filter-group">
                    <label>Hasta Fecha</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $filtros['fecha_fin']; ?>">
                </div>
                <div style="grid-column: 1 / -1;" class="preset-dates">
                    <span class="preset-chip" onclick="setPreset('hoy')">Hoy</span>
                    <span class="preset-chip" onclick="setPreset('semana')">Esta semana</span>
                    <span class="preset-chip" onclick="setPreset('mes')">Este mes</span>
                    <span class="preset-chip" onclick="setPreset('cuatrimestre')">Este cuatrimestre</span>
                    <span class="preset-chip" onclick="setPreset('30dias')">Últimos 30 días</span>
                    <span class="preset-chip" onclick="setPreset('custom')">Limpiar fechas</span>
                </div>
            </div>

            <!-- Filtros Dinámicos -->
            <div class="filters-grid" id="dynamicFilters">
                
                <div class="filter-group fg-edificio" style="display:none;">
                    <label>Edificio</label>
                    <select name="edificio">
                        <option value="Todos">Todos los edificios</option>
                        <?php foreach($edificios_db as $ed): ?>
                            <option value="<?php echo htmlspecialchars($ed); ?>" <?php echo $filtros['edificio'] == $ed ? 'selected' : ''; ?>><?php echo htmlspecialchars($ed); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group fg-usuario" style="display:none;">
                    <label>Buscar Usuario</label>
                    <input type="text" name="buscar_usuario" placeholder="Nombre de profesor o alumno..." value="<?php echo htmlspecialchars($filtros['buscar_usuario'] ?? ''); ?>">
                </div>

                <div class="filter-group fg-modulo" style="display:none;">
                    <label>Módulo del Sistema</label>
                    <select name="modulo">
                        <option value="">Todos los módulos</option>
                        <option value="USUARIOS" <?php echo $filtros['modulo'] == 'USUARIOS' ? 'selected' : ''; ?>>Usuarios</option>
                        <option value="RESERVAS" <?php echo $filtros['modulo'] == 'RESERVAS' ? 'selected' : ''; ?>>Reservas</option>
                        <option value="ACTIVOS" <?php echo $filtros['modulo'] == 'ACTIVOS' ? 'selected' : ''; ?>>Activos</option>
                        <option value="PRESTAMOS" <?php echo $filtros['modulo'] == 'PRESTAMOS' ? 'selected' : ''; ?>>Préstamos</option>
                    </select>
                </div>



                <div class="filter-group fg-metrica" style="display:none;">
                    <label>Métrica de Ordenamiento</label>
                    <select name="metrica">
                        <option value="reservas" <?php echo $filtros['metrica'] == 'reservas' ? 'selected' : ''; ?>>Cantidad de Reservas</option>
                        <option value="asistencia" <?php echo $filtros['metrica'] == 'asistencia' ? 'selected' : ''; ?>>Volumen de Asistencia</option>
                    </select>
                </div>

                <div class="filter-group fg-limit" style="display:none;">
                    <label>Cantidad de Resultados</label>
                    <input type="number" name="limit" value="<?php echo $filtros['limit'] ?: 10; ?>" min="1" max="100">
                </div>

                <div class="filter-group fg-activo" style="display:none;">
                    <label>Buscar Activo</label>
                    <input type="text" name="buscar_activo" placeholder="Num inv o tipo..." value="<?php echo htmlspecialchars($filtros['buscar_activo'] ?? ''); ?>">
                </div>
            </div>

            <!-- Botones de Acción -->
            <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 24px;">
                <div style="display: flex; gap: 12px;">
                    <a href="auditoria.php" class="btn btn-secondary"><i data-lucide="refresh-cw"></i> Restaurar</a>
                    <button type="submit" class="btn btn-primary"><i data-lucide="filter"></i> Generar Reporte</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Resultados -->
    <div class="card" style="padding: 0;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Resultados (<?php echo count($logs); ?>)</h3>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-outline" onclick="exportToExcel()" style="color: #16a34a; border-color: #16a34a;"><i data-lucide="file-spreadsheet"></i> Exportar Excel</button>
                <button class="btn btn-outline" onclick="window.print()" style="color: #dc2626; border-color: #dc2626;"><i data-lucide="file-text"></i> Exportar PDF</button>
            </div>
        </div>
        
        <div class="table-container">
            <table id="auditTable">
                <thead>
                    <?php if (in_array($tipo_reporte, ['actividad', 'inventario', 'incidencias'])): ?>
                        <tr><th>FECHA Y HORA</th><th>USUARIO</th><th>MÓDULO</th><th>ACCIÓN REALIZADA</th></tr>
                    <?php elseif ($tipo_reporte == 'asistencia'): ?>
                        <tr><th>FECHA</th><th>HORARIO</th><th>ESPACIO</th><th>RESPONSABLE</th><th>ASISTENCIA</th></tr>
                    <?php elseif ($tipo_reporte == 'aulas_top'): ?>
                        <tr><th>ESPACIO</th><th>EDIFICIO</th><th>TOTAL RESERVAS</th><th>ASISTENCIA TOTAL</th></tr>
                    <?php elseif ($tipo_reporte == 'uso_edificio'): ?>
                        <tr><th>EDIFICIO</th><th>TOTAL ESPACIOS</th><th>TOTAL RESERVAS</th><th>ASISTENCIA TOTAL</th></tr>
                    <?php elseif ($tipo_reporte == 'asistencia_usuario'): ?>
                        <tr><th>USUARIO</th><th>ROL</th><th>TOTAL RESERVAS</th><th>ASISTENCIA SUMADA</th></tr>
                    <?php elseif ($tipo_reporte == 'prestamos'): ?>
                        <tr><th>FECHA PRESTAMO</th><th>USUARIO</th><th>ACTIVO / INVENTARIO</th><th>ESTATUS</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No hay registros para este periodo.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                            <?php if (in_array($tipo_reporte, ['actividad', 'inventario', 'incidencias'])): 
                                $mod = $log['modulo_afectado'];
                                $badgeBg = '#eff6ff'; $badgeColor = '#2563eb';
                                if($mod == 'ACTIVOS' || $mod == 'INVENTARIO') { $badgeBg = '#fff7ed'; $badgeColor = '#ea580c'; }
                                elseif($mod == 'RESERVAS') { $badgeBg = '#f0fdf4'; $badgeColor = '#16a34a'; }
                                elseif($mod == 'USUARIOS') { $badgeBg = '#f5f3ff'; $badgeColor = '#7c3aed'; }
                                elseif($mod == 'INCIDENCIAS') { $badgeBg = '#fef2f2'; $badgeColor = '#dc2626'; }
                            ?>
                                <td>
                                    <div style="font-weight: 600; color: #0f172a;"><?php echo date('d M Y', strtotime($log['fecha_hora'])); ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo date('H:i A', strtotime($log['fecha_hora'])); ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; color: #475569;">
                                            <?php echo strtoupper(substr($log['usuario_nombre'] ?? 'S', 0, 1)); ?>
                                        </div>
                                        <b><?php echo htmlspecialchars($log['usuario_nombre'] ?? 'SISTEMA'); ?></b>
                                    </div>
                                </td>
                                <td><span style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px;"><?php echo htmlspecialchars($mod); ?></span></td>
                                <td style="color: #334155; line-height: 1.5;"><?php echo htmlspecialchars($log['accion']); ?></td>
                            <?php elseif ($tipo_reporte == 'asistencia'): ?>
                                <td><?php echo date('d/m/Y', strtotime($log['fecha_uso'])); ?></td>
                                <td><?php echo htmlspecialchars($log['hora_ent'] . ' a ' . $log['hora_sal']); ?></td>
                                <td><b><?php echo htmlspecialchars($log['espacio']); ?></b> <br><small><?php echo htmlspecialchars($log['edificio']); ?></small></td>
                                <td><?php echo htmlspecialchars($log['responsable']); ?></td>
                                <td><b style="color: var(--primary);"><?php echo (int)$log['num_alumnos']; ?></b> alumnos</td>
                            <?php elseif ($tipo_reporte == 'aulas_top'): ?>
                                <td><b><?php echo htmlspecialchars($log['nombre_numero']); ?></b><br><small><?php echo htmlspecialchars($log['tipo']); ?></small></td>
                                <td><?php echo htmlspecialchars($log['edificio']); ?></td>
                                <td><b><?php echo (int)$log['total_reservas']; ?></b></td>
                                <td style="color: var(--primary);"><b><?php echo (int)$log['total_asistencia']; ?></b> personas</td>
                            <?php elseif ($tipo_reporte == 'uso_edificio'): ?>
                                <td><b><?php echo htmlspecialchars($log['edificio'] ?: 'Sin Edificio'); ?></b></td>
                                <td><?php echo (int)$log['total_espacios']; ?></td>
                                <td><b><?php echo (int)$log['total_reservas']; ?></b></td>
                                <td style="color: var(--primary);"><b><?php echo (int)$log['total_asistencia']; ?></b> personas</td>
                            <?php elseif ($tipo_reporte == 'asistencia_usuario'): ?>
                                <td><b><?php echo htmlspecialchars($log['nombre']); ?></b></td>
                                <td><?php echo htmlspecialchars($log['rol']); ?></td>
                                <td><b><?php echo (int)$log['total_reservas']; ?></b></td>
                                <td style="color: var(--primary);"><b><?php echo (int)$log['total_asistencia']; ?></b> personas</td>
                            <?php elseif ($tipo_reporte == 'prestamos'): ?>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['fecha_pres'])); ?></td>
                                <td><b><?php echo htmlspecialchars($log['usuario_nombre']); ?></b></td>
                                <td><?php echo htmlspecialchars($log['activo_tipo'] . ' - ' . $log['activo_marca']); ?><br><small><?php echo htmlspecialchars($log['activo_inv']); ?></small></td>
                                <td>
                                    <?php if ($log['estatus'] == 'Activo'): ?>
                                        <span style="color: #ea580c; background: #ffedd5; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">En Curso</span>
                                    <?php else: ?>
                                        <span style="color: #16a34a; background: #dcfce3; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">Finalizado</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Dynamic Filters Engine
document.addEventListener('DOMContentLoaded', () => {
    const reportType = document.getElementById('tipoReporte');
    const updateFilters = () => {
        // Hide all dynamically
        document.querySelectorAll('.filter-group[class*="fg-"]').forEach(el => el.style.display = 'none');
        
        const val = reportType.value;
        if(val === 'actividad') {
            document.querySelector('.fg-usuario').style.display = 'flex';
            document.querySelector('.fg-modulo').style.display = 'flex';
        } else if(val === 'asistencia') {
            document.querySelector('.fg-edificio').style.display = 'flex';
        } else if(val === 'aulas_top') {
            document.querySelector('.fg-edificio').style.display = 'flex';
            document.querySelector('.fg-metrica').style.display = 'flex';
            document.querySelector('.fg-limit').style.display = 'flex';
        } else if(val === 'asistencia_usuario') {
            document.querySelector('.fg-usuario').style.display = 'flex';
        } else if(val === 'prestamos') {
            document.querySelector('.fg-usuario').style.display = 'flex';
            document.querySelector('.fg-activo').style.display = 'flex';
        }
    };
    reportType.addEventListener('change', updateFilters);
    updateFilters(); // Run on load
});

// Preset Dates
function setPreset(preset) {
    const dIni = document.getElementById('fecha_inicio');
    const dFin = document.getElementById('fecha_fin');
    const today = new Date();
    
    let start = new Date();
    let end = new Date();

    if(preset === 'hoy') {
        // do nothing, start and end are today
    } else if (preset === 'semana') {
        const day = today.getDay();
        const diff = today.getDate() - day + (day == 0 ? -6:1);
        start.setDate(diff);
    } else if (preset === 'mes') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
    } else if (preset === 'cuatrimestre') {
        const m = today.getMonth(); // 0-11
        if (m < 4) {
            // Ene-Abr (0-3)
            start = new Date(today.getFullYear(), 0, 1);
            end = new Date(today.getFullYear(), 3, 30);
        } else if (m < 8) {
            // May-Ago (4-7)
            start = new Date(today.getFullYear(), 4, 1);
            end = new Date(today.getFullYear(), 7, 31);
        } else {
            // Sep-Dic (8-11)
            start = new Date(today.getFullYear(), 8, 1);
            end = new Date(today.getFullYear(), 11, 31);
        }
    } else if (preset === '30dias') {
        start.setDate(today.getDate() - 30);
    } else if (preset === 'custom') {
        dIni.value = ''; dFin.value = ''; return;
    }

    dIni.value = start.toISOString().split('T')[0];
    dFin.value = end.toISOString().split('T')[0];
}
function exportToExcel() {
    var table = document.getElementById("auditTable");
    if (!table) {
        showToast("Error: No se encontró la tabla", "error");
        return;
    }
    var wb = XLSX.utils.table_to_book(table, {sheet: "Auditoria"});
    var reportType = document.getElementById('tipoReporte').options[document.getElementById('tipoReporte').selectedIndex].text;
    var filename = "Reporte_" + reportType.replace(/ /g, "_") + ".xlsx";
    XLSX.writeFile(wb, filename);
    if(typeof showToast === 'function') {
        showToast("Archivo Excel generado con éxito", "success");
    }
}
</script>

<?php include 'footer.php'; ?>
