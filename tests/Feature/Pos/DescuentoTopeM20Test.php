<?php

use App\Http\Requests\Ventas\StoreVentaRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

/**
 * Helper: validar payload contra StoreVentaRequest sin pasar por HTTP.
 */
function validarVenta(array $payload): \Illuminate\Contracts\Validation\Validator
{
    $req = StoreVentaRequest::create('/ventas', 'POST', $payload);
    $req->setContainer(app())->setRedirector(app('redirect'));
    $req->setUserResolver(fn() => auth()->user());

    $validator = Validator::make($payload, $req->rules());
    $req->withValidator($validator);
    $validator->passes();
    return $validator;
}

it('descuento_total > 0 SIN descuento_concepto_id falla validación', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    $validator = validarVenta([
        'tipo_comprobante' => 'ticket',
        'descuento_total'  => 10, // hay descuento pero no concepto
        // 'descuento_concepto_id' omitido
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 90,
        ]],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('descuento_concepto_id'))->toBeTrue();
});

it('descuento_total > 0 CON descuento_concepto_id pasa (sin tope en el rol)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    $validator = validarVenta([
        'tipo_comprobante'      => 'ticket',
        'descuento_total'       => 10,
        'descuento_concepto_id' => $this->env->descuentoConcepto->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 90,
        ]],
    ]);

    expect($validator->errors()->has('descuento_concepto_id'))->toBeFalse();
    expect($validator->errors()->has('descuento_total'))->toBeFalse();
});

it('rol con tope 10% bloquea descuento del 15%', function () {
    // El admin del TestEnv tiene es_admin=true. Le pongo tope 10% explícito
    // para forzar el path de validación.
    $this->env->rolAdmin->update(['max_descuento_porcentaje' => 10]);
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    $validator = validarVenta([
        'tipo_comprobante'      => 'ticket',
        'descuento_total'       => 15, // 15% sobre 100 → excede tope 10%
        'descuento_concepto_id' => $this->env->descuentoConcepto->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 85,
        ]],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('descuento_total'))->toBeTrue();
    expect($validator->errors()->first('descuento_total'))->toContain('máximo 10');
});

it('rol con tope 10% permite descuento del 10% exacto', function () {
    $this->env->rolAdmin->update(['max_descuento_porcentaje' => 10]);
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    $validator = validarVenta([
        'tipo_comprobante'      => 'ticket',
        'descuento_total'       => 10, // exactamente 10%
        'descuento_concepto_id' => $this->env->descuentoConcepto->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 90,
        ]],
    ]);

    expect($validator->errors()->has('descuento_total'))->toBeFalse();
});

it('rol con max_descuento_porcentaje=NULL no aplica tope (caso admin)', function () {
    // El admin del TestEnv tiene max_descuento_porcentaje NULL por default
    expect($this->env->rolAdmin->max_descuento_porcentaje)->toBeNull();

    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    // Descuento del 99% sobre 100 = 99. Total pagar = 1.
    $validator = validarVenta([
        'tipo_comprobante'      => 'ticket',
        'descuento_total'       => 99,
        'descuento_concepto_id' => $this->env->descuentoConcepto->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 1,
        ]],
    ]);

    expect($validator->errors()->has('descuento_total'))->toBeFalse();
});

it('sin descuento_total el tope NO se evalúa (la regla solo aplica si hay descuento)', function () {
    $this->env->rolAdmin->update(['max_descuento_porcentaje' => 5]); // tope ridículamente bajo
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    $validator = validarVenta([
        'tipo_comprobante' => 'ticket',
        // descuento_total = 0 (omitido)
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 100,
        ]],
    ]);

    expect($validator->errors()->has('descuento_total'))->toBeFalse();
    expect($validator->errors()->has('descuento_concepto_id'))->toBeFalse();
});
