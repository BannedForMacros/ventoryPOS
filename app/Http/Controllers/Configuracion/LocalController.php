<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\LocalRequest;
use App\Models\Empresa;
use App\Models\Local;
use App\Services\AlmacenSyncService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class LocalController extends Controller
{
    public function __construct(private AlmacenSyncService $almacenSync) {}

    public function index()
    {
        return Inertia::render('Configuracion/Locales', [
            'locales'  => Local::with('empresa')->orderBy('nombre')->get(),
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(),
        ]);
    }

    public function store(LocalRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $local = Local::create($request->validated());
                $this->almacenSync->sincronizarTrasCrearLocal($local);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['empresa_id' => $e->getMessage()])->withInput();
        }

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
