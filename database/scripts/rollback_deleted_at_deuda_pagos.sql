-- Rollback: quitar soft-delete de deuda_pagos
-- SOLO ejecutar si se quiere deshacer la columna deleted_at.

BEGIN;

DROP INDEX IF EXISTS public.deuda_pagos_deleted_at_index;
ALTER TABLE public.deuda_pagos DROP COLUMN IF EXISTS deleted_at;

COMMIT;
