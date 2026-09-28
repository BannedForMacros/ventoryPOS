<?php

use App\Models\Producto;
use App\Services\VentaService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * Reportes de ventas y utilidad: ordenar, buscar, cambiar de vista o paginar
 * piden solo la tabla (partial reload) y no alteran el resto del reporte.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);

    $this->vender = function (Producto $producto, float $cantidad, float $precio) {
        return $this->service->crear([
            'tipo_comprobante' => 'ticket',
            'items' => [[
                'producto_id'        => $producto->id,
                'producto_unidad_id' => $producto->unidadBase->id,
                'cantidad'           => $cantidad,
                'precio_unitario'    => $precio,
            ]],
            'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => $cantidad * $precio]],
        ], $this->env->admin, $this->turno);
    };

    // Uno con ganancia, uno vendido bajo el costo y uno sin costo registrado.
    $this->ganancia = $this->env->crearProducto(['nombre' => 'Cemento con ganancia', 'precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);
    $this->perdida  = $this->env->crearProducto(['nombre' => 'Fierro con pérdida', 'precio_venta' => 10, 'precio_costo' => 12, 'stock_inicial' => 50]);
    $this->sinCosto = $this->env->crearProducto(['nombre' => 'Flete sin costo', 'precio_venta' => 10, 'precio_costo' => 0, 'stock_inicial' => 50]);
    ($this->vender)($this->ganancia, 5, 10);
    ($this->vender)($this->perdida, 2, 10);
    ($this->vender)($this->sinCosto, 1, 10);
});

it('utilidad: al ordenar o buscar solo recarga la tabla y sus contadores', function () {
    $this->get(route('reportes.utilidad', ['orden' => 'margen']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly(['productos', 'conteos', 'filters'], fn (Assert $parcial) => $parcial
                ->missing('kpis')
                ->missing('categorias')
                ->where('filters.orden', 'margen')));
});

it('utilidad: categorías y contadores miran todo el periodo aunque se busque', function () {
    $completo = $this->get(route('reportes.utilidad'))->viewData('page')['props'];
    $buscando = $this->get(route('reportes.utilidad', ['buscar' => 'cemento']))->viewData('page')['props'];

    expect($buscando['productos']['data'])->toHaveCount(1);
    expect($buscando['productos']['data'][0]['producto_nombre'])->toBe('Cemento con ganancia');

    // La búsqueda no cambia ni las categorías ni los contadores.
    expect($buscando['categorias'])->toEqual($completo['categorias']);
    expect($buscando['conteos'])->toEqual($completo['conteos']);
    expect($completo['conteos']['total'])->toBe(3);
    expect($completo['conteos']['perdida'])->toBe(1);
    expect($completo['conteos']['sincosto'])->toBe(1);
});

it('utilidad: las vistas filtran en el servidor', function () {
    $perdida = $this->get(route('reportes.utilidad', ['vista' => 'perdida']))->viewData('page')['props'];
    expect(collect($perdida['productos']['data'])->pluck('producto_nombre')->all())->toBe(['Fierro con pérdida']);
    expect($perdida['filters']['vista'])->toBe('perdida');

    $sinCosto = $this->get(route('reportes.utilidad', ['vista' => 'sincosto']))->viewData('page')['props'];
    expect(collect($sinCosto['productos']['data'])->pluck('producto_nombre')->all())->toBe(['Flete sin costo']);

    // Una vista inventada se ignora: se muestran todos.
    $invalida = $this->get(route('reportes.utilidad', ['vista' => 'otra']))->viewData('page')['props'];
    expect($invalida['productos']['total'])->toBe(3);
    expect($invalida['filters']['vista'])->toBeNull();
});

it('utilidad: la tabla se pagina en la base de datos', function () {
    // 30 productos más, cada uno con su venta → 33 en total.
    foreach (range(1, 30) as $i) {
        $p = $this->env->crearProducto(['nombre' => "Extra {$i}", 'precio_venta' => 10, 'precio_costo' => 5, 'stock_inicial' => 5]);
        ($this->vender)($p, 1, 10);
    }

    $pag1 = $this->get(route('reportes.utilidad'))->viewData('page')['props']['productos'];
    expect($pag1['total'])->toBe(33);
    expect($pag1['data'])->toHaveCount(25);
    expect($pag1['last_page'])->toBe(2);

    $pag2 = $this->get(route('reportes.utilidad', ['page' => 2]))->viewData('page')['props']['productos'];
    expect($pag2['data'])->toHaveCount(8);
});

it('utilidad: tocar una categoría filtra la tabla', function () {
    $otra = \App\Models\Categoria::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Agregados', 'activo' => true]);
    $arena = $this->env->crearProducto(['nombre' => 'Arena fina', 'precio_venta' => 10, 'precio_costo' => 7, 'stock_inicial' => 10]);
    $arena->update(['categoria_id' => $otra->id]);
    ($this->vender)($arena, 1, 10);

    $props = $this->get(route('reportes.utilidad', ['categoria' => $otra->id]))->viewData('page')['props'];
    expect(collect($props['productos']['data'])->pluck('producto_nombre')->all())->toBe(['Arena fina']);

    $cat = collect($props['categorias'])->firstWhere('id', (string) $otra->id);
    expect($cat['productos'])->toBe(1);
    expect((float) $cat['utilidad'])->toBe(3.0);
});

it('utilidad: lista los más vendidos en soles con lo que dejó cada uno', function () {
    $top = $this->get(route('reportes.utilidad'))->viewData('page')['props']['mas_vendidos'];

    // Cemento 5×10 = 50, Fierro 2×10 = 20, Flete 1×10 = 10.
    expect(collect($top)->pluck('producto_nombre')->all())->toBe(['Cemento con ganancia', 'Fierro con pérdida', 'Flete sin costo']);
    expect((float) $top[0]['utilidad'])->toBe(20.0);
    expect((float) $top[1]['utilidad'])->toBe(-4.0);
});

it('ventas: el buscador filtra solo la lista, no el resumen', function () {
    $venta = ($this->vender)($this->ganancia, 1, 10);

    $props = $this->get(route('reportes.ventas', ['buscar' => $venta->numero]))->viewData('page')['props'];

    expect($props['ventas']['total'])->toBe(1);
    expect($props['kpis']['total_ventas'])->toBe(4); // el resumen sigue contando las 4 ventas
});

it('ventas: buscar o paginar recarga solo la lista', function () {
    $this->get(route('reportes.ventas', ['buscar' => 'V-']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly(['ventas', 'filters'], fn (Assert $parcial) => $parcial
                ->missing('kpis')
                ->missing('serie_diaria')
                ->missing('por_comprobante')));
});
