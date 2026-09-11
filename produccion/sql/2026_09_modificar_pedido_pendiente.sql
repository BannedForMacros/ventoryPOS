-- Modificar pedido pendiente por entregar (días después de la venta).
-- Cuando al modificar el pedido SOBRA dinero, se crea un anticipo de dinero
-- (saldo a favor, o devuelto en el acto) enlazado a la venta que lo originó,
-- para poder revertirlo si la venta se anula. SEGURO: solo columna nueva vacía.
ALTER TABLE cliente_anticipos ADD COLUMN IF NOT EXISTS venta_origen_id bigint REFERENCES ventas(id);
CREATE INDEX IF NOT EXISTS cliente_anticipos_venta_origen_idx ON cliente_anticipos (venta_origen_id) WHERE venta_origen_id IS NOT NULL;
