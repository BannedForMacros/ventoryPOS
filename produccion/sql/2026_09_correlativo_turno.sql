-- Correlativo inicial de ventas configurable por turno.
-- La cajera puede fijar al abrir el turno el número donde arranca la
-- numeración de ventas (ej. 1001 -> V-1001, V-1002, ...). NULL = V-0001.
ALTER TABLE turnos ADD COLUMN IF NOT EXISTS correlativo_inicial integer;
