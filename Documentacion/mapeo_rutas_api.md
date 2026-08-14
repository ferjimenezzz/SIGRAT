# 🗺️ Mapeo Completo y Definitivo de Endpoints REST & Standalone APIs - SIGRAT v3.0

> **Archivo:** `mapeo_rutas_api.md`  
> **Propósito:** Guía de referencia técnica exhaustiva para pruebas de integración con Postman, consumo frontend e integración con hardware IoT (ESP32).  
> **Estado:** 100% Sincronizado con el enrutador base `backend/api/index.php`, el interceptor modular `backend/routes.php` y los scripts independientes.

---

## 📋 Configuración Global para Postman

### 1. URL Base del Sistema
*Todas las peticiones REST al enrutador principal utilizan la siguiente base (ajustar según el alias de Apache en XAMPP):*
```http
http://localhost/Estadias/backend/api/index.php
```

### 2. Encabezados HTTP Obligatorios (Headers)
Para garantizar el correcto decodificado de los payloads JSON y la validación de seguridad:
- **`Content-Type: application/json`** *(Obligatorio en todas las peticiones POST y PUT con cuerpo JSON)*.
- **`Authorization: Bearer <TOKEN_JWT>`** *(Obligatorio para endpoints protegidos. Alternativamente, el servidor acepta la cookie `auth_token`)*.

---

## 1. 🔐 Módulo de Autenticación (`/auth`)
*Endpoints públicos para registro, solicitud de recuperación y restablecimiento de credenciales.*

### 1.1 Registro de Usuario
- **Método:** `POST`
- **Ruta:** `/auth/register`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/auth/register`
- **Body JSON:**
  ```json
  {
    "nombre": "Carlos",
    "correo": "carlos@sigrat.edu",
    "telefono": "4421234567",
    "carrera": "Telemática",
    "password": "SecretPassword123"
  }
  ```
- **Respuesta Exitosa (201 Created):** `{"success": true, "message": "Usuario registrado correctamente"}`

### 1.2 Solicitud de Recuperación de Contraseña
- **Método:** `POST`
- **Ruta:** `/auth/forgot-password`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/auth/forgot-password`
- **Body JSON:**
  ```json
  {
    "correo": "carlos@sigrat.edu"
  }
  ```

### 1.3 Restablecer Contraseña con Token
- **Método:** `POST`
- **Ruta:** `/auth/reset-password`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/auth/reset-password`
- **Body JSON:**
  ```json
  {
    "token": "token_recuperacion_123",
    "password": "NuevaPassword456"
  }
  ```

---

## 2. 👥 Módulo de Usuarios (`/usuarios`)
*Administración del padrón institucional. Requiere sesión o token con rol de Administrador.*

### 2.1 Listar Todos los Usuarios
- **Método:** `GET`
- **Ruta:** `/usuarios`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/usuarios`
- **Headers:** `Authorization: Bearer <TOKEN>`

### 2.2 Crear Usuario Administrativamente
- **Método:** `POST`
- **Ruta:** `/usuarios`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/usuarios`
- **Headers:** `Authorization: Bearer <TOKEN>` | `Content-Type: application/json`
- **Body JSON:**
  ```json
  {
    "nombre": "Ana García",
    "correo": "ana@sigrat.edu",
    "contrasena": "AnaPass123",
    "rol_id": 2
  }
  ```

### 2.3 Baja Lógica de Usuario (Soft Delete)
- **Método:** `DELETE`
- **Ruta:** `/usuarios/{us_id}` *(Ejemplo: `/usuarios/5`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/usuarios/5`
- **Headers:** `Authorization: Bearer <TOKEN>`

---

## 3. 🏢 Módulo de Espacios (`/spaces`)
*Gestión de Aulas Digitales, Laboratorios y Auditorios de CIC y PIDET.*

### 3.1 Consultar Catálogo de Espacios
- **Método:** `GET`
- **Ruta:** `/spaces`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/spaces`

### 3.2 Crear Espacio Físico
- **Método:** `POST`
- **Ruta:** `/spaces`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/spaces`
- **Body JSON:**
  ```json
  {
    "edificio": "CIC",
    "planta": "Planta Alta",
    "nombre_numero": "Aula Digital 1",
    "tipo": "Aula Digital",
    "capacidad": 25,
    "responsable": "Leticia Vera"
  }
  ```

