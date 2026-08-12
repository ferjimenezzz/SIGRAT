<?php
/**
 * @file header.php
 * @summary Plantilla principal superior y barra de navegación (Navbar) del Frontend.
 * @description Inicia la sesión de usuario, configura los encabezados HTTP contra caché, incluye librerías visuales (Bootstrap 5, Lucide Icons, JetBrains Mono) y renderiza la barra de navegación lateral y superior de manera dinámica según el rol del usuario autenticado.
 * @package Frontend\Templates
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

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

// Restricción de navegación para el rol Maestro / Docente / Profesor
$userRolCurrent = isset($_SESSION['rol']) ? strtoupper(trim($_SESSION['rol'])) : '';
$isMaestroUser = (
    strpos($userRolCurrent, 'MAESTRO') !== false || 
    strpos($userRolCurrent, 'DOCENTE') !== false || 
    strpos($userRolCurrent, 'PROFESOR') !== false
);

if ($isMaestroUser) {
    $allowedMaestroPages = ['calendario.php', 'aprobacion_reservas.php', 'perfil.php', 'manual_usuario.php'];
    if (!in_array($currentPage, $allowedMaestroPages)) {
        header("Location: calendario.php");
        exit();
    }
}

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
    $redirectParam = !empty($_SERVER['REQUEST_URI']) ? urlencode($_SERVER['REQUEST_URI']) : '';
    header("Location: login.php" . ($redirectParam ? "?redirect=" . $redirectParam : ""));
    exit();
}

if (!function_exists('hasPermission')) {
    function hasPermission($modulo, $accion = 'read') {
        if (!isset($_SESSION['rol'])) return false;
        $userRol = strtoupper(trim($_SESSION['rol']));
        
        // Identificar si el usuario es Maestro / Docente / Profesor
        $isMaestro = (
            strpos($userRol, 'MAESTRO') !== false || 
            strpos($userRol, 'DOCENTE') !== false || 
            strpos($userRol, 'PROFESOR') !== false
        );

        if ($isMaestro) {
            // El Maestro SOLO tiene permiso para Calendario y Aprobaciones
            if ($modulo === 'Calendario' || $modulo === 'Aprobaciones') {
                return true;
            }
            return false;
        }

        // Privilegios de SuperUsuario
        if ($userRol === 'SUPER ADMINISTRADOR') return true;
        
        if (!isset($_SESSION['permisos'])) {
            if (strpos($userRol, 'ADMIN') !== false) return true;
            if ($modulo === 'Dashboard' || $modulo === 'Calendario') return true;
            return false;
        }
        
        // Permisos heredados del Rol Base
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

        if ($modulo === 'Dashboard' || $modulo === 'Calendario') return true;

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


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT - Sistema Universitario</title>
        <link rel="icon" type="image/png" href="assets/images/sigrat_icon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- React, ReactDOM, MUI y TutorialGuide de forma global -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>
    <script src="assets/js/tutorial-guide.js"></script>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
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
            padding: 24px 20px 20px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 8px;
        }

        .sidebar-logo {
            width: 32px;
            height: 32px;
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

        .sidebar-user:hover {
            background: rgba(255,255,255,0.04);
        }

        .sidebar-user-chevron {
            color: #64748b;
            font-size: 12px;
            margin-left: auto;
            transition: color 0.2s, transform 0.2s;
        }

        .sidebar-user:hover .sidebar-user-chevron {
            color: #e2e8f0;
            transform: translateX(2px);
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

        /* === HELP CENTER BUTTON === */
        .sidebar-help-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            margin: 0 12px 4px 12px;
            border-radius: 10px;
            color: #8892a5;
            background: none;
            border: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            width: calc(100% - 24px);
            text-align: left;
            transition: all 0.2s;
        }
        .sidebar-help-btn i { font-size: 18px; flex-shrink: 0; }
        .sidebar-help-btn:hover {
            background: rgba(37, 99, 235, 0.12);
            color: #93c5fd;
        }

        /* === HELP CENTER DRAWER === */
        .hc-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9998;
            backdrop-filter: blur(2px);
            animation: hcFadeIn 0.25s ease;
        }
        .hc-overlay.open { display: block; }

        .hc-drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: 480px;
            max-width: 100vw;
            height: 100vh;
            background: #ffffff;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -8px 0 40px rgba(0, 0, 0, 0.15);
        }
        .hc-drawer.open { transform: translateX(0); }

        .hc-head {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            padding: 24px 24px 20px 24px;
            flex: none;
        }
        .hc-head-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .hc-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hc-title-icon {
            width: 40px; height: 40px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white;
        }
        .hc-title h2 {
            font-size: 18px; font-weight: 800;
            color: #ffffff; margin: 0;
            letter-spacing: -0.3px;
        }
        .hc-title p {
            font-size: 11px; color: rgba(255,255,255,0.65);
            margin: 2px 0 0 0; font-weight: 500;
        }
        .hc-close-btn {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.15);
            border: none; border-radius: 8px;
            color: white; font-size: 18px;
            cursor: pointer; display: flex;
            align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .hc-close-btn:hover { background: rgba(255,255,255,0.25); }

        /* Buscador */
        .hc-search-wrap {
            position: relative;
        }
        .hc-search-icon {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.55); font-size: 15px;
        }
        .hc-search {
            width: 100%; padding: 10px 14px 10px 38px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 10px; color: white;
            font-size: 13px; font-weight: 500;
            font-family: inherit; outline: none;
            box-sizing: border-box;
            transition: background 0.2s, border 0.2s;
        }
        .hc-search::placeholder { color: rgba(255,255,255,0.55); }
        .hc-search:focus {
            background: rgba(255,255,255,0.22);
            border-color: rgba(255,255,255,0.5);
        }

        /* Tabs */
        .hc-tabs {
            display: flex;
            gap: 4px;
            padding: 14px 24px 0 24px;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
            flex: none;
        }
        .hc-tab {
            padding: 9px 16px;
            font-size: 12px; font-weight: 700;
            color: #64748b; cursor: pointer;
            border: none; background: none;
            border-bottom: 2px solid transparent;
            font-family: inherit;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .hc-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .hc-tab:hover:not(.active) { color: #1e293b; }

        /* Cuerpo scrollable */
        .hc-body {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px 24px;
        }
        .hc-body::-webkit-scrollbar { width: 5px; }
        .hc-body::-webkit-scrollbar-track { background: transparent; }
        .hc-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* Panel tab */
        .hc-panel { display: none; }
        .hc-panel.active { display: block; }

        /* Sección de módulos — grid */
        .hc-modules-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 8px;
        }
        .hc-module-card {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }
        .hc-module-card:hover {
            border-color: #bfdbfe;
            background: #eff6ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37,99,235,0.08);
        }
        .hc-module-card.selected {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .hc-mod-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: white; flex-shrink: 0;
        }
        .hc-mod-name {
            font-size: 12px; font-weight: 700;
            color: #1e293b; line-height: 1.2;
        }
        .hc-mod-desc-short {
            font-size: 10px; color: #64748b;
            font-weight: 500; margin-top: 1px;
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Detalle de módulo */
        .hc-module-detail {
            display: none;
            animation: hcSlideDown 0.2s ease;
        }
        .hc-module-detail.visible { display: block; }

        .hc-detail-back {
            display: flex; align-items: center; gap: 6px;
            background: none; border: none;
            color: #2563eb; font-size: 12px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            padding: 0 0 16px 0;
            transition: opacity 0.2s;
        }
        .hc-detail-back:hover { opacity: 0.7; }

        .hc-detail-header {
            display: flex; align-items: center; gap: 14px;
            padding: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 16px;
        }
        .hc-detail-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: white; flex-shrink: 0;
        }
        .hc-detail-header h3 {
            font-size: 16px; font-weight: 800;
            color: #0f172a; margin: 0 0 4px 0;
        }
        .hc-detail-header p {
            font-size: 12px; color: #64748b;
            font-weight: 500; margin: 0; line-height: 1.5;
        }

        .hc-section-title {
            font-size: 10px; font-weight: 800;
            color: #94a3b8; text-transform: uppercase;
            letter-spacing: 0.8px; margin: 0 0 10px 0;
        }
        .hc-func-list {
            list-style: none; padding: 0; margin: 0 0 20px 0;
        }
        .hc-func-list li {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px; color: #334155;
            font-weight: 500; line-height: 1.4;
        }
        .hc-func-list li:last-child { border-bottom: none; }
        .hc-func-list li::before {
            content: '';
            width: 6px; height: 6px; border-radius: 50%;
            background: #2563eb; flex-shrink: 0; margin-top: 5px;
        }
        .hc-tips-list {
            display: flex; flex-direction: column; gap: 8px;
            margin-bottom: 20px;
        }
        .hc-tip-item {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 10px 12px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            font-size: 12px; color: #78350f;
            font-weight: 500; line-height: 1.5;
        }
        .hc-tip-item i { font-size: 14px; color: #d97706; flex-shrink: 0; margin-top: 1px; }

        /* FAQ */
        .hc-faq-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 8px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .hc-faq-item.open { border-color: #bfdbfe; }
        .hc-faq-q {
            display: flex; align-items: center;
            justify-content: space-between; gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            background: #f8fafc;
            font-size: 13px; font-weight: 700;
            color: #1e293b;
            transition: background 0.2s;
        }
        .hc-faq-q:hover { background: #f1f5f9; }
        .hc-faq-item.open .hc-faq-q { background: #eff6ff; color: #1d4ed8; }
        .hc-faq-chevron { font-size: 16px; flex-shrink: 0; transition: transform 0.25s; }
        .hc-faq-item.open .hc-faq-chevron { transform: rotate(180deg); }
        .hc-faq-a {
            max-height: 0; overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            font-size: 13px; color: #475569;
            font-weight: 500; line-height: 1.6;
            padding: 0 16px;
            background: white;
        }
        .hc-faq-item.open .hc-faq-a {
            max-height: 200px;
            padding: 12px 16px;
        }

        /* Footer con botón de manual */
        .hc-footer {
            padding: 16px 24px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            flex-shrink: 0;
        }
        .hc-manual-btn {
            display: flex; align-items: center; justify-content: center;
            gap: 8px; width: 100%;
            padding: 11px 20px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: white; border: none; border-radius: 10px;
            font-size: 13px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            text-decoration: none;
            transition: opacity 0.2s, transform 0.2s;
        }
        .hc-manual-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Chip de no resultados */
        .hc-no-results {
            text-align: center; padding: 32px 16px;
            color: #94a3b8; font-size: 13px; font-weight: 500;
        }
        .hc-no-results i { font-size: 36px; display: block; margin-bottom: 8px; }

        /* Colapsar help btn en sidebar colapsado */
        body.sidebar-collapsed .sidebar-help-btn span { display: none; }
        body.sidebar-collapsed .sidebar-help-btn {
            justify-content: center;
            padding: 6px 0;
            margin-bottom: 4px;
        }


        /* ====== LOGOUT CONFIRMATION MODAL ====== */
        .logout-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,0.45); backdrop-filter: blur(3px);
            z-index: 4000;
        }
        .logout-overlay.open { display: block; }
        .logout-modal {
            display: none; position: fixed;
            top: 50%; left: 50%; transform: translate(-50%, -48%);
            background: #fff; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15,23,42,0.18);
            padding: 32px 28px 24px;
            width: 340px; max-width: calc(100vw - 32px);
            z-index: 4001; text-align: center;
            animation: logoutPop 0.22s cubic-bezier(.34,1.56,.64,1) forwards;
        }
        .logout-modal.open { display: block; }
        @keyframes logoutPop {
            from { opacity: 0; transform: translate(-50%, -44%) scale(0.94); }
            to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        .logout-icon-wrap {
            width: 52px; height: 52px; border-radius: 14px;
            background: #fee2e2;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .logout-icon-wrap i { font-size: 22px; color: #dc2626; }
        .logout-title {
            font-size: 16px; font-weight: 800; color: #0f172a;
            margin-bottom: 8px;
        }
        .logout-msg {
            font-size: 13px; color: #64748b; line-height: 1.6;
            margin-bottom: 24px;
        }
        .logout-actions {
            display: flex; gap: 10px;
        }
        .logout-btn-cancel {
            flex: 1; padding: 10px;
            background: #f1f5f9; color: #475569;
            border: none; border-radius: 10px;
            font-size: 13px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            transition: background 0.15s;
        }
        .logout-btn-cancel:hover { background: #e2e8f0; }
        .logout-btn-confirm {
            flex: 1; padding: 10px;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white; border-radius: 10px;
            font-size: 13px; font-weight: 700;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            transition: opacity 0.15s;
        }
        .logout-btn-confirm:hover { opacity: 0.88; }
        @media (max-width: 992px) {
            .sidebar-help-btn span { display: none; }
            .sidebar-help-btn { justify-content: center; padding: 10px 0; }
            .hc-drawer { width: 420px; }
        }
        @media (max-width: 768px) {
            body.sidebar-mobile-open .sidebar-help-btn span { display: block; }
            body.sidebar-mobile-open .sidebar-help-btn { justify-content: flex-start; padding: 10px 14px; }
            .hc-drawer { width: 100vw; }
            .hc-modules-grid { grid-template-columns: 1fr; }
        }

        @keyframes hcFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes hcSlideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

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
        body.sidebar-collapsed .sidebar-user-chevron,
        body.sidebar-collapsed .sidebar-logout span {
            display: none;
        }

        body.sidebar-collapsed .sidebar-header {
            padding: 12px 0 8px 0;
            flex-direction: column;
            gap: 8px;
        }

        body.sidebar-collapsed .sidebar-logo {
            margin: 0;
            width: 28px;
            height: 28px;
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
            padding: 8px 0;
            margin: 4px 8px;
        }

        body.sidebar-collapsed .sidebar-logout {
            justify-content: center;
            padding: 6px 0;
            margin-bottom: 8px;
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
            top: 2px;
            right: 2px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 10px;
            font-weight: 800;
            border-radius: 999px;
            border: 2px solid white;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
            animation: notifPulse 2.5s infinite ease-in-out;
        }

        @keyframes notifPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }

        /* ==================== NOTIFICATIONS PANEL ==================== */
        .notif-panel {
            position: absolute;
            top: 48px;
            right: 0;
            width: 375px;
            max-width: calc(100vw - 32px);
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(0,0,0,0.05);
            z-index: 1000;
            display: none;
            flex-direction: column;
            overflow: hidden;
            text-align: left;
            animation: notifSlide 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes notifSlide {
            from { opacity: 0; transform: translateY(-8px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .notif-panel.show {
            display: flex;
        }

        .notif-header {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .notif-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notif-header h3 {
            font-size: 14px;
            font-weight: 800;
            margin: 0;
            color: #0f172a;
            letter-spacing: -0.2px;
        }

        .notif-unread-count {
            font-size: 10px;
            font-weight: 700;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        .notif-mark-all-btn {
            background: transparent;
            border: none;
            color: #2563eb;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .notif-mark-all-btn:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }

        /* Pestañas de Notificación */
        .notif-tabs {
            display: flex;
            background: #f8fafc;
            padding: 5px 12px;
            gap: 6px;
            border-bottom: 1px solid #f1f5f9;
        }

        .notif-tab {
            flex: 1;
            padding: 6px 12px;
            border: none;
            background: transparent;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .notif-tab:hover {
            color: #1e293b;
            background: rgba(0,0,0,0.03);
        }

        .notif-tab.active {
            background: #ffffff;
            color: #2563eb;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .notif-tab-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 10px;
            background: #e2e8f0;
            color: #475569;
        }

        .notif-tab.active .notif-tab-badge {
            background: #dbeafe;
            color: #1d4ed8;
        }

        /* Lista de Notificaciones */
        .notif-list {
            max-height: 330px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .notif-list::-webkit-scrollbar {
            width: 5px;
        }
        .notif-list::-webkit-scrollbar-track {
            background: transparent;
        }
        .notif-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            transition: background 0.15s ease;
            position: relative;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-item:hover {
            background: #f8fafc;
        }

        .notif-item.unread {
            background: #f0f9ff;
        }
        .notif-item.unread:hover {
            background: #e0f2fe;
        }

        .notif-item.read {
            opacity: 0.75;
        }

        .notif-icon-wrap {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notif-icon-prestamo {
            background: #ffedd5;
            color: #c2410c;
        }
        .notif-icon-reserva {
            background: #f3e8ff;
            color: #7c3aed;
        }
        .notif-icon-sistema, .notif-icon-rfid {
            background: #dcfce7;
            color: #15803d;
        }
        .notif-icon-default {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-content-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 3px;
        }

        .notif-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .notif-unread-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2563eb;
            flex-shrink: 0;
        }

        .notif-text {
            font-size: 11.5px;
            color: #475569;
            line-height: 1.45;
            word-break: break-word;
        }

        .notif-time {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }

        .notif-empty {
            padding: 36px 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .notif-empty i {
            font-size: 32px;
            color: #cbd5e1;
        }

        .notif-empty p {
            font-size: 12.5px;
            font-weight: 600;
            color: #64748b;
            margin: 0;
        }

        .notif-empty span {
            font-size: 11px;
            color: #94a3b8;
        }

        .notif-footer {
            padding: 10px 16px;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            text-align: center;
        }

        .notif-footer-link {
            font-size: 11.5px;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.15s;
        }

        .notif-footer-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
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


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
           El <main> es el área de scroll real.
           flex: 1 + overflow-y: auto hacen que sea el único
           elemento que se desplaza dentro de main-container.
        ==================================================== */
        .content-padding {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px 28px 0;
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
            .sidebar-user-chevron,
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
            body.sidebar-mobile-open .sidebar-user-chevron,
            body.sidebar-mobile-open .sidebar-logout span {
                display: block;
            }
            body.sidebar-mobile-open .sidebar-header {
            padding: 24px 20px 20px 20px;
            flex-direction: row;
            gap: 14px;
        }
            body.sidebar-mobile-open .sidebar-logo {
            width: 32px;
            height: 32px;
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
            <img src="assets/images/sigrat_icon.png" alt="SIGRAT" class="sidebar-logo" style="filter: brightness(0) invert(1); drop-shadow(0 0 2px rgba(0,0,0,0.5));">
            <div class="sidebar-brand">
                <h2>SIGRAT</h2>
                <p>Control Integral</p>
            </div>
            <button id="sidebarToggle" class="sidebar-toggle-btn" title="Minimizar/Desplegar menú">
                <i class="bi bi-list" style="font-size: 22px;"></i>
            </button>
        </div>

        <nav class="nav-menu">
            <?php if (hasPermission('Dashboard')): ?>
            <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('Calendario')): ?>
            <a href="calendario.php" class="nav-item <?php echo $currentPage == 'calendario.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar3"></i> <span>Calendario</span>
            </a>
            <?php endif; ?>
            
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
            <i class="bi bi-chevron-right sidebar-user-chevron"></i>
        </a>
        <!-- Centro de Ayuda -->
        <button class="sidebar-help-btn" id="helpCenterBtn" onclick="openHelpCenter()" title="Centro de Ayuda">
            <i class="bi bi-question-circle"></i> <span>Centro de ayuda</span>
        </button>
        <a href="#" class="sidebar-logout" onclick="openLogoutConfirm(); return false;">
            <i class="bi bi-box-arrow-left"></i> <span>Cerrar sesión</span>
        </a>
    </aside>

    <!-- Overlay para cerrar sidebar en móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ====== CERRAR SESIÓN: CONFIRMACIÓN ====== -->
    <div class="logout-overlay" id="logoutOverlay" onclick="closeLogoutConfirm()"></div>
    <div class="logout-modal" id="logoutModal" role="dialog" aria-modal="true" aria-label="Confirmar cierre de sesión">
        <div class="logout-icon-wrap">
            <i class="bi bi-box-arrow-left"></i>
        </div>
        <h3 class="logout-title">Cerrar sesión</h3>
        <p class="logout-msg">¿Deseas cerrar tu sesión actual?<br>Tendrás que iniciar sesión nuevamente para acceder al sistema.</p>
        <div class="logout-actions">
            <button class="logout-btn-cancel" onclick="closeLogoutConfirm()">Cancelar</button>
            <a href="logout.php" class="logout-btn-confirm"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </div>
    </div>

    <!-- ====== CENTRO DE AYUDA ====== -->
    <div class="hc-overlay" id="hcOverlay" onclick="closeHelpCenter()"></div>
    <div class="hc-drawer" id="hcDrawer" role="dialog" aria-modal="true" aria-label="Centro de Ayuda">
        <!-- Header -->
        <div class="hc-head">
            <div class="hc-head-top">
                <div class="hc-title">
                    <div class="hc-title-icon"><i class="bi bi-question-circle-fill"></i></div>
                    <div>
                        <h2>Centro de Ayuda</h2>
                        <p>SIGRAT &mdash; Guía de usuario</p>
                    </div>
                </div>
                <button class="hc-close-btn" onclick="closeHelpCenter()" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="hc-search-wrap">
                <i class="bi bi-search hc-search-icon"></i>
                <input type="text" class="hc-search" id="hcSearch" placeholder="Buscar módulo, función o pregunta..." autocomplete="off">
            </div>
        </div>

        <!-- Tabs -->
        <div class="hc-tabs">
            <button class="hc-tab active" data-panel="hcPanelModules" onclick="switchHcTab(this)">Módulos</button>
            <button class="hc-tab" data-panel="hcPanelFaq" onclick="switchHcTab(this)">Preguntas frecuentes</button>
        </div>

        <!-- Body -->
        <div class="hc-body">

            <!-- Panel: Módulos -->
            <div class="hc-panel active" id="hcPanelModules">
                <!-- Vista: lista de módulos (grid) -->
                <div id="hcModulesList">
                    <p class="hc-section-title" style="margin-bottom:12px;">Selecciona un módulo para ver su guía</p>
                    <div class="hc-modules-grid" id="hcModulesGrid"></div>
                </div>
                <!-- Vista: detalle de módulo -->
                <div class="hc-module-detail" id="hcModuleDetail">
                    <button class="hc-detail-back" onclick="backToModuleList()">
                        <i class="bi bi-arrow-left"></i> Volver a módulos
                    </button>
                    <div id="hcDetailContent"></div>
                </div>
            </div>

            <!-- Panel: FAQ -->
            <div class="hc-panel" id="hcPanelFaq">
                <p class="hc-section-title" style="margin-bottom:12px;">Preguntas frecuentes</p>
                <div id="hcFaqList"></div>
                <div class="hc-no-results" id="hcFaqNoResults" style="display:none;">
                    <i class="bi bi-search"></i>
                    No se encontraron resultados para tu búsqueda.
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="hc-footer">
            <button class="hc-manual-btn" id="hcTourBtn" style="display: none; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px 20px; background: #ffffff; color: #2563eb; border: 1.5px solid #2563eb; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; margin-bottom: 8px; transition: all 0.2s;" onclick="if (window.triggerTutorialGuide) { window.triggerTutorialGuide(); closeHelpCenter(); }" onmouseover="this.style.backgroundColor='#eff6ff'" onmouseout="this.style.backgroundColor='#ffffff'">
                <i class="bi bi-compass"></i>
                Ver recorrido guiado
            </button>
            <a href="manual_usuario.php" target="_blank" class="hc-manual-btn" id="hcManualBtn">
                <i class="bi bi-file-earmark-text"></i>
                Ver / Descargar Manual de Usuario (PDF)
            </a>
        </div>
    </div>

    <script>
    // ====== CERRAR SESIÓN: CONFIRMACIÓN ====== //
    function openLogoutConfirm() {
        document.getElementById('logoutOverlay').classList.add('open');
        document.getElementById('logoutModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeLogoutConfirm() {
        document.getElementById('logoutOverlay').classList.remove('open');
        document.getElementById('logoutModal').classList.remove('open');
        document.body.style.overflow = '';
    }



    // ESC cierra cualquier modal activo
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('logoutModal').classList.contains('open')) closeLogoutConfirm();
        }
    });
    </script>

    <script>
    // ====== HELP CENTER ENGINE ====== //
    let hcData = null;
    let hcActiveModuleId = null;

    // Mapa de permisos generado desde PHP según el rol y permisos del usuario actual
    // Cada clave corresponde al campo "permissionKey" en help_center.json
    // Módulos sin clave (Dashboard, Calendario) son siempre visibles
    const HC_USER_PERMISSIONS = {
        'Usuarios':     <?php echo hasPermission('Usuarios')    ? 'true' : 'false'; ?>,
        'Espacios':     <?php echo hasPermission('Espacios')    ? 'true' : 'false'; ?>,
        'Aprobaciones': <?php echo hasPermission('Aprobaciones')? 'true' : 'false'; ?>,
        'Prestamos':    <?php echo hasPermission('Prestamos')   ? 'true' : 'false'; ?>,
        'Inventario':   <?php echo hasPermission('Inventario')  ? 'true' : 'false'; ?>,
        'Auditorias':   <?php echo hasPermission('Auditorias')  ? 'true' : 'false'; ?>,
        'RFID':         <?php echo hasPermission('RFID')        ? 'true' : 'false'; ?>
    };

    /**
     * Filtra un array de módulos según los permisos del usuario.
     * Un módulo sin campo "permissionKey" se considera de acceso libre (Dashboard, Calendario).
     */
    function filterModulesByPermission(modules) {
        return modules.filter(mod => {
            if (!mod.permissionKey) return true; // Acceso libre
            return HC_USER_PERMISSIONS[mod.permissionKey] === true;
        });
    }

    function openHelpCenter() {
        document.getElementById('hcOverlay').classList.add('open');
        document.getElementById('hcDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';
        document.getElementById('hcSearch').focus();
        if (!hcData) loadHelpData();
    }

    function closeHelpCenter() {
        document.getElementById('hcOverlay').classList.remove('open');
        document.getElementById('hcDrawer').classList.remove('open');
        document.body.style.overflow = '';
    }

    function loadHelpData() {
        fetch('assets/data/help_center.json?v=' + Date.now())
            .then(r => r.json())
            .then(data => {
                hcData = data;
                // Filtrar módulos según permisos antes de renderizar
                const allowedModules = filterModulesByPermission(data.modules);
                renderModulesGrid(allowedModules);
                renderFaq(data.faq);
            })
            .catch(() => {
                document.getElementById('hcModulesGrid').innerHTML = '<p style="color:#94a3b8;font-size:13px;">No se pudo cargar el contenido. Intenta de nuevo.</p>';
            });
    }

    function renderModulesGrid(modules) {
        const grid = document.getElementById('hcModulesGrid');
        grid.innerHTML = '';
        if (!modules.length) {
            grid.innerHTML = '<div class="hc-no-results"><i class="bi bi-search"></i>Sin resultados para tu búsqueda.</div>';
            return;
        }
        modules.forEach(mod => {
            const card = document.createElement('button');
            card.className = 'hc-module-card';
            card.setAttribute('data-id', mod.id);
            card.innerHTML = `
                <div class="hc-mod-icon" style="background:${mod.color}">
                    <i class="bi ${mod.icon}"></i>
                </div>
                <div style="min-width:0;flex:1;">
                    <div class="hc-mod-name">${mod.name}</div>
                </div>
                <i class="bi bi-chevron-right" style="font-size:12px;color:#94a3b8;flex-shrink:0;"></i>
            `;
            card.addEventListener('click', () => showModuleDetail(mod));
            grid.appendChild(card);
        });
    }

    function showModuleDetail(mod) {
        hcActiveModuleId = mod.id;
        document.getElementById('hcModulesList').style.display = 'none';
        const detail = document.getElementById('hcModuleDetail');
        detail.classList.add('visible');

        const funcsHtml = mod.functions.map(f => `<li>${f}</li>`).join('');
        const tipsHtml = mod.tips.map(t => `<div class="hc-tip-item"><i class="bi bi-lightbulb"></i><span>${t}</span></div>`).join('');

        document.getElementById('hcDetailContent').innerHTML = `
            <div class="hc-detail-header">
                <div class="hc-detail-icon" style="background:${mod.color}">
                    <i class="bi ${mod.icon}"></i>
                </div>
                <div>
                    <h3>${mod.name}</h3>
                    <p>${mod.description}</p>
                </div>
            </div>
            <p class="hc-section-title">Funciones principales</p>
            <ul class="hc-func-list">${funcsHtml}</ul>
            <p class="hc-section-title">Consejos de uso</p>
            <div class="hc-tips-list">${tipsHtml}</div>
        `;
        document.querySelector('.hc-body').scrollTop = 0;
    }

    function backToModuleList() {
        hcActiveModuleId = null;
        document.getElementById('hcModulesList').style.display = '';
        document.getElementById('hcModuleDetail').classList.remove('visible');
        document.querySelector('.hc-body').scrollTop = 0;
    }

    function renderFaq(faqs) {
        const list = document.getElementById('hcFaqList');
        list.innerHTML = '';
        faqs.forEach((item, i) => {
            const div = document.createElement('div');
            div.className = 'hc-faq-item';
            div.setAttribute('data-question', item.question.toLowerCase());
            div.setAttribute('data-answer', item.answer.toLowerCase());
            if (item.keywords) div.setAttribute('data-keywords', item.keywords.toLowerCase());
            div.innerHTML = `
                <div class="hc-faq-q" onclick="toggleFaq(this.parentElement)">
                    <span>${item.question}</span>
                    <i class="bi bi-chevron-down hc-faq-chevron"></i>
                </div>
                <div class="hc-faq-a">${item.answer}</div>
            `;
            list.appendChild(div);
        });
    }

    function toggleFaq(item) {
        const wasOpen = item.classList.contains('open');
        document.querySelectorAll('.hc-faq-item').forEach(el => el.classList.remove('open'));
        if (!wasOpen) item.classList.add('open');
    }

    function switchHcTab(btn, clearSearch = true) {
        document.querySelectorAll('.hc-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.hc-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.getAttribute('data-panel')).classList.add('active');
        
        if (clearSearch) {
            document.getElementById('hcSearch').value = '';
            if (hcData) {
                renderModulesGrid(filterModulesByPermission(hcData.modules));
                renderFaq(hcData.faq);
                backToModuleList();
            }
        }
    }

    // Buscador en tiempo real
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('hcSearch');
        if (!searchInput) return;
        searchInput.addEventListener('input', function() {
            const rawQ = this.value.trim();
            if (!hcData) return;

            // Si estamos viendo el detalle, volver al grid
            backToModuleList();

            if (!rawQ) {
                // Reset a estado original
                renderModulesGrid(filterModulesByPermission(hcData.modules));
                document.querySelectorAll('.hc-faq-item').forEach(el => el.style.display = '');
                document.getElementById('hcFaqNoResults').style.display = 'none';
                return;
            }

            // Normalización: minúsculas y sin acentos
            const normalize = str => str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const qNorm = normalize(rawQ);
            
            // Tokenizar: buscar cualquier coincidencia por palabra mayor a 2 letras
            const tokens = qNorm.split(/\s+/).filter(t => t.length > 2);
            if (tokens.length === 0) tokens.push(qNorm); // Por si escribe algo de 2 letras

            // Filtrado Inteligente en Módulos
            const allowed = filterModulesByPermission(hcData.modules);
            const filteredMods = allowed.filter(m => {
                const text = normalize(m.name + " " + m.description + " " + m.functions.join(" ") + " " + m.tips.join(" ") + " " + (m.keywords || ""));
                // Si TODAS las palabras buscadas aparecen en el texto (búsqueda más precisa)
                return tokens.every(t => text.includes(t));
            });
            renderModulesGrid(filteredMods);

            // Filtrado Inteligente en FAQ
            const items = document.querySelectorAll('.hc-faq-item');
            let visibleFaqCount = 0;
            items.forEach(item => {
                const kws = item.getAttribute('data-keywords') || "";
                const text = normalize(item.getAttribute('data-question') + " " + item.getAttribute('data-answer') + " " + kws);
                const match = tokens.every(t => text.includes(t));
                item.style.display = match ? '' : 'none';
                if (match) visibleFaqCount++;
            });
            document.getElementById('hcFaqNoResults').style.display = visibleFaqCount === 0 ? 'block' : 'none';

            // Auto-Switch de Pestaña Inteligente (UX Experience)
            const modulesPanelActive = document.getElementById('hcPanelModules').classList.contains('active');
            
            if (modulesPanelActive && filteredMods.length === 0 && visibleFaqCount > 0) {
                // Se buscó algo que no es un módulo pero sí una pregunta frecuente -> Cambiar a pestaña FAQ
                switchHcTab(document.querySelector('.hc-tab[data-panel="hcPanelFaq"]'), false);
                this.focus();
            } 
            else if (!modulesPanelActive && visibleFaqCount === 0 && filteredMods.length > 0) {
                // Se buscó un módulo estando en FAQ -> Cambiar a pestaña Módulos
                switchHcTab(document.querySelector('.hc-tab[data-panel="hcPanelModules"]'), false);
                this.focus();
            }
        });

        // Cerrar con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('hcDrawer').classList.contains('open')) {
                closeHelpCenter();
            }
        });
    });
    </script>

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


            <div class="topbar-right">

                <!-- Botón de Recorrido Guiado (Tutorial) -->
                <div class="topbar-icon-btn" id="topbarTourBtn" title="Ver recorrido guiado" style="display: none; align-items: center; justify-content: center; gap: 8px; width: auto; padding: 0 14px;" onclick="if (window.triggerTutorialGuide) { window.triggerTutorialGuide(); }">
                    <i class="bi bi-compass"></i>
                    <span style="font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; white-space: nowrap;">Guía de inicio</span>
                </div>

                <div class="topbar-icon-btn" id="notifBtn" title="Centro de Notificaciones">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge" id="notifBadge"></span>
                    
                    <!-- Dropdown Notificaciones Interactivo -->
                    <div class="notif-panel" id="notifPanel">
                        <div class="notif-header">
                            <div class="notif-header-title">
                                <h3>Notificaciones</h3>
                                <span class="notif-unread-count" id="notifUnreadPill">0 sin leer</span>
                            </div>
                            <button type="button" class="notif-mark-all-btn" id="notifMarkAllBtn" title="Marcar todas como leídas">
                                <i class="bi bi-check2-all"></i> Marcar leídas
                            </button>
                        </div>

                        <div class="notif-tabs">
                            <button type="button" class="notif-tab active" data-notif-filter="unread" id="notifTabUnread">
                                Sin leer <span class="notif-tab-badge" id="notifTabUnreadCount">0</span>
                            </button>
                            <button type="button" class="notif-tab" data-notif-filter="all" id="notifTabAll">
                                Todas <span class="notif-tab-badge" id="notifTabAllCount">0</span>
                            </button>
                        </div>

                        <div class="notif-list" id="notifList">
                            <!-- Items insertados dinámicamente vía JS -->
                        </div>

                        <div class="notif-footer">
                            <a href="auditoria.php" class="notif-footer-link">
                                <i class="bi bi-journal-text"></i> Ver registro de auditoría
                            </a>
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
            const notifUnreadPill = document.getElementById('notifUnreadPill');
            const notifTabUnread = document.getElementById('notifTabUnread');
            const notifTabAll = document.getElementById('notifTabAll');
            const notifTabUnreadCount = document.getElementById('notifTabUnreadCount');
            const notifTabAllCount = document.getElementById('notifTabAllCount');
            const notifMarkAllBtn = document.getElementById('notifMarkAllBtn');

            if (!notifBtn) return;

            let allNotifs = [];
            let currentFilter = 'unread'; // 'unread' | 'all'

            // Abrir y cerrar el panel
            notifBtn.addEventListener('click', function(e) {
                if (e.target.closest('#notifPanel') && !e.target.closest('#notifMarkAllBtn') && !e.target.closest('.notif-tab')) {
                    return;
                }
                notifPanel.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!notifBtn.contains(e.target)) {
                    notifPanel.classList.remove('show');
                }
            });

            // Pestañas
            if (notifTabUnread && notifTabAll) {
                notifTabUnread.addEventListener('click', function(e) {
                    e.stopPropagation();
                    currentFilter = 'unread';
                    notifTabUnread.classList.add('active');
                    notifTabAll.classList.remove('active');
                    renderNotifications();
                });

                notifTabAll.addEventListener('click', function(e) {
                    e.stopPropagation();
                    currentFilter = 'all';
                    notifTabAll.classList.add('active');
                    notifTabUnread.classList.remove('active');
                    renderNotifications();
                });
            }

            // Marcar todas como leídas (Optimista e Instantáneo)
            if (notifMarkAllBtn) {
                notifMarkAllBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Cambiar automáticamente a la pestaña "Todas" para que no desaparezcan de la vista
                    currentFilter = 'all';
                    if(notifTabUnread) notifTabUnread.classList.remove('active');
                    if(notifTabAll) notifTabAll.classList.add('active');

                    // Actualización UI Optimista Instantánea (0ms)
                    allNotifs.forEach(n => { n.leido = true; });
                    renderNotifications();

                    // Petición asíncrona en segundo plano
                    fetch('../backend/api/index.php/notifications/read_all', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .catch(err => {
                        console.error('Error al marcar notificaciones leídas', err);
                        fetchNotificationsDirect();
                    });
                });
            }

            function isNotifRead(n) {
                return n.leido === true || n.leido === 1 || n.leido === "1" || n.leido === "t" || n.leido === "true";
            }

            function getIconForTipo(tipo) {
                const t = (tipo || '').toLowerCase();
                if (t.includes('prestamo')) return { icon: 'bi-arrow-left-right', class: 'notif-icon-prestamo' };
                if (t.includes('reserva')) return { icon: 'bi-calendar3', class: 'notif-icon-reserva' };
                if (t.includes('rfid')) return { icon: 'bi-broadcast', class: 'notif-icon-rfid' };
                if (t.includes('sistema')) return { icon: 'bi-shield-check', class: 'notif-icon-sistema' };
                return { icon: 'bi-bell-fill', class: 'notif-icon-default' };
            }

            function renderNotifications() {
                if (!notifList) return;

                const unreadList = allNotifs.filter(n => !isNotifRead(n));
                const unreadCount = unreadList.length;

                // Actualizar badges e indicadores
                if (unreadCount > 0) {
                    notifBadge.style.display = 'flex';
                    notifBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                    notifUnreadPill.textContent = `${unreadCount} sin leer`;
                    notifUnreadPill.style.display = 'inline-block';
                } else {
                    notifBadge.style.display = 'none';
                    notifUnreadPill.textContent = '0 sin leer';
                    notifUnreadPill.style.display = 'none';
                }

                notifTabUnreadCount.textContent = unreadCount;
                notifTabAllCount.textContent = allNotifs.length;

                const listToRender = currentFilter === 'unread' ? unreadList : allNotifs;

                if (listToRender.length === 0) {
                    if (currentFilter === 'unread') {
                        notifList.innerHTML = `
                            <div class="notif-empty">
                                <i class="bi bi-check2-circle" style="color:#10b981;"></i>
                                <p>¡Todo al día!</p>
                                <span>No tienes notificaciones sin leer.</span>
                            </div>`;
                    } else {
                        notifList.innerHTML = `
                            <div class="notif-empty">
                                <i class="bi bi-bell-slash"></i>
                                <p>Sin notificaciones</p>
                                <span>No tienes notificaciones registradas.</span>
                            </div>`;
                    }
                    return;
                }

                notifList.innerHTML = '';
                listToRender.forEach(n => {
                    const isRead = isNotifRead(n);
                    const iconInfo = getIconForTipo(n.tipo);
                    const a = document.createElement('a');
                    a.href = (n.enlace && n.enlace !== '#') ? n.enlace : 'javascript:void(0);';
                    a.className = 'notif-item ' + (isRead ? 'read' : 'unread');

                    let dateStr = '';
                    if (n.fecha_creacion) {
                        try {
                            dateStr = new Date(n.fecha_creacion).toLocaleString('es-MX', {
                                day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
                            });
                        } catch (e) {
                            dateStr = n.fecha_creacion;
                        }
                    }

                    a.innerHTML = `
                        <div class="notif-icon-wrap ${iconInfo.class}">
                            <i class="bi ${iconInfo.icon}"></i>
                        </div>
                        <div class="notif-content">
                            <div class="notif-content-head">
                                <div class="notif-title">${n.tipo || 'Notificación'}</div>
                                ${!isRead ? '<div class="notif-unread-dot" title="Sin leer"></div>' : ''}
                            </div>
                            <div class="notif-text">${n.mensaje}</div>
                            <span class="notif-time"><i class="bi bi-clock"></i> ${dateStr}</span>
                        </div>
                    `;

                    a.addEventListener('click', function(e) {
                        if (!isRead) {
                            // Actualización optimista instantánea
                            n.leido = true;
                            renderNotifications();

                            fetch('../backend/api/index.php/notifications/read', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ not_id: n.not_id })
                            }).then(() => {
                                if (n.enlace && n.enlace !== '#') {
                                    window.location.href = n.enlace;
                                }
                            }).catch(() => {
                                if (n.enlace && n.enlace !== '#') window.location.href = n.enlace;
                            });
                        } else if (n.enlace && n.enlace !== '#') {
                            window.location.href = n.enlace;
                        }
                    });

                    notifList.appendChild(a);
                });
            }

            let lastUnreadCount = 0;
            let initialLoadComplete = false;

            function fetchNotificationsDirect() {
                // Agregamos cacheBuster para forzar al navegador a siempre pedir los datos más frescos al servidor
                const cacheBuster = Date.now();
                fetch('../backend/api/index.php/notifications/all?_cb=' + cacheBuster)
                    .then(res => res.json())
                    .then(data => {
                        if (Array.isArray(data)) {
                            allNotifs = data;
                            
                            // Detectar cantidad actual de notificaciones sin leer
                            const currentUnreadCount = data.filter(n => parseInt(n.leido) === 0).length;
                            
                            // Si ya cargó la primera vez y el conteo subió, es una notificación NUEVA
                            if (initialLoadComplete && currentUnreadCount > lastUnreadCount) {
                                const newNotif = data.find(n => parseInt(n.leido) === 0);
                                if (newNotif) {
                                    // 1. Mostrar Toast estilo Facebook
                                    if (typeof showToast === 'function') {
                                        showToast(newNotif.mensaje, 'info');
                                    }
                                }
                            }
                            
                            lastUnreadCount = currentUnreadCount;
                            initialLoadComplete = true;

                            renderNotifications();
                        }
                    })
                    .catch(e => console.error('Error fetching notifications', e));
            }

            function checkExpiringAndFetch() {
                fetch('../backend/api/index.php/notifications/check_expiring', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                }).then(() => fetchNotificationsDirect())
                .catch(() => fetchNotificationsDirect());
            }

            // Cargar notificaciones inmediatamente (Directo y rápido)
            fetchNotificationsDirect();
            // Chequeo en segundo plano de vencimientos al iniciar
            setTimeout(checkExpiringAndFetch, 1000);
            // Refrescar directamente cada 30 segundos
            setInterval(fetchNotificationsDirect, 30000);

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

            // Inicialización de recorrido guiado para todos los módulos
            let tutorialRoot = document.getElementById("sigrat-global-tutorial-root");
            if (!tutorialRoot) {
                tutorialRoot = document.createElement("div");
                tutorialRoot.id = "sigrat-global-tutorial-root";
                document.body.appendChild(tutorialRoot);
            }

            const currentPage = window.location.pathname.split("/").pop() || "index.php";
            const allModulesSteps = {
                "index.php": [
                    {
                        title: "¡Bienvenido al Panel de Control!",
                        description: "Este es el centro operativo de <b>SIGRAT</b>. Aquí obtendrás un panorama completo del estado físico y digital del campus.",
                        position: "center"
                    },
                    {
                        target: ".stats-grid",
                        title: "Indicadores Clave (KPIs)",
                        description: "Esta sección consolida las métricas del día: reservas de hoy, aulas en uso activo, préstamos realizados y alertas de stock de inventario.",
                        position: "bottom"
                    },
                    {
                        target: ".charts-grid",
                        title: "Análisis de Uso e Inventario",
                        description: "Gráficas interactivas que detallan el uso histórico de los espacios y la distribución del inventario (disponibles, prestados, en mantenimiento). Puedes usar el selector de rango de tiempo para actualizar la información en tiempo real.",
                        position: "top"
                    },
                    {
                        target: ".reservations-card",
                        title: "Próximas Reservaciones",
                        description: "Consulta rápidamente el listado de las reservas programadas para hoy, con su respectivo horario y estado de confirmación.",
                        position: "left"
                    },
                    {
                        target: ".topbar-right",
                        title: "Barra de Navegación Superior",
                        description: "Ubicada en la esquina superior derecha. Aquí encontrarás accesos rápidos al panel de notificaciones (🔔), la brújula para iniciar esta guía (🧭) y la fecha de hoy.",
                        position: "left"
                    },
                    {
                        target: ".sidebar",
                        title: "Barra de Navegación Lateral",
                        description: "Este es el menú principal del sistema. Utilízalo para moverte y navegar entre los diferentes módulos disponibles (Dashboard, Calendario, Espacios, Inventario, Préstamos, RFID, Usuarios y Auditoría).",
                        position: "right"
                    },
                    {
                        target: "#helpCenterBtn",
                        title: "Centro de Ayuda y Reinicio",
                        description: "Para consultar documentación detallada o resolver dudas, haz clic en <b>Centro de Ayuda</b> en la barra lateral. Si deseas volver a ejecutar este tutorial en cualquier momento, presiona el ícono de brújula (🧭) en la barra superior.",
                        position: "right"
                    }
                ],
                "usuarios.php": [
                    {
                        title: "Control de Usuarios y Accesos",
                        description: "Módulo integral para administrar cuentas del personal, roles, privilegios del sistema e invitaciones a visitas externas.",
                        position: "center"
                    },
                    {
                        target: "header div[style*='gap: 12px']",
                        title: "Registro y Exportación de Datos",
                        description: "Agrupa las acciones para dar de alta nuevos usuarios y exportar todo el padrón de personal a reportes descargables en Excel o PDF.",
                        position: "left"
                    },
                    {
                        target: "#userFiltersContainer",
                        title: "Búsqueda y Filtros de Cuentas",
                        description: "Utiliza el buscador y los selectores de rol o estado a su lado para filtrar y ubicar rápidamente cualquier cuenta del sistema.",
                        position: "bottom"
                    },
                    {
                        target: "table, .table-responsive",
                        title: "Listado General de Cuentas",
                        description: "Aquí puedes monitorear la fecha de última conexión de cada usuario, editar sus perfiles o desactivar cuentas de forma directa.",
                        position: "top"
                    },
                    {
                        target: "#btn-invitaciones",
                        title: "Sección de Invitaciones",
                        description: "Esta es la pestaña de Invitaciones. Haz clic en este botón o pulsa <b>Siguiente</b> para abrir la sección de visitas y generar códigos de acceso.",
                        position: "bottom",
                        actionSelectorClick: "#btn-usuarios"
                    },
                    {
                        target: "#tab-invitaciones",
                        title: "Lista y Generación de Códigos",
                        description: "En esta sección puedes ver la lista de códigos de acceso generados para tus visitas y utilizar el formulario de la derecha para generar un nuevo código ingresando el nombre y correo del invitado.",
                        position: "top",
                        actionSelectorClick: "#btn-invitaciones"
                    }
                ],
                "espacios.php": [
                    {
                        title: "Catálogo de Espacios y Aulas",
                        description: "Te damos la bienvenida al módulo de Espacios. Desde aquí podrás administrar de forma centralizada todas las áreas del campus (aulas, laboratorios, auditorios y salas de juntas) y consultar la agenda diaria de ocupación.",
                        position: "center"
                    },
                    {
                        target: "header div[style*='gap: 12px']",
                        title: "Acciones de Espacios y Reservas",
                        description: "Agrupa los botones principales de la cabecera. Dependiendo del rol y de la pestaña activa, podrás dar de alta nuevos espacios o registrar solicitudes de reservación de manera inmediata.",
                        position: "left"
                    },
                    {
                        target: "#spaceFiltersContainer",
                        title: "Buscador y Filtros de Áreas",
                        description: "Te permite ubicar rápidamente cualquier instalación buscando por su nombre o edificio en la barra de texto, o filtrando las salas de forma directa por Edificio (CIC, PIDET) y por Tipo de espacio.",
                        position: "bottom"
                    },
                    {
                        target: "#stats-espacios",
                        title: "Indicadores Operativos de Espacios",
                        description: "Muestra en tiempo real el resumen numérico de las áreas: el total de espacios catalogados en el sistema, cuántos se encuentran disponibles (operativos), las reservaciones programadas para hoy y las aprobaciones pendientes de respuesta.",
                        position: "bottom"
                    },
                    {
                        target: "#spacesTable thead",
                        title: "Catálogo e Inventario de Áreas",
                        description: "Detalla la información técnica y de acceso de cada espacio: el nombre, el edificio, el tipo de salón, la capacidad oficial en personas, el estatus operativo (Activo/Inactivo) y las acciones rápidas de edición o baja.",
                        position: "top"
                    },
                    {
                        target: "#btn-calendario",
                        title: "Agenda Diaria de Ocupación",
                        description: "Haz clic en este botón o pulsa <b>Siguiente</b> para abrir el calendario de uso y consultar la programación diaria de reservaciones hora por hora.",
                        position: "bottom",
                        actionSelectorClick: "#btn-espacios"
                    },
                    {
                        target: "#tab-calendario",
                        title: "Reservaciones Programadas",
                        description: "En esta sección visualizas el cronograma de ocupación del día seleccionado. Podrás ver el horario exacto, el espacio, el nombre del docente o responsable y el estado de la reserva (Pendiente/Aprobada).",
                        position: "top",
                        actionSelectorClick: "#btn-calendario"
                    }
                ],
                "calendario.php": [
                    {
                        title: "Agenda y Horarios del Campus",
                        description: "Te damos la bienvenida al módulo de Calendario. Este es el centro de control de ocupación en tiempo real del campus, diseñado para gestionar reservaciones, revisar la disponibilidad de las salas y evitar conflictos de horarios.",
                        position: "center"
                    },
                    {
                        target: ".calendar-navigation-bar",
                        title: "Navegación e Intervalos de Vista",
                        description: "Te permite moverte de manera rápida a través de las fechas (ir a hoy, mes anterior o siguiente). También puedes cambiar la visualización completa entre las pestañas de vista Mensual y Semanal, o hacer clic en el selector del mes para saltar a una fecha específica.",
                        position: "bottom"
                    },
                    {
                        target: ".month-calendar-card",
                        title: "Cuadrícula del Calendario",
                        description: "Muestra las celdas del mes con los días y eventos agendados. Al pulsar sobre cualquier celda de fecha, la vista se actualizará para mostrar el desglose de horarios y reservas asociadas. También puedes hacer doble clic en un día libre para comenzar una reserva.",
                        position: "top"
                    },
                    {
                        target: ".calendar-sidebar-details",
                        title: "Resumen Diario y Disponibilidad",
                        description: "Ubicado en el panel lateral derecho. Te proporciona una vista ejecutiva rápida: el conteo de reservas agendadas, espacios libres y solicitudes pendientes, seguido por la agenda detallada de próximas reservaciones y la lista de espacios disponibles del día.",
                        position: "left"
                    },
                    {
                        target: "#btnNewReservation",
                        title: "Crear Nueva Reservación",
                        description: "Haz clic en este botón o presiona <b>Siguiente</b> para abrir el modal interactivo con el plano del campus y el formulario detallado para agendar tu espacio.",
                        position: "left",
                        actionSelectorClick: "#btnExitResModal"
                    },
                    {
                        target: "#reservationForm",
                        title: "Formulario de Reservación",
                        description: "Completa la información del evento: selecciona si es un día único o recurrente, ingresa la fecha, hora, duración, número de alumnos/asistentes, equipamiento requerido y el motivo de la actividad.",
                        position: "right",
                        actionSelectorClick: "#btnNewReservation"
                    },
                    {
                        target: ".map-pane",
                        title: "Plano Arquitectónico y Mapas",
                        description: "Esta sección te muestra el mapa interactivo del edificio. Puedes hacer zoom y desplazarte por el plano para visualizar las aulas libres (en verde), ocupadas (en rojo) o que requieren autorización (en amarillo). <b>Haz clic en cualquier espacio verde del plano para seleccionarlo directamente.</b>",
                        position: "left"
                    }
                ],
                "inventario.php": [
                    {
                        title: "Inventario de Activos y Equipos",
                        description: "Te damos la bienvenida al módulo de Inventario de Activos. Desde aquí podrás registrar, buscar y auditar todo el equipamiento tecnológico y mobiliario del campus.",
                        position: "center"
                    },
                    {
                        target: ".tabs-row",
                        title: "Operaciones y Registro de Activos",
                        description: "Agrupa las pestañas de navegación interna (Activos / Mobiliario) y los botones de acción rápida, como la exportación de reportes (PDF y Excel) y el botón para registrar un <b>Nuevo activo</b>.",
                        position: "bottom"
                    },
                    {
                        target: "#section-inventario .filters-bar",
                        title: "Buscador y Filtros de Inventario",
                        description: "Utiliza la barra de búsqueda para ubicar activos por nombre o número de serie, y aplica filtros rápidos por tipo de activo, estado operativo, edificio y aula específica.",
                        position: "bottom"
                    },
                    {
                        target: "#section-inventario .premium-table-card",
                        title: "Tabla General de Inventario",
                        description: "Muestra todos los activos registrados, detallando su etiqueta RFID, ubicación, tipo y estado actual. Aquí puedes editar o dar de baja cada activo.",
                        position: "top"
                    },
                    {
                        target: "#statsSidebar",
                        title: "Estado de Inventario y Categorías",
                        description: "Ubicado en la columna derecha. Muestra la gráfica analítica del estado de los activos (disponibles, prestados, en mantenimiento) y la distribución porcentual por categorías.",
                        position: "left",
                        actionSelectorClick: "#tab-inventario"
                    },
                    {
                        target: "#tab-mobiliario",
                        title: "Sección de Mobiliario",
                        description: "Haz clic en esta pestaña o presiona <b>Siguiente</b> para ingresar al inventario de mobiliario institucional (sillas, escritorios, mesas, etc.).",
                        position: "bottom",
                        actionSelectorClick: "#tab-inventario"
                    },
                    {
                        target: "#inventoryTable",
                        title: "Vista de Mobiliario Simplificada",
                        description: "En esta sección se listan los recursos de infraestructura física. Al registrar o editar aquí, la interfaz ocultará los campos técnicos innecesarios como tipo, marca, modelo y serie, simplificando la captura de datos.",
                        position: "top",
                        actionSelectorClick: "#tab-mobiliario"
                    }
                ],
                "prestamos.php": [
                    {
                        title: "Préstamos de Equipamiento",
                        description: "Te damos la bienvenida a la interfaz de Préstamos. Desde aquí podrás llevar un control estricto sobre las entregas temporales y devoluciones de equipamiento tecnológico y herramientas del campus, asegurando la trazabilidad de los activos asignados a docentes y alumnos.",
                        position: "center"
                    },
                    {
                        target: ".header-actions",
                        title: "Registro de Salidas e Historial",
                        description: "Este panel agrupa la barra de búsqueda y los botones de acción rápida. Puedes buscar préstamos por número de serie o solicitante. El botón de <b>Registrar Préstamo</b> te permite abrir el formulario de salida para vincular los materiales con su respectivo código RFID y asignar un responsable con fecha límite de retorno.",
                        position: "left"
                    },
                    {
                        target: ".filters-bar",
                        title: "Filtros de Estado de Préstamos",
                        description: "Te permite clasificar y depurar el listado general mediante pestañas de estado rápido: consulta todos los préstamos históricos, filtra los préstamos <b>Activos</b> (actualmente en manos del usuario), identifica préstamos <b>Vencidos</b> (fuera de la fecha límite de retorno) para aplicar avisos, o visualiza los ya <b>Devueltos</b>.",
                        position: "top"
                    },
                    {
                        target: ".table-container",
                        title: "Control de Devoluciones y Registro de Daños",
                        description: "Muestra el listado de préstamos activos y su fecha de vencimiento. En la columna de acciones, podrás procesar el retorno del activo al inventario de forma inmediata y, en caso de anomalías, reportar cualquier incidencia física, pérdida o daño para el seguimiento de mantenimiento.",
                        position: "top"
                    }
                ],
                "auditoria.php": [
                    {
                        title: "Historial de Auditoría",
                        description: "Acceso al registro inmutable de seguridad del sistema. Monitorea y audita cada cambio realizado.",
                        position: "center"
                    },
                    {
                        target: "#filterForm",
                        title: "Configuración del Reporte",
                        description: "Define los parámetros para el reporte: selecciona el tipo de auditoría (como actividad de usuarios, asistencia a espacios, uso de edificios, movimientos de inventario o préstamos) y acota el periodo estableciendo fechas de inicio y fin, o usando los botones de periodos rápidos (Últimos 7 días, Últimos 30 días, Mes actual) para actualizar la consulta de inmediato.",
                        position: "top"
                    },
                    {
                        target: "#auditTable thead",
                        title: "Tabla de Resultados",
                        description: "Muestra la información de auditoría generada en orden cronológico según tus filtros. Visualizarás la fecha y hora exacta del evento, el usuario responsable, el módulo del sistema afectado y la descripción detallada de la acción realizada.",
                        position: "top"
                    }
                ],
                "rfid.php": [
                    {
                        title: "Control de Acceso y Gestión RFID",
                        description: "Te damos la bienvenida al módulo de Gestión RFID. Aquí podrás administrar el enrolamiento de las tarjetas universitarias, comprobar la conexión de los lectores y monitorear los accesos en vivo en las diferentes aulas.",
                        position: "center"
                    },
                    {
                        target: "form",
                        title: "Enrolamiento de Tarjetas",
                        description: "Utiliza este formulario para dar de alta credenciales universitarias. Puedes seleccionar la modalidad de captura: enrolar por rangos automáticos (lotes), capturar de forma individual (usando el lector USB), o ingresar una lista manual de códigos.",
                        position: "top",
                        actionSelectorClick: "#tab-enrolamiento"
                    },
                    {
                        target: "#tab-simulador",
                        title: "Lector USB / Simulador",
                        description: "Haz clic en esta pestaña o presiona <b>Siguiente</b> para abrir el simulador y comprobar el funcionamiento de las antenas o lectores físicos conectados por USB.",
                        position: "bottom",
                        actionSelectorClick: "#tab-enrolamiento"
                    },
                    {
                        target: "#section-simulador",
                        title: "Consola del Simulador",
                        description: "En esta sección interactiva puedes conectar y verificar en tiempo real la comunicación con el microcontrolador USB (como un Arduino) y simular lecturas de tarjetas para comprobar el comportamiento del sistema.",
                        position: "top",
                        actionSelectorClick: "#tab-simulador"
                    },
                    {
                        target: "#tab-monitor",
                        title: "Acceso al Monitor en Vivo",
                        description: "Haz clic en esta pestaña o presiona <b>Siguiente</b> para abrir la consola de monitoreo en tiempo real del campus.",
                        position: "bottom",
                        actionSelectorClick: "#tab-simulador"
                    },
                    {
                        target: "#section-monitor",
                        title: "Monitor en Vivo y Estado de Antenas",
                        description: "Muestra en tiempo real la conectividad de las antenas instaladas en las aulas y un listado interactivo con los últimos escaneos de tarjetas RFID. Puedes filtrar los accesos por una antena específica.",
                        position: "top",
                        actionSelectorClick: "#tab-monitor"
                    }
                ]
            };

            const steps = allModulesSteps[currentPage];
            if (steps && window.TutorialGuide) {
                const moduleId = currentPage.replace(".php", "");
                const root = ReactDOM.createRoot(tutorialRoot);
                root.render(
                    React.createElement(window.TutorialGuide, {
                        steps: steps,
                        moduleId: moduleId
                    })
                );
            }
        });
        </script>
        <main class="content-padding">
