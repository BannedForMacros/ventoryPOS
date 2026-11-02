<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Conductor;
use App\Models\Entrada;
use App\Models\Modulo;
use App\Models\Proveedor;
use App\Models\RutaEntrega;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Models\Viaje;
use App\Models\ViajeParada;
use App\Rules\NoFutura;
use App\Services\ViajeService;
use App\Support\EnEmpresa;
use App\Support\Flota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Viajes: qué vehículo sale, con qué chofer y qué ventas lleva.
 *
 * Ninguna ruta de aquí mueve stock por su cuenta: entregar una parada usa la
 * misma entrega de Despachos (ver ViajeService).
 */
class ViajeController extends Controller
{
    private const ESTADOS = ['programado', 'en_ruta', 'cerrado', 'anulado'];

    public function __construct(private ViajeService $viajes) {}

    public function index(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);
        $empresaId = $user->empresa_id;

        $fecha      = $request->date('fecha')?->toDateString();
        $estado     = in_array($request->input('estado'), self::ESTADOS, true) ? $request->input('estado') : null;
        $vehiculoId = $request->integer('vehiculo_id') ?: null;
        $buscar     = trim((string) $request->input('buscar', ''));
        // Sin filtros se abre en "hoy y lo que sigue abierto": la agenda del día.
        $agenda     = !$fecha && !$estado && !$vehiculoId && $buscar === '';

