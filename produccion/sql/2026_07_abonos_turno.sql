-- ─────────────────────────────────────────────────────────────────────────────
-- Abonos de cuentas por cobrar → caja del turno
-- ─────────────────────────────────────────────────────────────────────────────
-- Los abonos (venta_abonos) no tenían turno_id, así que un abono en efectivo
-- entraba al cajón pero el sistema no lo esperaba (aparecía "sobrante"/faltante)
-- y no se reflejaba en la caja del turno en la pantalla de Ventas.
--
-- Agregamos turno_id NULLABLE: un abono puede no tener turno (transferencia al
-- banco registrada por admin sin caja abierta) → queda NULL y no afecta caja.
-- Los abonos en efectivo con turno sí suman al efectivo esperado del turno.
--
-- Backfill de registros ANTIGUOS: se atribuye cada abono al turno del MISMO
-- cajero cuya ventana [fecha_apertura, fecha_cierre] contiene la fecha del abono.
-- Es SEGURO para turnos cerrados: su arqueo muestra el valor GUARDADO
-- (monto_cierre_esperado/diferencia snapshot al cierre); calcularMontoEsperado()
-- solo se recalcula en vivo para las cards de caja de Ventas, no reescribe el
-- arqueo histórico. Los abonos sin turno que calce (p.ej. transferencia al banco
-- en un día sin caja del cajero) quedan en NULL y no afectan caja.

ALTER TABLE venta_abonos
    ADD COLUMN IF NOT EXISTS turno_id BIGINT NULL
    REFERENCES turnos(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS venta_abonos_turno_id_index ON venta_abonos(turno_id);

UPDATE venta_abonos va
SET turno_id = (
    SELECT t.id
    FROM turnos t
    WHERE t.user_id = va.user_id
      AND va.fecha >= t.fecha_apertura::date
      AND va.fecha <= COALESCE(t.fecha_cierre::date, va.fecha)
    ORDER BY t.fecha_apertura DESC
    LIMIT 1
)
WHERE va.turno_id IS NULL
  AND EXISTS (
    SELECT 1
    FROM turnos t
    WHERE t.user_id = va.user_id
      AND va.fecha >= t.fecha_apertura::date
      AND va.fecha <= COALESCE(t.fecha_cierre::date, va.fecha)
  );
