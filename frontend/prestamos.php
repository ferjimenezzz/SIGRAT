<?php
/**
 * @file prestamos.php
 * @summary Interfaz de gestión de préstamos.
 * @description Módulo administrativo para listar, buscar, filtrar y registrar préstamos de equipo.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

require_once 'seguridad.php';
require_once '../backend/controllers/LoanController.php';

$loanController = new \Controllers\LoanController();

$isAdmin = false;
if (isset($_SESSION['rol']) && strpos(strtoupper(trim($_SESSION['rol'])), 'ADMIN') !== false) {
    $isAdmin = true;
}
$us_id_sesion = $_SESSION['us_id'] ?? null;

$currentUser = null;
$db = \Config\Database::getConnection();
if (!$isAdmin) {
    $stmtUser = $db->prepare("SELECT nombre, apellido, correo, carrera FROM USUARIO WHERE us_id = ?");
    $stmtUser->execute([$us_id_sesion]);
    $currentUser = $stmtUser->fetch();
}

// Consultas para filtros
$stmtEspacios = $db->query("SELECT esp_id, edificio, planta, nombre_numero FROM ESPACIO WHERE estatus IN ('Disponible', 'No disponible') ORDER BY edificio, planta, nombre_numero");
$todos_espacios = $stmtEspacios->fetchAll(PDO::FETCH_ASSOC);

$edificios_agrupados = [];
foreach ($todos_espacios as $esp) {
    $edificios_agrupados[$esp['edificio']][$esp['planta']][] = $esp;
}

$stmtTipos = $db->query("SELECT DISTINCT tipo FROM ACTIVO WHERE tipo IS NOT NULL AND trim(tipo) != '' ORDER BY tipo");
$tipos_equipo = $stmtTipos->fetchAll(PDO::FETCH_COLUMN);

$stmtEstados = $db->query("SELECT DISTINCT estatus FROM PRESTAMO WHERE estatus IS NOT NULL ORDER BY estatus");
$estados_prestamo = $stmtEstados->fetchAll(PDO::FETCH_COLUMN);

// Manejar POST (Nuevo, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'new_loan_dynamic') {
        $fecha_ent = empty($_POST['fecha_ent']) ? null : $_POST['fecha_ent'];
        $estatus = isset($_POST['estatus']) ? $_POST['estatus'] : 'Activo';
        $res = $loanController->createDynamicLoan(
            $_POST['equipo'], '', $_POST['serie'] ?? '', 
            $_POST['nombre'], $_POST['correo'], $_POST['area'], 
            $_POST['fecha_pres'], $fecha_ent, $estatus, $_POST['observaciones']
        );
        if ($res['success']) { header("Location: prestamos.php?success=created"); exit(); }
        else { header("Location: prestamos.php?error=" . urlencode($res['error'])); exit(); }
    } 
    elseif ($_POST['action'] === 'edit_loan') {
        if (!$isAdmin) { header("Location: prestamos.php?error=unauthorized"); exit(); }
        $fecha_ent = empty($_POST['fecha_ent']) ? null : $_POST['fecha_ent'];
        $res = $loanController->updateLoan($_POST['pres_id'], $_POST['estatus'], $_POST['fecha_pres'], $fecha_ent);
        if ($res['success']) { header("Location: prestamos.php?success=edited"); exit(); }
        else { header("Location: prestamos.php?error=" . urlencode($res['error'])); exit(); }
    }
    elseif ($_POST['action'] === 'delete_loan') {
        if (!$isAdmin) { header("Location: prestamos.php?error=unauthorized"); exit(); }
        $res = $loanController->deleteLoan($_POST['pres_id']);
        if ($res['success']) { header("Location: prestamos.php?success=deleted"); exit(); }
        else { header("Location: prestamos.php?error=" . urlencode($res['error'])); exit(); }
    }
}

// Cargar la vista (ahora sí, se permite output HTML)
require_once 'header.php';

$loans = $loanController->getAllLoans($us_id_sesion, $isAdmin);
$availableAssets = $loanController->getAvailableAssets();
$users = $loanController->getUsers();

function getIconForAssetType($tipo) {
    $t = strtolower($tipo);
    if (strpos($t, 'laptop') !== false || strpos($t, 'computadora') !== false || strpos($t, 'pc') !== false) return 'bi-laptop';
    if (strpos($t, 'proyector') !== false) return 'bi-projector';
    if (strpos($t, 'router') !== false || strpos($t, 'switch') !== false || strpos($t, 'red') !== false) return 'bi-router';
    if (strpos($t, 'camara') !== false || strpos($t, 'cámara') !== false) return 'bi-camera';
    if (strpos($t, 'impresora') !== false) return 'bi-printer';
    if (strpos($t, 'tablet') !== false) return 'bi-tablet';
    return 'bi-box-seam';
}

$countTodos = count($loans);
$countActivos = 0; $countVencidos = 0; $countDevueltos = 0;

foreach ($loans as $l) {
    if ($l['estatus'] === 'Activo') $countActivos++;
    if ($l['estatus'] === 'Atrasado' || $l['estatus'] === 'Vencido') $countVencidos++;
    if ($l['estatus'] === 'Finalizado' || $l['estatus'] === 'Devuelto') $countDevueltos++;
}
?>

<!-- jQuery y Select2 (Para campos con buscador) -->


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- jsPDF y AutoTable para PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<style>
    /* ... Estilos anteriores simplificados por espacio ... */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-title h2 { font-size: 22px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
    .page-title p { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .header-actions { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    .filters-bar { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .filters-group { display: flex; gap: 12px; flex-wrap: wrap; }
    .filter-btn { background: white; border: 1px solid var(--border-color); color: var(--text-secondary); padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
    .filter-btn.active { background: #f1f5f9; color: var(--text-primary); border-color: #cbd5e1; }
    
    .table-container { background: white; border-radius: 12px; border: 2px solid var(--accent-blue); max-height: 450px; overflow-y: auto; overflow-x: hidden; margin-bottom: 24px; -webkit-overflow-scrolling: touch; }
    .table-custom { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .table-custom th { background: #1e293b; color: white; padding: 14px 12px; text-align: left; font-size: 12px; font-weight: 600; position: sticky; top: 0; z-index: 10; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .table-custom td { padding: 14px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; overflow: hidden; }
    /* Anchos fijos por columna para que quepan las 6 sin scroll horizontal */
    .table-custom th:nth-child(1), .table-custom td:nth-child(1) { width: 26%; }  /* Equipo */
    .table-custom th:nth-child(2), .table-custom td:nth-child(2) { width: 20%; }  /* Solicitante */
    .table-custom th:nth-child(3), .table-custom td:nth-child(3) { width: 14%; }  /* Fecha Préstamo */
    .table-custom th:nth-child(4), .table-custom td:nth-child(4) { width: 14%; }  /* Fecha Devolución */
    .table-custom th:nth-child(5), .table-custom td:nth-child(5) { width: 14%; }  /* Estado */
    .table-custom th:nth-child(6), .table-custom td:nth-child(6) { width: 12%; }  /* Acción */
    
    .item-cell { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .user-cell, .date-cell { display: flex; align-items: center; gap: 8px; }
    .item-icon { font-size: 20px; color: var(--text-primary); flex-shrink: 0; }
    .item-info { min-width: 0; }
    .item-info h4 { font-size: 13px; font-weight: 700; margin: 0; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .item-info p { font-size: 11px; color: var(--text-muted); margin: 0; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-avatar { width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #475569; flex-shrink: 0; }
    .user-name { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .date-cell { font-size: 12px; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
    .date-cell i { font-size: 14px; flex-shrink: 0; }
    .date-cell i.red { color: #ef4444; } .date-cell i.blue { color: #3b82f6; }
    
    .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .status-badge.activo { background: #dcfce7; color: #16a34a; }
    .status-badge.en-prestamo { background: #fef9c3; color: #ca8a04; }
    .status-badge.vencido { background: #fee2e2; color: #dc2626; }
    .status-badge.devuelto { background: #f3e8ff; color: #9333ea; }

    .action-btns { display: flex; gap: 6px; flex-wrap: nowrap; align-items: center; }
    .btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .btn-icon:hover { background: #f8fafc; color: var(--text-primary); }
    .btn-icon.delete:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 41, 0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
    .modal-overlay.active { display: flex; }
    .modal-content-box { background: white; border-radius: 16px; width: 100%; max-width: 650px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; }
    .modal-content-box form { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .modal-title { font-size: 18px; font-weight: 700; color: var(--text-primary); }
    .close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; }
    .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
    .form-section { margin-bottom: 24px; }
    .form-section-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; background: #f8fafc; flex-shrink: 0; flex-wrap: wrap; gap: 8px; }

    /* Select2 */
    .select2-container .select2-selection--single { height: 44px; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; font-size: 14px; font-weight: 600; background: #f8fafc; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; right: 10px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: #1e293b; line-height: 44px; padding-left: 16px; }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: flex-start; }
        .header-actions { width: 100%; }
        .header-actions .btn-primary { flex: 1; justify-content: center; }
        .header-actions .search-box { width: 100%; }
        .filters-bar { flex-direction: column; align-items: stretch; }
        .filters-group { justify-content: flex-start; }
        .form-grid { grid-template-columns: minmax(0, 1fr) !important; }
        .modal-footer { flex-direction: column-reverse; }
        .modal-footer .btn-primary,
        .modal-footer .btn-secondary { width: 100%; justify-content: center; }
    }
    @media (max-width: 480px) {
        .filter-btn span { display: none; }
        color: #ef4444;
        background-color: #fee2e2;
    }

    /* ═══════════════════════════════════════════
       OFFCANVAS DRAWER — FILTROS AVANZADOS
       ═══════════════════════════════════════════ */
    .drawer-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(15,23,42,0.45); backdrop-filter: blur(2px);
        z-index: 1040; opacity: 0; transition: opacity 0.3s ease;
    }
    .drawer-overlay.show { display: block; opacity: 1; }

    .filters-drawer {
        position: fixed; top: 0; right: -480px; width: 460px;
        max-width: 100vw; height: 100vh;
        background: #f8fafc;
        z-index: 1050;
        transition: right 0.32s cubic-bezier(0.4,0,0.2,1);
        box-shadow: -8px 0 32px rgba(15,23,42,0.12);
        display: flex; flex-direction: column;
        border-left: 1px solid #e2e8f0;
    }
    .filters-drawer.open { right: 0; }

    /* ── Encabezado ── */
    .drawer-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex; justify-content: space-between; align-items: center;
        background: white; flex-shrink: 0;
    }
    .drawer-header-left { display: flex; align-items: center; gap: 12px; }
    .drawer-icon {
        width: 44px; height: 44px; border-radius: 12px;
        background: #eff6ff; display: flex; align-items: center; justify-content: center;
        font-size: 20px; color: #2563eb; flex-shrink: 0;
    }
    .drawer-title-block h3 {
        font-size: 17px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.2;
    }
    .drawer-title-block p {
        font-size: 12px; color: #64748b; margin: 2px 0 0; font-weight: 500;
    }
    .close-drawer-btn {
        background: none; border: 1px solid #e2e8f0;
        color: #64748b; cursor: pointer; padding: 0;
        width: 36px; height: 36px; border-radius: 10px;
        transition: all 0.2s; display: flex; align-items: center; justify-content: center;
        font-size: 16px; flex-shrink: 0;
    }
    .close-drawer-btn:hover { background: #fee2e2; color: #ef4444; border-color: #fca5a5; }

    /* ── Badge filtros activos ── */
    .drawer-active-badge {
        display: none; margin: 12px 24px 0;
        background: #eff6ff; border: 1px solid #bfdbfe;
        border-radius: 8px; padding: 8px 14px;
        font-size: 12px; font-weight: 700; color: #1d4ed8;
        align-items: center; gap: 8px;
    }
    .drawer-active-badge.visible { display: flex; }
    .drawer-active-badge .badge-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #2563eb; flex-shrink: 0;
    }

    /* ── Cuerpo ── */
    .drawer-body { padding: 16px 20px; overflow-y: auto; flex: 1; }

    /* ── Tarjetas de grupo ── */
    .filter-card {
        background: white; border: 1px solid #e8edf4;
        border-radius: 14px; padding: 18px; margin-bottom: 14px;
        box-shadow: 0 1px 4px rgba(15,23,42,0.04);
    }
    .filter-card-title {
        font-size: 12px; font-weight: 800; color: #475569;
        text-transform: uppercase; letter-spacing: 0.6px;
        margin: 0 0 14px; display: flex; align-items: center; gap: 8px;
    }
    .filter-card-title i { font-size: 15px; color: #2563eb; }

    /* ── Etiquetas y selects ── */
    .filter-label {
        display: block; font-size: 12px; font-weight: 700; color: #374151;
        margin-bottom: 6px; margin-top: 12px;
    }
    .filter-label:first-child { margin-top: 0; }
    .drawer-select {
        width: 100%; height: 46px;
        border: 1.5px solid #e2e8f0; border-radius: 10px;
        padding: 0 36px 0 14px; font-size: 13.5px; font-weight: 600;
        color: #1e293b; background: #f8fafc;
        appearance: none; -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        cursor: pointer;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .drawer-select:hover:not(:disabled) { border-color: #93c5fd; background: white; }
    .drawer-select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); background: white; }
    .drawer-select:disabled { opacity: 0.45; cursor: not-allowed; background: #f1f5f9; }
    .drawer-select.has-value { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; font-weight: 700; }

    /* ── Resumen de filtros ── */
    .filter-summary {
        display: none; background: #f0fdf4; border: 1px solid #bbf7d0;
        border-radius: 12px; padding: 14px 16px; margin-bottom: 14px;
        animation: fadeInUp 0.25s ease;
    }
    .filter-summary.visible { display: block; }
    .filter-summary-title {
        font-size: 11px; font-weight: 800; color: #15803d;
        text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;
    }
    .filter-summary ul { margin: 0; padding: 0 0 0 14px; }
    .filter-summary li { font-size: 12.5px; color: #166534; font-weight: 600; margin-bottom: 2px; }

    /* ── Pie ── */
    .drawer-footer {
        padding: 16px 20px; border-top: 1px solid #e2e8f0;
        display: flex; gap: 10px; background: white; flex-shrink: 0;
    }
    .drawer-footer .btn-limpiar {
        flex: 1; height: 44px; border-radius: 10px;
        background: #f1f5f9; border: 1px solid #e2e8f0;
        color: #475569; font-size: 13.5px; font-weight: 700;
        cursor: pointer; transition: all 0.2s; display: flex;
        align-items: center; justify-content: center; gap: 6px;
    }
    .drawer-footer .btn-limpiar:hover { background: #e2e8f0; color: #1e293b; }
    .drawer-footer .btn-aplicar {
        flex: 2; height: 44px; border-radius: 10px;
        background: #2563eb; border: none;
        color: white; font-size: 13.5px; font-weight: 700;
        cursor: pointer; transition: all 0.2s; display: flex;
        align-items: center; justify-content: center; gap: 8px;
    }
    .drawer-footer .btn-aplicar:hover { background: #1d4ed8; box-shadow: 0 4px 12px rgba(37,99,235,0.35); }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="page-header">
    <div class="page-title">
        <h2>Préstamos de Equipo</h2>
        <p>Gestiona y controla los equipos en préstamo</p>
    </div>
    <div class="header-actions">
        <div class="search-box" style="background: white; border-radius: 10px; border: 1px solid var(--border-color); padding: 9px 16px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-search" style="color: var(--text-muted);"></i>
            <input type="text" id="searchInput" placeholder="Buscar equipo o serie..." onkeyup="filterTable()" style="border: none; outline: none; font-size: 13px;">
        </div>
        <button class="btn-primary" onclick="openModal('newLoanModal')">
            <i class="bi bi-plus-lg"></i> Nuevo préstamo
        </button>
    </div>
</div>

<div class="filters-bar">
    <div style="font-size: 16px; font-weight: 700; color: var(--text-primary);">Lista de Préstamos</div>
    <div class="filters-group">
        <button class="filter-btn active" onclick="setFilter('Todos', this)"><i class="bi bi-list-ul"></i> Todos (<?php echo $countTodos; ?>)</button>
        <button class="filter-btn" onclick="setFilter('Activo', this)"><i class="bi bi-clock"></i> En préstamo (<?php echo $countActivos; ?>)</button>
        <button class="filter-btn" onclick="setFilter('Vencido', this)"><i class="bi bi-exclamation-triangle"></i> Vencidos (<?php echo $countVencidos; ?>)</button>
        <button class="filter-btn" onclick="setFilter('Finalizado', this)"><i class="bi bi-check-circle"></i> Devueltos (<?php echo $countDevueltos; ?>)</button>
    </div>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <button type="button" id="filtersMainBtn" class="btn-secondary" onclick="openFiltersDrawer()"><i class="bi bi-funnel-fill"></i> Filtros</button>
        <button class="btn-secondary" onclick="exportToPDF()"><i class="bi bi-download"></i> Exportar a PDF</button>
    </div>
</div>

<div class="table-container">
    <table class="table-custom" id="loansTable">
        <thead>
            <tr>
                <th>Equipo</th>
                <th>Solicitante</th>
                <th>Fecha Préstamo</th>
                <th>Fecha Devolución</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loans as $loan): 
                $estado = $loan['estatus'];
                $iconClass = getIconForAssetType($loan['tipo']);
                $filterClass = ($estado === 'Activo') ? 'Activo' : (($estado === 'Finalizado' || $estado === 'Devuelto') ? 'Finalizado' : 'Vencido');
                $badgeClass = ($estado === 'Activo') ? 'activo' : (($estado === 'Finalizado' || $estado === 'Devuelto') ? 'devuelto' : 'vencido');
                $badgeText = ($estado === 'Activo') ? '<i class="bi bi-check-circle-fill"></i> Activo' : (($estado === 'Finalizado' || $estado === 'Devuelto') ? '<i class="bi bi-check"></i> Devuelto' : '<i class="bi bi-exclamation-triangle-fill"></i> Vencido');

                $fechaPres = date('Y-m-d', strtotime($loan['fecha_pres']));
                $fechaDevRaw = $loan['fecha_ent'] ? date('Y-m-d', strtotime($loan['fecha_ent'])) : '';
                $fechaDev = $loan['fecha_ent'] ? date('d M Y', strtotime($loan['fecha_ent'])) : 'Pendiente';
                $iniciales = strtoupper(substr($loan['solicitante_nombre'], 0, 2));

                $loanData = htmlspecialchars(json_encode([
                    'id' => $loan['pres_id'],
                    'equipo' => $loan['tipo'] . ' ' . $loan['marca'],
                    'serie' => $loan['num_serie'],
                    'solicitante' => $loan['solicitante_nombre'],
                    'correo' => $loan['solicitante_correo'],
                    'fecha_pres' => $fechaPres,
                    'fecha_ent' => $fechaDevRaw,
                    'estatus' => $estado
                ]), ENT_QUOTES, 'UTF-8');
            ?>
                <tr class="loan-row" 
                    data-edificio="<?php echo htmlspecialchars($loan['edificio'] ?? ''); ?>" 
                    data-planta="<?php echo htmlspecialchars($loan['planta'] ?? ''); ?>" 
                    data-espacio="<?php echo htmlspecialchars($loan['espacio_nombre'] ?? ''); ?>" 
                    data-estado="<?php echo htmlspecialchars($filterClass); ?>" 
                    data-tipo="<?php echo htmlspecialchars($loan['tipo']); ?>">
                    <td>
                        <div class="item-cell">
                            <i class="bi <?php echo $iconClass; ?> item-icon"></i>
                            <div class="item-info">
                                <h4><?php echo htmlspecialchars($loan['tipo'] . ' ' . $loan['marca']); ?></h4>
                                <?php 
                                $numSerie = trim($loan['num_serie'] ?? '');
                                if ($numSerie !== '' && strcasecmp($numSerie, 'N/A') !== 0): 
                                ?>
                                <p>Serie: <?php echo htmlspecialchars($numSerie); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="user-cell"><div class="user-avatar"><?php echo $iniciales; ?></div><span class="user-name"><?php echo htmlspecialchars($loan['solicitante_nombre']); ?></span></div>
                    </td>
                    <td><div class="date-cell"><i class="bi bi-calendar-event blue"></i> <?php echo date('d M Y', strtotime($loan['fecha_pres'])); ?></div></td>
                    <td><div class="date-cell"><i class="bi bi-calendar-check red"></i> <?php echo $fechaDev; ?></div></td>
                    <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-icon" title="Ver detalles" onclick="openViewModal(<?php echo $loanData; ?>)"><i class="bi bi-eye"></i></button>
                            <?php if ($isAdmin): ?>
                            <button class="btn-icon" title="Editar préstamo" onclick="openEditModal(<?php echo $loanData; ?>)"><i class="bi bi-pencil-square"></i></button>
                            <form id="delete-form-<?php echo $loan['pres_id']; ?>" method="POST" style="display:none;">
                                <input type="hidden" name="action" value="delete_loan">
                                <input type="hidden" name="pres_id" value="<?php echo $loan['pres_id']; ?>">
                            </form>
                            <button type="button" class="btn-icon delete" title="Eliminar préstamo" onclick="confirmDeleteLoan(<?php echo $loan['pres_id']; ?>)"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- OVERLAY Y DRAWER DE FILTROS -->
<div class="drawer-overlay" id="filtersOverlay" onclick="closeFiltersDrawer()"></div>
<div class="filters-drawer" id="filtersDrawer">

    <!-- Encabezado fijo -->
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div class="drawer-icon"><i class="bi bi-funnel-fill"></i></div>
            <div class="drawer-title-block">
                <h3>Filtros Avanzados</h3>
                <p>Encuentra rápidamente los préstamos que necesitas.</p>
            </div>
        </div>
        <button class="close-drawer-btn" onclick="closeFiltersDrawer()" title="Cerrar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Badge de filtros activos -->
    <div class="drawer-active-badge" id="drawerActiveBadge">
        <span class="badge-dot"></span>
        <span id="drawerBadgeText">0 filtros activos</span>
    </div>

    <!-- Cuerpo con scroll -->
    <div class="drawer-body">

        <!-- Resumen de selección -->
        <div class="filter-summary" id="filterSummary">
            <div class="filter-summary-title"><i class="bi bi-check2-circle"></i> Filtros seleccionados</div>
            <ul id="filterSummaryList"></ul>
        </div>

        <!-- Tarjeta 1: Ubicación -->
        <div class="filter-card">
            <div class="filter-card-title"><i class="bi bi-geo-alt-fill"></i> Ubicación</div>

            <label class="filter-label" for="drawerEdificio">Edificio</label>
            <select id="drawerEdificio" class="drawer-select" onchange="updateDrawerPlantas()">
                <option value="">Cualquier edificio</option>
                <?php foreach(array_keys($edificios_agrupados) as $edif): ?>
                    <option value="<?php echo htmlspecialchars($edif); ?>"><?php echo htmlspecialchars($edif); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="filter-label" for="drawerPlanta">Planta</label>
            <select id="drawerPlanta" class="drawer-select" onchange="updateDrawerEspacios()" disabled>
                <option value="">Selecciona un edificio primero</option>
            </select>

            <label class="filter-label" for="drawerEspacio">Área / Espacio</label>
            <select id="drawerEspacio" class="drawer-select" onchange="applyAdvancedFilters()" disabled>
                <option value="">Selecciona una planta primero</option>
            </select>
        </div>

        <!-- Tarjeta 2: Información del préstamo -->
        <div class="filter-card">
            <div class="filter-card-title"><i class="bi bi-clipboard2-data-fill"></i> Información del préstamo</div>

            <label class="filter-label" for="drawerEstado">Estado del préstamo</label>
            <select id="drawerEstado" class="drawer-select" onchange="applyAdvancedFilters()">
                <option value="">Cualquier estado</option>
                <?php foreach($estados_prestamo as $estado): ?>
                    <option value="<?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($estado); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="filter-label" for="drawerTipo">Tipo de equipo</label>
            <select id="drawerTipo" class="drawer-select" onchange="applyAdvancedFilters()">
                <option value="">Cualquier tipo</option>
                <?php foreach($tipos_equipo as $tipo): ?>
                    <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>

    <!-- Pie fijo -->
    <div class="drawer-footer">
        <button class="btn-limpiar" onclick="clearAllFilters()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
        <button class="btn-aplicar" onclick="closeFiltersDrawer()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
    </div>
</div>

<script>
const edificiosData = <?php echo json_encode($edificios_agrupados); ?>;

function openFiltersDrawer() {
    document.getElementById('filtersOverlay').classList.add('show');
    document.getElementById('filtersDrawer').classList.add('open');
    document.body.style.overflow = 'hidden';
    updateFilterSummary();
}

function closeFiltersDrawer() {
    document.getElementById('filtersOverlay').classList.remove('show');
    document.getElementById('filtersDrawer').classList.remove('open');
    document.body.style.overflow = '';
}

function updateDrawerPlantas() {
    const edificio = document.getElementById('drawerEdificio').value;
    const selectPlanta = document.getElementById('drawerPlanta');
    const selectEspacio = document.getElementById('drawerEspacio');
    
    selectPlanta.innerHTML = '<option value="">Cualquier planta</option>';
    selectEspacio.innerHTML = '<option value="">Selecciona una planta primero</option>';
    selectEspacio.disabled = true;

    if (edificio && edificiosData[edificio]) {
        selectPlanta.disabled = false;
        selectPlanta.innerHTML = '<option value="">Cualquier planta</option>';
        Object.keys(edificiosData[edificio]).forEach(planta => {
            selectPlanta.innerHTML += `<option value="${planta}">${planta}</option>`;
        });
    } else {
        selectPlanta.disabled = true;
        selectPlanta.innerHTML = '<option value="">Selecciona un edificio primero</option>';
    }
    updateSelectStyles();
    applyAdvancedFilters();
}

function updateDrawerEspacios() {
    const edificio = document.getElementById('drawerEdificio').value;
    const planta = document.getElementById('drawerPlanta').value;
    const selectEspacio = document.getElementById('drawerEspacio');
    
    selectEspacio.innerHTML = '<option value="">Cualquier espacio</option>';

    if (edificio && planta && edificiosData[edificio][planta]) {
        selectEspacio.disabled = false;
        edificiosData[edificio][planta].forEach(esp => {
            selectEspacio.innerHTML += `<option value="${esp.nombre_numero}">${esp.nombre_numero}</option>`;
        });
    } else {
        selectEspacio.disabled = true;
        selectEspacio.innerHTML = '<option value="">Selecciona una planta primero</option>';
    }
    updateSelectStyles();
    applyAdvancedFilters();
}

function applyAdvancedFilters() {
    const search = $('#searchInput').val().toLowerCase().trim();
    const fEdificio = document.getElementById('drawerEdificio').value;
    const fPlanta = document.getElementById('drawerPlanta').value;
    const fEspacio = document.getElementById('drawerEspacio').value;
    const fEstado = document.getElementById('drawerEstado').value;
    const fTipo = document.getElementById('drawerTipo').value;

    document.querySelectorAll('.loan-row').forEach(row => {
        let match = true;
        
        if (search !== '') {
            if (!row.innerText.toLowerCase().includes(search)) match = false;
        }
        if (fEdificio && row.getAttribute('data-edificio') !== fEdificio) match = false;
        if (fPlanta && row.getAttribute('data-planta') !== fPlanta) match = false;
        if (fEspacio && row.getAttribute('data-espacio') !== fEspacio) match = false;
        if (fEstado && row.getAttribute('data-estado') !== fEstado) match = false;
        if (fTipo) {
            let rowTipo = row.getAttribute('data-tipo') || '';
            if (rowTipo.indexOf(fTipo) === -1) match = false;
        }
        row.style.display = match ? '' : 'none';
    });

    updateSelectStyles();
    updateFilterSummary();
}

function updateSelectStyles() {
    ['drawerEdificio','drawerPlanta','drawerEspacio','drawerEstado','drawerTipo'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.value) {
            el.classList.add('has-value');
        } else {
            el.classList.remove('has-value');
        }
    });
}

function updateFilterSummary() {
    const labels = {
        drawerEdificio: 'Edificio',
        drawerPlanta: 'Planta',
        drawerEspacio: 'Espacio',
        drawerEstado: 'Estado',
        drawerTipo: 'Tipo'
    };
    const active = [];
    Object.keys(labels).forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) {
            active.push({ label: labels[id], value: el.options[el.selectedIndex].text });
        }
    });

    const badge = document.getElementById('drawerActiveBadge');
    const badgeText = document.getElementById('drawerBadgeText');
    const summary = document.getElementById('filterSummary');
    const summaryList = document.getElementById('filterSummaryList');

    if (active.length > 0) {
        badge.classList.add('visible');
        badgeText.textContent = active.length + (active.length === 1 ? ' filtro activo' : ' filtros activos');
        summaryList.innerHTML = active.map(f => `<li><strong>${f.label}:</strong> ${f.value}</li>`).join('');
        summary.classList.add('visible');
    } else {
        badge.classList.remove('visible');
        summary.classList.remove('visible');
        summaryList.innerHTML = '';
    }

    // Actualizar badge en el botón principal de la tabla
    const mainBtn = document.getElementById('filtersMainBtn');
    if (mainBtn) {
        if (active.length > 0) {
            mainBtn.innerHTML = `<i class="bi bi-funnel-fill"></i> Filtros <span style="background:#2563eb;color:white;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;">${active.length}</span>`;
        } else {
            mainBtn.innerHTML = '<i class="bi bi-funnel-fill"></i> Filtros';
        }
    }
}

function clearAllFilters() {
    document.getElementById('drawerEdificio').value = '';
    document.getElementById('drawerEstado').value = '';
    document.getElementById('drawerTipo').value = '';
    $('#searchInput').val('');
    updateDrawerPlantas();
    updateFilterSummary();
}
</script>

<!-- MODAL NUEVO PRÉSTAMO -->
<div class="modal-overlay" id="newLoanModal">
    <div class="modal-content-box" style="max-width: 750px;">
        <div class="modal-header">
            <div class="modal-title">Nuevo préstamo</div>
            <button class="close-btn" onclick="closeModal('newLoanModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="new_loan_dynamic">
            <div class="modal-body">
                <!-- Información del préstamo -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-file-earmark-text-fill" style="color: #2563eb;"></i> Información del préstamo</div>
                    <div class="form-grid" style="grid-template-columns: <?php echo $isAdmin ? '1fr 1fr' : '1fr'; ?>;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Equipo</label>
                            <select name="equipo" class="form-control searchable-select" style="width: 100%;" required>
                                <option value="">Selecciona un equipo</option>
                                <option value="Laptop">Laptop</option>
                                <option value="Proyector">Proyector</option>
                                <option value="Router">Router</option>
                                <option value="Cable">Cable</option>
                            </select>
                        </div>
                        <?php if ($isAdmin): ?>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Serie / Inventario</label>
                            <input type="text" name="serie" class="form-control" placeholder="Ingresa la serie (opcional)" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Información del solicitante -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-person-fill" style="color: #0ea5e9;"></i> Información del solicitante</div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; flex-wrap: wrap;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nombre del solicitante</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ingresa el nombre completo" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" 
                            value="<?php echo !$isAdmin && $currentUser ? htmlspecialchars($currentUser['nombre'] . ' ' . $currentUser['apellido']) : ''; ?>" 
                            <?php echo !$isAdmin ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Correo institucional</label>
                            <input type="email" name="correo" class="form-control" placeholder="ejemplo@sistema.com.mx" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" 
                            value="<?php echo !$isAdmin && $currentUser ? htmlspecialchars($currentUser['correo']) : ''; ?>" 
                            <?php echo !$isAdmin ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Área / Departamento</label>
                            <?php if (!$isAdmin): ?>
                                <input type="text" name="area" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" 
                                value="<?php echo htmlspecialchars($currentUser['carrera'] ?? 'Sin área'); ?>" readonly required>
                            <?php else: ?>
                                <select name="area" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" required>
                                    <option value="">Selecciona el área</option>
                                    <option value="Sistemas">Sistemas</option>
                                    <option value="Administración">Administración</option>
                                    <option value="Docencia">Docencia</option>
                                    <option value="Alumnado">Alumnado</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Fechas y estado -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-calendar-event-fill" style="color: #6366f1;"></i> Fechas</div>
                    <input type="hidden" name="estatus" value="Activo">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Fecha de inicio</label>
                            <input type="date" name="fecha_pres" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Fecha de devolución (Opcional)</label>
                            <input type="date" name="fecha_ent" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;">
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-chat-dots-fill" style="color: #3b82f6;"></i> Observaciones (Opcional)</div>
                    <textarea name="observaciones" class="form-control" placeholder="Agrega observaciones adicionales sobre el préstamo..." rows="3" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc; resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('newLoanModal')">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#2563eb; color:white; border:none; padding:12px 24px; border-radius:8px; font-weight:600;"><i class="bi bi-journal-check"></i> Registrar préstamo</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VER DETALLES -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content-box" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title">Detalles del Préstamo</div>
            <button class="close-btn" onclick="closeModal('viewModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <p><strong>Equipo:</strong> <span id="v_equipo"></span> <span id="v_serie_container">(Serie: <span id="v_serie"></span>)</span></p>
            <p><strong>Solicitante:</strong> <span id="v_solicitante"></span></p>
            <p><strong>Correo:</strong> <span id="v_correo"></span></p>
            <hr style="border-top:1px solid #e2e8f0; margin:16px 0;">
            <p><strong>Fecha Prestado:</strong> <span id="v_fechapres"></span></p>
            <p><strong>Fecha Devuelto:</strong> <span id="v_fechaent"></span></p>
            <p><strong>Estado:</strong> <span id="v_estado"></span></p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('viewModal')">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PRÉSTAMO -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content-box" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title">Editar Préstamo</div>
            <button class="close-btn" onclick="closeModal('editModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_loan">
            <input type="hidden" name="pres_id" id="e_pres_id">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Estado del Préstamo</label>
                    <select name="estatus" id="e_estatus" class="form-control" style="width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;">
                        <option value="Activo">Activo (En Préstamo)</option>
                        <option value="Finalizado">Finalizado (Devuelto)</option>
                        <option value="Atrasado">Atrasado (Vencido)</option>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Fecha de Préstamo</label>
                        <input type="date" name="fecha_pres" id="e_fechapres" class="form-control" style="border:1px solid #e2e8f0; border-radius:12px; padding:12px; width:100%; background:#f8fafc;" required>
                    </div>
                    <div class="form-group">
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Fecha de Devolución</label>
                        <input type="date" name="fecha_ent" id="e_fechaent" class="form-control" style="border:1px solid #e2e8f0; border-radius:12px; padding:12px; width:100%; background:#f8fafc;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Inicializar Select2 en los modales para permitir escritura/búsqueda
    $(document).ready(function() {
        $('.searchable-select').select2({
            placeholder: "Escribe para buscar...",
            allowClear: false,
            width: '100%'
        });
    });

    function openModal(id) { 
        document.getElementById(id).classList.add('active'); 
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) { 
        document.getElementById(id).classList.remove('active'); 
        document.body.style.overflow = '';
    }

    // Rellenar Modal de Ver
    function openViewModal(data) {
        document.getElementById('v_equipo').innerText = data.equipo;
        const serie = (data.serie || '').trim();
        if (serie !== '' && serie.toUpperCase() !== 'N/A') {
            document.getElementById('v_serie').innerText = serie;
            document.getElementById('v_serie_container').style.display = '';
        } else {
            document.getElementById('v_serie_container').style.display = 'none';
        }
        document.getElementById('v_solicitante').innerText = data.solicitante;
        document.getElementById('v_correo').innerText = data.correo;
        document.getElementById('v_fechapres').innerText = data.fecha_pres;
        document.getElementById('v_fechaent').innerText = data.fecha_ent || 'Pendiente';
        document.getElementById('v_estado').innerText = data.estatus;
        openModal('viewModal');
    }

    // Rellenar Modal de Editar
    function openEditModal(data) {
        document.getElementById('e_pres_id').value = data.id;
        document.getElementById('e_estatus').value = (data.estatus === 'Devuelto' ? 'Finalizado' : (data.estatus === 'Vencido' ? 'Atrasado' : data.estatus));
        document.getElementById('e_fechapres').value = data.fecha_pres;
        document.getElementById('e_fechaent').value = data.fecha_ent;
        openModal('editModal');
    }

    // Funciones de filtro y exportación (mantenidas igual)
    function setFilter(status, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const rows = document.querySelectorAll('.loan-row');
        let visibleCount = 0;
        rows.forEach(row => {
            if (status === 'Todos' || row.getAttribute('data-status') === status) { row.style.display = ''; visibleCount++; } 
            else { row.style.display = 'none'; }
        });
        document.getElementById('countText').innerText = `Mostrando ${visibleCount} préstamos`;
    }

    function filterTable() {
        const filter = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll(".loan-row");
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.filter-btn').classList.add('active');
        let visibleCount = 0;
        rows.forEach(row => {
            if (row.innerText.toLowerCase().includes(filter)) { row.style.display = ""; visibleCount++; } 
            else { row.style.display = "none"; }
        });
        document.getElementById('countText').innerText = `Mostrando ${visibleCount} préstamos`;
    }

    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        
        const pageWidth = doc.internal.pageSize.width;
        const pageHeight = doc.internal.pageSize.height;
        
        // Colores institucionales
        const primaryColor = [15, 23, 41]; // Azul oscuro SIGRAT
        const textDark = [30, 41, 59];
        const textGray = [100, 116, 139];
        
        // --- ENCABEZADO ---
        doc.setFontSize(24);
        doc.setTextColor(...primaryColor);
        doc.setFont("helvetica", "bold");
        doc.text("SIGRAT", 14, 22);
        
        doc.setFontSize(10);
        doc.setTextColor(...textGray);
        doc.setFont("helvetica", "normal");
        doc.text("Sistema Integral de Gestión de Recursos", 14, 27);
        
        doc.setFontSize(16);
        doc.setTextColor(...primaryColor);
        doc.setFont("helvetica", "bold");
        doc.text("REPORTE DE PRÉSTAMOS", pageWidth - 14, 22, { align: "right" });
        
        const dateObj = new Date();
        const dateStr = dateObj.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
        const timeStr = dateObj.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        
        doc.setFontSize(9);
        doc.setTextColor(...textGray);
        doc.setFont("helvetica", "normal");
        doc.text(`Fecha de generación: ${dateStr} ${timeStr}`, pageWidth - 14, 27, { align: "right" });
        
        const userName = "<?php echo isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'] . ' ' . ($_SESSION['apellido'] ?? '')) : 'Administrador'; ?>";
        doc.text(`Generado por: ${userName.trim() === '' ? 'Administrador' : userName}`, pageWidth - 14, 32, { align: "right" });
        
        doc.setDrawColor(226, 232, 240);
        doc.setLineWidth(0.5);
        doc.line(14, 36, pageWidth - 14, 36);
        
        // --- RECOLECCIÓN DE DATOS ---
        let bodyData = [];
        let totalActivos = 0, totalDevueltos = 0, totalVencidos = 0, totalPendientes = 0;
        
        document.querySelectorAll("#loansTable tbody tr").forEach(row => {
            if (row.style.display !== 'none') {
                const cols = row.querySelectorAll("td");
                
                let equipo = "";
                if (cols[0].querySelector('.item-info h4') && cols[0].querySelector('.item-info p')) {
                    equipo = cols[0].querySelector('.item-info h4').innerText + '\n' + cols[0].querySelector('.item-info p').innerText;
                } else {
                    equipo = cols[0].innerText.trim();
                }

                let solicitante = "";
                if (cols[1].querySelector('.user-name')) {
                    solicitante = cols[1].querySelector('.user-name').innerText;
                } else {
                    solicitante = cols[1].innerText.trim();
                }
                
                const fechaPres = cols[2].innerText.trim();
                const fechaDev = cols[3].innerText.trim();
                
                const estadoNode = cols[4].querySelector('.status-badge');
                const estadoTexto = estadoNode ? estadoNode.innerText.trim() : cols[4].innerText.trim();
                
                if (estadoTexto.toLowerCase().includes('activo')) totalActivos++;
                else if (estadoTexto.toLowerCase().includes('devuelto') || estadoTexto.toLowerCase().includes('finalizado')) totalDevueltos++;
                else if (estadoTexto.toLowerCase().includes('vencido') || estadoTexto.toLowerCase().includes('atrasado')) totalVencidos++;
                else totalPendientes++;

                bodyData.push([ equipo, solicitante, fechaPres, fechaDev, estadoTexto ]);
            }
        });

        // Resumen
        doc.setFontSize(10);
        doc.setTextColor(...textDark);
        doc.setFont("helvetica", "bold");
        doc.text(`Resumen del Reporte:`, 14, 44);
        doc.setFont("helvetica", "normal");
        doc.text(`Total de registros: ${bodyData.length}   |   Activos: ${totalActivos}   |   Devueltos: ${totalDevueltos}   |   Vencidos: ${totalVencidos}`, 14, 49);
        
        // --- TABLA ---
        doc.autoTable({
            head: [['Equipo', 'Solicitante', 'Fecha Préstamo', 'Fecha Devolución', 'Estado']],
            body: bodyData,
            startY: 55,
            theme: 'plain',
            styles: { 
                font: 'helvetica',
                fontSize: 9,
                cellPadding: { top: 6, right: 6, bottom: 6, left: 6 },
                textColor: textDark,
                valign: 'middle'
            },
            headStyles: { 
                fillColor: primaryColor, 
                textColor: [255, 255, 255],
                fontStyle: 'bold',
                halign: 'left'
            },
            alternateRowStyles: { 
                fillColor: [248, 250, 252] 
            },
            columnStyles: {
                0: { cellWidth: 55, fontStyle: 'bold' },
                1: { cellWidth: 45 },
                2: { cellWidth: 28, halign: 'center' },
                3: { cellWidth: 28, halign: 'center' },
                4: { cellWidth: 28, halign: 'center' }
            },
            didParseCell: function(data) {
                // Hacer el texto de "Serie: xxx" normal (no bold) y gris en la columna de equipo
                if (data.section === 'body' && data.column.index === 0) {
                    // jspdf-autotable no permite multiformato en la misma celda fácilmente por texto,
                    // usaremos un estilo bold general para la celda.
                }
                // Ocultar texto de estado para dibujarlo como badge
                if (data.section === 'body' && data.column.index === 4) {
                    data.cell.customText = data.cell.text[0];
                    data.cell.text = [];
                }
            },
            didDrawCell: function(data) {
                // Dibujar Badge de Estado
                if (data.section === 'body' && data.column.index === 4 && data.cell.customText) {
                    const text = data.cell.customText;
                    const textLower = text.toLowerCase();
                    
                    let bgColor, txColor;
                    if (textLower.includes('activo')) { bgColor = [220, 252, 231]; txColor = [22, 163, 74]; }
                    else if (textLower.includes('devuelto') || textLower.includes('finalizado')) { bgColor = [243, 232, 255]; txColor = [147, 51, 234]; }
                    else if (textLower.includes('vencido') || textLower.includes('atrasado')) { bgColor = [254, 226, 226]; txColor = [220, 38, 38]; }
                    else { bgColor = [254, 249, 195]; txColor = [202, 138, 4]; }
                    
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(8);
                    const txtWidth = doc.getTextWidth(text);
                    
                    const paddingX = 4;
                    const paddingY = 2;
                    const w = txtWidth + (paddingX * 2);
                    const h = 6;
                    const x = data.cell.x + (data.cell.width - w) / 2;
                    const y = data.cell.y + (data.cell.height - h) / 2;
                    
                    doc.setFillColor(...bgColor);
                    doc.roundedRect(x, y, w, h, 2, 2, 'F');
                    
                    doc.setTextColor(...txColor);
                    doc.text(text, x + paddingX, y + h - 1.8);
                }
            },
            didDrawPage: function (data) {
                // Pie de página
                const str = "Página " + doc.internal.getNumberOfPages();
                doc.setFontSize(8);
                doc.setTextColor(...textGray);
                doc.setFont("helvetica", "normal");
                
                doc.setDrawColor(226, 232, 240);
                doc.setLineWidth(0.5);
                doc.line(14, pageHeight - 15, pageWidth - 14, pageHeight - 15);
                
                doc.text("SIGRAT - Sistema Integral de Gestión de Recursos", 14, pageHeight - 10);
                doc.text(str, pageWidth - 14, pageHeight - 10, { align: "right" });
            }
        });
        
        doc.save('reporte_prestamos_sigrat.pdf');
    }

    // Funciones SweetAlert2
    function confirmDeleteLoan(id) {
        Swal.fire({
            title: '¿Eliminar préstamo?',
            text: 'Esta acción devolverá el equipo y eliminará el registro permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-' + id).submit();
            }
        });
    }

    // Alertas por URL
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const action = urlParams.get('success');
            let msg = 'Operación realizada correctamente.';
            let title = '¡Éxito!';
            let icon = 'success';
            
            if (action === 'created') msg = 'El préstamo se ha registrado correctamente.';
            if (action === 'edited') msg = 'El préstamo ha sido actualizado con éxito.';
            if (action === 'deleted') { title = 'Eliminado'; msg = 'El registro de préstamo fue dado de baja.'; icon = 'info'; }
            
            Swal.fire({ icon: icon, title: title, text: msg, timer: 3000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        if (urlParams.has('error')) {
            let msg = urlParams.get('error') === 'unauthorized' ? 'Acción no permitida.' : 'Error: ' + urlParams.get('error');
            Swal.fire({ icon: 'error', title: 'Oops...', text: msg });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });
</script>

<?php include 'footer.php'; ?>
