-- ============================================================================
-- PENDIENTES POR ENTREGAR desde el POS (caso Jibaja)
-- ============================================================================
-- El cliente paga la venta completa pero se lleva solo PARTE de la mercadería.
-- El POS crea automáticamente un anticipo material multi-producto (uno por
-- venta) vinculado vía cliente_anticipos.venta_id; el detalle de productos
-- pendientes vive en cliente_anticipo_items y cada entrega (total o parcial,
-- con su fecha) en cliente_anticipo_aplicaciones + sus items.
--
-- El stock de lo pendiente NO se descuenta al vender: se descuenta recién al
-- registrar la entrega (solo para anticipos con venta_id).
--
-- Idempotente: se puede correr varias veces con `psql -f`.
-- Aplicar en producción:  psql "$DATABASE_URL" -f 2026_07_pendientes_entrega.sql
-- ============================================================================

-- 1) Vinculo del anticipo con la venta que lo originó + fecha estimada de entrega
ALTER TABLE cliente_anticipos ADD COLUMN IF NOT EXISTS venta_id BIGINT NULL;
ALTER TABLE cliente_anticipos ADD COLUMN IF NOT EXISTS fecha_entrega_estimada DATE NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'cliente_anticipos_venta_id_foreign'
    ) THEN
        ALTER TABLE cliente_anticipos
            ADD CONSTRAINT cliente_anticipos_venta_id_foreign
            FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS cliente_anticipos_venta_id_index
    ON cliente_anticipos (venta_id);

-- 2) Detalle multi-producto del anticipo (toda la venta, no un solo material)
CREATE TABLE IF NOT EXISTS cliente_anticipo_items (
    id                  BIGSERIAL PRIMARY KEY,
    cliente_anticipo_id BIGINT        NOT NULL REFERENCES cliente_anticipos(id) ON DELETE CASCADE,
    venta_item_id       BIGINT        NULL     REFERENCES venta_items(id)       ON DELETE SET NULL,
    producto_id         BIGINT        NOT NULL REFERENCES productos(id),
    producto_unidad_id  BIGINT        NULL     REFERENCES producto_unidades(id),
    producto_nombre     VARCHAR(150)  NOT NULL DEFAULT '',
    unidad_nombre       VARCHAR(50)   NOT NULL DEFAULT '',
    cantidad            NUMERIC(12,4) NOT NULL,             -- comprometida (en la unidad vendida)
    factor_conversion   NUMERIC(12,4) NOT NULL DEFAULT 1,   -- a unidades base (para stock)
    cantidad_pendiente  NUMERIC(12,4) NOT NULL,             -- lo que falta entregar
    precio_unitario     NUMERIC(12,2) NOT NULL DEFAULT 0,   -- precio pagado (S/, congelado)
    created_at          TIMESTAMP(0) NULL,
    updated_at          TIMESTAMP(0) NULL
);

CREATE INDEX IF NOT EXISTS cliente_anticipo_items_anticipo_index
    ON cliente_anticipo_items (cliente_anticipo_id);

-- 3) Detalle de cada entrega parcial (qué ítems y cuánto se entregó esa vez)
CREATE TABLE IF NOT EXISTS cliente_anticipo_aplicacion_items (
    id                             BIGSERIAL PRIMARY KEY,
    cliente_anticipo_aplicacion_id BIGINT        NOT NULL REFERENCES cliente_anticipo_aplicaciones(id) ON DELETE CASCADE,
    cliente_anticipo_item_id       BIGINT        NOT NULL REFERENCES cliente_anticipo_items(id)        ON DELETE CASCADE,
    cantidad                       NUMERIC(12,4) NOT NULL,
    created_at                     TIMESTAMP(0) NULL,
    updated_at                     TIMESTAMP(0) NULL
);

CREATE INDEX IF NOT EXISTS cliente_anticipo_apl_items_apl_index
    ON cliente_anticipo_aplicacion_items (cliente_anticipo_aplicacion_id);
