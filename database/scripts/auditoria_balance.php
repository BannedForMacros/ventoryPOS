<?php
// Auditoría de trazabilidad del Balance Diario — SOLO LECTURA (+ regenerar borrador)
use Illuminate\Support\Facades\DB;

$EMPRESA = 1;
$FECHA   = '2026-07-05'; // fecha de la demo con datos
$fallas  = [];
$ok = function (string $msg) { echo "  ✔ {$msg}\n"; };
$bad = function (string $msg) use (&$fallas) { $fallas[] = $msg; echo "  ✘ FALLA: {$msg}\n"; };
$check = function (string $nombre, float $a, float $b, float $tol = 0.01) use ($ok, $bad) {
    abs($a - $b) <= $tol
        ? $ok("{$nombre}: " . number_format($a, 2))
        : $bad("{$nombre}: esperado " . number_format($b, 2) . " pero hay " . number_format($a, 2) . " (dif " . number_format($a - $b, 2) . ")");
};

$user = App\Models\User::find(1);
app(App\Services\BalanceDiarioService::class)->generar($user, $FECHA); // refresca borrador
$balance = App\Models\BalanceDiario::where('empresa_id', $EMPRESA)->where('fecha', $FECHA)->with('items')->first();

echo "════ 1. ARITMÉTICA DEL BALANCE ({$FECHA}, {$balance->estado}) ════\n";
$favorItems  = $balance->items->where('seccion', 'favor')->sum('monto');
$contraItems = $balance->items->where('seccion', 'contra')->sum('monto');
$check('Σ items A FAVOR == total_favor', (float) $balance->total_favor, (float) $favorItems);
$check('Σ items EN CONTRA == total_contra', (float) $balance->total_contra, (float) $contraItems);
$check('neto == favor − contra', (float) $balance->balance_neto, (float) $balance->total_favor - (float) $balance->total_contra);
if ($balance->balance_anterior !== null) {
    $check('diferencia == neto − anterior', (float) $balance->diferencia, (float) $balance->balance_neto - (float) $balance->balance_anterior);
    $check('utilidad == diferencia + gastos_dia', (float) $balance->utilidad_real, (float) $balance->diferencia + (float) $balance->gastos_dia);
    $ayer = App\Models\BalanceDiario::where('empresa_id', $EMPRESA)->where('fecha', '<', $FECHA)
        ->where('estado', 'confirmado')->orderByDesc('fecha')->first();
    $check('balance_anterior == neto del último confirmado (' . $ayer?->fecha?->toDateString() . ')', (float) $balance->balance_anterior, (float) $ayer->balance_neto);
}
$check('gastos_dia == Σ gastos de la fecha', (float) $balance->gastos_dia, (float) DB::table('gastos')->where('empresa_id', $EMPRESA)->where('fecha', $FECHA)->sum('monto'));

