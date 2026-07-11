--
-- PostgreSQL database dump
--

\restrict Zg8lrboonGheibpKDFVhmuegk7njezjwIgDQIH3vUb3OM5RC6JmOYzTZHuE8x14

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
    permite_stock_negativo boolean DEFAULT false NOT NULL,
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
1110	1097	1092	Tienda Principal	local	t	2026-07-11 10:12:11	2026-07-11 10:12:11
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
399	1097	1295	Administrador H&C	tesoreria.ajuste	App\\Models\\CuentaMovimiento	456	{"cuenta": "Cuenta BCP Soles", "motivo": "Saldo inicial — migración del sistema anterior (Excel 10-07)", "diferencia": 77544.29, "saldo_real": 77544.29, "saldo_previo": 0}	127.0.0.1	Symfony	2026-07-11 10:12:12
400	1097	1295	Administrador H&C	tesoreria.ajuste	App\\Models\\CuentaMovimiento	457	{"cuenta": "Cuenta BBVA Soles", "motivo": "Saldo inicial — migración del sistema anterior (Excel 10-07)", "diferencia": 18064.07, "saldo_real": 18064.07, "saldo_previo": 0}	127.0.0.1	Symfony	2026-07-11 10:12:12
401	1097	1295	Administrador H&C	tesoreria.ajuste	App\\Models\\CuentaMovimiento	458	{"cuenta": "Efectivo", "motivo": "Saldo inicial — migración del sistema anterior (Excel 10-07)", "diferencia": 23903.49, "saldo_real": 23903.49, "saldo_previo": 0}	127.0.0.1	Symfony	2026-07-11 10:12:12
402	1097	1295	Administrador H&C	balance.confirmado	App\\Models\\BalanceDiario	67	{"fecha": "2026-07-10", "balance_neto": 161060.87, "utilidad_real": 1454.14}	127.0.0.1	Symfony	2026-07-11 10:12:12
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
2154	59	favor	migracion	CUENTA BCP SOLES	\N	\N	44915.86	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2155	59	favor	migracion	CUENTA BBVA SOLES	\N	\N	4693.54	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2156	59	favor	migracion	EFECTIVO	\N	\N	11861.21	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2157	59	favor	migracion	STOCK (INVENTARIO)	\N	\N	239077.29	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2158	59	favor	migracion	DEUDAS POR COBRAR	\N	\N	79568.59	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2159	59	favor	migracion	JHON ASTONITAS	\N	\N	345.05	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2160	59	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	112896.61	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2161	59	contra	migracion	CLIENTES ANTICIPOS	\N	\N	36133.90	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2162	59	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2163	59	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2164	59	contra	migracion	PERSONAL	\N	\N	1400.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2165	59	contra	migracion	JORDIN HERRERA	\N	\N	30000.00	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2166	60	favor	migracion	CUENTA BCP SOLES	\N	\N	161631.96	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2167	60	favor	migracion	CUENTA BBVA SOLES	\N	\N	7518.43	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2168	60	favor	migracion	EFECTIVO	\N	\N	8606.11	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2169	60	favor	migracion	STOCK (INVENTARIO)	\N	\N	234496.21	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2170	60	favor	migracion	DEUDAS POR COBRAR	\N	\N	83983.42	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2171	60	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	101280.01	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2172	60	contra	migracion	CLIENTES ANTICIPOS	\N	\N	34341.90	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2173	60	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2174	60	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2175	60	contra	migracion	PERSONAL	\N	\N	2000.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2176	60	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	132000.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2177	60	contra	migracion	JORDIN HERRERA	\N	\N	30000.00	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2178	61	favor	migracion	CUENTA BCP SOLES	\N	\N	32920.56	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2179	61	favor	migracion	CUENTA BBVA SOLES	\N	\N	7814.43	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2180	61	favor	migracion	EFECTIVO	\N	\N	2866.11	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2181	61	favor	migracion	STOCK (INVENTARIO)	\N	\N	230624.62	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
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
2182	61	favor	migracion	DEUDAS POR COBRAR	\N	\N	81367.46	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2183	61	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	113041.61	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2184	61	contra	migracion	CLIENTES ANTICIPOS	\N	\N	35275.94	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
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
2185	61	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2186	61	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2187	61	contra	migracion	PERSONAL	\N	\N	2500.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2188	61	contra	migracion	JEINER	\N	\N	435.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2189	61	contra	migracion	MILAGROS	\N	\N	200.00	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2190	61	contra	migracion	LADRILLO H&C	\N	\N	5992.00	t	t	12	2026-07-11 10:12:12	2026-07-11 10:12:12
2191	62	favor	migracion	CUENTA BCP SOLES	\N	\N	43045.41	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2192	62	favor	migracion	CUENTA BBVA SOLES	\N	\N	8098.43	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2193	62	favor	migracion	EFECTIVO	\N	\N	5392.89	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2194	62	favor	migracion	STOCK (INVENTARIO)	\N	\N	218381.60	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2195	62	favor	migracion	DEUDAS POR COBRAR	\N	\N	74345.13	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2196	62	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	113041.61	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2197	62	contra	migracion	CLIENTES ANTICIPOS	\N	\N	37202.94	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2198	62	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2199	62	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2200	62	contra	migracion	MILAGROS	\N	\N	494.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2201	62	contra	migracion	YAPE DESCONOCIDO	\N	\N	539.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2202	63	favor	migracion	CUENTA BCP SOLES	\N	\N	20944.70	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2203	63	favor	migracion	CUENTA BBVA SOLES	\N	\N	8423.47	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2204	63	favor	migracion	EFECTIVO	\N	\N	8595.89	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2205	63	favor	migracion	STOCK (INVENTARIO)	\N	\N	246361.54	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2206	63	favor	migracion	DEUDAS POR COBRAR	\N	\N	79864.03	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2207	63	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	116530.01	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2208	63	contra	migracion	CLIENTES ANTICIPOS	\N	\N	35945.24	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2209	63	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2210	63	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2211	63	contra	migracion	PERSONAL	\N	\N	600.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2212	63	contra	migracion	MILAGROS	\N	\N	494.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2213	63	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	9747.00	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2214	63	contra	migracion	YAPE DESCONOCIDO	\N	\N	144.50	t	t	12	2026-07-11 10:12:12	2026-07-11 10:12:12
2215	63	contra	migracion	YAPE DESCONOCIDO 2	\N	\N	2200.00	t	t	13	2026-07-11 10:12:12	2026-07-11 10:12:12
2216	63	contra	migracion	SALDO DE CEMENTO HOLCIM	\N	\N	290.00	t	t	14	2026-07-11 10:12:12	2026-07-11 10:12:12
2217	63	contra	migracion	YAPE DE CAMILO - DESMONTE	\N	\N	300.00	t	t	15	2026-07-11 10:12:12	2026-07-11 10:12:12
2218	64	favor	migracion	CUENTA BCP SOLES	\N	\N	4248.20	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2219	64	favor	migracion	CUENTA BBVA SOLES	\N	\N	14134.62	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2220	64	favor	migracion	EFECTIVO	\N	\N	16257.49	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2221	64	favor	migracion	STOCK (INVENTARIO)	\N	\N	236830.27	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2222	64	favor	migracion	DEUDAS POR COBRAR	\N	\N	74623.63	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2223	64	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	91616.60	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2224	64	contra	migracion	CLIENTES ANTICIPOS	\N	\N	33123.24	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2225	64	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2226	64	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2227	64	contra	migracion	PERSONAL	\N	\N	1200.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2228	64	contra	migracion	MILAGROS	\N	\N	494.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2229	64	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	9747.00	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2230	64	contra	migracion	JEINER HERRERA - AGREGADOS	\N	\N	245.00	t	t	12	2026-07-11 10:12:12	2026-07-11 10:12:12
1909	14	favor	efectivo	Efectivo	cuenta	1	14895.11	f	f	1	2026-07-09 20:38:25	2026-07-09 20:38:25
1910	14	favor	cuenta_bancaria	Cuenta BBVA Soles	cuenta	15	4862.14	f	f	2	2026-07-09 20:38:25	2026-07-09 20:38:25
1911	14	favor	cuenta_bancaria	Cuenta BCP Soles	cuenta	14	107937.86	f	f	3	2026-07-09 20:38:25	2026-07-09 20:38:25
1912	14	favor	cuenta_bancaria	Plin	cuenta	17	0.00	f	f	4	2026-07-09 20:38:25	2026-07-09 20:38:25
1913	14	favor	cuenta_bancaria	Tarjeta	cuenta	4	0.00	f	f	5	2026-07-09 20:38:25	2026-07-09 20:38:25
1914	14	favor	cuenta_bancaria	Yape	cuenta	16	9787.50	f	f	6	2026-07-09 20:38:25	2026-07-09 20:38:25
1915	14	favor	stock	Stock (inventario valorizado)	\N	\N	226606.22	f	f	7	2026-07-09 20:38:25	2026-07-09 20:38:25
1916	14	favor	cxc	Deudas por cobrar (ventas a crédito)	\N	\N	67845.40	f	f	8	2026-07-09 20:38:25	2026-07-09 20:38:25
1917	14	favor	prestamo_otorgado	JHON ASTONITAS	deuda	19	245.05	f	f	9	2026-07-09 20:38:25	2026-07-09 20:38:25
2231	64	contra	migracion	ALPES	\N	\N	11550.00	t	t	13	2026-07-11 10:12:12	2026-07-11 10:12:12
2232	64	contra	migracion	SALDO DE CEMENTO HOLCIM	\N	\N	290.00	t	t	14	2026-07-11 10:12:12	2026-07-11 10:12:12
2233	64	contra	migracion	LUIS QUEVEDO	\N	\N	855.00	t	t	15	2026-07-11 10:12:12	2026-07-11 10:12:12
2234	65	favor	migracion	CUENTA BCP SOLES	\N	\N	62927.50	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2235	65	favor	migracion	CUENTA BBVA SOLES	\N	\N	16629.52	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2236	65	favor	migracion	EFECTIVO	\N	\N	11038.79	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2237	65	favor	migracion	STOCK (INVENTARIO)	\N	\N	258931.52	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2238	65	favor	migracion	DEUDAS POR COBRAR	\N	\N	81866.63	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2239	65	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	122036.10	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2240	65	contra	migracion	CLIENTES ANTICIPOS	\N	\N	53088.74	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2241	65	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2242	65	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2243	65	contra	migracion	PERSONAL	\N	\N	1600.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2244	65	contra	migracion	MILAGROS	\N	\N	494.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2245	65	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	55028.90	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2246	65	contra	migracion	SALDO DE CEMENTO HOLCIM	\N	\N	290.00	t	t	12	2026-07-11 10:12:12	2026-07-11 10:12:12
2247	65	contra	migracion	LUIS QUEVEDO	\N	\N	855.00	t	t	13	2026-07-11 10:12:12	2026-07-11 10:12:12
2248	66	favor	migracion	CUENTA BCP SOLES	\N	\N	67292.30	t	t	0	2026-07-11 10:12:12	2026-07-11 10:12:12
2249	66	favor	migracion	CUENTA BBVA SOLES	\N	\N	18064.07	t	t	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2250	66	favor	migracion	EFECTIVO	\N	\N	13591.69	t	t	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2251	66	favor	migracion	STOCK (INVENTARIO)	\N	\N	242444.33	t	t	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2252	66	favor	migracion	DEUDAS POR COBRAR	\N	\N	88866.98	t	t	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2253	66	contra	migracion	PROVEEDORES POR PAGAR	\N	\N	120949.60	t	t	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2254	66	contra	migracion	CLIENTES ANTICIPOS	\N	\N	51883.24	t	t	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2255	66	contra	migracion	DEUDA BCP 1 - 7630	\N	\N	6173.81	t	t	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2256	66	contra	migracion	DEUDA BCP 2 - 5557	\N	\N	32546.43	t	t	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2257	66	contra	migracion	PERSONAL	\N	\N	2000.00	t	t	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2258	66	contra	migracion	MILAGROS	\N	\N	494.00	t	t	10	2026-07-11 10:12:12	2026-07-11 10:12:12
2259	66	contra	migracion	INVERSIONES & TRANSPORTES	\N	\N	55028.90	t	t	11	2026-07-11 10:12:12	2026-07-11 10:12:12
2260	66	contra	migracion	SALDO DE CEMENTO HOLCIM	\N	\N	290.00	t	t	12	2026-07-11 10:12:12	2026-07-11 10:12:12
2261	66	contra	migracion	LUIS QUEVEDO	\N	\N	855.00	t	t	13	2026-07-11 10:12:12	2026-07-11 10:12:12
2262	67	favor	efectivo	Efectivo	cuenta	286	23903.49	f	f	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2263	67	favor	cuenta_bancaria	Cuenta BBVA Soles	cuenta	288	18064.07	f	f	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2264	67	favor	cuenta_bancaria	Cuenta BCP Dólares	cuenta	290	0.00	f	f	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2265	67	favor	cuenta_bancaria	Cuenta BCP Soles	cuenta	287	77544.29	f	f	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2266	67	favor	cuenta_bancaria	Yape	cuenta	289	0.00	f	f	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2267	67	favor	stock	Stock (inventario valorizado)	\N	\N	244461.00	f	f	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2268	67	favor	cxc	Deudas por cobrar (ventas a crédito)	\N	\N	83015.38	f	f	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2269	67	favor	planilla_descuento	Por descontar en planilla (faltantes y cargos)	\N	\N	0.00	f	f	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2270	67	contra	cxp	Proveedores por pagar	\N	\N	136280.98	f	f	1	2026-07-11 10:12:12	2026-07-11 10:12:12
2271	67	contra	anticipo_cliente	Clientes anticipos (a precio del día)	\N	\N	52303.24	f	f	2	2026-07-11 10:12:12	2026-07-11 10:12:12
2272	67	contra	deuda	DEUDA BCP 1 - 7630	deuda	66	6173.81	f	f	3	2026-07-11 10:12:12	2026-07-11 10:12:12
2273	67	contra	deuda	DEUDA BCP 2 - 5557	deuda	67	32546.43	f	f	4	2026-07-11 10:12:12	2026-07-11 10:12:12
2274	67	contra	deuda	11 CUBOS AFIRMADO - FERROCONSTRUCTORA	deuda	73	290.00	f	f	5	2026-07-11 10:12:12	2026-07-11 10:12:12
2275	67	contra	deuda	INVERSIONES & TRANSPORTES	deuda	70	55028.90	f	f	6	2026-07-11 10:12:12	2026-07-11 10:12:12
2276	67	contra	deuda	MILAGROS	deuda	69	494.00	f	f	7	2026-07-11 10:12:12	2026-07-11 10:12:12
2277	67	contra	deuda	PALANA	deuda	72	20.00	f	f	8	2026-07-11 10:12:12	2026-07-11 10:12:12
2278	67	contra	deuda	SALDO DE CEMENTO HOLCIM	deuda	71	290.00	f	f	9	2026-07-11 10:12:12	2026-07-11 10:12:12
2279	67	contra	personal	PERSONAL (sueldos pendientes)	deuda	68	2500.00	f	f	10	2026-07-11 10:12:12	2026-07-11 10:12:12
\.


