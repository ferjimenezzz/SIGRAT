<?php
/**
 * @file prestamos.php
 * @summary Interfaz de gestión de préstamos.
 * @description Módulo administrativo para listar, buscar, filtrar y registrar préstamos de equipo.
 */

require_once 'header.php';
require_once '../backend/controllers/LoanController.php';

$loanController = new \Controllers\LoanController();

// Manejar POST (Nuevo, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'new_loan_dynamic') {
        $fecha_ent = empty($_POST['fecha_ent']) ? null : $_POST['fecha_ent'];
        $res = $loanController->createDynamicLoan(
            $_POST['equipo'], $_POST['categoria'], $_POST['serie'], 
            $_POST['nombre'], $_POST['correo'], $_POST['area'], 
            $_POST['fecha_pres'], $fecha_ent, $_POST['estatus'], $_POST['observaciones']
        );
        if ($res['success']) echo "<script>alert('Préstamo registrado correctamente.'); window.location.href='prestamos.php';</script>";
        else echo "<script>alert('Error al registrar: " . addslashes($res['error']) . "');</script>";
    } 
    elseif ($_POST['action'] === 'edit_loan') {
        $fecha_ent = empty($_POST['fecha_ent']) ? null : $_POST['fecha_ent'];
        $res = $loanController->updateLoan($_POST['pres_id'], $_POST['estatus'], $_POST['fecha_pres'], $fecha_ent);
        if ($res['success']) echo "<script>alert('Préstamo actualizado.'); window.location.href='prestamos.php';</script>";
        else echo "<script>alert('Error al actualizar: " . addslashes($res['error']) . "');</script>";
    }
    elseif ($_POST['action'] === 'delete_loan') {
        $res = $loanController->deleteLoan($_POST['pres_id']);
        if ($res['success']) echo "<script>alert('Préstamo eliminado exitosamente.'); window.location.href='prestamos.php';</script>";
        else echo "<script>alert('Error al eliminar: " . addslashes($res['error']) . "');</script>";
    }
}

