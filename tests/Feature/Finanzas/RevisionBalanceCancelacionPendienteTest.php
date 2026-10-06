<?php

use App\Models\ClienteAnticipo;
use App\Services\BalanceDiarioService;
use App\Services\CambiosCierreService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Revisión del balance — P1: cancelar lo pendiente de un pedido tiene su propia
 * fecha (cliente_anticipo_cancelaciones.fecha). Cancelar HOY no puede borrar el
 * pasivo de los días anteriores: el cliente todavía esperaba esa mercadería.
 * Y si se carga con fecha de un día ya cerrado, el detector lo explica.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
    $this->ayer = now()->subDay()->toDateString();
    $this->hoy = now()->toDateString();

    // AYER: venta pagada con 5 und pendientes por entregar (5 × 20 = 100).
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante'  => 'ticket',
        'fecha_venta'       => $this->ayer,
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad' => 5, 'precio_unitario' => 20, 'cantidad_pendiente' => 5,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
    ], $this->env->admin, $this->env->abrirTurno());
    $this->anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->firstOrFail();

    $this->cancelar = fn (string $fecha) => $this->post(route('finanzas.anticipos.items.cancelar-pendiente',
        [$this->anticipo, $this->anticipo->items->first()]), [
        'cantidad' => 2, 'motivo' => 'El cliente ya no lo quiere', 'fecha' => $fecha,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
});

it('una cancelación de hoy no baja el pasivo del balance de ayer (línea y modal)', function () {
    ($this->cancelar)($this->hoy);
    expect((float) $this->anticipo->fresh()->saldo)->toBe(60.0);

    // Ayer el pedido seguía completo: 100 (antes salía 60).
    $ayer = $this->balances->generar($this->env->admin, $this->ayer);
    expect((float) $ayer->items->firstWhere('categoria', 'anticipo_cliente')->monto)->toBe(100.0);

    $modal = $this->getJson(route('finanzas.balance.detalle', ['fecha' => $this->ayer, 'categoria' => 'anticipo_cliente']))->json();
    expect((float) $modal['cards'][0]['valor'])->toBe(100.0);
    expect($modal['grupos'][0]['items'][0]['modalidad'])->toContain('5 ×');

    // Hoy ya bajó.
    $hoy = $this->balances->generar($this->env->admin, $this->hoy);
    expect((float) $hoy->items->firstWhere('categoria', 'anticipo_cliente')->monto)->toBe(60.0);
});

it('una cancelación cargada después del cierre con fecha de ese día se detecta', function () {
    $cerrado = $this->balances->generar($this->env->admin, $this->ayer);
    $this->balances->confirmar($cerrado, $this->env->admin);
    $neto = (float) $cerrado->fresh()->balance_neto;

    $this->travel(10)->minutes();
    ($this->cancelar)($this->ayer);

    $r = app(CambiosCierreService::class)->analizar($cerrado->fresh());
    $anticipos = collect($r['categorias'])->firstWhere('categoria', 'anticipo_cliente');
    expect($anticipos['diferencia'])->toBe(-40.0);
    expect($anticipos['sin_documento'])->toBeFalse();
    expect(collect($anticipos['documentos'])->pluck('tipo'))->toContain('Cancelación de pendiente registrada después');

    // Solo lectura: el día cerrado conserva su patrimonio.
    expect((float) $this->balances->generar($this->env->admin, $this->ayer)->balance_neto)->toBe($neto);
});
