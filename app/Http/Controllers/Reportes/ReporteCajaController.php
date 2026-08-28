<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Turno;
use App\Models\TurnoRetiro;
use App\Models\User;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteCajaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = Turno::deEmpresa($user->empresa_id)
            ->whereBetween('fecha_apertura', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($request->caja_id, fn ($q, $v) => $q->where('caja_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));

        // Una sola pasada liviana sobre TODOS los turnos del rango: alimenta
        // KPIs, serie diaria y desgloses por caja/cajero sin N queries.
        $todos = (clone $base)
            ->with(['caja:id,nombre', 'user:id,name'])
            ->withCount(['ventas as ventas_count' => fn ($q) => $q->where('estado', 'completada')])
            ->withSum(['ventas as ventas_total' => fn ($q) => $q->where('estado', 'completada')], 'total')
            ->withSum('gastos as gastos_total', 'monto')
            ->withSum('cancelacionesAnticipo as cancelaciones_total', 'monto')
            ->get(['id', 'caja_id', 'user_id', 'estado', 'fecha_apertura', 'diferencia']);

        $cerrados = $todos->where('estado', 'cerrado');

        $kpis = [
            'total_turnos'    => $todos->count(),
            'turnos_abiertos' => $todos->where('estado', 'abierto')->count(),
            'turnos_cerrados' => $cerrados->count(),
            'ventas_total'    => (float) $todos->sum(fn ($t) => (float) ($t->ventas_total ?? 0)),
            'ventas_count'    => (int)   $todos->sum('ventas_count'),
            'gastos_total'    => (float) $todos->sum(fn ($t) => (float) ($t->gastos_total ?? 0)),
            'cancelaciones_total' => (float) $todos->sum(fn ($t) => (float) ($t->cancelaciones_total ?? 0)),
            'sobrantes'       => (float) $cerrados->sum(fn ($t) => max(0, (float) $t->diferencia)),
            'faltantes'       => (float) $cerrados->sum(fn ($t) => max(0, -(float) $t->diferencia)),
            'diferencia_neta' => (float) $cerrados->sum(fn ($t) => (float) $t->diferencia),
            'con_descuadre'   => $cerrados->filter(fn ($t) => abs((float) $t->diferencia) > 0.009)->count(),
            'retiros_total'   => (float) TurnoRetiro::whereIn('turno_id', (clone $base)->select('id'))->sum('monto'),
        ];

        // Serie diaria: vendido y diferencia de caja por día de apertura
        $serieDiaria = $todos
            ->groupBy(fn ($t) => $t->fecha_apertura->toDateString())
            ->map(fn ($grupo, $dia) => [
                'dia'        => $dia,
                'ventas'     => (float) $grupo->sum(fn ($t) => (float) ($t->ventas_total ?? 0)),
                'gastos'     => (float) $grupo->sum(fn ($t) => (float) ($t->gastos_total ?? 0)),
                'cancelaciones' => (float) $grupo->sum(fn ($t) => (float) ($t->cancelaciones_total ?? 0)),
                'diferencia' => (float) $grupo->sum(fn ($t) => (float) ($t->diferencia ?? 0)),
            ])
            ->sortKeys()->values();

        // Desglose por caja
        $porCaja = $todos
            ->groupBy('caja_id')
            ->map(fn ($grupo) => [
                'nombre' => $grupo->first()->caja?->nombre ?? '—',
                'total'  => (float) $grupo->sum(fn ($t) => (float) ($t->ventas_total ?? 0)),
                'cancelaciones' => (float) $grupo->sum(fn ($t) => (float) ($t->cancelaciones_total ?? 0)),
                'turnos' => $grupo->count(),
            ])
            ->sortByDesc('total')->values();

        // Desglose por cajero (quién abrió) con su descuadre acumulado
        $porCajero = $todos
            ->groupBy('user_id')
            ->map(fn ($grupo) => [
                'nombre'     => $grupo->first()->user?->name ?? '—',
                'total'      => (float) $grupo->sum(fn ($t) => (float) ($t->ventas_total ?? 0)),
                'cancelaciones' => (float) $grupo->sum(fn ($t) => (float) ($t->cancelaciones_total ?? 0)),
                'turnos'     => $grupo->count(),
                'diferencia' => (float) $grupo->where('estado', 'cerrado')->sum(fn ($t) => (float) ($t->diferencia ?? 0)),
            ])
            ->sortByDesc('total')->values();

        // Listado paginado con TODO el detalle para la fila expandible
        $turnos = (clone $base)
            ->with([
                'user:id,name',
                'userCierre:id,name',
                'caja:id,nombre',
                'local:id,nombre',
                'arqueoMetodos' => fn ($q) => $q->select('id', 'turno_id', 'metodo_pago_id', 'monto_declarado')
                    ->with('metodoPago:id,nombre'),
                'gastos' => fn ($q) => $q->select('id', 'turno_id', 'gasto_concepto_id', 'monto', 'comentario')
                    ->with('concepto:id,nombre'),
                'retiros' => fn ($q) => $q->select('id', 'turno_id', 'user_id', 'concepto', 'monto', 'momento', 'estado', 'created_at')
                    ->with('user:id,name'),
            ])
            ->withCount(['ventas as ventas_count' => fn ($q) => $q->where('estado', 'completada')])
            ->withSum(['ventas as ventas_total' => fn ($q) => $q->where('estado', 'completada')], 'total')
            ->withSum('gastos as gastos_total', 'monto')
            ->withSum('cancelacionesAnticipo as cancelaciones_total', 'monto')
            ->orderByDesc('fecha_apertura')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($t) => [
                'id'                     => $t->id,
                'caja'                   => $t->caja,
                'local'                  => $t->local,
                'user'                   => $t->user,
                'user_cierre'            => $t->userCierre,
                'estado'                 => $t->estado,
                'fecha_apertura'         => $t->fecha_apertura?->toIso8601String(),
                'fecha_cierre'           => $t->fecha_cierre?->toIso8601String(),
                'monto_apertura'         => (float) $t->monto_apertura,
                'monto_fondos_adicionales' => (float) ($t->monto_fondos_adicionales ?? 0),
                'monto_caja_chica'       => (float) $t->monto_caja_chica,
                'monto_cierre_declarado' => $t->monto_cierre_declarado !== null ? (float) $t->monto_cierre_declarado : null,
                'monto_cierre_esperado'  => $t->monto_cierre_esperado  !== null ? (float) $t->monto_cierre_esperado  : null,
                'diferencia'             => $t->diferencia !== null ? (float) $t->diferencia : null,
                'ventas_count'           => (int)   $t->ventas_count,
                'ventas_total'           => (float) ($t->ventas_total ?? 0),
                'gastos_total'           => (float) ($t->gastos_total ?? 0),
                'cancelaciones_total'    => (float) ($t->cancelaciones_total ?? 0),
                'observacion_apertura'   => $t->observacion_apertura,
                'observacion_cierre'     => $t->observacion_cierre,
                'arqueo_metodos'         => $t->arqueoMetodos->map(fn ($m) => [
                    'id'              => $m->id,
                    'nombre'          => $m->metodoPago?->nombre ?? '—',
                    'monto_declarado' => (float) $m->monto_declarado,
                ])->values(),
                'gastos'                 => $t->gastos->map(fn ($g) => [
                    'id'       => $g->id,
                    'concepto' => $g->concepto?->nombre ?? '—',
                    'monto'    => (float) $g->monto,
                ])->values(),
                'efectivo_arrastre'      => $t->efectivo_arrastre !== null ? (float) $t->efectivo_arrastre : null,
                'destino_efectivo'       => $t->destino_efectivo,
                'retiros_total'          => (float) $t->retiros->sum('monto'),
                'retiros'                => $t->retiros->map(fn ($r) => [
                    'id'       => $r->id,
                    'concepto' => $r->concepto,
                    'monto'    => (float) $r->monto,
                    'momento'  => $r->momento,
                    'estado'   => $r->estado,
                    'usuario'  => $r->user?->name ?? '—',
                    'fecha'    => $r->created_at?->toIso8601String(),
                ])->values(),
            ]);

        $locales = $this->scope->localesVisibles($user);
        $cajas   = Caja::where('empresa_id', $user->empresa_id)
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'local_id']);
        $usuarios = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Caja', [
            'turnos'       => $turnos,
            'kpis'         => $kpis,
            'serie_diaria' => $serieDiaria,
            'por_caja'     => $porCaja,
            'por_cajero'   => $porCajero,
            'locales'      => $locales,
            'cajas'        => $cajas,
            'usuarios'     => $usuarios,
            'filters'      => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'estado'      => $request->estado,
                'local_id'    => $request->local_id,
                'caja_id'     => $request->caja_id,
                'user_id'     => $request->user_id,
            ],
        ]);
    }
}
