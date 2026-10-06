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
use Illuminate\Support\Facades\DB;
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
            'q'        => 'required_without:venta_id|nullable|string|max:30',
            'venta_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $q    = trim((string) $request->input('q', ''));

        $base = fn () => Venta::deEmpresa($user->empresa_id)->where('estado', 'completada');

        if ($request->filled('venta_id')) {
            // Elegida de la lista de coincidencias: va por id, sin ambigüedad.
            $candidatas = $base()->whereKey($request->integer('venta_id'))->get();
        } else {
            // Coincidencia EXACTA del número (sin distinguir mayúsculas) o del id.
            // Antes era ILIKE %q% y se quedaba con la más reciente: con el
            // correlativo por turno, "V-1001" existe una vez por turno y la
            // devolución acababa colgada de la venta equivocada.
            $candidatas = $base()
                ->where(function ($qry) use ($q) {
                    $qry->whereRaw('UPPER(numero) = UPPER(?)', [$q]);
                    if (ctype_digit($q)) {
                        $qry->orWhere('id', (int) $q);
                    }
                })
                ->with('cliente')
                ->latest('fecha_venta')
                ->limit(30)
                ->get();
        }

        if ($candidatas->isEmpty()) {
            return response()->json(['error' => 'No se encontró una venta completada con ese número.'], 404);
        }

        // Varias ventas con el mismo número: que elija la persona, no el sistema.
        if ($candidatas->count() > 1) {
            return response()->json([
                'coincidencias' => $candidatas->map(fn (Venta $v) => [
                    'id'          => $v->id,
                    'numero'      => $v->numero,
                    'fecha_venta' => $v->fecha_venta->toIso8601String(),
                    'total'       => (float) $v->total,
                    'cliente'     => $v->cliente?->nombre_completo,
                ])->values(),
            ]);
        }

        $venta = $candidatas->first()->load([
            'items.producto',
            'items.productoUnidad.unidadMedida',
            'cliente',
            'local',
            'pagos.metodoPago',
        ]);

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

        // Lo pendiente de entrega no se devuelve (nunca salió del almacén).
        $pendientes = $this->service->pendienteDeEntregaPorItem($venta->id);

        $itemsConDisponibilidad = $venta->items->map(function ($it) use ($devueltos, $pendientes) {
            $devuelto = (float) ($devueltos[$it->id] ?? 0);
            $pendiente = (float) ($pendientes[$it->id] ?? 0);
            $disponible = (float) $it->cantidad - $devuelto - $pendiente;
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
                'cantidad_pendiente_entrega' => $pendiente,
                'cantidad_disponible' => max(0, round($disponible, 4)),
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
                // Para que el formulario calcule lo mismo que el servidor: el
                // descuento global se prorratea y, al crédito, lo devuelto baja
                // primero la deuda.
                'factor_descuento' => Devolucion::factorDescuentoGlobal($venta),
                'es_credito'     => (bool) $venta->es_credito,
                'saldo_pendiente'=> (float) $venta->saldo_pendiente,
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
        } catch (RuntimeException|\LogicException $e) {
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

        try {
            // Bloqueada: un doble clic (o dos admins a la vez) no puede aprobar y
            // completar dos veces la misma devolución —doble restock, doble egreso.
            $devolucion = DB::transaction(function () use ($devolucion, $request, $obs) {
                $dev = Devolucion::whereKey($devolucion->id)->lockForUpdate()->firstOrFail();
                $dev->aprobar($request->user()->id, $obs);
                $dev->refresh()->completar();

                return $dev->fresh();
            });
        } catch (\LogicException $e) {
            return back()->withErrors(['general' => $this->mensajeYaProcesada($devolucion, $e)]);
        }

        // Recién ahora, completada y confirmada, corresponde la nota de crédito.
        $devolucion->loadMissing('venta');
        if ($devolucion->venta) {
            $this->service->encolarNotaCredito($devolucion, $devolucion->venta, $request->user());
        }

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

        try {
            $devolucion = DB::transaction(function () use ($devolucion, $request, $obs) {
                $dev = Devolucion::whereKey($devolucion->id)->lockForUpdate()->firstOrFail();
                $dev->rechazar($request->user()->id, $obs);

                return $dev;
            });
        } catch (\LogicException $e) {
            return back()->withErrors(['general' => $this->mensajeYaProcesada($devolucion, $e)]);
        }

        \App\Services\AuditoriaService::log('devolucion.rechazada', $devolucion, [
            'numero'      => $devolucion->numero,
            'venta_id'    => $devolucion->venta_id,
            'observacion' => $obs,
        ]);

        return redirect()->back()->with('success', 'Devolución rechazada.');
    }

    /**
     * Mensaje para un aprobar/rechazar que llega tarde (doble clic, otra pestaña,
     * otro admin): dice en qué quedó la devolución en vez de un 500.
     */
    private function mensajeYaProcesada(Devolucion $devolucion, \LogicException $e): string
    {
        $estado = Devolucion::whereKey($devolucion->id)->value('estado');

        return match ($estado) {
            'completada' => 'Esta devolución ya fue aprobada y completada.',
            'rechazada'  => 'Esta devolución ya fue rechazada.',
            'anulada'    => 'Esta devolución está anulada.',
            default      => $e->getMessage(),
        };
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
        // Una devolución sin completar (pendiente de aprobación, rechazada,
        // anulada) no tiene nada que acreditar ante SUNAT.
        if (!$devolucion->esCompletada()) {
            return redirect()->back()->with('error',
                'La nota de crédito solo se emite cuando la devolución está completada.');
        }

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
