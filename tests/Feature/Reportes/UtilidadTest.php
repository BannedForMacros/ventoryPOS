<?php

use App\Models\VentaItem;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Reporte de Utilidad: costo CONGELADO al vender + KPIs precisos.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);
});

it('congela el costo unitario base en cada ítem al registrar la venta', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $this->turno);

    $item = VentaItem::where('venta_id', $venta->id)->first();
    expect((float) $item->costo_unitario_base)->toBe(6.0);

    // Si el costo del producto SUBE después, el snapshot no cambia.
    $producto->update(['precio_costo' => 9]);
    expect((float) $item->fresh()->costo_unitario_base)->toBe(6.0);
});

it('el reporte de utilidad calcula bruta y neta con el costo congelado', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    // Venta: 5 und × S/10 = 50 de venta; costo 5 × 6 = 30 → bruta 20.
    $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    // El costo sube DESPUÉS de la venta: la utilidad del período no debe cambiar.
    $producto->update(['precio_costo' => 9]);

    $res = $this->get(route('reportes.utilidad'));
    $res->assertOk();

    $kpis = $res->viewData('page')['props']['kpis'];
    expect((float) $kpis['ventas'])->toBe(50.0);
    expect((float) $kpis['costo'])->toBe(30.0);
    expect((float) $kpis['utilidad_bruta'])->toBe(20.0);
    expect((float) $kpis['margen_bruto'])->toBe(40.0);
    expect((float) $kpis['utilidad_neta'])->toBe(20.0); // sin gastos ni devoluciones

    // Y el desglose por producto refleja el mismo margen.
    $productos = collect($res->viewData('page')['props']['productos']);
    expect($productos)->toHaveCount(1);
    expect((float) $productos[0]['utilidad'])->toBe(20.0);
    expect((float) $productos[0]['margen'])->toBe(40.0);
});

it('el dashboard admin incluye utilidad del mes y la serie de 30 días', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $this->turno);

    $res = $this->get(route('dashboard'));
    $res->assertOk();

    $props = $res->viewData('page')['props'];
    expect((float) $props['kpis']['utilidad_bruta_mes'])->toBe(8.0); // 20 - 12
    expect($props['serie30'])->toHaveCount(30);
    expect((float) collect($props['serie30'])->last()['total'])->toBe(20.0); // hoy
});
