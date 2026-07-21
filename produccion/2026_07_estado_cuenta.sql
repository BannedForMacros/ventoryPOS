-- ═══════════════════════════════════════════════════════════════════════════
-- Estado de cuenta por tercero — registro del módulo en el menú.
--
-- NO crea ni altera ninguna tabla: el módulo solo LEE de ventas, entradas,
-- cliente_anticipos y proveedor_adelantos, que ya existen. Lo único que hace
-- falta es la fila del menú y sus permisos.
--
-- Idempotente: se puede correr varias veces sin duplicar nada.
-- Equivale a  php artisan db:seed --class=ModulosFinanzasSeeder
-- ═══════════════════════════════════════════════════════════════════════════

BEGIN;

-- 1. El módulo, colgando del padre 'finanzas'. orden 0 = primero del grupo
--    (el menú ordena solo por `orden`, sin desempate: repetir el 1 de Balance
--    dejaría el orden al azar).
INSERT INTO modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT m.id, 'Estado de cuenta', 'finanzas.estado-cuenta', 'Users',
       '/finanzas/estado-cuenta', 0, true, NOW(), NOW()
FROM modulos m
WHERE m.slug = 'finanzas'
ON CONFLICT (slug) DO NOTHING;

-- 2. Permiso de lectura para todos los roles admin (de TODAS las empresas).
--    Solo 'ver': el módulo es de consulta, no crea ni edita nada.
INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, NOW(), NOW()
FROM roles r
CROSS JOIN modulos m
WHERE r.es_admin = true
  AND m.slug = 'finanzas.estado-cuenta'
ON CONFLICT (rol_id, modulo_id) DO NOTHING;

COMMIT;

-- ── Verificación ───────────────────────────────────────────────────────────
-- SELECT m.id, m.slug, m.nombre, m.ruta, m.orden, m.padre_id
--   FROM modulos m WHERE m.slug = 'finanzas.estado-cuenta';
--
-- SELECT r.id, r.nombre, r.empresa_id, p.ver
--   FROM permisos p
--   JOIN roles r   ON r.id = p.rol_id
--   JOIN modulos m ON m.id = p.modulo_id
--  WHERE m.slug = 'finanzas.estado-cuenta';
