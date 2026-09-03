-- ============================================================================
-- Módulo "Cierre de mes" (Reportes) + permisos para roles admin
-- ----------------------------------------------------------------------------
-- Equivalente en SQL puro a correr:
--   php artisan db:seed --class=ModulosReportesSeeder
--   php artisan db:seed --class=PermisosReportesSeeder
--
-- Qué hace:
--   1) Crea el módulo hijo 'reportes.cierre-mes' colgado del padre 'reportes'
--      (menú lateral: Reportes → Cierre de mes, /reportes/cierre-mes).
--   2) Da permiso total (ver/crear/editar/eliminar) a TODOS los roles admin
--      (es_admin = true), igual que hace el seeder con los demás reportes.
--
-- Es IDEMPOTENTE: se puede correr varias veces sin duplicar nada.
-- Ejecutar a mano en PostgreSQL (NO es una migración Laravel).
-- ============================================================================

BEGIN;

-- 1) Módulo (menú). ON CONFLICT por el UNIQUE de slug: si ya existe, no hace nada.
INSERT INTO modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Cierre de mes', 'reportes.cierre-mes', 'CalendarRange', '/reportes/cierre-mes', 1, true, NOW(), NOW()
FROM   modulos p
WHERE  p.slug = 'reportes'
ON CONFLICT (slug) DO NOTHING;

-- 2) Permisos para roles admin. ON CONFLICT por el UNIQUE (rol_id, modulo_id).
INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, NOW(), NOW()
FROM   roles r
CROSS  JOIN modulos m
WHERE  r.es_admin = true
  AND  m.slug = 'reportes.cierre-mes'
ON CONFLICT (rol_id, modulo_id) DO UPDATE
SET ver = true, crear = true, editar = true, eliminar = true, updated_at = NOW();

COMMIT;

-- ── Verificación (solo lectura) ─────────────────────────────────────────────
SELECT m.id, m.nombre, m.slug, m.ruta, m.activo,
       (SELECT COUNT(*) FROM permisos pm WHERE pm.modulo_id = m.id AND pm.ver) AS roles_con_acceso
FROM   modulos m
WHERE  m.slug = 'reportes.cierre-mes';
