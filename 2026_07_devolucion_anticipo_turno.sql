-- Devolución de anticipo: imputar a la caja/turno por donde SALE el dinero.
--
-- Problema: `anular()` registraba el egreso en tesorería pero sin turno. La caja
-- solo se enteraba por exclusión (`whereNotIn estado ['anulado','devuelto']`),
-- que descuenta el anticipo del turno donde ENTRÓ el dinero — no del turno donde
-- salió. Y para los pendientes del POS (turno_id NULL) no hacía nada en absoluto.
--
-- Columna nueva: el turno cuya caja pierde el efectivo al devolver.
-- NULL = la devolución no afecta ninguna caja (salió por banco, o el admin la
-- hizo fuera de turno).

ALTER TABLE cliente_anticipos
    ADD COLUMN IF NOT EXISTS turno_devolucion_id BIGINT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'cliente_anticipos_turno_devolucion_id_fkey'
    ) THEN
        ALTER TABLE cliente_anticipos
            ADD CONSTRAINT cliente_anticipos_turno_devolucion_id_fkey
            FOREIGN KEY (turno_devolucion_id) REFERENCES turnos(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS cliente_anticipos_turno_devolucion_id_index
    ON cliente_anticipos (turno_devolucion_id);

COMMENT ON COLUMN cliente_anticipos.turno_devolucion_id IS
    'Turno cuya caja pierde el efectivo al devolver el anticipo. NULL = no afecta caja.';
