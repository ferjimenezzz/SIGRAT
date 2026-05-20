<?php
/**
 * @file invitado.php
 * @summary Portal público para usuarios externos (sin sidebar).
 * @description Permite a personas con código de invitación validar su acceso y reservar.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGRAT - Reservación de Invitados</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Outfit', sans-serif;
        }
        .guest-card {
            max-width: 400px;
            width: 90%;
            background: white;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .guest-header {
            background: #0c1e35;
            padding: 40px;
            text-align: center;
            color: white;
        }
        .guest-body {
            padding: 40px;
        }
        .input-group {
            margin-bottom: 24px;
        }
        .input-group label {
            display: block;
            font-size: 10px;
            font-weight: 900;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .input-field {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            padding: 16px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            outline: none;
            transition: all 0.2s;
        }
        .input-field:focus {
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        .btn-confirm {
            width: 100%;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 18px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body>
    <div class="guest-card">
        <div class="guest-header">
            <div style="background: #3b82f6; width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i data-lucide="shield-check" style="width: 32px; height: 32px;"></i>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; margin: 0;">SIGRAT Externo</h1>
            <p style="font-size: 13px; opacity: 0.6; font-weight: 500; margin-top: 8px;">Reservación de espacios con invitación</p>
        </div>

        <div class="guest-body">
            <div id="step-1">
                <div class="input-group">
                    <label>Código de Invitación</label>
                    <input type="text" class="input-field" style="text-transform: uppercase; letter-spacing: 2px;" placeholder="EJ: SIG-XXXX">
                </div>
                <button onclick="nextStep()" class="btn-confirm">
                    CONTINUAR <i data-lucide="arrow-right"></i>
                </button>
            </div>

            <div id="step-2" style="display: none;">
                <div class="input-group">
                    <label>Tu Nombre Completo</label>
                    <input type="text" class="input-field" placeholder="Nombre y Apellido">
                </div>
                <div style="background: #eff6ff; padding: 16px; border-radius: 16px; border: 1px solid #dbeafe; margin-bottom: 24px;">
                    <p style="font-size: 10px; font-weight: 900; color: #3b82f6; text-transform: uppercase; margin-bottom: 4px;">Detalles</p>
                    <p style="font-size: 12px; font-weight: 700; color: #1e3a8a; font-style: italic;">"Acceso verificado. Puede reservar por 120 min."</p>
                </div>
                <button onclick="finalizar()" class="btn-confirm">
                    CONFIRMAR RESERVA
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function nextStep() {
            document.getElementById('step-1').style.display = 'none';
            document.getElementById('step-2').style.display = 'block';
        }
        function finalizar() {
            alert("Reserva confirmada. ¡Bienvenido!");
            window.location.reload();
        }
    </script>
</body>
</html>
