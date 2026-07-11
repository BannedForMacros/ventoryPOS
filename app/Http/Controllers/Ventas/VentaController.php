<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\AnularVentaRequest;
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

    /** TC USD del día para el POS; null si la SBS no responde (el POS no debe romperse). */
    private function tipoCambioHoy(): ?float
    {
        try {
            return app(\App\Services\TipoCambioService::class)->tasaHoy('USD');
        } catch (\Throwable $e) {
            return null;
        }
    }

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

        // A14 — Bloquear el POS cuando el usuario no tiene un almacén de ventas
        // resoluble (típicamente admin sin local_id en modo central_y_local).
        // Cargar el POS, llenar el carrito e intentar cobrar al final daba un
        // 422 sorpresa. Ahora el frontend recibe esta bandera y deshabilita el
        // botón "Cobrar" con un mensaje claro desde el primer momento.
        $puedeVender    = $this->scope->puedeVender($user);
        $razonNoVender  = null;
        if (!$puedeVender) {
            $razonNoVender = $user->empresa->usaCentralYLocal() && !$user->local_id
                ? 'No tienes un local asignado. Selecciona un local para operar el POS.'
                : 'No hay almacén de ventas configurado para tu local. Contacta al administrador.';
        }

        return Inertia::render('Pos/Index', [
            'turno'              => $turno,
            'productos'          => $productos,
            'clientes'           => $clientes,
            'metodosPago'        => $metodosPago,
            'conceptosDescuento' => $conceptosDescuento,
            'citaPrellenada'     => $citaPrellenada,
            'puedeVender'        => $puedeVender,
            'razonNoVender'      => $razonNoVender,
            // Multimoneda: monedas disponibles y TC del día (soles por 1 USD).
            'monedas'            => ['PEN', 'USD'],
            'tipoCambioHoy'      => $this->tipoCambioHoy(),
        ]);
    }

    // ── Ventas (historial) ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user    = $request->user();
        $esAdmin = $user->rol->es_admin;

        // La cajera (no admin) se limita a SUS ventas y por defecto a las de HOY.
        // El admin ve todo el historial de la empresa/local. Si la cajera cambia
        // el rango de fechas, sigue viendo solo lo suyo.
        $hoy         = now()->toDateString();
        $fechaDesde  = $request->fecha_desde ?: (!$esAdmin ? $hoy : null);
        $fechaHasta  = $request->fecha_hasta ?: (!$esAdmin ? $hoy : null);

        $ventas = Venta::deEmpresa($user->empresa_id)
            ->with(['user', 'cliente', 'local', 'caja', 'turno'])
            ->when(!$esAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v))
            ->when($fechaDesde, fn($q, $v) => $q->where('fecha_venta', '>=', $v))
            ->when($fechaHasta, fn($q, $v) => $q->where('fecha_venta', '<=', $v . ' 23:59:59'))
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
            'esAdmin' => $esAdmin,
            // Reflejar en la UI las fechas efectivas (incluye el default de hoy).
            'filters' => array_merge(
                $request->only(['estado', 'fecha_desde', 'fecha_hasta', 'local_id']),
                ['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta],
            ),
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

    public function anular(AnularVentaRequest $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        if ($venta->estado === 'anulada') {
            return back()->withErrors(['venta' => 'La venta ya está anulada.']);
        }

        $this->ventaService->anular(
            $venta,
            $request->user(),
            $request->validated('motivo'),
        );

        return redirect()->back()->with('success', "Venta {$venta->numero} anulada correctamente.");
    }
}
