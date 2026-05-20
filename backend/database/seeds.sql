/**
 * @file seeds.sql
 * @summary Datos de prueba MySQL para SIGRAT (XAMPP).
 * @description Población inicial de catálogos y registros de prueba.
 */

USE sigrat_db;

-- Roles
INSERT INTO ROLES (nombre, descripcion) VALUES 
('Administrador', 'Control total del sistema'),
('Personal', 'Gestión de espacios y activos'),
('Estudiante', 'Solicitudes de reserva');

-- Usuarios
INSERT INTO USUARIO (nombre, correo, contrasena, rol_id) VALUES 
('Admin SIGRAT', 'admin@sigrat.edu', 'admin123', 1),
('Dr. Leonardo Meza', 'lmeza@ipn.mx', 'prof123', 2);

-- Espacios
INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad) VALUES 
('CIC', 'Laboratorio L1', 'Laboratorio', 25),
('PIDET', 'Aula P10', 'Aula', 30);

-- Tags
INSERT INTO TAG_RFID (tag_id, tipo_tag) VALUES 
('TAG-USER-01', 'Usuario'),
('TAG-ASSET-01', 'Activo');
