<?php

use App\Services\BalanceDiarioService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * La línea "Stock (inventario valorizado)" del balance debe ser A LA FECHA
 * del balance, no el stock actual: cuadrar el balance del sábado un domingo
 * no debe restarle las ventas del domingo.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->ventas = app(VentaService::class);
    $this->balances = app(BalanceDiarioService::class);
    $this->actingAs($this->env->admin);
});

it('el balance de AYER usa el stock de ayer aunque hoy ya haya ventas', function () {
    // Stock inicial: 50 und × costo 6 = 300.
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    // HOY se venden 2 (stock actual queda 48 × 6 = 288).
    $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $this->turno);

    // Balance de AYER (se cuadra hoy): el stock debe ser el de AYER = 300.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    $lineaAyer = $ayer->items->firstWhere('categoria', 'stock');
    expect((float) $lineaAyer->monto)->toBe(300.0);

    // Balance de HOY: sí refleja la venta = 288.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    $lineaHoy = $hoyBal->items->firstWhere('categoria', 'stock');
    expect((float) $lineaHoy->monto)->toBe(288.0);
});

it('una compra registrada HOY tampoco infla el stock del balance de AYER', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    // Compra confirmada HOY: +10 und (stock actual 60).
    $entrada = \App\Models\Entrada::create([
        'empresa_id'  => $this->env->empresa->id,
        'almacen_id'  => $this->env->almacen->id,
        'user_id'     => $this->env->admin->id,
        'proveedor'   => 'Proveedor Test',
        'fecha'       => now()->toDateString(),
        'total'       => 60,
        'estado'      => 'confirmado',
        'estado_pago' => 'pendiente',
    ]);
    \Illuminate\Support\Facades\DB::table('entradas_detalle')->insert([
        'entrada_id'       => $entrada->id,
        'producto_id'      => $producto->id,
        'unidad_medida_id' => $this->env->unidad->id,
        'cantidad'         => 10,
        'factor_conversion'=> 1,
        'cantidad_base'    => 10,
        'precio_costo'     => 6,
        'subtotal'         => 60,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
    \App\Models\Stock::ajustar($this->env->almacen->id, $producto->id, 10, 6);

    // AYER no existía esa compra: stock del balance de ayer = 50 × 6 = 300.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'stock')->monto)->toBe(300.0);

    // HOY sí: 60 × 6 = 360.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    expect((float) $hoyBal->items->firstWhere('categoria', 'stock')->monto)->toBe(360.0);
});
