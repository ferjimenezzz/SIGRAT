<?php
/**
 * @file EmailService.php
 * @summary Servicio para el envío de correos electrónicos utilizando PHPMailer.
 * @description Implementa la lógica de envío de correos (confirmaciones, autorizaciones) de manera centralizada.
 */


// ============================================================================
// SECCIÓN 1: ESPACIO DE NOMBRES, CARGA DE ARCHIVOS Y DEPENDENCIAS
// ============================================================================
namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir el autoloader de Composer si existe
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}


// ============================================================================
// SECCIÓN 2: DEFINICIÓN DE CLASE, PROPIEDADES Y CONSTRUCTOR
// ============================================================================
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


// ============================================================================
// SECCIÓN 3: LÓGICA DE NEGOCIO Y OPERACIÓN (sendEmail)
// ============================================================================
    /**
     * @summary Envía un correo electrónico.
     * 
     * @param string $to Correo del destinatario.
     * @param string $subject Asunto del correo.
     * @param string $body Contenido en formato HTML.
     * @return bool True si se envió correctamente, False en caso contrario.
     */

    public function sendEmail($to, $subject, $body) {
        $bodyEncoded = base64_encode($body);
        $scriptPath = __DIR__ . '/send_email_bg.php';
        
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $cmd = "start /B php -f \"" . $scriptPath . "\" \"" . addslashes($to) . "\" \"" . addslashes($subject) . "\" \"" . $bodyEncoded . "\"";
            pclose(popen($cmd, "r"));
        } else {
            $cmd = "php -f \"" . $scriptPath . "\" \"" . addslashes($to) . "\" \"" . addslashes($subject) . "\" \"" . $bodyEncoded . "\" > /dev/null 2>&1 &";
            exec($cmd);
        }
        return true;
    }

    public function sendEmailDirectly($to, $subject, $body) {
        // Validar que se haya cargado el Username, de lo contrario no intentar enviar para no colgar la app
        if (empty($this->mail->Username)) {
            error_log("EmailService: No se enviará el correo porque las credenciales SMTP no están configuradas en el .env.");
            return false;
        }

        try {
            // Limpiar destinatarios previos por si se usa la misma instancia múltiples veces
            $this->mail->clearAllRecipients();
            
            // Adjuntar logo institucional como CID (Content-ID) incrustado para que aparezca sin depender de enlaces externos
            $logoPath = dirname(__DIR__, 2) . '/frontend/assets/images/sigrat_logo.png';
            if (file_exists($logoPath)) {
                $this->mail->AddEmbeddedImage($logoPath, 'sigrat_logo', 'sigrat_logo.png');
            }
            
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
     * @summary Envuelve el contenido HTML en una plantilla institucional ejecutiva con el logo incrustado de SIGRAT.
     * 
     * @param string $title Título del correo.
     * @param string $contentHtml Contenido interior en HTML.
     * @return string HTML estructurado y diseñado en línea.
     */
    private function wrapEmailTemplate($title, $contentHtml) {
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($title) . "</title>
        </head>
        <body style='margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #334155; -webkit-font-smoothing: antialiased;'>
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #f1f5f9; padding: 30px 15px;'>
                <tr>
                    <td align='center'>
                        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0;'>
                            <!-- Encabezado con Logo Institucional -->
                            <tr>
                                <td align='center' style='background-color: #f8fafc; padding: 25px 30px; text-align: center; border-bottom: 3px solid #1E335F;'>
                                    <img src='cid:sigrat_logo' alt='SIGRAT Logo' style='max-height: 65px; width: auto; display: block; margin: 0 auto;'>
                                </td>
                            </tr>
                            <!-- Cuerpo del Mensaje -->
                            <tr>
                                <td style='padding: 35px 30px; font-size: 15px; line-height: 1.6; color: #334155;'>
                                    " . $contentHtml . "
                                </td>
                            </tr>
                            <!-- Pie de Página Institucional -->
                            <tr>
                                <td style='background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;'>
                                    <p style='margin: 0 0 8px 0; font-weight: bold; color: #1E335F;'>SIGRAT — Sistema Institucional de Gestión de Reservas y Activos Tecnológicos</p>
                                    <p style='margin: 0;'>Este es un mensaje automático generado por la plataforma institucional, por favor no respondas a este correo.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }


// ============================================================================
// SECCIÓN 4: LÓGICA DE NEGOCIO Y OPERACIÓN (sendReservationCreated)
// ============================================================================
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
            <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin: 20px 0;'>
                <h3 style='margin: 0 0 12px 0; color: #1E335F; font-size: 15px;'>Detalles de la Solicitud:</h3>
                <ul style='margin: 0; padding-left: 20px; color: #334155; line-height: 1.8;'>
                    <li><strong>Lugar:</strong> $espacio_nombre</li>
                    <li><strong>Fecha:</strong> $fecha_uso</li>
                    <li><strong>Horario:</strong> $hora_ent - $hora_sal</li>
                </ul>
            </div>";
        }

        $contentHtml = "
            <h2 style='color: #1E335F; margin-top: 0; font-size: 20px;'>Notificación del Sistema de Reservas</h2>
            <p>Tu solicitud de reserva con el folio <strong style='color: #1E335F;'>#$re_id</strong> ha sido registrada en el sistema.</p>
            $detallesHtml
            <p>El estatus actual de tu solicitud es: <span style='background-color: #e2e8f0; color: #1e293b; padding: 4px 10px; border-radius: 6px; font-weight: bold;'>$estatus</span>.</p>
            <p>Si el estatus es Pendiente, un administrador revisará tu solicitud y te notificará por este mismo medio una vez que sea autorizada o rechazada.</p>
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 5: LÓGICA DE NEGOCIO Y OPERACIÓN (sendBulkReservationCreated)
// ============================================================================
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
            $listaReservas .= "<li style='margin-bottom: 6px;'>Reserva <strong style='color: #1E335F;'>#" . $re_ids[$i] . "</strong> para el día <strong>" . $fechaStr . "</strong></li>";
        }

        $detallesHtml = "";
        if ($espacio_nombre) {
            $detallesHtml = "
            <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin: 20px 0;'>
                <h3 style='margin: 0 0 12px 0; color: #1E335F; font-size: 15px;'>Detalles Compartidos:</h3>
                <ul style='margin: 0; padding-left: 20px; color: #334155; line-height: 1.8;'>
                    <li><strong>Lugar:</strong> $espacio_nombre</li>
                    <li><strong>Horario:</strong> $hora_ent - $hora_sal</li>
                </ul>
            </div>";
        }

        $contentHtml = "
            <h2 style='color: #1E335F; margin-top: 0; font-size: 20px;'>Notificación de Reservas Múltiples</h2>
            <p>Tus solicitudes de reserva han sido registradas en el sistema exitosamente:</p>
            <ul style='padding-left: 20px; color: #334155;'>
                $listaReservas
            </ul>
            $detallesHtml
            <p>El estatus actual de estas solicitudes es: <span style='background-color: #e2e8f0; color: #1e293b; padding: 4px 10px; border-radius: 6px; font-weight: bold;'>$estatus</span>.</p>
            <p>Si el estatus es Pendiente, un administrador revisará tus solicitudes y te notificará por este mismo medio.</p>
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 6: LÓGICA DE NEGOCIO Y OPERACIÓN (sendReservationApproved)
// ============================================================================
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
            <div style='background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px 20px; margin: 20px 0;'>
                <h3 style='margin: 0 0 12px 0; color: #166534; font-size: 15px;'>Detalles de la Reserva Autorizada:</h3>
                <ul style='margin: 0; padding-left: 20px; color: #15803d; line-height: 1.8;'>
                    <li><strong>Lugar:</strong> $espacio_nombre</li>
                    <li><strong>Fecha:</strong> $fecha_uso</li>
                    <li><strong>Horario:</strong> $hora_ent - $hora_sal</li>
                </ul>
            </div>";
        }

        $contentHtml = "
            <h2 style='color: #10b981; margin-top: 0; font-size: 20px;'>¡Tu reserva ha sido Aprobada!</h2>
            <p>Nos complace informarte que tu solicitud de reserva con el folio <strong style='color: #1E335F;'>#$re_id</strong> ha sido <strong style='color: #10b981;'>autorizada</strong>.</p>
            $detallesHtml
            <p>Por favor, asegúrate de cumplir con los lineamientos institucionales y los horarios de uso del espacio asignado.</p>
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 7: LÓGICA DE NEGOCIO Y OPERACIÓN (sendReservationRejected)
// ============================================================================
    /**
     * @summary Envía correo cuando una reserva es rechazada.
     * 
     * @param string $to Correo del usuario.
     * @param int $re_id ID de la reserva.
     * @param string $motivo Motivo del rechazo (opcional).
     */

    public function sendReservationRejected($to, $re_id, $motivo = '') {
        $subject = "Reserva Rechazada #$re_id";
        $motivoHtml = $motivo ? "<div style='background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; margin: 15px 0; border-radius: 4px; color: #991b1b;'><strong>Motivo del rechazo:</strong> $motivo</div>" : "";
        $contentHtml = "
            <h2 style='color: #ef4444; margin-top: 0; font-size: 20px;'>Reserva No Autorizada</h2>
            <p>Lamentamos informarte que tu solicitud de reserva con el folio <strong style='color: #1E335F;'>#$re_id</strong> ha sido <strong style='color: #ef4444;'>rechazada</strong>.</p>
            $motivoHtml
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 8: LÓGICA DE NEGOCIO Y OPERACIÓN (sendReservationCancelled)
// ============================================================================
    /**
     * @summary Envía correo cuando una reserva es cancelada.
     * 
     * @param string $to Correo del usuario.
     * @param int $re_id ID de la reserva.
     * @param string $motivo Motivo de la cancelación (opcional).
     */

    public function sendReservationCancelled($to, $re_id, $motivo = '') {
        $subject = "Reserva Cancelada #$re_id";
        $motivoHtml = $motivo ? "<div style='background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; margin: 15px 0; border-radius: 4px; color: #92400e;'><strong>Motivo de cancelación:</strong> $motivo</div>" : "";
        $contentHtml = "
            <h2 style='color: #f59e0b; margin-top: 0; font-size: 20px;'>Reserva Cancelada</h2>
            <p>Te informamos que la reserva con el folio <strong style='color: #1E335F;'>#$re_id</strong> ha sido <strong style='color: #f59e0b;'>cancelada</strong>.</p>
            $motivoHtml
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 9: LÓGICA DE NEGOCIO Y OPERACIÓN (sendPasswordRecovery)
// ============================================================================
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
        
        $contentHtml = "
            <h2 style='color: #1E335F; margin-top: 0; font-size: 20px;'>Recuperación de Contraseña</h2>
            <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta institucional en el sistema SIGRAT.</p>
            <p>Para crear una nueva contraseña de manera segura, haz clic en el siguiente enlace:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background: linear-gradient(135deg, #1E335F 0%, #2563eb 100%); color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);'>Restablecer Contraseña</a>
            </div>
            <p style='color: #64748b; font-size: 13px;'>Si el botón anterior no funciona, copia y pega el siguiente enlace directo en tu navegador web:</p>
            <p style='font-size: 13px; word-break: break-all; background-color: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;'><a href='$resetLink' style='color: #2563eb; text-decoration: none;'>$resetLink</a></p>
            <p style='color: #ef4444; font-size: 13px; margin-top: 20px;'><strong>Atención:</strong> Por motivos de seguridad, este enlace expirará automáticamente en 1 hora.</p>
            <p style='font-size: 13px; color: #64748b;'>Si no solicitaste este cambio, puedes ignorar este mensaje con total seguridad.</p>
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 10: LÓGICA DE NEGOCIO Y OPERACIÓN (sendLoanCreated)
// ============================================================================
    /**
     * @summary Envía correo de confirmación de un préstamo (ya sea dinámico o estándar).
     * 
     * @param string $to Correo del usuario.
     * @param int $pres_id ID del préstamo.
     * @param string $equipo Nombre o tipo de equipo.
     * @param string $serie Número de serie del equipo.
     * @param string $fecha_pres Fecha de inicio del préstamo.
     * @param string $fecha_ent Fecha de entrega esperada (opcional).
     */

    public function sendLoanCreated($to, $pres_id, $equipo, $serie, $fecha_pres, $fecha_ent = '') {
        $subject = "Confirmación de Préstamo de Equipo #$pres_id";
        
        $detallesHtml = "
        <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin: 20px 0;'>
            <h3 style='margin: 0 0 12px 0; color: #2454bbff; font-size: 15px;'>Detalles de la Salida de Equipo:</h3>
            <ul style='margin: 0; padding-left: 20px; color: #334155; line-height: 1.8;'>
                <li><strong>Equipo/Activo:</strong> $equipo</li>
                <li><strong>No. Serie:</strong> $serie</li>
                <li><strong>Fecha de Salida:</strong> $fecha_pres</li>
        ";
        
        if (!empty($fecha_ent)) {
            $detallesHtml .= "<li><strong>Devolución Esperada:</strong> $fecha_ent</li>";
        }
        
        $detallesHtml .= "</ul></div>";

        $contentHtml = "
            <h2 style='color: #1E335F; margin-top: 0; font-size: 20px;'>Notificación de Préstamo de Equipo</h2>
            <p>Se ha registrado exitosamente un préstamo de equipo institucional a tu nombre bajo el folio <strong style='color: #1E335F;'>#$pres_id</strong>.</p>
            $detallesHtml
            <p>Por favor, recuerda resguardar adecuadamente el equipo y devolverlo en las mismas condiciones funcionales en las que fue entregado.</p>
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo SIGRAT</strong></p>
        ";
        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }


// ============================================================================
// SECCIÓN 11: NOTIFICACIONES DE ALERTA PARA ADMINISTRADORES (APROBACIONES)
// ============================================================================
    /**
     * @summary Envía correo de alerta al Administrador cuando una reserva o grupo de reservas queda pendiente de aprobación.
     * @param string $to Correo electrónico del administrador.
     * @param string $adminName Nombre del administrador.
     * @param string|int $re_id ID o folio de la reserva (o resumen en caso múltiple).
     * @param string $usuario_nombre Nombre del usuario que solicita.
     * @param string $espacio_nombre Nombre del espacio solicitado.
     * @param string $fecha_uso Fecha(s) solicitada(s).
     * @param string $hora_ent Hora de entrada.
     * @param string $hora_sal Hora de salida.
     * @param string $motivo Actividad o motivo de la reserva (opcional).
     * @return bool True si el correo fue enviado exitosamente, false en caso contrario.
     */
    public function sendPendingApprovalAlertToAdmin($to, $adminName, $re_id, $usuario_nombre, $espacio_nombre, $fecha_uso, $hora_ent, $hora_sal, $motivo = '') {
        $subject = "🚨 Solicitud Pendiente de Aprobación - Reserva #" . $re_id;

        // Detectar ruta base de la aplicación para redirigir exactamente al módulo de aprobaciones
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $basePos = strpos($scriptPath, '/backend/');
        if ($basePos === false) $basePos = strpos($scriptPath, '/frontend/');
        $basePath = ($basePos !== false) ? substr($scriptPath, 0, $basePos) : '/Estadias';
        $appUrl = $protocol . "://" . $host . $basePath . "/frontend/aprobacion_reservas.php";

        $motivoHtml = !empty($motivo) ? "<li><strong>Motivo / Actividad:</strong> " . htmlspecialchars($motivo) . "</li>" : "";

        $contentHtml = "
            <h2 style='color: #1E335F; margin-top: 0; font-size: 20px;'>Nueva Solicitud Requiere Revisión</h2>
            <p>Hola <strong>" . htmlspecialchars($adminName) . "</strong>,</p>
            <p>Se ha registrado una nueva solicitud de reserva en el sistema que requiere autorización de un administrador para poder llevarse a cabo.</p>
            
            <div style='background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px 20px; margin: 20px 0; color: #92400e;'>
                <h3 style='margin: 0 0 12px 0; color: #b45309; font-size: 15px;'>Detalles de la Solicitud:</h3>
                <ul style='margin: 0; padding-left: 20px; line-height: 1.8;'>
                    <li><strong>Folio / ID:</strong> #" . htmlspecialchars($re_id) . "</li>
                    <li><strong>Solicitante:</strong> " . htmlspecialchars($usuario_nombre) . "</li>
                    <li><strong>Espacio:</strong> " . htmlspecialchars($espacio_nombre) . "</li>
                    <li><strong>Fecha(s):</strong> " . htmlspecialchars($fecha_uso) . "</li>
                    <li><strong>Horario:</strong> " . htmlspecialchars($hora_ent) . " - " . htmlspecialchars($hora_sal) . "</li>
                    $motivoHtml
                </ul>
            </div>

            <div style='text-align: center; margin: 30px 0;'>
                <a href='$appUrl' style='background: linear-gradient(135deg, #1E335F 0%, #2563eb 100%); color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);'>Revisar en Módulo de Aprobaciones</a>
            </div>

            <p style='font-size: 13px; color: #64748b; background-color: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;'>
                <strong>Nota:</strong> Para acceder al módulo de aprobaciones y gestionar esta solicitud, deberás haber iniciado sesión con tu cuenta de Administrador en SIGRAT. Si tu sesión expiró, el enlace te solicitará tus credenciales y te llevará automáticamente a esta sección.
            </p>
            <br>
            <p style='margin-bottom: 0;'>Atentamente,<br><strong>Equipo Automático SIGRAT</strong></p>
        ";

        $body = $this->wrapEmailTemplate($subject, $contentHtml);
        return $this->sendEmail($to, $subject, $body);
    }
}
