-- ============================================================================
-- MERCADERÍA EN TRÁNSITO (entradas que ya se compraron pero aún no llegan)
-- ============================================================================
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción.
--
-- PROBLEMA QUE RESUELVE
-- Hasta hoy una entrada solo podía estar en 'borrador' (no existe para nadie)
-- o 'confirmado' (el stock entra YA). Falta el caso real del negocio que compra
-- a un almacén central o a un distribuidor: la mercadería está facturada y
-- despachada, pero llega en 3-5 días. Confirmarla mete stock fantasma que se
-- puede vender; dejarla en borrador la hace invisible.
--
-- POR QUÉ ES SEGURO
-- 'en_transito' es un valor NUEVO del enum, así que los ~19 filtros que ya
-- dicen estado='confirmado' (Stock::reconstruir, kardex:reconstruir, balance,
-- estado de cuenta) lo excluyen SOLOS, sin tocarles una línea. La mercadería en
-- tránsito queda fuera del stock, del kardex y del inventario valorizado, que
-- es exactamente lo correcto: todavía no está en la tienda.
-- El stock entra donde siempre: cuando el estado pasa a 'confirmado'
-- (EntradaObserver), que ahora significa RECIBIDO.
--
-- OJO: ALTER TYPE ... ADD VALUE no puede ir dentro de una transacción explícita
-- si luego se usa el valor. Correr este archivo SIN envolver en BEGIN/COMMIT
-- (psql -f hace autocommit por sentencia, que es lo que queremos).
-- ============================================================================

ALTER TYPE estado_entrada_enum ADD VALUE IF NOT EXISTS 'en_transito';

-- Fecha en que se ESPERA la mercadería (la que el proveedor promete) y fecha en
-- que efectivamente llegó. La segunda se sella al recibir.
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS fecha_estimada_llegada date;
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS fecha_recepcion        date;

COMMENT ON COLUMN entradas.fecha_estimada_llegada IS 'Fecha prometida de llegada mientras la entrada está en_transito. Si ya pasó y no llegó, la entrada se marca como atrasada.';
COMMENT ON COLUMN entradas.fecha_recepcion        IS 'Fecha real en que llegó la mercadería. Se sella al pasar de en_transito a confirmado.';

-- Configuración por empresa. Ambos apagados por defecto: una empresa que no los
-- encienda no nota ningún cambio respecto de hoy.
--
--   usa_mercaderia_transito   → habilita el estado "en camino" en Entradas.
--   vende_mercaderia_transito → deja SOBREVENDER, pero solo hasta lo que
--                               realmente viene en camino. Es la alternativa
--                               fina a permite_stock_negativo (que abre la
--                               puerta para todo el catálogo sin control).
--                               Solo tiene efecto si el anterior está activo.
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS usa_mercaderia_transito   boolean NOT NULL DEFAULT false;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS vende_mercaderia_transito boolean NOT NULL DEFAULT false;

-- El POS consulta "qué viene en camino" por almacén en cada carga: sin este
-- índice sería un seq scan sobre entradas en cada apertura de caja.
CREATE INDEX IF NOT EXISTS entradas_empresa_estado_idx ON entradas (empresa_id, estado);
