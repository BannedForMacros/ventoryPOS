<?php

use App\Models\CuentaMovimiento;
use App\Models\Entrada;
use App\Models\EntradaPago;
use Tests\Support\TestEnv;

/**
 * CxP: editar y anular pagos registrados (solo admin) manteniendo tesorería
 * y el estado de pago de la compra consistentes.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);

    // Compra confirmada de S/ 100 sin pagar.
    $this->entrada = Entrada::create([
        'empresa_id'   => $this->env->empresa->id,
        'almacen_id'   => $this->env->almacen->id,
        'user_id'      => $this->env->admin->id,
        'proveedor'    => 'Proveedor Test',
        'fecha'        => now()->toDateString(),
        'total'        => 100,
        'monto_pagado' => 0,
        'estado'       => 'confirmado',
        'estado_pago'  => 'pendiente',
    ]);
});

function pagarCxp($test, float $monto): EntradaPago
{
    $test->post(route('finanzas.cxp.abonar', $test->entrada), [
        'monto'          => $monto,
        'fecha'          => now()->toDateString(),
        'metodo_pago_id' => $test->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    return EntradaPago::where('entrada_id', $test->entrada->id)->orderByDesc('id')->first();
}

it('editar un pago recalcula tesorería y el saldo de la compra', function () {
    $pago = pagarCxp($this, 40);
    expect((float) $this->entrada->fresh()->monto_pagado)->toBe(40.0);

    // El admin corrige: eran S/ 60, no 40.
    $this->put(route('finanzas.cxp.pagos.update', $pago), [
        'monto'          => 60,
        'fecha'          => now()->subDay()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $entrada = $this->entrada->fresh();
    expect((float) $entrada->monto_pagado)->toBe(60.0);
    expect($entrada->estado_pago)->toBe('parcial');

    // Tesorería: un único egreso vigente por S/ 60 con la fecha corregida.
    $movs = CuentaMovimiento::where('ref_tipo', 'entrada_pago')->where('ref_id', $pago->id)->get();
    expect($movs)->toHaveCount(1);
    expect((float) $movs->first()->monto)->toBe(60.0);
    expect(substr((string) $movs->first()->fecha, 0, 10))->toBe(now()->subDay()->toDateString());
});

it('no permite que el pago editado supere el saldo de la compra', function () {
    $pago = pagarCxp($this, 40);
    pagarCxp($this, 50); // pagado 90, saldo 10 → el primer pago puede subir máx. a 50

    $this->put(route('finanzas.cxp.pagos.update', $pago), [
        'monto' => 80,
        'fecha' => now()->toDateString(),
    ])->assertSessionHasErrors(['monto']);

    expect((float) $this->entrada->fresh()->monto_pagado)->toBe(90.0);
});

it('anular un pago revierte tesorería y devuelve el saldo a la compra', function () {
    $pago = pagarCxp($this, 100);
    expect($this->entrada->fresh()->estado_pago)->toBe('pagado');

    $this->delete(route('finanzas.cxp.pagos.destroy', $pago), [
        'motivo' => 'Se registró doble por error',
    ])->assertSessionHasNoErrors();

    $entrada = $this->entrada->fresh();
    expect((float) $entrada->monto_pagado)->toBe(0.0);
    expect($entrada->estado_pago)->toBe('pendiente');
    expect(EntradaPago::find($pago->id))->toBeNull();
    expect(CuentaMovimiento::where('ref_tipo', 'entrada_pago')->where('ref_id', $pago->id)->exists())->toBeFalse();
});

it('abonar consumiendo un adelanto acepta metodo_pago_id nulo (lo manda así el front)', function () {
    $prov = \App\Models\Proveedor::create([
        'empresa_id' => $this->env->empresa->id, 'razon_social' => 'Proveedor Adelanto',
        'tipo_documento' => 'RUC', 'numero_documento' => '20999888777', 'activo' => true,
    ]);
    $this->entrada->update(['proveedor_id' => $prov->id]);
    $adelanto = \App\Models\ProveedorAdelanto::create([
        'empresa_id' => $this->env->empresa->id, 'proveedor_id' => $prov->id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 60, 'saldo' => 60, 'estado' => 'activo',
    ]);

    $this->post(route('finanzas.cxp.abonar', $this->entrada), [
        'monto'                 => 60,
        'fecha'                 => now()->toDateString(),
        'metodo_pago_id'        => null,
        'cuenta_id'             => null,
        'proveedor_adelanto_id' => $adelanto->id,
    ])->assertSessionHasNoErrors();

    expect((float) $this->entrada->fresh()->monto_pagado)->toBe(60.0);
    expect((float) $adelanto->fresh()->saldo)->toBe(0.0);
});

it('abonar sin adelanto sigue exigiendo método de pago', function () {
    $this->post(route('finanzas.cxp.abonar', $this->entrada), [
        'monto' => 10, 'fecha' => now()->toDateString(), 'metodo_pago_id' => null,
    ])->assertSessionHasErrors(['metodo_pago_id']);
});
