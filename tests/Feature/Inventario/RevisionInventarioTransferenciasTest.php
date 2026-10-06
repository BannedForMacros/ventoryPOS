<?php

use App\Models\Almacen;
use App\Models\ProductoUnidad;
use App\Models\Stock;
use App\Models\Transferencia;
use App\Models\UnidadMedida;
use Tests\Support\TestEnv;

/**
 * Revisión de inventario (oct 2026) — transferencias central → local.
 *
 *  - Crear una transferencia daba SIEMPRE error 500 ($request sin capturar).
 *  - Editar una recibida perdía la cantidad recibida (detalles recreados).
 *  - Se podía recibir más de lo enviado o líneas de otra transferencia.
 *  - Doble clic en "Enviar" reventaba con 500 (LogicException) y sin cerrojo
 *    dos envíos simultáneos descontaban dos veces.
 *  - El factor de la presentación venía del navegador sin validar.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_almacen' => 'central_y_local']);
    $this->actingAs($this->env->admin);

    $this->central = Almacen::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => null,
        'nombre'     => 'Almacén Central',
        'tipo'       => 'central',
        'activo'     => true,
    ]);

    $this->producto = $this->env->crearProducto(['precio_costo' => 6, 'stock_inicial' => 0]);
    Stock::ajustar($this->central->id, $this->producto->id, 50, 6);
});

function rivTrPayload($test, array $detalle = [], array $extra = []): array
{
    return array_merge([
        'almacen_origen_id'  => $test->central->id,
        'almacen_destino_id' => $test->env->almacen->id,
        'fecha'              => now()->toDateString(),
        'detalles'           => [array_merge([
            'producto_id'       => $test->producto->id,
            'unidad_medida_id'  => $test->env->unidad->id,
            'cantidad'          => 10,
            'factor_conversion' => 1,
        ], $detalle)],
    ], $extra);
}

/** Transferencia creada directo con el modelo (independiente del store). */
function rivTrCrear($test, bool $enviar = false, float $cantidad = 10): Transferencia
{
    $t = Transferencia::create([
        'empresa_id'         => $test->env->empresa->id,
        'almacen_origen_id'  => $test->central->id,
        'almacen_destino_id' => $test->env->almacen->id,
        'user_id'            => $test->env->admin->id,
        'fecha'              => now()->toDateString(),
        'estado'             => 'borrador',
    ]);
    $t->detalles()->create([
        'producto_id'           => $test->producto->id,
        'unidad_medida_id'      => $test->env->unidad->id,
        'cantidad_enviada'      => $cantidad,
        'factor_conversion'     => 1,
        'cantidad_base_enviada' => $cantidad,
        'costo_unitario'        => 6,
    ]);
    if ($enviar) {
        $t->enviar($test->env->admin->id);
    }

    return $t->fresh();
}

function rivTrStock(int $almacenId, int $productoId): float
{
    return (float) (Stock::where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('cantidad') ?? 0);
}

it('crea la transferencia como borrador y también enviándola directo (antes: error 500)', function () {
    $this->post(route('inventario.transferencias.store'), rivTrPayload($this))
        ->assertRedirect(route('inventario.transferencias.index'))
        ->assertSessionHasNoErrors();

    $borrador = Transferencia::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect($borrador->estado)->toBe('borrador');
    expect($borrador->detalles()->count())->toBe(1);
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(50.0);

    $this->post(route('inventario.transferencias.store'), rivTrPayload($this, [], ['enviar' => true]))
        ->assertRedirect(route('inventario.transferencias.index'))
        ->assertSessionHasNoErrors();

    $enviada = Transferencia::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect($enviada->estado)->toBe('enviada');
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(40.0);
});

