<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\UnidadMedidaRequest;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnidadMedidaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $unidades = UnidadMedida::deEmpresa($empresaId)
            ->when($request->search, fn($q, $s) => $q->where('nombre', 'ilike', "%{$s}%"))
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Catalogo/UnidadesMedida', [
            'unidades' => $unidades,
        ]);
    }

    public function store(UnidadMedidaRequest $request)
    {
        UnidadMedida::create([
            ...$request->validated(),
            'empresa_id' => $request->user()->empresa_id,
        ]);

        return redirect()->back()->with('success', 'Unidad de medida creada correctamente.');
    }

    public function update(UnidadMedidaRequest $request, UnidadMedida $unidadesMedida)
    {
        $unidadesMedida->update($request->validated());

        return redirect()->back()->with('success', 'Unidad de medida actualizada correctamente.');
    }

    public function destroy(UnidadMedida $unidadesMedida)
    {
        if ($unidadesMedida->productoUnidades()->exists()) {
            $unidadesMedida->update(['activo' => false]);
            return redirect()->back()->with('success', 'Unidad desactivada (está en uso por productos).');
        }

        $unidadesMedida->delete();
        return redirect()->back()->with('success', 'Unidad de medida eliminada correctamente.');
    }
}
