-- ════════════════════════════════════════════════════════════════════════════
-- DIAGNÓSTICO: "el balance de tal día sale en negativo"
--
-- No modifica NADA: solo consulta. Sirve para explicarle al cliente por qué
-- una línea de Efectivo/Banco quedó negativa en un balance CONFIRMADO.
--
-- Uso:
--   psql -d <BD> -v empresa=1097 -v fecha="'2026-09-11'" -f 2026_09_diagnostico_dia_negativo.sql
-- ════════════════════════════════════════════════════════════════════════════

\echo '=== 1) Lo que GUARDÓ el balance de ese día (foto inmutable) vs lo que da HOY'
SELECT i.categoria,
       i.descripcion,
       i.monto                                   AS monto_guardado,
       b.estado,
       i.created_at                              AS foto_tomada_el
FROM   balance_diario_items i
JOIN   balances_diarios b ON b.id = i.balance_diario_id
WHERE  b.empresa_id = :empresa AND b.fecha = :fecha
   AND i.categoria IN ('efectivo','cuenta_bancaria')
ORDER  BY i.categoria, i.descripcion;

\echo '=== 2) Saldo REAL de cada cuenta a esa fecha, recalculado AHORA'
SELECT c.nombre,
       c.es_efectivo,
       ROUND(SUM(CASE WHEN m.tipo='ingreso' THEN m.monto ELSE -m.monto END), 2) AS saldo_real_a_la_fecha
FROM   cuentas c
LEFT   JOIN cuenta_movimientos m ON m.cuenta_id = c.id AND m.fecha <= :fecha
WHERE  c.empresa_id = :empresa
GROUP  BY c.id, c.nombre, c.es_efectivo
ORDER  BY c.es_efectivo DESC, c.nombre;

\echo '=== 3) LA CAUSA HABITUAL: plata de ese día registrada DESPUÉS del cierre'
\echo '    (préstamos, abonos o ajustes con fecha del día pero creados más tarde)'
SELECT c.nombre AS cuenta, m.fecha, m.tipo, m.monto, m.ref_tipo,
       LEFT(m.descripcion, 60) AS descripcion,
       m.created_at            AS registrado_el
FROM   cuenta_movimientos m
JOIN   cuentas c ON c.id = m.cuenta_id
JOIN   balances_diarios b ON b.empresa_id = m.empresa_id AND b.fecha = :fecha
WHERE  m.empresa_id = :empresa
   AND m.fecha <= :fecha
   AND m.created_at > b.updated_at      -- se registró después de confirmar el balance
ORDER  BY m.monto DESC;

\echo '=== 4) Movimientos del día, agrupados: cuánto entró y cuánto salió por concepto'
SELECT c.nombre AS cuenta, m.ref_tipo, m.tipo, COUNT(*) AS n, SUM(m.monto) AS total
FROM   cuenta_movimientos m
JOIN   cuentas c ON c.id = m.cuenta_id
WHERE  m.empresa_id = :empresa AND m.fecha = :fecha
GROUP  BY c.nombre, m.ref_tipo, m.tipo
ORDER  BY c.nombre, SUM(m.monto) DESC;

\echo '=== 5) Cosas borradas/editadas alrededor del cierre (también mueven el saldo)'
SELECT accion, COUNT(*) AS n, MIN(created_at) AS desde, MAX(created_at) AS hasta
FROM   auditoria
WHERE  empresa_id = :empresa
   AND created_at >= (:fecha)::date
   AND created_at <  (:fecha)::date + INTERVAL '3 days'
   AND (accion LIKE '%eliminad%' OR accion LIKE '%anulad%' OR accion LIKE '%editad%'
        OR accion LIKE 'tesoreria%' OR accion LIKE 'balance%')
GROUP  BY accion
ORDER  BY n DESC;
