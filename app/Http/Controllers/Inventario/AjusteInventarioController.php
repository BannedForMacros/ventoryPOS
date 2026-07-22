<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AjusteInventario;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Stock;
use App\Services\AuditoriaService;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Ajustes de inventario: suben (ingreso) o bajan (salida) el stock de un
 * producto por cantidad, con fecha propia, SIN mover dinero. Documento fuente
 * (sobrevive al "Recalcular stock"). Ver App\Models\AjusteInventario.
 */
class AjusteInventarioController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user       = $request->user();
        $almacenes  = $this->scope->almacenesVisibles($user);
        $almacenIds = $almacenes->pluck('id')->toArray();

        $ajustes = AjusteInventario::deEmpresa($user->empresa_id)
            ->whereIn('almacen_id', $almacenIds)
            ->with(['almacen:id,nombre', 'producto:id,nombre,codigo', 'user:id,name'])
            ->when($request->almacen_id, fn ($q, $v) => $q->where('almacen_id', $v))
            ->when($request->tipo, fn ($q, $v) => $q->where('tipo', $v))
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->fecha_desde, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($request->fecha_hasta, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($request->buscar, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('numero', 'ilike', "%{$v}%")
                ->orWhere('motivo', 'ilike', "%{$v}%")
                ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'ilike', "%{$v}%")->orWhere('codigo', 'ilike', "%{$v}%"))))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (AjusteInventario $a) => [
                'id'            => $a->id,
                'numero'        => $a->numero,
                'fecha'         => $a->fecha?->toDateString(),
                'tipo'          => $a->tipo,
                'cantidad_base' => (float) $a->cantidad_base,
                'estado'        => $a->estado,
                'motivo'        => $a->motivo,
                'almacen'       => $a->almacen?->nombre ?? '—',
                'producto'      => $a->producto?->nombre ?? '—',
                'producto_codigo' => $a->producto?->codigo,
                'usuario'       => $a->user?->name,
            ]);

        return Inertia::render('Inventario/Ajustes/Index', [
            'ajustes'         => $ajustes,
            'almacenes'       => $almacenes->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombre]),
            'productos'       => Producto::where('empresa_id', $user->empresa_id)->where('activo', true)
                                    ->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'puede'           => ['editar' => $user->tienePermiso('inventario.ajustes', 'editar')],
            'filters'         => $request->only(['almacen_id', 'tipo', 'estado', 'fecha_desde', 'fecha_hasta', 'buscar']),
        ]);
    }

    /**
     * Registra y CONFIRMA un ajuste (flujo rápido desde la lista de Stock o la
     * pantalla de ajustes): sube o baja el stock del producto al instante.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'almacen_id'  => ['required', 'integer', Rule::exists('almacenes', 'id')->where('empresa_id', $user->empresa_id)],
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $user->empresa_id)],
            'tipo'        => ['required', Rule::in(['ingreso', 'salida'])],
            'cantidad'    => ['required', 'numeric', 'gt:0'],
            'fecha'       => ['required', 'date'],
            'motivo'      => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $almacen = Almacen::findOrFail($data['almacen_id']);
        abort_unless($this->scope->puedeAccederAlmacen($user, $almacen), 403);

        $ajuste = DB::transaction(function () use ($data, $user) {
            $ajuste = AjusteInventario::create([
                'empresa_id'    => $user->empresa_id,
                'almacen_id'    => $data['almacen_id'],
                'producto_id'   => $data['producto_id'],
                'user_id'       => $user->id,
                'numero'        => AjusteInventario::generarNumero($user->empresa_id),
                'tipo'          => $data['tipo'],
                'cantidad_base' => round((float) $data['cantidad'], 4),
                'fecha'         => $data['fecha'],
                'estado'        => 'borrador',
                'motivo'        => $data['motivo'],
            ]);

            $ajuste->confirmar();

            return $ajuste;
        });

        AuditoriaService::log('ajuste_inventario.creado', $ajuste, [
            'numero'   => $ajuste->numero,
            'tipo'     => $ajuste->tipo,
            'cantidad' => (float) $ajuste->cantidad_base,
            'fecha'    => $ajuste->fecha->toDateString(),
            'producto' => $ajuste->producto?->nombre,
            'motivo'   => $ajuste->motivo,
        ], $user);

        return back()->with('success', "Ajuste {$ajuste->numero} aplicado: stock actualizado.");
    }

    /** Anula un ajuste confirmado: revierte el stock y lo marca 'anulado'. */
    public function anular(Request $request, AjusteInventario $ajuste)
    {
        $user = $request->user();
        abort_if($ajuste->empresa_id !== $user->empresa_id, 403);
        abort_unless($this->scope->puedeAccederAlmacen($user, $ajuste->almacen), 403);

        $data = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']]);

        $info = ['numero' => $ajuste->numero, 'tipo' => $ajuste->tipo, 'cantidad' => (float) $ajuste->cantidad_base];

        $ajuste->anular($user->id);

        AuditoriaService::log('ajuste_inventario.anulado', $ajuste, $info + ['motivo' => $data['motivo']], $user);

        return back()->with('success', "Ajuste {$ajuste->numero} anulado: stock revertido.");
    }
}
