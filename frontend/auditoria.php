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


// Capturar filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;
$us_id = $_GET['us_id'] ?? null;
$modulo = $_GET['modulo'] ?? null;

$logs = $auditController->getFiltered($fecha_inicio, $fecha_fin, $us_id, $modulo);
$usuarios = $db->query("SELECT us_id, nombre FROM USUARIO ORDER BY nombre")->fetchAll();

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <!-- Encabezado con Exportación -->
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Bitácora de Auditoría</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Trazabilidad completa de acciones y movimientos del sistema.</p>
        </div>
        <a href="../backend/reports/audit_pdf.php?<?php echo $_SERVER['QUERY_STRING']; ?>" target="_blank" class="btn-primary" style="background: #ef4444; display: flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-weight: 800; color: white;">
            <i data-lucide="file-text"></i> EXPORTAR PDF
        </a>
    </header>

    <!-- Barra de Filtros -->
    <div class="card" style="padding: 24px;">
        <form method="GET" style="display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 16px; align-items: flex-end;">
            <div>
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Desde</label>
                <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control">
            </div>
            <div>
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Hasta</label>
                <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control">
            </div>
            <div>
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Usuario</label>
                <select name="us_id" class="form-control">
                    <option value="">Todos los usuarios</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?php echo $u['us_id']; ?>" <?php echo $us_id == $u['us_id'] ? 'selected' : ''; ?>>
                            <?php echo $u['nombre']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Módulo</label>
                <select name="modulo" class="form-control">
                    <option value="">Todos</option>
                    <option value="USUARIOS" <?php echo $modulo == 'USUARIOS' ? 'selected' : ''; ?>>USUARIOS</option>
                    <option value="RESERVAS" <?php echo $modulo == 'RESERVAS' ? 'selected' : ''; ?>>RESERVAS</option>
                    <option value="ACTIVOS" <?php echo $modulo == 'ACTIVOS' ? 'selected' : ''; ?>>ACTIVOS</option>
                    <option value="SEGURIDAD" <?php echo $modulo == 'SEGURIDAD' ? 'selected' : ''; ?>>SEGURIDAD</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary" style="height: 42px;">
                    <i data-lucide="filter"></i> FILTRAR
                </button>
            </div>
            <div>
                <a href="auditoria.php" style="display: flex; align-items: center; height: 42px; color: #94a3b8; text-decoration: none; font-size: 12px; font-weight: 700;">LIMPIAR</a>
            </div>
        </form>
    </div>

    <!-- Tabla de Resultados -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Fecha y Hora</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Usuario</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Módulo</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Acción Realizada</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" style="padding: 40px; text-align: center; color: #94a3b8; font-weight: 600;">No se encontraron registros con los filtros seleccionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px; font-size: 13px; font-weight: 700; color: #64748b;"><?php echo $log['fecha_hora']; ?></td>
                        <td style="padding: 16px 24px; font-size: 14px; font-weight: 800; color: #1e293b;"><?php echo $log['usuario_nombre'] ?? 'SISTEMA'; ?></td>
                        <td style="padding: 16px 24px;">
                            <span style="background: #eff6ff; color: #2563eb; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 900;">
                                <?php echo $log['modulo_afectado']; ?>
                            </span>
                        </td>
                        <td style="padding: 16px 24px; font-size: 13px; color: #475569; font-weight: 500;"><?php echo $log['accion']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
