<?php
/**
 * @file perfil.php
 * @summary Interfaz de Mi Perfil - campos editables: nombre, teléfono, correo, organización.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

require_once 'seguridad.php';
require_once '../backend/config/Database.php';
require_once '../backend/controllers/AuthController.php';

$db = Config\Database::getConnection();
$us_id = $_SESSION['us_id'];

// Función para obtener datos frescos del usuario
function getUsuario($db, $us_id) {
    $stmt = $db->prepare("SELECT u.*, r.nombre as rol_nombre, r.permisos FROM USUARIO u LEFT JOIN ROLES r ON u.rol_id = r.rol_id WHERE u.us_id = ?");
    $stmt->execute([$us_id]);
    return $stmt->fetch();
}

$usuarioInfo = getUsuario($db, $us_id);

// Manejar POST (Actualizar Perfil)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nuevoNombre    = trim($_POST['nombre'] ?? '');
    $nuevoTelefono  = trim($_POST['telefono'] ?? '');
    $nuevoCorreo    = trim($_POST['correo'] ?? '');
    $nuevaArea      = trim($_POST['carrera'] ?? '');
    $nuevoGenero    = trim($_POST['genero'] ?? 'Masculino');

    $errores = [];
    if (empty($nuevoNombre))   $errores[] = "El nombre no puede estar vacío.";
    if (!filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) $errores[] = "El correo no tiene un formato válido.";
    if (!empty($nuevoTelefono) && (!is_numeric($nuevoTelefono) || strlen($nuevoTelefono) !== 10)) $errores[] = "El teléfono debe tener exactamente 10 dígitos.";

    // Verificar que el correo no esté en uso por otro usuario
    if (empty($errores) && $nuevoCorreo !== $usuarioInfo['correo']) {
        $chkStmt = $db->prepare("SELECT us_id FROM USUARIO WHERE correo = ? AND us_id != ?");
        $chkStmt->execute([$nuevoCorreo, $us_id]);
        if ($chkStmt->fetch()) {
            $errores[] = "Ese correo ya está en uso por otro usuario.";
        }
    }

    if (empty($errores)) {
        try {
            $updateStmt = $db->prepare("UPDATE USUARIO SET nombre = ?, telefono = ?, correo = ?, carrera = ?, genero = ? WHERE us_id = ?");
            $updateStmt->execute([$nuevoNombre, $nuevoTelefono, $nuevoCorreo, $nuevaArea, $nuevoGenero, $us_id]);

            // Actualizar sesión
            $_SESSION['nombre']   = $nuevoNombre;
            $_SESSION['division'] = $nuevaArea;
            $_SESSION['genero']   = $nuevoGenero;

            // Regenerar JWT con todos los campos actualizados
            $auth = new \Controllers\AuthController();
            $newPayload = [
                'us_id'    => $us_id,
                'nombre'   => $nuevoNombre,
                'rol'      => $_SESSION['rol'],
                'carrera'  => $nuevaArea,
                'genero'   => $nuevoGenero,
                'permisos' => json_decode($usuarioInfo['permisos'], true)
            ];
            $newToken = $auth->generateJWT($newPayload);
            setcookie('auth_token', $newToken, time() + (60 * 60 * 8), '/', '', false, true);

            // PRG: redirect para que el browser envíe la nueva cookie
            header("Location: perfil.php?ok=1");
            exit();
        } catch (Exception $e) {
            $error = "Error al actualizar perfil: " . $e->getMessage();
        }
    } else {
        $error = implode(' ', $errores);
    }
}

// Recargar datos frescos siempre
$usuarioInfo = getUsuario($db, $us_id);

// Intentar obtener la fecha de creación real (si existe el campo)
$fechaRegistro = 'Sin registrar';
if (!empty($usuarioInfo['fecha_registro'])) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($usuarioInfo['fecha_registro']);
    $fechaRegistro = date('j', $ts) . ' de ' . $meses[date('n', $ts)-1] . ' de ' . date('Y', $ts);
} elseif (!empty($usuarioInfo['ultima_conexion'])) {
    // Fallback: usar ultima_conexion
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($usuarioInfo['ultima_conexion']);
    $fechaRegistro = date('j', $ts) . ' de ' . $meses[date('n', $ts)-1] . ' de ' . date('Y', $ts);
}

include 'header.php';
?>

<!-- Cabecera de Página -->
<div style="margin-left: -28px; margin-right: -28px; margin-top: -24px; margin-bottom: 28px; padding: 24px 32px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">Mi perfil</h1>
        <p style="font-size: 13px; color: #64748b; margin: 0;">Consulta y modifica tu información personal registrada en el sistema</p>
    </div>
</div>

<!-- Mensajes -->
<?php if (isset($_GET['ok'])): ?>
<div style="background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; padding: 12px 20px; border-radius: 10px; margin-bottom: 24px; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 10px;">
    <i class="bi bi-check-circle-fill" style="font-size: 16px;"></i> Perfil actualizado correctamente.
</div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div style="background: #fef2f2; border: 1px solid #f87171; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 10px;">
    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Layout Principal -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">

    <!-- Tarjeta Formulario -->
    <div class="card" style="padding: 32px;">
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 32px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #f3e8ff; color: #9333ea; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                <i class="bi bi-person"></i>
            </div>
            <div>
                <h3 style="font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 2px;">Información personal</h3>
                <p style="font-size: 12px; color: #64748b; font-weight: 500; margin: 0;">Estos son tus datos personales registrados en el sistema</p>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_profile">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px 20px;">

                <!-- Nombre -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Nombre completo</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuarioInfo['nombre']); ?>" class="form-control" style="background: white;" required>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">Puedes modificar tu nombre</div>
                </div>

                <!-- Correo -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Correo institucional</label>
                    <input type="email" name="correo" value="<?php echo htmlspecialchars($usuarioInfo['correo']); ?>" class="form-control" style="background: white;" required>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">Puedes modificar tu correo</div>
                </div>

                <!-- Género -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Género</label>
                    <select name="genero" class="form-control" style="background: white; padding: 10px 16px;">
                        <option value="Masculino" <?php echo (($usuarioInfo['genero'] ?? 'Masculino') === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Femenino" <?php echo (($usuarioInfo['genero'] ?? '') === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">Para tu saludo personalizado</div>
                </div>

                <!-- Teléfono -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Número telefónico</label>
                    <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuarioInfo['telefono'] ?? ''); ?>" class="form-control" style="background: white;" maxlength="10" pattern="\d{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">Puedes modificar tu teléfono (10 dígitos)</div>
                </div>

                <!-- Organización/Área -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Organización / Área</label>
                    <select name="carrera" class="form-control" style="background: white; appearance: auto; padding: 10px 16px;">
                        <option value="">Seleccionar área...</option>
                        <?php
                        $areas = [
                            'Divisiones' => [
                                'División Económico - Administrativa',
                                'División de Tecnologías de Automatización e Información',
                                'División Industrial',
                                'División de Tecnología Ambiental',
                                'División de Idiomas',
                            ],
                            'Áreas Administrativas' => [
                                'Docente',
                                'Dirección Académica',
                                'Servicios Escolares',
                                'Recursos Materiales',
                                'TI',
                                'Biblioteca',
                                'Administración',
                                'Otro',
                            ],
                        ];
                        foreach ($areas as $grupo => $opciones):
                        ?>
                        <optgroup label="<?php echo $grupo; ?>">
                            <?php foreach ($opciones as $op): ?>
                            <option value="<?php echo $op; ?>" <?php echo ($usuarioInfo['carrera'] === $op) ? 'selected' : ''; ?>>
                                <?php echo $op; ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">Puedes modificar tu área</div>
                </div>

                <!-- Rol (bloqueado) -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Rol de usuario</label>
                    <div style="position: relative;">
                        <input type="text" value="<?php echo htmlspecialchars($usuarioInfo['rol_nombre'] ?? 'Sin rol'); ?>" class="form-control" style="background: #f8fafc; color: #94a3b8; padding-right: 40px;" readonly>
                        <i class="bi bi-lock" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #cbd5e1; font-size: 15px;"></i>
                    </div>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">No se puede modificar</div>
                </div>

                <!-- Fecha Registro (bloqueado) -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: none;">Fecha de registro</label>
                    <div style="position: relative;">
                        <input type="text" value="<?php echo htmlspecialchars($fechaRegistro); ?>" class="form-control" style="background: #f8fafc; color: #94a3b8; padding-right: 40px;" readonly>
                        <i class="bi bi-lock" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #cbd5e1; font-size: 15px;"></i>
                    </div>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">No se puede modificar</div>
                </div>

            </div>

            <div style="margin-top: 36px; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn-primary" style="background: #2563eb; color: white; padding: 12px 32px; font-size: 14px; border-radius: 8px;">
                    <i class="bi bi-device-hdd"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>

    <!-- Tarjeta Info Derecha (Actualizada) -->
    <div style="background: #eff6ff; border-radius: 16px; padding: 24px; border: 1px solid #dbeafe;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <i class="bi bi-info-circle" style="color: #6366f1; font-size: 20px;"></i>
            <h4 style="font-size: 14px; font-weight: 700; color: #4338ca; margin: 0;">¿Qué puedo modificar?</h4>
        </div>

        <p style="font-size: 12px; color: #1e293b; font-weight: 500; line-height: 1.6; margin-bottom: 16px;">
            Puedes actualizar los siguientes datos de tu perfil en cualquier momento:
        </p>

        <ul style="font-size: 12px; color: #1e293b; font-weight: 600; padding-left: 18px; margin-bottom: 20px; line-height: 2;">
            <li>Nombre completo</li>
            <li>Correo institucional</li>
            <li>Género</li>
            <li>Número telefónico</li>
            <li>Organización / Área</li>
        </ul>

        <div style="background: white; border-radius: 8px; padding: 14px; border: 1px solid #dbeafe;">
            <div style="display: flex; gap: 8px; align-items: flex-start;">
                <i class="bi bi-shield-lock" style="color: #6366f1; font-size: 14px; margin-top: 1px;"></i>
                <p style="font-size: 11px; color: #475569; font-weight: 500; line-height: 1.6; margin: 0;">
                    El <strong>Rol de usuario</strong> y la <strong>Fecha de registro</strong> son datos administrados por el sistema y solo pueden ser cambiados por un administrador.
                </p>
            </div>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>
