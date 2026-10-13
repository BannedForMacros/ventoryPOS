-- ============================================================================
--  ventoryPOS · Descripción opcional por línea de venta
--  Fecha: 2026-10-13
--
--  QUÉ HACE: agrega venta_items.descripcion (varchar 120, NULL). Las empresas
--  que venden el mismo producto en varias líneas pueden anotar en cada una algo
--  que las distinga; sale en el ticket interno, no en el comprobante electrónico.
--
--  Columna nueva y nula: no reescribe la tabla ni toca filas existentes.
--  Idempotente (IF NOT EXISTS): correrlo de nuevo no cambia nada.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía. Correrlo ANTES del deploy.
-- ============================================================================
BEGIN;

ALTER TABLE venta_items ADD COLUMN IF NOT EXISTS descripcion varchar(120) NULL;

COMMIT;
