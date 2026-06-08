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
// Filtrar espacios disponibles para reservación basados en la división del usuario
$division_usuario = $_SESSION['division'] ?? '';
$espaciosDisponiblesQuery = "SELECT esp_id, nombre_numero, edificio 
                             FROM ESPACIO 
                             WHERE estatus = 'Disponible' 
                             AND (acceso_tipo != 'Division' OR division_restringida = ? OR division_restringida IS NULL) 
                             ORDER BY edificio, nombre_numero";
$stmtEspacios = $db->prepare($espaciosDisponiblesQuery);
$stmtEspacios->execute([$division_usuario]);
$espaciosDisponibles = $stmtEspacios->fetchAll();

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

$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM ESPACIO WHERE estatus != 'Inactivo') as total_espacios,
        (SELECT COUNT(*) FROM ESPACIO WHERE estatus = 'Disponible') as disponibles,
        (SELECT COUNT(*) FROM RESERVA WHERE fecha_uso = CURRENT_DATE) as reservas_hoy,
        (SELECT COUNT(*) FROM RESERVA WHERE estatus = 'Pendiente') as pendientes
")->fetch();

$total_espacios = $stats['total_espacios'] ?? 0;
$espacios_disp = $stats['disponibles'] ?? 0;
$reservas_hoy = $stats['reservas_hoy'] ?? 0;
$aprobaciones_pend = $stats['pendientes'] ?? 0;

include 'header.php';
$tab = $_GET['tab'] ?? 'espacios';
?>

<!-- React Dependencies for Aprobaciones -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>

