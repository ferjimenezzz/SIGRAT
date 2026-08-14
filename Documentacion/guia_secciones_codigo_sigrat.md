# Guía de Comentarios por Sección y Estructura del Código SIGRAT
**Manual de Navegación Didáctica del Código Fuente — Senior Omni-Stack Architect & DBA**

---

## 1. Propósito de la Estandarización por Secciones
Para cumplir con el **Mandato de Documentación**, todo el código fuente de SIGRAT está organizado y comentado mediante bloques funcionales delimitados por encabezados de sección. Esto permite a cualquier ingeniero, auditor o directivo identificar de inmediato qué tarea computacional o regla de negocio ejecuta cada fragmento del archivo.

Anatomía estándar de una sección en PHP:
```php
// ============================================================================
// SECCIÓN X: [TÍTULO EXPLICATIVO DE LA CAPA O RESPONSABILIDAD]
// ============================================================================
// Explicación didáctica y detallada del "por qué" y "cómo" de esta lógica...
```

---

## 2. Mapa de Secciones en los Archivos Clave del Proyecto

### 🌐 A. Capa de Enrutamiento y API (`backend/api/`)

#### 📄 `global_search.php` — Motor de Búsqueda Transversal
* **SECCIÓN 1: INICIALIZACIÓN DE SESIÓN Y CARGA DE DEPENDENCIAS:** Abre o reanuda la sesión PHP para verificar privilegios del usuario. Carga el patrón Singleton de conexión PDO (`Database.php`).
* **SECCIÓN 2: CONFIGURACIÓN DE CABECERAS HTTP Y SEGURIDAD CORS:** Define permisos transversales (`Access-Control-Allow-Origin`) y fuerza la codificación JSON UTF-8 en la respuesta del servidor.
* **SECCIÓN 3: MIDDLEWARE DE AUTENTICACIÓN Y VALIDACIÓN DE ENTRADA:** Evalúa que exista la variable superglobal `$_SESSION['us_id']`. Si el usuario no está logueado, emite HTTP 401. Filtra y sanea el término de búsqueda `?q=`, retornando un arreglo vacío si la longitud es menor a 2 caracteres para proteger la memoria del servidor.
* **SECCIÓN 4: EJECUCIÓN DE BÚSQUEDA MULTI-TABLA (ACTIVOS Y USUARIOS):**
  * *4.1. Búsqueda en Catálogo de Activos:* Sentencia SQL preparada (`LIKE ?`) en marca, modelo, serie y tag RFID limitando a 5 resultados optimizados para la interfaz de inventario.
  * *4.2. Búsqueda en Padrón de Usuarios:* Consulta preparada sobre nombres, apellidos, correo y matrícula RFC, formateando la salida para el módulo de administración de usuarios.

#### 📄 `poll_rfid.php` — Sondeo Asíncrono de Hardware IoT
* **SECCIÓN 1: INICIALIZACIÓN DE SESIÓN Y ENLACE DE BASE DE DATOS:** Prepara el entorno transaccional para autenticar la petición web del frontend.
* **SECCIÓN 2: DEFINICIÓN DE CABECERAS HTTP PARA SONDEO REST:** Configuración para respuestas asíncronas periódicas desde el cliente JavaScript (*polling*).
* **SECCIÓN 3: CONTROL DE ACCESO (MIDDLEWARE) Y PARÁMETRO TEMPORAL:** Verifica la sesión institucional. Recibe el parámetro `?last_check=` que indica la marca de tiempo exacta del último escaneo registrado por el navegador.
* **SECCIÓN 4: CONSULTA Y RETORNO DE EVENTOS DE HARDWARE RECIENTES:** Ejecuta un `SELECT` con `LEFT JOIN` a la tabla `ACTIVO` para recuperar escaneos electromagnéticos ocurridos en los últimos segundos, retornando los UIDs junto con el reloj oficial del servidor para el siguiente ciclo.

---

### ⚙️ B. Capa de Controladores y Lógica de Negocio (`backend/controllers/`)

