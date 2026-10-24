<?php

use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Lista de ventas: por defecto solo las ACTIVAS. El select permite ver Todas,
 * Inactivas (anuladas con nota de crédito) o Anuladas.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->env->abrirTurno($this->env->admin);
    config(['facturamac.base_url' => 'https://facturamac.test']);
    $prod = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 4, 'stock_inicial' => 50]);

    $vender = function () use ($prod) {
        $this->post(route('ventas.store'), [
            'tipo_comprobante' => 'ticket',
            'items' => [[
                'producto_id' => $prod->id, 'producto_unidad_id' => $prod->unidadBase->id,
                'cantidad' => 1, 'precio_unitario' => 10, 'incluye_igv' => true,
            ]],
            'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
        ])->assertSessionHasNoErrors();
        return Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    };

    $this->activa  = $vender();
    $this->anulada = $vender();
    app(VentaService::class)->anular($this->anulada, $this->env->admin, 'Prueba');
    // Anulada con nota de crédito: sigue 'completada' con la marca de la devolución.
    $this->conNc = $vender();
    DB::statement('ALTER TABLE ventas DISABLE TRIGGER ALL');
    DB::table('ventas')->where('id', $this->conNc->id)->update(['anulada_nc_devolucion_id' => 999999]);
    DB::statement('ALTER TABLE ventas ENABLE TRIGGER ALL');

    $this->ids = fn (array $params = []) => collect($this->get(route('ventas.index', $params))
        ->viewData('page')['props']['ventas']['data'])->pluck('id')->sort()->values()->all();
});

it('por defecto solo salen las activas', function () {
    expect(($this->ids)())->toBe([$this->activa->id]);
    expect($this->get(route('ventas.index'))->viewData('page')['props']['filters']['estado'])->toBe('activas');
});

it('el select filtra activas, todas, inactivas y anuladas', function () {
    expect(($this->ids)(['estado' => 'activas']))->toBe([$this->activa->id])
        ->and(($this->ids)(['estado' => 'todas']))->toBe([$this->activa->id, $this->anulada->id, $this->conNc->id])
        ->and(($this->ids)(['estado' => 'inactivas']))->toBe([$this->conNc->id])
        ->and(($this->ids)(['estado' => 'anuladas']))->toBe([$this->anulada->id])
        // Un valor raro no muestra todo: vuelve a las activas.
        ->and(($this->ids)(['estado' => 'xyz']))->toBe([$this->activa->id]);
});
