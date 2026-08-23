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
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('descripcion', 'ilike', "%{$t}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$t}%")));
            })
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return Inertia::render('Finanzas/Tesoreria', [
            'cuentas'     => $cuentas,
            'cuentaId'    => $cuentaId,
            'movimientos' => $movimientos,
            'buscar'      => $request->input('buscar', ''),
            // Acciones visibles según la matriz de permisos del rol (los
            // ajustes se otorgan desde Configuración → Roles, nada hardcodeado).
            'puede'       => [
                'ajustar'  => $user->tienePermiso('finanzas.tesoreria', 'editar'),
                'crear'    => $user->tienePermiso('finanzas.tesoreria', 'crear'),
                'eliminar' => $user->tienePermiso('finanzas.tesoreria', 'editar'),
            ],
        ]);
    }

    /**
     * Exporta TODOS los movimientos filtrados a CSV (Excel).
     * Respeta los mismos filtros que index() y no pagina.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        $efectivo = TesoreriaService::efectivo($user->empresa_id);
        $cuentaId = (int) $request->input('cuenta_id', $efectivo->id);

        $movimientos = CuentaMovimiento::deEmpresa($user->empresa_id)
            ->where('cuenta_id', $cuentaId)
            ->with('user')
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->where('fecha', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->where('fecha', '<=', $v))
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('descripcion', 'ilike', "%{$t}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$t}%")));
            })
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get();

        $labels = [
            'venta' => 'Venta',
            'venta_abono' => 'Abono de cliente',
            'gasto' => 'Gasto',
            'entrada_pago' => 'Pago a proveedor',
            'entrada' => 'Pago a proveedor',
            'cliente_anticipo' => 'Anticipo de cliente',
            'cliente_anticipo_devolucion' => 'Devolución de anticipo',
            'proveedor_adelanto' => 'Adelanto a proveedor',
            'proveedor_adelanto_devolucion' => 'Devolución de adelanto',
            'deuda_pago' => 'Deuda / préstamo',
            'devolucion' => 'Reembolso devolución',
            'ajuste' => 'Ajuste manual',
        ];

        $headers = ['Fecha', 'Tipo', 'Descripción', 'Origen', 'Monto', 'Registrado por'];

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8
        $csv .= implode(',', array_map(fn ($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

        foreach ($movimientos as $m) {
            $row = [
                optional($m->fecha)->format('d/m/Y') ?? '—',
                $m->tipo === 'ingreso' ? 'Ingreso' : 'Egreso',
                $m->descripcion,
                $labels[$m->ref_tipo] ?? ($m->ref_tipo ?? '—'),
                number_format((float) $m->monto, 2, '.', ''),
                $m->user?->name ?? '—',
            ];

            $csv .= implode(',', array_map(fn ($c) => '"' . str_replace('"', '""', $c) . '"', $row)) . "\n";
        }

        $filename = 'tesoreria_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
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

    /**
     * Elimina un movimiento MANUAL de ajuste (ref_tipo = 'ajuste'). Solo esos:
     * los movimientos AUTOMÁTICOS (ventas, gastos, pagos, abonos…) NO se borran
     * desde aquí — esos se corrigen anulando/editando su operación de origen.
     * Si te equivocaste al registrar un ajuste manual, aquí lo eliminas y el
     * saldo de la cuenta se recalcula solo. Queda auditado.
     */
    public function destroy(Request $request, CuentaMovimiento $movimiento)
    {
        $user = $request->user();

        // Debe ser de la misma empresa y ser un ajuste manual, nunca un
        // movimiento generado por una operación real del negocio.
        abort_unless($movimiento->empresa_id === $user->empresa_id, 403);
        abort_unless($movimiento->ref_tipo === 'ajuste', 422,
            'Solo se pueden eliminar movimientos manuales de ajuste. Los demás se corrigen desde su operación de origen.');

        // Snapshot para auditoría ANTES de borrar (luego el modelo ya no existe).
        \App\Services\AuditoriaService::log('tesoreria.movimiento_eliminado', $movimiento, [
            'cuenta_id'   => $movimiento->cuenta_id,
            'tipo'        => $movimiento->tipo,
            'monto'       => (float) $movimiento->monto,
            'fecha'       => optional($movimiento->fecha)->toDateString(),
            'descripcion' => $movimiento->descripcion,
        ], $user);

        $movimiento->delete();

        return back()->with('success', 'Movimiento de ajuste eliminado. El saldo se recalculó.');
    }
}
