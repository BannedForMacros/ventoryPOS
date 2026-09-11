-- Vale/Crédito a favor de una devolución → ANTICIPO real del cliente.
-- Antes "vale_credito" era solo una etiqueta: no creaba nada y el crédito
-- vivía en la memoria de la cajera. Ahora la devolución completada crea
-- automáticamente un anticipo de dinero (sin tesorería: la plata ya entró
-- con la venta original) que el cliente puede usar en el POS o para cobrar
-- sus cuentas por cobrar. El enlace permite revertirlo al anular.
ALTER TABLE cliente_anticipos ADD COLUMN IF NOT EXISTS devolucion_id bigint REFERENCES devoluciones(id);
