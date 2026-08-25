<?php

use App\Models\Cliente;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\PlanillaDescuento;
use App\Models\Proveedor;
use App\Models\ProveedorAdelanto;
use App\Models\VentaAbono;
use App\Services\TesoreriaService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Flexibilidad de admin en finanzas: editar / eliminar / reactivar con
 * tesorería y saldos siempre consistentes, todo auditado y gobernado por
 * la matriz de permisos (nada hardcodeado).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

// ── Deudas y préstamos ──────────────────────────────────────────────────

function crearDeuda($test, array $extra = []): Deuda
{
    return Deuda::create(array_merge([
        'empresa_id'     => $test->env->empresa->id,
        'user_id'        => $test->env->admin->id,
        'direccion'      => 'por_pagar',
        'tipo'           => 'personal',
        'nombre'         => 'Deuda Test',
        'monto_original' => 1000,
        'saldo'          => 1000,
        'fecha_inicio'   => now()->toDateString(),
        'estado'         => 'activa',
    ], $extra));
}

it('editar una deuda ajusta el saldo por el delta del monto original', function () {
    $deuda = crearDeuda($this);
    // Amortiza 300 → saldo 700.
    $this->post(route('finanzas.deudas.pago', $deuda), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 300,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    // Edita: el monto original real era 1200 → saldo sube a 900.
    $this->put(route('finanzas.deudas.update', $deuda), [
        'tipo' => 'personal', 'nombre' => 'Deuda Corregida',
        'monto_original' => 1200, 'fecha_inicio' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $deuda->refresh();
    expect($deuda->nombre)->toBe('Deuda Corregida');
    expect((float) $deuda->saldo)->toBe(900.0);
});

it('reactivar una deuda anulada la devuelve al balance', function () {
    $deuda = crearDeuda($this);
    $this->post(route('finanzas.deudas.anular', $deuda), ['motivo' => 'Anulada por error'])->assertSessionHasNoErrors();
    expect($deuda->fresh()->estado)->toBe('anulada');

    $this->post(route('finanzas.deudas.reactivar', $deuda), ['motivo' => 'Se anuló por equivocación'])->assertSessionHasNoErrors();
    expect($deuda->fresh()->estado)->toBe('activa');
});

it('eliminar una deuda revierte los movimientos de tesorería de sus pagos', function () {
    $deuda = crearDeuda($this);
    $this->post(route('finanzas.deudas.pago', $deuda), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 200,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
    $pagoIds = $deuda->pagos()->pluck('id');
    expect(CuentaMovimiento::where('ref_tipo', 'deuda_pago')->whereIn('ref_id', $pagoIds)->count())->toBe(1);

    $this->delete(route('finanzas.deudas.destroy', $deuda), ['motivo' => 'Registro duplicado'])->assertSessionHasNoErrors();

    expect(Deuda::find($deuda->id))->toBeNull();
    expect(CuentaMovimiento::where('ref_tipo', 'deuda_pago')->whereIn('ref_id', $pagoIds)->count())->toBe(0);
});

it('eliminar un movimiento de deuda restaura el saldo y revierte tesorería', function () {
    $deuda = crearDeuda($this);
    $this->post(route('finanzas.deudas.pago', $deuda), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 400,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
    expect((float) $deuda->fresh()->saldo)->toBe(600.0);
    $pago = $deuda->pagos()->first();

    $this->delete(route('finanzas.deudas.pagos.destroy', $pago), ['motivo' => 'Cuota mal registrada'])->assertSessionHasNoErrors();

    expect((float) $deuda->fresh()->saldo)->toBe(1000.0);
    expect(CuentaMovimiento::where('ref_tipo', 'deuda_pago')->where('ref_id', $pago->id)->exists())->toBeFalse();
});

// ── Cuentas por cobrar: abonos corregibles ──────────────────────────────

function ventaCreditoConAbono($test): array
{
    $cliente = Cliente::create([
        'empresa_id' => $test->env->empresa->id,
        'nombres' => 'Cliente', 'apellidos' => 'Flexible',
        'tipo_documento' => 'DNI', 'numero_documento' => '77777777', 'activo' => true,
    ]);
    $producto = $test->env->crearProducto(['precio_venta' => 100, 'stock_inicial' => 10]);
    $turno    = $test->env->abrirTurno();

    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'es_credito'       => true,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [],
    ], $test->env->admin, $turno);

    $test->post(route('finanzas.cxc.abonar', $venta), [
        'monto' => 40, 'fecha' => now()->toDateString(),
        'metodo_pago_id' => $test->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    return [$venta->fresh(), VentaAbono::where('venta_id', $venta->id)->first()];
}

it('editar un abono de CxC recalcula la venta y tesorería', function () {
    [$venta, $abono] = ventaCreditoConAbono($this); // abonó 40, saldo 60

    $this->put(route('finanzas.cxc.abonos.update', $abono), [
        'monto' => 70, 'fecha' => now()->subDay()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->monto_pagado)->toBe(70.0);
    expect((float) $venta->saldo_pendiente)->toBe(30.0);

    $movs = CuentaMovimiento::where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->get();
    expect($movs)->toHaveCount(1);
    expect((float) $movs->first()->monto)->toBe(70.0);
});

it('anular un abono de CxC devuelve el saldo a la venta', function () {
    [$venta, $abono] = ventaCreditoConAbono($this);

    $this->delete(route('finanzas.cxc.abonos.destroy', $abono), ['motivo' => 'Abono duplicado'])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->saldo_pendiente)->toBe(100.0);
    expect(VentaAbono::find($abono->id))->toBeNull();
    expect(CuentaMovimiento::where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->exists())->toBeFalse();
});

// ── Planilla: desaplicar y reactivar ────────────────────────────────────

it('un descuento de planilla aplicado se puede desaplicar (vuelve a pendiente)', function () {
    $desc = PlanillaDescuento::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id,
        'registrado_por' => $this->env->admin->id, 'fecha' => now()->toDateString(),
        'monto' => 50, 'motivo' => 'Faltante de caja', 'estado' => 'pendiente',
    ]);
    $this->post(route('finanzas.planilla-descuentos.aplicar', $desc))->assertSessionHasNoErrors();
    expect($desc->fresh()->estado)->toBe('aplicado');

    $this->post(route('finanzas.planilla-descuentos.desaplicar', $desc), ['motivo' => 'Se aplicó por error'])->assertSessionHasNoErrors();

    $desc->refresh();
    expect($desc->estado)->toBe('pendiente');
    expect($desc->fecha_aplicacion)->toBeNull();
});

// ── Adelantos: editar y reactivar ───────────────────────────────────────

function crearAdelanto($test, float $monto = 500): ProveedorAdelanto
{
    $prov = Proveedor::create([
        'empresa_id' => $test->env->empresa->id, 'razon_social' => 'Proveedor Flex',
        'tipo_documento' => 'RUC', 'numero_documento' => '20123456789', 'activo' => true,
    ]);
    $test->post(route('finanzas.adelantos.store'), [
        'proveedor_id' => $prov->id, 'fecha' => now()->toDateString(), 'monto' => $monto,
        'metodo_pago_id' => $test->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    return ProveedorAdelanto::where('empresa_id', $test->env->empresa->id)->orderByDesc('id')->first();
}

it('editar un adelanto sin consumos rehace el egreso en tesorería', function () {
    $adelanto = crearAdelanto($this, 500);

    $this->put(route('finanzas.adelantos.update', $adelanto), [
        'monto' => 800, 'fecha' => now()->subDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    $adelanto->refresh();
    expect((float) $adelanto->monto)->toBe(800.0);
    expect((float) $adelanto->saldo)->toBe(800.0);

    $movs = CuentaMovimiento::where('ref_tipo', 'proveedor_adelanto')->where('ref_id', $adelanto->id)->get();
    expect($movs)->toHaveCount(1);
    expect((float) $movs->first()->monto)->toBe(800.0);
});

it('reactivar un adelanto anulado re-asienta el egreso', function () {
    $adelanto = crearAdelanto($this, 300);
    $this->post(route('finanzas.adelantos.anular', $adelanto), ['accion' => 'anulado', 'motivo' => 'Error de registro'])->assertSessionHasNoErrors();
    expect(CuentaMovimiento::where('ref_tipo', 'proveedor_adelanto')->where('ref_id', $adelanto->id)->exists())->toBeFalse();

    $this->post(route('finanzas.adelantos.reactivar', $adelanto), ['motivo' => 'Anulado por equivocación'])->assertSessionHasNoErrors();

    expect($adelanto->fresh()->estado)->toBe('activo');
    expect(CuentaMovimiento::where('ref_tipo', 'proveedor_adelanto')->where('ref_id', $adelanto->id)->exists())->toBeTrue();
});

it('adelanto con turno_id descuenta la caja en calcularMontoEsperado', function () {
    $turno = $this->env->abrirTurno(apertura: 500);

    $prov = Proveedor::create([
        'empresa_id' => $this->env->empresa->id, 'razon_social' => 'Proveedor Turno',
        'tipo_documento' => 'RUC', 'numero_documento' => '20999888777', 'activo' => true,
    ]);

    // Registrar adelanto imputado a este turno.
    $this->post(route('finanzas.adelantos.store'), [
        'proveedor_id'  => $prov->id,
        'fecha'         => now()->toDateString(),
        'monto'         => 200,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
        'turno_id'      => $turno->id,
    ])->assertSessionHasNoErrors();

    $adelanto = ProveedorAdelanto::where('empresa_id', $this->env->empresa->id)->orderByDesc('id')->first();
    expect($adelanto->turno_id)->toBe($turno->id);

    // Esperado = 500 (apertura) - 200 (adelanto efectivo) = 300
    expect($turno->fresh()->calcularMontoEsperado())->toBe(300.0);
});

// ── Tesorería: movimiento manual de ajuste ──────────────────────────────

it('registra un movimiento manual de ajuste (egreso) sobre una cuenta', function () {
    $efectivo = TesoreriaService::efectivo($this->env->empresa->id);
    $saldoAntes = app(TesoreriaService::class)->saldo($efectivo->id);

    $this->post(route('finanzas.tesoreria.movimiento'), [
        'cuenta_id'   => $efectivo->id,
        'fecha'       => now()->toDateString(),
        'tipo'        => 'egreso',
        'monto'       => 700,
        'descripcion' => 'Ajuste de cuenta: no cuadra el arqueo',
    ])->assertSessionHasNoErrors();

    expect(app(TesoreriaService::class)->saldo($efectivo->id))->toBe(round($saldoAntes - 700, 2));
    $mov = CuentaMovimiento::where('ref_tipo', 'ajuste')->orderByDesc('id')->first();
    expect($mov->descripcion)->toContain('Ajuste manual');
    expect(\App\Models\Auditoria::where('accion', 'tesoreria.movimiento_manual')->exists())->toBeTrue();
});
