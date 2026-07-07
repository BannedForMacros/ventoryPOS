<?php

use App\Models\Cliente;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('Cliente::generalDeEmpresa identifica por es_cliente_general, no por DNI mágico', function () {
    // El cliente general del TestEnv tiene DNI '99999999' y es_cliente_general=true.
    // Cambiamos el DNI a otro valor para probar que el lookup no depende del DNI.
    $this->env->clienteGeneral->update(['numero_documento' => '88888888']);

    $resuelto = Cliente::generalDeEmpresa($this->env->empresa->id);

    expect($resuelto)->not->toBeNull();
    expect($resuelto->id)->toBe($this->env->clienteGeneral->id);
});

it('un cliente con DNI 99999999 PERO sin la flag no es considerado Cliente General', function () {
    // Primero liberamos el DNI 99999999 (mover el general original a otro DNI)
    // y quitarle la flag para que el lookup falle.
    $this->env->clienteGeneral->update([
        'numero_documento'   => '11112222',
        'es_cliente_general' => false,
    ]);

    // Creamos otro cliente con DNI 99999999 pero sin flag (escenario legado).
    Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'DNI',
        'numero_documento'   => '99999999',
        'nombres'            => 'Confusor',
        'apellidos'          => '',
        'activo'             => true,
        'es_cliente_general' => false,
    ]);

    expect(Cliente::generalDeEmpresa($this->env->empresa->id))->toBeNull();
});

it('VentaService::crear cae a Cliente General resuelto por flag cuando no se pasa cliente_id', function () {
    $turno    = $this->env->abrirTurno();
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'incluye_igv' => false]);

    // Cambiamos el DNI del cliente general (deja de ser el "99999999")
    $this->env->clienteGeneral->update(['numero_documento' => '77777777']);

    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        // cliente_id omitido → debería caer al Cliente General
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 10,
        ]],
    ], $this->env->admin, $turno);

    expect($venta->cliente_id)->toBe($this->env->clienteGeneral->id);
});

it('partial unique index: solo puede haber UN Cliente General por empresa', function () {
    Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'DNI',
        'numero_documento'   => '12345678',
        'nombres'            => 'Otro',
        'apellidos'          => 'General',
        'activo'             => true,
        'es_cliente_general' => true, // ← ya hay uno, debería fallar
    ]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('ClienteController bloquea editar el Cliente General', function () {
    $this->withoutExceptionHandling();
    try {
        $this->put(route('clientes.update', $this->env->clienteGeneral), [
            'tipo_documento'   => 'DNI',
            'numero_documento' => '99999999',
            'nombres'          => 'Cambio',
            'apellidos'        => '',
        ]);
        $this->fail('Debió abortar con 403');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

// El alta de empresas por el panel quedó bloqueada (aislamiento multi-tenant):
// cualquier admin de empresa podría crear tenants ajenos. El onboarding se hace
// por seeder/tinker, que debe crear también el Cliente General con la flag.
it('EmpresaController bloquea crear empresas desde el panel', function () {
    $payload = [
        'razon_social'                    => 'Nueva SAC',
        'nombre_comercial'                => 'Nueva',
        'ruc'                             => substr('20' . str_pad((string) random_int(0, 99999999), 9, '0', STR_PAD_LEFT), 0, 11),
        'direccion'                       => 'Av. Test',
        'telefono'                        => '123',
        'email'                           => 'nueva_' . uniqid() . '@test.com',
        'activo'                          => true,
        'tasa_igv'                        => 18.00,
        'modo_almacen'                    => 'simple',
        'descuenta_stock_en_venta'        => true,
        'modo_cierre_caja'                => 'con_declaraciones',
        'modo_cierre_inventario'          => 'por_venta',
        'usa_fondos_iniciales'            => false,
        'fondos_iniciales_en_declaracion' => false,
        'permite_devoluciones'            => true,
        'dias_max_devolucion'             => 30,
        'requiere_aprobacion_devolucion'  => false,
        'restock_default'                 => true,
    ];

    $this->post(route('configuracion.empresas.store'), $payload)->assertForbidden();

    expect(\App\Models\Empresa::where('ruc', $payload['ruc'])->exists())->toBeFalse();
});

