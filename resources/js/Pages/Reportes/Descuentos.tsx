import { useEffect } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Percent, Calendar, Receipt, ShieldCheck, PieChart, UserRound, Users, TrendingDown,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import { AreaChart, DonutChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Th,
    theadStyle, zebra, fmtS, fmtInt, diaLabel, fechaHora,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { DescuentoConcepto, DescuentoLog, User, PageProps } from '@/types';

interface Kpis {
    total_descontado:     number;
    count:                number;
    ventas_rango:         number;
    pct_sobre_ventas:     number | null;
    con_aprobacion:       number;
    monto_con_aprobacion: number;
}

interface SerieDia    { dia: string; total: number; descuentos: number; }
interface PorConcepto { nombre: string; total: number; count: number; }
interface PorUsuario  { nombre: string; total: number; count: number; }
interface PorCliente  { nombre: string; total: number; count: number; }

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    concepto_id?: string; user_id?: string; aprobacion?: string;
}

interface Props extends PageProps {
    logs:         Paginado<DescuentoLog>;
    kpis:         Kpis;
    serie_diaria: SerieDia[];
    por_concepto: PorConcepto[];
    por_usuario:  PorUsuario[];
    por_cliente:  PorCliente[];
    conceptos:    Pick<DescuentoConcepto, 'id' | 'nombre'>[];
    usuarios:     Pick<User, 'id' | 'name'>[];
    filters:      Filters;
}

/** La relación aprobadoPor serializa sobre la clave `aprobado_por` (pisa el FK). */
const nombreAprobador = (log: DescuentoLog): string | undefined => {
    const v = log.aprobado_por as unknown;
    return v && typeof v === 'object' ? (v as { name?: string }).name : undefined;
};

const nombreCliente = (log: DescuentoLog) => {
    const c = log.cliente as { nombres?: string; apellidos?: string; razon_social?: string } | undefined;
    if (!c) return '—';
    return c.razon_social || `${c.nombres ?? ''} ${c.apellidos ?? ''}`.trim() || '—';
};

