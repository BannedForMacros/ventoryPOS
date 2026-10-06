<?php

use App\Models\Cliente;
use App\Models\VentaAbono;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión del cierre de mes — P2: "Por cobrar al corte", los mayores deudores
 * y lo pagado de las compras usaban los saldos de HOY. Un cobro o un pago
 * posterior al corte borraba la deuda del cierre. Ahora se reconstruyen al
 * corte, como el balance (los movimientos posteriores se devuelven).
 */
it('el cierre de un período pasado muestra lo que se debía y lo pagado a esa fecha', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $desde = now()->subDays(6)->toDateString();
    $hasta = now()->subDay()->toDateString();

    $cliente = Cliente::create(['empresa_id' => $env->empresa->id, 'nombres' => 'Moroso', 'apellidos' => 'RB',
        'tipo_documento' => 'DNI', 'numero_documento' => '74747474', 'activo' => true]);
    $producto = $env->crearProducto(['precio_venta' => 100, 'stock_inicial' => 10]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket', 'cliente_id' => $cliente->id, 'es_credito' => true,
        'fecha_venta' => now()->subDays(3)->toDateString(),
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        'pagos' => [],
    ], $env->admin, $env->abrirTurno());
    // HOY (después del corte) la cobra completa.
    VentaAbono::create(['venta_id' => $venta->id, 'user_id' => $env->admin->id, 'fecha' => now()->toDateString(), 'monto' => 100]);
    $venta->update(['monto_pagado' => 100, 'saldo_pendiente' => 0]);

    // Compra de 200 en el período, pagada HOY.
    $entrada = DB::table('entradas')->insertGetId([
        'empresa_id' => $env->empresa->id, 'almacen_id' => $env->almacen->id, 'user_id' => $env->admin->id,
        'proveedor' => 'Proveedor RB', 'tipo' => 'compra', 'fecha' => now()->subDays(2)->toDateString(),
        'estado' => 'confirmado', 'total' => 200, 'monto_pagado' => 200, 'estado_pago' => 'pagado',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('entrada_pagos')->insert(['entrada_id' => $entrada, 'user_id' => $env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 200, 'created_at' => now(), 'updated_at' => now()]);

    $props = $this->get(route('reportes.cierre-mes', ['fecha_desde' => $desde, 'fecha_hasta' => $hasta]))
        ->viewData('page')['props'];

    expect((float) $props['kpis']['por_cobrar'])->toBe(100.0);       // antes 0
    expect($props['kpis']['por_cobrar_count'])->toBe(1);
    expect((float) $props['top_deudores'][0]['saldo'])->toBe(100.0);
    expect($props['top_deudores'][0]['nombre'])->toBe('Moroso RB');
    expect((float) $props['kpis']['compras_pagado'])->toBe(0.0);     // antes 200
    expect((float) $props['kpis']['compras_pendiente'])->toBe(200.0);
});
