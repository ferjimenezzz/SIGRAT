<?php
/**
 * test_approval_script.php
 *
 * Script de prueba para validar el controlador de aprobación y rechazo de reservas
 * usando la conexión a Supabase (PostgreSQL).
 */

require_once __DIR__ . '/backend/config/Database.php';
require_once __DIR__ . '/backend/ReservationApprovalController.php';

try {
    $pdo = \Config\Database::getConnection();
    echo "✅ Conexión a Supabase (PostgreSQL) exitosa.\n";

    // 1. Modificar tabla si no tiene la columna status (migración rápida para Postgres)
    // En Postgres usamos un alter table básico. Si usáramos ENUM, habría que crear el tipo. Usaremos VARCHAR.
    try {
        $pdo->exec("ALTER TABLE reserva ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'");
        $pdo->exec("ALTER TABLE reserva ADD COLUMN IF NOT EXISTS approved_by INTEGER");
        $pdo->exec("ALTER TABLE reserva ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP");
        echo "✅ Columnas de estado verificadas/agregadas a la tabla reserva.\n";
    } catch (Exception $e) {
        // Puede fallar si ya existen
    }

    // 2. Insertamos un usuario administrador si no existe (id = 1)
    $stmt = $pdo->query("SELECT us_id FROM usuario WHERE us_id = 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO usuario (us_id, nombre, correo, contrasena, estatus) VALUES (1, 'Admin Prueba', 'admin@test.com', '123', 'Activo') ON CONFLICT (us_id) DO NOTHING");
        echo "✅ Usuario administrador (ID: 1) creado para la prueba.\n";
    }

    // 3. Insertamos un espacio de prueba si no existe (id = 1)
    $stmt = $pdo->query("SELECT esp_id FROM espacio WHERE esp_id = 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO espacio (esp_id, edificio, nombre_numero, estatus) VALUES (1, 'CIC', 'Lab Prueba', 'Disponible') ON CONFLICT (esp_id) DO NOTHING");
        echo "✅ Espacio (ID: 1) verificado para la prueba.\n";
    }

    // 4. Insertamos dos reservas de prueba con estado 'pending'
    // Postgres DATE y TIME formats. Usamos id altos para no chocar.
    $pdo->exec("INSERT INTO reserva (re_id, us_id, esp_id, fecha_uso, hora_ent, hora_sal, status) VALUES (9001, 1, 1, CURRENT_DATE, '10:00:00', '12:00:00', 'pending') ON CONFLICT (re_id) DO UPDATE SET status = 'pending'");
    $pdo->exec("INSERT INTO reserva (re_id, us_id, esp_id, fecha_uso, hora_ent, hora_sal, status) VALUES (9002, 1, 1, CURRENT_DATE, '13:00:00', '15:00:00', 'pending') ON CONFLICT (re_id) DO UPDATE SET status = 'pending'");
    echo "✅ Reservas de prueba (ID 9001 y 9002) inicializadas en estado 'pending'.\n";

    $controller = new \Backend\ReservationApprovalController($pdo);
    $adminId = 1;

    // 5. Aprobar la primera reserva (9001)
    echo "\n⏳ Aprobando reserva 9001...\n";
    $controller->approve(9001, $adminId);
    echo "✅ Reserva 9001 aprobada exitosamente.\n";

    // 6. Rechazar la segunda reserva (9002)
    echo "⏳ Rechazando reserva 9002...\n";
    $controller->reject(9002, $adminId, "No hay disponibilidad en ese horario");
    echo "✅ Reserva 9002 rechazada exitosamente.\n";

    // 7. Verificar en base de datos
    echo "\n🔍 Verificando estados actuales en Supabase:\n";
    $stmt = $pdo->query("SELECT re_id, status, approved_by FROM reserva WHERE re_id IN (9001, 9002)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo " - Reserva {$row['re_id']}: Estado '{$row['status']}' (por Admin ID: {$row['approved_by']})\n";
    }

    // 8. Limpiar datos de prueba (Reservas)
    $pdo->exec("DELETE FROM reserva WHERE re_id IN (9001, 9002)");
    $pdo->exec("DELETE FROM bitacora WHERE modulo_afectado = 'reserva' AND us_id = 1");
    echo "\n🧹 Datos de prueba eliminados.\n";
    
} catch (Exception $e) {
    echo "❌ Error en la prueba: " . $e->getMessage() . "\n";
}
?>
