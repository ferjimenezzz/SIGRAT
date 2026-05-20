# 🗺️ Manual Técnico de la API - SIGRAT v3.0 (Supabase Cloud)

Este documento es la referencia definitiva para el desarrollo, mantenimiento y pruebas de los endpoints del sistema SIGRAT.

---

## 📋 Requisitos Previos para Pruebas
1.  **Entorno:** XAMPP (Apache corriendo).
2.  **Conexión Cloud:** Verifica que tengas internet para conectar con Supabase.
3.  **Headers:** Siempre incluir `Content-Type: application/json` en las peticiones POST.

---

## 1. 🔐 Módulo de Autenticación
### Login de Usuario
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/auth/login.php`
*   **Método:** `POST`
*   **JSON:**
    ```json
    {
      "correo": "admin@sigrat.edu",
      "contrasena": "Fjamnr050.1"
    }
    ```

---

## 2. 👥 Módulo de Usuarios (CRUD)
### Crear Usuario
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/UserController.php`
*   **Método:** `POST`
*   **JSON:**
    ```json
    {
      "action": "save_user",
      "nombre": "Carlos",
      "apellido": "García",
      "correo": "carlos@sigrat.edu",
      "rol_id": 2,
      "rfc_matricula": "RFC-9988"
    }
    ```
### Listar Usuarios
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/UserController.php`
*   **Método:** `GET`
### Eliminar (Baja Lógica)
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/UserController.php?delete_user=1`
*   **Método:** `GET`

---

## 3. 📦 Módulo de Inventario (CRUD)
### Registrar Activo
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/AssetController.php`
*   **Método:** `POST`
*   **JSON:**
    ```json
    {
      "tipo": "Proyector",
      "marca": "Epson",
      "modelo": "PowerLite",
      "num_inv": "INV-P01",
      "tag_id": "RFID-PROY-01"
    }
    ```
### Listar Inventario
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/AssetController.php`
*   **Método:** `GET`

---

## 4. 🏢 Módulo de Espacios (Aulas/Labs)
### Crear Espacio
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/SpaceController.php`
*   **Método:** `POST`
*   **JSON:**
    ```json
    {
      "edificio": "CIC",
      "nombre_numero": "Laboratorio 3",
      "tipo": "Laboratorio",
      "capacidad": 15
    }
    ```

---

## 5. 📅 Módulo de Reservaciones
### Nueva Reservación
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/ReserveController.php`
*   **Método:** `POST`
*   **JSON:**
    ```json
    {
      "us_id": 1,
      "esp_id": 1,
      "fecha_uso": "2024-06-15",
      "hora_ent": "10:00:00",
      "hora_sal": "12:00:00"
    }
    ```

---

## 6. 🏷️ Módulo Hardware (RFID)
### Procesar Lectura
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/api/rfid_process.php`
*   **Método:** `POST`
*   **JSON:**
    ```json
    {
      "uid_tag": "E200001",
      "lec_id": 1,
      "tipo_evento": "ENTRY"
    }
    ```

---

## 7. 📜 Módulo de Auditoría
### Consultar Bitácora
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/AuditController.php?modulo=INVENTARIO`
*   **Método:** `GET`

---

## 🚀 Instrucciones Paso a Paso para Pruebas Completas
1.  **Paso 1 (Auth):** Realiza el Login para verificar que la conexión a Supabase es exitosa.
2.  **Paso 2 (Usuarios):** Crea un usuario de prueba. Ve a tu panel de Supabase y verifica que aparezca en la tabla `usuario`.
3.  **Paso 3 (Espacios):** Crea un espacio (Ej. Laboratorio).
4.  **Paso 4 (Reservas):** Usa el ID del usuario y el ID del espacio creados anteriormente para generar una reserva.
5.  **Paso 5 (RFID):** Simula una lectura con el `tag_id` del usuario creado.
6.  **Paso 6 (Auditoría):** Consulta la bitácora para verificar que todas las acciones anteriores quedaron registradas.
