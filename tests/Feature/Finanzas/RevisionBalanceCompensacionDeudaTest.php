<?php

use App\Models\Deuda;
use App\Models\DeudaPago;
use App\Services\BalanceDiarioService;
use Tests\Support\TestEnv;

/**
 * Revisión del balance — P1: una compensación entre deudas POSTERIOR al corte
 * debe devolver saldo (como una amortización). Antes caía en el "ELSE" del
 * ajuste y lo RESTABA: la deuda del día anterior salía con el doble de rebaja.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
    $this->ayer = now()->subDay()->toDateString();
    $this->antier = now()->subDays(2)->toDateString();
});

it('la compensación de hoy no rebaja la deuda del balance de ayer y su detalle la nombra', function () {
    $deuda = Deuda::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id,
        'direccion' => 'por_pagar', 'tipo' => 'personal', 'nombre' => 'Préstamo RB',
        'monto_original' => 1000, 'saldo' => 1000, 'fecha_inicio' => now()->subDays(5)->toDateString(), 'estado' => 'activa',
    ]);

    // Día ya cerrado ANTES de la compensación: su foto no se toca.
    $cerrado = $this->balances->generar($this->env->admin, $this->antier);
    $this->balances->confirmar($cerrado, $this->env->admin);
    $netoCerrado = (float) $cerrado->fresh()->balance_neto;

    // HOY: se compensa 300 contra otra deuda (baja el saldo a 700).
    DeudaPago::create([
        'deuda_id' => $deuda->id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'tipo' => 'compensacion', 'monto' => 300,
    ]);
    $deuda->recalcularSaldo();
    expect((float) $deuda->fresh()->saldo)->toBe(700.0);

    // Ayer la deuda seguía en 1000 (antes salía 400: 700 − 300).
    $ayer = $this->balances->generar($this->env->admin, $this->ayer);
    expect((float) $ayer->items->firstWhere('ref_id', $deuda->id)->monto)->toBe(1000.0);

    // El detalle de la deuda etiqueta el movimiento como compensación (baja saldo).
    $json = $this->getJson(route('finanzas.balance.detalle', [
        'fecha' => now()->toDateString(), 'categoria' => 'deuda', 'ref_id' => $deuda->id,
    ]))->assertOk()->json();
    $mov = collect($json['grupos'])->flatMap(fn ($g) => $g['items'])->firstWhere('monto', 300);
    expect($mov['descripcion'])->toBe('Compensación');
    expect($mov['tipo'])->toBe('ingreso');

    // El día cerrado conserva su patrimonio guardado.
    expect((float) $this->balances->generar($this->env->admin, $this->antier)->balance_neto)->toBe($netoCerrado);
});