export default function ReportesDescuentos({
    logs, kpis, serie_diaria, por_concepto, por_usuario, por_cliente,
    conceptos, usuarios, filters, flash,
}: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.descuentos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.descuentos'), {}, { preserveState: true, replace: true });

    const tieneFiltros = !!(filters.concepto_id || filters.user_id || filters.aprobacion);
    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Reporte de descuentos">
            <PageHeader
                icon={<Percent size={22} />}
                title="Reporte de descuentos"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${fmtInt(kpis.count)} descuentos aplicados`}
            />

            {/* Filtros */}
            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                <FieldSelect label="Concepto" value={filters.concepto_id ?? ''}
                    onChange={v => filtrar({ concepto_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...conceptos.map(c => ({ value: String(c.id), label: c.nombre }))]} />
                <FieldSelect label="Usuario" value={filters.user_id ?? ''}
                    onChange={v => filtrar({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
                <FieldSelect label="Aprobación" value={filters.aprobacion ?? ''}
                    onChange={v => filtrar({ aprobacion: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: '1', label: 'Solo con aprobación' },
                    ]} />
            </FiltrosReporte>

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <Kpi icon={<TrendingDown size={18} />} label="Total descontado" value={fmtS(kpis.total_descontado)}
                    sub={`${fmtInt(kpis.count)} descuentos`}
                    color="var(--color-danger)" />
                <Kpi icon={<Percent size={18} />} label="% sobre ventas"
                    value={kpis.pct_sobre_ventas !== null ? `${kpis.pct_sobre_ventas}%` : '—'}
                    sub={`ventas del rango: ${fmtS(kpis.ventas_rango)}`}
                    color="var(--color-primary)" />
                <Kpi icon={<ShieldCheck size={18} />} label="Con aprobación" value={fmtInt(kpis.con_aprobacion)}
                    sub={kpis.con_aprobacion > 0 ? fmtS(kpis.monto_con_aprobacion) : 'ninguno requirió'}
                    subColor={kpis.con_aprobacion > 0 ? 'var(--color-warning)' : undefined}
                    color="var(--color-warning)" />
                <Kpi icon={<Receipt size={18} />} label="Descuento promedio"
                    value={kpis.count > 0 ? fmtS(kpis.total_descontado / kpis.count) : fmtS(0)}
                    color="#8b5cf6" />
            </div>

            {/* Serie + dona */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<Calendar size={14} />} title="Descuentos por día" accent="var(--color-danger)">
                    <AreaChart
                        data={serieChart}
                        series={[{ key: 'total', label: 'Descontado', color: 'var(--color-danger)', relleno: true }]}
                        height={230}
                    />
                </ReportCard>
                <ReportCard icon={<PieChart size={14} />} title="Por concepto" accent="#8b5cf6">
                    <DonutChart
                        data={por_concepto.map(c => ({ label: c.nombre, valor: c.total }))}
                        centro={{ valor: fmtS(kpis.total_descontado), label: 'descontado' }}
                        vertical
                    />
                </ReportCard>
            </div>

            {/* Por usuario + por cliente */}
            <div className="grid md:grid-cols-2 gap-4 mb-4">
                <ReportCard icon={<UserRound size={14} />} title="Quién descuenta más" accent="var(--color-warning)">
                    <BarList
                        data={por_usuario.map(u => ({ label: u.nombre, valor: u.total, extra: `${u.count} veces` }))}
                        multicolor
                    />
                </ReportCard>
                <ReportCard icon={<Users size={14} />} title="Clientes con más descuento" accent="var(--vp-navy)">
                    <BarList
                        data={por_cliente.map(c => ({ label: c.nombre, valor: c.total, extra: `${c.count} veces` }))}
                    />
                </ReportCard>
            </div>

            {/* Listado */}
            <ReportCard icon={<Receipt size={14} />} title="Detalle de descuentos" badge={fmtInt(logs.total)} sinPadding>
                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={theadStyle}>
                                <Th>Fecha</Th><Th>Venta</Th><Th>Cliente</Th><Th>Concepto</Th>
                                <Th>Ámbito</Th><Th>Usuario</Th><Th>Aprobación</Th><Th right>Monto</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log, idx) => (
                                <tr key={log.id} style={zebra(idx)} className="transition-colors hover:bg-black/[0.03]">
                                    <td className="px-3 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                        {fechaHora(log.created_at)}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {log.venta_id ? (
                                            <Link href={route('ventas.show', log.venta_id)}
                                                className="font-mono text-xs font-semibold hover:underline"
                                                style={{ color: 'var(--color-primary)' }}>
                                                {(log.venta as { numero?: string } | undefined)?.numero ?? `#${log.venta_id}`}
                                            </Link>
                                        ) : <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{nombreCliente(log)}</td>
                                    <td className="px-3 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                        {(log.concepto as { nombre?: string } | undefined)?.nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                            style={log.venta_item_id ? {
                                                color: 'var(--color-primary)',
                                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                            } : {
                                                color: '#8b5cf6',
                                                backgroundColor: 'color-mix(in srgb, #8b5cf6 12%, transparent)',
                                            }}>
                                            {log.venta_item_id ? 'Línea' : 'Global'}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {(log.user as { name?: string } | undefined)?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {log.requeria_aprobacion ? (
                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                                                style={{
                                                    color: 'var(--color-warning)',
                                                    backgroundColor: 'color-mix(in srgb, var(--color-warning) 14%, transparent)',
                                                }}>
                                                {nombreAprobador(log) ? `Aprobó ${nombreAprobador(log)}` : 'Requería aprobación'}
                                            </span>
                                        ) : (
                                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Libre</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-bold text-sm whitespace-nowrap" style={{ color: 'var(--color-danger)' }}>
                                        -{fmtS(parseFloat(log.monto_descuento))}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <Percent size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron descuentos</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 p-3">
                    {logs.data.map(log => (
                        <div key={log.id} className="rounded-xl p-3"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                border: '1px solid var(--color-border)',
                            }}>
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                        {(log.concepto as { nombre?: string } | undefined)?.nombre ?? '—'}
                                        <span className="ml-1.5 text-[10px] font-bold" style={{ color: log.venta_item_id ? 'var(--color-primary)' : '#8b5cf6' }}>
                                            {log.venta_item_id ? 'Línea' : 'Global'}
                                        </span>
                                    </p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        {fechaHora(log.created_at)} · {(log.user as { name?: string } | undefined)?.name ?? '—'}
                                    </p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text)' }}>{nombreCliente(log)}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-sm" style={{ color: 'var(--color-danger)' }}>
                                        -{fmtS(parseFloat(log.monto_descuento))}
                                    </p>
                                    {log.venta_id && (
                                        <Link href={route('ventas.show', log.venta_id)}
                                            className="font-mono text-[11px] hover:underline" style={{ color: 'var(--color-primary)' }}>
                                            {(log.venta as { numero?: string } | undefined)?.numero ?? `#${log.venta_id}`}
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <Paginacion paginado={logs} ruta="reportes.descuentos" filters={filters as unknown as Record<string, unknown>} />
            </ReportCard>
        </AppLayout>
    );
}
