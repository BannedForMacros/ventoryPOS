<?php

namespace App\Http\Controllers\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Turnos\AbrirTurnoRequest;
use App\Http\Requests\Turnos\CerrarTurnoRequest;
use App\Http\Requests\Turnos\ReabrirTurnoRequest;
use App\Models\Caja;
use App\Models\CierreInventario;
use App\Models\MetodoPago;
use App\Models\Turno;
use App\Models\TurnoArqueo;
use App\Models\TurnoArqueoMetodo;
use App\Services\ConfiguracionOperacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TurnoController extends Controller
{
    public function __construct(private ConfiguracionOperacionService $config) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Turno::deEmpresa($user->empresa_id)
            ->with(['caja', 'user', 'userCierre', 'local'])
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->whereHas('caja', fn ($c) => $c->where('nombre', 'ilike', "%{$t}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$t}%"))
                    ->orWhereHas('local', fn ($l) => $l->where('nombre', 'ilike', "%{$t}%")));
            });

        if (!$user->rol->es_admin) {
            $query->where('user_id', $user->id);
        }

        $turnos = $query->orderByDesc('fecha_apertura')->paginate(20)->withQueryString();

        $empresa = \App\Models\Empresa::find($user->empresa_id);

        $cajasDisponibles = Caja::deEmpresa($user->empresa_id)
            ->activo()
            ->when($user->local_id, fn($q) => $q->where('local_id', $user->local_id))
            ->with('local')
            ->get()
            ->map(fn($c) => [
                ...$c->toArray(),
                'tiene_turno_abierto' => $c->tieneTurnoAbierto(),
                // Apertura sugerida según config de empresa (arrastre / fondo fijo)
                'apertura_sugerida'   => Turno::aperturaSugeridaParaCaja($c),
            ]);

        $metodosPago = MetodoPago::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('nombre')
            ->with('tipo:id,slug,nombre,icono')
            ->get(['id', 'nombre', 'tipo_id']);

        $turnoActivo = Turno::turnoActivoDelUsuario($user->id)
            ?->load(['caja', 'local', 'gastos.tipo', 'gastos.concepto',
                     'retiros.user:id,name',
                     'ventas' => fn($q) => $q->where('estado', 'completada')->with('pagos.metodoPago')]);

        // Configuración de fondos iniciales para los locales visibles del usuario
        $localIds = $cajasDisponibles->pluck('local_id')->unique()->values()->all();
        $configFondos = [];
        foreach ($localIds as $localId) {
            $local = \App\Models\Local::find($localId);
            if ($local) {
                $configFondos[$localId] = [
                    'usa_fondos_iniciales'           => $this->config->usaFondosIniciales($local),
                    'fondos_iniciales_en_declaracion' => $this->config->fondosInicialesEnDeclaracion($local),
                ];
            }
        }

        return Inertia::render('Turnos/Index', [
            'turnos'           => $turnos,
            'buscar'           => $request->input('buscar', ''),
            'cajasDisponibles' => $cajasDisponibles,
            'metodosPago'      => $metodosPago,
            'turnoActivo'      => $turnoActivo,
            'configFondos'     => $configFondos,
            'configEfectivo'   => [
                'modo_apertura_caja'         => $empresa?->modo_apertura_caja ?? 'libre',
                'apertura_editable'          => (bool) ($empresa?->apertura_editable ?? true),
                'usa_retiros_caja'           => (bool) ($empresa?->usa_retiros_caja ?? false),
                'retiro_requiere_aprobacion' => (bool) ($empresa?->retiro_requiere_aprobacion ?? true),
            ],
        ]);
    }

    public function show(Request $request, Turno $turno)
    {
        $user = $request->user();
        $esAdmin = $user->rol->es_admin;

        // Admin ve todos los turnos de la empresa; cajero solo los suyos
        abort_if(!$esAdmin && $turno->user_id !== $user->id, 403);
        abort_if($turno->empresa_id !== $user->empresa_id, 403);

        $turno->load([
            'caja',
            'local',
            'user',
            'userCierre',
            'arqueo',
            'arqueoMetodos.metodoPago',
            'gastos.tipo',
            'gastos.concepto',
            'gastos.user',
            'retiros.user:id,name',
            'retiros.aprobadoPor:id,name',
            'ventas' => fn($q) => $q->with(['cliente', 'pagos.metodoPago', 'items']),
        ]);

        // Ventas completadas por método de pago
        $ventasPorMetodo = [];
        $totalVentas = 0;
        foreach ($turno->ventas->where('estado', 'completada') as $venta) {
            $totalVentas += (float) $venta->total;
            foreach ($venta->pagos as $pago) {
                $nombre = $pago->metodoPago->nombre ?? 'Otro';
                $ventasPorMetodo[$nombre] = ($ventasPorMetodo[$nombre] ?? 0) + (float) $pago->monto;
            }
        }

        $totalGastos = $turno->gastos->sum(fn($g) => (float) $g->monto);

        return Inertia::render('Turnos/Show', [
            'turno'           => $turno,
            'ventasPorMetodo' => $ventasPorMetodo,
            'totalVentas'     => $totalVentas,
            'totalGastos'     => $totalGastos,
            'esAdmin'         => $esAdmin,
        ]);
    }

    public function reabrir(ReabrirTurnoRequest $request, Turno $turno)
    {
        abort_if(!$request->user()->rol->es_admin, 403);
        abort_if($turno->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($turno->estado !== 'cerrado', 422);

        $motivo = $request->validated('motivo');

        DB::transaction(function () use ($turno, $request, $motivo) {
            $snapshot = [
                'cerrado_por'        => $turno->user_cierre_id,
                'fecha_cierre'       => $turno->fecha_cierre?->toDateTimeString(),
                'monto_declarado'    => (float) $turno->monto_cierre_declarado,
                'monto_esperado'     => (float) $turno->monto_cierre_esperado,
                'diferencia'         => (float) $turno->diferencia,
            ];

            $turno->arqueo()->delete();
            $turno->arqueoMetodos()->delete();

            // F8 — Revertir el asiento de sobrante/faltante del cierre y la
            // consolidación (si existía): el turno vuelve a estar abierto y
            // se volverán a generar en el próximo cierre.
            $tesoreria = app(\App\Services\TesoreriaService::class);
            $tesoreria->revertir('cierre_turno', $turno->id);
            if ($turno->consolidacion) {
                $tesoreria->revertir('turno_consolidacion', $turno->consolidacion->id);
                $turno->consolidacion->delete();
            }

            // A8 — Anular el cierre de inventario asociado al turno (si existe).
            // Si no se anula, el cierre confirmado anterior queda "huerfano"
            // y los reportes posteriores cuentan el inventario dos veces cuando
            // el siguiente cierre se confirme. Guardamos los IDs en el snapshot
            // para que la auditoria muestre exactamente cuales quedaron sin validez.
            $cierresAnulados = CierreInventario::where('turno_id', $turno->id)
                ->where('estado', 'confirmado')
                ->get();

            foreach ($cierresAnulados as $c) {
                $observacionPrevia = $c->observacion ? "{$c->observacion}\n" : '';
                $c->update([
                    'estado'      => 'anulado',
                    'observacion' => $observacionPrevia
                        . "[Anulado por reapertura de turno el "
                        . now()->toDateTimeString()
                        . " — motivo: {$motivo}]",
                ]);
            }

            $turno->update([
                'estado'                 => 'abierto',
                'fecha_cierre'           => null,
                'user_cierre_id'         => null,
                'monto_cierre_declarado' => null,
                'monto_cierre_esperado'  => null,
                'diferencia'             => null,
                'observacion_cierre'     => null,
            ]);

            \App\Services\AuditoriaService::log('turno.reabierto', $turno, [
                'cierre_anterior'        => $snapshot,
                'turno_de'               => $turno->user_id,
                'motivo'                 => $motivo,
                'cierres_inventario_anulados' => $cierresAnulados->pluck('id')->all(),
            ], $request->user());
        });

        return redirect()->route('turnos.show', $turno->id)
            ->with('success', 'Turno reabierto. Ya puede registrar ventas y gastos.');
    }

    public function turnoActivo(Request $request)
    {
        $turno = Turno::turnoActivoDelUsuario($request->user()->id)
            ?->load(['caja', 'gastos.tipo', 'gastos.concepto']);

        return response()->json($turno);
    }

    public function abrir(AbrirTurnoRequest $request)
    {
        $user = $request->user();
        $caja = Caja::findOrFail($request->input('caja_id'));
        $local = $caja->local()->firstOrFail();

        // Solo se pide caja chica si la empresa/local lo permite Y la caja la tiene activa
        $usaFondos = $this->config->usaFondosIniciales($local);
        $montoCajaChica = ($usaFondos && $caja->caja_chica_activa)
            ? (float) $request->input('monto_caja_chica', 0)
            : 0;

        // Apertura sugerida (arrastre / fondo fijo). El servidor manda: si la
        // empresa bloqueó la edición, se usa el sugerido aunque el cliente
        // envíe otro monto; si la edición está permitida y difiere, se audita.
        $empresa        = \App\Models\Empresa::find($user->empresa_id);
        $sugerida       = Turno::aperturaSugeridaParaCaja($caja);
        $montoApertura  = (float) $request->input('monto_apertura');
        $fueAjustada    = false;
        if ($sugerida !== null) {
            if (!($empresa?->apertura_editable ?? true)) {
                $montoApertura = (float) $sugerida['monto'];
            } elseif (abs($montoApertura - (float) $sugerida['monto']) >= 0.01) {
                $fueAjustada = true;
            }
        }

        $turno = Turno::create([
            'empresa_id'           => $user->empresa_id,
            'local_id'             => $caja->local_id,
            'caja_id'              => $caja->id,
            'user_id'              => $user->id,
            'monto_apertura'       => $montoApertura,
            'monto_caja_chica'     => $montoCajaChica,
            'estado'               => 'abierto',
            'fecha_apertura'       => now(),
            'observacion_apertura' => $request->input('observacion_apertura'),
        ]);

        if ($fueAjustada) {
            \App\Services\AuditoriaService::log('turno.apertura_ajustada', $turno, [
                'caja'      => $caja->nombre,
                'sugerido'  => (float) $sugerida['monto'],
                'ingresado' => $montoApertura,
                'origen'    => $sugerida['origen'],
            ], $user);
        }

        return redirect()->back()->with('success', 'Turno abierto correctamente.');
    }

    public function cerrarPage(Request $request, Turno $turno)
    {
        $user = $request->user();
        // La cajera cierra SU turno; el admin puede cerrar cualquier turno
        // abierto de la empresa (p. ej. uno reabierto para regularizar ventas).
        abort_if($turno->user_id !== $user->id
            && !($user->rol->es_admin && $turno->empresa_id === $user->empresa_id), 403);
        abort_if($turno->estado !== 'abierto', 422);

        $turno->load(['caja', 'gastos.tipo', 'gastos.concepto',
                       'ventas' => fn($q) => $q->where('estado', 'completada')->with('pagos.metodoPago')]);

        // Resumen ventas por método de pago
        $ventasPorMetodo = [];
        $totalVentas = 0;
        foreach ($turno->ventas as $venta) {
            $totalVentas += (float) $venta->total;
            foreach ($venta->pagos as $pago) {
                $nombre = $pago->metodoPago->nombre ?? 'Otro';
                $ventasPorMetodo[$nombre] = ($ventasPorMetodo[$nombre] ?? 0) + (float) $pago->monto;
            }
        }

        $totalGastos = $turno->gastos->sum(fn($g) => (float) $g->monto);
        $montoEsperado = $turno->calcularMontoEsperado();

        $metodosPago = MetodoPago::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('nombre')
            ->with('tipo:id,slug,nombre,icono')
            ->get(['id', 'nombre', 'tipo_id']);

        $modoCaja      = $this->config->modoCierreCaja($turno->local);
        $modoInventario = $this->config->modoCierreInventario($turno->local);
        $usaFondos     = $this->config->usaFondosIniciales($turno->local);
        $fondosEnDecl  = $this->config->fondosInicialesEnDeclaracion($turno->local);

        // Cierre de inventario asociado a este turno (si existe)
        $cierreInventarioTurno = CierreInventario::where('turno_id', $turno->id)
            ->orderByDesc('id')
            ->first();

        $empresa = $turno->empresa;

        return Inertia::render('Turnos/Cerrar', [
            'turno'                        => $turno,
            // Pregunta de destino del efectivo al cierre (config de empresa)
            'preguntaDestino'              => (bool) ($empresa?->cierre_pregunta_destino ?? false),
            'totalRetiros'                 => (float) $turno->retiros()->where('momento', 'turno')->sum('monto'),
            // Aviso: productos vendidos en el turno cuyo stock quedó negativo.
            // El frontend pide confirmación explícita antes de cerrar.
            'productosStockNegativo'       => $turno->productosVendidosConStockNegativo(),
            'ventasPorMetodo'              => $ventasPorMetodo,
            'totalVentas'                  => $totalVentas,
            'totalGastos'                  => $totalGastos,
            'montoEsperado'                => $montoEsperado,
            'metodosPago'                  => $metodosPago,
            'modoCierreCaja'               => $modoCaja,
            'modoCierreInventario'         => $modoInventario,
            'cierreInventarioTurno'        => $cierreInventarioTurno,
            'usaFondosIniciales'           => $usaFondos,
            'fondosInicialesEnDeclaracion' => $fondosEnDecl,
        ]);
    }

    public function cerrar(CerrarTurnoRequest $request, Turno $turno)
    {
        $user = $request->user();
        // Mismo criterio que cerrarPage: dueña del turno, o admin de la empresa.
        abort_if($turno->user_id !== $user->id
            && !($user->rol->es_admin && $turno->empresa_id === $user->empresa_id), 403);
        abort_if($turno->estado !== 'abierto', 422);

        $turno->loadMissing('local');
        $modoCaja       = $this->config->modoCierreCaja($turno->local);
        $modoInventario = $this->config->modoCierreInventario($turno->local);

        // Inventario declarado: requiere cierre de inventario CONFIRMADO atado al turno
        if ($modoInventario === 'declarado') {
            $cierreOk = CierreInventario::where('turno_id', $turno->id)
                ->where('estado', 'confirmado')
                ->exists();

            if (!$cierreOk) {
                return back()->withErrors([
                    'cierre_inventario' => 'Este local exige cierre de inventario declarado al cerrar caja. Crea y confirma un cierre de inventario asociado al turno antes de cerrar.',
                ]);
            }
        }

        // Validación de stock negativo: si en el turno se vendieron productos
        // que quedaron con stock negativo, el cierre exige confirmación
        // explícita (el frontend muestra la lista y pregunta antes de enviar
        // confirma_stock_negativo=true). Se recalcula aquí en el servidor para
        // que no se pueda saltar el aviso.
        $productosNegativos = $turno->productosVendidosConStockNegativo();
        if (!empty($productosNegativos) && !$request->boolean('confirma_stock_negativo')) {
            $nombres = collect($productosNegativos)->pluck('producto_nombre')->take(5)->implode(', ');
            $extra   = count($productosNegativos) > 5 ? '…' : '';
            return back()->withErrors([
                'stock_negativo' => "Has vendido productos que quedaron con stock negativo: {$nombres}{$extra}. Confirma que deseas cerrar la caja de todas formas.",
            ]);
        }

        DB::transaction(function () use ($request, $turno, $modoCaja) {
            $turno->arqueo()->delete();
            $turno->arqueoMetodos()->delete();

            if ($modoCaja !== 'rapido') {
                foreach ($request->input('arqueo', []) as $fila) {
                    TurnoArqueo::create([
                        'turno_id'     => $turno->id,
                        'denominacion' => $fila['denominacion'],
                        'cantidad'     => $fila['cantidad'],
                    ]);
                }

                foreach ($request->input('arqueo_metodos', []) as $fila) {
                    TurnoArqueoMetodo::create([
                        'turno_id'        => $turno->id,
                        'metodo_pago_id'  => $fila['metodo_pago_id'],
                        'monto_declarado' => $fila['monto_declarado'],
                    ]);
                }
            }

            $turno->refresh();

            $montoDeclarado = $modoCaja === 'rapido' ? null : $turno->calcularTotalArqueo();
            $montoEsperado  = $turno->calcularMontoEsperado();
            $diferencia     = ($modoCaja === 'rapido' || $montoDeclarado === null)
                ? null
                : $montoDeclarado - $montoEsperado;

            $turno->update([
                'user_cierre_id'         => $request->user()->id,
                'monto_cierre_declarado' => $montoDeclarado,
                'monto_cierre_esperado'  => $montoEsperado,
                'diferencia'             => $diferencia,
                'estado'                 => 'cerrado',
                'fecha_cierre'           => now(),
                'observacion_cierre'     => $request->input('observacion_cierre'),
            ]);

            // Destino del efectivo final (config "cierre_pregunta_destino"):
            // ¿queda en el cajón para el siguiente turno o se entrega a
            // administración? La entrega genera un retiro momento='cierre'
            // (traslado de custodia, no toca tesorería: el neto no cambia).
            $empresaTurno = $turno->empresa;
            if (($empresaTurno?->cierre_pregunta_destino ?? false) && $request->filled('destino_efectivo')) {
                $efectivoFinal = (float) ($montoDeclarado ?? $montoEsperado);
                $destino = $request->input('destino_efectivo');
                $queda = match ($destino) {
                    'caja'           => $efectivoFinal,
                    'administracion' => 0.0,
                    'parcial'        => min((float) $request->input('efectivo_queda', 0), $efectivoFinal),
                    default          => $efectivoFinal,
                };
                $queda   = max(0.0, round($queda, 2));
                $entrega = round($efectivoFinal - $queda, 2);

                $turno->update([
                    'efectivo_arrastre' => $queda,
                    'destino_efectivo'  => $destino,
                ]);

                if ($entrega >= 0.01) {
                    \App\Models\TurnoRetiro::create([
                        'empresa_id'  => $turno->empresa_id,
                        'turno_id'    => $turno->id,
                        'user_id'     => $request->user()->id,
                        'concepto'    => \App\Models\TurnoRetiro::CONCEPTO_ENTREGA_ADMIN,
                        'monto'       => $entrega,
                        'momento'     => 'cierre',
                        'estado'      => 'aprobado',
                        'observacion' => 'Entrega del efectivo final al cerrar el turno',
                    ]);
                }
            }

            // Snapshot de productos vendidos en el turno (para reportes históricos)
            $turno->poblarSnapshotProductos();

            // F8 — Si la empresa NO exige consolidación, el conteo de la
            // cajera es el que manda: el sobrante/faltante del turno
            // (declarado − esperado) se asienta en tesorería con origen
            // trazable. Si la empresa SÍ exige consolidación, el asiento se
            // hará cuando el consolidador registre SU conteo.
            $empresa = $turno->empresa;
            if (!$empresa?->requiere_consolidacion_caja
                && $turno->diferencia !== null
                && abs((float) $turno->diferencia) >= 0.01) {
                $dif = (float) $turno->diferencia;
                app(\App\Services\TesoreriaService::class)->registrar(
                    $turno->empresa_id,
                    null, // efectivo
                    $request->user(),
                    now()->toDateString(),
                    $dif > 0 ? 'ingreso' : 'egreso',
                    abs($dif),
                    ($dif > 0 ? 'Sobrante' : 'Faltante') . " de caja — cierre turno #{$turno->id} ({$turno->user?->name})",
                    'cierre_turno',
                    $turno->id,
                );
            }
        });

        $turno->refresh();
        \App\Services\AuditoriaService::log('turno.cerrado', $turno, array_filter([
            'declarado'  => (float) $turno->monto_cierre_declarado,
            'esperado'   => (float) $turno->monto_cierre_esperado,
            'diferencia' => (float) $turno->diferencia,
            'modo_caja'  => $modoCaja,
            'destino_efectivo'  => $turno->destino_efectivo,
            'efectivo_arrastre' => $turno->efectivo_arrastre !== null ? (float) $turno->efectivo_arrastre : null,
        ], fn ($v) => $v !== null), $request->user());

        return redirect()->route('turnos.index')->with('success', 'Turno cerrado correctamente.');
    }
}
