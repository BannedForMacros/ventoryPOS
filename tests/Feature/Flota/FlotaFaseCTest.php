<?php

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\CuentaMovimiento;
use App\Models\FlotaLiquidacion;
use App\Models\FlotaTarifa;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use App\Models\RutaEntrega;
use App\Models\Vehiculo;
use App\Models\VehiculoCapacidad;
use App\Models\Viaje;
use App\Services\FlotaService;
use App\Services\PagoChoferesService;
use App\Services\ViajeService;
use Tests\Support\TestEnv;

/**
 * Flota, Fase C: pago a choferes y ayudantes (destajo) como gastos por placa,
 * carga por volumen y precio por zona.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->env->empresa->update(['usa_flota' => true, 'usa_entregas' => true, 'entrega_config' => ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]]);
    $e = $this->env->empresa->id;

    $this->tipo = GastoTipo::create(['empresa_id' => $e, 'nombre' => 'Personal', 'categoria' => 'operativo', 'activo' => true]);
    $this->conceptoPago = GastoConcepto::create(['empresa_id' => $e, 'gasto_tipo_id' => $this->tipo->id, 'nombre' => 'Pago choferes', 'activo' => true]);

    $this->chofer = Conductor::create(['empresa_id' => $e, 'tipo' => 'chofer', 'nombres' => 'Jonathan', 'numero_documento' => '44556677', 'licencia' => 'Q1']);
    $this->ayudante = Conductor::create(['empresa_id' => $e, 'tipo' => 'ayudante', 'nombres' => 'Lucas']);
    $this->camionA = Vehiculo::create(['empresa_id' => $e, 'placa' => 'M9C-822', 'capacidad_kg' => 13000, 'estado' => 'disponible', 'conductor_id' => $this->chofer->id]);
    $this->camionB = Vehiculo::create(['empresa_id' => $e, 'placa' => 'M8B-893', 'capacidad_kg' => 13000, 'estado' => 'disponible']);

    $this->cliente = Cliente::create(['empresa_id' => $e, 'nombres' => 'Obra', 'tipo_documento' => 'DNI', 'numero_documento' => '16789011', 'activo' => true]);
    $this->ladrillo = $this->env->crearProducto(['nombre' => 'King Kong', 'precio_venta' => 10, 'stock_inicial' => 20000]);
    $this->ladrillo->update(['peso_kg' => 2.8]);
    $this->techo = $this->env->crearProducto(['nombre' => 'Techo 12', 'precio_venta' => 10, 'stock_inicial' => 5000]);
    $this->techo->update(['peso_kg' => 6.5]);

    // JH: S/ 12.50 por millar al chofer y a cada ayudante; techo S/ 70 por millar.
    FlotaTarifa::create(['empresa_id' => $e, 'producto_id' => null, 'por_cantidad' => 1000, 'monto_chofer' => 12.5, 'monto_ayudante' => 12.5]);
    FlotaTarifa::create(['empresa_id' => $e, 'producto_id' => $this->techo->id, 'por_cantidad' => 1000, 'monto_chofer' => 70, 'monto_ayudante' => 70]);
    $this->env->empresa->update(['flota_config' => ['vuelta_chofer' => 20, 'vuelta_ayudante' => 0]]);

    $this->desde = now()->startOfWeek()->toDateString();
    $this->hasta = now()->endOfWeek()->toDateString();
});

/** Venta rápida del POS con placa: el viaje del día lleva la venta ya entregada. */
function fcVenta($test, Vehiculo $camion, $producto, float $cantidad): void
{
    $test->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket', 'cliente_id' => $test->cliente->id, 'tipo_entrega' => 'envio', 'envio_entregado' => true,
        'cliente_direccion' => 'Obra', 'vehiculo_id' => $camion->id,
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => $cantidad, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $cantidad * 10]],
    ])->assertSessionHasNoErrors();
}

function fcPagar($test, Conductor $c, array $extra = []): \Illuminate\Testing\TestResponse
{
    return $test->post(route('flota.pagos.pagar'), array_merge([
        'conductor_id' => $c->id, 'desde' => $test->desde, 'hasta' => $test->hasta, 'fecha' => now()->toDateString(),
        'gasto_tipo_id' => $test->tipo->id, 'gasto_concepto_id' => $test->conceptoPago->id,
        'metodo_pago_id' => $test->env->metodo('efectivo')->id, 'turno_id' => $test->turno->id,
    ], $extra));
}

