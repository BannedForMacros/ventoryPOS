<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\BalanceDiario;
use App\Models\BalanceDiarioItem;
use App\Models\ClienteAnticipo;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\Entrada;
use App\Models\Gasto;
use App\Models\ProveedorAdelanto;
use App\Models\Venta;
use App\Services\BalanceDiarioService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Balance diario: la foto patrimonial que el dueño arma cada día
 * (réplica del Excel "BALANCE FERRETERIA H&C").
 */
class BalanceDiarioController extends Controller
{
    public function __construct(private BalanceDiarioService $service) {}

    /**
     * Histórico de balances + acceso al día seleccionado.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $balances = BalanceDiario::deEmpresa($user->empresa_id)
            ->with('user')
            ->orderByDesc('fecha')
            ->paginate(30);

        return Inertia::render('Finanzas/BalanceDiario', [
            'balances' => $balances,
            'hoy'      => now()->toDateString(),
        ]);
    }

    /**
     * Genera (o regenera las líneas automáticas de) el balance de una fecha
     * y lo muestra para edición/conciliación.
     */
    public function show(Request $request, string $fecha)
    {
        $user = $request->user();

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $balance = BalanceDiario::deEmpresa($user->empresa_id)->where('fecha', $fecha)->first();

        // Borrador (o inexistente): regenerar líneas automáticas al momento,
        // así siempre refleja el estado real de CxC/CxP/stock/deudas.
        if (!$balance || $balance->esBorrador()) {
            $balance = $this->service->generar($user, $fecha);
        }

        $balance->load(['items', 'user']);

        // Detalle de gastos del día para el panel lateral (como su Excel).
        $gastos = Gasto::deEmpresa($user->empresa_id)
            ->where('fecha', $fecha)
            ->with(['tipo', 'concepto'])
            ->orderBy('id')
            ->get();

        // Salidas de dinero del día (para el card EN CONTRA, informativas:
        // ya están descontadas de las cuentas, no se suman al total).
        // Se excluyen los gastos porque tienen su propio panel.
        $labels = [
            'entrada_pago'                  => 'Pagos a proveedores',
            'entrada'                       => 'Pagos a proveedores',
            'proveedor_adelanto'            => 'Adelantos a proveedores',
            'deuda_pago'                    => 'Cuotas de deudas/préstamos',
            'devolucion'                    => 'Reembolsos a clientes',
            'cliente_anticipo_devolucion'   => 'Devolución de anticipos',
            'cierre_turno'                  => 'Faltantes de caja',
            'turno_consolidacion'           => 'Faltantes de caja',
            'ajuste'                        => 'Ajustes de saldo',
        ];
        $salidasDia = CuentaMovimiento::deEmpresa($user->empresa_id)
            ->whereBetween('fecha', [date('Y-m-d', strtotime($fecha . ' -3 months')), $fecha])
            ->where('tipo', 'egreso')
            ->where(fn ($q) => $q->whereNull('ref_tipo')->orWhere('ref_tipo', '!=', 'gasto'))
            ->selectRaw('ref_tipo, SUM(monto) as monto')
            ->groupBy('ref_tipo')
            ->get()
            ->map(fn ($r) => [
                'label' => $labels[$r->ref_tipo] ?? ($r->ref_tipo ?? 'Otros'),
                'monto' => round((float) $r->monto, 2),
            ])->values();

        return Inertia::render('Finanzas/BalanceDiarioDetalle', [
            'balance'    => $balance,
            'gastos'     => $gastos,
            'salidasDia' => $salidasDia,
        ]);
    }


