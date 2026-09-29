<?php

use App\Models\Almacen;
use App\Models\ProductoUnidad;
use App\Models\Stock;
use App\Models\UnidadMedida;
use App\Services\InventarioInicialService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Pantalla "Inventario inicial": carga a mano y desde Excel con columnas
 * elegidas por el usuario. El stock arranca de lo contado al cierre de la
 * fecha del conteo y solo suma lo que pasa después.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->alm = $this->env->almacen->id;
});

/** Ajuste de salida CONFIRMADO como documento (lo lee la reconstrucción). */
function iiSalida(TestEnv $env, int $productoId, string $fecha, float $cantidad): void
{
    DB::table('ajustes_inventario')->insert([
        'empresa_id' => $env->empresa->id, 'almacen_id' => $env->almacen->id, 'producto_id' => $productoId,
        'user_id' => $env->admin->id, 'numero' => 'AJ-II' . uniqid(), 'tipo' => 'salida',
        'cantidad_base' => $cantidad, 'fecha' => $fecha, 'estado' => 'confirmado', 'motivo' => 'test',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function iiCaja(TestEnv $env, int $productoId, float $factor = 12): ProductoUnidad
{
    $um = UnidadMedida::create(['empresa_id' => $env->empresa->id, 'nombre' => 'Caja', 'abreviatura' => 'cja', 'activo' => true]);

    return ProductoUnidad::create([
        'producto_id' => $productoId, 'unidad_medida_id' => $um->id, 'es_base' => false,
        'factor_conversion' => $factor, 'tipo_precio' => 'fijo', 'precio_venta' => 100, 'precio_costo' => 0, 'activo' => true,
    ]);
}

it('guarda a mano: el stock arranca de lo contado y solo descuenta lo posterior', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0]);
    $ayer = now()->subDay()->toDateString();
    iiSalida($this->env, $p->id, now()->subDays(3)->toDateString(), 7);   // antes del conteo: ya está dentro
    iiSalida($this->env, $p->id, now()->toDateString(), 5);               // después del conteo: se descuenta

    $this->post(route('inventario.inicial.guardar'), [
        'almacen_id' => $this->alm, 'fecha' => $ayer, 'items' => [['producto_id' => $p->id, 'cantidad' => 25, 'costo' => 7.5]],
    ])->assertRedirect()->assertSessionHas('success');

    $ini = DB::table('stock_iniciales')->where('almacen_id', $this->alm)->where('producto_id', $p->id)->first();
    expect((float) $ini->cantidad)->toBe(25.0)
        ->and((float) $ini->costo)->toBe(7.5)
        ->and(substr($ini->fecha, 0, 10))->toBe($ayer);

    $stock = Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)->first();
    expect((float) $stock->cantidad)->toBe(20.0)
        ->and((float) $stock->costo_promedio)->toBe(7.5);

    expect(DB::table('movimientos_inventario')->where('producto_id', $p->id)->where('tipo', 'inventario_inicial')->exists())->toBeTrue();
});

it('sin costo usa el costo promedio actual; y no acepta fecha futura ni negativos', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 10, 'precio_costo' => 6]);

    $this->post(route('inventario.inicial.guardar'), [
        'almacen_id' => $this->alm, 'fecha' => now()->toDateString(), 'items' => [['producto_id' => $p->id, 'cantidad' => 4, 'costo' => null]],
    ])->assertSessionHas('success');
    expect((float) DB::table('stock_iniciales')->where('producto_id', $p->id)->value('costo'))->toBe(6.0);

    $this->post(route('inventario.inicial.guardar'), [
        'almacen_id' => $this->alm, 'fecha' => now()->addDay()->toDateString(), 'items' => [['producto_id' => $p->id, 'cantidad' => 4]],
    ])->assertSessionHasErrors('fecha');
    $this->post(route('inventario.inicial.guardar'), [
        'almacen_id' => $this->alm, 'fecha' => now()->toDateString(), 'items' => [['producto_id' => $p->id, 'cantidad' => -1]],
    ])->assertSessionHasErrors('items.0.cantidad');
});

it('convierte la unidad del Excel a la unidad base y suma filas repetidas', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'nombre' => 'Cemento Sol Tipo I', 'codigo' => 'CEM-01']);
    $caja = iiCaja($this->env, $p->id, 12);

    app(InventarioInicialService::class)->guardar($this->env->empresa->id, $this->alm, now()->toDateString(), [
        ['producto_id' => $p->id, 'cantidad' => 2, 'producto_unidad_id' => $caja->id, 'costo' => 120],   // 24 und a S/ 10
        ['producto_id' => $p->id, 'cantidad' => 6, 'costo' => 13],                                         // 6 und a S/ 13
    ]);

    $ini = DB::table('stock_iniciales')->where('producto_id', $p->id)->first();
    expect((float) $ini->cantidad)->toBe(30.0)
        ->and((float) $ini->costo)->toBe(10.6);   // (24·10 + 6·13) / 30
});

