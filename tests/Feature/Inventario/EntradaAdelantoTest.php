<?php

use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\Proveedor;
use App\Models\ProveedorAdelanto;
use App\Models\CuentaMovimiento;
use Tests\Support\TestEnv;

/**
 * Pagos de entradas (crear/editar) usando adelantos a proveedores.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_costo' => 10, 'stock_inicial' => 0]);

    $this->proveedor = Proveedor::create([
        'empresa_id'       => $this->env->empresa->id,
        'razon_social'     => 'Proveedor Adelanto',
        'tipo_documento'   => 'RUC',
        'numero_documento' => '20512345678',
        'activo'           => true,
    ]);

    $this->adelanto = ProveedorAdelanto::create([
        'empresa_id'      => $this->env->empresa->id,
        'proveedor_id'    => $this->proveedor->id,
        'user_id'         => $this->env->admin->id,
        'metodo_pago_id'  => $this->env->metodo('efectivo')->id,
        'cuenta_id'       => null,
        'fecha'           => now()->toDateString(),
        'monto'           => 1000,
        'saldo'           => 1000,
        'estado'          => 'activo',
    ]);
});

function payloadNuevaEntrada($test, float $cantidad, array $pagos = [], string $estadoPago = 'pagado', ?int $proveedorId = null): array
{
    return [
        'almacen_id'   => $test->env->almacen->id,
        'proveedor_id' => $proveedorId ?? $test->proveedor->id,
        'tipo'         => 'compra',
        'fecha'        => now()->toDateString(),
        'estado_pago'  => $estadoPago,
        'pagos'        => $pagos,
        'detalles'     => [[
            'producto_id'       => $test->producto->id,
            'unidad_medida_id'  => $test->env->unidad->id,
            'cantidad'          => $cantidad,
            'factor_conversion' => 1,
            'precio_costo'      => 10,
        ]],
    ];
}

it('crear entrada pagada mezclando adelanto y efectivo', function () {
    // Compra de S/ 1200: adelanto 1000 + efectivo 200.
    $this->post(route('inventario.entradas.store'), payloadNuevaEntrada($this, 120, [
        ['proveedor_adelanto_id' => $this->adelanto->id, 'monto' => 1000, 'fecha' => now()->toDateString()],
        ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'cuenta_id' => null, 'monto' => 200, 'fecha' => now()->toDateString()],
    ]))->assertSessionHasNoErrors();

    $entrada = Entrada::where('proveedor_id', $this->proveedor->id)->orderByDesc('id')->first();
    expect($entrada)->not->toBeNull();
    expect((float) $entrada->total)->toBe(1200.0);
    expect((float) $entrada->monto_pagado)->toBe(1200.0);
    expect($entrada->estado_pago)->toBe('pagado');

    $pagos = EntradaPago::where('entrada_id', $entrada->id)->orderBy('id')->get();
    expect($pagos)->toHaveCount(2);
    $pagoAdelanto = $pagos->firstWhere('proveedor_adelanto_id', $this->adelanto->id);
    $pagoEfectivo = $pagos->firstWhere('proveedor_adelanto_id', null);
    expect($pagoAdelanto)->not->toBeNull();
    expect($pagoEfectivo)->not->toBeNull();
    expect((float) $pagoAdelanto->monto)->toBe(1000.0);
    expect((float) $pagoEfectivo->monto)->toBe(200.0);

    $this->adelanto->refresh();
    expect((float) $this->adelanto->saldo)->toBe(0.0);
    expect($this->adelanto->estado)->toBe('aplicado');

    // No hay egreso de tesorería por el pago con adelanto.
    expect(CuentaMovimiento::where('ref_tipo', 'entrada_pago')->where('ref_id', $pagoAdelanto->id)->exists())->toBeFalse();
    // Sí hay egreso por los S/ 200 en efectivo.
    expect(CuentaMovimiento::where('ref_tipo', 'entrada_pago')->where('ref_id', $pagoEfectivo->id)->where('tipo', 'egreso')->exists())->toBeTrue();
});

it('rechaza pagar con adelanto de otro proveedor', function () {
    $otroProveedor = Proveedor::create([
        'empresa_id'       => $this->env->empresa->id,
        'razon_social'     => 'Otro Proveedor',
        'tipo_documento'   => 'RUC',
        'numero_documento' => '20587654321',
        'activo'           => true,
    ]);

    $this->post(route('inventario.entradas.store'), payloadNuevaEntrada($this, 50, [
        ['proveedor_adelanto_id' => $this->adelanto->id, 'monto' => 500, 'fecha' => now()->toDateString()],
    ], 'parcial', $otroProveedor->id))->assertSessionHasErrors(['proveedor_adelanto_id']);

    $this->adelanto->refresh();
    expect((float) $this->adelanto->saldo)->toBe(1000.0);
});

it('rechaza pagar más del saldo del adelanto', function () {
    $this->post(route('inventario.entradas.store'), payloadNuevaEntrada($this, 200, [
        ['proveedor_adelanto_id' => $this->adelanto->id, 'monto' => 2000, 'fecha' => now()->toDateString()],
    ], 'parcial'))->assertSessionHasErrors(['monto']);

    $this->adelanto->refresh();
    expect((float) $this->adelanto->saldo)->toBe(1000.0);
});

it('al editar se puede pagar el saldo con adelanto', function () {
    $entrada = Entrada::create([
        'empresa_id'   => $this->env->empresa->id,
        'almacen_id'   => $this->env->almacen->id,
        'user_id'      => $this->env->admin->id,
        'proveedor_id' => $this->proveedor->id,
        'proveedor'    => $this->proveedor->razon_social,
        'tipo'         => 'compra',
        'fecha'        => now()->toDateString(),
        'total'        => 800,
        'monto_pagado' => 0,
        'estado'       => 'borrador',
        'estado_pago'  => 'pendiente',
    ]);
    $entrada->detalles()->create([
        'producto_id'       => $this->producto->id,
        'unidad_medida_id'  => $this->env->unidad->id,
        'cantidad'        => 80,
        'factor_conversion' => 1,
        'cantidad_base'   => 80,
        'precio_costo'    => 10,
        'subtotal'        => 800,
    ]);

    $this->put(route('inventario.entradas.update', $entrada), [
        'almacen_id' => $this->env->almacen->id,
        'proveedor_id' => $this->proveedor->id,
        'tipo'       => 'compra',
        'fecha'      => now()->toDateString(),
        'detalles'   => [[
            'producto_id'       => $this->producto->id,
            'unidad_medida_id'  => $this->env->unidad->id,
            'cantidad'          => 80,
            'factor_conversion' => 1,
            'precio_costo'      => 10,
        ]],
        'pagos' => [
            ['proveedor_adelanto_id' => $this->adelanto->id, 'monto' => 800, 'fecha' => now()->toDateString()],
        ],
    ])->assertSessionHasNoErrors();

    $entrada->refresh();
    expect((float) $entrada->monto_pagado)->toBe(800.0);
    expect($entrada->estado_pago)->toBe('pagado');

    $this->adelanto->refresh();
    expect((float) $this->adelanto->saldo)->toBe(200.0);
    expect($this->adelanto->estado)->toBe('activo');
});
