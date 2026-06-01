<?php
/**
 * @file header.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

$base_path = "C:/xampp/htdocs/creaciones antigravity/Estadias";
require_once $base_path . '/backend/controllers/AuthController.php';

use Controllers\AuthController;

$auth = new AuthController();
$jwt_valid = false;

if (isset($_COOKIE['auth_token'])) {
    $payload = $auth->validateJWT($_COOKIE['auth_token']);
    if ($payload) {
        $_SESSION['us_id'] = $payload['us_id'];
        $_SESSION['nombre'] = $payload['nombre'];
        $_SESSION['rol'] = $payload['rol'];
        $_SESSION['permisos'] = $payload['permisos'];
        $jwt_valid = true;
    } else {
        setcookie('auth_token', '', time() - 3600, '/creaciones%20antigravity/Estadias/');
    }
}

if (!$jwt_valid && isset($_SESSION['us_id'])) {
    $jwt_valid = true; 
}

$currentPage = basename($_SERVER['PHP_SELF']);
$protected_pages = ['usuarios.php', 'espacios.php', 'enrolamiento.php', 'auditoria.php', 'config.php', 'test_rfid.php'];

if (!$jwt_valid && in_array($currentPage, $protected_pages)) {
    header("Location: login.php");
    exit();
}

if (!function_exists('hasPermission')) {
    function hasPermission($modulo, $accion = 'read') {
        if (!isset($_SESSION['rol'])) return false;
        $userRol = strtoupper(trim($_SESSION['rol']));
        // Si es Admin superuser
        if (strpos($userRol, 'ADMIN') !== false) return true;
        if (!isset($_SESSION['permisos'])) return false;
        
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT - Sistema Universitario</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --sidebar-navy: #0c1e35; --active-blue: #3b82f6; --bg-main: #f8fafc; --text-slate: #475569; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-slate); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--sidebar-navy); color: #94a3b8; height: 100vh; position: fixed; z-index: 100; display: flex; flex-direction: column; }
        .sidebar-header { padding: 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .logo-box { background: var(--active-blue); padding: 8px; border-radius: 8px; color: white; }
        .nav-menu { flex: 1; padding: 16px; display: flex; flex-direction: column; gap: 4px; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .nav-item.active { background: var(--active-blue); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .main-container { flex: 1; margin-left: 260px; display: flex; flex-direction: column; }
        .top-bar { height: 64px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; position: sticky; top: 0; z-index: 90; }
        .content-padding { padding: 32px; }
        .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }

        /* SISTEMA DE DISEÑO GLOBAL */
        .btn-primary { 
            background: var(--active-blue); color: white; border: none; padding: 12px 24px; 
            border-radius: 12px; font-weight: 800; cursor: pointer; font-size: 13px; 
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            text-decoration: none; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 12px -2px rgba(59, 130, 246, 0.3); opacity: 0.9; }
        
        .btn-secondary { 
            background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 12px 24px; 
            border-radius: 12px; font-weight: 800; cursor: pointer; font-size: 13px;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
            text-decoration: none;
        }
        .btn-secondary:hover { background: #e2e8f0; }

        .form-control {
            width: 100%; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 12px; 
            font-size: 14px; font-weight: 600; color: #1e293b; background: #f8fafc;
            transition: all 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--active-blue); background: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        
        label { display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-box"><i data-lucide="shield-check"></i></div>
            <div>
                <h2 style="color: white; font-size: 18px; font-weight: 800; letter-spacing: -1px;">SIGRAT</h2>
                <p style="font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.5;">DPIyDT UTEQ</p>
            </div>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>"><i data-lucide="layout-dashboard" style="width:18px;"></i> Dashboard</a>
            
            <?php if (hasPermission('Inventario')): ?>
            <a href="inventario.php" class="nav-item <?php echo $currentPage == 'inventario.php' ? 'active' : ''; ?>"><i data-lucide="package" style="width:18px;"></i> Inventario</a>
            <?php endif; ?>

            <?php if (hasPermission('Espacios')): ?>
            <a href="espacios.php" class="nav-item <?php echo $currentPage == 'espacios.php' ? 'active' : ''; ?>"><i data-lucide="map-pin" style="width:18px;"></i> Espacios</a>
            <?php endif; ?>

            <?php if (hasPermission('Visitas')): ?>
            <a href="visitas.php" class="nav-item <?php echo $currentPage == 'visitas.php' ? 'active' : ''; ?>"><i data-lucide="user-check" style="width:18px;"></i> Visitas</a>
            <?php endif; ?>

            <?php if (hasPermission('RFID')): ?>
            <a href="rfid.php" class="nav-item <?php echo $currentPage == 'rfid.php' ? 'active' : ''; ?>"><i data-lucide="radio" style="width:18px;"></i> RFID</a>
            <?php endif; ?>

            <?php if (hasPermission('Usuarios')): ?>
            <a href="usuarios.php" class="nav-item <?php echo $currentPage == 'usuarios.php' ? 'active' : ''; ?>"><i data-lucide="users" style="width:18px;"></i> Usuarios</a>
            <?php endif; ?>

            <?php if (hasPermission('Auditorias')): ?>
            <a href="auditoria.php" class="nav-item <?php echo $currentPage == 'auditoria.php' ? 'active' : ''; ?>"><i data-lucide="shield-check" style="width:18px;"></i> Auditorías</a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="main-container">
        <header class="top-bar">
            <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Panel de Control / <?php echo strtoupper(basename($_SERVER['PHP_SELF'], '.php')); ?></div>
            <div style="display: flex; align-items: center; gap: 24px;">
                <div style="display: flex; align-items: center; gap: 12px; padding-left: 24px; border-left: 1px solid #e2e8f0;">
                    <div style="width: 32px; height: 32px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #475569;">
                        <?php echo substr($_SESSION['nombre'] ?? 'U', 0, 2); ?>
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 12px; font-weight: 700; color: #1e293b;"><?php echo $_SESSION['nombre'] ?? 'Invitado'; ?></span>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            <?php if(isset($_SESSION['us_id'])): ?>
                                <span style="font-size: 9px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 800;"><?php echo strtoupper($_SESSION['rol'] ?? 'S/R'); ?></span>
                                <a href="logout.php" style="font-size: 10px; font-weight: 800; color: #ef4444; text-decoration: none; margin-left: 8px;">SALIR</a>
                            <?php else: ?>
                                <a href="login.php" style="font-size: 10px; font-weight: 800; color: #3b82f6; text-decoration: none;">INICIAR SESIÓN</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="content-padding">
        <script>lucide.createIcons();</script>
