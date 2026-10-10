<?php

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Restablecer una venta anulada por error: solo el administrador. Deshace lo
 * que hizo la anulación (stock, dinero, deuda) y nada más.
 */

beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $this->turno = $this->env->abrirTurno($this->env->admin);
});

function ventaAnulada($test, array $extra = [])
{
    $venta = app(VentaService::class)->crear(array_merge([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $test->producto->id, 'producto_unidad_id' => $test->producto->unidadBase->id, 'cantidad' => 4, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => 40]],
    ], $extra), $test->env->admin, $test->turno);
    app(VentaService::class)->anular($venta, $test->env->admin, 'Se anuló por error');

    return $venta->fresh();
}

function stockProducto($test): float
{
    return (float) DB::table('stock')->where('producto_id', $test->producto->id)->sum('cantidad');
}

function ingresosDe($venta): float
{
    return (float) DB::table('cuenta_movimientos')->where('ref_tipo', 'venta')->where('ref_id', $venta->id)->sum('monto');
}

it('el admin restablece una venta anulada: vuelve el stock, el dinero y la caja del turno', function () {
    $venta = ventaAnulada($this);
    expect(stockProducto($this))->toBe(100.0)->and(ingresosDe($venta))->toBe(0.0);

    $this->post(route('ventas.restablecer', $venta), ['motivo' => 'La anulé por equivocación'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $venta->refresh();
    expect($venta->estado)->toBe('completada')
        ->and(stockProducto($this))->toBe(96.0)
        ->and(ingresosDe($venta))->toBe(40.0);
    expect((float) $this->turno->fresh()->calcularMontoEsperado())->toBe((float) $this->turno->monto_apertura + 40);
    expect(DB::table('auditoria')->where('accion', 'venta.restablecida')->where('modelo_id', $venta->id)->exists())->toBeTrue();

    // No se restablece dos veces.
    $this->post(route('ventas.restablecer', $venta), ['motivo' => 'Otra vez por si acaso'])
        ->assertSessionHasErrors(['venta' => 'La venta no está anulada.']);
    expect(stockProducto($this))->toBe(96.0);
});

it('a crédito: vuelve la deuda del cliente', function () {
    $cliente = \App\Models\Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Cliente', 'apellidos' => 'Crédito',
        'tipo_documento' => 'DNI', 'numero_documento' => '45454545', 'activo' => true,
    ]);
    $venta = ventaAnulada($this, ['es_credito' => true, 'cliente_id' => $cliente->id, 'pagos' => []]);
    expect((float) $venta->saldo_pendiente)->toBe(0.0);

    $this->post(route('ventas.restablecer', $venta), ['motivo' => 'La anulé por equivocación'])->assertSessionHasNoErrors();
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(40.0);
});

it('una cajera no puede restablecer, aunque tenga permiso de editar ventas', function () {
    $venta = ventaAnulada($this);
    $rol = Rol::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Cajera', 'descripcion' => 'x', 'es_admin' => false, 'activo' => true]);
    Permiso::create(['rol_id' => $rol->id, 'modulo_id' => Modulo::where('slug', 'ventas')->value('id'), 'ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true]);
    $cajera = User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Cajera', 'email' => 'caj+' . uniqid() . '@test.com', 'password' => bcrypt('x'), 'email_verified_at' => now(), 'activo' => true,
    ]);

    $this->actingAs($cajera)->post(route('ventas.restablecer', $venta), ['motivo' => 'Quiero restablecerla'])->assertForbidden();
    expect($venta->fresh()->estado)->toBe('anulada')->and(stockProducto($this))->toBe(100.0);
});

it('no restablece una venta anulada antes de cerrar su turno (esa caja se cuadró sin ella)', function () {
    $venta = ventaAnulada($this);
    DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'cerrado', 'fecha_cierre' => now()->addMinute()]);

    $this->post(route('ventas.restablecer', $venta), ['motivo' => 'La anulé por equivocación'])
        ->assertSessionHasErrors('venta');
    expect($venta->fresh()->estado)->toBe('anulada');
});

it('el detalle le dice al admin si puede restablecerla; a nadie más le llega', function () {
    $venta = ventaAnulada($this);
    $this->get(route('ventas.show', $venta))->assertInertia(fn ($p) => $p->where('restablecer.bloqueo', null));

    $boleta = ventaAnulada($this, ['tipo_comprobante' => 'boleta']);
    $this->get(route('ventas.show', $boleta))->assertInertia(fn ($p) => $p->where('restablecer.bloqueo', fn ($m) => str_contains($m, 'tiene comprobante')));
});

it('ninguna venta con comprobante se restablece: electrónica ni emitida por fuera', function () {
    foreach ([['tipo_comprobante' => 'boleta'], ['tipo_comprobante' => 'boleta_externa', 'numero_comprobante' => 'B099-123']] as $tipo) {
        $venta = ventaAnulada($this, $tipo);
        $stock = stockProducto($this);
        $this->post(route('ventas.restablecer', $venta), ['motivo' => 'La anulé por equivocación'])
            ->assertSessionHasErrors(['venta' => 'Esta venta tiene comprobante (boleta o factura): una venta con comprobante no se puede restablecer. Si hace falta, regístrala de nuevo en el POS.']);
        expect($venta->fresh()->estado)->toBe('anulada')->and(stockProducto($this))->toBe($stock);
    }
});

it('el kardex queda igual que antes de anular: Recalcular no deshace la venta restablecida', function () {
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $this->producto->id, 'producto_unidad_id' => $this->producto->unidadBase->id, 'cantidad' => 4, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 40]],
    ], $this->env->admin, $this->turno);
    $almacenId = (int) DB::table('stock')->where('producto_id', $this->producto->id)->value('almacen_id');
    $kardex = fn () => app(\App\Services\KardexService::class)->reconstruirPar($almacenId, $this->producto->id, simular: true)['cantidad_despues'];

    $original = $kardex();
    app(VentaService::class)->anular($venta, $this->env->admin, 'Se anuló por error');
    $this->post(route('ventas.restablecer', $venta), ['motivo' => 'La anulé por equivocación'])->assertSessionHasNoErrors();

    expect((float) $kardex())->toBe((float) $original);
});
