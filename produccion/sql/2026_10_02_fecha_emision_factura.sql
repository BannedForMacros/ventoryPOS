-- ============================================================================
--  ventoryPOS · Fecha de emisión elegible para la factura
--  Fecha: 2026-10-02
--
--  QUÉ HACE: agrega dos columnas opcionales. No modifica ninguna fila existente
--  y es idempotente.
--
--    empresas.pos_fecha_emision_factura  el POS muestra un selector de fecha al
--                                        elegir Factura (por defecto apagado)
--    ventas.fecha_emision                fecha con la que se emite el comprobante;
--                                        NULL = la fecha de la venta, como siempre
--
--  La fecha de la VENTA (caja, turno, reportes) no cambia: solo la del comprobante.
--  SUNAT admite hasta 3 días atrás; lo valida la aplicación y lo vuelve a validar
--  FacturaMac.
--
--  Con el selector apagado, todo queda exactamente como hoy.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;
SET LOCAL lock_timeout = '5s';

ALTER TABLE public.empresas ADD COLUMN IF NOT EXISTS pos_fecha_emision_factura BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE public.ventas   ADD COLUMN IF NOT EXISTS fecha_emision DATE NULL;

COMMENT ON COLUMN public.empresas.pos_fecha_emision_factura IS
    'El POS muestra un selector de fecha de emisión al elegir Factura (hoy y hasta 3 días atrás).';
COMMENT ON COLUMN public.ventas.fecha_emision IS
    'Fecha con la que se emite el comprobante. NULL = la fecha de la venta.';

INSERT INTO public.migrations (migration, batch)
SELECT '2026_10_02_000001_fecha_emision_factura',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations WHERE migration = '2026_10_02_000001_fecha_emision_factura'
);

COMMIT;
