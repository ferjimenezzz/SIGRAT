<?php
/**
 * @file EmailService.php
 * @summary Servicio para el envío de correos electrónicos utilizando PHPMailer.
 * @description Implementa la lógica de envío de correos (confirmaciones, autorizaciones) de manera centralizada.
 */

namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir el autoloader de Composer si existe
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

class EmailService {
    private $mail;

    /**
     * @summary Constructor que inicializa y configura PHPMailer con las variables de entorno.
     */
    public function __construct() {
        $this->mail = new PHPMailer(true);

        try {
            // Configuración del servidor (Obtenida del entorno)
            // Se asume que las variables de entorno ya fueron cargadas por config/Database.php u otro bootstrapper.
            $this->mail->isSMTP();
            $this->mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = getenv('SMTP_USER');
            $this->mail->Password   = getenv('SMTP_PASS');
            $this->mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = getenv('SMTP_PORT') ?: 587;

            // Remitente
            $fromEmail = getenv('SMTP_FROM_EMAIL') ?: getenv('SMTP_USER');
            $fromName = getenv('SMTP_FROM_NAME') ?: 'Sistema de Reservas SIGRAT';
            
            // Solo configurar From si hay credenciales (evita errores en init sin .env configurado)
            if ($fromEmail) {
                $this->mail->setFrom($fromEmail, $fromName);
            }
            
            // Configuración general
            $this->mail->CharSet = 'UTF-8';
            $this->mail->isHTML(true);

        } catch (Exception $e) {
            error_log("Error inicializando EmailService: {$this->mail->ErrorInfo}");
        }
    }

    /**
     * @summary Envía un correo electrónico.
     * 
     * @param string $to Correo del destinatario.
     * @param string $subject Asunto del correo.
     * @param string $body Contenido en formato HTML.
     * @return bool True si se envió correctamente, False en caso contrario.
     */
    public function sendEmail($to, $subject, $body) {
        // Validar que se haya cargado el Username, de lo contrario no intentar enviar para no colgar la app
        if (empty($this->mail->Username)) {
            error_log("EmailService: No se enviará el correo porque las credenciales SMTP no están configuradas en el .env.");
            return false;
        }

        try {
            // Limpiar destinatarios previos por si se usa la misma instancia múltiples veces
            $this->mail->clearAllRecipients();
            
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            
            // Opcional: generar versión texto plano
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo a $to: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * @summary Envía correo de confirmación de reserva recién creada.
     * 
     * @param string $to Correo del usuario.
     * @param int $re_id ID de la reserva.
     * @param string $estatus Estatus inicial (Pendiente/Aprobada).
     */
    public function sendReservationCreated($to, $re_id, $estatus) {
        $subject = "Confirmación de solicitud de reserva #$re_id";
        $body = "
            <h2>Notificación del Sistema de Reservas</h2>
            <p>Tu solicitud de reserva con el número de folio <strong>#$re_id</strong> ha sido registrada en el sistema.</p>
            <p>El estatus actual de tu solicitud es: <strong>$estatus</strong>.</p>
            <p>Si el estatus es Pendiente, un administrador revisará tu solicitud y te notificará por este mismo medio una vez que sea autorizada o rechazada.</p>
            <br>
            <p>Saludos cordiales,<br>Equipo SIGRAT</p>
        ";
        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * @summary Envía correo cuando una reserva es autorizada.
     * 
     * @param string $to Correo del usuario.
     * @param int $re_id ID de la reserva.
     */
    public function sendReservationApproved($to, $re_id) {
        $subject = "Reserva Aprobada #$re_id";
        $body = "
            <h2>¡Tu reserva ha sido aprobada!</h2>
            <p>Nos complace informarte que tu solicitud de reserva con el número de folio <strong>#$re_id</strong> ha sido <strong>autorizada</strong>.</p>
            <p>Por favor, asegúrate de cumplir con los lineamientos de uso del espacio asignado.</p>
            <br>
            <p>Saludos cordiales,<br>Equipo SIGRAT</p>
        ";
        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * @summary Envía correo cuando una reserva es rechazada.
     * 
     * @param string $to Correo del usuario.
     * @param int $re_id ID de la reserva.
     * @param string $motivo Motivo del rechazo (opcional).
     */
    public function sendReservationRejected($to, $re_id, $motivo = '') {
        $subject = "Reserva Rechazada #$re_id";
        $motivoHtml = $motivo ? "<p>Motivo: <strong>$motivo</strong></p>" : "";
        $body = "
            <h2>Notificación sobre tu reserva</h2>
            <p>Lamentamos informarte que tu solicitud de reserva con el número de folio <strong>#$re_id</strong> ha sido <strong>rechazada</strong>.</p>
            $motivoHtml
            <br>
            <p>Saludos cordiales,<br>Equipo SIGRAT</p>
        ";
        return $this->sendEmail($to, $subject, $body);
    }
}
