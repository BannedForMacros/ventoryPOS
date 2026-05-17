<?php

use App\Http\Requests\Ventas\StoreVentaRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin); // StoreVentaRequest::rules() lee $this->user()->empresa_id
});

/**
 * Corre StoreVentaRequest contra un payload sin pasar por el HTTP kernel.
 * Devuelve el Validator ya validado para inspeccionar errores.
 */
function validarPayloadVenta(array $payload): \Illuminate\Contracts\Validation\Validator
{
    $req = StoreVentaRequest::create('/ventas', 'POST', $payload);
    $req->setContainer(app())->setRedirector(app('redirect'));
    app()->instance('request', $req);
    \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
    $req->setUserResolver(fn() => auth()->user());

    $validator = Validator::make($payload, $req->rules());
    $req->withValidator($validator);
    $validator->passes(); // dispara las reglas after()
    return $validator;
}

it('acepta venta exacta con tarjeta (sin vuelto, total cubierto)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'incluye_igv' => true]);
    $tarjeta  = $this->env->metodo('tarjeta_debito');

    $validator = validarPayloadVenta([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
            'incluye_igv'        => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $tarjeta->id,
            'monto'          => 50,
        ]],
    ]);

    expect($validator->fails())->toBeFalse();
});

it('bloquea sobrepago con tarjeta (método que no admite vuelto)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'incluye_igv' => true]);
    $tarjeta  = $this->env->metodo('tarjeta_debito');

    $validator = validarPayloadVenta([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
            'incluye_igv'        => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $tarjeta->id,
            'monto'          => 70, // exceso 20 sin método con vuelto → debe fallar
        ]],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('pagos.0.monto'))->toBeTrue();
});

it('acepta pago mixto tarjeta + efectivo aunque la suma exceda (el efectivo absorbe el vuelto)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => true]);
    $tarjeta  = $this->env->metodo('tarjeta_debito');
    $efectivo = $this->env->metodo('efectivo');

    $validator = validarPayloadVenta([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
            'incluye_igv'        => true,
        ]],
        'pagos' => [
            ['metodo_pago_id' => $tarjeta->id,  'monto' => 80], // sin vuelto, NO excede los 100
            ['metodo_pago_id' => $efectivo->id, 'monto' => 30], // admite vuelto
        ],
    ]);

    expect($validator->fails())->toBeFalse();
});

it('bloquea cuando los pagos no cubren el total', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'incluye_igv' => true]);
    $efectivo = $this->env->metodo('efectivo');

    $validator = validarPayloadVenta([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
            'incluye_igv'        => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $efectivo->id,
            'monto'          => 30, // falta cubrir 20
        ]],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('pagos'))->toBeTrue();
});

it('bloquea sobrepago compuesto solo por métodos sin vuelto (tarjeta + yape)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'incluye_igv' => true]);
    $tarjeta  = $this->env->metodo('tarjeta_debito');
    $yape     = $this->env->metodo('yape');

    $validator = validarPayloadVenta([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
            'incluye_igv'        => true,
        ]],
        'pagos' => [
            ['metodo_pago_id' => $tarjeta->id, 'monto' => 40],
            ['metodo_pago_id' => $yape->id,    'monto' => 20], // total 60, exceso 10 sin método con vuelto
        ],
    ]);

    expect($validator->fails())->toBeTrue();
});
