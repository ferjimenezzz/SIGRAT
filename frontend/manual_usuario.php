<?php
/**
 * @file manual_usuario.php
 * @summary Manual de Usuario SIGRAT — optimizado para impresión/guardado como PDF.
 */
$fechaGeneracion = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual de Usuario — SIGRAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-dark:  #1e3a8a;
            --blue:       #2563eb;
            --blue-light: #eff6ff;
            --gray-100:   #f8fafc;
            --gray-200:   #f1f5f9;
            --gray-300:   #e2e8f0;
            --gray-500:   #64748b;
            --gray-700:   #334155;
            --gray-900:   #0f172a;
            --amber-bg:   #fffbeb;
            --amber-bd:   #fde68a;
            --amber-text: #78350f;
            --green-bg:   #dcfce7;
            --green-text: #166534;
            --radius:     12px;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--gray-700);
            background: #ffffff;
            font-size: 13px;
            line-height: 1.65;
        }

        /* ===================== PORTADA ===================== */
        .cover {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(145deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);
            text-align: center;
            padding: 60px 40px;
            page-break-after: always;
        }
        .cover-logo-ring {
            width: 96px; height: 96px;
            border-radius: 24px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.20);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 28px;
        }
        .cover-logo-ring img {
            width: 60px; height: 60px; object-fit: contain;
            filter: brightness(0) invert(1);
        }
        .cover-logo-text {
            font-size: 38px; font-weight: 900; color: white; letter-spacing: -1px;
        }
        .cover-tag {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.22);
            color: rgba(255,255,255,0.75);
            padding: 5px 18px; border-radius: 20px;
            font-size: 10px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            margin-bottom: 22px;
        }
        .cover-title {
            font-size: 44px; font-weight: 900; color: #ffffff;
            line-height: 1.1; letter-spacing: -1.5px; margin-bottom: 10px;
        }
        .cover-subtitle {
            font-size: 15px; color: rgba(255,255,255,0.60);
            font-weight: 500; margin-bottom: 52px; max-width: 480px;
        }
        .cover-meta {
            display: flex; gap: 48px;
            border-top: 1px solid rgba(255,255,255,0.15);
            padding-top: 30px;
        }
        .cover-meta-item { text-align: center; }
        .cover-meta-label {
            font-size: 9px; color: rgba(255,255,255,0.40);
            text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;
            margin-bottom: 5px;
        }
        .cover-meta-value {
            font-size: 14px; color: rgba(255,255,255,0.90); font-weight: 700;
        }

        /* ===================== WRAPPER ===================== */
        .content-wrap { max-width: 820px; margin: 0 auto; padding: 0 56px 72px; }

        /* ===================== TABLA DE CONTENIDOS ===================== */
        .toc { padding: 64px 0 48px; page-break-after: always; }
        .toc-heading {
            font-size: 26px; font-weight: 900; color: var(--gray-900);
            letter-spacing: -0.5px; margin-bottom: 6px;
        }
        .toc-rule {
            width: 44px; height: 4px; border-radius: 3px;
            background: var(--blue); margin-bottom: 32px;
        }
        .toc-list { list-style: none; }
        .toc-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; padding: 9px 0;
            border-bottom: 1px dashed var(--gray-300);
        }
        .toc-row:last-child { border-bottom: none; }
        .toc-left { display: flex; align-items: center; gap: 12px; }
        .toc-num {
            width: 26px; height: 26px; border-radius: 7px;
            background: var(--blue-light); color: var(--blue);
            font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .toc-name { font-size: 13.5px; font-weight: 600; color: var(--gray-900); }
        .toc-page { font-size: 11px; color: var(--gray-500); font-weight: 600; }

        /* ===================== SECCIONES ===================== */
        .section { page-break-before: always; padding-top: 64px; }

        .section-label {
            font-size: 10px; font-weight: 800; color: var(--blue);
            text-transform: uppercase; letter-spacing: 1.5px;
            margin-bottom: 10px; display: block;
        }
        .section-title {
            font-size: 24px; font-weight: 900; color: var(--gray-900);
            letter-spacing: -0.4px; margin-bottom: 8px;
        }
        .section-intro {
            font-size: 14px; color: var(--gray-500); font-weight: 400;
            line-height: 1.7; margin-bottom: 28px;
            padding-bottom: 22px; border-bottom: 1px solid var(--gray-300);
        }

        /* Sub-etiqueta de bloque */
        .block-label {
            font-size: 10px; font-weight: 800; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px;
            margin-bottom: 12px; display: block;
        }

        /* Lista de funciones */
        .func-list { list-style: none; margin-bottom: 28px; }
        .func-row {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 9px 0; border-bottom: 1px solid var(--gray-200);
            font-size: 13px; color: var(--gray-700); line-height: 1.55;
        }
        .func-row:last-child { border-bottom: none; }
        .func-mark {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--blue); flex-shrink: 0; margin-top: 6px;
        }

        /* Cuadros de consejo */
        .tip-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 28px; }
        .tip-box {
            background: var(--amber-bg); border: 1px solid var(--amber-bd);
            border-left: 3px solid #f59e0b;
            border-radius: 8px; padding: 11px 14px;
            font-size: 12px; color: var(--amber-text); font-weight: 500; line-height: 1.55;
        }
        .tip-label {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: #b45309; margin-bottom: 3px; display: block;
        }

        /* Cuadros informativos */
        .info-box {
            background: var(--blue-light);
            border: 1px solid #bfdbfe; border-left: 3px solid var(--blue);
            border-radius: 8px; padding: 12px 16px;
            font-size: 13px; color: #1d4ed8; font-weight: 500; line-height: 1.6;
            margin-bottom: 20px;
        }
        .note-label {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 3px; display: block; color: #1e40af;
        }
        .success-box {
            background: var(--green-bg); border: 1px solid #bbf7d0;
            border-left: 3px solid #16a34a;
            border-radius: 8px; padding: 12px 16px;
            font-size: 13px; color: var(--green-text); font-weight: 500; line-height: 1.6;
            margin-bottom: 20px;
        }

        /* Tabla de estados */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table thead tr { background: var(--blue-dark); }
        .data-table th {
            padding: 10px 14px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: white; text-align: left;
        }
        .data-table th:first-child { border-radius: 8px 0 0 0; }
        .data-table th:last-child  { border-radius: 0 8px 0 0; }
        .data-table td {
            padding: 9px 14px; font-size: 12px;
            border-bottom: 1px solid var(--gray-200); color: var(--gray-700);
        }
        .data-table tr:nth-child(even) td { background: var(--gray-100); }

        /* Badge de estado */
        .badge {
            display: inline-block; padding: 2px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 700;
        }
        .badge-green  { background: #dcfce7; color: #16a34a; }
        .badge-orange { background: #ffedd5; color: #c2410c; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .badge-red    { background: #fee2e2; color: #991b1b; }

        /* ===================== PIE DE PAGINA ===================== */
        footer.doc-footer {
            border-top: 1px solid var(--gray-300);
            background: var(--gray-100);
            text-align: center;
            padding: 28px 56px;
            margin-top: 60px;
        }
        footer.doc-footer p { font-size: 11px; color: var(--gray-500); }
        footer.doc-footer strong { color: var(--gray-900); }

        /* ===================== BARRA DE ACCIONES (solo pantalla) ===================== */
        .action-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: white; border-bottom: 1px solid var(--gray-300);
            padding: 11px 24px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .action-bar-brand { font-size: 14px; font-weight: 800; color: var(--gray-900); }
        .action-bar-brand span { color: var(--blue); }
        .action-bar-btns { display: flex; gap: 10px; }
        .btn-print {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: white; border: none; padding: 9px 20px;
            border-radius: 9px; font-size: 13px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            display: flex; align-items: center; gap: 7px;
        }
        .btn-print:hover { opacity: 0.88; }
        .btn-back {
            background: var(--gray-200); color: var(--gray-700);
            border: none; padding: 9px 20px; border-radius: 9px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            font-family: inherit; text-decoration: none;
            display: flex; align-items: center; gap: 7px;
        }
        .main-doc { padding-top: 60px; }

        @media print {
            .action-bar { display: none !important; }
            .main-doc { padding-top: 0; }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <div class="action-bar-brand"><span>SIGRAT</span> &mdash; Manual de Usuario</div>
        <div class="action-bar-btns">
            <a href="javascript:window.close()" class="btn-back">&#8592; Cerrar</a>
            <button class="btn-print" onclick="window.print()">Guardar / Imprimir como PDF</button>
        </div>
    </div>

    <div class="main-doc">

        <!-- PORTADA -->
        <div class="cover">
            <div class="cover-logo-ring">
                <?php if (file_exists(__DIR__ . '/assets/images/sigrat_logo.png')): ?>
                    <img src="assets/images/sigrat_logo.png" alt="SIGRAT">
                <?php else: ?>
                    <div class="cover-logo-text">S</div>
                <?php endif; ?>
            </div>
            <div class="cover-tag">Documento Oficial &mdash; Uso Interno</div>
            <div class="cover-title">Manual de Usuario<br>SIGRAT</div>
            <div class="cover-subtitle">Sistema Integral de Gestión de Recursos y Actividades Tecnológicas</div>
            <div class="cover-meta">
                <div class="cover-meta-item">
                    <div class="cover-meta-label">Versión</div>
                    <div class="cover-meta-value">1.0</div>
                </div>
                <div class="cover-meta-item">
                    <div class="cover-meta-label">Fecha</div>
                    <div class="cover-meta-value"><?php echo $fechaGeneracion; ?></div>
                </div>
                <div class="cover-meta-item">
                    <div class="cover-meta-label">Clasificación</div>
                    <div class="cover-meta-value">Confidencial</div>
                </div>
            </div>
        </div>

        <div class="content-wrap">

            <!-- ÍNDICE -->
            <div class="toc">
                <div class="toc-heading">Tabla de Contenidos</div>
                <div class="toc-rule"></div>
                <ul class="toc-list">
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">1</div><span class="toc-name">Introducción al Sistema</span></div><span class="toc-page">3</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">2</div><span class="toc-name">Acceso y Sesión</span></div><span class="toc-page">4</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">3</div><span class="toc-name">Dashboard — Pantalla Principal</span></div><span class="toc-page">5</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">4</div><span class="toc-name">Módulo de Calendario</span></div><span class="toc-page">6</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">5</div><span class="toc-name">Gestión de Usuarios</span></div><span class="toc-page">7</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">6</div><span class="toc-name">Módulo de Espacios</span></div><span class="toc-page">8</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">7</div><span class="toc-name">Aprobaciones de Reservas</span></div><span class="toc-page">9</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">8</div><span class="toc-name">Módulo de Préstamos</span></div><span class="toc-page">10</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">9</div><span class="toc-name">Inventario de Activos</span></div><span class="toc-page">11</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">10</div><span class="toc-name">Módulo de Auditoría</span></div><span class="toc-page">12</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">11</div><span class="toc-name">Monitor RFID</span></div><span class="toc-page">13</span></div></li>
                    <li><div class="toc-row"><div class="toc-left"><div class="toc-num">12</div><span class="toc-name">Preguntas Frecuentes</span></div><span class="toc-page">14</span></div></li>
                </ul>
            </div>

            <!-- 1. INTRODUCCIÓN -->
            <div class="section">
                <span class="section-label">Sección 1</span>
                <h2 class="section-title">Introducción al Sistema</h2>
                <p class="section-intro">
                    SIGRAT (Sistema Integral de Gestión de Recursos y Actividades Tecnológicas) es una plataforma web institucional diseñada para centralizar y optimizar la administración de espacios físicos, activos tecnológicos, usuarios y actividades. Proporciona control completo sobre reservas, préstamos de equipos, inventario y trazabilidad de operaciones.
                </p>
                <div class="info-box">
                    <span class="note-label">Aviso de confidencialidad</span>
                    Este manual es de uso interno. La información aquí contenida es confidencial y está destinada exclusivamente al personal autorizado de la institución.
                </div>
                <span class="block-label">Módulos del sistema</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>Dashboard</strong> — Vista general con métricas, alertas y accesos rápidos.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Calendario</strong> — Gestión y reserva de espacios físicos.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Usuarios</strong> — Administración de cuentas, roles y permisos de acceso.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Espacios</strong> — Catálogo de aulas, laboratorios y salas disponibles.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Aprobaciones</strong> — Autorización de solicitudes de reserva por administradores.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Préstamos</strong> — Control del ciclo completo de préstamo de activos.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Inventario</strong> — Catálogo maestro de activos institucionales.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Auditoría</strong> — Trazabilidad, reportes y análisis del sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Monitor RFID</strong> — Integración con lectores de tarjetas físicos.</div></li>
                </ul>
            </div>

            <!-- 2. ACCESO -->
            <div class="section">
                <span class="section-label">Sección 2</span>
                <h2 class="section-title">Acceso y Sesión</h2>
                <p class="section-intro">Para ingresar al sistema, el administrador debe proporcionarte tus credenciales: correo electrónico y contraseña. En el primer acceso, se utiliza una contraseña temporal que deberás cambiar de inmediato.</p>
                <span class="block-label">Pasos para iniciar sesión</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Abre tu navegador y navega a la dirección web del sistema SIGRAT de tu institución.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Ingresa tu <strong>correo electrónico</strong> y <strong>contraseña</strong> en el formulario de inicio de sesión.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>Iniciar Sesión</strong>. El sistema te redirigirá automáticamente al Dashboard.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Si es tu primer acceso, la contraseña temporal asignada es <strong>123456</strong>. Cámbiala de inmediato desde tu perfil.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo</span>
                        Si olvidaste tu contraseña, contacta al administrador del sistema para que la restablezca. No hay recuperación automática por correo.
                    </div>
                    <div class="tip-box">
                        <span class="tip-label">Buena práctica</span>
                        Usa siempre el botón "Cerrar Sesión" del menú lateral para salir de forma segura y evitar que alguien más acceda a tu cuenta.
                    </div>
                </div>
            </div>

            <!-- 3. DASHBOARD -->
            <div class="section">
                <span class="section-label">Sección 3</span>
                <h2 class="section-title">Dashboard — Pantalla Principal</h2>
                <p class="section-intro">El Dashboard es el punto de partida del sistema. Muestra un resumen ejecutivo del estado actual en tiempo real, con acceso directo a las principales funciones.</p>
                <span class="block-label">Elementos principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>Tarjetas de estadísticas</strong> — Totales de usuarios activos, reservas del día, préstamos en curso e inventario crítico.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Notificaciones</strong> — Icono de campana en la esquina superior derecha con alertas del sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Menú lateral</strong> — Navegación rápida hacia todos los módulos disponibles según tu rol.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Centro de Ayuda</strong> — Botón de signo de interrogación en la parte inferior del menú lateral, disponible en todo momento.</div></li>
                </ul>
            </div>

            <!-- 4. CALENDARIO -->
            <div class="section">
                <span class="section-label">Sección 4</span>
                <h2 class="section-title">Módulo de Calendario</h2>
                <p class="section-intro">Gestiona todas las reservas de espacios físicos. Visualiza la disponibilidad y crea solicitudes de uso de instalaciones en pocos pasos.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Crear nuevas reservas seleccionando espacio, fecha y horario deseado.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Visualizar todas las reservas activas en vista mensual, semanal o diaria.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Modificar o cancelar reservas existentes haciendo clic sobre el evento en el calendario.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Registrar asistencia a clases y eventos programados.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Consultar la disponibilidad de cualquier espacio en tiempo real.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo</span>
                        Haz clic en cualquier celda vacía del calendario para crear una reserva rápida en ese horario específico.
                    </div>
                    <div class="tip-box">
                        <span class="tip-label">Importante</span>
                        Las reservas que aún no han sido aprobadas aparecerán en un color diferente. Consulta el módulo de Aprobaciones para seguimiento.
                    </div>
                </div>
            </div>

            <!-- 5. USUARIOS -->
            <div class="section">
                <span class="section-label">Sección 5</span>
                <h2 class="section-title">Gestión de Usuarios</h2>
                <p class="section-intro">Administra todas las cuentas del sistema, asigna roles con permisos diferenciados y gestiona el acceso de visitantes mediante códigos de invitación. Disponible únicamente para administradores.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Crear usuarios internos con nombre, correo electrónico, rol y número de matrícula.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Editar información de perfil y reasignar el rol de cualquier usuario.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Desactivar cuentas (conservan historial pero no pueden iniciar sesión).</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Generar códigos de invitación para acceso de usuarios externos o visitantes.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Exportar el listado completo de usuarios a Excel o PDF.</div></li>
                </ul>
                <div class="success-box">
                    La contraseña inicial de todo usuario nuevo es <strong>123456</strong>. El usuario debe cambiarla obligatoriamente al primer inicio de sesión.
                </div>
            </div>

            <!-- 6. ESPACIOS -->
            <div class="section">
                <span class="section-label">Sección 6</span>
                <h2 class="section-title">Módulo de Espacios</h2>
                <p class="section-intro">Catálogo maestro de todos los espacios físicos disponibles en la institución: aulas, laboratorios, oficinas y salas de reunión. Disponible para administradores.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Registrar nuevos espacios con nombre, edificio, capacidad máxima y tipo.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Activar o desactivar espacios temporalmente (por ejemplo, durante mantenimiento).</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Consultar el historial de uso y reservas de cada espacio.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Eliminar espacios del catálogo cuando dejen de estar en servicio.</div></li>
                </ul>
            </div>

            <!-- 7. APROBACIONES -->
            <div class="section">
                <span class="section-label">Sección 7</span>
                <h2 class="section-title">Aprobaciones de Reservas</h2>
                <p class="section-intro">Centro de autorización de solicitudes de reserva. Los administradores revisan, aprueban o rechazan peticiones de uso antes de que se confirmen en el calendario.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Revisar solicitudes pendientes con todos sus detalles (solicitante, espacio, fecha, hora).</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Aprobar una o varias reservas de forma simultánea.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Rechazar solicitudes con un comentario que explique el motivo de la decisión.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Filtrar solicitudes por fecha, espacio o usuario solicitante.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Consultar historial completo de decisiones de aprobación.</div></li>
                </ul>
            </div>

            <!-- 8. PRÉSTAMOS -->
            <div class="section">
                <span class="section-label">Sección 8</span>
                <h2 class="section-title">Módulo de Préstamos</h2>
                <p class="section-intro">Controla el ciclo completo de préstamo de activos tecnológicos: desde la salida hasta la devolución, con registro del responsable, fechas y estado del activo.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Registrar un nuevo préstamo seleccionando el activo y el usuario solicitante.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Registrar devoluciones y actualizar el estado del activo correspondiente.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Consultar qué activos están actualmente prestados y quién los tiene.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Ver el historial completo de préstamos con fechas, estados y usuarios.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Exportar reportes de préstamos a PDF o Excel con formato institucional.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo</span>
                        Filtra por estado "En Curso" para identificar rápidamente los activos que aún no han sido devueltos y realizar seguimiento.
                    </div>
                </div>
            </div>

            <!-- 9. INVENTARIO -->
            <div class="section">
                <span class="section-label">Sección 9</span>
                <h2 class="section-title">Inventario de Activos</h2>
                <p class="section-intro">Catálogo maestro de todos los activos tecnológicos e institucionales. Permite su registro, actualización de estado, baja definitiva y seguimiento en tiempo real.</p>
                <span class="block-label">Estados de un activo</span>
                <table class="data-table" style="margin-bottom:24px;">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><span class="badge badge-green">Disponible</span></td><td>El activo está listo para ser prestado.</td></tr>
                        <tr><td><span class="badge badge-orange">Prestado</span></td><td>Actualmente en uso por un usuario registrado.</td></tr>
                        <tr><td><span class="badge badge-blue">Mantenimiento</span></td><td>En reparación; no disponible para préstamo.</td></tr>
                        <tr><td><span class="badge badge-red">Extraviado</span></td><td>No localizado; requiere seguimiento administrativo.</td></tr>
                    </tbody>
                </table>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Registrar activos con número de inventario, tipo, marca y modelo.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Actualizar el estado de cualquier activo registrado en el sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Dar de baja activos dañados o fuera de servicio de forma definitiva.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Exportar el inventario completo a Excel con formato profesional.</div></li>
                </ul>
            </div>

            <!-- 10. AUDITORÍA -->
            <div class="section">
                <span class="section-label">Sección 10</span>
                <h2 class="section-title">Módulo de Auditoría</h2>
                <p class="section-intro">Centro de trazabilidad y reportes. Registra automáticamente cada acción realizada en el sistema para análisis, cumplimiento normativo y seguimiento de incidencias.</p>
                <span class="block-label">Tipos de reporte disponibles</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>Actividad general</strong> — Registro cronológico de todas las acciones por usuario y módulo.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Asistencia a aulas</strong> — Historial de uso de espacios físicos por fecha y horario.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Espacios más utilizados</strong> — Ranking de instalaciones por frecuencia de uso.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Uso por edificio</strong> — Estadísticas agrupadas por edificio o área.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Préstamos</strong> — Historial detallado del módulo de préstamos de activos.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo</span>
                        Usa los presets de fecha disponibles (Hoy, Esta semana, Este mes) para acotar los resultados sin necesidad de seleccionar rangos manualmente.
                    </div>
                    <div class="tip-box">
                        <span class="tip-label">Buena práctica</span>
                        Exporta los reportes en PDF para entregarlos como documentos oficiales a directivos o instancias externas.
                    </div>
                </div>
            </div>

            <!-- 11. RFID -->
            <div class="section">
                <span class="section-label">Sección 11</span>
                <h2 class="section-title">Monitor RFID</h2>
                <p class="section-intro">Interfaz en tiempo real que conecta SIGRAT con lectores RFID y NFC físicos para el registro automático de asistencia y control de acceso a instalaciones.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Visualizar lecturas del lector RFID en tiempo real sin necesidad de recargar.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Registrar asistencia automáticamente al detectar una tarjeta asociada.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Asociar tarjetas RFID o NFC a usuarios existentes del sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Consultar el historial completo de accesos registrados por tarjeta.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Diagnosticar el estado de conexión del hardware lector.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Solución de problemas</span>
                        Si el monitor no muestra lecturas, verifica que el dispositivo RFID esté encendido y conectado correctamente al equipo o servidor.
                    </div>
                </div>
            </div>

            <!-- 12. FAQ -->
            <div class="section">
                <span class="section-label">Sección 12</span>
                <h2 class="section-title">Preguntas Frecuentes</h2>
                <p class="section-intro">Respuestas a las consultas más habituales de los usuarios del sistema SIGRAT.</p>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo recupero mi contraseña?</strong> — Contacta a tu administrador del sistema para que la restablezca manualmente.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Por qué no veo algunos módulos?</strong> — El acceso depende del rol asignado a tu cuenta. Solicita el acceso necesario a tu administrador.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo sé si mi reserva fue aprobada?</strong> — Recibirás una notificación en la campana de la barra superior cuando sea procesada.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Puedo acceder desde el celular?</strong> — Sí, SIGRAT es completamente adaptable a dispositivos móviles y tabletas.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo exporto un reporte?</strong> — Usa los botones "Exportar Excel" o "Exportar PDF" disponibles en la barra superior de cada módulo.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Qué significa "En Curso" en Préstamos?</strong> — El activo fue prestado y aún no se ha registrado su devolución en el sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Los datos se guardan automáticamente?</strong> — Sí, cada acción confirmada se guarda de inmediato. No dejes formularios a medio completar si vas a cerrar el navegador.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo reporto un error técnico?</strong> — Contacta al equipo de soporte de tu institución y describe el error con una captura de pantalla.</div></li>
                </ul>
            </div>

        </div><!-- /content-wrap -->
    </div><!-- /main-doc -->

    <footer class="doc-footer">
        <p>Generado automáticamente por <strong>SIGRAT</strong> &mdash; Sistema Integral de Gestión de Recursos y Actividades Tecnológicas</p>
        <p style="margin-top:4px;">Versión 1.0 &nbsp;&middot;&nbsp; <?php echo $fechaGeneracion; ?> &nbsp;&middot;&nbsp; Documento de uso interno y confidencial</p>
    </footer>

</body>
</html>