it('calcula lo que toca a chofer y ayudante por lo entregado y por vuelta', function () {
    fcVenta($this, $this->camionA, $this->ladrillo, 4000);   // 4 millares × 12.50 = 50
    fcVenta($this, $this->camionA, $this->techo, 500);       // 0.5 millar × 70   = 35
    Viaje::query()->update(['conductor_id' => $this->chofer->id]);
    Viaje::first()->ayudantes()->sync([$this->ayudante->id]);

    // Vuelta de fábrica que salió: S/ 20 al chofer, nada al ayudante.
    $vuelta = Viaje::create(['empresa_id' => $this->env->empresa->id, 'numero' => 'VJ-0099', 'tipo' => 'abastecimiento', 'fecha' => now()->toDateString(),
        'vehiculo_id' => $this->camionB->id, 'conductor_id' => $this->chofer->id, 'estado' => 'cerrado']);
    $vuelta->ayudantes()->sync([$this->ayudante->id]);

    $r = collect(app(PagoChoferesService::class)->calcular($this->env->empresa->id, $this->desde, $this->hasta))->keyBy('conductor_id');

    expect($r[$this->chofer->id]['monto'])->toEqual(105)          // 50 + 35 + 20
        ->and($r[$this->chofer->id]['vueltas'])->toBe(1)
        ->and($r[$this->chofer->id]['unidades'])->toEqual(4500)
        ->and($r[$this->ayudante->id]['monto'])->toEqual(85);     // 50 + 35, la vuelta no
});

it('pagar registra gastos por placa, con su egreso, y no se vuelve a pagar', function () {
    fcVenta($this, $this->camionA, $this->ladrillo, 4000);     // 50 en A
    fcVenta($this, $this->camionB, $this->ladrillo, 4000);     // 50 en B
    Viaje::query()->update(['conductor_id' => $this->chofer->id]);

    fcPagar($this, $this->chofer, ['ajuste' => -20, 'ajuste_motivo' => 'Adelanto del martes'])->assertSessionHasNoErrors();

    $liq = FlotaLiquidacion::firstOrFail();
    $gastos = Gasto::where('flota_liquidacion_id', $liq->id)->get();
    expect((float) $liq->monto_calculado)->toBe(100.0)
        ->and((float) $liq->total)->toBe(80.0)
        ->and($gastos)->toHaveCount(2)
        ->and($gastos->sum('monto'))->toEqual(80)
        ->and($gastos->pluck('vehiculo_id')->sort()->values()->all())->toBe(collect([$this->camionA->id, $this->camionB->id])->sort()->values()->all())
        ->and(CuentaMovimiento::where('ref_tipo', 'gasto')->whereIn('ref_id', $gastos->pluck('id'))->sum('monto'))->toEqual(80);

    // Ya no queda nada por pagar a esa persona.
    expect(app(PagoChoferesService::class)->calcular($this->env->empresa->id, $this->desde, $this->hasta))->toBe([]);
    fcPagar($this, $this->chofer)->assertSessionHasErrors('conductor_id');

    // La utilidad por placa descuenta el pago al personal.
    $u = collect(app(FlotaService::class)->utilidad($this->env->empresa->id, $this->desde, now()->toDateString()))->keyBy('placa');
    expect($u['M9C-822']['personal'])->toEqual(40)->and($u['M9C-822']['otros'])->toEqual(0);
});