    /**
     * F9/F11 — Detalle NORMALIZADO de una línea del balance (JSON).
     *
     * Todas las categorías devuelven la misma estructura para el componente
     * <DetalleAgrupado/>: cards de resumen + grupos por FECHA desplegables,
     * y cada fila con su AUDITORÍA (quién la registró). Trazabilidad total.
     *
     * {
     *   tipo: 'grupos',
     *   cards:  [{label, valor, color?}],
     *   desde?, hasta?,            // solo categorías con filtro de fechas
     *   grupos: [{id, titulo, subtitulo?, monto, tipo?, items: [
     *       {descripcion, extra?, monto, tipo?, user?}
     *   ]}],
     * }
     */
    public function detalleItem(Request $request, string $fecha, string $categoria)
    {
        $user = $request->user();
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $empresaId = $user->empresa_id;
        $refId     = $request->integer('ref_id') ?: null;

        $fmtDia = fn (string $f) => substr($f, 0, 10);
        $nombreCliente = fn ($c) => $c?->razon_social ?? trim(($c?->nombres ?? '') . ' ' . ($c?->apellidos ?? ''));

        switch ($categoria) {
            // ── Efectivo / cuenta bancaria: INGRESOS por día (bruto) ────
            // ── Gastos emitidos: EGRESOS por día ────────────────────────
            case 'efectivo':
            case 'cuenta_bancaria':
            case 'gastos_emitidos': {
                $esEgreso = $categoria === 'gastos_emitidos';
                $hasta = min($request->input('hasta', $fecha), $fecha);
                $desde = $request->input('desde', date('Y-m-d', strtotime($hasta . ' -3 months')));

                $q = CuentaMovimiento::deEmpresa($empresaId)
                    ->whereBetween('fecha', [$desde, $hasta])
                    ->where('tipo', $esEgreso ? 'egreso' : 'ingreso')
                    ->with(['user:id,name', 'cuenta:id,nombre']);

                if (!$esEgreso) {
                    $q->where('cuenta_id', $refId ?? TesoreriaService::efectivo($empresaId)->id);
                }

                $movs = $q->orderByDesc('fecha')->orderByDesc('id')->limit(1000)->get();

                $grupos = $movs->groupBy(fn ($m) => $fmtDia((string) $m->fecha))
                    ->map(fn ($rows, $f) => [
                        'id'       => $f,
                        'titulo'   => $f,
                        'esFecha'  => true,
                        'monto'    => round((float) $rows->sum('monto'), 2),
                        'tipo'     => $esEgreso ? 'egreso' : 'ingreso',
                        'items'    => $rows->map(fn ($m) => [
                            'descripcion' => $m->descripcion,
                            'extra'       => $esEgreso ? ($m->cuenta?->nombre ? "desde {$m->cuenta->nombre}" : null) : null,
                            'monto'       => (float) $m->monto,
                            'tipo'        => $m->tipo,
                            'user'        => $m->user?->name,
                        ])->values(),
                    ])->values();

                $total = round((float) $movs->sum('monto'), 2);

                return response()->json([
                    'tipo'   => 'grupos',
                    'desde'  => $desde,
                    'hasta'  => $hasta,
                    'cards'  => [
                        ['label' => $esEgreso ? 'Total salidas (período)' : 'Total ingresado (período)',
                         'valor' => $total, 'color' => $esEgreso ? 'danger' : 'success'],
                        ['label' => 'Días con movimiento', 'valor' => $grupos->count(), 'esNumero' => true],
                        ['label' => 'Operaciones', 'valor' => $movs->count(), 'esNumero' => true],
                    ],
                    'grupos' => $grupos,
                ]);
            }

            // ── Stock valorizado: producto por producto ─────────────────
            case 'stock': {
                $filas = DB::table('stock')
                    ->join('productos', 'productos.id', '=', 'stock.producto_id')
                    ->where('productos.empresa_id', $empresaId)
                    ->where('productos.activo', true)
                    ->where('stock.cantidad', '!=', 0)
                    ->selectRaw('productos.nombre, SUM(stock.cantidad) as cantidad, productos.precio_costo,
                                 SUM(stock.cantidad * productos.precio_costo) as valor')
                    ->groupBy('productos.id', 'productos.nombre', 'productos.precio_costo')
                    ->orderByDesc('valor')
                    ->limit(500)
                    ->get();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Valor total del inventario', 'valor' => round((float) $filas->sum('valor'), 2), 'color' => 'success'],
                        ['label' => 'Productos con stock', 'valor' => $filas->count(), 'esNumero' => true],
                    ],
                    'grupos' => $filas->map(fn ($f, $i) => [
                        'id'        => (string) $i,
                        'titulo'    => $f->nombre,
                        'subtitulo' => rtrim(rtrim(number_format((float) $f->cantidad, 2), '0'), '.') . ' und × S/' . number_format((float) $f->precio_costo, 2) . ' (costo del día)',
                        'monto'     => round((float) $f->valor, 2),
                        'items'     => [],
                    ])->values(),
                ]);
            }

