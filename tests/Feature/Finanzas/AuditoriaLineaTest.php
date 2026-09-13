<?php

use App\Models\Deuda;
use App\Models\Stock;
use App\Services\AuditoriaLineaService;
use App\Services\BalanceDiarioService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Bloque 2 — auditoría de punta a punta de cada línea del balance.
 *
 * Garantías: cada línea ES la suma de su desglose (el modal nunca suma distinto
 * que la línea) y la variación de una línea se explica entidad por entidad con
 * los documentos que la movieron.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->balances  = app(BalanceDiarioService::class);
    $this->auditoria = app(AuditoriaLineaService::class);
    $this->ayer = now()->subDay()->toDateString();
    $this->hoy  = now()->toDateString();
});

function alCompra(TestEnv $env, string $fecha, float $total, string $doc, float $pagado = 0): int
{
    return DB::table('entradas')->insertGetId([
        'empresa_id' => $env->empresa->id, 'almacen_id' => $env->almacen->id, 'user_id' => $env->admin->id,
        'numero_documento' => $doc, 'tipo' => 'compra', 'fecha' => $fecha, 'estado' => 'confirmado',
        'total' => $total, 'monto_pagado' => $pagado, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('cada línea del balance es exactamente la suma de su desglose', function () {
    alCompra($this->env, $this->ayer, 1234.56, 'F-001');
    Deuda::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id, 'direccion' => 'por_pagar',
        'tipo' => 'personal', 'nombre' => 'Préstamo X', 'monto_original' => 900, 'saldo' => 900,
        'fecha_inicio' => $this->ayer, 'estado' => 'activa',
    ]);
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    Stock::ajustar($this->env->almacen->id, $p->id, 10, 5, contexto: ['tipo' => 'entrada', 'fecha' => $this->ayer]);

    $lineas = collect($this->balances->calcularLineas($this->env->empresa->id, $this->hoy))->groupBy('categoria');

    foreach (['stock', 'cxc', 'cxp', 'anticipo_cliente', 'planilla_descuento', 'efectivo', 'cuenta_bancaria',
              'deuda', 'personal', 'prestamo_otorgado', 'adelanto_proveedor'] as $cat) {
        $linea = round((float) ($lineas->get($cat)?->sum('monto') ?? 0), 2);
        $suma  = round(array_sum(array_column($this->balances->desglose($this->env->empresa->id, $this->hoy, $cat), 'monto')), 2);
        expect($suma)->toBe($linea, "La categoría {$cat} no suma igual que su línea");
    }
    expect(round((float) $lineas->get('cxp')->sum('monto'), 2))->toBe(1234.56);
});

it('la variación de proveedores se explica compra por compra con sus documentos', function () {
    $vieja = alCompra($this->env, $this->ayer, 1000, 'F-VIEJA', 400);
    DB::table('entrada_pagos')->insert([
        'entrada_id' => $vieja, 'user_id' => $this->env->admin->id, 'fecha' => $this->hoy, 'monto' => 400,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    alCompra($this->env, $this->hoy, 500, 'F-NUEVA');

    $r = $this->auditoria->comparar($this->env->empresa->id, 'cxp', $this->ayer, $this->hoy);

    expect($r['total_desde'])->toBe(1000.0);
    expect($r['total_hasta'])->toBe(1100.0);
    expect($r['variacion'])->toBe(100.0);
    expect(round(array_sum(array_column($r['filas'], 'variacion')), 2))->toBe(100.0);

    $nueva = collect($r['filas'])->first(fn ($f) => str_contains($f['descripcion'], 'F-NUEVA'));
    expect($nueva['variacion'])->toBe(500.0);
    expect($nueva['eventos'][0]['descripcion'])->toContain('registrada');

    $pagada = collect($r['filas'])->first(fn ($f) => str_contains($f['descripcion'], 'F-VIEJA'));
    expect($pagada['antes'])->toBe(1000.0);
    expect($pagada['despues'])->toBe(600.0);
    expect($pagada['eventos'][0]['monto'])->toBe(-400.0);
    expect($pagada['eventos'][0]['user'])->toBe($this->env->admin->name);
});

it('la variación del stock se explica producto por producto con sus movimientos', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0, 'nombre' => 'Fierro auditado']);
    $alm = $this->env->almacen->id;
    Stock::ajustar($alm, $p->id, 10, 5, contexto: ['tipo' => 'entrada', 'fecha' => $this->ayer . ' 08:00:00']);
    Stock::ajustar($alm, $p->id, 5, 8, contexto: ['tipo' => 'entrada', 'fecha' => $this->hoy . ' 09:00:00']);
    Stock::ajustar($alm, $p->id, -3, 0, contexto: ['tipo' => 'venta', 'fecha' => $this->hoy . ' 10:00:00']);

    $r = $this->auditoria->comparar($this->env->empresa->id, 'stock', $this->ayer, $this->hoy);
    $fila = collect($r['filas'])->firstWhere('descripcion', 'Fierro auditado');

    // Ayer: 10 und a S/5 = 50. Hoy: 12 und a la última compra (S/8) = 96.
    expect($fila['antes'])->toBe(50.0);
    expect($fila['despues'])->toBe(96.0);
    expect($fila['variacion'])->toBe(46.0);
    expect($fila['eventos'])->toHaveCount(2);
});

it('el modal de variación del balance suma lo mismo que su tarjeta', function () {
    $b = $this->balances->generar($this->env->admin, $this->ayer);
    $this->balances->confirmar($b, $this->env->admin);
    alCompra($this->env, $this->hoy, 750, 'F-HOY');

    $json = $this->getJson(route('finanzas.balance.detalle', ['fecha' => $this->hoy, 'categoria' => 'cxp']) . '?variacion=1')
        ->assertOk()->json();

    $variacion = collect($json['cards'])->firstWhere('label', 'Variación real')['valor'];
    expect((float) $variacion)->toBe(750.0);
    expect(round(collect($json['grupos'])->sum('monto'), 2))->toBe(750.0);
    expect($json['grupos'][0]['items'][0]['historial'][0]['descripcion'])->toContain('F-HOY');
});
