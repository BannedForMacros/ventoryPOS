<?php

use App\Models\Proveedor;
use App\Services\BalanceDiarioService;
use App\Services\EstadoCuentaService;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Revisión del balance — P1 (decisión de negocio): compras EN TRÁNSITO.
 *
 * CxP ya las cuenta (Entrada::comprometido) pero el balance y el estado de
 * cuenta solo miraban 'confirmado': pagar por adelantado una compra que aún no
 * llega sacaba plata de la caja sin activo ni pasivo y el patrimonio caía. Ahora
 * la deuda con el proveedor incluye las compras en tránsito y su valor queda a
 * favor como "Mercadería en tránsito": comprar o pagar por adelantado no mueve
 * el patrimonio.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances = app(BalanceDiarioService::class);
    $this->hoy = now()->toDateString();
    $this->proveedor = Proveedor::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'RUC', 'numero_documento' => '20999888777',
        'razon_social' => 'Comercial RB', 'activo' => true,
    ]);
});

function rbtCompraTransito($test, float $total, float $pagado = 0): int
{
    return DB::table('entradas')->insertGetId([
        'empresa_id' => $test->env->empresa->id, 'almacen_id' => $test->env->almacen->id, 'user_id' => $test->env->admin->id,
        'proveedor_id' => $test->proveedor->id, 'numero_documento' => 'F-TRANS-' . uniqid(), 'tipo' => 'compra',
        'fecha' => $test->hoy, 'estado' => 'en_transito', 'total' => $total, 'monto_pagado' => $pagado,
        'estado_pago' => $pagado >= $total ? 'pagado' : 'pendiente', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('comprar en tránsito y pagarla por adelantado no mueve el patrimonio', function () {
    $env = $this->env;
    $caja = TesoreriaService::efectivo($env->empresa->id);
    app(TesoreriaService::class)->registrar($env->empresa->id, $caja->id, $env->admin, now()->subDays(3)->toDateString(),
        'ingreso', 2000, 'Fondo inicial', 'ajuste', null);

    // Día cerrado: su foto no se toca aunque cambie el cálculo.
    $cerrado = $this->balances->generar($env->admin, now()->subDay()->toDateString());
    $this->balances->confirmar($cerrado, $env->admin);
    $netoCerrado = (float) $cerrado->fresh()->balance_neto;

    $antes = (float) $this->balances->generar($env->admin, $this->hoy)->balance_neto;

    // Compra en tránsito de 500 pagada HOY por adelantado (sale de la caja).
    $pagada = rbtCompraTransito($this, 500, 500);
    $pagoId = DB::table('entrada_pagos')->insertGetId([
        'entrada_id' => $pagada, 'user_id' => $env->admin->id, 'fecha' => $this->hoy, 'monto' => 500,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(TesoreriaService::class)->registrar($env->empresa->id, $caja->id, $env->admin, $this->hoy,
        'egreso', 500, 'Pago adelantado de compra en tránsito', 'entrada_pago', $pagoId);

    // Otra compra en tránsito de 200, al crédito.
    rbtCompraTransito($this, 200);

    $b = $this->balances->generar($env->admin, $this->hoy);
    expect((float) $b->items->firstWhere('categoria', 'mercaderia_transito')->monto)->toBe(700.0);
    expect((float) $b->items->firstWhere('categoria', 'cxp')->monto)->toBe(200.0);
    expect((float) $b->balance_neto)->toBe($antes); // antes caía 500

    // Su modal suma lo mismo que la línea.
    $modal = $this->getJson(route('finanzas.balance.detalle', ['fecha' => $this->hoy, 'categoria' => 'mercaderia_transito']))->json();
    expect((float) $modal['cards'][0]['valor'])->toBe(700.0);

    // El estado de cuenta del proveedor también le debe la compra en tránsito.
    $fila = app(EstadoCuentaService::class)->resumen($env->empresa->id)->firstWhere('proveedor_id', $this->proveedor->id);
    expect((float) $fila['le_debemos'])->toBe(200.0);

    expect((float) $this->balances->generar($env->admin, now()->subDay()->toDateString())->balance_neto)->toBe($netoCerrado);
});
