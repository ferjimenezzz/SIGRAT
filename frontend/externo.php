<?php
/**
 * @file externo.php
 * @summary Portal público para visitas externas.
 * @description Permite a los externos validar su código de invitación y realizar una reservación.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/InviteController.php';
require_once '../backend/controllers/ReservationController.php';
require_once '../backend/controllers/SpaceController.php';

$inviteController = new Controllers\InviteController();
$resController = new Controllers\ReservationController();
$spaceController = new Controllers\SpaceController();

$step = 1; // 1: Validar Código, 2: Realizar Reserva
$visitor = null;
$error = null;

// Procesar Validación de Código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate') {
    $visitor = $inviteController->validate($_POST['codigo']);
    if ($visitor) {
        $step = 2;
    } else {
        $error = "Código inválido, expirado o ya utilizado.";
    }
}

// Procesar Reservación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    $data = [
        'vis_id' => $_POST['vis_id'],
        'esp_id' => $_POST['esp_id'],
        'fecha_uso' => $_POST['fecha_uso'],
        'hora_ent' => $_POST['hora_ent'],
        'hora_sal' => $_POST['hora_sal'],
        'num_alumnos' => 1 // Visita individual
    ];
    $res = $resController->create($data);
    if ($res['success']) {
        $step = 3; // Éxito
    } else {
        $error = $res['error'];
        $step = 2;
        // Recuperar datos de la visita para re-renderizar paso 2
        $db = Config\Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM VISITA WHERE vis_id = ?");
        $stmt->execute([$_POST['vis_id']]);
        $visitor = $stmt->fetch();
    }
}

$spaces = $spaceController->getAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Externo - SIGRAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e293b; --accent: #2563eb; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { width: 100%; max-width: 440px; padding: 20px; }
        .card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        h1 { font-size: 28px; font-weight: 900; color: #1e293b; margin-bottom: 8px; letter-spacing: -1px; }
        p { color: #64748b; font-size: 14px; margin-bottom: 32px; }
        .input-group { margin-bottom: 20px; }
        label { display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; }
        input, select { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 12px; font-weight: 700; box-sizing: border-box; font-family: inherit; }
        button { width: 100%; background: var(--accent); color: white; border: none; padding: 16px; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer; transition: transform 0.2s; margin-top: 10px; }
        button:active { transform: scale(0.98); }
        .error { color: #ef4444; font-size: 12px; font-weight: 700; margin-bottom: 20px; text-align: center; }
        .success-icon { width: 64px; height: 64px; background: #10b981; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <?php if ($step === 1): ?>
            <h1>Portal de Visitas</h1>
            <p>Ingrese el código de invitación que le fue proporcionado por su anfitrión.</p>
            
            <?php if ($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="validate">
                <div class="input-group">
                    <label>Código de Acceso</label>
                    <input type="text" name="codigo" placeholder="Ej: ABC12345" required style="text-align: center; font-size: 20px; letter-spacing: 4px; text-transform: uppercase;">
                </div>
                <button type="submit">VALIDAR CÓDIGO</button>
            </form>

        <?php elseif ($step === 2): ?>
            <h1>Hola, <?php echo explode(' ', $visitor['nombre'])[0]; ?></h1>
            <p>Complete su solicitud de reservación para acceder a las instalaciones.</p>

            <?php if ($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="vis_id" value="<?php echo $visitor['vis_id']; ?>">
                
                <div class="input-group">
                    <label>Espacio solicitado</label>
                    <select name="esp_id" required>
                        <?php foreach ($spaces as $sp): ?>
                            <option value="<?php echo $sp['esp_id']; ?>"><?php echo $sp['edificio']; ?> - <?php echo $sp['nombre_numero']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Fecha de Uso</label>
                    <input type="date" name="fecha_uso" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="input-group">
                        <label>Entrada</label>
                        <input type="time" name="hora_ent" required>
                    </div>
                    <div class="input-group">
                        <label>Salida</label>
                        <input type="time" name="hora_sal" required>
                    </div>
                </div>

                <button type="submit">SOLICITAR RESERVA</button>
            </form>

        <?php elseif ($step === 3): ?>
            <div class="success-icon"><i data-lucide="check" style="width: 32px; height: 32px;"></i></div>
            <h1 style="text-align: center;">¡Solicitud Enviada!</h1>
            <p style="text-align: center;">Su reserva ha sido registrada y está en espera de aprobación por el administrador. Le llegará una notificación a su correo.</p>
            <button onclick="window.location.href='externo.php'" style="background: #f1f5f9; color: #1e293b;">CERRAR</button>
        <?php endif; ?>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>
