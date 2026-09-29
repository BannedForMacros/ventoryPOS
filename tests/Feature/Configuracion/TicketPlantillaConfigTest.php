<?php

use App\Models\Cliente;
use App\Models\Venta;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Pantalla Configuración → Ticket y los datos del cliente pedidos al vender.
 * Todo es opcional por empresa: sin activar nada, el sistema queda como antes.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function tcVenta($test, array $extra = []): array
{
    $p = $test->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    return array_merge([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 20]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $extra);
}

it('la pantalla abre con la plantilla estándar y muestra cómo quedaría la detallada', function () {
    $props = $this->get(route('configuracion.ticket.index'))->assertOk()->original->getData()['page']['props'];

    expect($props['plantilla']['plantilla'])->toBe('estandar')
        ->and($props['posDatosCliente'])->toBeFalse()
        ->and(array_column($props['vistaPrevia'], 'clave'))->toBe(['pagada', 'saldo', 'cotizacion'])
        ->and(array_column($props['catalogo']['plantillas'], 'clave'))->toBe(['estandar', 'detallada']);

    // La muestra lleva el nombre real del negocio
    $texto = json_encode($props['vistaPrevia'][0]['bloques'], JSON_UNESCAPED_UNICODE);
    expect($texto)->toContain(mb_strtoupper($this->env->empresa->nombre_comercial));
});

