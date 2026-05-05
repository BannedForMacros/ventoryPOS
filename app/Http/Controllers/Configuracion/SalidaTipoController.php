<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\SalidaTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SalidaTipoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $tipos = SalidaTipo::deEmpresa($empresaId)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/SalidasTipos', [
            'tipos' => $tipos,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'activo' => 'boolean',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $empresaId = $request->user()->empresa_id;
        $slug = $this->generarSlugUnico($empresaId, $data['nombre']);

        SalidaTipo::create([
            'empresa_id' => $empresaId,
            'nombre'     => $data['nombre'],
            'slug'       => $slug,
            'es_sistema' => false,
            'activo'     => $data['activo'] ?? true,
            'orden'      => $data['orden'] ?? 100,
        ]);

        return redirect()->back()->with('success', 'Tipo de salida creado.');
    }

    public function update(Request $request, SalidaTipo $tipo)
    {
        abort_if($tipo->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'activo' => 'boolean',
            'orden'  => 'nullable|integer|min:0',
        ]);

        // Los tipos de sistema solo permiten editar nombre/orden, no eliminar
        $update = [
            'nombre' => $data['nombre'],
            'activo' => $data['activo'] ?? $tipo->activo,
            'orden'  => $data['orden'] ?? $tipo->orden,
        ];

        $tipo->update($update);

        return redirect()->back()->with('success', 'Tipo de salida actualizado.');
    }

    public function destroy(Request $request, SalidaTipo $tipo)
    {
        abort_if($tipo->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($tipo->es_sistema, 422, 'Los tipos del sistema no se pueden eliminar. Puedes desactivarlos.');

        if ($tipo->salidas()->exists()) {
            $tipo->update(['activo' => false]);
            return redirect()->back()->with('success', 'Tipo desactivado (tiene salidas registradas).');
        }

        $tipo->delete();
        return redirect()->back()->with('success', 'Tipo de salida eliminado.');
    }

    protected function generarSlugUnico(int $empresaId, string $nombre): string
    {
        $base = Str::slug($nombre, '_');
        $slug = $base;
        $i = 2;
        while (SalidaTipo::where('empresa_id', $empresaId)->where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }
        return $slug;
    }
}
