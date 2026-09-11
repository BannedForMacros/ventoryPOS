-- Deudas vinculadas a un tercero (OPCIONAL) + cruces con CxC/CxP/anticipos.
-- SEGURO PARA DATOS EXISTENTES: solo agrega columnas nuevas vacías; las
-- deudas actuales (nombre de texto libre) siguen funcionando exactamente
-- igual. El vínculo se elige al crear/editar y se puede agregar después.
ALTER TABLE deudas ADD COLUMN IF NOT EXISTS cliente_id   bigint REFERENCES clientes(id);
ALTER TABLE deudas ADD COLUMN IF NOT EXISTS proveedor_id bigint REFERENCES proveedores(id);

-- Cruces desde el movimiento de la deuda:
--  - cobrar una deuda por cobrar consumiendo el ANTICIPO del cliente vinculado
--  - compensar una deuda por pagar contra una VENTA al crédito (CxC)
--  - compensar una deuda por cobrar contra una COMPRA con saldo (CxP)
ALTER TABLE deuda_pagos ADD COLUMN IF NOT EXISTS cliente_anticipo_id     bigint REFERENCES cliente_anticipos(id);
ALTER TABLE deuda_pagos ADD COLUMN IF NOT EXISTS compensacion_venta_id   bigint REFERENCES ventas(id);
ALTER TABLE deuda_pagos ADD COLUMN IF NOT EXISTS compensacion_entrada_id bigint REFERENCES entradas(id);

-- Contraparte en venta_abonos / entrada_pagos cuando el otro lado es una deuda.
ALTER TABLE venta_abonos  ADD COLUMN IF NOT EXISTS compensacion_deuda_id bigint REFERENCES deudas(id);
ALTER TABLE entrada_pagos ADD COLUMN IF NOT EXISTS compensacion_deuda_id bigint REFERENCES deudas(id);

-- Trazabilidad del anticipo consumido desde una deuda.
ALTER TABLE cliente_anticipo_aplicaciones ADD COLUMN IF NOT EXISTS deuda_pago_id bigint REFERENCES deuda_pagos(id);