--
-- Data for Name: balances_diarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.balances_diarios (id, empresa_id, user_id, fecha, estado, total_favor, total_contra, balance_neto, balance_anterior, diferencia, gastos_dia, utilidad_real, observacion, created_at, updated_at) FROM stdin;
12	1	1	2026-07-04	confirmado	433198.96	235196.44	198002.52	\N	\N	750.70	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
13	1	1	2026-07-05	borrador	441639.26	260045.24	181594.02	198002.52	-16408.50	608.80	-15799.70	\N	2026-07-05 19:27:37	2026-07-05 21:01:46
14	1	1	2026-07-06	borrador	440784.78	260045.24	180739.54	198002.52	-17262.98	0.00	-17262.98	\N	2026-07-06 08:52:06	2026-07-09 20:11:58
59	1097	1295	2026-07-01	confirmado	380461.54	219150.75	161310.79	164747.27	-3436.48	1848.70	-1587.78	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
60	1097	1295	2026-07-02	confirmado	496236.13	338342.15	157893.98	161310.79	-3416.81	32.00	-3384.81	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
61	1097	1295	2026-07-03	confirmado	355593.18	196164.79	159428.39	157893.98	1534.41	332.50	1866.91	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
62	1097	1295	2026-07-04	confirmado	349263.46	189997.79	159265.67	159428.39	-162.72	30.00	-132.72	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
63	1097	1295	2026-07-06	confirmado	364189.63	204970.99	159218.64	159265.67	-47.03	488.50	441.47	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
64	1097	1295	2026-07-07	confirmado	346094.21	187841.08	158253.13	159218.64	-965.51	109.90	-855.61	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
65	1097	1295	2026-07-08	confirmado	431393.96	272112.98	159280.98	158253.13	1027.85	350.00	1377.85	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
66	1097	1295	2026-07-09	confirmado	430259.37	270220.98	160038.39	159280.98	757.41	265.90	1023.31	Snapshot migrado del Excel (montos, sin detalle)	2026-07-11 10:12:12	2026-07-11 10:12:12
67	1097	1295	2026-07-10	confirmado	446988.23	285927.36	161060.87	160038.39	1022.48	431.66	1454.14	\N	2026-07-11 10:12:12	2026-07-11 10:12:12
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
1100	1097	1092	Caja Principal	t	50.00	f	t	2026-07-11 10:12:11	2026-07-11 10:12:11
1101	1097	1092	Caja 2 — Mostrador	f	0.00	f	t	2026-07-11 10:12:11	2026-07-11 10:12:11
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
1104	1097	Ferretería	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12
322	1	Materiales de construcción	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37
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
175	1097	1635	1295	\N	\N	2026-04-24	397.13	397.13	monto	\N	\N	\N	activo	Pendiente por entregar P00100041821 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
176	1097	1636	1295	\N	\N	2026-04-29	40.12	40.12	monto	\N	\N	\N	activo	Pendiente por entregar P00100041961 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
177	1097	1637	1295	\N	\N	2026-07-10	19.06	19.06	monto	\N	\N	\N	activo	Pendiente por entregar P00100043820 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
8	1	337	1	\N	14	2026-07-02	26250.00	26250.00	material	305	25000.0000	25000.0000	activo	Ladrillo KK pagado por adelantado, entrega por obra	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
9	1	341	1	\N	16	2026-07-03	6500.00	6500.00	monto	\N	\N	\N	activo	A cuenta de materiales para su casa	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
10	1	342	2	\N	16	2026-07-05	1200.00	1200.00	monto	\N	\N	\N	activo	A cuenta de pedido de calaminas	2026-07-05 19:27:37	2026-07-05 19:27:37	PEN	\N	\N
11	1	342	1	5	14	2026-07-05	2000.00	0.00	material	308	200.0000	100.0000	activo	\N	2026-07-05 20:22:35	2026-07-05 20:22:56	PEN	\N	\N
178	1097	1588	1295	\N	\N	2026-07-03	9777.63	9777.63	monto	\N	\N	\N	activo	Pendiente por entregar P00100043645 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
179	1097	1638	1295	\N	\N	2026-05-19	1395.21	1395.21	monto	\N	\N	\N	activo	Pendiente por entregar P00200031999 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
180	1097	1639	1295	\N	\N	2026-01-27	264.76	264.76	monto	\N	\N	\N	activo	Pendiente por entregar P00200029187 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
181	1097	1640	1295	\N	\N	2025-01-23	33.10	33.10	monto	\N	\N	\N	activo	Pendiente por entregar F00100001944 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
182	1097	1641	1295	\N	\N	2023-03-29	81.24	81.24	monto	\N	\N	\N	activo	Pendiente por entregar B00100001260 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
183	1097	1642	1295	\N	\N	2023-08-29	486.21	486.21	monto	\N	\N	\N	activo	Pendiente por entregar P00100014270 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
184	1097	1614	1295	\N	\N	2025-12-06	333.02	333.02	monto	\N	\N	\N	activo	Pendiente por entregar P00100036692 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
185	1097	1614	1295	\N	\N	2025-12-06	333.02	333.02	monto	\N	\N	\N	activo	Pendiente por entregar P00100036693 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
186	1097	1643	1295	\N	\N	2024-06-10	64.83	64.83	monto	\N	\N	\N	activo	Pendiente por entregar P00100020876 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
187	1097	1644	1295	\N	\N	2025-12-30	6.11	6.11	monto	\N	\N	\N	activo	Pendiente por entregar P00200028299 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
188	1097	1645	1295	\N	\N	2024-01-03	12824.98	12824.98	monto	\N	\N	\N	activo	Pendiente por entregar P00200012046 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
189	1097	1646	1295	\N	\N	2026-07-08	19420.00	19420.00	monto	\N	\N	\N	activo	Pendiente por entregar P00100043762 (valorizado precio real, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
190	1097	1647	1295	\N	\N	2025-12-01	884.75	884.75	monto	\N	\N	\N	activo	Pendiente por entregar P00100036495 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
191	1097	1647	1295	\N	\N	2026-01-10	920.59	920.59	monto	\N	\N	\N	activo	Pendiente por entregar P00100038090 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
192	1097	1647	1295	\N	\N	2026-07-04	582.78	582.78	monto	\N	\N	\N	activo	Pendiente por entregar P00100043687 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
193	1097	1648	1295	\N	\N	2026-02-23	225.44	225.44	monto	\N	\N	\N	activo	Pendiente por entregar P00200030094 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
194	1097	1649	1295	\N	\N	2026-02-19	120.36	120.36	monto	\N	\N	\N	activo	Pendiente por entregar P00200029931 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
195	1097	1650	1295	\N	\N	2024-01-18	64.83	64.83	monto	\N	\N	\N	activo	Pendiente por entregar P00100017909 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
196	1097	1651	1295	\N	\N	2023-05-02	33.10	33.10	monto	\N	\N	\N	activo	Pendiente por entregar P00200008967 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
197	1097	1652	1295	\N	\N	2025-12-17	304.52	304.52	monto	\N	\N	\N	activo	Pendiente por entregar P00100037178 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
198	1097	1653	1295	\N	\N	2026-04-17	1534.33	1534.33	monto	\N	\N	\N	activo	Pendiente por entregar P00200031344 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
199	1097	1654	1295	\N	\N	2026-03-07	2156.12	2156.12	monto	\N	\N	\N	activo	Pendiente por entregar P00200030422 (valorizado costo+IGV escalado, migración)	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
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
1582	1097	DNI	99999999	Cliente General		\N	\N	\N	\N	\N	t	2026-07-11 10:12:11	2026-07-11 10:12:11	t
1583	1097	DNI	\N	ALEJANDRO PAREDES		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1584	1097	DNI	\N	ANGEL MORENO-TRABAJADOR		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1585	1097	DNI	\N	AZAÐERO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1586	1097	DNI	\N	BAUTISTA CARRASCO ELMER - 996813790		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1587	1097	DNI	\N	BECERRA ALCALDE ADELMO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
337	1	RUC	20487965123	CONSTRUCTORA CHICLAYO SAC		CONSTRUCTORA CHICLAYO SAC	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
338	1	RUC	20539871456	CONSTRUCTORA NORTE SAC		CONSTRUCTORA NORTE SAC	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
339	1	DNI	16745823	Eladio	Vásquez Cieza	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
340	1	DNI	17458963	Manuel	Effio Puican	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
341	1	DNI	16987452	Rosa	Paredes Llontop	\N	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
342	1	RUC	20601234789	COMERCIAL SANTA ROSA EIRL		COMERCIAL SANTA ROSA EIRL	\N	\N	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f
1588	1097	DNI	\N	BUSTAMANTE GONZALES RIGOBERTO - 967795790		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1589	1097	DNI	\N	CABANILLAS ZAMORA LENIN MICHEL - 918473100		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1590	1097	DNI	\N	CATALINO SANCHEZ		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1591	1097	RUC	20505958111	CJ TELECOM SAC		CJ TELECOM SAC	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1592	1097	DNI	\N	CLIDER - MIRAFLORES		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1593	1097	DNI	\N	COFESEG		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1594	1097	RUC	20614348608	CONSORCIO B&B		CONSORCIO B&B	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1595	1097	DNI	\N	DARIO OCHOA - 930938708		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1596	1097	RUC	10166854038	DIAZ BARCO MAGDALENA		DIAZ BARCO MAGDALENA	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1597	1097	DNI	\N	EDINSON BARBOZA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1598	1097	DNI	\N	EDINSON VASQUEZ- ROCA FUERTE		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1599	1097	DNI	\N	ELMER BUSTAMANTE - JLO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1600	1097	DNI	\N	FERNANDEZ SANCHEZ LUZ ANGELICA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1601	1097	DNI	\N	FERRETERIA ALPES - TUMAN		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1602	1097	RUC	20604078238	FERROCONSTRUCTORA JH SERVICIOS GENERALES E.I.R.L.		FERROCONSTRUCTORA JH SERVICIOS GENERALES E.I.R.L.	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1603	1097	DNI	\N	FRANCISCO BECERRA TORRES		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1604	1097	DNI	\N	GONZALES NUÐEZ EDILBERTO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1605	1097	DNI	\N	GUSTAVO GUEVARA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1606	1097	DNI	\N	HECTOR MEJIA CEL. 930234446		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1607	1097	DNI	\N	HERRERA SALAZAR JORDIN		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1608	1097	DNI	\N	HUERTAS		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1609	1097	DNI	\N	ING RIMARACHIN		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1610	1097	DNI	\N	JEINER HERRERA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1611	1097	DNI	\N	JEINER HERRERA - FERRETERIA LA UNION		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1612	1097	DNI	\N	JHON SANCHEZ CEL. 915950023		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1613	1097	DNI	\N	JHONY- LA CRIA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1614	1097	DNI	\N	JIBAJA NEYRA KEVIN OVET - 999336049		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1615	1097	DNI	\N	JOSE MORE		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1616	1097	DNI	\N	JUAN SALAZAR SALAZAR		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1617	1097	DNI	\N	JUDITH OBLITAS		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1618	1097	DNI	\N	KAREN AQUINO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1619	1097	DNI	\N	MAESTRO BLANCO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1620	1097	DNI	\N	MALDONADO CORDOVA LUIS ALBERTO- 990073177		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1621	1097	DNI	\N	MARIA GLADYS PEREZ VASQUEZ		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1622	1097	DNI	\N	MENDOZA MONDRAGON FLOR ESTELITA - 995379798		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1623	1097	DNI	\N	MIGUEL CAMPOS		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1624	1097	DNI	\N	MILIAN SALAZAR LUIS ALBERTO -916666706		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1625	1097	DNI	\N	MORENO VALDIVIESO SUGEY DEL ROSARIO - 977761809		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1626	1097	DNI	\N	ORLANDO VASQUEZ		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1627	1097	DNI	\N	PROGRESO-PATAPO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1628	1097	DNI	\N	QUISPE		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1629	1097	RUC	20602126120	QURMAQ SOCIEDAD ANONIMA CERRADA		QURMAQ SOCIEDAD ANONIMA CERRADA	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1630	1097	DNI	\N	SEÐOR LUCAS HERRERA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1631	1097	DNI	\N	SONAPO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1632	1097	DNI	\N	TILO - 978080316		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1633	1097	RUC	20561194697	TRANSPORTES MARIA ANTONIETA S.A.C.		TRANSPORTES MARIA ANTONIETA S.A.C.	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1634	1097	DNI	\N	VILCHEZ TARRILLO HERIBERTO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1635	1097	DNI	\N	AUTO FACIL EN CUOTAS, ISABEL E.I.R.L.		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1636	1097	DNI	\N	BUENAÑO TAPIA MERY EMILIN - 980461765		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1637	1097	DNI	\N	BURGA MALDONADO ALBERTO ANANIAS - 971648368		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1638	1097	DNI	\N	CAMISAN AQUINO ESTHER - 988092730		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1639	1097	DNI	\N	CARHUAJULCA IRURETA LUZ ANGELICA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1640	1097	DNI	\N	DISTRIBUIDORA DE PRODUCTOS DE CONSUMO MASIVO SOCIEDAD ANONIMA CERRADA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1641	1097	DNI	\N	HERNANDEZ LLACSAHUANGA MARIO CESAR		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1642	1097	DNI	\N	HERNANDEZ MORALES HERMINIO		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1643	1097	DNI	\N	JUAN CLIENTE		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1644	1097	DNI	\N	LACHE HERNANDEZ ORFELINDA ELIZABETH		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1645	1097	DNI	\N	LUIS MEDINA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1646	1097	DNI	\N	MORETO ALTAMIRANO ZARELA NOEMI - 992750519		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1647	1097	DNI	\N	PEREZ DIAZ MIGUEL - 937744347		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1648	1097	DNI	\N	PEREZ PEREZ JUAN CARLOS		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1649	1097	DNI	\N	PESANTES CORTIJO SILVIA MARTINA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1650	1097	DNI	\N	S.R OMAR		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1651	1097	DNI	\N	TARRILLO VASQUEZ CLARA ESTHER		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1652	1097	DNI	\N	TORRES RUIZ LUZ ANGELICA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1653	1097	DNI	\N	WALTER - CASA BLANCA		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
1654	1097	DNI	\N	YDROGO GALVEZ YONATAN		\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f
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
38	289	5458
39	287	5458
40	288	5457
41	288	5460
\.


--
-- Data for Name: cuenta_movimientos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuenta_movimientos (id, empresa_id, cuenta_id, user_id, fecha, tipo, monto, descripcion, ref_tipo, ref_id, created_at, updated_at, moneda, tipo_cambio, monto_moneda) FROM stdin;
334	1	17	1	2026-07-09	ingreso	190.95	Venta V-0001 — Plin	venta	820	2026-07-09 19:56:19	2026-07-09 19:56:19	PEN	\N	\N
335	1	1	1	2026-07-09	ingreso	8.00	Venta V-0001 — Efectivo	venta	820	2026-07-09 19:56:19	2026-07-09 19:56:19	PEN	\N	\N
337	1	15	1	2026-07-09	ingreso	700.00	Abono venta V-0503 — Eladio Vásquez Cieza	venta_abono	10	2026-07-09 19:58:41	2026-07-09 19:58:41	PEN	\N	\N
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
456	1097	287	1295	2026-07-10	ingreso	77544.29	Ajuste de saldo: Saldo inicial — migración del sistema anterior (Excel 10-07)	ajuste	\N	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
112	1	4	1	2026-07-05	egreso	8000.00	Pago a proveedor COFESA SAC	entrada_pago	17	2026-07-05 19:49:21	2026-07-05 19:49:21	PEN	\N	\N
113	1	14	1	2026-07-05	ingreso	100.00	Venta V-0001 — Transferencia	venta	170	2026-07-05 20:17:50	2026-07-05 20:17:50	PEN	\N	\N
114	1	15	1	2026-07-05	ingreso	168.60	Abono venta V-0001 — Andrea Torres Mendoza	venta_abono	9	2026-07-05 20:19:19	2026-07-05 20:19:19	PEN	\N	\N
115	1	14	1	2026-07-05	ingreso	2000.00	Anticipo de cliente — COMERCIAL SANTA ROSA EIRL	cliente_anticipo	11	2026-07-05 20:22:35	2026-07-05 20:22:35	PEN	\N	\N
116	1	1	1	2026-07-05	egreso	50.00	Faltante consolidado (Efectivo) — turno #213 (cajera: Jesús)	turno_consolidacion	6	2026-07-05 20:32:16	2026-07-05 20:32:16	PEN	\N	\N
457	1097	288	1295	2026-07-10	ingreso	18064.07	Ajuste de saldo: Saldo inicial — migración del sistema anterior (Excel 10-07)	ajuste	\N	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
458	1097	286	1295	2026-07-10	ingreso	23903.49	Ajuste de saldo: Saldo inicial — migración del sistema anterior (Excel 10-07)	ajuste	\N	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
\.


--
-- Data for Name: cuentas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas (id, empresa_id, nombre, numero_cuenta, banco, cci, titular, activo, created_at, updated_at, es_efectivo, moneda) FROM stdin;
1	1	Efectivo	\N	\N	\N	\N	t	2026-07-05 14:26:12	2026-07-05 14:26:12	t	PEN
4	1	Tarjeta	\N	\N	\N	\N	t	2026-07-05 19:47:03	2026-07-05 19:47:03	f	PEN
286	1097	Efectivo	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	PEN
287	1097	Cuenta BCP Soles	305-2279107-0-89	BCP	002-305-002279107089-13	H&C FERROMATERIALES S.R.L	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	PEN
288	1097	Cuenta BBVA Soles	0011-0285-02-01958513	BBVA	\N	FERROMATERIALES H&C S.R.L	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	PEN
289	1097	Yape	\N	BCP	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	PEN
14	1	Cuenta BCP Soles	305-2214578-0-11	BCP	\N	HYC Ferromateriales SRL	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f	PEN
15	1	Cuenta BBVA Soles	0011-0249-0100045678	BBVA	\N	HYC Ferromateriales SRL	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f	PEN
16	1	Yape	\N	BCP	\N	\N	t	2026-07-05 19:27:37	2026-07-05 19:27:37	f	PEN
17	1	Plin	\N	\N	\N	\N	t	2026-07-05 20:32:16	2026-07-05 20:32:16	f	PEN
290	1097	Cuenta BCP Dólares	\N	BCP	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	USD
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
66	1097	1295	por_pagar	bancaria	DEUDA BCP 1 - 7630	6173.81	6173.81	2026-07-10	\N	activa	Préstamo bancario — saldo al implementar el sistema	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
67	1097	1295	por_pagar	bancaria	DEUDA BCP 2 - 5557	32546.43	32546.43	2026-07-10	\N	activa	Préstamo bancario — saldo al implementar el sistema	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
68	1097	1295	por_pagar	trabajador	PERSONAL (sueldos pendientes)	2500.00	2500.00	2026-07-10	\N	activa	Sueldos por pagar	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
69	1097	1295	por_pagar	personal	MILAGROS	494.00	494.00	2026-07-10	\N	activa	Deuda personal	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
70	1097	1295	por_pagar	personal	INVERSIONES & TRANSPORTES	55028.90	55028.90	2026-07-10	\N	activa	Deuda a proveedor de transporte	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
71	1097	1295	por_pagar	personal	SALDO DE CEMENTO HOLCIM	290.00	290.00	2026-07-10	\N	activa	Saldo de cemento	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
72	1097	1295	por_pagar	personal	PALANA	20.00	20.00	2026-07-10	\N	activa	Deuda menor	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
73	1097	1295	por_pagar	personal	11 CUBOS AFIRMADO - FERROCONSTRUCTORA	290.00	290.00	2026-07-10	\N	activa	Saldo de agregados	2026-07-11 10:12:12	2026-07-11 10:12:12	PEN	\N	\N
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

COPY public.empresas (id, razon_social, nombre_comercial, ruc, direccion, telefono, email, logo, activo, created_at, updated_at, modo_almacen, descuenta_stock_en_venta, modo_cierre_caja, usa_fondos_iniciales, fondos_iniciales_en_declaracion, modo_cierre_inventario, permite_devoluciones, dias_max_devolucion, requiere_aprobacion_devolucion, restock_default, usa_agenda, agenda_sujeto_label, agenda_sujeto_requerido, tasa_igv, requiere_consolidacion_caja, permite_stock_negativo) FROM stdin;
1	MacSoft E.I.R.L.	MacSoft Importaciones	20612345678	Av. Balta 850, Chiclayo, Lambayeque	+51 974 123 456	macsoft@gmail.com	\N	t	2026-05-18 01:53:39	2026-07-05 15:08:33	simple	t	con_declaraciones	t	t	por_venta	t	15	f	t	t	\N	f	18.00	t	f
1097	HYC FERROMATERIALES SRL	Ferretería H&C	20600134648	Chiclayo, Lambayeque	\N	\N	\N	t	2026-07-11 10:12:11	2026-07-11 10:12:11	simple	t	con_declaraciones	t	f	por_venta	t	15	f	t	f	\N	f	18.00	f	f
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
74	1097	1110	1295	MIG-CORPORACION HERRERA 	CORPORACION HERRERA S.A.C.	compra	2026-07-10	confirmado	Saldo migrado del sistema anterior	347.60	2026-07-11 10:12:12	2026-07-11 10:12:12	50	pendiente	\N	\N	0.00	PEN	\N	\N
75	1097	1110	1295	MIG-DEPOSITO PAKATNAMU S	DEPOSITO PAKATNAMU S.A.C	compra	2026-07-06	confirmado	Saldo migrado del sistema anterior	51040.00	2026-07-11 10:12:12	2026-07-11 10:12:12	51	pendiente	\N	\N	0.00	PEN	\N	\N
76	1097	1110	1295	MIG-FERRONOR SAC.	FERRONOR SAC.	compra	2026-06-10	confirmado	Saldo migrado del sistema anterior	18767.00	2026-07-11 10:12:12	2026-07-11 10:12:12	52	pendiente	\N	\N	0.00	PEN	\N	\N
77	1097	1110	1295	MIG-LADRILLERA RAMOS	LADRILLERA RAMOS	compra	2026-04-25	confirmado	Saldo migrado del sistema anterior	1900.00	2026-07-11 10:12:12	2026-07-11 10:12:12	53	pendiente	\N	\N	0.00	PEN	\N	\N
78	1097	1110	1295	MIG-SERVICIOS GENERALES 	SERVICIOS GENERALES ADJ EIRL	compra	2026-07-06	confirmado	Saldo migrado del sistema anterior	64226.38	2026-07-11 10:12:12	2026-07-11 10:12:12	54	pendiente	\N	\N	0.00	PEN	\N	\N
15	1	1	1	F002-1120	ARDILES IMPORT SRL	compra	2026-07-02	confirmado	\N	32400.00	2026-07-05 19:27:37	2026-07-05 19:27:37	11	pendiente	\N	\N	0.00	PEN	\N	\N
16	1	1	1	F003-5541	COFESA SAC	compra	2026-07-03	confirmado	\N	26300.00	2026-07-05 19:27:37	2026-07-05 19:27:37	12	parcial	\N	\N	5000.00	PEN	\N	\N
17	1	1	1	F004-0089	UYUSTOOLS PERU SAC	compra	2026-07-03	confirmado	\N	14700.00	2026-07-05 19:27:37	2026-07-05 19:27:37	13	pendiente	\N	\N	0.00	PEN	\N	\N
14	1	1	1	F001-8934	FERRONOR EIRL	compra	2026-07-02	confirmado	\N	48500.00	2026-07-05 19:27:37	2026-07-05 19:27:37	10	parcial	\N	\N	22000.00	PEN	\N	\N
20	1	1	1	\N	COFESA SAC	compra	2026-07-05	confirmado	\N	20000.00	2026-07-05 19:49:21	2026-07-05 19:49:21	12	parcial	2	4	8000.00	PEN	\N	\N
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
78	1097	43	Gasto operativo	t	2026-07-11 10:12:12	2026-07-11 10:12:12
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
43	1097	Operativo	operativo	t	2026-07-11 10:12:12	2026-07-11 10:12:12
\.


--
-- Data for Name: gastos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.gastos (id, empresa_id, local_id, user_id, turno_id, gasto_tipo_id, gasto_concepto_id, monto, fecha, comentario, created_at, updated_at, cuenta_id, moneda, tipo_cambio, monto_moneda) FROM stdin;
43	1	1	1	475	26	57	90.00	2026-07-09	\N	2026-07-09 19:57:13	2026-07-09 19:57:13	15	PEN	\N	\N
44	1	1	1	212	27	59	330.00	2026-07-09	\N	2026-07-09 20:01:42	2026-07-09 20:01:42	15	PEN	\N	\N
53	1097	1092	1295	\N	43	78	370.00	2026-07-10	COMBUSTIBLE FUSO	2026-07-11 10:12:12	2026-07-11 10:12:12	286	PEN	\N	\N
54	1097	1092	1295	\N	43	78	61.66	2026-07-10	PAGO LINEA DE CELULAR	2026-07-11 10:12:12	2026-07-11 10:12:12	286	PEN	\N	\N
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
1092	1097	Tienda Principal	Chiclayo	\N	t	t	2026-07-11 10:12:11	2026-07-11 10:12:11	\N	\N	\N	\N	\N	\N	\N	\N	\N
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
5456	1097	Efectivo	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	1
5457	1097	Tarjeta	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	2
5458	1097	Yape	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	5
5459	1097	Plin	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	6
5460	1097	Transferencia	t	2026-07-11 10:12:12	2026-07-11 10:12:12	f	4
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
88	1169	28	t	t	f	f	\N	\N
89	1169	29	t	t	f	f	\N	\N
90	1169	22	t	t	t	f	\N	\N
91	1169	18	t	t	t	f	\N	\N
92	1169	37	t	t	f	f	\N	\N
93	1169	24	t	t	f	f	\N	\N
94	1169	12	t	f	f	f	\N	\N
95	1169	14	t	f	f	f	\N	\N
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
6520	6520	1092	t	1.0000	fijo	0.26	0.26	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6521	6521	1092	t	1.0000	fijo	10.80	10.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6522	6522	1092	t	1.0000	fijo	14.55	14.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6523	6523	1092	t	1.0000	fijo	3.98	3.98	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6524	6524	1092	t	1.0000	fijo	6.77	6.77	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6525	6525	1092	t	1.0000	fijo	17.50	17.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6526	6526	1092	t	1.0000	fijo	3.37	3.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6527	6527	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6528	6528	1092	t	1.0000	fijo	2.34	2.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6529	6529	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6530	6530	1092	t	1.0000	fijo	2.02	2.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6531	6531	1092	t	1.0000	fijo	43.92	43.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
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
6532	6532	1092	t	1.0000	fijo	0.12	0.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6533	6533	1092	t	1.0000	fijo	11.00	11.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6534	6534	1092	t	1.0000	fijo	7.58	7.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6535	6535	1092	t	1.0000	fijo	37.51	37.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6536	6536	1092	t	1.0000	fijo	0.20	0.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6537	6537	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6538	6538	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6539	6539	1092	t	1.0000	fijo	1.10	1.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6540	6540	1092	t	1.0000	fijo	0.86	0.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6541	6541	1092	t	1.0000	fijo	0.48	0.48	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6542	6542	1092	t	1.0000	fijo	1.65	1.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6543	6543	1092	t	1.0000	fijo	0.55	0.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6544	6544	1092	t	1.0000	fijo	27.00	27.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6545	6545	1092	t	1.0000	fijo	7.30	7.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6546	6546	1092	t	1.0000	fijo	3.04	3.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6547	6547	1092	t	1.0000	fijo	3.04	3.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6548	6548	1092	t	1.0000	fijo	31.40	31.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6549	6549	1092	t	1.0000	fijo	1.10	1.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6550	6550	1092	t	1.0000	fijo	8.50	8.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6551	6551	1092	t	1.0000	fijo	7.78	7.78	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6552	6552	1092	t	1.0000	fijo	6.76	6.76	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6553	6553	1092	t	1.0000	fijo	9.40	9.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6554	6554	1092	t	1.0000	fijo	2.25	2.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6555	6555	1092	t	1.0000	fijo	4.07	4.07	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6556	6556	1092	t	1.0000	fijo	3.78	3.78	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6557	6557	1092	t	1.0000	fijo	5.72	5.72	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6558	6558	1092	t	1.0000	fijo	38.00	38.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6559	6559	1092	t	1.0000	fijo	0.35	0.35	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6560	6560	1092	t	1.0000	fijo	20.00	20.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6561	6561	1092	t	1.0000	fijo	0.20	0.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6562	6562	1092	t	1.0000	fijo	0.17	0.17	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6563	6563	1092	t	1.0000	fijo	4.81	4.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6564	6564	1092	t	1.0000	fijo	2.76	2.76	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6565	6565	1092	t	1.0000	fijo	2.57	2.57	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6566	6566	1092	t	1.0000	fijo	0.76	0.76	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6567	6567	1092	t	1.0000	fijo	3.50	3.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6568	6568	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6569	6569	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6570	6570	1092	t	1.0000	fijo	1.20	1.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6571	6571	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6572	6572	1092	t	1.0000	fijo	2.40	2.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6573	6573	1092	t	1.0000	fijo	12.50	12.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6574	6574	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6575	6575	1092	t	1.0000	fijo	0.79	0.79	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6576	6576	1092	t	1.0000	fijo	1.42	1.42	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6577	6577	1092	t	1.0000	fijo	1.16	1.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6578	6578	1092	t	1.0000	fijo	1.99	1.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6579	6579	1092	t	1.0000	fijo	4.41	4.41	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6580	6580	1092	t	1.0000	fijo	5.81	5.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6581	6581	1092	t	1.0000	fijo	2.60	2.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6582	6582	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6583	6583	1092	t	1.0000	fijo	2.30	2.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6584	6584	1092	t	1.0000	fijo	3.25	3.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6585	6585	1092	t	1.0000	fijo	3.16	3.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6586	6586	1092	t	1.0000	fijo	1.22	1.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6587	6587	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6588	6588	1092	t	1.0000	fijo	9.59	9.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6589	6589	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6590	6590	1092	t	1.0000	fijo	2.03	2.03	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6591	6591	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6592	6592	1092	t	1.0000	fijo	4.51	4.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6593	6593	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6594	6594	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6595	6595	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6596	6596	1092	t	1.0000	fijo	0.54	0.54	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6597	6597	1092	t	1.0000	fijo	1.98	1.98	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6598	6598	1092	t	1.0000	fijo	1.57	1.57	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6599	6599	1092	t	1.0000	fijo	2.62	2.62	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6600	6600	1092	t	1.0000	fijo	0.78	0.78	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6601	6601	1092	t	1.0000	fijo	3.47	3.47	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6602	6602	1092	t	1.0000	fijo	4.44	4.44	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6603	6603	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6604	6604	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6605	6605	1092	t	1.0000	fijo	0.73	0.73	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6606	6606	1092	t	1.0000	fijo	0.60	0.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6607	6607	1092	t	1.0000	fijo	17.12	17.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6608	6608	1092	t	1.0000	fijo	2.17	2.17	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6609	6609	1092	t	1.0000	fijo	1.69	1.69	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6610	6610	1092	t	1.0000	fijo	20.21	20.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6611	6611	1092	t	1.0000	fijo	8.96	8.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6612	6612	1092	t	1.0000	fijo	9.00	9.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6613	6613	1092	t	1.0000	fijo	9.17	9.17	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6614	6614	1092	t	1.0000	fijo	8.50	8.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6615	6615	1092	t	1.0000	fijo	2.43	2.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6616	6616	1092	t	1.0000	fijo	7.00	7.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6617	6617	1092	t	1.0000	fijo	12.81	12.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6618	6618	1092	t	1.0000	fijo	20.66	20.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6619	6619	1092	t	1.0000	fijo	1.14	1.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6620	6620	1092	t	1.0000	fijo	11.81	11.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6621	6621	1092	t	1.0000	fijo	100.01	100.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6622	6622	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6623	6623	1092	t	1.0000	fijo	30.80	30.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6624	6624	1092	t	1.0000	fijo	1.85	1.85	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6625	6625	1092	t	1.0000	fijo	30.60	30.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6626	6626	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6627	6627	1092	t	1.0000	fijo	9.50	9.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6628	6628	1092	t	1.0000	fijo	1.40	1.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6629	6629	1092	t	1.0000	fijo	1.75	1.75	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6630	6630	1092	t	1.0000	fijo	3.13	3.13	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6631	6631	1092	t	1.0000	fijo	10.93	10.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6632	6632	1092	t	1.0000	fijo	3.53	3.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6633	6633	1092	t	1.0000	fijo	1.63	1.63	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6634	6634	1092	t	1.0000	fijo	4.31	4.31	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6635	6635	1092	t	1.0000	fijo	4.31	4.31	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6636	6636	1092	t	1.0000	fijo	6.18	6.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6637	6637	1092	t	1.0000	fijo	1.83	1.83	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6638	6638	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6639	6639	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6640	6640	1092	t	1.0000	fijo	0.07	0.07	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6641	6641	1092	t	1.0000	fijo	0.04	0.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6642	6642	1092	t	1.0000	fijo	0.09	0.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6643	6643	1092	t	1.0000	fijo	4.40	4.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6644	6644	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6645	6645	1092	t	1.0000	fijo	4.92	4.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6646	6646	1092	t	1.0000	fijo	1.79	1.79	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6647	6647	1092	t	1.0000	fijo	0.74	0.74	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6648	6648	1092	t	1.0000	fijo	0.64	0.64	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6649	6649	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6650	6650	1092	t	1.0000	fijo	6.84	6.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6651	6651	1092	t	1.0000	fijo	3.56	3.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6652	6652	1092	t	1.0000	fijo	6.25	6.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6653	6653	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6654	6654	1092	t	1.0000	fijo	32.83	32.83	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6655	6655	1092	t	1.0000	fijo	5.66	5.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6656	6656	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6657	6657	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6658	6658	1092	t	1.0000	fijo	0.50	0.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6659	6659	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6660	6660	1092	t	1.0000	fijo	8.58	8.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6661	6661	1092	t	1.0000	fijo	2.12	2.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6662	6662	1092	t	1.0000	fijo	1.23	1.23	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6663	6663	1092	t	1.0000	fijo	2.25	2.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6664	6664	1092	t	1.0000	fijo	4.43	4.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6665	6665	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6666	6666	1092	t	1.0000	fijo	10.40	10.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6667	6667	1092	t	1.0000	fijo	11.75	11.75	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6668	6668	1092	t	1.0000	fijo	15.51	15.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6669	6669	1092	t	1.0000	fijo	13.00	13.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6670	6670	1092	t	1.0000	fijo	4.01	4.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6671	6671	1092	t	1.0000	fijo	6.50	6.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6672	6672	1092	t	1.0000	fijo	13.99	13.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6673	6673	1092	t	1.0000	fijo	5.70	5.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6674	6674	1092	t	1.0000	fijo	18.01	18.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6675	6675	1092	t	1.0000	fijo	0.38	0.38	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6676	6676	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6677	6677	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6678	6678	1092	t	1.0000	fijo	0.33	0.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6679	6679	1092	t	1.0000	fijo	1.45	1.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6680	6680	1092	t	1.0000	fijo	7.00	7.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6681	6681	1092	t	1.0000	fijo	83.00	83.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6682	6682	1092	t	1.0000	fijo	31.00	31.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6683	6683	1092	t	1.0000	fijo	17.50	17.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6684	6684	1092	t	1.0000	fijo	35.20	35.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6685	6685	1092	t	1.0000	fijo	7.50	7.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6686	6686	1092	t	1.0000	fijo	1.09	1.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6687	6687	1092	t	1.0000	fijo	2.28	2.28	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6688	6688	1092	t	1.0000	fijo	2.84	2.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6689	6689	1092	t	1.0000	fijo	3.96	3.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6690	6690	1092	t	1.0000	fijo	5.97	5.97	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6691	6691	1092	t	1.0000	fijo	0.09	0.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6692	6692	1092	t	1.0000	fijo	4.91	4.91	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6693	6693	1092	t	1.0000	fijo	4.44	4.44	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6694	6694	1092	t	1.0000	fijo	5.78	5.78	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6695	6695	1092	t	1.0000	fijo	6.94	6.94	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6696	6696	1092	t	1.0000	fijo	14.40	14.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6697	6697	1092	t	1.0000	fijo	8.97	8.97	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6698	6698	1092	t	1.0000	fijo	33.21	33.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6699	6699	1092	t	1.0000	fijo	2.04	2.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6700	6700	1092	t	1.0000	fijo	0.60	0.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6701	6701	1092	t	1.0000	fijo	65.01	65.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6702	6702	1092	t	1.0000	fijo	64.88	64.88	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6703	6703	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6704	6704	1092	t	1.0000	fijo	5.66	5.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6705	6705	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6706	6706	1092	t	1.0000	fijo	0.96	0.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6707	6707	1092	t	1.0000	fijo	2.99	2.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6708	6708	1092	t	1.0000	fijo	1.53	1.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6709	6709	1092	t	1.0000	fijo	5.76	5.76	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6710	6710	1092	t	1.0000	fijo	2.10	2.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6711	6711	1092	t	1.0000	fijo	17.50	17.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6712	6712	1092	t	1.0000	fijo	0.34	0.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6713	6713	1092	t	1.0000	fijo	0.74	0.74	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6714	6714	1092	t	1.0000	fijo	17.41	17.41	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6715	6715	1092	t	1.0000	fijo	22.04	22.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6716	6716	1092	t	1.0000	fijo	0.13	0.13	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6717	6717	1092	t	1.0000	fijo	0.22	0.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6718	6718	1092	t	1.0000	fijo	4.80	4.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6719	6719	1092	t	1.0000	fijo	3.43	3.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6720	6720	1092	t	1.0000	fijo	3.60	3.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6721	6721	1092	t	1.0000	fijo	3.67	3.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6722	6722	1092	t	1.0000	fijo	3.67	3.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6723	6723	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6724	6724	1092	t	1.0000	fijo	5.89	5.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6725	6725	1092	t	1.0000	fijo	5.61	5.61	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6726	6726	1092	t	1.0000	fijo	2.38	2.38	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6727	6727	1092	t	1.0000	fijo	0.85	0.85	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6728	6728	1092	t	1.0000	fijo	2.12	2.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6729	6729	1092	t	1.0000	fijo	2.90	2.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6730	6730	1092	t	1.0000	fijo	1.40	1.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6731	6731	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6732	6732	1092	t	1.0000	fijo	4.45	4.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6733	6733	1092	t	1.0000	fijo	2.24	2.24	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6734	6734	1092	t	1.0000	fijo	2.90	2.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6735	6735	1092	t	1.0000	fijo	0.50	0.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6736	6736	1092	t	1.0000	fijo	1.55	1.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6737	6737	1092	t	1.0000	fijo	1.40	1.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6738	6738	1092	t	1.0000	fijo	2.90	2.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6739	6739	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6740	6740	1092	t	1.0000	fijo	1.22	1.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6741	6741	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6742	6742	1092	t	1.0000	fijo	1.60	1.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6743	6743	1092	t	1.0000	fijo	1.56	1.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6744	6744	1092	t	1.0000	fijo	1.66	1.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6745	6745	1092	t	1.0000	fijo	0.86	0.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6746	6746	1092	t	1.0000	fijo	5.35	5.35	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6747	6747	1092	t	1.0000	fijo	3.17	3.17	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6748	6748	1092	t	1.0000	fijo	9.18	9.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6749	6749	1092	t	1.0000	fijo	5.30	5.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6750	6750	1092	t	1.0000	fijo	9.52	9.52	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6751	6751	1092	t	1.0000	fijo	4.84	4.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6752	6752	1092	t	1.0000	fijo	15.16	15.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6753	6753	1092	t	1.0000	fijo	7.00	7.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6754	6754	1092	t	1.0000	fijo	45.01	45.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6755	6755	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6756	6756	1092	t	1.0000	fijo	0.31	0.31	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6757	6757	1092	t	1.0000	fijo	0.19	0.19	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6758	6758	1092	t	1.0000	fijo	0.35	0.35	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6759	6759	1092	t	1.0000	fijo	0.34	0.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6760	6760	1092	t	1.0000	fijo	1.09	1.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6761	6761	1092	t	1.0000	fijo	3.30	3.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6762	6762	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6763	6763	1092	t	1.0000	fijo	3.10	3.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6764	6764	1092	t	1.0000	fijo	4.92	4.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6765	6765	1092	t	1.0000	fijo	6.47	6.47	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6766	6766	1092	t	1.0000	fijo	10.50	10.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6767	6767	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6768	6768	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6769	6769	1092	t	1.0000	fijo	14.50	14.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6770	6770	1092	t	1.0000	fijo	0.32	0.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6771	6771	1092	t	1.0000	fijo	0.11	0.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6772	6772	1092	t	1.0000	fijo	0.61	0.61	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6773	6773	1092	t	1.0000	fijo	0.89	0.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6774	6774	1092	t	1.0000	fijo	7.00	7.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6775	6775	1092	t	1.0000	fijo	0.85	0.85	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6776	6776	1092	t	1.0000	fijo	1.11	1.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6777	6777	1092	t	1.0000	fijo	1.12	1.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6778	6778	1092	t	1.0000	fijo	2.38	2.38	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6779	6779	1092	t	1.0000	fijo	5.39	5.39	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6780	6780	1092	t	1.0000	fijo	5.70	5.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6781	6781	1092	t	1.0000	fijo	14.92	14.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6782	6782	1092	t	1.0000	fijo	2.60	2.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6783	6783	1092	t	1.0000	fijo	2.19	2.19	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6784	6784	1092	t	1.0000	fijo	4.85	4.85	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6785	6785	1092	t	1.0000	fijo	4.68	4.68	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6786	6786	1092	t	1.0000	fijo	3.68	3.68	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6787	6787	1092	t	1.0000	fijo	7.80	7.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6788	6788	1092	t	1.0000	fijo	1.43	1.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6789	6789	1092	t	1.0000	fijo	2.11	2.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6790	6790	1092	t	1.0000	fijo	11.56	11.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6791	6791	1092	t	1.0000	fijo	7.50	7.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6792	6792	1092	t	1.0000	fijo	4.47	4.47	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6793	6793	1092	t	1.0000	fijo	4.84	4.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6794	6794	1092	t	1.0000	fijo	1.89	1.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6795	6795	1092	t	1.0000	fijo	0.25	0.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6796	6796	1092	t	1.0000	fijo	12.21	12.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6797	6797	1092	t	1.0000	fijo	6.56	6.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6798	6798	1092	t	1.0000	fijo	7.65	7.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6799	6799	1092	t	1.0000	fijo	51.50	51.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6800	6800	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6801	6801	1092	t	1.0000	fijo	0.47	0.47	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6802	6802	1092	t	1.0000	fijo	1.77	1.77	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6803	6803	1092	t	1.0000	fijo	3.49	3.49	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6804	6804	1092	t	1.0000	fijo	8.00	8.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6805	6805	1092	t	1.0000	fijo	4.80	4.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6806	6806	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6807	6807	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6808	6808	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6809	6809	1092	t	1.0000	fijo	5.00	5.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6810	6810	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6811	6811	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6812	6812	1092	t	1.0000	fijo	4.90	4.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6813	6813	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6814	6814	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6815	6815	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6816	6816	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6817	6817	1092	t	1.0000	fijo	3.28	3.28	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6818	6818	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6819	6819	1092	t	1.0000	fijo	3.29	3.29	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6820	6820	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6821	6821	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6822	6822	1092	t	1.0000	fijo	3.20	3.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6823	6823	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6824	6824	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6825	6825	1092	t	1.0000	fijo	10.01	10.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6826	6826	1092	t	1.0000	fijo	10.01	10.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6827	6827	1092	t	1.0000	fijo	9.99	9.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6828	6828	1092	t	1.0000	fijo	11.00	11.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6829	6829	1092	t	1.0000	fijo	10.50	10.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6830	6830	1092	t	1.0000	fijo	10.01	10.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6831	6831	1092	t	1.0000	fijo	9.99	9.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6832	6832	1092	t	1.0000	fijo	10.07	10.07	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6833	6833	1092	t	1.0000	fijo	10.50	10.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6834	6834	1092	t	1.0000	fijo	10.01	10.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6835	6835	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6836	6836	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6837	6837	1092	t	1.0000	fijo	7.00	7.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6838	6838	1092	t	1.0000	fijo	6.50	6.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6839	6839	1092	t	1.0000	fijo	6.80	6.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6840	6840	1092	t	1.0000	fijo	1.68	1.68	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6841	6841	1092	t	1.0000	fijo	1.53	1.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6842	6842	1092	t	1.0000	fijo	1.83	1.83	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6843	6843	1092	t	1.0000	fijo	60.92	60.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6844	6844	1092	t	1.0000	fijo	51.00	51.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6845	6845	1092	t	1.0000	fijo	15.42	15.42	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6846	6846	1092	t	1.0000	fijo	6.07	6.07	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6847	6847	1092	t	1.0000	fijo	1.82	1.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6848	6848	1092	t	1.0000	fijo	3.29	3.29	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6849	6849	1092	t	1.0000	fijo	4.77	4.77	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6850	6850	1092	t	1.0000	fijo	2.54	2.54	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6851	6851	1092	t	1.0000	fijo	18.57	18.57	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6852	6852	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6853	6853	1092	t	1.0000	fijo	12.64	12.64	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6854	6854	1092	t	1.0000	fijo	15.45	15.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6855	6855	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6856	6856	1092	t	1.0000	fijo	8.00	8.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6857	6857	1092	t	1.0000	fijo	9.65	9.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6858	6858	1092	t	1.0000	fijo	10.01	10.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6859	6859	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6860	6860	1092	t	1.0000	fijo	15.00	15.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6861	6861	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6862	6862	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6863	6863	1092	t	1.0000	fijo	3.65	3.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6864	6864	1092	t	1.0000	fijo	4.00	4.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6865	6865	1092	t	1.0000	fijo	0.11	0.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6866	6866	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6867	6867	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6868	6868	1092	t	1.0000	fijo	3.59	3.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6869	6869	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6870	6870	1092	t	1.0000	fijo	5.20	5.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6871	6871	1092	t	1.0000	fijo	32.32	32.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6872	6872	1092	t	1.0000	fijo	29.67	29.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6873	6873	1092	t	1.0000	fijo	74.27	74.27	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6874	6874	1092	t	1.0000	fijo	18.14	18.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6875	6875	1092	t	1.0000	fijo	49.98	49.98	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6876	6876	1092	t	1.0000	fijo	7.26	7.26	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6877	6877	1092	t	1.0000	fijo	13.02	13.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6878	6878	1092	t	1.0000	fijo	4.50	4.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6879	6879	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6880	6880	1092	t	1.0000	fijo	11.00	11.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6881	6881	1092	t	1.0000	fijo	4.35	4.35	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6882	6882	1092	t	1.0000	fijo	2.34	2.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6883	6883	1092	t	1.0000	fijo	1.32	1.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6884	6884	1092	t	1.0000	fijo	5.00	5.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6885	6885	1092	t	1.0000	fijo	0.25	0.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6886	6886	1092	t	1.0000	fijo	0.22	0.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6887	6887	1092	t	1.0000	fijo	0.30	0.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6888	6888	1092	t	1.0000	fijo	0.40	0.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6889	6889	1092	t	1.0000	fijo	20.79	20.79	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6890	6890	1092	t	1.0000	fijo	2.68	2.68	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6891	6891	1092	t	1.0000	fijo	1.96	1.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6892	6892	1092	t	1.0000	fijo	0.59	0.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6893	6893	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6894	6894	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6895	6895	1092	t	1.0000	fijo	3.50	3.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6896	6896	1092	t	1.0000	fijo	3.50	3.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6897	6897	1092	t	1.0000	fijo	3.01	3.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6898	6898	1092	t	1.0000	fijo	3.29	3.29	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6899	6899	1092	t	1.0000	fijo	5.68	5.68	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6900	6900	1092	t	1.0000	fijo	3.96	3.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6901	6901	1092	t	1.0000	fijo	19.97	19.97	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6902	6902	1092	t	1.0000	fijo	7.33	7.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6903	6903	1092	t	1.0000	fijo	7.33	7.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6904	6904	1092	t	1.0000	fijo	1.09	1.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6905	6905	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6906	6906	1092	t	1.0000	fijo	1.09	1.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6907	6907	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6908	6908	1092	t	1.0000	fijo	2.14	2.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6909	6909	1092	t	1.0000	fijo	20.38	20.38	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6910	6910	1092	t	1.0000	fijo	1.89	1.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6911	6911	1092	t	1.0000	fijo	12.86	12.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6912	6912	1092	t	1.0000	fijo	1.46	1.46	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6913	6913	1092	t	1.0000	fijo	1.42	1.42	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6914	6914	1092	t	1.0000	fijo	9.45	9.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6915	6915	1092	t	1.0000	fijo	2.67	2.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6916	6916	1092	t	1.0000	fijo	20.10	20.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6917	6917	1092	t	1.0000	fijo	3.34	3.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6918	6918	1092	t	1.0000	fijo	111.33	111.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6919	6919	1092	t	1.0000	fijo	32.50	32.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6920	6920	1092	t	1.0000	fijo	37.50	37.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6921	6921	1092	t	1.0000	fijo	2.04	2.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6922	6922	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6923	6923	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6924	6924	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6925	6925	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6926	6926	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6927	6927	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6928	6928	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6929	6929	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6930	6930	1092	t	1.0000	fijo	1.45	1.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6931	6931	1092	t	1.0000	fijo	7.50	7.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6932	6932	1092	t	1.0000	fijo	6.01	6.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6933	6933	1092	t	1.0000	fijo	7.45	7.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6934	6934	1092	t	1.0000	fijo	4.52	4.52	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6935	6935	1092	t	1.0000	fijo	41.93	41.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6936	6936	1092	t	1.0000	fijo	19.71	19.71	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6937	6937	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6938	6938	1092	t	1.0000	fijo	16.69	16.69	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6939	6939	1092	t	1.0000	fijo	14.01	14.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6940	6940	1092	t	1.0000	fijo	17.94	17.94	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6941	6941	1092	t	1.0000	fijo	16.31	16.31	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6942	6942	1092	t	1.0000	fijo	20.10	20.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6943	6943	1092	t	1.0000	fijo	19.74	19.74	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6944	6944	1092	t	1.0000	fijo	12.85	12.85	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6945	6945	1092	t	1.0000	fijo	12.61	12.61	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6946	6946	1092	t	1.0000	fijo	3.93	3.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6947	6947	1092	t	1.0000	fijo	54.50	54.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6948	6948	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6949	6949	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6950	6950	1092	t	1.0000	fijo	5.39	5.39	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6951	6951	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6952	6952	1092	t	1.0000	fijo	2.55	2.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6953	6953	1092	t	1.0000	fijo	2.95	2.95	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6954	6954	1092	t	1.0000	fijo	1.23	1.23	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6955	6955	1092	t	1.0000	fijo	1.18	1.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6956	6956	1092	t	1.0000	fijo	1.16	1.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6957	6957	1092	t	1.0000	fijo	1.40	1.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6958	6958	1092	t	1.0000	fijo	1.05	1.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6959	6959	1092	t	1.0000	fijo	1.16	1.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6960	6960	1092	t	1.0000	fijo	1.12	1.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6961	6961	1092	t	1.0000	fijo	1.27	1.27	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6962	6962	1092	t	1.0000	fijo	1.42	1.42	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6963	6963	1092	t	1.0000	fijo	1.86	1.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6964	6964	1092	t	1.0000	fijo	1.65	1.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6965	6965	1092	t	1.0000	fijo	1.25	1.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6966	6966	1092	t	1.0000	fijo	1.24	1.24	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6967	6967	1092	t	1.0000	fijo	19.35	19.35	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6968	6968	1092	t	1.0000	fijo	32.53	32.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6969	6969	1092	t	1.0000	fijo	11.55	11.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6970	6970	1092	t	1.0000	fijo	18.35	18.35	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6971	6971	1092	t	1.0000	fijo	16.43	16.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6972	6972	1092	t	1.0000	fijo	15.84	15.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6973	6973	1092	t	1.0000	fijo	14.71	14.71	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6974	6974	1092	t	1.0000	fijo	15.12	15.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6975	6975	1092	t	1.0000	fijo	8.96	8.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6976	6976	1092	t	1.0000	fijo	11.22	11.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6977	6977	1092	t	1.0000	fijo	7.17	7.17	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6978	6978	1092	t	1.0000	fijo	1.11	1.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6979	6979	1092	t	1.0000	fijo	1.16	1.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6980	6980	1092	t	1.0000	fijo	1.24	1.24	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6981	6981	1092	t	1.0000	fijo	1.36	1.36	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6982	6982	1092	t	1.0000	fijo	1.56	1.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6983	6983	1092	t	1.0000	fijo	1.86	1.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6984	6984	1092	t	1.0000	fijo	2.15	2.15	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6985	6985	1092	t	1.0000	fijo	2.54	2.54	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6986	6986	1092	t	1.0000	fijo	1.04	1.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6987	6987	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6988	6988	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6989	6989	1092	t	1.0000	fijo	26.51	26.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6990	6990	1092	t	1.0000	fijo	39.22	39.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6991	6991	1092	t	1.0000	fijo	7.94	7.94	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6992	6992	1092	t	1.0000	fijo	8.47	8.47	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6993	6993	1092	t	1.0000	fijo	3.19	3.19	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6994	6994	1092	t	1.0000	fijo	4.86	4.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6995	6995	1092	t	1.0000	fijo	24.00	24.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6996	6996	1092	t	1.0000	fijo	36.26	36.26	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6997	6997	1092	t	1.0000	fijo	36.46	36.46	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6998	6998	1092	t	1.0000	fijo	35.21	35.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
6999	6999	1092	t	1.0000	fijo	36.50	36.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7000	7000	1092	t	1.0000	fijo	26.00	26.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7001	7001	1092	t	1.0000	fijo	24.89	24.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7002	7002	1092	t	1.0000	fijo	27.27	27.27	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7003	7003	1092	t	1.0000	fijo	12.50	12.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7004	7004	1092	t	1.0000	fijo	13.00	13.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7005	7005	1092	t	1.0000	fijo	3.73	3.73	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7006	7006	1092	t	1.0000	fijo	1.31	1.31	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7007	7007	1092	t	1.0000	fijo	3.69	3.69	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7008	7008	1092	t	1.0000	fijo	5.18	5.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7009	7009	1092	t	1.0000	fijo	0.65	0.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7010	7010	1092	t	1.0000	fijo	1.52	1.52	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7011	7011	1092	t	1.0000	fijo	2.82	2.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7012	7012	1092	t	1.0000	fijo	1.75	1.75	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7013	7013	1092	t	1.0000	fijo	1.16	1.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7014	7014	1092	t	1.0000	fijo	12.34	12.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7015	7015	1092	t	1.0000	fijo	10.03	10.03	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7016	7016	1092	t	1.0000	fijo	9.44	9.44	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7017	7017	1092	t	1.0000	fijo	1.20	1.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7018	7018	1092	t	1.0000	fijo	1.48	1.48	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7019	7019	1092	t	1.0000	fijo	1.65	1.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7020	7020	1092	t	1.0000	fijo	4.90	4.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7021	7021	1092	t	1.0000	fijo	1.63	1.63	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7022	7022	1092	t	1.0000	fijo	1.13	1.13	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7023	7023	1092	t	1.0000	fijo	1.70	1.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7024	7024	1092	t	1.0000	fijo	1.09	1.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7025	7025	1092	t	1.0000	fijo	0.53	0.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7026	7026	1092	t	1.0000	fijo	23.51	23.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7027	7027	1092	t	1.0000	fijo	26.00	26.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7028	7028	1092	t	1.0000	fijo	9.99	9.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7029	7029	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7030	7030	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7031	7031	1092	t	1.0000	fijo	0.71	0.71	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7032	7032	1092	t	1.0000	fijo	0.42	0.42	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7033	7033	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7034	7034	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7035	7035	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7036	7036	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7037	7037	1092	t	1.0000	fijo	0.64	0.64	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7038	7038	1092	t	1.0000	fijo	0.72	0.72	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7039	7039	1092	t	1.0000	fijo	13.43	13.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7040	7040	1092	t	1.0000	fijo	19.94	19.94	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7041	7041	1092	t	1.0000	fijo	24.34	24.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7042	7042	1092	t	1.0000	fijo	32.53	32.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7043	7043	1092	t	1.0000	fijo	3.55	3.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7044	7044	1092	t	1.0000	fijo	3.86	3.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7045	7045	1092	t	1.0000	fijo	8.90	8.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7046	7046	1092	t	1.0000	fijo	3.08	3.08	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7047	7047	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7048	7048	1092	t	1.0000	fijo	5.00	5.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7049	7049	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7050	7050	1092	t	1.0000	fijo	7.30	7.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7051	7051	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7052	7052	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7053	7053	1092	t	1.0000	fijo	0.60	0.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7054	7054	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7055	7055	1092	t	1.0000	fijo	1.10	1.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7056	7056	1092	t	1.0000	fijo	0.37	0.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7057	7057	1092	t	1.0000	fijo	0.37	0.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7058	7058	1092	t	1.0000	fijo	0.60	0.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7059	7059	1092	t	1.0000	fijo	0.45	0.45	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7060	7060	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7061	7061	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7062	7062	1092	t	1.0000	fijo	3.56	3.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7063	7063	1092	t	1.0000	fijo	3.28	3.28	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7064	7064	1092	t	1.0000	fijo	3.50	3.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7065	7065	1092	t	1.0000	fijo	3.60	3.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7066	7066	1092	t	1.0000	fijo	6.01	6.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7067	7067	1092	t	1.0000	fijo	0.57	0.57	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7068	7068	1092	t	1.0000	fijo	0.40	0.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7069	7069	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7070	7070	1092	t	1.0000	fijo	32.00	32.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7071	7071	1092	t	1.0000	fijo	33.00	33.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7072	7072	1092	t	1.0000	fijo	8.00	8.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7073	7073	1092	t	1.0000	fijo	8.50	8.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7074	7074	1092	t	1.0000	fijo	12.50	12.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7075	7075	1092	t	1.0000	fijo	0.27	0.27	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7076	7076	1092	t	1.0000	fijo	0.33	0.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7077	7077	1092	t	1.0000	fijo	4.51	4.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7078	7078	1092	t	1.0000	fijo	0.67	0.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7079	7079	1092	t	1.0000	fijo	0.89	0.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7080	7080	1092	t	1.0000	fijo	1.14	1.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7081	7081	1092	t	1.0000	fijo	16.00	16.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7082	7082	1092	t	1.0000	fijo	13.00	13.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7083	7083	1092	t	1.0000	fijo	13.10	13.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7084	7084	1092	t	1.0000	fijo	12.92	12.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7085	7085	1092	t	1.0000	fijo	13.50	13.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7086	7086	1092	t	1.0000	fijo	12.57	12.57	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7087	7087	1092	t	1.0000	fijo	12.84	12.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7088	7088	1092	t	1.0000	fijo	13.50	13.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7089	7089	1092	t	1.0000	fijo	12.84	12.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7090	7090	1092	t	1.0000	fijo	15.00	15.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7091	7091	1092	t	1.0000	fijo	15.00	15.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7092	7092	1092	t	1.0000	fijo	13.50	13.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7093	7093	1092	t	1.0000	fijo	12.50	12.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7094	7094	1092	t	1.0000	fijo	12.70	12.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7095	7095	1092	t	1.0000	fijo	2.80	2.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7096	7096	1092	t	1.0000	fijo	2.94	2.94	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7097	7097	1092	t	1.0000	fijo	18.00	18.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7098	7098	1092	t	1.0000	fijo	2.95	2.95	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7099	7099	1092	t	1.0000	fijo	2.77	2.77	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7100	7100	1092	t	1.0000	fijo	2.78	2.78	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7101	7101	1092	t	1.0000	fijo	2.91	2.91	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7102	7102	1092	t	1.0000	fijo	3.41	3.41	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7103	7103	1092	t	1.0000	fijo	4.58	4.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7104	7104	1092	t	1.0000	fijo	2.91	2.91	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7105	7105	1092	t	1.0000	fijo	2.96	2.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7106	7106	1092	t	1.0000	fijo	4.59	4.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7107	7107	1092	t	1.0000	fijo	9.00	9.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7108	7108	1092	t	1.0000	fijo	7.86	7.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7109	7109	1092	t	1.0000	fijo	6.70	6.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7110	7110	1092	t	1.0000	fijo	10.96	10.96	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7111	7111	1092	t	1.0000	fijo	2.32	2.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7112	7112	1092	t	1.0000	fijo	3.06	3.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7113	7113	1092	t	1.0000	fijo	0.05	0.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7114	7114	1092	t	1.0000	fijo	0.01	0.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7115	7115	1092	t	1.0000	fijo	0.05	0.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7116	7116	1092	t	1.0000	fijo	11.61	11.61	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7117	7117	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7118	7118	1092	t	1.0000	fijo	6.50	6.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7119	7119	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7120	7120	1092	t	1.0000	fijo	9.81	9.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7121	7121	1092	t	1.0000	fijo	46.00	46.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7122	7122	1092	t	1.0000	fijo	38.40	38.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7123	7123	1092	t	1.0000	fijo	26.10	26.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7124	7124	1092	t	1.0000	fijo	4.01	4.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7125	7125	1092	t	1.0000	fijo	14.40	14.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7126	7126	1092	t	1.0000	fijo	3.28	3.28	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7127	7127	1092	t	1.0000	fijo	16.30	16.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7128	7128	1092	t	1.0000	fijo	7.73	7.73	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7129	7129	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7130	7130	1092	t	1.0000	fijo	1.59	1.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7131	7131	1092	t	1.0000	fijo	40.00	40.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7132	7132	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7133	7133	1092	t	1.0000	fijo	66.00	66.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7134	7134	1092	t	1.0000	fijo	60.00	60.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7135	7135	1092	t	1.0000	fijo	0.58	0.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7136	7136	1092	t	1.0000	fijo	0.67	0.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7137	7137	1092	t	1.0000	fijo	13.40	13.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7138	7138	1092	t	1.0000	fijo	13.00	13.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7139	7139	1092	t	1.0000	fijo	13.50	13.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7140	7140	1092	t	1.0000	fijo	13.50	13.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7141	7141	1092	t	1.0000	fijo	2.95	2.95	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7142	7142	1092	t	1.0000	fijo	2.87	2.87	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7143	7143	1092	t	1.0000	fijo	2.80	2.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7144	7144	1092	t	1.0000	fijo	2.80	2.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7145	7145	1092	t	1.0000	fijo	2.80	2.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7146	7146	1092	t	1.0000	fijo	2.87	2.87	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7147	7147	1092	t	1.0000	fijo	2.88	2.88	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7148	7148	1092	t	1.0000	fijo	2.80	2.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7149	7149	1092	t	1.0000	fijo	2.95	2.95	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7150	7150	1092	t	1.0000	fijo	4.14	4.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7151	7151	1092	t	1.0000	fijo	2.99	2.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7152	7152	1092	t	1.0000	fijo	3.02	3.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7153	7153	1092	t	1.0000	fijo	3.12	3.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7154	7154	1092	t	1.0000	fijo	3.66	3.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7155	7155	1092	t	1.0000	fijo	3.32	3.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7156	7156	1092	t	1.0000	fijo	2.93	2.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7157	7157	1092	t	1.0000	fijo	3.15	3.15	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7158	7158	1092	t	1.0000	fijo	3.48	3.48	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7159	7159	1092	t	1.0000	fijo	2.97	2.97	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7160	7160	1092	t	1.0000	fijo	3.50	3.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7161	7161	1092	t	1.0000	fijo	3.33	3.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7162	7162	1092	t	1.0000	fijo	12.00	12.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7163	7163	1092	t	1.0000	fijo	10.67	10.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7164	7164	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7165	7165	1092	t	1.0000	fijo	0.04	0.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7166	7166	1092	t	1.0000	fijo	0.13	0.13	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7167	7167	1092	t	1.0000	fijo	3.29	3.29	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7168	7168	1092	t	1.0000	fijo	8.87	8.87	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7169	7169	1092	t	1.0000	fijo	7.89	7.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7170	7170	1092	t	1.0000	fijo	31.03	31.03	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7171	7171	1092	t	1.0000	fijo	13.99	13.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7172	7172	1092	t	1.0000	fijo	4.06	4.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7173	7173	1092	t	1.0000	fijo	17.50	17.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7174	7174	1092	t	1.0000	fijo	1.90	1.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7175	7175	1092	t	1.0000	fijo	1.83	1.83	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7176	7176	1092	t	1.0000	fijo	1.32	1.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7177	7177	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7178	7178	1092	t	1.0000	fijo	27.06	27.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7179	7179	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7180	7180	1092	t	1.0000	fijo	0.93	0.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7181	7181	1092	t	1.0000	fijo	1.14	1.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7182	7182	1092	t	1.0000	fijo	1.91	1.91	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7183	7183	1092	t	1.0000	fijo	1.66	1.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7184	7184	1092	t	1.0000	fijo	2.22	2.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7185	7185	1092	t	1.0000	fijo	0.92	0.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7186	7186	1092	t	1.0000	fijo	1.60	1.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7187	7187	1092	t	1.0000	fijo	3.76	3.76	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7188	7188	1092	t	1.0000	fijo	4.04	4.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7189	7189	1092	t	1.0000	fijo	2.60	2.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7190	7190	1092	t	1.0000	fijo	6.37	6.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7191	7191	1092	t	1.0000	fijo	3.12	3.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7192	7192	1092	t	1.0000	fijo	6.57	6.57	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7193	7193	1092	t	1.0000	fijo	9.70	9.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7194	7194	1092	t	1.0000	fijo	9.61	9.61	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7195	7195	1092	t	1.0000	fijo	9.52	9.52	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7196	7196	1092	t	1.0000	fijo	3.03	3.03	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7197	7197	1092	t	1.0000	fijo	3.21	3.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7198	7198	1092	t	1.0000	fijo	14.50	14.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7199	7199	1092	t	1.0000	fijo	6.01	6.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7200	7200	1092	t	1.0000	fijo	6.08	6.08	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7201	7201	1092	t	1.0000	fijo	7.50	7.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7202	7202	1092	t	1.0000	fijo	7.63	7.63	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7203	7203	1092	t	1.0000	fijo	1.36	1.36	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7204	7204	1092	t	1.0000	fijo	1.99	1.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7205	7205	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7206	7206	1092	t	1.0000	fijo	1.99	1.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7207	7207	1092	t	1.0000	fijo	1.71	1.71	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7208	7208	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7209	7209	1092	t	1.0000	fijo	1.82	1.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7210	7210	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7211	7211	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7212	7212	1092	t	1.0000	fijo	1.84	1.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7213	7213	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7214	7214	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7215	7215	1092	t	1.0000	fijo	1.90	1.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7216	7216	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7217	7217	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7218	7218	1092	t	1.0000	fijo	0.50	0.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7219	7219	1092	t	1.0000	fijo	0.50	0.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7220	7220	1092	t	1.0000	fijo	1.14	1.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7221	7221	1092	t	1.0000	fijo	3.08	3.08	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7222	7222	1092	t	1.0000	fijo	21.30	21.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7223	7223	1092	t	1.0000	fijo	12.05	12.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7224	7224	1092	t	1.0000	fijo	4.79	4.79	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7225	7225	1092	t	1.0000	fijo	5.25	5.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7226	7226	1092	t	1.0000	fijo	4.70	4.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7227	7227	1092	t	1.0000	fijo	11.66	11.66	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7228	7228	1092	t	1.0000	fijo	0.92	0.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7229	7229	1092	t	1.0000	fijo	0.01	0.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7230	7230	1092	t	1.0000	fijo	1.52	1.52	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7231	7231	1092	t	1.0000	fijo	8.71	8.71	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7232	7232	1092	t	1.0000	fijo	16.82	16.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7233	7233	1092	t	1.0000	fijo	7.33	7.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7234	7234	1092	t	1.0000	fijo	0.05	0.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7235	7235	1092	t	1.0000	fijo	0.05	0.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7236	7236	1092	t	1.0000	fijo	0.05	0.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7237	7237	1092	t	1.0000	fijo	0.06	0.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7238	7238	1092	t	1.0000	fijo	0.38	0.38	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7239	7239	1092	t	1.0000	fijo	1.86	1.86	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7240	7240	1092	t	1.0000	fijo	2.37	2.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7241	7241	1092	t	1.0000	fijo	1.70	1.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7242	7242	1092	t	1.0000	fijo	0.63	0.63	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7243	7243	1092	t	1.0000	fijo	2.63	2.63	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7244	7244	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7245	7245	1092	t	1.0000	fijo	1.64	1.64	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7246	7246	1092	t	1.0000	fijo	0.84	0.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7247	7247	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7248	7248	1092	t	1.0000	fijo	4.31	4.31	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7249	7249	1092	t	1.0000	fijo	6.34	6.34	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7250	7250	1092	t	1.0000	fijo	3.80	3.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7251	7251	1092	t	1.0000	fijo	19.71	19.71	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7252	7252	1092	t	1.0000	fijo	7.62	7.62	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7253	7253	1092	t	1.0000	fijo	3.33	3.33	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7254	7254	1092	t	1.0000	fijo	5.20	5.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7255	7255	1092	t	1.0000	fijo	23.00	23.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7256	7256	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7257	7257	1092	t	1.0000	fijo	5.00	5.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7258	7258	1092	t	1.0000	fijo	10.48	10.48	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7259	7259	1092	t	1.0000	fijo	15.40	15.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7260	7260	1092	t	1.0000	fijo	7.32	7.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7261	7261	1092	t	1.0000	fijo	1.90	1.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7262	7262	1092	t	1.0000	fijo	6.50	6.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7263	7263	1092	t	1.0000	fijo	2.90	2.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7264	7264	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7265	7265	1092	t	1.0000	fijo	10.44	10.44	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7266	7266	1092	t	1.0000	fijo	0.55	0.55	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7267	7267	1092	t	1.0000	fijo	8.50	8.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7268	7268	1092	t	1.0000	fijo	4.25	4.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7269	7269	1092	t	1.0000	fijo	5.40	5.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7270	7270	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7271	7271	1092	t	1.0000	fijo	1.90	1.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7272	7272	1092	t	1.0000	fijo	1.82	1.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7273	7273	1092	t	1.0000	fijo	8.07	8.07	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7274	7274	1092	t	1.0000	fijo	14.84	14.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7275	7275	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7276	7276	1092	t	1.0000	fijo	9.13	9.13	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7277	7277	1092	t	1.0000	fijo	0.30	0.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7278	7278	1092	t	1.0000	fijo	0.20	0.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7279	7279	1092	t	1.0000	fijo	14.47	14.47	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7280	7280	1092	t	1.0000	fijo	16.00	16.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7281	7281	1092	t	1.0000	fijo	3.30	3.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7282	7282	1092	t	1.0000	fijo	10.73	10.73	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7283	7283	1092	t	1.0000	fijo	5.14	5.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7284	7284	1092	t	1.0000	fijo	0.09	0.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7285	7285	1092	t	1.0000	fijo	8.19	8.19	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7286	7286	1092	t	1.0000	fijo	0.09	0.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7287	7287	1092	t	1.0000	fijo	2.25	2.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7288	7288	1092	t	1.0000	fijo	2.21	2.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7289	7289	1092	t	1.0000	fijo	1.72	1.72	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7290	7290	1092	t	1.0000	fijo	2.04	2.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7291	7291	1092	t	1.0000	fijo	13.63	13.63	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7292	7292	1092	t	1.0000	fijo	0.06	0.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7293	7293	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7294	7294	1092	t	1.0000	fijo	0.04	0.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7295	7295	1092	t	1.0000	fijo	7.15	7.15	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7296	7296	1092	t	1.0000	fijo	9.61	9.61	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7297	7297	1092	t	1.0000	fijo	21.75	21.75	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7298	7298	1092	t	1.0000	fijo	38.50	38.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7299	7299	1092	t	1.0000	fijo	73.50	73.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7300	7300	1092	t	1.0000	fijo	0.12	0.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7301	7301	1092	t	1.0000	fijo	19.10	19.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7302	7302	1092	t	1.0000	fijo	7.04	7.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7303	7303	1092	t	1.0000	fijo	7.50	7.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7304	7304	1092	t	1.0000	fijo	18.20	18.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7305	7305	1092	t	1.0000	fijo	89.76	89.76	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7306	7306	1092	t	1.0000	fijo	4.00	4.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7307	7307	1092	t	1.0000	fijo	2.14	2.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7308	7308	1092	t	1.0000	fijo	2.30	2.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7309	7309	1092	t	1.0000	fijo	568.00	568.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7310	7310	1092	t	1.0000	fijo	500.00	500.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7311	7311	1092	t	1.0000	fijo	0.22	0.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7312	7312	1092	t	1.0000	fijo	0.22	0.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7313	7313	1092	t	1.0000	fijo	0.84	0.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7314	7314	1092	t	1.0000	fijo	1.48	1.48	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7315	7315	1092	t	1.0000	fijo	1.90	1.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7316	7316	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7317	7317	1092	t	1.0000	fijo	2.18	2.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7318	7318	1092	t	1.0000	fijo	0.40	0.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7319	7319	1092	t	1.0000	fijo	1.29	1.29	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7320	7320	1092	t	1.0000	fijo	0.26	0.26	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7321	7321	1092	t	1.0000	fijo	1.10	1.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7322	7322	1092	t	1.0000	fijo	0.90	0.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7323	7323	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7324	7324	1092	t	1.0000	fijo	1.62	1.62	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7325	7325	1092	t	1.0000	fijo	1.04	1.04	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7326	7326	1092	t	1.0000	fijo	2.30	2.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7327	7327	1092	t	1.0000	fijo	1.39	1.39	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7328	7328	1092	t	1.0000	fijo	0.59	0.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7329	7329	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7330	7330	1092	t	1.0000	fijo	1.40	1.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7331	7331	1092	t	1.0000	fijo	1.11	1.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7332	7332	1092	t	1.0000	fijo	1.58	1.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7333	7333	1092	t	1.0000	fijo	1.18	1.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7334	7334	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7335	7335	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7336	7336	1092	t	1.0000	fijo	0.02	0.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7337	7337	1092	t	1.0000	fijo	6.40	6.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7338	7338	1092	t	1.0000	fijo	2.82	2.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7339	7339	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7340	7340	1092	t	1.0000	fijo	2.78	2.78	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7341	7341	1092	t	1.0000	fijo	1.20	1.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7342	7342	1092	t	1.0000	fijo	2.10	2.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7343	7343	1092	t	1.0000	fijo	4.84	4.84	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7344	7344	1092	t	1.0000	fijo	3.00	3.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7345	7345	1092	t	1.0000	fijo	6.24	6.24	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7346	7346	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7347	7347	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7348	7348	1092	t	1.0000	fijo	2.19	2.19	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7349	7349	1092	t	1.0000	fijo	0.89	0.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7350	7350	1092	t	1.0000	fijo	3.40	3.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7351	7351	1092	t	1.0000	fijo	1.30	1.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7352	7352	1092	t	1.0000	fijo	3.30	3.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7353	7353	1092	t	1.0000	fijo	3.30	3.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7354	7354	1092	t	1.0000	fijo	10.15	10.15	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7355	7355	1092	t	1.0000	fijo	0.00	0.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7356	7356	1092	t	1.0000	fijo	8.00	8.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7357	7357	1092	t	1.0000	fijo	4.30	4.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7358	7358	1092	t	1.0000	fijo	10.08	10.08	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7359	7359	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7360	7360	1092	t	1.0000	fijo	1.00	1.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7361	7361	1092	t	1.0000	fijo	4.37	4.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7362	7362	1092	t	1.0000	fijo	18.18	18.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7363	7363	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7364	7364	1092	t	1.0000	fijo	35.00	35.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7365	7365	1092	t	1.0000	fijo	0.06	0.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7366	7366	1092	t	1.0000	fijo	0.22	0.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7367	7367	1092	t	1.0000	fijo	0.18	0.18	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7368	7368	1092	t	1.0000	fijo	0.09	0.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7369	7369	1092	t	1.0000	fijo	0.25	0.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7370	7370	1092	t	1.0000	fijo	10.43	10.43	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7371	7371	1092	t	1.0000	fijo	15.92	15.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7372	7372	1092	t	1.0000	fijo	2.07	2.07	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7373	7373	1092	t	1.0000	fijo	4.11	4.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7374	7374	1092	t	1.0000	fijo	9.50	9.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7375	7375	1092	t	1.0000	fijo	1.11	1.11	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7376	7376	1092	t	1.0000	fijo	1.82	1.82	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7377	7377	1092	t	1.0000	fijo	2.01	2.01	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7378	7378	1092	t	1.0000	fijo	6.05	6.05	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7379	7379	1092	t	1.0000	fijo	9.65	9.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7380	7380	1092	t	1.0000	fijo	5.50	5.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7381	7381	1092	t	1.0000	fijo	3.58	3.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7382	7382	1092	t	1.0000	fijo	1.79	1.79	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7383	7383	1092	t	1.0000	fijo	2.21	2.21	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7384	7384	1092	t	1.0000	fijo	23.51	23.51	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7385	7385	1092	t	1.0000	fijo	39.49	39.49	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7386	7386	1092	t	1.0000	fijo	32.98	32.98	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7387	7387	1092	t	1.0000	fijo	20.00	20.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7388	7388	1092	t	1.0000	fijo	9.99	9.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7389	7389	1092	t	1.0000	fijo	17.00	17.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7390	7390	1092	t	1.0000	fijo	9.70	9.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7391	7391	1092	t	1.0000	fijo	12.70	12.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7392	7392	1092	t	1.0000	fijo	13.70	13.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7393	7393	1092	t	1.0000	fijo	12.00	12.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7394	7394	1092	t	1.0000	fijo	15.10	15.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7395	7395	1092	t	1.0000	fijo	12.50	12.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7396	7396	1092	t	1.0000	fijo	6.60	6.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7397	7397	1092	t	1.0000	fijo	32.00	32.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7398	7398	1092	t	1.0000	fijo	13.40	13.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7399	7399	1092	t	1.0000	fijo	33.50	33.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7400	7400	1092	t	1.0000	fijo	8.40	8.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7401	7401	1092	t	1.0000	fijo	4.80	4.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7402	7402	1092	t	1.0000	fijo	5.81	5.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7403	7403	1092	t	1.0000	fijo	0.09	0.09	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7404	7404	1092	t	1.0000	fijo	1.12	1.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7405	7405	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7406	7406	1092	t	1.0000	fijo	1.06	1.06	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7407	7407	1092	t	1.0000	fijo	0.50	0.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7408	7408	1092	t	1.0000	fijo	2.50	2.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7409	7409	1092	t	1.0000	fijo	8.58	8.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7410	7410	1092	t	1.0000	fijo	2.25	2.25	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7411	7411	1092	t	1.0000	fijo	0.85	0.85	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7412	7412	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7413	7413	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7414	7414	1092	t	1.0000	fijo	2.93	2.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7415	7415	1092	t	1.0000	fijo	1.53	1.53	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7416	7416	1092	t	1.0000	fijo	2.44	2.44	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7417	7417	1092	t	1.0000	fijo	2.93	2.93	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7418	7418	1092	t	1.0000	fijo	0.50	0.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7419	7419	1092	t	1.0000	fijo	1.56	1.56	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7420	7420	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7421	7421	1092	t	1.0000	fijo	0.80	0.80	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7422	7422	1092	t	1.0000	fijo	0.37	0.37	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7423	7423	1092	t	1.0000	fijo	0.20	0.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7424	7424	1092	t	1.0000	fijo	1.12	1.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7425	7425	1092	t	1.0000	fijo	2.14	2.14	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7426	7426	1092	t	1.0000	fijo	1.59	1.59	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7427	7427	1092	t	1.0000	fijo	2.12	2.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7428	7428	1092	t	1.0000	fijo	1.89	1.89	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7429	7429	1092	t	1.0000	fijo	0.67	0.67	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7430	7430	1092	t	1.0000	fijo	0.70	0.70	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7431	7431	1092	t	1.0000	fijo	1.50	1.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7432	7432	1092	t	1.0000	fijo	3.22	3.22	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7433	7433	1092	t	1.0000	fijo	5.40	5.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7434	7434	1092	t	1.0000	fijo	2.58	2.58	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7435	7435	1092	t	1.0000	fijo	2.40	2.40	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7436	7436	1092	t	1.0000	fijo	4.81	4.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7437	7437	1092	t	1.0000	fijo	3.60	3.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7438	7438	1092	t	1.0000	fijo	2.10	2.10	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7439	7439	1092	t	1.0000	fijo	3.60	3.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7440	7440	1092	t	1.0000	fijo	8.77	8.77	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7441	7441	1092	t	1.0000	fijo	16.50	16.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7442	7442	1092	t	1.0000	fijo	0.65	0.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7443	7443	1092	t	1.0000	fijo	16.69	16.69	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7444	7444	1092	t	1.0000	fijo	83.90	83.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7445	7445	1092	t	1.0000	fijo	14.50	14.50	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7446	7446	1092	t	1.0000	fijo	0.65	0.65	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7447	7447	1092	t	1.0000	fijo	1.81	1.81	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7448	7448	1092	t	1.0000	fijo	5.02	5.02	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7449	7449	1092	t	1.0000	fijo	5.99	5.99	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7450	7450	1092	t	1.0000	fijo	3.72	3.72	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7451	7451	1092	t	1.0000	fijo	12.12	12.12	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7452	7452	1092	t	1.0000	fijo	21.72	21.72	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7453	7453	1092	t	1.0000	fijo	7.16	7.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7454	7454	1092	t	1.0000	fijo	11.00	11.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7455	7455	1092	t	1.0000	fijo	9.90	9.90	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7456	7456	1092	t	1.0000	fijo	3.30	3.30	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7457	7457	1092	t	1.0000	fijo	2.88	2.88	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7458	7458	1092	t	1.0000	fijo	4.00	4.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7459	7459	1092	t	1.0000	fijo	2.42	2.42	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7460	7460	1092	t	1.0000	fijo	7.92	7.92	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7461	7461	1092	t	1.0000	fijo	3.62	3.62	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7462	7462	1092	t	1.0000	fijo	8.91	8.91	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7463	7463	1092	t	1.0000	fijo	3.91	3.91	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7464	7464	1092	t	1.0000	fijo	12.00	12.00	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7465	7465	1092	t	1.0000	fijo	14.60	14.60	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7466	7466	1092	t	1.0000	fijo	9.32	9.32	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7467	7467	1092	t	1.0000	fijo	0.20	0.20	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7468	7468	1092	t	1.0000	fijo	14.24	14.24	t	2026-07-11 10:12:12	2026-07-11 10:12:12
7469	7469	1092	t	1.0000	fijo	98.16	98.16	t	2026-07-11 10:12:12	2026-07-11 10:12:12
\.


--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.productos (id, empresa_id, categoria_id, codigo, nombre, descripcion, tipo, tipo_precio, precio_venta, precio_costo, imagen, activo, created_at, updated_at, incluye_igv, controla_stock, es_retornable) FROM stdin;
1	1	1	P-0001	Blusa básica blanca	\N	producto	fijo	45.00	24.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
2	1	1	P-0002	Blusa con encaje	\N	producto	fijo	65.00	35.75	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
3	1	1	P-0003	Vestido casual floreado	\N	producto	fijo	89.00	48.95	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
4	1	1	P-0004	Vestido coctel negro	\N	producto	fijo	120.00	66.00	\N	f	2026-05-18 01:53:39	2026-05-18 01:53:39	t	t	t
6520	1097	1104	FERHC-0001	ABRAZADERA GALVANIZADA SIN FIN 5/8 C&A	\N	producto	fijo	0.26	0.26	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6521	1097	1104	FERHC-0002	ACCESORIO PARA WATER C/JALADOR BOYA NEGRA C&A	\N	producto	fijo	10.80	10.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6522	1097	1104	FERHC-0003	ACCESORIO PARA WATER C/PULSADOR C&A	\N	producto	fijo	14.55	14.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6523	1097	1104	FERHC-0004	ACEITE LUBRICANTE 30ML 3 EN 1	\N	producto	fijo	3.98	3.98	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6524	1097	1104	FERHC-0005	ACEITE LUBRICANTE 90ML 3 EN 1	\N	producto	fijo	6.77	6.77	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6525	1097	1104	FERHC-0006	ACEITE RP RIDER TOWN 4T 20W50 12 X 1LT REPSOL	\N	producto	fijo	17.50	17.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6526	1097	1104	FERHC-0007	ACIDO ESPECIAL KRIZZAL	\N	producto	fijo	3.37	3.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6527	1097	1104	FERHC-0008	ADAPTADOR CPVC 1/2 PAVCO	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6528	1097	1104	FERHC-0009	ADAPTADOR DE BRONCE 1/2 X 1 1/4 VALMAX	\N	producto	fijo	2.34	2.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6529	1097	1104	FERHC-0010	ADAPTADOR DE ENCHUFE UNIVERSAL SWIFT	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6530	1097	1104	FERHC-0011	ADAPTADOR ELECTRICO TRIPLE TIPO T HOME LIGHT	\N	producto	fijo	2.02	2.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6531	1097	1104	FERHC-0012	ALAMBRE PUAS X 200MT PRODAC	\N	producto	fijo	43.92	43.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6532	1097	1104	FERHC-0013	ALCAYATA 3 (56 UNID-0.50KG) VARIOS	\N	producto	fijo	0.12	0.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6533	1097	1104	FERHC-0014	ALICATE PRESION C/JEBE 10 PL ASAKI	\N	producto	fijo	11.00	11.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6534	1097	1104	FERHC-0015	APLICADOR SILICONA T/ESQUELETO TRUPER	\N	producto	fijo	7.58	7.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6535	1097	1104	FERHC-0016	ARCO DE SIERRA PROFESIONAL ALTA TENSION 12 TRUPER	\N	producto	fijo	37.51	37.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6536	1097	1104	FERHC-0017	Abrazadera 3/4 Luz S/M	\N	producto	fijo	0.20	0.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6537	1097	1104	FERHC-0018	Aceite P/Maquina Grande 60ml A-1 A-1	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6538	1097	1104	FERHC-0019	Adaptador Pvc 1 PAVCO	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6539	1097	1104	FERHC-0020	Adaptador Pvc 1 PLASTICA	\N	producto	fijo	1.10	1.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6540	1097	1104	FERHC-0021	Adaptador Pvc 1/2 PAVCO	\N	producto	fijo	0.86	0.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6541	1097	1104	FERHC-0022	Adaptador Pvc 1/2 PLASTICA	\N	producto	fijo	0.48	0.48	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6542	1097	1104	FERHC-0023	Adaptador Pvc 3/4 PAVCO	\N	producto	fijo	1.65	1.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6543	1097	1104	FERHC-0024	Adaptador Pvc 3/4 PLASTICA	\N	producto	fijo	0.55	0.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6544	1097	1104	FERHC-0025	Afirmado S/M	\N	producto	fijo	27.00	27.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6545	1097	1104	FERHC-0026	Alambre Galvanizado 16 VELKAS	\N	producto	fijo	7.30	7.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6546	1097	1104	FERHC-0027	Alambre Negro 08 PRODAC	\N	producto	fijo	3.04	3.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6547	1097	1104	FERHC-0028	Alambre Negro 16 PRODAC	\N	producto	fijo	3.04	3.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6548	1097	1104	FERHC-0029	Alambre Puas 200m C&A	\N	producto	fijo	31.40	31.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6549	1097	1104	FERHC-0030	Alambre Tw 14 INDECO	\N	producto	fijo	1.10	1.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6550	1097	1104	FERHC-0031	Alcohol 96░ LUCAS	\N	producto	fijo	8.50	8.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6551	1097	1104	FERHC-0032	Alicate Corte 6 C&A	\N	producto	fijo	7.78	7.78	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6552	1097	1104	FERHC-0033	Alicate Punta 6 C&A	\N	producto	fijo	6.76	6.76	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6553	1097	1104	FERHC-0034	Alicate Universal 8 KAMASA	\N	producto	fijo	9.40	9.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6554	1097	1104	FERHC-0035	Ambientador X Litro Surtido KRIZZAL	\N	producto	fijo	2.25	2.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6555	1097	1104	FERHC-0036	Anillo De Cera C/G METUSA	\N	producto	fijo	4.07	4.07	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6556	1097	1104	FERHC-0037	Aplicador Silicona T/Esqueleto C&A	\N	producto	fijo	3.78	3.78	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6557	1097	1104	FERHC-0038	Arco De Sierra 12pl M/Plastico C&A	\N	producto	fijo	5.72	5.72	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6558	1097	1104	FERHC-0039	Arena Amarilla S/M	\N	producto	fijo	38.00	38.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6559	1097	1104	FERHC-0040	Arenilla Fina Por Lata S/M	\N	producto	fijo	0.35	0.35	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6560	1097	1104	FERHC-0041	Arenilla S/M	\N	producto	fijo	20.00	20.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6561	1097	1104	FERHC-0042	Armella Cerrada 1 1/2 Pl S/M	\N	producto	fijo	0.20	0.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6562	1097	1104	FERHC-0043	Armella Cerrada 1 Pl S/M	\N	producto	fijo	0.17	0.17	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6563	1097	1104	FERHC-0044	BADILEJO M/GOMA 6 KAMASA	\N	producto	fijo	4.81	4.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6564	1097	1104	FERHC-0045	BADILEJO M/MADERA 6 C&A	\N	producto	fijo	2.76	2.76	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6565	1097	1104	FERHC-0046	BADILEJO M/MADERA 7 C&A	\N	producto	fijo	2.57	2.57	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6566	1097	1104	FERHC-0047	BISAGRA 1 1/2 BISA	\N	producto	fijo	0.76	0.76	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6567	1097	1104	FERHC-0048	BORNES PARA BATERIA KAMASA	\N	producto	fijo	3.50	3.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6568	1097	1104	FERHC-0049	BROCHA DE NYLON M/MADERA 1 TUMI	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6569	1097	1104	FERHC-0050	BROCHA DE NYLON M/PLASTICO 1 COPERSA	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6570	1097	1104	FERHC-0051	BROCHA DE NYLON M/PLASTICO 11/2 COPERSA	\N	producto	fijo	1.20	1.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6571	1097	1104	FERHC-0052	BROCHA DE NYLON M/PLASTICO 21/2 COPERSA	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6572	1097	1104	FERHC-0053	BROCHA DE NYLON M/PLASTICO 3 COPERSA	\N	producto	fijo	2.40	2.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6573	1097	1104	FERHC-0054	Base Zincromato 1/4 Galon VELSALIT	\N	producto	fijo	12.50	12.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6574	1097	1104	FERHC-0055	Bisagra 2 1/2 BISA	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6575	1097	1104	FERHC-0056	Bisagra 2 BISA	\N	producto	fijo	0.79	0.79	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6576	1097	1104	FERHC-0057	Bisagra 3 1/2 BISA	\N	producto	fijo	1.42	1.42	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6577	1097	1104	FERHC-0058	Bisagra 3 BISA	\N	producto	fijo	1.16	1.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6578	1097	1104	FERHC-0059	Bisagra 4 BISA	\N	producto	fijo	1.99	1.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6579	1097	1104	FERHC-0060	Bisagra Fija Aluminizada 4 ALUMINIZADA	\N	producto	fijo	4.41	4.41	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6580	1097	1104	FERHC-0061	Broca Para Concreto 1/2-13mm VARIOS	\N	producto	fijo	5.81	5.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6581	1097	1104	FERHC-0062	Broca Para Concreto 1/4-6.5mm VARIOS	\N	producto	fijo	2.60	2.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6582	1097	1104	FERHC-0063	Broca Para Concreto 1/8-3mm VARIOS	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6583	1097	1104	FERHC-0064	Broca Para Concreto 3/16-5mm VARIOS	\N	producto	fijo	2.30	2.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6584	1097	1104	FERHC-0065	Broca Para Concreto 3/8-10mm VARIOS	\N	producto	fijo	3.25	3.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6585	1097	1104	FERHC-0066	Broca Para Concreto 5/16-8mm VARIOS	\N	producto	fijo	3.16	3.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6586	1097	1104	FERHC-0067	Broca Para Concreto 5/32-4mm VARIOS	\N	producto	fijo	1.22	1.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6587	1097	1104	FERHC-0068	Broca Para Fierro Hss 1/16 VARIOS	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6588	1097	1104	FERHC-0069	Broca Para Fierro Hss 1/2 VARIOS	\N	producto	fijo	9.59	9.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6589	1097	1104	FERHC-0070	Broca Para Fierro Hss 1/32 VARIOS	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6590	1097	1104	FERHC-0071	Broca Para Fierro Hss 1/4 VARIOS	\N	producto	fijo	2.03	2.03	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6591	1097	1104	FERHC-0072	Broca Para Fierro Hss 3/32 VARIOS	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6592	1097	1104	FERHC-0073	Broca Para Fierro Hss 3/8 VARIOS	\N	producto	fijo	4.51	4.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6593	1097	1104	FERHC-0074	Broca Para Fierro Hss 5/16 VARIOS	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6594	1097	1104	FERHC-0075	Broca Para Fierro Hss 5/32 VARIOS	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6595	1097	1104	FERHC-0076	Brocha De Nylon M/Madera 1 C&A	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6596	1097	1104	FERHC-0077	Brocha De Nylon M/Madera 1/2 C&A	\N	producto	fijo	0.54	0.54	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6597	1097	1104	FERHC-0078	Brocha De Nylon M/Madera 2 1/2 C&A	\N	producto	fijo	1.98	1.98	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6598	1097	1104	FERHC-0079	Brocha De Nylon M/Madera 2 C&A	\N	producto	fijo	1.57	1.57	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6599	1097	1104	FERHC-0080	Brocha De Nylon M/Madera 3 C&A	\N	producto	fijo	2.62	2.62	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6600	1097	1104	FERHC-0081	Brocha De Nylon M/Madera 3/4 C&A	\N	producto	fijo	0.78	0.78	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6601	1097	1104	FERHC-0082	Brocha De Nylon M/Madera 4 C&A	\N	producto	fijo	3.47	3.47	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6602	1097	1104	FERHC-0083	Brocha De Nylon M/Madera 5 C&A	\N	producto	fijo	4.44	4.44	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6603	1097	1104	FERHC-0084	Bushing Pvc 1 A 1/2 INYECTOPLAST	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6604	1097	1104	FERHC-0085	Bushing Pvc 1 A 3/4 INYECTOPLAST	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6605	1097	1104	FERHC-0086	Bushing Pvc 1/2 A 1/2 TRANSFORMADO	\N	producto	fijo	0.73	0.73	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6606	1097	1104	FERHC-0087	Bushing Pvc 3/4 A 1/2 INYECTOPLAST	\N	producto	fijo	0.60	0.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6607	1097	1104	FERHC-0088	CABEZA DE DUCHA CROMADA CIRCULAR 6 C&A	\N	producto	fijo	17.12	17.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6608	1097	1104	FERHC-0089	CABLE THW-90 + PLUS, 12 AWG INDECO	\N	producto	fijo	2.17	2.17	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6609	1097	1104	FERHC-0090	CABLE THW-90 + PLUS, 14 AWG INDECO	\N	producto	fijo	1.69	1.69	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6610	1097	1104	FERHC-0091	CAJA 12 POLOS P/EMPOTRAR KBA	\N	producto	fijo	20.21	20.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6611	1097	1104	FERHC-0092	CAJA CONCRETO DESAGUE BASE S/M	\N	producto	fijo	8.96	8.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6612	1097	1104	FERHC-0093	CAJA CONCRETO DESAGUE INTERMEDIA S/M	\N	producto	fijo	9.00	9.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6613	1097	1104	FERHC-0094	CAJA CONCRETO DESAGUE PESTAÐA S/M	\N	producto	fijo	9.17	9.17	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6614	1097	1104	FERHC-0095	CAJA CONCRETO DESAGUE TAPA S/M	\N	producto	fijo	8.50	8.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6615	1097	1104	FERHC-0096	CAJA RECTANGULAR UNIVERSAL PARA SOBREPONER HOME LIGHT	\N	producto	fijo	2.43	2.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6616	1097	1104	FERHC-0097	CAL SACO POR 30 KG CAL	\N	producto	fijo	7.00	7.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6617	1097	1104	FERHC-0098	CALAMINA 0.14 X 3.60 X 0.80 ACEROS AREQUIPA	\N	producto	fijo	12.81	12.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6618	1097	1104	FERHC-0099	CALAMINA 0.22X3.60X0.80 PRODAC	\N	producto	fijo	20.66	20.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6619	1097	1104	FERHC-0100	CANALETA 10X20 (3/4) HOME LIGHT	\N	producto	fijo	1.14	1.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6620	1097	1104	FERHC-0101	CARETA PARA SOLDA /CABECERA AMARILLA C&A	\N	producto	fijo	11.81	11.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6621	1097	1104	FERHC-0102	CARRETILLA RHINO AMARILLA RHINO	\N	producto	fijo	100.01	100.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6622	1097	1104	FERHC-0103	CARRETILLA T/BUGGY C/LLANTA REF T/HOJA AZUL 5.5P C&A	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6623	1097	1104	FERHC-0104	CAÐO JARDINERO CIM	\N	producto	fijo	30.80	30.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6624	1097	1104	FERHC-0105	CAÐO JARDINERO PVC BLANCO TOSISAC	\N	producto	fijo	1.85	1.85	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6625	1097	1104	FERHC-0106	CEMENTO ROJO PACASMAYO	\N	producto	fijo	30.60	30.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6626	1097	1104	FERHC-0107	CERA ROJA SILICONEADA X 1LT LUCAS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6627	1097	1104	FERHC-0108	CERRADURA INTERIOR BOLA WINGS	\N	producto	fijo	9.50	9.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6628	1097	1104	FERHC-0109	CERROJO N1 31/2 PL SANSON SANSON	\N	producto	fijo	1.40	1.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6629	1097	1104	FERHC-0110	CERROJO N2 5 PL SANSON SANSON	\N	producto	fijo	1.75	1.75	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6630	1097	1104	FERHC-0111	CHALECO POLIESTER NARANJA C&A	\N	producto	fijo	3.13	3.13	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6631	1097	1104	FERHC-0112	CHAPA BOLA CROMADO C&A	\N	producto	fijo	10.93	10.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6632	1097	1104	FERHC-0113	CINTA AISLANTE 3M 155 GRANDE 3M	\N	producto	fijo	3.53	3.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6633	1097	1104	FERHC-0114	CINTA AISLANTE 3M 155 PEQUEÐO 3M	\N	producto	fijo	1.63	1.63	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6634	1097	1104	FERHC-0115	CINTA AISLANTE 3M 165 19MMX18.3M AMARILLO 3M	\N	producto	fijo	4.31	4.31	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6635	1097	1104	FERHC-0116	CINTA AISLANTE 3M 165 19MMX18.3M AZUL 3M	\N	producto	fijo	4.31	4.31	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6636	1097	1104	FERHC-0117	CINTA EMBALAJE 2 X 200 YDS KNAUF	\N	producto	fijo	6.18	6.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6637	1097	1104	FERHC-0118	CINTA REFLECTIVA BLANCO/ ROJO 2 PLG KNAUF	\N	producto	fijo	1.83	1.83	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
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
6638	1097	1104	FERHC-0119	CLAVO ACERO 1 1/2-3.5X50MM CAJ-1KG (416UNI) VARIOS	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6639	1097	1104	FERHC-0120	CLAVO ACERO 1(CAJA 345 X 1KG) VARIOS	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6640	1097	1104	FERHC-0121	CLAVO ACERO 2 1/2-3.5X60MM CAJ-0.50KG(72 UNID) VARIOS	\N	producto	fijo	0.07	0.07	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6641	1097	1104	FERHC-0122	CLAVO ACERO 2-3.5X50MM CAJ-1KG (247UNI) VARIOS	\N	producto	fijo	0.04	0.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6642	1097	1104	FERHC-0123	CLAVO ACERO 3-4.3X75MM CAJ-1KG (104UNI) VARIOS	\N	producto	fijo	0.09	0.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6643	1097	1104	FERHC-0124	CLAVO P/MADERA 1 1/2 CONFER CONFER	\N	producto	fijo	4.40	4.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6644	1097	1104	FERHC-0125	CLAVO P/MADERA 5 CONFER CONFER	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6645	1097	1104	FERHC-0126	CLAVO PARA CALAMINA C/ARANDELA C&A	\N	producto	fijo	4.92	4.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6646	1097	1104	FERHC-0127	CODO PVC 1 SP PLASTICA	\N	producto	fijo	1.79	1.79	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6647	1097	1104	FERHC-0128	CODO PVC 1/2 SP PLASTICA	\N	producto	fijo	0.74	0.74	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6648	1097	1104	FERHC-0129	CODO PVC MIXTO 1/2 PLASTICA	\N	producto	fijo	0.64	0.64	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6649	1097	1104	FERHC-0130	CODO PVC MIXTO 3/4 PAVCO	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6650	1097	1104	FERHC-0131	CODO SAL 4 PAVCO	\N	producto	fijo	6.84	6.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6651	1097	1104	FERHC-0132	CODO SAL 4 PLASTICA	\N	producto	fijo	3.56	3.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6652	1097	1104	FERHC-0133	COLA SINTETICA 1 KG LOSARO	\N	producto	fijo	6.25	6.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6653	1097	1104	FERHC-0134	COLA SINTETICA 1/2 KG VELSALIT	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6654	1097	1104	FERHC-0135	COMBA C/MANGO FIBRA DE VIDRIO 8LBS C&A	\N	producto	fijo	32.83	32.83	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6655	1097	1104	FERHC-0136	CORDON VULCANIZADO 2X12 NMT(SJT-0) INDECO INDECO	\N	producto	fijo	5.66	5.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6656	1097	1104	FERHC-0137	CRUZETA 2 MM 100UND VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6657	1097	1104	FERHC-0138	CRUZETAS 1 MM 100UND VARIOS	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6658	1097	1104	FERHC-0139	CUCHILLO CARTONERO CUTER EUROTOOLS	\N	producto	fijo	0.50	0.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6659	1097	1104	FERHC-0140	CURVA LUZ SAP 1 PAVCO	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6660	1097	1104	FERHC-0141	Cabeza Ducha Cromada Peque±a C&A	\N	producto	fijo	8.58	8.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6661	1097	1104	FERHC-0142	Cabeza Ducha pvc grande Completa S/M	\N	producto	fijo	2.12	2.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6662	1097	1104	FERHC-0143	Cable Mellizo 2X18 INDECO	\N	producto	fijo	1.23	1.23	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6663	1097	1104	FERHC-0144	Cable Mellizo 2x16 INDECO	\N	producto	fijo	2.25	2.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6664	1097	1104	FERHC-0145	Caja 2 Polos P/Empotrar KBA	\N	producto	fijo	4.43	4.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6665	1097	1104	FERHC-0146	Caja 2 Polos P/Empotrar XACE	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6666	1097	1104	FERHC-0147	Caja 4 Polos P/Empotrar KBA	\N	producto	fijo	10.40	10.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6667	1097	1104	FERHC-0148	Caja 6 Polos P/Empotrar KBA	\N	producto	fijo	11.75	11.75	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6668	1097	1104	FERHC-0149	Caja 8 Polos P/Empotrar KBA	\N	producto	fijo	15.51	15.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6669	1097	1104	FERHC-0150	Caja Concreto Para Agua CONCRETO	\N	producto	fijo	13.00	13.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6670	1097	1104	FERHC-0151	Caja De Pase 100 X 100 X 70 XACE	\N	producto	fijo	4.01	4.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6671	1097	1104	FERHC-0152	Caja De Pase 150 X 150 X 80 XACE	\N	producto	fijo	6.50	6.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6672	1097	1104	FERHC-0153	Caja De Pase 200 X 200 X 80 XACE	\N	producto	fijo	13.99	13.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6673	1097	1104	FERHC-0154	Caja De Pase Liso 10.2 X 10.2 X 5.5 STECK	\N	producto	fijo	5.70	5.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6674	1097	1104	FERHC-0155	Caja De Pase Liso 23.4 X 17.4 X 9 STECK	\N	producto	fijo	18.01	18.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6675	1097	1104	FERHC-0156	Caja Octagonal AMERICA	\N	producto	fijo	0.38	0.38	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6676	1097	1104	FERHC-0157	Caja Octagonal PAVCO	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6677	1097	1104	FERHC-0158	Caja Piramide P/Cuchilla Sobreponer 2 Polos S/M	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6678	1097	1104	FERHC-0159	Caja Rectangular AMERICA	\N	producto	fijo	0.33	0.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6679	1097	1104	FERHC-0160	Caja Rectangular PAVCO	\N	producto	fijo	1.45	1.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6680	1097	1104	FERHC-0161	Calamina 0.14x1.80x0.80 PRODAC	\N	producto	fijo	7.00	7.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6681	1097	1104	FERHC-0162	Calamina Traslucida Gran Onda TECHITO	\N	producto	fijo	83.00	83.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6682	1097	1104	FERHC-0163	Calamina Traslucida Perfil 4 TECHITO	\N	producto	fijo	31.00	31.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6683	1097	1104	FERHC-0164	Calamina Traslusida onda 76 1.80x0.84 Liviana TECHITO	\N	producto	fijo	17.50	17.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6684	1097	1104	FERHC-0165	Calamina Traslusida onda 76 3.60x0.84 Liviana TECHITO	\N	producto	fijo	35.20	35.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6685	1097	1104	FERHC-0166	Camara P/Llanta Carretilla TRUPER	\N	producto	fijo	7.50	7.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6686	1097	1104	FERHC-0167	Canaleta 10x15 HOME LIGHT	\N	producto	fijo	1.09	1.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6687	1097	1104	FERHC-0168	Candado Dorado 32 Mm ECONOMICA	\N	producto	fijo	2.28	2.28	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6688	1097	1104	FERHC-0169	Candado Dorado 38mm ECONOMICA	\N	producto	fijo	2.84	2.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6689	1097	1104	FERHC-0170	Candado Dorado 50mm ECONOMICA	\N	producto	fijo	3.96	3.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6690	1097	1104	FERHC-0171	Candado Dorado 63 Mm ECONOMICA	\N	producto	fijo	5.97	5.97	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6691	1097	1104	FERHC-0172	Capuchon Pvc Rojo VARIOS	\N	producto	fijo	0.09	0.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6692	1097	1104	FERHC-0173	Ca±o Botadero Bronceado Liso C&A	\N	producto	fijo	4.91	4.91	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6693	1097	1104	FERHC-0174	Ca±o Botadero M/Redondo Metal C&A	\N	producto	fijo	4.44	4.44	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6694	1097	1104	FERHC-0175	Ca±o Jardinero Bronceado Liso C&A	\N	producto	fijo	5.78	5.78	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6695	1097	1104	FERHC-0176	Ca±o Jardinero M/Rojo Metal C&A	\N	producto	fijo	6.94	6.94	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6696	1097	1104	FERHC-0177	Ca±o Jardinero Naranja PCP	\N	producto	fijo	14.40	14.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6697	1097	1104	FERHC-0178	Ca±o P/Lavanderia 1/2 C&A	\N	producto	fijo	8.97	8.97	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6698	1097	1104	FERHC-0179	Cemento Azul Antisalitre PACASMAYO	\N	producto	fijo	33.21	33.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6699	1097	1104	FERHC-0180	Cemento Blanco KOLORCIX	\N	producto	fijo	2.04	2.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6700	1097	1104	FERHC-0181	Cemento VARIOS	\N	producto	fijo	0.60	0.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6701	1097	1104	FERHC-0182	Cerradura 226 FORTE	\N	producto	fijo	65.01	65.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6702	1097	1104	FERHC-0183	Cerradura 240 FORTE	\N	producto	fijo	64.88	64.88	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6703	1097	1104	FERHC-0184	Cerradura 444 Barra TRAVEX	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6704	1097	1104	FERHC-0185	Cerrojo N4 8pl SANSON	\N	producto	fijo	5.66	5.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6705	1097	1104	FERHC-0186	Cinta Aislante Grande TECNOFAN	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6706	1097	1104	FERHC-0187	Cinta Aislante Peque±o TECNOFAN	\N	producto	fijo	0.96	0.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6707	1097	1104	FERHC-0188	Cinta Masketing 1 PEGAFAN	\N	producto	fijo	2.99	2.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6708	1097	1104	FERHC-0189	Cinta Masketing 1/2 PEGAFAN	\N	producto	fijo	1.53	1.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6709	1097	1104	FERHC-0190	Cinta Masketing 2 PEGAFAN	\N	producto	fijo	5.76	5.76	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6710	1097	1104	FERHC-0191	Cinta Masketing 3/4 PEGAFAN	\N	producto	fijo	2.10	2.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6711	1097	1104	FERHC-0192	Cinta P/Medir Plastica 50 Metros ASAKI	\N	producto	fijo	17.50	17.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6712	1097	1104	FERHC-0193	Cinta Teflon 1/2 X 12m C&A	\N	producto	fijo	0.34	0.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6713	1097	1104	FERHC-0194	Cinta Teflon P/Gas Amarilla 1/2 Magnun MAGNUN	\N	producto	fijo	0.74	0.74	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6714	1097	1104	FERHC-0195	Cizalla 12 M/Tubular C&A C&A	\N	producto	fijo	17.41	17.41	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6715	1097	1104	FERHC-0196	Cizalla 18 M/Tubular C&A C&A	\N	producto	fijo	22.04	22.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6716	1097	1104	FERHC-0197	Clavo Acero 4-4.5X100mm Caj X 81unid X Kg VARIOS	\N	producto	fijo	0.13	0.13	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6717	1097	1104	FERHC-0198	Clavo Acero 5 P Caj X 1 Kg 58 Unid VARIOS	\N	producto	fijo	0.22	0.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6718	1097	1104	FERHC-0199	Clavo P/Madera 1 Confer CONFER	\N	producto	fijo	4.80	4.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6719	1097	1104	FERHC-0200	Clavo P/Madera 2 1/2 Confer CONFER	\N	producto	fijo	3.43	3.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6720	1097	1104	FERHC-0201	Clavo P/Madera 2 Confer CONFER	\N	producto	fijo	3.60	3.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6721	1097	1104	FERHC-0202	Clavo P/Madera 3 Confer CONFER	\N	producto	fijo	3.67	3.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6722	1097	1104	FERHC-0203	Clavo P/Madera 4 Confer CONFER	\N	producto	fijo	3.67	3.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6723	1097	1104	FERHC-0204	Clavo P/Madera 6 Confer CONFER	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6724	1097	1104	FERHC-0205	Clavo P/Madera 7 Confer CONFER	\N	producto	fijo	5.89	5.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6725	1097	1104	FERHC-0206	Clavo Para Calamina C&A C&A	\N	producto	fijo	5.61	5.61	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6726	1097	1104	FERHC-0207	Codo Bronce 1/2 VALMAX	\N	producto	fijo	2.38	2.38	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6727	1097	1104	FERHC-0208	Codo Cpvc 1/2 Sp PAVCO	\N	producto	fijo	0.85	0.85	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6728	1097	1104	FERHC-0209	Codo Cpvc 3/4 Sp PAVCO	\N	producto	fijo	2.12	2.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6729	1097	1104	FERHC-0210	Codo Fierro G. 1 FIERRO G	\N	producto	fijo	2.90	2.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6730	1097	1104	FERHC-0211	Codo Fierro G. 1/2 FIERRO G	\N	producto	fijo	1.40	1.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6731	1097	1104	FERHC-0212	Codo Fierro G. 3/4 FIERRO G	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6732	1097	1104	FERHC-0213	Codo Pvc 1 C/Rosca PAVCO	\N	producto	fijo	4.45	4.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6733	1097	1104	FERHC-0214	Codo Pvc 1 C/Rosca PLASTICA	\N	producto	fijo	2.24	2.24	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6734	1097	1104	FERHC-0215	Codo Pvc 1 Sp PAVCO	\N	producto	fijo	2.90	2.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6735	1097	1104	FERHC-0216	Codo Pvc 1/2 C/Rosca NICOL	\N	producto	fijo	0.50	0.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6736	1097	1104	FERHC-0217	Codo Pvc 1/2 C/Rosca PAVCO	\N	producto	fijo	1.55	1.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6737	1097	1104	FERHC-0218	Codo Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.40	1.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6738	1097	1104	FERHC-0219	Codo Pvc 3/4 C/Rosca PAVCO	\N	producto	fijo	2.90	2.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6739	1097	1104	FERHC-0220	Codo Pvc 3/4 C/Rosca PLASTICA	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6740	1097	1104	FERHC-0221	Codo Pvc 3/4 SP PLASTICA	\N	producto	fijo	1.22	1.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6741	1097	1104	FERHC-0222	Codo Pvc 3/4 Sp PAVCO	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6742	1097	1104	FERHC-0223	Codo Pvc Mixto 1 INYECTOPLAST	\N	producto	fijo	1.60	1.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6743	1097	1104	FERHC-0224	Codo Pvc Mixto 1/2 PAVCO	\N	producto	fijo	1.56	1.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6744	1097	1104	FERHC-0225	Codo Sal 2 PAVCO	\N	producto	fijo	1.66	1.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6745	1097	1104	FERHC-0226	Codo Sal 2 PLASTICA	\N	producto	fijo	0.86	0.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6746	1097	1104	FERHC-0227	Codo Sal 3 PAVCO	\N	producto	fijo	5.35	5.35	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6747	1097	1104	FERHC-0228	Codo Sal 3 PLASTICA	\N	producto	fijo	3.17	3.17	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6748	1097	1104	FERHC-0229	Codo Sal 4x2 PAVCO	\N	producto	fijo	9.18	9.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6749	1097	1104	FERHC-0230	Codo Sal 4x2 PLASTICA	\N	producto	fijo	5.30	5.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6750	1097	1104	FERHC-0231	Cola Sintetica Clasica TEKNOCOLA	\N	producto	fijo	9.52	9.52	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6751	1097	1104	FERHC-0232	Cola Sintetica VELSALIT	\N	producto	fijo	4.84	4.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6752	1097	1104	FERHC-0233	Comba C/Mango Madera 4lbs C&A	\N	producto	fijo	15.16	15.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6753	1097	1104	FERHC-0234	Comba De Goma 500 Gr M/Madera VARIOS	\N	producto	fijo	7.00	7.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6754	1097	1104	FERHC-0235	Confitillo Fino S/M	\N	producto	fijo	45.01	45.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6755	1097	1104	FERHC-0236	Curva Sel 1 PAVCO	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6756	1097	1104	FERHC-0237	Curva Sel 3/4 PAVCO	\N	producto	fijo	0.31	0.31	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6757	1097	1104	FERHC-0238	Curva Sel 3/4 PLASTICA	\N	producto	fijo	0.19	0.19	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6758	1097	1104	FERHC-0239	Curva Sel 5/8 PAVCO	\N	producto	fijo	0.35	0.35	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6759	1097	1104	FERHC-0240	DESARMADOR REVERSIBLE 5 X 70 C&A	\N	producto	fijo	0.34	0.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6760	1097	1104	FERHC-0241	DESARMADOR REVERSIBLE 6X35 C&A	\N	producto	fijo	1.09	1.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6761	1097	1104	FERHC-0242	DESARMADOR REVERSIBLE M/ERGON 6 X 100MM C&A	\N	producto	fijo	3.30	3.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6762	1097	1104	FERHC-0243	DIAFRACMA O SAPITO SANY	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6763	1097	1104	FERHC-0244	DISCO CORTE FIERRO 4 1/2 3M	\N	producto	fijo	3.10	3.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6764	1097	1104	FERHC-0245	DISCO CORTE MADERA 4 1/2 24T KAMASA	\N	producto	fijo	4.92	4.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6765	1097	1104	FERHC-0246	DISCO CORTE MADERA 4 1/2 40T KAMASA	\N	producto	fijo	6.47	6.47	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6766	1097	1104	FERHC-0247	DISCO CORTE MADERA 7 24T KAMASA	\N	producto	fijo	10.50	10.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6767	1097	1104	FERHC-0248	DISCO DE LIJAS FLAP PULIR P60 VARGYOV	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6768	1097	1104	FERHC-0249	DISCO PLATO C/LIJA 4 1/2 VARGYOV	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6769	1097	1104	FERHC-0250	DISCO TRONZADORA 14 DEWALT	\N	producto	fijo	14.50	14.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6770	1097	1104	FERHC-0251	DRIZA POLIPROPILENO 1/4 AZUL(42MT X 5KG) C&A	\N	producto	fijo	0.32	0.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6771	1097	1104	FERHC-0252	DRIZA POLIPROPILENO 1/8 AZUL (196 MTS1KG) C&A	\N	producto	fijo	0.11	0.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6772	1097	1104	FERHC-0253	DRIZA POLIPROPILENO 5/16 BLANCO C&A	\N	producto	fijo	0.61	0.61	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6773	1097	1104	FERHC-0254	DRIZA POLIPROPILENO 7/16 BLANCO C&A	\N	producto	fijo	0.89	0.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6774	1097	1104	FERHC-0255	Desague Para Lavadero Con Coleta Pvc PICETTI	\N	producto	fijo	7.00	7.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6775	1097	1104	FERHC-0256	Desarmador Reversible 5 X 75 C&A	\N	producto	fijo	0.85	0.85	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6776	1097	1104	FERHC-0257	Desarmador Reversible 5x65 WINGS	\N	producto	fijo	1.11	1.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6777	1097	1104	FERHC-0258	Desarmador Reversible 6 X 90 C&A	\N	producto	fijo	1.12	1.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6778	1097	1104	FERHC-0259	Desatorador Para Water S/M	\N	producto	fijo	2.38	2.38	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6779	1097	1104	FERHC-0260	Disco Corte Concreto 4 1/2 Continuo KAMASA	\N	producto	fijo	5.39	5.39	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6780	1097	1104	FERHC-0261	Disco Corte Concreto 4 1/2 KAMASA	\N	producto	fijo	5.70	5.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6781	1097	1104	FERHC-0262	Disco Corte Concreto 7 KAMASA	\N	producto	fijo	14.92	14.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6782	1097	1104	FERHC-0263	Disco Corte Fierro 4 1/2 DEWALT	\N	producto	fijo	2.60	2.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6783	1097	1104	FERHC-0264	Disco Corte Fierro 4 1/2 NORTON	\N	producto	fijo	2.19	2.19	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6784	1097	1104	FERHC-0265	Disco Corte Fierro 7 3M	\N	producto	fijo	4.85	4.85	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6785	1097	1104	FERHC-0266	Disco Corte Fierro 7 DEWALT	\N	producto	fijo	4.68	4.68	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6786	1097	1104	FERHC-0267	Disco Desbaste 4 1/2 DEWALT	\N	producto	fijo	3.68	3.68	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6787	1097	1104	FERHC-0268	Disco Desbaste 7 DEWALT	\N	producto	fijo	7.80	7.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6788	1097	1104	FERHC-0269	ENCHUFE INDUSTRIAL C/TIERRA HOME LIGHT	\N	producto	fijo	1.43	1.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6789	1097	1104	FERHC-0270	ENCHUFE INDUSTRIAL S/TIERRA HOME LIGHT	\N	producto	fijo	2.11	2.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6790	1097	1104	FERHC-0271	ESCOBILLON 41CM HUDE	\N	producto	fijo	11.56	11.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6791	1097	1104	FERHC-0272	ESCOBILLON ITALIANA	\N	producto	fijo	7.50	7.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6792	1097	1104	FERHC-0273	ESCUADRA FIERRO 6 PLG UYUSTOOLS	\N	producto	fijo	4.47	4.47	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6793	1097	1104	FERHC-0274	ESCUADRA FIERRO 8 PLG UYUSTOOLS	\N	producto	fijo	4.84	4.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6794	1097	1104	FERHC-0275	ESPATULA M/GOMA 2 PRO C&A	\N	producto	fijo	1.89	1.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6795	1097	1104	FERHC-0276	ESPONJA DULOPILLO DOLOPIO	\N	producto	fijo	0.25	0.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6796	1097	1104	FERHC-0277	EXTENSION C/3 TOMAS C/FOCO 10M HOME LIGHT	\N	producto	fijo	12.21	12.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6797	1097	1104	FERHC-0278	EXTENSION C/3 TOMAS C/FOCO 3M HOME LIGHT	\N	producto	fijo	6.56	6.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6798	1097	1104	FERHC-0279	EXTENSION C/3 TOMAS C/FOCO 5M HOME LIGHT	\N	producto	fijo	7.65	7.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6799	1097	1104	FERHC-0280	Electronivel 3 Mts ROTOPLAST	\N	producto	fijo	51.50	51.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6800	1097	1104	FERHC-0281	Enchufe De Colores EUROLITE	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6801	1097	1104	FERHC-0282	Enchufe Negro PLANO HOME LIGHT	\N	producto	fijo	0.47	0.47	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6802	1097	1104	FERHC-0283	Escobilla Fierro Acerado 4x14 C&A	\N	producto	fijo	1.77	1.77	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6803	1097	1104	FERHC-0284	Escobilla copa 3 Trensado KHOPPER	\N	producto	fijo	3.49	3.49	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6804	1097	1104	FERHC-0285	Escuadra 12 ASAKI	\N	producto	fijo	8.00	8.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6805	1097	1104	FERHC-0286	Esmalte 1/16 Amarillo Md VARIOS	\N	producto	fijo	4.80	4.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6806	1097	1104	FERHC-0287	Esmalte 1/16 Azul Naval VARIOS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6807	1097	1104	FERHC-0288	Esmalte 1/16 Bayo VARIOS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6808	1097	1104	FERHC-0289	Esmalte 1/16 Crema VARIOS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6809	1097	1104	FERHC-0290	Esmalte 1/16 Gris Claro VARIOS	\N	producto	fijo	5.00	5.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6810	1097	1104	FERHC-0291	Esmalte 1/16 Gris Oscuro VARIOS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6811	1097	1104	FERHC-0292	Esmalte 1/16 Nogal VARIOS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6812	1097	1104	FERHC-0293	Esmalte 1/16 Verde Cromo VARIOS	\N	producto	fijo	4.90	4.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6813	1097	1104	FERHC-0294	Esmalte 1/32 Amarillo Md VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6814	1097	1104	FERHC-0295	Esmalte 1/32 Azul Electrico VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6815	1097	1104	FERHC-0296	Esmalte 1/32 Azul Ultramar VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6816	1097	1104	FERHC-0297	Esmalte 1/32 Bayo VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6817	1097	1104	FERHC-0298	Esmalte 1/32 Blanco VARIOS	\N	producto	fijo	3.28	3.28	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6818	1097	1104	FERHC-0299	Esmalte 1/32 Celeste VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6819	1097	1104	FERHC-0300	Esmalte 1/32 Gris Claro VARIOS	\N	producto	fijo	3.29	3.29	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6820	1097	1104	FERHC-0301	Esmalte 1/32 Gris Oscuro VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6821	1097	1104	FERHC-0302	Esmalte 1/32 Naranja VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6822	1097	1104	FERHC-0303	Esmalte 1/32 Negro VARIOS	\N	producto	fijo	3.20	3.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6823	1097	1104	FERHC-0304	Esmalte 1/32 Nogal VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6824	1097	1104	FERHC-0305	Esmalte 1/32 Verde Esmeralda VARIOS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6825	1097	1104	FERHC-0306	Esmalte 1/4 Amarillo Md VARIOS	\N	producto	fijo	10.01	10.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6826	1097	1104	FERHC-0307	Esmalte 1/4 Azul Electrico VARIOS	\N	producto	fijo	10.01	10.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6827	1097	1104	FERHC-0308	Esmalte 1/4 Bayo VARIOS	\N	producto	fijo	9.99	9.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6828	1097	1104	FERHC-0309	Esmalte 1/4 Blanco VARIOS	\N	producto	fijo	11.00	11.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6829	1097	1104	FERHC-0310	Esmalte 1/4 Caoba VARIOS	\N	producto	fijo	10.50	10.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6830	1097	1104	FERHC-0311	Esmalte 1/4 Celeste VARIOS	\N	producto	fijo	10.01	10.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6831	1097	1104	FERHC-0312	Esmalte 1/4 Gris Claro VARIOS	\N	producto	fijo	9.99	9.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6832	1097	1104	FERHC-0313	Esmalte 1/4 Gris Oscuro VARIOS	\N	producto	fijo	10.07	10.07	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6833	1097	1104	FERHC-0314	Esmalte 1/4 Negro VARIOS	\N	producto	fijo	10.50	10.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6834	1097	1104	FERHC-0315	Esmalte 1/4 Verde Cromo VARIOS	\N	producto	fijo	10.01	10.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6835	1097	1104	FERHC-0316	Esmalte 1/8 Bayo VARIOS	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6836	1097	1104	FERHC-0317	Esmalte 1/8 Celeste VARIOS	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6837	1097	1104	FERHC-0318	Esmalte 1/8 Rojo Bermellon VARIOS	\N	producto	fijo	7.00	7.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6838	1097	1104	FERHC-0319	Esmalte 1/8 Rojo Oxido VARIOS	\N	producto	fijo	6.50	6.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6839	1097	1104	FERHC-0320	Esmalte 1/8 Verde Cromo VARIOS	\N	producto	fijo	6.80	6.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6840	1097	1104	FERHC-0321	Espatula M/Madera 1 1/2 C&A	\N	producto	fijo	1.68	1.68	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6841	1097	1104	FERHC-0322	Espatula M/Madera 2 C&A	\N	producto	fijo	1.53	1.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6842	1097	1104	FERHC-0323	Espatula M/Madera 3 C&A	\N	producto	fijo	1.83	1.83	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6843	1097	1104	FERHC-0324	Eternit Gran Onda 3.05m X 1.10m ETERNIT	\N	producto	fijo	60.92	60.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6844	1097	1104	FERHC-0325	Eternit Perfil 4 3.05 X 1.10 ETERNIT	\N	producto	fijo	51.00	51.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6845	1097	1104	FERHC-0326	Extension C/3 Tomas C/Foco 15m HOME LIGHT	\N	producto	fijo	15.42	15.42	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6846	1097	1104	FERHC-0327	FIERRO 6MM PRODAC	\N	producto	fijo	6.07	6.07	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6847	1097	1104	FERHC-0328	FOCO LED 12 W SWIFT	\N	producto	fijo	1.82	1.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6848	1097	1104	FERHC-0329	FOCO LED 18 W SWIFT	\N	producto	fijo	3.29	3.29	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6849	1097	1104	FERHC-0330	FOCO LED 20 W SWIFT	\N	producto	fijo	4.77	4.77	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6850	1097	1104	FERHC-0331	FOCO LED 9 W SWIFT	\N	producto	fijo	2.54	2.54	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6851	1097	1104	FERHC-0332	FOCO LED DECORATIVO 46 W T/HELICE L/DIA HOME LIGHT	\N	producto	fijo	18.57	18.57	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6852	1097	1104	FERHC-0333	FOCO LED GU 5.3-T11 5W 6500K SWIFT	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6853	1097	1104	FERHC-0334	FOCO LED UFO CIRCULAR 24 W L/DIA HOME LIGHT	\N	producto	fijo	12.64	12.64	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6854	1097	1104	FERHC-0335	FOCO LED UFO CIRCULAR 40 W L/DIA HOME LIGHT	\N	producto	fijo	15.45	15.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6855	1097	1104	FERHC-0336	FORTACHO DE PLASTICO 20 X 15 S/M	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6856	1097	1104	FERHC-0337	FORTACHO DE PLASTICO 25 X 17 S/M	\N	producto	fijo	8.00	8.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6857	1097	1104	FERHC-0338	FORTACHO DE PLASTICO 27 X 18 S/M	\N	producto	fijo	9.65	9.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6858	1097	1104	FERHC-0339	FORTACHO DE PLASTICO 30 X 20 S/M	\N	producto	fijo	10.01	10.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6859	1097	1104	FERHC-0340	FORTACHO DE PLASTICO 38 X 24 S/M	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6860	1097	1104	FERHC-0341	FORTACHO DE PLASTICO 40 X 26 S/M	\N	producto	fijo	15.00	15.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6861	1097	1104	FERHC-0342	FORTACHO DE PLASTICO 6 X 30 S/M	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6862	1097	1104	FERHC-0343	FRAGUA BEIGGE SANSON	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6863	1097	1104	FERHC-0344	FRAGUA BLANCO SANSON	\N	producto	fijo	3.65	3.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6864	1097	1104	FERHC-0345	FRAGUA CELESTE SANSON	\N	producto	fijo	4.00	4.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6865	1097	1104	FERHC-0346	FRAGUA COLORES VARIOS QUIZUD	\N	producto	fijo	0.11	0.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6866	1097	1104	FERHC-0347	FRAGUA CUERO SANSON	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6867	1097	1104	FERHC-0348	FRAGUA GRIS PLATA SANSON	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6868	1097	1104	FERHC-0349	FRAGUA HUESO SANSON	\N	producto	fijo	3.59	3.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6869	1097	1104	FERHC-0350	FRAGUA MARRON SANSON	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6870	1097	1104	FERHC-0351	FRAGUA NEGRA SANSON	\N	producto	fijo	5.20	5.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6871	1097	1104	FERHC-0352	Fierro 1/2 SIDERPERU	\N	producto	fijo	32.32	32.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6872	1097	1104	FERHC-0353	Fierro 12 MM SIDERPERU	\N	producto	fijo	29.67	29.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6873	1097	1104	FERHC-0354	Fierro 3/4 SIDERPERU	\N	producto	fijo	74.27	74.27	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6874	1097	1104	FERHC-0355	Fierro 3/8 SIDERPERU	\N	producto	fijo	18.14	18.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6875	1097	1104	FERHC-0356	Fierro 5/8 SIDERPERU	\N	producto	fijo	49.98	49.98	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6876	1097	1104	FERHC-0357	Fierro 6 MM SIDERPERU	\N	producto	fijo	7.26	7.26	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6877	1097	1104	FERHC-0358	Fierro 8 MM SIDERPERU	\N	producto	fijo	13.02	13.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6878	1097	1104	FERHC-0359	Foco 36 W PHELIPS	\N	producto	fijo	4.50	4.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6879	1097	1104	FERHC-0360	Foco 42 W PHELIPS	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6880	1097	1104	FERHC-0361	Foco 85 W PHELIX	\N	producto	fijo	11.00	11.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6881	1097	1104	FERHC-0362	Foco Led 12 W PHILIPS	\N	producto	fijo	4.35	4.35	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6882	1097	1104	FERHC-0363	Foco Led 15 W SWIFT	\N	producto	fijo	2.34	2.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6883	1097	1104	FERHC-0364	Foco Led 7 W SWIFT	\N	producto	fijo	1.32	1.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6884	1097	1104	FERHC-0365	Foco Led Nicroico KROSL	\N	producto	fijo	5.00	5.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6885	1097	1104	FERHC-0366	GANCHO J 1/4 X 2 1/2 S/M	\N	producto	fijo	0.25	0.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6886	1097	1104	FERHC-0367	GANCHO J 1/4 X 2 S/M	\N	producto	fijo	0.22	0.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6887	1097	1104	FERHC-0368	GANCHO J 1/4 X 3 S/M	\N	producto	fijo	0.30	0.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6888	1097	1104	FERHC-0369	GANCHO J 1/4 X 5 S/M	\N	producto	fijo	0.40	0.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6889	1097	1104	FERHC-0370	GLOSS DE 1/4 BLANCO GLOSS	\N	producto	fijo	20.79	20.79	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6890	1097	1104	FERHC-0371	GUANTE TELA COLORES VARIOS FERRAWY	\N	producto	fijo	2.68	2.68	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6891	1097	1104	FERHC-0372	GUANTES DE HILO ANTICORTE ECON FERRAWY	\N	producto	fijo	1.96	1.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6892	1097	1104	FERHC-0373	Gancho Cuadrado 1/4 x 1 1/2 x 6 1/4 G.O S/M	\N	producto	fijo	0.59	0.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6893	1097	1104	FERHC-0374	Grapa Para Cable Blanco 7mmx100unid UYUSTOOLS	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6894	1097	1104	FERHC-0375	Grapas Alambre Pua C&A	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6895	1097	1104	FERHC-0376	Gru±a De Canto TIGRE	\N	producto	fijo	3.50	3.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6896	1097	1104	FERHC-0377	Gru±a De Centro TIGRE	\N	producto	fijo	3.50	3.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6897	1097	1104	FERHC-0378	Guante Conveniente Talla M Clasica VIRUTEX	\N	producto	fijo	3.01	3.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6898	1097	1104	FERHC-0379	Guante Conveniente Talla S Clasica VIRUTEX	\N	producto	fijo	3.29	3.29	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6899	1097	1104	FERHC-0380	HOZ DENTADA 16 C&A	\N	producto	fijo	5.68	5.68	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6900	1097	1104	FERHC-0381	Hisopo C/Base S/M	\N	producto	fijo	3.96	3.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6901	1097	1104	FERHC-0382	INFLADOR P/NEUMATICOS 23PL TRUPER	\N	producto	fijo	19.97	19.97	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6902	1097	1104	FERHC-0383	INSECTICIDA AZUL 3 EN 1 COCK BRAND	\N	producto	fijo	7.33	7.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6903	1097	1104	FERHC-0384	INSECTICIDA ROJO 3 EN 1 COCK BRAND	\N	producto	fijo	7.33	7.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6904	1097	1104	FERHC-0385	INTERRUPTOR COLGANTE(AEREO) HOME LIGHT	\N	producto	fijo	1.09	1.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6905	1097	1104	FERHC-0386	INTERRUPTOR DOBLE 3VIAS P/CONMUTACION HOME LIGHT	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6906	1097	1104	FERHC-0387	INTERRUPTOR SIMPLE P/SOBREPONER HOME LIGHT	\N	producto	fijo	1.09	1.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6907	1097	1104	FERHC-0388	INTERRUPTOR TRIPLE P/EMPOTRADO FERRAWY	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6908	1097	1104	FERHC-0389	INTERRUPTOR-TOMACPRRIENTE MIXTO P/EMP HOME LIGHT	\N	producto	fijo	2.14	2.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6909	1097	1104	FERHC-0390	Interruptor Doble P/Conmutacion TICINO	\N	producto	fijo	20.38	20.38	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6910	1097	1104	FERHC-0391	Interruptor Doble P/Empotrado HOME LIGHT	\N	producto	fijo	1.89	1.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6911	1097	1104	FERHC-0392	Interruptor Doble P/Empotrado TICINO	\N	producto	fijo	12.86	12.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6912	1097	1104	FERHC-0393	Interruptor Simple 3vias P/Conmutacion HOME LIGHT	\N	producto	fijo	1.46	1.46	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6913	1097	1104	FERHC-0394	Interruptor Simple P/Empotrado HOME LIGHT	\N	producto	fijo	1.42	1.42	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6914	1097	1104	FERHC-0395	Interruptor Simple P/Empotrado TICINO	\N	producto	fijo	9.45	9.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6915	1097	1104	FERHC-0396	Interruptor Triple P/Empotrado HOME LIGHT	\N	producto	fijo	2.67	2.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6916	1097	1104	FERHC-0397	Interruptor Triple P/Empotrado TICINO	\N	producto	fijo	20.10	20.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6917	1097	1104	FERHC-0398	Kresso 1L KRIZZAL	\N	producto	fijo	3.34	3.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6918	1097	1104	FERHC-0399	LAVADERO 2 POZAS 1.10MT X 48CM X 0.80 S/M	\N	producto	fijo	111.33	111.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6919	1097	1104	FERHC-0400	LAVADERO 75 X 40 CM ALUMINIO 1 POZA S/M	\N	producto	fijo	32.50	32.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6920	1097	1104	FERHC-0401	LAVADERO 96 X 43 CM ALUMINIO 1 POZA S/M	\N	producto	fijo	37.50	37.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6921	1097	1104	FERHC-0402	LENTE SEGURIDAD NEGRO ACHINADO ASAKI	\N	producto	fijo	2.04	2.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6922	1097	1104	FERHC-0403	LIJA AL AGUA N░ 1200 ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6923	1097	1104	FERHC-0404	LIJA AL AGUA N░ 1500 ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6924	1097	1104	FERHC-0405	LIJA AL AGUA N░ 2000 ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6925	1097	1104	FERHC-0406	LIJA AL AGUA N░ 240 ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6926	1097	1104	FERHC-0407	LIJA AL AGUA N░ 320 ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6927	1097	1104	FERHC-0408	LIJA AL AGUA N░ 400 ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6928	1097	1104	FERHC-0409	LIJA N░ 220 PARA FIERRO ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6929	1097	1104	FERHC-0410	LIJA N░ 280 PARA FIERRO ABRALIT	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6930	1097	1104	FERHC-0411	LIJA PARA MADERA N░ 80 ASA	\N	producto	fijo	1.45	1.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6931	1097	1104	FERHC-0412	LIJADORA DE SANDALIA S/M	\N	producto	fijo	7.50	7.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6932	1097	1104	FERHC-0413	LIMA REDONDA BASTARDA 8 TRUPER	\N	producto	fijo	6.01	6.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6933	1097	1104	FERHC-0414	LIMA TRIANGULAR PESADA C/M 8 PL TRUPER	\N	producto	fijo	7.45	7.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6934	1097	1104	FERHC-0415	LIMA TRIANGULAR PESADA C/POLI 7 PL TRUPER	\N	producto	fijo	4.52	4.52	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6935	1097	1104	FERHC-0416	LLANTA COMPLETA IMPONCHABLE P/CARRETILLA C/ARO C&A	\N	producto	fijo	41.93	41.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6936	1097	1104	FERHC-0417	LLAVE CRUZ 14 (7X19X21X23) C&A	\N	producto	fijo	19.71	19.71	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6937	1097	1104	FERHC-0418	LLAVE DE DUCHA MANIJA ASPA C&A	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6938	1097	1104	FERHC-0419	LLAVE DE DUCHA MODELO Z C&A	\N	producto	fijo	16.69	16.69	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6939	1097	1104	FERHC-0420	LLAVE DE LAVATORIO CROMADO GRIFEMA	\N	producto	fijo	14.01	14.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6940	1097	1104	FERHC-0421	LLAVE DE LAVATORIO MUEBLE MODELO C C&A	\N	producto	fijo	17.94	17.94	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6941	1097	1104	FERHC-0422	LLAVE DE LAVATORIO MUEBLE MODELO Z C&A	\N	producto	fijo	16.31	16.31	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6942	1097	1104	FERHC-0423	LLAVE DE LAVATORIO PARED P/GANSO FLEX GRIS C&A	\N	producto	fijo	20.10	20.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6943	1097	1104	FERHC-0424	LLAVE DE LAVATORIO PARED P/GANSO FLEX NEGRO C&A	\N	producto	fijo	19.74	19.74	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6944	1097	1104	FERHC-0425	LLAVE ESTILSON 10 C&A	\N	producto	fijo	12.85	12.85	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6945	1097	1104	FERHC-0426	LLAVE ESTILSON 12 C&A	\N	producto	fijo	12.61	12.61	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6946	1097	1104	FERHC-0427	LLAVE HALEN EXAGONAL CROMADO 8 PCS KAMASA	\N	producto	fijo	3.93	3.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6947	1097	1104	FERHC-0428	LLAVE PASO 1 CIM	\N	producto	fijo	54.50	54.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6948	1097	1104	FERHC-0429	LLAVE PASO 1/2 S/ROSCA C&A	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6949	1097	1104	FERHC-0430	LLAVE PASO PVC CON UNIVERSAL 1/2 ERA	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6950	1097	1104	FERHC-0431	LUBRICANTE MULTIUSO P/AFLOJAR 11 OZ KNAUF	\N	producto	fijo	5.39	5.39	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6951	1097	1104	FERHC-0432	Ladrillo Concreto Tipo 12 S/M	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6952	1097	1104	FERHC-0433	Ladrillo Techo 12 SIPAN	\N	producto	fijo	2.55	2.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6953	1097	1104	FERHC-0434	Ladrillo Techo 15 ITAL	\N	producto	fijo	2.95	2.95	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6954	1097	1104	FERHC-0435	Lija Al Agua N░ 120 ASA	\N	producto	fijo	1.23	1.23	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6955	1097	1104	FERHC-0436	Lija Al Agua N░ 150 ASA	\N	producto	fijo	1.18	1.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6956	1097	1104	FERHC-0437	Lija Al Agua N░ 180 ASA	\N	producto	fijo	1.16	1.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6957	1097	1104	FERHC-0438	Lija Al Agua N░ 80 ASA	\N	producto	fijo	1.40	1.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6958	1097	1104	FERHC-0439	Lija N░ 100 Para Fierro ASA	\N	producto	fijo	1.05	1.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6959	1097	1104	FERHC-0440	Lija N░ 120 Para Fierro ASA	\N	producto	fijo	1.16	1.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6960	1097	1104	FERHC-0441	Lija N░ 150 Para Fierro ASA	\N	producto	fijo	1.12	1.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6961	1097	1104	FERHC-0442	Lija N░ 180 Para Fierro ASA	\N	producto	fijo	1.27	1.27	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6962	1097	1104	FERHC-0443	Lija N░ 40 Para Fierro ASA	\N	producto	fijo	1.42	1.42	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6963	1097	1104	FERHC-0444	Lija N░ 60 Para Fierro ASA	\N	producto	fijo	1.86	1.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6964	1097	1104	FERHC-0445	Lija N░ 80 Para Fierro ASA	\N	producto	fijo	1.65	1.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6965	1097	1104	FERHC-0446	Lija Para Madera N░ 100 ASA	\N	producto	fijo	1.25	1.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6966	1097	1104	FERHC-0447	Lija Para Madera N░ 180 ASA	\N	producto	fijo	1.24	1.24	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6967	1097	1104	FERHC-0448	Linterna Recargable HOME LIGHT	\N	producto	fijo	19.35	19.35	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6968	1097	1104	FERHC-0449	Llanta Completa P/Carretilla Ref C/Aro C&A	\N	producto	fijo	32.53	32.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6969	1097	1104	FERHC-0450	Llanta Sola P/Carretilla C&A	\N	producto	fijo	11.55	11.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6970	1097	1104	FERHC-0451	Llave De Ducha Modelo C C&A	\N	producto	fijo	18.35	18.35	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6971	1097	1104	FERHC-0452	Llave De Lavatorio Mueble Modelo A C&A	\N	producto	fijo	16.43	16.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6972	1097	1104	FERHC-0453	Llave De Lavatorio Mueble Modelo D C&A	\N	producto	fijo	15.84	15.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6973	1097	1104	FERHC-0454	Llave De Lavatorio Pared P/Ganso Mod B C&A	\N	producto	fijo	14.71	14.71	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6974	1097	1104	FERHC-0455	Llave De Lavatorio Pared P/Ganso Mod F C&A	\N	producto	fijo	15.12	15.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6975	1097	1104	FERHC-0456	Llave Francesa 10 C&A	\N	producto	fijo	8.96	8.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6976	1097	1104	FERHC-0457	Llave Francesa 12 C&A	\N	producto	fijo	11.22	11.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6977	1097	1104	FERHC-0458	Llave Francesa 8 C&A	\N	producto	fijo	7.17	7.17	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6978	1097	1104	FERHC-0459	Llave Mixta 10mm FERRAWY	\N	producto	fijo	1.11	1.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6979	1097	1104	FERHC-0460	Llave Mixta 11mm FERRAWY	\N	producto	fijo	1.16	1.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6980	1097	1104	FERHC-0461	Llave Mixta 12mm FERRAWY	\N	producto	fijo	1.24	1.24	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6981	1097	1104	FERHC-0462	Llave Mixta 13mm FERRAWY	\N	producto	fijo	1.36	1.36	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6982	1097	1104	FERHC-0463	Llave Mixta 14mm FERRAWY	\N	producto	fijo	1.56	1.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6983	1097	1104	FERHC-0464	Llave Mixta 15mm FERRAWY	\N	producto	fijo	1.86	1.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6984	1097	1104	FERHC-0465	Llave Mixta 17mm FERRAWY	\N	producto	fijo	2.15	2.15	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6985	1097	1104	FERHC-0466	Llave Mixta 19 C&A	\N	producto	fijo	2.54	2.54	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6986	1097	1104	FERHC-0467	Llave Mixta 8 C&A	\N	producto	fijo	1.04	1.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6987	1097	1104	FERHC-0468	Llave Para Amoladora 41/2 FERRAWY	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6988	1097	1104	FERHC-0469	Llave Para Taladro 41/2 UYUSTOOLS	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6989	1097	1104	FERHC-0470	Llave Paso 1/2 CIM	\N	producto	fijo	26.51	26.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6990	1097	1104	FERHC-0471	Llave Paso 3/4 CIM	\N	producto	fijo	39.22	39.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6991	1097	1104	FERHC-0472	Llave Paso Metal 1/2 VALMAX	\N	producto	fijo	7.94	7.94	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6992	1097	1104	FERHC-0473	Llave Paso Pvc 1 PAVCO	\N	producto	fijo	8.47	8.47	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6993	1097	1104	FERHC-0474	Llave Paso Pvc 1/2 C/R PAVCO	\N	producto	fijo	3.19	3.19	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6994	1097	1104	FERHC-0475	Llave Paso Pvc 3/4 PAVCO	\N	producto	fijo	4.86	4.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6995	1097	1104	FERHC-0476	Llave Termomagnetica 2X16 A SCHNEIDER	\N	producto	fijo	24.00	24.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6996	1097	1104	FERHC-0477	Llave Termomagnetica 2X16 A TICINO	\N	producto	fijo	36.26	36.26	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6997	1097	1104	FERHC-0478	Llave Termomagnetica 2X20 A TICINO	\N	producto	fijo	36.46	36.46	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6998	1097	1104	FERHC-0479	Llave Termomagnetica 2X25 A TICINO	\N	producto	fijo	35.21	35.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
6999	1097	1104	FERHC-0480	Llave Termomagnetica 2X32 A TICINO	\N	producto	fijo	36.50	36.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7000	1097	1104	FERHC-0481	Llave Termomagnetica 2x20 A SCHNEIDER	\N	producto	fijo	26.00	26.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7001	1097	1104	FERHC-0482	Llave Termomagnetica 2x25 A SCHNEIDER	\N	producto	fijo	24.89	24.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7002	1097	1104	FERHC-0483	Llave Termomagnetica 2x32 A SCHNEIDER	\N	producto	fijo	27.27	27.27	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7003	1097	1104	FERHC-0484	MACHETE CAÐERO M/MADERA C/GANCHO 14 BELLOTA	\N	producto	fijo	12.50	12.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7004	1097	1104	FERHC-0485	MACHETE T/SABLE M/NEGRO 22 PL BELLOTA	\N	producto	fijo	13.00	13.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7005	1097	1104	FERHC-0486	MALLA GALVANIZADA CUADRADA 1/2 PESADA PRODAC	\N	producto	fijo	3.73	3.73	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7006	1097	1104	FERHC-0487	MALLA GRIS PARA ZANCUDO 1.20 VARIOS	\N	producto	fijo	1.31	1.31	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7007	1097	1104	FERHC-0488	MALLA RASCHEL 65% 4.2 MT 27.3KG VERDE VARIOS	\N	producto	fijo	3.69	3.69	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7008	1097	1104	FERHC-0489	MALLA RASCHEL 90% 4.20MT 39.9KG VERDE VARIOS	\N	producto	fijo	5.18	5.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7009	1097	1104	FERHC-0490	MANGUERA DE COLOR DUPLEX 5/8 X 100 MT DUPLEX	\N	producto	fijo	0.65	0.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7010	1097	1104	FERHC-0491	MANGUERA REFORZADA P/GAS NARANJA 3/8 2M	\N	producto	fijo	1.52	1.52	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7011	1097	1104	FERHC-0492	MANGUERA REFORZADA PVC VERDE 1 2M	\N	producto	fijo	2.82	2.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7012	1097	1104	FERHC-0493	MANGUERA REFORZADA PVC VERDE 3/4 2M	\N	producto	fijo	1.75	1.75	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7013	1097	1104	FERHC-0494	MANGUERA REFORZADA PVC VERDE 5/8 2M	\N	producto	fijo	1.16	1.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7014	1097	1104	FERHC-0495	MARTILLO DE GOMA C/BLANCO 16 ONZ TRUPER	\N	producto	fijo	12.34	12.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7015	1097	1104	FERHC-0496	MARTILLO M/FIBRA VIDRIO 16 ONZ C&A	\N	producto	fijo	10.03	10.03	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7016	1097	1104	FERHC-0497	MARTILLO M/MADERA 20OZ C&A	\N	producto	fijo	9.44	9.44	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7017	1097	1104	FERHC-0498	MERLUZA KOLORCIX	\N	producto	fijo	1.20	1.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7018	1097	1104	FERHC-0499	Malla Metalica Galvanizada 1/2 VARIOS	\N	producto	fijo	1.48	1.48	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7019	1097	1104	FERHC-0500	Malla Plastificada Verde 1/2 X 10kg VARIOS	\N	producto	fijo	1.65	1.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7020	1097	1104	FERHC-0501	Malla Plastificada Verde Pesada 1/2 X 3pl X 25kg VARIOS	\N	producto	fijo	4.90	4.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7021	1097	1104	FERHC-0502	Malla Verde Para Zancudo 1.20 VARIOS	\N	producto	fijo	1.63	1.63	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7022	1097	1104	FERHC-0503	Malla Verde Para Zancudo 90 Cm VARIOS	\N	producto	fijo	1.13	1.13	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7023	1097	1104	FERHC-0504	Manguera De Color Duplex 1 X 100 Mt DUPLEX	\N	producto	fijo	1.70	1.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7024	1097	1104	FERHC-0505	Manguera De Color Duplex 3/4 X 100 Mt DUPLEX	\N	producto	fijo	1.09	1.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7025	1097	1104	FERHC-0506	Manguera Ref Trans P/Autm 3/8 2M	\N	producto	fijo	0.53	0.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7026	1097	1104	FERHC-0507	Martillo M/Fibra Vidrio 16onz TRUPER	\N	producto	fijo	23.51	23.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7027	1097	1104	FERHC-0508	Martillo M/Madera 34mm TRAMONTINA	\N	producto	fijo	26.00	26.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7028	1097	1104	FERHC-0509	Masilla Para Carro BONFLEX	\N	producto	fijo	9.99	9.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7029	1097	1104	FERHC-0510	Masilla Para Madera AMERICA	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7030	1097	1104	FERHC-0511	NAYLO DE PESCAR 50 PRETUL	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7031	1097	1104	FERHC-0512	NIPLE PVC 1 X 1 1/2 TRANSFORMADO	\N	producto	fijo	0.71	0.71	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7032	1097	1104	FERHC-0513	NIPLE PVC 1 X 1 TRANSFORMADO	\N	producto	fijo	0.42	0.42	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7033	1097	1104	FERHC-0514	NIPLE PVC 1 X 2 TRANSFORMADO	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7034	1097	1104	FERHC-0515	NIPLE PVC 1 X 4 TRANSFORMADO	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7035	1097	1104	FERHC-0516	NIPLE PVC 1/2 X 3 TRANSFORMADO	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7036	1097	1104	FERHC-0517	NIPLE PVC 3/4 X 1 TRANSFORMADO	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7037	1097	1104	FERHC-0518	NIPLE PVC 3/4 X 2 1/2 TRANSFORMADO	\N	producto	fijo	0.64	0.64	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7038	1097	1104	FERHC-0519	NIPLE PVC 3/4 X 2 TRANSFORMADO	\N	producto	fijo	0.72	0.72	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7039	1097	1104	FERHC-0520	NIVEL DE ALUMINIO PROFESIONAL 12 PL TRUPER	\N	producto	fijo	13.43	13.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7040	1097	1104	FERHC-0521	NIVEL DE ALUMINIO PROFESIONAL 18 PL TRUPER	\N	producto	fijo	19.94	19.94	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7041	1097	1104	FERHC-0522	NIVEL DE ALUMINIO PROFESIONAL 24 PL TRUPER	\N	producto	fijo	24.34	24.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7042	1097	1104	FERHC-0523	NIVEL DE ALUMINIO PROFESIONAL 36 PL TRUPER	\N	producto	fijo	32.53	32.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7043	1097	1104	FERHC-0524	NIVEL PLASTICO AMARILLO 14 PLG UYUSTOOLS	\N	producto	fijo	3.55	3.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7044	1097	1104	FERHC-0525	NIVEL PLASTICO AMARILLO 18 PLG UYUSTOOLS	\N	producto	fijo	3.86	3.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7045	1097	1104	FERHC-0526	Naylo De Pescar 100 ARATY	\N	producto	fijo	8.90	8.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7046	1097	1104	FERHC-0527	Naylo De Pescar 40 ARATY	\N	producto	fijo	3.08	3.08	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7047	1097	1104	FERHC-0528	Naylo De Pescar 45 ARATY	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7048	1097	1104	FERHC-0529	Naylo De Pescar 60 ARATY	\N	producto	fijo	5.00	5.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7049	1097	1104	FERHC-0530	Naylo De Pescar 70 ARATY	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7050	1097	1104	FERHC-0531	Naylo De Pescar 80 ARATY	\N	producto	fijo	7.30	7.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7051	1097	1104	FERHC-0532	Niple 1 X 1 FIERRO G	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7052	1097	1104	FERHC-0533	Niple 1/2 X 1 1/2 FIERRO G	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7053	1097	1104	FERHC-0534	Niple 1/2 X 1 FIERRO G	\N	producto	fijo	0.60	0.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7054	1097	1104	FERHC-0535	Niple 3/4 X 3 FIERRO G	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7055	1097	1104	FERHC-0536	Niple Pvc 1 X 3 TRANSFORMADO	\N	producto	fijo	1.10	1.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7056	1097	1104	FERHC-0537	Niple Pvc 1/2 X 1 1/2 TRANSFORMADO	\N	producto	fijo	0.37	0.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7057	1097	1104	FERHC-0538	Niple Pvc 1/2 x 1 TRANSFORMADO	\N	producto	fijo	0.37	0.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7058	1097	1104	FERHC-0539	Niple Pvc 1/2 x 2 1/2 TRANSFORMADO	\N	producto	fijo	0.60	0.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7059	1097	1104	FERHC-0540	Niple Pvc 1/2 x 2 TRANSFORMADO	\N	producto	fijo	0.45	0.45	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7060	1097	1104	FERHC-0541	Niple Pvc 3/4 x 3 TRANSFORMADO	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7061	1097	1104	FERHC-0542	Niple Pvc 3/4 x 6 TRANSFORMADO	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7062	1097	1104	FERHC-0543	Ocre Azul BAYER	\N	producto	fijo	3.56	3.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7063	1097	1104	FERHC-0544	Ocre Negro BAYER	\N	producto	fijo	3.28	3.28	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7064	1097	1104	FERHC-0545	Ocre Rojo BAYER	\N	producto	fijo	3.50	3.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7065	1097	1104	FERHC-0546	Ocre Verde BAYER	\N	producto	fijo	3.60	3.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7066	1097	1104	FERHC-0547	PAJARAFIA TORCIDA CONO VARIOS	\N	producto	fijo	6.01	6.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7067	1097	1104	FERHC-0548	PAJARRAFIA PAQ X 12 UNID S/M	\N	producto	fijo	0.57	0.57	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7068	1097	1104	FERHC-0549	PARCHE DE LLANTA VIPAL R-01 VIPAL	\N	producto	fijo	0.40	0.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7069	1097	1104	FERHC-0550	PEGAMENTO 1/16 DORADO OATEY	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7070	1097	1104	FERHC-0551	PEGAMENTO 1/4 NARANJA OATEY	\N	producto	fijo	32.00	32.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7071	1097	1104	FERHC-0552	PEGAMENTO 1/8 AZUL OATEY	\N	producto	fijo	33.00	33.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7072	1097	1104	FERHC-0553	PEGAMENTO FRIO BV-03 VIPAL	\N	producto	fijo	8.00	8.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7073	1097	1104	FERHC-0554	PEGAMENTO GRIS CERAMICA INTERIORES ADJ	\N	producto	fijo	8.50	8.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7074	1097	1104	FERHC-0555	PEGAMENTO GRIS CERAMICA PREMIUM ADJ	\N	producto	fijo	12.50	12.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7075	1097	1104	FERHC-0556	PERNO HEX 1/4 X 2 1/2 G2 S/M	\N	producto	fijo	0.27	0.27	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7076	1097	1104	FERHC-0557	PERNO HEX 1/4 X 3 G2 S/M	\N	producto	fijo	0.33	0.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7077	1097	1104	FERHC-0558	PIEDRA DE AFILAR 8X2X1 KAMASA	\N	producto	fijo	4.51	4.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7078	1097	1104	FERHC-0559	PINCEL PLANO 18 C&A	\N	producto	fijo	0.67	0.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7079	1097	1104	FERHC-0560	PINCEL PLANO 22 C&A	\N	producto	fijo	0.89	0.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7080	1097	1104	FERHC-0561	PINCEL PLANO 24 C&A	\N	producto	fijo	1.14	1.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7081	1097	1104	FERHC-0562	PINTURA EN BALDE AMARILLO KOLORCIX	\N	producto	fijo	16.00	16.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7082	1097	1104	FERHC-0563	PINTURA EN BALDE ARTICO KOLORCIX	\N	producto	fijo	13.00	13.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7083	1097	1104	FERHC-0564	PINTURA EN BALDE CELESTE KOLORCIX	\N	producto	fijo	13.10	13.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7084	1097	1104	FERHC-0565	PINTURA EN BALDE CITRON KOLORCIX	\N	producto	fijo	12.92	12.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7085	1097	1104	FERHC-0566	PINTURA EN BALDE GRIS CLARO KOLORCIX	\N	producto	fijo	13.50	13.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7086	1097	1104	FERHC-0567	PINTURA EN BALDE LILA KOLORCIX	\N	producto	fijo	12.57	12.57	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7087	1097	1104	FERHC-0568	PINTURA EN BALDE MAIZ KOLORCIX	\N	producto	fijo	12.84	12.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7088	1097	1104	FERHC-0569	PINTURA EN BALDE MARFIL KOLORCIX	\N	producto	fijo	13.50	13.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7089	1097	1104	FERHC-0570	PINTURA EN BALDE MELON KOLORCIX	\N	producto	fijo	12.84	12.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7090	1097	1104	FERHC-0571	PINTURA EN BALDE NARANJA KOLORCIX	\N	producto	fijo	15.00	15.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7091	1097	1104	FERHC-0572	PINTURA EN BALDE PISTACHO KOLORCIX	\N	producto	fijo	15.00	15.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7092	1097	1104	FERHC-0573	PINTURA EN BALDE ROJO TEJA KOLORCIX	\N	producto	fijo	13.50	13.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7093	1097	1104	FERHC-0574	PINTURA EN BALDE TURQUESA KOLORCIX	\N	producto	fijo	12.50	12.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7094	1097	1104	FERHC-0575	PINTURA EN BALDE VERDE ESMERALDA KOLORCIX	\N	producto	fijo	12.70	12.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7095	1097	1104	FERHC-0576	PINTURA EN BOLSA BLANCO HUMO KOLORCIX	\N	producto	fijo	2.80	2.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7096	1097	1104	FERHC-0577	PINTURA EN BOLSA BLANCO KOLORCIX	\N	producto	fijo	2.94	2.94	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7097	1097	1104	FERHC-0578	PINTURA EN BOLSA BLANCO X 25KG KOLORCIX	\N	producto	fijo	18.00	18.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7098	1097	1104	FERHC-0579	PINTURA EN BOLSA LILA KOLORCIX	\N	producto	fijo	2.95	2.95	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7099	1097	1104	FERHC-0580	PINTURA EN BOLSA MARFIL KOLORCIX	\N	producto	fijo	2.77	2.77	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7100	1097	1104	FERHC-0581	PINTURA EN BOLSA ROJO TEJA KOLORCIX	\N	producto	fijo	2.78	2.78	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7101	1097	1104	FERHC-0582	PINTURA SPRAY AZUL ELECTRICO C&A	\N	producto	fijo	2.91	2.91	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7102	1097	1104	FERHC-0583	PINTURA SPRAY CATERPILLAR C&A	\N	producto	fijo	3.41	3.41	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7103	1097	1104	FERHC-0584	PINTURA SPRAY DORADO C&A	\N	producto	fijo	4.58	4.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7104	1097	1104	FERHC-0585	PINTURA SPRAY NEGRO BRILLANTE C&A	\N	producto	fijo	2.91	2.91	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7105	1097	1104	FERHC-0586	PINTURA SPRAY NEGRO MATE C&A	\N	producto	fijo	2.96	2.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7106	1097	1104	FERHC-0587	PLANCHA DE BATIR M/GOMA 7 C&A	\N	producto	fijo	4.59	4.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7107	1097	1104	FERHC-0588	PLANCHA DE BATIR M/GOMA 8 KAMASA	\N	producto	fijo	9.00	9.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7108	1097	1104	FERHC-0589	PLANCHA PULIR LISA M/GOMA 11 X 5 PRO C&A	\N	producto	fijo	7.86	7.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7109	1097	1104	FERHC-0590	PLANCHA RASPIN DENTADA M/GOMA 11 X 5PL C&A	\N	producto	fijo	6.70	6.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7110	1097	1104	FERHC-0591	PLANCHA RASPIN M/GOMA ROJO BESTOOL	\N	producto	fijo	10.96	10.96	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7111	1097	1104	FERHC-0592	PLASTICO AZUL-NEGRO 1.50 MT(ROLL 120MT) S/M	\N	producto	fijo	2.32	2.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7112	1097	1104	FERHC-0593	PLASTICO AZUL-NEGRO 2METROS(ROLLO80MTS) S/M	\N	producto	fijo	3.06	3.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7113	1097	1104	FERHC-0594	PRECINTO 3 X 100 VARIOS	\N	producto	fijo	0.05	0.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7114	1097	1104	FERHC-0595	PRECINTO 3.6MM X 200 HOME LIGHT	\N	producto	fijo	0.01	0.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7115	1097	1104	FERHC-0596	PRECINTO 4.8 X 250 VARIOS	\N	producto	fijo	0.05	0.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7116	1097	1104	FERHC-0597	Palana Cuchara M/Negro C&A	\N	producto	fijo	11.61	11.61	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7117	1097	1104	FERHC-0598	Palana Recta M/Negro C&A	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7118	1097	1104	FERHC-0599	Palo Pulido Zapapico S/M	\N	producto	fijo	6.50	6.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7119	1097	1104	FERHC-0600	Palo Repuesto De Escoba S/M	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7120	1097	1104	FERHC-0601	Pegamento 1/32 Azul OATEY	\N	producto	fijo	9.81	9.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7121	1097	1104	FERHC-0602	Pegamento 1/4 Azul OATEY	\N	producto	fijo	46.00	46.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7122	1097	1104	FERHC-0603	Pegamento 1/4 Dorado OATEY	\N	producto	fijo	38.40	38.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7123	1097	1104	FERHC-0604	Pegamento 1/8 Dorado OATEY	\N	producto	fijo	26.10	26.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7124	1097	1104	FERHC-0605	Pegamento Africano 1/32 TEROCAL	\N	producto	fijo	4.01	4.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7125	1097	1104	FERHC-0606	Pegamento Africano 1/4 Galon TEROCAL	\N	producto	fijo	14.40	14.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7126	1097	1104	FERHC-0607	Pegamento Azul C/Brocha DATEY	\N	producto	fijo	3.28	3.28	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7127	1097	1104	FERHC-0608	Pegamento Blanco Flexible Porcelanato CHECERAMIC	\N	producto	fijo	16.30	16.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7128	1097	1104	FERHC-0609	Pegamento Gris Ceramica CHECERAMIC	\N	producto	fijo	7.73	7.73	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7129	1097	1104	FERHC-0610	Perno Anclaje S/M	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7130	1097	1104	FERHC-0611	Perno De Sujecion S/M	\N	producto	fijo	1.59	1.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7131	1097	1104	FERHC-0612	Piedra Base S/M	\N	producto	fijo	40.00	40.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7132	1097	1104	FERHC-0613	Piedra Chancada 1/2 Por Lata S/M	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7133	1097	1104	FERHC-0614	Piedra Chancada 1/2 S/M	\N	producto	fijo	66.00	66.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7134	1097	1104	FERHC-0615	Piedra Chancada 3/4 S/M	\N	producto	fijo	60.00	60.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7135	1097	1104	FERHC-0616	Pincel Plano 10 C&A	\N	producto	fijo	0.58	0.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7136	1097	1104	FERHC-0617	Pincel Plano 12 C&A	\N	producto	fijo	0.67	0.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7137	1097	1104	FERHC-0618	Pintura En Balde Azul KOLORCIX	\N	producto	fijo	13.40	13.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7138	1097	1104	FERHC-0619	Pintura En Balde Blanco Humo KOLORCIX	\N	producto	fijo	13.00	13.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7139	1097	1104	FERHC-0620	Pintura En Balde Blanco KOLORCIX	\N	producto	fijo	13.50	13.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7140	1097	1104	FERHC-0621	Pintura En Balde Fresa KOLORCIX	\N	producto	fijo	13.50	13.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7141	1097	1104	FERHC-0622	Pintura En Bolsa Amarillo KOLORCIX	\N	producto	fijo	2.95	2.95	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7142	1097	1104	FERHC-0623	Pintura En Bolsa Azul KOLORCIX	\N	producto	fijo	2.87	2.87	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7143	1097	1104	FERHC-0624	Pintura En Bolsa Celeste KOLORCIX	\N	producto	fijo	2.80	2.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7144	1097	1104	FERHC-0625	Pintura En Bolsa Crema KOLORCIX	\N	producto	fijo	2.80	2.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7145	1097	1104	FERHC-0626	Pintura En Bolsa Melon KOLORCIX	\N	producto	fijo	2.80	2.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7146	1097	1104	FERHC-0627	Pintura En Bolsa Naranja KOLORCIX	\N	producto	fijo	2.87	2.87	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7147	1097	1104	FERHC-0628	Pintura En Bolsa Rosado KOLORCIX	\N	producto	fijo	2.88	2.88	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7148	1097	1104	FERHC-0629	Pintura En Bolsa Verde Esmeralda KOLORCIX	\N	producto	fijo	2.80	2.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7149	1097	1104	FERHC-0630	Pintura En Bolsa Verde Limon KOLORCIX	\N	producto	fijo	2.95	2.95	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7150	1097	1104	FERHC-0631	Pintura Spray Aluminio C&A	\N	producto	fijo	4.14	4.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7151	1097	1104	FERHC-0632	Pintura Spray Amarillo Limon C&A	\N	producto	fijo	2.99	2.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7152	1097	1104	FERHC-0633	Pintura Spray Azul Claro C&A	\N	producto	fijo	3.02	3.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7153	1097	1104	FERHC-0634	Pintura Spray Blanco Brillante C&A	\N	producto	fijo	3.12	3.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7154	1097	1104	FERHC-0635	Pintura Spray Blanco Mate C&A	\N	producto	fijo	3.66	3.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7155	1097	1104	FERHC-0636	Pintura Spray Celeste C&A	\N	producto	fijo	3.32	3.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7156	1097	1104	FERHC-0637	Pintura Spray Gris C&A	\N	producto	fijo	2.93	2.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7157	1097	1104	FERHC-0638	Pintura Spray Marron C&A	\N	producto	fijo	3.15	3.15	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7158	1097	1104	FERHC-0639	Pintura Spray Naranja C&A	\N	producto	fijo	3.48	3.48	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7159	1097	1104	FERHC-0640	Pintura Spray Rojo Brillante C&A	\N	producto	fijo	2.97	2.97	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7160	1097	1104	FERHC-0641	Pintura Spray Silver C&A	\N	producto	fijo	3.50	3.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7161	1097	1104	FERHC-0642	Pintura Spray Verde Irlandes C&A	\N	producto	fijo	3.33	3.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7162	1097	1104	FERHC-0643	Plancha Pulir M/Goma KAMASA	\N	producto	fijo	12.00	12.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7163	1097	1104	FERHC-0644	Plomada Cilindrica Zincada S/M	\N	producto	fijo	10.67	10.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7164	1097	1104	FERHC-0645	Precinto 4.8 X 300 VARIOS	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7165	1097	1104	FERHC-0646	Precinto 4.8 X 400 VARIOS	\N	producto	fijo	0.04	0.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7166	1097	1104	FERHC-0647	Precinto 4.8 X 500 VARIOS	\N	producto	fijo	0.13	0.13	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7167	1097	1104	FERHC-0648	QUITA SARRO LUKAS	\N	producto	fijo	3.29	3.29	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7168	1097	1104	FERHC-0649	RAPIMIX ASENTADO PACASMAYO	\N	producto	fijo	8.87	8.87	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7169	1097	1104	FERHC-0650	RAPIMIX PARA TARRAJEO PACASMAYO	\N	producto	fijo	7.89	7.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7170	1097	1104	FERHC-0651	RASTRILLO RECTO 16 DIENTES M/MADERA TRUPER	\N	producto	fijo	31.03	31.03	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7171	1097	1104	FERHC-0652	REMACHADORA 10 PL SCHUBERT	\N	producto	fijo	13.99	13.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7172	1097	1104	FERHC-0653	RODILLO P/PINTAR 12 C&A	\N	producto	fijo	4.06	4.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7173	1097	1104	FERHC-0654	RODILLO P/PINTAR 12 TORO	\N	producto	fijo	17.50	17.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7174	1097	1104	FERHC-0655	RODOPLAST BEIGGE VARIOS	\N	producto	fijo	1.90	1.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7175	1097	1104	FERHC-0656	RODOPLAST MARFIL CLARO VARIOS	\N	producto	fijo	1.83	1.83	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7176	1097	1104	FERHC-0657	RODOPLAST NEGRO VARIOS	\N	producto	fijo	1.32	1.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7177	1097	1104	FERHC-0658	RODOPLAST VERDE SPAY VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7178	1097	1104	FERHC-0659	Radar Automatico TAIWAN	\N	producto	fijo	27.06	27.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7179	1097	1104	FERHC-0660	Recogedor Colores Economico S/M	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7180	1097	1104	FERHC-0661	Reduccion Cpvc 3/4 A 1/2 PAVCO	\N	producto	fijo	0.93	0.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7181	1097	1104	FERHC-0662	Reduccion Pvc 1 A 1/2 INYECTOPLAST	\N	producto	fijo	1.14	1.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7182	1097	1104	FERHC-0663	Reduccion Pvc 1 a 1/2 PAVCO	\N	producto	fijo	1.91	1.91	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7183	1097	1104	FERHC-0664	Reduccion Pvc 1 a 3/4 INYECTOPLAST	\N	producto	fijo	1.66	1.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7184	1097	1104	FERHC-0665	Reduccion Pvc 1 a 3/4 PAVCO	\N	producto	fijo	2.22	2.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7185	1097	1104	FERHC-0666	Reduccion Pvc 3/4 A 1/2 INYECTOPLAST	\N	producto	fijo	0.92	0.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7186	1097	1104	FERHC-0667	Reduccion Pvc 3/4 A 1/2 PAVCO	\N	producto	fijo	1.60	1.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7187	1097	1104	FERHC-0668	Reduccion Sal 3 A 2 PAVCO	\N	producto	fijo	3.76	3.76	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7188	1097	1104	FERHC-0669	Reduccion Sal 4 A 2 PAVCO	\N	producto	fijo	4.04	4.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7189	1097	1104	FERHC-0670	Reduccion Sal 4 A 2 PLASTICA	\N	producto	fijo	2.60	2.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7190	1097	1104	FERHC-0671	Reduccion Sal 4 A 3 PAVCO	\N	producto	fijo	6.37	6.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7191	1097	1104	FERHC-0672	Registro 2 Cromado VARIOS	\N	producto	fijo	3.12	3.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7192	1097	1104	FERHC-0673	Registro 3 Cromado VARIOS	\N	producto	fijo	6.57	6.57	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7193	1097	1104	FERHC-0674	Registro 4 Cromado VARIOS	\N	producto	fijo	9.70	9.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7194	1097	1104	FERHC-0675	Regla De Aluminio S/M	\N	producto	fijo	9.61	9.61	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7195	1097	1104	FERHC-0676	Repuesto Para Corta Mayolica KAMASA	\N	producto	fijo	9.52	9.52	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7196	1097	1104	FERHC-0677	Rodaje P/Carretilla 2 pz TRUPER	\N	producto	fijo	3.03	3.03	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7197	1097	1104	FERHC-0678	Rodillo P/Pintar 9 C&A	\N	producto	fijo	3.21	3.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7198	1097	1104	FERHC-0679	Rodillo P/Pintar 9 TORO	\N	producto	fijo	14.50	14.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7199	1097	1104	FERHC-0680	Rodoplast Aluminio Brillante 11.5 Ceramica VARIOS	\N	producto	fijo	6.01	6.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7200	1097	1104	FERHC-0681	Rodoplast Aluminio Brillante 9.5 Ceramica VARIOS	\N	producto	fijo	6.08	6.08	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7201	1097	1104	FERHC-0682	Rodoplast Aluminio Mate 11.5 Ceramica VARIOS	\N	producto	fijo	7.50	7.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7202	1097	1104	FERHC-0683	Rodoplast Aluminio Mate 9.5 Ceramica VARIOS	\N	producto	fijo	7.63	7.63	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7203	1097	1104	FERHC-0684	Rodoplast Blanco VARIOS	\N	producto	fijo	1.36	1.36	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7204	1097	1104	FERHC-0685	Rodoplast Bone VARIOS	\N	producto	fijo	1.99	1.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7205	1097	1104	FERHC-0686	Rodoplast Champang VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7206	1097	1104	FERHC-0687	Rodoplast Chocolate VARIOS	\N	producto	fijo	1.99	1.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7207	1097	1104	FERHC-0688	Rodoplast Crema VARIOS	\N	producto	fijo	1.71	1.71	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7208	1097	1104	FERHC-0689	Rodoplast Gris Claro VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7209	1097	1104	FERHC-0690	Rodoplast Gris Oscuro VARIOS	\N	producto	fijo	1.82	1.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7210	1097	1104	FERHC-0691	Rodoplast Lila Bebe VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7211	1097	1104	FERHC-0692	Rodoplast Madera VARIOS	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7212	1097	1104	FERHC-0693	Rodoplast Marfil Oscuro VARIOS	\N	producto	fijo	1.84	1.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7213	1097	1104	FERHC-0694	Rodoplast Marron Tabaco VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7214	1097	1104	FERHC-0695	Rodoplast Palo Rosa VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7215	1097	1104	FERHC-0696	Rodoplast Rojo VARIOS	\N	producto	fijo	1.90	1.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7216	1097	1104	FERHC-0697	Rodoplast Verde Nilo VARIOS	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7217	1097	1104	FERHC-0698	Rondana Circular Grande S/M	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7218	1097	1104	FERHC-0699	Rondana Circular Peq. S/M	\N	producto	fijo	0.50	0.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7219	1097	1104	FERHC-0700	Rondana Rect Peq. S/M	\N	producto	fijo	0.50	0.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7220	1097	1104	FERHC-0701	SEMICODO CPVC 1/2 SP PAVCO	\N	producto	fijo	1.14	1.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7221	1097	1104	FERHC-0702	SIERRA ACEROS AREQUIPA	\N	producto	fijo	3.08	3.08	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7222	1097	1104	FERHC-0703	SIKA 1 GALON X 4 LITROS SIKA	\N	producto	fijo	21.30	21.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7223	1097	1104	FERHC-0704	SILICONA BLANCO 280 ML PARA BAÐO Y COCINA SOLDIMIX	\N	producto	fijo	12.05	12.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7224	1097	1104	FERHC-0705	SILICONA PARA VIDRIO C/NEGRO 225ML KNAUF	\N	producto	fijo	4.79	4.79	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7225	1097	1104	FERHC-0706	SILICONA PARA VIDRIO TRANSPARENTE 225ML KNAUF	\N	producto	fijo	5.25	5.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7226	1097	1104	FERHC-0707	SILICONA TRANSPARENTE MULTIUSOS 50GR SOLDIMIX	\N	producto	fijo	4.70	4.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7227	1097	1104	FERHC-0708	SILICONA UV 300ML FRESA SIMONIZ	\N	producto	fijo	11.66	11.66	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7228	1097	1104	FERHC-0709	SOCATE AEREO DE PLASTICO REFORZADO HOME LIGHT	\N	producto	fijo	0.92	0.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7229	1097	1104	FERHC-0710	SOCATE MODELO PLANO BLANCO P22BN TICINO	\N	producto	fijo	0.01	0.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7230	1097	1104	FERHC-0711	SOCATE MODELO PLANO HOME LIGHT	\N	producto	fijo	1.52	1.52	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7231	1097	1104	FERHC-0712	SOCATE OVALADA BLANCO TICINO	\N	producto	fijo	8.71	8.71	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7232	1097	1104	FERHC-0713	SOLDADURA 1/8 SUPERSITO	\N	producto	fijo	16.82	16.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7233	1097	1104	FERHC-0714	SOLDIMIX EXTRAFUERTE 24 HRS SOLDIMIX	\N	producto	fijo	7.33	7.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7234	1097	1104	FERHC-0715	STOVE BOLTS 5/32 X 1 1/2 PL S/M	\N	producto	fijo	0.05	0.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7235	1097	1104	FERHC-0716	STOVE BOLTS 6/32 X 1 1/2 PL S/M	\N	producto	fijo	0.05	0.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7236	1097	1104	FERHC-0717	STOVE BOLTS 6/32 X 1 PL S/M	\N	producto	fijo	0.05	0.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7237	1097	1104	FERHC-0718	STOVE BOLTS 6/32 X 2 PL S/M	\N	producto	fijo	0.06	0.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7238	1097	1104	FERHC-0719	SUPER GLUE BLISTER X 1.50 GR SOLDIMIX	\N	producto	fijo	0.38	0.38	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7239	1097	1104	FERHC-0720	Semicodo Cpvc 3/4 Sp PAVCO	\N	producto	fijo	1.86	1.86	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7240	1097	1104	FERHC-0721	Semicodo Pvc 1 Sp PAVCO	\N	producto	fijo	2.37	2.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7241	1097	1104	FERHC-0722	Semicodo Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.70	1.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7242	1097	1104	FERHC-0723	Semicodo Pvc 1/2 Sp PLASTICA	\N	producto	fijo	0.63	0.63	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7243	1097	1104	FERHC-0724	Semicodo Pvc 3/4 Sp PAVCO	\N	producto	fijo	2.63	2.63	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7244	1097	1104	FERHC-0725	Semicodo Pvc 3/4 Sp TRANSFORMADO	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7245	1097	1104	FERHC-0726	Semicodo Sal 2 PAVCO	\N	producto	fijo	1.64	1.64	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7246	1097	1104	FERHC-0727	Semicodo Sal 2 PLASTICA	\N	producto	fijo	0.84	0.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7247	1097	1104	FERHC-0728	Semicodo Sal 3 INYECTOPLAST	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7248	1097	1104	FERHC-0729	Semicodo Sal 3 PAVCO	\N	producto	fijo	4.31	4.31	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7249	1097	1104	FERHC-0730	Semicodo Sal 4 PAVCO	\N	producto	fijo	6.34	6.34	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7250	1097	1104	FERHC-0731	Semicodo Sal 4 PLASTICA	\N	producto	fijo	3.80	3.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7251	1097	1104	FERHC-0732	Serrucho Curvo Poda 14pl M/Madera TRUPER	\N	producto	fijo	19.71	19.71	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7252	1097	1104	FERHC-0733	Serrucho M/Madera 18 Pl C&A	\N	producto	fijo	7.62	7.62	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7253	1097	1104	FERHC-0734	Sierra Naranja SANDFLEX	\N	producto	fijo	3.33	3.33	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7254	1097	1104	FERHC-0735	Sika x kg SIKA	\N	producto	fijo	5.20	5.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7255	1097	1104	FERHC-0736	Sikaflex 11 Fc Plus Gris 300 Ml Sika SIKA	\N	producto	fijo	23.00	23.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7256	1097	1104	FERHC-0737	Socate Colgante NEW LIGHT	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7257	1097	1104	FERHC-0738	Soda Caustica Litro KRIZZAL	\N	producto	fijo	5.00	5.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7258	1097	1104	FERHC-0739	Soda Caustica Por Kg KRIZZAL	\N	producto	fijo	10.48	10.48	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7259	1097	1104	FERHC-0740	Soldadura 1/8 PUNTO AZUL	\N	producto	fijo	15.40	15.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7260	1097	1104	FERHC-0741	Soldimix 10 Minutos SOLDIMIX	\N	producto	fijo	7.32	7.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7261	1097	1104	FERHC-0742	Sombrero De Ventilacion 2 HECHIZA	\N	producto	fijo	1.90	1.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7262	1097	1104	FERHC-0743	Sombrero De Ventilacion 4 HECHIZA	\N	producto	fijo	6.50	6.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7263	1097	1104	FERHC-0744	Sumidero 2 Cromado VARIOS	\N	producto	fijo	2.90	2.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7264	1097	1104	FERHC-0745	Sumidero 3 Cromado VARIOS	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7265	1097	1104	FERHC-0746	Sumidero 4 Cromado VARIOS	\N	producto	fijo	10.44	10.44	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7266	1097	1104	FERHC-0747	TAPON CPVC 1/2 HEMBRA PAVCO	\N	producto	fijo	0.55	0.55	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7267	1097	1104	FERHC-0748	TECNOPORT 1 1.20X2.40 MTS(PAQ 38UNID) S/M	\N	producto	fijo	8.50	8.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7268	1097	1104	FERHC-0749	TECNOPORT 1/2 1.20X2.40 MTS(PAQ 76UNID) S/M	\N	producto	fijo	4.25	4.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7269	1097	1104	FERHC-0750	TECNOPORT T/12 X0.30X1.20 (64 UNID) S/M	\N	producto	fijo	5.40	5.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7270	1097	1104	FERHC-0751	TEE PVC 1 SP PLASTICA	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7271	1097	1104	FERHC-0752	TEE PVC 3/4 CR PLASTICA PLASTICA	\N	producto	fijo	1.90	1.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7272	1097	1104	FERHC-0753	TEE SAL 2 PLASTICA	\N	producto	fijo	1.82	1.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7273	1097	1104	FERHC-0754	TEE SAL 3 A 2 PAVCO	\N	producto	fijo	8.07	8.07	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7274	1097	1104	FERHC-0755	TEE SAL 4 A 3 PAVCO	\N	producto	fijo	14.84	14.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7275	1097	1104	FERHC-0756	TEE SAL SANITARIA 2 PLASTICA	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7276	1097	1104	FERHC-0757	TEE SAL SANITARIA 4 PLASTICA	\N	producto	fijo	9.13	9.13	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7277	1097	1104	FERHC-0758	TERMINAL DE OJO BRONCE S/M	\N	producto	fijo	0.30	0.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7278	1097	1104	FERHC-0759	TERMINAL MACHO BRONCE S/M	\N	producto	fijo	0.20	0.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7279	1097	1104	FERHC-0760	THINER ACRILICO FMQ FM	\N	producto	fijo	14.47	14.47	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7280	1097	1104	FERHC-0761	THINER ACRILICO PATRON AC-450 2.8 LTRS TORVISCO	\N	producto	fijo	16.00	16.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7281	1097	1104	FERHC-0762	THINER ACRILICO TX-500 X 1/2 LITRO LOSARO	\N	producto	fijo	3.30	3.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7282	1097	1104	FERHC-0763	TIJERA P/HOJALATERO 12 M/REFORZADA C&A	\N	producto	fijo	10.73	10.73	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7283	1097	1104	FERHC-0764	TIMBRE DING DONG HOME LIGHT	\N	producto	fijo	5.14	5.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7284	1097	1104	FERHC-0765	TIRAFON HEX 1/4 X 1 1/2 S/M	\N	producto	fijo	0.09	0.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7285	1097	1104	FERHC-0766	TIRALINEA 30 MT 3 PZAS C&A	\N	producto	fijo	8.19	8.19	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7286	1097	1104	FERHC-0767	TIZA BLANCA PARA PIZARRA CAJA X 50UNID S/M	\N	producto	fijo	0.09	0.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7287	1097	1104	FERHC-0768	TOMACORRIENTE DOBLE P/SOBREPONER CON PUESTA A TIERRA HOME LIGHT	\N	producto	fijo	2.25	2.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7288	1097	1104	FERHC-0769	TOMACORRIENTE DOBLE/EMPOT CON PUESTA A TIERRA HOME LIGHT	\N	producto	fijo	2.21	2.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7289	1097	1104	FERHC-0770	TOMACORRIENTE SIMPLE P/EMPOTRADO HOME LIGHT	\N	producto	fijo	1.72	1.72	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7290	1097	1104	FERHC-0771	TOMACORRIENTE TRIPLE P/EMPOTRADO HOME LIGHT	\N	producto	fijo	2.04	2.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7291	1097	1104	FERHC-0772	TOMACORRIENTE TRIPLE SOBREPONER TICINO	\N	producto	fijo	13.63	13.63	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7292	1097	1104	FERHC-0773	TORNILLO AUTOPERFORANTE #10 X 1 1/2 UYUSTOOLS	\N	producto	fijo	0.06	0.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7293	1097	1104	FERHC-0774	TORNILLO SPACK 3.5X25 S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7294	1097	1104	FERHC-0775	TORNILLO SPACK 5X25 S/M	\N	producto	fijo	0.04	0.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7295	1097	1104	FERHC-0776	TRAMPA BOTELLA PVC P/LAVATORIO SANIFER	\N	producto	fijo	7.15	7.15	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7296	1097	1104	FERHC-0777	TRAMPA FLEXIBLE Y DESAGUE P/LAV 1.1/4 HYDRA	\N	producto	fijo	9.61	9.61	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7297	1097	1104	FERHC-0778	TRIPLAY 3.5MM TIPO LUPUNA 1.22 X2.44 LUPUNA	\N	producto	fijo	21.75	21.75	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7298	1097	1104	FERHC-0779	TRIPLAY 6MM TIPO LUPUNA 1.22 X 2.44 LUPUNA	\N	producto	fijo	38.50	38.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7299	1097	1104	FERHC-0780	TRIPLAY FENOLICO 17 MM 1.22 X 2.44 ECOPLEX	\N	producto	fijo	73.50	73.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7300	1097	1104	FERHC-0781	TROMPITO PARA CAÐO S/M	\N	producto	fijo	0.12	0.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7301	1097	1104	FERHC-0782	TUBO PVC C-10 1 C/R PLASTICA	\N	producto	fijo	19.10	19.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7302	1097	1104	FERHC-0783	TUBO PVC C-10 1/2 SP PLASTICA	\N	producto	fijo	7.04	7.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7303	1097	1104	FERHC-0784	TUBO PVC C-10 3/4 SP PLASTICA	\N	producto	fijo	7.50	7.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7304	1097	1104	FERHC-0785	TUBO SAL 4 PLASTICA	\N	producto	fijo	18.20	18.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7305	1097	1104	FERHC-0786	TUBO SAL 6 S-25 NARANJA 160MM UF X6 MT KOPLAST	\N	producto	fijo	89.76	89.76	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7306	1097	1104	FERHC-0787	TUBO SEL LUZ 1 PLASTICA	\N	producto	fijo	4.00	4.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7307	1097	1104	FERHC-0788	TUBO SEL LUZ 3/4 BLANCO PLASTICA	\N	producto	fijo	2.14	2.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7308	1097	1104	FERHC-0789	TUBO SEL LUZ 5/8 PLASTICA	\N	producto	fijo	2.30	2.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7309	1097	1104	FERHC-0790	Tanque Eternit Arena 1100 Lts ETERNIT	\N	producto	fijo	568.00	568.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7310	1097	1104	FERHC-0791	Tanque Eternit Azul 1100 Lts ETERNIT	\N	producto	fijo	500.00	500.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7311	1097	1104	FERHC-0792	Tapa Ciega Circular S/M	\N	producto	fijo	0.22	0.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7312	1097	1104	FERHC-0793	Tapa Ciega Rectangular S/M	\N	producto	fijo	0.22	0.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7313	1097	1104	FERHC-0794	Tapon Cpvc 3/4 Hembra PAVCO	\N	producto	fijo	0.84	0.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7314	1097	1104	FERHC-0795	Tapon De Oidos S/M	\N	producto	fijo	1.48	1.48	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7315	1097	1104	FERHC-0796	Tapon Hembra Pvc 1 C/Rosca PAVCO	\N	producto	fijo	1.90	1.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7316	1097	1104	FERHC-0797	Tapon Hembra Pvc 1 SP INYECTOPLAST	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7317	1097	1104	FERHC-0798	Tapon Hembra Pvc 1 Sp PAVCO	\N	producto	fijo	2.18	2.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7318	1097	1104	FERHC-0799	Tapon Hembra Pvc 1/2 C/Rosca GERFOR	\N	producto	fijo	0.40	0.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7319	1097	1104	FERHC-0800	Tapon Hembra Pvc 1/2 C/Rosca PAVCO	\N	producto	fijo	1.29	1.29	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7320	1097	1104	FERHC-0801	Tapon Hembra Pvc 1/2 Sp INYECTOPLAST	\N	producto	fijo	0.26	0.26	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7321	1097	1104	FERHC-0802	Tapon Hembra Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.10	1.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7322	1097	1104	FERHC-0803	Tapon Hembra Pvc 3/4 C/Rosca PAVCO	\N	producto	fijo	0.90	0.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7323	1097	1104	FERHC-0804	Tapon Hembra Pvc 3/4 Sp INYECTOPLAST	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7324	1097	1104	FERHC-0805	Tapon Hembra Pvc 3/4 Sp PAVCO	\N	producto	fijo	1.62	1.62	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7325	1097	1104	FERHC-0806	Tapon Macho 1/2 FIERRO G	\N	producto	fijo	1.04	1.04	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7326	1097	1104	FERHC-0807	Tapon Macho Pvc 1 Vinduit PAVCO	\N	producto	fijo	2.30	2.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7327	1097	1104	FERHC-0808	Tapon Macho Pvc 1/2 PAVCO	\N	producto	fijo	1.39	1.39	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7328	1097	1104	FERHC-0809	Tapon Macho Pvc 1/2 PLASTICA	\N	producto	fijo	0.59	0.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7329	1097	1104	FERHC-0810	Tapon Macho Pvc 3/4 GERFOR	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7330	1097	1104	FERHC-0811	Tapon Macho Pvc 3/4 Vinduit PAVCO	\N	producto	fijo	1.40	1.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7331	1097	1104	FERHC-0812	Tapon Sal 2 PAVCO	\N	producto	fijo	1.11	1.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7332	1097	1104	FERHC-0813	Tapon Sal 3 PAVCO	\N	producto	fijo	1.58	1.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7333	1097	1104	FERHC-0814	Tapon Sal 4 INYECTOPLAST	\N	producto	fijo	1.18	1.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7334	1097	1104	FERHC-0815	Tarugo Azul S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7335	1097	1104	FERHC-0816	Tarugo Naranja S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7336	1097	1104	FERHC-0817	Tarugo Verde S/M	\N	producto	fijo	0.02	0.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7337	1097	1104	FERHC-0818	Tecnoport 3/4 1.20x2.40 Mts S/M	\N	producto	fijo	6.40	6.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7338	1097	1104	FERHC-0819	Tee Bronce 1/2 VALMAX	\N	producto	fijo	2.82	2.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7339	1097	1104	FERHC-0820	Tee Cpvc 1/2 Sp PAVCO	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7340	1097	1104	FERHC-0821	Tee Cpvc 3/4 Sp PAVCO	\N	producto	fijo	2.78	2.78	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7341	1097	1104	FERHC-0822	Tee Fierro G. 1/2 FIERRO G	\N	producto	fijo	1.20	1.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7342	1097	1104	FERHC-0823	Tee Fierro G. 3/4 FIERRO G	\N	producto	fijo	2.10	2.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7343	1097	1104	FERHC-0824	Tee Pvc 1 C/Rosca PAVCO	\N	producto	fijo	4.84	4.84	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7344	1097	1104	FERHC-0825	Tee Pvc 1 C/Rosca PLASTICA	\N	producto	fijo	3.00	3.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7345	1097	1104	FERHC-0826	Tee Pvc 1 Sp PAVCO	\N	producto	fijo	6.24	6.24	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7346	1097	1104	FERHC-0827	Tee Pvc 1/2 C/Rosca NICOL	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7347	1097	1104	FERHC-0828	Tee Pvc 1/2 C/Rosca PAVCO	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7348	1097	1104	FERHC-0829	Tee Pvc 1/2 Sp PAVCO	\N	producto	fijo	2.19	2.19	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7349	1097	1104	FERHC-0830	Tee Pvc 1/2 Sp PLASTICA	\N	producto	fijo	0.89	0.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7350	1097	1104	FERHC-0831	Tee Pvc 3/4 C/Rosca PAVCO	\N	producto	fijo	3.40	3.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7351	1097	1104	FERHC-0832	Tee Pvc 3/4 S/p PLASTICA	\N	producto	fijo	1.30	1.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7352	1097	1104	FERHC-0833	Tee Pvc 3/4 Sp PAVCO	\N	producto	fijo	3.30	3.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7353	1097	1104	FERHC-0834	Tee Sal 2 PAVCO	\N	producto	fijo	3.30	3.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7354	1097	1104	FERHC-0835	Tee Sal 3 PAVCO	\N	producto	fijo	10.15	10.15	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7355	1097	1104	FERHC-0836	Tee Sal 3 PLASTICA	\N	producto	fijo	0.00	0.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7356	1097	1104	FERHC-0837	Tee Sal 4 A 2 PAVCO	\N	producto	fijo	8.00	8.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7357	1097	1104	FERHC-0838	Tee Sal 4 A 2 PLASTICA	\N	producto	fijo	4.30	4.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7358	1097	1104	FERHC-0839	Tee Sal 4 PAVCO	\N	producto	fijo	10.08	10.08	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7359	1097	1104	FERHC-0840	Tee Sal 4 PLASTICA	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7360	1097	1104	FERHC-0841	Tee Sal En Cruz 2 PAVCO	\N	producto	fijo	1.00	1.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7361	1097	1104	FERHC-0842	Tee Sal Sanitaria 2 PAVCO	\N	producto	fijo	4.37	4.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7362	1097	1104	FERHC-0843	Tee Sal Sanitaria 4 PAVCO	\N	producto	fijo	18.18	18.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7363	1097	1104	FERHC-0844	Thiner Acrilico Economico Por Litro FM	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7364	1097	1104	FERHC-0845	Tierra Cultivo S/M	\N	producto	fijo	35.00	35.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7365	1097	1104	FERHC-0846	Tirafon 1/4x 1 S/M	\N	producto	fijo	0.06	0.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7366	1097	1104	FERHC-0847	Tirafon Hex 1/4 X 3 S/M	\N	producto	fijo	0.22	0.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7367	1097	1104	FERHC-0848	Tirafon Hex 1/4 x 2 1/2 S/M	\N	producto	fijo	0.18	0.18	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7368	1097	1104	FERHC-0849	Tirafon Hex 1/4 x 2 S/M	\N	producto	fijo	0.09	0.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7369	1097	1104	FERHC-0850	Tirafon Hex 1/4 x 4 S/M	\N	producto	fijo	0.25	0.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7370	1097	1104	FERHC-0851	Tiralinea 30M Cuerpo De Plastico Y Nivel TRUPER	\N	producto	fijo	10.43	10.43	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7371	1097	1104	FERHC-0852	Tomacorriente Doble P/Empotrado TICINO	\N	producto	fijo	15.92	15.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7372	1097	1104	FERHC-0853	Tomacorriente Doble/Emp HOME LIGHT	\N	producto	fijo	2.07	2.07	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7373	1097	1104	FERHC-0854	Tomacorriente Simple Emp SCHNEIDER	\N	producto	fijo	4.11	4.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7374	1097	1104	FERHC-0855	Tomacorriente Simple P/Empotrado TICINO	\N	producto	fijo	9.50	9.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7375	1097	1104	FERHC-0856	Tomacorriente Simple P/Sobre HOME LIGHT	\N	producto	fijo	1.11	1.11	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7376	1097	1104	FERHC-0857	Tomacorriente Triple Sobreponer HOME LIGHT	\N	producto	fijo	1.82	1.82	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7377	1097	1104	FERHC-0858	Tortol 3/8 S/M	\N	producto	fijo	2.01	2.01	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7378	1097	1104	FERHC-0859	Trampa Flexible Y Desague P/Lav 1.1/4-1.1/2 C&A	\N	producto	fijo	6.05	6.05	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7379	1097	1104	FERHC-0860	Trampa P 2 PAVCO	\N	producto	fijo	9.65	9.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7380	1097	1104	FERHC-0861	Trampa Para Noque Sin Hueco TITOMAX	\N	producto	fijo	5.50	5.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7381	1097	1104	FERHC-0862	Trapeador Microfibra 4575 Cm S/M	\N	producto	fijo	3.58	3.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7382	1097	1104	FERHC-0863	Tubo Abasto P/Inodoro 7/8 Naylon C&A	\N	producto	fijo	1.79	1.79	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7383	1097	1104	FERHC-0864	Tubo Abasto P/Lavatorio 1/2 Nylon C&A	\N	producto	fijo	2.21	2.21	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7384	1097	1104	FERHC-0865	Tubo Cpvc 1/2 Sp PAVCO	\N	producto	fijo	23.51	23.51	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7385	1097	1104	FERHC-0866	Tubo Cpvc 3/4 Sp PAVCO	\N	producto	fijo	39.49	39.49	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7386	1097	1104	FERHC-0867	Tubo Pvc C-10 1 C/r PAVCO	\N	producto	fijo	32.98	32.98	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7387	1097	1104	FERHC-0868	Tubo Pvc C-10 1 Sp PAVCO	\N	producto	fijo	20.00	20.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7388	1097	1104	FERHC-0869	Tubo Pvc C-10 1 Sp PLASTICA	\N	producto	fijo	9.99	9.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7389	1097	1104	FERHC-0870	Tubo Pvc C-10 1/2 C/R PAVCO	\N	producto	fijo	17.00	17.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7390	1097	1104	FERHC-0871	Tubo Pvc C-10 1/2 C/R PLASTICA	\N	producto	fijo	9.70	9.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7391	1097	1104	FERHC-0872	Tubo Pvc C-10 1/2 Sp PAVCO	\N	producto	fijo	12.70	12.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7392	1097	1104	FERHC-0873	Tubo Pvc C-10 3/4 C/R GERFOR	\N	producto	fijo	13.70	13.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7393	1097	1104	FERHC-0874	Tubo Pvc C-10 3/4 C/R PLASTICA	\N	producto	fijo	12.00	12.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7394	1097	1104	FERHC-0875	Tubo Pvc C-10 3/4 Sp PAVCO	\N	producto	fijo	15.10	15.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7395	1097	1104	FERHC-0876	Tubo Sal 2 PAVCO	\N	producto	fijo	12.50	12.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7396	1097	1104	FERHC-0877	Tubo Sal 2 PLASTICA	\N	producto	fijo	6.60	6.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7397	1097	1104	FERHC-0878	Tubo Sal 3 PAVCO	\N	producto	fijo	32.00	32.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7398	1097	1104	FERHC-0879	Tubo Sal 3 PLASTICA	\N	producto	fijo	13.40	13.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7399	1097	1104	FERHC-0880	Tubo Sal 4 PAVCO	\N	producto	fijo	33.50	33.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7400	1097	1104	FERHC-0881	Tubo Sel Luz 1 PAVCO	\N	producto	fijo	8.40	8.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7401	1097	1104	FERHC-0882	Tubo Sel Luz 3/4 PAVCO	\N	producto	fijo	4.80	4.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7402	1097	1104	FERHC-0883	Tubo Sel Luz 5/8 PAVCO	\N	producto	fijo	5.81	5.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7403	1097	1104	FERHC-0884	Tuercas Hex 1/4 Zinc S/M	\N	producto	fijo	0.09	0.09	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7404	1097	1104	FERHC-0885	UNION CPVC 3/4 SP PAVCO	\N	producto	fijo	1.12	1.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7405	1097	1104	FERHC-0886	UNION PVC 1 C/R INYECTOPLAST	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7406	1097	1104	FERHC-0887	UNION PVC 1 SP PLASTICA	\N	producto	fijo	1.06	1.06	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7407	1097	1104	FERHC-0888	UNION PVC 3/4 SP HECHIZA	\N	producto	fijo	0.50	0.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7408	1097	1104	FERHC-0889	UNION UNIVERSAL 3/4 PVC ERA	\N	producto	fijo	2.50	2.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7409	1097	1104	FERHC-0890	UNION UNIVERSAL CPVC 1/2 PAVCO	\N	producto	fijo	8.58	8.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7410	1097	1104	FERHC-0891	Union Bronce 1/2 VALMAX	\N	producto	fijo	2.25	2.25	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7411	1097	1104	FERHC-0892	Union Cpvc 1/2 Sp PAVCO	\N	producto	fijo	0.85	0.85	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7412	1097	1104	FERHC-0893	Union Fierro G. 1 FIERRO G	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7413	1097	1104	FERHC-0894	Union Fierro G. 1/2 FIERRO G	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7414	1097	1104	FERHC-0895	Union Pvc 1 C/R PAVCO	\N	producto	fijo	2.93	2.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7415	1097	1104	FERHC-0896	Union Pvc 1 Mixta INYECTOPLAST	\N	producto	fijo	1.53	1.53	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7416	1097	1104	FERHC-0897	Union Pvc 1 Mixta PAVCO	\N	producto	fijo	2.44	2.44	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7417	1097	1104	FERHC-0898	Union Pvc 1 Sp PAVCO	\N	producto	fijo	2.93	2.93	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7418	1097	1104	FERHC-0899	Union Pvc 1/2 C/R NICOL	\N	producto	fijo	0.50	0.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7419	1097	1104	FERHC-0900	Union Pvc 1/2 C/R PAVCO	\N	producto	fijo	1.56	1.56	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7420	1097	1104	FERHC-0901	Union Pvc 1/2 Mixta PAVCO	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7421	1097	1104	FERHC-0902	Union Pvc 1/2 Mixta PLASTICA	\N	producto	fijo	0.80	0.80	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7422	1097	1104	FERHC-0903	Union Pvc 1/2 SP PLASTICA	\N	producto	fijo	0.37	0.37	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7423	1097	1104	FERHC-0904	Union Pvc 1/2 Sp HECHIZA	\N	producto	fijo	0.20	0.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7424	1097	1104	FERHC-0905	Union Pvc 1/2 Sp PAVCO	\N	producto	fijo	1.12	1.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7425	1097	1104	FERHC-0906	Union Pvc 3/4 C/R PAVCO	\N	producto	fijo	2.14	2.14	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7426	1097	1104	FERHC-0907	Union Pvc 3/4 C/R PLASTICA	\N	producto	fijo	1.59	1.59	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7427	1097	1104	FERHC-0908	Union Pvc 3/4 Mixta PAVCO	\N	producto	fijo	2.12	2.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7428	1097	1104	FERHC-0909	Union Pvc 3/4 Sp PAVCO	\N	producto	fijo	1.89	1.89	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7429	1097	1104	FERHC-0910	Union Pvc 3/4 Sp PLASTICA	\N	producto	fijo	0.67	0.67	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7430	1097	1104	FERHC-0911	Union Sal 2 HECHIZA	\N	producto	fijo	0.70	0.70	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7431	1097	1104	FERHC-0912	Union Sal 2 PAVCO	\N	producto	fijo	1.50	1.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7432	1097	1104	FERHC-0913	Union Sal 3 PAVCO	\N	producto	fijo	3.22	3.22	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7433	1097	1104	FERHC-0914	Union Sal 4 PAVCO	\N	producto	fijo	5.40	5.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7434	1097	1104	FERHC-0915	Union Universal 1/2 PAVCO	\N	producto	fijo	2.58	2.58	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7435	1097	1104	FERHC-0916	Union Universal 1/2 PCP	\N	producto	fijo	2.40	2.40	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7436	1097	1104	FERHC-0917	Union Univesal 1 PAVCO	\N	producto	fijo	4.81	4.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7437	1097	1104	FERHC-0918	Union Univesal 3/4 PAVCO	\N	producto	fijo	3.60	3.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7438	1097	1104	FERHC-0919	U±as Para Lavatorio Aluminio S/M	\N	producto	fijo	2.10	2.10	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7439	1097	1104	FERHC-0920	U±as Para Lavatorio Fierro Fundido S/M	\N	producto	fijo	3.60	3.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7440	1097	1104	FERHC-0921	VALVULA CHECK SWING HORIZONTAL 1/2 SWIFT	\N	producto	fijo	8.77	8.77	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7441	1097	1104	FERHC-0922	VALVULA GAS 24 LB 2G GASPER	\N	producto	fijo	16.50	16.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7442	1097	1104	FERHC-0923	VIDRIO PARA SOLDAR 12 NEGRO S/M	\N	producto	fijo	0.65	0.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7443	1097	1104	FERHC-0924	Valvula Che 1 ROTOPLAST	\N	producto	fijo	16.69	16.69	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7444	1097	1104	FERHC-0925	Valvula Check Swing 1 Asiento Goma CIM	\N	producto	fijo	83.90	83.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7445	1097	1104	FERHC-0926	Valvula Gas Equipada C/Manguera SURGE	\N	producto	fijo	14.50	14.50	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7446	1097	1104	FERHC-0927	Vidrio Para Soldar 11 Negro S/M	\N	producto	fijo	0.65	0.65	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7447	1097	1104	FERHC-0928	WALL SOCATE OVALADO VARGYOV	\N	producto	fijo	1.81	1.81	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7448	1097	1104	FERHC-0929	WINCHA 5 METROS PRETUL	\N	producto	fijo	5.02	5.02	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7449	1097	1104	FERHC-0930	WINCHA 5 MT C/PROTECTOR KAMASA	\N	producto	fijo	5.99	5.99	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7450	1097	1104	FERHC-0931	WINCHA 5MTS WINGS	\N	producto	fijo	3.72	3.72	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7451	1097	1104	FERHC-0932	WINCHA AUTO-LOCK 5 MTS C/AMARILLA TRUPER	\N	producto	fijo	12.12	12.12	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7452	1097	1104	FERHC-0933	WINCHA AUTO-LOCK 8 MTS C/AMARILLA TRUPER	\N	producto	fijo	21.72	21.72	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7453	1097	1104	FERHC-0934	WINCHA PASACABLE X 10 METROS TRUPER	\N	producto	fijo	7.16	7.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7454	1097	1104	FERHC-0935	WINCHA PASACABLE X 15 METROS TRUPER	\N	producto	fijo	11.00	11.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7455	1097	1104	FERHC-0936	WINCHA PASACABLE X 20 METROS TRUPER	\N	producto	fijo	9.90	9.90	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7456	1097	1104	FERHC-0937	YESO BOLSA X 15 KG S/M	\N	producto	fijo	3.30	3.30	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7457	1097	1104	FERHC-0938	YESO CERAMICO X 1KG KOLORCIX	\N	producto	fijo	2.88	2.88	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7458	1097	1104	FERHC-0939	Yee Sal 2 PAVCO	\N	producto	fijo	4.00	4.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7459	1097	1104	FERHC-0940	Yee Sal 2 PLASTICA	\N	producto	fijo	2.42	2.42	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7460	1097	1104	FERHC-0941	Yee Sal 3 PAVCO	\N	producto	fijo	7.92	7.92	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7461	1097	1104	FERHC-0942	Yee Sal 3 PLASTICA	\N	producto	fijo	3.62	3.62	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7462	1097	1104	FERHC-0943	Yee Sal 4 A 2 PAVCO	\N	producto	fijo	8.91	8.91	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7463	1097	1104	FERHC-0944	Yee Sal 4 A 2 PLASTICA	\N	producto	fijo	3.91	3.91	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7464	1097	1104	FERHC-0945	Yee Sal 4 A 3 PAVCO	\N	producto	fijo	12.00	12.00	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7465	1097	1104	FERHC-0946	Yee Sal 4 PAVCO	\N	producto	fijo	14.60	14.60	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7466	1097	1104	FERHC-0947	Yee Sal 4 PLASTICA	\N	producto	fijo	9.32	9.32	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7467	1097	1104	FERHC-0948	Yeso Por Kg S/M	\N	producto	fijo	0.20	0.20	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7468	1097	1104	FERHC-0949	ZAPAPICO 5 LBS C/NEGRO C&A	\N	producto	fijo	14.24	14.24	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
7469	1097	1104	FERHC-GRANEL	MATERIALES A GRANEL (ladrillo/cemento/agregados)	\N	producto	fijo	98.16	98.16	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12	t	t	f
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
50	1097	RUC	20479790214	CORPORACION HERRERA S.A.C.	\N	\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12
51	1097	RUC	20131719559	DEPOSITO PAKATNAMU S.A.C	\N	\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12
52	1097	RUC	20103134065	FERRONOR SAC.	\N	\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12
53	1097	RUC	\N	LADRILLERA RAMOS	\N	\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12
54	1097	RUC	20496166273	SERVICIOS GENERALES ADJ EIRL	\N	\N	\N	\N	\N	\N	t	2026-07-11 10:12:12	2026-07-11 10:12:12
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, empresa_id, nombre, descripcion, es_admin, activo, created_at, updated_at, max_descuento_porcentaje) FROM stdin;
1	1	Administrador	Dueña — acceso total	t	t	2026-05-18 01:53:39	2026-05-18 01:53:39	\N
2	1	Cajera	Vende, cobra, abre y cierra turno. No edita catálogo ni configuración.	f	t	2026-05-18 01:53:39	2026-05-18 01:53:39	10.00
1168	1097	Administrador	Acceso total	t	t	2026-07-11 10:12:11	2026-07-11 10:12:11	\N
1169	1097	Cajera	Vende, cobra, abre y cierra turno.	f	t	2026-07-11 10:12:11	2026-07-11 10:12:11	10.00
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
MKuMB2GTwe284F0ZZkY6JPfu3OUv6UdJmPw4tjLa	\N	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	YTozOntzOjY6Il90b2tlbiI7czo0MDoid2lnb0ZMamJJNVk5QWdXNkZvYmZ0Qks1UDVZcjVyNDhmNWpOam9aaCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=	1783736857
anHS2uqkHxz0ypoGJmvGkNjkFqfgZd00xioBl7Lm	957	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	YTo1OntzOjY6Il90b2tlbiI7czo0MDoiNTg5dktTT3kwYU0yMXQwY0l3bWQ2ZmZrQ1g3Nkc0RFN6SzNXeHBVUyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjk1NztzOjM6InVybCI7YToxOntzOjg6ImludGVuZGVkIjtzOjQwOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmluYW56YXMvYW50aWNpcG9zIjt9fQ==	1783718827
m90rFv5zobqNce0FKI1r8arWrh3FayRqLVCb5NBZ	\N	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	YTozOntzOjY6Il90b2tlbiI7czo0MDoiV1FiWnFoWmFGc2FDVEFLQ2x1aXdhVHZnN2xQcVE3VWJJSHB2ZWY1aSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=	1783757756
reSnlhyHIKwkK0CcSMjBsxAIFKUw1ai3D6ugpEcu	1	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	YTozOntzOjY6Il90b2tlbiI7czo0MDoia05KRDVwN1VIanRFZkxvMk8zNXFONmtZYTg4U09zVlJpNXR3WTVMRSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9	1783783037
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
7112	1110	6520	102.0000	0.2600	2026-07-11 10:12:12	2026-07-11 10:12:12
7113	1110	6521	13.0000	10.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7114	1110	6522	9.0000	14.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7115	1110	6523	10.0000	3.9800	2026-07-11 10:12:12	2026-07-11 10:12:12
504	1	305	85000.0000	0.8500	2026-07-05 19:27:37	2026-07-05 19:27:37
506	1	307	18000.0000	1.9000	2026-07-05 19:27:37	2026-07-05 19:27:37
513	1	314	380.0000	28.5000	2026-07-05 19:27:37	2026-07-05 19:27:37
7116	1110	6524	13.0000	6.7700	2026-07-11 10:12:12	2026-07-11 10:12:12
507	1	308	843.0000	26.5000	2026-07-05 19:27:37	2026-07-05 20:17:50
7117	1110	6525	1.0000	17.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7118	1110	6526	46.0000	3.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
7119	1110	6527	20.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7120	1110	6528	2.0000	2.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7121	1110	6529	23.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7122	1110	6530	38.0000	2.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7123	1110	6531	20.0000	43.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7124	1110	6532	16.0000	0.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7125	1110	6533	6.0000	11.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7126	1110	6534	3.0000	7.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
7127	1110	6535	2.0000	37.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7128	1110	6536	100.0000	0.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7129	1110	6537	1.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7130	1110	6538	58.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7131	1110	6539	67.0000	1.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7132	1110	6540	95.0000	0.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7133	1110	6541	122.0000	0.4800	2026-07-11 10:12:12	2026-07-11 10:12:12
7134	1110	6542	115.0000	1.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7135	1110	6543	60.0000	0.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7136	1110	6544	6.0000	27.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7137	1110	6545	0.0000	7.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7138	1110	6546	794.0000	3.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7139	1110	6547	2401.0000	3.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7140	1110	6548	6.0000	31.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7141	1110	6549	15.0000	1.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7142	1110	6550	12.0000	8.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7143	1110	6551	4.0000	7.7800	2026-07-11 10:12:12	2026-07-11 10:12:12
7144	1110	6552	3.0000	6.7600	2026-07-11 10:12:12	2026-07-11 10:12:12
7145	1110	6553	2.0000	9.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7146	1110	6554	9.0000	2.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7147	1110	6555	27.0000	4.0700	2026-07-11 10:12:12	2026-07-11 10:12:12
7148	1110	6556	11.0000	3.7800	2026-07-11 10:12:12	2026-07-11 10:12:12
7149	1110	6557	16.0000	5.7200	2026-07-11 10:12:12	2026-07-11 10:12:12
7150	1110	6558	30.5000	38.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7151	1110	6559	0.0100	0.3500	2026-07-11 10:12:12	2026-07-11 10:12:12
7152	1110	6560	2.0000	20.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7153	1110	6561	64.0000	0.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7154	1110	6562	58.0000	0.1700	2026-07-11 10:12:12	2026-07-11 10:12:12
7155	1110	6563	8.0000	4.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7156	1110	6564	6.0000	2.7600	2026-07-11 10:12:12	2026-07-11 10:12:12
7157	1110	6565	9.0000	2.5700	2026-07-11 10:12:12	2026-07-11 10:12:12
7158	1110	6566	16.0000	0.7600	2026-07-11 10:12:12	2026-07-11 10:12:12
7159	1110	6567	6.0000	3.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7160	1110	6568	14.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7161	1110	6569	11.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7162	1110	6570	12.0000	1.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7163	1110	6571	16.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7164	1110	6572	15.0000	2.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7165	1110	6573	8.0000	12.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7166	1110	6574	76.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7167	1110	6575	81.0000	0.7900	2026-07-11 10:12:12	2026-07-11 10:12:12
7168	1110	6576	9.0000	1.4200	2026-07-11 10:12:12	2026-07-11 10:12:12
7169	1110	6577	7.0000	1.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7170	1110	6578	34.0000	1.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7171	1110	6579	13.0000	4.4100	2026-07-11 10:12:12	2026-07-11 10:12:12
7172	1110	6580	3.0000	5.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7173	1110	6581	4.0000	2.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7174	1110	6582	14.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7175	1110	6583	9.0000	2.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7176	1110	6584	7.0000	3.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7177	1110	6585	6.0000	3.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7178	1110	6586	10.0000	1.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7179	1110	6587	8.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7180	1110	6588	3.0000	9.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
7181	1110	6589	17.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7182	1110	6590	5.0000	2.0300	2026-07-11 10:12:12	2026-07-11 10:12:12
7183	1110	6591	2.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7184	1110	6592	7.0000	4.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7185	1110	6593	5.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7186	1110	6594	2.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7187	1110	6595	9.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7188	1110	6596	21.0000	0.5400	2026-07-11 10:12:12	2026-07-11 10:12:12
7189	1110	6597	22.0000	1.9800	2026-07-11 10:12:12	2026-07-11 10:12:12
7190	1110	6598	12.0000	1.5700	2026-07-11 10:12:12	2026-07-11 10:12:12
7191	1110	6599	11.0000	2.6200	2026-07-11 10:12:12	2026-07-11 10:12:12
7192	1110	6600	12.0000	0.7800	2026-07-11 10:12:12	2026-07-11 10:12:12
7193	1110	6601	12.0000	3.4700	2026-07-11 10:12:12	2026-07-11 10:12:12
7194	1110	6602	13.0000	4.4400	2026-07-11 10:12:12	2026-07-11 10:12:12
7195	1110	6603	24.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7196	1110	6604	70.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7197	1110	6605	34.0000	0.7300	2026-07-11 10:12:12	2026-07-11 10:12:12
7198	1110	6606	8.0000	0.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7199	1110	6607	2.0000	17.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7200	1110	6608	937.0000	2.1700	2026-07-11 10:12:12	2026-07-11 10:12:12
7201	1110	6609	849.2000	1.6900	2026-07-11 10:12:12	2026-07-11 10:12:12
7202	1110	6610	9.0000	20.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7203	1110	6611	12.0000	8.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7204	1110	6612	13.0000	9.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7205	1110	6613	18.0000	9.1700	2026-07-11 10:12:12	2026-07-11 10:12:12
7206	1110	6614	6.0000	8.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7207	1110	6615	8.0000	2.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7208	1110	6616	291.5000	7.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7209	1110	6617	447.0000	12.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7210	1110	6618	55.0000	20.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7211	1110	6619	17.0000	1.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7212	1110	6620	3.0000	11.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7213	1110	6621	9.0000	100.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7214	1110	6622	3.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7215	1110	6623	11.0000	30.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7216	1110	6624	228.0000	1.8500	2026-07-11 10:12:12	2026-07-11 10:12:12
7217	1110	6625	412.0000	30.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7218	1110	6626	2.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7219	1110	6627	5.0000	9.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7220	1110	6628	3.0000	1.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7221	1110	6629	2.0000	1.7500	2026-07-11 10:12:12	2026-07-11 10:12:12
7222	1110	6630	1.0000	3.1300	2026-07-11 10:12:12	2026-07-11 10:12:12
7223	1110	6631	6.0000	10.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
7224	1110	6632	77.0000	3.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
7225	1110	6633	51.0000	1.6300	2026-07-11 10:12:12	2026-07-11 10:12:12
7226	1110	6634	5.0000	4.3100	2026-07-11 10:12:12	2026-07-11 10:12:12
7227	1110	6635	1.0000	4.3100	2026-07-11 10:12:12	2026-07-11 10:12:12
7228	1110	6636	35.0000	6.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7229	1110	6637	45.7000	1.8300	2026-07-11 10:12:12	2026-07-11 10:12:12
7230	1110	6638	774.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7231	1110	6639	26.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7232	1110	6640	140.0000	0.0700	2026-07-11 10:12:12	2026-07-11 10:12:12
7233	1110	6641	508.0000	0.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7234	1110	6642	524.0000	0.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7235	1110	6643	11.7500	4.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7236	1110	6644	29.1000	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7237	1110	6645	41.1500	4.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7238	1110	6646	28.0000	1.7900	2026-07-11 10:12:12	2026-07-11 10:12:12
7239	1110	6647	153.0000	0.7400	2026-07-11 10:12:12	2026-07-11 10:12:12
7240	1110	6648	136.0000	0.6400	2026-07-11 10:12:12	2026-07-11 10:12:12
7241	1110	6649	44.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7242	1110	6650	46.0000	6.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7243	1110	6651	42.0000	3.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
7244	1110	6652	11.0000	6.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7245	1110	6653	7.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7246	1110	6654	2.0000	32.8300	2026-07-11 10:12:12	2026-07-11 10:12:12
7247	1110	6655	35.0000	5.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7248	1110	6656	31.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7249	1110	6657	1.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7250	1110	6658	15.0000	0.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7251	1110	6659	1.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7252	1110	6660	1.0000	8.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
7253	1110	6661	26.0000	2.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7254	1110	6662	222.5000	1.2300	2026-07-11 10:12:12	2026-07-11 10:12:12
7255	1110	6663	585.0000	2.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7256	1110	6664	15.0000	4.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7257	1110	6665	6.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7258	1110	6666	14.0000	10.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7259	1110	6667	11.0000	11.7500	2026-07-11 10:12:12	2026-07-11 10:12:12
7260	1110	6668	4.0000	15.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7261	1110	6669	11.0000	13.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7262	1110	6670	10.0000	4.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7263	1110	6671	8.0000	6.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7264	1110	6672	3.0000	13.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7265	1110	6673	3.0000	5.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7266	1110	6674	6.0000	18.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7267	1110	6675	131.0000	0.3800	2026-07-11 10:12:12	2026-07-11 10:12:12
7268	1110	6676	254.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7269	1110	6677	12.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7270	1110	6678	109.0000	0.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7271	1110	6679	544.0000	1.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7272	1110	6680	50.0000	7.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7273	1110	6681	11.0000	83.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7274	1110	6682	56.0000	31.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7275	1110	6683	25.0000	17.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7276	1110	6684	5.0000	35.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7277	1110	6685	12.0000	7.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7278	1110	6686	51.0000	1.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7279	1110	6687	12.0000	2.2800	2026-07-11 10:12:12	2026-07-11 10:12:12
7280	1110	6688	12.0000	2.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7281	1110	6689	15.0000	3.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7282	1110	6690	5.0000	5.9700	2026-07-11 10:12:12	2026-07-11 10:12:12
7283	1110	6691	1014.0000	0.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7284	1110	6692	14.0000	4.9100	2026-07-11 10:12:12	2026-07-11 10:12:12
7285	1110	6693	8.0000	4.4400	2026-07-11 10:12:12	2026-07-11 10:12:12
7286	1110	6694	10.0000	5.7800	2026-07-11 10:12:12	2026-07-11 10:12:12
7287	1110	6695	8.0000	6.9400	2026-07-11 10:12:12	2026-07-11 10:12:12
7288	1110	6696	15.0000	14.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7289	1110	6697	11.0000	8.9700	2026-07-11 10:12:12	2026-07-11 10:12:12
7290	1110	6698	469.0000	33.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7291	1110	6699	8.0000	2.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7292	1110	6700	12.0000	0.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7293	1110	6701	8.0000	65.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7294	1110	6702	5.0000	64.8800	2026-07-11 10:12:12	2026-07-11 10:12:12
7295	1110	6703	1.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7296	1110	6704	6.0000	5.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7297	1110	6705	101.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7298	1110	6706	36.0000	0.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7299	1110	6707	23.0000	2.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7300	1110	6708	14.0000	1.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
7301	1110	6709	25.0000	5.7600	2026-07-11 10:12:12	2026-07-11 10:12:12
7302	1110	6710	9.0000	2.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7303	1110	6711	1.0000	17.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7304	1110	6712	42.0000	0.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7305	1110	6713	45.0000	0.7400	2026-07-11 10:12:12	2026-07-11 10:12:12
7306	1110	6714	7.0000	17.4100	2026-07-11 10:12:12	2026-07-11 10:12:12
7307	1110	6715	6.0000	22.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7308	1110	6716	161.5000	0.1300	2026-07-11 10:12:12	2026-07-11 10:12:12
7309	1110	6717	148.0000	0.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7310	1110	6718	14.3500	4.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7311	1110	6719	653.5000	3.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7312	1110	6720	84.9500	3.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7313	1110	6721	94.4500	3.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7314	1110	6722	80.1000	3.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7315	1110	6723	85.5500	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7316	1110	6724	41.0500	5.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7317	1110	6725	0.0000	5.6100	2026-07-11 10:12:12	2026-07-11 10:12:12
7318	1110	6726	18.0000	2.3800	2026-07-11 10:12:12	2026-07-11 10:12:12
7319	1110	6727	72.0000	0.8500	2026-07-11 10:12:12	2026-07-11 10:12:12
7320	1110	6728	66.0000	2.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7321	1110	6729	16.0000	2.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7322	1110	6730	82.0000	1.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7323	1110	6731	6.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7324	1110	6732	43.0000	4.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7325	1110	6733	21.0000	2.2400	2026-07-11 10:12:12	2026-07-11 10:12:12
7326	1110	6734	94.0000	2.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7327	1110	6735	14.0000	0.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7328	1110	6736	39.0000	1.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7329	1110	6737	105.0000	1.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7330	1110	6738	19.0000	2.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7331	1110	6739	41.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7332	1110	6740	107.0000	1.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7333	1110	6741	91.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7334	1110	6742	6.0000	1.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7335	1110	6743	52.0000	1.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
7336	1110	6744	85.0000	1.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7337	1110	6745	176.0000	0.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7338	1110	6746	30.0000	5.3500	2026-07-11 10:12:12	2026-07-11 10:12:12
7339	1110	6747	32.0000	3.1700	2026-07-11 10:12:12	2026-07-11 10:12:12
7340	1110	6748	23.0000	9.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7341	1110	6749	8.0000	5.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7342	1110	6750	12.0000	9.5200	2026-07-11 10:12:12	2026-07-11 10:12:12
7343	1110	6751	3.0000	4.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7344	1110	6752	4.0000	15.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7345	1110	6753	2.0000	7.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7346	1110	6754	1.0000	45.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7347	1110	6755	34.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7348	1110	6756	492.0000	0.3100	2026-07-11 10:12:12	2026-07-11 10:12:12
7349	1110	6757	1225.0000	0.1900	2026-07-11 10:12:12	2026-07-11 10:12:12
7350	1110	6758	21.0000	0.3500	2026-07-11 10:12:12	2026-07-11 10:12:12
7351	1110	6759	12.0000	0.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7352	1110	6760	8.0000	1.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7353	1110	6761	7.0000	3.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7354	1110	6762	4.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7355	1110	6763	56.0000	3.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7356	1110	6764	10.0000	4.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7357	1110	6765	34.0000	6.4700	2026-07-11 10:12:12	2026-07-11 10:12:12
7358	1110	6766	18.0000	10.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7359	1110	6767	23.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7360	1110	6768	4.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7361	1110	6769	17.0000	14.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7362	1110	6770	536.0000	0.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7363	1110	6771	356.0000	0.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7364	1110	6772	380.0000	0.6100	2026-07-11 10:12:12	2026-07-11 10:12:12
7365	1110	6773	397.0000	0.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7366	1110	6774	1.0000	7.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7367	1110	6775	16.0000	0.8500	2026-07-11 10:12:12	2026-07-11 10:12:12
7368	1110	6776	2.0000	1.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7369	1110	6777	11.0000	1.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7370	1110	6778	12.0000	2.3800	2026-07-11 10:12:12	2026-07-11 10:12:12
7371	1110	6779	18.0000	5.3900	2026-07-11 10:12:12	2026-07-11 10:12:12
7372	1110	6780	13.0000	5.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7373	1110	6781	12.0000	14.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7374	1110	6782	179.0000	2.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7375	1110	6783	50.0000	2.1900	2026-07-11 10:12:12	2026-07-11 10:12:12
7376	1110	6784	25.0000	4.8500	2026-07-11 10:12:12	2026-07-11 10:12:12
7377	1110	6785	91.0000	4.6800	2026-07-11 10:12:12	2026-07-11 10:12:12
7378	1110	6786	12.0000	3.6800	2026-07-11 10:12:12	2026-07-11 10:12:12
7379	1110	6787	16.0000	7.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7380	1110	6788	5.0000	1.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7381	1110	6789	20.0000	2.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7382	1110	6790	1.0000	11.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
7383	1110	6791	6.0000	7.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7384	1110	6792	12.0000	4.4700	2026-07-11 10:12:12	2026-07-11 10:12:12
7385	1110	6793	12.0000	4.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7386	1110	6794	12.0000	1.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7387	1110	6795	66.0000	0.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7388	1110	6796	4.0000	12.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7389	1110	6797	6.0000	6.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
7390	1110	6798	5.0000	7.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7391	1110	6799	3.0000	51.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7392	1110	6800	100.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7393	1110	6801	111.0000	0.4700	2026-07-11 10:12:12	2026-07-11 10:12:12
7394	1110	6802	14.0000	1.7700	2026-07-11 10:12:12	2026-07-11 10:12:12
7395	1110	6803	29.0000	3.4900	2026-07-11 10:12:12	2026-07-11 10:12:12
7396	1110	6804	9.0000	8.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7397	1110	6805	2.0000	4.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7398	1110	6806	5.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7399	1110	6807	7.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7400	1110	6808	9.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7401	1110	6809	7.0000	5.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7402	1110	6810	5.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7403	1110	6811	4.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7404	1110	6812	8.0000	4.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7405	1110	6813	3.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7406	1110	6814	6.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7407	1110	6815	6.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7408	1110	6816	7.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7409	1110	6817	13.0000	3.2800	2026-07-11 10:12:12	2026-07-11 10:12:12
7410	1110	6818	4.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7411	1110	6819	12.0000	3.2900	2026-07-11 10:12:12	2026-07-11 10:12:12
7412	1110	6820	10.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7413	1110	6821	4.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7414	1110	6822	6.0000	3.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7415	1110	6823	7.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7416	1110	6824	2.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7417	1110	6825	3.0000	10.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7418	1110	6826	1.0000	10.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7419	1110	6827	3.0000	9.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7420	1110	6828	7.0000	11.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7421	1110	6829	12.0000	10.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7422	1110	6830	3.0000	10.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7423	1110	6831	3.0000	9.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7424	1110	6832	4.0000	10.0700	2026-07-11 10:12:12	2026-07-11 10:12:12
7425	1110	6833	29.0000	10.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7426	1110	6834	5.0000	10.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7427	1110	6835	1.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7428	1110	6836	2.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7429	1110	6837	1.0000	7.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7430	1110	6838	1.0000	6.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7431	1110	6839	5.0000	6.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7432	1110	6840	14.0000	1.6800	2026-07-11 10:12:12	2026-07-11 10:12:12
7433	1110	6841	9.0000	1.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
7434	1110	6842	2.0000	1.8300	2026-07-11 10:12:12	2026-07-11 10:12:12
7435	1110	6843	159.0000	60.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7436	1110	6844	140.0000	51.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7437	1110	6845	2.0000	15.4200	2026-07-11 10:12:12	2026-07-11 10:12:12
7438	1110	6846	272.0000	6.0700	2026-07-11 10:12:12	2026-07-11 10:12:12
7439	1110	6847	22.0000	1.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7440	1110	6848	41.0000	3.2900	2026-07-11 10:12:12	2026-07-11 10:12:12
7441	1110	6849	41.0000	4.7700	2026-07-11 10:12:12	2026-07-11 10:12:12
7442	1110	6850	1.0000	2.5400	2026-07-11 10:12:12	2026-07-11 10:12:12
7443	1110	6851	6.0000	18.5700	2026-07-11 10:12:12	2026-07-11 10:12:12
7444	1110	6852	1.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7445	1110	6853	6.0000	12.6400	2026-07-11 10:12:12	2026-07-11 10:12:12
7446	1110	6854	4.0000	15.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7447	1110	6855	9.0000	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7448	1110	6856	8.0000	8.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7449	1110	6857	8.0000	9.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7450	1110	6858	4.0000	10.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7451	1110	6859	2.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7452	1110	6860	4.0000	15.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7453	1110	6861	7.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7454	1110	6862	8.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7455	1110	6863	30.0000	3.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7456	1110	6864	11.0000	4.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7457	1110	6865	12.0000	0.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7458	1110	6866	10.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7459	1110	6867	2.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7460	1110	6868	17.0000	3.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
7461	1110	6869	5.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7462	1110	6870	7.0000	5.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7463	1110	6871	493.0000	32.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7464	1110	6872	184.0000	29.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7465	1110	6873	12.0000	74.2700	2026-07-11 10:12:12	2026-07-11 10:12:12
7466	1110	6874	379.0000	18.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7467	1110	6875	228.0000	49.9800	2026-07-11 10:12:12	2026-07-11 10:12:12
7468	1110	6876	368.0000	7.2600	2026-07-11 10:12:12	2026-07-11 10:12:12
7469	1110	6877	529.0000	13.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7470	1110	6878	5.0000	4.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7471	1110	6879	8.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7472	1110	6880	3.0000	11.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7473	1110	6881	1.0000	4.3500	2026-07-11 10:12:12	2026-07-11 10:12:12
7474	1110	6882	4.0000	2.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7475	1110	6883	40.0000	1.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7476	1110	6884	10.0000	5.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7477	1110	6885	331.0000	0.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7478	1110	6886	642.0000	0.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7479	1110	6887	344.0000	0.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7480	1110	6888	1.0000	0.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7481	1110	6889	4.0000	20.7900	2026-07-11 10:12:12	2026-07-11 10:12:12
7482	1110	6890	166.0000	2.6800	2026-07-11 10:12:12	2026-07-11 10:12:12
7483	1110	6891	200.0000	1.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7484	1110	6892	25.0000	0.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
7485	1110	6893	2.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7486	1110	6894	53.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7487	1110	6895	6.0000	3.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7488	1110	6896	4.0000	3.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7489	1110	6897	4.0000	3.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7490	1110	6898	7.0000	3.2900	2026-07-11 10:12:12	2026-07-11 10:12:12
7491	1110	6899	5.0000	5.6800	2026-07-11 10:12:12	2026-07-11 10:12:12
7492	1110	6900	7.0000	3.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7493	1110	6901	4.0000	19.9700	2026-07-11 10:12:12	2026-07-11 10:12:12
7494	1110	6902	18.0000	7.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7495	1110	6903	3.0000	7.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7496	1110	6904	19.0000	1.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7497	1110	6905	10.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7498	1110	6906	56.0000	1.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7499	1110	6907	12.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7500	1110	6908	21.0000	2.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7501	1110	6909	12.0000	20.3800	2026-07-11 10:12:12	2026-07-11 10:12:12
7502	1110	6910	104.0000	1.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7503	1110	6911	28.0000	12.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7504	1110	6912	15.0000	1.4600	2026-07-11 10:12:12	2026-07-11 10:12:12
7505	1110	6913	51.0000	1.4200	2026-07-11 10:12:12	2026-07-11 10:12:12
7506	1110	6914	18.0000	9.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7507	1110	6915	66.0000	2.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7508	1110	6916	7.0000	20.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7509	1110	6917	12.0000	3.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7510	1110	6918	1.0000	111.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7511	1110	6919	1.0000	32.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7512	1110	6920	1.0000	37.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7513	1110	6921	121.0000	2.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7514	1110	6922	65.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7515	1110	6923	24.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7516	1110	6924	6.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7517	1110	6925	2.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7518	1110	6926	45.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7519	1110	6927	14.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7520	1110	6928	71.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7521	1110	6929	24.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7522	1110	6930	28.0000	1.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7523	1110	6931	4.0000	7.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7524	1110	6932	7.0000	6.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7525	1110	6933	7.0000	7.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7526	1110	6934	5.0000	4.5200	2026-07-11 10:12:12	2026-07-11 10:12:12
7527	1110	6935	4.0000	41.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
7528	1110	6936	1.0000	19.7100	2026-07-11 10:12:12	2026-07-11 10:12:12
7529	1110	6937	1.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7530	1110	6938	4.0000	16.6900	2026-07-11 10:12:12	2026-07-11 10:12:12
7531	1110	6939	6.0000	14.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7532	1110	6940	1.0000	17.9400	2026-07-11 10:12:12	2026-07-11 10:12:12
7533	1110	6941	12.0000	16.3100	2026-07-11 10:12:12	2026-07-11 10:12:12
7534	1110	6942	4.0000	20.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7535	1110	6943	5.0000	19.7400	2026-07-11 10:12:12	2026-07-11 10:12:12
7536	1110	6944	4.0000	12.8500	2026-07-11 10:12:12	2026-07-11 10:12:12
7537	1110	6945	2.0000	12.6100	2026-07-11 10:12:12	2026-07-11 10:12:12
7538	1110	6946	2.0000	3.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
7539	1110	6947	5.0000	54.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7540	1110	6948	8.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7541	1110	6949	3.0000	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7542	1110	6950	12.0000	5.3900	2026-07-11 10:12:12	2026-07-11 10:12:12
7543	1110	6951	165.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7544	1110	6952	402.0000	2.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7545	1110	6953	0.0000	2.9500	2026-07-11 10:12:12	2026-07-11 10:12:12
7546	1110	6954	24.0000	1.2300	2026-07-11 10:12:12	2026-07-11 10:12:12
7547	1110	6955	13.0000	1.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7548	1110	6956	3.0000	1.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7549	1110	6957	5.0000	1.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7550	1110	6958	132.0000	1.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7551	1110	6959	70.0000	1.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7552	1110	6960	184.0000	1.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7553	1110	6961	69.0000	1.2700	2026-07-11 10:12:12	2026-07-11 10:12:12
7554	1110	6962	45.0000	1.4200	2026-07-11 10:12:12	2026-07-11 10:12:12
7555	1110	6963	27.0000	1.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7556	1110	6964	34.0000	1.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7557	1110	6965	28.0000	1.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7558	1110	6966	33.0000	1.2400	2026-07-11 10:12:12	2026-07-11 10:12:12
7559	1110	6967	3.0000	19.3500	2026-07-11 10:12:12	2026-07-11 10:12:12
7560	1110	6968	8.0000	32.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
7561	1110	6969	7.0000	11.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7562	1110	6970	2.0000	18.3500	2026-07-11 10:12:12	2026-07-11 10:12:12
7563	1110	6971	5.0000	16.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7564	1110	6972	9.0000	15.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7565	1110	6973	5.0000	14.7100	2026-07-11 10:12:12	2026-07-11 10:12:12
7566	1110	6974	2.0000	15.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7567	1110	6975	6.0000	8.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7568	1110	6976	6.0000	11.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7569	1110	6977	7.0000	7.1700	2026-07-11 10:12:12	2026-07-11 10:12:12
7570	1110	6978	9.0000	1.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7571	1110	6979	10.0000	1.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7572	1110	6980	12.0000	1.2400	2026-07-11 10:12:12	2026-07-11 10:12:12
7573	1110	6981	10.0000	1.3600	2026-07-11 10:12:12	2026-07-11 10:12:12
7574	1110	6982	17.0000	1.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
7575	1110	6983	10.0000	1.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7576	1110	6984	15.0000	2.1500	2026-07-11 10:12:12	2026-07-11 10:12:12
7577	1110	6985	14.0000	2.5400	2026-07-11 10:12:12	2026-07-11 10:12:12
7578	1110	6986	8.0000	1.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7579	1110	6987	10.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7580	1110	6988	12.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7581	1110	6989	5.0000	26.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7582	1110	6990	6.0000	39.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7583	1110	6991	9.0000	7.9400	2026-07-11 10:12:12	2026-07-11 10:12:12
7584	1110	6992	14.0000	8.4700	2026-07-11 10:12:12	2026-07-11 10:12:12
7585	1110	6993	46.0000	3.1900	2026-07-11 10:12:12	2026-07-11 10:12:12
7586	1110	6994	31.0000	4.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7587	1110	6995	5.0000	24.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7588	1110	6996	9.0000	36.2600	2026-07-11 10:12:12	2026-07-11 10:12:12
7589	1110	6997	9.0000	36.4600	2026-07-11 10:12:12	2026-07-11 10:12:12
7590	1110	6998	11.0000	35.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7591	1110	6999	5.0000	36.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7592	1110	7000	5.0000	26.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7593	1110	7001	9.0000	24.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7594	1110	7002	4.0000	27.2700	2026-07-11 10:12:12	2026-07-11 10:12:12
7595	1110	7003	4.0000	12.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7596	1110	7004	4.0000	13.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7597	1110	7005	120.0000	3.7300	2026-07-11 10:12:12	2026-07-11 10:12:12
7598	1110	7006	26.0000	1.3100	2026-07-11 10:12:12	2026-07-11 10:12:12
7599	1110	7007	47.0000	3.6900	2026-07-11 10:12:12	2026-07-11 10:12:12
7600	1110	7008	8.0000	5.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7601	1110	7009	84.0000	0.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7602	1110	7010	29.0000	1.5200	2026-07-11 10:12:12	2026-07-11 10:12:12
7603	1110	7011	115.0000	2.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7604	1110	7012	56.0000	1.7500	2026-07-11 10:12:12	2026-07-11 10:12:12
7605	1110	7013	81.0000	1.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
7606	1110	7014	3.0000	12.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7607	1110	7015	6.0000	10.0300	2026-07-11 10:12:12	2026-07-11 10:12:12
7608	1110	7016	7.0000	9.4400	2026-07-11 10:12:12	2026-07-11 10:12:12
7609	1110	7017	16.0000	1.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7610	1110	7018	162.0000	1.4800	2026-07-11 10:12:12	2026-07-11 10:12:12
7611	1110	7019	108.0000	1.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7612	1110	7020	61.7000	4.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7613	1110	7021	33.5000	1.6300	2026-07-11 10:12:12	2026-07-11 10:12:12
7614	1110	7022	192.0000	1.1300	2026-07-11 10:12:12	2026-07-11 10:12:12
7615	1110	7023	51.0000	1.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7616	1110	7024	1.0000	1.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7617	1110	7025	122.0000	0.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
7618	1110	7026	2.0000	23.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7619	1110	7027	2.0000	26.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7620	1110	7028	31.0000	9.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7621	1110	7029	4.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7622	1110	7030	9.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7623	1110	7031	35.0000	0.7100	2026-07-11 10:12:12	2026-07-11 10:12:12
7624	1110	7032	105.0000	0.4200	2026-07-11 10:12:12	2026-07-11 10:12:12
7625	1110	7033	28.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7626	1110	7034	46.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7627	1110	7035	94.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7628	1110	7036	47.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7629	1110	7037	98.0000	0.6400	2026-07-11 10:12:12	2026-07-11 10:12:12
7630	1110	7038	5.0000	0.7200	2026-07-11 10:12:12	2026-07-11 10:12:12
7631	1110	7039	6.0000	13.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7632	1110	7040	6.0000	19.9400	2026-07-11 10:12:12	2026-07-11 10:12:12
7633	1110	7041	5.0000	24.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7634	1110	7042	4.0000	32.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
7635	1110	7043	12.0000	3.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7636	1110	7044	6.0000	3.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7637	1110	7045	6.0000	8.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7638	1110	7046	4.0000	3.0800	2026-07-11 10:12:12	2026-07-11 10:12:12
7639	1110	7047	5.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7640	1110	7048	8.0000	5.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7641	1110	7049	5.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7642	1110	7050	6.0000	7.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7643	1110	7051	14.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7644	1110	7052	4.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7645	1110	7053	48.0000	0.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7646	1110	7054	30.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7647	1110	7055	13.0000	1.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7648	1110	7056	24.0000	0.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
7649	1110	7057	104.0000	0.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
7650	1110	7058	78.0000	0.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7651	1110	7059	63.0000	0.4500	2026-07-11 10:12:12	2026-07-11 10:12:12
7652	1110	7060	31.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7653	1110	7061	4.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7654	1110	7062	20.0000	3.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
7655	1110	7063	49.0000	3.2800	2026-07-11 10:12:12	2026-07-11 10:12:12
7656	1110	7064	31.0000	3.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7657	1110	7065	24.0000	3.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7658	1110	7066	4.0000	6.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7659	1110	7067	21.0000	0.5700	2026-07-11 10:12:12	2026-07-11 10:12:12
7660	1110	7068	70.0000	0.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7661	1110	7069	1.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7662	1110	7070	2.0000	32.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7663	1110	7071	3.0000	33.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7664	1110	7072	1.0000	8.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7665	1110	7073	1.0000	8.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7666	1110	7074	3.0000	12.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7667	1110	7075	73.0000	0.2700	2026-07-11 10:12:12	2026-07-11 10:12:12
7668	1110	7076	4.0000	0.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7669	1110	7077	2.0000	4.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7670	1110	7078	1.0000	0.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7671	1110	7079	10.0000	0.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7672	1110	7080	9.0000	1.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7673	1110	7081	3.0000	16.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7674	1110	7082	4.0000	13.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7675	1110	7083	5.0000	13.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7676	1110	7084	5.0000	12.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7677	1110	7085	5.0000	13.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7678	1110	7086	5.0000	12.5700	2026-07-11 10:12:12	2026-07-11 10:12:12
7679	1110	7087	5.0000	12.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7680	1110	7088	2.0000	13.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7681	1110	7089	3.0000	12.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7682	1110	7090	3.0000	15.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7683	1110	7091	4.0000	15.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7684	1110	7092	4.0000	13.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7685	1110	7093	3.0000	12.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7686	1110	7094	3.0000	12.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7687	1110	7095	9.0000	2.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7688	1110	7096	39.0000	2.9400	2026-07-11 10:12:12	2026-07-11 10:12:12
7689	1110	7097	11.0000	18.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7690	1110	7098	14.0000	2.9500	2026-07-11 10:12:12	2026-07-11 10:12:12
7691	1110	7099	12.0000	2.7700	2026-07-11 10:12:12	2026-07-11 10:12:12
7692	1110	7100	13.0000	2.7800	2026-07-11 10:12:12	2026-07-11 10:12:12
7693	1110	7101	12.0000	2.9100	2026-07-11 10:12:12	2026-07-11 10:12:12
7694	1110	7102	14.0000	3.4100	2026-07-11 10:12:12	2026-07-11 10:12:12
7695	1110	7103	10.0000	4.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
7696	1110	7104	77.0000	2.9100	2026-07-11 10:12:12	2026-07-11 10:12:12
7697	1110	7105	24.0000	2.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7698	1110	7106	1.0000	4.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
7699	1110	7107	13.0000	9.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7700	1110	7108	6.0000	7.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7701	1110	7109	10.0000	6.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7702	1110	7110	1.0000	10.9600	2026-07-11 10:12:12	2026-07-11 10:12:12
7703	1110	7111	116.5000	2.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7704	1110	7112	180.0000	3.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7705	1110	7113	50.0000	0.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7706	1110	7114	150.0000	0.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7707	1110	7115	22.0000	0.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7708	1110	7116	13.0000	11.6100	2026-07-11 10:12:12	2026-07-11 10:12:12
7709	1110	7117	4.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7710	1110	7118	3.0000	6.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7711	1110	7119	5.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7712	1110	7120	160.0000	9.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7713	1110	7121	7.0000	46.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7714	1110	7122	9.0000	38.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7715	1110	7123	11.0000	26.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7716	1110	7124	12.0000	4.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7717	1110	7125	4.0000	14.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7718	1110	7126	105.0000	3.2800	2026-07-11 10:12:12	2026-07-11 10:12:12
7719	1110	7127	99.0000	16.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7720	1110	7128	162.0000	7.7300	2026-07-11 10:12:12	2026-07-11 10:12:12
7721	1110	7129	22.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7722	1110	7130	23.0000	1.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
7723	1110	7131	2.7500	40.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7724	1110	7132	5.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7725	1110	7133	16.0000	66.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7726	1110	7134	6.0000	60.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7727	1110	7135	2.0000	0.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
7728	1110	7136	4.0000	0.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7729	1110	7137	4.0000	13.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7730	1110	7138	3.0000	13.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7731	1110	7139	11.0000	13.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7732	1110	7140	7.0000	13.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7733	1110	7141	11.0000	2.9500	2026-07-11 10:12:12	2026-07-11 10:12:12
7734	1110	7142	15.0000	2.8700	2026-07-11 10:12:12	2026-07-11 10:12:12
7735	1110	7143	14.0000	2.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7736	1110	7144	12.0000	2.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7737	1110	7145	9.0000	2.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7738	1110	7146	16.0000	2.8700	2026-07-11 10:12:12	2026-07-11 10:12:12
7739	1110	7147	18.0000	2.8800	2026-07-11 10:12:12	2026-07-11 10:12:12
7740	1110	7148	12.0000	2.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7741	1110	7149	15.0000	2.9500	2026-07-11 10:12:12	2026-07-11 10:12:12
7742	1110	7150	18.0000	4.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7743	1110	7151	14.0000	2.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7744	1110	7152	12.0000	3.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7745	1110	7153	20.0000	3.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7746	1110	7154	34.0000	3.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7747	1110	7155	11.0000	3.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7748	1110	7156	25.0000	2.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
7749	1110	7157	9.0000	3.1500	2026-07-11 10:12:12	2026-07-11 10:12:12
7750	1110	7158	10.0000	3.4800	2026-07-11 10:12:12	2026-07-11 10:12:12
7751	1110	7159	5.0000	2.9700	2026-07-11 10:12:12	2026-07-11 10:12:12
7752	1110	7160	10.0000	3.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7753	1110	7161	3.0000	3.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7754	1110	7162	8.0000	12.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7755	1110	7163	3.0000	10.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
7756	1110	7164	263.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7757	1110	7165	265.0000	0.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7758	1110	7166	33.0000	0.1300	2026-07-11 10:12:12	2026-07-11 10:12:12
7759	1110	7167	3.0000	3.2900	2026-07-11 10:12:12	2026-07-11 10:12:12
7760	1110	7168	270.0000	8.8700	2026-07-11 10:12:12	2026-07-11 10:12:12
7761	1110	7169	521.0000	7.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7762	1110	7170	1.0000	31.0300	2026-07-11 10:12:12	2026-07-11 10:12:12
7763	1110	7171	1.0000	13.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7764	1110	7172	22.0000	4.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7765	1110	7173	12.0000	17.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7766	1110	7174	19.0000	1.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7767	1110	7175	8.0000	1.8300	2026-07-11 10:12:12	2026-07-11 10:12:12
7768	1110	7176	20.0000	1.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7769	1110	7177	2.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7770	1110	7178	3.0000	27.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7771	1110	7179	8.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7772	1110	7180	22.0000	0.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
7773	1110	7181	14.0000	1.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7774	1110	7182	70.0000	1.9100	2026-07-11 10:12:12	2026-07-11 10:12:12
7775	1110	7183	142.0000	1.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7776	1110	7184	34.0000	2.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7777	1110	7185	1.0000	0.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7778	1110	7186	36.0000	1.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7779	1110	7187	14.0000	3.7600	2026-07-11 10:12:12	2026-07-11 10:12:12
7780	1110	7188	24.0000	4.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7781	1110	7189	23.0000	2.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7782	1110	7190	12.0000	6.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
7783	1110	7191	30.0000	3.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7784	1110	7192	7.0000	6.5700	2026-07-11 10:12:12	2026-07-11 10:12:12
7785	1110	7193	17.0000	9.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7786	1110	7194	1.0000	9.6100	2026-07-11 10:12:12	2026-07-11 10:12:12
7787	1110	7195	2.0000	9.5200	2026-07-11 10:12:12	2026-07-11 10:12:12
7788	1110	7196	2.0000	3.0300	2026-07-11 10:12:12	2026-07-11 10:12:12
7789	1110	7197	44.0000	3.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7790	1110	7198	14.0000	14.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7791	1110	7199	26.0000	6.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7792	1110	7200	8.0000	6.0800	2026-07-11 10:12:12	2026-07-11 10:12:12
7793	1110	7201	25.0000	7.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7794	1110	7202	12.0000	7.6300	2026-07-11 10:12:12	2026-07-11 10:12:12
7795	1110	7203	49.0000	1.3600	2026-07-11 10:12:12	2026-07-11 10:12:12
7796	1110	7204	82.0000	1.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7797	1110	7205	9.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7798	1110	7206	33.0000	1.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7799	1110	7207	33.0000	1.7100	2026-07-11 10:12:12	2026-07-11 10:12:12
7800	1110	7208	4.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7801	1110	7209	15.0000	1.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7802	1110	7210	15.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7803	1110	7211	14.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7804	1110	7212	14.0000	1.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7805	1110	7213	20.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7806	1110	7214	11.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7807	1110	7215	25.0000	1.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7808	1110	7216	23.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7809	1110	7217	51.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7810	1110	7218	41.0000	0.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7811	1110	7219	23.0000	0.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7812	1110	7220	66.0000	1.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7813	1110	7221	98.0000	3.0800	2026-07-11 10:12:12	2026-07-11 10:12:12
7814	1110	7222	6.0000	21.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7815	1110	7223	6.0000	12.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7816	1110	7224	14.0000	4.7900	2026-07-11 10:12:12	2026-07-11 10:12:12
7817	1110	7225	1.0000	5.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7818	1110	7226	9.0000	4.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7819	1110	7227	1.0000	11.6600	2026-07-11 10:12:12	2026-07-11 10:12:12
7820	1110	7228	27.0000	0.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7821	1110	7229	1.0000	0.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7822	1110	7230	41.0000	1.5200	2026-07-11 10:12:12	2026-07-11 10:12:12
7823	1110	7231	12.0000	8.7100	2026-07-11 10:12:12	2026-07-11 10:12:12
7824	1110	7232	63.7500	16.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7825	1110	7233	12.0000	7.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7826	1110	7234	61.0000	0.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7827	1110	7235	734.0000	0.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7828	1110	7236	116.0000	0.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7829	1110	7237	136.0000	0.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7830	1110	7238	30.0000	0.3800	2026-07-11 10:12:12	2026-07-11 10:12:12
7831	1110	7239	58.0000	1.8600	2026-07-11 10:12:12	2026-07-11 10:12:12
7832	1110	7240	32.0000	2.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
7833	1110	7241	37.0000	1.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7834	1110	7242	79.0000	0.6300	2026-07-11 10:12:12	2026-07-11 10:12:12
7835	1110	7243	11.0000	2.6300	2026-07-11 10:12:12	2026-07-11 10:12:12
7836	1110	7244	8.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7837	1110	7245	151.0000	1.6400	2026-07-11 10:12:12	2026-07-11 10:12:12
7838	1110	7246	240.0000	0.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7839	1110	7247	22.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7840	1110	7248	9.0000	4.3100	2026-07-11 10:12:12	2026-07-11 10:12:12
7841	1110	7249	21.0000	6.3400	2026-07-11 10:12:12	2026-07-11 10:12:12
7842	1110	7250	7.0000	3.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7843	1110	7251	5.0000	19.7100	2026-07-11 10:12:12	2026-07-11 10:12:12
7844	1110	7252	6.0000	7.6200	2026-07-11 10:12:12	2026-07-11 10:12:12
7845	1110	7253	22.0000	3.3300	2026-07-11 10:12:12	2026-07-11 10:12:12
7846	1110	7254	28.0000	5.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7847	1110	7255	3.0000	23.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7848	1110	7256	81.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7849	1110	7257	9.0000	5.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7850	1110	7258	25.0000	10.4800	2026-07-11 10:12:12	2026-07-11 10:12:12
7851	1110	7259	133.2900	15.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7852	1110	7260	5.0000	7.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
7853	1110	7261	18.0000	1.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7854	1110	7262	10.0000	6.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7855	1110	7263	60.0000	2.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7856	1110	7264	11.0000	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7857	1110	7265	20.0000	10.4400	2026-07-11 10:12:12	2026-07-11 10:12:12
7858	1110	7266	4.0000	0.5500	2026-07-11 10:12:12	2026-07-11 10:12:12
7859	1110	7267	63.0000	8.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7860	1110	7268	228.0000	4.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7861	1110	7269	192.0000	5.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7862	1110	7270	44.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7863	1110	7271	25.0000	1.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7864	1110	7272	98.0000	1.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7865	1110	7273	11.0000	8.0700	2026-07-11 10:12:12	2026-07-11 10:12:12
7866	1110	7274	7.0000	14.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7867	1110	7275	5.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7868	1110	7276	3.0000	9.1300	2026-07-11 10:12:12	2026-07-11 10:12:12
7869	1110	7277	100.0000	0.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7870	1110	7278	100.0000	0.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7871	1110	7279	8.0000	14.4700	2026-07-11 10:12:12	2026-07-11 10:12:12
7872	1110	7280	2.0000	16.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7873	1110	7281	7.0000	3.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7874	1110	7282	7.0000	10.7300	2026-07-11 10:12:12	2026-07-11 10:12:12
7875	1110	7283	2.0000	5.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7876	1110	7284	530.0000	0.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7877	1110	7285	1.0000	8.1900	2026-07-11 10:12:12	2026-07-11 10:12:12
7878	1110	7286	246.0000	0.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7879	1110	7287	91.0000	2.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7880	1110	7288	99.0000	2.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7881	1110	7289	64.0000	1.7200	2026-07-11 10:12:12	2026-07-11 10:12:12
7882	1110	7290	108.0000	2.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7883	1110	7291	67.0000	13.6300	2026-07-11 10:12:12	2026-07-11 10:12:12
7884	1110	7292	1988.0000	0.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7885	1110	7293	306.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7886	1110	7294	237.0000	0.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7887	1110	7295	11.0000	7.1500	2026-07-11 10:12:12	2026-07-11 10:12:12
7888	1110	7296	5.0000	9.6100	2026-07-11 10:12:12	2026-07-11 10:12:12
7889	1110	7297	57.0000	21.7500	2026-07-11 10:12:12	2026-07-11 10:12:12
7890	1110	7298	24.0000	38.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7891	1110	7299	10.0000	73.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7892	1110	7300	79.0000	0.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7893	1110	7301	15.0000	19.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7894	1110	7302	84.0000	7.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7895	1110	7303	6.0000	7.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7896	1110	7304	126.0000	18.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7897	1110	7305	2.0000	89.7600	2026-07-11 10:12:12	2026-07-11 10:12:12
7898	1110	7306	16.0000	4.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7899	1110	7307	348.0000	2.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
7900	1110	7308	46.0000	2.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7901	1110	7309	2.0000	568.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7902	1110	7310	8.0000	500.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7903	1110	7311	69.0000	0.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7904	1110	7312	83.0000	0.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7905	1110	7313	34.0000	0.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7906	1110	7314	7.0000	1.4800	2026-07-11 10:12:12	2026-07-11 10:12:12
7907	1110	7315	71.0000	1.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7908	1110	7316	50.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7909	1110	7317	50.0000	2.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7910	1110	7318	49.0000	0.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7911	1110	7319	62.0000	1.2900	2026-07-11 10:12:12	2026-07-11 10:12:12
7912	1110	7320	89.0000	0.2600	2026-07-11 10:12:12	2026-07-11 10:12:12
7913	1110	7321	52.0000	1.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7914	1110	7322	23.0000	0.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
7915	1110	7323	13.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7916	1110	7324	25.0000	1.6200	2026-07-11 10:12:12	2026-07-11 10:12:12
7917	1110	7325	50.0000	1.0400	2026-07-11 10:12:12	2026-07-11 10:12:12
7918	1110	7326	22.0000	2.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7919	1110	7327	53.0000	1.3900	2026-07-11 10:12:12	2026-07-11 10:12:12
7920	1110	7328	37.0000	0.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
7921	1110	7329	25.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7922	1110	7330	131.0000	1.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7923	1110	7331	39.0000	1.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7924	1110	7332	25.0000	1.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
7925	1110	7333	154.0000	1.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7926	1110	7334	914.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7927	1110	7335	587.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7928	1110	7336	1000.0000	0.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
7929	1110	7337	150.0000	6.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7930	1110	7338	24.0000	2.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7931	1110	7339	46.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7932	1110	7340	40.0000	2.7800	2026-07-11 10:12:12	2026-07-11 10:12:12
7933	1110	7341	38.0000	1.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
7934	1110	7342	5.0000	2.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7935	1110	7343	13.0000	4.8400	2026-07-11 10:12:12	2026-07-11 10:12:12
7936	1110	7344	23.0000	3.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7937	1110	7345	61.0000	6.2400	2026-07-11 10:12:12	2026-07-11 10:12:12
7938	1110	7346	27.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7939	1110	7347	48.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7940	1110	7348	108.0000	2.1900	2026-07-11 10:12:12	2026-07-11 10:12:12
7941	1110	7349	110.0000	0.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
7942	1110	7350	53.0000	3.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7943	1110	7351	51.0000	1.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7944	1110	7352	39.0000	3.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7945	1110	7353	50.0000	3.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7946	1110	7354	13.0000	10.1500	2026-07-11 10:12:12	2026-07-11 10:12:12
7947	1110	7355	6.0000	0.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7948	1110	7356	24.0000	8.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7949	1110	7357	22.0000	4.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
7950	1110	7358	22.0000	10.0800	2026-07-11 10:12:12	2026-07-11 10:12:12
7951	1110	7359	23.0000	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7952	1110	7360	52.0000	1.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7953	1110	7361	20.0000	4.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
7954	1110	7362	34.0000	18.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7955	1110	7363	8.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7956	1110	7364	3.5000	35.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7957	1110	7365	400.0000	0.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7958	1110	7366	66.0000	0.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
7959	1110	7367	1121.0000	0.1800	2026-07-11 10:12:12	2026-07-11 10:12:12
7960	1110	7368	417.0000	0.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7961	1110	7369	251.0000	0.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
7962	1110	7370	12.0000	10.4300	2026-07-11 10:12:12	2026-07-11 10:12:12
7963	1110	7371	19.0000	15.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
7964	1110	7372	116.0000	2.0700	2026-07-11 10:12:12	2026-07-11 10:12:12
7965	1110	7373	6.0000	4.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7966	1110	7374	15.0000	9.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7967	1110	7375	76.0000	1.1100	2026-07-11 10:12:12	2026-07-11 10:12:12
7968	1110	7376	58.0000	1.8200	2026-07-11 10:12:12	2026-07-11 10:12:12
7969	1110	7377	12.0000	2.0100	2026-07-11 10:12:12	2026-07-11 10:12:12
7970	1110	7378	10.0000	6.0500	2026-07-11 10:12:12	2026-07-11 10:12:12
7971	1110	7379	7.0000	9.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
7972	1110	7380	20.0000	5.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7973	1110	7381	13.0000	3.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
7974	1110	7382	18.0000	1.7900	2026-07-11 10:12:12	2026-07-11 10:12:12
7975	1110	7383	31.0000	2.2100	2026-07-11 10:12:12	2026-07-11 10:12:12
7976	1110	7384	9.0000	23.5100	2026-07-11 10:12:12	2026-07-11 10:12:12
7977	1110	7385	20.0000	39.4900	2026-07-11 10:12:12	2026-07-11 10:12:12
7978	1110	7386	11.0000	32.9800	2026-07-11 10:12:12	2026-07-11 10:12:12
7979	1110	7387	30.0000	20.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7980	1110	7388	26.0000	9.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
7981	1110	7389	31.0000	17.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7982	1110	7390	24.0000	9.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7983	1110	7391	69.0000	12.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7984	1110	7392	1.0000	13.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
7985	1110	7393	9.0000	12.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7986	1110	7394	7.0000	15.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
7987	1110	7395	87.0000	12.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7988	1110	7396	57.0000	6.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
7989	1110	7397	22.0000	32.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
7990	1110	7398	19.0000	13.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7991	1110	7399	141.0000	33.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7992	1110	7400	56.0000	8.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
7993	1110	7401	181.0000	4.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
7994	1110	7402	50.0000	5.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
7995	1110	7403	1836.0000	0.0900	2026-07-11 10:12:12	2026-07-11 10:12:12
7996	1110	7404	32.0000	1.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
7997	1110	7405	12.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
7998	1110	7406	61.0000	1.0600	2026-07-11 10:12:12	2026-07-11 10:12:12
7999	1110	7407	120.0000	0.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8000	1110	7408	24.0000	2.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8001	1110	7409	3.0000	8.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
8002	1110	7410	58.0000	2.2500	2026-07-11 10:12:12	2026-07-11 10:12:12
8003	1110	7411	18.0000	0.8500	2026-07-11 10:12:12	2026-07-11 10:12:12
8004	1110	7412	9.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
8005	1110	7413	65.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
8006	1110	7414	20.0000	2.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
8007	1110	7415	10.0000	1.5300	2026-07-11 10:12:12	2026-07-11 10:12:12
8008	1110	7416	21.0000	2.4400	2026-07-11 10:12:12	2026-07-11 10:12:12
8009	1110	7417	44.0000	2.9300	2026-07-11 10:12:12	2026-07-11 10:12:12
8010	1110	7418	23.0000	0.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8011	1110	7419	23.0000	1.5600	2026-07-11 10:12:12	2026-07-11 10:12:12
8012	1110	7420	26.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8013	1110	7421	44.0000	0.8000	2026-07-11 10:12:12	2026-07-11 10:12:12
8014	1110	7422	85.0000	0.3700	2026-07-11 10:12:12	2026-07-11 10:12:12
8015	1110	7423	138.0000	0.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
8016	1110	7424	66.0000	1.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
8017	1110	7425	48.0000	2.1400	2026-07-11 10:12:12	2026-07-11 10:12:12
8018	1110	7426	22.0000	1.5900	2026-07-11 10:12:12	2026-07-11 10:12:12
8019	1110	7427	18.0000	2.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
8020	1110	7428	94.0000	1.8900	2026-07-11 10:12:12	2026-07-11 10:12:12
8021	1110	7429	15.0000	0.6700	2026-07-11 10:12:12	2026-07-11 10:12:12
8022	1110	7430	4.0000	0.7000	2026-07-11 10:12:12	2026-07-11 10:12:12
8023	1110	7431	11.0000	1.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8024	1110	7432	17.0000	3.2200	2026-07-11 10:12:12	2026-07-11 10:12:12
8025	1110	7433	7.0000	5.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
8026	1110	7434	59.0000	2.5800	2026-07-11 10:12:12	2026-07-11 10:12:12
8027	1110	7435	85.0000	2.4000	2026-07-11 10:12:12	2026-07-11 10:12:12
8028	1110	7436	31.0000	4.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
8029	1110	7437	34.0000	3.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
8030	1110	7438	5.0000	2.1000	2026-07-11 10:12:12	2026-07-11 10:12:12
8031	1110	7439	16.0000	3.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
8032	1110	7440	10.0000	8.7700	2026-07-11 10:12:12	2026-07-11 10:12:12
8033	1110	7441	12.0000	16.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8034	1110	7442	41.0000	0.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
8035	1110	7443	8.0000	16.6900	2026-07-11 10:12:12	2026-07-11 10:12:12
8036	1110	7444	5.0000	83.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
8037	1110	7445	12.0000	14.5000	2026-07-11 10:12:12	2026-07-11 10:12:12
8038	1110	7446	4.0000	0.6500	2026-07-11 10:12:12	2026-07-11 10:12:12
8039	1110	7447	20.0000	1.8100	2026-07-11 10:12:12	2026-07-11 10:12:12
8040	1110	7448	4.0000	5.0200	2026-07-11 10:12:12	2026-07-11 10:12:12
8041	1110	7449	3.0000	5.9900	2026-07-11 10:12:12	2026-07-11 10:12:12
8042	1110	7450	6.0000	3.7200	2026-07-11 10:12:12	2026-07-11 10:12:12
8043	1110	7451	6.0000	12.1200	2026-07-11 10:12:12	2026-07-11 10:12:12
8044	1110	7452	2.0000	21.7200	2026-07-11 10:12:12	2026-07-11 10:12:12
8045	1110	7453	5.0000	7.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
8046	1110	7454	1.0000	11.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
8047	1110	7455	9.0000	9.9000	2026-07-11 10:12:12	2026-07-11 10:12:12
8048	1110	7456	764.0000	3.3000	2026-07-11 10:12:12	2026-07-11 10:12:12
8049	1110	7457	25.0000	2.8800	2026-07-11 10:12:12	2026-07-11 10:12:12
8050	1110	7458	49.0000	4.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
8051	1110	7459	77.0000	2.4200	2026-07-11 10:12:12	2026-07-11 10:12:12
8052	1110	7460	9.0000	7.9200	2026-07-11 10:12:12	2026-07-11 10:12:12
8053	1110	7461	12.0000	3.6200	2026-07-11 10:12:12	2026-07-11 10:12:12
8054	1110	7462	18.0000	8.9100	2026-07-11 10:12:12	2026-07-11 10:12:12
8055	1110	7463	6.0000	3.9100	2026-07-11 10:12:12	2026-07-11 10:12:12
8056	1110	7464	8.0000	12.0000	2026-07-11 10:12:12	2026-07-11 10:12:12
8057	1110	7465	18.0000	14.6000	2026-07-11 10:12:12	2026-07-11 10:12:12
8058	1110	7466	13.0000	9.3200	2026-07-11 10:12:12	2026-07-11 10:12:12
8059	1110	7467	2.0000	0.2000	2026-07-11 10:12:12	2026-07-11 10:12:12
8060	1110	7468	5.0000	14.2400	2026-07-11 10:12:12	2026-07-11 10:12:12
8061	1110	7469	1.0000	98.1600	2026-07-11 10:12:12	2026-07-11 10:12:12
\.


