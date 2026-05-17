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
use App\Services\CitaService;
use App\Services\LocalScopeService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VentaController extends Controller
{
    public function __construct(
        private VentaService      $ventaService,
        private LocalScopeService $scope,
        private CitaService       $citaService,
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

        // Si el POS se abre desde una cita (cita_id en query), prellenar el carrito.
        // El frontend usa esta data para inicializar carrito + cliente automaticamente.
        $citaPrellenada = null;
        if ($citaId = $request->query('cita_id')) {
            $cita = \App\Models\Cita::with(['cliente', 'items.producto', 'items.productoUnidad.unidadMedida'])
                ->where('id', $citaId)
                ->where('empresa_id', $user->empresa_id)
                ->first();

            if ($cita && !$cita->venta_id && $cita->estaActiva()) {
                // Marcamos por item si producto/unidad siguen activos. Entre agendar
                // y cobrar pueden haber pasado dias y el admin pudo desactivar items.
                // El frontend usa estos flags para mostrar alerta visible y bloquear
                // el cobro hasta que el cajero resuelva (eliminar la linea o pedir
                // reactivacion al admin).
                $items = $cita->items->map(function ($it) {
                    $productoActivo = (bool) ($it->producto?->activo);
                    $unidadActiva   = (bool) ($it->productoUnidad?->activo);

                    return [
                        'producto_id'        => $it->producto_id,
                        'producto_unidad_id' => $it->producto_unidad_id,
                        'producto_nombre'    => $it->producto->nombre,
                        'unidad_nombre'      => $it->productoUnidad->unidadMedida->nombre ?? '',
                        'cantidad'           => (float) $it->cantidad,
                        'precio_unitario'    => (float) $it->productoUnidad->precio_venta, // precio actual del catalogo
                        'incluye_igv'        => (bool) $it->producto->incluye_igv,
                        'producto_activo'    => $productoActivo,
                        'unidad_activa'      => $unidadActiva,
                        'inactivo'           => !($productoActivo && $unidadActiva),
                    ];
                });

                $citaPrellenada = [
                    'id'             => $cita->id,
                    'numero'         => $cita->numero,
                    'sujeto_label'   => $user->empresa->agenda_sujeto_label,
                    'sujeto'         => $cita->sujeto_nombre,
                    'cliente'        => $cita->cliente,
                    'items'          => $items,
                    'tiene_inactivos'=> $items->contains(fn($i) => $i['inactivo']),
                ];
            }
        }

        return Inertia::render('Pos/Index', [
            'turno'              => $turno,
            'productos'          => $productos,
            'clientes'           => $clientes,
            'metodosPago'        => $metodosPago,
            'conceptosDescuento' => $conceptosDescuento,
            'citaPrellenada'     => $citaPrellenada,
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

        // Si la venta vino desde una cita prellenada, vincular y marcar la cita
        // como completada. Falla silenciosamente si la cita no existe / no aplica.
        if ($citaId = $request->input('cita_id')) {
            $cita = \App\Models\Cita::where('id', $citaId)
                ->where('empresa_id', $user->empresa_id)
                ->first();
            if ($cita && !$cita->venta_id && $cita->estaActiva()) {
                try {
                    $this->citaService->vincularVenta($cita, $venta, $user);
                } catch (\Throwable $e) {
                    \Log::warning('No se pudo vincular cita con venta', [
                        'cita_id'  => $citaId,
                        'venta_id' => $venta->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

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
