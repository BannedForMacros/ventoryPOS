-- ============================================================================
--  ventoryPOS · Costo de catálogo con 4 decimales
--  Fecha: 2026-10-12
--
--  QUÉ HACE: amplía productos.precio_costo y producto_unidades.precio_costo de
--  numeric(12,2) a numeric(14,4). Solo amplía: ningún valor existente cambia.
--
--  Con 2 decimales, el costo de lo que se vende por unidad suelta (una tableta
--  de S/ 0.0163) se guardaba como 0.02. El kardex y las entradas ya usan 4.
--  Va junto con el cambio de los modelos (cast decimal:4).
--
--  Reescribe las dos tablas (son chicas): bloqueo de segundos.
--  Idempotente: correrlo de nuevo no cambia nada.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================
BEGIN;

ALTER TABLE productos         ALTER COLUMN precio_costo TYPE numeric(14,4);
ALTER TABLE producto_unidades ALTER COLUMN precio_costo TYPE numeric(14,4);

COMMIT;
