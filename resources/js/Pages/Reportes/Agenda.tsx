import { router } from '@inertiajs/react';
import {
    CalendarClock, CheckCircle2, UserX, Coins, Users, Clock, Sparkles,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import { BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Empty, Th,
    theadStyle, zebra, fmtS, fmtInt,
} from '@/Components/Reportes/ReportUI';
import type { PageProps } from '@/types';

/**
 * Reporte de agenda POR PROFESIONAL.
 *
 * "Profesional" es neutro a propósito: la estilista de una peluquería, el
 * veterinario de una clínica y el técnico de un taller usan la misma agenda y
 * el mismo reporte. Nada aquí habla de un rubro concreto.
 */

interface Fila {
    profesional_id: number | null;
    profesional:    string;
    total:          number;
    completadas:    number;
    no_asistio:     number;
    canceladas:     number;
    pendientes:     number;
    minutos:        number;
    monto:          number;
    ticket:         number;
    /** null cuando todavía ninguna cita suya tuvo desenlace. */
    asistencia:     number | null;
}

interface Props extends PageProps {
    filas:     Fila[];
    servicios: { nombre: string; veces: number; cantidad: number }[];
    kpis: {
        citas: number; completadas: number; no_asistio: number;
        monto: number; ticket: number; asistencia: number | null;
    };
    filters: { fecha_desde: string; fecha_hasta: string; local_id?: string; profesional_id?: string };
    locales:       { id: number; nombre: string }[];
    profesionales: { id: number; name: string }[];
}

/** 135 min → "2h 15m". En minutos sueltos no se lee una jornada. */
function horas(min: number): string {
    if (min <= 0) return '—';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h${m > 0 ? ` ${m}m` : ''}` : `${m}m`;
}

export default function ReporteAgenda({ filas, servicios, kpis, filters, locales, profesionales }: Props) {
    function aplicar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.agenda'), { ...filters, ...patch }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }

    const tieneFiltros = !!(filters.local_id || filters.profesional_id);
    // El más productivo marca el largo de la barra; el resto se lee en relación a él.
    const montoMax = Math.max(...filas.map(f => f.monto), 1);

    return (
        <AppLayout title="Reporte de agenda">
            <PageHeader
                title="Agenda por profesional"
                subtitle="Cuánto atendió cada uno, cuánto se le plantaron y cuánto produjo"
            />

            <FiltrosReporte
                fechaDesde={filters.fecha_desde}
                fechaHasta={filters.fecha_hasta}
                onChange={aplicar}
                onClear={() => aplicar({ local_id: undefined, profesional_id: undefined })}
                tieneFiltros={tieneFiltros}
            >
                {locales.length > 1 && (
                    <FieldSelect
                        label="Local"
                        value={filters.local_id ?? ''}
                        onChange={v => aplicar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' },
                            ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]}
                    />
                )}
                <FieldSelect
                    label="Profesional"
                    value={filters.profesional_id ?? ''}
                    onChange={v => aplicar({ profesional_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' },
                        ...profesionales.map(p => ({ value: String(p.id), label: p.name }))]}
                />
            </FiltrosReporte>

            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                <Kpi icon={<CalendarClock size={16} />} label="Citas" value={fmtInt(kpis.citas)}
                    color="var(--color-text)" />
                <Kpi icon={<CheckCircle2 size={16} />} label="Atendidas" value={fmtInt(kpis.completadas)}
                    color="var(--color-success)" />
                <Kpi icon={<UserX size={16} />} label="No asistieron" value={fmtInt(kpis.no_asistio)}
                    sub={kpis.asistencia !== null ? `${kpis.asistencia}% de asistencia` : undefined}
                    color={kpis.no_asistio > 0 ? 'var(--color-danger)' : 'var(--color-text)'} />
                <Kpi icon={<Coins size={16} />} label="Producido" value={fmtS(kpis.monto)}
                    color="var(--color-primary)" />
                <Kpi icon={<Sparkles size={16} />} label="Ticket promedio" value={fmtS(kpis.ticket)}
                    sub="por cita atendida" color="var(--color-text)" />
            </div>

            <ReportCard icon={<Users size={15} />} title="Por profesional" sinPadding>
                {filas.length === 0 ? (
                    <Empty text="No hay citas en el período" />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={theadStyle}>
                                <tr>
                                    <Th>Profesional</Th>
                                    <Th right>Citas</Th>
                                    <Th right>Atendidas</Th>
                                    <Th right>No asistió</Th>
                                    <Th right>Canceladas</Th>
                                    <Th right>Por venir</Th>
                                    <Th right>Tiempo</Th>
                                    <Th right>Ticket</Th>
                                    <Th right>Producido</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {filas.map((f, i) => (
                                    <tr key={f.profesional_id ?? 'sin'} style={zebra(i)}>
                                        <td className="px-3 py-2">
                                            <div className="font-medium">{f.profesional}</div>
                                            {/* La barra hace comparable de un vistazo lo que la
                                                columna de números solo deja comparar leyendo. */}
                                            <div className="mt-1 h-1 rounded-full overflow-hidden"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                                <div className="h-full rounded-full"
                                                    style={{
                                                        width: `${Math.max(2, (f.monto / montoMax) * 100)}%`,
                                                        backgroundColor: 'var(--color-primary)',
                                                    }} />
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{fmtInt(f.total)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums font-semibold"
                                            style={{ color: 'var(--color-success)' }}>{fmtInt(f.completadas)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums"
                                            style={{ color: f.no_asistio > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                            {fmtInt(f.no_asistio)}
                                            {f.asistencia !== null && (
                                                <span className="block text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                                                    {f.asistencia}% asist.
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums"
                                            style={{ color: 'var(--color-text-muted)' }}>{fmtInt(f.canceladas)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums"
                                            style={{ color: 'var(--color-text-muted)' }}>{fmtInt(f.pendientes)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums"
                                            style={{ color: 'var(--color-text-muted)' }}>
                                            <span className="inline-flex items-center gap-1">
                                                <Clock size={11} className="opacity-50" />{horas(f.minutos)}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{fmtS(f.ticket)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums font-semibold">{fmtS(f.monto)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </ReportCard>

            <div className="mt-4">
                <ReportCard icon={<Sparkles size={15} />} title="Servicios más atendidos">
                    {servicios.length === 0 ? (
                        <Empty text="Sin servicios atendidos en el período" />
                    ) : (
                        // money=false: aquí se cuentan veces atendido, no soles.
                        <BarList
                            data={servicios.map(s => ({ label: s.nombre, valor: s.veces }))}
                            money={false}
                        />
                    )}
                </ReportCard>
            </div>
        </AppLayout>
    );
}
