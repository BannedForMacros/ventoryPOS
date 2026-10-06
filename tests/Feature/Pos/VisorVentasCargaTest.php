<?php

use App\Models\VisorVentaSesion;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Visor de ventas: cargar lo leído al POS. Complementa a VisorVentasTest.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->env->empresa->update(['usa_visor_ventas' => true, 'visor_ventas_limite_diario' => 5]);
});

it('con ids carga hasta 50 productos, no solo los 40 de la grilla', function () {
    $ids = collect(range(1, 45))
        ->map(fn ($i) => $this->env->crearProducto(['nombre' => sprintf('PRODUCTO VISOR %02d', $i)])->id)
        ->all();

    $r = $this->getJson(route('pos.productos', ['ids' => $ids]))->assertOk()->json();

    expect(collect($r['productos'])->pluck('id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
        ->and($r['has_more'])->toBeFalse();
});

it('editando una venta no se cruza la última lectura del cuaderno', function () {
    $producto = $this->env->crearProducto(['nombre' => 'PARACETAMOL 500MG', 'precio_venta' => 10, 'stock_inicial' => 50]);
    VisorVentaSesion::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id,
        'estado' => 'leida', 'ventas_leidas' => 1, 'modelo' => 'simulado',
        'lectura' => ['ventas' => [[
            'fecha' => now()->format('d/m/y'), 'total' => 10,
            'items' => [['cantidad' => 1, 'texto' => '1 paracetamol', 'interpretacion' => 'paracetamol', 'seguro' => true]],
        ]]],
    ]);

    // Sin edición la revisión sigue ahí (contraprueba)…
    $this->get(route('pos.index'))->assertInertia(fn ($p) => $p->has('visorVentas.ultima.ventas', 1));

    // …y editando una venta ni se calcula.
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $this->turno);

    $this->get(route('pos.index', ['venta_id' => $venta->id]))->assertInertia(fn ($p) => $p
        ->whereNot('ventaEnEdicion', null)
        ->where('visorVentas.ultima', null));
});
