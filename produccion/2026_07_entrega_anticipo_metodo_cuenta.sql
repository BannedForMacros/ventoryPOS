-- ============================================================================
-- Entregas de anticipo EN DINERO: método de pago + cuenta (y egreso de caja)
-- ============================================================================
-- Una entrega (aplicación) de un anticipo en dinero es dinero que SALE al
-- cliente. Hasta ahora no guardaba por dónde salía ni movía tesorería. Se
-- agregan metodo_pago_id y cuenta_id; el egreso de caja se registra desde el
-- código (ref_tipo 'cliente_anticipo_entrega'). Nullable + FK, sin migración.
-- ============================================================================

ALTER TABLE cliente_anticipo_aplicaciones
    ADD COLUMN IF NOT EXISTS metodo_pago_id bigint NULL,
    ADD COLUMN IF NOT EXISTS cuenta_id      bigint NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'cli_ant_aplic_metodo_pago_id_fkey'
    ) THEN
        ALTER TABLE cliente_anticipo_aplicaciones
            ADD CONSTRAINT cli_ant_aplic_metodo_pago_id_fkey
            FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'cli_ant_aplic_cuenta_id_fkey'
    ) THEN
        ALTER TABLE cliente_anticipo_aplicaciones
            ADD CONSTRAINT cli_ant_aplic_cuenta_id_fkey
            FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE SET NULL;
    END IF;
END $$;
