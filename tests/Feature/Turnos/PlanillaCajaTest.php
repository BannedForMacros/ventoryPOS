<?php

use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use App\Models\PlanillaColumna;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Models\VentaPago;
use App\Services\PlanillaCajaService;
use App\Services\VentaService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * Reporte de caja del turno (función opcional por empresa): una fila por
 * comprobante, el dinero en las columnas que eligió la empresa, y el
 * efectivo en caja igual al esperado del cierre. Se descarga (Excel / PDF);
 * en el detalle del turno solo están los botones.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['usa_planilla_caja' => true]);
    $this->actingAs($this->env->admin);
    $e = $this->env->empresa->id;

    // Columnas de ejemplo de una empresa. Tarjeta queda sin columna: sale con su nombre.
    $this->colEfectivo = PlanillaColumna::create(['empresa_id' => $e, 'nombre' => 'Efectivo', 'orden' => 1]);
    $this->colDepositos = PlanillaColumna::create(['empresa_id' => $e, 'nombre' => 'Depósitos', 'orden' => 2]);
    $this->colYape = PlanillaColumna::create(['empresa_id' => $e, 'nombre' => 'Yape / Cuenta BCP', 'orden' => 3]);
    $this->env->metodo('efectivo')->update(['planilla_columna_id' => $this->colEfectivo->id]);
    $this->env->metodo('transferencia')->update(['planilla_columna_id' => $this->colDepositos->id]);
    $this->env->metodo('yape')->update(['planilla_columna_id' => $this->colYape->id]);
    $this->env->metodo('plin')->update(['planilla_columna_id' => $this->colYape->id]);

    $this->turno = $this->env->abrirTurno(apertura: 100.0);
    $producto = $this->env->crearProducto(['precio_venta' => 1, 'precio_costo' => 0.5, 'stock_inicial' => 500]);

    $vender = function (float $total, array $pagos) use ($producto) {
        return app(VentaService::class)->crear([
            'tipo_comprobante' => 'ticket',
            'items' => [[
                'producto_id'        => $producto->id,
                'producto_unidad_id' => $producto->unidadBase->id,
                'cantidad'           => $total,
                'precio_unitario'    => 1,
            ]],
            'pagos' => array_map(fn ($p) => ['metodo_pago_id' => $this->env->metodo($p[0])->id, 'monto' => $p[1]], $pagos),
        ], $this->env->admin, $this->turno);
    };

    $this->ventaEfectivo = $vender(10, [['efectivo', 10]]);
    $this->ventaMixta    = $vender(20, [['efectivo', 5], ['plin', 15]]);
    $this->ventaAnulada  = $vender(7, [['efectivo', 7]]);
    $this->ventaAnulada->update(['estado' => 'anulada']);

    // Venta al crédito: 30, paga 10 en efectivo, queda 20 al crédito.
    $this->ventaCredito = Venta::create([
        'empresa_id' => $e, 'local_id' => $this->env->local->id, 'turno_id' => $this->turno->id,
        'caja_id' => $this->env->caja->id, 'user_id' => $this->env->admin->id,
        'cliente_id' => $this->env->clienteGeneral->id, 'numero' => 'V-9001', 'tipo_comprobante' => 'ticket',
        'subtotal' => 30, 'descuento_total' => 0, 'igv' => 0, 'total' => 30, 'estado' => 'completada',
        'es_credito' => true, 'monto_pagado' => 10, 'saldo_pendiente' => 20, 'fecha_venta' => now(),
    ]);
    VentaPago::create(['venta_id' => $this->ventaCredito->id, 'metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10, 'vuelto' => 0]);

    // Abono de un crédito cobrado en este turno: 8 en efectivo.
    VentaAbono::create([
        'venta_id' => $this->ventaCredito->id, 'user_id' => $this->env->admin->id, 'turno_id' => $this->turno->id,
        'metodo_pago_id' => $this->env->metodo('efectivo')->id, 'fecha' => now()->toDateString(), 'monto' => 8,
    ]);

    // Gasto en efectivo: 20.
    $tipo = GastoTipo::create(['empresa_id' => $e, 'nombre' => 'Operativo', 'categoria' => 'operativo', 'activo' => true]);
    $concepto = GastoConcepto::create(['empresa_id' => $e, 'gasto_tipo_id' => $tipo->id, 'nombre' => 'Pago semana Javier', 'activo' => true]);
    Gasto::create([
        'empresa_id' => $e, 'local_id' => $this->env->local->id, 'turno_id' => $this->turno->id,
        'user_id' => $this->env->admin->id, 'gasto_tipo_id' => $tipo->id, 'gasto_concepto_id' => $concepto->id,
        'monto' => 20, 'fecha' => now(),
    ]);

    $this->planilla = fn () => app(PlanillaCajaService::class)->deTurno($this->turno->fresh());
    $this->fila = function (array $p, string $vale) {
        foreach ($p['secciones'] as $s) foreach ($s['filas'] as $f) if (str_starts_with($f['vale'], $vale)) return $f;
        return null;
    };
});

