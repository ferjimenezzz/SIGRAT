<?php
/**
 * @file calendario.php
 * @summary Módulo de calendario unificado con vistas Mensual y Semanal, filtros rápidos y creación de reservas recurrentes/multi-día.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================
require_once 'seguridad.php';
require_once '../backend/config/Database.php';

$db = Config\Database::getConnection();
$us_id_sesion = $_SESSION['us_id'] ?? null;
$isAdmin = isset($_SESSION['rol']) && strpos(strtoupper(trim($_SESSION['rol'])), 'ADMIN') !== false;
$userRolCurrent = isset($_SESSION['rol']) ? strtoupper(trim($_SESSION['rol'])) : '';
$isMaestro = (
    strpos($userRolCurrent, 'MAESTRO') !== false || 
    strpos($userRolCurrent, 'DOCENTE') !== false || 
    strpos($userRolCurrent, 'PROFESOR') !== false
);

// 1. Obtener detalles del usuario autenticado para prellenar la reserva
$currentUser = [
    'nombre' => $_SESSION['nombre'] ?? '',
    'correo' => $_SESSION['correo'] ?? '',
    'telefono' => '',
    'carrera' => $_SESSION['division'] ?? ''
];

if (is_numeric($us_id_sesion)) {
    try {
        $stmtUser = $db->prepare("SELECT nombre, correo, telefono, carrera FROM usuario WHERE us_id = ?");
        $stmtUser->execute([$us_id_sesion]);
        $fetched = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($fetched) {
            $currentUser = array_merge($currentUser, $fetched);
        }
    } catch (\Exception $e) {
        error_log("Error al consultar usuario en calendario: " . $e->getMessage());
    }
} elseif (!empty($_SESSION['vis_id'])) {
    try {
        $stmtVis = $db->prepare("SELECT nombre, correo FROM visita WHERE vis_id = ?");
        $stmtVis->execute([$_SESSION['vis_id']]);
        $fetchedVis = $stmtVis->fetch(PDO::FETCH_ASSOC);
        if ($fetchedVis) {
            $currentUser['nombre'] = $fetchedVis['nombre'] ?? $currentUser['nombre'];
            $currentUser['correo'] = $fetchedVis['correo'] ?? $currentUser['correo'];
        }
    } catch (\Exception $e) {
        error_log("Error al consultar visita en calendario: " . $e->getMessage());
    }
}

// 2. Obtener lista de espacios activos
$spaces = $db->query("SELECT * FROM espacio WHERE estatus != 'Inactivo' ORDER BY edificio, nombre_numero")->fetchAll(PDO::FETCH_ASSOC);

// 3. Obtener lista de activos/equipos para el selector de equipamiento
$assets = $db->query("SELECT a.act_id, a.tipo, a.modelo, a.marca, e.edificio, a.esp_asignado 
                      FROM activo a 
                      LEFT JOIN espacio e ON a.esp_asignado = e.esp_id 
                      WHERE a.estatus = 'Disponible'")->fetchAll(PDO::FETCH_ASSOC);

// Incluir cabecera común
include 'header.php';
?>

<!-- Hojas de estilo y Fuentes adicionales -->

<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<style>
    /* VARIABLES DE DISEÑO PREMIUM */
    :root {
        --active-blue: #2563eb;
        --active-blue-light: rgba(37, 99, 235, 0.1);
        --bg-panel: #ffffff;
        --border-color: #e2e8f0;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --green-accent: #10b981;
        --orange-accent: #f59e0b;
        --purple-accent: #8b5cf6;
        --pink-accent: #ec4899;
        --shadow-premium: 0 10px 30px rgba(0, 0, 0, 0.04);
    }

    /* CONTENEDOR PRINCIPAL */
    .calendar-wrapper {
        display: flex;
        flex-direction: column;
        gap: 24px;
        position: relative;
    }

    /* BARRA SUPERIOR */
    .calendar-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .calendar-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .search-input-wrapper {
        position: relative;
        width: 280px;
    }

    .search-input-wrapper i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 14px;
    }

    .search-input-wrapper input {
        width: 100%;
        padding: 10px 14px 10px 38px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        outline: none;
        background: white;
        transition: all 0.2s;
    }

    .search-input-wrapper input:focus {
        border-color: var(--active-blue);
        box-shadow: 0 0 0 3px var(--active-blue-light);
    }

    .btn-action-outline {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border: 1px solid var(--border-color);
        background: white;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-action-outline:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .btn-action-primary {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--active-blue);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .btn-action-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
    }

    /* CONTROLES DE NAVEGACIÓN Y VISTAS */
    .calendar-navigation-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        padding: 16px 24px;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-premium);
        flex-wrap: wrap;
        gap: 16px;
    }

    .nav-buttons-group {
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        background: #f8fafc;
        border-radius: 10px;
        overflow: hidden;
    }

    .nav-btn {
        background: transparent;
        border: none;
        padding: 10px 18px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-btn:hover {
        background: #e2e8f0;
        color: var(--text-primary);
    }

    .nav-btn.border-x {
        border-left: 1px solid var(--border-color);
        border-right: 1px solid var(--border-color);
    }

    .calendar-current-label {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .view-switcher-group {
        display: flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }

    .btn-switch-view {
        border: none;
        background: transparent;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-switch-view.active {
        background: white;
        color: var(--active-blue);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* CONTENEDOR DE FILTROS ACTIVOS */
    .active-filters-tags {
        display: none; /* Se muestra dinámicamente */
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        padding: 4px 8px;
    }

    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #eff6ff;
        color: var(--active-blue);
        font-size: 12px;
        font-weight: 600;
        border-radius: 20px;
        border: 1px solid #bfdbfe;
    }

    .filter-tag i {
        cursor: pointer;
        font-size: 13px;
        transition: color 0.2s;
    }

    .filter-tag i:hover {
        color: #1d4ed8;
    }

    .btn-clear-all-filters {
        background: transparent;
        border: none;
        color: var(--active-blue);
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        margin-left: 12px;
    }

    .btn-clear-all-filters:hover {
        text-decoration: underline;
    }

    .showing-highlight-bar {
        display: none;
        padding: 10px 18px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: var(--active-blue);
        font-size: 13px;
        font-weight: 600;
        border-radius: 10px;
    }

    /* BARRA DE FILTROS RÁPIDOS INLINE */
    .quick-filters-bar {
        display: flex;
        gap: 20px;
        align-items: center;
        background: white;
        padding: 16px 24px;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-premium);
        flex-wrap: wrap;
    }

    .quick-filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .quick-filter-group span {
        font-size: 11px;
        font-weight: 800;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .quick-filter-select {
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        font-size: 12px;
        font-weight: 600;
        color: var(--text-primary);
        background: white;
        outline: none;
        cursor: pointer;
    }

    /* GRID DEL DISEÑO PRINCIPAL (MENSUAL) */
    .calendar-grid-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 24px;
        align-items: start;
    }

    .calendar-grid-layout.full-width-calendar {
        grid-template-columns: 1fr !important;
    }

    @media (max-width: 1024px) {
        .calendar-grid-layout {
            grid-template-columns: 1fr;
        }
    }

    /* VISTA MENSUAL: GRID DE DÍAS */
    .month-calendar-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-premium);
        overflow: hidden;
    }

    .month-days-header {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        background: #f8fafc;
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }

    .month-day-header-cell {
        padding: 14px;
        font-size: 12px;
        font-weight: 800;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .month-days-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        grid-auto-rows: minmax(110px, 1fr);
    }

    .month-day-cell {
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        padding: 8px;
        position: relative;
        background: white;
        display: flex;
        flex-direction: column;
        gap: 4px;
        cursor: pointer;
        transition: background 0.15s;
    }

    .month-day-cell:nth-child(7n) {
        border-right: none;
    }

    .month-day-cell:hover {
        background: #f8fafc;
    }

    .month-day-cell.other-month {
        background: #fcfdfe;
    }

    .month-day-cell.other-month .day-number {
        color: #cbd5e1;
    }

    .month-day-cell.today {
        background: rgba(37, 99, 235, 0.02);
    }

    .month-day-cell.today .day-number {
        background: var(--active-blue);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
    }

    .day-number {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-primary);
        align-self: flex-start;
        margin-bottom: 4px;
    }

    .month-events-container {
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow-y: auto;
        flex: 1;
        max-height: 90px;
    }

    /* EVENTOS CAPSULA */
    .event-capsule {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        border-left: 3px solid transparent;
        line-height: 1.3;
    }

    .event-capsule.status-approved {
        background: #eff6ff;
        color: var(--active-blue);
        border-left-color: var(--active-blue);
    }

    .event-capsule.status-pending {
        background: #fffbeb;
        color: #d97706;
        border-left-color: #d97706;
    }

    .event-capsule.status-rejected {
        background: #fdf2f8;
        color: #db2777;
        border-left-color: #db2777;
    }

    .event-capsule.event-color-blue { background: #eff6ff; color: var(--active-blue); border-left-color: var(--active-blue); }
    .event-capsule.event-color-purple { background: #f5f3ff; color: var(--purple-accent); border-left-color: var(--purple-accent); }
    .event-capsule.event-color-orange { background: #fffbeb; color: var(--orange-accent); border-left-color: var(--orange-accent); }
    .event-capsule.event-color-pink { background: #fdf2f8; color: var(--pink-accent); border-left-color: var(--pink-accent); }
    .event-capsule.event-color-green { background: #ecfdf5; color: var(--green-accent); border-left-color: var(--green-accent); }
    .event-capsule.event-color-teal { background: #f0fdfa; color: #0d9488; border-left-color: #0d9488; }
    .event-capsule.event-color-red { background: #fef2f2; color: #e11d48; border-left-color: #e11d48; }

    /* SISTEMA DE 40 COLORES FIJOS Y ÚNICOS POR AULA/ESPACIO */
    .event-color-sp-1 { --sp-bg: #eff6ff; --sp-border: #2563eb; --sp-text: #1d4ed8; --sp-dark: #1e3a8a; } /* Sala Magna 1 */
    .event-color-sp-2 { --sp-bg: #f5f3ff; --sp-border: #6366f1; --sp-text: #4338ca; --sp-dark: #312e81; } /* Sala Magna 2 */
    .event-color-sp-3 { --sp-bg: #ecfeff; --sp-border: #06b6d4; --sp-text: #0e7490; --sp-dark: #164e63; } /* Sala Magna 3 */
    .event-color-sp-4 { --sp-bg: #fffbeb; --sp-border: #f59e0b; --sp-text: #b45309; --sp-dark: #78350f; } /* Sala Magna 4 */
    .event-color-sp-5 { --sp-bg: #f8fafc; --sp-border: #8b5cf6; --sp-text: #6d28d9; --sp-dark: #4c1d95; } /* Posgrado 1 */
    .event-color-sp-6 { --sp-bg: #f7fee7; --sp-border: #84cc16; --sp-text: #4d7c0f; --sp-dark: #365314; } /* Posgrado 2 */
    .event-color-sp-7 { --sp-bg: #fff1f2; --sp-border: #f43f5e; --sp-text: #be123c; --sp-dark: #881337; } /* Salón 1 */
    .event-color-sp-8 { --sp-bg: #f8fafc; --sp-border: #64748b; --sp-text: #334155; --sp-dark: #0f172a; } /* Cubículos */
    .event-color-sp-9 { --sp-bg: #fdf4ff; --sp-border: #d946ef; --sp-text: #a21caf; --sp-dark: #701a75; } /* Oficina Lety */
    .event-color-sp-10 { --sp-bg: #fff7ed; --sp-border: #ea580c; --sp-text: #c2410c; --sp-dark: #7c2d12; } /* Aula Digital 1 */
    .event-color-sp-11 { --sp-bg: #ecfdf5; --sp-border: #10b981; --sp-text: #047857; --sp-dark: #064e3b; } /* Aula Digital 2 */
    .event-color-sp-12 { --sp-bg: #f0f9ff; --sp-border: #0ea5e9; --sp-text: #0369a1; --sp-dark: #0c4a6e; } /* Aula Digital 3 */
    .event-color-sp-13 { --sp-bg: #fdf2f8; --sp-border: #ec4899; --sp-text: #be185d; --sp-dark: #831843; } /* Aula Digital 4 */
    .event-color-sp-14 { --sp-bg: #faf5ff; --sp-border: #9333ea; --sp-text: #7e22ce; --sp-dark: #581c87; } /* Aula 5 Digital */
    .event-color-sp-15 { --sp-bg: #fef2f2; --sp-border: #dc2626; --sp-text: #b91c1c; --sp-dark: #7f1d1d; } /* Auditorio PIDET Baja */
    .event-color-sp-16 { --sp-bg: #fcfcf8; --sp-border: #65a30d; --sp-text: #4d7c0f; --sp-dark: #365314; } /* Maker Space */
    .event-color-sp-17 { --sp-bg: #fefce8; --sp-border: #eab308; --sp-text: #a16207; --sp-dark: #713f12; } /* Talentos */
    .event-color-sp-18 { --sp-bg: #f0fdfa; --sp-border: #14b8a6; --sp-text: #0f766e; --sp-dark: #134e4a; } /* Aula 03 */
    .event-color-sp-19 { --sp-bg: #eef2ff; --sp-border: #4f46e5; --sp-text: #3730a3; --sp-dark: #312e81; } /* Aula 04 */
    .event-color-sp-20 { --sp-bg: #fff0f5; --sp-border: #e11d48; --sp-text: #9f1239; --sp-dark: #881337; } /* Aula 05 */
    .event-color-sp-21 { --sp-bg: #fef5ec; --sp-border: #d97706; --sp-text: #92400e; --sp-dark: #78350f; } /* Aula 06 */
    .event-color-sp-22 { --sp-bg: #f0fdf4; --sp-border: #15803d; --sp-text: #166534; --sp-dark: #14532d; } /* Posgrado 1 Baja */
    .event-color-sp-23 { --sp-bg: #f1f5f9; --sp-border: #475569; --sp-text: #1e293b; --sp-dark: #0f172a; } /* Posgrado 2 Baja */
    .event-color-sp-24 { --sp-bg: #eff6ff; --sp-border: #1e3a8a; --sp-text: #1e3a8a; --sp-dark: #172554; } /* UNAM */
    .event-color-sp-25 { --sp-bg: #e6fffa; --sp-border: #00a88f; --sp-text: #007a67; --sp-dark: #004d40; } /* Lab. Siemens */
    .event-color-sp-26 { --sp-bg: #ebf8ff; --sp-border: #0077b6; --sp-text: #023e8a; --sp-dark: #03045e; } /* Aula Siemens */
    .event-color-sp-27 { --sp-bg: #e6f0fa; --sp-border: #0068b5; --sp-text: #004880; --sp-dark: #002d50; } /* Laboratorio Intel */
    .event-color-sp-28 { --sp-bg: #e8f4fd; --sp-border: #0f62fe; --sp-text: #0043ce; --sp-dark: #001d6c; } /* Sala IBM */
    .event-color-sp-29 { --sp-bg: #e8fcf8; --sp-border: #00bceb; --sp-text: #0080a0; --sp-dark: #005a70; } /* Laboratorio CISCO */
    .event-color-sp-30 { --sp-bg: #f3e8ff; --sp-border: #a855f7; --sp-text: #7e22ce; --sp-dark: #581c87; } /* Aula CIC Alta */
    .event-color-sp-31 { --sp-bg: #fff0f3; --sp-border: #800f2f; --sp-text: #590d22; --sp-dark: #3f0817; } /* Sala Videoconferencias */
    .event-color-sp-32 { --sp-bg: #fbf7f4; --sp-border: #8c6b5d; --sp-text: #5c4033; --sp-dark: #3b2820; } /* Sala de Juntas */
    .event-color-sp-33 { --sp-bg: #edf7ed; --sp-border: #2e7d32; --sp-text: #1b5e20; --sp-dark: #0d3810; } /* Embebidos */
    .event-color-sp-34 { --sp-bg: #ffebee; --sp-border: #cf0a2c; --sp-text: #9f001c; --sp-dark: #6e0012; } /* Aula Huawei */
    .event-color-sp-35 { --sp-bg: #e8f8f5; --sp-border: #16a085; --sp-text: #0e6655; --sp-dark: #0b4a3e; } /* GE Vernova */
    .event-color-sp-36 { --sp-bg: #e3f2fd; --sp-border: #1565c0; --sp-text: #0d47a1; --sp-dark: #0a3577; } /* Auditorio CIC Baja */
    .event-color-sp-37 { --sp-bg: #ede7f6; --sp-border: #673ab7; --sp-text: #4527a0; --sp-dark: #311b92; } /* CEPRODI */
    .event-color-sp-38 { --sp-bg: #fff3e0; --sp-border: #e65100; --sp-text: #bf360c; --sp-dark: #8c2608; } /* Sala Capacitación */
    .event-color-sp-39 { --sp-bg: #e0f2f1; --sp-border: #00897b; --sp-text: #00695c; --sp-dark: #004d40; } /* Centro Innovación Siemens */
    .event-color-sp-40 { --sp-bg: #fce4ec; --sp-border: #880e4f; --sp-text: #560027; --sp-dark: #3c001b; } /* Aula Proyectos */

    .event-capsule[class*="event-color-sp-"] {
        background: var(--sp-bg);
        color: var(--sp-text);
        border-left-color: var(--sp-border);
    }
    .week-event-card[class*="event-color-sp-"] {
        background: var(--sp-bg);
        color: var(--sp-dark, var(--sp-text));
        border-left-color: var(--sp-border);
    }

    /* DETALLES LATERALES DERECHOS */
    .calendar-sidebar-details {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .sidebar-section-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-premium);
        padding: 20px;
    }

    .sidebar-section-title {
        font-size: 14px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-section-title a {
        color: var(--active-blue);
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
    }

    .sidebar-section-title a:hover {
        text-decoration: underline;
    }

    /* Reservaciones list */
    .upcoming-res-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 240px;
        overflow-y: auto;
        padding-right: 6px;
    }

    .upcoming-res-list::-webkit-scrollbar {
        width: 4px;
    }

    .upcoming-res-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .upcoming-res-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .upcoming-res-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .upcoming-res-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }

    .upcoming-res-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .res-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--active-blue);
        font-size: 16px;
    }

    .res-item-icon.icon-blue { background: #eff6ff; color: var(--active-blue); }
    .res-item-icon.icon-green { background: #ecfdf5; color: var(--green-accent); }
    .res-item-icon.icon-orange { background: #fffbeb; color: var(--orange-accent); }

    .res-item-info {
        flex: 1;
        min-width: 0;
    }

    .res-item-name {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .res-item-time {
        font-size: 11px;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .status-badge {
        font-size: 10px;
        font-weight: 800;
        padding: 4px 8px;
        border-radius: 20px;
    }

    .status-badge.badge-confirmada { background: #dcfce7; color: #166534; }
    .status-badge.badge-pendiente { background: #fef3c7; color: #b45309; }
    .status-badge.badge-rechazada { background: #fee2e2; color: #ef4444; }
    .status-badge.badge-cancelada { background: #fee2e2; color: #ef4444; }

    /* Espacios disponibles */
    .available-spaces-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 160px;
        overflow-y: auto;
        padding-right: 6px;
    }

    .available-spaces-list::-webkit-scrollbar {
        width: 4px;
    }

    .available-spaces-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .available-spaces-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .available-spaces-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .space-status-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f8fafc;
        cursor: pointer;
    }

    .space-status-item:hover {
        opacity: 0.8;
    }

    .space-status-item:last-child {
        border-bottom: none;
    }

    .space-status-left {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .space-status-icon {
        color: var(--active-blue);
        font-size: 14px;
    }

    .space-status-name {
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .space-status-state {
        font-size: 11px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .space-status-state.state-libre { color: var(--green-accent); }
    .space-status-state.state-ocupado { color: var(--active-blue); }

    /* Resumen */
    .resumen-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .resumen-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px;
        text-align: center;
        border: 1px solid #f1f5f9;
    }

    .resumen-card-num {
        font-size: 20px;
        font-weight: 800;
        color: var(--text-primary);
    }

    .resumen-card-label {
        font-size: 9px;
        font-weight: 700;
        color: var(--text-secondary);
        margin-top: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    /* VISTA SEMANAL (ESPACIOS EN FILAS, DIAS EN COLUMNAS) */
    .week-calendar-container {
        display: none;
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-premium);
        overflow-x: auto;
    }

    .week-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
        table-layout: fixed;
    }

    .week-table th {
        background: #f8fafc;
        border-bottom: 1px solid var(--border-color);
        padding: 16px;
        font-size: 12px;
        font-weight: 800;
        color: var(--text-secondary);
        text-align: center;
        text-transform: uppercase;
    }

    .week-table th.col-space-header {
        text-align: left;
        width: 180px;
        border-right: 1px solid var(--border-color);
    }

    .week-table td {
        border-bottom: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        padding: 12px;
        vertical-align: top;
        position: relative;
    }

    .week-table td.col-space-info {
        border-right: 1px solid var(--border-color);
        background: #fcfdfe;
        font-weight: 700;
    }

    .week-space-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .week-space-subtitle {
        font-size: 11px;
        color: var(--text-secondary);
        margin-top: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .week-cell-slots-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 120px;
    }

    .week-event-card {
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        border-left: 4px solid var(--active-blue);
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .week-event-card.event-color-green {
        background: #ecfdf5;
        color: #065f46;
        border-left-color: var(--green-accent);
    }

    .week-event-card.event-color-blue {
        background: #eff6ff;
        color: #1e40af;
        border-left-color: var(--active-blue);
    }

    .week-event-card.event-color-orange {
        background: #fffbeb;
        color: #92400e;
        border-left-color: var(--orange-accent);
    }

    .week-event-card.event-color-purple {
        background: #f5f3ff;
        color: #5b21b6;
        border-left-color: var(--purple-accent);
    }

    .week-event-card.event-color-pink {
        background: #fdf2f8;
        color: #9d174d;
        border-left-color: var(--pink-accent);
    }

    .week-event-card.event-color-teal {
        background: #f0fdfa;
        color: #0d9488;
        border-left-color: #0d9488;
    }

    /* RESPONSIVIDAD ESPECÍFICA PARA CALENDARIO */
    @media (max-width: 768px) {
        .calendar-header-bar {
            flex-direction: column;
            align-items: stretch;
        }
        .calendar-actions, .search-input-wrapper {
            width: 100%;
        }
        .search-input-wrapper input {
            width: 100%;
        }
        .btn-action-primary, .btn-action-outline {
            flex: 1;
            justify-content: center;
        }
        
        .calendar-navigation-bar {
            justify-content: center;
        }
        .calendar-current-label {
            width: 100%;
            justify-content: center;
            order: -1; /* Poner el mes/año arriba en móvil */
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .quick-filters-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .quick-filter-group {
            width: 100%;
            justify-content: space-between;
        }
        .quick-filter-select {
            flex: 1;
            margin-left: 10px;
            min-width: 0;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
    }

    .week-event-card.event-color-red {
        background: #fef2f2;
        color: #e11d48;
        border-left-color: #e11d48;
    }

    .week-event-time {
        font-size: 9px;
        opacity: 0.7;
        margin-top: 4px;
    }

    /* ==================== FILTROS SLIDING SIDE PANEL ==================== */
    .filters-sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(4px);
        z-index: 900;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .filters-sidebar-panel {
        position: fixed;
        top: 0;
        right: -380px;
        width: 380px;
        height: 100%;
        background: white;
        box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
        z-index: 951;
        display: flex;
        flex-direction: column;
        transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }

    .filters-sidebar-panel.show {
        right: 0;
    }

    .filters-sidebar-header {
        padding: 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }

    .filters-sidebar-header h3 {
        font-size: 16px;
        font-weight: 800;
        color: var(--text-primary);
    }

    .filters-sidebar-body {
        padding: 24px;
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .filters-sidebar-footer {
        padding: 20px 24px;
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 12px;
        background: #f8fafc;
    }

    .filter-section-title {
        font-size: 11px;
        font-weight: 800;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }

    .filter-checkbox-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .filter-checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        cursor: pointer;
    }

    .filter-checkbox-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        cursor: pointer;
    }

    .filter-select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        outline: none;
        background: white;
    }

    .filter-radio-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .filter-radio-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        cursor: pointer;
    }

    .filter-radio-item input[type="radio"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .filter-dates-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .filter-date-input-group label {
        font-size: 10px;
        font-weight: 700;
        color: var(--text-secondary);
        display: block;
        margin-bottom: 4px;
    }

    .filter-date-input-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        outline: none;
    }

    .filter-hours-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .filter-hours-row label {
        font-size: 10px;
        font-weight: 700;
        color: var(--text-secondary);
        display: block;
        margin-bottom: 4px;
    }

    .filter-hours-row select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        outline: none;
    }

    /* Slider styling */
    .slider-container {
        padding: 8px 0;
    }

    .slider-range-values {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        font-weight: 700;
        color: var(--active-blue);
        margin-top: 8px;
    }

    .filter-slider {
        width: 100%;
        height: 6px;
        border-radius: 3px;
        background: #e2e8f0;
        outline: none;
        -webkit-appearance: none;
    }

    .filter-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--active-blue);
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Toggle Switch */
    .toggle-switch-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .toggle-switch-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--active-blue);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }

    /* ==================== MODAL DE NUEVA RESERVA ==================== */
    .res-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .res-modal-card {
        background: white;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        border: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
    }

    .res-modal-header {
        padding: 24px 32px 16px 32px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .res-modal-header h2 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text-primary);
    }

    .res-modal-header button {
        background: none;
        border: none;
        color: var(--text-secondary);
        font-size: 20px;
        cursor: pointer;
    }

    .res-modal-body {
        padding: 24px 32px;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .res-modal-footer {
        padding: 20px 32px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 16px;
        background: #f8fafc;
        border-radius: 0 0 20px 20px;
    }

    .res-modal-section-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
        margin-bottom: 16px;
    }

    .res-modal-section-title i {
        color: var(--active-blue);
    }

    .modal-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 12px;
    }

    .modal-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1.5fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .modal-form-group label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-secondary);
        margin-bottom: 6px;
        text-transform: none;
        letter-spacing: 0;
    }

    .modal-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        outline: none;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .modal-input:focus {
        border-color: var(--active-blue);
        background: white;
        box-shadow: 0 0 0 3px var(--active-blue-light);
    }

    .modal-input:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .modal-textarea {
        width: 100%;
        height: 80px;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        outline: none;
        background: #f8fafc;
        resize: none;
        transition: all 0.2s;
    }

    .modal-textarea:focus {
        border-color: var(--active-blue);
        background: white;
        box-shadow: 0 0 0 3px var(--active-blue-light);
    }

    .char-counter {
        font-size: 11px;
        color: var(--text-secondary);
        text-align: right;
        margin-top: 4px;
    }

    .btn-switch-res-mode {
        border: none;
        background: transparent;
        padding: 8px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-switch-res-mode.active {
        background: white;
        color: var(--active-blue);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* CUSTOM MONTH/YEAR PICKER DROPDOWN */
    .month-picker-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1000;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        padding: 16px;
        width: 280px;
        margin-top: 8px;
        animation: slideDownFade 0.2s ease-out;
    }
    
    @keyframes slideDownFade {
        from {
            opacity: 0;
            transform: translate(-50%, -10px);
        }
        to {
            opacity: 1;
            transform: translate(-50%, 0);
        }
    }

    .picker-month-btn {
        background: transparent;
        border: 1px solid transparent;
        padding: 8px 0;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }

    .picker-month-btn:hover {
        background: var(--active-blue-light);
        color: var(--active-blue);
    }

    .picker-month-btn.active {
        background: var(--active-blue);
        color: white;
        font-weight: 700;
    }

    .btn-picker-year-nav {
        background: #f1f5f9;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-primary);
        transition: all 0.2s;
    }

    .btn-picker-year-nav:hover {
        background: #e2e8f0;
        color: var(--active-blue);
    }

    /* TOOLTIP Y DETALLES */
    .res-tooltip {
        display: none;
        position: fixed;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.96);
        color: white;
        padding: 12px 16px;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
        font-size: 12px;
        max-width: 280px;
        pointer-events: none;
        line-height: 1.4;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }

    /* ==================== MODAL ANCHO: NUEVA RESERVA CON MAPA ==================== */
    .res-modal-wide-card {
        background: white;
        width: 95%;
        max-width: 1400px;
        height: 95%;
        max-height: 920px;
        border-radius: 20px;
        box-shadow: 0 30px 80px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: modalSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalSlideIn {
        from { opacity: 0; transform: scale(0.95) translateY(10px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .res-modal-wide-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 24px;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
        background: white;
    }

    .res-modal-two-col {
        display: grid;
        grid-template-columns: 380px 1fr;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    @media (max-width: 1024px) {
        .res-modal-two-col {
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr;
        }
        .res-modal-wide-card {
            width: 95%;
            height: 95%;
        }
    }

    /* COLUMNA IZQUIERDA: FORMULARIO */
    .form-pane {
        border-right: 1px solid #f1f5f9;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        background: #fafbfc;
    }

    .form-pane::-webkit-scrollbar { width: 4px; }
    .form-pane::-webkit-scrollbar-track { background: transparent; }
    .form-pane::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    .form-pane form {
        display: flex;
        flex-direction: column;
        gap: 0;
        height: 100%;
    }

    /* CONTENEDOR RESPONSIVO DE BOTONES DEL MODAL DE RESERVACIÓN */
    .modal-actions-row {
        display: flex;
        gap: 10px;
        margin-top: auto;
        flex-wrap: wrap;
        width: 100%;
    }

    .modal-btn-action {
        flex: 1;
        justify-content: center;
        min-width: 120px;
    }

    /* COLUMNA DERECHA: MAPA */
    .map-pane {
        display: flex;
        flex-direction: column;
        background: #f8fafc;
        overflow: hidden;
        position: relative;
    }

    .map-pane-controls {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        background: white;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
        flex-wrap: wrap;
    }

    /* Viewport del mapa con zoom/pan */
    .map-pane-viewport {
        flex: 1;
        overflow: hidden;
        position: relative;
        cursor: grab;
        background: #f1f5f9;
    }

    .map-pane-viewport:active { cursor: grabbing; }

    .map-pane-inner {
        position: relative;
        display: inline-block;
        transform-origin: 0 0;
        transition: transform 0.15s ease;
    }

    /* Leyenda */
    .map-pane-legend {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 8px 16px;
        background: white;
        border-top: 1px solid #f1f5f9;
        flex-shrink: 0;
        flex-wrap: wrap;
    }

    .map-legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
    }

    .map-legend-item span {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        display: inline-block;
    }

    /* Tooltip del mapa dentro del modal */
    .modal-map-tooltip {
        position: fixed;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.95);
        color: white;
        padding: 10px 14px;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        font-size: 12px;
        line-height: 1.5;
        pointer-events: none;
        max-width: 220px;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }

    .modal-map-tooltip .tt-name  { font-weight: 800; font-size: 13px; margin-bottom: 3px; }
    .modal-map-tooltip .tt-type  { opacity: 0.75; font-size: 11px; }
    .modal-map-tooltip .tt-cap   { margin-top: 4px; font-size: 11px; }
    .modal-map-tooltip .tt-status { margin-top: 2px; font-size: 11px; font-weight: 700; }
    .modal-map-tooltip .tt-status.libre { color: #4ade80; }
    .modal-map-tooltip .tt-status.ocupado { color: #f87171; }
    .modal-map-tooltip .tt-status.nores  { color: #94a3b8; }
</style>

<div class="calendar-wrapper">
    <!-- BARRA SUPERIOR CON ACCIONES -->
    <div class="calendar-header-bar">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; margin-bottom: 4px;">Calendario</h1>
            <p style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Consulta disponibilidad y agenda de espacios</p>
        </div>
        <div class="calendar-actions">
            <div class="search-input-wrapper">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar espacio, sala...">
            </div>
            <?php if (hasPermission('Calendario', 'ver_todas_reservas')): ?>
            <div class="btn-action-outline" style="cursor: default; display: flex; align-items: center; gap: 10px; user-select: none;">
                <span style="font-weight: 700; color: var(--text-primary);">Mis reservas</span>
                <label class="toggle-switch" style="margin-bottom: 0;">
                    <input type="checkbox" id="quickFilterSoloMisReservas">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php else: ?>
            <input type="checkbox" id="quickFilterSoloMisReservas" checked style="display: none;">
            <?php endif; ?>
            <button class="btn-action-outline" id="btnToggleFilters">
                <i class="bi bi-funnel"></i> Filtros avanz.
            </button>
            <button class="btn-action-primary" id="btnNewReservation">
                <i class="bi bi-plus-lg"></i> Nueva reservación
            </button>
        </div>
    </div>

    <!-- CONTROLES DE NAVEGACIÓN Y VISTAS -->
    <div class="calendar-navigation-bar">
        <div class="nav-buttons-group">
            <button class="nav-btn" id="btnPrev"><i class="bi bi-chevron-left"></i></button>
            <button class="nav-btn border-x" id="btnToday">Hoy</button>
            <button class="nav-btn" id="btnNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        
        <!-- MES Y AÑO SELECCIÓN DESPLEGABLE -->
        <div class="calendar-current-label">
            <select id="selectMonthNav" style="display: none;">
                <option value="0">Enero</option>
                <option value="1">Febrero</option>
                <option value="2">Marzo</option>
                <option value="3">Abril</option>
                <option value="4">Mayo</option>
                <option value="5">Junio</option>
                <option value="6">Julio</option>
                <option value="7">Agosto</option>
                <option value="8">Septiembre</option>
                <option value="9">Octubre</option>
                <option value="10">Noviembre</option>
                <option value="11">Diciembre</option>
            </select>
            <select id="selectYearNav" style="display: none;"></select>

            <!-- Custom trigger & dropdown menu -->
            <div style="position: relative; display: inline-block;">
                <div id="monthPickerTrigger" style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
                    <span id="currentMonthYearLabel" style="font-size: 18px; font-weight: 800; color: var(--text-primary);">Cargando...</span>
                    <i class="bi bi-chevron-down" id="monthPickerChevron" style="font-size: 14px; color: var(--text-secondary); transition: transform 0.2s;"></i>
                </div>
                
                <div class="month-picker-dropdown" id="monthPickerDropdown">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <button type="button" id="prevYearBtn" class="btn-picker-year-nav"><i class="bi bi-chevron-left"></i></button>
                        <span id="pickerYearLabel" style="font-weight: 800; font-size: 16px; color: var(--text-primary);">2026</span>
                        <button type="button" id="nextYearBtn" class="btn-picker-year-nav"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <div class="months-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                        <button type="button" class="picker-month-btn" data-month="0">Ene</button>
                        <button type="button" class="picker-month-btn" data-month="1">Feb</button>
                        <button type="button" class="picker-month-btn" data-month="2">Mar</button>
                        <button type="button" class="picker-month-btn" data-month="3">Abr</button>
                        <button type="button" class="picker-month-btn" data-month="4">May</button>
                        <button type="button" class="picker-month-btn" data-month="5">Jun</button>
                        <button type="button" class="picker-month-btn" data-month="6">Jul</button>
                        <button type="button" class="picker-month-btn" data-month="7">Ago</button>
                        <button type="button" class="picker-month-btn" data-month="8">Sep</button>
                        <button type="button" class="picker-month-btn" data-month="9">Oct</button>
                        <button type="button" class="picker-month-btn" data-month="10">Nov</button>
                        <button type="button" class="picker-month-btn" data-month="11">Dic</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="view-switcher-group">
            <button class="btn-switch-view active" data-view="month">Mes</button>
            <button class="btn-switch-view" data-view="week">Semana</button>
        </div>
    </div>

    <!-- BARRA DE FILTROS RÁPIDOS INLINE -->
    <div class="quick-filters-bar">
        <div class="quick-filter-group">
            <span>Edificio:</span>
            <select class="quick-filter-select" id="quickFilterEdificio">
                <option value="">Todos</option>
                <option value="CIC">CIC</option>
                <option value="PIDET">PIDET</option>
            </select>
        </div>
        
        <div class="quick-filter-group">
            <span>Tipo:</span>
            <select class="quick-filter-select" id="quickFilterTipo">
                <option value="">Todos</option>
                <option value="Aula">Aula</option>
                <option value="Laboratorio">Laboratorio</option>
                <option value="Auditorio">Auditorio</option>
                <option value="Sala de juntas">Sala de juntas</option>
            </select>
        </div>

        <div class="quick-filter-group">
            <span>Espacio:</span>
            <select class="quick-filter-select" id="quickFilterEspacio">
                <option value="">Todos</option>
                <!-- Rellenado dinámicamente -->
            </select>
        </div>

        <div class="quick-filter-group">
            <span>Estado:</span>
            <select class="quick-filter-select" id="quickFilterStatus">
                <option value="Todos">Todos</option>
                <option value="Aprobada">Aprobados</option>
                <option value="Pendiente">Pendientes</option>
            </select>
        </div>

    </div>

    <!-- BARRA DE FILTROS ACTIVOS -->
    <div class="active-filters-tags" id="activeFiltersContainer">
        <!-- Tags se inyectarán vía JS -->
    </div>
    
    <!-- BARRA DE HIGHLIGHT DE FILTROS -->
    <div class="showing-highlight-bar" id="highlightBar">
        Mostrando: CIC · Laboratorios · 2:00 PM -- 5:00 PM
    </div>

    <!-- CUADRO PRINCIPAL (MENSUAL) -->
    <div class="calendar-grid-layout <?php echo $isMaestro ? 'full-width-calendar' : ''; ?>" id="monthViewGrid">
        <!-- Calendario Mensual -->
        <div class="month-calendar-card">
            <div class="month-days-header">
                <div class="month-day-header-cell">Dom</div>
                <div class="month-day-header-cell">Lun</div>
                <div class="month-day-header-cell">Mar</div>
                <div class="month-day-header-cell">Mié</div>
                <div class="month-day-header-cell">Jue</div>
                <div class="month-day-header-cell">Vie</div>
                <div class="month-day-header-cell">Sáb</div>
            </div>
            <div class="month-days-grid" id="monthGridBody">
                <!-- Se poblará vía Javascript -->
            </div>
        </div>

        <?php if (!$isMaestro): ?>
        <!-- Sidebar Derecha -->
        <div class="calendar-sidebar-details">
            <!-- Resumen del Día -->
            <?php if ($isAdmin): ?>

<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
            <div class="sidebar-section-card">
                <div class="sidebar-section-title">
                    <span>Resumen del día</span>
                </div>
                <div class="resumen-cards-grid">
                    <div class="resumen-card">
                        <div class="resumen-card-num" id="statReservasHoy">0</div>
                        <div class="resumen-card-label">Reservas</div>
                    </div>
                    <div class="resumen-card">
                        <div class="resumen-card-num" id="statDisponibles">0</div>
                        <div class="resumen-card-label">Libres</div>
                    </div>
                    <div class="resumen-card">
                        <div class="resumen-card-num" id="statPendientes">0</div>
                        <div class="resumen-card-label">Por aprobar</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Próximas Reservaciones -->
            <div class="sidebar-section-card">
                <div class="sidebar-section-title">
                    <span>Próximas reservaciones</span>
                </div>
                <div class="upcoming-res-list" id="upcomingReservationsList">
                    <!-- Dinámico -->
                </div>
            </div>

            <!-- Espacios Disponibles -->
            <div class="sidebar-section-card">
                <div class="sidebar-section-title">
                    <span>Espacios disponibles</span>
                </div>
                <div class="available-spaces-list" id="availableSpacesList">
                    <!-- Dinámico -->
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TIMETABLE SEMANAL (SEMANA) -->
    <div class="week-calendar-container" id="weekViewGrid">
        <table class="week-table">
            <thead>
                <tr id="weekTableHeader">
                    <th class="col-space-header">Espacio</th>
                    <th>Lun</th>
                    <th>Mar</th>
                    <th>Mié</th>
                    <th>Jue</th>
                    <th>Vie</th>
                    <th>Sáb</th>
                    <th>Dom</th>
                </tr>
            </thead>
            <tbody id="weekTableBody">
                <!-- Inyectado vía JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== SIDEBAR DE FILTROS ==================== -->
<div class="filters-sidebar-overlay" id="filtersOverlay"></div>
<div class="filters-sidebar-panel" id="filtersSidebar">
    <div class="filters-sidebar-header">
        <h3>Filtros Avanzados</h3>
        <button id="btnExitFilters" style="background:none; border:none; font-size:22px; cursor:pointer; color:var(--text-secondary);"><i class="bi bi-x"></i></button>
    </div>
    
    <div class="filters-sidebar-body">
        <!-- Edificio -->
        <div>
            <div class="filter-section-title">1. Edificio</div>
            <div class="filter-checkbox-list">
                <label class="filter-checkbox-item">
                    <input type="checkbox" name="filter_edificio" value="CIC"> CIC
                </label>
                <label class="filter-checkbox-item">
                    <input type="checkbox" name="filter_edificio" value="PIDET"> PIDET
                </label>
            </div>
        </div>

        <!-- Tipo de Espacio -->
        <div>
            <div class="filter-section-title">2. Tipo de espacio</div>
            <div class="filter-checkbox-list">
                <label class="filter-checkbox-item">
                    <input type="checkbox" name="filter_tipo" value="Aula"> Aula
                </label>
                <label class="filter-checkbox-item">
                    <input type="checkbox" name="filter_tipo" value="Laboratorio"> Laboratorio
                </label>
                <label class="filter-checkbox-item">
                    <input type="checkbox" name="filter_tipo" value="Auditorio"> Auditorio
                </label>
                <label class="filter-checkbox-item">
                    <input type="checkbox" name="filter_tipo" value="Sala de juntas"> Sala de juntas
                </label>
            </div>
        </div>

        <!-- Espacio específico -->
        <div>
            <div class="filter-section-title">3. Espacio específico</div>
            <select class="filter-select" id="filterEspacioSelect">
                <option value="">Buscar espacio...</option>
                <?php foreach ($spaces as $sp): ?>
                    <option value="<?php echo $sp['esp_id']; ?>"><?php echo $sp['edificio'] . ' - ' . $sp['nombre_numero']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Estado de disponibilidad -->
        <div>
            <div class="filter-section-title">4. Estado de disponibilidad</div>
            <div class="filter-radio-list">
                <label class="filter-radio-item">
                    <input type="radio" name="filter_status" value="Todos" checked> Todos
                </label>
                <label class="filter-radio-item">
                    <input type="radio" name="filter_status" value="Aprobada"> Aprobados (Ocupados)
                </label>
                <label class="filter-radio-item">
                    <input type="radio" name="filter_status" value="Pendiente"> Pendientes
                </label>
            </div>
        </div>

        <!-- Fecha rango -->
        <div>
            <div class="filter-section-title">5. Fecha</div>
            <div class="filter-dates-row">
                <div class="filter-date-input-group">
                    <label>Desde</label>
                    <input type="date" id="filterFechaDesde">
                </div>
                <div class="filter-date-input-group">
                    <label>Hasta</label>
                    <input type="date" id="filterFechaHasta">
                </div>
            </div>
        </div>

        <!-- Rango de Hora -->
        <div>
            <div class="filter-section-title">6. Hora</div>
            <div class="filter-hours-row">
                <div>
                    <label>Hora Inicio</label>
                    <select id="filterHoraInicio">
                        <option value="07:00">07:00 AM</option>
                        <option value="08:00" selected>08:00 AM</option>
                        <option value="09:00">09:00 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="12:00">12:00 PM</option>
                        <option value="13:00">01:00 PM</option>
                        <option value="14:00">02:00 PM</option>
                        <option value="15:00">03:00 PM</option>
                        <option value="16:00">04:00 PM</option>
                        <option value="17:00">05:00 PM</option>
                        <option value="18:00">06:00 PM</option>
                    </select>
                </div>
                <div>
                    <label>Hora Fin</label>
                    <select id="filterHoraFin">
                        <option value="09:00">09:00 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="12:00">12:00 PM</option>
                        <option value="13:00">01:00 PM</option>
                        <option value="14:00">02:00 PM</option>
                        <option value="15:00">03:00 PM</option>
                        <option value="16:00">04:00 PM</option>
                        <option value="17:00">05:00 PM</option>
                        <option value="18:00">06:00 PM</option>
                        <option value="19:00">07:00 PM</option>
                        <option value="20:00" selected>08:00 PM</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Capacidad -->
        <div>
            <div class="filter-section-title">7. Capacidad mínima</div>
            <div class="slider-container">
                <input type="range" class="filter-slider" id="filterCapacidad" min="5" max="100" value="5">
                <div class="slider-range-values">
                    <span>5 pers.</span>
                    <span id="capacidadSliderLabel">Mínimo: 5 personas</span>
                    <span>100 pers.</span>
                </div>
            </div>
        </div>

        <!-- Solo mis reservaciones -->
        <div class="toggle-switch-container">
            <div>
                <div class="toggle-switch-label">Solo mis reservaciones</div>
                <div style="font-size:11px; color:var(--text-secondary); margin-top:2px;">Mostrar únicamente mis registros</div>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" id="filterSoloMisReservas">
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>
    
    <div class="filters-sidebar-footer">
        <button class="btn-action-primary" style="flex:1; justify-content:center;" id="btnApplyFilters">Aplicar filtros</button>
        <button class="btn-action-outline" style="flex:1; justify-content:center;" id="btnClearFilters">Limpiar filtros</button>
    </div>
</div>

<!-- ==================== MODAL DE RESERVACIÓN ANCHO (MAPA INTEGRADO) ==================== -->
<div class="res-modal-overlay" id="reservationModal">
    <div class="res-modal-wide-card">

        <!-- HEADER DEL MODAL -->
        <div class="res-modal-wide-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#2563eb,#7c3aed);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-calendar-plus"></i>
                </div>
                <div>
                    <h2 style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Nueva Reserva</h2>
                    <p style="margin:0;font-size:12px;color:#64748b;font-weight:500;">Selecciona el espacio en el plano o usa el formulario</p>
                </div>
            </div>
            <button id="btnExitResModal" type="button" style="background:#f1f5f9;border:none;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#64748b;font-size:18px;transition:all .2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- CUERPO: 2 COLUMNAS -->
        <div class="res-modal-two-col">

            <!-- ── COLUMNA IZQUIERDA: FORMULARIO ── -->
            <div class="form-pane">
                <form id="reservationForm">

                    <!-- Modo día único / múltiples días / por cuatrimestre -->
                    <div style="display:flex;gap:4px;background:#f1f5f9;padding:4px;border-radius:10px;border:1px solid var(--border-color);margin-bottom:16px;">
                        <button type="button" class="btn-switch-res-mode active" id="btnResModeSingle">Día único</button>
                        <button type="button" class="btn-switch-res-mode" id="btnResModeMultiple">Múltiples días</button>
                        <button type="button" class="btn-switch-res-mode" id="btnResModeCuatrimestre">Por cuatrimestre</button>
                    </div>

                    <!-- Edificio + Planta -->
                    <div class="res-modal-section-title"><i class="bi bi-building"></i> Ubicación del espacio</div>
                    <div class="modal-grid-2" style="margin-bottom:10px;">
                        <div class="modal-form-group">
                            <label>Edificio</label>
                            <select class="modal-input" id="resEdificio" required>
                                <option value="">Seleccione edificio...</option>
                                <option value="PIDET" selected>PIDET</option>
                                <option value="CIC">CIC</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label>Planta</label>
                            <select class="modal-input" id="resPlanta">
                                <option value="alta" selected>Planta Alta</option>
                                <option value="baja">Planta Baja</option>
                            </select>
                        </div>
                    </div>

                    <!-- Espacio -->
                    <div class="modal-form-group" style="margin-bottom:16px;">
                        <label><i class="bi bi-geo-alt" style="color:#3b82f6;margin-right:4px;"></i>Espacio seleccionado</label>
                        <select class="modal-input" name="esp_id" id="resEspacio" required style="border:2px solid #c7d2fe;background:#eff6ff;">
                            <option value="">← Haz clic en el plano o selecciona aquí</option>
                        </select>
                        <div id="resSelectedSpaceInfo" style="margin-top:6px;display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 12px;font-size:12px;color:#166534;font-weight:600;"></div>
                    </div>

                    <!-- Info Reserva -->
                    <div class="res-modal-section-title"><i class="bi bi-calendar-check"></i> Información de la reserva</div>

                    <!-- Fecha single day -->
                    <div id="resSingleDayFields" class="modal-form-group" style="margin-bottom:10px;">
                        <label>Fecha</label>
                        <input type="date" class="modal-input" name="fecha_uso" id="resFecha">
                    </div>

                    <!-- Fechas multi-day -->
                    <div id="resMultiDayFields" style="display:none;flex-direction:column;gap:10px;margin-bottom:10px;">
                        <div class="modal-grid-2">
                            <div class="modal-form-group">
                                <label>Fecha Inicio</label>
                                <input type="date" class="modal-input" id="resFechaInicio">
                            </div>
                            <div class="modal-form-group">
                                <label>Fecha Fin</label>
                                <input type="date" class="modal-input" id="resFechaFin">
                            </div>
                        </div>
                    </div>

                    <!-- Fechas cuatrimestre -->
                    <div id="resCuatrimestreFields" style="display:none;flex-direction:column;gap:10px;margin-bottom:10px;">
                        <div class="modal-form-group">
                            <label>Cuatrimestre</label>
                            <select class="modal-input" id="resCuatrimestreSelect">
                            </select>
                        </div>
                        <div class="modal-grid-2">
                            <div class="modal-form-group">
                                <label>Fecha Inicio</label>
                                <input type="date" class="modal-input" id="resCuatFechaInicio">
                            </div>
                            <div class="modal-form-group">
                                <label>Fecha Fin</label>
                                <input type="date" class="modal-input" id="resCuatFechaFin">
                            </div>
                        </div>
                    </div>

                    <div class="modal-grid-2" style="margin-bottom:10px;">
                        <div class="modal-form-group">
                            <label>Hora Inicio</label>
                            <select class="modal-input" name="hora_ent" id="resHoraEnt" required>
                                <option value="" disabled selected style="display:none;"></option>
                                <?php foreach(['08:00'=>'08:00 AM','09:00'=>'09:00 AM','10:00'=>'10:00 AM','11:00'=>'11:00 AM','12:00'=>'12:00 PM','13:00'=>'01:00 PM','14:00'=>'02:00 PM','15:00'=>'03:00 PM','16:00'=>'04:00 PM','17:00'=>'05:00 PM','18:00'=>'06:00 PM'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label>Hora Fin</label>
                            <select class="modal-input" name="hora_sal" id="resHoraSal" required>
                                <option value="" disabled selected style="display:none;"></option>
                                <?php foreach(['09:00'=>'09:00 AM','10:00'=>'10:00 AM','11:00'=>'11:00 AM','12:00'=>'12:00 PM','13:00'=>'01:00 PM','14:00'=>'02:00 PM','15:00'=>'03:00 PM','16:00'=>'04:00 PM','17:00'=>'05:00 PM','18:00'=>'06:00 PM','19:00'=>'07:00 PM','20:00'=>'08:00 PM'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small id="resWarningLong" style="color:#ef4444;font-size:10px;display:none;font-weight:700;margin-top:4px;">Reservas &gt; 2 horas requieren aprobación del admin.</small>
                        </div>
                    </div>

                    <div class="modal-grid-2" style="margin-bottom:10px;">
                        <div class="modal-form-group">
                            <label>Capacidad del espacio</label>
                            <input type="text" class="modal-input" id="resCapacidadLabel" disabled value="0 personas">
                        </div>
                        <div class="modal-form-group">
                            <label>N° alumnos/asistentes</label>
                            <input type="number" class="modal-input" name="num_alumnos" id="resNumAlumnos" value="10" min="1" required>
                            <small id="resCapacidadError" style="color:#ef4444;font-size:11px;font-weight:700;margin-top:4px;display:none;"></small>
                        </div>
                    </div>

                    <div class="modal-form-group" style="margin-bottom:10px;">
                        <label>Equipamiento disponible</label>
                        <div id="resEquipamientoContainer" style="display:flex;flex-direction:column;gap:6px;margin-top:4px;max-height:90px;overflow-y:auto;padding:8px;border:1px solid var(--border-color);border-radius:8px;background:#f8fafc;">
                            <div style="font-size:12px;color:var(--text-secondary);">Selecciona un espacio primero...</div>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="res-modal-section-title"><i class="bi bi-chat-left-text"></i> Motivo</div>
                    <div class="modal-form-group" style="margin-bottom:10px;">
                        <label>Motivo / Actividad</label>
                        <textarea class="modal-textarea" id="resMotivo" maxlength="250" placeholder="Describe el propósito de la reserva..." style="height:60px;"></textarea>
                        <div class="char-counter"><span id="charCount">0</span> / 250</div>
                    </div>

                    <!-- Solicitante -->
                    <div class="res-modal-section-title"><i class="bi bi-person"></i> Solicitante</div>
                    <div class="modal-grid-2" style="margin-bottom:10px;">
                        <div class="modal-form-group">
                            <label>Nombre</label>
                            <input type="text" class="modal-input" id="resNombreSolicitante" disabled value="<?php echo htmlspecialchars($currentUser['nombre']); ?>">
                        </div>
                        <div class="modal-form-group">
                            <label>Correo</label>
                            <input type="email" class="modal-input" id="resCorreoSolicitante" disabled value="<?php echo htmlspecialchars($currentUser['correo']); ?>">
                        </div>
                    </div>
                    <div class="modal-form-group" style="margin-bottom:16px;">
                        <label>Teléfono (Opcional)</label>
                        <input type="text" class="modal-input" id="resTelefonoSolicitante" placeholder="+52 ..." value="<?php echo htmlspecialchars($currentUser['telefono']); ?>">
                    </div>

                    <!-- Botones -->
                    <div class="modal-actions-row">
                        <button type="button" class="btn-action-outline modal-btn-action" id="btnCancelReserva">Cancelar</button>
                        <button type="submit" class="btn-action-primary modal-btn-action" id="btnConfirmReserva">
                            <i class="bi bi-calendar-check"></i> Confirmar reserva
                        </button>
                    </div>

                </form>
            </div>

            <!-- ── COLUMNA DERECHA: MAPA INTERACTIVO ── -->
            <div class="map-pane">
                <!-- Barra de controles del mapa -->
                <div class="map-pane-controls">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <i class="bi bi-map" style="color:#3b82f6;font-size:16px;"></i>
                        <span style="font-weight:800;font-size:13px;color:#1e293b;">Plano Arquitectónico</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex:1;max-width:220px;">
                        <div style="position:relative;flex:1;">
                            <i class="bi bi-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;"></i>
                            <input type="text" id="modalMapSearch" placeholder="Buscar espacio..." oninput="modalHighlightMapSpace(this.value)"
                                style="width:100%;padding:7px 8px 7px 28px;border-radius:8px;border:1px solid #e2e8f0;font-size:12px;outline:none;background:#f8fafc;">
                        </div>
                    </div>
                    <!-- Controles de zoom -->
                    <div style="display:flex;gap:4px;">
                        <button type="button" onclick="modalMapZoomIn()" title="Acercar" style="width:30px;height:30px;border:1px solid #e2e8f0;background:white;border-radius:8px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;color:#475569;transition:all .2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">+</button>
                        <button type="button" onclick="modalMapZoomOut()" title="Alejar" style="width:30px;height:30px;border:1px solid #e2e8f0;background:white;border-radius:8px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;color:#475569;transition:all .2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">−</button>
                        <button type="button" onclick="modalMapZoomReset()" title="Restablecer vista" style="width:30px;height:30px;border:1px solid #e2e8f0;background:white;border-radius:8px;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;color:#475569;transition:all .2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">⌂</button>
                    </div>
                </div>

                <!-- Contenedor del plano con zoom/pan -->
                <div class="map-pane-viewport" id="modalMapViewport">
                    <div class="map-pane-inner" id="modalMapInner">
                        <img id="modalMapImage" src="assets/mapas/MAPA_PIDET_Planta_Alta.png" alt="Plano arquitectónico" style="display:block;user-select:none;" onload="onModalMapImageLoad()" draggable="false">
                        <svg id="modalMapOverlay" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;" preserveAspectRatio="xMinYMin meet"></svg>
                    </div>
                </div>

                <!-- Leyenda -->
                <div class="map-pane-legend" style="flex-wrap: wrap; gap: 8px;">
                    <div class="map-legend-item"><span style="background:rgba(34,197,94,0.35);border:2px solid #22C55E;"></span>Libre</div>
                    <div class="map-legend-item"><span style="background:rgba(239,68,68,0.35);border:2px solid #EF4444;"></span>Ocupado</div>
                    <div class="map-legend-item"><span style="background:rgba(37,99,235,0.4);border:2px solid #2563EB;"></span>Seleccionado</div>
                    <div class="map-legend-item"><span style="background:rgba(55,65,81,0.35);border:2px solid #374151;"></span>Privado</div>
                    <div class="map-legend-item"><span style="background:rgba(245,158,11,0.35);border:2px solid #F59E0B;"></span>Requiere autorización</div>
                    <div class="map-legend-item"><span style="background:rgba(139,92,246,0.35);border:2px solid #8B5CF6;"></span>Reserva especial</div>
                </div>

                <!-- Tooltip del mapa -->
                <div id="modalMapTooltip" class="modal-map-tooltip" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MODAL DE DETALLES DE RESERVACIÓN ==================== -->
<div class="res-modal-overlay" id="resDetailsModal" style="display: none;">
    <div class="res-modal-card" style="max-width: 450px;">
        <div class="res-modal-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <h2>Detalles de la Reserva</h2>
            <button id="btnExitDetailsModal" type="button" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-secondary);"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="res-modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Info de estado y espacio -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 id="detEspacioNombre" style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0;">Laboratorio 101</h3>
                    <p id="detEdificioTipo" style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">CIC · Laboratorio</p>
                </div>
                <span id="detEstatusBadge" class="status-badge" style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">Aprobada</span>
            </div>
            
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0;">
            
            <!-- Información detallada -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: var(--active-blue);"><i class="bi bi-calendar-event"></i></div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600;">FECHA</div>
                        <div id="detFecha" style="font-size: 13px; font-weight: 700; color: var(--text-primary);">Martes, 16 de Junio, 2026</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: var(--active-blue);"><i class="bi bi-clock"></i></div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600;">HORARIO</div>
                        <div id="detHorario" style="font-size: 13px; font-weight: 700; color: var(--text-primary);">08:00 AM - 10:00 AM</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; color: var(--green-accent);"><i class="bi bi-person"></i></div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600;">SOLICITANTE</div>
                        <div id="detSolicitante" style="font-size: 13px; font-weight: 700; color: var(--text-primary);">Juan Pérez</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; color: var(--green-accent);"><i class="bi bi-envelope"></i></div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600;">CORREO</div>
                        <div id="detCorreo" style="font-size: 13px; font-weight: 700; color: var(--text-primary); word-break: break-all;">juan.perez@correo.com</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #fef3c7; display: flex; align-items: center; justify-content: center; color: var(--orange-accent);"><i class="bi bi-people"></i></div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600;">ASISTENTES ESTIMADOS</div>
                        <div id="detAsistentes" style="font-size: 13px; font-weight: 700; color: var(--text-primary);">15 alumnos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="res-modal-footer" style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;">
            <button type="button" class="btn-action-outline" style="flex:1; justify-content:center;" id="btnCloseDetailsModal">Cerrar</button>
        </div>
    </div>
</div>

<!-- Contenedor del Tooltip -->
<div class="res-tooltip" id="calendarTooltip"></div>

<!-- JAVASCRIPT CONTROLLER LOGIC -->
<script>
    // DATOS ESTÁTICOS COMPARTIDOS DESDE PHP
    const allSpaces = <?php echo json_encode($spaces); ?>;
    const allAssets = <?php echo json_encode($assets); ?>;
    const sessionUserId = <?php echo json_encode($us_id_sesion); ?>;
    const isUserAdmin = <?php echo json_encode($isAdmin); ?>;
    let isProgrammaticMapChange = false;
    window.reservationsForSelectedDate = [];
    window.lastLoadedDate = "";
    window.isSpaceFullyOccupied = function(espId) {
        if (!espId) return false;
        const resList = window.reservationsForSelectedDate.filter(r => {
            if (parseInt(r.esp_id) !== parseInt(espId)) return false;
            const estatus = (r.estatus || r.status || '').toLowerCase();
            if (estatus === 'rechazada' || estatus === 'rejected' || estatus === 'cancelada' || estatus === 'cancelled') {
                return false;
            }
            return true;
        });
        if (resList.length === 0) return false;
        const slots = Array(12).fill(false); // 8-9, 9-10, ..., 19-20
        resList.forEach(r => {
            const start = parseInt(r.hora_ent);
            const end = parseInt(r.hora_sal);
            for (let h = start; h < end; h++) {
                if (h >= 8 && h < 20) {
                    slots[h - 8] = true;
                }
            }
        });
        return slots.every(s => s === true);
    };

    // SISTEMA DE COLORES POR ESPACIO
    // SISTEMA DE COLORES ÚNICOS FIJOS POR AULA/ESPACIO
    function getColorForSpace(esp_id) {
        const id = parseInt(esp_id, 10);
        if (!isNaN(id) && id >= 1 && id <= 40) {
            return `event-color-sp-${id}`;
        }
        // Fallback si en el futuro se agregan más salones (> 40)
        const num = !isNaN(id) && id > 0 ? ((Math.abs(id) - 1) % 40) + 1 : 1;
        return `event-color-sp-${num}`;
    }

    // LÓGICA CENTRALIZADA DE NEGOCIO PARA ESPACIOS
    function getSpaceRules(spaceData) {
        // Valores por defecto
        const rules = {
            tipo_acceso: 'General',
            es_reservable: true,
            mensaje_tooltip: 'Reserva directa disponible. Este espacio permite reserva inmediata.',
            mensaje_toast_seleccion: 'Puedes continuar con tu reservación.',
            mensaje_toast_exito: '¡Reserva creada correctamente! Tu espacio ha sido reservado con éxito. Disfruta tu reserva.',
            icono: 'bi-check-circle-fill',
            color_tema: '#10b981' // Verde
        };

        if (!spaceData) return rules;

        const rawRes = spaceData.es_reservable;
        rules.es_reservable = !(rawRes === false || rawRes === 'f' || rawRes === 0 || rawRes === 'false' || rawRes === null || rawRes === '');
        
        const acceso = (spaceData.acceso || '').toLowerCase();
        const tipo = (spaceData.tipo || '').toLowerCase();
        const responsable = spaceData.responsable || '';
        const nombre = spaceData.nombre_numero || 'Espacio';
        const edificio = spaceData.edificio || '';

        // 1. No reservable / Privado
        if (!rules.es_reservable) {
            rules.tipo_acceso = 'Privado / No Reservable';
            rules.mensaje_tooltip = 'Este espacio es de acceso privado y no admite reservaciones.';
            rules.mensaje_toast_seleccion = `Este espacio es de acceso privado y no admite reservaciones.`;
            rules.es_reservable = false;
            rules.icono = 'bi-lock-fill';
            rules.color_tema = '#64748b'; // Gris
            return rules;
        }

        // 2. Visita (Ej: CEPRODI)
        if (acceso === 'visita') {
            rules.tipo_acceso = 'Solo Visita';
            rules.mensaje_tooltip = `Has seleccionado ${nombre}. Este espacio únicamente puede reservarse mediante visita presencial.`;
            rules.mensaje_toast_seleccion = `Has seleccionado ${nombre}. Este espacio únicamente puede reservarse mediante visita programada.`;
            rules.mensaje_toast_exito = 'Tu solicitud quedó registrada. Recuerda que este espacio únicamente puede utilizarse mediante visita autorizada.';
            rules.icono = 'bi-eye-fill';
            rules.color_tema = '#8b5cf6'; // Morado
            return rules;
        }

        // 3. Restringido
        if (acceso === 'restringido') {
            rules.tipo_acceso = 'Requiere Autorización';
            if (responsable) {
                rules.mensaje_tooltip = `Has seleccionado ${nombre}. Esta solicitud será enviada al administrador ${responsable} para su autorización.`;
                rules.mensaje_toast_seleccion = `Has seleccionado ${nombre}. Esta solicitud será enviada al administrador para su autorización.`;
            } else {
                rules.mensaje_tooltip = `Has seleccionado ${nombre}. Esta solicitud será enviada al administrador para su autorización.`;
                rules.mensaje_toast_seleccion = `Has seleccionado ${nombre}. Esta reservación requiere autorización de administrador.`;
            }
            rules.mensaje_toast_exito = 'La reservación ha sido enviada para validación. Recibirás una notificación cuando sea aprobada.';
            rules.icono = 'bi-shield-lock-fill';
            rules.color_tema = '#f59e0b'; // Naranja
            return rules;
        }

        // 4. Administrador (Solo Administradores)
        if (acceso === 'administrador') {
            rules.tipo_acceso = 'Requiere Administración';
            rules.mensaje_tooltip = `Has seleccionado ${nombre}. Esta reservación deberá ser gestionada por la administración.`;
            rules.mensaje_toast_seleccion = `Has seleccionado ${nombre}. Esta reservación deberá ser autorizada por la administración.`;
            rules.mensaje_toast_exito = 'Tu solicitud fue registrada correctamente. El equipo administrativo recibirá una notificación.';
            rules.icono = 'bi-person-workspace';
            rules.color_tema = '#f59e0b'; // Naranja
            return rules;
        }

        // 5. General
        rules.mensaje_tooltip = `Has seleccionado ${nombre}. Este espacio permite reserva inmediata.`;
        rules.mensaje_toast_seleccion = `Has seleccionado ${nombre}. Este espacio permite reserva inmediata. Puedes continuar con tu reservación.`;
        // Variar el mensaje de éxito para espacios generales
        const mensajesExito = [
            '¡Reserva creada correctamente! Tu espacio ha sido reservado con éxito.',
            '¡Tu solicitud fue registrada correctamente! Disfruta tu reserva.',
            '¡Listo! Tu espacio quedó reservado. Te esperamos.',
            '¡Perfecto! La reserva ha sido confirmada correctamente.'
        ];
        rules.mensaje_toast_exito = mensajesExito[Math.floor(Math.random() * mensajesExito.length)];
        return rules;
    }

    // ESTADO DE LA APLICACIÓN DE CALENDARIO
    const state = {
        currentView: 'month', // 'month', 'week'
        currentDate: new Date(), // Fecha de referencia de la vista
        events: [], // Eventos cargados desde la API
        filters: {
            edificio: [],
            tipo: [],
            esp_id: '',
            status: 'Todos',
            fecha_inicio: '',
            fecha_fin: '',
            hora_inicio: '08:00',
            hora_fin: '20:00',
            capacidad: 5,
            us_id: ''
        },
        searchQuery: '',
        resMode: 'single' // 'single', 'multiple'
    };

    document.addEventListener('DOMContentLoaded', () => {
        initYearNav();
        initUIElements();
        syncFiltersAndFetch();

        // Escucha de caracteres en el textarea del modal
        const resMotivo = document.getElementById('resMotivo');
        const charCount = document.getElementById('charCount');
        if (resMotivo && charCount) {
            resMotivo.addEventListener('input', () => {
                charCount.textContent = resMotivo.value.length;
            });
        }
    });

    // ----------------------------------------------------
    // INICIALIZACIÓN DEL SELECTOR DE AÑO
    // ----------------------------------------------------
    function initYearNav() {
        const yearSelect = document.getElementById('selectYearNav');
        const currentYear = new Date().getFullYear();
        
        let opts = '';
        for (let y = currentYear - 2; y <= currentYear + 2; y++) {
            opts += `<option value="${y}">${y}</option>`;
        }
        yearSelect.innerHTML = opts;
        yearSelect.value = currentYear;
    }

    // ----------------------------------------------------
    // INICIALIZACIÓN DE COMPONENTES DE INTERFAZ
    // ----------------------------------------------------
    function initUIElements() {
        // Dropdowns de mes y año
        const monthSelect = document.getElementById('selectMonthNav');
        const yearSelect = document.getElementById('selectYearNav');
        
        monthSelect.value = state.currentDate.getMonth();
        yearSelect.value = state.currentDate.getFullYear();

        const handleSelectNavChange = () => {
            state.currentDate = new Date(parseInt(yearSelect.value), parseInt(monthSelect.value), 1);
            renderActiveCalendar();
        };

        monthSelect.addEventListener('change', handleSelectNavChange);
        yearSelect.addEventListener('change', handleSelectNavChange);

        // Switchers de vista (Mes/Semana)
        document.querySelectorAll('.btn-switch-view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.btn-switch-view').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.currentView = btn.dataset.view;
                
                // Ajustar UI según la vista
                document.getElementById('monthViewGrid').style.display = state.currentView === 'month' ? 'grid' : 'none';
                document.getElementById('weekViewGrid').style.display = state.currentView === 'week' ? 'block' : 'none';

                renderActiveCalendar();

                renderActiveCalendar();
            });
        });

        // Navegación de flechas
        document.getElementById('btnPrev').addEventListener('click', () => adjustDate(-1));
        document.getElementById('btnNext').addEventListener('click', () => adjustDate(1));
        document.getElementById('btnToday').addEventListener('click', () => {
            state.currentDate = new Date();
            monthSelect.value = state.currentDate.getMonth();
            yearSelect.value = state.currentDate.getFullYear();
            renderActiveCalendar();
        });

        // Filtro sidebar toggle
        const filtersOverlay = document.getElementById('filtersOverlay');
        const filtersSidebar = document.getElementById('filtersSidebar');
        const btnToggleFilters = document.getElementById('btnToggleFilters');
        const btnExitFilters = document.getElementById('btnExitFilters');

        function openFilters() {
            filtersOverlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                filtersOverlay.style.opacity = '1';
                filtersSidebar.classList.add('show');
            }, 10);
        }

        function closeFilters() {
            filtersSidebar.classList.remove('show');
            filtersOverlay.style.opacity = '0';
            setTimeout(() => {
                filtersOverlay.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }

        if(btnToggleFilters) btnToggleFilters.addEventListener('click', openFilters);
        if(btnExitFilters) btnExitFilters.addEventListener('click', closeFilters);
        if(filtersOverlay) filtersOverlay.addEventListener('click', closeFilters);

        // Capacidad slider listener en sidebar
        const filterCapacidad = document.getElementById('filterCapacidad');
        const capacidadSliderLabel = document.getElementById('capacidadSliderLabel');
        if (filterCapacidad && capacidadSliderLabel) {
            filterCapacidad.addEventListener('input', (e) => {
                capacidadSliderLabel.textContent = `Mínimo: ${e.target.value} personas`;
            });
        }

        // Aplicar y Limpiar filtros del Panel Lateral
        document.getElementById('btnApplyFilters').addEventListener('click', () => {
            applySidebarFilters();
            closeFilters();
        });

        document.getElementById('btnClearFilters').addEventListener('click', () => {
            clearSidebarFilters();
            closeFilters();
        });

        // ----------------------------------------------------
        // FILTROS RÁPIDOS INLINE (EVENT LISTENERS)
        // ----------------------------------------------------
        const quickFilterEdificio = document.getElementById('quickFilterEdificio');
        const quickFilterTipo = document.getElementById('quickFilterTipo');
        const quickFilterEspacio = document.getElementById('quickFilterEspacio');
        const quickFilterStatus = document.getElementById('quickFilterStatus');
        const quickFilterSoloMisReservas = document.getElementById('quickFilterSoloMisReservas');

        // Poblar selector de espacios rápido
        function populateQuickSpaces() {
            const edifVal = quickFilterEdificio.value;
            const tipoVal = quickFilterTipo.value;
            
            let filtered = allSpaces;
            if(edifVal) filtered = filtered.filter(s => s.edificio === edifVal);
            if(tipoVal) filtered = filtered.filter(s => s.tipo === tipoVal);
            
            let opts = '<option value="">Todos</option>';
            filtered.forEach(s => {
                opts += `<option value="${s.esp_id}">${s.edificio} - ${s.nombre_numero}</option>`;
            });
            quickFilterEspacio.innerHTML = opts;
            quickFilterEspacio.value = state.filters.esp_id || "";
        }
        
        populateQuickSpaces();

        // Al cambiar cualquier filtro rápido, actualizamos el estado e interactuamos al instante
        quickFilterEdificio.addEventListener('change', () => {
            state.filters.edificio = quickFilterEdificio.value ? [quickFilterEdificio.value] : [];
            state.filters.esp_id = ""; // Reset espacio al cambiar edificio
            populateQuickSpaces();
            
            // Sincronizar sidebar
            document.querySelectorAll('input[name="filter_edificio"]').forEach(c => {
                c.checked = state.filters.edificio.includes(c.value);
            });
            document.getElementById('filterEspacioSelect').value = "";
            
            renderActiveCalendar();
        });

        quickFilterTipo.addEventListener('change', () => {
            state.filters.tipo = quickFilterTipo.value ? [quickFilterTipo.value] : [];
            state.filters.esp_id = ""; // Reset espacio al cambiar tipo
            populateQuickSpaces();

            // Sincronizar sidebar
            document.querySelectorAll('input[name="filter_tipo"]').forEach(c => {
                c.checked = state.filters.tipo.includes(c.value);
            });
            document.getElementById('filterEspacioSelect').value = "";

            renderActiveCalendar();
        });

        quickFilterEspacio.addEventListener('change', () => {
            state.filters.esp_id = quickFilterEspacio.value;
            
            // Sincronizar sidebar
            document.getElementById('filterEspacioSelect').value = quickFilterEspacio.value;
            
            renderActiveCalendar();
        });

        quickFilterStatus.addEventListener('change', () => {
            state.filters.status = quickFilterStatus.value;
            
            // Sincronizar sidebar
            document.querySelector(`input[name="filter_status"][value="${quickFilterStatus.value}"]`).checked = true;
            
            renderActiveCalendar();
        });

        quickFilterSoloMisReservas.addEventListener('change', () => {
            state.filters.us_id = quickFilterSoloMisReservas.checked ? sessionUserId : '';
            
            // Sincronizar sidebar
            document.getElementById('filterSoloMisReservas').checked = quickFilterSoloMisReservas.checked;
            
            renderActiveCalendar();
        });

        // Barra de búsqueda en tiempo real
        const searchInput = document.getElementById('searchInput');
        if(searchInput) {
            searchInput.addEventListener('input', (e) => {
                state.searchQuery = e.target.value.toLowerCase().trim();
                renderActiveCalendar();
            });
        }

        // MODAL DE RESERVACIÓN
        const reservationModal = document.getElementById('reservationModal');
        const btnNewReservation = document.getElementById('btnNewReservation');
        const btnExitResModal = document.getElementById('btnExitResModal');
        const btnCancelReserva = document.getElementById('btnCancelReserva');
        const resEdificio = document.getElementById('resEdificio');
        const resEspacio = document.getElementById('resEspacio');
        
        // SWITCHER DE MODO EN MODAL (DÍA ÚNICO VS MULTI-DÍA VS CUATRIMESTRE)
        const btnResModeSingle = document.getElementById('btnResModeSingle');
        const btnResModeMultiple = document.getElementById('btnResModeMultiple');
        const btnResModeCuatrimestre = document.getElementById('btnResModeCuatrimestre');
        const resSingleDayFields = document.getElementById('resSingleDayFields');
        const resMultiDayFields = document.getElementById('resMultiDayFields');
        const resCuatrimestreFields = document.getElementById('resCuatrimestreFields');
        
        function updateCuatrimestreDatesFromSelect() {
            const sel = document.getElementById('resCuatrimestreSelect');
            const startInput = document.getElementById('resCuatFechaInicio');
            const endInput = document.getElementById('resCuatFechaFin');
            if (!sel || !startInput || !endInput) return;
            
            const val = sel.value;
            if (val && val.includes('|')) {
                const [s, e] = val.split('|');
                startInput.value = s;
                endInput.value = e;
            }
        }

        const resCuatrimestreSelectEl = document.getElementById('resCuatrimestreSelect');
        if (resCuatrimestreSelectEl) {
            resCuatrimestreSelectEl.addEventListener('change', updateCuatrimestreDatesFromSelect);
        }

        function populateCuatrimestreSelect() {
            const sel = document.getElementById('resCuatrimestreSelect');
            if (!sel) return;
            sel.innerHTML = '';
            
            const now = new Date();
            const currentYear = now.getFullYear();
            
            const cuats = [];
            [currentYear, currentYear + 1].forEach(yr => {
                cuats.push({
                    name: `Enero - Abril ${yr}`,
                    start: `${yr}-01-08`,
                    end: `${yr}-04-30`
                });
                cuats.push({
                    name: `Mayo - Agosto ${yr}`,
                    start: `${yr}-05-02`,
                    end: `${yr}-08-31`
                });
                cuats.push({
                    name: `Septiembre - Diciembre ${yr}`,
                    start: `${yr}-09-01`,
                    end: `${yr}-12-20`
                });
            });
            
            const todayStr = now.toISOString().split('T')[0];
            let selectedSet = false;
            
            cuats.forEach(c => {
                const opt = document.createElement('option');
                opt.value = `${c.start}|${c.end}`;
                opt.textContent = `${c.name} (${c.start} a ${c.end})`;
                
                if (!selectedSet && todayStr <= c.end) {
                    opt.selected = true;
                    selectedSet = true;
                }
                sel.appendChild(opt);
            });

            updateCuatrimestreDatesFromSelect();
        }
        
        if (btnResModeSingle) {
            btnResModeSingle.addEventListener('click', () => {
                btnResModeSingle.classList.add('active');
                btnResModeMultiple.classList.remove('active');
                if (btnResModeCuatrimestre) btnResModeCuatrimestre.classList.remove('active');
                resSingleDayFields.style.display = 'block';
                resMultiDayFields.style.display = 'none';
                if (resCuatrimestreFields) resCuatrimestreFields.style.display = 'none';
                state.resMode = 'single';
                
                document.getElementById('resFecha').required = true;
                document.getElementById('resFechaInicio').required = false;
                document.getElementById('resFechaFin').required = false;
                if (document.getElementById('resCuatFechaInicio')) document.getElementById('resCuatFechaInicio').required = false;
                if (document.getElementById('resCuatFechaFin')) document.getElementById('resCuatFechaFin').required = false;
                if (typeof checkAvailability === 'function') checkAvailability();
            });
        }

        if (btnResModeMultiple) {
            btnResModeMultiple.addEventListener('click', () => {
                btnResModeMultiple.classList.add('active');
                btnResModeSingle.classList.remove('active');
                if (btnResModeCuatrimestre) btnResModeCuatrimestre.classList.remove('active');
                resSingleDayFields.style.display = 'none';
                resMultiDayFields.style.display = 'flex';
                if (resCuatrimestreFields) resCuatrimestreFields.style.display = 'none';
                state.resMode = 'multiple';

                document.getElementById('resFecha').required = false;
                document.getElementById('resFechaInicio').required = true;
                document.getElementById('resFechaFin').required = true;
                if (document.getElementById('resCuatFechaInicio')) document.getElementById('resCuatFechaInicio').required = false;
                if (document.getElementById('resCuatFechaFin')) document.getElementById('resCuatFechaFin').required = false;
                if (typeof checkAvailability === 'function') checkAvailability();
            });
        }

        if (btnResModeCuatrimestre) {
            btnResModeCuatrimestre.addEventListener('click', () => {
                btnResModeCuatrimestre.classList.add('active');
                btnResModeSingle.classList.remove('active');
                btnResModeMultiple.classList.remove('active');
                resSingleDayFields.style.display = 'none';
                resMultiDayFields.style.display = 'none';
                if (resCuatrimestreFields) resCuatrimestreFields.style.display = 'flex';
                state.resMode = 'cuatrimestre';

                populateCuatrimestreSelect();

                document.getElementById('resFecha').required = false;
                document.getElementById('resFechaInicio').required = false;
                document.getElementById('resFechaFin').required = false;
                if (document.getElementById('resCuatFechaInicio')) document.getElementById('resCuatFechaInicio').required = true;
                if (document.getElementById('resCuatFechaFin')) document.getElementById('resCuatFechaFin').required = true;
                if (typeof checkAvailability === 'function') checkAvailability();
            });
        }

         function openResModal(defaultDate = null) {
            window.lastLoadedDate = "";
            // Rellenar fecha seleccionada
            const todayStr = defaultDate || new Date().toISOString().split('T')[0];
            document.getElementById('resFecha').value = todayStr;
            document.getElementById('resFechaInicio').value = todayStr;
            
            // Calcular fecha fin (hoy + 7 días por defecto para facilidad)
            const dFin = new Date(todayStr + 'T00:00:00');
            dFin.setDate(dFin.getDate() + 7);
            document.getElementById('resFechaFin').value = dFin.toISOString().split('T')[0];
            
            // Vaciar y resetear campos
            document.getElementById('resEdificio').value = "PIDET";
            document.getElementById('resEdificio').dispatchEvent(new Event('change'));
            document.getElementById('resEspacio').value = '';
            document.getElementById('resCapacidadLabel').value = "0 personas";

            // Limpiar error de capacidad si existe
            const numAlumnosInput = document.getElementById('resNumAlumnos');
            if (numAlumnosInput) {
                numAlumnosInput.style.borderColor = '';
                numAlumnosInput.style.boxShadow = '';
            }
            const capError = document.getElementById('resCapacidadError');
            if (capError) capError.style.display = 'none';

            const eqCont = document.getElementById('resEquipamientoContainer');
            if (eqCont) eqCont.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
            document.getElementById('resMotivo').value = "";
            document.getElementById('charCount').textContent = "0";
            document.getElementById('resWarningLong').style.display = 'none';
            if (document.getElementById('resHoraSal')) {
                document.getElementById('resHoraSal').value = "";
            }

            // Restablecer botón de confirmar (por si quedó en estado "Procesando")
            const btnConfirm = document.getElementById('btnConfirmReserva');
            if (btnConfirm) {
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
            }

            // Forzar volver a Día Único al abrir
            btnResModeSingle.click();

            reservationModal.style.display = 'flex';
            
            // Compensar la barra de scroll para que no brinque el fondo ("overflow")
            const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
            document.body.style.overflow = 'hidden';
            document.body.style.paddingRight = scrollbarWidth + 'px';

            // Inicializar o resetear mapa e interactividad de horarios de inmediato
            setTimeout(() => {
                if (typeof initModalMap === 'function') initModalMap();
                if (typeof checkAvailability === 'function') checkAvailability();
            }, 100);
        }

        window.closeResModal = function() {
            reservationModal.style.display = 'none';
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            // Restablecer siempre el botón al cerrar el modal
            const btnConfirm = document.getElementById('btnConfirmReserva');
            if (btnConfirm) {
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
            }
        }

        if(btnNewReservation) btnNewReservation.addEventListener('click', () => openResModal());
        if(btnExitResModal) btnExitResModal.addEventListener('click', window.closeResModal);
        if(btnCancelReserva) btnCancelReserva.addEventListener('click', window.closeResModal);

        // Al cambiar edificio en la reserva
        if(resEdificio) {
            resEdificio.addEventListener('change', (e) => {
                const edif = e.target.value;
                const filtered = allSpaces.filter(sp => sp.edificio === edif);
                
                let opts = '<option value="">Seleccione espacio...</option>';
                filtered.forEach(sp => {
                    // Mostrar las Salas Magnas individualmente (Sala Magna 1, 2, 3, 4)
                    opts += `<option value="${sp.esp_id}">${sp.nombre_numero} (${sp.tipo})</option>`;
                });
                resEspacio.innerHTML = opts;
                document.getElementById('resCapacidadLabel').value = "0 personas";
                const eqCont2 = document.getElementById('resEquipamientoContainer');
                if (eqCont2) eqCont2.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
            });
        }

        // Al cambiar espacio en la reserva
        if(resEspacio) {
            resEspacio.addEventListener('change', (e) => {
                const val = e.target.value;
                const eqContainer = document.getElementById('resEquipamientoContainer');
                const btnConfirm = document.getElementById('btnConfirmReserva');
                const numInput = document.getElementById('resNumAlumnos');

                // ── NAVEGACIÓN INTELIGENTE ENTRE MAPAS ──
                // Al seleccionar un espacio, detectar su edificio y planta y cambiar el mapa
                if (val) {
                    const espId = parseInt(val);
                    const spNav = allSpaces.find(sp => sp.esp_id === espId);
                    if (spNav) {
                        const edifNav = spNav.edificio || 'PIDET';
                        // Determinar la planta según el campo planta del espacio
                        let plantaNav = (spNav.planta || '').toLowerCase();
                        // 'Alta' -> 'alta', 'Baja' -> 'baja'
                        if (plantaNav === 'planta alta') plantaNav = 'alta';
                        if (plantaNav === 'planta baja') plantaNav = 'baja';
                        
                        if (!plantaNav && Object.keys(MAP_DATA).length > 0) {
                            // Buscar el esp_id en MAP_DATA para determinar la planta correcta
                            for (const [mapKey, mapCfg] of Object.entries(MAP_DATA)) {
                                if (mapCfg.zones && mapCfg.zones.some(z => z.esp_id == espId)) {
                                    plantaNav = mapKey.split('_')[1] || 'alta';
                                    break;
                                }
                            }
                        }
                        if (!plantaNav) {
                            // Último fallback: inferir por nombre
                            const nombreLower = (spNav.nombre_numero || '').toLowerCase();
                            if (nombreLower.includes('baja') || nombreLower.includes('aula 0') || nombreLower.includes('auditorio') || nombreLower.includes('magna')) {
                                plantaNav = 'baja';
                            } else {
                                plantaNav = 'alta';
                            }
                        }

                        const edifSelect = document.getElementById('resEdificio');
                        const plantaSelect = document.getElementById('resPlanta');

                        isProgrammaticMapChange = true;
                        try {
                            let mapChanged = false;
                            if (edifSelect && edifSelect.value !== edifNav) {
                                edifSelect.value = edifNav;
                                edifSelect.dispatchEvent(new Event('change'));
                                mapChanged = true;
                            }
                            if (plantaSelect && plantaSelect.value !== plantaNav) {
                                plantaSelect.value = plantaNav;
                                if (!mapChanged) {
                                    plantaSelect.dispatchEvent(new Event('change'));
                                }
                            }
                            if (resEspacio && resEspacio.value !== val) {
                                resEspacio.value = val;
                            }
                        } finally {
                            isProgrammaticMapChange = false;
                        }
                        // Sincronizar el polígono en el mapa después de que cargue la imagen
                        setTimeout(() => {
                            if (typeof syncMapFromForm === 'function') syncMapFromForm();
                        }, 300);
                    }
                }

                // Quitar banners informativos anteriores
                const prevBox = document.getElementById('resDynamicInfoBox');
                if (prevBox) prevBox.remove();

                // Limpiar error de capacidad anterior
                if (numInput) {
                    numInput.style.borderColor = '';
                    numInput.style.boxShadow = '';
                }
                const capError = document.getElementById('resCapacidadError');
                if (capError) capError.style.display = 'none';

                const infoBox = document.createElement('div');
                infoBox.id = 'resDynamicInfoBox';
                infoBox.style.marginTop = '10px';

                // Detectar si el espacio seleccionado es una Sala Magna individual
                const isSalaMagna = spObj2Check => spObj2Check && spObj2Check.nombre_numero && spObj2Check.nombre_numero.startsWith('Sala Magna');
                const espIdNum = parseInt(val);
                const spObjCheck = allSpaces.find(sp => sp.esp_id === espIdNum);

                if (isSalaMagna(spObjCheck)) {
                    // Sala Magna individual: capacidad fija de 24 personas
                    document.getElementById('resCapacidadLabel').value = '24 personas';
                    btnConfirm.disabled = false;
                    if (numInput) numInput.value = 24;

                    infoBox.innerHTML = `
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div style="font-weight: 700; font-size: 15px; color: #0f172a;">${spObjCheck.nombre_numero}</div>
                                <div style="background: #22c55e15; color: #16a34a; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    Acceso General
                                </div>
                            </div>
                            <div style="font-size: 13px; color: #475569; margin-bottom: 12px;">
                                <i class="bi bi-people-fill" style="margin-right:4px;"></i> Capacidad: <strong>24 personas</strong>
                            </div>
                            <div style="background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; padding: 8px 12px; font-size: 12px; font-weight: 500;">
                                <i class="bi bi-info-circle-fill" style="margin-right: 4px;"></i> Sala individual. La disponibilidad se verifica en tiempo real.
                            </div>
                        </div>
                    `;
                    e.target.parentElement.appendChild(infoBox);

                    // Cargar equipamiento de PIDET
                    const spAssetsMagna = allAssets.filter(as => as.edificio === 'PIDET');
                    if (spAssetsMagna.length > 0) {
                        let html = '';
                        spAssetsMagna.forEach(as => {
                            html += `<label style='display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-primary); cursor: pointer;'>
                                <input type='checkbox' class='equipamiento-checkbox' value='${as.act_id}'>
                                ${as.tipo} ${as.marca} ${as.modelo || ''}
                            </label>`;
                        });
                        eqContainer.innerHTML = html;
                    } else {
                        eqContainer.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Sin equipamiento específico disponible.</div>';
                    }
                    checkAvailability();
                    return;
                }

                const espId = parseInt(val);
                const spObj = allSpaces.find(sp => sp.esp_id === espId);
                if (spObj) {
                    document.getElementById('resCapacidadLabel').value = `${spObj.capacidad} personas`;
                    
                    // Asignar el valor predeterminado del número de alumnos al máximo del espacio
                    const numAlumnosField = document.getElementById('resNumAlumnos');
                    if (numAlumnosField) {
                        numAlumnosField.value = spObj.capacidad;
                    }

                    const rules = getSpaceRules(spObj);
                    
                    // Tarjeta Elegante de Información
                    infoBox.innerHTML = `
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div style="font-weight: 700; font-size: 15px; color: #0f172a;">${spObj.nombre_numero}</div>
                                <div style="background: ${rules.color_tema}15; color: ${rules.color_tema}; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    ${rules.tipo_acceso}
                                </div>
                            </div>
                            <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
                                <i class="bi bi-diagram-3-fill" style="margin-right:4px;"></i> Tipo: <strong>${spObj.tipo}</strong>
                            </div>
                            <div style="font-size: 13px; color: #475569; margin-bottom: 12px;">
                                <i class="bi bi-people-fill" style="margin-right:4px;"></i> Capacidad: <strong>${spObj.capacidad} pers.</strong>
                            </div>
                            <div style="background: ${rules.color_tema}10; color: ${rules.color_tema}; border-left: 4px solid ${rules.color_tema}; padding: 8px 12px; font-size: 12px; font-weight: 500;">
                                <i class="bi ${rules.icono}" style="margin-right: 4px;"></i> ${rules.mensaje_tooltip}
                            </div>
                        </div>
                    `;
                    e.target.parentElement.appendChild(infoBox);

                    // Deshabilitar botón si no es reservable o si es administrador (y no lo es)
                    if (!rules.es_reservable) {
                        btnConfirm.disabled = true;
                    } else if (rules.tipo_acceso === 'Requiere Administración') {
                        if (!isUserAdmin) {
                            btnConfirm.disabled = true;
                            infoBox.innerHTML += `<div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; margin-top: 8px;">
                                <i class="bi bi-shield-lock-fill"></i> Acceso Exclusivo: Este espacio está catalogado para uso exclusivo de Administradores.
                            </div>`;
                        } else {
                            btnConfirm.disabled = false;
                            const btnMulti = document.getElementById('btnResModeMultiple');
                            if (btnMulti) btnMulti.click();
                        }
                    } else {
                        btnConfirm.disabled = false;
                    }

                    // Buscar equipamiento asignado a este espacio o edificio
                    const spAssets = allAssets.filter(as => as.esp_asignado == espId || (as.edificio === spObj.edificio && !as.esp_asignado));
                    if(spAssets.length > 0) {
                        let html = '';
                        spAssets.forEach(as => {
                            html += `<label style='display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-primary); cursor: pointer;'>
                                <input type='checkbox' class='equipamiento-checkbox' value='${as.act_id}'>
                                ${as.tipo} ${as.marca} ${as.modelo || ''}
                            </label>`;
                        });
                        eqContainer.innerHTML = html;
                    } else {
                        eqContainer.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Sin equipamiento específico disponible.</div>';
                    }
                } else {
                    eqContainer.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
                }

                // ── VALIDACIÓN DE CAPACIDAD EN TIEMPO REAL ──
                // Al cambiar espacio, se ata un listener al campo num_alumnos
                if (numInput) {
                    if (window._capacidadInputHandler) {
                        numInput.removeEventListener('input', window._capacidadInputHandler);
                    }
                    window._capacidadInputHandler = function() {
                        const espId2 = document.getElementById('resEspacio').value;
                        if (!espId2) return;
                        const spObj2 = allSpaces.find(sp => sp.esp_id === parseInt(espId2));
                        if (!spObj2) return;
                        const cap = parseInt(spObj2.capacidad || 0);
                        const asistentes = parseInt(numInput.value || 0);
                        const capError2 = document.getElementById('resCapacidadError');
                        if (cap > 0 && asistentes > cap) {
                            numInput.style.borderColor = '#ef4444';
                            numInput.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.15)';
                            if (capError2) {
                                capError2.textContent = `Este espacio admite un máximo de ${cap} personas. (Actual: ${asistentes})`;
                                capError2.style.display = 'block';
                            }
                        } else {
                            numInput.style.borderColor = '';
                            numInput.style.boxShadow = '';
                            if (capError2) capError2.style.display = 'none';
                        }
                    };
                    numInput.addEventListener('input', window._capacidadInputHandler);
                }

                checkAvailability();
            });
        }

        const resFecha = document.getElementById('resFecha');
        if (resFecha) {
            resFecha.addEventListener('change', checkAvailability);
        }

        window.updateWarningLong = function() {
            const selectHoraEnt = document.getElementById('resHoraEnt');
            const selectHoraSal = document.getElementById('resHoraSal');
            const resWarningLong = document.getElementById('resWarningLong');
            if (!selectHoraEnt || !selectHoraSal || !resWarningLong) return;

            const hEnt = parseInt(selectHoraEnt.value);
            const hSal = parseInt(selectHoraSal.value);
            if (!isNaN(hEnt) && !isNaN(hSal) && (hSal - hEnt) > 2) {
                resWarningLong.style.display = 'block';
            } else {
                resWarningLong.style.display = 'none';
            }
        };

        window.updateHoraFinOptions = function() {
            const selectHoraEnt = document.getElementById('resHoraEnt');
            const selectHoraSal = document.getElementById('resHoraSal');
            if (!selectHoraEnt || !selectHoraSal) return;

            const horaEntVal = selectHoraEnt.value;
            if (!horaEntVal) {
                let opts = '<option value="" disabled selected style="display:none;"></option>';
                const labels = {
                    '09:00': '09:00 AM', '10:00': '10:00 AM', '11:00': '11:00 AM', '12:00': '12:00 PM',
                    '13:00': '01:00 PM', '14:00': '02:00 PM', '15:00': '03:00 PM', '16:00': '04:00 PM',
                    '17:00': '05:00 PM', '18:00': '06:00 PM', '19:00': '07:00 PM', '20:00': '08:00 PM'
                };
                
                const fechaEl = document.getElementById('resFecha');
                const fecha = fechaEl ? fechaEl.value : '';
                const now = new Date();
                const tzOffset = now.getTimezoneOffset() * 60000;
                const localISOTime = (new Date(now.getTime() - tzOffset)).toISOString().slice(0, 10);
                const isTodayExact = (fecha === localISOTime);
                const currentHour = now.getHours();

                for (let h = 9; h <= 20; h++) {
                    const valStr = h < 10 ? `0${h}:00` : `${h}:00`;
                    let label = labels[valStr] || `${h}:00`;
                    let isDisabled = false;
                    
                    const prevStartVal = (h - 1) < 10 ? `0${h - 1}:00` : `${h - 1}:00`;
                    const startOpt = Array.from(selectHoraEnt.options).find(o => o.value === prevStartVal);

                    if (startOpt && startOpt.disabled) {
                        isDisabled = true;
                        if (startOpt.text.includes('(Pasada)')) {
                            label += ' (Pasada)';
                        } else if (startOpt.text.includes('(Ocupado)') || startOpt.text.includes('(Sin salas disponibles)')) {
                            label += ' (Ocupado)';
                        } else {
                            label += ' (No disponible)';
                        }
                    } else if (isTodayExact && h <= currentHour) {
                        isDisabled = true;
                        label += ' (Pasada)';
                    }
                    
                    if (isDisabled) {
                        opts += `<option value="${valStr}" disabled>${label}</option>`;
                    } else {
                        opts += `<option value="${valStr}">${label}</option>`;
                    }
                }
                selectHoraSal.innerHTML = opts;
                return;
            }

            const entHour = parseInt(horaEntVal);
            
            // Determinar la primera hora de reserva ocupada que sea estrictamente posterior a entHour
            let firstOcupiedHour = 24; // Por defecto medianoche o fuera de rango
            Array.from(selectHoraEnt.options).forEach(opt => {
                const h = parseInt(opt.value);
                if (h > entHour && opt.disabled && (opt.text.includes('(Ocupado)') || opt.text.includes('(Sin salas disponibles)'))) {
                    if (h < firstOcupiedHour) {
                        firstOcupiedHour = h;
                    }
                }
            });

            // Generar las opciones de Hora de Fin (desde entHour + 1 hasta el limite o la primera ocupada)
            let opts = '';
            const labels = {
                '09:00': '09:00 AM', '10:00': '10:00 AM', '11:00': '11:00 AM', '12:00': '12:00 PM',
                '13:00': '01:00 PM', '14:00': '02:00 PM', '15:00': '03:00 PM', '16:00': '04:00 PM',
                '17:00': '05:00 PM', '18:00': '06:00 PM', '19:00': '07:00 PM', '20:00': '08:00 PM'
            };

            for (let h = entHour + 1; h <= 20; h++) {
                const valStr = h < 10 ? `0${h}:00` : `${h}:00`;
                const label = labels[valStr] || `${h}:00`;
                
                if (h <= firstOcupiedHour) {
                    opts += `<option value="${valStr}">${label}</option>`;
                } else {
                    opts += `<option value="${valStr}" disabled>${label} (Ocupado/Traslape)</option>`;
                }
            }

            selectHoraSal.innerHTML = opts;

            // Seleccionar por defecto la primera opción de hora fin (1 hora de duración) o la seleccionada anteriormente si es válida
            const prevVal = selectHoraSal.value;
            if (prevVal && parseInt(prevVal) > entHour && parseInt(prevVal) <= firstOcupiedHour) {
                selectHoraSal.value = prevVal;
            } else {
                selectHoraSal.selectedIndex = 0;
            }

            // Disparar validación de advertencia de > 2 horas
            updateWarningLong();
        };

        const selectHoraEnt = document.getElementById('resHoraEnt');
        if (selectHoraEnt) {
            selectHoraEnt.addEventListener('change', () => {
                updateHoraFinOptions();
            });
        }

        const selectHoraSal = document.getElementById('resHoraSal');
        if (selectHoraSal) {
            selectHoraSal.addEventListener('change', () => {
                updateWarningLong();
            });
        }

        function checkAvailability() {
            // Re-habilitar controles y restablecer botón de confirmación
            const selectHoraEnt = document.getElementById('resHoraEnt');
            const selectHoraSal = document.getElementById('resHoraSal');
            const btnConfirm = document.getElementById('btnConfirmReserva');
            if (selectHoraEnt) selectHoraEnt.disabled = false;
            if (selectHoraSal) selectHoraSal.disabled = false;
            if (btnConfirm) {
                btnConfirm.disabled = false;
                btnConfirm.style.opacity = '1';
            }

            if (state.resMode !== 'single') {
                // En multi-día no se pueden consultar conflictos del backend,
                // pero sí actualizamos las opciones de hora fin
                updateHoraFinOptions();
                return;
            }
            const espId = resEspacio.value;
            const fecha = document.getElementById('resFecha').value;
            if (!fecha) return;

            if (window.lastLoadedDate !== fecha) {
                window.lastLoadedDate = fecha;
                fetch(`../backend/api/index.php/reservations?date=${fecha}`)
                    .then(r => r.json())
                    .then(data => {
                        window.reservationsForSelectedDate = Array.isArray(data) ? data : [];
                        if (typeof renderModalMap === 'function') {
                            renderModalMap();
                        }
                        checkAvailability();
                    })
                    .catch(err => console.error("Error loading daily reservations:", err));
                return;
            }
            
            const now = new Date();
            const tzOffset = now.getTimezoneOffset() * 60000;
            const localISOTime = (new Date(now.getTime() - tzOffset)).toISOString().slice(0, 10);
            const isTodayExact = fecha === localISOTime;
            const currentHour = now.getHours();

            // Habilitar todos primero y limpiar texto extra
            const selectHora = document.getElementById('resHoraEnt');
            const horaLabels = {
                '08:00': '08:00 AM', '09:00': '09:00 AM', '10:00': '10:00 AM', '11:00': '11:00 AM',
                '12:00': '12:00 PM', '13:00': '01:00 PM', '14:00': '02:00 PM', '15:00': '03:00 PM',
                '16:00': '04:00 PM', '17:00': '05:00 PM', '18:00': '06:00 PM'
            };
            Array.from(selectHora.options).forEach(opt => {
                opt.disabled = false;
                const baseText = horaLabels[opt.value] || opt.value;
                opt.text = baseText;
                
                const h = parseInt(opt.value);
                if (isTodayExact && h <= currentHour) {
                    opt.disabled = true;
                    opt.text = baseText + ' (Pasada)';
                }
            });

            if (!espId) {
                // Si aún no se selecciona espacio, seleccionar el primer horario disponible basándonos en la hora actual
                let firstAvailableIndex = -1;
                for (let i = 0; i < selectHora.options.length; i++) {
                    if (!selectHora.options[i].disabled) {
                        firstAvailableIndex = i;
                        break;
                    }
                }
                if (firstAvailableIndex !== -1) {
                    selectHora.selectedIndex = firstAvailableIndex;
                }
                updateHoraFinOptions();
                return;
            }
            
            // Verificar si es una Sala Magna individual para también comprobar disponibilidad global
            const espIdNum2 = parseInt(espId);
            const spSelected = !isNaN(espIdNum2) ? allSpaces.find(sp => sp.esp_id === espIdNum2) : null;
            const isSelectedSalaMagna = spSelected && spSelected.nombre_numero && spSelected.nombre_numero.startsWith('Sala Magna');

            // Obtener todos los esp_ids de Salas Magnas disponibles en allSpaces
            const salasMagnaIds = allSpaces
                .filter(sp => sp.nombre_numero && sp.nombre_numero.startsWith('Sala Magna'))
                .map(sp => sp.esp_id);

            fetch(`../backend/api/index.php/reservations?esp_id=${espId}&date=${fecha}`)
                .then(res => res.json())
                .then(async data => {
                    // Marcar horas ocupadas de la sala seleccionada
                    if (data && data.length > 0) {
                        Array.from(selectHora.options).forEach(opt => {
                            const optHour = parseInt(opt.value);
                            data.forEach(res => {
                                const estatus = (res.estatus || res.status || '').toLowerCase();
                                if (estatus === 'rechazada' || estatus === 'rejected' || estatus === 'cancelada' || estatus === 'cancelled') {
                                    return;
                                }
                                const startH = parseInt(res.hora_ent);
                                const endH = parseInt(res.hora_sal);
                                if (optHour >= startH && optHour < endH) {
                                    opt.disabled = true;
                                    const baseText = horaLabels[opt.value] || opt.value;
                                    opt.text = baseText + ' (Ocupado)';
                                }
                            });
                        });
                    }

                    // Si es Sala Magna individual, verificar si TODAS las salas están ocupadas por cada hora
                    if (isSelectedSalaMagna && salasMagnaIds.length > 1) {
                        try {
                            // Traer reservas de todas las salas magnas para esa fecha
                            const allMagnaFetches = salasMagnaIds.map(id =>
                                fetch(`../backend/api/index.php/reservations?esp_id=${id}&date=${fecha}`)
                                    .then(r => r.json())
                                    .catch(() => [])
                            );
                            const allMagnaData = await Promise.all(allMagnaFetches);

                            // Para cada hora, comprobar si todas las salas están ocupadas
                            Array.from(selectHora.options).forEach(opt => {
                                if (opt.disabled) return; // ya bloqueada
                                const optHour = parseInt(opt.value);
                                const totalSalas = salasMagnaIds.length;
                                let salasOcupadas = 0;

                                allMagnaData.forEach(magnaReservas => {
                                    if (!magnaReservas || magnaReservas.length === 0) return;
                                    const ocupada = magnaReservas.some(res => {
                                        const estatus = (res.estatus || res.status || '').toLowerCase();
                                        if (estatus === 'rechazada' || estatus === 'rejected' || estatus === 'cancelada' || estatus === 'cancelled') return false;
                                        const startH = parseInt(res.hora_ent);
                                        const endH = parseInt(res.hora_sal);
                                        return optHour >= startH && optHour < endH;
                                    });
                                    if (ocupada) salasOcupadas++;
                                });

                                if (salasOcupadas >= totalSalas) {
                                    opt.disabled = true;
                                    const baseText = horaLabels[opt.value] || opt.value;
                                    opt.text = baseText + ' (Sin salas disponibles)';
                                }
                            });
                        } catch(e) {
                            console.warn('No se pudo verificar disponibilidad global de Sala Magna:', e);
                        }
                    }

                    // Verificar si el espacio está completamente ocupado por todo el día
                    const isFullyOccupied = typeof isSpaceFullyOccupied === 'function' && isSpaceFullyOccupied(espId);

                    if (isFullyOccupied) {
                        selectHora.value = "";
                        selectHora.disabled = true;
                        selectHoraSal.value = "";
                        selectHoraSal.innerHTML = '<option value="" disabled selected style="display:none;"></option>';
                        selectHoraSal.disabled = true;
                        
                        if (btnConfirm) {
                            btnConfirm.disabled = true;
                            btnConfirm.style.opacity = '0.5';
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Espacio Ocupado',
                            text: 'Este espacio ya está completamente ocupado por todo el día.',
                            confirmButtonColor: '#ef4444'
                        });
                    } else {
                        // Seleccionar automáticamente el primer horario disponible no deshabilitado
                        let firstAvailableIndex = -1;
                        for (let i = 0; i < selectHora.options.length; i++) {
                            if (!selectHora.options[i].disabled) {
                                firstAvailableIndex = i;
                                break;
                            }
                        }
                        
                        if (firstAvailableIndex !== -1) {
                            selectHora.selectedIndex = firstAvailableIndex;
                            selectHora.disabled = false;
                            selectHoraSal.disabled = false;
                        } else {
                            selectHora.value = "";
                        }
                        
                        if (btnConfirm) {
                            btnConfirm.disabled = false;
                            btnConfirm.style.opacity = '1';
                        }
                        
                        updateHoraFinOptions();
                    }
                })
                .catch(err => console.error("Error check availability", err));
        }

        // Envío del formulario de reserva
        const resForm = document.getElementById('reservationForm');
        if (resForm) {
            resForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitReservation();
            });
        }

        // ----------------------------------------------------
        // LOGICA DE SELECTOR DE FECHA PERSONALIZADO
        // ----------------------------------------------------
        const monthPickerTrigger = document.getElementById('monthPickerTrigger');
        const monthPickerDropdown = document.getElementById('monthPickerDropdown');
        const monthPickerChevron = document.getElementById('monthPickerChevron');
        const currentMonthYearLabel = document.getElementById('currentMonthYearLabel');
        const pickerYearLabel = document.getElementById('pickerYearLabel');
        const prevYearBtn = document.getElementById('prevYearBtn');
        const nextYearBtn = document.getElementById('nextYearBtn');
        
        let pickerCurrentYear = state.currentDate.getFullYear();
        
        window.updateCustomPickerUI = function() {
            const currentMonth = state.currentDate.getMonth();
            const currentYear = state.currentDate.getFullYear();
            
            const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            if (currentMonthYearLabel) {
                currentMonthYearLabel.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            }
            
            if (pickerYearLabel) {
                pickerYearLabel.textContent = pickerCurrentYear;
            }
            
            document.querySelectorAll('.picker-month-btn').forEach(btn => {
                const btnMonth = parseInt(btn.dataset.month);
                if (btnMonth === currentMonth && pickerCurrentYear === currentYear) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        };
        
        if (monthPickerTrigger) {
            monthPickerTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = monthPickerDropdown.style.display === 'block';
                if (isOpen) {
                    closeCustomPicker();
                } else {
                    openCustomPicker();
                }
            });
        }
        
        function openCustomPicker() {
            if (monthPickerDropdown) monthPickerDropdown.style.display = 'block';
            if (monthPickerChevron) monthPickerChevron.style.transform = 'rotate(180deg)';
            pickerCurrentYear = state.currentDate.getFullYear();
            window.updateCustomPickerUI();
        }
        
        function closeCustomPicker() {
            if (monthPickerDropdown) monthPickerDropdown.style.display = 'none';
            if (monthPickerChevron) monthPickerChevron.style.transform = 'rotate(0deg)';
        }
        
        document.addEventListener('click', (e) => {
            if (monthPickerDropdown && !monthPickerDropdown.contains(e.target) && e.target !== monthPickerTrigger) {
                closeCustomPicker();
            }
        });
        
        if (prevYearBtn) {
            prevYearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                pickerCurrentYear--;
                window.updateCustomPickerUI();
            });
        }
        
        if (nextYearBtn) {
            nextYearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                pickerCurrentYear++;
                window.updateCustomPickerUI();
            });
        }
        
        document.querySelectorAll('.picker-month-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const selectedMonth = parseInt(btn.dataset.month);
                
                monthSelect.value = selectedMonth;
                
                let yearOptionExists = false;
                for (let i = 0; i < yearSelect.options.length; i++) {
                    if (parseInt(yearSelect.options[i].value) === pickerCurrentYear) {
                        yearOptionExists = true;
                        break;
                    }
                }
                
                if (!yearOptionExists) {
                    const opt = document.createElement('option');
                    opt.value = pickerCurrentYear;
                    opt.textContent = pickerCurrentYear;
                    yearSelect.appendChild(opt);
                }
                
                yearSelect.value = pickerCurrentYear;
                monthSelect.dispatchEvent(new Event('change'));
                closeCustomPicker();
            });
        });

        // ----------------------------------------------------
        // LOGICA DE MODAL DE DETALLES
        // ----------------------------------------------------
        const resDetailsModal = document.getElementById('resDetailsModal');
        const btnExitDetailsModal = document.getElementById('btnExitDetailsModal');
        const btnCloseDetailsModal = document.getElementById('btnCloseDetailsModal');
        
        if (btnExitDetailsModal) btnExitDetailsModal.addEventListener('click', () => { resDetailsModal.style.display = 'none'; document.body.style.overflow = ''; });
        if (btnCloseDetailsModal) btnCloseDetailsModal.addEventListener('click', () => { resDetailsModal.style.display = 'none'; document.body.style.overflow = ''; });
        if (resDetailsModal) {
            resDetailsModal.addEventListener('click', (e) => {
                if (e.target === resDetailsModal) {
                    resDetailsModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    }

    // ----------------------------------------------------
    // SINCRO FILTROS RAPIDOS & FETCH INICIAL
    // ----------------------------------------------------
    function syncFiltersAndFetch() {
        fetchEvents();
    }

    // ----------------------------------------------------
    // AJUSTAR FECHA DE NAVEGACIÓN (FLECHAS)
    // ----------------------------------------------------
    function adjustDate(direction) {
        const d = state.currentDate;
        if (state.currentView === 'month') {
            d.setMonth(d.getMonth() + direction);
        } else if (state.currentView === 'week') {
            d.setDate(d.getDate() + (direction * 7));
        }
        
        // Sincronizar selectores
        document.getElementById('selectMonthNav').value = d.getMonth();
        document.getElementById('selectYearNav').value = d.getFullYear();

        renderActiveCalendar();
    }

    // ----------------------------------------------------
    // OBTENER RESERVACIONES DESDE LA API
    // ----------------------------------------------------
    window.fetchEvents = function() {
        let url = '../backend/api/index.php/calendar/events';
        
        fetch(url, { credentials: 'same-origin' })
            .then(res => {
                if(!res.ok) throw new Error("Error en la petición: " + res.statusText);
                return res.json();
            })
            .then(data => {
                state.events = Array.isArray(data) ? data : [];
                renderActiveCalendar();
            })
            .catch(err => console.error("Error al cargar reservaciones del calendario:", err));
    }

    // ----------------------------------------------------
    // RENDERIZAR CALENDARIO SELECCIONADO
    // ----------------------------------------------------
    function renderActiveCalendar() {
        // Actualizar dropdowns de mes/año nav
        document.getElementById('selectMonthNav').value = state.currentDate.getMonth();
        document.getElementById('selectYearNav').value = state.currentDate.getFullYear();
        
        // Sincronizar UI del seleccionador de fecha personalizado
        if (typeof window.updateCustomPickerUI === 'function') {
            window.updateCustomPickerUI();
        }
        
        // Renderizar filtros tags
        renderActiveFiltersTags();

        // Renderizar según vista activa
        if (state.currentView === 'month') {
            renderMonthView();
        } else if (state.currentView === 'week') {
            renderWeekView();
        }

        // Actualizar sidebar e indicadores de resumen
        updateSidebarStats();
    }

    // ----------------------------------------------------
    // RENDER DE FILTROS ACTIVOS (TAGS)
    // ----------------------------------------------------
    const mesesEsp = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    function renderActiveFiltersTags() {
        const container = document.getElementById('activeFiltersContainer');
        const highlightBar = document.getElementById('highlightBar');
        container.innerHTML = '';
        
        const tags = [];
        const highlightTexts = [];

        if (state.filters.edificio.length > 0) {
            tags.push({ key: 'edificio', label: `Edificio: ${state.filters.edificio.join(', ')}` });
            highlightTexts.push(`Edificio: ${state.filters.edificio.join(', ')}`);
        }
        if (state.filters.tipo.length > 0) {
            tags.push({ key: 'tipo', label: `Tipo: ${state.filters.tipo.join(', ')}` });
            highlightTexts.push(`Tipo: ${state.filters.tipo.join(', ')}`);
        }
        if (state.filters.esp_id) {
            const sp = allSpaces.find(s => s.esp_id == state.filters.esp_id);
            if(sp) {
                tags.push({ key: 'esp_id', label: `Espacio: ${sp.edificio} - ${sp.nombre_numero}` });
                highlightTexts.push(`Espacio: ${sp.edificio} - ${sp.nombre_numero}`);
            }
        }
        if (state.filters.status !== 'Todos') {
            tags.push({ key: 'status', label: `Estatus: ${state.filters.status}` });
            highlightTexts.push(`Estatus: ${state.filters.status}`);
        }
        if (state.filters.hora_inicio !== '08:00' || state.filters.hora_fin !== '20:00') {
            tags.push({ key: 'hours', label: `Horario: ${state.filters.hora_inicio} a ${state.filters.hora_fin}` });
            highlightTexts.push(`Horario: ${state.filters.hora_inicio} a ${state.filters.hora_fin}`);
        }
        if (state.filters.capacidad > 5) {
            tags.push({ key: 'capacidad', label: `Capacidad: ≥${state.filters.capacidad} pers.` });
            highlightTexts.push(`Capacidad: ≥${state.filters.capacidad} pers.`);
        }
        if (state.filters.us_id) {
            tags.push({ key: 'us_id', label: 'Solo mis reservas' });
            highlightTexts.push('Mis reservaciones');
        }

        if (tags.length > 0) {
            container.style.display = 'flex';
            tags.forEach(t => {
                const tagEl = document.createElement('div');
                tagEl.className = 'filter-tag';
                tagEl.innerHTML = `
                    <span>${t.label}</span>
                    <i class="bi bi-x" onclick="removeActiveFilter('${t.key}')"></i>
                `;
                container.appendChild(tagEl);
            });
            
            // Botón Limpiar Todo
            const btnClear = document.createElement('button');
            btnClear.className = 'btn-clear-all-filters';
            btnClear.textContent = 'Limpiar todo';
            btnClear.onclick = () => clearSidebarFilters();
            container.appendChild(btnClear);

            // Highlight bar
            highlightBar.style.display = 'block';
            highlightBar.textContent = `Mostrando: ${highlightTexts.join(' · ')}`;
        } else {
            container.style.display = 'none';
            highlightBar.style.display = 'none';
        }
    }

    window.removeActiveFilter = function(key) {
        if (key === 'edificio') {
            state.filters.edificio = [];
            document.querySelectorAll('input[name="filter_edificio"]').forEach(c => c.checked = false);
            document.getElementById('quickFilterEdificio').value = "";
        } else if (key === 'tipo') {
            state.filters.tipo = [];
            document.querySelectorAll('input[name="filter_tipo"]').forEach(c => c.checked = false);
            document.getElementById('quickFilterTipo').value = "";
        } else if (key === 'esp_id') {
            state.filters.esp_id = '';
            document.getElementById('filterEspacioSelect').value = '';
            document.getElementById('quickFilterEspacio').value = '';
        } else if (key === 'status') {
            state.filters.status = 'Todos';
            document.querySelector('input[name="filter_status"][value="Todos"]').checked = true;
            document.getElementById('quickFilterStatus').value = 'Todos';
        } else if (key === 'hours') {
            state.filters.hora_inicio = '08:00';
            state.filters.hora_fin = '20:00';
            document.getElementById('filterHoraInicio').value = '08:00';
            document.getElementById('filterHoraFin').value = '20:00';
        } else if (key === 'capacidad') {
            state.filters.capacidad = 5;
            document.getElementById('filterCapacidad').value = 5;
            document.getElementById('capacidadSliderLabel').textContent = 'Mínimo: 5 personas';
        } else if (key === 'us_id') {
            state.filters.us_id = '';
            document.getElementById('filterSoloMisReservas').checked = false;
            document.getElementById('quickFilterSoloMisReservas').checked = false;
        }
        renderActiveCalendar();
    };

    // APLICAR FILTROS DESDE SIDEBAR
    function applySidebarFilters() {
        // Edificio
        const edificios = [];
        document.querySelectorAll('input[name="filter_edificio"]:checked').forEach(c => edificios.push(c.value));
        state.filters.edificio = edificios;

        // Tipo
        const tipos = [];
        document.querySelectorAll('input[name="filter_tipo"]:checked').forEach(c => tipos.push(c.value));
        state.filters.tipo = tipos;

        // Espacio
        state.filters.esp_id = document.getElementById('filterEspacioSelect').value;

        // Estatus
        state.filters.status = document.querySelector('input[name="filter_status"]:checked').value;

        // Rango de fechas
        state.filters.fecha_inicio = document.getElementById('filterFechaDesde').value;
        state.filters.fecha_fin = document.getElementById('filterFechaHasta').value;

        // Horas
        state.filters.hora_inicio = document.getElementById('filterHoraInicio').value;
        state.filters.hora_fin = document.getElementById('filterHoraFin').value;

        // Capacidad
        state.filters.capacidad = parseInt(document.getElementById('filterCapacidad').value);

        // Solo mis reservaciones
        state.filters.us_id = document.getElementById('filterSoloMisReservas').checked ? sessionUserId : '';

        // Sincronizar filtros rápidos
        document.getElementById('quickFilterEdificio').value = edificios.length === 1 ? edificios[0] : "";
        document.getElementById('quickFilterTipo').value = tipos.length === 1 ? tipos[0] : "";
        
        // Recargar selector de espacios rápido
        const quickEsp = document.getElementById('quickFilterEspacio');
        let quickOpts = '<option value="">Todos</option>';
        allSpaces.forEach(s => {
            quickOpts += `<option value="${s.esp_id}">${s.edificio} - ${s.nombre_numero}</option>`;
        });
        quickEsp.innerHTML = quickOpts;
        quickEsp.value = state.filters.esp_id;

        document.getElementById('quickFilterStatus').value = state.filters.status;
        document.getElementById('quickFilterSoloMisReservas').checked = !!state.filters.us_id;

        renderActiveCalendar();
    }

    // LIMPIAR FILTROS
    function clearSidebarFilters() {
        state.filters = {
            edificio: [],
            tipo: [],
            esp_id: '',
            status: 'Todos',
            fecha_inicio: '',
            fecha_fin: '',
            hora_inicio: '08:00',
            hora_fin: '20:00',
            capacidad: 5,
            us_id: ''
        };

        // Reset Inputs Sidebar
        document.querySelectorAll('input[name="filter_edificio"]').forEach(c => c.checked = false);
        document.querySelectorAll('input[name="filter_tipo"]').forEach(c => c.checked = false);
        document.getElementById('filterEspacioSelect').value = '';
        document.querySelector('input[name="filter_status"][value="Todos"]').checked = true;
        document.getElementById('filterFechaDesde').value = '';
        document.getElementById('filterFechaHasta').value = '';
        document.getElementById('filterHoraInicio').value = '08:00';
        document.getElementById('filterHoraFin').value = '20:00';
        document.getElementById('filterCapacidad').value = 5;
        document.getElementById('capacidadSliderLabel').textContent = 'Mínimo: 5 personas';
        document.getElementById('filterSoloMisReservas').checked = false;

        // Reset Inputs Inline Rápidos
        document.getElementById('quickFilterEdificio').value = "";
        document.getElementById('quickFilterTipo').value = "";
        document.getElementById('quickFilterEspacio').value = "";
        document.getElementById('quickFilterStatus').value = "Todos";
        document.getElementById('quickFilterSoloMisReservas').checked = false;

        renderActiveCalendar();
    }

    // ----------------------------------------------------
    // FUNCIÓN DE FILTRADO LOCAL DE EVENTOS Y ESPACIOS
    // ----------------------------------------------------
    function getFilteredEvents() {
        return state.events.filter(ev => {
            // Filtro por búsqueda de texto
            if (state.searchQuery) {
                const sName = ev.nombre_numero.toLowerCase();
                const uName = (ev.usuario_nombre || '').toLowerCase();
                if (!sName.includes(state.searchQuery) && !uName.includes(state.searchQuery)) {
                    return false;
                }
            }

            // Filtro de Edificio
            if (state.filters.edificio.length > 0 && !state.filters.edificio.includes(ev.edificio)) {
                return false;
            }

            // Filtro de Tipo de espacio
            if (state.filters.tipo.length > 0 && !state.filters.tipo.includes(ev.espacio_tipo)) {
                return false;
            }

            // Filtro de Espacio específico
            if (state.filters.esp_id && ev.esp_id != state.filters.esp_id) {
                return false;
            }

            // Filtro de Estatus (Aprobada / Pendiente / Rechazada)
            if (state.filters.status !== 'Todos') {
                const evStatus = ev.estatus || ev.status;
                if (state.filters.status === 'Aprobada' && evStatus !== 'Aprobada' && evStatus !== 'approved') return false;
                if (state.filters.status === 'Pendiente' && evStatus !== 'Pendiente' && evStatus !== 'pending') return false;
            }

            // Filtro de Horario
            if (ev.hora_ent < state.filters.hora_inicio || ev.hora_sal > state.filters.hora_fin) {
                return false;
            }

            // Filtro de Capacidad mínima
            if (ev.espacio_capacidad && ev.espacio_capacidad < state.filters.capacidad) {
                return false;
            }

            // Solo mis reservaciones
            if (state.filters.us_id && ev.us_id != state.filters.us_id) {
                return false;
            }

            return true;
        });
    }

    function getFilteredSpaces() {
        return allSpaces.filter(sp => {
            // Filtro por búsqueda
            if (state.searchQuery) {
                const sName = sp.nombre_numero.toLowerCase();
                if (!sName.includes(state.searchQuery)) return false;
            }

            // Edificio
            if (state.filters.edificio.length > 0 && !state.filters.edificio.includes(sp.edificio)) {
                return false;
            }

            // Tipo
            if (state.filters.tipo.length > 0 && !state.filters.tipo.includes(sp.tipo)) {
                return false;
            }

            // Espacio específico
            if (state.filters.esp_id && sp.esp_id != state.filters.esp_id) {
                return false;
            }

            // Capacidad
            if (sp.capacidad < state.filters.capacidad) {
                return false;
            }

            return true;
        });
    }

    // ----------------------------------------------------
    // VISTA MENSUAL: CÁLCULOS Y RENDER
    // ----------------------------------------------------
    function renderMonthView() {
        const monthBody = document.getElementById('monthGridBody');
        monthBody.innerHTML = '';

        const d = state.currentDate;
        const year = d.getFullYear();
        const month = d.getMonth();

        // Primer día del mes y total de días
        const firstDay = new Date(year, month, 1);
        const startDayIndex = firstDay.getDay(); // 0 (Dom) a 6 (Sáb)
        const totalDays = new Date(year, month + 1, 0).getDate();
        const prevTotalDays = new Date(year, month, 0).getDate();

        const cells = [];

        // Rellenar días del mes anterior
        for (let i = startDayIndex - 1; i >= 0; i--) {
            cells.push({
                date: new Date(year, month - 1, prevTotalDays - i),
                currentMonth: false
            });
        }

        // Rellenar días del mes actual
        for (let i = 1; i <= totalDays; i++) {
            cells.push({
                date: new Date(year, month, i),
                currentMonth: true
            });
        }

        // Rellenar días del mes siguiente para completar la cuadrícula de 6 filas (42 celdas)
        const nextMonthPadding = 42 - cells.length;
        for (let i = 1; i <= nextMonthPadding; i++) {
            cells.push({
                date: new Date(year, month + 1, i),
                currentMonth: false
            });
        }

        const filteredEvents = getFilteredEvents();
        const todayStr = new Date().toISOString().split('T')[0];

        // Crear elementos HTML
        cells.forEach(cell => {
            const cellEl = document.createElement('div');
            cellEl.className = 'month-day-cell';
            if (!cell.currentMonth) cellEl.classList.add('other-month');
            
            const cellDateStr = cell.date.toISOString().split('T')[0];
            if (cellDateStr === todayStr) cellEl.classList.add('today');

            // Número de día
            const numEl = document.createElement('div');
            numEl.className = 'day-number';
            numEl.textContent = cell.date.getDate();
            cellEl.appendChild(numEl);

            // Contenedor de eventos
            const eventsCont = document.createElement('div');
            eventsCont.className = 'month-events-container';

            // Filtrar eventos para este día
            const dayEvents = filteredEvents.filter(ev => ev.fecha_uso === cellDateStr);
            dayEvents.forEach(ev => {
                const evEl = document.createElement('div');
                
                // Formatear color dinámico por espacio (o rojo si está cancelada/rechazada)
                let statClass = getColorForSpace(ev.esp_id);
                const evEst = ev.estatus || ev.status;
                if (evEst === 'Cancelada' || evEst === 'cancelada' || evEst === 'cancelled' || evEst === 'Cancelado' || evEst === 'Rechazada' || evEst === 'rejected' || evEst === 'rechazada') {
                    statClass = 'event-color-red';
                }

                evEl.className = `event-capsule ${statClass}`;
                evEl.textContent = `${ev.hora_ent.substring(0,5)} ${ev.nombre_numero}`;
                
                // Tooltip events
                evEl.addEventListener('mouseenter', (e) => showResTooltip(e, ev));
                evEl.addEventListener('mousemove', (e) => positionResTooltip(e));
                evEl.addEventListener('mouseleave', () => hideResTooltip());
                
                // Click details modal event
                evEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    hideResTooltip();
                    openDetailsModal(ev);
                });

                eventsCont.appendChild(evEl);
            });

            cellEl.appendChild(eventsCont);

            // Al dar click en una celda
            cellEl.addEventListener('click', (e) => {
                if(!e.target.classList.contains('event-capsule')) {
                    openResModal(cellDateStr);
                }
            });

            monthBody.appendChild(cellEl);
        });
    }

    // ----------------------------------------------------
    // VISTA SEMANAL: CÁLCULOS Y RENDER
    // ----------------------------------------------------
    function renderWeekView() {
        const tableHeader = document.getElementById('weekTableHeader');
        const tableBody = document.getElementById('weekTableBody');
        
        // Calcular los días de la semana (Lunes a Domingo)
        const d = state.currentDate;
        const dayOfWeek = d.getDay(); 
        const distanceToMon = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        
        const weekDates = [];
        for (let i = 0; i < 7; i++) {
            const temp = new Date(d);
            temp.setDate(d.getDate() + distanceToMon + i);
            weekDates.push(temp);
        }

        // Render headers
        const diasSemanaNombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        let headerHtml = '<th class="col-space-header">Espacio</th>';
        weekDates.forEach((wDate, idx) => {
            const dayNum = wDate.getDate();
            const monthShort = mesesEsp[wDate.getMonth()].substring(0,3);
            const isToday = wDate.toISOString().split('T')[0] === new Date().toISOString().split('T')[0];
            const activeCircle = isToday ? 'style="background:var(--active-blue); color:white; border-radius:50%; width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center;"' : '';
            
            headerHtml += `<th>
                <div>${diasSemanaNombres[idx]}</div>
                <div style="font-size:14px; font-weight:800; margin-top:4px; color:var(--text-primary);">
                    <span ${activeCircle}>${dayNum}</span>
                </div>
            </th>`;
        });
        tableHeader.innerHTML = headerHtml;

        // Render body
        tableBody.innerHTML = '';
        const filteredSpaces = getFilteredSpaces();
        const filteredEvents = getFilteredEvents();

        if (filteredSpaces.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" style="padding: 48px; text-align: center; color: var(--text-secondary); font-weight: 600;">No hay espacios que coincidan con los filtros.</td></tr>`;
            return;
        }

        filteredSpaces.forEach(sp => {
            const row = document.createElement('tr');
            
            // Columna de espacio
            const spaceTd = document.createElement('td');
            spaceTd.className = 'col-space-info';
            spaceTd.innerHTML = `
                <div class="week-space-title">${sp.nombre_numero}</div>
                <div class="week-space-subtitle">${sp.edificio} · Cap: ${sp.capacidad}</div>
            `;
            row.appendChild(spaceTd);

            // Columnas de días
            weekDates.forEach(wDate => {
                const dayTd = document.createElement('td');
                const dateStr = wDate.toISOString().split('T')[0];

                const cellContainer = document.createElement('div');
                cellContainer.className = 'week-cell-slots-container';

                // Obtener reservaciones para este espacio y este día
                const resEvents = filteredEvents.filter(ev => ev.esp_id == sp.esp_id && ev.fecha_uso === dateStr);
                
                resEvents.forEach(ev => {
                    const evCard = document.createElement('div');
                    
                    // Elegir color dinámico según espacio (o rojo si está cancelada/rechazada)
                    let colorClass = getColorForSpace(sp.esp_id);
                    const evEst = ev.estatus || ev.status;
                    if (evEst === 'Cancelada' || evEst === 'cancelada' || evEst === 'cancelled' || evEst === 'Cancelado' || evEst === 'Rechazada' || evEst === 'rejected' || evEst === 'rechazada') {
                        colorClass = 'event-color-red';
                    }

                    evCard.className = `week-event-card ${colorClass}`;
                    
                    const userName = ev.usuario_nombre || 'Visita';
                    evCard.innerHTML = `
                        <div style="font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${userName}</div>
                        <div class="week-event-time">${ev.hora_ent.substring(0,5)} - ${ev.hora_sal.substring(0,5)}</div>
                    `;
                    
                    // Tooltip events
                    evCard.addEventListener('mouseenter', (e) => showResTooltip(e, ev));
                    evCard.addEventListener('mousemove', (e) => positionResTooltip(e));
                    evCard.addEventListener('mouseleave', () => hideResTooltip());
                    
                    evCard.addEventListener('click', (e) => {
                        e.stopPropagation();
                        hideResTooltip();
                        openDetailsModal(ev);
                    });

                    cellContainer.appendChild(evCard);
                });

                dayTd.appendChild(cellContainer);
                
                // Clic en la celda vacía para crear reservación
                dayTd.addEventListener('click', () => {
                    openResModal(dateStr);
                    // Prefiltar edificio y espacio si aplica
                    document.getElementById('resEdificio').value = sp.edificio;
                    document.getElementById('resEdificio').dispatchEvent(new Event('change'));
                    document.getElementById('resEspacio').value = sp.esp_id;
                    document.getElementById('resEspacio').dispatchEvent(new Event('change'));
                });

                row.appendChild(dayTd);
            });

            tableBody.appendChild(row);
        });
    }

    // ----------------------------------------------------
    // ACTUALIZAR ESTADÍSTICAS LATERALES Y RESUMEN
    // ----------------------------------------------------
    function updateSidebarStats() {
        const filteredEvents = getFilteredEvents();
        const filteredSpaces = getFilteredSpaces();
        
        const d = state.currentDate;
        const dateStr = d.toISOString().split('T')[0];

        // Reservas de hoy (de la fecha actual de navegación)
        const todayEvents = filteredEvents.filter(ev => ev.fecha_uso === dateStr);
        
        // 1. Resumen del Día Contadores
        const totalHoyCount = todayEvents.length;
        const pendientesCount = todayEvents.filter(ev => {
            const est = ev.estatus || ev.status;
            return est === 'Pendiente' || est === 'pending';
        }).length;
        
        // Espacios Libres (total espacios menos los que tienen al menos una reserva aprobada hoy)
        const occupiedSpaceIds = todayEvents.filter(ev => {
            const est = ev.estatus || ev.status;
            return est === 'Aprobada' || est === 'approved';
        }).map(ev => ev.esp_id);
        const uniqueOccupied = [...new Set(occupiedSpaceIds)];
        const libresCount = Math.max(0, filteredSpaces.length - uniqueOccupied.length);

        if (document.getElementById('statReservasHoy')) document.getElementById('statReservasHoy').textContent = totalHoyCount;
        if (document.getElementById('statDisponibles')) document.getElementById('statDisponibles').textContent = libresCount;
        if (document.getElementById('statPendientes')) document.getElementById('statPendientes').textContent = pendientesCount;

        // 2. Próximas Reservaciones Sidebar — Semana completa (7 días), ordenadas cronológicamente
        const upcomingList = document.getElementById('upcomingReservationsList');
        if (upcomingList) {
            upcomingList.innerHTML = '';

            const nowDate = new Date();
            const tzOffset = nowDate.getTimezoneOffset() * 60000;
            const todayStrLocal = (new Date(nowDate.getTime() - tzOffset)).toISOString().slice(0, 10);
            const sevenDaysLater = new Date(nowDate.getTime() - tzOffset + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

            const currentTimeStr = nowDate.toTimeString().substring(0, 5); // "HH:MM"

            // Filtrar eventos de los próximos 7 días (incluyendo hoy)
            const weekEvents = filteredEvents
                .filter(ev => {
                    if (ev.fecha_uso < todayStrLocal || ev.fecha_uso > sevenDaysLater) return false;
                    
                    const est = (ev.estatus || ev.status || '').toLowerCase();
                    if (est === 'cancelada' || est === 'cancelado' || est === 'cancelled' || est === 'rechazada' || est === 'rechazado' || est === 'rejected') return false;

                    // Ocultar si ya pasó la hora de salida hoy
                    if (ev.fecha_uso === todayStrLocal) {
                        const hSal = (ev.hora_sal || '').substring(0, 5);
                        if (hSal && hSal < currentTimeStr) return false;
                    }
                    
                    return true;
                })
                .sort((a, b) => {
                    if (a.fecha_uso !== b.fecha_uso) return a.fecha_uso.localeCompare(b.fecha_uso);
                    return (a.hora_ent || '').localeCompare(b.hora_ent || '');
                });
            
            if (weekEvents.length === 0) {
                upcomingList.innerHTML = '<div style="font-size:12px; color:var(--text-secondary); font-style:italic; text-align:center; padding: 12px 0;">Sin reservaciones programadas para los próximos 7 días.</div>';
            } else {
                const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                const mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

                weekEvents.forEach(ev => {
                    const item = document.createElement('div');
                    item.className = 'upcoming-res-item';
                    item.style.cursor = 'pointer';
                    
                    let iconClass = 'icon-blue';
                    let iconType = 'bi-journal-check';
                    if(ev.espacio_tipo === 'Laboratorio') { iconClass = 'icon-orange'; iconType = 'bi-laptop'; }
                    if(ev.espacio_tipo === 'Auditorio') { iconClass = 'icon-green'; iconType = 'bi-megaphone'; }

                    let badgeText = 'Confirmada';
                    let badgeClass = 'badge-confirmada';
                    const est = ev.estatus || ev.status;
                    if(est === 'Pendiente' || est === 'pending') { badgeText = 'Pendiente'; badgeClass = 'badge-pendiente'; }
                    if(est === 'Rechazada' || est === 'rejected' || est === 'rechazada') { badgeText = 'Rechazada'; badgeClass = 'badge-rechazada'; }
                    if(est === 'Cancelada' || est === 'cancelada' || est === 'cancelled' || est === 'Cancelado') { badgeText = 'Cancelada'; badgeClass = 'badge-cancelada'; }

                    // Formatear fecha legible
                    let fechaLabel = ev.fecha_uso;
                    try {
                        const parts = ev.fecha_uso.split('-');
                        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                        fechaLabel = `${diasSemana[d.getDay()]} ${d.getDate()} ${mesesCortos[d.getMonth()]}`;
                        if (ev.fecha_uso === todayStrLocal) fechaLabel = 'Hoy';
                    } catch(err) {}

                    item.innerHTML = `
                        <div class="res-item-icon ${iconClass}"><i class="bi ${iconType}"></i></div>
                        <div class="res-item-info">
                            <div class="res-item-name">${ev.nombre_numero}</div>
                            <div class="res-item-time" style="color:var(--active-blue);font-weight:700;">${fechaLabel}</div>
                            <div class="res-item-time">${(ev.hora_ent||'').substring(0,5)} - ${(ev.hora_sal||'').substring(0,5)}</div>
                        </div>
                        <span class="status-badge ${badgeClass}">${badgeText}</span>
                    `;
                    item.addEventListener('click', () => openDetailsModal(ev));
                    upcomingList.appendChild(item);
                });
            }
        }

        // 3. Espacios Disponibles Sidebar (Top 5 listado)
        const spacesList = document.getElementById('availableSpacesList');
        if (spacesList) {
            spacesList.innerHTML = '';

            const topSpaces = filteredSpaces.slice(0, 5);
            if (topSpaces.length === 0) {
                spacesList.innerHTML = '<div style="font-size:12px; color:var(--text-secondary); font-style:italic; text-align:center; padding: 12px 0;">No hay espacios registrados.</div>';
            } else {
                topSpaces.forEach(sp => {
                    const isOccupiedToday = uniqueOccupied.includes(sp.esp_id);
                    const stateText = isOccupiedToday ? 'Ocupado hoy' : 'Disponible';
                    const stateClass = isOccupiedToday ? 'state-ocupado' : 'state-libre';
                    const stateIcon = isOccupiedToday ? 'bi-lock' : 'bi-check-circle';

                    const sEl = document.createElement('div');
                    sEl.className = 'space-status-item';
                    sEl.innerHTML = `
                        <div class="space-status-left">
                            <i class="bi ${stateIcon} space-status-icon"></i>
                            <span class="space-status-name">${sp.nombre_numero}</span>
                        </div>
                        <span class="space-status-state ${stateClass}">${stateText}</span>
                    `;
                    
                    sEl.addEventListener('click', () => {
                        openResModal(dateStr);
                        document.getElementById('resEdificio').value = sp.edificio;
                        document.getElementById('resEdificio').dispatchEvent(new Event('change'));
                        document.getElementById('resEspacio').value = sp.esp_id;
                        document.getElementById('resEspacio').dispatchEvent(new Event('change'));
                    });

                    spacesList.appendChild(sEl);
                });
            }
        }
    }
    // ENVIAR SOLICITUD DE RESERVACIÓN (DÍA ÚNICO O RECURRENTE)
    // ----------------------------------------------------
    function submitReservation() {
        const espId = document.getElementById('resEspacio').value;
        const horaEnt = document.getElementById('resHoraEnt').value;
        const horaSal = document.getElementById('resHoraSal').value;
        const numAlumnos = parseInt(document.getElementById('resNumAlumnos').value);
        const motivo = document.getElementById('resMotivo').value;

        if (state.resMode === 'single') {
            if (espId && typeof isSpaceFullyOccupied === 'function' && isSpaceFullyOccupied(espId)) {
                Swal.fire('Atención', 'Este espacio ya está completamente ocupado por todo el día.', 'error');
                return;
            }
        }

        if (!espId || !horaEnt || !horaSal) {
            Swal.fire('Atención', 'Por favor, complete todos los campos obligatorios.', 'warning');
            return;
        }

        // ── VALIDACIÓN DE HORA FIN POSTERIOR A HORA INICIO ──
        const entHour = parseInt(horaEnt.split(':')[0]);
        const salHour = parseInt(horaSal.split(':')[0]);
        if (salHour <= entHour) {
            Swal.fire('Atención', 'La hora de fin debe ser posterior a la hora de inicio.', 'warning');
            return;
        }

        // ── VALIDACIÓN DE CAPACIDAD ANTES DE ENVIAR ──
        if (espId && espId !== 'SALA_MAGNA_MODULAR') {
            const spCheck = allSpaces.find(sp => sp.esp_id === parseInt(espId));
            if (spCheck) {
                const capMax = parseInt(spCheck.capacidad || 0);
                if (capMax > 0 && numAlumnos > capMax) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Capacidad excedida',
                        html: `El número de asistentes (<strong>${numAlumnos}</strong>) supera la capacidad máxima del espacio seleccionado.<br><br>Este espacio admite un máximo de <strong>${capMax} personas</strong>.<br><br>Por favor, reduce el número de asistentes o selecciona un espacio más grande.`,
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: 'Entendido'
                    });
                    // Resaltar el campo
                    const numInput = document.getElementById('resNumAlumnos');
                    if (numInput) {
                        numInput.style.borderColor = '#ef4444';
                        numInput.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.15)';
                        numInput.focus();
                    }
                    return;
                }
            }
        }

        const now = new Date();
        const currentHour = now.getHours();
        const selectedHour = parseInt(horaEnt.split(':')[0]);
        const tzOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = (new Date(now.getTime() - tzOffset)).toISOString().slice(0, 10);

        const userVisId = <?php echo json_encode($_SESSION['vis_id'] ?? null); ?>;
        const requestData = {
            esp_id: espId === 'SALA_MAGNA_MODULAR' ? 'SALA_MAGNA_MODULAR' : parseInt(espId),
            is_sala_magna_modular: espId === 'SALA_MAGNA_MODULAR',
            is_cuatrimestre: state.resMode === 'multiple',
            hora_ent: `${horaEnt}:00`,
            hora_sal: `${horaSal}:00`,
            num_alumnos: numAlumnos,
            motivo: motivo,
            vis_id: userVisId || null
        };

        requestData.is_cuatrimestre = (state.resMode === 'cuatrimestre');

        // Procesar por modo: Día Único vs. Múltiples Días vs. Por Cuatrimestre
        if (state.resMode === 'single') {
            const fecha = document.getElementById('resFecha').value;
            if(!fecha) {
                Swal.fire('Atención', 'Por favor, selecciona una fecha.', 'warning');
                return;
            }
            if (fecha === localISOTime && selectedHour <= currentHour) {
                Swal.fire('Atención', 'No puedes reservar en una hora que ya pasó el día de hoy.', 'warning');
                return;
            }
            requestData.fecha_uso = fecha;
        } else if (state.resMode === 'multiple') {
            // Múltiples días: todas las fechas entre Inicio y Fin consecutivas
            const startStr = document.getElementById('resFechaInicio').value;
            const endStr = document.getElementById('resFechaFin').value;
            if(!startStr || !endStr) {
                Swal.fire('Atención', 'Por favor, selecciona el rango de fechas.', 'warning');
                return;
            }
            if (startStr === localISOTime && selectedHour <= currentHour) {
                 Swal.fire('Atención', 'No puedes reservar en una hora que ya pasó para el día de hoy (fecha de inicio).', 'warning');
                 return;
            }

            const startDate = new Date(startStr + 'T00:00:00');
            const endDate = new Date(endStr + 'T00:00:00');
            if (endDate < startDate) {
                Swal.fire('Atención', 'La fecha de fin no puede ser menor que la de inicio.', 'warning');
                return;
            }

            const fechas = [];
            let curr = new Date(startDate);
            while (curr <= endDate) {
                const y = curr.getFullYear();
                const m = String(curr.getMonth() + 1).padStart(2, '0');
                const d = String(curr.getDate()).padStart(2, '0');
                fechas.push(`${y}-${m}-${d}`);
                curr.setDate(curr.getDate() + 1);
            }
            
            if (fechas.length === 0) {
                Swal.fire('Atención', 'No hay fechas válidas en el rango seleccionado.', 'warning');
                return;
            }

            requestData.fechas_uso = fechas;
        } else if (state.resMode === 'cuatrimestre') {
            // Por cuatrimestre: fechas obtenidas a partir de los inputs resCuatFechaInicio y resCuatFechaFin
            const startStr = document.getElementById('resCuatFechaInicio').value;
            const endStr = document.getElementById('resCuatFechaFin').value;
            if (!startStr || !endStr) {
                Swal.fire('Atención', 'Por favor, selecciona las fechas de inicio y fin del cuatrimestre.', 'warning');
                return;
            }
            if (startStr === localISOTime && selectedHour <= currentHour) {
                 Swal.fire('Atención', 'No puedes reservar en una hora que ya pasó para el día de hoy (fecha de inicio).', 'warning');
                 return;
            }

            const startDate = new Date(startStr + 'T00:00:00');
            const endDate = new Date(endStr + 'T00:00:00');
            if (endDate < startDate) {
                Swal.fire('Atención', 'La fecha de fin no puede ser menor que la de inicio.', 'warning');
                return;
            }

            const fechas = [];
            let curr = new Date(startDate);
            while (curr <= endDate) {
                const y = curr.getFullYear();
                const m = String(curr.getMonth() + 1).padStart(2, '0');
                const d = String(curr.getDate()).padStart(2, '0');
                fechas.push(`${y}-${m}-${d}`);
                curr.setDate(curr.getDate() + 1);
            }

            if (fechas.length === 0) {
                Swal.fire('Atención', 'No hay fechas válidas en el cuatrimestre seleccionado.', 'warning');
                return;
            }

            requestData.fechas_uso = fechas;
        }

        // Obtener equipamientos seleccionados
        const eqCheckboxes = document.querySelectorAll('.equipamiento-checkbox:checked');
        if (eqCheckboxes.length > 0) {
            requestData.equipamiento_ids = Array.from(eqCheckboxes).map(cb => parseInt(cb.value));
        }

        const btnConfirm = document.getElementById('btnConfirmReserva');
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';

        const performFetch = (dataToSubmit) => {
            fetch('../backend/api/index.php/reservations', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataToSubmit)
            })
            .then(async res => {
                const status = res.status;
                const data = await res.json().catch(() => ({}));
                
                if (status === 409 && data.conflicts) {
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Fechas Ocupadas',
                        text: `Los siguientes días ya están ocupados: ${data.conflicts.join(', ')}. ¿Deseas reservar de todos modos omitiendo estos días?`,
                        showCancelButton: true,
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Sí, omitir ocupados',
                        cancelButtonText: 'Cancelar reserva'
                    });
                    
                    if (result.isConfirmed) {
                        dataToSubmit.skip_conflicts = true;
                        performFetch(dataToSubmit);
                    } else {
                        btnConfirm.disabled = false;
                        btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
                    }
                } else if (data.success || data.id || data.ids) {
                    const spObj = allSpaces.find(sp => sp.esp_id == dataToSubmit.esp_id);
                    const rules = getSpaceRules(spObj);

                    Swal.fire({
                        icon: 'success',
                        title: '¡Reservación Solicitada!',
                        text: rules.mensaje_toast_exito,
                        confirmButtonColor: rules.color_tema || '#10b981'
                    });
                    window.closeResModal();
                    window.fetchEvents();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al agendar reserva',
                        text: data.error || 'Conflicto de horario o espacio no disponible.',
                        confirmButtonColor: '#ef4444'
                    });
                    btnConfirm.disabled = false;
                    btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
                }
            })
            .catch(err => {
                console.error("Error submitting reservation:", err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'Ocurrió un error al procesar la reservación: ' + err.message,
                    confirmButtonColor: '#ef4444'
                });
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
            });
        };
        
        performFetch(requestData);
    }

    // ----------------------------------------------------
    // DETALLES Y TOOLTIP DE RESERVACIONES (DIT)
    // ----------------------------------------------------
    window.openDetailsModal = function(ev) {
        document.getElementById('detEspacioNombre').textContent = ev.nombre_numero;
        document.getElementById('detEdificioTipo').textContent = `${ev.edificio} · ${ev.espacio_tipo || 'Espacio'}`;
        
        const est = ev.estatus || ev.status || 'Pendiente';
        const badge = document.getElementById('detEstatusBadge');
        badge.textContent = est;
        badge.className = 'status-badge';
        
        if (est === 'Aprobada' || est === 'approved' || est === 'Aprobado') {
            badge.style.background = '#dcfce7';
            badge.style.color = '#15803d';
        } else if (est === 'Pendiente' || est === 'pending') {
            badge.style.background = '#fef3c7';
            badge.style.color = '#b45309';
        } else if (est === 'Cancelada' || est === 'cancelada' || est === 'cancelled' || est === 'Cancelado') {
            badge.style.background = '#fee2e2';
            badge.style.color = '#ef4444';
        } else {
            badge.style.background = '#fee2e2';
            badge.style.color = '#ef4444';
        }
        
        // Formatear fecha
        try {
            const dateParts = ev.fecha_uso.split('-');
            const dateObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('es-ES', options);
            document.getElementById('detFecha').textContent = formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
        } catch (err) {
            document.getElementById('detFecha').textContent = ev.fecha_uso;
        }
        
        // Formatear horario
        const horaEnt = ev.hora_ent ? ev.hora_ent.substring(0, 5) : '00:00';
        const horaSal = ev.hora_sal ? ev.hora_sal.substring(0, 5) : '00:00';
        document.getElementById('detHorario').textContent = `${horaEnt} - ${horaSal}`;
        
        // Solicitante
        document.getElementById('detSolicitante').textContent = ev.usuario_nombre || 'Visita / Externo';
        document.getElementById('detCorreo').textContent = ev.usuario_correo || 'No disponible';
        document.getElementById('detAsistentes').textContent = `${ev.num_alumnos || 0} alumnos`;
        
        document.getElementById('resDetailsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.showResTooltip = function(e, ev) {
        const tooltip = document.getElementById('calendarTooltip');
        if (!tooltip) return;
        
        const est = ev.estatus || ev.status || 'Pendiente';
        let statusColor = '#10b981';
        if (est === 'Pendiente' || est === 'pending') statusColor = '#f59e0b';
        if (est === 'Rechazada' || est === 'rejected' || est === 'rechazada') statusColor = '#ef4444';
        if (est === 'Cancelada' || est === 'cancelada' || est === 'cancelled' || est === 'Cancelado') statusColor = '#ef4444';
        
        const horaEnt = ev.hora_ent ? ev.hora_ent.substring(0, 5) : '00:00';
        const horaSal = ev.hora_sal ? ev.hora_sal.substring(0, 5) : '00:00';
        
        let fechaFormateada = ev.fecha_uso;
        try {
            const dateParts = ev.fecha_uso.split('-');
            const dateObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const fDate = dateObj.toLocaleDateString('es-ES', options);
            fechaFormateada = fDate.charAt(0).toUpperCase() + fDate.slice(1);
        } catch(e) {}
        
        tooltip.innerHTML = `
            <div style="font-weight: 800; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <span>${ev.nombre_numero} (${ev.edificio})</span>
                <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: ${statusColor}; color: white; text-transform: uppercase;">${est}</span>
            </div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-calendar-event" style="margin-right: 6px;"></i><strong>Fecha:</strong> ${fechaFormateada}</div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-clock" style="margin-right: 6px;"></i><strong>Horario:</strong> ${horaEnt} - ${horaSal}</div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-person" style="margin-right: 6px;"></i><strong>Solicitante:</strong> ${ev.usuario_nombre || 'Visita'}</div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-envelope" style="margin-right: 6px;"></i><strong>Correo:</strong> ${ev.usuario_correo || 'N/A'}</div>
            <div style="color: #cbd5e1;"><i class="bi bi-people" style="margin-right: 6px;"></i><strong>Asistentes:</strong> ${ev.num_alumnos || 0}</div>
        `;
        tooltip.style.display = 'block';
        window.positionResTooltip(e);
    };

    window.positionResTooltip = function(e) {
        const tooltip = document.getElementById('calendarTooltip');
        if (!tooltip || tooltip.style.display === 'none') return;
        
        const tooltipWidth = tooltip.offsetWidth;
        const tooltipHeight = tooltip.offsetHeight;
        
        let x = e.clientX + 15;
        let y = e.clientY + 15;
        
        if (x + tooltipWidth > window.innerWidth) {
            x = e.clientX - tooltipWidth - 15;
        }
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    };

    window.hideResTooltip = function() {
        const tooltip = document.getElementById('calendarTooltip');
        if (tooltip) tooltip.style.display = 'none';
    };

    // ============================================================================
    // MAPA INTERACTIVO INTEGRADO EN EL MODAL DE RESERVAS
    // ============================================================================

    let MAP_DATA = {};
    let modalCurrentMapKey = 'PIDET_alta';
    let modalSelectedPolygon = null;

    // Estado del Pan y Zoom
    let modalZoom = 1;
    let modalPanX = 0;
    let modalPanY = 0;
    let modalIsDragging = false;
    let modalStartX = 0;
    let modalStartY = 0;

    // Inicializar Motor
    // IMPORTANTE: Siempre recarga el JSON fresco desde el servidor para que el Editor de Mapas
    // sea la única fuente de verdad. El cache-buster garantiza que los cambios guardados
    // en el editor se reflejen de inmediato sin necesidad de Ctrl+F5.
    function initModalMap() {
        const cacheBuster = Date.now();
        fetch('assets/map_data.json?v=' + cacheBuster)
            .then(r => r.json())
            .then(data => {
                MAP_DATA = data;
                updateModalMapImage();
            })
            .catch(e => console.error('Error cargando map_data.json:', e));
    }

    function updateModalMapImage() {
        const edif = document.getElementById('resEdificio').value || 'PIDET';
        const planta = document.getElementById('resPlanta').value || 'alta';
        modalCurrentMapKey = `${edif}_${planta}`;

        const config = MAP_DATA[modalCurrentMapKey];
        if (!config) return;

        modalDeselectZone();
        const img = document.getElementById('modalMapImage');
        img.src = config.image;
        
        // Reset pan & zoom al cambiar de mapa
        modalMapZoomReset();
    }

    // Callbacks del modal selectores
    document.getElementById('resEdificio').addEventListener('change', () => {
        updateModalMapImage();
    });
    
    document.getElementById('resPlanta').addEventListener('change', () => {
        updateModalMapImage();
        // Borrar selección de espacio si cambió de planta y no fue un cambio programático
        if (!isProgrammaticMapChange) {
            document.getElementById('resEspacio').value = "";
            document.getElementById('resEspacio').dispatchEvent(new Event('change'));
        }
    });

    window.onModalMapImageLoad = function() {
        const img = document.getElementById('modalMapImage');
        if (!img.naturalWidth) return;

        const svg = document.getElementById('modalMapOverlay');
        svg.setAttribute('viewBox', `0 0 ${img.naturalWidth} ${img.naturalHeight}`);
        renderModalMap();

        // Ejecutar Fit to View inicial
        if (typeof window.calculateFitToView === 'function') {
            window.calculateFitToView();
        }
    };

    function renderModalMap() {
        const svg = document.getElementById('modalMapOverlay');
        svg.innerHTML = '';
        const config = MAP_DATA[modalCurrentMapKey];
        if (!config || !config.zones) return;

        config.zones.forEach(zone => {
            // Pasar esp_id cuando esté disponible en el JSON (prioritario)
            const spaceData = findSpaceInDB(zone.db_name, config.edificio, zone.esp_id);
            let estatus = spaceData ? spaceData.estatus : 'Disponible';
            if (spaceData && typeof isSpaceFullyOccupied === 'function' && isSpaceFullyOccupied(spaceData.esp_id)) {
                estatus = 'Ocupado';
            }
            
            // Etiqueta viene EXCLUSIVAMENTE de la BD. Si no hay match, mostrar db_name como fallback.
            const label = spaceData ? spaceData.nombre_numero : (zone.db_name || 'Sin asignar');

            // ─────────────────────────────────────────────────────
            // es_reservable: PostgreSQL puede enviar true/false/"t"/"f"/1/0
            // Usamos comparación explícita contra valores falsy
            // ─────────────────────────────────────────────────────
            let esReservable = true;
            if (spaceData) {
                const raw = spaceData.es_reservable;
                // false / 'f' / 0 / 'false' / '' / null → NO reservable
                esReservable = !(raw === false || raw === 'f' || raw === 0 || raw === 'false' || raw === null || raw === '');
            }

            const acceso = spaceData ? spaceData.acceso : 'general';
            const baseStyle = getBaseStyle(estatus, esReservable, acceso, zone.db_name);
            const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            poly.setAttribute('points', zone.points);
            poly.dataset.dbname = zone.db_name;
            poly.dataset.espid = spaceData ? spaceData.esp_id : '';
            poly.dataset.estatus = estatus;
            poly.dataset.reservable = esReservable;
            poly.dataset.acceso = acceso;
            
            poly.setAttribute('fill', baseStyle.fill);
            poly.setAttribute('stroke', baseStyle.stroke);
            poly.setAttribute('stroke-width', '3');
            poly.setAttribute('stroke-opacity', '0.4');
            poly.style.pointerEvents = 'all';
            poly.style.cursor = esReservable ? 'pointer' : 'not-allowed';
            poly.style.transition = 'all 0.15s ease';

            // Tooltip events
            poly.addEventListener('mouseenter', (e) => {
                if (modalIsDragging) return;
                if (poly !== modalSelectedPolygon) {
                    poly.setAttribute('stroke-width', '4');
                    poly.setAttribute('stroke-opacity', '0.9');
                    poly.setAttribute('fill', baseStyle.hoverFill);
                }
                showModalTooltip(e, label, spaceData, estatus, esReservable);
            });

            poly.addEventListener('mousemove', (e) => {
                if (modalIsDragging) { hideModalTooltip(); return; }
                moveModalTooltip(e);
            });

            poly.addEventListener('mouseleave', () => {
                if (poly !== modalSelectedPolygon) {
                    poly.setAttribute('stroke-width', '3');
                    poly.setAttribute('stroke-opacity', '0.4');
                    poly.setAttribute('fill', baseStyle.fill);
                }
                hideModalTooltip();
            });

            // Click event (seleccionar)
            poly.addEventListener('click', (e) => {
                if (modalIsDragging) return; // evitar click accidental al arrastrar
                e.stopPropagation();
                if (!esReservable) return;
                modalSelectZone(poly, zone, spaceData);
            });

            svg.appendChild(poly);
        });
        
        // Renderizar textos libres
        if (config.texts) {
            config.texts.forEach(t => {
                const textEl = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                textEl.setAttribute('x', t.x);
                textEl.setAttribute('y', t.y);
                textEl.textContent = t.content;
                textEl.setAttribute('font-size', t.size || 16);
                textEl.setAttribute('font-family', 'sans-serif');
                textEl.setAttribute('font-weight', 'bold');
                textEl.setAttribute('fill', '#1e293b');
                textEl.setAttribute('paint-order', 'stroke fill');
                textEl.setAttribute('stroke', 'rgba(255, 255, 255, 0.9)');
                textEl.setAttribute('stroke-width', '4px');
                textEl.setAttribute('text-anchor', 'middle');
                textEl.setAttribute('dominant-baseline', 'middle');
                textEl.style.pointerEvents = 'none'; // Para que no bloquee los clics en los polígonos
                textEl.style.userSelect = 'none';
                svg.appendChild(textEl);
            });
        }

        // Sincronizar selección si ya había un espacio seleccionado en el form
        syncMapFromForm();
    }

    function modalSelectZone(poly, zone, spaceData) {
        if (modalSelectedPolygon && modalSelectedPolygon !== poly) {
            resetPolygonStyle(modalSelectedPolygon);
        }

        modalSelectedPolygon = poly;

        // Estilo seleccionado MUCHO más evidente (Azul fuerte, borde grueso)
        poly.setAttribute('fill', 'rgba(37, 99, 235, 0.4)');
        poly.setAttribute('stroke', '#1d4ed8');
        poly.setAttribute('stroke-opacity', '1');
        poly.setAttribute('stroke-width', '6');

        // Auto-centrar en el salón seleccionado
        centerMapOnPolygon(poly);

        // Disparar toast contextual según reglas de negocio
        if (spaceData && typeof Swal !== 'undefined') {
            const rules = getSpaceRules(spaceData);
            // (Toast eliminado por solicitud del usuario para limpiar la interfaz)
            
            // Si es no reservable, resetear el select pero dejar el polígono seleccionado visualmente
            if (!rules.es_reservable) {
                const resEspacio = document.getElementById('resEspacio');
                if (resEspacio) {
                    resEspacio.value = '';
                    resEspacio.dispatchEvent(new Event('change'));
                }
                return; // Cortar el flujo hacia el formulario si no es reservable
            }
        }

        // Sincronizar hacia el formulario
        if (spaceData) {
            const resEdificio = document.getElementById('resEdificio');
            if (resEdificio && resEdificio.value !== spaceData.edificio) {
                resEdificio.value = spaceData.edificio;
                resEdificio.dispatchEvent(new Event('change')); // Popula el resEspacio sincrónicamente
            }

            const resEspacio = document.getElementById('resEspacio');
            if (resEspacio) {
                resEspacio.value = spaceData.esp_id;
                resEspacio.dispatchEvent(new Event('change'));
            }
        }
    }

    function modalDeselectZone() {
        if (modalSelectedPolygon) {
            resetPolygonStyle(modalSelectedPolygon);
            modalSelectedPolygon = null;
        }
    }

    function resetPolygonStyle(poly) {
        const estatus = poly.dataset.estatus || 'Disponible';
        const res = poly.dataset.reservable === 'true';
        const acceso = poly.dataset.acceso || 'general';
        const dbname = poly.dataset.dbname || '';
        const style = getBaseStyle(estatus, res, acceso, dbname);
        poly.setAttribute('fill', style.fill);
        poly.setAttribute('stroke', style.stroke);
        poly.setAttribute('stroke-opacity', '0.4');
        poly.setAttribute('stroke-width', '3');
    }

    function getBaseStyle(estatus, isReservable, acceso, dbName) {
        if (!isReservable) {
            // Privado (Gris Oscuro #374151)
            return { 
                fill: 'rgba(55, 65, 81, 0.35)', 
                stroke: '#374151',
                hoverFill: 'rgba(55, 65, 81, 0.55)'
            };
        }
        
        if (estatus === 'Ocupado') {
            // Ocupado (Rojo #EF4444)
            return { 
                fill: 'rgba(239, 68, 68, 0.35)', 
                stroke: '#EF4444',
                hoverFill: 'rgba(239, 68, 68, 0.55)'
            };
        }
        
        // Reserva especial / Visita (Morado #8B5CF6)
        if (dbName === 'CEPRODI' || acceso === 'visita') {
            return { 
                fill: 'rgba(139, 92, 246, 0.35)',  
                stroke: '#8B5CF6',
                hoverFill: 'rgba(139, 92, 246, 0.55)'
            };
        }
        
        // Requiere autorización (Naranja #F59E0B)
        if (acceso === 'restringido' || acceso === 'administrador') {
            return { 
                fill: 'rgba(245, 158, 11, 0.35)',  
                stroke: '#F59E0B',
                hoverFill: 'rgba(245, 158, 11, 0.55)'
            };
        }
        
        // Libre (Verde #22C55E)
        return { 
            fill: 'rgba(34, 197, 94, 0.35)',  
            stroke: '#22C55E',
            hoverFill: 'rgba(34, 197, 94, 0.55)'
        };
    }

    function findSpaceInDB(dbName, edificio, espId) {
        if (!allSpaces) return null;

        // 1. Buscar por esp_id (100% confiable, no depende del nombre)
        if (espId) {
            const byId = allSpaces.find(sp => sp.esp_id == espId);
            if (byId) return byId;
        }

        // 2. Fallback: exact match por nombre dentro del mismo edificio
        if (!dbName) return null;
        const needle = dbName.toLowerCase().trim();
        const exact = allSpaces.find(sp => {
            if (sp.edificio !== edificio) return false;
            return sp.nombre_numero.toLowerCase().trim() === needle;
        });
        if (exact) return exact;

        // 3. Fallback parcial (hay contiene needle)
        return allSpaces.find(sp => {
            if (sp.edificio !== edificio) return false;
            return sp.nombre_numero.toLowerCase().trim().includes(needle);
        }) || null;
    }

    // Sincronización Form -> Map
    function syncMapFromForm() {
        const espId = document.getElementById('resEspacio').value;
        if (!espId) {
            modalDeselectZone();
            return;
        }
        const svg = document.getElementById('modalMapOverlay');
        const polys = svg.querySelectorAll('polygon');
        let found = false;
        polys.forEach(p => {
            if (p.dataset.espid == espId || (espId === 'SALA_MAGNA_MODULAR' && p.dataset.dbname.startsWith('Sala Magna'))) {
                modalSelectZone(p, null, null); // spaceData not strictly needed here for styling
                found = true;
            }
        });
        if(!found) modalDeselectZone();
    }


    // Tooltip
    const tooltip = document.getElementById('modalMapTooltip');
    function showModalTooltip(e, label, data, estatus, isReservable) {
        const rules = getSpaceRules(data);
        
        let estatusColor = '#10b981'; // default verde
        let estatusBadge = 'Disponible';
        if (estatus === 'Ocupado') {
            estatusColor = '#ef4444';
            estatusBadge = 'Ocupado';
        } else if (!rules.es_reservable) {
            estatusColor = '#64748b';
            estatusBadge = 'No Disponible';
        }

        const capacidadHtml = data && data.capacidad > 0 ? 
            `<div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span style="color:#64748b;">Capacidad:</span>
                <span style="font-weight:600; color:#0f172a;">${data.capacidad} pers.</span>
            </div>` : '';

        tooltip.innerHTML = `
            <div style="min-width: 200px;">
                <div style="font-weight:700; font-size:14px; margin-bottom:8px; border-bottom:1px solid #e2e8f0; padding-bottom:6px; color:#0f172a;">
                    ${label}
                </div>
                <div style="font-size:12px; margin-bottom:8px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="color:#64748b;">Tipo:</span>
                        <span style="font-weight:600; color:#0f172a;">${data ? data.tipo : 'Espacio'}</span>
                    </div>
                    ${capacidadHtml}
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="color:#64748b;">Estado:</span>
                        <span style="font-weight:600; color:${estatusColor};">${estatusBadge}</span>
                    </div>
                </div>
                <div style="background:${rules.color_tema}15; color:${rules.color_tema}; padding:8px; border-radius:6px; font-size:11px; font-weight:600; display:flex; gap:6px; align-items:start;">
                    <i class="bi ${rules.icono}" style="margin-top:1px;"></i>
                    <span style="line-height:1.3;">${rules.mensaje_tooltip}</span>
                </div>
            </div>
        `;
        
        // Estilos base para el tooltip container (asegurar que se vea elegante)
        tooltip.style.padding = '12px';
        tooltip.style.borderRadius = '10px';
        tooltip.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
        tooltip.style.border = '1px solid rgba(0,0,0,0.05)';
        tooltip.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
        tooltip.style.backdropFilter = 'blur(8px)';
        tooltip.style.display = 'block';

        moveModalTooltip(e);
    }
    function moveModalTooltip(e) {
        if(tooltip.style.display === 'none') return;
        const tw = tooltip.offsetWidth  || 220;
        const th = tooltip.offsetHeight || 150;
        const margin = 12;

        // Use modal container bounds as clipping boundary
        const mapModal = document.querySelector('.res-modal-overlay') || document.body;
        const bounds = mapModal.getBoundingClientRect();
        const maxX = bounds.right  || window.innerWidth;
        const maxY = bounds.bottom || window.innerHeight;
        const minX = bounds.left   || 0;

        let x = e.clientX + 15;
        let y = e.clientY + 15;

        // Flip to left if tooltip overflows right edge
        if (x + tw + margin > maxX) x = e.clientX - tw - 15;
        // Clamp to minimum left bound
        if (x < minX + margin) x = minX + margin;
        // Flip to above cursor if tooltip overflows bottom
        if (y + th + margin > maxY) y = e.clientY - th - 15;
        // Clamp to top
        if (y < margin) y = margin;

        tooltip.style.left = x + 'px';
        tooltip.style.top  = y + 'px';
    }
    function hideModalTooltip() {
        tooltip.style.display = 'none';
    }

    // Buscador local en el mapa con auto-foco
    window.modalHighlightMapSpace = function(query) {
        const polys = document.getElementById('modalMapOverlay').querySelectorAll('polygon');
        const q = query.toLowerCase().trim();
        let exactMatch = null;
        let partialMatches = [];

        polys.forEach(p => {
            const dbname = (p.dataset.dbname || '').toLowerCase();
            if (!q) {
                p.style.opacity = '1';
                if (p !== modalSelectedPolygon) { resetPolygonStyle(p); }
            } else if (dbname.includes(q)) {
                p.style.opacity = '1';
                if(p !== modalSelectedPolygon) {
                    p.setAttribute('stroke','#2563eb'); 
                    p.setAttribute('stroke-opacity','0.8');
                    p.setAttribute('stroke-width','4');
                }
                if (dbname === q) exactMatch = p;
                partialMatches.push(p);
            } else {
                p.style.opacity = '0.2';
                if (p !== modalSelectedPolygon) { resetPolygonStyle(p); }
            }
        });

        if (q && exactMatch) {
            centerMapOnPolygon(exactMatch);
        } else if (q && partialMatches.length === 1) {
            centerMapOnPolygon(partialMatches[0]);
        }
    };

    // Zoom & Pan
    const mapViewport = document.getElementById('modalMapViewport');
    const mapInner = document.getElementById('modalMapInner');

    function applyTransform(withTransition = false) {
        if (!mapInner) return;
        if (withTransition) {
            mapInner.style.transition = 'transform 0.35s ease-in-out';
        } else {
            mapInner.style.transition = 'none';
        }
        mapInner.style.transform = `translate(${modalPanX}px, ${modalPanY}px) scale(${modalZoom})`;
        
        // Remove transition after it's done so drag remains responsive
        if (withTransition) {
            setTimeout(() => { mapInner.style.transition = 'none'; }, 350);
        }
    }

    // Auto-foco inteligente: solo centra si el espacio está fuera de la pantalla
    window.centerMapOnPolygon = function(poly) {
        if (!mapViewport || !poly) return;
        
        const bbox = poly.getBBox();
        const vW = mapViewport.clientWidth;
        const vH = mapViewport.clientHeight;

        // Coordenadas del polígono en la pantalla (pixeles reales)
        const polyScreenX = (bbox.x * modalZoom) + modalPanX;
        const polyScreenY = (bbox.y * modalZoom) + modalPanY;
        const polyScreenW = bbox.width * modalZoom;
        const polyScreenH = bbox.height * modalZoom;

        // Comprobar si el polígono está al menos parcialmente visible en pantalla
        // Le damos un pequeño margen de 20px para que no se sienta justo en el borde cortado
        const margin = 20;
        const isVisible = (polyScreenX + polyScreenW > margin) && 
                          (polyScreenX < vW - margin) && 
                          (polyScreenY + polyScreenH > margin) && 
                          (polyScreenY < vH - margin);

        if (isVisible) {
            // El polígono ya está visible en la pantalla. 
            // Para priorizar la experiencia de navegación (tipo Google Maps o AutoCAD),
            // NO forzamos un movimiento brusco. Mantenemos el contexto visual del usuario.
            return;
        }

        // Si el polígono quedó completamente fuera del área visible,
        // realizamos un desplazamiento suave (smooth pan) para centrarlo.
        const polyCenterX = bbox.x + (bbox.width / 2);
        const polyCenterY = bbox.y + (bbox.height / 2);
        
        modalPanX = (vW / 2) - (polyCenterX * modalZoom);
        modalPanY = (vH / 2) - (polyCenterY * modalZoom);
        
        applyTransform(true);
    };

    function zoomToCenter(delta) {
        const newZoom = Math.min(Math.max(modalZoom + delta, 0.5), 4);
        if (!mapViewport) return;
        const rect = mapViewport.getBoundingClientRect();
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        const imgX = (centerX - modalPanX) / modalZoom;
        const imgY = (centerY - modalPanY) / modalZoom;
        
        modalZoom = newZoom;
        modalPanX = centerX - (imgX * modalZoom);
        modalPanY = centerY - (imgY * modalZoom);
        
        applyTransform(true);
    }

    window.calculateFitToView = function() {
        if (!mapViewport) return;
        const img = document.getElementById('modalMapImage');
        if (!img || !img.naturalWidth) return;

        const vW = mapViewport.clientWidth;
        const vH = mapViewport.clientHeight;
        
        // Márgenes de seguridad
        const margin = 30;
        const availableW = vW - (margin * 2);
        const availableH = vH - (margin * 2);

        // Calcular escalas para que quepa completo
        const scaleX = availableW / img.naturalWidth;
        const scaleY = availableH / img.naturalHeight;

        // Tomar la escala mínima para asegurar que quepa sin recortarse
        const fitScale = Math.min(scaleX, scaleY);
        
        modalZoom = Math.min(Math.max(fitScale, 0.1), 4);

        // Calcular el centrado absoluto
        const scaledW = img.naturalWidth * modalZoom;
        const scaledH = img.naturalHeight * modalZoom;

        modalPanX = (vW - scaledW) / 2;
        modalPanY = (vH - scaledH) / 2;

        applyTransform(true);
    };

    window.modalMapZoomIn = function() { zoomToCenter(0.4); };
    window.modalMapZoomOut = function() { zoomToCenter(-0.4); };
    window.modalMapZoomReset = function() { 
        window.calculateFitToView();
    };

    // Recalcular Fit to View al cambiar el tamaño de ventana (debounce)
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // Solo si el modal está abierto (si no, no tiene clientWidth válido)
            const modal = document.getElementById('reservationModalWide');
            if (modal && modal.style.display === 'flex') {
                window.calculateFitToView();
            }
        }, 150);
    });

    if (mapViewport) {
        // Scroll wheel zoom (Centrado al puntero)
        mapViewport.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.15 : 0.15;
            const newZoom = Math.min(Math.max(modalZoom + delta, 0.5), 4);
            
            const rect = mapViewport.getBoundingClientRect();
            const cursorX = e.clientX - rect.left;
            const cursorY = e.clientY - rect.top;
            
            const imgX = (cursorX - modalPanX) / modalZoom;
            const imgY = (cursorY - modalPanY) / modalZoom;
            
            modalZoom = newZoom;
            modalPanX = cursorX - (imgX * modalZoom);
            modalPanY = cursorY - (imgY * modalZoom);
            
            applyTransform(false);
        }, {passive: false});

        // Drag to pan
        mapViewport.addEventListener('mousedown', (e) => {
            if(e.button !== 0) return; // solo click izquierdo
            modalIsDragging = true;
            modalStartX = e.clientX - modalPanX;
            modalStartY = e.clientY - modalPanY;
            mapViewport.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!modalIsDragging) return;
            modalPanX = e.clientX - modalStartX;
            modalPanY = e.clientY - modalStartY;
            applyTransform(false);
        });

        window.addEventListener('mouseup', () => {
            modalIsDragging = false;
            mapViewport.style.cursor = 'grab';
        });
        
        window.addEventListener('mouseleave', () => {
            modalIsDragging = false;
            mapViewport.style.cursor = 'grab';
        });
    }

</script>

<?php
// Incluir el pie de página común
include 'footer.php';
?>