it('un ajuste exige motivo y el total no puede quedar en cero', function () {
    fcVenta($this, $this->camionA, $this->ladrillo, 1000);     // 12.50
    Viaje::query()->update(['conductor_id' => $this->chofer->id]);

    fcPagar($this, $this->chofer, ['ajuste' => -5])->assertSessionHasErrors('ajuste_motivo');
    fcPagar($this, $this->chofer, ['ajuste' => -12.5, 'ajuste_motivo' => 'Adelanto'])->assertSessionHasErrors('ajuste');
    expect(FlotaLiquidacion::where('empresa_id', $this->env->empresa->id)->count())->toBe(0)
        ->and(Gasto::where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('anular la liquidación elimina sus gastos y libera los viajes', function () {
    fcVenta($this, $this->camionA, $this->ladrillo, 2000);
    Viaje::query()->update(['conductor_id' => $this->chofer->id]);
    fcPagar($this, $this->chofer)->assertSessionHasNoErrors();
    $liq = FlotaLiquidacion::firstOrFail();

    $this->post(route('flota.pagos.anular', $liq->id), ['motivo' => 'Se pagó mal'])->assertSessionHasNoErrors();

    expect($liq->fresh()->estado)->toBe('anulada')
        ->and(Gasto::where('flota_liquidacion_id', $liq->id)->count())->toBe(0)           // eliminados (borrado suave)
        ->and(Gasto::withTrashed()->where('flota_liquidacion_id', $liq->id)->count())->toBe(1)
        ->and(collect(app(PagoChoferesService::class)->calcular($this->env->empresa->id, $this->desde, $this->hasta))->first()['monto'])->toEqual(25);
});

it('el pago no se registra con un concepto de combustible o mantenimiento', function () {
    $this->conceptoPago->update(['flota_uso' => 'combustible']);
    fcVenta($this, $this->camionA, $this->ladrillo, 1000);
    Viaje::query()->update(['conductor_id' => $this->chofer->id]);

    fcPagar($this, $this->chofer)->assertSessionHasErrors('gasto_concepto_id');
});

it('tarifas: se guardan la general, por producto y la vuelta', function () {
    $this->put(route('flota.pagos.tarifas'), [
        'general' => ['por_cantidad' => 1000, 'monto_chofer' => 15, 'monto_ayudante' => 10],
        'productos' => [['producto_id' => $this->techo->id, 'por_cantidad' => 1000, 'monto_chofer' => 80, 'monto_ayudante' => 80]],
        'vuelta_chofer' => 25, 'vuelta_ayudante' => 5,
    ])->assertSessionHasNoErrors();

    expect(FlotaTarifa::where('empresa_id', $this->env->empresa->id)->count())->toBe(2)
        ->and((float) FlotaTarifa::whereNull('producto_id')->where('empresa_id', $this->env->empresa->id)->value('monto_ayudante'))->toBe(10.0)
        ->and(PagoChoferesService::config($this->env->empresa->fresh()))->toBe(['vuelta_chofer' => 25.0, 'vuelta_ayudante' => 5.0]);
});

it('carga por volumen: manda el menor entre peso y volumen, y suma fracciones', function () {
    // Techo en camión de 13 t: por peso 2,000 und; por volumen solo 1,500.
    VehiculoCapacidad::create(['vehiculo_id' => $this->camionA->id, 'producto_id' => $this->techo->id, 'max_unidades' => 1500]);
    $maximos = [$this->techo->id => 1500.0];

    $soloTecho = ViajeService::usoDeCarga([['producto_id' => $this->techo->id, 'unidades' => 750, 'peso_kg' => 750 * 6.5]], 13000, $maximos);
    expect($soloTecho['porcentaje'])->toEqual(50)->and($soloTecho['por_volumen'])->toBeTrue();

    // Mixto: 750 techo (50 %) + 2,321.43 King Kong por peso (50 %).
    $mixto = ViajeService::usoDeCarga([
        ['producto_id' => $this->techo->id, 'unidades' => 750, 'peso_kg' => 750 * 6.5],
        ['producto_id' => $this->ladrillo->id, 'unidades' => 2321.43, 'peso_kg' => 6500.0],
    ], 13000, $maximos);
    expect($mixto['porcentaje'])->toEqual(100);

    // Sin límite por volumen, igual que antes: el peso.
    expect(ViajeService::usoDeCarga([['producto_id' => $this->ladrillo->id, 'unidades' => 1000, 'peso_kg' => 2800.0]], 13000, [])['porcentaje'])->toEqual(21.5);
});

it('precio por zona: la ruta guarda su margen y el POS lo recibe', function () {
    $this->put(route('configuracion.entregas.update'), [
        'activo' => true, 'ruta_obligatoria' => false, 'fecha_obligatoria' => false, 'envio_sale_al_entregar' => false, 'aviso_monto' => null,
    ])->assertSessionHasNoErrors();
    $this->post(route('configuracion.entregas.rutas.store'), ['nombre' => 'Lejana', 'zona' => 'Tumán', 'margen_monto' => 90, 'margen_por' => 1000])->assertSessionHasNoErrors();

    $ruta = RutaEntrega::where('empresa_id', $this->env->empresa->id)->where('nombre', 'Lejana')->firstOrFail();
    expect((float) $ruta->margen_monto)->toBe(90.0)->and((float) $ruta->margen_por)->toBe(1000.0);

    $this->withoutVite()->get(route('pos.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('entregas.rutas.0.margen_monto', '90.00'));
});

it('las pantallas de la fase C abren', function () {
    $this->withoutVite();
    fcVenta($this, $this->camionA, $this->ladrillo, 1000);
    Viaje::query()->update(['conductor_id' => $this->chofer->id]);
    fcPagar($this, $this->chofer)->assertSessionHasNoErrors();

    $this->get(route('flota.pagos.index'))->assertOk();
    $this->get(route('flota.pagos.index', ['periodo' => 'semana']))->assertOk()->assertInertia(fn ($p) => $p->has('liquidaciones', 1));
    $this->put(route('flota.vehiculos.capacidades', $this->camionA->id), ['capacidades' => [['producto_id' => $this->techo->id, 'max_unidades' => 1500]]])->assertSessionHasNoErrors();
    $this->get(route('flota.vehiculos.show', $this->camionA->id))->assertOk()->assertInertia(fn ($p) => $p->has('capacidades', 1));
    $this->get(route('viajes.create'))->assertOk();
});
