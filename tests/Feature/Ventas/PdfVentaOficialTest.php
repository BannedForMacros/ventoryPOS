<?php

use App\Models\VentaComprobante;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Detalle de venta → PDF: si hay factura/boleta emitida en FacturaMac, se
 * abre la representación impresa OFICIAL; si no, la plantilla interna.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $turno = $this->env->abrirTurno();
    $p = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 5]);
    $this->venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $turno);
});

it('con comprobante emitido, el PDF abre el oficial de FacturaMac', function () {
    $this->venta->update(['tipo_comprobante' => 'boleta']);
    VentaComprobante::create(['venta_id' => $this->venta->id, 'tipo' => '03', 'numero' => 'B001-00000001',
        'estado' => 'aceptado', 'facturamac_id' => 123, 'intentos' => 1]);

    $this->get(route('ventas.pdf', $this->venta))->assertRedirect(route('ventas.comprobante.pdf', $this->venta));
});

it('con ?interno=1 sale la plantilla interna aunque haya comprobante', function () {
    $this->venta->update(['tipo_comprobante' => 'boleta']);
    VentaComprobante::create(['venta_id' => $this->venta->id, 'tipo' => '03', 'numero' => 'B001-00000002',
        'estado' => 'aceptado', 'facturamac_id' => 124, 'intentos' => 1]);

    $r = $this->get(route('ventas.pdf', [$this->venta, 'interno' => 1]));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toContain('application/pdf');
});

it('una nota de venta sigue con la plantilla interna', function () {
    $r = $this->get(route('ventas.pdf', $this->venta));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toContain('application/pdf');
});
