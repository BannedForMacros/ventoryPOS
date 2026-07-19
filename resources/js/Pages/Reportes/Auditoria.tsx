import { Fragment, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Activity, Calendar, ChevronDown, ChevronUp, ShieldCheck,
    Users, Zap, ListChecks, UserRound,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import { AreaChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Empty, Th,
    theadStyle, zebra, fmtInt, diaLabel, fechaHora,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { PageProps, User } from '@/types';

interface Registro {
    id:           number;
    created_at:   string;
    accion:       string;
    accion_label: string;
    user_id:      number | null;
    user_name:    string | null;
    user_email:   string | null;
    modelo_tipo:  string | null;
    modelo_id:    number | null;
    contexto:     Record<string, unknown> | null;
    ip:           string | null;
}

interface Kpis {
    total_acciones:   number;
    usuarios_activos: number;
    acciones_hoy:     number;
    accion_top:       string;
}

interface PorAccion  { accion: string; label: string; total: number; }
interface PorUsuario { nombre: string; total: number; }
interface SerieDia   { dia: string; total: number; }

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    accion?: string; user_id?: string;
}

interface Props extends PageProps {
    registros:    Paginado<Registro>;
    kpis:         Kpis;
    por_accion:   PorAccion[];
    por_usuario:  PorUsuario[];
    serie_diaria: SerieDia[];
    usuarios:     Pick<User, 'id' | 'name'>[];
    acciones:     Record<string, string>;
    filters:      Filters;
}

/** Color del badge según el "verbo" del slug de acción. */
function colorAccion(accion: string): string {
    if (/elimin|anulad|rechaz|cancel/.test(accion)) return 'var(--color-danger)';
    if (/cread|creada|confirmad|completad|aprobad/.test(accion)) return 'var(--color-success)';
    if (/actualizad|modificad|cerrad|reabiert|recalculad|iniciad/.test(accion)) return 'var(--color-primary)';
    return 'var(--color-secondary)';
}

const valorLegible = (v: unknown): string => {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'boolean') return v ? 'sí' : 'no';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
};

