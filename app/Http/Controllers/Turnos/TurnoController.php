<?php

namespace App\Http\Controllers\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Turnos\AbrirTurnoRequest;
use App\Http\Requests\Turnos\CerrarTurnoRequest;
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
            ->with(['caja', 'user', 'userCierre', 'local']);

        if (!$user->rol->es_admin) {
            $query->where('user_id', $user->id);
        }

        $turnos = $query->orderByDesc('fecha_apertura')->paginate(20);

        $cajasDisponibles = Caja::deEmpresa($user->empresa_id)
            ->activo()
            ->when($user->local_id, fn($q) => $q->where('local_id', $user->local_id))
            ->with('local')
            ->get()
            ->map(fn($c) => [
                ...$c->toArray(),
                'tiene_turno_abierto' => $c->tieneTurnoAbierto(),
            ]);

        $metodosPago = MetodoPago::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $turnoActivo = Turno::turnoActivoDelUsuario($user->id)
            ?->load(['caja', 'local', 'gastos.tipo', 'gastos.concepto',
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
            'cajasDisponibles' => $cajasDisponibles,
            'metodosPago'      => $metodosPago,
            'turnoActivo'      => $turnoActivo,
            'configFondos'     => $configFondos,
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

    public function reabrir(Request $request, Turno $turno)
    {
        abort_if(!$request->user()->rol->es_admin, 403);
        abort_if($turno->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($turno->estado !== 'cerrado', 422);

        DB::transaction(function () use ($turno) {
            $turno->arqueo()->delete();
            $turno->arqueoMetodos()->delete();

            $turno->update([
                'estado'                 => 'abierto',
                'fecha_cierre'           => null,
                'user_cierre_id'         => null,
                'monto_cierre_declarado' => null,
                'monto_cierre_esperado'  => null,
                'diferencia'             => null,
                'observacion_cierre'     => null,
            ]);
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

        Turno::create([
            'empresa_id'           => $user->empresa_id,
            'local_id'             => $caja->local_id,
            'caja_id'              => $caja->id,
            'user_id'              => $user->id,
            'monto_apertura'       => $request->input('monto_apertura'),
            'monto_caja_chica'     => $montoCajaChica,
            'estado'               => 'abierto',
            'fecha_apertura'       => now(),
            'observacion_apertura' => $request->input('observacion_apertura'),
        ]);

        return redirect()->back()->with('success', 'Turno abierto correctamente.');
    }

    public function cerrarPage(Request $request, Turno $turno)
    {
        $user = $request->user();
        abort_if($turno->user_id !== $user->id, 403);
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
            ->get(['id', 'nombre', 'tipo']);

        $modoCaja      = $this->config->modoCierreCaja($turno->local);
        $modoInventario = $this->config->modoCierreInventario($turno->local);
        $usaFondos     = $this->config->usaFondosIniciales($turno->local);
        $fondosEnDecl  = $this->config->fondosInicialesEnDeclaracion($turno->local);

        // Cierre de inventario asociado a este turno (si existe)
        $cierreInventarioTurno = CierreInventario::where('turno_id', $turno->id)
            ->orderByDesc('id')
            ->first();

        return Inertia::render('Turnos/Cerrar', [
            'turno'                        => $turno,
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
        abort_if($turno->user_id !== $request->user()->id, 403);
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

            // Snapshot de productos vendidos en el turno (para reportes históricos)
            $turno->poblarSnapshotProductos();
        });

        return redirect()->route('turnos.index')->with('success', 'Turno cerrado correctamente.');
    }
}
