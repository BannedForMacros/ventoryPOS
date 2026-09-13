<?php

namespace App\Services;

use App\Models\BalanceDiario;

/**
 * Detector de DÍAS SUCIOS del balance diario. SOLO LEE: nunca regenera ni escribe.
 *
 * Un balance confirmado es la foto de ese día. Si después se registra, edita o
 * anula algo con fecha anterior al cierre (una compra cargada tarde, una venta
 * anulada días después, un pedido modificado), las líneas de ese día ya no
 * coinciden con lo que hoy dicen los documentos. Este servicio recalcula las
 * líneas con los datos actuales — con BalanceDiarioService::calcularLineas, la
 * misma función que usa la regeneración — y las compara con lo grabado.
 *
 * Los días cerrados NO se reabren ni se regeneran en cascada: la diferencia se
 * informa como "ajuste por registros posteriores al cierre".
 */
class BalanceVerificacionService
{
    /** Diferencias menores a esto (soles) son redondeo, no un día sucio. */
    public const TOLERANCIA = 1.0;

    /**
     * Categorías que genera HOY el balance. Líneas de estructuras anteriores
     * (p. ej. "gastos emitidos", eliminada en F11) no se comparan: no son un
     * cambio de datos sino de modelo.
     */
    public const CATEGORIAS = [
        'efectivo', 'cuenta_bancaria', 'stock', 'cxc', 'prestamo_otorgado',
        'adelanto_proveedor', 'planilla_descuento', 'cxp', 'anticipo_cliente', 'deuda', 'personal',
    ];

    public function __construct(private BalanceDiarioService $balances) {}

    public function verificar(BalanceDiario $balance): array
    {
        $fecha = $balance->fecha->toDateString();

        // Las líneas manuales no se recalculan: se comparan solo las automáticas.
        $guardadas = $balance->items()->where('es_manual', false)->whereIn('categoria', self::CATEGORIAS)->get()
            ->map(fn ($i) => $this->normalizar($i->seccion, $i->categoria, $i->descripcion, $i->ref_id, (float) $i->monto));
        $actuales = collect($this->balances->calcularLineas($balance->empresa_id, $fecha))
            ->map(fn ($i) => $this->normalizar($i['seccion'], $i['categoria'], $i['descripcion'], $i['ref_id'] ?? null, (float) $i['monto']));

        $porClave = [];
        foreach ([['guardado', $guardadas], ['actual', $actuales]] as [$lado, $lineas]) {
            foreach ($lineas as $l) {
                $porClave[$l['clave']] ??= $l + ['guardado' => 0.0, 'actual' => 0.0];
                $porClave[$l['clave']][$lado] += $l['monto'];
            }
        }

        $lineas = collect($porClave)
            ->map(fn ($l) => [
                'seccion'     => $l['seccion'],
                'categoria'   => $l['categoria'],
                'descripcion' => $l['descripcion'],
                'ref_id'      => $l['ref_id'],
                'guardado'    => round($l['guardado'], 2),
                'actual'      => round($l['actual'], 2),
                'diferencia'  => round($l['actual'] - $l['guardado'], 2),
            ])
            ->filter(fn ($l) => abs($l['diferencia']) >= 0.01)
            ->sortByDesc(fn ($l) => abs($l['diferencia']))
            ->values();

        // Efecto en el patrimonio: lo a favor suma, lo en contra resta.
        $efecto = fn ($l) => $l['seccion'] === 'contra' ? -$l['diferencia'] : $l['diferencia'];

        $categorias = $lineas->groupBy('categoria')
            ->map(fn ($g, $cat) => [
                'categoria'  => $cat,
                'seccion'    => $g->first()['seccion'],
                'guardado'   => round($g->sum('guardado'), 2),
                'actual'     => round($g->sum('actual'), 2),
                'diferencia' => round($g->sum('diferencia'), 2),
                'efecto'     => round($g->sum($efecto), 2),
            ])
            ->sortByDesc(fn ($c) => abs($c['efecto']))
            ->values();

        $diferenciaPatrimonio = round($lineas->sum($efecto), 2);

        $metricasGuardadas = [
            'ventas_dia' => (float) $balance->ventas_dia,
            'costo_dia'  => (float) $balance->costo_dia,
            'gastos_dia' => (float) $balance->gastos_dia,
        ];
        $metricasActuales = $this->balances->calcularMetricas($balance->empresa_id, $fecha);
        $metricas = collect($metricasGuardadas)
            ->map(fn ($v, $k) => ['guardado' => round($v, 2), 'actual' => (float) $metricasActuales[$k],
                                  'diferencia' => round((float) $metricasActuales[$k] - $v, 2)])
            ->filter(fn ($m) => abs($m['diferencia']) >= self::TOLERANCIA)
            ->all();

        return [
            'balance_id'            => $balance->id,
            'fecha'                 => $fecha,
            'estado'                => $balance->estado,
            'patrimonio_guardado'   => (float) $balance->balance_neto,
            'patrimonio_actual'     => round((float) $balance->balance_neto + $diferenciaPatrimonio, 2),
            'diferencia_patrimonio' => $diferenciaPatrimonio,
            'sucio'                 => abs($diferenciaPatrimonio) >= self::TOLERANCIA
                || $categorias->contains(fn ($c) => abs($c['diferencia']) >= self::TOLERANCIA)
                || !empty($metricas),
            'categorias'            => $categorias->all(),
            'lineas'                => $lineas->all(),
            'metricas'              => $metricas,
        ];
    }

    /**
     * ¿Tiene sentido verificar este día? Solo si se guardó con la ESTRUCTURA
     * actual del balance y desde el inventario inicial de la empresa. Los días
     * armados con el modelo anterior (una línea por cuenta bancaria, "gastos
     * emitidos", sin kardex) no son comparables: sus diferencias serían de
     * modelo, no de datos.
     */
    public function verificable(BalanceDiario $balance): bool
    {
        static $inicio = [];
        $inicio[$balance->empresa_id] ??= \Illuminate\Support\Facades\DB::table('stock_iniciales')
            ->where('empresa_id', $balance->empresa_id)->min('fecha') ?? '';

        if ($inicio[$balance->empresa_id] !== '' && $balance->fecha->toDateString() < substr((string) $inicio[$balance->empresa_id], 0, 10)) {
            return false;
        }

        return !$balance->items()->where('es_manual', false)
            ->where(fn ($q) => $q->whereNotIn('categoria', self::CATEGORIAS)
                ->orWhere(fn ($q2) => $q2->whereIn('categoria', ['efectivo', 'cuenta_bancaria'])
                    ->where(fn ($q3) => $q3->whereNull('ref_tipo')->orWhere('ref_tipo', '!=', 'entidad'))))
            ->exists();
    }

    /** Categorías de una sola línea: se comparan por categoría, no por su texto. */
    private const LINEA_UNICA = ['stock', 'cxc', 'cxp', 'anticipo_cliente', 'planilla_descuento'];

    private function normalizar(string $seccion, string $categoria, string $descripcion, $refId, float $monto): array
    {
        $clave = $categoria . '|' . match (true) {
            (bool) $refId                                => 'r' . $refId,
            in_array($categoria, self::LINEA_UNICA, true) => 'unica',
            default                                      => 'd' . mb_strtolower(trim($descripcion)),
        };

        return compact('seccion', 'categoria', 'descripcion', 'monto', 'clave') + ['ref_id' => $refId];
    }
}
