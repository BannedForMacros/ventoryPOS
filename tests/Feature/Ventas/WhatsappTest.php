<?php

use App\Models\Auditoria;
use App\Models\DescuentoLog;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

it('urlAprobacion devuelve URL de WhatsApp, marca notificacion_enviada y registra auditoría', function () {
    // Necesito una venta + descuento log
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante'      => 'ticket',
        'cliente_id'            => $this->env->clienteGeneral->id, // descuentos_log.cliente_id es NOT NULL
        'descuento_total'       => 20,
        'descuento_concepto_id' => $this->env->descuentoConcepto->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 80,
        ]],
    ], $this->env->admin, $this->turno);

    $log = DescuentoLog::where('venta_id', $venta->id)->firstOrFail();

    $response = $this->postJson(route('whatsapp.aprobacion'), [
        'descuento_log_id'    => $log->id,
        'telefono_supervisor' => '+51999888777',
    ]);

    $response->assertOk();
    expect($response->json('url'))->toStartWith('https://wa.me/51999888777');

    expect($log->fresh()->notificacion_enviada)->toBeTrue();
    expect(Auditoria::where('accion', 'whatsapp.aprobacion_solicitada')->where('modelo_id', $log->id)->exists())
        ->toBeTrue();
});

it('un usuario SIN permiso ventas.crear recibe 403', function () {
    // Quito permiso de admin y le doy un rol con 0 permisos
    $rolSinPermiso = \App\Models\Rol::create([
        'empresa_id'  => $this->env->empresa->id,
        'nombre'      => 'Sin permisos',
        'descripcion' => 'Vacío',
        'es_admin'    => false,
        'activo'      => true,
    ]);
    $this->env->admin->update(['rol_id' => $rolSinPermiso->id]);

    $this->withoutExceptionHandling();
    try {
        $this->postJson(route('whatsapp.aprobacion'), [
            'descuento_log_id'    => 1,
            'telefono_supervisor' => '+51999888777',
        ]);
        $this->fail('Debió abortar con 403');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('urlConfirmacion responde 422 si el cliente no tiene teléfono y NO audita', function () {
    $cliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '12345678',
        'nombres'          => 'Juan',
        'apellidos'        => 'Perez',
        'telefono'         => null,
        'activo'           => true,
    ]);
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'incluye_igv' => false]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
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
    ], $this->env->admin, $this->turno);

    $response = $this->postJson(route('whatsapp.confirmacion', $venta));
    $response->assertStatus(422);

    expect(Auditoria::where('accion', 'whatsapp.confirmacion_enviada')->where('modelo_id', $venta->id)->exists())
        ->toBeFalse();
});

it('urlConfirmacion audita cuando el cliente sí tiene teléfono', function () {
    $cliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '87654321',
        'nombres'          => 'Maria',
        'apellidos'        => 'Lopez',
        'telefono'         => '+51977666555',
        'activo'           => true,
    ]);
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'incluye_igv' => false]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 50,
        ]],
    ], $this->env->admin, $this->turno);

    $this->postJson(route('whatsapp.confirmacion', $venta))->assertOk();

    expect(Auditoria::where('accion', 'whatsapp.confirmacion_enviada')->where('modelo_id', $venta->id)->exists())
        ->toBeTrue();
});
