-- ============================================================================
--  ventoryPOS · Índice de venta_items por venta
--  Fecha: 2026-10-06
--
--  QUÉ HACE: crea el índice venta_items(venta_id). Solo acelera: no cambia datos.
--  POR QUÉ: el reporte de utilidad reparte el descuento global de cada venta
--  entre sus líneas (UtilidadService::lineaNeta) y busca las líneas de cada
--  venta; sin índice recorría toda la tabla una y otra vez (HYC: ~8 s).
--
--  CONCURRENTLY: se crea sin bloquear ventas en curso. Por eso NO va dentro de
--  BEGIN/COMMIT. Idempotente (IF NOT EXISTS).
-- ============================================================================

CREATE INDEX CONCURRENTLY IF NOT EXISTS venta_items_venta_id_idx ON venta_items (venta_id);
