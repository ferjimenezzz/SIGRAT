<?php
/**
 * @file auditoria.php
 * @summary Módulo de Auditoría y Trazabilidad con Filtros y Exportación.
 * @description Permite visualizar y filtrar los movimientos del sistema, con opción de reporte.
 */
require_once 'seguridad.php';
require_once '../backend/controllers/AuditController.php';
require_once '../backend/config/Database.php';

$auditController = new Controllers\AuditController();
$db = Config\Database::getConnection();

// Capturar filtros básicos
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;
$us_id = $_GET['us_id'] ?? null;
$modulo = $_GET['modulo'] ?? null;

// Capturar filtros extra
$extra_filters = [
    'buscar_usuario' => $_GET['buscar_usuario'] ?? null,
    'edificio' => $_GET['edificio'] ?? null,
    'estado' => $_GET['estado'] ?? null,
    'incluir_prestamos' => $_GET['incluir_prestamos'] ?? null,
    'incluir_transferencias' => $_GET['incluir_transferencias'] ?? null,
];

$logs = $auditController->getFiltered($fecha_inicio, $fecha_fin, $us_id, $modulo, $extra_filters);
$stats = $auditController->getAuditStats();
$usuarios = $db->query("SELECT us_id, nombre FROM USUARIO ORDER BY nombre")->fetchAll();

include 'header.php';
?>

