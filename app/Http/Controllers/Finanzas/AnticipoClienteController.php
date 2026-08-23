<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\ClienteAnticipoCancelacion;
use App\Models\ClienteAnticipoItem;
use App\Models\Cuenta;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\TicketPrintService;
use Illuminate\Support\Facades\Storage;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\Stock;
use App\Models\Turno;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionOperacionService;
use App\Services\LocalScopeService;
use App\Services\TesoreriaService;
use App\Support\AfectaCaja;
use App\Support\ExigeCuentaDePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
    use ExigeCuentaDePago;

    public function __construct(
        private TesoreriaService $tesoreria,
        private LocalScopeService $scope,
        private ConfiguracionOperacionService $config,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Garantiza el Cliente General ("Clientes varios") para poder registrar
        // depósitos sin dueño identificado (idempotente, sin migración).
        Cliente::asegurarGeneralDeEmpresa($user->empresa_id);

        $query = ClienteAnticipo::deEmpresa($user->empresa_id)
            ->with([
                'cliente', 'producto', 'metodoPago', 'cuenta', 'venta:id,numero',
                'items.producto:id,nombre,precio_venta', 'items.unidad:id,precio_venta',
                'items.cancelaciones',
                'aplicaciones.venta', 'aplicaciones.user', 'aplicaciones.items.item:id,producto_nombre,unidad_nombre,cantidad_pendiente',
                'aplicaciones.metodoPago:id,nombre', 'aplicaciones.cuenta:id,nombre',
                'cancelaciones.turno:id,fecha_apertura', 'cancelaciones.caja:id,nombre',
                'cancelaciones.metodoPago:id,nombre', 'cancelaciones.cuenta:id,nombre',
            ])
            ->when($request->input('cliente_id'), fn ($q, $v) => $q->where('cliente_id', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('cliente', fn ($c) => $c
                        ->where('nombres', 'ilike', "%{$t}%")
                        ->orWhere('apellidos', 'ilike', "%{$t}%")
                        ->orWhere('razon_social', 'ilike', "%{$t}%")
                        ->orWhere('numero_documento', 'ilike', "%{$t}%"))
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'ilike', "%{$t}%")));
            });

        // Filtro de estado: 'activos' (default), un estado puntual
        // (aplicado/anulado/devuelto) o 'todos'.
        $estado = $request->input('estado', 'activos');
        if ($estado === 'activos') {
            $query->activo();
        } elseif (in_array($estado, ['aplicado', 'anulado', 'devuelto'], true)) {
            $query->where('estado', $estado);
        }

        $anticipos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString()
            // El pasivo a hoy se calcula en el backend (multi-item o clásico)
            // para que el frontend no tenga que replicar la valorización.
            ->through(function (ClienteAnticipo $a) {
                $a->setAttribute('valor_pasivo', $a->estado === 'activo' ? $a->valorPasivo() : 0.0);
                return $a;
            });

        // Total del pasivo a precio del día (lo que mostrará el balance).
        $activosCol = ClienteAnticipo::deEmpresa($user->empresa_id)->activo()
            ->with(['producto', 'items.unidad'])->get();
        $totalPasivo = $activosCol->sum(fn (ClienteAnticipo $a) => $a->valorPasivo());

        // KPIs de cabecera (sobre los anticipos ACTIVOS, independiente del filtro)
        $kpis = [
            'activos'  => $activosCol->count(),
            'clientes' => $activosCol->pluck('cliente_id')->unique()->count(),
            'material' => round((float) $activosCol->where('tipo_valorizacion', 'material')
                ->sum(fn (ClienteAnticipo $a) => $a->valorPasivo()), 2),
            'dinero'   => round((float) $activosCol->where('tipo_valorizacion', 'monto')->sum('saldo'), 2),
        ];

        return Inertia::render('Finanzas/Anticipos', [
            'anticipos'   => $anticipos,
            'totalPasivo' => round((float) $totalPasivo, 2),
            'kpis'        => $kpis,
            'estado'      => $request->input('estado', 'activos'),
            'buscar'      => $request->input('buscar', ''),
            // Incluye "Clientes varios" (cliente general) de primero, para
            // registrar depósitos sin cliente identificado — mismo patrón que
            // Cotizaciones.
            'clientes'    => Cliente::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->orderByDesc('es_cliente_general')
                ->orderBy('nombres')->get(['id', 'nombres', 'apellidos', 'razon_social', 'es_cliente_general']),
            'productos'   => Producto::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->orderBy('nombre')->get(['id', 'nombre', 'precio_venta']),
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
            // "Afecta caja a:" — turnos para elegir a qué caja entra el dinero del
            // anticipo (los de hoy o abiertos ahora). null = no afecta ninguna caja.
            'turnos'      => Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where(fn ($q) => $q->whereDate('fecha_apertura', now()->toDateString())->orWhere('estado', 'abierto'))
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
            // Turno sugerido por defecto: el propio abierto del usuario, o el único
            // abierto en su ámbito (misma auto-resolución que usa store()).
            'turnoActivoId' => $this->turnoSugerido($user),
            // Permiso de edición (finanzas.anticipos → editar). Habilita editar/
            // anular entregas de dinero desde la UI; el backend lo re-verifica por
            // middleware. Configurable por rol, no hardcodeado.
            'puede' => [
                'editar' => $user->tienePermiso('finanzas.anticipos', 'editar'),
            ],
        ]);
    }

    /**
     * Turno al que se imputaría el dinero del anticipo por defecto: 1) el turno
     * propio abierto del usuario; 2) si no tiene, el ÚNICO turno abierto en su
     * ámbito; 3) ninguno. Mismo patrón que Cuentas por Cobrar.
     */
    private function turnoSugerido($user): ?int
    {
        $turnoId = Turno::turnoActivoDelUsuario($user->id)?->id;
        if (!$turnoId) {
            $abiertos = Turno::deEmpresa($user->empresa_id)->where('estado', 'abierto')
                ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
                ->pluck('id');
            $turnoId = $abiertos->count() === 1 ? $abiertos->first() : null;
        }

        return $turnoId;
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'cliente_id'        => ['required', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $user->empresa_id)->where('activo', true)],
            'fecha'             => ['required', 'date'],
            'monto'             => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id'    => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'         => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'tipo_valorizacion' => ['required', Rule::in(['monto', 'material'])],
            'producto_id'       => ['required_if:tipo_valorizacion,material', 'nullable', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $user->empresa_id)],
            'cantidad'          => ['required_if:tipo_valorizacion,material', 'nullable', 'numeric', 'min:0.0001'],
            'observacion'       => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno de cuya caja entra el dinero. null = "Sin turno".
            'turno_id'          => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        // Turno al que se imputa el anticipo (si es efectivo, suma a esa caja):
        // si el front manda 'turno_id' (aunque sea null = "Sin turno"), se respeta
        // (gateado por config, módulo 'anticipos', modo libre); si NO lo manda
        // (llamadores viejos), se auto-resuelve.
        $turnoId = $request->has('turno_id')
            ? AfectaCaja::resolverTurno($user, 'anticipos', $data['turno_id'] ?? null, 'libre')
            : $this->turnoSugerido($user);

        $anticipo = DB::transaction(function () use ($data, $user, $turnoId) {
            $anticipo = ClienteAnticipo::create($data + [
                'empresa_id'         => $user->empresa_id,
                'user_id'            => $user->id,
                'saldo'              => $data['monto'],
                'cantidad_pendiente' => $data['tipo_valorizacion'] === 'material' ? $data['cantidad'] : null,
                'estado'             => 'activo',
                'turno_id'           => $turnoId,
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
     * Edita un anticipo EN DINERO: cliente, fecha, monto, método/cuenta, el
     * turno al que afecta caja y la observación. Reasienta tesorería (revierte
     * el ingreso original y lo vuelve a registrar con los datos nuevos).
     *
     * Solo se permite en anticipos "por dinero" (tipo 'monto'), activos, que
     * NO provienen de una venta del POS y que aún NO tienen aplicaciones
     * (nada entregado/usado): así el saldo sigue igual al monto y editar el
     * importe no descuadra despachos ya hechos. Los de material/POS o ya
     * aplicados se ajustan anulando/reactivando o desde su venta.
     */
    public function update(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);

        if ($anticipo->tipo_valorizacion !== 'monto' || $anticipo->venta_id || $anticipo->items()->exists()) {
            throw ValidationException::withMessages([
                'anticipo' => 'Solo se pueden editar anticipos en dinero. Los de material o los pendientes por entregar de una venta se ajustan desde su venta o anulándolos.',
            ]);
        }
        abort_unless($anticipo->estado === 'activo', 422, 'Solo se editan anticipos activos.');
        if ($anticipo->aplicaciones()->exists()) {
            throw ValidationException::withMessages([
                'anticipo' => 'Este anticipo ya tiene entregas/aplicaciones registradas. Anúlalo y regístralo de nuevo si necesitas corregirlo.',
            ]);
        }

        $data = $request->validate([
            'cliente_id'     => ['required', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $user->empresa_id)->where('activo', true)],
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'observacion'    => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno de cuya caja entra el dinero. null = "Sin turno".
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        $turnoId = $request->has('turno_id')
            ? AfectaCaja::resolverTurno($user, 'anticipos', $data['turno_id'] ?? null, 'libre')
            : $anticipo->turno_id;

        $antes = [
            'monto' => (float) $anticipo->monto,
            'fecha' => $anticipo->fecha->toDateString(),
        ];

        DB::transaction(function () use ($anticipo, $user, $data, $turnoId) {
            $anticipo->update($data + [
                // Sin aplicaciones, el saldo sigue al monto.
                'saldo'    => $data['monto'],
                'turno_id' => $turnoId,
            ]);

            // Reasienta tesorería con los datos nuevos (mismo patrón que editar abono).
            $this->tesoreria->revertir('cliente_anticipo', $anticipo->id);
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
                "Anticipo de cliente — {$nombre} [editado]",
                'cliente_anticipo',
                $anticipo->id,
            );
        });

        AuditoriaService::log('anticipo_cliente.editado', $anticipo, [
            'antes'   => $antes,
            'despues' => ['monto' => (float) $data['monto'], 'fecha' => $data['fecha']],
        ], $user);

        return back()->with('success', 'Anticipo actualizado: tesorería se reasentó.');
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
            // Entrega EN DINERO: por dónde sale la plata (egreso de caja). En
            // material no aplica (sale mercadería, no dinero).
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
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

            $aplicacion = $anticipo->aplicaciones()->create([
                'empresa_id'     => $anticipo->empresa_id,
                'numero'         => \App\Models\ClienteAnticipoAplicacion::generarNumero($anticipo->empresa_id),
                'user_id'        => $user->id,
                'fecha'          => $data['fecha'],
                'monto'          => $montoAplicado,
                'cantidad'       => $cantCubierta,
                'venta_id'       => $data['venta_id'] ?? null,
                'observacion'    => $obs,
                // Solo en dinero: por dónde salió la plata.
                'metodo_pago_id' => $esMaterial ? null : ($data['metodo_pago_id'] ?? null),
                'cuenta_id'      => $esMaterial ? null : ($data['cuenta_id'] ?? null),
            ]);

            // Entrega en DINERO → egreso de caja por lo entregado (el material no
            // mueve tesorería: sale mercadería). Reasentable/reversible por su ref.
            if (!$esMaterial && $montoAplicado > 0.009) {
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $data['cuenta_id']
                        ?? ($data['metodo_pago_id'] ? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id']) : null)
                        ?? $anticipo->cuenta_id,
                    $user,
                    $data['fecha'],
                    'egreso',
                    (float) $montoAplicado,
                    "Entrega de anticipo #{$anticipo->id} ({$aplicacion->numero})",
                    'cliente_anticipo_entrega',
                    $aplicacion->id,
                );
            }

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
     * Cambia el producto de una línea pendiente de un anticipo material (POS).
     * Útil cuando el cliente pagó N unidades de un producto y, antes de entregarlas,
     * decide recibir parte en otro producto/marca. El precio congelado de la venta
     * se respeta. NO mueve stock: lo pendiente sigue sin salir del almacén.
     */
    public function cambiarProductoItem(Request $request, ClienteAnticipo $anticipo, ClienteAnticipoItem $item)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_if($item->cliente_anticipo_id !== $anticipo->id, 403);
        abort_unless($anticipo->tipo_valorizacion === 'material' && $anticipo->venta_id, 422,
            'Solo se puede cambiar producto en anticipos materiales del POS.');
        abort_unless(in_array($anticipo->estado, ['activo'], true), 422, 'El anticipo no está activo.');

        $data = $request->validate([
            'cantidad'          => ['required', 'numeric', 'min:0.01'],
            'nuevo_producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $user->empresa_id)],
            'nueva_unidad_id'   => ['nullable', 'integer', Rule::exists('producto_unidades', 'id')],
            'motivo'            => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $cantidad = round((float) $data['cantidad'], 4);
        $pendiente = (float) $item->cantidad_pendiente;

        if ($cantidad > $pendiente + 0.00009) {
            throw ValidationException::withMessages([
                'cantidad' => "Solo quedan pendientes {$pendiente} por cambiar de «{$item->producto_nombre}».",
            ]);
        }

        $nuevoProducto = Producto::deEmpresa($user->empresa_id)->findOrFail($data['nuevo_producto_id']);
        abort_unless($nuevoProducto->activo && $nuevoProducto->esProductoFisico(), 422, 'El producto destino debe ser un producto físico activo.');

        // Resolver unidad destino: la elegida, la base del producto, o la primera.
        if (!empty($data['nueva_unidad_id'])) {
            $nuevaUnidad = $nuevoProducto->unidades()->where('id', $data['nueva_unidad_id'])->firstOrFail();
        } else {
            $nuevaUnidad = $nuevoProducto->unidades()->where('es_base', true)->first()
                ?? $nuevoProducto->unidades()->orderBy('es_base', 'desc')->first();
        }

        abort_if($nuevaUnidad === null, 422, 'El producto destino no tiene unidades configuradas.');

        DB::transaction(function () use ($anticipo, $item, $user, $cantidad, $nuevoProducto, $nuevaUnidad, $data) {
            // Reducir la línea original.
            $item->update([
                'cantidad'           => max(0, round((float) $item->cantidad - $cantidad, 4)),
                'cantidad_pendiente' => max(0, round((float) $item->cantidad_pendiente - $cantidad, 4)),
            ]);

            // Crear la nueva línea pendiente con el producto destino.
            $anticipo->items()->create([
                'venta_item_id'      => null, // Es una sustitución, no viene de un ítem de venta original.
                'producto_id'        => $nuevoProducto->id,
                'producto_unidad_id' => $nuevaUnidad->id,
                'producto_nombre'    => $nuevoProducto->nombre,
                'unidad_nombre'      => optional($nuevaUnidad->unidadMedida)->nombre ?? 'und',
                'cantidad'           => $cantidad,
                'cantidad_pendiente' => $cantidad,
                'factor_conversion'  => (float) $nuevaUnidad->factor_conversion,
                'precio_unitario'    => (float) $item->precio_unitario, // Respeta precio congelado.
            ]);

            // Reconstruir el saldo del anticipo a valor pagado (cantidad total pendiente × precio).
            $anticipo->load('items');
            $nuevoSaldo = round($anticipo->items->sum(fn ($i) => (float) $i->cantidad_pendiente * (float) $i->precio_unitario), 2);

            $anticipo->update([
                'saldo'              => $nuevoSaldo,
                'cantidad_pendiente' => round($anticipo->items->sum(fn ($i) => (float) $i->cantidad_pendiente), 4),
                'estado'             => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
            ]);

            AuditoriaService::log('anticipo_cliente.cambio_producto', $anticipo, [
                'item_id'             => $item->id,
                'producto_origen'     => $item->producto_nombre,
                'cantidad'            => $cantidad,
                'producto_destino'    => $nuevoProducto->nombre,
                'unidad_destino'      => optional($nuevaUnidad->unidadMedida)->nombre,
                'motivo'              => $data['motivo'],
                'saldo'               => $nuevoSaldo,
            ], $user);
        });

        return back()->with('success', 'Producto cambiado: ahora el cliente tiene pendiente ' . rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.') . ' de «' . $nuevoProducto->nombre . '».');
    }

    /**
     * Cancela una parte (o la totalidad) del pendiente de un ítem de un anticipo
     * material del POS. Ajusta la venta original, el anticipo y, según el tipo
     * de venta, genera un egreso de tesorería (contado) o reduce la deuda (crédito).
     *
     * Usa el helper "Afecta caja" para decidir si el movimiento se imputa a un
     * turno/caja para los reportes de caja.
     */
    public function cancelarPendienteItem(Request $request, ClienteAnticipo $anticipo, ClienteAnticipoItem $item)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_if($item->cliente_anticipo_id !== $anticipo->id, 403);
        abort_unless($anticipo->tipo_valorizacion === 'material' && $anticipo->venta_id, 422,
            'Solo se puede cancelar pendiente en anticipos materiales del POS.');
        abort_unless($anticipo->estado === 'activo', 422, 'El anticipo no está activo.');

        $data = $request->validate([
            'cantidad'       => ['required', 'numeric', 'min:0.0001'],
            'motivo'         => ['required', 'string', 'min:5', 'max:500'],
            'fecha'          => ['required', 'date'],
            'observacion'    => ['nullable', 'string', 'max:500'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        $venta = Venta::where('id', $anticipo->venta_id)
            ->where('empresa_id', $user->empresa_id)
            ->firstOrFail();

        // V14 — Una venta con comprobante informado a SUNAT no se puede tocar
        // directamente; requiere Nota de Crédito.
        if (Schema::hasTable('venta_comprobantes')) {
            $ce = $venta->comprobanteElectronico()->first();
            if ($ce && $ce->esEmitido()) {
                abort(422, "La venta tiene el comprobante {$ce->numero} informado a SUNAT. Para reducir el pendiente emita una Nota de Crédito.");
            }
        }

        $cantidadCancelar = round((float) $data['cantidad'], 4);
        $pendiente = (float) $item->cantidad_pendiente;
        if ($cantidadCancelar > $pendiente + 0.00009) {
            throw ValidationException::withMessages([
                'cantidad' => "Solo quedan pendientes {$pendiente} por cancelar.",
            ]);
        }

        $esCredito = (bool) $venta->es_credito;
        if (! $esCredito && empty($data['metodo_pago_id']) && empty($data['cuenta_id'])) {
            throw ValidationException::withMessages([
                'cuenta_id' => 'En una venta de contado indica por dónde se devuelve el dinero.',
            ]);
        }

        $montoCancelar = round($cantidadCancelar * (float) $item->precio_unitario, 2);

        $turnoId = AfectaCaja::resolverTurno(
            $user, 'anticipos_cancelacion',
            !empty($data['turno_id']) ? (int) $data['turno_id'] : null,
            'libre',
        );

        $antesVenta = [
            'total'           => (float) $venta->total,
            'saldo_pendiente' => (float) $venta->saldo_pendiente,
        ];
        $antesItem = [
            'cantidad'           => (float) $item->cantidad,
            'cantidad_pendiente' => (float) $item->cantidad_pendiente,
        ];
        $antesAnticipoSaldo = (float) $anticipo->saldo;

        DB::transaction(function () use ($anticipo, $item, $venta, $user, $data, $cantidadCancelar, $montoCancelar, $turnoId, $esCredito) {
            // ── Ajustar el ítem de la venta original ───────────────────────
            $ventaItem = VentaItem::where('id', $item->venta_item_id)
                ->where('venta_id', $venta->id)
                ->firstOrFail();

            $nuevaCantidad = max(0, round((float) $ventaItem->cantidad - $cantidadCancelar, 4));
            $nuevaCantidadBase = round($nuevaCantidad * (float) $ventaItem->factor_conversion, 4);
            $nuevoSubtotal = round(((float) $ventaItem->precio_unitario - (float) $ventaItem->descuento_item) * $nuevaCantidad, 2);

            $ventaItem->update([
                'cantidad'      => $nuevaCantidad,
                'cantidad_base' => $nuevaCantidadBase,
                'subtotal'      => $nuevoSubtotal,
            ]);

            // ── Recalcular totales de la venta ────────────────────────────
            $venta->load('items');
            $venta->calcularTotales();
            $venta->refresh();

            $nuevoTotal = (float) $venta->total;
            $factorMoneda = ($venta->moneda !== 'PEN' && (float) $venta->tipo_cambio > 0)
                ? (float) $venta->tipo_cambio
                : 1.0;

            if ($esCredito) {
                $montoPagado = (float) $venta->monto_pagado;
                $venta->update([
                    'saldo_pendiente' => max(0, round($nuevoTotal - $montoPagado, 2)),
                    'monto_moneda'    => $venta->moneda !== 'PEN' ? round($nuevoTotal / $factorMoneda, 2) : null,
                ]);
            } else {
                $venta->update([
                    'monto_pagado'    => $nuevoTotal,
                    'saldo_pendiente' => 0,
                    'monto_moneda'    => $venta->moneda !== 'PEN' ? round($nuevoTotal / $factorMoneda, 2) : null,
                ]);
            }

            // ── Ajustar el ítem del anticipo ────────────────────────────
            $item->update([
                'cantidad'           => max(0, round((float) $item->cantidad - $cantidadCancelar, 4)),
                'cantidad_pendiente' => max(0, round((float) $item->cantidad_pendiente - $cantidadCancelar, 4)),
            ]);

            // ── Guardar turno/caja afectada ───────────────────────────────
            $turno = $turnoId
                ? Turno::where('id', $turnoId)->where('empresa_id', $user->empresa_id)->first()
                : null;
            $cajaId = $turno?->caja_id;

            // ── Crear el registro de cancelación ──────────────────────────
            $cancelacion = ClienteAnticipoCancelacion::create([
                'cliente_anticipo_id'      => $anticipo->id,
                'cliente_anticipo_item_id' => $item->id,
                'empresa_id'               => $user->empresa_id,
                'user_id'                  => $user->id,
                'fecha'                    => $data['fecha'],
                'cantidad'                 => $cantidadCancelar,
                'monto'                    => $montoCancelar,
                'motivo'                   => $data['motivo'],
                'turno_id'                 => $turnoId,
                'caja_id'                  => $cajaId,
                'metodo_pago_id'           => $data['metodo_pago_id'] ?? null,
                'cuenta_id'                => $data['cuenta_id'] ?? null,
                'observacion'              => $data['observacion'] ?? null,
                'moneda'                   => $venta->moneda ?? 'PEN',
                'tipo_cambio'              => $venta->tipo_cambio,
                'monto_moneda'             => $venta->moneda !== 'PEN' && $factorMoneda > 0
                    ? round($montoCancelar / $factorMoneda, 2)
                    : null,
            ]);

            // ── Contado: devolver dinero (egreso de tesorería) ────────────
            if (! $esCredito && $montoCancelar > 0.009) {
                $cuentaSalida = $data['cuenta_id']
                    ?? ($data['metodo_pago_id']
                        ? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'])
                        : null)
                    ?? $anticipo->cuenta_id;

                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $cuentaSalida,
                    $user,
                    $data['fecha'],
                    'egreso',
                    $montoCancelar,
                    "Cancelación de pendiente — Venta {$venta->numero} — {$item->producto_nombre}",
                    'anticipo_cancelacion',
                    $cancelacion->id,
                    $venta->moneda ?? 'PEN',
                    $venta->tipo_cambio,
                    $cancelacion->monto_moneda,
                );
            }

            // ── Recalcular saldo y estado del anticipo ────────────────────
            $this->recomputarSaldoMaterial($anticipo);

            $anticipo->refresh();
            if ((float) $anticipo->saldo <= 0.01) {
                $anticipo->update(['estado' => $esCredito ? 'aplicado' : 'devuelto']);
            }
        });

        AuditoriaService::log('anticipo_cliente.pendiente_cancelado', $anticipo, [
            'item_id'              => $item->id,
            'producto'             => $item->producto_nombre,
            'cantidad_cancelada'   => $cantidadCancelar,
            'monto_cancelado'      => $montoCancelar,
            'venta_id'             => $venta->id,
            'venta_numero'         => $venta->numero,
            'es_credito'           => $esCredito,
            'antes_venta'          => $antesVenta,
            'despues_venta'        => [
                'total'           => (float) $venta->fresh()->total,
                'saldo_pendiente' => (float) $venta->fresh()->saldo_pendiente,
            ],
            'antes_item'           => $antesItem,
            'antes_saldo_anticipo' => $antesAnticipoSaldo,
            'motivo'               => $data['motivo'],
        ], $user);

        return back()->with('success', 'Pendiente cancelado: la venta y el anticipo quedaron ajustados.');
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
            // Devolución: por dónde y cuándo sale el dinero. Opcionales; si no se
            // eligen, cae en la cuenta original del anticipo y la fecha de hoy.
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'fecha'          => ['nullable', 'date'],
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
                // El dinero sale por la cuenta elegida (o la resuelta del método);
                // si no se indicó nada, por la cuenta original del anticipo.
                $cuentaSalida = $data['cuenta_id']
                    ?? ($data['metodo_pago_id']
                        ? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'])
                        : null)
                    ?? $anticipo->cuenta_id;
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $cuentaSalida,
                    $user,
                    $data['fecha'] ?? now()->toDateString(),
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
     * Edita una ENTREGA (aplicación) de un anticipo:
     *  - En DINERO: corrige monto, fecha, método/cuenta.
     *  - En MATERIAL (POS): corrige las CANTIDADES entregadas de cada ítem,
     *    ajustando stock y pendiente del anticipo. No se cambian productos.
     */
    public function editarEntrega(Request $request, ClienteAnticipoAplicacion $entrega)
    {
        $user = $request->user();
        abort_if($entrega->empresa_id !== $user->empresa_id, 403);

        $anticipo = $entrega->anticipo;
        $this->validarEntregaEditable($anticipo, $entrega);

        if ($anticipo->tipo_valorizacion === 'monto') {
            return $this->editarEntregaDinero($request, $entrega, $anticipo, $user);
        }

        return $this->editarEntregaMaterial($request, $entrega, $anticipo, $user);
    }

    private function editarEntregaDinero(Request $request, ClienteAnticipoAplicacion $entrega, ClienteAnticipo $anticipo, $user)
    {
        $data = $request->validate([
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'fecha'          => ['required', 'date'],
            'observacion'    => ['nullable', 'string', 'max:500'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
        ]);

        // La suma de entregas no puede superar el monto del anticipo.
        $otras = (float) $anticipo->aplicaciones()->where('id', '<>', $entrega->id)->sum('monto');
        if ($otras + (float) $data['monto'] > (float) $anticipo->monto + 0.01) {
            throw ValidationException::withMessages([
                'monto' => 'La suma de las entregas (S/ ' . number_format($otras + (float) $data['monto'], 2)
                    . ') superaría el monto del anticipo (S/ ' . number_format((float) $anticipo->monto, 2) . ').',
            ]);
        }

        $antes = ['monto' => (float) $entrega->monto, 'fecha' => (string) $entrega->fecha->toDateString()];

        DB::transaction(function () use ($entrega, $anticipo, $data, $otras, $user) {
            $entrega->update([
                'monto'          => $data['monto'],
                'fecha'          => $data['fecha'],
                'observacion'    => $data['observacion'] ?? $entrega->observacion,
                'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
                'cuenta_id'      => $data['cuenta_id'] ?? null,
            ]);
            $this->recomputarSaldoDinero($anticipo, $otras + (float) $data['monto']);

            // Reasienta el egreso de caja con los datos nuevos (mismo patrón que
            // editar abono/anticipo): revierte el anterior y registra el actual.
            $this->tesoreria->revertir('cliente_anticipo_entrega', $entrega->id);
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id']
                    ?? ($data['metodo_pago_id'] ? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id']) : null)
                    ?? $anticipo->cuenta_id,
                $user,
                $data['fecha'],
                'egreso',
                (float) $data['monto'],
                "Entrega de anticipo #{$anticipo->id} ({$entrega->numero}) [editada]",
                'cliente_anticipo_entrega',
                $entrega->id,
            );
        });

        AuditoriaService::log('anticipo_cliente.entrega_editada', $anticipo, [
            'entrega' => $entrega->numero,
            'antes'   => $antes,
            'despues' => ['monto' => (float) $data['monto'], 'fecha' => $data['fecha']],
            'saldo'   => (float) $anticipo->fresh()->saldo,
        ], $user);

        return back()->with('success', 'Entrega actualizada: el saldo del anticipo se recalculó.');
    }

    private function editarEntregaMaterial(Request $request, ClienteAnticipoAplicacion $entrega, ClienteAnticipo $anticipo, $user)
    {
        $data = $request->validate([
            'fecha'       => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'items'       => ['present', 'array'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
        ]);

        $entrega->load('items.item.producto', 'anticipo.venta.local');
        $apItems = $entrega->items->keyBy('id');

        // Las cantidades recibidas en el payload indican lo que SÍ se entregó.
        // Si un ítem de la entrega no viene, se entiende que su nueva cantidad es 0.
        $cantidadesRecibidas = [];
        foreach ($data['items'] as $idx => $linea) {
            $cantidadesRecibidas[(int) $linea['id']] = round((float) $linea['cantidad'], 4);
        }

        // Validar y preparar ajustes.
        $almacen = $this->almacenDeEntregaMaterial($anticipo, $user);
        $permitirNegativo = $this->config->permiteStockNegativo($user->empresa_id);
        $ajustes = [];

        foreach ($apItems as $id => $apItem) {
            $item = $apItem->item;
            if (!$item) continue;

            $nuevaCantidad = $cantidadesRecibidas[$id] ?? 0.0;
            $viejaCantidad = (float) $apItem->cantidad;
            $maximoPermitido = round((float) $item->cantidad_pendiente + $viejaCantidad, 4);

            if ($nuevaCantidad > $maximoPermitido + 0.00009) {
                throw ValidationException::withMessages([
                    'items' => "De «{$item->producto_nombre}» solo puedes aumentar hasta {$maximoPermitido} (pendiente + lo ya entregado en esta entrega).",
                ]);
            }

            $diferencia = round($nuevaCantidad - $viejaCantidad, 4);
            if (abs($diferencia) > 0.00009) {
                $ajustes[] = [
                    'apItem'        => $apItem,
                    'item'          => $item,
                    'nuevaCantidad' => $nuevaCantidad,
                    'diferencia'    => $diferencia,
                    'producto'      => $item->producto,
                ];
            }
        }

        $antes = $entrega->items->map(fn ($i) => [
            'item'     => $i->item?->producto_nombre,
            'cantidad' => (float) $i->cantidad,
        ])->all();

        DB::transaction(function () use ($entrega, $anticipo, $data, $ajustes, $almacen, $permitirNegativo) {
            // Aplicar ajustes de stock y pendiente. Si la nueva cantidad es 0,
            // se elimina el detalle de la entrega.
            $totalEntregado = (float) $entrega->cantidad;
            foreach ($ajustes as $a) {
                $apItem = $a['apItem'];
                $item   = $a['item'];
                $diff   = $a['diferencia'];
                $nueva  = $a['nuevaCantidad'];

                $item->update([
                    'cantidad_pendiente' => max(0, round((float) $item->cantidad_pendiente - $diff, 4)),
                ]);

                if ($almacen && $a['producto'] && $anticipo->venta
                    && $this->config->deboDescontarStock($a['producto'], $anticipo->venta->local)) {
                    $base = round(abs($diff) * (float) $item->factor_conversion, 4);
                    if ($base > 0.00009) {
                        $signo = $diff > 0 ? -1 : 1; // Si entrega más: sale stock; si menos: vuelve.
                        Stock::ajustar($almacen->id, $item->producto_id, $signo * $base, 0, $permitirNegativo, contexto: [
                            'tipo'            => 'ajuste_entrega_pendiente',
                            'referencia_tipo' => 'venta',
                            'referencia_id'   => $anticipo->venta_id,
                            'fecha'           => now(),
                            'user_id'         => optional(auth()->user())->id,
                            'empresa_id'      => $almacen->empresa_id,
                        ]);
                    }
                }

                if ($nueva <= 0.00009) {
                    $apItem->delete();
                } else {
                    $apItem->update(['cantidad' => $nueva]);
                }

                $totalEntregado += $diff;
            }

            $entrega->update([
                'fecha'       => $data['fecha'],
                'observacion' => $data['observacion'] ?? $entrega->observacion,
                'cantidad'    => max(0, round($totalEntregado, 4)),
            ]);

            // Si la entrega quedó sin ítems, se elimina (equivale a anularla).
            $entrega->load('items');
            if ($entrega->items->isEmpty()) {
                $entrega->delete();
            }

            $this->recomputarSaldoMaterial($anticipo);
        });

        $existe = $anticipo->fresh()->aplicaciones()->where('id', $entrega->id ?? 0)->exists();
        $mensaje = $existe
            ? 'Entrega actualizada: stock y pendiente quedaron consistentes.'
            : 'Entrega eliminada: al quedar en 0 se anuló y se recuperó el stock/pendiente.';

        AuditoriaService::log('anticipo_cliente.entrega_editada', $anticipo, [
            'entrega' => $entrega->numero,
            'antes'   => $antes,
            'despues' => collect($data['items'])->map(fn ($i) => [
                'item'     => $apItems[(int) $i['id']]?->item?->producto_nombre,
                'cantidad' => (float) $i['cantidad'],
            ])->all(),
            'saldo'   => (float) $anticipo->fresh()->saldo,
        ], $user);

        return back()->with('success', $mensaje);
    }

    /**
     * Anula (deshace) una ENTREGA. Para dinero, restituye el saldo; para material,
     * devuelve el stock y recupera el pendiente del anticipo.
     */
    public function anularEntrega(Request $request, ClienteAnticipoAplicacion $entrega)
    {
        $user = $request->user();
        abort_if($entrega->empresa_id !== $user->empresa_id, 403);

        $anticipo = $entrega->anticipo;
        $this->validarEntregaEditable($anticipo, $entrega);

        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);

        $info = [
            'entrega' => $entrega->numero,
            'monto'   => (float) $entrega->monto,
            'fecha'   => (string) $entrega->fecha->toDateString(),
        ];

        if ($anticipo->tipo_valorizacion === 'monto') {
            DB::transaction(function () use ($entrega, $anticipo) {
                // Revierte el egreso de caja de esta entrega antes de borrarla.
                $this->tesoreria->revertir('cliente_anticipo_entrega', $entrega->id);
                $entrega->delete();
                $otras = (float) $anticipo->aplicaciones()->sum('monto');
                $this->recomputarSaldoDinero($anticipo, $otras);
            });
        } else {
            $entrega->load('items.item.producto', 'anticipo.venta.local');
            $almacen = $this->almacenDeEntregaMaterial($anticipo, $user);
            $permitirNegativo = $this->config->permiteStockNegativo($user->empresa_id);

            DB::transaction(function () use ($entrega, $anticipo, $almacen, $permitirNegativo) {
                foreach ($entrega->items as $apItem) {
                    $item = $apItem->item;
                    if (!$item) continue;

                    // Recuperar pendiente.
                    $item->update([
                        'cantidad_pendiente' => round((float) $item->cantidad_pendiente + (float) $apItem->cantidad, 4),
                    ]);

                    // Devolver stock al almacén.
                    if ($almacen && $item->producto && $anticipo->venta
                        && $this->config->deboDescontarStock($item->producto, $anticipo->venta->local)) {
                        $base = round((float) $apItem->cantidad * (float) $item->factor_conversion, 4);
                        if ($base > 0.00009) {
                            Stock::ajustar($almacen->id, $item->producto_id, $base, 0, $permitirNegativo, contexto: [
                                'tipo'            => 'anulacion_entrega_pendiente',
                                'referencia_tipo' => 'venta',
                                'referencia_id'   => $anticipo->venta_id,
                                'fecha'           => now(),
                                'user_id'         => optional(auth()->user())->id,
                                'empresa_id'      => $almacen->empresa_id,
                            ]);
                        }
                    }
                }

                $entrega->items()->delete();
                $entrega->delete();
                $this->recomputarSaldoMaterial($anticipo);
            });
        }

        AuditoriaService::log('anticipo_cliente.entrega_anulada', $anticipo, $info + [
            'motivo' => $data['motivo'],
            'saldo'  => (float) $anticipo->fresh()->saldo,
        ], $user);

        return back()->with('success', 'Entrega anulada: el anticipo y el stock quedaron consistentes.');
    }

    /** Reglas comunes para editar/anular una entrega. */
    private function validarEntregaEditable(?ClienteAnticipo $anticipo, ClienteAnticipoAplicacion $entrega): void
    {
        abort_if($anticipo === null, 404);
        abort_unless(in_array($anticipo->estado, ['activo', 'aplicado'], true), 422,
            'El anticipo no admite editar sus entregas en su estado actual.');
        if ($entrega->observacion && str_contains($entrega->observacion, 'Excedente a CxC')) {
            throw ValidationException::withMessages([
                'entrega' => 'Esta entrega generó una cuenta por cobrar por excedente. Revísala y ajústala manualmente en Cuentas por cobrar.',
            ]);
        }
    }

    /** Resuelve el almacén de ventas del local de la venta original (POS). */
    private function almacenDeEntregaMaterial(ClienteAnticipo $anticipo, $user): ?\App\Models\Almacen
    {
        if (!$anticipo->venta_id || !$anticipo->venta) {
            return null;
        }
        return $this->scope->almacenVentasDeLocal($anticipo->empresa_id, $anticipo->venta->local_id);
    }

    /** Reasienta saldo/estado de un anticipo MATERIAL a partir de sus ítems pendientes. */
    private function recomputarSaldoMaterial(ClienteAnticipo $anticipo): void
    {
        $anticipo->load('items');
        $nuevoSaldo = round($anticipo->items->sum(fn ($i) => (float) $i->cantidad_pendiente * (float) $i->precio_unitario), 2);
        $nuevaCantidad = round($anticipo->items->sum(fn ($i) => (float) $i->cantidad_pendiente), 4);

        $anticipo->update([
            'saldo'              => $nuevoSaldo,
            'cantidad_pendiente' => $nuevaCantidad,
            'estado'             => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
        ]);
    }

    /** Reasienta saldo/estado de un anticipo en dinero: saldo = monto − entregas. */
    private function recomputarSaldoDinero(ClienteAnticipo $anticipo, float $sumaEntregas): void
    {
        $saldo = round((float) $anticipo->monto - $sumaEntregas, 2);
        $anticipo->update([
            'saldo'  => max(0, $saldo),
            'estado' => $saldo <= 0.01 ? 'aplicado' : 'activo',
        ]);
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

        // Pasamos el usuario para enrutar el ticket a la ticketera de SU caja/turno
        // (su PC), no a "la primera caja del local".
        return response()->json(app(TicketPrintService::class)->payloadDeEntregaAnticipo($entrega, $request->user()));
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
