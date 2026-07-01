<?php
/**
 * @file externo.php
 * @summary Portal público para visitas externas.
 * @description Permite a los externos validar su código de invitación y realizar una reservación.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

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


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Sin Cuenta - SIGRAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', sans-serif;
            background-color: #1E335F;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card-container {
            width: 100%;
            max-width: 460px;
        }

        .card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            position: relative;
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-section img {
            height: 65px;
            object-fit: contain;
        }

        /* Title Section */
        .title-section {
            text-align: center;
            margin-bottom: 28px;
        }

        .title-section h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .title-section .subtitle {
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Info Box */
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .info-box .info-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            background: #dbeafe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            font-size: 18px;
        }

        .info-box .info-text h3 {
            font-size: 14px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 4px;
        }

        .info-box .info-text p {
            font-size: 12.5px;
            color: #3b82f6;
            line-height: 1.5;
            margin: 0;
        }

        /* Form Label */
        .form-label-custom {
            display: block;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 12px;
        }

        /* Code Input Group */
        .code-input-group {
            position: relative;
            margin-bottom: 10px;
        }

        .code-input-group input {
            width: 100%;
            background: #1e293b;
            border: 2px solid #334155;
            color: #f1f5f9;
            padding: 16px 60px 16px 20px;
            border-radius: 14px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 6px;
            text-align: center;
            text-transform: uppercase;
            font-family: 'Courier New', monospace;
            transition: border-color 0.3s ease;
        }

        .code-input-group input::placeholder {
            color: #475569;
            letter-spacing: 4px;
            font-size: 14px;
        }

        .code-input-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .toggle-password {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: #334155;
            border: none;
            color: #94a3b8;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 18px;
        }

        .toggle-password:hover {
            background: #475569;
            color: #e2e8f0;
        }

        /* Hint Text */
        .hint-text {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .hint-text i {
            font-size: 14px;
        }

        /* Error Message */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #dc2626;
            font-size: 13px;
            font-weight: 600;
        }

        .error-message i {
            font-size: 18px;
            min-width: 18px;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
            text-transform: none;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #475569 0%, #334155 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(71, 85, 105, 0.35);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit i {
            font-size: 20px;
        }

        /* Blue accent button for step 2 */
        .btn-accent {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        .btn-accent:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }

        .login-link i {
            margin-right: 4px;
            font-size: 14px;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 700;
        }

        .login-link a:hover {
            text-decoration: underline;
            color: #1d4ed8;
        }

        /* ============ Step 2 Styles ============ */
        .welcome-section {
            text-align: center;
            margin-bottom: 28px;
        }

        .welcome-section .avatar {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            font-weight: 800;
            margin: 0 auto 14px auto;
        }

        .welcome-section h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .welcome-section p {
            font-size: 13px;
            color: #94a3b8;
        }

        .input-group-styled {
            margin-bottom: 18px;
        }

        .input-group-styled label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        .input-group-styled input,
        .input-group-styled select {
            width: 100%;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .input-group-styled input:focus,
        .input-group-styled select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .time-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* ============ Step 3 Success ============ */
        .success-section {
            text-align: center;
            padding: 20px 0;
        }

        .success-icon-wrapper {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px auto;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .success-icon-wrapper i {
            font-size: 36px;
            color: white;
        }

        .success-section h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 10px;
        }

        .success-section p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .btn-close-page {
            width: 100%;
            background: #f1f5f9;
            color: #1e293b;
            border: 2px solid #e2e8f0;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-close-page:hover {
            background: #e2e8f0;
        }
    </style>
</head>


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
<body>

<div class="card-container">
    <div class="card">

        <?php if ($step === 1): ?>
            <!-- ========== PASO 1: Validar Código ========== -->
            <div class="logo-section">
                <img src="assets/images/sigrat_logo.png" alt="SIGRAT">
            </div>

            <div class="title-section">
                <h1>Reservar Sin Cuenta</h1>
                <span class="subtitle">SIGRAT</span>
            </div>

            <div class="info-box">
                <div class="info-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="info-text">
                    <h3>Código de acceso requerido</h3>
                    <p>Ingresa el código que te proporcionó tu institución para continuar.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="validate">

                <label class="form-label-custom">Código de Acceso:</label>

                <div class="code-input-group">
                    <input type="password" name="codigo" id="codigoInput" placeholder="• • • • • • •" required autocomplete="off">
                    <button type="button" class="toggle-password" onclick="toggleCode()" id="toggleBtn" aria-label="Mostrar código">
                        <i class="bi bi-eye-fill" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="hint-text">
                    <i class="bi bi-info-circle"></i>
                    <span>El código fue entregado por el personal de tu área</span>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-calendar2-check"></i>
                    Continuar Con la Reservación
                </button>
            </form>

            <div class="login-link">
                <i class="bi bi-lock"></i>
                ¿Ya tienes cuenta? <a href="iniciar_sesion.php">Iniciar sesión aquí</a>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- ========== PASO 2: Realizar Reserva ========== -->
            <div class="logo-section">
                <img src="assets/images/sigrat_logo.png" alt="SIGRAT">
            </div>

            <div class="welcome-section">
                <div class="avatar"><?php echo strtoupper(substr($visitor['nombre'], 0, 1)); ?></div>
                <h1>Hola, <?php echo explode(' ', $visitor['nombre'])[0]; ?></h1>
                <p>Complete su solicitud de reservación para acceder a las instalaciones.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="vis_id" value="<?php echo $visitor['vis_id']; ?>">

                <div class="input-group-styled">
                    <label>Espacio solicitado</label>
                    <select name="esp_id" required>
                        <?php foreach ($spaces as $sp): ?>
                            <option value="<?php echo $sp['esp_id']; ?>"><?php echo $sp['edificio']; ?> - <?php echo $sp['nombre_numero']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group-styled">
                    <label>Fecha de Uso</label>
                    <input type="date" name="fecha_uso" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="time-grid">
                    <div class="input-group-styled">
                        <label>Entrada</label>
                        <input type="time" name="hora_ent" required>
                    </div>
                    <div class="input-group-styled">
                        <label>Salida</label>
                        <input type="time" name="hora_sal" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit btn-accent" style="margin-top: 10px;">
                    <i class="bi bi-send-fill"></i>
                    SOLICITAR RESERVA
                </button>
            </form>

            <div class="login-link">
                <i class="bi bi-arrow-left"></i>
                <a href="externo.php">Volver al inicio</a>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- ========== PASO 3: Éxito ========== -->
            <div class="success-section">
                <div class="success-icon-wrapper">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h1>¡Solicitud Enviada!</h1>
                <p>Su reserva ha sido registrada y está en espera de aprobación por el administrador. Le llegará una notificación a su correo.</p>
                <button class="btn-close-page" onclick="window.location.href='externo.php'">CERRAR</button>
            </div>

        <?php endif; ?>
    </div>
</div>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<script>
    function toggleCode() {
        const input = document.getElementById('codigoInput');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye-fill');
            icon.classList.add('bi-eye-slash-fill');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash-fill');
            icon.classList.add('bi-eye-fill');
        }
    }
</script>

</body>
</html>
