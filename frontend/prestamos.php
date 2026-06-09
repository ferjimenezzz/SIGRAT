<?php
/**
 * @file prestamos.php
 * @summary Interfaz de gestión de préstamos.
 * @description Módulo administrativo para listar, buscar, filtrar y registrar préstamos de equipo.
 */

require_once 'header.php';
require_once '../backend/controllers/LoanController.php';

// Verificar permiso (opcional, si hay sistema de permisos)
if (function_exists('hasPermission') && !hasPermission('Prestamos')) {
    // Si no tienes configurado el permiso 'Prestamos', lo omitimos por ahora 
    // o puedes descomentar la siguiente línea para restringir acceso:
    // echo "<div class='content-padding'>No tienes permiso para ver esta sección.</div>"; include 'footer.php'; exit;
}

$loanController = new \Controllers\LoanController();

// Procesar formulario de nuevo préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_loan') {
    $act_id = $_POST['act_id'];
    $us_id = $_POST['us_id'];
    // En el futuro, se podrían usar las fechas y observaciones ingresadas, pero el controlador actual 
    // usa la fecha del sistema NOW(). Lo dejamos así por compatibilidad.
    $res = $loanController->create($act_id, $us_id);
    
    if ($res['success']) {
        echo "<script>alert('Préstamo registrado correctamente.'); window.location.href='prestamos.php';</script>";
    } else {
        echo "<script>alert('Error al registrar préstamo: " . addslashes($res['error']) . "');</script>";
    }
}

// Obtener datos
$loans = $loanController->getAllLoans();
$availableAssets = $loanController->getAvailableAssets();
$users = $loanController->getUsers();

// Utilidad para iconos según tipo de equipo
function getIconForAssetType($tipo) {
    $t = strtolower($tipo);
    if (strpos($t, 'laptop') !== false || strpos($t, 'computadora') !== false || strpos($t, 'pc') !== false) return 'bi-laptop';
    if (strpos($t, 'proyector') !== false) return 'bi-projector';
    if (strpos($t, 'router') !== false || strpos($t, 'switch') !== false || strpos($t, 'red') !== false) return 'bi-router';
    if (strpos($t, 'camara') !== false || strpos($t, 'cámara') !== false) return 'bi-camera';
    if (strpos($t, 'impresora') !== false) return 'bi-printer';
    if (strpos($t, 'tablet') !== false) return 'bi-tablet';
    if (strpos($t, 'cable') !== false) return 'bi-plug';
    if (strpos($t, 'llave') !== false) return 'bi-key';
    return 'bi-box-seam'; // Default
}

// Stats para los filtros
$countTodos = count($loans);
$countActivos = 0;
$countVencidos = 0;
$countDevueltos = 0;

foreach ($loans as $l) {
    if ($l['estatus'] === 'Activo') $countActivos++;
    if ($l['estatus'] === 'Atrasado' || $l['estatus'] === 'Vencido') $countVencidos++;
    if ($l['estatus'] === 'Finalizado' || $l['estatus'] === 'Devuelto') $countDevueltos++;
}

?>

