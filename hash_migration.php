<?php
/**
 * @file hash_migration.php
 * @summary Script de migración para hashear contraseñas existentes.
 * @description Ejecuta este script una vez para asegurar que todas las credenciales cumplan con el estándar de seguridad.
 */

require_once 'backend/config/Database.php';
require_once 'backend/controllers/AuthController.php';

use Config\Database;
use Controllers\AuthController;

try {
    $db = Database::getConnection();
    $users = $db->query("SELECT us_id, contrasena FROM USUARIO")->fetchAll();
    
    $count = 0;
    foreach ($users as $u) {
        // Solo hashear si no parece un hash de BCRYPT (los hashes de BCRYPT suelen empezar con $2y$)
        if (substr($u['contrasena'], 0, 4) !== '$2y$') {
            $hashed = AuthController::hashPassword($u['contrasena']);
            $stmt = $db->prepare("UPDATE USUARIO SET contrasena = ? WHERE us_id = ?");
            $stmt->execute([$hashed, $u['us_id']]);
            $count++;
        }
    }
    
    echo "Migración completada. $count usuarios actualizados con hashing seguro.";
} catch (Exception $e) {
    echo "Error en migración: " . $e->getMessage();
}
