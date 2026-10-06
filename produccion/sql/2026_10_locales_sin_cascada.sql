-- ============================================================================
-- 2026_10_locales_sin_cascada.sql   (PROPUESTO — no aplicado en ninguna base)
--
-- Qué hace: quita el ON DELETE CASCADE de ventas, turnos y gastos hacia
-- locales (y de turnos hacia cajas). Con el CASCADE, un DELETE de un local
-- (desde la app o a mano en psql) se llevaba toda su historia y dejaba
-- huérfanos los asientos de tesorería (cuenta_movimientos).
--
-- La app ya no borra locales con historia (LocalController::destroy los
-- desactiva); esto es la segunda línea de defensa en la base.
--
-- Se usa NO ACTION (no RESTRICT) a propósito: NO ACTION se comprueba al
-- final de la sentencia, así que borrar una EMPRESA entera (que cascadea
-- empresa -> ventas/turnos/gastos y empresa -> locales en la misma sentencia)
-- sigue funcionando. Lo que se bloquea es borrar un local suelto con historia.
--
-- cajas.local_id se deja en CASCADE: una caja sin turnos se va con su local;
-- si tiene turnos, el NO ACTION de turnos.caja_id lo impide.
--
-- Idempotente: se puede correr varias veces.
-- ============================================================================
BEGIN;

ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_local_id_foreign;
ALTER TABLE ventas ADD CONSTRAINT ventas_local_id_foreign
    FOREIGN KEY (local_id) REFERENCES locales(id) ON DELETE NO ACTION;

ALTER TABLE turnos DROP CONSTRAINT IF EXISTS turnos_local_id_foreign;
ALTER TABLE turnos ADD CONSTRAINT turnos_local_id_foreign
    FOREIGN KEY (local_id) REFERENCES locales(id) ON DELETE NO ACTION;

ALTER TABLE gastos DROP CONSTRAINT IF EXISTS gastos_local_id_foreign;
ALTER TABLE gastos ADD CONSTRAINT gastos_local_id_foreign
    FOREIGN KEY (local_id) REFERENCES locales(id) ON DELETE NO ACTION;

ALTER TABLE turnos DROP CONSTRAINT IF EXISTS turnos_caja_id_foreign;
ALTER TABLE turnos ADD CONSTRAINT turnos_caja_id_foreign
    FOREIGN KEY (caja_id) REFERENCES cajas(id) ON DELETE NO ACTION;

COMMIT;
