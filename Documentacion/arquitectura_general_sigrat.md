# SIGRAT - Arquitectura General y Comentario Integral del Código
**Sistema de Gestión de Reservas y Administración Tecnológica**
*Documento Técnico Ejecutivo — Senior Omni-Stack Architect & DBA*

---

## 1. Visión General y Patrón Arquitectónico
SIGRAT está diseñado bajo una arquitectura **Omni-Stack / MVC Modificado (Model-View-Controller)** con desacoplamiento entre la capa de presentación, el enrutador de API RESTful y el motor relacional de base de datos. El sistema prioriza la **seguridad transaccional**, el **rendimiento asíncrono** y la **integración de hardware IoT (RFID)** en tiempo real.

```
+-----------------------------------------------------------------------------------+
|                              CAPA DE PRESENTACIÓN                                 |
|   [ Frontend / PHP SSR + Vanilla JS Fetch API + Bootstrap 5 + Lucide Icons ]     |
+-----------------------------------------+-----------------------------------------+
                                          |
                        Peticiones HTTP / JSON (AJAX / Fetch)
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                           CAPA DE ENRUTAMIENTO Y API                              |
|           [ backend/api/index.php (Router Único & CORS / Security) ]              |
+-----------------------------------------+-----------------------------------------+
                                          |
                        Despacho por Controlador (Namespace Controllers)
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                        CAPA DE LÓGICA DE NEGOCIO (SOLID)                          |
|    [ RFIDController | TagController | AssetController | LoanController | etc. ]  |
+-----------------------------------------+-----------------------------------------+
                                          |
                  Abstracción PDO / Sentencias Preparadas (ACID)
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                     CAPA DE PERSISTENCIA Y BASE DE DATOS                          |
|             [ MySQL 5.7+ / MariaDB - Estructura Relacional en 3NF ]               |
+-----------------------------------------------------------------------------------+
```

---

## 2. Estructura de Directorios y Módulos del Sistema

### 📂 `backend/` — Motor API REST y Lógica de Negocio
Encargado del procesamiento de reglas institucionales, comunicación segura con el hardware y transacciones con la base de datos.
* **`backend/api/index.php` (Router Central):** Punto de entrada único para la API. Intercepta todas las peticiones, configura los encabezados HTTP (CORS, JSON, Control de Caché), procesa el cuerpo de la petición (`php://input`), registra trazas de depuración de hardware ESP32/Antenas IP en logs aislados y despacha la ejecución hacia el controlador adecuado.
* **`backend/api/global_search.php`:** Endpoint de alto rendimiento para búsqueda transversal en tiempo real (usuarios, activos, reservas y espacios).
* **`backend/api/poll_rfid.php`:** Endpoint de sondeo asíncrono (*polling*) utilizado por el frontend para sincronizar lecturas físicas de tarjetas RFID detectadas por antenas en los últimos segundos sin necesidad de recargar la página.
* **`backend/routes.php`:** Enrutador modular complementario para el subsistema de aprobación y rechazo de reservas por parte de directivos.
* **`backend/config/Database.php`:** Implementa el patrón **Singleton** para establecer y reutilizar la conexión PDO a MySQL. Configura el cotejamiento UTF-8 y fuerza el modo de reporte de errores a excepciones (`PDO::ERRMODE_EXCEPTION`) para prevenir inyecciones SQL y fallos silenciosos.
* **`backend/services/EmailService.php`:** Servicio de comunicación institucional mediante correo electrónico y SMTP para notificar aprobaciones, rechazos, préstamos y alertas de inventario.

#### 📁 `backend/controllers/` — Controladores Especializados
Cada controlador cumple con el principio de responsabilidad única (SOLID) y expone métodos documentados con bloques PHPDoc:
1. **`AuthController.php` & `UsuarioController.php`:** Gestión de identidad. Autentica credenciales, emite tokens de sesión y administra el CRUD de usuarios bajo una estricta jerarquía de roles:
   * *Super Administrador:* Control total, auditoría profunda y configuraciones del sistema.
   * *Administrador:* Gestión operativa de inventarios, usuarios y espacios.
   * *Institucional (Directivo/Profesor):* Solicitud y aprobación de reservas y préstamos.
   * *Seguridad:* Monitoreo de accesos, antenas RFID y auditoría física.
   * *Externo / Invitado:* Acceso restringido a reservas temporales y consultas públicas.
