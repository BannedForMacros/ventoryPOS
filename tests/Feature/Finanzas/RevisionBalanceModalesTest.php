<?php

use App\Models\Almacen;
use App\Models\PlanillaDescuento;
use App\Services\BalanceDiarioService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión del balance — P1: el modal de cada línea suma EXACTAMENTE lo mismo
 * que la línea. Antes cada modal tenía su propia consulta (límite de 500 filas
 * antes de filtrar el saldo, sin GREATEST por almacén, planilla de solo 3
 * meses, stock vivo de hoy) y su total no coincidía con la línea.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
    $this->hoy = now()->toDateString();
});

function rbmLinea($test, string $categoria): float
{
    return (float) $test->balances->generar($test->env->admin, $test->hoy)
        ->items->where('categoria', $categoria)->sum('monto');
}

function rbmModal($test, string $categoria): array
{
    return $test->getJson(route('finanzas.balance.detalle', ['fecha' => $test->hoy, 'categoria' => $categoria]))
        ->assertOk()->json();
}

it('stock, stock_mov, cxc y planilla: el modal suma lo mismo que la línea', function () {
    $env = $this->env;

    // ── Stock: un producto con saldo +10 en un almacén y −4 en otro. La línea
    // no deja que el negativo reste (GREATEST por almacén): vale 10 × 5 = 50.
    $p = $env->crearProducto(['nombre' => 'Cemento RB', 'precio_costo' => 5, 'stock_inicial' => 0]);
    $otro = Almacen::create(['empresa_id' => $env->empresa->id, 'local_id' => $env->local->id,
        'nombre' => 'Almacén 2', 'tipo' => 'local', 'activo' => true]);
    foreach ([[$env->almacen->id, 10], [$otro->id, -4]] as [$alm, $saldo]) {
        DB::table('movimientos_inventario')->insert([
            'empresa_id' => $env->empresa->id, 'almacen_id' => $alm, 'producto_id' => $p->id,
            'fecha' => $this->hoy . ' 08:00:00', 'tipo' => 'entrada', 'cantidad' => $saldo,
            'costo_unitario' => 5, 'costo_promedio' => 5, 'saldo_cantidad' => $saldo, 'saldo_valorizado' => $saldo * 5,
        ]);
    }

    // ── CxC: 501 ventas a crédito recientes ya pagadas y UNA más antigua con
    // saldo. El modal tomaba las 500 más recientes y recién filtraba el saldo.
    $turno = $env->abrirTurno();
    $base = ['empresa_id' => $env->empresa->id, 'local_id' => $env->local->id, 'user_id' => $env->admin->id,
        'turno_id' => $turno->id, 'caja_id' => $env->caja->id,
        'cliente_id' => $env->clienteGeneral->id, 'tipo_comprobante' => 'ticket', 'estado' => 'completada',
        'es_credito' => true, 'subtotal' => 10, 'igv' => 0, 'total' => 10, 'created_at' => now(), 'updated_at' => now()];
    DB::table('ventas')->insert($base + ['numero' => 'RB-VIEJA', 'fecha_venta' => now()->subDays(20),
        'monto_pagado' => 0, 'saldo_pendiente' => 10]);
    DB::table('ventas')->insert(collect(range(1, 501))->map(fn ($i) => $base + [
        'numero' => "RB-{$i}", 'fecha_venta' => now()->subMinutes($i), 'monto_pagado' => 10, 'saldo_pendiente' => 0,
    ])->all());

    // ── Planilla: un descuento de hace 5 meses aún pendiente (el modal solo
    // miraba 3 meses).
    PlanillaDescuento::create([
        'empresa_id' => $env->empresa->id, 'user_id' => $env->admin->id, 'registrado_por' => $env->admin->id,
        'fecha' => now()->subMonths(5)->toDateString(), 'monto' => 75, 'motivo' => 'Faltante antiguo', 'estado' => 'pendiente',
    ]);

    $stock = rbmLinea($this, 'stock');
    expect($stock)->toBe(50.0);
    $modal = rbmModal($this, 'stock');
    expect((float) $modal['cards'][0]['valor'])->toBe($stock);
    expect((float) collect($modal['grupos'])->sum('monto'))->toBe($stock);

    // El panel de movimientos dice el valor del inventario A LA FECHA (el de la línea).
    $mov = rbmModal($this, 'stock_mov');
    expect((float) collect($mov['cards'])->firstWhere('label', "Valor del inventario al {$this->hoy}")['valor'])->toBe($stock);

    $cxc = rbmLinea($this, 'cxc');
    expect($cxc)->toBe(10.0);
    expect((float) rbmModal($this, 'cxc')['cards'][0]['valor'])->toBe($cxc);

    $planilla = rbmLinea($this, 'planilla_descuento');
    expect($planilla)->toBe(75.0);
    expect((float) rbmModal($this, 'planilla_descuento')['cards'][0]['valor'])->toBe($planilla);
});
