<?php

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
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
        $rol_id = $_POST['rol_id'];
        $genero = $_POST['genero'] ?? 'Masculino';
        $us_id = $_POST['us_id'] ?? null;

        if ($us_id) {
            $stmt = $db->prepare("UPDATE usuario SET nombre=?, apellido=?, correo=?, empresa=?, rol_id=?, genero=? WHERE us_id=?");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rol_id, $genero, $us_id]);
        } else {
            $pass = AuthController::hashPassword('123456');
            $stmt = $db->prepare("INSERT INTO usuario (nombre, apellido, correo, empresa, rol_id, genero, contrasena, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo')");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rol_id, $genero, $pass]);
        }
        header("Location: usuarios.php?tab=usuarios&success=1");
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


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Gestión de Usuarios</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Administra usuarios y permisos del sistema</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <button onclick="handleExportPDF()" id="btn-export-pdf" class="btn-secondary" style="border-radius: 8px; font-size: 12px; font-weight: 600; background: white; padding: 10px 16px; border: 1px solid #ef4444; color: #ef4444; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i data-lucide="file-text" style="width: 16px;"></i> PDF</button>
            <button onclick="handleExportExcel()" id="btn-export-excel" class="btn-secondary" style="border-radius: 8px; font-size: 12px; font-weight: 600; background: white; padding: 10px 16px; border: 1px solid #10b981; color: #10b981; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i data-lucide="table" style="width: 16px;"></i> Excel</button>
            <button onclick="openUserModal()" id="btn-action-user" class="btn-primary" style="background: #2563eb; border-radius: 8px; font-size: 12px; font-weight: 600; padding: 10px 16px; color: white; border: none; cursor: pointer; display: <?php echo $tab === 'usuarios' ? 'flex' : 'none'; ?>; align-items: center; gap: 6px;"><i data-lucide="plus" style="width: 16px;"></i> Nuevo usuario</button>
        </div>
    </header>

    <!-- Barra de Búsqueda, Filtros y Pestañas -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); flex-wrap: wrap; gap: 16px;">
        <div id="userFiltersContainer" style="display: flex; align-items: center; gap: 12px; flex: 1; flex-wrap: wrap;">
            <div style="position: relative; width: 300px; max-width: 100%; flex-grow: 1;">
                <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; color: #94a3b8;"></i>
                <input type="text" id="searchInput" placeholder="Buscar usuario, email..." style="width: 100%; padding: 10px 10px 10px 36px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 500; outline: none; box-sizing: border-box;">
            </div>
            
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
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
    <div id="tab-usuarios" class="card" style="display: <?php echo $tab === 'usuarios' ? 'block' : 'none'; ?>; padding: 0; overflow: auto; max-height: 450px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;" id="usersTable">
            <thead style="border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10; background: #f8fafc;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Nombre</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Correo Electrónico</th>
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
                        <span class="user-name" style="font-size: 14px; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars(trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''))); ?></span>
                    </td>
                    <td style="padding: 16px 24px;">
                        <span class="user-email" style="font-size: 13px; color: #475569; font-weight: 500;"><?php echo htmlspecialchars($u['correo'] ?? ''); ?></span>
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

    <!-- Pestaña Invitaciones -->
    <div id="tab-invitaciones" style="display: <?php echo $tab === 'invitaciones' ? 'grid' : 'none'; ?>; grid-template-columns: 2fr 1fr; gap: 32px;">
        <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); max-height: 350px; overflow: auto; align-self: start;">
            <table style="width: 100%; border-collapse: collapse;" id="invitesTable">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Invitado</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Correo Electrónico</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Código</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Anfitrión</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: left;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $inv): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($inv['nombre'] ?? ''); ?></td>
                        <td style="padding: 16px 24px; color: #475569; font-size: 13px;"><?php echo htmlspecialchars($inv['correo'] ?? ''); ?></td>
                        <td style="padding: 16px 24px;">
                            <code style="background: #eff6ff; color: #2563eb; padding: 6px 12px; border-radius: 8px; font-weight: 800; font-size: 13px; letter-spacing: 1px;"><?php echo htmlspecialchars($inv['codigo_acceso'] ?? ''); ?></code>
                        </td>
                        <td style="padding: 16px 24px; font-size: 13px; font-weight: 700; color: #64748b;"><?php echo htmlspecialchars($inv['anfitrion_nombre'] ?? 'N/A'); ?></td>
                        <td style="padding: 16px 24px;">
                            <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">
                                <?php echo htmlspecialchars($inv['estatus'] ?? 'Generado'); ?>
                            </span>
                        </td>
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
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Nombre(s)</label>
                    <input type="text" name="nombre" id="us_nombre" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Apellido(s)</label>
                    <input type="text" name="apellido" id="us_apellido" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Correo Electrónico</label>
                <input type="email" name="correo" id="us_correo" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px;">
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
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeUserModal()" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="flex: 1; background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Rol (Eliminado) -->

