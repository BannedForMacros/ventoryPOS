-- Rollback: eliminar la tabla y secuencia de cancelaciones de anticipo.
-- SOLO ejecutar si se quiere deshacer la creación. Esto borra los datos.

BEGIN;

DROP TABLE IF EXISTS public.cliente_anticipo_cancelaciones CASCADE;
DROP SEQUENCE IF EXISTS public.cliente_anticipo_cancelaciones_id_seq CASCADE;

COMMIT;
