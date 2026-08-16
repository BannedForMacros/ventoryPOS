-- ─────────────────────────────────────────────────────────────────────────────
-- ÍNDICES POS SERVER-SIDE (scroll infinito + búsqueda)
--
-- Aplica esquema por SQL directo, NO por migraciones.
-- Todos los CREATE INDEX usan CONCURRENTLY para evitar bloquear lecturas/escrituras
-- en producción. NO ejecutar dentro de una transacción.
--
-- Requiere la extensión pg_trgm (incluida en la mayoría de Postgres; si falta,
-- el CREATE EXTENSION la instala). pg_trgm permite búsquedas parciales eficientes
-- tipo ILIKE '%manz%' sin escanear toda la tabla.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Productos: filtro por empresa/activo + orden cursor (nombre, id)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_activo_empresa_nombre_id
    ON productos (empresa_id, activo, nombre, id);

-- Productos: búsqueda parcial por nombre y código
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_nombre_trgm
    ON productos USING gin (nombre gin_trgm_ops);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_codigo_trgm
    ON productos USING gin (codigo gin_trgm_ops);

-- Clientes: filtro por empresa/activo + orden cursor (nombres, id)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_activo_empresa_nombres_id
    ON clientes (empresa_id, activo, nombres, id);

-- Clientes: búsqueda exacta/prefijo por DNI/RUC (muy rápida con B-tree)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_numero_documento
    ON clientes (empresa_id, activo, numero_documento);

-- Clientes: búsqueda parcial por nombre, apellidos y razón social
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_nombres_trgm
    ON clientes USING gin (nombres gin_trgm_ops);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_apellidos_trgm
    ON clientes USING gin (apellidos gin_trgm_ops);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clientes_razon_social_trgm
    ON clientes USING gin (razon_social gin_trgm_ops);
