-- ============================================================================
--  ventoryPOS · Flota Fase C: pago a choferes y ayudantes, carga por volumen
--  y precio por zona
--  Fecha: 2026-11-02
--
--  QUÉ HACE: agrega columnas y tablas nuevas, todas opcionales, y registra la
--  pantalla "Pago a choferes". No modifica ninguna fila existente y es
--  idempotente. Requiere las fases A y B (2026_11_flota_viajes.sql y
--  2026_11_flota_fase_b.sql).
--
--    flota_tarifas                cuánto se paga al chofer y a cada ayudante por
--                                 cada N unidades repartidas (por producto, o la
--                                 tarifa general si producto_id es NULL)
--    empresas.flota_config        pago por vuelta de fábrica (abastecimiento)
--    flota_liquidaciones          liquidación semanal pagada a una persona
--    flota_liquidacion_viajes     qué viajes cubrió (no se pagan dos veces)
--    gastos.flota_liquidacion_id  los gastos con que se pagó (uno por placa)
--    vehiculo_capacidades         unidades máximas por producto y camión
--                                 (volumen: tolva o baranda)
--    rutas_entrega.margen_monto   margen por zona para el precio sugerido
--    rutas_entrega.margen_por     … cada cuántas unidades (1000 = por millar)
--
--  EL PAGO ES UN GASTO: pagar una liquidación registra gastos de siempre
--  (caja, tesorería, balance), uno por placa, así la utilidad por camión
--  descuenta lo que se pagó. Anularla elimina esos gastos.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
-- ============================================================================

BEGIN;

ALTER TABLE public.empresas ADD COLUMN IF NOT EXISTS flota_config JSON NULL;

CREATE TABLE IF NOT EXISTS public.flota_tarifas (
    id              BIGSERIAL PRIMARY KEY,
    empresa_id      BIGINT        NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    producto_id     BIGINT        NULL REFERENCES public.productos(id) ON DELETE CASCADE,
    por_cantidad    NUMERIC(12,2) NOT NULL DEFAULT 1000,
    monto_chofer    NUMERIC(10,2) NOT NULL DEFAULT 0,
    monto_ayudante  NUMERIC(10,2) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS flota_tarifas_empresa_producto_unique
    ON public.flota_tarifas (empresa_id, COALESCE(producto_id, 0));

CREATE TABLE IF NOT EXISTS public.flota_liquidaciones (
    id               BIGSERIAL PRIMARY KEY,
    empresa_id       BIGINT        NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    numero           VARCHAR(20)   NOT NULL,
    conductor_id     BIGINT        NOT NULL REFERENCES public.conductores(id),
    desde            DATE          NOT NULL,
    hasta            DATE          NOT NULL,
    viajes           INTEGER       NOT NULL DEFAULT 0,
    vueltas          INTEGER       NOT NULL DEFAULT 0,
    unidades         NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_calculado  NUMERIC(12,2) NOT NULL DEFAULT 0,
    ajuste           NUMERIC(12,2) NOT NULL DEFAULT 0,
    ajuste_motivo    VARCHAR(255)  NULL,
    total            NUMERIC(12,2) NOT NULL DEFAULT 0,
    detalle          JSON          NULL,
    estado           VARCHAR(10)   NOT NULL DEFAULT 'pagada' CHECK (estado IN ('pagada', 'anulada')),
    fecha_pago       DATE          NOT NULL,
    motivo_anulacion VARCHAR(255)  NULL,
    user_id          BIGINT        NULL REFERENCES public.users(id) ON DELETE SET NULL,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS flota_liquidaciones_empresa_numero_unique ON public.flota_liquidaciones (empresa_id, numero);
CREATE INDEX IF NOT EXISTS flota_liquidaciones_conductor_index ON public.flota_liquidaciones (conductor_id, desde);

CREATE TABLE IF NOT EXISTS public.flota_liquidacion_viajes (
    id                     BIGSERIAL PRIMARY KEY,
    flota_liquidacion_id   BIGINT        NOT NULL REFERENCES public.flota_liquidaciones(id) ON DELETE CASCADE,
    viaje_id               BIGINT        NOT NULL REFERENCES public.viajes(id),
    vehiculo_id            BIGINT        NULL REFERENCES public.vehiculos(id) ON DELETE SET NULL,
    rol                    VARCHAR(10)   NOT NULL CHECK (rol IN ('chofer', 'ayudante')),
    unidades               NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto                  NUMERIC(12,2) NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS flota_liquidacion_viajes_viaje_index ON public.flota_liquidacion_viajes (viaje_id);
CREATE INDEX IF NOT EXISTS flota_liquidacion_viajes_liq_index ON public.flota_liquidacion_viajes (flota_liquidacion_id);

ALTER TABLE public.gastos ADD COLUMN IF NOT EXISTS flota_liquidacion_id BIGINT NULL
    REFERENCES public.flota_liquidaciones(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS gastos_flota_liquidacion_index ON public.gastos (flota_liquidacion_id) WHERE flota_liquidacion_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS public.vehiculo_capacidades (
    id            BIGSERIAL PRIMARY KEY,
    vehiculo_id   BIGINT        NOT NULL REFERENCES public.vehiculos(id) ON DELETE CASCADE,
    producto_id   BIGINT        NOT NULL REFERENCES public.productos(id) ON DELETE CASCADE,
    max_unidades  NUMERIC(12,2) NOT NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS vehiculo_capacidades_unique ON public.vehiculo_capacidades (vehiculo_id, producto_id);

ALTER TABLE public.rutas_entrega ADD COLUMN IF NOT EXISTS margen_monto NUMERIC(10,2) NULL;
ALTER TABLE public.rutas_entrega ADD COLUMN IF NOT EXISTS margen_por   NUMERIC(12,2) NOT NULL DEFAULT 1000;

-- Pantalla "Pago a choferes" (solo administradores de entrada: el cálculo del
-- pago lo ve el gerente; se habilita a otros roles en Permisos por Rol).
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Pago a choferes', 'flota.pagos', 'HandCoins', '/flota/pagos', 4, true, now(), now()
FROM public.modulos p
WHERE p.slug = 'flota'
  AND NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'flota.pagos');

INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug = 'flota.pagos'
  AND r.es_admin = true
  AND NOT EXISTS (SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id);

COMMIT;
