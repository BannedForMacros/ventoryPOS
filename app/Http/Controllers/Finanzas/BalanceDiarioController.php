<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\BalanceCuentaArqueo;
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
use App\Models\Turno;
use App\Models\TurnoRetiro;
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
    /** Movimientos de una deuda: la compensación baja el saldo como una amortización. */
    private const ETIQUETA_MOV_DEUDA = [
        'amortizacion' => 'Amortización',
        'compensacion' => 'Compensación',
        'incremento'   => 'Incremento',
    ];

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
            'anticipo_cancelacion'          => 'Cancelaciones de pendiente de anticipo',
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
            'anticipo_cancelacion'          => 'Cancelaciones de pendiente de anticipo',
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
        // Mismas cuentas que la línea del balance (incluye desactivadas con saldo a la fecha).
        $saldosCuentas = $this->service->cuentasDelBalance($user->empresa_id, $fecha)
            ->map(fn ($c) => [
                'id'          => $c->id, // para abrir el detalle/auditoría al hacer clic
                'nombre'      => $c->nombre,
                'banco'       => $c->banco,
                'es_efectivo' => (bool) $c->es_efectivo,
                'saldo'       => $tesoreria->saldo($c->id, $fecha),
            ])->values();

        // ── Resumen por ENTIDAD (banco) — línea textual arriba de las cuentas.
        // Junta todas las cuentas de una misma entidad: Efectivo por un lado y
        // cada banco (BBVA, BCP…) sumando sus cuentas. Responde de un vistazo
        // "¿cuánto tengo en efectivo / en el BBVA / en el BCP?".
        $saldosEntidad = $saldosCuentas
            ->groupBy(fn ($c) => $c['es_efectivo'] ? 'Efectivo' : ($c['banco'] ?: $c['nombre']))
            ->map(fn ($grupo, $entidad) => [
                'entidad'     => (string) $entidad,
                'es_efectivo' => (bool) $grupo->first()['es_efectivo'],
                'saldo'       => round((float) $grupo->sum('saldo'), 2),
            ])
            ->sortByDesc('es_efectivo')
            ->values();

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
            'stock' => 'Stock (inventario)', 'mercaderia_transito' => 'Mercadería en tránsito', 'cxc' => 'Deudas por cobrar',
            'cxp' => 'Deudas por pagar', 'prestamo_otorgado' => 'Préstamos otorgados',
            'adelanto_proveedor' => 'Adelantos a proveedores', 'anticipo_cliente' => 'Anticipos de clientes',
            'planilla_descuento' => 'Descuentos de planilla', 'gastos_emitidos' => 'Gastos emitidos',
            'deuda' => 'Deudas/préstamos', 'personal' => 'Personal',
        ];

        // Cambios en el día anterior DESPUÉS de su cierre: separan la variación que
        // se ve hoy en "movimiento real del día" + "cambios en días ya cerrados".
        // Solo lectura; el día anterior no se toca.
        $cambiosDiaAnterior = null;
        if ($anterior) {
            $resumen = app(\App\Services\CambiosCierreService::class)->resumen($anterior);
            if ($resumen['verificable']) {
                $cambiosDiaAnterior = $resumen + ['umbral' => \App\Services\CambiosCierreService::UMBRAL];
            }
        }

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

        // ¿Se puede reabrir? Solo el ÚLTIMO balance confirmado (los siguientes
        // se encadenan sobre él) y solo por un admin.
        $esUltimoConfirmado = $balance->estado === 'confirmado'
            && !BalanceDiario::deEmpresa($user->empresa_id)->confirmado()
                ->where('fecha', '>', $balance->fecha->toDateString())->exists();

        // ── Alertas de calidad de datos (por qué el patrimonio podría verse mal)
        // 1) Productos con STOCK NEGATIVO al corte: casi siempre entradas
        //    registradas DESPUÉS de las ventas o compras sin ingresar. Restan
        //    valor fantasma al inventario y bajan el patrimonio sin ser pérdida.
        // 2) Kardex DESALINEADO del stock vivo: al editar/revertir entradas el
        //    kardex queda viejo y el balance lee un valor que ya no es real →
        //    hay que "Recalcular stock" para reconstruirlo.
        $stockNegativo = collect(DB::select(
            'WITH saldos AS (
                SELECT DISTINCT ON (mi.almacen_id, mi.producto_id) mi.producto_id, mi.saldo_cantidad
                FROM movimientos_inventario mi
                WHERE mi.empresa_id = ? AND mi.fecha <= ?
                ORDER BY mi.almacen_id, mi.producto_id, mi.fecha DESC, mi.id DESC
            )
            SELECT p.nombre, s.saldo_cantidad AS cantidad
            FROM saldos s JOIN productos p ON p.id = s.producto_id AND p.activo = true
            WHERE s.saldo_cantidad < 0
            ORDER BY s.saldo_cantidad ASC LIMIT 50',
            [$user->empresa_id, $balance->fecha->toDateString() . ' 23:59:59'],
        ))->map(fn ($r) => ['nombre' => $r->nombre, 'cantidad' => (float) $r->cantidad]);

        $kardexDesalineado = (int) (DB::selectOne(
            'WITH kardex AS (
                SELECT DISTINCT ON (mi.almacen_id, mi.producto_id) mi.almacen_id, mi.producto_id, mi.saldo_cantidad AS q
                FROM movimientos_inventario mi WHERE mi.empresa_id = ?
                ORDER BY mi.almacen_id, mi.producto_id, mi.fecha DESC, mi.id DESC
            )
            SELECT COUNT(*) AS n
            FROM kardex k JOIN productos p ON p.id = k.producto_id AND p.activo = true
            LEFT JOIN stock s ON s.producto_id = k.producto_id AND s.almacen_id = k.almacen_id
            WHERE ABS(COALESCE(s.cantidad, 0) - k.q) > 0.01',
            [$user->empresa_id],
        )?->n ?? 0);

        // ── Histórico del PATRIMONIO del dueño: últimos días confirmados +
        // este. Da el "¿cuánto tiene el dueño?" y su tendencia día a día.
        $patrimonioHistorial = BalanceDiario::deEmpresa($user->empresa_id)
            ->where(fn ($q) => $q->confirmado()->orWhere('id', $balance->id))
            ->where('fecha', '<=', $balance->fecha->toDateString())
            ->orderByDesc('fecha')->limit(21)->get(['fecha', 'balance_neto', 'utilidad_dia', 'estado'])
            ->sortBy('fecha')->values()
            ->map(fn ($b) => [
                'fecha'      => $b->fecha->toDateString(),
                'patrimonio' => (float) $b->balance_neto,
                'utilidad'   => $b->utilidad_dia !== null ? (float) $b->utilidad_dia : null,
            ]);

        // ── Arqueo de cuentas: conteo declarado guardado (si hay) ──────────
        // Se adjunta a cada cuenta: lo declarado, la diferencia y si ya se generó
        // el ajuste de caja. Sin arqueo guardado, van null (aún no contado).
        $arqueos = BalanceCuentaArqueo::where('balance_diario_id', $balance->id)
            ->get()->keyBy('cuenta_id');
        $saldosCuentas = $saldosCuentas->map(function ($c) use ($arqueos) {
            $a = $arqueos->get($c['id']);
            $c['declarado']  = $a ? (float) $a->monto_declarado : null;
            $c['diferencia'] = $a ? round((float) $a->monto_declarado - (float) $c['saldo'], 2) : null;
            $c['ajustado']   = $a ? (bool) $a->ajustado : false;
            return $c;
        });

        return Inertia::render('Finanzas/BalanceDiarioDetalle', [
            'balance'        => $balance,
            'gastos'         => $gastos,
            'salidasDia'     => $salidasDia,
            'movimientosDia' => $movimientosDia,
            'saldosCuentas'  => $saldosCuentas,
            'saldosEntidad'  => $saldosEntidad,
            'variaciones'    => $variaciones,
            'cambiosDiaAnterior'   => $cambiosDiaAnterior,
            'balanceAnteriorFecha' => $anterior?->fecha?->toDateString(),
            'esAdmin'            => (bool) $user->rol->es_admin,
            'puedeReabrir'       => $esUltimoConfirmado && (bool) $user->rol->es_admin,
            'alertaStock'        => [
                'negativos'          => $stockNegativo,
                'kardex_desalineado' => $kardexDesalineado,
            ],
            'patrimonioHistorial' => $patrimonioHistorial,
            // Comparativo con el día anterior (el "versus") para cada card.
            'comparativo'        => $anterior ? [
                'fecha'      => $anterior->fecha->toDateString(),
                'patrimonio' => (float) $anterior->balance_neto,
                'ventas'     => (float) $anterior->ventas_dia,
                'costo'      => (float) $anterior->costo_dia,
                'gastos'     => (float) $anterior->gastos_dia,
                'utilidad'   => $anterior->utilidad_dia !== null ? (float) $anterior->utilidad_dia : null,
            ] : null,
        ]);
    }

    /**
     * Guarda el ARQUEO de cuentas del día: por cada cuenta, cuánto declara el
     * usuario que tiene físicamente. Solo registra el conteo y la diferencia
     * (snapshot del saldo del sistema). NO mueve tesorería — generar el ajuste
     * es opcional y aparte (generarAjustesArqueo).
     */
    public function guardarArqueo(Request $request, string $fecha)
    {
        $user = $request->user();
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $data = $request->validate([
            'cuentas'                => ['required', 'array', 'min:1'],
            'cuentas.*.cuenta_id'    => ['required', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'cuentas.*.declarado'    => ['required', 'numeric'],
        ]);

        $balance   = $this->service->generar($user, $fecha); // asegura la fila del día
        $tesoreria = app(TesoreriaService::class);

        foreach ($data['cuentas'] as $c) {
            $sistema = round((float) $tesoreria->saldo((int) $c['cuenta_id'], $fecha), 2);
            $declarado = round((float) $c['declarado'], 2);
            BalanceCuentaArqueo::updateOrCreate(
                ['balance_diario_id' => $balance->id, 'cuenta_id' => (int) $c['cuenta_id']],
                [
                    'saldo_sistema'   => $sistema,
                    'monto_declarado' => $declarado,
                    'diferencia'      => round($declarado - $sistema, 2),
                    // Cambiar el conteo re-abre el ajuste (si difiere de nuevo).
                    'ajustado'        => false,
                ],
            );
        }

        \App\Services\AuditoriaService::log('balance.arqueo_guardado', $balance, [
            'fecha' => $fecha, 'cuentas' => count($data['cuentas']),
        ], $user);

        return back()->with('success', 'Conteo de cuentas guardado. Las diferencias quedaron registradas.');
    }

    /**
     * OPCIONAL: asienta la diferencia de cada cuenta arqueada como movimiento de
     * tesorería (ajuste), dejando el saldo real = lo declarado. Recién con esto
     * el balance de mañana arranca desde el monto contado. Si no se usa, el
     * conteo queda solo informativo y nada se mueve.
     */
    public function generarAjustesArqueo(Request $request, string $fecha)
    {
        $user = $request->user();
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $balance   = $this->service->generar($user, $fecha);
        $tesoreria = app(TesoreriaService::class);
        $arqueos   = BalanceCuentaArqueo::where('balance_diario_id', $balance->id)->where('ajustado', false)->get();

        $n = 0;
        DB::transaction(function () use ($arqueos, $tesoreria, $user, $fecha, &$n) {
            foreach ($arqueos as $a) {
                $sistema = round((float) $tesoreria->saldo($a->cuenta_id, $fecha), 2);
                $dif = round((float) $a->monto_declarado - $sistema, 2);
                if (abs($dif) < 0.01) { $a->update(['ajustado' => true, 'saldo_sistema' => $sistema, 'diferencia' => 0]); continue; }

                $mov = $tesoreria->registrar(
                    $user->empresa_id, $a->cuenta_id, $user, $fecha,
                    $dif > 0 ? 'ingreso' : 'egreso', abs($dif),
                    'Ajuste por arqueo del balance ' . $fecha,
                    'ajuste', null,
                );
                $a->update(['ajustado' => true, 'saldo_sistema' => $sistema, 'diferencia' => $dif, 'ajuste_mov_id' => $mov->id]);
                $n++;
            }
        });

        \App\Services\AuditoriaService::log('balance.arqueo_ajustado', $balance, ['fecha' => $fecha, 'ajustes' => $n], $user);

        return back()->with('success', $n > 0
            ? "Se generaron {$n} ajuste(s) de caja: el saldo real quedó igual a lo declarado."
            : 'No hay diferencias por ajustar (todo cuadra).');
    }

    /**
     * Reabre (des-confirma) un balance para regenerarlo — p. ej. después de
     * registrar una venta olvidada con fecha de ese día en un turno reabierto.
     *
     * Solo el ÚLTIMO balance confirmado puede reabrirse: los balances
     * posteriores usan su neto como "BALANCE AYER" y se descuadrarían. Al
     * volver a borrador, show() regenera las líneas automáticas con los datos
     * actuales (las manuales se preservan) y el admin lo confirma de nuevo.
     */
    public function reabrir(Request $request, BalanceDiario $balance)
    {
        $user = $request->user();
        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($user->rol->es_admin, 403, 'Solo un administrador puede reabrir un balance.');
        abort_unless($balance->estado === 'confirmado', 422, 'El balance no está confirmado.');

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $haySiguiente = BalanceDiario::deEmpresa($user->empresa_id)->confirmado()
            ->where('fecha', '>', $balance->fecha->toDateString())
            ->exists();
        if ($haySiguiente) {
            return back()->withErrors([
                'balance' => 'Solo se puede reabrir el último balance confirmado: los días siguientes ya se encadenaron sobre este. Reabre primero (de atrás hacia adelante) los balances posteriores.',
            ]);
        }

        $snapshot = [
            'total_favor'   => (float) $balance->total_favor,
            'total_contra'  => (float) $balance->total_contra,
            'balance_neto'  => (float) $balance->balance_neto,
            'utilidad_real' => $balance->utilidad_real !== null ? (float) $balance->utilidad_real : null,
        ];

        $balance->update(['estado' => 'borrador']);

        \App\Services\AuditoriaService::log('balance.reabierto', $balance, [
            'fecha'            => $balance->fecha->toDateString(),
            'motivo'           => $data['motivo'],
            'totales_previos'  => $snapshot,
        ], $user);

        return redirect()->route('finanzas.balance.show', $balance->fecha->toDateString())
            ->with('success', 'Balance reabierto y regenerado con los datos actuales. Revísalo y confírmalo de nuevo.');
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
        // Costo efectivo del producto: precio_costo, y si está en 0 el
        // costo_promedio real (mismo criterio que valoriza el balance). Así una
        // venta de un producto sin costo registrado no aparece en 0.
        $costo = "COALESCE(NULLIF(p.precio_costo, 0), (SELECT s2.costo_promedio FROM stock s2 WHERE s2.producto_id = p.id AND s2.costo_promedio > 0 LIMIT 1), 0)";

        // Ventas (−)
        $ventas = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->whereDate('v.fecha_venta', $fecha)
            ->selectRaw("'Ventas' as tipo, v.numero as documento, p.nombre as producto, u.name as usuario,
                         (-1 * vi.cantidad_base) as cantidad, (-1 * vi.cantidad_base * {$costo}) as valor")
            ->get();

        // Salidas (−)
        $salidas = DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->join('productos as p', 'p.id', '=', 'sd.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.empresa_id', $empresaId)->where('s.estado', 'confirmado')
            ->whereDate('s.fecha', $fecha)
            ->selectRaw("'Salidas de inventario' as tipo, s.numero_documento as documento, p.nombre as producto, u.name as usuario,
                         (-1 * sd.cantidad_base) as cantidad, (-1 * sd.cantidad_base * {$costo}) as valor")
            ->get();

        // Entradas / compras (+)
        $entradas = DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->join('productos as p', 'p.id', '=', 'ed.producto_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.empresa_id', $empresaId)->where('e.estado', 'confirmado')
            ->whereDate('e.fecha', $fecha)
            ->selectRaw("'Entradas (compras)' as tipo, e.numero_documento as documento, p.nombre as producto, u.name as usuario,
                         ed.cantidad_base as cantidad, (ed.cantidad_base * {$costo}) as valor")
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
                         dd.cantidad_base as cantidad, (dd.cantidad_base * {$costo}) as valor")
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
                         ci.diferencia as cantidad, (ci.diferencia * {$costo}) as valor")
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

    /**
     * Recalcular STOCK del balance: reconstruye el kardex y el stock de la
     * empresa. Corrige el valor de inventario cuando quedó viejo por entradas
     * registradas TARDE o editadas (stock negativo / kardex desalineado). Un
     * clic desde la alerta del balance, sin ir a Inventario.
     */
    public function recalcularStock(Request $request, string $fecha)
    {
        $user = $request->user();
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);
        abort_unless((bool) $user->rol->es_admin, 403);

        // Motor único (stock + kardex del mismo cálculo); solo escribe lo que difiere.
        $almacenIds = DB::table('almacenes')->where('empresa_id', $user->empresa_id)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        app(\App\Services\KardexService::class)->reconstruirAlmacenes($almacenIds);

        return redirect()->route('finanzas.balance.show', ['fecha' => $fecha])
            ->with('success', 'Kardex y stock reconstruidos. El inventario del balance quedó al día.');
    }

    public function detalleItem(Request $request, string $fecha, string $categoria)
    {
        $user = $request->user();
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $empresaId = $user->empresa_id;
        $refId     = $request->integer('ref_id') ?: null;

        // Fila de VARIACIÓN del día (panel "qué subió / qué bajó"): auditoría de la
        // línea completa entre el día anterior confirmado y esta fecha, entidad por
        // entidad y con los documentos que la movieron.
        if ($request->boolean('variacion')) {
            return $this->detalleVariacion($empresaId, $fecha, $categoria);
        }

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
                // Por defecto SOLO el mes del balance (el negocio cuadra por
                // mes); para ver días de meses anteriores se usan los filtros
                // Desde/Hasta del modal.
                $desde = $request->input('desde', date('Y-m-01', strtotime($hasta)));

                // AUDITORÍA: la línea de cuenta muestra TODO lo que entró Y salió
                // (antes solo ingresos): así se explica por qué el monto cambió
                // vs ayer sin saltar entre dos paneles. Gastos emitidos sigue
                // siendo solo egresos (es la contra-línea).
                // Cuentas de esta línea. F11: efectivo/banco son POR ENTIDAD
                // (Efectivo, BCP, BBVA…): se juntan TODAS las cuentas de la
                // entidad (BCP Soles + Yape = BCP). Compat: si llega ref_id
                // (balances viejos por cuenta) se respeta esa cuenta.
                $entidad   = $request->input('entidad');
                $cuentaSel = $request->integer('cuenta_id') ?: null; // sub-cuenta (tab)
                $userSel   = $request->integer('user_id') ?: null;   // filtrar por cajero

                // Sub-cuentas de la entidad (para los TABS del detalle: una
                // entidad BCP puede tener BCP Soles + Yape + BCP Dólares).
                $subcuentas = collect();
                if ($categoria === 'efectivo') {
                    $subcuentas = Cuenta::deEmpresa($empresaId)->where('es_efectivo', true)
                        ->orderBy('nombre')->get(['id', 'nombre']);
                } elseif ($categoria === 'cuenta_bancaria') {
                    $subcuentas = Cuenta::deEmpresa($empresaId)->where('es_efectivo', false)
                        ->when($entidad, fn ($q) => $q->where('banco', $entidad))
                        ->orderBy('nombre')->get(['id', 'nombre']);
                }

                $cuentaIds = null; // null = todas
                if ($cuentaSel) {
                    // Tab de una sub-cuenta específica.
                    $cuentaIds = collect([$cuentaSel]);
                } elseif ($esEgreso && $refId) {
                    // Gastos emitidos desglosados por cuenta (balances viejos).
                    $cuentaIds = collect([$refId]);
                } elseif ($categoria === 'efectivo' || $categoria === 'cuenta_bancaria') {
                    $cuentaIds = $subcuentas->isNotEmpty()
                        ? $subcuentas->pluck('id')
                        : ($refId ? collect([$refId]) : null);
                }

                $q = CuentaMovimiento::deEmpresa($empresaId)
                    ->whereBetween('fecha', [$desde, $hasta])
                    ->when($esEgreso, fn ($qq) => $qq->where('tipo', 'egreso'))
                    ->when($cuentaIds !== null, fn ($qq) => $qq->whereIn('cuenta_id', $cuentaIds))
                    ->when($userSel, fn ($qq) => $qq->where('user_id', $userSel))
                    ->with(['user:id,name', 'cuenta:id,nombre']);

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

                // Rastro de auditoría por movimiento: cuándo se REGISTRÓ de
                // verdad (created_at) y cuándo se EDITÓ (updated_at). Un ⚠
                // marca los retrofechados (creados DESPUÉS del día que dicen):
                // son los que cambian montos de días ya cuadrados.
                $fmtHora = fn ($ts) => $ts ? date('d/m H:i', strtotime((string) $ts)) : '—';

                $grupos = $movs->groupBy(fn ($m) => $fmtDia((string) $m->fecha))
                    ->map(function ($rows, $f) use ($esEgreso, $origenes, $fmtHora) {
                        $neto = round((float) $rows->sum(fn ($m) => $m->tipo === 'ingreso' ? (float) $m->monto : -(float) $m->monto), 2);
                        return [
                            'id'       => $f,
                            'titulo'   => $f,
                            'esFecha'  => true,
                            'monto'    => $esEgreso ? round((float) $rows->sum('monto'), 2) : abs($neto),
                            'tipo'     => $esEgreso ? 'egreso' : ($neto >= 0 ? 'ingreso' : 'egreso'),
                            'items'    => $rows->map(function ($m) use ($origenes, $fmtHora) {
                                $retro  = substr((string) $m->created_at, 0, 10) > substr((string) $m->fecha, 0, 10);
                                $edito  = $m->updated_at && $m->created_at
                                    && $m->updated_at->gt($m->created_at->copy()->addSeconds(2));
                                return [
                                    'descripcion' => $m->descripcion,
                                    'origen'      => $origenes[$m->ref_tipo] ?? ($m->ref_tipo ?? '—'),
                                    'cuenta'      => $m->cuenta?->nombre ?? '—',
                                    'registrado'  => $fmtHora($m->created_at) . ($retro ? ' ⚠ retrofechado' : ''),
                                    'editado'     => $edito ? $fmtHora($m->updated_at) : '—',
                                    'monto'       => (float) $m->monto,
                                    'tipo'        => $m->tipo,
                                    'user'        => $m->user?->name,
                                ];
                            })->values(),
                        ];
                    })->values();

                $ingresos = round((float) $movs->where('tipo', 'ingreso')->sum('monto'), 2);
                $egresos  = round((float) $movs->where('tipo', 'egreso')->sum('monto'), 2);

                $itemCols = [
                    ['campo' => 'descripcion', 'label' => 'Descripción'],
                    ['campo' => 'origen',      'label' => 'Origen'],
                ];
                if ($esEgreso) {
                    $itemCols[] = ['campo' => 'cuenta', 'label' => 'Desde cuenta'];
                }
                $itemCols[] = ['campo' => 'registrado', 'label' => 'Registrado'];
                $itemCols[] = ['campo' => 'editado',    'label' => 'Editado'];

                $cards = $esEgreso
                    ? [
                        ['label' => 'Total salidas (período)', 'valor' => $egresos, 'color' => 'danger'],
                        ['label' => 'Días con movimiento', 'valor' => $grupos->count(), 'esNumero' => true],
                        ['label' => 'Operaciones', 'valor' => $movs->count(), 'esNumero' => true],
                    ]
                    : [
                        ['label' => 'Entró (período)', 'valor' => $ingresos, 'color' => 'success'],
                        ['label' => 'Salió (período)', 'valor' => $egresos, 'color' => 'danger'],
                        ['label' => 'Neto (entró − salió)', 'valor' => round($ingresos - $egresos, 2),
                         'color' => $ingresos - $egresos >= 0 ? 'success' : 'danger'],
                        ['label' => 'Operaciones', 'valor' => $movs->count(), 'esNumero' => true],
                    ];
                // Línea desglosada por cuenta: decir de cuál es este detalle.
                if ($esEgreso && $refId) {
                    $nombreCuenta = Cuenta::where('id', $refId)->value('nombre');
                    if ($nombreCuenta) {
                        array_unshift($cards, ['label' => 'Salidas de la cuenta', 'valor' => $nombreCuenta, 'esTexto' => true]);
                    }
                }

                // Desglose del detalle:
                //  • EFECTIVO → NETO POR TURNO del día: lo que quedó en efectivo en
                //    la caja de cada turno (cada cajera). Es la reconciliación de
                //    caja que pide el cliente. Usa el monto esperado del cierre
                //    (o el calculado si el turno sigue abierto) y, si ya cerró,
                //    también lo declarado (contado) y la diferencia.
                //  • BANCOS → NETO POR CAJERO acumulado a la fecha (quién movió el
                //    dinero de esa entidad); la suma cuadra con el card.
                $porCajero  = [];
                $porTurno   = [];
                $cajaGrande = null;
                $entregasAdmin = null;
                if (!$esEgreso && $categoria === 'efectivo') {
                    $porTurno = \App\Models\Turno::deEmpresa($empresaId)
                        ->whereDate('fecha_apertura', $fecha)
                        ->with(['user:id,name', 'caja:id,nombre'])
                        ->orderBy('fecha_apertura')->get()
                        ->map(function ($t) {
                            $esperado = ($t->estado === 'cerrado' && $t->monto_cierre_esperado !== null)
                                ? (float) $t->monto_cierre_esperado
                                : $t->calcularMontoEsperado();
                            return [
                                'turno_id'   => $t->id,
                                'user_id'    => $t->user_id,
                                'cajera'     => $t->user?->name ?? '—',
                                'caja'       => $t->caja?->nombre ?? 'Caja',
                                'estado'     => $t->estado,
                                'esperado'   => round($esperado, 2),
                                'declarado'  => $t->monto_cierre_declarado !== null ? (float) $t->monto_cierre_declarado : null,
                                'diferencia' => $t->diferencia !== null ? (float) $t->diferencia : null,
                            ];
                        })->values();

                    // ¿DÓNDE está el efectivo? (opt-in: empresas.usa_caja_grande)
                    // Custodia física a la fecha: lo que hay en cada cajón
                    // (turno abierto → esperado en vivo; caja sin turno → lo
                    // que QUEDÓ al último cierre, efectivo_arrastre) y el
                    // resto = "Caja Grande" (administración). La suma cuadra
                    // con el saldo de las cuentas Efectivo a la fecha.
                    $empresa = \App\Models\Empresa::find($empresaId);
                    if ($empresa?->usa_caja_grande) {
                        $finDia   = $fecha . ' 23:59:59';
                        $tesoreria = app(TesoreriaService::class);
                        $saldoEfectivo = round((float) $subcuentas->sum(
                            fn ($c) => $tesoreria->saldo($c->id, $fecha)
                        ), 2);

                        $enCajas = \App\Models\Caja::where('empresa_id', $empresaId)
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->get(['id', 'nombre'])
                            ->map(function ($caja) use ($finDia) {
                                $abierto = \App\Models\Turno::where('caja_id', $caja->id)
                                    ->where('estado', 'abierto')
                                    ->where('fecha_apertura', '<=', $finDia)
                                    ->orderByDesc('fecha_apertura')
                                    ->first();
                                if ($abierto) {
                                    return [
                                        'caja'   => $caja->nombre,
                                        'estado' => 'abierto',
                                        'monto'  => round($abierto->calcularMontoEsperado(), 2),
                                    ];
                                }
                                $cerrado = \App\Models\Turno::where('caja_id', $caja->id)
                                    ->where('estado', 'cerrado')
                                    ->where('fecha_cierre', '<=', $finDia)
                                    ->orderByDesc('fecha_cierre')
                                    ->first();
                                return [
                                    'caja'   => $caja->nombre,
                                    'estado' => 'cerrado',
                                    // Cierres anteriores a esta función no registraron
                                    // qué quedó en el cajón (NULL) → 0 es lo honesto.
                                    'monto'  => round((float) ($cerrado?->efectivo_arrastre ?? 0), 2),
                                ];
                            })->values();

                        $totalEnCajas = round((float) $enCajas->sum('monto'), 2);
                        $montoAdmin   = round($saldoEfectivo - $totalEnCajas, 2);
                        $cajaGrande = [
                            'en_cajas'       => $enCajas,
                            'total_en_cajas' => $totalEnCajas,
                            'caja_grande'    => $montoAdmin,
                            'saldo_efectivo' => $saldoEfectivo,
                            'negativo'       => $montoAdmin < 0,
                        ];
                    }

                    // Caja Grande (clic en su fila): movimientos que la alimentan.
                    // Como no es una cuenta, su "libro" se arma con los traslados
                    // internos de custodia (entregas a administración, tabla
                    // turno_retiros) en el período filtrado; los ingresos/egresos
                    // reales del Efectivo siguen abajo en la lista del detalle.
                    if ($request->boolean('caja_grande') && $empresa?->usa_caja_grande) {
                        $retiros = TurnoRetiro::where('empresa_id', $empresaId)
                            ->where('concepto', TurnoRetiro::CONCEPTO_ENTREGA_ADMIN)
                            ->whereBetween('created_at', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
                            ->with(['turno.caja:id,nombre', 'turno.user:id,name', 'user:id,name'])
                            ->orderByDesc('created_at')
                            ->get();
                        $entregasAdmin = [
                            'total' => round((float) $retiros->sum('monto'), 2),
                            'items' => $retiros->map(fn ($r) => [
                                'fecha'   => date('d/m/Y', strtotime((string) $r->created_at)),
                                'turno'   => (int) $r->turno_id,
                                'caja'    => $r->turno?->caja?->nombre ?? '—',
                                'cajera'  => $r->turno?->user?->name ?? $r->user?->name ?? '—',
                                'momento' => $r->momento === 'cierre' ? 'Al cierre del turno' : 'Durante el turno',
                                'monto'   => round((float) $r->monto, 2),
                                'registro'=> $r->user?->name ?? '—',
                            ])->values(),
                        ];
                    }
                } elseif (!$esEgreso) {
                    // Bancos: neto por cajero (columnas calificadas porque users
                    // también tiene empresa_id → deEmpresa sería ambiguo).
                    $netoSql = "SUM(CASE WHEN m.tipo = 'ingreso' THEN m.monto ELSE -m.monto END)";
                    $porCajero = DB::table('cuenta_movimientos as m')
                        ->where('m.empresa_id', $empresaId)
                        ->whereDate('m.fecha', '<=', $fecha)
                        ->when($cuentaIds !== null, fn ($qq) => $qq->whereIn('m.cuenta_id', $cuentaIds))
                        ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                        ->groupBy('u.id', 'u.name')
                        ->selectRaw("u.id as user_id, COALESCE(u.name, '—') as usuario, {$netoSql} as total, COUNT(*) as ops")
                        ->havingRaw("ROUND({$netoSql}, 2) <> 0")
                        ->orderByRaw("{$netoSql} DESC")
                        ->get()
                        ->map(fn ($r) => ['user_id' => $r->user_id, 'usuario' => $r->usuario, 'total' => round((float) $r->total, 2), 'ops' => (int) $r->ops])
                        ->values();
                }

                return response()->json([
                    'tipo'   => 'grupos',
                    'desde'  => $desde,
                    'hasta'  => $hasta,
                    'cards'  => $cards,
                    'itemCols'   => $itemCols,
                    'montoLabel' => 'Monto',
                    'grupos'     => $grupos,
                    // Jerarquía del detalle: tabs por sub-cuenta de la entidad +
                    // desglose "generado por cajero" (filtrable por usuario).
                    'subcuentas' => $subcuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values(),
                    'cuentaSel'  => $cuentaSel,
                    'porCajero'  => $porCajero,
                    'porTurno'   => $porTurno,
                    'cajaGrande' => $cajaGrande,
                    'verCajaGrande'   => (bool) $request->boolean('caja_grande'),
                    'entregasAdmin'   => $entregasAdmin,
                    'userSel'    => $userSel,
                ]);
            }

            // ── Stock A LA FECHA del balance: producto por producto ─────
            // Sale del MISMO desglose que la línea (BalanceDiarioService::
            // desgloseStock): saldo del kardex por almacén sin negativos × costo
            // conocido a la fecha. Así el modal suma exactamente la línea.
            case 'stock': {
                $desglose = $this->service->desgloseStock($empresaId, $fecha);
                $filas = collect($desglose)->sortByDesc('monto');

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => 'Valor del inventario al ' . $fecha, 'valor' => round((float) $filas->sum('monto'), 2), 'color' => 'success'],
                        ['label' => 'Productos con stock', 'valor' => $filas->filter(fn ($f) => $f['monto'] > 0.005)->count(), 'esNumero' => true],
                    ],
                    'grupos' => $filas->map(fn ($f, $clave) => [
                        'id'        => (string) $clave,
                        'titulo'    => $f['descripcion'],
                        'subtitulo' => $f['detalle'] ? $f['detalle'] . ' (costo del día)' : null,
                        'monto'     => round((float) $f['monto'], 2),
                        'items'     => [],
                    ])->values(),
                ]);
            }

            // ── Mercadería en tránsito al corte: compra por compra ──────
            case 'mercaderia_transito': {
                $filas = collect($this->service->desgloseTransito($empresaId, $fecha));

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => "En tránsito al {$fecha}", 'valor' => round((float) $filas->sum('monto'), 2), 'color' => 'success'],
                        ['label' => 'Compras por llegar', 'valor' => $filas->count(), 'esNumero' => true],
                    ],
                    'grupos' => $filas->map(fn ($f, $clave) => [
                        'id'        => (string) $clave,
                        'titulo'    => $f['descripcion'],
                        'subtitulo' => $f['detalle'],
                        'monto'     => round((float) $f['monto'], 2),
                        'items'     => [],
                    ])->values(),
                ]);
            }

            // ── Stock: MOVIMIENTOS del día + reconciliación de la variación ──
            // Explica por qué el valor del inventario cambió vs el día anterior:
            // parte por movimientos (ventas/salidas/entradas) y parte por cambio
            // de costos (revaluación), que NO se ve en los movimientos.
            case 'stock_mov': {
                // Valor del inventario A LA FECHA del balance, el mismo de su
                // línea (no el stock vivo de hoy al costo actual).
                $valorHoy = round(array_sum(array_column($this->service->desgloseStock($empresaId, $fecha), 'monto')), 2);

                // Valor de stock del último balance confirmado anterior.
                $balAnt = DB::table('balances_diarios as b')
                    ->join('balance_diario_items as i', 'i.balance_diario_id', '=', 'b.id')
                    ->where('b.empresa_id', $empresaId)->where('b.estado', 'confirmado')
                    ->where('b.fecha', '<', $fecha)->where('i.categoria', 'stock')
                    ->orderByDesc('b.fecha')
                    ->selectRaw('b.fecha, i.monto')->first();
                $valorAyer  = $balAnt ? (float) $balAnt->monto : null;
                $variacion  = $valorAyer !== null ? round($valorHoy - $valorAyer, 2) : null;

                $movs      = $this->movimientosStockDia($empresaId, $fecha);
                $movTotal  = round((float) $movs->sum('valor'), 2);
                $revaluado = $variacion !== null ? round($variacion - $movTotal, 2) : null;

                $fmtCant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

                // La DIRECCIÓN (color/signo) se basa en la CANTIDAD, no en el valor:
                // un producto sin costo registrado igual es una salida (no "+0").
                $grupos = $movs->groupBy('tipo')->map(fn ($rows, $tipo) => [
                    'id'     => $tipo,
                    'titulo' => $tipo,
                    'monto'  => round((float) $rows->sum('valor'), 2),
                    'tipo'   => $rows->sum('cantidad') >= 0 ? 'ingreso' : 'egreso',
                    'items'  => $rows->map(fn ($m) => [
                        'producto'  => $m->producto,
                        'documento' => $m->documento ?? '—',
                        'cantidad'  => ($m->cantidad >= 0 ? '+' : '') . $fmtCant($m->cantidad),
                        'monto'     => round((float) $m->valor, 2),
                        'tipo'      => $m->cantidad >= 0 ? 'ingreso' : 'egreso',
                        'user'      => $m->usuario,
                    ])->values(),
                ])->sortByDesc(fn ($g) => abs($g['monto']))->values();

                $cards = [];
                if ($variacion !== null) {
                    $cards[] = ['label' => 'Variación del inventario (vs ' . $balAnt->fecha . ')', 'valor' => $variacion, 'color' => $variacion >= 0 ? 'success' : 'danger'];
                }
                $cards[] = ['label' => 'Explicado por movimientos del día', 'valor' => $movTotal, 'color' => $movTotal >= 0 ? 'success' : 'danger'];
                if ($revaluado !== null && abs($revaluado) >= 0.01) {
                    $cards[] = ['label' => 'Por cambio de costos / ajustes', 'valor' => $revaluado, 'color' => $revaluado >= 0 ? 'success' : 'danger'];
                }
                $cards[] = ['label' => 'Valor del inventario al ' . $fecha, 'valor' => round($valorHoy, 2), 'color' => 'success'];

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => $cards,
                    'itemCols' => [
                        ['campo' => 'producto',  'label' => 'Producto'],
                        ['campo' => 'documento', 'label' => 'Documento'],
                        ['campo' => 'cantidad',  'label' => 'Cant.'],
                    ],
                    'montoLabel' => 'Valor',
                    'grupos'     => $grupos,
                ]);
            }

            // ── Deudas por cobrar A LA FECHA del balance ─────────────────
            // Ventas a crédito nacidas hasta la fecha, con el saldo QUE TENÍAN
            // ese día (abonos posteriores se devuelven; ventas posteriores no
            // aparecen). El historial solo muestra pagos hasta la fecha.
            case 'cxc': {
                // Las ventas y su saldo al corte salen del MISMO desglose que la
                // línea (sin límite ni filtros propios): el modal suma la línea.
                $saldos = $this->montosDesglose($empresaId, $fecha, 'cxc', 'v');
                $ventas = Venta::deEmpresa($empresaId)->whereIn('id', array_keys($saldos))
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'user:id,name',
                            'pagos.metodoPago:id,nombre',
                            'abonos.metodoPago:id,nombre', 'abonos.cuenta:id,nombre', 'abonos.user:id,name'])
                    ->orderByDesc('fecha_venta')
                    ->get()
                    ->each(fn ($v) => $v->setAttribute('saldo_corte', $saldos[$v->id]));

                $grupos = $ventas->groupBy(fn ($v) => $v->fecha_venta->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('saldo_corte'), 2),
                        'tipo'    => 'neutro',
                        'items'   => $rows->map(fn ($v) => [
                            'descripcion' => "{$v->numero} — " . $nombreCliente($v->cliente),
                            'sub'         => 'Vendida el ' . $v->fecha_venta->format('d/m/Y h:i a'),
                            'total'       => 'S/ ' . number_format((float) $v->total, 2),
                            'pagado'      => 'S/ ' . number_format(max(0, (float) $v->total - (float) $v->saldo_corte), 2),
                            'vence'       => $v->fecha_vencimiento?->format('d/m/Y') ?? '—',
                            'monto'       => (float) $v->saldo_corte,
                            'user'        => $v->user?->name,
                            // Trazabilidad: pago inicial del POS + abonos HASTA la fecha.
                            'historial'   => $v->pagos
                                ->filter(fn ($p) => (float) $p->monto - (float) $p->vuelto > 0)
                                ->map(fn ($p) => [
                                    'fecha'       => $v->fecha_venta->format('d/m/Y'),
                                    'descripcion' => 'Pago inicial (en la venta)'
                                        . ($p->metodoPago ? " · {$p->metodoPago->nombre}" : ''),
                                    'monto'       => round((float) $p->monto - (float) $p->vuelto, 2),
                                    'user'        => $v->user?->name,
                                ])
                                ->concat($v->abonos->filter(fn ($a) => $a->fecha->toDateString() <= $fecha)
                                    ->sortBy('fecha')->values()->map(fn ($a) => [
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
                        ['label' => "Por cobrar al {$fecha}", 'valor' => round((float) $ventas->sum('saldo_corte'), 2), 'color' => 'danger'],
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

            // ── Proveedores por pagar A LA FECHA del balance ─────────────
            // Compras hasta la fecha con el saldo que tenían ese día (pagos
            // posteriores se devuelven; compras posteriores no aparecen).
            case 'cxp': {
                // Mismas compras y saldos que la línea (desgloseCxp), sin límite.
                $saldos = $this->montosDesglose($empresaId, $fecha, 'cxp', 'e');
                $entradas = Entrada::deEmpresa($empresaId)->whereIn('id', array_keys($saldos))
                    ->with(['proveedorRel:id,razon_social,nombre_comercial', 'user:id,name',
                            'pagosParciales.metodoPago:id,nombre', 'pagosParciales.cuenta:id,nombre', 'pagosParciales.user:id,name'])
                    ->orderByDesc('fecha')
                    ->get()
                    ->each(fn ($e) => $e->setAttribute('saldo_corte', $saldos[$e->id]));

                $grupos = $entradas->groupBy(fn ($e) => $e->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('saldo_corte'), 2),
                        'tipo'    => 'egreso',
                        'items'   => $rows->map(fn ($e) => [
                            'descripcion' => $e->proveedorRel?->razon_social ?? $e->proveedorRel?->nombre_comercial ?? $e->proveedor ?? 'Proveedor',
                            'sub'         => 'Compra del ' . $e->fecha->format('d/m/Y')
                                . ($e->numero_documento ? " · Doc. {$e->numero_documento}" : ''),
                            'total'       => 'S/ ' . number_format((float) $e->total, 2),
                            'pagado'      => 'S/ ' . number_format(max(0, (float) $e->total - (float) $e->saldo_corte), 2),
                            'monto'       => (float) $e->saldo_corte,
                            'user'        => $e->user?->name,
                            // Trazabilidad: pagos realizados HASTA la fecha del balance.
                            'historial'   => $e->pagosParciales->filter(fn ($p) => $p->fecha->toDateString() <= $fecha)
                                ->sortBy('fecha')->values()->map(fn ($p) => [
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
                        ['label' => "Por pagar al {$fecha}", 'valor' => round((float) $entradas->sum('saldo_corte'), 2), 'color' => 'danger'],
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

            // ── Anticipos de clientes A LA FECHA del balance ─────────────
            // Nacidos hasta la fecha, con el saldo/pendiente que tenían ese
            // día (entregas posteriores se devuelven; anticipos posteriores
            // no aparecen).
            case 'anticipo_cliente': {
                // Mismos anticipos y montos que la línea (desgloseAnticipos): los
                // devueltos hasta su devolución, entregas y cancelaciones
                // posteriores devueltas, material al precio congelado. Sin límite.
                $valores = $this->montosDesglose($empresaId, $fecha, 'anticipo_cliente', 'a');
                $cancPost = $this->service->cancelacionesPosteriores($empresaId, $fecha);

                $anticipos = ClienteAnticipo::deEmpresa($empresaId)->whereIn('id', array_keys($valores))
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'producto:id,nombre,precio_venta', 'user:id,name',
                            'items', 'venta:id,numero',
                            'aplicaciones.user:id,name', 'aplicaciones.items'])
                    ->orderByDesc('fecha')
                    ->get()
                    ->each(function (ClienteAnticipo $a) use ($fecha, $valores, $cancPost) {
                        // Entregas y cancelaciones POSTERIORES al corte → vuelven al pendiente.
                        $post = $a->aplicaciones->filter(fn ($ap) => $ap->fecha->toDateString() > $fecha);
                        $porItem = $post->flatMap(fn ($ap) => $ap->items)
                            ->groupBy('cliente_anticipo_item_id')->map(fn ($g) => (float) $g->sum('cantidad'));
                        foreach ($cancPost->where('cliente_anticipo_id', $a->id) as $c) {
                            $porItem[$c->cliente_anticipo_item_id] = ($porItem[$c->cliente_anticipo_item_id] ?? 0) + (float) $c->cantidad;
                        }
                        $a->setAttribute('post_por_item', $porItem);
                        if ($a->items->isEmpty() && $a->tipo_valorizacion === 'material' && $a->cantidad_pendiente !== null) {
                            $a->setAttribute('cant_corte', (float) $a->cantidad_pendiente + (float) $post->sum(fn ($ap) => (float) ($ap->cantidad ?? 0)));
                        }
                        $a->setAttribute('valor_corte', $valores[$a->id]);
                    });

                // Modalidad legible AL CORTE: multi-producto (pendiente del POS)
                // lista sus ítems pendientes; material clásico su producto.
                $fmtCant   = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
                $modalidad = function (ClienteAnticipo $a) use ($fmtCant) {
                    if ($a->items->isNotEmpty()) {
                        $postItem = $a->post_por_item;
                        $lista = $a->items
                            ->map(fn ($i) => ['n' => $i->producto_nombre, 'c' => (float) $i->cantidad_pendiente + (float) ($postItem[$i->id] ?? 0)])
                            ->filter(fn ($x) => $x['c'] > 0.0001)
                            ->map(fn ($x) => $fmtCant($x['c']) . ' × ' . $x['n'])
                            ->implode(', ');
                        return 'Por entregar' . ($a->venta?->numero ? " (Venta {$a->venta->numero})" : '') . ': ' . ($lista ?: '—');
                    }
                    if ($a->tipo_valorizacion === 'material') {
                        $precioPagado = (float) $a->cantidad > 0 ? (float) $a->monto / (float) $a->cantidad : 0;
                        return "{$a->producto?->nombre} × " . (float) ($a->cant_corte ?? $a->cantidad_pendiente)
                            . ' a S/' . number_format($precioPagado, 2) . ' (precio pagado)';
                    }
                    return 'Dinero';
                };

                $grupos = $anticipos->groupBy(fn ($a) => $a->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('valor_corte'), 2),
                        'tipo'    => 'egreso',
                        'items'   => $rows->map(fn ($a) => [
                            'descripcion' => $nombreCliente($a->cliente),
                            'sub'         => 'Recibido el ' . $a->fecha->format('d/m/Y'),
                            'modalidad'   => $modalidad($a),
                            'recibido'    => 'S/ ' . number_format((float) $a->monto, 2),
                            'monto'       => (float) $a->valor_corte,
                            'user'        => $a->user?->name,
                            // Trazabilidad: entregas HASTA la fecha del balance.
                            'historial'   => $a->aplicaciones->filter(fn ($ap) => $ap->fecha->toDateString() <= $fecha)
                                ->sortBy('fecha')->values()->map(fn ($ap) => [
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
                        ['label' => "Pasivo al {$fecha}", 'valor' => round((float) $anticipos->sum('valor_corte'), 2), 'color' => 'danger'],
                        ['label' => 'Anticipos con pendiente', 'valor' => $anticipos->count(), 'esNumero' => true],
                    ],
                    'itemCols' => [
                        ['campo' => 'descripcion', 'label' => 'Cliente'],
                        ['campo' => 'modalidad',   'label' => 'Modalidad / Material'],
                        ['campo' => 'recibido',    'label' => 'Recibido'],
                    ],
                    'montoLabel' => 'Pasivo',
                    'grupos' => $grupos,
                ]);
            }

            // ── Adelanto a proveedor puntual: sus aplicaciones ──────────
            case 'adelanto_proveedor': {
                if (!$refId) {
                    return $this->detalleVariacion($empresaId, $fecha, $categoria);
                }

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

            // ── Descuentos de planilla PENDIENTES AL CORTE ──────────────
            // Los mismos de la línea (desglosePlanilla): registrados hasta la
            // fecha y aún sin aplicar ese día, sin importar su antigüedad ni su
            // estado de hoy (aplicado después del corte todavía suma).
            case 'planilla_descuento': {
                $montos = $this->montosDesglose($empresaId, $fecha, 'planilla_descuento', 'pd');
                $descuentos = \App\Models\PlanillaDescuento::deEmpresa($empresaId)
                    ->whereIn('id', array_keys($montos))
                    ->with(['trabajador:id,name', 'registradoPor:id,name'])
                    ->orderByDesc('fecha')->orderByDesc('id')
                    ->get();

                $grupos = $descuentos->groupBy(fn ($d) => $d->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum(fn ($d) => $montos[$d->id]), 2),
                        'tipo'    => 'neutro',
                        'items'   => $rows->map(fn ($d) => [
                            'descripcion' => $d->motivo,
                            'trabajador'  => $d->trabajador?->name ?? '—',
                            'estado'      => $d->estado === 'aplicado' && $d->fecha_aplicacion
                                ? 'Pendiente al ' . $fecha . ' (aplicado el ' . $d->fecha_aplicacion->format('d/m/Y') . ')'
                                : 'Pendiente',
                            'monto'       => $montos[$d->id],
                            'tipo'        => 'ingreso',
                            'user'        => $d->registradoPor?->name,
                        ])->values(),
                    ])->values();

                return response()->json([
                    'tipo'  => 'grupos',
                    'cards' => [
                        ['label' => "Pendiente de descontar al {$fecha}", 'valor' => round(array_sum($montos), 2), 'color' => 'success'],
                        ['label' => 'Descuentos pendientes', 'valor' => count($montos), 'esNumero' => true],
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
                // Fila de VARIACIÓN del día: agrupa TODA la categoría, no es una
                // deuda puntual (llega sin ref_id). Antes caía en el 404 y, tras
                // el fix de deudas eliminadas, mostraba un falso "deuda eliminada"
                // con montos en cero. Ahora muestra qué cambió contra ayer.
                if (!$refId) {
                    return $this->detalleVariacion($empresaId, $fecha, $categoria);
                }

                $deuda = $refId
                    ? Deuda::deEmpresa($empresaId)
                        ->with(['pagos.metodoPago:id,nombre', 'pagos.cuenta:id,nombre', 'pagos.user:id,name', 'user:id,name'])
                        ->find($refId)
                    : null;

                // La deuda ya no existe: se eliminó DESPUÉS de confirmar este
                // balance, que es un snapshot inmutable y conserva su línea.
                // Antes esto reventaba con 404 ("esta línea cambió, recargando")
                // y recargar no arreglaba nada. Ahora se reconstruye el detalle
                // desde la auditoría de eliminación (snapshot + motivo + quién).
                if (!$deuda) {
                    return $this->detalleDeudaEliminada($empresaId, $refId, $fecha, $categoria);
                }

                $grupos = $deuda->pagos->sortByDesc('fecha')->groupBy(fn ($p) => $p->fecha->format('Y-m-d'))
                    ->map(fn ($rows, $f) => [
                        'id'      => $f,
                        'titulo'  => $f,
                        'esFecha' => true,
                        'monto'   => round((float) $rows->sum('monto'), 2),
                        'tipo'    => 'neutro',
                        'items'   => $rows->map(fn ($p) => [
                            'descripcion' => self::ETIQUETA_MOV_DEUDA[$p->tipo] ?? 'Incremento',
                            'cuenta'      => $p->cuenta?->nombre ?? '—',
                            'observacion' => $p->observacion ?? '—',
                            'monto'       => (float) $p->monto,
                            'tipo'        => in_array($p->tipo, ['amortizacion', 'compensacion'], true) ? 'ingreso' : 'egreso',
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
     * Monto de cada entidad de una línea según el desglose del servicio (la
     * misma fuente que la línea), indexado por id: ['v12' => 30.5] → [12 => 30.5].
     *
     * @return array<int, float>
     */
    private function montosDesglose(int $empresaId, string $fecha, string $categoria, string $prefijo): array
    {
        $out = [];
        foreach ($this->service->desglose($empresaId, $fecha, $categoria) as $clave => $d) {
            if (str_starts_with((string) $clave, $prefijo)) {
                $out[(int) substr((string) $clave, strlen($prefijo))] = (float) $d['monto'];
            }
        }

        return $out;
    }

    /**
     * Auditoría de una línea del balance entre el día anterior CONFIRMADO y esta
     * fecha: cada entidad (producto, venta, compra, anticipo, cuenta, deuda…) con
     * su valor en ambos días y los documentos que la movieron. Las filas suman
     * exactamente la variación real de la línea.
     *
     * Si el día anterior cambió DESPUÉS de su cierre, se informa aparte: es la
     * diferencia entre lo que muestra la tarjeta (contra lo guardado al cerrar) y
     * el movimiento real del período.
     */
    private function detalleVariacion(int $empresaId, string $fecha, string $categoria)
    {
        $nombres = [
            'efectivo' => 'Efectivo', 'cuenta_bancaria' => 'Cuentas bancarias', 'stock' => 'Stock (inventario)',
            'stock_mov' => 'Stock (inventario)', 'mercaderia_transito' => 'Mercadería en tránsito',
            'cxc' => 'Deudas por cobrar', 'cxp' => 'Proveedores por pagar',
            'prestamo_otorgado' => 'Préstamos otorgados', 'adelanto_proveedor' => 'Adelantos a proveedores',
            'anticipo_cliente' => 'Anticipos de clientes', 'planilla_descuento' => 'Descuentos de planilla',
            'deuda' => 'Deudas y préstamos', 'personal' => 'Deudas con el personal',
        ];
        $categoria = $categoria === 'stock_mov' ? 'stock' : $categoria;
        $nombre = $nombres[$categoria] ?? $categoria;

        $anterior = BalanceDiario::deEmpresa($empresaId)->confirmado()
            ->where('fecha', '<', $fecha)->orderByDesc('fecha')->first();

        if (!$anterior) {
            return response()->json([
                'tipo'  => 'grupos',
                'aviso' => ['variant' => 'info', 'titulo' => "{$nombre}: sin día anterior para comparar",
                            'texto' => 'Todavía no hay un balance confirmado anterior a esta fecha.'],
                'cards' => [], 'grupos' => [],
            ]);
        }

        $desde = $anterior->fecha->toDateString();
        $r = app(\App\Services\AuditoriaLineaService::class)->comparar($empresaId, $categoria, $desde, $fecha);

        $fmt = fn (string $f) => \Illuminate\Support\Carbon::parse($f)->format('d/m');
        $money = fn (float $v) => 'S/ ' . number_format($v, 2);
        // En contra, subir es malo (más deuda); a favor, al revés.
        $tipo = fn (float $v) => abs($v) < 0.005 ? null : (($v > 0) === $r['en_contra'] ? 'egreso' : 'ingreso');

        $item = fn (array $f) => [
            'descripcion' => $f['descripcion'],
            'sub'         => $f['detalle'],
            'antesTxt'    => $money($f['antes']),
            'despuesTxt'  => $money($f['despues']),
            'monto'       => $f['variacion'],
            'tipo'        => $tipo($f['variacion']),
            'historial'   => array_map(fn ($e) => [
                'fecha'       => \Illuminate\Support\Carbon::parse($e['fecha'])->format('d/m H:i'),
                'descripcion' => $e['descripcion'],
                'monto'       => (float) ($e['monto'] ?? 0),
                'user'        => $e['user'],
            ], $f['eventos']),
        ];

        $filas = collect($r['filas']);
        $grupo = fn (string $id, string $titulo, $items) => $items->isEmpty() ? null : [
            'id' => $id, 'titulo' => $titulo, 'esFecha' => false, 'tipo' => 'neutro',
            'monto' => round((float) $items->sum('variacion'), 2),
            'items' => $items->map($item)->values(),
        ];

        $grupos = collect([
            $grupo('subieron', $r['en_contra'] ? 'Subieron (más deuda)' : 'Subieron', $filas->filter(fn ($f) => $f['variacion'] >= 0.005)),
            $grupo('bajaron', $r['en_contra'] ? 'Bajaron (se pagó / entregó)' : 'Bajaron', $filas->filter(fn ($f) => $f['variacion'] <= -0.005)),
            $grupo('movimientos', 'Con movimientos que se compensaron', $filas->filter(fn ($f) => abs($f['variacion']) < 0.005)),
        ])->filter()->values();

        // Cambios de esta línea en el día anterior DESPUÉS de su cierre.
        $verificador = app(\App\Services\BalanceVerificacionService::class);
        $cambio = null;
        if ($verificador->verificable($anterior)) {
            $c = collect($verificador->verificar($anterior)['categorias'])->firstWhere('categoria', $categoria);
            if ($c && abs($c['diferencia']) >= 1) $cambio = (float) $c['diferencia'];
        }

        $texto = "Qué cambió en {$nombre} entre el cierre del {$fmt($desde)} y el {$fmt($fecha)}, con los datos de hoy. "
            . 'Desplegá cada fila para ver los documentos que la movieron, cuándo y quién.';
        if ($cambio !== null) {
            $texto .= " Además, el {$fmt($desde)} esta línea cambió " . ($cambio > 0 ? '+' : '−') . $money(abs($cambio))
                . ' DESPUÉS de su cierre (registros tardíos o correcciones): por eso la tarjeta de variación puede mostrar un número distinto. '
                . 'El detalle está en "Cambios en días ya cerrados".';
        }

        return response()->json([
            'tipo'  => 'grupos',
            'aviso' => ['variant' => $cambio !== null ? 'warning' : 'info', 'titulo' => "{$nombre} — auditoría del período", 'texto' => $texto],
            'cards' => array_values(array_filter([
                ['label' => "Al {$fmt($desde)}", 'valor' => $r['total_desde']],
                ['label' => "Al {$fmt($fecha)}", 'valor' => $r['total_hasta']],
                ['label' => 'Variación real', 'valor' => $r['variacion'],
                 'color' => abs($r['variacion']) < 0.005 ? null : (($r['variacion'] > 0) === $r['en_contra'] ? 'danger' : 'success')],
                $cambio !== null ? ['label' => "Cambió después del cierre del {$fmt($desde)}", 'valor' => $cambio, 'color' => 'warning'] : null,
            ])),
            'itemCols' => [
                ['campo' => 'descripcion', 'label' => 'Concepto'],
                ['campo' => 'antesTxt',    'label' => "Al {$fmt($desde)}"],
                ['campo' => 'despuesTxt',  'label' => "Al {$fmt($fecha)}"],
            ],
            'montoLabel' => 'Variación',
            'grupos'     => $grupos,
        ]);
    }

    /**
     * Detalle de una línea de deuda/préstamo cuya deuda YA NO EXISTE.
     *
     * Los balances confirmados son snapshots inmutables: si la deuda se elimina
     * después, la línea queda apuntando a un id muerto. En vez de romper el
     * modal con un 404, se reconstruye lo que se pueda desde la auditoría de
     * eliminación (nombre, montos, movimientos, motivo y quién la eliminó).
     */
    private function detalleDeudaEliminada(int $empresaId, ?int $refId, string $fecha, string $categoria)
    {
        $item = $refId
            ? BalanceDiarioItem::query()
                ->join('balances_diarios as b', 'b.id', '=', 'balance_diario_items.balance_diario_id')
                ->where('b.empresa_id', $empresaId)
                ->whereDate('b.fecha', $fecha)
                ->where('balance_diario_items.categoria', $categoria)
                ->where('balance_diario_items.ref_id', $refId)
                ->select('balance_diario_items.*')
                ->first()
            : null;

        $log = $refId
            ? Auditoria::deEmpresa($empresaId)
                ->where('accion', 'deuda.eliminada')
                ->where('modelo_id', $refId)
                ->orderByDesc('id')
                ->first()
            : null;

        $ctx       = $log?->contexto ?? [];
        $snap      = $ctx['snapshot'] ?? [];
        $motivo    = $ctx['motivo'] ?? null;
        $nombre    = $snap['nombre'] ?? $item?->descripcion ?? 'Deuda eliminada';
        $original  = (float) ($snap['monto_original'] ?? $item?->monto ?? 0);
        $saldoFin  = (float) ($snap['saldo'] ?? 0);
        $porCobrar = ($snap['direccion'] ?? null) === 'por_cobrar' || $categoria === 'prestamo_otorgado';

        $grupos = collect($snap['movimientos'] ?? [])
            ->groupBy(fn ($m) => substr((string) ($m['fecha'] ?? $fecha), 0, 10))
            ->map(fn ($rows, $f) => [
                'id'      => 'mov-' . $f,
                'titulo'  => $f,
                'esFecha' => true,
                'monto'   => round((float) collect($rows)->sum('monto'), 2),
                'tipo'    => 'neutro',
                'items'   => collect($rows)->map(fn ($m) => [
                    'descripcion' => self::ETIQUETA_MOV_DEUDA[$m['tipo'] ?? ''] ?? 'Incremento',
                    'cuenta'      => '—',
                    'observacion' => 'Reconstruido desde la auditoría',
                    'monto'       => (float) ($m['monto'] ?? 0),
                    'tipo'        => in_array($m['tipo'] ?? '', ['amortizacion', 'compensacion'], true) ? 'ingreso' : 'egreso',
                    'user'        => $log?->user_name,
                ])->values(),
            ])->values();

        // El origen del saldo, igual que en el detalle de una deuda viva.
        $grupos->push([
            'id'      => 'registro',
            'titulo'  => substr((string) ($snap['fecha_inicio'] ?? $fecha), 0, 10),
            'esFecha' => true,
            'monto'   => $original,
            'tipo'    => 'neutro',
            'items'   => [[
                'descripcion' => 'Registro de la deuda (saldo inicial)',
                'cuenta'      => '—',
                'observacion' => $snap['observacion'] ?? '—',
                'monto'       => $original,
                'tipo'        => $porCobrar ? 'ingreso' : 'egreso',
                'user'        => null,
            ]],
        ]);

        $texto = $log
            ? 'La eliminó ' . $log->user_name . ' el ' . $log->created_at->format('d/m/Y H:i')
                . ($motivo ? ' — motivo: "' . $motivo . '".' : '.')
                . ' Este balance se confirmó ANTES de esa eliminación y es la foto de ese día, por eso la línea sigue apareciendo. El detalle de abajo se reconstruyó desde la auditoría.'
            : 'La deuda ya no existe y no se encontró el registro de auditoría de su eliminación. La línea se conserva porque el balance de este día es un snapshot inmutable.';

        return response()->json([
            'tipo'  => 'grupos',
            'aviso' => [
                'variant' => 'warning',
                'titulo'  => 'Esta deuda fue eliminada del sistema',
                'texto'   => $texto,
            ],
            'cards' => [
                ['label' => 'Deuda eliminada', 'valor' => $nombre, 'esTexto' => true],
                ['label' => 'Monto original', 'valor' => $original],
                ['label' => 'Monto en este balance', 'valor' => (float) ($item?->monto ?? 0),
                 'color' => $porCobrar ? 'success' : 'danger'],
                ['label' => 'Saldo al eliminarla', 'valor' => $saldoFin],
            ],
            'itemCols' => [
                ['campo' => 'descripcion', 'label' => 'Movimiento'],
                ['campo' => 'cuenta',      'label' => 'Cuenta'],
                ['campo' => 'observacion', 'label' => 'Observación'],
            ],
            'montoLabel' => 'Monto',
            'grupos'     => $grupos,
        ]);
    }

    /**
     * Actualiza una línea manual (monto) o su check de conciliación ("OK").
     */
    /**
     * "¿Qué cambió después del cierre?" de un día confirmado: líneas que cambiaron
     * y los documentos que lo causaron. Solo lectura.
     */
    public function cambiosCierre(Request $request, string $fecha)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $balance = BalanceDiario::deEmpresa($request->user()->empresa_id)->where('fecha', $fecha)->firstOrFail();
        abort_unless($balance->estado === 'confirmado', 404);

        return response()->json(
            app(\App\Services\CambiosCierreService::class)->analizar($balance)
            + ['umbral' => \App\Services\CambiosCierreService::UMBRAL]
        );
    }

    /**
     * Diferencia después del cierre de varios balances a la vez (la lista la pide
     * en segundo plano para no demorar la carga). Solo lectura.
     */
    public function verificacionLista(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($i) => (int) $i)->filter()->unique()->take(60);

        $svc = app(\App\Services\CambiosCierreService::class);

        return response()->json(
            BalanceDiario::deEmpresa($request->user()->empresa_id)->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (BalanceDiario $b) => [$b->id => $svc->resumen($b)])
        );
    }

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
