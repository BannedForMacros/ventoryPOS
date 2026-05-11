<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreCitaRequest;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Local;
use App\Models\Producto;
use App\Models\User;
use App\Services\CitaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgendaController extends Controller
{
    public function __construct(private CitaService $citaService) {}

    /**
     * Lista de citas con filtros. Default: las del dia actual.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->empresa->usa_agenda, 403, 'Esta empresa no tiene el módulo Agenda habilitado.');

        // Filtros
        $fechaDesde = $request->fecha_desde
            ? Carbon::parse($request->fecha_desde)->startOfDay()
            : Carbon::today()->startOfDay();
        $fechaHasta = $request->fecha_hasta
            ? Carbon::parse($request->fecha_hasta)->endOfDay()
            : Carbon::today()->endOfDay();

        $query = Cita::deEmpresa($user->empresa_id)
            ->with(['cliente:id,nombres,apellidos,razon_social,numero_documento',
                    'profesional:id,name',
                    'local:id,nombre',
                    'venta:id,numero,total',
                    'items.producto:id,nombre,codigo',
                    'items.productoUnidad.unidadMedida:id,nombre'])
            ->entreFechas($fechaDesde, $fechaHasta);

        // Filtro por usuario: si NO es admin, solo ve sus citas (como profesional o creador)
        if (!$user->rol?->es_admin) {
            $query->where(fn($q) =>
                $q->where('profesional_id', $user->id)
                  ->orWhere('created_by', $user->id)
            );
        }

        if ($request->estado) {
            $query->where('estado', $request->estado);
        }
        if ($request->profesional_id) {
            $query->where('profesional_id', $request->profesional_id);
        }
        if ($user->local_id) {
            $query->where('local_id', $user->local_id);
        } elseif ($request->local_id) {
            $query->where('local_id', $request->local_id);
        }

        $citas = $query->orderBy('fecha_hora')->get();

        // Resumen por estado para los chips de la cabecera
        $resumen = [
            'total'       => $citas->count(),
            'programadas' => $citas->where('estado', Cita::ESTADO_PROGRAMADA)->count(),
            'confirmadas' => $citas->where('estado', Cita::ESTADO_CONFIRMADA)->count(),
            'en_atencion' => $citas->where('estado', Cita::ESTADO_EN_ATENCION)->count(),
            'completadas' => $citas->where('estado', Cita::ESTADO_COMPLETADA)->count(),
            'canceladas'  => $citas->where('estado', Cita::ESTADO_CANCELADA)->count(),
            'no_asistio'  => $citas->where('estado', Cita::ESTADO_NO_ASISTIO)->count(),
        ];

        return Inertia::render('Agenda/Index', [
            'citas'         => $citas,
            'resumen'       => $resumen,
            'profesionales' => User::where('empresa_id', $user->empresa_id)
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'locales'       => $user->local_id
                ? Local::where('id', $user->local_id)->get(['id', 'nombre'])
                : Local::where('empresa_id', $user->empresa_id)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'agendaConfig'  => [
                'sujeto_label'     => $user->empresa->agenda_sujeto_label,
                'sujeto_requerido' => (bool) $user->empresa->agenda_sujeto_requerido,
            ],
            'filters'       => [
                'fecha_desde'    => $fechaDesde->toDateString(),
                'fecha_hasta'    => $fechaHasta->toDateString(),
                'estado'         => $request->estado,
                'profesional_id' => $request->profesional_id,
                'local_id'       => $user->local_id ?? $request->local_id,
            ],
            'estadosLabels' => [
                Cita::ESTADO_PROGRAMADA  => 'Programada',
                Cita::ESTADO_CONFIRMADA  => 'Confirmada',
                Cita::ESTADO_EN_ATENCION => 'En atención',
                Cita::ESTADO_COMPLETADA  => 'Completada',
                Cita::ESTADO_NO_ASISTIO  => 'No asistió',
                Cita::ESTADO_CANCELADA   => 'Cancelada',
            ],
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user->empresa->usa_agenda, 403);

        return Inertia::render('Agenda/Form', $this->datosFormulario($user));
    }

    public function edit(Request $request, Cita $cita)
    {
        $user = $request->user();
        abort_if($cita->empresa_id !== $user->empresa_id, 403);
        abort_unless($cita->estaActiva(), 422, 'No se puede editar una cita en estado ' . $cita->estado_label . '.');

        $cita->load(['items.producto', 'items.productoUnidad.unidadMedida', 'cliente', 'profesional']);

        return Inertia::render('Agenda/Form', array_merge(
            $this->datosFormulario($user),
            ['cita' => $cita],
        ));
    }

    public function store(StoreCitaRequest $request)
    {
        $cita = $this->citaService->crear($request->validated(), $request->user());

        return redirect()->route('agenda.show', $cita->id)
            ->with('success', "Cita {$cita->numero} creada correctamente.");
    }

    public function update(StoreCitaRequest $request, Cita $cita)
    {
        $user = $request->user();
        abort_if($cita->empresa_id !== $user->empresa_id, 403);
        abort_unless($cita->estaActiva(), 422);

        // Estrategia simple: borrar items viejos y recrear segun lo enviado.
        // Esto es seguro porque editar = recrear (la cita en si conserva su id, numero y auditoria).
        $data = $request->validated();
        \DB::transaction(function () use ($cita, $data, $user) {
            $cita->update([
                'local_id'           => $data['local_id'],
                'cliente_id'         => $data['cliente_id'],
                'profesional_id'     => $data['profesional_id'] ?? null,
                'fecha_hora'         => $data['fecha_hora'],
                'observaciones'      => $data['observaciones'] ?? null,
                'sujeto_nombre'      => $data['sujeto_nombre'] ?? null,
                'sujeto_descripcion' => $data['sujeto_descripcion'] ?? null,
            ]);

            $cita->items()->delete();

            $duracionTotal = 0;
            foreach ($data['items'] as $i => $item) {
                $unidad = \App\Models\ProductoUnidad::findOrFail($item['producto_unidad_id']);
                $duracion = (int) ($item['duracion_min'] ?? 30);
                $duracionTotal += $duracion * (int) ($item['cantidad'] ?? 1);

                $cita->items()->create([
                    'producto_id'        => $unidad->producto_id,
                    'producto_unidad_id' => $unidad->id,
                    'cantidad'           => $item['cantidad'] ?? 1,
                    'duracion_min'       => $duracion,
                    'precio_estimado'    => (float) $unidad->precio_venta,
                    'observaciones'      => $item['observaciones'] ?? null,
                    'orden'              => $i,
                ]);
            }
            $cita->update(['duracion_min' => $duracionTotal ?: 30]);
        });

        return redirect()->route('agenda.show', $cita->id)
            ->with('success', 'Cita actualizada correctamente.');
    }

    public function show(Request $request, Cita $cita)
    {
        $user = $request->user();
        abort_if($cita->empresa_id !== $user->empresa_id, 403);

        $cita->load([
            'cliente', 'profesional:id,name,email',
            'creador:id,name', 'local:id,nombre',
            'venta:id,numero,total,fecha_venta,estado',
            'items.producto:id,nombre,codigo,tipo',
            'items.productoUnidad.unidadMedida:id,nombre,abreviatura',
        ]);

        return Inertia::render('Agenda/Show', [
            'cita'         => $cita,
            'agendaConfig' => [
                'sujeto_label'     => $user->empresa->agenda_sujeto_label,
                'sujeto_requerido' => (bool) $user->empresa->agenda_sujeto_requerido,
            ],
        ]);
    }

    public function destroy(Request $request, Cita $cita)
    {
        $user = $request->user();
        abort_if($cita->empresa_id !== $user->empresa_id, 403);
        abort_unless($user->rol?->es_admin, 403, 'Solo administradores pueden eliminar citas.');
        abort_if($cita->venta_id, 422, 'No se puede eliminar una cita con venta asociada. Anula la venta primero.');

        $numero = $cita->numero;
        $cita->delete();

        return redirect()->route('agenda.index')
            ->with('success', "Cita {$numero} eliminada.");
    }

    // ── Transiciones de estado ───────────────────────────────────────────

    public function confirmar(Request $request, Cita $cita)
    {
        $this->guardEmpresa($request, $cita);
        try {
            $this->citaService->confirmar($cita, $request->user());
            return back()->with('success', 'Cita confirmada.');
        } catch (\LogicException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }
    }

    public function iniciar(Request $request, Cita $cita)
    {
        $this->guardEmpresa($request, $cita);
        try {
            $this->citaService->iniciar($cita, $request->user());
            return back()->with('success', 'Atención iniciada.');
        } catch (\LogicException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }
    }

    public function cancelar(Request $request, Cita $cita)
    {
        $this->guardEmpresa($request, $cita);
        $request->validate(['motivo' => 'required|string|max:500']);
        try {
            $this->citaService->cancelar($cita, $request->input('motivo'), $request->user());
            return back()->with('success', 'Cita cancelada.');
        } catch (\LogicException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }
    }

    public function noAsistio(Request $request, Cita $cita)
    {
        $this->guardEmpresa($request, $cita);
        try {
            $this->citaService->marcarNoAsistio($cita, $request->user());
            return back()->with('success', 'Cita marcada como no asistió.');
        } catch (\LogicException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }
    }

    /**
     * Redirige al POS con cita_id. El POS leera la cita y prellenara el carrito.
     * Si la cita aun no esta en_atencion, primero se marca como tal.
     */
    public function completar(Request $request, Cita $cita)
    {
        $this->guardEmpresa($request, $cita);

        if ($cita->venta_id) {
            return redirect()->route('ventas.show', $cita->venta_id)
                ->with('error', 'Esta cita ya fue cobrada en la venta indicada.');
        }
        if (!$cita->estaActiva()) {
            return back()->withErrors(['estado' => 'Esta cita está en estado ' . $cita->estado_label . ' y no puede cobrarse.']);
        }

        // Si esta programada/confirmada, pasarla a en_atencion para reflejar realidad
        if (!$cita->esEnAtencion()) {
            $this->citaService->iniciar($cita, $request->user());
        }

        return redirect()->route('pos.index', ['cita_id' => $cita->id]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function guardEmpresa(Request $request, Cita $cita): void
    {
        abort_if($cita->empresa_id !== $request->user()->empresa_id, 403);
    }

    /**
     * Datos comunes para crear/editar cita: locales, profesionales, productos+unidades, clientes, config.
     */
    private function datosFormulario(User $user): array
    {
        $empresaId = $user->empresa_id;

        return [
            'locales'       => $user->local_id
                ? Local::where('id', $user->local_id)->get(['id', 'nombre'])
                : Local::where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'profesionales' => User::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'clientes'      => Cliente::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('nombres')
                ->get(['id', 'nombres', 'apellidos', 'razon_social', 'tipo_documento', 'numero_documento']),
            'productos'     => Producto::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->with(['unidades' => fn($q) => $q->where('activo', true), 'unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'tipo']),
            'agendaConfig'  => [
                'sujeto_label'     => $user->empresa->agenda_sujeto_label,
                'sujeto_requerido' => (bool) $user->empresa->agenda_sujeto_requerido,
            ],
            'currentLocalId' => $user->local_id,
            'currentUserId'  => $user->id,
        ];
    }
}
