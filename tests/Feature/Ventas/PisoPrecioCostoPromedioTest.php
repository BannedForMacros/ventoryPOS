<?php

use App\Models\Stock;
use Tests\Support\TestEnv;

/**
 * El piso de precio del POS es el costo promedio ACTUAL del almacén de ventas,
 * no el costo guardado en la presentación (el del catálogo / carga inicial,
 * que no sigue a las compras). Caso real H&C: Alambre Negro 16 con la
 * presentación a S/ 3.04 y el costo promedio en S/ 3.1823.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->turno = $this->env->abrirTurno($this->env->admin);

    $this->vender = fn ($producto, float $precio) => $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => $precio, 'incluye_igv' => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => $precio]],
    ]);
    $this->conCpp = function (float $costoPresentacion, float $cpp) {
        $p = $this->env->crearProducto(['precio_venta' => 5, 'precio_costo' => $costoPresentacion, 'stock_inicial' => 50]);
        Stock::where('producto_id', $p->id)->update(['costo_promedio' => $cpp]);

        return $p;
    };
});

it('no deja vender bajo el costo promedio aunque la presentación tenga un costo menor', function () {
    $p = ($this->conCpp)(3.04, 3.1823);

    ($this->vender)($p, 3.10)->assertSessionHasErrors(['items.0.precio_unitario' => 'El precio de "' . $p->nombre . '" (S/ 3.10) no puede ser menor al costo (S/ 3.18).']);
    ($this->vender)($p, 3.18)->assertSessionHasNoErrors();
});

it('el piso sigue al costo promedio cuando bajó respecto del costo de la presentación', function () {
    $p = ($this->conCpp)(4.00, 3.00);

    ($this->vender)($p, 3.50)->assertSessionHasNoErrors();
    ($this->vender)($p, 2.90)->assertSessionHasErrors('items.0.precio_unitario');
});

it('sin costo promedio en el almacén usa el costo de la presentación', function () {
    $p = ($this->conCpp)(4.00, 0);

    ($this->vender)($p, 3.90)->assertSessionHasErrors('items.0.precio_unitario');
    ($this->vender)($p, 4.00)->assertSessionHasNoErrors();
});

it('el POS recibe el costo promedio del almacén de ventas', function () {
    config(['facturamac.base_url' => 'https://facturamac.test']);
    $p = ($this->conCpp)(3.04, 3.1823);

    $this->get(route('pos.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('productos', fn ($ps) => collect($ps)->firstWhere('id', $p->id)['stock_costo_promedio'] == 3.1823));
});
