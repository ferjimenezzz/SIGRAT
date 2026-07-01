<?php
/**
 * @file visitas.php
 * @summary Módulo Administrativo de Visitas.
 * @description Permite a los administradores visualizar el registro de visitantes y gestionar su estatus. Ajustado para PostgreSQL.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

require_once 'seguridad.php';
require_once '../backend/config/Database.php';

$db = Config\Database::getConnection();

// Marcar salida manual
if (isset($_GET['marcar_salida'])) {
    $stmt = $db->prepare("UPDATE visita SET estatus = 'Completada' WHERE vis_id = ? AND estatus IN ('Activa', 'Generado')");
    $stmt->execute([$_GET['marcar_salida']]);
    header("Location: visitas.php?success=1");
    exit();
}

// Filtro por fecha (por defecto hoy)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Usando DATE() o CAST(.. AS DATE) para la comparación en PostgreSQL
$query = "SELECT v.*, u.nombre as anfitrion_nombre 
          FROM visita v 
          LEFT JOIN usuario u ON v.us_anfitrion = u.us_id 
          WHERE CAST(v.fecha_acceso AS DATE) = ? 
          ORDER BY v.fecha_acceso DESC";
$stmt = $db->prepare($query);
$stmt->execute([$selectedDate]);
$visitas = $stmt->fetchAll();

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">


<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Control de Visitas</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Registro de invitados, accesos y anfitriones.</p>
        </div>
        <div style="display: flex; gap: 16px;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <i data-lucide="calendar" style="color: var(--active-blue); width: 18px;"></i>
                <input type="date" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="location.href='?date='+this.value" style="border: none; outline: none; font-size: 12px; font-weight: 800; color: #334155;">
            </div>
            <a href="invitado.php" target="_blank" class="btn-secondary" style="text-decoration: none;">VER PORTAL PÚBLICO</a>
        </div>
    </header>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Visitante / Correo</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Anfitrión</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Espacio</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Hora Acceso</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase;">Estatus</th>
                    <th style="padding: 16px 24px; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visitas)): ?>
                    <tr><td colspan="6" style="padding: 48px; text-align: center; color: #94a3b8; font-weight: 600;">No hay visitas registradas en esta fecha.</td></tr>
                <?php else: ?>
                    <?php foreach ($visitas as $visita): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px;">
                            <p style="font-size: 14px; font-weight: 800; color: #334155; margin: 0;"><?php echo htmlspecialchars($visita['nombre']); ?></p>
                            <p style="font-size: 11px; font-weight: 600; color: #64748b; margin: 0;"><?php echo htmlspecialchars($visita['correo']); ?></p>
                        </td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 600; color: #64748b;"><?php echo htmlspecialchars($visita['anfitrion_nombre'] ?: 'N/A'); ?></td>
                        <td style="padding: 16px 24px; font-size: 12px; font-weight: 500; color: #64748b;">
                            <?php echo htmlspecialchars($visita['espacio_solicitado'] ?: 'No especificado'); ?>
                        </td>
                        <td style="padding: 16px 24px;">
                            <div style="font-weight: 800; font-size: 12px; color: #1e293b;"><?php echo $visita['fecha_acceso'] ? date('H:i', strtotime($visita['fecha_acceso'])) : 'N/A'; ?></div>
                        </td>
                        <td style="padding: 16px 24px;">
                            <?php 
                            $bg = '#eff6ff'; $col = '#1d4ed8';
                            if ($visita['estatus'] === 'Completada') { $bg = '#f1f5f9'; $col = '#64748b'; }
                            if ($visita['estatus'] === 'Generado') { $bg = '#fef3c7'; $col = '#92400e'; }
                            ?>
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 900; background: <?php echo $bg; ?>; color: <?php echo $col; ?>;">
                                <?php echo strtoupper($visita['estatus']); ?>
                            </span>
                        </td>
                        <td style="padding: 16px 24px; text-align: right;">
                            <?php if ($visita['estatus'] === 'Activa' || $visita['estatus'] === 'Generado'): ?>
                            <a href="?marcar_salida=<?php echo $visita['vis_id']; ?>" onclick="return confirm('¿Registrar salida/completar visita?')" style="color: #10b981; text-decoration: none; font-size: 10px; font-weight: 900;"><i data-lucide="log-out" style="width: 14px; vertical-align: middle;"></i> MARCAR COMPLETADA</a>
                            <?php else: ?>
                            <span style="color: #cbd5e1; font-size: 10px; font-weight: 900;">CERRADA</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
