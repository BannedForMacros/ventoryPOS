-- =====================================================================
-- Pendiente por entregar VENTA-LIGADO: MILAGROS HERRERA (RUC 20600134648)
-- ---------------------------------------------------------------------
-- Comprobante P00100043546 (2026-06-29): 5000 Ladrillo Artesanal Bodoque
-- King Kong. Pagó S/16,980.70, no se llevó la mercadería.
--
-- Se arma como el "pendiente por entregar" del POS: venta + venta_item +
-- cliente_anticipo (material, con venta_id) + cliente_anticipo_item. Así la
-- ENTREGA parcial (Finanzas → Anticipos) DESCUENTA STOCK del almacén de
-- ventas (permite negativo → Bodoque 0 → -1500 al entregar 1500; luego una
-- entrada lo repone).
--
--   * Stock NO se toca al crear (lo pendiente sale recién en la entrega).
--   * SIN tesorería: el dinero ya está en el saldo de apertura del corte 10;
--     re-registrarlo duplicaría la caja.
--   * precio_unitario = 3.40 (16,980.70/5000 = 3.39614 no cabe en 2 dec);
--     la entrega final se capea al saldo → el total cierra EXACTO en 16,980.70.
--
-- Efecto en balance: "Clientes Anticipos" +16,980.70; balance neto -16,980.70
-- (se reconoce la deuda de mercadería). Regenerar el balance después.
--
-- Portable (todo por RUC/nombre/email; sin IDs). Idempotente (NOT EXISTS).
-- =====================================================================

DO $$
DECLARE
  v_emp bigint; v_cli bigint; v_usr bigint;
  v_prod bigint; v_pu bigint; v_unidad varchar;
  v_turno bigint; v_caja bigint; v_local bigint;
  v_venta bigint; v_item bigint; v_ant bigint;
  v_total  numeric := 16980.70;
  v_cant   numeric := 5000;
  v_precio numeric := 3.40;
  v_nombre varchar := 'Ladrillo Artesanal Bodoque King Kong S/M';
BEGIN
  SELECT id INTO v_emp FROM empresas WHERE ruc = '20600134648';
  IF v_emp IS NULL THEN RAISE EXCEPTION 'Empresa RUC 20600134648 no encontrada'; END IF;

  SELECT id INTO v_cli FROM clientes  WHERE empresa_id = v_emp AND nombres = 'MILAGROS' AND apellidos = 'HERRERA';
  SELECT id INTO v_usr FROM users     WHERE email = 'admin@ferreteriahyc.com';
  SELECT id INTO v_prod FROM productos WHERE empresa_id = v_emp AND nombre = v_nombre;
  SELECT pu.id, um.nombre INTO v_pu, v_unidad
    FROM producto_unidades pu JOIN unidades_medida um ON um.id = pu.unidad_medida_id
    WHERE pu.producto_id = v_prod AND pu.es_base = true LIMIT 1;
  SELECT id, caja_id, local_id INTO v_turno, v_caja, v_local
    FROM turnos WHERE empresa_id = v_emp ORDER BY fecha_apertura, id LIMIT 1;

  IF v_cli IS NULL OR v_usr IS NULL OR v_prod IS NULL OR v_pu IS NULL OR v_turno IS NULL THEN
    RAISE EXCEPTION 'No se pudo resolver: cli=% usr=% prod=% pu=% turno=%', v_cli, v_usr, v_prod, v_pu, v_turno;
  END IF;

  IF EXISTS (SELECT 1 FROM cliente_anticipos WHERE empresa_id = v_emp AND observacion LIKE '%P00100043546%') THEN
    RAISE NOTICE 'Ya existe el pendiente de P00100043546; no se duplica.';
    RETURN;
  END IF;

  -- 1) Venta (sin tesorería; el stock NO se descuenta aquí: es pendiente)
  INSERT INTO ventas (empresa_id, local_id, turno_id, caja_id, user_id, cliente_id, numero,
      tipo_comprobante, subtotal, descuento_total, igv, total, estado, fecha_venta,
      es_credito, monto_pagado, saldo_pendiente, moneda, created_at, updated_at)
  VALUES (v_emp, v_local, v_turno, v_caja, v_usr, v_cli, 'MIG-P00100043546',
      'ticket', v_total, 0, 0, v_total, 'completada', TIMESTAMP '2026-06-29 12:00:00',
      false, v_total, 0, 'PEN', NOW(), NOW())
  RETURNING id INTO v_venta;

  -- 2) Ítem de la venta (todo pendiente por entregar)
  INSERT INTO venta_items (venta_id, producto_id, producto_unidad_id, producto_nombre, unidad_nombre,
      cantidad, factor_conversion, cantidad_base, precio_unitario, precio_original, descuento_item,
      subtotal, incluye_igv, costo_unitario_base, created_at, updated_at)
  VALUES (v_venta, v_prod, v_pu, v_nombre, v_unidad,
      v_cant, 1, v_cant, v_precio, v_precio, 0,
      v_total, false, 0, NOW(), NOW())
  RETURNING id INTO v_item;

  -- 3) Anticipo material ligado a la venta (con detalle → la entrega mueve stock)
  INSERT INTO cliente_anticipos (empresa_id, cliente_id, user_id, venta_id, fecha, monto, saldo,
      tipo_valorizacion, estado, observacion, moneda, created_at, updated_at)
  VALUES (v_emp, v_cli, v_usr, v_venta, DATE '2026-06-29', v_total, v_total,
      'material', 'activo',
      'Pendiente por entregar P00100043546 (MILAGROS HERRERA, 5000 Ladrillo Bodoque King Kong, corrección migración)',
      'PEN', NOW(), NOW())
  RETURNING id INTO v_ant;

  -- 4) Detalle del anticipo (une con el venta_item; 5000 u pendientes)
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id, venta_item_id, producto_id, producto_unidad_id,
      producto_nombre, unidad_nombre, cantidad, factor_conversion, cantidad_pendiente, precio_unitario,
      created_at, updated_at)
  VALUES (v_ant, v_item, v_prod, v_pu, v_nombre, v_unidad, v_cant, 1, v_cant, v_precio, NOW(), NOW());

  RAISE NOTICE 'OK: venta % / item % / anticipo % — 5000 u pendientes, S/%', v_venta, v_item, v_ant, v_total;
END $$;

-- Verificación
SELECT ca.id AS anticipo_id, ca.venta_id, ca.tipo_valorizacion, ca.saldo,
       cai.cantidad_pendiente AS unidades_pendientes, cai.precio_unitario,
       pr.nombre AS producto, v.numero AS venta
FROM   cliente_anticipos ca
JOIN   empresas e  ON e.id = ca.empresa_id
JOIN   cliente_anticipo_items cai ON cai.cliente_anticipo_id = ca.id
JOIN   productos pr ON pr.id = cai.producto_id
JOIN   ventas v ON v.id = ca.venta_id
WHERE  e.ruc = '20600134648' AND ca.observacion LIKE '%P00100043546%';
