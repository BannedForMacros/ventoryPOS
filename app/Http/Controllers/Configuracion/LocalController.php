<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\LocalRequest;
use App\Models\Empresa;
use App\Models\Local;
use App\Services\AlmacenSyncService;
use Illuminate\Database\QueryException;
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
            'locales'  => Local::where('empresa_id', $empresaId)->with('empresa')->orderBy('nombre')->get()
                ->each(fn (Local $l) => $l->setAttribute('tiene_historia', $this->tieneHistoria($l))),
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

    /**
     * Un local con historia NO se borra: ventas, turnos, cajas y gastos
     * cuelgan de él con ON DELETE CASCADE, así que el borrado físico se
     * llevaba la historia por delante (y dejaba huérfanos los asientos de
     * tesorería). Con historia se desactiva; vacío se elimina junto con sus
     * cajas y almacenes, que tampoco tienen movimientos.
     */
    public function destroy(Request $request, Local $local)
    {
        abort_if($local->empresa_id !== $request->user()->empresa_id, 403);

        if ($this->tieneHistoria($local)) {
            $local->update(['activo' => false]);
            return redirect()->back()->with('success',
                "El local «{$local->nombre}» tiene historia (ventas, turnos, gastos o movimientos de inventario), "
                . 'así que no se eliminó: se desactivó para conservarla.');
        }

        try {
            DB::transaction(function () use ($local) {
                // Caja y almacén sin movimientos: se van con el local. El almacén
                // va primero porque su FK es SET NULL y un almacén tipo local
                // sin local viola chk_tipo_local (23514).
                $local->cajas()->delete();
                $local->almacenes()->each(fn ($a) => $a->delete());
                $local->delete();
            });
        } catch (QueryException $e) {
            // Alguna referencia que no contemplamos arriba: mejor desactivar
            // que mostrar un error técnico.
            $local->update(['activo' => false]);
            return redirect()->back()->with('success',
                "El local «{$local->nombre}» tiene registros asociados, así que no se eliminó: se desactivó.");
        }

        return redirect()->back()->with('success', 'Local eliminado correctamente.');
    }

    /**
     * ¿El local tiene algo que perder si se borra? Operaciones propias
     * (ventas, turnos, gastos, cotizaciones, devoluciones, citas) o
     * movimientos en alguno de sus almacenes.
     */
    private function tieneHistoria(Local $local): bool
    {
        foreach (['ventas', 'turnos', 'gastos', 'cotizaciones', 'devoluciones', 'citas'] as $tabla) {
            if (DB::table($tabla)->where('local_id', $local->id)->exists()) return true;
        }

        $almacenes = DB::table('almacenes')->where('local_id', $local->id)->pluck('id');
        if ($almacenes->isEmpty()) return false;

        foreach (['entradas', 'salidas', 'ajustes_inventario', 'cierres_inventario', 'movimientos_inventario', 'stock_iniciales'] as $tabla) {
            if (DB::table($tabla)->whereIn('almacen_id', $almacenes)->exists()) return true;
        }

        return DB::table('transferencias')
                ->where(fn ($q) => $q->whereIn('almacen_origen_id', $almacenes)->orWhereIn('almacen_destino_id', $almacenes))
                ->exists()
            || DB::table('stock')->whereIn('almacen_id', $almacenes)->where('cantidad', '!=', 0)->exists();
    }
}
