-- ============================================================================
--  ventoryPOS · Estado de la nota de crédito en la devolución
--  Fecha: 2026-09-20
--
--  QUÉ HACE: agrega CINCO COLUMNAS NUEVAS a `devoluciones`, todas opcionales.
--  No toca ninguna fila existente, no cambia ningún valor y es idempotente.
--
--  POR QUÉ: hoy la nota de crédito de una devolución vive solo en el log y en
--  una línea de auditoría. Si el envío a SUNAT falla después de agotar sus
--  reintentos, la devolución queda hecha —el stock volvió y el dinero salió—
--  pero SUNAT sigue viendo declarado el importe original, y NADIE se entera:
--  no hay estado que mirar, ni lista donde aparezca, ni botón para terminarlo.
--
--  Con estas columnas el trabajo pendiente deja de ser invisible: se puede
--  listar, avisar y reintentar desde el sistema.
--
--  LA REGLA DE ORO NO CAMBIA: una nota de crédito que falla NO revierte la
--  devolución. El stock ya volvió al almacén y el dinero ya salió de la caja.
--  Esto solo hace visible lo que quedó a medias.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`. Hay
--  migraciones que figuran como pendientes aunque sus tablas existan, y
--  lanzarlo intentaría rehacerlas (falla en cliente_anticipo_cancelaciones).
--  Este script es la única vía.
-- ============================================================================

BEGIN;

-- Estado de la NC de esta devolución. NULL = nunca se intentó (devoluciones
-- anteriores a este cambio): no se rellena hacia atrás a propósito, inventar un
-- estado para lo viejo sería afirmar algo que no se comprobó.
--
--   no_aplica  la venta no tenía comprobante informado a SUNAT (ticket, o
--              emisión apagada). No hay nada que acreditar y está bien así.
--   pendiente  encolada, todavía sin desenlace.
--   esperando  el comprobante aún no llegó a SUNAT (típico de una boleta, que
--              espera el Resumen Diario de las 23:55). No es un fallo.
--   emitida    SUNAT la tiene.
--   fallida    se agotaron los intentos. ESTE es el que hay que mirar: la
--              devolución está hecha y SUNAT sigue viendo el importe original.
ALTER TABLE public.devoluciones
    ADD COLUMN IF NOT EXISTS nota_credito_estado    character varying(20),
    ADD COLUMN IF NOT EXISTS nota_credito_numero    character varying(20),
    ADD COLUMN IF NOT EXISTS nota_credito_facturamac_id integer,
    ADD COLUMN IF NOT EXISTS nota_credito_error     text,
    ADD COLUMN IF NOT EXISTS nota_credito_at        timestamp(0) without time zone;

COMMENT ON COLUMN public.devoluciones.nota_credito_estado IS
    'no_aplica | pendiente | esperando | emitida | fallida. NULL = devolución anterior a este cambio.';
COMMENT ON COLUMN public.devoluciones.nota_credito_error IS
    'Último error al emitir la NC. Se limpia cuando un reintento sale bien.';

-- Para la bandeja de "notas de crédito sin emitir": son pocas filas sobre
-- muchas, así que el índice va PARCIAL y solo cubre lo que se va a buscar.
CREATE INDEX IF NOT EXISTS devoluciones_nota_credito_pendiente_index
    ON public.devoluciones (empresa_id, nota_credito_estado)
    WHERE nota_credito_estado IN ('pendiente', 'esperando', 'fallida');

COMMIT;
