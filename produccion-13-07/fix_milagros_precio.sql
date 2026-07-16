-- =====================================================================
-- CORRECCIÓN DE PRECIO — MILAGROS HERRERA (RUC 20600134648)
-- ---------------------------------------------------------------------
-- El pendiente de MILAGROS (venta MIG-P00100043546, 5000 Ladrillo Bodoque)
-- se creó con precio ERRADO 3.40 (16,980.70). El precio REAL del Excel de
-- anticipados es 0.63. Se CORRIGE por UPDATE — NO se borra nada: la entrega
-- del 14-07 (1500 u) se conserva; solo se ajustan precio y montos derivados.
--
-- Cantidades intactas: 5000 total · 1500 entregados · 3500 pendientes.
-- Con precio 0.63:  total=3,150.00 · entregado=945.00 · saldo=2,205.00
--   (3,150 − 945 = 2,205  y  3,500 × 0.63 = 2,205  ✓ cuadra)
--
-- Portable (resuelve por RUC + numero de venta; sin IDs fijos). Idempotente.
-- =====================================================================

DO $$
DECLARE
  v_emp bigint; v_venta bigint; v_item bigint; v_ant bigint; v_antitem bigint;
  v_precio    numeric := 0.63;
  v_cant_tot  numeric; v_cant_pend numeric;
  v_total     numeric; v_saldo numeric;
BEGIN
  SELECT id INTO v_emp   FROM empresas WHERE ruc = '20600134648';
  SELECT id INTO v_venta FROM ventas   WHERE empresa_id = v_emp AND numero = 'MIG-P00100043546';
  IF v_venta IS NULL THEN RAISE EXCEPTION 'No se encontró la venta MIG-P00100043546'; END IF;

  SELECT id, cantidad INTO v_item, v_cant_tot FROM venta_items WHERE venta_id = v_venta LIMIT 1;
  SELECT id INTO v_ant     FROM cliente_anticipos      WHERE empresa_id = v_emp AND venta_id = v_venta;
  SELECT id, cantidad_pendiente INTO v_antitem, v_cant_pend
         FROM cliente_anticipo_items WHERE cliente_anticipo_id = v_ant LIMIT 1;

  v_total := ROUND(v_cant_tot  * v_precio, 2);   -- 5000 * 0.63 = 3150.00
  v_saldo := ROUND(v_cant_pend * v_precio, 2);   -- 3500 * 0.63 = 2205.00

  -- 1) Venta y su ítem
  UPDATE ventas SET subtotal = v_total, total = v_total, monto_pagado = v_total, updated_at = NOW()
  WHERE id = v_venta;
  UPDATE venta_items SET precio_unitario = v_precio, precio_original = v_precio,
                         subtotal = v_total, updated_at = NOW()
  WHERE id = v_item;

  -- 2) Detalle del anticipo (precio congelado) — cantidades intactas
  UPDATE cliente_anticipo_items SET precio_unitario = v_precio, updated_at = NOW()
  WHERE id = v_antitem;

  -- 3) Entrega(s) ya registrada(s): recalcular su monto al precio real (cantidad intacta)
  UPDATE cliente_anticipo_aplicaciones SET monto = ROUND(cantidad * v_precio, 2), updated_at = NOW()
  WHERE cliente_anticipo_id = v_ant;

  -- 4) Cabecera del anticipo: monto = total original; saldo = pendiente × precio
  UPDATE cliente_anticipos SET monto = v_total, saldo = v_saldo, updated_at = NOW()
  WHERE id = v_ant;

  RAISE NOTICE 'MILAGROS OK — precio 0.63 | total %, entregado %, saldo %',
      v_total, ROUND((v_cant_tot - v_cant_pend) * v_precio, 2), v_saldo;
END $$;

-- Verificación
SELECT v.total AS venta_total, vi.precio_unitario AS precio,
       ca.monto AS ant_monto, ca.saldo AS ant_saldo,
       cai.cantidad AS u_total, cai.cantidad_pendiente AS u_pend,
       (SELECT SUM(monto) FROM cliente_anticipo_aplicaciones a WHERE a.cliente_anticipo_id = ca.id) AS entregado
FROM ventas v
JOIN empresas e ON e.id = v.empresa_id
JOIN venta_items vi ON vi.venta_id = v.id
JOIN cliente_anticipos ca ON ca.venta_id = v.id
JOIN cliente_anticipo_items cai ON cai.cliente_anticipo_id = ca.id
WHERE e.ruc = '20600134648' AND v.numero = 'MIG-P00100043546';
