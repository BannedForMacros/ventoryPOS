<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Salida;
use App\Models\SalidaTipo;
use App\Models\Stock;
use App\Models\Turno;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class SalidaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user       = $request->user();
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        // M19: paginar para no cargar miles de filas en memoria.
        $salidas = Salida::whereIn('almacen_id', $almacenIds)
            ->with(['almacen.local', 'user', 'tipo'])
            ->when($request->almacen_id, fn ($q, $id) => $q->where('almacen_id', $id))
            ->when($request->salida_tipo_id, fn ($q, $id) => $q->where('salida_tipo_id', $id))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('numero_documento', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('tipo', fn ($x) => $x->where('nombre', 'ilike', "%{$t}%")));
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventario/Salidas/Index', [
            'salidas'         => $salidas,
            'almacenes'       => $this->scope->almacenesVisibles($user),
            'tipos'           => SalidaTipo::deEmpresa($user->empresa_id)->activo()->orderBy('orden')->orderBy('nombre')->get(),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'filters'         => $request->only(['almacen_id', 'salida_tipo_id', 'estado', 'fecha_desde', 'fecha_hasta']),
            'buscar'          => $request->input('buscar', ''),
        ]);
    }

    public function create(Request $request)
    {
        $user       = $request->user();
        $empresaId  = $user->empresa_id;
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        // Stock disponible por (almacen, producto) — el frontend lo usa para mostrar
        // "Disponible: X" al lado de cada producto y bloquear cantidades que excedan.
        // Mejor traerlo todo aca que hacer N fetches via ajax desde el form.
        $stocks = Stock::whereIn('almacen_id', $almacenIds)
            ->get(['almacen_id', 'producto_id', 'cantidad']);

        return Inertia::render('Inventario/Salidas/Create', [
            'almacenes' => $this->scope->almacenesVisibles($user),
            'tipos'     => SalidaTipo::deEmpresa($empresaId)->activo()->orderBy('orden')->orderBy('nombre')->get(),
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
            'stocks'    => $stocks,
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'almacen_id'       => 'required|exists:almacenes,id',
            'salida_tipo_id'   => 'required|exists:salida_tipos,id',
            'turno_id'         => 'nullable|exists:turnos,id',
            'numero_documento' => 'nullable|string|max:50',
            'fecha'            => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.observacion'       => 'nullable|string',
        ]);

        $almacen = Almacen::findOrFail($data['almacen_id']);
        abort_unless($this->scope->puedeAccederAlmacen($user, $almacen), 403);

        // Validar que el turno (si viene) sea del usuario, esté abierto y corresponda al local del almacén
        if (!empty($data['turno_id'])) {
            $turno = Turno::where('id', $data['turno_id'])
                ->where('user_id', $user->id)
                ->where('estado', 'abierto')
                ->firstOrFail();

            if ($almacen->local_id !== $turno->local_id) {
                return back()->withErrors([
                    'turno_id' => 'El turno solo puede asociarse al almacén del local del turno.',
                ]);
            }
        }

        // Validar que el tipo pertenece a la empresa
        $tipo = SalidaTipo::where('id', $data['salida_tipo_id'])
            ->where('empresa_id', $user->empresa_id)
            ->firstOrFail();

        try {
            $salida = DB::transaction(function () use ($data, $user) {
                $salida = Salida::create([
                    'empresa_id'       => $user->empresa_id,
                    'almacen_id'       => $data['almacen_id'],
                    'user_id'          => $user->id,
                    'turno_id'         => $data['turno_id'] ?? null,
                    'salida_tipo_id'   => $data['salida_tipo_id'],
                    'numero_documento' => $data['numero_documento'] ?? null,
                    'fecha'            => $data['fecha'],
                    'observacion'      => $data['observacion'] ?? null,
                    'estado'           => 'borrador',
                    'total'            => 0,
                ]);

                foreach ($data['detalles'] as $d) {
                    $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);

                    $salida->detalles()->create([
                        'producto_id'      => $d['producto_id'],
                        'unidad_medida_id' => $d['unidad_medida_id'],
                        'cantidad'         => $d['cantidad'],
                        'factor_conversion'=> $d['factor_conversion'],
                        'cantidad_base'    => $cantidadBase,
                        'costo_unitario'   => 0, // se llenará al confirmar
                        'subtotal'         => 0, // se calcula al confirmar
                        'observacion'      => $d['observacion'] ?? null,
                    ]);
                }

                if (request()->boolean('confirmar')) {
                    $salida->confirmar();
                }

                return $salida;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['detalles' => $e->getMessage()])->withInput();
        }

        return redirect()->route('inventario.salidas.index')
            ->with('success', 'Salida registrada correctamente.');
    }

    public function edit(Request $request, Salida $salida)
    {
        abort_if(!$salida->esBorrador(), 403, 'Solo se pueden editar salidas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $salida->almacen), 403);

        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Salidas/Edit', [
            'salida'    => $salida->load(['detalles.producto', 'detalles.unidadMedida', 'tipo']),
            'almacenes' => $this->scope->almacenesVisibles($user),
            'tipos'     => SalidaTipo::deEmpresa($empresaId)->activo()->orderBy('orden')->orderBy('nombre')->get(),
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
        ]);
    }

    public function update(Request $request, Salida $salida)
    {
        abort_if(!$salida->esBorrador(), 403, 'Solo se pueden editar salidas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $salida->almacen), 403);

        $data = $request->validate([
            'almacen_id'       => 'required|exists:almacenes,id',
            'salida_tipo_id'   => 'required|exists:salida_tipos,id',
            'numero_documento' => 'nullable|string|max:50',
            'fecha'            => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.observacion'       => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $salida) {
            $salida->update([
                'almacen_id'       => $data['almacen_id'],
                'salida_tipo_id'   => $data['salida_tipo_id'],
                'numero_documento' => $data['numero_documento'] ?? null,
                'fecha'            => $data['fecha'],
                'observacion'      => $data['observacion'] ?? null,
            ]);

            $salida->detalles()->delete();

            foreach ($data['detalles'] as $d) {
                $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);

                $salida->detalles()->create([
                    'producto_id'      => $d['producto_id'],
                    'unidad_medida_id' => $d['unidad_medida_id'],
                    'cantidad'         => $d['cantidad'],
                    'factor_conversion'=> $d['factor_conversion'],
                    'cantidad_base'    => $cantidadBase,
                    'costo_unitario'   => 0,
                    'subtotal'         => 0,
                    'observacion'      => $d['observacion'] ?? null,
                ]);
            }
        });

        return redirect()->route('inventario.salidas.index')
            ->with('success', 'Salida actualizada correctamente.');
    }

    public function confirmar(Request $request, Salida $salida)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $salida->almacen), 403);

        try {
            $salida->confirmar();
        } catch (RuntimeException $e) {
            return back()->withErrors(['confirmar' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Salida confirmada. Stock descontado.');
    }

    public function destroy(Request $request, Salida $salida)
    {
        abort_if(!$salida->esBorrador(), 403, 'Solo se pueden eliminar salidas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $salida->almacen), 403);

        $salida->detalles()->delete();
        $salida->delete();

        return redirect()->back()->with('success', 'Salida eliminada.');
    }
}
