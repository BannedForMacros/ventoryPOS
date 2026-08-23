-- Script de creación de la tabla cliente_anticipo_cancelaciones
-- Ejecutar en PostgreSQL de producción (o cualquier entorno) ANTES de usar
-- el botón "Cancelar pendiente" en Finanzas → Anticipos.

BEGIN;

CREATE SEQUENCE IF NOT EXISTS public.cliente_anticipo_cancelaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS public.cliente_anticipo_cancelaciones (
    id bigint NOT NULL DEFAULT nextval('public.cliente_anticipo_cancelaciones_id_seq'::regclass),
    cliente_anticipo_id bigint NOT NULL,
    cliente_anticipo_item_id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    user_id bigint NOT NULL,
    fecha date NOT NULL,
    cantidad numeric(16,4) NOT NULL,
    monto numeric(12,2) NOT NULL,
    motivo text NOT NULL,
    turno_id bigint,
    caja_id bigint,
    metodo_pago_id bigint,
    cuenta_id bigint,
    observacion character varying(500),
    moneda character varying(3) DEFAULT 'PEN'::character varying NOT NULL,
    tipo_cambio numeric(12,6),
    monto_moneda numeric(12,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT cliente_anticipo_cancelaciones_pkey PRIMARY KEY (id)
);

ALTER SEQUENCE public.cliente_anticipo_cancelaciones_id_seq
    OWNED BY public.cliente_anticipo_cancelaciones.id;

CREATE INDEX IF NOT EXISTS cliente_anticipo_cancelaciones_cliente_anticipo_id_index
    ON public.cliente_anticipo_cancelaciones USING btree (cliente_anticipo_id);

CREATE INDEX IF NOT EXISTS cliente_anticipo_cancelaciones_empresa_id_fecha_index
    ON public.cliente_anticipo_cancelaciones USING btree (empresa_id, fecha);

CREATE INDEX IF NOT EXISTS cliente_anticipo_cancelaciones_turno_id_index
    ON public.cliente_anticipo_cancelaciones USING btree (turno_id);

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_caja_id_foreign
    FOREIGN KEY (caja_id) REFERENCES public.cajas(id);

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_cliente_anticipo_id_foreign
    FOREIGN KEY (cliente_anticipo_id) REFERENCES public.cliente_anticipos(id) ON DELETE CASCADE;

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_cliente_anticipo_item_id_foreign
    FOREIGN KEY (cliente_anticipo_item_id) REFERENCES public.cliente_anticipo_items(id) ON DELETE CASCADE;

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_cuenta_id_foreign
    FOREIGN KEY (cuenta_id) REFERENCES public.cuentas(id);

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_empresa_id_foreign
    FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_metodo_pago_id_foreign
    FOREIGN KEY (metodo_pago_id) REFERENCES public.metodos_pago(id);

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_turno_id_foreign
    FOREIGN KEY (turno_id) REFERENCES public.turnos(id);

ALTER TABLE public.cliente_anticipo_cancelaciones
    ADD CONSTRAINT cliente_anticipo_cancelaciones_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES public.users(id);

COMMIT;
