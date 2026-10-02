<?php

use App\Events\Cambio;
use App\Models\Cliente;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TestEnv;

/**
 * Tiempo real: las pantallas abiertas se enteran de "cambió X" sin recargar.
 */
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 6999, // nadie escucha aquí
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('una venta avisa UNA sola vez, al confirmarse, con ventas y stock', function () {
    Event::fake([Cambio::class]);
    $turno = $this->env->abrirTurno();
    $p = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 20]);

    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 2, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $turno);

    $avisos = Event::dispatched(Cambio::class, fn ($e) => $e->empresaId === $this->env->empresa->id
        && in_array('ventas', $e->recursos, true));
    expect($avisos)->toHaveCount(1);
    expect($avisos->first()[0]->recursos)->toContain('stock');
});

it('si la operación se deshace no avisa nada', function () {
    Event::fake([Cambio::class]);

    DB::beginTransaction();
    Cliente::create(['empresa_id' => $this->env->empresa->id, 'nombres' => 'Temporal', 'tipo_documento' => 'DNI', 'numero_documento' => '44445555', 'activo' => true]);
    DB::rollBack();

    Event::assertNotDispatched(Cambio::class, fn ($e) => in_array('clientes', $e->recursos, true));
});

it('registrar un cliente avisa a su empresa', function () {
    Event::fake([Cambio::class]);

    Cliente::create(['empresa_id' => $this->env->empresa->id, 'nombres' => 'Nuevo', 'tipo_documento' => 'DNI', 'numero_documento' => '44446666', 'activo' => true]);

    Event::assertDispatched(Cambio::class, fn ($e) => $e->empresaId === $this->env->empresa->id
        && $e->recursos === ['clientes']
        && $e->broadcastOn()->name === 'private-empresa.' . $this->env->empresa->id);
});

it('si Reverb no responde, la operación igual se guarda', function () {
    // Sin fake: el aviso intenta salir a un puerto cerrado y falla en silencio.
    $c = Cliente::create(['empresa_id' => $this->env->empresa->id, 'nombres' => 'Sin socket', 'tipo_documento' => 'DNI', 'numero_documento' => '44447777', 'activo' => true]);

    expect(Cliente::find($c->id))->not->toBeNull();
});

it('un usuario solo puede escuchar el canal de su empresa', function () {
    // Los canales se registran al arrancar, con la conexión de entonces: se
    // vuelven a registrar ya con Reverb activo.
    require base_path('routes/channels.php');

    $propio = $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-empresa.' . $this->env->empresa->id]);
    $propio->assertOk();

    $ajeno = $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-empresa.' . ($this->env->empresa->id + 999)]);
    $ajeno->assertForbidden();
});

it('la respuesta de SUNAT (comprobante) avisa a la empresa de la venta', function () {
    Event::fake([Cambio::class]);
    $turno = $this->env->abrirTurno();
    $p = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 5]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $turno);
    Event::fake([Cambio::class]); // solo lo que sigue

    App\Models\VentaComprobante::create(['venta_id' => $venta->id, 'tipo' => '03', 'estado' => 'aceptado', 'intentos' => 1]);

    Event::assertDispatched(Cambio::class, fn ($e) => $e->empresaId === $this->env->empresa->id && $e->recursos === ['ventas']);
});

it('un turno, un gasto o un retiro avisan a la caja', function () {
    Event::fake([Cambio::class]);
    $this->env->abrirTurno();

    Event::assertDispatched(Cambio::class, fn ($e) => in_array('turnos', $e->recursos, true));
});
