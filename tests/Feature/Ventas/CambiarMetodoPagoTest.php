<?php

use App\Models\CuentaMovimiento;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Corregir SOLO el método de pago de una venta, también con boleta/factura ya
 * informada a SUNAT (el comprobante no lleva el medio de pago): el dinero pasa
 * a la cuenta correcta con la misma fecha y nada de lo declarado cambia.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 50, 'precio_costo' => 20, 'stock_inicial' => 10]);
    $this->turno = $this->env->abrirTurno($this->env->admin);

    $this->vender = function (array $pagos) {
        $this->post(route('ventas.store'), [
            'tipo_comprobante' => 'ticket',
            'items' => [[
                'producto_id' => $this->producto->id, 'producto_unidad_id' => $this->producto->unidadBase->id,
                'cantidad' => 2, 'precio_unitario' => 50, 'incluye_igv' => true,
            ]],
            'pagos' => $pagos,
        ])->assertSessionHasNoErrors();

        return Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    };
    $this->emitida = fn (Venta $v) => VentaComprobante::create([
        'venta_id' => $v->id, 'tipo' => '01', 'estado' => 'aceptado',
        'facturamac_id' => 999, 'numero' => 'F001-00000001', 'idempotency_key' => 'mp-' . $v->id,
    ]);
    $this->movs = fn (Venta $v) => CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $v->id)->get();
});

it('con factura emitida no se edita la venta, pero sí se corrige el método de pago', function () {
    $venta = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]]);
    ($this->emitida)($venta);
    $efectivo = app(TesoreriaService::class)->resolverCuenta($this->env->empresa->id, null, $this->env->metodo('efectivo')->id);
    $fecha = ($this->movs)($venta)->first()->fecha->toDateString();

    $pago = $venta->pagos()->first();
    $this->from(route('ventas.show', $venta))->put(route('ventas.metodo-pago', $venta), [
        'pagos' => [['id' => $pago->id, 'metodo_pago_id' => $this->env->metodo('yape')->id, 'referencia' => 'OP-123']],
        'motivo' => 'La cajera marcó efectivo y pagó con Yape',
    ])->assertSessionHasNoErrors();

    $pago->refresh();
    expect($pago->metodo_pago_id)->toBe($this->env->metodo('yape')->id)
        ->and($pago->referencia)->toBe('OP-123')
        ->and((float) $pago->monto)->toBe(100.0);

    // El dinero salió del efectivo y entró a la cuenta de Yape, el mismo día.
    $movs = ($this->movs)($venta);
    expect($movs)->toHaveCount(1)
        ->and((float) $movs[0]->monto)->toBe(100.0)
        ->and($movs[0]->cuenta_id)->not->toBe($efectivo)
        ->and($movs[0]->fecha->toDateString())->toBe($fecha);

    // Nada de lo declarado cambió.
    $venta->refresh();
    expect((float) $venta->total)->toBe(100.0)->and($venta->estado)->toBe('completada')
        ->and($venta->comprobanteElectronico()->first()->estado)->toBe('aceptado');

    expect(DB::table('auditoria')->where('accion', 'venta.metodo_pago_cambiado')->where('modelo_id', $venta->id)->exists())->toBeTrue();
});

it('un pago que dio vuelto no puede pasar a un método sin vuelto', function () {
    $venta = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 150]]);
    $pago = $venta->pagos()->first();
    expect((float) $pago->vuelto)->toBe(50.0);

    $this->from(route('ventas.show', $venta))->put(route('ventas.metodo-pago', $venta), [
        'pagos' => [['id' => $pago->id, 'metodo_pago_id' => $this->env->metodo('yape')->id]],
    ])->assertSessionHasErrors('metodo_pago');

    expect($pago->fresh()->metodo_pago_id)->toBe($this->env->metodo('efectivo')->id);
});

it('con dos pagos se cambia uno y el dinero de cada uno queda en su cuenta', function () {
    $venta = ($this->vender)([
        ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 60],
        ['metodo_pago_id' => $this->env->metodo('yape')->id, 'monto' => 40],
    ]);
    [$p1, $p2] = $venta->pagos()->orderBy('id')->get()->all();

    $this->put(route('ventas.metodo-pago', $venta), ['pagos' => [
        ['id' => $p1->id, 'metodo_pago_id' => $this->env->metodo('plin')->id],
        ['id' => $p2->id, 'metodo_pago_id' => $this->env->metodo('yape')->id],
    ]])->assertSessionHasNoErrors();

    $movs = ($this->movs)($venta);
    expect($movs)->toHaveCount(2)->and(round((float) $movs->sum('monto'), 2))->toBe(100.0)
        ->and($movs->pluck('descripcion')->implode(' | '))->toContain('Plin')->toContain('Yape')->not->toContain('Efectivo');
});

it('no acepta pagos de otra venta ni deja sin cambios', function () {
    $venta = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]]);
    $otra  = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]]);

    $this->put(route('ventas.metodo-pago', $venta), ['pagos' => [
        ['id' => $otra->pagos()->first()->id, 'metodo_pago_id' => $this->env->metodo('yape')->id],
    ]])->assertSessionHasErrors('metodo_pago');

    $this->put(route('ventas.metodo-pago', $venta), ['pagos' => [
        ['id' => $venta->pagos()->first()->id, 'metodo_pago_id' => $this->env->metodo('efectivo')->id],
    ]])->assertSessionHasErrors('metodo_pago');
});

it('una venta anulada no se toca', function () {
    $venta = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]]);
    $pago  = $venta->pagos()->first();
    $venta->update(['estado' => 'anulada']);

    $this->put(route('ventas.metodo-pago', $venta), ['pagos' => [
        ['id' => $pago->id, 'metodo_pago_id' => $this->env->metodo('yape')->id],
    ]])->assertSessionHasErrors('metodo_pago');
});

it('la pantalla de la venta ofrece el cambio al admin aunque la factura esté emitida', function () {
    $venta = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]]);
    ($this->emitida)($venta);

    $this->get(route('ventas.show', $venta))->assertOk()->assertInertia(fn ($p) => $p->where('cambioPago.bloqueo', null));

    // El modal (lista o detalle) pide sus datos al abrirse.
    $r = $this->getJson(route('ventas.metodo-pago.datos', $venta))->assertOk();
    expect($r->json('bloqueo'))->toBeNull()
        ->and($r->json('metodos'))->toHaveCount(5)
        ->and($r->json('pagos.0.monto'))->toEqual(100)
        ->and($r->json('venta.numero'))->toBe($venta->numero);
});

it('con el turno cerrado solo el administrador cambia el método de pago', function () {
    $venta = ($this->vender)([['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]]);
    $this->turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);
    $servicio = app(\App\Services\CambiarMetodoPagoVenta::class);

    $cajera = $this->env->admin->replicate();
    $cajera->setRelation('rol', new \App\Models\Rol(['es_admin' => false]));

    expect($servicio->motivoBloqueo($venta->fresh(), $cajera))->toContain('solo un administrador')
        ->and($servicio->motivoBloqueo($venta->fresh(), $this->env->admin))->toBeNull();
});
