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
        $recentLogs = $auditController->getFiltered(null, null, null); 
        $stats = $dashController->getStats();
        $usage = $dashController->getSpaceUsage();
    } catch (Exception $e) {
        $stats = ['reservas_hoy' => 0, 'activos_uso' => 0, 'alertas_stock' => 0, 'incidentes' => 0];
        $usage = ['CIC' => 0, 'PIDET' => 0];
        $recentLogs = [];
    }
    ?>
    <div style="display: flex; flex-direction: column; gap: 32px;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;">
            <a href="reservas.php" class="card" style="border-left: 4px solid #3b82f6; text-decoration: none;">
                <p style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin: 0;">Reservas Hoy</p>
                <h2 style="font-size: 28px; font-weight: 900; margin: 8px 0 0 0; color: #1e293b;"><?php echo $stats['reservas_hoy']; ?></h2>
            </a>
            <a href="enrolamiento.php" class="card" style="border-left: 4px solid #10b981; text-decoration: none;">
                <p style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin: 0;">Activos en Uso</p>
                <h2 style="font-size: 28px; font-weight: 900; margin: 8px 0 0 0; color: #1e293b;"><?php echo $stats['activos_uso']; ?></h2>
            </a>
            <a href="enrolamiento.php?filtro=alerta" class="card" style="border-left: 4px solid #f59e0b; text-decoration: none;">
                <p style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin: 0;">Alertas Stock</p>
                <h2 style="font-size: 28px; font-weight: 900; margin: 8px 0 0 0; color: #1e293b;"><?php echo $stats['alertas_stock']; ?></h2>
            </a>
            <a href="auditoria.php?modulo=SEGURIDAD" class="card" style="border-left: 4px solid #ef4444; text-decoration: none;">
                <p style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin: 0;">Incidentes</p>
                <h2 style="font-size: 28px; font-weight: 900; margin: 8px 0 0 0; color: #1e293b;"><?php echo $stats['incidentes']; ?></h2>
            </a>
        </div>

        <div class="card">
            <h3 style="font-size: 16px; font-weight: 800; color: #334155; margin-bottom: 24px;">Resumen de Ocupación</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                <?php foreach ($usage as $building => $count): ?>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 12px; font-weight: 800; color: #64748b;"><?php echo $building; ?></span>
                        <span style="font-size: 12px; font-weight: 900; color: #3b82f6;"><?php echo $count; ?> Reservas</span>
                    </div>
                    <div style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                        <div style="width: <?php echo ($count > 0 ? 50 : 0); ?>%; height: 100%; background: #3b82f6;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <?php
} else {
    // PORTAL PÚBLICO
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Portal de Invitados - SIGRAT</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <style>
            :root { --primary: #1e293b; --accent: #3b82f6; }
            body { font-family: 'Outfit', sans-serif; background: #f8fafc; margin: 0; padding: 0; color: var(--primary); }
            .top-nav { background: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 100; }
            .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
            .card { background: white; border-radius: 24px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 32px; }
            .badge-busy { background: #fee2e2; color: #ef4444; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 900; }
            .btn { background: var(--accent); color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        </style>
    </head>
    <body>
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="background: var(--accent); color: white; padding: 8px; border-radius: 10px;"><i data-lucide="shield-check"></i></div>
                <h1 style="font-size: 20px; font-weight: 900; margin: 0;">SIGRAT <span style="color: #94a3b8; font-weight: 400;">PÚBLICO</span></h1>
            </div>
            <a href="login.php" class="btn" style="background: #f1f5f9; color: #475569;">ACCESO ADMINISTRATIVO</a>
        </nav>

        <div class="container">
            <div class="card" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border: none;">
                <h2 style="font-size: 28px; font-weight: 900; margin-bottom: 8px;">¿Desea agendar un espacio?</h2>
                <p style="opacity: 0.7; margin-bottom: 24px;">Ingrese su código de invitación generado por un administrador para realizar una reserva.</p>
                <form action="reservas.php" method="GET" style="display: flex; gap: 12px;">
                    <input type="text" name="invite_code" placeholder="CÓDIGO DE ACCESO" style="padding: 12px 20px; border-radius: 12px; border: none; font-weight: 900; width: 200px; text-transform: uppercase;">
                    <button type="submit" class="btn">RESERVAR AHORA</button>
                </form>
            </div>

            <h3 style="font-weight: 900; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i data-lucide="calendar"></i> Disponibilidad de Áreas (Hoy)
            </h3>

            <form method="GET" class="filter-bar" style="display: flex; gap: 16px; margin-bottom: 32px;">
                <select name="edificio" onchange="this.form.submit()" style="padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; font-weight: 700;">
                    <option value="">Todos los Edificios</option>
                    <option value="CIC" <?php echo $edificio == 'CIC' ? 'selected' : ''; ?>>CIC</option>
                    <option value="PIDET" <?php echo $edificio == 'PIDET' ? 'selected' : ''; ?>>PIDET</option>
                </select>
            </form>

            <div class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Horario</th>
                            <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Área / Espacio</th>
                            <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Disponibilidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eventos)): ?>
                            <tr><td colspan="3" style="padding: 40px; text-align: center; color: #94a3b8; font-weight: 700;">TODAS LAS ÁREAS ESTÁN DISPONIBLES</td></tr>
                        <?php else: ?>
                            <?php foreach ($eventos as $ev): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 20px 24px; font-weight: 800;"><?php echo $ev['hora_ent']; ?> - <?php echo $ev['hora_sal']; ?></td>
                                <td style="padding: 20px 24px; font-size: 14px; font-weight: 700; color: #334155;">[<?php echo $ev['edificio']; ?>] <?php echo $ev['nombre_numero']; ?></td>
                                <td style="padding: 20px 24px;"><span class="badge-busy">OCUPADO</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>lucide.createIcons();</script>
    </body>
    </html>
    <?php
}
