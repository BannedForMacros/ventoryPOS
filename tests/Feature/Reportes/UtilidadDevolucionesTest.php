<?php

use App\Services\DevolucionService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Reporte de utilidad: una devolución deshace la venta (ventas y costo netos),
 * no es un gasto. Solo la mercadería devuelta dañada es pérdida real.
 *
 * Escenario base: 5 und a S/ 10 con costo S/ 6 → vendido 50, costo 30, bruta 20.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);
    $this->venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    $this->devolver = fn (string $motivo, string $forma, bool $restock, float $cantidad = 2) =>
        app(DevolucionService::class)->crear([
            'venta_id'        => $this->venta->id,
            'motivo_id'       => $this->env->motivo($motivo)->id,
            'forma_reembolso' => $forma,
            'items' => [[
                'venta_item_id'   => $this->venta->items->first()->id,
                'cantidad'        => $cantidad,
                'estado_producto' => $restock ? 'bueno' : 'defectuoso',
                'restock'         => $restock,
            ]],
            'pagos' => $forma === 'sin_reembolso' ? [] : [[
                'metodo_pago_id' => $this->env->metodo('efectivo')->id,
                'monto'          => $cantidad * 10,
            ]],
        ], $this->env->admin, $this->turno);

    $this->kpis = fn () => $this->get(route('reportes.utilidad'))->viewData('page')['props']['kpis'];
});

it('si el producto vuelve al stock, es como si esa venta no hubiera existido', function () {
    ($this->devolver)('producto_equivocado', 'efectivo', restock: true);

    $k = ($this->kpis)();
    expect((float) $k['devuelto'])->toBe(20.0);      // 2 × 10 devueltos al cliente
    expect((float) $k['recuperado'])->toBe(12.0);    // 2 × 6 vuelven al inventario
    expect((float) $k['ventas'])->toBe(30.0);        // 50 − 20: queda lo vendido de verdad
    expect((float) $k['costo'])->toBe(18.0);         // 30 − 12
    expect((float) $k['costo_danado'])->toBe(0.0);
    expect((float) $k['utilidad_neta'])->toBe(12.0); // igual que haber vendido solo 3: 3 × 4
});

it('vender y que te devuelvan todo deja la utilidad en cero', function () {
    ($this->devolver)('producto_equivocado', 'efectivo', restock: true, cantidad: 5);

    $k = ($this->kpis)();
    expect((float) $k['ventas'])->toBe(0.0);
    expect((float) $k['costo'])->toBe(0.0);
    expect((float) $k['utilidad_neta'])->toBe(0.0);  // el patrimonio queda igual
});

it('si el producto vuelve dañado, se pierde su costo', function () {
    ($this->devolver)('defecto_fabrica', 'efectivo', restock: false);

    $k = ($this->kpis)();
    expect((float) $k['recuperado'])->toBe(0.0);
    expect((float) $k['costo_danado'])->toBe(12.0);  // 2 × 6 de mercadería perdida
    expect((float) $k['ventas'])->toBe(30.0);
    expect((float) $k['costo'])->toBe(30.0);         // el costo de lo dañado no se recupera
    expect((float) $k['utilidad_neta'])->toBe(0.0);
});

it('una devolución sin reembolso no resta dinero', function () {
    ($this->devolver)('defecto_fabrica', 'sin_reembolso', restock: false);

    $k = ($this->kpis)();
    expect((float) $k['devuelto'])->toBe(0.0);
    expect((float) $k['ventas'])->toBe(50.0);
    expect((float) $k['utilidad_neta'])->toBe(20.0);
});

it('muestra los descuentos que ya vienen restados en lo vendido', function () {
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
            'descuento_item'     => 1,   // S/ 1 por unidad → 2 de descuento
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 18]],
    ], $this->env->admin, $this->turno);

    $k = ($this->kpis)();
    expect((float) $k['descuentos'])->toBe(2.0);
    expect((float) $k['ventas'])->toBe(68.0); // 50 + 18: el descuento ya está restado
});
