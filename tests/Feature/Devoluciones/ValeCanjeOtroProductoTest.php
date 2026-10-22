<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\CuentaMovimiento;
use App\Models\Devolucion;
use App\Models\Venta;
use Tests\Support\TestEnv;

/**
 * Cambio por otro producto (caso real H&C): la clienta devuelve unidades de
 * una venta de días atrás, el valor queda como VALE a su nombre y con él paga
 * otro producto más caro hoy. La diferencia entra a la caja de HOY (o queda al
 * crédito); la venta y la caja de aquel día no se tocan.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->turno = $this->env->abrirTurno($this->env->admin);
    config(['facturamac.base_url' => 'https://facturamac.test']);

    $this->alambre = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 4, 'stock_inicial' => 50]);
    $this->taladro = $this->env->crearProducto(['precio_venta' => 150, 'precio_costo' => 90, 'stock_inicial' => 5]);
    $this->senora = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI', 'numero_documento' => '41234567',
        'nombres' => 'Rosa', 'apellidos' => 'Quispe', 'activo' => true, 'es_cliente_general' => false,
    ]);

    // Venta de 12 alambres a Cliente General (como la mayoría en H&C).
    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id' => $this->alambre->id, 'producto_unidad_id' => $this->alambre->unidadBase->id,
            'cantidad' => 12, 'precio_unitario' => 10, 'incluye_igv' => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 120]],
    ])->assertSessionHasNoErrors();
    $this->venta = Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();

    $this->devolver = fn (array $extra = []) => $this->post(route('devoluciones.store'), array_merge([
        'venta_id' => $this->venta->id,
        'motivo_id' => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'vale_credito',
        'turno_id' => $this->turno->id,
        'items' => [[
            'venta_item_id' => $this->venta->items()->first()->id,
            'cantidad' => 12, 'estado_producto' => 'bueno', 'restock' => true,
        ]],
        'pagos' => [],
    ], $extra));
});

it('con la venta a Cliente General el vale exige un dueño', function () {
    ($this->devolver)()->assertSessionHasErrors('vale_cliente_id');
    expect(Devolucion::where('venta_id', $this->venta->id)->exists())->toBeFalse();
});

it('la opción «cambio por otro producto» ya no se acepta (perdía el saldo)', function () {
    ($this->devolver)(['forma_reembolso' => 'cambio_producto'])->assertSessionHasErrors('forma_reembolso');
});

it('el vale queda a nombre de la persona elegida y el POS lo abre aplicado', function () {
    ($this->devolver)(['vale_cliente_id' => $this->senora->id])->assertSessionHasNoErrors();

    $dev  = Devolucion::where('venta_id', $this->venta->id)->firstOrFail();
    $vale = ClienteAnticipo::where('devolucion_id', $dev->id)->firstOrFail();
    expect($dev->vale_cliente_id)->toBe($this->senora->id)
        ->and($vale->cliente_id)->toBe($this->senora->id)
        ->and((float) $vale->saldo)->toBe(120.0)
        // La venta original sigue a nombre de Cliente General.
        ->and($this->venta->fresh()->cliente_id)->toBe($this->env->clienteGeneral->id);

    $this->get(route('devoluciones.show', $dev))->assertOk()->assertInertia(fn ($p) => $p
        ->where('usarVale.anticipo_id', $vale->id)
        ->where('usarVale.saldo', 120));

    $this->get(route('pos.index', ['vale_anticipo_id' => $vale->id]))->assertOk()->assertInertia(fn ($p) => $p
        ->where('valePrellenado.anticipo_id', $vale->id)
        ->where('valePrellenado.cliente.id', $this->senora->id));

    // Un id ajeno o un anticipo que no es vale no precarga nada.
    $this->get(route('pos.index', ['vale_anticipo_id' => 999999]))->assertOk()
        ->assertInertia(fn ($p) => $p->where('valePrellenado', null));
});

it('con el vale paga otro producto más caro: solo la diferencia entra a la caja de hoy', function () {
    ($this->devolver)(['vale_cliente_id' => $this->senora->id])->assertSessionHasNoErrors();
    $vale = ClienteAnticipo::where('cliente_id', $this->senora->id)->firstOrFail();
    $movsAntes = CuentaMovimiento::where('empresa_id', $this->env->empresa->id)->sum('monto');

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'cliente_id' => $this->senora->id,
        'anticipo_ids' => [$vale->id],
        'items' => [[
            'producto_id' => $this->taladro->id, 'producto_unidad_id' => $this->taladro->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => 150, 'incluye_igv' => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 30]],
    ])->assertSessionHasNoErrors();

    $nueva = Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect((float) $nueva->total)->toBe(150.0)->and($nueva->cliente_id)->toBe($this->senora->id)
        ->and((float) $vale->fresh()->saldo)->toBe(0.0)
        ->and($vale->fresh()->estado)->toBe('aplicado')
        // A tesorería solo entró la diferencia (30); el vale no mueve caja.
        ->and(round((float) CuentaMovimiento::where('empresa_id', $this->env->empresa->id)->sum('monto') - (float) $movsAntes, 2))->toBe(30.0)
        ->and(round((float) CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $nueva->id)->sum('monto'), 2))->toBe(30.0);
});

it('la diferencia también puede quedar al crédito', function () {
    ($this->devolver)(['vale_cliente_id' => $this->senora->id])->assertSessionHasNoErrors();
    $vale = ClienteAnticipo::where('cliente_id', $this->senora->id)->firstOrFail();

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'cliente_id' => $this->senora->id,
        'anticipo_ids' => [$vale->id],
        'es_credito' => true,
        'fecha_vencimiento' => now()->addDays(15)->toDateString(),
        'items' => [[
            'producto_id' => $this->taladro->id, 'producto_unidad_id' => $this->taladro->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => 150, 'incluye_igv' => true,
        ]],
        'pagos' => [],
    ])->assertSessionHasNoErrors();

    $nueva = Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect((bool) $nueva->es_credito)->toBeTrue()
        ->and((float) $nueva->monto_pagado)->toBe(120.0)
        ->and((float) $nueva->saldo_pendiente)->toBe(30.0)
        ->and(CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $nueva->id)->count())->toBe(0);
});

it('en una venta al crédito con deuda el vale no cambia de dueño', function () {
    $deudor = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI', 'numero_documento' => '47654321',
        'nombres' => 'Juan', 'apellidos' => 'Pérez', 'activo' => true, 'es_cliente_general' => false,
    ]);
    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket', 'cliente_id' => $deudor->id,
        'es_credito' => true, 'fecha_vencimiento' => now()->addDays(15)->toDateString(),
        'items' => [[
            'producto_id' => $this->alambre->id, 'producto_unidad_id' => $this->alambre->unidadBase->id,
            'cantidad' => 5, 'precio_unitario' => 10, 'incluye_igv' => true,
        ]],
        'pagos' => [],
    ])->assertSessionHasNoErrors();
    $this->venta = Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();

    $datos = fn (?int $dueno) => [
        'vale_cliente_id' => $dueno,
        'items' => [[
            'venta_item_id' => $this->venta->items()->first()->id,
            'cantidad' => 2, 'estado_producto' => 'bueno', 'restock' => true,
        ]],
    ];
    ($this->devolver)($datos($this->senora->id))->assertSessionHasErrors('vale_cliente_id');
    ($this->devolver)($datos(null))->assertSessionHasNoErrors();
});
