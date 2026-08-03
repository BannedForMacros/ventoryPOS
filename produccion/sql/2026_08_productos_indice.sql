-- ============================================================================
-- ÍNDICE FALTANTE EN PRODUCTOS
-- ============================================================================
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción.
--
-- `Producto::deEmpresa($id)` se usa en ~20 sitios (POS, catálogo, reportes,
-- balance) y hoy recorre la tabla entera porque `productos` no tiene ningún
-- índice sobre empresa_id. Con dos empresas ya se nota; con cada cliente nuevo
-- que se sume al esquema compartido, más. Lo detectó la auditoría de
-- aislamiento multi-tenant.
--
-- CREATE INDEX (sin CONCURRENTLY) toma un lock de escritura sobre la tabla.
-- Con ~1.500 filas es instantáneo; si algún día la tabla crece mucho, usar
-- CREATE INDEX CONCURRENTLY y correrlo fuera de transacción.
-- ============================================================================

CREATE INDEX IF NOT EXISTS productos_empresa_activo_idx ON productos (empresa_id, activo);
