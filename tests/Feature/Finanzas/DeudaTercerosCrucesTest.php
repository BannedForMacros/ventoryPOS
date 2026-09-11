<?php

use App\Models\ClienteAnticipo;
use App\Models\Deuda;
use App\Models\DeudaPago;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\Venta;
use App\Models\VentaAbono;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Deudas vinculadas a un tercero (opcional) + cruces:
 *  - deuda por cobrar ← anticipo del cliente (sin caja)
 *  - deuda por pagar ↔ venta CxC del cliente (compensación, sin caja)
 *  - deuda por cobrar ↔ compra CxP del proveedor (compensación, sin caja)
 * Anular cualquier lado revierte al hermano. Las deudas SIN vínculo no cambian.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function crearDeudaVinculada($test, string $direccion, float $monto, array $extra = []): Deuda
{
    return Deuda::create(array_merge([
        'empresa_id'     => $test->env->empresa->id,
        'user_id'        => $test->env->admin->id,
        'direccion'      => $direccion,
        'tipo'           => 'personal',
        'nombre'         => "Préstamo {$direccion} " . uniqid(),
        'monto_original' => $monto,
        'saldo'          => $monto,
        'fecha_inicio'   => now()->toDateString(),
        'estado'         => 'activa',
    ], $extra));
}

function ventaCreditoDe($test, float $total): Venta
{
    return Venta::create([
        'empresa_id' => $test->env->empresa->id, 'local_id' => $test->env->local->id,
        'turno_id' => $test->turno->id, 'caja_id' => $test->env->caja->id,
        'user_id' => $test->env->admin->id, 'cliente_id' => $test->env->clienteGeneral->id,
        'numero' => 'V-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'tipo_comprobante' => 'ticket', 'subtotal' => $total, 'descuento_total' => 0,
        'igv' => 0, 'total' => $total, 'estado' => 'completada', 'es_credito' => true,
        'monto_pagado' => 0, 'saldo_pendiente' => $total, 'fecha_venta' => now(),
    ]);
}

it('crea una deuda vinculada a un cliente sin afectar deudas existentes', function () {
    $deudaVieja = crearDeudaVinculada($this, 'por_pagar', 500); // sin vínculo (legacy)

    $this->post(route('finanzas.deudas.store'), [
        'direccion' => 'por_cobrar', 'tipo' => 'personal',
        'nombre' => 'Préstamo a cliente general',
        'cliente_id' => $this->env->clienteGeneral->id,
        'monto_original' => 300, 'fecha_inicio' => now()->toDateString(),
        'registrar_caja' => false, 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $deuda = Deuda::where('nombre', 'Préstamo a cliente general')->firstOrFail();
    expect($deuda->cliente_id)->toBe($this->env->clienteGeneral->id);
    // La deuda vieja sigue intacta y sin vínculo.
    expect($deudaVieja->fresh()->cliente_id)->toBeNull();
    expect((float) $deudaVieja->fresh()->saldo)->toBe(500.0);
});

it('cobra una deuda por cobrar del anticipo del cliente vinculado, sin tesorería', function () {
    $deuda    = crearDeudaVinculada($this, 'por_cobrar', 200, ['cliente_id' => $this->env->clienteGeneral->id]);
    $anticipo = ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $this->env->clienteGeneral->id,
        'user_id' => $this->env->admin->id, 'fecha' => now()->toDateString(),
        'monto' => 150, 'saldo' => 150, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ]);

    $this->post(route('finanzas.deudas.pago', $deuda->id), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 150,
        'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasNoErrors();

    expect((float) $deuda->fresh()->saldo)->toBe(50.0);
    expect((float) $anticipo->fresh()->saldo)->toBe(0.0);

    $pago = DeudaPago::where('deuda_id', $deuda->id)->firstOrFail();
    expect($pago->cliente_anticipo_id)->toBe($anticipo->id);
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'deuda_pago')->where('ref_id', $pago->id)->count())->toBe(0);

    // Anular devuelve el saldo al anticipo.
    $this->delete(route('finanzas.deudas.pagos.destroy', $pago->id), ['motivo' => 'Registrado por error'])
        ->assertSessionHasNoErrors();
    expect((float) $deuda->fresh()->saldo)->toBe(200.0);
    expect((float) $anticipo->fresh()->saldo)->toBe(150.0);
    expect($anticipo->fresh()->estado)->toBe('activo');
});

