-- ─────────────────────────────────────────────────────────────────────────────
-- Pagos de entrada (compras) en efectivo → deben SALIR de la caja del turno
-- ─────────────────────────────────────────────────────────────────────────────
-- Problema: una cajera paga una entrada (compra) en efectivo desde su caja. Ese
-- dinero sale del cajón, pero calcularMontoEsperado() no lo restaba, así que el
-- sistema esperaba MÁS efectivo del que había → "les aparece como si tuvieran más".
--
-- entrada_pagos no tenía turno_id (igual que pasaba con venta_abonos). Lo agregamos
-- NULLABLE: un pago de entrada sin turno (transferencia al banco, admin sin caja)
-- queda en NULL y no afecta caja. Los pagos en efectivo con turno se restan del
-- efectivo esperado.
--
-- Backfill de registros antiguos: cada pago → turno del MISMO usuario que estaba
-- ABIERTO en el MOMENTO en que se registró el pago. Usamos `created_at` (instante
-- real del registro), NO `fecha` (que hereda la fecha de la entrada y puede estar
-- atrasada). Así el pago cae en el turno cuya caja realmente entregó el efectivo.
-- Seguro para turnos cerrados (su arqueo muestra el valor GUARDADO; solo cambia
-- el resumen de caja en vivo, no reescribe el arqueo histórico).

ALTER TABLE entrada_pagos
    ADD COLUMN IF NOT EXISTS turno_id BIGINT NULL
    REFERENCES turnos(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS entrada_pagos_turno_id_index ON entrada_pagos(turno_id);

UPDATE entrada_pagos ep
SET turno_id = (
    SELECT t.id
    FROM turnos t
    WHERE t.user_id = ep.user_id
      AND ep.created_at >= t.fecha_apertura
      AND ep.created_at <= COALESCE(t.fecha_cierre, now())
    ORDER BY t.fecha_apertura DESC
    LIMIT 1
)
WHERE ep.turno_id IS NULL
  AND EXISTS (
    SELECT 1
    FROM turnos t
    WHERE t.user_id = ep.user_id
      AND ep.created_at >= t.fecha_apertura
      AND ep.created_at <= COALESCE(t.fecha_cierre, now())
  );
