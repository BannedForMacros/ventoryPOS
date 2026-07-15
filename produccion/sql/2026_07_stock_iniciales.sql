-- ─────────────────────────────────────────────────────────────────────────────
-- Inventario inicial (stock de apertura) por producto/almacén
-- ─────────────────────────────────────────────────────────────────────────────
-- Problema que resuelve: la carga inicial del inventario se metió DIRECTO en la
-- tabla `stock` (no como movimiento). Cuando se corrió "Recalcular stock", la
-- reconstrucción rearmó el stock SOLO desde movimientos (ventas, entradas...) e
-- ignoró esa carga inicial → quedaron cantidades negativas/erradas.
--
-- Solución: guardar el inventario a una FECHA DE CORTE como "inicial". A partir
-- de ahí, Stock::reconstruir() arranca de este saldo y suma únicamente los
-- movimientos POSTERIORES a la fecha de corte. Así "Recalcular stock" ya no borra
-- la apertura: es reconstrucción-segura para siempre.
--
-- Carga: el comando `stock:cargar-inicial` llena esta tabla desde el Excel del
-- inventario (p.ej. el del 13/07) y deja el stock actual correcto.

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
