-- ============================================================================
-- Token de impresora por caja (agente local VentoryPrint.exe)
-- ----------------------------------------------------------------------------
-- Cada caja registradora tiene una PC con el agente de impresión escuchando
-- en http://127.0.0.1:9111. El token identifica de forma única a la caja ante
-- su agente: el ticket solo se imprime si el token del payload coincide con
-- el configurado en el agente de esa PC.
--
-- Ejecutar a mano en PostgreSQL (NO es una migración Laravel).
-- ============================================================================

-- 1) Nueva columna en cajas. varchar(64) da holgura: los tokens generados
--    miden 32 (uuid sin guiones, backfill) o 40 (hex desde la app).
ALTER TABLE cajas ADD COLUMN IF NOT EXISTS token_impresora varchar(64);

-- 2) Backfill: poblar un token aleatorio para las cajas existentes que aún
--    no tienen uno. gen_random_uuid() (pgcrypto/core en PG13+) genera un UUID
--    v4; se le quitan los guiones para dejar 32 caracteres hex.
UPDATE cajas
SET token_impresora = replace(gen_random_uuid()::text, '-', '')
WHERE token_impresora IS NULL;

-- 3) Índice único: dos cajas jamás pueden compartir token, porque el token es
--    lo que enruta el ticket a la impresora correcta.
CREATE UNIQUE INDEX IF NOT EXISTS cajas_token_impresora_unique
    ON cajas (token_impresora);
