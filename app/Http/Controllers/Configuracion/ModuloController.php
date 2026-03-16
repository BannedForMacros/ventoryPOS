<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ModuloRequest;
use App\Models\Modulo;
use Inertia\Inertia;

class ModuloController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracion/Modulos', [
            'modulos' => Modulo::with('padre')->orderBy('orden')->get(),
        ]);
    }

    public function store(ModuloRequest $request)
    {
        Modulo::create($request->validated());
        return redirect()->back()->with('success', 'Módulo creado correctamente.');
    }

    public function update(ModuloRequest $request, Modulo $modulo)
    {
        $modulo->update($request->validated());
        return redirect()->back()->with('success', 'Módulo actualizado correctamente.');
    }

    public function destroy(Modulo $modulo)
    {
        $modulo->delete();
        return redirect()->back()->with('success', 'Módulo eliminado correctamente.');
    }
}
