<?php
/**
 * @file espacios.php
 * @summary Gestión de Espacios y Áreas (CIC/PIDET).
 * @description Permite dar de alta, editar y eliminar laboratorios, aulas y talleres.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/SpaceController.php';
use Controllers\SpaceController;

$db = Config\Database::getConnection();
$spaceController = new SpaceController();

// Procesar Creación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_space') {
    $spaceController->create($_POST);
    header("Location: espacios.php");
    exit();
}

// Procesar Eliminación (Baja Lógica)
if (isset($_GET['delete_id'])) {
    $db = Config\Database::getConnection();
    $stmt = $db->prepare("UPDATE ESPACIO SET estatus = 'Inactivo' WHERE esp_id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: espacios.php");
    exit();
}

$spaces = $db->query("SELECT * FROM ESPACIO WHERE estatus != 'Inactivo' ORDER BY edificio, nombre_numero")->fetchAll();

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header>
        <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gestión de Espacios</h1>
        <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Administración de áreas físicas en los edificios CIC y PIDET.</p>
    </header>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px;">
        <!-- Lista de Espacios -->
        <main class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Edificio / Nombre</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Tipo</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Capacidad</th>
                        <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spaces as $space): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px;">
                            <span style="background: <?php echo $space['edificio'] == 'CIC' ? '#eff6ff' : '#fff7ed'; ?>; color: <?php echo $space['edificio'] == 'CIC' ? '#2563eb' : '#ea580c'; ?>; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 900; margin-right: 8px;">
                                <?php echo $space['edificio']; ?>
                            </span>
                            <span style="font-size: 14px; font-weight: 700; color: #334155;"><?php echo $space['nombre_numero']; ?></span>
                        </td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 600; color: #64748b;"><?php echo $space['tipo']; ?></td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 600; color: #64748b;"><?php echo $space['capacidad']; ?> pers.</td>
                        <td style="padding: 16px 24px; text-align: right;">
                            <a href="?delete_id=<?php echo $space['esp_id']; ?>" onclick="return confirm('¿Eliminar este espacio?')" style="color: #ef4444; text-decoration: none; font-size: 10px; font-weight: 900;">ELIMINAR</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

        <!-- Formulario Nuevo Espacio -->
        <aside class="card">
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 24px;">Nuevo Espacio</h3>
            <form method="POST">
                <input type="hidden" name="action" value="new_space">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Edificio</label>
                        <select name="edificio" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; font-weight: 700;">
                            <option value="CIC">CIC</option>
                            <option value="PIDET">PIDET</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Nombre / Número</label>
                        <input type="text" name="nombre_numero" required placeholder="Ej: Laboratorio L1" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Tipo de Área</label>
                        <input type="text" name="tipo" required placeholder="Ej: Laboratorio, Aula, Taller" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px;">Capacidad</label>
                        <input type="number" name="capacidad" required style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 44px;">REGISTRAR ÁREA</button>
                </div>
            </form>
        </aside>
    </div>
</div>

<?php include 'footer.php'; ?>
