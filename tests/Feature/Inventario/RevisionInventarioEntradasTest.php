<?php

use App\Models\Almacen;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\Stock;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Services\KardexService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión de inventario (oct 2026) — entradas (compras).
 *
 *  - Mercadería en tránsito comprada antes del inventario inicial pero recibida
 *    después no entraba nunca al stock (se miraba la fecha de compra).
 *  - Cambiar el almacén de una entrada confirmada dejaba el anterior en negativo.
 *  - Confirmar dos veces (doble clic) daba 500 y sin cerrojo sumaba dos veces.
 *  - La fecha de recepción aceptaba fechas futuras y anteriores a la compra.
 *  - Anular / volver a pendiente una compra pagada por compensación dejaba viva
 *    la otra mitad (la venta seguía "cobrada").
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['usa_mercaderia_transito' => true]);
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_costo' => 6, 'stock_inicial' => 0]);
    $this->alm = $this->env->almacen->id;
});

function rivEnStock(int $almacenId, int $productoId): float
{
    return (float) (Stock::where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('cantidad') ?? 0);
}

function rivEnEntrada($test, string $estado, float $cantidad, string $fecha, ?int $almacenId = null): Entrada
{
    $e = Entrada::create([
        'empresa_id'   => $test->env->empresa->id,
        'almacen_id'   => $almacenId ?? $test->alm,
        'user_id'      => $test->env->admin->id,
        'proveedor'    => 'Proveedor Revisión',
        'tipo'         => 'compra',
        'fecha'        => $fecha,
        'estado'       => 'borrador',
        'total'        => $cantidad * 6,
        'monto_pagado' => 0,
        'estado_pago'  => 'pendiente',
    ]);
    $e->detalles()->create([
        'producto_id' => $test->producto->id, 'unidad_medida_id' => $test->env->unidad->id,
        'cantidad' => $cantidad, 'factor_conversion' => 1, 'cantidad_base' => $cantidad,
        'precio_costo' => 6, 'subtotal' => $cantidad * 6,
    ]);
    if ($estado === 'confirmado') $e->confirmar();
    if ($estado === 'en_transito') $e->marcarEnTransito();

    return $e->fresh();
}

