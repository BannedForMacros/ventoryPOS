-- ============================================================================
-- Fix: pendiente fantasma por anticipos ANULADOS + dato real de V-0018
-- Empresa: HYC FERROMATERIALES SRL (empresa_id = 1097)
-- ============================================================================
-- Contexto:
--   La venta V-0018 (id 1529, LADRILLO PANDERETA FORTES, 1300 und) se editó 3
--   veces. Cada edición ANULABA el anticipo "pendiente por entregar" anterior y
--   creaba uno nuevo, PERO los items del anticipo anulado conservaban su
--   cantidad_pendiente. Como Stock::reconstruir y kardex:reconstruir sumaban ese
--   pendiente sin mirar el estado del anticipo, cada recálculo revivía 600 und
--   fantasma (anticipos 254 y 255, 300 c/u) e inflaba el stock.
--
--   Además, la operación real fue: 400 llevadas en el momento + 900 pendientes,
--   entregadas el 20/07 (E-0009). Por el forcejeo con el formulario quedó
--   registrado 300 pendiente / 300 entregado. Se corrige a 900/900.
--
-- El bug de código ya está corregido en:
--   app/Models/Stock.php, app/Console/Commands/ReconstruirKardex.php,
--   app/Services/VentaService.php  (filtran/limpian anticipos anulados).
-- Este script limpia los datos históricos que quedaron sucios.
-- ============================================================================

BEGIN;

-- ── Problema 1: matar el pendiente fantasma de TODO anticipo anulado ─────────
-- Idempotente: cubre 254/255 de V-0018 y cualquier otro anulado que quedara sucio.
UPDATE cliente_anticipo_items ci
SET    cantidad_pendiente = 0
FROM   cliente_anticipos an
WHERE  ci.cliente_anticipo_id = an.id
  AND  an.estado = 'anulado'
  AND  ci.cantidad_pendiente > 0;

-- ── Problema 2: dato real de V-0018 → 900 pendiente / 900 entregado ──────────
-- Anticipo vigente 256, item 145 (LADRILLO PANDERETA FORTES). Pendiente actual = 0
-- (todo entregado); se corrige la cantidad original 300 → 900.
UPDATE cliente_anticipo_items
SET    cantidad = 900
WHERE  id = 145
  AND  cliente_anticipo_id = 256
  AND  producto_id = 7483;

-- Entrega E-0009 (aplicacion 17) e item de entrega 13: 300 → 900. Valor 900*0.69.
UPDATE cliente_anticipo_aplicacion_items
SET    cantidad = 900
WHERE  id = 13
  AND  cliente_anticipo_aplicacion_id = 17
  AND  cliente_anticipo_item_id = 145;

UPDATE cliente_anticipo_aplicaciones
SET    cantidad = 900, monto = 621.00
WHERE  id = 17
  AND  venta_id = 1529;

-- Valor del anticipo material vigente (informativo; no mueve tesorería). saldo=0.
UPDATE cliente_anticipos
SET    monto = 621.00
WHERE  id = 256
  AND  venta_id = 1529;

COMMIT;

-- ============================================================================
-- Después de aplicar, reconstruir stock y kardex de HYC:
--   php artisan kardex:reconstruir --empresa=1097
--   php artisan stock:recalcular   --empresa=1097
--
-- Verificación esperada (LADRILLO PANDERETA FORTES, producto_id 7483):
--   - stock.cantidad = -1000  (el usuario controla el negativo)
--   - kardex de V-0018: venta -400 (salió al vender) + entrega_pendiente -900
--   - suma del kardex = tabla stock (cuadre exacto)
-- ============================================================================
