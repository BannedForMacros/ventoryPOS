-- =====================================================================
-- RECREAR ANTICIPADOS con montos REALES (ANTICIPADOS 15-07.xlsx)
-- Ferretería H&C — RUC 20600134648.  Total objetivo = 71,777.44
-- ---------------------------------------------------------------------
-- (1) Crea producto faltante (Techo 12 ITAL) + clientes faltantes (CORONEL, MEJIA).
-- (2) MILAGROS: UPDATE de precio 0.63 (CONSERVA su entrega del 14-07).
-- (3) Borra los 25 anticipos 'monto' migrados (venta_id NULL, 0 entregas).
-- (4) Recrea material venta-ligado por cliente con el valor real del Excel.
--     Se SALTAN los ya existentes del POS: ELVIS (#201, 5,675) y los fierros
--     de JIBAJA (#200, 575.20, vale P001-43843). Sin tesorería.
-- Portable (por RUC/nombre/email), transaccional. Probar con rollback.
-- =====================================================================
DO $$
DECLARE
  v_emp bigint; v_turno bigint; v_caja bigint; v_local bigint; v_admin bigint; v_und bigint;
  v_cli bigint; v_prod bigint; v_pu bigint; v_venta bigint; v_item bigint; v_ant bigint; v_uname varchar;
  v_mventa bigint; v_mitem bigint; v_mant bigint; v_mantitem bigint; v_mtot numeric; v_mpend numeric;
BEGIN
  SELECT id INTO v_emp FROM empresas WHERE ruc='20600134648';
  SELECT id, caja_id, local_id INTO v_turno, v_caja, v_local FROM turnos WHERE empresa_id=v_emp ORDER BY fecha_apertura, id LIMIT 1;
  SELECT id INTO v_admin FROM users WHERE email='admin@ferreteriahyc.com';
  SELECT id INTO v_und FROM unidades_medida WHERE empresa_id=v_emp AND nombre='Unidad' LIMIT 1;

  -- (1) Producto faltante: Ladrillo Techo 12 ITAL
  IF NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id=v_emp AND nombre='Ladrillo Techo 12 ITAL') THEN
    INSERT INTO productos (empresa_id,nombre,tipo,tipo_precio,precio_venta,precio_costo,activo,incluye_igv,created_at,updated_at)
    VALUES (v_emp,'Ladrillo Techo 12 ITAL','producto','fijo',2.85,0,true,true,NOW(),NOW()) RETURNING id INTO v_prod;
    INSERT INTO producto_unidades (producto_id,unidad_medida_id,es_base,factor_conversion,tipo_precio,precio_venta,precio_costo,activo,created_at,updated_at)
    VALUES (v_prod,v_und,true,1,'fijo',2.85,0,true,NOW(),NOW());
  END IF;

  -- (2) FIX MILAGROS (precio 0.63, conserva la entrega)
  SELECT id INTO v_mventa FROM ventas WHERE empresa_id=v_emp AND numero='MIG-P00100043546';
  IF v_mventa IS NOT NULL THEN
    SELECT id, cantidad INTO v_mitem, v_mtot FROM venta_items WHERE venta_id=v_mventa LIMIT 1;
    SELECT id INTO v_mant FROM cliente_anticipos WHERE empresa_id=v_emp AND venta_id=v_mventa;
    SELECT id, cantidad_pendiente INTO v_mantitem, v_mpend FROM cliente_anticipo_items WHERE cliente_anticipo_id=v_mant LIMIT 1;
    UPDATE ventas SET subtotal=ROUND(v_mtot*0.63,2), total=ROUND(v_mtot*0.63,2), monto_pagado=ROUND(v_mtot*0.63,2), updated_at=NOW() WHERE id=v_mventa;
    UPDATE venta_items SET precio_unitario=0.63, precio_original=0.63, subtotal=ROUND(v_mtot*0.63,2), updated_at=NOW() WHERE id=v_mitem;
    UPDATE cliente_anticipo_items SET precio_unitario=0.63, updated_at=NOW() WHERE id=v_mantitem;
    UPDATE cliente_anticipo_aplicaciones SET monto=ROUND(cantidad*0.63,2), updated_at=NOW() WHERE cliente_anticipo_id=v_mant;
    UPDATE cliente_anticipos SET monto=ROUND(v_mtot*0.63,2), saldo=ROUND(v_mpend*0.63,2), updated_at=NOW() WHERE id=v_mant;
  END IF;

  -- (3) Borrar los 25 anticipos 'monto' migrados (sin venta, sin entregas)
  DELETE FROM cliente_anticipos WHERE empresa_id=v_emp AND venta_id IS NULL;

  -- (4) Recrear material venta-ligado por cliente:

  -- ===== DISTRIBUIDORA DE PRODUCTOS DE CONSUMO MASIVOS  (total 34.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='DISTRIBUIDORA DE PRODUCTOS DE CONSUMO MASIVO SOCIEDAD ANONIMA CERRADA' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: DISTRIBUIDORA DE PRODUCTOS DE CONSUMO MASIVO SOCIEDAD ANONIMA CERRADA'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A01','ticket',34.00,0,0,34.00,'completada',TIMESTAMP '2025-01-23 12:00:00',false,34.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2025-01-23',34.00,34.00,'material','activo','Pendiente por entregar F001-1944 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 1/2 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 1/2 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,0.5,1,0.5,68.00,68.00,0,34.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,0.5,1,0.5,68.00,NOW(),NOW());

  -- ===== HERNANDEZ LLACSAHUANGA MARIO CESAR  (total 114.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='HERNANDEZ LLACSAHUANGA MARIO CESAR' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: HERNANDEZ LLACSAHUANGA MARIO CESAR'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A02','ticket',114.00,0,0,114.00,'completada',TIMESTAMP '2023-03-29 12:00:00',false,114.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2023-03-29',114.00,114.00,'material','activo','Pendiente por entregar B001-1260 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Afirmado S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Afirmado S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Afirmado S/M',v_uname,3.0,1,3.0,38.00,38.00,0,114.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Afirmado S/M',v_uname,3.0,1,3.0,38.00,NOW(),NOW());

  -- ===== SR. OMAR  (total 70.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='S.R OMAR' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: S.R OMAR'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A03','ticket',70.00,0,0,70.00,'completada',TIMESTAMP '2024-01-18 12:00:00',false,70.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2024-01-18',70.00,70.00,'material','activo','Pendiente por entregar P001-17909 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,2.0,1,2.0,35.00,35.00,0,70.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,2.0,1,2.0,35.00,NOW(),NOW());

  -- ===== HERNANDEZ MORALES HERMINIO  (total 547.50) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='HERNANDEZ MORALES HERMINIO' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: HERNANDEZ MORALES HERMINIO'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A04','ticket',547.50,0,0,547.50,'completada',TIMESTAMP '2023-08-29 12:00:00',false,547.50,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2023-08-29',547.50,547.50,'material','activo','Pendiente por entregar P001-14270 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,15.0,1,15.0,36.50,36.50,0,547.50,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,15.0,1,15.0,36.50,NOW(),NOW());

  -- ===== JUAN CLIENTE  (total 51.80) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='JUAN CLIENTE' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: JUAN CLIENTE'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A05','ticket',51.80,0,0,51.80,'completada',TIMESTAMP '2024-06-10 12:00:00',false,51.80,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2024-06-10',51.80,51.80,'material','activo','Pendiente por entregar P001-20876 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,2.0,1,2.0,25.90,25.90,0,51.80,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,2.0,1,2.0,25.90,NOW(),NOW());

  -- ===== JIBAJA NEYRA KEVIN OVET  (total 650.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='JIBAJA NEYRA KEVIN OVET - 999336049' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: JIBAJA NEYRA KEVIN OVET - 999336049'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A06','ticket',650.00,0,0,650.00,'completada',TIMESTAMP '2025-12-06 12:00:00',false,650.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2025-12-06',650.00,650.00,'material','activo','Pendiente por entregar P001-36692,P001-36693 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Cemento Azul Antisalitre PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Cemento Azul Antisalitre PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,10.0,1,10.0,32.50,32.50,0,325.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,10.0,1,10.0,32.50,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Cemento Azul Antisalitre PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Cemento Azul Antisalitre PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,10.0,1,10.0,32.50,32.50,0,325.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,10.0,1,10.0,32.50,NOW(),NOW());

  -- ===== PEREZ DIAZ MIGUEL  (total 4054.70) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='PEREZ DIAZ MIGUEL - 937744347' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: PEREZ DIAZ MIGUEL - 937744347'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A07','ticket',4054.70,0,0,4054.70,'completada',TIMESTAMP '2026-07-04 12:00:00',false,4054.70,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-07-04',4054.70,4054.70,'material','activo','Pendiente por entregar P001-36495,P001-38090,P001-43645 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,20.0,1,20.0,33.20,33.20,0,664.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,20.0,1,20.0,33.20,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 3/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 3/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,13.0,1,13.0,18.90,18.90,0,245.70,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,13.0,1,13.0,18.90,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='CEMENTO ROJO PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: CEMENTO ROJO PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,30.0,1,30.0,30.00,30.00,0,900.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,30.0,1,30.0,30.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 1/2 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 1/2 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,5.0,1,5.0,70.00,70.00,0,350.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,5.0,1,5.0,70.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Arena Amarilla S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Arena Amarilla S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Arena Amarilla S/M',v_uname,5.0,1,5.0,48.00,48.00,0,240.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Arena Amarilla S/M',v_uname,5.0,1,5.0,48.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='LADRILLO ESTANDAR 18 HUECOS TAYSON' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: LADRILLO ESTANDAR 18 HUECOS TAYSON'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'LADRILLO ESTANDAR 18 HUECOS TAYSON',v_uname,1500.0,1,1500.0,1.01,1.01,0,1515.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'LADRILLO ESTANDAR 18 HUECOS TAYSON',v_uname,1500.0,1,1500.0,1.01,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='PLASTICO AZUL-NEGRO 2METROS(ROLLO80MTS) S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: PLASTICO AZUL-NEGRO 2METROS(ROLLO80MTS) S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'PLASTICO AZUL-NEGRO 2METROS(ROLLO80MTS) S/M',v_uname,20.0,1,20.0,7.00,7.00,0,140.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'PLASTICO AZUL-NEGRO 2METROS(ROLLO80MTS) S/M',v_uname,20.0,1,20.0,7.00,NOW(),NOW());

  -- ===== TORRES RUIZ LUZ ANGELICA  (total 311.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='TORRES RUIZ LUZ ANGELICA' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: TORRES RUIZ LUZ ANGELICA'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A08','ticket',311.00,0,0,311.00,'completada',TIMESTAMP '2025-12-17 12:00:00',false,311.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2025-12-17',311.00,311.00,'material','activo','Pendiente por entregar P001-37178 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Cemento Azul Antisalitre PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Cemento Azul Antisalitre PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,8.0,1,8.0,33.00,33.00,0,264.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,8.0,1,8.0,33.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Arena Amarilla S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Arena Amarilla S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Arena Amarilla S/M',v_uname,1.0,1,1.0,47.00,47.00,0,47.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Arena Amarilla S/M',v_uname,1.0,1,1.0,47.00,NOW(),NOW());

  -- ===== CORONEL REGALADO DIONY JOSE  (total 6960.00) =====
  IF NOT EXISTS (SELECT 1 FROM clientes WHERE empresa_id=v_emp AND nombres='CORONEL REGALADO DIONY JOSE') THEN
    INSERT INTO clientes (empresa_id,nombres,tipo_documento,activo,es_cliente_general,created_at,updated_at) VALUES (v_emp,'CORONEL REGALADO DIONY JOSE','DNI',true,false,NOW(),NOW()); END IF;
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='CORONEL REGALADO DIONY JOSE' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: CORONEL REGALADO DIONY JOSE'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A09','ticket',6960.00,0,0,6960.00,'completada',TIMESTAMP '2026-03-13 12:00:00',false,6960.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-03-13',6960.00,6960.00,'material','activo','Pendiente por entregar P001-40490 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Ladrillo Techo 12 ITAL' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Ladrillo Techo 12 ITAL'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Ladrillo Techo 12 ITAL',v_uname,1400.0,1,1400.0,2.85,2.85,0,3990.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Ladrillo Techo 12 ITAL',v_uname,1400.0,1,1400.0,2.85,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='CEMENTO ROJO MOCHICA' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: CEMENTO ROJO MOCHICA'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'CEMENTO ROJO MOCHICA',v_uname,100.0,1,100.0,29.70,29.70,0,2970.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'CEMENTO ROJO MOCHICA',v_uname,100.0,1,100.0,29.70,NOW(),NOW());

  -- ===== AUTOFACIL  (total 420.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='AUTO FACIL EN CUOTAS, ISABEL E.I.R.L.' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: AUTO FACIL EN CUOTAS, ISABEL E.I.R.L.'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A10','ticket',420.00,0,0,420.00,'completada',TIMESTAMP '2026-04-24 12:00:00',false,420.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-04-24',420.00,420.00,'material','activo','Pendiente por entregar P001-41821 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 1/2 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 1/2 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,6.0,1,6.0,70.00,70.00,0,420.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,6.0,1,6.0,70.00,NOW(),NOW());

  -- ===== BUENAÑO TAPIA MERY EMILIN  (total 55.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='BUENAÑO TAPIA MERY EMILIN - 980461765' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: BUENAÑO TAPIA MERY EMILIN - 980461765'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A11','ticket',55.00,0,0,55.00,'completada',TIMESTAMP '2026-04-29 12:00:00',false,55.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-04-29',55.00,55.00,'material','activo','Pendiente por entregar P001-41961 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Base S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Base S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Base S/M',v_uname,1.0,1,1.0,55.00,55.00,0,55.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Base S/M',v_uname,1.0,1,1.0,55.00,NOW(),NOW());

  -- ===== BUSTAMANTE GONZALES RIGOBERTO  (total 10150.04) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='BUSTAMANTE GONZALES RIGOBERTO - 967795790' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: BUSTAMANTE GONZALES RIGOBERTO - 967795790'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A12','ticket',10150.04,0,0,10150.04,'completada',TIMESTAMP '2026-07-03 12:00:00',false,10150.04,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-07-03',10150.04,10150.04,'material','activo','Pendiente por entregar P001-43645 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 5/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 5/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,16.0,1,16.0,51.50,51.50,0,824.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,16.0,1,16.0,51.50,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,85.0,1,85.0,33.00,33.00,0,2805.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,85.0,1,85.0,33.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 3/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 3/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,48.0,1,48.0,18.70,18.70,0,897.60,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,48.0,1,48.0,18.70,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 6 MM SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 6 MM SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 6 MM SIDERPERU',v_uname,29.0,1,29.0,8.00,8.00,0,232.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 6 MM SIDERPERU',v_uname,29.0,1,29.0,8.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 16 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 16 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,28.0,1,28.0,4.00,4.00,0,112.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,28.0,1,28.0,4.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Clavo P/Madera 2 1/2 Confer CONFER' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Clavo P/Madera 2 1/2 Confer CONFER'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Clavo P/Madera 2 1/2 Confer CONFER',v_uname,10.0,1,10.0,4.40,4.40,0,44.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Clavo P/Madera 2 1/2 Confer CONFER',v_uname,10.0,1,10.0,4.40,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='CEMENTO ROJO PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: CEMENTO ROJO PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,70.0,1,70.0,31.70,31.70,0,2219.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,70.0,1,70.0,31.70,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Cemento Azul Antisalitre PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Cemento Azul Antisalitre PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,37.0,1,37.0,34.20,34.20,0,1265.40,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,37.0,1,37.0,34.20,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Arena Amarilla S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Arena Amarilla S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Arena Amarilla S/M',v_uname,5.0,1,5.0,50.00,50.00,0,250.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Arena Amarilla S/M',v_uname,5.0,1,5.0,50.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 1/2 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 1/2 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,5.0,1,5.0,70.00,70.00,0,350.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,5.0,1,5.0,70.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Ladrillo Techo 12 SIPAN' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Ladrillo Techo 12 SIPAN'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Ladrillo Techo 12 SIPAN',v_uname,436.0,1,436.0,2.64,2.64,0,1151.04,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Ladrillo Techo 12 SIPAN',v_uname,436.0,1,436.0,2.64,NOW(),NOW());

  -- ===== MEJIA HERNANDEZ KAREN STEPHANY  (total 720.00) =====
  IF NOT EXISTS (SELECT 1 FROM clientes WHERE empresa_id=v_emp AND nombres='MEJIA HERNANDEZ KAREN STEPHANY') THEN
    INSERT INTO clientes (empresa_id,nombres,tipo_documento,activo,es_cliente_general,created_at,updated_at) VALUES (v_emp,'MEJIA HERNANDEZ KAREN STEPHANY','DNI',true,false,NOW(),NOW()); END IF;
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='MEJIA HERNANDEZ KAREN STEPHANY' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: MEJIA HERNANDEZ KAREN STEPHANY'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A13','ticket',720.00,0,0,720.00,'completada',TIMESTAMP '2026-07-06 12:00:00',false,720.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-07-06',720.00,720.00,'material','activo','Pendiente por entregar P001-43700 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='LADRILLO PANDERETA FORTES' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: LADRILLO PANDERETA FORTES'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'LADRILLO PANDERETA FORTES',v_uname,1000.0,1,1000.0,0.72,0.72,0,720.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'LADRILLO PANDERETA FORTES',v_uname,1000.0,1,1000.0,0.72,NOW(),NOW());

  -- ===== MORETO ALTAMIRANO ZARELA NOEMI  (total 19420.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='MORETO ALTAMIRANO ZARELA NOEMI - 992750519' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: MORETO ALTAMIRANO ZARELA NOEMI - 992750519'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A14','ticket',19420.00,0,0,19420.00,'completada',TIMESTAMP '2026-07-08 12:00:00',false,19420.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-07-08',19420.00,19420.00,'material','activo','Pendiente por entregar P001-43762 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,200.0,1,200.0,33.10,33.10,0,6620.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,200.0,1,200.0,33.10,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 5/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 5/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,70.0,1,70.0,51.70,51.70,0,3619.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,70.0,1,70.0,51.70,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Cemento Azul Antisalitre PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Cemento Azul Antisalitre PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,150.0,1,150.0,34.30,34.30,0,5145.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,150.0,1,150.0,34.30,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='CEMENTO ROJO PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: CEMENTO ROJO PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,100.0,1,100.0,31.70,31.70,0,3170.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,100.0,1,100.0,31.70,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 8 MM SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 8 MM SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 8 MM SIDERPERU',v_uname,50.0,1,50.0,13.75,13.75,0,687.50,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 8 MM SIDERPERU',v_uname,50.0,1,50.0,13.75,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 16 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 16 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,25.0,1,25.0,3.80,3.80,0,95.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,25.0,1,25.0,3.80,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 08 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 08 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 08 PRODAC',v_uname,10.0,1,10.0,3.90,3.90,0,39.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 08 PRODAC',v_uname,10.0,1,10.0,3.90,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Clavo P/Madera 2 1/2 Confer CONFER' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Clavo P/Madera 2 1/2 Confer CONFER'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Clavo P/Madera 2 1/2 Confer CONFER',v_uname,5.0,1,5.0,4.40,4.40,0,22.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Clavo P/Madera 2 1/2 Confer CONFER',v_uname,5.0,1,5.0,4.40,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Clavo P/Madera 3 Confer CONFER' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Clavo P/Madera 3 Confer CONFER'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Clavo P/Madera 3 Confer CONFER',v_uname,5.0,1,5.0,4.50,4.50,0,22.50,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Clavo P/Madera 3 Confer CONFER',v_uname,5.0,1,5.0,4.50,NOW(),NOW());

  -- ===== BURGA MALDONADO ALBERTO ANANIAS  (total 420.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='BURGA MALDONADO ALBERTO ANANIAS - 971648368' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: BURGA MALDONADO ALBERTO ANANIAS - 971648368'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A15','ticket',420.00,0,0,420.00,'completada',TIMESTAMP '2026-07-10 12:00:00',false,420.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-07-10',420.00,420.00,'material','activo','Pendiente por entregar P001-43820 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Arena Amarilla S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Arena Amarilla S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Arena Amarilla S/M',v_uname,0.5,1,0.5,60.00,60.00,0,30.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Arena Amarilla S/M',v_uname,0.5,1,0.5,60.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='LADRILLO PANDERETA TAYSON' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: LADRILLO PANDERETA TAYSON'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'LADRILLO PANDERETA TAYSON',v_uname,500.0,1,500.0,0.78,0.78,0,390.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'LADRILLO PANDERETA TAYSON',v_uname,500.0,1,500.0,0.78,NOW(),NOW());

  -- ===== TARRILLO VASQUEZ CLARA ESTHER  (total 34.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='TARRILLO VASQUEZ CLARA ESTHER' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: TARRILLO VASQUEZ CLARA ESTHER'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A16','ticket',34.00,0,0,34.00,'completada',TIMESTAMP '2023-05-02 12:00:00',false,34.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2023-05-02',34.00,34.00,'material','activo','Pendiente por entregar P002-8967 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 1/2 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 1/2 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,0.5,1,0.5,68.00,68.00,0,34.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,0.5,1,0.5,68.00,NOW(),NOW());

  -- ===== LUIS MEDINA  (total 13351.70) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='LUIS MEDINA' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: LUIS MEDINA'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A17','ticket',13351.70,0,0,13351.70,'completada',TIMESTAMP '2024-01-03 12:00:00',false,13351.70,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2024-01-03',13351.70,13351.70,'material','activo','Pendiente por entregar P002-12046 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Cemento Azul Antisalitre PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Cemento Azul Antisalitre PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,100.0,1,100.0,31.70,31.70,0,3170.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Cemento Azul Antisalitre PACASMAYO',v_uname,100.0,1,100.0,31.70,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 16 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 16 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,5.0,1,5.0,3.80,3.80,0,19.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,5.0,1,5.0,3.80,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 16 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 16 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,57.0,1,57.0,3.80,3.80,0,216.60,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,57.0,1,57.0,3.80,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Clavo P/Madera 2 1/2 Confer CONFER' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Clavo P/Madera 2 1/2 Confer CONFER'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Clavo P/Madera 2 1/2 Confer CONFER',v_uname,10.0,1,10.0,3.80,3.80,0,38.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Clavo P/Madera 2 1/2 Confer CONFER',v_uname,10.0,1,10.0,3.80,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 6 MM SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 6 MM SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 6 MM SIDERPERU',v_uname,42.0,1,42.0,8.20,8.20,0,344.40,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 6 MM SIDERPERU',v_uname,42.0,1,42.0,8.20,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 8 MM SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 8 MM SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 8 MM SIDERPERU',v_uname,75.0,1,75.0,14.20,14.20,0,1065.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 8 MM SIDERPERU',v_uname,75.0,1,75.0,14.20,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 3/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 3/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,38.0,1,38.0,19.40,19.40,0,737.20,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,38.0,1,38.0,19.40,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,140.0,1,140.0,34.50,34.50,0,4830.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,140.0,1,140.0,34.50,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 5/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 5/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,55.0,1,55.0,53.30,53.30,0,2931.50,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,55.0,1,55.0,53.30,NOW(),NOW());

  -- ===== LACHE HERNANDEZ ORFELINDA  (total 46.50) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='LACHE HERNANDEZ ORFELINDA ELIZABETH' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: LACHE HERNANDEZ ORFELINDA ELIZABETH'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A18','ticket',46.50,0,0,46.50,'completada',TIMESTAMP '2025-12-30 12:00:00',false,46.50,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2025-12-30',46.50,46.50,'material','activo','Pendiente por entregar P002-28299 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='LADRILLO PANDERETA FORTES' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: LADRILLO PANDERETA FORTES'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'LADRILLO PANDERETA FORTES',v_uname,75.0,1,75.0,0.50,0.50,0,37.50,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'LADRILLO PANDERETA FORTES',v_uname,75.0,1,75.0,0.50,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 16 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 16 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,2.0,1,2.0,4.50,4.50,0,9.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,2.0,1,2.0,4.50,NOW(),NOW());

  -- ===== CARHUAJULCA IRURETA LUZ ANGELICA  (total 240.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='CARHUAJULCA IRURETA LUZ ANGELICA' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: CARHUAJULCA IRURETA LUZ ANGELICA'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A19','ticket',240.00,0,0,240.00,'completada',TIMESTAMP '2026-01-27 12:00:00',false,240.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-01-27',240.00,240.00,'material','activo','Pendiente por entregar P002-29187 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 1/2 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 1/2 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,4.0,1,4.0,60.00,60.00,0,240.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 1/2 S/M',v_uname,4.0,1,4.0,60.00,NOW(),NOW());

  -- ===== PESANTES CORTIJO MARTINA  (total 120.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='PESANTES CORTIJO SILVIA MARTINA' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: PESANTES CORTIJO SILVIA MARTINA'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A20','ticket',120.00,0,0,120.00,'completada',TIMESTAMP '2026-02-19 12:00:00',false,120.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-02-19',120.00,120.00,'material','activo','Pendiente por entregar P002-29931 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Base S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Base S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Base S/M',v_uname,3.0,1,3.0,40.00,40.00,0,120.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Base S/M',v_uname,3.0,1,3.0,40.00,NOW(),NOW());

  -- ===== PEREZ PEREZ JUAN CARLOS  (total 285.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='PEREZ PEREZ JUAN CARLOS' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: PEREZ PEREZ JUAN CARLOS'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A21','ticket',285.00,0,0,285.00,'completada',TIMESTAMP '2026-02-23 12:00:00',false,285.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-02-23',285.00,285.00,'material','activo','Pendiente por entregar P002-30094 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Ladrillo Concreto Tipo 12 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Ladrillo Concreto Tipo 12 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Ladrillo Concreto Tipo 12 S/M',v_uname,150.0,1,150.0,1.90,1.90,0,285.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Ladrillo Concreto Tipo 12 S/M',v_uname,150.0,1,150.0,1.90,NOW(),NOW());

  -- ===== WALTER -CASA BLANCA  (total 1540.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='WALTER - CASA BLANCA' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: WALTER - CASA BLANCA'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A22','ticket',1540.00,0,0,1540.00,'completada',TIMESTAMP '2026-04-17 12:00:00',false,1540.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-04-17',1540.00,1540.00,'material','activo','Pendiente por entregar P002-31344 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='CEMENTO ROJO PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: CEMENTO ROJO PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,50.0,1,50.0,30.80,30.80,0,1540.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,50.0,1,50.0,30.80,NOW(),NOW());

  -- ===== YDROGO GALVEZ YONATAN  (total 2211.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='YDROGO GALVEZ YONATAN' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: YDROGO GALVEZ YONATAN'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A23','ticket',2211.00,0,0,2211.00,'completada',TIMESTAMP '2026-03-07 12:00:00',false,2211.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-03-07',2211.00,2211.00,'material','activo','Pendiente por entregar P002-30422 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,48.0,1,48.0,33.00,33.00,0,1584.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,48.0,1,48.0,33.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 3/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 3/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,33.0,1,33.0,19.00,19.00,0,627.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 3/8 SIDERPERU',v_uname,33.0,1,33.0,19.00,NOW(),NOW());

  -- ===== CAMISAN AQUINO ESTHER  (total 1516.00) =====
  SELECT id INTO v_cli FROM clientes WHERE empresa_id=v_emp AND nombres='CAMISAN AQUINO ESTHER - 988092730' LIMIT 1;
  IF v_cli IS NULL THEN RAISE EXCEPTION 'cliente no encontrado: CAMISAN AQUINO ESTHER - 988092730'; END IF;
  INSERT INTO ventas (empresa_id,local_id,turno_id,caja_id,user_id,cliente_id,numero,tipo_comprobante,subtotal,descuento_total,igv,total,estado,fecha_venta,es_credito,monto_pagado,saldo_pendiente,moneda,created_at,updated_at)
  VALUES (v_emp,v_local,v_turno,v_caja,v_admin,v_cli,'MIG-A24','ticket',1516.00,0,0,1516.00,'completada',TIMESTAMP '2026-05-19 12:00:00',false,1516.00,0,'PEN',NOW(),NOW()) RETURNING id INTO v_venta;
  INSERT INTO cliente_anticipos (empresa_id,cliente_id,user_id,venta_id,fecha,monto,saldo,tipo_valorizacion,estado,observacion,moneda,created_at,updated_at)
  VALUES (v_emp,v_cli,v_admin,v_venta,DATE '2026-05-19',1516.00,1516.00,'material','activo','Pendiente por entregar P002-31999 (ANTICIPADOS 15-07, real)','PEN',NOW(),NOW()) RETURNING id INTO v_ant;
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Alambre Negro 16 PRODAC' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Alambre Negro 16 PRODAC'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,10.0,1,10.0,4.20,4.20,0,42.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Alambre Negro 16 PRODAC',v_uname,10.0,1,10.0,4.20,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Arena Amarilla S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Arena Amarilla S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Arena Amarilla S/M',v_uname,4.0,1,4.0,45.00,45.00,0,180.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Arena Amarilla S/M',v_uname,4.0,1,4.0,45.00,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='CEMENTO ROJO PACASMAYO' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: CEMENTO ROJO PACASMAYO'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,4.0,1,4.0,31.20,31.20,0,124.80,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'CEMENTO ROJO PACASMAYO',v_uname,4.0,1,4.0,31.20,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 1/2 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 1/2 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,20.0,1,20.0,34.70,34.70,0,694.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 1/2 SIDERPERU',v_uname,20.0,1,20.0,34.70,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Fierro 5/8 SIDERPERU' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Fierro 5/8 SIDERPERU'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,4.0,1,4.0,53.80,53.80,0,215.20,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Fierro 5/8 SIDERPERU',v_uname,4.0,1,4.0,53.80,NOW(),NOW());
  SELECT pu.id, um.nombre INTO v_pu, v_uname FROM producto_unidades pu JOIN unidades_medida um ON um.id=pu.unidad_medida_id JOIN productos p ON p.id=pu.producto_id WHERE p.empresa_id=v_emp AND p.nombre='Piedra Chancada 3/4 S/M' AND pu.es_base LIMIT 1;
  IF v_pu IS NULL THEN RAISE EXCEPTION 'producto/unidad no encontrado: Piedra Chancada 3/4 S/M'; END IF;
  SELECT producto_id INTO v_prod FROM producto_unidades WHERE id=v_pu;
  INSERT INTO venta_items (venta_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_base,precio_unitario,precio_original,descuento_item,subtotal,incluye_igv,costo_unitario_base,created_at,updated_at)
  VALUES (v_venta,v_prod,v_pu,'Piedra Chancada 3/4 S/M',v_uname,4.0,1,4.0,65.00,65.00,0,260.00,false,0,NOW(),NOW()) RETURNING id INTO v_item;
  INSERT INTO cliente_anticipo_items (cliente_anticipo_id,venta_item_id,producto_id,producto_unidad_id,producto_nombre,unidad_nombre,cantidad,factor_conversion,cantidad_pendiente,precio_unitario,created_at,updated_at)
  VALUES (v_ant,v_item,v_prod,v_pu,'Piedra Chancada 3/4 S/M',v_uname,4.0,1,4.0,65.00,NOW(),NOW());
END $$;

-- ===== Verificación =====
SELECT count(*) AS anticipos, count(*) FILTER (WHERE tipo_valorizacion='material') AS material,
       ROUND(SUM(saldo),2) AS saldo_total
FROM cliente_anticipos WHERE empresa_id=(SELECT id FROM empresas WHERE ruc='20600134648') AND estado='activo';
