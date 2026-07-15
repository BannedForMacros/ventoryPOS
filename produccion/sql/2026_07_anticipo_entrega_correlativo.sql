-- ─────────────────────────────────────────────────────────────────────────────
-- Correlativo de trazabilidad para las ENTREGAS de anticipos de clientes
-- ─────────────────────────────────────────────────────────────────────────────
-- Cada entrega (aplicación de un anticipo) se guarda en cliente_anticipo_aplicaciones,
-- pero no tenía un número de documento. Agregamos `numero` (correlativo E-0001 por
-- empresa) para trazabilidad e impresión. Como la tabla no tenía empresa_id, lo
-- agregamos también (se llena desde el anticipo).

ALTER TABLE cliente_anticipo_aplicaciones
    ADD COLUMN IF NOT EXISTS empresa_id BIGINT NULL REFERENCES empresas(id);

ALTER TABLE cliente_anticipo_aplicaciones
    ADD COLUMN IF NOT EXISTS numero VARCHAR(20) NULL;

-- Backfill empresa_id desde el anticipo padre
UPDATE cliente_anticipo_aplicaciones ap
SET empresa_id = ca.empresa_id
FROM cliente_anticipos ca
WHERE ca.id = ap.cliente_anticipo_id
  AND ap.empresa_id IS NULL;

-- Backfill numero: E-0001, E-0002... por empresa, en orden de creación
WITH num AS (
    SELECT id,
           'E-' || LPAD(ROW_NUMBER() OVER (PARTITION BY empresa_id ORDER BY id)::text, 4, '0') AS n
    FROM cliente_anticipo_aplicaciones
    WHERE numero IS NULL
)
UPDATE cliente_anticipo_aplicaciones ap
SET numero = num.n
FROM num
WHERE num.id = ap.id;

-- Unicidad (empresa, numero)
CREATE UNIQUE INDEX IF NOT EXISTS cliente_anticipo_aplicaciones_empresa_numero_unique
    ON cliente_anticipo_aplicaciones (empresa_id, numero)
    WHERE numero IS NOT NULL;