it('editar una transferencia recibida conserva lo que se recibió', function () {
    $t = rivTrCrear($this, enviar: true);
    $detalleId = $t->detalles()->value('id');

    // Llegaron 7 de 10.
    $this->post(route('inventario.transferencias.recibir', $t), ['cantidades' => [$detalleId => 7]])
        ->assertSessionHasNoErrors();
    expect(rivTrStock($this->env->almacen->id, $this->producto->id))->toBe(7.0);

    // Edición tal como la mandaba el formulario: cantidades_recibidas con el id
    // VIEJO de la línea. Antes los detalles se recreaban con ids nuevos, no se
    // encontraba el 7 y la recepción volvía a "10 recibidas".
    $this->put(route('inventario.transferencias.update', $t), rivTrPayload($this, [], [
        'observacion_recepcion' => 'corrección de fecha',
        'cantidades_recibidas'  => [$detalleId => 7],
    ]))->assertSessionHasNoErrors();

    $d = $t->fresh()->detalles()->firstOrFail();
    expect((float) $d->cantidad_recibida)->toBe(7.0);
    expect((float) $d->diferencia_base)->toBe(-3.0);
    expect(rivTrStock($this->env->almacen->id, $this->producto->id))->toBe(7.0);
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(40.0);

    // Formulario nuevo: la línea viaja con su id y su cantidad recibida.
    $this->put(route('inventario.transferencias.update', $t), rivTrPayload($this, [
        'id' => $d->id, 'cantidad_recibida' => 6,
    ]))->assertSessionHasNoErrors();

    expect((float) $t->fresh()->detalles()->value('cantidad_recibida'))->toBe(6.0);
    expect(rivTrStock($this->env->almacen->id, $this->producto->id))->toBe(6.0);
});

it('no deja recibir más de lo enviado ni líneas de otra transferencia', function () {
    $t = rivTrCrear($this, enviar: true);
    $detalleId = $t->detalles()->value('id');

    $this->post(route('inventario.transferencias.recibir', $t), ['cantidades' => [$detalleId => 15]])
        ->assertSessionHasErrors("cantidades.{$detalleId}");

    $this->post(route('inventario.transferencias.recibir', $t), ['cantidades' => [$detalleId => 10, 999999 => 5]])
        ->assertSessionHasErrors('cantidades.999999');

    expect($t->fresh()->estado)->toBe('enviada');
    expect(rivTrStock($this->env->almacen->id, $this->producto->id))->toBe(0.0);
});

it('enviar dos veces (doble clic o pestaña vieja) no descuenta dos veces y avisa claro', function () {
    $t = rivTrCrear($this);

    // Dos copias en memoria del mismo borrador (dos pestañas).
    $pestanaA = Transferencia::find($t->id);
    $pestanaB = Transferencia::find($t->id);

    $pestanaA->enviar($this->env->admin->id);
    expect(fn () => $pestanaB->enviar($this->env->admin->id))
        ->toThrow(LogicException::class, 'La transferencia ya fue enviada.');
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(40.0);

    // Por la ruta: error de validación legible, no un 500.
    $this->post(route('inventario.transferencias.enviar', $t))
        ->assertSessionHasErrors(['estado' => 'La transferencia ya fue enviada.']);
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(40.0);
});

it('el factor de la presentación sale del catálogo, no del navegador', function () {
    $caja = UnidadMedida::create([
        'empresa_id' => $this->env->empresa->id, 'nombre' => 'Caja', 'abreviatura' => 'CJA', 'activo' => true,
    ]);
    ProductoUnidad::create([
        'producto_id' => $this->producto->id, 'unidad_medida_id' => $caja->id, 'es_base' => false,
        'factor_conversion' => 4, 'tipo_precio' => 'fijo', 'precio_venta' => 40, 'precio_costo' => 24, 'activo' => true,
    ]);

    // Ya enviada; se edita a 2 cajas con el factor manipulado a 1000: deben
    // salir 2 × 4 = 8 unidades, no 2,000.
    $t = rivTrCrear($this, enviar: true);
    $this->put(route('inventario.transferencias.update', $t), rivTrPayload($this, [
        'unidad_medida_id' => $caja->id, 'cantidad' => 2, 'factor_conversion' => 1000,
    ]))->assertSessionHasNoErrors();

    $d = $t->fresh()->detalles()->firstOrFail();
    expect((float) $d->factor_conversion)->toBe(4.0);
    expect((float) $d->cantidad_base_enviada)->toBe(8.0);
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(42.0);

    // Una unidad que el producto no tiene se rechaza.
    $otra = UnidadMedida::create([
        'empresa_id' => $this->env->empresa->id, 'nombre' => 'Saco', 'abreviatura' => 'SCO', 'activo' => true,
    ]);
    $this->put(route('inventario.transferencias.update', $t), rivTrPayload($this, ['unidad_medida_id' => $otra->id]))
        ->assertSessionHasErrors('detalles.0.unidad_medida_id');
    expect(rivTrStock($this->central->id, $this->producto->id))->toBe(42.0);
});