export default function ReportesAuditoria({
    registros, kpis, por_accion, por_usuario, serie_diaria, usuarios, acciones, filters, flash,
}: Props) {
    const [abiertos, setAbiertos] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.auditoria'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.auditoria'), {}, { preserveState: true, replace: true });
    const tieneFiltros = !!(filters.accion || filters.user_id);

    function toggle(id: number) {
        setAbiertos(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Auditoría">
            <PageHeader
                icon={<ShieldCheck size={22} />}
                title="Auditoría"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${fmtInt(kpis.total_acciones)} acciones registradas`}
            />

            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                <FieldSelect label="Acción" value={filters.accion ?? ''}
                    onChange={v => filtrar({ accion: v || undefined })}
                    options={[
                        { value: '', label: 'Todas' },
                        ...Object.entries(acciones).map(([value, label]) => ({ value, label })),
                    ]} />
                <FieldSelect label="Usuario" value={filters.user_id ?? ''}
                    onChange={v => filtrar({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
            </FiltrosReporte>

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <Kpi icon={<Activity size={18} />} label="Acciones en el rango" value={fmtInt(kpis.total_acciones)}
                    color="var(--color-primary)" />
                <Kpi icon={<Users size={18} />} label="Usuarios activos" value={fmtInt(kpis.usuarios_activos)}
                    color="var(--color-success)" />
                <Kpi icon={<Calendar size={18} />} label="Acciones hoy" value={fmtInt(kpis.acciones_hoy)}
                    color="#8b5cf6" />
                <Kpi icon={<Zap size={18} />} label="Acción más frecuente" value={kpis.accion_top}
                    color="var(--color-warning)" />
            </div>

            {/* Serie + desgloses */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<Calendar size={14} />} title="Actividad por día">
                    <AreaChart
                        data={serieChart}
                        series={[{ key: 'total', label: 'Acciones', relleno: true }]}
                        height={220}
                        money={false}
                    />
                </ReportCard>
                <ReportCard icon={<ListChecks size={14} />} title="Por acción" accent="#8b5cf6">
                    <BarList
                        data={por_accion.map(a => ({ label: a.label, valor: a.total, color: colorAccion(a.accion) }))}
                        money={false}
                    />
                </ReportCard>
            </div>

            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard icon={<UserRound size={14} />} title="Por usuario" accent="var(--vp-navy)">
                    <BarList
                        data={por_usuario.map(u => ({ label: u.nombre, valor: u.total }))}
                        money={false}
                    />
                </ReportCard>

                {/* Listado */}
                <ReportCard className="lg:col-span-2" icon={<ShieldCheck size={14} />} title="Registro de acciones"
                    badge={fmtInt(registros.total)} sinPadding>
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={theadStyle}>
                                    <Th className="w-8" />
                                    <Th>Fecha</Th><Th>Usuario</Th><Th>Acción</Th><Th>Registro</Th><Th>IP</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {registros.data.map((r, idx) => (
                                    <Fragment key={r.id}>
                                        <tr onClick={() => toggle(r.id)}
                                            className="cursor-pointer transition-colors hover:bg-black/[0.03]"
                                            style={zebra(idx)}>
                                            <td className="pl-3 py-2.5" style={{ color: 'var(--color-primary)' }}>
                                                {abiertos.has(r.id) ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                            </td>
                                            <td className="px-3 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                                {fechaHora(r.created_at)}
                                            </td>
                                            <td className="px-3 py-2.5 text-xs">
                                                <p className="font-medium" style={{ color: 'var(--color-text)' }}>{r.user_name ?? '—'}</p>
                                                {r.user_email && (
                                                    <p className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{r.user_email}</p>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                                                    style={{
                                                        color: colorAccion(r.accion),
                                                        backgroundColor: `color-mix(in srgb, ${colorAccion(r.accion)} 12%, transparent)`,
                                                    }}>
                                                    {r.accion_label}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {r.modelo_tipo ? `${r.modelo_tipo} #${r.modelo_id ?? '—'}` : '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                                {r.ip ?? '—'}
                                            </td>
                                        </tr>
                                        {abiertos.has(r.id) && (
                                            <tr>
                                                <td colSpan={6} className="px-4 py-3"
                                                    style={{
                                                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 4%, var(--color-surface))',
                                                        borderTop: '1px dashed var(--color-border)',
                                                    }}>
                                                    <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                                                        Contexto
                                                    </p>
                                                    {r.contexto && Object.keys(r.contexto).length > 0 ? (
                                                        <div className="grid sm:grid-cols-2 gap-x-6 gap-y-1">
                                                            {Object.entries(r.contexto).map(([k, v]) => (
                                                                <div key={k} className="flex items-baseline justify-between gap-3 text-[11px]"
                                                                    style={{ borderBottom: '1px dotted color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                                                    <span className="font-medium" style={{ color: 'var(--color-text-muted)' }}>
                                                                        {k.replaceAll('_', ' ')}
                                                                    </span>
                                                                    <span className="font-semibold text-right break-all" style={{ color: 'var(--color-text)' }}>
                                                                        {valorLegible(v)}
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>Sin datos adicionales</p>
                                                    )}
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                                {registros.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                            <ShieldCheck size={36} className="mx-auto mb-2 opacity-20" />
                                            <p className="text-sm">Sin acciones registradas</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Cards móvil */}
                    <div className="md:hidden flex flex-col gap-2 p-3">
                        {registros.data.map(r => (
                            <div key={r.id} className="rounded-xl p-3"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                    border: '1px solid var(--color-border)',
                                }}
                                onClick={() => toggle(r.id)}>
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                            style={{
                                                color: colorAccion(r.accion),
                                                backgroundColor: `color-mix(in srgb, ${colorAccion(r.accion)} 12%, transparent)`,
                                            }}>
                                            {r.accion_label}
                                        </span>
                                        <p className="text-xs mt-1" style={{ color: 'var(--color-text)' }}>{r.user_name ?? '—'}</p>
                                        <p className="text-[10px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{fechaHora(r.created_at)}</p>
                                    </div>
                                    <span className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                                        {r.modelo_tipo ? `${r.modelo_tipo} #${r.modelo_id ?? ''}` : ''}
                                    </span>
                                </div>
                                {abiertos.has(r.id) && r.contexto && Object.keys(r.contexto).length > 0 && (
                                    <div className="mt-2 pt-2 space-y-0.5" style={{ borderTop: '1px dashed var(--color-border)' }}>
                                        {Object.entries(r.contexto).map(([k, v]) => (
                                            <div key={k} className="flex justify-between gap-2 text-[11px]">
                                                <span style={{ color: 'var(--color-text-muted)' }}>{k.replaceAll('_', ' ')}</span>
                                                <span className="font-semibold text-right break-all" style={{ color: 'var(--color-text)' }}>{valorLegible(v)}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                        {registros.data.length === 0 && <Empty text="Sin acciones registradas" />}
                    </div>

                    <Paginacion paginado={registros} ruta="reportes.auditoria" filters={filters as unknown as Record<string, unknown>} />
                </ReportCard>
            </div>
        </AppLayout>
    );
}
