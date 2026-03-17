<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Local;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $almacenes = Almacen::deEmpresa($empresaId)
            ->with('local')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();

        $locales = Local::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/Almacenes', [
            'almacenes' => $almacenes,
            'locales'   => $locales,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'   => 'required|string|max:100',
            'tipo'     => 'required|in:central,local',
            'local_id' => 'nullable|exists:locales,id',
            'activo'   => 'boolean',
        ]);

        if ($data['tipo'] === 'local' && empty($data['local_id'])) {
            return back()->withErrors(['local_id' => 'Debes seleccionar un local para un almacén de tipo local.']);
        }

        if ($data['tipo'] === 'central') {
            $data['local_id'] = null;
        }

        Almacen::create([
            'empresa_id' => $request->user()->empresa_id,
            ...$data,
        ]);

        return redirect()->back()->with('success', 'Almacén creado correctamente.');
    }

    public function update(Request $request, Almacen $almacen)
    {
        abort_if($almacen->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validate([
            'nombre'   => 'required|string|max:100',
            'tipo'     => 'required|in:central,local',
            'local_id' => 'nullable|exists:locales,id',
            'activo'   => 'boolean',
        ]);

        if ($data['tipo'] === 'local' && empty($data['local_id'])) {
            return back()->withErrors(['local_id' => 'Debes seleccionar un local para un almacén de tipo local.']);
        }

        if ($data['tipo'] === 'central') {
            $data['local_id'] = null;
        }

        $almacen->update($data);

        return redirect()->back()->with('success', 'Almacén actualizado correctamente.');
    }

    public function destroy(Request $request, Almacen $almacen)
    {
        abort_if($almacen->empresa_id !== $request->user()->empresa_id, 403);

        $tieneMovimientos = $almacen->entradas()->exists()
            || $almacen->transferenciasOrigen()->exists()
            || $almacen->transferenciasDestino()->exists();

        if ($tieneMovimientos) {
            $almacen->update(['activo' => false]);
            return redirect()->back()->with('success', 'Almacén desactivado (tiene movimientos registrados).');
        }

        $almacen->delete();
        return redirect()->back()->with('success', 'Almacén eliminado correctamente.');
    }
}
