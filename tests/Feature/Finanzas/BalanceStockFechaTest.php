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

it('una entrega de pendiente hecha HOY no baja el STOCK del balance de AYER', function () {
    // Reproduce el incidente real: mercadería pagada AYER pero pendiente por
    // entregar sigue en almacén; se entrega HOY. El stock del balance de AYER
    // debe incluirla igual (la entrega de hoy no reduce el día ya cerrado).
    $cliente = \App\Models\Cliente::create([
        'empresa_id' => $this->env->empresa->id,
        'nombres' => 'Pendiente', 'apellidos' => 'Test',
        'tipo_documento' => 'DNI', 'numero_documento' => '43219876', 'activo' => true,
    ]);
    // Stock inicial: 50 und × costo 6 = 300.
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    // AYER: venta de 5 und TODAS pendientes por entregar → NO salen del almacén al vender.
    $venta = $this->ventas->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $cliente->id,
        'fecha_venta'       => now()->subDay()->toDateString(),
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
            'cantidad_pendiente' => 5,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    $anticipo = \App\Models\ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();

    // HOY: se entregan las 5 → recién ahora salen del almacén (stock 45 × 6 = 270).
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 5]],
    ])->assertSessionHasNoErrors();

    // Balance de AYER: las 5 aún estaban en almacén → stock = 300
    // (SIN el fix, la reconstrucción daba 270 porque no devolvía la entrega).
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'stock')->monto)->toBe(300.0);

    // Balance de HOY: entregado → 45 × 6 = 270.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    expect((float) $hoyBal->items->firstWhere('categoria', 'stock')->monto)->toBe(270.0);
});

it('reabrir el balance de AYER refleja una venta backdateada a ese día (no se congela)', function () {
    // El reabrir DEBE recomputar: si registro una venta con fecha de AYER y
    // reabro, el stock de ayer tiene que bajar (no quedarse congelado).
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    // Confirmar AYER: stock = 50 × 6 = 300.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'stock')->monto)->toBe(300.0);
    $this->balances->confirmar($ayer, $this->env->admin);

    // Se registra una venta OLVIDADA con fecha de AYER (3 und → −18 de costo).
    $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'fecha_venta'      => now()->subDay()->toDateString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 3,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 30]],
    ], $this->env->admin, $this->turno);

    // Reabrir y regenerar: el stock de AYER debe reflejar la venta = 47 × 6 = 282.
    $ayer->update(['estado' => 'borrador']);
    $regen = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $regen->items->firstWhere('categoria', 'stock')->monto)->toBe(282.0);
});
