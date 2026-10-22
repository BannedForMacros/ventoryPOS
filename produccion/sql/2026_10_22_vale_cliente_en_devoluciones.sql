-- ============================================================================
--  ventoryPOS · Vale de devolución a nombre de una persona
--  Fecha: 2026-10-22
--
--  QUÉ HACE: agrega una columna opcional. No modifica ninguna fila existente
--  y es idempotente.
--
--    devoluciones.vale_cliente_id  a quién queda el vale (anticipo) cuando el
--                                  reembolso es «Vale / Crédito a favor».
--                                  NULL = el cliente de la venta (como hoy).
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;
SET LOCAL lock_timeout = '5s';

ALTER TABLE public.devoluciones ADD COLUMN IF NOT EXISTS vale_cliente_id BIGINT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'devoluciones_vale_cliente_id_foreign'
    ) THEN
        ALTER TABLE public.devoluciones
            ADD CONSTRAINT devoluciones_vale_cliente_id_foreign
            FOREIGN KEY (vale_cliente_id) REFERENCES public.clientes (id) ON DELETE SET NULL;
    END IF;
END $$;

COMMENT ON COLUMN public.devoluciones.vale_cliente_id IS
    'A quién queda el vale cuando el reembolso es vale/crédito a favor. NULL = el cliente de la venta.';

INSERT INTO public.migrations (migration, batch)
SELECT '2026_10_22_000001_vale_cliente_en_devoluciones',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations WHERE migration = '2026_10_22_000001_vale_cliente_en_devoluciones'
);

COMMIT;
