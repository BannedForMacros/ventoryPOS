<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * F7 — Tesorería: libro de movimientos por cuenta (efectivo y bancos).
 *
 * Aquí se VE de dónde viene y a dónde va cada sol: ventas, abonos, gastos,
 * pagos a proveedores, anticipos, adelantos, cuotas de deudas, devoluciones
 * y ajustes (siempre con motivo, siempre auditados).
 */
class TesoreriaController extends Controller
{
    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Garantizar que la cuenta Efectivo exista.
        $efectivo = TesoreriaService::efectivo($user->empresa_id);

        $cuentas = Cuenta::deEmpresa($user->empresa_id)->activo()
            ->orderByDesc('es_efectivo')->orderBy('nombre')->get()
            ->map(fn (Cuenta $c) => [
                'id'          => $c->id,
                'nombre'      => $c->nombre,
                'banco'       => $c->banco,
                'es_efectivo' => $c->es_efectivo,
                'saldo'       => $this->tesoreria->saldo($c->id),
            ]);

        $cuentaId = (int) $request->input('cuenta_id', $efectivo->id);

        $movimientos = CuentaMovimiento::deEmpresa($user->empresa_id)
            ->where('cuenta_id', $cuentaId)
            ->with('user')
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->where('fecha', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->where('fecha', '<=', $v))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return Inertia::render('Finanzas/Tesoreria', [
            'cuentas'     => $cuentas,
            'cuentaId'    => $cuentaId,
            'movimientos' => $movimientos,
            // Acciones visibles según la matriz de permisos del rol (los
            // ajustes se otorgan desde Configuración → Roles, nada hardcodeado).
            'puede'       => [
                'ajustar' => $user->tienePermiso('finanzas.tesoreria', 'editar'),
                'crear'   => $user->tienePermiso('finanzas.tesoreria', 'crear'),
            ],
        ]);
    }

    /**
     * Ajuste de saldo: el usuario cuenta el dinero real; el sistema genera
     * el movimiento por la diferencia, con motivo obligatorio y auditoría.
     */
    public function ajustar(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'cuenta_id'  => ['required', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'fecha'      => ['required', 'date'],
            'saldo_real' => ['required', 'numeric', 'min:0'],
            'motivo'     => ['required', 'string', 'min:5', 'max:250'],
        ]);

        $cuenta = Cuenta::findOrFail($data['cuenta_id']);

        $mov = $this->tesoreria->ajustarSaldo($cuenta, $user, $data['fecha'], (float) $data['saldo_real'], $data['motivo']);

        return back()->with('success', $mov
            ? 'Ajuste registrado: ' . ($mov->tipo === 'ingreso' ? '+' : '−') . 'S/ ' . number_format((float) $mov->monto, 2)
            : 'El saldo ya cuadra, no se generó ajuste.');
    }

    /**
     * Movimiento MANUAL de ajuste: ingreso o egreso directo sobre una cuenta,
     * con monto exacto y descripción (p. ej. "no cuadra: salida de S/ 700 de
     * BCP — ajuste de cuenta"). A diferencia de ajustar() (que declara el
     * saldo real), aquí el usuario indica el movimiento tal cual. Se gobierna
     * por la matriz de permisos (finanzas.tesoreria, crear) y queda auditado.
     */
    public function movimiento(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'cuenta_id'   => ['required', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'fecha'       => ['required', 'date'],
            'tipo'        => ['required', Rule::in(['ingreso', 'egreso'])],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'descripcion' => ['required', 'string', 'min:5', 'max:250'],
        ]);

        $mov = $this->tesoreria->registrar(
            $user->empresa_id,
            (int) $data['cuenta_id'],
            $user,
            $data['fecha'],
            $data['tipo'],
            (float) $data['monto'],
            'Ajuste manual — ' . $data['descripcion'],
            'ajuste',
            null,
        );

        \App\Services\AuditoriaService::log('tesoreria.movimiento_manual', $mov, [
            'cuenta_id'   => (int) $data['cuenta_id'],
            'tipo'        => $data['tipo'],
            'monto'       => (float) $data['monto'],
            'fecha'       => $data['fecha'],
            'descripcion' => $data['descripcion'],
        ], $user);

        return back()->with('success', 'Movimiento de ajuste registrado: '
            . ($data['tipo'] === 'ingreso' ? '+' : '−') . 'S/ ' . number_format((float) $data['monto'], 2));
    }
}
