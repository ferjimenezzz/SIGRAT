<?php
/**
 * @file calendario.php
 * @summary Módulo de calendario unificado con vistas Mensual y Semanal, filtros rápidos y creación de reservas recurrentes/multi-día.
 */
require_once 'seguridad.php';
require_once '../backend/config/Database.php';

$db = Config\Database::getConnection();
$us_id_sesion = $_SESSION['us_id'] ?? null;
$isAdmin = isset($_SESSION['rol']) && strpos(strtoupper(trim($_SESSION['rol'])), 'ADMIN') !== false;

// 1. Obtener detalles del usuario autenticado para prellenar la reserva
$stmtUser = $db->prepare("SELECT nombre, correo, telefono, carrera FROM usuario WHERE us_id = ?");
$stmtUser->execute([$us_id_sesion]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [
    'nombre' => $_SESSION['nombre'] ?? '',
    'correo' => '',
    'telefono' => '',
    'carrera' => $_SESSION['division'] ?? ''
];

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    /* DETALLES LATERALES DERECHOS */
    .calendar-sidebar-details {
        display: flex;
        flex-direction: column;
        gap: 24px;
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
    .status-badge.badge-rechazada { background: #fce7f3; color: #be185d; }

    /* Espacios disponibles */
    .available-spaces-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 220px;
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
        position: fixed;
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
    <div class="calendar-grid-layout" id="monthViewGrid">
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

        <!-- Sidebar Derecha -->
        <div class="calendar-sidebar-details">
            <!-- Resumen del Día -->
            <?php if (hasPermission('Calendario', 'ver_resumen')): ?>
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

<!-- ==================== MODAL DE RESERVACIÓN MULTI-DÍA ==================== -->
<div class="res-modal-overlay" id="reservationModal">
    <div class="res-modal-card">
        <div class="res-modal-header">
            <h2>Nueva reserva</h2>
            <button id="btnExitResModal" type="button"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <form id="reservationForm">
            <div class="res-modal-body">
                <!-- MODO DE RESERVACIÓN: DÍA ÚNICO O MULTI-DÍA -->
                <div style="display: flex; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 10px; border: 1px solid var(--border-color);">
                    <button type="button" class="btn-switch-res-mode active" id="btnResModeSingle">Día único</button>
                    <button type="button" class="btn-switch-res-mode" id="btnResModeMultiple">Múltiples días</button>
                </div>

                <!-- SECCIÓN 1: INFORMACIÓN DE RESERVA -->
                <div>
                    <div class="res-modal-section-title">
                        <i class="bi bi-calendar-check"></i> Información de la reserva
                    </div>
                    
                    <div class="modal-grid-2">
                        <div class="modal-form-group">
                            <label>Edificio</label>
                            <select class="modal-input" id="resEdificio" required>
                                <option value="">Seleccione edificio...</option>
                                <option value="CIC">CIC</option>
                                <option value="PIDET">PIDET</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label>Espacio / Laboratorio</label>
                            <select class="modal-input" name="esp_id" id="resEspacio" required>
                                <option value="">Seleccione espacio...</option>
                                <!-- Rellenado dinámicamente -->
                            </select>
                        </div>
                    </div>

                    <!-- Campos de fecha según modo -->
                    <div id="resSingleDayFields" class="modal-form-group" style="margin-bottom: 12px;">
                        <label>Fecha</label>
                        <input type="date" class="modal-input" name="fecha_uso" id="resFecha">
                    </div>

                    <div id="resMultiDayFields" style="display: none; flex-direction: column; gap: 12px; margin-bottom: 12px;">
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
                        <div class="modal-form-group">
                            <label>Días de la semana</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="1" checked> Lun
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="2" checked> Mar
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="3" checked> Mié
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="4" checked> Jue
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="5" checked> Vie
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="6"> Sáb
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-primary); text-transform: none; cursor: pointer;">
                                    <input type="checkbox" class="weekday-checkbox" value="0"> Dom
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-grid-2">
                        <div class="modal-form-group">
                            <label>Hora Inicio</label>
                            <select class="modal-input" name="hora_ent" id="resHoraEnt" required>
                                <option value="08:00">08:00 AM</option>
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
                        <div class="modal-form-group">
                            <label>Duración</label>
                            <select class="modal-input" id="resDuracion" required>
                                <option value="1">1 hora</option>
                                <option value="2" selected>2 horas</option>
                                <option value="3">3 horas</option>
                                <option value="4">4 horas</option>
                                <option value="5">5 horas</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-grid-2">
                        <div class="modal-form-group">
                            <label>Capacidad del espacio</label>
                            <input type="text" class="modal-input" id="resCapacidadLabel" disabled value="30 personas">
                        </div>
                        <div class="modal-form-group">
                            <label>N° alumnos/asistentes</label>
                            <input type="number" class="modal-input" name="num_alumnos" id="resNumAlumnos" value="10" min="1" required>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label>Equipamiento disponible (Seleccionar para préstamo)</label>
                        <div id="resEquipamientoContainer" style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px; max-height: 120px; overflow-y: auto; padding: 8px; border: 1px solid var(--border-color); border-radius: 8px; background: #f8fafc;">
                            <div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: MOTIVO DE LA RESERVA -->
                <div>
                    <div class="res-modal-section-title">
                        <i class="bi bi-chat-left-text"></i> Motivo de la reserva
                    </div>
                    <div class="modal-form-group">
                        <label>Motivo / Actividad</label>
                        <textarea class="modal-textarea" id="resMotivo" maxlength="250" placeholder="Describe el propósito de la reserva..."></textarea>
                        <div class="char-counter"><span id="charCount">0</span> / 250</div>
                    </div>
                </div>

                <!-- SECCIÓN 3: INFORMACIÓN DEL SOLICITANTE -->
                <div>
                    <div class="res-modal-section-title">
                        <i class="bi bi-person"></i> Información del solicitante
                    </div>
                    
                    <div class="modal-grid-2">
                        <div class="modal-form-group">
                            <label>Nombre completo</label>
                            <input type="text" class="modal-input" id="resNombreSolicitante" disabled value="<?php echo htmlspecialchars($currentUser['nombre']); ?>">
                        </div>
                        <div class="modal-form-group">
                            <label>Correo institucional</label>
                            <input type="email" class="modal-input" id="resCorreoSolicitante" disabled value="<?php echo htmlspecialchars($currentUser['correo']); ?>">
                        </div>
                    </div>
                    
                    <div class="modal-grid-2">
                        <div class="modal-form-group" style="grid-column: span 2;">
                            <label>Teléfono (Opcional)</label>
                            <input type="text" class="modal-input" id="resTelefonoSolicitante" placeholder="+52 ..." value="<?php echo htmlspecialchars($currentUser['telefono']); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="res-modal-footer">
                <button type="button" class="btn-action-outline" style="flex:1; justify-content:center;" id="btnCancelReserva">Cancelar</button>
                <button type="submit" class="btn-action-primary" style="flex:1; justify-content:center;" id="btnConfirmReserva">
                    <i class="bi bi-calendar-check"></i> Confirmar reserva
                </button>
            </div>
        </form>
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

    // SISTEMA DE COLORES POR ESPACIO
    function getColorForSpace(esp_id) {
        const colors = [
            'event-color-blue', 'event-color-purple', 'event-color-orange', 
            'event-color-pink', 'event-color-green', 'event-color-teal', 'event-color-red'
        ];
        let hash = 0;
        const str = String(esp_id);
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const index = Math.abs(hash) % colors.length;
        return colors[index];
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
        
        // SWITCHER DE MODO EN MODAL (DÍA ÚNICO VS MULTI-DÍA)
        const btnResModeSingle = document.getElementById('btnResModeSingle');
        const btnResModeMultiple = document.getElementById('btnResModeMultiple');
        const resSingleDayFields = document.getElementById('resSingleDayFields');
        const resMultiDayFields = document.getElementById('resMultiDayFields');
        
        btnResModeSingle.addEventListener('click', () => {
            btnResModeSingle.classList.add('active');
            btnResModeMultiple.classList.remove('active');
            resSingleDayFields.style.display = 'block';
            resMultiDayFields.style.display = 'none';
            state.resMode = 'single';
            
            // Requiere fecha única obligatoria
            document.getElementById('resFecha').required = true;
            document.getElementById('resFechaInicio').required = false;
            document.getElementById('resFechaFin').required = false;
        });

        btnResModeMultiple.addEventListener('click', () => {
            btnResModeMultiple.classList.add('active');
            btnResModeSingle.classList.remove('active');
            resSingleDayFields.style.display = 'none';
            resMultiDayFields.style.display = 'flex';
            state.resMode = 'multiple';

            // Requiere fechas múltiples obligatorias
            document.getElementById('resFecha').required = false;
            document.getElementById('resFechaInicio').required = true;
            document.getElementById('resFechaFin').required = true;
        });

        function openResModal(defaultDate = null) {
            // Rellenar fecha seleccionada
            const todayStr = defaultDate || new Date().toISOString().split('T')[0];
            document.getElementById('resFecha').value = todayStr;
            document.getElementById('resFechaInicio').value = todayStr;
            
            // Calcular fecha fin (hoy + 7 días por defecto para facilidad)
            const dFin = new Date(todayStr + 'T00:00:00');
            dFin.setDate(dFin.getDate() + 7);
            document.getElementById('resFechaFin').value = dFin.toISOString().split('T')[0];
            
            // Vaciar y resetear campos
            document.getElementById('resEdificio').value = "";
            document.getElementById('resEspacio').innerHTML = '<option value="">Seleccione espacio...</option>';
            document.getElementById('resCapacidadLabel').value = "0 personas";
            const eqCont = document.getElementById('resEquipamientoContainer');
            if (eqCont) eqCont.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
            document.getElementById('resMotivo').value = "";
            document.getElementById('charCount').textContent = "0";

            // Forzar volver a Día Único al abrir
            btnResModeSingle.click();

            reservationModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        window.closeResModal = function() {
            reservationModal.style.display = 'none';
            document.body.style.overflow = '';
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
                const espId = parseInt(e.target.value);
                const spObj = allSpaces.find(sp => sp.esp_id === espId);
                const eqContainer = document.getElementById('resEquipamientoContainer');
                if (spObj) {
                    document.getElementById('resCapacidadLabel').value = `${spObj.capacidad} personas`;
                    
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
                checkAvailability();
            });
        }

        const resFecha = document.getElementById('resFecha');
        if (resFecha) {
            resFecha.addEventListener('change', checkAvailability);
        }

        function checkAvailability() {
            if (state.resMode !== 'single') return; // En multi-día es más complejo, lo dejamos al backend
            const espId = resEspacio.value;
            const fecha = document.getElementById('resFecha').value;
            if (!espId || !fecha) return;
            
            // Habilitar todos primero
            Array.from(document.getElementById('resHoraEnt').options).forEach(opt => {
                opt.disabled = false;
                const h = parseInt(opt.value);
                opt.text = opt.value + (h < 12 ? ' AM' : ' PM');
            });
            
            fetch(`../backend/api/index.php/reservations?esp_id=${espId}&date=${fecha}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const selectHora = document.getElementById('resHoraEnt');
                        Array.from(selectHora.options).forEach(opt => {
                            const optHour = parseInt(opt.value);
                            data.forEach(res => {
                                const startH = parseInt(res.hora_ent);
                                const endH = parseInt(res.hora_sal);
                                if (optHour >= startH && optHour < endH) {
                                    opt.disabled = true;
                                    opt.text = opt.value + ' (Ocupado)';
                                }
                            });
                        });
                        // Si la seleccionada está ocupada, cambiar
                        if (selectHora.options[selectHora.selectedIndex].disabled) {
                            for (let i=0; i<selectHora.options.length; i++) {
                                if (!selectHora.options[i].disabled) {
                                    selectHora.selectedIndex = i;
                                    break;
                                }
                            }
                        }
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
                
                // Formatear color dinámico por espacio
                let statClass = getColorForSpace(ev.esp_id);

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
                    
                    // Elegir color dinámico según espacio
                    let colorClass = getColorForSpace(sp.esp_id);

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

        document.getElementById('statReservasHoy').textContent = totalHoyCount;
        document.getElementById('statDisponibles').textContent = libresCount;
        document.getElementById('statPendientes').textContent = pendientesCount;

        // 2. Próximas Reservaciones Sidebar (Top 3 ordenados por hora de entrada)
        const upcomingList = document.getElementById('upcomingReservationsList');
        upcomingList.innerHTML = '';

        const sortedTodayEvents = [...todayEvents].sort((a,b) => a.hora_ent.localeCompare(b.hora_ent)).slice(0, 3);
        
        if (sortedTodayEvents.length === 0) {
            upcomingList.innerHTML = '<div style="font-size:12px; color:var(--text-secondary); font-style:italic; text-align:center; padding: 12px 0;">Sin reservaciones agendadas hoy.</div>';
        } else {
            sortedTodayEvents.forEach(ev => {
                const item = document.createElement('div');
                item.className = 'upcoming-res-item';
                
                let iconClass = 'icon-blue';
                let iconType = 'bi-journal-check';
                if(ev.espacio_tipo === 'Laboratorio') { iconClass = 'icon-orange'; iconType = 'bi-laptop'; }
                if(ev.espacio_tipo === 'Auditorio') { iconClass = 'icon-green'; iconType = 'bi-megaphone'; }

                let badgeText = 'Confirmada';
                let badgeClass = 'badge-confirmada';
                const est = ev.estatus || ev.status;
                if(est === 'Pendiente' || est === 'pending') { badgeText = 'Pendiente'; badgeClass = 'badge-pendiente'; }
                if(est === 'Rechazada' || est === 'rejected') { badgeText = 'Rechazada'; badgeClass = 'badge-rechazada'; }

                item.innerHTML = `
                    <div class="res-item-icon ${iconClass}"><i class="bi ${iconType}"></i></div>
                    <div class="res-item-info">
                        <div class="res-item-name">${ev.nombre_numero}</div>
                        <div class="res-item-time">${ev.hora_ent.substring(0,5)} - ${ev.hora_sal.substring(0,5)}</div>
                    </div>
                    <span class="status-badge ${badgeClass}">${badgeText}</span>
                `;
                upcomingList.appendChild(item);
            });
        }

        // 3. Espacios Disponibles Sidebar (Top 5 listado)
        const spacesList = document.getElementById('availableSpacesList');
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
    // ENVIAR SOLICITUD DE RESERVACIÓN (DÍA ÚNICO O RECURRENTE)
    // ----------------------------------------------------
    function submitReservation() {
        const espId = document.getElementById('resEspacio').value;
        const horaEnt = document.getElementById('resHoraEnt').value;
        const duracionHoras = parseInt(document.getElementById('resDuracion').value);
        const numAlumnos = parseInt(document.getElementById('resNumAlumnos').value);
        const motivo = document.getElementById('resMotivo').value;

        if (!espId || !horaEnt) {
            Swal.fire('Atención', 'Por favor, complete todos los campos obligatorios.', 'warning');
            return;
        }

        // Calcular hora de salida
        const entParts = horaEnt.split(':').map(Number);
        let salHour = entParts[0] + duracionHoras;
        let salMin = entParts[1];
        
        const salHourStr = salHour < 10 ? `0${salHour}` : `${salHour}`;
        const salMinStr = salMin < 10 ? `0${salMin}` : `${salMin}`;
        const horaSal = `${salHourStr}:${salMinStr}`;

        const requestData = {
            esp_id: parseInt(espId),
            hora_ent: `${horaEnt}:00`,
            hora_sal: `${horaSal}:00`,
            num_alumnos: numAlumnos,
            motivo: motivo,
            vis_id: null
        };

        // Procesar por modo: Día Único vs. Múltiples Días
        if (state.resMode === 'single') {
            const fecha = document.getElementById('resFecha').value;
            if(!fecha) {
                Swal.fire('Atención', 'Por favor, selecciona una fecha.', 'warning');
                return;
            }
            requestData.fecha_uso = fecha;
        } else {
            // Múltiples días
            const startStr = document.getElementById('resFechaInicio').value;
            const endStr = document.getElementById('resFechaFin').value;
            if(!startStr || !endStr) {
                Swal.fire('Atención', 'Por favor, selecciona el rango de fechas.', 'warning');
                return;
            }

            const startDate = new Date(startStr + 'T00:00:00');
            const endDate = new Date(endStr + 'T00:00:00');
            if (endDate < startDate) {
                Swal.fire('Atención', 'La fecha de fin no puede ser menor que la de inicio.', 'warning');
                return;
            }

            // Obtener días de la semana seleccionados
            const checkedWeekdays = [];
            document.querySelectorAll('.weekday-checkbox:checked').forEach(cb => {
                checkedWeekdays.push(parseInt(cb.value));
            });

            if (checkedWeekdays.length === 0) {
                Swal.fire('Atención', 'Por favor, selecciona al menos un día de la semana.', 'warning');
                return;
            }

            // Generar lista de fechas hábiles dentro del rango
            const fechas = [];
            let curr = new Date(startDate);
            while (curr <= endDate) {
                if (checkedWeekdays.includes(curr.getDay())) {
                    const y = curr.getFullYear();
                    const m = String(curr.getMonth() + 1).padStart(2, '0');
                    const d = String(curr.getDate()).padStart(2, '0');
                    fechas.push(`${y}-${m}-${d}`);
                }
                curr.setDate(curr.getDate() + 1);
            }
            
            console.log("Multi-day array generated:", fechas);

            if (fechas.length === 0) {
                alert("No hay días hábiles que coincidan en el rango seleccionado.");
                return;
            }

            // Adjuntar arreglo al payload
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

        fetch('../backend/api/index.php/reservations', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success || data.id || data.ids) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Reservación Solicitada!',
                    text: 'Tu reservación ha sido programada con éxito. Revisa tu correo para más detalles.',
                    confirmButtonColor: '#10b981'
                });
                window.closeResModal();
                
                // Recargar eventos y actualizar vista
                window.fetchEvents();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al agendar reserva',
                    text: data.error || 'Conflicto de horario o espacio no disponible.',
                    confirmButtonColor: '#ef4444'
                });
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
        })
        .finally(() => {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
        });
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
        } else {
            badge.style.background = '#fee2e2';
            badge.style.color = '#b91c1c';
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
        if (est === 'Rechazada' || est === 'rejected') statusColor = '#ef4444';
        
        const horaEnt = ev.hora_ent ? ev.hora_ent.substring(0, 5) : '00:00';
        const horaSal = ev.hora_sal ? ev.hora_sal.substring(0, 5) : '00:00';
        
        tooltip.innerHTML = `
            <div style="font-weight: 800; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <span>${ev.nombre_numero} (${ev.edificio})</span>
                <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: ${statusColor}; color: white; text-transform: uppercase;">${est}</span>
            </div>
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
        if (y + tooltipHeight > window.innerHeight) {
            y = e.clientY - tooltipHeight - 15;
        }
        
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    };

    window.hideResTooltip = function() {
        const tooltip = document.getElementById('calendarTooltip');
        if (tooltip) tooltip.style.display = 'none';
    };
</script>

<?php
// Incluir el pie de página común
include 'footer.php';
?>
