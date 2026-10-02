<?php

use App\Models\Venta;
use App\Services\ReporteVentasPdfService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Reporte de ventas para el dueño (PDF): números del resumen y descarga.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);

    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $vender = fn (int $cant, string $metodo, float $desc = 0) => $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => $cant,
            'precio_unitario'    => 10,
            'descuento_item'     => $desc,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo($metodo)->id, 'monto' => $cant * (10 - $desc)]],
    ], $this->env->admin, $this->turno);

    $vender(3, 'efectivo');          // 30
    $vender(5, 'efectivo', 1);       // 45 con S/ 5 de descuento
    $this->anulada = $vender(2, 'efectivo');
    $this->service->anular($this->anulada, $this->env->admin, 'Prueba de venta anulada');
});

function datosReporte($env): array
{
    $hoy = now()->toDateString();

    return app(ReporteVentasPdfService::class)->armar($env->empresa, $hoy, $hoy,
        fn ($d, $h) => Venta::deEmpresa($env->empresa->id)->whereBetween('fecha_venta', [$d . ' 00:00:00', $h . ' 23:59:59']));
}

it('resume total, utilidad, descuentos, anuladas y cobro', function () {
    $r = datosReporte($this->env);
    $k = $r['k'];

    expect($k['total'])->toBe(75.0);
    expect($k['n'])->toBe(2);
    expect($k['ticket'])->toBe(37.5);
    expect($k['costo'])->toBe(48.0);                 // 8 unidades × 6
    expect($k['utilidad'])->toBe(27.0);
    expect($k['descuentos'])->toBe(5.0);
    expect($k['con_desc_n'])->toBe(1);
    expect($k['anuladas_n'])->toBe(1);
    expect($k['anuladas'])->toBe(20.0);

    expect($r['metodos'])->toHaveCount(1);
    expect($r['metodos'][0]['total'])->toBe(75.0);
    expect($r['metodos'][0]['pct'])->toEqual(100);

    expect($r['top'][0]['cantidad'])->toBe(8.0);
    expect(collect($r['semana']['filas'])->sum('total'))->toEqual(75.0);
    expect(collect($r['horas']['filas'])->sum('total'))->toEqual(75.0);
    expect($r['claves'])->not->toBeEmpty();
});

it('descarga el PDF desde el reporte de ventas', function () {
    $res = $this->get(route('reportes.ventas.pdf', ['fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->toDateString()]));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect(substr($res->getContent(), 0, 4))->toBe('%PDF');
});

it('el PDF respeta los filtros de la pantalla', function () {
    $otro = $this->env->metodo('yape');
    $hoy  = now()->toDateString();
    $request = Illuminate\Http\Request::create('/', 'GET', ['metodo_pago_id' => $otro->id]);

    // Ninguna venta se pagó con Yape: el resumen filtrado sale en cero.
    $r = app(ReporteVentasPdfService::class)->armar($this->env->empresa, $hoy, $hoy,
        fn ($d, $h) => Venta::deEmpresa($this->env->empresa->id)
            ->whereBetween('fecha_venta', [$d . ' 00:00:00', $h . ' 23:59:59'])
            ->whereHas('pagos', fn ($p) => $p->where('metodo_pago_id', $request->metodo_pago_id)));

    expect($r['k']['total'])->toBe(0.0);
    expect($r['claves'])->toBe(['No hubo ventas en este período.']);
});

it('arma el PDF aunque el período no tenga ventas', function () {
    $this->get(route('reportes.ventas.pdf', ['fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-01-31']))
        ->assertOk();
});