<style>
    /* Estilos específicos para la pantalla de Préstamos */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-title h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .page-title p {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 500;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    /* Filters Bar */
    .filters-bar {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .filters-group {
        display: flex;
        gap: 12px;
    }

    .filter-btn {
        background: white;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .filter-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .filter-btn.active {
        background: #f1f5f9;
        color: var(--text-primary);
        border-color: #cbd5e1;
    }

    /* Table Styles */
    .table-container {
        background: white;
        border-radius: 12px;
        border: 2px solid var(--accent-blue);
        overflow: hidden;
        margin-bottom: 24px;
    }

    .table-custom {
        width: 100%;
        border-collapse: collapse;
    }

    .table-custom th {
        background: #1e293b;
        color: white;
        padding: 16px 24px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        text-transform: none;
    }

    .table-custom td {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .table-custom tr:last-child td {
        border-bottom: none;
    }

    /* Item Details */
    .item-cell {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .item-icon {
        font-size: 24px;
        color: var(--text-primary);
    }

    .item-info h4 {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 2px 0;
    }

    .item-info p {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
        font-weight: 600;
    }

    /* User Avatar */
    .user-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }

    .user-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Date Cell */
    .date-cell {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .date-cell i {
        color: var(--text-muted);
        font-size: 16px;
    }

    .date-cell i.red { color: #ef4444; }
    .date-cell i.blue { color: #3b82f6; }

    /* Badges */
    .status-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge.activo { background: #dcfce7; color: #16a34a; }
    .status-badge.en-prestamo { background: #fef9c3; color: #ca8a04; }
    .status-badge.vencido { background: #fee2e2; color: #dc2626; }
    .status-badge.devuelto { background: #f3e8ff; color: #9333ea; }
    .status-badge.finalizado { background: #f3e8ff; color: #9333ea; }

    /* Actions */
    .action-btns {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: white;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-icon:hover {
        background: #f8fafc;
        color: var(--text-primary);
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 41, 0.6);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active { display: flex; }

    .modal-content-box {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 650px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title { font-size: 18px; font-weight: 700; color: var(--text-primary); }
    .close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; }

    .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
    
    .form-section { margin-bottom: 24px; }
    .form-section-title {
        font-size: 14px; font-weight: 700; color: var(--text-primary);
        display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
    }
    .form-section-title i { color: var(--accent-blue); font-size: 18px; }

    .form-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
    }

    .form-grid.cols-2 { grid-template-columns: repeat(2, 1fr); }

    .form-group { margin-bottom: 16px; }
    .form-group label {
        display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary);
        margin-bottom: 6px; text-transform: none; letter-spacing: 0;
    }

    .modal-footer {
        padding: 16px 24px; border-top: 1px solid var(--border-color);
        display: flex; justify-content: space-between; background: #f8fafc;
    }

    /* Paginación */
    .pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 500;
    }

    .page-numbers { display: flex; gap: 4px; }
    .page-btn {
        width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; cursor: pointer; border: 1px solid transparent;
        color: var(--text-secondary);
    }
    .page-btn:hover { background: #f1f5f9; }
    .page-btn.active { background: var(--accent-blue); color: white; }
    .page-btn.border { border-color: var(--border-color); }
</style>

<!-- HEADER Y BÚSQUEDA -->
<div class="page-header">
    <div class="page-title">
        <h2>Préstamos de Equipo</h2>
        <p>Gestiona y controla los equipos en préstamo</p>
    </div>
    <div class="header-actions">
        <div class="search-box" style="background: white;">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Buscar equipo, solicitante, o serie..." onkeyup="filterTable()">
        </div>
        <button class="btn-primary" onclick="openModal()">
            <i class="bi bi-plus-lg"></i> Nuevo préstamo
        </button>
    </div>
</div>

<!-- BARRA DE FILTROS -->
<div class="filters-bar">
    <div style="font-size: 16px; font-weight: 700; color: var(--text-primary);">Lista de Préstamos</div>
    <div class="filters-group">
        <button class="filter-btn active" onclick="setFilter('Todos', this)">
            <i class="bi bi-list-ul"></i> Todos (<?php echo $countTodos; ?>)
        </button>
        <button class="filter-btn" onclick="setFilter('Activo', this)">
            <i class="bi bi-clock"></i> En préstamo (<?php echo $countActivos; ?>)
        </button>
        <button class="filter-btn" onclick="setFilter('Vencido', this)">
            <i class="bi bi-exclamation-triangle"></i> Vencidos (<?php echo $countVencidos; ?>)
        </button>
        <button class="filter-btn" onclick="setFilter('Finalizado', this)">
            <i class="bi bi-check-circle"></i> Devueltos (<?php echo $countDevueltos; ?>)
        </button>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <span style="font-size: 13px; color: var(--text-muted); font-weight: 500; margin-right: 12px;" id="countText">
            Mostrando todos los préstamos
        </span>
        <button class="btn-secondary" onclick="exportToCSV()">
            <i class="bi bi-download"></i> Exportar
        </button>
    </div>
</div>

<!-- TABLA DE PRÉSTAMOS -->
<div class="table-container">
    <table class="table-custom" id="loansTable">
        <thead>
            <tr>
                <th>Equipo</th>
                <th>Solicitante</th>
                <th>Fecha de Préstamo</th>
                <th>Fecha de devolución</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($loans)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">No hay préstamos registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($loans as $loan): ?>
                    <?php 
                        $iconClass = getIconForAssetType($loan['tipo']);
                        $estado = $loan['estatus'];
                        // Normalización visual de estatus
                        if ($estado == 'Activo') {
                            $badgeClass = 'activo';
                            $badgeText = '<i class="bi bi-check-circle-fill"></i> Activo';
                            $filterClass = 'Activo';
                        } elseif ($estado == 'Finalizado' || $estado == 'Devuelto') {
                            $badgeClass = 'devuelto';
                            $badgeText = '<i class="bi bi-check"></i> Devuelto';
                            $filterClass = 'Finalizado';
                        } elseif ($estado == 'Atrasado' || $estado == 'Vencido') {
                            $badgeClass = 'vencido';
                            $badgeText = '<i class="bi bi-exclamation-triangle-fill"></i> Vencido';
                            $filterClass = 'Vencido';
                        } else {
                            $badgeClass = 'en-prestamo';
                            $badgeText = '<i class="bi bi-clock-fill"></i> En Préstamo';
                            $filterClass = 'Activo';
                        }

                        $fechaPres = date('d M Y', strtotime($loan['fecha_pres']));
                        $fechaDev = $loan['fecha_ent'] ? date('d M Y', strtotime($loan['fecha_ent'])) : 'Pendiente';
                        
                        $iniciales = strtoupper(substr($loan['solicitante_nombre'], 0, 2));
                    ?>
                    <tr class="loan-row" data-status="<?php echo $filterClass; ?>">
                        <td>
                            <div class="item-cell">
                                <i class="bi <?php echo $iconClass; ?> item-icon"></i>
                                <div class="item-info">
                                    <h4><?php echo htmlspecialchars($loan['tipo'] . ' ' . $loan['marca']); ?></h4>
                                    <p>Serie: <?php echo htmlspecialchars($loan['num_serie']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar"><?php echo $iniciales; ?></div>
                                <span class="user-name"><?php echo htmlspecialchars($loan['solicitante_nombre']); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="date-cell">
                                <i class="bi bi-calendar-event blue"></i> <?php echo $fechaPres; ?>
                            </div>
                        </td>
                        <td>
                            <div class="date-cell">
                                <i class="bi bi-calendar-check red"></i> <?php echo $fechaDev; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-icon" title="Ver detalles"><i class="bi bi-eye"></i></button>
                                <?php if ($estado == 'Activo'): ?>
                                    <button class="btn-icon" title="Editar/Devolver" onclick="alert('Funcionalidad de edición en desarrollo.')"><i class="bi bi-pencil-square"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAGINACIÓN (Visual) -->
<div class="pagination">
    <span>Mostrando todos los registros</span>
    <div class="page-numbers">
        <div class="page-btn border"><i class="bi bi-chevron-left"></i></div>
        <div class="page-btn active">1</div>
        <div class="page-btn border"><i class="bi bi-chevron-right"></i></div>
    </div>
</div>

<!-- MODAL NUEVO PRÉSTAMO -->
<div class="modal-overlay" id="newLoanModal">
    <div class="modal-content-box">
        <div class="modal-header">
            <div class="modal-title">Nuevo préstamo</div>
            <button class="close-btn" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <form method="POST" action="prestamos.php">
            <input type="hidden" name="action" value="new_loan">
            <div class="modal-body">
                
                <!-- Sección Equipo -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-file-earmark-text-fill"></i> Información del préstamo
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Equipo Disponible</label>
                            <select class="form-control" name="act_id" required>
                                <option value="">Selecciona un equipo...</option>
                                <?php foreach ($availableAssets as $asset): ?>
                                    <option value="<?php echo $asset['act_id']; ?>">
                                        <?php echo htmlspecialchars($asset['tipo'] . ' ' . $asset['marca'] . ' (Serie: ' . $asset['num_serie'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sección Solicitante -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-person-fill"></i> Información del solicitante
                    </div>
                    <div class="form-group">
                        <label>Usuario / Solicitante</label>
                        <select class="form-control" name="us_id" required>
                            <option value="">Selecciona un usuario registrado...</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['us_id']; ?>">
                                    <?php echo htmlspecialchars($u['nombre'] . ' - ' . $u['correo'] . ' (' . $u['carrera'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sección Fechas -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-calendar-date-fill"></i> Fechas y estado
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Fecha de inicio</label>
                            <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Estado Inicial</label>
                            <select class="form-control" disabled>
                                <option selected>Activo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-chat-dots-fill"></i> Observaciones (Opcional)
                    </div>
                    <div class="form-group">
                        <textarea class="form-control" rows="3" placeholder="Agrega observaciones adicionales sobre el préstamo..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="bi bi-journal-check"></i> Registrar préstamo</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Filtros por Estado
    function setFilter(status, btn) {
        // Actualizar UI de botones
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Filtrar filas
        const rows = document.querySelectorAll('.loan-row');
        let visibleCount = 0;
        rows.forEach(row => {
            if (status === 'Todos' || row.getAttribute('data-status') === status) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('countText').innerText = `Mostrando ${visibleCount} préstamos`;
    }

    // Búsqueda de texto
    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll(".loan-row");
        
        // Reset botones de filtro al buscar
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.filter-btn').classList.add('active'); // Seleccionar 'Todos'

        let visibleCount = 0;
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });
        document.getElementById('countText').innerText = `Mostrando ${visibleCount} préstamos`;
    }

    // Modal Control
    function openModal() {
        document.getElementById('newLoanModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('newLoanModal').classList.remove('active');
    }

    // Exportar a CSV
    function exportToCSV() {
        const rows = document.querySelectorAll("table tr");
        let csvContent = "";
        
        rows.forEach(function(row) {
            // Ignorar filas ocultas por el filtro
            if (row.style.display !== 'none') {
                let rowData = [];
                const cols = row.querySelectorAll("td, th");
                
                cols.forEach((col, index) => {
                    // No exportar la columna de acciones (última columna)
                    if (index < cols.length - 1) {
                        let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                        rowData.push('"' + text + '"');
                    }
                });
                csvContent += rowData.join(",") + "\r\n";
            }
        });

        // Crear blob y descargar
        const blob = new Blob(["\uFEFF"+csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "reporte_prestamos.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php include 'footer.php'; ?>
