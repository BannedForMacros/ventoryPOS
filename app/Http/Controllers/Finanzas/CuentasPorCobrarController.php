<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Support\Xlsx;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\Turno;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use App\Support\AfectaCaja;
use App\Support\ExigeCuentaDePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Cuentas por cobrar: ventas a crédito con saldo pendiente y sus abonos.
 * Es el "DEUDAS POR COBRAR" del balance diario del cliente.
 */
class CuentasPorCobrarController extends Controller
{
    use ExigeCuentaDePago;

    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Venta::deEmpresa($user->empresa_id)
            ->where('es_credito', true)
            ->where('estado', 'completada')
            ->with(['cliente', 'items', 'caja:id,nombre', 'abonos.metodoPago', 'abonos.cuenta', 'abonos.user', 'pagos.metodoPago', 'user:id,name'])
            ->when($request->input('cliente_id'), fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->whereDate('fecha_venta', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->whereDate('fecha_venta', '<=', $v))
            // Búsqueda del lado del SERVIDOR (por número o cliente): así encuentra
            // la venta en TODO el histórico, no solo en la página cargada.
            ->when($request->input('busqueda'), function ($q, $b) {
                $q->where(function ($q) use ($b) {
                    $q->where('numero', 'ilike', "%{$b}%")
                      ->orWhereHas('cliente', function ($c) use ($b) {
                          $c->where('nombres', 'ilike', "%{$b}%")
                            ->orWhere('apellidos', 'ilike', "%{$b}%")
                            ->orWhere('razon_social', 'ilike', "%{$b}%")
                            ->orWhere('numero_documento', 'ilike', "%{$b}%");
                      });
                });
            });

        // Filtro de estado: 'pendientes' (default), 'saldadas' o 'todas'.
        $estado = $request->input('estado', 'pendientes');
        if ($estado === 'pendientes') {
            $query->where('saldo_pendiente', '>', 0);
        } elseif ($estado === 'saldadas') {
            $query->where('saldo_pendiente', '<=', 0);
        }

        $ventas = $query->orderByDesc('fecha_venta')->paginate(25)->withQueryString();

        $totalPendiente = (float) Venta::deEmpresa($user->empresa_id)
            ->conSaldoPendiente()
            ->sum('saldo_pendiente');

        // KPIs de cabecera (siempre sobre el universo pendiente, no sobre el filtro)
        $basePendiente = Venta::deEmpresa($user->empresa_id)->conSaldoPendiente();
        $vencidas = (clone $basePendiente)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString());
        $kpis = [
            'ventas_con_saldo'   => (int)   (clone $basePendiente)->count(),
            'clientes_con_deuda' => (int)   (clone $basePendiente)->distinct()->count('cliente_id'),
            'vencidas'           => (int)   (clone $vencidas)->count(),
            'monto_vencido'      => round((float) (clone $vencidas)->sum('saldo_pendiente'), 2),
        ];

