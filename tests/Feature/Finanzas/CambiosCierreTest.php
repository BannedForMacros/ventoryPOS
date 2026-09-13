<?php

use App\Models\BalanceDiario;
use App\Models\Deuda;
use App\Services\AuditoriaService;
use App\Services\BalanceDiarioService;
use App\Services\CambiosCierreService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Bloque 2 — días cerrados que cambiaron después del cierre.
 *
 * Garantías: el detector SOLO LEE (nunca regenera un día cerrado), marca desde
 * S/ 100 y explica cada cambio con el documento que lo causó.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
    $this->cambios  = app(CambiosCierreService::class);
    $this->ayer     = now()->subDay()->toDateString();
});

function ccConfirmar($test, string $fecha): BalanceDiario
{
    $b = $test->balances->generar($test->env->admin, $fecha);
    $test->balances->confirmar($b, $test->env->admin);

    return $b->fresh();
}

function ccCompra(TestEnv $env, string $fecha, float $total): int
{
    return DB::table('entradas')->insertGetId([
        'empresa_id' => $env->empresa->id, 'almacen_id' => $env->almacen->id, 'user_id' => $env->admin->id,
        'numero_documento' => 'F-TARDE', 'tipo' => 'compra', 'fecha' => $fecha, 'estado' => 'confirmado',
        'total' => $total, 'monto_pagado' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('un día cerrado sin cambios no se marca', function () {
    $b = ccConfirmar($this, $this->ayer);

    $r = $this->cambios->resumen($b);

    expect($r['verificable'])->toBeTrue();
    expect($r['diferencia'])->toBe(0.0);
    expect($r['relevante'])->toBeFalse();
});

it('una compra cargada después del cierre con fecha de ese día se detecta y se explica', function () {
    $b = ccConfirmar($this, $this->ayer);
    $lineasAntes = $b->items()->count();

    $this->travel(10)->minutes();
    ccCompra($this->env, $this->ayer, 1500);

    $r = $this->cambios->analizar($b);

    expect($r['diferencia_patrimonio'])->toBe(-1500.0);   // más deuda con proveedores
    expect($r['relevante'])->toBeTrue();
    $cxp = collect($r['categorias'])->firstWhere('categoria', 'cxp');
    expect($cxp['diferencia'])->toBe(1500.0);
    expect($cxp['sin_documento'])->toBeFalse();
    expect($cxp['documentos'][0]['tipo'])->toBe('Compra registrada después del cierre');
    expect($cxp['documentos'][0]['documento'])->toBe('F-TARDE');

    // Solo lectura: el día cerrado quedó intacto.
    expect($b->fresh()->items()->count())->toBe($lineasAntes);
    expect((float) $b->fresh()->balance_neto)->toBe((float) $b->balance_neto);
});

it('una deuda eliminada después del cierre se explica con su nombre y motivo', function () {
    $deuda = Deuda::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id,
        'direccion' => 'por_pagar', 'tipo' => 'personal', 'nombre' => 'Préstamo de prueba',
        'monto_original' => 800, 'saldo' => 800, 'fecha_inicio' => $this->ayer, 'estado' => 'activa',
    ]);
    $b = ccConfirmar($this, $this->ayer);

    $this->travel(10)->minutes();
    AuditoriaService::log('deuda.eliminada', $deuda, [
        'motivo'   => 'mal registrado',
        'snapshot' => ['nombre' => $deuda->nombre, 'fecha_inicio' => $this->ayer, 'monto_original' => 800],
    ]);
    $deuda->delete();

    $r = $this->cambios->analizar($b);

    expect($r['diferencia_patrimonio'])->toBe(800.0);
    $linea = collect($r['lineas'])->firstWhere('descripcion', 'Préstamo de prueba');
    expect($linea['guardado'])->toBe(800.0);
    expect($linea['actual'])->toBe(0.0);
    $doc = collect(collect($r['categorias'])->firstWhere('categoria', 'deuda')['documentos'])->first();
    expect($doc['tipo'])->toContain('eliminado');
    expect($doc['tipo'])->toContain('mal registrado');
    expect($doc['documento'])->toBe('Préstamo de prueba');
});

it('una diferencia menor al umbral no marca el día', function () {
    $b = ccConfirmar($this, $this->ayer);
    $this->travel(10)->minutes();
    ccCompra($this->env, $this->ayer, 40);

    $r = $this->cambios->resumen($b);

    expect($r['diferencia'])->toBe(-40.0);
    expect($r['relevante'])->toBeFalse();
});

it('los endpoints de la lista, del modal y de la variación separada responden', function () {
    $b = ccConfirmar($this, $this->ayer);
    $this->travel(10)->minutes();
    ccCompra($this->env, $this->ayer, 1500);

    $lista = $this->getJson(route('finanzas.balance.verificacion', ['ids' => $b->id]))->assertOk()->json();
    expect($lista[$b->id]['relevante'])->toBeTrue();

    $modal = $this->getJson(route('finanzas.balance.cambios-cierre', $this->ayer))->assertOk()->json();
    expect((float) $modal['diferencia_patrimonio'])->toBe(-1500.0);

    $hoy = $this->get(route('finanzas.balance.show', now()->toDateString()))->original->getData()['page']['props'];
    expect($hoy['cambiosDiaAnterior']['fecha'])->toBe($this->ayer);
    expect((float) $hoy['cambiosDiaAnterior']['diferencia'])->toBe(-1500.0);
});
