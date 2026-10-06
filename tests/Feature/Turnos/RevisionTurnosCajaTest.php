<?php

use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoCancelacion;
use App\Models\ClienteAnticipoItem;
use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\TurnoRetiro;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Support\TestEnv;

/**
 * Revisión de caja/turnos: el efectivo esperado de cada cajón. Cada prueba
 * fallaba antes de su arreglo.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

function rtcCajera($test): User
{
    $rol = Rol::create(['empresa_id' => $test->env->empresa->id, 'nombre' => 'Cajera ' . uniqid(), 'es_admin' => false, 'activo' => true]);

    return User::create([
        'empresa_id' => $test->env->empresa->id, 'local_id' => $test->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Cajera', 'email' => 'cajera+' . uniqid() . '@test.com', 'password' => bcrypt('x'), 'activo' => true,
    ]);
}

function rtcEfectivo($test): Cuenta
{
    return Cuenta::firstOrCreate(
        ['empresa_id' => $test->env->empresa->id, 'es_efectivo' => true],
        ['nombre' => 'Efectivo', 'activo' => true],
    );
}

it('#14 no se reabre un turno si su dueña ya tiene otro turno abierto', function () {
    $viejo = $this->env->abrirTurno();
    $viejo->update(['estado' => 'cerrado', 'fecha_cierre' => now()->subHour(), 'fecha_apertura' => now()->subHours(5)]);
    $nuevo = $this->env->abrirTurno();

    $this->post(route('turnos.reabrir', $viejo), ['motivo' => 'corregir una venta mal cobrada'])->assertSessionHasErrors('motivo');

    expect($viejo->fresh()->estado)->toBe('cerrado');
    expect(Turno::where('user_id', $this->env->admin->id)->where('estado', 'abierto')->count())->toBe(1);
    expect(Turno::turnoActivoDelUsuario($this->env->admin->id)->id)->toBe($nuevo->id);
});

it('#15 la cancelación de un pendiente se descuenta del cajón que se eligió, no del de quien la registra', function () {
    $turnoAdmin  = $this->env->abrirTurno();
    $turnoCajera = $this->env->abrirTurno(rtcCajera($this));

    $producto = $this->env->crearProducto();
    $anticipo = ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $this->env->clienteGeneral->id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 50, 'saldo' => 30, 'tipo_valorizacion' => 'material', 'estado' => 'activo',
    ]);
    $item = ClienteAnticipoItem::create([
        'cliente_anticipo_id' => $anticipo->id, 'producto_id' => $producto->id, 'producto_nombre' => $producto->nombre,
        'unidad_nombre' => 'UND', 'cantidad' => 5, 'cantidad_pendiente' => 3, 'factor_conversion' => 1, 'precio_unitario' => 10,
    ]);
    // La registró el admin (con su turno abierto) pero eligió "Afecta caja: turno de la cajera".
    $cancel = ClienteAnticipoCancelacion::create([
        'cliente_anticipo_id' => $anticipo->id, 'cliente_anticipo_item_id' => $item->id, 'empresa_id' => $this->env->empresa->id,
        'user_id' => $this->env->admin->id, 'fecha' => now()->toDateString(), 'cantidad' => 2, 'monto' => 20,
        'motivo' => 'ya no lo quiere', 'turno_id' => $turnoCajera->id, 'caja_id' => $this->env->caja->id,
    ]);
    app(\App\Services\TesoreriaService::class)->registrar($this->env->empresa->id, rtcEfectivo($this)->id, $this->env->admin,
        now()->toDateString(), 'egreso', 20, 'Cancelación de pendiente', 'anticipo_cancelacion', $cancel->id);

    expect(round($turnoAdmin->fresh()->calcularMontoEsperado(), 2))->toBe(100.0);
    expect(round($turnoCajera->fresh()->calcularMontoEsperado(), 2))->toBe(80.0);
});

it('#17 con arrastre y caja chica en la declaración, la apertura sugerida no vuelve a contar la caja chica', function () {
    $this->env->empresa->update(['modo_apertura_caja' => 'arrastre', 'usa_fondos_iniciales' => true, 'fondos_iniciales_en_declaracion' => true]);
    $this->env->caja->update(['caja_chica_activa' => true, 'caja_chica_monto_sugerido' => 50]);

    $anterior = $this->env->abrirTurno();
    // Contó 350 en el cajón: 300 de la venta del día + los 50 de caja chica.
    $anterior->update([
        'monto_caja_chica' => 50, 'estado' => 'cerrado', 'fecha_cierre' => now()->subMinute(),
        'monto_cierre_declarado' => 350, 'monto_cierre_esperado' => 350,
    ]);

    $sugerida = Turno::aperturaSugeridaParaCaja($this->env->caja->fresh());

    // El turno nuevo declara su caja chica aparte (50): el arrastre es 300.
    expect($sugerida['origen'])->toBe('arrastre');
    expect((float) $sugerida['monto'])->toBe(300.0);
});

it('#19 un retiro pendiente se puede rechazar y su efectivo vuelve al esperado', function () {
    $this->env->empresa->update(['usa_retiros_caja' => true, 'retiro_requiere_aprobacion' => true]);
    $turno = $this->env->abrirTurno();
    $retiro = TurnoRetiro::create([
        'empresa_id' => $this->env->empresa->id, 'turno_id' => $turno->id, 'user_id' => $this->env->admin->id,
        'concepto' => TurnoRetiro::CONCEPTO_ENTREGA_ADMIN, 'monto' => 30, 'momento' => 'turno', 'estado' => 'registrado',
    ]);
    expect(round($turno->calcularMontoEsperado(), 2))->toBe(70.0);

    $this->post(route('turnos.retiros.rechazar', $retiro))->assertSessionHasNoErrors();

    expect(TurnoRetiro::withoutGlobalScope('vigentes')->find($retiro->id)->estado)->toBe(TurnoRetiro::ESTADO_ANULADO);
    expect(round($turno->fresh()->calcularMontoEsperado(), 2))->toBe(100.0);
});

it('#20 la pantalla de cierre recibe el desglose del esperado por concepto y suma exacto', function () {
    $this->env->empresa->update(['modo_cierre_caja' => 'rapido']);
    $turno = $this->env->abrirTurno();

    // Entra un anticipo en efectivo (no es venta) y sale un gasto.
    ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $this->env->clienteGeneral->id, 'user_id' => $this->env->admin->id,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id, 'turno_id' => $turno->id,
        'fecha' => now()->toDateString(), 'monto' => 40, 'saldo' => 40, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ]);
    $tipo = GastoTipo::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Operativo', 'categoria' => 'operativo', 'activo' => true]);
    $concepto = GastoConcepto::create(['empresa_id' => $this->env->empresa->id, 'gasto_tipo_id' => $tipo->id, 'nombre' => 'Movilidad', 'activo' => true]);
    Gasto::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'user_id' => $this->env->admin->id,
        'turno_id' => $turno->id, 'gasto_tipo_id' => $tipo->id, 'gasto_concepto_id' => $concepto->id,
        'monto' => 15, 'fecha' => now()->toDateString(),
    ]);

    $this->get(route('turnos.cerrar.page', $turno))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('montoEsperado', fn ($v) => abs((float) $v - 125) < 0.001)
            ->where('desgloseEsperado.apertura', fn ($v) => abs((float) $v - 100) < 0.001)
            ->where('desgloseEsperado.entradas', fn ($e) => collect($e)->contains(fn ($l) => $l['concepto'] === 'Anticipos de clientes' && abs($l['monto'] - 40) < 0.001))
            ->where('desgloseEsperado.salidas', fn ($s) => collect($s)->contains(fn ($l) => $l['concepto'] === 'Gastos' && abs($l['monto'] - 15) < 0.001))
            ->etc());
});