        return Inertia::render('Finanzas/CuentasPorCobrar', [
            'ventas'         => $ventas,
            'totalPendiente' => round($totalPendiente, 2),
            'kpis'           => $kpis,
            // Acciones visibles según la matriz de permisos del rol.
            'puede'          => [
                'editar'   => $user->tienePermiso('finanzas.cuentas-por-cobrar', 'editar'),
                'eliminar' => $user->tienePermiso('finanzas.cuentas-por-cobrar', 'eliminar'),
            ],
            'estado'         => $request->input('estado', 'pendientes'),
            'busqueda'       => (string) $request->input('busqueda', ''),
            'metodosPago'    => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'        => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
            // "Afecta caja a:" — turnos para elegir a qué caja entra el cobro (los de
            // hoy o abiertos ahora). null = no afecta ninguna caja.
            'turnos'         => Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where(fn ($q) => $q->whereDate('fecha_apertura', now()->toDateString())->orWhere('estado', 'abierto'))
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
            // Turno sugerido por defecto para el cobro: el propio abierto del usuario, o
            // el único abierto en su ámbito (misma auto-resolución que usa abonar()).
            'turnoActivoId'  => $this->turnoSugerido($user),
            // Compras con saldo para "Compensar con una compra (CxP)": lo que el
            // tercero nos debe se cancela contra lo que le debemos, sin mover caja.
            'comprasCompensables' => \App\Models\Entrada::deEmpresa($user->empresa_id)
                ->comprometido()
                ->whereRaw('total - monto_pagado > 0.01')
                ->with('proveedorRel:id,razon_social,nombre_comercial,numero_documento')
                ->orderByDesc('fecha')
                ->get(['id', 'correlativo', 'numero_documento', 'proveedor', 'proveedor_id', 'fecha', 'total', 'monto_pagado']),
            'puedeCompensar' => $user->tienePermiso('finanzas.cuentas-por-cobrar', 'crear')
                && $user->tienePermiso('finanzas.cuentas-por-pagar', 'crear'),
            // Anticipos de DINERO con saldo, por cliente: para cobrar la deuda
            // consumiendo el anticipo (espejo del adelanto de proveedor en CxP).
            'anticiposClientes' => \App\Models\ClienteAnticipo::deEmpresa($user->empresa_id)
                ->activo()
                ->where('tipo_valorizacion', 'monto')
                ->where('saldo', '>', 0)
                ->get(['id', 'cliente_id', 'fecha', 'saldo']),
        ]);
    }

    /**
     * Exporta TODAS las cuentas por cobrar filtradas a CSV (Excel).
     * Respeta los mismos filtros que index() y no pagina.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        $query = Venta::deEmpresa($user->empresa_id)
            ->where('es_credito', true)
            ->where('estado', 'completada')
            ->with(['cliente'])
            ->when($request->input('cliente_id'), fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->whereDate('fecha_venta', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->whereDate('fecha_venta', '<=', $v))
            ->when($request->input('busqueda'), function ($q, $b) {
                $q->where(function ($q) use ($b) {
                    $q->where('numero', 'ilike', "%{$b}%")
                      ->orWhereHas('cliente', function ($c) use ($b) {
                          $c->where('nombres', 'ilike', "%{$b}%")
                            ->orWhere('apellidos', 'ilike', "%{$b}%")
                            ->orWhere('razon_social', 'ilike', "%{$b}%")
                            ->orWhere('numero_documento', 'ilike', "%{$b}%");
                      });
                });
            });

        $estado = $request->input('estado', 'pendientes');
        if ($estado === 'pendientes') {
            $query->where('saldo_pendiente', '>', 0);
        } elseif ($estado === 'saldadas') {
            $query->where('saldo_pendiente', '<=', 0);
        }

        $ventas = $query->orderByDesc('fecha_venta')->get();

        $headers = ['Fecha', 'N°', 'Cliente', 'Total', 'Pagado', 'Saldo', 'Vence'];
        $filas = [];

        foreach ($ventas as $v) {
            $cliente = $v->cliente;
            $nombreCliente = $cliente?->razon_social
                ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''));

            $filas[] = [
                optional($v->fecha_venta)->format('d/m/Y') ?? '—',
                $v->numero ?? '—',
                $nombreCliente ?: '—',
                (float) $v->total,
                (float) $v->monto_pagado,
                (float) $v->saldo_pendiente,
                optional($v->fecha_vencimiento)?->format('d/m/Y') ?? '—',
            ];
        }

        return Xlsx::descargar($headers, $filas, 'cuentas_por_cobrar', [3 => true, 4 => true, 5 => true]);
    }

    /**
     * Turno al que se imputaría un cobro por defecto: 1) el turno propio abierto del
     * usuario; 2) si no tiene, el ÚNICO turno abierto en su ámbito; 3) ninguno.
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

    /**
     * Registra un abono (cobro parcial o total) sobre una venta a crédito.
     */
    public function abonar(Request $request, Venta $venta)
    {
        $user = $request->user();
        abort_if($venta->empresa_id !== $user->empresa_id, 403);
        abort_unless($venta->es_credito && $venta->estado === 'completada', 422, 'La venta no es una venta a crédito activa.');

        $data = $request->validate([
            'monto'          => ['required', 'numeric', 'min:0.01', 'max:' . (float) $venta->saldo_pendiente],
            'fecha'          => ['required', 'date'],
            // Sin método cuando se cobra consumiendo el anticipo del cliente.
            'metodo_pago_id' => ['required_without:cliente_anticipo_id', 'nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            // Cobro consumiendo un anticipo de DINERO del cliente: no entra
            // dinero nuevo (ya entró al crear el anticipo) → sin tesorería.
            'cliente_anticipo_id' => ['nullable', 'integer', Rule::exists('cliente_anticipos', 'id')->where('empresa_id', $user->empresa_id)],
            // Pago MIXTO: además del anticipo, un pago adicional con método/cuenta
            // (ese sí entra a caja). 'monto' = lo tomado del anticipo.
            'monto_adicional' => ['nullable', 'numeric', 'min:0.01'],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno de cuya caja entra el cobro. null = "Sin turno".
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        // ── Cobro con anticipo del cliente (espejo del adelanto en CxP) ──
        // Soporta pago MIXTO: 'monto' se toma del anticipo (sin caja) y
        // 'monto_adicional' entra con método/cuenta como un abono normal.
        if (!empty($data['cliente_anticipo_id'])) {
            $montoAnticipo  = (float) $data['monto'];
            $montoAdicional = (float) ($data['monto_adicional'] ?? 0);

            if ($montoAdicional > 0.009 && empty($data['metodo_pago_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'metodo_pago_id' => 'Elige el método del pago adicional.',
                ]);
            }
            if ($montoAnticipo + $montoAdicional > (float) $venta->saldo_pendiente + 0.009) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'monto_adicional' => 'Anticipo + pago adicional (S/ ' . number_format($montoAnticipo + $montoAdicional, 2)
                        . ') superan el saldo pendiente (S/ ' . number_format((float) $venta->saldo_pendiente, 2) . ').',
                ]);
            }

            // Turno del pago ADICIONAL (el del anticipo nunca toca caja).
            $turnoIdAdicional = $montoAdicional > 0.009
                ? ($request->has('turno_id')
                    ? AfectaCaja::resolverTurno($user, 'cxc', $data['turno_id'] ?? null, 'libre')
                    : $this->turnoSugerido($user))
                : null;

            DB::transaction(function () use ($venta, $user, $data, $montoAnticipo, $montoAdicional, $turnoIdAdicional) {
                $anticipo = \App\Models\ClienteAnticipo::where('id', $data['cliente_anticipo_id'])
                    ->where('empresa_id', $user->empresa_id)
                    ->where('cliente_id', $venta->cliente_id)
                    ->where('tipo_valorizacion', 'monto')
                    ->where('estado', 'activo')
                    ->lockForUpdate()
                    ->first();

                if (!$anticipo) {
                    abort(422, 'El anticipo no está disponible para este cliente.');
                }
                if ($montoAnticipo > (float) $anticipo->saldo + 0.009) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'monto' => 'El anticipo solo tiene S/ ' . number_format((float) $anticipo->saldo, 2)
                            . ' de saldo. Baja el monto y cobra el resto con otro método.',
                    ]);
                }

                // 1) Abono contra el anticipo — SIN tesorería (ese dinero ya entró).
                $abono = VentaAbono::create([
                    'venta_id'            => $venta->id,
                    'user_id'             => $user->id,
                    'fecha'               => $data['fecha'],
                    'monto'               => $montoAnticipo,
                    'observacion'         => trim("Cobrado del anticipo #{$anticipo->id}. " . ($data['observacion'] ?? '')) ?: null,
                    'cliente_anticipo_id' => $anticipo->id,
                ]);

                // Aplicación trazada + saldo del anticipo (mismo patrón que el POS).
                $anticipo->aplicaciones()->create([
                    'empresa_id'     => $venta->empresa_id,
                    'numero'         => \App\Models\ClienteAnticipoAplicacion::generarNumero($venta->empresa_id),
                    'venta_id'       => $venta->id,
                    'venta_abono_id' => $abono->id,
                    'user_id'        => $user->id,
                    'fecha'          => $data['fecha'],
                    'monto'          => $montoAnticipo,
                    'observacion'    => "Cobro de crédito — venta {$venta->numero}",
                ]);
                $nuevoSaldo = round((float) $anticipo->saldo - $montoAnticipo, 2);
                $anticipo->update([
                    'saldo'  => max(0, $nuevoSaldo),
                    'estado' => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
                ]);

                // 2) Pago ADICIONAL con método/cuenta — este SÍ entra a tesorería,
                //    como cualquier abono normal.
                if ($montoAdicional > 0.009) {
                    $abonoAdicional = VentaAbono::create([
                        'venta_id'       => $venta->id,
                        'user_id'        => $user->id,
                        'turno_id'       => $turnoIdAdicional,
                        'metodo_pago_id' => $data['metodo_pago_id'],
                        'cuenta_id'      => $data['cuenta_id'] ?? null,
                        'fecha'          => $data['fecha'],
                        'monto'          => $montoAdicional,
                        'referencia'     => $data['referencia'] ?? null,
                        'observacion'    => trim("Pago adicional al cobro con anticipo #{$anticipo->id}. " . ($data['observacion'] ?? '')) ?: null,
                    ]);

                    $clienteNombre = $venta->cliente?->razon_social
                        ?? trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''));
                    $this->tesoreria->registrar(
                        $user->empresa_id,
                        $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                        $user,
                        $data['fecha'],
                        'ingreso',
                        $montoAdicional,
                        "Abono venta {$venta->numero} — {$clienteNombre}",
                        'venta_abono',
                        $abonoAdicional->id,
                    );
                }

                $pagado = round((float) $venta->monto_pagado + $montoAnticipo + $montoAdicional, 2);
                $venta->update([
                    'monto_pagado'    => $pagado,
                    'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
                ]);

                AuditoriaService::log('cxc.abono_con_anticipo', $venta, [
                    'numero'          => $venta->numero,
                    'anticipo_id'     => $anticipo->id,
                    'monto_anticipo'  => $montoAnticipo,
                    'monto_adicional' => $montoAdicional,
                    'saldo_venta'     => (float) $venta->saldo_pendiente,
                    'saldo_anticipo'  => (float) $anticipo->saldo,
                ], $user);
            });

            $msj = 'Abono cobrado del anticipo del cliente (sin mover caja)';
            if ($montoAdicional > 0.009) $msj .= ' + pago adicional de S/ ' . number_format($montoAdicional, 2);
            return back()->with('success', $msj . '.');
        }

        // Turno al que se imputa el abono (y si es efectivo, suma a esa caja):
        // si el front manda 'turno_id' (aunque sea null = "Sin turno"), se respeta
        // (gateado por config, módulo 'cxc', modo libre). Si NO lo manda
        // (llamadores viejos), se auto-resuelve como antes.
        $turnoId = $request->has('turno_id')
            ? AfectaCaja::resolverTurno($user, 'cxc', $data['turno_id'] ?? null, 'libre')
            : $this->turnoSugerido($user);

        DB::transaction(function () use ($venta, $user, $data, $turnoId) {
            $abono = VentaAbono::create($data + [
                'venta_id' => $venta->id,
                'user_id'  => $user->id,
                'turno_id' => $turnoId,
            ]);

            // F7 — El cobro ingresa a tesorería con su origen.
            $clienteNombre = $venta->cliente?->razon_social
                ?? trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''));
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                $user,
                $data['fecha'],
                'ingreso',
                (float) $data['monto'],
                "Abono venta {$venta->numero} — {$clienteNombre}",
                'venta_abono',
                $abono->id,
            );

            $pagado = round((float) $venta->monto_pagado + (float) $data['monto'], 2);
            $venta->update([
                'monto_pagado'    => $pagado,
                'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
            ]);

            AuditoriaService::log('cxc.abono', $venta, [
                'numero' => $venta->numero,
                'monto'  => (float) $data['monto'],
                'saldo'  => (float) $venta->saldo_pendiente,
            ], $user);
        });

        return back()->with('success', 'Abono registrado correctamente.');
    }

    /**
     * Compensa una venta al crédito (CxC) contra una compra con saldo (CxP):
     * lo que el tercero nos debe se cancela contra lo que le debemos, SIN
     * mover dinero de caja. Sirve en ambas direcciones (se llama igual desde
     * la pantalla de CxC y la de CxP).
     */
    public function compensar(Request $request)
    {
        $user = $request->user();
        // Toca ambos módulos: exigir permiso de crear en los dos.
        abort_unless($user->tienePermiso('finanzas.cuentas-por-cobrar', 'crear')
            && $user->tienePermiso('finanzas.cuentas-por-pagar', 'crear'), 403,
            'Necesitas permiso de Cuentas por Cobrar y Cuentas por Pagar para compensar.');

        $data = $request->validate([
            'venta_id'    => ['required', 'integer', Rule::exists('ventas', 'id')->where('empresa_id', $user->empresa_id)],
            'entrada_id'  => ['required', 'integer', Rule::exists('entradas', 'id')->where('empresa_id', $user->empresa_id)],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'fecha'       => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $venta   = Venta::findOrFail($data['venta_id']);
        $entrada = \App\Models\Entrada::findOrFail($data['entrada_id']);

        abort_unless($venta->es_credito && $venta->estado === 'completada', 422, 'La venta no es una venta a crédito activa.');
        abort_if((float) $venta->saldo_pendiente <= 0, 422, 'La venta ya está saldada.');
        abort_unless(in_array($entrada->estado, [\App\Models\Entrada::ESTADO_CONFIRMADO, \App\Models\Entrada::ESTADO_EN_TRANSITO], true),
            422, 'Solo se puede compensar contra compras confirmadas o en tránsito.');
        abort_if($entrada->saldoPendiente() <= 0, 422, 'La compra ya está pagada.');

        $maximo = round(min((float) $venta->saldo_pendiente, $entrada->saldoPendiente()), 2);
        if ((float) $data['monto'] > $maximo + 0.009) {
            return back()->withErrors([
                'monto' => "El monto a compensar no puede superar S/ " . number_format($maximo, 2) . " (el menor de los dos saldos).",
            ]);
        }

        DB::transaction(function () use ($venta, $entrada, $data, $user) {
            app(\App\Services\CompensacionCxcCxpService::class)->crear(
                $venta,
                $entrada,
                (float) $data['monto'],
                $data['fecha'],
                $data['observacion'] ?? null,
                $user,
            );
        });

        return back()->with('success', 'Compensación registrada: ambos saldos se redujeron sin mover caja.');
    }

    /**
     * Edita un abono ya registrado: monto, fecha, método/cuenta, referencia.
     * Revierte el ingreso original en tesorería y lo vuelve a asentar con los
     * datos nuevos; recalcula el saldo de la venta. Espejo del editar pago
     * de Cuentas por Pagar. Todo queda en auditoría.
     */
    public function editarAbono(Request $request, VentaAbono $abono)
    {
        $user  = $request->user();
        $venta = $abono->venta;
        abort_if(!$venta || $venta->empresa_id !== $user->empresa_id, 403);
        // Un abono por compensación no se edita (desalinearía el pago hermano
        // de la compra): se anula — eso revierte ambos lados — y se recompensa.
        abort_if($abono->esCompensacion(), 422,
            'Este abono es una compensación con una compra: no se edita. Anúlalo (revierte ambos lados) y vuelve a compensar.');
        // Un abono cobrado de un anticipo tampoco: el saldo del anticipo y su
        // aplicación quedarían desalineados. Anular y volver a registrar.
        abort_if($abono->esConAnticipo(), 422,
            'Este abono consumió un anticipo del cliente: no se edita. Anúlalo (el anticipo recupera su saldo) y regístralo de nuevo.');

        // Tope: el saldo actual + lo que ya aporta este abono.
        $maxMonto = round((float) $venta->saldo_pendiente + (float) $abono->monto, 2);

        $data = $request->validate([
            'monto'          => ['required', 'numeric', 'min:0.01', "max:{$maxMonto}"],
            'fecha'          => ['required', 'date'],
            'metodo_pago_id' => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
        ]);

        $antes = [
            'monto' => (float) $abono->monto,
            'fecha' => $abono->fecha->toDateString(),
        ];

        DB::transaction(function () use ($abono, $venta, $user, $data, $antes) {
            $abono->update($data);

            $this->tesoreria->revertir('venta_abono', $abono->id);
            $clienteNombre = $venta->cliente?->razon_social
                ?? trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''));
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                $user,
                $data['fecha'],
                'ingreso',
                (float) $data['monto'],
                "Abono venta {$venta->numero} — {$clienteNombre} [editado]",
                'venta_abono',
                $abono->id,
            );

            $pagado = round((float) $venta->monto_pagado - $antes['monto'] + (float) $data['monto'], 2);
            $venta->update([
                'monto_pagado'    => max(0, $pagado),
                'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
            ]);

            AuditoriaService::log('cxc.abono_editado', $venta, [
                'abono_id' => $abono->id,
                'antes'    => $antes,
                'despues'  => ['monto' => (float) $data['monto'], 'fecha' => $data['fecha']],
                'saldo'    => (float) $venta->saldo_pendiente,
            ], $user);
        });

        return back()->with('success', 'Abono actualizado: tesorería y el saldo de la venta se recalcularon.');
    }

    /**
     * Anula un abono: revierte el ingreso en tesorería y la venta recupera
     * su saldo pendiente. Con motivo auditado.
     */
    public function eliminarAbono(Request $request, VentaAbono $abono)
    {
        $user  = $request->user();
        $venta = $abono->venta;
        abort_if(!$venta || $venta->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($abono, $venta, $user, $data) {
            if ($abono->esCompensacion()) {
                // Compensación: no hubo dinero (nada que revertir en tesorería),
                // pero la contraparte (pago de COMPRA o movimiento de DEUDA)
                // también debe revertirse para no quedar desparejada.
                app(\App\Services\CompensacionCxcCxpService::class)
                    ->revertirContraparteDesdeVenta($abono->compensacion_grupo_id, $user);
            } elseif ($abono->esConAnticipo()) {
                // Cobro con anticipo: sin tesorería que revertir; el anticipo
                // recupera su saldo y se borra la aplicación enlazada.
                $anticipo = \App\Models\ClienteAnticipo::whereKey($abono->cliente_anticipo_id)
                    ->lockForUpdate()->first();
                if ($anticipo) {
                    $anticipo->aplicaciones()->where('venta_abono_id', $abono->id)->delete();
                    $anticipo->update([
                        'saldo'  => round((float) $anticipo->saldo + (float) $abono->monto, 2),
                        'estado' => 'activo',
                    ]);
                    AuditoriaService::log('anticipo_cliente.cobro_revertido', $anticipo, [
                        'abono_id' => $abono->id,
                        'monto'    => (float) $abono->monto,
                        'saldo'    => (float) $anticipo->saldo,
                    ], $user);
                }
            } else {
                $this->tesoreria->revertir('venta_abono', $abono->id);
            }

            $pagado = round((float) $venta->monto_pagado - (float) $abono->monto, 2);
            $venta->update([
                'monto_pagado'    => max(0, $pagado),
                'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
            ]);

            AuditoriaService::log('cxc.abono_anulado', $venta, [
                'abono_id' => $abono->id,
                'monto'    => (float) $abono->monto,
                'fecha'    => $abono->fecha->toDateString(),
                'motivo'   => $data['motivo'],
                'saldo'    => (float) $venta->saldo_pendiente,
            ], $user);

            $abono->delete();
        });

        return back()->with('success', 'Abono anulado: la venta recuperó su saldo pendiente.');
    }
}
