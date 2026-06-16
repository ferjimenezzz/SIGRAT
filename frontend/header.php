<?php
/**
 * @file header.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (session_status() === PHP_SESSION_NONE) session_start();

// Ruta dinámica relativa al directorio actual
$base_path = dirname(__DIR__);
require_once $base_path . '/backend/controllers/AuthController.php';

$auth = new \Controllers\AuthController();
$jwt_valid = false;

// Control de sesión estricto mediante token JWT
if (isset($_COOKIE['auth_token'])) {
    $payload = $auth->validateJWT($_COOKIE['auth_token']);
    if ($payload) {
        $_SESSION['us_id'] = $payload['us_id'];
        $_SESSION['nombre'] = $payload['nombre'];
        $_SESSION['rol'] = $payload['rol'];
        $_SESSION['permisos'] = $payload['permisos'];
        $jwt_valid = true;
        
        // Sliding Expiration: Renovar la cookie por otros 8 horas para mantener la sesión activa
        setcookie('auth_token', $_COOKIE['auth_token'], time() + (60 * 60 * 8), '/', '', false, true);
    } else {
        // Token inválido o expirado -> limpiar sesión y cookies
        \Controllers\AuthController::logout();
    }
} else {
    // Si no existe la cookie del token, invalidar la sesión de PHP existente
    if (isset($_SESSION['us_id'])) {
        \Controllers\AuthController::logout();
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
// Lista completa de páginas protegidas (incluyendo aprobacion_reservas.php)
$protected_pages = [
    'usuarios.php', 
    'espacios.php', 
    'enrolamiento.php', 
    'auditoria.php', 
    'config.php', 
    'test_rfid.php',
    'aprobacion_reservas.php'
];

if (!$jwt_valid && in_array($currentPage, $protected_pages)) {
    header("Location: login.php");
    exit();
}

if (!function_exists('hasPermission')) {
    function hasPermission($modulo, $accion = 'read') {
        if (!isset($_SESSION['rol'])) return false;
        $userRol = strtoupper(trim($_SESSION['rol']));
        
        // 2. Privilegios de SuperUsuario/Admin
        if (strpos($userRol, 'ADMIN') !== false || strpos($userRol, 'SUPERUSUARIO') !== false) return true;
        
        if (!isset($_SESSION['permisos'])) return false;
        
        // 3. Permisos heredados del Rol Base
        $permisos = $_SESSION['permisos'];
        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }
        
        if (isset($permisos[$modulo]) && isset($permisos[$modulo][$accion])) {
            return $permisos[$modulo][$accion] === true;
        }
        return false;
    }
}

// Fecha en español para el top bar
$dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$diaNum = date('j');
$diaSemana = $dias[date('w')];
$mes = $meses[date('n') - 1];
$anio = date('Y');
$fechaFormateada = "$diaNum de $mes de $anio";

// Iniciales del usuario para el avatar
$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$inicialesUsuario = '';
$partesNombre = explode(' ', $nombreUsuario);
$inicialesUsuario = strtoupper(substr($partesNombre[0], 0, 1));
if (count($partesNombre) > 1) {
    $inicialesUsuario .= strtoupper(substr($partesNombre[1], 0, 1));
}
$rolUsuario = $_SESSION['rol'] ?? 'Sin rol';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT - Sistema Universitario</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0f1729;
            --sidebar-hover: rgba(255,255,255,0.06);
            --sidebar-active: #2563eb;
            --bg-main: #f0f2f5;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --accent-blue: #2563eb;
            --topbar-bg: #ffffff;
        }


        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
        }

        /* ==================== SIDEBAR ==================== */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background-color: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: width 0.3s ease, min-width 0.3s ease;
        }

        .sidebar-top {
            padding: 20px 18px 12px 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-back-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .sidebar-back-btn:hover {
            background: var(--sidebar-hover);
            color: white;
        }

        .sidebar-toggle-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            background: transparent;
            margin-left: auto;
        }

        .sidebar-toggle-btn:hover {
            background: var(--sidebar-hover);
            color: white;
        }
        
        body.sidebar-collapsed .sidebar-toggle-btn i {
            transform: rotate(180deg);
        }

        .sidebar-header {
            padding: 8px 18px 20px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 8px;
        }

        .sidebar-logo {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            object-fit: contain;
            background: white;
            padding: 4px;
        }

        .sidebar-brand h2 {
            color: white;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .sidebar-brand p {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            letter-spacing: 0.5px;
        }

        /* Navigation */
        .nav-menu {
            flex: 1;
            padding: 8px 12px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            color: #8892a5;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        .nav-item:hover {
            background: var(--sidebar-hover);
            color: #c8d0df;
        }

        .nav-item.active {
            background: var(--sidebar-active);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.35);
        }

        /* User section at bottom of sidebar */
        .sidebar-user {
            padding: 16px 14px;
            margin: 8px 12px 12px 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-user-avatar {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 800;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            color: #e2e8f0;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
        }

        .sidebar-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            margin: 0 12px 16px 12px;
            border-radius: 10px;
            color: #8892a5;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .sidebar-logout i {
            font-size: 18px;
        }

        .sidebar-logout:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
        }

        /* ==================== COLLAPSED SIDEBAR ==================== */
        body.sidebar-collapsed .sidebar {
            width: 80px;
            min-width: 80px;
        }

        body.sidebar-collapsed .main-container {
            margin-left: 80px;
        }

        body.sidebar-collapsed .sidebar-brand,
        body.sidebar-collapsed .nav-item span,
        body.sidebar-collapsed .sidebar-user-info,
        body.sidebar-collapsed .sidebar-logout span {
            display: none;
        }

        body.sidebar-collapsed .sidebar-header {
            padding: 8px 0 20px 0;
            justify-content: center;
        }

        body.sidebar-collapsed .sidebar-logo {
            margin: 0;
        }

        body.sidebar-collapsed .nav-item {
            justify-content: center;
            padding: 11px 0;
        }

        body.sidebar-collapsed .nav-item i {
            margin: 0;
            font-size: 20px;
            width: auto;
        }

        body.sidebar-collapsed .sidebar-user {
            justify-content: center;
            padding: 16px 0;
            margin: 8px 12px 12px 12px;
        }

        body.sidebar-collapsed .sidebar-logout {
            justify-content: center;
            padding: 10px 0;
        }

        body.sidebar-collapsed .sidebar-logout i {
            margin: 0;
        }

        /* ==================== MAIN CONTAINER ==================== */
        .main-container {
            flex: 1;
            margin-left: 240px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* ==================== TOP BAR ==================== */
        .top-bar {
            height: 68px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-left h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .topbar-left p {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 9px 16px;
            min-width: 200px;
        }

        .search-box i {
            color: var(--text-muted);
            font-size: 16px;
        }

        .search-box input {
            border: none;
            background: transparent;
            font-size: 13px;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            width: 100%;
            font-weight: 500;
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .topbar-icon-btn {
            position: relative;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .topbar-icon-btn:hover {
            background: #e2e8f0;
        }

        .topbar-icon-btn i {
            font-size: 18px;
        }

        .notification-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: #2563eb;
            border-radius: 50%;
            border: 2px solid white;
            display: none;
        }

        /* ==================== NOTIFICATIONS ==================== */
        .notif-panel {
            position: absolute;
            top: 50px;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            z-index: 1000;
            display: none;
            flex-direction: column;
            overflow: hidden;
            text-align: left;
        }

        .notif-panel.show {
            display: flex;
        }

        .notif-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }

        .notif-header h3 {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
        }

        .notif-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            display: block;
            transition: background 0.2s;
        }

        .notif-item:hover {
            background: #f8fafc;
        }

        .notif-item.unread {
            background: #eff6ff;
        }

        .notif-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .notif-text {
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .notif-time {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 6px;
            display: block;
        }
        
        .notif-empty {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
        }

        .topbar-date {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
        }

        .topbar-date i {
            color: var(--text-muted);
            font-size: 18px;
        }

        .topbar-date-text {
            display: flex;
            flex-direction: column;
        }

        .topbar-date-text .date-main {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .topbar-date-text .date-day {
            font-size: 10px;
            font-weight: 500;
            color: var(--text-muted);
        }

        /* ==================== CONTENT ==================== */
        .content-padding {
            padding: 24px 28px;
        }

        /* ==================== DESIGN SYSTEM ==================== */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }

        .btn-primary { 
            background: var(--accent-blue); color: white; border: none; padding: 12px 24px; 
            border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 13px; 
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary:hover { transform: translateY(-1px); opacity: 0.9; }
        
        .btn-secondary { 
            background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 12px 24px; 
            border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            text-decoration: none;
        }
        .btn-secondary:hover { background: #e2e8f0; }

        .form-control {
            width: 100%; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 12px; 
            font-size: 14px; font-weight: 600; color: #1e293b; background: #f8fafc;
            transition: all 0.2s; font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: var(--accent-blue); background: white; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        label { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <script>
        if (localStorage.getItem('sigrat_sidebar_collapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>
    <aside class="sidebar">
        <div class="sidebar-top">
            <a href="login.php" class="sidebar-back-btn" title="Volver">
                <i class="bi bi-arrow-left" style="font-size: 18px;"></i>
            </a>
            <button id="sidebarToggle" class="sidebar-toggle-btn" title="Minimizar menú">
                <i class="bi bi-chevron-left" style="font-size: 18px; transition: transform 0.3s ease;"></i>
            </button>
        </div>

        <div class="sidebar-header">
            <img src="assets/images/sigrat_logo.png" alt="SIGRAT" class="sidebar-logo">
            <div class="sidebar-brand">
                <h2>SIGRAT</h2>
                <p>Control Integral</p>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span>
            </a>
            <a href="calendario.php" class="nav-item <?php echo $currentPage == 'calendario.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar3"></i> <span>Calendario</span>
            </a>
            
            <?php if (hasPermission('Usuarios')): ?>
            <a href="usuarios.php" class="nav-item <?php echo $currentPage == 'usuarios.php' ? 'active' : ''; ?>">
                <i class="bi bi-person"></i> <span>Usuario</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('Espacios')): ?>
            <a href="espacios.php" class="nav-item <?php echo $currentPage == 'espacios.php' ? 'active' : ''; ?>">
                <i class="bi bi-geo-alt"></i> <span>Espacios</span>
            </a>
            <a href="aprobacion_reservas.php" class="nav-item <?php echo $currentPage == 'aprobacion_reservas.php' ? 'active' : ''; ?>">
                <i class="bi bi-check2-square"></i> <span>Aprobaciones</span>
            </a>
            <?php endif; ?>

            <a href="prestamos.php" class="nav-item <?php echo $currentPage == 'prestamos.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-left-right"></i> <span>Préstamos</span>
            </a>

            <?php if (hasPermission('Inventario')): ?>
            <a href="inventario.php" class="nav-item <?php echo $currentPage == 'inventario.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> <span>Inventario</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('Auditorias')): ?>
            <a href="auditoria.php" class="nav-item <?php echo $currentPage == 'auditoria.php' ? 'active' : ''; ?>">
                <i class="bi bi-heart-pulse"></i> <span>Auditoría</span>
            </a>
            <?php endif; ?>


        </nav>

        <!-- User section -->
        <a href="perfil.php" class="sidebar-user" style="text-decoration: none; cursor: pointer; transition: background 0.2s; border-radius: 10px;">
            <div class="sidebar-user-avatar"><?php echo $inicialesUsuario; ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo $nombreUsuario; ?></div>
                <div class="sidebar-user-role"><?php echo ucfirst($rolUsuario); ?></div>
            </div>
        </a>
        <a href="logout.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-left"></i> <span>Cerrar sesión</span>
        </a>
    </aside>

    <div class="main-container">
        <header class="top-bar">
            <div class="topbar-left">
                <h1>¡Bienvenido, <?php echo explode(' ', $nombreUsuario)[0]; ?>!</h1>
                <p>Resumen general del sistema</p>
            </div>
            <div class="topbar-right">
                <div class="topbar-icon-btn" id="notifBtn">
                    <i class="bi bi-bell"></i>
                    <div class="notification-badge" id="notifBadge"></div>
                    
                    <!-- Dropdown Notificaciones -->
                    <div class="notif-panel" id="notifPanel">
                        <div class="notif-header">
                            <h3>Notificaciones</h3>
                        </div>
                        <div class="notif-list" id="notifList">
                            <!-- Items insertados vía JS -->
                        </div>
                    </div>
                </div>

                <div class="topbar-date">
                    <i class="bi bi-calendar4-week"></i>
                    <div class="topbar-date-text">
                        <span class="date-main"><?php echo $fechaFormateada; ?></span>
                        <span class="date-day"><?php echo $diaSemana; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Limpieza de modo oscuro residual -->
        <script>
        localStorage.removeItem('sigrat_dark');
        document.body.classList.remove('dark-mode');

        // Sidebar Toggle Logic
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.body.classList.toggle('sidebar-collapsed');
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sigrat_sidebar_collapsed', isCollapsed ? 'true' : 'false');
                });
            }
        });
        </script>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notifBtn = document.getElementById('notifBtn');
            const notifPanel = document.getElementById('notifPanel');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');

            if(notifBtn) {
                notifBtn.addEventListener('click', function(e) {
                    if(e.target.closest('.notif-list')) return; // No cerrar si cliquean un item
                    notifPanel.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!notifBtn.contains(e.target)) {
                        notifPanel.classList.remove('show');
                    }
                });

                function fetchNotifications() {
                    // Primero forzar chequeo de préstamos por vencer (silenciosamente)
                    fetch('../backend/api/index.php/notifications/check_expiring', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    }).then(() => {
                        return fetch('../backend/api/index.php/notifications/all');
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(Array.isArray(data)) {
                            // Calcular no leídas (Postgres puede devolver booleanos como t/f, o 0/1)
                            const unreadCount = data.filter(n => n.leido === false || n.leido === 0 || n.leido === "0" || n.leido === "f").length;
                            
                            if(unreadCount > 0) {
                                notifBadge.style.display = 'block';
                            } else {
                                notifBadge.style.display = 'none';
                            }

                            if(data.length > 0) {
                                notifList.innerHTML = '';
                                data.forEach(n => {
                                    const a = document.createElement('a');
                                    a.href = n.enlace ? n.enlace : '#';
                                    const isRead = n.leido === true || n.leido === 1 || n.leido === "1" || n.leido === "t";
                                    a.className = 'notif-item ' + (isRead ? 'read' : 'unread');
                                    
                                    // Estilo para las leídas
                                    if(isRead) {
                                        a.style.opacity = '0.6';
                                        a.style.background = '#f8fafc';
                                    }

                                    a.innerHTML = `
                                        <div class="notif-title">${n.tipo}</div>
                                        <div class="notif-text">${n.mensaje}</div>
                                        <span class="notif-time">${new Date(n.fecha_creacion).toLocaleString()}</span>
                                    `;
                                    a.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        if(!isRead) {
                                            // Marcar como leída y luego redirigir
                                            fetch('../backend/api/index.php/notifications/read', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json' },
                                                body: JSON.stringify({ not_id: n.not_id })
                                            }).then(() => {
                                                window.location.href = a.href;
                                            });
                                        } else {
                                            window.location.href = a.href;
                                        }
                                    });
                                    notifList.appendChild(a);
                                });
                            } else {
                                notifList.innerHTML = '<div class="notif-empty">No tienes notificaciones recientes</div>';
                            }
                        }
                    })
                    .catch(e => console.error('Error fetching notifications', e));
                }

                // Cargar notificaciones al iniciar
                fetchNotifications();
                // Refrescar cada minuto
                setInterval(fetchNotifications, 60000);
            }
        });
        </script>
        <main class="content-padding">
