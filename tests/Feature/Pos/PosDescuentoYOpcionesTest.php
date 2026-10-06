<?php

use App\Http\Requests\Ventas\StoreVentaRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TestEnv;

/**
 * Reglas del POS del lado del servidor:
 *  1. Un descuento (por producto o global) no puede superar lo que vale.
 *  2. "Venta a crédito" y "Pendiente por entregar" se pueden apagar por empresa.
 *  3. El POS recibe el tipo de cada método de pago (sin él no pre-carga efectivo).
 */

beforeEach(function () {
    $this->env   = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function validarVentaPos(array $payload): \Illuminate\Contracts\Validation\Validator
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

function payloadPos(\App\Models\Producto $producto, \App\Models\MetodoPago $metodo, array $extra = []): array
{
    return array_replace_recursive([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => $metodo->id, 'monto' => 40]],
    ], $extra);
}

it('rechaza un descuento por producto mayor que su precio', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $v = validarVentaPos(payloadPos($producto, $this->env->metodo('efectivo'), [
        'items' => [['descuento_item' => 25]],
    ]));

    expect($v->errors()->has('items.0.descuento_item'))->toBeTrue();
});

it('rechaza un descuento global mayor que el total de la venta', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $concepto = \App\Models\DescuentoConcepto::create([
        'empresa_id' => $this->env->empresa->id, 'nombre' => 'Cortesía', 'activo' => true,
    ]);
    $v = validarVentaPos(payloadPos($producto, $this->env->metodo('efectivo'), [
        'descuento_total'       => 50,
        'descuento_concepto_id' => $concepto->id,
    ]));

    expect($v->errors()->has('descuento_total'))->toBeTrue();
});

it('acepta un descuento igual al precio (producto regalado)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $v = validarVentaPos(payloadPos($producto, $this->env->metodo('efectivo'), [
        'items' => [['descuento_item' => 20]],
        'pagos' => [['monto' => 0]],
    ]));

    expect($v->errors()->has('items.0.descuento_item'))->toBeFalse();
});

it('rechaza la venta a crédito si la empresa la apagó', function () {
    $this->env->empresa->update(['pos_permite_credito' => false]);
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $v = validarVentaPos(payloadPos($producto, $this->env->metodo('efectivo'), ['es_credito' => true]));

    expect($v->errors()->first('es_credito'))->toContain('desactivada');
});

it('rechaza pendiente por entregar si la empresa lo apagó', function () {
    $this->env->empresa->update(['pos_permite_pendiente_entrega' => false]);
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $v = validarVentaPos(payloadPos($producto, $this->env->metodo('efectivo'), ['entrega_pendiente' => true]));

    expect($v->errors()->first('entrega_pendiente'))->toContain('desactivado');
});

it('con las opciones activas (default de las empresas existentes) no hay error de configuración', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $v = validarVentaPos(payloadPos($producto, $this->env->metodo('efectivo'), ['es_credito' => true]));

    expect($v->errors()->first('es_credito'))->not->toContain('desactivada');
});

it('una empresa nueva nace con crédito y pendiente apagados', function () {
    $empresa = app(\App\Services\OnboardingEmpresaService::class)->crear(
        ['razon_social' => 'Botica Prueba', 'ruc' => '10999999991'],
        ['name' => 'Admin', 'email' => 'botica-prueba@x.test', 'password' => 'secret123'],
    );

    expect($empresa->fresh()->pos_permite_credito)->toBeFalse()
        ->and($empresa->fresh()->pos_permite_pendiente_entrega)->toBeFalse();
});

it('el POS recibe el tipo e icono de cada método de pago, con efectivo primero', function () {
    $props = $this->get(route('pos.index'))->viewData('page')['props'];

    $primero = $props['metodosPago'][0];
    expect($primero['tipo']['slug'])->toBe('efectivo')
        ->and($primero['tipo']['icono'])->not->toBeNull()
        ->and($props)->toHaveKeys(['permiteCredito', 'permitePendienteEntrega']);
});

it('una venta con 100 % de descuento se cobra sin ningún método de pago', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    // Con motivo: un descuento sin motivo ya no puede dejar la línea bajo el costo.
    $payload = payloadPos($producto, $this->env->metodo('efectivo'), [
        'items' => [['descuento_item' => 20, 'descuento_concepto_id' => $this->env->descuentoConcepto->id]],
    ]);
    $payload['pagos'] = [];

    $v = validarVentaPos($payload);
    expect($v->errors()->all())->toBe([]);

    $venta = app(\App\Services\VentaService::class)->crear($payload, $this->env->admin, $this->turno);
    expect((float) $venta->total)->toBe(0.0)
        ->and($venta->estado)->toBe('completada')
        ->and($venta->pagos)->toHaveCount(0);
});

it('crédito + despacho: se permite y el pedido queda para el almacén', function () {
    $this->env->empresa->update(['usa_despacho_almacen' => true]);
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 10]);
    $cliente  = \App\Models\Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI',
        'numero_documento' => '44556677', 'nombres' => 'Cliente', 'apellidos' => 'Crédito', 'activo' => true,
    ]);

    $payload = payloadPos($producto, $this->env->metodo('efectivo'), [
        'cliente_id'       => $cliente->id,
        'es_credito'       => true,
        'despacho_almacen' => true,
    ]);
    $payload['pagos'] = [];

    expect(validarVentaPos($payload)->errors()->all())->toBe([]);

    $venta = app(\App\Services\VentaService::class)->crear($payload, $this->env->admin, $this->turno);
    $anticipo = \App\Models\ClienteAnticipo::where('venta_id', $venta->id)->with('items')->firstOrFail();

    expect((float) $venta->saldo_pendiente)->toBe(40.0)                     // la deuda va a Cuentas por cobrar
        ->and($anticipo->estado)->toBe('activo')                            // sigue en la bandeja del almacén
        ->and((float) $anticipo->items->sum('cantidad_pendiente'))->toBe(2.0)
        ->and((float) $anticipo->saldo)->toBe(40.0);                        // obligación de entregar, completa
});
