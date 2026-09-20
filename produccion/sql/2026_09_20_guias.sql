-- ============================================================================
--  ventoryPOS · Guías de remisión (espejo local de FacturaMac)
--  Fecha: 2026-09-20
--
--  QUÉ HACE: crea UNA TABLA NUEVA. No toca ninguna fila existente, no modifica
--  ninguna tabla en uso, y es idempotente.
--
--  NINGUNA VENTA NI NINGÚN DESPACHO CAMBIA DE COMPORTAMIENTO. La tabla nace vacía
--  y nada del flujo de caja la consulta. Una guía es el DOCUMENTO de un movimiento
--  de mercadería; el movimiento lo siguen haciendo la venta, el despacho y la
--  transferencia, exactamente como hasta ahora.
--
--  POR QUÉ SE GUARDA COPIA: la guía se imprime en el almacén, con el camión
--  esperando, y no puede depender de que FacturaMac responda en ese momento. Aquí
--  vive lo justo para listarla, buscarla y saber si el camión puede salir.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`. Hay
--  migraciones que figuran como pendientes aunque sus tablas existan, y lanzarlo
--  intentaría rehacerlas (comprobado: falla en cliente_anticipo_cancelaciones).
--  Este script es la única vía.
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS public.guias (
    id                  bigserial PRIMARY KEY,
    empresa_id          bigint NOT NULL,

    -- Las tres opcionales: una guía puede nacer sola. Un traslado entre almacenes
    -- no tiene venta, y a veces se despacha algo que todavía no se facturó.
    venta_id            bigint,
    transferencia_id    bigint,
    cliente_id          bigint,

    facturamac_id       integer,
    serie               character varying(4),
    correlativo         integer,
    numero              character varying(20),
    estado              character varying(30) DEFAULT 'pendiente' NOT NULL,

    -- Lo manda FacturaMac ya resuelto. NO se deduce aquí del estado con una lista
    -- propia: ese fue el fallo que costó dos bugs fiscales con los comprobantes.
    puede_trasladar     boolean DEFAULT false NOT NULL,
    aviso               character varying(255),

    sunat_codigo        character varying(255),
    sunat_descripcion   text,
    error               text,
    intentos            integer DEFAULT 0 NOT NULL,
    enviado_at          timestamp(0) without time zone,

    payload             json,
    motivo              character varying(40),
    modalidad           character varying(10),
    fecha_traslado      date,
    destinatario        character varying(255),
    llegada_direccion   character varying(255),

    idempotency_key     character varying(100),
    referencia_externa  character varying(100),
    user_id             bigint,
    created_at          timestamp(0) without time zone,
    updated_at          timestamp(0) without time zone
);

ALTER TABLE public.guias DROP CONSTRAINT IF EXISTS guias_empresa_id_foreign;
ALTER TABLE public.guias ADD  CONSTRAINT guias_empresa_id_foreign
    FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;

ALTER TABLE public.guias DROP CONSTRAINT IF EXISTS guias_venta_id_foreign;
ALTER TABLE public.guias ADD  CONSTRAINT guias_venta_id_foreign
    FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE SET NULL;

ALTER TABLE public.guias DROP CONSTRAINT IF EXISTS guias_transferencia_id_foreign;
ALTER TABLE public.guias ADD  CONSTRAINT guias_transferencia_id_foreign
    FOREIGN KEY (transferencia_id) REFERENCES public.transferencias(id) ON DELETE SET NULL;

ALTER TABLE public.guias DROP CONSTRAINT IF EXISTS guias_cliente_id_foreign;
ALTER TABLE public.guias ADD  CONSTRAINT guias_cliente_id_foreign
    FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;

ALTER TABLE public.guias DROP CONSTRAINT IF EXISTS guias_user_id_foreign;
ALTER TABLE public.guias ADD  CONSTRAINT guias_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;

-- Sin esto, un doble clic en «Emitir guía» manda dos y consume DOS números de
-- SUNAT para el mismo despacho. La clave es la misma que viaja a FacturaMac, así
-- que las dos puntas quedan protegidas igual.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid = 'public.guias'::regclass
                     AND conname  = 'guias_empresa_id_idempotency_key_unique') THEN
        ALTER TABLE public.guias
            ADD CONSTRAINT guias_empresa_id_idempotency_key_unique UNIQUE (empresa_id, idempotency_key);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS guias_empresa_id_estado_index ON public.guias (empresa_id, estado);

COMMENT ON COLUMN public.guias.puede_trasladar IS
    'Lo manda FacturaMac ya resuelto. No se deduce del estado: dos listas separadas dejan de coincidir.';

INSERT INTO public.migrations (migration, batch)
SELECT '2026_09_20_000001_create_guias_table',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations WHERE migration = '2026_09_20_000001_create_guias_table'
);

COMMIT;
