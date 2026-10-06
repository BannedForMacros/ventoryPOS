<?php

use App\Models\AjusteInventario;
use App\Models\CierreInventario;
use App\Models\Entrada;
use App\Models\Salida;
use App\Models\SalidaTipo;
use App\Models\Stock;
use App\Services\KardexService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión de inventario (oct 2026) — cierres de inventario y fechas.
 *
 *  - Un cierre de un día pasado (o editarlo después) se comparaba contra el
 *    stock de HOY: las ventas/salidas posteriores se volvían "sobrante" y el
 *    cierre las borraba.
 *  - En modo precargado, lo vendido entre cargar el formulario y guardarlo se
 *    convertía en sobrante.
 *  - Ajustes, salidas y cierres fechados en o antes del inventario inicial se
 *    aceptaban y luego desaparecían sin aviso en el "Recalcular".
 *  - El cierre aceptaba fecha futura.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_costo' => 6, 'stock_inicial' => 0]);
    $this->alm = $this->env->almacen->id;
});

function rivCfStock(int $almacenId, int $productoId): float
{
    return (float) (Stock::where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('cantidad') ?? 0);
}

/** Compra confirmada de $cantidad en la fecha indicada. */
function rivCfCompra($test, float $cantidad, string $fecha): void
{
    $e = Entrada::create([
        'empresa_id' => $test->env->empresa->id, 'almacen_id' => $test->alm, 'user_id' => $test->env->admin->id,
        'tipo' => 'compra', 'fecha' => $fecha, 'estado' => 'borrador', 'total' => $cantidad * 6,
        'monto_pagado' => 0, 'estado_pago' => 'pendiente',
    ]);
    $e->detalles()->create([
        'producto_id' => $test->producto->id, 'unidad_medida_id' => $test->env->unidad->id,
        'cantidad' => $cantidad, 'factor_conversion' => 1, 'cantidad_base' => $cantidad,
        'precio_costo' => 6, 'subtotal' => $cantidad * 6,
    ]);
    $e->confirmar();
}

function rivCfAjusteSalida($test, float $cantidad, string $fecha): void
{
    $test->post(route('inventario.ajustes.store'), [
        'almacen_id' => $test->alm, 'producto_id' => $test->producto->id, 'tipo' => 'salida',
        'cantidad' => $cantidad, 'fecha' => $fecha, 'motivo' => 'Venta de mostrador',
    ])->assertSessionHasNoErrors();
}

it('un cierre de un día pasado, y su edición, no borran las salidas posteriores', function () {
    rivCfCompra($this, 20, now()->subDays(10)->toDateString());
    $fechaCierre = now()->subDays(7)->toDateString();
    rivCfAjusteSalida($this, 5, now()->subDays(3)->toDateString());   // DESPUÉS del cierre
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(15.0);

    // Ese día había 20 en sistema y se contaron 18 → faltan 2.
    $this->post(route('inventario.cierres.store'), [
        'almacen_id' => $this->alm, 'fecha' => $fechaCierre, 'confirmar' => true,
        'items' => [['producto_id' => $this->producto->id, 'stock_declarado' => 18]],
    ])->assertSessionHasNoErrors();

    $cierre = CierreInventario::where('almacen_id', $this->alm)->latest('id')->firstOrFail();
    $item   = $cierre->items()->firstOrFail();
    expect((float) $item->stock_sistema)->toBe(20.0);
    expect((float) $item->diferencia)->toBe(-2.0);
    // 20 − 2 (faltante del cierre) − 5 (salida posterior) = 13. Antes: 18.
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(13.0);

    // Editar sin tocar la cantidad: la diferencia se conserva.
    $this->put(route('inventario.cierres.update', $cierre), [
        'observacion' => 'revisado',
        'items' => [['producto_id' => $this->producto->id, 'stock_sistema' => 20, 'stock_declarado' => 18]],
    ])->assertSessionHasNoErrors();
    expect((float) $cierre->items()->value('diferencia'))->toBe(-2.0);
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(13.0);

    // Corregir el conteo a 17: se recalcula contra el saldo de ESE día (20).
    $this->put(route('inventario.cierres.update', $cierre), [
        'items' => [['producto_id' => $this->producto->id, 'stock_sistema' => 20, 'stock_declarado' => 17]],
    ])->assertSessionHasNoErrors();
    expect((float) $cierre->items()->value('diferencia'))->toBe(-3.0);
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(12.0);
});

it('en modo precargado lo vendido entre cargar y guardar no se vuelve sobrante', function () {
    $this->env->empresa->update(['cierre_precarga_stock' => true]);
    rivCfCompra($this, 15, now()->subDays(2)->toDateString());

    // El formulario se cargó con 15 en sistema (precargado: declarado = 15)…
    $vistoAlCargar = 15;
    // …y antes de guardar se vendieron 2.
    rivCfAjusteSalida($this, 2, now()->toDateString());
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(13.0);

    $this->post(route('inventario.cierres.store'), [
        'almacen_id' => $this->alm, 'fecha' => now()->toDateString(), 'confirmar' => true,
        'items' => [['producto_id' => $this->producto->id, 'stock_sistema' => $vistoAlCargar, 'stock_declarado' => $vistoAlCargar]],
    ])->assertSessionHasNoErrors();

    $item = CierreInventario::where('almacen_id', $this->alm)->latest('id')->firstOrFail()->items()->firstOrFail();
    expect((float) $item->diferencia)->toBe(0.0);
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(13.0);
});

