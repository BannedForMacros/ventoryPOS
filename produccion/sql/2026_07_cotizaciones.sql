-- ============================================================================
-- COTIZACIONES
-- ============================================================================
-- El vendedor arma una cotización (proforma) con precios congelados y una
-- vigencia. La `referencia` es una etiqueta flexible por rubro: "Obra Av.
-- Grau" (ferretería), "Paciente Firulais" (veterinaria), etc.
--
-- Ciclo de vida (cotizaciones.estado):
--   vigente → aceptada | rechazada | vencida | anulada
--   vigente/aceptada/vencida → convertida (se creó la venta desde el POS y
--   quedó vinculada vía venta_id).
--
-- Las vigentes con fecha_vencimiento < hoy se marcan 'vencida' automáticamente
-- al listar el módulo (update masivo, sin cron).
--
-- Idempotente: se puede correr varias veces con `psql -f`.
-- Aplicar en producción:  psql "$DATABASE_URL" -f 2026_07_cotizaciones.sql
-- ============================================================================

-- 1) Cabecera de la cotización
CREATE TABLE IF NOT EXISTS cotizaciones (
    id                     BIGSERIAL PRIMARY KEY,
    empresa_id             BIGINT        NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    local_id               BIGINT        NULL     REFERENCES locales(id),
    user_id                BIGINT        NOT NULL REFERENCES users(id),
    cliente_id             BIGINT        NOT NULL REFERENCES clientes(id),
    numero                 VARCHAR(20)   NOT NULL,                       -- correlativo COT-0001 por empresa
    referencia             VARCHAR(200)  NULL,                           -- etiqueta por rubro: obra, paciente, proyecto...
    estado                 VARCHAR(20)   NOT NULL DEFAULT 'vigente',     -- vigente|aceptada|rechazada|vencida|convertida|anulada
    fecha                  DATE          NOT NULL,
    fecha_vencimiento      DATE          NULL,                           -- hasta cuándo se respetan los precios
    fecha_entrega_estimada DATE          NULL,
    subtotal               NUMERIC(12,2) NOT NULL DEFAULT 0,             -- suma bruta (precios × cantidad)
    descuento_total        NUMERIC(12,2) NOT NULL DEFAULT 0,             -- suma de descuentos por línea
    igv                    NUMERIC(12,2) NOT NULL DEFAULT 0,             -- desglose informativo (tasa de la empresa)
    total                  NUMERIC(12,2) NOT NULL DEFAULT 0,
    venta_id               BIGINT        NULL     REFERENCES ventas(id) ON DELETE SET NULL, -- venta creada al convertir
    observacion            TEXT          NULL,
    notas_seguimiento      TEXT          NULL,                           -- bitácora de contactos con el cliente
    ultimo_contacto        DATE          NULL,
    created_at             TIMESTAMP(0)  NULL,
    updated_at             TIMESTAMP(0)  NULL
);

CREATE INDEX IF NOT EXISTS cotizaciones_empresa_estado_index
    ON cotizaciones (empresa_id, estado);

CREATE INDEX IF NOT EXISTS cotizaciones_empresa_vencimiento_index
    ON cotizaciones (empresa_id, fecha_vencimiento);

-- El correlativo por empresa lo garantiza la BD (patrón optimistic-insert).
CREATE UNIQUE INDEX IF NOT EXISTS cotizaciones_empresa_numero_unique
    ON cotizaciones (empresa_id, numero);

-- 2) Detalle: productos cotizados con precio congelado y descuento por línea
CREATE TABLE IF NOT EXISTS cotizacion_items (
    id                 BIGSERIAL PRIMARY KEY,
    cotizacion_id      BIGINT        NOT NULL REFERENCES cotizaciones(id) ON DELETE CASCADE,
    producto_id        BIGINT        NOT NULL REFERENCES productos(id),
    producto_unidad_id BIGINT        NULL     REFERENCES producto_unidades(id),
    producto_nombre    VARCHAR(150)  NOT NULL DEFAULT '',   -- snapshot (el catálogo puede cambiar)
    unidad_nombre      VARCHAR(50)   NOT NULL DEFAULT '',
    cantidad           NUMERIC(12,4) NOT NULL,
    precio_unitario    NUMERIC(12,2) NOT NULL,              -- precio cotizado (congelado)
    descuento_item     NUMERIC(12,2) NOT NULL DEFAULT 0,    -- descuento por unidad
    subtotal           NUMERIC(12,2) NOT NULL DEFAULT 0,    -- (precio − descuento) × cantidad
    created_at         TIMESTAMP(0)  NULL,
    updated_at         TIMESTAMP(0)  NULL
);

CREATE INDEX IF NOT EXISTS cotizacion_items_cotizacion_index
    ON cotizacion_items (cotizacion_id);
