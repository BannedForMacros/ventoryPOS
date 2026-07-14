-- =====================================================================
-- Fix costo CEMENTO ROJO MOCHICA (Ferretería H&C - RUC 20600134648)
-- ---------------------------------------------------------------------
-- Problema: el producto tenía precio_costo = 0, por lo que el balance
-- lo valorizaba con stock.costo_promedio = 24.4308 (SIN IGV). Debe ser
-- el costo CON IGV = 24.87 * 1.18 = 29.35. Subvaluaba 313 u ~ S/1,539.71.
--
-- Portable: resuelve empresa por RUC y producto por nombre (NO usa IDs,
-- que difieren entre esta BD y producción). Idempotente: re-ejecutarlo
-- deja los mismos valores.
-- =====================================================================

BEGIN;

-- (1) Cargar el costo CON IGV en el catálogo (lo que usa el balance primero)
UPDATE productos p
SET    precio_costo = 29.35,
       updated_at   = NOW()
FROM   empresas e
WHERE  e.id = p.empresa_id
  AND  e.ruc = '20600134648'
  AND  p.nombre = 'CEMENTO ROJO MOCHICA';

-- (2) Alinear el costo promedio del stock al mismo criterio con IGV
--     (así el producto queda consistente con el resto del catálogo,
--      donde precio_costo = costo_promedio = costo * 1.18)
UPDATE stock s
SET    costo_promedio = 29.35,
       updated_at     = NOW()
FROM   productos p
JOIN   empresas e ON e.id = p.empresa_id
WHERE  s.producto_id = p.id
  AND  e.ruc = '20600134648'
  AND  p.nombre = 'CEMENTO ROJO MOCHICA';

-- (3) Verificación: debe mostrar precio_costo=29.35, costo_promedio=29.35
--     y el nuevo valor valorizado del stock de este producto.
SELECT p.nombre,
       p.precio_costo,
       s.cantidad,
       s.costo_promedio,
       ROUND(s.cantidad * COALESCE(NULLIF(p.precio_costo,0), s.costo_promedio), 2) AS valor_con_igv
FROM   productos p
JOIN   stock s    ON s.producto_id = p.id
JOIN   empresas e ON e.id = p.empresa_id
WHERE  e.ruc = '20600134648'
  AND  p.nombre = 'CEMENTO ROJO MOCHICA';

COMMIT;
