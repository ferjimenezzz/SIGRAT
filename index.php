<?php
/**
 * @file index.php
 * @summary Punto de entrada raíz.
 * @description Redirige al directorio frontend para mantener la estructura solicitada.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

header("Location: frontend/index.php");
exit();
?>
