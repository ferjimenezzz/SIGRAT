<?php
/**
 * @file send_email_bg.php
 * @summary Envío asíncrono de correos en segundo plano mediante PHP CLI.
 */

if (php_sapi_name() !== 'cli') {
    exit('Solo ejecucion mediante CLI permitida.');
}

// Desactivar límite de tiempo de ejecución
set_time_limit(0);

require_once __DIR__ . '/EmailService.php';

$to = $argv[1] ?? '';
$subject = $argv[2] ?? '';
$bodyBase64 = $argv[3] ?? '';

if ($to && $subject && $bodyBase64) {
    $body = base64_decode($bodyBase64);
    $emailService = new \Services\EmailService();
    // Enviamos el correo usando el método directo de PHPMailer
    $emailService->sendEmailDirectly($to, $subject, $body);
}