it('reparte cada pago en la columna de su medio', function () {
    $p = ($this->planilla)();
    $ef = "c{$this->colEfectivo->id}";
    $yape = "c{$this->colYape->id}";

    $mixta = ($this->fila)($p, $this->ventaMixta->numero);
    expect($mixta['montos'])->toBe([$ef => 5.0, $yape => 15.0]);   // Plin cae en "Yape / Cuenta BCP"

    $credito = ($this->fila)($p, 'V-9001');
    expect($credito['montos'])->toBe([$ef => 10.0, 'credito' => 20.0]);
});

it('muestra las anuladas con monto cero', function () {
    $anulada = ($this->fila)(($this->planilla)(), $this->ventaAnulada->numero);
    expect($anulada['anulada'])->toBeTrue();
    expect($anulada['montos'])->toBe([]);
});

it('el efectivo en caja cuadra con el esperado del cierre', function () {
    $p = ($this->planilla)();
    // 100 apertura + 10 + 5 + 10 (crédito) + 8 (abono) − 20 (gasto) = 113
    expect($p['efectivo_en_caja'])->toBe(113.0);
    expect($p['efectivo_esperado'])->toBe(113.0);
    expect($p['diferencia_sistema'])->toBe(0.0);
});

it('oculta las columnas sin montos y agrupa por sección', function () {
    $p = ($this->planilla)();
    $nombres = array_column($p['columnas'], 'nombre');

    expect($nombres)->toBe(['Efectivo', 'Yape / Cuenta BCP', 'Créditos']); // sin Depósitos, Tarjeta ni Anticipo
    $secciones = collect($p['secciones'])->mapWithKeys(fn ($s) => [$s['clave'] => count($s['filas'])]);
    expect($secciones['notas'])->toBe(4);
    expect($secciones['salidas'])->toBe(1);
    expect($secciones['pagos_anteriores'])->toBe(1);
    expect($p['ventas_count'])->toBe(4);
});

it('el detalle del turno ofrece el reporte solo si la empresa lo activó', function () {
    $this->get(route('turnos.show', $this->turno->id))
        ->assertInertia(fn (Assert $page) => $page->where('reporteCaja', true)->missing('planilla'));

    $this->env->empresa->update(['usa_planilla_caja' => false]);
    $this->get(route('turnos.show', $this->turno->id))
        ->assertInertia(fn (Assert $page) => $page->where('reporteCaja', false));
    $this->get(route('turnos.planilla.excel', $this->turno->id))->assertForbidden();
    $this->get(route('turnos.planilla.imprimir', $this->turno->id))->assertForbidden();
});

it('exporta a Excel y abre la vista de impresión', function () {
    $this->get(route('turnos.planilla.excel', $this->turno->id))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->get(route('turnos.planilla.imprimir', $this->turno->id))
        ->assertOk()
        ->assertSee('Efectivo en caja')
        ->assertSee('ANULADA')
        ->assertSee('class="caja"', false)      // casilla vacía para revisar a mano
        ->assertSee($this->env->empresa->razon_social ?? $this->env->empresa->nombre_comercial); // la empresa del turno
});
