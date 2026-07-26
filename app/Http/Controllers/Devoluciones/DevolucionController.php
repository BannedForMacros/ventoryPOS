<?php

namespace App\Http\Controllers\Devoluciones;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\DevolucionMotivo;
use App\Models\MetodoPago;
use App\Models\Turno;
use App\Models\Venta;
use App\Services\ConfiguracionOperacionService;
use App\Services\DevolucionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class DevolucionController extends Controller
{
    public function __construct(
        private DevolucionService $service,
        private ConfiguracionOperacionService $config,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // M19: paginar para no cargar miles de filas. withQueryString preserva
        // los filtros activos al navegar entre páginas.
        $devoluciones = Devolucion::deEmpresa($user->empresa_id)
            ->with(['venta:id,numero,fecha_venta,total', 'motivo', 'user', 'local'])
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('numero', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('venta', fn ($v) => $v->where('numero', 'ilike', "%{$t}%"))
                    ->orWhereHas('motivo', fn ($m) => $m->where('nombre', 'ilike', "%{$t}%")));
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Devoluciones/Index', [
            'devoluciones' => $devoluciones,
            'filters'      => $request->only(['estado', 'fecha_desde', 'fecha_hasta']),
            'buscar'       => $request->input('buscar', ''),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $turno = Turno::turnoActivoDelUsuario($user->id);

        return Inertia::render('Devoluciones/Create', [
            'motivos'     => DevolucionMotivo::deEmpresa($user->empresa_id)->activo()->orderBy('orden')->get(),
            // Métodos CON sus cuentas: el reembolso elige a qué cuenta sale la
            // plata (pivot.id = cuenta_metodo_pago_id, igual que el POS).
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()
                ->with(['tipo:id,slug,nombre,icono', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])
                ->orderBy('nombre')->get()
                ->map(fn ($m) => [
                    'id' => $m->id, 'nombre' => $m->nombre, 'tipo_id' => $m->tipo_id,
                    'tipo_slug' => $m->tipo?->slug,
                    'cuentas'   => $m->cuentas->map(fn ($c) => [
                        'cuenta_metodo_pago_id' => $c->pivot->id,
                        'nombre'                => $c->nombre,
                    ])->values(),
                ]),
            'turnoActivo' => $turno?->load('caja'),
            // "Afecta caja a:" — para admin (o cuando no hay turno propio), poder
            // elegir a qué caja/turno se imputa la devolución. Turnos abiertos.
            'turnos'      => Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where('estado', 'abierto')
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
            'esAdmin'     => (bool) $user->rol->es_admin,
        ]);
    }

    /**
     * Buscar venta por número o ID, devuelve la venta con sus items y devoluciones previas.
     */
    public function buscarVenta(Request $request)
    {
        $request->validate([
            'q' => 'required|string|max:30',
        ]);

        $user = $request->user();
        $q    = $request->input('q');

        $venta = Venta::deEmpresa($user->empresa_id)
            ->where(function ($qry) use ($q) {
                $qry->where('numero', 'ilike', "%{$q}%")
                    ->orWhere('id', is_numeric($q) ? (int) $q : 0);
            })
            ->with([
                'items.producto',
                'items.productoUnidad.unidadMedida',
                'cliente',
                'local',
                'pagos.metodoPago',
            ])
            ->where('estado', 'completada')
            ->latest('fecha_venta')
            ->first();

        if (!$venta) {
            return response()->json(['error' => 'No se encontró una venta completada con ese criterio.'], 404);
        }

        // Verificar plazo
        $dentroPlazo = $this->config->estaDentroDelPlazo($venta->local, $venta->fecha_venta);

        // Calcular cantidad ya devuelta por item
        $devueltos = \Illuminate\Support\Facades\DB::table('devoluciones_detalle')
            ->join('devoluciones', 'devoluciones.id', '=', 'devoluciones_detalle.devolucion_id')
            ->where('devoluciones.venta_id', $venta->id)
            ->whereIn('devoluciones.estado', ['pendiente', 'aprobada', 'completada'])
            ->select('devoluciones_detalle.venta_item_id', \Illuminate\Support\Facades\DB::raw('SUM(devoluciones_detalle.cantidad) as total'))
            ->groupBy('devoluciones_detalle.venta_item_id')
            ->pluck('total', 'venta_item_id');

        $itemsConDisponibilidad = $venta->items->map(function ($it) use ($devueltos) {
            $devuelto = (float) ($devueltos[$it->id] ?? 0);
            $disponible = (float) $it->cantidad - $devuelto;
            $producto = $it->producto;
            return [
                'id'                  => $it->id,
                'producto_id'         => $it->producto_id,
                'producto_unidad_id'  => $it->producto_unidad_id,
                'producto_nombre'     => $it->producto_nombre,
                'unidad_nombre'       => $it->unidad_nombre,
                'cantidad'            => (float) $it->cantidad,
                'precio_unitario'     => (float) $it->precio_unitario,
                'descuento_item'      => (float) $it->descuento_item,
                'subtotal'            => (float) $it->subtotal,
                'es_retornable'       => $producto ? $this->config->esRetornable($producto) : true,
                'cantidad_devuelta'   => $devuelto,
                'cantidad_disponible' => max(0, $disponible),
            ];
        });

        return response()->json([
            'venta' => [
                'id'             => $venta->id,
                'numero'         => $venta->numero,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'fecha_venta'    => $venta->fecha_venta->toIso8601String(),
                'subtotal'       => (float) $venta->subtotal,
                'descuento_total'=> (float) $venta->descuento_total,
                'igv'            => (float) $venta->igv,
                'total'          => (float) $venta->total,
                'cliente'        => $venta->cliente ? [
                    'id'              => $venta->cliente->id,
                    'nombre_completo' => $venta->cliente->nombre_completo,
                    'numero_documento' => $venta->cliente->numero_documento,
                ] : null,
                'local'          => ['id' => $venta->local->id, 'nombre' => $venta->local->nombre],
                'pagos'          => $venta->pagos->map(fn($p) => [
                    'metodo_pago_id'   => $p->metodo_pago_id,
                    'metodo_pago_nombre' => $p->metodoPago->nombre ?? null,
                    'metodo_pago_tipo' => $p->metodoPago->tipo ?? null,
                    'monto'            => (float) $p->monto,
                ]),
                'items'          => $itemsConDisponibilidad,
            ],
            'configuracion' => [
                'permite_devoluciones' => $this->config->permiteDevoluciones($venta->local),
                'dias_max_devolucion'  => $this->config->diasMaxDevolucion($venta->local),
                'requiere_aprobacion'  => $this->config->requiereAprobacionDevolucion($venta->local),
                'restock_default'      => $this->config->restockDefault($venta->local),
                'dentro_del_plazo'     => $dentroPlazo,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'venta_id'        => 'required|exists:ventas,id',
            'motivo_id'       => 'required|exists:devolucion_motivos,id',
            'forma_reembolso' => 'required|in:efectivo,mismo_metodo,vale_credito,cambio_producto,sin_reembolso',
            'observacion'     => 'nullable|string|max:500',
            'items'           => 'required|array|min:1',
            'items.*.venta_item_id'   => 'required|exists:venta_items,id',
            'items.*.cantidad'        => 'required|numeric|min:0.0001',
            'items.*.estado_producto' => 'nullable|in:bueno,defectuoso,vencido,dañado',
            'items.*.restock'         => 'nullable|boolean',
            'items.*.motivo_id'       => 'nullable|exists:devolucion_motivos,id',
            'items.*.observacion'     => 'nullable|string',
            'pagos'                   => 'nullable|array',
            'pagos.*.metodo_pago_id'  => 'required_with:pagos|exists:metodos_pago,id',
            // Cuenta obligatoria si el método tiene cuentas (1 se autoselecciona; 2+ elige).
            'pagos.*.cuenta_metodo_pago_id' => ['nullable', 'integer',
                function ($attr, $value, $fail) use ($request) {
                    if ($value) return;
                    preg_match('/pagos\.(\d+)\./', $attr, $m);
                    if (isset($m[1]) && \App\Support\PagoCuenta::requiere((int) $request->input("pagos.{$m[1]}.metodo_pago_id"))) {
                        $fail('Debes seleccionar la cuenta para este método de pago.');
                    }
                },
            ],
            'pagos.*.monto'           => 'required_with:pagos|numeric|min:0',
            'pagos.*.referencia'      => 'nullable|string|max:100',
            // "Afecta caja a:" — turno al que se imputa la devolución (opcional).
            'turno_id'                => 'nullable|integer|exists:turnos,id',
        ]);

        $user  = $request->user();

        // Turno destino, gateado por config (módulo 'devoluciones', modo forzado:
        // el admin elige; el cajero se imputa a su turno activo). Si la empresa
        // apaga el módulo, resolverTurno devuelve null → no afecta ninguna caja.
        $turnoId = \App\Support\AfectaCaja::resolverTurno(
            $user, 'devoluciones',
            !empty($data['turno_id']) ? (int) $data['turno_id'] : null,
            'forzado',
        );
        $turno = null;
        if ($turnoId) {
            $turno = Turno::where('id', $turnoId)
                ->where('empresa_id', $user->empresa_id)
                ->where('estado', 'abierto')
                ->first();
            if (!$turno && !empty($data['turno_id'])) {
                return back()->withErrors(['turno_id' => 'El turno indicado no está abierto.'])->withInput();
            }
        }

        try {
            $devolucion = $this->service->crear($data, $user, $turno);
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return back()->withErrors(['general' => $e->getMessage()])->withInput();
        }

        return redirect()->route('devoluciones.show', $devolucion->id)
            ->with('success', 'Devolución registrada correctamente.');
    }

    public function show(Request $request, Devolucion $devolucion)
    {
        abort_if($devolucion->empresa_id !== $request->user()->empresa_id, 403);

        $devolucion->load([
            'venta:id,numero,fecha_venta,total,cliente_id',
            'venta.cliente',
            'motivo',
            'user', 'userAprobacion',
            'detalles.producto', 'detalles.motivo',
            'pagos.metodoPago',
        ]);

        return Inertia::render('Devoluciones/Show', [
            'devolucion' => $devolucion,
        ]);
    }

    public function aprobar(Request $request, Devolucion $devolucion)
    {
        abort_if($devolucion->empresa_id !== $request->user()->empresa_id, 403);
        abort_unless($request->user()->rol?->es_admin, 403, 'Solo administradores pueden aprobar devoluciones.');

        $obs = $request->input('observacion_aprobacion');
        $devolucion->aprobar($request->user()->id, $obs);
        $devolucion->refresh()->completar();

        \App\Services\AuditoriaService::log('devolucion.aprobada', $devolucion, [
            'numero'        => $devolucion->numero,
            'venta_id'      => $devolucion->venta_id,
            'monto'         => (float) $devolucion->monto_devolucion,
            'observacion'   => $obs,
        ]);

        return redirect()->back()->with('success', 'Devolución aprobada y completada.');
    }

    public function rechazar(Request $request, Devolucion $devolucion)
    {
        abort_if($devolucion->empresa_id !== $request->user()->empresa_id, 403);
        abort_unless($request->user()->rol?->es_admin, 403, 'Solo administradores pueden rechazar devoluciones.');

        $obs = $request->input('observacion_aprobacion');
        $devolucion->rechazar($request->user()->id, $obs);

        \App\Services\AuditoriaService::log('devolucion.rechazada', $devolucion, [
            'numero'      => $devolucion->numero,
            'venta_id'    => $devolucion->venta_id,
            'observacion' => $obs,
        ]);

        return redirect()->back()->with('success', 'Devolución rechazada.');
    }

    public function anular(Request $request, Devolucion $devolucion)
    {
        abort_if($devolucion->empresa_id !== $request->user()->empresa_id, 403);

        $devolucion->anular();

        return redirect()->back()->with('success', 'Devolución anulada.');
    }
}
