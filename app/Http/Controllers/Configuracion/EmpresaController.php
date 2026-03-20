<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\EmpresaRequest;
use App\Models\Cliente;
use App\Models\Empresa;
use Inertia\Inertia;

class EmpresaController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracion/Empresas', [
            'empresas' => Empresa::orderBy('razon_social')->get(),
        ]);
    }

    public function store(EmpresaRequest $request)
    {
        $empresa = Empresa::create($request->validated());

        // Crear cliente general por defecto
        Cliente::create([
            'empresa_id'       => $empresa->id,
            'tipo_documento'   => 'DNI',
            'numero_documento' => '99999999',
            'nombres'          => 'Clientes Varios',
            'apellidos'        => '',
            'activo'           => true,
        ]);

        return redirect()->back()->with('success', 'Empresa creada correctamente.');
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        $empresa->update($request->validated());
        return redirect()->back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Empresa $empresa)
    {
        $empresa->delete();
        return redirect()->back()->with('success', 'Empresa eliminada correctamente.');
    }
}
