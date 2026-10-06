<?php

use App\Models\ClienteAnticipo;
use App\Models\Deuda;
use App\Services\AuditoriaService;
use App\Services\BalanceDiarioService;
use App\Services\CambiosCierreService;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión — P2: el detector de cambios después del cierre explica también el
 * efectivo/banco cuando lo movieron: anular una deuda (revierte su tesorería),
 * reactivarla (la vuelve a asentar) y entregar en DINERO un anticipo (egreso).
 * Antes esas diferencias de caja salían "sin documento".
 */
it('anular una deuda y entregar dinero de un anticipo después del cierre explican la caja', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $balances = app(BalanceDiarioService::class);
    $tesoreria = app(TesoreriaService::class);
    $ayer = now()->subDay()->toDateString();
    $caja = TesoreriaService::efectivo($env->empresa->id);

    // Préstamo recibido hace días: entró 1000 a la caja.
    $deuda = Deuda::create(['empresa_id' => $env->empresa->id, 'user_id' => $env->admin->id,
        'direccion' => 'por_pagar', 'tipo' => 'personal', 'nombre' => 'Préstamo RB caja',
        'monto_original' => 1000, 'saldo' => 1000, 'fecha_inicio' => now()->subDays(3)->toDateString(), 'estado' => 'activa']);
    $tesoreria->registrar($env->empresa->id, $caja->id, $env->admin, now()->subDays(3)->toDateString(),
        'ingreso', 1000, 'Desembolso préstamo', 'deuda', $deuda->id);

    // Anticipo en dinero de 300 recibido hace días.
    $anticipo = ClienteAnticipo::create(['empresa_id' => $env->empresa->id, 'cliente_id' => $env->clienteGeneral->id,
        'user_id' => $env->admin->id, 'fecha' => now()->subDays(3)->toDateString(), 'monto' => 300, 'saldo' => 300,
        'tipo_valorizacion' => 'monto', 'estado' => 'activo']);

    $cerrado = $balances->generar($env->admin, $ayer);
    $balances->confirmar($cerrado, $env->admin);
    $this->travel(10)->minutes();

    // Se anula la deuda: su desembolso sale de la caja.
    AuditoriaService::log('deuda.anulada', $deuda, ['motivo' => 'mal registrada', 'saldo' => 1000]);
    $tesoreria->revertir('deuda', $deuda->id);
    $deuda->update(['estado' => 'anulada']);

    // Se entrega en dinero 100 del anticipo con fecha de ayer.
    $entregaId = DB::table('cliente_anticipo_aplicaciones')->insertGetId([
        'cliente_anticipo_id' => $anticipo->id, 'empresa_id' => $env->empresa->id, 'user_id' => $env->admin->id,
        'fecha' => $ayer, 'monto' => 100, 'metodo_pago_id' => $env->metodo('efectivo')->id, 'cuenta_id' => $caja->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $anticipo->update(['saldo' => 200]);
    $tesoreria->registrar($env->empresa->id, $caja->id, $env->admin, $ayer,
        'egreso', 100, 'Entrega de anticipo en dinero', 'cliente_anticipo_entrega', $entregaId);

    $r = app(CambiosCierreService::class)->analizar($cerrado->fresh());
    $efectivo = collect($r['categorias'])->firstWhere('categoria', 'efectivo');

    expect($efectivo['diferencia'])->toBe(-1100.0);
    expect($efectivo['sin_documento'])->toBeFalse();
    $tipos = collect($efectivo['documentos'])->pluck('tipo');
    expect($tipos)->toContain('Deuda anulada');
    expect($tipos)->toContain('Entrega de dinero de anticipo registrada después');
});
