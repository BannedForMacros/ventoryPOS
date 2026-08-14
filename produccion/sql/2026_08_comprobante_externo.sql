-- ─────────────────────────────────────────────────────────────────────────────
-- COMPROBANTES ELECTRÓNICOS EXTERNOS (SUNAT propio del cliente)
--
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción con el usuario dueño de la base de datos (normalmente postgres).
--
-- Añade `ventas.numero_comprobante` para guardar el número de boleta/factura
-- electrónica que el cliente genera por su cuenta en otro sistema. Es informativo:
-- ventoryPOS no emite por FacturaMac para estos tipos.
-- ─────────────────────────────────────────────────────────────────────────────
BEGIN;

ALTER TABLE ventas ADD COLUMN IF NOT EXISTS numero_comprobante VARCHAR(30) NULL;

COMMIT;
