-- ─────────────────────────────────────────────────────────────────────────────
-- SUPERADMIN GLOBAL (panel del proveedor)
--
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción.
--
-- El superadmin es un usuario ÚNICO del proveedor (MacSoft) que NO pertenece a
-- ninguna empresa: administra el alta de empresas y sus usuarios desde /admin.
-- Por eso users.empresa_id pasa a ser nullable (solo el superadmin la deja en
-- NULL; los usuarios de tenant la siguen llevando siempre).
--
-- auditoria.empresa_id también pasa a nullable: las acciones del superadmin
-- (crear empresa, crear admin de empresa...) se auditan sin empresa; los
-- reportes de auditoría de cada tenant filtran por empresa_id así que no las
-- ven, que es lo correcto.
-- ─────────────────────────────────────────────────────────────────────────────
BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS es_superadmin boolean NOT NULL DEFAULT false;
ALTER TABLE users ALTER COLUMN empresa_id DROP NOT NULL;
ALTER TABLE auditoria ALTER COLUMN empresa_id DROP NOT NULL;

COMMIT;

-- Después de aplicar, crear (o promover) el superadmin desde la consola:
--
--   php artisan superadmin:crear soporte@macsoft.pe "MacSoft" --password="..."
--
-- El comando es idempotente: si el email ya existe, solo lo promueve.
