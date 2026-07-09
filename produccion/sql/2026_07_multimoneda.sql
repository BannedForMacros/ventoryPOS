-- ============================================================================
-- Multimoneda (PEN base + USD) — cambio de esquema idempotente
-- Aplicar directo a Postgres (NO usamos migraciones):
--   PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d ventoryPOS -f produccion/sql/2026_07_multimoneda.sql
--
-- Modelo: la columna de monto EXISTENTE queda SIEMPRE en soles (PEN), congelada
-- al momento de registrar. Al lado se guarda el original en moneda extranjera:
--   moneda        char(3)        -- 'PEN' | 'USD' | ...
--   tipo_cambio   decimal(12,6)  -- soles por 1 unidad extranjera (NULL/1 = PEN)
--   monto_moneda  decimal(14,2)  -- monto original en 'moneda' (NULL para PEN)
-- Asi TesoreriaService / BalanceDiarioService / auditoria siguen sumando soles
-- sin cambios, y el valor historico en soles nunca se reconvierte.
-- ============================================================================

BEGIN;

-- 1) Almacen del tipo de cambio del dia (SBS via Decolecta) -------------------
CREATE TABLE IF NOT EXISTS tipos_cambio (
    id             bigserial PRIMARY KEY,
    fecha          date        NOT NULL,
    moneda         char(3)     NOT NULL DEFAULT 'USD',   -- moneda origen (extranjera)
    tasa           decimal(12,6) NOT NULL,               -- soles por 1 unidad (SBS "price")
    fuente         varchar(40) NOT NULL DEFAULT 'decolecta_sbs_accounting',
    raw            jsonb,                                 -- respuesta cruda para auditoria
    created_at     timestamp,
    updated_at     timestamp
);
CREATE UNIQUE INDEX IF NOT EXISTS tipos_cambio_fecha_moneda_unique
    ON tipos_cambio (fecha, moneda);

-- 2) Trio moneda/tipo_cambio/monto_moneda en las tablas de dinero -------------
DO $$
DECLARE
    t text;
    money_tables text[] := ARRAY[
        'ventas','venta_pagos','venta_abonos',
        'entradas','entrada_pagos',
        'deudas','deuda_pagos',
        'cliente_anticipos','proveedor_adelantos',
        'gastos','cuenta_movimientos'
    ];
BEGIN
    FOREACH t IN ARRAY money_tables LOOP
        EXECUTE format('ALTER TABLE %I ADD COLUMN IF NOT EXISTS moneda char(3) NOT NULL DEFAULT ''PEN''', t);
        EXECUTE format('ALTER TABLE %I ADD COLUMN IF NOT EXISTS tipo_cambio decimal(12,6)', t);
        EXECUTE format('ALTER TABLE %I ADD COLUMN IF NOT EXISTS monto_moneda decimal(14,2)', t);
    END LOOP;
END $$;

-- 3) Moneda nativa de la cuenta (una cuenta USD lleva plata en dolares) -------
ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS moneda char(3) NOT NULL DEFAULT 'PEN';

COMMIT;
