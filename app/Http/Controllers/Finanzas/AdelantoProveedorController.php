<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\Proveedor;
use App\Models\ProveedorAdelanto;
use App\Models\Turno;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use App\Support\AfectaCaja;
use App\Support\ExigeCuentaDePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Adelantos a proveedores: dinero entregado antes de recibir el material
 * ("ADELANTO DE PROVEEDORES" del balance, activo).
 *
 * El consumo del adelanto contra una entrada se hace desde Cuentas por
 * Pagar (abonar con proveedor_adelanto_id); aquí se gestiona el alta,
 * la devolución y la anulación.
 */
class AdelantoProveedorController extends Controller
{
    use ExigeCuentaDePago;

    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ProveedorAdelanto::deEmpresa($user->empresa_id)
            ->with(['proveedor', 'metodoPago', 'cuenta', 'turno:id,caja_id', 'turno.caja:id,nombre', 'aplicaciones.entrada', 'aplicaciones.user'])
            ->when($request->input('proveedor_id'), fn ($q, $v) => $q->where('proveedor_id', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('referencia', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('proveedor', fn ($p) => $p
                        ->where('razon_social', 'ilike', "%{$t}%")
                        ->orWhere('nombre_comercial', 'ilike', "%{$t}%")));
            });

        // Filtro de estado: 'activos' (default), un estado puntual
        // (aplicado/anulado/devuelto) o 'todos'.
        $estado = $request->input('estado', 'activos');
        if ($estado === 'activos') {
            $query->activo();
        } elseif (in_array($estado, ['aplicado', 'anulado', 'devuelto'], true)) {
            $query->where('estado', $estado);
        }

        $adelantos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        $totalActivo = (float) ProveedorAdelanto::deEmpresa($user->empresa_id)->activo()->sum('saldo');

        // KPIs de cabecera (sobre adelantos ACTIVOS, independiente del filtro)
        $baseActivo = ProveedorAdelanto::deEmpresa($user->empresa_id)->activo();
        $kpis = [
            'activos'     => (int)   (clone $baseActivo)->count(),
            'proveedores' => (int)   (clone $baseActivo)->distinct()->count('proveedor_id'),
            'aplicado'    => round((float) ProveedorAdelanto::deEmpresa($user->empresa_id)
                ->where('estado', 'aplicado')->sum('monto'), 2),
        ];

