<?php
/**
 * @file users_pdf.php
 * @summary Generador de reporte de usuarios en formato imprimible/PDF.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['us_id'])) die("Acceso denegado.");

require_once __DIR__ . '/../config/Database.php';

$db = Config\Database::getConnection();

// Consulta principal: usuarios con rol
$usersStmt = $db->query("
    SELECT u.us_id, u.nombre, u.apellido, u.correo, u.empresa, u.rfc_matricula,
           u.estatus, u.ultima_conexion, r.nombre as rol_nombre
    FROM USUARIO u
    LEFT JOIN ROLES r ON u.rol_id = r.rol_id
    ORDER BY u.estatus ASC, u.nombre ASC
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Roles
$rolesStmt = $db->query("SELECT r.*, (SELECT COUNT(*) FROM USUARIO u WHERE u.rol_id = r.rol_id) as total_usuarios FROM ROLES r ORDER BY r.nombre ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsers   = count($users);
$activos      = count(array_filter($users, fn($u) => $u['estatus'] === 'Activo'));
$inactivos    = $totalUsers - $activos;
$totalRoles   = count($roles);
?>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Usuarios - SIGRAT</title>
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
        .stat-card.activo .num { color: #16a34a; }
        .stat-card.inactivo .num { color: #dc2626; }

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

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .badge-activo   { background: #dcfce7; color: #15803d; }
        .badge-inactivo { background: #fee2e2; color: #b91c1c; }
        .badge-rol      { background: #eff6ff; color: #2563eb; }

        .user-name  { font-weight: 700; color: #1e293b; }
        .user-email { font-size: 10px; color: #64748b; margin-top: 2px; }
        .user-org   { font-size: 10px; color: #94a3b8; margin-top: 2px; }

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
<body onload="window.print()">
    <div class="header">
        <div>
            <div class="logo">SIGRAT<span>Control Integral</span></div>
        </div>
        <div class="report-info">
            <h1>Reporte de Usuarios</h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?php echo $totalUsers; ?></div>
            <div class="label">Total Usuarios</div>
        </div>
        <div class="stat-card activo">
            <div class="num"><?php echo $activos; ?></div>
            <div class="label">Activos</div>
        </div>
        <div class="stat-card inactivo">
            <div class="num"><?php echo $inactivos; ?></div>
            <div class="label">Inactivos</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $totalRoles; ?></div>
            <div class="label">Roles</div>
        </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="section-title">👥 Usuarios del Sistema</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Última Conexión</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
            <tr>
                <td colspan="5" style="text-align:center; padding: 30px; color: #94a3b8;">No hay usuarios registrados.</td>
            </tr>
            <?php else: ?>
            <?php $i = 1; foreach ($users as $u): ?>
            <tr>
                <td style="color:#94a3b8; font-size:11px;"><?php echo $i++; ?></td>
                <td>
                    <div class="user-name"><?php echo htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido'])); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($u['correo']); ?></div>
                    <?php $org = $u['empresa'] ?: $u['rfc_matricula']; if ($org): ?>
                        <div class="user-org"><?php echo htmlspecialchars($org); ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-rol"><?php echo htmlspecialchars($u['rol_nombre'] ?? 'Sin rol'); ?></span>
                </td>
                <td>
                    <span class="badge <?php echo $u['estatus'] === 'Activo' ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <?php echo htmlspecialchars($u['estatus']); ?>
                    </span>
                </td>
                <td style="color: #64748b; font-size: 11px;">
                    <?php echo $u['ultima_conexion'] ? htmlspecialchars($u['ultima_conexion']) : '<span style="color:#94a3b8;">Nunca</span>'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Tabla de Roles -->
    <div class="section-title" style="margin-top: 36px;">🔑 Roles del Sistema</div>
    <table>
        <thead>
            <tr>
                <th>Rol</th>
                <th>Descripción</th>
                <th>Usuarios Asignados</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($roles)): ?>
            <tr>
                <td colspan="3" style="text-align:center; padding: 30px; color: #94a3b8;">No hay roles definidos.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($roles as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['nombre']); ?></strong></td>
                <td style="color:#475569;"><?php echo htmlspecialchars($r['descripcion'] ?? '—'); ?></td>
                <td style="text-align:center; font-weight: 700; color: #2563eb;"><?php echo (int)$r['total_usuarios']; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Reporte de Usuarios · Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT) · <?php echo date('Y'); ?>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
        <button class="btn-close" onclick="window.close()">Cerrar</button>
    </div>
</body>
</html>
