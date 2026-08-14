-- Script DDL para crear la tabla 'lugares' en PostgreSQL / Supabase

-- Si no existen los tipos ENUM, descomenta estas líneas y créalos:
-- CREATE TYPE edificio_enum AS ENUM ('CIC', 'PIDET');
-- CREATE TYPE tipo_lugar_enum AS ENUM ('Pasillo', 'Bodega', 'Oficina', 'Cubículo', 'Recepción', 'Cluster', 'Otro');
-- CREATE TYPE planta_enum AS ENUM ('Baja', 'Alta');
-- CREATE TYPE estatus_enum AS ENUM ('Disponible', 'Ocupado', 'Mantenimiento', 'Inactivo');

CREATE TABLE public.lugares (
  lug_id SERIAL PRIMARY KEY,
  -- Si usas ENUMs estrictos, cambia VARCHAR por el nombre del TYPE.
  edificio VARCHAR(50) NOT NULL CHECK (edificio IN ('CIC', 'PIDET')),
  planta VARCHAR(20) DEFAULT NULL CHECK (planta IN ('Baja', 'Alta')),
  nombre_numero VARCHAR(50) NOT NULL,
  tipo VARCHAR(50) DEFAULT NULL CHECK (tipo IN ('Pasillo', 'Bodega', 'Oficina', 'Cubículo', 'Recepción', 'Cluster', 'Otro')),
  capacidad INT DEFAULT NULL,
  estatus VARCHAR(50) DEFAULT 'Disponible' CHECK (estatus IN ('Disponible','Ocupado','Mantenimiento','Inactivo')),
  eliminado BOOLEAN DEFAULT false
);

-- Habilitar Row Level Security (Recomendación de seguridad para Supabase)
ALTER TABLE public.lugares ENABLE ROW LEVEL SECURITY;

-- Política de lectura básica (ajusta según tus roles)
CREATE POLICY "Catálogo de lugares visible para autenticados" 
ON public.lugares FOR SELECT TO authenticated USING (true);
