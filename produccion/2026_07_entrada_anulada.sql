-- ============================================================================
-- Anular entradas confirmadas: nuevo estado 'anulada' en el enum de entradas
-- ============================================================================
-- Permite anular una entrada YA CONFIRMADA (antes solo se borraban borradores).
-- Al marcarla 'anulada' sale de TODO lo correlacionado, porque stock, kardex,
-- cuentas por pagar, balance y estado de cuenta filtran estado='confirmado':
--   Stock::reconstruir / kardex:reconstruir  → e.estado='confirmado'
--   CuentasPorPagar / BalanceDiarioService   → ->confirmado()
--   EstadoCuentaService (proveedor)          → estado='confirmado'
-- El código revierte además el stock (kardex 'entrada_reverso' + reconstruir CPP)
-- y los pagos/tesorería en vivo. Idempotente.
-- ============================================================================

ALTER TYPE estado_entrada_enum ADD VALUE IF NOT EXISTS 'anulada';
