<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\MetodoPago;
use App\Models\ProveedorAdelanto;
use App\Models\Turno;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use App\Support\AfectaCaja;
use App\Support\ExigeCuentaDePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Cuentas por pagar a proveedores con abonos parciales.
 * Es el "PROVEEDORES POR PAGAR" del balance diario del cliente.
 */
class CuentasPorPagarController extends Controller
{
    use ExigeCuentaDePago;

    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Entrada::deEmpresa($user->empresa_id)
            ->confirmado()
            ->with([
                'proveedorRel', 'almacen',
                'pagosParciales.metodoPago', 'pagosParciales.cuenta', 'pagosParciales.adelanto', 'pagosParciales.user',
                // Caja que afectó el pago (solo para MOSTRAR en la ventana; se edita con el lápiz).
                'pagosParciales.turno:id,caja_id', 'pagosParciales.turno.caja:id,nombre',
                // Mercadería que originó la deuda: se muestra en la MISMA ventana
                // de pagos (antes había que salir a Entradas para saber qué se compró).
                'detalles.producto:id,nombre,codigo', 'detalles.unidadMedida:id,nombre,abreviatura',
            ])
            ->when($request->input('proveedor_id'), fn ($q, $v) => $q->where('proveedor_id', $v))
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->where('fecha', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->where('fecha', '<=', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('numero_documento', 'ilike', "%{$t}%")
                    ->orWhere('proveedor', 'ilike', "%{$t}%")
                    ->orWhereHas('proveedorRel', fn ($p) => $p
                        ->where('razon_social', 'ilike', "%{$t}%")
                        ->orWhere('nombre_comercial', 'ilike', "%{$t}%")));
            });

        // Filtro de estado de pago: 'pendientes' (default), 'parciales',
        // 'pagadas' o 'todas'.
        $estado = $request->input('estado', 'pendientes');
        if ($estado === 'pendientes') {
            $query->where('estado_pago', '!=', 'pagado')->whereRaw('total - monto_pagado > 0.01');
        } elseif ($estado === 'parciales') {
            $query->where('estado_pago', 'parcial');
        } elseif ($estado === 'pagadas') {
            $query->where('estado_pago', 'pagado');
        }

        $entradas = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        $totalPendiente = (float) Entrada::deEmpresa($user->empresa_id)
            ->confirmado()
            ->where('estado_pago', '!=', 'pagado')
            ->selectRaw('COALESCE(SUM(GREATEST(total - monto_pagado, 0)), 0) as v')
            ->value('v');

        // KPIs de cabecera (universo pendiente, independiente del filtro visible)
        $basePendiente = Entrada::deEmpresa($user->empresa_id)
            ->confirmado()
            ->where('estado_pago', '!=', 'pagado')
            ->whereRaw('total - monto_pagado > 0.01');
        $kpis = [
            'compras_con_saldo'     => (int) (clone $basePendiente)->count(),
            // Proveedor formal (proveedor_id) o texto libre: cuenta ambos sin duplicar
            'proveedores_con_deuda' => (int) (clone $basePendiente)
                ->selectRaw("COUNT(DISTINCT COALESCE(proveedor_id::text, proveedor)) as c")
                ->value('c'),
            'abonado'               => round((float) (clone $basePendiente)->sum('monto_pagado'), 2),
        ];

        return Inertia::render('Finanzas/CuentasPorPagar', [
            'entradas'       => $entradas,
            'totalPendiente' => round($totalPendiente, 2),
            'kpis'           => $kpis,
            'esAdmin'        => (bool) $user->rol->es_admin,
            'estado'         => $request->input('estado', 'pendientes'),
            'buscar'         => $request->input('buscar', ''),
            'metodosPago'    => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'        => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
            // Adelantos con saldo para ofrecer "pagar consumiendo adelanto".
            'adelantos'      => ProveedorAdelanto::deEmpresa($user->empresa_id)->activo()
                ->where('saldo', '>', 0)->get(['id', 'proveedor_id', 'saldo']),
            // "Afecta caja a:" — SOLO turnos ABIERTOS (un turno cerrado ya tiene su
            // efectivo esperado congelado, no reflejaría el cambio). null = no
            // afecta ninguna caja.
            'turnos'         => Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where('estado', 'abierto')
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
        ]);
    }

    /**
     * Registra un abono al proveedor. Puede pagarse con dinero (método +
     * cuenta) o consumiendo un adelanto previo (proveedor_adelanto_id):
     * en ese caso baja el saldo del adelanto en la misma transacción.
     */
    public function abonar(Request $request, Entrada $entrada)
    {
        $user = $request->user();
        abort_if($entrada->empresa_id !== $user->empresa_id, 403);
        abort_unless($entrada->estado === 'confirmado', 422, 'Solo se pueden abonar entradas confirmadas.');

        $saldo = $entrada->saldoPendiente();
        abort_if($saldo <= 0, 422, 'La entrada ya está pagada.');

        $data = $request->validate([
            'monto'                 => ['required', 'numeric', 'min:0.01', "max:{$saldo}"],
            'fecha'                 => ['required', 'date'],
            'metodo_pago_id'        => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'             => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'proveedor_adelanto_id' => ['nullable', 'integer', Rule::exists('proveedor_adelantos', 'id')->where('empresa_id', $user->empresa_id)],
            'referencia'            => ['nullable', 'string', 'max:200'],
            'observacion'           => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno de cuya caja sale el efectivo. null = no afecta
            // ninguna caja. Va directo al EntradaPago (turno_id es fillable).
            'turno_id'              => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        // Gate por config de empresa (módulo 'cxp', modo libre: respeta lo elegido).
        $data['turno_id'] = AfectaCaja::resolverTurno($user, 'cxp', $data['turno_id'] ?? null, 'libre');

        DB::transaction(function () use ($entrada, $user, $data) {
            // Si el pago consume un adelanto, validar saldo y descontarlo.
            if (!empty($data['proveedor_adelanto_id'])) {
                $adelanto = ProveedorAdelanto::where('id', $data['proveedor_adelanto_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($adelanto->estado === 'activo', 422, 'El adelanto no está activo.');
                abort_if((float) $adelanto->saldo < (float) $data['monto'] - 0.01, 422, 'El adelanto no tiene saldo suficiente.');

                $nuevoSaldo = round((float) $adelanto->saldo - (float) $data['monto'], 2);
                $adelanto->update([
                    'saldo'  => max(0, $nuevoSaldo),
                    'estado' => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
                ]);

                $adelanto->aplicaciones()->create([
                    'entrada_id' => $entrada->id,
                    'user_id'    => $user->id,
                    'fecha'      => $data['fecha'],
                    'monto'      => $data['monto'],
                ]);
            }

            $pago = EntradaPago::create($data + ['entrada_id' => $entrada->id, 'user_id' => $user->id]);

            // F7 — Egreso de tesorería SOLO si sale dinero nuevo. Cuando el
            // pago consume un adelanto no hay salida de caja (el dinero ya
            // salió cuando se entregó el adelanto).
            if (empty($data['proveedor_adelanto_id'])) {
                $prov = $entrada->proveedorRel?->razon_social ?? $entrada->proveedor ?? 'proveedor';
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                    $user,
                    $data['fecha'],
                    'egreso',
                    (float) $data['monto'],
                    "Pago a proveedor {$prov}" . ($entrada->numero_documento ? " ({$entrada->numero_documento})" : ''),
                    'entrada_pago',
                    $pago->id,
                );
            }

            $entrada->aplicarPago((float) $data['monto']);

            AuditoriaService::log('cxp.abono', $entrada, [
                'monto'        => (float) $data['monto'],
                'saldo'        => $entrada->saldoPendiente(),
                'via_adelanto' => !empty($data['proveedor_adelanto_id']),
            ], $user);
        });

        return back()->with('success', 'Pago al proveedor registrado correctamente.');
    }

    /**
     * Edita un pago ya registrado (SOLO admin): monto, fecha, método/cuenta,
     * referencia y observación. Revierte el asiento de tesorería original y
     * lo vuelve a registrar con los datos nuevos; recalcula monto_pagado y
     * estado_pago de la compra.
     *
     * Pagos hechos CONSUMIENDO UN ADELANTO: el monto no se edita aquí (el
     * saldo del adelanto y su historial quedarían desalineados) — solo
     * fecha/referencia/observación. Para corregir el monto: anular y volver
     * a registrar.
     */
    public function editarPago(Request $request, EntradaPago $pago)
    {
        $user    = $request->user();
        $entrada = $pago->entrada;

        abort_if(!$entrada || $entrada->empresa_id !== $user->empresa_id, 403);
        abort_unless($user->rol->es_admin, 403, 'Solo un administrador puede editar pagos registrados.');

        $esAdelanto = !empty($pago->proveedor_adelanto_id);

        // Tope del monto nuevo: el saldo actual + lo que ya aporta este pago.
        $maxMonto = round($entrada->saldoPendiente() + (float) $pago->monto, 2);

        $data = $request->validate([
            'monto'          => [$esAdelanto ? 'prohibited' : 'required', 'numeric', 'min:0.01', "max:{$maxMonto}"],
            'fecha'          => ['required', 'date'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno de cuya caja salió el efectivo. null = no
            // afecta ninguna caja. Solo se aplica si el request trae la clave.
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ], [
            'monto.prohibited' => 'Este pago consumió un adelanto: su monto no se edita. Anúlalo y regístralo de nuevo.',
        ]);

        $antes = [
            'monto'          => (float) $pago->monto,
            'fecha'          => $pago->fecha->toDateString(),
            'metodo_pago_id' => $pago->metodo_pago_id,
            'cuenta_id'      => $pago->cuenta_id,
        ];

        DB::transaction(function () use ($pago, $entrada, $user, $data, $esAdelanto, $antes, $request) {
            $montoNuevo = $esAdelanto ? (float) $pago->monto : (float) $data['monto'];

            $pago->update([
                'monto'          => $montoNuevo,
                'fecha'          => $data['fecha'],
                'metodo_pago_id' => $esAdelanto ? $pago->metodo_pago_id : ($data['metodo_pago_id'] ?? null),
                'cuenta_id'      => $esAdelanto ? $pago->cuenta_id : ($data['cuenta_id'] ?? null),
                'referencia'     => $data['referencia'] ?? null,
                'observacion'    => $data['observacion'] ?? null,
                // Solo tocar turno_id si el request lo envió (así otros llamadores
                // que no lo mandan no lo borran sin querer). Gateado por config.
                'turno_id'       => $request->has('turno_id')
                    ? AfectaCaja::resolverTurno($user, 'cxp', $data['turno_id'] ?? null, 'libre')
                    : $pago->turno_id,
            ]);

            // F7 — Rehacer el egreso de tesorería (solo pagos con dinero).
            if (!$esAdelanto) {
                $this->tesoreria->revertir('entrada_pago', $pago->id);
                $prov = $entrada->proveedorRel?->razon_social ?? $entrada->proveedor ?? 'proveedor';
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                    $user,
                    $data['fecha'],
                    'egreso',
                    $montoNuevo,
                    "Pago a proveedor {$prov}" . ($entrada->numero_documento ? " ({$entrada->numero_documento})" : '') . ' [editado]',
                    'entrada_pago',
                    $pago->id,
                );
            }

            // Recalcular lo pagado de la compra con el delta del monto.
            $pagado = round((float) $entrada->monto_pagado - $antes['monto'] + $montoNuevo, 2);
            $entrada->update([
                'monto_pagado' => max(0, $pagado),
                'estado_pago'  => $pagado >= (float) $entrada->total - 0.01 ? 'pagado' : ($pagado > 0 ? 'parcial' : 'pendiente'),
            ]);

            AuditoriaService::log('cxp.pago_editado', $entrada, [
                'pago_id' => $pago->id,
                'antes'   => $antes,
                'despues' => [
                    'monto'          => $montoNuevo,
                    'fecha'          => $data['fecha'],
                    'metodo_pago_id' => $pago->metodo_pago_id,
                    'cuenta_id'      => $pago->cuenta_id,
                ],
            ], $user);
        });

        return back()->with('success', 'Pago actualizado: tesorería y el saldo de la compra se recalcularon.');
    }

    /**
     * Anula un pago registrado (SOLO admin): revierte tesorería (o restaura
     * el saldo del adelanto consumido), recalcula la compra y elimina el pago.
     */
    public function eliminarPago(Request $request, EntradaPago $pago)
    {
        $user    = $request->user();
        $entrada = $pago->entrada;

        abort_if(!$entrada || $entrada->empresa_id !== $user->empresa_id, 403);
        abort_unless($user->rol->es_admin, 403, 'Solo un administrador puede anular pagos registrados.');

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($pago, $entrada, $user, $data) {
            // Pago vía adelanto: devolver el saldo al adelanto y borrar su aplicación.
            if ($pago->proveedor_adelanto_id) {
                $adelanto = ProveedorAdelanto::where('id', $pago->proveedor_adelanto_id)->lockForUpdate()->first();
                if ($adelanto) {
                    $adelanto->update([
                        'saldo'  => round((float) $adelanto->saldo + (float) $pago->monto, 2),
                        'estado' => 'activo',
                    ]);
                    $adelanto->aplicaciones()
                        ->where('entrada_id', $entrada->id)
                        ->where('monto', $pago->monto)
                        ->orderByDesc('id')
                        ->first()?->delete();
                }
            } else {
                // Pago con dinero: revertir el egreso de tesorería.
                $this->tesoreria->revertir('entrada_pago', $pago->id);
            }

            $pagado = round((float) $entrada->monto_pagado - (float) $pago->monto, 2);
            $entrada->update([
                'monto_pagado' => max(0, $pagado),
                'estado_pago'  => $pagado >= (float) $entrada->total - 0.01 ? 'pagado' : ($pagado > 0 ? 'parcial' : 'pendiente'),
            ]);

            AuditoriaService::log('cxp.pago_anulado', $entrada, [
                'pago_id'      => $pago->id,
                'monto'        => (float) $pago->monto,
                'fecha'        => $pago->fecha->toDateString(),
                'via_adelanto' => (bool) $pago->proveedor_adelanto_id,
                'motivo'       => $data['motivo'],
            ], $user);

            $pago->delete();
        });

        return back()->with('success', 'Pago anulado: el saldo de la compra y tesorería se recalcularon.');
    }
}
