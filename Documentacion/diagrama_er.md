# 📊 Diagrama Entidad-Relación - SIGRAT (Base de Datos Oficial - 18 Tablas)

Este diagrama representa la estructura de las 18 tablas oficiales utilizadas en el sistema SIGRAT, incluyendo todas las relaciones y claves foráneas.

```mermaid
erDiagram
    %% Relaciones Base
    ROLES ||--o{ USUARIO : "define"
    TAG_RFID ||--o{ LECTOR : "detectado en"
    TAG_RFID ||--o{ ACTIVO : "etiqueta a"
    TAG_RFID ||--o{ LLAVE : "identifica a"
    TAG_RFID ||--o{ MOBILIARIO : "etiqueta a"
    TAG_RFID ||--o{ MOVIMIENTO_RFID : "genera"
    
    ANTENA ||--o{ LECTOR : "procesa desde"
    
    ESPACIO ||--o{ RESERVA : "es reservado"
    ESPACIO ||--o{ ACTIVO : "alberga"
    ESPACIO ||--o{ LLAVE : "pertenece a"
    ESPACIO ||--o{ MOVIMIENTO_RFID : "registra en"
    
    USUARIO ||--o{ RESERVA : "realiza"
    USUARIO ||--o{ APROBACION : "administra"
    USUARIO ||--o{ PRESTAMO : "solicita"
    USUARIO ||--o{ MOVIMIENTO_RFID : "tiene"
    USUARIO ||--o{ BITACORA : "genera"
    USUARIO ||--o{ REPORTE : "genera"
    USUARIO ||--o{ ARCHIVO : "crea"

    VISITA ||--o{ RESERVA : "asiste a"
    VISITA ||--o{ BITACORA : "involucra a"
    
    RESERVA ||--o{ APROBACION : "requiere"
    RESERVA ||--o{ LLAVE : "otorga"
    
    ACTIVO ||--o{ MOBILIARIO : "compone"
    ACTIVO ||--o{ PRESTAMO : "sujeto a"
    ACTIVO ||--o{ MANTENIMIENTO : "recibe"
    ACTIVO ||--o{ MOVIMIENTO_RFID : "tiene"

    LECTOR ||--o{ MOVIMIENTO_RFID : "detecta"

    REPORTE ||--o{ ARCHIVO : "incluye"

    %% Entidades
    ROLES {
        int rol_id PK
        string nombre
        string descripcion
        json permisos
    }

    USUARIO {
        int us_id PK
        string nombre
        string correo
        string contrasena
        int rol_id FK
        string estatus
    }

    VISITA {
        int vis_id PK
        string nombre
        string correo
        date fecha_acceso
        string espacio_solicitado
        string estatus
    }

    TAG_RFID {
        string tag_id PK
        string tipo_tag
        string estado
        timestamp fecha_activacion
    }

    ANTENA {
        int ant_id PK
        string ubicacion
        string tipo
        string estatus
    }

    LECTOR {
        int lec_id PK
        date fecha
        time hora_ent
        time hora_sal
        string tag_id FK
        int ant_id FK
    }

    ESPACIO {
        int esp_id PK
        string edificio
        string nombre_numero
        string tipo
        int capacidad
        string inv_asociado
        string estatus
    }

    RESERVA {
        int re_id PK
        int us_id FK
        int esp_id FK
        int vis_id FK
        int num_alumnos
        timestamp fecha_sol
        date fecha_uso
        time hora_ent
        time hora_sal
        string estatus
    }

    APROBACION {
        int apro_id PK
        int re_id FK
        int admin_id FK
        timestamp fecha
        string estado
        string comentarios
    }

    ACTIVO {
        int act_id PK
        string tipo
        string marca
        string modelo
        string num_serie
        string num_inv
        string estatus
        string tag_id FK
        int esp_asignado FK
    }

    LLAVE {
        int llave_id PK
        string rfid_num
        int esp_id FK
        string tag_id FK
        string estatus
        int re_id FK
    }

    MOBILIARIO {
        int mob_id PK
        string tipo
        string dimensiones
        string tag_id FK
        int act_id FK
    }

    PRESTAMO {
        int pres_id PK
        datetime fecha_pres
        datetime fecha_ent
        int act_id FK
        int us_id FK
        string estatus
    }

    MANTENIMIENTO {
        int mant_id PK
        int act_id FK
        date fecha
        string descripcion
        string responsable
        string estatus
    }

    MOVIMIENTO_RFID {
        int mov_id PK
        string tag_id FK
        string tipo_mov
        timestamp fecha_hora
        int lec_id FK
        int us_id FK
        int esp_id FK
        int act_id FK
    }

    BITACORA {
        int bit_id PK
        int us_id FK
        string accion
        string modulo_afectado
        timestamp fecha_hora
        int vis_id FK
    }

    REPORTE {
        int rep_id PK
        string tipo_rep
        timestamp fecha_gen
        int us_id FK
        string descripcion
    }

    ARCHIVO {
        int arc_id PK
        string tipo_arc
        timestamp fecha_gen
        string descripcion
        string ruta_archivo
        int rep_id FK
        int us_id FK
    }
```

## Arquitectura de 18 Tablas
1. **Identidad y Accesos:** `ROLES`, `USUARIO`, `VISITA`.
2. **Hardware y Seguimiento:** `TAG_RFID`, `ANTENA`, `LECTOR`, `MOVIMIENTO_RFID`.
3. **Logística y Espacios:** `ESPACIO`, `RESERVA`, `APROBACION`.
4. **Inventario Físico:** `ACTIVO`, `LLAVE`, `MOBILIARIO`, `PRESTAMO`, `MANTENIMIENTO`.
5. **Auditoría:** `BITACORA`, `REPORTE`, `ARCHIVO`.
