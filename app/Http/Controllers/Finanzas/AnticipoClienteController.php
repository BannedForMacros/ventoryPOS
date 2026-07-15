<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\Cuenta;
use App\Services\TicketPrintService;
use Illuminate\Support\Facades\Storage;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Stock;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionOperacionService;
use App\Services\LocalScopeService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Anticipos de clientes: dinero recibido por adelantado a cambio de
 * mercadería futura ("CLIENTES ANTICIPOS" del balance, pasivo).
 *
 * Modalidad 'material': el anticipo compromete N unidades de un producto;
 * el pasivo se valoriza a precio de venta ACTUAL (precio del día).
 *
 * Anticipos con venta_id (pendiente por entregar del POS): multi-producto
 * (detalle en cliente_anticipo_items). El stock de lo pendiente NO salió al
 * vender, así que cada entrega registrada aquí SÍ descuenta stock.
 */
class AnticipoClienteController extends Controller
{
    public function __construct(
        private TesoreriaService $tesoreria,
        private LocalScopeService $scope,
        private ConfiguracionOperacionService $config,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ClienteAnticipo::deEmpresa($user->empresa_id)
            ->with([
                'cliente', 'producto', 'metodoPago', 'cuenta', 'venta:id,numero',
                'items.producto:id,nombre,precio_venta', 'items.unidad:id,precio_venta',
                'aplicaciones.venta', 'aplicaciones.user', 'aplicaciones.items.item:id,producto_nombre,unidad_nombre',
            ])
            ->when($request->input('cliente_id'), fn ($q, $v) => $q->where('cliente_id', $v));

        if ($request->input('estado', 'activos') === 'activos') {
            $query->activo();
        }

        $anticipos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString()
            // El pasivo a hoy se calcula en el backend (multi-item o clásico)
            // para que el frontend no tenga que replicar la valorización.
            ->through(function (ClienteAnticipo $a) {
                $a->setAttribute('valor_pasivo_hoy', $a->estado === 'activo' ? $a->valorPasivoHoy() : 0.0);
                return $a;
            });

        // Total del pasivo a precio del día (lo que mostrará el balance).
        $totalPasivo = ClienteAnticipo::deEmpresa($user->empresa_id)->activo()
            ->with(['producto', 'items.unidad'])->get()
            ->sum(fn (ClienteAnticipo $a) => $a->valorPasivoHoy());

        return Inertia::render('Finanzas/Anticipos', [
            'anticipos'   => $anticipos,
            'totalPasivo' => round((float) $totalPasivo, 2),
            'estado'      => $request->input('estado', 'activos'),
            'clientes'    => Cliente::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->where('es_cliente_general', false)
                ->orderBy('nombres')->get(['id', 'nombres', 'apellidos', 'razon_social']),
            'productos'   => Producto::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->orderBy('nombre')->get(['id', 'nombre', 'precio_venta']),
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'cliente_id'        => ['required', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $user->empresa_id)->where('activo', true)],
            'fecha'             => ['required', 'date'],
            'monto'             => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id'    => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'         => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'tipo_valorizacion' => ['required', Rule::in(['monto', 'material'])],
            'producto_id'       => ['required_if:tipo_valorizacion,material', 'nullable', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $user->empresa_id)],
            'cantidad'          => ['required_if:tipo_valorizacion,material', 'nullable', 'numeric', 'min:0.0001'],
            'observacion'       => ['nullable', 'string', 'max:500'],
        ]);

        $anticipo = DB::transaction(function () use ($data, $user) {
            $anticipo = ClienteAnticipo::create($data + [
                'empresa_id'         => $user->empresa_id,
                'user_id'            => $user->id,
                'saldo'              => $data['monto'],
                'cantidad_pendiente' => $data['tipo_valorizacion'] === 'material' ? $data['cantidad'] : null,
                'estado'             => 'activo',
            ]);

            // F7 — El dinero del anticipo ingresa a tesorería.
            $anticipo->load('cliente');
            $nombre = $anticipo->cliente?->razon_social
                ?? trim(($anticipo->cliente?->nombres ?? '') . ' ' . ($anticipo->cliente?->apellidos ?? ''));
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                $user,
                $data['fecha'],
                'ingreso',
                (float) $data['monto'],
                "Anticipo de cliente — {$nombre}",
                'cliente_anticipo',
                $anticipo->id,
            );

            return $anticipo;
        });

        AuditoriaService::log('anticipo_cliente.creado', $anticipo, [
            'cliente_id' => $anticipo->cliente_id,
            'monto'      => (float) $anticipo->monto,
            'tipo'       => $anticipo->tipo_valorizacion,
        ], $user);

        return back()->with('success', 'Anticipo registrado correctamente.');
    }

    /**
     * Aplica el anticipo: el cliente retiró mercadería (despacho) y el pasivo
     * baja. Puede vincularse a una venta existente.
     *
     * Si la entrega EXCEDE lo anticipado (más unidades que las pendientes, o
     * un valor mayor al saldo), NO se registra a ciegas: se exige confirmación
     * (`exceso_a_cxc`) y el excedente se convierte en una DEUDA POR COBRAR a
     * nombre del cliente (valorizada a precio del día en modalidad material).
     * Sin confirmación, la aplicación se rechaza con el detalle del exceso.
     */
    public function aplicar(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_unless($anticipo->estado === 'activo', 422, 'El anticipo no está activo.');

        // Anticipo multi-producto (pendiente por entregar del POS): entrega
        // parcial por ítem con fecha propia. Flujo separado.
        if ($anticipo->items()->exists()) {
            return $this->aplicarMultiItem($request, $anticipo);
        }

        $esMaterial = $anticipo->tipo_valorizacion === 'material';

        $data = $request->validate([
            'fecha'        => ['required', 'date'],
            // En material el monto se CALCULA (prorrata del anticipo), no se digita.
            'monto'        => [$esMaterial ? 'nullable' : 'required', 'numeric', 'min:0.01'],
            'cantidad'     => [$esMaterial ? 'required' : 'nullable', 'numeric', 'min:0.0001'],
            'venta_id'     => ['nullable', 'integer', Rule::exists('ventas', 'id')->where('empresa_id', $user->empresa_id)],
            'observacion'  => ['nullable', 'string', 'max:500'],
            'exceso_a_cxc' => ['nullable', 'boolean'],
        ]);

        // ── Calcular cobertura y excedente ──────────────────────────────
        if ($esMaterial) {
            $anticipo->loadMissing('producto');
            $pendiente     = (float) $anticipo->cantidad_pendiente;
            $entregada     = (float) $data['cantidad'];
            $cantCubierta  = min($entregada, $pendiente);
            $excesoCant    = round(max(0, $entregada - $pendiente), 4);
            $precioDia     = (float) ($anticipo->producto?->precio_venta ?? 0);
            $excesoMonto   = round($excesoCant * $precioDia, 2);
            // El saldo (dinero) baja a prorrata del anticipo original:
            // (monto / cantidad) = soles anticipados por unidad.
            $montoAplicado = (float) $anticipo->cantidad > 0
                ? min(round($cantCubierta * ((float) $anticipo->monto / (float) $anticipo->cantidad), 2), (float) $anticipo->saldo)
                : (float) $anticipo->saldo;
        } else {
            $valorEntrega  = (float) $data['monto'];
            $saldo         = (float) $anticipo->saldo;
            $montoAplicado = min($valorEntrega, $saldo);
            $cantCubierta  = null;
            $excesoCant    = 0.0;
            $excesoMonto   = round(max(0, $valorEntrega - $saldo), 2);
        }

        // Exceso sin confirmación → se pregunta, no se registra.
        if ($excesoMonto > 0.009 && !($data['exceso_a_cxc'] ?? false)) {
            $detalle = $esMaterial
                ? "Estás entregando {$excesoCant} und más de lo anticipado (S/ " . number_format($excesoMonto, 2) . ' al precio de hoy).'
                : 'La entrega excede el saldo del anticipo por S/ ' . number_format($excesoMonto, 2) . '.';
            throw ValidationException::withMessages([
                'exceso' => $detalle . ' Confirma si el excedente se registra como cuenta por cobrar del cliente.',
            ]);
        }

        DB::transaction(function () use ($anticipo, $user, $data, $esMaterial, $cantCubierta, $excesoCant, $excesoMonto, $montoAplicado) {
            $obs = $data['observacion'] ?? null;
            if ($excesoMonto > 0.009) {
                $obs = trim(($obs ? $obs . ' · ' : '')
                    . 'Excedente a CxC: '
                    . ($esMaterial ? "{$excesoCant} und × precio del día = " : '')
                    . 'S/ ' . number_format($excesoMonto, 2));
            }

            $anticipo->aplicaciones()->create([
                'empresa_id'  => $anticipo->empresa_id,
                'numero'      => \App\Models\ClienteAnticipoAplicacion::generarNumero($anticipo->empresa_id),
                'user_id'     => $user->id,
                'fecha'       => $data['fecha'],
                'monto'       => $montoAplicado,
                'cantidad'    => $cantCubierta,
                'venta_id'    => $data['venta_id'] ?? null,
                'observacion' => $obs,
            ]);

            $nuevoSaldo    = round((float) $anticipo->saldo - $montoAplicado, 2);
            $nuevaCantidad = $anticipo->cantidad_pendiente !== null && $cantCubierta !== null
                ? round((float) $anticipo->cantidad_pendiente - $cantCubierta, 4)
                : $anticipo->cantidad_pendiente;

            $agotado = $esMaterial
                ? ($nuevaCantidad !== null && $nuevaCantidad <= 0.0001)
                : ($nuevoSaldo <= 0.01);

            $anticipo->update([
                'saldo'              => max(0, $nuevoSaldo),
                'cantidad_pendiente' => $nuevaCantidad !== null ? max(0, $nuevaCantidad) : null,
                'estado'             => $agotado ? 'aplicado' : 'activo',
            ]);

            // ── Excedente confirmado → deuda por cobrar del cliente ─────
            // (mismo patrón que "JHON ASTONITAS" del Excel: línea a favor en
            // el balance). NO mueve tesorería: salió mercadería, no dinero.
            if ($excesoMonto > 0.009) {
                $anticipo->loadMissing('cliente');
                $nombre = $anticipo->cliente?->razon_social
                    ?? trim(($anticipo->cliente?->nombres ?? '') . ' ' . ($anticipo->cliente?->apellidos ?? ''));

                \App\Models\Deuda::create([
                    'empresa_id'     => $user->empresa_id,
                    'user_id'        => $user->id,
                    'direccion'      => 'por_cobrar',
                    'tipo'           => 'personal',
                    'nombre'         => mb_substr("{$nombre} — excedente despacho anticipo #{$anticipo->id}", 0, 150),
                    'monto_original' => $excesoMonto,
                    'saldo'          => $excesoMonto,
                    'fecha_inicio'   => $data['fecha'],
                    'estado'         => 'activa',
                    'observacion'    => $esMaterial
                        ? "Despacho de {$excesoCant} und por encima de lo anticipado, valorizado a precio del día."
                        : 'Entrega por encima del saldo del anticipo.',
                ]);
            }

            AuditoriaService::log('anticipo_cliente.aplicado', $anticipo, [
                'monto'        => $montoAplicado,
                'cantidad'     => $cantCubierta,
                'exceso_monto' => $excesoMonto,
                'exceso_cant'  => $excesoCant,
                'saldo'        => (float) $anticipo->saldo,
            ], $user);
        });

        return back()->with('success', $excesoMonto > 0.009
            ? 'Despacho registrado. El excedente de S/ ' . number_format($excesoMonto, 2) . ' quedó como cuenta por cobrar del cliente.'
            : 'Aplicación del anticipo registrada.');
    }

    /**
     * Registra una entrega (total o parcial) de un anticipo MULTI-PRODUCTO
     * creado por el POS ("pendiente por entregar"). El usuario elige cuánto
     * entrega de cada ítem ("solo te doy tanto de esto, lo demás queda") y la
     * fecha de esa entrega. Como este stock NO salió al vender, aquí SÍ se
     * descuenta del almacén del local de la venta.
     */
    private function aplicarMultiItem(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();

        $data = $request->validate([
            'fecha'            => ['required', 'date'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
            'observacion'      => ['nullable', 'string', 'max:500'],
        ]);

        $anticipo->load('items.producto', 'venta.local');
        $itemsAnticipo = $anticipo->items->keyBy('id');

        // ── Validar entregas contra lo pendiente de cada ítem ───────────
        $entregas = []; // [item => modelo, cantidad => float]
        foreach ($data['items'] as $idx => $linea) {
            $item = $itemsAnticipo->get((int) $linea['id']);
            if (!$item) {
                throw ValidationException::withMessages([
                    "items.{$idx}.id" => 'El ítem no pertenece a este anticipo.',
                ]);
            }

            $cantidad  = round((float) $linea['cantidad'], 4);
            $pendiente = (float) $item->cantidad_pendiente;
            if ($cantidad <= 0.00009) continue; // "lo demás lo dejamos"

            if ($cantidad > $pendiente + 0.00009) {
                throw ValidationException::withMessages([
                    "items.{$idx}.cantidad" => "De «{$item->producto_nombre}» solo quedan pendientes " . rtrim(rtrim(number_format($pendiente, 4, '.', ''), '0'), '.') . ' por entregar.',
                ]);
            }

            $entregas[] = ['item' => $item, 'cantidad' => $cantidad];
        }

        if (empty($entregas)) {
            throw ValidationException::withMessages([
                'items' => 'Indica la cantidad a entregar de al menos un producto.',
            ]);
        }

        // El saldo (dinero) baja al valor PAGADO de lo entregado (precio
        // congelado de la venta), capado al saldo restante.
        $montoAplicado = min(
            round(collect($entregas)->sum(fn ($e) => $e['cantidad'] * (float) $e['item']->precio_unitario), 2),
            (float) $anticipo->saldo,
        );
        $totalUnidades = round(collect($entregas)->sum(fn ($e) => $e['cantidad']), 4);

        DB::transaction(function () use ($anticipo, $user, $data, $entregas, $montoAplicado, $totalUnidades) {
            $aplicacion = $anticipo->aplicaciones()->create([
                'empresa_id'  => $anticipo->empresa_id,
                'numero'      => \App\Models\ClienteAnticipoAplicacion::generarNumero($anticipo->empresa_id),
                'user_id'     => $user->id,
                'fecha'       => $data['fecha'],
                'monto'       => $montoAplicado,
                'cantidad'    => $totalUnidades,
                'venta_id'    => $anticipo->venta_id,
                'observacion' => $data['observacion'] ?? null,
            ]);

            // ── Stock: lo pendiente nunca salió del almacén; sale AHORA ──
            // (solo anticipos nacidos de una venta POS). Se usa el almacén
            // del local de la venta, igual que anular/editar venta.
            $almacen = null;
            $permitirNegativo = $this->config->permiteStockNegativo($user->empresa_id);
            if ($anticipo->venta_id && $anticipo->venta) {
                $almacen = $this->scope->almacenVentasDeLocal($anticipo->empresa_id, $anticipo->venta->local_id)
                    ?? abort(422, 'No se encontró un almacén de ventas para el local de la venta original.');
            }

            foreach ($entregas as $e) {
                $item = $e['item'];

                $aplicacion->items()->create([
                    'cliente_anticipo_item_id' => $item->id,
                    'cantidad'                 => $e['cantidad'],
                ]);

                $item->update([
                    'cantidad_pendiente' => max(0, round((float) $item->cantidad_pendiente - $e['cantidad'], 4)),
                ]);

                if ($almacen && $item->producto
                    && $this->config->deboDescontarStock($item->producto, $anticipo->venta->local)) {
                    $base = round($e['cantidad'] * (float) $item->factor_conversion, 4);
                    if ($base > 0.00009) {
                        Stock::ajustar($almacen->id, $item->producto_id, -$base, 0, $permitirNegativo, contexto: [
                            'tipo'            => 'entrega_pendiente',
                            'referencia_tipo' => 'venta',
                            'referencia_id'   => $anticipo->venta_id ?? optional($anticipo->venta)->id,
                            'fecha'           => now(),
                            'user_id'         => optional(auth()->user())->id,
                            'empresa_id'      => $almacen->empresa_id,
                        ]);
                    }
                }
            }

            // Estado: aplicado cuando ya no queda nada por entregar.
            $anticipo->load('items');
            $agotado = $anticipo->items->every(fn ($i) => (float) $i->cantidad_pendiente <= 0.0001);

            $anticipo->update([
                'saldo'  => max(0, round((float) $anticipo->saldo - $montoAplicado, 2)),
                'estado' => $agotado ? 'aplicado' : 'activo',
            ]);

            AuditoriaService::log('anticipo_cliente.aplicado', $anticipo, [
                'monto'    => $montoAplicado,
                'cantidad' => $totalUnidades,
                'items'    => collect($entregas)->map(fn ($e) => [
                    'producto' => $e['item']->producto_nombre,
                    'cantidad' => $e['cantidad'],
                ])->all(),
                'fecha'    => $data['fecha'],
                'saldo'    => (float) $anticipo->saldo,
            ], $user);
        });

        return back()->with('success', 'Entrega registrada: el stock entregado salió del almacén y el pendiente se actualizó.');
    }

    /**
     * Devuelve el dinero al cliente (o anula un registro erróneo).
     */
    public function anular(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_unless($anticipo->estado === 'activo', 422, 'El anticipo no está activo.');

        $data = $request->validate([
            'accion' => ['required', Rule::in(['devuelto', 'anulado'])],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        // Un pendiente nacido de una venta POS no se "anula" aquí: su dinero
        // entró con la venta, así que revertirlo exige anular LA VENTA (eso
        // ajusta stock y tesorería juntos). Sí se permite 'devuelto' (se le
        // devolvió al cliente el dinero de lo no entregado).
        if ($data['accion'] === 'anulado' && $anticipo->venta_id) {
            throw ValidationException::withMessages([
                'accion' => 'Este pendiente proviene de una venta del POS. Para revertirlo, anula la venta '
                    . ($anticipo->venta?->numero ? "({$anticipo->venta->numero}) " : '')
                    . 'desde el historial de ventas.',
            ]);
        }

        DB::transaction(function () use ($anticipo, $user, $data) {
            $anticipo->update(['estado' => $data['accion']]);

            // F7 — Tesorería: si se devuelve el dinero, egreso por el saldo
            // restante; si fue un registro erróneo, se revierte el ingreso.
            if ($data['accion'] === 'devuelto') {
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $anticipo->cuenta_id,
                    $user,
                    now()->toDateString(),
                    'egreso',
                    (float) $anticipo->saldo,
                    "Devolución de anticipo #{$anticipo->id}: {$data['motivo']}",
                    'cliente_anticipo_devolucion',
                    $anticipo->id,
                );
            } else {
                $this->tesoreria->revertir('cliente_anticipo', $anticipo->id);
            }
        });

        AuditoriaService::log('anticipo_cliente.' . $data['accion'], $anticipo, [
            'motivo' => $data['motivo'],
            'saldo'  => (float) $anticipo->saldo,
        ], $user);

        return back()->with('success', $data['accion'] === 'devuelto' ? 'Anticipo marcado como devuelto.' : 'Anticipo anulado.');
    }

    /**
     * Reactiva un anticipo cerrado por error:
     *  - anulado  → vuelve a activo re-asentando el ingreso original.
     *  - devuelto → vuelve a activo revirtiendo el egreso de la devolución.
     * Los pendientes del POS (venta_id) no se reactivan aquí: nacen y mueren
     * con su venta.
     */
    public function reactivar(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_unless(in_array($anticipo->estado, ['anulado', 'devuelto'], true), 422, 'Solo se reactivan anticipos anulados o devueltos.');

        if ($anticipo->venta_id) {
            throw ValidationException::withMessages([
                'anticipo' => 'Este pendiente proviene de una venta del POS: se maneja desde la venta (regístrala de nuevo si la anulaste).',
            ]);
        }

        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);

        $estadoPrevio = $anticipo->estado;

        DB::transaction(function () use ($anticipo, $user, $estadoPrevio) {
            if ($estadoPrevio === 'anulado') {
                // El ingreso original fue revertido al anular: re-asentarlo.
                $anticipo->load('cliente');
                $nombre = $anticipo->cliente?->razon_social
                    ?? trim(($anticipo->cliente?->nombres ?? '') . ' ' . ($anticipo->cliente?->apellidos ?? ''));
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $anticipo->cuenta_id ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $anticipo->metodo_pago_id),
                    $user,
                    $anticipo->fecha->toDateString(),
                    'ingreso',
                    (float) $anticipo->monto,
                    "Anticipo de cliente — {$nombre} [reactivado]",
                    'cliente_anticipo',
                    $anticipo->id,
                );
            } else {
                // La devolución generó un egreso: revertirlo.
                $this->tesoreria->revertir('cliente_anticipo_devolucion', $anticipo->id);
            }

            $agotado = $anticipo->tipo_valorizacion === 'material'
                ? ($anticipo->cantidad_pendiente !== null && (float) $anticipo->cantidad_pendiente <= 0.0001)
                : ((float) $anticipo->saldo <= 0.01);
            $anticipo->update(['estado' => $agotado ? 'aplicado' : 'activo']);
        });

        AuditoriaService::log('anticipo_cliente.reactivado', $anticipo, [
            'estado_previo' => $estadoPrevio,
            'motivo'        => $data['motivo'],
            'saldo'         => (float) $anticipo->saldo,
        ], $user);

        return back()->with('success', 'Anticipo reactivado: tesorería quedó consistente.');
    }

    /** Payload JSON del ticket de una ENTREGA de anticipo (para el agente). */
    public function ticket(Request $request, ClienteAnticipoAplicacion $entrega)
    {
        abort_if($entrega->empresa_id !== $request->user()->empresa_id, 403);

        return response()->json(app(TicketPrintService::class)->payloadDeEntregaAnticipo($entrega));
    }

    /** Documento A4 imprimible (HTML → PDF) de una ENTREGA de anticipo. */
    public function documento(Request $request, ClienteAnticipoAplicacion $entrega)
    {
        abort_if($entrega->empresa_id !== $request->user()->empresa_id, 403);

        $entrega->loadMissing(['anticipo.empresa', 'anticipo.cliente', 'anticipo.venta', 'user', 'items.item']);
        $empresa = $entrega->anticipo?->empresa;

        // Logo incrustado base64 (no depende de storage:link).
        $logoData = null;
        if ($empresa?->logo && Storage::disk('public')->exists($empresa->logo)) {
            $ext  = strtolower(pathinfo($empresa->logo, PATHINFO_EXTENSION));
            $mime = match ($ext) { 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg' };
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($empresa->logo));
        }

        return view('entrega-anticipo', [
            'entrega' => $entrega,
            'empresa' => $empresa,
            'cliente' => $entrega->anticipo?->cliente,
            'logoUrl' => $logoData,
        ]);
    }
}
