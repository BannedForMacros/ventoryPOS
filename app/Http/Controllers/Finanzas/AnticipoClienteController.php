<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Anticipos de clientes: dinero recibido por adelantado a cambio de
 * mercadería futura ("CLIENTES ANTICIPOS" del balance, pasivo).
 *
 * Modalidad 'material': el anticipo compromete N unidades de un producto;
 * el pasivo se valoriza a precio de venta ACTUAL (precio del día).
 */
class AnticipoClienteController extends Controller
{
    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ClienteAnticipo::deEmpresa($user->empresa_id)
            ->with(['cliente', 'producto', 'metodoPago', 'cuenta', 'aplicaciones.venta', 'aplicaciones.user'])
            ->when($request->input('cliente_id'), fn ($q, $v) => $q->where('cliente_id', $v));

        if ($request->input('estado', 'activos') === 'activos') {
            $query->activo();
        }

        $anticipos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        // Total del pasivo a precio del día (lo que mostrará el balance).
        $totalPasivo = ClienteAnticipo::deEmpresa($user->empresa_id)->activo()->with('producto')->get()
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
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
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
     * Aplica el anticipo: el cliente retiró mercadería (o se le facturó una
     * venta) y el pasivo baja. Puede vincularse a una venta existente.
     */
    public function aplicar(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();
        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_unless($anticipo->estado === 'activo', 422, 'El anticipo no está activo.');

        $data = $request->validate([
            'fecha'       => ['required', 'date'],
            'monto'       => ['required', 'numeric', 'min:0.01', 'max:' . (float) $anticipo->saldo],
            'cantidad'    => ['nullable', 'numeric', 'min:0.0001'],
            'venta_id'    => ['nullable', 'integer', Rule::exists('ventas', 'id')->where('empresa_id', $user->empresa_id)],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        if ($anticipo->tipo_valorizacion === 'material') {
            $request->validate([
                'cantidad' => ['required', 'numeric', 'min:0.0001', 'max:' . (float) $anticipo->cantidad_pendiente],
            ]);
        }

        DB::transaction(function () use ($anticipo, $user, $data) {
            $anticipo->aplicaciones()->create($data + ['user_id' => $user->id]);

            $nuevoSaldo = round((float) $anticipo->saldo - (float) $data['monto'], 2);
            $nuevaCantidad = $anticipo->cantidad_pendiente !== null && !empty($data['cantidad'])
                ? round((float) $anticipo->cantidad_pendiente - (float) $data['cantidad'], 4)
                : $anticipo->cantidad_pendiente;

            $agotado = $anticipo->tipo_valorizacion === 'material'
                ? ($nuevaCantidad !== null && $nuevaCantidad <= 0.0001)
                : ($nuevoSaldo <= 0.01);

            $anticipo->update([
                'saldo'              => max(0, $nuevoSaldo),
                'cantidad_pendiente' => $nuevaCantidad !== null ? max(0, $nuevaCantidad) : null,
                'estado'             => $agotado ? 'aplicado' : 'activo',
            ]);

            AuditoriaService::log('anticipo_cliente.aplicado', $anticipo, [
                'monto'    => (float) $data['monto'],
                'cantidad' => $data['cantidad'] ?? null,
                'saldo'    => (float) $anticipo->saldo,
            ], $user);
        });

        return back()->with('success', 'Aplicación del anticipo registrada.');
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
}
