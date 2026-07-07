<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\LocalRequest;
use App\Models\Empresa;
use App\Models\Local;
use App\Services\AlmacenSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class LocalController extends Controller
{
    public function __construct(private AlmacenSyncService $almacenSync) {}

    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        return Inertia::render('Configuracion/Locales', [
            'locales'  => Local::where('empresa_id', $empresaId)->with('empresa')->orderBy('nombre')->get(),
            'empresas' => Empresa::where('id', $empresaId)->where('activo', true)->orderBy('razon_social')->get(),
        ]);
    }

    public function store(LocalRequest $request)
    {
        $data = $request->validated();
        $data['empresa_id'] = $request->user()->empresa_id;

        try {
            DB::transaction(function () use ($data) {
                $local = Local::create($data);
                $this->almacenSync->sincronizarTrasCrearLocal($local);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['empresa_id' => $e->getMessage()])->withInput();
        }

        return redirect()->back()->with('success', 'Local creado correctamente.');
    }

    public function update(LocalRequest $request, Local $local)
    {
        abort_if($local->empresa_id !== $request->user()->empresa_id, 403);
        $local->update($request->validated());
        return redirect()->back()->with('success', 'Local actualizado correctamente.');
    }

    public function destroy(Request $request, Local $local)
    {
        abort_if($local->empresa_id !== $request->user()->empresa_id, 403);
        $local->delete();
        return redirect()->back()->with('success', 'Local eliminado correctamente.');
    }
}
