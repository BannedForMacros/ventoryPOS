-- ─────────────────────────────────────────────────────────────────────────────
-- Gastos: editar / eliminar (soft delete) / reactivar + filtro "Eliminados".
--
-- Se agrega `deleted_at` para borrado suave: al eliminar un gasto se conserva
-- la fila (con su egreso de tesorería revertido) y puede verse en el filtro
-- "Eliminados" y reactivarse. El proyecto aplica esquema por SQL directo,
-- NO por migraciones de Laravel: correr esto en producción.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE gastos ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL;

-- Índice para que el filtro de activos (deleted_at IS NULL) sea barato.
CREATE INDEX IF NOT EXISTS gastos_deleted_at_index ON gastos (deleted_at);
