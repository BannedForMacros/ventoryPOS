-- ============================================================================
--  ventoryPOS · Visor de ventas: leer con IA la foto del cuaderno de ventas
--  Fecha: 2026-10-05
--
--  QUÉ HACE: agrega dos columnas a empresas y tres tablas nuevas. No modifica
--  ninguna fila existente y es idempotente.
--
--    empresas.usa_visor_ventas            activa el visor (por defecto apagado;
--                                         lo enciende el superadmin: gasta API)
--    empresas.visor_ventas_limite_diario  lecturas por día que da el plan (2)
--    visor_ventas_sesiones                cada foto leída: quién, cuándo, cuánto
--                                         leyó, cuántos tokens costó y la huella
--                                         de la foto (para no leer la misma dos veces)
--    visor_ventas_aprendizaje             lo que la cajera corrigió ("guts" →
--                                         Guantes quirúrgicos): la próxima foto
--                                         lo reconoce solo
--    visor_ventas_cobradas                cada venta del cuaderno ya cobrada, con
--                                         sus huellas, para avisar si se intenta
--                                         cargar de nuevo
--
--  Con la función apagada, el POS queda exactamente como hoy.
-- ============================================================================

BEGIN;

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS usa_visor_ventas boolean NOT NULL DEFAULT false;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS visor_ventas_limite_diario integer NOT NULL DEFAULT 2;

CREATE TABLE IF NOT EXISTS visor_ventas_sesiones (
    id              bigserial PRIMARY KEY,
    empresa_id      bigint NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    user_id         bigint NOT NULL REFERENCES users(id),
    estado          varchar(20) NOT NULL DEFAULT 'procesando',  -- procesando | leida | error (no se cobró) | fallida (se cobró: gasta cupo) | reusada (misma foto ya leída: gratis)
    ventas_leidas   integer NOT NULL DEFAULT 0,
    modelo          varchar(60),
    tokens_entrada  integer,
    tokens_salida   integer,
    error           text,
    created_at      timestamp(0) without time zone,
    updated_at      timestamp(0) without time zone
);

CREATE INDEX IF NOT EXISTS visor_ventas_sesiones_empresa_fecha_idx
    ON visor_ventas_sesiones (empresa_id, created_at);

ALTER TABLE visor_ventas_sesiones ADD COLUMN IF NOT EXISTS foto_hash varchar(64);
CREATE INDEX IF NOT EXISTS visor_ventas_sesiones_foto_idx
    ON visor_ventas_sesiones (empresa_id, foto_hash);

-- Lo que devolvió la lectura: si la misma foto llega otra vez (se cortó la
-- conexión, recargó el POS) se devuelve gratis en vez de pagarla de nuevo.
ALTER TABLE visor_ventas_sesiones ADD COLUMN IF NOT EXISTS lectura jsonb;

CREATE TABLE IF NOT EXISTS visor_ventas_aprendizaje (
    id           bigserial PRIMARY KEY,
    empresa_id   bigint NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    texto        varchar(200) NOT NULL,   -- lo escrito, normalizado (sin cantidad ni tildes)
    producto_id  bigint NOT NULL REFERENCES productos(id) ON DELETE CASCADE,
    veces        integer NOT NULL DEFAULT 1,
    created_at   timestamp(0) without time zone,
    updated_at   timestamp(0) without time zone,
    UNIQUE (empresa_id, texto)
);

CREATE TABLE IF NOT EXISTS visor_ventas_cobradas (
    id                bigserial PRIMARY KEY,
    empresa_id        bigint NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    venta_id          bigint NOT NULL REFERENCES ventas(id) ON DELETE CASCADE,
    user_id           bigint NOT NULL REFERENCES users(id),
    fecha_cuaderno    varchar(20),
    total_cuaderno    numeric(12,2),
    huella_texto      varchar(64) NOT NULL,   -- lo leído (fecha, total y renglones)
    huella_productos  varchar(64) NOT NULL,   -- lo cobrado (fecha, total y productos)
    created_at        timestamp(0) without time zone,
    updated_at        timestamp(0) without time zone
);
-- De qué lectura y en qué posición estaba cada venta cobrada: "ya cobrada" es
-- seguro solo dentro de la MISMA lectura (dos ventas iguales en el día son
-- ventas distintas). Una lectura reusada (misma foto, otra cajera u otro día)
-- apunta a la original con origen_id; `cruce` guarda lo ya cruzado con el
-- catálogo para no rehacerlo en cada carga del POS.
ALTER TABLE visor_ventas_cobradas ADD COLUMN IF NOT EXISTS sesion_id bigint REFERENCES visor_ventas_sesiones(id) ON DELETE SET NULL;
ALTER TABLE visor_ventas_cobradas ADD COLUMN IF NOT EXISTS indice integer;
CREATE INDEX IF NOT EXISTS visor_ventas_cobradas_sesion_idx ON visor_ventas_cobradas (sesion_id, indice);
ALTER TABLE visor_ventas_sesiones ADD COLUMN IF NOT EXISTS origen_id bigint REFERENCES visor_ventas_sesiones(id) ON DELETE SET NULL;
ALTER TABLE visor_ventas_sesiones ADD COLUMN IF NOT EXISTS cruce jsonb;

CREATE INDEX IF NOT EXISTS visor_ventas_cobradas_texto_idx ON visor_ventas_cobradas (empresa_id, huella_texto);
CREATE INDEX IF NOT EXISTS visor_ventas_cobradas_productos_idx ON visor_ventas_cobradas (empresa_id, huella_productos);

COMMIT;