            // ── Deudas por cobrar: por fecha de venta, con vendedor ─────
            case 'cxc': {
                $ventas = Venta::deEmpresa($empresaId)->conSaldoPendiente()
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'user:id,name'])
                    ->orderByDesc('fecha_venta')
                    ->limit(500)
                    ->get();

                $grupos = $ventas->groupBy(fn ($v) => $v->fecha_venta->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('saldo_pendiente'), 2),
                        'tipo'    => 'neutro',
                        'items'   => $rows->map(fn ($v) => [
                            'descripcion' => "{$v->numero} — " . $nombreCliente($v->cliente),
                            'extra'       => 'total S/' . number_format((float) $v->total, 2)
                                . ' · pagado S/' . number_format((float) $v->monto_pagado, 2)
                                . ($v->fecha_vencimiento ? ' · vence ' . $v->fecha_vencimiento->format('d/m/Y') : ''),
                            'monto'       => (float) $v->saldo_pendiente,
                            'user'        => $v->user?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Total por cobrar', 'valor' => round((float) $ventas->sum('saldo_pendiente'), 2), 'color' => 'danger'],
                        ['label' => 'Ventas a crédito con saldo', 'valor' => $ventas->count(), 'esNumero' => true],
                    ],
                    'grupos' => $grupos,
                ]);
            }

            // ── Proveedores por pagar: por fecha, con quién registró ────
            case 'cxp': {
                $entradas = Entrada::deEmpresa($empresaId)->confirmado()
                    ->where('estado_pago', '!=', 'pagado')
                    ->whereRaw('total - monto_pagado > 0.01')
                    ->with(['proveedorRel:id,razon_social,nombre_comercial', 'user:id,name'])
                    ->orderByDesc('fecha')
                    ->limit(500)
                    ->get();

                $grupos = $entradas->groupBy(fn ($e) => $e->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum(fn ($e) => $e->saldoPendiente()), 2),
                        'tipo'    => 'egreso',
                        'items'   => $rows->map(fn ($e) => [
                            'descripcion' => ($e->proveedorRel?->razon_social ?? $e->proveedorRel?->nombre_comercial ?? $e->proveedor ?? 'Proveedor')
                                . ($e->numero_documento ? " ({$e->numero_documento})" : ''),
                            'extra'       => 'total S/' . number_format((float) $e->total, 2)
                                . ' · pagado S/' . number_format((float) $e->monto_pagado, 2),
                            'monto'       => $e->saldoPendiente(),
                            'user'        => $e->user?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Total por pagar', 'valor' => round((float) $entradas->sum(fn ($e) => $e->saldoPendiente()), 2), 'color' => 'danger'],
                        ['label' => 'Compras con saldo', 'valor' => $entradas->count(), 'esNumero' => true],
                    ],
                    'grupos' => $grupos,
                ]);
            }

            // ── Anticipos de clientes: por fecha, valorizados hoy ───────
            case 'anticipo_cliente': {
                $anticipos = ClienteAnticipo::deEmpresa($empresaId)->activo()
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'producto:id,nombre,precio_venta', 'user:id,name'])
                    ->orderByDesc('fecha')
                    ->limit(500)
                    ->get();

                $grupos = $anticipos->groupBy(fn ($a) => $a->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum(fn ($a) => $a->valorPasivoHoy()), 2),
                        'tipo'    => 'egreso',
                        'items'   => $rows->map(fn ($a) => [
                            'descripcion' => $nombreCliente($a->cliente),
                            'extra'       => ($a->tipo_valorizacion === 'material'
                                    ? "{$a->producto?->nombre} × " . (float) $a->cantidad_pendiente . ' a S/' . number_format((float) ($a->producto?->precio_venta ?? 0), 2) . ' del día'
                                    : 'Dinero')
                                . ' · recibió S/' . number_format((float) $a->monto, 2),
                            'monto'       => $a->valorPasivoHoy(),
                            'user'        => $a->user?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Pasivo a precio del día', 'valor' => round((float) $anticipos->sum(fn ($a) => $a->valorPasivoHoy()), 2), 'color' => 'danger'],
                        ['label' => 'Anticipos activos', 'valor' => $anticipos->count(), 'esNumero' => true],
                    ],
                    'grupos' => $grupos,
                ]);
            }

            // ── Adelanto a proveedor puntual: sus aplicaciones ──────────
            case 'adelanto_proveedor': {
                $adelanto = ProveedorAdelanto::deEmpresa($empresaId)
                    ->with(['proveedor:id,razon_social,nombre_comercial', 'aplicaciones.entrada:id,numero_documento', 'aplicaciones.user:id,name', 'user:id,name'])
                    ->findOrFail($refId);

                $grupos = $adelanto->aplicaciones->groupBy(fn ($ap) => $ap->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('monto'), 2),
                        'tipo'    => 'egreso',
                        'items'   => $rows->map(fn ($ap) => [
                            'descripcion' => $ap->entrada ? "Aplicado a compra {$ap->entrada->numero_documento}" : ($ap->observacion ?? 'Aplicación'),
                            'monto'       => (float) $ap->monto,
                            'user'        => $ap->user?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Proveedor', 'valor' => $adelanto->proveedor?->razon_social ?? $adelanto->proveedor?->nombre_comercial ?? '—', 'esTexto' => true],
                        ['label' => 'Entregado (por ' . ($adelanto->user?->name ?? '—') . ')', 'valor' => (float) $adelanto->monto],
                        ['label' => 'Saldo a favor', 'valor' => (float) $adelanto->saldo, 'color' => 'success'],
                    ],
                    'grupos' => $grupos,
                ]);
            }

            // ── Deuda / préstamo puntual: historial de movimientos ──────
            case 'deuda':
            case 'personal':
            case 'prestamo_otorgado': {
                $deuda = Deuda::deEmpresa($empresaId)
                    ->with(['pagos.metodoPago:id,nombre', 'pagos.cuenta:id,nombre', 'pagos.user:id,name', 'user:id,name'])
                    ->findOrFail($refId);

                $grupos = $deuda->pagos->sortByDesc('fecha')->groupBy(fn ($p) => $p->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('monto'), 2),
                        'tipo'    => 'neutro',
                        'items'   => $rows->map(fn ($p) => [
                            'descripcion' => ($p->tipo === 'amortizacion' ? 'Amortización' : 'Incremento')
                                . ($p->cuenta ? " · {$p->cuenta->nombre}" : ''),
                            'extra'       => $p->observacion,
                            'monto'       => (float) $p->monto,
                            'tipo'        => $p->tipo === 'amortizacion' ? 'ingreso' : 'egreso',
                            'user'        => $p->user?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Deuda (registrada por ' . ($deuda->user?->name ?? '—') . ')', 'valor' => $deuda->nombre, 'esTexto' => true],
                        ['label' => 'Monto original', 'valor' => (float) $deuda->monto_original],
                        ['label' => 'Saldo actual', 'valor' => (float) $deuda->saldo,
                         'color' => $deuda->direccion === 'por_cobrar' ? 'success' : 'danger'],
                    ],
                    'grupos' => $grupos,
                ]);
            }
        }

        abort(404, 'Esta línea no tiene detalle.');
    }

    /**
     * Actualiza una línea manual (monto) o su check de conciliación ("OK").
     */
    public function actualizarItem(Request $request, BalanceDiarioItem $item)
    {
        $user    = $request->user();
        $balance = $item->balance;

        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($balance->esBorrador(), 422, 'El balance ya fue confirmado.');

        $data = $request->validate([
            'monto'      => ['nullable', 'numeric'],
            'conciliado' => ['nullable', 'boolean'],
        ]);

        // Solo las líneas manuales aceptan cambio de monto; el check de
        // conciliación aplica a cualquiera (es la marca "OK" del Excel).
        $update = [];
        if (array_key_exists('monto', $data) && $data['monto'] !== null) {
            abort_unless($item->es_manual, 422, 'Esta línea se calcula automáticamente.');
            $update['monto'] = round((float) $data['monto'], 2);
        }
        if (array_key_exists('conciliado', $data) && $data['conciliado'] !== null) {
            $update['conciliado'] = $data['conciliado'];
        }

        if ($update) {
            $item->update($update);
            $balance->recalcularTotales();
        }

        return back();
    }

    /**
     * Agrega una línea manual extra (los "OSCAR ALBERTO - DEPÓSITO...",
     * "16 FIERRO 3/4", etc. del Excel).
     */
    public function agregarItem(Request $request, BalanceDiario $balance)
    {
        $user = $request->user();
        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($balance->esBorrador(), 422, 'El balance ya fue confirmado.');

        $data = $request->validate([
            'seccion'     => ['required', Rule::in(['favor', 'contra'])],
            'descripcion' => ['required', 'string', 'max:250'],
            'monto'       => ['required', 'numeric', 'min:0'],
        ]);

        $maxOrden = (int) $balance->items()->where('seccion', $data['seccion'])->max('orden');

        $item = $balance->items()->create([
            'seccion'     => $data['seccion'],
            'categoria'   => $data['seccion'] === 'favor' ? 'otro_favor' : 'otro_contra',
            'descripcion' => $data['descripcion'],
            'monto'       => round((float) $data['monto'], 2),
            'es_manual'   => true,
            'conciliado'  => false,
            'orden'       => $maxOrden + 1,
        ]);

        // Trazabilidad: toda línea manual queda en auditoría con autor,
        // fecha y monto. (Lo recurrente debe registrarse en su módulo:
        // deudas, anticipos, adelantos... no como línea suelta.)
        \App\Services\AuditoriaService::log('balance.linea_manual', $item, [
            'balance_fecha' => $balance->fecha->toDateString(),
            'seccion'       => $data['seccion'],
            'descripcion'   => $data['descripcion'],
            'monto'         => (float) $data['monto'],
        ], $user);

        $balance->recalcularTotales();

        return back()->with('success', 'Línea agregada.');
    }

    /**
     * Elimina una línea manual.
     */
    public function eliminarItem(Request $request, BalanceDiarioItem $item)
    {
        $user    = $request->user();
        $balance = $item->balance;

        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($balance->esBorrador(), 422, 'El balance ya fue confirmado.');
        abort_unless($item->es_manual, 422, 'Las líneas automáticas no se pueden eliminar.');

        \App\Services\AuditoriaService::log('balance.linea_manual_eliminada', $item, [
            'balance_fecha' => $balance->fecha->toDateString(),
            'descripcion'   => $item->descripcion,
            'monto'         => (float) $item->monto,
        ], $user);

        $item->delete();
        $balance->recalcularTotales();

        return back()->with('success', 'Línea eliminada.');
    }

    /**
     * Confirma el balance del día: snapshot inmutable que servirá de
     * "BALANCE AYER" para el siguiente.
     */
    public function confirmar(Request $request, BalanceDiario $balance)
    {
        $user = $request->user();
        abort_if($balance->empresa_id !== $user->empresa_id, 403);

        $this->service->confirmar($balance, $user);

        return back()->with('success', 'Balance confirmado. Ya es la referencia para el día siguiente.');
    }
}