<style>
    /* Diseño Sistema (Tailwind-like variables) */
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
        --font-sans: 'Inter', system-ui, sans-serif;
    }

    .audit-container {
        font-family: var(--font-sans);
        display: flex;
        flex-direction: column;
        gap: 24px;
        color: var(--text-main);
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 40px;
    }

    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .header-section h1 {
        font-size: 28px;
        font-weight: 800;
        margin: 0 0 8px 0;
        letter-spacing: -0.5px;
    }

    .header-section p {
        color: var(--text-muted);
        font-size: 14px;
        margin: 0;
    }



    .card {
        background: white;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 24px;
        border: 1px solid var(--border);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .filter-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 8px;
    }

    .filter-group input, .filter-group select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        color: var(--text-main);
        background: #fff;
        outline: none;
        transition: border-color 0.2s;
    }

    .filter-group input:focus, .filter-group select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .btn-more-filters {
        background: var(--primary-dark);
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        width: max-content;
    }

    .more-filters-section {
        display: none;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }

    .more-filters-section.active {
        display: block;
    }

    .filters-actions {
        display: flex;
        justify-content: flex-end;
        gap: 16px;
        margin-top: 24px;
    }

    .btn-clear {
        padding: 10px 20px;
        background: var(--secondary);
        color: var(--text-muted);
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-apply {
        padding: 10px 24px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
    .stat-icon.green { background: #f0fdf4; color: #22c55e; }
    .stat-icon.orange { background: #fff7ed; color: #f97316; }
    .stat-icon.purple { background: #faf5ff; color: #a855f7; }

    .stat-info h4 {
        margin: 0;
        font-size: 13px;
        color: var(--text-main);
        font-weight: 700;
    }

    .stat-info p {
        margin: 4px 0 0 0;
        font-size: 12px;
        color: var(--text-muted);
    }

    .report-section {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .report-include {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        max-width: 400px;
    }

    .report-include h3 {
        margin: 0 0 16px 0;
        font-size: 16px;
    }

    .report-include ul {
        list-style: none;
        padding: 0;
        margin: 0;
        font-size: 13px;
        color: var(--text-muted);
    }

    .report-include li {
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .report-include li i {
        color: var(--primary);
        width: 16px;
    }

    .btn-download {
        background: var(--primary-dark);
        color: white;
        padding: 14px 28px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .table-card {
        padding: 0;
        overflow-x: auto;
    }

    .table-card table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .table-card th {
        padding: 16px 24px;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--bg-main);
        border-bottom: 1px solid var(--border);
    }

    .table-card td {
        padding: 16px 24px;
        font-size: 14px;
        border-bottom: 1px solid var(--secondary);
        color: var(--text-main);
    }

    .badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        background: #eff6ff;
        color: #2563eb;
        text-transform: uppercase;
        display: inline-block;
    }
</style>

<div class="audit-container">

    <!-- Header -->
    <div class="header-section">
        <div>
            <h1>Bitácora de Auditoría</h1>
            <p>Trazabilidad completa de acciones y movimientos del sistema.</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card">
        <h3 style="margin-top:0; margin-bottom:20px; font-size:18px;">Filtros del Reporte</h3>
        
        <form method="GET" action="auditoria.php">
            <!-- Main Filters Row -->
            <div class="filters-grid" style="grid-template-columns: 1fr 1fr auto;">
                <div class="filter-group">
                    <label>Edificio</label>
                    <select name="edificio">
                        <option value="Todos">Todos</option>
                        <option value="CIC" <?php echo ($extra_filters['edificio'] ?? '') === 'CIC' ? 'selected' : ''; ?>>CIC</option>
                        <option value="Edificio 1" <?php echo ($extra_filters['edificio'] ?? '') === 'Edificio 1' ? 'selected' : ''; ?>>Edificio Principal</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Módulo</label>
                    <select name="modulo">
                        <option value="">Todos los movimientos</option>
                        <option value="USUARIOS" <?php echo $modulo == 'USUARIOS' ? 'selected' : ''; ?>>Usuarios</option>
                        <option value="RESERVAS" <?php echo $modulo == 'RESERVAS' ? 'selected' : ''; ?>>Reservas</option>
                        <option value="ACTIVOS" <?php echo $modulo == 'ACTIVOS' ? 'selected' : ''; ?>>Activos</option>
                        <option value="SEGURIDAD" <?php echo $modulo == 'SEGURIDAD' ? 'selected' : ''; ?>>Seguridad</option>
                        <option value="PRESTAMOS" <?php echo $modulo == 'PRESTAMOS' ? 'selected' : ''; ?>>Préstamos</option>
                    </select>
                </div>
                <?php $isFiltersActive = array_filter($extra_filters) || $fecha_inicio || $fecha_fin; ?>
                <div class="filter-group">
                    <button type="button" class="btn-more-filters" id="toggleFiltersBtn">
                        <i data-lucide="settings"></i> Más filtros <i data-lucide="<?php echo $isFiltersActive ? 'chevron-up' : 'chevron-down'; ?>" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- More Filters (Collapsible) -->
            <div class="more-filters-section <?php echo $isFiltersActive ? 'active' : ''; ?>" id="moreFiltersSection">
                <div class="filters-grid" style="margin-bottom: 20px;">
                    <div class="filter-group">
                        <label>Desde Fecha</label>
                        <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Hasta Fecha</label>
                        <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Buscar Usuario</label>
                        <input type="text" name="buscar_usuario" placeholder="Nombre de usuario..." value="<?php echo htmlspecialchars($extra_filters['buscar_usuario'] ?? ''); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Estado Acción</label>
                        <select name="estado">
                            <option value="Todos">Todos</option>
                            <option value="Aprobado" <?php echo ($extra_filters['estado'] ?? '') === 'Aprobado' ? 'selected' : ''; ?>>Exitoso / Aprobado</option>
                            <option value="Rechazado" <?php echo ($extra_filters['estado'] ?? '') === 'Rechazado' ? 'selected' : ''; ?>>Rechazado / Error</option>
                        </select>
                    </div>
                </div>

                <div class="filters-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="filter-group">
                        <label>Incluir préstamos</label>
                        <select name="incluir_prestamos">
                            <option value="Todos">Todos</option>
                            <option value="Si" <?php echo ($extra_filters['incluir_prestamos'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                            <option value="No" <?php echo ($extra_filters['incluir_prestamos'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Incluir transferencias internas</label>
                        <select name="incluir_transferencias">
                            <option value="Todos">Todos</option>
                            <option value="Si" <?php echo ($extra_filters['incluir_transferencias'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                            <option value="No" <?php echo ($extra_filters['incluir_transferencias'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="filters-actions">
                <a href="auditoria.php" class="btn-clear"><i data-lucide="refresh-cw"></i> Limpiar filtros</a>
                <button type="submit" class="btn-apply"><i data-lucide="filter"></i> Aplicar filtros</button>
            </div>
        </form>
    </div>

    <!-- KPIs -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i data-lucide="users"></i>
            </div>
            <div class="stat-info">
                <h4>Total de registros</h4>
                <p>Histórico: <strong><?php echo number_format($stats['total']); ?></strong></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i data-lucide="log-in"></i>
            </div>
            <div class="stat-info">
                <h4>Módulo más activo</h4>
                <p><strong><?php echo htmlspecialchars($stats['modulo_activo']); ?></strong></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i data-lucide="log-out"></i>
            </div>
            <div class="stat-info">
                <h4>Usuarios Activos</h4>
                <p><strong><?php echo number_format($stats['usuarios_activos']); ?></strong> usuarios únicos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i data-lucide="file-text"></i>
            </div>
            <div class="stat-info">
                <h4>Movimientos Hoy</h4>
                <p><strong><?php echo number_format($stats['hoy']); ?></strong> acciones</p>
            </div>
        </div>
    </div>

    <!-- Reporte Exportar y Tabla -->
    
    <div class="report-section">
        <div class="report-include">
            <h3>Qué incluye este reporte?</h3>
            <ul>
                <li><i data-lucide="check-circle-2"></i> Resumen de movimientos en el periodo seleccionado</li>
                <li><i data-lucide="check-circle-2"></i> Detalles de operaciones por usuario</li>
                <li><i data-lucide="check-circle-2"></i> Estadísticas por módulo afectado</li>
                <li style="margin-top: 12px; background: #eff6ff; padding: 10px; border-radius: 6px; color: #1e40af;">
                    <i data-lucide="info" style="color:#1e40af;"></i> El reporte se generará en formato PDF y podrá descargarse o imprimirse.
                </li>
            </ul>
        </div>
        
        <div>
            <a href="../backend/reports/audit_pdf.php?<?php echo $_SERVER['QUERY_STRING']; ?>" target="_blank" class="btn-download">
                <i data-lucide="download"></i> Descargar Reporte
            </a>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card table-card">
        <table>
            <thead>
                <tr>
                    <th>FECHA Y HORA</th>
                    <th>USUARIO</th>
                    <th>MÓDULO</th>
                    <th>ACCIÓN REALIZADA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" style="padding: 40px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            No se encontraron registros con los filtros seleccionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-size: 13px; font-weight: 600; color: var(--text-muted);">
                            <?php echo date('d/m/Y H:i', strtotime($log['fecha_hora'])); ?>
                        </td>
                        <td style="font-weight: 700;">
                            <?php echo htmlspecialchars($log['usuario_nombre'] ?? 'SISTEMA'); ?>
                        </td>
                        <td>
                            <span class="badge">
                                <?php echo htmlspecialchars($log['modulo_afectado']); ?>
                            </span>
                        </td>
                        <td style="color: #475569;">
                            <?php echo htmlspecialchars($log['accion']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
    // Lógica para colapsar / expandir filtros
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('toggleFiltersBtn');
        const section = document.getElementById('moreFiltersSection');
        const icon = document.getElementById('toggleIcon');
        
        btn.addEventListener('click', function() {
            section.classList.toggle('active');
            if (section.classList.contains('active')) {
                icon.setAttribute('data-lucide', 'chevron-up');
            } else {
                icon.setAttribute('data-lucide', 'chevron-down');
            }
            // re-render lucide icons
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    });
</script>

<?php include 'footer.php'; ?>
