<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Recuperar Contraseña - SIGRAT</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background-color:#1E335F;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    font-family:'Segoe UI',sans-serif;
}

.recovery-card{
    width:450px;
    background:white;
    border-radius:15px;
    padding:30px;
    box-shadow:0 10px 30px rgba(0,0,0,.25);
}

.logo{
    text-align:center;
    margin-bottom:15px;
}

.logo img{
    width:120px;
}

.titulo{
    text-align:center;
    color:#1E335F;
    font-weight:bold;
    font-size:22px;
}

.subtitulo{
    text-align:center;
    color:#6c757d;
    margin-bottom:20px;
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

.volver-link{
    color:#6c757d;
    text-decoration:none;
    font-size:14px;
}

.volver-link:hover{
    text-decoration:underline;
}

</style>

</head>

<body>

<div class="recovery-card">

    <div class="logo">
        <img src="assets/images/sigrat_logo.png" alt="SIGRAT">
    </div>

    <div class="titulo">
        RECUPERAR CONTRASEÑA
    </div>

    <div class="subtitulo">
        Ingresa tu correo institucional para recuperar el acceso.
    </div>

    <form id="formRecuperar">

        <div class="mb-3">

            <label class="form-label">
                Correo Institucional
            </label>

            <input
                type="email"
                class="form-control"
                id="correo"
                placeholder="ejemplo@uteq.edu.mx"
                required>

        </div>

        <div
            id="mensajeError"
            class="alert alert-danger d-none">
        </div>

        <div
            id="mensajeExito"
            class="alert alert-success d-none">
        </div>

        <div class="d-grid">

            <button
                type="submit"
                class="btn btn-sigrat">

                ENVIAR ENLACE

            </button>

        </div>

    </form>

    <div class="text-center mt-3">

        <a href="iniciar_sesion.php" class="volver-link">
            Volver al inicio de sesión
        </a>

    </div>

</div>

<script>

document
.getElementById("formRecuperar")
.addEventListener("submit", function(e){

    e.preventDefault();

    const correo =
    document.getElementById("correo").value;

    const error =
    document.getElementById("mensajeError");

    const exito =
    document.getElementById("mensajeExito");

    error.classList.add("d-none");
    exito.classList.add("d-none");

    if(!correo.endsWith("@uteq.edu.mx")){

        error.innerHTML =
        "Debes ingresar un correo institucional @uteq.edu.mx";

        error.classList.remove("d-none");

        return;
    }

    exito.innerHTML =
    "Si el correo existe en el sistema, recibirás instrucciones para restablecer tu contraseña.";

    exito.classList.remove("d-none");

});

</script>

</body>
</html>