echo "\n════ 2. CADA LÍNEA vs SU FUENTE DE VERDAD ════\n";
foreach ($balance->items->where('es_manual', false) as $it) {
    $nombre = "[{$it->seccion}] {$it->descripcion}";
    switch (true) {
        case in_array($it->categoria, ['efectivo', 'cuenta_bancaria']):
            $real = (float) DB::table('cuenta_movimientos')->where('cuenta_id', $it->ref_id)
                ->where('fecha', '<=', $FECHA)->where('tipo', 'ingreso')->sum('monto');
            $check($nombre . ' (bruto ingresos)', (float) $it->monto, $real); break;
        case $it->categoria === 'gastos_emitidos':
            $real = (float) DB::table('cuenta_movimientos')->where('cuenta_id', $it->ref_id)
                ->where('fecha', '<=', $FECHA)->where('tipo', 'egreso')->sum('monto');
            $check($nombre . ' (egresos)', (float) $it->monto, $real); break;
        case $it->categoria === 'stock':
            $real = (float) DB::table('stock')->join('productos', 'productos.id', '=', 'stock.producto_id')
                ->where('productos.empresa_id', $EMPRESA)->where('productos.activo', true)
                ->selectRaw('COALESCE(SUM(stock.cantidad*productos.precio_costo),0) v')->value('v');
            $check($nombre, (float) $it->monto, round($real, 2)); break;
        case $it->categoria === 'cxc':
            $real = (float) DB::table('ventas')->where('empresa_id', $EMPRESA)->where('es_credito', true)
                ->where('estado', 'completada')->where('saldo_pendiente', '>', 0)->sum('saldo_pendiente');
            $check($nombre, (float) $it->monto, $real); break;
        case $it->categoria === 'cxp':
            $real = (float) DB::table('entradas')->where('empresa_id', $EMPRESA)->where('estado', 'confirmado')
                ->whereRaw('total - monto_pagado > 0.01')->where('estado_pago', '!=', 'pagado')
                ->selectRaw('COALESCE(SUM(total-monto_pagado),0) v')->value('v');
            $check($nombre, (float) $it->monto, round($real, 2)); break;
        case $it->categoria === 'anticipo_cliente':
            $real = App\Models\ClienteAnticipo::deEmpresa($EMPRESA)->activo()->with('producto')->get()
                ->sum(fn ($a) => $a->valorPasivo());
            $check($nombre, (float) $it->monto, round((float) $real, 2)); break;
        case in_array($it->categoria, ['deuda', 'personal', 'prestamo_otorgado']):
            $real = (float) DB::table('deudas')->where('id', $it->ref_id)->value('saldo');
            $check($nombre, (float) $it->monto, $real); break;
        case $it->categoria === 'adelanto_proveedor':
            $real = (float) DB::table('proveedor_adelantos')->where('id', $it->ref_id)->value('saldo');
            $check($nombre, (float) $it->monto, $real); break;
        case $it->categoria === 'planilla_descuento':
            $real = (float) DB::table('planilla_descuentos')->where('empresa_id', $EMPRESA)
                ->where('estado', 'pendiente')->sum('monto');
            $check($nombre, (float) $it->monto, $real); break;
        default:
            echo "  ? {$nombre}: categoría sin verificador ({$it->categoria})\n";
    }
}

echo "\n════ 3. TESORERÍA: NADA SIN ORIGEN, NADA HUÉRFANO ════\n";
$sinOrigen = DB::table('cuenta_movimientos')->whereNull('ref_tipo')->count();
$sinOrigen === 0 ? $ok('Todos los movimientos tienen ref_tipo') : $bad("{$sinOrigen} movimientos SIN ref_tipo");
$refTables = ['venta' => 'ventas', 'venta_abono' => 'venta_abonos', 'gasto' => 'gastos',
    'entrada_pago' => 'entrada_pagos', 'entrada' => 'entradas', 'deuda_pago' => 'deuda_pagos',
    'cliente_anticipo' => 'cliente_anticipos', 'cliente_anticipo_devolucion' => 'cliente_anticipos',
    'proveedor_adelanto' => 'proveedor_adelantos', 'proveedor_adelanto_devolucion' => 'proveedor_adelantos'];
foreach ($refTables as $ref => $tabla) {
    $huerfanos = DB::table('cuenta_movimientos')->where('ref_tipo', $ref)->whereNotNull('ref_id')
        ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($tabla)->whereColumn("{$tabla}.id", 'cuenta_movimientos.ref_id'))
        ->count();
    $huerfanos === 0 ? $ok("ref '{$ref}' sin huérfanos") : $bad("{$huerfanos} movimientos '{$ref}' apuntan a registros inexistentes");
}
$ajustesSinMotivo = DB::table('cuenta_movimientos')->where('ref_tipo', 'ajuste')
    ->where('descripcion', 'not like', '%:%')->count();
$ajustesSinMotivo === 0 ? $ok('Ajustes con motivo en la descripción') : $bad("{$ajustesSinMotivo} ajustes sin motivo");

