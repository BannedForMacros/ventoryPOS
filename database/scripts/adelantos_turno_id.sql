-- Integrar adelantos a proveedores al sistema "Afecta caja" unificado.
-- Agrega turno_id para imputar el egreso a la caja de un turno específico.
-- NULL = sin turno (no afecta ninguna caja).

ALTER TABLE proveedor_adelantos
ADD COLUMN turno_id BIGINT NULL REFERENCES turnos(id) ON DELETE SET NULL;

CREATE INDEX idx_proveedor_adelantos_turno ON proveedor_adelantos(turno_id);
