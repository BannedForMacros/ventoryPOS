-- ============================================================================
--  ventoryPOS · Venta anulada con nota de crédito
--  Fecha: 2026-10-10
--
--  QUÉ HACE: agrega ventas.anulada_nc_devolucion_id. Una boleta/factura que ya
--  estaba en SUNAT se "anula" con una devolución total + Nota de Crédito
--  (AnulacionConNotaCredito). Por dentro la venta sigue 'completada' —así el
--  stock y el dinero no se devuelven dos veces (los revirtió la devolución)—,
--  pero en pantalla debe verse y tratarse como ANULADA. Esta columna lo marca.
--
--  Idempotente. Además marca las ya hechas (devolución completada con el motivo
--  de sistema "Anulación de venta").
-- ============================================================================

ALTER TABLE ventas ADD COLUMN IF NOT EXISTS anulada_nc_devolucion_id bigint REFERENCES devoluciones(id) ON DELETE SET NULL;

UPDATE ventas v
   SET anulada_nc_devolucion_id = d.id
  FROM devoluciones d
  JOIN devolucion_motivos m ON m.id = d.motivo_id
 WHERE d.venta_id = v.id
   AND m.slug = 'anulacion_venta'
   AND d.estado = 'completada'
   AND v.anulada_nc_devolucion_id IS NULL;
