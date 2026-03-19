<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\DescuentoConcepto;
use App\Models\DescuentoLog;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DescuentoLogController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $logs = DescuentoLog::where('empresa_id', $user->empresa_id)
            ->with(['user', 'cliente', 'concepto', 'venta', 'aprobadoPor'])
            ->when($request->concepto_id, fn($q, $v) => $q->where('descuento_concepto_id', $v))
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->fecha_desde, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->fecha_hasta, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($user->local_id, fn($q) => $q->whereHas('venta', fn($vq) => $vq->where('local_id', $user->local_id)))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $conceptos = DescuentoConcepto::deEmpresa($user->empresa_id)->orderBy('nombre')->get();

        return Inertia::render('Reportes/Descuentos', [
            'logs'      => $logs,
            'conceptos' => $conceptos,
            'filters'   => $request->only(['concepto_id', 'user_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }
}
