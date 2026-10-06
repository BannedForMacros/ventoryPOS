<?php

use App\Models\ProductoUnidad;
use App\Models\UnidadMedida;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Revisión de reportes — P2: la tabla por producto (utilidad, productos,
 * dashboard y ventas) sumaba el subtotal de cada línea SIN el descuento global
 * de la venta y SUM(cantidad) mezclaba presentaciones (1 caja + 3 unidades = 4).
 * Ahora la venta de cada línea lleva su parte del descuento global y las
 * unidades van en la unidad base.
 */
it('las tablas por producto restan el descuento global y cuentan en unidad base', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $turno = $env->abrirTurno();
    $producto = $env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $cajaUm = UnidadMedida::create(['empresa_id' => $env->empresa->id, 'nombre' => 'Caja x12', 'abreviatura' => 'CJ12', 'activo' => true]);
    $caja = ProductoUnidad::create(['producto_id' => $producto->id, 'unidad_medida_id' => $cajaUm->id, 'es_base' => false,
        'factor_conversion' => 12, 'tipo_precio' => 'fijo', 'precio_venta' => 120, 'precio_costo' => 72, 'activo' => true]);
    $efectivo = $env->metodo('efectivo')->id;

    // 1 caja (12 und) a 120, y 3 unidades a 10 con S/ 15 de descuento global.
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $caja->id, 'cantidad' => 1, 'precio_unitario' => 120]],
        'pagos' => [['metodo_pago_id' => $efectivo, 'monto' => 120]],
    ], $env->admin, $turno);
    $conDescuento = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket', 'descuento_total' => 15, 'descuento_concepto_id' => $env->descuentoConcepto->id,
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => 3, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $efectivo, 'monto' => 15]],
    ], $env->admin, $turno);
    expect((float) $conDescuento->total)->toBe(15.0);

    // Reporte de utilidad: la fila suma lo mismo que el resumen.
    $u = $this->get(route('reportes.utilidad'))->viewData('page')['props'];
    $fila = collect($u['productos']['data'])->firstWhere('producto_id', $producto->id);
    expect((float) $fila['ventas'])->toBe(135.0);   // antes 150
    expect((float) $fila['ventas'])->toBe((float) $u['kpis']['ventas']);
    expect((float) $fila['cantidad'])->toBe(15.0);  // 12 + 3 (antes 4)

    // Reporte de productos.
    $p = $this->get(route('reportes.productos'))->viewData('page')['props'];
    $rank = collect($p['productos']['data'])->firstWhere('producto_id', $producto->id);
    expect((float) $rank['monto_total'])->toBe(135.0);
    expect((float) $rank['cantidad_total'])->toBe(15.0);

    // Dashboard (top del mes).
    $d = $this->get(route('dashboard'))->viewData('page')['props'];
    $top = collect($d['topProductos'])->firstWhere('producto_id', $producto->id);
    expect((float) $top['total'])->toBe(135.0);
    expect((float) $top['cantidad'])->toBe(15.0);
});
