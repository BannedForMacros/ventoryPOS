<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de punta a punta de UNA línea del balance entre dos fechas.
 *
 * Compara el desglose de la línea (BalanceDiarioService::desglose: productos,
 * ventas, compras, anticipos, cuentas, deudas…) al cierre de $desde y de $hasta,
 * y adjunta a cada entidad los documentos que la movieron en ese período. Como
 * la línea ES la suma de su desglose, las filas siempre suman exactamente la
 * variación de la línea. Solo lee.
 */
class AuditoriaLineaService
{
    /** Líneas EN CONTRA: que suban es malo (más deuda). */
    public const EN_CONTRA = ['cxp', 'anticipo_cliente', 'deuda', 'personal'];

    public function __construct(private BalanceDiarioService $balances) {}

    public function comparar(int $empresaId, string $categoria, string $desde, string $hasta): array
    {
        $antes   = $this->balances->desglose($empresaId, $desde, $categoria);
        $despues = $this->balances->desglose($empresaId, $hasta, $categoria);
        $eventos = $this->eventos($empresaId, $categoria, $desde, $hasta);

        $filas = [];
        foreach (array_unique(array_merge(array_keys($antes), array_keys($despues), array_keys($eventos))) as $clave) {
            $a = (float) ($antes[$clave]['monto'] ?? 0);
            $d = (float) ($despues[$clave]['monto'] ?? 0);
            $variacion = round($d - $a, 2);
            $movs = $eventos[$clave] ?? [];
            if (abs($variacion) < 0.005 && empty($movs)) continue;

            $filas[] = [
                'clave'       => $clave,
                'descripcion' => $despues[$clave]['descripcion'] ?? $antes[$clave]['descripcion'] ?? ($movs[0]['entidad'] ?? $clave),
                'detalle'     => $despues[$clave]['detalle'] ?? $antes[$clave]['detalle'] ?? null,
                'antes'       => round($a, 2),
                'despues'     => round($d, 2),
                'variacion'   => $variacion,
                'eventos'     => array_map(fn ($e) => array_diff_key($e, ['entidad' => 1]), $movs),
            ];
        }

        usort($filas, fn ($x, $y) => abs($y['variacion']) <=> abs($x['variacion']));

        $totalAntes   = round(array_sum(array_column($antes, 'monto')), 2);
        $totalDespues = round(array_sum(array_column($despues, 'monto')), 2);

        return [
            'categoria'     => $categoria,
            'en_contra'     => in_array($categoria, self::EN_CONTRA, true),
            'desde'         => $desde,
            'hasta'         => $hasta,
            'total_desde'   => $totalAntes,
            'total_hasta'   => $totalDespues,
            'variacion'     => round($totalDespues - $totalAntes, 2),
            'filas'         => $filas,
        ];
    }

