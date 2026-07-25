-- ─────────────────────────────────────────────────────────────────────────────
-- "Afecta caja" configurable por empresa (opt-in por módulo)
-- ─────────────────────────────────────────────────────────────────────────────
-- Unifica el selector "Afecta caja a (turno)" que estaba duplicado en ~8
-- controladores y ~7 páginas. Ahora cada empresa decide, POR MÓDULO, si el
-- movimiento puede afectar la caja de un turno. Un cliente que no lo quiere lo
-- apaga en Configuración → Empresa y el selector desaparece de ese módulo.
--
-- Estructura: una sola columna JSONB (afecta_caja_config) como mapa
--   { "deuda": {"activo": true}, "gastos": {"activo": false}, ... }
-- Los defaults viven en App\Support\AfectaCaja::MODULOS; NULL o clave ausente =
-- default del registro. Agregar un módulo nuevo NO requiere tocar el esquema:
-- solo aparece una clave más en el JSON.
--
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr esto
-- en producción.
-- ─────────────────────────────────────────────────────────────────────────────

-- Config por empresa (NULL = todos los módulos en su default del registro).
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS afecta_caja_config JSONB NULL;

-- ── Deuda / préstamos como primer módulo montado sobre el sistema unificado ──
-- Desembolso inicial (fila `deudas`) y cuotas/incrementos (`deuda_pagos`) pueden
-- salir/entrar de la caja de un turno cuando se pagan en efectivo. Se agrega
-- turno_id NULLABLE en ambos: sin turno (transferencia, admin sin caja) = NULL
-- y no afecta caja.
ALTER TABLE deudas
    ADD COLUMN IF NOT EXISTS turno_id BIGINT NULL
    REFERENCES turnos(id) ON DELETE SET NULL;

ALTER TABLE deuda_pagos
    ADD COLUMN IF NOT EXISTS turno_id BIGINT NULL
    REFERENCES turnos(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS deudas_turno_id_index      ON deudas(turno_id);
CREATE INDEX IF NOT EXISTS deuda_pagos_turno_id_index ON deuda_pagos(turno_id);

-- SIN backfill a propósito: "Afecta caja" en deuda es opt-in nuevo (default OFF
-- en App\Support\AfectaCaja). Rellenar turno_id en registros históricos podría
-- alterar el efectivo esperado de un turno ABIERTO de una empresa que nunca lo
-- activó. Los movimientos nuevos llevan turno solo cuando la empresa opta.
