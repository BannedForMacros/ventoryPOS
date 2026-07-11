<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\BalanceDiario;
use App\Models\BalanceDiarioItem;
use App\Models\ClienteAnticipo;
use App\Models\Cuenta;
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
        // Con su cuenta: el cliente quiere saber DE DÓNDE salió cada gasto.
        $gastos = Gasto::deEmpresa($user->empresa_id)
            ->where('fecha', $fecha)
            ->with(['tipo', 'concepto', 'cuenta:id,nombre,es_efectivo'])
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

        // ── Movimientos del DÍA (la película de hoy) ────────────────────
        // Todo lo que entró y salió EN ESTA FECHA, agrupado por concepto.
        // Es la respuesta a "¿por qué el balance se movió hoy?": las líneas
        // del balance son acumulados en bruto y una venta puntual se pierde
        // dentro de ellos; aquí se ve cada operación del día con su origen.
        $origenLabels = [
            'venta'                         => 'Ventas cobradas',
            'venta_abono'                   => 'Cobros de créditos (CxC)',
            'cliente_anticipo'              => 'Anticipos de clientes',
            'cliente_anticipo_devolucion'   => 'Devolución de anticipos',
            'gasto'                         => 'Gastos del día',
            'entrada_pago'                  => 'Pagos a proveedores',
            'entrada'                       => 'Pagos a proveedores',
            'proveedor_adelanto'            => 'Adelantos a proveedores',
            'proveedor_adelanto_devolucion' => 'Devolución de adelantos',
            'deuda_pago'                    => 'Cuotas de deudas/préstamos',
            'devolucion'                    => 'Reembolsos a clientes',
            'cierre_turno'                  => 'Cierres de caja',
            'turno_consolidacion'           => 'Consolidación de caja',
            'ajuste'                        => 'Ajustes de saldo',
        ];
        $movimientosDia = CuentaMovimiento::deEmpresa($user->empresa_id)
            ->where('fecha', $fecha)
            ->with(['cuenta:id,nombre', 'user:id,name'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($m) => $origenLabels[$m->ref_tipo] ?? 'Otros movimientos')
            ->map(fn ($rows, $label) => [
                'label'    => $label,
                'ingresos' => round((float) $rows->where('tipo', 'ingreso')->sum('monto'), 2),
                'egresos'  => round((float) $rows->where('tipo', 'egreso')->sum('monto'), 2),
                'items'    => $rows->map(fn ($m) => [
                    'descripcion' => $m->descripcion,
                    'cuenta'      => $m->cuenta?->nombre ?? '—',
                    'tipo'        => $m->tipo,
                    'monto'       => (float) $m->monto,
                    'user'        => $m->user?->name,
                ])->values(),
            ])
            ->sortByDesc(fn ($g) => $g['ingresos'] + $g['egresos'])
            ->values();

        // ── Saldo REAL por cuenta a esta fecha (lo que "tengo") ────────
        // Neto = ingresos − egresos hasta la fecha. Es la respuesta directa a
        // "¿cuánto tengo en efectivo / en mis tarjetas / en cada banco?" sin
        // tener que restar mentalmente el bruto A FAVOR y los gastos emitidos.
        $tesoreria = app(TesoreriaService::class);
        $saldosCuentas = Cuenta::deEmpresa($user->empresa_id)->activo()
            ->orderByDesc('es_efectivo')->orderBy('nombre')->get()
            ->map(fn ($c) => [
                'nombre'      => $c->nombre,
                'banco'       => $c->banco,
                'es_efectivo' => (bool) $c->es_efectivo,
                'saldo'       => $tesoreria->saldo($c->id, $fecha),
            ])->values();

        // ── Variación vs día anterior (por categoría) ───────────────────
        // Compara el monto de cada categoría HOY contra el último balance
        // CONFIRMADO anterior (que congeló los valores reales de ese día). Así
        // se ve qué subió/bajó y, al hacer clic, se abre el detalle de esa línea.
        $anterior = BalanceDiario::deEmpresa($user->empresa_id)
            ->confirmado()
            ->where('fecha', '<', $fecha)
            ->orderByDesc('fecha')
            ->with('items')
            ->first();

        $labelCat = [
            'efectivo' => 'Efectivo', 'cuenta_bancaria' => 'Cuentas bancarias',
            'stock' => 'Stock (inventario)', 'cxc' => 'Deudas por cobrar',
            'cxp' => 'Deudas por pagar', 'prestamo_otorgado' => 'Préstamos otorgados',
            'adelanto_proveedor' => 'Adelantos a proveedores', 'anticipo_cliente' => 'Anticipos de clientes',
            'planilla_descuento' => 'Descuentos de planilla', 'gastos_emitidos' => 'Gastos emitidos',
            'deuda' => 'Deudas/préstamos', 'personal' => 'Personal',
        ];

        $totHoy  = $balance->items->groupBy('categoria');
        $totAyer = $anterior ? $anterior->items->groupBy('categoria') : collect();

        $variaciones = collect($totHoy->keys())->merge($totAyer->keys())->unique()->values()
            ->map(function ($cat) use ($totHoy, $totAyer, $labelCat) {
                $hoy  = round((float) ($totHoy->get($cat)?->sum('monto') ?? 0), 2);
                $ayer = round((float) ($totAyer->get($cat)?->sum('monto') ?? 0), 2);
                return [
                    'categoria' => $cat,
                    'seccion'   => $totHoy->get($cat)?->first()->seccion ?? ($totAyer->get($cat)?->first()->seccion ?? 'favor'),
                    'label'     => $labelCat[$cat] ?? ucfirst(str_replace('_', ' ', (string) $cat)),
                    'hoy'       => $hoy,
                    'ayer'      => $ayer,
                    'delta'     => round($hoy - $ayer, 2),
                ];
            })
            ->filter(fn ($v) => abs($v['delta']) >= 0.01) // solo lo que cambió
            ->sortByDesc(fn ($v) => abs($v['delta']))
            ->values();

        return Inertia::render('Finanzas/BalanceDiarioDetalle', [
            'balance'        => $balance,
            'gastos'         => $gastos,
            'salidasDia'     => $salidasDia,
            'movimientosDia' => $movimientosDia,
            'saldosCuentas'  => $saldosCuentas,
            'variaciones'    => $variaciones,
            'balanceAnteriorFecha' => $anterior?->fecha?->toDateString(),
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
    /**
     * Movimientos que cambian el VALOR del inventario de la empresa en una fecha.
     * Une las fuentes que suben/bajan el stock total (las transferencias internas
     * se excluyen porque no cambian el valor total de la empresa). El valor de
     * cada movimiento se calcula con productos.precio_costo (igual que el balance).
     *
     * Devuelve filas con: tipo, producto, documento, cantidad (± base), valor (±), usuario.
     */
    private function movimientosStockDia(int $empresaId, string $fecha): \Illuminate\Support\Collection
    {
        // Ventas (−)
        $ventas = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->whereDate('v.fecha_venta', $fecha)
            ->selectRaw("'Ventas' as tipo, v.numero as documento, p.nombre as producto, u.name as usuario,
                         (-1 * vi.cantidad_base) as cantidad, (-1 * vi.cantidad_base * p.precio_costo) as valor")
            ->get();

        // Salidas (−)
        $salidas = DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->join('productos as p', 'p.id', '=', 'sd.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.empresa_id', $empresaId)->where('s.estado', 'confirmado')
            ->whereDate('s.fecha', $fecha)
            ->selectRaw("'Salidas de inventario' as tipo, s.numero_documento as documento, p.nombre as producto, u.name as usuario,
                         (-1 * sd.cantidad_base) as cantidad, (-1 * sd.cantidad_base * p.precio_costo) as valor")
            ->get();

        // Entradas / compras (+)
        $entradas = DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->join('productos as p', 'p.id', '=', 'ed.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.empresa_id', $empresaId)->where('e.estado', 'confirmado')
            ->whereDate('e.fecha', $fecha)
            ->selectRaw("'Entradas (compras)' as tipo, e.numero_documento as documento, p.nombre as producto, u.name as usuario,
                         ed.cantidad_base as cantidad, (ed.cantidad_base * p.precio_costo) as valor")
            ->get();

        // Devoluciones con reingreso (+)
        $devoluciones = DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->join('productos as p', 'p.id', '=', 'dd.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.empresa_id', $empresaId)->where('d.estado', 'completada')
            ->where('dd.restock', true)
            ->whereDate('d.fecha', $fecha)
            ->selectRaw("'Devoluciones (reingreso)' as tipo, d.numero as documento, p.nombre as producto, u.name as usuario,
                         dd.cantidad_base as cantidad, (dd.cantidad_base * p.precio_costo) as valor")
            ->get();

        // Ajustes por cierre de inventario (+/−)
        $cierres = DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->join('productos as p', 'p.id', '=', 'ci.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.empresa_id', $empresaId)->where('c.estado', 'confirmado')
            ->where('ci.diferencia', '!=', 0)
            ->whereDate('c.fecha', $fecha)
            ->selectRaw("'Ajustes de inventario' as tipo, ('CI-' || c.id) as documento, p.nombre as producto, u.name as usuario,
                         ci.diferencia as cantidad, (ci.diferencia * p.precio_costo) as valor")
            ->get();

        return collect()
            ->concat($ventas)->concat($salidas)->concat($entradas)
            ->concat($devoluciones)->concat($cierres)
            ->map(function ($m) {
                $m->cantidad = (float) $m->cantidad;
                $m->valor    = (float) $m->valor;
                return $m;
            })
            ->values();
    }

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
                } elseif ($refId) {
                    // Gastos emitidos desglosados por cuenta: filtrar la cuenta
                    // de la línea (sin ref_id — balances viejos — muestra todas).
                    $q->where('cuenta_id', $refId);
                }

                $movs = $q->orderByDesc('fecha')->orderByDesc('id')->limit(1000)->get();

                // Origen legible del movimiento (columna propia, no "raya")
                $origenes = [
                    'venta' => 'Venta', 'venta_abono' => 'Cobro de crédito',
                    'cliente_anticipo' => 'Anticipo de cliente', 'cliente_anticipo_devolucion' => 'Devolución de anticipo',
                    'gasto' => 'Gasto', 'entrada_pago' => 'Pago a proveedor', 'entrada' => 'Pago a proveedor',
                    'proveedor_adelanto' => 'Adelanto a proveedor', 'proveedor_adelanto_devolucion' => 'Devolución de adelanto',
                    'deuda_pago' => 'Deuda/préstamo', 'devolucion' => 'Reembolso',
                    'cierre_turno' => 'Cierre de caja', 'turno_consolidacion' => 'Consolidación',
                    'ajuste' => 'Ajuste de saldo',
                ];

                $grupos = $movs->groupBy(fn ($m) => $fmtDia((string) $m->fecha))
                    ->map(fn ($rows, $f) => [
                        'id'       => $f,
                        'titulo'   => $f,
                        'esFecha'  => true,
                        'monto'    => round((float) $rows->sum('monto'), 2),
                        'tipo'     => $esEgreso ? 'egreso' : 'ingreso',
                        'items'    => $rows->map(fn ($m) => [
                            'descripcion' => $m->descripcion,
                            'origen'      => $origenes[$m->ref_tipo] ?? ($m->ref_tipo ?? '—'),
                            'cuenta'      => $m->cuenta?->nombre ?? '—',
                            'monto'       => (float) $m->monto,
                            'tipo'        => $m->tipo,
                            'user'        => $m->user?->name,
                        ])->values(),
                    ])->values();

                $total = round((float) $movs->sum('monto'), 2);

                $itemCols = [['campo' => 'descripcion', 'label' => 'Descripción'], ['campo' => 'origen', 'label' => 'Origen']];
                if ($esEgreso) {
                    $itemCols[] = ['campo' => 'cuenta', 'label' => 'Desde cuenta'];
                }

                $cards = [
                    ['label' => $esEgreso ? 'Total salidas (período)' : 'Total ingresado (período)',
                     'valor' => $total, 'color' => $esEgreso ? 'danger' : 'success'],
                    ['label' => 'Días con movimiento', 'valor' => $grupos->count(), 'esNumero' => true],
                    ['label' => 'Operaciones', 'valor' => $movs->count(), 'esNumero' => true],
                ];
                // Línea desglosada por cuenta: decir de cuál es este detalle.
                if ($esEgreso && $refId) {
                    $nombreCuenta = Cuenta::where('id', $refId)->value('nombre');
                    if ($nombreCuenta) {
                        array_unshift($cards, ['label' => 'Salidas de la cuenta', 'valor' => $nombreCuenta, 'esTexto' => true]);
                    }
                }

                return response()->json([
                    'tipo'   => 'grupos',
                    'desde'  => $desde,
                    'hasta'  => $hasta,
                    'cards'  => $cards,
                    'itemCols'   => $itemCols,
                    'montoLabel' => 'Monto',
                    'grupos'     => $grupos,
                ]);
            }

            // ── Stock: MOVIMIENTOS del día (trazabilidad de por qué subió/bajó)
            case 'stock': {
                // Valor total del inventario AHORA (misma valuación que el balance).
                $valorInventario = (float) DB::table('stock')
                    ->join('productos', 'productos.id', '=', 'stock.producto_id')
                    ->where('productos.empresa_id', $empresaId)
                    ->where('productos.activo', true)
                    ->selectRaw('COALESCE(SUM(stock.cantidad * productos.precio_costo), 0) as v')
                    ->value('v');

                // Movimientos que cambian el VALOR del inventario de la empresa en
                // esta fecha (las transferencias internas no cambian el total).
                $movs = $this->movimientosStockDia($empresaId, $fecha);

                $entradasVal = round((float) $movs->where('valor', '>', 0)->sum('valor'), 2);
                $salidasVal  = round((float) $movs->where('valor', '<', 0)->sum('valor'), 2); // negativo
                $variacion   = round($entradasVal + $salidasVal, 2);

                $fmtCant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

                $grupos = $movs->groupBy('tipo')->map(fn ($rows, $tipo) => [
                    'id'     => $tipo,
                    'titulo' => $tipo,
                    'monto'  => round((float) $rows->sum('valor'), 2),
                    'tipo'   => $rows->sum('valor') >= 0 ? 'ingreso' : 'egreso',
                    'items'  => $rows->map(fn ($m) => [
                        'producto'  => $m->producto,
                        'documento' => $m->documento ?? '—',
                        'cantidad'  => ($m->cantidad >= 0 ? '+' : '') . $fmtCant($m->cantidad),
                        'monto'     => round((float) $m->valor, 2),
                        'tipo'      => $m->valor >= 0 ? 'ingreso' : 'egreso',
                        'user'      => $m->usuario,
                    ])->values(),
                ])->sortByDesc(fn ($g) => abs($g['monto']))->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Valor del inventario hoy', 'valor' => round($valorInventario, 2), 'color' => 'success'],
                        ['label' => 'Entradas del día (+)', 'valor' => $entradasVal, 'color' => 'success'],
                        ['label' => 'Salidas del día (−)', 'valor' => abs($salidasVal), 'color' => 'danger'],
                        ['label' => 'Variación del día', 'valor' => $variacion, 'color' => $variacion >= 0 ? 'success' : 'danger'],
                    ],
                    'itemCols' => [
                        ['campo' => 'producto',  'label' => 'Producto'],
                        ['campo' => 'documento', 'label' => 'Documento'],
                        ['campo' => 'cantidad',  'label' => 'Cant.'],
                    ],
                    'montoLabel' => 'Valor',
                    'grupos'     => $grupos,
                ]);
            }

            // ── Deudas por cobrar: por fecha de venta, con vendedor ─────
            case 'cxc': {
                $ventas = Venta::deEmpresa($empresaId)->conSaldoPendiente()
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'user:id,name',
                            'pagos.metodoPago:id,nombre',
                            'abonos.metodoPago:id,nombre', 'abonos.cuenta:id,nombre', 'abonos.user:id,name'])
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
                            'sub'         => 'Vendida el ' . $v->fecha_venta->format('d/m/Y h:i a'),
                            'total'       => 'S/ ' . number_format((float) $v->total, 2),
                            'pagado'      => 'S/ ' . number_format((float) $v->monto_pagado, 2),
                            'vence'       => $v->fecha_vencimiento?->format('d/m/Y') ?? '—',
                            'monto'       => (float) $v->saldo_pendiente,
                            'user'        => $v->user?->name,
                            // Trazabilidad: pago inicial del POS + cada abono posterior.
                            'historial'   => $v->pagos
                                ->filter(fn ($p) => (float) $p->monto - (float) $p->vuelto > 0)
                                ->map(fn ($p) => [
                                    'fecha'       => $v->fecha_venta->format('d/m/Y'),
                                    'descripcion' => 'Pago inicial (en la venta)'
                                        . ($p->metodoPago ? " · {$p->metodoPago->nombre}" : ''),
                                    'monto'       => round((float) $p->monto - (float) $p->vuelto, 2),
                                    'user'        => $v->user?->name,
                                ])
                                ->concat($v->abonos->sortBy('fecha')->values()->map(fn ($a) => [
                                    'fecha'       => $a->fecha->format('d/m/Y'),
                                    'descripcion' => 'Abono'
                                        . ($a->metodoPago ? " · {$a->metodoPago->nombre}" : '')
                                        . ($a->cuenta ? " → {$a->cuenta->nombre}" : '')
                                        . ($a->referencia ? " · ref. {$a->referencia}" : ''),
                                    'monto'       => (float) $a->monto,
                                    'user'        => $a->user?->name,
                                ]))->values(),
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Total por cobrar', 'valor' => round((float) $ventas->sum('saldo_pendiente'), 2), 'color' => 'danger'],
                        ['label' => 'Ventas a crédito con saldo', 'valor' => $ventas->count(), 'esNumero' => true],
                    ],
                    'itemCols' => [
                        ['campo' => 'descripcion', 'label' => 'Venta / Cliente'],
                        ['campo' => 'total',       'label' => 'Total'],
                        ['campo' => 'pagado',      'label' => 'Pagado'],
                        ['campo' => 'vence',       'label' => 'Vence'],
                    ],
                    'montoLabel' => 'Saldo',
                    'grupos' => $grupos,
                ]);
            }

            // ── Proveedores por pagar: por fecha, con quién registró ────
            case 'cxp': {
                $entradas = Entrada::deEmpresa($empresaId)->confirmado()
                    ->where('estado_pago', '!=', 'pagado')
                    ->whereRaw('total - monto_pagado > 0.01')
                    ->with(['proveedorRel:id,razon_social,nombre_comercial', 'user:id,name',
                            'pagosParciales.metodoPago:id,nombre', 'pagosParciales.cuenta:id,nombre', 'pagosParciales.user:id,name'])
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
                            'descripcion' => $e->proveedorRel?->razon_social ?? $e->proveedorRel?->nombre_comercial ?? $e->proveedor ?? 'Proveedor',
                            'sub'         => 'Compra del ' . $e->fecha->format('d/m/Y')
                                . ($e->numero_documento ? " · Doc. {$e->numero_documento}" : ''),
                            'total'       => 'S/ ' . number_format((float) $e->total, 2),
                            'pagado'      => 'S/ ' . number_format((float) $e->monto_pagado, 2),
                            'monto'       => $e->saldoPendiente(),
                            'user'        => $e->user?->name,
                            // Trazabilidad: cada pago realizado a esta compra.
                            'historial'   => $e->pagosParciales->sortBy('fecha')->values()->map(fn ($p) => [
                                'fecha'       => $p->fecha->format('d/m/Y'),
                                'descripcion' => ($p->proveedor_adelanto_id ? "Consumo de adelanto #{$p->proveedor_adelanto_id}" : 'Pago')
                                    . ($p->metodoPago ? " · {$p->metodoPago->nombre}" : '')
                                    . ($p->cuenta ? " ← {$p->cuenta->nombre}" : '')
                                    . ($p->referencia ? " · ref. {$p->referencia}" : ''),
                                'monto'       => (float) $p->monto,
                                'user'        => $p->user?->name,
                            ]),
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Total por pagar', 'valor' => round((float) $entradas->sum(fn ($e) => $e->saldoPendiente()), 2), 'color' => 'danger'],
                        ['label' => 'Compras con saldo', 'valor' => $entradas->count(), 'esNumero' => true],
                    ],
                    'itemCols' => [
                        ['campo' => 'descripcion', 'label' => 'Proveedor'],
                        ['campo' => 'total',       'label' => 'Total compra'],
                        ['campo' => 'pagado',      'label' => 'Pagado'],
                    ],
                    'montoLabel' => 'Saldo',
                    'grupos' => $grupos,
                ]);
            }

            // ── Anticipos de clientes: por fecha, valorizados hoy ───────
            case 'anticipo_cliente': {
                $anticipos = ClienteAnticipo::deEmpresa($empresaId)->activo()
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'producto:id,nombre,precio_venta', 'user:id,name',
                            'aplicaciones.user:id,name'])
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
                            'sub'         => 'Recibido el ' . $a->fecha->format('d/m/Y'),
                            'modalidad'   => $a->tipo_valorizacion === 'material'
                                ? "{$a->producto?->nombre} × " . (float) $a->cantidad_pendiente . ' a S/' . number_format((float) ($a->producto?->precio_venta ?? 0), 2) . ' del día'
                                : 'Dinero',
                            'recibido'    => 'S/ ' . number_format((float) $a->monto, 2),
                            'monto'       => $a->valorPasivoHoy(),
                            'user'        => $a->user?->name,
                            // Trazabilidad: entregas de material / usos del anticipo.
                            'historial'   => $a->aplicaciones->sortBy('fecha')->values()->map(fn ($ap) => [
                                'fecha'       => $ap->fecha->format('d/m/Y'),
                                'descripcion' => 'Entrega'
                                    . ($ap->cantidad ? ' de ' . (float) $ap->cantidad . ' und' : '')
                                    . ($ap->observacion ? " · {$ap->observacion}" : ''),
                                'monto'       => (float) $ap->monto,
                                'user'        => $ap->user?->name,
                            ]),
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Pasivo a precio del día', 'valor' => round((float) $anticipos->sum(fn ($a) => $a->valorPasivoHoy()), 2), 'color' => 'danger'],
                        ['label' => 'Anticipos activos', 'valor' => $anticipos->count(), 'esNumero' => true],
                    ],
                    'itemCols' => [
                        ['campo' => 'descripcion', 'label' => 'Cliente'],
                        ['campo' => 'modalidad',   'label' => 'Modalidad / Material'],
                        ['campo' => 'recibido',    'label' => 'Recibido'],
                    ],
                    'montoLabel' => 'Pasivo hoy',
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
                    'itemCols'   => [['campo' => 'descripcion', 'label' => 'Aplicación']],
                    'montoLabel' => 'Monto',
                    'grupos' => $grupos,
                ]);
            }

            // ── Descuentos de planilla: pendientes (suman) + aplicados ──
            case 'planilla_descuento': {
                $descuentos = \App\Models\PlanillaDescuento::deEmpresa($empresaId)
                    ->with(['trabajador:id,name', 'registradoPor:id,name'])
                    ->whereBetween('fecha', [date('Y-m-d', strtotime($fecha . ' -3 months')), $fecha])
                    ->orderByDesc('fecha')->orderByDesc('id')
                    ->get();

                $estados = ['pendiente' => 'Pendiente', 'aplicado' => 'Aplicado en planilla', 'anulado' => 'Anulado'];

                $grupos = $descuentos->groupBy(fn ($d) => $d->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        // Solo lo PENDIENTE suma a la línea del balance.
                        'monto'   => round((float) $rows->where('estado', 'pendiente')->sum('monto'), 2),
                        'tipo'    => 'neutro',
                        'items'   => $rows->map(fn ($d) => [
                            'descripcion' => $d->motivo,
                            'trabajador'  => $d->trabajador?->name ?? '—',
                            'estado'      => ($estados[$d->estado] ?? $d->estado)
                                . ($d->estado === 'aplicado' && $d->fecha_aplicacion ? ' (' . $d->fecha_aplicacion->format('d/m/Y') . ')' : ''),
                            'monto'       => (float) $d->monto,
                            'tipo'        => $d->estado === 'pendiente' ? 'ingreso' : null,
                            'user'        => $d->registradoPor?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Pendiente de descontar', 'valor' => round((float) $descuentos->where('estado', 'pendiente')->sum('monto'), 2), 'color' => 'success'],
                        ['label' => 'Ya aplicado en planilla (rango)', 'valor' => round((float) $descuentos->where('estado', 'aplicado')->sum('monto'), 2)],
                    ],
                    'itemCols' => [
                        ['campo' => 'descripcion', 'label' => 'Motivo'],
                        ['campo' => 'trabajador',  'label' => 'Trabajador'],
                        ['campo' => 'estado',      'label' => 'Estado'],
                    ],
                    'montoLabel' => 'Monto',
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
                            'descripcion' => $p->tipo === 'amortizacion' ? 'Amortización' : 'Incremento',
                            'cuenta'      => $p->cuenta?->nombre ?? '—',
                            'observacion' => $p->observacion ?? '—',
                            'monto'       => (float) $p->monto,
                            'tipo'        => $p->tipo === 'amortizacion' ? 'ingreso' : 'egreso',
                            'user'        => $p->user?->name,
                        ])->values(),
                    ])->values();

                // Trazabilidad: el historial SIEMPRE arranca con el registro de
                // la deuda (el origen del saldo). original − amortizaciones
                // + incrementos = saldo actual, sin montos "de la nada".
                $fechaRegistro = ($deuda->fecha_inicio ?? $deuda->created_at)->format('Y-m-d');
                $grupos->push([
                    'id'      => 'registro',
                    'titulo'  => $fechaRegistro,
                    'esFecha' => true,
                    'monto'   => (float) $deuda->monto_original,
                    'tipo'    => 'neutro',
                    'items'   => [[
                        'descripcion' => 'Registro de la deuda (saldo inicial)',
                        'cuenta'      => '—',
                        'observacion' => $deuda->observacion ?? '—',
                        'monto'       => (float) $deuda->monto_original,
                        'tipo'        => $deuda->direccion === 'por_cobrar' ? 'ingreso' : 'egreso',
                        'user'        => $deuda->user?->name,
                    ]],
                ]);

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Deuda (registrada por ' . ($deuda->user?->name ?? '—') . ')', 'valor' => $deuda->nombre, 'esTexto' => true],
                        ['label' => 'Monto original', 'valor' => (float) $deuda->monto_original],
                        ['label' => 'Saldo actual', 'valor' => (float) $deuda->saldo,
                         'color' => $deuda->direccion === 'por_cobrar' ? 'success' : 'danger'],
                    ],
                    'itemCols' => [
                        ['campo' => 'descripcion', 'label' => 'Movimiento'],
                        ['campo' => 'cuenta',      'label' => 'Cuenta'],
                        ['campo' => 'observacion', 'label' => 'Observación'],
                    ],
                    'montoLabel' => 'Monto',
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
