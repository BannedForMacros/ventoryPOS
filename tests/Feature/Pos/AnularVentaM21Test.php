<?php

use App\Models\Auditoria;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env    = TestEnv::crear();
    $this->turno  = $this->env->abrirTurno();
    $this->ventas = app(VentaService::class);
    $this->actingAs($this->env->admin);
});

function ventaSimple(TestEnv $env, $turno): \App\Models\Venta
{
    $producto = $env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10, 'incluye_igv' => false]);
    return app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => 20,
        ]],
    ], $env->admin, $turno);
}

it('anular SIN motivo da 422 con error de validación', function () {
    $venta = ventaSimple($this->env, $this->turno);

    $this->post(route('ventas.anular', $venta), [])
        ->assertSessionHasErrors('motivo');

    expect($venta->fresh()->estado)->toBe('completada');
});

it('anular con motivo demasiado corto (< 10 chars) da 422', function () {
    $venta = ventaSimple($this->env, $this->turno);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'corto'])
        ->assertSessionHasErrors('motivo');

    expect($venta->fresh()->estado)->toBe('completada');
});

it('anular con motivo válido completa la anulación y guarda el motivo en auditoría', function () {
    $venta = ventaSimple($this->env, $this->turno);

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'Cliente cambió de opinión antes de salir',
    ])->assertRedirect();

    expect($venta->fresh()->estado)->toBe('anulada');

    $audit = Auditoria::where('accion', 'venta.anulada')
        ->where('modelo_id', $venta->id)
        ->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->contexto['motivo'])->toBe('Cliente cambió de opinión antes de salir');
});

it('VentaService::anular llamado directamente (sin controller) acepta motivo opcional', function () {
    // Llamada interna / scripts admin: motivo puede ser null y queda registrado como tal.
    $venta = ventaSimple($this->env, $this->turno);

    $this->ventas->anular($venta, $this->env->admin); // sin motivo

    $audit = Auditoria::where('accion', 'venta.anulada')
        ->where('modelo_id', $venta->id)
        ->latest('id')->first();
    expect($audit->contexto)->toHaveKey('motivo');
    expect($audit->contexto['motivo'])->toBeNull();
});