<div style="display: flex; flex-direction: column; gap: 24px;">
    <!-- Encabezado con título y botones -->
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Gestión de Espacios</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Administración de áreas, calendario y aprobaciones</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <?php if (hasPermission('Espacios', 'create')): ?>
            <button onclick="openNewSpaceModal()" id="btn-action-space" class="btn-primary" style="background: #2563eb; border-radius: 8px; font-size: 12px; font-weight: 600; padding: 10px 16px; color: white; border: none; cursor: pointer; display: <?php echo $tab === 'espacios' ? 'flex' : 'none'; ?>; align-items: center; gap: 6px;"><i data-lucide="plus" style="width: 16px;"></i> Nuevo espacio</button>
            <?php endif; ?>
            <?php if (hasPermission('Reservas', 'create')): ?>
            <button onclick="openResModal()" id="btn-action-res" class="btn-primary" style="background: #2563eb; border-radius: 8px; font-size: 12px; font-weight: 600; padding: 10px 16px; color: white; border: none; cursor: pointer; display: <?php echo $tab === 'calendario' ? 'flex' : 'none'; ?>; align-items: center; gap: 6px;"><i data-lucide="calendar-plus" style="width: 16px;"></i> Nueva reserva</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Barra de Búsqueda, Filtros y Pestañas -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
            <div style="position: relative; width: 300px;">
                <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; color: #94a3b8;"></i>
                <input type="text" id="searchInput" placeholder="Buscar espacio, edificio..." style="width: 100%; padding: 10px 10px 10px 36px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 500; outline: none;">
            </div>
            
            <div style="display: flex; gap: 12px; margin-left: 20px;">
                <select id="edificioFilter" style="padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #475569; background: white; outline: none; cursor: pointer;">
                    <option value="">🏢 Todos los edificios</option>
                    <option value="CIC">CIC</option>
                    <option value="PIDET">PIDET</option>
                </select>
            </div>
        </div>
        
        <div style="display: flex; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 10px; border: 1px solid #e2e8f0;">
            <button onclick="switchTab('espacios')" id="btn-espacios" class="btn-tab <?php echo $tab == 'espacios' ? 'active' : ''; ?>">ESPACIOS</button>
            <button onclick="switchTab('calendario')" id="btn-calendario" class="btn-tab <?php echo $tab == 'calendario' ? 'active' : ''; ?>">CALENDARIO</button>
            <?php if (hasPermission('Reservas', 'update') || hasPermission('Espacios', 'update')): ?>
            <button onclick="switchTab('aprobaciones')" id="btn-aprobaciones" class="btn-tab <?php echo $tab == 'aprobaciones' ? 'active' : ''; ?>">APROBACIONES</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarjetas Estadísticas -->
    <div id="stats-espacios" style="display: <?php echo $tab === 'espacios' ? 'grid' : 'none'; ?>; grid-template-columns: repeat(4, 1fr); gap: 20px;">
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Total de Espacios</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $total_espacios; ?></div>
            <div style="display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Registrados</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Espacios Disponibles</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $espacios_disp; ?></div>
            <div style="display: inline-block; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Operativos</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Reservas de Hoy</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $reservas_hoy; ?></div>
            <div style="display: inline-block; background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Agendadas</div>
        </div>
        <div class="stat-card" style="background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h4 style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;">Por Aprobar</h4>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;"><?php echo $aprobaciones_pend; ?></div>
            <div style="display: inline-block; background: #fce7f3; color: #be185d; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Pendientes</div>
        </div>
    </div>

    <!-- 1. Pestaña Espacios -->
    <div id="tab-espacios" class="card" style="display: <?php echo $tab === 'espacios' ? 'block' : 'none'; ?>; padding: 0; overflow: hidden; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;" id="spacesTable">
            <thead style="border-bottom: 1px solid #e2e8f0;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Edificio / Nombre</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Tipo</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Capacidad</th>
                    <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spaces as $space): ?>
                <tr class="space-row" data-edificio="<?php echo htmlspecialchars($space['edificio']); ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                    <td style="padding: 16px 24px; display: flex; align-items: center; gap: 12px;">
                        <span style="background: <?php echo $space['edificio'] == 'CIC' ? '#eff6ff' : '#fff7ed'; ?>; color: <?php echo $space['edificio'] == 'CIC' ? '#2563eb' : '#ea580c'; ?>; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block;">
                            <?php echo $space['edificio']; ?>
                        </span>
                        <div>
                            <p class="space-name" style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0;"><?php echo htmlspecialchars($space['nombre_numero']); ?></p>
                            <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;"><?php echo htmlspecialchars($space['acceso_tipo']); ?></p>
                        </div>
                    </td>
                    <td style="padding: 16px 24px; font-size: 13px; font-weight: 600; color: #475569;">
                        <?php echo htmlspecialchars($space['tipo']); ?>
                    </td>
                    <td style="padding: 16px 24px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="users" style="width: 14px; color: #94a3b8;"></i>
                            <span style="font-size: 13px; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($space['capacidad']); ?> pers.</span>
                        </div>
                    </td>
                    <td style="padding: 16px 24px; text-align: center;">
                        <?php if (hasPermission('Espacios', 'update')): ?>
                        <button onclick='openEditSpace(<?php echo json_encode($space); ?>)' style="background: none; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; color: #475569; cursor: pointer; transition: all 0.2s; margin-right: 8px;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='none'">
                            <i data-lucide="edit-2" style="width: 16px; height: 16px;"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (hasPermission('Espacios', 'delete')): ?>
                        <a href="?delete_id=<?php echo $space['esp_id']; ?>" onclick="return confirm('¿Eliminar este espacio?')" style="display: inline-block; background: none; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; color: #ef4444; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">
                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. Pestaña Calendario -->
    <div id="tab-calendario" style="display: <?php echo $tab == 'calendario' ? 'flex' : 'none'; ?>; flex-direction: column; gap: 32px;">
        <div style="display: flex; gap: 16px; justify-content: space-between;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <i data-lucide="calendar" style="color: var(--active-blue); width: 18px;"></i>
                <input type="date" value="<?php echo $selectedDate; ?>" onchange="location.href='?tab=calendario&date='+this.value" style="border: none; outline: none; font-size: 12px; font-weight: 800; color: #334155;">
            </div>
            <!-- Nueva reserva button moved to header -->
        </div>

        <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Horario</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Espacio / Edificio</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Solicitante</th>
                        <th style="padding: 16px 24px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservas)): ?>
                        <tr><td colspan="4" style="padding: 48px; text-align: center; color: #94a3b8; font-weight: 600;">Sin reservaciones para este día.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reservas as $res): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                            <td style="padding: 16px 24px; font-size: 13px; font-weight: 800; color: #1e293b;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="clock" style="width: 14px; color: #94a3b8;"></i>
                                    <?php echo $res['hora_ent']; ?> - <?php echo $res['hora_sal']; ?>
                                </div>
                            </td>
                            <td style="padding: 16px 24px;">
                                <span style="background: <?php echo $res['edificio'] == 'CIC' ? '#eff6ff' : '#fff7ed'; ?>; color: <?php echo $res['edificio'] == 'CIC' ? '#2563eb' : '#ea580c'; ?>; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-right: 8px; display: inline-block;"><?php echo $res['edificio']; ?></span>
                                <span style="font-size: 14px; font-weight: 700; color: #334155;"><?php echo $res['esp_name']; ?></span>
                            </td>
                            <td style="padding: 16px 24px; font-size: 13px; font-weight: 600; color: #475569;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($res['user_name'] ?: 'Visita'); ?>&background=random&color=fff&rounded=true&size=24" alt="Avatar" style="width: 24px; height: 24px; border-radius: 50%;">
                                    <?php echo $res['user_name'] ?: 'Visita Externa'; ?>
                                </div>
                            </td>
                            <td style="padding: 16px 24px;">
                                <?php 
                                $bg = '#fef3c7'; $col = '#b45309';
                                if ($res['estatus'] === 'Aprobada') { $bg = '#dcfce7'; $col = '#166534'; }
                                elseif ($res['estatus'] === 'Rechazada') { $bg = '#fce7f3'; $col = '#be185d'; }
                                ?>
                                <span style="padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; background: <?php echo $bg; ?>; color: <?php echo $col; ?>; display: inline-block;">
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

