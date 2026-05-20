/**
 * @file mysql_schema.sql
 * @summary Esquema oficial de base de datos SIGRAT.
 * @description Estructura de 18 tablas ajustada exactamente al diagrama Entidad-Relación (1).
 */

CREATE DATABASE IF NOT EXISTS sigrat_db;
USE sigrat_db;

-- 1. Gestión de Usuarios y Roles
CREATE TABLE ROLES (
    rol_id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    permisos JSON
);

CREATE TABLE USUARIO (
    us_id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    rol_id INT,
    estatus ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    FOREIGN KEY (rol_id) REFERENCES ROLES(rol_id) ON DELETE SET NULL
);

CREATE TABLE VISITA (
    vis_id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(100),
    fecha_acceso DATE,
    espacio_solicitado VARCHAR(100),
    estatus VARCHAR(20) DEFAULT 'Pendiente'
);

-- 2. Hardware y RFID
CREATE TABLE TAG_RFID (
    tag_id VARCHAR(50) PRIMARY KEY, -- El ID físico del chip
    tipo_tag ENUM('Usuario', 'Activo', 'Llave', 'Mobiliario') NOT NULL,
    estado ENUM('Activo', 'Inactivo', 'Extraviado') DEFAULT 'Activo',
    fecha_activacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ANTENA (
    ant_id INT PRIMARY KEY AUTO_INCREMENT,
    ubicacion VARCHAR(100) NOT NULL,
    tipo VARCHAR(50),
    estatus ENUM('Operativa', 'Mantenimiento', 'Fuera de Servicio') DEFAULT 'Operativa'
);

CREATE TABLE LECTOR (
    lec_id INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE DEFAULT (CURRENT_DATE),
    hora_ent TIME,
    hora_sal TIME,
    tag_id VARCHAR(50),
    ant_id INT,
    FOREIGN KEY (tag_id) REFERENCES TAG_RFID(tag_id),
    FOREIGN KEY (ant_id) REFERENCES ANTENA(ant_id)
);

-- 3. Espacios y Reservas
CREATE TABLE ESPACIO (
    esp_id INT PRIMARY KEY AUTO_INCREMENT,
    edificio ENUM('CIC', 'PIDET') NOT NULL,
    nombre_numero VARCHAR(50) NOT NULL,
    tipo VARCHAR(50),
    capacidad INT,
    inv_asociado VARCHAR(50),
    estatus VARCHAR(20) DEFAULT 'Disponible'
);

CREATE TABLE RESERVA (
    re_id INT PRIMARY KEY AUTO_INCREMENT,
    us_id INT NULL,
    esp_id INT,
    vis_id INT NULL,
    num_alumnos INT DEFAULT 0,
    fecha_sol TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_uso DATE NOT NULL,
    hora_ent TIME NOT NULL,
    hora_sal TIME NOT NULL,
    estatus VARCHAR(20) DEFAULT 'Pendiente',
    FOREIGN KEY (us_id) REFERENCES USUARIO(us_id) ON DELETE CASCADE,
    FOREIGN KEY (esp_id) REFERENCES ESPACIO(esp_id) ON DELETE CASCADE,
    FOREIGN KEY (vis_id) REFERENCES VISITA(vis_id) ON DELETE SET NULL
);

CREATE TABLE APROBACION (
    apro_id INT PRIMARY KEY AUTO_INCREMENT,
    re_id INT,
    admin_id INT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Aprobado', 'Rechazado') NOT NULL,
    comentarios TEXT,
    FOREIGN KEY (re_id) REFERENCES RESERVA(re_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES USUARIO(us_id) ON DELETE SET NULL
);

-- 4. Inventario y Activos
CREATE TABLE ACTIVO (
    act_id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50),
    marca VARCHAR(50),
    modelo VARCHAR(50),
    num_serie VARCHAR(100) UNIQUE,
    num_inv VARCHAR(100) UNIQUE,
    estatus VARCHAR(20) DEFAULT 'Disponible',
    tag_id VARCHAR(50),
    esp_asignado INT,
    FOREIGN KEY (tag_id) REFERENCES TAG_RFID(tag_id) ON DELETE SET NULL,
    FOREIGN KEY (esp_asignado) REFERENCES ESPACIO(esp_id) ON DELETE SET NULL
);

CREATE TABLE LLAVE (
    llave_id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_num VARCHAR(50) UNIQUE,
    esp_id INT,
    tag_id VARCHAR(50),
    estatus VARCHAR(20) DEFAULT 'Disponible',
    re_id INT,
    FOREIGN KEY (esp_id) REFERENCES ESPACIO(esp_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES TAG_RFID(tag_id) ON DELETE SET NULL,
    FOREIGN KEY (re_id) REFERENCES RESERVA(re_id) ON DELETE SET NULL
);

CREATE TABLE MOBILIARIO (
    mob_id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50),
    dimensiones VARCHAR(100),
    tag_id VARCHAR(50),
    act_id INT,
    FOREIGN KEY (tag_id) REFERENCES TAG_RFID(tag_id) ON DELETE SET NULL,
    FOREIGN KEY (act_id) REFERENCES ACTIVO(act_id) ON DELETE CASCADE
);

CREATE TABLE PRESTAMO (
    pres_id INT PRIMARY KEY AUTO_INCREMENT,
    fecha_pres DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_ent DATETIME NULL,
    act_id INT,
    us_id INT,
    estatus ENUM('Activo', 'Finalizado', 'Atrasado') DEFAULT 'Activo',
    FOREIGN KEY (act_id) REFERENCES ACTIVO(act_id) ON DELETE CASCADE,
    FOREIGN KEY (us_id) REFERENCES USUARIO(us_id) ON DELETE CASCADE
);

CREATE TABLE MANTENIMIENTO (
    mant_id INT PRIMARY KEY AUTO_INCREMENT,
    act_id INT,
    fecha DATE DEFAULT (CURRENT_DATE),
    descripcion TEXT,
    responsable VARCHAR(100),
    estatus VARCHAR(20),
    FOREIGN KEY (act_id) REFERENCES ACTIVO(act_id) ON DELETE CASCADE
);

-- 5. Seguimiento y Trazabilidad
CREATE TABLE MOVIMIENTO_RFID (
    mov_id INT PRIMARY KEY AUTO_INCREMENT,
    tag_id VARCHAR(50),
    tipo_mov ENUM('ENTRADA', 'SALIDA') NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lec_id INT,
    us_id INT,
    esp_id INT,
    act_id INT,
    FOREIGN KEY (tag_id) REFERENCES TAG_RFID(tag_id),
    FOREIGN KEY (lec_id) REFERENCES LECTOR(lec_id),
    FOREIGN KEY (us_id) REFERENCES USUARIO(us_id),
    FOREIGN KEY (esp_id) REFERENCES ESPACIO(esp_id),
    FOREIGN KEY (act_id) REFERENCES ACTIVO(act_id)
);

CREATE TABLE BITACORA (
    bit_id INT PRIMARY KEY AUTO_INCREMENT,
    us_id INT,
    accion VARCHAR(255) NOT NULL,
    modulo_afectado VARCHAR(50),
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vis_id INT,
    FOREIGN KEY (us_id) REFERENCES USUARIO(us_id) ON DELETE SET NULL,
    FOREIGN KEY (vis_id) REFERENCES VISITA(vis_id) ON DELETE SET NULL
);

-- 6. Reportes
CREATE TABLE REPORTE (
    rep_id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_rep VARCHAR(50),
    fecha_gen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    us_id INT,
    descripcion TEXT,
    FOREIGN KEY (us_id) REFERENCES USUARIO(us_id) ON DELETE SET NULL
);

CREATE TABLE ARCHIVO (
    arc_id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_arc VARCHAR(20),
    fecha_gen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT,
    ruta_archivo VARCHAR(255),
    rep_id INT,
    us_id INT,
    FOREIGN KEY (rep_id) REFERENCES REPORTE(rep_id) ON DELETE CASCADE,
    FOREIGN KEY (us_id) REFERENCES USUARIO(us_id) ON DELETE SET NULL
);
