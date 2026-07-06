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
use Illuminate\Validation\ValidationException;
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
