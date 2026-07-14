-- =====================================================================
--  KARDEX / MOVIMIENTOS DE INVENTARIO  (2026-07)
-- =====================================================================
--  100% ADITIVO. No altera, actualiza ni borra NINGUNA tabla existente.
--  - Crea una tabla nueva y vacía: movimientos_inventario (el ledger/kardex).
--  - Registra el módulo de menú "Kardex" bajo Reportes.
--  - Da permiso de ver a los mismos roles que ya ven el reporte de Utilidad.
--
--  Seguro de aplicar en producción ANTES de subir el código (queda vacía y
--  nadie la usa hasta que el código nuevo esté desplegado). No afecta el
--  stock, el costo promedio, el balance diario ni ningún flujo existente.
--
--  Después de aplicar este SQL y desplegar el código, ejecutar una vez:
--      php artisan kardex:reconstruir
--  para rellenar el histórico desde los documentos ya existentes.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1) Tabla ledger del kardex (una fila por cada movimiento de stock)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.movimientos_inventario (
    id               bigserial PRIMARY KEY,
    empresa_id       bigint NOT NULL,
    almacen_id       bigint NOT NULL,
    producto_id      bigint NOT NULL,
    fecha            timestamp(0) without time zone NOT NULL DEFAULT now(),
    tipo             varchar(30)  NOT NULL,   -- entrada|salida|venta|devolucion|transferencia_envio|transferencia_recepcion|cierre|entrega_pendiente|ajuste
    referencia_tipo  varchar(30),             -- entrada|salida|venta|devolucion|transferencia|cierre
    referencia_id    bigint,
    documento        varchar(60),             -- numero legible del documento (opcional)
    cantidad         numeric(14,4) NOT NULL,          -- + entra / - sale (en unidad base)
    costo_unitario   numeric(14,4) NOT NULL DEFAULT 0, -- costo del movimiento (unidad base)
    costo_promedio   numeric(14,4) NOT NULL DEFAULT 0, -- CPP resultante tras el movimiento
    saldo_cantidad   numeric(14,4) NOT NULL DEFAULT 0, -- stock resultante tras el movimiento
    saldo_valorizado numeric(14,4) NOT NULL DEFAULT 0, -- saldo_cantidad * costo_promedio
    user_id          bigint,
    created_at       timestamp(0) without time zone,
    updated_at       timestamp(0) without time zone
);

CREATE INDEX IF NOT EXISTS idx_movinv_alm_prod_fecha
    ON public.movimientos_inventario (almacen_id, producto_id, fecha);
CREATE INDEX IF NOT EXISTS idx_movinv_emp_fecha
    ON public.movimientos_inventario (empresa_id, fecha);
CREATE INDEX IF NOT EXISTS idx_movinv_referencia
    ON public.movimientos_inventario (referencia_tipo, referencia_id);

-- ---------------------------------------------------------------------
-- 2) Módulo de menú "Kardex" bajo Reportes (slug reportes.kardex)
--    Idempotente: no se duplica si el SQL se corre dos veces.
-- ---------------------------------------------------------------------
INSERT INTO public.modulos (id, padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT
    nextval('public.modulos_id_seq'),
    (SELECT id FROM public.modulos WHERE slug = 'reportes' LIMIT 1),
    'Kardex',
    'reportes.kardex',
    'ScrollText',
    '/reportes/kardex',
    8,
    true,
    now(),
    now()
WHERE NOT EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'reportes.kardex');

-- ---------------------------------------------------------------------
-- 3) Permiso "ver" para los roles que YA pueden ver el reporte de Utilidad.
--    (Los roles admin ya lo ven por bypass; esto cubre roles no-admin.)
--    Solo INSERTA filas nuevas; nunca toca permisos existentes.
-- ---------------------------------------------------------------------
INSERT INTO public.permisos (id, rol_id, modulo_id, ver, crear, editar, eliminar, created_at, updated_at)
SELECT
    nextval('public.permisos_id_seq'),
    p.rol_id,
    (SELECT id FROM public.modulos WHERE slug = 'reportes.kardex' LIMIT 1),
    true, false, false, false,
    now(), now()
FROM public.permisos p
JOIN public.modulos m ON m.id = p.modulo_id
WHERE m.slug = 'reportes.utilidad'
  AND p.ver = true
  AND NOT EXISTS (
      SELECT 1 FROM public.permisos px
      WHERE px.rol_id = p.rol_id
        AND px.modulo_id = (SELECT id FROM public.modulos WHERE slug = 'reportes.kardex' LIMIT 1)
  );

COMMIT;
