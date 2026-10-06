<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\CuentaRequest;
use App\Models\Cuenta;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CuentaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cuentas = Cuenta::deEmpresa($empresaId)
            ->with(['metodosPago' => fn($q) => $q->select('metodos_pago.id', 'nombre', 'tipo_id')->with('tipo:id,slug,nombre')])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/Cuentas', [
            'cuentas' => $cuentas,
        ]);
    }

    public function store(CuentaRequest $request)
    {
        Cuenta::create([
            'empresa_id' => $request->user()->empresa_id,
            ...$request->validated(),
        ]);

        return redirect()->back()->with('success', 'Cuenta creada correctamente.');
    }

    public function update(CuentaRequest $request, Cuenta $cuenta)
    {
        abort_if($cuenta->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validated();
        if ($cuenta->activo && array_key_exists('activo', $data) && ! $data['activo']) {
            $this->exigirSaldoCero($cuenta);
        }

        $cuenta->update($data);

        return redirect()->back()->with('success', 'Cuenta actualizada correctamente.');
    }

    public function destroy(Request $request, Cuenta $cuenta)
    {
        abort_if($cuenta->empresa_id !== $request->user()->empresa_id, 403);

        $this->exigirSaldoCero($cuenta);
        $cuenta->update(['activo' => false]);

        return redirect()->back()->with('success', 'Cuenta desactivada correctamente.');
    }

    /**
     * Una cuenta inactiva sale del balance: si tenía saldo, ese dinero
     * "desaparecía" del patrimonio. Solo se desactiva vacía.
     */
    private function exigirSaldoCero(Cuenta $cuenta): void
    {
        $saldo = app(TesoreriaService::class)->saldo($cuenta->id);

        abort_if(abs($saldo) >= 0.01, 422,
            "La cuenta «{$cuenta->nombre}» tiene un saldo de S/ " . number_format($saldo, 2)
            . '. Primero traslada el saldo a otra cuenta (o ajústalo a cero) y luego desactívala; '
            . 'si no, ese dinero desaparece del balance.');
    }
}
