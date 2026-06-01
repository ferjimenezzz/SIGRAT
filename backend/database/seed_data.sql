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
-- Espacios
INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad, acceso, planta) VALUES
-- Datos del bloque CIC
('CIC', 'Aula A', 'Aula', 30, 'general', 'Alta'),
('CIC', 'Aula B (anteriormente SIEMENS PLM)', 'Aula', 20, 'general', 'Alta'),
('CIC', 'Aula C (anteriormente MIRAI)', 'Aula', 30, 'general', 'Alta'),
('CIC', 'Aula IBM', 'Sala de Reuniones', 18, 'restringido', 'Alta'),
('CIC', 'CISCO', 'Aula', 31, 'general', 'Alta'),
('CIC', 'Intel', 'Aula', 30, 'DTAI', 'Alta'),
('CIC', 'Aula QSM', 'Aula', 21, 'general', 'Alta'),
('CIC', 'Laboratorio de Embebidos', 'Laboratorio', 20, 'general', 'Baja'),
('CIC', 'Aula HUAWAI', 'Aula', 29, 'general', 'Baja'),
('CIC', 'Laboratorio de Realidad Aumentada', 'Laboratorio', 20, 'restringido', 'Baja'),
('CIC', 'CEPRODI', 'Laboratorio', 26, 'restringido', 'Baja'),
('CIC', 'SIEMENS Digital Ingenuity Lab (Centro de Cómputo)', 'Laboratorio', 12, 'restringido', 'Baja'),
('CIC', 'SIEMENS Digital Ingenuity Lab (Centro de Control de Motores)', 'Laboratorio', 8, 'general', 'Baja'),
('CIC', 'Auditorio', 'Auditorio', 86, 'general', 'Baja'),

-- Datos del bloque PIDET
('PIDET', 'Posgrado 1', 'Aula', 20, 'general', 'Baja'),
('PIDET', 'Posgrado 2', 'Aula', 20, 'general', 'Baja'),
('PIDET', 'Posgrado 3', 'Aula', 24, 'general', 'Alta'),
('PIDET', 'Posgrado 4', 'Aula', 30, 'general', 'Alta'),
('PIDET', 'Posgrado 5', 'Aula', 20, 'general', 'Alta'),
('PIDET', 'Posgrado 6', 'Aula', 20, 'general', 'Alta'),
('PIDET', 'Posgrado 7', 'Aula', 20, 'general', 'Alta'),
('PIDET', 'Posgrado 8', 'Aula', 20, 'general', 'Alta'),
('PIDET', 'Aula Magna 1', 'Aula', 22, 'general', 'Alta'),
('PIDET', 'Aula Magna 2', 'Aula', 22, 'general', 'Alta'),
('PIDET', 'Aula Magna 3', 'Aula', 22, 'general', 'Alta'),
('PIDET', 'Aula Magna 4', 'Aula', 22, 'general', 'Alta'),
('PIDET', 'Aula digital 1', 'Aula', 24, 'general', 'Alta'),
('PIDET', 'Aula digital 2', 'Aula', 24, 'general', 'Alta'),
('PIDET', 'Aula digital 3', 'Aula', 24, 'general', 'Alta'),
('PIDET', 'Aula digital 4', 'Aula', 24, 'general', 'Alta'),
('PIDET', 'Aula digital 5', 'Aula', 24, 'general', 'Alta'),
('PIDET', 'Maker Space', 'Laboratorio', 25, 'por división', 'Baja'),
('PIDET', 'Laboratorio de Automatización y robótica', 'Laboratorio', 25, 'por división', 'Baja'),
('PIDET', 'Laboratorio de óptica y experimentación', 'Laboratorio', 15, 'por división', 'Baja'),
('PIDET', 'Laboratorio de biología', 'Laboratorio', 15, 'por división', 'Baja'),
('PIDET', 'Auditorio', 'Auditorio', 120, 'general', 'Baja');

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
