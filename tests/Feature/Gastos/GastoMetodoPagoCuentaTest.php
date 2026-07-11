<?php

use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * El gasto se paga eligiendo un MÉTODO DE PAGO (y su cuenta si tiene), igual
 * que el POS. El backend resuelve ese método + cuenta_metodo_pago_id a una
 * cuenta_id concreta vía TesoreriaService::resolverCuenta y asienta el egreso
 * en esa cuenta. No hay columnas nuevas: se reutiliza gastos.cuenta_id.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    // Tipo + concepto de gasto
    $this->tipo = GastoTipo::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Operativo',
        'categoria'  => 'operativo',
        'activo'     => true,
    ]);
    $this->concepto = GastoConcepto::create([
        'empresa_id'    => $this->env->empresa->id,
        'gasto_tipo_id' => $this->tipo->id,
        'nombre'        => 'Movilidad',
        'activo'        => true,
    ]);
});

function postGasto(array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->from(route('gastos.index'))->post(route('gastos.store'), array_merge([
        'gasto_tipo_id'     => test()->tipo->id,
        'gasto_concepto_id' => test()->concepto->id,
        'monto'             => 40,
        'fecha'             => now()->toDateString(),
        'comentario'        => '',
        'turno_id'          => test()->turno->id,
    ], $extra));
}

it('paga un gasto con método efectivo y asienta el egreso en la cuenta efectivo', function () {
    $efectivo = $this->env->metodo('efectivo');

    $resp = postGasto(['metodo_pago_id' => $efectivo->id]);
    $resp->assertSessionHasNoErrors();

    $gasto = Gasto::where('empresa_id', $this->env->empresa->id)->latest('id')->first();
    expect($gasto)->not->toBeNull();

    // La cuenta resuelta es la caja Efectivo
    $cuentaEfectivo = Cuenta::where('empresa_id', $this->env->empresa->id)->where('es_efectivo', true)->first();
    expect($gasto->cuenta_id)->toBe($cuentaEfectivo->id);

    // Movimiento de tesorería: egreso en esa cuenta, por el monto
    $mov = CuentaMovimiento::where('ref_tipo', 'gasto')->where('ref_id', $gasto->id)->first();
    expect($mov)->not->toBeNull();
    expect($mov->tipo)->toBe('egreso');
    expect((float) $mov->monto)->toBe(40.0);
    expect($mov->cuenta_id)->toBe($cuentaEfectivo->id);
});

it('paga un gasto con método que tiene cuenta y usa esa cuenta (por cuenta_metodo_pago_id)', function () {
    // Vincular una cuenta bancaria al método "transferencia"
    $transferencia = $this->env->metodo('transferencia');
    $cuentaBanco   = Cuenta::create([
        'empresa_id'  => $this->env->empresa->id,
        'nombre'      => 'BCP Corriente',
        'es_efectivo' => false,
        'activo'      => true,
    ]);
    $pivotId = DB::table('cuenta_metodo_pago')->insertGetId([
        'cuenta_id'      => $cuentaBanco->id,
        'metodo_pago_id' => $transferencia->id,
    ]);

    $resp = postGasto([
        'metodo_pago_id'        => $transferencia->id,
        'cuenta_metodo_pago_id' => $pivotId,
    ]);
    $resp->assertSessionHasNoErrors();

    $gasto = Gasto::where('empresa_id', $this->env->empresa->id)->latest('id')->first();
    expect($gasto->cuenta_id)->toBe($cuentaBanco->id);

    $mov = CuentaMovimiento::where('ref_tipo', 'gasto')->where('ref_id', $gasto->id)->first();
    expect($mov->cuenta_id)->toBe($cuentaBanco->id);
    expect($mov->tipo)->toBe('egreso');
});

it('rechaza cuando la cuenta_metodo_pago_id no pertenece al método enviado', function () {
    $transferencia = $this->env->metodo('transferencia');
    $yape          = $this->env->metodo('yape');
    $cuentaBanco   = Cuenta::create([
        'empresa_id'  => $this->env->empresa->id,
        'nombre'      => 'BCP Corriente',
        'es_efectivo' => false,
        'activo'      => true,
    ]);
    // Pivote pertenece a transferencia...
    $pivotId = DB::table('cuenta_metodo_pago')->insertGetId([
        'cuenta_id'      => $cuentaBanco->id,
        'metodo_pago_id' => $transferencia->id,
    ]);

    // ...pero enviamos el método yape → debe fallar
    $resp = postGasto([
        'metodo_pago_id'        => $yape->id,
        'cuenta_metodo_pago_id' => $pivotId,
    ]);
    $resp->assertSessionHasErrors('cuenta_metodo_pago_id');
});

it('sigue funcionando sin método (compat): cae a efectivo', function () {
    $resp = postGasto(); // sin metodo_pago_id ni cuenta
    $resp->assertSessionHasNoErrors();

    $gasto = Gasto::where('empresa_id', $this->env->empresa->id)->latest('id')->first();
    // cuenta_id null → registrar asienta en efectivo
    $mov = CuentaMovimiento::where('ref_tipo', 'gasto')->where('ref_id', $gasto->id)->first();
    $cuentaEfectivo = Cuenta::where('empresa_id', $this->env->empresa->id)->where('es_efectivo', true)->first();
    expect($mov->cuenta_id)->toBe($cuentaEfectivo->id);
});
