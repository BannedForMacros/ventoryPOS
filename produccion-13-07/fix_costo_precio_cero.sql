-- =====================================================================
-- Fix precio_costo = 0  (Ferretería H&C - RUC 20600134648)
-- ---------------------------------------------------------------------
-- Carga el costo CON IGV (= costo_sin_igv del Excel "1307 stock" * 1.18)
-- a los productos que tenían precio_costo = 0 y SÍ figuran en ese Excel.
-- El balance valoriza con precio_costo (fallback costo_promedio); al
-- setearlo, deja de caer a un costo_promedio que podía estar sin IGV.
--
-- NO incluye CEMENTO ROJO MOCHICA (ya corregido en fix_costo_mochica.sql).
-- Portable (resuelve empresa por RUC, producto por nombre; sin IDs).
-- Transaccional e idempotente.
--
-- NO tocados (no están en el Excel o su costo es 0 -> sin fuente de costo):
--   Cemento Azul Antisalitre PACASMAYO (ya tiene costo_promedio 33.21 c/IGV, OK),
--   CARRETILLA T/BUGGY, Cerradura 444, CRUZETAS, FORTACHO, FRAGUA GRIS,
--   LLAVE DE DUCHA, Palana Recta, PEGAMENTO 1/16, Tee Sal 3, CEMENTO ROJO QHUNA,
--   y ladrillos con cantidad 0 (PPRUEBA, TAYSON, TERRANOVA-stock0, etc.).
-- =====================================================================

BEGIN;

-- (1) precio_costo (con IGV) en el catálogo
WITH nuevos(nombre, precio_costo) AS (
  VALUES
    ('Arena Amarilla Por Lata S/M'::varchar, 0.70::numeric),
    ('Cemento Blanco KOLORCIX', 2.04),
    ('CEMENTO ROJO PACASMAYO', 30.60),
    ('Cemento VARIOS', 0.60),
    ('LADRILLO PANDERETA TERRANOVA', 0.28),
    ('MACHETE CAÑERO M/MADERA C/GANCHO 14 BELLOTA', 12.50),
    ('PINTURA EN BALDE CREMA KOLORCIX', 13.00)
)
UPDATE productos p
SET    precio_costo = n.precio_costo,
       updated_at   = NOW()
FROM   nuevos n, empresas e
WHERE  e.id = p.empresa_id
  AND  e.ruc = '20600134648'
  AND  p.nombre = n.nombre;

-- (2) alinear costo_promedio del stock al mismo costo con IGV
WITH nuevos(nombre, precio_costo) AS (
  VALUES
    ('Arena Amarilla Por Lata S/M'::varchar, 0.70::numeric),
    ('Cemento Blanco KOLORCIX', 2.04),
    ('CEMENTO ROJO PACASMAYO', 30.60),
    ('Cemento VARIOS', 0.60),
    ('LADRILLO PANDERETA TERRANOVA', 0.28),
    ('MACHETE CAÑERO M/MADERA C/GANCHO 14 BELLOTA', 12.50),
    ('PINTURA EN BALDE CREMA KOLORCIX', 13.00)
)
UPDATE stock s
SET    costo_promedio = n.precio_costo,
       updated_at     = NOW()
FROM   nuevos n
JOIN   productos p ON p.nombre = n.nombre
JOIN   empresas  e ON e.id = p.empresa_id AND e.ruc = '20600134648'
WHERE  s.producto_id = p.id;

-- (3) Verificación
SELECT p.nombre, p.precio_costo, s.cantidad, s.costo_promedio,
       ROUND(s.cantidad * COALESCE(NULLIF(p.precio_costo,0), s.costo_promedio),2) AS valor_con_igv
FROM   productos p
JOIN   stock s    ON s.producto_id = p.id
JOIN   empresas e ON e.id = p.empresa_id
WHERE  e.ruc = '20600134648'
  AND  p.nombre IN (
    'Arena Amarilla Por Lata S/M','Cemento Blanco KOLORCIX','CEMENTO ROJO PACASMAYO',
    'Cemento VARIOS','LADRILLO PANDERETA TERRANOVA',
    'MACHETE CAÑERO M/MADERA C/GANCHO 14 BELLOTA','PINTURA EN BALDE CREMA KOLORCIX')
ORDER BY valor_con_igv DESC;

COMMIT;
