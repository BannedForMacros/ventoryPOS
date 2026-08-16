-- ─────────────────────────────────────────────────────────────────────────────
-- COMPROBANTES ELECTRÓNICOS EXTERNOS (SUNAT propio del cliente)
--
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción con el usuario dueño de la base de datos (normalmente postgres).
--
-- 1. Añade `ventas.numero_comprobante` para guardar el número de boleta/factura
--    electrónica que el cliente genera por su cuenta en otro sistema.
-- 2. Extiende el CHECK de `ventas.tipo_comprobante` para aceptar
--    `boleta_externa` y `factura_externa`.
--
-- Es informativo: ventoryPOS no emite por FacturaMac para estos tipos.
-- ─────────────────────────────────────────────────────────────────────────────
BEGIN;

ALTER TABLE ventas ADD COLUMN IF NOT EXISTS numero_comprobante VARCHAR(30) NULL;

ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_tipo_comprobante_check;
ALTER TABLE ventas ADD CONSTRAINT ventas_tipo_comprobante_check
    CHECK (tipo_comprobante::text = ANY (ARRAY[
        'ticket'::text,
        'boleta'::text,
        'factura'::text,
        'boleta_externa'::text,
        'factura_externa'::text
    ]));

COMMIT;
