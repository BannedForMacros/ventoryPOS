<?php

use App\Http\Requests\Ventas\StoreVentaRequest;
use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\CuentaMovimiento;
use App\Models\DescuentoLog;
use App\Models\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TestEnv;

/**
 * Revisión del cobro en el POS: anticipos junto a pagos, ventas en dólares,
 * cuentas de pago, tope de descuento, edición de ventas viejas y redondeo.
 */

beforeEach(function () {
    $this->env     = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno   = $this->env->abrirTurno();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);
    $this->cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI',
        'numero_documento' => '4' . random_int(1000000, 9999999), 'nombres' => 'Rosa', 'apellidos' => 'Quispe', 'activo' => true,
    ]);
});

function rvcValidar(array $payload): \Illuminate\Contracts\Validation\Validator
{
    $req = StoreVentaRequest::create('/ventas', 'POST', $payload);
    $req->setContainer(app())->setRedirector(app('redirect'));
    app()->instance('request', $req);
    \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
    $req->setUserResolver(fn () => auth()->user());

    $validator = Validator::make($payload, $req->rules());
    $req->withValidator($validator);
    $validator->passes();

    return $validator;
}

function rvcAnticipo(float $saldo): ClienteAnticipo
{
    return ClienteAnticipo::create([
        'empresa_id' => test()->env->empresa->id, 'cliente_id' => test()->cliente->id,
        'user_id' => test()->env->admin->id, 'fecha' => now()->toDateString(),
        'monto' => $saldo, 'saldo' => $saldo, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ]);
}

function rvcItem(\App\Models\Producto $p, float $precio, float $cantidad = 1, array $extra = []): array
{
    return array_merge([
        'producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id,
        'cantidad' => $cantidad, 'precio_unitario' => $precio, 'incluye_igv' => (bool) $p->incluye_igv,
    ], $extra);
}

it('anticipo + efectivo: el anticipo cubre primero y lo que sobra es vuelto, no caja fantasma', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 100]);
    $anticipo = rvcAnticipo(60);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'anticipo_ids'     => [$anticipo->id],
        'items'            => [rvcItem($producto, 100)],
        'pagos'            => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    expect((float) $venta->pagos->first()->vuelto)->toBe(10.0)
        ->and((float) $anticipo->fresh()->saldo)->toBe(0.0)
        ->and((float) CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $venta->id)->sum('monto'))->toBe(40.0);
});

it('anticipo + tarjeta que ya cubre el total: se rechaza el sobrepago sin vuelto', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 100]);
    $anticipo = rvcAnticipo(60);

    $v = rvcValidar([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'anticipo_ids'     => [$anticipo->id],
        'items'            => [rvcItem($producto, 100)],
        'pagos'            => [['metodo_pago_id' => $this->env->metodo('tarjeta_debito')->id, 'monto' => 100]],
    ]);

    expect($v->errors()->has('pagos.0.monto'))->toBeTrue();
});

it('editar una venta pagada con anticipo: el POS lo precarga y guardar no lo cambia por efectivo', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 100]);
    $anticipo = rvcAnticipo(100);
    $payload  = [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'anticipo_ids'     => [$anticipo->id],
        'items'            => [rvcItem($producto, 100)],
        'pagos'            => [],
    ];
    $venta = $this->service->crear($payload, $this->env->admin, $this->turno);
    expect($anticipo->fresh()->estado)->toBe('aplicado');

    $props = $this->get(route('pos.index', ['venta_id' => $venta->id]))->viewData('page')['props'];
    expect($props['ventaEnEdicion']['anticipos'][0]['id'])->toBe($anticipo->id)
        ->and((float) $props['ventaEnEdicion']['anticipos'][0]['saldo'])->toBe(100.0);

    // El anticipo agotado por ESTA venta vuelve a estar disponible al editarla.
    $this->put(route('ventas.update', $venta), $payload)->assertSessionHasNoErrors();

    expect($venta->fresh()->pagos)->toHaveCount(0)
        ->and((float) $anticipo->fresh()->saldo)->toBe(0.0)
        ->and(CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $venta->id)->count())->toBe(0);
});

