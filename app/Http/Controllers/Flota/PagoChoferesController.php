<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gastos\GastoController;
use App\Models\FlotaLiquidacion;
use App\Models\FlotaTarifa;
use App\Models\Producto;
use App\Services\AuditoriaService;
use App\Services\PagoChoferesService;
use App\Support\EnEmpresa;
use App\Support\Flota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Flota → Pago a choferes: lo que corresponde pagar a cada chofer y ayudante
 * por sus viajes, sus tarifas y las liquidaciones pagadas. Solo lo ve quien
 * tenga el permiso (de entrada, el administrador).
 */
class PagoChoferesController extends Controller
{
    public function __construct(private PagoChoferesService $pagos) {}

    public function index(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);
        [$desde, $hasta, $clave] = $this->semana($request);

        $liquidaciones = FlotaLiquidacion::where('empresa_id', $user->empresa_id)
            ->where('desde', '<=', $hasta)->where('hasta', '>=', $desde)
            ->with('conductor:id,nombres,apellidos')->orderByDesc('id')->get()
            ->map(fn ($l) => [
                'id' => $l->id, 'numero' => $l->numero, 'persona' => $l->conductor?->nombre_completo,
                'desde' => $l->desde->toDateString(), 'hasta' => $l->hasta->toDateString(),
                'viajes' => $l->viajes, 'vueltas' => $l->vueltas, 'unidades' => (float) $l->unidades,
                'monto_calculado' => (float) $l->monto_calculado, 'ajuste' => (float) $l->ajuste,
                'ajuste_motivo' => $l->ajuste_motivo, 'total' => (float) $l->total, 'estado' => $l->estado,
                'fecha_pago' => $l->fecha_pago->toDateString(), 'detalle' => $l->detalle ?? [],
                'motivo_anulacion' => $l->motivo_anulacion,
            ]);

        $tarifas = FlotaTarifa::where('empresa_id', $user->empresa_id)->with('producto:id,nombre')->get();

