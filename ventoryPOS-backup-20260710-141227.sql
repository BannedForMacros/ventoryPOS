--
-- PostgreSQL database dump
--

\restrict bRgjSs61c063zj7E55Jx3tGbsOvYv1NbV1r7Hgkj8NcNcFFPdqDaU2AcNlcIrG6

-- Dumped from database version 17.10 (Homebrew)
-- Dumped by pg_dump version 17.10 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_turno_id_foreign;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_local_id_foreign;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_descuento_concepto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_caja_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_pagos DROP CONSTRAINT IF EXISTS venta_pagos_venta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_pagos DROP CONSTRAINT IF EXISTS venta_pagos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_pagos DROP CONSTRAINT IF EXISTS venta_pagos_cuenta_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_items DROP CONSTRAINT IF EXISTS venta_items_venta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_items DROP CONSTRAINT IF EXISTS venta_items_producto_unidad_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_items DROP CONSTRAINT IF EXISTS venta_items_producto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_items DROP CONSTRAINT IF EXISTS venta_items_descuento_concepto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_abonos DROP CONSTRAINT IF EXISTS venta_abonos_venta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_abonos DROP CONSTRAINT IF EXISTS venta_abonos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_abonos DROP CONSTRAINT IF EXISTS venta_abonos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.venta_abonos DROP CONSTRAINT IF EXISTS venta_abonos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_rol_id_foreign;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_local_id_foreign;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.unidades_medida DROP CONSTRAINT IF EXISTS unidades_medida_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.turnos DROP CONSTRAINT IF EXISTS turnos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos DROP CONSTRAINT IF EXISTS turnos_user_cierre_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos DROP CONSTRAINT IF EXISTS turnos_local_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos DROP CONSTRAINT IF EXISTS turnos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos DROP CONSTRAINT IF EXISTS turnos_caja_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_consolidaciones DROP CONSTRAINT IF EXISTS turno_consolidaciones_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_consolidaciones DROP CONSTRAINT IF EXISTS turno_consolidaciones_turno_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_consolidaciones DROP CONSTRAINT IF EXISTS turno_consolidaciones_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_consolidacion_items DROP CONSTRAINT IF EXISTS turno_consolidacion_items_turno_consolidacion_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_consolidacion_items DROP CONSTRAINT IF EXISTS turno_consolidacion_items_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_consolidacion_items DROP CONSTRAINT IF EXISTS turno_consolidacion_items_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_cierre_productos DROP CONSTRAINT IF EXISTS turno_cierre_productos_turno_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_cierre_productos DROP CONSTRAINT IF EXISTS turno_cierre_productos_producto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo DROP CONSTRAINT IF EXISTS turno_arqueo_turno_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo_metodos DROP CONSTRAINT IF EXISTS turno_arqueo_metodos_turno_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo_metodos DROP CONSTRAINT IF EXISTS turno_arqueo_metodos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_user_recepcion_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_user_envio_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias_detalle DROP CONSTRAINT IF EXISTS transferencias_detalle_unidad_medida_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias_detalle DROP CONSTRAINT IF EXISTS transferencias_detalle_transferencia_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias_detalle DROP CONSTRAINT IF EXISTS transferencias_detalle_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_almacen_origen_id_fkey;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_almacen_destino_id_fkey;
ALTER TABLE IF EXISTS ONLY public.stock DROP CONSTRAINT IF EXISTS stock_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.stock DROP CONSTRAINT IF EXISTS stock_almacen_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas DROP CONSTRAINT IF EXISTS salidas_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas DROP CONSTRAINT IF EXISTS salidas_turno_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas DROP CONSTRAINT IF EXISTS salidas_salida_tipo_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas DROP CONSTRAINT IF EXISTS salidas_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas_detalle DROP CONSTRAINT IF EXISTS salidas_detalle_unidad_medida_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas_detalle DROP CONSTRAINT IF EXISTS salidas_detalle_salida_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas_detalle DROP CONSTRAINT IF EXISTS salidas_detalle_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salidas DROP CONSTRAINT IF EXISTS salidas_almacen_id_fkey;
ALTER TABLE IF EXISTS ONLY public.salida_tipos DROP CONSTRAINT IF EXISTS salida_tipos_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.roles DROP CONSTRAINT IF EXISTS roles_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedores DROP CONSTRAINT IF EXISTS proveedores_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelantos DROP CONSTRAINT IF EXISTS proveedor_adelantos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelantos DROP CONSTRAINT IF EXISTS proveedor_adelantos_proveedor_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelantos DROP CONSTRAINT IF EXISTS proveedor_adelantos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelantos DROP CONSTRAINT IF EXISTS proveedor_adelantos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelantos DROP CONSTRAINT IF EXISTS proveedor_adelantos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelanto_aplicaciones DROP CONSTRAINT IF EXISTS proveedor_adelanto_aplicaciones_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelanto_aplicaciones DROP CONSTRAINT IF EXISTS proveedor_adelanto_aplicaciones_proveedor_adelanto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelanto_aplicaciones DROP CONSTRAINT IF EXISTS proveedor_adelanto_aplicaciones_entrada_id_foreign;
ALTER TABLE IF EXISTS ONLY public.productos DROP CONSTRAINT IF EXISTS productos_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.productos DROP CONSTRAINT IF EXISTS productos_categoria_id_fkey;
ALTER TABLE IF EXISTS ONLY public.producto_unidades DROP CONSTRAINT IF EXISTS producto_unidades_unidad_medida_id_fkey;
ALTER TABLE IF EXISTS ONLY public.producto_unidades DROP CONSTRAINT IF EXISTS producto_unidades_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.planilla_descuentos DROP CONSTRAINT IF EXISTS planilla_descuentos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.planilla_descuentos DROP CONSTRAINT IF EXISTS planilla_descuentos_registrado_por_foreign;
ALTER TABLE IF EXISTS ONLY public.planilla_descuentos DROP CONSTRAINT IF EXISTS planilla_descuentos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.planilla_descuentos DROP CONSTRAINT IF EXISTS planilla_descuentos_aplicado_por_foreign;
ALTER TABLE IF EXISTS ONLY public.permisos DROP CONSTRAINT IF EXISTS permisos_rol_id_foreign;
ALTER TABLE IF EXISTS ONLY public.permisos DROP CONSTRAINT IF EXISTS permisos_modulo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.modulos DROP CONSTRAINT IF EXISTS modulos_padre_id_foreign;
ALTER TABLE IF EXISTS ONLY public.metodos_pago DROP CONSTRAINT IF EXISTS metodos_pago_tipo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.metodos_pago DROP CONSTRAINT IF EXISTS metodos_pago_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.locales DROP CONSTRAINT IF EXISTS locales_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_turno_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_local_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_gasto_tipo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_gasto_concepto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gasto_tipos DROP CONSTRAINT IF EXISTS gasto_tipos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gasto_conceptos DROP CONSTRAINT IF EXISTS gasto_conceptos_gasto_tipo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.gasto_conceptos DROP CONSTRAINT IF EXISTS gasto_conceptos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_proveedor_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entradas_detalle DROP CONSTRAINT IF EXISTS entradas_detalle_unidad_medida_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entradas_detalle DROP CONSTRAINT IF EXISTS entradas_detalle_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entradas_detalle DROP CONSTRAINT IF EXISTS entradas_detalle_entrada_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_almacen_id_fkey;
ALTER TABLE IF EXISTS ONLY public.entrada_pagos DROP CONSTRAINT IF EXISTS entrada_pagos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entrada_pagos DROP CONSTRAINT IF EXISTS entrada_pagos_proveedor_adelanto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entrada_pagos DROP CONSTRAINT IF EXISTS entrada_pagos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entrada_pagos DROP CONSTRAINT IF EXISTS entrada_pagos_entrada_id_foreign;
ALTER TABLE IF EXISTS ONLY public.entrada_pagos DROP CONSTRAINT IF EXISTS entrada_pagos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_venta_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_user_aprobacion_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_turno_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_motivo_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_local_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones_detalle DROP CONSTRAINT IF EXISTS devoluciones_detalle_venta_item_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones_detalle DROP CONSTRAINT IF EXISTS devoluciones_detalle_producto_unidad_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones_detalle DROP CONSTRAINT IF EXISTS devoluciones_detalle_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones_detalle DROP CONSTRAINT IF EXISTS devoluciones_detalle_motivo_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones_detalle DROP CONSTRAINT IF EXISTS devoluciones_detalle_devolucion_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_caja_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devolucion_pagos DROP CONSTRAINT IF EXISTS devolucion_pagos_metodo_pago_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devolucion_pagos DROP CONSTRAINT IF EXISTS devolucion_pagos_devolucion_id_fkey;
ALTER TABLE IF EXISTS ONLY public.devolucion_motivos DROP CONSTRAINT IF EXISTS devolucion_motivos_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.deudas DROP CONSTRAINT IF EXISTS deudas_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.deudas DROP CONSTRAINT IF EXISTS deudas_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.deuda_pagos DROP CONSTRAINT IF EXISTS deuda_pagos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.deuda_pagos DROP CONSTRAINT IF EXISTS deuda_pagos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.deuda_pagos DROP CONSTRAINT IF EXISTS deuda_pagos_deuda_id_foreign;
ALTER TABLE IF EXISTS ONLY public.deuda_pagos DROP CONSTRAINT IF EXISTS deuda_pagos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_venta_item_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_venta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_descuento_concepto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_aprobado_por_foreign;
ALTER TABLE IF EXISTS ONLY public.descuento_conceptos DROP CONSTRAINT IF EXISTS descuento_conceptos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cuentas DROP CONSTRAINT IF EXISTS cuentas_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cuenta_movimientos DROP CONSTRAINT IF EXISTS cuenta_movimientos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cuenta_movimientos DROP CONSTRAINT IF EXISTS cuenta_movimientos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cuenta_movimientos DROP CONSTRAINT IF EXISTS cuenta_movimientos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cuenta_metodo_pago DROP CONSTRAINT IF EXISTS cuenta_metodo_pago_metodo_pago_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cuenta_metodo_pago DROP CONSTRAINT IF EXISTS cuenta_metodo_pago_cuenta_id_fkey;
ALTER TABLE IF EXISTS ONLY public.clientes DROP CONSTRAINT IF EXISTS clientes_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_producto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_metodo_pago_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_cuenta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipo_aplicaciones DROP CONSTRAINT IF EXISTS cliente_anticipo_aplicaciones_venta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipo_aplicaciones DROP CONSTRAINT IF EXISTS cliente_anticipo_aplicaciones_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipo_aplicaciones DROP CONSTRAINT IF EXISTS cliente_anticipo_aplicaciones_cliente_anticipo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_venta_id_foreign;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_profesional_id_foreign;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_local_id_foreign;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_created_by_foreign;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cita_items DROP CONSTRAINT IF EXISTS cita_items_producto_unidad_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cita_items DROP CONSTRAINT IF EXISTS cita_items_producto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cita_items DROP CONSTRAINT IF EXISTS cita_items_cita_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_turno_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario_items DROP CONSTRAINT IF EXISTS cierres_inventario_items_producto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario_items DROP CONSTRAINT IF EXISTS cierres_inventario_items_cierre_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_almacen_id_fkey;
ALTER TABLE IF EXISTS ONLY public.categorias DROP CONSTRAINT IF EXISTS categorias_empresa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_local_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.balances_diarios DROP CONSTRAINT IF EXISTS balances_diarios_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.balances_diarios DROP CONSTRAINT IF EXISTS balances_diarios_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.balance_diario_items DROP CONSTRAINT IF EXISTS balance_diario_items_balance_diario_id_foreign;
ALTER TABLE IF EXISTS ONLY public.auditoria DROP CONSTRAINT IF EXISTS auditoria_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.auditoria DROP CONSTRAINT IF EXISTS auditoria_empresa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.almacenes DROP CONSTRAINT IF EXISTS almacenes_local_id_fkey;
ALTER TABLE IF EXISTS ONLY public.almacenes DROP CONSTRAINT IF EXISTS almacenes_empresa_id_fkey;
DROP INDEX IF EXISTS public.ventas_idempotency_key_unique;
DROP INDEX IF EXISTS public.ventas_cxc_index;
DROP INDEX IF EXISTS public.venta_abonos_venta_id_fecha_index;
DROP INDEX IF EXISTS public.turno_consolidaciones_empresa_id_fecha_index;
DROP INDEX IF EXISTS public.tipos_cambio_fecha_moneda_unique;
DROP INDEX IF EXISTS public.sessions_user_id_index;
DROP INDEX IF EXISTS public.sessions_last_activity_index;
DROP INDEX IF EXISTS public.proveedor_adelantos_empresa_id_estado_index;
DROP INDEX IF EXISTS public.planilla_descuentos_empresa_id_user_id_estado_index;
DROP INDEX IF EXISTS public.jobs_queue_index;
DROP INDEX IF EXISTS public.idx_salidas_turno;
DROP INDEX IF EXISTS public.idx_salidas_tipo;
DROP INDEX IF EXISTS public.idx_salidas_fecha;
DROP INDEX IF EXISTS public.idx_salidas_empresa;
DROP INDEX IF EXISTS public.idx_salidas_detalle_salida;
DROP INDEX IF EXISTS public.idx_salidas_detalle_producto;
DROP INDEX IF EXISTS public.idx_salidas_almacen;
DROP INDEX IF EXISTS public.idx_salida_tipos_empresa;
DROP INDEX IF EXISTS public.idx_proveedores_empresa;
DROP INDEX IF EXISTS public.idx_proveedores_activo;
DROP INDEX IF EXISTS public.idx_entradas_proveedor;
DROP INDEX IF EXISTS public.idx_devoluciones_venta;
DROP INDEX IF EXISTS public.idx_devoluciones_turno;
DROP INDEX IF EXISTS public.idx_devoluciones_motivo;
DROP INDEX IF EXISTS public.idx_devoluciones_local;
DROP INDEX IF EXISTS public.idx_devoluciones_fecha;
DROP INDEX IF EXISTS public.idx_devoluciones_empresa;
DROP INDEX IF EXISTS public.idx_devoluciones_detalle_venta_item;
DROP INDEX IF EXISTS public.idx_devoluciones_detalle_producto;
DROP INDEX IF EXISTS public.idx_devoluciones_detalle_devolucion;
DROP INDEX IF EXISTS public.idx_devolucion_pagos_devolucion;
DROP INDEX IF EXISTS public.idx_devolucion_motivos_empresa;
DROP INDEX IF EXISTS public.idx_cierres_inv_turno;
DROP INDEX IF EXISTS public.idx_cierres_inv_items_producto;
DROP INDEX IF EXISTS public.idx_cierres_inv_items_cierre;
DROP INDEX IF EXISTS public.idx_cierres_inv_fecha;
DROP INDEX IF EXISTS public.idx_cierres_inv_empresa;
DROP INDEX IF EXISTS public.idx_cierres_inv_almacen;
DROP INDEX IF EXISTS public.entrada_pagos_entrada_id_fecha_index;
DROP INDEX IF EXISTS public.deudas_empresa_id_direccion_estado_index;
DROP INDEX IF EXISTS public.deuda_pagos_deuda_id_fecha_index;
DROP INDEX IF EXISTS public.cuenta_movimientos_ref_tipo_ref_id_index;
DROP INDEX IF EXISTS public.cuenta_movimientos_empresa_id_cuenta_id_fecha_index;
DROP INDEX IF EXISTS public.clientes_un_general_por_empresa;
DROP INDEX IF EXISTS public.cliente_anticipos_empresa_id_estado_index;
DROP INDEX IF EXISTS public.citas_venta_id_index;
DROP INDEX IF EXISTS public.citas_profesional_id_fecha_hora_index;
DROP INDEX IF EXISTS public.citas_numero_empresa_unique;
DROP INDEX IF EXISTS public.citas_local_id_fecha_hora_index;
DROP INDEX IF EXISTS public.citas_empresa_id_fecha_hora_index;
DROP INDEX IF EXISTS public.citas_empresa_id_estado_index;
DROP INDEX IF EXISTS public.citas_cliente_id_fecha_hora_index;
DROP INDEX IF EXISTS public.cita_items_producto_unidad_id_index;
DROP INDEX IF EXISTS public.cita_items_producto_id_index;
DROP INDEX IF EXISTS public.cita_items_cita_id_index;
DROP INDEX IF EXISTS public.cache_locks_expiration_index;
DROP INDEX IF EXISTS public.cache_expiration_index;
DROP INDEX IF EXISTS public.balance_diario_items_balance_diario_id_seccion_index;
DROP INDEX IF EXISTS public.auditoria_user_id_index;
DROP INDEX IF EXISTS public.auditoria_modelo_tipo_modelo_id_index;
DROP INDEX IF EXISTS public.auditoria_empresa_id_index;
DROP INDEX IF EXISTS public.auditoria_empresa_id_created_at_index;
DROP INDEX IF EXISTS public.auditoria_empresa_id_accion_index;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_turno_id_numero_unique;
ALTER TABLE IF EXISTS ONLY public.ventas DROP CONSTRAINT IF EXISTS ventas_pkey;
ALTER TABLE IF EXISTS ONLY public.venta_pagos DROP CONSTRAINT IF EXISTS venta_pagos_pkey;
ALTER TABLE IF EXISTS ONLY public.venta_items DROP CONSTRAINT IF EXISTS venta_items_pkey;
ALTER TABLE IF EXISTS ONLY public.venta_abonos DROP CONSTRAINT IF EXISTS venta_abonos_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_unique;
ALTER TABLE IF EXISTS ONLY public.unidades_medida DROP CONSTRAINT IF EXISTS unidades_medida_pkey;
ALTER TABLE IF EXISTS ONLY public.unidades_medida DROP CONSTRAINT IF EXISTS unidades_medida_empresa_id_abreviatura_key;
ALTER TABLE IF EXISTS ONLY public.turnos DROP CONSTRAINT IF EXISTS turnos_pkey;
ALTER TABLE IF EXISTS ONLY public.turno_consolidaciones DROP CONSTRAINT IF EXISTS turno_consolidaciones_turno_id_unique;
ALTER TABLE IF EXISTS ONLY public.turno_consolidaciones DROP CONSTRAINT IF EXISTS turno_consolidaciones_pkey;
ALTER TABLE IF EXISTS ONLY public.turno_consolidacion_items DROP CONSTRAINT IF EXISTS turno_consolidacion_items_pkey;
ALTER TABLE IF EXISTS ONLY public.turno_cierre_productos DROP CONSTRAINT IF EXISTS turno_cierre_productos_turno_id_producto_id_unique;
ALTER TABLE IF EXISTS ONLY public.turno_cierre_productos DROP CONSTRAINT IF EXISTS turno_cierre_productos_pkey;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo DROP CONSTRAINT IF EXISTS turno_arqueo_turno_id_denominacion_unique;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo DROP CONSTRAINT IF EXISTS turno_arqueo_pkey;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo_metodos DROP CONSTRAINT IF EXISTS turno_arqueo_metodos_turno_id_metodo_pago_id_unique;
ALTER TABLE IF EXISTS ONLY public.turno_arqueo_metodos DROP CONSTRAINT IF EXISTS turno_arqueo_metodos_pkey;
ALTER TABLE IF EXISTS ONLY public.transferencias DROP CONSTRAINT IF EXISTS transferencias_pkey;
ALTER TABLE IF EXISTS ONLY public.transferencias_detalle DROP CONSTRAINT IF EXISTS transferencias_detalle_pkey;
ALTER TABLE IF EXISTS ONLY public.tipos_metodo_pago DROP CONSTRAINT IF EXISTS tipos_metodo_pago_slug_unique;
ALTER TABLE IF EXISTS ONLY public.tipos_metodo_pago DROP CONSTRAINT IF EXISTS tipos_metodo_pago_pkey;
ALTER TABLE IF EXISTS ONLY public.tipos_cambio DROP CONSTRAINT IF EXISTS tipos_cambio_pkey;
ALTER TABLE IF EXISTS ONLY public.stock DROP CONSTRAINT IF EXISTS stock_pkey;
ALTER TABLE IF EXISTS ONLY public.stock DROP CONSTRAINT IF EXISTS stock_almacen_id_producto_id_key;
ALTER TABLE IF EXISTS ONLY public.sessions DROP CONSTRAINT IF EXISTS sessions_pkey;
ALTER TABLE IF EXISTS ONLY public.salidas DROP CONSTRAINT IF EXISTS salidas_pkey;
ALTER TABLE IF EXISTS ONLY public.salidas_detalle DROP CONSTRAINT IF EXISTS salidas_detalle_pkey;
ALTER TABLE IF EXISTS ONLY public.salida_tipos DROP CONSTRAINT IF EXISTS salida_tipos_pkey;
ALTER TABLE IF EXISTS ONLY public.salida_tipos DROP CONSTRAINT IF EXISTS salida_tipos_empresa_slug_unique;
ALTER TABLE IF EXISTS ONLY public.salida_tipos DROP CONSTRAINT IF EXISTS salida_tipos_empresa_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.roles DROP CONSTRAINT IF EXISTS roles_pkey;
ALTER TABLE IF EXISTS ONLY public.proveedores DROP CONSTRAINT IF EXISTS proveedores_pkey;
ALTER TABLE IF EXISTS ONLY public.proveedores DROP CONSTRAINT IF EXISTS proveedores_empresa_documento_unique;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelantos DROP CONSTRAINT IF EXISTS proveedor_adelantos_pkey;
ALTER TABLE IF EXISTS ONLY public.proveedor_adelanto_aplicaciones DROP CONSTRAINT IF EXISTS proveedor_adelanto_aplicaciones_pkey;
ALTER TABLE IF EXISTS ONLY public.productos DROP CONSTRAINT IF EXISTS productos_pkey;
ALTER TABLE IF EXISTS ONLY public.productos DROP CONSTRAINT IF EXISTS productos_empresa_id_codigo_key;
ALTER TABLE IF EXISTS ONLY public.producto_unidades DROP CONSTRAINT IF EXISTS producto_unidades_producto_id_unidad_medida_id_key;
ALTER TABLE IF EXISTS ONLY public.producto_unidades DROP CONSTRAINT IF EXISTS producto_unidades_pkey;
ALTER TABLE IF EXISTS ONLY public.planilla_descuentos DROP CONSTRAINT IF EXISTS planilla_descuentos_pkey;
ALTER TABLE IF EXISTS ONLY public.permisos DROP CONSTRAINT IF EXISTS permisos_rol_id_modulo_id_unique;
ALTER TABLE IF EXISTS ONLY public.permisos DROP CONSTRAINT IF EXISTS permisos_pkey;
ALTER TABLE IF EXISTS ONLY public.password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.modulos DROP CONSTRAINT IF EXISTS modulos_slug_unique;
ALTER TABLE IF EXISTS ONLY public.modulos DROP CONSTRAINT IF EXISTS modulos_pkey;
ALTER TABLE IF EXISTS ONLY public.migrations DROP CONSTRAINT IF EXISTS migrations_pkey;
ALTER TABLE IF EXISTS ONLY public.metodos_pago DROP CONSTRAINT IF EXISTS metodos_pago_pkey;
ALTER TABLE IF EXISTS ONLY public.metodos_pago DROP CONSTRAINT IF EXISTS metodos_pago_empresa_id_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.locales DROP CONSTRAINT IF EXISTS locales_pkey;
ALTER TABLE IF EXISTS ONLY public.jobs DROP CONSTRAINT IF EXISTS jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.job_batches DROP CONSTRAINT IF EXISTS job_batches_pkey;
ALTER TABLE IF EXISTS ONLY public.gastos DROP CONSTRAINT IF EXISTS gastos_pkey;
ALTER TABLE IF EXISTS ONLY public.gasto_tipos DROP CONSTRAINT IF EXISTS gasto_tipos_pkey;
ALTER TABLE IF EXISTS ONLY public.gasto_tipos DROP CONSTRAINT IF EXISTS gasto_tipos_empresa_id_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.gasto_conceptos DROP CONSTRAINT IF EXISTS gasto_conceptos_pkey;
ALTER TABLE IF EXISTS ONLY public.gasto_conceptos DROP CONSTRAINT IF EXISTS gasto_conceptos_empresa_id_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_uuid_unique;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.entradas DROP CONSTRAINT IF EXISTS entradas_pkey;
ALTER TABLE IF EXISTS ONLY public.entradas_detalle DROP CONSTRAINT IF EXISTS entradas_detalle_pkey;
ALTER TABLE IF EXISTS ONLY public.entrada_pagos DROP CONSTRAINT IF EXISTS entrada_pagos_pkey;
ALTER TABLE IF EXISTS ONLY public.empresas DROP CONSTRAINT IF EXISTS empresas_ruc_unique;
ALTER TABLE IF EXISTS ONLY public.empresas DROP CONSTRAINT IF EXISTS empresas_pkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones DROP CONSTRAINT IF EXISTS devoluciones_pkey;
ALTER TABLE IF EXISTS ONLY public.devoluciones_detalle DROP CONSTRAINT IF EXISTS devoluciones_detalle_pkey;
ALTER TABLE IF EXISTS ONLY public.devolucion_pagos DROP CONSTRAINT IF EXISTS devolucion_pagos_pkey;
ALTER TABLE IF EXISTS ONLY public.devolucion_motivos DROP CONSTRAINT IF EXISTS devolucion_motivos_pkey;
ALTER TABLE IF EXISTS ONLY public.devolucion_motivos DROP CONSTRAINT IF EXISTS devolucion_motivos_empresa_slug_unique;
ALTER TABLE IF EXISTS ONLY public.devolucion_motivos DROP CONSTRAINT IF EXISTS devolucion_motivos_empresa_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.deudas DROP CONSTRAINT IF EXISTS deudas_pkey;
ALTER TABLE IF EXISTS ONLY public.deuda_pagos DROP CONSTRAINT IF EXISTS deuda_pagos_pkey;
ALTER TABLE IF EXISTS ONLY public.descuentos_log DROP CONSTRAINT IF EXISTS descuentos_log_pkey;
ALTER TABLE IF EXISTS ONLY public.descuento_conceptos DROP CONSTRAINT IF EXISTS descuento_conceptos_pkey;
ALTER TABLE IF EXISTS ONLY public.descuento_conceptos DROP CONSTRAINT IF EXISTS descuento_conceptos_empresa_id_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.cuentas DROP CONSTRAINT IF EXISTS cuentas_pkey;
ALTER TABLE IF EXISTS ONLY public.cuenta_movimientos DROP CONSTRAINT IF EXISTS cuenta_movimientos_pkey;
ALTER TABLE IF EXISTS ONLY public.cuenta_metodo_pago DROP CONSTRAINT IF EXISTS cuenta_metodo_pago_unique;
ALTER TABLE IF EXISTS ONLY public.cuenta_metodo_pago DROP CONSTRAINT IF EXISTS cuenta_metodo_pago_pkey;
ALTER TABLE IF EXISTS ONLY public.clientes DROP CONSTRAINT IF EXISTS clientes_pkey;
ALTER TABLE IF EXISTS ONLY public.clientes DROP CONSTRAINT IF EXISTS clientes_empresa_id_numero_documento_unique;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipos DROP CONSTRAINT IF EXISTS cliente_anticipos_pkey;
ALTER TABLE IF EXISTS ONLY public.cliente_anticipo_aplicaciones DROP CONSTRAINT IF EXISTS cliente_anticipo_aplicaciones_pkey;
ALTER TABLE IF EXISTS ONLY public.citas DROP CONSTRAINT IF EXISTS citas_pkey;
ALTER TABLE IF EXISTS ONLY public.cita_items DROP CONSTRAINT IF EXISTS cita_items_pkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_pkey;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario_items DROP CONSTRAINT IF EXISTS cierres_inventario_items_unique;
ALTER TABLE IF EXISTS ONLY public.cierres_inventario_items DROP CONSTRAINT IF EXISTS cierres_inventario_items_pkey;
ALTER TABLE IF EXISTS ONLY public.categorias DROP CONSTRAINT IF EXISTS categorias_pkey;
ALTER TABLE IF EXISTS ONLY public.categorias DROP CONSTRAINT IF EXISTS categorias_empresa_id_nombre_key;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_pkey;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_local_id_nombre_unique;
ALTER TABLE IF EXISTS ONLY public.cache DROP CONSTRAINT IF EXISTS cache_pkey;
ALTER TABLE IF EXISTS ONLY public.cache_locks DROP CONSTRAINT IF EXISTS cache_locks_pkey;
ALTER TABLE IF EXISTS ONLY public.balances_diarios DROP CONSTRAINT IF EXISTS balances_diarios_pkey;
ALTER TABLE IF EXISTS ONLY public.balances_diarios DROP CONSTRAINT IF EXISTS balances_diarios_empresa_id_fecha_unique;
ALTER TABLE IF EXISTS ONLY public.balance_diario_items DROP CONSTRAINT IF EXISTS balance_diario_items_pkey;
ALTER TABLE IF EXISTS ONLY public.auditoria DROP CONSTRAINT IF EXISTS auditoria_pkey;
ALTER TABLE IF EXISTS ONLY public.almacenes DROP CONSTRAINT IF EXISTS almacenes_pkey;
ALTER TABLE IF EXISTS public.ventas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.venta_pagos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.venta_items ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.venta_abonos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.unidades_medida ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turnos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turno_consolidaciones ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turno_consolidacion_items ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turno_cierre_productos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turno_arqueo_metodos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turno_arqueo ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.transferencias_detalle ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.transferencias ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.tipos_metodo_pago ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.tipos_cambio ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.stock ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.salidas_detalle ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.salidas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.salida_tipos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.roles ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.proveedores ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.proveedor_adelantos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.proveedor_adelanto_aplicaciones ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.productos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.producto_unidades ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.planilla_descuentos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.permisos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.modulos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.migrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.metodos_pago ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.locales ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.gastos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.gasto_tipos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.gasto_conceptos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.failed_jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.entradas_detalle ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.entradas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.entrada_pagos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.empresas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.devoluciones_detalle ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.devoluciones ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.devolucion_pagos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.devolucion_motivos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.deudas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.deuda_pagos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.descuentos_log ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.descuento_conceptos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cuentas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cuenta_movimientos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cuenta_metodo_pago ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.clientes ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cliente_anticipos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cliente_anticipo_aplicaciones ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.citas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cita_items ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cierres_inventario_items ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cierres_inventario ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.categorias ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cajas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.balances_diarios ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.balance_diario_items ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.auditoria ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.almacenes ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.ventas_id_seq;
DROP TABLE IF EXISTS public.ventas;
DROP SEQUENCE IF EXISTS public.venta_pagos_id_seq;
DROP TABLE IF EXISTS public.venta_pagos;
DROP SEQUENCE IF EXISTS public.venta_items_id_seq;
DROP TABLE IF EXISTS public.venta_items;
DROP SEQUENCE IF EXISTS public.venta_abonos_id_seq;
DROP TABLE IF EXISTS public.venta_abonos;
DROP SEQUENCE IF EXISTS public.users_id_seq;
DROP TABLE IF EXISTS public.users;
DROP SEQUENCE IF EXISTS public.unidades_medida_id_seq;
DROP TABLE IF EXISTS public.unidades_medida;
DROP SEQUENCE IF EXISTS public.turnos_id_seq;
DROP TABLE IF EXISTS public.turnos;
DROP SEQUENCE IF EXISTS public.turno_consolidaciones_id_seq;
DROP TABLE IF EXISTS public.turno_consolidaciones;
DROP SEQUENCE IF EXISTS public.turno_consolidacion_items_id_seq;
DROP TABLE IF EXISTS public.turno_consolidacion_items;
DROP SEQUENCE IF EXISTS public.turno_cierre_productos_id_seq;
DROP TABLE IF EXISTS public.turno_cierre_productos;
DROP SEQUENCE IF EXISTS public.turno_arqueo_metodos_id_seq;
DROP TABLE IF EXISTS public.turno_arqueo_metodos;
DROP SEQUENCE IF EXISTS public.turno_arqueo_id_seq;
DROP TABLE IF EXISTS public.turno_arqueo;
DROP SEQUENCE IF EXISTS public.transferencias_id_seq;
DROP SEQUENCE IF EXISTS public.transferencias_detalle_id_seq;
DROP TABLE IF EXISTS public.transferencias_detalle;
DROP TABLE IF EXISTS public.transferencias;
DROP SEQUENCE IF EXISTS public.tipos_metodo_pago_id_seq;
DROP TABLE IF EXISTS public.tipos_metodo_pago;
DROP SEQUENCE IF EXISTS public.tipos_cambio_id_seq;
DROP TABLE IF EXISTS public.tipos_cambio;
DROP SEQUENCE IF EXISTS public.stock_id_seq;
DROP TABLE IF EXISTS public.stock;
DROP TABLE IF EXISTS public.sessions;
DROP SEQUENCE IF EXISTS public.salidas_id_seq;
DROP SEQUENCE IF EXISTS public.salidas_detalle_id_seq;
DROP TABLE IF EXISTS public.salidas_detalle;
DROP TABLE IF EXISTS public.salidas;
DROP SEQUENCE IF EXISTS public.salida_tipos_id_seq;
DROP TABLE IF EXISTS public.salida_tipos;
DROP SEQUENCE IF EXISTS public.roles_id_seq;
DROP TABLE IF EXISTS public.roles;
DROP SEQUENCE IF EXISTS public.proveedores_id_seq;
DROP TABLE IF EXISTS public.proveedores;
DROP SEQUENCE IF EXISTS public.proveedor_adelantos_id_seq;
DROP TABLE IF EXISTS public.proveedor_adelantos;
DROP SEQUENCE IF EXISTS public.proveedor_adelanto_aplicaciones_id_seq;
DROP TABLE IF EXISTS public.proveedor_adelanto_aplicaciones;
DROP SEQUENCE IF EXISTS public.productos_id_seq;
DROP TABLE IF EXISTS public.productos;
DROP SEQUENCE IF EXISTS public.producto_unidades_id_seq;
DROP TABLE IF EXISTS public.producto_unidades;
DROP SEQUENCE IF EXISTS public.planilla_descuentos_id_seq;
DROP TABLE IF EXISTS public.planilla_descuentos;
DROP SEQUENCE IF EXISTS public.permisos_id_seq;
DROP TABLE IF EXISTS public.permisos;
DROP TABLE IF EXISTS public.password_reset_tokens;
DROP SEQUENCE IF EXISTS public.modulos_id_seq;
DROP TABLE IF EXISTS public.modulos;
DROP SEQUENCE IF EXISTS public.migrations_id_seq;
DROP TABLE IF EXISTS public.migrations;
DROP SEQUENCE IF EXISTS public.metodos_pago_id_seq;
DROP TABLE IF EXISTS public.metodos_pago;
DROP SEQUENCE IF EXISTS public.locales_id_seq;
DROP TABLE IF EXISTS public.locales;
DROP SEQUENCE IF EXISTS public.jobs_id_seq;
DROP TABLE IF EXISTS public.jobs;
DROP TABLE IF EXISTS public.job_batches;
DROP SEQUENCE IF EXISTS public.gastos_id_seq;
DROP TABLE IF EXISTS public.gastos;
DROP SEQUENCE IF EXISTS public.gasto_tipos_id_seq;
DROP TABLE IF EXISTS public.gasto_tipos;
DROP SEQUENCE IF EXISTS public.gasto_conceptos_id_seq;
DROP TABLE IF EXISTS public.gasto_conceptos;
DROP SEQUENCE IF EXISTS public.failed_jobs_id_seq;
DROP TABLE IF EXISTS public.failed_jobs;
DROP SEQUENCE IF EXISTS public.entradas_id_seq;
DROP SEQUENCE IF EXISTS public.entradas_detalle_id_seq;
DROP TABLE IF EXISTS public.entradas_detalle;
DROP TABLE IF EXISTS public.entradas;
DROP SEQUENCE IF EXISTS public.entrada_pagos_id_seq;
DROP TABLE IF EXISTS public.entrada_pagos;
DROP SEQUENCE IF EXISTS public.empresas_id_seq;
DROP TABLE IF EXISTS public.empresas;
DROP SEQUENCE IF EXISTS public.devoluciones_id_seq;
DROP SEQUENCE IF EXISTS public.devoluciones_detalle_id_seq;
DROP TABLE IF EXISTS public.devoluciones_detalle;
DROP TABLE IF EXISTS public.devoluciones;
DROP SEQUENCE IF EXISTS public.devolucion_pagos_id_seq;
DROP TABLE IF EXISTS public.devolucion_pagos;
DROP SEQUENCE IF EXISTS public.devolucion_motivos_id_seq;
DROP TABLE IF EXISTS public.devolucion_motivos;
DROP SEQUENCE IF EXISTS public.deudas_id_seq;
DROP TABLE IF EXISTS public.deudas;
DROP SEQUENCE IF EXISTS public.deuda_pagos_id_seq;
DROP TABLE IF EXISTS public.deuda_pagos;
DROP SEQUENCE IF EXISTS public.descuentos_log_id_seq;
DROP TABLE IF EXISTS public.descuentos_log;
DROP SEQUENCE IF EXISTS public.descuento_conceptos_id_seq;
DROP TABLE IF EXISTS public.descuento_conceptos;
DROP SEQUENCE IF EXISTS public.cuentas_id_seq;
DROP TABLE IF EXISTS public.cuentas;
DROP SEQUENCE IF EXISTS public.cuenta_movimientos_id_seq;
DROP TABLE IF EXISTS public.cuenta_movimientos;
DROP SEQUENCE IF EXISTS public.cuenta_metodo_pago_id_seq;
DROP TABLE IF EXISTS public.cuenta_metodo_pago;
DROP SEQUENCE IF EXISTS public.clientes_id_seq;
DROP TABLE IF EXISTS public.clientes;
DROP SEQUENCE IF EXISTS public.cliente_anticipos_id_seq;
DROP TABLE IF EXISTS public.cliente_anticipos;
DROP SEQUENCE IF EXISTS public.cliente_anticipo_aplicaciones_id_seq;
DROP TABLE IF EXISTS public.cliente_anticipo_aplicaciones;
DROP SEQUENCE IF EXISTS public.citas_id_seq;
DROP TABLE IF EXISTS public.citas;
DROP SEQUENCE IF EXISTS public.cita_items_id_seq;
DROP TABLE IF EXISTS public.cita_items;
DROP SEQUENCE IF EXISTS public.cierres_inventario_items_id_seq;
DROP TABLE IF EXISTS public.cierres_inventario_items;
DROP SEQUENCE IF EXISTS public.cierres_inventario_id_seq;
DROP TABLE IF EXISTS public.cierres_inventario;
DROP SEQUENCE IF EXISTS public.categorias_id_seq;
DROP TABLE IF EXISTS public.categorias;
DROP SEQUENCE IF EXISTS public.cajas_id_seq;
DROP TABLE IF EXISTS public.cajas;
DROP TABLE IF EXISTS public.cache_locks;
DROP TABLE IF EXISTS public.cache;
DROP SEQUENCE IF EXISTS public.balances_diarios_id_seq;
DROP TABLE IF EXISTS public.balances_diarios;
DROP SEQUENCE IF EXISTS public.balance_diario_items_id_seq;
DROP TABLE IF EXISTS public.balance_diario_items;
DROP SEQUENCE IF EXISTS public.auditoria_id_seq;
DROP TABLE IF EXISTS public.auditoria;
DROP SEQUENCE IF EXISTS public.almacenes_id_seq;
DROP TABLE IF EXISTS public.almacenes;
DROP TYPE IF EXISTS public.tipo_precio_enum;
DROP TYPE IF EXISTS public.tipo_item;
DROP TYPE IF EXISTS public.tipo_entrada_enum;
DROP TYPE IF EXISTS public.tipo_documento_enum;
DROP TYPE IF EXISTS public.estado_entrada_enum;
--
-- Name: estado_entrada_enum; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.estado_entrada_enum AS ENUM (
    'borrador',
    'confirmado'
);


--
-- Name: tipo_documento_enum; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tipo_documento_enum AS ENUM (
    'DNI',
    'RUC',
    'CE',
    'pasaporte',
    'otro'
);


--
-- Name: tipo_entrada_enum; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tipo_entrada_enum AS ENUM (
    'compra',
    'ajuste',
    'devolucion',
    'otro'
);


--
-- Name: tipo_item; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tipo_item AS ENUM (
    'producto',
    'servicio'
);


--
-- Name: tipo_precio_enum; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tipo_precio_enum AS ENUM (
    'fijo',
    'referencial'
);


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: almacenes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.almacenes (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint,
    nombre character varying(100) NOT NULL,
    tipo character varying(20) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT almacenes_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('central'::character varying)::text, ('local'::character varying)::text]))),
    CONSTRAINT chk_tipo_local CHECK (((((tipo)::text = 'local'::text) AND (local_id IS NOT NULL)) OR (((tipo)::text = 'central'::text) AND (local_id IS NULL))))
);


--
-- Name: almacenes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.almacenes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: almacenes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.almacenes_id_seq OWNED BY public.almacenes.id;


--
-- Name: auditoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditoria (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    user_id bigint,
    user_name character varying(150) NOT NULL,
    accion character varying(80) NOT NULL,
    modelo_tipo character varying(150),
    modelo_id bigint,
    contexto jsonb,
    ip character varying(45),
    user_agent character varying(500),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: auditoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditoria_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditoria_id_seq OWNED BY public.auditoria.id;


--
-- Name: balance_diario_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.balance_diario_items (
    id bigint NOT NULL,
    balance_diario_id bigint NOT NULL,
    seccion character varying(10) NOT NULL,
    categoria character varying(30) NOT NULL,
    descripcion character varying(250) NOT NULL,
    ref_tipo character varying(40),
    ref_id bigint,
    monto numeric(14,2) NOT NULL,
    es_manual boolean DEFAULT false NOT NULL,
    conciliado boolean DEFAULT false NOT NULL,
    orden smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: balance_diario_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.balance_diario_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: balance_diario_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.balance_diario_items_id_seq OWNED BY public.balance_diario_items.id;


--
-- Name: balances_diarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.balances_diarios (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    total_favor numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    total_contra numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    balance_neto numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    balance_anterior numeric(14,2),
    diferencia numeric(14,2),
    gastos_dia numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    utilidad_real numeric(14,2),
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: balances_diarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.balances_diarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: balances_diarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.balances_diarios_id_seq OWNED BY public.balances_diarios.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cajas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cajas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    caja_chica_activa boolean DEFAULT false NOT NULL,
    caja_chica_monto_sugerido numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    caja_chica_en_arqueo boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cajas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cajas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cajas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cajas_id_seq OWNED BY public.cajas.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion character varying(255),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: cierres_inventario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cierres_inventario (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    almacen_id bigint NOT NULL,
    user_id bigint NOT NULL,
    turno_id bigint,
    fecha date NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    observacion text,
    total_items integer DEFAULT 0 NOT NULL,
    total_diferencias integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT cierres_inventario_estado_check CHECK (((estado)::text = ANY (ARRAY[('borrador'::character varying)::text, ('confirmado'::character varying)::text, ('anulado'::character varying)::text])))
);


--
-- Name: cierres_inventario_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cierres_inventario_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cierres_inventario_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cierres_inventario_id_seq OWNED BY public.cierres_inventario.id;


--
-- Name: cierres_inventario_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cierres_inventario_items (
    id bigint NOT NULL,
    cierre_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    stock_sistema numeric(14,4) DEFAULT 0 NOT NULL,
    stock_declarado numeric(14,4) DEFAULT 0 NOT NULL,
    diferencia numeric(14,4) DEFAULT 0 NOT NULL,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cierres_inventario_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cierres_inventario_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cierres_inventario_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cierres_inventario_items_id_seq OWNED BY public.cierres_inventario_items.id;


--
-- Name: cita_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cita_items (
    id bigint NOT NULL,
    cita_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    producto_unidad_id bigint NOT NULL,
    cantidad numeric(12,4) DEFAULT '1'::numeric NOT NULL,
    duracion_min smallint DEFAULT '30'::smallint NOT NULL,
    precio_estimado numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    observaciones text,
    orden smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cita_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cita_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cita_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cita_items_id_seq OWNED BY public.cita_items.id;


--
-- Name: citas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.citas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint NOT NULL,
    cliente_id bigint NOT NULL,
    profesional_id bigint,
    created_by bigint,
    numero character varying(30),
    fecha_hora timestamp(0) without time zone NOT NULL,
    duracion_min smallint DEFAULT '30'::smallint NOT NULL,
    estado character varying(20) DEFAULT 'programada'::character varying NOT NULL,
    observaciones text,
    sujeto_nombre character varying(150),
    sujeto_descripcion text,
    venta_id bigint,
    confirmada_at timestamp(0) without time zone,
    iniciada_at timestamp(0) without time zone,
    completada_at timestamp(0) without time zone,
    cancelada_at timestamp(0) without time zone,
    motivo_cancelacion text,
    recordatorio_enviado_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT citas_estado_check CHECK (((estado)::text = ANY (ARRAY[('programada'::character varying)::text, ('confirmada'::character varying)::text, ('en_atencion'::character varying)::text, ('completada'::character varying)::text, ('no_asistio'::character varying)::text, ('cancelada'::character varying)::text])))
);


--
-- Name: citas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.citas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: citas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.citas_id_seq OWNED BY public.citas.id;


--
-- Name: cliente_anticipo_aplicaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cliente_anticipo_aplicaciones (
    id bigint NOT NULL,
    cliente_anticipo_id bigint NOT NULL,
    venta_id bigint,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    cantidad numeric(12,4),
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cliente_anticipo_aplicaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cliente_anticipo_aplicaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cliente_anticipo_aplicaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cliente_anticipo_aplicaciones_id_seq OWNED BY public.cliente_anticipo_aplicaciones.id;


--
-- Name: cliente_anticipos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cliente_anticipos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    cliente_id bigint NOT NULL,
    user_id bigint NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    saldo numeric(12,2) NOT NULL,
    tipo_valorizacion character varying(20) DEFAULT 'monto'::character varying NOT NULL,
    producto_id bigint,
    cantidad numeric(12,4),
    cantidad_pendiente numeric(12,4),
    estado character varying(20) DEFAULT 'activo'::character varying NOT NULL,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: cliente_anticipos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cliente_anticipos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cliente_anticipos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cliente_anticipos_id_seq OWNED BY public.cliente_anticipos.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    tipo_documento public.tipo_documento_enum DEFAULT 'DNI'::public.tipo_documento_enum NOT NULL,
    numero_documento character varying(20),
    nombres character varying(100),
    apellidos character varying(100),
    razon_social character varying(200),
    telefono character varying(20),
    email character varying(150),
    direccion character varying(255),
    fecha_nacimiento date,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    es_cliente_general boolean DEFAULT false NOT NULL
);


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: cuenta_metodo_pago; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuenta_metodo_pago (
    id bigint NOT NULL,
    cuenta_id bigint NOT NULL,
    metodo_pago_id bigint NOT NULL
);


--
-- Name: cuenta_metodo_pago_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuenta_metodo_pago_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuenta_metodo_pago_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuenta_metodo_pago_id_seq OWNED BY public.cuenta_metodo_pago.id;


--
-- Name: cuenta_movimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuenta_movimientos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    cuenta_id bigint NOT NULL,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    tipo character varying(10) NOT NULL,
    monto numeric(12,2) NOT NULL,
    descripcion character varying(250) NOT NULL,
    ref_tipo character varying(40),
    ref_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: cuenta_movimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuenta_movimientos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuenta_movimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuenta_movimientos_id_seq OWNED BY public.cuenta_movimientos.id;


--
-- Name: cuentas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    numero_cuenta character varying(100),
    banco character varying(100),
    cci character varying(50),
    titular character varying(150),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    es_efectivo boolean DEFAULT false NOT NULL,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL
);


--
-- Name: cuentas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_id_seq OWNED BY public.cuentas.id;


--
-- Name: descuento_conceptos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.descuento_conceptos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    requiere_aprobacion boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: descuento_conceptos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.descuento_conceptos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: descuento_conceptos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.descuento_conceptos_id_seq OWNED BY public.descuento_conceptos.id;


--
-- Name: descuentos_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.descuentos_log (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    venta_id bigint,
    venta_item_id bigint,
    descuento_concepto_id bigint NOT NULL,
    user_id bigint NOT NULL,
    cliente_id bigint NOT NULL,
    aprobado_por bigint,
    monto_descuento numeric(12,2) NOT NULL,
    requeria_aprobacion boolean DEFAULT false NOT NULL,
    notificacion_enviada boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: descuentos_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.descuentos_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: descuentos_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.descuentos_log_id_seq OWNED BY public.descuentos_log.id;


--
-- Name: deuda_pagos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.deuda_pagos (
    id bigint NOT NULL,
    deuda_id bigint NOT NULL,
    user_id bigint NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    fecha date NOT NULL,
    tipo character varying(20) DEFAULT 'amortizacion'::character varying NOT NULL,
    monto numeric(12,2) NOT NULL,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: deuda_pagos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.deuda_pagos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: deuda_pagos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.deuda_pagos_id_seq OWNED BY public.deuda_pagos.id;


--
-- Name: deudas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.deudas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    user_id bigint NOT NULL,
    direccion character varying(20) NOT NULL,
    tipo character varying(20) DEFAULT 'otro'::character varying NOT NULL,
    nombre character varying(200) NOT NULL,
    monto_original numeric(12,2) NOT NULL,
    saldo numeric(12,2) NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_vencimiento date,
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: deudas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.deudas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: deudas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.deudas_id_seq OWNED BY public.deudas.id;


--
-- Name: devolucion_motivos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devolucion_motivos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(80) NOT NULL,
    slug character varying(40) NOT NULL,
    afecta_restock_default character varying(20) DEFAULT 'permite'::character varying NOT NULL,
    es_sistema boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    orden integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT devolucion_motivos_afecta_restock_check CHECK (((afecta_restock_default)::text = ANY (ARRAY[('permite'::character varying)::text, ('impide'::character varying)::text, ('obliga_merma'::character varying)::text])))
);


--
-- Name: devolucion_motivos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.devolucion_motivos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: devolucion_motivos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.devolucion_motivos_id_seq OWNED BY public.devolucion_motivos.id;


--
-- Name: devolucion_pagos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devolucion_pagos (
    id bigint NOT NULL,
    devolucion_id bigint NOT NULL,
    metodo_pago_id bigint NOT NULL,
    cuenta_metodo_pago_id bigint,
    monto numeric(14,2) NOT NULL,
    referencia character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: devolucion_pagos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.devolucion_pagos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: devolucion_pagos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.devolucion_pagos_id_seq OWNED BY public.devolucion_pagos.id;


--
-- Name: devoluciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devoluciones (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint NOT NULL,
    turno_id bigint,
    caja_id bigint,
    venta_id bigint NOT NULL,
    user_id bigint NOT NULL,
    user_aprobacion_id bigint,
    numero character varying(30),
    fecha timestamp(0) without time zone NOT NULL,
    motivo_id bigint NOT NULL,
    forma_reembolso character varying(30) DEFAULT 'efectivo'::character varying NOT NULL,
    monto_devolucion numeric(14,2) DEFAULT 0 NOT NULL,
    monto_reembolso numeric(14,2) DEFAULT 0 NOT NULL,
    requiere_aprobacion boolean DEFAULT false NOT NULL,
    fue_aprobada boolean DEFAULT false NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    observacion text,
    fecha_aprobacion timestamp(0) without time zone,
    observacion_aprobacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT devoluciones_estado_check CHECK (((estado)::text = ANY (ARRAY[('pendiente'::character varying)::text, ('aprobada'::character varying)::text, ('rechazada'::character varying)::text, ('completada'::character varying)::text, ('anulada'::character varying)::text]))),
    CONSTRAINT devoluciones_forma_reembolso_check CHECK (((forma_reembolso)::text = ANY (ARRAY[('efectivo'::character varying)::text, ('mismo_metodo'::character varying)::text, ('vale_credito'::character varying)::text, ('cambio_producto'::character varying)::text, ('sin_reembolso'::character varying)::text])))
);


--
-- Name: devoluciones_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devoluciones_detalle (
    id bigint NOT NULL,
    devolucion_id bigint NOT NULL,
    venta_item_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    producto_unidad_id bigint,
    cantidad numeric(14,4) NOT NULL,
    cantidad_base numeric(14,4) NOT NULL,
    precio_unitario numeric(14,2) NOT NULL,
    subtotal numeric(14,2) NOT NULL,
    estado_producto character varying(20) DEFAULT 'bueno'::character varying NOT NULL,
    restock boolean DEFAULT true NOT NULL,
    motivo_id bigint,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT devoluciones_detalle_estado_producto_check CHECK (((estado_producto)::text = ANY (ARRAY[('bueno'::character varying)::text, ('defectuoso'::character varying)::text, ('vencido'::character varying)::text, ('dañado'::character varying)::text])))
);


--
-- Name: devoluciones_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.devoluciones_detalle_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: devoluciones_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.devoluciones_detalle_id_seq OWNED BY public.devoluciones_detalle.id;


--
-- Name: devoluciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.devoluciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: devoluciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.devoluciones_id_seq OWNED BY public.devoluciones.id;


--
-- Name: empresas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresas (
    id bigint NOT NULL,
    razon_social character varying(255) NOT NULL,
    nombre_comercial character varying(255),
    ruc character varying(11) NOT NULL,
    direccion character varying(255),
    telefono character varying(255),
    email character varying(255),
    logo character varying(255),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    modo_almacen character varying(20) DEFAULT 'simple'::character varying NOT NULL,
    descuenta_stock_en_venta boolean DEFAULT true NOT NULL,
    modo_cierre_caja character varying(30) DEFAULT 'con_declaraciones'::character varying NOT NULL,
    usa_fondos_iniciales boolean DEFAULT true NOT NULL,
    fondos_iniciales_en_declaracion boolean DEFAULT false NOT NULL,
    modo_cierre_inventario character varying(20) DEFAULT 'por_venta'::character varying NOT NULL,
    permite_devoluciones boolean DEFAULT true NOT NULL,
    dias_max_devolucion integer DEFAULT 0 NOT NULL,
    requiere_aprobacion_devolucion boolean DEFAULT false NOT NULL,
    restock_default boolean DEFAULT true NOT NULL,
    usa_agenda boolean DEFAULT false NOT NULL,
    agenda_sujeto_label character varying(50),
    agenda_sujeto_requerido boolean DEFAULT false NOT NULL,
    tasa_igv numeric(5,2) DEFAULT '18'::numeric NOT NULL,
    requiere_consolidacion_caja boolean DEFAULT false NOT NULL,
    CONSTRAINT empresas_modo_almacen_check CHECK (((modo_almacen)::text = ANY (ARRAY[('simple'::character varying)::text, ('central_y_local'::character varying)::text]))),
    CONSTRAINT empresas_modo_cierre_caja_check CHECK (((modo_cierre_caja)::text = ANY (ARRAY[('rapido'::character varying)::text, ('con_declaraciones'::character varying)::text]))),
    CONSTRAINT empresas_modo_cierre_inventario_check CHECK (((modo_cierre_inventario)::text = ANY (ARRAY[('por_venta'::character varying)::text, ('declarado'::character varying)::text])))
);


--
-- Name: empresas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresas_id_seq OWNED BY public.empresas.id;


--
-- Name: entrada_pagos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entrada_pagos (
    id bigint NOT NULL,
    entrada_id bigint NOT NULL,
    user_id bigint NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    proveedor_adelanto_id bigint,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    referencia character varying(200),
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: entrada_pagos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.entrada_pagos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entrada_pagos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.entrada_pagos_id_seq OWNED BY public.entrada_pagos.id;


--
-- Name: entradas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entradas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    almacen_id bigint NOT NULL,
    user_id bigint NOT NULL,
    numero_documento character varying(50),
    proveedor character varying(150),
    tipo public.tipo_entrada_enum DEFAULT 'compra'::public.tipo_entrada_enum NOT NULL,
    fecha date NOT NULL,
    estado public.estado_entrada_enum DEFAULT 'borrador'::public.estado_entrada_enum NOT NULL,
    observacion text,
    total numeric(12,2) DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    proveedor_id bigint,
    estado_pago character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    monto_pagado numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: entradas_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entradas_detalle (
    id bigint NOT NULL,
    entrada_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    unidad_medida_id bigint NOT NULL,
    cantidad numeric(12,4) NOT NULL,
    factor_conversion numeric(12,4) DEFAULT 1 NOT NULL,
    cantidad_base numeric(12,4) NOT NULL,
    precio_costo numeric(12,4) NOT NULL,
    subtotal numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    numero_documento character varying(50)
);


--
-- Name: entradas_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.entradas_detalle_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entradas_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.entradas_detalle_id_seq OWNED BY public.entradas_detalle.id;


--
-- Name: entradas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.entradas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entradas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.entradas_id_seq OWNED BY public.entradas.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: gasto_conceptos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gasto_conceptos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    gasto_tipo_id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: gasto_conceptos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gasto_conceptos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gasto_conceptos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gasto_conceptos_id_seq OWNED BY public.gasto_conceptos.id;


--
-- Name: gasto_tipos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gasto_tipos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    categoria character varying(255) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT gasto_tipos_categoria_check CHECK (((categoria)::text = ANY (ARRAY[('administrativo'::character varying)::text, ('operativo'::character varying)::text, ('otro'::character varying)::text])))
);


--
-- Name: gasto_tipos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gasto_tipos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gasto_tipos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gasto_tipos_id_seq OWNED BY public.gasto_tipos.id;


--
-- Name: gastos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gastos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint NOT NULL,
    user_id bigint NOT NULL,
    turno_id bigint,
    gasto_tipo_id bigint NOT NULL,
    gasto_concepto_id bigint NOT NULL,
    monto numeric(12,2) NOT NULL,
    fecha date NOT NULL,
    comentario text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    cuenta_id bigint,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: gastos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gastos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gastos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gastos_id_seq OWNED BY public.gastos.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: locales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.locales (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    direccion character varying(255),
    telefono character varying(255),
    es_principal boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    descuenta_stock_en_venta boolean,
    modo_cierre_caja character varying(30),
    usa_fondos_iniciales boolean,
    fondos_iniciales_en_declaracion boolean,
    modo_cierre_inventario character varying(20),
    permite_devoluciones boolean,
    dias_max_devolucion integer,
    requiere_aprobacion_devolucion boolean,
    restock_default boolean,
    CONSTRAINT locales_modo_cierre_caja_check CHECK (((modo_cierre_caja IS NULL) OR ((modo_cierre_caja)::text = ANY (ARRAY[('rapido'::character varying)::text, ('con_declaraciones'::character varying)::text])))),
    CONSTRAINT locales_modo_cierre_inventario_check CHECK (((modo_cierre_inventario IS NULL) OR ((modo_cierre_inventario)::text = ANY (ARRAY[('por_venta'::character varying)::text, ('declarado'::character varying)::text]))))
);


--
-- Name: locales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.locales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: locales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.locales_id_seq OWNED BY public.locales.id;


--
-- Name: metodos_pago; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.metodos_pago (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(80) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    admite_vuelto boolean DEFAULT false NOT NULL,
    tipo_id bigint NOT NULL
);


--
-- Name: metodos_pago_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.metodos_pago_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: metodos_pago_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.metodos_pago_id_seq OWNED BY public.metodos_pago.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: modulos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modulos (
    id bigint NOT NULL,
    padre_id bigint,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    icono character varying(255),
    ruta character varying(255),
    orden integer DEFAULT 0 NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: modulos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modulos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modulos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modulos_id_seq OWNED BY public.modulos.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permisos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permisos (
    id bigint NOT NULL,
    rol_id bigint NOT NULL,
    modulo_id bigint NOT NULL,
    ver boolean DEFAULT false NOT NULL,
    crear boolean DEFAULT false NOT NULL,
    editar boolean DEFAULT false NOT NULL,
    eliminar boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permisos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permisos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permisos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permisos_id_seq OWNED BY public.permisos.id;


--
-- Name: planilla_descuentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.planilla_descuentos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    user_id bigint NOT NULL,
    registrado_por bigint NOT NULL,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    motivo character varying(250) NOT NULL,
    ref_tipo character varying(40),
    ref_id bigint,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    aplicado_por bigint,
    fecha_aplicacion date,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: planilla_descuentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.planilla_descuentos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: planilla_descuentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.planilla_descuentos_id_seq OWNED BY public.planilla_descuentos.id;


--
-- Name: producto_unidades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.producto_unidades (
    id bigint NOT NULL,
    producto_id bigint NOT NULL,
    unidad_medida_id bigint NOT NULL,
    es_base boolean DEFAULT false NOT NULL,
    factor_conversion numeric(12,4) DEFAULT 1.0000 NOT NULL,
    tipo_precio public.tipo_precio_enum DEFAULT 'fijo'::public.tipo_precio_enum NOT NULL,
    precio_venta numeric(12,2) NOT NULL,
    precio_costo numeric(12,2) DEFAULT 0 NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: producto_unidades_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.producto_unidades_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: producto_unidades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.producto_unidades_id_seq OWNED BY public.producto_unidades.id;


--
-- Name: productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.productos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    categoria_id bigint,
    codigo character varying(50),
    nombre character varying(150) NOT NULL,
    descripcion text,
    tipo public.tipo_item NOT NULL,
    tipo_precio public.tipo_precio_enum DEFAULT 'fijo'::public.tipo_precio_enum NOT NULL,
    precio_venta numeric(12,2) NOT NULL,
    precio_costo numeric(12,2) DEFAULT 0 NOT NULL,
    imagen character varying(255),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    incluye_igv boolean DEFAULT false NOT NULL,
    controla_stock boolean,
    es_retornable boolean
);


--
-- Name: productos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.productos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: productos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.productos_id_seq OWNED BY public.productos.id;


--
-- Name: proveedor_adelanto_aplicaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proveedor_adelanto_aplicaciones (
    id bigint NOT NULL,
    proveedor_adelanto_id bigint NOT NULL,
    entrada_id bigint,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proveedor_adelanto_aplicaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proveedor_adelanto_aplicaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedor_adelanto_aplicaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proveedor_adelanto_aplicaciones_id_seq OWNED BY public.proveedor_adelanto_aplicaciones.id;


--
-- Name: proveedor_adelantos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proveedor_adelantos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    proveedor_id bigint NOT NULL,
    user_id bigint NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    saldo numeric(12,2) NOT NULL,
    estado character varying(20) DEFAULT 'activo'::character varying NOT NULL,
    referencia character varying(200),
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: proveedor_adelantos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proveedor_adelantos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedor_adelantos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proveedor_adelantos_id_seq OWNED BY public.proveedor_adelantos.id;


--
-- Name: proveedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proveedores (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    tipo_documento public.tipo_documento_enum DEFAULT 'RUC'::public.tipo_documento_enum NOT NULL,
    numero_documento character varying(20),
    razon_social character varying(200),
    nombre_comercial character varying(200),
    contacto character varying(150),
    telefono character varying(20),
    email character varying(150),
    direccion character varying(255),
    observacion text,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proveedores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proveedores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proveedores_id_seq OWNED BY public.proveedores.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    descripcion character varying(255),
    es_admin boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    max_descuento_porcentaje numeric(5,2)
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: salida_tipos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salida_tipos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(80) NOT NULL,
    slug character varying(40) NOT NULL,
    es_sistema boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    orden integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: salida_tipos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salida_tipos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salida_tipos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salida_tipos_id_seq OWNED BY public.salida_tipos.id;


--
-- Name: salidas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salidas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    almacen_id bigint NOT NULL,
    user_id bigint NOT NULL,
    turno_id bigint,
    salida_tipo_id bigint NOT NULL,
    numero_documento character varying(50),
    fecha date NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    observacion text,
    total numeric(14,2) DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT salidas_estado_check CHECK (((estado)::text = ANY (ARRAY[('borrador'::character varying)::text, ('confirmado'::character varying)::text])))
);


--
-- Name: salidas_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salidas_detalle (
    id bigint NOT NULL,
    salida_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    unidad_medida_id bigint NOT NULL,
    cantidad numeric(14,4) NOT NULL,
    factor_conversion numeric(14,4) DEFAULT 1 NOT NULL,
    cantidad_base numeric(14,4) NOT NULL,
    costo_unitario numeric(14,4) DEFAULT 0 NOT NULL,
    subtotal numeric(14,2) DEFAULT 0 NOT NULL,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: salidas_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salidas_detalle_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salidas_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salidas_detalle_id_seq OWNED BY public.salidas_detalle.id;


--
-- Name: salidas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salidas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salidas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salidas_id_seq OWNED BY public.salidas.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: stock; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock (
    id bigint NOT NULL,
    almacen_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    cantidad numeric(12,4) DEFAULT 0 NOT NULL,
    costo_promedio numeric(12,4) DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_id_seq OWNED BY public.stock.id;


--
-- Name: tipos_cambio; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tipos_cambio (
    id bigint NOT NULL,
    fecha date NOT NULL,
    moneda character(3) DEFAULT 'USD'::bpchar NOT NULL,
    tasa numeric(12,6) NOT NULL,
    fuente character varying(40) DEFAULT 'decolecta_sbs_accounting'::character varying NOT NULL,
    raw jsonb,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tipos_cambio_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_cambio_id_seq OWNED BY public.tipos_cambio.id;


--
-- Name: tipos_metodo_pago; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tipos_metodo_pago (
    id bigint NOT NULL,
    slug character varying(50) NOT NULL,
    nombre character varying(80) NOT NULL,
    icono character varying(50),
    admite_vuelto_default boolean DEFAULT false NOT NULL,
    requiere_referencia boolean DEFAULT false NOT NULL,
    orden smallint DEFAULT '0'::smallint NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tipos_metodo_pago_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tipos_metodo_pago_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_metodo_pago_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_metodo_pago_id_seq OWNED BY public.tipos_metodo_pago.id;


--
-- Name: transferencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transferencias (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    almacen_origen_id bigint NOT NULL,
    almacen_destino_id bigint NOT NULL,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    fecha_envio timestamp(0) without time zone,
    fecha_recepcion timestamp(0) without time zone,
    user_envio_id bigint,
    user_recepcion_id bigint,
    observacion_envio text,
    observacion_recepcion text,
    CONSTRAINT chk_almacenes_distintos CHECK ((almacen_origen_id <> almacen_destino_id)),
    CONSTRAINT transferencias_estado_check CHECK (((estado)::text = ANY (ARRAY[('borrador'::character varying)::text, ('enviada'::character varying)::text, ('recibida'::character varying)::text, ('anulada'::character varying)::text])))
);


--
-- Name: transferencias_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transferencias_detalle (
    id bigint NOT NULL,
    transferencia_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    unidad_medida_id bigint NOT NULL,
    cantidad_enviada numeric(12,4) NOT NULL,
    factor_conversion numeric(12,4) DEFAULT 1 NOT NULL,
    cantidad_base_enviada numeric(12,4) NOT NULL,
    costo_unitario numeric(12,4) DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    cantidad_recibida numeric(12,4),
    cantidad_base_recibida numeric(12,4),
    diferencia_base numeric(12,4),
    observacion text
);


--
-- Name: transferencias_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transferencias_detalle_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transferencias_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transferencias_detalle_id_seq OWNED BY public.transferencias_detalle.id;


--
-- Name: transferencias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transferencias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transferencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transferencias_id_seq OWNED BY public.transferencias.id;


--
-- Name: turno_arqueo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turno_arqueo (
    id bigint NOT NULL,
    turno_id bigint NOT NULL,
    denominacion numeric(8,2) NOT NULL,
    cantidad integer DEFAULT 0 NOT NULL,
    subtotal numeric(12,2) GENERATED ALWAYS AS ((denominacion * (cantidad)::numeric)) STORED NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: turno_arqueo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turno_arqueo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turno_arqueo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turno_arqueo_id_seq OWNED BY public.turno_arqueo.id;


--
-- Name: turno_arqueo_metodos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turno_arqueo_metodos (
    id bigint NOT NULL,
    turno_id bigint NOT NULL,
    metodo_pago_id bigint NOT NULL,
    monto_declarado numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: turno_arqueo_metodos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turno_arqueo_metodos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turno_arqueo_metodos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turno_arqueo_metodos_id_seq OWNED BY public.turno_arqueo_metodos.id;


--
-- Name: turno_cierre_productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turno_cierre_productos (
    id bigint NOT NULL,
    turno_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    producto_nombre character varying(200) NOT NULL,
    cantidad_vendida numeric(12,3) NOT NULL,
    precio_unitario numeric(12,2) NOT NULL,
    total numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    stock_final numeric(14,4)
);


--
-- Name: turno_cierre_productos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turno_cierre_productos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turno_cierre_productos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turno_cierre_productos_id_seq OWNED BY public.turno_cierre_productos.id;


--
-- Name: turno_consolidacion_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turno_consolidacion_items (
    id bigint NOT NULL,
    turno_consolidacion_id bigint NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    etiqueta character varying(100) NOT NULL,
    declarado numeric(12,2),
    esperado numeric(12,2),
    contado numeric(12,2) NOT NULL,
    diferencia numeric(12,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: turno_consolidacion_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turno_consolidacion_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turno_consolidacion_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turno_consolidacion_items_id_seq OWNED BY public.turno_consolidacion_items.id;


--
-- Name: turno_consolidaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turno_consolidaciones (
    id bigint NOT NULL,
    turno_id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    efectivo_declarado numeric(12,2),
    efectivo_esperado numeric(12,2),
    caja_chica numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    efectivo_contado numeric(12,2) NOT NULL,
    diferencia_vs_declarado numeric(12,2),
    diferencia_vs_esperado numeric(12,2),
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: turno_consolidaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turno_consolidaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turno_consolidaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turno_consolidaciones_id_seq OWNED BY public.turno_consolidaciones.id;


--
-- Name: turnos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turnos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint NOT NULL,
    caja_id bigint NOT NULL,
    user_id bigint NOT NULL,
    user_cierre_id bigint,
    monto_apertura numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    monto_caja_chica numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    monto_cierre_declarado numeric(12,2),
    monto_cierre_esperado numeric(12,2),
    diferencia numeric(12,2),
    estado character varying(255) DEFAULT 'abierto'::character varying NOT NULL,
    fecha_apertura timestamp(0) without time zone NOT NULL,
    fecha_cierre timestamp(0) without time zone,
    observacion_apertura text,
    observacion_cierre text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT turnos_estado_check CHECK (((estado)::text = ANY (ARRAY[('abierto'::character varying)::text, ('cerrado'::character varying)::text])))
);


--
-- Name: turnos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turnos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turnos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turnos_id_seq OWNED BY public.turnos.id;


--
-- Name: unidades_medida; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unidades_medida (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    abreviatura character varying(20) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.unidades_medida_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.unidades_medida_id_seq OWNED BY public.unidades_medida.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint,
    rol_id bigint,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: venta_abonos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.venta_abonos (
    id bigint NOT NULL,
    venta_id bigint NOT NULL,
    user_id bigint NOT NULL,
    metodo_pago_id bigint,
    cuenta_id bigint,
    fecha date NOT NULL,
    monto numeric(12,2) NOT NULL,
    referencia character varying(200),
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: venta_abonos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.venta_abonos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: venta_abonos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.venta_abonos_id_seq OWNED BY public.venta_abonos.id;


--
-- Name: venta_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.venta_items (
    id bigint NOT NULL,
    venta_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    producto_unidad_id bigint NOT NULL,
    producto_nombre character varying(150) NOT NULL,
    unidad_nombre character varying(50) NOT NULL,
    cantidad numeric(12,4) NOT NULL,
    factor_conversion numeric(12,4) NOT NULL,
    cantidad_base numeric(12,4) NOT NULL,
    precio_unitario numeric(12,2) NOT NULL,
    precio_original numeric(12,2) NOT NULL,
    descuento_item numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    descuento_concepto_id bigint,
    subtotal numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    incluye_igv boolean DEFAULT false NOT NULL
);


--
-- Name: venta_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.venta_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: venta_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.venta_items_id_seq OWNED BY public.venta_items.id;


--
-- Name: venta_pagos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.venta_pagos (
    id bigint NOT NULL,
    venta_id bigint NOT NULL,
    metodo_pago_id bigint NOT NULL,
    cuenta_metodo_pago_id bigint,
    monto numeric(12,2) NOT NULL,
    referencia character varying(100),
    vuelto numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2)
);


--
-- Name: venta_pagos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.venta_pagos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: venta_pagos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.venta_pagos_id_seq OWNED BY public.venta_pagos.id;


--
-- Name: ventas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ventas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    local_id bigint NOT NULL,
    turno_id bigint NOT NULL,
    caja_id bigint NOT NULL,
    user_id bigint NOT NULL,
    cliente_id bigint NOT NULL,
    numero character varying(20),
    tipo_comprobante character varying(255) DEFAULT 'ticket'::character varying NOT NULL,
    subtotal numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    descuento_total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    descuento_concepto_id bigint,
    igv numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    estado character varying(255) DEFAULT 'completada'::character varying NOT NULL,
    observacion text,
    fecha_venta timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    idempotency_key character varying(100),
    es_credito boolean DEFAULT false NOT NULL,
    monto_pagado numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    saldo_pendiente numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    fecha_vencimiento date,
    moneda character(3) DEFAULT 'PEN'::bpchar NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(14,2),
    CONSTRAINT ventas_estado_check CHECK (((estado)::text = ANY (ARRAY[('completada'::character varying)::text, ('anulada'::character varying)::text]))),
    CONSTRAINT ventas_tipo_comprobante_check CHECK (((tipo_comprobante)::text = ANY (ARRAY[('ticket'::character varying)::text, ('boleta'::character varying)::text, ('factura'::character varying)::text])))
);


--
-- Name: ventas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ventas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ventas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ventas_id_seq OWNED BY public.ventas.id;


--
-- Name: almacenes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes ALTER COLUMN id SET DEFAULT nextval('public.almacenes_id_seq'::regclass);


--
-- Name: auditoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria ALTER COLUMN id SET DEFAULT nextval('public.auditoria_id_seq'::regclass);


--
-- Name: balance_diario_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balance_diario_items ALTER COLUMN id SET DEFAULT nextval('public.balance_diario_items_id_seq'::regclass);


--
-- Name: balances_diarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balances_diarios ALTER COLUMN id SET DEFAULT nextval('public.balances_diarios_id_seq'::regclass);


--
-- Name: cajas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas ALTER COLUMN id SET DEFAULT nextval('public.cajas_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: cierres_inventario id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario ALTER COLUMN id SET DEFAULT nextval('public.cierres_inventario_id_seq'::regclass);


--
-- Name: cierres_inventario_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario_items ALTER COLUMN id SET DEFAULT nextval('public.cierres_inventario_items_id_seq'::regclass);


--
-- Name: cita_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cita_items ALTER COLUMN id SET DEFAULT nextval('public.cita_items_id_seq'::regclass);


--
-- Name: citas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas ALTER COLUMN id SET DEFAULT nextval('public.citas_id_seq'::regclass);


--
-- Name: cliente_anticipo_aplicaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipo_aplicaciones ALTER COLUMN id SET DEFAULT nextval('public.cliente_anticipo_aplicaciones_id_seq'::regclass);


--
-- Name: cliente_anticipos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos ALTER COLUMN id SET DEFAULT nextval('public.cliente_anticipos_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: cuenta_metodo_pago id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_metodo_pago ALTER COLUMN id SET DEFAULT nextval('public.cuenta_metodo_pago_id_seq'::regclass);


--
-- Name: cuenta_movimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_movimientos ALTER COLUMN id SET DEFAULT nextval('public.cuenta_movimientos_id_seq'::regclass);


--
-- Name: cuentas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas ALTER COLUMN id SET DEFAULT nextval('public.cuentas_id_seq'::regclass);


--
-- Name: descuento_conceptos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuento_conceptos ALTER COLUMN id SET DEFAULT nextval('public.descuento_conceptos_id_seq'::regclass);


--
-- Name: descuentos_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log ALTER COLUMN id SET DEFAULT nextval('public.descuentos_log_id_seq'::regclass);


--
-- Name: deuda_pagos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deuda_pagos ALTER COLUMN id SET DEFAULT nextval('public.deuda_pagos_id_seq'::regclass);


--
-- Name: deudas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deudas ALTER COLUMN id SET DEFAULT nextval('public.deudas_id_seq'::regclass);


--
-- Name: devolucion_motivos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_motivos ALTER COLUMN id SET DEFAULT nextval('public.devolucion_motivos_id_seq'::regclass);


--
-- Name: devolucion_pagos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_pagos ALTER COLUMN id SET DEFAULT nextval('public.devolucion_pagos_id_seq'::regclass);


--
-- Name: devoluciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones ALTER COLUMN id SET DEFAULT nextval('public.devoluciones_id_seq'::regclass);


--
-- Name: devoluciones_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle ALTER COLUMN id SET DEFAULT nextval('public.devoluciones_detalle_id_seq'::regclass);


--
-- Name: empresas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas ALTER COLUMN id SET DEFAULT nextval('public.empresas_id_seq'::regclass);


--
-- Name: entrada_pagos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos ALTER COLUMN id SET DEFAULT nextval('public.entrada_pagos_id_seq'::regclass);


--
-- Name: entradas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas ALTER COLUMN id SET DEFAULT nextval('public.entradas_id_seq'::regclass);


--
-- Name: entradas_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas_detalle ALTER COLUMN id SET DEFAULT nextval('public.entradas_detalle_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: gasto_conceptos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_conceptos ALTER COLUMN id SET DEFAULT nextval('public.gasto_conceptos_id_seq'::regclass);


--
-- Name: gasto_tipos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_tipos ALTER COLUMN id SET DEFAULT nextval('public.gasto_tipos_id_seq'::regclass);


--
-- Name: gastos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos ALTER COLUMN id SET DEFAULT nextval('public.gastos_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: locales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locales ALTER COLUMN id SET DEFAULT nextval('public.locales_id_seq'::regclass);


--
-- Name: metodos_pago id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metodos_pago ALTER COLUMN id SET DEFAULT nextval('public.metodos_pago_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: modulos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos ALTER COLUMN id SET DEFAULT nextval('public.modulos_id_seq'::regclass);


--
-- Name: permisos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos ALTER COLUMN id SET DEFAULT nextval('public.permisos_id_seq'::regclass);


--
-- Name: planilla_descuentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planilla_descuentos ALTER COLUMN id SET DEFAULT nextval('public.planilla_descuentos_id_seq'::regclass);


--
-- Name: producto_unidades id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_unidades ALTER COLUMN id SET DEFAULT nextval('public.producto_unidades_id_seq'::regclass);


--
-- Name: productos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos ALTER COLUMN id SET DEFAULT nextval('public.productos_id_seq'::regclass);


--
-- Name: proveedor_adelanto_aplicaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelanto_aplicaciones ALTER COLUMN id SET DEFAULT nextval('public.proveedor_adelanto_aplicaciones_id_seq'::regclass);


--
-- Name: proveedor_adelantos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos ALTER COLUMN id SET DEFAULT nextval('public.proveedor_adelantos_id_seq'::regclass);


--
-- Name: proveedores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores ALTER COLUMN id SET DEFAULT nextval('public.proveedores_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: salida_tipos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salida_tipos ALTER COLUMN id SET DEFAULT nextval('public.salida_tipos_id_seq'::regclass);


--
-- Name: salidas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas ALTER COLUMN id SET DEFAULT nextval('public.salidas_id_seq'::regclass);


--
-- Name: salidas_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas_detalle ALTER COLUMN id SET DEFAULT nextval('public.salidas_detalle_id_seq'::regclass);


--
-- Name: stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock ALTER COLUMN id SET DEFAULT nextval('public.stock_id_seq'::regclass);


--
-- Name: tipos_cambio id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio ALTER COLUMN id SET DEFAULT nextval('public.tipos_cambio_id_seq'::regclass);


--
-- Name: tipos_metodo_pago id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_metodo_pago ALTER COLUMN id SET DEFAULT nextval('public.tipos_metodo_pago_id_seq'::regclass);


--
-- Name: transferencias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias ALTER COLUMN id SET DEFAULT nextval('public.transferencias_id_seq'::regclass);


--
-- Name: transferencias_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_detalle ALTER COLUMN id SET DEFAULT nextval('public.transferencias_detalle_id_seq'::regclass);


--
-- Name: turno_arqueo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo ALTER COLUMN id SET DEFAULT nextval('public.turno_arqueo_id_seq'::regclass);


--
-- Name: turno_arqueo_metodos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo_metodos ALTER COLUMN id SET DEFAULT nextval('public.turno_arqueo_metodos_id_seq'::regclass);


--
-- Name: turno_cierre_productos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_cierre_productos ALTER COLUMN id SET DEFAULT nextval('public.turno_cierre_productos_id_seq'::regclass);


--
-- Name: turno_consolidacion_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidacion_items ALTER COLUMN id SET DEFAULT nextval('public.turno_consolidacion_items_id_seq'::regclass);


--
-- Name: turno_consolidaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidaciones ALTER COLUMN id SET DEFAULT nextval('public.turno_consolidaciones_id_seq'::regclass);


--
-- Name: turnos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos ALTER COLUMN id SET DEFAULT nextval('public.turnos_id_seq'::regclass);


--
-- Name: unidades_medida id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida ALTER COLUMN id SET DEFAULT nextval('public.unidades_medida_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: venta_abonos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_abonos ALTER COLUMN id SET DEFAULT nextval('public.venta_abonos_id_seq'::regclass);


--
-- Name: venta_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_items ALTER COLUMN id SET DEFAULT nextval('public.venta_items_id_seq'::regclass);


--
-- Name: venta_pagos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_pagos ALTER COLUMN id SET DEFAULT nextval('public.venta_pagos_id_seq'::regclass);


--
-- Name: ventas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas ALTER COLUMN id SET DEFAULT nextval('public.ventas_id_seq'::regclass);


--
-- Data for Name: almacenes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.almacenes (id, empresa_id, local_id, nombre, tipo, activo, created_at, updated_at) FROM stdin;
1	1	1	Tienda Chiclayo	local	t	2026-05-18 01:53:39	2026-05-18 01:53:39
826	817	812	Tienda Principal	local	t	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: auditoria; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.auditoria (id, empresa_id, user_id, user_name, accion, modelo_tipo, modelo_id, contexto, ip, user_agent, created_at) FROM stdin;
151	1	1	Jesús	tesoreria.ajuste	App\\Models\\CuentaMovimiento	28	{"cuenta": "Efectivo", "motivo": "DEMO: saldo inicial contado por el dueño", "diferencia": 1290, "saldo_real": 1000, "saldo_previo": -290}	127.0.0.1	Symfony	2026-07-05 15:08:33
152	1	2	Cajera	turno.cerrado	App\\Models\\Turno	203	{"esperado": 350, "declarado": 347, "modo_caja": "con_declaraciones", "diferencia": -3}	127.0.0.1	Symfony	2026-07-05 15:08:34
155	1	1	Jesús	caja.consolidada	App\\Models\\TurnoConsolidacion	5	{"cajera": "Cajera", "lineas": 1, "turno_id": 203, "faltante_total": 10, "genero_descuento": true}	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	2026-07-05 15:54:51
156	1	1	Jesús	cxc.abono	App\\Models\\Venta	145	{"monto": 80, "saldo": 170, "numero": "V-0002"}	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	2026-07-05 19:47:03
157	1	1	Jesús	balance.confirmado	App\\Models\\BalanceDiario	10	{"fecha": "2026-07-04", "balance_neto": 197927.52, "utilidad_real": null}	127.0.0.1	Symfony	2026-07-05 19:19:39
158	1	1	Jesús	balance.confirmado	App\\Models\\BalanceDiario	12	{"fecha": "2026-07-04", "balance_neto": 198002.52, "utilidad_real": null}	127.0.0.1	Symfony	2026-07-05 19:27:37
161	1	1	Jesús	cxc.abono	App\\Models\\Venta	170	{"monto": 168.6, "saldo": 0, "numero": "V-0001"}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:19:19
162	1	1	Jesús	decolecta.dni.consultado	\N	\N	{"dni": "74636171"}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:21:25
163	1	1	Jesús	anticipo_cliente.creado	App\\Models\\ClienteAnticipo	11	{"tipo": "material", "monto": 2000, "cliente_id": "342"}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:22:35
164	1	1	Jesús	anticipo_cliente.aplicado	App\\Models\\ClienteAnticipo	11	{"monto": 2000, "saldo": 0, "cantidad": "100"}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:22:56
165	1	1	Jesús	turno.cerrado	App\\Models\\Turno	213	{"esperado": 200, "declarado": 120, "modo_caja": "con_declaraciones", "diferencia": -80}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:30:00
166	1	1	Jesús	caja.consolidada	App\\Models\\TurnoConsolidacion	6	{"cajera": "Jesús", "lineas": 5, "turno_id": 213, "faltante_total": 50, "genero_descuento": true}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:32:16
167	1	1	Jesús	planilla_descuento.aplicado	App\\Models\\PlanillaDescuento	9	{"monto": 50, "trabajador_id": 1}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	2026-07-05 20:33:14
318	1	1	Jesús	cxc.abono	App\\Models\\Venta	169	{"monto": 700, "saldo": 1140, "numero": "V-0503"}	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	2026-07-09 19:58:41
319	817	960	Administrador H&C	tesoreria.ajuste	App\\Models\\CuentaMovimiento	339	{"cuenta": "Cuenta BCP Soles", "motivo": "Saldo inicial — migración del sistema anterior (Excel 08-07)", "diferencia": 62927.5, "saldo_real": 62927.5, "saldo_previo": 0}	127.0.0.1	Symfony	2026-07-10 14:09:11
320	817	960	Administrador H&C	tesoreria.ajuste	App\\Models\\CuentaMovimiento	340	{"cuenta": "Cuenta BBVA Soles", "motivo": "Saldo inicial — migración del sistema anterior (Excel 08-07)", "diferencia": 16629.52, "saldo_real": 16629.52, "saldo_previo": 0}	127.0.0.1	Symfony	2026-07-10 14:09:11
321	817	960	Administrador H&C	tesoreria.ajuste	App\\Models\\CuentaMovimiento	341	{"cuenta": "Efectivo", "motivo": "Saldo inicial — migración del sistema anterior (Excel 08-07)", "diferencia": 11038.79, "saldo_real": 11038.79, "saldo_previo": 0}	127.0.0.1	Symfony	2026-07-10 14:09:11
322	817	960	Administrador H&C	balance.confirmado	App\\Models\\BalanceDiario	49	{"fecha": "2026-07-08", "balance_neto": 159280.98, "utilidad_real": 1377.85}	127.0.0.1	Symfony	2026-07-10 14:09:11
\.


--
-- Data for Name: balance_diario_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.balance_diario_items (id, balance_diario_id, seccion, categoria, descripcion, ref_tipo, ref_id, monto, es_manual, conciliado, orden, created_at, updated_at) FROM stdin;
1918	14	favor	prestamo_otorgado	Préstamo moto — Carlos Uceda (almacén)	deuda	20	1200.00	f	f	10	2026-07-09 20:38:25	2026-07-09 20:38:25
1919	14	favor	adelanto_proveedor	Adelanto a UYUSTOOLS PERU SAC	proveedor_adelanto	7	4500.00	f	f	11	2026-07-09 20:38:25	2026-07-09 20:38:25
1920	14	favor	adelanto_proveedor	Adelanto a PRODAC SA	proveedor_adelanto	8	2800.00	f	f	12	2026-07-09 20:38:25	2026-07-09 20:38:25
1921	14	favor	planilla_descuento	Por descontar en planilla (faltantes y cargos)	\N	\N	105.50	f	f	13	2026-07-09 20:38:25	2026-07-09 20:38:25
1922	14	contra	cxp	Proveedores por pagar	\N	\N	106900.00	f	f	1	2026-07-09 20:38:25	2026-07-09 20:38:25
1923	14	contra	gastos_emitidos	Gastos emitidos — Efectivo	cuenta	1	715.50	f	f	2	2026-07-09 20:38:25	2026-07-09 20:38:25
1924	14	contra	gastos_emitidos	Gastos emitidos — Cuenta BCP Soles	cuenta	14	35519.50	f	f	3	2026-07-09 20:38:25	2026-07-09 20:38:25
1925	14	contra	gastos_emitidos	Gastos emitidos — Tarjeta	cuenta	4	8000.00	f	f	4	2026-07-09 20:38:25	2026-07-09 20:38:25
1926	14	contra	anticipo_cliente	Clientes anticipos (a precio del día)	\N	\N	38190.00	f	f	5	2026-07-09 20:38:25	2026-07-09 20:38:25
1927	14	contra	deuda	DEUDA BCP 1 - 7630	deuda	15	6173.81	f	f	6	2026-07-09 20:38:25	2026-07-09 20:38:25
1928	14	contra	deuda	DEUDA BCP 2 - 5557	deuda	16	32546.43	f	f	7	2026-07-09 20:38:25	2026-07-09 20:38:25
1929	14	contra	deuda	JORDIN HERRERA	deuda	17	30000.00	f	f	8	2026-07-09 20:38:25	2026-07-09 20:38:25
1930	14	contra	personal	Sueldos pendientes — personal	deuda	18	2000.00	f	f	9	2026-07-09 20:38:25	2026-07-09 20:38:25
1936	43	favor	migracion	JHON ASTONITAS	\N	\N	345.05	t	t	5	2026-07-10 14:09:11	2026-07-10 14:09:11
1937	43	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	112896.61	t	t	6	2026-07-10 14:09:11	2026-07-10 14:09:11
1938	43	contra	migracion	CLIENTES ANTICIPOS	\N	\N	36133.90	t	t	7	2026-07-10 14:09:11	2026-07-10 14:09:11
1939	43	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	8	2026-07-10 14:09:11	2026-07-10 14:09:11
1940	43	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	9	2026-07-10 14:09:11	2026-07-10 14:09:11
1941	43	contra	migracion	PERSONAL	\N	\N	1400.00	t	t	10	2026-07-10 14:09:11	2026-07-10 14:09:11
1942	43	contra	migracion	JORDIN HERRERA	\N	\N	30000.00	t	t	11	2026-07-10 14:09:11	2026-07-10 14:09:11
1943	44	favor	migracion	CUENTA BCP SOLES	\N	\N	161631.96	t	t	0	2026-07-10 14:09:11	2026-07-10 14:09:11
1944	44	favor	migracion	CUENTA BBVA SOLES	\N	\N	7518.43	t	t	1	2026-07-10 14:09:11	2026-07-10 14:09:11
1945	44	favor	migracion	EFECTIVO	\N	\N	8606.11	t	t	2	2026-07-10 14:09:11	2026-07-10 14:09:11
1946	44	favor	migracion	STOCK (INVENTARIO)	\N	\N	234496.21	t	t	3	2026-07-10 14:09:11	2026-07-10 14:09:11
1947	44	favor	migracion	DEUDAS POR COBRAR	\N	\N	83983.42	t	t	4	2026-07-10 14:09:11	2026-07-10 14:09:11
1948	44	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	101280.01	t	t	5	2026-07-10 14:09:11	2026-07-10 14:09:11
1949	44	contra	migracion	CLIENTES ANTICIPOS	\N	\N	34341.90	t	t	6	2026-07-10 14:09:11	2026-07-10 14:09:11
1950	44	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-10 14:09:11	2026-07-10 14:09:11
1951	44	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-10 14:09:11	2026-07-10 14:09:11
1952	44	contra	migracion	PERSONAL	\N	\N	2000.00	t	t	9	2026-07-10 14:09:11	2026-07-10 14:09:11
1953	44	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	132000.00	t	t	10	2026-07-10 14:09:11	2026-07-10 14:09:11
1954	44	contra	migracion	JORDIN HERRERA	\N	\N	30000.00	t	t	11	2026-07-10 14:09:11	2026-07-10 14:09:11
1955	45	favor	migracion	CUENTA BCP SOLES	\N	\N	32920.56	t	t	0	2026-07-10 14:09:11	2026-07-10 14:09:11
1956	45	favor	migracion	CUENTA BBVA SOLES	\N	\N	7814.43	t	t	1	2026-07-10 14:09:11	2026-07-10 14:09:11
1957	45	favor	migracion	EFECTIVO	\N	\N	2866.11	t	t	2	2026-07-10 14:09:11	2026-07-10 14:09:11
1958	45	favor	migracion	STOCK (INVENTARIO)	\N	\N	230624.62	t	t	3	2026-07-10 14:09:11	2026-07-10 14:09:11
1959	45	favor	migracion	DEUDAS POR COBRAR	\N	\N	81367.46	t	t	4	2026-07-10 14:09:11	2026-07-10 14:09:11
1960	45	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	113041.61	t	t	5	2026-07-10 14:09:11	2026-07-10 14:09:11
1961	45	contra	migracion	CLIENTES ANTICIPOS	\N	\N	35275.94	t	t	6	2026-07-10 14:09:11	2026-07-10 14:09:11
1962	45	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-10 14:09:11	2026-07-10 14:09:11
1963	45	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-10 14:09:11	2026-07-10 14:09:11
528	12	favor	efectivo	Efectivo	cuenta	1	12760.11	f	f	1	2026-07-05 19:27:37	2026-07-05 19:27:37
529	12	favor	cuenta_bancaria	Cuenta BBVA Soles	cuenta	15	4693.54	f	f	2	2026-07-05 19:27:37	2026-07-05 19:27:37
530	12	favor	cuenta_bancaria	Cuenta BCP Soles	cuenta	14	104337.86	f	f	3	2026-07-05 19:27:37	2026-07-05 19:27:37
531	12	favor	cuenta_bancaria	Tarjeta	cuenta	4	0.00	f	f	4	2026-07-05 19:27:37	2026-07-05 19:27:37
532	12	favor	cuenta_bancaria	Yape	cuenta	16	7710.00	f	f	5	2026-07-05 19:27:37	2026-07-05 19:27:37
533	12	favor	stock	Stock (inventario valorizado)	\N	\N	226572.00	f	f	6	2026-07-05 19:27:37	2026-07-05 19:27:37
534	12	favor	cxc	Deudas por cobrar (ventas a crédito)	\N	\N	68205.40	f	f	7	2026-07-05 19:27:37	2026-07-05 19:27:37
535	12	favor	prestamo_otorgado	JHON ASTONITAS	deuda	19	345.05	f	f	8	2026-07-05 19:27:37	2026-07-05 19:27:37
536	12	favor	prestamo_otorgado	Préstamo moto — Carlos Uceda (almacén)	deuda	20	1275.00	f	f	9	2026-07-05 19:27:37	2026-07-05 19:27:37
537	12	favor	adelanto_proveedor	Adelanto a UYUSTOOLS PERU SAC	proveedor_adelanto	7	4500.00	f	f	10	2026-07-05 19:27:37	2026-07-05 19:27:37
538	12	favor	adelanto_proveedor	Adelanto a PRODAC SA	proveedor_adelanto	8	2800.00	f	f	11	2026-07-05 19:27:37	2026-07-05 19:27:37
539	12	contra	cxp	Proveedores por pagar	\N	\N	96900.00	f	f	1	2026-07-05 19:27:37	2026-07-05 19:27:37
540	12	contra	gastos_emitidos	Gastos emitidos (salidas de dinero)	\N	\N	33076.20	f	f	2	2026-07-05 19:27:37	2026-07-05 19:27:37
541	12	contra	anticipo_cliente	Clientes anticipos (a precio del día)	\N	\N	34000.00	f	f	3	2026-07-05 19:27:37	2026-07-05 19:27:37
542	12	contra	deuda	DEUDA BCP 1 - 7630	deuda	15	6673.81	f	f	4	2026-07-05 19:27:37	2026-07-05 19:27:37
543	12	contra	deuda	DEUDA BCP 2 - 5557	deuda	16	32546.43	f	f	5	2026-07-05 19:27:37	2026-07-05 19:27:37
544	12	contra	deuda	JORDIN HERRERA	deuda	17	30000.00	f	f	6	2026-07-05 19:27:37	2026-07-05 19:27:37
545	12	contra	personal	Sueldos pendientes — personal	deuda	18	2000.00	f	f	7	2026-07-05 19:27:37	2026-07-05 19:27:37
1931	43	favor	migracion	CUENTA BCP SOLES	\N	\N	44915.86	t	t	0	2026-07-10 14:09:11	2026-07-10 14:09:11
1932	43	favor	migracion	CUENTA BBVA SOLES	\N	\N	4693.54	t	t	1	2026-07-10 14:09:11	2026-07-10 14:09:11
1933	43	favor	migracion	EFECTIVO	\N	\N	11861.21	t	t	2	2026-07-10 14:09:11	2026-07-10 14:09:11
1454	13	favor	efectivo	Efectivo	cuenta	1	14895.11	f	f	1	2026-07-06 15:35:06	2026-07-06 15:35:06
1455	13	favor	cuenta_bancaria	Cuenta BBVA Soles	cuenta	15	4862.14	f	f	2	2026-07-06 15:35:06	2026-07-06 15:35:06
1456	13	favor	cuenta_bancaria	Cuenta BCP Soles	cuenta	14	107937.86	f	f	3	2026-07-06 15:35:06	2026-07-06 15:35:06
1457	13	favor	cuenta_bancaria	Plin	cuenta	17	0.00	f	f	4	2026-07-06 15:35:06	2026-07-06 15:35:06
1458	13	favor	cuenta_bancaria	Tarjeta	cuenta	4	0.00	f	f	5	2026-07-06 15:35:06	2026-07-06 15:35:06
1459	13	favor	cuenta_bancaria	Yape	cuenta	16	9787.50	f	f	6	2026-07-06 15:35:06	2026-07-06 15:35:06
1460	13	favor	stock	Stock (inventario valorizado)	\N	\N	226760.70	f	f	7	2026-07-06 15:35:06	2026-07-06 15:35:06
1461	13	favor	cxc	Deudas por cobrar (ventas a crédito)	\N	\N	68545.40	f	f	8	2026-07-06 15:35:06	2026-07-06 15:35:06
1462	13	favor	prestamo_otorgado	JHON ASTONITAS	deuda	19	245.05	f	f	9	2026-07-06 15:35:06	2026-07-06 15:35:06
1463	13	favor	prestamo_otorgado	Préstamo moto — Carlos Uceda (almacén)	deuda	20	1200.00	f	f	10	2026-07-06 15:35:06	2026-07-06 15:35:06
1464	13	favor	adelanto_proveedor	Adelanto a UYUSTOOLS PERU SAC	proveedor_adelanto	7	4500.00	f	f	11	2026-07-06 15:35:06	2026-07-06 15:35:06
1465	13	favor	adelanto_proveedor	Adelanto a PRODAC SA	proveedor_adelanto	8	2800.00	f	f	12	2026-07-06 15:35:06	2026-07-06 15:35:06
1466	13	favor	planilla_descuento	Por descontar en planilla (faltantes y cargos)	\N	\N	105.50	f	f	13	2026-07-06 15:35:06	2026-07-06 15:35:06
1467	13	contra	cxp	Proveedores por pagar	\N	\N	106900.00	f	f	1	2026-07-06 15:35:06	2026-07-06 15:35:06
1468	13	contra	gastos_emitidos	Gastos emitidos — Efectivo	cuenta	1	715.50	f	f	2	2026-07-06 15:35:06	2026-07-06 15:35:06
1469	13	contra	gastos_emitidos	Gastos emitidos — Cuenta BCP Soles	cuenta	14	35519.50	f	f	3	2026-07-06 15:35:06	2026-07-06 15:35:06
1470	13	contra	gastos_emitidos	Gastos emitidos — Tarjeta	cuenta	4	8000.00	f	f	4	2026-07-06 15:35:06	2026-07-06 15:35:06
1471	13	contra	anticipo_cliente	Clientes anticipos (a precio del día)	\N	\N	38190.00	f	f	5	2026-07-06 15:35:06	2026-07-06 15:35:06
1472	13	contra	deuda	DEUDA BCP 1 - 7630	deuda	15	6173.81	f	f	6	2026-07-06 15:35:06	2026-07-06 15:35:06
1473	13	contra	deuda	DEUDA BCP 2 - 5557	deuda	16	32546.43	f	f	7	2026-07-06 15:35:06	2026-07-06 15:35:06
1474	13	contra	deuda	JORDIN HERRERA	deuda	17	30000.00	f	f	8	2026-07-06 15:35:06	2026-07-06 15:35:06
1475	13	contra	personal	Sueldos pendientes — personal	deuda	18	2000.00	f	f	9	2026-07-06 15:35:06	2026-07-06 15:35:06
1934	43	favor	migracion	STOCK (INVENTARIO)	\N	\N	239077.29	t	t	3	2026-07-10 14:09:11	2026-07-10 14:09:11
1935	43	favor	migracion	DEUDAS POR COBRAR	\N	\N	79568.59	t	t	4	2026-07-10 14:09:11	2026-07-10 14:09:11
1909	14	favor	efectivo	Efectivo	cuenta	1	14895.11	f	f	1	2026-07-09 20:38:25	2026-07-09 20:38:25
1910	14	favor	cuenta_bancaria	Cuenta BBVA Soles	cuenta	15	4862.14	f	f	2	2026-07-09 20:38:25	2026-07-09 20:38:25
1911	14	favor	cuenta_bancaria	Cuenta BCP Soles	cuenta	14	107937.86	f	f	3	2026-07-09 20:38:25	2026-07-09 20:38:25
1912	14	favor	cuenta_bancaria	Plin	cuenta	17	0.00	f	f	4	2026-07-09 20:38:25	2026-07-09 20:38:25
1913	14	favor	cuenta_bancaria	Tarjeta	cuenta	4	0.00	f	f	5	2026-07-09 20:38:25	2026-07-09 20:38:25
1914	14	favor	cuenta_bancaria	Yape	cuenta	16	9787.50	f	f	6	2026-07-09 20:38:25	2026-07-09 20:38:25
1915	14	favor	stock	Stock (inventario valorizado)	\N	\N	226606.22	f	f	7	2026-07-09 20:38:25	2026-07-09 20:38:25
1916	14	favor	cxc	Deudas por cobrar (ventas a crédito)	\N	\N	67845.40	f	f	8	2026-07-09 20:38:25	2026-07-09 20:38:25
1917	14	favor	prestamo_otorgado	JHON ASTONITAS	deuda	19	245.05	f	f	9	2026-07-09 20:38:25	2026-07-09 20:38:25
1964	45	contra	migracion	PERSONAL	\N	\N	2500.00	t	t	9	2026-07-10 14:09:11	2026-07-10 14:09:11
1965	45	contra	migracion	JEINER	\N	\N	435.00	t	t	10	2026-07-10 14:09:11	2026-07-10 14:09:11
1966	45	contra	migracion	MILAGROS	\N	\N	200.00	t	t	11	2026-07-10 14:09:11	2026-07-10 14:09:11
1967	45	contra	migracion	LADRILLO H&C	\N	\N	5992.00	t	t	12	2026-07-10 14:09:11	2026-07-10 14:09:11
1968	46	favor	migracion	CUENTA BCP SOLES	\N	\N	43045.41	t	t	0	2026-07-10 14:09:11	2026-07-10 14:09:11
1969	46	favor	migracion	CUENTA BBVA SOLES	\N	\N	8098.43	t	t	1	2026-07-10 14:09:11	2026-07-10 14:09:11
1970	46	favor	migracion	EFECTIVO	\N	\N	5392.89	t	t	2	2026-07-10 14:09:11	2026-07-10 14:09:11
1971	46	favor	migracion	STOCK (INVENTARIO)	\N	\N	218381.60	t	t	3	2026-07-10 14:09:11	2026-07-10 14:09:11
1972	46	favor	migracion	DEUDAS POR COBRAR	\N	\N	74345.13	t	t	4	2026-07-10 14:09:11	2026-07-10 14:09:11
1973	46	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	113041.61	t	t	5	2026-07-10 14:09:11	2026-07-10 14:09:11
1974	46	contra	migracion	CLIENTES ANTICIPOS	\N	\N	37202.94	t	t	6	2026-07-10 14:09:11	2026-07-10 14:09:11
1975	46	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-10 14:09:11	2026-07-10 14:09:11
1976	46	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-10 14:09:11	2026-07-10 14:09:11
1977	46	contra	migracion	MILAGROS	\N	\N	494.00	t	t	9	2026-07-10 14:09:11	2026-07-10 14:09:11
1978	46	contra	migracion	YAPE DESCONOCIDO	\N	\N	539.00	t	t	10	2026-07-10 14:09:11	2026-07-10 14:09:11
1979	47	favor	migracion	CUENTA BCP SOLES	\N	\N	20944.70	t	t	0	2026-07-10 14:09:11	2026-07-10 14:09:11
1980	47	favor	migracion	CUENTA BBVA SOLES	\N	\N	8423.47	t	t	1	2026-07-10 14:09:11	2026-07-10 14:09:11
1981	47	favor	migracion	EFECTIVO	\N	\N	8595.89	t	t	2	2026-07-10 14:09:11	2026-07-10 14:09:11
1982	47	favor	migracion	STOCK (INVENTARIO)	\N	\N	246361.54	t	t	3	2026-07-10 14:09:11	2026-07-10 14:09:11
1983	47	favor	migracion	DEUDAS POR COBRAR	\N	\N	79864.03	t	t	4	2026-07-10 14:09:11	2026-07-10 14:09:11
1984	47	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	116530.01	t	t	5	2026-07-10 14:09:11	2026-07-10 14:09:11
1985	47	contra	migracion	CLIENTES ANTICIPOS	\N	\N	35945.24	t	t	6	2026-07-10 14:09:11	2026-07-10 14:09:11
1986	47	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-10 14:09:11	2026-07-10 14:09:11
1987	47	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-10 14:09:11	2026-07-10 14:09:11
1988	47	contra	migracion	PERSONAL	\N	\N	600.00	t	t	9	2026-07-10 14:09:11	2026-07-10 14:09:11
1989	47	contra	migracion	MILAGROS	\N	\N	494.00	t	t	10	2026-07-10 14:09:11	2026-07-10 14:09:11
1990	47	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	9747.00	t	t	11	2026-07-10 14:09:11	2026-07-10 14:09:11
1991	47	contra	migracion	YAPE DESCONOCIDO	\N	\N	144.50	t	t	12	2026-07-10 14:09:11	2026-07-10 14:09:11
1992	47	contra	migracion	YAPE DESCONOCIDO 2	\N	\N	2200.00	t	t	13	2026-07-10 14:09:11	2026-07-10 14:09:11
1993	47	contra	migracion	SALDO DE CEMENTO HOLCIM	\N	\N	290.00	t	t	14	2026-07-10 14:09:11	2026-07-10 14:09:11
1994	47	contra	migracion	YAPE DE CAMILO - DESMONTE	\N	\N	300.00	t	t	15	2026-07-10 14:09:11	2026-07-10 14:09:11
1995	48	favor	migracion	CUENTA BCP SOLES	\N	\N	4248.20	t	t	0	2026-07-10 14:09:11	2026-07-10 14:09:11
1996	48	favor	migracion	CUENTA BBVA SOLES	\N	\N	14134.62	t	t	1	2026-07-10 14:09:11	2026-07-10 14:09:11
1997	48	favor	migracion	EFECTIVO	\N	\N	16257.49	t	t	2	2026-07-10 14:09:11	2026-07-10 14:09:11
1998	48	favor	migracion	STOCK (INVENTARIO)	\N	\N	236830.27	t	t	3	2026-07-10 14:09:11	2026-07-10 14:09:11
1999	48	favor	migracion	DEUDAS POR COBRAR	\N	\N	74623.63	t	t	4	2026-07-10 14:09:11	2026-07-10 14:09:11
2000	48	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	91616.60	t	t	5	2026-07-10 14:09:11	2026-07-10 14:09:11
2001	48	contra	migracion	CLIENTES ANTICIPOS	\N	\N	33123.24	t	t	6	2026-07-10 14:09:11	2026-07-10 14:09:11
2002	48	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-10 14:09:11	2026-07-10 14:09:11
2003	48	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-10 14:09:11	2026-07-10 14:09:11
2004	48	contra	migracion	PERSONAL	\N	\N	1200.00	t	t	9	2026-07-10 14:09:11	2026-07-10 14:09:11
2005	48	contra	migracion	MILAGROS	\N	\N	494.00	t	t	10	2026-07-10 14:09:11	2026-07-10 14:09:11
2006	48	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	9747.00	t	t	11	2026-07-10 14:09:11	2026-07-10 14:09:11
2007	48	contra	migracion	JEINER HERRERA - AGREGADOS	\N	\N	245.00	t	t	12	2026-07-10 14:09:11	2026-07-10 14:09:11
2008	48	contra	migracion	ALPES	\N	\N	11550.00	t	t	13	2026-07-10 14:09:11	2026-07-10 14:09:11
2009	48	contra	migracion	SALDO DE CEMENTO HOLCIM	\N	\N	290.00	t	t	14	2026-07-10 14:09:11	2026-07-10 14:09:11
2010	48	contra	migracion	LUIS QUEVEDO	\N	\N	855.00	t	t	15	2026-07-10 14:09:11	2026-07-10 14:09:11
2011	49	favor	efectivo	Efectivo	cuenta	191	11038.79	f	f	1	2026-07-10 14:09:11	2026-07-10 14:09:11
2012	49	favor	cuenta_bancaria	Cuenta BBVA Soles	cuenta	193	16629.52	f	f	2	2026-07-10 14:09:11	2026-07-10 14:09:11
2013	49	favor	cuenta_bancaria	Cuenta BCP Dólares	cuenta	195	0.00	f	f	3	2026-07-10 14:09:11	2026-07-10 14:09:11
2014	49	favor	cuenta_bancaria	Cuenta BCP Soles	cuenta	192	62927.50	f	f	4	2026-07-10 14:09:11	2026-07-10 14:09:11
2015	49	favor	cuenta_bancaria	Yape	cuenta	194	0.00	f	f	5	2026-07-10 14:09:11	2026-07-10 14:09:11
2016	49	favor	stock	Stock (inventario valorizado)	\N	\N	258931.52	f	f	6	2026-07-10 14:09:11	2026-07-10 14:09:11
2017	49	favor	cxc	Deudas por cobrar (ventas a crédito)	\N	\N	81866.63	f	f	7	2026-07-10 14:09:11	2026-07-10 14:09:11
2018	49	favor	planilla_descuento	Por descontar en planilla (faltantes y cargos)	\N	\N	0.00	f	f	8	2026-07-10 14:09:11	2026-07-10 14:09:11
2019	49	contra	cxp	Proveedores por pagar	\N	\N	122036.10	f	f	1	2026-07-10 14:09:11	2026-07-10 14:09:11
2020	49	contra	anticipo_cliente	Clientes anticipos (a precio del día)	\N	\N	53088.74	f	f	2	2026-07-10 14:09:11	2026-07-10 14:09:11
2021	49	contra	deuda	DEUDA BCP 1 - 7630	deuda	51	6173.81	f	f	3	2026-07-10 14:09:11	2026-07-10 14:09:11
2022	49	contra	deuda	DEUDA BCP 2 - 5557	deuda	52	32546.43	f	f	4	2026-07-10 14:09:11	2026-07-10 14:09:11
2023	49	contra	deuda	INVERSIONES & TRANSPORTES	deuda	55	55028.90	f	f	5	2026-07-10 14:09:11	2026-07-10 14:09:11
2024	49	contra	deuda	LUIS QUEVEDO	deuda	57	855.00	f	f	6	2026-07-10 14:09:11	2026-07-10 14:09:11
2025	49	contra	deuda	MILAGROS	deuda	54	494.00	f	f	7	2026-07-10 14:09:11	2026-07-10 14:09:11
2026	49	contra	deuda	SALDO DE CEMENTO HOLCIM	deuda	56	290.00	f	f	8	2026-07-10 14:09:11	2026-07-10 14:09:11
2027	49	contra	personal	PERSONAL (sueldos pendientes)	deuda	53	1600.00	f	f	9	2026-07-10 14:09:11	2026-07-10 14:09:11
\.


--
-- Data for Name: balances_diarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.balances_diarios (id, empresa_id, user_id, fecha, estado, total_favor, total_contra, balance_neto, balance_anterior, diferencia, gastos_dia, utilidad_real, observacion, created_at, updated_at) FROM stdin;
12	1	1	2026-07-04	confirmado	433198.96	235196.44	198002.52	\N	\N	750.70	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
13	1	1	2026-07-05	borrador	441639.26	260045.24	181594.02	198002.52	-16408.50	608.80	-15799.70	\N	2026-07-05 19:27:37	2026-07-05 21:01:46
14	1	1	2026-07-06	borrador	440784.78	260045.24	180739.54	198002.52	-17262.98	0.00	-17262.98	\N	2026-07-06 08:52:06	2026-07-09 20:11:58
43	817	960	2026-07-01	confirmado	380461.54	219150.75	161310.79	164747.27	-3436.48	1848.70	-1587.78	Snapshot migrado del Excel (montos, sin detalle)	2026-07-10 14:09:11	2026-07-10 14:09:11
44	817	960	2026-07-02	confirmado	496236.13	338342.15	157893.98	161310.79	-3416.81	32.00	-3384.81	Snapshot migrado del Excel (montos, sin detalle)	2026-07-10 14:09:11	2026-07-10 14:09:11
45	817	960	2026-07-03	confirmado	355593.18	196164.79	159428.39	157893.98	1534.41	332.50	1866.91	Snapshot migrado del Excel (montos, sin detalle)	2026-07-10 14:09:11	2026-07-10 14:09:11
46	817	960	2026-07-04	confirmado	349263.46	189997.79	159265.67	159428.39	-162.72	30.00	-132.72	Snapshot migrado del Excel (montos, sin detalle)	2026-07-10 14:09:11	2026-07-10 14:09:11
47	817	960	2026-07-06	confirmado	364189.63	204970.99	159218.64	159265.67	-47.03	488.50	441.47	Snapshot migrado del Excel (montos, sin detalle)	2026-07-10 14:09:11	2026-07-10 14:09:11
48	817	960	2026-07-07	confirmado	346094.21	187841.08	158253.13	159218.64	-965.51	109.90	-855.61	Snapshot migrado del Excel (montos, sin detalle)	2026-07-10 14:09:11	2026-07-10 14:09:11
49	817	960	2026-07-08	confirmado	431393.96	272112.98	159280.98	158253.13	1027.85	350.00	1377.85	\N	2026-07-10 14:09:11	2026-07-10 14:09:11
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer	i:1783645039;	1783645039
laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab	i:1;	1783645039
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: cajas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cajas (id, empresa_id, local_id, nombre, caja_chica_activa, caja_chica_monto_sugerido, caja_chica_en_arqueo, activo, created_at, updated_at) FROM stdin;
1	1	1	Caja Principal	t	50.00	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39
312	1	1	Caja 2 — Mostrador	f	0.00	f	t	2026-07-05 20:04:10	2026-07-05 20:04:10
818	817	812	Caja Principal	t	50.00	f	t	2026-07-10 14:09:10	2026-07-10 14:09:10
819	817	812	Caja 2 — Mostrador	f	0.00	f	t	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias (id, empresa_id, nombre, descripcion, activo, created_at, updated_at) FROM stdin;
1	1	Ropa	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
2	1	Accesorios y carteras	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
3	1	Calzado	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
4	1	Cosméticos	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
5	1	Cuidado personal	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
6	1	Cabello y peinado	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
7	1	Joyería y bisutería	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
8	1	Tecnología y gadgets	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
9	1	Servicios de imagen	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39
322	1	Materiales de construcción	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
824	817	Ferretería	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: cierres_inventario; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cierres_inventario (id, empresa_id, almacen_id, user_id, turno_id, fecha, estado, observacion, total_items, total_diferencias, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cierres_inventario_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cierres_inventario_items (id, cierre_id, producto_id, stock_sistema, stock_declarado, diferencia, observacion, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cita_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cita_items (id, cita_id, producto_id, producto_unidad_id, cantidad, duracion_min, precio_estimado, observaciones, orden, created_at, updated_at) FROM stdin;
1	1	55	55	1.0000	60	120.00	\N	0	2026-05-18 01:53:39	2026-05-18 01:53:39
2	2	54	54	1.0000	45	80.00	\N	0	2026-05-18 01:53:39	2026-05-18 01:53:39
3	3	55	55	1.0000	90	120.00	\N	0	2026-05-18 01:53:39	2026-05-18 01:53:39
4	4	54	54	1.0000	60	80.00	\N	0	2026-05-18 01:53:39	2026-05-18 01:53:39
5	5	55	55	1.0000	60	120.00	\N	0	2026-05-18 01:53:39	2026-05-18 01:53:39
\.


--
-- Data for Name: citas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.citas (id, empresa_id, local_id, cliente_id, profesional_id, created_by, numero, fecha_hora, duracion_min, estado, observaciones, sujeto_nombre, sujeto_descripcion, venta_id, confirmada_at, iniciada_at, completada_at, cancelada_at, motivo_cancelacion, recordatorio_enviado_at, created_at, updated_at) FROM stdin;
1	1	1	2	1	1	C-001-00001	2026-05-19 10:00:00	60	programada	Maquillaje para matrimonio civil	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
2	1	1	3	1	1	C-001-00002	2026-05-19 14:00:00	45	programada	Asesoría de imagen para entrevista	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
3	1	1	4	1	1	C-001-00003	2026-05-21 08:00:00	90	programada	Maquillaje de novia + prueba	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
4	1	1	5	1	1	C-001-00004	2026-05-23 16:30:00	60	programada	Personal shopping - cambio de armario	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
5	1	1	6	1	1	C-001-00005	2026-05-25 11:00:00	60	programada	Maquillaje para sesión de fotos	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
\.


--
-- Data for Name: cliente_anticipo_aplicaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cliente_anticipo_aplicaciones (id, cliente_anticipo_id, venta_id, user_id, fecha, monto, cantidad, observacion, created_at, updated_at) FROM stdin;
5	11	\N	1	2026-07-05	2000.00	100.0000	\N	2026-07-05 20:22:56	2026-07-05 20:22:56
\.


--
-- Data for Name: cliente_anticipos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cliente_anticipos (id, empresa_id, cliente_id, user_id, metodo_pago_id, cuenta_id, fecha, monto, saldo, tipo_valorizacion, producto_id, cantidad, cantidad_pendiente, estado, observacion, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
8	1	337	1	\N	14	2026-07-02	26250.00	26250.00	material	305	25000.0000	25000.0000	activo	Ladrillo KK pagado por adelantado, entrega por obra	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
9	1	341	1	\N	16	2026-07-03	6500.00	6500.00	monto	\N	\N	\N	activo	A cuenta de materiales para su casa	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
10	1	342	2	\N	16	2026-07-05	1200.00	1200.00	monto	\N	\N	\N	activo	A cuenta de pedido de calaminas	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
11	1	342	1	5	14	2026-07-05	2000.00	0.00	material	308	200.0000	100.0000	activo	\N	2026-07-05 20:22:35	2026-07-05 20:22:56	PEN	\N	\N
123	817	1199	960	\N	\N	2026-04-24	347.02	347.02	monto	\N	\N	\N	activo	Pendiente por entregar P00100041821 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
124	817	1200	960	\N	\N	2026-04-29	33.74	33.74	monto	\N	\N	\N	activo	Pendiente por entregar P00100041961 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
125	817	1153	960	\N	\N	2026-07-03	9394.84	9394.84	monto	\N	\N	\N	activo	Pendiente por entregar P00100043645 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
126	817	1201	960	\N	\N	2026-05-19	1346.87	1346.87	monto	\N	\N	\N	activo	Pendiente por entregar P00200031999 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
127	817	1202	960	\N	\N	2026-01-27	231.35	231.35	monto	\N	\N	\N	activo	Pendiente por entregar P00200029187 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
128	817	1203	960	\N	\N	2026-03-13	2829.19	2829.19	monto	\N	\N	\N	activo	Pendiente por entregar P00100040490 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
129	817	1204	960	\N	\N	2025-01-23	28.92	28.92	monto	\N	\N	\N	activo	Pendiente por entregar F00100001944 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
130	817	1205	960	\N	\N	2023-03-29	78.08	78.08	monto	\N	\N	\N	activo	Pendiente por entregar B00100001260 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
131	817	1206	960	\N	\N	2023-08-29	471.95	471.95	monto	\N	\N	\N	activo	Pendiente por entregar P00100014270 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
132	817	1207	960	\N	\N	2025-12-06	320.13	320.13	monto	\N	\N	\N	activo	Pendiente por entregar P00100036692 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
133	817	1207	960	\N	\N	2025-12-06	320.13	320.13	monto	\N	\N	\N	activo	Pendiente por entregar P00100036693 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
134	817	1207	960	\N	\N	2026-07-08	39.92	39.92	monto	\N	\N	\N	activo	Pendiente por entregar P00100043742 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
135	817	1207	960	\N	\N	2026-07-08	471.95	471.95	monto	\N	\N	\N	activo	Pendiente por entregar P00100043743 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
136	817	1208	960	\N	\N	2024-06-10	62.93	62.93	monto	\N	\N	\N	activo	Pendiente por entregar P00100020876 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
137	817	1209	960	\N	\N	2025-12-30	5.86	5.86	monto	\N	\N	\N	activo	Pendiente por entregar P00200028299 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
138	817	1210	960	\N	\N	2024-01-03	12381.84	12381.84	monto	\N	\N	\N	activo	Pendiente por entregar P00200012046 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
139	817	1211	960	\N	\N	2026-07-08	18186.43	18186.43	monto	\N	\N	\N	activo	Pendiente por entregar P00100043762 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
140	817	1212	960	\N	\N	2025-12-01	856.58	856.58	monto	\N	\N	\N	activo	Pendiente por entregar P00100036495 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
141	817	1212	960	\N	\N	2026-01-10	884.90	884.90	monto	\N	\N	\N	activo	Pendiente por entregar P00100038090 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
142	817	1212	960	\N	\N	2026-07-04	531.33	531.33	monto	\N	\N	\N	activo	Pendiente por entregar P00100043687 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
143	817	1213	960	\N	\N	2026-02-23	216.89	216.89	monto	\N	\N	\N	activo	Pendiente por entregar P00200030094 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
144	817	1214	960	\N	\N	2026-02-19	101.21	101.21	monto	\N	\N	\N	activo	Pendiente por entregar P00200029931 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
145	817	1215	960	\N	\N	2024-01-18	62.93	62.93	monto	\N	\N	\N	activo	Pendiente por entregar P00100017909 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
146	817	1216	960	\N	\N	2023-05-02	28.92	28.92	monto	\N	\N	\N	activo	Pendiente por entregar P00200008967 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
147	817	1217	960	\N	\N	2025-12-17	292.73	292.73	monto	\N	\N	\N	activo	Pendiente por entregar P00100037178 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
148	817	1218	960	\N	\N	2026-04-17	1474.84	1474.84	monto	\N	\N	\N	activo	Pendiente por entregar P00200031344 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
149	817	1219	960	\N	\N	2026-03-07	2087.26	2087.26	monto	\N	\N	\N	activo	Pendiente por entregar P00200030422 (valorizado, migración)	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (id, empresa_id, tipo_documento, numero_documento, nombres, apellidos, razon_social, telefono, email, direccion, fecha_nacimiento, activo, created_at, updated_at, es_cliente_general) FROM stdin;
1	1	DNI	99999999	Clientes Varios		\N	\N	\N	\N	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39	t
2	1	DNI	72345678	María	Salazar Rodríguez	\N	+51 974 555 001	\N	\N	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f
3	1	DNI	70112233	Lucía	Vega Castillo	\N	+51 974 555 002	\N	\N	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f
4	1	DNI	71889944	Andrea	Torres Mendoza	\N	+51 974 555 003	\N	\N	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f
5	1	DNI	70556677	Patricia	Quispe Vargas	\N	+51 974 555 004	\N	\N	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f
6	1	DNI	73221100	Carolina	Flores Cabrera	\N	+51 974 555 005	\N	\N	\N	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f
1148	817	DNI	99999999	Cliente General		\N	\N	\N	\N	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t
1149	817	DNI	\N	ALEJANDRO PAREDES		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1150	817	DNI	\N	AZAÐERO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1151	817	DNI	\N	BAUTISTA CARRASCO ELMER - 996813790		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1152	817	DNI	\N	BECERRA ALCALDE ADELMO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1153	817	DNI	\N	BUSTAMANTE GONZALES RIGOBERTO - 967795790		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
337	1	RUC	20487965123	CONSTRUCTORA CHICLAYO SAC		CONSTRUCTORA CHICLAYO SAC	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
338	1	RUC	20539871456	CONSTRUCTORA NORTE SAC		CONSTRUCTORA NORTE SAC	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
339	1	DNI	16745823	Eladio	Vásquez Cieza	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
340	1	DNI	17458963	Manuel	Effio Puican	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
341	1	DNI	16987452	Rosa	Paredes Llontop	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
342	1	RUC	20601234789	COMERCIAL SANTA ROSA EIRL		COMERCIAL SANTA ROSA EIRL	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
1154	817	DNI	\N	CABANILLAS ZAMORA LENIN MICHEL - 918473100		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1155	817	DNI	\N	CATALINO SANCHEZ		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1156	817	RUC	20505958111	CJ TELECOM SAC		CJ TELECOM SAC	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1157	817	DNI	\N	COFESEG		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1158	817	RUC	20614348608	CONSORCIO B&B		CONSORCIO B&B	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1159	817	DNI	\N	DARIO OCHOA - 930938708		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1160	817	RUC	10166854038	DIAZ BARCO MAGDALENA		DIAZ BARCO MAGDALENA	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1161	817	DNI	\N	EDINSON BARBOZA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1162	817	DNI	\N	EDINSON VASQUEZ- ROCA FUERTE		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1163	817	DNI	\N	ELMER BUSTAMANTE - JLO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1164	817	DNI	\N	FERNANDEZ SANCHEZ LUZ ANGELICA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1165	817	DNI	\N	FERRETERIA ALPES - TUMAN		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1166	817	RUC	20604078238	FERROCONSTRUCTORA JH SERVICIOS GENERALES E.I.R.L.		FERROCONSTRUCTORA JH SERVICIOS GENERALES E.I.R.L.	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1167	817	DNI	\N	FRANCISCO BECERRA TORRES		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1168	817	DNI	\N	FREDY ALVA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1169	817	DNI	\N	GONZALES NUÐEZ EDILBERTO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1170	817	DNI	\N	GUSTAVO GUEVARA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1171	817	DNI	\N	HECTOR MEJIA CEL. 930234446		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1172	817	DNI	\N	HERRERA SALAZAR JORDIN		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1173	817	DNI	\N	HUERTAS		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1174	817	DNI	\N	ING RIMARACHIN		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1175	817	DNI	\N	JEINER HERRERA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1176	817	DNI	\N	JEINER HERRERA - FERRETERIA LA UNION		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1177	817	DNI	\N	JHON SANCHEZ CEL. 915950023		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1178	817	DNI	\N	JHONY- LA CRIA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1179	817	DNI	\N	JOSE MORE		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1180	817	DNI	\N	JUAN SALAZAR SALAZAR		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1181	817	DNI	\N	JUDITH OBLITAS		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1182	817	DNI	\N	KAREN AQUINO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1183	817	DNI	\N	MAESTRO BLANCO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1184	817	DNI	\N	MALDONADO CORDOVA LUIS ALBERTO- 990073177		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1185	817	DNI	\N	MARIA GLADYS PEREZ VASQUEZ		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1186	817	DNI	\N	MENDOZA MONDRAGON FLOR ESTELITA - 995379798		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1187	817	DNI	\N	MIGUEL CAMPOS		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1188	817	DNI	\N	MILIAN SALAZAR LUIS ALBERTO -916666706		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1189	817	DNI	\N	MORENO VALDIVIESO SUGEY DEL ROSARIO - 977761809		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1190	817	DNI	\N	ORLANDO VASQUEZ		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1191	817	DNI	\N	PROGRESO-PATAPO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1192	817	DNI	\N	QUISPE		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1193	817	RUC	20602126120	QURMAQ SOCIEDAD ANONIMA CERRADA		QURMAQ SOCIEDAD ANONIMA CERRADA	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1194	817	DNI	\N	SEÐOR LUCAS HERRERA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1195	817	DNI	\N	SONAPO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1196	817	DNI	\N	TILO - 978080316		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1197	817	RUC	20561194697	TRANSPORTES MARIA ANTONIETA S.A.C.		TRANSPORTES MARIA ANTONIETA S.A.C.	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1198	817	DNI	\N	VILCHEZ TARRILLO HERIBERTO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1199	817	DNI	\N	AUTO FACIL EN CUOTAS, ISABEL E.I.R.L.		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1200	817	DNI	\N	BUENAÑO TAPIA MERY EMILIN - 980461765		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1201	817	DNI	\N	CAMISAN AQUINO ESTHER - 988092730		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1202	817	DNI	\N	CARHUAJULCA IRURETA LUZ ANGELICA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1203	817	DNI	\N	CORONEL REGALADO DIONY JOSE - 924998664		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1204	817	DNI	\N	DISTRIBUIDORA DE PRODUCTOS DE CONSUMO MASIVO SOCIEDAD ANONIMA CERRADA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1205	817	DNI	\N	HERNANDEZ LLACSAHUANGA MARIO CESAR		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1206	817	DNI	\N	HERNANDEZ MORALES HERMINIO		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1207	817	DNI	\N	JIBAJA NEYRA KEVIN OVET - 999336049		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1208	817	DNI	\N	JUAN CLIENTE		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1209	817	DNI	\N	LACHE HERNANDEZ ORFELINDA ELIZABETH		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1210	817	DNI	\N	LUIS MEDINA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1211	817	DNI	\N	MORETO ALTAMIRANO ZARELA NOEMI - 992750519		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1212	817	DNI	\N	PEREZ DIAZ MIGUEL - 937744347		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1213	817	DNI	\N	PEREZ PEREZ JUAN CARLOS		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1214	817	DNI	\N	PESANTES CORTIJO SILVIA MARTINA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1215	817	DNI	\N	S.R OMAR		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1216	817	DNI	\N	TARRILLO VASQUEZ CLARA ESTHER		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1217	817	DNI	\N	TORRES RUIZ LUZ ANGELICA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1218	817	DNI	\N	WALTER - CASA BLANCA		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
1219	817	DNI	\N	YDROGO GALVEZ YONATAN		\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11	f
\.


--
-- Data for Name: cuenta_metodo_pago; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuenta_metodo_pago (id, cuenta_id, metodo_pago_id) FROM stdin;
2	4	2
9	14	5
11	15	5
12	14	3
13	17	4
28	194	4058
29	192	4058
30	193	4057
31	193	4060
\.


--
-- Data for Name: cuenta_movimientos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuenta_movimientos (id, empresa_id, cuenta_id, user_id, fecha, tipo, monto, descripcion, ref_tipo, ref_id, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
334	1	17	1	2026-07-09	ingreso	190.95	Venta V-0001 — Plin	venta	820	2026-07-09 19:56:19	2026-07-09 19:56:19	PEN	\N	\N
335	1	1	1	2026-07-09	ingreso	8.00	Venta V-0001 — Efectivo	venta	820	2026-07-09 19:56:19	2026-07-09 19:56:19	PEN	\N	\N
337	1	15	1	2026-07-09	ingreso	700.00	Abono venta V-0503 — Eladio Vásquez Cieza	venta_abono	10	2026-07-09 19:58:41	2026-07-09 19:58:41	PEN	\N	\N
339	817	192	960	2026-07-08	ingreso	62927.50	Ajuste de saldo: Saldo inicial — migración del sistema anterior (Excel 08-07)	ajuste	\N	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
340	817	193	960	2026-07-08	ingreso	16629.52	Ajuste de saldo: Saldo inicial — migración del sistema anterior (Excel 08-07)	ajuste	\N	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
341	817	191	960	2026-07-08	ingreso	11038.79	Ajuste de saldo: Saldo inicial — migración del sistema anterior (Excel 08-07)	ajuste	\N	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
72	1	14	1	2026-07-01	ingreso	44915.86	Ajuste de saldo: Saldo inicial al implementar el sistema (Excel)	ajuste	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
73	1	15	1	2026-07-01	ingreso	4693.54	Ajuste de saldo: Saldo inicial al implementar el sistema (Excel)	ajuste	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
74	1	1	1	2026-07-01	ingreso	8606.11	Ajuste de saldo: Saldo inicial al implementar el sistema (Excel)	ajuste	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
75	1	1	1	2026-07-02	ingreso	75.00	Cuota de deuda — Préstamo moto — Carlos Uceda (almacén)	deuda_pago	14	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
76	1	14	1	2026-07-03	egreso	20000.00	Pago a proveedor F001-8934	entrada_pago	11	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
77	1	14	1	2026-07-03	egreso	5000.00	Pago a proveedor F003-5541	entrada_pago	12	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
78	1	14	1	2026-07-02	egreso	4500.00	Adelanto a proveedor #7	proveedor_adelanto	7	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
79	1	14	1	2026-07-03	egreso	2800.00	Adelanto a proveedor #8	proveedor_adelanto	8	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
80	1	14	1	2026-07-02	ingreso	26250.00	Anticipo de cliente #8	cliente_anticipo	8	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
81	1	16	1	2026-07-03	ingreso	6500.00	Anticipo de cliente #9	cliente_anticipo	9	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
82	1	14	2	2026-07-02	ingreso	15000.00	Venta V-0101 (inicial crédito)	venta	159	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
83	1	1	2	2026-07-03	ingreso	2000.00	Venta V-0201 (inicial crédito)	venta	160	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
84	1	14	2	2026-07-03	ingreso	10000.00	Venta V-0202 (inicial crédito)	venta	161	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
85	1	1	2	2026-07-04	ingreso	849.00	Venta V-0401	venta	162	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
86	1	16	2	2026-07-04	ingreso	1210.00	Venta V-0402	venta	163	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
87	1	14	2	2026-07-04	ingreso	3172.00	Venta V-0403	venta	164	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
88	1	1	2	2026-07-04	ingreso	430.00	Venta V-0404	venta	165	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
89	1	1	2	2026-07-04	ingreso	800.00	Venta V-0405 (inicial crédito)	venta	166	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
90	1	14	1	2026-07-04	ingreso	5000.00	Abono venta V-0101	venta_abono	7	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
91	1	1	2	2026-07-04	egreso	355.00	Gasto — Combustible: Combustible FUSO	gasto	15	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
92	1	1	2	2026-07-04	egreso	120.00	Gasto — Mantenimiento vehicular: Mecánico y fajas — FUSO	gasto	16	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
93	1	1	2	2026-07-04	egreso	35.00	Gasto — Alimentación personal: Almuerzo personal	gasto	17	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
94	1	14	1	2026-07-04	egreso	240.70	Gasto — Líneas de celulares: Líneas de celulares (5)	gasto	18	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
95	1	1	2	2026-07-04	egreso	25.50	Faltante de caja — cierre de turno (Cajera)	cierre_turno	211	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
96	1	1	2	2026-07-05	ingreso	1660.00	Venta V-0501	venta	167	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
97	1	16	2	2026-07-05	ingreso	877.50	Venta V-0502	venta	168	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
98	1	1	2	2026-07-05	ingreso	300.00	Venta V-0503 (inicial crédito)	venta	169	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
99	1	14	1	2026-07-05	ingreso	1500.00	Abono venta V-0202	venta_abono	8	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
100	1	14	1	2026-07-05	egreso	2000.00	Pago a proveedor F001-8934 (FERRONOR EIRL)	entrada_pago	13	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
101	1	14	1	2026-07-05	egreso	500.00	Cuota de deuda — DEUDA BCP 1 - 7630	deuda_pago	15	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
102	1	1	1	2026-07-05	ingreso	100.00	Cuota de deuda — JHON ASTONITAS	deuda_pago	16	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
103	1	1	1	2026-07-05	ingreso	75.00	Cuota de deuda — Préstamo moto — Carlos Uceda (almacén)	deuda_pago	17	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
104	1	14	1	2026-07-05	egreso	478.80	Gasto — Energía eléctrica: Energía eléctrica — 5 recibos	gasto	19	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
105	1	1	2	2026-07-05	egreso	100.00	Gasto — Combustible: Combustible JBC	gasto	20	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
106	1	1	2	2026-07-05	egreso	20.00	Gasto — Limpieza: Sr. de limpieza	gasto	21	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
107	1	1	2	2026-07-05	egreso	10.00	Gasto — Alimentación personal: Desayuno Modesto	gasto	22	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
108	1	16	2	2026-07-05	ingreso	1200.00	Anticipo de cliente #10	cliente_anticipo	10	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
336	1	15	1	2026-07-09	egreso	90.00	Gasto — Contadora	gasto	43	2026-07-09 19:57:13	2026-07-09 19:57:13	PEN	\N	\N
338	1	15	1	2026-07-09	egreso	330.00	Gasto — SUNAT Renta	gasto	44	2026-07-09 20:01:42	2026-07-09 20:01:42	PEN	\N	\N
112	1	4	1	2026-07-05	egreso	8000.00	Pago a proveedor COFESA SAC	entrada_pago	17	2026-07-05 19:49:21	2026-07-05 19:49:21	PEN	\N	\N
113	1	14	1	2026-07-05	ingreso	100.00	Venta V-0001 — Transferencia	venta	170	2026-07-05 20:17:50	2026-07-05 20:17:50	PEN	\N	\N
114	1	15	1	2026-07-05	ingreso	168.60	Abono venta V-0001 — Andrea Torres Mendoza	venta_abono	9	2026-07-05 20:19:19	2026-07-05 20:19:19	PEN	\N	\N
115	1	14	1	2026-07-05	ingreso	2000.00	Anticipo de cliente — COMERCIAL SANTA ROSA EIRL	cliente_anticipo	11	2026-07-05 20:22:35	2026-07-05 20:22:35	PEN	\N	\N
116	1	1	1	2026-07-05	egreso	50.00	Faltante consolidado (Efectivo) — turno #213 (cajera: Jesús)	turno_consolidacion	6	2026-07-05 20:32:16	2026-07-05 20:32:16	PEN	\N	\N
\.


--
-- Data for Name: cuentas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas (id, empresa_id, nombre, numero_cuenta, banco, cci, titular, activo, created_at, updated_at, es_efectivo, moneda) FROM stdin;
1	1	Efectivo	\N	\N	\N	\N	t	2026-07-05 14:26:12	2026-07-05 14:26:12	t	PEN
4	1	Tarjeta	\N	\N	\N	\N	t	2026-07-05 19:47:03	2026-07-05 19:47:03	f	PEN
14	1	Cuenta BCP Soles	305-2214578-0-11	BCP	\N	HYC Ferromateriales SRL	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f	PEN
15	1	Cuenta BBVA Soles	0011-0249-0100045678	BBVA	\N	HYC Ferromateriales SRL	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f	PEN
16	1	Yape	\N	BCP	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f	PEN
17	1	Plin	\N	\N	\N	\N	t	2026-07-05 20:32:16	2026-07-05 20:32:16	f	PEN
191	817	Efectivo	\N	\N	\N	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	PEN
192	817	Cuenta BCP Soles	305-2279107-0-89	BCP	002-305-002279107089-13	H&C FERROMATERIALES S.R.L	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	PEN
193	817	Cuenta BBVA Soles	0011-0285-02-01958513	BBVA	\N	FERROMATERIALES H&C S.R.L	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	PEN
194	817	Yape	\N	BCP	\N	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	PEN
195	817	Cuenta BCP Dólares	\N	BCP	\N	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	USD
\.


--
-- Data for Name: descuento_conceptos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.descuento_conceptos (id, empresa_id, nombre, requiere_aprobacion, activo, created_at, updated_at) FROM stdin;
1	1	Cliente frecuente	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39
2	1	Promoción del día	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39
3	1	Cierre de temporada	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39
4	1	Producto en exhibición	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39
5	1	Cortesía	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39
\.


--
-- Data for Name: descuentos_log; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.descuentos_log (id, empresa_id, venta_id, venta_item_id, descuento_concepto_id, user_id, cliente_id, aprobado_por, monto_descuento, requeria_aprobacion, notificacion_enviada, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: deuda_pagos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.deuda_pagos (id, deuda_id, user_id, metodo_pago_id, cuenta_id, fecha, tipo, monto, observacion, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
14	20	1	\N	1	2026-07-02	amortizacion	75.00	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
15	15	1	\N	14	2026-07-05	amortizacion	500.00	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
16	19	1	\N	1	2026-07-05	amortizacion	100.00	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
17	20	1	\N	1	2026-07-05	amortizacion	75.00	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
\.


--
-- Data for Name: deudas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.deudas (id, empresa_id, user_id, direccion, tipo, nombre, monto_original, saldo, fecha_inicio, fecha_vencimiento, estado, observacion, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
16	1	1	por_pagar	bancaria	DEUDA BCP 2 - 5557	32546.43	32546.43	2026-07-01	\N	activa	Préstamo vehicular FUSO — saldo al implementar el sistema (préstamo original S/ 35,000)	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
17	1	1	por_pagar	personal	JORDIN HERRERA	30000.00	30000.00	2026-07-01	\N	activa	Préstamo personal	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
18	1	1	por_pagar	trabajador	Sueldos pendientes — personal	2000.00	2000.00	2026-07-01	\N	activa	Quincena por pagar	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
15	1	1	por_pagar	bancaria	DEUDA BCP 1 - 7630	6673.81	6173.81	2026-07-01	\N	activa	Préstamo capital de trabajo — saldo al implementar el sistema (préstamo original S/ 8,000)	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
19	1	1	por_cobrar	personal	JHON ASTONITAS	345.05	245.05	2026-07-01	\N	activa	Préstamo a tercero	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
20	1	1	por_cobrar	trabajador	Préstamo moto — Carlos Uceda (almacén)	1350.00	1200.00	2026-07-01	\N	activa	Cuota semanal S/ 75 descontada en caja — saldo al implementar el sistema (préstamo original S/ 1,500)	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
51	817	960	por_pagar	bancaria	DEUDA BCP 1 - 7630	6173.81	6173.81	2026-07-08	\N	activa	Préstamo bancario — saldo al implementar el sistema	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
52	817	960	por_pagar	bancaria	DEUDA BCP 2 - 5557	32546.43	32546.43	2026-07-08	\N	activa	Préstamo bancario — saldo al implementar el sistema	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
53	817	960	por_pagar	trabajador	PERSONAL (sueldos pendientes)	1600.00	1600.00	2026-07-08	\N	activa	Sueldos por pagar	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
54	817	960	por_pagar	personal	MILAGROS	494.00	494.00	2026-07-08	\N	activa	Deuda personal	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
55	817	960	por_pagar	personal	INVERSIONES & TRANSPORTES	55028.90	55028.90	2026-07-08	\N	activa	Deuda a proveedor de transporte	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
56	817	960	por_pagar	personal	SALDO DE CEMENTO HOLCIM	290.00	290.00	2026-07-08	\N	activa	Saldo de cemento	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
57	817	960	por_pagar	personal	LUIS QUEVEDO	855.00	855.00	2026-07-08	\N	activa	Deuda personal	2026-07-10 14:09:11	2026-07-10 14:09:11	PEN	\N	\N
\.


--
-- Data for Name: devolucion_motivos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.devolucion_motivos (id, empresa_id, nombre, slug, afecta_restock_default, es_sistema, activo, orden, created_at, updated_at) FROM stdin;
1	1	Producto equivocado	producto_equivocado	permite	t	t	10	2026-05-18 01:53:39	2026-05-18 01:53:39
2	1	Talla/tamaño incorrecto	talla_incorrecta	permite	t	t	20	2026-05-18 01:53:39	2026-05-18 01:53:39
3	1	No le gustó al cliente	no_gusto	permite	t	t	30	2026-05-18 01:53:39	2026-05-18 01:53:39
4	1	Defecto de fábrica	defecto_fabrica	obliga_merma	t	t	40	2026-05-18 01:53:39	2026-05-18 01:53:39
5	1	Vencido	vencido	obliga_merma	t	t	50	2026-05-18 01:53:39	2026-05-18 01:53:39
6	1	Otro	otro	permite	t	t	99	2026-05-18 01:53:39	2026-05-18 01:53:39
\.


--
-- Data for Name: devolucion_pagos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.devolucion_pagos (id, devolucion_id, metodo_pago_id, cuenta_metodo_pago_id, monto, referencia, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: devoluciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.devoluciones (id, empresa_id, local_id, turno_id, caja_id, venta_id, user_id, user_aprobacion_id, numero, fecha, motivo_id, forma_reembolso, monto_devolucion, monto_reembolso, requiere_aprobacion, fue_aprobada, estado, observacion, fecha_aprobacion, observacion_aprobacion, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: devoluciones_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.devoluciones_detalle (id, devolucion_id, venta_item_id, producto_id, producto_unidad_id, cantidad, cantidad_base, precio_unitario, subtotal, estado_producto, restock, motivo_id, observacion, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: empresas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.empresas (id, razon_social, nombre_comercial, ruc, direccion, telefono, email, logo, activo, created_at, updated_at, modo_almacen, descuenta_stock_en_venta, modo_cierre_caja, usa_fondos_iniciales, fondos_iniciales_en_declaracion, modo_cierre_inventario, permite_devoluciones, dias_max_devolucion, requiere_aprobacion_devolucion, restock_default, usa_agenda, agenda_sujeto_label, agenda_sujeto_requerido, tasa_igv, requiere_consolidacion_caja) FROM stdin;
1	MacSoft E.I.R.L.	MacSoft Importaciones	20612345678	Av. Balta 850, Chiclayo, Lambayeque	+51 974 123 456	macsoft@gmail.com	\N	t	2026-05-18 01:53:39	2026-07-05 15:08:33	simple	t	con_declaraciones	t	t	por_venta	t	15	f	t	t	\N	f	18.00	t
817	HYC FERROMATERIALES SRL	Ferretería H&C	20600134648	Chiclayo, Lambayeque	\N	\N	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	simple	t	con_declaraciones	t	f	por_venta	t	15	f	t	f	\N	f	18.00	f
\.


--
-- Data for Name: entrada_pagos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.entrada_pagos (id, entrada_id, user_id, metodo_pago_id, cuenta_id, proveedor_adelanto_id, fecha, monto, referencia, observacion, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
11	14	1	\N	14	\N	2026-07-03	20000.00	Transferencia BCP	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
12	16	1	\N	14	\N	2026-07-03	5000.00	Transferencia BCP	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
13	14	1	\N	14	\N	2026-07-05	2000.00	Transferencia BCP	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
17	20	1	2	4	\N	2026-07-05	8000.00	\N	\N	2026-07-05 19:49:21	2026-07-05 19:49:21	PEN	\N	\N
\.


--
-- Data for Name: entradas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.entradas (id, empresa_id, almacen_id, user_id, numero_documento, proveedor, tipo, fecha, estado, observacion, total, created_at, updated_at, proveedor_id, estado_pago, metodo_pago_id, cuenta_id, monto_pagado, moneda, tipo_cambio, monto_moneda) FROM stdin;
15	1	1	1	F002-1120	ARDILES IMPORT SRL	compra	2026-07-02	confirmado	\N	32400.00	2026-07-05 19:27:37	2026-07-05 19:27:37	11	pendiente	\N	\N	0.00	PEN	\N	\N
16	1	1	1	F003-5541	COFESA SAC	compra	2026-07-03	confirmado	\N	26300.00	2026-07-05 19:27:37	2026-07-05 19:27:37	12	parcial	\N	\N	5000.00	PEN	\N	\N
17	1	1	1	F004-0089	UYUSTOOLS PERU SAC	compra	2026-07-03	confirmado	\N	14700.00	2026-07-05 19:27:37	2026-07-05 19:27:37	13	pendiente	\N	\N	0.00	PEN	\N	\N
14	1	1	1	F001-8934	FERRONOR EIRL	compra	2026-07-02	confirmado	\N	48500.00	2026-07-05 19:27:37	2026-07-05 19:27:37	10	parcial	\N	\N	22000.00	PEN	\N	\N
20	1	1	1	\N	COFESA SAC	compra	2026-07-05	confirmado	\N	20000.00	2026-07-05 19:49:21	2026-07-05 19:49:21	12	parcial	2	4	8000.00	PEN	\N	\N
59	817	826	960	MIG-DEPOSITO PAKATNAMU S	DEPOSITO PAKATNAMU S.A.C	compra	2026-07-06	confirmado	Saldo migrado del sistema anterior	51040.00	2026-07-10 14:09:11	2026-07-10 14:09:11	39	pendiente	\N	\N	0.00	PEN	\N	\N
60	817	826	960	MIG-FERRONOR SAC.	FERRONOR SAC.	compra	2026-06-18	confirmado	Saldo migrado del sistema anterior	18767.00	2026-07-10 14:09:11	2026-07-10 14:09:11	40	pendiente	\N	\N	0.00	PEN	\N	\N
61	817	826	960	MIG-GRUPO CORPORATIVO HE	GRUPO CORPORATIVO HERRERA E.I.R.L.	compra	2026-07-06	confirmado	Saldo migrado del sistema anterior	661.50	2026-07-10 14:09:11	2026-07-10 14:09:11	41	pendiente	\N	\N	0.00	PEN	\N	\N
62	817	826	960	MIG-LADRILLERA RAMOS	LADRILLERA RAMOS	compra	2026-04-25	confirmado	Saldo migrado del sistema anterior	1900.00	2026-07-10 14:09:11	2026-07-10 14:09:11	42	pendiente	\N	\N	0.00	PEN	\N	\N
63	817	826	960	MIG-ROCA FUERTE - CARLOS	ROCA FUERTE - CARLOS	compra	2026-07-08	confirmado	Saldo migrado del sistema anterior	425.00	2026-07-10 14:09:11	2026-07-10 14:09:11	43	pendiente	\N	\N	0.00	PEN	\N	\N
64	817	826	960	MIG-SERVICIOS GENERALES 	SERVICIOS GENERALES ADJ EIRL	compra	2026-07-03	confirmado	Saldo migrado del sistema anterior	49242.60	2026-07-10 14:09:11	2026-07-10 14:09:11	44	pendiente	\N	\N	0.00	PEN	\N	\N
\.


--
-- Data for Name: entradas_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.entradas_detalle (id, entrada_id, producto_id, unidad_medida_id, cantidad, factor_conversion, cantidad_base, precio_costo, subtotal, created_at, updated_at, numero_documento) FROM stdin;
64	20	311	1	100.0000	1.0000	100.0000	200.0000	20000.00	2026-07-05 19:49:21	2026-07-05 19:49:21	\N
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: gasto_conceptos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.gasto_conceptos (id, empresa_id, gasto_tipo_id, nombre, activo, created_at, updated_at) FROM stdin;
74	817	39	Gasto operativo	t	2026-07-10 14:09:11	2026-07-10 14:09:11
51	1	25	Combustible	t	2026-07-05 19:27:37	2026-07-05 19:27:37
52	1	25	Mantenimiento vehicular	t	2026-07-05 19:27:37	2026-07-05 19:27:37
53	1	25	Peajes y pasajes	t	2026-07-05 19:27:37	2026-07-05 19:27:37
54	1	26	Alquiler local	t	2026-07-05 19:27:37	2026-07-05 19:27:37
55	1	26	Energía eléctrica	t	2026-07-05 19:27:37	2026-07-05 19:27:37
56	1	26	Líneas de celulares	t	2026-07-05 19:27:37	2026-07-05 19:27:37
57	1	26	Contadora	t	2026-07-05 19:27:37	2026-07-05 19:27:37
58	1	26	Mantenimiento bancario	t	2026-07-05 19:27:37	2026-07-05 19:27:37
59	1	27	SUNAT Renta	t	2026-07-05 19:27:37	2026-07-05 19:27:37
60	1	27	EsSalud	t	2026-07-05 19:27:37	2026-07-05 19:27:37
61	1	28	Alimentación personal	t	2026-07-05 19:27:37	2026-07-05 19:27:37
62	1	28	Limpieza	t	2026-07-05 19:27:37	2026-07-05 19:27:37
63	1	29	Herramientas y repuestos	t	2026-07-05 19:27:37	2026-07-05 19:27:37
64	1	29	Flete y descarga	t	2026-07-05 19:27:37	2026-07-05 19:27:37
65	1	30	Otros gastos	t	2026-07-05 19:27:37	2026-07-05 19:27:37
\.


--
-- Data for Name: gasto_tipos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.gasto_tipos (id, empresa_id, nombre, categoria, activo, created_at, updated_at) FROM stdin;
25	1	Vehículos y combustible	operativo	t	2026-07-05 19:27:37	2026-07-05 19:27:37
26	1	Administrativo	administrativo	t	2026-07-05 19:27:37	2026-07-05 19:27:37
27	1	Impuestos	administrativo	t	2026-07-05 19:27:37	2026-07-05 19:27:37
28	1	Personal	operativo	t	2026-07-05 19:27:37	2026-07-05 19:27:37
29	1	Operativo	operativo	t	2026-07-05 19:27:37	2026-07-05 19:27:37
30	1	Otro	otro	t	2026-07-05 19:27:37	2026-07-05 19:27:37
39	817	Operativo	operativo	t	2026-07-10 14:09:11	2026-07-10 14:09:11
\.


--
-- Data for Name: gastos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.gastos (id, empresa_id, local_id, user_id, turno_id, gasto_tipo_id, gasto_concepto_id, monto, fecha, comentario, created_at, updated_at, cuenta_id, moneda, tipo_cambio, monto_moneda) FROM stdin;
43	1	1	1	475	26	57	90.00	2026-07-09	\N	2026-07-09 19:57:13	2026-07-09 19:57:13	15	PEN	\N	\N
44	1	1	1	212	27	59	330.00	2026-07-09	\N	2026-07-09 20:01:42	2026-07-09 20:01:42	15	PEN	\N	\N
45	817	812	960	\N	39	74	50.00	2026-07-08	MOTO CAR - GASOLINA	2026-07-10 14:09:11	2026-07-10 14:09:11	191	PEN	\N	\N
46	817	812	960	\N	39	74	100.00	2026-07-08	REPARACION MOTO ROJA	2026-07-10 14:09:11	2026-07-10 14:09:11	191	PEN	\N	\N
47	817	812	960	\N	39	74	70.00	2026-07-08	ATROPELLO DE PERRO	2026-07-10 14:09:11	2026-07-10 14:09:11	191	PEN	\N	\N
48	817	812	960	\N	39	74	130.00	2026-07-08	FLETE CARLOS HERRERA	2026-07-10 14:09:11	2026-07-10 14:09:11	191	PEN	\N	\N
15	1	1	2	211	25	51	355.00	2026-07-04	Combustible FUSO	2026-07-05 19:27:37	2026-07-05 19:27:37	1	PEN	\N	\N
16	1	1	2	211	25	52	120.00	2026-07-04	Mecánico y fajas — FUSO	2026-07-05 19:27:37	2026-07-05 19:27:37	1	PEN	\N	\N
17	1	1	2	211	28	61	35.00	2026-07-04	Almuerzo personal	2026-07-05 19:27:37	2026-07-05 19:27:37	1	PEN	\N	\N
18	1	1	1	\N	26	56	240.70	2026-07-04	Líneas de celulares (5)	2026-07-05 19:27:37	2026-07-05 19:27:37	14	PEN	\N	\N
19	1	1	1	\N	26	55	478.80	2026-07-05	Energía eléctrica — 5 recibos	2026-07-05 19:27:37	2026-07-05 19:27:37	14	PEN	\N	\N
20	1	1	2	212	25	51	100.00	2026-07-05	Combustible JBC	2026-07-05 19:27:37	2026-07-05 19:27:37	1	PEN	\N	\N
21	1	1	2	212	28	62	20.00	2026-07-05	Sr. de limpieza	2026-07-05 19:27:37	2026-07-05 19:27:37	1	PEN	\N	\N
22	1	1	2	212	28	61	10.00	2026-07-05	Desayuno Modesto	2026-07-05 19:27:37	2026-07-05 19:27:37	1	PEN	\N	\N
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: locales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.locales (id, empresa_id, nombre, direccion, telefono, es_principal, activo, created_at, updated_at, descuenta_stock_en_venta, modo_cierre_caja, usa_fondos_iniciales, fondos_iniciales_en_declaracion, modo_cierre_inventario, permite_devoluciones, dias_max_devolucion, requiere_aprobacion_devolucion, restock_default) FROM stdin;
1	1	Tienda Chiclayo	Av. Balta 850	+51 974 123 456	t	t	2026-05-18 01:53:39	2026-05-18 01:53:39	\N	\N	\N	\N	\N	\N	\N	\N	\N
812	817	Tienda Principal	Chiclayo	\N	t	t	2026-07-10 14:09:10	2026-07-10 14:09:10	\N	\N	\N	\N	\N	\N	\N	\N	\N
\.


--
-- Data for Name: metodos_pago; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.metodos_pago (id, empresa_id, nombre, activo, created_at, updated_at, admite_vuelto, tipo_id) FROM stdin;
1	1	Efectivo	t	2026-05-18 01:53:39	2026-05-18 01:53:39	t	1
2	1	Tarjeta	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f	2
3	1	Yape	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f	5
4	1	Plin	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f	6
5	1	Transferencia	t	2026-05-18 01:53:39	2026-05-18 01:53:39	f	4
4056	817	Efectivo	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	1
4057	817	Tarjeta	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	2
4058	817	Yape	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	5
4059	817	Plin	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	6
4060	817	Transferencia	t	2026-07-10 14:09:10	2026-07-10 14:09:10	f	4
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000001_create_cache_table	1
2	0001_01_01_000002_create_jobs_table	1
3	0001_01_01_000003_create_empresas_table	1
4	0001_01_01_000004_create_locales_table	1
5	0001_01_01_000005_create_roles_table	1
6	0001_01_01_000006_create_modulos_table	1
7	0001_01_01_000007_create_permisos_table	1
8	0001_01_01_000008_create_users_table	1
9	2026_03_17_000001_create_clientes_table	2
10	2026_03_17_000002_create_metodos_pago_table	2
12	2026_03_17_000003_create_cuentas_table	2
13	2026_03_17_000004_create_cuenta_metodo_pago_table	2
14	2026_03_19_000001_create_cajas_table	3
15	2026_03_19_000002_create_turnos_table	4
16	2026_03_19_000003_create_turno_arqueo_table	5
17	2026_03_19_000004_create_turno_arqueo_metodos_table	6
18	2026_03_19_000005_create_turno_cierre_productos_table	7
19	2026_03_19_000006_create_gasto_tipos_table	8
20	2026_03_19_000007_create_gasto_conceptos_table	9
21	2026_03_19_000008_create_gastos_table	10
22	2026_03_19_000009_create_descuento_conceptos_table	11
23	2026_03_19_000010_create_ventas_table	12
24	2026_03_19_000011_create_venta_items_table	13
25	2026_03_19_000012_create_venta_pagos_table	14
26	2026_03_19_000013_create_descuentos_log_table	15
27	2026_05_06_000001_add_idempotency_key_to_ventas_table	16
28	2026_05_06_000002_create_auditoria_table	17
29	2026_05_09_000001_add_agenda_config_to_empresas_table	18
30	2026_05_09_000002_create_citas_table	18
31	2026_05_09_000003_create_cita_items_table	18
32	2026_05_13_000001_add_tasa_igv_to_empresas_table	19
33	2026_05_14_000001_add_admite_vuelto_to_metodos_pago_table	20
34	2026_05_14_000010_create_tipos_metodo_pago_table	21
35	2026_05_14_000011_migrate_metodos_pago_tipo_to_fk	21
36	2026_05_17_000001_add_unique_turno_numero_to_ventas	22
37	2026_05_17_000002_allow_anulado_in_cierres_inventario	23
38	2026_05_17_000003_add_es_cliente_general_to_clientes	24
39	2026_05_18_000001_add_max_descuento_porcentaje_to_roles	25
40	2026_05_19_000001_add_numero_documento_to_entradas_detalle	26
41	2026_05_19_000002_add_pago_fields_to_entradas	27
42	2026_07_04_000001_add_credito_fields_to_ventas	28
43	2026_07_04_000002_create_venta_abonos_table	28
44	2026_07_04_000003_create_cliente_anticipos_table	28
45	2026_07_04_000004_create_proveedor_adelantos_table	28
46	2026_07_04_000005_create_entrada_pagos_table	28
47	2026_07_04_000006_create_deudas_table	28
48	2026_07_04_000007_create_balances_diarios_table	28
49	2026_07_05_000001_create_cuenta_movimientos_table	29
50	2026_07_05_000002_create_turno_consolidaciones_table	30
51	2026_07_05_000003_create_turno_consolidacion_items_table	31
\.


--
-- Data for Name: modulos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.modulos (id, padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at) FROM stdin;
1	\N	Dashboard	dashboard	LayoutDashboard	/dashboard	1	t	2026-03-16 03:19:42	2026-03-16 03:19:42
2	\N	Configuración	configuracion	Settings	\N	2	t	2026-03-16 03:19:42	2026-03-16 03:19:42
3	2	Empresas	config.empresas	Building2	/configuracion/empresas	1	t	2026-03-16 03:19:42	2026-03-16 03:19:42
4	2	Locales	config.locales	MapPin	/configuracion/locales	2	t	2026-03-16 03:19:42	2026-03-16 03:19:42
5	2	Roles	config.roles	Shield	/configuracion/roles	3	t	2026-03-16 03:19:42	2026-03-16 03:19:42
6	2	Usuarios	config.usuarios	Users	/configuracion/usuarios	4	t	2026-03-16 03:19:42	2026-03-16 03:19:42
7	2	Módulos	config.modulos	Layers	/configuracion/modulos	5	t	2026-03-16 03:19:42	2026-03-16 03:19:42
8	2	Permisos por Rol	config.permisos	Lock	/configuracion/permisos	6	t	2026-03-16 03:19:42	2026-03-16 03:19:42
9	\N	Catálogo	catalogo	Package	\N	10	t	2026-03-17 00:54:24	2026-03-17 00:54:24
10	9	Categorías	catalogo.categorias	Tag	/catalogo/categorias	1	t	2026-03-17 00:54:24	2026-03-17 00:54:24
12	9	Productos y servicios	catalogo.productos	ShoppingBag	/catalogo/productos	3	t	2026-03-17 00:54:24	2026-03-17 00:54:24
13	\N	Inventario	inventario	Warehouse	\N	20	t	2026-03-17 02:47:06	2026-03-17 02:47:06
14	13	Stock actual	inventario.stock	BarChart3	/inventario/stock	1	t	2026-03-17 02:47:06	2026-03-17 02:47:06
15	13	Entradas	inventario.entradas	ArrowDownCircle	/inventario/entradas	2	t	2026-03-17 02:47:06	2026-03-17 02:47:06
16	13	Transferencias	inventario.transferencias	ArrowLeftRight	/inventario/transferencias	3	t	2026-03-17 02:47:06	2026-03-17 02:47:06
17	2	Almacenes	configuracion.almacenes	Warehouse	/configuracion/almacenes	7	t	2026-03-17 02:47:06	2026-03-17 02:47:06
20	2	Métodos de pago	configuracion.metodos-pago	Wallet	/configuracion/metodos-pago	6	t	2026-03-17 19:57:57	2026-03-17 19:57:57
21	2	Cuentas	configuracion.cuentas	Landmark	/configuracion/cuentas	7	t	2026-03-17 23:18:07	2026-03-17 23:18:07
26	2	Cajas	configuracion.cajas	Monitor	/configuracion/cajas	7	t	2026-03-19 21:11:27	2026-03-19 21:11:27
27	2	Tipos de gasto	configuracion.gastos-tipos	Tags	/configuracion/gastos/tipos	8	t	2026-03-19 21:11:27	2026-03-19 21:11:27
18	\N	Clientes	clientes	Users	/clientes	30	t	2026-03-17 19:57:57	2026-03-19 22:13:19
22	\N	Turnos	turnos	Clock	/turnos	40	t	2026-03-19 21:11:27	2026-03-19 22:13:19
24	\N	Gastos	gastos	Receipt	/gastos	50	t	2026-03-19 21:11:27	2026-03-19 22:13:19
28	\N	POS	pos	ShoppingCart	/pos	35	t	2026-03-19 23:05:51	2026-03-19 23:05:51
29	\N	Ventas	ventas	ReceiptText	/ventas	36	t	2026-03-19 23:05:51	2026-03-19 23:05:51
30	\N	Reportes	reportes	BarChart2	\N	70	t	2026-03-19 23:05:51	2026-03-19 23:05:51
31	30	Descuentos	reportes.descuentos	Tag	/reportes/descuentos	1	t	2026-03-19 23:05:51	2026-03-19 23:05:51
32	2	Conceptos de descuento	configuracion.descuento-conceptos	Percent	/configuracion/descuento-conceptos	9	t	2026-03-19 23:05:51	2026-03-19 23:05:51
33	13	Cierres de inventario	inventario.cierres	ClipboardCheck	/inventario/cierres	4	t	2026-05-04 22:49:04	2026-05-04 22:49:04
34	13	Salidas	inventario.salidas	ArrowUpCircle	/inventario/salidas	5	t	2026-05-05 05:14:47	2026-05-05 05:14:47
35	2	Tipos de salida	configuracion.salidas-tipos	Tags	/configuracion/salidas-tipos	8	t	2026-05-05 05:14:47	2026-05-05 05:14:47
36	\N	Proveedores	proveedores	Truck	/proveedores	35	t	2026-05-05 17:10:07	2026-05-05 17:10:07
37	\N	Devoluciones	devoluciones	Undo2	/devoluciones	40	t	2026-05-05 17:52:08	2026-05-05 17:52:08
38	2	Motivos de devolución	configuracion.devolucion-motivos	Tags	/configuracion/devolucion-motivos	9	t	2026-05-05 17:52:08	2026-05-05 17:52:08
39	30	Ventas	reportes.ventas	TrendingUp	/reportes/ventas	2	t	2026-05-06 12:55:11	2026-05-06 12:55:11
40	30	Productos	reportes.productos	Package	/reportes/productos	3	t	2026-05-06 12:55:11	2026-05-06 12:55:11
41	30	Caja / Turnos	reportes.caja	Wallet	/reportes/caja	4	t	2026-05-06 12:55:11	2026-05-06 12:55:11
42	30	Gastos	reportes.gastos	TrendingDown	/reportes/gastos	5	t	2026-05-06 12:55:11	2026-05-06 12:55:11
43	30	Devoluciones	reportes.devoluciones	Undo2	/reportes/devoluciones	6	t	2026-05-06 12:55:11	2026-05-06 12:55:11
44	30	Auditoría	reportes.auditoria	ShieldCheck	/reportes/auditoria	99	t	2026-05-07 02:08:18	2026-05-07 02:08:18
11	9	Presentaciones	catalogo.unidades	Ruler	/catalogo/unidades-medida	2	t	2026-03-17 00:54:24	2026-05-09 19:54:23
45	\N	Agenda	agenda	CalendarDays	/agenda	35	t	2026-05-10 02:45:54	2026-05-10 02:45:54
46	\N	Finanzas	finanzas	Wallet	\N	25	t	2026-07-04 21:03:25	2026-07-04 21:03:25
47	46	Balance diario	finanzas.balance	Scale	/finanzas/balance	1	t	2026-07-04 21:03:25	2026-07-04 21:03:25
48	46	Cuentas por cobrar	finanzas.cuentas-por-cobrar	HandCoins	/finanzas/cuentas-por-cobrar	2	t	2026-07-04 21:03:25	2026-07-04 21:03:25
49	46	Cuentas por pagar	finanzas.cuentas-por-pagar	Banknote	/finanzas/cuentas-por-pagar	3	t	2026-07-04 21:03:25	2026-07-04 21:03:25
50	46	Anticipos de clientes	finanzas.anticipos	PiggyBank	/finanzas/anticipos	4	t	2026-07-04 21:03:25	2026-07-04 21:03:25
51	46	Adelantos a proveedores	finanzas.adelantos	ArrowUpCircle	/finanzas/adelantos	5	t	2026-07-04 21:03:25	2026-07-04 21:03:25
52	46	Deudas y préstamos	finanzas.deudas	Landmark	/finanzas/deudas	6	t	2026-07-04 21:03:25	2026-07-04 21:03:25
53	46	Tesorería	finanzas.tesoreria	Coins	/finanzas/tesoreria	2	t	2026-07-05 14:26:13	2026-07-05 14:26:13
54	46	Consolidación de caja	finanzas.consolidacion	ClipboardCheck	/finanzas/consolidacion	3	t	2026-07-05 14:48:45	2026-07-05 14:48:45
55	46	Descuentos de planilla	finanzas.planilla-descuentos	UserMinus	/finanzas/descuentos-planilla	8	t	2026-07-05 14:48:45	2026-07-05 14:48:45
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: permisos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permisos (id, rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at) FROM stdin;
1	2	28	t	t	f	f	2026-05-18 01:53:39	2026-05-18 01:53:39
2	2	29	t	t	f	f	2026-05-18 01:53:39	2026-05-18 01:53:39
3	2	22	t	t	t	f	2026-05-18 01:53:39	2026-05-18 01:53:39
4	2	18	t	t	t	f	2026-05-18 01:53:39	2026-05-18 01:53:39
5	2	37	t	t	f	f	2026-05-18 01:53:39	2026-05-18 01:53:39
6	2	24	t	t	f	f	2026-05-18 01:53:39	2026-05-18 01:53:39
7	2	45	t	t	t	f	2026-05-18 01:53:39	2026-05-18 01:53:39
8	2	12	t	f	f	f	2026-05-18 01:53:39	2026-05-18 01:53:39
9	2	14	t	f	f	f	2026-05-18 01:53:39	2026-05-18 01:53:39
10	1	46	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
11	1	47	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
12	1	48	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
13	1	49	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
14	1	50	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
15	1	51	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
16	1	52	t	t	t	t	2026-07-04 21:03:25	2026-07-04 21:03:25
17	1	53	t	t	t	t	2026-07-05 14:26:13	2026-07-05 14:26:13
18	1	54	t	t	t	t	2026-07-05 14:48:45	2026-07-05 14:48:45
19	1	55	t	t	t	t	2026-07-05 14:48:45	2026-07-05 14:48:45
64	871	28	t	t	f	f	\N	\N
65	871	29	t	t	f	f	\N	\N
66	871	22	t	t	t	f	\N	\N
67	871	18	t	t	t	f	\N	\N
68	871	37	t	t	f	f	\N	\N
69	871	24	t	t	f	f	\N	\N
70	871	12	t	f	f	f	\N	\N
71	871	14	t	f	f	f	\N	\N
\.


--
-- Data for Name: planilla_descuentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.planilla_descuentos (id, empresa_id, user_id, registrado_por, fecha, monto, motivo, ref_tipo, ref_id, estado, aplicado_por, fecha_aplicacion, observacion, created_at, updated_at) FROM stdin;
7	1	2	1	2026-07-04	25.50	Faltante de caja en cierre de turno	cierre_turno	211	pendiente	\N	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
8	1	2	1	2026-07-05	80.00	Herramienta dañada — martillo demoledor	\N	\N	pendiente	\N	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
9	1	1	1	2026-07-05	50.00	Faltante de caja — turno #213 del 05/07/2026	turno_consolidacion	6	aplicado	1	2026-07-05	\N	2026-07-05 20:32:16	2026-07-05 20:33:14
\.


--
-- Data for Name: producto_unidades; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.producto_unidades (id, producto_id, unidad_medida_id, es_base, factor_conversion, tipo_precio, precio_venta, precio_costo, activo, created_at, updated_at) FROM stdin;
1	1	1	t	1.0000	fijo	45.00	24.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
2	2	1	t	1.0000	fijo	65.00	35.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
3	3	1	t	1.0000	fijo	89.00	48.95	t	2026-05-18 01:53:39	2026-05-18 01:53:39
4	4	1	t	1.0000	fijo	120.00	66.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
5	5	1	t	1.0000	fijo	75.00	41.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
6	6	1	t	1.0000	fijo	70.00	38.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
7	7	1	t	1.0000	fijo	60.00	33.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
8	8	1	t	1.0000	fijo	50.00	27.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
9	9	1	t	1.0000	fijo	55.00	30.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
10	10	1	t	1.0000	fijo	95.00	52.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
11	11	1	t	1.0000	fijo	110.00	60.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
12	12	1	t	1.0000	fijo	65.00	35.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
13	13	1	t	1.0000	fijo	89.00	48.95	t	2026-05-18 01:53:39	2026-05-18 01:53:39
14	14	1	t	1.0000	fijo	120.00	66.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
15	15	1	t	1.0000	fijo	95.00	52.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
16	16	1	t	1.0000	fijo	45.00	24.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
17	17	1	t	1.0000	fijo	35.00	19.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
18	18	1	t	1.0000	fijo	55.00	30.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
19	19	1	t	1.0000	fijo	60.00	33.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
20	20	1	t	1.0000	fijo	40.00	22.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
21	21	1	t	1.0000	fijo	50.00	27.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
22	22	1	t	1.0000	fijo	65.00	35.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
23	23	1	t	1.0000	fijo	55.00	30.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
24	24	1	t	1.0000	fijo	110.00	60.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
25	25	1	t	1.0000	fijo	145.00	79.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
26	26	1	t	1.0000	fijo	130.00	71.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
27	27	1	t	1.0000	fijo	35.00	19.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
28	28	1	t	1.0000	fijo	28.00	15.40	t	2026-05-18 01:53:39	2026-05-18 01:53:39
29	29	1	t	1.0000	fijo	28.00	15.40	t	2026-05-18 01:53:39	2026-05-18 01:53:39
30	30	1	t	1.0000	fijo	22.00	12.10	t	2026-05-18 01:53:39	2026-05-18 01:53:39
31	31	1	t	1.0000	fijo	55.00	30.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
32	32	1	t	1.0000	fijo	45.00	24.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
33	33	1	t	1.0000	fijo	75.00	41.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
34	34	1	t	1.0000	fijo	35.00	19.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
35	35	1	t	1.0000	fijo	25.00	13.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
36	36	1	t	1.0000	fijo	30.00	16.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
37	37	1	t	1.0000	fijo	65.00	35.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
38	38	1	t	1.0000	fijo	89.00	48.95	t	2026-05-18 01:53:39	2026-05-18 01:53:39
39	39	1	t	1.0000	fijo	55.00	30.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
40	40	1	t	1.0000	fijo	45.00	24.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
41	41	1	t	1.0000	fijo	30.00	16.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
42	42	1	t	1.0000	fijo	50.00	27.50	t	2026-05-18 01:53:39	2026-05-18 01:53:39
43	43	1	t	1.0000	fijo	180.00	99.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
44	44	1	t	1.0000	fijo	220.00	121.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
45	45	1	t	1.0000	fijo	145.00	79.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
46	46	1	t	1.0000	fijo	18.00	9.90	t	2026-05-18 01:53:39	2026-05-18 01:53:39
47	47	1	t	1.0000	fijo	35.00	19.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
48	48	1	t	1.0000	fijo	45.00	24.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
49	49	1	t	1.0000	fijo	28.00	15.40	t	2026-05-18 01:53:39	2026-05-18 01:53:39
50	50	1	t	1.0000	fijo	75.00	41.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
51	51	1	t	1.0000	fijo	65.00	35.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
52	52	1	t	1.0000	fijo	35.00	19.25	t	2026-05-18 01:53:39	2026-05-18 01:53:39
53	53	1	t	1.0000	fijo	25.00	13.75	t	2026-05-18 01:53:39	2026-05-18 01:53:39
54	54	1	t	1.0000	fijo	80.00	0.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
55	55	1	t	1.0000	fijo	120.00	0.00	t	2026-05-18 01:53:39	2026-05-18 01:53:39
4442	4442	812	t	1.0000	fijo	0.26	0.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4443	4443	812	t	1.0000	fijo	10.80	9.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4444	4444	812	t	1.0000	fijo	14.55	12.33	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4445	4445	812	t	1.0000	fijo	3.98	3.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4446	4446	812	t	1.0000	fijo	6.77	5.74	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4447	4447	812	t	1.0000	fijo	17.50	14.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4448	4448	812	t	1.0000	fijo	3.37	2.86	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4449	4449	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4450	4450	812	t	1.0000	fijo	2.34	1.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4451	4451	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4452	4452	812	t	1.0000	fijo	2.02	1.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4453	4453	812	t	1.0000	fijo	43.92	37.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
305	305	1	t	1.0000	fijo	1.10	0.85	t	2026-07-05 19:27:37	2026-07-05 19:27:37
306	306	1	t	1.0000	fijo	0.75	0.58	t	2026-07-05 19:27:37	2026-07-05 19:27:37
307	307	1	t	1.0000	fijo	2.40	1.90	t	2026-07-05 19:27:37	2026-07-05 19:27:37
308	308	1	t	1.0000	fijo	29.90	26.50	t	2026-07-05 19:27:37	2026-07-05 19:27:37
309	309	1	t	1.0000	fijo	36.50	32.80	t	2026-07-05 19:27:37	2026-07-05 19:27:37
310	310	1	t	1.0000	fijo	21.00	18.90	t	2026-07-05 19:27:37	2026-07-05 19:27:37
311	311	1	t	1.0000	fijo	5.50	4.20	t	2026-07-05 19:27:37	2026-07-05 19:27:37
312	312	1	t	1.0000	fijo	5.20	4.10	t	2026-07-05 19:27:37	2026-07-05 19:27:37
313	313	1	t	1.0000	fijo	5.00	3.90	t	2026-07-05 19:27:37	2026-07-05 19:27:37
314	314	1	t	1.0000	fijo	33.00	28.50	t	2026-07-05 19:27:37	2026-07-05 19:27:37
315	315	1	t	1.0000	fijo	55.00	38.00	t	2026-07-05 19:27:37	2026-07-05 19:27:37
316	316	1	t	1.0000	fijo	70.00	52.00	t	2026-07-05 19:27:37	2026-07-05 19:27:37
4454	4454	812	t	1.0000	fijo	0.12	0.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4455	4455	812	t	1.0000	fijo	11.00	9.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4456	4456	812	t	1.0000	fijo	7.58	6.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4457	4457	812	t	1.0000	fijo	37.51	31.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4458	4458	812	t	1.0000	fijo	0.20	0.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4459	4459	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4460	4460	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4461	4461	812	t	1.0000	fijo	1.10	0.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4462	4462	812	t	1.0000	fijo	0.86	0.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4463	4463	812	t	1.0000	fijo	0.48	0.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4464	4464	812	t	1.0000	fijo	1.65	1.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4465	4465	812	t	1.0000	fijo	0.55	0.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4466	4466	812	t	1.0000	fijo	27.00	22.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4467	4467	812	t	1.0000	fijo	7.30	6.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4468	4468	812	t	1.0000	fijo	3.04	2.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4469	4469	812	t	1.0000	fijo	3.04	2.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4470	4470	812	t	1.0000	fijo	31.40	26.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4471	4471	812	t	1.0000	fijo	1.10	0.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4472	4472	812	t	1.0000	fijo	8.50	7.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4473	4473	812	t	1.0000	fijo	7.78	6.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4474	4474	812	t	1.0000	fijo	6.76	5.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4475	4475	812	t	1.0000	fijo	9.40	7.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4476	4476	812	t	1.0000	fijo	2.25	1.91	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4477	4477	812	t	1.0000	fijo	4.07	3.45	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4478	4478	812	t	1.0000	fijo	3.78	3.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4479	4479	812	t	1.0000	fijo	5.72	4.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4480	4480	812	t	1.0000	fijo	38.00	32.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4481	4481	812	t	1.0000	fijo	0.35	0.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4482	4482	812	t	1.0000	fijo	20.00	16.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4483	4483	812	t	1.0000	fijo	0.20	0.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4484	4484	812	t	1.0000	fijo	0.17	0.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4485	4485	812	t	1.0000	fijo	4.81	4.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4486	4486	812	t	1.0000	fijo	2.76	2.34	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4487	4487	812	t	1.0000	fijo	2.57	2.18	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4488	4488	812	t	1.0000	fijo	0.76	0.64	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4489	4489	812	t	1.0000	fijo	3.50	2.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4490	4490	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4491	4491	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4492	4492	812	t	1.0000	fijo	1.20	1.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4493	4493	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4494	4494	812	t	1.0000	fijo	2.40	2.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4495	4495	812	t	1.0000	fijo	12.50	10.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4496	4496	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4497	4497	812	t	1.0000	fijo	0.79	0.67	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4498	4498	812	t	1.0000	fijo	1.42	1.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4499	4499	812	t	1.0000	fijo	1.16	0.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4500	4500	812	t	1.0000	fijo	1.99	1.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4501	4501	812	t	1.0000	fijo	4.41	3.74	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4502	4502	812	t	1.0000	fijo	5.81	4.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4503	4503	812	t	1.0000	fijo	2.60	2.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4504	4504	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4505	4505	812	t	1.0000	fijo	2.30	1.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4506	4506	812	t	1.0000	fijo	3.25	2.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4507	4507	812	t	1.0000	fijo	3.16	2.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4508	4508	812	t	1.0000	fijo	1.22	1.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4509	4509	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4510	4510	812	t	1.0000	fijo	9.59	8.13	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4511	4511	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4512	4512	812	t	1.0000	fijo	2.03	1.72	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4513	4513	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4514	4514	812	t	1.0000	fijo	4.51	3.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4515	4515	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4516	4516	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4517	4517	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4518	4518	812	t	1.0000	fijo	0.54	0.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4519	4519	812	t	1.0000	fijo	1.98	1.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4520	4520	812	t	1.0000	fijo	1.57	1.33	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4521	4521	812	t	1.0000	fijo	2.62	2.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4522	4522	812	t	1.0000	fijo	0.78	0.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4523	4523	812	t	1.0000	fijo	3.47	2.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4524	4524	812	t	1.0000	fijo	4.44	3.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4525	4525	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4526	4526	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4527	4527	812	t	1.0000	fijo	0.73	0.62	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4528	4528	812	t	1.0000	fijo	0.60	0.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4529	4529	812	t	1.0000	fijo	17.12	14.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4530	4530	812	t	1.0000	fijo	2.17	1.84	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4531	4531	812	t	1.0000	fijo	1.68	1.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4532	4532	812	t	1.0000	fijo	20.21	17.13	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4533	4533	812	t	1.0000	fijo	8.96	7.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4534	4534	812	t	1.0000	fijo	9.00	7.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4535	4535	812	t	1.0000	fijo	9.17	7.77	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4536	4536	812	t	1.0000	fijo	8.50	7.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4537	4537	812	t	1.0000	fijo	2.43	2.06	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4538	4538	812	t	1.0000	fijo	7.00	5.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4539	4539	812	t	1.0000	fijo	12.81	10.86	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4540	4540	812	t	1.0000	fijo	20.66	17.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4541	4541	812	t	1.0000	fijo	1.14	0.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4542	4542	812	t	1.0000	fijo	11.81	10.01	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4543	4543	812	t	1.0000	fijo	100.00	84.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4544	4544	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4545	4545	812	t	1.0000	fijo	30.80	26.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4546	4546	812	t	1.0000	fijo	1.85	1.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4547	4547	812	t	1.0000	fijo	29.35	24.87	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4548	4548	812	t	1.0000	fijo	30.60	25.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4549	4549	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4550	4550	812	t	1.0000	fijo	9.50	8.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4551	4551	812	t	1.0000	fijo	1.40	1.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4552	4552	812	t	1.0000	fijo	1.75	1.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4553	4553	812	t	1.0000	fijo	3.13	2.65	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4554	4554	812	t	1.0000	fijo	10.93	9.26	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4555	4555	812	t	1.0000	fijo	3.53	2.99	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4556	4556	812	t	1.0000	fijo	1.63	1.38	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4557	4557	812	t	1.0000	fijo	4.31	3.65	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4558	4558	812	t	1.0000	fijo	4.31	3.65	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4559	4559	812	t	1.0000	fijo	6.18	5.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4560	4560	812	t	1.0000	fijo	1.83	1.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4561	4561	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4562	4562	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4563	4563	812	t	1.0000	fijo	0.07	0.06	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4564	4564	812	t	1.0000	fijo	0.04	0.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4565	4565	812	t	1.0000	fijo	0.09	0.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4566	4566	812	t	1.0000	fijo	4.40	3.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4567	4567	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4568	4568	812	t	1.0000	fijo	4.92	4.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4569	4569	812	t	1.0000	fijo	1.75	1.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4570	4570	812	t	1.0000	fijo	0.74	0.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4571	4571	812	t	1.0000	fijo	0.64	0.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4572	4572	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4573	4573	812	t	1.0000	fijo	6.84	5.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4574	4574	812	t	1.0000	fijo	3.56	3.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4575	4575	812	t	1.0000	fijo	6.25	5.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4576	4576	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4577	4577	812	t	1.0000	fijo	32.83	27.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4578	4578	812	t	1.0000	fijo	5.66	4.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4579	4579	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4580	4580	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4581	4581	812	t	1.0000	fijo	0.50	0.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4582	4582	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4583	4583	812	t	1.0000	fijo	8.58	7.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4584	4584	812	t	1.0000	fijo	2.12	1.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4585	4585	812	t	1.0000	fijo	1.23	1.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4586	4586	812	t	1.0000	fijo	2.25	1.91	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4587	4587	812	t	1.0000	fijo	4.42	3.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4588	4588	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4589	4589	812	t	1.0000	fijo	10.40	8.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4590	4590	812	t	1.0000	fijo	11.75	9.96	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4591	4591	812	t	1.0000	fijo	15.51	13.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4592	4592	812	t	1.0000	fijo	13.00	11.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4593	4593	812	t	1.0000	fijo	4.01	3.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4594	4594	812	t	1.0000	fijo	6.50	5.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4595	4595	812	t	1.0000	fijo	13.99	11.86	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4596	4596	812	t	1.0000	fijo	5.70	4.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4597	4597	812	t	1.0000	fijo	18.01	15.26	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4598	4598	812	t	1.0000	fijo	0.38	0.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4599	4599	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4600	4600	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4601	4601	812	t	1.0000	fijo	0.33	0.28	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4602	4602	812	t	1.0000	fijo	1.45	1.23	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4603	4603	812	t	1.0000	fijo	7.00	5.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4604	4604	812	t	1.0000	fijo	82.94	70.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4605	4605	812	t	1.0000	fijo	31.00	26.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4606	4606	812	t	1.0000	fijo	17.50	14.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4607	4607	812	t	1.0000	fijo	35.20	29.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4608	4608	812	t	1.0000	fijo	7.50	6.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4609	4609	812	t	1.0000	fijo	1.09	0.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4610	4610	812	t	1.0000	fijo	2.28	1.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4611	4611	812	t	1.0000	fijo	2.84	2.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4612	4612	812	t	1.0000	fijo	3.96	3.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4613	4613	812	t	1.0000	fijo	5.97	5.06	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4614	4614	812	t	1.0000	fijo	0.09	0.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4615	4615	812	t	1.0000	fijo	4.91	4.16	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4616	4616	812	t	1.0000	fijo	4.44	3.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4617	4617	812	t	1.0000	fijo	5.78	4.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4618	4618	812	t	1.0000	fijo	6.94	5.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4619	4619	812	t	1.0000	fijo	14.40	12.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4620	4620	812	t	1.0000	fijo	8.97	7.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4621	4621	812	t	1.0000	fijo	33.21	28.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4622	4622	812	t	1.0000	fijo	2.04	1.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4623	4623	812	t	1.0000	fijo	0.60	0.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4624	4624	812	t	1.0000	fijo	65.01	55.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4625	4625	812	t	1.0000	fijo	64.88	54.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4626	4626	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4627	4627	812	t	1.0000	fijo	5.66	4.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4628	4628	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4629	4629	812	t	1.0000	fijo	0.96	0.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4630	4630	812	t	1.0000	fijo	2.99	2.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4631	4631	812	t	1.0000	fijo	1.53	1.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4632	4632	812	t	1.0000	fijo	5.76	4.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4633	4633	812	t	1.0000	fijo	2.10	1.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4634	4634	812	t	1.0000	fijo	17.50	14.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4635	4635	812	t	1.0000	fijo	0.34	0.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4636	4636	812	t	1.0000	fijo	0.74	0.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4637	4637	812	t	1.0000	fijo	17.41	14.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4638	4638	812	t	1.0000	fijo	22.04	18.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4639	4639	812	t	1.0000	fijo	0.13	0.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4640	4640	812	t	1.0000	fijo	0.22	0.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4641	4641	812	t	1.0000	fijo	4.80	4.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4642	4642	812	t	1.0000	fijo	3.43	2.91	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4643	4643	812	t	1.0000	fijo	3.60	3.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4644	4644	812	t	1.0000	fijo	3.67	3.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4645	4645	812	t	1.0000	fijo	3.67	3.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4646	4646	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4647	4647	812	t	1.0000	fijo	5.89	4.99	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4648	4648	812	t	1.0000	fijo	5.61	4.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4649	4649	812	t	1.0000	fijo	2.38	2.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4650	4650	812	t	1.0000	fijo	0.85	0.72	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4651	4651	812	t	1.0000	fijo	2.12	1.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4652	4652	812	t	1.0000	fijo	2.90	2.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4653	4653	812	t	1.0000	fijo	1.40	1.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4654	4654	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4655	4655	812	t	1.0000	fijo	4.45	3.77	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4656	4656	812	t	1.0000	fijo	2.24	1.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4657	4657	812	t	1.0000	fijo	2.90	2.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4658	4658	812	t	1.0000	fijo	0.50	0.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4659	4659	812	t	1.0000	fijo	1.55	1.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4660	4660	812	t	1.0000	fijo	1.40	1.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4661	4661	812	t	1.0000	fijo	2.90	2.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4662	4662	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4663	4663	812	t	1.0000	fijo	1.22	1.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4664	4664	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4665	4665	812	t	1.0000	fijo	1.60	1.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4666	4666	812	t	1.0000	fijo	1.56	1.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4667	4667	812	t	1.0000	fijo	1.66	1.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4668	4668	812	t	1.0000	fijo	0.86	0.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4669	4669	812	t	1.0000	fijo	5.35	4.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4670	4670	812	t	1.0000	fijo	3.17	2.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4671	4671	812	t	1.0000	fijo	9.18	7.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4672	4672	812	t	1.0000	fijo	5.30	4.49	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4673	4673	812	t	1.0000	fijo	9.52	8.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4674	4674	812	t	1.0000	fijo	4.84	4.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4675	4675	812	t	1.0000	fijo	15.16	12.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4676	4676	812	t	1.0000	fijo	7.00	5.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4677	4677	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4678	4678	812	t	1.0000	fijo	0.31	0.26	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4679	4679	812	t	1.0000	fijo	0.19	0.16	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4680	4680	812	t	1.0000	fijo	0.35	0.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4681	4681	812	t	1.0000	fijo	0.34	0.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4682	4682	812	t	1.0000	fijo	1.09	0.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4683	4683	812	t	1.0000	fijo	3.30	2.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4684	4684	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4685	4685	812	t	1.0000	fijo	3.10	2.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4686	4686	812	t	1.0000	fijo	4.92	4.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4687	4687	812	t	1.0000	fijo	6.47	5.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4688	4688	812	t	1.0000	fijo	10.50	8.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4689	4689	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4690	4690	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4691	4691	812	t	1.0000	fijo	14.50	12.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4692	4692	812	t	1.0000	fijo	0.32	0.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4693	4693	812	t	1.0000	fijo	0.11	0.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4694	4694	812	t	1.0000	fijo	0.61	0.52	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4695	4695	812	t	1.0000	fijo	0.89	0.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4696	4696	812	t	1.0000	fijo	7.00	5.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4697	4697	812	t	1.0000	fijo	0.85	0.72	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4698	4698	812	t	1.0000	fijo	1.11	0.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4699	4699	812	t	1.0000	fijo	1.12	0.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4700	4700	812	t	1.0000	fijo	2.38	2.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4701	4701	812	t	1.0000	fijo	5.39	4.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4702	4702	812	t	1.0000	fijo	5.70	4.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4703	4703	812	t	1.0000	fijo	14.92	12.64	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4704	4704	812	t	1.0000	fijo	2.60	2.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4705	4705	812	t	1.0000	fijo	2.19	1.86	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4706	4706	812	t	1.0000	fijo	4.85	4.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4707	4707	812	t	1.0000	fijo	4.68	3.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4708	4708	812	t	1.0000	fijo	3.68	3.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4709	4709	812	t	1.0000	fijo	7.80	6.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4710	4710	812	t	1.0000	fijo	1.43	1.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4711	4711	812	t	1.0000	fijo	2.11	1.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4712	4712	812	t	1.0000	fijo	11.56	9.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4713	4713	812	t	1.0000	fijo	7.50	6.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4714	4714	812	t	1.0000	fijo	4.47	3.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4715	4715	812	t	1.0000	fijo	4.84	4.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4716	4716	812	t	1.0000	fijo	1.89	1.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4717	4717	812	t	1.0000	fijo	0.25	0.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4718	4718	812	t	1.0000	fijo	12.21	10.35	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4719	4719	812	t	1.0000	fijo	6.56	5.56	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4720	4720	812	t	1.0000	fijo	7.65	6.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4721	4721	812	t	1.0000	fijo	51.50	43.64	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4722	4722	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4723	4723	812	t	1.0000	fijo	0.47	0.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4724	4724	812	t	1.0000	fijo	1.77	1.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4725	4725	812	t	1.0000	fijo	3.49	2.96	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4726	4726	812	t	1.0000	fijo	8.00	6.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4727	4727	812	t	1.0000	fijo	4.80	4.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4728	4728	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4729	4729	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4730	4730	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4731	4731	812	t	1.0000	fijo	5.00	4.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4732	4732	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4733	4733	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4734	4734	812	t	1.0000	fijo	4.90	4.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4735	4735	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4736	4736	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4737	4737	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4738	4738	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4739	4739	812	t	1.0000	fijo	3.28	2.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4740	4740	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4741	4741	812	t	1.0000	fijo	3.29	2.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4742	4742	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4743	4743	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4744	4744	812	t	1.0000	fijo	3.20	2.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4745	4745	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4746	4746	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4747	4747	812	t	1.0000	fijo	10.01	8.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4748	4748	812	t	1.0000	fijo	10.01	8.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4749	4749	812	t	1.0000	fijo	9.99	8.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4750	4750	812	t	1.0000	fijo	11.00	9.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4751	4751	812	t	1.0000	fijo	10.50	8.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4752	4752	812	t	1.0000	fijo	10.01	8.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4753	4753	812	t	1.0000	fijo	9.99	8.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4754	4754	812	t	1.0000	fijo	10.07	8.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4755	4755	812	t	1.0000	fijo	10.50	8.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4756	4756	812	t	1.0000	fijo	10.01	8.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4757	4757	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4758	4758	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4759	4759	812	t	1.0000	fijo	7.00	5.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4760	4760	812	t	1.0000	fijo	6.50	5.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4761	4761	812	t	1.0000	fijo	6.80	5.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4762	4762	812	t	1.0000	fijo	1.68	1.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4763	4763	812	t	1.0000	fijo	1.53	1.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4764	4764	812	t	1.0000	fijo	1.83	1.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4765	4765	812	t	1.0000	fijo	60.92	51.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4766	4766	812	t	1.0000	fijo	51.00	43.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4767	4767	812	t	1.0000	fijo	15.42	13.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4768	4768	812	t	1.0000	fijo	6.07	5.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4769	4769	812	t	1.0000	fijo	1.82	1.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4770	4770	812	t	1.0000	fijo	3.29	2.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4771	4771	812	t	1.0000	fijo	4.77	4.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4772	4772	812	t	1.0000	fijo	2.54	2.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4773	4773	812	t	1.0000	fijo	18.57	15.74	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4774	4774	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4775	4775	812	t	1.0000	fijo	12.64	10.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4776	4776	812	t	1.0000	fijo	15.45	13.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4777	4777	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4778	4778	812	t	1.0000	fijo	8.00	6.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4779	4779	812	t	1.0000	fijo	9.65	8.18	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4780	4780	812	t	1.0000	fijo	10.01	8.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4781	4781	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4782	4782	812	t	1.0000	fijo	15.00	12.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4783	4783	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4784	4784	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4785	4785	812	t	1.0000	fijo	3.65	3.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4786	4786	812	t	1.0000	fijo	4.00	3.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4787	4787	812	t	1.0000	fijo	0.11	0.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4788	4788	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4789	4789	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4790	4790	812	t	1.0000	fijo	3.59	3.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4791	4791	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4792	4792	812	t	1.0000	fijo	5.20	4.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4793	4793	812	t	1.0000	fijo	32.64	27.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4794	4794	812	t	1.0000	fijo	29.67	25.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4795	4795	812	t	1.0000	fijo	74.27	62.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4796	4796	812	t	1.0000	fijo	18.14	15.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4797	4797	812	t	1.0000	fijo	49.91	42.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4798	4798	812	t	1.0000	fijo	7.26	6.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4799	4799	812	t	1.0000	fijo	13.23	11.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4800	4800	812	t	1.0000	fijo	4.50	3.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4801	4801	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4802	4802	812	t	1.0000	fijo	11.00	9.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4803	4803	812	t	1.0000	fijo	4.35	3.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4804	4804	812	t	1.0000	fijo	2.34	1.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4805	4805	812	t	1.0000	fijo	1.32	1.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4806	4806	812	t	1.0000	fijo	5.00	4.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4807	4807	812	t	1.0000	fijo	0.25	0.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4808	4808	812	t	1.0000	fijo	0.22	0.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4809	4809	812	t	1.0000	fijo	0.29	0.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4810	4810	812	t	1.0000	fijo	0.40	0.34	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4811	4811	812	t	1.0000	fijo	20.79	17.62	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4812	4812	812	t	1.0000	fijo	2.68	2.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4813	4813	812	t	1.0000	fijo	2.12	1.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4814	4814	812	t	1.0000	fijo	0.59	0.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4815	4815	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4816	4816	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4817	4817	812	t	1.0000	fijo	3.50	2.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4818	4818	812	t	1.0000	fijo	3.50	2.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4819	4819	812	t	1.0000	fijo	3.01	2.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4820	4820	812	t	1.0000	fijo	3.29	2.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4821	4821	812	t	1.0000	fijo	5.68	4.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4822	4822	812	t	1.0000	fijo	3.96	3.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4823	4823	812	t	1.0000	fijo	19.97	16.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4824	4824	812	t	1.0000	fijo	7.33	6.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4825	4825	812	t	1.0000	fijo	7.33	6.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4826	4826	812	t	1.0000	fijo	1.09	0.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4827	4827	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4828	4828	812	t	1.0000	fijo	1.09	0.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4829	4829	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4830	4830	812	t	1.0000	fijo	2.14	1.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4831	4831	812	t	1.0000	fijo	20.38	17.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4832	4832	812	t	1.0000	fijo	1.89	1.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4833	4833	812	t	1.0000	fijo	12.86	10.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4834	4834	812	t	1.0000	fijo	1.46	1.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4835	4835	812	t	1.0000	fijo	1.42	1.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4836	4836	812	t	1.0000	fijo	9.45	8.01	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4837	4837	812	t	1.0000	fijo	2.67	2.26	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4838	4838	812	t	1.0000	fijo	20.10	17.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4839	4839	812	t	1.0000	fijo	3.34	2.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4840	4840	812	t	1.0000	fijo	111.33	94.35	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4841	4841	812	t	1.0000	fijo	32.50	27.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4842	4842	812	t	1.0000	fijo	37.50	31.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4843	4843	812	t	1.0000	fijo	2.04	1.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4844	4844	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4845	4845	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4846	4846	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4847	4847	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4848	4848	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4849	4849	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4850	4850	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4851	4851	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4852	4852	812	t	1.0000	fijo	1.45	1.23	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4853	4853	812	t	1.0000	fijo	7.50	6.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4854	4854	812	t	1.0000	fijo	6.01	5.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4855	4855	812	t	1.0000	fijo	7.45	6.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4856	4856	812	t	1.0000	fijo	4.52	3.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4857	4857	812	t	1.0000	fijo	41.93	35.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4858	4858	812	t	1.0000	fijo	19.71	16.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4859	4859	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4860	4860	812	t	1.0000	fijo	16.69	14.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4861	4861	812	t	1.0000	fijo	14.01	11.87	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4862	4862	812	t	1.0000	fijo	17.94	15.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4863	4863	812	t	1.0000	fijo	16.31	13.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4864	4864	812	t	1.0000	fijo	20.10	17.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4865	4865	812	t	1.0000	fijo	19.74	16.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4866	4866	812	t	1.0000	fijo	12.85	10.89	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4867	4867	812	t	1.0000	fijo	12.61	10.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4868	4868	812	t	1.0000	fijo	3.93	3.33	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4869	4869	812	t	1.0000	fijo	54.50	46.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4870	4870	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4871	4871	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4872	4872	812	t	1.0000	fijo	5.39	4.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4873	4873	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4874	4874	812	t	1.0000	fijo	2.55	2.16	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4875	4875	812	t	1.0000	fijo	2.86	2.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4876	4876	812	t	1.0000	fijo	1.23	1.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4877	4877	812	t	1.0000	fijo	1.18	1.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4878	4878	812	t	1.0000	fijo	1.16	0.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4879	4879	812	t	1.0000	fijo	1.40	1.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4880	4880	812	t	1.0000	fijo	1.05	0.89	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4881	4881	812	t	1.0000	fijo	1.16	0.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4882	4882	812	t	1.0000	fijo	1.12	0.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4883	4883	812	t	1.0000	fijo	1.27	1.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4884	4884	812	t	1.0000	fijo	1.42	1.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4885	4885	812	t	1.0000	fijo	1.86	1.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4886	4886	812	t	1.0000	fijo	1.65	1.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4887	4887	812	t	1.0000	fijo	1.25	1.06	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4888	4888	812	t	1.0000	fijo	1.24	1.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4889	4889	812	t	1.0000	fijo	19.35	16.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4890	4890	812	t	1.0000	fijo	32.53	27.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4891	4891	812	t	1.0000	fijo	11.55	9.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4892	4892	812	t	1.0000	fijo	18.35	15.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4893	4893	812	t	1.0000	fijo	16.43	13.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4894	4894	812	t	1.0000	fijo	15.84	13.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4895	4895	812	t	1.0000	fijo	14.71	12.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4896	4896	812	t	1.0000	fijo	15.12	12.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4897	4897	812	t	1.0000	fijo	8.96	7.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4898	4898	812	t	1.0000	fijo	11.22	9.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4899	4899	812	t	1.0000	fijo	7.17	6.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4900	4900	812	t	1.0000	fijo	1.11	0.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4901	4901	812	t	1.0000	fijo	1.16	0.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4902	4902	812	t	1.0000	fijo	1.24	1.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4903	4903	812	t	1.0000	fijo	1.36	1.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4904	4904	812	t	1.0000	fijo	1.56	1.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4905	4905	812	t	1.0000	fijo	1.86	1.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4906	4906	812	t	1.0000	fijo	2.15	1.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4907	4907	812	t	1.0000	fijo	2.54	2.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4908	4908	812	t	1.0000	fijo	1.04	0.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4909	4909	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4910	4910	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4911	4911	812	t	1.0000	fijo	26.51	22.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4912	4912	812	t	1.0000	fijo	39.22	33.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4913	4913	812	t	1.0000	fijo	7.94	6.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4914	4914	812	t	1.0000	fijo	8.47	7.18	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4915	4915	812	t	1.0000	fijo	3.19	2.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4916	4916	812	t	1.0000	fijo	4.86	4.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4917	4917	812	t	1.0000	fijo	24.00	20.34	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4918	4918	812	t	1.0000	fijo	36.26	30.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4919	4919	812	t	1.0000	fijo	36.46	30.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4920	4920	812	t	1.0000	fijo	35.21	29.84	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4921	4921	812	t	1.0000	fijo	36.50	30.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4922	4922	812	t	1.0000	fijo	26.00	22.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4923	4923	812	t	1.0000	fijo	24.89	21.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4924	4924	812	t	1.0000	fijo	27.27	23.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4925	4925	812	t	1.0000	fijo	12.50	10.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4926	4926	812	t	1.0000	fijo	13.00	11.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4927	4927	812	t	1.0000	fijo	3.73	3.16	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4928	4928	812	t	1.0000	fijo	1.31	1.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4929	4929	812	t	1.0000	fijo	3.69	3.13	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4930	4930	812	t	1.0000	fijo	5.18	4.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4931	4931	812	t	1.0000	fijo	0.65	0.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4932	4932	812	t	1.0000	fijo	1.52	1.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4933	4933	812	t	1.0000	fijo	2.82	2.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4934	4934	812	t	1.0000	fijo	1.75	1.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4935	4935	812	t	1.0000	fijo	1.16	0.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4936	4936	812	t	1.0000	fijo	12.34	10.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4937	4937	812	t	1.0000	fijo	10.03	8.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4938	4938	812	t	1.0000	fijo	9.44	8.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4939	4939	812	t	1.0000	fijo	1.20	1.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4940	4940	812	t	1.0000	fijo	1.48	1.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4941	4941	812	t	1.0000	fijo	1.65	1.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4942	4942	812	t	1.0000	fijo	4.90	4.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4943	4943	812	t	1.0000	fijo	1.63	1.38	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4944	4944	812	t	1.0000	fijo	1.13	0.96	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4945	4945	812	t	1.0000	fijo	1.70	1.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4946	4946	812	t	1.0000	fijo	1.09	0.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4947	4947	812	t	1.0000	fijo	0.53	0.45	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4948	4948	812	t	1.0000	fijo	23.51	19.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4949	4949	812	t	1.0000	fijo	26.00	22.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4950	4950	812	t	1.0000	fijo	9.99	8.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4951	4951	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4952	4952	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4953	4953	812	t	1.0000	fijo	0.71	0.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4954	4954	812	t	1.0000	fijo	0.42	0.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4955	4955	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4956	4956	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4957	4957	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4958	4958	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4959	4959	812	t	1.0000	fijo	0.64	0.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4960	4960	812	t	1.0000	fijo	0.72	0.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4961	4961	812	t	1.0000	fijo	13.43	11.38	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4962	4962	812	t	1.0000	fijo	19.94	16.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4963	4963	812	t	1.0000	fijo	24.34	20.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4964	4964	812	t	1.0000	fijo	32.53	27.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4965	4965	812	t	1.0000	fijo	3.55	3.01	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4966	4966	812	t	1.0000	fijo	3.86	3.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4967	4967	812	t	1.0000	fijo	8.90	7.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4968	4968	812	t	1.0000	fijo	3.08	2.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4969	4969	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4970	4970	812	t	1.0000	fijo	5.00	4.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4971	4971	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4972	4972	812	t	1.0000	fijo	7.30	6.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4973	4973	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4974	4974	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4975	4975	812	t	1.0000	fijo	0.60	0.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4976	4976	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4977	4977	812	t	1.0000	fijo	1.10	0.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4978	4978	812	t	1.0000	fijo	0.37	0.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4979	4979	812	t	1.0000	fijo	0.37	0.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4980	4980	812	t	1.0000	fijo	0.60	0.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4981	4981	812	t	1.0000	fijo	0.45	0.38	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4982	4982	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4983	4983	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4984	4984	812	t	1.0000	fijo	3.56	3.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4985	4985	812	t	1.0000	fijo	3.28	2.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4986	4986	812	t	1.0000	fijo	3.50	2.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4987	4987	812	t	1.0000	fijo	3.60	3.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4988	4988	812	t	1.0000	fijo	6.01	5.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4989	4989	812	t	1.0000	fijo	0.57	0.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4990	4990	812	t	1.0000	fijo	0.40	0.34	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4991	4991	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4992	4992	812	t	1.0000	fijo	32.00	27.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4993	4993	812	t	1.0000	fijo	33.00	27.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4994	4994	812	t	1.0000	fijo	8.00	6.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4995	4995	812	t	1.0000	fijo	8.50	7.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4996	4996	812	t	1.0000	fijo	12.50	10.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4997	4997	812	t	1.0000	fijo	0.27	0.23	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4998	4998	812	t	1.0000	fijo	0.33	0.28	t	2026-07-10 14:09:10	2026-07-10 14:09:10
4999	4999	812	t	1.0000	fijo	40.00	33.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5000	5000	812	t	1.0000	fijo	4.51	3.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5001	5001	812	t	1.0000	fijo	0.67	0.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5002	5002	812	t	1.0000	fijo	0.89	0.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5003	5003	812	t	1.0000	fijo	1.14	0.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5004	5004	812	t	1.0000	fijo	16.00	13.56	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5005	5005	812	t	1.0000	fijo	13.00	11.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5006	5006	812	t	1.0000	fijo	13.10	11.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5007	5007	812	t	1.0000	fijo	12.92	10.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5008	5008	812	t	1.0000	fijo	13.50	11.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5009	5009	812	t	1.0000	fijo	12.57	10.65	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5010	5010	812	t	1.0000	fijo	12.84	10.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5011	5011	812	t	1.0000	fijo	13.50	11.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5012	5012	812	t	1.0000	fijo	12.84	10.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5013	5013	812	t	1.0000	fijo	15.00	12.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5014	5014	812	t	1.0000	fijo	15.00	12.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5015	5015	812	t	1.0000	fijo	13.50	11.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5016	5016	812	t	1.0000	fijo	12.50	10.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5017	5017	812	t	1.0000	fijo	12.70	10.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5018	5018	812	t	1.0000	fijo	2.80	2.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5019	5019	812	t	1.0000	fijo	2.94	2.49	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5020	5020	812	t	1.0000	fijo	18.00	15.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5021	5021	812	t	1.0000	fijo	2.95	2.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5022	5022	812	t	1.0000	fijo	2.77	2.35	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5023	5023	812	t	1.0000	fijo	2.78	2.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5024	5024	812	t	1.0000	fijo	2.91	2.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5025	5025	812	t	1.0000	fijo	3.41	2.89	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5026	5026	812	t	1.0000	fijo	4.58	3.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5027	5027	812	t	1.0000	fijo	2.91	2.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5028	5028	812	t	1.0000	fijo	2.96	2.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5029	5029	812	t	1.0000	fijo	4.59	3.89	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5030	5030	812	t	1.0000	fijo	9.00	7.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5031	5031	812	t	1.0000	fijo	7.86	6.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5032	5032	812	t	1.0000	fijo	6.70	5.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5033	5033	812	t	1.0000	fijo	10.96	9.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5034	5034	812	t	1.0000	fijo	2.32	1.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5035	5035	812	t	1.0000	fijo	3.06	2.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5036	5036	812	t	1.0000	fijo	0.05	0.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5037	5037	812	t	1.0000	fijo	0.01	0.01	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5038	5038	812	t	1.0000	fijo	0.05	0.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5039	5039	812	t	1.0000	fijo	11.61	9.84	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5040	5040	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5041	5041	812	t	1.0000	fijo	6.50	5.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5042	5042	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5043	5043	812	t	1.0000	fijo	9.81	8.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5044	5044	812	t	1.0000	fijo	46.00	38.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5045	5045	812	t	1.0000	fijo	38.40	32.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5046	5046	812	t	1.0000	fijo	26.10	22.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5047	5047	812	t	1.0000	fijo	4.01	3.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5048	5048	812	t	1.0000	fijo	14.40	12.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5049	5049	812	t	1.0000	fijo	3.28	2.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5050	5050	812	t	1.0000	fijo	16.30	13.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5051	5051	812	t	1.0000	fijo	7.00	5.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5052	5052	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5053	5053	812	t	1.0000	fijo	1.59	1.35	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5054	5054	812	t	1.0000	fijo	35.00	29.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5055	5055	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5056	5056	812	t	1.0000	fijo	60.00	50.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5057	5057	812	t	1.0000	fijo	60.00	50.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5058	5058	812	t	1.0000	fijo	0.58	0.49	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5059	5059	812	t	1.0000	fijo	0.67	0.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5060	5060	812	t	1.0000	fijo	13.40	11.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5061	5061	812	t	1.0000	fijo	13.00	11.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5062	5062	812	t	1.0000	fijo	13.50	11.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5063	5063	812	t	1.0000	fijo	13.50	11.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5064	5064	812	t	1.0000	fijo	2.95	2.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5065	5065	812	t	1.0000	fijo	2.87	2.43	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5066	5066	812	t	1.0000	fijo	2.80	2.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5067	5067	812	t	1.0000	fijo	2.80	2.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5068	5068	812	t	1.0000	fijo	2.80	2.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5069	5069	812	t	1.0000	fijo	2.87	2.43	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5070	5070	812	t	1.0000	fijo	2.88	2.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5071	5071	812	t	1.0000	fijo	2.80	2.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5072	5072	812	t	1.0000	fijo	2.95	2.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5073	5073	812	t	1.0000	fijo	4.14	3.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5074	5074	812	t	1.0000	fijo	2.99	2.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5075	5075	812	t	1.0000	fijo	3.02	2.56	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5076	5076	812	t	1.0000	fijo	3.12	2.64	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5077	5077	812	t	1.0000	fijo	3.66	3.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5078	5078	812	t	1.0000	fijo	3.32	2.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5079	5079	812	t	1.0000	fijo	2.93	2.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5080	5080	812	t	1.0000	fijo	3.15	2.67	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5081	5081	812	t	1.0000	fijo	3.48	2.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5082	5082	812	t	1.0000	fijo	2.97	2.52	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5083	5083	812	t	1.0000	fijo	3.50	2.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5084	5084	812	t	1.0000	fijo	3.33	2.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5085	5085	812	t	1.0000	fijo	12.00	10.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5086	5086	812	t	1.0000	fijo	10.67	9.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5087	5087	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5088	5088	812	t	1.0000	fijo	0.04	0.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5089	5089	812	t	1.0000	fijo	0.13	0.11	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5090	5090	812	t	1.0000	fijo	3.29	2.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5091	5091	812	t	1.0000	fijo	8.87	7.52	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5092	5092	812	t	1.0000	fijo	7.89	6.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5093	5093	812	t	1.0000	fijo	31.03	26.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5094	5094	812	t	1.0000	fijo	13.99	11.86	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5095	5095	812	t	1.0000	fijo	4.06	3.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5096	5096	812	t	1.0000	fijo	17.50	14.83	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5097	5097	812	t	1.0000	fijo	1.99	1.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5098	5098	812	t	1.0000	fijo	1.83	1.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5099	5099	812	t	1.0000	fijo	1.32	1.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5100	5100	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5101	5101	812	t	1.0000	fijo	27.06	22.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5102	5102	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5103	5103	812	t	1.0000	fijo	0.93	0.79	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5104	5104	812	t	1.0000	fijo	1.14	0.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5105	5105	812	t	1.0000	fijo	1.91	1.62	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5106	5106	812	t	1.0000	fijo	1.66	1.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5107	5107	812	t	1.0000	fijo	2.22	1.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5108	5108	812	t	1.0000	fijo	0.92	0.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5109	5109	812	t	1.0000	fijo	1.60	1.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5110	5110	812	t	1.0000	fijo	3.76	3.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5111	5111	812	t	1.0000	fijo	4.04	3.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5112	5112	812	t	1.0000	fijo	2.60	2.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5113	5113	812	t	1.0000	fijo	6.37	5.40	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5114	5114	812	t	1.0000	fijo	3.12	2.64	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5115	5115	812	t	1.0000	fijo	6.57	5.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5116	5116	812	t	1.0000	fijo	9.70	8.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5117	5117	812	t	1.0000	fijo	9.61	8.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5118	5118	812	t	1.0000	fijo	9.52	8.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5119	5119	812	t	1.0000	fijo	3.03	2.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5120	5120	812	t	1.0000	fijo	3.21	2.72	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5121	5121	812	t	1.0000	fijo	14.50	12.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5122	5122	812	t	1.0000	fijo	6.01	5.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5123	5123	812	t	1.0000	fijo	6.08	5.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5124	5124	812	t	1.0000	fijo	7.50	6.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5125	5125	812	t	1.0000	fijo	7.63	6.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5126	5126	812	t	1.0000	fijo	1.36	1.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5127	5127	812	t	1.0000	fijo	1.99	1.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5128	5128	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5129	5129	812	t	1.0000	fijo	1.99	1.69	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5130	5130	812	t	1.0000	fijo	1.71	1.45	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5131	5131	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5132	5132	812	t	1.0000	fijo	1.82	1.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5133	5133	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5134	5134	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5135	5135	812	t	1.0000	fijo	1.84	1.56	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5136	5136	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5137	5137	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5138	5138	812	t	1.0000	fijo	1.90	1.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5139	5139	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5140	5140	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5141	5141	812	t	1.0000	fijo	0.50	0.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5142	5142	812	t	1.0000	fijo	0.50	0.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5143	5143	812	t	1.0000	fijo	1.14	0.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5144	5144	812	t	1.0000	fijo	3.08	2.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5145	5145	812	t	1.0000	fijo	21.30	18.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5146	5146	812	t	1.0000	fijo	12.05	10.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5147	5147	812	t	1.0000	fijo	4.79	4.06	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5148	5148	812	t	1.0000	fijo	5.25	4.45	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5149	5149	812	t	1.0000	fijo	4.70	3.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5150	5150	812	t	1.0000	fijo	11.66	9.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5151	5151	812	t	1.0000	fijo	0.92	0.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5152	5152	812	t	1.0000	fijo	0.01	0.01	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5153	5153	812	t	1.0000	fijo	1.52	1.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5154	5154	812	t	1.0000	fijo	8.71	7.38	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5155	5155	812	t	1.0000	fijo	16.82	14.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5156	5156	812	t	1.0000	fijo	7.33	6.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5157	5157	812	t	1.0000	fijo	0.05	0.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5158	5158	812	t	1.0000	fijo	0.05	0.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5159	5159	812	t	1.0000	fijo	0.05	0.04	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5160	5160	812	t	1.0000	fijo	0.06	0.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5161	5161	812	t	1.0000	fijo	0.38	0.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5162	5162	812	t	1.0000	fijo	1.86	1.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5163	5163	812	t	1.0000	fijo	2.37	2.01	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5164	5164	812	t	1.0000	fijo	1.70	1.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5165	5165	812	t	1.0000	fijo	0.63	0.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5166	5166	812	t	1.0000	fijo	2.63	2.23	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5167	5167	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5168	5168	812	t	1.0000	fijo	1.64	1.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5169	5169	812	t	1.0000	fijo	0.84	0.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5170	5170	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5171	5171	812	t	1.0000	fijo	4.31	3.65	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5172	5172	812	t	1.0000	fijo	6.34	5.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5173	5173	812	t	1.0000	fijo	3.80	3.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5174	5174	812	t	1.0000	fijo	19.71	16.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5175	5175	812	t	1.0000	fijo	7.62	6.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5176	5176	812	t	1.0000	fijo	3.33	2.82	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5177	5177	812	t	1.0000	fijo	5.20	4.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5178	5178	812	t	1.0000	fijo	23.00	19.49	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5179	5179	812	t	1.0000	fijo	10.50	8.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5180	5180	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5181	5181	812	t	1.0000	fijo	5.00	4.24	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5182	5182	812	t	1.0000	fijo	10.48	8.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5183	5183	812	t	1.0000	fijo	15.40	13.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5184	5184	812	t	1.0000	fijo	7.32	6.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5185	5185	812	t	1.0000	fijo	1.90	1.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5186	5186	812	t	1.0000	fijo	6.50	5.51	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5187	5187	812	t	1.0000	fijo	2.90	2.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5188	5188	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5189	5189	812	t	1.0000	fijo	10.44	8.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5190	5190	812	t	1.0000	fijo	0.55	0.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5191	5191	812	t	1.0000	fijo	8.50	7.20	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5192	5192	812	t	1.0000	fijo	4.25	3.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5193	5193	812	t	1.0000	fijo	5.40	4.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5194	5194	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5195	5195	812	t	1.0000	fijo	1.90	1.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5196	5196	812	t	1.0000	fijo	1.82	1.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5197	5197	812	t	1.0000	fijo	8.07	6.84	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5198	5198	812	t	1.0000	fijo	14.84	12.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5199	5199	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5200	5200	812	t	1.0000	fijo	9.13	7.74	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5201	5201	812	t	1.0000	fijo	0.29	0.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5202	5202	812	t	1.0000	fijo	0.20	0.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5203	5203	812	t	1.0000	fijo	14.47	12.26	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5204	5204	812	t	1.0000	fijo	16.00	13.56	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5205	5205	812	t	1.0000	fijo	3.30	2.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5206	5206	812	t	1.0000	fijo	10.73	9.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5207	5207	812	t	1.0000	fijo	5.14	4.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5208	5208	812	t	1.0000	fijo	0.09	0.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5209	5209	812	t	1.0000	fijo	8.19	6.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5210	5210	812	t	1.0000	fijo	0.09	0.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5211	5211	812	t	1.0000	fijo	2.25	1.91	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5212	5212	812	t	1.0000	fijo	2.21	1.87	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5213	5213	812	t	1.0000	fijo	1.72	1.46	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5214	5214	812	t	1.0000	fijo	2.04	1.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5215	5215	812	t	1.0000	fijo	13.63	11.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5216	5216	812	t	1.0000	fijo	0.06	0.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5217	5217	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5218	5218	812	t	1.0000	fijo	0.04	0.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5219	5219	812	t	1.0000	fijo	7.15	6.06	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5220	5220	812	t	1.0000	fijo	9.61	8.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5221	5221	812	t	1.0000	fijo	21.75	18.43	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5222	5222	812	t	1.0000	fijo	38.50	32.63	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5223	5223	812	t	1.0000	fijo	73.50	62.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5224	5224	812	t	1.0000	fijo	0.12	0.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5225	5225	812	t	1.0000	fijo	19.10	16.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5226	5226	812	t	1.0000	fijo	7.04	5.97	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5227	5227	812	t	1.0000	fijo	7.50	6.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5228	5228	812	t	1.0000	fijo	18.20	15.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5229	5229	812	t	1.0000	fijo	89.76	76.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5230	5230	812	t	1.0000	fijo	4.00	3.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5231	5231	812	t	1.0000	fijo	2.14	1.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5232	5232	812	t	1.0000	fijo	2.30	1.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5233	5233	812	t	1.0000	fijo	568.00	481.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5234	5234	812	t	1.0000	fijo	500.00	423.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5235	5235	812	t	1.0000	fijo	0.22	0.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5236	5236	812	t	1.0000	fijo	0.22	0.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5237	5237	812	t	1.0000	fijo	0.84	0.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5238	5238	812	t	1.0000	fijo	1.48	1.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5239	5239	812	t	1.0000	fijo	1.90	1.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5240	5240	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5241	5241	812	t	1.0000	fijo	2.18	1.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5242	5242	812	t	1.0000	fijo	0.40	0.34	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5243	5243	812	t	1.0000	fijo	1.29	1.09	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5244	5244	812	t	1.0000	fijo	0.26	0.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5245	5245	812	t	1.0000	fijo	1.10	0.93	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5246	5246	812	t	1.0000	fijo	0.90	0.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5247	5247	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5248	5248	812	t	1.0000	fijo	1.62	1.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5249	5249	812	t	1.0000	fijo	1.04	0.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5250	5250	812	t	1.0000	fijo	2.30	1.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5251	5251	812	t	1.0000	fijo	1.39	1.18	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5252	5252	812	t	1.0000	fijo	0.59	0.50	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5253	5253	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5254	5254	812	t	1.0000	fijo	1.40	1.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5255	5255	812	t	1.0000	fijo	1.11	0.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5256	5256	812	t	1.0000	fijo	1.58	1.34	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5257	5257	812	t	1.0000	fijo	1.18	1.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5258	5258	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5259	5259	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5260	5260	812	t	1.0000	fijo	0.02	0.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5261	5261	812	t	1.0000	fijo	6.40	5.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5262	5262	812	t	1.0000	fijo	2.82	2.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5263	5263	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5264	5264	812	t	1.0000	fijo	2.78	2.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5265	5265	812	t	1.0000	fijo	1.20	1.02	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5266	5266	812	t	1.0000	fijo	2.10	1.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5267	5267	812	t	1.0000	fijo	4.84	4.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5268	5268	812	t	1.0000	fijo	3.00	2.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5269	5269	812	t	1.0000	fijo	6.24	5.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5270	5270	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5271	5271	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5272	5272	812	t	1.0000	fijo	2.19	1.86	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5273	5273	812	t	1.0000	fijo	0.89	0.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5274	5274	812	t	1.0000	fijo	3.40	2.88	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5275	5275	812	t	1.0000	fijo	1.30	1.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5276	5276	812	t	1.0000	fijo	3.30	2.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5277	5277	812	t	1.0000	fijo	3.30	2.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5278	5278	812	t	1.0000	fijo	10.15	8.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5279	5279	812	t	1.0000	fijo	0.00	0.00	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5280	5280	812	t	1.0000	fijo	8.00	6.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5281	5281	812	t	1.0000	fijo	4.30	3.64	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5282	5282	812	t	1.0000	fijo	10.08	8.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5283	5283	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5284	5284	812	t	1.0000	fijo	1.00	0.85	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5285	5285	812	t	1.0000	fijo	4.37	3.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5286	5286	812	t	1.0000	fijo	18.18	15.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5287	5287	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5288	5288	812	t	1.0000	fijo	35.00	29.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5289	5289	812	t	1.0000	fijo	0.06	0.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5290	5290	812	t	1.0000	fijo	0.22	0.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5291	5291	812	t	1.0000	fijo	0.18	0.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5292	5292	812	t	1.0000	fijo	0.09	0.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5293	5293	812	t	1.0000	fijo	0.25	0.21	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5294	5294	812	t	1.0000	fijo	10.43	8.84	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5295	5295	812	t	1.0000	fijo	15.92	13.49	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5296	5296	812	t	1.0000	fijo	2.06	1.75	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5297	5297	812	t	1.0000	fijo	4.11	3.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5298	5298	812	t	1.0000	fijo	9.50	8.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5299	5299	812	t	1.0000	fijo	1.11	0.94	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5300	5300	812	t	1.0000	fijo	1.82	1.54	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5301	5301	812	t	1.0000	fijo	2.01	1.70	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5302	5302	812	t	1.0000	fijo	6.05	5.13	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5303	5303	812	t	1.0000	fijo	9.65	8.18	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5304	5304	812	t	1.0000	fijo	5.50	4.66	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5305	5305	812	t	1.0000	fijo	3.58	3.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5306	5306	812	t	1.0000	fijo	1.79	1.52	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5307	5307	812	t	1.0000	fijo	2.21	1.87	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5308	5308	812	t	1.0000	fijo	23.51	19.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5309	5309	812	t	1.0000	fijo	39.49	33.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5310	5310	812	t	1.0000	fijo	32.98	27.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5311	5311	812	t	1.0000	fijo	20.00	16.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5312	5312	812	t	1.0000	fijo	9.99	8.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5313	5313	812	t	1.0000	fijo	17.00	14.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5314	5314	812	t	1.0000	fijo	9.70	8.22	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5315	5315	812	t	1.0000	fijo	12.70	10.76	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5316	5316	812	t	1.0000	fijo	13.70	11.61	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5317	5317	812	t	1.0000	fijo	12.00	10.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5318	5318	812	t	1.0000	fijo	15.10	12.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5319	5319	812	t	1.0000	fijo	12.50	10.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5320	5320	812	t	1.0000	fijo	6.60	5.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5321	5321	812	t	1.0000	fijo	32.00	27.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5322	5322	812	t	1.0000	fijo	13.40	11.36	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5323	5323	812	t	1.0000	fijo	33.50	28.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5324	5324	812	t	1.0000	fijo	8.40	7.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5325	5325	812	t	1.0000	fijo	4.80	4.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5326	5326	812	t	1.0000	fijo	5.81	4.92	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5327	5327	812	t	1.0000	fijo	0.09	0.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5328	5328	812	t	1.0000	fijo	1.12	0.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5329	5329	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5330	5330	812	t	1.0000	fijo	1.06	0.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5331	5331	812	t	1.0000	fijo	0.50	0.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5332	5332	812	t	1.0000	fijo	2.50	2.12	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5333	5333	812	t	1.0000	fijo	8.58	7.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5334	5334	812	t	1.0000	fijo	2.25	1.91	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5335	5335	812	t	1.0000	fijo	0.85	0.72	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5336	5336	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5337	5337	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5338	5338	812	t	1.0000	fijo	2.93	2.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5339	5339	812	t	1.0000	fijo	1.53	1.30	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5340	5340	812	t	1.0000	fijo	2.44	2.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5341	5341	812	t	1.0000	fijo	2.93	2.48	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5342	5342	812	t	1.0000	fijo	0.50	0.42	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5343	5343	812	t	1.0000	fijo	1.56	1.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5344	5344	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5345	5345	812	t	1.0000	fijo	0.80	0.68	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5346	5346	812	t	1.0000	fijo	0.37	0.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5347	5347	812	t	1.0000	fijo	0.20	0.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5348	5348	812	t	1.0000	fijo	1.12	0.95	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5349	5349	812	t	1.0000	fijo	2.14	1.81	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5350	5350	812	t	1.0000	fijo	1.59	1.35	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5351	5351	812	t	1.0000	fijo	2.12	1.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5352	5352	812	t	1.0000	fijo	1.89	1.60	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5353	5353	812	t	1.0000	fijo	0.67	0.57	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5354	5354	812	t	1.0000	fijo	0.70	0.59	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5355	5355	812	t	1.0000	fijo	1.50	1.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5356	5356	812	t	1.0000	fijo	3.22	2.73	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5357	5357	812	t	1.0000	fijo	5.40	4.58	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5358	5358	812	t	1.0000	fijo	2.58	2.19	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5359	5359	812	t	1.0000	fijo	2.40	2.03	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5360	5360	812	t	1.0000	fijo	4.81	4.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5361	5361	812	t	1.0000	fijo	3.60	3.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5362	5362	812	t	1.0000	fijo	2.10	1.78	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5363	5363	812	t	1.0000	fijo	3.60	3.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5364	5364	812	t	1.0000	fijo	8.77	7.43	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5365	5365	812	t	1.0000	fijo	16.50	13.98	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5366	5366	812	t	1.0000	fijo	0.65	0.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5367	5367	812	t	1.0000	fijo	16.69	14.14	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5368	5368	812	t	1.0000	fijo	83.90	71.10	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5369	5369	812	t	1.0000	fijo	14.50	12.29	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5370	5370	812	t	1.0000	fijo	0.65	0.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5371	5371	812	t	1.0000	fijo	1.81	1.53	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5372	5372	812	t	1.0000	fijo	5.01	4.25	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5373	5373	812	t	1.0000	fijo	5.99	5.08	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5374	5374	812	t	1.0000	fijo	3.72	3.15	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5375	5375	812	t	1.0000	fijo	12.12	10.27	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5376	5376	812	t	1.0000	fijo	21.72	18.41	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5377	5377	812	t	1.0000	fijo	7.16	6.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5378	5378	812	t	1.0000	fijo	11.00	9.32	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5379	5379	812	t	1.0000	fijo	9.90	8.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5380	5380	812	t	1.0000	fijo	3.30	2.80	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5381	5381	812	t	1.0000	fijo	2.88	2.44	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5382	5382	812	t	1.0000	fijo	4.00	3.39	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5383	5383	812	t	1.0000	fijo	2.42	2.05	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5384	5384	812	t	1.0000	fijo	7.92	6.71	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5385	5385	812	t	1.0000	fijo	3.62	3.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5386	5386	812	t	1.0000	fijo	8.91	7.55	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5387	5387	812	t	1.0000	fijo	3.91	3.31	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5388	5388	812	t	1.0000	fijo	12.00	10.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5389	5389	812	t	1.0000	fijo	14.60	12.37	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5390	5390	812	t	1.0000	fijo	9.32	7.90	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5391	5391	812	t	1.0000	fijo	0.20	0.17	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5392	5392	812	t	1.0000	fijo	14.24	12.07	t	2026-07-10 14:09:10	2026-07-10 14:09:10
5393	5393	812	t	1.0000	fijo	39544.47	39544.47	t	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.productos (id, empresa_id, categoria_id, codigo, nombre, descripcion, tipo, tipo_precio, precio_venta, precio_costo, imagen, activo, created_at, updated_at, incluye_igv, controla_stock, es_retornable) FROM stdin;
1	1	1	P-0001	Blusa básica blanca	\N	producto	fijo	45.00	24.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
2	1	1	P-0002	Blusa con encaje	\N	producto	fijo	65.00	35.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
3	1	1	P-0003	Vestido casual floreado	\N	producto	fijo	89.00	48.95	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
4	1	1	P-0004	Vestido coctel negro	\N	producto	fijo	120.00	66.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
4442	817	824	FERHC-0001	ABRAZADERA GALVANIZADA SIN FIN 5/8 C&A	\N	producto	fijo	0.26	0.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4443	817	824	FERHC-0002	ACCESORIO PARA WATER C/JALADOR BOYA NEGRA C&A	\N	producto	fijo	10.80	9.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4444	817	824	FERHC-0003	ACCESORIO PARA WATER C/PULSADOR C&A	\N	producto	fijo	14.55	12.33	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4445	817	824	FERHC-0004	ACEITE LUBRICANTE 30ML 3 EN 1	\N	producto	fijo	3.98	3.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4446	817	824	FERHC-0005	ACEITE LUBRICANTE 90ML 3 EN 1	\N	producto	fijo	6.77	5.74	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4447	817	824	FERHC-0006	ACEITE RP RIDER TOWN 4T 20W50 12 X 1LT REPSOL	\N	producto	fijo	17.50	14.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4448	817	824	FERHC-0007	ACIDO ESPECIAL KRIZZAL	\N	producto	fijo	3.37	2.86	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4449	817	824	FERHC-0008	ADAPTADOR CPVC 1/2 PAVCO	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4450	817	824	FERHC-0009	ADAPTADOR DE BRONCE 1/2 X 1 1/4 VALMAX	\N	producto	fijo	2.34	1.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4451	817	824	FERHC-0010	ADAPTADOR DE ENCHUFE UNIVERSAL SWIFT	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4452	817	824	FERHC-0011	ADAPTADOR ELECTRICO TRIPLE TIPO T HOME LIGHT	\N	producto	fijo	2.02	1.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4453	817	824	FERHC-0012	ALAMBRE PUAS X 200MT PRODAC	\N	producto	fijo	43.92	37.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4454	817	824	FERHC-0013	ALCAYATA 3 (56 UNID-0.50KG) VARIOS	\N	producto	fijo	0.12	0.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4455	817	824	FERHC-0014	ALICATE PRESION C/JEBE 10 PL ASAKI	\N	producto	fijo	11.00	9.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4456	817	824	FERHC-0015	APLICADOR SILICONA T/ESQUELETO TRUPER	\N	producto	fijo	7.58	6.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4457	817	824	FERHC-0016	ARCO DE SIERRA PROFESIONAL ALTA TENSION 12 TRUPER	\N	producto	fijo	37.51	31.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4458	817	824	FERHC-0017	Abrazadera 3/4 Luz S/M	\N	producto	fijo	0.20	0.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4459	817	824	FERHC-0018	Aceite P/Maquina Grande 60ml A-1 A-1	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4460	817	824	FERHC-0019	Adaptador Pvc 1 PAVCO	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4461	817	824	FERHC-0020	Adaptador Pvc 1 PLASTICA	\N	producto	fijo	1.10	0.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4462	817	824	FERHC-0021	Adaptador Pvc 1/2 PAVCO	\N	producto	fijo	0.86	0.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4463	817	824	FERHC-0022	Adaptador Pvc 1/2 PLASTICA	\N	producto	fijo	0.48	0.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4464	817	824	FERHC-0023	Adaptador Pvc 3/4 PAVCO	\N	producto	fijo	1.65	1.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4465	817	824	FERHC-0024	Adaptador Pvc 3/4 PLASTICA	\N	producto	fijo	0.55	0.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4466	817	824	FERHC-0025	Afirmado S/M	\N	producto	fijo	27.00	22.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4467	817	824	FERHC-0026	Alambre Galvanizado 16 VELKAS	\N	producto	fijo	7.30	6.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4468	817	824	FERHC-0027	Alambre Negro 08 PRODAC	\N	producto	fijo	3.04	2.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4469	817	824	FERHC-0028	Alambre Negro 16 PRODAC	\N	producto	fijo	3.04	2.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4470	817	824	FERHC-0029	Alambre Puas 200m C&A	\N	producto	fijo	31.40	26.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4471	817	824	FERHC-0030	Alambre Tw 14 INDECO	\N	producto	fijo	1.10	0.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4472	817	824	FERHC-0031	Alcohol 96░ LUCAS	\N	producto	fijo	8.50	7.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4473	817	824	FERHC-0032	Alicate Corte 6 C&A	\N	producto	fijo	7.78	6.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4474	817	824	FERHC-0033	Alicate Punta 6 C&A	\N	producto	fijo	6.76	5.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4475	817	824	FERHC-0034	Alicate Universal 8 KAMASA	\N	producto	fijo	9.40	7.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4476	817	824	FERHC-0035	Ambientador X Litro Surtido KRIZZAL	\N	producto	fijo	2.25	1.91	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4477	817	824	FERHC-0036	Anillo De Cera C/G METUSA	\N	producto	fijo	4.07	3.45	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4478	817	824	FERHC-0037	Aplicador Silicona T/Esqueleto C&A	\N	producto	fijo	3.78	3.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4479	817	824	FERHC-0038	Arco De Sierra 12pl M/Plastico C&A	\N	producto	fijo	5.72	4.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4480	817	824	FERHC-0039	Arena Amarilla S/M	\N	producto	fijo	38.00	32.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4481	817	824	FERHC-0040	Arenilla Fina Por Lata S/M	\N	producto	fijo	0.35	0.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4482	817	824	FERHC-0041	Arenilla S/M	\N	producto	fijo	20.00	16.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4483	817	824	FERHC-0042	Armella Cerrada 1 1/2 Pl S/M	\N	producto	fijo	0.20	0.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4484	817	824	FERHC-0043	Armella Cerrada 1 Pl S/M	\N	producto	fijo	0.17	0.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4485	817	824	FERHC-0044	BADILEJO M/GOMA 6 KAMASA	\N	producto	fijo	4.81	4.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4486	817	824	FERHC-0045	BADILEJO M/MADERA 6 C&A	\N	producto	fijo	2.76	2.34	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4487	817	824	FERHC-0046	BADILEJO M/MADERA 7 C&A	\N	producto	fijo	2.57	2.18	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4488	817	824	FERHC-0047	BISAGRA 1 1/2 BISA	\N	producto	fijo	0.76	0.64	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4489	817	824	FERHC-0048	BORNES PARA BATERIA KAMASA	\N	producto	fijo	3.50	2.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4490	817	824	FERHC-0049	BROCHA DE NYLON M/MADERA 1 TUMI	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4491	817	824	FERHC-0050	BROCHA DE NYLON M/PLASTICO 1 COPERSA	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4492	817	824	FERHC-0051	BROCHA DE NYLON M/PLASTICO 11/2 COPERSA	\N	producto	fijo	1.20	1.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4493	817	824	FERHC-0052	BROCHA DE NYLON M/PLASTICO 21/2 COPERSA	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4494	817	824	FERHC-0053	BROCHA DE NYLON M/PLASTICO 3 COPERSA	\N	producto	fijo	2.40	2.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4495	817	824	FERHC-0054	Base Zincromato 1/4 Galon VELSALIT	\N	producto	fijo	12.50	10.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4496	817	824	FERHC-0055	Bisagra 2 1/2 BISA	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4497	817	824	FERHC-0056	Bisagra 2 BISA	\N	producto	fijo	0.79	0.67	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4498	817	824	FERHC-0057	Bisagra 3 1/2 BISA	\N	producto	fijo	1.42	1.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4499	817	824	FERHC-0058	Bisagra 3 BISA	\N	producto	fijo	1.16	0.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4500	817	824	FERHC-0059	Bisagra 4 BISA	\N	producto	fijo	1.99	1.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4501	817	824	FERHC-0060	Bisagra Fija Aluminizada 4 ALUMINIZADA	\N	producto	fijo	4.41	3.74	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4502	817	824	FERHC-0061	Broca Para Concreto 1/2-13mm VARIOS	\N	producto	fijo	5.81	4.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4503	817	824	FERHC-0062	Broca Para Concreto 1/4-6.5mm VARIOS	\N	producto	fijo	2.60	2.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4504	817	824	FERHC-0063	Broca Para Concreto 1/8-3mm VARIOS	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4505	817	824	FERHC-0064	Broca Para Concreto 3/16-5mm VARIOS	\N	producto	fijo	2.30	1.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4506	817	824	FERHC-0065	Broca Para Concreto 3/8-10mm VARIOS	\N	producto	fijo	3.25	2.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4507	817	824	FERHC-0066	Broca Para Concreto 5/16-8mm VARIOS	\N	producto	fijo	3.16	2.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4508	817	824	FERHC-0067	Broca Para Concreto 5/32-4mm VARIOS	\N	producto	fijo	1.22	1.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4509	817	824	FERHC-0068	Broca Para Fierro Hss 1/16 VARIOS	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4510	817	824	FERHC-0069	Broca Para Fierro Hss 1/2 VARIOS	\N	producto	fijo	9.59	8.13	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4511	817	824	FERHC-0070	Broca Para Fierro Hss 1/32 VARIOS	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4512	817	824	FERHC-0071	Broca Para Fierro Hss 1/4 VARIOS	\N	producto	fijo	2.03	1.72	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4513	817	824	FERHC-0072	Broca Para Fierro Hss 3/32 VARIOS	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4514	817	824	FERHC-0073	Broca Para Fierro Hss 3/8 VARIOS	\N	producto	fijo	4.51	3.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4515	817	824	FERHC-0074	Broca Para Fierro Hss 5/16 VARIOS	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4516	817	824	FERHC-0075	Broca Para Fierro Hss 5/32 VARIOS	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4517	817	824	FERHC-0076	Brocha De Nylon M/Madera 1 C&A	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4518	817	824	FERHC-0077	Brocha De Nylon M/Madera 1/2 C&A	\N	producto	fijo	0.54	0.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4519	817	824	FERHC-0078	Brocha De Nylon M/Madera 2 1/2 C&A	\N	producto	fijo	1.98	1.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4520	817	824	FERHC-0079	Brocha De Nylon M/Madera 2 C&A	\N	producto	fijo	1.57	1.33	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4521	817	824	FERHC-0080	Brocha De Nylon M/Madera 3 C&A	\N	producto	fijo	2.62	2.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4522	817	824	FERHC-0081	Brocha De Nylon M/Madera 3/4 C&A	\N	producto	fijo	0.78	0.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4523	817	824	FERHC-0082	Brocha De Nylon M/Madera 4 C&A	\N	producto	fijo	3.47	2.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4524	817	824	FERHC-0083	Brocha De Nylon M/Madera 5 C&A	\N	producto	fijo	4.44	3.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4525	817	824	FERHC-0084	Bushing Pvc 1 A 1/2 INYECTOPLAST	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4526	817	824	FERHC-0085	Bushing Pvc 1 A 3/4 INYECTOPLAST	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4527	817	824	FERHC-0086	Bushing Pvc 1/2 A 1/2 TRANSFORMADO	\N	producto	fijo	0.73	0.62	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4528	817	824	FERHC-0087	Bushing Pvc 3/4 A 1/2 INYECTOPLAST	\N	producto	fijo	0.60	0.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4529	817	824	FERHC-0088	CABEZA DE DUCHA CROMADA CIRCULAR 6 C&A	\N	producto	fijo	17.12	14.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4530	817	824	FERHC-0089	CABLE THW-90 + PLUS, 12 AWG INDECO	\N	producto	fijo	2.17	1.84	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4531	817	824	FERHC-0090	CABLE THW-90 + PLUS, 14 AWG INDECO	\N	producto	fijo	1.68	1.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4532	817	824	FERHC-0091	CAJA 12 POLOS P/EMPOTRAR KBA	\N	producto	fijo	20.21	17.13	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4533	817	824	FERHC-0092	CAJA CONCRETO DESAGUE BASE S/M	\N	producto	fijo	8.96	7.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4534	817	824	FERHC-0093	CAJA CONCRETO DESAGUE INTERMEDIA S/M	\N	producto	fijo	9.00	7.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4535	817	824	FERHC-0094	CAJA CONCRETO DESAGUE PESTAÐA S/M	\N	producto	fijo	9.17	7.77	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4536	817	824	FERHC-0095	CAJA CONCRETO DESAGUE TAPA S/M	\N	producto	fijo	8.50	7.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4537	817	824	FERHC-0096	CAJA RECTANGULAR UNIVERSAL PARA SOBREPONER HOME LIGHT	\N	producto	fijo	2.43	2.06	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4538	817	824	FERHC-0097	CAL SACO POR 30 KG CAL	\N	producto	fijo	7.00	5.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4539	817	824	FERHC-0098	CALAMINA 0.14 X 3.60 X 0.80 ACEROS AREQUIPA	\N	producto	fijo	12.81	10.86	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4540	817	824	FERHC-0099	CALAMINA 0.22X3.60X0.80 PRODAC	\N	producto	fijo	20.66	17.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4541	817	824	FERHC-0100	CANALETA 10X20 (3/4) HOME LIGHT	\N	producto	fijo	1.14	0.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4542	817	824	FERHC-0101	CARETA PARA SOLDA /CABECERA AMARILLA C&A	\N	producto	fijo	11.81	10.01	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4543	817	824	FERHC-0102	CARRETILLA RHINO AMARILLA RHINO	\N	producto	fijo	100.00	84.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4544	817	824	FERHC-0103	CARRETILLA T/BUGGY C/LLANTA REF T/HOJA AZUL 5.5P C&A	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4545	817	824	FERHC-0104	CAÐO JARDINERO CIM	\N	producto	fijo	30.80	26.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4546	817	824	FERHC-0105	CAÐO JARDINERO PVC BLANCO TOSISAC	\N	producto	fijo	1.85	1.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4547	817	824	FERHC-0106	CEMENTO ROJO MOCHICA	\N	producto	fijo	29.35	24.87	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4548	817	824	FERHC-0107	CEMENTO ROJO PACASMAYO	\N	producto	fijo	30.60	25.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4549	817	824	FERHC-0108	CERA ROJA SILICONEADA X 1LT LUCAS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4550	817	824	FERHC-0109	CERRADURA INTERIOR BOLA WINGS	\N	producto	fijo	9.50	8.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4551	817	824	FERHC-0110	CERROJO N1 31/2 PL SANSON SANSON	\N	producto	fijo	1.40	1.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4552	817	824	FERHC-0111	CERROJO N2 5 PL SANSON SANSON	\N	producto	fijo	1.75	1.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4553	817	824	FERHC-0112	CHALECO POLIESTER NARANJA C&A	\N	producto	fijo	3.13	2.65	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4554	817	824	FERHC-0113	CHAPA BOLA CROMADO C&A	\N	producto	fijo	10.93	9.26	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4555	817	824	FERHC-0114	CINTA AISLANTE 3M 155 GRANDE 3M	\N	producto	fijo	3.53	2.99	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4556	817	824	FERHC-0115	CINTA AISLANTE 3M 155 PEQUEÐO 3M	\N	producto	fijo	1.63	1.38	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4557	817	824	FERHC-0116	CINTA AISLANTE 3M 165 19MMX18.3M AMARILLO 3M	\N	producto	fijo	4.31	3.65	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4558	817	824	FERHC-0117	CINTA AISLANTE 3M 165 19MMX18.3M AZUL 3M	\N	producto	fijo	4.31	3.65	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4559	817	824	FERHC-0118	CINTA EMBALAJE 2 X 200 YDS KNAUF	\N	producto	fijo	6.18	5.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5	1	1	P-0005	Jean skinny	\N	producto	fijo	75.00	41.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
6	1	1	P-0006	Pantalón palazzo	\N	producto	fijo	70.00	38.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
7	1	1	P-0007	Falda midi	\N	producto	fijo	60.00	33.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
8	1	1	P-0008	Short denim	\N	producto	fijo	50.00	27.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
9	1	1	P-0009	Polo oversize	\N	producto	fijo	55.00	30.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
10	1	1	P-0010	Chompa de hilo	\N	producto	fijo	95.00	52.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
11	1	1	P-0011	Cardigan tejido	\N	producto	fijo	110.00	60.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
12	1	1	P-0012	Pijama 2 piezas	\N	producto	fijo	65.00	35.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
13	1	2	P-0013	Cartera bandolera	\N	producto	fijo	89.00	48.95	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
14	1	2	P-0014	Cartera tote grande	\N	producto	fijo	120.00	66.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
15	1	2	P-0015	Mochila mini cuero	\N	producto	fijo	95.00	52.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
16	1	2	P-0016	Billetera cuero sintético	\N	producto	fijo	45.00	24.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
17	1	2	P-0017	Cinturón delgado dorado	\N	producto	fijo	35.00	19.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
18	1	2	P-0018	Lentes de sol cat-eye	\N	producto	fijo	55.00	30.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
19	1	2	P-0019	Lentes de sol aviador	\N	producto	fijo	60.00	33.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
20	1	2	P-0020	Pañuelo de seda	\N	producto	fijo	40.00	22.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
21	1	2	P-0021	Sombrero de playa	\N	producto	fijo	50.00	27.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
22	1	2	P-0022	Cangurera bandolera	\N	producto	fijo	65.00	35.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
23	1	3	P-0023	Sandalias planas	\N	producto	fijo	55.00	30.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
24	1	3	P-0024	Tacones nude	\N	producto	fijo	110.00	60.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
25	1	3	P-0025	Zapatillas blancas	\N	producto	fijo	145.00	79.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
26	1	3	P-0026	Botines tobilleros	\N	producto	fijo	130.00	71.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
27	1	3	P-0027	Pantuflas peluche	\N	producto	fijo	35.00	19.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
28	1	4	P-0028	Labial mate rojo	\N	producto	fijo	28.00	15.40	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
29	1	4	P-0029	Labial mate nude	\N	producto	fijo	28.00	15.40	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
30	1	4	P-0030	Brillo labial transparente	\N	producto	fijo	22.00	12.10	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
31	1	4	P-0031	Base líquida tono claro	\N	producto	fijo	55.00	30.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
32	1	4	P-0032	Polvo compacto	\N	producto	fijo	45.00	24.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
33	1	4	P-0033	Paleta sombras 12 colores	\N	producto	fijo	75.00	41.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
34	1	4	P-0034	Rímel volumen	\N	producto	fijo	35.00	19.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
35	1	4	P-0035	Delineador líquido	\N	producto	fijo	25.00	13.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
36	1	4	P-0036	Brocha kabuki	\N	producto	fijo	30.00	16.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
37	1	4	P-0037	Set 5 brochas maquillaje	\N	producto	fijo	65.00	35.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
38	1	5	P-0038	Perfume floral 100ml	\N	producto	fijo	89.00	48.95	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
39	1	5	P-0039	Perfume fresh 50ml	\N	producto	fijo	55.00	30.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
40	1	5	P-0040	Crema hidratante facial	\N	producto	fijo	45.00	24.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
41	1	5	P-0041	Mascarillas carbón x10	\N	producto	fijo	30.00	16.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
42	1	5	P-0042	Aceite capilar de argán	\N	producto	fijo	50.00	27.50	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
43	1	6	P-0043	Plancha cerámica	\N	producto	fijo	180.00	99.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
44	1	6	P-0044	Secadora 1800W	\N	producto	fijo	220.00	121.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
45	1	6	P-0045	Rizador cerámico	\N	producto	fijo	145.00	79.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
46	1	6	P-0046	Set 10 ligas para cabello	\N	producto	fijo	18.00	9.90	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
47	1	7	P-0047	Set aretes argollas x6	\N	producto	fijo	35.00	19.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
48	1	7	P-0048	Collar gargantilla dorado	\N	producto	fijo	45.00	24.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
49	1	7	P-0049	Pulsera cuero trenzado	\N	producto	fijo	28.00	15.40	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
50	1	7	P-0050	Reloj minimalista mujer	\N	producto	fijo	75.00	41.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
51	1	8	P-0051	Audífonos bluetooth	\N	producto	fijo	65.00	35.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
52	1	8	P-0052	Cargador USB-C 20W	\N	producto	fijo	35.00	19.25	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
53	1	8	P-0053	Soporte para celular	\N	producto	fijo	25.00	13.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
54	1	9	S-0001	Asesoría de imagen	\N	servicio	fijo	80.00	0.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	f	f
55	1	9	S-0002	Maquillaje para evento	\N	servicio	fijo	120.00	0.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	f	f
305	1	322	FER-001	Ladrillo King Kong 18 huecos	\N	producto	fijo	1.10	0.85	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
306	1	322	FER-002	Ladrillo Pandereta	\N	producto	fijo	0.75	0.58	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
307	1	322	FER-003	Ladrillo de Techo 15	\N	producto	fijo	2.40	1.90	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
308	1	322	FER-004	Cemento Pacasmayo Tipo I x 42.5 kg	\N	producto	fijo	29.90	26.50	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
309	1	322	FER-005	Fierro corrugado 1/2" x 9m Aceros Arequipa	\N	producto	fijo	36.50	32.80	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
310	1	322	FER-006	Fierro corrugado 3/8" x 9m Aceros Arequipa	\N	producto	fijo	21.00	18.90	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
311	1	322	FER-007	Alambre N°16 Prodac (kg)	\N	producto	fijo	5.50	4.20	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
312	1	322	FER-008	Alambre N°8 Prodac (kg)	\N	producto	fijo	5.20	4.10	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
313	1	322	FER-009	Clavos 2 1/2" (kg)	\N	producto	fijo	5.00	3.90	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
314	1	322	FER-010	Calamina galvanizada 0.22 x 3.6m	\N	producto	fijo	33.00	28.50	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
315	1	322	FER-011	Arena gruesa (m³)	\N	producto	fijo	55.00	38.00	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
316	1	322	FER-012	Piedra chancada 1/2" (m³)	\N	producto	fijo	70.00	52.00	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	t	t	f
4560	817	824	FERHC-0119	CINTA REFLECTIVA BLANCO/ ROJO 2 PLG KNAUF	\N	producto	fijo	1.83	1.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4561	817	824	FERHC-0120	CLAVO ACERO 1 1/2-3.5X50MM CAJ-1KG (416UNI) VARIOS	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4562	817	824	FERHC-0121	CLAVO ACERO 1(CAJA 345 X 1KG) VARIOS	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4563	817	824	FERHC-0122	CLAVO ACERO 2 1/2-3.5X60MM CAJ-0.50KG(72 UNID) VARIOS	\N	producto	fijo	0.07	0.06	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4564	817	824	FERHC-0123	CLAVO ACERO 2-3.5X50MM CAJ-1KG (247UNI) VARIOS	\N	producto	fijo	0.04	0.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4565	817	824	FERHC-0124	CLAVO ACERO 3-4.3X75MM CAJ-1KG (104UNI) VARIOS	\N	producto	fijo	0.09	0.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4566	817	824	FERHC-0125	CLAVO P/MADERA 1 1/2 CONFER CONFER	\N	producto	fijo	4.40	3.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4567	817	824	FERHC-0126	CLAVO P/MADERA 5 CONFER CONFER	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4568	817	824	FERHC-0127	CLAVO PARA CALAMINA C/ARANDELA C&A	\N	producto	fijo	4.92	4.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4569	817	824	FERHC-0128	CODO PVC 1 SP PLASTICA	\N	producto	fijo	1.75	1.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4570	817	824	FERHC-0129	CODO PVC 1/2 SP PLASTICA	\N	producto	fijo	0.74	0.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4571	817	824	FERHC-0130	CODO PVC MIXTO 1/2 PLASTICA	\N	producto	fijo	0.64	0.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4572	817	824	FERHC-0131	CODO PVC MIXTO 3/4 PAVCO	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4573	817	824	FERHC-0132	CODO SAL 4 PAVCO	\N	producto	fijo	6.84	5.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4574	817	824	FERHC-0133	CODO SAL 4 PLASTICA	\N	producto	fijo	3.56	3.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4575	817	824	FERHC-0134	COLA SINTETICA 1 KG LOSARO	\N	producto	fijo	6.25	5.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4576	817	824	FERHC-0135	COLA SINTETICA 1/2 KG VELSALIT	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4577	817	824	FERHC-0136	COMBA C/MANGO FIBRA DE VIDRIO 8LBS C&A	\N	producto	fijo	32.83	27.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4578	817	824	FERHC-0137	CORDON VULCANIZADO 2X12 NMT(SJT-0) INDECO INDECO	\N	producto	fijo	5.66	4.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4579	817	824	FERHC-0138	CRUZETA 2 MM 100UND VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4580	817	824	FERHC-0139	CRUZETAS 1 MM 100UND VARIOS	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4581	817	824	FERHC-0140	CUCHILLO CARTONERO CUTER EUROTOOLS	\N	producto	fijo	0.50	0.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4582	817	824	FERHC-0141	CURVA LUZ SAP 1 PAVCO	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4583	817	824	FERHC-0142	Cabeza Ducha Cromada Peque±a C&A	\N	producto	fijo	8.58	7.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4584	817	824	FERHC-0143	Cabeza Ducha pvc grande Completa S/M	\N	producto	fijo	2.12	1.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4585	817	824	FERHC-0144	Cable Mellizo 2X18 INDECO	\N	producto	fijo	1.23	1.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4586	817	824	FERHC-0145	Cable Mellizo 2x16 INDECO	\N	producto	fijo	2.25	1.91	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4587	817	824	FERHC-0146	Caja 2 Polos P/Empotrar KBA	\N	producto	fijo	4.42	3.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4588	817	824	FERHC-0147	Caja 2 Polos P/Empotrar XACE	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4589	817	824	FERHC-0148	Caja 4 Polos P/Empotrar KBA	\N	producto	fijo	10.40	8.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4590	817	824	FERHC-0149	Caja 6 Polos P/Empotrar KBA	\N	producto	fijo	11.75	9.96	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4591	817	824	FERHC-0150	Caja 8 Polos P/Empotrar KBA	\N	producto	fijo	15.51	13.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4592	817	824	FERHC-0151	Caja Concreto Para Agua CONCRETO	\N	producto	fijo	13.00	11.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4593	817	824	FERHC-0152	Caja De Pase 100 X 100 X 70 XACE	\N	producto	fijo	4.01	3.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4594	817	824	FERHC-0153	Caja De Pase 150 X 150 X 80 XACE	\N	producto	fijo	6.50	5.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4595	817	824	FERHC-0154	Caja De Pase 200 X 200 X 80 XACE	\N	producto	fijo	13.99	11.86	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4596	817	824	FERHC-0155	Caja De Pase Liso 10.2 X 10.2 X 5.5 STECK	\N	producto	fijo	5.70	4.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4597	817	824	FERHC-0156	Caja De Pase Liso 23.4 X 17.4 X 9 STECK	\N	producto	fijo	18.01	15.26	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4598	817	824	FERHC-0157	Caja Octagonal AMERICA	\N	producto	fijo	0.38	0.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4599	817	824	FERHC-0158	Caja Octagonal PAVCO	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4600	817	824	FERHC-0159	Caja Piramide P/Cuchilla Sobreponer 2 Polos S/M	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4601	817	824	FERHC-0160	Caja Rectangular AMERICA	\N	producto	fijo	0.33	0.28	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4602	817	824	FERHC-0161	Caja Rectangular PAVCO	\N	producto	fijo	1.45	1.23	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4603	817	824	FERHC-0162	Calamina 0.14x1.80x0.80 PRODAC	\N	producto	fijo	7.00	5.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4604	817	824	FERHC-0163	Calamina Traslucida Gran Onda TECHITO	\N	producto	fijo	82.94	70.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4605	817	824	FERHC-0164	Calamina Traslucida Perfil 4 TECHITO	\N	producto	fijo	31.00	26.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4606	817	824	FERHC-0165	Calamina Traslusida onda 76 1.80x0.84 Liviana TECHITO	\N	producto	fijo	17.50	14.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4607	817	824	FERHC-0166	Calamina Traslusida onda 76 3.60x0.84 Liviana TECHITO	\N	producto	fijo	35.20	29.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4608	817	824	FERHC-0167	Camara P/Llanta Carretilla TRUPER	\N	producto	fijo	7.50	6.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4609	817	824	FERHC-0168	Canaleta 10x15 HOME LIGHT	\N	producto	fijo	1.09	0.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4610	817	824	FERHC-0169	Candado Dorado 32 Mm ECONOMICA	\N	producto	fijo	2.28	1.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4611	817	824	FERHC-0170	Candado Dorado 38mm ECONOMICA	\N	producto	fijo	2.84	2.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4612	817	824	FERHC-0171	Candado Dorado 50mm ECONOMICA	\N	producto	fijo	3.96	3.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4613	817	824	FERHC-0172	Candado Dorado 63 Mm ECONOMICA	\N	producto	fijo	5.97	5.06	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4614	817	824	FERHC-0173	Capuchon Pvc Rojo VARIOS	\N	producto	fijo	0.09	0.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4615	817	824	FERHC-0174	Ca±o Botadero Bronceado Liso C&A	\N	producto	fijo	4.91	4.16	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4616	817	824	FERHC-0175	Ca±o Botadero M/Redondo Metal C&A	\N	producto	fijo	4.44	3.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4617	817	824	FERHC-0176	Ca±o Jardinero Bronceado Liso C&A	\N	producto	fijo	5.78	4.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4618	817	824	FERHC-0177	Ca±o Jardinero M/Rojo Metal C&A	\N	producto	fijo	6.94	5.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4619	817	824	FERHC-0178	Ca±o Jardinero Naranja PCP	\N	producto	fijo	14.40	12.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4620	817	824	FERHC-0179	Ca±o P/Lavanderia 1/2 C&A	\N	producto	fijo	8.97	7.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4621	817	824	FERHC-0180	Cemento Azul Antisalitre PACASMAYO	\N	producto	fijo	33.21	28.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4622	817	824	FERHC-0181	Cemento Blanco KOLORCIX	\N	producto	fijo	2.04	1.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4623	817	824	FERHC-0182	Cemento VARIOS	\N	producto	fijo	0.60	0.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4624	817	824	FERHC-0183	Cerradura 226 FORTE	\N	producto	fijo	65.01	55.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4625	817	824	FERHC-0184	Cerradura 240 FORTE	\N	producto	fijo	64.88	54.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4626	817	824	FERHC-0185	Cerradura 444 Barra TRAVEX	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4627	817	824	FERHC-0186	Cerrojo N4 8pl SANSON	\N	producto	fijo	5.66	4.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4628	817	824	FERHC-0187	Cinta Aislante Grande TECNOFAN	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4629	817	824	FERHC-0188	Cinta Aislante Peque±o TECNOFAN	\N	producto	fijo	0.96	0.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4630	817	824	FERHC-0189	Cinta Masketing 1 PEGAFAN	\N	producto	fijo	2.99	2.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4631	817	824	FERHC-0190	Cinta Masketing 1/2 PEGAFAN	\N	producto	fijo	1.53	1.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4632	817	824	FERHC-0191	Cinta Masketing 2 PEGAFAN	\N	producto	fijo	5.76	4.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4633	817	824	FERHC-0192	Cinta Masketing 3/4 PEGAFAN	\N	producto	fijo	2.10	1.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4634	817	824	FERHC-0193	Cinta P/Medir Plastica 50 Metros ASAKI	\N	producto	fijo	17.50	14.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4635	817	824	FERHC-0194	Cinta Teflon 1/2 X 12m C&A	\N	producto	fijo	0.34	0.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4636	817	824	FERHC-0195	Cinta Teflon P/Gas Amarilla 1/2 Magnun MAGNUN	\N	producto	fijo	0.74	0.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4637	817	824	FERHC-0196	Cizalla 12 M/Tubular C&A C&A	\N	producto	fijo	17.41	14.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4638	817	824	FERHC-0197	Cizalla 18 M/Tubular C&A C&A	\N	producto	fijo	22.04	18.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4639	817	824	FERHC-0198	Clavo Acero 4-4.5X100mm Caj X 81unid X Kg VARIOS	\N	producto	fijo	0.13	0.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4640	817	824	FERHC-0199	Clavo Acero 5 P Caj X 1 Kg 58 Unid VARIOS	\N	producto	fijo	0.22	0.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4641	817	824	FERHC-0200	Clavo P/Madera 1 Confer CONFER	\N	producto	fijo	4.80	4.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4642	817	824	FERHC-0201	Clavo P/Madera 2 1/2 Confer CONFER	\N	producto	fijo	3.43	2.91	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4643	817	824	FERHC-0202	Clavo P/Madera 2 Confer CONFER	\N	producto	fijo	3.60	3.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4644	817	824	FERHC-0203	Clavo P/Madera 3 Confer CONFER	\N	producto	fijo	3.67	3.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4645	817	824	FERHC-0204	Clavo P/Madera 4 Confer CONFER	\N	producto	fijo	3.67	3.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4646	817	824	FERHC-0205	Clavo P/Madera 6 Confer CONFER	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4647	817	824	FERHC-0206	Clavo P/Madera 7 Confer CONFER	\N	producto	fijo	5.89	4.99	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4648	817	824	FERHC-0207	Clavo Para Calamina C&A C&A	\N	producto	fijo	5.61	4.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4649	817	824	FERHC-0208	Codo Bronce 1/2 VALMAX	\N	producto	fijo	2.38	2.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4650	817	824	FERHC-0209	Codo Cpvc 1/2 Sp PAVCO	\N	producto	fijo	0.85	0.72	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4651	817	824	FERHC-0210	Codo Cpvc 3/4 Sp PAVCO	\N	producto	fijo	2.12	1.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4652	817	824	FERHC-0211	Codo Fierro G. 1 FIERRO G	\N	producto	fijo	2.90	2.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4653	817	824	FERHC-0212	Codo Fierro G. 1/2 FIERRO G	\N	producto	fijo	1.40	1.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4654	817	824	FERHC-0213	Codo Fierro G. 3/4 FIERRO G	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4655	817	824	FERHC-0214	Codo Pvc 1 C/Rosca PAVCO	\N	producto	fijo	4.45	3.77	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4656	817	824	FERHC-0215	Codo Pvc 1 C/Rosca PLASTICA	\N	producto	fijo	2.24	1.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4657	817	824	FERHC-0216	Codo Pvc 1 Sp PAVCO	\N	producto	fijo	2.90	2.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4658	817	824	FERHC-0217	Codo Pvc 1/2 C/Rosca NICOL	\N	producto	fijo	0.50	0.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4659	817	824	FERHC-0218	Codo Pvc 1/2 C/Rosca PAVCO	\N	producto	fijo	1.55	1.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4660	817	824	FERHC-0219	Codo Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.40	1.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4661	817	824	FERHC-0220	Codo Pvc 3/4 C/Rosca PAVCO	\N	producto	fijo	2.90	2.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4662	817	824	FERHC-0221	Codo Pvc 3/4 C/Rosca PLASTICA	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4663	817	824	FERHC-0222	Codo Pvc 3/4 SP PLASTICA	\N	producto	fijo	1.22	1.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4664	817	824	FERHC-0223	Codo Pvc 3/4 Sp PAVCO	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4665	817	824	FERHC-0224	Codo Pvc Mixto 1 INYECTOPLAST	\N	producto	fijo	1.60	1.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4666	817	824	FERHC-0225	Codo Pvc Mixto 1/2 PAVCO	\N	producto	fijo	1.56	1.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4667	817	824	FERHC-0226	Codo Sal 2 PAVCO	\N	producto	fijo	1.66	1.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4668	817	824	FERHC-0227	Codo Sal 2 PLASTICA	\N	producto	fijo	0.86	0.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4669	817	824	FERHC-0228	Codo Sal 3 PAVCO	\N	producto	fijo	5.35	4.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4670	817	824	FERHC-0229	Codo Sal 3 PLASTICA	\N	producto	fijo	3.17	2.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4671	817	824	FERHC-0230	Codo Sal 4x2 PAVCO	\N	producto	fijo	9.18	7.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4672	817	824	FERHC-0231	Codo Sal 4x2 PLASTICA	\N	producto	fijo	5.30	4.49	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4673	817	824	FERHC-0232	Cola Sintetica Clasica TEKNOCOLA	\N	producto	fijo	9.52	8.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4674	817	824	FERHC-0233	Cola Sintetica VELSALIT	\N	producto	fijo	4.84	4.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4675	817	824	FERHC-0234	Comba C/Mango Madera 4lbs C&A	\N	producto	fijo	15.16	12.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4676	817	824	FERHC-0235	Comba De Goma 500 Gr M/Madera VARIOS	\N	producto	fijo	7.00	5.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4677	817	824	FERHC-0236	Curva Sel 1 PAVCO	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4678	817	824	FERHC-0237	Curva Sel 3/4 PAVCO	\N	producto	fijo	0.31	0.26	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4679	817	824	FERHC-0238	Curva Sel 3/4 PLASTICA	\N	producto	fijo	0.19	0.16	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4680	817	824	FERHC-0239	Curva Sel 5/8 PAVCO	\N	producto	fijo	0.35	0.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4681	817	824	FERHC-0240	DESARMADOR REVERSIBLE 5 X 70 C&A	\N	producto	fijo	0.34	0.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4682	817	824	FERHC-0241	DESARMADOR REVERSIBLE 6X35 C&A	\N	producto	fijo	1.09	0.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4683	817	824	FERHC-0242	DESARMADOR REVERSIBLE M/ERGON 6 X 100MM C&A	\N	producto	fijo	3.30	2.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4684	817	824	FERHC-0243	DIAFRACMA O SAPITO SANY	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4685	817	824	FERHC-0244	DISCO CORTE FIERRO 4 1/2 3M	\N	producto	fijo	3.10	2.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4686	817	824	FERHC-0245	DISCO CORTE MADERA 4 1/2 24T KAMASA	\N	producto	fijo	4.92	4.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4687	817	824	FERHC-0246	DISCO CORTE MADERA 4 1/2 40T KAMASA	\N	producto	fijo	6.47	5.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4688	817	824	FERHC-0247	DISCO CORTE MADERA 7 24T KAMASA	\N	producto	fijo	10.50	8.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4689	817	824	FERHC-0248	DISCO DE LIJAS FLAP PULIR P60 VARGYOV	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4690	817	824	FERHC-0249	DISCO PLATO C/LIJA 4 1/2 VARGYOV	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4691	817	824	FERHC-0250	DISCO TRONZADORA 14 DEWALT	\N	producto	fijo	14.50	12.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4692	817	824	FERHC-0251	DRIZA POLIPROPILENO 1/4 AZUL(42MT X 5KG) C&A	\N	producto	fijo	0.32	0.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4693	817	824	FERHC-0252	DRIZA POLIPROPILENO 1/8 AZUL (196 MTS1KG) C&A	\N	producto	fijo	0.11	0.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4694	817	824	FERHC-0253	DRIZA POLIPROPILENO 5/16 BLANCO C&A	\N	producto	fijo	0.61	0.52	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4695	817	824	FERHC-0254	DRIZA POLIPROPILENO 7/16 BLANCO C&A	\N	producto	fijo	0.89	0.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4696	817	824	FERHC-0255	Desague Para Lavadero Con Coleta Pvc PICETTI	\N	producto	fijo	7.00	5.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4697	817	824	FERHC-0256	Desarmador Reversible 5 X 75 C&A	\N	producto	fijo	0.85	0.72	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4698	817	824	FERHC-0257	Desarmador Reversible 5x65 WINGS	\N	producto	fijo	1.11	0.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4699	817	824	FERHC-0258	Desarmador Reversible 6 X 90 C&A	\N	producto	fijo	1.12	0.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4700	817	824	FERHC-0259	Desatorador Para Water S/M	\N	producto	fijo	2.38	2.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4701	817	824	FERHC-0260	Disco Corte Concreto 4 1/2 Continuo KAMASA	\N	producto	fijo	5.39	4.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4702	817	824	FERHC-0261	Disco Corte Concreto 4 1/2 KAMASA	\N	producto	fijo	5.70	4.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4703	817	824	FERHC-0262	Disco Corte Concreto 7 KAMASA	\N	producto	fijo	14.92	12.64	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4704	817	824	FERHC-0263	Disco Corte Fierro 4 1/2 DEWALT	\N	producto	fijo	2.60	2.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4705	817	824	FERHC-0264	Disco Corte Fierro 4 1/2 NORTON	\N	producto	fijo	2.19	1.86	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4706	817	824	FERHC-0265	Disco Corte Fierro 7 3M	\N	producto	fijo	4.85	4.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4707	817	824	FERHC-0266	Disco Corte Fierro 7 DEWALT	\N	producto	fijo	4.68	3.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4708	817	824	FERHC-0267	Disco Desbaste 4 1/2 DEWALT	\N	producto	fijo	3.68	3.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4709	817	824	FERHC-0268	Disco Desbaste 7 DEWALT	\N	producto	fijo	7.80	6.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4710	817	824	FERHC-0269	ENCHUFE INDUSTRIAL C/TIERRA HOME LIGHT	\N	producto	fijo	1.43	1.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4711	817	824	FERHC-0270	ENCHUFE INDUSTRIAL S/TIERRA HOME LIGHT	\N	producto	fijo	2.11	1.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4712	817	824	FERHC-0271	ESCOBILLON 41CM HUDE	\N	producto	fijo	11.56	9.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4713	817	824	FERHC-0272	ESCOBILLON ITALIANA	\N	producto	fijo	7.50	6.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4714	817	824	FERHC-0273	ESCUADRA FIERRO 6 PLG UYUSTOOLS	\N	producto	fijo	4.47	3.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4715	817	824	FERHC-0274	ESCUADRA FIERRO 8 PLG UYUSTOOLS	\N	producto	fijo	4.84	4.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4716	817	824	FERHC-0275	ESPATULA M/GOMA 2 PRO C&A	\N	producto	fijo	1.89	1.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4717	817	824	FERHC-0276	ESPONJA DULOPILLO DOLOPIO	\N	producto	fijo	0.25	0.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4718	817	824	FERHC-0277	EXTENSION C/3 TOMAS C/FOCO 10M HOME LIGHT	\N	producto	fijo	12.21	10.35	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4719	817	824	FERHC-0278	EXTENSION C/3 TOMAS C/FOCO 3M HOME LIGHT	\N	producto	fijo	6.56	5.56	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4720	817	824	FERHC-0279	EXTENSION C/3 TOMAS C/FOCO 5M HOME LIGHT	\N	producto	fijo	7.65	6.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4721	817	824	FERHC-0280	Electronivel 3 Mts ROTOPLAST	\N	producto	fijo	51.50	43.64	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4722	817	824	FERHC-0281	Enchufe De Colores EUROLITE	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4723	817	824	FERHC-0282	Enchufe Negro PLANO HOME LIGHT	\N	producto	fijo	0.47	0.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4724	817	824	FERHC-0283	Escobilla Fierro Acerado 4x14 C&A	\N	producto	fijo	1.77	1.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4725	817	824	FERHC-0284	Escobilla copa 3 Trensado KHOPPER	\N	producto	fijo	3.49	2.96	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4726	817	824	FERHC-0285	Escuadra 12 ASAKI	\N	producto	fijo	8.00	6.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4727	817	824	FERHC-0286	Esmalte 1/16 Amarillo Md VARIOS	\N	producto	fijo	4.80	4.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4728	817	824	FERHC-0287	Esmalte 1/16 Azul Naval VARIOS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4729	817	824	FERHC-0288	Esmalte 1/16 Bayo VARIOS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4730	817	824	FERHC-0289	Esmalte 1/16 Crema VARIOS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4731	817	824	FERHC-0290	Esmalte 1/16 Gris Claro VARIOS	\N	producto	fijo	5.00	4.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4732	817	824	FERHC-0291	Esmalte 1/16 Gris Oscuro VARIOS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4733	817	824	FERHC-0292	Esmalte 1/16 Nogal VARIOS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4734	817	824	FERHC-0293	Esmalte 1/16 Verde Cromo VARIOS	\N	producto	fijo	4.90	4.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4735	817	824	FERHC-0294	Esmalte 1/32 Amarillo Md VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4736	817	824	FERHC-0295	Esmalte 1/32 Azul Electrico VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4737	817	824	FERHC-0296	Esmalte 1/32 Azul Ultramar VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4738	817	824	FERHC-0297	Esmalte 1/32 Bayo VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4739	817	824	FERHC-0298	Esmalte 1/32 Blanco VARIOS	\N	producto	fijo	3.28	2.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4740	817	824	FERHC-0299	Esmalte 1/32 Celeste VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4741	817	824	FERHC-0300	Esmalte 1/32 Gris Claro VARIOS	\N	producto	fijo	3.29	2.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4742	817	824	FERHC-0301	Esmalte 1/32 Gris Oscuro VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4743	817	824	FERHC-0302	Esmalte 1/32 Naranja VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4744	817	824	FERHC-0303	Esmalte 1/32 Negro VARIOS	\N	producto	fijo	3.20	2.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4745	817	824	FERHC-0304	Esmalte 1/32 Nogal VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4746	817	824	FERHC-0305	Esmalte 1/32 Verde Esmeralda VARIOS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4747	817	824	FERHC-0306	Esmalte 1/4 Amarillo Md VARIOS	\N	producto	fijo	10.01	8.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4748	817	824	FERHC-0307	Esmalte 1/4 Azul Electrico VARIOS	\N	producto	fijo	10.01	8.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4749	817	824	FERHC-0308	Esmalte 1/4 Bayo VARIOS	\N	producto	fijo	9.99	8.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4750	817	824	FERHC-0309	Esmalte 1/4 Blanco VARIOS	\N	producto	fijo	11.00	9.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4751	817	824	FERHC-0310	Esmalte 1/4 Caoba VARIOS	\N	producto	fijo	10.50	8.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4752	817	824	FERHC-0311	Esmalte 1/4 Celeste VARIOS	\N	producto	fijo	10.01	8.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4753	817	824	FERHC-0312	Esmalte 1/4 Gris Claro VARIOS	\N	producto	fijo	9.99	8.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4754	817	824	FERHC-0313	Esmalte 1/4 Gris Oscuro VARIOS	\N	producto	fijo	10.07	8.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4755	817	824	FERHC-0314	Esmalte 1/4 Negro VARIOS	\N	producto	fijo	10.50	8.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4756	817	824	FERHC-0315	Esmalte 1/4 Verde Cromo VARIOS	\N	producto	fijo	10.01	8.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4757	817	824	FERHC-0316	Esmalte 1/8 Bayo VARIOS	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4758	817	824	FERHC-0317	Esmalte 1/8 Celeste VARIOS	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4759	817	824	FERHC-0318	Esmalte 1/8 Rojo Bermellon VARIOS	\N	producto	fijo	7.00	5.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4760	817	824	FERHC-0319	Esmalte 1/8 Rojo Oxido VARIOS	\N	producto	fijo	6.50	5.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4761	817	824	FERHC-0320	Esmalte 1/8 Verde Cromo VARIOS	\N	producto	fijo	6.80	5.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4762	817	824	FERHC-0321	Espatula M/Madera 1 1/2 C&A	\N	producto	fijo	1.68	1.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4763	817	824	FERHC-0322	Espatula M/Madera 2 C&A	\N	producto	fijo	1.53	1.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4764	817	824	FERHC-0323	Espatula M/Madera 3 C&A	\N	producto	fijo	1.83	1.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4765	817	824	FERHC-0324	Eternit Gran Onda 3.05m X 1.10m ETERNIT	\N	producto	fijo	60.92	51.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4766	817	824	FERHC-0325	Eternit Perfil 4 3.05 X 1.10 ETERNIT	\N	producto	fijo	51.00	43.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4767	817	824	FERHC-0326	Extension C/3 Tomas C/Foco 15m HOME LIGHT	\N	producto	fijo	15.42	13.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4768	817	824	FERHC-0327	FIERRO 6MM PRODAC	\N	producto	fijo	6.07	5.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4769	817	824	FERHC-0328	FOCO LED 12 W SWIFT	\N	producto	fijo	1.82	1.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4770	817	824	FERHC-0329	FOCO LED 18 W SWIFT	\N	producto	fijo	3.29	2.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4771	817	824	FERHC-0330	FOCO LED 20 W SWIFT	\N	producto	fijo	4.77	4.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4772	817	824	FERHC-0331	FOCO LED 9 W SWIFT	\N	producto	fijo	2.54	2.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4773	817	824	FERHC-0332	FOCO LED DECORATIVO 46 W T/HELICE L/DIA HOME LIGHT	\N	producto	fijo	18.57	15.74	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4774	817	824	FERHC-0333	FOCO LED GU 5.3-T11 5W 6500K SWIFT	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4775	817	824	FERHC-0334	FOCO LED UFO CIRCULAR 24 W L/DIA HOME LIGHT	\N	producto	fijo	12.64	10.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4776	817	824	FERHC-0335	FOCO LED UFO CIRCULAR 40 W L/DIA HOME LIGHT	\N	producto	fijo	15.45	13.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4777	817	824	FERHC-0336	FORTACHO DE PLASTICO 20 X 15 S/M	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4778	817	824	FERHC-0337	FORTACHO DE PLASTICO 25 X 17 S/M	\N	producto	fijo	8.00	6.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4779	817	824	FERHC-0338	FORTACHO DE PLASTICO 27 X 18 S/M	\N	producto	fijo	9.65	8.18	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4780	817	824	FERHC-0339	FORTACHO DE PLASTICO 30 X 20 S/M	\N	producto	fijo	10.01	8.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4781	817	824	FERHC-0340	FORTACHO DE PLASTICO 38 X 24 S/M	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4782	817	824	FERHC-0341	FORTACHO DE PLASTICO 40 X 26 S/M	\N	producto	fijo	15.00	12.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4783	817	824	FERHC-0342	FORTACHO DE PLASTICO 6 X 30 S/M	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4784	817	824	FERHC-0343	FRAGUA BEIGGE SANSON	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4785	817	824	FERHC-0344	FRAGUA BLANCO SANSON	\N	producto	fijo	3.65	3.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4786	817	824	FERHC-0345	FRAGUA CELESTE SANSON	\N	producto	fijo	4.00	3.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4787	817	824	FERHC-0346	FRAGUA COLORES VARIOS QUIZUD	\N	producto	fijo	0.11	0.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4788	817	824	FERHC-0347	FRAGUA CUERO SANSON	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4789	817	824	FERHC-0348	FRAGUA GRIS PLATA SANSON	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4790	817	824	FERHC-0349	FRAGUA HUESO SANSON	\N	producto	fijo	3.59	3.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4791	817	824	FERHC-0350	FRAGUA MARRON SANSON	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4792	817	824	FERHC-0351	FRAGUA NEGRA SANSON	\N	producto	fijo	5.20	4.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4793	817	824	FERHC-0352	Fierro 1/2 SIDERPERU	\N	producto	fijo	32.64	27.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4794	817	824	FERHC-0353	Fierro 12 MM SIDERPERU	\N	producto	fijo	29.67	25.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4795	817	824	FERHC-0354	Fierro 3/4 SIDERPERU	\N	producto	fijo	74.27	62.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4796	817	824	FERHC-0355	Fierro 3/8 SIDERPERU	\N	producto	fijo	18.14	15.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4797	817	824	FERHC-0356	Fierro 5/8 SIDERPERU	\N	producto	fijo	49.91	42.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4798	817	824	FERHC-0357	Fierro 6 MM SIDERPERU	\N	producto	fijo	7.26	6.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4799	817	824	FERHC-0358	Fierro 8 MM SIDERPERU	\N	producto	fijo	13.23	11.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4800	817	824	FERHC-0359	Foco 36 W PHELIPS	\N	producto	fijo	4.50	3.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4801	817	824	FERHC-0360	Foco 42 W PHELIPS	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4802	817	824	FERHC-0361	Foco 85 W PHELIX	\N	producto	fijo	11.00	9.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4803	817	824	FERHC-0362	Foco Led 12 W PHILIPS	\N	producto	fijo	4.35	3.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4804	817	824	FERHC-0363	Foco Led 15 W SWIFT	\N	producto	fijo	2.34	1.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4805	817	824	FERHC-0364	Foco Led 7 W SWIFT	\N	producto	fijo	1.32	1.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4806	817	824	FERHC-0365	Foco Led Nicroico KROSL	\N	producto	fijo	5.00	4.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4807	817	824	FERHC-0366	GANCHO J 1/4 X 2 1/2 S/M	\N	producto	fijo	0.25	0.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4808	817	824	FERHC-0367	GANCHO J 1/4 X 2 S/M	\N	producto	fijo	0.22	0.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4809	817	824	FERHC-0368	GANCHO J 1/4 X 3 S/M	\N	producto	fijo	0.29	0.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4810	817	824	FERHC-0369	GANCHO J 1/4 X 5 S/M	\N	producto	fijo	0.40	0.34	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4811	817	824	FERHC-0370	GLOSS DE 1/4 BLANCO GLOSS	\N	producto	fijo	20.79	17.62	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4812	817	824	FERHC-0371	GUANTE TELA COLORES VARIOS FERRAWY	\N	producto	fijo	2.68	2.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4813	817	824	FERHC-0372	GUANTES DE HILO ANTICORTE ECON FERRAWY	\N	producto	fijo	2.12	1.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4814	817	824	FERHC-0373	Gancho Cuadrado 1/4 x 1 1/2 x 6 1/4 G.O S/M	\N	producto	fijo	0.59	0.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4815	817	824	FERHC-0374	Grapa Para Cable Blanco 7mmx100unid UYUSTOOLS	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4816	817	824	FERHC-0375	Grapas Alambre Pua C&A	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4817	817	824	FERHC-0376	Gru±a De Canto TIGRE	\N	producto	fijo	3.50	2.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4818	817	824	FERHC-0377	Gru±a De Centro TIGRE	\N	producto	fijo	3.50	2.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4819	817	824	FERHC-0378	Guante Conveniente Talla M Clasica VIRUTEX	\N	producto	fijo	3.01	2.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4820	817	824	FERHC-0379	Guante Conveniente Talla S Clasica VIRUTEX	\N	producto	fijo	3.29	2.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4821	817	824	FERHC-0380	HOZ DENTADA 16 C&A	\N	producto	fijo	5.68	4.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4822	817	824	FERHC-0381	Hisopo C/Base S/M	\N	producto	fijo	3.96	3.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4823	817	824	FERHC-0382	INFLADOR P/NEUMATICOS 23PL TRUPER	\N	producto	fijo	19.97	16.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4824	817	824	FERHC-0383	INSECTICIDA AZUL 3 EN 1 COCK BRAND	\N	producto	fijo	7.33	6.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4825	817	824	FERHC-0384	INSECTICIDA ROJO 3 EN 1 COCK BRAND	\N	producto	fijo	7.33	6.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4826	817	824	FERHC-0385	INTERRUPTOR COLGANTE(AEREO) HOME LIGHT	\N	producto	fijo	1.09	0.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4827	817	824	FERHC-0386	INTERRUPTOR DOBLE 3VIAS P/CONMUTACION HOME LIGHT	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4828	817	824	FERHC-0387	INTERRUPTOR SIMPLE P/SOBREPONER HOME LIGHT	\N	producto	fijo	1.09	0.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4829	817	824	FERHC-0388	INTERRUPTOR TRIPLE P/EMPOTRADO FERRAWY	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4830	817	824	FERHC-0389	INTERRUPTOR-TOMACPRRIENTE MIXTO P/EMP HOME LIGHT	\N	producto	fijo	2.14	1.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4831	817	824	FERHC-0390	Interruptor Doble P/Conmutacion TICINO	\N	producto	fijo	20.38	17.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4832	817	824	FERHC-0391	Interruptor Doble P/Empotrado HOME LIGHT	\N	producto	fijo	1.89	1.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4833	817	824	FERHC-0392	Interruptor Doble P/Empotrado TICINO	\N	producto	fijo	12.86	10.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4834	817	824	FERHC-0393	Interruptor Simple 3vias P/Conmutacion HOME LIGHT	\N	producto	fijo	1.46	1.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4835	817	824	FERHC-0394	Interruptor Simple P/Empotrado HOME LIGHT	\N	producto	fijo	1.42	1.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4836	817	824	FERHC-0395	Interruptor Simple P/Empotrado TICINO	\N	producto	fijo	9.45	8.01	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4837	817	824	FERHC-0396	Interruptor Triple P/Empotrado HOME LIGHT	\N	producto	fijo	2.67	2.26	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4838	817	824	FERHC-0397	Interruptor Triple P/Empotrado TICINO	\N	producto	fijo	20.10	17.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4839	817	824	FERHC-0398	Kresso 1L KRIZZAL	\N	producto	fijo	3.34	2.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4840	817	824	FERHC-0399	LAVADERO 2 POZAS 1.10MT X 48CM X 0.80 S/M	\N	producto	fijo	111.33	94.35	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4841	817	824	FERHC-0400	LAVADERO 75 X 40 CM ALUMINIO 1 POZA S/M	\N	producto	fijo	32.50	27.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4842	817	824	FERHC-0401	LAVADERO 96 X 43 CM ALUMINIO 1 POZA S/M	\N	producto	fijo	37.50	31.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4843	817	824	FERHC-0402	LENTE SEGURIDAD NEGRO ACHINADO ASAKI	\N	producto	fijo	2.04	1.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4844	817	824	FERHC-0403	LIJA AL AGUA N░ 1200 ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4845	817	824	FERHC-0404	LIJA AL AGUA N░ 1500 ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4846	817	824	FERHC-0405	LIJA AL AGUA N░ 2000 ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4847	817	824	FERHC-0406	LIJA AL AGUA N░ 240 ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4848	817	824	FERHC-0407	LIJA AL AGUA N░ 320 ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4849	817	824	FERHC-0408	LIJA AL AGUA N░ 400 ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4850	817	824	FERHC-0409	LIJA N░ 220 PARA FIERRO ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4851	817	824	FERHC-0410	LIJA N░ 280 PARA FIERRO ABRALIT	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4852	817	824	FERHC-0411	LIJA PARA MADERA N░ 80 ASA	\N	producto	fijo	1.45	1.23	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4853	817	824	FERHC-0412	LIJADORA DE SANDALIA S/M	\N	producto	fijo	7.50	6.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4854	817	824	FERHC-0413	LIMA REDONDA BASTARDA 8 TRUPER	\N	producto	fijo	6.01	5.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4855	817	824	FERHC-0414	LIMA TRIANGULAR PESADA C/M 8 PL TRUPER	\N	producto	fijo	7.45	6.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4856	817	824	FERHC-0415	LIMA TRIANGULAR PESADA C/POLI 7 PL TRUPER	\N	producto	fijo	4.52	3.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4857	817	824	FERHC-0416	LLANTA COMPLETA IMPONCHABLE P/CARRETILLA C/ARO C&A	\N	producto	fijo	41.93	35.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4858	817	824	FERHC-0417	LLAVE CRUZ 14 (7X19X21X23) C&A	\N	producto	fijo	19.71	16.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4859	817	824	FERHC-0418	LLAVE DE DUCHA MANIJA ASPA C&A	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4860	817	824	FERHC-0419	LLAVE DE DUCHA MODELO Z C&A	\N	producto	fijo	16.69	14.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4861	817	824	FERHC-0420	LLAVE DE LAVATORIO CROMADO GRIFEMA	\N	producto	fijo	14.01	11.87	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4862	817	824	FERHC-0421	LLAVE DE LAVATORIO MUEBLE MODELO C C&A	\N	producto	fijo	17.94	15.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4863	817	824	FERHC-0422	LLAVE DE LAVATORIO MUEBLE MODELO Z C&A	\N	producto	fijo	16.31	13.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4864	817	824	FERHC-0423	LLAVE DE LAVATORIO PARED P/GANSO FLEX GRIS C&A	\N	producto	fijo	20.10	17.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4865	817	824	FERHC-0424	LLAVE DE LAVATORIO PARED P/GANSO FLEX NEGRO C&A	\N	producto	fijo	19.74	16.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4866	817	824	FERHC-0425	LLAVE ESTILSON 10 C&A	\N	producto	fijo	12.85	10.89	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4867	817	824	FERHC-0426	LLAVE ESTILSON 12 C&A	\N	producto	fijo	12.61	10.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4868	817	824	FERHC-0427	LLAVE HALEN EXAGONAL CROMADO 8 PCS KAMASA	\N	producto	fijo	3.93	3.33	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4869	817	824	FERHC-0428	LLAVE PASO 1 CIM	\N	producto	fijo	54.50	46.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4870	817	824	FERHC-0429	LLAVE PASO 1/2 S/ROSCA C&A	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4871	817	824	FERHC-0430	LLAVE PASO PVC CON UNIVERSAL 1/2 ERA	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4872	817	824	FERHC-0431	LUBRICANTE MULTIUSO P/AFLOJAR 11 OZ KNAUF	\N	producto	fijo	5.39	4.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4873	817	824	FERHC-0432	Ladrillo Concreto Tipo 12 S/M	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4874	817	824	FERHC-0433	Ladrillo Techo 12 SIPAN	\N	producto	fijo	2.55	2.16	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4875	817	824	FERHC-0434	Ladrillo Techo 15 ITAL	\N	producto	fijo	2.86	2.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4876	817	824	FERHC-0435	Lija Al Agua N░ 120 ASA	\N	producto	fijo	1.23	1.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4877	817	824	FERHC-0436	Lija Al Agua N░ 150 ASA	\N	producto	fijo	1.18	1.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4878	817	824	FERHC-0437	Lija Al Agua N░ 180 ASA	\N	producto	fijo	1.16	0.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4879	817	824	FERHC-0438	Lija Al Agua N░ 80 ASA	\N	producto	fijo	1.40	1.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4880	817	824	FERHC-0439	Lija N░ 100 Para Fierro ASA	\N	producto	fijo	1.05	0.89	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4881	817	824	FERHC-0440	Lija N░ 120 Para Fierro ASA	\N	producto	fijo	1.16	0.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4882	817	824	FERHC-0441	Lija N░ 150 Para Fierro ASA	\N	producto	fijo	1.12	0.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4883	817	824	FERHC-0442	Lija N░ 180 Para Fierro ASA	\N	producto	fijo	1.27	1.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4884	817	824	FERHC-0443	Lija N░ 40 Para Fierro ASA	\N	producto	fijo	1.42	1.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4885	817	824	FERHC-0444	Lija N░ 60 Para Fierro ASA	\N	producto	fijo	1.86	1.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4886	817	824	FERHC-0445	Lija N░ 80 Para Fierro ASA	\N	producto	fijo	1.65	1.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4887	817	824	FERHC-0446	Lija Para Madera N░ 100 ASA	\N	producto	fijo	1.25	1.06	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4888	817	824	FERHC-0447	Lija Para Madera N░ 180 ASA	\N	producto	fijo	1.24	1.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4889	817	824	FERHC-0448	Linterna Recargable HOME LIGHT	\N	producto	fijo	19.35	16.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4890	817	824	FERHC-0449	Llanta Completa P/Carretilla Ref C/Aro C&A	\N	producto	fijo	32.53	27.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4891	817	824	FERHC-0450	Llanta Sola P/Carretilla C&A	\N	producto	fijo	11.55	9.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4892	817	824	FERHC-0451	Llave De Ducha Modelo C C&A	\N	producto	fijo	18.35	15.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4893	817	824	FERHC-0452	Llave De Lavatorio Mueble Modelo A C&A	\N	producto	fijo	16.43	13.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4894	817	824	FERHC-0453	Llave De Lavatorio Mueble Modelo D C&A	\N	producto	fijo	15.84	13.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4895	817	824	FERHC-0454	Llave De Lavatorio Pared P/Ganso Mod B C&A	\N	producto	fijo	14.71	12.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4896	817	824	FERHC-0455	Llave De Lavatorio Pared P/Ganso Mod F C&A	\N	producto	fijo	15.12	12.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4897	817	824	FERHC-0456	Llave Francesa 10 C&A	\N	producto	fijo	8.96	7.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4898	817	824	FERHC-0457	Llave Francesa 12 C&A	\N	producto	fijo	11.22	9.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4899	817	824	FERHC-0458	Llave Francesa 8 C&A	\N	producto	fijo	7.17	6.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4900	817	824	FERHC-0459	Llave Mixta 10mm FERRAWY	\N	producto	fijo	1.11	0.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4901	817	824	FERHC-0460	Llave Mixta 11mm FERRAWY	\N	producto	fijo	1.16	0.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4902	817	824	FERHC-0461	Llave Mixta 12mm FERRAWY	\N	producto	fijo	1.24	1.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4903	817	824	FERHC-0462	Llave Mixta 13mm FERRAWY	\N	producto	fijo	1.36	1.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4904	817	824	FERHC-0463	Llave Mixta 14mm FERRAWY	\N	producto	fijo	1.56	1.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4905	817	824	FERHC-0464	Llave Mixta 15mm FERRAWY	\N	producto	fijo	1.86	1.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4906	817	824	FERHC-0465	Llave Mixta 17mm FERRAWY	\N	producto	fijo	2.15	1.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4907	817	824	FERHC-0466	Llave Mixta 19 C&A	\N	producto	fijo	2.54	2.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4908	817	824	FERHC-0467	Llave Mixta 8 C&A	\N	producto	fijo	1.04	0.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4909	817	824	FERHC-0468	Llave Para Amoladora 41/2 FERRAWY	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4910	817	824	FERHC-0469	Llave Para Taladro 41/2 UYUSTOOLS	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4911	817	824	FERHC-0470	Llave Paso 1/2 CIM	\N	producto	fijo	26.51	22.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4912	817	824	FERHC-0471	Llave Paso 3/4 CIM	\N	producto	fijo	39.22	33.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4913	817	824	FERHC-0472	Llave Paso Metal 1/2 VALMAX	\N	producto	fijo	7.94	6.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4914	817	824	FERHC-0473	Llave Paso Pvc 1 PAVCO	\N	producto	fijo	8.47	7.18	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4915	817	824	FERHC-0474	Llave Paso Pvc 1/2 C/R PAVCO	\N	producto	fijo	3.19	2.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4916	817	824	FERHC-0475	Llave Paso Pvc 3/4 PAVCO	\N	producto	fijo	4.86	4.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4917	817	824	FERHC-0476	Llave Termomagnetica 2X16 A SCHNEIDER	\N	producto	fijo	24.00	20.34	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4918	817	824	FERHC-0477	Llave Termomagnetica 2X16 A TICINO	\N	producto	fijo	36.26	30.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4919	817	824	FERHC-0478	Llave Termomagnetica 2X20 A TICINO	\N	producto	fijo	36.46	30.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4920	817	824	FERHC-0479	Llave Termomagnetica 2X25 A TICINO	\N	producto	fijo	35.21	29.84	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4921	817	824	FERHC-0480	Llave Termomagnetica 2X32 A TICINO	\N	producto	fijo	36.50	30.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4922	817	824	FERHC-0481	Llave Termomagnetica 2x20 A SCHNEIDER	\N	producto	fijo	26.00	22.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4923	817	824	FERHC-0482	Llave Termomagnetica 2x25 A SCHNEIDER	\N	producto	fijo	24.89	21.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4924	817	824	FERHC-0483	Llave Termomagnetica 2x32 A SCHNEIDER	\N	producto	fijo	27.27	23.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4925	817	824	FERHC-0484	MACHETE CAÐERO M/MADERA C/GANCHO 14 BELLOTA	\N	producto	fijo	12.50	10.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4926	817	824	FERHC-0485	MACHETE T/SABLE M/NEGRO 22 PL BELLOTA	\N	producto	fijo	13.00	11.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4927	817	824	FERHC-0486	MALLA GALVANIZADA CUADRADA 1/2 PESADA PRODAC	\N	producto	fijo	3.73	3.16	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4928	817	824	FERHC-0487	MALLA GRIS PARA ZANCUDO 1.20 VARIOS	\N	producto	fijo	1.31	1.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4929	817	824	FERHC-0488	MALLA RASCHEL 65% 4.2 MT 27.3KG VERDE VARIOS	\N	producto	fijo	3.69	3.13	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4930	817	824	FERHC-0489	MALLA RASCHEL 90% 4.20MT 39.9KG VERDE VARIOS	\N	producto	fijo	5.18	4.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4931	817	824	FERHC-0490	MANGUERA DE COLOR DUPLEX 5/8 X 100 MT DUPLEX	\N	producto	fijo	0.65	0.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4932	817	824	FERHC-0491	MANGUERA REFORZADA P/GAS NARANJA 3/8 2M	\N	producto	fijo	1.52	1.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4933	817	824	FERHC-0492	MANGUERA REFORZADA PVC VERDE 1 2M	\N	producto	fijo	2.82	2.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4934	817	824	FERHC-0493	MANGUERA REFORZADA PVC VERDE 3/4 2M	\N	producto	fijo	1.75	1.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4935	817	824	FERHC-0494	MANGUERA REFORZADA PVC VERDE 5/8 2M	\N	producto	fijo	1.16	0.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4936	817	824	FERHC-0495	MARTILLO DE GOMA C/BLANCO 16 ONZ TRUPER	\N	producto	fijo	12.34	10.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4937	817	824	FERHC-0496	MARTILLO M/FIBRA VIDRIO 16 ONZ C&A	\N	producto	fijo	10.03	8.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4938	817	824	FERHC-0497	MARTILLO M/MADERA 20OZ C&A	\N	producto	fijo	9.44	8.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4939	817	824	FERHC-0498	MERLUZA KOLORCIX	\N	producto	fijo	1.20	1.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4940	817	824	FERHC-0499	Malla Metalica Galvanizada 1/2 VARIOS	\N	producto	fijo	1.48	1.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4941	817	824	FERHC-0500	Malla Plastificada Verde 1/2 X 10kg VARIOS	\N	producto	fijo	1.65	1.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4942	817	824	FERHC-0501	Malla Plastificada Verde Pesada 1/2 X 3pl X 25kg VARIOS	\N	producto	fijo	4.90	4.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4943	817	824	FERHC-0502	Malla Verde Para Zancudo 1.20 VARIOS	\N	producto	fijo	1.63	1.38	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4944	817	824	FERHC-0503	Malla Verde Para Zancudo 90 Cm VARIOS	\N	producto	fijo	1.13	0.96	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4945	817	824	FERHC-0504	Manguera De Color Duplex 1 X 100 Mt DUPLEX	\N	producto	fijo	1.70	1.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4946	817	824	FERHC-0505	Manguera De Color Duplex 3/4 X 100 Mt DUPLEX	\N	producto	fijo	1.09	0.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4947	817	824	FERHC-0506	Manguera Ref Trans P/Autm 3/8 2M	\N	producto	fijo	0.53	0.45	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4948	817	824	FERHC-0507	Martillo M/Fibra Vidrio 16onz TRUPER	\N	producto	fijo	23.51	19.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4949	817	824	FERHC-0508	Martillo M/Madera 34mm TRAMONTINA	\N	producto	fijo	26.00	22.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4950	817	824	FERHC-0509	Masilla Para Carro BONFLEX	\N	producto	fijo	9.99	8.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4951	817	824	FERHC-0510	Masilla Para Madera AMERICA	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4952	817	824	FERHC-0511	NAYLO DE PESCAR 50 PRETUL	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4953	817	824	FERHC-0512	NIPLE PVC 1 X 1 1/2 TRANSFORMADO	\N	producto	fijo	0.71	0.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4954	817	824	FERHC-0513	NIPLE PVC 1 X 1 TRANSFORMADO	\N	producto	fijo	0.42	0.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4955	817	824	FERHC-0514	NIPLE PVC 1 X 2 TRANSFORMADO	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4956	817	824	FERHC-0515	NIPLE PVC 1 X 4 TRANSFORMADO	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4957	817	824	FERHC-0516	NIPLE PVC 1/2 X 3 TRANSFORMADO	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4958	817	824	FERHC-0517	NIPLE PVC 3/4 X 1 TRANSFORMADO	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4959	817	824	FERHC-0518	NIPLE PVC 3/4 X 2 1/2 TRANSFORMADO	\N	producto	fijo	0.64	0.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4960	817	824	FERHC-0519	NIPLE PVC 3/4 X 2 TRANSFORMADO	\N	producto	fijo	0.72	0.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4961	817	824	FERHC-0520	NIVEL DE ALUMINIO PROFESIONAL 12 PL TRUPER	\N	producto	fijo	13.43	11.38	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4962	817	824	FERHC-0521	NIVEL DE ALUMINIO PROFESIONAL 18 PL TRUPER	\N	producto	fijo	19.94	16.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4963	817	824	FERHC-0522	NIVEL DE ALUMINIO PROFESIONAL 24 PL TRUPER	\N	producto	fijo	24.34	20.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4964	817	824	FERHC-0523	NIVEL DE ALUMINIO PROFESIONAL 36 PL TRUPER	\N	producto	fijo	32.53	27.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4965	817	824	FERHC-0524	NIVEL PLASTICO AMARILLO 14 PLG UYUSTOOLS	\N	producto	fijo	3.55	3.01	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4966	817	824	FERHC-0525	NIVEL PLASTICO AMARILLO 18 PLG UYUSTOOLS	\N	producto	fijo	3.86	3.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4967	817	824	FERHC-0526	Naylo De Pescar 100 ARATY	\N	producto	fijo	8.90	7.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4968	817	824	FERHC-0527	Naylo De Pescar 40 ARATY	\N	producto	fijo	3.08	2.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4969	817	824	FERHC-0528	Naylo De Pescar 45 ARATY	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4970	817	824	FERHC-0529	Naylo De Pescar 60 ARATY	\N	producto	fijo	5.00	4.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4971	817	824	FERHC-0530	Naylo De Pescar 70 ARATY	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4972	817	824	FERHC-0531	Naylo De Pescar 80 ARATY	\N	producto	fijo	7.30	6.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4973	817	824	FERHC-0532	Niple 1 X 1 FIERRO G	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4974	817	824	FERHC-0533	Niple 1/2 X 1 1/2 FIERRO G	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4975	817	824	FERHC-0534	Niple 1/2 X 1 FIERRO G	\N	producto	fijo	0.60	0.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4976	817	824	FERHC-0535	Niple 3/4 X 3 FIERRO G	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4977	817	824	FERHC-0536	Niple Pvc 1 X 3 TRANSFORMADO	\N	producto	fijo	1.10	0.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4978	817	824	FERHC-0537	Niple Pvc 1/2 X 1 1/2 TRANSFORMADO	\N	producto	fijo	0.37	0.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4979	817	824	FERHC-0538	Niple Pvc 1/2 x 1 TRANSFORMADO	\N	producto	fijo	0.37	0.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4980	817	824	FERHC-0539	Niple Pvc 1/2 x 2 1/2 TRANSFORMADO	\N	producto	fijo	0.60	0.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4981	817	824	FERHC-0540	Niple Pvc 1/2 x 2 TRANSFORMADO	\N	producto	fijo	0.45	0.38	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4982	817	824	FERHC-0541	Niple Pvc 3/4 x 3 TRANSFORMADO	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4983	817	824	FERHC-0542	Niple Pvc 3/4 x 6 TRANSFORMADO	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4984	817	824	FERHC-0543	Ocre Azul BAYER	\N	producto	fijo	3.56	3.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4985	817	824	FERHC-0544	Ocre Negro BAYER	\N	producto	fijo	3.28	2.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4986	817	824	FERHC-0545	Ocre Rojo BAYER	\N	producto	fijo	3.50	2.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4987	817	824	FERHC-0546	Ocre Verde BAYER	\N	producto	fijo	3.60	3.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4988	817	824	FERHC-0547	PAJARAFIA TORCIDA CONO VARIOS	\N	producto	fijo	6.01	5.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4989	817	824	FERHC-0548	PAJARRAFIA PAQ X 12 UNID S/M	\N	producto	fijo	0.57	0.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4990	817	824	FERHC-0549	PARCHE DE LLANTA VIPAL R-01 VIPAL	\N	producto	fijo	0.40	0.34	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4991	817	824	FERHC-0550	PEGAMENTO 1/16 DORADO OATEY	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4992	817	824	FERHC-0551	PEGAMENTO 1/4 NARANJA OATEY	\N	producto	fijo	32.00	27.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4993	817	824	FERHC-0552	PEGAMENTO 1/8 AZUL OATEY	\N	producto	fijo	33.00	27.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4994	817	824	FERHC-0553	PEGAMENTO FRIO BV-03 VIPAL	\N	producto	fijo	8.00	6.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4995	817	824	FERHC-0554	PEGAMENTO GRIS CERAMICA INTERIORES ADJ	\N	producto	fijo	8.50	7.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4996	817	824	FERHC-0555	PEGAMENTO GRIS CERAMICA PREMIUM ADJ	\N	producto	fijo	12.50	10.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4997	817	824	FERHC-0556	PERNO HEX 1/4 X 2 1/2 G2 S/M	\N	producto	fijo	0.27	0.23	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4998	817	824	FERHC-0557	PERNO HEX 1/4 X 3 G2 S/M	\N	producto	fijo	0.33	0.28	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
4999	817	824	FERHC-0558	PIEDRA BASE OVER S/M	\N	producto	fijo	40.00	33.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5000	817	824	FERHC-0559	PIEDRA DE AFILAR 8X2X1 KAMASA	\N	producto	fijo	4.51	3.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5001	817	824	FERHC-0560	PINCEL PLANO 18 C&A	\N	producto	fijo	0.67	0.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5002	817	824	FERHC-0561	PINCEL PLANO 22 C&A	\N	producto	fijo	0.89	0.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5003	817	824	FERHC-0562	PINCEL PLANO 24 C&A	\N	producto	fijo	1.14	0.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5004	817	824	FERHC-0563	PINTURA EN BALDE AMARILLO KOLORCIX	\N	producto	fijo	16.00	13.56	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5005	817	824	FERHC-0564	PINTURA EN BALDE ARTICO KOLORCIX	\N	producto	fijo	13.00	11.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5006	817	824	FERHC-0565	PINTURA EN BALDE CELESTE KOLORCIX	\N	producto	fijo	13.10	11.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5007	817	824	FERHC-0566	PINTURA EN BALDE CITRON KOLORCIX	\N	producto	fijo	12.92	10.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5008	817	824	FERHC-0567	PINTURA EN BALDE GRIS CLARO KOLORCIX	\N	producto	fijo	13.50	11.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5009	817	824	FERHC-0568	PINTURA EN BALDE LILA KOLORCIX	\N	producto	fijo	12.57	10.65	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5010	817	824	FERHC-0569	PINTURA EN BALDE MAIZ KOLORCIX	\N	producto	fijo	12.84	10.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5011	817	824	FERHC-0570	PINTURA EN BALDE MARFIL KOLORCIX	\N	producto	fijo	13.50	11.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5012	817	824	FERHC-0571	PINTURA EN BALDE MELON KOLORCIX	\N	producto	fijo	12.84	10.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5013	817	824	FERHC-0572	PINTURA EN BALDE NARANJA KOLORCIX	\N	producto	fijo	15.00	12.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5014	817	824	FERHC-0573	PINTURA EN BALDE PISTACHO KOLORCIX	\N	producto	fijo	15.00	12.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5015	817	824	FERHC-0574	PINTURA EN BALDE ROJO TEJA KOLORCIX	\N	producto	fijo	13.50	11.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5016	817	824	FERHC-0575	PINTURA EN BALDE TURQUESA KOLORCIX	\N	producto	fijo	12.50	10.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5017	817	824	FERHC-0576	PINTURA EN BALDE VERDE ESMERALDA KOLORCIX	\N	producto	fijo	12.70	10.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5018	817	824	FERHC-0577	PINTURA EN BOLSA BLANCO HUMO KOLORCIX	\N	producto	fijo	2.80	2.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5019	817	824	FERHC-0578	PINTURA EN BOLSA BLANCO KOLORCIX	\N	producto	fijo	2.94	2.49	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5020	817	824	FERHC-0579	PINTURA EN BOLSA BLANCO X 25KG KOLORCIX	\N	producto	fijo	18.00	15.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5021	817	824	FERHC-0580	PINTURA EN BOLSA LILA KOLORCIX	\N	producto	fijo	2.95	2.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5022	817	824	FERHC-0581	PINTURA EN BOLSA MARFIL KOLORCIX	\N	producto	fijo	2.77	2.35	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5023	817	824	FERHC-0582	PINTURA EN BOLSA ROJO TEJA KOLORCIX	\N	producto	fijo	2.78	2.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5024	817	824	FERHC-0583	PINTURA SPRAY AZUL ELECTRICO C&A	\N	producto	fijo	2.91	2.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5025	817	824	FERHC-0584	PINTURA SPRAY CATERPILLAR C&A	\N	producto	fijo	3.41	2.89	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5026	817	824	FERHC-0585	PINTURA SPRAY DORADO C&A	\N	producto	fijo	4.58	3.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5027	817	824	FERHC-0586	PINTURA SPRAY NEGRO BRILLANTE C&A	\N	producto	fijo	2.91	2.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5028	817	824	FERHC-0587	PINTURA SPRAY NEGRO MATE C&A	\N	producto	fijo	2.96	2.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5029	817	824	FERHC-0588	PLANCHA DE BATIR M/GOMA 7 C&A	\N	producto	fijo	4.59	3.89	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5030	817	824	FERHC-0589	PLANCHA DE BATIR M/GOMA 8 KAMASA	\N	producto	fijo	9.00	7.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5031	817	824	FERHC-0590	PLANCHA PULIR LISA M/GOMA 11 X 5 PRO C&A	\N	producto	fijo	7.86	6.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5032	817	824	FERHC-0591	PLANCHA RASPIN DENTADA M/GOMA 11 X 5PL C&A	\N	producto	fijo	6.70	5.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5033	817	824	FERHC-0592	PLANCHA RASPIN M/GOMA ROJO BESTOOL	\N	producto	fijo	10.96	9.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5034	817	824	FERHC-0593	PLASTICO AZUL-NEGRO 1.50 MT(ROLL 120MT) S/M	\N	producto	fijo	2.32	1.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5035	817	824	FERHC-0594	PLASTICO AZUL-NEGRO 2METROS(ROLLO80MTS) S/M	\N	producto	fijo	3.06	2.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5036	817	824	FERHC-0595	PRECINTO 3 X 100 VARIOS	\N	producto	fijo	0.05	0.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5037	817	824	FERHC-0596	PRECINTO 3.6MM X 200 HOME LIGHT	\N	producto	fijo	0.01	0.01	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5038	817	824	FERHC-0597	PRECINTO 4.8 X 250 VARIOS	\N	producto	fijo	0.05	0.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5039	817	824	FERHC-0598	Palana Cuchara M/Negro C&A	\N	producto	fijo	11.61	9.84	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5040	817	824	FERHC-0599	Palana Recta M/Negro C&A	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5041	817	824	FERHC-0600	Palo Pulido Zapapico S/M	\N	producto	fijo	6.50	5.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5042	817	824	FERHC-0601	Palo Repuesto De Escoba S/M	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5043	817	824	FERHC-0602	Pegamento 1/32 Azul OATEY	\N	producto	fijo	9.81	8.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5044	817	824	FERHC-0603	Pegamento 1/4 Azul OATEY	\N	producto	fijo	46.00	38.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5045	817	824	FERHC-0604	Pegamento 1/4 Dorado OATEY	\N	producto	fijo	38.40	32.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5046	817	824	FERHC-0605	Pegamento 1/8 Dorado OATEY	\N	producto	fijo	26.10	22.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5047	817	824	FERHC-0606	Pegamento Africano 1/32 TEROCAL	\N	producto	fijo	4.01	3.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5048	817	824	FERHC-0607	Pegamento Africano 1/4 Galon TEROCAL	\N	producto	fijo	14.40	12.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5049	817	824	FERHC-0608	Pegamento Azul C/Brocha DATEY	\N	producto	fijo	3.28	2.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5050	817	824	FERHC-0609	Pegamento Blanco Flexible Porcelanato CHECERAMIC	\N	producto	fijo	16.30	13.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5051	817	824	FERHC-0610	Pegamento Gris Ceramica CHECERAMIC	\N	producto	fijo	7.00	5.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5052	817	824	FERHC-0611	Perno Anclaje S/M	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5053	817	824	FERHC-0612	Perno De Sujecion S/M	\N	producto	fijo	1.59	1.35	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5054	817	824	FERHC-0613	Piedra Base S/M	\N	producto	fijo	35.00	29.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5055	817	824	FERHC-0614	Piedra Chancada 1/2 Por Lata S/M	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5056	817	824	FERHC-0615	Piedra Chancada 1/2 S/M	\N	producto	fijo	60.00	50.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5057	817	824	FERHC-0616	Piedra Chancada 3/4 S/M	\N	producto	fijo	60.00	50.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5058	817	824	FERHC-0617	Pincel Plano 10 C&A	\N	producto	fijo	0.58	0.49	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5059	817	824	FERHC-0618	Pincel Plano 12 C&A	\N	producto	fijo	0.67	0.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5060	817	824	FERHC-0619	Pintura En Balde Azul KOLORCIX	\N	producto	fijo	13.40	11.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5061	817	824	FERHC-0620	Pintura En Balde Blanco Humo KOLORCIX	\N	producto	fijo	13.00	11.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5062	817	824	FERHC-0621	Pintura En Balde Blanco KOLORCIX	\N	producto	fijo	13.50	11.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5063	817	824	FERHC-0622	Pintura En Balde Fresa KOLORCIX	\N	producto	fijo	13.50	11.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5064	817	824	FERHC-0623	Pintura En Bolsa Amarillo KOLORCIX	\N	producto	fijo	2.95	2.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5065	817	824	FERHC-0624	Pintura En Bolsa Azul KOLORCIX	\N	producto	fijo	2.87	2.43	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5066	817	824	FERHC-0625	Pintura En Bolsa Celeste KOLORCIX	\N	producto	fijo	2.80	2.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5067	817	824	FERHC-0626	Pintura En Bolsa Crema KOLORCIX	\N	producto	fijo	2.80	2.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5068	817	824	FERHC-0627	Pintura En Bolsa Melon KOLORCIX	\N	producto	fijo	2.80	2.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5069	817	824	FERHC-0628	Pintura En Bolsa Naranja KOLORCIX	\N	producto	fijo	2.87	2.43	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5070	817	824	FERHC-0629	Pintura En Bolsa Rosado KOLORCIX	\N	producto	fijo	2.88	2.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5071	817	824	FERHC-0630	Pintura En Bolsa Verde Esmeralda KOLORCIX	\N	producto	fijo	2.80	2.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5072	817	824	FERHC-0631	Pintura En Bolsa Verde Limon KOLORCIX	\N	producto	fijo	2.95	2.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5073	817	824	FERHC-0632	Pintura Spray Aluminio C&A	\N	producto	fijo	4.14	3.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5074	817	824	FERHC-0633	Pintura Spray Amarillo Limon C&A	\N	producto	fijo	2.99	2.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5075	817	824	FERHC-0634	Pintura Spray Azul Claro C&A	\N	producto	fijo	3.02	2.56	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5076	817	824	FERHC-0635	Pintura Spray Blanco Brillante C&A	\N	producto	fijo	3.12	2.64	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5077	817	824	FERHC-0636	Pintura Spray Blanco Mate C&A	\N	producto	fijo	3.66	3.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5078	817	824	FERHC-0637	Pintura Spray Celeste C&A	\N	producto	fijo	3.32	2.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5079	817	824	FERHC-0638	Pintura Spray Gris C&A	\N	producto	fijo	2.93	2.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5080	817	824	FERHC-0639	Pintura Spray Marron C&A	\N	producto	fijo	3.15	2.67	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5081	817	824	FERHC-0640	Pintura Spray Naranja C&A	\N	producto	fijo	3.48	2.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5082	817	824	FERHC-0641	Pintura Spray Rojo Brillante C&A	\N	producto	fijo	2.97	2.52	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5083	817	824	FERHC-0642	Pintura Spray Silver C&A	\N	producto	fijo	3.50	2.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5084	817	824	FERHC-0643	Pintura Spray Verde Irlandes C&A	\N	producto	fijo	3.33	2.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5085	817	824	FERHC-0644	Plancha Pulir M/Goma KAMASA	\N	producto	fijo	12.00	10.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5086	817	824	FERHC-0645	Plomada Cilindrica Zincada S/M	\N	producto	fijo	10.67	9.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5087	817	824	FERHC-0646	Precinto 4.8 X 300 VARIOS	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5088	817	824	FERHC-0647	Precinto 4.8 X 400 VARIOS	\N	producto	fijo	0.04	0.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5089	817	824	FERHC-0648	Precinto 4.8 X 500 VARIOS	\N	producto	fijo	0.13	0.11	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5090	817	824	FERHC-0649	QUITA SARRO LUKAS	\N	producto	fijo	3.29	2.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5091	817	824	FERHC-0650	RAPIMIX ASENTADO PACASMAYO	\N	producto	fijo	8.87	7.52	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5092	817	824	FERHC-0651	RAPIMIX PARA TARRAJEO PACASMAYO	\N	producto	fijo	7.89	6.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5093	817	824	FERHC-0652	RASTRILLO RECTO 16 DIENTES M/MADERA TRUPER	\N	producto	fijo	31.03	26.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5094	817	824	FERHC-0653	REMACHADORA 10 PL SCHUBERT	\N	producto	fijo	13.99	11.86	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5095	817	824	FERHC-0654	RODILLO P/PINTAR 12 C&A	\N	producto	fijo	4.06	3.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5096	817	824	FERHC-0655	RODILLO P/PINTAR 12 TORO	\N	producto	fijo	17.50	14.83	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5097	817	824	FERHC-0656	RODOPLAST BEIGGE VARIOS	\N	producto	fijo	1.99	1.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5098	817	824	FERHC-0657	RODOPLAST MARFIL CLARO VARIOS	\N	producto	fijo	1.83	1.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5099	817	824	FERHC-0658	RODOPLAST NEGRO VARIOS	\N	producto	fijo	1.32	1.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5100	817	824	FERHC-0659	RODOPLAST VERDE SPAY VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5101	817	824	FERHC-0660	Radar Automatico TAIWAN	\N	producto	fijo	27.06	22.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5102	817	824	FERHC-0661	Recogedor Colores Economico S/M	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5103	817	824	FERHC-0662	Reduccion Cpvc 3/4 A 1/2 PAVCO	\N	producto	fijo	0.93	0.79	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5104	817	824	FERHC-0663	Reduccion Pvc 1 A 1/2 INYECTOPLAST	\N	producto	fijo	1.14	0.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5105	817	824	FERHC-0664	Reduccion Pvc 1 a 1/2 PAVCO	\N	producto	fijo	1.91	1.62	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5106	817	824	FERHC-0665	Reduccion Pvc 1 a 3/4 INYECTOPLAST	\N	producto	fijo	1.66	1.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5107	817	824	FERHC-0666	Reduccion Pvc 1 a 3/4 PAVCO	\N	producto	fijo	2.22	1.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5108	817	824	FERHC-0667	Reduccion Pvc 3/4 A 1/2 INYECTOPLAST	\N	producto	fijo	0.92	0.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5109	817	824	FERHC-0668	Reduccion Pvc 3/4 A 1/2 PAVCO	\N	producto	fijo	1.60	1.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5110	817	824	FERHC-0669	Reduccion Sal 3 A 2 PAVCO	\N	producto	fijo	3.76	3.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5111	817	824	FERHC-0670	Reduccion Sal 4 A 2 PAVCO	\N	producto	fijo	4.04	3.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5112	817	824	FERHC-0671	Reduccion Sal 4 A 2 PLASTICA	\N	producto	fijo	2.60	2.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5113	817	824	FERHC-0672	Reduccion Sal 4 A 3 PAVCO	\N	producto	fijo	6.37	5.40	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5114	817	824	FERHC-0673	Registro 2 Cromado VARIOS	\N	producto	fijo	3.12	2.64	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5115	817	824	FERHC-0674	Registro 3 Cromado VARIOS	\N	producto	fijo	6.57	5.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5116	817	824	FERHC-0675	Registro 4 Cromado VARIOS	\N	producto	fijo	9.70	8.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5117	817	824	FERHC-0676	Regla De Aluminio S/M	\N	producto	fijo	9.61	8.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5118	817	824	FERHC-0677	Repuesto Para Corta Mayolica KAMASA	\N	producto	fijo	9.52	8.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5119	817	824	FERHC-0678	Rodaje P/Carretilla 2 pz TRUPER	\N	producto	fijo	3.03	2.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5120	817	824	FERHC-0679	Rodillo P/Pintar 9 C&A	\N	producto	fijo	3.21	2.72	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5121	817	824	FERHC-0680	Rodillo P/Pintar 9 TORO	\N	producto	fijo	14.50	12.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5122	817	824	FERHC-0681	Rodoplast Aluminio Brillante 11.5 Ceramica VARIOS	\N	producto	fijo	6.01	5.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5123	817	824	FERHC-0682	Rodoplast Aluminio Brillante 9.5 Ceramica VARIOS	\N	producto	fijo	6.08	5.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5124	817	824	FERHC-0683	Rodoplast Aluminio Mate 11.5 Ceramica VARIOS	\N	producto	fijo	7.50	6.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5125	817	824	FERHC-0684	Rodoplast Aluminio Mate 9.5 Ceramica VARIOS	\N	producto	fijo	7.63	6.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5126	817	824	FERHC-0685	Rodoplast Blanco VARIOS	\N	producto	fijo	1.36	1.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5127	817	824	FERHC-0686	Rodoplast Bone VARIOS	\N	producto	fijo	1.99	1.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5128	817	824	FERHC-0687	Rodoplast Champang VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5129	817	824	FERHC-0688	Rodoplast Chocolate VARIOS	\N	producto	fijo	1.99	1.69	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5130	817	824	FERHC-0689	Rodoplast Crema VARIOS	\N	producto	fijo	1.71	1.45	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5131	817	824	FERHC-0690	Rodoplast Gris Claro VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5132	817	824	FERHC-0691	Rodoplast Gris Oscuro VARIOS	\N	producto	fijo	1.82	1.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5133	817	824	FERHC-0692	Rodoplast Lila Bebe VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5134	817	824	FERHC-0693	Rodoplast Madera VARIOS	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5135	817	824	FERHC-0694	Rodoplast Marfil Oscuro VARIOS	\N	producto	fijo	1.84	1.56	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5136	817	824	FERHC-0695	Rodoplast Marron Tabaco VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5137	817	824	FERHC-0696	Rodoplast Palo Rosa VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5138	817	824	FERHC-0697	Rodoplast Rojo VARIOS	\N	producto	fijo	1.90	1.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5139	817	824	FERHC-0698	Rodoplast Verde Nilo VARIOS	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5140	817	824	FERHC-0699	Rondana Circular Grande S/M	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5141	817	824	FERHC-0700	Rondana Circular Peq. S/M	\N	producto	fijo	0.50	0.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5142	817	824	FERHC-0701	Rondana Rect Peq. S/M	\N	producto	fijo	0.50	0.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5143	817	824	FERHC-0702	SEMICODO CPVC 1/2 SP PAVCO	\N	producto	fijo	1.14	0.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5144	817	824	FERHC-0703	SIERRA ACEROS AREQUIPA	\N	producto	fijo	3.08	2.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5145	817	824	FERHC-0704	SIKA 1 GALON X 4 LITROS SIKA	\N	producto	fijo	21.30	18.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5146	817	824	FERHC-0705	SILICONA BLANCO 280 ML PARA BAÐO Y COCINA SOLDIMIX	\N	producto	fijo	12.05	10.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5147	817	824	FERHC-0706	SILICONA PARA VIDRIO C/NEGRO 225ML KNAUF	\N	producto	fijo	4.79	4.06	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5148	817	824	FERHC-0707	SILICONA PARA VIDRIO TRANSPARENTE 225ML KNAUF	\N	producto	fijo	5.25	4.45	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5149	817	824	FERHC-0708	SILICONA TRANSPARENTE MULTIUSOS 50GR SOLDIMIX	\N	producto	fijo	4.70	3.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5150	817	824	FERHC-0709	SILICONA UV 300ML FRESA SIMONIZ	\N	producto	fijo	11.66	9.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5151	817	824	FERHC-0710	SOCATE AEREO DE PLASTICO REFORZADO HOME LIGHT	\N	producto	fijo	0.92	0.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5152	817	824	FERHC-0711	SOCATE MODELO PLANO BLANCO P22BN TICINO	\N	producto	fijo	0.01	0.01	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5153	817	824	FERHC-0712	SOCATE MODELO PLANO HOME LIGHT	\N	producto	fijo	1.52	1.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5154	817	824	FERHC-0713	SOCATE OVALADA BLANCO TICINO	\N	producto	fijo	8.71	7.38	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5155	817	824	FERHC-0714	SOLDADURA 1/8 SUPERSITO	\N	producto	fijo	16.82	14.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5156	817	824	FERHC-0715	SOLDIMIX EXTRAFUERTE 24 HRS SOLDIMIX	\N	producto	fijo	7.33	6.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5157	817	824	FERHC-0716	STOVE BOLTS 5/32 X 1 1/2 PL S/M	\N	producto	fijo	0.05	0.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5158	817	824	FERHC-0717	STOVE BOLTS 6/32 X 1 1/2 PL S/M	\N	producto	fijo	0.05	0.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5159	817	824	FERHC-0718	STOVE BOLTS 6/32 X 1 PL S/M	\N	producto	fijo	0.05	0.04	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5160	817	824	FERHC-0719	STOVE BOLTS 6/32 X 2 PL S/M	\N	producto	fijo	0.06	0.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5161	817	824	FERHC-0720	SUPER GLUE BLISTER X 1.50 GR SOLDIMIX	\N	producto	fijo	0.38	0.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5162	817	824	FERHC-0721	Semicodo Cpvc 3/4 Sp PAVCO	\N	producto	fijo	1.86	1.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5163	817	824	FERHC-0722	Semicodo Pvc 1 Sp PAVCO	\N	producto	fijo	2.37	2.01	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5164	817	824	FERHC-0723	Semicodo Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.70	1.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5165	817	824	FERHC-0724	Semicodo Pvc 1/2 Sp PLASTICA	\N	producto	fijo	0.63	0.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5166	817	824	FERHC-0725	Semicodo Pvc 3/4 Sp PAVCO	\N	producto	fijo	2.63	2.23	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5167	817	824	FERHC-0726	Semicodo Pvc 3/4 Sp TRANSFORMADO	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5168	817	824	FERHC-0727	Semicodo Sal 2 PAVCO	\N	producto	fijo	1.64	1.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5169	817	824	FERHC-0728	Semicodo Sal 2 PLASTICA	\N	producto	fijo	0.84	0.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5170	817	824	FERHC-0729	Semicodo Sal 3 INYECTOPLAST	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5171	817	824	FERHC-0730	Semicodo Sal 3 PAVCO	\N	producto	fijo	4.31	3.65	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5172	817	824	FERHC-0731	Semicodo Sal 4 PAVCO	\N	producto	fijo	6.34	5.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5173	817	824	FERHC-0732	Semicodo Sal 4 PLASTICA	\N	producto	fijo	3.80	3.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5174	817	824	FERHC-0733	Serrucho Curvo Poda 14pl M/Madera TRUPER	\N	producto	fijo	19.71	16.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5175	817	824	FERHC-0734	Serrucho M/Madera 18 Pl C&A	\N	producto	fijo	7.62	6.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5176	817	824	FERHC-0735	Sierra Naranja SANDFLEX	\N	producto	fijo	3.33	2.82	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5177	817	824	FERHC-0736	Sika x kg SIKA	\N	producto	fijo	5.20	4.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5178	817	824	FERHC-0737	Sikaflex 11 Fc Plus Gris 300 Ml Sika SIKA	\N	producto	fijo	23.00	19.49	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5179	817	824	FERHC-0738	Silicona Mega Grey MEGA GREY	\N	producto	fijo	10.50	8.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5180	817	824	FERHC-0739	Socate Colgante NEW LIGHT	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5181	817	824	FERHC-0740	Soda Caustica Litro KRIZZAL	\N	producto	fijo	5.00	4.24	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5182	817	824	FERHC-0741	Soda Caustica Por Kg KRIZZAL	\N	producto	fijo	10.48	8.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5183	817	824	FERHC-0742	Soldadura 1/8 PUNTO AZUL	\N	producto	fijo	15.40	13.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5184	817	824	FERHC-0743	Soldimix 10 Minutos SOLDIMIX	\N	producto	fijo	7.32	6.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5185	817	824	FERHC-0744	Sombrero De Ventilacion 2 HECHIZA	\N	producto	fijo	1.90	1.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5186	817	824	FERHC-0745	Sombrero De Ventilacion 4 HECHIZA	\N	producto	fijo	6.50	5.51	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5187	817	824	FERHC-0746	Sumidero 2 Cromado VARIOS	\N	producto	fijo	2.90	2.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5188	817	824	FERHC-0747	Sumidero 3 Cromado VARIOS	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5189	817	824	FERHC-0748	Sumidero 4 Cromado VARIOS	\N	producto	fijo	10.44	8.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5190	817	824	FERHC-0749	TAPON CPVC 1/2 HEMBRA PAVCO	\N	producto	fijo	0.55	0.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5191	817	824	FERHC-0750	TECNOPORT 1 1.20X2.40 MTS(PAQ 38UNID) S/M	\N	producto	fijo	8.50	7.20	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5192	817	824	FERHC-0751	TECNOPORT 1/2 1.20X2.40 MTS(PAQ 76UNID) S/M	\N	producto	fijo	4.25	3.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5193	817	824	FERHC-0752	TECNOPORT T/12 X0.30X1.20 (64 UNID) S/M	\N	producto	fijo	5.40	4.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5194	817	824	FERHC-0753	TEE PVC 1 SP PLASTICA	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5195	817	824	FERHC-0754	TEE PVC 3/4 CR PLASTICA PLASTICA	\N	producto	fijo	1.90	1.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5196	817	824	FERHC-0755	TEE SAL 2 PLASTICA	\N	producto	fijo	1.82	1.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5197	817	824	FERHC-0756	TEE SAL 3 A 2 PAVCO	\N	producto	fijo	8.07	6.84	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5198	817	824	FERHC-0757	TEE SAL 4 A 3 PAVCO	\N	producto	fijo	14.84	12.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5199	817	824	FERHC-0758	TEE SAL SANITARIA 2 PLASTICA	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5200	817	824	FERHC-0759	TEE SAL SANITARIA 4 PLASTICA	\N	producto	fijo	9.13	7.74	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5201	817	824	FERHC-0760	TERMINAL DE OJO BRONCE S/M	\N	producto	fijo	0.29	0.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5202	817	824	FERHC-0761	TERMINAL MACHO BRONCE S/M	\N	producto	fijo	0.20	0.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5203	817	824	FERHC-0762	THINER ACRILICO FMQ FM	\N	producto	fijo	14.47	12.26	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5204	817	824	FERHC-0763	THINER ACRILICO PATRON AC-450 2.8 LTRS TORVISCO	\N	producto	fijo	16.00	13.56	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5205	817	824	FERHC-0764	THINER ACRILICO TX-500 X 1/2 LITRO LOSARO	\N	producto	fijo	3.30	2.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5206	817	824	FERHC-0765	TIJERA P/HOJALATERO 12 M/REFORZADA C&A	\N	producto	fijo	10.73	9.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5207	817	824	FERHC-0766	TIMBRE DING DONG HOME LIGHT	\N	producto	fijo	5.14	4.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5208	817	824	FERHC-0767	TIRAFON HEX 1/4 X 1 1/2 S/M	\N	producto	fijo	0.09	0.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5209	817	824	FERHC-0768	TIRALINEA 30 MT 3 PZAS C&A	\N	producto	fijo	8.19	6.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5210	817	824	FERHC-0769	TIZA BLANCA PARA PIZARRA CAJA X 50UNID S/M	\N	producto	fijo	0.09	0.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5211	817	824	FERHC-0770	TOMACORRIENTE DOBLE P/SOBREPONER CON PUESTA A TIERRA HOME LIGHT	\N	producto	fijo	2.25	1.91	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5212	817	824	FERHC-0771	TOMACORRIENTE DOBLE/EMPOT CON PUESTA A TIERRA HOME LIGHT	\N	producto	fijo	2.21	1.87	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5213	817	824	FERHC-0772	TOMACORRIENTE SIMPLE P/EMPOTRADO HOME LIGHT	\N	producto	fijo	1.72	1.46	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5214	817	824	FERHC-0773	TOMACORRIENTE TRIPLE P/EMPOTRADO HOME LIGHT	\N	producto	fijo	2.04	1.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5215	817	824	FERHC-0774	TOMACORRIENTE TRIPLE SOBREPONER TICINO	\N	producto	fijo	13.63	11.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5216	817	824	FERHC-0775	TORNILLO AUTOPERFORANTE #10 X 1 1/2 UYUSTOOLS	\N	producto	fijo	0.06	0.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5217	817	824	FERHC-0776	TORNILLO SPACK 3.5X25 S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5218	817	824	FERHC-0777	TORNILLO SPACK 5X25 S/M	\N	producto	fijo	0.04	0.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5219	817	824	FERHC-0778	TRAMPA BOTELLA PVC P/LAVATORIO SANIFER	\N	producto	fijo	7.15	6.06	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5220	817	824	FERHC-0779	TRAMPA FLEXIBLE Y DESAGUE P/LAV 1.1/4 HYDRA	\N	producto	fijo	9.61	8.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5221	817	824	FERHC-0780	TRIPLAY 3.5MM TIPO LUPUNA 1.22 X2.44 LUPUNA	\N	producto	fijo	21.75	18.43	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5222	817	824	FERHC-0781	TRIPLAY 6MM TIPO LUPUNA 1.22 X 2.44 LUPUNA	\N	producto	fijo	38.50	32.63	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5223	817	824	FERHC-0782	TRIPLAY FENOLICO 17 MM 1.22 X 2.44 ECOPLEX	\N	producto	fijo	73.50	62.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5224	817	824	FERHC-0783	TROMPITO PARA CAÐO S/M	\N	producto	fijo	0.12	0.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5225	817	824	FERHC-0784	TUBO PVC C-10 1 C/R PLASTICA	\N	producto	fijo	19.10	16.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5226	817	824	FERHC-0785	TUBO PVC C-10 1/2 SP PLASTICA	\N	producto	fijo	7.04	5.97	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5227	817	824	FERHC-0786	TUBO PVC C-10 3/4 SP PLASTICA	\N	producto	fijo	7.50	6.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5228	817	824	FERHC-0787	TUBO SAL 4 PLASTICA	\N	producto	fijo	18.20	15.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5229	817	824	FERHC-0788	TUBO SAL 6 S-25 NARANJA 160MM UF X6 MT KOPLAST	\N	producto	fijo	89.76	76.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5230	817	824	FERHC-0789	TUBO SEL LUZ 1 PLASTICA	\N	producto	fijo	4.00	3.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5231	817	824	FERHC-0790	TUBO SEL LUZ 3/4 BLANCO PLASTICA	\N	producto	fijo	2.14	1.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5232	817	824	FERHC-0791	TUBO SEL LUZ 5/8 PLASTICA	\N	producto	fijo	2.30	1.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5233	817	824	FERHC-0792	Tanque Eternit Arena 1100 Lts ETERNIT	\N	producto	fijo	568.00	481.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5234	817	824	FERHC-0793	Tanque Eternit Azul 1100 Lts ETERNIT	\N	producto	fijo	500.00	423.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5235	817	824	FERHC-0794	Tapa Ciega Circular S/M	\N	producto	fijo	0.22	0.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5236	817	824	FERHC-0795	Tapa Ciega Rectangular S/M	\N	producto	fijo	0.22	0.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5237	817	824	FERHC-0796	Tapon Cpvc 3/4 Hembra PAVCO	\N	producto	fijo	0.84	0.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5238	817	824	FERHC-0797	Tapon De Oidos S/M	\N	producto	fijo	1.48	1.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5239	817	824	FERHC-0798	Tapon Hembra Pvc 1 C/Rosca PAVCO	\N	producto	fijo	1.90	1.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5240	817	824	FERHC-0799	Tapon Hembra Pvc 1 SP INYECTOPLAST	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5241	817	824	FERHC-0800	Tapon Hembra Pvc 1 Sp PAVCO	\N	producto	fijo	2.18	1.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5242	817	824	FERHC-0801	Tapon Hembra Pvc 1/2 C/Rosca GERFOR	\N	producto	fijo	0.40	0.34	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5243	817	824	FERHC-0802	Tapon Hembra Pvc 1/2 C/Rosca PAVCO	\N	producto	fijo	1.29	1.09	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5244	817	824	FERHC-0803	Tapon Hembra Pvc 1/2 Sp INYECTOPLAST	\N	producto	fijo	0.26	0.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5245	817	824	FERHC-0804	Tapon Hembra Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.10	0.93	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5246	817	824	FERHC-0805	Tapon Hembra Pvc 3/4 C/Rosca PAVCO	\N	producto	fijo	0.90	0.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5247	817	824	FERHC-0806	Tapon Hembra Pvc 3/4 Sp INYECTOPLAST	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5248	817	824	FERHC-0807	Tapon Hembra Pvc 3/4 Sp PAVCO	\N	producto	fijo	1.62	1.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5249	817	824	FERHC-0808	Tapon Macho 1/2 FIERRO G	\N	producto	fijo	1.04	0.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5250	817	824	FERHC-0809	Tapon Macho Pvc 1 Vinduit PAVCO	\N	producto	fijo	2.30	1.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5251	817	824	FERHC-0810	Tapon Macho Pvc 1/2 PAVCO	\N	producto	fijo	1.39	1.18	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5252	817	824	FERHC-0811	Tapon Macho Pvc 1/2 PLASTICA	\N	producto	fijo	0.59	0.50	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5253	817	824	FERHC-0812	Tapon Macho Pvc 3/4 GERFOR	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5254	817	824	FERHC-0813	Tapon Macho Pvc 3/4 Vinduit PAVCO	\N	producto	fijo	1.40	1.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5255	817	824	FERHC-0814	Tapon Sal 2 PAVCO	\N	producto	fijo	1.11	0.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5256	817	824	FERHC-0815	Tapon Sal 3 PAVCO	\N	producto	fijo	1.58	1.34	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5257	817	824	FERHC-0816	Tapon Sal 4 INYECTOPLAST	\N	producto	fijo	1.18	1.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5258	817	824	FERHC-0817	Tarugo Azul S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5259	817	824	FERHC-0818	Tarugo Naranja S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5260	817	824	FERHC-0819	Tarugo Verde S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5261	817	824	FERHC-0820	Tecnoport 3/4 1.20x2.40 Mts S/M	\N	producto	fijo	6.40	5.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5262	817	824	FERHC-0821	Tee Bronce 1/2 VALMAX	\N	producto	fijo	2.82	2.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5263	817	824	FERHC-0822	Tee Cpvc 1/2 Sp PAVCO	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5264	817	824	FERHC-0823	Tee Cpvc 3/4 Sp PAVCO	\N	producto	fijo	2.78	2.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5265	817	824	FERHC-0824	Tee Fierro G. 1/2 FIERRO G	\N	producto	fijo	1.20	1.02	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5266	817	824	FERHC-0825	Tee Fierro G. 3/4 FIERRO G	\N	producto	fijo	2.10	1.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5267	817	824	FERHC-0826	Tee Pvc 1 C/Rosca PAVCO	\N	producto	fijo	4.84	4.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5268	817	824	FERHC-0827	Tee Pvc 1 C/Rosca PLASTICA	\N	producto	fijo	3.00	2.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5269	817	824	FERHC-0828	Tee Pvc 1 Sp PAVCO	\N	producto	fijo	6.24	5.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5270	817	824	FERHC-0829	Tee Pvc 1/2 C/Rosca NICOL	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5271	817	824	FERHC-0830	Tee Pvc 1/2 C/Rosca PAVCO	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5272	817	824	FERHC-0831	Tee Pvc 1/2 Sp PAVCO	\N	producto	fijo	2.19	1.86	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5273	817	824	FERHC-0832	Tee Pvc 1/2 Sp PLASTICA	\N	producto	fijo	0.89	0.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5274	817	824	FERHC-0833	Tee Pvc 3/4 C/Rosca PAVCO	\N	producto	fijo	3.40	2.88	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5275	817	824	FERHC-0834	Tee Pvc 3/4 S/p PLASTICA	\N	producto	fijo	1.30	1.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5276	817	824	FERHC-0835	Tee Pvc 3/4 Sp PAVCO	\N	producto	fijo	3.30	2.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5277	817	824	FERHC-0836	Tee Sal 2 PAVCO	\N	producto	fijo	3.30	2.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5278	817	824	FERHC-0837	Tee Sal 3 PAVCO	\N	producto	fijo	10.15	8.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5279	817	824	FERHC-0838	Tee Sal 3 PLASTICA	\N	producto	fijo	0.00	0.00	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5280	817	824	FERHC-0839	Tee Sal 4 A 2 PAVCO	\N	producto	fijo	8.00	6.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5281	817	824	FERHC-0840	Tee Sal 4 A 2 PLASTICA	\N	producto	fijo	4.30	3.64	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5282	817	824	FERHC-0841	Tee Sal 4 PAVCO	\N	producto	fijo	10.08	8.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5283	817	824	FERHC-0842	Tee Sal 4 PLASTICA	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5284	817	824	FERHC-0843	Tee Sal En Cruz 2 PAVCO	\N	producto	fijo	1.00	0.85	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5285	817	824	FERHC-0844	Tee Sal Sanitaria 2 PAVCO	\N	producto	fijo	4.37	3.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5286	817	824	FERHC-0845	Tee Sal Sanitaria 4 PAVCO	\N	producto	fijo	18.18	15.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5287	817	824	FERHC-0846	Thiner Acrilico Economico Por Litro FM	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5288	817	824	FERHC-0847	Tierra Cultivo S/M	\N	producto	fijo	35.00	29.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5289	817	824	FERHC-0848	Tirafon 1/4x 1 S/M	\N	producto	fijo	0.06	0.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5290	817	824	FERHC-0849	Tirafon Hex 1/4 X 3 S/M	\N	producto	fijo	0.22	0.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5291	817	824	FERHC-0850	Tirafon Hex 1/4 x 2 1/2 S/M	\N	producto	fijo	0.18	0.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5292	817	824	FERHC-0851	Tirafon Hex 1/4 x 2 S/M	\N	producto	fijo	0.09	0.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5293	817	824	FERHC-0852	Tirafon Hex 1/4 x 4 S/M	\N	producto	fijo	0.25	0.21	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5294	817	824	FERHC-0853	Tiralinea 30M Cuerpo De Plastico Y Nivel TRUPER	\N	producto	fijo	10.43	8.84	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5295	817	824	FERHC-0854	Tomacorriente Doble P/Empotrado TICINO	\N	producto	fijo	15.92	13.49	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5296	817	824	FERHC-0855	Tomacorriente Doble/Emp HOME LIGHT	\N	producto	fijo	2.06	1.75	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5297	817	824	FERHC-0856	Tomacorriente Simple Emp SCHNEIDER	\N	producto	fijo	4.11	3.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5298	817	824	FERHC-0857	Tomacorriente Simple P/Empotrado TICINO	\N	producto	fijo	9.50	8.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5299	817	824	FERHC-0858	Tomacorriente Simple P/Sobre HOME LIGHT	\N	producto	fijo	1.11	0.94	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5300	817	824	FERHC-0859	Tomacorriente Triple Sobreponer HOME LIGHT	\N	producto	fijo	1.82	1.54	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5301	817	824	FERHC-0860	Tortol 3/8 S/M	\N	producto	fijo	2.01	1.70	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5302	817	824	FERHC-0861	Trampa Flexible Y Desague P/Lav 1.1/4-1.1/2 C&A	\N	producto	fijo	6.05	5.13	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5303	817	824	FERHC-0862	Trampa P 2 PAVCO	\N	producto	fijo	9.65	8.18	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5304	817	824	FERHC-0863	Trampa Para Noque Sin Hueco TITOMAX	\N	producto	fijo	5.50	4.66	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5305	817	824	FERHC-0864	Trapeador Microfibra 4575 Cm S/M	\N	producto	fijo	3.58	3.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5306	817	824	FERHC-0865	Tubo Abasto P/Inodoro 7/8 Naylon C&A	\N	producto	fijo	1.79	1.52	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5307	817	824	FERHC-0866	Tubo Abasto P/Lavatorio 1/2 Nylon C&A	\N	producto	fijo	2.21	1.87	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5308	817	824	FERHC-0867	Tubo Cpvc 1/2 Sp PAVCO	\N	producto	fijo	23.51	19.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5309	817	824	FERHC-0868	Tubo Cpvc 3/4 Sp PAVCO	\N	producto	fijo	39.49	33.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5310	817	824	FERHC-0869	Tubo Pvc C-10 1 C/r PAVCO	\N	producto	fijo	32.98	27.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5311	817	824	FERHC-0870	Tubo Pvc C-10 1 Sp PAVCO	\N	producto	fijo	20.00	16.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5312	817	824	FERHC-0871	Tubo Pvc C-10 1 Sp PLASTICA	\N	producto	fijo	9.99	8.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5313	817	824	FERHC-0872	Tubo Pvc C-10 1/2 C/R PAVCO	\N	producto	fijo	17.00	14.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5314	817	824	FERHC-0873	Tubo Pvc C-10 1/2 C/R PLASTICA	\N	producto	fijo	9.70	8.22	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5315	817	824	FERHC-0874	Tubo Pvc C-10 1/2 Sp PAVCO	\N	producto	fijo	12.70	10.76	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5316	817	824	FERHC-0875	Tubo Pvc C-10 3/4 C/R GERFOR	\N	producto	fijo	13.70	11.61	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5317	817	824	FERHC-0876	Tubo Pvc C-10 3/4 C/R PLASTICA	\N	producto	fijo	12.00	10.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5318	817	824	FERHC-0877	Tubo Pvc C-10 3/4 Sp PAVCO	\N	producto	fijo	15.10	12.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5319	817	824	FERHC-0878	Tubo Sal 2 PAVCO	\N	producto	fijo	12.50	10.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5320	817	824	FERHC-0879	Tubo Sal 2 PLASTICA	\N	producto	fijo	6.60	5.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5321	817	824	FERHC-0880	Tubo Sal 3 PAVCO	\N	producto	fijo	32.00	27.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5322	817	824	FERHC-0881	Tubo Sal 3 PLASTICA	\N	producto	fijo	13.40	11.36	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5323	817	824	FERHC-0882	Tubo Sal 4 PAVCO	\N	producto	fijo	33.50	28.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5324	817	824	FERHC-0883	Tubo Sel Luz 1 PAVCO	\N	producto	fijo	8.40	7.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5325	817	824	FERHC-0884	Tubo Sel Luz 3/4 PAVCO	\N	producto	fijo	4.80	4.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5326	817	824	FERHC-0885	Tubo Sel Luz 5/8 PAVCO	\N	producto	fijo	5.81	4.92	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5327	817	824	FERHC-0886	Tuercas Hex 1/4 Zinc S/M	\N	producto	fijo	0.09	0.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5328	817	824	FERHC-0887	UNION CPVC 3/4 SP PAVCO	\N	producto	fijo	1.12	0.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5329	817	824	FERHC-0888	UNION PVC 1 C/R INYECTOPLAST	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5330	817	824	FERHC-0889	UNION PVC 1 SP PLASTICA	\N	producto	fijo	1.06	0.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5331	817	824	FERHC-0890	UNION PVC 3/4 SP HECHIZA	\N	producto	fijo	0.50	0.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5332	817	824	FERHC-0891	UNION UNIVERSAL 3/4 PVC ERA	\N	producto	fijo	2.50	2.12	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5333	817	824	FERHC-0892	UNION UNIVERSAL CPVC 1/2 PAVCO	\N	producto	fijo	8.58	7.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5334	817	824	FERHC-0893	Union Bronce 1/2 VALMAX	\N	producto	fijo	2.25	1.91	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5335	817	824	FERHC-0894	Union Cpvc 1/2 Sp PAVCO	\N	producto	fijo	0.85	0.72	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5336	817	824	FERHC-0895	Union Fierro G. 1 FIERRO G	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5337	817	824	FERHC-0896	Union Fierro G. 1/2 FIERRO G	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5338	817	824	FERHC-0897	Union Pvc 1 C/R PAVCO	\N	producto	fijo	2.93	2.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5339	817	824	FERHC-0898	Union Pvc 1 Mixta INYECTOPLAST	\N	producto	fijo	1.53	1.30	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5340	817	824	FERHC-0899	Union Pvc 1 Mixta PAVCO	\N	producto	fijo	2.44	2.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5341	817	824	FERHC-0900	Union Pvc 1 Sp PAVCO	\N	producto	fijo	2.93	2.48	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5342	817	824	FERHC-0901	Union Pvc 1/2 C/R NICOL	\N	producto	fijo	0.50	0.42	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5343	817	824	FERHC-0902	Union Pvc 1/2 C/R PAVCO	\N	producto	fijo	1.56	1.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5344	817	824	FERHC-0903	Union Pvc 1/2 Mixta PAVCO	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5345	817	824	FERHC-0904	Union Pvc 1/2 Mixta PLASTICA	\N	producto	fijo	0.80	0.68	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5346	817	824	FERHC-0905	Union Pvc 1/2 SP PLASTICA	\N	producto	fijo	0.37	0.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5347	817	824	FERHC-0906	Union Pvc 1/2 Sp HECHIZA	\N	producto	fijo	0.20	0.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5348	817	824	FERHC-0907	Union Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.12	0.95	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5349	817	824	FERHC-0908	Union Pvc 3/4 C/R PAVCO	\N	producto	fijo	2.14	1.81	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5350	817	824	FERHC-0909	Union Pvc 3/4 C/R PLASTICA	\N	producto	fijo	1.59	1.35	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5351	817	824	FERHC-0910	Union Pvc 3/4 Mixta PAVCO	\N	producto	fijo	2.12	1.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5352	817	824	FERHC-0911	Union Pvc 3/4 Sp PAVCO	\N	producto	fijo	1.89	1.60	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5353	817	824	FERHC-0912	Union Pvc 3/4 Sp PLASTICA	\N	producto	fijo	0.67	0.57	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5354	817	824	FERHC-0913	Union Sal 2 HECHIZA	\N	producto	fijo	0.70	0.59	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5355	817	824	FERHC-0914	Union Sal 2 PAVCO	\N	producto	fijo	1.50	1.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5356	817	824	FERHC-0915	Union Sal 3 PAVCO	\N	producto	fijo	3.22	2.73	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5357	817	824	FERHC-0916	Union Sal 4 PAVCO	\N	producto	fijo	5.40	4.58	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5358	817	824	FERHC-0917	Union Universal 1/2 PAVCO	\N	producto	fijo	2.58	2.19	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5359	817	824	FERHC-0918	Union Universal 1/2 PCP	\N	producto	fijo	2.40	2.03	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5360	817	824	FERHC-0919	Union Univesal 1 PAVCO	\N	producto	fijo	4.81	4.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5361	817	824	FERHC-0920	Union Univesal 3/4 PAVCO	\N	producto	fijo	3.60	3.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5362	817	824	FERHC-0921	U±as Para Lavatorio Aluminio S/M	\N	producto	fijo	2.10	1.78	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5363	817	824	FERHC-0922	U±as Para Lavatorio Fierro Fundido S/M	\N	producto	fijo	3.60	3.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5364	817	824	FERHC-0923	VALVULA CHECK SWING HORIZONTAL 1/2 SWIFT	\N	producto	fijo	8.77	7.43	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5365	817	824	FERHC-0924	VALVULA GAS 24 LB 2G GASPER	\N	producto	fijo	16.50	13.98	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5366	817	824	FERHC-0925	VIDRIO PARA SOLDAR 12 NEGRO S/M	\N	producto	fijo	0.65	0.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5367	817	824	FERHC-0926	Valvula Che 1 ROTOPLAST	\N	producto	fijo	16.69	14.14	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5368	817	824	FERHC-0927	Valvula Check Swing 1 Asiento Goma CIM	\N	producto	fijo	83.90	71.10	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5369	817	824	FERHC-0928	Valvula Gas Equipada C/Manguera SURGE	\N	producto	fijo	14.50	12.29	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5370	817	824	FERHC-0929	Vidrio Para Soldar 11 Negro S/M	\N	producto	fijo	0.65	0.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5371	817	824	FERHC-0930	WALL SOCATE OVALADO VARGYOV	\N	producto	fijo	1.81	1.53	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5372	817	824	FERHC-0931	WINCHA 5 METROS PRETUL	\N	producto	fijo	5.01	4.25	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5373	817	824	FERHC-0932	WINCHA 5 MT C/PROTECTOR KAMASA	\N	producto	fijo	5.99	5.08	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5374	817	824	FERHC-0933	WINCHA 5MTS WINGS	\N	producto	fijo	3.72	3.15	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5375	817	824	FERHC-0934	WINCHA AUTO-LOCK 5 MTS C/AMARILLA TRUPER	\N	producto	fijo	12.12	10.27	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5376	817	824	FERHC-0935	WINCHA AUTO-LOCK 8 MTS C/AMARILLA TRUPER	\N	producto	fijo	21.72	18.41	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5377	817	824	FERHC-0936	WINCHA PASACABLE X 10 METROS TRUPER	\N	producto	fijo	7.16	6.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5378	817	824	FERHC-0937	WINCHA PASACABLE X 15 METROS TRUPER	\N	producto	fijo	11.00	9.32	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5379	817	824	FERHC-0938	WINCHA PASACABLE X 20 METROS TRUPER	\N	producto	fijo	9.90	8.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5380	817	824	FERHC-0939	YESO BOLSA X 15 KG S/M	\N	producto	fijo	3.30	2.80	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5381	817	824	FERHC-0940	YESO CERAMICO X 1KG KOLORCIX	\N	producto	fijo	2.88	2.44	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5382	817	824	FERHC-0941	Yee Sal 2 PAVCO	\N	producto	fijo	4.00	3.39	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5383	817	824	FERHC-0942	Yee Sal 2 PLASTICA	\N	producto	fijo	2.42	2.05	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5384	817	824	FERHC-0943	Yee Sal 3 PAVCO	\N	producto	fijo	7.92	6.71	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5385	817	824	FERHC-0944	Yee Sal 3 PLASTICA	\N	producto	fijo	3.62	3.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5386	817	824	FERHC-0945	Yee Sal 4 A 2 PAVCO	\N	producto	fijo	8.91	7.55	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5387	817	824	FERHC-0946	Yee Sal 4 A 2 PLASTICA	\N	producto	fijo	3.91	3.31	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5388	817	824	FERHC-0947	Yee Sal 4 A 3 PAVCO	\N	producto	fijo	12.00	10.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5389	817	824	FERHC-0948	Yee Sal 4 PAVCO	\N	producto	fijo	14.60	12.37	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5390	817	824	FERHC-0949	Yee Sal 4 PLASTICA	\N	producto	fijo	9.32	7.90	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5391	817	824	FERHC-0950	Yeso Por Kg S/M	\N	producto	fijo	0.20	0.17	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5392	817	824	FERHC-0951	ZAPAPICO 5 LBS C/NEGRO C&A	\N	producto	fijo	14.24	12.07	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
5393	817	824	FERHC-GRANEL	MATERIALES A GRANEL (ladrillo/cemento/agregados)	\N	producto	fijo	39544.47	39544.47	\N	t	2026-07-10 14:09:10	2026-07-10 14:09:10	t	t	f
\.


--
-- Data for Name: proveedor_adelanto_aplicaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proveedor_adelanto_aplicaciones (id, proveedor_adelanto_id, entrada_id, user_id, fecha, monto, observacion, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proveedor_adelantos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proveedor_adelantos (id, empresa_id, proveedor_id, user_id, metodo_pago_id, cuenta_id, fecha, monto, saldo, estado, referencia, observacion, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
7	1	13	1	\N	14	2026-07-02	4500.00	4500.00	activo	\N	Adelanto por pedido de herramientas	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
8	1	14	1	\N	14	2026-07-03	2800.00	2800.00	activo	\N	Adelanto campaña de alambre	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
\.


--
-- Data for Name: proveedores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proveedores (id, empresa_id, tipo_documento, numero_documento, razon_social, nombre_comercial, contacto, telefono, email, direccion, observacion, activo, created_at, updated_at) FROM stdin;
10	1	RUC	20481123457	FERRONOR EIRL	\N	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
11	1	RUC	20392214569	ARDILES IMPORT SRL	\N	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
12	1	RUC	20172214870	COFESA SAC	\N	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
13	1	RUC	20504412987	UYUSTOOLS PERU SAC	\N	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
14	1	RUC	20100127390	PRODAC SA	\N	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
39	817	RUC	20131719559	DEPOSITO PAKATNAMU S.A.C	\N	\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11
40	817	RUC	20103134065	FERRONOR SAC.	\N	\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11
41	817	RUC	20615155412	GRUPO CORPORATIVO HERRERA E.I.R.L.	\N	\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11
42	817	RUC	\N	LADRILLERA RAMOS	\N	\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11
43	817	RUC	\N	ROCA FUERTE - CARLOS	\N	\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11
44	817	RUC	20496166273	SERVICIOS GENERALES ADJ EIRL	\N	\N	\N	\N	\N	\N	t	2026-07-10 14:09:11	2026-07-10 14:09:11
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, empresa_id, nombre, descripcion, es_admin, activo, created_at, updated_at, max_descuento_porcentaje) FROM stdin;
1	1	Administrador	Dueña — acceso total	t	t	2026-05-18 01:53:39	2026-05-18 01:53:39	\N
2	1	Cajera	Vende, cobra, abre y cierra turno. No edita catálogo ni configuración.	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39	10.00
870	817	Administrador	Acceso total	t	t	2026-07-10 14:09:10	2026-07-10 14:09:10	\N
871	817	Cajera	Vende, cobra, abre y cierra turno.	f	t	2026-07-10 14:09:10	2026-07-10 14:09:10	10.00
\.


--
-- Data for Name: salida_tipos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.salida_tipos (id, empresa_id, nombre, slug, es_sistema, activo, orden, created_at, updated_at) FROM stdin;
1	1	Merma	merma	f	t	1	2026-05-20 20:40:33	2026-05-20 20:40:33
\.


--
-- Data for Name: salidas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.salidas (id, empresa_id, almacen_id, user_id, turno_id, salida_tipo_id, numero_documento, fecha, estado, observacion, total, created_at, updated_at) FROM stdin;
1	1	1	1	\N	1	\N	2026-05-20	confirmado	\N	910.00	2026-05-20 20:40:52	2026-05-20 20:40:52
\.


--
-- Data for Name: salidas_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.salidas_detalle (id, salida_id, producto_id, unidad_medida_id, cantidad, factor_conversion, cantidad_base, costo_unitario, subtotal, observacion, created_at, updated_at) FROM stdin;
1	1	42	1	29.0000	1.0000	29.0000	31.3793	910.00	\N	2026-05-20 20:40:52	2026-05-20 20:40:52
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
QbNWEy3bEa2lVzQNX20fVry6Xg7HKR3CPd1BsmhW	1	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	YTo0OntzOjY6Il90b2tlbiI7czo0MDoiaXpJZHJFMndBTVZTaDJEamFZdXFXUFJjRWhEWUY0b1h6WkhNMGJJWiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czo0OToiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2ZpbmFuemFzL2JhbGFuY2UvMjAyNi0wNy0wNiI7czo1OiJyb3V0ZSI7czoyMToiZmluYW56YXMuYmFsYW5jZS5zaG93Ijt9fQ==	1783647505
anHS2uqkHxz0ypoGJmvGkNjkFqfgZd00xioBl7Lm	957	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	YTo0OntzOjY6Il90b2tlbiI7czo0MDoiNTg5dktTT3kwYU0yMXQwY0l3bWQ2ZmZrQ1g3Nkc0RFN6SzNXeHBVUyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9maW5hbnphcy9hbnRpY2lwb3MiO3M6NToicm91dGUiO3M6MjQ6ImZpbmFuemFzLmFudGljaXBvcy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjk1Nzt9	1783709032
\.


--
-- Data for Name: stock; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock (id, almacen_id, producto_id, cantidad, costo_promedio, created_at, updated_at) FROM stdin;
2	1	2	20.0000	35.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
3	1	3	18.0000	48.9500	2026-05-18 01:53:39	2026-05-18 01:53:39
4	1	4	12.0000	66.0000	2026-05-18 01:53:39	2026-05-18 01:53:39
5	1	5	35.0000	41.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
6	1	6	22.0000	38.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
7	1	7	25.0000	33.0000	2026-05-18 01:53:39	2026-05-18 01:53:39
8	1	8	28.0000	27.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
9	1	9	40.0000	30.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
10	1	10	15.0000	52.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
12	1	12	30.0000	35.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
13	1	13	18.0000	48.9500	2026-05-18 01:53:39	2026-05-18 01:53:39
15	1	15	15.0000	52.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
16	1	16	25.0000	24.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
17	1	17	30.0000	19.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
18	1	18	22.0000	30.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
19	1	19	20.0000	33.0000	2026-05-18 01:53:39	2026-05-18 01:53:39
20	1	20	25.0000	22.0000	2026-05-18 01:53:39	2026-05-18 01:53:39
21	1	21	18.0000	27.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
22	1	22	16.0000	35.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
23	1	23	25.0000	30.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
24	1	24	15.0000	60.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
25	1	25	20.0000	79.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
26	1	26	12.0000	71.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
27	1	27	30.0000	19.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
28	1	28	50.0000	15.4000	2026-05-18 01:53:39	2026-05-18 01:53:39
29	1	29	50.0000	15.4000	2026-05-18 01:53:39	2026-05-18 01:53:39
30	1	30	45.0000	12.1000	2026-05-18 01:53:39	2026-05-18 01:53:39
31	1	31	30.0000	30.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
32	1	32	35.0000	24.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
33	1	33	18.0000	41.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
34	1	34	40.0000	19.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
35	1	35	50.0000	13.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
36	1	36	25.0000	16.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
37	1	37	20.0000	35.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
38	1	38	25.0000	48.9500	2026-05-18 01:53:39	2026-05-18 01:53:39
39	1	39	30.0000	30.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
40	1	40	35.0000	24.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
41	1	41	40.0000	16.5000	2026-05-18 01:53:39	2026-05-18 01:53:39
43	1	43	10.0000	99.0000	2026-05-18 01:53:39	2026-05-18 01:53:39
44	1	44	8.0000	121.0000	2026-05-18 01:53:39	2026-05-18 01:53:39
45	1	45	10.0000	79.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
46	1	46	60.0000	9.9000	2026-05-18 01:53:39	2026-05-18 01:53:39
47	1	47	30.0000	19.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
48	1	48	25.0000	24.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
49	1	49	35.0000	15.4000	2026-05-18 01:53:39	2026-05-18 01:53:39
50	1	50	18.0000	41.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
51	1	51	25.0000	35.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
52	1	52	40.0000	19.2500	2026-05-18 01:53:39	2026-05-18 01:53:39
53	1	53	50.0000	13.7500	2026-05-18 01:53:39	2026-05-18 01:53:39
14	1	14	13.0000	72.4615	2026-05-18 01:53:39	2026-05-20 18:36:31
11	1	11	15.0000	63.1333	2026-05-18 01:53:39	2026-05-20 19:11:54
42	1	42	0.0000	31.3793	2026-05-18 01:53:39	2026-05-20 20:40:52
1	1	1	28.0000	24.7500	2026-05-18 01:53:39	2026-07-05 15:08:33
510	1	311	992.0000	23.7800	2026-07-05 19:27:37	2026-07-09 19:56:19
511	1	312	695.0000	4.1000	2026-07-05 19:27:37	2026-07-09 19:56:19
514	1	315	119.0000	38.0000	2026-07-05 19:27:37	2026-07-09 19:56:19
509	1	310	739.0000	18.9000	2026-07-05 19:27:37	2026-07-09 19:56:19
508	1	309	619.0000	32.8000	2026-07-05 19:27:37	2026-07-09 19:56:19
512	1	313	449.0000	3.9000	2026-07-05 19:27:37	2026-07-09 19:56:19
505	1	306	59999.0000	0.5800	2026-07-05 19:27:37	2026-07-09 19:56:19
515	1	316	89.0000	52.0000	2026-07-05 19:27:37	2026-07-09 19:56:19
4898	826	4442	102.0000	0.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
4899	826	4443	13.0000	9.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
4900	826	4444	9.0000	12.3300	2026-07-10 14:09:10	2026-07-10 14:09:10
4901	826	4445	10.0000	3.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
504	1	305	85000.0000	0.8500	2026-07-05 19:27:37	2026-07-05 19:27:37
506	1	307	18000.0000	1.9000	2026-07-05 19:27:37	2026-07-05 19:27:37
513	1	314	380.0000	28.5000	2026-07-05 19:27:37	2026-07-05 19:27:37
4902	826	4446	13.0000	5.7400	2026-07-10 14:09:10	2026-07-10 14:09:10
507	1	308	843.0000	26.5000	2026-07-05 19:27:37	2026-07-05 20:17:50
4903	826	4447	1.0000	14.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
4904	826	4448	51.0000	2.8600	2026-07-10 14:09:10	2026-07-10 14:09:10
4905	826	4449	20.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
4906	826	4450	2.0000	1.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
4907	826	4451	23.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
4908	826	4452	38.0000	1.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
4909	826	4453	20.0000	37.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
4910	826	4454	16.0000	0.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
4911	826	4455	6.0000	9.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
4912	826	4456	3.0000	6.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
4913	826	4457	2.0000	31.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
4914	826	4458	100.0000	0.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
4915	826	4459	1.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
4916	826	4460	61.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
4917	826	4461	67.0000	0.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
4918	826	4462	108.0000	0.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
4919	826	4463	125.0000	0.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
4920	826	4464	115.0000	1.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
4921	826	4465	60.0000	0.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
4922	826	4466	18.0000	22.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
4923	826	4467	0.0000	6.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
4924	826	4468	826.0000	2.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
4925	826	4469	2517.0000	2.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
4926	826	4470	6.0000	26.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
4927	826	4471	15.0000	0.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
4928	826	4472	12.0000	7.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
4929	826	4473	4.0000	6.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
4930	826	4474	3.0000	5.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
4931	826	4475	2.0000	7.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
4932	826	4476	9.0000	1.9100	2026-07-10 14:09:10	2026-07-10 14:09:10
4933	826	4477	28.0000	3.4500	2026-07-10 14:09:10	2026-07-10 14:09:10
4934	826	4478	11.0000	3.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
4935	826	4479	16.0000	4.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
4936	826	4480	21.5000	32.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
4937	826	4481	5.0100	0.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
4938	826	4482	5.0000	16.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
4939	826	4483	64.0000	0.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
4940	826	4484	68.0000	0.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
4941	826	4485	8.0000	4.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
4942	826	4486	6.0000	2.3400	2026-07-10 14:09:10	2026-07-10 14:09:10
4943	826	4487	9.0000	2.1800	2026-07-10 14:09:10	2026-07-10 14:09:10
4944	826	4488	16.0000	0.6400	2026-07-10 14:09:10	2026-07-10 14:09:10
4945	826	4489	6.0000	2.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
4946	826	4490	14.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
4947	826	4491	11.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
4948	826	4492	12.0000	1.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
4949	826	4493	16.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
4950	826	4494	15.0000	2.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
4951	826	4495	8.0000	10.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
4952	826	4496	76.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
4953	826	4497	81.0000	0.6700	2026-07-10 14:09:10	2026-07-10 14:09:10
4954	826	4498	9.0000	1.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
4955	826	4499	7.0000	0.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
4956	826	4500	34.0000	1.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
4957	826	4501	13.0000	3.7400	2026-07-10 14:09:10	2026-07-10 14:09:10
4958	826	4502	3.0000	4.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
4959	826	4503	4.0000	2.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
4960	826	4504	14.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
4961	826	4505	9.0000	1.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
4962	826	4506	7.0000	2.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
4963	826	4507	6.0000	2.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
4964	826	4508	10.0000	1.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
4965	826	4509	8.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
4966	826	4510	3.0000	8.1300	2026-07-10 14:09:10	2026-07-10 14:09:10
4967	826	4511	17.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
4968	826	4512	5.0000	1.7200	2026-07-10 14:09:10	2026-07-10 14:09:10
4969	826	4513	2.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
4970	826	4514	7.0000	3.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
4971	826	4515	5.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
4972	826	4516	2.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
4973	826	4517	9.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
4974	826	4518	21.0000	0.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
4975	826	4519	22.0000	1.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
4976	826	4520	12.0000	1.3300	2026-07-10 14:09:10	2026-07-10 14:09:10
4977	826	4521	11.0000	2.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
4978	826	4522	12.0000	0.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
4979	826	4523	12.0000	2.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
4980	826	4524	13.0000	3.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
4981	826	4525	24.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
4982	826	4526	70.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
4983	826	4527	34.0000	0.6200	2026-07-10 14:09:10	2026-07-10 14:09:10
4984	826	4528	8.0000	0.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
4985	826	4529	2.0000	14.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
4986	826	4530	937.0000	1.8400	2026-07-10 14:09:10	2026-07-10 14:09:10
4987	826	4531	856.2000	1.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
4988	826	4532	9.0000	17.1300	2026-07-10 14:09:10	2026-07-10 14:09:10
4989	826	4533	12.0000	7.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
4990	826	4534	13.0000	7.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
4991	826	4535	18.0000	7.7700	2026-07-10 14:09:10	2026-07-10 14:09:10
4992	826	4536	6.0000	7.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
4993	826	4537	8.0000	2.0600	2026-07-10 14:09:10	2026-07-10 14:09:10
4994	826	4538	296.5000	5.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
4995	826	4539	447.0000	10.8600	2026-07-10 14:09:10	2026-07-10 14:09:10
4996	826	4540	55.0000	17.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
4997	826	4541	17.0000	0.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
4998	826	4542	3.0000	10.0100	2026-07-10 14:09:10	2026-07-10 14:09:10
4999	826	4543	9.0000	84.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5000	826	4544	3.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5001	826	4545	11.0000	26.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5002	826	4546	228.0000	1.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5003	826	4547	90.0000	24.8700	2026-07-10 14:09:10	2026-07-10 14:09:10
5004	826	4548	713.0000	25.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5005	826	4549	2.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5006	826	4550	5.0000	8.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5007	826	4551	3.0000	1.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5008	826	4552	2.0000	1.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5009	826	4553	1.0000	2.6500	2026-07-10 14:09:10	2026-07-10 14:09:10
5010	826	4554	6.0000	9.2600	2026-07-10 14:09:10	2026-07-10 14:09:10
5011	826	4555	79.0000	2.9900	2026-07-10 14:09:10	2026-07-10 14:09:10
5012	826	4556	51.0000	1.3800	2026-07-10 14:09:10	2026-07-10 14:09:10
5013	826	4557	6.0000	3.6500	2026-07-10 14:09:10	2026-07-10 14:09:10
5014	826	4558	1.0000	3.6500	2026-07-10 14:09:10	2026-07-10 14:09:10
5015	826	4559	35.0000	5.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5016	826	4560	45.7000	1.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5017	826	4561	774.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5018	826	4562	26.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5019	826	4563	142.0000	0.0600	2026-07-10 14:09:10	2026-07-10 14:09:10
5020	826	4564	508.0000	0.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5021	826	4565	524.0000	0.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5022	826	4566	11.9500	3.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5023	826	4567	29.1000	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5024	826	4568	42.1500	4.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5025	826	4569	1.0000	1.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5026	826	4570	158.0000	0.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5027	826	4571	136.0000	0.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5028	826	4572	44.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5029	826	4573	46.0000	5.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5030	826	4574	42.0000	3.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5031	826	4575	11.0000	5.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5032	826	4576	7.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5033	826	4577	2.0000	27.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
5034	826	4578	35.0000	4.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5035	826	4579	31.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5036	826	4580	1.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5037	826	4581	15.0000	0.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5038	826	4582	1.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5039	826	4583	1.0000	7.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5040	826	4584	27.0000	1.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5041	826	4585	227.5000	1.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5042	826	4586	585.0000	1.9100	2026-07-10 14:09:10	2026-07-10 14:09:10
5043	826	4587	15.0000	3.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5044	826	4588	6.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5045	826	4589	14.0000	8.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5046	826	4590	11.0000	9.9600	2026-07-10 14:09:10	2026-07-10 14:09:10
5047	826	4591	4.0000	13.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5048	826	4592	11.0000	11.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5049	826	4593	10.0000	3.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5050	826	4594	8.0000	5.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5051	826	4595	3.0000	11.8600	2026-07-10 14:09:10	2026-07-10 14:09:10
5052	826	4596	3.0000	4.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5053	826	4597	6.0000	15.2600	2026-07-10 14:09:10	2026-07-10 14:09:10
5054	826	4598	135.0000	0.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5055	826	4599	268.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5056	826	4600	12.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5057	826	4601	112.0000	0.2800	2026-07-10 14:09:10	2026-07-10 14:09:10
5058	826	4602	544.0000	1.2300	2026-07-10 14:09:10	2026-07-10 14:09:10
5059	826	4603	50.0000	5.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5060	826	4604	11.0000	70.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5061	826	4605	56.0000	26.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5062	826	4606	25.0000	14.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5063	826	4607	5.0000	29.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5064	826	4608	12.0000	6.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5065	826	4609	51.0000	0.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5066	826	4610	12.0000	1.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5067	826	4611	12.0000	2.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5068	826	4612	15.0000	3.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5069	826	4613	5.0000	5.0600	2026-07-10 14:09:10	2026-07-10 14:09:10
5070	826	4614	1014.0000	0.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5071	826	4615	14.0000	4.1600	2026-07-10 14:09:10	2026-07-10 14:09:10
5072	826	4616	8.0000	3.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5073	826	4617	10.0000	4.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5074	826	4618	8.0000	5.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5075	826	4619	15.0000	12.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5076	826	4620	13.0000	7.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5077	826	4621	694.0000	28.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5078	826	4622	8.0000	1.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5079	826	4623	13.0000	0.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5080	826	4624	8.0000	55.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5081	826	4625	5.0000	54.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5082	826	4626	1.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5083	826	4627	6.0000	4.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5084	826	4628	101.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5085	826	4629	36.0000	0.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5086	826	4630	23.0000	2.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5087	826	4631	14.0000	1.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5088	826	4632	25.0000	4.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5089	826	4633	9.0000	1.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5090	826	4634	1.0000	14.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5091	826	4635	48.0000	0.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5092	826	4636	45.0000	0.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5093	826	4637	7.0000	14.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5094	826	4638	6.0000	18.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5095	826	4639	161.5000	0.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5096	826	4640	148.0000	0.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5097	826	4641	14.3500	4.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5098	826	4642	659.5000	2.9100	2026-07-10 14:09:10	2026-07-10 14:09:10
5099	826	4643	86.9500	3.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5100	826	4644	99.4500	3.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5101	826	4645	86.1000	3.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5102	826	4646	85.5500	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5103	826	4647	41.0500	4.9900	2026-07-10 14:09:10	2026-07-10 14:09:10
5104	826	4648	0.0000	4.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5105	826	4649	22.0000	2.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5106	826	4650	72.0000	0.7200	2026-07-10 14:09:10	2026-07-10 14:09:10
5107	826	4651	66.0000	1.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5108	826	4652	16.0000	2.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5109	826	4653	82.0000	1.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5110	826	4654	6.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5111	826	4655	43.0000	3.7700	2026-07-10 14:09:10	2026-07-10 14:09:10
5112	826	4656	21.0000	1.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5113	826	4657	94.0000	2.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5114	826	4658	14.0000	0.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5115	826	4659	39.0000	1.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5116	826	4660	142.0000	1.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5117	826	4661	19.0000	2.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5118	826	4662	41.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5119	826	4663	107.0000	1.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5120	826	4664	91.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5121	826	4665	6.0000	1.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5122	826	4666	54.0000	1.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5123	826	4667	85.0000	1.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5124	826	4668	182.0000	0.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5125	826	4669	30.0000	4.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5126	826	4670	32.0000	2.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5127	826	4671	23.0000	7.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5128	826	4672	8.0000	4.4900	2026-07-10 14:09:10	2026-07-10 14:09:10
5129	826	4673	12.0000	8.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5130	826	4674	3.0000	4.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5131	826	4675	4.0000	12.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5132	826	4676	3.0000	5.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5133	826	4677	34.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5134	826	4678	568.0000	0.2600	2026-07-10 14:09:10	2026-07-10 14:09:10
5135	826	4679	1231.0000	0.1600	2026-07-10 14:09:10	2026-07-10 14:09:10
5136	826	4680	21.0000	0.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5137	826	4681	12.0000	0.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5138	826	4682	8.0000	0.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5139	826	4683	7.0000	2.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5140	826	4684	4.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5141	826	4685	56.0000	2.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5142	826	4686	10.0000	4.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5143	826	4687	34.0000	5.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5144	826	4688	18.0000	8.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5145	826	4689	23.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5146	826	4690	5.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5147	826	4691	17.0000	12.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5148	826	4692	566.0000	0.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5149	826	4693	362.0000	0.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5150	826	4694	380.0000	0.5200	2026-07-10 14:09:10	2026-07-10 14:09:10
5151	826	4695	397.0000	0.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5152	826	4696	1.0000	5.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5153	826	4697	16.0000	0.7200	2026-07-10 14:09:10	2026-07-10 14:09:10
5154	826	4698	2.0000	0.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
5155	826	4699	11.0000	0.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5156	826	4700	12.0000	2.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5157	826	4701	18.0000	4.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5158	826	4702	13.0000	4.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5159	826	4703	13.0000	12.6400	2026-07-10 14:09:10	2026-07-10 14:09:10
5160	826	4704	204.0000	2.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5161	826	4705	50.0000	1.8600	2026-07-10 14:09:10	2026-07-10 14:09:10
5162	826	4706	28.0000	4.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5163	826	4707	91.0000	3.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5164	826	4708	13.0000	3.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5165	826	4709	16.0000	6.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5166	826	4710	5.0000	1.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5167	826	4711	20.0000	1.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5168	826	4712	1.0000	9.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5169	826	4713	6.0000	6.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5170	826	4714	12.0000	3.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5171	826	4715	12.0000	4.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5172	826	4716	12.0000	1.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5173	826	4717	66.0000	0.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5174	826	4718	5.0000	10.3500	2026-07-10 14:09:10	2026-07-10 14:09:10
5175	826	4719	6.0000	5.5600	2026-07-10 14:09:10	2026-07-10 14:09:10
5176	826	4720	6.0000	6.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5177	826	4721	3.0000	43.6400	2026-07-10 14:09:10	2026-07-10 14:09:10
5178	826	4722	100.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5179	826	4723	111.0000	0.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5180	826	4724	14.0000	1.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5181	826	4725	29.0000	2.9600	2026-07-10 14:09:10	2026-07-10 14:09:10
5182	826	4726	9.0000	6.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5183	826	4727	2.0000	4.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5184	826	4728	5.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5185	826	4729	7.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5186	826	4730	9.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5187	826	4731	7.0000	4.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5188	826	4732	5.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5189	826	4733	4.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5190	826	4734	8.0000	4.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5191	826	4735	3.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5192	826	4736	6.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5193	826	4737	6.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5194	826	4738	7.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5195	826	4739	13.0000	2.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5196	826	4740	4.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5197	826	4741	12.0000	2.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5198	826	4742	10.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5199	826	4743	4.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5200	826	4744	6.0000	2.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5201	826	4745	7.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5202	826	4746	2.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5203	826	4747	3.0000	8.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5204	826	4748	1.0000	8.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5205	826	4749	3.0000	8.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5206	826	4750	7.0000	9.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5207	826	4751	12.0000	8.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5208	826	4752	3.0000	8.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5209	826	4753	3.0000	8.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5210	826	4754	4.0000	8.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5211	826	4755	5.0000	8.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5212	826	4756	5.0000	8.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5213	826	4757	1.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5214	826	4758	2.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5215	826	4759	1.0000	5.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5216	826	4760	1.0000	5.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5217	826	4761	5.0000	5.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5218	826	4762	14.0000	1.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5219	826	4763	9.0000	1.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5220	826	4764	3.0000	1.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5221	826	4765	159.0000	51.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5222	826	4766	140.0000	43.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5223	826	4767	2.0000	13.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5224	826	4768	272.0000	5.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5225	826	4769	22.0000	1.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5226	826	4770	41.0000	2.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5227	826	4771	41.0000	4.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5228	826	4772	1.0000	2.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5229	826	4773	6.0000	15.7400	2026-07-10 14:09:10	2026-07-10 14:09:10
5230	826	4774	1.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5231	826	4775	6.0000	10.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5232	826	4776	4.0000	13.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5233	826	4777	9.0000	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5234	826	4778	8.0000	6.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5235	826	4779	8.0000	8.1800	2026-07-10 14:09:10	2026-07-10 14:09:10
5236	826	4780	4.0000	8.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5237	826	4781	2.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5238	826	4782	4.0000	12.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5239	826	4783	7.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5240	826	4784	8.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5241	826	4785	30.0000	3.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5242	826	4786	11.0000	3.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5243	826	4787	12.0000	0.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5244	826	4788	13.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5245	826	4789	2.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5246	826	4790	17.0000	3.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5247	826	4791	5.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5248	826	4792	7.0000	4.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5249	826	4793	396.0000	27.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5250	826	4794	184.0000	25.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5251	826	4795	16.0000	62.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
5252	826	4796	432.0000	15.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5253	826	4797	133.0000	42.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5254	826	4798	431.0000	6.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5255	826	4799	574.0000	11.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5256	826	4800	5.0000	3.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5257	826	4801	8.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5258	826	4802	3.0000	9.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5259	826	4803	1.0000	3.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5260	826	4804	4.0000	1.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5261	826	4805	40.0000	1.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5262	826	4806	10.0000	4.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5263	826	4807	331.0000	0.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5264	826	4808	642.0000	0.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5265	826	4809	344.0000	0.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5266	826	4810	1.0000	0.3400	2026-07-10 14:09:10	2026-07-10 14:09:10
5267	826	4811	4.0000	17.6200	2026-07-10 14:09:10	2026-07-10 14:09:10
5268	826	4812	169.0000	2.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5269	826	4813	200.0000	1.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5270	826	4814	25.0000	0.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5271	826	4815	2.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5272	826	4816	53.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5273	826	4817	6.0000	2.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5274	826	4818	4.0000	2.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5275	826	4819	4.0000	2.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5276	826	4820	7.0000	2.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5277	826	4821	5.0000	4.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5278	826	4822	7.0000	3.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5279	826	4823	5.0000	16.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5280	826	4824	19.0000	6.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5281	826	4825	3.0000	6.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5282	826	4826	19.0000	0.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5283	826	4827	10.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5284	826	4828	56.0000	0.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5285	826	4829	12.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5286	826	4830	21.0000	1.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5287	826	4831	12.0000	17.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5288	826	4832	104.0000	1.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5289	826	4833	28.0000	10.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5290	826	4834	15.0000	1.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5291	826	4835	52.0000	1.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5292	826	4836	18.0000	8.0100	2026-07-10 14:09:10	2026-07-10 14:09:10
5293	826	4837	66.0000	2.2600	2026-07-10 14:09:10	2026-07-10 14:09:10
5294	826	4838	7.0000	17.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5295	826	4839	12.0000	2.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5296	826	4840	1.0000	94.3500	2026-07-10 14:09:10	2026-07-10 14:09:10
5297	826	4841	1.0000	27.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5298	826	4842	1.0000	31.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5299	826	4843	121.0000	1.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5300	826	4844	65.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5301	826	4845	24.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5302	826	4846	6.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5303	826	4847	2.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5304	826	4848	45.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5305	826	4849	14.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5306	826	4850	71.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5307	826	4851	25.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5308	826	4852	28.0000	1.2300	2026-07-10 14:09:10	2026-07-10 14:09:10
5309	826	4853	4.0000	6.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5310	826	4854	7.0000	5.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5311	826	4855	7.0000	6.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5312	826	4856	5.0000	3.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5313	826	4857	4.0000	35.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5314	826	4858	1.0000	16.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5315	826	4859	1.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5316	826	4860	4.0000	14.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5317	826	4861	6.0000	11.8700	2026-07-10 14:09:10	2026-07-10 14:09:10
5318	826	4862	1.0000	15.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5319	826	4863	12.0000	13.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
5320	826	4864	4.0000	17.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5321	826	4865	5.0000	16.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5322	826	4866	4.0000	10.8900	2026-07-10 14:09:10	2026-07-10 14:09:10
5323	826	4867	2.0000	10.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5324	826	4868	2.0000	3.3300	2026-07-10 14:09:10	2026-07-10 14:09:10
5325	826	4869	5.0000	46.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5326	826	4870	11.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5327	826	4871	7.0000	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5328	826	4872	12.0000	4.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5329	826	4873	165.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5330	826	4874	437.0000	2.1600	2026-07-10 14:09:10	2026-07-10 14:09:10
5331	826	4875	0.0000	2.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5332	826	4876	24.0000	1.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5333	826	4877	13.0000	1.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5334	826	4878	3.0000	0.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5335	826	4879	5.0000	1.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5336	826	4880	132.0000	0.8900	2026-07-10 14:09:10	2026-07-10 14:09:10
5337	826	4881	70.0000	0.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5338	826	4882	184.0000	0.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5339	826	4883	69.0000	1.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5340	826	4884	45.0000	1.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5341	826	4885	27.0000	1.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
5342	826	4886	35.0000	1.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5343	826	4887	28.0000	1.0600	2026-07-10 14:09:10	2026-07-10 14:09:10
5344	826	4888	33.0000	1.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5345	826	4889	3.0000	16.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5346	826	4890	8.0000	27.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5347	826	4891	7.0000	9.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5348	826	4892	2.0000	15.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5349	826	4893	5.0000	13.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5350	826	4894	9.0000	13.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5351	826	4895	5.0000	12.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5352	826	4896	2.0000	12.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5353	826	4897	6.0000	7.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5354	826	4898	6.0000	9.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5355	826	4899	7.0000	6.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5356	826	4900	9.0000	0.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
5357	826	4901	10.0000	0.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5358	826	4902	12.0000	1.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5359	826	4903	10.0000	1.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5360	826	4904	17.0000	1.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5361	826	4905	10.0000	1.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
5362	826	4906	15.0000	1.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
5363	826	4907	14.0000	2.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5364	826	4908	8.0000	0.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5365	826	4909	10.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5366	826	4910	12.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5367	826	4911	5.0000	22.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5368	826	4912	6.0000	33.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5369	826	4913	9.0000	6.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5370	826	4914	15.0000	7.1800	2026-07-10 14:09:10	2026-07-10 14:09:10
5371	826	4915	46.0000	2.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5372	826	4916	31.0000	4.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5373	826	4917	5.0000	20.3400	2026-07-10 14:09:10	2026-07-10 14:09:10
5374	826	4918	9.0000	30.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5375	826	4919	9.0000	30.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5376	826	4920	11.0000	29.8400	2026-07-10 14:09:10	2026-07-10 14:09:10
5377	826	4921	6.0000	30.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5378	826	4922	5.0000	22.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5379	826	4923	9.0000	21.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5380	826	4924	4.0000	23.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5381	826	4925	4.0000	10.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5382	826	4926	4.0000	11.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5383	826	4927	120.0000	3.1600	2026-07-10 14:09:10	2026-07-10 14:09:10
5384	826	4928	26.0000	1.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5385	826	4929	47.0000	3.1300	2026-07-10 14:09:10	2026-07-10 14:09:10
5386	826	4930	8.0000	4.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5387	826	4931	94.0000	0.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5388	826	4932	29.0000	1.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5389	826	4933	115.0000	2.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5390	826	4934	56.0000	1.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5391	826	4935	81.0000	0.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5392	826	4936	3.0000	10.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5393	826	4937	6.0000	8.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5394	826	4938	7.0000	8.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5395	826	4939	16.0000	1.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5396	826	4940	174.0000	1.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5397	826	4941	108.0000	1.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5398	826	4942	61.7000	4.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5399	826	4943	33.5000	1.3800	2026-07-10 14:09:10	2026-07-10 14:09:10
5400	826	4944	192.0000	0.9600	2026-07-10 14:09:10	2026-07-10 14:09:10
5401	826	4945	51.0000	1.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5402	826	4946	1.0000	0.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5403	826	4947	122.0000	0.4500	2026-07-10 14:09:10	2026-07-10 14:09:10
5404	826	4948	2.0000	19.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5405	826	4949	2.0000	22.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5406	826	4950	31.0000	8.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5407	826	4951	4.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5408	826	4952	9.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5409	826	4953	35.0000	0.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5410	826	4954	105.0000	0.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5411	826	4955	30.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5412	826	4956	46.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5413	826	4957	94.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5414	826	4958	47.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5415	826	4959	98.0000	0.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5416	826	4960	5.0000	0.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5417	826	4961	6.0000	11.3800	2026-07-10 14:09:10	2026-07-10 14:09:10
5418	826	4962	6.0000	16.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5419	826	4963	5.0000	20.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5420	826	4964	4.0000	27.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5421	826	4965	12.0000	3.0100	2026-07-10 14:09:10	2026-07-10 14:09:10
5422	826	4966	6.0000	3.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5423	826	4967	6.0000	7.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5424	826	4968	4.0000	2.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5425	826	4969	5.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5426	826	4970	8.0000	4.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5427	826	4971	5.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5428	826	4972	6.0000	6.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5429	826	4973	14.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5430	826	4974	4.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5431	826	4975	48.0000	0.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5432	826	4976	30.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5433	826	4977	13.0000	0.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5434	826	4978	24.0000	0.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5435	826	4979	104.0000	0.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5436	826	4980	78.0000	0.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5437	826	4981	67.0000	0.3800	2026-07-10 14:09:10	2026-07-10 14:09:10
5438	826	4982	31.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5439	826	4983	4.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5440	826	4984	20.0000	3.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5441	826	4985	49.0000	2.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5442	826	4986	31.0000	2.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5443	826	4987	24.0000	3.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5444	826	4988	4.0000	5.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5445	826	4989	21.0000	0.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5446	826	4990	70.0000	0.3400	2026-07-10 14:09:10	2026-07-10 14:09:10
5447	826	4991	1.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5448	826	4992	2.0000	27.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5449	826	4993	3.0000	27.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5450	826	4994	1.0000	6.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5451	826	4995	1.0000	7.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5452	826	4996	3.0000	10.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5453	826	4997	73.0000	0.2300	2026-07-10 14:09:10	2026-07-10 14:09:10
5454	826	4998	4.0000	0.2800	2026-07-10 14:09:10	2026-07-10 14:09:10
5455	826	4999	11.0000	33.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5456	826	5000	2.0000	3.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
5457	826	5001	1.0000	0.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5458	826	5002	10.0000	0.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5459	826	5003	9.0000	0.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5460	826	5004	3.0000	13.5600	2026-07-10 14:09:10	2026-07-10 14:09:10
5461	826	5005	4.0000	11.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5462	826	5006	5.0000	11.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5463	826	5007	5.0000	10.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5464	826	5008	5.0000	11.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5465	826	5009	5.0000	10.6500	2026-07-10 14:09:10	2026-07-10 14:09:10
5466	826	5010	5.0000	10.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5467	826	5011	2.0000	11.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5468	826	5012	3.0000	10.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5469	826	5013	3.0000	12.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5470	826	5014	4.0000	12.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5471	826	5015	4.0000	11.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5472	826	5016	3.0000	10.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5473	826	5017	3.0000	10.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5474	826	5018	9.0000	2.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5475	826	5019	51.0000	2.4900	2026-07-10 14:09:10	2026-07-10 14:09:10
5476	826	5020	11.0000	15.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5477	826	5021	16.0000	2.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5478	826	5022	12.0000	2.3500	2026-07-10 14:09:10	2026-07-10 14:09:10
5479	826	5023	13.0000	2.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5480	826	5024	12.0000	2.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5481	826	5025	14.0000	2.8900	2026-07-10 14:09:10	2026-07-10 14:09:10
5482	826	5026	10.0000	3.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5483	826	5027	79.0000	2.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5484	826	5028	25.0000	2.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5485	826	5029	1.0000	3.8900	2026-07-10 14:09:10	2026-07-10 14:09:10
5486	826	5030	13.0000	7.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5487	826	5031	6.0000	6.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5488	826	5032	10.0000	5.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5489	826	5033	1.0000	9.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5490	826	5034	116.5000	1.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5491	826	5035	180.0000	2.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5492	826	5036	50.0000	0.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5493	826	5037	150.0000	0.0100	2026-07-10 14:09:10	2026-07-10 14:09:10
5494	826	5038	22.0000	0.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5495	826	5039	14.0000	9.8400	2026-07-10 14:09:10	2026-07-10 14:09:10
5496	826	5040	4.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5497	826	5041	3.0000	5.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5498	826	5042	6.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5499	826	5043	160.0000	8.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5500	826	5044	7.0000	38.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5501	826	5045	9.0000	32.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5502	826	5046	11.0000	22.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5503	826	5047	12.0000	3.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5504	826	5048	4.0000	12.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5505	826	5049	107.0000	2.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5506	826	5050	99.0000	13.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5507	826	5051	55.0000	5.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5508	826	5052	23.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5509	826	5053	23.0000	1.3500	2026-07-10 14:09:10	2026-07-10 14:09:10
5510	826	5054	3.7500	29.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5511	826	5055	5.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5512	826	5056	5.0000	50.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5513	826	5057	6.0000	50.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5514	826	5058	2.0000	0.4900	2026-07-10 14:09:10	2026-07-10 14:09:10
5515	826	5059	4.0000	0.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5516	826	5060	4.0000	11.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5517	826	5061	3.0000	11.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5518	826	5062	11.0000	11.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5519	826	5063	7.0000	11.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5520	826	5064	11.0000	2.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5521	826	5065	15.0000	2.4300	2026-07-10 14:09:10	2026-07-10 14:09:10
5522	826	5066	14.0000	2.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5523	826	5067	12.0000	2.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5524	826	5068	9.0000	2.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5525	826	5069	16.0000	2.4300	2026-07-10 14:09:10	2026-07-10 14:09:10
5526	826	5070	18.0000	2.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5527	826	5071	12.0000	2.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5528	826	5072	17.0000	2.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5529	826	5073	18.0000	3.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5530	826	5074	14.0000	2.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5531	826	5075	12.0000	2.5600	2026-07-10 14:09:10	2026-07-10 14:09:10
5532	826	5076	20.0000	2.6400	2026-07-10 14:09:10	2026-07-10 14:09:10
5533	826	5077	34.0000	3.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5534	826	5078	11.0000	2.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5535	826	5079	25.0000	2.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5536	826	5080	9.0000	2.6700	2026-07-10 14:09:10	2026-07-10 14:09:10
5537	826	5081	10.0000	2.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5538	826	5082	6.0000	2.5200	2026-07-10 14:09:10	2026-07-10 14:09:10
5539	826	5083	10.0000	2.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5540	826	5084	3.0000	2.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
5541	826	5085	8.0000	10.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5542	826	5086	3.0000	9.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5543	826	5087	278.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5544	826	5088	265.0000	0.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5545	826	5089	33.0000	0.1100	2026-07-10 14:09:10	2026-07-10 14:09:10
5546	826	5090	3.0000	2.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5547	826	5091	270.0000	7.5200	2026-07-10 14:09:10	2026-07-10 14:09:10
5548	826	5092	521.0000	6.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5549	826	5093	1.0000	26.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5550	826	5094	1.0000	11.8600	2026-07-10 14:09:10	2026-07-10 14:09:10
5551	826	5095	22.0000	3.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5552	826	5096	12.0000	14.8300	2026-07-10 14:09:10	2026-07-10 14:09:10
5553	826	5097	19.0000	1.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5554	826	5098	8.0000	1.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5555	826	5099	20.0000	1.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5556	826	5100	2.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5557	826	5101	3.0000	22.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5558	826	5102	8.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5559	826	5103	22.0000	0.7900	2026-07-10 14:09:10	2026-07-10 14:09:10
5560	826	5104	15.0000	0.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5561	826	5105	70.0000	1.6200	2026-07-10 14:09:10	2026-07-10 14:09:10
5562	826	5106	142.0000	1.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5563	826	5107	34.0000	1.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5564	826	5108	1.0000	0.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5565	826	5109	37.0000	1.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5566	826	5110	14.0000	3.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5567	826	5111	24.0000	3.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5568	826	5112	23.0000	2.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5569	826	5113	12.0000	5.4000	2026-07-10 14:09:10	2026-07-10 14:09:10
5570	826	5114	30.0000	2.6400	2026-07-10 14:09:10	2026-07-10 14:09:10
5571	826	5115	7.0000	5.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5572	826	5116	17.0000	8.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5573	826	5117	1.0000	8.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5574	826	5118	2.0000	8.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5575	826	5119	2.0000	2.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5576	826	5120	44.0000	2.7200	2026-07-10 14:09:10	2026-07-10 14:09:10
5577	826	5121	14.0000	12.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5578	826	5122	26.0000	5.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5579	826	5123	8.0000	5.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5580	826	5124	25.0000	6.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5581	826	5125	12.0000	6.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5582	826	5126	49.0000	1.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5583	826	5127	82.0000	1.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5584	826	5128	9.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5585	826	5129	33.0000	1.6900	2026-07-10 14:09:10	2026-07-10 14:09:10
5586	826	5130	33.0000	1.4500	2026-07-10 14:09:10	2026-07-10 14:09:10
5587	826	5131	4.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5588	826	5132	15.0000	1.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5589	826	5133	15.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5590	826	5134	14.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5591	826	5135	14.0000	1.5600	2026-07-10 14:09:10	2026-07-10 14:09:10
5592	826	5136	20.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5593	826	5137	11.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5594	826	5138	25.0000	1.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5595	826	5139	23.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5596	826	5140	51.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5597	826	5141	41.0000	0.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5598	826	5142	25.0000	0.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5599	826	5143	66.0000	0.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5600	826	5144	98.0000	2.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5601	826	5145	6.0000	18.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5602	826	5146	6.0000	10.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5603	826	5147	14.0000	4.0600	2026-07-10 14:09:10	2026-07-10 14:09:10
5604	826	5148	2.0000	4.4500	2026-07-10 14:09:10	2026-07-10 14:09:10
5605	826	5149	9.0000	3.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5606	826	5150	1.0000	9.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5607	826	5151	27.0000	0.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5608	826	5152	1.0000	0.0100	2026-07-10 14:09:10	2026-07-10 14:09:10
5609	826	5153	41.0000	1.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5610	826	5154	12.0000	7.3800	2026-07-10 14:09:10	2026-07-10 14:09:10
5611	826	5155	63.7500	14.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5612	826	5156	12.0000	6.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5613	826	5157	61.0000	0.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5614	826	5158	734.0000	0.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5615	826	5159	116.0000	0.0400	2026-07-10 14:09:10	2026-07-10 14:09:10
5616	826	5160	136.0000	0.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5617	826	5161	30.0000	0.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5618	826	5162	58.0000	1.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
5619	826	5163	32.0000	2.0100	2026-07-10 14:09:10	2026-07-10 14:09:10
5620	826	5164	37.0000	1.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5621	826	5165	79.0000	0.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5622	826	5166	11.0000	2.2300	2026-07-10 14:09:10	2026-07-10 14:09:10
5623	826	5167	8.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5624	826	5168	151.0000	1.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5625	826	5169	240.0000	0.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5626	826	5170	22.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5627	826	5171	9.0000	3.6500	2026-07-10 14:09:10	2026-07-10 14:09:10
5628	826	5172	21.0000	5.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5629	826	5173	7.0000	3.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5630	826	5174	5.0000	16.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5631	826	5175	6.0000	6.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5632	826	5176	25.0000	2.8200	2026-07-10 14:09:10	2026-07-10 14:09:10
5633	826	5177	28.0000	4.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5634	826	5178	3.0000	19.4900	2026-07-10 14:09:10	2026-07-10 14:09:10
5635	826	5179	1.0000	8.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5636	826	5180	81.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5637	826	5181	9.0000	4.2400	2026-07-10 14:09:10	2026-07-10 14:09:10
5638	826	5182	26.0000	8.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5639	826	5183	136.2900	13.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5640	826	5184	5.0000	6.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5641	826	5185	18.0000	1.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5642	826	5186	10.0000	5.5100	2026-07-10 14:09:10	2026-07-10 14:09:10
5643	826	5187	60.0000	2.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5644	826	5188	11.0000	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5645	826	5189	20.0000	8.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5646	826	5190	4.0000	0.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5647	826	5191	64.0000	7.2000	2026-07-10 14:09:10	2026-07-10 14:09:10
5648	826	5192	228.0000	3.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5649	826	5193	192.0000	4.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
5650	826	5194	45.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5651	826	5195	25.0000	1.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5652	826	5196	98.0000	1.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5653	826	5197	11.0000	6.8400	2026-07-10 14:09:10	2026-07-10 14:09:10
5654	826	5198	7.0000	12.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
5655	826	5199	5.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5656	826	5200	3.0000	7.7400	2026-07-10 14:09:10	2026-07-10 14:09:10
5657	826	5201	100.0000	0.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5658	826	5202	100.0000	0.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5659	826	5203	9.0000	12.2600	2026-07-10 14:09:10	2026-07-10 14:09:10
5660	826	5204	2.0000	13.5600	2026-07-10 14:09:10	2026-07-10 14:09:10
5661	826	5205	7.0000	2.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5662	826	5206	7.0000	9.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5663	826	5207	2.0000	4.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5664	826	5208	530.0000	0.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5665	826	5209	1.0000	6.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
5666	826	5210	251.0000	0.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5667	826	5211	92.0000	1.9100	2026-07-10 14:09:10	2026-07-10 14:09:10
5668	826	5212	99.0000	1.8700	2026-07-10 14:09:10	2026-07-10 14:09:10
5669	826	5213	64.0000	1.4600	2026-07-10 14:09:10	2026-07-10 14:09:10
5670	826	5214	108.0000	1.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5671	826	5215	67.0000	11.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5672	826	5216	1988.0000	0.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5673	826	5217	306.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5674	826	5218	237.0000	0.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5675	826	5219	12.0000	6.0600	2026-07-10 14:09:10	2026-07-10 14:09:10
5676	826	5220	5.0000	8.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5677	826	5221	57.0000	18.4300	2026-07-10 14:09:10	2026-07-10 14:09:10
5678	826	5222	29.0000	32.6300	2026-07-10 14:09:10	2026-07-10 14:09:10
5679	826	5223	10.0000	62.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5680	826	5224	79.0000	0.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5681	826	5225	15.0000	16.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5682	826	5226	89.0000	5.9700	2026-07-10 14:09:10	2026-07-10 14:09:10
5683	826	5227	6.0000	6.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5684	826	5228	126.0000	15.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5685	826	5229	2.0000	76.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5686	826	5230	16.0000	3.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5687	826	5231	353.0000	1.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5688	826	5232	46.0000	1.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5689	826	5233	2.0000	481.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5690	826	5234	8.0000	423.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5691	826	5235	69.0000	0.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5692	826	5236	83.0000	0.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5693	826	5237	34.0000	0.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5694	826	5238	7.0000	1.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5695	826	5239	71.0000	1.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5696	826	5240	50.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5697	826	5241	50.0000	1.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5698	826	5242	53.0000	0.3400	2026-07-10 14:09:10	2026-07-10 14:09:10
5699	826	5243	62.0000	1.0900	2026-07-10 14:09:10	2026-07-10 14:09:10
5700	826	5244	89.0000	0.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5701	826	5245	52.0000	0.9300	2026-07-10 14:09:10	2026-07-10 14:09:10
5702	826	5246	23.0000	0.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5703	826	5247	13.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5704	826	5248	27.0000	1.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5705	826	5249	50.0000	0.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5706	826	5250	22.0000	1.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5707	826	5251	57.0000	1.1800	2026-07-10 14:09:10	2026-07-10 14:09:10
5708	826	5252	37.0000	0.5000	2026-07-10 14:09:10	2026-07-10 14:09:10
5709	826	5253	25.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5710	826	5254	131.0000	1.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5711	826	5255	39.0000	0.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
5712	826	5256	25.0000	1.3400	2026-07-10 14:09:10	2026-07-10 14:09:10
5713	826	5257	154.0000	1.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5714	826	5258	914.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5715	826	5259	587.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5716	826	5260	1000.0000	0.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5717	826	5261	150.0000	5.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5718	826	5262	24.0000	2.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5719	826	5263	46.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5720	826	5264	40.0000	2.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5721	826	5265	39.0000	1.0200	2026-07-10 14:09:10	2026-07-10 14:09:10
5722	826	5266	5.0000	1.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5723	826	5267	13.0000	4.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5724	826	5268	23.0000	2.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5725	826	5269	61.0000	5.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5726	826	5270	27.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5727	826	5271	48.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5728	826	5272	109.0000	1.8600	2026-07-10 14:09:10	2026-07-10 14:09:10
5729	826	5273	113.0000	0.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5730	826	5274	53.0000	2.8800	2026-07-10 14:09:10	2026-07-10 14:09:10
5731	826	5275	52.0000	1.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5732	826	5276	44.0000	2.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5733	826	5277	50.0000	2.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5734	826	5278	13.0000	8.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5735	826	5279	6.0000	0.0000	2026-07-10 14:09:10	2026-07-10 14:09:10
5736	826	5280	24.0000	6.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5737	826	5281	22.0000	3.6400	2026-07-10 14:09:10	2026-07-10 14:09:10
5738	826	5282	22.0000	8.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5739	826	5283	23.0000	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5740	826	5284	52.0000	0.8500	2026-07-10 14:09:10	2026-07-10 14:09:10
5741	826	5285	20.0000	3.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5742	826	5286	34.0000	15.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5743	826	5287	8.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5744	826	5288	1.0000	29.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5745	826	5289	400.0000	0.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5746	826	5290	66.0000	0.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5747	826	5291	1121.0000	0.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5748	826	5292	417.0000	0.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5749	826	5293	251.0000	0.2100	2026-07-10 14:09:10	2026-07-10 14:09:10
5750	826	5294	12.0000	8.8400	2026-07-10 14:09:10	2026-07-10 14:09:10
5751	826	5295	19.0000	13.4900	2026-07-10 14:09:10	2026-07-10 14:09:10
5752	826	5296	117.0000	1.7500	2026-07-10 14:09:10	2026-07-10 14:09:10
5753	826	5297	6.0000	3.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5754	826	5298	15.0000	8.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5755	826	5299	76.0000	0.9400	2026-07-10 14:09:10	2026-07-10 14:09:10
5756	826	5300	58.0000	1.5400	2026-07-10 14:09:10	2026-07-10 14:09:10
5757	826	5301	12.0000	1.7000	2026-07-10 14:09:10	2026-07-10 14:09:10
5758	826	5302	10.0000	5.1300	2026-07-10 14:09:10	2026-07-10 14:09:10
5759	826	5303	7.0000	8.1800	2026-07-10 14:09:10	2026-07-10 14:09:10
5760	826	5304	21.0000	4.6600	2026-07-10 14:09:10	2026-07-10 14:09:10
5761	826	5305	13.0000	3.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5762	826	5306	18.0000	1.5200	2026-07-10 14:09:10	2026-07-10 14:09:10
5763	826	5307	31.0000	1.8700	2026-07-10 14:09:10	2026-07-10 14:09:10
5764	826	5308	9.0000	19.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5765	826	5309	20.0000	33.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5766	826	5310	11.0000	27.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5767	826	5311	30.0000	16.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5768	826	5312	26.0000	8.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
5769	826	5313	31.0000	14.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5770	826	5314	24.0000	8.2200	2026-07-10 14:09:10	2026-07-10 14:09:10
5771	826	5315	72.0000	10.7600	2026-07-10 14:09:10	2026-07-10 14:09:10
5772	826	5316	1.0000	11.6100	2026-07-10 14:09:10	2026-07-10 14:09:10
5773	826	5317	9.0000	10.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5774	826	5318	15.0000	12.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5775	826	5319	87.0000	10.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5776	826	5320	57.0000	5.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5777	826	5321	22.0000	27.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5778	826	5322	19.0000	11.3600	2026-07-10 14:09:10	2026-07-10 14:09:10
5779	826	5323	141.0000	28.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5780	826	5324	56.0000	7.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5781	826	5325	226.0000	4.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5782	826	5326	50.0000	4.9200	2026-07-10 14:09:10	2026-07-10 14:09:10
5783	826	5327	1836.0000	0.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5784	826	5328	32.0000	0.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5785	826	5329	12.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5786	826	5330	63.0000	0.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5787	826	5331	120.0000	0.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5788	826	5332	24.0000	2.1200	2026-07-10 14:09:10	2026-07-10 14:09:10
5789	826	5333	3.0000	7.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5790	826	5334	58.0000	1.9100	2026-07-10 14:09:10	2026-07-10 14:09:10
5791	826	5335	18.0000	0.7200	2026-07-10 14:09:10	2026-07-10 14:09:10
5792	826	5336	9.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5793	826	5337	67.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5794	826	5338	20.0000	2.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5795	826	5339	10.0000	1.3000	2026-07-10 14:09:10	2026-07-10 14:09:10
5796	826	5340	21.0000	2.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5797	826	5341	44.0000	2.4800	2026-07-10 14:09:10	2026-07-10 14:09:10
5798	826	5342	23.0000	0.4200	2026-07-10 14:09:10	2026-07-10 14:09:10
5799	826	5343	23.0000	1.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5800	826	5344	26.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5801	826	5345	44.0000	0.6800	2026-07-10 14:09:10	2026-07-10 14:09:10
5802	826	5346	86.0000	0.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5803	826	5347	138.0000	0.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5804	826	5348	66.0000	0.9500	2026-07-10 14:09:10	2026-07-10 14:09:10
5805	826	5349	48.0000	1.8100	2026-07-10 14:09:10	2026-07-10 14:09:10
5806	826	5350	22.0000	1.3500	2026-07-10 14:09:10	2026-07-10 14:09:10
5807	826	5351	18.0000	1.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5808	826	5352	94.0000	1.6000	2026-07-10 14:09:10	2026-07-10 14:09:10
5809	826	5353	15.0000	0.5700	2026-07-10 14:09:10	2026-07-10 14:09:10
5810	826	5354	4.0000	0.5900	2026-07-10 14:09:10	2026-07-10 14:09:10
5811	826	5355	11.0000	1.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5812	826	5356	17.0000	2.7300	2026-07-10 14:09:10	2026-07-10 14:09:10
5813	826	5357	7.0000	4.5800	2026-07-10 14:09:10	2026-07-10 14:09:10
5814	826	5358	59.0000	2.1900	2026-07-10 14:09:10	2026-07-10 14:09:10
5815	826	5359	87.0000	2.0300	2026-07-10 14:09:10	2026-07-10 14:09:10
5816	826	5360	34.0000	4.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5817	826	5361	34.0000	3.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5818	826	5362	5.0000	1.7800	2026-07-10 14:09:10	2026-07-10 14:09:10
5819	826	5363	16.0000	3.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5820	826	5364	10.0000	7.4300	2026-07-10 14:09:10	2026-07-10 14:09:10
5821	826	5365	12.0000	13.9800	2026-07-10 14:09:10	2026-07-10 14:09:10
5822	826	5366	41.0000	0.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5823	826	5367	8.0000	14.1400	2026-07-10 14:09:10	2026-07-10 14:09:10
5824	826	5368	5.0000	71.1000	2026-07-10 14:09:10	2026-07-10 14:09:10
5825	826	5369	12.0000	12.2900	2026-07-10 14:09:10	2026-07-10 14:09:10
5826	826	5370	5.0000	0.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5827	826	5371	20.0000	1.5300	2026-07-10 14:09:10	2026-07-10 14:09:10
5828	826	5372	4.0000	4.2500	2026-07-10 14:09:10	2026-07-10 14:09:10
5829	826	5373	3.0000	5.0800	2026-07-10 14:09:10	2026-07-10 14:09:10
5830	826	5374	6.0000	3.1500	2026-07-10 14:09:10	2026-07-10 14:09:10
5831	826	5375	6.0000	10.2700	2026-07-10 14:09:10	2026-07-10 14:09:10
5832	826	5376	2.0000	18.4100	2026-07-10 14:09:10	2026-07-10 14:09:10
5833	826	5377	5.0000	6.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5834	826	5378	1.0000	9.3200	2026-07-10 14:09:10	2026-07-10 14:09:10
5835	826	5379	9.0000	8.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5836	826	5380	764.0000	2.8000	2026-07-10 14:09:10	2026-07-10 14:09:10
5837	826	5381	25.0000	2.4400	2026-07-10 14:09:10	2026-07-10 14:09:10
5838	826	5382	49.0000	3.3900	2026-07-10 14:09:10	2026-07-10 14:09:10
5839	826	5383	77.0000	2.0500	2026-07-10 14:09:10	2026-07-10 14:09:10
5840	826	5384	9.0000	6.7100	2026-07-10 14:09:10	2026-07-10 14:09:10
5841	826	5385	12.0000	3.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5842	826	5386	18.0000	7.5500	2026-07-10 14:09:10	2026-07-10 14:09:10
5843	826	5387	6.0000	3.3100	2026-07-10 14:09:10	2026-07-10 14:09:10
5844	826	5388	8.0000	10.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5845	826	5389	18.0000	12.3700	2026-07-10 14:09:10	2026-07-10 14:09:10
5846	826	5390	13.0000	7.9000	2026-07-10 14:09:10	2026-07-10 14:09:10
5847	826	5391	2.0000	0.1700	2026-07-10 14:09:10	2026-07-10 14:09:10
5848	826	5392	5.0000	12.0700	2026-07-10 14:09:10	2026-07-10 14:09:10
5849	826	5393	1.0000	39544.4700	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: tipos_cambio; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tipos_cambio (id, fecha, moneda, tasa, fuente, raw, created_at, updated_at) FROM stdin;
1	2026-07-08	USD	3.409000	decolecta_sbs_accounting	{"date": "2026-07-08", "price": "3.409000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-09 11:44:50	2026-07-09 11:44:50
2	2025-08-08	USD	3.531000	decolecta_sbs_accounting	{"date": "2025-08-08", "price": "3.531000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-09 12:04:45	2026-07-09 12:04:45
3	2026-07-09	USD	3.402000	decolecta_sbs_accounting	{"date": "2026-07-09", "price": "3.402000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-09 19:49:08	2026-07-09 19:49:08
\.


--
-- Data for Name: tipos_metodo_pago; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tipos_metodo_pago (id, slug, nombre, icono, admite_vuelto_default, requiere_referencia, orden, activo, created_at, updated_at) FROM stdin;
1	efectivo	Efectivo	Banknote	t	f	10	t	2026-05-15 04:16:01	2026-05-15 04:16:01
2	tarjeta_debito	Tarjeta débito	CreditCard	f	f	20	t	2026-05-15 04:16:01	2026-05-15 04:16:01
3	tarjeta_credito	Tarjeta crédito	CreditCard	f	f	30	t	2026-05-15 04:16:01	2026-05-15 04:16:01
4	transferencia	Transferencia	ArrowLeftRight	f	t	40	t	2026-05-15 04:16:01	2026-05-15 04:16:01
5	yape	Yape	Smartphone	f	t	50	t	2026-05-15 04:16:01	2026-05-15 04:16:01
6	plin	Plin	Smartphone	f	t	60	t	2026-05-15 04:16:01	2026-05-15 04:16:01
7	otro	Otro	Wallet	f	f	99	t	2026-05-15 04:16:01	2026-05-15 04:16:01
\.


--
-- Data for Name: transferencias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.transferencias (id, empresa_id, almacen_origen_id, almacen_destino_id, user_id, fecha, estado, created_at, updated_at, fecha_envio, fecha_recepcion, user_envio_id, user_recepcion_id, observacion_envio, observacion_recepcion) FROM stdin;
\.


--
-- Data for Name: transferencias_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.transferencias_detalle (id, transferencia_id, producto_id, unidad_medida_id, cantidad_enviada, factor_conversion, cantidad_base_enviada, costo_unitario, created_at, updated_at, cantidad_recibida, cantidad_base_recibida, diferencia_base, observacion) FROM stdin;
\.


--
-- Data for Name: turno_arqueo; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turno_arqueo (id, turno_id, denominacion, cantidad, created_at, updated_at) FROM stdin;
54	213	200.00	0	2026-07-05 20:30:00	2026-07-05 20:30:00
55	213	100.00	1	2026-07-05 20:30:00	2026-07-05 20:30:00
56	213	50.00	0	2026-07-05 20:30:00	2026-07-05 20:30:00
57	213	20.00	1	2026-07-05 20:30:00	2026-07-05 20:30:00
58	213	10.00	0	2026-07-05 20:30:00	2026-07-05 20:30:00
59	213	5.00	0	2026-07-05 20:30:00	2026-07-05 20:30:00
60	213	2.00	0	2026-07-05 20:30:00	2026-07-05 20:30:00
61	213	1.00	0	2026-07-05 20:30:00	2026-07-05 20:30:00
62	213	0.50	0	2026-07-05 20:30:00	2026-07-05 20:30:00
63	213	0.20	0	2026-07-05 20:30:00	2026-07-05 20:30:00
64	213	0.10	0	2026-07-05 20:30:00	2026-07-05 20:30:00
\.


--
-- Data for Name: turno_arqueo_metodos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turno_arqueo_metodos (id, turno_id, metodo_pago_id, monto_declarado, created_at, updated_at) FROM stdin;
3	213	4	0.00	2026-07-05 20:30:00	2026-07-05 20:30:00
4	213	2	0.00	2026-07-05 20:30:00	2026-07-05 20:30:00
5	213	5	100.00	2026-07-05 20:30:00	2026-07-05 20:30:00
6	213	3	0.00	2026-07-05 20:30:00	2026-07-05 20:30:00
\.


--
-- Data for Name: turno_cierre_productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turno_cierre_productos (id, turno_id, producto_id, producto_nombre, cantidad_vendida, precio_unitario, total, created_at, updated_at, stock_final) FROM stdin;
18	213	308	Cemento Pacasmayo Tipo I x 42.5 kg	7.000	29.90	209.30	2026-07-05 20:30:00	2026-07-05 20:30:00	843.0000
19	213	312	Alambre N°8 Prodac (kg)	4.000	5.20	20.80	2026-07-05 20:30:00	2026-07-05 20:30:00	696.0000
20	213	311	Alambre N°16 Prodac (kg)	7.000	5.50	38.50	2026-07-05 20:30:00	2026-07-05 20:30:00	993.0000
\.


--
-- Data for Name: turno_consolidacion_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turno_consolidacion_items (id, turno_consolidacion_id, metodo_pago_id, cuenta_id, etiqueta, declarado, esperado, contado, diferencia, created_at, updated_at) FROM stdin;
4	6	\N	1	Efectivo	120.00	200.00	150.00	-50.00	2026-07-05 20:32:16	2026-07-05 20:32:16
5	6	4	17	Plin	0.00	0.00	0.00	0.00	2026-07-05 20:32:16	2026-07-05 20:32:16
6	6	2	4	Tarjeta	0.00	0.00	0.00	0.00	2026-07-05 20:32:16	2026-07-05 20:32:16
7	6	5	14	Transferencia	100.00	100.00	100.00	0.00	2026-07-05 20:32:16	2026-07-05 20:32:16
8	6	3	14	Yape	0.00	0.00	0.00	0.00	2026-07-05 20:32:16	2026-07-05 20:32:16
\.


--
-- Data for Name: turno_consolidaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turno_consolidaciones (id, turno_id, empresa_id, user_id, fecha, efectivo_declarado, efectivo_esperado, caja_chica, efectivo_contado, diferencia_vs_declarado, diferencia_vs_esperado, observacion, created_at, updated_at) FROM stdin;
6	213	1	1	2026-07-05	120.00	200.00	0.00	150.00	30.00	-50.00	\N	2026-07-05 20:32:16	2026-07-05 20:32:16
\.


--
-- Data for Name: turnos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turnos (id, empresa_id, local_id, caja_id, user_id, user_cierre_id, monto_apertura, monto_caja_chica, monto_cierre_declarado, monto_cierre_esperado, diferencia, estado, fecha_apertura, fecha_cierre, observacion_apertura, observacion_cierre, created_at, updated_at) FROM stdin;
475	1	1	312	1	\N	100.00	0.00	\N	\N	\N	abierto	2026-07-08 17:18:34	\N	\N	\N	2026-07-08 17:18:34	2026-07-08 17:18:34
209	1	1	1	2	2	200.00	0.00	200.00	200.00	0.00	cerrado	2026-07-02 08:30:00	2026-07-02 18:30:00	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
210	1	1	1	2	2	200.00	0.00	2200.00	2200.00	0.00	cerrado	2026-07-03 08:30:00	2026-07-03 18:30:00	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
211	1	1	1	2	2	200.00	0.00	1743.50	1769.00	-25.50	cerrado	2026-07-04 08:30:00	2026-07-04 18:30:00	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
212	1	1	1	2	\N	200.00	0.00	\N	\N	\N	abierto	2026-07-05 08:30:00	\N	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
213	1	1	312	1	1	200.00	0.00	120.00	200.00	-80.00	cerrado	2026-07-05 20:05:11	2026-07-05 20:30:00	\N	\N	2026-07-05 20:05:11	2026-07-05 20:30:00
481	817	812	818	960	\N	0.00	0.00	\N	\N	\N	cerrado	2026-07-08 08:00:00	2026-07-08 20:00:00	\N	\N	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: unidades_medida; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.unidades_medida (id, empresa_id, nombre, abreviatura, activo, created_at, updated_at) FROM stdin;
1	1	Unidad	UND	t	2026-05-18 01:53:39	2026-05-18 01:53:39
812	817	Unidad	UND	t	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, empresa_id, local_id, rol_id, name, email, email_verified_at, password, activo, remember_token, created_at, updated_at) FROM stdin;
1	1	1	1	Jesús	jesus@gmail.com	2026-05-18 01:53:39	$2y$12$zlXBFY8O1fDfJUR0.w.9P.GvI0NIzJcYaHEmn6SHtjUIAUVkFZ.VC	t	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
2	1	1	2	Cajera	cajera@gmail.com	2026-05-18 01:53:39	$2y$12$nK5vzxRZrV/hA7kOEuwy5eIHNn6JQuk142w2wpS2knYUK3.Y1lVK6	t	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
960	817	812	870	Administrador H&C	admin@ferreteriahyc.com	2026-07-10 14:09:10	$2y$12$6NLWQqQ1oc73vZTjTZ/DXe7ef8FNFZiB4JMpKlsMSijyji8H6Pmi6	t	\N	2026-07-10 14:09:10	2026-07-10 14:09:10
961	817	812	871	Cajera 1	cajera1@ferreteriahyc.com	2026-07-10 14:09:10	$2y$12$C0YwB/x8uiwSYuQqr5ogWeBrENUKYB6U7WAv4AMH8cZ91asQGNcWi	t	\N	2026-07-10 14:09:10	2026-07-10 14:09:10
962	817	812	871	Cajera 2	cajera2@ferreteriahyc.com	2026-07-10 14:09:10	$2y$12$51GebRhRSzpgud2cxNseYux1Ep8MIzlVS5U2gWUPPYIYkoQdOtjeS	t	\N	2026-07-10 14:09:10	2026-07-10 14:09:10
\.


--
-- Data for Name: venta_abonos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.venta_abonos (id, venta_id, user_id, metodo_pago_id, cuenta_id, fecha, monto, referencia, observacion, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
7	159	1	\N	14	2026-07-04	5000.00	Transferencia BCP — obra Av. Balta	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
8	161	1	\N	14	2026-07-05	1500.00	Transferencia BCP	\N	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
9	170	1	5	15	2026-07-05	168.60	\N	\N	2026-07-05 20:19:19	2026-07-05 20:19:19	PEN	\N	\N
10	169	1	5	15	2026-07-09	700.00	\N	\N	2026-07-09 19:58:41	2026-07-09 19:58:41	PEN	\N	\N
\.


--
-- Data for Name: venta_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.venta_items (id, venta_id, producto_id, producto_unidad_id, producto_nombre, unidad_nombre, cantidad, factor_conversion, cantidad_base, precio_unitario, precio_original, descuento_item, descuento_concepto_id, subtotal, created_at, updated_at, incluye_igv) FROM stdin;
162	159	305	305	Ladrillo King Kong 18 huecos	Unidad	30000.0000	1.0000	30000.0000	1.10	1.10	0.00	\N	33000.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
163	159	308	308	Cemento Pacasmayo Tipo I x 42.5 kg	Unidad	400.0000	1.0000	400.0000	29.90	29.90	0.00	\N	11960.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
164	160	309	309	Fierro corrugado 1/2" x 9m Aceros Arequipa	Unidad	200.0000	1.0000	200.0000	36.50	36.50	0.00	\N	7300.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
165	160	308	308	Cemento Pacasmayo Tipo I x 42.5 kg	Unidad	36.0000	1.0000	36.0000	29.90	29.90	0.00	\N	1076.40	2026-07-05 19:27:37	2026-07-05 19:27:37	t
166	161	306	306	Ladrillo Pandereta	Unidad	45000.0000	1.0000	45000.0000	0.75	0.75	0.00	\N	33750.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
167	161	309	309	Fierro corrugado 1/2" x 9m Aceros Arequipa	Unidad	250.0000	1.0000	250.0000	36.50	36.50	0.00	\N	9125.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
168	162	305	305	Ladrillo King Kong 18 huecos	Unidad	500.0000	1.0000	500.0000	1.10	1.10	0.00	\N	550.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
169	162	308	308	Cemento Pacasmayo Tipo I x 42.5 kg	Unidad	10.0000	1.0000	10.0000	29.90	29.90	0.00	\N	299.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
170	163	314	314	Calamina galvanizada 0.22 x 3.6m	Unidad	30.0000	1.0000	30.0000	33.00	33.00	0.00	\N	990.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
171	163	311	311	Alambre N°16 Prodac (kg)	Unidad	40.0000	1.0000	40.0000	5.50	5.50	0.00	\N	220.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
172	164	309	309	Fierro corrugado 1/2" x 9m Aceros Arequipa	Unidad	80.0000	1.0000	80.0000	36.50	36.50	0.00	\N	2920.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
173	164	310	310	Fierro corrugado 3/8" x 9m Aceros Arequipa	Unidad	12.0000	1.0000	12.0000	21.00	21.00	0.00	\N	252.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
174	165	315	315	Arena gruesa (m³)	Unidad	6.0000	1.0000	6.0000	55.00	55.00	0.00	\N	330.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
175	165	313	313	Clavos 2 1/2" (kg)	Unidad	20.0000	1.0000	20.0000	5.00	5.00	0.00	\N	100.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
176	166	306	306	Ladrillo Pandereta	Unidad	4000.0000	1.0000	4000.0000	0.75	0.75	0.00	\N	3000.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
177	166	308	308	Cemento Pacasmayo Tipo I x 42.5 kg	Unidad	60.0000	1.0000	60.0000	29.90	29.90	0.00	\N	1794.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
178	167	305	305	Ladrillo King Kong 18 huecos	Unidad	1000.0000	1.0000	1000.0000	1.10	1.10	0.00	\N	1100.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
179	167	316	316	Piedra chancada 1/2" (m³)	Unidad	8.0000	1.0000	8.0000	70.00	70.00	0.00	\N	560.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
180	168	308	308	Cemento Pacasmayo Tipo I x 42.5 kg	Unidad	25.0000	1.0000	25.0000	29.90	29.90	0.00	\N	747.50	2026-07-05 19:27:37	2026-07-05 19:27:37	t
181	168	312	312	Alambre N°8 Prodac (kg)	Unidad	25.0000	1.0000	25.0000	5.20	5.20	0.00	\N	130.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
182	169	310	310	Fierro corrugado 3/8" x 9m Aceros Arequipa	Unidad	100.0000	1.0000	100.0000	21.00	21.00	0.00	\N	2100.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
183	169	313	313	Clavos 2 1/2" (kg)	Unidad	8.0000	1.0000	8.0000	5.00	5.00	0.00	\N	40.00	2026-07-05 19:27:37	2026-07-05 19:27:37	t
184	170	311	311	Alambre N°16 Prodac (kg)	Unidad	7.0000	1.0000	7.0000	5.50	5.50	0.00	\N	38.50	2026-07-05 20:17:50	2026-07-05 20:17:50	t
185	170	312	312	Alambre N°8 Prodac (kg)	Unidad	4.0000	1.0000	4.0000	5.20	5.20	0.00	\N	20.80	2026-07-05 20:17:50	2026-07-05 20:17:50	t
186	170	308	308	Cemento Pacasmayo Tipo I x 42.5 kg	Unidad	7.0000	1.0000	7.0000	29.90	29.90	0.00	\N	209.30	2026-07-05 20:17:50	2026-07-05 20:17:50	t
360	820	311	311	Alambre N°16 Prodac (kg)	Unidad	1.0000	1.0000	1.0000	5.50	5.50	0.00	\N	5.50	2026-07-09 19:56:19	2026-07-09 19:56:19	t
361	820	312	312	Alambre N°8 Prodac (kg)	Unidad	1.0000	1.0000	1.0000	5.20	5.20	0.00	\N	5.20	2026-07-09 19:56:19	2026-07-09 19:56:19	t
362	820	315	315	Arena gruesa (m³)	Unidad	1.0000	1.0000	1.0000	55.00	55.00	0.00	\N	55.00	2026-07-09 19:56:19	2026-07-09 19:56:19	t
363	820	310	310	Fierro corrugado 3/8" x 9m Aceros Arequipa	Unidad	1.0000	1.0000	1.0000	21.00	21.00	0.00	\N	21.00	2026-07-09 19:56:19	2026-07-09 19:56:19	t
364	820	309	309	Fierro corrugado 1/2" x 9m Aceros Arequipa	Unidad	1.0000	1.0000	1.0000	36.50	36.50	0.00	\N	36.50	2026-07-09 19:56:19	2026-07-09 19:56:19	t
365	820	313	313	Clavos 2 1/2" (kg)	Unidad	1.0000	1.0000	1.0000	5.00	5.00	0.00	\N	5.00	2026-07-09 19:56:19	2026-07-09 19:56:19	t
366	820	306	306	Ladrillo Pandereta	Unidad	1.0000	1.0000	1.0000	0.75	0.75	0.00	\N	0.75	2026-07-09 19:56:19	2026-07-09 19:56:19	t
367	820	316	316	Piedra chancada 1/2" (m³)	Unidad	1.0000	1.0000	1.0000	70.00	70.00	0.00	\N	70.00	2026-07-09 19:56:19	2026-07-09 19:56:19	t
\.


--
-- Data for Name: venta_pagos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.venta_pagos (id, venta_id, metodo_pago_id, cuenta_metodo_pago_id, monto, referencia, vuelto, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
148	159	5	\N	15000.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
149	160	1	\N	2000.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
150	161	5	\N	10000.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
151	162	1	\N	849.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
152	163	3	\N	1210.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
153	164	5	\N	3172.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
154	165	1	\N	430.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
155	166	1	\N	800.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
156	167	1	\N	1660.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
157	168	3	\N	877.50	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
158	169	1	\N	300.00	\N	0.00	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
159	170	5	9	100.00	\N	0.00	2026-07-05 20:17:50	2026-07-05 20:17:50	PEN	\N	\N
329	820	4	13	190.95	\N	0.00	2026-07-09 19:56:19	2026-07-09 19:56:19	PEN	\N	\N
330	820	1	\N	8.00	\N	0.00	2026-07-09 19:56:19	2026-07-09 19:56:19	PEN	\N	\N
\.


--
-- Data for Name: ventas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ventas (id, empresa_id, local_id, turno_id, caja_id, user_id, cliente_id, numero, tipo_comprobante, subtotal, descuento_total, descuento_concepto_id, igv, total, estado, observacion, fecha_venta, created_at, updated_at, idempotency_key, es_credito, monto_pagado, saldo_pendiente, fecha_vencimiento, moneda, tipo_cambio, monto_moneda) FROM stdin;
820	1	1	475	312	1	1	V-0001	ticket	198.95	0.00	\N	30.35	198.95	completada	\N	2026-07-09 19:56:19	2026-07-09 19:56:19	2026-07-09 19:56:19	da8d4bc1-5b15-4b9d-bf59-7832595ba30f	f	198.95	0.00	\N	PEN	\N	\N
169	1	1	212	1	2	339	V-0503	ticket	2140.00	0.00	\N	0.00	2140.00	completada	\N	2026-07-05 12:15:00	2026-07-05 19:27:37	2026-07-09 19:58:41	\N	t	1000.00	1140.00	2026-07-19	PEN	\N	\N
821	817	812	481	818	960	1149	P00100032336	ticket	30.00	0.00	\N	0.00	30.00	completada	Saldo migrado del sistema anterior	2025-07-21 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	30.00	\N	PEN	\N	\N
822	817	812	481	818	960	1150	P00100040619	ticket	30.00	0.00	\N	0.00	30.00	completada	Saldo migrado del sistema anterior	2026-03-16 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	30.00	\N	PEN	\N	\N
823	817	812	481	818	960	1150	P00200020018	ticket	3150.00	0.00	\N	0.00	3150.00	completada	Saldo migrado del sistema anterior	2025-01-28 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	3150.00	\N	PEN	\N	\N
824	817	812	481	818	960	1150	P00100041470	ticket	48.00	0.00	\N	0.00	48.00	completada	Saldo migrado del sistema anterior	2026-04-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	48.00	\N	PEN	\N	\N
825	817	812	481	818	960	1150	P00200028597	ticket	45.00	0.00	\N	0.00	45.00	completada	Saldo migrado del sistema anterior	2026-01-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	45.00	\N	PEN	\N	\N
826	817	812	481	818	960	1150	P00100040481	ticket	1005.00	0.00	\N	0.00	1005.00	completada	Saldo migrado del sistema anterior	2026-03-12 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1005.00	\N	PEN	\N	\N
827	817	812	481	818	960	1150	P00100040555	ticket	1340.00	0.00	\N	0.00	1340.00	completada	Saldo migrado del sistema anterior	2026-03-14 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1340.00	\N	PEN	\N	\N
828	817	812	481	818	960	1151	P00100043718	ticket	5.00	0.00	\N	0.00	5.00	completada	Saldo migrado del sistema anterior	2026-07-07 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	5.00	\N	PEN	\N	\N
829	817	812	481	818	960	1151	P00100035735	ticket	18.00	0.00	\N	0.00	18.00	completada	Saldo migrado del sistema anterior	2025-11-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	18.00	\N	PEN	\N	\N
830	817	812	481	818	960	1152	P00100012526	ticket	185.50	0.00	\N	0.00	185.50	completada	Saldo migrado del sistema anterior	2023-06-15 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	185.50	\N	PEN	\N	\N
831	817	812	481	818	960	1153	P00100043645	ticket	150.04	0.00	\N	0.00	150.04	completada	Saldo migrado del sistema anterior	2026-07-03 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	150.04	\N	PEN	\N	\N
832	817	812	481	818	960	1154	P00100039262	ticket	672.10	0.00	\N	0.00	672.10	completada	Saldo migrado del sistema anterior	2026-02-09 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	672.10	\N	PEN	\N	\N
160	1	1	210	1	2	339	V-0201	ticket	8376.40	0.00	\N	0.00	8376.40	completada	\N	2026-07-03 09:40:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	2000.00	6376.40	2026-07-17	PEN	\N	\N
833	817	812	481	818	960	1155	P00200025226	ticket	200.00	0.00	\N	0.00	200.00	completada	Saldo migrado del sistema anterior	2025-09-06 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	200.00	\N	PEN	\N	\N
162	1	1	211	1	2	1	V-0401	ticket	849.00	0.00	\N	0.00	849.00	completada	\N	2026-07-04 09:10:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	849.00	0.00	\N	PEN	\N	\N
163	1	1	211	1	2	1	V-0402	ticket	1210.00	0.00	\N	0.00	1210.00	completada	\N	2026-07-04 11:25:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	1210.00	0.00	\N	PEN	\N	\N
164	1	1	211	1	2	338	V-0403	ticket	3172.00	0.00	\N	0.00	3172.00	completada	\N	2026-07-04 12:40:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	3172.00	0.00	\N	PEN	\N	\N
165	1	1	211	1	2	1	V-0404	ticket	430.00	0.00	\N	0.00	430.00	completada	\N	2026-07-04 16:50:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	430.00	0.00	\N	PEN	\N	\N
166	1	1	211	1	2	340	V-0405	ticket	4794.00	0.00	\N	0.00	4794.00	completada	\N	2026-07-04 15:20:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	800.00	3994.00	2026-07-18	PEN	\N	\N
159	1	1	209	1	2	337	V-0101	ticket	44960.00	0.00	\N	0.00	44960.00	completada	\N	2026-07-02 10:15:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	20000.00	24960.00	2026-07-31	PEN	\N	\N
167	1	1	212	1	2	1	V-0501	ticket	1660.00	0.00	\N	0.00	1660.00	completada	\N	2026-07-05 09:05:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	1660.00	0.00	\N	PEN	\N	\N
168	1	1	212	1	2	341	V-0502	ticket	877.50	0.00	\N	0.00	877.50	completada	\N	2026-07-05 10:35:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	877.50	0.00	\N	PEN	\N	\N
161	1	1	210	1	2	338	V-0202	ticket	42875.00	0.00	\N	0.00	42875.00	completada	\N	2026-07-03 16:05:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	11500.00	31375.00	2026-07-25	PEN	\N	\N
834	817	812	481	818	960	1156	P00200009618	ticket	11.70	0.00	\N	0.00	11.70	completada	Saldo migrado del sistema anterior	2023-07-10 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	11.70	\N	PEN	\N	\N
835	817	812	481	818	960	1156	P00100012965	ticket	40.30	0.00	\N	0.00	40.30	completada	Saldo migrado del sistema anterior	2023-07-10 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	40.30	\N	PEN	\N	\N
836	817	812	481	818	960	1156	P00400001701	ticket	127.50	0.00	\N	0.00	127.50	completada	Saldo migrado del sistema anterior	2023-07-14 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	127.50	\N	PEN	\N	\N
170	1	1	213	312	1	4	V-0001	ticket	268.60	0.00	\N	40.97	268.60	completada	\N	2026-07-05 20:17:50	2026-07-05 20:17:50	2026-07-05 20:19:19	0a7a7b1f-10e6-4f2e-af12-def8a86f3942	t	268.60	0.00	\N	PEN	\N	\N
837	817	812	481	818	960	1156	P00400001984	ticket	86.00	0.00	\N	0.00	86.00	completada	Saldo migrado del sistema anterior	2023-09-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	86.00	\N	PEN	\N	\N
838	817	812	481	818	960	1157	P00100016284	ticket	1.00	0.00	\N	0.00	1.00	completada	Saldo migrado del sistema anterior	2023-11-14 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1.00	\N	PEN	\N	\N
839	817	812	481	818	960	1157	P00100014846	ticket	2.00	0.00	\N	0.00	2.00	completada	Saldo migrado del sistema anterior	2023-09-21 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	2.00	\N	PEN	\N	\N
840	817	812	481	818	960	1157	P00200011147	ticket	160.00	0.00	\N	0.00	160.00	completada	Saldo migrado del sistema anterior	2023-11-07 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	160.00	\N	PEN	\N	\N
841	817	812	481	818	960	1158	P00400003889	ticket	1320.00	0.00	\N	0.00	1320.00	completada	Saldo migrado del sistema anterior	2025-10-16 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1320.00	\N	PEN	\N	\N
842	817	812	481	818	960	1158	P00200026351	ticket	330.00	0.00	\N	0.00	330.00	completada	Saldo migrado del sistema anterior	2025-10-21 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	330.00	\N	PEN	\N	\N
843	817	812	481	818	960	1158	P00200026408	ticket	660.00	0.00	\N	0.00	660.00	completada	Saldo migrado del sistema anterior	2025-10-23 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	660.00	\N	PEN	\N	\N
844	817	812	481	818	960	1159	P00200023562	ticket	112.50	0.00	\N	0.00	112.50	completada	Saldo migrado del sistema anterior	2025-06-28 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	112.50	\N	PEN	\N	\N
845	817	812	481	818	960	1159	P00200023570	ticket	5.00	0.00	\N	0.00	5.00	completada	Saldo migrado del sistema anterior	2025-06-28 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	5.00	\N	PEN	\N	\N
846	817	812	481	818	960	1160	F00100003091	factura	1669.00	0.00	\N	0.00	1669.00	completada	Saldo migrado del sistema anterior	2026-07-06 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1669.00	\N	PEN	\N	\N
847	817	812	481	818	960	1161	P00200016646	ticket	2710.00	0.00	\N	0.00	2710.00	completada	Saldo migrado del sistema anterior	2024-08-29 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	2710.00	\N	PEN	\N	\N
848	817	812	481	818	960	1162	P00200027719	ticket	90.00	0.00	\N	0.00	90.00	completada	Saldo migrado del sistema anterior	2025-12-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	90.00	\N	PEN	\N	\N
849	817	812	481	818	960	1162	P00400002887	ticket	2650.80	0.00	\N	0.00	2650.80	completada	Saldo migrado del sistema anterior	2024-05-21 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	2650.80	\N	PEN	\N	\N
850	817	812	481	818	960	1163	P00100037758	ticket	75.00	0.00	\N	0.00	75.00	completada	Saldo migrado del sistema anterior	2026-01-02 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	75.00	\N	PEN	\N	\N
851	817	812	481	818	960	1164	P00100039704	ticket	54.50	0.00	\N	0.00	54.50	completada	Saldo migrado del sistema anterior	2026-02-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	54.50	\N	PEN	\N	\N
852	817	812	481	818	960	1164	P00100040504	ticket	155.00	0.00	\N	0.00	155.00	completada	Saldo migrado del sistema anterior	2026-03-13 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	155.00	\N	PEN	\N	\N
853	817	812	481	818	960	1165	P00100042996	ticket	1932.00	0.00	\N	0.00	1932.00	completada	Saldo migrado del sistema anterior	2026-06-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1932.00	\N	PEN	\N	\N
854	817	812	481	818	960	1166	P00200030673	ticket	312.00	0.00	\N	0.00	312.00	completada	Saldo migrado del sistema anterior	2026-03-16 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	312.00	\N	PEN	\N	\N
855	817	812	481	818	960	1167	P00100013356	ticket	500.00	0.00	\N	0.00	500.00	completada	Saldo migrado del sistema anterior	2023-07-25 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	500.00	\N	PEN	\N	\N
856	817	812	481	818	960	1168	P00200032777	ticket	38.50	0.00	\N	0.00	38.50	completada	Saldo migrado del sistema anterior	2026-07-03 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	38.50	\N	PEN	\N	\N
857	817	812	481	818	960	1169	P00100014940	ticket	88.80	0.00	\N	0.00	88.80	completada	Saldo migrado del sistema anterior	2023-09-24 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	88.80	\N	PEN	\N	\N
858	817	812	481	818	960	1170	P00100042767	ticket	648.60	0.00	\N	0.00	648.60	completada	Saldo migrado del sistema anterior	2026-06-01 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	648.60	\N	PEN	\N	\N
859	817	812	481	818	960	1170	P00200032275	ticket	88.40	0.00	\N	0.00	88.40	completada	Saldo migrado del sistema anterior	2026-06-01 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	88.40	\N	PEN	\N	\N
860	817	812	481	818	960	1171	P00200031230	ticket	338.00	0.00	\N	0.00	338.00	completada	Saldo migrado del sistema anterior	2026-04-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	338.00	\N	PEN	\N	\N
861	817	812	481	818	960	1172	P00200032871	ticket	100.40	0.00	\N	0.00	100.40	completada	Saldo migrado del sistema anterior	2026-07-06 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	100.40	\N	PEN	\N	\N
862	817	812	481	818	960	1173	P00400000848	ticket	4468.00	0.00	\N	0.00	4468.00	completada	Saldo migrado del sistema anterior	2022-10-29 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	4468.00	\N	PEN	\N	\N
863	817	812	481	818	960	1174	P00100041641	ticket	1380.00	0.00	\N	0.00	1380.00	completada	Saldo migrado del sistema anterior	2026-04-17 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1380.00	\N	PEN	\N	\N
864	817	812	481	818	960	1175	P00200008460	ticket	46.00	0.00	\N	0.00	46.00	completada	Saldo migrado del sistema anterior	2023-03-31 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	46.00	\N	PEN	\N	\N
865	817	812	481	818	960	1175	P00200027522	ticket	1480.00	0.00	\N	0.00	1480.00	completada	Saldo migrado del sistema anterior	2025-12-04 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1480.00	\N	PEN	\N	\N
866	817	812	481	818	960	1175	P00100003927	ticket	176.40	0.00	\N	0.00	176.40	completada	Saldo migrado del sistema anterior	2022-04-22 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	176.40	\N	PEN	\N	\N
867	817	812	481	818	960	1175	P00200018849	ticket	25.00	0.00	\N	0.00	25.00	completada	Saldo migrado del sistema anterior	2024-12-10 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	25.00	\N	PEN	\N	\N
868	817	812	481	818	960	1175	P00200012299	ticket	28.00	0.00	\N	0.00	28.00	completada	Saldo migrado del sistema anterior	2024-01-16 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	28.00	\N	PEN	\N	\N
869	817	812	481	818	960	1175	P00200003138	ticket	11.40	0.00	\N	0.00	11.40	completada	Saldo migrado del sistema anterior	2022-05-26 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	11.40	\N	PEN	\N	\N
870	817	812	481	818	960	1175	P00100019920	ticket	40.00	0.00	\N	0.00	40.00	completada	Saldo migrado del sistema anterior	2024-04-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	40.00	\N	PEN	\N	\N
871	817	812	481	818	960	1175	P00200003316	ticket	9.00	0.00	\N	0.00	9.00	completada	Saldo migrado del sistema anterior	2022-06-04 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	9.00	\N	PEN	\N	\N
872	817	812	481	818	960	1175	P00200020509	ticket	40.00	0.00	\N	0.00	40.00	completada	Saldo migrado del sistema anterior	2025-02-15 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	40.00	\N	PEN	\N	\N
873	817	812	481	818	960	1175	P00100040757	ticket	29.00	0.00	\N	0.00	29.00	completada	Saldo migrado del sistema anterior	2026-03-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	29.00	\N	PEN	\N	\N
874	817	812	481	818	960	1175	P00400003919	ticket	5.00	0.00	\N	0.00	5.00	completada	Saldo migrado del sistema anterior	2025-12-27 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	5.00	\N	PEN	\N	\N
875	817	812	481	818	960	1175	P00200003335	ticket	7.50	0.00	\N	0.00	7.50	completada	Saldo migrado del sistema anterior	2022-06-04 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	7.50	\N	PEN	\N	\N
876	817	812	481	818	960	1175	P00200022979	ticket	80.00	0.00	\N	0.00	80.00	completada	Saldo migrado del sistema anterior	2025-06-03 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	80.00	\N	PEN	\N	\N
877	817	812	481	818	960	1175	P00200015247	ticket	45.00	0.00	\N	0.00	45.00	completada	Saldo migrado del sistema anterior	2024-06-22 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	45.00	\N	PEN	\N	\N
878	817	812	481	818	960	1175	P00200003339	ticket	3.00	0.00	\N	0.00	3.00	completada	Saldo migrado del sistema anterior	2022-06-04 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	3.00	\N	PEN	\N	\N
879	817	812	481	818	960	1175	P00200023083	ticket	20.00	0.00	\N	0.00	20.00	completada	Saldo migrado del sistema anterior	2025-06-07 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	20.00	\N	PEN	\N	\N
880	817	812	481	818	960	1175	P00100021797	ticket	32.50	0.00	\N	0.00	32.50	completada	Saldo migrado del sistema anterior	2024-07-23 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	32.50	\N	PEN	\N	\N
881	817	812	481	818	960	1175	P00200003776	ticket	2.50	0.00	\N	0.00	2.50	completada	Saldo migrado del sistema anterior	2022-06-30 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	2.50	\N	PEN	\N	\N
882	817	812	481	818	960	1175	P00200023094	ticket	22.50	0.00	\N	0.00	22.50	completada	Saldo migrado del sistema anterior	2025-06-07 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	22.50	\N	PEN	\N	\N
883	817	812	481	818	960	1175	P00100014466	ticket	2.00	0.00	\N	0.00	2.00	completada	Saldo migrado del sistema anterior	2023-09-06 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	2.00	\N	PEN	\N	\N
884	817	812	481	818	960	1175	P00100022466	ticket	6.50	0.00	\N	0.00	6.50	completada	Saldo migrado del sistema anterior	2024-08-18 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	6.50	\N	PEN	\N	\N
885	817	812	481	818	960	1175	P00100038751	ticket	209.30	0.00	\N	0.00	209.30	completada	Saldo migrado del sistema anterior	2026-01-26 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	209.30	\N	PEN	\N	\N
886	817	812	481	818	960	1175	P00200003782	ticket	0.50	0.00	\N	0.00	0.50	completada	Saldo migrado del sistema anterior	2022-06-30 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	0.50	\N	PEN	\N	\N
887	817	812	481	818	960	1175	P00200003827	ticket	5.50	0.00	\N	0.00	5.50	completada	Saldo migrado del sistema anterior	2022-07-04 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	5.50	\N	PEN	\N	\N
888	817	812	481	818	960	1175	P00200030613	ticket	544.00	0.00	\N	0.00	544.00	completada	Saldo migrado del sistema anterior	2026-03-12 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	544.00	\N	PEN	\N	\N
889	817	812	481	818	960	1175	P00200018626	ticket	20.50	0.00	\N	0.00	20.50	completada	Saldo migrado del sistema anterior	2024-11-29 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	20.50	\N	PEN	\N	\N
890	817	812	481	818	960	1175	P00200029449	ticket	18.00	0.00	\N	0.00	18.00	completada	Saldo migrado del sistema anterior	2026-02-04 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	18.00	\N	PEN	\N	\N
891	817	812	481	818	960	1175	P00100025216	ticket	79.29	0.00	\N	0.00	79.29	completada	Saldo migrado del sistema anterior	2024-11-29 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	79.29	\N	PEN	\N	\N
892	817	812	481	818	960	1175	P00200006541	ticket	29.20	0.00	\N	0.00	29.20	completada	Saldo migrado del sistema anterior	2022-12-13 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	29.20	\N	PEN	\N	\N
893	817	812	481	818	960	1175	P00200026812	ticket	128.00	0.00	\N	0.00	128.00	completada	Saldo migrado del sistema anterior	2025-11-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	128.00	\N	PEN	\N	\N
894	817	812	481	818	960	1175	P00100003915	ticket	35.00	0.00	\N	0.00	35.00	completada	Saldo migrado del sistema anterior	2022-04-22 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	35.00	\N	PEN	\N	\N
895	817	812	481	818	960	1176	P00100043766	ticket	614.00	0.00	\N	0.00	614.00	completada	Saldo migrado del sistema anterior	2026-07-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	614.00	\N	PEN	\N	\N
896	817	812	481	818	960	1177	P00100042486	ticket	56.00	0.00	\N	0.00	56.00	completada	Saldo migrado del sistema anterior	2026-05-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	56.00	\N	PEN	\N	\N
897	817	812	481	818	960	1177	P00200032234	ticket	80.00	0.00	\N	0.00	80.00	completada	Saldo migrado del sistema anterior	2026-05-29 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	80.00	\N	PEN	\N	\N
898	817	812	481	818	960	1177	P00200032598	ticket	4640.00	0.00	\N	0.00	4640.00	completada	Saldo migrado del sistema anterior	2026-06-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	4640.00	\N	PEN	\N	\N
899	817	812	481	818	960	1177	P00200030393	ticket	1764.00	0.00	\N	0.00	1764.00	completada	Saldo migrado del sistema anterior	2026-03-06 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1764.00	\N	PEN	\N	\N
900	817	812	481	818	960	1178	P00200023377	ticket	498.00	0.00	\N	0.00	498.00	completada	Saldo migrado del sistema anterior	2025-06-19 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	498.00	\N	PEN	\N	\N
901	817	812	481	818	960	1179	P00200030545	ticket	89.00	0.00	\N	0.00	89.00	completada	Saldo migrado del sistema anterior	2026-03-10 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	89.00	\N	PEN	\N	\N
902	817	812	481	818	960	1180	P00100042177	ticket	167.50	0.00	\N	0.00	167.50	completada	Saldo migrado del sistema anterior	2026-05-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	167.50	\N	PEN	\N	\N
903	817	812	481	818	960	1181	P00200030655	ticket	198.00	0.00	\N	0.00	198.00	completada	Saldo migrado del sistema anterior	2026-03-16 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	198.00	\N	PEN	\N	\N
904	817	812	481	818	960	1181	P00200032129	ticket	333.00	0.00	\N	0.00	333.00	completada	Saldo migrado del sistema anterior	2026-05-25 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	333.00	\N	PEN	\N	\N
905	817	812	481	818	960	1181	P00200032233	ticket	297.50	0.00	\N	0.00	297.50	completada	Saldo migrado del sistema anterior	2026-05-29 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	297.50	\N	PEN	\N	\N
906	817	812	481	818	960	1181	P00200032246	ticket	69.00	0.00	\N	0.00	69.00	completada	Saldo migrado del sistema anterior	2026-05-30 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	69.00	\N	PEN	\N	\N
907	817	812	481	818	960	1181	P00200032406	ticket	110.00	0.00	\N	0.00	110.00	completada	Saldo migrado del sistema anterior	2026-06-09 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	110.00	\N	PEN	\N	\N
908	817	812	481	818	960	1182	P00100043178	ticket	3075.00	0.00	\N	0.00	3075.00	completada	Saldo migrado del sistema anterior	2026-06-17 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	3075.00	\N	PEN	\N	\N
909	817	812	481	818	960	1182	P00100043025	ticket	324.00	0.00	\N	0.00	324.00	completada	Saldo migrado del sistema anterior	2026-06-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	324.00	\N	PEN	\N	\N
910	817	812	481	818	960	1182	P00100043295	ticket	4149.00	0.00	\N	0.00	4149.00	completada	Saldo migrado del sistema anterior	2026-06-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	4149.00	\N	PEN	\N	\N
911	817	812	481	818	960	1183	P00200025295	ticket	1030.00	0.00	\N	0.00	1030.00	completada	Saldo migrado del sistema anterior	2025-09-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1030.00	\N	PEN	\N	\N
912	817	812	481	818	960	1184	P00100025236	ticket	682.00	0.00	\N	0.00	682.00	completada	Saldo migrado del sistema anterior	2024-11-30 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	682.00	\N	PEN	\N	\N
913	817	812	481	818	960	1185	P00200030602	ticket	392.00	0.00	\N	0.00	392.00	completada	Saldo migrado del sistema anterior	2026-03-12 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	392.00	\N	PEN	\N	\N
914	817	812	481	818	960	1186	P00100043726	ticket	1434.60	0.00	\N	0.00	1434.60	completada	Saldo migrado del sistema anterior	2026-07-07 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1434.60	\N	PEN	\N	\N
915	817	812	481	818	960	1187	P00100040229	ticket	225.00	0.00	\N	0.00	225.00	completada	Saldo migrado del sistema anterior	2026-03-05 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	225.00	\N	PEN	\N	\N
916	817	812	481	818	960	1188	P00100034901	ticket	45.00	0.00	\N	0.00	45.00	completada	Saldo migrado del sistema anterior	2025-10-12 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	45.00	\N	PEN	\N	\N
917	817	812	481	818	960	1189	P00200027799	ticket	155.00	0.00	\N	0.00	155.00	completada	Saldo migrado del sistema anterior	2025-12-13 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	155.00	\N	PEN	\N	\N
918	817	812	481	818	960	1190	P00200032426	ticket	500.00	0.00	\N	0.00	500.00	completada	Saldo migrado del sistema anterior	2026-06-10 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	500.00	\N	PEN	\N	\N
919	817	812	481	818	960	1191	P00200032740	ticket	3396.60	0.00	\N	0.00	3396.60	completada	Saldo migrado del sistema anterior	2026-07-01 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	3396.60	\N	PEN	\N	\N
920	817	812	481	818	960	1191	P00200032918	ticket	2900.00	0.00	\N	0.00	2900.00	completada	Saldo migrado del sistema anterior	2026-07-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	2900.00	\N	PEN	\N	\N
921	817	812	481	818	960	1192	P00200032898	ticket	4539.00	0.00	\N	0.00	4539.00	completada	Saldo migrado del sistema anterior	2026-07-07 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	4539.00	\N	PEN	\N	\N
922	817	812	481	818	960	1192	P00200032900	ticket	1032.50	0.00	\N	0.00	1032.50	completada	Saldo migrado del sistema anterior	2026-07-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1032.50	\N	PEN	\N	\N
923	817	812	481	818	960	1192	P00200032909	ticket	3202.00	0.00	\N	0.00	3202.00	completada	Saldo migrado del sistema anterior	2026-07-08 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	3202.00	\N	PEN	\N	\N
924	817	812	481	818	960	1193	P00200030376	ticket	551.00	0.00	\N	0.00	551.00	completada	Saldo migrado del sistema anterior	2026-03-06 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	551.00	\N	PEN	\N	\N
925	817	812	481	818	960	1193	P00100041779	ticket	7290.00	0.00	\N	0.00	7290.00	completada	Saldo migrado del sistema anterior	2026-04-22 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	7290.00	\N	PEN	\N	\N
926	817	812	481	818	960	1193	F00100002891	factura	1081.50	0.00	\N	0.00	1081.50	completada	Saldo migrado del sistema anterior	2026-04-23 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1081.50	\N	PEN	\N	\N
927	817	812	481	818	960	1193	P00200029709	ticket	1437.50	0.00	\N	0.00	1437.50	completada	Saldo migrado del sistema anterior	2026-02-11 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1437.50	\N	PEN	\N	\N
928	817	812	481	818	960	1194	P00200032600	ticket	5.00	0.00	\N	0.00	5.00	completada	Saldo migrado del sistema anterior	2026-06-20 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	5.00	\N	PEN	\N	\N
929	817	812	481	818	960	1194	P00100039054	ticket	200.70	0.00	\N	0.00	200.70	completada	Saldo migrado del sistema anterior	2026-02-02 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	200.70	\N	PEN	\N	\N
930	817	812	481	818	960	1195	P00200026141	ticket	980.00	0.00	\N	0.00	980.00	completada	Saldo migrado del sistema anterior	2025-10-13 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	980.00	\N	PEN	\N	\N
931	817	812	481	818	960	1195	P00200026727	ticket	98.00	0.00	\N	0.00	98.00	completada	Saldo migrado del sistema anterior	2025-11-05 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	98.00	\N	PEN	\N	\N
932	817	812	481	818	960	1196	P00200025421	ticket	56.00	0.00	\N	0.00	56.00	completada	Saldo migrado del sistema anterior	2025-09-12 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	56.00	\N	PEN	\N	\N
933	817	812	481	818	960	1197	F00100003073	factura	1552.50	0.00	\N	0.00	1552.50	completada	Saldo migrado del sistema anterior	2026-07-02 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	1552.50	\N	PEN	\N	\N
934	817	812	481	818	960	1197	F00100003074	factura	903.50	0.00	\N	0.00	903.50	completada	Saldo migrado del sistema anterior	2026-07-02 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	903.50	\N	PEN	\N	\N
935	817	812	481	818	960	1197	F00100003080	factura	960.00	0.00	\N	0.00	960.00	completada	Saldo migrado del sistema anterior	2026-07-03 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	960.00	\N	PEN	\N	\N
936	817	812	481	818	960	1198	P00200032402	ticket	393.00	0.00	\N	0.00	393.00	completada	Saldo migrado del sistema anterior	2026-06-09 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	393.00	\N	PEN	\N	\N
937	817	812	481	818	960	1198	P00200031645	ticket	146.00	0.00	\N	0.00	146.00	completada	Saldo migrado del sistema anterior	2026-05-02 12:00:00	2026-07-10 14:09:11	2026-07-10 14:09:11	\N	t	0.00	146.00	\N	PEN	\N	\N
\.


--
-- Name: almacenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.almacenes_id_seq', 826, true);


--
-- Name: auditoria_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.auditoria_id_seq', 322, true);


--
-- Name: balance_diario_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.balance_diario_items_id_seq', 2027, true);


--
-- Name: balances_diarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.balances_diarios_id_seq', 49, true);


--
-- Name: cajas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cajas_id_seq', 819, true);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_seq', 824, true);


--
-- Name: cierres_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cierres_inventario_id_seq', 14, true);


--
-- Name: cierres_inventario_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cierres_inventario_items_id_seq', 1, false);


--
-- Name: cita_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cita_items_id_seq', 82, true);


--
-- Name: citas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.citas_id_seq', 82, true);


--
-- Name: cliente_anticipo_aplicaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cliente_anticipo_aplicaciones_id_seq', 8, true);


--
-- Name: cliente_anticipos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cliente_anticipos_id_seq', 149, true);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 1219, true);


--
-- Name: cuenta_metodo_pago_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuenta_metodo_pago_id_seq', 31, true);


--
-- Name: cuenta_movimientos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuenta_movimientos_id_seq', 341, true);


--
-- Name: cuentas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_id_seq', 195, true);


--
-- Name: descuento_conceptos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.descuento_conceptos_id_seq', 811, true);


--
-- Name: descuentos_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.descuentos_log_id_seq', 7, true);


--
-- Name: deuda_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.deuda_pagos_id_seq', 17, true);


--
-- Name: deudas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.deudas_id_seq', 57, true);


--
-- Name: devolucion_motivos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devolucion_motivos_id_seq', 3230, true);


--
-- Name: devolucion_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devolucion_pagos_id_seq', 49, true);


--
-- Name: devoluciones_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devoluciones_detalle_id_seq', 49, true);


--
-- Name: devoluciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devoluciones_id_seq', 49, true);


--
-- Name: empresas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresas_id_seq', 817, true);


--
-- Name: entrada_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.entrada_pagos_id_seq', 17, true);


--
-- Name: entradas_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.entradas_detalle_id_seq', 68, true);


--
-- Name: entradas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.entradas_id_seq', 64, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: gasto_conceptos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.gasto_conceptos_id_seq', 74, true);


--
-- Name: gasto_tipos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.gasto_tipos_id_seq', 39, true);


--
-- Name: gastos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.gastos_id_seq', 48, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: locales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.locales_id_seq', 812, true);


--
-- Name: metodos_pago_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.metodos_pago_id_seq', 4060, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 51, true);


--
-- Name: modulos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.modulos_id_seq', 55, true);


--
-- Name: permisos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permisos_id_seq', 71, true);


--
-- Name: planilla_descuentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.planilla_descuentos_id_seq', 9, true);


--
-- Name: producto_unidades_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.producto_unidades_id_seq', 5393, true);


--
-- Name: productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.productos_id_seq', 5393, true);


--
-- Name: proveedor_adelanto_aplicaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proveedor_adelanto_aplicaciones_id_seq', 3, true);


--
-- Name: proveedor_adelantos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proveedor_adelantos_id_seq', 9, true);


--
-- Name: proveedores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proveedores_id_seq', 44, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 871, true);


--
-- Name: salida_tipos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.salida_tipos_id_seq', 1, true);


--
-- Name: salidas_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.salidas_detalle_id_seq', 1, true);


--
-- Name: salidas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.salidas_id_seq', 1, true);


--
-- Name: stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_id_seq', 5849, true);


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tipos_cambio_id_seq', 3, true);


--
-- Name: tipos_metodo_pago_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tipos_metodo_pago_id_seq', 7, true);


--
-- Name: transferencias_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transferencias_detalle_id_seq', 7, true);


--
-- Name: transferencias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transferencias_id_seq', 7, true);


--
-- Name: turno_arqueo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_arqueo_id_seq', 108, true);


--
-- Name: turno_arqueo_metodos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_arqueo_metodos_id_seq', 6, true);


--
-- Name: turno_cierre_productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_cierre_productos_id_seq', 36, true);


--
-- Name: turno_consolidacion_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_consolidacion_items_id_seq', 8, true);


--
-- Name: turno_consolidaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_consolidaciones_id_seq', 6, true);


--
-- Name: turnos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turnos_id_seq', 481, true);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.unidades_medida_id_seq', 812, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 962, true);


--
-- Name: venta_abonos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.venta_abonos_id_seq', 10, true);


--
-- Name: venta_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.venta_items_id_seq', 367, true);


--
-- Name: venta_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.venta_pagos_id_seq', 330, true);


--
-- Name: ventas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ventas_id_seq', 937, true);


--
-- Name: almacenes almacenes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_pkey PRIMARY KEY (id);


--
-- Name: auditoria auditoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_pkey PRIMARY KEY (id);


--
-- Name: balance_diario_items balance_diario_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balance_diario_items
    ADD CONSTRAINT balance_diario_items_pkey PRIMARY KEY (id);


--
-- Name: balances_diarios balances_diarios_empresa_id_fecha_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balances_diarios
    ADD CONSTRAINT balances_diarios_empresa_id_fecha_unique UNIQUE (empresa_id, fecha);


--
-- Name: balances_diarios balances_diarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balances_diarios
    ADD CONSTRAINT balances_diarios_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cajas cajas_local_id_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_local_id_nombre_unique UNIQUE (local_id, nombre);


--
-- Name: cajas cajas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_empresa_id_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_empresa_id_nombre_key UNIQUE (empresa_id, nombre);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: cierres_inventario_items cierres_inventario_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario_items
    ADD CONSTRAINT cierres_inventario_items_pkey PRIMARY KEY (id);


--
-- Name: cierres_inventario_items cierres_inventario_items_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario_items
    ADD CONSTRAINT cierres_inventario_items_unique UNIQUE (cierre_id, producto_id);


--
-- Name: cierres_inventario cierres_inventario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario
    ADD CONSTRAINT cierres_inventario_pkey PRIMARY KEY (id);


--
-- Name: cita_items cita_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cita_items
    ADD CONSTRAINT cita_items_pkey PRIMARY KEY (id);


--
-- Name: citas citas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_pkey PRIMARY KEY (id);


--
-- Name: cliente_anticipo_aplicaciones cliente_anticipo_aplicaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipo_aplicaciones
    ADD CONSTRAINT cliente_anticipo_aplicaciones_pkey PRIMARY KEY (id);


--
-- Name: cliente_anticipos cliente_anticipos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_pkey PRIMARY KEY (id);


--
-- Name: clientes clientes_empresa_id_numero_documento_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_empresa_id_numero_documento_unique UNIQUE (empresa_id, numero_documento);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: cuenta_metodo_pago cuenta_metodo_pago_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_metodo_pago
    ADD CONSTRAINT cuenta_metodo_pago_pkey PRIMARY KEY (id);


--
-- Name: cuenta_metodo_pago cuenta_metodo_pago_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_metodo_pago
    ADD CONSTRAINT cuenta_metodo_pago_unique UNIQUE (cuenta_id, metodo_pago_id);


--
-- Name: cuenta_movimientos cuenta_movimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_movimientos
    ADD CONSTRAINT cuenta_movimientos_pkey PRIMARY KEY (id);


--
-- Name: cuentas cuentas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas
    ADD CONSTRAINT cuentas_pkey PRIMARY KEY (id);


--
-- Name: descuento_conceptos descuento_conceptos_empresa_id_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuento_conceptos
    ADD CONSTRAINT descuento_conceptos_empresa_id_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: descuento_conceptos descuento_conceptos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuento_conceptos
    ADD CONSTRAINT descuento_conceptos_pkey PRIMARY KEY (id);


--
-- Name: descuentos_log descuentos_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_pkey PRIMARY KEY (id);


--
-- Name: deuda_pagos deuda_pagos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deuda_pagos
    ADD CONSTRAINT deuda_pagos_pkey PRIMARY KEY (id);


--
-- Name: deudas deudas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deudas
    ADD CONSTRAINT deudas_pkey PRIMARY KEY (id);


--
-- Name: devolucion_motivos devolucion_motivos_empresa_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_motivos
    ADD CONSTRAINT devolucion_motivos_empresa_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: devolucion_motivos devolucion_motivos_empresa_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_motivos
    ADD CONSTRAINT devolucion_motivos_empresa_slug_unique UNIQUE (empresa_id, slug);


--
-- Name: devolucion_motivos devolucion_motivos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_motivos
    ADD CONSTRAINT devolucion_motivos_pkey PRIMARY KEY (id);


--
-- Name: devolucion_pagos devolucion_pagos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_pagos
    ADD CONSTRAINT devolucion_pagos_pkey PRIMARY KEY (id);


--
-- Name: devoluciones_detalle devoluciones_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle
    ADD CONSTRAINT devoluciones_detalle_pkey PRIMARY KEY (id);


--
-- Name: devoluciones devoluciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_pkey PRIMARY KEY (id);


--
-- Name: empresas empresas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pkey PRIMARY KEY (id);


--
-- Name: empresas empresas_ruc_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_ruc_unique UNIQUE (ruc);


--
-- Name: entrada_pagos entrada_pagos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos
    ADD CONSTRAINT entrada_pagos_pkey PRIMARY KEY (id);


--
-- Name: entradas_detalle entradas_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas_detalle
    ADD CONSTRAINT entradas_detalle_pkey PRIMARY KEY (id);


--
-- Name: entradas entradas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: gasto_conceptos gasto_conceptos_empresa_id_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_conceptos
    ADD CONSTRAINT gasto_conceptos_empresa_id_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: gasto_conceptos gasto_conceptos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_conceptos
    ADD CONSTRAINT gasto_conceptos_pkey PRIMARY KEY (id);


--
-- Name: gasto_tipos gasto_tipos_empresa_id_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_tipos
    ADD CONSTRAINT gasto_tipos_empresa_id_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: gasto_tipos gasto_tipos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_tipos
    ADD CONSTRAINT gasto_tipos_pkey PRIMARY KEY (id);


--
-- Name: gastos gastos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: locales locales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locales
    ADD CONSTRAINT locales_pkey PRIMARY KEY (id);


--
-- Name: metodos_pago metodos_pago_empresa_id_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metodos_pago
    ADD CONSTRAINT metodos_pago_empresa_id_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: metodos_pago metodos_pago_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metodos_pago
    ADD CONSTRAINT metodos_pago_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: modulos modulos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_pkey PRIMARY KEY (id);


--
-- Name: modulos modulos_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_slug_unique UNIQUE (slug);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permisos permisos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_pkey PRIMARY KEY (id);


--
-- Name: permisos permisos_rol_id_modulo_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_rol_id_modulo_id_unique UNIQUE (rol_id, modulo_id);


--
-- Name: planilla_descuentos planilla_descuentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planilla_descuentos
    ADD CONSTRAINT planilla_descuentos_pkey PRIMARY KEY (id);


--
-- Name: producto_unidades producto_unidades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_unidades
    ADD CONSTRAINT producto_unidades_pkey PRIMARY KEY (id);


--
-- Name: producto_unidades producto_unidades_producto_id_unidad_medida_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_unidades
    ADD CONSTRAINT producto_unidades_producto_id_unidad_medida_id_key UNIQUE (producto_id, unidad_medida_id);


--
-- Name: productos productos_empresa_id_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_empresa_id_codigo_key UNIQUE (empresa_id, codigo);


--
-- Name: productos productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pkey PRIMARY KEY (id);


--
-- Name: proveedor_adelanto_aplicaciones proveedor_adelanto_aplicaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelanto_aplicaciones
    ADD CONSTRAINT proveedor_adelanto_aplicaciones_pkey PRIMARY KEY (id);


--
-- Name: proveedor_adelantos proveedor_adelantos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos
    ADD CONSTRAINT proveedor_adelantos_pkey PRIMARY KEY (id);


--
-- Name: proveedores proveedores_empresa_documento_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_empresa_documento_unique UNIQUE (empresa_id, numero_documento);


--
-- Name: proveedores proveedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_pkey PRIMARY KEY (id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: salida_tipos salida_tipos_empresa_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salida_tipos
    ADD CONSTRAINT salida_tipos_empresa_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: salida_tipos salida_tipos_empresa_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salida_tipos
    ADD CONSTRAINT salida_tipos_empresa_slug_unique UNIQUE (empresa_id, slug);


--
-- Name: salida_tipos salida_tipos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salida_tipos
    ADD CONSTRAINT salida_tipos_pkey PRIMARY KEY (id);


--
-- Name: salidas_detalle salidas_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas_detalle
    ADD CONSTRAINT salidas_detalle_pkey PRIMARY KEY (id);


--
-- Name: salidas salidas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas
    ADD CONSTRAINT salidas_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: stock stock_almacen_id_producto_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_almacen_id_producto_id_key UNIQUE (almacen_id, producto_id);


--
-- Name: stock stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_pkey PRIMARY KEY (id);


--
-- Name: tipos_cambio tipos_cambio_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio
    ADD CONSTRAINT tipos_cambio_pkey PRIMARY KEY (id);


--
-- Name: tipos_metodo_pago tipos_metodo_pago_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_metodo_pago
    ADD CONSTRAINT tipos_metodo_pago_pkey PRIMARY KEY (id);


--
-- Name: tipos_metodo_pago tipos_metodo_pago_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_metodo_pago
    ADD CONSTRAINT tipos_metodo_pago_slug_unique UNIQUE (slug);


--
-- Name: transferencias_detalle transferencias_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_detalle
    ADD CONSTRAINT transferencias_detalle_pkey PRIMARY KEY (id);


--
-- Name: transferencias transferencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_pkey PRIMARY KEY (id);


--
-- Name: turno_arqueo_metodos turno_arqueo_metodos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo_metodos
    ADD CONSTRAINT turno_arqueo_metodos_pkey PRIMARY KEY (id);


--
-- Name: turno_arqueo_metodos turno_arqueo_metodos_turno_id_metodo_pago_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo_metodos
    ADD CONSTRAINT turno_arqueo_metodos_turno_id_metodo_pago_id_unique UNIQUE (turno_id, metodo_pago_id);


--
-- Name: turno_arqueo turno_arqueo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo
    ADD CONSTRAINT turno_arqueo_pkey PRIMARY KEY (id);


--
-- Name: turno_arqueo turno_arqueo_turno_id_denominacion_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo
    ADD CONSTRAINT turno_arqueo_turno_id_denominacion_unique UNIQUE (turno_id, denominacion);


--
-- Name: turno_cierre_productos turno_cierre_productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_cierre_productos
    ADD CONSTRAINT turno_cierre_productos_pkey PRIMARY KEY (id);


--
-- Name: turno_cierre_productos turno_cierre_productos_turno_id_producto_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_cierre_productos
    ADD CONSTRAINT turno_cierre_productos_turno_id_producto_id_unique UNIQUE (turno_id, producto_id);


--
-- Name: turno_consolidacion_items turno_consolidacion_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidacion_items
    ADD CONSTRAINT turno_consolidacion_items_pkey PRIMARY KEY (id);


--
-- Name: turno_consolidaciones turno_consolidaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidaciones
    ADD CONSTRAINT turno_consolidaciones_pkey PRIMARY KEY (id);


--
-- Name: turno_consolidaciones turno_consolidaciones_turno_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidaciones
    ADD CONSTRAINT turno_consolidaciones_turno_id_unique UNIQUE (turno_id);


--
-- Name: turnos turnos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos
    ADD CONSTRAINT turnos_pkey PRIMARY KEY (id);


--
-- Name: unidades_medida unidades_medida_empresa_id_abreviatura_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_empresa_id_abreviatura_key UNIQUE (empresa_id, abreviatura);


--
-- Name: unidades_medida unidades_medida_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: venta_abonos venta_abonos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_abonos
    ADD CONSTRAINT venta_abonos_pkey PRIMARY KEY (id);


--
-- Name: venta_items venta_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_items
    ADD CONSTRAINT venta_items_pkey PRIMARY KEY (id);


--
-- Name: venta_pagos venta_pagos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_pagos
    ADD CONSTRAINT venta_pagos_pkey PRIMARY KEY (id);


--
-- Name: ventas ventas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_pkey PRIMARY KEY (id);


--
-- Name: ventas ventas_turno_id_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_turno_id_numero_unique UNIQUE (turno_id, numero);


--
-- Name: auditoria_empresa_id_accion_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_empresa_id_accion_index ON public.auditoria USING btree (empresa_id, accion);


--
-- Name: auditoria_empresa_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_empresa_id_created_at_index ON public.auditoria USING btree (empresa_id, created_at);


--
-- Name: auditoria_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_empresa_id_index ON public.auditoria USING btree (empresa_id);


--
-- Name: auditoria_modelo_tipo_modelo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_modelo_tipo_modelo_id_index ON public.auditoria USING btree (modelo_tipo, modelo_id);


--
-- Name: auditoria_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_user_id_index ON public.auditoria USING btree (user_id);


--
-- Name: balance_diario_items_balance_diario_id_seccion_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX balance_diario_items_balance_diario_id_seccion_index ON public.balance_diario_items USING btree (balance_diario_id, seccion);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: cita_items_cita_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cita_items_cita_id_index ON public.cita_items USING btree (cita_id);


--
-- Name: cita_items_producto_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cita_items_producto_id_index ON public.cita_items USING btree (producto_id);


--
-- Name: cita_items_producto_unidad_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cita_items_producto_unidad_id_index ON public.cita_items USING btree (producto_unidad_id);


--
-- Name: citas_cliente_id_fecha_hora_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX citas_cliente_id_fecha_hora_index ON public.citas USING btree (cliente_id, fecha_hora);


--
-- Name: citas_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX citas_empresa_id_estado_index ON public.citas USING btree (empresa_id, estado);


--
-- Name: citas_empresa_id_fecha_hora_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX citas_empresa_id_fecha_hora_index ON public.citas USING btree (empresa_id, fecha_hora);


--
-- Name: citas_local_id_fecha_hora_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX citas_local_id_fecha_hora_index ON public.citas USING btree (local_id, fecha_hora);


--
-- Name: citas_numero_empresa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX citas_numero_empresa_unique ON public.citas USING btree (empresa_id, numero) WHERE (numero IS NOT NULL);


--
-- Name: citas_profesional_id_fecha_hora_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX citas_profesional_id_fecha_hora_index ON public.citas USING btree (profesional_id, fecha_hora);


--
-- Name: citas_venta_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX citas_venta_id_index ON public.citas USING btree (venta_id);


--
-- Name: cliente_anticipos_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cliente_anticipos_empresa_id_estado_index ON public.cliente_anticipos USING btree (empresa_id, estado);


--
-- Name: clientes_un_general_por_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX clientes_un_general_por_empresa ON public.clientes USING btree (empresa_id) WHERE (es_cliente_general = true);


--
-- Name: cuenta_movimientos_empresa_id_cuenta_id_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuenta_movimientos_empresa_id_cuenta_id_fecha_index ON public.cuenta_movimientos USING btree (empresa_id, cuenta_id, fecha);


--
-- Name: cuenta_movimientos_ref_tipo_ref_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuenta_movimientos_ref_tipo_ref_id_index ON public.cuenta_movimientos USING btree (ref_tipo, ref_id);


--
-- Name: deuda_pagos_deuda_id_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX deuda_pagos_deuda_id_fecha_index ON public.deuda_pagos USING btree (deuda_id, fecha);


--
-- Name: deudas_empresa_id_direccion_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX deudas_empresa_id_direccion_estado_index ON public.deudas USING btree (empresa_id, direccion, estado);


--
-- Name: entrada_pagos_entrada_id_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX entrada_pagos_entrada_id_fecha_index ON public.entrada_pagos USING btree (entrada_id, fecha);


--
-- Name: idx_cierres_inv_almacen; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cierres_inv_almacen ON public.cierres_inventario USING btree (almacen_id);


--
-- Name: idx_cierres_inv_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cierres_inv_empresa ON public.cierres_inventario USING btree (empresa_id);


--
-- Name: idx_cierres_inv_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cierres_inv_fecha ON public.cierres_inventario USING btree (fecha);


--
-- Name: idx_cierres_inv_items_cierre; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cierres_inv_items_cierre ON public.cierres_inventario_items USING btree (cierre_id);


--
-- Name: idx_cierres_inv_items_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cierres_inv_items_producto ON public.cierres_inventario_items USING btree (producto_id);


--
-- Name: idx_cierres_inv_turno; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cierres_inv_turno ON public.cierres_inventario USING btree (turno_id);


--
-- Name: idx_devolucion_motivos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devolucion_motivos_empresa ON public.devolucion_motivos USING btree (empresa_id);


--
-- Name: idx_devolucion_pagos_devolucion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devolucion_pagos_devolucion ON public.devolucion_pagos USING btree (devolucion_id);


--
-- Name: idx_devoluciones_detalle_devolucion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_detalle_devolucion ON public.devoluciones_detalle USING btree (devolucion_id);


--
-- Name: idx_devoluciones_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_detalle_producto ON public.devoluciones_detalle USING btree (producto_id);


--
-- Name: idx_devoluciones_detalle_venta_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_detalle_venta_item ON public.devoluciones_detalle USING btree (venta_item_id);


--
-- Name: idx_devoluciones_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_empresa ON public.devoluciones USING btree (empresa_id);


--
-- Name: idx_devoluciones_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_fecha ON public.devoluciones USING btree (fecha);


--
-- Name: idx_devoluciones_local; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_local ON public.devoluciones USING btree (local_id);


--
-- Name: idx_devoluciones_motivo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_motivo ON public.devoluciones USING btree (motivo_id);


--
-- Name: idx_devoluciones_turno; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_turno ON public.devoluciones USING btree (turno_id);


--
-- Name: idx_devoluciones_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_devoluciones_venta ON public.devoluciones USING btree (venta_id);


--
-- Name: idx_entradas_proveedor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entradas_proveedor ON public.entradas USING btree (proveedor_id);


--
-- Name: idx_proveedores_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_proveedores_activo ON public.proveedores USING btree (activo);


--
-- Name: idx_proveedores_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_proveedores_empresa ON public.proveedores USING btree (empresa_id);


--
-- Name: idx_salida_tipos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salida_tipos_empresa ON public.salida_tipos USING btree (empresa_id);


--
-- Name: idx_salidas_almacen; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_almacen ON public.salidas USING btree (almacen_id);


--
-- Name: idx_salidas_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_detalle_producto ON public.salidas_detalle USING btree (producto_id);


--
-- Name: idx_salidas_detalle_salida; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_detalle_salida ON public.salidas_detalle USING btree (salida_id);


--
-- Name: idx_salidas_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_empresa ON public.salidas USING btree (empresa_id);


--
-- Name: idx_salidas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_fecha ON public.salidas USING btree (fecha);


--
-- Name: idx_salidas_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_tipo ON public.salidas USING btree (salida_tipo_id);


--
-- Name: idx_salidas_turno; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_salidas_turno ON public.salidas USING btree (turno_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: planilla_descuentos_empresa_id_user_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX planilla_descuentos_empresa_id_user_id_estado_index ON public.planilla_descuentos USING btree (empresa_id, user_id, estado);


--
-- Name: proveedor_adelantos_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX proveedor_adelantos_empresa_id_estado_index ON public.proveedor_adelantos USING btree (empresa_id, estado);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: tipos_cambio_fecha_moneda_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tipos_cambio_fecha_moneda_unique ON public.tipos_cambio USING btree (fecha, moneda);


--
-- Name: turno_consolidaciones_empresa_id_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX turno_consolidaciones_empresa_id_fecha_index ON public.turno_consolidaciones USING btree (empresa_id, fecha);


--
-- Name: venta_abonos_venta_id_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX venta_abonos_venta_id_fecha_index ON public.venta_abonos USING btree (venta_id, fecha);


--
-- Name: ventas_cxc_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ventas_cxc_index ON public.ventas USING btree (empresa_id, es_credito, saldo_pendiente);


--
-- Name: ventas_idempotency_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ventas_idempotency_key_unique ON public.ventas USING btree (idempotency_key) WHERE (idempotency_key IS NOT NULL);


--
-- Name: almacenes almacenes_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: almacenes almacenes_local_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_local_id_fkey FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE SET NULL;


--
-- Name: auditoria auditoria_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: auditoria auditoria_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: balance_diario_items balance_diario_items_balance_diario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balance_diario_items
    ADD CONSTRAINT balance_diario_items_balance_diario_id_foreign FOREIGN KEY (balance_diario_id) REFERENCES public.balances_diarios(id) ON DELETE CASCADE;


--
-- Name: balances_diarios balances_diarios_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balances_diarios
    ADD CONSTRAINT balances_diarios_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: balances_diarios balances_diarios_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.balances_diarios
    ADD CONSTRAINT balances_diarios_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: cajas cajas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cajas cajas_local_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_local_id_foreign FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE CASCADE;


--
-- Name: categorias categorias_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cierres_inventario cierres_inventario_almacen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario
    ADD CONSTRAINT cierres_inventario_almacen_id_fkey FOREIGN KEY (almacen_id) REFERENCES public.almacenes(id);


--
-- Name: cierres_inventario cierres_inventario_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario
    ADD CONSTRAINT cierres_inventario_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cierres_inventario_items cierres_inventario_items_cierre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario_items
    ADD CONSTRAINT cierres_inventario_items_cierre_id_fkey FOREIGN KEY (cierre_id) REFERENCES public.cierres_inventario(id) ON DELETE CASCADE;


--
-- Name: cierres_inventario_items cierres_inventario_items_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario_items
    ADD CONSTRAINT cierres_inventario_items_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: cierres_inventario cierres_inventario_turno_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario
    ADD CONSTRAINT cierres_inventario_turno_id_fkey FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE SET NULL;


--
-- Name: cierres_inventario cierres_inventario_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_inventario
    ADD CONSTRAINT cierres_inventario_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: cita_items cita_items_cita_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cita_items
    ADD CONSTRAINT cita_items_cita_id_foreign FOREIGN KEY (cita_id) REFERENCES public.citas(id) ON DELETE CASCADE;


--
-- Name: cita_items cita_items_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cita_items
    ADD CONSTRAINT cita_items_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE RESTRICT;


--
-- Name: cita_items cita_items_producto_unidad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cita_items
    ADD CONSTRAINT cita_items_producto_unidad_id_foreign FOREIGN KEY (producto_unidad_id) REFERENCES public.producto_unidades(id) ON DELETE RESTRICT;


--
-- Name: citas citas_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE RESTRICT;


--
-- Name: citas citas_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: citas citas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: citas citas_local_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_local_id_foreign FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE RESTRICT;


--
-- Name: citas citas_profesional_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_profesional_id_foreign FOREIGN KEY (profesional_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: citas citas_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.citas
    ADD CONSTRAINT citas_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE RESTRICT;


--
-- Name: cliente_anticipo_aplicaciones cliente_anticipo_aplicaciones_cliente_anticipo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipo_aplicaciones
    ADD CONSTRAINT cliente_anticipo_aplicaciones_cliente_anticipo_id_foreign FOREIGN KEY (cliente_anticipo_id) REFERENCES public.cliente_anticipos(id) ON DELETE CASCADE;


--
-- Name: cliente_anticipo_aplicaciones cliente_anticipo_aplicaciones_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipo_aplicaciones
    ADD CONSTRAINT cliente_anticipo_aplicaciones_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: cliente_anticipo_aplicaciones cliente_anticipo_aplicaciones_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipo_aplicaciones
    ADD CONSTRAINT cliente_anticipo_aplicaciones_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE SET NULL;


--
-- Name: cliente_anticipos cliente_anticipos_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: cliente_anticipos cliente_anticipos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: cliente_anticipos cliente_anticipos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cliente_anticipos cliente_anticipos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: cliente_anticipos cliente_anticipos_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE SET NULL;


--
-- Name: cliente_anticipos cliente_anticipos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cliente_anticipos
    ADD CONSTRAINT cliente_anticipos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: clientes clientes_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cuenta_metodo_pago cuenta_metodo_pago_cuenta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_metodo_pago
    ADD CONSTRAINT cuenta_metodo_pago_cuenta_id_fkey FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE CASCADE;


--
-- Name: cuenta_metodo_pago cuenta_metodo_pago_metodo_pago_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_metodo_pago
    ADD CONSTRAINT cuenta_metodo_pago_metodo_pago_id_fkey FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE CASCADE;


--
-- Name: cuenta_movimientos cuenta_movimientos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_movimientos
    ADD CONSTRAINT cuenta_movimientos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE CASCADE;


--
-- Name: cuenta_movimientos cuenta_movimientos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_movimientos
    ADD CONSTRAINT cuenta_movimientos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cuenta_movimientos cuenta_movimientos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuenta_movimientos
    ADD CONSTRAINT cuenta_movimientos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: cuentas cuentas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas
    ADD CONSTRAINT cuentas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: descuento_conceptos descuento_conceptos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuento_conceptos
    ADD CONSTRAINT descuento_conceptos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: descuentos_log descuentos_log_aprobado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_aprobado_por_foreign FOREIGN KEY (aprobado_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: descuentos_log descuentos_log_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: descuentos_log descuentos_log_descuento_concepto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_descuento_concepto_id_foreign FOREIGN KEY (descuento_concepto_id) REFERENCES public.descuento_conceptos(id);


--
-- Name: descuentos_log descuentos_log_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: descuentos_log descuentos_log_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: descuentos_log descuentos_log_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE SET NULL;


--
-- Name: descuentos_log descuentos_log_venta_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_log
    ADD CONSTRAINT descuentos_log_venta_item_id_foreign FOREIGN KEY (venta_item_id) REFERENCES public.venta_items(id) ON DELETE SET NULL;


--
-- Name: deuda_pagos deuda_pagos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deuda_pagos
    ADD CONSTRAINT deuda_pagos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: deuda_pagos deuda_pagos_deuda_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deuda_pagos
    ADD CONSTRAINT deuda_pagos_deuda_id_foreign FOREIGN KEY (deuda_id) REFERENCES public.deudas(id) ON DELETE CASCADE;


--
-- Name: deuda_pagos deuda_pagos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deuda_pagos
    ADD CONSTRAINT deuda_pagos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: deuda_pagos deuda_pagos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deuda_pagos
    ADD CONSTRAINT deuda_pagos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: deudas deudas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deudas
    ADD CONSTRAINT deudas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: deudas deudas_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deudas
    ADD CONSTRAINT deudas_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: devolucion_motivos devolucion_motivos_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_motivos
    ADD CONSTRAINT devolucion_motivos_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: devolucion_pagos devolucion_pagos_devolucion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_pagos
    ADD CONSTRAINT devolucion_pagos_devolucion_id_fkey FOREIGN KEY (devolucion_id) REFERENCES public.devoluciones(id) ON DELETE CASCADE;


--
-- Name: devolucion_pagos devolucion_pagos_metodo_pago_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devolucion_pagos
    ADD CONSTRAINT devolucion_pagos_metodo_pago_id_fkey FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id);


--
-- Name: devoluciones devoluciones_caja_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_caja_id_fkey FOREIGN KEY (caja_id) REFERENCES public.cajas(id) ON DELETE SET NULL;


--
-- Name: devoluciones_detalle devoluciones_detalle_devolucion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle
    ADD CONSTRAINT devoluciones_detalle_devolucion_id_fkey FOREIGN KEY (devolucion_id) REFERENCES public.devoluciones(id) ON DELETE CASCADE;


--
-- Name: devoluciones_detalle devoluciones_detalle_motivo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle
    ADD CONSTRAINT devoluciones_detalle_motivo_id_fkey FOREIGN KEY (motivo_id) REFERENCES public.devolucion_motivos(id);


--
-- Name: devoluciones_detalle devoluciones_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle
    ADD CONSTRAINT devoluciones_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: devoluciones_detalle devoluciones_detalle_producto_unidad_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle
    ADD CONSTRAINT devoluciones_detalle_producto_unidad_id_fkey FOREIGN KEY (producto_unidad_id) REFERENCES public.producto_unidades(id);


--
-- Name: devoluciones_detalle devoluciones_detalle_venta_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones_detalle
    ADD CONSTRAINT devoluciones_detalle_venta_item_id_fkey FOREIGN KEY (venta_item_id) REFERENCES public.venta_items(id);


--
-- Name: devoluciones devoluciones_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: devoluciones devoluciones_local_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_local_id_fkey FOREIGN KEY (local_id) REFERENCES public.locales(id);


--
-- Name: devoluciones devoluciones_motivo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_motivo_id_fkey FOREIGN KEY (motivo_id) REFERENCES public.devolucion_motivos(id);


--
-- Name: devoluciones devoluciones_turno_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_turno_id_fkey FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE SET NULL;


--
-- Name: devoluciones devoluciones_user_aprobacion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_user_aprobacion_id_fkey FOREIGN KEY (user_aprobacion_id) REFERENCES public.users(id);


--
-- Name: devoluciones devoluciones_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: devoluciones devoluciones_venta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_venta_id_fkey FOREIGN KEY (venta_id) REFERENCES public.ventas(id);


--
-- Name: entrada_pagos entrada_pagos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos
    ADD CONSTRAINT entrada_pagos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: entrada_pagos entrada_pagos_entrada_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos
    ADD CONSTRAINT entrada_pagos_entrada_id_foreign FOREIGN KEY (entrada_id) REFERENCES public.entradas(id) ON DELETE CASCADE;


--
-- Name: entrada_pagos entrada_pagos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos
    ADD CONSTRAINT entrada_pagos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: entrada_pagos entrada_pagos_proveedor_adelanto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos
    ADD CONSTRAINT entrada_pagos_proveedor_adelanto_id_foreign FOREIGN KEY (proveedor_adelanto_id) REFERENCES public.proveedor_adelantos(id) ON DELETE SET NULL;


--
-- Name: entrada_pagos entrada_pagos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entrada_pagos
    ADD CONSTRAINT entrada_pagos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: entradas entradas_almacen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_almacen_id_fkey FOREIGN KEY (almacen_id) REFERENCES public.almacenes(id);


--
-- Name: entradas entradas_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: entradas_detalle entradas_detalle_entrada_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas_detalle
    ADD CONSTRAINT entradas_detalle_entrada_id_fkey FOREIGN KEY (entrada_id) REFERENCES public.entradas(id) ON DELETE CASCADE;


--
-- Name: entradas_detalle entradas_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas_detalle
    ADD CONSTRAINT entradas_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: entradas_detalle entradas_detalle_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas_detalle
    ADD CONSTRAINT entradas_detalle_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);


--
-- Name: entradas entradas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: entradas entradas_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: entradas entradas_proveedor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_proveedor_id_fkey FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id) ON DELETE SET NULL;


--
-- Name: entradas entradas_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entradas
    ADD CONSTRAINT entradas_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: gasto_conceptos gasto_conceptos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_conceptos
    ADD CONSTRAINT gasto_conceptos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: gasto_conceptos gasto_conceptos_gasto_tipo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_conceptos
    ADD CONSTRAINT gasto_conceptos_gasto_tipo_id_foreign FOREIGN KEY (gasto_tipo_id) REFERENCES public.gasto_tipos(id) ON DELETE CASCADE;


--
-- Name: gasto_tipos gasto_tipos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gasto_tipos
    ADD CONSTRAINT gasto_tipos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: gastos gastos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: gastos gastos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: gastos gastos_gasto_concepto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_gasto_concepto_id_foreign FOREIGN KEY (gasto_concepto_id) REFERENCES public.gasto_conceptos(id);


--
-- Name: gastos gastos_gasto_tipo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_gasto_tipo_id_foreign FOREIGN KEY (gasto_tipo_id) REFERENCES public.gasto_tipos(id);


--
-- Name: gastos gastos_local_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_local_id_foreign FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE CASCADE;


--
-- Name: gastos gastos_turno_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_turno_id_foreign FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE SET NULL;


--
-- Name: gastos gastos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: locales locales_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locales
    ADD CONSTRAINT locales_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: metodos_pago metodos_pago_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metodos_pago
    ADD CONSTRAINT metodos_pago_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: metodos_pago metodos_pago_tipo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metodos_pago
    ADD CONSTRAINT metodos_pago_tipo_id_foreign FOREIGN KEY (tipo_id) REFERENCES public.tipos_metodo_pago(id) ON DELETE RESTRICT;


--
-- Name: modulos modulos_padre_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_padre_id_foreign FOREIGN KEY (padre_id) REFERENCES public.modulos(id) ON DELETE SET NULL;


--
-- Name: permisos permisos_modulo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_modulo_id_foreign FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: permisos permisos_rol_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_rol_id_foreign FOREIGN KEY (rol_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: planilla_descuentos planilla_descuentos_aplicado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planilla_descuentos
    ADD CONSTRAINT planilla_descuentos_aplicado_por_foreign FOREIGN KEY (aplicado_por) REFERENCES public.users(id);


--
-- Name: planilla_descuentos planilla_descuentos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planilla_descuentos
    ADD CONSTRAINT planilla_descuentos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: planilla_descuentos planilla_descuentos_registrado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planilla_descuentos
    ADD CONSTRAINT planilla_descuentos_registrado_por_foreign FOREIGN KEY (registrado_por) REFERENCES public.users(id);


--
-- Name: planilla_descuentos planilla_descuentos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planilla_descuentos
    ADD CONSTRAINT planilla_descuentos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: producto_unidades producto_unidades_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_unidades
    ADD CONSTRAINT producto_unidades_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE CASCADE;


--
-- Name: producto_unidades producto_unidades_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_unidades
    ADD CONSTRAINT producto_unidades_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id) ON DELETE CASCADE;


--
-- Name: productos productos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE SET NULL;


--
-- Name: productos productos_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: proveedor_adelanto_aplicaciones proveedor_adelanto_aplicaciones_entrada_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelanto_aplicaciones
    ADD CONSTRAINT proveedor_adelanto_aplicaciones_entrada_id_foreign FOREIGN KEY (entrada_id) REFERENCES public.entradas(id) ON DELETE SET NULL;


--
-- Name: proveedor_adelanto_aplicaciones proveedor_adelanto_aplicaciones_proveedor_adelanto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelanto_aplicaciones
    ADD CONSTRAINT proveedor_adelanto_aplicaciones_proveedor_adelanto_id_foreign FOREIGN KEY (proveedor_adelanto_id) REFERENCES public.proveedor_adelantos(id) ON DELETE CASCADE;


--
-- Name: proveedor_adelanto_aplicaciones proveedor_adelanto_aplicaciones_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelanto_aplicaciones
    ADD CONSTRAINT proveedor_adelanto_aplicaciones_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: proveedor_adelantos proveedor_adelantos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos
    ADD CONSTRAINT proveedor_adelantos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: proveedor_adelantos proveedor_adelantos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos
    ADD CONSTRAINT proveedor_adelantos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: proveedor_adelantos proveedor_adelantos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos
    ADD CONSTRAINT proveedor_adelantos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: proveedor_adelantos proveedor_adelantos_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos
    ADD CONSTRAINT proveedor_adelantos_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: proveedor_adelantos proveedor_adelantos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedor_adelantos
    ADD CONSTRAINT proveedor_adelantos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: proveedores proveedores_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: roles roles_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: salida_tipos salida_tipos_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salida_tipos
    ADD CONSTRAINT salida_tipos_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: salidas salidas_almacen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas
    ADD CONSTRAINT salidas_almacen_id_fkey FOREIGN KEY (almacen_id) REFERENCES public.almacenes(id);


--
-- Name: salidas_detalle salidas_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas_detalle
    ADD CONSTRAINT salidas_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: salidas_detalle salidas_detalle_salida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas_detalle
    ADD CONSTRAINT salidas_detalle_salida_id_fkey FOREIGN KEY (salida_id) REFERENCES public.salidas(id) ON DELETE CASCADE;


--
-- Name: salidas_detalle salidas_detalle_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas_detalle
    ADD CONSTRAINT salidas_detalle_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);


--
-- Name: salidas salidas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas
    ADD CONSTRAINT salidas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: salidas salidas_salida_tipo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas
    ADD CONSTRAINT salidas_salida_tipo_id_fkey FOREIGN KEY (salida_tipo_id) REFERENCES public.salida_tipos(id);


--
-- Name: salidas salidas_turno_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas
    ADD CONSTRAINT salidas_turno_id_fkey FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE SET NULL;


--
-- Name: salidas salidas_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salidas
    ADD CONSTRAINT salidas_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: stock stock_almacen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_almacen_id_fkey FOREIGN KEY (almacen_id) REFERENCES public.almacenes(id) ON DELETE CASCADE;


--
-- Name: stock stock_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE CASCADE;


--
-- Name: transferencias transferencias_almacen_destino_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_almacen_destino_id_fkey FOREIGN KEY (almacen_destino_id) REFERENCES public.almacenes(id);


--
-- Name: transferencias transferencias_almacen_origen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_almacen_origen_id_fkey FOREIGN KEY (almacen_origen_id) REFERENCES public.almacenes(id);


--
-- Name: transferencias_detalle transferencias_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_detalle
    ADD CONSTRAINT transferencias_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: transferencias_detalle transferencias_detalle_transferencia_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_detalle
    ADD CONSTRAINT transferencias_detalle_transferencia_id_fkey FOREIGN KEY (transferencia_id) REFERENCES public.transferencias(id) ON DELETE CASCADE;


--
-- Name: transferencias_detalle transferencias_detalle_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_detalle
    ADD CONSTRAINT transferencias_detalle_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);


--
-- Name: transferencias transferencias_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: transferencias transferencias_user_envio_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_user_envio_id_fkey FOREIGN KEY (user_envio_id) REFERENCES public.users(id);


--
-- Name: transferencias transferencias_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: transferencias transferencias_user_recepcion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias
    ADD CONSTRAINT transferencias_user_recepcion_id_fkey FOREIGN KEY (user_recepcion_id) REFERENCES public.users(id);


--
-- Name: turno_arqueo_metodos turno_arqueo_metodos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo_metodos
    ADD CONSTRAINT turno_arqueo_metodos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id);


--
-- Name: turno_arqueo_metodos turno_arqueo_metodos_turno_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo_metodos
    ADD CONSTRAINT turno_arqueo_metodos_turno_id_foreign FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE CASCADE;


--
-- Name: turno_arqueo turno_arqueo_turno_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_arqueo
    ADD CONSTRAINT turno_arqueo_turno_id_foreign FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE CASCADE;


--
-- Name: turno_cierre_productos turno_cierre_productos_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_cierre_productos
    ADD CONSTRAINT turno_cierre_productos_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: turno_cierre_productos turno_cierre_productos_turno_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_cierre_productos
    ADD CONSTRAINT turno_cierre_productos_turno_id_foreign FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE CASCADE;


--
-- Name: turno_consolidacion_items turno_consolidacion_items_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidacion_items
    ADD CONSTRAINT turno_consolidacion_items_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: turno_consolidacion_items turno_consolidacion_items_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidacion_items
    ADD CONSTRAINT turno_consolidacion_items_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: turno_consolidacion_items turno_consolidacion_items_turno_consolidacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidacion_items
    ADD CONSTRAINT turno_consolidacion_items_turno_consolidacion_id_foreign FOREIGN KEY (turno_consolidacion_id) REFERENCES public.turno_consolidaciones(id) ON DELETE CASCADE;


--
-- Name: turno_consolidaciones turno_consolidaciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidaciones
    ADD CONSTRAINT turno_consolidaciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: turno_consolidaciones turno_consolidaciones_turno_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidaciones
    ADD CONSTRAINT turno_consolidaciones_turno_id_foreign FOREIGN KEY (turno_id) REFERENCES public.turnos(id) ON DELETE CASCADE;


--
-- Name: turno_consolidaciones turno_consolidaciones_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turno_consolidaciones
    ADD CONSTRAINT turno_consolidaciones_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: turnos turnos_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos
    ADD CONSTRAINT turnos_caja_id_foreign FOREIGN KEY (caja_id) REFERENCES public.cajas(id) ON DELETE CASCADE;


--
-- Name: turnos turnos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos
    ADD CONSTRAINT turnos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: turnos turnos_local_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos
    ADD CONSTRAINT turnos_local_id_foreign FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE CASCADE;


--
-- Name: turnos turnos_user_cierre_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos
    ADD CONSTRAINT turnos_user_cierre_id_foreign FOREIGN KEY (user_cierre_id) REFERENCES public.users(id);


--
-- Name: turnos turnos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos
    ADD CONSTRAINT turnos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: unidades_medida unidades_medida_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: users users_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: users users_local_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_local_id_foreign FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE SET NULL;


--
-- Name: users users_rol_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_rol_id_foreign FOREIGN KEY (rol_id) REFERENCES public.roles(id) ON DELETE SET NULL;


--
-- Name: venta_abonos venta_abonos_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_abonos
    ADD CONSTRAINT venta_abonos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id) ON DELETE SET NULL;


--
-- Name: venta_abonos venta_abonos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_abonos
    ADD CONSTRAINT venta_abonos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id) ON DELETE SET NULL;


--
-- Name: venta_abonos venta_abonos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_abonos
    ADD CONSTRAINT venta_abonos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: venta_abonos venta_abonos_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_abonos
    ADD CONSTRAINT venta_abonos_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE CASCADE;


--
-- Name: venta_items venta_items_descuento_concepto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_items
    ADD CONSTRAINT venta_items_descuento_concepto_id_foreign FOREIGN KEY (descuento_concepto_id) REFERENCES public.descuento_conceptos(id) ON DELETE SET NULL;


--
-- Name: venta_items venta_items_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_items
    ADD CONSTRAINT venta_items_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: venta_items venta_items_producto_unidad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_items
    ADD CONSTRAINT venta_items_producto_unidad_id_foreign FOREIGN KEY (producto_unidad_id) REFERENCES public.producto_unidades(id);


--
-- Name: venta_items venta_items_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_items
    ADD CONSTRAINT venta_items_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE CASCADE;


--
-- Name: venta_pagos venta_pagos_cuenta_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_pagos
    ADD CONSTRAINT venta_pagos_cuenta_metodo_pago_id_foreign FOREIGN KEY (cuenta_metodo_pago_id) REFERENCES public.cuenta_metodo_pago(id) ON DELETE SET NULL;


--
-- Name: venta_pagos venta_pagos_metodo_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_pagos
    ADD CONSTRAINT venta_pagos_metodo_pago_id_foreign FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id);


--
-- Name: venta_pagos venta_pagos_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venta_pagos
    ADD CONSTRAINT venta_pagos_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE CASCADE;


--
-- Name: ventas ventas_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_caja_id_foreign FOREIGN KEY (caja_id) REFERENCES public.cajas(id);


--
-- Name: ventas ventas_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: ventas ventas_descuento_concepto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_descuento_concepto_id_foreign FOREIGN KEY (descuento_concepto_id) REFERENCES public.descuento_conceptos(id) ON DELETE SET NULL;


--
-- Name: ventas ventas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: ventas ventas_local_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_local_id_foreign FOREIGN KEY (local_id) REFERENCES public.locales(id) ON DELETE CASCADE;


--
-- Name: ventas ventas_turno_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_turno_id_foreign FOREIGN KEY (turno_id) REFERENCES public.turnos(id);


--
-- Name: ventas ventas_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- PostgreSQL database dump complete
--

\unrestrict bRgjSs61c063zj7E55Jx3tGbsOvYv1NbV1r7Hgkj8NcNcFFPdqDaU2AcNlcIrG6

