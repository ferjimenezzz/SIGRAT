# Requerimientos Adaptados para SIGRAT (v3.0)

A continuación, se presenta la adaptación de los requerimientos solicitados, alineados estrictamente con la arquitectura de **SIGRAT**: Stack PHP Nativo (PDO/SOLID), React (Funcional/Hooks), Base de Datos Relacional (PostgreSQL/MySQL en 3NF con 18 tablas) y lineamientos de alta precisión y seguridad.

---

## Requerimientos Funcionales (RF)

### RF01 Inicio de Sesión
**Adaptación SIGRAT:** El sistema permitirá la autenticación consultando la tabla `USUARIO` mediante PDO. Las contraseñas se validarán utilizando algoritmos de hash seguros (ej. bcrypt/Argon2). Una vez validado, el backend en PHP emitirá un **JWT (JSON Web Token)** firmado de corta duración que el frontend (React) gestionará para el manejo de sesiones (stateless).

### RF02 Gestión de Roles y Permisos
**Adaptación SIGRAT:** Tras el inicio de sesión, el JWT contendrá el `rol_id`. El sistema cruzará esta información con la tabla `ROLES`, la cual incluye un campo tipo `json` llamado `permisos`. El backend validará cada endpoint (middleware) según estos permisos, y el frontend (React) renderizará condicionalmente los módulos del menú de navegación y componentes internos.

### RF03 Cierre de Sesión
**Adaptación SIGRAT:** Se invalidará la sesión eliminando el JWT del almacenamiento del cliente (localStorage/sessionStorage). En el backend, se puede implementar una estrategia de "blacklisting" de tokens para mayor seguridad y registrar el evento de cierre de sesión en la tabla `BITACORA` para auditoría.

### RF04 Enrolamiento de Usuarios
**Adaptación SIGRAT:** El sistema vinculará físicamente a una persona con su tarjeta RFID insertando un nuevo registro en la tabla `TAG_RFID` (con `tipo_tag` = 'Usuario'). Esta asociación servirá para trazar sus entradas y salidas a través de los cruces generados en `MOVIMIENTO_RFID` (donde se vincula el `tag_id` detectado por un `LECTOR` y se asocia posteriormente a su `us_id`).

### RF05 Alta y Vinculación Masiva de Activos
**Adaptación SIGRAT:** A través del Módulo de Inventario en React, se permitirá el ingreso en lote (batch upload) de hardware y bienes muebles. Se impactarán simultáneamente las tablas `ACTIVO` o `MOBILIARIO`, vinculándolos con sus respectivos `TAG_RFID`. La operación será procesada mediante transacciones SQL (`BEGIN` / `COMMIT`) para asegurar la atomicidad y evitar inconsistencias.

### RF06 Visualización de Disponibilidad
**Adaptación SIGRAT:** Se implementará un componente React avanzado (ej. react-big-calendar) en el Dashboard. Este calendario consumirá un endpoint API que ejecuta un `SELECT` con `JOIN` entre `RESERVA` y `ESPACIO`, filtrando las reservaciones donde el `estatus` sea "Aprobado" (cruzando con la tabla `APROBACION`).

### RF07 Reservación de Espacios
**Adaptación SIGRAT:** Un usuario con permisos insertará una solicitud en `RESERVA`. La capa lógica de la base de datos (mediante consultas SQL optimizadas o Triggers) validará que no exista superposición de rangos entre `hora_ent` y `hora_sal` para la misma `fecha_uso` y `esp_id`. La reserva quedará "Pendiente" hasta que un administrador inserte el registro de validación en la tabla `APROBACION`.

### RF08 Monitoreo de Licencias
**Adaptación SIGRAT:** Dado que la estructura de 18 tablas está definida, el registro lógico del software y licencias vigentes se gestionará internamente integrando un campo especializado (o aprovechando campos genéricos/descripciones) dentro de los registros de computadoras en la tabla `ACTIVO`. Alternativamente, se podrá manejar anexando metadatos en formato JSON en el registro del activo técnico en `ACTIVO`. *(Nota Arquitectónica: Si el modelo de datos es inflexible, se debe extender un campo existente)*.

### RF09 Control de Préstamos y Resguardos
**Adaptación SIGRAT:** Un usuario de almacén registrará un evento en la tabla `PRESTAMO`, asociando el `act_id` con el `us_id` correspondiente, la `fecha_pres` (timestamp actual) y proyectando una `fecha_ent`. El estatus del activo en la tabla `ACTIVO` cambiará de "Disponible" a "En Préstamo".

