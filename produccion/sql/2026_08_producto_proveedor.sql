-- ============================================================================
-- PROVEEDOR DEL PRODUCTO (quién nos lo vende)
-- ============================================================================
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción.
--
-- POR QUÉ NO ROMPE NADA A LAS EMPRESAS QUE YA OPERAN
--
--   1. La columna es NULLABLE y SIN DEFAULT. En PostgreSQL 11+ eso es un cambio
--      de metadatos: no reescribe la tabla ni la bloquea, es instantáneo aunque
--      tenga millones de filas.
--   2. Los productos existentes quedan en NULL, que significa "sin proveedor
--      asignado". Ninguna consulta actual menciona esta columna, así que el
--      comportamiento de H&C y MacSoft es idéntico al de antes; solo verán un
--      campo opcional más en el formulario, vacío.
--   3. ON DELETE SET NULL y NO CASCADE, a propósito: dar de baja un proveedor
--      NUNCA puede arrastrarse los productos que le compramos. El producto
--      sobrevive y simplemente se queda sin proveedor asignado.
--
-- OJO CON EL AISLAMIENTO: la FK garantiza que el proveedor exista, pero NO que
-- sea de la misma empresa que el producto — Postgres no puede expresar eso. Esa
-- comprobación la hace ProductoRequest con
-- Rule::exists('proveedores','id')->where('empresa_id', $empresaId),
-- igual que ya se hace con categoria_id.
-- ============================================================================

ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS proveedor_id BIGINT NULL
    REFERENCES proveedores(id) ON DELETE SET NULL;

COMMENT ON COLUMN productos.proveedor_id IS 'Proveedor habitual del producto. NULL = sin asignar. Sirve para filtrar el catálogo y armar reposiciones por proveedor.';

-- Filtrar el catálogo por proveedor es el caso de uso de la columna; sin índice
-- sería un seq scan sobre productos.
CREATE INDEX IF NOT EXISTS productos_proveedor_idx ON productos (proveedor_id) WHERE proveedor_id IS NOT NULL;

-- De paso, el índice que la auditoría marcó como faltante: `Producto::deEmpresa()`
-- se usa en ~20 sitios y hoy recorre la tabla entera. Con dos empresas ya se
-- nota; con cada cliente nuevo, más.
CREATE INDEX IF NOT EXISTS productos_empresa_activo_idx ON productos (empresa_id, activo);
