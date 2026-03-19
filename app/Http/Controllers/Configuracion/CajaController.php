<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CajaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $cajas = Caja::deEmpresa($user->empresa_id)
            ->with(['local', 'turnos' => fn($q) => $q->where('estado', 'abierto')])
            ->when($user->local_id, fn($q) => $q->where('local_id', $user->local_id))
            ->orderBy('local_id')->orderBy('nombre')
            ->get();

        $locales = $this->scope->localesVisibles($user);

        return Inertia::render('Configuracion/Cajas', [
            'cajas'   => $cajas,
            'locales' => $locales,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'local_id'                  => ['required', 'exists:locales,id'],
            'nombre'                    => ['required', 'string', 'max:100'],
            'caja_chica_activa'         => ['boolean'],
            'caja_chica_monto_sugerido' => ['nullable', 'numeric', 'min:0'],
            'caja_chica_en_arqueo'      => ['boolean'],
            'activo'                    => ['boolean'],
        ]);

        Caja::create([...$data, 'empresa_id' => $user->empresa_id]);

        return redirect()->back()->with('success', 'Caja creada correctamente.');
    }

    public function update(Request $request, Caja $caja)
    {
        abort_if($caja->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validate([
            'nombre'                    => ['required', 'string', 'max:100'],
            'caja_chica_activa'         => ['boolean'],
            'caja_chica_monto_sugerido' => ['nullable', 'numeric', 'min:0'],
            'caja_chica_en_arqueo'      => ['boolean'],
            'activo'                    => ['boolean'],
        ]);

        if (isset($data['activo']) && !$data['activo'] && $caja->tieneTurnoAbierto()) {
            return back()->withErrors(['activo' => 'No se puede desactivar una caja con turno abierto.']);
        }

        $caja->update($data);

        return redirect()->back()->with('success', 'Caja actualizada correctamente.');
    }

    public function destroy(Request $request, Caja $caja)
    {
        abort_if($caja->empresa_id !== $request->user()->empresa_id, 403);

        if ($caja->tieneTurnoAbierto()) {
            return back()->withErrors(['caja' => 'No se puede eliminar una caja con turno abierto.']);
        }

        $caja->update(['activo' => false]);

        return redirect()->back()->with('success', 'Caja desactivada correctamente.');
    }
}
