-- ─────────────────────────────────────────────────────────────────────────────
-- Devoluciones de anticipo de cliente → caja de la que realmente sale
-- ─────────────────────────────────────────────────────────────────────────────
-- Hasta ahora la devolución se detectaba por el turno ABIERTO del usuario que
-- registraba el egreso (user_id + created_at). Eso falla cuando la devolución
-- la registra otro usuario (admin, gerente) o cuando no hay un turno abierto en
-- ese instante: el billete sale del cajón pero el sistema nunca lo descuenta.
--
-- Agregamos turno_devolucion_id NULLABLE: al devolver se guarda el turno activo
-- del usuario (o el turno original del anticipo si no hay ninguno). El cálculo
-- de efectivo esperado resta la devolución de ESE turno.
--
-- Backfill de registros ANTIGUOS: intentamos derivar el turno de salida usando
-- el mismo criterio anterior (turno abierto del user_id del movimiento en el
-- created_at). Si no hay coincidencia, caemos de nuevo al turno donde entró el
-- dinero (turno_id del anticipo), que es la caja más probable de la que salió.

ALTER TABLE cliente_anticipos
    ADD COLUMN IF NOT EXISTS turno_devolucion_id BIGINT NULL
    REFERENCES turnos(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS cliente_anticipos_turno_devolucion_id_index
    ON cliente_anticipos(turno_devolucion_id);

-- Registros antiguos: primero intentar asignar el turno activo del usuario que
-- hizo la devolución, luego el turno original del anticipo.
UPDATE cliente_anticipos ca
SET turno_devolucion_id = COALESCE(
    (
        SELECT t.id
        FROM turnos t
        WHERE t.user_id = ca.user_id
          AND ca.updated_at >= t.fecha_apertura
          AND ca.updated_at <= COALESCE(t.fecha_cierre, now())
        ORDER BY t.fecha_apertura DESC
        LIMIT 1
    ),
    ca.turno_id
)
WHERE ca.estado = 'devuelto'
  AND ca.turno_devolucion_id IS NULL;
