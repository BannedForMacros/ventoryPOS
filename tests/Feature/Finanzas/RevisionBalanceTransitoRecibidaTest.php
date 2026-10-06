<?php

use App\Models\Entrada;
use App\Services\AuditoriaLineaService;
use App\Services\BalanceDiarioService;
use App\Services\CambiosCierreService;
use Tests\Support\TestEnv;

/**
 * INV-1 — compra en tránsito ya recibida.
 *
 * El kardex la mete con su fecha de RECEPCIÓN, pero el tránsito solo miraba las
 * que seguían 'en_transito': en los días entre la compra y la recepción el
 * activo desaparecía (ni tránsito ni stock) y la CxP quedaba, así que el
 * patrimonio de esos días caía al recibirla. Ahora esos días la siguen contando
 * como tránsito y el detector post-cierre marca el día de la recepción.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['usa_mercaderia_transito' => true]);
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
    $this->cambios  = app(CambiosCierreService::class);
    $this->producto = $this->env->crearProducto(['precio_costo' => 50, 'stock_inicial' => 0]);
    $this->dCompra = now()->subDays(3)->toDateString();
    $this->dMedio  = now()->subDays(2)->toDateString();
    $this->dRecibe = now()->subDay()->toDateString();
});

it('recibir una compra en tránsito no cambia el balance de los días en que estuvo en camino', function () {
    $e = Entrada::create([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->env->almacen->id, 'user_id' => $this->env->admin->id,
        'proveedor' => 'Proveedor Tránsito', 'numero_documento' => 'F-TR-1', 'tipo' => 'compra', 'fecha' => $this->dCompra,
        'estado' => 'borrador', 'total' => 500, 'monto_pagado' => 0, 'estado_pago' => 'pendiente',
    ]);
    $e->detalles()->create([
        'producto_id' => $this->producto->id, 'unidad_medida_id' => $this->env->unidad->id,
        'cantidad' => 10, 'factor_conversion' => 1, 'cantidad_base' => 10, 'precio_costo' => 50, 'subtotal' => 500,
    ]);
    $e->marcarEnTransito();

    // Días cerrados mientras estaba en camino.
    $bMedio = $this->balances->generar($this->env->admin, $this->dMedio);
    $this->balances->confirmar($bMedio, $this->env->admin);
    $bRecibe = $this->balances->generar($this->env->admin, $this->dRecibe);
    $this->balances->confirmar($bRecibe, $this->env->admin);
    expect((float) $bMedio->fresh()->items->firstWhere('categoria', 'mercaderia_transito')->monto)->toBe(500.0);

    $this->travel(10)->minutes();
    $this->post(route('inventario.entradas.recibir', $e), ['fecha_recepcion' => $this->dRecibe])->assertSessionHasNoErrors();

    // El día intermedio sigue con su tránsito: el patrimonio no se mueve.
    expect(array_sum(array_column($this->balances->desgloseTransito($this->env->empresa->id, $this->dMedio), 'monto')))->toBe(500.0);
    expect($this->balances->desgloseTransito($this->env->empresa->id, $this->dRecibe))->toBe([]);
    $rMedio = $this->cambios->analizar($bMedio->fresh());
    expect($rMedio['diferencia_patrimonio'])->toBe(0.0);   // antes: -500

    // El detector marca la recepción en el día en que llegó, no en el de la compra.
    $tipos = fn (string $f) => $this->cambios->documentosPosteriores($this->env->empresa->id, $f, now()->subMinutes(5))->pluck('tipo')->all();
    expect($tipos($this->dMedio))->not->toContain('Compra en tránsito recibida');
    expect($tipos($this->dRecibe))->toContain('Compra en tránsito recibida');

    // El modal de la línea explica la salida del tránsito el día de la recepción.
    $ev = app(AuditoriaLineaService::class)->eventos($this->env->empresa->id, 'mercaderia_transito', $this->dMedio, $this->dRecibe);
    expect(collect($ev['e' . $e->id] ?? [])->sum('monto'))->toBe(-500.0);
});
