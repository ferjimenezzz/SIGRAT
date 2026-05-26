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
INSERT INTO ESPACIO (edificio, nombre_numero, tipo, capacidad, acceso) VALUES 
('CIC', 'aula a', 'aula', 25, 'general'),
('CIC', 'siemens', 'aula', 30, 'general'),
('CIC', 'qsm', 'aula', 21, 'general'),
('CIC', 'laboratorio ibm', 'laboratorio', 18, ''),
('CIC', 'cisco', 'aula', 31, 'general'),
('CIC', '', 'aula', , ''),
('CIC', 'huawei', 'aula', 29, 'general'),
('CIC', 'lab de realidad aumentada', 'laboratorio', , ''),
('CIC', 'cprodi', 'laboratorio', 26, ''),
('CIC', 'centro de información digital ingenuity lab', 'laboratorio', 12, ''),
('CIC', 'centro de formación digital ingenuity lab', 'laboratorio', 8, ''),
('CIC', 'auditorio', 'auditorio', 86, ''),
('CIC', 'centro de formación', 'aula', 20, 'general'),
('PIDET', 'Posgrado 1', 'Aula', 20, 'general'),
('PIDET', 'Posgrado 2', 'Aula', 20, 'general'),
('PIDET', 'Posgrado 3', 'Aula', 24, 'general'),
('PIDET', 'Posgrado 4', 'Aula', 30, 'general'),
('PIDET', 'Posgrado 5', 'Aula', 20, 'general'),
('PIDET', 'Posgrado 6', 'Aula', 20, 'general'),
('PIDET', 'Posgrado 7', 'Aula', 20, 'general'),
('PIDET', 'Posgrado 8', 'Aula', 20, 'general'),
('PIDET', 'Sala Magna 1', 'Aula', 20, 'general'),
('PIDET', 'Sala Magna 2', 'Aula', 22, 'general'),
('PIDET', 'Sala Magna 3', 'Aula', 22, 'general'),
('PIDET', 'Sala Magna 4', 'Aula', 22, 'general'),
('PIDET', 'Aula Digital 1', 'Aula', 24, 'general'),
('PIDET', 'Aula Digital 2', 'Aula', 24, 'general'),
('PIDET', 'Aula Digital 3', 'Aula', 24, 'general'),
('PIDET', 'Aula Digital 3', 'Aula', 24, 'general'),
('PIDET', 'Aula Digital 4', 'Aula', 24, 'general'),
('PIDET', 'Aula Digital 5', 'Aula', 24, 'general'),
('PIDET', 'Maker Space', 'laboratorio', 25, 'industrial'),
('PIDET', 'Lab de Automatización y robótica', 'laboratorio', 25, 'dtai'),
('PIDET', 'lab de óptica y experimentación', 'laboratorio', 15, 'industrial'),
('PIDET', 'lab de biología', 'laboratorio', 15, 'industrial'),
('PIDET', 'auditorio', 'auditorio', 120, 'general');
  
-- Tags
INSERT INTO TAG_RFID (tag_id, tipo_tag) VALUES 
('TAG-USER-01', 'Usuario'),
('TAG-ASSET-01', 'Activo');