echo "\n════ 4. OPERACIONES → ASIENTO (nada se quedó sin registrar) ════\n";
$ventasSinMov = DB::table('venta_pagos')->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
    ->where('ventas.estado', 'completada')->whereRaw('venta_pagos.monto - venta_pagos.vuelto > 0.009')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cuenta_movimientos')
        ->where('ref_tipo', 'venta')->whereColumn('ref_id', 'ventas.id'))->count();
$ventasSinMov === 0 ? $ok('Toda venta completada con pago tiene asiento') : $bad("{$ventasSinMov} pagos de venta sin asiento");
foreach ([['venta_abonos', 'venta_abono', null], ['gastos', 'gasto', null], ['deuda_pagos', 'deuda_pago', null]] as [$tabla, $ref]) {
    $sin = DB::table($tabla)->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cuenta_movimientos')
        ->where('ref_tipo', $ref)->whereColumn('ref_id', "{$tabla}.id"))->count();
    $sin === 0 ? $ok("Toda fila de {$tabla} tiene asiento") : $bad("{$sin} filas de {$tabla} SIN asiento");
}
$epSin = DB::table('entrada_pagos')->whereNull('proveedor_adelanto_id')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cuenta_movimientos')
        ->where('ref_tipo', 'entrada_pago')->whereColumn('ref_id', 'entrada_pagos.id'))->count();
$epSin === 0 ? $ok('Todo pago a proveedor (en dinero) tiene asiento') : $bad("{$epSin} entrada_pagos sin asiento");
$antSin = DB::table('cliente_anticipos')->where('estado', '!=', 'anulado')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cuenta_movimientos')
        ->where('ref_tipo', 'cliente_anticipo')->whereColumn('ref_id', 'cliente_anticipos.id'))->count();
$antSin === 0 ? $ok('Todo anticipo tiene su ingreso') : $bad("{$antSin} anticipos sin ingreso");
$adeSin = DB::table('proveedor_adelantos')->where('estado', '!=', 'anulado')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cuenta_movimientos')
        ->where('ref_tipo', 'proveedor_adelanto')->whereColumn('ref_id', 'proveedor_adelantos.id'))->count();
$adeSin === 0 ? $ok('Todo adelanto tiene su egreso') : $bad("{$adeSin} adelantos sin egreso");

