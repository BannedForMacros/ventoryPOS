-- POS: "Venta al crédito" y "Pendiente por entregar" configurables por empresa.
-- Hay negocios (boticas, peluquerías) que nunca venden al crédito ni dejan
-- mercadería pendiente, y ver esas casillas en el POS confunde a la cajera.
--
-- DEFAULT true: las empresas que ya existen (HYC, etc.) siguen viéndolas igual.
-- Las empresas nuevas nacen con ambas apagadas (OnboardingEmpresaService).
--
-- APLICAR ANTES DEL DEPLOY: el alta de empresa y el guardado de Configuración
-- escriben estas columnas.
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pos_permite_credito boolean NOT NULL DEFAULT true;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pos_permite_pendiente_entrega boolean NOT NULL DEFAULT true;
