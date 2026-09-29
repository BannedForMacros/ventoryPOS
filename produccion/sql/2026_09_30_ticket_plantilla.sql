-- ============================================================================
--  ventoryPOS · Ticket por plantilla y datos del cliente en la venta
--  Fecha: 2026-09-30
--
--  QUÉ HACE: agrega columnas nuevas, todas opcionales. No modifica ninguna
--  fila existente y es idempotente.
--
--    empresas.ticket_plantilla     plantilla del ticket (NULL = el de siempre)
--    empresas.pos_datos_cliente    el POS pide teléfono, dirección y observación
--    users.telefono                celular de quien atendió (sale en el ticket)
--    ventas.cliente_telefono/cliente_direccion        datos de ESA venta
--    cotizaciones.cliente_telefono/cliente_direccion  datos de ESA cotización
--
--  También registra la pantalla "Ticket" en el menú de Configuración.
--
--  Con todo en su valor por defecto, el sistema y el ticket quedan igual que
--  hoy para todas las empresas.
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

BEGIN;

ALTER TABLE public.empresas     ADD COLUMN IF NOT EXISTS ticket_plantilla  JSON NULL;
ALTER TABLE public.empresas     ADD COLUMN IF NOT EXISTS pos_datos_cliente BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE public.users        ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) NULL;

ALTER TABLE public.ventas       ADD COLUMN IF NOT EXISTS cliente_telefono  VARCHAR(30)  NULL;
ALTER TABLE public.ventas       ADD COLUMN IF NOT EXISTS cliente_direccion VARCHAR(255) NULL;

ALTER TABLE public.cotizaciones ADD COLUMN IF NOT EXISTS cliente_telefono  VARCHAR(30)  NULL;
ALTER TABLE public.cotizaciones ADD COLUMN IF NOT EXISTS cliente_direccion VARCHAR(255) NULL;

-- Pantalla "Ticket" en Configuración, junto a Empresas. Solo administradores.
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Ticket', 'config.ticket', 'ReceiptText', '/configuracion/ticket', 1, true, now(), now()
FROM public.modulos p
WHERE p.slug = 'configuracion'
  AND NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'config.ticket');

INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, true, true, true, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug = 'config.ticket'
  AND r.es_admin = true
  AND NOT EXISTS (
      SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id
  );

COMMIT;