$loans = $loanController->getAllLoans();
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* ... Estilos anteriores simplificados por espacio ... */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title h2 { font-size: 22px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
    .page-title p { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .header-actions { display: flex; gap: 16px; align-items: center; }
    .filters-bar { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .filters-group { display: flex; gap: 12px; }
    .filter-btn { background: white; border: 1px solid var(--border-color); color: var(--text-secondary); padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
    .filter-btn.active { background: #f1f5f9; color: var(--text-primary); border-color: #cbd5e1; }
    
    .table-container { background: white; border-radius: 12px; border: 2px solid var(--accent-blue); overflow: hidden; margin-bottom: 24px; }
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom th { background: #1e293b; color: white; padding: 16px 24px; text-align: left; font-size: 12px; font-weight: 600; }
    .table-custom td { padding: 16px 24px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    
    .item-cell, .user-cell, .date-cell { display: flex; align-items: center; gap: 10px; }
    .item-icon { font-size: 24px; color: var(--text-primary); }
    .item-info h4 { font-size: 14px; font-weight: 700; margin: 0; color: var(--text-primary); }
    .item-info p { font-size: 11px; color: var(--text-muted); margin: 0; font-weight: 600; }
    .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #475569; }
    .user-name, .date-cell { font-size: 13px; font-weight: 600; color: var(--text-primary); }
    .date-cell i { font-size: 16px; }
    .date-cell i.red { color: #ef4444; } .date-cell i.blue { color: #3b82f6; }
    
    .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
    .status-badge.activo { background: #dcfce7; color: #16a34a; }
    .status-badge.en-prestamo { background: #fef9c3; color: #ca8a04; }
    .status-badge.vencido { background: #fee2e2; color: #dc2626; }
    .status-badge.devuelto { background: #f3e8ff; color: #9333ea; }

    .action-btns { display: flex; gap: 8px; }
    .btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .btn-icon:hover { background: #f8fafc; color: var(--text-primary); }
    .btn-icon.delete:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 41, 0.6); z-index: 1000; display: none; align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-content-box { background: white; border-radius: 16px; width: 100%; max-width: 650px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 18px; font-weight: 700; color: var(--text-primary); }
    .close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; }
    .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
    .form-section { margin-bottom: 24px; }
    .form-section-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; background: #f8fafc; }

    /* Ajuste para que Select2 se vea igual al input de Bootstrap */
    .select2-container .select2-selection--single { height: 44px; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; font-size: 14px; font-weight: 600; background: #f8fafc; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; right: 10px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: #1e293b; line-height: 44px; padding-left: 16px; }
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
    <div style="display: flex; gap: 8px; align-items: center;">
        <span style="font-size: 13px; color: var(--text-muted); font-weight: 500; margin-right: 12px;" id="countText">Mostrando todos</span>
        <button class="btn-secondary" onclick="exportToCSV()"><i class="bi bi-download"></i> Exportar</button>
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
            <?php foreach ($loans as $loan): ?>
                <?php 
                    $iconClass = getIconForAssetType($loan['tipo']);
                    $estado = $loan['estatus'];
                    if ($estado == 'Activo') { $badgeClass = 'activo'; $badgeText = '<i class="bi bi-check-circle-fill"></i> Activo'; $filterClass = 'Activo'; } 
                    elseif ($estado == 'Finalizado' || $estado == 'Devuelto') { $badgeClass = 'devuelto'; $badgeText = '<i class="bi bi-check"></i> Devuelto'; $filterClass = 'Finalizado'; } 
                    elseif ($estado == 'Atrasado' || $estado == 'Vencido') { $badgeClass = 'vencido'; $badgeText = '<i class="bi bi-exclamation-triangle-fill"></i> Vencido'; $filterClass = 'Vencido'; } 
                    else { $badgeClass = 'en-prestamo'; $badgeText = '<i class="bi bi-clock-fill"></i> En Préstamo'; $filterClass = 'Activo'; }

                    $fechaPres = date('Y-m-d', strtotime($loan['fecha_pres']));
                    $fechaDevRaw = $loan['fecha_ent'] ? date('Y-m-d', strtotime($loan['fecha_ent'])) : '';
                    $fechaDev = $loan['fecha_ent'] ? date('d M Y', strtotime($loan['fecha_ent'])) : 'Pendiente';
                    $iniciales = strtoupper(substr($loan['solicitante_nombre'], 0, 2));

                    // Datos JSON para llenar modales
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
                <tr class="loan-row" data-status="<?php echo $filterClass; ?>">
                    <td>
                        <div class="item-cell">
                            <i class="bi <?php echo $iconClass; ?> item-icon"></i>
                            <div class="item-info"><h4><?php echo htmlspecialchars($loan['tipo'] . ' ' . $loan['marca']); ?></h4><p>Serie: <?php echo htmlspecialchars($loan['num_serie']); ?></p></div>
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
                            <button class="btn-icon" title="Editar préstamo" onclick="openEditModal(<?php echo $loanData; ?>)"><i class="bi bi-pencil-square"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este préstamo? El equipo quedará libre.');">
                                <input type="hidden" name="action" value="delete_loan">
                                <input type="hidden" name="pres_id" value="<?php echo $loan['pres_id']; ?>">
                                <button type="submit" class="btn-icon delete" title="Eliminar préstamo"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

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
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Equipo</label>
                            <select name="equipo" class="form-control searchable-select" style="width: 100%;" required>
                                <option value="">Selecciona un equipo</option>
                                <option value="Laptop">Laptop</option>
                                <option value="Proyector">Proyector</option>
                                <option value="Router">Router</option>
                                <option value="Cable">Cable</option>
                                <option value="Mobiliario">Mobiliario</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Categoría</label>
                            <select name="categoria" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" required>
                                <option value="">Selecciona categoría</option>
                                <option value="Computo">Cómputo</option>
                                <option value="Redes">Redes</option>
                                <option value="Accesorios">Accesorios</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Serie / Inventario</label>
                            <input type="text" name="serie" class="form-control" placeholder="Ingresa la serie (opcional)" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;">
                        </div>
                    </div>
                </div>
                
                <!-- Información del solicitante -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-person-fill" style="color: #0ea5e9;"></i> Información del solicitante</div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nombre del solicitante</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ingresa el nombre completo" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" required>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Correo institucional</label>
                            <input type="email" name="correo" class="form-control" placeholder="ejemplo@sistema.com.mx" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" required>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Área / Departamento</label>
                            <select name="area" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" required>
                                <option value="">Selecciona el área</option>
                                <option value="Sistemas">Sistemas</option>
                                <option value="Administración">Administración</option>
                                <option value="Docencia">Docencia</option>
                                <option value="Alumnado">Alumnado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fechas y estado -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-calendar-event-fill" style="color: #6366f1;"></i> Fechas y estado</div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Fecha de inicio</label>
                            <input type="date" name="fecha_pres" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Fecha de devolución</label>
                            <input type="date" name="fecha_ent" class="form-control" style="width: 100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;">
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Estado Inicial</label>
                            <div style="position: relative; display: flex; align-items: center; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#f8fafc;">
                                <div style="width:10px; height:10px; border-radius:50%; background:#22c55e; margin-right:8px;"></div>
                                <select name="estatus" style="border:none; background:transparent; width:100%; outline:none; font-weight:600; color:#16a34a;">
                                    <option value="Activo">Activo</option>
                                </select>
                            </div>
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
            <p><strong>Equipo:</strong> <span id="v_equipo"></span> (Serie: <span id="v_serie"></span>)</p>
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
            allowClear: true,
            width: '100%'
        });
    });

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // Rellenar Modal de Ver
    function openViewModal(data) {
        document.getElementById('v_equipo').innerText = data.equipo;
        document.getElementById('v_serie').innerText = data.serie;
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

    function exportToCSV() {
        let csvContent = "";
        document.querySelectorAll("table tr").forEach(row => {
            if (row.style.display !== 'none') {
                let rowData = [];
                const cols = row.querySelectorAll("td, th");
                cols.forEach((col, index) => {
                    if (index < cols.length - 1) rowData.push('"' + col.innerText.replace(/(\r\n|\n|\r)/gm, " ").trim() + '"');
                });
                csvContent += rowData.join(",") + "\r\n";
            }
        });
        const blob = new Blob(["\uFEFF"+csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a"); link.href = URL.createObjectURL(blob);
        link.download = "reporte_prestamos.csv"; link.click();
    }
</script>

<?php include 'footer.php'; ?>
