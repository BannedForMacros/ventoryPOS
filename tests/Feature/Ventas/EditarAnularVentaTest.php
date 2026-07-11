<?php

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Stock;
use App\Models\User;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Edición completa de venta dentro de 3 min + anular con código tras 3 min.
 * El ADMIN no tiene restricción de tiempo ni código.
 */

beforeEach(function () {
    $this->env     = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->service = app(VentaService::class);

    // Cajera (no admin) con permiso ventas ver/crear/editar
    $rol = Rol::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre' => 'Cajera', 'descripcion' => 'Cajera', 'es_admin' => false, 'activo' => true,
    ]);
    Permiso::create([
        'rol_id' => $rol->id, 'modulo_id' => Modulo::where('slug', 'ventas')->value('id'),
        'ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => false,
    ]);
    $this->cajera = User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id,
        'rol_id' => $rol->id, 'name' => 'Cajera Uno',
        'email' => 'cajera+' . uniqid() . '@test.com', 'password' => bcrypt('secret'),
        'email_verified_at' => now(), 'activo' => true,
    ]);

    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $this->efectivo = $this->env->metodo('efectivo');
});

function ventaBase(User $user, \App\Models\Turno $turno, float $cantidad = 2): \App\Models\Venta
{
    return test()->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[ 'metodo_pago_id' => test()->efectivo->id, 'monto' => $cantidad * 10 ]],
    ], $user, $turno);
}

function payloadEdicion(float $cantidad): array
{
    return [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => 10,
            'incluye_igv'        => true,
        ]],
        'pagos' => [[ 'metodo_pago_id' => test()->efectivo->id, 'monto' => $cantidad * 10 ]],
    ];
}

it('la cajera edita su venta dentro de 3 min: recalcula stock y total', function () {
    $turno = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turno, 2); // stock 100 → 98

    expect((float) Stock::where('producto_id', $this->producto->id)->value('cantidad'))->toBe(98.0);

    // Editar a 5 unidades
    $this->actingAs($this->cajera)
        ->put(route('ventas.update', $venta), payloadEdicion(5))
        ->assertRedirect(route('ventas.show', $venta));

    $venta->refresh()->load('items');
    expect($venta->items)->toHaveCount(1);
    expect((float) $venta->items->first()->cantidad)->toBe(5.0);
    expect((float) $venta->total)->toBe(50.0);
    // Stock: se revirtió +2 (a 100) y se descontó -5 → 95
    expect((float) Stock::where('producto_id', $this->producto->id)->value('cantidad'))->toBe(95.0);
});

it('la cajera NO puede editar pasados 3 min', function () {
    $turno = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turno, 2);
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(4)]); $venta->refresh();

    $this->actingAs($this->cajera)
        ->put(route('ventas.update', $venta), payloadEdicion(5))
        ->assertSessionHasErrors('venta');

    // Sin cambios: sigue con 2 unidades
    expect((float) $venta->fresh()->load('items')->items->first()->cantidad)->toBe(2.0);
});

it('el admin SÍ puede editar pasados 3 min', function () {
    $turno = $this->env->abrirTurno($this->env->admin);
    $venta = ventaBase($this->env->admin, $turno, 2);
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(4)]); $venta->refresh();

    $this->actingAs($this->env->admin)
        ->put(route('ventas.update', $venta), payloadEdicion(3))
        ->assertRedirect(route('ventas.show', $venta));

    expect((float) $venta->fresh()->load('items')->items->first()->cantidad)->toBe(3.0);
});

it('la cajera anula dentro de 3 min sin código y se restaura stock', function () {
    $turno = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turno, 2); // stock 98

    $this->actingAs($this->cajera)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Error de digitación en la venta'])
        ->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada');
    expect((float) Stock::where('producto_id', $this->producto->id)->value('cantidad'))->toBe(100.0);
});

it('la cajera pasados 3 min necesita código de admin para anular', function () {
    $turno = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turno, 2);
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(4)]); $venta->refresh();

    // Sin código → error
    $this->actingAs($this->cajera)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Cliente devolvió la mercadería'])
        ->assertSessionHasErrors('codigo_autorizacion');
    expect($venta->fresh()->estado)->toBe('completada');

    // Con código de admin (clave 'secret' del admin de TestEnv) → ok
    $this->actingAs($this->cajera)
        ->post(route('ventas.anular', $venta), [
            'motivo' => 'Cliente devolvió la mercadería',
            'codigo_autorizacion' => 'secret',
        ])
        ->assertSessionHasNoErrors();
    expect($venta->fresh()->estado)->toBe('anulada');
});

it('el admin anula pasados 3 min sin código', function () {
    $turno = $this->env->abrirTurno($this->env->admin);
    $venta = ventaBase($this->env->admin, $turno, 2);
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(4)]); $venta->refresh();

    $this->actingAs($this->env->admin)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Ajuste administrativo del cierre'])
        ->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada');
});
