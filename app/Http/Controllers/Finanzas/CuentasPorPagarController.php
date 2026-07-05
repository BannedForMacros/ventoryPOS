<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\MetodoPago;
use App\Models\ProveedorAdelanto;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
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
    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Entrada::deEmpresa($user->empresa_id)
            ->confirmado()
            ->with(['proveedorRel', 'almacen', 'pagosParciales.metodoPago', 'pagosParciales.cuenta', 'pagosParciales.adelanto', 'pagosParciales.user'])
            ->when($request->input('proveedor_id'), fn ($q, $v) => $q->where('proveedor_id', $v))
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->where('fecha', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->where('fecha', '<=', $v));

        if ($request->input('estado', 'pendientes') === 'pendientes') {
            $query->where('estado_pago', '!=', 'pagado')->whereRaw('total - monto_pagado > 0.01');
        }

        $entradas = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        $totalPendiente = (float) Entrada::deEmpresa($user->empresa_id)
            ->confirmado()
            ->where('estado_pago', '!=', 'pagado')
            ->selectRaw('COALESCE(SUM(GREATEST(total - monto_pagado, 0)), 0) as v')
            ->value('v');

        return Inertia::render('Finanzas/CuentasPorPagar', [
            'entradas'       => $entradas,
            'totalPendiente' => round($totalPendiente, 2),
            'estado'         => $request->input('estado', 'pendientes'),
            'metodosPago'    => MetodoPago::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
            'cuentas'        => Cuenta::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
            // Adelantos con saldo para ofrecer "pagar consumiendo adelanto".
            'adelantos'      => ProveedorAdelanto::deEmpresa($user->empresa_id)->activo()
                ->where('saldo', '>', 0)->get(['id', 'proveedor_id', 'saldo']),
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
            'cuenta_id'             => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'proveedor_adelanto_id' => ['nullable', 'integer', Rule::exists('proveedor_adelantos', 'id')->where('empresa_id', $user->empresa_id)],
            'referencia'            => ['nullable', 'string', 'max:200'],
            'observacion'           => ['nullable', 'string', 'max:500'],
        ]);

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
}
