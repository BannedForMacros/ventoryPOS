<?php

use App\Models\Entrada;
use App\Models\ProductoUnidad;
use App\Models\Stock;
use App\Models\UnidadMedida;
use App\Services\KardexService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión de inventario (oct 2026) — costo promedio con presentaciones.
 *
 * En una entrada, precio_costo es el precio de la PRESENTACIÓN que se compró
 * (subtotal = cantidad × precio_costo; el formulario lo muestra "c/u" de la
 * presentación). Al stock entra cantidad_base = cantidad × factor. Antes el
 * costo promedio se calculaba con precio_costo como si fuera por unidad base:
 * 2 cajas de 12 a S/ 120 dejaban la unidad a S/ 120 en vez de S/ 10.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_costo' => 0, 'stock_inicial' => 0]);

    $this->caja = UnidadMedida::create([
        'empresa_id' => $this->env->empresa->id, 'nombre' => 'Caja x12', 'abreviatura' => 'CJ12', 'activo' => true,
    ]);
    ProductoUnidad::create([
        'producto_id' => $this->producto->id, 'unidad_medida_id' => $this->caja->id, 'es_base' => false,
        'factor_conversion' => 12, 'tipo_precio' => 'fijo', 'precio_venta' => 150, 'precio_costo' => 0, 'activo' => true,
    ]);
});

function rivCppEntrada($test, array $detalle): void
{
    $test->post(route('inventario.entradas.store'), [
        'almacen_id' => $test->env->almacen->id,
        'tipo'       => 'compra',
        'fecha'      => now()->toDateString(),
        'confirmar'  => true,
        'detalles'   => [array_merge([
            'producto_id'      => $test->producto->id,
            'unidad_medida_id' => $test->caja->id,
        ], $detalle)],
    ])->assertSessionHasNoErrors();
}

it('comprar en cajas deja el costo promedio por unidad base (en vivo, kardex y reconstruido)', function () {
    // 2 cajas × S/ 120 = S/ 240 por 24 unidades → S/ 10 la unidad.
    rivCppEntrada($this, ['cantidad' => 2, 'factor_conversion' => 12, 'precio_costo' => 120]);

    $entrada = Entrada::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect((float) $entrada->total)->toBe(240.0);

    $stock = Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $this->producto->id)->firstOrFail();
    expect((float) $stock->cantidad)->toBe(24.0);
    expect((float) $stock->costo_promedio)->toBe(10.0);

    $fila = DB::table('movimientos_inventario')->where('producto_id', $this->producto->id)->where('tipo', 'entrada')->first();
    expect((float) $fila->costo_unitario)->toBe(10.0);

    // El motor único (Recalcular) da exactamente lo mismo.
    $r = app(KardexService::class)->reconstruirPar($this->env->almacen->id, $this->producto->id, simular: true);
    expect($r['costo_despues'])->toBe(10.0);
    expect($r['stock_cambia'])->toBeFalse();
});

it('el auditor de CPP por factor solo lee: muestra el costo inflado y el corregido sin escribir', function () {
    rivCppEntrada($this, ['cantidad' => 2, 'factor_conversion' => 12, 'precio_costo' => 120]);

    // Simula el dato viejo: costo inflado ×12 como lo dejaba el motor anterior.
    Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $this->producto->id)
        ->update(['costo_promedio' => 120]);
    $kardexAntes = DB::table('movimientos_inventario')->where('producto_id', $this->producto->id)->get()->toArray();

    $codigo = Artisan::call('inventario:auditar-cpp-factor', ['--empresa' => $this->env->empresa->id]);
    $salida = Artisan::output();

    expect($codigo)->toBe(0);
    expect($salida)->toContain('SOLO LECTURA')
        ->and($salida)->toContain('120.0000')
        ->and($salida)->toContain('10.0000')
        // INV-6: avisa que el autocontrol nocturno la aplica solo (no "solo con Recalcular").
        ->and($salida)->toContain('inventario:autocontrol');

    // No escribió nada.
    expect((float) Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $this->producto->id)->value('costo_promedio'))->toBe(120.0);
    expect(DB::table('movimientos_inventario')->where('producto_id', $this->producto->id)->get()->toArray())->toEqual($kardexAntes);
});

it('en entradas el factor sale del catálogo, no del navegador', function () {
    // El navegador manda factor 1000 para la caja de 12.
    rivCppEntrada($this, ['cantidad' => 1, 'factor_conversion' => 1000, 'precio_costo' => 120]);

    $d = Entrada::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail()->detalles()->firstOrFail();
    expect((float) $d->factor_conversion)->toBe(12.0);
    expect((float) $d->cantidad_base)->toBe(12.0);
    expect((float) Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $this->producto->id)->value('cantidad'))->toBe(12.0);

    // Una presentación de otro producto se rechaza.
    $ajena = UnidadMedida::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Saco', 'abreviatura' => 'SCO', 'activo' => true]);
    $this->post(route('inventario.entradas.store'), [
        'almacen_id' => $this->env->almacen->id, 'tipo' => 'compra', 'fecha' => now()->toDateString(),
        'detalles'   => [['producto_id' => $this->producto->id, 'unidad_medida_id' => $ajena->id, 'cantidad' => 1, 'factor_conversion' => 1, 'precio_costo' => 5]],
    ])->assertSessionHasErrors('detalles.0.unidad_medida_id');
});

it('INV-4 — editar una entrada vieja conserva el factor con que se registró cada línea', function () {
    // 10 cajas de 12 a S/ 120 → 120 unidades base.
    rivCppEntrada($this, ['cantidad' => 10, 'factor_conversion' => 12, 'precio_costo' => 120]);
    $entrada = Entrada::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    $stock = fn () => (float) Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $this->producto->id)->value('cantidad');
    expect($stock())->toBe(120.0);

    // Después el catálogo cambia la caja a 6 unidades.
    ProductoUnidad::where('producto_id', $this->producto->id)->where('unidad_medida_id', $this->caja->id)
        ->update(['factor_conversion' => 6]);

    // Se edita la entrada solo para corregir el número de factura.
    $this->put(route('inventario.entradas.update', $entrada), [
        'almacen_id' => $this->env->almacen->id, 'tipo' => 'compra', 'fecha' => now()->toDateString(),
        'numero_documento' => 'F001-CORREGIDA',
        'detalles' => [[
            'producto_id' => $this->producto->id, 'unidad_medida_id' => $this->caja->id,
            'cantidad' => 10, 'precio_costo' => 120,
        ]],
    ])->assertSessionHasNoErrors();

    $d = $entrada->fresh()->detalles()->first();
    expect((float) $d->factor_conversion)->toBe(12.0)   // antes: 6 (el del catálogo de hoy)
        ->and((float) $d->cantidad_base)->toBe(120.0)
        ->and($stock())->toBe(120.0);                    // antes: 60, sin que nadie lo pidiera

    // Una línea NUEVA sí toma el factor actual del catálogo.
    $this->put(route('inventario.entradas.update', $entrada), [
        'almacen_id' => $this->env->almacen->id, 'tipo' => 'compra', 'fecha' => now()->toDateString(),
        'detalles' => [[
            'producto_id' => $this->producto->id, 'unidad_medida_id' => $this->env->unidad->id,
            'cantidad' => 5, 'precio_costo' => 10,
        ]],
    ])->assertSessionHasNoErrors();
    expect($stock())->toBe(5.0);
});
