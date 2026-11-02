-- ============================================================================
--  ventoryPOS · Flota y viajes de reparto (Fase A)
--  Fecha: 2026-11-02
--
--  QUÉ HACE: agrega tablas y columnas nuevas, todas opcionales, y registra las
--  pantallas "Viajes", "Vehículos" y "Conductores". No modifica ninguna fila
--  existente y es idempotente.
--
--    empresas.usa_flota       activa la función (por defecto apagada)
--    productos.peso_kg        peso por unidad base, para la carga y la guía
--    conductores              choferes y ayudantes de la empresa
--    vehiculos                camiones / unidades de reparto
--    viajes                   salida de un vehículo con su chofer: reparto,
--                             abastecimiento (vuelta de fábrica) o flete
--    viaje_ayudantes          quién más fue en el viaje
--    viaje_paradas            cada venta que lleva el viaje
--    viaje_parada_items       qué y cuánto lleva de cada venta
--    guias.viaje_parada_id    guía emitida desde una parada del viaje
--
--  UN VIAJE NO MUEVE STOCK POR SÍ MISMO. Al entregar una parada se usa la
--  misma entrega de pendientes de siempre (Despachos); una venta "directa"
--  ya sacó su stock al venderse.
--
--  Con la función apagada, la venta, el stock, el POS y Despachos quedan
--  exactamente como hoy.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

BEGIN;

ALTER TABLE public.empresas  ADD COLUMN IF NOT EXISTS usa_flota BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE public.productos ADD COLUMN IF NOT EXISTS peso_kg   NUMERIC(12,4) NULL;