it('rechaza ajustes, salidas y cierres en o antes del inventario inicial, y cierres con fecha futura', function () {
    $corte = now()->subDays(5)->toDateString();
    DB::table('stock_iniciales')->insert([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->alm, 'producto_id' => $this->producto->id,
        'fecha' => $corte, 'cantidad' => 30, 'costo' => 6, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(KardexService::class)->reconstruirPar($this->alm, $this->producto->id);
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(30.0);

    // Ajuste el mismo día del corte.
    $this->post(route('inventario.ajustes.store'), [
        'almacen_id' => $this->alm, 'producto_id' => $this->producto->id, 'tipo' => 'salida',
        'cantidad' => 4, 'fecha' => $corte, 'motivo' => 'Merma',
    ])->assertSessionHasErrors('fecha');
    expect(AjusteInventario::where('almacen_id', $this->alm)->count())->toBe(0);

    // Salida anterior al corte.
    $tipo = SalidaTipo::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Merma', 'slug' => 'merma', 'activo' => true]);
    $this->post(route('inventario.salidas.store'), [
        'almacen_id' => $this->alm, 'salida_tipo_id' => $tipo->id, 'fecha' => now()->subDays(6)->toDateString(),
        'confirmar' => true,
        'detalles' => [['producto_id' => $this->producto->id, 'unidad_medida_id' => $this->env->unidad->id, 'cantidad' => 3, 'factor_conversion' => 1]],
    ])->assertSessionHasErrors('fecha');
    expect(Salida::where('almacen_id', $this->alm)->count())->toBe(0);

    // Cierre del día del corte.
    $this->post(route('inventario.cierres.store'), [
        'almacen_id' => $this->alm, 'fecha' => $corte, 'confirmar' => true,
        'items' => [['producto_id' => $this->producto->id, 'stock_declarado' => 25]],
    ])->assertSessionHasErrors('fecha');

    // Cierre con fecha futura.
    $this->post(route('inventario.cierres.store'), [
        'almacen_id' => $this->alm, 'fecha' => now()->addDay()->toDateString(), 'confirmar' => true,
        'items' => [['producto_id' => $this->producto->id, 'stock_declarado' => 25]],
    ])->assertSessionHasErrors('fecha');

    expect(CierreInventario::where('almacen_id', $this->alm)->count())->toBe(0);
    // Nada movió el stock (antes el ajuste lo bajaba y el "Recalcular" lo devolvía).
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(30.0);

    // Un día después del corte sí se acepta.
    $this->post(route('inventario.ajustes.store'), [
        'almacen_id' => $this->alm, 'producto_id' => $this->producto->id, 'tipo' => 'salida',
        'cantidad' => 4, 'fecha' => now()->subDays(4)->toDateString(), 'motivo' => 'Merma',
    ])->assertSessionHasNoErrors();
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(26.0);
});

it('INV-8 — en modo precargado un cierre de un día pasado compara el conteo con el saldo de ESE día', function () {
    $this->env->empresa->update(['cierre_precarga_stock' => true]);
    rivCfCompra($this, 50, now()->subDays(5)->toDateString());
    $fechaCierre = now()->subDays(3)->toDateString();
    rivCfAjusteSalida($this, 5, now()->subDay()->toDateString());   // DESPUÉS del día del cierre
    expect(rivCfStock($this->alm, $this->producto->id))->toBe(45.0);

    // Con la fecha, el formulario precarga el saldo de ese día (50), no el de hoy.
    $r = $this->getJson(route('inventario.cierres.productos', ['almacen_id' => $this->alm, 'fecha' => $fechaCierre]))->json();
    expect((float) collect($r['productos'])->firstWhere('id', $this->producto->id)['stock_sistema'])->toBe(50.0);

    // El formulario viejo precargó 45 (stock de hoy) y el usuario escribió 48,
    // lo que contó ese día: faltan 2 (antes salía sobrante +3).
    $this->post(route('inventario.cierres.store'), [
        'almacen_id' => $this->alm, 'fecha' => $fechaCierre,
        'items' => [
            ['producto_id' => $this->producto->id, 'stock_sistema' => 45, 'stock_declarado' => 48],
        ],
    ])->assertSessionHasNoErrors();
    $item = CierreInventario::where('almacen_id', $this->alm)->latest('id')->firstOrFail()->items()->firstOrFail();
    expect((float) $item->stock_sistema)->toBe(50.0)
        ->and((float) $item->diferencia)->toBe(-2.0);

    // Lo que no se tocó (declarado = lo precargado) queda sin diferencia.
    $this->post(route('inventario.cierres.store'), [
        'almacen_id' => $this->alm, 'fecha' => $fechaCierre,
        'items' => [['producto_id' => $this->producto->id, 'stock_sistema' => 45, 'stock_declarado' => 45]],
    ])->assertSessionHasNoErrors();
    $item = CierreInventario::where('almacen_id', $this->alm)->latest('id')->firstOrFail()->items()->firstOrFail();
    expect((float) $item->diferencia)->toBe(0.0);
});
