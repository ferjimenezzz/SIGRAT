<?php
/**
 * @file login.php
 * @summary Inicio de sesión real con validación de base de datos.
 */
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
            'permisos' => json_decode($user['permisos'], true)
        ];

        $token = $auth->generateJWT($payload);

        // Establecer Cookie Segura con ruta codificada (evita el error de espacios)
        setcookie('auth_token', $token, time() + (60 * 60 * 8), '/creaciones%20antigravity/Estadias/', '', false, true);

        // Población inmediata de sesión (Redundancia de seguridad)
        $_SESSION['us_id'] = $user['us_id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol_nombre'];
        $_SESSION['permisos'] = json_decode($user['permisos'], true);
        
        header("Location: /creaciones%20antigravity/Estadias/frontend/index.php");
        exit();
    } else {
        $error = "Credenciales incorrectas o usuario inactivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - SIGRAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e293b; --accent: #2563eb; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: white; width: 100%; max-width: 400px; padding: 48px; border-radius: 32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; }
        .logo-box { width: 48px; height: 48px; background: var(--accent); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; margin-bottom: 24px; }
        h1 { font-size: 28px; font-weight: 900; color: #1e293b; margin-bottom: 8px; letter-spacing: -1px; }
        p { color: #64748b; font-size: 14px; margin-bottom: 32px; }
        .input-group { margin-bottom: 20px; }
        label { display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; }
        input { width: 100%; border: 1px solid #e2e8f0; padding: 14px; border-radius: 12px; font-weight: 700; box-sizing: border-box; font-family: inherit; }
        button { width: 100%; background: var(--primary); color: white; border: none; padding: 16px; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.2s; }
        button:hover { background: #0f172a; transform: translateY(-2px); }
        .error { background: #fee2e2; color: #ef4444; padding: 12px; border-radius: 8px; font-size: 12px; font-weight: 700; margin-bottom: 24px; text-align: center; }
        .back { display: block; text-align: center; margin-top: 24px; color: #94a3b8; text-decoration: none; font-size: 12px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-box"><i data-lucide="shield-lock"></i></div>
        <h1>Acceso Institucional</h1>
        <p>Ingrese sus credenciales para acceder al panel de SIGRAT.</p>
        <?php if ($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>
        <form method="POST">
            <div class="input-group">
                <label>Correo Electrónico</label>
                <input type="email" name="correo" placeholder="nombre@institucion.edu" required>
            </div>
            <div class="input-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login">ENTRAR AL SISTEMA</button>
        </form>
        <a href="index.php" class="back"><i data-lucide="arrow-left" style="width: 12px; vertical-align: middle;"></i> VOLVER AL CALENDARIO</a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
