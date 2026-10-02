<?php

use App\Models\Cliente;
use App\Models\Venta;
use App\Services\Facturacion\VentaAContrato;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestEnv;

/**
 * Fecha de emisión de la factura elegida en el POS (opcional por empresa).
 *
 * Las reglas que no se pueden romper:
 *   · la VENTA sigue siendo de hoy (caja, turno, reportes); solo la factura lleva
 *     la fecha elegida;
 *   · SUNAT acepta hasta 3 días atrás: fuera de eso no se cobra, porque FacturaMac
 *     no lo emitiría y la venta quedaría sin comprobante;
 *   · el selector solo existe si la empresa lo activó, y solo para facturas;
 *   · el turno reabierto de un admin hereda la misma ventana.
 */
beforeEach(function () {
    config(['facturamac.base_url' => 'http://emisor.test']);
    Http::fake(['*/api/v1/configuracion' => Http::response(['modo' => 'produccion', 'emision_activa' => true], 200)]);
    // El job de emisión no debe salir hacia FacturaMac en estos tests.
    Queue::fake();

    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->env->conectarFacturacion();
    $this->env->empresa->update(['pos_fecha_emision_factura' => true]);
    $this->actingAs($this->env->admin);

    $this->clienteRuc = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'RUC', 'numero_documento' => '20612792438',
        'razon_social' => 'SOMAVAP GROUP S.R.L.', 'direccion' => 'CAL. MANUEL SEOANE NRO 600', 'es_cliente_general' => false, 'activo' => true,
    ]);
    $this->producto = $this->env->crearProducto(['precio_venta' => 118, 'precio_costo' => 50, 'stock_inicial' => 50]);
});

/** La última venta de la empresa del test (la base local tiene ventas reales). */
function ventaDelEntorno(): Venta
{
    return Venta::withoutGlobalScopes()->where('empresa_id', test()->env->empresa->id)->latest('id')->firstOrFail();
}

function ventaFactura(array $extra = []): array
{
    $env = test()->env;

    return array_merge([
        'tipo_comprobante' => 'factura',
        'cliente_id'       => test()->clienteRuc->id,
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 118,
            'incluye_igv'        => true,
        ]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 118]],
    ], $extra);
}

it('el POS recibe la ventana de SUNAT y si debe mostrar el selector', function () {
    $this->env->abrirTurno($this->env->admin);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('permiteFechaFactura', true)
        ->where('ventanaEmision.maxima', now()->toDateString())
        ->where('ventanaEmision.minima', now()->subDays(3)->toDateString()));
});

it('una factura con fecha de hace 2 días se registra HOY y se emite con esa fecha', function () {
    $this->env->abrirTurno($this->env->admin);
    $hace2 = now()->subDays(2)->toDateString();

    $this->post(route('ventas.store'), ventaFactura(['fecha_emision' => $hace2]))->assertSessionHasNoErrors();

    $venta = ventaDelEntorno();
    expect($venta->fecha_emision->toDateString())->toBe($hace2);
    // La venta (caja, turno, reportes) sigue siendo de hoy.
    expect($venta->fecha_venta->toDateString())->toBe(now()->toDateString());

    $payload = app(VentaAContrato::class)->mapear($venta->fresh())->aArray();
    expect($payload['fecha_emision'])->toBe($hace2);
});

it('una factura con fecha de hace 5 días no se cobra', function () {
    $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), ventaFactura(['fecha_emision' => now()->subDays(5)->toDateString()]))
        ->assertSessionHasErrors('fecha_emision');

    expect(Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('una fecha futura tampoco', function () {
    $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), ventaFactura(['fecha_emision' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('fecha_emision');
});

it('sin el selector activo en la empresa la fecha no se acepta', function () {
    $this->env->empresa->update(['pos_fecha_emision_factura' => false]);
    $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), ventaFactura(['fecha_emision' => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors('fecha_emision');
});

it('la fecha solo se elige en una factura, no en una boleta', function () {
    $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), ventaFactura([
        'tipo_comprobante' => 'boleta',
        'cliente_id'       => $this->env->clienteGeneral->id,
        'fecha_emision'    => now()->subDay()->toDateString(),
    ]))->assertSessionHasErrors('fecha_emision');
});

it('sin fecha elegida la factura sale con la fecha de la venta, como siempre', function () {
    $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), ventaFactura())->assertSessionHasNoErrors();

    $venta = ventaDelEntorno();
    expect($venta->fecha_emision)->toBeNull();
    expect(app(VentaAContrato::class)->mapear($venta->fresh())->aArray()['fecha_emision'])->toBe(now()->toDateString());
});

it('un turno reabierto de hace más de 3 días no deja cobrar una factura o boleta', function () {
    $turno = $this->env->abrirTurno($this->env->admin);
    $turno->update(['fecha_apertura' => now()->subDays(10)]);

    $this->post(route('ventas.store'), ventaFactura([
        'turno_id'    => $turno->id,
        'fecha_venta' => now()->subDays(10)->toDateString(),
    ]))->assertSessionHasErrors('fecha_venta');

    expect(Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('ese mismo turno reabierto sí deja registrar un ticket', function () {
    $turno = $this->env->abrirTurno($this->env->admin);
    $turno->update(['fecha_apertura' => now()->subDays(10)]);

    $this->post(route('ventas.store'), ventaFactura([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->env->clienteGeneral->id,
        'turno_id'         => $turno->id,
        'fecha_venta'      => now()->subDays(10)->toDateString(),
    ]))->assertSessionHasNoErrors();

    // Que no haya errores no basta: la venta tiene que existir, con la fecha del turno.
    $venta = ventaDelEntorno();
    expect($venta->tipo_comprobante)->toBe('ticket');
    expect($venta->fecha_venta->toDateString())->toBe(now()->subDays(10)->toDateString());
});

it('la empresa activa el selector desde Configuración → Empresas', function () {
    $this->env->empresa->update(['pos_fecha_emision_factura' => false]);

    expect($this->env->empresa->fresh()->pos_fecha_emision_factura)->toBeFalse();
    expect((new \App\Http\Requests\Configuracion\EmpresaRequest())->rules())->toHaveKey('pos_fecha_emision_factura');
});