### RF10 Registro Automático de Asistencia
**Adaptación SIGRAT:** Las lecturas generadas por el hardware impactarán un endpoint de alta eficiencia (IoT) que insertará registros directamente en `LECTOR` y `MOVIMIENTO_RFID`. Basado en el `tag_id`, el sistema identificará si el evento fue procesado por una antena interna o externa para calcular lógicamente si el `tipo_mov` es "ENTRADA" o "SALIDA".

### RF11 Cálculo Automático de Horas
**Adaptación SIGRAT:** Se creará una consulta SQL avanzada (utilizando CTEs y Window Functions como `LEAD()` o `LAG()`) para emparejar registros cronológicos de "ENTRADA" y "SALIDA" en `MOVIMIENTO_RFID` por cada `us_id`. Esta consulta devolverá la sumatoria de tiempo (DATEDIFF o equivalentes de intervalo de Postgres) agrupado por periodo, garantizando precisión milimétrica.

### RF12 Alerta de Seguridad por Activos
**Adaptación SIGRAT:** Si la tabla `LECTOR` recibe el `tag_id` de un activo en una zona de salida (antena perimetral), el backend consultará inmediatamente la tabla `PRESTAMO` para el `act_id` asociado. Si no existe un préstamo vigente, el backend disparará un evento en tiempo real (vía SSE o WebSockets) hacia el frontend de React para desplegar una alerta en rojo de alta prioridad con un sonido en el navegador.

### RF13 Generación de Reportes Administrativos
**Adaptación SIGRAT:** Un módulo en PHP consumirá librerías como FPDF y PhpSpreadsheet. Tomará la información estructurada mediante JOINs complejos (cruzando `BITACORA`, `RESERVA`, y `MOVIMIENTO_RFID`). El reporte se guardará físicamente en el servidor y su metadato (incluyendo `ruta_archivo`) se insertará en las tablas `REPORTE` y `ARCHIVO` para su futura consulta en la UI.

---

## Requerimientos No Funcionales (RNF)

### RNF01 Seguridad en Autenticación
**Adaptación SIGRAT:** Implementación de encriptación fuerte nativa (como `password_hash()` con PASSWORD_ARGON2ID o BCRYPT en PHP) almacenado en PostgreSQL. Implementación de un **JWT con tiempo de expiración (exp)** y un mecanismo de refresh silencioso. Protección adicional contra CSRF e Inyección SQL (usando sentencias preparadas de PDO `?` estricto en todos los queries).

### RNF02 Tiempo de Respuesta en Login
**Adaptación SIGRAT:** El endpoint de autenticación no demorará más de 2 segundos. Se logrará asegurando que las columnas críticas (ej. `correo` en `USUARIO`) estén indexadas (índice B-Tree). La UI (React) usará _lazy loading_ y mostrará _skeletons_ para renderizar el Dashboard instantáneamente tras la resolución de la promesa del Login.

### RNF03 Latencia de Red en IoT
**Adaptación SIGRAT:** El middleware PHP encargado de recibir los hits de las antenas RFID operará sin inicializar componentes pesados del framework. Insertará el evento a `LECTOR` y `MOVIMIENTO_RFID` en < 3 segundos. Se optimizará el query insert con "Prepared Statements" persistentes y connection pooling en PostgreSQL/MySQL.

### RNF04 Mecanismo de Desconexión
**Adaptación SIGRAT:** Las antenas IoT contarán con un daemon/middleware local de recolección temporal (ej. base de datos local SQLite o caché de disco). Si el ping a la base de datos central en la red universitaria falla, se retendrán los registros localmente y se enviarán en lote (batch push) al endpoint PHP en cuanto la red se restablezca, manteniendo la marca de tiempo `fecha_hora` original inalterada.

### RNF05 Diseño de Interfaz - UI/UX
**Adaptación SIGRAT:** La arquitectura visual de React empleará **Material Design Premium** (como base estilística formal e institucional) o **Tailwind CSS** para alta personalización rápida (requiere confirmación por comando `[Aprobado]`). Se priorizará un diseño donde el flujo principal de enrolar un TAG RFID (RF04 y RF05) esté estructurado en modales de un solo paso, garantizando un máximo de **3 clics** por operador.
