<?php

use App\Models\Cliente;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Revisión de la pantalla de Ventas, la edición de despachos y la numeración
 * y el turno de las ventas de negocios sin caja.
 */

beforeEach(function () {
    $this->env      = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->service  = app(VentaService::class);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 100]);
    $this->efectivo = $this->env->metodo('efectivo');
    $this->actingAs($this->env->admin);
});

function rvtPayload(float $pagado = 20, array $extra = []): array
{
    return array_merge([
        'tipo_comprobante' => 'ticket',
        'idempotency_key'  => 'rvt-' . uniqid('', true),
        'items' => [[
            'producto_id' => test()->producto->id, 'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad' => 2, 'precio_unitario' => 10,
        ]],
        'pagos' => [['metodo_pago_id' => test()->efectivo->id, 'monto' => $pagado]],
    ], $extra);
}

function rvtCajera(): User
{
    $rol = Rol::create(['empresa_id' => test()->env->empresa->id, 'nombre' => 'Cajera', 'es_admin' => false, 'activo' => true]);
    Permiso::create([
        'rol_id' => $rol->id, 'modulo_id' => Modulo::where('slug', 'ventas')->value('id'),
        'ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => false,
    ]);

    return User::create([
        'empresa_id' => test()->env->empresa->id, 'local_id' => test()->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Cajera', 'email' => 'cajera+' . uniqid() . '@test.com', 'password' => bcrypt('x'),
        'email_verified_at' => now(), 'activo' => true,
    ]);
}

it('editar una venta de despacho en almacén la sigue tratando como despacho', function () {
    $this->env->empresa->update(['usa_despacho_almacen' => true]);
    $turno   = $this->env->abrirTurno();
    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI', 'numero_documento' => '41234567',
        'nombres' => 'Juan', 'apellidos' => 'Pérez', 'activo' => true,
    ]);
    $venta = $this->service->crear(rvtPayload(20, ['cliente_id' => $cliente->id, 'despacho_almacen' => true]), $this->env->admin, $turno);

    $edicion = $this->get(route('pos.index', ['venta_id' => $venta->id]))->viewData('page')['props']['ventaEnEdicion'];
    expect($edicion['despacho_almacen'])->toBeTrue()
        ->and($edicion['entrega_pendiente'])->toBeFalse();

    // Despacho con Cliente General: el aviso sale UNA vez, no repetido.
    $r = $this->post(route('ventas.store'), rvtPayload(20, ['despacho_almacen' => true]));
    expect(session('errors')->get('cliente_id'))->toHaveCount(1);
});

it('el filtro de hoy no limita la búsqueda ni el selector de turnos', function () {
    $hoy  = $this->env->abrirTurno();
    $viejo = $this->env->abrirTurno();
    $viejo->update(['fecha_apertura' => now()->subDays(5), 'estado' => 'cerrado']);

    $props = $this->get(route('ventas.index'))->viewData('page')['props'];
    expect($props['filters']['fechas_por_defecto'])->toBeTrue()
        ->and(collect($props['turnos'])->pluck('id')->all())->toContain($viejo->id);

    $props = $this->get(route('ventas.index', ['q' => 'V-0001']))->viewData('page')['props'];
    expect($props['filters']['fechas_por_defecto'])->toBeFalse();
});

it('el desglose de caja descuenta el vuelto del efectivo', function () {
    $turno = $this->env->abrirTurno();
    $this->service->crear(rvtPayload(50), $this->env->admin, $turno); // total 20, vuelto 30

    $resumen = $this->get(route('ventas.index'))->viewData('page')['props']['resumen'];
    $efectivo = collect($resumen['metodos'])->firstWhere('es_efectivo', true);

    expect((float) $resumen['efectivo_ventas'])->toBe(20.0)
        ->and((float) $efectivo['total'])->toBe(20.0);
});

it('la cajera no ve ventas ni cajas de otra persona cambiando el id', function () {
    $cajera     = rvtCajera();
    $turnoAdmin = $this->env->abrirTurno();
    $ventaAdmin = $this->service->crear(rvtPayload(), $this->env->admin, $turnoAdmin);

    $this->actingAs($cajera);
    $this->get(route('ventas.show', $ventaAdmin))->assertForbidden();
    $this->get(route('ventas.ticket', $ventaAdmin))->assertForbidden();

    $props = $this->get(route('ventas.index', ['turno_id' => $turnoAdmin->id]))->viewData('page')['props'];
    expect($props['resumen'])->toBeNull();
});

it('turno automático: con el turno de ayer aún abierto, la venta de hoy va al turno de hoy', function () {
    $this->env->empresa->update(['modo_turno' => 'automatico']);
    $ayer = $this->env->abrirTurno();
    $ayer->update(['fecha_apertura' => now()->subDay()]);

    $this->post(route('ventas.store'), rvtPayload())->assertSessionHasNoErrors();

    $venta = Venta::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect($venta->turno_id)->not->toBe($ayer->id)
        ->and($venta->turno->fecha_apertura->isToday())->toBeTrue();
});

it('numeración por día: un turno que cruzó la medianoche no choca con su propio número de ayer', function () {
    $this->env->empresa->update(['venta_correlativo_alcance' => 'dia']);
    $turno = $this->env->abrirTurno();
    $turno->update(['fecha_apertura' => now()->subDay()]);

    $deAyer = $this->service->crear(rvtPayload(), $this->env->admin, $turno);
    $deAyer->update(['fecha_venta' => now()->subDay()]);
    expect($deAyer->numero)->toBe('V-0001');

    $deHoy = $this->service->crear(rvtPayload(), $this->env->admin, $turno->fresh());
    expect($deHoy->numero)->toBe('V-0002');
});
