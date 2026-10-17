-- ============================================================================
--  ventoryPOS · Formato del PDF de la venta enviado por WhatsApp
--  Fecha: 2026-10-17
--
--  QUÉ HACE: agrega una columna opcional. No modifica ninguna fila existente
--  y es idempotente.
--
--    empresas.venta_pdf_formato  'a4' (hoja completa) o 'ticket' (80 mm);
--                                por defecto 'a4'
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;
SET LOCAL lock_timeout = '5s';

ALTER TABLE public.empresas ADD COLUMN IF NOT EXISTS venta_pdf_formato VARCHAR(10) NOT NULL DEFAULT 'a4';

COMMENT ON COLUMN public.empresas.venta_pdf_formato IS
    'Formato del PDF de la venta enviado por WhatsApp: a4 o ticket (80 mm).';

INSERT INTO public.migrations (migration, batch)
SELECT '2026_10_17_000002_venta_pdf_formato',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations WHERE migration = '2026_10_17_000002_venta_pdf_formato'
);

COMMIT;
