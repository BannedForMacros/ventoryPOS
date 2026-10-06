<?php

use App\Models\Cliente;
use App\Services\EstadoCuentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión — P2: estado de cuenta por tercero.
 *  • La antigüedad salía NEGATIVA (Carbon 3 devuelve diffInDays con signo).
 *  • "Su anticipo" ignoraba los anticipos de mercadería con saldo en dinero 0
 *    pero unidades aún por entregar; el balance sí los valoriza (precio pagado).
 */
it('la antigüedad es positiva y su anticipo incluye las unidades pendientes al precio pagado', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $turno = $env->abrirTurno();
    $cliente = Cliente::create(['empresa_id' => $env->empresa->id, 'nombres' => 'Tercero', 'apellidos' => 'RB',
        'tipo_documento' => 'DNI', 'numero_documento' => '73737373', 'activo' => true]);
    $producto = $env->crearProducto(['precio_venta' => 30, 'stock_inicial' => 0]);

    // Crédito de hace 10 días con saldo.
    DB::table('ventas')->insert([
        'empresa_id' => $env->empresa->id, 'local_id' => $env->local->id, 'caja_id' => $env->caja->id,
        'turno_id' => $turno->id, 'user_id' => $env->admin->id, 'cliente_id' => $cliente->id,
        'numero' => 'RB-EC', 'tipo_comprobante' => 'ticket', 'estado' => 'completada', 'es_credito' => true,
        'subtotal' => 50, 'igv' => 0, 'total' => 50, 'monto_pagado' => 0, 'saldo_pendiente' => 50,
        'fecha_venta' => now()->subDays(10), 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Anticipo de mercadería: pagó 10 und a S/ 20 (200), quedan 4 por entregar
    // y su saldo en dinero ya está en 0.
    DB::table('cliente_anticipos')->insert([
        'empresa_id' => $env->empresa->id, 'cliente_id' => $cliente->id, 'user_id' => $env->admin->id,
        'fecha' => now()->subDays(4)->toDateString(), 'monto' => 200, 'saldo' => 0,
        'tipo_valorizacion' => 'material', 'producto_id' => $producto->id, 'cantidad' => 10, 'cantidad_pendiente' => 4,
        'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $fila = app(EstadoCuentaService::class)->resumen($env->empresa->id)->firstWhere('cliente_id', $cliente->id);

    expect($fila['dias_antiguedad'])->toBe(10);       // antes −10
    expect((float) $fila['su_anticipo'])->toBe(80.0); // 4 × 20 (antes 0)
    expect((float) $fila['neto'])->toBe(-30.0);       // nos debe 50 − le debemos 80

    // El detalle del tercero muestra el mismo anticipo.
    $detalle = $this->get(route('finanzas.estado-cuenta.show', $fila['clave']))->viewData('page')['props'];
    $mov = collect($detalle['movimientos'] ?? [])->firstWhere('tipo', 'anticipo');
    expect((float) $mov['monto'])->toBe(-80.0);
});
