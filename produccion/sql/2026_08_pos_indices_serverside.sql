-- ─────────────────────────────────────────────────────────────────────────────
-- ÍNDICES POS SERVER-SIDE (scroll infinito + búsqueda flexible)
--
-- Aplica esquema por SQL directo, NO por migraciones.
-- Todos los CREATE INDEX usan CONCURRENTLY para evitar bloquear lecturas/escrituras
-- en producción. NO ejecutar dentro de una transacción.
--
-- Requiere las extensiones pg_trgm (búsqueda difusa/parcial) y unaccent (ignorar
-- tildes). `unaccent` no está marcada IMMUTABLE por defecto, así que se envuelve
-- en una función IMMUTABLE para poder usarla en índices funcionales.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE OR REPLACE FUNCTION public.unaccent_immutable(text)
RETURNS text
LANGUAGE sql
IMMUTABLE STRICT
AS $$
  SELECT public.unaccent($1)
$$;

-- Productos: filtro por empresa/activo + orden cursor (nombre, id)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_activo_empresa_nombre_id
    ON productos (empresa_id, activo, nombre, id);

-- Productos: búsqueda parcial/difusa por nombre (sin tildes, multi-palabra)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_nombre_unaccent_trgm
    ON productos USING gin (public.unaccent_immutable(nombre) gin_trgm_ops);

-- Productos: búsqueda parcial por código
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_codigo_trgm
    ON productos USING gin (codigo gin_trgm_ops);

-- Clientes: filtro por empresa/activo + orden cursor (nombres, id)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_activo_empresa_nombres_id
    ON clientes (empresa_id, activo, nombres, id);

-- Clientes: búsqueda exacta/prefijo por DNI/RUC (muy rápida con B-tree)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_numero_documento
    ON clientes (empresa_id, activo, numero_documento);

-- Clientes: búsqueda parcial/difusa en nombre + apellidos + razón social
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_nombres_unaccent_trgm
    ON clientes USING gin (
        public.unaccent_immutable(coalesce(nombres, '') || ' ' || coalesce(apellidos, '') || ' ' || coalesce(razon_social, ''))
        gin_trgm_ops
    );
