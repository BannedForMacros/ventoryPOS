import { Fragment, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Wallet, Receipt, Building2, Store, Calendar, ChevronDown, ChevronUp,
    PieChart, ListOrdered, UserRound, Trash2, CalendarClock,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import { AreaChart, DonutChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Th,
    theadStyle, zebra, fmtS, fmtInt, diaLabel, fechaHora,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Gasto, GastoTipo, Local, User, PageProps } from '@/types';

interface Kpis {
    total:           number;
    count:           number;
    admin:           number;
    turno:           number;
    promedio_diario: number;
    eliminados:      number;
    monto_eliminado: number;
}

interface SerieDia    { dia: string; total: number; gastos: number; }
interface PorTipo     { gasto_tipo_id: number; nombre: string; categoria: string; total: number; count: number; }
interface PorConcepto { nombre: string; tipo: string; total: number; count: number; }
interface PorUsuario  { nombre: string; total: number; count: number; }

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    tipo_id?: string; concepto_id?: string; local_id?: string;
    user_id?: string; origen?: string; estado?: string;
}

interface Props extends PageProps {
    gastos:       Paginado<Gasto>;
    kpis:         Kpis;
    serie_diaria: SerieDia[];
    por_tipo:     PorTipo[];
    por_concepto: PorConcepto[];
    por_usuario:  PorUsuario[];
    tipos:        GastoTipo[];
    locales:      Local[];
    usuarios:     Pick<User, 'id' | 'name'>[];
    filters:      Filters;
}

const soloFecha = (v: string) => (v ?? '').slice(0, 10);