2. **`RFIDController.php` & `TagController.php`:** Núcleo de integración IoT. `RFIDController` procesa escaneos en bruto provenientes de antenas físicas (o simuladores) y determina si el tag corresponde a un activo, llave o mobiliario. `TagController` gestiona el catálogo de tags, enrolamiento masivo e individual, y la asociación de claves físicas impresas en las etiquetas (`clave_etiqueta`).
3. **`AssetController.php` & `LoanController.php`:** Módulo de inventario tecnológico. `AssetController` administra el catálogo de equipos, números de serie y estado de operatividad. `LoanController` controla el préstamo y devolución de activos, vinculando el UID de la tarjeta RFID del activo con el responsable, calculando tiempos y generating alertas por morosidad.
4. **`ReservationController.php`, `ReservationApprovalController.php` & `SpaceController.php`:** Módulo de espacios institucionales (auditorios, salas, laboratorios). Administra disponibilidades, calendarios y el flujo de aprobación multinivel.
5. **`AuditController.php` & `BatchController.php`:** Trazabilidad e importación. `AuditController` registra un log inmutable de cada acción crítica (quién, qué, cuándo, desde qué IP/módulo). `BatchController` envuelve operaciones masivas (cargas de Excel/CSV de activos o tags) en transacciones PDO (`beginTransaction`, `commit`, `rollBack`) para garantizar que las importaciones sean atómicas (o se guarda todo o no se guarda nada).

---

### 📂 `frontend/` — Interfaz de Usuario y Capa de Presentación
Desarrollada en PHP nativo para renderizado del servidor (SSR) combinado con componentes dinámicos en Vanilla JavaScript (AJAX/Fetch API) y estilos basados en Bootstrap 5 con variables CSS personalizadas (paleta de colores moderna y Glassmorphism).
* **`frontend/header.php` & `frontend/footer.php`:** Plantillas estructurales. El encabezado evalúa la sesión y construye dinámicamente la barra de navegación (sidebar/navbar), mostrando únicamente los módulos autorizados para el rol del usuario en curso.
* **`frontend/seguridad.php` (Middleware de Protección):** Guardián que se incluye al principio de cada módulo protegido. Evalúa la existencia y validez de la cookie/token de sesión. Ante cualquier anomalía, destruye la sesión y redirige a la pantalla de inicio de sesión (`login.php`).
* **`frontend/login.php` & `frontend/iniciar_sesion.php`:** Vistas y procesadores del flujo de autenticación institucional.
* **`frontend/rfid.php`:** Centro de control de antenas e integraciones IoT. Permite alternar entre enrolamiento individual manual (con validación de clave impresa), escucha de antenas IP en tiempo real (mediante polling a `/hardware/latest-unknown-tag`) y enrolamiento masivo por rangos.
* **`frontend/inventario.php` & `frontend/prestamos.php`:** Interfaces de control de equipos. Integran lectores de código de barras y RFID para realizar préstamos y devoluciones en fracción de segundo sin intervención manual de teclado.
* **`frontend/auditoria.php`:** Consola de monitoreo de seguridad. Presenta una tabla interactiva con filtros avanzados por módulo, gravedad y fecha, permitiendo exportación directa a reportes PDF y Excel.
* **`frontend/calendario.php` & `frontend/espacios.php`:** Gestión visual de reservas institucionales con vistas mensuales, semanales y diarias.
* **`frontend/usuarios.php` & `frontend/perfil.php`:** Administración del capital humano y configuración del perfil personal del operador.

---

