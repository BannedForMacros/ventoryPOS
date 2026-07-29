-- ============================================================================
--  ventoryPOS · Facturación electrónica — tablas de apoyo
--  Fecha: 2026-07-28
--  Equivale a las migraciones:
--     2026_07_27_000001_create_venta_comprobantes_table
--     2026_07_27_000002_create_unidad_sunat_map_table
--
--  QUÉ HACE: crea DOS TABLAS NUEVAS. No modifica ni borra nada existente.
--  Es seguro ejecutarlo con el sistema en marcha y es idempotente
--  (IF NOT EXISTS), así que repetirlo no hace daño.
--
--  IMPORTANTE — ORDEN DE DESPLIEGUE:
--  Ejecuta este script ANTES de subir el código. `VentaService::anular()` y
--  `actualizar()` consultan `venta_comprobantes`; si el código llega antes que
--  la tabla, anular o editar una venta falla con "relation does not exist".
--
--  Al final se registran las migraciones en la tabla `migrations` para que
--  `php artisan migrate` no intente crearlas otra vez.
-- ============================================================================

BEGIN;

-- ── 1. Comprobantes electrónicos emitidos por cada venta ────────────────────
-- Espejo local de lo que vive en FacturaMac. Se guarda copia de serie,
-- correlativo, QR y hash porque el ticket se imprime EN CAJA, al instante, y no
-- puede depender de que FacturaMac o SUNAT respondan en ese momento.
--
-- UNIQUE(venta_id): una venta = un comprobante. Es la barrera de base de datos
-- contra una doble emisión si dos procesos corren a la vez.
CREATE TABLE IF NOT EXISTS public.venta_comprobantes (
    id                BIGSERIAL PRIMARY KEY,
    venta_id          BIGINT       NOT NULL,
    tipo              VARCHAR(2)   NOT NULL,              -- '01' factura | '03' boleta
    serie             VARCHAR(4),
    correlativo       INTEGER,
    numero            VARCHAR(20),                        -- 'F002-00000123'
    estado            VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
    facturamac_id     INTEGER,                            -- id del comprobante en el emisor
    hash_cpe          VARCHAR(255),
    qr                TEXT,
    sunat_codigo      VARCHAR(255),
    sunat_descripcion TEXT,
    error             TEXT,
    intentos          INTEGER      NOT NULL DEFAULT 0,
    enviado_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    idempotency_key   VARCHAR(100),
    created_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT venta_comprobantes_venta_id_unique UNIQUE (venta_id),
    CONSTRAINT venta_comprobantes_venta_id_foreign
        FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS venta_comprobantes_idempotency_key_index
    ON public.venta_comprobantes (idempotency_key);

COMMENT ON COLUMN public.venta_comprobantes.estado IS
    'pendiente | enviando | enviado | pendiente_resumen | en_resumen | pendiente_anulacion | aceptado | rechazado | error_envio | anulado | simulado';

-- ── 2. Mapa de unidades de medida al catálogo 03 de SUNAT ───────────────────
-- ventoryPOS usa abreviaturas libres por empresa ("BOL", "CAJA", "BID"); SUNAT
-- exige su catálogo ("BG", "BX", "NIU"). Esta tabla traduce, y es por empresa
-- porque cada una nombra sus unidades a su manera.
-- Sin coincidencia se usa NIU y se avisa: una unidad sin mapear no debe impedir
-- facturar.
CREATE TABLE IF NOT EXISTS public.unidad_sunat_map (
    id               BIGSERIAL PRIMARY KEY,
    empresa_id       BIGINT      NOT NULL,
    unidad_medida_id BIGINT,
    abreviatura      VARCHAR(20),                         -- match por texto si no hay id
    codigo_sunat     VARCHAR(5)  NOT NULL DEFAULT 'NIU',
    created_at       TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at       TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT unidad_sunat_map_empresa_id_unidad_medida_id_unique
        UNIQUE (empresa_id, unidad_medida_id),
    CONSTRAINT unidad_sunat_map_empresa_id_foreign
        FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE,
    CONSTRAINT unidad_sunat_map_unidad_medida_id_foreign
        FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS unidad_sunat_map_empresa_id_abreviatura_index
    ON public.unidad_sunat_map (empresa_id, abreviatura);

-- ── 3. Registrar las migraciones para que artisan no las repita ─────────────
INSERT INTO public.migrations (migration, batch)
SELECT '2026_07_27_000001_create_venta_comprobantes_table',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations
    WHERE migration = '2026_07_27_000001_create_venta_comprobantes_table'
);

INSERT INTO public.migrations (migration, batch)
SELECT '2026_07_27_000002_create_unidad_sunat_map_table',
       COALESCE((SELECT MAX(batch) FROM public.migrations), 0)
WHERE NOT EXISTS (
    SELECT 1 FROM public.migrations
    WHERE migration = '2026_07_27_000002_create_unidad_sunat_map_table'
);

COMMIT;

-- ── Verificación (ejecútalo después) ────────────────────────────────────────
-- SELECT to_regclass('public.venta_comprobantes') IS NOT NULL AS venta_comprobantes,
--        to_regclass('public.unidad_sunat_map')   IS NOT NULL AS unidad_sunat_map;
--
-- Ambas deben devolver `t`.
