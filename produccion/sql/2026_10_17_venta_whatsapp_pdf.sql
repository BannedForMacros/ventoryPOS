-- ============================================================================
--  ventoryPOS · Envío del PDF de la venta por WhatsApp
--  Fecha: 2026-10-17
--
--  QUÉ HACE: agrega una columna opcional. No modifica ninguna fila existente
--  y es idempotente.
--
--    empresas.venta_whatsapp_pdf  la lista de ventas muestra el botón WhatsApp
--                                 para mandar el PDF de la venta al cliente
--                                 (por defecto apagado)
--
--  Con la opción apagada, todo queda exactamente como hoy.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;
SET LOCAL lock_timeout = '5s';

ALTER TABLE public.empresas ADD COLUMN IF NOT EXISTS venta_whatsapp_pdf BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN public.empresas.venta_whatsapp_pdf IS
    'La lista de ventas muestra el botón WhatsApp para enviar el PDF de la venta al cliente.';

INSERT INTO public.migrations (migration, batch)
SELECT '2026_10_17_000001_venta_whatsapp_pdf',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations WHERE migration = '2026_10_17_000001_venta_whatsapp_pdf'
);

COMMIT;
