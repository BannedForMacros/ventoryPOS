<?php

use App\Models\CuentaMovimiento;
use App\Models\Entrada;
use App\Models\EntradaPago;
use Tests\Support\TestEnv;

/**
 * Editar entrada: registrar pagos NUEVOS del saldo desde la misma edición
 * (p. ej. se agregó un producto y se paga la diferencia ahí mismo).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_costo' => 10, 'stock_inicial' => 0]);
});

function crearEntradaBorrador($test, float $cantidad = 5): Entrada
{
    $entrada = Entrada::create([
        'empresa_id'   => $test->env->empresa->id,
        'almacen_id'   => $test->env->almacen->id,
        'user_id'      => $test->env->admin->id,
        'proveedor'    => 'Proveedor Edit',
        'tipo'         => 'compra',
        'fecha'        => now()->toDateString(),
        'total'        => $cantidad * 10,
        'monto_pagado' => 0,
        'estado'       => 'borrador',
        'estado_pago'  => 'pendiente',
    ]);
    $entrada->detalles()->create([
        'producto_id'       => $test->producto->id,
        'unidad_medida_id'  => $test->env->unidad->id,
        'cantidad'          => $cantidad,
        'factor_conversion' => 1,
        'cantidad_base'     => $cantidad,
        'precio_costo'      => 10,
        'subtotal'          => $cantidad * 10,
    ]);

    return $entrada;
}

function payloadEdicionEntrada($test, float $cantidad, array $pagos = []): array
{
    return [
        'almacen_id' => $test->env->almacen->id,
        'tipo'       => 'compra',
        'fecha'      => now()->toDateString(),
        'detalles'   => [[
            'producto_id'       => $test->producto->id,
            'unidad_medida_id'  => $test->env->unidad->id,
            'cantidad'          => $cantidad,
            'factor_conversion' => 1,
            'precio_costo'      => 10,
        ]],
        'pagos' => $pagos,
    ];
}

it('al editar se puede registrar el pago del saldo (entrada_pagos + tesorería + estado)', function () {
    $entrada = crearEntradaBorrador($this, 5); // total 50, pendiente

    // Edición: sube a 8 und (total 80) y paga TODO ahí mismo.
    $this->put(route('inventario.entradas.update', $entrada), payloadEdicionEntrada($this, 8, [
        ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'cuenta_id' => null, 'monto' => 80],
    ]))->assertSessionHasNoErrors();

    $entrada->refresh();
    expect((float) $entrada->total)->toBe(80.0);
    expect((float) $entrada->monto_pagado)->toBe(80.0);
    expect($entrada->estado_pago)->toBe('pagado');

    $pago = EntradaPago::where('entrada_id', $entrada->id)->first();
    expect($pago)->not->toBeNull();
    expect(CuentaMovimiento::where('ref_tipo', 'entrada_pago')->where('ref_id', $pago->id)->where('tipo', 'egreso')->exists())->toBeTrue();
});

it('acepta pago parcial en la edición y queda como parcial', function () {
    $entrada = crearEntradaBorrador($this, 5); // total 50

    $this->put(route('inventario.entradas.update', $entrada), payloadEdicionEntrada($this, 5, [
        ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'cuenta_id' => null, 'monto' => 20],
    ]))->assertSessionHasNoErrors();

    $entrada->refresh();
    expect((float) $entrada->monto_pagado)->toBe(20.0);
    expect($entrada->estado_pago)->toBe('parcial');
});

it('rechaza pagos que superan el saldo pendiente', function () {
    $entrada = crearEntradaBorrador($this, 5); // total 50

    $this->put(route('inventario.entradas.update', $entrada), payloadEdicionEntrada($this, 5, [
        ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'cuenta_id' => null, 'monto' => 70],
    ]))->assertSessionHasErrors(['pagos']);

    $entrada->refresh();
    expect((float) $entrada->monto_pagado)->toBe(0.0);
    expect(EntradaPago::where('entrada_id', $entrada->id)->count())->toBe(0);
});

it('editar sin pagos nuevos no toca los pagos existentes (comportamiento actual intacto)', function () {
    $entrada = crearEntradaBorrador($this, 5);

    $this->put(route('inventario.entradas.update', $entrada), payloadEdicionEntrada($this, 6, []))
        ->assertSessionHasNoErrors();

    $entrada->refresh();
    expect((float) $entrada->total)->toBe(60.0);
    expect((float) $entrada->monto_pagado)->toBe(0.0);
    expect($entrada->estado_pago)->toBe('pendiente');
});
