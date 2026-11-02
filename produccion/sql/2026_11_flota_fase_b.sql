-- ============================================================================
--  ventoryPOS · Flota Fase B: combustible, mantenimiento, documentos y
--  utilidad por placa
--  Fecha: 2026-11-02
--
--  QUÉ HACE: agrega columnas y tablas nuevas, todas opcionales, y registra la
--  pantalla "Resumen de flota". No modifica ninguna fila existente y es
--  idempotente. Requiere 2026_11_flota_viajes.sql (Fase A).
--
--    gastos.vehiculo_id              el gasto se carga a una placa (utilidad)
--    gasto_conceptos.flota_uso       'combustible' | 'mantenimiento': el gasto
--                                    pide además su ficha técnica
--    vehiculos.mant_intervalo_km     cada cuántos km toca el preventivo
--    vehiculos.mant_base_km          km del último preventivo antes de usar el
--                                    sistema (punto de partida del contador)
--    vehiculo_combustibles           km, galones y tanque lleno de cada carga
--    vehiculo_mantenimientos         preventivo / correctivo, taller, días parado
--    vehiculo_documentos             SOAT, revisión técnica… con vencimiento
--
--  EL DINERO SIGUE SIENDO EL GASTO: combustible y mantenimiento son gastos de
--  siempre (caja, tesorería, balance, eliminar y reactivar). Su ficha técnica
--  cuelga del gasto y se ignora mientras el gasto esté eliminado.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
-- ============================================================================

BEGIN;

ALTER TABLE public.gastos ADD COLUMN IF NOT EXISTS vehiculo_id BIGINT NULL
    REFERENCES public.vehiculos(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS gastos_vehiculo_fecha_index ON public.gastos (vehiculo_id, fecha) WHERE vehiculo_id IS NOT NULL;

ALTER TABLE public.gasto_conceptos ADD COLUMN IF NOT EXISTS flota_uso VARCHAR(15) NULL;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'gasto_conceptos_flota_uso_check') THEN
        ALTER TABLE public.gasto_conceptos ADD CONSTRAINT gasto_conceptos_flota_uso_check
            CHECK (flota_uso IS NULL OR flota_uso IN ('combustible', 'mantenimiento'));
    END IF;
END $$;

ALTER TABLE public.vehiculos ADD COLUMN IF NOT EXISTS mant_intervalo_km INTEGER NOT NULL DEFAULT 8000;
ALTER TABLE public.vehiculos ADD COLUMN IF NOT EXISTS mant_base_km NUMERIC(12,1) NULL;

CREATE TABLE IF NOT EXISTS public.vehiculo_combustibles (
    id            BIGSERIAL PRIMARY KEY,
    empresa_id    BIGINT        NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    vehiculo_id   BIGINT        NOT NULL REFERENCES public.vehiculos(id),
    gasto_id      BIGINT        NOT NULL REFERENCES public.gastos(id) ON DELETE CASCADE,
    conductor_id  BIGINT        NULL REFERENCES public.conductores(id) ON DELETE SET NULL,
    km            NUMERIC(12,1) NOT NULL,
    galones       NUMERIC(10,3) NOT NULL,
    tanque_lleno  BOOLEAN       NOT NULL DEFAULT TRUE,
    grifo         VARCHAR(80)   NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS vehiculo_combustibles_gasto_unique ON public.vehiculo_combustibles (gasto_id);
CREATE INDEX IF NOT EXISTS vehiculo_combustibles_vehiculo_km_index ON public.vehiculo_combustibles (vehiculo_id, km);

CREATE TABLE IF NOT EXISTS public.vehiculo_mantenimientos (
    id            BIGSERIAL PRIMARY KEY,
    empresa_id    BIGINT        NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    vehiculo_id   BIGINT        NOT NULL REFERENCES public.vehiculos(id),
    gasto_id      BIGINT        NOT NULL REFERENCES public.gastos(id) ON DELETE CASCADE,
    tipo          VARCHAR(12)   NOT NULL DEFAULT 'preventivo' CHECK (tipo IN ('preventivo', 'correctivo')),
    km            NUMERIC(12,1) NULL,
    taller        VARCHAR(100)  NULL,
    detalle       VARCHAR(500)  NULL,
    dias_parado   SMALLINT      NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS vehiculo_mantenimientos_gasto_unique ON public.vehiculo_mantenimientos (gasto_id);
CREATE INDEX IF NOT EXISTS vehiculo_mantenimientos_vehiculo_index ON public.vehiculo_mantenimientos (vehiculo_id, km);

CREATE TABLE IF NOT EXISTS public.vehiculo_documentos (
    id               BIGSERIAL PRIMARY KEY,
    empresa_id       BIGINT       NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    vehiculo_id      BIGINT       NOT NULL REFERENCES public.vehiculos(id) ON DELETE CASCADE,
    tipo             VARCHAR(40)  NOT NULL,
    numero           VARCHAR(60)  NULL,
    emision          DATE         NULL,
    vence            DATE         NULL,
    archivo          VARCHAR(255) NULL,
    archivo_nombre   VARCHAR(150) NULL,
    observacion      VARCHAR(255) NULL,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS vehiculo_documentos_vehiculo_index ON public.vehiculo_documentos (vehiculo_id, tipo);
CREATE INDEX IF NOT EXISTS vehiculo_documentos_vence_index ON public.vehiculo_documentos (empresa_id, vence);

-- Pantalla "Resumen de flota", primera del grupo.
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Resumen de flota', 'flota.resumen', 'Gauge', '/flota', 0, true, now(), now()
FROM public.modulos p
WHERE p.slug = 'flota'
  AND NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'flota.resumen');

INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug = 'flota.resumen'
  AND r.es_admin = true
  AND NOT EXISTS (SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id);

COMMIT;
