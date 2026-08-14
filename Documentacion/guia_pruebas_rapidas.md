# 🚀 GUÍA DE PRUEBAS RÁPIDAS - SIGRAT v3.0 (SUPABASE)

**Base URL del Proyecto:** `http://localhost/creaciones%20antigravity/Estadias/backend/`

## 📋 Pasos para Realizar Pruebas

1. **Abrir Postman** o cualquier cliente de API.
2. **Configurar el Header:** Asegúrate de que `Content-Type` sea `application/json`.
3. **Copiar y Pegar:** Usa las siguientes configuraciones para cada módulo.

---

## 1. Módulo de Autenticación
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/auth/login.php`
*   **Método:** `POST`
*   **Cuerpo (JSON):**
```json
{
  "correo": "admin@sigrat.edu",
  "contrasena": "Fjamnr050.1"
}
```

## 2. Registro de Usuario (PostgreSQL Ready)
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/UserController.php`
*   **Método:** `POST`
*   **Cuerpo (JSON):**
```json
{
  "action": "save_user",
  "nombre": "Prueba",
  "apellido": "Sistema",
  "correo": "test.cloud@sigrat.edu",
  "rol_id": 2,
  "rfc_matricula": "PROY-V3-POSTGRES"
}
```

## 3. Alta de Nuevo Espacio (Aulas/Labs)
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/SpaceController.php`
*   **Método:** `POST`
*   **Cuerpo (JSON):**
```json
{
  "edificio": "CIC",
  "nombre_numero": "Laboratorio de IA",
  "tipo": "Laboratorio",
  "capacidad": 20
}
```

## 4. Creación de Reservación
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/ReserveController.php`
*   **Método:** `POST`
*   **Cuerpo (JSON):**
```json
{
  "us_id": 1,
  "esp_id": 1,
  "fecha_uso": "2024-12-25",
  "hora_ent": "09:00:00",
  "hora_sal": "11:00:00"
}
```

## 5. Simulación de Hardware RFID (Lector ESP32)
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/api/rfid_process.php`
*   **Método:** `POST`
*   **Cuerpo (JSON):**
```json
{
  "uid_tag": "E200001",
  "lec_id": 1,
  "tipo_evento": "CHECK"
}
```

## 6. Consulta de Auditoría (Bitácora Cloud)
*   **URL:** `http://localhost/creaciones%20antigravity/Estadias/backend/controllers/AuditController.php`
*   **Método:** `GET`
*   **Parámetros URL:** `?modulo=USUARIOS`

---
**Nota:** Estos endpoints interactúan directamente con tu base de datos en Supabase. Puedes verificar los cambios en tiempo real desde el "Table Editor" de tu panel de Supabase.
