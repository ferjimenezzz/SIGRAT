/**
 * @file seed_data.sql
 * @summary Datos iniciales para pruebas del sistema SIGRAT.
 * @description Inserta roles, un administrador inicial, edificios y espacios para comenzar a usar el sistema.
 */

USE sigrat_db;

-- 1. Insertar Roles iniciales
INSERT INTO ROLES (nombre, descripcion, permisos) VALUES 
('Administrador', 'Acceso total al sistema y auditoría', '{"all": true}'),
('Personal Académico', 'Gestión de espacios y préstamos básicos', '{"reservations": true, "loans": true}'),
('Estudiante', 'Solo consulta y solicitudes de reservación', '{"reservations": "create"}');

-- 2. Insertar Usuario Administrador (Password: admin123 - Hashear en producción)
INSERT INTO USUARIO (nombre, correo, contrasena, rol_id, estatus) VALUES 
('Admin SIGRAT', 'admin@sigrat.edu', 'admin123', 1, 'Activo');

-- 3. Insertar Espacios (Edificios CIC y PIDET)
INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad, estatus) VALUES 
('CIC', 'Laboratorio de Posgrado', 'Laboratorio', 30, 'Disponible'),
('CIC', 'Aula A1', 'Aula', 40, 'Disponible'),
('PIDET', 'Auditorio Principal', 'Auditorio', 150, 'Disponible'),
('PIDET', 'Taller de Mecatrónica', 'Taller', 20, 'Disponible');

-- 4. Insertar Tags RFID de prueba
INSERT INTO TAG_RFID (tag_id, tipo_tag, estado) VALUES 
('E200001', 'Usuario', 'Activo'),
('E200002', 'Usuario', 'Activo'),
('A100001', 'Activo', 'Activo'),
('A100002', 'Activo', 'Activo'),
('K500001', 'Llave', 'Activo');

-- 5. Vincular Tag a Usuario Admin
UPDATE USUARIO SET nombre = 'Admin SIGRAT' WHERE us_id = 1; 
-- Nota: En un flujo real, se haría via registro/enrolamiento.

-- 6. Insertar Antenas/Lectores de prueba
INSERT INTO ANTENA (ubicacion, tipo, estatus) VALUES 
('Entrada CIC', 'Fija', 'Operativa'),
('Entrada PIDET', 'Fija', 'Operativa');