<!-- Modal Nuevo Espacio -->
<div id="modal-nuevo-espacio" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 500px; padding: 32px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="font-size: 20px; font-weight: 800; color: #1e293b;">Nuevo Espacio</h2>
            <button onclick="closeNewSpaceModal()" style="background: none; border: none; cursor: pointer; color: #94a3b8;"><i data-lucide="x"></i></button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="new_space">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Edificio</label>
                    <select name="edificio" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <option value="CIC">CIC</option>
                        <option value="PIDET">PIDET</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Nombre / Número</label>
                    <input type="text" name="nombre_numero" required placeholder="Ej: L1" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Tipo de Área</label>
                    <select name="tipo" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <option value="">Seleccione...</option>
                        <?php foreach ($spaceController->getTiposPermitidos() as $tipoEnum): ?>
                        <option value="<?php echo htmlspecialchars($tipoEnum); ?>"><?php echo htmlspecialchars($tipoEnum); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Capacidad</label>
                    <input type="number" name="capacidad" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Tipo de Acceso</label>
                <select name="acceso_tipo" id="acceso_tipo_new" onchange="toggleDivisionSelect('new')" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                    <option value="General">General (Auto-aprobado)</option>
                    <option value="Division">Por División (Auto-aprobado para la división)</option>
                    <option value="Restringido">Restringido (Requiere aprobación del admin)</option>
                </select>
            </div>
            
            <div id="div_restringida_new" style="display: none; margin-bottom: 32px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">División Restringida</label>
                <select name="division_restringida" id="division_restringida_new" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                    <option value="">Seleccione una división...</option>
                    <option value="División Económico - Administrativa">División Económico - Administrativa</option>
                    <option value="División de Tecnologías de Automatización e Información">División de Tecnologías de Automatización e Información</option>
                    <option value="División Industrial">División Industrial</option>
                    <option value="División de Tecnología Ambiental">División de Tecnología Ambiental</option>
                    <option value="División de Idiomas">División de Idiomas</option>
                </select>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="button" onclick="closeNewSpaceModal()" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="flex: 1; background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Guardar Espacio</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edición de Espacio -->
<div id="modal-edit-espacio" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 500px; padding: 32px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="font-size: 20px; font-weight: 800; color: #1e293b;">Editar Espacio</h2>
            <button onclick="closeEditSpace()" style="background: none; border: none; cursor: pointer; color: #94a3b8;"><i data-lucide="x"></i></button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="edit_space">
            <input type="hidden" name="esp_id" id="edit_esp_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Edificio</label>
                    <select name="edificio" id="edit_edificio" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <option value="CIC">CIC</option>
                        <option value="PIDET">PIDET</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Nombre / Número</label>
                    <input type="text" name="nombre_numero" id="edit_nombre_numero" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Tipo de Área</label>
                    <select name="tipo" id="edit_tipo" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <option value="">Seleccione...</option>
                        <?php foreach ($spaceController->getTiposPermitidos() as $tipoEnum): ?>
                        <option value="<?php echo htmlspecialchars($tipoEnum); ?>"><?php echo htmlspecialchars($tipoEnum); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Capacidad</label>
                    <input type="number" name="capacidad" id="edit_capacidad" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Tipo de Acceso</label>
                <select name="acceso_tipo" id="edit_acceso_tipo" onchange="toggleDivisionSelect('edit')" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                    <option value="General">General (Auto-aprobado)</option>
                    <option value="Division">Por División (Auto-aprobado para la división)</option>
                    <option value="Restringido">Restringido (Requiere aprobación del admin)</option>
                </select>
            </div>
            
            <div id="div_restringida_edit" style="display: none; margin-bottom: 32px;">
                <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">División Restringida</label>
                <select name="division_restringida" id="edit_division_restringida" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                    <option value="">Seleccione una división...</option>
                    <option value="División Económico - Administrativa">División Económico - Administrativa</option>
                    <option value="División de Tecnologías de Automatización e Información">División de Tecnologías de Automatización e Información</option>
                    <option value="División Industrial">División Industrial</option>
                    <option value="División de Tecnología Ambiental">División de Tecnología Ambiental</option>
                    <option value="División de Idiomas">División de Idiomas</option>
                </select>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="button" onclick="closeEditSpace()" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="flex: 1; background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Nueva Reserva -->
