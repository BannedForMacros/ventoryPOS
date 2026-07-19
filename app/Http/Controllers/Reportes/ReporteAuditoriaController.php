<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteAuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->subDays(6)->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = Auditoria::deEmpresa($user->empresa_id)
            ->whereBetween('created_at', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->accion, fn ($q, $v) => $q->where('accion', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v));

        $labels = Auditoria::accionLabels();

        // ── KPIs ──────────────────────────────────────────────────────────
        $porAccion = (clone $base)
            ->select('accion', DB::raw('COUNT(*) as total'))
            ->groupBy('accion')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'accion' => $r->accion,
                'label'  => $labels[$r->accion] ?? $r->accion,
                'total'  => (int) $r->total,
            ]);

        $kpis = [
            'total_acciones'   => (int) $porAccion->sum('total'),
            'usuarios_activos' => (int) (clone $base)->distinct('user_id')->count('user_id'),
            'acciones_hoy'     => (int) (clone $base)->whereDate('created_at', now()->toDateString())->count(),
            'accion_top'       => $porAccion->first()['label'] ?? '—',
        ];

        // ── Desglose por usuario (snapshot user_name, sobrevive a bajas) ──
        $porUsuario = (clone $base)
            ->select('user_name', DB::raw('COUNT(*) as total'))
            ->groupBy('user_name')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['nombre' => $r->user_name ?? '—', 'total' => (int) $r->total]);

        // ── Serie diaria ──────────────────────────────────────────────────
        $serieDiaria = (clone $base)
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => ['dia' => $r->dia, 'total' => (int) $r->total]);

        // ── Listado ───────────────────────────────────────────────────────
        $registros = (clone $base)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($r) => [
                'id'           => $r->id,
                'created_at'   => $r->created_at->toIso8601String(),
                'accion'       => $r->accion,
                'accion_label' => $r->accion_label,
                'user_id'      => $r->user_id,
                'user_name'    => $r->user_name,
                'user_email'   => $r->user?->email,
                'modelo_tipo'  => $r->modelo_tipo ? class_basename($r->modelo_tipo) : null,
                'modelo_id'    => $r->modelo_id,
                'contexto'     => $r->contexto,
                'ip'           => $r->ip,
            ]);

        return Inertia::render('Reportes/Auditoria', [
            'registros'    => $registros,
            'kpis'         => $kpis,
            'por_accion'   => $porAccion,
            'por_usuario'  => $porUsuario,
            'serie_diaria' => $serieDiaria,
            'usuarios'     => User::where('empresa_id', $user->empresa_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'acciones'     => $labels,
            'filters'      => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'accion'      => $request->accion,
                'user_id'     => $request->user_id,
            ],
        ]);
    }
}
