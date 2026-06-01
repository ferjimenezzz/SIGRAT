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

-- Usuarios
INSERT INTO USUARIO (nombre, correo, contrasena, rol_id) VALUES 
('Admin SIGRAT', 'admin@sigrat.edu', 'admin123', 1),
('Dr. Leonardo Meza', 'lmeza@ipn.mx', 'prof123', 2);

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
  
-- Tags
INSERT INTO TAG_RFID (tag_id, tipo_tag) VALUES 
('TAG-USER-01', 'Usuario'),
('TAG-ASSET-01', 'Activo');
