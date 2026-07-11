-- ============================================================================
--  Corrección de precio_costo borrado por edición de productos
-- ----------------------------------------------------------------------------
--  Problema: al editar un producto, el sistema ponía productos.precio_costo = 0.
--  Como el balance valorizaba el inventario con ese campo, se "perdía" valor.
--  Esta corrección restaura el precio_costo desde el costo real ponderado del
--  inventario (stock.costo_promedio), que NUNCA se borró.
--
--  Es SEGURO correrlo varias veces (idempotente) y NO toca productos que ya
--  tienen un precio_costo válido. Aplica a TODAS las empresas.
--
--  Recomendado: correr primero la VISTA PREVIA para ver qué cambiará.
-- ============================================================================

-- ── VISTA PREVIA (no modifica nada): qué productos se corregirían ───────────
SELECT p.empresa_id,
       p.id,
       p.nombre,
       p.precio_costo                    AS costo_actual,
       MAX(s.costo_promedio)             AS costo_nuevo,
       ROUND(MAX(s.cantidad) * MAX(s.costo_promedio), 2) AS valor_recuperado
FROM   productos p
JOIN   stock s ON s.producto_id = p.id
WHERE  COALESCE(p.precio_costo, 0) = 0
  AND  s.costo_promedio > 0
GROUP  BY p.empresa_id, p.id, p.nombre, p.precio_costo
ORDER  BY valor_recuperado DESC;


-- ── CORRECCIÓN (esto SÍ modifica). Descomentar y ejecutar cuando estés listo ─
/*
BEGIN;

-- 1) Producto: restaurar precio_costo desde el costo promedio real.
UPDATE productos p
SET    precio_costo = sub.costo,
       updated_at   = NOW()
FROM ( SELECT producto_id, MAX(costo_promedio) AS costo
       FROM   stock
       WHERE  costo_promedio > 0
       GROUP  BY producto_id ) sub
WHERE  sub.producto_id = p.id
  AND  COALESCE(p.precio_costo, 0) = 0;

-- 2) Unidad base: igualar su precio_costo por consistencia del catálogo.
UPDATE producto_unidades pu
SET    precio_costo = sub.costo,
       updated_at   = NOW()
FROM ( SELECT producto_id, MAX(costo_promedio) AS costo
       FROM   stock
       WHERE  costo_promedio > 0
       GROUP  BY producto_id ) sub
WHERE  sub.producto_id = pu.producto_id
  AND  pu.es_base = true
  AND  COALESCE(pu.precio_costo, 0) = 0;

COMMIT;
*/
