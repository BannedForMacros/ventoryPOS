<?php

use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Las cards de Ventas siguen el filtro de fechas: si la administradora filtra el
 * 09, ve lo vendido el 09 aunque el turno siga abierto desde el 08 (caso
 * Farmacix). Sin filtro, siguen mostrando la caja del turno, con aviso si ese
 * turno viene de otro día.
 */

beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $this->turno = $this->env->abrirTurno($this->env->admin);
    // El turno se abrió AYER y no se cerró.
    DB::table('turnos')->where('id', $this->turno->id)->update(['fecha_apertura' => now()->subDay()->setTime(10, 15)]);
});

function venderEl($test, string $fecha, float $monto, string $metodo = 'efectivo')
{
    $v = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $test->producto->id, 'producto_unidad_id' => $test->producto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => $monto]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo($metodo)->id, 'monto' => $monto]],
    ], $test->env->admin, $test->turno);
    DB::table('ventas')->where('id', $v->id)->update(['fecha_venta' => $fecha . ' 12:00:00']);

    return $v;
}

it('filtrando un día, las cards muestran lo vendido ese día aunque el turno venga de ayer', function () {
    $ayer = now()->subDay()->toDateString();
    $hoy  = now()->toDateString();
    venderEl($this, $ayer, 50);
    venderEl($this, $hoy, 20);
    venderEl($this, $hoy, 15, 'yape');

    $this->get(route('ventas.index', ['fecha_desde' => $hoy, 'fecha_hasta' => $hoy]))->assertInertia(fn ($p) => $p
        ->where('resumen.modo', 'rango')
        ->where('resumen.total_vendido', 35)
        ->where('resumen.num_ventas', 2)
        ->where('resumen.metodos', fn ($m) => collect($m)->firstWhere('es_efectivo', true)['total'] == 20)
        ->where('resumen.turnos_abiertos_antes.0.id', $this->turno->id));

    $this->get(route('ventas.index', ['fecha_desde' => $ayer, 'fecha_hasta' => $ayer]))->assertInertia(fn ($p) => $p
        ->where('resumen.total_vendido', 50));
});

it('sin filtro, las cards son la caja del turno y avisan que viene de otro día', function () {
    venderEl($this, now()->subDay()->toDateString(), 50);
    venderEl($this, now()->toDateString(), 20);

    $this->get(route('ventas.index'))->assertInertia(fn ($p) => $p
        ->missing('resumen.modo')
        ->where('resumen.total_vendido', 70)
        ->where('resumen.turnos_abiertos_antes.0.id', $this->turno->id));
});

it('un turno abierto hoy no genera aviso', function () {
    DB::table('turnos')->where('id', $this->turno->id)->update(['fecha_apertura' => now()->startOfDay()->addHours(8)]);
    venderEl($this, now()->toDateString(), 20);

    $this->get(route('ventas.index'))->assertInertia(fn ($p) => $p->where('resumen.turnos_abiertos_antes', []));
});