it('compensa una deuda por pagar contra una venta CxC del cliente vinculado', function () {
    $deuda = crearDeudaVinculada($this, 'por_pagar', 400, ['cliente_id' => $this->env->clienteGeneral->id]);
    $venta = ventaCreditoDe($this, 250);

    $this->post(route('finanzas.deudas.pago', $deuda->id), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 250,
        'compensar_venta_id' => $venta->id,
    ])->assertSessionHasNoErrors();

    expect((float) $deuda->fresh()->saldo)->toBe(150.0);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(0.0);

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    expect($abono->compensacion_deuda_id)->toBe($deuda->id);
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->count())->toBe(0);

    // Anular el ABONO de la venta revierte también la deuda.
    $this->delete(route('finanzas.cxc.abonos.destroy', $abono->id), ['motivo' => 'Compensación errónea'])
        ->assertSessionHasNoErrors();
    expect((float) $deuda->fresh()->saldo)->toBe(400.0);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(250.0);
    expect(DeudaPago::where('deuda_id', $deuda->id)->count())->toBe(0);
});

it('compensa una deuda por cobrar contra una compra CxP del proveedor vinculado', function () {
    $proveedor = \App\Models\Proveedor::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'RUC',
        'numero_documento' => '20999999991', 'razon_social' => 'Proveedor Deuda SAC', 'activo' => true,
    ]);
    $deuda   = crearDeudaVinculada($this, 'por_cobrar', 300, ['proveedor_id' => $proveedor->id]);
    $entrada = Entrada::create([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->env->almacen->id,
        'user_id' => $this->env->admin->id, 'proveedor_id' => $proveedor->id,
        'proveedor' => 'Proveedor Deuda SAC', 'tipo' => 'compra',
        'fecha' => now()->toDateString(), 'estado' => 'confirmado',
        'total' => 180, 'monto_pagado' => 0, 'estado_pago' => 'pendiente',
    ]);

    $this->post(route('finanzas.deudas.pago', $deuda->id), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 180,
        'compensar_entrada_id' => $entrada->id,
    ])->assertSessionHasNoErrors();

    expect((float) $deuda->fresh()->saldo)->toBe(120.0);
    expect($entrada->fresh()->estado_pago)->toBe('pagado');

    $pagoEntrada = EntradaPago::where('entrada_id', $entrada->id)->firstOrFail();
    expect($pagoEntrada->compensacion_deuda_id)->toBe($deuda->id);

    // Anular el movimiento de la DEUDA revierte también la compra.
    $pagoDeuda = DeudaPago::where('deuda_id', $deuda->id)->firstOrFail();
    $this->delete(route('finanzas.deudas.pagos.destroy', $pagoDeuda->id), ['motivo' => 'Compensación errónea'])
        ->assertSessionHasNoErrors();
    expect((float) $deuda->fresh()->saldo)->toBe(300.0);
    expect((float) $entrada->fresh()->monto_pagado)->toBe(0.0);
    expect(EntradaPago::where('entrada_id', $entrada->id)->count())->toBe(0);
});

it('rechaza el cruce si la deuda no está vinculada al tercero correcto', function () {
    $deudaSinVinculo = crearDeudaVinculada($this, 'por_pagar', 400); // sin cliente
    $venta = ventaCreditoDe($this, 250);

    $this->post(route('finanzas.deudas.pago', $deudaSinVinculo->id), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 100,
        'compensar_venta_id' => $venta->id,
    ])->assertStatus(422);

    expect((float) $deudaSinVinculo->fresh()->saldo)->toBe(400.0);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(250.0);
});

it('un movimiento cruzado no se puede editar', function () {
    $deuda = crearDeudaVinculada($this, 'por_pagar', 400, ['cliente_id' => $this->env->clienteGeneral->id]);
    $venta = ventaCreditoDe($this, 250);

    $this->post(route('finanzas.deudas.pago', $deuda->id), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 100,
        'compensar_venta_id' => $venta->id,
    ])->assertSessionHasNoErrors();

    $pago = DeudaPago::where('deuda_id', $deuda->id)->firstOrFail();
    $this->put(route('finanzas.deudas.pagos.update', $pago->id), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 150,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertStatus(422);
});
