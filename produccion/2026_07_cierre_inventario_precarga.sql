-- ============================================================================
-- Cierre de inventario: modo de carga configurable por empresa
-- ============================================================================
-- cierre_precarga_stock = true  → CIERRE LÓGICO: al cerrar, cada producto sale
--   precargado con el stock del sistema; editas solo los que difieren (parcial).
-- cierre_precarga_stock = false → CIERRE EN BLANCO: todos salen vacíos y hay que
--   declarar la cantidad de TODOS los productos (conteo completo obligatorio).
-- Default false (comportamiento previo). Se activa para HYC (empresa 1097).
-- ============================================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS cierre_precarga_stock boolean NOT NULL DEFAULT false;

-- HYC arranca en modo lógico (precargado).
UPDATE empresas SET cierre_precarga_stock = true WHERE id = 1097;