<style>
    .btn-tab {
        border: none; padding: 10px 24px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #64748b; background: transparent; cursor: pointer; transition: all 0.2s;
    }
    .btn-tab.active { background: white; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let currentActiveTab = '<?php echo $tab; ?>';

    function switchTab(tab) {
        currentActiveTab = tab;
        document.getElementById('tab-usuarios').style.display = tab === 'usuarios' ? 'block' : 'none';
        document.getElementById('tab-invitaciones').style.display = tab === 'invitaciones' ? 'grid' : 'none';
        
        // Mostrar u ocultar estadísticas
        document.getElementById('stats-usuarios').style.display = tab === 'usuarios' ? 'grid' : 'none';
        document.getElementById('stats-invitaciones').style.display = tab === 'invitaciones' ? 'grid' : 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        if(document.getElementById('btn-' + tab)) document.getElementById('btn-' + tab).classList.add('active');
        
        document.getElementById('btn-action-user').style.display = tab === 'usuarios' ? 'flex' : 'none';
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

    function handleExportPDF() {
        if (currentActiveTab === 'invitaciones') {
            window.open('../backend/reports/invitations_pdf.php', '_blank');
        } else {
            window.open('../backend/reports/users_pdf.php', '_blank');
        }
    }

    function handleExportExcel() {
        if (currentActiveTab === 'invitaciones') {
            exportTableToExcel('invitesTable', 'Invitaciones_SIGRAT');
        } else {
            exportTableToExcel('usersTable', 'Usuarios_SIGRAT');
        }
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showCopyToast();
            }).catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showCopyToast();
        } catch (err) {
            console.error('Error al copiar:', err);
        }
        document.body.removeChild(textArea);
    }

    function showCopyToast() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: '¡Código copiado al portapapeles!',
                showConfirmButton: false,
                timer: 2500
            });
        }
    }

    // Modal Usuario
    function openUserModal() {
        document.getElementById('us_id').value = '';
        document.getElementById('us_nombre').value = '';
        document.getElementById('us_apellido').value = '';
        document.getElementById('us_correo').value = '';
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
        
        let nom = (u.nombre || '').trim();
        let ape = (u.apellido || '').trim();

        // Si el campo apellido está vacío y el nombre contiene espacios, desglosarlo automáticamente
        if (!ape && nom.includes(' ')) {
            const parts = nom.split(/\s+/);
            if (parts.length >= 4) {
                nom = parts.slice(0, parts.length - 2).join(' ');
                ape = parts.slice(parts.length - 2).join(' ');
            } else if (parts.length === 3) {
                nom = parts[0];
                ape = parts.slice(1).join(' ');
            } else if (parts.length === 2) {
                nom = parts[0];
                ape = parts[1];
            }
        }

        document.getElementById('us_nombre').value = nom;
        document.getElementById('us_apellido').value = ape;
        document.getElementById('us_correo').value = u.correo || '';
        document.getElementById('us_rol').value = u.rol_id;
        document.getElementById('us_genero').value = u.genero || 'Masculino';
        document.getElementById('user-modal-title').innerText = 'Editar Usuario';
        document.getElementById('modal-usuario').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Botón Limpiar Filtros
    document.getElementById('btnClearFilters').addEventListener('click', () => {
        searchInput.value = '';
        roleFilter.value = '';
        statusFilter.value = '';
        filterTable();
    });

    // Formulario Generar Invitación (AJAX con SweetAlert y Copiar Código)
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
                    // Update DOM instantly
                    const tableBody = document.querySelector('#tab-invitaciones table tbody');
                    const guestName = document.getElementById('nombre_visita').value;
                    const guestEmail = document.getElementById('correo_visita').value;
                    const hostName = "<?php echo addslashes($_SESSION['nombre'] ?? 'Anfitrión Actual'); ?>";
                    
                    const newRow = document.createElement('tr');
                    newRow.style.borderBottom = '1px solid #f1f5f9';
                    newRow.innerHTML = `
                        <td style="padding: 16px 24px; font-weight: 700; color: #1e293b;">${guestName.replace(/</g, "&lt;")}</td>
                        <td style="padding: 16px 24px; color: #475569; font-size: 13px;">${guestEmail.replace(/</g, "&lt;")}</td>
                        <td style="padding: 16px 24px;">
                            <code style="background: #eff6ff; color: #2563eb; padding: 6px 12px; border-radius: 8px; font-weight: 800; font-size: 13px; letter-spacing: 1px;">${data.codigo}</code>
                        </td>
                        <td style="padding: 16px 24px; font-size: 13px; font-weight: 700; color: #64748b;">${hostName}</td>
                        <td style="padding: 16px 24px;">
                            <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block;">Generado</span>
                        </td>
                    `;
                    
                    if (tableBody) tableBody.prepend(newRow);
                    document.getElementById('form-invitacion').reset();

                    // Show success modal with Copy button
                    Swal.fire({
                        icon: 'success',
                        title: '¡Invitación Generada Exitosamente!',
                        html: `
                            <p style="color: #64748b; font-size: 13px; margin-bottom: 12px;">Se ha enviado un correo electrónico a <b>${guestEmail.replace(/</g, "&lt;")}</b> con la invitación y las instrucciones de acceso.</p>
                            <div style="background: #eff6ff; border: 2px dashed #2563eb; padding: 16px; border-radius: 12px; margin: 16px 0;">
                                <span style="font-size: 11px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Código de Acceso</span>
                                <div style="font-size: 28px; font-weight: 900; color: #2563eb; letter-spacing: 5px; margin: 6px 0;" id="modalCodeText">${data.codigo}</div>
                            </div>
                            <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 12px; display: inline-block;">
                                ⏰ Tiempo de caducidad: 24 horas a partir de la emisión
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#2563eb',
                        cancelButtonColor: '#10b981',
                        confirmButtonText: 'Entendido',
                        cancelButtonText: '📋 Copiar Código'
                    }).then((result) => {
                        if (result.dismiss === Swal.DismissReason.cancel) {
                            copyToClipboard(data.codigo);
                        }
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
