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
    </style>
</head>

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
        <button class="btn btn-guest">
            RESERVAR SIN CUENTA
        </button>
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
                            <input type="text" class="form-control" required minlength="3">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Institucional</label>
                            <input type="email" id="correoRegistro" class="form-control" placeholder="ejemplo@uteq.edu.mx" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número Telefónico</label>
                            <input type="tel" class="form-control" pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Carrera / Área</label>
                            <input type="text" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" id="passwordRegistro" class="form-control" required>
                            <small class="text-muted">Mínimo 8 caracteres, una mayúscula, un número y un carácter especial.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input type="password" id="confirmPassword" class="form-control" required>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function registrarUsuario(){
    let correo = document.getElementById("correoRegistro").value;
    let password = document.getElementById("passwordRegistro").value;
    let confirmPassword = document.getElementById("confirmPassword").value;

    if(!correo.endsWith("@uteq.edu.mx")){
        alert("Debes utilizar un correo institucional @uteq.edu.mx");
        return;
    }

    const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/;
    if(!regex.test(password)){
        alert("La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un carácter especial.");
        return;
    }

    if(password !== confirmPassword){
        alert("Las contraseñas no coinciden.");
        return;
    }

    alert("Usuario registrado correctamente.");
    document.getElementById("formRegistro").reset();
    
    var modal = bootstrap.Modal.getInstance(document.getElementById('registroModal'));
    if(modal) {
        modal.hide();
    }
}
</script>


</body>
</html>
