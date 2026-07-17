<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Deuda;
use App\Models\MetodoPago;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
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
    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Deuda::deEmpresa($user->empresa_id)
            ->with(['pagos.metodoPago', 'pagos.cuenta', 'pagos.user'])
            ->when($request->input('direccion'), fn ($q, $v) => $q->where('direccion', $v))
            ->when($request->input('tipo'), fn ($q, $v) => $q->where('tipo', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('nombre', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%"));
            });

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
            'buscar'      => $request->input('buscar', ''),
            // Acciones visibles según la matriz de permisos del rol (nada
            // hardcodeado a es_admin: se otorgan desde Configuración → Roles).
            'puede'       => [
                'editar'   => $user->tienePermiso('finanzas.deudas', 'editar'),
                'eliminar' => $user->tienePermiso('finanzas.deudas', 'eliminar'),
            ],
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
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
            'monto_original'    => ['required', 'numeric', 'min:0.01', 'gte:' . max(0.01, $amortizadoNeto)],
            'fecha_inicio'      => ['required', 'date'],
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

        $deuda->update($data + [
            'saldo'  => $nuevoSaldo,
            'estado' => $nuevoSaldo <= 0.01 ? 'pagada' : 'activa',
        ]);

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

        $deuda->update([
            'estado' => (float) $deuda->saldo <= 0.01 ? 'pagada' : 'activa',
        ]);

        AuditoriaService::log('deuda.reactivada', $deuda, [
            'motivo' => $data['motivo'],
            'saldo'  => (float) $deuda->saldo,
        ], $user);

        return back()->with('success', 'Deuda reactivada: vuelve a aparecer en el balance.');
    }

    /**
     * Elimina una deuda POR COMPLETO (registro erróneo/duplicado): revierte
     * los asientos de tesorería de todos sus movimientos y borra el registro.
     * Queda snapshot completo en auditoría.
     */
    public function destroy(Request $request, Deuda $deuda)
    {
        $user = $request->user();
        abort_if($deuda->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($deuda, $user, $data) {
            $deuda->loadMissing('pagos');

            AuditoriaService::log('deuda.eliminada', $deuda, [
                'motivo'   => $data['motivo'],
                'snapshot' => [
                    'nombre'         => $deuda->nombre,
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
            ], $user);

            // Revertir el dinero de cada movimiento en tesorería.
            foreach ($deuda->pagos as $pago) {
                $this->tesoreria->revertir('deuda_pago', $pago->id);
            }

            $deuda->pagos()->delete();
            $deuda->delete();
        });

        return back()->with('success', 'Deuda eliminada: los movimientos de tesorería asociados se revirtieron.');
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
            // Deshacer el efecto del movimiento en el saldo.
            $nuevoSaldo = $pago->tipo === 'amortizacion'
                ? round((float) $deuda->saldo + (float) $pago->monto, 2)
                : max(0, round((float) $deuda->saldo - (float) $pago->monto, 2));

            $this->tesoreria->revertir('deuda_pago', $pago->id);

            $deuda->update([
                'saldo'  => $nuevoSaldo,
                'estado' => $deuda->estado === 'anulada' ? 'anulada' : ($nuevoSaldo <= 0.01 ? 'pagada' : 'activa'),
            ]);

            AuditoriaService::log('deuda.movimiento_eliminado', $deuda, [
                'motivo'     => $data['motivo'],
                'movimiento' => [
                    'fecha' => $pago->fecha->toDateString(),
                    'tipo'  => $pago->tipo,
                    'monto' => (float) $pago->monto,
                ],
                'saldo' => $nuevoSaldo,
            ], $user);

            $pago->delete();
        });

        return back()->with('success', 'Movimiento eliminado: tesorería y el saldo de la deuda se recalcularon.');
    }
}
