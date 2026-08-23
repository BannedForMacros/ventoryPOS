<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\PlanillaDescuento;
use App\Models\Turno;
use App\Models\TurnoConsolidacion;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * F8 — Consolidación de caja: el supervisor verifica TODO el cierre de la
 * cajera — efectivo (billetes/monedas) Y cada método de pago (Yape,
 * tarjeta, transferencias) — viendo lo declarado. Su conteo manda: la
 * diferencia de cada línea asienta en la cuenta de tesorería del método,
 * y el balance diario (que lee tesorería) refleja lo consolidado.
 */
class ConsolidacionController extends Controller
{
    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $empresa = $user->empresa;

        $estado = $request->input('estado', 'pendientes');

        $query = Turno::deEmpresa($user->empresa_id)
            ->cerrado()
            ->with(['caja', 'user', 'userCierre', 'consolidacion.user', 'consolidacion.items.metodoPago', 'arqueoMetodos.metodoPago'])
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->whereDate('fecha_cierre', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->whereDate('fecha_cierre', '<=', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->whereHas('caja', fn ($c) => $c->where('nombre', 'ilike', "%{$t}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$t}%")));
            });

        // 'pendientes' (default) | 'consolidados' | 'todos'
        if ($estado === 'pendientes') {
            $query->whereDoesntHave('consolidacion');
        } elseif ($estado === 'consolidados') {
            $query->whereHas('consolidacion');
        }

        $turnos = $query->orderByDesc('fecha_cierre')->paginate(20)->withQueryString();

        // Esperado por método NO-efectivo para cada turno de la página:
        // ventas completadas del turno agrupadas por método (neto de vuelto).
        $turnoIds = collect($turnos->items())->pluck('id');
        $esperadosPorMetodo = DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->join('tipos_metodo_pago', 'tipos_metodo_pago.id', '=', 'metodos_pago.tipo_id')
            ->whereIn('ventas.turno_id', $turnoIds)
            ->where('ventas.estado', 'completada')
            ->where('tipos_metodo_pago.slug', '!=', 'efectivo')
            ->selectRaw('ventas.turno_id, venta_pagos.metodo_pago_id, metodos_pago.nombre, SUM(venta_pagos.monto - venta_pagos.vuelto) as esperado')
            ->groupBy('ventas.turno_id', 'venta_pagos.metodo_pago_id', 'metodos_pago.nombre')
            ->get()
            ->groupBy('turno_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'metodo_pago_id' => (int) $r->metodo_pago_id,
                'nombre'         => $r->nombre,
                'esperado'       => round((float) $r->esperado, 2),
            ])->values());

        return Inertia::render('Finanzas/Consolidacion', [
            'turnos'                => $turnos,
            'esperadosPorMetodo'    => $esperadosPorMetodo,
            'estado'                => $estado,
            'buscar'                => $request->input('buscar', ''),
            'requiereConsolidacion' => (bool) $empresa->requiere_consolidacion_caja,
        ]);
    }

    /**
     * Exporta TODOS los turnos filtrados a CSV (Excel), respetando estado,
     * rango de fechas y búsqueda. Las columnas coinciden con las visibles en la tabla.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        $estado = $request->input('estado', 'pendientes');

        $query = Turno::deEmpresa($user->empresa_id)
            ->cerrado()
            ->with(['caja', 'user', 'consolidacion.user', 'arqueoMetodos.metodoPago'])
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->whereDate('fecha_cierre', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->whereDate('fecha_cierre', '<=', $v))
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->whereHas('caja', fn ($c) => $c->where('nombre', 'ilike', "%{$t}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$t}%")));
            });

        if ($estado === 'pendientes') {
            $query->whereDoesntHave('consolidacion');
        } elseif ($estado === 'consolidados') {
            $query->whereHas('consolidacion');
        }

        $turnos = $query->orderByDesc('fecha_cierre')->get();

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8
        $headers = ['Cierre', 'Caja', 'Cajera', 'Caja chica', 'Declaró (efectivo)', 'Otros métodos', 'Consolidación'];
        $csv .= implode(',', array_map($this->escaparCsv(...), $headers)) . "\n";

        foreach ($turnos as $t) {
            $cierre = $t->fecha_cierre?->format('d/m/Y H:i') ?? '—';
            $caja = $t->caja?->nombre ?? '—';
            $cajera = $t->user?->name ?? '—';
            $cajaChica = 'S/ ' . number_format((float) $t->monto_caja_chica, 2, '.', '');
            $declaro = $t->monto_cierre_declarado !== null
                ? 'S/ ' . number_format((float) $t->monto_cierre_declarado, 2, '.', '')
                : 'Cierre rápido';

            $otrosMetodos = $t->arqueoMetodos->map(
                fn ($m) => ($m->metodoPago?->nombre ?? 'Método') . ': S/ ' . number_format((float) $m->monto_declarado, 2, '.', '')
            )->implode(' · ') ?: '—';

            $consolidacion = $t->consolidacion
                ? 'Consolidado por ' . ($t->consolidacion->user?->name ?? '—')
                : 'Pendiente';

            $row = [$cierre, $caja, $cajera, $cajaChica, $declaro, $otrosMetodos, $consolidacion];
            $csv .= implode(',', array_map($this->escaparCsv(...), $row)) . "\n";
        }

        $filename = 'consolidacion_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function escaparCsv(string $value): string
    {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
    }

    /**
     * Registra el conteo del consolidador: una línea por efectivo y por
     * cada método verificado. items[]: {metodo_pago_id: null|id, contado}.
     * metodo_pago_id null = EFECTIVO.
     */
    public function consolidar(Request $request, Turno $turno)
    {
        $user = $request->user();
        abort_if($turno->empresa_id !== $user->empresa_id, 403);
        abort_unless($turno->estado === 'cerrado', 422, 'Solo se consolidan turnos cerrados.');
        abort_if($turno->consolidacion()->exists(), 422, 'Este turno ya fue consolidado.');

        $data = $request->validate([
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.metodo_pago_id'   => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'items.*.contado'          => ['required', 'numeric', 'min:0'],
            'observacion'              => ['nullable', 'string', 'max:500'],
            'generar_descuento'        => ['nullable', 'boolean'],
        ]);

        // Debe venir exactamente UNA línea de efectivo (metodo_pago_id null).
        $lineasEfectivo = collect($data['items'])->filter(fn ($i) => empty($i['metodo_pago_id']));
        abort_unless($lineasEfectivo->count() === 1, 422, 'Debe incluirse exactamente una línea de efectivo.');

        DB::transaction(function () use ($turno, $user, $data) {
            $declaradoEf = $turno->monto_cierre_declarado !== null ? (float) $turno->monto_cierre_declarado : null;
            $esperadoEf  = $turno->monto_cierre_esperado !== null ? (float) $turno->monto_cierre_esperado : null;

            // Declarado por método (arqueo de la cajera) y esperado por método (ventas).
            $declaradosMetodo = $turno->arqueoMetodos()->pluck('monto_declarado', 'metodo_pago_id');
            $esperadosMetodo = DB::table('venta_pagos')
                ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
                ->where('ventas.turno_id', $turno->id)
                ->where('ventas.estado', 'completada')
                ->selectRaw('venta_pagos.metodo_pago_id, SUM(venta_pagos.monto - venta_pagos.vuelto) as esperado')
                ->groupBy('venta_pagos.metodo_pago_id')
                ->pluck('esperado', 'metodo_pago_id');

            $contadoEf = (float) collect($data['items'])->first(fn ($i) => empty($i['metodo_pago_id']))['contado'];

            $consolidacion = TurnoConsolidacion::create([
                'turno_id'                => $turno->id,
                'empresa_id'              => $turno->empresa_id,
                'user_id'                 => $user->id,
                'fecha'                   => now()->toDateString(),
                'efectivo_declarado'      => $declaradoEf,
                'efectivo_esperado'       => $esperadoEf,
                'caja_chica'              => (float) $turno->monto_caja_chica,
                'efectivo_contado'        => $contadoEf,
                'diferencia_vs_declarado' => $declaradoEf !== null ? round($contadoEf - $declaradoEf, 2) : null,
                'diferencia_vs_esperado'  => $esperadoEf !== null ? round($contadoEf - $esperadoEf, 2) : null,
                'observacion'             => $data['observacion'] ?? null,
            ]);

            // El conteo del consolidador MANDA: revertir el asiento del
            // cierre si existía (config apagada antes, o cambió).
            $this->tesoreria->revertir('cierre_turno', $turno->id);

            $metodos = MetodoPago::whereIn('id', collect($data['items'])->pluck('metodo_pago_id')->filter())->get()->keyBy('id');
            $faltanteTotal = 0.0;

            foreach ($data['items'] as $linea) {
                $metodoId  = $linea['metodo_pago_id'] ?? null;
                $contado   = round((float) $linea['contado'], 2);
                $esEfectivo = $metodoId === null;

                $metodo    = $metodoId ? $metodos->get($metodoId) : null;
                $etiqueta  = $esEfectivo ? 'Efectivo' : ($metodo?->nombre ?? 'Método');
                $declarado = $esEfectivo ? $declaradoEf : ($declaradosMetodo->has($metodoId) ? (float) $declaradosMetodo[$metodoId] : null);
                $esperado  = $esEfectivo ? $esperadoEf : round((float) ($esperadosMetodo[$metodoId] ?? 0), 2);
                $dif       = $esperado !== null ? round($contado - $esperado, 2) : null;

                $cuentaId = $esEfectivo
                    ? TesoreriaService::efectivo($turno->empresa_id)->id
                    : $this->tesoreria->resolverCuenta($turno->empresa_id, null, $metodoId);

                $consolidacion->items()->create([
                    'metodo_pago_id' => $metodoId,
                    'cuenta_id'      => $cuentaId,
                    'etiqueta'       => $etiqueta,
                    'declarado'      => $declarado,
                    'esperado'       => $esperado,
                    'contado'        => $contado,
                    'diferencia'     => $dif,
                ]);

                // Asiento por línea: la diferencia va a la cuenta del método.
                if ($dif !== null && abs($dif) >= 0.01) {
                    $this->tesoreria->registrar(
                        $turno->empresa_id,
                        $cuentaId,
                        $user,
                        now()->toDateString(),
                        $dif > 0 ? 'ingreso' : 'egreso',
                        abs($dif),
                        ($dif > 0 ? 'Sobrante' : 'Faltante')
                            . " consolidado ({$etiqueta}) — turno #{$turno->id} (cajera: {$turno->user?->name})",
                        'turno_consolidacion',
                        $consolidacion->id,
                    );
                    if ($dif < 0) $faltanteTotal += abs($dif);
                }
            }

            // Faltante total → descuento de planilla opcional a la cajera.
            if (!empty($data['generar_descuento']) && $faltanteTotal >= 0.01) {
                PlanillaDescuento::create([
                    'empresa_id'     => $turno->empresa_id,
                    'user_id'        => $turno->user_id,
                    'registrado_por' => $user->id,
                    'fecha'          => now()->toDateString(),
                    'monto'          => round($faltanteTotal, 2),
                    'motivo'         => "Faltante de caja — turno #{$turno->id} del " . $turno->fecha_cierre?->format('d/m/Y'),
                    'ref_tipo'       => 'turno_consolidacion',
                    'ref_id'         => $consolidacion->id,
                    'estado'         => 'pendiente',
                ]);
            }

            AuditoriaService::log('caja.consolidada', $consolidacion, [
                'turno_id'       => $turno->id,
                'cajera'         => $turno->user?->name,
                'lineas'         => collect($data['items'])->count(),
                'faltante_total' => round($faltanteTotal, 2),
                'genero_descuento' => !empty($data['generar_descuento']) && $faltanteTotal >= 0.01,
            ], $user);
        });

        return back()->with('success', 'Turno consolidado correctamente (todas las cuentas verificadas).');
    }
}
