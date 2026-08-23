-- Agrega soft-delete a los movimientos de deudas (deuda_pagos)
-- Ejecutar en producción para poder ver movimientos eliminados.

BEGIN;

ALTER TABLE public.deuda_pagos
    ADD COLUMN IF NOT EXISTS deleted_at timestamp(0) without time zone;

CREATE INDEX IF NOT EXISTS deuda_pagos_deleted_at_index
    ON public.deuda_pagos USING btree (deleted_at)
    WHERE deleted_at IS NOT NULL;

COMMIT;
