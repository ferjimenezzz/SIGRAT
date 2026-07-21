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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

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
            gap: 10px; padding: 0;
            border-bottom: 1px dashed var(--gray-300);
        }
        .toc-row:last-child { border-bottom: none; }
        .toc-left { display: flex; align-items: center; gap: 12px; flex: 1; }
        .toc-icon {
            width: 30px; height: 30px; border-radius: 8px;
            background: var(--blue-light);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; color: var(--blue); font-size: 13px;
            transition: background 0.2s, color 0.2s;
        }
        .toc-num-badge {
            width: 20px; height: 20px; border-radius: 6px;
            background: var(--gray-200); color: var(--gray-500);
            font-size: 10px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: background 0.2s, color 0.2s;
        }
        .toc-link {
            flex: 1; display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; text-decoration: none;
            gap: 10px;
            transition: color 0.2s;
        }
        .toc-link:hover .toc-name { color: var(--blue); }
        .toc-row:hover .toc-icon { background: var(--blue); color: white; }
        .toc-row:hover .toc-num-badge { background: var(--blue-light); color: var(--blue); }
        .toc-name {
            font-size: 13.5px; font-weight: 600; color: var(--gray-900);
            transition: color 0.2s;
        }
        .toc-section-num {
            font-size: 10px; color: var(--gray-500); font-weight: 700;
            white-space: nowrap; letter-spacing: 0.3px;
        }

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

        /* ===================== BOTÓN VOLVER AL ÍNDICE ===================== */
        .back-to-index {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 32px;
            padding: 7px 16px;
            background: var(--gray-100);
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 11px; font-weight: 700; color: var(--gray-500);
            text-decoration: none;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .back-to-index:hover {
            background: var(--blue-light);
            color: var(--blue);
            border-color: #bfdbfe;
        }
        .back-to-index i { font-size: 12px; }

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

        /* ===================== OPTIMIZACIÓN PARA IMPRESIÓN ===================== */
        @media print {
            html { scroll-behavior: auto; }
            .action-bar { display: none !important; }
            .back-to-index { display: none !important; }
            .main-doc { padding-top: 0; }
            .toc-link { color: inherit; text-decoration: none; }
            .toc-row:hover .toc-icon { background: var(--blue-light); color: var(--blue); }
            .section { page-break-before: always; break-before: page; }
            .data-table { page-break-inside: avoid; break-inside: avoid; }
            .tip-box, .info-box, .success-box { page-break-inside: avoid; break-inside: avoid; }
            a[href]::after { content: none !important; }
            @page {
                margin: 2cm 2.2cm;
                size: A4 portrait;
            }
            @page :first {
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <div class="action-bar-brand"><span>SIGRAT</span> &mdash; Manual de Usuario</div>
        <div class="action-bar-btns">
            <a href="javascript:window.close()" class="btn-back"><i class="bi bi-arrow-left"></i> Cerrar</a>
            <button class="btn-print" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> Exportar Manual a PDF</button>
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
            <div class="toc" id="toc">
                <div class="toc-heading">Tabla de Contenidos</div>
                <div class="toc-rule"></div>
                <ul class="toc-list">
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-info-circle-fill"></i></div>
                                <a class="toc-link" href="#sec-intro">
                                    <span class="toc-name">Introducción al Sistema</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">1</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-shield-lock-fill"></i></div>
                                <a class="toc-link" href="#sec-acceso">
                                    <span class="toc-name">Acceso y Sesión</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">2</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-grid-1x2-fill"></i></div>
                                <a class="toc-link" href="#sec-dashboard">
                                    <span class="toc-name">Dashboard &mdash; Pantalla Principal</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">3</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-calendar3"></i></div>
                                <a class="toc-link" href="#sec-calendario">
                                    <span class="toc-name">Módulo de Calendario</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">4</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-people-fill"></i></div>
                                <a class="toc-link" href="#sec-usuarios">
                                    <span class="toc-name">Gestión de Usuarios</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">5</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-geo-alt-fill"></i></div>
                                <a class="toc-link" href="#sec-espacios">
                                    <span class="toc-name">Módulo de Espacios</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">6</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-check2-square"></i></div>
                                <a class="toc-link" href="#sec-aprobaciones">
                                    <span class="toc-name">Aprobaciones de Reservas</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">7</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-arrow-left-right"></i></div>
                                <a class="toc-link" href="#sec-prestamos">
                                    <span class="toc-name">Módulo de Préstamos</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">8</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-box-seam-fill"></i></div>
                                <a class="toc-link" href="#sec-inventario">
                                    <span class="toc-name">Inventario de Activos</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">9</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-activity"></i></div>
                                <a class="toc-link" href="#sec-auditoria">
                                    <span class="toc-name">Módulo de Auditoría</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">10</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-broadcast"></i></div>
                                <a class="toc-link" href="#sec-rfid">
                                    <span class="toc-name">Monitor RFID</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">11</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-person-circle"></i></div>
                                <a class="toc-link" href="#sec-perfil">
                                    <span class="toc-name">Mi Perfil</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">12</div>
                        </div>
                    </li>
                    <li>
                        <div class="toc-row">
                            <div class="toc-left">
                                <div class="toc-icon"><i class="bi bi-question-circle-fill"></i></div>
                                <a class="toc-link" href="#sec-faq">
                                    <span class="toc-name">Preguntas Frecuentes</span>
                                </a>
                            </div>
                            <div class="toc-num-badge">13</div>
                        </div>
                    </li>

                </ul>
            </div>

            <!-- 1. INTRODUCCIÓN -->
            <div class="section" id="sec-intro">
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
                    <li class="func-row"><div class="func-mark"></div><div><strong>Dashboard</strong> — Vista general con métricas, alertas y accesos rápidos al sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Calendario</strong> — Gestión y reserva de espacios físicos con Plano Arquitectónico interactivo integrado en el formulario de reserva.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Usuarios</strong> — Administración de cuentas, roles y permisos de acceso (solo administradores).</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Espacios</strong> — Catálogo de aulas, laboratorios y salas disponibles con su configuración.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Aprobaciones</strong> — Autorización de solicitudes de reserva por administradores.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Préstamos</strong> — Control del ciclo completo de préstamo de activos tecnológicos.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Inventario</strong> — Catálogo maestro de activos institucionales con estados y exportaciones.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Auditoría</strong> — Trazabilidad, reportes de uso y análisis estadístico del sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Monitor RFID</strong> — Integración con lectores de tarjetas físicos para registro de asistencia.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Mi Perfil</strong> — Edición de datos personales y cambio de contraseña del usuario activo.</div></li>
                </ul>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 2. ACCESO -->
            <div class="section" id="sec-acceso">
                <span class="section-label">Sección 2</span>
                <h2 class="section-title">Acceso y Sesión</h2>
                <p class="section-intro">Para ingresar al sistema necesitas un correo institucional (@uteq.edu.mx) y una contraseña proporcionada por el administrador. El sistema valida las credenciales en tiempo real y establece una sesión segura con duración de 8 horas.</p>
                <span class="block-label">Pasos para iniciar sesión</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Abre tu navegador y navega a la dirección web del sistema SIGRAT de tu institución.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Ingresa tu <strong>correo electrónico institucional</strong> (@uteq.edu.mx) y tu <strong>contraseña</strong> en el formulario de inicio de sesión.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>Iniciar Sesión</strong>. El sistema te redirigirá automáticamente al Dashboard.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Si el sistema muestra "Credenciales incorrectas o usuario inactivo", verifica que tu correo y contraseña sean correctos o que tu cuenta esté activa.</div></li>
                </ul>
                <span class="block-label">Recuperación de contraseña</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>En la pantalla de inicio de sesión, haz clic en el enlace <strong>«¿Olvidaste tu contraseña?»</strong> debajo del campo de contraseña.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Ingresa tu correo institucional y el sistema te enviará un enlace de restablecimiento a tu bandeja de entrada.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Abre el correo recibido, haz clic en el enlace y define una nueva contraseña de al menos 6 caracteres.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>También puedes solicitar al administrador que restablezca tu contraseña directamente desde el módulo de Usuarios.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Buena práctica</span>
                        Usa siempre el botón «Cerrar Sesión» del menú lateral para salir de forma segura y evitar accesos no autorizados desde el mismo navegador.
                    </div>
                    <div class="tip-box">
                        <span class="tip-label">Seguridad</span>
                        Tu sesión expira automáticamente después de 8 horas de inactividad. Si el sistema te redirige al login inesperadamente, tu sesión venció; vuelve a ingresar tus credenciales.
                    </div>
                </div>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 3. DASHBOARD -->
            <div class="section" id="sec-dashboard">
                <span class="section-label">Sección 3</span>
                <h2 class="section-title">Dashboard — Pantalla Principal</h2>
                <p class="section-intro">El Dashboard es el punto de partida del sistema. Muestra un resumen ejecutivo del estado actual en tiempo real, con acceso directo a las principales funciones.</p>
                <span class="block-label">Elementos principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>Tarjetas de estadísticas</strong> — Totales de usuarios activos, reservas del día, préstamos en curso e inventario crítico.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Notificaciones</strong> — Icono de campana en la esquina superior derecha. Cuando hay mensajes sin leer, aparece un indicador azul sobre el ícono.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Menú lateral</strong> — Navegación rápida hacia todos los módulos disponibles según tu rol.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Centro de Ayuda</strong> — Botón de signo de interrogación en la parte inferior del menú lateral, disponible en todo momento.</div></li>
                </ul>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 4. CALENDARIO -->
            <div class="section" id="sec-calendario">
                <span class="section-label">Sección 4</span>
                <h2 class="section-title">Módulo de Calendario</h2>
                <p class="section-intro">Gestiona todas las reservas de espacios físicos de la institución. Permite visualizar la disponibilidad de aulas y laboratorios, crear solicitudes de uso y dar seguimiento a su aprobación.</p>
                <span class="block-label">Cómo crear una reserva</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en el botón <strong>«+ Nueva Reserva»</strong> o en cualquier celda vacía del calendario.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>El formulario abre un <strong>Plano Arquitectónico interactivo</strong> a la derecha. Selecciona el edificio (CIC o PIDET) y la planta (Planta Baja o Planta Alta).</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic directamente sobre un espacio <strong style="color:#16a34a;">libre (verde)</strong> en el plano para seleccionarlo automáticamente, o elige el espacio desde el menú desplegable.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Completa la <strong>fecha</strong>, <strong>hora de inicio</strong>, <strong>hora de fin</strong>, número de asistentes y <strong>motivo</strong> de la reserva.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>«Guardar»</strong>. La solicitud quedará en estado <strong>Pendiente</strong> hasta que un administrador la apruebe o rechace.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Recibirás una <strong>notificación por correo</strong> cuando tu solicitud sea procesada.</div></li>
                </ul>
                <span class="block-label">Plano Arquitectónico — Leyenda de colores</span>
                <table class="data-table" style="margin-bottom:24px;">
                    <thead><tr><th>Color</th><th>Estado del espacio</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge badge-green">Verde</span></td><td><strong>Libre</strong> — Disponible para reservar.</td></tr>
                        <tr><td><span class="badge badge-red">Rojo</span></td><td><strong>Ocupado</strong> — Ya tiene una reserva aprobada en ese horario.</td></tr>
                        <tr><td><span class="badge badge-blue">Azul</span></td><td><strong>Seleccionado</strong> — El espacio que elegiste actualmente.</td></tr>
                        <tr><td><span class="badge" style="background:#e2e8f0;color:#475569;">Gris</span></td><td><strong>Privado</strong> — Espacio reservado de forma permanente o fuera de servicio.</td></tr>
                        <tr><td><span class="badge badge-orange">Amarillo/Naranja</span></td><td><strong>Requiere autorización</strong> — Espacio que necesita aprobación especial.</td></tr>
                    </tbody>
                </table>
                <span class="block-label">Estados de una solicitud de reserva</span>
                <table class="data-table" style="margin-bottom:24px;">
                    <thead><tr><th>Estado</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge badge-blue">Pendiente</span></td><td>La solicitud fue enviada y está esperando revisión por parte de un administrador.</td></tr>
                        <tr><td><span class="badge badge-green">Aprobada</span></td><td>La reserva fue autorizada y aparece confirmada en el calendario.</td></tr>
                        <tr><td><span class="badge badge-red">Rechazada</span></td><td>La solicitud fue denegada. Se notifica al usuario con el motivo del rechazo.</td></tr>
                        <tr><td><span class="badge badge-orange">Cancelada</span></td><td>La reserva fue cancelada por el propio usuario antes de ser procesada.</td></tr>
                    </tbody>
                </table>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo</span>
                        Usa el Plano Arquitectónico dentro del formulario para ver de un vistazo qué espacios están disponibles sin tener que probar uno por uno desde el menú desplegable.
                    </div>
                    <div class="tip-box">
                        <span class="tip-label">Importante</span>
                        Los espacios en rojo (Ocupado) no pueden seleccionarse. Si todos están ocupados, considera cambiar el horario o buscar en otro edificio o planta.
                    </div>
                </div>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 5. USUARIOS -->
            <div class="section" id="sec-usuarios">
                <span class="section-label">Sección 5</span>
                <h2 class="section-title">Gestión de Usuarios</h2>
                <p class="section-intro">Administra todas las cuentas del sistema, asigna roles con permisos diferenciados y controla el acceso de cada persona según su área y responsabilidades. Disponible únicamente para administradores.</p>
                <span class="block-label">Funciones principales</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Crear nuevos usuarios con nombre completo, correo institucional, rol, área y contraseña inicial.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Editar información de perfil y reasignar el rol de cualquier usuario existente.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Desactivar cuentas: el historial se conserva pero el usuario no puede iniciar sesión.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Restablecer la contraseña de un usuario directamente desde el panel de administración.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Exportar el listado completo de usuarios a Excel o PDF con formato profesional.</div></li>
                </ul>
                <div class="info-box">
                    <span class="note-label">Gestión de contraseñas</span>
                    El administrador define la contraseña inicial al crear un usuario. El usuario puede cambiarla en cualquier momento desde su perfil (menú lateral → su nombre → Mi Perfil). Si un usuario olvida su contraseña, puede usar la opción <strong>«¿Olvidaste tu contraseña?»</strong> en la pantalla de inicio de sesión o solicitar al administrador que la restablezca.
                </div>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 6. ESPACIOS -->
            <div class="section" id="sec-espacios">
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
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 7. APROBACIONES -->
            <div class="section" id="sec-aprobaciones">
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
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 8. PRÉSTAMOS -->
            <div class="section" id="sec-prestamos">
                <span class="section-label">Sección 8</span>
                <h2 class="section-title">Módulo de Préstamos</h2>
                <p class="section-intro">Controla el ciclo completo de préstamo de activos tecnológicos: laptops, proyectores, cables, adaptadores y cualquier equipo del inventario. Desde la salida hasta la devolución, con registro del responsable, fechas y estado.</p>
                <span class="block-label">Cómo registrar un préstamo</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>«+ Nuevo Préstamo»</strong> en la barra superior del módulo.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Selecciona el <strong>activo</strong> a prestar (solo aparecen los disponibles), el <strong>usuario solicitante</strong> (CIC o PIDET) y la <strong>fecha estimada de devolución</strong>.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>«Guardar»</strong>. El estado del activo cambia automáticamente a «En Curso».</div></li>
                </ul>
                <span class="block-label">Cómo registrar una devolución</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Localiza el préstamo activo en la tabla. Usa los filtros avanzados si hay muchos registros.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en el botón de <strong>acción (Ver/Editar)</strong> del préstamo y selecciona <strong>«Registrar devolución»</strong>.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>El sistema marcará el préstamo como <strong>«Finalizado»</strong> y el activo volverá a estar disponible.</div></li>
                </ul>
                <span class="block-label">Estados de un préstamo</span>
                <table class="data-table" style="margin-bottom:24px;">
                    <thead><tr><th>Estado</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge badge-green">Activo</span></td><td>El préstamo fue registrado y el activo está en posesión del solicitante.</td></tr>
                        <tr><td><span class="badge badge-orange">En Curso</span></td><td>El préstamo sigue vigente; el activo no ha sido devuelto.</td></tr>
                        <tr><td><span class="badge badge-red">Vencido</span></td><td>Se superó la fecha de devolución sin registrar el regreso del activo.</td></tr>
                        <tr><td><span class="badge badge-blue">Finalizado</span></td><td>El activo fue devuelto y el ciclo de préstamo está cerrado.</td></tr>
                    </tbody>
                </table>
                <span class="block-label">Filtros avanzados</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en el botón <strong>«Filtros»</strong> de la barra superior para abrir el panel lateral de filtros avanzados.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Filtra por <strong>Edificio</strong>, <strong>Planta</strong>, <strong>Área/Espacio</strong>, <strong>Estado del préstamo</strong> o <strong>Tipo</strong> de forma combinada.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Los filtros de Planta y Área se cargan dinámicamente según el Edificio seleccionado.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>«Aplicar filtros»</strong> para ver los resultados o en <strong>«Limpiar»</strong> para restablecer la tabla completa.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo</span>
                        Usa el filtro de estado «Vencido» para identificar activos que no han sido devueltos a tiempo y dar seguimiento al responsable.
                    </div>
                </div>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 9. INVENTARIO -->
            <div class="section" id="sec-inventario">
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
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 10. AUDITORÍA -->
            <div class="section" id="sec-auditoria">
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
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 11. RFID -->
            <div class="section" id="sec-rfid">
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
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 12. MI PERFIL -->
            <div class="section" id="sec-perfil">
                <span class="section-label">Sección 12</span>
                <h2 class="section-title">Mi Perfil</h2>
                <p class="section-intro">Desde Mi Perfil puedes consultar y actualizar tus datos personales registrados en el sistema, así como cambiar tu contraseña de acceso. Accede haciendo clic en tu nombre en la parte inferior del menú lateral.</p>
                <span class="block-label">Información que puedes modificar</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>Nombre completo</strong> — Actualizable en cualquier momento.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Correo institucional</strong> — Debe ser único; el sistema valida que no esté en uso por otro usuario.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Género</strong> — Determina el saludo personalizado en el Dashboard (Bienvenido / Bienvenida).</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Número telefónico</strong> — Exactamente 10 dígitos numéricos.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Organización / Área</strong> — Selecciona tu división o área administrativa desde el menú desplegable.</div></li>
                </ul>
                <span class="block-label">Información de solo lectura (administrada por el sistema)</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>Rol de usuario</strong> — Solo puede ser modificado por un administrador desde el módulo de Usuarios.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Fecha de registro</strong> — Generada automáticamente al crear la cuenta.</div></li>
                </ul>
                <span class="block-label">Cómo cambiar tu contraseña</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div>Ve a Mi Perfil y localiza la sección <strong>«Cambiar contraseña»</strong> en la columna derecha.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Ingresa tu <strong>contraseña actual</strong>, luego la <strong>nueva contraseña</strong> (mínimo 6 caracteres) y confírmala.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>El indicador de fortaleza te muestra en tiempo real qué tan segura es tu nueva contraseña.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div>Haz clic en <strong>«Actualizar contraseña»</strong>. La nueva contraseña tendrá efecto inmediatamente.</div></li>
                </ul>
                <div class="tip-list">
                    <div class="tip-box">
                        <span class="tip-label">Consejo de seguridad</span>
                        Usa una contraseña de al menos 10 caracteres que combine letras mayúsculas, minúsculas, números y símbolos para una seguridad óptima.
                    </div>
                </div>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

            <!-- 13. FAQ -->
            <div class="section" id="sec-faq">
                <span class="section-label">Sección 13</span>
                <h2 class="section-title">Preguntas Frecuentes</h2>
                <p class="section-intro">Respuestas a las consultas más habituales de los usuarios del sistema SIGRAT, desde el acceso básico hasta las operaciones más comunes.</p>
                <span class="block-label">Acceso y contraseña</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo inicio sesión?</strong> — Abre el sistema en tu navegador, ingresa tu correo institucional (@uteq.edu.mx) y tu contraseña, luego haz clic en «Iniciar Sesión».</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Qué hago si olvidé mi contraseña?</strong> — En la pantalla de inicio de sesión haz clic en «¿Olvidaste tu contraseña?», ingresa tu correo y recibirás un enlace de restablecimiento. También puedes pedirle al administrador que la restablezca.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo cambio mi contraseña estando dentro del sistema?</strong> — Ve a Mi Perfil (clic en tu nombre en el menú lateral), busca la sección «Cambiar contraseña» y sigue los pasos.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>Mi sesión se cerró sola, ¿qué pasó?</strong> — Las sesiones expiran automáticamente después de 8 horas de inactividad por seguridad. Vuelve a iniciar sesión normalmente.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Por qué no puedo ver algunos módulos?</strong> — El acceso depende del rol asignado a tu cuenta. Si necesitas un módulo específico, solicítalo al administrador del sistema.</div></li>
                </ul>
                <span class="block-label">Reservas y Calendario</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo creo una reservación?</strong> — Ve al módulo Calendario, haz clic en «+ Nueva Reserva» o en una celda vacía, selecciona espacio, fecha, hora de inicio y fin, completa el motivo y guarda.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo consulto la disponibilidad de un espacio?</strong> — Al crear una nueva reserva en el Calendario, el formulario incluye un <strong>Plano Arquitectónico</strong>. Los espacios en verde están libres; los rojos, ocupados.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo uso el Plano Arquitectónico para reservar?</strong> — En el formulario de Nueva Reserva, selecciona el edificio (CIC o PIDET) y la planta. Haz clic sobre un espacio <strong>verde (Libre)</strong> para seleccionarlo automáticamente. Los rojos ya están ocupados.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo sé si mi reserva fue aprobada?</strong> — Recibirás una notificación en el ícono de campana (barra superior) y por correo electrónico cuando tu solicitud sea aprobada o rechazada.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Qué significan los colores de las reservas en el calendario?</strong> — Las reservas pendientes aparecen en un tono diferente al de las aprobadas. Una vez aprobadas se confirman en el calendario con su color definitivo.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Puedo cancelar una reserva ya creada?</strong> — Sí, haz clic sobre el evento en el calendario y selecciona la opción de cancelar. Solo puedes cancelar reservas propias que aún estén pendientes.</div></li>
                </ul>
                <span class="block-label">Préstamos e Inventario</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo solicito un préstamo de equipo?</strong> — Ve al módulo Préstamos y haz clic en «+ Nuevo Préstamo». Selecciona el activo disponible, el responsable y la fecha de devolución estimada.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Qué significa el estado «Vencido» en Préstamos?</strong> — El activo no fue devuelto antes de la fecha límite. Usa el filtro de estado «Vencido» para localizarlos y dar seguimiento.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Qué significa el estado «En Curso» en Préstamos?</strong> — El activo fue prestado y aún no se ha registrado su devolución. Cuando se devuelva, el administrador debe registrar el regreso en el sistema.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo aplico filtros en Préstamos?</strong> — Haz clic en el botón «Filtros» de la barra superior. Se abrirá un panel lateral donde puedes filtrar por Edificio, Planta, Área, Estado y Tipo. Haz clic en «Aplicar filtros» para ver los resultados.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo veo el inventario disponible para préstamo?</strong> — Ve al módulo Inventario y filtra por estado «Disponible» para ver todos los activos que pueden prestarse.</div></li>
                </ul>
                <span class="block-label">General</span>
                <ul class="func-list">
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Los datos se guardan automáticamente?</strong> — Sí, cada acción confirmada se guarda en la base de datos de inmediato. No cierres el navegador mientras tengas formularios a medio completar.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo exporto información?</strong> — En los módulos de Préstamos, Inventario, Usuarios y Auditoría encontrarás botones de «Exportar Excel» o «Exportar PDF» en la barra de herramientas superior.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Puedo acceder al sistema desde mi celular?</strong> — Sí, SIGRAT es completamente responsivo y funciona en dispositivos móviles, tablets y escritorio con cualquier navegador moderno.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Cómo reporto un error técnico?</strong> — Contacta al equipo de soporte de tu institución. Describe el error, proporciona una captura de pantalla y menciona los pasos que realizaste antes de que ocurriera.</div></li>
                    <li class="func-row"><div class="func-mark"></div><div><strong>¿Qué navegadores son compatibles con SIGRAT?</strong> — El sistema funciona correctamente en Google Chrome, Mozilla Firefox, Microsoft Edge y Safari en sus versiones recientes. Se recomienda Chrome para la mejor experiencia.</div></li>
                </ul>
                <a href="#toc" class="back-to-index"><i class="bi bi-arrow-up"></i> Volver al índice</a>
            </div>

        </div><!-- /content-wrap -->
    </div><!-- /main-doc -->

    <footer class="doc-footer">
        <p>Generado automáticamente por <strong>SIGRAT</strong> &mdash; Sistema Integral de Gestión de Recursos y Actividades Tecnológicas</p>
        <p style="margin-top:4px;">Versión 1.0 &nbsp;&middot;&nbsp; <?php echo $fechaGeneracion; ?> &nbsp;&middot;&nbsp; Documento de uso interno y confidencial</p>
    </footer>

</body>
</html>
