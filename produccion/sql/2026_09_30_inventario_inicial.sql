-- ============================================================================
--  ventoryPOS · Pantalla "Inventario inicial"
--  Fecha: 2026-09-30
--
--  QUÉ HACE: registra UN MÓDULO NUEVO en el menú (bajo Inventario) y le da el
--  permiso completo a los roles administradores. No crea tablas: la pantalla
--  usa `stock_iniciales`, que ya existe (produccion/sql/2026_07_stock_iniciales.sql).
--  Es idempotente.
--
--  La pantalla permite cargar el stock contado con el que arranca cada
--  producto: a mano (solo los que ya se contaron) o subiendo un Excel y
--  eligiendo qué columna es el producto, la cantidad, la unidad y el costo.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

BEGIN;

-- Por si el servidor aún no tiene la tabla (script de julio no corrido).
CREATE TABLE IF NOT EXISTS stock_iniciales (
    id          BIGSERIAL PRIMARY KEY,
    empresa_id  BIGINT       NOT NULL REFERENCES empresas(id)   ON DELETE CASCADE,
    almacen_id  BIGINT       NOT NULL REFERENCES almacenes(id)  ON DELETE CASCADE,
    producto_id BIGINT       NOT NULL REFERENCES productos(id)  ON DELETE CASCADE,
    fecha       DATE         NOT NULL,
    cantidad    NUMERIC(14,4) NOT NULL DEFAULT 0,
    costo       NUMERIC(14,4) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    CONSTRAINT stock_iniciales_almacen_producto_unique UNIQUE (almacen_id, producto_id)
);
CREATE INDEX IF NOT EXISTS stock_iniciales_producto_idx ON stock_iniciales (producto_id);

-- Cuelga de Inventario, justo después de "Stock actual" (orden 1).
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Inventario inicial', 'inventario.inicial', 'PackageCheck', '/inventario/inicial', 1, true, now(), now()
FROM public.modulos p
WHERE p.slug = 'inventario'
  AND NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'inventario.inicial');

-- Solo administradores: cambia el stock de arranque de todo el inventario.
INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug = 'inventario.inicial'
  AND r.es_admin = true
  AND NOT EXISTS (
      SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id
  );

COMMIT;