<div id="modal-reserva" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 550px; padding: 32px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h2 style="font-size: 20px; font-weight: 800; color: #1e293b;">Programar Reservación</h2>
            <button onclick="closeResModal()" style="background: none; border: none; cursor: pointer; color: #94a3b8;"><i data-lucide="x"></i></button>
        </div>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 24px;">Complete el formulario para solicitar un espacio y equipo.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="new_reserva">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="fecha_uso" value="<?php echo $selectedDate; ?>">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Área / Espacio</label>
                    <select name="esp_id" id="sel-espacio" required onchange="filterInventory()" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none; background: white;">
                        <option value="">Seleccione un espacio...</option>
                        <?php foreach ($espaciosDisponibles as $esp): ?>
                        <option value="<?php echo $esp['esp_id']; ?>" data-edificio="<?php echo $esp['edificio']; ?>"><?php echo $esp['edificio']; ?> - <?php echo $esp['nombre_numero']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Hora Entrada</label>
                        <input type="time" name="hora_ent" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Hora Salida</label>
                        <input type="time" name="hora_sal" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 500; font-size: 14px; outline: none;">
                    </div>
                </div>

                <div id="inventory-section" style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; display: none;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; margin-bottom: 16px;">Equipo Disponible</label>
                    <div id="inventory-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 12px;">
                    <button type="button" onclick="closeResModal()" style="flex: 1; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; background: #2563eb; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Solicitar</button>
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

    // Filters for spaces
    const searchInput = document.getElementById('searchInput');
    const edificioFilter = document.getElementById('edificioFilter');
    const rows = document.querySelectorAll('.space-row');

    function filterTable() {
        if(!searchInput) return;
        const query = searchInput.value.toLowerCase();
        const edificio = edificioFilter.value;

        rows.forEach(row => {
            const name = row.querySelector('.space-name').innerText.toLowerCase();
            const rowEdificio = row.getAttribute('data-edificio');

            const matchSearch = name.includes(query);
            const matchEdificio = edificio === "" || rowEdificio === edificio;

            if (matchSearch && matchEdificio) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if(searchInput) searchInput.addEventListener('input', filterTable);
    if(edificioFilter) edificioFilter.addEventListener('change', filterTable);

    function openNewSpaceModal() {
        document.getElementById('modal-nuevo-espacio').style.display = 'flex';
    }
    function closeNewSpaceModal() {
        document.getElementById('modal-nuevo-espacio').style.display = 'none';
    }

    function openResModal() {
        document.getElementById('modal-reserva').style.display = 'flex';
    }
    function closeResModal() {
        document.getElementById('modal-reserva').style.display = 'none';
    }

    function toggleDivisionSelect(context) {
        const accesoTipo = document.getElementById(context === 'new' ? 'acceso_tipo_new' : 'edit_acceso_tipo').value;
        const divRestringida = document.getElementById(context === 'new' ? 'div_restringida_new' : 'div_restringida_edit');
        const selectRestringida = document.getElementById(context === 'new' ? 'division_restringida_new' : 'edit_division_restringida');
        
        if (accesoTipo === 'Division') {
            divRestringida.style.display = 'block';
            selectRestringida.required = true;
        } else {
            divRestringida.style.display = 'none';
            selectRestringida.required = false;
        }
    }

    function openEditSpace(sp) {
        document.getElementById('edit_esp_id').value = sp.esp_id;
        document.getElementById('edit_edificio').value = sp.edificio;
        document.getElementById('edit_nombre_numero').value = sp.nombre_numero;
        document.getElementById('edit_tipo').value = sp.tipo;
        document.getElementById('edit_capacidad').value = sp.capacidad;
        document.getElementById('edit_acceso_tipo').value = sp.acceso_tipo || 'General';
        document.getElementById('edit_division_restringida').value = sp.division_restringida || '';
        toggleDivisionSelect('edit');
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
