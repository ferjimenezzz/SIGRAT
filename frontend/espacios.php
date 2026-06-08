<?php
/**
 * @file espacios.php
 * @summary Gestión Unificada de Espacios, Reservas y Aprobaciones.
 */
require_once 'seguridad.php';
require_once '../backend/config/Database.php';
require_once '../backend/controllers/SpaceController.php';
require_once '../backend/controllers/ReservationController.php';

$db = Config\Database::getConnection();

// --- 1. Lógica de Espacios ---
$spaceController = new Controllers\SpaceController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_space') {
    $spaceController->create($_POST);
    header("Location: espacios.php?tab=espacios");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_space') {
    $spaceController->update($_POST['esp_id'], $_POST);
    header("Location: espacios.php?tab=espacios");
    exit();
}
if (isset($_GET['delete_id'])) {
    $stmt = $db->prepare("UPDATE ESPACIO SET estatus = 'Inactivo' WHERE esp_id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: espacios.php?tab=espacios");
    exit();
}
$spaces = $db->query("SELECT * FROM ESPACIO WHERE estatus != 'Inactivo' ORDER BY edificio, nombre_numero")->fetchAll();

// --- 2. Lógica de Reservas (Calendario) ---
$resController = new Controllers\ReservationController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_reserva') {
    $fecha_uso = $_POST['fecha_uso'] ?? date('Y-m-d');
    $data = [
        'esp_id' => $_POST['esp_id'] ?? null,
        'fecha_uso' => $fecha_uso,
        'hora_ent' => $_POST['hora_ent'] ?? null,
        'hora_sal' => $_POST['hora_sal'] ?? null,
        'us_id' => $_SESSION['us_id'] ?? null,
    ];
    $resController->create($data);
    header("Location: espacios.php?tab=calendario&date=" . urlencode($fecha_uso) . "&success=1");
    exit();
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$espaciosDisponibles = $db->query("SELECT esp_id, nombre_numero, edificio FROM ESPACIO WHERE estatus = 'Disponible' ORDER BY edificio, nombre_numero")->fetchAll();

$reservasQuery = "SELECT r.*, e.nombre_numero as esp_name, e.edificio, u.nombre as user_name 
                  FROM RESERVA r
                  JOIN ESPACIO e ON r.esp_id = e.esp_id
                  LEFT JOIN USUARIO u ON r.us_id = u.us_id
                  WHERE r.fecha_uso = ?
                  ORDER BY r.hora_ent ASC";
$stmt = $db->prepare($reservasQuery);
$stmt->execute([$selectedDate]);
$reservas = $stmt->fetchAll();