export default function ReportesGastos({
    gastos, kpis, serie_diaria, por_tipo, por_concepto, por_usuario,
    tipos, locales, usuarios, filters, flash,
}: Props) {
    const [abiertos, setAbiertos] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        // Cambiar de tipo invalida el concepto elegido
        if ('tipo_id' in patch && patch.tipo_id !== filters.tipo_id) patch.concepto_id = undefined;
        router.get(route('reportes.gastos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.gastos'), {}, { preserveState: true, replace: true });

    const tieneFiltros = !!(filters.tipo_id || filters.concepto_id || filters.local_id
        || filters.user_id || filters.origen || filters.estado);

    const conceptosDelTipo = useMemo(() => {
        if (!filters.tipo_id) return tipos.flatMap(t => (t.conceptos ?? []));
        return tipos.find(t => String(t.id) === String(filters.tipo_id))?.conceptos ?? [];
    }, [tipos, filters.tipo_id]);

    function toggle(id: number) {
        setAbiertos(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const viendoEliminados = filters.estado === 'eliminados';
    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Reporte de gastos">
            <PageHeader
                icon={<Wallet size={22} />}
                title="Reporte de gastos"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${fmtInt(kpis.count)} gastos activos`}
            />

            {/* Filtros */}
            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                <FieldSelect label="Tipo" value={filters.tipo_id ?? ''}
                    onChange={v => filtrar({ tipo_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...tipos.map(t => ({ value: String(t.id), label: t.nombre }))]} />
                <FieldSelect label="Concepto" value={filters.concepto_id ?? ''}
                    onChange={v => filtrar({ concepto_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...conceptosDelTipo.map(c => ({ value: String(c.id), label: c.nombre }))]} />
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
                <FieldSelect label="Usuario" value={filters.user_id ?? ''}
                    onChange={v => filtrar({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
                <FieldSelect label="Origen" value={filters.origen ?? ''}
                    onChange={v => filtrar({ origen: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'turno', label: 'De turno' },
                        { value: 'admin', label: 'Administrativos' },
                    ]} />
                <FieldSelect label="Estado" value={filters.estado ?? ''}
                    onChange={v => filtrar({ estado: v || undefined })}
                    options={[
                        { value: '', label: 'Activos' },
                        { value: 'eliminados', label: 'Eliminados' },
                        { value: 'todos', label: 'Todos' },
                    ]} />
            </FiltrosReporte>

            {/* KPIs (siempre sobre gastos activos) */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<Wallet size={18} />} label="Total gastado" value={fmtS(kpis.total)}
                    sub={`${fmtInt(kpis.count)} gastos activos`}
                    color="var(--color-danger)" />
                <Kpi icon={<Building2 size={18} />} label="Administrativo" value={fmtS(kpis.admin)}
                    sub={kpis.total > 0 ? `${Math.round((kpis.admin / kpis.total) * 100)}% del total` : undefined}
                    color="var(--vp-navy)" />
                <Kpi icon={<Store size={18} />} label="De turno (caja)" value={fmtS(kpis.turno)}
                    sub={kpis.total > 0 ? `${Math.round((kpis.turno / kpis.total) * 100)}% del total` : undefined}
                    color="var(--color-primary)" />
                <Kpi icon={<CalendarClock size={18} />} label="Promedio diario" value={fmtS(kpis.promedio_diario)}
                    color="#8b5cf6" />
                <Kpi icon={<Trash2 size={18} />} label="Eliminados" value={fmtInt(kpis.eliminados)}
                    sub={kpis.eliminados > 0 ? `${fmtS(kpis.monto_eliminado)} revertidos` : 'ninguno en el rango'}
                    subColor={kpis.eliminados > 0 ? 'var(--color-warning)' : undefined}
                    color="var(--color-warning)" />
            </div>

            {/* Serie diaria + dona por tipo */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<Calendar size={14} />} title="Gastos por día" accent="var(--color-danger)">
                    <AreaChart
                        data={serieChart}
                        series={[{ key: 'total', label: 'Gastado', color: 'var(--color-danger)', relleno: true }]}
                        height={230}
                    />
                </ReportCard>
                <ReportCard icon={<PieChart size={14} />} title="Por tipo de gasto" accent="#8b5cf6">
                    <DonutChart
                        data={por_tipo.map(t => ({ label: t.nombre, valor: t.total }))}
                        centro={{ valor: fmtS(kpis.total), label: 'total' }}
                        vertical
                    />
                </ReportCard>
            </div>

            {/* Conceptos + usuarios */}
            <div className="grid md:grid-cols-2 gap-4 mb-4">
                <ReportCard icon={<ListOrdered size={14} />} title="Top conceptos" accent="var(--color-warning)">
                    <BarList
                        data={por_concepto.map(c => ({
                            label: c.nombre,
                            valor: c.total,
                            extra: `${c.tipo}${c.tipo ? ' · ' : ''}${c.count} gastos`,
                            color: 'var(--color-warning)',
                        }))}
                    />
                </ReportCard>
                <ReportCard icon={<UserRound size={14} />} title="Por usuario" accent="var(--color-success)">
                    <BarList
                        data={por_usuario.map(u => ({ label: u.nombre, valor: u.total, extra: `${u.count} gastos` }))}
                        multicolor
                    />
                </ReportCard>
            </div>

            {/* Listado */}
            <ReportCard icon={<Receipt size={14} />} title="Detalle de gastos" badge={fmtInt(gastos.total)}
                accent={viendoEliminados ? 'var(--color-warning)' : 'var(--color-primary)'}
                actions={viendoEliminados ? (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                        style={{ color: 'var(--color-warning)', backgroundColor: 'color-mix(in srgb, var(--color-warning) 14%, transparent)' }}>
                        Viendo eliminados — no cuentan en los totales
                    </span>
                ) : undefined}
                sinPadding>
                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={theadStyle}>
                                <Th className="w-8" />
                                <Th>Fecha</Th><Th>Tipo</Th><Th>Concepto</Th><Th>Comentario</Th>
                                <Th>Usuario</Th><Th>Local</Th><Th>Origen</Th><Th>Cuenta</Th><Th right>Monto</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {gastos.data.map((g, idx) => {
                                const eliminado = !!g.deleted_at;
                                return (
                                    <Fragment key={g.id}>
                                        <tr onClick={() => toggle(g.id)}
                                            className="cursor-pointer transition-colors hover:bg-black/[0.03]"
                                            style={{ ...zebra(idx), opacity: eliminado ? 0.55 : 1 }}>
                                            <td className="pl-3 py-2.5" style={{ color: 'var(--color-primary)' }}>
                                                {abiertos.has(g.id) ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                            </td>
                                            <td className="px-3 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                                {soloFecha(g.fecha)}
                                                {eliminado && (
                                                    <span className="block text-[10px]" style={{ color: 'var(--color-warning)' }}>
                                                        eliminado {fechaHora(g.deleted_at as string)}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text)' }}>{g.tipo?.nombre ?? '—'}</td>
                                            <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{g.concepto?.nombre ?? '—'}</td>
                                            <td className="px-3 py-2.5 text-xs max-w-[220px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                {g.comentario ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{g.user?.name ?? '—'}</td>
                                            <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>{g.local?.nombre ?? '—'}</td>
                                            <td className="px-3 py-2.5">
                                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                    style={g.turno_id ? {
                                                        color: 'var(--color-primary)',
                                                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                                    } : {
                                                        color: 'var(--vp-navy)',
                                                        backgroundColor: 'color-mix(in srgb, var(--vp-navy) 10%, transparent)',
                                                    }}>
                                                    {g.turno_id ? 'Turno' : 'Admin'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {(g.cuenta as { nombre?: string } | undefined)?.nombre ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-right font-bold text-sm whitespace-nowrap"
                                                style={{ color: eliminado ? 'var(--color-text-muted)' : 'var(--color-danger)' }}>
                                                {fmtS(parseFloat(g.monto))}
                                            </td>
                                        </tr>
                                        {abiertos.has(g.id) && <DetalleGasto gasto={g} colSpan={10} />}
                                    </Fragment>
                                );
                            })}
                            {gastos.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <Wallet size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron gastos</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 p-3">
                    {gastos.data.map(g => {
                        const eliminado = !!g.deleted_at;
                        return (
                            <div key={g.id} className="rounded-xl p-3"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                    border: '1px solid var(--color-border)',
                                    opacity: eliminado ? 0.6 : 1,
                                }}
                                onClick={() => toggle(g.id)}>
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                            {g.concepto?.nombre ?? '—'}
                                        </p>
                                        <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                            {g.tipo?.nombre ?? '—'} · {soloFecha(g.fecha)} · {g.user?.name ?? '—'}
                                        </p>
                                        {eliminado && (
                                            <p className="text-[10px] mt-0.5" style={{ color: 'var(--color-warning)' }}>
                                                eliminado {fechaHora(g.deleted_at as string)}
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold text-sm" style={{ color: eliminado ? 'var(--color-text-muted)' : 'var(--color-danger)' }}>
                                            {fmtS(parseFloat(g.monto))}
                                        </p>
                                        <span className="text-[10px] font-bold" style={{ color: 'var(--color-text-muted)' }}>
                                            {g.turno_id ? 'Turno' : 'Admin'}
                                        </span>
                                    </div>
                                </div>
                                {abiertos.has(g.id) && g.comentario && (
                                    <p className="text-xs mt-2 pt-2 italic" style={{ borderTop: '1px dashed var(--color-border)', color: 'var(--color-text-muted)' }}>
                                        “{g.comentario}”
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                <Paginacion paginado={gastos} ruta="reportes.gastos" filters={filters as unknown as Record<string, unknown>} />
            </ReportCard>
        </AppLayout>
    );
}

/* ── Fila expandida ─────────────────────────────────────────────────────── */
function DetalleGasto({ gasto, colSpan }: { gasto: Gasto; colSpan: number }) {
    const turno = gasto.turno as { id: number; fecha_apertura: string } | undefined;
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-3"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-danger) 4%, var(--color-surface))',
                    borderTop: '1px dashed var(--color-border)',
                }}>
                <div className="grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-[11px]">
                    <p style={{ color: 'var(--color-text)' }}>
                        <span className="font-bold" style={{ color: 'var(--vp-navy)' }}>Comentario: </span>
                        {gasto.comentario || 'Sin comentario'}
                    </p>
                    <p style={{ color: 'var(--color-text)' }}>
                        <span className="font-bold" style={{ color: 'var(--vp-navy)' }}>Registrado: </span>
                        {fechaHora(gasto.created_at)}
                    </p>
                    {turno && (
                        <p style={{ color: 'var(--color-text)' }}>
                            <span className="font-bold" style={{ color: 'var(--vp-navy)' }}>Turno: </span>
                            #{turno.id} (apertura {fechaHora(turno.fecha_apertura)})
                        </p>
                    )}
                    {(gasto as Record<string, unknown>).moneda === 'USD' && (
                        <p style={{ color: 'var(--color-text)' }}>
                            <span className="font-bold" style={{ color: 'var(--vp-navy)' }}>Moneda original: </span>
                            $ {Number((gasto as Record<string, unknown>).monto_moneda ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}
                            {' '}(TC {String((gasto as Record<string, unknown>).tipo_cambio ?? '')})
                        </p>
                    )}
                </div>
            </td>
        </tr>
    );
}
