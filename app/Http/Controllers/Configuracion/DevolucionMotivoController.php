<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\DevolucionMotivo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DevolucionMotivoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $motivos = DevolucionMotivo::deEmpresa($empresaId)
            ->orderBy('orden')->orderBy('nombre')->get();

        return Inertia::render('Configuracion/DevolucionMotivos', [
            'motivos' => $motivos,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'afecta_restock_default' => 'required|in:permite,impide,obliga_merma',
            'activo' => 'boolean',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $empresaId = $request->user()->empresa_id;
        $slug = $this->generarSlugUnico($empresaId, $data['nombre']);

        DevolucionMotivo::create([
            'empresa_id' => $empresaId,
            'nombre'     => $data['nombre'],
            'slug'       => $slug,
            'afecta_restock_default' => $data['afecta_restock_default'],
            'es_sistema' => false,
            'activo'     => $data['activo'] ?? true,
            'orden'      => $data['orden'] ?? 100,
        ]);

        return redirect()->back()->with('success', 'Motivo de devolución creado.');
    }

    public function update(Request $request, DevolucionMotivo $motivo)
    {
        abort_if($motivo->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'afecta_restock_default' => 'required|in:permite,impide,obliga_merma',
            'activo' => 'boolean',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $motivo->update([
            'nombre' => $data['nombre'],
            'afecta_restock_default' => $data['afecta_restock_default'],
            'activo' => $data['activo'] ?? $motivo->activo,
            'orden'  => $data['orden'] ?? $motivo->orden,
        ]);

        return redirect()->back()->with('success', 'Motivo actualizado.');
    }

    public function destroy(Request $request, DevolucionMotivo $motivo)
    {
        abort_if($motivo->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($motivo->es_sistema, 422, 'Los motivos del sistema no se pueden eliminar. Puedes desactivarlos.');

        if ($motivo->devoluciones()->exists()) {
            $motivo->update(['activo' => false]);
            return redirect()->back()->with('success', 'Motivo desactivado (tiene devoluciones registradas).');
        }

        $motivo->delete();
        return redirect()->back()->with('success', 'Motivo eliminado.');
    }

    protected function generarSlugUnico(int $empresaId, string $nombre): string
    {
        $base = Str::slug($nombre, '_');
        $slug = $base;
        $i = 2;
        while (DevolucionMotivo::where('empresa_id', $empresaId)->where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }
        return $slug;
    }
}
