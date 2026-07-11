<?php

use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * El detalle de la línea "Stock" del balance diario ahora muestra los
 * MOVIMIENTOS del día (qué entró/salió y por qué cambió el inventario),
 * no una lista vacía.
 */

beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

it('el detalle de stock lista las ventas del día como salida de inventario', function () {
    $producto = $this->env->crearProducto([
        'nombre' => 'Cemento X', 'precio_venta' => 30, 'precio_costo' => 20, 'stock_inicial' => 100,
    ]);
    $efectivo = $this->env->metodo('efectivo');

    // Venta de 5 unidades hoy → stock baja, valor de inventario baja
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 30,
        ]],
        'pagos' => [[ 'metodo_pago_id' => $efectivo->id, 'monto' => 150 ]],
    ], $this->env->admin, $this->turno);

    $hoy = now()->toDateString();
    $resp = $this->getJson(route('finanzas.balance.detalle', ['fecha' => $hoy, 'categoria' => 'stock']));

    $resp->assertOk();
    $json = $resp->json();

    expect($json['tipo'])->toBe('grupos');
    // Debe existir un grupo "Ventas" con el producto vendido
    $ventas = collect($json['grupos'])->firstWhere('titulo', 'Ventas');
    expect($ventas)->not->toBeNull();
    $item = collect($ventas['items'])->firstWhere('producto', 'Cemento X');
    expect($item)->not->toBeNull();
    // Salida de 5 uds × costo 20 = -100
    expect((float) $item['monto'])->toBe(-100.0);
    expect($item['cantidad'])->toBe('-5');

    // Cards de resumen: la variación del día debe ser negativa (salió inventario)
    $variacion = collect($json['cards'])->firstWhere('label', 'Variación del día');
    expect((float) $variacion['valor'])->toBe(-100.0);
});

it('el detalle de stock muestra las entradas (compras) del día como reingreso', function () {
    // Sin ventas: creamos una entrada confirmada directamente
    $producto = $this->env->crearProducto(['nombre' => 'Fierro Y', 'precio_costo' => 15, 'stock_inicial' => 0]);

    $entradaId = \Illuminate\Support\Facades\DB::table('entradas')->insertGetId([
        'empresa_id' => $this->env->empresa->id,
        'almacen_id' => $this->env->almacen->id,
        'user_id'    => $this->env->admin->id,
        'numero_documento' => 'F001-123',
        'tipo'       => 'compra',
        'fecha'      => now()->toDateString(),
        'estado'     => 'confirmado',
        'total'      => 150,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \Illuminate\Support\Facades\DB::table('entradas_detalle')->insert([
        'entrada_id'       => $entradaId,
        'producto_id'      => $producto->id,
        'unidad_medida_id' => $this->env->unidad->id,
        'cantidad'         => 10,
        'factor_conversion'=> 1,
        'cantidad_base'    => 10,
        'precio_costo'     => 15,
        'subtotal'         => 150,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $hoy = now()->toDateString();
    $json = $this->getJson(route('finanzas.balance.detalle', ['fecha' => $hoy, 'categoria' => 'stock']))->json();

    $entradas = collect($json['grupos'])->firstWhere('titulo', 'Entradas (compras)');
    expect($entradas)->not->toBeNull();
    $item = collect($entradas['items'])->firstWhere('producto', 'Fierro Y');
    expect((float) $item['monto'])->toBe(150.0); // +10 × 15
    expect($item['cantidad'])->toBe('+10');
});
