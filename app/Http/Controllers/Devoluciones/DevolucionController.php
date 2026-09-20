<?php

namespace App\Http\Controllers\Devoluciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Devoluciones\StoreDevolucionRequest;
use App\Jobs\EmitirNotaCreditoElectronica;
use App\Models\Devolucion;
use App\Models\DevolucionMotivo;
use App\Models\MetodoPago;
use App\Models\Turno;
use App\Models\Venta;
use App\Services\AuditoriaService;
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
            // Bandeja de lo que quedó sin acreditar ante SUNAT. Es el filtro que
            // un admin mira de corrido, en lugar de abrir devolución por
            // devolución para descubrir cuál se quedó a medias.
            ->when($request->boolean('nc_pendiente'),
                fn ($q) => $q->whereIn('nota_credito_estado', Devolucion::NC_SIN_CERRAR))
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
            'filters'      => $request->only(['estado', 'fecha_desde', 'fecha_hasta', 'nc_pendiente']),
            'buscar'       => $request->input('buscar', ''),
            // Cuántas notas de crédito quedaron sin emitir, en TODA la empresa y no
            // solo en la página visible: es un aviso, y un aviso que depende de en
            // qué página estés no avisa de nada.
            'ncFallidas'   => Devolucion::deEmpresa($user->empresa_id)
                ->where('nota_credito_estado', Devolucion::NC_FALLIDA)->count(),
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
            // Llega desde "Devolver / Nota de crédito" en una venta ya declarada.
            // Se manda el NÚMERO, que es lo que busca el formulario, y solo si la
            // venta es de esta empresa: el id viaja por la URL y cualquiera podría
            // cambiarlo.
            'ventaPrellenada' => $request->filled('venta_id')
                ? Venta::where('id', $request->integer('venta_id'))
                    ->where('empresa_id', $user->empresa_id)
                    ->value('numero')
                : null,
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

    public function store(StoreDevolucionRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

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

        try {
            $devolucion->anular();
        } catch (\LogicException $e) {
            // Ej.: el vale/crédito de la devolución ya fue usado por el cliente.
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Devolución anulada.');
    }

    /**
     * Vuelve a intentar la nota de crédito de una devolución que quedó a medias.
     *
     * POR QUÉ EXISTE: la NC se emite en segundo plano y puede agotar sus
     * reintentos (SUNAT caída, el comprobante que nunca llegó a estar acreditable).
     * Hasta ahora eso terminaba en un mensaje que mandaba a emitirla a mano en
     * FacturaMac: el sistema sabía que algo quedó sin terminar y no ofrecía cómo
     * terminarlo, y en ventoryPOS no quedaba rastro de que se hubiera resuelto.
     *
     * Reintentar es SEGURO por diseño: el job manda `idempotency_key` =
     * "devolucion-{id}", así que por más veces que se pulse, una devolución tiene
     * como mucho una nota de crédito.
     *
     * NO revierte ni toca la devolución: el stock ya volvió y el dinero ya salió.
     * Lo único que hace es volver a intentar el documento.
     */
    public function reintentarNotaCredito(Request $request, Devolucion $devolucion)
    {
        $user = $request->user();
        abort_if($devolucion->empresa_id !== $user->empresa_id, 403);
        abort_unless($user->rol?->es_admin, 403, 'Solo administradores pueden reintentar la nota de crédito.');

        // `emitida` y `no_aplica` no se reintentan: en la primera ya existe el
        // documento y en la segunda no hay nada que acreditar. Dejar pulsar aquí
        // solo serviría para encolar trabajo que el job va a descartar.
        if (!$devolucion->notaCreditoSinCerrar()) {
            return redirect()->back()->with('error',
                $devolucion->nota_credito_estado === Devolucion::NC_EMITIDA
                    ? 'Esta devolución ya tiene su nota de crédito emitida.'
                    : 'Esta devolución no necesita nota de crédito.');
        }

        // Arranca de cero la cuenta de esperas: si la anterior murió de viejo
        // esperando el Resumen Diario, este intento merece su propio plazo.
        $devolucion->anotarNotaCredito(Devolucion::NC_PENDIENTE);

        EmitirNotaCreditoElectronica::dispatch(
            $devolucion->id,
            (int) $devolucion->empresa_id,
            $user->id,
        )->afterCommit();

        AuditoriaService::log('venta_comprobante.nota_credito_reintentada', $devolucion, [
            'venta_id'      => $devolucion->venta_id,
            'estado_previo' => $devolucion->getOriginal('nota_credito_estado'),
        ], $user);

        return redirect()->back()->with('success',
            'Nota de crédito encolada de nuevo. En unos minutos verás aquí en qué quedó.');
    }
}