    /**
     * Documentos que movieron cada entidad con fecha en (desde, hasta].
     *
     * @return array<string, list<array{fecha: string, descripcion: string, monto: ?float, user: ?string, entidad?: string}>>
     */
    public function eventos(int $empresaId, string $categoria, string $desde, string $hasta): array
    {
        $ini = $desde . ' 23:59:59';
        $fin = $hasta . ' 23:59:59';
        $ev  = [];
        $push = function (string $clave, $fecha, string $desc, ?float $monto, $userId = null, ?string $entidad = null) use (&$ev) {
            $ev[$clave][] = [
                'fecha'       => substr((string) $fecha, 0, 16),
                'descripcion' => $desc,
                'monto'       => $monto !== null ? round($monto, 2) : null,
                'user_id'     => $userId,
                'entidad'     => $entidad,
            ];
        };

        switch ($categoria) {
            case 'stock':
                $numVenta = [];
                foreach (DB::table('movimientos_inventario as m')->join('productos as p', 'p.id', '=', 'm.producto_id')
                    ->where('m.empresa_id', $empresaId)->where('m.fecha', '>', $ini)->where('m.fecha', '<=', $fin)
                    ->orderBy('m.fecha')->orderBy('m.id')
                    ->get(['m.producto_id', 'p.nombre', 'm.fecha', 'm.tipo', 'm.referencia_tipo', 'm.referencia_id', 'm.documento',
                           'm.cantidad', 'm.costo_unitario', 'm.user_id']) as $m) {
                    $doc = $m->documento;
                    if (!$doc && $m->referencia_tipo === 'venta' && $m->referencia_id) {
                        $doc = $numVenta[$m->referencia_id] ??= DB::table('ventas')->where('id', $m->referencia_id)->value('numero');
                    }
                    $cant = (float) $m->cantidad;
                    $push('p' . $m->producto_id, $m->fecha,
                        MovimientoInventario::etiquetaTipo($m->tipo) . ($doc ? " {$doc}" : ($m->referencia_id ? " #{$m->referencia_id}" : ''))
                        . ' · ' . ($cant > 0 ? '+' : '') . rtrim(rtrim(number_format($cant, 4, '.', ','), '0'), '.') . ' und',
                        $cant * (float) $m->costo_unitario, $m->user_id, $m->nombre);
                }
                break;

            case 'cxc':
                foreach (DB::table('ventas')->where('empresa_id', $empresaId)->where('es_credito', true)->where('estado', 'completada')
                    ->where('fecha_venta', '>', $ini)->where('fecha_venta', '<=', $fin)
                    ->get(['id', 'numero', 'fecha_venta', 'total', 'user_id']) as $v) {
                    $push('v' . $v->id, $v->fecha_venta, "Venta a crédito {$v->numero} (total S/ " . number_format((float) $v->total, 2) . ')', null, $v->user_id);
                }
                foreach (DB::table('venta_abonos as a')->join('ventas as v', 'v.id', '=', 'a.venta_id')
                    ->where('v.empresa_id', $empresaId)->where('a.fecha', '>', $desde)->where('a.fecha', '<=', $hasta)
                    ->get(['a.venta_id', 'v.numero', 'a.fecha', 'a.monto', 'a.user_id']) as $a) {
                    $push('v' . $a->venta_id, $a->fecha, "Cobro de {$a->numero}", -(float) $a->monto, $a->user_id);
                }
                break;

            case 'cxp':
                foreach (DB::table('entradas')->where('empresa_id', $empresaId)->where('estado', 'confirmado')
                    ->where('fecha', '>', $desde)->where('fecha', '<=', $hasta)
                    ->get(['id', 'numero_documento', 'correlativo', 'fecha', 'total', 'user_id']) as $e) {
                    $push('e' . $e->id, $e->fecha, 'Compra ' . ($e->numero_documento ?: ($e->correlativo ?: "#{$e->id}")) . ' registrada', (float) $e->total, $e->user_id);
                }
                foreach (DB::table('entrada_pagos as p')->join('entradas as e', 'e.id', '=', 'p.entrada_id')
                    ->where('e.empresa_id', $empresaId)->where('p.fecha', '>', $desde)->where('p.fecha', '<=', $hasta)
                    ->get(['p.entrada_id', 'e.numero_documento', 'p.fecha', 'p.monto', 'p.user_id']) as $p) {
                    $push('e' . $p->entrada_id, $p->fecha, 'Pago al proveedor' . ($p->numero_documento ? " ({$p->numero_documento})" : ''), -(float) $p->monto, $p->user_id);
                }
                break;

            case 'anticipo_cliente':
                foreach (DB::table('cliente_anticipos')->where('empresa_id', $empresaId)
                    ->where('fecha', '>', $desde)->where('fecha', '<=', $hasta)
                    ->get(['id', 'fecha', 'monto', 'user_id']) as $a) {
                    $push('a' . $a->id, $a->fecha, 'Anticipo recibido', (float) $a->monto, $a->user_id);
                }
                foreach (DB::table('cliente_anticipo_aplicaciones as ca')->join('cliente_anticipos as c', 'c.id', '=', 'ca.cliente_anticipo_id')
                    ->where('c.empresa_id', $empresaId)->where('ca.fecha', '>', $desde)->where('ca.fecha', '<=', $hasta)
                    ->get(['ca.cliente_anticipo_id', 'ca.numero', 'ca.fecha', 'ca.monto', 'ca.user_id']) as $a) {
                    $push('a' . $a->cliente_anticipo_id, $a->fecha, 'Entrega / aplicación' . ($a->numero ? " {$a->numero}" : ''), -(float) $a->monto, $a->user_id);
                }
                foreach (DB::table('cuenta_movimientos')->where('empresa_id', $empresaId)->where('ref_tipo', 'cliente_anticipo_devolucion')
                    ->where('fecha', '>', $desde)->where('fecha', '<=', $hasta)
                    ->get(['ref_id', 'fecha', 'monto', 'user_id']) as $m) {
                    $push('a' . $m->ref_id, $m->fecha, 'Anticipo devuelto al cliente', -(float) $m->monto, $m->user_id);
                }
                break;

            case 'efectivo':
            case 'cuenta_bancaria':
                foreach (DB::table('cuenta_movimientos as m')->join('cuentas as c', 'c.id', '=', 'm.cuenta_id')
                    ->where('m.empresa_id', $empresaId)->where('c.es_efectivo', $categoria === 'efectivo')
                    ->where('m.fecha', '>', $desde)->where('m.fecha', '<=', $hasta)
                    ->orderBy('m.fecha')->orderBy('m.id')
                    ->get(['m.cuenta_id', 'm.fecha', 'm.tipo', 'm.monto', 'm.descripcion', 'm.user_id', 'm.created_at']) as $m) {
                    $push('c' . $m->cuenta_id, $m->created_at ?: $m->fecha, (string) $m->descripcion,
                        ($m->tipo === 'ingreso' ? 1 : -1) * (float) $m->monto, $m->user_id);
                }
                break;

            case 'deuda':
            case 'personal':
            case 'prestamo_otorgado':
                foreach (DB::table('deudas')->where('empresa_id', $empresaId)
                    ->where('fecha_inicio', '>', $desde)->where('fecha_inicio', '<=', $hasta)
                    ->get(['id', 'nombre', 'fecha_inicio', 'monto_original', 'user_id']) as $d) {
                    $push((string) $d->id, $d->fecha_inicio, 'Registro de la deuda/préstamo', (float) $d->monto_original, $d->user_id, $d->nombre);
                }
                foreach (DB::table('deuda_pagos as p')->join('deudas as d', 'd.id', '=', 'p.deuda_id')
                    ->where('d.empresa_id', $empresaId)->whereNull('p.deleted_at')
                    ->where('p.fecha', '>', $desde)->where('p.fecha', '<=', $hasta)
                    ->get(['p.deuda_id', 'd.nombre', 'p.tipo', 'p.fecha', 'p.monto', 'p.observacion', 'p.user_id']) as $p) {
                    $signo = $p->tipo === 'incremento' ? 1 : -1;
                    $push((string) $p->deuda_id, $p->fecha,
                        ($p->tipo === 'incremento' ? 'Incremento' : ($p->tipo === 'compensacion' ? 'Compensación' : 'Amortización'))
                        . ($p->observacion ? " · {$p->observacion}" : ''),
                        $signo * (float) $p->monto, $p->user_id, $p->nombre);
                }
                break;

            case 'adelanto_proveedor':
                foreach (DB::table('proveedor_adelantos')->where('empresa_id', $empresaId)
                    ->where('fecha', '>', $desde)->where('fecha', '<=', $hasta)
                    ->get(['id', 'fecha', 'monto', 'user_id']) as $a) {
                    $push((string) $a->id, $a->fecha, 'Adelanto entregado', (float) $a->monto, $a->user_id);
                }
                foreach (DB::table('proveedor_adelanto_aplicaciones as pa')->join('proveedor_adelantos as p', 'p.id', '=', 'pa.proveedor_adelanto_id')
                    ->where('p.empresa_id', $empresaId)->where('pa.fecha', '>', $desde)->where('pa.fecha', '<=', $hasta)
                    ->get(['pa.proveedor_adelanto_id', 'pa.fecha', 'pa.monto', 'pa.user_id']) as $a) {
                    $push((string) $a->proveedor_adelanto_id, $a->fecha, 'Aplicado a una compra', -(float) $a->monto, $a->user_id);
                }
                foreach (DB::table('cuenta_movimientos')->where('empresa_id', $empresaId)->where('ref_tipo', 'proveedor_adelanto_devolucion')
                    ->where('fecha', '>', $desde)->where('fecha', '<=', $hasta)
                    ->get(['ref_id', 'fecha', 'monto', 'user_id']) as $m) {
                    $push((string) $m->ref_id, $m->fecha, 'Devuelto por el proveedor', -(float) $m->monto, $m->user_id);
                }
                break;

            case 'planilla_descuento':
                foreach (DB::table('planilla_descuentos')->where('empresa_id', $empresaId)
                    ->where('fecha', '>', $desde)->where('fecha', '<=', $hasta)
                    ->get(['id', 'fecha', 'motivo', 'monto', 'registrado_por']) as $d) {
                    $push('pd' . $d->id, $d->fecha, "Descuento registrado: {$d->motivo}", (float) $d->monto, $d->registrado_por);
                }
                foreach (DB::table('planilla_descuentos')->where('empresa_id', $empresaId)->where('estado', 'aplicado')
                    ->where('fecha_aplicacion', '>', $desde)->where('fecha_aplicacion', '<=', $hasta)
                    ->get(['id', 'fecha_aplicacion', 'motivo', 'monto', 'aplicado_por']) as $d) {
                    $push('pd' . $d->id, $d->fecha_aplicacion, 'Aplicado en planilla', -(float) $d->monto, $d->aplicado_por);
                }
                break;
        }

        $ids = collect($ev)->flatten(1)->pluck('user_id')->filter()->unique()->all();
        $nombres = $ids ? DB::table('users')->whereIn('id', $ids)->pluck('name', 'id') : collect();

        foreach ($ev as $clave => $lista) {
            usort($lista, fn ($x, $y) => strcmp($x['fecha'], $y['fecha']));
            $ev[$clave] = array_map(function ($e) use ($nombres) {
                $e['user'] = $e['user_id'] ? ($nombres[$e['user_id']] ?? null) : null;
                unset($e['user_id']);

                return $e;
            }, $lista);
        }

        return $ev;
    }
}
