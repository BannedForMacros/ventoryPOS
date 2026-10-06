<?php

use App\Services\BalanceDiarioService;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión del dashboard — P2.
 *  • Ventas por método sumaba lo ENTREGADO por el cliente, sin restar el vuelto.
 *  • El stock valorizado sumaba la tabla stock viva (con negativos) y no
 *    coincidía con la línea de stock del balance de hoy.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('ventas por método resta el vuelto', function () {
    $env = $this->env;
    $producto = $env->crearProducto(['precio_venta' => 70, 'stock_inicial' => 10]);
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 70]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 100]], // vuelto 30
    ], $env->admin, $env->abrirTurno());

    $props = $this->get(route('dashboard'))->viewData('page')['props'];
    expect((float) collect($props['ventasPorMetodo'])->firstWhere('tipo', 'efectivo')['total'])->toBe(70.0); // antes 100
});

it('el stock valorizado es el mismo de la línea de stock del balance de hoy', function () {
    $env = $this->env;
    $hoy = now()->toDateString();
    $bueno = $env->crearProducto(['precio_costo' => 6, 'stock_inicial' => 10]);
    $negativo = $env->crearProducto(['precio_costo' => 6, 'stock_inicial' => -5]); // error de registro: no es inventario
    foreach ([[$bueno, 10], [$negativo, -5]] as [$p, $saldo]) {
        DB::table('movimientos_inventario')->insert([
            'empresa_id' => $env->empresa->id, 'almacen_id' => $env->almacen->id, 'producto_id' => $p->id,
            'fecha' => $hoy . ' 08:00:00', 'tipo' => 'entrada', 'cantidad' => $saldo, 'costo_unitario' => 6,
            'costo_promedio' => 6, 'saldo_cantidad' => $saldo, 'saldo_valorizado' => $saldo * 6,
        ]);
    }

    $linea = (float) app(BalanceDiarioService::class)->generar($env->admin, $hoy)->items->firstWhere('categoria', 'stock')->monto;
    expect($linea)->toBe(60.0);

    $props = $this->get(route('dashboard'))->viewData('page')['props'];
    expect((float) $props['kpis']['stock_valorizado'])->toBe($linea); // antes 30 (restaba el negativo)
});
