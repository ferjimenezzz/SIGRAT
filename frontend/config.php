<?php
/**
 * @file config.php
 * @summary Módulo de Configuración con Gestión Avanzada de Roles y Permisos (CRUD Completo).
 * @description Permite Crear, Ver, Editar y Eliminar roles con permisos granulares.
 */

require_once '../backend/config/Database.php';
$db = Config\Database::getConnection();

// 1. Manejar Creación / Edición de Rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_role') {
        $nombre = $_POST['nombre_rol'];
        $descripcion = $_POST['descripcion_rol'];
        $permisos = $_POST['permisos'] ?? [];
        $rol_id = $_POST['rol_id'] ?? null;

        if ($rol_id) {
            // Editar
            $stmt = $db->prepare("UPDATE ROLES SET nombre = ?, descripcion = ?, permisos = ? WHERE rol_id = ?");
            $stmt->execute([$nombre, $descripcion, json_encode($permisos), $rol_id]);
        } else {
            // Nuevo
            $stmt = $db->prepare("INSERT INTO ROLES (nombre, descripcion, permisos) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, json_encode($permisos)]);
        }
        header("Location: config.php?success=1");
        exit();
    }
}

// 2. Manejar Eliminación de Rol
if (isset($_GET['delete_rol'])) {
    $stmt = $db->prepare("DELETE FROM ROLES WHERE rol_id = ?");
    $stmt->execute([$_GET['delete_rol']]);
    header("Location: config.php?success=deleted");
    exit();
}

$roles = $db->query("SELECT * FROM ROLES ORDER BY rol_id DESC")->fetchAll();
$modulos = [
    'usuarios' => ['Ver', 'Crear', 'Editar', 'Eliminar'],
    'espacios' => ['Ver', 'Crear', 'Editar', 'Eliminar'],
    'inventario' => ['Ver', 'Crear', 'Editar', 'Eliminar'],
    'auditoria' => ['Ver', 'Exportar PDF'],
    'configuracion' => ['Ver', 'Editar Roles']
];

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gobernanza del Sistema</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Administre roles, permisos granulares y seguridad institucional.</p>
        </div>
        <button onclick="openModal()" class="btn-primary">+ NUEVO ROL</button>
    </header>

    <div class="card">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
            <?php foreach ($roles as $rol): ?>
            <?php 
            // Deserializa los permisos del formato JSON almacenado en la BD
            $p = json_decode($rol['permisos'] ?? '', true) ?: []; 
            ?>
            <div style="padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; position: relative;">
                <div style="position: absolute; top: 16px; right: 16px; display: flex; gap: 12px;">
                    <button onclick='editRole(<?php echo json_encode($rol); ?>)' style="background: none; border: none; color: var(--active-blue); font-size: 10px; font-weight: 900; cursor: pointer;">EDITAR</button>
                    <a href="?delete_rol=<?php echo $rol['rol_id']; ?>" onclick="return confirm('¿Eliminar este rol?')" style="color: #ef4444; text-decoration: none; font-size: 10px; font-weight: 900;">ELIMINAR</a>
                </div>
                
                <h4 style="font-size: 18px; font-weight: 900; color: #1e293b; margin: 0 0 4px 0;"><?php echo $rol['nombre']; ?></h4>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 20px; font-weight: 500;"><?php echo $rol['descripcion']; ?></p>
                
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <?php 
                    // Iteramos sobre las secciones de permisos definidos en el rol
                    foreach ($p as $modulo => $acciones): 
                        // Se previene TypeError en PHP 8 al comprobar si $acciones es arreglo antes de contar
                        // Si es booleano y es true (ej. en el rol de super admin), se muestra "Todo", de lo contrario se muestra su valor plano
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
</div>

<!-- Modal de Roles (Crear/Editar) -->
<div id="modal-rol" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 40px;">
        <h2 id="modal-title" style="font-weight: 900; color: #1e293b; margin-bottom: 8px;">Configurar Rol</h2>
        <p style="font-size: 13px; color: #94a3b8; margin-bottom: 32px;">Defina las acciones específicas permitidas para este perfil.</p>
        
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
                    <button type="button" onclick="closeModal()" style="flex: 1; background: #f1f5f9; color: #64748b; border: none; padding: 16px; border-radius: 12px; font-weight: 800; cursor: pointer;">CANCELAR</button>
                    <button type="submit" style="flex: 1; background: #1e293b; color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 800; cursor: pointer;">GUARDAR CAMBIOS</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Inicializa y muestra el modal para agregar un nuevo rol en el sistema.
 * @return {void}
 */
function openModal() {
    // Restablece los campos de texto y selecciones del formulario a sus valores iniciales
    document.getElementById('form-rol').reset();
    // Limpia el ID del rol para asegurar que la acción posterior sea un INSERT
    document.getElementById('rol_id').value = '';
    // Ajusta el título para reflejar la intención de crear un rol nuevo
    document.getElementById('modal-title').innerText = 'Configurar Nuevo Rol';
    // Muestra la ventana modal usando flexbox para centrado automático
    document.getElementById('modal-rol').style.display = 'flex';
}

/**
 * Oculta la ventana modal de edición/registro de roles.
 * @return {void}
 */
function closeModal() {
    // Cambia el display a 'none' para ocultar el modal del DOM visual
    document.getElementById('modal-rol').style.display = 'none';
}

/**
 * Carga la información de un rol existente y abre el modal en modo edición.
 * @param {Object} rol - Objeto que representa los datos del rol obtenidos de la base de datos.
 * @param {number} rol.rol_id - ID único del rol.
 * @param {string} rol.nombre - Nombre del rol.
 * @param {string} rol.descripcion - Descripción funcional del rol.
 * @param {string} rol.permisos - Cadena JSON que contiene los permisos del rol.
 * @return {void}
 */
function editRole(rol) {
    // Precarga los datos del rol en los campos ocultos y de texto correspondientes
    document.getElementById('rol_id').value = rol.rol_id;
    document.getElementById('nombre_rol').value = rol.nombre;
    document.getElementById('descripcion_rol').value = rol.descripcion;
    document.getElementById('modal-title').innerText = 'Editar Rol: ' + rol.nombre;
    
    // Desmarca todos los checkboxes de los permisos para evitar arrastrar selecciones previas
    document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
    
    // Intenta decodificar la cadena JSON que almacena los permisos asignados
    let permisos = {};
    try {
        permisos = JSON.parse(rol.permisos) || {};
    } catch (e) {
        console.error("Error al parsear permisos JSON", e);
    }
    
    // Mapea los permisos recuperados y marca los checkboxes correspondientes en el DOM
    for (const mod in permisos) {
        // Valida que el permiso del módulo esté estructurado como un arreglo (ej. ['Ver', 'Crear'])
        if (Array.isArray(permisos[mod])) {
            permisos[mod].forEach(acc => {
                const check = document.querySelector(`.perm-check[data-mod="${mod}"][data-acc="${acc}"]`);
                if (check) check.checked = true;
            });
        }
    }
    
    // Muestra el modal para edición
    document.getElementById('modal-rol').style.display = 'flex';
}
</script>

<?php include 'footer.php'; ?>
