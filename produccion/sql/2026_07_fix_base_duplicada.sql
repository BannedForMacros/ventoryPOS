-- ─────────────────────────────────────────────────────────────────────────────
-- Fix: unidad base duplicada → las ENTRADAS salían con "UND" por defecto
-- ─────────────────────────────────────────────────────────────────────────────
-- Contexto:
--   Al editar un producto y cambiar su unidad base (p.ej. de "UND" a "m3"),
--   si la unidad vieja ya tenía ventas, ProductoController@update la DESACTIVABA
--   (activo=false) pero NO limpiaba su es_base. La nueva base se creaba también
--   con es_base=true. Resultado: dos filas con es_base=true por producto.
--   El editor de entradas tomaba la PRIMERA por id (la "UND" vieja, inactiva) →
--   la entrada salía por defecto en UND en vez de la base real.
--
-- Alcance real detectado: productos 6558, 6560, 7133 (arena / arenilla / piedra).
--
-- Corrección: quitar es_base a la presentación INACTIVA cuando el producto ya
-- tiene OTRA base ACTIVA. No se elimina nada (esas filas tienen ventas históricas).
-- Idempotente: correrlo dos veces no hace daño.

UPDATE producto_unidades pu
SET es_base = false
WHERE pu.es_base = true
  AND pu.activo = false
  AND EXISTS (
      SELECT 1
      FROM producto_unidades pu2
      WHERE pu2.producto_id = pu.producto_id
        AND pu2.es_base = true
        AND pu2.activo = true
  );
