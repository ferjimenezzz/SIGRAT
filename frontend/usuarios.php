<?php
/**
 * @file usuarios.php
 * @summary Gestión Integral de Usuarios, Invitaciones y Roles del Sistema.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/InviteController.php';
require_once '../backend/controllers/AuthController.php';

use Controllers\AuthController;

$db = Config\Database::getConnection();
$inviteController = new Controllers\InviteController();

// 1. Manejar Registro / Edición de Usuario Interno
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
            $stmt = $db->prepare("UPDATE usuario SET nombre=?, apellido=?, correo=?, empresa=?, rfc_matricula=?, rol_id=? WHERE us_id=?");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rfc, $rol_id, $us_id]);
        } else {
            $pass = AuthController::hashPassword('123456');
            $stmt = $db->prepare("INSERT INTO usuario (nombre, apellido, correo, empresa, rfc_matricula, rol_id, contrasena) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $correo, $empresa, $rfc, $rol_id, $pass]);
        }
        header("Location: usuarios.php?tab=usuarios&success=1");
        exit();
    } elseif ($_POST['action'] === 'save_role') {
        $nombre = $_POST['nombre_rol'];
        $descripcion = $_POST['descripcion_rol'];
        $permisos = $_POST['permisos'] ?? [];
        $rol_id = $_POST['rol_id'] ?? null;

        if ($rol_id) {
            $stmt = $db->prepare("UPDATE ROLES SET nombre = ?, descripcion = ?, permisos = ? WHERE rol_id = ?");
            $stmt->execute([$nombre, $descripcion, json_encode($permisos), $rol_id]);
        } else {
            $stmt = $db->prepare("INSERT INTO ROLES (nombre, descripcion, permisos) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, json_encode($permisos)]);
        }
        header("Location: usuarios.php?tab=roles&success=1");
        exit();
    } elseif ($_POST['action'] === 'generate_code') {
        $inviteController->create($_POST['nombre_visita'], $_POST['correo_visita'], $_SESSION['us_id']);
        header("Location: usuarios.php?tab=invitaciones&success=1");
        exit();
    }
}

// 2. Eliminar Usuario o Rol
if (isset($_GET['delete_user'])) {
    $stmt = $db->prepare("UPDATE usuario SET estatus = 'Inactivo' WHERE us_id = ?");
    $stmt->execute([$_GET['delete_user']]);
    header("Location: usuarios.php?tab=usuarios");
    exit();
}
if (isset($_GET['delete_rol'])) {
    $stmt = $db->prepare("DELETE FROM ROLES WHERE rol_id = ?");
    $stmt->execute([$_GET['delete_rol']]);
    header("Location: usuarios.php?tab=roles");
    exit();
}

$users = $db->query("SELECT u.*, r.nombre as rol_nombre FROM usuario u LEFT JOIN roles r ON u.rol_id = r.rol_id WHERE u.estatus != 'Inactivo'")->fetchAll();
$roles = $db->query("SELECT * FROM ROLES ORDER BY rol_id DESC")->fetchAll();
$invites = $inviteController->getAllActive();

$modulos = [
    'Inventario' => ['read', 'create', 'update', 'delete'],
    'Espacios' => ['read', 'create', 'update', 'delete'],
    'Visitas' => ['read', 'create', 'update', 'delete'],
    'RFID' => ['read', 'create', 'update', 'delete'],
    'Usuarios' => ['read', 'create', 'update', 'delete'],
    'Auditorias' => ['read', 'create', 'update', 'delete']
];

include 'header.php';
$tab = $_GET['tab'] ?? 'usuarios';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gestión de Usuarios y Roles</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Administración de personal, roles del sistema e invitaciones.</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div style="display: flex; gap: 4px; background: #f1f5f9; padding: 6px; border-radius: 14px; border: 1px solid #e2e8f0;">
                <button onclick="switchTab('usuarios')" id="btn-usuarios" class="btn-tab <?php echo $tab === 'usuarios' ? 'active' : ''; ?>">USUARIOS</button>
                <button onclick="switchTab('roles')" id="btn-roles" class="btn-tab <?php echo $tab === 'roles' ? 'active' : ''; ?>">ROLES</button>
                <button onclick="switchTab('invitaciones')" id="btn-invitaciones" class="btn-tab <?php echo $tab === 'invitaciones' ? 'active' : ''; ?>">INVITACIONES</button>
            </div>
            <button onclick="openUserModal()" id="btn-action-user" class="btn-primary" style="height: 44px; border-radius: 12px; display: <?php echo $tab === 'usuarios' ? 'block' : 'none'; ?>;">+ NUEVO USUARIO</button>
            <button onclick="openRoleModal()" id="btn-action-role" class="btn-primary" style="height: 44px; border-radius: 12px; display: <?php echo $tab === 'roles' ? 'block' : 'none'; ?>;">+ NUEVO ROL</button>
        </div>
    </header>

    <!-- Pestaña Usuarios -->
    <div id="tab-usuarios" class="card" style="display: <?php echo $tab === 'usuarios' ? 'block' : 'none'; ?>; padding: 0; overflow: hidden;">
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

    <!-- Pestaña Roles -->
    <div id="tab-roles" style="display: <?php echo $tab === 'roles' ? 'block' : 'none'; ?>;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
            <?php foreach ($roles as $rol): ?>
            <?php $p = json_decode($rol['permisos'] ?? '', true) ?: []; ?>
            <div style="padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; position: relative;">
                <div style="position: absolute; top: 16px; right: 16px; display: flex; gap: 12px;">
                    <button onclick='editRole(<?php echo json_encode($rol); ?>)' style="background: none; border: none; color: var(--active-blue); font-size: 10px; font-weight: 900; cursor: pointer;">EDITAR</button>
                    <a href="?delete_rol=<?php echo $rol['rol_id']; ?>" onclick="return confirm('¿Eliminar este rol?')" style="color: #ef4444; text-decoration: none; font-size: 10px; font-weight: 900;">ELIMINAR</a>
                </div>
                
                <h4 style="font-size: 18px; font-weight: 900; color: #1e293b; margin: 0 0 4px 0;"><?php echo $rol['nombre']; ?></h4>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 20px; font-weight: 500;"><?php echo $rol['descripcion']; ?></p>
                
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <?php foreach ($p as $modulo => $acciones): 
                        $countVal = is_array($acciones) ? count($acciones) : ($acciones === true ? 'Todo' : htmlspecialchars((string)$acciones));
                    ?>
                        <span style="background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 6px; font-size: 9px; font-weight: 900; text-transform: uppercase;">
                            <?php echo htmlspecialchars($modulo); ?>: <?php echo $countVal; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Pestaña Invitaciones -->
    <div id="tab-invitaciones" style="display: <?php echo $tab === 'invitaciones' ? 'grid' : 'none'; ?>; grid-template-columns: 2fr 1fr; gap: 32px;">
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

<!-- Modal Usuario -->
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

<!-- Modal Rol -->
<div id="modal-rol" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 40px;">
        <h2 id="role-modal-title" style="font-weight: 900; color: #1e293b; margin-bottom: 8px;">Configurar Rol</h2>
        <p style="font-size: 13px; color: #94a3b8; margin-bottom: 32px;">Defina las acciones permitidas (CRUD) por módulo para este perfil.</p>
        
        <form method="POST" id="form-rol">
            <input type="hidden" name="action" value="save_role">
            <input type="hidden" name="rol_id" id="rol_id">
            
            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Nombre del Rol</label>
                    <input type="text" name="nombre_rol" id="nombre_rol" required style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 700;">
                </div>
                
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Descripción</label>
                    <textarea name="descripcion_rol" id="descripcion_rol" style="width: 100%; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 700; height: 60px; resize: none;"></textarea>
                </div>

                <div style="background: #f8fafc; padding: 24px; border-radius: 20px; border: 1px solid #f1f5f9;">
                    <label style="display: block; font-size: 11px; font-weight: 900; color: #1e293b; text-transform: uppercase; margin-bottom: 20px;">Permisos Granulares</label>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <?php foreach ($modulos as $mod => $acciones): ?>
                        <div>
                            <p style="font-size: 12px; font-weight: 900; color: #334155; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px;"><?php echo $mod; ?></p>
                            <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                <?php foreach ($acciones as $acc): ?>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer;">
                                    <input type="checkbox" name="permisos[<?php echo $mod; ?>][]" value="<?php echo $acc; ?>" class="perm-check" data-mod="<?php echo $mod; ?>" data-acc="<?php echo $acc; ?>"> <?php echo $acc; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; padding-top: 12px;">
                    <button type="button" onclick="closeRoleModal()" style="flex: 1; background: #f1f5f9; color: #64748b; border: none; padding: 16px; border-radius: 12px; font-weight: 800; cursor: pointer;">CANCELAR</button>
                    <button type="submit" style="flex: 1; background: #1e293b; color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 800; cursor: pointer;">GUARDAR CAMBIOS</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .btn-tab {
        border: none; padding: 10px 24px; border-radius: 10px; font-size: 11px; font-weight: 800; color: #64748b; background: transparent; cursor: pointer; transition: all 0.2s; text-transform: uppercase;
    }
    .btn-tab.active { background: white; color: #2563eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
</style>

<script>
    function switchTab(tab) {
        document.getElementById('tab-usuarios').style.display = tab === 'usuarios' ? 'block' : 'none';
        document.getElementById('tab-roles').style.display = tab === 'roles' ? 'block' : 'none';
        document.getElementById('tab-invitaciones').style.display = tab === 'invitaciones' ? 'grid' : 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        if(document.getElementById('btn-' + tab)) document.getElementById('btn-' + tab).classList.add('active');
        
        document.getElementById('btn-action-user').style.display = tab === 'usuarios' ? 'block' : 'none';
        document.getElementById('btn-action-role').style.display = tab === 'roles' ? 'block' : 'none';
    }

    // Modal Usuario
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

    // Modal Rol
    function openRoleModal() {
        document.getElementById('form-rol').reset();
        document.getElementById('rol_id').value = '';
        document.getElementById('role-modal-title').innerText = 'Configurar Nuevo Rol';
        document.getElementById('modal-rol').style.display = 'flex';
    }
    function closeRoleModal() {
        document.getElementById('modal-rol').style.display = 'none';
    }
    function editRole(rol) {
        document.getElementById('rol_id').value = rol.rol_id;
        document.getElementById('nombre_rol').value = rol.nombre;
        document.getElementById('descripcion_rol').value = rol.descripcion;
        document.getElementById('role-modal-title').innerText = 'Editar Rol: ' + rol.nombre;
        
        document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
        
        let permisos = {};
        try { permisos = JSON.parse(rol.permisos) || {}; } catch (e) {}
        
        for (const mod in permisos) {
            if (Array.isArray(permisos[mod])) {
                permisos[mod].forEach(acc => {
                    const check = document.querySelector(`.perm-check[data-mod="${mod}"][data-acc="${acc}"]`);
                    if (check) check.checked = true;
                });
            }
        }
        document.getElementById('modal-rol').style.display = 'flex';
    }
</script>

<?php include 'footer.php'; ?>