        return Inertia::render('Flota/Pagos', [
            'periodo'       => ['clave' => $clave, 'desde' => $desde, 'hasta' => $hasta],
            'porPagar'      => $this->pagos->calcular($user->empresa_id, $desde, $hasta),
            'liquidaciones' => $liquidaciones,
            'tarifas'       => [
                'general'   => ($g = $tarifas->firstWhere('producto_id', null)) ? [
                    'por_cantidad' => (float) $g->por_cantidad, 'monto_chofer' => (float) $g->monto_chofer, 'monto_ayudante' => (float) $g->monto_ayudante,
                ] : null,
                'productos' => $tarifas->whereNotNull('producto_id')->values()->map(fn ($t) => [
                    'producto_id' => $t->producto_id, 'producto' => $t->producto?->nombre,
                    'por_cantidad' => (float) $t->por_cantidad, 'monto_chofer' => (float) $t->monto_chofer, 'monto_ayudante' => (float) $t->monto_ayudante,
                ]),
                ...PagoChoferesService::config($user->empresa),
            ],
            'productos'     => Producto::where('empresa_id', $user->empresa_id)->where('activo', true)->where('tipo', 'producto')
                ->orderBy('nombre')->get(['id', 'nombre'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->nombre]),
            'gasto'         => app(GastoController::class)->datosFormulario($user),
            'puede'         => ['editar' => $user->tienePermiso('flota.pagos', 'editar'), 'crear' => $user->tienePermiso('flota.pagos', 'crear')],
        ]);
    }

    public function tarifas(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);

        $data = $request->validate([
            'general'                     => ['nullable', 'array'],
            'general.por_cantidad'        => ['required_with:general', 'numeric', 'min:1'],
            'general.monto_chofer'        => ['required_with:general', 'numeric', 'min:0'],
            'general.monto_ayudante'      => ['required_with:general', 'numeric', 'min:0'],
            'productos'                   => ['array'],
            'productos.*.producto_id'     => ['required', 'integer', 'distinct', EnEmpresa::existe('productos')],
            'productos.*.por_cantidad'    => ['required', 'numeric', 'min:1'],
            'productos.*.monto_chofer'    => ['required', 'numeric', 'min:0'],
            'productos.*.monto_ayudante'  => ['required', 'numeric', 'min:0'],
            'vuelta_chofer'               => ['nullable', 'numeric', 'min:0'],
            'vuelta_ayudante'             => ['nullable', 'numeric', 'min:0'],
        ], [
            'productos.*.producto_id.distinct' => 'Ese producto ya tiene su tarifa.',
            'productos.*.por_cantidad.min'     => 'La cantidad debe ser al menos 1.',
        ]);

        DB::transaction(function () use ($user, $data) {
            FlotaTarifa::where('empresa_id', $user->empresa_id)->delete();
            $filas = [];
            if (!empty($data['general'])) $filas[] = ['producto_id' => null] + $data['general'];
            foreach ($data['productos'] ?? [] as $p) $filas[] = $p;
            foreach ($filas as $f) {
                FlotaTarifa::create([
                    'empresa_id'     => $user->empresa_id,
                    'producto_id'    => $f['producto_id'],
                    'por_cantidad'   => round((float) $f['por_cantidad'], 2),
                    'monto_chofer'   => round((float) $f['monto_chofer'], 2),
                    'monto_ayudante' => round((float) $f['monto_ayudante'], 2),
                ]);
            }
            $cfg = is_array($user->empresa->flota_config) ? $user->empresa->flota_config : [];
            $user->empresa->update(['flota_config' => array_merge($cfg, [
                'vuelta_chofer'   => round((float) ($data['vuelta_chofer'] ?? 0), 2),
                'vuelta_ayudante' => round((float) ($data['vuelta_ayudante'] ?? 0), 2),
            ])]);
        });
        AuditoriaService::log('flota.tarifas_actualizadas', $user->empresa, ['productos' => count($data['productos'] ?? [])], $user);

        return back()->with('success', 'Tarifas guardadas.');
    }

    public function pagar(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);

        $data = $request->validate([
            'conductor_id'          => ['required', 'integer', EnEmpresa::existe('conductores')],
            'desde'                 => ['required', 'date'],
            'hasta'                 => ['required', 'date', 'after_or_equal:desde'],
            'ajuste'                => ['nullable', 'numeric', 'min:-999999', 'max:999999'],
            'ajuste_motivo'         => ['nullable', 'string', 'max:255'],
            'fecha'                 => ['required', 'date', new \App\Rules\NoFutura],
            'gasto_tipo_id'         => ['required', 'integer', EnEmpresa::existe('gasto_tipos')],
            'gasto_concepto_id'     => ['required', 'integer', EnEmpresa::existe('gasto_conceptos')],
            'metodo_pago_id'        => ['required', 'integer', EnEmpresa::existe('metodos_pago')],
            'cuenta_metodo_pago_id' => ['nullable', 'integer', EnEmpresa::cuentaDePago(),
                function ($attr, $value, $fail) use ($request) {
                    if (!$value && \App\Support\PagoCuenta::requiere($request->integer('metodo_pago_id') ?: null)) {
                        $fail('Elige la cuenta de este método de pago.');
                    }
                }],
            'turno_id'              => ['nullable', 'integer', EnEmpresa::existe('turnos')],
            'local_id'              => ['nullable', 'integer', EnEmpresa::existe('locales')],
        ], [
            'gasto_tipo_id.required'     => 'Elige el tipo de gasto del pago.',
            'gasto_concepto_id.required' => 'Elige el concepto de gasto del pago.',
            'metodo_pago_id.required'    => 'Elige con qué se paga.',
        ]);

        $liq = $this->pagos->pagar($user, (int) $data['conductor_id'], $data['desde'], $data['hasta'], $data);

        return back()->with('success', "Liquidación {$liq->numero} pagada: S/ " . number_format((float) $liq->total, 2) . '.');
    }

    public function anular(Request $request, FlotaLiquidacion $liquidacion)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($liquidacion->empresa_id !== $user->empresa_id, 404);

        $data = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Indica por qué se anula.']);
        $this->pagos->anular($liquidacion, $data['motivo'], $user);

        return back()->with('success', "Liquidación {$liquidacion->numero} anulada: sus gastos se eliminaron y los viajes vuelven a estar por pagar.");
    }

    /** Lunes a domingo; por defecto la semana pasada (la que se paga). @return array{0:string,1:string,2:string} */
    private function semana(Request $request): array
    {
        $hoy = Carbon::today();
        $clave = $request->input('periodo', 'semana_pasada');

        [$d, $h] = match ($clave) {
            'semana' => [$hoy->copy()->startOfWeek(), $hoy->copy()->endOfWeek()],
            'rango'  => [Carbon::parse($request->input('desde', $hoy)), Carbon::parse($request->input('hasta', $hoy))],
            default  => [$hoy->copy()->startOfWeek()->subWeek(), $hoy->copy()->startOfWeek()->subDay()],
        };
        if ($d->gt($h)) [$d, $h] = [$h, $d];

        return [$d->toDateString(), $h->toDateString(), in_array($clave, ['semana', 'rango'], true) ? $clave : 'semana_pasada'];
    }
}
