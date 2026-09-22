-- ============================================================================
--  ventoryPOS · Reporte de agenda por profesional
--  Fecha: 2026-09-22
--
--  QUÉ HACE: registra UN MÓDULO NUEVO en el menú (bajo Reportes) y le da el
--  permiso a los roles administradores. No crea tablas, no toca ninguna fila
--  existente y es idempotente.
--
--  El reporte solo lee: citas, sus ítems y la venta que nació de cada cita.
--  Nada del flujo de caja ni del inventario cambia de comportamiento.
--
--  "Profesional" es neutro a propósito, como la columna `citas.profesional_id`
--  que ya existía: es la estilista en una peluquería, el veterinario en una
--  clínica y el técnico en un taller. La empresa que no usa agenda no ve el
--  módulo (el controlador corta con `usa_agenda`).
--
--  RECORDATORIO: en este servidor NO se ejecuta `php artisan migrate`.
--  Este script es la única vía.
-- ============================================================================

BEGIN;

-- Cuelga de Reportes (padre_id = el módulo 'reportes') y se coloca antes de
-- Auditoría, que va siempre al final con orden 99.
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id, 'Agenda', 'reportes.agenda', 'CalendarClock', '/reportes/agenda', 9, true, now(), now()
FROM public.modulos p
WHERE p.slug = 'reportes'
  AND NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'reportes.agenda');

-- Solo a los roles administradores, igual que el resto de reportes: quien
-- atiende no tiene por qué ver cuánto produjeron sus compañeros.
INSERT INTO public.permisos (rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT r.id, m.id, true, false, false, false, now(), now()
FROM public.roles r
CROSS JOIN public.modulos m
WHERE m.slug = 'reportes.agenda'
  AND r.es_admin = true
  AND NOT EXISTS (
      SELECT 1 FROM public.permisos p WHERE p.rol_id = r.id AND p.modulo_id = m.id
  );

COMMIT;