it('venta en dólares con anticipo en soles: el saldo se convierte antes de dar la venta por pagada', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 75, 'precio_costo' => 1]);
    $anticipo = rvcAnticipo(37.50); // = US$ 10 a 3.75

    $v = rvcValidar([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'moneda'           => 'USD',
        'tipo_cambio'      => 3.75,
        'anticipo_ids'     => [$anticipo->id],
        'items'            => [rvcItem($producto, 20)], // US$ 20
        'pagos'            => [],
    ]);

    expect($v->errors()->has('pagos'))->toBeTrue();
});

it('venta en dólares: el piso de costo compara en soles y el descuento global se registra en soles', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'precio_costo' => 6]);
    $payload  = [
        'tipo_comprobante'      => 'ticket',
        'moneda'                => 'USD',
        'tipo_cambio'           => 3.75,
        'descuento_total'       => 1,
        'descuento_concepto_id' => $this->env->descuentoConcepto->id,
        'items'                 => [rvcItem($producto, 5)], // US$ 5 = S/ 18.75, sobre el costo S/ 6
        'pagos'                 => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 4]],
    ];

    expect(rvcValidar($payload)->errors()->has('items.0.precio_unitario'))->toBeFalse();

    $venta = $this->service->crear($payload, $this->env->admin, $this->turno);
    expect((float) DescuentoLog::where('venta_id', $venta->id)->whereNull('venta_item_id')->value('monto_descuento'))->toBe(3.75);
});

it('cuenta de pago: solo se exige si el método tiene cuentas ACTIVAS', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20]);
    $yape     = $this->env->metodo('yape');
    $cuenta   = \App\Models\Cuenta::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Yape viejo', 'activo' => false]);
    DB::table('cuenta_metodo_pago')->insert(['cuenta_id' => $cuenta->id, 'metodo_pago_id' => $yape->id]);

    $v = rvcValidar([
        'tipo_comprobante' => 'ticket',
        'items'            => [rvcItem($producto, 20)],
        'pagos'            => [['metodo_pago_id' => $yape->id, 'monto' => 20]],
    ]);

    expect($v->errors()->has('pagos.0.cuenta_metodo_pago_id'))->toBeFalse();
});

it('el tope de descuento del rol y el piso de costo también miran el descuento por producto', function () {
    $rol = Rol::create([
        'empresa_id' => $this->env->empresa->id, 'nombre' => 'Cajera', 'es_admin' => false,
        'activo' => true, 'max_descuento_porcentaje' => 10,
    ]);
    $cajera = User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Cajera', 'email' => 'cajera+' . uniqid() . '@test.com', 'password' => bcrypt('x'),
        'email_verified_at' => now(), 'activo' => true,
    ]);
    $this->actingAs($cajera);
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6]);

    // 30 % de descuento en la línea, sin descuento global: pasa el tope del 10 %.
    $v = rvcValidar([
        'tipo_comprobante' => 'ticket',
        'items'            => [rvcItem($producto, 10, 1, ['descuento_item' => 3, 'descuento_concepto_id' => $this->env->descuentoConcepto->id])],
        'pagos'            => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 7]],
    ]);
    expect($v->errors()->has('items'))->toBeTrue();

    // Un descuento sin motivo no sirve para vender bajo el costo (10 − 5 < 6).
    $this->actingAs($this->env->admin);
    $v = rvcValidar([
        'tipo_comprobante' => 'ticket',
        'items'            => [rvcItem($producto, 10, 1, ['descuento_item' => 5])],
        'pagos'            => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 5]],
    ]);
    expect($v->errors()->has('items.0.precio_unitario'))->toBeTrue();
});

it('se puede editar un crédito vencido con un producto que ya se desactivó', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20]);
    $payload  = [
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $this->cliente->id,
        'es_credito'        => true,
        'fecha_vencimiento' => now()->subDays(5)->toDateString(),
        'items'             => [rvcItem($producto, 20)],
        'pagos'             => [],
    ];
    $venta = $this->service->crear($payload, $this->env->admin, $this->turno);
    $producto->update(['activo' => false]);

    $this->put(route('ventas.update', $venta), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('ventas.show', $venta));
});

it('el total redondea cada línea a céntimos, igual que el POS', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 1, 'incluye_igv' => false]);

    // 10 × 0.3333 = 3.333 → 3.33 por línea; dos líneas = 6.66 (no 6.67).
    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items'            => [rvcItem($producto, 10, 0.3333), rvcItem($producto, 10, 0.3333)],
        'pagos'            => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 6.66]],
    ], $this->env->admin, $this->turno);

    expect((float) $venta->total)->toBe(6.66);
});
