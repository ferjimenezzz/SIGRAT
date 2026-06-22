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
            // Cargar archivo .env manualmente
            $env_file = dirname(__DIR__) . '/.env';
            $env = [];
            if (file_exists($env_file)) {
                $env = parse_ini_file($env_file);
            }

            $this->mail->isSMTP();
            $this->mail->Host       = $env['SMTP_HOST'] ?? 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $env['SMTP_USER'] ?? '';
            $this->mail->Password   = $env['SMTP_PASS'] ?? '';
            $this->mail->SMTPSecure = $env['SMTP_SECURE'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = $env['SMTP_PORT'] ?? 587;
            $this->mail->Timeout    = 3; // Timeout corto para no bloquear la app


            // Remitente
            $fromEmail = $env['SMTP_FROM_EMAIL'] ?? $this->mail->Username;
            $fromName = $env['SMTP_FROM_NAME'] ?? 'Sistema de Reservas SIGRAT';
            
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
     * @param string $espacio_nombre Nombre del espacio (opcional).
     * @param string $fecha_uso Fecha de uso (opcional).
     * @param string $hora_ent Hora de entrada (opcional).
     * @param string $hora_sal Hora de salida (opcional).
     */
    public function sendReservationCreated($to, $re_id, $estatus, $espacio_nombre = '', $fecha_uso = '', $hora_ent = '', $hora_sal = '') {
        $subject = "Confirmación de solicitud de reserva #$re_id";
        
        $detallesHtml = "";
        if ($espacio_nombre) {
            $detallesHtml = "
            <br>
            <h3>Detalles de la Reserva:</h3>
            <ul>
                <li><strong>Lugar:</strong> $espacio_nombre</li>
                <li><strong>Fecha:</strong> $fecha_uso</li>
                <li><strong>Horario:</strong> $hora_ent - $hora_sal</li>
            </ul>
            <br>";
        }

        $body = "
            <h2>Notificación del Sistema de Reservas</h2>
            <p>Tu solicitud de reserva con el número de folio <strong>#$re_id</strong> ha sido registrada en el sistema.</p>
            $detallesHtml
            <p>El estatus actual de tu solicitud es: <strong>$estatus</strong>.</p>
            <p>Si el estatus es Pendiente, un administrador revisará tu solicitud y te notificará por este mismo medio una vez que sea autorizada o rechazada.</p>
            <br>
            <p>Saludos cordiales,<br>Equipo SIGRAT</p>
        ";
        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * @summary Envía correo de confirmación para reservas múltiples.
     * 
     * @param string $to Correo del usuario.
     * @param array $re_ids Array de IDs de reservas.
     * @param array $fechas Array de fechas correspondientes.
     * @param string $estatus Estatus inicial.
     * @param string $espacio_nombre Nombre del espacio (opcional).
     * @param string $hora_ent Hora de entrada (opcional).
     * @param string $hora_sal Hora de salida (opcional).
     */
    public function sendBulkReservationCreated($to, $re_ids, $fechas, $estatus, $espacio_nombre = '', $hora_ent = '', $hora_sal = '') {
        $subject = "Confirmación de solicitudes de reserva múltiples";
        
        $listaReservas = "";
        for ($i = 0; $i < count($re_ids); $i++) {
            $fechaStr = isset($fechas[$i]) ? $fechas[$i] : '';
            $listaReservas .= "<li>Reserva <strong>#" . $re_ids[$i] . "</strong> para el día <strong>" . $fechaStr . "</strong></li>";
        }

        $detallesHtml = "";
        if ($espacio_nombre) {
            $detallesHtml = "
            <br>
            <h3>Detalles Compartidos:</h3>
            <ul>
                <li><strong>Lugar:</strong> $espacio_nombre</li>
                <li><strong>Horario:</strong> $hora_ent - $hora_sal</li>
            </ul>
            <br>";
        }

        $body = "
            <h2>Notificación del Sistema de Reservas</h2>
            <p>Tus solicitudes de reserva han sido registradas en el sistema exitosamente:</p>
            <ul>
                $listaReservas
            </ul>
            $detallesHtml
            <p>El estatus actual de estas solicitudes es: <strong>$estatus</strong>.</p>
            <p>Si el estatus es Pendiente, un administrador revisará tus solicitudes y te notificará una vez que sean autorizadas o rechazadas.</p>
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
    public function sendReservationApproved($to, $re_id, $espacio_nombre = '', $fecha_uso = '', $hora_ent = '', $hora_sal = '') {
        $subject = "Reserva Aprobada #$re_id";
        
        $detallesHtml = "";
        if ($espacio_nombre) {
            $detallesHtml = "
            <br>
            <h3>Detalles de la Reserva:</h3>
            <ul>
                <li><strong>Lugar:</strong> $espacio_nombre</li>
                <li><strong>Fecha:</strong> $fecha_uso</li>
                <li><strong>Horario:</strong> $hora_ent - $hora_sal</li>
            </ul>
            <br>";
        }

        $body = "
            <h2>¡Tu reserva ha sido aprobada!</h2>
            <p>Nos complace informarte que tu solicitud de reserva con el número de folio <strong>#$re_id</strong> ha sido <strong>autorizada</strong>.</p>
            $detallesHtml
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

    /**
     * @summary Envía un correo con el enlace para restablecer la contraseña.
     * 
     * @param string $to Correo del usuario.
     * @param string $token Token de recuperación seguro.
     */
    public function sendPasswordRecovery($to, $token) {
        $subject = "Recuperación de Contraseña - SIGRAT";
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        
        // Detectar automáticamente la carpeta base del proyecto (ej: /creaciones antigravity/Estadias)
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $basePos = strpos($scriptPath, '/backend/');
        $basePath = ($basePos !== false) ? substr($scriptPath, 0, $basePos) : '/Estadias';
        
        $resetLink = $protocol . "://" . $host . $basePath . "/frontend/recuperar_password.php?token=" . $token;
        $resetLink = str_replace(' ', '%20', $resetLink); // Codificar espacios en la URL
        
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #1E335F;'>Recuperación de Contraseña</h2>
                <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en el sistema SIGRAT.</p>
                <p>Para crear una nueva contraseña, haz clic en el siguiente enlace:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' style='background-color: #1E335F; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Restablecer Contraseña</a>
                </div>
                <p style='color: #6c757d; font-size: 14px;'>Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:</p>
                <p style='color: #6c757d; font-size: 13px; word-break: break-all;'><a href='$resetLink'>$resetLink</a></p>
                <br>
                <p style='color: #ef4444; font-size: 14px;'><strong>Atención:</strong> Este enlace expirará en 1 hora.</p>
                <p style='font-size: 14px;'>Si no solicitaste este cambio, puedes ignorar este correo.</p>
                <br>
                <p style='font-size: 14px;'>Saludos cordiales,<br>Equipo SIGRAT</p>
            </div>
        ";
        return $this->sendEmail($to, $subject, $body);
    }
}