it('la mercadería en tránsito recibida después del inventario inicial sí entra al stock', function () {
    $corte = now()->subDays(5)->toDateString();
    DB::table('stock_iniciales')->insert([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->alm, 'producto_id' => $this->producto->id,
        'fecha' => $corte, 'cantidad' => 5, 'costo' => 6, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(KardexService::class)->reconstruirPar($this->alm, $this->producto->id);
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(5.0);

    // Comprada ANTES del corte (no estaba en el conteo: venía en camino)…
    $e = rivEnEntrada($this, 'en_transito', 10, now()->subDays(8)->toDateString());

    // …y recibida DESPUÉS del corte.
    $this->post(route('inventario.entradas.recibir', $e), ['fecha_recepcion' => now()->subDays(2)->toDateString()])
        ->assertSessionHasNoErrors();

    expect(rivEnStock($this->alm, $this->producto->id))->toBe(15.0);

    // Y sobrevive al "Recalcular" (motor único).
    app(KardexService::class)->reconstruirPar($this->alm, $this->producto->id);
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(15.0);
    expect(DB::table('movimientos_inventario')->where('producto_id', $this->producto->id)->where('tipo', 'entrada')
        ->value('fecha'))->toStartWith(now()->subDays(2)->toDateString());
});

it('la fecha de recepción no puede ser futura ni anterior a la compra', function () {
    $e = rivEnEntrada($this, 'en_transito', 10, now()->subDays(3)->toDateString());

    $this->post(route('inventario.entradas.recibir', $e), ['fecha_recepcion' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors('fecha_recepcion');
    $this->post(route('inventario.entradas.recibir', $e), ['fecha_recepcion' => now()->subDays(4)->toDateString()])
        ->assertSessionHasErrors('fecha_recepcion');

    expect($e->fresh()->estado)->toBe('en_transito');
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(0.0);
});

it('cambiar el almacén de una entrada confirmada valida el stock del almacén anterior', function () {
    $otro = Almacen::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id,
        'nombre' => 'Depósito 2', 'tipo' => 'local', 'activo' => true,
    ]);
    $e = rivEnEntrada($this, 'confirmado', 10, now()->toDateString());
    // Se vendieron/sacaron 6 de esas 10.
    Stock::ajustar($this->alm, $this->producto->id, -6);
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(4.0);

    $this->put(route('inventario.entradas.update', $e), [
        'almacen_id' => $otro->id, 'tipo' => 'compra', 'fecha' => now()->toDateString(),
        'detalles'   => [['producto_id' => $this->producto->id, 'unidad_medida_id' => $this->env->unidad->id,
            'cantidad' => 10, 'factor_conversion' => 1, 'precio_costo' => 6]],
    ])->assertSessionHasErrors('detalles');

    // Nada se movió: el almacén original NO quedó en −6.
    expect($e->fresh()->almacen_id)->toBe($this->alm);
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(4.0);
    expect(rivEnStock($otro->id, $this->producto->id))->toBe(0.0);
});

it('confirmar dos veces no suma dos veces y avisa con un error claro', function () {
    $e = rivEnEntrada($this, 'borrador', 10, now()->toDateString());

    $pestanaA = Entrada::find($e->id);
    $pestanaB = Entrada::find($e->id);
    $pestanaA->confirmar();
    expect(fn () => $pestanaB->confirmar())->toThrow(LogicException::class, 'Esta entrada ya fue confirmada.');
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(10.0);

    $this->post(route('inventario.entradas.confirmar', $e))
        ->assertSessionHasErrors(['estado' => 'Esta entrada ya fue confirmada.']);
    expect(rivEnStock($this->alm, $this->producto->id))->toBe(10.0);
});

it('anular o volver a pendiente una compra compensada revierte también el abono de la venta', function () {
    $turno = $this->env->abrirTurno();
    $crearVenta = fn () => Venta::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'turno_id' => $turno->id,
        'caja_id' => $this->env->caja->id, 'user_id' => $this->env->admin->id, 'cliente_id' => $this->env->clienteGeneral->id,
        'numero' => 'V-R' . random_int(1000, 9999), 'tipo_comprobante' => 'ticket',
        'subtotal' => 100, 'descuento_total' => 0, 'igv' => 0, 'total' => 100, 'estado' => 'completada',
        'es_credito' => true, 'monto_pagado' => 0, 'saldo_pendiente' => 100, 'fecha_venta' => now(),
    ]);
    $compensar = function (Venta $venta, Entrada $entrada) {
        $this->post(route('finanzas.compensaciones.cxc-cxp'), [
            'venta_id' => $venta->id, 'entrada_id' => $entrada->id, 'monto' => 60, 'fecha' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        expect((float) $venta->fresh()->saldo_pendiente)->toBe(40.0);
    };

    // 1) Anular la compra.
    $venta   = $crearVenta();
    $entrada = rivEnEntrada($this, 'confirmado', 10, now()->toDateString());
    $compensar($venta, $entrada);

    $this->post(route('inventario.entradas.anular', $entrada), ['motivo' => 'Compra duplicada'])->assertSessionHasNoErrors();

    expect($entrada->fresh()->estado)->toBe('anulada');
    expect(EntradaPago::where('entrada_id', $entrada->id)->count())->toBe(0);
    expect(VentaAbono::where('venta_id', $venta->id)->count())->toBe(0);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);

    // 2) Volver la compra a "pendiente" desde el listado.
    $venta2   = $crearVenta();
    $entrada2 = rivEnEntrada($this, 'confirmado', 10, now()->toDateString());
    $compensar($venta2, $entrada2);

    $this->post(route('inventario.entradas.pago', $entrada2), ['estado_pago' => 'pendiente'])->assertSessionHasNoErrors();

    expect(EntradaPago::where('entrada_id', $entrada2->id)->count())->toBe(0);
    expect(VentaAbono::where('venta_id', $venta2->id)->count())->toBe(0);
    expect((float) $venta2->fresh()->saldo_pendiente)->toBe(100.0);
});

it('DIN-3 — dos "Pagado" simultáneos del pago rápido no pagan dos veces la compra', function () {
    $entrada = rivEnEntrada($this, 'confirmado', 50, now()->toDateString());   // S/ 300 pendiente
    $stale   = Entrada::find($entrada->id);   // la 2.ª petición cargó la compra antes de que la 1.ª pagara
    $pago    = ['estado_pago' => 'pagado', 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
                'cuenta_id' => \App\Services\TesoreriaService::efectivo($this->env->empresa->id)->id];

    $this->post(route('inventario.entradas.pago', $entrada), $pago)->assertSessionHasNoErrors();

    $req = \Illuminate\Http\Request::create('/', 'POST', $pago);
    $req->setUserResolver(fn () => $this->env->admin);
    app()->instance('request', $req);
    app(\App\Http\Controllers\Inventario\EntradaController::class)->actualizarPago($req, $stale);

    expect((float) EntradaPago::where('entrada_id', $entrada->id)->sum('monto'))->toBe(300.0)
        ->and((float) DB::table('cuenta_movimientos')->where('ref_tipo', 'entrada_pago')
            ->whereIn('ref_id', EntradaPago::where('entrada_id', $entrada->id)->pluck('id'))->sum('monto'))->toBe(300.0)
        ->and((float) $entrada->fresh()->monto_pagado)->toBe(300.0);
});
