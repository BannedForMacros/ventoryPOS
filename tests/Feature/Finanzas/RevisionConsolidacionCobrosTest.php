<?php

use App\Models\Cliente;
use App\Models\CuentaMovimiento;
use App\Models\TurnoConsolidacion;
use App\Models\VentaAbono;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Revisión — P1: la consolidación de caja espera por cada método lo que el
 * turno COBRÓ (ventas sin vuelto + abonos + anticipos − reembolsos), igual que
 * el cierre (Turno::cobrosPorMetodo). Antes solo miraba las ventas: un abono de
 * crédito por Yape aparecía como "sobrante" y se asentaba un ingreso falso.
 */
it('un abono por Yape del turno no se asienta como sobrante al consolidar', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $turno = $env->abrirTurno();
    $yape = $env->metodo('yape');
    $producto = $env->crearProducto(['precio_venta' => 50, 'stock_inicial' => 10]);

    // Venta de contado por Yape: 50.
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
                     'cantidad' => 1, 'precio_unitario' => 50]],
        'pagos' => [['metodo_pago_id' => $yape->id, 'monto' => 50]],
    ], $env->admin, $turno);

    // Abono de un crédito anterior por Yape, cobrado en este turno: 30.
    $cliente = Cliente::create(['empresa_id' => $env->empresa->id, 'nombres' => 'Deudor', 'apellidos' => 'RB',
        'tipo_documento' => 'DNI', 'numero_documento' => '71717171', 'activo' => true]);
    $turnoViejo = $env->abrirTurno(); // el crédito es de otro turno; solo el abono es de este
    $credito = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket', 'cliente_id' => $cliente->id, 'es_credito' => true,
        'fecha_venta' => now()->subDays(3)->toDateString(),
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
                     'cantidad' => 1, 'precio_unitario' => 50]],
        'pagos' => [],
    ], $env->admin, $turnoViejo);
    VentaAbono::create(['venta_id' => $credito->id, 'user_id' => $env->admin->id, 'turno_id' => $turno->id,
        'metodo_pago_id' => $yape->id, 'fecha' => now()->toDateString(), 'monto' => 30]);

    $turno->update(['estado' => 'cerrado', 'fecha_cierre' => now(), 'monto_cierre_esperado' => 100, 'monto_cierre_declarado' => 100]);

    // La pantalla espera 80 por Yape (50 de venta + 30 de abono), como el cierre.
    $props = $this->get(route('finanzas.consolidacion.index'))->viewData('page')['props'];
    $yapeEsperado = collect($props['esperadosPorMetodo'][$turno->id])->firstWhere('metodo_pago_id', $yape->id);
    expect((float) $yapeEsperado['esperado'])->toBe(80.0);

    // El consolidador cuenta exactamente lo cobrado: no hay diferencia que asentar.
    $this->post(route('finanzas.consolidacion.consolidar', $turno), ['items' => [
        ['metodo_pago_id' => null, 'contado' => 100],
        ['metodo_pago_id' => $yape->id, 'contado' => 80],
    ]])->assertSessionHasNoErrors();

    $consolidacion = TurnoConsolidacion::where('turno_id', $turno->id)->with('items')->firstOrFail();
    expect((float) $consolidacion->items->firstWhere('metodo_pago_id', $yape->id)->diferencia)->toBe(0.0);
    expect(CuentaMovimiento::where('ref_tipo', 'turno_consolidacion')->where('ref_id', $consolidacion->id)->exists())->toBeFalse();
});
