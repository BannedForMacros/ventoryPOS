-- ============================================================================
-- DESPACHO EN ALMACEN
-- ============================================================================
-- Habilita a nivel empresa la opcion "Despacho en almacen" en el POS: la venta
-- queda como pendiente de entrega y el stock solo se descuenta cuando el
-- almacenero confirma el despacho. Reutiliza el motor existente de
-- "pendiente por entregar" (anticipos materiales vinculados a la venta).
--
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en produccion.
-- ============================================================================

-- 1. Configuracion por empresa (apagado por defecto).
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS usa_despacho_almacen BOOLEAN NOT NULL DEFAULT FALSE;

-- 2. Modulo para la bandeja del almacenero (bajo Inventario).
INSERT INTO modulos (padre_id, nombre, slug, icono, ruta, orden, activo)
SELECT inv.id, 'Despachos', 'despachos', 'PackageCheck', '/despachos', 10, true
FROM modulos inv
WHERE inv.slug = 'inventario'
ON CONFLICT (slug) DO UPDATE SET
    padre_id = EXCLUDED.padre_id,
    nombre   = EXCLUDED.nombre,
    icono    = EXCLUDED.icono,
    ruta     = EXCLUDED.ruta,
    orden    = EXCLUDED.orden,
    activo   = EXCLUDED.activo;

-- 3. Permisos.
-- Admin: todo.
INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar)
SELECT r.id, m.id, true, true, true, true
FROM roles r
CROSS JOIN modulos m
WHERE r.es_admin = true AND m.slug = 'despachos'
ON CONFLICT (rol_id, modulo_id) DO UPDATE SET
    ver     = true,
    crear   = true,
    editar  = true,
    eliminar= true;

-- Almacenero (rol con nombre exacto "Almacenero"): ver la bandeja y confirmar despachos.
INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar)
SELECT r.id, m.id, true, false, true, false
FROM roles r
CROSS JOIN modulos m
WHERE lower(r.nombre) = 'almacenero' AND m.slug = 'despachos'
ON CONFLICT (rol_id, modulo_id) DO UPDATE SET
    ver     = true,
    editar  = true;
