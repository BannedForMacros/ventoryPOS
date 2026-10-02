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
        // Los módulos son el menú de TODAS las empresas: solo el superadmin los cambia.
        abort_unless(request()->user()?->es_superadmin, 403, 'Solo el superadministrador puede modificar los módulos.');

        Modulo::create($request->validated());
        return redirect()->back()->with('success', 'Módulo creado correctamente.');
    }

    public function update(ModuloRequest $request, Modulo $modulo)
    {
        // Los módulos son el menú de TODAS las empresas: solo el superadmin los cambia.
        abort_unless(request()->user()?->es_superadmin, 403, 'Solo el superadministrador puede modificar los módulos.');

        $modulo->update($request->validated());
        return redirect()->back()->with('success', 'Módulo actualizado correctamente.');
    }

    public function destroy(Modulo $modulo)
    {
        // Los módulos son el menú de TODAS las empresas: solo el superadmin los cambia.
        abort_unless(request()->user()?->es_superadmin, 403, 'Solo el superadministrador puede modificar los módulos.');

        $modulo->delete();
        return redirect()->back()->with('success', 'Módulo eliminado correctamente.');
    }
}
