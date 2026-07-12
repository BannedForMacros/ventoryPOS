-- ============================================================================
-- COSTO CONGELADO POR ÍTEM DE VENTA (para el Reporte de Utilidad)
-- ============================================================================
-- venta_items.costo_unitario_base = costo por UNIDAD BASE del producto en el
-- momento de la venta (mismo criterio canónico del balance:
-- precio_costo del producto, o costo_promedio real del stock si aquel está en 0).
--
-- Sin esto la utilidad histórica se recalcularía con el costo ACTUAL (que muta
-- con cada compra) y el margen del pasado cambiaría solo. Igual que el TC
-- congelado de multimoneda: el costo se fija el día de la operación.
--
-- El backfill de ventas antiguas usa el costo actual como mejor aproximación
-- disponible (una sola vez); las ventas nuevas lo graban exacto desde el POS.
--
-- Idempotente: se puede correr varias veces con `psql -f`.
-- ============================================================================

ALTER TABLE venta_items ADD COLUMN IF NOT EXISTS costo_unitario_base NUMERIC(12,4) NULL;

-- Backfill una sola vez: solo filas donde sigue NULL (no pisa lo ya congelado).
UPDATE venta_items vi
SET costo_unitario_base = COALESCE(
        NULLIF(p.precio_costo, 0),
        (SELECT s.costo_promedio FROM stock s
          WHERE s.producto_id = p.id AND s.costo_promedio > 0
          ORDER BY s.id LIMIT 1),
        0)
FROM productos p
WHERE p.id = vi.producto_id
  AND vi.costo_unitario_base IS NULL;
