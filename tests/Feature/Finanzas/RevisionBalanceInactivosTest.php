<?php

use App\Models\Cuenta;
use App\Services\BalanceDiarioService;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión del balance — P1: desactivar una cuenta o un producto es un hecho de
 * HOY. Antes los dos desaparecían del balance de TODAS las fechas y el
 * patrimonio de los días pasados bajaba sin ningún movimiento que lo explicara.
 */
it('una cuenta y un producto desactivados siguen en el balance de los días en que tenían saldo', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $balances = app(BalanceDiarioService::class);
    $ayer = now()->subDay()->toDateString();

    $cuenta = Cuenta::create(['empresa_id' => $env->empresa->id, 'nombre' => 'Interbank RB', 'banco' => 'Interbank',
        'es_efectivo' => false, 'activo' => true]);
    app(TesoreriaService::class)->registrar($env->empresa->id, $cuenta->id, $env->admin, now()->subDays(3)->toDateString(),
        'ingreso', 800, 'Depósito', 'ajuste', null);

    $producto = $env->crearProducto(['nombre' => 'Clavo RB', 'precio_costo' => 4, 'stock_inicial' => 0]);
    DB::table('movimientos_inventario')->insert([
        'empresa_id' => $env->empresa->id, 'almacen_id' => $env->almacen->id, 'producto_id' => $producto->id,
        'fecha' => now()->subDays(3)->toDateString() . ' 09:00:00', 'tipo' => 'entrada', 'cantidad' => 25,
        'costo_unitario' => 4, 'costo_promedio' => 4, 'saldo_cantidad' => 25, 'saldo_valorizado' => 100,
    ]);

    // Día cerrado con los dos activos: su foto no cambia.
    $cerrado = $balances->generar($env->admin, now()->subDays(2)->toDateString());
    $balances->confirmar($cerrado, $env->admin);
    $neto = (float) $cerrado->fresh()->balance_neto;

    // Hoy se desactivan.
    $cuenta->update(['activo' => false]);
    $producto->update(['activo' => false]);

    $b = $balances->generar($env->admin, $ayer);
    expect((float) $b->items->firstWhere('descripcion', 'Interbank')?->monto)->toBe(800.0);
    expect((float) $b->items->firstWhere('categoria', 'stock')->monto)->toBe(100.0);

    // La tarjeta de cuentas del día también la muestra.
    $props = $this->get(route('finanzas.balance.show', $ayer))->viewData('page')['props'];
    expect(collect($props['saldosCuentas'])->firstWhere('id', $cuenta->id)['saldo'])->toEqual(800.0);

    expect((float) $balances->generar($env->admin, now()->subDays(2)->toDateString())->balance_neto)->toBe($neto);
});