        $viajes = Viaje::deEmpresa($empresaId)
            ->with(['vehiculo:id,placa,marca,modelo,capacidad_kg', 'conductor:id,nombres,apellidos', 'paradas.items', 'rutaEntrega:id,nombre,zona'])
            ->when($agenda, fn ($q) => $q->where(fn ($s) => $s->whereDate('fecha', today())->orWhereIn('estado', Viaje::ABIERTOS)))
            ->when($fecha, fn ($q) => $q->whereDate('fecha', $fecha))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($vehiculoId, fn ($q) => $q->where('vehiculo_id', $vehiculoId))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('numero', 'ilike', "%{$buscar}%")
                ->orWhere('destino', 'ilike', "%{$buscar}%")
                ->orWhereHas('paradas.venta', fn ($v) => $v->where('numero', 'ilike', "%{$buscar}%"))))
            // Lo que está en la calle primero, luego lo programado por hora.
            ->orderByRaw("CASE estado WHEN 'en_ruta' THEN 0 WHEN 'programado' THEN 1 WHEN 'cerrado' THEN 2 ELSE 3 END")
            ->orderByDesc('fecha')
            ->orderByRaw('hora_salida ASC NULLS LAST')
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $viajes->through(fn (Viaje $v) => $this->paraLista($v));

        $hoy = Viaje::deEmpresa($empresaId)->whereDate('fecha', today())->where('estado', '<>', 'anulado');

        return Inertia::render('Viajes/Index', [
            'viajes'   => $viajes,
            'filtros'  => ['fecha' => $fecha, 'estado' => $estado, 'vehiculo_id' => $vehiculoId, 'buscar' => $buscar, 'agenda' => $agenda],
            'resumen'  => [
                'en_ruta'     => Viaje::deEmpresa($empresaId)->where('estado', 'en_ruta')->count(),
                'programados' => (clone $hoy)->where('estado', 'programado')->count(),
                'cerrados'    => (clone $hoy)->where('estado', 'cerrado')->count(),
                'por_cargar'  => count($this->viajes->pendientesDisponibles($user, null, '', 500)),
            ],
            'vehiculos' => Vehiculo::deEmpresa($empresaId)->activo()->orderBy('placa')->get(['id', 'placa'])
                ->map(fn ($v) => ['value' => $v->id, 'label' => $v->placa]),
            'puede'    => ['crear' => $user->tienePermiso('viajes', 'crear')],
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);

        return Inertia::render('Viajes/Form', $this->datosFormulario($request, null) + [
            // Desde Despachos: "Cargar en un viaje" llega con el pedido elegido.
            'preseleccion' => $request->integer('anticipo') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);

        $viaje = $this->viajes->guardar(null, $this->validar($request), $user);

        return redirect()->route('viajes.show', $viaje)->with('success', "Viaje {$viaje->numero} programado.");
    }

    public function show(Request $request, Viaje $viaje)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($viaje->empresa_id !== $user->empresa_id, 404);

        $viaje->load([
            'vehiculo', 'conductor', 'ayudantes:id,nombres,apellidos', 'almacen:id,nombre', 'rutaEntrega:id,nombre,zona',
            'proveedor:id,razon_social', 'entrada:id,correlativo,numero_documento', 'user:id,name',
            'paradas.items', 'paradas.guia',
            'paradas.venta:id,numero,cliente_id,cliente_direccion,cliente_telefono,saldo_pendiente,total,estado,observacion',
            'paradas.venta.cliente:id,nombres,apellidos,razon_social,telefono,direccion,es_cliente_general',
            'paradas.entrega:id,numero,fecha',
            'paradas.entrega.items',
        ]);

        return Inertia::render('Viajes/Show', [
            'viaje'    => $this->paraDetalle($viaje),
            'carga'    => $this->viajes->carga($viaje),
            'puede'    => [
                'editar' => $user->tienePermiso('viajes', 'editar'),
                'guia'   => $this->puedeGuia($user),
            ],
        ]);
    }

    public function edit(Request $request, Viaje $viaje)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($viaje->empresa_id !== $user->empresa_id, 404);

        if (!$viaje->abierto()) {
            return redirect()->route('viajes.show', $viaje)->with('error', "El viaje {$viaje->numero} ya no se puede cambiar.");
        }

        return Inertia::render('Viajes/Form', $this->datosFormulario($request, $viaje) + ['preseleccion' => null]);
    }

    public function update(Request $request, Viaje $viaje)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($viaje->empresa_id !== $user->empresa_id, 404);

        $viaje = $this->viajes->guardar($viaje, $this->validar($request), $user);

        return redirect()->route('viajes.show', $viaje)->with('success', "Viaje {$viaje->numero} actualizado.");
    }

    public function iniciar(Request $request, Viaje $viaje)
    {
        Flota::exigir($request->user());
        abort_if($viaje->empresa_id !== $request->user()->empresa_id, 404);

        $this->viajes->iniciar($viaje, $request->user());

        return back()->with('success', "Viaje {$viaje->numero} en ruta.");
    }

    public function entregar(Request $request, ViajeParada $parada)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($parada->viaje?->empresa_id !== $user->empresa_id, 404);

        $data = $request->validate([
            'fecha'            => ['required', 'date', new NoFutura],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
            'observacion'      => ['nullable', 'string', 'max:300'],
        ], [
            'fecha.required'            => 'Indica la fecha de la entrega.',
            'items.required'            => 'Indica qué se entregó.',
            'items.*.cantidad.required' => 'Indica la cantidad entregada (0 si no se entregó).',
            'items.*.cantidad.min'      => 'La cantidad no puede ser negativa.',
        ]);

        $this->viajes->entregarParada($parada, $data, $user);

        return back()->with('success', 'Entrega registrada. La mercadería salió del stock.');
    }

    public function noEntregada(Request $request, ViajeParada $parada)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($parada->viaje?->empresa_id !== $user->empresa_id, 404);

        $data = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Indica por qué no se entregó.']);
        $this->viajes->noEntregada($parada, $data['motivo'], $user);

        return back()->with('success', 'Anotado. El pedido vuelve a quedar por entregar en Despachos.');
    }

    public function cerrar(Request $request, Viaje $viaje)
    {
        Flota::exigir($request->user());
        abort_if($viaje->empresa_id !== $request->user()->empresa_id, 404);

        $this->viajes->cerrar($viaje, $request->user());

        return back()->with('success', "Viaje {$viaje->numero} cerrado.");
    }

    public function anular(Request $request, Viaje $viaje)
    {
        Flota::exigir($request->user());
        abort_if($viaje->empresa_id !== $request->user()->empresa_id, 404);

        $data = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Indica por qué se anula el viaje.']);
        $this->viajes->anular($viaje, $data['motivo'], $request->user());

        return back()->with('success', "Viaje {$viaje->numero} anulado. Lo que llevaba vuelve a quedar por entregar.");
    }

    /** Pedidos por entregar para el buscador del formulario. */
    public function pendientes(Request $request)
    {
        Flota::exigir($request->user());

        return response()->json($this->viajes->pendientesDisponibles(
            $request->user(), $request->integer('viaje') ?: null, trim((string) $request->input('buscar', '')),
        ));
    }

    /**
     * Ventas ya entregadas al venderse que pueden ir en el viaje (un flete, o
     * una venta "puesto en obra" a la que no se le eligió placa en el POS).
     */
    public function ventas(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);
        $buscar = trim((string) $request->input('buscar', ''));
        $viajeId = $request->integer('viaje') ?: null;

        $ventas = Venta::where('empresa_id', $user->empresa_id)
            ->where('estado', 'completada')
            ->where('fecha_venta', '>=', now()->subDays(15)->startOfDay())
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
            ->whereDoesntHave('anticipos', fn ($q) => $q->where('tipo_valorizacion', 'material')->where('estado', 'activo'))
            ->whereNotExists(fn ($q) => $q->from('viaje_paradas as vp')->join('viajes as v', 'v.id', '=', 'vp.viaje_id')
                ->whereColumn('vp.venta_id', 'ventas.id')->where('vp.modo', 'directa')->where('v.estado', '<>', 'anulado')
                ->when($viajeId, fn ($s) => $s->where('v.id', '<>', $viajeId)))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('numero', 'ilike', "%{$buscar}%")
                ->orWhere('cliente_direccion', 'ilike', "%{$buscar}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'ilike', "%{$buscar}%")
                    ->orWhere('nombres', 'ilike', "%{$buscar}%")->orWhere('apellidos', 'ilike', "%{$buscar}%"))))
            ->with(['cliente:id,nombres,apellidos,razon_social,es_cliente_general', 'items:id,venta_id,producto_id,producto_nombre,unidad_nombre,cantidad,factor_conversion', 'items.producto:id,peso_kg'])
            ->orderByDesc('fecha_venta')
            ->limit(25)
            ->get();

        return response()->json($ventas->map(fn (Venta $v) => [
            'id'        => $v->id,
            'numero'    => $v->numero,
            'fecha'     => $v->fecha_venta?->toIso8601String(),
            'cliente'   => ViajeService::nombreCliente($v->cliente),
            'direccion' => $v->cliente_direccion,
            'total'     => (float) $v->total,
            'items'     => $v->items->map(fn ($i) => [
                'producto_id'     => $i->producto_id,
                'factor'          => (float) $i->factor_conversion,
                'producto_nombre' => $i->producto_nombre,
                'unidad_nombre'   => $i->unidad_nombre,
                'cantidad'        => (float) $i->cantidad,
                'peso_kg'         => $i->producto?->peso_kg !== null
                    ? round((float) $i->cantidad * (float) $i->factor_conversion * (float) $i->producto->peso_kg, 3) : null,
            ]),
        ]));
    }

    // ── Interioridades ───────────────────────────────────────────────────────

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo'                              => ['required', Rule::in(Viaje::TIPOS)],
            'fecha'                             => ['required', 'date'],
            'hora_salida'                       => ['nullable', 'date_format:H:i'],
            'vehiculo_id'                       => ['required', 'integer', EnEmpresa::existe('vehiculos')],
            'conductor_id'                      => ['nullable', 'integer', EnEmpresa::existe('conductores')],
            'ayudantes'                         => ['nullable', 'array'],
            'ayudantes.*'                       => ['integer'],
            'almacen_id'                        => ['nullable', 'integer', EnEmpresa::existe('almacenes')],
            'ruta_entrega_id'                   => ['nullable', 'integer', EnEmpresa::existe('rutas_entrega')],
            'origen'                            => ['nullable', 'string', 'max:200'],
            'destino'                           => ['nullable', 'string', 'max:200'],
            'proveedor_id'                      => ['nullable', 'integer', EnEmpresa::existe('proveedores')],
            'entrada_id'                        => ['nullable', 'integer', EnEmpresa::existe('entradas')],
            'observacion'                       => ['nullable', 'string', 'max:1000'],
            'paradas'                           => ['nullable', 'array', 'max:60'],
            'paradas.*.cliente_anticipo_id'     => ['nullable', 'integer'],
            'paradas.*.venta_id'                => ['nullable', 'integer'],
            'paradas.*.items'                   => ['nullable', 'array'],
            'paradas.*.items.*.cliente_anticipo_item_id' => ['required', 'integer'],
            'paradas.*.items.*.cantidad'        => ['required', 'numeric', 'min:0'],
        ], [
            'vehiculo_id.required' => 'Elige el vehículo.',
            'vehiculo_id.exists'   => 'Elige un vehículo de tu empresa.',
            'fecha.required'       => 'Indica la fecha del viaje.',
            'hora_salida.date_format' => 'La hora de salida no es válida.',
            'paradas.max'          => 'Un viaje lleva como máximo 60 paradas.',
            'paradas.*.items.*.cantidad.min' => 'La cantidad no puede ser negativa.',
        ]);
    }

    private function datosFormulario(Request $request, ?Viaje $viaje): array
    {
        $user      = $request->user();
        $empresaId = $user->empresa_id;
        $enRuta    = Viaje::deEmpresa($empresaId)->where('estado', 'en_ruta')
            ->when($viaje, fn ($q) => $q->where('id', '<>', $viaje->id))->pluck('numero', 'vehiculo_id');

        return [
            'viaje'      => $viaje ? $this->paraFormulario($viaje) : null,
            'vehiculos'  => Vehiculo::deEmpresa($empresaId)->activo()->with(['conductor:id,nombres,apellidos', 'capacidades'])->orderBy('placa')->get()
                ->map(fn (Vehiculo $v) => [
                    'id'           => $v->id,
                    'placa'        => $v->placa,
                    'descripcion'  => trim(($v->marca ?? '') . ' ' . ($v->modelo ?? '') . ($v->tipo ? " · {$v->tipo}" : '')),
                    'capacidad_kg' => $v->capacidad_kg !== null ? (float) $v->capacidad_kg : null,
                    'conductor_id' => $v->conductor_id,
                    'conductor'    => $v->conductor?->nombre_completo,
                    'estado'       => $v->estado,
                    'en_ruta'      => $enRuta[$v->id] ?? null,
                    // Límite por volumen: producto_id => unidades máximas.
                    'capacidades'  => (object) $v->capacidades->pluck('max_unidades', 'producto_id')->map(fn ($m) => (float) $m)->all(),
                ]),
            'conductores' => Conductor::deEmpresa($empresaId)->activo()->orderBy('nombres')->get()
                ->map(fn (Conductor $c) => [
                    'id'              => $c->id,
                    'nombre'          => $c->nombre_completo,
                    'tipo'            => $c->tipo,
                    'licencia'        => $c->licencia,
                    'estado_licencia' => $c->estadoLicencia(),
                    'licencia_vence'  => $c->licencia_vence?->toDateString(),
                ]),
            'almacenes'   => Almacen::where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => $a->nombre]),
            'rutas'       => RutaEntrega::deEmpresa($empresaId)->activa()->orderBy('orden')->get(['id', 'nombre', 'zona'])
                ->map(fn ($r) => ['value' => $r->id, 'label' => $r->etiqueta]),
            'proveedores' => Proveedor::where('empresa_id', $empresaId)->where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->razon_social]),
            'entradas'    => Entrada::where('empresa_id', $empresaId)->where('fecha', '>=', now()->subDays(30)->toDateString())
                ->whereIn('estado', ['confirmado', 'en_transito'])
                ->orderByDesc('fecha')->orderByDesc('id')->limit(60)
                ->get(['id', 'correlativo', 'numero_documento', 'proveedor', 'proveedor_id', 'fecha'])
                ->map(fn ($e) => [
                    'value'        => $e->id,
                    'proveedor_id' => $e->proveedor_id,
                    'label'        => trim(($e->correlativo ?? "#{$e->id}") . ($e->numero_documento ? " · {$e->numero_documento}" : '')
                        . ($e->proveedor ? " · {$e->proveedor}" : '') . ' · ' . Carbon::parse($e->fecha)->format('d/m')),
                ]),
            'pendientes'  => $this->viajes->pendientesDisponibles($user, $viaje?->id, '', 60,
                $viaje ? $viaje->paradas()->whereNotNull('cliente_anticipo_id')->pluck('cliente_anticipo_id')->all() : []),
        ];
    }

    private function paraLista(Viaje $v): array
    {
        $carga = $this->viajes->carga($v);
        $pedidos = $v->paradas->where('modo', 'pendiente');

        return [
            'id'          => $v->id,
            'numero'      => $v->numero,
            'tipo'        => $v->tipo,
            'fecha'       => $v->fecha?->toDateString(),
            'hora_salida' => $v->hora_salida ? substr((string) $v->hora_salida, 0, 5) : null,
            'estado'      => $v->estado,
            'placa'       => $v->vehiculo?->placa,
            'vehiculo'    => trim(($v->vehiculo?->marca ?? '') . ' ' . ($v->vehiculo?->modelo ?? '')),
            'chofer'      => $v->conductor?->nombre_completo,
            'ruta'        => $v->rutaEntrega?->etiqueta,
            'destino'     => $v->destino,
            'paradas'     => $v->paradas->count(),
            'entregadas'  => $v->paradas->where('estado', 'entregado')->count(),
            'por_entregar' => $pedidos->where('estado', 'por_entregar')->count(),
            'carga'       => $carga,
        ];
    }

    private function paraFormulario(Viaje $viaje): array
    {
        $viaje->load(['ayudantes:id', 'paradas.items', 'paradas.venta:id,numero,cliente_id,cliente_direccion', 'paradas.venta.cliente:id,nombres,apellidos,razon_social,es_cliente_general']);

        return [
            'id'              => $viaje->id,
            'numero'          => $viaje->numero,
            'estado'          => $viaje->estado,
            'tipo'            => $viaje->tipo,
            'fecha'           => $viaje->fecha?->toDateString(),
            'hora_salida'     => $viaje->hora_salida ? substr((string) $viaje->hora_salida, 0, 5) : '',
            'vehiculo_id'     => $viaje->vehiculo_id,
            'conductor_id'    => $viaje->conductor_id,
            'ayudantes'       => $viaje->ayudantes->pluck('id')->all(),
            'almacen_id'      => $viaje->almacen_id,
            'ruta_entrega_id' => $viaje->ruta_entrega_id,
            'origen'          => $viaje->origen ?? '',
            'destino'         => $viaje->destino ?? '',
            'proveedor_id'    => $viaje->proveedor_id,
            'entrada_id'      => $viaje->entrada_id,
            'observacion'     => $viaje->observacion ?? '',
            // Lo ya resuelto se enseña pero no se edita.
            'resueltas'       => $viaje->paradas->filter(fn ($p) => $p->modo === 'pendiente' && $p->estado !== 'por_entregar')
                ->map(fn ($p) => [
                    'id'      => $p->id,
                    'venta'   => $p->venta?->numero,
                    'cliente' => ViajeService::nombreCliente($p->venta?->cliente),
                    'estado'  => $p->estado,
                ])->values(),
            'paradas'         => $viaje->paradas->filter(fn ($p) => $p->estado === 'por_entregar' || $p->modo === 'directa')
                ->map(fn ($p) => $p->modo === 'directa'
                    ? ['venta_id' => $p->venta_id, 'venta' => $p->venta?->numero, 'cliente' => ViajeService::nombreCliente($p->venta?->cliente),
                       'direccion' => $p->venta?->cliente_direccion, 'items' => $p->items->map(fn ($i) => [
                           'producto_id' => $i->producto_id, 'factor' => (float) $i->factor_conversion,
                           'producto_nombre' => $i->producto_nombre, 'unidad_nombre' => $i->unidad_nombre,
                           'cantidad' => (float) $i->cantidad, 'peso_kg' => $i->peso_kg !== null ? (float) $i->peso_kg : null])]
                    : ['cliente_anticipo_id' => $p->cliente_anticipo_id, 'items' => $p->items->map(fn ($i) => [
                           'cliente_anticipo_item_id' => $i->cliente_anticipo_item_id, 'cantidad' => (float) $i->cantidad])])
                ->values(),
        ];
    }

    private function paraDetalle(Viaje $v): array
    {
        return [
            'id'               => $v->id,
            'numero'           => $v->numero,
            'tipo'             => $v->tipo,
            'estado'           => $v->estado,
            'fecha'            => $v->fecha?->toDateString(),
            'hora_salida'      => $v->hora_salida ? substr((string) $v->hora_salida, 0, 5) : null,
            'salida_at'        => $v->salida_at?->toIso8601String(),
            'cierre_at'        => $v->cierre_at?->toIso8601String(),
            'origen'           => $v->origen,
            'destino'          => $v->destino,
            'observacion'      => $v->observacion,
            'motivo_anulacion' => $v->motivo_anulacion,
            'almacen'          => $v->almacen?->nombre,
            'ruta'             => $v->rutaEntrega?->etiqueta,
            'proveedor'        => $v->proveedor?->razon_social,
            'entrada'          => $v->entrada ? ['id' => $v->entrada->id, 'numero' => $v->entrada->correlativo ?? $v->entrada->numero_documento] : null,
            'registrado_por'   => $v->user?->name,
            'vehiculo'         => $v->vehiculo ? [
                'placa'        => $v->vehiculo->placa,
                'descripcion'  => trim(($v->vehiculo->marca ?? '') . ' ' . ($v->vehiculo->modelo ?? '') . ($v->vehiculo->tipo ? " · {$v->vehiculo->tipo}" : '')),
                'estado'       => $v->vehiculo->estado,
            ] : null,
            'chofer'           => $v->conductor ? [
                'nombre'          => $v->conductor->nombre_completo,
                'documento'       => $v->conductor->numero_documento,
                'licencia'        => trim(($v->conductor->licencia ?? '') . ($v->conductor->licencia_categoria ? " ({$v->conductor->licencia_categoria})" : '')),
                'telefono'        => $v->conductor->telefono,
                'estado_licencia' => $v->conductor->estadoLicencia(),
            ] : null,
            'ayudantes'        => $v->ayudantes->map(fn ($a) => $a->nombre_completo)->all(),
            'paradas'          => $v->paradas->map(function (ViajeParada $p) {
                $venta     = $p->venta;
                $entregado = $p->entrega?->items->pluck('cantidad', 'cliente_anticipo_item_id') ?? collect();

                return [
                    'id'        => $p->id,
                    'modo'      => $p->modo,
                    'estado'    => $venta?->estado === 'anulada' ? 'anulada' : $p->estado,
                    'motivo'    => $p->motivo,
                    'entregado_at' => $p->entregado_at?->toIso8601String(),
                    'entrega'   => $p->entrega ? ['numero' => $p->entrega->numero] : null,
                    'venta'     => $venta ? ['id' => $venta->id, 'numero' => $venta->numero, 'saldo' => (float) ($venta->saldo_pendiente ?? 0), 'observacion' => $venta->observacion] : null,
                    'cliente'   => ViajeService::nombreCliente($venta?->cliente),
                    'cliente_id' => $venta?->cliente_id,
                    'direccion' => $venta?->cliente_direccion ?? $venta?->cliente?->direccion,
                    'telefono'  => $venta?->cliente_telefono ?? $venta?->cliente?->telefono,
                    'peso_kg'   => $p->pesoKg(),
                    'guia'      => $p->guia ? ['id' => $p->guia->id, 'numero' => $p->guia->numero, 'estado' => $p->guia->estado] : null,
                    'items'     => $p->items->map(fn ($i) => [
                        'id'              => $i->id,
                        'producto_nombre' => $i->producto_nombre,
                        'unidad_nombre'   => $i->unidad_nombre,
                        'cantidad'        => (float) $i->cantidad,
                        'entregado'       => $i->cliente_anticipo_item_id && $entregado->has($i->cliente_anticipo_item_id)
                            ? (float) $entregado[$i->cliente_anticipo_item_id] : null,
                        'peso_kg'         => $i->peso_kg !== null ? (float) $i->peso_kg : null,
                    ]),
                ];
            })->values(),
        ];
    }

    /** La guía solo se ofrece si el módulo de guías está encendido y el usuario puede emitir. */
    private function puedeGuia($user): bool
    {
        return (bool) Modulo::where('slug', 'guias')->value('activo') && $user->tienePermiso('guias', 'crear');
    }
}
