-- ============================================================================
-- @file security_hardening_rls_rpc.sql
-- @summary Script DDL de Blindaje Integral de Base de Datos PostgreSQL / Supabase.
-- @description Implementa Row Level Security (RLS), Políticas de Acceso Granular, Procedimientos Almacenados (RPC) seguros contra Inyección SQL dinámica y Restricciones del Motor (CHECK constraints).
-- @author Senior Omni-Stack Architect & DBA
-- ============================================================================

-- ============================================================================
-- SECCIÓN 1: ACTIVACIÓN DE ROW LEVEL SECURITY (RLS) EN TABLAS INSTITUCIONALES
-- ============================================================================
-- ¿Por qué?: Por defecto, PostgreSQL permite a cualquier cliente conectado con permisos de tabla consultar o modificar todas las filas. 
-- Al habilitar RLS, el motor denega implícitamente todo acceso (Default Deny) hasta que una política explicite lo contrario.

ALTER TABLE public.usuario ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.reserva ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.activo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.prestamo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.bitacora ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.espacio ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tag_rfid ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.roles ENABLE ROW LEVEL SECURITY;


-- ============================================================================
-- SECCIÓN 2: DEFINICIÓN DE POLÍTICAS DE SEGURIDAD A NIVEL DE FILA (POLICIES)
-- ============================================================================

-- 2.1 Políticas para la Tabla ROLES y ESPACIO (Catálogos de Lectura Pública Autenticada)
-- ¿Por qué?: Los catálogos deben ser legibles para cualquier usuario registrado para poblar formularios y selectores web, pero inmutables para no-administradores.
CREATE POLICY "Catálogo de roles visible para autenticados" 
ON public.roles FOR SELECT TO authenticated USING (true);

CREATE POLICY "Catálogo de espacios visible para autenticados" 
ON public.espacio FOR SELECT TO authenticated USING (true);

-- 2.2 Políticas para la Tabla ACTIVO (Inventario)
-- ¿Por qué?: Oculta físicamente a nivel de motor los activos dados de baja, impidiendo que sean consultados o referenciados por API directas.
CREATE POLICY "Inventario activo visible para usuarios autenticados" 
ON public.activo FOR SELECT TO authenticated USING (estatus != 'Baja');

CREATE POLICY "Administradores gestionan inventario total" 
ON public.activo FOR ALL TO authenticated USING (
    EXISTS (
        SELECT 1 FROM public.usuario u 
        JOIN public.roles r ON u.rol_id = r.rol_id 
        WHERE u.us_id = (auth.jwt() ->> 'sub')::integer 
        AND UPPER(r.nombre) LIKE '%ADMIN%'
    )
);

-- 2.3 Políticas para la Tabla RESERVA (Aislamiento de Privacidad)
-- ¿Por qué?: Un usuario estándar solo debe tener visibilidad sobre sus propias reservaciones o visitas asignadas, evitando el scraping interno de horarios universitarios.
CREATE POLICY "Usuarios gestionan sus propias reservaciones" 
ON public.reserva FOR ALL TO authenticated USING (
    us_id = (auth.jwt() ->> 'sub')::integer
    OR
    EXISTS (
        SELECT 1 FROM public.usuario u 
        JOIN public.roles r ON u.rol_id = r.rol_id 
        WHERE u.us_id = (auth.jwt() ->> 'sub')::integer 
        AND UPPER(r.nombre) LIKE '%ADMIN%'
    )
);

-- 2.4 Políticas para la Tabla PRESTAMO
-- ¿Por qué?: Los préstamos de hardware solo pueden ser inspeccionados por el alumno prestatario o por el personal de inventario/administración.
CREATE POLICY "Usuarios consultan sus préstamos asignados" 
ON public.prestamo FOR SELECT TO authenticated USING (
    us_id = (auth.jwt() ->> 'sub')::integer
    OR
    EXISTS (
        SELECT 1 FROM public.usuario u 
        JOIN public.roles r ON u.rol_id = r.rol_id 
        WHERE u.us_id = (auth.jwt() ->> 'sub')::integer 
        AND UPPER(r.nombre) LIKE '%ADMIN%'
    )
);

-- 2.5 Políticas para la Tabla BITACORA (Auditoría de Solo Escritura e Inspección Admin)
-- ¿Por qué?: Ningún usuario estándar puede leer ni alterar los registros de auditoría del sistema (Inmutabilidad forense).
CREATE POLICY "Inserción de bitácora por sistema y usuarios" 
ON public.bitacora FOR INSERT TO authenticated WITH CHECK (true);

CREATE POLICY "Lectura de bitácora exclusiva para administradores" 
ON public.bitacora FOR SELECT TO authenticated USING (
    EXISTS (
        SELECT 1 FROM public.usuario u 
        JOIN public.roles r ON u.rol_id = r.rol_id 
        WHERE u.us_id = (auth.jwt() ->> 'sub')::integer 
        AND UPPER(r.nombre) LIKE '%ADMIN%'
    )
);


