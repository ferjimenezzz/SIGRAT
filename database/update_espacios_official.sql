-- ============================================================================
-- @file update_espacios_official.sql
-- @summary Script DDL y DML para actualizar el catálogo oficial de espacios de CIC y PIDET (29 espacios oficiales) y soportar el acceso exclusivo por Administradores.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- DDL: Asegurar que el ENUM de acceso en PostgreSQL acepte el valor 'administrador'
-- ----------------------------------------------------------------------------
ALTER TYPE acceso ADD VALUE IF NOT EXISTS 'administrador';

-- ----------------------------------------------------------------------------
-- DML: Limpiar referencias en tablas relacionadas y recrear el catálogo de espacios
-- ----------------------------------------------------------------------------
BEGIN;

-- Desvincular referencias previas para evitar conflictos
UPDATE movimiento_rfid SET esp_id = NULL;
UPDATE activo SET esp_asignado = NULL;

-- Eliminar catálogo anterior
DELETE FROM espacio;
ALTER SEQUENCE espacio_esp_id_seq RESTART WITH 1;

-- Insertar los 29 espacios oficiales de PIDET y CIC
INSERT INTO espacio (edificio, nombre_numero, tipo, capacidad, estatus, acceso, planta, acceso_tipo, es_reservable) VALUES
-- PIDET (18 espacios)
('PIDET', 'Aula 01', 'Aula', 20, 'Disponible', 'administrador', 'Baja', 'General', true),
('PIDET', 'Sala Magna 4', 'Auditorio', 24, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Sala Magna 3', 'Auditorio', 24, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Sala Magna 2', 'Auditorio', 24, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Sala Magna 1', 'Auditorio', 24, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Aula DIGITAL 01', 'Aula', 24, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Aula DIGITAL 02', 'Aula', 24, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Aula DIGITAL 03', 'Aula', 19, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Aula DIGITAL 04', 'Aula', 20, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Aula 5 DIGITAL', 'Aula', 20, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Posgrado 2', 'Aula', 22, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Posgrado 1', 'Aula', 22, 'Disponible', 'general', 'Alta', 'General', true),
('PIDET', 'Salon 1', 'Aula', 22, 'Disponible', 'administrador', 'Alta', 'General', true),
('PIDET', 'Aula 6', 'Laboratorio', 25, 'Disponible', 'administrador', 'Baja', 'General', true),
('PIDET', 'Aula 5', 'Laboratorio', 25, 'Disponible', 'administrador', 'Baja', 'General', true),
('PIDET', 'Aula 4', 'Laboratorio', 15, 'Disponible', 'administrador', 'Baja', 'General', true),
('PIDET', 'Aula 3', 'Laboratorio', 15, 'Disponible', 'administrador', 'Baja', 'General', true),
('PIDET', 'Auditorio', 'Auditorio', 120, 'Disponible', 'general', 'Baja', 'General', true),

-- CIC (11 espacios)
('CIC', 'Sala de Video conferencias', 'Aula', 30, 'Disponible', 'administrador', 'Alta', 'General', true),
('CIC', 'Area de Formación de Desarrollo de Proyectos', 'Aula', 20, 'Disponible', 'general', 'Alta', 'General', true),
('CIC', 'Laboratorio de CISCO', 'Laboratorio', 30, 'Disponible', 'restringido', 'Alta', 'Restringido', true),
('CIC', 'Sala IBM', 'Sala de Reuniones', 18, 'Disponible', 'restringido', 'Alta', 'Restringido', true),
('CIC', 'Gevernova', 'Laboratorio', 20, 'Disponible', 'restringido', 'Baja', 'Restringido', true),
('CIC', 'Sala capacitación', 'Laboratorio', 29, 'Disponible', 'restringido', 'Baja', 'Restringido', true),
('CIC', 'Centro de inovación y desarrollo SIEMENS', 'Laboratorio', 20, 'Disponible', 'restringido', 'Baja', 'Restringido', true),
('CIC', 'CEPRODI', 'Laboratorio', 26, 'Disponible', 'restringido', 'Baja', 'Restringido', true),
('CIC', 'Aula Huawei', 'Laboratorio', 12, 'Disponible', 'administrador', 'Baja', 'General', true),
('CIC', 'Envevidos', 'Laboratorio', 8, 'Disponible', 'administrador', 'Baja', 'General', true),
('CIC', 'Auditorio', 'Auditorio', 86, 'Disponible', 'general', 'Baja', 'General', true);

COMMIT;