        return Inertia::render('Finanzas/Adelantos', [
            'adelantos'   => $adelantos,
            'totalActivo' => round($totalActivo, 2),
            'kpis'        => $kpis,
            // Acciones visibles según la matriz de permisos del rol.
            'puede'       => [
                'editar'   => $user->tienePermiso('finanzas.adelantos', 'editar'),
                'eliminar' => $user->tienePermiso('finanzas.adelantos', 'eliminar'),
            ],
            'estado'      => $request->input('estado', 'activos'),
            'buscar'      => $request->input('buscar', ''),
            'proveedores' => Proveedor::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])->orderBy('nombre')->get()->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug, 'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
            // "Afecta caja a:" — turnos ABIERTOS para el selector unificado.
            'turnos'      => Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where('estado', 'abierto')
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
        ]);
    }

    /**
     * Exporta TODOS los adelantos a proveedores filtrados a CSV (Excel),
     * respetando proveedor, estado y búsqueda. Las columnas coinciden con las
     * visibles en la tabla.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        $query = ProveedorAdelanto::deEmpresa($user->empresa_id)
            ->with(['proveedor', 'metodoPago', 'cuenta'])
            ->when($request->input('proveedor_id'), fn ($q, $v) => $q->where('proveedor_id', $v))
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('referencia', 'ilike', "%{$t}%")
                    ->orWhere('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('proveedor', fn ($p) => $p
                        ->where('razon_social', 'ilike', "%{$t}%")
                        ->orWhere('nombre_comercial', 'ilike', "%{$t}%")));
            });

        $estado = $request->input('estado', 'activos');
        if ($estado === 'activos') {
            $query->activo();
        } elseif (in_array($estado, ['aplicado', 'anulado', 'devuelto'], true)) {
            $query->where('estado', $estado);
        }

        $adelantos = $query->orderByDesc('fecha')->orderByDesc('id')->get();

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8
        $headers = ['Fecha', 'Proveedor', 'Entregado', 'Saldo a favor', 'Estado', 'Referencia'];
        $csv .= implode(',', array_map($this->escaparCsv(...), $headers)) . "\n";

        foreach ($adelantos as $a) {
            $proveedor = $a->proveedor;
            $nombreProveedor = $proveedor?->razon_social ?? $proveedor?->nombre_comercial ?? '—';

            $estadoLabel = match ($a->estado) {
                'activo' => 'Activo',
                'aplicado' => 'Aplicado',
                'devuelto' => 'Devuelto',
                default => 'Anulado',
            };

            $row = [
                $a->fecha->format('d/m/Y'),
                $nombreProveedor,
                'S/ ' . number_format((float) $a->monto, 2, '.', ''),
                'S/ ' . number_format((float) $a->saldo, 2, '.', ''),
                $estadoLabel,
                $a->referencia ?? '—',
            ];

            $csv .= implode(',', array_map($this->escaparCsv(...), $row)) . "\n";
        }

        $filename = 'adelantos_' . now()->format('Ymd_His') . '.csv';

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

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'proveedor_id'   => ['required', 'integer', Rule::exists('proveedores', 'id')->where('empresa_id', $user->empresa_id)->where('activo', true)],
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
            // "Afecta caja a:" — turno de cuya caja sale el efectivo.
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        // Gate por config de empresa (módulo 'adelantos', modo libre: respeta lo elegido).
        $data['turno_id'] = AfectaCaja::resolverTurno($user, 'adelantos', $data['turno_id'] ?? null, 'libre');

        $adelanto = DB::transaction(function () use ($data, $user) {
            $adelanto = ProveedorAdelanto::create($data + [
                'empresa_id' => $user->empresa_id,
                'user_id'    => $user->id,
                'saldo'      => $data['monto'],
                'estado'     => 'activo',
            ]);

            // F7 — El adelanto sale de tesorería al momento de entregarlo.
            $adelanto->load('proveedor');
            $prov = $adelanto->proveedor?->razon_social ?? $adelanto->proveedor?->nombre_comercial ?? 'proveedor';
            $this->tesoreria->registrar(
                $user->empresa_id,
                $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $data['metodo_pago_id'] ?? null),
                $user,
                $data['fecha'],
                'egreso',
                (float) $data['monto'],
                "Adelanto a proveedor — {$prov}",
                'proveedor_adelanto',
                $adelanto->id,
            );

            return $adelanto;
        });

        AuditoriaService::log('adelanto_proveedor.creado', $adelanto, [
            'proveedor_id' => $adelanto->proveedor_id,
            'monto'        => (float) $adelanto->monto,
        ], $user);

        return back()->with('success', 'Adelanto registrado correctamente.');
    }

    /**
     * El proveedor devolvió el dinero, o el registro fue un error.
     */
    public function anular(Request $request, ProveedorAdelanto $adelanto)
    {
        $user = $request->user();
        abort_if($adelanto->empresa_id !== $user->empresa_id, 403);
        abort_unless($adelanto->estado === 'activo', 422, 'El adelanto no está activo.');

        $data = $request->validate([
            'accion' => ['required', Rule::in(['devuelto', 'anulado'])],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        // Anular (registro erróneo) exige que NO se haya consumido nada: si ya
        // se aplicó a compras, revertir el egreso completo descuadraría todo.
        if ($data['accion'] === 'anulado' && $adelanto->aplicaciones()->exists()) {
            return back()->withErrors([
                'accion' => 'Este adelanto ya se consumió parcialmente contra compras: no se puede anular como registro erróneo. Puedes marcar como devuelto el saldo restante.',
            ]);
        }

        DB::transaction(function () use ($adelanto, $user, $data) {
            $adelanto->update(['estado' => $data['accion']]);

            // F7 — Tesorería: devuelto = el dinero vuelve (ingreso por el
            // saldo); anulado = registro erróneo, se revierte el egreso.
            if ($data['accion'] === 'devuelto') {
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $adelanto->cuenta_id,
                    $user,
                    now()->toDateString(),
                    'ingreso',
                    (float) $adelanto->saldo,
                    "Devolución de adelanto #{$adelanto->id}: {$data['motivo']}",
                    'proveedor_adelanto_devolucion',
                    $adelanto->id,
                );
            } else {
                $this->tesoreria->revertir('proveedor_adelanto', $adelanto->id);
            }
        });

        AuditoriaService::log('adelanto_proveedor.' . $data['accion'], $adelanto, [
            'motivo' => $data['motivo'],
            'saldo'  => (float) $adelanto->saldo,
        ], $user);

        return back()->with('success', $data['accion'] === 'devuelto' ? 'Adelanto marcado como devuelto.' : 'Adelanto anulado.');
    }

    /**
     * Edita un adelanto ACTIVO. El monto solo puede cambiar si aún no se
     * consumió nada (sin aplicaciones): en ese caso se rehace el egreso en
     * tesorería. Fecha/referencia/observación se editan siempre.
     */
    public function update(Request $request, ProveedorAdelanto $adelanto)
    {
        $user = $request->user();
        abort_if($adelanto->empresa_id !== $user->empresa_id, 403);
        abort_unless($adelanto->estado === 'activo', 422, 'El adelanto no está activo (reactívalo primero).');

        $tieneConsumos = $adelanto->aplicaciones()->exists();

        $data = $request->validate([
            'monto'          => [$tieneConsumos ? 'prohibited' : 'required', 'numeric', 'min:0.01'],
            'fecha'          => ['required', 'date'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
            'turno_id'       => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ], [
            'monto.prohibited' => 'Este adelanto ya tiene consumos: su monto no se edita. Solo fecha, referencia y observación.',
        ]);

        $antes = ['monto' => (float) $adelanto->monto, 'fecha' => $adelanto->fecha->toDateString()];

        DB::transaction(function () use ($adelanto, $user, $data, $tieneConsumos, $request) {
            $montoNuevo = $tieneConsumos ? (float) $adelanto->monto : (float) $data['monto'];

            $adelanto->update([
                'monto'          => $montoNuevo,
                'saldo'          => $tieneConsumos ? $adelanto->saldo : $montoNuevo,
                'fecha'          => $data['fecha'],
                'metodo_pago_id' => $data['metodo_pago_id'] ?? $adelanto->metodo_pago_id,
                'cuenta_id'      => $data['cuenta_id'] ?? $adelanto->cuenta_id,
                'referencia'     => $data['referencia'] ?? null,
                'observacion'    => $data['observacion'] ?? null,
                // Solo tocar turno_id si el request lo envió (para no borrarlo sin querer).
                'turno_id'       => $request->has('turno_id')
                    ? AfectaCaja::resolverTurno($user, 'adelantos', $data['turno_id'] ?? null, 'libre')
                    : $adelanto->turno_id,
            ]);

            // Rehacer el egreso original con los datos nuevos.
            $this->tesoreria->revertir('proveedor_adelanto', $adelanto->id);
            $adelanto->load('proveedor');
            $prov = $adelanto->proveedor?->razon_social ?? $adelanto->proveedor?->nombre_comercial ?? 'proveedor';
            $this->tesoreria->registrar(
                $user->empresa_id,
                $adelanto->cuenta_id ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $adelanto->metodo_pago_id),
                $user,
                $data['fecha'],
                'egreso',
                $montoNuevo,
                "Adelanto a proveedor — {$prov} [editado]",
                'proveedor_adelanto',
                $adelanto->id,
            );
        });

        AuditoriaService::log('adelanto_proveedor.editado', $adelanto, [
            'antes'   => $antes,
            'despues' => ['monto' => (float) $adelanto->monto, 'fecha' => $data['fecha']],
        ], $user);

        return back()->with('success', 'Adelanto actualizado: el egreso en tesorería se rehízo con los datos nuevos.');
    }

    /**
     * Reactiva un adelanto cerrado por error:
     *  - anulado  → vuelve a activo re-asentando el egreso original.
     *  - devuelto → vuelve a activo revirtiendo el ingreso de la devolución.
     */
    public function reactivar(Request $request, ProveedorAdelanto $adelanto)
    {
        $user = $request->user();
        abort_if($adelanto->empresa_id !== $user->empresa_id, 403);
        abort_unless(in_array($adelanto->estado, ['anulado', 'devuelto'], true), 422, 'Solo se reactivan adelantos anulados o devueltos.');

        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);

        $estadoPrevio = $adelanto->estado;

        DB::transaction(function () use ($adelanto, $user, $estadoPrevio) {
            if ($estadoPrevio === 'anulado') {
                // El egreso original fue revertido al anular: re-asentarlo.
                $adelanto->load('proveedor');
                $prov = $adelanto->proveedor?->razon_social ?? $adelanto->proveedor?->nombre_comercial ?? 'proveedor';
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $adelanto->cuenta_id ?? $this->tesoreria->resolverCuenta($user->empresa_id, null, $adelanto->metodo_pago_id),
                    $user,
                    $adelanto->fecha->toDateString(),
                    'egreso',
                    (float) $adelanto->monto,
                    "Adelanto a proveedor — {$prov} [reactivado]",
                    'proveedor_adelanto',
                    $adelanto->id,
                );
            } else {
                // La devolución generó un ingreso: revertirlo.
                $this->tesoreria->revertir('proveedor_adelanto_devolucion', $adelanto->id);
            }

            $adelanto->update(['estado' => 'activo']);
        });

        AuditoriaService::log('adelanto_proveedor.reactivado', $adelanto, [
            'estado_previo' => $estadoPrevio,
            'motivo'        => $data['motivo'],
            'saldo'         => (float) $adelanto->saldo,
        ], $user);

        return back()->with('success', 'Adelanto reactivado: tesorería quedó consistente.');
    }
}
