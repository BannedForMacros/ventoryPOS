<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\CategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $categorias = Categoria::deEmpresa($empresaId)
            ->when($request->search, fn($q, $s) => $q->where('nombre', 'ilike', "%{$s}%"))
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Catalogo/Categorias', [
            'categorias' => $categorias,
        ]);
    }

    public function store(CategoriaRequest $request)
    {
        Categoria::create([
            ...$request->validated(),
            'empresa_id' => $request->user()->empresa_id,
        ]);

        return redirect()->back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(CategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update($request->validated());

        return redirect()->back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Categoria $categoria)
    {
        if ($categoria->productos()->exists()) {
            $categoria->update(['activo' => false]);
            return redirect()->back()->with('success', 'Categoría desactivada (tiene productos asociados).');
        }

        $categoria->delete();
        return redirect()->back()->with('success', 'Categoría eliminada correctamente.');
    }
}
