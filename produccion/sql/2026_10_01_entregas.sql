-- ============================================================================
--  ventoryPOS · Entregas: recojo en tienda o envío, rutas y fecha programada
--  Fecha: 2026-10-01
--
--  QUÉ HACE: agrega columnas y una tabla nuevas, todas opcionales, y registra
--  la pantalla "Entregas" en Configuración. No modifica ninguna fila existente
--  y es idempotente.
--
--    empresas.usa_entregas        activa la función (por defecto apagada)
--    empresas.entrega_config      monto del aviso y qué se exige en un envío
--    rutas_entrega                rutas o zonas de reparto de cada empresa
--    ventas.tipo_entrega          'recojo' | 'envio' (NULL en ventas sin la función)
--    ventas.ruta_entrega_id       ruta del envío
--    ventas.entrega_programada    fecha y hora programadas
--
--  La mercadería pendiente y sus entregas parciales siguen donde ya estaban
--  (cliente_anticipos de tipo material y sus aplicaciones).
--
--  Con la función apagada, la venta, el stock y el ticket quedan como hoy.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

BEGIN;

ALTER TABLE public.empresas ADD COLUMN IF NOT EXISTS usa_entregas   BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE public.empresas ADD COLUMN IF NOT EXISTS entrega_config JSON NULL;

CREATE TABLE IF NOT EXISTS public.rutas_entrega (
    id          BIGSERIAL PRIMARY KEY,
    empresa_id  BIGINT       NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    nombre      VARCHAR(60)  NOT NULL,
    zona        VARCHAR(120) NULL,
    orden       SMALLINT     NOT NULL DEFAULT 0,
    activo      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS rutas_entrega_empresa_id_orden_index ON public.rutas_entrega (empresa_id, orden);

ALTER TABLE public.ventas ADD COLUMN IF NOT EXISTS tipo_entrega       VARCHAR(10) NULL;
ALTER TABLE public.ventas ADD COLUMN IF NOT EXISTS ruta_entrega_id    BIGINT NULL REFERENCES public.rutas_entrega(id) ON DELETE SET NULL;
ALTER TABLE public.ventas ADD COLUMN IF NOT EXISTS entrega_programada TIMESTAMP NULL;
CREATE INDEX IF NOT EXISTS ventas_empresa_id_entrega_programada_index ON public.ventas (empresa_id, entrega_programada);

-- Pantalla "Entregas" en Configuración. Solo administradores.
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Entregas', 'config.entregas', 'Truck', '/configuracion/entregas', 2, true, now(), now()
FROM public.modulos p
WHERE p.slug = 'configuracion'
  AND NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'config.entregas');

INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug = 'config.entregas'
  AND r.es_admin = true
  AND NOT EXISTS (
      SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id
  );

COMMIT;
