<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\EstadoCuentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Estado de cuenta por TERCERO: una sola fila por cliente/proveedor con todo
 * lo que debe y todo lo que se le debe, en vez de una lista plana por
 * documento repartida en cuatro módulos.
 *
 * De solo consulta: no mueve tesorería ni toca saldos. Para cobrar o pagar se
 * sigue usando Cuentas por cobrar / Cuentas por pagar.
 */
class EstadoCuentaController extends Controller
{
    public function __construct(private EstadoCuentaService $estadoCuenta) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $filas = $this->estadoCuenta->resumen($user->empresa_id);

        // Los totales se calculan SIEMPRE sobre el universo completo, no sobre
        // el filtro: el usuario necesita el gran total aunque esté filtrando.
        $totales = [
            'nos_debe'         => round($filas->sum('nos_debe'), 2),
            'le_debemos'       => round($filas->sum('le_debemos'), 2),
            'su_anticipo'      => round($filas->sum('su_anticipo'), 2),
            'nuestro_adelanto' => round($filas->sum('nuestro_adelanto'), 2),
        ];
        $totales['neto'] = round(
            ($totales['nos_debe'] + $totales['nuestro_adelanto'])
            - ($totales['le_debemos'] + $totales['su_anticipo']),
            2,
        );

        return Inertia::render('Finanzas/EstadoCuenta', [
            'terceros' => $filas->values(),
            'totales'  => $totales,
            'kpis'     => [
                'terceros'        => $filas->count(),
                'nos_deben'       => $filas->where('nos_debe', '>', 0)->count(),
                'les_debemos'     => $filas->where('le_debemos', '>', 0)->count(),
                'ambos_roles'     => $filas->where('rol', 'ambos')->count(),
                'sin_identificar' => $filas->where('sin_identificar', true)->count(),
            ],
        ]);
    }

    /**
     * Cuenta corriente de un tercero: todos sus movimientos en orden de fecha
     * con saldo corriendo. La clave llega de la lista (doc:… / cli:… / prov:…).
     */
    public function show(Request $request, string $clave)
    {
        $user  = $request->user();
        $filas = $this->estadoCuenta->resumen($user->empresa_id);
        $t     = $filas->firstWhere('clave', $clave);

        abort_if(!$t, 404, 'Tercero sin movimientos.');

        return Inertia::render('Finanzas/EstadoCuentaDetalle', [
            'tercero'     => $t,
            'movimientos' => $this->movimientos($user->empresa_id, $t),
        ]);
    }

    /**
     * Movimientos del tercero, unificando las cuatro fuentes.
     *
     * SIGNO: positivo = a nuestro favor (nos deben más), negativo = en nuestra
     * contra. Así el acumulado final coincide con el neto de la lista.
     */
    private function movimientos(int $empresaId, array $t): array
    {
        $movs = [];

        // ── Lado cliente ─────────────────────────────────────────────────────
        if ($t['cliente_id']) {
            $ventas = DB::table('ventas')
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $t['cliente_id'])
                ->where('es_credito', true)
                ->where('estado', 'completada')
                ->where('saldo_pendiente', '>', 0)
                ->get(['id', 'numero', 'fecha_venta', 'total', 'monto_pagado', 'saldo_pendiente']);

            foreach ($ventas as $v) {
                $movs[] = [
                    'fecha'       => substr($v->fecha_venta, 0, 10),
                    'tipo'        => 'venta_credito',
                    'etiqueta'    => 'Venta a crédito',
                    'documento'   => $v->numero,
                    'detalle'     => 'Venta al crédito por ' . number_format((float) $v->total, 2),
                    'monto'       => round((float) $v->total, 2),
                    'variant'     => 'primary',
                    // Referencia para abrir el detalle del documento al hacer clic.
                    'ref_tipo'    => 'venta',
                    'ref_id'      => (int) $v->id,
                ];

                // Pago inicial en el POS (parte del total que ya entró en caja).
                foreach (DB::table('venta_pagos')->where('venta_id', $v->id)->get(['monto', 'referencia']) as $p) {
                    $movs[] = [
                        'fecha'     => substr($v->fecha_venta, 0, 10),
                        'tipo'      => 'pago_pos',
                        'etiqueta'  => 'Pago inicial',
                        'documento' => $v->numero,
                        'detalle'   => 'Pagado en caja al momento de la venta' . ($p->referencia ? " · Ref. {$p->referencia}" : ''),
                        'monto'     => -round((float) $p->monto, 2),
                        'variant'   => 'success',
                        'ref_tipo'  => 'venta',
                        'ref_id'    => (int) $v->id,
                    ];
                }
            }

            $ventaIds = $ventas->pluck('id');
            if ($ventaIds->isNotEmpty()) {
                foreach (DB::table('venta_abonos')->whereIn('venta_id', $ventaIds)->get() as $a) {
                    $numero = $ventas->firstWhere('id', $a->venta_id)?->numero;
                    $movs[] = [
                        'fecha'     => substr($a->fecha, 0, 10),
                        'tipo'      => 'abono',
                        'etiqueta'  => 'Abono del cliente',
                        'documento' => $numero,
                        'detalle'   => 'Cobro a cuenta' . ($a->referencia ? " · Ref. {$a->referencia}" : ''),
                        'monto'     => -round((float) $a->monto, 2),
                        'variant'   => 'success',
                        'ref_tipo'  => 'venta',
                        'ref_id'    => (int) $a->venta_id,
                    ];
                }
            }

            // Anticipos: el cliente pagó algo que aún no se lleva → se le debe.
            $anticipos = DB::table('cliente_anticipos')
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $t['cliente_id'])
                ->where('estado', 'activo')
                ->where('saldo', '>', 0)
                ->get(['id', 'fecha', 'monto', 'saldo', 'tipo_valorizacion', 'venta_id']);

            foreach ($anticipos as $a) {
                $esMaterial = $a->tipo_valorizacion === 'material';
                $movs[] = [
                    'fecha'     => substr($a->fecha, 0, 10),
                    'tipo'      => 'anticipo',
                    'etiqueta'  => $esMaterial ? 'Anticipo (mercadería)' : 'Anticipo (dinero)',
                    'documento' => 'ANT-' . $a->id,
                    'detalle'   => $esMaterial
                        ? 'Pagado y pendiente de entregar, al precio congelado de la venta'
                        : 'Dinero a favor del cliente',
                    'monto'     => -round((float) $a->saldo, 2),
                    'variant'   => 'warning',
                    // Si nació de una venta POS/migrada, el clic abre esa venta.
                    'ref_tipo'  => $a->venta_id ? 'venta' : null,
                    'ref_id'    => $a->venta_id ? (int) $a->venta_id : null,
                ];
            }
        }

        // ── Lado proveedor ───────────────────────────────────────────────────
        $entradas = collect();
        if ($t['proveedor_id'] || $t['sin_identificar']) {
            $q = DB::table('entradas')
                ->where('empresa_id', $empresaId)
                ->where('estado', 'confirmado')
                ->whereRaw('COALESCE(total, 0) - COALESCE(monto_pagado, 0) > 0');

            if ($t['proveedor_id']) {
                $q->where('proveedor_id', $t['proveedor_id']);
            } else {
                // Compras con proveedor en texto libre: se agrupan por nombre.
                $q->whereNull('proveedor_id')->whereRaw('LOWER(TRIM(proveedor)) = ?', [
                    trim(mb_strtolower($t['nombre'])),
                ]);
            }

            $entradas = $q->get(['id', 'correlativo', 'numero_documento', 'fecha', 'total', 'monto_pagado']);

            foreach ($entradas as $e) {
                $movs[] = [
                    'fecha'     => substr($e->fecha, 0, 10),
                    'tipo'      => 'compra',
                    'etiqueta'  => 'Compra',
                    'documento' => $e->correlativo ?: 'E-' . $e->id,
                    'detalle'   => 'Compra al crédito por ' . number_format((float) $e->total, 2)
                        . ($e->numero_documento ? " · Doc. {$e->numero_documento}" : ''),
                    'monto'     => -round((float) $e->total, 2),
                    'variant'   => 'danger',
                    'ref_tipo'  => 'entrada',
                    'ref_id'    => (int) $e->id,
                ];
            }

            if ($entradas->isNotEmpty()) {
                foreach (DB::table('entrada_pagos')->whereIn('entrada_id', $entradas->pluck('id'))->get() as $p) {
                    $entrada = $entradas->firstWhere('id', $p->entrada_id);
                    $movs[] = [
                        'fecha'     => substr($p->fecha, 0, 10),
                        'tipo'      => 'pago_compra',
                        'etiqueta'  => 'Pago al proveedor',
                        'documento' => $entrada?->correlativo ?: 'E-' . $p->entrada_id,
                        'detalle'   => 'Pago a cuenta' . ($p->referencia ? " · Ref. {$p->referencia}" : ''),
                        'monto'     => round((float) $p->monto, 2),
                        'variant'   => 'success',
                        'ref_tipo'  => 'entrada',
                        'ref_id'    => (int) $p->entrada_id,
                    ];
                }
            }
        }

        if ($t['proveedor_id']) {
            foreach (DB::table('proveedor_adelantos')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $t['proveedor_id'])
                ->where('estado', 'activo')
                ->where('saldo', '>', 0)
                ->get(['id', 'fecha', 'saldo', 'referencia']) as $ad) {
                $movs[] = [
                    'fecha'     => substr($ad->fecha, 0, 10),
                    'tipo'      => 'adelanto',
                    'etiqueta'  => 'Adelanto al proveedor',
                    'documento' => 'ADL-' . $ad->id,
                    'detalle'   => 'Plata entregada y aún no consumida' . ($ad->referencia ? " · Ref. {$ad->referencia}" : ''),
                    'monto'     => round((float) $ad->saldo, 2),
                    'variant'   => 'warning',
                ];
            }
        }

        // Orden cronológico y saldo corriendo.
        usort($movs, fn ($a, $b) => [$a['fecha'], $a['tipo']] <=> [$b['fecha'], $b['tipo']]);

        $acumulado = 0.0;
        foreach ($movs as $i => $m) {
            $acumulado += $m['monto'];
            $movs[$i]['saldo'] = round($acumulado, 2);
        }

        return $movs;
    }
}
