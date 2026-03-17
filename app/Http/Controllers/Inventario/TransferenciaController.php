<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Transferencia;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransferenciaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $this->abortSiModoSimple($request);

        $user       = $request->user();
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        $transferencias = Transferencia::where('empresa_id', $user->empresa_id)
            ->where(fn ($q) =>
                $q->whereIn('almacen_origen_id', $almacenIds)
                  ->orWhereIn('almacen_destino_id', $almacenIds)
            )
            ->with(['almacenOrigen.local', 'almacenDestino.local', 'user'])
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Inventario/Transferencias/Index', [
            'transferencias'  => $transferencias,
            'almacenes'       => $this->scope->almacenesVisibles($user),
            'filters'         => $request->only(['estado', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $this->abortSiModoSimple($request);

        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Transferencias/Create', [
            'almacenes' => $this->scope->almacenesVisibles($user),
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->abortSiModoSimple($request);

        $user = $request->user();

        $data = $request->validate([
            'almacen_origen_id'  => 'required|exists:almacenes,id',
            'almacen_destino_id' => 'required|exists:almacenes,id|different:almacen_origen_id',
            'fecha'              => 'required|date',
            'observacion'        => 'nullable|string',
            'detalles'           => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
        ]);

        $origen  = Almacen::find($data['almacen_origen_id']);
        $destino = Almacen::find($data['almacen_destino_id']);

        abort_if($origen->empresa_id !== $user->empresa_id, 403);
        abort_if($destino->empresa_id !== $user->empresa_id, 403);

        DB::transaction(function () use ($data, $user) {
            $transferencia = Transferencia::create([
                'empresa_id'         => $user->empresa_id,
                'almacen_origen_id'  => $data['almacen_origen_id'],
                'almacen_destino_id' => $data['almacen_destino_id'],
                'user_id'            => $user->id,
                'fecha'              => $data['fecha'],
                'observacion'        => $data['observacion'] ?? null,
                'estado'             => 'borrador',
            ]);

            foreach ($data['detalles'] as $d) {
                $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);

                // Capturar el costo promedio actual del origen para trazabilidad
                $stockOrigen   = Stock::where('almacen_id', $data['almacen_origen_id'])
                    ->where('producto_id', $d['producto_id'])
                    ->first();
                $costoUnitario = $stockOrigen ? (float) $stockOrigen->costo_promedio : 0;

                $transferencia->detalles()->create([
                    'producto_id'      => $d['producto_id'],
                    'unidad_medida_id' => $d['unidad_medida_id'],
                    'cantidad'         => $d['cantidad'],
                    'factor_conversion'=> $d['factor_conversion'],
                    'cantidad_base'    => $cantidadBase,
                    'costo_unitario'   => $costoUnitario,
                ]);
            }

            if (request()->boolean('confirmar')) {
                $transferencia->load('detalles.producto');
                $transferencia->confirmar();
            }
        });

        return redirect()->route('inventario.transferencias.index')
            ->with('success', 'Transferencia registrada correctamente.');
    }

    public function confirmar(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);

        $transferencia->load('detalles.producto');
        $transferencia->confirmar();

        return redirect()->back()->with('success', 'Transferencia confirmada. El stock ha sido actualizado.');
    }

    public function destroy(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->estado !== 'borrador', 403, 'Solo se pueden eliminar transferencias en borrador.');
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);

        $transferencia->detalles()->delete();
        $transferencia->delete();

        return redirect()->back()->with('success', 'Transferencia eliminada.');
    }

    private function abortSiModoSimple(Request $request): void
    {
        $empresa = $request->user()->empresa;
        abort_if($empresa->usaModoSimple(), 403, 'Las transferencias no están disponibles en modo simple.');
    }
}
