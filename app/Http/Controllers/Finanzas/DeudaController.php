<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Support\Xlsx;
use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\DeudaPago;
use App\Models\MetodoPago;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use App\Support\AfectaCaja;
use App\Support\ExigeCuentaDePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Deudas y préstamos: bancarios ("DEUDA BCP 1 - 7630"), de personas
 * ("JEINER HERRERA"), al personal ("DEBEMOS AL PERSONAL") y préstamos
 * otorgados a terceros (por cobrar).
 */
class DeudaController extends Controller
{
    use ExigeCuentaDePago;

    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Deuda::deEmpresa($user->empresa_id)
            ->with([
                'pagos' => fn ($q) => $q->with(['metodoPago:id,nombre', 'cuenta:id,nombre', 'user:id,name', 'turno:id'])->orderBy('fecha', 'desc')->orderBy('id', 'desc'),
                'desembolso.cuenta',
                // Tercero vinculado (opcional): badge en el listado + cruces.
                'cliente:id,nombres,apellidos,razon_social,numero_documento',
                'proveedor:id,razon_social,nombre_comercial,numero_documento',
            ])
            ->when($request->input('direccion'), fn ($q, $v) => $q->where('direccion', $v))
            ->when($request->input('tipo'), fn ($q, $v) => $q->where('tipo', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('nombre', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('cliente', fn ($c) => $c
                        ->where('nombres', 'ilike', "%{$t}%")
                        ->orWhere('apellidos', 'ilike', "%{$t}%")
                        ->orWhere('razon_social', 'ilike', "%{$t}%")
                        ->orWhere('numero_documento', 'ilike', "%{$t}%"))
                    ->orWhereHas('proveedor', fn ($p) => $p
                        ->where('razon_social', 'ilike', "%{$t}%")
                        ->orWhere('nombre_comercial', 'ilike', "%{$t}%")
                        ->orWhere('numero_documento', 'ilike', "%{$t}%")));
            });

        // Filtro de estado: 'activas' (default), 'pagadas', 'anuladas' o 'todas'.
        $estado = $request->input('estado', 'activas');
        if ($estado === 'activas') {
            $query->activa();
        } elseif ($estado === 'pagadas') {
            $query->where('estado', 'pagada');
        } elseif ($estado === 'anuladas') {
            $query->where('estado', 'anulada');
        }

        $deudas = $query->orderBy('direccion')->orderBy('tipo')->orderBy('nombre')->paginate(25)->withQueryString();