CREATE TABLE IF NOT EXISTS public.conductores (
    id                  BIGSERIAL PRIMARY KEY,
    empresa_id          BIGINT       NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    tipo                VARCHAR(10)  NOT NULL DEFAULT 'chofer' CHECK (tipo IN ('chofer', 'ayudante')),
    nombres             VARCHAR(80)  NOT NULL,
    apellidos           VARCHAR(80)  NULL,
    tipo_documento      VARCHAR(10)  NOT NULL DEFAULT 'DNI',
    numero_documento    VARCHAR(15)  NULL,
    telefono            VARCHAR(20)  NULL,
    licencia            VARCHAR(20)  NULL,
    licencia_categoria  VARCHAR(10)  NULL,
    licencia_vence      DATE         NULL,
    observacion         VARCHAR(255) NULL,
    activo              BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS conductores_empresa_id_index ON public.conductores (empresa_id);
CREATE UNIQUE INDEX IF NOT EXISTS conductores_empresa_documento_unique
    ON public.conductores (empresa_id, numero_documento) WHERE numero_documento IS NOT NULL;

CREATE TABLE IF NOT EXISTS public.vehiculos (
    id                   BIGSERIAL PRIMARY KEY,
    empresa_id           BIGINT        NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    placa                VARCHAR(10)   NOT NULL,
    marca                VARCHAR(40)   NULL,
    modelo               VARCHAR(40)   NULL,
    anio                 SMALLINT      NULL,
    tipo                 VARCHAR(30)   NULL,
    capacidad_kg         NUMERIC(12,2) NULL,
    tarjeta_circulacion  VARCHAR(30)   NULL,
    conductor_id         BIGINT        NULL REFERENCES public.conductores(id) ON DELETE SET NULL,
    -- "En ruta" no se guarda: sale de tener un viaje en ruta.
    estado               VARCHAR(20)   NOT NULL DEFAULT 'disponible'
                         CHECK (estado IN ('disponible', 'taller', 'fuera_servicio')),
    km_actual            NUMERIC(12,1) NULL,
    observacion          VARCHAR(255)  NULL,
    activo               BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS vehiculos_empresa_placa_unique ON public.vehiculos (empresa_id, placa);

CREATE TABLE IF NOT EXISTS public.viajes (
    id              BIGSERIAL PRIMARY KEY,
    empresa_id      BIGINT       NOT NULL REFERENCES public.empresas(id) ON DELETE CASCADE,
    numero          VARCHAR(20)  NOT NULL,
    tipo            VARCHAR(15)  NOT NULL DEFAULT 'reparto'
                    CHECK (tipo IN ('reparto', 'abastecimiento', 'flete')),
    fecha           DATE         NOT NULL,
    hora_salida     TIME         NULL,
    vehiculo_id     BIGINT       NOT NULL REFERENCES public.vehiculos(id),
    conductor_id    BIGINT       NULL REFERENCES public.conductores(id) ON DELETE SET NULL,
    almacen_id      BIGINT       NULL REFERENCES public.almacenes(id) ON DELETE SET NULL,
    ruta_entrega_id BIGINT       NULL REFERENCES public.rutas_entrega(id) ON DELETE SET NULL,
    estado          VARCHAR(15)  NOT NULL DEFAULT 'programado'
                    CHECK (estado IN ('programado', 'en_ruta', 'cerrado', 'anulado')),
    origen          VARCHAR(200) NULL,
    destino         VARCHAR(200) NULL,
    proveedor_id    BIGINT       NULL REFERENCES public.proveedores(id) ON DELETE SET NULL,
    entrada_id      BIGINT       NULL REFERENCES public.entradas(id) ON DELETE SET NULL,
    observacion     TEXT         NULL,
    motivo_anulacion VARCHAR(255) NULL,
    user_id         BIGINT       NULL REFERENCES public.users(id) ON DELETE SET NULL,
    salida_at       TIMESTAMP    NULL,
    cierre_at       TIMESTAMP    NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS viajes_empresa_numero_unique ON public.viajes (empresa_id, numero);
CREATE INDEX IF NOT EXISTS viajes_empresa_fecha_index ON public.viajes (empresa_id, fecha);
CREATE INDEX IF NOT EXISTS viajes_vehiculo_estado_index ON public.viajes (vehiculo_id, estado);

CREATE TABLE IF NOT EXISTS public.viaje_ayudantes (
    viaje_id     BIGINT NOT NULL REFERENCES public.viajes(id) ON DELETE CASCADE,
    conductor_id BIGINT NOT NULL REFERENCES public.conductores(id),
    PRIMARY KEY (viaje_id, conductor_id)
);

CREATE TABLE IF NOT EXISTS public.viaje_paradas (
    id                             BIGSERIAL PRIMARY KEY,
    viaje_id                       BIGINT       NOT NULL REFERENCES public.viajes(id) ON DELETE CASCADE,
    venta_id                       BIGINT       NULL REFERENCES public.ventas(id),
    cliente_anticipo_id            BIGINT       NULL REFERENCES public.cliente_anticipos(id) ON DELETE SET NULL,
    orden                          SMALLINT     NOT NULL DEFAULT 0,
    -- pendiente: lleva mercadería por entregar (sale del stock al entregarla).
    -- directa:   la venta ya sacó su stock al venderse; el viaje solo la lleva.
    modo                           VARCHAR(12)  NOT NULL DEFAULT 'pendiente' CHECK (modo IN ('pendiente', 'directa')),
    estado                         VARCHAR(15)  NOT NULL DEFAULT 'por_entregar'
                                   CHECK (estado IN ('por_entregar', 'entregado', 'no_entregado')),
    cliente_anticipo_aplicacion_id BIGINT       NULL REFERENCES public.cliente_anticipo_aplicaciones(id) ON DELETE SET NULL,
    entregado_at                   TIMESTAMP    NULL,
    motivo                         VARCHAR(255) NULL,
    created_at                     TIMESTAMP NULL,
    updated_at                     TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS viaje_paradas_viaje_index ON public.viaje_paradas (viaje_id, orden);
CREATE INDEX IF NOT EXISTS viaje_paradas_anticipo_index ON public.viaje_paradas (cliente_anticipo_id);
CREATE INDEX IF NOT EXISTS viaje_paradas_venta_index ON public.viaje_paradas (venta_id);

CREATE TABLE IF NOT EXISTS public.viaje_parada_items (
    id                       BIGSERIAL PRIMARY KEY,
    viaje_parada_id          BIGINT        NOT NULL REFERENCES public.viaje_paradas(id) ON DELETE CASCADE,
    cliente_anticipo_item_id BIGINT        NULL REFERENCES public.cliente_anticipo_items(id) ON DELETE SET NULL,
    venta_item_id            BIGINT        NULL REFERENCES public.venta_items(id) ON DELETE SET NULL,
    producto_id              BIGINT        NOT NULL REFERENCES public.productos(id),
    producto_nombre          VARCHAR(150)  NOT NULL,
    unidad_nombre            VARCHAR(50)   NOT NULL DEFAULT '',
    cantidad                 NUMERIC(12,4) NOT NULL,
    factor_conversion        NUMERIC(12,4) NOT NULL DEFAULT 1,
    peso_kg                  NUMERIC(12,3) NULL,
    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS viaje_parada_items_parada_index ON public.viaje_parada_items (viaje_parada_id);
CREATE INDEX IF NOT EXISTS viaje_parada_items_anticipo_item_index ON public.viaje_parada_items (cliente_anticipo_item_id);

-- La guía puede salir de una parada del viaje (placa, chofer y peso ya puestos).
DO $$
BEGIN
    IF to_regclass('public.guias') IS NOT NULL THEN
        ALTER TABLE public.guias ADD COLUMN IF NOT EXISTS viaje_parada_id BIGINT NULL
            REFERENCES public.viaje_paradas(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Menú: grupo "Flota y reparto" con sus tres pantallas. Solo administradores
-- de entrada; los demás roles se habilitan en Permisos por Rol.
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT NULL, 'Flota y reparto', 'flota', 'Truck', NULL, 22, true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'flota');

INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, v.nombre, v.slug, v.icono, v.ruta, v.orden, true, now(), now()
FROM public.modulos p
CROSS JOIN (VALUES
    ('Viajes',      'viajes',            'Route',  '/viajes',            1),
    ('Vehículos',   'flota.vehiculos',   'Truck',  '/flota/vehiculos',   2),
    ('Conductores', 'flota.conductores', 'IdCard', '/flota/conductores', 3)
) AS v(nombre, slug, icono, ruta, orden)
WHERE p.slug = 'flota'
  AND NOT EXISTS (SELECT 1 FROM public.modulos m WHERE m.slug = v.slug);

INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug IN ('flota', 'viajes', 'flota.vehiculos', 'flota.conductores')
  AND r.es_admin = true
  AND NOT EXISTS (SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id);

COMMIT;