### 📂 `database/` — Estructura Relacional y Persistencia
El modelo de datos se encuentra normalizado en **Tercera Forma Normal (3NF)** y ejecutado sobre el motor **InnoDB** para soportar claves foráneas (`FOREIGN KEY`) y transacciones atómicas.
* **`sigrat_db.sql` / `schema.sql`:** Definición del esquema completo de tablas:
  * `USUARIOS`, `ROLES`, `PERMISOS`: Seguridad y control de acceso (RBAC).
  * `TAG_RFID`: Mapeo maestro de tarjetas electrónicas, tipos (`Activo`, `Llave`, `Sin Asignar`), estados y clave física impresa.
  * `ACTIVOS`, `CATEGORIAS_ACTIVO`, `UBICACIONES`: Inventario y geolocalización dentro del recinto.
  * `PRESTAMOS`, `DETALLE_PRESTAMO`: Trazabilidad de entradas y salidas de equipamiento con marcas de tiempo temporales.
  * `ESPACIOS`, `RESERVAS`, `APROBACION_RESERVAS`: Control de áreas físicas, horarios y firmas directivas de autorización.
  * `AUDITORIA_SISTEMA`: Bitácora transaccional inmutable para análisis forense de seguridad.

---

## 3. Comentario Detallado de Flujos Críticos de Negocio

### 🔄 Flujo A: Enrolamiento de Tarjeta RFID desde Antena IP / ESP32
1. **Detección Física:** El microcontrolador (ESP32 con lector RC522 o Antena IP) lee el UID del tag y realiza una petición HTTP POST hacia el endpoint `backend/api/index.php/hardware/scan`.
2. **Evaluación de Existencia:** El controlador `RFIDController->processScan()` consulta a la base de datos si el UID ya existe. Al no encontrarlo en la tabla `TAG_RFID`, lo cataloga como tag desconocido y lo registra en el archivo `backend/api/unknown_tags.log` junto con un *timestamp*.
3. **Escucha Dinámica en el Frontend:** El operador en `frontend/rfid.php` activa el modo **"ESCUCHAR ANTENA IP"**. El navegador inicia un intervalo (*setInterval*) de sondeo cada 2 segundos hacia `/hardware/latest-unknown-tag`.
4. **Captura y Enrolamiento:** Al recibir el UID desconocido escaneado en los últimos 60 segundos, el frontend autocompleta el campo de texto y solicita al operador la **Clave de Etiqueta Física**.
5. **Persistencia Transaccional:** Al enviar el formulario, `TagController->enrollSingleWithLabel()` ejecuta un `INSERT INTO TAG_RFID` mediante sentencias preparadas PDO y genera un evento en `AUDITORIA_SISTEMA`.

### 🔄 Flujo B: Préstamo Rápido de Activos mediante Tarjeta RFID
1. **Presentación de Tarjeta:** El usuario solicita un proyector o laptop y pasa la tarjeta RFID del activo por el lector conectado a la terminal del operador en `frontend/prestamos.php`.
2. **Consulta AJAX:** El frontend consulta el UID al backend. `AssetController` retorna los metadatos del activo (Nombre, Modelo, Número de Serie, Estado).
3. **Validación de Disponibilidad:** El sistema verifica que el activo tenga el estado `Disponible`. Si está marcado como `En Préstamo` o `Mantenimiento`, se aborta la transacción con una alerta visual en rojo.
4. **Registro de Préstamo:** El operador selecciona al prestatario y confirma. `LoanController` inicia una transacción (`$this->db->beginTransaction()`), inserta la cabecera en `PRESTAMOS`, actualiza el estado del activo a `En Préstamo`, registra el evento en auditoría y hace el `commit()` definitivo.

---

## 4. Políticas de Calidad y Estándares de Código Aplicados
* **El Mandato de Documentación:** Todos los archivos PHP de lógica de negocio en el proyecto incorporan un encabezado estandarizado `@file`, `@summary` y `@description`. Todas las clases y funciones principales cuentan con bloques PHPDoc que especifican el propósito, los parámetros (`@param`), tipos de datos y retornos (`@return`).
* **Seguridad Defensiva (OWASP):** 
  * Eliminación absoluta de consultas SQL concatenadas en favor de sentencias preparadas con enlace de parámetros (`bindParam` / `execute([$val])`).
  * Sanitización de entradas post/get con `trim()`, `htmlspecialchars()` y validaciones de tipos.
  * Aislamiento de variables de entorno locales en `.env` y blindaje del repositorio mediante `.gitignore` de archivos sensibles y logs temporales (`*.log`).
