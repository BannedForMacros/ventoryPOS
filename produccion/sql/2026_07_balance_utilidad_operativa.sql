-- ─────────────────────────────────────────────────────────────────────────────
-- Balance Diario — rediseño:
--   • Se agrega la UTILIDAD OPERATIVA del día (ventas − costo − gastos), que es
--     la ganancia real ("cuánto vendí, cuánto gané"). Reemplaza como titular a
--     la vieja "utilidad_real = Δpatrimonio + gastos", que salía negativa al
--     pagar proveedores o comprar mercadería (eso NO es pérdida).
--   • balance_neto sigue siendo el PATRIMONIO del dueño (a favor − en contra);
--     ahora "en contra" ya no incluye los egresos brutos (gastos_emitidos), y
--     "a favor" usa el saldo REAL neto por cuenta — el patrimonio da igual pero
--     queda limpio y explicable.
--
-- El proyecto aplica esquema por SQL directo (no migraciones). Correr en prod.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE balances_diarios
    ADD COLUMN IF NOT EXISTS ventas_dia   numeric(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS costo_dia    numeric(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS utilidad_dia numeric(12,2) NULL;
