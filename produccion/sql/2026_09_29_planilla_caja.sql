-- =============================================================================
-- PLANILLA DE CAJA POR TURNO (función opcional por empresa)
-- =============================================================================
-- QUÉ HACE
--   1. empresas.usa_planilla_caja: activa la planilla en el detalle del turno.
--      Por defecto FALSE: ninguna empresa la ve hasta activarla.
--   2. planilla_columnas: las columnas de dinero de cada empresa
--      (ej. "Efectivo", "Depósitos", "Yape / Cuenta BCP").
--   3. metodos_pago.planilla_columna_id: a qué columna suma cada medio de pago.
--
-- PARA QUÉ
--   Reporte de caja del turno con una fila por comprobante y el dinero
--   repartido en columnas configurables por empresa. Idempotente: se puede correr 2 veces.
--
-- RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
-- Este script es la única vía. APLICAR ANTES DEL DEPLOY.
-- =============================================================================

BEGIN;

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS usa_planilla_caja BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS planilla_columnas (
    id          BIGSERIAL PRIMARY KEY,
    empresa_id  BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nombre      VARCHAR(60) NOT NULL,
    orden       SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP(0) NULL,
    updated_at  TIMESTAMP(0) NULL
);
CREATE INDEX IF NOT EXISTS planilla_columnas_empresa_id_orden_index ON planilla_columnas (empresa_id, orden);

ALTER TABLE metodos_pago ADD COLUMN IF NOT EXISTS planilla_columna_id BIGINT NULL
    REFERENCES planilla_columnas(id) ON DELETE SET NULL;

COMMIT;

-- -----------------------------------------------------------------------------
-- ACTIVAR PARA UNA EMPRESA (ajustar el id y los nombres de las columnas):
--
-- UPDATE empresas SET usa_planilla_caja = TRUE WHERE id = <ID_EMPRESA>;
-- INSERT INTO planilla_columnas (empresa_id, nombre, orden, created_at, updated_at) VALUES
--   (<ID_EMPRESA>, 'Efectivo', 1, NOW(), NOW()),
--   (<ID_EMPRESA>, 'Depósitos', 2, NOW(), NOW()),
--   (<ID_EMPRESA>, 'Yape / Cuenta BCP', 3, NOW(), NOW()),
--   (<ID_EMPRESA>, 'Tarjeta', 4, NOW(), NOW());
-- Luego asignar cada medio de pago a su columna desde
-- Configuración → Métodos de pago (o por SQL con planilla_columna_id).
-- -----------------------------------------------------------------------------
