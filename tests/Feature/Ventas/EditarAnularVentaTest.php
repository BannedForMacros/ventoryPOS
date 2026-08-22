<?php

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Stock;
use App\Models\User;
use App\Services\VentaService;
use Inertia\Testing\AssertableInertia as Assert;
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

it('el admin SÍ puede editar pasados 3 min cuando cajera_puede_editar está desactivado', function () {
    $this->env->empresa->update([
        'cajera_puede_editar' => false,
        'venta_edicion_con_contador' => false,
        'venta_edicion_minutos' => 3,
    ]);

    $turnoCajera = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turnoCajera, 2);
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(10)]); $venta->refresh();

    // El admin sin turno propio edita a 5 unidades
    $this->actingAs($this->env->admin)
        ->put(route('ventas.update', $venta), payloadEdicion(5))
        ->assertRedirect(route('ventas.show', $venta));

    expect((float) $venta->fresh()->load('items')->items->first()->cantidad)->toBe(5.0);
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

it('el admin abre el POS para editar la venta de la cajera SIN tener turno propio', function () {
    $turnoCajera = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turnoCajera, 2);
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(10)]); $venta->refresh();

    // El admin NO abre turno. Antes esto redirigía a turnos.index; ahora debe
    // cargar el POS en modo edición usando el turno de la venta.
    $this->actingAs($this->env->admin)
        ->get(route('pos.index', ['venta_id' => $venta->id]))
        ->assertInertia(fn (Assert $p) => $p
            ->component('Pos/Index')
            ->where('ventaEnEdicion.id', $venta->id)
            ->where('turno.id', $turnoCajera->id)   // usa el turno de la cajera
        );
});

it('el admin edita la venta de la cajera SIN turno propio y ajusta el stock del local de la venta', function () {
    $turnoCajera = $this->env->abrirTurno($this->cajera);
    $venta = ventaBase($this->cajera, $turnoCajera, 2); // stock 100 → 98
    \App\Models\Venta::where('id', $venta->id)->update(['created_at' => now()->subMinutes(10)]); $venta->refresh();

    // Admin sin turno propio edita a 5 unidades
    $this->actingAs($this->env->admin)
        ->put(route('ventas.update', $venta), payloadEdicion(5))
        ->assertRedirect(route('ventas.show', $venta));

    expect((float) $venta->fresh()->load('items')->items->first()->cantidad)->toBe(5.0);
    // Stock del almacén del local de la venta: 100 − 5 = 95
    expect((float) Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $this->producto->id)->value('cantidad'))->toBe(95.0);
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

it('en edición se puede cambiar una venta de contado a crédito respetando el pago inicial', function () {
    $cliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'nombres'          => 'Juan', 'apellidos' => 'Perez',
        'tipo_documento'   => 'DNI', 'numero_documento' => '12345678',
        'activo'           => true,
    ]);
    $turno = $this->env->abrirTurno($this->env->admin);
    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[ 'metodo_pago_id' => $this->efectivo->id, 'monto' => 20 ]],
    ], $this->env->admin, $turno); // total 20, pagado 20, contado

    expect($venta->es_credito)->toBeFalse();
    expect((float) $venta->saldo_pendiente)->toBe(0.0);

    $this->actingAs($this->env->admin)
        ->put(route('ventas.update', $venta), [
            'tipo_comprobante' => 'ticket',
            'cliente_id'       => $cliente->id,
            'es_credito'       => true,
            'items' => [[
                'producto_id'        => $this->producto->id,
                'producto_unidad_id' => $this->producto->unidadBase->id,
                'cantidad'           => 2,
                'precio_unitario'    => 10,
                'incluye_igv'        => true,
            ]],
            'pagos' => [[ 'metodo_pago_id' => $this->efectivo->id, 'monto' => 20 ]],
        ])
        ->assertRedirect(route('ventas.show', $venta));

    $venta->refresh();
    expect($venta->es_credito)->toBeTrue();
    expect((float) $venta->total)->toBe(20.0);
    expect((float) $venta->monto_pagado)->toBe(20.0);
    expect((float) $venta->saldo_pendiente)->toBe(0.0);
});

it('en edición NO se puede quitar el crédito a una venta que ya estaba a crédito', function () {
    $turno = $this->env->abrirTurno($this->env->admin);
    $cliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'nombres'          => 'Juan', 'apellidos' => 'Perez',
        'tipo_documento'   => 'DNI', 'numero_documento' => '12345678',
        'activo'           => true,
    ]);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'es_credito'       => true,
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [],
    ], $this->env->admin, $turno);

    expect($venta->es_credito)->toBeTrue();

    $this->actingAs($this->env->admin)
        ->put(route('ventas.update', $venta), [
            'tipo_comprobante' => 'ticket',
            'cliente_id'       => $cliente->id,
            'es_credito'       => false,
            'items' => [[
                'producto_id'        => $this->producto->id,
                'producto_unidad_id' => $this->producto->unidadBase->id,
                'cantidad'           => 2,
                'precio_unitario'    => 10,
                'incluye_igv'        => true,
            ]],
            'pagos' => [[ 'metodo_pago_id' => $this->efectivo->id, 'monto' => 20 ]],
        ])
        ->assertSessionHasErrors('es_credito');

    expect($venta->fresh()->es_credito)->toBeTrue();
});
