<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteAuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $registros = Auditoria::deEmpresa($user->empresa_id)
            ->with('user:id,name,email')
            ->when($request->accion, fn($q, $v) => $q->where('accion', $v))
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->fecha_desde, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->fecha_hasta, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
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
            'registros' => $registros,
            'usuarios'  => User::where('empresa_id', $user->empresa_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'acciones'  => Auditoria::accionLabels(),
            'filters'   => $request->only(['accion', 'user_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }
}
