<?php

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Ventas\StoreVentaRequest;
use App\Models\Cliente;
use App\Models\Stock;
use App\Services\VentaService;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TestEnv;

/**
 * Cobertura de las mejoras del POS:
 *  1. Alta de cliente desde el POS (POST clientes.store con redirect back).
 *  2. Piso de precio: no se puede vender por debajo del costo.
 *  3. Config empresa permite_stock_negativo: venta que deja stock negativo.
 *  4. Cierre de turno exige confirmación cuando hay stock negativo vendido.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

/** Espejo del helper de SobrepagoValidacionTest con nombre propio (Pest carga funciones globales). */
function validarPayloadVentaMejoras(array $payload): \Illuminate\Contracts\Validation\Validator
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

// ── 1. Alta de cliente desde el POS ─────────────────────────────────────────

it('crea un cliente vía clientes.store desde el POS y vuelve al POS', function () {
    $response = $this->from('/pos')->post(route('clientes.store'), [
        'tipo_documento'   => 'DNI',
        'numero_documento' => '45678912',
        'nombres'          => 'María',
        'apellidos'        => 'Quispe',
        'razon_social'     => '',
        'telefono'         => '987654321',
        'email'            => '',
        'direccion'        => '',
        'fecha_nacimiento' => '',
        'activo'           => true,
    ]);

    $response->assertRedirect('/pos');
    $response->assertSessionHas('success');

    $cliente = Cliente::where('empresa_id', $this->env->empresa->id)
        ->where('numero_documento', '45678912')
        ->first();
    expect($cliente)->not->toBeNull();
    expect($cliente->nombres)->toBe('María');
    expect($cliente->activo)->toBeTrue();
});

it('rechaza crear cliente DNI sin nombres (validación del form)', function () {
    $response = $this->from('/pos')->post(route('clientes.store'), [
        'tipo_documento'   => 'DNI',
        'numero_documento' => '45678913',
        'nombres'          => '',
        'activo'           => true,
    ]);

    $response->assertRedirect('/pos');
    $response->assertSessionHasErrors('nombres');
});

// ── 2. Piso de precio (no vender bajo el costo) ─────────────────────────────

it('rechaza un precio unitario por debajo del costo del producto', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6]);
    $efectivo = $this->env->metodo('efectivo');

    $validator = validarPayloadVentaMejoras([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 5.50, // costo 6.00 → debe fallar
            'incluye_igv'        => true,
        ]],
        'pagos' => [[ 'metodo_pago_id' => $efectivo->id, 'monto' => 5.50 ]],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.precio_unitario'))->toBeTrue();
});

it('acepta un precio unitario igual al costo o mayor', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6]);
    $efectivo = $this->env->metodo('efectivo');

    $validator = validarPayloadVentaMejoras([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 6.00, // exactamente el costo → permitido
            'incluye_igv'        => true,
        ]],
        'pagos' => [[ 'metodo_pago_id' => $efectivo->id, 'monto' => 6.00 ]],
    ]);

    expect($validator->errors()->has('items.0.precio_unitario'))->toBeFalse();
});

// ── 3. Venta con stock negativo según config de empresa ────────────────────

function payloadVentaStock(\App\Models\Producto $producto, \App\Models\MetodoPago $metodo, float $cantidad): array
{
    $precio = (float) $producto->precio_venta;
    return [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => $precio,
        ]],
        'pagos' => [[ 'metodo_pago_id' => $metodo->id, 'monto' => $cantidad * $precio ]],
    ];
}

it('bloquea la venta sin stock suficiente cuando permite_stock_negativo está apagado', function () {
    $this->env->empresa->update(['permite_stock_negativo' => false]);
    $producto = $this->env->crearProducto(['stock_inicial' => 1]);
    $efectivo = $this->env->metodo('efectivo');

    app(VentaService::class)->crear(
        payloadVentaStock($producto, $efectivo, 5),
        $this->env->admin,
        $this->turno,
    );
})->throws(InsufficientStockException::class);

it('permite la venta y deja stock negativo cuando permite_stock_negativo está activo', function () {
    $this->env->empresa->update(['permite_stock_negativo' => true]);
    $producto = $this->env->crearProducto(['stock_inicial' => 1]);
    $efectivo = $this->env->metodo('efectivo');

    $venta = app(VentaService::class)->crear(
        payloadVentaStock($producto, $efectivo, 5),
        $this->env->admin,
        $this->turno,
    );

    expect($venta->estado)->toBe('completada');
    $stock = Stock::where('almacen_id', $this->env->almacen->id)
        ->where('producto_id', $producto->id)->first();
    expect((float) $stock->cantidad)->toBe(-4.0);
});

// ── 4. Cierre de turno con stock negativo requiere confirmación ────────────

it('exige confirmación para cerrar turno cuando se vendió con stock negativo, y cierra al confirmar', function () {
    $this->env->empresa->update(['permite_stock_negativo' => true]);
    $producto = $this->env->crearProducto(['stock_inicial' => 1]);
    $efectivo = $this->env->metodo('efectivo');

    app(VentaService::class)->crear(
        payloadVentaStock($producto, $efectivo, 5),
        $this->env->admin,
        $this->turno,
    );

    // Sin confirmación → error y el turno sigue abierto
    $response = $this->from(route('turnos.cerrar.page', $this->turno))
        ->post(route('turnos.cerrar', $this->turno), [
            'observacion_cierre' => '',
        ]);
    $response->assertSessionHasErrors('stock_negativo');
    expect($this->turno->fresh()->estado)->toBe('abierto');

    // La lista de productos con stock negativo del turno contiene el producto
    $negativos = $this->turno->fresh()->productosVendidosConStockNegativo();
    expect($negativos)->toHaveCount(1);
    expect($negativos[0]['producto_id'])->toBe($producto->id);
    expect($negativos[0]['stock_actual'])->toBe(-4.0);

    // Con confirmación explícita → cierra correctamente
    $response = $this->post(route('turnos.cerrar', $this->turno), [
        'observacion_cierre'      => '',
        'confirma_stock_negativo' => true,
    ]);
    $response->assertRedirect(route('turnos.index'));
    expect($this->turno->fresh()->estado)->toBe('cerrado');
});

it('cierra el turno sin fricción cuando no hay stock negativo', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 10]);
    $efectivo = $this->env->metodo('efectivo');

    app(VentaService::class)->crear(
        payloadVentaStock($producto, $efectivo, 2),
        $this->env->admin,
        $this->turno,
    );

    $response = $this->post(route('turnos.cerrar', $this->turno), [
        'observacion_cierre' => '',
    ]);
    $response->assertRedirect(route('turnos.index'));
    expect($this->turno->fresh()->estado)->toBe('cerrado');
});
