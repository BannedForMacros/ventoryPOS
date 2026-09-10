-- Cobrar una cuenta por cobrar consumiendo el ANTICIPO del cliente.
-- El cliente dejó dinero adelantado (pasivo) y además debe una venta al
-- crédito: el abono se cobra del anticipo SIN mover caja (ese dinero ya
-- entró cuando se creó el anticipo). Espejo del "pagar con adelanto de
-- proveedor" de Cuentas por Pagar.
ALTER TABLE venta_abonos ADD COLUMN IF NOT EXISTS cliente_anticipo_id bigint REFERENCES cliente_anticipos(id);
-- Enlace exacto aplicación→abono para poder revertir en pareja al anular.
ALTER TABLE cliente_anticipo_aplicaciones ADD COLUMN IF NOT EXISTS venta_abono_id bigint REFERENCES venta_abonos(id);
