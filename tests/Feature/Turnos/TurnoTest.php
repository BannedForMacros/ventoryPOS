<?php

use App\Models\Auditoria;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use App\Models\Turno;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

/**
 * Helper: registra una venta efectivo del monto indicado en el turno dado.
 * Crea producto + venta + payment de un solo plumazo para no contaminar los tests.
 */
function ventaEfectivo(TestEnv $env, Turno $turno, float $monto): \App\Models\Venta
{
    // Producto exonerado para que precio == total (sin IGV en juego)
    $producto = $env->crearProducto(['precio_venta' => $monto, 'incluye_igv' => false]);
    return app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => $monto,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => $monto,
        ]],
    ], $env->admin, $turno);
}

it('abrir un turno deja estado=abierto y registra el monto de apertura', function () {
    $turno = $this->env->abrirTurno(apertura: 150);

    expect($turno->estado)->toBe('abierto');
    expect((float) $turno->monto_apertura)->toBe(150.0);
    expect($turno->fecha_apertura)->not->toBeNull();
    expect($turno->fecha_cierre)->toBeNull();
});

it('calcularMontoEsperado: apertura + ventas efectivo - gastos', function () {
    $turno = $this->env->abrirTurno(apertura: 100);

    ventaEfectivo($this->env, $turno, 50);
    ventaEfectivo($this->env, $turno, 30);

    // Gasto de 20 (asumimos que sale de caja efectivo)
    $tipo = GastoTipo::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Operativo',
        'categoria'  => 'operativo',
        'activo'     => true,
    ]);
    $concepto = GastoConcepto::create([
        'empresa_id'    => $this->env->empresa->id,
        'gasto_tipo_id' => $tipo->id,
        'nombre'        => 'Útiles',
        'activo'        => true,
    ]);
    Gasto::create([
        'empresa_id'        => $this->env->empresa->id,
        'local_id'          => $this->env->local->id,
        'turno_id'          => $turno->id,
        'user_id'           => $this->env->admin->id,
        'gasto_tipo_id'     => $tipo->id,
        'gasto_concepto_id' => $concepto->id,
        'monto'             => 20,
        'fecha'             => now(),
    ]);

    // Esperado = 100 (apertura) + 50 + 30 (ventas efectivo) - 20 (gastos) = 160
    expect($turno->fresh()->calcularMontoEsperado())->toBe(160.0);
});

it('cerrar turno SIN diferencia (declarado == esperado) deja diferencia=0', function () {
    $turno = $this->env->abrirTurno(apertura: 100);
    ventaEfectivo($this->env, $turno, 80);
    // Esperado: 100 + 80 = 180

    $this->post(route('turnos.cerrar', $turno), [
        'arqueo' => [
            ['denominacion' => 100, 'cantidad' => 1],
            ['denominacion' => 50,  'cantidad' => 1],
            ['denominacion' => 20,  'cantidad' => 1],
            ['denominacion' => 10,  'cantidad' => 1],
        ],
        'arqueo_metodos' => [],
        'observacion_cierre' => null,
    ])->assertRedirect(route('turnos.index'));

    $turno->refresh();
    expect($turno->estado)->toBe('cerrado');
    expect((float) $turno->monto_cierre_declarado)->toBe(180.0);
    expect((float) $turno->monto_cierre_esperado)->toBe(180.0);
    expect((float) $turno->diferencia)->toBe(0.0);
});

it('cerrar turno CON diferencia (sobrante) la guarda en positivo', function () {
    $turno = $this->env->abrirTurno(apertura: 100);
    ventaEfectivo($this->env, $turno, 50);
    // Esperado: 150

    $this->post(route('turnos.cerrar', $turno), [
        'arqueo' => [
            ['denominacion' => 100, 'cantidad' => 1],
            ['denominacion' => 50,  'cantidad' => 1],
            ['denominacion' => 10,  'cantidad' => 1], // declara 160, sobrante 10
        ],
        'arqueo_metodos' => [],
    ])->assertRedirect(route('turnos.index'));

    $turno->refresh();
    expect((float) $turno->monto_cierre_declarado)->toBe(160.0);
    expect((float) $turno->monto_cierre_esperado)->toBe(150.0);
    expect((float) $turno->diferencia)->toBe(10.0);
});

it('cerrar turno CON diferencia (faltante) la guarda en negativo', function () {
    $turno = $this->env->abrirTurno(apertura: 100);
    ventaEfectivo($this->env, $turno, 50);
    // Esperado: 150

    $this->post(route('turnos.cerrar', $turno), [
        'arqueo' => [
            ['denominacion' => 100, 'cantidad' => 1],
            ['denominacion' => 20,  'cantidad' => 2], // declara 140, faltante 10
        ],
        'arqueo_metodos' => [],
    ])->assertRedirect(route('turnos.index'));

    $turno->refresh();
    expect((float) $turno->diferencia)->toBe(-10.0);
});

it('reabrir un turno cerrado limpia los campos de cierre y registra auditoría', function () {
    $turno = $this->env->abrirTurno(apertura: 100);
    ventaEfectivo($this->env, $turno, 50);

    // cerrar
    $this->post(route('turnos.cerrar', $turno), [
        'arqueo' => [['denominacion' => 100, 'cantidad' => 1], ['denominacion' => 50, 'cantidad' => 1]],
        'arqueo_metodos' => [],
    ]);
    expect($turno->fresh()->estado)->toBe('cerrado');

    // reabrir (A8: motivo ahora es obligatorio)
    $this->post(route('turnos.reabrir', $turno), [
        'motivo' => 'Diferencia mal contada, recontamos',
    ])->assertRedirect();

    $turno->refresh();
    expect($turno->estado)->toBe('abierto');
    expect($turno->fecha_cierre)->toBeNull();
    expect($turno->user_cierre_id)->toBeNull();
    expect($turno->monto_cierre_declarado)->toBeNull();
    expect($turno->diferencia)->toBeNull();
    expect($turno->arqueo)->toHaveCount(0);

    expect(Auditoria::where('accion', 'turno.reabierto')->where('modelo_id', $turno->id)->exists())
        ->toBeTrue();
});

it('un cajero no-admin no puede reabrir un turno (403)', function () {
    // Forzamos que el rol del admin actual no sea admin
    $this->env->rolAdmin->update(['es_admin' => false]);
    $turno = $this->env->abrirTurno(apertura: 100);
    $turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);

    // withoutExceptionHandling para evitar el render de Inertia del error page
    // (que requiere el manifest de Vite). Probamos directamente la excepción.
    $this->withoutExceptionHandling();
    try {
        // Mandamos motivo válido para que la validación pase y la negativa
        // venga del check de rol (no del FormRequest).
        $this->post(route('turnos.reabrir', $turno), [
            'motivo' => 'Quiero reabrir como cajero',
        ]);
        $this->fail('Debió abortar con 403');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});