-- ============================================================================
-- SECCIÓN 3: PROCEDIMIENTOS ALMACENADOS (RPC) BLINDADOS CONTRA SQL INJECTION
-- ============================================================================
-- ¿Por qué?: Al usar funciones PL/pgSQL llamadas vía Supabase RPC, la concatenación de texto con entrada del usuario introduce vulnerabilidades graves de SQLi en servidor.
-- Solución: Se implementa la cláusula USING para separar el query estático de sus variables, combinada con SECURITY DEFINER y aislamiento del search_path.

/**
 * @function public.rpc_buscar_activos_seguro
 * @summary Búsqueda dinámica de activos en inventario inmune a Inyección SQL.
 * @param p_termino VARCHAR Cadena de búsqueda para marca, modelo o serie.
 * @param p_estatus VARCHAR Filtro de estado del activo.
 * @return SETOF public.activo Conjunto de filas coincidentes de la tabla activo.
 */
CREATE OR REPLACE FUNCTION public.rpc_buscar_activos_seguro(
    p_termino VARCHAR(100),
    p_estatus VARCHAR(50) DEFAULT 'Disponible'
)
RETURNS SETOF public.activo
LANGUAGE plpgsql
SECURITY DEFINER                 -- Ejecuta bajo el contexto del creador, permitiendo control preciso
SET search_path = public         -- Blindaje contra ataques de suplantación por modificación del search_path (Schema Hijacking)
AS $$
DECLARE
    v_sql TEXT;                  -- Contenedor del AST estático
    v_like_termino VARCHAR(102); -- Variable parametrizada con comodines
BEGIN
    -- 1. Preparación del parámetro LIKE sin modificar la estructura del query
    v_like_termino := '%' || TRIM(p_termino) || '%';

    -- 2. Definición del query estático con marcadores de posición ($1, $2, $3)
    -- NUNCA se debe hacer: v_sql := 'SELECT * FROM activo WHERE marca LIKE ''' || p_termino || '''';
    v_sql := 'SELECT * FROM public.activo 
              WHERE (marca ILIKE $1 OR modelo ILIKE $2 OR num_serie ILIKE $3)
                AND estatus = $4
              ORDER BY act_id DESC';

    -- 3. Ejecución segura mediante la cláusula USING
    -- El motor compila la consulta estática y trata v_like_termino y p_estatus puramente como literales de datos
    RETURN QUERY EXECUTE v_sql USING v_like_termino, v_like_termino, v_like_termino, p_estatus;
END;
$$;


-- ============================================================================
-- SECCIÓN 4: RESTRICCIONES DE INTEGRIDAD A NIVEL DE MOTOR (CHECK CONSTRAINTS)
-- ============================================================================
-- ¿Por qué?: Garantiza que ninguna transacción defectuosa o bypass del Frontend/Backend pueda escribir un estado de datos incoherente en el motor de persistencia.

-- 4.1 Restricción de Coherencia Temporal en Reservas
-- Evita reservaciones donde la hora de término sea idéntica o anterior a la hora de inicio
ALTER TABLE public.reserva 
DROP CONSTRAINT IF EXISTS chk_reserva_tiempos_logicos;

ALTER TABLE public.reserva 
ADD CONSTRAINT chk_reserva_tiempos_logicos 
CHECK (hora_sal > hora_ent);

-- 4.2 Restricción de Límite y Positividad en Aforo
-- Impide aforos negativos o números desproporcionados por errores tipográficos o manipulación del payload JSON
ALTER TABLE public.reserva 
DROP CONSTRAINT IF EXISTS chk_reserva_aforo_positivo;

ALTER TABLE public.reserva 
ADD CONSTRAINT chk_reserva_aforo_positivo 
CHECK (num_alumnos >= 0 AND num_alumnos <= 500);

-- 4.3 Restricción de Estatus Válidos en Préstamos
-- Asegura que un préstamo únicamente pueda transicionar entre los estados oficiales del ciclo de vida institucional
ALTER TABLE public.prestamo 
DROP CONSTRAINT IF EXISTS chk_prestamo_estatus_valido;

ALTER TABLE public.prestamo 
ADD CONSTRAINT chk_prestamo_estatus_valido 
CHECK (estatus IN ('Activo', 'Devuelto', 'Vencido', 'Extraviado', 'Cancelado'));

-- 4.4 Restricción de Módulos Estandarizados en Bitácora
-- Inmuniza la bitácora de seguridad contra inserciones de texto arbitrario que dificulten el análisis forense
ALTER TABLE public.bitacora 
DROP CONSTRAINT IF EXISTS chk_bitacora_modulo_estandar;

ALTER TABLE public.bitacora 
ADD CONSTRAINT chk_bitacora_modulo_estandar 
CHECK (modulo_afectado IN ('SEGURIDAD', 'INVENTARIO', 'RESERVAS', 'USUARIOS', 'SISTEMA', 'PRESTAMOS', 'RFID', 'AUTENTICACION'));

-- ============================================================================
-- FIN DEL SCRIPT DE BLINDAJE
-- ============================================================================
