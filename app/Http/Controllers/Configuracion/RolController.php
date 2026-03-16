<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\RolRequest;
use App\Models\Empresa;
use App\Models\Rol;
use Inertia\Inertia;

class RolController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracion/Roles', [
            'roles'    => Rol::with('empresa')->orderBy('nombre')->get(),
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(),
        ]);
    }

    public function store(RolRequest $request)
    {
        Rol::create($request->validated());
        return redirect()->back()->with('success', 'Rol creado correctamente.');
    }

    public function update(RolRequest $request, Rol $rol)
    {
        $rol->update($request->validated());
        return redirect()->back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Rol $rol)
    {
        $rol->delete();
        return redirect()->back()->with('success', 'Rol eliminado correctamente.');
    }
}
