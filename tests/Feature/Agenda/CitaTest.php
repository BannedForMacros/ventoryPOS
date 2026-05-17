<?php

use App\Models\Cita;
use App\Models\Stock;
use App\Services\CitaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env  = TestEnv::crear();
    $this->svc  = app(CitaService::class);
    $this->actingAs($this->env->admin);
});

it('crear una cita guarda items y deja estado=programada', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 30, 'nombre' => 'Corte de pelo']);

    $cita = $this->svc->crear([
        'local_id'    => $this->env->local->id,
        'cliente_id'  => $this->env->clienteGeneral->id,
        'fecha_hora'  => now()->addDay()->toDateTimeString(),
        'observaciones' => 'Sin observaciones',
        'sujeto_nombre' => 'Firulais',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'duracion_min'       => 45,
        ]],
    ], $this->env->admin);

    expect($cita->estado)->toBe(Cita::ESTADO_PROGRAMADA);
    expect($cita->numero)->toStartWith('C-');
    expect($cita->items)->toHaveCount(1);
    expect($cita->duracion_min)->toBe(45);
    expect((float) $cita->items->first()->precio_estimado)->toBe(30.0);
});

it('completarYCobrar crea la venta, descuenta stock y vincula la cita', function () {
    $turno    = $this->env->abrirTurno();
    $producto = $this->env->crearProducto(['precio_venta' => 40, 'stock_inicial' => 10, 'incluye_igv' => false]);

    $cita = $this->svc->crear([
        'local_id'   => $this->env->local->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'fecha_hora' => now()->addDay()->toDateTimeString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
        ]],
    ], $this->env->admin);

    $venta = $this->svc->completarYCobrar($cita, [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 40,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 80,
        ]],
    ], $this->env->admin, $turno);

    $cita->refresh();
    expect($cita->estado)->toBe(Cita::ESTADO_COMPLETADA);
    expect($cita->venta_id)->toBe($venta->id);
    expect((float) $venta->total)->toBe(80.0);
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(8.0);
});

it('cita ya completada no se puede volver a cobrar (LogicException)', function () {
    $turno    = $this->env->abrirTurno();
    $producto = $this->env->crearProducto(['precio_venta' => 10]);
    $cita = $this->svc->crear([
        'local_id'   => $this->env->local->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'fecha_hora' => now()->addDay()->toDateTimeString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
        ]],
    ], $this->env->admin);

    $cita->update(['estado' => Cita::ESTADO_COMPLETADA, 'completada_at' => now()]);

    $this->svc->completarYCobrar($cita->fresh(), [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $turno);
})->throws(LogicException::class);

it('al abrir POS con cita_id que contiene producto inactivo, el payload marca tiene_inactivos=true', function () {
    $turno    = $this->env->abrirTurno();
    $producto = $this->env->crearProducto(['precio_venta' => 25, 'nombre' => 'Vacuna']);
    $cita = $this->svc->crear([
        'local_id'   => $this->env->local->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'fecha_hora' => now()->addDay()->toDateTimeString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
        ]],
    ], $this->env->admin);

    // Admin desactiva el producto entre agendar y cobrar
    $producto->update(['activo' => false]);

    // Probamos via HTTP: GET /pos?cita_id=X
    $response = $this->get(route('pos.index', ['cita_id' => $cita->id]));

    // Inertia respuesta — extraemos los props enviados al frontend.
    // El test asserta que citaPrellenada.tiene_inactivos = true.
    $page = $response->original->getData()['page'];
    expect($page['props']['citaPrellenada'])->not->toBeNull();
    expect($page['props']['citaPrellenada']['tiene_inactivos'])->toBeTrue();
    expect($page['props']['citaPrellenada']['items'][0]['producto_activo'])->toBeFalse();
});

it('cancelar una cita la deja en estado=cancelada con motivo', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10]);
    $cita = $this->svc->crear([
        'local_id'   => $this->env->local->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'fecha_hora' => now()->addDay()->toDateTimeString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
        ]],
    ], $this->env->admin);

    $this->svc->cancelar($cita, 'El cliente no podía asistir', $this->env->admin);

    $cita->refresh();
    expect($cita->estado)->toBe(Cita::ESTADO_CANCELADA);
    expect($cita->motivo_cancelacion)->toBe('El cliente no podía asistir');
    expect($cita->cancelada_at)->not->toBeNull();
});
