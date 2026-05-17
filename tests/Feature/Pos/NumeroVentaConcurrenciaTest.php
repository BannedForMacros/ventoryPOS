<?php

use App\Models\Venta;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env    = TestEnv::crear();
    $this->turno  = $this->env->abrirTurno();
    $this->ventas = app(VentaService::class);
    $this->actingAs($this->env->admin);
});

it('genera V-0001 cuando no hay ventas previas en el turno', function () {
    expect(Venta::generarNumero($this->turno->id))->toBe('V-0001');
});

it('si ya existe V-0001 en el turno, generarNumero devuelve V-0002', function () {
    Venta::create([
        'empresa_id'       => $this->env->empresa->id,
        'local_id'         => $this->env->local->id,
        'turno_id'         => $this->turno->id,
        'caja_id'          => $this->env->caja->id,
        'user_id'          => $this->env->admin->id,
        'cliente_id'       => $this->env->clienteGeneral->id,
        'numero'           => 'V-0001',
        'tipo_comprobante' => 'ticket',
        'subtotal'         => 0,
        'descuento_total'  => 0,
        'igv'              => 0,
        'total'            => 0,
        'estado'           => 'completada',
        'fecha_venta'      => now(),
    ]);

    expect(Venta::generarNumero($this->turno->id))->toBe('V-0002');
});

it('UNIQUE(turno_id, numero): insertar dos ventas con el mismo número falla a nivel BD', function () {
    Venta::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'turno_id'   => $this->turno->id,
        'caja_id'    => $this->env->caja->id,
        'user_id'    => $this->env->admin->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'numero'     => 'V-0001',
        'tipo_comprobante' => 'ticket',
        'subtotal' => 0, 'descuento_total' => 0, 'igv' => 0, 'total' => 0,
        'estado' => 'completada', 'fecha_venta' => now(),
    ]);

    // Segunda venta con el mismo número en el mismo turno → UniqueConstraintViolationException
    Venta::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'turno_id'   => $this->turno->id,
        'caja_id'    => $this->env->caja->id,
        'user_id'    => $this->env->admin->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'numero'     => 'V-0001', // ← colisión
        'tipo_comprobante' => 'ticket',
        'subtotal' => 0, 'descuento_total' => 0, 'igv' => 0, 'total' => 0,
        'estado' => 'completada', 'fecha_venta' => now(),
    ]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('VentaService::crear reintenta si un competidor toma el número correlativo entre cálculo e insert', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'incluye_igv' => false]);
    $efectivo = $this->env->metodo('efectivo');

    // Simulamos que un proceso paralelo ya insertó V-0001 antes de que el
    // service llame a Venta::create. El service va a calcular V-0001, fallar
    // por UNIQUE, y reintentar con V-0002.
    Venta::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'turno_id'   => $this->turno->id,
        'caja_id'    => $this->env->caja->id,
        'user_id'    => $this->env->admin->id,
        'cliente_id' => $this->env->clienteGeneral->id,
        'numero'     => 'V-0001',
        'tipo_comprobante' => 'ticket',
        'subtotal' => 0, 'descuento_total' => 0, 'igv' => 0, 'total' => 0,
        'estado' => 'completada', 'fecha_venta' => now(),
    ]);

    $venta = $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $efectivo->id,
            'monto'          => 10,
        ]],
    ], $this->env->admin, $this->turno);

    expect($venta->numero)->toBe('V-0002');
    expect(Venta::where('turno_id', $this->turno->id)->count())->toBe(2);
});