        $vencidas = Deuda::deEmpresa($user->empresa_id)->activa()
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString());
        $totales = [
            'por_pagar'     => round((float) Deuda::deEmpresa($user->empresa_id)->porPagar()->activa()->sum('saldo'), 2),
            'por_cobrar'    => round((float) Deuda::deEmpresa($user->empresa_id)->porCobrar()->activa()->sum('saldo'), 2),
            'activas'       => (int) Deuda::deEmpresa($user->empresa_id)->activa()->count(),
            'vencidas'      => (int) (clone $vencidas)->count(),
            'monto_vencido' => round((float) (clone $vencidas)->sum('saldo'), 2),
        ];

        return Inertia::render('Finanzas/Deudas', [
            'deudas'      => $deudas,
            'totales'     => $totales,
            'estado'      => $request->input('estado', 'activas'),
            'buscar'      => $request->input('buscar', ''),
            // Acciones visibles según la matriz de permisos del rol (nada
            // hardcodeado a es_admin: se otorgan desde Configuración → Roles).
            'puede'       => [
                'editar'     => $user->tienePermiso('finanzas.deudas', 'editar'),
                'eliminar'   => $user->tienePermiso('finanzas.deudas', 'eliminar'),
                'compensar'  => $user->tienePermiso('finanzas.deudas', 'editar'),
            ],
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
            // "Afecta caja a:" — turnos abiertos para que el admin (o quien no
            // tenga turno propio) elija a qué caja se imputa el desembolso/pago.
            // El componente <AfectaCajaSelect> decide si mostrarse según la
            // config de la empresa (módulo 'deuda'). turno_activo y es_admin
            // llegan por props compartidas.
            'turnos'      => \App\Models\Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where('estado', 'abierto')
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
            // Terceros para el vínculo OPCIONAL de la deuda (selector con buscador).
            'clientes'    => \App\Models\Cliente::where('empresa_id', $user->empresa_id)
                ->where('activo', true)
                ->orderBy('razon_social')->orderBy('nombres')
                ->get(['id', 'tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'razon_social']),
            'proveedores' => \App\Models\Proveedor::deEmpresa($user->empresa_id)
                ->activo()
                ->orderBy('razon_social')
                ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'nombre_comercial']),
            // Cruces para deudas VINCULADAS (mismo dato que CxC/CxP):
            //  - anticipos de dinero por cliente (cobrar deuda por cobrar del anticipo)
            //  - ventas CxC con saldo (compensar deuda por pagar del cliente)
            //  - compras CxP con saldo (compensar deuda por cobrar del proveedor)
            'anticiposClientes' => \App\Models\ClienteAnticipo::deEmpresa($user->empresa_id)
                ->activo()->where('tipo_valorizacion', 'monto')->where('saldo', '>', 0)
                ->get(['id', 'cliente_id', 'fecha', 'saldo']),
            'ventasCompensables' => \App\Models\Venta::deEmpresa($user->empresa_id)
                ->conSaldoPendiente()
                ->orderByDesc('fecha_venta')
                ->get(['id', 'numero', 'cliente_id', 'fecha_venta', 'total', 'monto_pagado', 'saldo_pendiente']),
            'comprasCompensables' => \App\Models\Entrada::deEmpresa($user->empresa_id)
                ->comprometido()
                ->whereRaw('total - monto_pagado > 0.01')
                ->orderByDesc('fecha')
                ->get(['id', 'correlativo', 'numero_documento', 'proveedor', 'proveedor_id', 'fecha', 'total', 'monto_pagado']),
        ]);
    }

    /**
     * Exporta TODAS las deudas filtradas a CSV (Excel), respetando dirección,
     * tipo, estado y búsqueda. Las columnas coinciden con las visibles en la tabla.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        $query = Deuda::deEmpresa($user->empresa_id)
            ->with(['pagos.metodoPago'])
            ->when($request->input('direccion'), fn ($q, $v) => $q->where('direccion', $v))
            ->when($request->input('tipo'), fn ($q, $v) => $q->where('tipo', $v))
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('nombre', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%"));
            });

        $estado = $request->input('estado', 'activas');
        if ($estado === 'activas') {
            $query->activa();
        } elseif ($estado === 'pagadas') {
            $query->where('estado', 'pagada');
        } elseif ($estado === 'anuladas') {
            $query->where('estado', 'anulada');
        }

        $deudas = $query->orderBy('direccion')->orderBy('tipo')->orderBy('nombre')->get();

        $tipoLabel = [
            'bancaria' => 'Bancaria', 'personal' => 'Personal',
            'trabajador' => 'Al personal', 'otro' => 'Otro',
        ];

        $headers = ['Dirección', 'Nombre', 'Tipo', 'Método de pago', 'Original', 'Saldo', 'Estado'];
        $filas = [];

        foreach ($deudas as $d) {
            $metodos = $d->pagos->map(fn ($p) => $p->metodoPago?->nombre)->filter()->unique()->implode(' · ') ?: '—';

            // Signo contable: lo que DEBEMOS va en negativo, lo que NOS DEBEN en
            // positivo. Así la suma de la columna en Excel da el neto real.
            $signo = $d->direccion === 'por_pagar' ? -1 : 1;

            $filas[] = [
                $d->direccion === 'por_pagar' ? 'Debemos' : 'Nos deben',
                $d->nombre,
                $tipoLabel[$d->tipo] ?? $d->tipo,
                $metodos,
                $signo * (float) $d->monto_original,
                $signo * (float) $d->saldo,
                $d->estado === 'activa' ? 'Activa' : ($d->estado === 'pagada' ? 'Pagada' : 'Anulada'),
            ];
        }

        return Xlsx::descargar($headers, $filas, 'deudas', [4 => true, 5 => true]);
    }

    /**
     * Devuelve las deudas ACTIVAS para poblar selects de compensación u otros
     * formularios. Filtro opcional por dirección y búsqueda por nombre/observación.
     */
    public function activas(Request $request)
    {
        $user = $request->user();
        $direccion = $request->input('direccion');
        $buscar = trim($request->input('buscar', ''));

        $query = Deuda::deEmpresa($user->empresa_id)
            ->activa()
            ->when(in_array($direccion, ['por_pagar', 'por_cobrar']), fn ($q) => $q->where('direccion', $direccion))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('nombre', 'ilike', "%{$buscar}%")
                ->orWhere('observacion', 'ilike', "%{$buscar}%")));

        return response()->json($query->orderBy('nombre')->limit(100)->get([
            'id', 'nombre', 'direccion', 'saldo',
        ]));
    }

    /**
     * Compensa una deuda por pagar contra una por cobrar. Crea un movimiento
     * tipo "compensacion" en cada deuda (sin mover caja), reduce ambos saldos
     * y cierra la(s) que quede(n) en cero.
     */
    public function compensar(Request $request)
    {
        $user = $request->user();
        abort_if(!$user->tienePermiso('finanzas.deudas', 'editar'), 403);

        $data = $request->validate([
            'deuda_por_pagar_id'  => ['required', 'integer', 'exists:deudas,id'],
            'deuda_por_cobrar_id' => ['required', 'integer', 'exists:deudas,id'],
            'fecha'               => ['required', 'date', new \App\Rules\NoFutura],
            'monto'               => ['required', 'numeric', 'min:0.01'],
            'observacion'         => ['nullable', 'string', 'max:500'],
        ]);

        $porPagar  = Deuda::deEmpresa($user->empresa_id)->find($data['deuda_por_pagar_id']);
        $porCobrar = Deuda::deEmpresa($user->empresa_id)->find($data['deuda_por_cobrar_id']);

        $validator = \Illuminate\Support\Facades\Validator::make($data, []);
        $validator->after(function ($v) use ($porPagar, $porCobrar) {
            if (!$porPagar || $porPagar->direccion !== Deuda::DIRECCION_POR_PAGAR) {
                $v->errors()->add('deuda_por_pagar_id', 'La deuda seleccionada no es una deuda por pagar.');
            }
            if (!$porCobrar || $porCobrar->direccion !== Deuda::DIRECCION_POR_COBRAR) {
                $v->errors()->add('deuda_por_cobrar_id', 'La deuda seleccionada no es una deuda por cobrar.');
            }
            if ($porPagar && $porCobrar && $porPagar->id === $porCobrar->id) {
                $v->errors()->add('deuda_por_cobrar_id', 'No se puede compensar una deuda consigo misma.');
            }
            if ($porPagar && $porPagar->estado !== 'activa') {
                $v->errors()->add('deuda_por_pagar_id', 'La deuda por pagar no está activa.');
            }
            if ($porCobrar && $porCobrar->estado !== 'activa') {
                $v->errors()->add('deuda_por_cobrar_id', 'La deuda por cobrar no está activa.');
            }

            if ($porPagar && $porCobrar && $porPagar->estado === 'activa' && $porCobrar->estado === 'activa') {
                $maximo = min((float) $porPagar->saldo, (float) $porCobrar->saldo);
                if ($maximo < 0.01) {
                    $v->errors()->add('monto', 'Ambas deudas deben tener saldo positivo para compensar.');
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $maximo = min((float) $porPagar->saldo, (float) $porCobrar->saldo);
        $montoSolicitado = (float) $data['monto'];
        if ($montoSolicitado < 0.01 || $montoSolicitado > $maximo + 0.001) {
            return back()->withErrors(['monto' => 'El monto no puede superar el saldo menor (máximo S/ ' . number_format($maximo, 2) . ').'])->withInput();
        }

        $grupoId = Str::uuid()->toString();
        $observacion = $data['observacion'] ?? null;
        $monto = $montoSolicitado;

        DB::transaction(function () use ($user, $porPagar, $porCobrar, $data, $monto, $grupoId, $observacion) {
            // Releer ambas deudas con bloqueo (en orden de id, sin interbloqueos)
            // y revalidar el máximo: dos compensaciones a la vez no pueden
            // pasar ambas el tope.
            $bloqueadas = Deuda::whereIn('id', [$porPagar->id, $porCobrar->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $porPagar   = $bloqueadas[$porPagar->id];
            $porCobrar  = $bloqueadas[$porCobrar->id];
            abort_unless($porPagar->estado === 'activa' && $porCobrar->estado === 'activa', 422, 'Una de las deudas ya no está activa.');
            $maximo = min((float) $porPagar->saldo, (float) $porCobrar->saldo);
            if ($monto > $maximo + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'monto' => 'El monto no puede superar el saldo menor (máximo S/ ' . number_format($maximo, 2) . ', recién actualizado).',
                ]);
            }

            $porPagar->pagos()->create([
                'user_id'               => $user->id,
                'fecha'                 => $data['fecha'],
                'tipo'                  => 'compensacion',
                'monto'                 => $monto,
                'observacion'           => $observacion,
                'compensacion_grupo_id' => $grupoId,
                'compensacion_deuda_id' => $porCobrar->id,
            ]);

            $porCobrar->pagos()->create([
                'user_id'               => $user->id,
                'fecha'                 => $data['fecha'],
                'tipo'                  => 'compensacion',
                'monto'                 => $monto,
                'observacion'           => $observacion,
                'compensacion_grupo_id' => $grupoId,
                'compensacion_deuda_id' => $porPagar->id,
            ]);

            $porPagar->recalcularSaldo();
            $porCobrar->recalcularSaldo();

            AuditoriaService::log('deuda.compensacion_creada', $porPagar, [
                'grupo_id'            => $grupoId,
                'monto'               => $monto,
                'deuda_por_pagar_id'  => $porPagar->id,
                'deuda_por_cobrar_id' => $porCobrar->id,
                'observacion'         => $observacion,
            ], $user);
        });

        return back()->with('success', 'Compensación registrada correctamente.');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'direccion'         => ['required', Rule::in(['por_pagar', 'por_cobrar'])],
            'tipo'              => ['required', Rule::in(['bancaria', 'personal', 'trabajador', 'otro'])],
            'nombre'            => ['required', 'string', 'max:200'],
            // Tercero vinculado OPCIONAL (a lo más uno): habilita cruces y estado de cuenta.
            'cliente_id'        => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $user->empresa_id), 'prohibits:proveedor_id'],
            'proveedor_id'      => ['nullable', 'integer', Rule::exists('proveedores', 'id')->where('empresa_id', $user->empresa_id)],
            'monto_original'    => ['required', 'numeric', 'min:0.01'],
            'fecha_inicio'      => ['required', 'date', new \App\Rules\NoFutura],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'observacion'       => ['nullable', 'string', 'max:500'],
            // Desembolso opcional: mover el dinero en tesorería al crear la deuda.
            // Por defecto SÍ se mueve; se puede desactivar para deudas históricas
            // (ya gastadas / sin rastro de caja).
            'registrar_caja'    => ['boolean'],
            'metodo_pago_id'    => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'         => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            // "Afecta caja a:" — turno cuya caja recibe/entrega el desembolso.
            'turno_id'          => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        $registrarCaja = $request->boolean('registrar_caja', true);

        // Solo tiene sentido imputar turno si el desembolso mueve caja. La regla
        // única (módulo apagado → null; cajero → su turno; admin → el elegido)
        // vive en AfectaCaja::resolverTurno.
        $turnoId = $registrarCaja
            ? AfectaCaja::resolverTurno($user, 'deuda', $data['turno_id'] ?? null)
            : null;

        $deuda = DB::transaction(function () use ($data, $user, $registrarCaja, $turnoId) {
            $deuda = Deuda::create([
                'empresa_id'        => $user->empresa_id,
                'user_id'           => $user->id,
                'direccion'         => $data['direccion'],
                'tipo'              => $data['tipo'],
                'nombre'            => $data['nombre'],
                'cliente_id'        => $data['cliente_id'] ?? null,
                'proveedor_id'      => $data['proveedor_id'] ?? null,
                'monto_original'    => $data['monto_original'],
                'fecha_inicio'      => $data['fecha_inicio'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'observacion'       => $data['observacion'] ?? null,
                'saldo'             => $data['monto_original'],
                'estado'            => 'activa',
                'turno_id'          => $turnoId,
            ]);

            // Desembolso inicial. NO es una amortización: no toca el saldo (que
            // sigue siendo el monto por pagar/cobrar). Solo mueve el dinero:
            //   por_pagar  → nos ENTRA el préstamo   → INGRESO a la cuenta
            //   por_cobrar → SALE lo que prestamos    → EGRESO de la cuenta
            // ref_tipo='deuda', ref_id=deuda->id → un único asiento reversible.
            if ($registrarCaja) {
                $esIngreso = $deuda->direccion === Deuda::DIRECCION_POR_PAGAR;
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                    $user,
                    $data['fecha_inicio'],
                    $esIngreso ? 'ingreso' : 'egreso',
                    (float) $data['monto_original'],
                    "Desembolso de deuda — {$deuda->nombre}",
                    'deuda',
                    $deuda->id,
                );
            }

            return $deuda;
        });

        AuditoriaService::log('deuda.creada', $deuda, [
            'nombre'     => $deuda->nombre,
            'direccion'  => $deuda->direccion,
            'monto'      => (float) $deuda->monto_original,
            'desembolso' => $registrarCaja,
        ], $user);

        return back()->with('success', 'Deuda registrada correctamente.');
    }

    /**
     * Movimiento sobre la deuda:
     *  - amortizacion: baja el saldo (cuota pagada / cobro del préstamo).
     *  - incremento: sube el saldo (nuevo desembolso sobre la misma línea).
     */
    public function registrarPago(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);
        abort_unless($deuda->estado === 'activa', 422, 'La deuda no está activa.');

        // Cruces (solo deudas VINCULADAS a un tercero, tipo amortización, sin
        // método de pago porque NO mueve caja):
        //  - cliente_anticipo_id: cobrar la deuda por cobrar del anticipo del cliente.
        //  - compensar_venta_id:  compensar deuda por pagar con una venta CxC del cliente.
        //  - compensar_entrada_id: compensar deuda por cobrar con una compra CxP del proveedor.
        $esCruce = $request->filled('cliente_anticipo_id')
            || $request->filled('compensar_venta_id')
            || $request->filled('compensar_entrada_id');

        $rules = [
            'tipo'           => ['required', Rule::in(['amortizacion', 'incremento'])],
            'fecha'          => ['required', 'date', new \App\Rules\NoFutura],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => [$esCruce ? 'nullable' : 'required', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'observacion'    => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno cuya caja mueve el efectivo de esta cuota.
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
            'cliente_anticipo_id'  => ['nullable', 'integer', 'prohibits:compensar_venta_id,compensar_entrada_id', Rule::exists('cliente_anticipos', 'id')->where('empresa_id', $user->empresa_id)],
            'compensar_venta_id'   => ['nullable', 'integer', 'prohibits:compensar_entrada_id', Rule::exists('ventas', 'id')->where('empresa_id', $user->empresa_id)],
            'compensar_entrada_id' => ['nullable', 'integer', Rule::exists('entradas', 'id')->where('empresa_id', $user->empresa_id)],
        ];

        if ($request->input('tipo') === 'amortizacion') {
            $rules['monto'][] = 'max:' . (float) $deuda->saldo;
        }

        $data = $request->validate($rules);

        if ($esCruce) {
            abort_unless($data['tipo'] === 'amortizacion', 422, 'Los cruces solo aplican a amortizaciones.');
            return $this->registrarCruce($deuda, $data, $user);
        }

        $data['turno_id'] = AfectaCaja::resolverTurno($user, 'deuda', $data['turno_id'] ?? null);

        DB::transaction(function () use ($deuda, $user, $data) {
            $pago = $deuda->pagos()->create($data + ['user_id' => $user->id]);

            // F7 — Tesorería. La dirección del dinero depende de quién debe:
            //   por_pagar  + amortización → pagamos cuota        → EGRESO
            //   por_pagar  + incremento   → nos desembolsan más   → INGRESO
            //   por_cobrar + amortización → nos pagan la cuota    → INGRESO
            //     (ej. el trabajador paga su cuota semanal de la moto)
            //   por_cobrar + incremento   → prestamos más dinero  → EGRESO
            $esIngreso = ($deuda->direccion === Deuda::DIRECCION_POR_PAGAR) === ($data['tipo'] === 'incremento');
            $verbo     = $data['tipo'] === 'amortizacion' ? 'Cuota' : 'Incremento';
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                $user,
                $data['fecha'],
                $esIngreso ? 'ingreso' : 'egreso',
                (float) $data['monto'],
                "{$verbo} de deuda — {$deuda->nombre}",
                'deuda_pago',
                $pago->id,
            );

            $deuda->recalcularSaldo();

            AuditoriaService::log('deuda.' . $data['tipo'], $deuda, [
                'monto' => (float) $data['monto'],
                'saldo' => (float) $deuda->saldo,
            ], $user);
        });

        return back()->with('success', 'Movimiento registrado correctamente.');
    }

    /**
     * Amortiza la deuda CRUZANDO con otro módulo (sin mover caja):
     * anticipo del cliente, venta CxC o compra CxP del tercero vinculado.
     */
    private function registrarCruce(Deuda $deuda, array $data, \App\Models\User $user)
    {
        $monto = (float) $data['monto'];
        $comp  = app(\App\Services\CompensacionCxcCxpService::class);

        // ── Cobrar del ANTICIPO del cliente vinculado (deuda por cobrar) ─────
        if (!empty($data['cliente_anticipo_id'])) {
            abort_unless($deuda->direccion === Deuda::DIRECCION_POR_COBRAR, 422,
                'Solo una deuda POR COBRAR se cobra con el anticipo del cliente.');
            abort_unless($deuda->cliente_id, 422, 'La deuda no está vinculada a un cliente.');

            DB::transaction(function () use ($deuda, $data, $user, $monto) {
                $deuda = $this->bloquearDeudaParaAmortizar($deuda, $monto);

                $anticipo = \App\Models\ClienteAnticipo::where('id', $data['cliente_anticipo_id'])
                    ->where('empresa_id', $user->empresa_id)
                    ->where('cliente_id', $deuda->cliente_id)
                    ->where('tipo_valorizacion', 'monto')
                    ->where('estado', 'activo')
                    ->lockForUpdate()
                    ->first();

                abort_unless($anticipo !== null, 422, 'El anticipo no está disponible para el cliente vinculado.');
                if ($monto > (float) $anticipo->saldo + 0.009) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'monto' => 'El anticipo solo tiene S/ ' . number_format((float) $anticipo->saldo, 2) . ' de saldo.',
                    ]);
                }

                $pago = $deuda->pagos()->create([
                    'user_id'             => $user->id,
                    'fecha'               => $data['fecha'],
                    'tipo'                => 'amortizacion',
                    'monto'               => $monto,
                    'observacion'         => trim("Cobrado del anticipo #{$anticipo->id}. " . ($data['observacion'] ?? '')) ?: null,
                    'cliente_anticipo_id' => $anticipo->id,
                ]);

                $anticipo->aplicaciones()->create([
                    'empresa_id'    => $deuda->empresa_id,
                    'numero'        => \App\Models\ClienteAnticipoAplicacion::generarNumero($deuda->empresa_id),
                    'deuda_pago_id' => $pago->id,
                    'user_id'       => $user->id,
                    'fecha'         => $data['fecha'],
                    'monto'         => $monto,
                    'observacion'   => "Cobro de deuda «{$deuda->nombre}»",
                ]);
                $nuevoSaldo = round((float) $anticipo->saldo - $monto, 2);
                $anticipo->update([
                    'saldo'  => max(0, $nuevoSaldo),
                    'estado' => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
                ]);

                // SIN tesorería: el dinero del anticipo ya entró en su día.
                $deuda->recalcularSaldo();

                AuditoriaService::log('deuda.cobrada_con_anticipo', $deuda, [
                    'anticipo_id'    => $anticipo->id,
                    'monto'          => $monto,
                    'saldo_deuda'    => (float) $deuda->saldo,
                    'saldo_anticipo' => (float) $anticipo->saldo,
                ], $user);
            });

            return back()->with('success', 'Cuota cobrada del anticipo del cliente (sin mover caja).');
        }

        // ── Compensar con una VENTA CxC del cliente vinculado (deuda por pagar) ─
        if (!empty($data['compensar_venta_id'])) {
            abort_unless($deuda->direccion === Deuda::DIRECCION_POR_PAGAR, 422,
                'Solo una deuda POR PAGAR se compensa con lo que el cliente nos debe (CxC).');
            abort_unless($deuda->cliente_id, 422, 'La deuda no está vinculada a un cliente.');

            $venta = \App\Models\Venta::findOrFail($data['compensar_venta_id']);
            abort_unless($venta->cliente_id === $deuda->cliente_id, 422, 'La venta no es del cliente vinculado a esta deuda.');
            abort_unless($venta->es_credito && $venta->estado === 'completada', 422, 'La venta no es una venta a crédito activa.');
            abort_if((float) $venta->saldo_pendiente <= 0, 422, 'La venta ya está saldada.');
            if ($monto > (float) $venta->saldo_pendiente + 0.009) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'monto' => 'La venta solo tiene S/ ' . number_format((float) $venta->saldo_pendiente, 2) . ' de saldo.',
                ]);
            }

            DB::transaction(function () use ($comp, $deuda, $venta, $monto, $data, $user) {
                $deuda = $this->bloquearDeudaParaAmortizar($deuda, $monto);
                $venta = \App\Models\Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
                abort_unless($venta->es_credito && $venta->estado === 'completada', 422, 'La venta no es una venta a crédito activa.');
                if ($monto > (float) $venta->saldo_pendiente + 0.009) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'monto' => 'La venta solo tiene S/ ' . number_format((float) $venta->saldo_pendiente, 2) . ' de saldo (recién actualizado).',
                    ]);
                }

                $comp->compensarDeudaConVenta($deuda, $venta, $monto, $data['fecha'], $data['observacion'] ?? null, $user);
            });

            return back()->with('success', 'Deuda compensada contra la venta al crédito (sin mover caja).');
        }

        // ── Compensar con una COMPRA CxP del proveedor vinculado (deuda por cobrar) ─
        abort_unless($deuda->direccion === Deuda::DIRECCION_POR_COBRAR, 422,
            'Solo una deuda POR COBRAR se compensa con lo que le debemos al proveedor (CxP).');
        abort_unless($deuda->proveedor_id, 422, 'La deuda no está vinculada a un proveedor.');

        $entrada = \App\Models\Entrada::findOrFail($data['compensar_entrada_id']);
        abort_unless($entrada->proveedor_id === $deuda->proveedor_id, 422, 'La compra no es del proveedor vinculado a esta deuda.');
        abort_unless(in_array($entrada->estado, [\App\Models\Entrada::ESTADO_CONFIRMADO, \App\Models\Entrada::ESTADO_EN_TRANSITO], true),
            422, 'Solo se puede compensar contra compras confirmadas o en tránsito.');
        abort_if($entrada->saldoPendiente() <= 0, 422, 'La compra ya está pagada.');
        if ($monto > $entrada->saldoPendiente() + 0.009) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'monto' => 'La compra solo tiene S/ ' . number_format($entrada->saldoPendiente(), 2) . ' de saldo.',
            ]);
        }

        DB::transaction(function () use ($comp, $deuda, $entrada, $monto, $data, $user) {
            $deuda   = $this->bloquearDeudaParaAmortizar($deuda, $monto);
            $entrada = \App\Models\Entrada::whereKey($entrada->id)->lockForUpdate()->firstOrFail();
            if ($monto > $entrada->saldoPendiente() + 0.009) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'monto' => 'La compra solo tiene S/ ' . number_format($entrada->saldoPendiente(), 2) . ' de saldo (recién actualizado).',
                ]);
            }

            $comp->compensarDeudaConEntrada($deuda, $entrada, $monto, $data['fecha'], $data['observacion'] ?? null, $user);
        });

        return back()->with('success', 'Deuda compensada contra la compra (sin mover caja).');
    }

    /**
     * Relee la deuda con bloqueo (dentro de la transacción) y revalida que siga
     * activa y que la amortización no supere su saldo de ESE momento.
     */
    private function bloquearDeudaParaAmortizar(Deuda $deuda, float $monto): Deuda
    {
        $deuda = Deuda::whereKey($deuda->id)->lockForUpdate()->firstOrFail();
        abort_unless($deuda->estado === 'activa', 422, 'La deuda no está activa.');
        if ($monto > (float) $deuda->saldo + 0.009) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'monto' => 'El monto supera el saldo actual de la deuda (S/ ' . number_format((float) $deuda->saldo, 2) . '). Puede que otro usuario acabe de registrar un movimiento.',
            ]);
        }

        return $deuda;
    }

    /**
     * Edita un movimiento ya registrado de una deuda (fecha, tipo, monto,
     * método/cuenta u observación). Revierte el asiento anterior en tesorería,
     * actualiza el pago, recalcula el saldo y registra el nuevo asiento.
     */
    public function editarPago(Request $request, DeudaPago $pago)
    {
        $user  = $request->user();
        $deuda = $pago->deuda;
        abort_if(!$deuda || $deuda->empresa_id !== $user->empresa_id, 403);
        abort_unless($deuda->estado === 'activa', 422, 'La deuda no está activa.');
        // Un movimiento cruzado con otro módulo (anticipo/CxC/CxP) no se edita:
        // desalinearía a la contraparte. Se anula (revierte ambos lados) y se rehace.
        abort_if($pago->esCruce(), 422,
            'Este movimiento cruza con otro módulo (anticipo/venta/compra): no se edita. Anúlalo — revierte ambos lados — y regístralo de nuevo.');
        // Una compensación entre dos deudas tampoco: su pareja quedaría desalineada
        // (y convertirla en cuota inventaría un movimiento de caja).
        abort_if($pago->tipo === 'compensacion', 422,
            'Este movimiento es una compensación con otra deuda: no se edita. Elimínalo — revierte las dos deudas — y vuelve a compensar.');

        // Límite para amortizaciones: no puede hacer que el saldo quede negativo.
        // Las compensaciones también bajan el saldo (igual que en recalcularSaldo).
        $incrementosOtros = (float) $deuda->pagos()->where('tipo', 'incremento')->where('id', '!=', $pago->id)->sum('monto');
        $amortizacionesOtros = (float) $deuda->pagos()->whereIn('tipo', ['amortizacion', 'compensacion'])->where('id', '!=', $pago->id)->sum('monto');
        $maxAmortizacion = max(0, (float) $deuda->monto_original + $incrementosOtros - $amortizacionesOtros);

        $rules = [
            'tipo'           => ['required', Rule::in(['amortizacion', 'incremento'])],
            'fecha'          => ['required', 'date', new \App\Rules\NoFutura],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'observacion'    => ['nullable', 'string', 'max:500'],
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ];

        if ($request->input('tipo') === 'amortizacion') {
            $rules['monto'][] = 'max:' . $maxAmortizacion;
        }

        $data = $request->validate($rules);
        $data['turno_id'] = AfectaCaja::resolverTurno($user, 'deuda', $data['turno_id'] ?? null);

        DB::transaction(function () use ($pago, $deuda, $user, $data) {
            $this->tesoreria->revertir('deuda_pago', $pago->id);

            $pago->update([
                'tipo'           => $data['tipo'],
                'fecha'          => $data['fecha'],
                'monto'          => $data['monto'],
                'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
                'cuenta_id'      => $data['cuenta_id'] ?? null,
                'observacion'    => $data['observacion'] ?? null,
                'turno_id'       => $data['turno_id'] ?? null,
                'user_id'        => $user->id,
            ]);

            $esIngreso = ($deuda->direccion === Deuda::DIRECCION_POR_PAGAR) === ($data['tipo'] === 'incremento');
            $verbo     = $data['tipo'] === 'amortizacion' ? 'Cuota' : 'Incremento';
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                $user,
                $data['fecha'],
                $esIngreso ? 'ingreso' : 'egreso',
                (float) $data['monto'],
                "{$verbo} de deuda — {$deuda->nombre}",
                'deuda_pago',
                $pago->id,
            );

            $deuda->recalcularSaldo();

            AuditoriaService::log('deuda.movimiento_editado', $deuda, [
                'movimiento_id' => $pago->id,
                'nuevo' => [
                    'tipo'  => $data['tipo'],
                    'fecha' => $data['fecha'],
                    'monto' => (float) $data['monto'],
                ],
                'saldo' => (float) $deuda->saldo,
            ], $user);
        });

        return back()->with('success', 'Movimiento editado correctamente.');
    }

    /**
     * Anula (oculta del balance) una deuda sin tocar tesorería, igual que con
     * las cuotas: anular es un "ocultar" reversible, no una reversión de caja.
     * El desembolso y los movimientos siguen en tesorería y se recuperan al
     * reactivar. Para deshacer el dinero por completo usa "eliminar" (destroy).
     */
    public function anular(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);
        abort_unless($deuda->estado === 'activa', 422, 'La deuda no está activa.');

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        // Un cruce (anticipo, venta, compra u otra deuda) movió saldos de OTRO
        // módulo: anular solo esta mitad los dejaría descuadrados.
        $cruces = $deuda->pagos()->get()->filter(fn (DeudaPago $p) => $p->esCruce() || $p->tipo === 'compensacion');
        abort_if($cruces->isNotEmpty(), 422, 'Esta deuda tiene cruces o compensaciones con otros documentos: anúlalos primero desde sus movimientos.');

        DB::transaction(function () use ($deuda, $user, $data) {
            // Bloquear y re-chequear: con un doble clic la segunda petición
            // llegaba con la deuda aún 'activa' en memoria y dejaba otro log
            // "anulada" sin asientos; reactivar tomaba ese y el dinero no volvía.
            $deuda = Deuda::whereKey($deuda->id)->lockForUpdate()->firstOrFail();
            abort_unless($deuda->estado === 'activa', 422, 'La deuda no está activa.');

            // Anular deshace TODO su dinero (desembolso y cuotas): si no, la deuda
            // sale del balance pero su plata se queda en las cuentas e infla el
            // patrimonio. Los asientos se guardan para que "Reactivar" los devuelva.
            $movimientos = $this->movimientosTesoreria($deuda)->orderBy('m.id')->get(['m.*'])
                ->map(fn ($r) => (array) $r)->values()->all();

            $log = AuditoriaService::log('deuda.anulada', $deuda, [
                'motivo'                => $data['motivo'],
                'saldo'                 => (float) $deuda->saldo,
                'movimientos_revertidos' => $movimientos,
            ], $user);
            abort_if(!$log, 500, 'No se pudo guardar el respaldo de su dinero; no se anuló. Intenta de nuevo.');

            $this->tesoreria->revertir('deuda', $deuda->id);
            foreach ($deuda->pagos()->pluck('id') as $pagoId) {
                $this->tesoreria->revertir('deuda_pago', $pagoId);
            }

            $deuda->update(['estado' => 'anulada']);
        });

        return back()->with('success', 'Deuda anulada: su dinero se retiró de las cuentas. Si fue un error, reactívala y vuelve tal cual.');
    }

    /**
     * Qué pasa con el dinero si se anula esta deuda (lo mismo que al eliminar,
     * pero se conserva el registro). Lo muestra el modal antes de confirmar.
     */
    public function impactoAnular(Request $request, Deuda $deuda)
    {
        return $this->impactoEliminar($request, $deuda);
    }

    /**
     * Edita la cabecera de una deuda (nombre, tipo, fechas, monto original,
     * observación). Si cambia el monto original, el saldo se ajusta por el
     * MISMO delta (los movimientos ya registrados se respetan). No se editan
     * deudas anuladas: primero reactivar.
     */
    public function update(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);
        abort_unless($deuda->estado !== 'anulada', 422, 'La deuda está anulada: reactívala antes de editarla.');

        // Lo ya amortizado (neto) no puede quedar "flotando": el monto original
        // nuevo debe cubrir al menos lo que ya se pagó/cobró de esta deuda.
        $amortizadoNeto = round((float) $deuda->monto_original - (float) $deuda->saldo, 2);

        $data = $request->validate([
            'tipo'              => ['required', Rule::in(['bancaria', 'personal', 'trabajador', 'otro'])],
            'nombre'            => ['required', 'string', 'max:200'],
            'cliente_id'        => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $user->empresa_id), 'prohibits:proveedor_id'],
            'proveedor_id'      => ['nullable', 'integer', Rule::exists('proveedores', 'id')->where('empresa_id', $user->empresa_id)],
            'monto_original'    => ['required', 'numeric', 'min:0.01', 'gte:' . max(0.01, $amortizadoNeto)],
            'fecha_inicio'      => ['required', 'date', new \App\Rules\NoFutura],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'observacion'       => ['nullable', 'string', 'max:500'],
        ], [
            'monto_original.gte' => "El monto original no puede ser menor a lo ya amortizado (S/ " . number_format($amortizadoNeto, 2) . ').',
        ]);

        $antes = [
            'nombre'         => $deuda->nombre,
            'tipo'           => $deuda->tipo,
            'monto_original' => (float) $deuda->monto_original,
            'saldo'          => (float) $deuda->saldo,
            'fecha_inicio'   => $deuda->fecha_inicio?->toDateString(),
        ];

        // El saldo se mueve por el delta del monto original.
        $delta      = round((float) $data['monto_original'] - (float) $deuda->monto_original, 2);
        $nuevoSaldo = max(0, round((float) $deuda->saldo + $delta, 2));

        DB::transaction(function () use ($deuda, $data, $nuevoSaldo) {
            $deuda->update($data + [
                'saldo'  => $nuevoSaldo,
                'estado' => $nuevoSaldo <= 0.01 ? 'pagada' : 'activa',
            ]);

            // Mantener coherente el desembolso inicial (si existe): su monto y
            // fecha siguen al principal. No se toca la cuenta ni la dirección
            // (la edición no las cambia). Si no hubo desembolso, no crea nada.
            CuentaMovimiento::where('ref_tipo', 'deuda')->where('ref_id', $deuda->id)->update([
                'monto' => round((float) $data['monto_original'], 2),
                'fecha' => substr($data['fecha_inicio'], 0, 10),
            ]);
        });

        AuditoriaService::log('deuda.editada', $deuda, [
            'antes'   => $antes,
            'despues' => [
                'nombre'         => $deuda->nombre,
                'tipo'           => $deuda->tipo,
                'monto_original' => (float) $deuda->monto_original,
                'saldo'          => (float) $deuda->saldo,
            ],
        ], $user);

        return back()->with('success', 'Deuda actualizada.');
    }

    /**
     * Reactiva una deuda anulada (se anuló por error). Vuelve a 'activa' con
     * el saldo que tenía; si ya estaba saldada queda 'pagada'.
     */
    public function reactivar(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);
        abort_unless($deuda->estado === 'anulada', 422, 'Solo se pueden reactivar deudas anuladas.');

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($deuda, $user, $data) {
            // Bloquear y re-chequear: un doble envío no repone dos veces.
            $deuda = Deuda::whereKey($deuda->id)->lockForUpdate()->firstOrFail();
            abort_unless($deuda->estado === 'anulada', 422, 'Solo se pueden reactivar deudas anuladas.');

            // Devuelve el dinero que se retiró al anular (las anulaciones de
            // antes del 04/10/2026 no lo retiraban: no hay nada que devolver).
            $log = \App\Models\Auditoria::deEmpresa($deuda->empresa_id)
                ->where('accion', 'deuda.anulada')->where('modelo_id', $deuda->id)
                ->orderByDesc('id')->first();
            $devueltos = 0;
            foreach (($log?->contexto ?? [])['movimientos_revertidos'] ?? [] as $m) {
                // No revivir el dinero de una cuota que se eliminó mientras estaba anulada.
                if ($m['ref_tipo'] === 'deuda_pago'
                    && ! DB::table('deuda_pagos')->where('id', $m['ref_id'])->whereNull('deleted_at')->exists()) {
                    continue;
                }
                if (DB::table('cuenta_movimientos')->where('id', $m['id'])->exists()) continue;
                DB::table('cuenta_movimientos')->insert($m);
                $devueltos++;
            }

            // El saldo se recalcula desde sus movimientos vivos: mientras estuvo
            // anulada recalcularSaldo() no corre, así que una cuota eliminada en
            // ese lapso dejaba el saldo viejo. recalcularSaldo decide activa/pagada.
            $deuda->update(['estado' => 'activa']);
            $deuda->recalcularSaldo();

            AuditoriaService::log('deuda.reactivada', $deuda, [
                'motivo'                => $data['motivo'],
                'saldo'                 => (float) $deuda->saldo,
                'movimientos_devueltos' => $devueltos,
            ], $user);
        });

        return back()->with('success', 'Deuda reactivada: vuelve al balance y su dinero a las cuentas.');
    }

    /**
     * Elimina una deuda (registro erróneo/duplicado): revierte los asientos de
     * tesorería de todos sus movimientos y borra el registro.
     *
     * Antes de borrar guarda un RESPALDO completo en la auditoría (las filas tal
     * cual: deuda, movimientos y asientos de caja/banco con su cuenta). Con eso
     * "Restaurar" la devuelve idéntica, con el mismo id. Sin respaldo no se
     * borra: una eliminación sin vuelta atrás fue lo que costó el préstamo de
     * HYC (17/09).
     */
    public function destroy(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        // Mismo criterio que anular: un cruce o compensación bajó el saldo de
        // OTRO documento (anticipo, venta, compra u otra deuda). Borrar solo esta
        // mitad dejaría al hermano descontado contra una deuda que ya no existe.
        $cruces = $deuda->pagos()->get()->filter(fn (DeudaPago $p) => $p->esCruce() || $p->tipo === 'compensacion');
        abort_if($cruces->isNotEmpty(), 422, 'Esta deuda tiene cruces o compensaciones con otros documentos: elimínalos primero desde sus movimientos (eso revierte los dos lados) y luego elimina la deuda.');

        DB::transaction(function () use ($deuda, $user, $data) {
            $deuda->loadMissing('pagos');
            $respaldo = $this->respaldoDe($deuda);

            $log = AuditoriaService::log('deuda.eliminada', $deuda, [
                'motivo'   => $data['motivo'],
                'snapshot' => [
                    'nombre'         => $deuda->nombre,
                    // La fecha permite saber qué balances cerrados cambian al eliminarla.
                    'fecha_inicio'   => $deuda->fecha_inicio?->toDateString(),
                    'direccion'      => $deuda->direccion,
                    'tipo'           => $deuda->tipo,
                    'monto_original' => (float) $deuda->monto_original,
                    'saldo'          => (float) $deuda->saldo,
                    'estado'         => $deuda->estado,
                    'movimientos'    => $deuda->pagos->map(fn ($p) => [
                        'fecha' => $p->fecha->toDateString(),
                        'tipo'  => $p->tipo,
                        'monto' => (float) $p->monto,
                    ])->all(),
                ],
                'respaldo' => $respaldo,
            ], $user);

            // La auditoría nunca bloquea... salvo aquí: sin respaldo no hay restaurar.
            abort_if(!$log, 500, 'No se pudo guardar el respaldo de la deuda; no se eliminó. Intenta de nuevo.');

            // Revertir el dinero de cada movimiento en tesorería.
            foreach ($deuda->pagos as $pago) {
                $this->tesoreria->revertir('deuda_pago', $pago->id);
            }

            // Revertir también el desembolso inicial (ingreso/egreso al crear).
            $this->tesoreria->revertir('deuda', $deuda->id);

            $deuda->pagos()->delete();
            $deuda->delete();
        });

        return back()->with('success', 'Deuda eliminada y su dinero revertido. Si fue un error, la puedes restaurar desde "Eliminadas".');
    }

    /**
     * Qué pasa con el dinero si se elimina esta deuda: cada asiento de
     * tesorería que se revierte, por cuenta. Lo muestra el modal ANTES de
     * eliminar, para que nadie borre la fila equivocada a ciegas.
     */
    public function impactoEliminar(Request $request, Deuda $deuda)
    {
        abort_if($deuda->empresa_id !== $request->user()->empresa_id, 403);

        $movs = $this->movimientosTesoreria($deuda)
            ->leftJoin('cuentas as c', 'c.id', '=', 'm.cuenta_id')
            ->orderBy('m.fecha')->orderBy('m.id')
            ->get(['m.fecha', 'm.tipo', 'm.monto', 'm.descripcion', 'c.nombre as cuenta']);

        return response()->json([
            // Al eliminar, cada asiento se deshace: un ingreso SALE de la cuenta.
            'movimientos' => $movs->map(fn ($m) => [
                'fecha'       => substr((string) $m->fecha, 0, 10),
                'cuenta'      => $m->cuenta ?? 'Cuenta eliminada',
                'descripcion' => $m->descripcion,
                'efecto'      => round(($m->tipo === 'ingreso' ? -1 : 1) * (float) $m->monto, 2),
            ])->values(),
            'cantidad_pagos' => $deuda->pagos()->count(),
        ]);
    }

    /**
     * Deudas eliminadas (la papelera): sale de la auditoría, la última
     * eliminación de cada deuda que hoy no existe.
     */
    public function eliminadas(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $logs = \App\Models\Auditoria::deEmpresa($empresaId)
            ->where('accion', 'deuda.eliminada')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->unique('modelo_id');

        $vivas = Deuda::where('empresa_id', $empresaId)->whereIn('id', $logs->pluck('modelo_id')->filter())->pluck('id')->all();

        return response()->json($logs
            ->reject(fn ($l) => in_array($l->modelo_id, $vivas))
            ->map(function ($l) {
                $ctx  = $l->contexto ?? [];
                $snap = $ctx['snapshot'] ?? [];
                return [
                    'auditoria_id'   => $l->id,
                    'deuda_id'       => $l->modelo_id,
                    'nombre'         => $snap['nombre'] ?? "Deuda #{$l->modelo_id}",
                    'direccion'      => $snap['direccion'] ?? null,
                    'monto_original' => (float) ($snap['monto_original'] ?? 0),
                    'saldo'          => (float) ($snap['saldo'] ?? 0),
                    'fecha_inicio'   => $snap['fecha_inicio'] ?? null,
                    'motivo'         => $ctx['motivo'] ?? null,
                    'eliminada_por'  => $l->user_name,
                    'eliminada_el'   => $l->created_at?->format('d/m/Y H:i'),
                    // Las eliminadas antes de que existiera el respaldo no se pueden devolver solas.
                    'restaurable'    => !empty($ctx['respaldo']['deuda']),
                ];
            })
            ->values());
    }

    /**
     * Devuelve una deuda eliminada exactamente como estaba: mismas filas y
     * mismo id (deuda, movimientos y asientos de caja/banco). El dinero vuelve
     * a sus cuentas con sus fechas originales.
     */
    public function restaurar(Request $request, int $auditoria)
    {
        $user = $request->user();

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $log = \App\Models\Auditoria::deEmpresa($user->empresa_id)
            ->where('accion', 'deuda.eliminada')
            ->findOrFail($auditoria);

        $respaldo = ($log->contexto ?? [])['respaldo'] ?? null;
        abort_if(empty($respaldo['deuda']), 422, 'Esta deuda se eliminó antes de que existiera la papelera: no hay respaldo para devolverla sola. Regístrala de nuevo.');

        $fila = $respaldo['deuda'];
        abort_if((int) $fila['empresa_id'] !== (int) $user->empresa_id, 403);
        abort_if(DB::table('deudas')->where('id', $fila['id'])->exists(), 422, 'Esta deuda ya fue restaurada.');

        // El dinero vuelve a sus cuentas: tienen que seguir existiendo.
        $cuentas = collect($respaldo['movimientos'] ?? [])->pluck('cuenta_id')->filter()->unique();
        $faltan  = $cuentas->diff(DB::table('cuentas')->whereIn('id', $cuentas)->pluck('id'));
        abort_if($faltan->isNotEmpty(), 422, 'Una de las cuentas de esta deuda ya no existe; no se puede devolver el dinero a su lugar.');

        DB::transaction(function () use ($respaldo) {
            DB::table('deudas')->insert($respaldo['deuda']);
            foreach ($respaldo['pagos'] ?? [] as $p) {
                DB::table('deuda_pagos')->insert($p);
            }
            foreach ($respaldo['movimientos'] ?? [] as $m) {
                DB::table('cuenta_movimientos')->insert($m);
            }
        });

        $deuda = Deuda::find($fila['id']);
        AuditoriaService::log('deuda.restaurada', $deuda, [
            'motivo'       => $data['motivo'],
            'auditoria_id' => $log->id,
            'nombre'       => $deuda?->nombre,
            'monto'        => (float) ($deuda?->monto_original ?? 0),
        ], $user);

        return back()->with('success', "Deuda \"{$deuda?->nombre}\" restaurada: su dinero volvió a las cuentas con sus fechas originales.");
    }

    /** Asientos de tesorería de la deuda: su desembolso y los de cada movimiento. */
    private function movimientosTesoreria(Deuda $deuda)
    {
        $pagoIds = DB::table('deuda_pagos')->where('deuda_id', $deuda->id)->pluck('id');

        return DB::table('cuenta_movimientos as m')
            ->where('m.empresa_id', $deuda->empresa_id)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('m.ref_tipo', 'deuda')->where('m.ref_id', $deuda->id))
                ->orWhere(fn ($w) => $w->where('m.ref_tipo', 'deuda_pago')->whereIn('m.ref_id', $pagoIds)));
    }

    /** Filas tal cual, para restaurar sin perder nada (ni los movimientos ya anulados). */
    private function respaldoDe(Deuda $deuda): array
    {
        $comoArray = fn ($rows) => $rows->map(fn ($r) => (array) $r)->values()->all();

        return [
            'deuda'       => (array) DB::table('deudas')->where('id', $deuda->id)->first(),
            'pagos'       => $comoArray(DB::table('deuda_pagos')->where('deuda_id', $deuda->id)->orderBy('id')->get()),
            'movimientos' => $comoArray($this->movimientosTesoreria($deuda)->orderBy('m.id')->get(['m.*'])),
        ];
    }

    /**
     * Elimina un movimiento puntual (cuota/incremento mal registrado):
     * revierte su asiento en tesorería y recalcula el saldo de la deuda.
     */
    public function eliminarPago(Request $request, \App\Models\DeudaPago $pago)
    {
        $user  = $request->user();
        $deuda = $pago->deuda;
        abort_if(!$deuda || $deuda->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($pago, $deuda, $user, $data) {
            // Cruce con otro módulo: revertir también a la contraparte
            // (anticipo del cliente / abono de venta / pago de compra). SIN
            // tesorería: estos movimientos nunca movieron caja.
            if ($pago->esCruce()) {
                $comp = app(\App\Services\CompensacionCxcCxpService::class);

                if ($pago->cliente_anticipo_id) {
                    $anticipo = \App\Models\ClienteAnticipo::whereKey($pago->cliente_anticipo_id)
                        ->lockForUpdate()->first();
                    // Un anticipo devuelto/anulado no recupera saldo: ese dinero ya salió.
                    abort_if($anticipo && in_array($anticipo->estado, ['devuelto', 'anulado'], true), 422,
                        "El anticipo #{$anticipo?->id} con que se cobró esta cuota ya está {$anticipo?->estado}: su saldo no puede volver. Reactiva primero el anticipo en Finanzas → Anticipos.");
                    if ($anticipo) {
                        $anticipo->aplicaciones()->where('deuda_pago_id', $pago->id)->delete();
                        $anticipo->update([
                            'saldo'  => round((float) $anticipo->saldo + (float) $pago->monto, 2),
                            'estado' => 'activo',
                        ]);
                    }
                } elseif ($pago->compensacion_venta_id) {
                    $comp->revertirLadoVenta($pago->compensacion_grupo_id, $user);
                } elseif ($pago->compensacion_entrada_id) {
                    $comp->revertirLadoEntrada($pago->compensacion_grupo_id, $user);
                }

                $pago->delete();
                $deuda->recalcularSaldo();

                AuditoriaService::log('deuda.cruce_anulado', $deuda, [
                    'motivo'  => $data['motivo'],
                    'pago_id' => $pago->id,
                    'monto'   => (float) $pago->monto,
                    'saldo'   => (float) $deuda->saldo,
                ], $user);

                return;
            }

            // Si es una compensación, eliminamos el par de movimientos.
            if ($pago->tipo === 'compensacion' && $pago->compensacion_grupo_id) {
                $grupoId = $pago->compensacion_grupo_id;
                $companions = DeudaPago::where('compensacion_grupo_id', $grupoId)->get();
                $deudaIds   = $companions->pluck('deuda_id')->unique()->all();

                foreach ($companions as $comp) {
                    $comp->delete();
                }

                foreach ($deudaIds as $did) {
                    Deuda::find($did)?->recalcularSaldo();
                }

                AuditoriaService::log('deuda.compensacion_eliminada', $deuda, [
                    'motivo'   => $data['motivo'],
                    'grupo_id' => $grupoId,
                ], $user);

                return;
            }

            $this->tesoreria->revertir('deuda_pago', $pago->id);
            $pago->delete();
            $deuda->recalcularSaldo();

            AuditoriaService::log('deuda.movimiento_eliminado', $deuda, [
                'motivo'     => $data['motivo'],
                'movimiento' => [
                    'fecha' => $pago->fecha->toDateString(),
                    'tipo'  => $pago->tipo,
                    'monto' => (float) $pago->monto,
                ],
                'saldo' => (float) $deuda->saldo,
            ], $user);
        });

        return back()->with('success', 'Movimiento eliminado: tesorería y el saldo de la deuda se recalcularon.');
    }

    /**
     * Devuelve los movimientos de una deuda, activos y/o eliminados,
     * para el modal de movimientos.
     */
    public function movimientos(Request $request, Deuda $deuda)
    {
        abort_if($deuda->empresa_id !== $request->user()->empresa_id, 403);

        $todos = $request->boolean('todos');

        $pagos = $deuda->pagos()
            ->with(['metodoPago:id,nombre', 'cuenta:id,nombre', 'user:id,name', 'compensacionDeuda:id,nombre'])
            ->when($todos, fn ($q) => $q->withTrashed())
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (\App\Models\DeudaPago $p) => [
                'id'           => $p->id,
                'fecha'        => $p->fecha->toDateString(),
                'tipo'         => $p->tipo,
                'monto'        => $p->monto,
                'observacion'  => $p->observacion,
                'metodo_pago'  => $p->metodoPago ? ['nombre' => $p->metodoPago->nombre] : null,
                'cuenta'       => $p->cuenta ? ['nombre' => $p->cuenta->nombre] : null,
                'user'         => $p->user ? ['name' => $p->user->name] : null,
                'compensacion_deuda' => $p->compensacionDeuda ? ['id' => $p->compensacionDeuda->id, 'nombre' => $p->compensacionDeuda->nombre] : null,
                // Cruce con otro módulo: no editable (solo anular, revierte ambos lados).
                'es_cruce'     => $p->esCruce(),
                'eliminado'    => ! is_null($p->deleted_at),
                'deleted_at'   => $p->deleted_at?->format('d/m/Y H:i'),
            ]);

        return response()->json($pagos);
    }
}