it('empareja las filas del Excel por código, por nombre y sugiere parecidos', function () {
    $cem = $this->env->crearProducto(['nombre' => 'Cemento Sol Tipo I', 'codigo' => 'CEM-01']);
    iiCaja($this->env, $cem->id, 12);
    $fie = $this->env->crearProducto(['nombre' => 'Fierro 1/2" Siderperú', 'codigo' => 'F12']);
    $this->env->crearProducto(['nombre' => 'Clavo 3 pulgadas', 'codigo' => 'CL3']);
    DB::table('stock_iniciales')->insert([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->alm, 'producto_id' => $fie->id,
        'fecha' => now()->toDateString(), 'cantidad' => 3, 'costo' => 30, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $res = $this->postJson(route('inventario.inicial.emparejar'), ['almacen_id' => $this->alm, 'filas' => [
        ['fila' => 2, 'producto' => 'lo que sea', 'codigo' => 'cem-01', 'cantidad' => '2', 'unidad' => 'Cajas', 'costo' => '120'],
        ['fila' => 3, 'producto' => 'FIERRO 1/2 SIDERPERU', 'cantidad' => '1,500'],
        ['fila' => 4, 'producto' => 'Clavos 3 pulgadas acero', 'cantidad' => 10],
        ['fila' => 5, 'producto' => 'Cemento sol tipo I', 'cantidad' => ''],
        ['fila' => 6, 'producto' => 'fierro 1/2 siderperu', 'cantidad' => '12,5'],
        ['fila' => 7, 'producto' => 'Cemento Sol Tipo I', 'cantidad' => 3, 'unidad' => 'bolsa'],
    ]])->assertOk()->json('filas');

    $f = collect($res)->keyBy('fila');
    expect($f[2]['estado'])->toBe('ok')
        ->and($f[2]['producto']['id'])->toBe($cem->id)
        ->and($f[2]['producto_unidad_id'])->toBe(collect($f[2]['producto']['unidades'])->firstWhere('nombre', 'Caja')['id']);
    expect($f[3]['estado'])->toBe('ok')
        ->and($f[3]['producto']['id'])->toBe($fie->id)
        ->and((float) $f[3]['cantidad'])->toBe(1500.0)
        ->and($f[3]['ya_tenia']['cantidad'])->toEqual(3);
    expect($f[4]['estado'])->toBe('sugerido')
        ->and($f[4]['producto'])->toBeNull()
        ->and($f[4]['sugerencias'][0]['nombre'])->toBe('Clavo 3 pulgadas');
    expect($f[5]['estado'])->toBe('error');
    expect((float) $f[6]['cantidad'])->toBe(12.5)
        ->and($f[6]['repetido_de'])->toBe(3);
    expect($f[7]['estado'])->toBe('unidad');
});

it('quitar el inventario inicial borra la apertura', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0]);
    $srv = app(InventarioInicialService::class);
    $srv->guardar($this->env->empresa->id, $this->alm, now()->toDateString(), [['producto_id' => $p->id, 'cantidad' => 9, 'costo' => 2]]);

    $this->delete(route('inventario.inicial.quitar', $p->id), ['almacen_id' => $this->alm])->assertSessionHas('success');

    expect(DB::table('stock_iniciales')->where('producto_id', $p->id)->exists())->toBeFalse();
});

it('la pantalla muestra el avance y filtra los que faltan cargar', function () {
    $a = $this->env->crearProducto(['nombre' => 'AAA contado']);
    $b = $this->env->crearProducto(['nombre' => 'BBB pendiente']);
    $this->env->crearProducto(['nombre' => 'Servicio de corte', 'tipo' => 'servicio']);
    app(InventarioInicialService::class)->guardar($this->env->empresa->id, $this->alm, now()->toDateString(), [['producto_id' => $a->id, 'cantidad' => 5, 'costo' => 1]]);

    $props = $this->get(route('inventario.inicial.index'))->assertOk()->original->getData()['page']['props'];
    expect($props['resumen']['productos'])->toBe(2)
        ->and($props['resumen']['cargados'])->toBe(1)
        ->and($props['resumen']['valor'])->toEqual(5);

    $props = $this->get(route('inventario.inicial.index', ['estado' => 'pendientes']))->original->getData()['page']['props'];
    expect(collect($props['productos']['data'])->pluck('id')->all())->toBe([$b->id]);
});

it('no deja tocar el almacén de otra empresa', function () {
    $otra = TestEnv::crear();
    $p = $this->env->crearProducto();

    $this->post(route('inventario.inicial.guardar'), [
        'almacen_id' => $otra->almacen->id, 'fecha' => now()->toDateString(), 'items' => [['producto_id' => $p->id, 'cantidad' => 1]],
    ])->assertForbidden();
});

it('lee números como vienen en los Excel peruanos', function (mixed $entrada, ?float $esperado) {
    expect(InventarioInicialService::numero($entrada, null))->toBe($esperado);
})->with([
    ['1,234.50', 1234.5], ['1.234,50', 1234.5], ['12,5', 12.5], ['1,500', 1500.0],
    [' 40 ', 40.0], [7, 7.0], ['S/ 3.20', 3.2], ['', null], ['abc', null],
]);

it('número y unidad pegados o separados son el mismo producto', function () {
    $p = $this->env->crearProducto(['nombre' => 'ACEITE LUBRICANTE 90ML 3 EN 1']);

    $f = $this->postJson(route('inventario.inicial.emparejar'), ['almacen_id' => $this->alm, 'filas' => [
        ['fila' => 2, 'producto' => 'Aceite lubricante 90 ml 3 en 1', 'cantidad' => 5],
        ['fila' => 3, 'producto' => 'Aceite lubricante 90 ml tres en uno', 'cantidad' => 5],
    ]])->json('filas');

    expect($f[0]['producto']['id'])->toBe($p->id)
        ->and($f[1]['estado'])->toBe('sugerido')
        ->and($f[1]['sugerencias'][0]['id'])->toBe($p->id);
});
