-- ─────────────────────────────────────────────────────────────────────────────
-- Anticipos de clientes en efectivo → caja del turno
-- ─────────────────────────────────────────────────────────────────────────────
-- Igual que los abonos (venta_abonos) y los pagos de entrada (entrada_pagos):
-- un anticipo cobrado EN EFECTIVO entra al cajón, pero cliente_anticipos no
-- tenía turno_id, así que el sistema no lo esperaba (aparecía "sobrante") y no
-- se reflejaba en la caja del turno.
--
-- Agregamos turno_id NULLABLE: un anticipo puede no tener turno (depósito al
-- banco / Yape registrado por admin sin caja abierta, o un anticipo que el
-- usuario decide NO imputar a caja) → queda NULL y no afecta caja. Solo los
-- anticipos en efectivo CON turno suman al efectivo esperado del turno
-- (ver Turno::calcularMontoEsperado; se excluyen los anulados y devueltos).
--
-- Backfill de registros ANTIGUOS: cada anticipo → turno del MISMO usuario que
-- estaba ABIERTO en el MOMENTO del registro. Usamos `created_at` (instante real
-- del registro), NO `fecha` (que es la del formulario y puede estar retrofechada),
-- para que el efectivo caiga en el turno cuya caja realmente lo recibió. Seguro
-- para turnos cerrados: su arqueo muestra el valor GUARDADO al cierre;
-- calcularMontoEsperado() solo se recalcula en vivo para las cards de caja de
-- Ventas, no reescribe el arqueo histórico.

ALTER TABLE cliente_anticipos
    ADD COLUMN IF NOT EXISTS turno_id BIGINT NULL
    REFERENCES turnos(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS cliente_anticipos_turno_id_index ON cliente_anticipos(turno_id);

UPDATE cliente_anticipos ca
SET turno_id = (
    SELECT t.id
    FROM turnos t
    WHERE t.user_id = ca.user_id
      AND ca.created_at >= t.fecha_apertura
      AND ca.created_at <= COALESCE(t.fecha_cierre, now())
    ORDER BY t.fecha_apertura DESC
    LIMIT 1
)
WHERE ca.turno_id IS NULL
  AND EXISTS (
    SELECT 1
    FROM turnos t
    WHERE t.user_id = ca.user_id
      AND ca.created_at >= t.fecha_apertura
      AND ca.created_at <= COALESCE(t.fecha_cierre, now())
  );
