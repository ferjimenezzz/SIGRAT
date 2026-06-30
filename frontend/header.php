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
        $_SESSION['genero'] = $payload['genero'] ?? 'Masculino';
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
    // header("Location: " . $base_path . "/frontend/iniciar_sesion.php");
    // exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
// Lista completa de páginas protegidas (incluyendo aprobacion_reservas.php)
$protected_pages = [
    'usuarios.php', 
    'espacios.php', 
    'enrolamiento.php', 
    'auditoria.php', 
    'config.php', 
    'rfid.php',
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
        
        // 2. Privilegios de SuperUsuario
        if (strtoupper($userRol) === 'SUPER ADMINISTRADOR') return true;
        
        if (!isset($_SESSION['permisos'])) return false;
        
        // 3. Permisos heredados del Rol Base
        $permisos = $_SESSION['permisos'];
        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }
        
        if (isset($permisos[$modulo])) {
            if ($permisos[$modulo] === true) return true;
            if (is_array($permisos[$modulo]) && isset($permisos[$modulo][$accion])) {
                return $permisos[$modulo][$accion] === true;
            }
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
    <script>
        const savedTheme = localStorage.getItem('sigrat_theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
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

        /* ================================================================
           MODELO DE SCROLL DEFINITIVO
           - html y body: NO hacen scroll (overflow: hidden)
           - .sidebar: posición fija, capa independiente, NO participa
             en ningún scroll
           - .main-container: ES el único contenedor con scroll vertical
        ================================================================ */
        html {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            height: 100%;
            overflow: hidden;
        }

        /* ==================== SIDEBAR ====================
           El sidebar es una CAPA INDEPENDIENTE anclada al viewport.
           No forma parte del flujo de la página ni de ningún scroll.
           Ningún padre tiene overflow, transform, sticky, ni translate.
        ====================================================== */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background-color: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            display: flex;
            flex-direction: column;
            /* Scroll independiente para móvil/tablet/laptops pequeñas */
            overflow-y: auto;
            overflow-x: hidden;
            transition: width 0.3s ease, min-width 0.3s ease;
        }

        /* Estilizar el scrollbar del sidebar para que no sea intrusivo */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
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

        .sidebar-header {
            padding: 24px 16px 20px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 8px;
        }

        .sidebar-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
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

        body.sidebar-collapsed .sidebar {
            width: 80px;
            min-width: 80px;
        }

        body.sidebar-collapsed .main-container {
            left: 80px;
        }

        body.sidebar-collapsed .sidebar-brand,
        body.sidebar-collapsed .nav-item span,
        body.sidebar-collapsed .sidebar-user-info,
        body.sidebar-collapsed .sidebar-logout span {
            display: none;
        }

        body.sidebar-collapsed .sidebar-header {
            padding: 20px 0 16px 0;
            flex-direction: column;
            gap: 16px;
        }

        body.sidebar-collapsed .sidebar-logo {
            margin: 0;
            width: 36px;
            height: 36px;
        }

        body.sidebar-collapsed .sidebar-toggle-btn {
            margin-left: 0;
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

        /* ==================== MAIN CONTAINER ====================
           Este es el ÚNICO contenedor que hace scroll vertical.
           Ocupa todo el alto del viewport y desplaza su contenido
           internamente, dejando al sidebar completamente inmóvil.
        ========================================================= */
        .main-container {
            position: fixed;
            top: 0;
            left: 240px;
            right: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: left 0.3s ease;
        }

        /* ==================== TOP BAR ==================== */
        .top-bar {
            height: 68px;
            min-height: 68px;
            flex-shrink: 0;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            /* sticky funciona dentro de main-container (el scroll container) */
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

        /* ==================== CONTENT ====================
           El <main> es el área de scroll real.
           flex: 1 + overflow-y: auto hacen que sea el único
           elemento que se desplaza dentro de main-container.
        ==================================================== */
        .content-padding {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px 28px;
            max-width: 100%;
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
            min-width: 0; 
            text-overflow: ellipsis;
        }
        .form-control:focus { outline: none; border-color: var(--accent-blue); background: white; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        label { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }

        /* ==================== MOBILE MENU BUTTON ==================== */
        .mobile-menu-btn {
            display: none;
            background: transparent;
            border: none;
            font-size: 24px;
            color: var(--text-primary);
            cursor: pointer;
            padding: 4px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .topbar-left-wrapper {
            display: flex;
            align-items: center;
            min-width: 0;
        }

        /* ==================== SIDEBAR OVERLAY (mobile) ==================== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 99;
        }

        body.sidebar-mobile-open .sidebar-overlay {
            display: block;
        }

        /* ==================== RESPONSIVE ==================== */

        /* Tablet: colapsar sidebar a solo íconos */
        @media (max-width: 992px) {
            .sidebar {
                width: 72px;
                min-width: 72px;
            }
            .main-container {
                left: 72px;
            }
            .sidebar-brand,
            .nav-item span,
            .sidebar-user-info,
            .sidebar-logout span {
                display: none;
            }
            .sidebar-header {
                padding: 20px 0 16px 0;
                flex-direction: column;
                gap: 12px;
                justify-content: center;
            }
            .sidebar-logo {
                margin: 0;
                width: 34px;
                height: 34px;
            }
            .sidebar-toggle-btn {
                margin-left: 0;
            }
            .nav-item {
                justify-content: center;
                padding: 11px 0;
            }
            .nav-item i {
                margin: 0;
                font-size: 20px;
                width: auto;
            }
            .sidebar-user {
                justify-content: center;
                padding: 16px 0;
                margin: 8px 8px 8px 8px;
            }
            .sidebar-logout {
                justify-content: center;
                padding: 10px 0;
            }
            .sidebar-logout i {
                margin: 0;
            }
            /* Ocultar el toggle de escritorio en tablet */
            .sidebar-toggle-btn {
                display: none;
            }
            /* Ocultar elementos sobrantes del topbar */
            .search-box {
                display: none;
            }
        }

        /* Móvil: sidebar oculto por defecto, se desliza al abrirse */
        @media (max-width: 768px) {
            .top-bar {
                padding: 0 16px;
            }
            .topbar-right {
                gap: 10px;
            }
            .mobile-menu-btn {
                display: flex;
                align-items: center;
            }
            .sidebar {
                width: 260px;
                min-width: 260px;
                /* Deslizamos el sidebar fuera del viewport en móvil */
                left: -260px;
                transition: left 0.3s ease;
                box-shadow: 4px 0 24px rgba(0,0,0,0.15);
            }
            /* Mostrar sidebar en móvil al abrir */
            body.sidebar-mobile-open .sidebar {
                left: 0;
            }
            body.sidebar-mobile-open .sidebar-brand,
            body.sidebar-mobile-open .nav-item span,
            body.sidebar-mobile-open .sidebar-user-info,
            body.sidebar-mobile-open .sidebar-logout span {
                display: block;
            }
            body.sidebar-mobile-open .sidebar-header {
                padding: 24px 16px 20px 20px;
                flex-direction: row;
                gap: 10px;
            }
            body.sidebar-mobile-open .sidebar-logo {
                width: 42px;
                height: 42px;
            }
            body.sidebar-mobile-open .nav-item {
                justify-content: flex-start;
                padding: 11px 14px;
            }
            body.sidebar-mobile-open .nav-item i {
                width: 20px;
                font-size: 18px;
            }
            body.sidebar-mobile-open .sidebar-user {
                justify-content: flex-start;
                padding: 16px 14px;
                margin: 8px 12px 12px 12px;
            }
            body.sidebar-mobile-open .sidebar-logout {
                justify-content: flex-start;
                padding: 10px 14px;
                margin: 0 12px 16px 12px;
            }

            .main-container {
                left: 0;
            }
            body.sidebar-collapsed .main-container {
                left: 0;
            }

            .topbar-date {
                display: none;
            }
            .top-bar {
                padding: 0 16px;
            }
            .topbar-left h1 {
                font-size: 15px;
            }
            .topbar-left p {
                display: none;
            }
            .content-padding {
                padding: 16px;
            }
        }

        /* Móvil pequeño */
        @media (max-width: 480px) {
            .topbar-right .topbar-icon-btn:not(#notifBtn) {
                display: none;
            }
            .top-bar {
                padding: 0 12px;
                height: 56px;
            }
            .content-padding {
                padding: 12px;
            }
        }

        /* ============================================================
           CSS GLOBAL RESPONSIVO — aplica a todos los módulos
        ============================================================ */

        /* --- Página genérica --- */
        .page-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Encabezado de página: título + botones */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .page-header h1 {
            font-size: 22px;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }
        .page-header p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* Barra de filtros / búsqueda */
        .page-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: white;
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .page-toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
            min-width: 0;
        }
        .page-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        /* Grids de tarjetas estadísticas */
        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .stats-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .stats-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        /* Tabla responsiva */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .table-responsive table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }
        .table-responsive th,
        .table-responsive td {
            white-space: nowrap;
        }

        /* Cards de datos */
        .data-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .data-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Grid 2 columnas para formularios/layouts */
        .grid-cols-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .grid-cols-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        /* ============================================================
           BREAKPOINTS GLOBALES
        ============================================================ */

        /* Laptop ≤ 1200px */
        @media (max-width: 1200px) {
            .stats-grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Tablet ≤ 992px */
        @media (max-width: 992px) {
            .stats-grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
            .grid-cols-3 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Tablet pequeña ≤ 768px */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .page-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .page-toolbar-left,
            .page-toolbar-right {
                width: 100%;
                flex-wrap: wrap;
            }
            .page-toolbar-right {
                justify-content: flex-start;
            }
            .grid-cols-2 {
                grid-template-columns: minmax(0, 1fr);
            }
            .grid-cols-3 {
                grid-template-columns: minmax(0, 1fr);
            }
            .stats-grid-2 {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        /* Móvil ≤ 640px */
        @media (max-width: 640px) {
            .stats-grid-4 {
                grid-template-columns: minmax(0, 1fr);
            }
            .stats-grid-3 {
                grid-template-columns: minmax(0, 1fr);
            }
            .page-header h1 {
                font-size: 18px;
            }
            .page-header-actions {
                width: 100%;
            }
            .page-header-actions .btn-primary,
            .page-header-actions .btn-secondary {
                flex: 1;
                justify-content: center;
            }
            .content-padding {
                padding: 14px;
            }
        }

        /* Móvil pequeño ≤ 480px */
        @media (max-width: 480px) {
            .page-header-actions {
                flex-direction: column;
            }
            .page-header-actions .btn-primary,
            .page-header-actions .btn-secondary {
                width: 100%;
            }
        }

        /* ============================================================
           FIXES PARA MÓDULOS CON INLINE STYLES
           Aplicado con !important para sobrescribir estilos en línea
        ============================================================ */

        /* Stats con inline style grid (usuarios, inventario, etc.) */
        @media (max-width: 1200px) {
            [style*="grid-template-columns: repeat(4, 1fr)"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (max-width: 640px) {
            [style*="grid-template-columns: repeat(4, 1fr)"],
            [style*="grid-template-columns: repeat(3, 1fr)"] {
                grid-template-columns: minmax(0, 1fr) !important;
            }
        }

        /* Grids de 2 columnas en módulos */
        @media (max-width: 768px) {
            [style*="grid-template-columns: 2fr 1fr"],
            [style*="grid-template-columns: 1.4fr 1fr"],
            [style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: minmax(0, 1fr) !important;
            }
        }

        /* Headers de módulos con inline flex */
        @media (max-width: 640px) {
            [style*="display: flex; justify-content: space-between; align-items: center"] {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }
        }

        /* Fix input de búsqueda ancho fijo */
        @media (max-width: 768px) {
            [style*="position: relative; width: 300px"] {
                width: 100% !important;
            }
            [style*="width: 300px"] input {
                width: 100% !important;
            }
        }

        /* Fix tablas sin wrapper — darles overflow horizontal */
        @media (max-width: 768px) {
            .card table,
            [class*="card"] table {
                min-width: 600px;
            }
        }
        /* ==================== DARK MODE (SLATE PREMIUM) ==================== */
        [data-theme="dark"] {
            --bg-main: #020617;
            --card-bg: #0f172a;
            --sidebar-bg: #020617;
            --topbar-bg: #020617;
            --border-color: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-blue: #3b82f6; /* Azul brillante para contraste oscuro */
            color-scheme: dark;
        }

        [data-theme="dark"] body { background: var(--bg-main); color: var(--text-primary); }
        [data-theme="dark"] .sidebar { background: var(--sidebar-bg); border-right: 1px solid var(--border-color); }
        [data-theme="dark"] .sidebar-logo, [data-theme="dark"] .sidebar-nav .nav-item { color: var(--text-secondary); }
        [data-theme="dark"] .sidebar-nav .nav-item:hover, [data-theme="dark"] .sidebar-nav .nav-item.active { background: rgba(255,255,255,0.05); color: var(--text-primary); }
        
        [data-theme="dark"] .card, [data-theme="dark"] .premium-card, [data-theme="dark"] .chart-card, [data-theme="dark"] .reservations-card, [data-theme="dark"] .sidebar-card, [data-theme="dark"] .stats-card, [data-theme="dark"] .premium-table-card, [data-theme="dark"] .dashboard-card { 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -2px rgba(0, 0, 0, 0.4);
        }
        
        [data-theme="dark"] .top-bar { background: var(--topbar-bg); border-bottom: 1px solid var(--border-color); }
        
        /* Formularios e Inputs */
        [data-theme="dark"] .form-control, [data-theme="dark"] .search-input, [data-theme="dark"] #globalSearchInput { 
            background: #1e293b; 
            border: 1px solid #334155; 
            color: var(--text-primary); 
        }
        [data-theme="dark"] .form-control:focus, [data-theme="dark"] .search-input:focus, [data-theme="dark"] #globalSearchInput:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        
        /* Tablas */
        [data-theme="dark"] .premium-table th { background: #020617; border-bottom: 2px solid var(--border-color) !important; border-top: none; color: var(--text-secondary); }
        [data-theme="dark"] .premium-table td { border-bottom: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .premium-table tr:hover { background: rgba(255,255,255,0.02); }
        
        /* Botones */
        [data-theme="dark"] .btn-primary { background: var(--accent-blue); color: #fff; border: none; }
        [data-theme="dark"] .btn-secondary, [data-theme="dark"] .btn-outline { 
            background: transparent !important; 
            color: var(--text-primary) !important; 
            border-color: #334155 !important; 
        }
        [data-theme="dark"] .btn-secondary:hover, [data-theme="dark"] .btn-outline:hover { background: #1e293b !important; border-color: #475569 !important; }
        
        /* Otros Componentes */
        [data-theme="dark"] .modal-content { background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5); }
        [data-theme="dark"] .modal-header { border-bottom: 1px solid var(--border-color); }
        [data-theme="dark"] .modal-footer { border-top: 1px solid var(--border-color); }
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] h5, [data-theme="dark"] h6 { color: var(--text-primary); }
        [data-theme="dark"] .stat-value { color: var(--text-primary); }
        [data-theme="dark"] .category-header { color: var(--text-primary); }
        [data-theme="dark"] .donut-number { color: var(--text-primary) !important; }
        [data-theme="dark"] .donut-legend-item { color: var(--text-secondary); }
        [data-theme="dark"] .notif-panel, [data-theme="dark"] #globalSearchResults { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important; }
        [data-theme="dark"] .notif-header { border-bottom: 1px solid var(--border-color); }
        [data-theme="dark"] .notif-item, [data-theme="dark"] .global-search-result { border-bottom: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .global-search-result:hover, [data-theme="dark"] .notif-item:hover { background: rgba(255,255,255,0.03) !important; }
        [data-theme="dark"] .notif-item.read { background: transparent !important; opacity: 0.5; }
    </style>
</head>
<body>
    <script>
        if (localStorage.getItem('sigrat_sidebar_collapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/sigrat_logo.png" alt="SIGRAT" class="sidebar-logo" style="filter: brightness(0) invert(1); drop-shadow(0 0 2px rgba(0,0,0,0.5));">
            <div class="sidebar-brand">
                <h2>SIGRAT</h2>
                <p>Control Integral</p>
            </div>
            <button id="sidebarToggle" class="sidebar-toggle-btn" title="Minimizar/Desplegar menú">
                <i class="bi bi-list" style="font-size: 22px;"></i>
            </button>
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
            <?php endif; ?>

            <?php if (hasPermission('Aprobaciones')): ?>
            <a href="aprobacion_reservas.php" class="nav-item <?php echo $currentPage == 'aprobacion_reservas.php' ? 'active' : ''; ?>">
                <i class="bi bi-check2-square"></i> <span>Aprobaciones</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('Prestamos')): ?>
            <a href="prestamos.php" class="nav-item <?php echo $currentPage == 'prestamos.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-left-right"></i> <span>Préstamos</span>
            </a>
            <?php endif; ?>

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

            <?php if (hasPermission('RFID')): ?>
            <a href="rfid.php" class="nav-item <?php echo $currentPage == 'rfid.php' ? 'active' : ''; ?>">
                <i class="bi bi-broadcast"></i> <span>Monitor RFID</span>
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

    <!-- Overlay para cerrar sidebar en móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-container">
        <header class="top-bar">
            <div class="topbar-left-wrapper">
                <button id="mobileMenuBtn" class="mobile-menu-btn">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-left">
                    <?php 
                    $generoUsuario = $_SESSION['genero'] ?? 'Masculino';
                    $saludoTexto = ($generoUsuario === 'Femenino') ? '¡Bienvenida' : '¡Bienvenido';
                    ?>
                    <h1><?php echo $saludoTexto; ?>, <?php echo explode(' ', $nombreUsuario)[0]; ?>!</h1>
                    <p>Resumen general del sistema</p>
                </div>
            </div>
            <div class="global-search-container" style="position: relative; margin-left: 24px; margin-right: 24px; flex: 1; max-width: 450px;">
                <div style="position: relative;">
                    <i class="bi bi-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="globalSearchInput" placeholder="Buscar por serie, TAG o nombre..." class="form-control" style="padding-left: 40px; border-radius: 20px; background: var(--bg-main); border: 1px solid var(--border-color); width: 100%;">
                </div>
                <div id="globalSearchResults" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; margin-top: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 100; display: none; overflow: hidden; max-height: 400px; overflow-y: auto;">
                </div>
            </div>

            <div class="topbar-right">
                <button class="topbar-icon-btn" id="themeToggleBtn" title="Modo Oscuro/Claro">
                    <i class="bi bi-moon"></i>
                </button>

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

        <script>
        // Theme Toggle Logic
        document.addEventListener('DOMContentLoaded', function() {
            const themeBtn = document.getElementById('themeToggleBtn');
            if (themeBtn) {
                const icon = themeBtn.querySelector('i');
                // Set initial icon
                if (document.documentElement.getAttribute('data-theme') === 'dark') {
                    icon.classList.remove('bi-moon');
                    icon.classList.add('bi-sun');
                }
                
                // Disparar evento para que gráficas y componentes se ajusten
                setTimeout(() => document.dispatchEvent(new Event('themeChanged')), 100);

                themeBtn.addEventListener('click', () => {
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    if (isDark) {
                        document.documentElement.removeAttribute('data-theme');
                        localStorage.setItem('sigrat_theme', 'light');
                        icon.classList.remove('bi-sun');
                        icon.classList.add('bi-moon');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        localStorage.setItem('sigrat_theme', 'dark');
                        icon.classList.remove('bi-moon');
                        icon.classList.add('bi-sun');
                    }
                    document.dispatchEvent(new Event('themeChanged'));
                });
            }
        });

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

            // Mobile Menu Toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function closeMobileSidebar() {
                document.body.classList.remove('sidebar-mobile-open');
            }

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.body.classList.toggle('sidebar-mobile-open');
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeMobileSidebar);
            }

            // Cerrar sidebar móvil al hacer click en un link del nav
            document.querySelectorAll('.sidebar .nav-item').forEach(function(link) {
                link.addEventListener('click', closeMobileSidebar);
            });
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

            // AUTO-RESPONSIVE TABLES: Envolver todas las tablas automáticamente
            document.querySelectorAll("table").forEach(table => {
                if (!table.parentElement.classList.contains("table-responsive")) {
                    const wrapper = document.createElement("div");
                    wrapper.className = "table-responsive";
                    wrapper.style.overflowX = "auto";
                    wrapper.style.width = "100%";
                    wrapper.style.display = "block";
                    wrapper.style.WebkitOverflowScrolling = "touch";
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
            // Global Search Logic
            const searchInput = document.getElementById('globalSearchInput');
            const searchContainer = document.querySelector('.global-search-container');
            const searchResults = document.getElementById('globalSearchResults');
            let searchTimeout;

            if (searchInput) {
                searchContainer.style.display = 'block'; // Mostrar si JS está activo

                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    const query = e.target.value.trim();
                    
                    if (query.length < 2) {
                        searchResults.style.display = 'none';
                        return;
                    }

                    searchTimeout = setTimeout(() => {
                        fetch(`../backend/api/global_search.php?q=${encodeURIComponent(query)}`)
                            .then(res => res.json())
                            .then(data => {
                                searchResults.innerHTML = '';
                                if (data.length === 0) {
                                    searchResults.innerHTML = '<div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">No se encontraron resultados</div>';
                                } else {
                                    data.forEach(item => {
                                        const div = document.createElement('a');
                                        div.href = item.url;
                                        div.className = 'global-search-result';
                                        div.style.cssText = 'display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.2s;';
                                        
                                        const icon = item.type === 'activo' ? 'bi-box-seam' : 'bi-person';
                                        const color = item.type === 'activo' ? 'var(--accent-blue)' : '#10b981';
                                        
                                        div.innerHTML = `
                                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; margin-right: 12px; color: ${color};">
                                                <i class="bi ${icon}"></i>
                                            </div>
                                            <div style="flex: 1; overflow: hidden;">
                                                <div style="font-weight: 600; font-size: 13px; margin-bottom: 2px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">${item.title}</div>
                                                <div style="font-size: 11px; color: var(--text-muted);">${item.subtitle}</div>
                                            </div>
                                        `;
                                        searchResults.appendChild(div);
                                    });
                                }
                                searchResults.style.display = 'block';
                            })
                            .catch(err => console.error(err));
                    }, 300);
                });

                // Cerrar al clickear afuera
                document.addEventListener('click', function(e) {
                    if (!searchContainer.contains(e.target)) {
                        searchResults.style.display = 'none';
                    }
                });
                
                searchInput.addEventListener('focus', function() {
                    if (searchInput.value.trim().length >= 2) {
                        searchResults.style.display = 'block';
                    }
                });
            }
        });
        </script>
        <main class="content-padding">
