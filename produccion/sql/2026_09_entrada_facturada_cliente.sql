-- Entradas con facturación directa al cliente.
-- El proveedor factura/cobra directamente al cliente del negocio (el dueño
-- actúa solo como intermediario). La mercadería entra al inventario normal,
-- pero la deuda NO es de la empresa: estas entradas se excluyen de Cuentas
-- por Pagar y del balance (deuda propia).
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS facturada_a_cliente boolean NOT NULL DEFAULT false;
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS cliente_id bigint REFERENCES clientes(id);
CREATE INDEX IF NOT EXISTS entradas_cliente_idx ON entradas (cliente_id) WHERE cliente_id IS NOT NULL;
