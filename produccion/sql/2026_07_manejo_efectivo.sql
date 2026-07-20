-- ─────────────────────────────────────────────────────────────────────────────
-- Manejo de efectivo configurable por empresa:
--   · Modo de apertura de caja: libre (actual) | arrastre | fondo_fijo
--   · Pregunta de destino del efectivo al cerrar turno (queda en caja /
--     se entrega a administración / parcial)
--   · Retiros de caja ("Entrega a administración", sangrías) durante el turno
--   · Desglose "Caja Grande" (efectivo en cajas vs custodia administración)
--
-- TODOS los defaults reproducen el comportamiento actual: ninguna empresa
-- cambia hasta que active los ajustes en Configuración → Empresa.
-- El proyecto aplica esquema por SQL directo, NO por migraciones: correr
-- esto en producción.
-- ─────────────────────────────────────────────────────────────────────────────

-- Configuración por empresa
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS modo_apertura_caja VARCHAR(20) NOT NULL DEFAULT 'libre';
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS apertura_editable BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS usa_retiros_caja BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS retiro_requiere_aprobacion BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS cierre_pregunta_destino BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS usa_caja_grande BOOLEAN NOT NULL DEFAULT FALSE;

-- Fondo fijo por caja (solo aplica con modo_apertura_caja = 'fondo_fijo')
ALTER TABLE cajas ADD COLUMN IF NOT EXISTS fondo_fijo_monto NUMERIC(12,2) NOT NULL DEFAULT 0;

-- Cierre de turno: qué pasó con el efectivo final
--   efectivo_arrastre: monto que QUEDÓ en el cajón (apertura sugerida del
--     siguiente turno de esa caja cuando el modo es 'arrastre').
--   destino_efectivo: 'caja' | 'administracion' | 'parcial' (NULL = cierre
--     anterior a esta función o empresa sin la pregunta activada).
ALTER TABLE turnos ADD COLUMN IF NOT EXISTS efectivo_arrastre NUMERIC(12,2) NULL;
ALTER TABLE turnos ADD COLUMN IF NOT EXISTS destino_efectivo VARCHAR(20) NULL;

-- Retiros de efectivo de un turno (sangría / entrega a administración).
-- NO son gastos: es traslado de custodia; no tocan tesorería (el neto de la
-- cuenta Efectivo no cambia, solo cambia de manos). Restan del esperado.
CREATE TABLE IF NOT EXISTS turno_retiros (
    id             BIGSERIAL PRIMARY KEY,
    empresa_id     BIGINT NOT NULL REFERENCES empresas(id),
    turno_id       BIGINT NOT NULL REFERENCES turnos(id),
    user_id        BIGINT NOT NULL REFERENCES users(id),
    aprobado_por   BIGINT NULL REFERENCES users(id),
    concepto       VARCHAR(100) NOT NULL DEFAULT 'Entrega a administración',
    monto          NUMERIC(12,2) NOT NULL,
    momento        VARCHAR(20) NOT NULL DEFAULT 'turno',  -- 'turno' | 'cierre'
    estado         VARCHAR(20) NOT NULL DEFAULT 'registrado', -- 'registrado' | 'aprobado'
    observacion    TEXT NULL,
    created_at     TIMESTAMP NULL,
    updated_at     TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS turno_retiros_turno_id_index ON turno_retiros (turno_id);
CREATE INDEX IF NOT EXISTS turno_retiros_empresa_id_index ON turno_retiros (empresa_id);
