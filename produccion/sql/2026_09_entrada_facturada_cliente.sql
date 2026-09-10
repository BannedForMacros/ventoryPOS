-- Entradas con facturación a nombre del cliente.
-- El comprobante del proveedor sale a nombre de un cliente del negocio, pero
-- la deuda sigue siendo de la empresa: la entrada cuenta NORMAL en Cuentas
-- por Pagar y en el balance. El flag + cliente_id son informativos y sirven
-- de filtro ("qué compras se facturaron a otro cliente, no a mi empresa").
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS facturada_a_cliente boolean NOT NULL DEFAULT false;
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS cliente_id bigint REFERENCES clientes(id);
CREATE INDEX IF NOT EXISTS entradas_cliente_idx ON entradas (cliente_id) WHERE cliente_id IS NOT NULL;