--
-- Data for Name: tipos_cambio; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tipos_cambio (id, fecha, moneda, tasa, fuente, raw, created_at, updated_at) FROM stdin;
1	2026-07-08	USD	3.409000	decolecta_sbs_accounting	{"date": "2026-07-08", "price": "3.409000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-09 11:44:50	2026-07-09 11:44:50
2	2025-08-08	USD	3.531000	decolecta_sbs_accounting	{"date": "2025-08-08", "price": "3.531000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-09 12:04:45	2026-07-09 12:04:45
3	2026-07-09	USD	3.402000	decolecta_sbs_accounting	{"date": "2026-07-09", "price": "3.402000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-09 19:49:08	2026-07-09 19:49:08
4	2026-07-11	USD	3.393000	decolecta_sbs_accounting	{"date": "2026-07-10", "price": "3.393000", "base_currency": "USD", "quote_currency": "PEN"}	2026-07-11 08:57:30	2026-07-11 08:57:30
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
638	1097	1092	1100	1295	\N	0.00	0.00	\N	\N	\N	cerrado	2026-07-10 08:00:00	2026-07-10 20:00:00	\N	\N	2026-07-11 10:12:11	2026-07-11 10:12:11
209	1	1	1	2	2	200.00	0.00	200.00	200.00	0.00	cerrado	2026-07-02 08:30:00	2026-07-02 18:30:00	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
210	1	1	1	2	2	200.00	0.00	2200.00	2200.00	0.00	cerrado	2026-07-03 08:30:00	2026-07-03 18:30:00	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
211	1	1	1	2	2	200.00	0.00	1743.50	1769.00	-25.50	cerrado	2026-07-04 08:30:00	2026-07-04 18:30:00	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
212	1	1	1	2	\N	200.00	0.00	\N	\N	\N	abierto	2026-07-05 08:30:00	\N	\N	\N	2026-07-05 19:27:37	2026-07-05 19:27:37
213	1	1	312	1	1	200.00	0.00	120.00	200.00	-80.00	cerrado	2026-07-05 20:05:11	2026-07-05 20:30:00	\N	\N	2026-07-05 20:05:11	2026-07-05 20:30:00
\.


--
-- Data for Name: unidades_medida; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.unidades_medida (id, empresa_id, nombre, abreviatura, activo, created_at, updated_at) FROM stdin;
1	1	Unidad	UND	t	2026-05-18 01:53:39	2026-05-18 01:53:39
1092	1097	Unidad	UND	t	2026-07-11 10:12:11	2026-07-11 10:12:11
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, empresa_id, local_id, rol_id, name, email, email_verified_at, password, activo, remember_token, created_at, updated_at) FROM stdin;
1	1	1	1	Jesús	jesus@gmail.com	2026-05-18 01:53:39	$2y$12$zlXBFY8O1fDfJUR0.w.9P.GvI0NIzJcYaHEmn6SHtjUIAUVkFZ.VC	t	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
2	1	1	2	Cajera	cajera@gmail.com	2026-05-18 01:53:39	$2y$12$nK5vzxRZrV/hA7kOEuwy5eIHNn6JQuk142w2wpS2knYUK3.Y1lVK6	t	\N	2026-05-18 01:53:39	2026-05-18 01:53:39
1295	1097	1092	1168	Administrador H&C	admin@ferreteriahyc.com	2026-07-11 10:12:11	$2y$12$hMO3.gY7aFAjB4XfIXl0iOY8yuKD.271K0RjkdDIoYCt8I3iPLlRy	t	\N	2026-07-11 10:12:11	2026-07-11 10:12:11
1296	1097	1092	1169	Cajera 1	cajera1@ferreteriahyc.com	2026-07-11 10:12:11	$2y$12$MdrXJByLMLtb36iID5kDoug3mieApnTRVOwWezb.vwUFYlIzt89lu	t	\N	2026-07-11 10:12:11	2026-07-11 10:12:11
1297	1097	1092	1169	Cajera 2	cajera2@ferreteriahyc.com	2026-07-11 10:12:11	$2y$12$O0kxCIhh2veBHpk.Spd3pe0q8CmxyFA835k9Jc1k16UKMG0rJgS8C	t	\N	2026-07-11 10:12:12	2026-07-11 10:12:12
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
1092	1097	1092	638	1100	1295	1583	MIG-6a525d4c50b55	ticket	30.00	0.00	\N	0.00	30.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	30.00	\N	PEN	\N	\N
1093	1097	1092	638	1100	1295	1584	MIG-6a525d4c50ced	ticket	239.00	0.00	\N	0.00	239.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	239.00	\N	PEN	\N	\N
1094	1097	1092	638	1100	1295	1585	MIG-6a525d4c50e3d	ticket	5618.00	0.00	\N	0.00	5618.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	5618.00	\N	PEN	\N	\N
1095	1097	1092	638	1100	1295	1586	MIG-6a525d4c50f61	ticket	343.00	0.00	\N	0.00	343.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	343.00	\N	PEN	\N	\N
1096	1097	1092	638	1100	1295	1587	MIG-6a525d4c5108b	ticket	185.50	0.00	\N	0.00	185.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	185.50	\N	PEN	\N	\N
1097	1097	1092	638	1100	1295	1588	MIG-6a525d4c511c4	ticket	150.04	0.00	\N	0.00	150.04	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	150.04	\N	PEN	\N	\N
1098	1097	1092	638	1100	1295	1589	MIG-6a525d4c512fb	ticket	622.10	0.00	\N	0.00	622.10	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	622.10	\N	PEN	\N	\N
1099	1097	1092	638	1100	1295	1590	MIG-6a525d4c51400	ticket	200.00	0.00	\N	0.00	200.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	200.00	\N	PEN	\N	\N
1100	1097	1092	638	1100	1295	1591	MIG-6a525d4c514f8	ticket	265.50	0.00	\N	0.00	265.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	265.50	\N	PEN	\N	\N
1101	1097	1092	638	1100	1295	1592	MIG-6a525d4c515e6	ticket	263.50	0.00	\N	0.00	263.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	263.50	\N	PEN	\N	\N
1102	1097	1092	638	1100	1295	1593	MIG-6a525d4c516d3	ticket	163.00	0.00	\N	0.00	163.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	163.00	\N	PEN	\N	\N
1103	1097	1092	638	1100	1295	1594	MIG-6a525d4c517c6	ticket	2310.00	0.00	\N	0.00	2310.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	2310.00	\N	PEN	\N	\N
160	1	1	210	1	2	339	V-0201	ticket	8376.40	0.00	\N	0.00	8376.40	completada	\N	2026-07-03 09:40:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	2000.00	6376.40	2026-07-17	PEN	\N	\N
1104	1097	1092	638	1100	1295	1595	MIG-6a525d4c518ae	ticket	117.50	0.00	\N	0.00	117.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	117.50	\N	PEN	\N	\N
162	1	1	211	1	2	1	V-0401	ticket	849.00	0.00	\N	0.00	849.00	completada	\N	2026-07-04 09:10:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	849.00	0.00	\N	PEN	\N	\N
163	1	1	211	1	2	1	V-0402	ticket	1210.00	0.00	\N	0.00	1210.00	completada	\N	2026-07-04 11:25:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	1210.00	0.00	\N	PEN	\N	\N
164	1	1	211	1	2	338	V-0403	ticket	3172.00	0.00	\N	0.00	3172.00	completada	\N	2026-07-04 12:40:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	3172.00	0.00	\N	PEN	\N	\N
165	1	1	211	1	2	1	V-0404	ticket	430.00	0.00	\N	0.00	430.00	completada	\N	2026-07-04 16:50:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	430.00	0.00	\N	PEN	\N	\N
166	1	1	211	1	2	340	V-0405	ticket	4794.00	0.00	\N	0.00	4794.00	completada	\N	2026-07-04 15:20:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	800.00	3994.00	2026-07-18	PEN	\N	\N
159	1	1	209	1	2	337	V-0101	ticket	44960.00	0.00	\N	0.00	44960.00	completada	\N	2026-07-02 10:15:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	20000.00	24960.00	2026-07-31	PEN	\N	\N
167	1	1	212	1	2	1	V-0501	ticket	1660.00	0.00	\N	0.00	1660.00	completada	\N	2026-07-05 09:05:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	1660.00	0.00	\N	PEN	\N	\N
168	1	1	212	1	2	341	V-0502	ticket	877.50	0.00	\N	0.00	877.50	completada	\N	2026-07-05 10:35:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	f	877.50	0.00	\N	PEN	\N	\N
161	1	1	210	1	2	338	V-0202	ticket	42875.00	0.00	\N	0.00	42875.00	completada	\N	2026-07-03 16:05:00	2026-07-05 19:27:37	2026-07-05 19:27:37	\N	t	11500.00	31375.00	2026-07-25	PEN	\N	\N
1105	1097	1092	638	1100	1295	1596	MIG-6a525d4c519a0	ticket	1669.00	0.00	\N	0.00	1669.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1669.00	\N	PEN	\N	\N
1106	1097	1092	638	1100	1295	1597	MIG-6a525d4c51a8d	ticket	2710.00	0.00	\N	0.00	2710.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	2710.00	\N	PEN	\N	\N
1107	1097	1092	638	1100	1295	1598	MIG-6a525d4c51b81	ticket	2740.80	0.00	\N	0.00	2740.80	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	2740.80	\N	PEN	\N	\N
170	1	1	213	312	1	4	V-0001	ticket	268.60	0.00	\N	40.97	268.60	completada	\N	2026-07-05 20:17:50	2026-07-05 20:17:50	2026-07-05 20:19:19	0a7a7b1f-10e6-4f2e-af12-def8a86f3942	t	268.60	0.00	\N	PEN	\N	\N
1108	1097	1092	638	1100	1295	1599	MIG-6a525d4c51c67	ticket	75.00	0.00	\N	0.00	75.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	75.00	\N	PEN	\N	\N
1109	1097	1092	638	1100	1295	1600	MIG-6a525d4c51d4e	ticket	209.50	0.00	\N	0.00	209.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	209.50	\N	PEN	\N	\N
1110	1097	1092	638	1100	1295	1601	MIG-6a525d4c51e57	ticket	1932.00	0.00	\N	0.00	1932.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1932.00	\N	PEN	\N	\N
1111	1097	1092	638	1100	1295	1602	MIG-6a525d4c51f51	ticket	312.00	0.00	\N	0.00	312.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	312.00	\N	PEN	\N	\N
1112	1097	1092	638	1100	1295	1603	MIG-6a525d4c52067	ticket	500.00	0.00	\N	0.00	500.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	500.00	\N	PEN	\N	\N
1113	1097	1092	638	1100	1295	1604	MIG-6a525d4c5217e	ticket	88.80	0.00	\N	0.00	88.80	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	88.80	\N	PEN	\N	\N
1114	1097	1092	638	1100	1295	1605	MIG-6a525d4c52273	ticket	737.00	0.00	\N	0.00	737.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	737.00	\N	PEN	\N	\N
1115	1097	1092	638	1100	1295	1606	MIG-6a525d4c52360	ticket	338.00	0.00	\N	0.00	338.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	338.00	\N	PEN	\N	\N
1116	1097	1092	638	1100	1295	1607	MIG-6a525d4c52447	ticket	100.40	0.00	\N	0.00	100.40	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	100.40	\N	PEN	\N	\N
1117	1097	1092	638	1100	1295	1608	MIG-6a525d4c52540	ticket	4468.00	0.00	\N	0.00	4468.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	4468.00	\N	PEN	\N	\N
1118	1097	1092	638	1100	1295	1609	MIG-6a525d4c5262b	ticket	1380.00	0.00	\N	0.00	1380.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1380.00	\N	PEN	\N	\N
1119	1097	1092	638	1100	1295	1610	MIG-6a525d4c52726	ticket	3180.59	0.00	\N	0.00	3180.59	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	3180.59	\N	PEN	\N	\N
1120	1097	1092	638	1100	1295	1611	MIG-6a525d4c52803	ticket	614.00	0.00	\N	0.00	614.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	614.00	\N	PEN	\N	\N
1121	1097	1092	638	1100	1295	1612	MIG-6a525d4c528e2	ticket	6540.00	0.00	\N	0.00	6540.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	6540.00	\N	PEN	\N	\N
1122	1097	1092	638	1100	1295	1613	MIG-6a525d4c529ce	ticket	498.00	0.00	\N	0.00	498.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	498.00	\N	PEN	\N	\N
1123	1097	1092	638	1100	1295	1614	MIG-6a525d4c52abb	ticket	150.00	0.00	\N	0.00	150.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	150.00	\N	PEN	\N	\N
1124	1097	1092	638	1100	1295	1615	MIG-6a525d4c52ba3	ticket	89.00	0.00	\N	0.00	89.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	89.00	\N	PEN	\N	\N
1125	1097	1092	638	1100	1295	1616	MIG-6a525d4c52c8d	ticket	167.50	0.00	\N	0.00	167.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	167.50	\N	PEN	\N	\N
1126	1097	1092	638	1100	1295	1617	MIG-6a525d4c52d9a	ticket	1007.50	0.00	\N	0.00	1007.50	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1007.50	\N	PEN	\N	\N
1127	1097	1092	638	1100	1295	1618	MIG-6a525d4c52e8d	ticket	4598.00	0.00	\N	0.00	4598.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	4598.00	\N	PEN	\N	\N
1128	1097	1092	638	1100	1295	1619	MIG-6a525d4c52f9b	ticket	1030.00	0.00	\N	0.00	1030.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1030.00	\N	PEN	\N	\N
1129	1097	1092	638	1100	1295	1620	MIG-6a525d4c53089	ticket	682.00	0.00	\N	0.00	682.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	682.00	\N	PEN	\N	\N
1130	1097	1092	638	1100	1295	1621	MIG-6a525d4c53190	ticket	392.00	0.00	\N	0.00	392.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	392.00	\N	PEN	\N	\N
1131	1097	1092	638	1100	1295	1622	MIG-6a525d4c53296	ticket	1005.60	0.00	\N	0.00	1005.60	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1005.60	\N	PEN	\N	\N
1132	1097	1092	638	1100	1295	1623	MIG-6a525d4c53390	ticket	225.00	0.00	\N	0.00	225.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	225.00	\N	PEN	\N	\N
1133	1097	1092	638	1100	1295	1624	MIG-6a525d4c5347f	ticket	45.00	0.00	\N	0.00	45.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	45.00	\N	PEN	\N	\N
1134	1097	1092	638	1100	1295	1625	MIG-6a525d4c5356b	ticket	155.00	0.00	\N	0.00	155.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	155.00	\N	PEN	\N	\N
1135	1097	1092	638	1100	1295	1626	MIG-6a525d4c5367b	ticket	500.00	0.00	\N	0.00	500.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	500.00	\N	PEN	\N	\N
1136	1097	1092	638	1100	1295	1627	MIG-6a525d4c5377b	ticket	6296.60	0.00	\N	0.00	6296.60	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	6296.60	\N	PEN	\N	\N
1137	1097	1092	638	1100	1295	1628	MIG-6a525d4c53869	ticket	11077.25	0.00	\N	0.00	11077.25	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	11077.25	\N	PEN	\N	\N
1138	1097	1092	638	1100	1295	1629	MIG-6a525d4c53954	ticket	10360.00	0.00	\N	0.00	10360.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	10360.00	\N	PEN	\N	\N
1139	1097	1092	638	1100	1295	1630	MIG-6a525d4c53a38	ticket	205.70	0.00	\N	0.00	205.70	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	205.70	\N	PEN	\N	\N
1140	1097	1092	638	1100	1295	1631	MIG-6a525d4c53b2b	ticket	1078.00	0.00	\N	0.00	1078.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	1078.00	\N	PEN	\N	\N
1141	1097	1092	638	1100	1295	1632	MIG-6a525d4c53c17	ticket	56.00	0.00	\N	0.00	56.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	56.00	\N	PEN	\N	\N
1142	1097	1092	638	1100	1295	1633	MIG-6a525d4c53d07	ticket	4756.00	0.00	\N	0.00	4756.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	4756.00	\N	PEN	\N	\N
1143	1097	1092	638	1100	1295	1634	MIG-6a525d4c53e08	ticket	539.00	0.00	\N	0.00	539.00	completada	Saldo migrado del sistema anterior	2026-07-10 12:00:00	2026-07-11 10:12:12	2026-07-11 10:12:12	\N	t	0.00	539.00	\N	PEN	\N	\N
\.


--
-- Name: almacenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.almacenes_id_seq', 1114, true);


--
-- Name: auditoria_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.auditoria_id_seq', 402, true);


--
-- Name: balance_diario_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.balance_diario_items_id_seq', 2279, true);


--
-- Name: balances_diarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.balances_diarios_id_seq', 67, true);


--
-- Name: cajas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cajas_id_seq', 1105, true);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_seq', 1108, true);


--
-- Name: cierres_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cierres_inventario_id_seq', 18, true);


--
-- Name: cierres_inventario_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cierres_inventario_items_id_seq', 1, false);


--
-- Name: cita_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cita_items_id_seq', 104, true);


--
-- Name: citas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.citas_id_seq', 104, true);


--
-- Name: cliente_anticipo_aplicaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cliente_anticipo_aplicaciones_id_seq', 8, true);


--
-- Name: cliente_anticipos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cliente_anticipos_id_seq', 199, true);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 1658, true);


--
-- Name: cuenta_metodo_pago_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuenta_metodo_pago_id_seq', 43, true);


--
-- Name: cuenta_movimientos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuenta_movimientos_id_seq', 461, true);


--
-- Name: cuentas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_id_seq', 294, true);


--
-- Name: descuento_conceptos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.descuento_conceptos_id_seq', 1093, true);


--
-- Name: descuentos_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.descuentos_log_id_seq', 9, true);


--
-- Name: deuda_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.deuda_pagos_id_seq', 17, true);


--
-- Name: deudas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.deudas_id_seq', 73, true);


--
-- Name: devolucion_motivos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devolucion_motivos_id_seq', 4358, true);


--
-- Name: devolucion_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devolucion_pagos_id_seq', 63, true);


--
-- Name: devoluciones_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devoluciones_detalle_id_seq', 63, true);


--
-- Name: devoluciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devoluciones_id_seq', 63, true);


--
-- Name: empresas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresas_id_seq', 1101, true);


--
-- Name: entrada_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.entrada_pagos_id_seq', 17, true);


--
-- Name: entradas_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.entradas_detalle_id_seq', 70, true);


--
-- Name: entradas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.entradas_id_seq', 78, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: gasto_conceptos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.gasto_conceptos_id_seq', 82, true);


