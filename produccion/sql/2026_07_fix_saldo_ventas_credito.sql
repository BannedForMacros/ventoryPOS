-- Repara ventas a crédito cuyo monto_pagado/saldo_pendiente quedó mal por editar
-- una venta con abonos (el edit ignoraba los abonos). Recalcula desde los pagos
-- REALES: venta_pagos (neto de vuelto) + venta_abonos. Solo toca ventas a crédito
-- completadas cuyo cálculo no cuadra. NO toca tesorería (el dinero ya está bien).
WITH pagado AS (
  SELECT v.id,
    COALESCE((SELECT SUM(vp.monto - COALESCE(vp.vuelto,0)) FROM venta_pagos vp WHERE vp.venta_id=v.id),0)
    + COALESCE((SELECT SUM(va.monto) FROM venta_abonos va WHERE va.venta_id=v.id),0) AS real_pagado
  FROM ventas v
  WHERE v.es_credito = true AND v.estado = 'completada'
)
UPDATE ventas v SET
  monto_pagado    = ROUND(p.real_pagado, 2),
  saldo_pendiente = GREATEST(0, ROUND(v.total - p.real_pagado, 2))
FROM pagado p
WHERE p.id = v.id
  AND ABS(v.monto_pagado - p.real_pagado) > 0.01;   -- solo las descuadradas