$inventario = $db->query("SELECT a.act_id, a.tipo, a.modelo, e.edificio 
                          FROM ACTIVO a 
                          LEFT JOIN ESPACIO e ON a.esp_asignado = e.esp_id 
                          WHERE a.estatus = 'Disponible'")->fetchAll();

include 'header.php';
$tab = $_GET['tab'] ?? 'espacios';
?>

<!-- React Dependencies for Aprobaciones -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gestión de Espacios y Reservas</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Administración de áreas, calendario y aprobaciones.</p>
        </div>
        <div style="display: flex; gap: 8px; background: #f1f5f9; padding: 4px; border-radius: 12px;">
            <button onclick="switchTab('espacios')" id="btn-espacios" class="btn-tab <?php echo $tab == 'espacios' ? 'active' : ''; ?>">ESPACIOS</button>
            <button onclick="switchTab('calendario')" id="btn-calendario" class="btn-tab <?php echo $tab == 'calendario' ? 'active' : ''; ?>">CALENDARIO</button>
            <?php if (hasPermission('Espacios', 'update') || hasPermission('Espacios', 'delete')): // Simulando que el update/delete da poder de aprobar ?>
            <button onclick="switchTab('aprobaciones')" id="btn-aprobaciones" class="btn-tab <?php echo $tab == 'aprobaciones' ? 'active' : ''; ?>">APROBACIONES</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- 1. Pestaña Espacios -->
    <div id="tab-espacios" style="display: <?php echo $tab == 'espacios' ? 'grid' : 'none'; ?>; grid-template-columns: 2fr 1fr; gap: 32px;">
        <main class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Edificio / Nombre</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Tipo</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Capacidad</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spaces as $space): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px;">
                            <span style="background: <?php echo $space['edificio'] == 'CIC' ? '#eff6ff' : '#fff7ed'; ?>; color: <?php echo $space['edificio'] == 'CIC' ? '#2563eb' : '#ea580c'; ?>; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 900; margin-right: 8px;">
                                <?php echo $space['edificio']; ?>
                            </span>
                            <span style="font-size: 14px; font-weight: 700; color: #334155;"><?php echo $space['nombre_numero']; ?></span>
                        </td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 600; color: #64748b;"><?php echo $space['tipo']; ?></td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 600; color: #64748b;"><?php echo $space['capacidad']; ?> pers.</td>
                        <td style="padding: 16px 24px; text-align: right;">
                            <a href="javascript:void(0)" onclick='openEditSpace(<?php echo json_encode($space); ?>)' style="color: #3b82f6; text-decoration: none; font-size: 10px; font-weight: 900; margin-right: 12px;">EDITAR</a>
                            <a href="?delete_id=<?php echo $space['esp_id']; ?>" onclick="return confirm('¿Eliminar este espacio?')" style="color: #ef4444; text-decoration: none; font-size: 10px; font-weight: 900;">ELIMINAR</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

        <aside class="card">
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 24px;">Nuevo Espacio</h3>
            <form method="POST">
                <input type="hidden" name="action" value="new_space">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Edificio</label>
                        <select name="edificio" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; font-weight: 700;">
                            <option value="CIC">CIC</option>
                            <option value="PIDET">PIDET</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Nombre / Número</label>
                        <input type="text" name="nombre_numero" required placeholder="Ej: Laboratorio L1" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Tipo de Área</label>
                        <select name="tipo" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; font-weight: 700; color: #334155;">
                            <option value="">Seleccione un tipo...</option>
                            <?php foreach ($spaceController->getTiposPermitidos() as $tipoEnum): ?>
                            <option value="<?php echo htmlspecialchars($tipoEnum); ?>"><?php echo htmlspecialchars($tipoEnum); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Capacidad</label>
                        <input type="number" name="capacidad" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 44px;">REGISTRAR ÁREA</button>
                </div>
            </form>
        </aside>
    </div>

    <!-- 2. Pestaña Calendario -->
    <div id="tab-calendario" style="display: <?php echo $tab == 'calendario' ? 'flex' : 'none'; ?>; flex-direction: column; gap: 32px;">
        <div style="display: flex; gap: 16px; justify-content: space-between;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <i data-lucide="calendar" style="color: var(--active-blue); width: 18px;"></i>
                <input type="date" value="<?php echo $selectedDate; ?>" onchange="location.href='?tab=calendario&date='+this.value" style="border: none; outline: none; font-size: 12px; font-weight: 800; color: #334155;">
            </div>
            <button onclick="openResModal()" class="btn-primary">+ NUEVA RESERVA</button>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Horario</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Espacio / Edificio</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Solicitante</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservas)): ?>
                        <tr><td colspan="4" style="padding: 48px; text-align: center; color: #94a3b8; font-weight: 600;">Sin reservaciones para este día.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reservas as $res): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 20px 24px; font-size: 13px; font-weight: 800; color: #1e293b;"><?php echo $res['hora_ent']; ?> - <?php echo $res['hora_sal']; ?></td>
                            <td style="padding: 20px 24px;">
                                <span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 900; margin-right: 8px;"><?php echo $res['edificio']; ?></span>
                                <span style="font-size: 14px; font-weight: 700; color: #334155;"><?php echo $res['esp_name']; ?></span>
                            </td>
                            <td style="padding: 20px 24px; font-size: 13px; font-weight: 600; color: #64748b;"><?php echo $res['user_name'] ?: 'Visita Externa'; ?></td>
                            <td style="padding: 20px 24px;">
                                <?php 
                                $bg = '#fef3c7'; $col = '#92400e';
                                if ($res['estatus'] === 'Aprobada') { $bg = '#d1fae5'; $col = '#065f46'; }
                                elseif ($res['estatus'] === 'Rechazada') { $bg = '#fee2e2'; $col = '#991b1b'; }
                                ?>
                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 900; background: <?php echo $bg; ?>; color: <?php echo $col; ?>;">
                                    <?php echo strtoupper($res['estatus']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Pestaña Aprobaciones -->
    <div id="tab-aprobaciones" style="display: <?php echo $tab == 'aprobaciones' ? 'block' : 'none'; ?>;">
        <div id="react-approval-app"></div>
    </div>
</div>

<!-- Modal de Edición de Espacio -->
<div id="modal-edit-espacio" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 40px;">
        <h2 style="font-weight: 900; color: #1e293b; margin-bottom: 24px;">Editar Espacio</h2>
        
        <form method="POST">
            <input type="hidden" name="action" value="edit_space">
            <input type="hidden" name="esp_id" id="edit_esp_id">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Edificio</label>
                    <select name="edificio" id="edit_edificio" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; font-weight: 700;">
                        <option value="CIC">CIC</option>
                        <option value="PIDET">PIDET</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Nombre / Número</label>
                    <input type="text" name="nombre_numero" id="edit_nombre_numero" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                </div>
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Tipo de Área</label>
                    <select name="tipo" id="edit_tipo" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; font-weight: 700; color: #334155;">
                        <option value="">Seleccione un tipo...</option>
                        <?php foreach ($spaceController->getTiposPermitidos() as $tipoEnum): ?>
                        <option value="<?php echo htmlspecialchars($tipoEnum); ?>"><?php echo htmlspecialchars($tipoEnum); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Capacidad</label>
                    <input type="number" name="capacidad" id="edit_capacidad" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                </div>
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="button" onclick="closeEditSpace()" class="btn-secondary" style="flex: 1; justify-content: center;">CANCELAR</button>
                    <button type="submit" class="btn-primary" style="flex: 1; justify-content: center; background: #1e293b;">GUARDAR CAMBIOS</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Nueva Reserva -->
<div id="modal-reserva" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 550px; padding: 40px;">
        <h2 style="font-weight: 900; color: #1e293b; margin-bottom: 8px;">Programar Reservación</h2>
        <p style="font-size: 13px; color: #94a3b8; margin-bottom: 32px;">Complete el formulario para solicitar un espacio y equipo.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="new_reserva">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="fecha_uso" value="<?php echo $selectedDate; ?>">
                <div>
                    <label>Área / Espacio</label>
                    <select name="esp_id" id="sel-espacio" required class="form-control" onchange="filterInventory()">
                        <option value="">Seleccione un espacio...</option>
                        <?php foreach ($espaciosDisponibles as $esp): ?>
                        <option value="<?php echo $esp['esp_id']; ?>" data-edificio="<?php echo $esp['edificio']; ?>"><?php echo $esp['edificio']; ?> - <?php echo $esp['nombre_numero']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label>Hora Entrada</label>
                        <input type="time" name="hora_ent" required class="form-control">
                    </div>
                    <div>
                        <label>Hora Salida</label>
                        <input type="time" name="hora_sal" required class="form-control">
                    </div>
                </div>

                <div id="inventory-section" style="background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #f1f5f9; display: none;">
                    <label style="display: block; font-size: 11px; font-weight: 900; color: #1e293b; text-transform: uppercase; margin-bottom: 16px;">Equipo Disponible en este Edificio</label>
                    <div id="inventory-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; padding-top: 12px;">
                    <button type="button" onclick="closeResModal()" class="btn-secondary" style="flex: 1; justify-content: center;">DESCARTAR</button>
                    <button type="submit" class="btn-primary" style="flex: 1; justify-content: center; background: #1e293b;">SOLICITAR</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .btn-tab {
        border: none; background: none; padding: 8px 16px; border-radius: 10px;
        font-size: 11px; font-weight: 900; color: #94a3b8; cursor: pointer; transition: all 0.2s;
    }
    .btn-tab.active { background: white; color: var(--active-blue); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
</style>

<script>
    function switchTab(tab) {
        document.getElementById('tab-espacios').style.display = tab === 'espacios' ? 'grid' : 'none';
        document.getElementById('tab-calendario').style.display = tab === 'calendario' ? 'flex' : 'none';
        document.getElementById('tab-aprobaciones').style.display = tab === 'aprobaciones' ? 'block' : 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        if (document.getElementById('btn-' + tab)) {
            document.getElementById('btn-' + tab).classList.add('active');
        }
    }

    const inventarioFull = <?php echo json_encode($inventario); ?>;

    function openResModal() {
        document.getElementById('modal-reserva').style.display = 'flex';
    }
    function closeResModal() {
        document.getElementById('modal-reserva').style.display = 'none';
    }

    function openEditSpace(sp) {
        document.getElementById('edit_esp_id').value = sp.esp_id;
        document.getElementById('edit_edificio').value = sp.edificio;
        document.getElementById('edit_nombre_numero').value = sp.nombre_numero;
        document.getElementById('edit_tipo').value = sp.tipo;
        document.getElementById('edit_capacidad').value = sp.capacidad;
        document.getElementById('modal-edit-espacio').style.display = 'flex';
    }
    function closeEditSpace() {
        document.getElementById('modal-edit-espacio').style.display = 'none';
    }

    function filterInventory() {
        const select = document.getElementById('sel-espacio');
        const selectedOption = select.options[select.selectedIndex];
        const edificio = selectedOption.getAttribute('data-edificio');
        const listDiv = document.getElementById('inventory-list');
        const section = document.getElementById('inventory-section');

        listDiv.innerHTML = '';
        if (!edificio) {
            section.style.display = 'none';
            return;
        }

        const filtrado = inventarioFull.filter(i => i.edificio === edificio);

        if (filtrado.length > 0) {
            section.style.display = 'block';
            filtrado.forEach(item => {
                const label = document.createElement('label');
                label.style = 'display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer;';
                label.innerHTML = `<input type="checkbox" name="equipo[]" value="${item.act_id}"> ${item.tipo} ${item.modelo} <span style="font-size: 9px; opacity: 0.5;">(${item.edificio})</span>`;
                listDiv.appendChild(label);
            });
        } else {
            listDiv.innerHTML = '<p style="font-size: 11px; color: #94a3b8; grid-column: span 2;">No hay equipo disponible.</p>';
            section.style.display = 'block';
        }
    }
</script>

<!-- Script React original adaptado -->
<script type="text/babel">
    const { useState, useEffect } = React;
    const { Container, Typography, Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Button, Chip, Dialog, DialogTitle, DialogContent, DialogActions, TextField, CircularProgress, Alert } = MaterialUI;

    function ReservationApprovalApp() {
        const [reservations, setReservations] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);
        const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
        const [selectedReservation, setSelectedReservation] = useState(null);
        const [rejectReason, setRejectReason] = useState("");
        const [actionLoading, setActionLoading] = useState(false);

        const fetchReservations = async () => {
            setLoading(true);
            try {
                const response = await fetch('../backend/api/index.php/reservations/pending', { credentials: 'same-origin' });
                if (!response.ok) throw new Error(`Error del servidor (${response.status})`);
                const data = await response.json();
                setReservations(Array.isArray(data) ? data : []);
                setError(null);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        useEffect(() => { fetchReservations(); }, []);

        const handleApprove = async (id) => {
            if(!confirm("¿Seguro que deseas APROBAR esta reserva?")) return;
            setActionLoading(true);
            try {
                const response = await fetch(`../backend/api/index.php/reservations/${id}/approve`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (!response.ok) throw new Error("Error al aprobar");
                alert("Reserva aprobada exitosamente.");
                fetchReservations();
            } catch (err) {
                alert(err.message);
            } finally {
                setActionLoading(false);
            }
        };

        const openRejectDialog = (reservation) => {
            setSelectedReservation(reservation);
            setRejectReason("");
            setRejectDialogOpen(true);
        };

        const closeRejectDialog = () => {
            setRejectDialogOpen(false);
            setSelectedReservation(null);
        };

        const handleReject = async () => {
            if (!selectedReservation) return;
            setActionLoading(true);
            try {
                const response = await fetch(`../backend/api/index.php/reservations/${selectedReservation.re_id}/reject`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason: rejectReason })
                });
                if (!response.ok) throw new Error("Error al rechazar");
                alert("Reserva rechazada exitosamente.");
                closeRejectDialog();
                fetchReservations();
            } catch (err) {
                alert(err.message);
            } finally {
                setActionLoading(false);
            }
        };

        return (
            <div style={{ marginTop: 10, fontFamily: 'Outfit, sans-serif' }}>
                {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}
                <Paper elevation={0} sx={{ borderRadius: 3, overflow: "hidden", border: '1px solid #e2e8f0' }}>
                    {loading ? (
                        <div style={{ padding: 60, textAlign: "center" }}>
                            <CircularProgress sx={{ color: '#3b82f6' }} />
                        </div>
                    ) : (
                        <TableContainer>
                            <Table>
                                <TableHead sx={{ bgcolor: "#f8fafc" }}>
                                    <TableRow>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>ID</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Solicitante</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Espacio</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Horario</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Estado</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }} align="center">Acciones</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {reservations.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} align="center" sx={{ py: 6, color: "#94a3b8", fontWeight: 700 }}>
                                                No hay reservas pendientes en este momento.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        reservations.map((row) => (
                                            <TableRow key={row.re_id} hover>
                                                <TableCell sx={{ fontWeight: 700, color: '#64748b' }}>#{row.re_id}</TableCell>
                                                <TableCell sx={{ fontWeight: 700 }}>{row.usuario_nombre || 'Desconocido'}</TableCell>
                                                <TableCell sx={{ fontWeight: 700, color: '#334155' }}>{row.espacio_nombre || 'Desconocido'}</TableCell>
                                                <TableCell>
                                                    <div style={{ fontWeight: 800 }}>{row.fecha_uso}</div>
                                                    <div style={{ fontSize: 12, color: "#64748b", fontWeight: 600 }}>{row.hora_ent} a {row.hora_sal}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <Chip label="PENDIENTE" size="small" sx={{ fontWeight: 800, bgcolor: '#fef3c7', color: '#d97706', borderRadius: 2 }} />
                                                </TableCell>
                                                <TableCell align="center">
                                                    <Button variant="contained" size="small" sx={{ mr: 1, fontWeight: 800, borderRadius: 2, bgcolor: '#10b981', boxShadow: 'none' }} onClick={() => handleApprove(row.re_id)} disabled={actionLoading}>Aprobar</Button>
                                                    <Button variant="outlined" color="error" size="small" sx={{ fontWeight: 800, borderRadius: 2 }} onClick={() => openRejectDialog(row)} disabled={actionLoading}>Rechazar</Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    )}
                </Paper>

                <Dialog open={rejectDialogOpen} onClose={closeRejectDialog}>
                    <DialogTitle>Rechazar Solicitud</DialogTitle>
                    <DialogContent>
                        <TextField autoFocus margin="dense" label="Motivo de rechazo (opcional)" fullWidth variant="outlined" value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} />
                    </DialogContent>
                    <DialogActions>
                        <Button onClick={closeRejectDialog}>Cancelar</Button>
                        <Button onClick={handleReject} color="error" variant="contained">Confirmar Rechazo</Button>
                    </DialogActions>
                </Dialog>
            </div>
        );
    }
    const root = ReactDOM.createRoot(document.getElementById('react-approval-app'));
    root.render(<ReservationApprovalApp />);
</script>

<?php include 'footer.php'; ?>