--
-- Name: gasto_tipos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.gasto_tipos_id_seq', 47, true);


--
-- Name: gastos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.gastos_id_seq', 57, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: locales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.locales_id_seq', 1096, true);


--
-- Name: metodos_pago_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.metodos_pago_id_seq', 5480, true);


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

SELECT pg_catalog.setval('public.permisos_id_seq', 95, true);


--
-- Name: planilla_descuentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.planilla_descuentos_id_seq', 9, true);


--
-- Name: producto_unidades_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.producto_unidades_id_seq', 7469, true);


--
-- Name: productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.productos_id_seq', 7469, true);


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

SELECT pg_catalog.setval('public.proveedores_id_seq', 54, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 1173, true);


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

SELECT pg_catalog.setval('public.stock_id_seq', 8061, true);


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tipos_cambio_id_seq', 4, true);


--
-- Name: tipos_metodo_pago_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tipos_metodo_pago_id_seq', 7, true);


--
-- Name: transferencias_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transferencias_detalle_id_seq', 9, true);


--
-- Name: transferencias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transferencias_id_seq', 9, true);


--
-- Name: turno_arqueo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_arqueo_id_seq', 130, true);


--
-- Name: turno_arqueo_metodos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_arqueo_metodos_id_seq', 6, true);


--
-- Name: turno_cierre_productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turno_cierre_productos_id_seq', 50, true);


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

SELECT pg_catalog.setval('public.turnos_id_seq', 642, true);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.unidades_medida_id_seq', 1096, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 1301, true);


--
-- Name: venta_abonos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.venta_abonos_id_seq', 10, true);


--
-- Name: venta_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.venta_items_id_seq', 465, true);


--
-- Name: venta_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.venta_pagos_id_seq', 423, true);


--
-- Name: ventas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ventas_id_seq', 1143, true);


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

\unrestrict Zg8lrboonGheibpKDFVhmuegk7njezjwIgDQIH3vUb3OM5RC6JmOYzTZHuE8x14

