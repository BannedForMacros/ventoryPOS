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
        private \App\Services\FlotaService $flota,
        private \App\Services\GastoService $gastos,
    ) {}

    /**
     * Flota (si la empresa la usa): placas y choferes para cargar un gasto a un
     * camión y llenar la ficha de combustible / mantenimiento. null = sin flota.
     */
    public static function datosFlota(\App\Models\User $user): ?array
    {
        if (!\App\Support\Flota::activa($user->empresa)) return null;

        return [
            'vehiculos'   => \App\Models\Vehiculo::deEmpresa($user->empresa_id)->activo()->orderBy('placa')
                ->get(['id', 'placa', 'marca', 'modelo', 'conductor_id']),
            'conductores' => \App\Models\Conductor::deEmpresa($user->empresa_id)->activo()->where('tipo', 'chofer')
                ->orderBy('nombres')->get(['id', 'nombres', 'apellidos'])->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre_completo]),
        ];
    }

    /**
     * Para revisar el km mientras se escribe la carga: entre qué lecturas debe
     * caer en esa fecha y el precio por galón habitual del vehículo.
     */
    public function kmReferencia(Request $request)
    {
        $user = $request->user();
        abort_unless(\App\Support\Flota::activa($user->empresa), 404);
        $data = $request->validate([
            'vehiculo_id' => ['required', 'integer'],
            'fecha'       => ['required', 'date'],
            'gasto_id'    => ['nullable', 'integer'],
        ]);
        $vehiculo = \App\Models\Vehiculo::deEmpresa($user->empresa_id)->findOrFail($data['vehiculo_id']);
        $gastoId = !empty($data['gasto_id'])
            ? Gasto::withTrashed()->where('empresa_id', $user->empresa_id)->whereKey($data['gasto_id'])->value('id')
            : null;

        return response()->json($this->flota->referenciaKm($vehiculo->id, \Illuminate\Support\Carbon::parse($data['fecha'])->toDateString(), $gastoId));
    }

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
            ->with(['tipo', 'concepto', 'user', 'local', 'turno', 'vehiculo:id,placa', 'combustible', 'mantenimiento'])
            ->when($request->input('vehiculo_id'), fn($q, $v) => $q->where('vehiculo_id', $v))
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

        $form = $this->datosFormulario($user);

        return Inertia::render('Gastos/Index', [
            'gastos'          => $gastos,
            'scope'           => $scope,
            'mostrar'         => $mostrar,
            'buscar'          => $request->input('buscar', ''),
        ] + $form);
    }

    /**
     * Lo que necesita el modal de gasto (tipos con conceptos, locales, turnos
     * abiertos, métodos de pago con cuentas y la flota). Lo comparten Gastos y
     * la ficha del vehículo, que abre el mismo modal ya con la placa puesta.
     */
    public function datosFormulario(User $user): array
    {
        $esAdmin = $user->rol->es_admin;

        return [
            'tipos'          => GastoTipo::deEmpresa($user->empresa_id)->activo()
                ->with(['conceptos' => fn($q) => $q->activo()->orderBy('nombre')])
                ->orderBy('nombre')->get(),
            'locales'        => $this->scope->localesVisibles($user),
            // Para admin: turnos abiertos de la empresa para asignar gastos.
            'turnosAbiertos' => $esAdmin
                ? Turno::deEmpresa($user->empresa_id)->abierto()->with(['caja', 'user'])->get()
                : [],
            'esAdmin'        => $esAdmin,
            'flota'          => self::datosFlota($user),
            // Métodos de pago con sus cuentas (mismo patrón que el POS): el modal
            // muestra el método y, si tiene cuentas, un segundo select de cuenta.
            // La relación cuentas() usa withPivot('id') → cada cuenta trae pivot.id
            // (el cuenta_metodo_pago_id que el backend resuelve a una cuenta real).
            'metodosPago'    => \App\Models\MetodoPago::deEmpresa($user->empresa_id)->activo()
                ->with(['tipo:id,slug,nombre', 'cuentas' => fn($q) => $q->where('activo', true)])
                ->orderBy('nombre')->get(),
        ];
    }

    public function store(StoreGastoRequest $request)
    {
        $this->gastos->registrar($request->user(), $request->all());

        return redirect()->back()->with('success', 'Gasto registrado correctamente.');
    }

    public function update(UpdateGastoRequest $request, Gasto $gasto)
    {
        $user = $request->user();
        abort_if($gasto->empresa_id !== $user->empresa_id, 403);

        // Solo cambia el comentario: no mueve caja ni tesorería, así que el
        // admin lo corrige aunque el turno esté consolidado o cerrado con
        // entrega del efectivo (sin reabrirlo). El no-admin, con sus reglas.
        if ($user->rol?->es_admin && $this->soloCambiaComentario($request, $gasto)) {
            $antes = $gasto->comentario;
            DB::transaction(function () use ($gasto, $request) {
                $gasto->update(['comentario' => $request->input('comentario')]);
                // La ficha de flota (km, galones, placa) no mueve dinero.
                $this->flota->sincronizarGasto($gasto, $request->all());
            });
            AuditoriaService::log('gasto.editado', $gasto, [
                'antes'   => ['comentario' => $antes],
                'despues' => ['comentario' => $gasto->comentario],
            ], $user);

            return redirect()->back()->with('success', 'Comentario del gasto actualizado.');
        }

        $this->gastos->autorizarModificacion($gasto, $user);
        $this->gastos->validarFechaEnTurno($gasto->turno_id, $request->input('fecha'));

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
            $this->flota->sincronizarGasto($gasto, $request->all());

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

            $this->gastos->recuadrarTurno($gasto->turno_id, $user);

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
        $this->gastos->eliminar($gasto, $user);

        return redirect()->back()->with('success', 'Gasto eliminado. Puedes verlo en "Eliminados" y reactivarlo.');
    }

    public function restore(Request $request, int $id)
    {
        $user  = $request->user();
        $gasto = Gasto::withTrashed()->where('empresa_id', $user->empresa_id)->findOrFail($id);
        abort_unless($gasto->trashed(), 422, 'El gasto no está eliminado.');
        $this->gastos->autorizarModificacion($gasto, $user);

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

            $this->gastos->recuadrarTurno($gasto->turno_id, $user);

            AuditoriaService::log('gasto.reactivado', $gasto, [
                'monto' => (float) $gasto->monto,
                'fecha' => optional($gasto->fecha)->toDateString(),
            ], $user);
        });

        return redirect()->back()->with('success', 'Gasto reactivado. Volvió a descontarse de tesorería.');
    }

    /** ¿La edición deja igual todo lo que afecta dinero (tipo, concepto, monto, fecha y cuenta)? */
    private function soloCambiaComentario(Request $request, Gasto $gasto): bool
    {
        return ! $request->input('metodo_pago_id')
            && (int) $request->input('gasto_tipo_id') === (int) $gasto->gasto_tipo_id
            && (int) $request->input('gasto_concepto_id') === (int) $gasto->gasto_concepto_id
            && round((float) $request->input('monto'), 2) === round((float) $gasto->monto, 2)
            && $request->input('fecha') === optional($gasto->fecha)->toDateString();
    }



}
