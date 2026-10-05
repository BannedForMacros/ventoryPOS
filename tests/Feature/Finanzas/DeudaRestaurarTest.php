<?php

use App\Models\Auditoria;
use App\Models\Deuda;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Papelera de deudas: eliminar guarda un respaldo completo y "Restaurar" la
 * devuelve idéntica (mismo id, mismos movimientos, el dinero en sus cuentas
 * y con sus fechas). Caso real: el préstamo de HYC borrado por error.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

/** Estado comparable de la deuda y su dinero (sin timestamps de auditoría). */
function fotoDeuda(int $deudaId): array
{
    $pagoIds = DB::table('deuda_pagos')->where('deuda_id', $deudaId)->pluck('id');

    return [
        'deuda' => (array) DB::table('deudas')->where('id', $deudaId)->first(),
        'pagos' => DB::table('deuda_pagos')->where('deuda_id', $deudaId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'movs'  => DB::table('cuenta_movimientos')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('ref_tipo', 'deuda')->where('ref_id', $deudaId))
                ->orWhere(fn ($w) => $w->where('ref_tipo', 'deuda_pago')->whereIn('ref_id', $pagoIds)))
            ->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
    ];
}

it('elimina una deuda con su dinero y la restaura idéntica', function () {
    $efectivo = $this->env->metodo('efectivo')->id;

    $this->post(route('finanzas.deudas.store'), [
        'direccion' => 'por_pagar', 'tipo' => 'bancaria', 'nombre' => 'Préstamo para pagar a proveedor',
        'monto_original' => 1000, 'fecha_inicio' => now()->subDays(3)->toDateString(),
        'registrar_caja' => true, 'metodo_pago_id' => $efectivo,
    ])->assertSessionHasNoErrors();
    $deuda = Deuda::where('nombre', 'Préstamo para pagar a proveedor')->firstOrFail();

    $this->post(route('finanzas.deudas.pago', $deuda), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 250, 'metodo_pago_id' => $efectivo,
    ])->assertSessionHasNoErrors();

    $antes = fotoDeuda($deuda->id);
    expect($antes['movs'])->toHaveCount(2); // desembolso + cuota

    // Antes de eliminar se ve qué pasa con el dinero.
    $impacto = $this->getJson(route('finanzas.deudas.impacto-eliminar', $deuda))->assertOk()->json();
    expect($impacto['movimientos'])->toHaveCount(2);
    expect((float) collect($impacto['movimientos'])->sum('efecto'))->toBe(-750.0); // sale el préstamo, vuelve la cuota

    $this->delete(route('finanzas.deudas.destroy', $deuda), ['motivo' => 'Borrada por error'])->assertSessionHasNoErrors();
    expect(Deuda::find($deuda->id))->toBeNull();
    expect(fotoDeuda($deuda->id)['movs'])->toHaveCount(0);

    // Aparece en la papelera, restaurable.
    $papelera = $this->getJson(route('finanzas.deudas.eliminadas'))->assertOk()->json();
    $fila = collect($papelera)->firstWhere('deuda_id', $deuda->id);
    expect($fila['restaurable'])->toBeTrue();
    expect($fila['motivo'])->toBe('Borrada por error');

    $this->post(route('finanzas.deudas.restaurar', $fila['auditoria_id']), ['motivo' => 'Se borró la equivocada'])
        ->assertSessionHasNoErrors();

    // Idéntica: mismas filas, mismo id, el dinero de vuelta con sus fechas.
    expect(fotoDeuda($deuda->id))->toEqual($antes);
    expect(collect($this->getJson(route('finanzas.deudas.eliminadas'))->json())->firstWhere('deuda_id', $deuda->id))->toBeNull();
    expect(Auditoria::where('accion', 'deuda.restaurada')->where('modelo_id', $deuda->id)->exists())->toBeTrue();

    // No se restaura dos veces.
    $this->post(route('finanzas.deudas.restaurar', $fila['auditoria_id']), ['motivo' => 'Otra vez por si acaso'])
        ->assertRedirect();
    expect(DB::table('deudas')->where('id', $deuda->id)->count())->toBe(1);
    expect(fotoDeuda($deuda->id))->toEqual($antes);
});

it('no restaura una deuda de otra empresa', function () {
    $otra = TestEnv::crear();
    $deuda = Deuda::create([
        'empresa_id' => $otra->empresa->id, 'user_id' => $otra->admin->id, 'direccion' => 'por_pagar',
        'tipo' => 'otro', 'nombre' => 'Ajena', 'monto_original' => 100, 'saldo' => 100,
        'fecha_inicio' => now()->toDateString(), 'estado' => 'activa',
    ]);
    $this->actingAs($otra->admin)->delete(route('finanzas.deudas.destroy', $deuda), ['motivo' => 'Prueba ajena'])
        ->assertSessionHasNoErrors();
    $log = Auditoria::where('accion', 'deuda.eliminada')->where('modelo_id', $deuda->id)->firstOrFail();

    $this->actingAs($this->env->admin)
        ->post(route('finanzas.deudas.restaurar', $log->id), ['motivo' => 'Intento cruzado'])
        ->assertNotFound();
    expect(Deuda::find($deuda->id))->toBeNull();
});
