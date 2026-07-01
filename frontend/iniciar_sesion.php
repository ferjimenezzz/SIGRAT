<?php
/**
 * @file iniciar_sesion.php
 * @summary Inicio de sesión real con validación de base de datos.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

session_start();
require_once '../backend/config/Database.php';
require_once '../backend/controllers/AuthController.php';

use Controllers\AuthController;

$auth = new AuthController();
$error = null;

if (isset($_POST['login'])) {
    $db = Config\Database::getConnection();
    $correo = $_POST['correo'];
    $pass = $_POST['password'];

    $stmt = $db->prepare("SELECT u.*, r.nombre as rol_nombre, r.permisos 
                          FROM USUARIO u 
                          LEFT JOIN ROLES r ON u.rol_id = r.rol_id 
                          WHERE u.correo = ? AND u.estatus = 'Activo'");
    $stmt->execute([$correo]);
    $user = $stmt->fetch();

    if ($user && AuthController::verifyPassword($pass, $user['contrasena'])) {
        // Generar Payload para JWT
        $payload = [
            'us_id' => $user['us_id'],
            'nombre' => $user['nombre'],
            'rol' => $user['rol_nombre'],
            'carrera' => $user['carrera'],
            'genero' => $user['genero'],
            'permisos' => json_decode($user['permisos'], true)
        ];

        $token = $auth->generateJWT($payload);

        // Establecer Cookie Segura con ruta genérica
        setcookie('auth_token', $token, time() + (60 * 60 * 8), '/', '', false, true);

        // Población inmediata de sesión (Redundancia de seguridad)
        $_SESSION['us_id'] = $user['us_id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol_nombre'];
        $_SESSION['genero'] = $user['genero'];
        $_SESSION['permisos'] = json_decode($user['permisos'], true);
        $_SESSION['division'] = $user['carrera'];
        
        // Registrar última conexión en la BD
        $stmtUpdate = $db->prepare("UPDATE USUARIO SET ultima_conexion = NOW() WHERE us_id = ?");
        $stmtUpdate->execute([$user['us_id']]);

        header("Location: index.php");
        exit();
    } else {
        $error = "Credenciales incorrectas o usuario inactivo.";
    }
}
?>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT | Inicio de Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{
            background-color:#1E335F;
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            margin:0;
            font-family:'Segoe UI',sans-serif;
        }

        .login-card{
            width:420px;
            background:#fff;
            border-radius:15px;
            padding:30px;
            box-shadow:0 10px 30px rgba(0,0,0,.25);
        }

        .logo{
            text-align:center;
            margin-bottom:15px;
        }

        .logo img{
            width:110px;
        }

        .titulo{
            text-align:center;
            font-size:22px;
            font-weight:bold;
            color:#1E335F;
        }

        .subtitulo{
            text-align:center;
            color:#6c757d;
            margin-bottom:25px;
            font-size:14px;
        }

        .btn-sigrat{
            background:#1E335F;
            color:white;
            border:none;
            border-radius:15px;
            font-weight:600;
        }

        .btn-sigrat:hover{
            background:#17284b;
            color:white;
        }

        .form-control{
            border-radius:10px;
        }

        .input-group-text{
            cursor:pointer;
            background:white;
        }

        .small-link{
            font-size:13px;
            text-decoration:none;
        }

        .small-link:hover{
            text-decoration:underline;
        }

        .volver{
            text-align:center;
            margin-top:15px;
        }

        .volver a{
            color:#6c757d;
            text-decoration:none;
        }

        .volver a:hover{
            text-decoration:underline;
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

    <div class="titulo">
        INICIO DE SESIÓN
    </div>

    <div class="subtitulo">
        Sistema Integral de Gestión de Reservaciones y Activos Tecnológicos
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" id="formLogin">
        <div class="mb-3">
            <label class="form-label">Correo Electrónico</label>
            <input type="email" name="correo" class="form-control" id="correo" placeholder="ejemplo@uteq.edu.mx" required>
            <div class="invalid-feedback">Ingresa un correo institucional válido.</div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label class="form-label">Contraseña</label>
                <a href="recuperar_password.php" class="small-link">¿Olvidaste tu contraseña?</a>
            </div>
            <div class="input-group">
                <input type="password" name="password" class="form-control" id="password" placeholder="Contraseña" required>
                <span class="input-group-text" onclick="mostrarPassword()">
                    <i class="bi bi-eye" id="iconPassword"></i>
                </span>
            </div>
            <div class="invalid-feedback">Ingresa tu contraseña.</div>
        </div>

        <div id="mensajeError" class="alert alert-danger d-none"></div>

        <div class="d-grid">
            <button type="submit" name="login" class="btn btn-sigrat">INICIAR SESIÓN</button>
        </div>
    </form>

    <div class="volver">
        <a href="login.php">Volver al inicio</a>
    </div>
</div>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<script>
function mostrarPassword(){
    const input = document.getElementById("password");
    const icon = document.getElementById("iconPassword");

    if(input.type === "password"){
        input.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
    }else{
        input.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
    }
}

document.getElementById("formLogin").addEventListener("submit", function(e){
    const correo = document.getElementById("correo");
    const password = document.getElementById("password");
    const mensaje = document.getElementById("mensajeError");

    mensaje.classList.add("d-none");
    correo.classList.remove("is-invalid");
    password.classList.remove("is-invalid");

    // Validar correo institucional
    if(!correo.value.endsWith("@uteq.edu.mx")){
        e.preventDefault();
        correo.classList.add("is-invalid");
        mensaje.innerHTML = "Debes ingresar un correo institucional @uteq.edu.mx";
        mensaje.classList.remove("d-none");
        return;
    }

    // Validar contraseña
    if(password.value.length < 8){
        e.preventDefault();
        password.classList.add("is-invalid");
        mensaje.innerHTML = "La contraseña debe contener al menos 8 caracteres.";
        mensaje.classList.remove("d-none");
        return;
    }

    // El formulario procederá normalmente a enviar el POST al servidor
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
