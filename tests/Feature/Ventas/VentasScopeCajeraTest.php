<?php

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Services\VentaService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * El historial de ventas debe LIMITAR a la cajera (no admin) a SUS ventas y por
 * defecto a las de HOY. El admin sigue viendo todo.
 */

beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->service = app(VentaService::class);

    // Rol cajera (no admin) con permiso ventas.ver
    $this->rolCajera = Rol::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Cajera',
        'descripcion'=> 'Cajera de mostrador',
        'es_admin'   => false,
        'activo'     => true,
    ]);
    Permiso::create([
        'rol_id'    => $this->rolCajera->id,
        'modulo_id' => Modulo::where('slug', 'ventas')->value('id'),
        'ver'       => true,
        'crear'     => true,
        'editar'    => false,
        'eliminar'  => false,
    ]);

    $this->cajera = User::create([
        'empresa_id'        => $this->env->empresa->id,
        'local_id'          => $this->env->local->id,
        'rol_id'            => $this->rolCajera->id,
        'name'              => 'Cajera Uno',
        'email'             => 'cajera+' . uniqid() . '@test.com',
        'password'          => bcrypt('secret'),
        'email_verified_at' => now(),
        'activo'            => true,
    ]);

    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 100]);
    $this->efectivo = $this->env->metodo('efectivo');
});

function ventaDe(User $user, \App\Models\Turno $turno): \App\Models\Venta
{
    return test()->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[ 'metodo_pago_id' => test()->efectivo->id, 'monto' => 10 ]],
    ], $user, $turno);
}

it('la cajera solo ve sus ventas de hoy', function () {
    // Turno + venta de la cajera (hoy)
    $turnoCajera = $this->env->abrirTurno($this->cajera);
    $ventaHoy    = ventaDe($this->cajera, $turnoCajera);

    // Otra venta de la cajera pero de AYER (debe quedar fuera por el default de hoy)
    $ventaAyer = ventaDe($this->cajera, $turnoCajera);
    $ventaAyer->update(['fecha_venta' => now()->subDay()]);

    // Venta del ADMIN (otro usuario) hoy → la cajera NO debe verla
    $turnoAdmin = $this->env->abrirTurno($this->env->admin);
    ventaDe($this->env->admin, $turnoAdmin);

    $this->actingAs($this->cajera)
        ->get(route('ventas.index'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('Ventas/Index')
            ->has('ventas.data', 1) // solo la venta de hoy de la cajera
            ->where('ventas.data.0.id', $ventaHoy->id)
        );
});

it('el admin ve todas las ventas de la empresa (sin límite de hoy ni de usuario)', function () {
    $turnoCajera = $this->env->abrirTurno($this->cajera);
    ventaDe($this->cajera, $turnoCajera);
    $vAyer = ventaDe($this->cajera, $turnoCajera);
    $vAyer->update(['fecha_venta' => now()->subDay()]);

    $turnoAdmin = $this->env->abrirTurno($this->env->admin);
    ventaDe($this->env->admin, $turnoAdmin);

    $this->actingAs($this->env->admin)
        ->get(route('ventas.index'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('Ventas/Index')
            ->has('ventas.data', 3) // 2 cajera (hoy + ayer) + 1 admin
        );
});
