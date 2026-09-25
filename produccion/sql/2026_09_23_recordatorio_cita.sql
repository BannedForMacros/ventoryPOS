-- ============================================================================
--  ventoryPOS · Recordatorio de cita por WhatsApp
--  Fecha: 2026-09-23
--
--  QUÉ HACE: agrega UNA COLUMNA a `empresas` para el texto del recordatorio.
--  Nullable y sin valor: mientras esté vacía se usa la plantilla por defecto
--  que vive en el código. No toca ninguna fila existente y es idempotente.
--
--  POR QUÉ: el plantón es la mayor pérdida de una peluquería, una veterinaria o
--  un taller — la silla vacía ya no se recupera. Recordar la cita el día antes
--  es lo que lo baja, y hoy había que hacerlo desde el teléfono, a mano, mirando
--  la agenda en otra pantalla.
--
--  La columna `citas.recordatorio_enviado_at` YA EXISTÍA y nadie la escribía.
--  Ahora se usa: es lo que distingue "ya le avisé" de "se me pasó".
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
-- ============================================================================

BEGIN;

ALTER TABLE public.empresas
    ADD COLUMN IF NOT EXISTS agenda_recordatorio_plantilla text;

COMMENT ON COLUMN public.empresas.agenda_recordatorio_plantilla IS
    'Texto del recordatorio de cita. Admite {cliente} {fecha} {hora} {servicios} {profesional} {negocio}. NULL = se usa la plantilla por defecto del código.';

COMMIT;