it('guarda la plantilla completa y descarta lo que no existe', function () {
    $this->put(route('configuracion.ticket.update'), [
        'plantilla' => 'detallada',
        'secciones' => [
            ['clave' => 'negocio', 'activa' => true],
            ['clave' => 'inventada', 'activa' => true],
            ['clave' => 'cliente', 'activa' => false],
            ['clave' => 'items', 'activa' => false],   // fija
        ],
        'textos'   => ['pagado' => '  CANCELADO  ', 'por_cancelar' => ''],
        'opciones' => ['banda_pagado' => false],
        'pos_datos_cliente' => true,
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $empresa = $this->env->empresa->fresh();
    $pl = $empresa->ticket_plantilla;

    expect($empresa->pos_datos_cliente)->toBeTrue()
        ->and($pl['plantilla'])->toBe('detallada')
        ->and(array_column($pl['secciones'], 'clave'))->not->toContain('inventada')
        ->and(collect($pl['secciones'])->firstWhere('clave', 'cliente')['activa'])->toBeFalse()
        ->and(collect($pl['secciones'])->firstWhere('clave', 'items')['activa'])->toBeTrue()
        ->and($pl['textos']['pagado'])->toBe('CANCELADO')
        ->and($pl['textos']['por_cancelar'])->toBe('POR CANCELAR')   // vacío vuelve al texto por defecto
        ->and($pl['opciones']['banda_pagado'])->toBeFalse()
        ->and($pl['opciones']['cliente_recuadro'])->toBeTrue();
});

it('la vista previa refleja los cambios sin guardarlos', function () {
    $muestras = $this->postJson(route('configuracion.ticket.vista-previa'), [
        'plantilla' => 'detallada',
        'textos'    => ['por_cancelar' => 'DEBE', 'por_cancelar_detalle' => 'COBRAR EN OBRA'],
    ])->assertOk()->json('vistaPrevia');

    $banda = collect($muestras[1]['bloques'])->firstWhere('tipo', 'banda');
    expect(array_column($banda['lineas'], 'texto'))->toBe(['DEBE', 'COBRAR EN OBRA', 'S/ 572.50']);
    expect($this->env->empresa->fresh()->ticket_plantilla)->toBeNull();

    $this->postJson(route('configuracion.ticket.vista-previa'), ['plantilla' => 'otra'])->assertStatus(422);
});

it('la venta guarda los datos con que se atendió y completa la ficha vacía del cliente', function () {
    $sinDatos = Cliente::create(['empresa_id' => $this->env->empresa->id, 'nombres' => 'Juan', 'tipo_documento' => 'DNI', 'numero_documento' => '11111111', 'activo' => true]);
    $conDatos = Cliente::create(['empresa_id' => $this->env->empresa->id, 'nombres' => 'Ana', 'tipo_documento' => 'DNI', 'numero_documento' => '22222222',
        'telefono' => '900000001', 'direccion' => 'Su casa', 'activo' => true]);
    $servicio = app(VentaService::class);

    $v1 = $servicio->crear(tcVenta($this, ['cliente_id' => $sinDatos->id, 'cliente_telefono' => ' 979 555 012 ', 'cliente_direccion' => 'Obra Pomalca', 'observacion' => 'Tocar el timbre']), $this->env->admin, $this->turno);
    $v2 = $servicio->crear(tcVenta($this, ['cliente_id' => $conDatos->id, 'cliente_direccion' => 'Obra Tumán']), $this->env->admin, $this->turno);
    $v3 = $servicio->crear(tcVenta($this, ['cliente_id' => $this->env->clienteGeneral->id, 'cliente_telefono' => '955555555']), $this->env->admin, $this->turno);

    expect($v1->cliente_telefono)->toBe('979 555 012')
        ->and($v1->cliente_direccion)->toBe('Obra Pomalca')
        ->and($v1->observacion)->toBe('Tocar el timbre');

    // Ficha vacía: se completa. Ficha con datos: no se pisa. Cliente general: nunca.
    expect($sinDatos->fresh()->only(['telefono', 'direccion']))->toBe(['telefono' => '979 555 012', 'direccion' => 'Obra Pomalca']);
    expect($conDatos->fresh()->direccion)->toBe('Su casa')
        ->and($v2->cliente_direccion)->toBe('Obra Tumán');
    expect($this->env->clienteGeneral->fresh()->telefono)->toBeNull()
        ->and($v3->cliente_telefono)->toBe('955555555');
});

it('editar una venta desde un POS que no pide los datos no los borra', function () {
    $servicio = app(VentaService::class);
    $venta = $servicio->crear(tcVenta($this, ['cliente_telefono' => '979555012', 'observacion' => 'Tocar el timbre']), $this->env->admin, $this->turno);

    $servicio->actualizar($venta, tcVenta($this), $this->env->admin);

    $venta = Venta::find($venta->id);
    expect($venta->cliente_telefono)->toBe('979555012')
        ->and($venta->observacion)->toBe('Tocar el timbre');

    $servicio->actualizar($venta, tcVenta($this, ['cliente_telefono' => null, 'observacion' => 'Otra']), $this->env->admin);
    expect(Venta::find($venta->id)->only(['cliente_telefono', 'observacion']))->toBe(['cliente_telefono' => null, 'observacion' => 'Otra']);
});

it('el usuario guarda su celular y el POS sabe si debe pedir los datos', function () {
    $u = $this->env->admin;
    $this->put(route('configuracion.usuarios.update', $u->id), [
        'name' => $u->name, 'email' => $u->email, 'rol_id' => $u->rol_id, 'local_id' => $u->local_id, 'activo' => true,
        'telefono' => '974 123 456',
    ])->assertSessionHasNoErrors();
    expect($u->fresh()->telefono)->toBe('974 123 456');

    $this->put(route('configuracion.usuarios.update', $u->id), [
        'name' => $u->name, 'email' => $u->email, 'rol_id' => $u->rol_id, 'local_id' => $u->local_id, 'activo' => true,
        'telefono' => 'llámame',
    ])->assertSessionHasErrors('telefono');

    $props = fn () => $this->get(route('pos.index'))->original->getData()['page']['props'];
    expect($props()['pideDatosCliente'])->toBeFalse();
    $this->env->empresa->update(['pos_datos_cliente' => true]);
    expect($props()['pideDatosCliente'])->toBeTrue();
});
