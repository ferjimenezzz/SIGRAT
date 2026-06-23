<?php
/**
 * @file usuarios.php
 * @summary Gestión Integral de Usuarios, Invitaciones y Roles del Sistema.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/InviteController.php';
require_once '../backend/controllers/AuthController.php';

use Controllers\AuthController;

$db = Config\Database::getConnection();
$inviteController = new Controllers\InviteController();

// 1. Manejar Registro / Edición de Usuario Interno, o Exportaciones
if (isset($_GET['action'])) {
    if (strpos($_GET['action'], 'export') === 0) {
        header('Content-Type: text/csv; charset=utf-8');
        echo "\xEF\xBB\xBF"; // BOM
        $output = fopen('php://output', 'w');

        if ($_GET['action'] === 'export_usuarios') {
            header('Content-Disposition: attachment; filename=usuarios_export_' . date('Y-m-d') . '.csv');
            fputcsv($output, ['Nombre', 'Apellido', 'Correo', 'Empresa/Matricula', 'Rol', 'Estado', 'Ultima Conexion']);
            $exportUsers = $db->query("SELECT u.*, r.nombre as rol_nombre FROM usuario u LEFT JOIN roles r ON u.rol_id = r.rol_id")->fetchAll();
            foreach ($exportUsers as $row) {
                fputcsv($output, [$row['nombre'], $row['apellido'], $row['correo'], $row['empresa'] ?: $row['rfc_matricula'], $row['rol_nombre'], $row['estatus'], $row['ultima_conexion'] ?: 'Nunca']);
            }
        } elseif ($_GET['action'] === 'export_roles') {
            header('Content-Disposition: attachment; filename=roles_export_' . date('Y-m-d') . '.csv');
            fputcsv($output, ['ID', 'Nombre', 'Descripcion', 'Permisos']);
            $exportRoles = $db->query("SELECT * FROM ROLES")->fetchAll();
            foreach ($exportRoles as $row) {
                fputcsv($output, [$row['rol_id'], $row['nombre'], $row['descripcion'], $row['permisos']]);
            }
        } elseif ($_GET['action'] === 'export_invitaciones') {
            header('Content-Disposition: attachment; filename=invitaciones_export_' . date('Y-m-d') . '.csv');
            fputcsv($output, ['Invitado', 'Correo', 'Codigo', 'Estatus', 'Anfitrion']);
            $exportInvites = $inviteController->getAllActive();
            foreach ($exportInvites as $row) {
                fputcsv($output, [$row['nombre'], $row['correo'], $row['codigo_acceso'], $row['estatus'], $row['anfitrion_nombre']]);
            }
        } else {
            // Default export to avoid errors
            header('Content-Disposition: attachment; filename=export_' . date('Y-m-d') . '.csv');
        }
        
        fclose($output);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_user') {
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido'] ?? '';
        $correo = $_POST['correo'];
        $empresa = $_POST['empresa'] ?? '';
        $rfc = $_POST['rfc_matricula'] ?? '';
        $rol_id = $_POST['rol_id'];
        $genero = $_POST['genero'] ?? 'Masculino';
        $us_id = $_POST['us_id'] ?? null;

        if ($us_id) {
            $stmt = $db->prepare("UPDATE usuario SET nombre=?, apellido=?, correo=?, empresa=?, rfc_matricula=?, rol_id=?, genero=? WHERE us_id=?");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rfc, $rol_id, $genero, $us_id]);
        } else {
            $pass = AuthController::hashPassword('123456');
            $stmt = $db->prepare("INSERT INTO usuario (nombre, apellido, correo, empresa, rfc_matricula, rol_id, genero, contrasena, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Activo')");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rfc, $rol_id, $genero, $pass]);
        }
        header("Location: usuarios.php?tab=usuarios&success=1");
        exit();
    } elseif ($_POST['action'] === 'save_role') {
        $nombre = $_POST['nombre_rol'];
        $descripcion = $_POST['descripcion_rol'];
        $permisos = $_POST['permisos'] ?? [];
        $rol_id = $_POST['rol_id'] ?? null;

        if ($rol_id) {
            $stmt = $db->prepare("UPDATE ROLES SET nombre = ?, descripcion = ?, permisos = ? WHERE rol_id = ?");
            $stmt->execute([$nombre, $descripcion, json_encode($permisos), $rol_id]);
        } else {
            $stmt = $db->prepare("INSERT INTO ROLES (nombre, descripcion, permisos) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, json_encode($permisos)]);
        }
        header("Location: usuarios.php?tab=roles&success=1");
        exit();
    } elseif ($_POST['action'] === 'generate_code') {
        header('Content-Type: application/json');
        try {
            $res = $inviteController->generate($_POST['nombre_visita'], $_POST['correo_visita'], $_SESSION['us_id']);
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

// 2. Eliminar Usuario o Rol
if (isset($_GET['delete_user'])) {
    $stmt = $db->prepare("UPDATE usuario SET estatus = 'Inactivo' WHERE us_id = ?");
    $stmt->execute([$_GET['delete_user']]);
    header("Location: usuarios.php?tab=usuarios&deleted=1");
    exit();
}
if (isset($_GET['delete_rol'])) {
    $stmt = $db->prepare("DELETE FROM ROLES WHERE rol_id = ?");
    $stmt->execute([$_GET['delete_rol']]);
    header("Location: usuarios.php?tab=roles&deleted=1");
    exit();
}

// NUEVAS CONSULTAS PARA ESTADÍSTICAS REALES
$stats = $db->query("
    SELECT 
        COUNT(*) as total_usuarios,
        SUM(CASE WHEN estatus = 'Activo' THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN estatus = 'Inactivo' THEN 1 ELSE 0 END) as inactivos,
        SUM(CASE WHEN r.nombre = 'Administrador' OR r.nombre = 'Super Administrador' THEN 1 ELSE 0 END) as administradores,
        SUM(CASE WHEN EXTRACT(MONTH FROM u.fecha_creacion) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM u.fecha_creacion) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 ELSE 0 END) as nuevos_mes
    FROM usuario u
    LEFT JOIN roles r ON u.rol_id = r.rol_id
")->fetch();

$total_usuarios = $stats['total_usuarios'] ?? 0;
$activos = $stats['activos'] ?? 0;
$inactivos = $stats['inactivos'] ?? 0;
$admins = $stats['administradores'] ?? 0;
$nuevos_mes = $stats['nuevos_mes'] ?? 0;
$activos_percent = $total_usuarios > 0 ? round(($activos / $total_usuarios) * 100, 1) : 0;
$admins_percent = $total_usuarios > 0 ? round(($admins / $total_usuarios) * 100, 1) : 0;

$users = $db->query("SELECT u.*, r.nombre as rol_nombre FROM usuario u LEFT JOIN roles r ON u.rol_id = r.rol_id")->fetchAll();
$roles = $db->query("SELECT * FROM ROLES ORDER BY rol_id DESC")->fetchAll();
$invites = $inviteController->getAllActive();

// ESTADÍSTICAS DE INVITACIONES
$invStats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM visita WHERE codigo_acceso IS NOT NULL) as total_generados,
        (SELECT COUNT(DISTINCT vis_id) FROM reserva WHERE vis_id IS NOT NULL) as total_ocupados,
        (SELECT MAX(usos) FROM (SELECT COUNT(*) as usos FROM reserva WHERE vis_id IS NOT NULL GROUP BY vis_id) as conteo) as max_usos
")->fetch();

$inv_total_generados = $invStats['total_generados'] ?? 0;
$inv_total_ocupados = $invStats['total_ocupados'] ?? 0;
$inv_max_usos = $invStats['max_usos'] ?? 0;
$inv_tasa_uso = $inv_total_generados > 0 ? round(($inv_total_ocupados / $inv_total_generados) * 100, 1) : 0;

$modulos = [
    'Inventario' => ['read', 'create', 'update', 'delete'],
    'Espacios' => ['read', 'create', 'update', 'delete'],
    'Reservas' => ['read', 'create', 'update', 'delete'],
    'Visitas' => ['read', 'create', 'update', 'delete'],
    'RFID' => ['read', 'create', 'update', 'delete'],
    'Usuarios' => ['read', 'create', 'update', 'delete'],
    'Auditorias' => ['read', 'create', 'update', 'delete']
];

function getRelativeTime($timestamp) {
    if (!$timestamp) return 'Nunca';
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    if ($diff < 60) return "Hace unos segundos";
    if ($diff < 3600) return "Hace " . floor($diff / 60) . " min";
    if ($diff < 86400) {
        if (date('Y-m-d') == date('Y-m-d', $time)) return "Hoy, " . date('h:i A', $time);
        return "Hace " . floor($diff / 3600) . " horas";
    }
    if ($diff < 172800 && date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) return "Ayer, " . date('h:i A', $time);
    return "Hace " . floor($diff / 86400) . " días";
}

include 'header.php';
$tab = $_GET['tab'] ?? 'usuarios';
?>

<div style="display: flex; flex-direction: column; gap: 24px;">
    <!-- Encabezado con título y botones -->
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Gestión de Usuarios</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Administra usuarios y permisos del sistema</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <button onclick="window.open('../backend/reports/users_pdf.php', '_blank')" id="btn-export" class="btn-secondary" style="border-radius: 8px; font-size: 12px; font-weight: 600; background: white; padding: 10px 16px; border: 1px solid #e2e8f0; color: #1e293b; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i data-lucide="file-text" style="width: 16px;"></i> Exportar PDF</button>
            <button onclick="openUserModal()" id="btn-action-user" class="btn-primary" style="background: #2563eb; border-radius: 8px; font-size: 12px; font-weight: 600; padding: 10px 16px; color: white; border: none; cursor: pointer; display: <?php echo $tab === 'usuarios' ? 'flex' : 'none'; ?>; align-items: center; gap: 6px;"><i data-lucide="plus" style="width: 16px;"></i> Nuevo usuario</button>
            <button onclick="openRoleModal()" id="btn-action-role" class="btn-primary" style="background: #2563eb; border-radius: 8px; font-size: 12px; font-weight: 600; padding: 10px 16px; color: white; border: none; cursor: pointer; display: <?php echo $tab === 'roles' ? 'flex' : 'none'; ?>; align-items: center; gap: 6px;"><i data-lucide="plus" style="width: 16px;"></i> Nuevo rol</button>
        </div>
    </header>

    <!-- Barra de Búsqueda, Filtros y Pestañas -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
            <div style="position: relative; width: 300px;">
                <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; color: #94a3b8;"></i>
                <input type="text" id="searchInput" placeholder="Buscar usuario, email..." style="width: 100%; padding: 10px 10px 10px 36px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 500; outline: none;">
            </div>
            
            <div style="display: flex; gap: 12px; margin-left: 20px;">
                <select id="roleFilter" style="padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #475569; background: white; outline: none; cursor: pointer;">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['nombre']); ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="statusFilter" style="padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #475569; background: white; outline: none; cursor: pointer;">
                    <option value="">Estado</option>
                    <option value="Activo">Activo</option>
                    <option value="Inactivo">Inactivo</option>
                </select>
            </div>
            
            <button id="btnClearFilters" style="display: flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #475569; background: white; cursor: pointer;">
                <i data-lucide="filter" style="width: 14px;"></i> Limpiar Filtros
            </button>
        </div>
        
        <div style="display: flex; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 10px; border: 1px solid #e2e8f0;">
            <button onclick="switchTab('usuarios')" id="btn-usuarios" class="btn-tab <?php echo $tab === 'usuarios' ? 'active' : ''; ?>">USUARIOS</button>
            <button onclick="switchTab('roles')" id="btn-roles" class="btn-tab <?php echo $tab === 'roles' ? 'active' : ''; ?>">ROLES</button>
            <button onclick="switchTab('invitaciones')" id="btn-invitaciones" class="btn-tab <?php echo $tab === 'invitaciones' ? 'active' : ''; ?>">INVITACIONES</button>
        </div>
    </div>

    <!-- Tarjetas Estadísticas -->
    <div id="stats-usuarios" style="display: <?php echo ($tab === 'usuarios' || $tab === 'roles') ? 'grid' : 'none'; ?>; grid-template-columns: repeat(4, 1fr); gap: 20px;">
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Total usuarios</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $total_usuarios; ?></div>
            <div style="display: inline-block; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">+<?php echo $nuevos_mes; ?> este mes</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Activos Ahora</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $activos; ?></div>
            <div style="display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;"><?php echo $activos_percent; ?>%</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Administradores</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $admins; ?></div>
            <div style="display: inline-block; background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;"><?php echo $admins_percent; ?>%</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Inactivos</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $inactivos; ?></div>
            <div style="display: inline-block; background: #fce7f3; color: #be185d; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Revisar</div>
        </div>
    </div>

    <!-- Estadísticas Invitaciones -->
    <div id="stats-invitaciones" style="display: <?php echo $tab === 'invitaciones' ? 'grid' : 'none'; ?>; grid-template-columns: repeat(4, 1fr); gap: 20px;">
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Códigos Generados</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $inv_total_generados; ?></div>
            <div style="display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Total Histórico</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Códigos Ocupados</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $inv_total_ocupados; ?></div>
            <div style="display: inline-block; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Utilizados exitosamente</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Tasa de Uso</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $inv_tasa_uso; ?>%</div>
            <div style="display: inline-block; background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Conversión</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Récord de Uso</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $inv_max_usos; ?></div>
            <div style="display: inline-block; background: #fce7f3; color: #be185d; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Más ocupado</div>
        </div>
    </div>

    <!-- Pestaña Usuarios -->
    <div id="tab-usuarios" class="card" style="display: <?php echo $tab === 'usuarios' ? 'block' : 'none'; ?>; padding: 0; overflow: hidden; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;" id="usersTable">
            <thead style="border-bottom: 1px solid #e2e8f0;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Usuario</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Rol</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Estado</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Última Conexión</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="user-row" data-role="<?php echo htmlspecialchars($u['rol_nombre'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($u['estatus'] ?? ''); ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                    <td style="padding: 16px 24px; display: flex; align-items: center; gap: 12px;">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')); ?>&background=random&color=fff&rounded=true&size=40" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%;">
                        <div>
                            <p class="user-name" style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0;"><?php echo htmlspecialchars(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')); ?></p>
                            <p class="user-email" style="font-size: 12px; color: #64748b; margin: 0;"><?php echo htmlspecialchars($u['correo'] ?? ''); ?></p>
                            <p style="font-size: 10px; color: #94a3b8; margin: 2px 0 0 0; text-transform: uppercase; font-weight: 700;">Emp/Mat: <?php echo htmlspecialchars($u['empresa'] ?? $u['rfc_matricula'] ?? 'N/A'); ?></p>
                        </div>
                    </td>
                    <td style="padding: 16px 24px;">
                        <?php 
                        $rol = htmlspecialchars($u['rol_nombre'] ?? 'Usuario');
                        $bg = '#f1f5f9'; $color = '#475569';
                        if(stripos($rol, 'admin') !== false) { $bg = '#dcfce7'; $color = '#166534'; }
                        elseif(stripos($rol, 'usuario') !== false) { $bg = '#fce7f3'; $color = '#be185d'; }
                        elseif(stripos($rol, 'visualizador') !== false) { $bg = '#fef9c3'; $color = '#854d0e'; }
                        ?>
                        <span style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block;">
                            <?php echo $rol; ?>
                        </span>
                    </td>
                    <td style="padding: 16px 24px;">
                        <?php 
                        $estatus = htmlspecialchars($u['estatus'] ?? 'Activo');
                        $dotColor = $estatus === 'Activo' ? '#16a34a' : '#d1d5db';
                        ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo $dotColor; ?>; display: inline-block;"></span>
                            <span style="font-size: 13px; font-weight: 700; color: #1e293b;"><?php echo $estatus; ?></span>
                        </div>
                    </td>
                    <td style="padding: 16px 24px;">
                        <span style="font-size: 13px; font-weight: 700; color: #1e293b;"><?php echo getRelativeTime($u['ultima_conexion'] ?? null); ?></span>
                    </td>
                    <td style="padding: 16px 24px; text-align: center;">
                        <button onclick='editUser(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)' style="background: none; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; color: #475569; cursor: pointer; transition: all 0.2s; margin-right: 8px;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='none'">
                            <i data-lucide="edit-2" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button onclick="confirmDelete(event, '?delete_user=<?php echo $u['us_id']; ?>', '¿Dar de baja a este usuario?')" style="display: inline-block; background: none; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; color: #ef4444; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">
                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pestaña Roles -->
    <div id="tab-roles" style="display: <?php echo $tab === 'roles' ? 'block' : 'none'; ?>;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
            <?php foreach ($roles as $rol): ?>
            <?php $p = json_decode($rol['permisos'] ?? '', true) ?: []; ?>
            <div style="padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative;">
                <div style="position: absolute; top: 20px; right: 20px; display: flex; gap: 8px;">
                    <button onclick='editRole(<?php echo json_encode($rol); ?>)' style="background: #f1f5f9; border: none; border-radius: 6px; padding: 6px; color: #475569; cursor: pointer;"><i data-lucide="edit-2" style="width: 14px; height: 14px;"></i></button>
                    <button onclick="confirmDelete(event, '?delete_rol=<?php echo $rol['rol_id']; ?>', '¿Eliminar este rol definitivamente?')" style="background: #fef2f2; border: none; border-radius: 6px; padding: 6px; color: #ef4444; cursor: pointer; display: inline-block;"><i data-lucide="trash-2" style="width: 14px; height: 14px;"></i></button>
                </div>
                
                <h4 style="font-size: 18px; font-weight: 800; color: #1e293b; margin: 0 0 6px 0;"><?php echo htmlspecialchars($rol['nombre']); ?></h4>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 20px; font-weight: 500;"><?php echo htmlspecialchars($rol['descripcion']); ?></p>
                
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($p as $modulo => $acciones): 
                        $countVal = is_array($acciones) ? count($acciones) : ($acciones === true ? 'Todo' : htmlspecialchars((string)$acciones));
                    ?>
                        <span style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase;">
                            <?php echo htmlspecialchars($modulo); ?>: <?php echo $countVal; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Pestaña Invitaciones -->
    <div id="tab-invitaciones" style="display: <?php echo $tab === 'invitaciones' ? 'grid' : 'none'; ?>; grid-template-columns: 2fr 1fr; gap: 32px;">
        <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Invitado</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Código</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Anfitrión</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $inv): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px;">
                            <p style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0;"><?php echo htmlspecialchars($inv['nombre'] ?? ''); ?></p>
                            <p style="font-size: 12px; color: #64748b; margin: 0;"><?php echo htmlspecialchars($inv['correo'] ?? ''); ?></p>
                        </td>
                        <td style="padding: 16px 24px;">
                            <code style="background: #f1f5f9; padding: 6px 12px; border-radius: 8px; font-weight: 800; color: #1e293b; font-size: 13px;"><?php echo htmlspecialchars($inv['codigo_acceso'] ?? ''); ?></code>
                        </td>
                        <td style="padding: 16px 24px; font-size: 13px; font-weight: 700; color: #64748b;"><?php echo htmlspecialchars($inv['anfitrion_nombre'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 24px; align-self: start;">
            <h3 style="font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 24px;">Generar Invitación</h3>
            <form id="form-invitacion">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Nombre/Empresa del Invitado</label>
                        <input type="text" name="nombre_visita" id="nombre_visita" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Correo del Invitado</label>
                        <input type="email" name="correo_visita" id="correo_visita" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                    </div>
                    <button type="submit" style="background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; margin-top: 8px;">Generar Código</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Usuario -->
<div id="modal-usuario" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 500px; padding: 32px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 id="user-modal-title" style="font-size: 20px; font-weight: 800; color: #1e293b;">Nuevo Usuario</h2>
            <button onclick="closeUserModal()" style="background: none; border: none; cursor: pointer; color: #94a3b8;"><i data-lucide="x"></i></button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="us_id" id="us_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Nombre</label>
                    <input type="text" name="nombre" id="us_nombre" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Apellido</label>
                    <input type="text" name="apellido" id="us_apellido" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Correo Electrónico</label>
                <input type="email" name="correo" id="us_correo" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 32px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Rol Asignado</label>
                    <select name="rol_id" id="us_rol" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['rol_id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Género</label>
                    <select name="genero" id="us_genero" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Matrícula / RFC</label>
                    <input type="text" name="rfc_matricula" id="us_rfc" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeUserModal()" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="flex: 1; background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Rol -->
<div id="modal-rol" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 32px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h2 id="role-modal-title" style="font-size: 20px; font-weight: 800; color: #1e293b;">Configurar Rol</h2>
            <button onclick="closeRoleModal()" style="background: none; border: none; cursor: pointer; color: #94a3b8;"><i data-lucide="x"></i></button>
        </div>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 24px;">Defina las acciones permitidas (CRUD) por módulo para este perfil.</p>
        
        <form method="POST" id="form-rol">
            <input type="hidden" name="action" value="save_role">
            <input type="hidden" name="rol_id" id="rol_id">
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Nombre del Rol</label>
                    <input type="text" name="nombre_rol" id="nombre_rol" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
                
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Descripción</label>
                    <textarea name="descripcion_rol" id="descripcion_rol" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; height: 60px; resize: none;"></textarea>
                </div>

                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; margin-bottom: 16px;">Permisos Granulares</label>
                    
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($modulos as $mod => $acciones): ?>
                        <div>
                            <p style="font-size: 12px; font-weight: 700; color: #475569; margin: 0 0 8px 0;"><?php echo $mod; ?></p>
                            <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                <?php foreach ($acciones as $acc): ?>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: #64748b; cursor: pointer;">
                                    <input type="checkbox" name="permisos[<?php echo $mod; ?>][]" value="<?php echo $acc; ?>" class="perm-check" data-mod="<?php echo $mod; ?>" data-acc="<?php echo $acc; ?>" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #cbd5e1;"> <?php echo $acc; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; padding-top: 8px;">
                    <button type="button" onclick="closeRoleModal()" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Guardar Cambios</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .btn-tab {
        border: none; padding: 10px 24px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #64748b; background: transparent; cursor: pointer; transition: all 0.2s;
    }
    .btn-tab.active { background: white; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>

<script>
    let currentActiveTab = '<?php echo $tab; ?>';

    function switchTab(tab) {
        currentActiveTab = tab;
        document.getElementById('tab-usuarios').style.display = tab === 'usuarios' ? 'block' : 'none';
        document.getElementById('tab-roles').style.display = tab === 'roles' ? 'block' : 'none';
        document.getElementById('tab-invitaciones').style.display = tab === 'invitaciones' ? 'grid' : 'none';
        
        // Mostrar u ocultar estadísticas
        document.getElementById('stats-usuarios').style.display = (tab === 'usuarios' || tab === 'roles') ? 'grid' : 'none';
        document.getElementById('stats-invitaciones').style.display = tab === 'invitaciones' ? 'grid' : 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        if(document.getElementById('btn-' + tab)) document.getElementById('btn-' + tab).classList.add('active');
        
        document.getElementById('btn-action-user').style.display = tab === 'usuarios' ? 'flex' : 'none';
        document.getElementById('btn-action-role').style.display = tab === 'roles' ? 'flex' : 'none';
    }

    function exportToPDF() {
        let title = "";
        let headers = [];
        let rowsHtml = "";

        if (currentActiveTab === 'usuarios') {
            title = "Reporte de Usuarios - SIGRAT";
            headers = ["Usuario", "Rol", "Estado", "Última Conexión"];
            
            const rows = document.querySelectorAll("#usersTable tbody tr");
            rows.forEach(row => {
                if (row.style.display === "none") return;
                
                const name = row.querySelector(".user-name")?.innerText || "";
                const email = row.querySelector(".user-email")?.innerText || "";
                const detailText = row.querySelector("p[style*='text-transform: uppercase']")?.innerText || "";
                
                const userCell = `<div><strong>${name}</strong></div><div style="font-size: 11px; color: #475569;">${email}</div><div style="font-size: 10px; color: #64748b;">${detailText}</div>`;
                const rol = row.querySelector("td:nth-child(2) span")?.innerText || "";
                const status = row.querySelector("td:nth-child(3)")?.innerText || "";
                const badgeClass = status.trim().toLowerCase() === 'activo' ? 'badge-activo' : 'badge-inactivo';
                const statusCell = `<span class="badge ${badgeClass}">${status}</span>`;
                const lastConn = row.querySelector("td:nth-child(4) span")?.innerText || "";
                
                rowsHtml += `
                    <tr>
                        <td>${userCell}</td>
                        <td><span class="badge" style="background:#f1f5f9; color:#475569;">${rol}</span></td>
                        <td>${statusCell}</td>
                        <td>${lastConn}</td>
                    </tr>
                `;
            });
        } else if (currentActiveTab === 'roles') {
            title = "Reporte de Roles y Permisos - SIGRAT";
            headers = ["Rol", "Descripción", "Permisos"];
            
            const roleCards = document.querySelectorAll("#tab-roles > div > div");
            roleCards.forEach(card => {
                const name = card.querySelector("h4")?.innerText || "";
                const desc = card.querySelector("p")?.innerText || "";
                const badges = Array.from(card.querySelectorAll("span")).map(s => s.innerText).join(", ");
                
                rowsHtml += `
                    <tr>
                        <td><strong>${name}</strong></td>
                        <td>${desc}</td>
                        <td style="font-size: 11px; line-height: 1.4;">${badges}</td>
                    </tr>
                `;
            });
        } else if (currentActiveTab === 'invitaciones') {
            title = "Reporte de Invitaciones - SIGRAT";
            headers = ["Invitado", "Código de Acceso", "Anfitrión"];
            
            const rows = document.querySelectorAll("#tab-invitaciones table tbody tr");
            rows.forEach(row => {
                const guestName = row.querySelector("td:first-child p:first-child")?.innerText || "";
                const guestEmail = row.querySelector("td:first-child p:nth-child(2)")?.innerText || "";
                const code = row.querySelector("td:nth-child(2) code")?.innerText || "";
                const host = row.querySelector("td:nth-child(3)")?.innerText || "";
                
                rowsHtml += `
                    <tr>
                        <td>
                            <div><strong>${guestName}</strong></div>
                            <div style="font-size: 11px; color: #475569;">${guestEmail}</div>
                        </td>
                        <td><code style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-family: monospace;">${code}</code></td>
                        <td>${host}</td>
                    </tr>
                `;
            });
        }

        const headersHtml = headers.map(h => `<th>${h}</th>`).join("");
        
        // Usar iframe oculto para evitar bloqueo de popups del navegador
        let printFrame = document.getElementById('_pdf_print_frame');
        if (printFrame) printFrame.remove();
        printFrame = document.createElement('iframe');
        printFrame.id = '_pdf_print_frame';
        printFrame.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;';
        document.body.appendChild(printFrame);
        const doc = printFrame.contentWindow.document;
        doc.open();
        doc.write(`
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>${title}</title>
                <style>
                    body {
                        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                        color: #1e293b;
                        margin: 0;
                        padding: 40px;
                        background-color: #ffffff;
                    }
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        border-bottom: 2px solid #2563eb;
                        padding-bottom: 20px;
                        margin-bottom: 30px;
                    }
                    .logo-area h1 {
                        font-size: 28px;
                        font-weight: 800;
                        color: #2563eb;
                        margin: 0;
                        letter-spacing: -1px;
                    }
                    .logo-area p {
                        font-size: 11px;
                        color: #64748b;
                        margin: 4px 0 0 0;
                        font-weight: 600;
                        text-transform: uppercase;
                    }
                    .meta-info {
                        text-align: right;
                        font-size: 13px;
                        color: #475569;
                    }
                    .meta-info h2 {
                        font-size: 18px;
                        font-weight: 700;
                        color: #1e293b;
                        margin: 0 0 6px 0;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }
                    th {
                        background-color: #f8fafc;
                        color: #475569;
                        font-weight: 700;
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        padding: 12px 14px;
                        border: 1px solid #e2e8f0;
                        text-align: left;
                    }
                    td {
                        padding: 12px 14px;
                        font-size: 13px;
                        color: #334155;
                        border: 1px solid #e2e8f0;
                    }
                    tr:nth-child(even) td {
                        background-color: #f8fafc;
                    }
                    .footer {
                        margin-top: 50px;
                        font-size: 11px;
                        color: #94a3b8;
                        text-align: center;
                        border-top: 1px solid #e2e8f0;
                        padding-top: 20px;
                    }
                    .badge {
                        display: inline-block;
                        padding: 4px 8px;
                        border-radius: 6px;
                        font-size: 11px;
                        font-weight: 700;
                    }
                    .badge-activo { background-color: #dcfce7; color: #166534; }
                    .badge-inactivo { background-color: #f3f4f6; color: #4b5563; }
                    @media print {
                        body { padding: 0; }
                        .no-print { display: none !important; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="logo-area">
                        <h1>SIGRAT</h1>
                        <p>Control Integral</p>
                    </div>
                    <div class="meta-info">
                        <h2>${title}</h2>
                        <div>Generado el: ${new Date().toLocaleString()}</div>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>${headersHtml}</tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
                <div class="footer">
                    Este documento es un reporte generado por el Sistema de Gestión de Reservas y Actividades Tecnológicas (SIGRAT).
                </div>
            </body>
            </html>
        `);
        doc.close();
        // Pequeño delay para que el iframe cargue el contenido antes de imprimir
        setTimeout(() => {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        }, 400);
    }


    // Filtros en vivo JS
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('.user-row');

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const role = roleFilter.value;
        const status = statusFilter.value;

        rows.forEach(row => {
            const name = row.querySelector('.user-name').innerText.toLowerCase();
            const email = row.querySelector('.user-email').innerText.toLowerCase();
            const rowRole = row.getAttribute('data-role');
            const rowStatus = row.getAttribute('data-status');

            const matchSearch = name.includes(query) || email.includes(query);
            const matchRole = role === "" || rowRole === role;
            const matchStatus = status === "" || rowStatus === status;

            if (matchSearch && matchRole && matchStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterTable);
    roleFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Modal Usuario
    function openUserModal() {
        document.getElementById('us_id').value = '';
        document.getElementById('us_nombre').value = '';
        document.getElementById('us_apellido').value = '';
        document.getElementById('us_correo').value = '';
        document.getElementById('us_rfc').value = '';
        document.getElementById('user-modal-title').innerText = 'Nuevo Usuario';
        document.getElementById('modal-usuario').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeUserModal() {
        document.getElementById('modal-usuario').style.display = 'none';
        document.body.style.overflow = '';
    }
    function editUser(u) {
        document.getElementById('us_id').value = u.us_id;
        document.getElementById('us_nombre').value = u.nombre || '';
        document.getElementById('us_apellido').value = u.apellido || '';
        document.getElementById('us_correo').value = u.correo || '';
        document.getElementById('us_rol').value = u.rol_id;
        document.getElementById('us_genero').value = u.genero || 'Masculino';
        document.getElementById('us_rfc').value = u.rfc_matricula || u.empresa || '';
        document.getElementById('user-modal-title').innerText = 'Editar Usuario';
        document.getElementById('modal-usuario').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Modal Rol
    function openRoleModal() {
        document.getElementById('form-rol').reset();
        document.getElementById('rol_id').value = '';
        document.getElementById('role-modal-title').innerText = 'Configurar Nuevo Rol';
        document.getElementById('modal-rol').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeRoleModal() {
        document.getElementById('modal-rol').style.display = 'none';
        document.body.style.overflow = '';
    }
    function editRole(rol) {
        document.getElementById('rol_id').value = rol.rol_id;
        document.getElementById('nombre_rol').value = rol.nombre;
        document.getElementById('descripcion_rol').value = rol.descripcion;
        document.getElementById('role-modal-title').innerText = 'Editar Rol: ' + rol.nombre;
        
        document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
        
        let permisos = {};
        try { permisos = JSON.parse(rol.permisos) || {}; } catch (e) {}
        
        for (const mod in permisos) {
            if (Array.isArray(permisos[mod])) {
                permisos[mod].forEach(acc => {
                    const check = document.querySelector(`.perm-check[data-mod="${mod}"][data-acc="${acc}"]`);
                    if (check) check.checked = true;
                });
            }
        }
        document.getElementById('modal-rol').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Botón Limpiar Filtros
    document.getElementById('btnClearFilters').addEventListener('click', () => {
        searchInput.value = '';
        roleFilter.value = '';
        statusFilter.value = '';
        filterTable();
    });

    // Formulario Generar Invitación (AJAX con SweetAlert)
    const formInvitacion = document.getElementById('form-invitacion');
    if (formInvitacion) {
        formInvitacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formInvitacion);
            formData.append('action', 'generate_code');
            
            try {
                const res = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Invitación Generada!',
                        html: `El código de acceso es:<br><br><b style="font-size:24px; letter-spacing:4px; color:#2563eb;">${data.codigo}</b><br><br>Por favor, compártelo con el invitado.`,
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        window.location.href = 'usuarios.php?tab=invitaciones';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo generar la invitación: ' + (data.error || 'Error desconocido'),
                        confirmButtonColor: '#2563eb'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'Ocurrió un error al procesar la solicitud.',
                    confirmButtonColor: '#2563eb'
                });
            }
        });
    }

    // Notificaciones de URL
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            Swal.fire({
                icon: 'success',
                title: '¡Operación Exitosa!',
                text: 'Los cambios se han guardado correctamente.',
                timer: 2500,
                showConfirmButton: false
            });
            // Limpiar URL
            window.history.replaceState({}, document.title, window.location.pathname + '?tab=' + (urlParams.get('tab') || 'usuarios'));
        }
        if (urlParams.has('deleted')) {
            Swal.fire({
                icon: 'info',
                title: 'Registro Eliminado',
                text: 'El registro ha sido dado de baja o eliminado exitosamente.',
                timer: 2500,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname + '?tab=' + (urlParams.get('tab') || 'usuarios'));
        }
    });

    // Función global para confirmaciones con SweetAlert2
    function confirmDelete(e, url, message) {
        e.preventDefault();
        Swal.fire({
            title: '¿Estás seguro?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }

</script>

<?php include 'footer.php'; ?>
