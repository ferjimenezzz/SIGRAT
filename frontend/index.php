<?php
/**
 * @file index.php
 * @summary Punto de entrada principal del sistema SIGRAT.
 * @description Gestión de Dashboard Administrativo y Portal Público de Invitados.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../backend/controllers/CalendarController.php';
require_once '../backend/controllers/SpaceController.php';
require_once '../backend/controllers/AuditController.php';
require_once '../backend/controllers/DashboardController.php';
require_once '../backend/controllers/InviteController.php';

$calController = new Controllers\CalendarController();
$spaceController = new Controllers\SpaceController();
$auditController = new Controllers\AuditController();
$dashController = new Controllers\DashboardController();
$inviteController = new Controllers\InviteController();

$edificio = $_GET['edificio'] ?? null;
$esp_id = $_GET['esp_id'] ?? null;
$eventos = $calController->getEvents($edificio, $esp_id);
$espacios = $spaceController->getAll();

// Si el usuario está logueado, incluimos el header administrativo y el dashboard
if (isset($_SESSION['us_id'])) {
    include 'header.php';
    
    try {
        $recentLogs = $auditController->getGeneralActivity([]); 
        $stats = $dashController->getStats();
        $usage = $dashController->getSpaceUsage();
        $resByDay = $dashController->getReservationsByDay();
        $visitStats = $dashController->getVisitsStats();

        // Nuevos datos para el dashboard rediseñado
        $rango = $_GET['rango'] ?? 'semana';
        $classroomUsageStats = $dashController->getClassroomUsageStats();
        $rfidAccessStats = $dashController->getRFIDAccessStats();
        $loanStats = $dashController->getLoanStats();
        $spaceUsageByName = $dashController->getSpaceUsageByName($rango);
        $inventoryStatus = $dashController->getInventoryStatus();
        $todayReservations = $dashController->getTodayReservations();
    } catch (Exception $e) {
        $stats = ['reservas_hoy' => 0, 'reservas_growth' => 0, 'activos_uso' => 0, 'alertas_stock' => 0, 'incidentes' => 0];
        $usage = ['CIC' => 0, 'PIDET' => 0];
        $recentLogs = [];
        $resByDay = [];
        $visitStats = [];
        $classroomUsageStats = ['current' => 0, 'growth' => 0];
        $rfidAccessStats = ['current' => 0, 'growth' => 0];
        $loanStats = ['current' => 0, 'growth' => 0];
        $spaceUsageByName = [];
        $inventoryStatus = [];
        $todayReservations = [];
    }

    // Calcular total de inventario
    $inventoryTotal = 0;
    foreach ($inventoryStatus as $item) {
        $inventoryTotal += $item['total'];
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* ==================== STAT CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px 16px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.pink { background: #fce4ec; color: #e91e63; }
        .stat-icon.blue { background: #e3f2fd; color: #2563eb; }
        .stat-icon.green { background: #e8f5e9; color: #2e7d32; }
        .stat-icon.amber { background: #fff8e1; color: #f9a825; }

        .stat-content {
            flex: 1;
        }

        .stat-title {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 4px;
            letter-spacing: -1px;
        }

        .stat-subtitle {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
        }

        .stat-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
        }

        .stat-change {
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .stat-change.up { color: #10b981; }
        .stat-change.down { color: #ef4444; }

        .stat-sparkline {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 24px;
        }

        .stat-sparkline .bar {
            width: 4px;
            border-radius: 2px;
            transition: height 0.3s ease;
        }

        /* ==================== CHARTS SECTION ==================== */
        .charts-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }

        .chart-dropdown {
            padding: 6px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            cursor: pointer;
            font-family: inherit;
        }

        /* Donut center text */
        .donut-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .donut-center-text {
            position: absolute;
            text-align: center;
            pointer-events: none;
        }

        .donut-center-text .donut-value {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
        }

        .donut-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-left: 20px;
        }

        .donut-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }

        .donut-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            min-width: 10px;
        }

        .donut-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* ==================== RESERVATIONS LIST ==================== */
        .reservations-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            max-height: 200px;
            overflow-y: auto;
        }

        /* Ocultar barra de scroll interna en webkit para que se vea limpio pero siga siendo scrolleable */
        .reservations-card::-webkit-scrollbar {
            width: 6px;
        }
        .reservations-card::-webkit-scrollbar-track {
            background: transparent;
        }
        .reservations-card::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 10px;
        }

        .reservations-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .reservations-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }

        .reservations-link {
            font-size: 13px;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
        }

        .reservations-link:hover {
            text-decoration: underline;
        }

        .reservation-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .reservation-item:last-child {
            border-bottom: none;
        }

        .res-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .res-icon.aula { background: #ede9fe; color: #7c3aed; }
        .res-icon.lab { background: #fce4ec; color: #e91e63; }
        .res-icon.auditorio { background: #e3f2fd; color: #2563eb; }
        .res-icon.sala { background: #fff8e1; color: #f9a825; }
        .res-icon.default { background: #f1f5f9; color: #64748b; }

        .res-time {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            min-width: 100px;
        }

        .res-info {
            flex: 1;
        }

        .res-space-name {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        .res-description {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }

        .res-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .res-badge.confirmed { background: #d1fae5; color: #059669; }
        .res-badge.pending { background: #fef3c7; color: #d97706; }
        .res-badge.rejected { background: #fee2e2; color: #dc2626; }

        .res-menu-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            font-size: 18px;
        }

        .res-menu-btn:hover {
            background: #f1f5f9;
            color: #64748b;
        }

        .res-menu-container {
            position: relative;
        }

        .res-dropdown-menu {
            position: absolute;
            right: 34px; /* Aparece justo a la izquierda del botón */
            top: 50%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-50%) translateX(10px);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: row; /* Horizontal */
            padding: 4px 6px;
            gap: 2px;
        }

        .res-menu-container:hover .res-dropdown-menu,
        .res-menu-container:focus-within .res-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) translateX(0);
        }

        .res-dropdown-item {
            padding: 6px 10px;
            font-size: 13px;
            color: #475569;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            border-radius: 6px;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .res-dropdown-item:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .res-dropdown-item.danger {
            color: #ef4444;
        }

        .res-dropdown-item.danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 12px;
            display: block;
        }

        .empty-state p {
            font-size: 14px;
            font-weight: 600;
        }
    </style>

    <!-- ==================== STAT CARDS ==================== -->
    <div class="stats-grid">
        <!-- Aulas más utilizadas -->
        <div class="stat-card">
            <div class="stat-icon pink">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-title">Aulas más utilizadas</div>
                <div class="stat-value"><?php echo $classroomUsageStats['current']; ?>%</div>
                <div class="stat-subtitle">Uso promedio</div>
                <div class="stat-footer">
                    <span class="stat-change <?php echo $classroomUsageStats['growth'] >= 0 ? 'up' : 'down'; ?>">
                        <i class="bi bi-arrow-<?php echo $classroomUsageStats['growth'] >= 0 ? 'up' : 'down'; ?>-short"></i> <?php echo abs($classroomUsageStats['growth']); ?>% vs semana pasada
                    </span>
                    <div class="stat-sparkline">
                        <div class="bar" style="height: 10px; background: #fce4ec;"></div>
                        <div class="bar" style="height: 16px; background: #f48fb1;"></div>
                        <div class="bar" style="height: 12px; background: #fce4ec;"></div>
                        <div class="bar" style="height: 20px; background: #e91e63;"></div>
                        <div class="bar" style="height: 14px; background: #f48fb1;"></div>
                        <div class="bar" style="height: 24px; background: #e91e63;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reservaciones activas -->
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="bi bi-clipboard2-check-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-title">Reservaciones activas</div>
                <div class="stat-value"><?php echo $stats['reservas_hoy']; ?></div>
                <div class="stat-subtitle">Hoy</div>
                <div class="stat-footer">
                    <span class="stat-change <?php echo $stats['reservas_growth'] >= 0 ? 'up' : 'down'; ?>">
                        <i class="bi bi-arrow-<?php echo $stats['reservas_growth'] >= 0 ? 'up' : 'down'; ?>-short"></i> <?php echo abs($stats['reservas_growth']); ?>% vs ayer
                    </span>
                    <div class="stat-sparkline">
                        <div class="bar" style="height: 14px; background: #e3f2fd;"></div>
                        <div class="bar" style="height: 20px; background: #90caf9;"></div>
                        <div class="bar" style="height: 10px; background: #e3f2fd;"></div>
                        <div class="bar" style="height: 18px; background: #2563eb;"></div>
                        <div class="bar" style="height: 24px; background: #2563eb;"></div>
                        <div class="bar" style="height: 16px; background: #90caf9;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos RFID hoy -->
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="bi bi-wifi"></i>
            </div>
            <div class="stat-content">
                <div class="stat-title">Accesos RFID hoy</div>
                <div class="stat-value"><?php echo $rfidAccessStats['current']; ?></div>
                <div class="stat-subtitle">Entradas y salidas</div>
                <div class="stat-footer">
                    <span class="stat-change <?php echo $rfidAccessStats['growth'] >= 0 ? 'up' : 'down'; ?>">
                        <i class="bi bi-arrow-<?php echo $rfidAccessStats['growth'] >= 0 ? 'up' : 'down'; ?>-short"></i> <?php echo abs($rfidAccessStats['growth']); ?>% vs ayer
                    </span>
                    <div class="stat-sparkline">
                        <div class="bar" style="height: 8px; background: #e8f5e9;"></div>
                        <div class="bar" style="height: 18px; background: #66bb6a;"></div>
                        <div class="bar" style="height: 14px; background: #a5d6a7;"></div>
                        <div class="bar" style="height: 22px; background: #2e7d32;"></div>
                        <div class="bar" style="height: 12px; background: #a5d6a7;"></div>
                        <div class="bar" style="height: 20px; background: #2e7d32;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activos en préstamo -->
        <div class="stat-card">
            <div class="stat-icon amber">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-title">Activos en préstamo</div>
                <div class="stat-value"><?php echo $loanStats['current']; ?></div>
                <div class="stat-subtitle">Activos</div>
                <div class="stat-footer">
                    <span class="stat-change <?php echo $loanStats['growth'] >= 0 ? 'up' : 'down'; ?>">
                        <i class="bi bi-arrow-<?php echo $loanStats['growth'] >= 0 ? 'up' : 'down'; ?>-short"></i> <?php echo abs($loanStats['growth']); ?>% vs ayer
                    </span>
                    <div class="stat-sparkline">
                        <div class="bar" style="height: 12px; background: #fff8e1;"></div>
                        <div class="bar" style="height: 20px; background: #ffd54f;"></div>
                        <div class="bar" style="height: 16px; background: #fff8e1;"></div>
                        <div class="bar" style="height: 24px; background: #f9a825;"></div>
                        <div class="bar" style="height: 10px; background: #ffd54f;"></div>
                        <div class="bar" style="height: 18px; background: #f9a825;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MAIN DASHBOARD LAYOUT ==================== -->
    
    <!-- ROW 1: CHARTS -->
    <div class="charts-grid" style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; margin-bottom: 24px; align-items: stretch;">
        <!-- Bar Chart: Espacios más utilizados -->
        <div class="chart-card" style="display: flex; flex-direction: column; min-height: 350px;">
            <div class="chart-header">
                <h3 class="chart-title">Espacios más utilizados</h3>
                <select class="chart-dropdown" id="rangoChart" onchange="updateBarChart(this.value)">
                    <option value="semana" <?php echo $rango === 'semana' ? 'selected' : ''; ?>>Esta semana</option>
                    <option value="mes" <?php echo $rango === 'mes' ? 'selected' : ''; ?>>Este mes</option>
                    <option value="ano" <?php echo $rango === 'ano' ? 'selected' : ''; ?>>Este año</option>
                </select>
            </div>
            <div style="flex: 1; position: relative; width: 100%;">
                <canvas id="spacesBarChart"></canvas>
            </div>
        </div>

        <!-- Donut Chart: Estado de inventario -->
        <div class="chart-card donut-card" style="display: flex; flex-direction: column; min-height: 350px;">
            <div class="chart-header">
                <h3 class="chart-title">Estado de inventario</h3>
            </div>
            <div class="donut-container" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px;">
                <div class="donut-wrapper" style="width: 250px; height: 250px;">
                    <canvas id="inventoryDonut"></canvas>
                    <div class="donut-center-text">
                        <div class="donut-value"><?php echo number_format($inventoryTotal, 0, '.', ','); ?></div>
                    </div>
                </div>
                <div class="donut-legend">
                    <div class="donut-legend-item">
                        <div class="donut-legend-dot" style="background: #ef4444;"></div>
                        Disponibles
                    </div>
                    <div class="donut-legend-item">
                        <div class="donut-legend-dot" style="background: #f59e0b;"></div>
                        En préstamo
                    </div>
                    <div class="donut-legend-item">
                        <div class="donut-legend-dot" style="background: #2563eb;"></div>
                        En mantenimiento
                    </div>
                    <div class="donut-legend-item">
                        <div class="donut-legend-dot" style="background: #10b981;"></div>
                        Extraviados
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: RESERVATIONS FULL WIDTH -->
    <div class="reservations-card" style="width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 24px;">
        <div class="reservations-header">
            <h3 class="reservations-title">Reservaciones de hoy</h3>
            <a href="calendario.php" class="reservations-link">Ver todas</a>
        </div>

        <?php if (empty($todayReservations)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>No hay reservaciones programadas para hoy</p>
            </div>
        <?php else: ?>
            <?php foreach ($todayReservations as $res): ?>
                <?php
                    $iconClass = 'default';
                    $iconName = 'bi-building';
                    $tipoEsp = strtolower($res['tipo_espacio'] ?? '');
                    if (strpos($tipoEsp, 'aula') !== false) { $iconClass = 'aula'; $iconName = 'bi-mortarboard-fill'; }
                    elseif (strpos($tipoEsp, 'lab') !== false) { $iconClass = 'lab'; $iconName = 'bi-pc-display'; }
                    elseif (strpos($tipoEsp, 'audit') !== false) { $iconClass = 'auditorio'; $iconName = 'bi-display'; }
                    elseif (strpos($tipoEsp, 'sala') !== false) { $iconClass = 'sala'; $iconName = 'bi-people-fill'; }

                    $estatus = strtolower($res['estatus'] ?? 'pendiente');
                    $badgeClass = 'pending';
                    $badgeText = 'Pendiente';
                    if (strpos($estatus, 'aprob') !== false || strpos($estatus, 'confirm') !== false) { 
                        $badgeClass = 'confirmed'; $badgeText = 'Confirmada'; 
                    } elseif (strpos($estatus, 'rechaz') !== false) { 
                        $badgeClass = 'rejected'; $badgeText = 'Rechazada'; 
                    }

                    $horaEnt = substr($res['hora_ent'], 0, 5);
                    $horaSal = substr($res['hora_sal'], 0, 5);
                ?>
                <div class="reservation-item">
                    <div class="res-icon <?php echo $iconClass; ?>">
                        <i class="bi <?php echo $iconName; ?>"></i>
                    </div>
                    <div class="res-time"><?php echo $horaEnt; ?> - <?php echo $horaSal; ?></div>
                    <div class="res-info">
                        <div class="res-space-name"><?php echo htmlspecialchars($res['nombre_numero']); ?></div>
                        <div class="res-description"><?php echo htmlspecialchars($res['solicitante']); ?></div>
                    </div>
                    <span class="res-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    // ==================== BAR CHART: Espacios más utilizados ====================
    let spaceDataRaw = <?php echo json_encode($spaceUsageByName); ?>;
    
    function processBarData(rawData) {
        const labels = rawData.map(d => {
            let name = d.nombre_numero;
            if(name.includes('(')) name = name.split('(')[0].trim();
            return name.length > 18 ? name.substring(0, 15) + '...' : name;
        });
        const values = rawData.map(d => parseInt(d.total_reservas));
        const colors = values.map((_, i) => i % 2 === 0 ? '#2563eb' : '#93c5fd');
        
        return {
            labels: labels.length ? labels : ['Sin datos'],
            values: values.length ? values : [0],
            colors: colors.length ? colors : ['#e2e8f0']
        };
    }

    let barChartData = processBarData(spaceDataRaw);
    const ctxBar = document.getElementById('spacesBarChart').getContext('2d');
    let spacesBarChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: barChartData.labels,
            datasets: [{
                label: 'Reservaciones',
                data: barChartData.values,
                backgroundColor: barChartData.colors,
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        precision: 0,
                        font: { family: "'Inter', sans-serif", size: 11, weight: 500 },
                        color: '#94a3b8'
                    },
                    grid: { color: '#f1f5f9', drawBorder: false }
                },
                x: {
                    ticks: { 
                        font: { family: "'Inter', sans-serif", size: 10, weight: 600 },
                        color: '#64748b',
                        maxRotation: 45,
                        minRotation: 0
                    },
                    grid: { display: false }
                }
            }
        }
    });

    // Función asíncrona para actualizar la gráfica de barras sin recargar
    async function updateBarChart(rango) {
        try {
            const response = await fetch(`../backend/api/index.php/dashboard?rango=${rango}`);
            const data = await response.json();
            
            if (data && Array.isArray(data)) {
                let newData = processBarData(data);
                spacesBarChart.data.labels = newData.labels;
                spacesBarChart.data.datasets[0].data = newData.values;
                spacesBarChart.data.datasets[0].backgroundColor = newData.colors;
                spacesBarChart.update();
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
        }
    }

    // ==================== DONUT CHART: Estado de inventario ====================
    const invDataRaw = <?php echo json_encode($inventoryStatus); ?>;
    const invLabels = invDataRaw.map(d => d.estatus);
    const invValues = invDataRaw.map(d => parseInt(d.total));

    // Color mapping for statuses
    const statusColorMap = {
        'Disponible': '#ef4444',
        'En préstamo': '#f59e0b',
        'Préstamo': '#f59e0b',
        'Activo': '#f59e0b',
        'Mantenimiento': '#2563eb',
        'En mantenimiento': '#2563eb',
        'Extraviado': '#10b981',
        'Dañado': '#8b5cf6'
    };

    const invColors = invLabels.map(label => statusColorMap[label] || '#cbd5e1');

    const ctxDonut = document.getElementById('inventoryDonut').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: invLabels.length ? invLabels : ['Sin datos'],
            datasets: [{
                data: invValues.length ? invValues : [1],
                backgroundColor: invColors.length ? invColors : ['#e2e8f0'],
                borderWidth: 0,
                spacing: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false }
            }
        }
    });
    </script>

    </div>
    <?php include 'footer.php'; ?>
    <?php
} else {
    // Redirigir a login.php (contiene iniciar sesión, registrarse, reservar sin cuenta)
    header("Location: login.php");
    exit();
}
