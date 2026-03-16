<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\LocalRequest;
use App\Models\Empresa;
use App\Models\Local;
use Inertia\Inertia;

class LocalController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracion/Locales', [
            'locales'  => Local::with('empresa')->orderBy('nombre')->get(),
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(),
        ]);
    }

    public function store(LocalRequest $request)
    {
        Local::create($request->validated());
        return redirect()->back()->with('success', 'Local creado correctamente.');
    }

    public function update(LocalRequest $request, Local $local)
    {
        $local->update($request->validated());
        return redirect()->back()->with('success', 'Local actualizado correctamente.');
    }

    public function destroy(Local $local)
    {
        $local->delete();
        return redirect()->back()->with('success', 'Local eliminado correctamente.');
    }
}