### 3.3 Actualizar Espacio y Responsable
- **Método:** `PUT`
- **Ruta:** `/spaces/{esp_id}` *(Ejemplo: `/spaces/10`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/spaces/10`
- **Body JSON:**
  ```json
  {
    "edificio": "CIC",
    "planta": "Planta Alta",
    "nombre_numero": "Aula Digital 1 Modificado",
    "tipo": "Aula Digital",
    "capacidad": 30,
    "responsable": "Andrea Sarahí López"
  }
  ```

### 3.4 Eliminar Espacio
- **Método:** `DELETE`
- **Ruta:** `/spaces/{esp_id}` *(Ejemplo: `/spaces/10`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/spaces/10`

---

## 4. 📅 Módulo de Reservaciones (`/reservations`)
*Incluye la creación de reservas por usuarios y el flujo de aprobación por personal académico o administradores (`routes.php`).*

### 4.1 Consultar Disponibilidad por Fecha
- **Método:** `GET`
- **Ruta:** `/reservations?date={YYYY-MM-DD}`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations?date=2026-07-25`

### 4.2 Consultar Disponibilidad de un Espacio Específico
- **Método:** `GET`
- **Ruta:** `/reservations?esp_id={esp_id}&date={YYYY-MM-DD}`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations?esp_id=10&date=2026-07-25`

### 4.3 Crear Solicitud de Reservación (Simple o Múltiple)
- **Método:** `POST`
- **Ruta:** `/reservations`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations`
- **Headers:** `Authorization: Bearer <TOKEN>`
- **Body JSON (Reserva de una fecha con equipamiento):**
  ```json
  {
    "esp_id": 10,
    "fecha_uso": "2026-07-25",
    "hora_ent": "10:00:00",
    "hora_sal": "12:00:00",
    "motivo": "Clase de Redes Avanzadas",
    "equipamiento_ids": [1, 2]
  }
  ```
- **Body JSON (Reserva múltiple por bloque de fechas):**
  ```json
  {
    "esp_id": 10,
    "fechas_uso": ["2026-07-25", "2026-07-26", "2026-07-27"],
    "hora_ent": "10:00:00",
    "hora_sal": "12:00:00",
    "motivo": "Taller Especializado",
    "skip_conflicts": false
  }
  ```

### 4.4 Listar Solicitudes Pendientes (Aprobación Institucional)
- **Método:** `GET`
- **Ruta:** `/reservations/pending`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations/pending`
- **Headers:** `Authorization: Bearer <TOKEN>` *(Requiere rol Administrador o Personal Académico)*.

### 4.5 Listar Solicitudes Aprobadas
- **Método:** `GET`
- **Ruta:** `/reservations/approved`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations/approved`

### 4.6 Listar Solicitudes Canceladas / Rechazadas
- **Método:** `GET`
- **Ruta:** `/reservations/cancelled`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations/cancelled`

### 4.7 Aprobar Reservación
- **Método:** `POST`
- **Ruta:** `/reservations/{re_id}/approve` *(Ejemplo: `/reservations/15/approve`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations/15/approve`
- **Headers:** `Authorization: Bearer <TOKEN>`
- **Body JSON:** `{}` *(O enviando `{"new_esp_id": 11}` para aprobar y reasignar a un espacio diferente al solicitado)*.

### 4.8 Rechazar Reservación
- **Método:** `POST`
- **Ruta:** `/reservations/{re_id}/reject` *(Ejemplo: `/reservations/15/reject`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations/15/reject`
- **Body JSON:**
  ```json
  {
    "reason": "El laboratorio estará ocupado para mantenimiento preventivo."
  }
  ```

### 4.9 Cancelar Reservación (Dueño o Admin)
- **Método:** `POST`
- **Ruta:** `/reservations/{re_id}/cancel` *(Ejemplo: `/reservations/15/cancel`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/reservations/15/cancel`
- **Body JSON:**
  ```json
  {
    "reason": "Cancelación solicitada por el titular."
  }
  ```

---

## 5. 📦 Módulo de Activos e Inventario (`/assets`)
*Control patrimonial y asignación de etiquetas RFID a equipos institucionales.*

### 5.1 Consultar Inventario Completo
- **Método:** `GET`
- **Ruta:** `/assets`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/assets`

### 5.2 Registrar un Activo Individual
- **Método:** `POST`
- **Ruta:** `/assets`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/assets`
- **Body JSON:**
  ```json
  {
    "marca": "Epson",
    "modelo": "PowerLite X49",
    "num_serie": "EPS-2026-001",
    "tag_id": "E20000179",
    "tipo": "Proyector",
    "estatus": "Disponible"
  }
  ```

### 5.3 Carga Masiva de Activos por Lotes
- **Método:** `POST`
- **Ruta:** `/assets/bulk`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/assets/bulk`
- **Body JSON:**
  ```json
  {
    "common_category": "Proyector",
    "assets": [
      {"marca": "Epson", "modelo": "L310", "num_serie": "SR-001"},
      {"marca": "Epson", "modelo": "L310", "num_serie": "SR-002"}
    ]
  }
  ```

### 5.4 Actualizar Activo
- **Método:** `PUT`
- **Ruta:** `/assets/{act_id}` *(Ejemplo: `/assets/5`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/assets/5`
- **Body JSON:**
  ```json
  {
    "marca": "Epson",
    "modelo": "PowerLite X49",
    "num_serie": "EPS-2026-001-MOD",
    "estatus": "En Mantenimiento"
  }
  ```

### 5.5 Eliminar Activo
- **Método:** `DELETE`
- **Ruta:** `/assets/{act_id}` *(Ejemplo: `/assets/5`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/assets/5`

---

## 6. 🤝 Préstamos de Equipamiento (`/loans`)
*Registro de salidas temporales de proyectores, laptops o llaves físicas.*

### 6.1 Listar Todos los Préstamos
- **Método:** `GET`
- **Ruta:** `/loans`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/loans`

### 6.2 Registrar Préstamo Instantáneo
- **Método:** `POST`
- **Ruta:** `/loans`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/loans`
- **Body JSON:**
  ```json
  {
    "act_id": 5,
    "us_id": 2
  }
  ```

### 6.3 Registrar Devolución de Equipo
- **Método:** `PUT`
- **Ruta:** `/loans/{pres_id}` *(Ejemplo: `/loans/12`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/loans/12`
- **Body JSON:** `{}` *(También se puede enviar un `PUT` a `/loans` con body `{"pres_id": 12}`)*.

---

## 7. 🛠️ Mantenimiento de Activos (`/maintenance`)
*Bitácora de incidencias, reparaciones y servicios técnicos efectuados al hardware.*

### 7.1 Registrar Incidencia / Mantenimiento
- **Método:** `POST`
- **Ruta:** `/maintenance`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/maintenance`
- **Body JSON:**
  ```json
  {
    "act_id": 5,
    "descripcion": "Cambio de lámpara del proyector y limpieza del sistema óptico.",
    "responsable": "Ing. Martín Soporte"
  }
  ```

---

## 8. 🏷️ Tags y Tarjetas RFID (`/tags`)
*Catálogo de tarjetas o llaveros RFID asignables a usuarios o activos.*

### 8.1 Listar Tags RFID
- **Método:** `GET`
- **Ruta:** `/tags`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/tags`

### 8.2 Registrar Tag RFID en Catálogo
- **Método:** `POST`
- **Ruta:** `/tags`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/tags`
- **Body JSON:**
  ```json
  {
    "uid_tag": "E20000199291",
    "tipo": "Tarjeta",
    "estatus": "Activo"
  }
  ```

### 8.3 Cambiar Estatus del Tag
- **Método:** `PUT`
- **Ruta:** `/tags/{tag_id}` *(Ejemplo: `/tags/1`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/tags/1`
- **Body JSON:**
  ```json
  {
    "estatus": "Inactivo"
  }
  ```

### 8.4 Eliminar Tag RFID
- **Método:** `DELETE`
- **Ruta:** `/tags/{tag_id}` *(Ejemplo: `/tags/1`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/tags/1`

---

## 9. 📡 Hardware RFID / IoT Antenas (`/hardware`)
*Endpoints públicos para la comunicación directa de microcontroladores ESP32 y lectores físicos.*

### 9.1 Procesar Lectura en Puerta / Escáner (IoT ESP32)
- **Método:** `POST`
- **Ruta:** `/hardware/rfid-scan`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/hardware/rfid-scan`
- **Headers:** `Content-Type: application/json` *(Público / IoT)*
- **Body JSON:**
  ```json
  {
    "tag_id": "E20000179",
    "lec_id": 1
  }
  ```

### 9.2 Obtener Escaneos Recientes (Monitoreo en Vivo)
- **Método:** `POST`
- **Ruta:** `/hardware/recent-scans`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/hardware/recent-scans`
- **Body JSON:**
  ```json
  {
    "ant_id": "all"
  }
  ```

### 9.3 Consultar Estado de Conexión de Antenas
- **Método:** `GET`
- **Ruta:** `/hardware/antennas-status`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/hardware/antennas-status`

### 9.4 Heartbeat / Ping de Antena IoT
- **Método:** `POST`
- **Ruta:** `/hardware/ping`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/hardware/ping`
- **Body JSON:**
  ```json
  {
    "ant_id": 1
  }
  ```

### 9.5 Capturar Último Tag Desconocido en Vivo
- **Método:** `GET`
- **Ruta:** `/hardware/latest-unknown-tag`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/hardware/latest-unknown-tag`
- **Descripción:** Permite capturar el último tag RFID que pasó por la antena y no está en la base de datos, para facilitarle al administrador su registro rápido desde la UI.

---

## 10. 🎫 Pase de Invitados (`/invites`)
*Generación y validación de códigos de acceso temporal para visitantes.*

### 10.1 Generar Código de Invitación
- **Método:** `POST`
- **Ruta:** `/invites/generate`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/invites/generate`
- **Body JSON:**
  ```json
  {
    "hours_valid": 24
  }
  ```

### 10.2 Validar Código de Invitación
- **Método:** `GET`
- **Ruta:** `/invites/validate/{codigo}` *(Ejemplo: `/invites/validate/INV-8899`)*
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/invites/validate/INV-8899`

---

## 11. 📆 Calendario de Reservaciones (`/calendar`)
*Retorna eventos estructurados y filtrados para renderizado de calendarios.*

### 11.1 Consultar Eventos Filtrados
- **Método:** `GET`
- **Ruta:** `/calendar`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/calendar?edificio=CIC&esp_id=10&fecha_inicio=2026-07-01&fecha_fin=2026-07-31`
- **Parámetros Query opcionales:** `edificio`, `esp_id`, `tipo`, `fecha_inicio`, `fecha_fin`, `us_id`, `status`

---

## 12. 📊 Dashboard Estadísticas (`/dashboard`)
*Métricas agregadas de uso y ocupación institucional para gráficas.*

### 12.1 Obtener Métricas de Ocupación
- **Método:** `GET`
- **Ruta:** `/dashboard?rango={rango}`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/dashboard?rango=semana`
- **Valores para query param `rango`:** `semana`, `mes`, `año`

---

## 13. 🔔 Notificaciones Institucionales (`/notifications`)
*Alertas en vivo sobre reservas, aprobaciones y préstamos por vencer.*

### 13.1 Obtener Notificaciones No Leídas
- **Método:** `GET`
- **Ruta:** `/notifications/unread`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/notifications/unread`

### 13.2 Obtener Historial Completo de Notificaciones
- **Método:** `GET`
- **Ruta:** `/notifications/all`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/notifications/all`

### 13.3 Marcar Notificación como Leída
- **Método:** `POST`
- **Ruta:** `/notifications/read`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/notifications/read`
- **Body JSON:**
  ```json
  {
    "not_id": 15
  }
  ```

### 13.4 Verificar Préstamos Vencidos (Trigger Alertas)
- **Método:** `POST`
- **Ruta:** `/notifications/check_expiring`
- **URL Completa:** `http://localhost/Estadias/backend/api/index.php/notifications/check_expiring`
- **Body JSON:** `{}`

---

## 14. ⚡ Standalone APIs (Scripts Independientes en `/backend/api/`)
*Endpoints rápidos que se ejecutan sin pasar por el router de `index.php` para maximizar rendimiento.*

### 14.1 Búsqueda Global Instantánea (`global_search.php`)
- **Método:** `GET`
- **URL Completa:** `http://localhost/Estadias/backend/api/global_search.php?q=Epson`
- **Descripción:** Busca en tiempo real en las tablas `USUARIO` y `ACTIVO` devolviendo resultados agrupados para el buscador del encabezado (`header.php`). Requiere sesión activa (`$_SESSION['us_id']`).

### 14.2 Sondeo Asíncrono de Eventos RFID (`poll_rfid.php`)
- **Método:** `GET`
- **URL Completa:** `http://localhost/Estadias/backend/api/poll_rfid.php?last_check=2026-07-20%2010:00:00`
- **Descripción:** Consulta movimientos RFID posteriores a la fecha/hora especificada en `last_check` para actualizar el monitor en tiempo real de la UI.

### 14.3 Guardado de Polígonos de Mapas (`save_map.php`)
- **Método:** `POST`
- **URL Completa:** `http://localhost/Estadias/backend/api/save_map.php`
- **Headers:** `Content-Type: application/json`
- **Body JSON:** Estructura completa de polígonos del editor de mapas (`PIDET_alta`, `CIC_alta`, etc.) que actualiza de manera segura el archivo `frontend/assets/map_data.json`.

---

## 15. 📜 Reportes Oficiales & Exportaciones (`/backend/reports/`)
*Generadores que retornan flujos binarios (PDF) o archivos Excel descargables.*

| Método | URL en Postman / Navegador | Parámetros / Body | Descripción |
| :--- | :--- | :--- | :--- |
| **GET** | `http://localhost/Estadias/backend/reports/users_pdf.php` | *N/A* | Genera y descarga el padrón de usuarios en PDF. |
| **GET** | `http://localhost/Estadias/backend/reports/inventory_pdf.php` | Query: `?tipo=Proyector&estado=Disponible` | Genera y descarga el inventario patrimonial en PDF. |
| **GET** | `http://localhost/Estadias/backend/reports/audit_pdf.php` | Query: `?fecha_inicio=...&fecha_fin=...&modulo=...&tipo_reporte=actividad` | Exporta la bitácora inmutable de auditoría en PDF con múltiples filtros. |
| **POST** | `http://localhost/Estadias/backend/reports/excel_export.php` | Body (`x-www-form-urlencoded`): `exportData=[{"Col1":"Val1"}]` | Convierte datos tabulares en archivo Excel (`.xls`) descargable al instante. |