echo "\n════ 5. CADENAS INTERNAS (original − pagos = saldo) ════\n";
foreach (DB::table('deudas')->where('empresa_id', $EMPRESA)->where('estado', '!=', 'anulada')->get() as $d) {
    $mov = (float) DB::table('deuda_pagos')->where('deuda_id', $d->id)
        ->selectRaw("COALESCE(SUM(CASE WHEN tipo='amortizacion' THEN monto ELSE -monto END),0) s")->value('s');
    abs(($d->monto_original - $mov) - $d->saldo) <= 0.01
        ? $ok("Deuda '{$d->nombre}': {$d->monto_original} − pagos = {$d->saldo}")
        : $bad("Deuda '{$d->nombre}' descuadra: orig {$d->monto_original} − pagos {$mov} ≠ saldo {$d->saldo}");
}
foreach (DB::table('ventas')->where('empresa_id', $EMPRESA)->where('es_credito', true)->where('estado', 'completada')->get() as $v) {
    $inicial = (float) DB::table('venta_pagos')->where('venta_id', $v->id)->selectRaw('COALESCE(SUM(monto-vuelto),0) s')->value('s');
    $abonos  = (float) DB::table('venta_abonos')->where('venta_id', $v->id)->sum('monto');
    $okPagado = abs(($inicial + $abonos) - (float) $v->monto_pagado) <= 0.01;
    $okSaldo  = abs(((float) $v->total - (float) $v->monto_pagado) - (float) $v->saldo_pendiente) <= 0.01;
    ($okPagado && $okSaldo)
        ? $ok("Venta {$v->numero}: inicial {$inicial} + abonos {$abonos} = pagado {$v->monto_pagado}; saldo {$v->saldo_pendiente}")
        : $bad("Venta {$v->numero} descuadra (pagado={$v->monto_pagado}, inicial+abonos=" . ($inicial + $abonos) . ", saldo={$v->saldo_pendiente})");
}
foreach (DB::table('entradas')->where('empresa_id', $EMPRESA)->where('monto_pagado', '>', 0)->get() as $e) {
    $pagos = (float) DB::table('entrada_pagos')->where('entrada_id', $e->id)->sum('monto');
    abs($pagos - (float) $e->monto_pagado) <= 0.01
        ? $ok("Entrada {$e->numero_documento}: Σ pagos {$pagos} = monto_pagado {$e->monto_pagado} [{$e->estado_pago}]")
        : $bad("Entrada {$e->numero_documento}: Σ pagos {$pagos} ≠ monto_pagado {$e->monto_pagado}");
}
foreach (DB::table('cliente_anticipos')->where('empresa_id', $EMPRESA)->where('estado', '!=', 'anulado')->get() as $a) {
    $aplicado = (float) DB::table('cliente_anticipo_aplicaciones')->where('cliente_anticipo_id', $a->id)->sum('monto');
    abs(((float) $a->monto - $aplicado) - (float) $a->saldo) <= 0.01
        ? $ok("Anticipo #{$a->id}: {$a->monto} − aplicado {$aplicado} = saldo {$a->saldo}")
        : $bad("Anticipo #{$a->id} descuadra: monto {$a->monto} − aplicado {$aplicado} ≠ saldo {$a->saldo}");
}
foreach (DB::table('proveedor_adelantos')->where('empresa_id', $EMPRESA)->where('estado', '!=', 'anulado')->get() as $a) {
    $aplicado = (float) DB::table('proveedor_adelanto_aplicaciones')->where('proveedor_adelanto_id', $a->id)->sum('monto');
    abs(((float) $a->monto - $aplicado) - (float) $a->saldo) <= 0.01
        ? $ok("Adelanto #{$a->id}: {$a->monto} − aplicado {$aplicado} = saldo {$a->saldo}")
        : $bad("Adelanto #{$a->id} descuadra");
}

echo "\n════ 6. SALDOS POR CUENTA == BRUTO − EMITIDOS ════\n";
foreach (DB::table('cuentas')->where('empresa_id', $EMPRESA)->where('activo', true)->get() as $c) {
    $ing = (float) DB::table('cuenta_movimientos')->where('cuenta_id', $c->id)->where('fecha', '<=', $FECHA)->where('tipo', 'ingreso')->sum('monto');
    $egr = (float) DB::table('cuenta_movimientos')->where('cuenta_id', $c->id)->where('fecha', '<=', $FECHA)->where('tipo', 'egreso')->sum('monto');
    $neto = app(App\Services\TesoreriaService::class)->saldo($c->id, $FECHA);
    $check("Cuenta {$c->nombre}: bruto {$ing} − emitidos {$egr} == saldo", $neto, round($ing - $egr, 2));
    if ($neto < 0) echo "    ⚠ ADVERTENCIA: '{$c->nombre}' en NEGATIVO (S/ " . number_format($neto, 2) . ")\n";
}

echo "\n════ 7. INMUTABILIDAD DEL CONFIRMADO ════\n";
$conf = App\Models\BalanceDiario::where('empresa_id', $EMPRESA)->where('estado', 'confirmado')->orderByDesc('fecha')->first();
if ($conf) {
    $sumF = (float) $conf->items()->where('seccion', 'favor')->sum('monto');
    $sumC = (float) $conf->items()->where('seccion', 'contra')->sum('monto');
    $check("Confirmado {$conf->fecha->toDateString()}: items favor == snapshot", $sumF, (float) $conf->total_favor);
    $check("Confirmado {$conf->fecha->toDateString()}: items contra == snapshot", $sumC, (float) $conf->total_contra);
}

echo "\n════ RESULTADO ════\n";
echo empty($fallas)
    ? "AUDITORÍA LIMPIA: 0 fallas. Toda cifra del balance tiene fuente verificable.\n"
    : count($fallas) . " FALLAS:\n- " . implode("\n- ", $fallas) . "\n";
