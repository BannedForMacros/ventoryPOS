<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\MetodoPago;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use App\Support\AfectaCaja;
use App\Support\ExigeCuentaDePago;
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
    use ExigeCuentaDePago;

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
                'editar'   => $user->tienePermiso('finanzas.deudas', 'editar'),
                'eliminar' => $user->tienePermiso('finanzas.deudas', 'eliminar'),
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

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8
        $headers = ['Dirección', 'Nombre', 'Tipo', 'Método de pago', 'Original', 'Saldo', 'Estado'];
        $csv .= implode(',', array_map($this->escaparCsv(...), $headers)) . "\n";

        foreach ($deudas as $d) {
            $metodos = $d->pagos->map(fn ($p) => $p->metodoPago?->nombre)->filter()->unique()->implode(' · ') ?: '—';

            $row = [
                $d->direccion === 'por_pagar' ? 'Debemos' : 'Nos deben',
                $d->nombre,
                $tipoLabel[$d->tipo] ?? $d->tipo,
                $metodos,
                'S/ ' . number_format((float) $d->monto_original, 2, '.', ''),
                'S/ ' . number_format((float) $d->saldo, 2, '.', ''),
                $d->estado === 'activa' ? 'Activa' : ($d->estado === 'pagada' ? 'Pagada' : 'Anulada'),
            ];

            $csv .= implode(',', array_map($this->escaparCsv(...), $row)) . "\n";
        }

        $filename = 'deudas_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function escaparCsv(string $value): string
    {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
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
            // Desembolso opcional: mover el dinero en tesorería al crear la deuda.
            // Por defecto SÍ se mueve; se puede desactivar para deudas históricas
            // (ya gastadas / sin rastro de caja).
            'registrar_caja'    => ['boolean'],
            'metodo_pago_id'    => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
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

        $rules = [
            'tipo'           => ['required', Rule::in(['amortizacion', 'incremento'])],
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'observacion'    => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno cuya caja mueve el efectivo de esta cuota.
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ];

        if ($request->input('tipo') === 'amortizacion') {
            $rules['monto'][] = 'max:' . (float) $deuda->saldo;
        }

        $data = $request->validate($rules);
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

            // Revertir también el desembolso inicial (ingreso/egreso al crear).
            $this->tesoreria->revertir('deuda', $deuda->id);

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
