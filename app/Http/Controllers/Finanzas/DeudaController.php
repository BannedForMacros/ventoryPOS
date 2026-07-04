<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Deuda;
use App\Models\MetodoPago;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Deudas y préstamos: bancarios ("DEUDA BCP 1 - 7630"), de personas
 * ("JEINER HERRERA"), al personal ("DEBEMOS AL PERSONAL") y préstamos
 * otorgados a terceros (por cobrar).
 */
class DeudaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Deuda::deEmpresa($user->empresa_id)
            ->with(['pagos.metodoPago', 'pagos.cuenta', 'pagos.user'])
            ->when($request->input('direccion'), fn ($q, $v) => $q->where('direccion', $v))
            ->when($request->input('tipo'), fn ($q, $v) => $q->where('tipo', $v));

        if ($request->input('estado', 'activas') === 'activas') {
            $query->activa();
        }

        $deudas = $query->orderBy('direccion')->orderBy('tipo')->orderBy('nombre')->paginate(25)->withQueryString();

        $totales = [
            'por_pagar'  => round((float) Deuda::deEmpresa($user->empresa_id)->porPagar()->activa()->sum('saldo'), 2),
            'por_cobrar' => round((float) Deuda::deEmpresa($user->empresa_id)->porCobrar()->activa()->sum('saldo'), 2),
        ];

        return Inertia::render('Finanzas/Deudas', [
            'deudas'      => $deudas,
            'totales'     => $totales,
            'estado'      => $request->input('estado', 'activas'),
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'direccion'         => ['required', Rule::in(['por_pagar', 'por_cobrar'])],
            'tipo'              => ['required', Rule::in(['bancaria', 'personal', 'trabajador', 'otro'])],
            'nombre'            => ['required', 'string', 'max:200'],
            'monto_original'    => ['required', 'numeric', 'min:0.01'],
            'fecha_inicio'      => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'observacion'       => ['nullable', 'string', 'max:500'],
        ]);

        $deuda = Deuda::create($data + [
            'empresa_id' => $user->empresa_id,
            'user_id'    => $user->id,
            'saldo'      => $data['monto_original'],
            'estado'     => 'activa',
        ]);

        AuditoriaService::log('deuda.creada', $deuda, [
            'nombre'    => $deuda->nombre,
            'direccion' => $deuda->direccion,
            'monto'     => (float) $deuda->monto_original,
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

        $rules = [
            'tipo'           => ['required', Rule::in(['amortizacion', 'incremento'])],
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'observacion'    => ['nullable', 'string', 'max:500'],
        ];

        if ($request->input('tipo') === 'amortizacion') {
            $rules['monto'][] = 'max:' . (float) $deuda->saldo;
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($deuda, $user, $data) {
            $deuda->pagos()->create($data + ['user_id' => $user->id]);

            $nuevoSaldo = $data['tipo'] === 'amortizacion'
                ? round((float) $deuda->saldo - (float) $data['monto'], 2)
                : round((float) $deuda->saldo + (float) $data['monto'], 2);

            $deuda->update([
                'saldo'  => max(0, $nuevoSaldo),
                'estado' => $nuevoSaldo <= 0.01 ? 'pagada' : 'activa',
            ]);

            AuditoriaService::log('deuda.' . $data['tipo'], $deuda, [
                'monto' => (float) $data['monto'],
                'saldo' => (float) $deuda->saldo,
            ], $user);
        });

        return back()->with('success', 'Movimiento registrado correctamente.');
    }

    public function anular(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);
        abort_unless($deuda->estado === 'activa', 422, 'La deuda no está activa.');

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $deuda->update(['estado' => 'anulada']);

        AuditoriaService::log('deuda.anulada', $deuda, [
            'motivo' => $data['motivo'],
            'saldo'  => (float) $deuda->saldo,
        ], $user);

        return back()->with('success', 'Deuda anulada.');
    }
}
