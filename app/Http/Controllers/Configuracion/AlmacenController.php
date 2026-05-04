<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Local;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $empresa   = Empresa::findOrFail($empresaId);

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
            'almacenes'    => $almacenes,
            'locales'      => $locales,
            'modo_almacen' => $empresa->modo_almacen,
        ]);
    }

    public function store(Request $request)
    {
        $empresa = Empresa::findOrFail($request->user()->empresa_id);

        if ($empresa->usaModoSimple()) {
            return back()->withErrors([
                'tipo' => 'En modo "simple" la empresa solo puede tener 1 almacén (creado automáticamente con el local). '
                    . 'Para añadir más almacenes, cambia el modo a "central + local".',
            ]);
        }

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

            $existeCentral = Almacen::deEmpresa($empresa->id)->central()->exists();
            if ($existeCentral) {
                return back()->withErrors([
                    'tipo' => 'La empresa ya tiene un almacén central. Solo puede haber uno.',
                ]);
            }
        }

        Almacen::create([
            'empresa_id' => $empresa->id,
            ...$data,
        ]);

        return redirect()->back()->with('success', 'Almacén creado correctamente.');
    }

    public function update(Request $request, Almacen $almacen)
    {
        abort_if($almacen->empresa_id !== $request->user()->empresa_id, 403);

        $empresa = Empresa::findOrFail($almacen->empresa_id);

        if ($empresa->usaModoSimple()) {
            $data = $request->validate([
                'nombre' => 'required|string|max:100',
                'activo' => 'boolean',
            ]);

            // En modo simple no permitimos cambiar tipo ni local_id
            $almacen->update([
                'nombre' => $data['nombre'],
                'activo' => $data['activo'] ?? $almacen->activo,
            ]);

            return redirect()->back()->with('success', 'Almacén actualizado correctamente.');
        }

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

            $existeOtroCentral = Almacen::deEmpresa($empresa->id)
                ->central()
                ->where('id', '!=', $almacen->id)
                ->exists();
            if ($existeOtroCentral) {
                return back()->withErrors([
                    'tipo' => 'La empresa ya tiene otro almacén central.',
                ]);
            }
        }

        $almacen->update($data);

        return redirect()->back()->with('success', 'Almacén actualizado correctamente.');
    }

    public function destroy(Request $request, Almacen $almacen)
    {
        abort_if($almacen->empresa_id !== $request->user()->empresa_id, 403);

        $empresa = Empresa::findOrFail($almacen->empresa_id);

        if ($empresa->usaModoSimple()) {
            return back()->withErrors([
                'tipo' => 'No se puede eliminar el único almacén en modo "simple".',
            ]);
        }

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