#### 📄 `BatchController.php` — Procesamiento Masivo Transaccional (ACID)
* **SECCIÓN 1: IMPORTACIÓN DE DEPENDENCIAS Y CONTROLADORES BASE:** Incorpora el acceso a la base de datos MySQL y al motor de bitácora inmutable (`AuditController`).
* **SECCIÓN 2: DEFINICIÓN DE CLASE E INICIALIZACIÓN:** Configura las propiedades privadas `$db` y `$audit` y su instanciación en el constructor.
* **SECCIÓN 3: MÉTODO DE ASIGNACIÓN MASIVA CON TRANSACCIÓN ACID:**
  * *3.1. Inicio de Transacción Atómica PDO:* Llama a `$this->db->beginTransaction()`. Garantiza que si un lote de 50 tags falla en el elemento 49, se revierta el lote entero.
  * *3.2. Recorrido y Validación Maestra:* Bucle `foreach` que verifica si cada UID electrónico ya existe en la tabla maestra `TAG_RFID`.
  * *3.3. Despacho por Tipo de Entidad Destino:* Separa dinámicamente la lógica de negocio para insertar en las tablas relacionales `LLAVE`, `MOBILIARIO` o `ACTIVO`, actualizando el campo `tipo_tag` de manera sincronizada.
  * *3.4. Confirmación Definitiva y Auditoría Forense:* Ejecuta `$this->db->commit()` y escribe en `AUDITORIA_SISTEMA` un log con el recuento exacto de registros impactados.
  * *3.5. Manejo de Errores y Rollback:* Bloque `catch` que captura cualquier excepción y ejecuta `$this->db->rollBack()` para mantener la integridad de MySQL en 3NF.

#### 📄 `ReservationApprovalController.php` — Aprobación Directiva de Espacios
* **SECCIÓN 1: IMPORTACIÓN DE DEPENDENCIAS Y SERVICIOS DE COMUNICACIÓN:** Carga la clase de correo electrónico `EmailService.php` para el despacho de alertas SMTP.
* **SECCIÓN 2: DEFINICIÓN DE CLASE Y CONSTRUCTOR:** Inyección del objeto PDO para manejo de consultas en modo estricto.
* **SECCIÓN 3: CONSULTAS DE SOLICITUDES Y LIMPIEZA DE EXPIRACIONES:** Método `getByStatus()` que purga automáticamente (`UPDATE ... status = 'cancelled'`) aquellas reservas cuya fecha y hora de inicio ya caducaron sin haber recibido firma de aprobación.

#### 📄 `routes.php` — Enrutador Secundario de Aprobaciones
* **SECCIÓN 1: ENRUTADOR PRINCIPAL PARA APROBACIÓN DE RESERVAS:** Función `handleReservationApproval()` que segmenta la URI solicitada (`explode('/')`) para interceptar peticiones de aprobación, rechazo y cancelación de reservas.
* **SECCIÓN 2: CONTROL DE SESIÓN Y RECUPERACIÓN DE TOKEN JWT:** Intercepta la sesión PHP actual y, si se perdió en la memoria del servidor, aplica un *fallback* criptográfico validando la firma de la cookie `auth_token`.
* **SECCIÓN 3: VERIFICACIÓN DE PRIVILEGIOS DE ACCESO (RBAC):** Valida que el rol de usuario posea permisos de `ADMIN` o `Personal Académico` antes de permitir cambios de estado en las reservas.
* **SECCIÓN 4: DESPACHO DE ACCIONES Y RESPUESTA JSON:** Evalúa la acción (`approve`, `reject`, `cancel`), invoca al controlador con las razones justificadas y retorna el código HTTP 200 con mensaje de confirmación.

---

### 🎨 C. Capa de Presentación e Interfaz (`frontend/`)

#### 📄 `seguridad.php` — Middleware y Guardián de Vistas
* **SECCIÓN 1: INICIALIZACIÓN DE SESIÓN Y CARGA DE CONTROLADORES:** Verifica el estado de la sesión PHP e importa `AuthController.php`.
* **SECCIÓN 2: VERIFICACIÓN PRIMARIA DE COOKIE DE SESIÓN:** Si la cookie `auth_token` está ausente, redirige de forma forzada a `login.php`.
* **SECCIÓN 3: VALIDACIÓN CRIPTOGRÁFICA DEL TOKEN JWT:** Verifica la firma HMAC y la fecha de expiración (`exp`). Ante el menor indicio de manipulación, ejecuta un `logout()` e invalida la cookie.
* **SECCIÓN 4: SINCRONIZACIÓN DE SESIÓN EN MEMORIA:** Sincroniza las variables superglobales (`$_SESSION['us_id']`, `$_SESSION['rol']`) con el payload del token para garantizar su disponibilidad ininterrumpida en las vistas del operador.
