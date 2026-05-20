<?php
/**
 * @file usuarios.php
 * @summary Gestión Integral de Usuarios (Internos y Visitas).
 * @description Ajustado para PostgreSQL y blindado contra advertencias de índices indefinidos.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/InviteController.php';
require_once '../backend/controllers/AuthController.php';

use Controllers\AuthController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Config\Database::getConnection();
$inviteController = new Controllers\InviteController();

// 1. Manejar Registro / Edición de Usuario Interno e Invitaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_user') {
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido'] ?? '';
        $correo = $_POST['correo'];
        $empresa = $_POST['empresa'] ?? '';
        $rfc = $_POST['rfc_matricula'] ?? '';
        $rol_id = $_POST['rol_id'];
        $us_id = $_POST['us_id'] ?? null;

        if ($us_id) {
            // Sintaxis PostgreSQL (minúsculas)
            $stmt = $db->prepare("UPDATE usuario SET nombre=?, apellido=?, correo=?, empresa=?, rfc_matricula=?, rol_id=? WHERE us_id=?");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rfc, $rol_id, $us_id]);
        } else {
            $pass = AuthController::hashPassword('123456');
            $stmt = $db->prepare("INSERT INTO usuario (nombre, apellido, correo, empresa, rfc_matricula, rol_id, contrasena) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rfc, $rol_id, $pass]);
        }
        header("Location: usuarios.php?success=1");
        exit();
    } elseif ($_POST['action'] === 'generate_code') {
        $nombre_visita = $_POST['nombre_visita'];
        $correo_visita = $_POST['correo_visita'];
        $anfitrion_id = $_SESSION['us_id'] ?? null;

        if ($anfitrion_id) {
            $inviteController->generate($nombre_visita, $correo_visita, $anfitrion_id);
            header("Location: usuarios.php?tab=externos&success_invite=1");
        } else {
            header("Location: usuarios.php?tab=externos&error_invite=no_session");
        }
        exit();
    }
}

// 2. Eliminar Usuario
if (isset($_GET['delete_user'])) {
    $stmt = $db->prepare("UPDATE usuario SET estatus = 'Inactivo' WHERE us_id = ?");
    $stmt->execute([$_GET['delete_user']]);
    header("Location: usuarios.php");
    exit();
}

// Consultas optimizadas para PostgreSQL
$users = $db->query("SELECT u.*, r.nombre as rol_nombre FROM usuario u LEFT JOIN roles r ON u.rol_id = r.rol_id WHERE u.estatus != 'Inactivo'")->fetchAll();
$roles = $db->query("SELECT rol_id, nombre FROM roles")->fetchAll();
$invites = $inviteController->getAllActive();

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gestión de Usuarios</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Administración de personal institucional y visitas externas.</p>
        </div>
        <style>
            .tab-container {
                display: flex;
                gap: 4px;
                background: #f1f5f9;
                padding: 6px;
                border-radius: 14px;
                border: 1px solid #e2e8f0;
            }
            .btn-tab {
                border: none;
                padding: 10px 24px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 800;
                color: #64748b;
                background: transparent;
                cursor: pointer;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .btn-tab.active {
                background: white;
                color: #2563eb;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            .btn-tab:not(.active):hover {
                color: #1e293b;
            }
        </style>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div class="tab-container">
                <button onclick="switchTab('internos')" id="tab-internos" class="btn-tab active">INTERNOS</button>
                <button onclick="switchTab('externos')" id="tab-externos" class="btn-tab">VISITAS</button>
            </div>
            <button onclick="openUserModal()" class="btn-primary" style="height: 44px; border-radius: 12px;">+ NUEVO USUARIO</button>
        </div>
    </div>

    <!-- Contenido: Usuarios Internos -->
    <div id="content-internos" class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Nombre y Apellido</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Empresa / Matrícula</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Rol</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 20px 24px;">
                        <p style="font-size: 14px; font-weight: 800; color: #1e293b; margin: 0;"><?php echo htmlspecialchars(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')); ?></p>
                        <p style="font-size: 11px; color: #94a3b8; font-weight: 600; margin: 0;"><?php echo htmlspecialchars($u['correo'] ?? ''); ?></p>
                    </td>
                    <td style="padding: 20px 24px;">
                        <p style="font-size: 12px; font-weight: 700; color: #475569; margin: 0;"><?php echo htmlspecialchars($u['empresa'] ?? 'N/A'); ?></p>
                        <p style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin: 0;"><?php echo htmlspecialchars($u['rfc_matricula'] ?? 'S/N'); ?></p>
                    </td>
                    <td style="padding: 20px 24px;">
                        <span style="background: #eff6ff; color: #2563eb; padding: 4px 12px; border-radius: 20px; font-size: 9px; font-weight: 900;">
                            <?php echo htmlspecialchars($u['rol_nombre'] ?? 'USUARIO'); ?>
                        </span>
                    </td>
                    <td style="padding: 20px 24px; text-align: right;">
                        <button onclick='editUser(<?php echo json_encode($u); ?>)' style="background: none; border: none; color: #2563eb; font-size: 11px; font-weight: 800; cursor: pointer; margin-right: 16px;">EDITAR</button>
                        <a href="?delete_user=<?php echo $u['us_id']; ?>" onclick="return confirm('¿Baja de este usuario?')" style="color: #ef4444; text-decoration: none; font-size: 11px; font-weight: 800;">ELIMINAR</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Contenido: Visitas Externas -->
    <div id="content-externos" style="display: none; grid-template-columns: 2fr 1fr; gap: 32px;">
        <main class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Invitado</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Código</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Anfitrión</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $inv): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px;">
                            <p style="font-size: 14px; font-weight: 800;"><?php echo htmlspecialchars($inv['nombre'] ?? ''); ?></p>
                            <p style="font-size: 10px; color: #94a3b8;"><?php echo htmlspecialchars($inv['correo'] ?? ''); ?></p>
                        </td>
                        <td style="padding: 16px 24px;">
                            <code style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-weight: 800; color: #1e293b;"><?php echo htmlspecialchars($inv['codigo_acceso'] ?? ''); ?></code>
                        </td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 700; color: #64748b;"><?php echo htmlspecialchars($inv['anfitrion_nombre'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
        
        <aside class="card">
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 24px;">Generar Invitación</h3>
            <form method="POST">
                <input type="hidden" name="action" value="generate_code">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <input type="text" name="nombre_visita" placeholder="Nombre completo" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 700;">
                    <input type="email" name="correo_visita" placeholder="Correo electrónico" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 700;">
                    <button type="submit" class="btn-primary" style="justify-content: center;">GENERAR CÓDIGO</button>
                </div>
            </form>
        </aside>
    </div>
</div>

<!-- Modal de Usuarios (Simplificado para evitar errores) -->
<div id="modal-usuario" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 40px;">
        <h2 id="user-modal-title" style="font-weight: 900; color: #1e293b; margin-bottom: 32px;">Registrar Usuario</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="us_id" id="us_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Nombre</label>
                    <input type="text" name="nombre" id="us_nombre" required style="width: 100%; border: 1px solid #e2e8f0; padding: 10px; border-radius: 10px; font-weight: 700;">
                </div>
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Apellido</label>
                    <input type="text" name="apellido" id="us_apellido" style="width: 100%; border: 1px solid #e2e8f0; padding: 10px; border-radius: 10px; font-weight: 700;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Correo Institucional</label>
                <input type="email" name="correo" id="us_correo" required style="width: 100%; border: 1px solid #e2e8f0; padding: 10px; border-radius: 10px; font-weight: 700;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px;">
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Rol Asignado</label>
                    <select name="rol_id" id="us_rol" required style="width: 100%; border: 1px solid #e2e8f0; padding: 10px; border-radius: 10px; font-weight: 700;">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['rol_id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Matrícula / RFC</label>
                    <input type="text" name="rfc_matricula" id="us_rfc" style="width: 100%; border: 1px solid #e2e8f0; padding: 10px; border-radius: 10px; font-weight: 700;">
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeUserModal()" style="flex: 1; background: #f1f5f9; color: #64748b; border: none; padding: 14px; border-radius: 12px; font-weight: 800; cursor: pointer;">CANCELAR</button>
                <button type="submit" style="flex: 1; background: #1e293b; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 800; cursor: pointer;">GUARDAR</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.getElementById('content-internos').style.display = tab === 'internos' ? 'block' : 'none';
        document.getElementById('content-externos').style.display = tab === 'externos' ? 'grid' : 'none';
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        if(document.getElementById('tab-' + tab)) document.getElementById('tab-' + tab).classList.add('active');
    }

    function openUserModal() {
        document.getElementById('us_id').value = '';
        document.getElementById('user-modal-title').innerText = 'Registrar Nuevo Usuario';
        document.getElementById('modal-usuario').style.display = 'flex';
    }

    function closeUserModal() {
        document.getElementById('modal-usuario').style.display = 'none';
    }

    function editUser(u) {
        document.getElementById('us_id').value = u.us_id;
        document.getElementById('us_nombre').value = u.nombre || '';
        document.getElementById('us_apellido').value = u.apellido || '';
        document.getElementById('us_correo').value = u.correo || '';
        document.getElementById('us_rol').value = u.rol_id;
        document.getElementById('us_rfc').value = u.rfc_matricula || '';
        document.getElementById('user-modal-title').innerText = 'Editar Usuario';
        document.getElementById('modal-usuario').style.display = 'flex';
    }

    // Mantener la pestaña activa después de recargar si viene en el GET
    const urlParams = new URLSearchParams(window.location.search);
    let activeTab = urlParams.get('tab') || 'internos';
    switchTab(activeTab);
</script>

<?php include 'footer.php'; ?>
