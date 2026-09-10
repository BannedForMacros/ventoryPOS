-- Compensación CxC ↔ CxP: cancelar una venta al crédito contra una compra
-- al mismo tercero (o libre) SIN mover dinero de caja. Se registra un abono
-- en la venta y un pago en la entrada por el mismo monto, enlazados por un
-- grupo; ninguno genera movimiento de tesorería.
ALTER TABLE venta_abonos  ADD COLUMN IF NOT EXISTS compensacion_grupo_id varchar(36);
ALTER TABLE venta_abonos  ADD COLUMN IF NOT EXISTS compensacion_entrada_id bigint REFERENCES entradas(id);
ALTER TABLE entrada_pagos ADD COLUMN IF NOT EXISTS compensacion_grupo_id varchar(36);
ALTER TABLE entrada_pagos ADD COLUMN IF NOT EXISTS compensacion_venta_id bigint REFERENCES ventas(id);
CREATE INDEX IF NOT EXISTS venta_abonos_compensacion_idx  ON venta_abonos (compensacion_grupo_id)  WHERE compensacion_grupo_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS entrada_pagos_compensacion_idx ON entrada_pagos (compensacion_grupo_id) WHERE compensacion_grupo_id IS NOT NULL;
