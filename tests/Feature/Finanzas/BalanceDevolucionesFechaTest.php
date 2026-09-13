<?php

use App\Models\Cliente;
use App\Models\Proveedor;
use App\Services\BalanceDiarioService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Balance A LA FECHA con devoluciones.
 *
 * Un adelanto a proveedor o un anticipo de cliente DEVUELTO es un evento real
 * posterior: hasta el día de la devolución existía y debe contar. Antes el
 * balance aplicaba el estado de hoy hacia atrás y lo borraba de todas las
 * fechas pasadas (FERRONOR −33,963 en HYC, anticipo #202 +1,890 en decenas de
 * días). Un ANULADO, en cambio, es un registro erróneo y no cuenta nunca.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
});

function bdLineas(BalanceDiarioService $svc, TestEnv $env, string $fecha, string $categoria): float
{
    return (float) collect($svc->calcularLineas($env->empresa->id, $fecha))
        ->where('categoria', $categoria)->sum('monto');
}

function bdAuditarDevolucion(TestEnv $env, string $accion, int $modeloId, string $cuando): void
{
    DB::table('auditoria')->insert([
        'empresa_id' => $env->empresa->id, 'user_id' => $env->admin->id, 'user_name' => 'test',
        'accion' => $accion, 'modelo_id' => $modeloId, 'created_at' => $cuando,
    ]);
}

it('un adelanto devuelto cuenta en los balances anteriores a su devolución', function () {
    $prov = Proveedor::create([
        'empresa_id' => $this->env->empresa->id, 'razon_social' => 'FERRONOR TEST',
        'tipo_documento' => 'RUC', 'numero_documento' => '20999999991', 'activo' => true,
    ]);
    $id = DB::table('proveedor_adelantos')->insertGetId([
        'empresa_id' => $this->env->empresa->id, 'proveedor_id' => $prov->id, 'user_id' => $this->env->admin->id,
        'fecha' => '2026-09-02', 'monto' => 25544.32, 'saldo' => 25544.32, 'estado' => 'devuelto',
        'created_at' => '2026-09-02 19:14:11', 'updated_at' => '2026-09-06 01:30:22',
    ]);
    bdAuditarDevolucion($this->env, 'adelanto_proveedor.devuelto', $id, '2026-09-06 01:30:22');

    expect(bdLineas($this->balances, $this->env, '2026-09-04', 'adelanto_proveedor'))->toBe(25544.32);
    expect(bdLineas($this->balances, $this->env, '2026-09-06', 'adelanto_proveedor'))->toBe(0.0);
});

it('un anticipo devuelto cuenta como pasivo hasta el día de su devolución', function () {
    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Cliente', 'apellidos' => 'Devuelto',
        'tipo_documento' => 'DNI', 'numero_documento' => '44444444', 'activo' => true,
    ]);
    $id = DB::table('cliente_anticipos')->insertGetId([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $cliente->id, 'user_id' => $this->env->admin->id,
        'fecha' => '2026-06-29', 'monto' => 3150, 'saldo' => 1890, 'estado' => 'devuelto',
        'created_at' => '2026-06-29 10:00:00', 'updated_at' => '2026-09-01 10:02:27',
    ]);
    bdAuditarDevolucion($this->env, 'anticipo_cliente.devuelto', $id, '2026-09-01 10:02:27');

    expect(bdLineas($this->balances, $this->env, '2026-08-15', 'anticipo_cliente'))->toBe(1890.0);
    expect(bdLineas($this->balances, $this->env, '2026-09-01', 'anticipo_cliente'))->toBe(0.0);
});

it('un adelanto anulado (registro erróneo) no cuenta en ninguna fecha', function () {
    $prov = Proveedor::create([
        'empresa_id' => $this->env->empresa->id, 'razon_social' => 'ERROR TEST',
        'tipo_documento' => 'RUC', 'numero_documento' => '20999999992', 'activo' => true,
    ]);
    DB::table('proveedor_adelantos')->insert([
        'empresa_id' => $this->env->empresa->id, 'proveedor_id' => $prov->id, 'user_id' => $this->env->admin->id,
        'fecha' => '2026-09-02', 'monto' => 5000, 'saldo' => 5000, 'estado' => 'anulado',
        'created_at' => '2026-09-02 10:00:00', 'updated_at' => '2026-09-05 10:00:00',
    ]);

    expect(bdLineas($this->balances, $this->env, '2026-09-03', 'adelanto_proveedor'))->toBe(0.0);
});
