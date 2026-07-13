<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
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

        // Por defecto solo pendientes; ?estado=todas muestra también saldadas.
        if ($request->input('estado', 'pendientes') === 'pendientes') {
            $query->where('saldo_pendiente', '>', 0);
        }

        $ventas = $query->orderByDesc('fecha_venta')->paginate(25)->withQueryString();

        $totalPendiente = (float) Venta::deEmpresa($user->empresa_id)
            ->conSaldoPendiente()
            ->sum('saldo_pendiente');

        return Inertia::render('Finanzas/CuentasPorCobrar', [
            'ventas'         => $ventas,
            'totalPendiente' => round($totalPendiente, 2),
            // Acciones visibles según la matriz de permisos del rol.
            'puede'          => [
                'editar'   => $user->tienePermiso('finanzas.cuentas-por-cobrar', 'editar'),
                'eliminar' => $user->tienePermiso('finanzas.cuentas-por-cobrar', 'eliminar'),
            ],
            'estado'         => $request->input('estado', 'pendientes'),
            'busqueda'       => (string) $request->input('busqueda', ''),
            'metodosPago'    => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'        => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
        ]);
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
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($venta, $user, $data) {
            $abono = VentaAbono::create($data + ['venta_id' => $venta->id, 'user_id' => $user->id]);

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
}
