<?php
/**
 * @file login.php
 * @summary Pantalla e interfaz de inicio de sesión de usuario en SIGRAT.
 * @description Renderiza el formulario de autenticación con diseño moderno (Glassmorphism / Material-inspired), maneja la validación de campos, comunicación con la API de autenticación y redirección al panel principal tras un login exitoso.
 * @package Frontend\Views
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

?>


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT - Inicio</title>

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
            font-family:'Segoe UI', sans-serif;
        }

        .card-sigrat{
            width:450px;
            background:#ffffff;
            border-radius:15px;
            padding:30px;
            box-shadow:0 10px 30px rgba(0,0,0,.25);
        }

        .logos{
            display:flex;
            justify-content:center;
            align-items:center;
            gap:30px;
            margin-bottom:25px;
        }

        .logos img{
            max-height:90px;
            object-fit:contain;
        }

        .btn-login{
            background:#1E335F;
            color:white;
            font-weight:600;
            border-radius:15px;
        }

        .btn-login:hover{
            background:#17284b;
            color:white;
        }

        .btn-register{
            background:#4c6fff;
            color:white;
            font-weight:600;
            border:none;
            border-radius:15px;
        }

        .btn-register:hover{
            background:#3659e7;
            color:white;
        }

        .btn-guest{
            background:#8b8b8b;
            color:white;
            font-weight:600;
            border:none;
            border-radius:15px;
        }

        .btn-guest:hover{
            background:#6f6f6f;
            color:white;
        }

        .title-modal{
            color:#1E335F;
            font-weight:bold;
        }

        .form-label{
            font-weight:600;
        }

        .modal-header{
            background:#1E335F;
            color:white;
        }

        .btn-close{
            filter:invert(1);
        }

        .form-select{
    border-radius: 8px;
    height: 38px;
}
    </style>
</head>


<!-- ============================================================================ -->
<!-- SECCIÓN 3: COMPONENTES OPERATIVOS E INTERFAZ DE USUARIO -->
<!-- ============================================================================ -->
<body>

<div class="card-sigrat">
    <div class="logos">
        <img src="assets/images/logo_uteq.png" alt="UTEQ">
        <img src="assets/images/sigrat_logo.png" alt="SIGRAT">
    </div>

    <div class="d-grid gap-3">
        <!-- login -->
        <a href="iniciar_sesion.php" class="btn btn-login text-center" style="text-decoration:none;">
            INICIAR SESIÓN
        </a>

        <!--registro-->
        <button class="btn btn-register" data-bs-toggle="modal" data-bs-target="#registroModal">
            REGISTRARSE
        </button>

        <!--reserva sin cuenta-->
        <a href="externo.php" class="btn btn-guest text-center" style="text-decoration:none;">
            RESERVAR SIN CUENTA
        </a>
    </div>
</div>


<!--modal de registro-->
<div class="modal fade" id="registroModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registro de Usuario SIGRAT</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formRegistro">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" id="nombreRegistro" class="form-control" required minlength="3">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Institucional</label>
                            <input type="email" id="correoRegistro" class="form-control" placeholder="ejemplo@uteq.edu.mx" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número Telefónico</label>
                            <input type="tel" id="telefonoRegistro" class="form-control" pattern="[0-9]{10}" maxlength="10" required>
                        </div>

                        <div class="col-md-6 mb-3">
    <label class="form-label">División / Área</label>
    <select id="carreraRegistro" class="form-select" required>
        <option value="" selected disabled>Seleccione una opción</option>

        <optgroup label="Divisiones">
            <option value="División Económico - Administrativa">División Económico - Administrativa</option>
            <option value="División de Tecnologías de Automatización e Información">División de Tecnologías de Automatización e Información</option>
            <option value="División Industrial">División Industrial</option>
            <option value="División de Tecnología Ambiental">División de Tecnología Ambiental</option>
            <option value="División de Idiomas">División de Idiomas</option>
        </optgroup>

        <optgroup label="Áreas Administrativas">
            <option value="Docente">Docente</option>
            <option value="Dirección Académica">Dirección Académica</option>
            <option value="Servicios Escolares">Servicios Escolares</option>
            <option value="Recursos Materiales">Recursos Materiales</option>
            <option value="TI">Tecnologías de la Información</option>
            <option value="Biblioteca">Biblioteca</option>
            <option value="Administración">Administración</option>
            <option value="Otro">Otro</option>
        </optgroup>
    </select>
</div>
                    </div>
                    
                       <div class="row">

    <div class="col-md-6 mb-3">
        <label class="form-label">Contraseña</label>

        <div class="input-group">
            <input type="password"
                   id="passwordRegistro"
                   class="form-control"
                   required>

            <button class="btn btn-outline-secondary"
                    type="button"
                    onclick="togglePassword('passwordRegistro', this)">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <small class="text-muted">
            Mínimo 8 caracteres
        </small>
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Confirmar Contraseña</label>

        <div class="input-group">
            <input type="password"
                   id="confirmPassword"
                   class="form-control"
                   required>

            <button class="btn btn-outline-secondary"
                    type="button"
                    onclick="togglePassword('confirmPassword', this)">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

</div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="registrarUsuario()">Registrarme</button>
            </div>
        </div>
    </div>
</div>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function registrarUsuario(){
    let nombre = document.getElementById("nombreRegistro").value;
    let correo = document.getElementById("correoRegistro").value;
    let telefono = document.getElementById("telefonoRegistro").value;
    let carrera = document.getElementById("carreraRegistro").value;
    let password = document.getElementById("passwordRegistro").value;
    let confirmPassword = document.getElementById("confirmPassword").value;

    if(!nombre || !correo || !telefono || !carrera || !password) {
        alert("Por favor, completa todos los campos.");
        return;
    }

    if(!correo.endsWith("@uteq.edu.mx")){
        alert("Debes utilizar un correo institucional @uteq.edu.mx");
        return;
    }

    const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
    if(!regex.test(password)){
        alert("La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un carácter especial.");
        return;
    }

    if(password !== confirmPassword){
        alert("Las contraseñas no coinciden.");
        return;
    }

    fetch("../backend/api/index.php/auth/register", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            nombre: nombre,
            correo: correo,
            telefono: telefono,
            carrera: carrera,
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert(data.message);
            document.getElementById("formRegistro").reset();
            var modal = bootstrap.Modal.getInstance(document.getElementById('registroModal'));
            if(modal) {
                modal.hide();
            }
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        console.error("Error al registrar:", error);
        alert("Hubo un error al intentar registrar el usuario.");
    });

}

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
</script>


</body>
</html>
