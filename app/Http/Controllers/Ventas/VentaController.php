<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaRequest;
use App\Models\Cliente;
use App\Models\DescuentoConcepto;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\Venta;
use App\Services\LocalScopeService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VentaController extends Controller
{
    public function __construct(
        private VentaService     $ventaService,
        private LocalScopeService $scope,
    ) {}

    // ── POS ────────────────────────────────────────────────────────────────────

    public function pos(Request $request)
    {
        $user   = $request->user();
        $turno  = Turno::turnoActivoDelUsuario($user->id)?->load('caja');

        if (!$turno) {
            return redirect()->route('turnos.index')
                ->with('error', 'Debes tener un turno activo para acceder al POS.');
        }

        $productos = Producto::deEmpresa($user->empresa_id)
            ->activo()
            ->with(['unidades.unidadMedida', 'unidadBase', 'categoria'])
            ->orderBy('nombre')
            ->get();

        $clientes = Cliente::where('empresa_id', $user->empresa_id)
            ->activo()
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'tipo_documento', 'numero_documento', 'telefono']);

        $metodosPago = MetodoPago::deEmpresa($user->empresa_id)
            ->activo()
            ->with(['cuentas' => fn($q) => $q->where('activo', true)])
            ->orderBy('nombre')
            ->get();

        $conceptosDescuento = DescuentoConcepto::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Pos/Index', [
            'turno'              => $turno,
            'productos'          => $productos,
            'clientes'           => $clientes,
            'metodosPago'        => $metodosPago,
            'conceptosDescuento' => $conceptosDescuento,
        ]);
    }

    // ── Ventas (historial) ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $request->user();

        $ventas = Venta::deEmpresa($user->empresa_id)
            ->with(['user', 'cliente', 'local'])
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v))
            ->when($request->fecha_desde, fn($q, $v) => $q->where('fecha_venta', '>=', $v))
            ->when($request->fecha_hasta, fn($q, $v) => $q->where('fecha_venta', '<=', $v . ' 23:59:59'))
            ->when($request->local_id, fn($q, $v) => $q->where('local_id', $v))
            ->when($user->local_id, fn($q) => $q->where('local_id', $user->local_id))
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $locales = $this->scope->localesVisibles($user);

        return Inertia::render('Ventas/Index', [
            'ventas'  => $ventas,
            'locales' => $locales,
            'filters' => $request->only(['estado', 'fecha_desde', 'fecha_hasta', 'local_id']),
        ]);
    }

    public function show(Request $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        $venta->load([
            'user', 'cliente', 'local', 'caja', 'turno',
            'items.producto', 'items.productoUnidad.unidadMedida', 'items.descuentoConcepto',
            'pagos.metodoPago', 'pagos.cuentaMetodoPago',
            'descuentosLog.concepto', 'descuentosLog.user',
        ]);

        return Inertia::render('Ventas/Show', [
            'venta' => $venta,
        ]);
    }

    // ── Store (POS: registrar venta) ───────────────────────────────────────────

    public function store(StoreVentaRequest $request)
    {
        $user  = $request->user();
        $turno = Turno::turnoActivoDelUsuario($user->id);

        if (!$turno) {
            return back()->withErrors(['turno' => 'No tienes un turno activo.']);
        }

        $venta = $this->ventaService->crear($request->validated(), $user, $turno);

        return redirect()->route('ventas.show', $venta)
            ->with('success', "Venta {$venta->numero} registrada correctamente.");
    }

    // ── Anular ─────────────────────────────────────────────────────────────────

    public function anular(Request $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        if ($venta->estado === 'anulada') {
            return back()->withErrors(['venta' => 'La venta ya está anulada.']);
        }

        $this->ventaService->anular($venta, $request->user());

        return redirect()->back()->with('success', "Venta {$venta->numero} anulada correctamente.");
    }
}
