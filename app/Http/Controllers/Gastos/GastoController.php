<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gastos\StoreGastoRequest;
use App\Http\Requests\Gastos\UpdateGastoRequest;
use App\Models\Gasto;
use App\Models\GastoTipo;
use App\Models\Local;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\LocalScopeService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GastoController extends Controller
{
    public function __construct(
        private LocalScopeService $scope,
        private TesoreriaService $tesoreria,
    ) {}

    public function index(Request $request)
    {
        $user    = $request->user();
        $scope   = $request->input('scope', 'turno');       // 'turno' | 'administrativo'
        $mostrar = $request->input('mostrar', 'activos');   // 'activos' | 'eliminados' | 'todos'

        $query = Gasto::deEmpresa($user->empresa_id)
            // 'eliminados' muestra SOLO los borrados (soft delete); 'todos'
            // incluye activos + eliminados; por defecto el scope global de
            // SoftDeletes ya oculta los eliminados.
            ->when($mostrar === 'eliminados', fn ($q) => $q->onlyTrashed())
            ->when($mostrar === 'todos', fn ($q) => $q->withTrashed())
            ->with(['tipo', 'concepto', 'user', 'local', 'turno'])
            ->when($request->input('tipo_id'), fn($q, $v) => $q->where('gasto_tipo_id', $v))
            ->when($request->input('concepto_id'), fn($q, $v) => $q->where('gasto_concepto_id', $v))
            ->when($request->input('fecha_desde'), fn($q, $v) => $q->where('fecha', '>=', $v))
            ->when($request->input('fecha_hasta'), fn($q, $v) => $q->where('fecha', '<=', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('comentario', 'ilike', "%{$t}%")
                    ->orWhereHas('tipo', fn ($x) => $x->where('nombre', 'ilike', "%{$t}%"))
                    ->orWhereHas('concepto', fn ($x) => $x->where('nombre', 'ilike', "%{$t}%")));
            });

        if ($scope === 'turno') {
            if (!$user->rol->es_admin) {
                $turnoIds = Turno::where('user_id', $user->id)->pluck('id');
                $query->whereIn('turno_id', $turnoIds);
            }
            $query->whereNotNull('turno_id');
        } else {
            $query->whereNull('turno_id');
            if ($user->local_id) {
                $query->where('local_id', $user->local_id);
            } elseif ($request->input('local_id')) {
                $query->where('local_id', $request->input('local_id'));
            }
        }

        $gastos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        $tipos = GastoTipo::deEmpresa($user->empresa_id)->activo()
            ->with(['conceptos' => fn($q) => $q->activo()->orderBy('nombre')])
            ->orderBy('nombre')->get();

        $locales = $this->scope->localesVisibles($user);

        $esAdmin = $user->rol->es_admin;

        // Para admin: cargar turnos abiertos de la empresa para asignar gastos
        $turnosAbiertos = [];
        if ($esAdmin) {
            $turnosAbiertos = Turno::deEmpresa($user->empresa_id)
                ->abierto()
                ->with(['caja', 'user'])
                ->get();
        }

        return Inertia::render('Gastos/Index', [
            'gastos'          => $gastos,
            'tipos'           => $tipos,
            'scope'           => $scope,
            'mostrar'         => $mostrar,
            'buscar'          => $request->input('buscar', ''),
            'locales'         => $locales,
            'turnosAbiertos'  => $turnosAbiertos,
            'esAdmin'         => $esAdmin,
            // Métodos de pago con sus cuentas (mismo patrón que el POS): el modal
            // muestra el método y, si tiene cuentas, un segundo select de cuenta.
            // La relación cuentas() usa withPivot('id') → cada cuenta trae pivot.id
            // (el cuenta_metodo_pago_id que el backend resuelve a una cuenta real).
            'metodosPago'     => \App\Models\MetodoPago::deEmpresa($user->empresa_id)->activo()
                ->with(['tipo:id,slug,nombre', 'cuentas' => fn($q) => $q->where('activo', true)])
                ->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreGastoRequest $request)
    {
        $user = $request->user();

        // Solo admins pueden crear gastos administrativos (sin turno) — pero solo
        // cuando el módulo 'gastos' afecta caja. Si la empresa lo apagó, cualquiera
        // puede registrar gastos sin turno (no descuentan de ninguna caja).
        if (!$request->input('turno_id') && !$user->rol->es_admin
            && \App\Support\AfectaCaja::activo($user->empresa, 'gastos')) {
            abort(403, 'Solo administradores pueden registrar gastos administrativos.');
        }

        // Turno gateado por config (módulo 'gastos', modo forzado: el cajero se
        // imputa a su turno; el admin usa el elegido; apagado → null = no afecta caja).
        $localId = $user->local_id ?? $request->input('local_id');
        $turnoId = \App\Support\AfectaCaja::resolverTurno(
            $user, 'gastos',
            $request->input('turno_id') ? (int) $request->input('turno_id') : null,
            'forzado',
        );

        if ($turnoId) {
            $turno = Turno::find($turnoId);
            if ($turno) {
                $localId = $turno->local_id;
            }
        }

        $this->validarFechaEnTurno($turnoId, $request->input('fecha'));

        // Resolver la cuenta destino igual que el POS: se elige un método de pago
        // y (si tiene) su cuenta. TesoreriaService::resolverCuenta traduce el
        // método + cuenta_metodo_pago_id a una cuenta_id concreta (efectivo si el
        // método es efectivo o no tiene cuenta). Compat: si aún llega cuenta_id
        // directo (clientes viejos), se respeta.
        $metodoPagoId       = $request->input('metodo_pago_id');
        $cuentaMetodoPagoId = $request->input('cuenta_metodo_pago_id');
        $cuentaId = $metodoPagoId
            ? $this->tesoreria->resolverCuenta(
                $user->empresa_id,
                $cuentaMetodoPagoId ? (int) $cuentaMetodoPagoId : null,
                (int) $metodoPagoId,
              )
            : ($request->input('cuenta_id') ?: null);

        DB::transaction(function () use ($request, $user, $localId, $turnoId, $cuentaId) {
            $gasto = Gasto::create([
                'empresa_id'        => $user->empresa_id,
                'local_id'          => $localId,
                'user_id'           => $user->id,
                'turno_id'          => $turnoId,
                'gasto_tipo_id'     => $request->input('gasto_tipo_id'),
                'gasto_concepto_id' => $request->input('gasto_concepto_id'),
                'monto'             => $request->input('monto'),
                'cuenta_id'         => $cuentaId,
                'fecha'             => $request->input('fecha'),
                'comentario'        => $request->input('comentario'),
            ]);

            // F7 — Tesorería: el gasto sale de la cuenta resuelta (efectivo por defecto).
            $gasto->load('concepto');
            $this->tesoreria->registrar(
                $user->empresa_id,
                $gasto->cuenta_id, // null → efectivo
                $user,
                $request->input('fecha'),
                'egreso',
                (float) $request->input('monto'),
                'Gasto — ' . ($gasto->concepto?->nombre ?? 'operativo'),
                'gasto',
                $gasto->id,
            );
        });

        return redirect()->back()->with('success', 'Gasto registrado correctamente.');
    }

    public function update(UpdateGastoRequest $request, Gasto $gasto)
    {
        $user = $request->user();
        abort_if($gasto->empresa_id !== $user->empresa_id, 403);
        $this->autorizarModificacion($gasto, $user);
        $this->validarFechaEnTurno($gasto->turno_id, $request->input('fecha'));

        // La cuenta solo cambia si mandan un método de pago; si no, se mantiene
        // la cuenta original del gasto (editar el monto no debe mover la cuenta).
        $metodoPagoId       = $request->input('metodo_pago_id');
        $cuentaMetodoPagoId = $request->input('cuenta_metodo_pago_id');
        $cuentaId = $metodoPagoId
            ? $this->tesoreria->resolverCuenta(
                $user->empresa_id,
                $cuentaMetodoPagoId ? (int) $cuentaMetodoPagoId : null,
                (int) $metodoPagoId,
              )
            : $gasto->cuenta_id;

        DB::transaction(function () use ($request, $user, $gasto, $cuentaId) {
            $antes = [
                'monto'       => (float) $gasto->monto,
                'fecha'       => optional($gasto->fecha)->toDateString(),
                'concepto_id' => $gasto->gasto_concepto_id,
                'cuenta_id'   => $gasto->cuenta_id,
            ];

            $gasto->update([
                'gasto_tipo_id'     => $request->input('gasto_tipo_id'),
                'gasto_concepto_id' => $request->input('gasto_concepto_id'),
                'monto'             => $request->input('monto'),
                'cuenta_id'         => $cuentaId,
                'fecha'             => $request->input('fecha'),
                'comentario'        => $request->input('comentario'),
            ]);

            // F7 — Reasentar tesorería: se revierte el egreso anterior y se
            // registra el nuevo con el monto/fecha/cuenta ya actualizados.
            $gasto->load('concepto');
            $this->tesoreria->revertir('gasto', $gasto->id);
            $this->tesoreria->registrar(
                $user->empresa_id,
                $gasto->cuenta_id,
                $user,
                $request->input('fecha'),
                'egreso',
                (float) $request->input('monto'),
                'Gasto — ' . ($gasto->concepto?->nombre ?? 'operativo'),
                'gasto',
                $gasto->id,
            );

            $this->recuadrarTurno($gasto->turno_id, $user);

            AuditoriaService::log('gasto.editado', $gasto, [
                'antes'   => $antes,
                'despues' => [
                    'monto'       => (float) $request->input('monto'),
                    'fecha'       => $request->input('fecha'),
                    'concepto_id' => (int) $request->input('gasto_concepto_id'),
                    'cuenta_id'   => $cuentaId,
                ],
            ], $user);
        });

        return redirect()->back()->with('success', 'Gasto actualizado correctamente.');
    }

    public function destroy(Request $request, Gasto $gasto)
    {
        $user = $request->user();
        abort_if($gasto->empresa_id !== $user->empresa_id, 403);
        $this->autorizarModificacion($gasto, $user);

        DB::transaction(function () use ($gasto, $user) {
            AuditoriaService::log('gasto.eliminado', $gasto, [
                'monto'    => (float) $gasto->monto,
                'fecha'    => optional($gasto->fecha)->toDateString(),
                'concepto' => $gasto->concepto?->nombre,
                'turno_id' => $gasto->turno_id,
            ], $user);

            // Revertir el egreso de tesorería y borrar en suave (queda en
            // "Eliminados" y se puede reactivar).
            $this->tesoreria->revertir('gasto', $gasto->id);
            $gasto->delete();
            $this->recuadrarTurno($gasto->turno_id, $user);
        });

        return redirect()->back()->with('success', 'Gasto eliminado. Puedes verlo en "Eliminados" y reactivarlo.');
    }

    public function restore(Request $request, int $id)
    {
        $user  = $request->user();
        $gasto = Gasto::withTrashed()->where('empresa_id', $user->empresa_id)->findOrFail($id);
        abort_unless($gasto->trashed(), 422, 'El gasto no está eliminado.');
        $this->autorizarModificacion($gasto, $user);

        DB::transaction(function () use ($gasto, $user) {
            $gasto->restore();

            // Volver a asentar el egreso que se revirtió al eliminar.
            $gasto->load('concepto');
            $this->tesoreria->registrar(
                $user->empresa_id,
                $gasto->cuenta_id,
                $user,
                optional($gasto->fecha)->toDateString(),
                'egreso',
                (float) $gasto->monto,
                'Gasto — ' . ($gasto->concepto?->nombre ?? 'operativo'),
                'gasto',
                $gasto->id,
            );

            $this->recuadrarTurno($gasto->turno_id, $user);

            AuditoriaService::log('gasto.reactivado', $gasto, [
                'monto' => (float) $gasto->monto,
                'fecha' => optional($gasto->fecha)->toDateString(),
            ], $user);
        });

        return redirect()->back()->with('success', 'Gasto reactivado. Volvió a descontarse de tesorería.');
    }

    /**
     * Reglas para editar / eliminar / reactivar un gasto: un administrador
     * puede con cualquiera (si el turno está cerrado, se recuadra al guardar);
     * un no-admin solo con SUS gastos de turno mientras el turno siga abierto
     * (nunca con gastos administrativos).
     */
    private function autorizarModificacion(Gasto $gasto, User $user): void
    {
        $turno = $gasto->turno;

        // Un turno cerrado ya cuadró su caja con este gasto. Solo el admin lo
        // corrige, y el cuadre del turno se recalcula en el acto (recuadrarTurno).
        // Exigir reabrir lo dejaba sin salida: reabrir se bloquea si la cajera
        // o la caja ya tienen otro turno abierto, que es lo normal al día siguiente.
        if ($turno && $turno->estado !== 'abierto') {
            abort_unless($user->rol?->es_admin, 422,
                'El turno de este gasto ya está cerrado: solo un administrador puede corregirlo.');
            // Consolidado: el conteo del consolidador ya se asentó contra el
            // esperado viejo; recalcularlo aquí lo dejaría a medias.
            abort_if($turno->consolidacion()->exists(), 422,
                'El turno de este gasto ya fue consolidado: reabre el turno para modificarlo.');
            // Cierre rápido con destino del efectivo: la entrega y lo que quedó
            // en el cajón salieron del esperado viejo; se corrigen reabriendo.
            abort_if($turno->monto_cierre_declarado === null && $turno->destino_efectivo !== null, 422,
                'El turno de este gasto se cerró entregando el efectivo según el sistema: reabre el turno para modificarlo.');
        }

        if ($user->rol?->es_admin) return;

        abort_if($gasto->esAdministrativo(), 403, 'Solo un administrador puede modificar gastos administrativos.');

        abort_if(!$turno, 403, 'Solo puedes modificar gastos de un turno abierto.');
        abort_if($turno->user_id !== $user->id, 403, 'Este gasto es del turno de otra persona: solo ella o un administrador pueden modificarlo.');
    }

    /**
     * Tras corregir un gasto de un turno CERRADO: recalcula el esperado y la
     * diferencia con el conteo que ya declaró la cajera, y reasienta el
     * sobrante/faltante en tesorería. Sin esto el gasto viejo seguía
     * "explicado" por un sobrante/faltante que ya no existe y la caja quedaba
     * contada dos veces. Turno abierto: no hace nada (se cuadra al cerrar).
     */
    private function recuadrarTurno(?int $turnoId, User $user): void
    {
        if (!$turnoId) return;

        $turno = Turno::whereKey($turnoId)->lockForUpdate()->first();
        if (!$turno || $turno->estado === 'abierto') return;

        $antes = [
            'esperado'   => $turno->monto_cierre_esperado !== null ? (float) $turno->monto_cierre_esperado : null,
            'diferencia' => $turno->diferencia !== null ? (float) $turno->diferencia : null,
        ];

        $esperado   = round($turno->calcularMontoEsperado(), 2);
        $declarado  = $turno->monto_cierre_declarado !== null ? (float) $turno->monto_cierre_declarado : null;
        $diferencia = $declarado === null ? null : round($declarado - $esperado, 2);

        $turno->update(['monto_cierre_esperado' => $esperado, 'diferencia' => $diferencia]);

        // Mismo criterio que TurnoController::cerrar: el asiento de
        // sobrante/faltante solo existe si la empresa no exige consolidación.
        // Conserva la fecha del asiento original (el día del cierre).
        $fechaAsiento = \App\Models\CuentaMovimiento::where('ref_tipo', 'cierre_turno')->where('ref_id', $turno->id)->value('fecha')
            ?? $turno->fecha_cierre?->toDateString() ?? now()->toDateString();
        $this->tesoreria->revertir('cierre_turno', $turno->id);
        if (!$turno->empresa?->requiere_consolidacion_caja && $diferencia !== null && abs($diferencia) >= 0.01) {
            $this->tesoreria->registrar(
                $turno->empresa_id,
                null, // efectivo
                $turno->user_cierre_id ?? $user,
                substr((string) $fechaAsiento, 0, 10),
                $diferencia > 0 ? 'ingreso' : 'egreso',
                abs($diferencia),
                ($diferencia > 0 ? 'Sobrante' : 'Faltante') . " de caja — cierre turno #{$turno->id} ({$turno->user?->name})",
                'cierre_turno',
                $turno->id,
            );
        }

        AuditoriaService::log('turno.cuadre_recalculado', $turno, [
            'motivo'  => 'Corrección de un gasto del turno cerrado',
            'antes'   => $antes,
            'despues' => ['esperado' => $esperado, 'diferencia' => $diferencia],
        ], $user);
    }

    /**
     * Un gasto de turno sale de ESE cajón: su fecha tiene que caer dentro del
     * turno (del día de apertura al de cierre, o a hoy si sigue abierto). Si
     * no, el balance lo pondría en un día y la caja en otro.
     */
    private function validarFechaEnTurno(?int $turnoId, ?string $fecha): void
    {
        if (!$turnoId || !$fecha) return;

        $turno = Turno::find($turnoId);
        if (!$turno || !$turno->fecha_apertura) return;

        $dia   = substr($fecha, 0, 10);
        $desde = $turno->fecha_apertura->toDateString();
        $hasta = ($turno->fecha_cierre ?? now())->toDateString();

        if ($dia < $desde || $dia > $hasta) {
            $rango = $desde === $hasta
                ? \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y')
                : \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') . ' al ' . \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha' => "La fecha del gasto debe ser la del turno ({$rango}). Si es de otro día, regístralo como gasto administrativo o en el turno de ese día.",
            ]);
        }
    }
}
