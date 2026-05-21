<?php
/**
 * @file reservas.php
 * @summary Gestión de Reservas con Selección Dinámica de Equipo por Edificio.
 */

require_once '../backend/config/Database.php';
$db = Config\Database::getConnection();
require_once '../backend/controllers/ReservationController.php';

// Handle form submission for creating a new reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resController = new Controllers\ReservationController();
    $fecha_uso = $_POST['fecha_uso'] ?? date('Y-m-d');
    
    $data = [
        'esp_id' => $_POST['esp_id'] ?? null,
        'fecha_uso' => $fecha_uso,
        'hora_ent' => $_POST['hora_ent'] ?? null,
        'hora_sal' => $_POST['hora_sal'] ?? null,
        'us_id' => $_SESSION['us_id'] ?? null,
    ];
    
    // Check if external guest
    if (isset($_GET['invite_code'])) {
        // Here we could validate the invite_code and set vis_id, but for now we fallback
    }

    $resController->create($data);
    header("Location: reservas.php?date=" . urlencode($fecha_uso) . "&success=1");
    exit();
}

include 'header.php';
$selectedDate = $_GET['date'] ?? date('Y-m-d');


// 1. Obtener Espacios para el select
$espacios = $db->query("SELECT esp_id, nombre_numero, edificio FROM ESPACIO WHERE estatus = 'Disponible' ORDER BY edificio, nombre_numero")->fetchAll();

// 2. Obtener Reservas del día
$reservasQuery = "SELECT r.*, e.nombre_numero as esp_name, e.edificio, u.nombre as user_name 
                  FROM RESERVA r
                  JOIN ESPACIO e ON r.esp_id = e.esp_id
                  LEFT JOIN USUARIO u ON r.us_id = u.us_id
                  WHERE r.fecha_uso = ?
                  ORDER BY r.hora_ent ASC";
$stmt = $db->prepare($reservasQuery);
$stmt->execute([$selectedDate]);
$reservas = $stmt->fetchAll();

// 3. Obtener Inventario por edificio (para el JS)
$inventario = $db->query("SELECT a.act_id, a.tipo, a.modelo, e.edificio 
                          FROM ACTIVO a 
                          LEFT JOIN ESPACIO e ON a.esp_asignado = e.esp_id 
                          WHERE a.estatus = 'Disponible'")->fetchAll();
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Agenda Institucional</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Gestión de laboratorios y equipamiento CIC / PIDET.</p>
        </div>
        <div style="display: flex; gap: 16px;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <i data-lucide="calendar" style="color: var(--active-blue); width: 18px;"></i>
                <input type="date" value="<?php echo $selectedDate; ?>" onchange="location.href='?date='+this.value" style="border: none; outline: none; font-size: 12px; font-weight: 800; color: #334155;">
            </div>
            <button onclick="openResModal()" class="btn-primary">+ NUEVA RESERVA</button>
        </div>
    </header>

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

<!-- Modal de Nueva Reserva -->
<div id="modal-reserva" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 550px; padding: 40px;">
        <h2 style="font-weight: 900; color: #1e293b; margin-bottom: 8px;">Programar Reservación</h2>
        <p style="font-size: 13px; color: #94a3b8; margin-bottom: 32px;">Complete el formulario para solicitar un espacio y equipo.</p>
        
        <form method="POST">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="fecha_uso" value="<?php echo $selectedDate; ?>">
                <div>
                    <label>Área / Espacio</label>
                    <select name="esp_id" id="sel-espacio" required class="form-control" onchange="filterInventory()">
                        <option value="">Seleccione un espacio...</option>
                        <?php foreach ($espacios as $esp): ?>
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
                        <!-- Se llena dinámicamente con JS -->
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

<script>
    const inventarioFull = <?php echo json_encode($inventario); ?>;

    function openResModal() {
        document.getElementById('modal-reserva').style.display = 'flex';
    }
    function closeResModal() {
        document.getElementById('modal-reserva').style.display = 'none';
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
            listDiv.innerHTML = '<p style="font-size: 11px; color: #94a3b8; grid-column: span 2;">No hay equipo disponible para este edificio.</p>';
            section.style.display = 'block';
        }
    }
</script>

<?php include 'footer.php'; ?>
