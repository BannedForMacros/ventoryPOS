-- ============================================================================
--  ventoryPOS · Turno automático (negocios que no usan caja)
--  Fecha: 2026-09-22
--
--  QUÉ HACE: agrega TRES COLUMNAS a `empresas`, las tres con el valor que
--  reproduce EXACTAMENTE el comportamiento de hoy. Ninguna empresa cambia de
--  conducta al aplicar este script: hay que encenderlo a mano, empresa por
--  empresa.
--
--  PARA QUÉ: una peluquería, una veterinaria o un taller pequeño no abren ni
--  cierran caja — abren la puerta y trabajan. Pero una venta NO puede existir
--  sin turno: `ventas.turno_id` y `ventas.caja_id` son obligatorios y el
--  correlativo cuelga del turno. Arrancar el turno de raíz sería cirugía mayor
--  (esquema, numeración, reportes, arqueos).
--
--  La salida es que el turno SIGA EXISTIENDO pero nadie lo vea: se abre solo
--  con la primera venta del día y se cierra solo cuando el día termina. Así el
--  balance, la tesorería, el kardex y los reportes siguen funcionando igual,
--  sin tocar una sola tabla de movimientos.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

BEGIN;

ALTER TABLE public.empresas
    -- manual     = como siempre: alguien abre y cierra el turno a mano.
    -- automatico = el sistema abre el turno del día en la primera venta.
    --              UNO POR PERSONA, también cuando hay un solo local: cada
    --              quien responde por lo suyo y el reporte por profesional
    --              sigue teniendo sentido.
    ADD COLUMN IF NOT EXISTS modo_turno character varying(20) NOT NULL DEFAULT 'manual',

    -- Cierra solo los turnos que quedaron abiertos de días anteriores, sin
    -- declarar nada. Es INDEPENDIENTE del modo a propósito: una empresa que
    -- abre turnos a mano también puede querer que no se le queden abiertos.
    ADD COLUMN IF NOT EXISTS turno_cierre_automatico boolean NOT NULL DEFAULT false,

    -- Hasta dónde llega la numeración V-0001 antes de reiniciar:
    --   turno    = como hoy: cada turno empieza de nuevo.
    --   dia      = corrida entre todas las personas del local, reinicia cada día.
    --   continuo = nunca reinicia.
    -- Con turnos automáticos POR PERSONA, dejarlo en 'turno' haría que dos
    -- estilistas emitieran las dos su V-0001 el mismo día. Por eso es elegible.
    ADD COLUMN IF NOT EXISTS venta_correlativo_alcance character varying(20) NOT NULL DEFAULT 'turno';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'empresas_modo_turno_check') THEN
        ALTER TABLE public.empresas ADD CONSTRAINT empresas_modo_turno_check
            CHECK (modo_turno IN ('manual', 'automatico'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'empresas_venta_correlativo_alcance_check') THEN
        ALTER TABLE public.empresas ADD CONSTRAINT empresas_venta_correlativo_alcance_check
            CHECK (venta_correlativo_alcance IN ('turno', 'dia', 'continuo'));
    END IF;
END $$;

COMMENT ON COLUMN public.empresas.modo_turno IS
    'manual = se abre y cierra a mano. automatico = el sistema abre el turno del día por persona en la primera venta.';
COMMENT ON COLUMN public.empresas.turno_cierre_automatico IS
    'Cierra sin declaración los turnos abiertos de días anteriores. Independiente de modo_turno.';
COMMENT ON COLUMN public.empresas.venta_correlativo_alcance IS
    'turno | dia | continuo. Hasta dónde llega la numeración de ventas antes de reiniciar.';

COMMIT;

-- ============================================================================
--  Para encenderlo en una peluquería / veterinaria / taller:
--
--    UPDATE public.empresas
--       SET modo_turno                = 'automatico',
--           turno_cierre_automatico   = true,
--           venta_correlativo_alcance = 'dia'
--     WHERE id = <empresa>;
--
--  Y al revés, para volver atrás, basta con dejar los tres valores por defecto.
-- ============================================================================
