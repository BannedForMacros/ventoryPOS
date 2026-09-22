<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\User;
use App\Services\LocalScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Reporte de agenda POR PROFESIONAL.
 *
 * "Profesional" es deliberadamente neutro, igual que la columna `citas.profesional_id`
 * que ya existía: quien atiende es una estilista en una peluquería, un veterinario en
 * una clínica y un técnico en un taller. El módulo se diseñó multidisciplina desde el
 * principio (de ahí `agenda_sujeto_label`) y este reporte no lo estrecha: cada empresa
 * le pone el nombre que use.
 *
 * QUÉ CONTESTA: cuánto atendió cada uno, cuánto se le plantaron y cuánto dinero
 * entró por lo suyo, en un rango de fechas.
 *
 * EL DINERO SALE DE LA VENTA, NO DE LA CITA. Una cita no tiene importe: los
 * precios pueden cambiar entre que se reserva y se cobra, y en el mostrador se
 * añaden cosas que no estaban reservadas. El único importe real es el de la venta
 * que nació de esa cita (`citas.venta_id`). Calcularlo desde los ítems reservados
 * daría un número que no coincide con la caja, y ese es el tipo de cifra que
 * destruye la confianza en un reporte.
 */
class ReporteAgendaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->empresa->usa_agenda, 403, 'Esta empresa no tiene el módulo Agenda habilitado.');

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        // Las columnas van SIEMPRE con su tabla: abajo esto se une con `users` y
        // con `ventas`, y las tres tienen `empresa_id`, `local_id` y `estado`.
        // Sin calificar, Postgres corta con "column reference is ambiguous".
        $base = fn () => Cita::query()
            ->where('citas.empresa_id', $user->empresa_id)
            ->whereBetween('citas.fecha_hora', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->local_id, fn ($q, $v) => $q->where('citas.local_id', $v))
            ->when($request->profesional_id, fn ($q, $v) => $q->where('citas.profesional_id', $v))
            // Un usuario con local asignado ve lo de SU local, como el resto de reportes.
            ->when($user->local_id, fn ($q) => $q->where('citas.local_id', $user->local_id));

        // ── Una fila por profesional ────────────────────────────────────────
        //
        // LEFT JOIN a ventas: una cita completada siempre tiene venta, pero una
        // programada o cancelada no. Con INNER JOIN desaparecerían del conteo
        // justo las que hacen falta para calcular la asistencia.
        //
        // `profesional_id` puede ser NULL (cita del local, sin nadie asignado):
        // esas se agrupan aparte en vez de descartarse, porque son horas de
        // trabajo que alguien hizo.
        $filas = $base()
            ->leftJoin('ventas', 'ventas.id', '=', 'citas.venta_id')
            ->leftJoin('users', 'users.id', '=', 'citas.profesional_id')
            ->groupBy('citas.profesional_id', 'users.name')
            ->selectRaw("
                citas.profesional_id,
                COALESCE(users.name, 'Sin profesional asignado') as profesional,
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE citas.estado = ?) as completadas,
                COUNT(*) FILTER (WHERE citas.estado = ?) as no_asistio,
                COUNT(*) FILTER (WHERE citas.estado = ?) as canceladas,
                COUNT(*) FILTER (WHERE citas.estado IN (?, ?, ?)) as pendientes,
                COALESCE(SUM(citas.duracion_min) FILTER (WHERE citas.estado = ?), 0) as minutos,
                COALESCE(SUM(ventas.total) FILTER (WHERE ventas.estado = 'completada'), 0) as monto
            ", [
                Cita::ESTADO_COMPLETADA, Cita::ESTADO_NO_ASISTIO, Cita::ESTADO_CANCELADA,
                Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA, Cita::ESTADO_EN_ATENCION,
                Cita::ESTADO_COMPLETADA,
            ])
            ->orderByDesc('monto')
            ->get()
            ->map(function ($f) {
                $completadas = (int) $f->completadas;
                $monto       = (float) $f->monto;

                return [
                    'profesional_id' => $f->profesional_id,
                    'profesional'    => $f->profesional,
                    'total'          => (int) $f->total,
                    'completadas'    => $completadas,
                    'no_asistio'     => (int) $f->no_asistio,
                    'canceladas'     => (int) $f->canceladas,
                    'pendientes'     => (int) $f->pendientes,
                    'minutos'        => (int) $f->minutos,
                    'monto'          => round($monto, 2),
                    // Lo que de verdad se quiere saber de cada uno.
                    'ticket'         => $completadas > 0 ? round($monto / $completadas, 2) : 0.0,
                    // Sobre las citas que YA tuvieron desenlace: contar las futuras
                    // como "no asistió todavía" hundiría el porcentaje sin motivo.
                    'asistencia'     => ($completadas + (int) $f->no_asistio) > 0
                        ? round($completadas * 100 / ($completadas + (int) $f->no_asistio), 1)
                        : null,
                ];
            })
            ->values();

        // ── Servicios más hechos, en el mismo rango ─────────────────────────
        // Se cuentan sobre las citas COMPLETADAS: lo reservado y no atendido no
        // dice qué se trabajó.
        $servicios = DB::table('cita_items as ci')
            ->join('citas as c', 'c.id', '=', 'ci.cita_id')
            ->join('productos as p', 'p.id', '=', 'ci.producto_id')
            ->where('c.empresa_id', $user->empresa_id)
            ->where('c.estado', Cita::ESTADO_COMPLETADA)
            ->whereBetween('c.fecha_hora', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->local_id, fn ($q, $v) => $q->where('c.local_id', $v))
            ->when($request->profesional_id, fn ($q, $v) => $q->where('c.profesional_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('c.local_id', $user->local_id))
            ->groupBy('p.id', 'p.nombre')
            ->selectRaw('p.nombre, COUNT(*) as veces, COALESCE(SUM(ci.cantidad), 0) as cantidad')
            ->orderByDesc('veces')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'nombre'   => $s->nombre,
                'veces'    => (int) $s->veces,
                'cantidad' => (float) $s->cantidad,
            ]);

        $totales = [
            'citas'       => (int) $filas->sum('total'),
            'completadas' => (int) $filas->sum('completadas'),
            'no_asistio'  => (int) $filas->sum('no_asistio'),
            'monto'       => round((float) $filas->sum('monto'), 2),
        ];
        $conDesenlace = $totales['completadas'] + $totales['no_asistio'];

        return Inertia::render('Reportes/Agenda', [
            'filas'     => $filas,
            'servicios' => $servicios,
            'kpis'      => $totales + [
                'ticket'     => $totales['completadas'] > 0
                    ? round($totales['monto'] / $totales['completadas'], 2) : 0.0,
                'asistencia' => $conDesenlace > 0
                    ? round($totales['completadas'] * 100 / $conDesenlace, 1) : null,
            ],
            'filters'   => [
                'fecha_desde'    => $desde,
                'fecha_hasta'    => $hasta,
                'local_id'       => $request->local_id,
                'profesional_id' => $request->profesional_id,
            ],
            'locales'       => $this->scope->localesVisibles($user),
            'profesionales' => User::where('empresa_id', $user->empresa_id)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
