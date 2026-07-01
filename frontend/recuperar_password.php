<?php
/**
 * @file recuperar_password.php
 * @summary Interfaz gráfica unificada para la recuperación y restablecimiento de contraseñas.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

session_start();
$token = $_GET['token'] ?? null;
?>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT | Recuperar Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #1E335F;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
        }

        .login-card {
            width: 420px;
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
        }

        .logo {
            text-align: center;
            margin-bottom: 15px;
        }

        .logo img {
            width: 110px;
        }

        .titulo {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #1E335F;
        }

        .subtitulo {
            text-align: center;
            color: #6c757d;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .btn-sigrat {
            background: #1E335F;
            color: white;
            border: none;
            border-radius: 15px;
            font-weight: 600;
        }

        .btn-sigrat:hover {
            background: #17284b;
            color: white;
        }

        .form-control {
            border-radius: 10px;
        }

        .input-group-text {
            cursor: pointer;
            background: white;
        }

        .volver {
            text-align: center;
            margin-top: 15px;
        }

        .volver a {
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
        }

        .volver a:hover {
            text-decoration: underline;
        }

        /* Utilidad para ocultar sin Display:none */
        .hidden {
            display: none !important;
        }
    </style>
</head>


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
<body>

<div class="login-card">
    <div class="logo">
        <img src="assets/images/sigrat_logo.png" alt="SIGRAT">
    </div>

    <?php if (!$token): ?>
    <!-- MODO 1: SOLICITAR ENLACE -->
    <div class="titulo">RECUPERAR CONTRASEÑA</div>
    <div class="subtitulo">Ingresa tu correo institucional para recibir un enlace de recuperación.</div>

    <form id="formRequestReset">
        <div class="mb-3">
            <label class="form-label" style="font-weight: 600;">Correo Electrónico</label>
            <input type="email" id="correo" class="form-control" placeholder="ejemplo@uteq.edu.mx" required>
        </div>

        <div id="msgRequest" class="alert d-none"></div>

        <div class="d-grid mt-4">
            <button type="submit" id="btnRequest" class="btn btn-sigrat">
                ENVIAR ENLACE
            </button>
        </div>
    </form>
    <?php else: ?>
    <!-- MODO 2: RESTABLECER CONTRASEÑA -->
    <div class="titulo">NUEVA CONTRASEÑA</div>
    <div class="subtitulo">Ingresa una nueva contraseña para tu cuenta.</div>

    <form id="formResetPassword">
        <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <div class="mb-3">
            <label class="form-label" style="font-weight: 600;">Nueva Contraseña</label>
            <div class="input-group">
                <input type="password" id="newPassword" class="form-control" placeholder="Contraseña" required>
                <span class="input-group-text" onclick="togglePassword('newPassword', this)">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
            <small class="text-muted" style="font-size: 12px;">Mínimo 8 caracteres, mayúscula, número y especial.</small>
        </div>

        <div class="mb-3">
            <label class="form-label" style="font-weight: 600;">Confirmar Contraseña</label>
            <div class="input-group">
                <input type="password" id="confirmPassword" class="form-control" placeholder="Confirmar" required>
                <span class="input-group-text" onclick="togglePassword('confirmPassword', this)">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
        </div>

        <div id="msgReset" class="alert d-none"></div>

        <div class="d-grid mt-4">
            <button type="submit" id="btnReset" class="btn btn-sigrat">
                GUARDAR CONTRASEÑA
            </button>
        </div>
    </form>
    <?php endif; ?>

    <div class="volver">
        <a href="iniciar_sesion.php"><i class="bi bi-arrow-left"></i> Volver al inicio de sesión</a>
    </div>
</div>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<script>
// Función para mostrar/ocultar contraseñas
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector("i");
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
    }
}

// Lógica para Solicitar Enlace
const formRequest = document.getElementById('formRequestReset');
if (formRequest) {
    formRequest.addEventListener('submit', function(e) {
        e.preventDefault();
        const correo = document.getElementById('correo').value;
        const btn = document.getElementById('btnRequest');
        const msg = document.getElementById('msgRequest');

        // Validar correo institucional
        if (!correo.endsWith('@uteq.edu.mx')) {
            msg.className = "alert alert-danger";
            msg.innerHTML = "Debes ingresar un correo institucional @uteq.edu.mx";
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
        msg.className = "alert d-none";

        fetch('../backend/api/index.php/auth/forgot-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ correo: correo })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = 'ENVIAR ENLACE';
            msg.classList.remove('d-none');
            
            if (data.success || data.message.includes('recibirás un enlace')) {
                msg.className = "alert alert-success";
                msg.innerHTML = "<i class='bi bi-check-circle'></i> " + data.message;
                document.getElementById('correo').value = '';
            } else {
                msg.className = "alert alert-danger";
                msg.innerHTML = "<i class='bi bi-exclamation-triangle'></i> " + (data.message || 'Error desconocido.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = 'ENVIAR ENLACE';
            msg.className = "alert alert-danger";
            msg.innerHTML = "Error de conexión. Intente más tarde.";
        });
    });
}

// Lógica para Restablecer Contraseña
const formReset = document.getElementById('formResetPassword');
if (formReset) {
    formReset.addEventListener('submit', function(e) {
        e.preventDefault();
        const token = document.getElementById('token').value;
        const pass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;
        const btn = document.getElementById('btnReset');
        const msg = document.getElementById('msgReset');

        msg.className = "alert d-none";

        const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
        if(!regex.test(pass)){
            msg.className = "alert alert-warning";
            msg.innerHTML = "La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un carácter especial.";
            return;
        }

        if (pass !== confirmPass) {
            msg.className = "alert alert-warning";
            msg.innerHTML = "Las contraseñas no coinciden.";
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        fetch('../backend/api/index.php/auth/reset-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token, password: pass })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                msg.className = "alert alert-success";
                msg.innerHTML = "<i class='bi bi-check-circle'></i> " + data.message;
                btn.style.display = 'none';
                setTimeout(() => {
                    window.location.href = 'iniciar_sesion.php';
                }, 3000);
            } else {
                btn.disabled = false;
                btn.innerHTML = 'GUARDAR CONTRASEÑA';
                msg.className = "alert alert-danger";
                msg.innerHTML = "<i class='bi bi-exclamation-triangle'></i> " + (data.message || 'Error desconocido.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = 'GUARDAR CONTRASEÑA';
            msg.className = "alert alert-danger";
            msg.innerHTML = "Error de conexión. Intente más tarde.";
        });
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
