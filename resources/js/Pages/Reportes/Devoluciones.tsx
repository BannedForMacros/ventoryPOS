import { Fragment, useEffect, useState } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Undo2, AlertCircle, CheckCircle2, ListChecks, Percent, Calendar,
    HandCoins, Tags, ChevronDown, ChevronUp, PackageCheck, PackageX,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import { AreaChart, DonutChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Empty, Th,
    theadStyle, zebra, fmtS, fmtInt, fmtCant, diaLabel, fechaHora,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Local, User, PageProps } from '@/types';

interface DetalleRow {
    id:              number;
    producto_id:     number;
    venta_item_id:   number | null;
    cantidad:        string;
    precio_unitario: string;
    subtotal:        string;
    estado_producto: string | null;
    restock:         boolean;
    producto?:       { id: number; nombre: string } | null;
    venta_item?:     { id: number; producto_nombre: string; unidad_nombre: string } | null;
}

interface PagoRow {
    id: number; monto: string; referencia: string | null;
    metodo_pago?: { id: number; nombre: string } | null;
}

interface DevolucionRow {
    id:                     number;
    numero:                 string;
    fecha:                  string;
    estado:                 'pendiente' | 'aprobada' | 'completada' | 'rechazada' | 'anulada';
    forma_reembolso:        string;
    monto_devolucion:       string;
    monto_reembolso:        string;
    observacion:            string | null;
    fecha_aprobacion:       string | null;
    observacion_aprobacion: string | null;
    user?:                  Pick<User, 'id' | 'name'>;
    user_aprobacion?:       Pick<User, 'id' | 'name'> | null;
    motivo?:                { id: number; nombre: string } | null;
    local?:                 Pick<Local, 'id' | 'nombre'>;
    venta?:                 { id: number; numero: string; total: string; cliente?: { id: number; nombres: string; apellidos: string | null; razon_social: string | null } | null } | null;
    detalles?:              DetalleRow[];
    pagos?:                 PagoRow[];
}

interface Kpis {
    total_devoluciones: number;
    monto_devuelto:     number;
    monto_reembolsado:  number;
    pendientes:         number;
    completadas:        number;
    rechazadas:         number;
    anuladas:           number;
    ventas_rango:       number;
    tasa:               number | null;
}

interface SerieDia  { dia: string; devoluciones: number; monto: number; }
interface PorMotivo { motivo_id: number | null; nombre: string; count: number; total: number; }
interface PorEstado { estado: string; count: number; total: number; }
interface PorForma  { forma: string; count: number; total: number; }

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    estado?: string; motivo_id?: string; forma?: string; local_id?: string; user_id?: string;
}

interface Props extends PageProps {
    devoluciones: Paginado<DevolucionRow>;
    kpis:         Kpis;
    serie_diaria: SerieDia[];
    por_motivo:   PorMotivo[];
    por_estado:   PorEstado[];
    por_forma:    PorForma[];
    motivos:      { id: number; nombre: string }[];
    locales:      Local[];
    usuarios:     Pick<User, 'id' | 'name'>[];
    filters:      Filters;
}

const ESTADO_COLOR: Record<string, string> = {
    pendiente: '#FFB800',
    aprobada:  'var(--color-primary)',
    completada:'var(--color-success)',
    rechazada: 'var(--color-danger)',
    anulada:   '#64748b',
};
const ESTADO_BADGE: Record<string, 'success' | 'danger' | 'warning' | 'primary' | 'secondary'> = {
    pendiente: 'warning', aprobada: 'primary', completada: 'success', rechazada: 'danger', anulada: 'secondary',
};
const FORMA_LABEL: Record<string, string> = {
    efectivo:        'Efectivo',
    mismo_metodo:    'Mismo método',
    vale_credito:    'Vale de crédito',
    cambio_producto: 'Cambio de producto',
    sin_reembolso:   'Sin reembolso',
};
const ESTADO_PRODUCTO_COLOR: Record<string, string> = {
    bueno: 'var(--color-success)', defectuoso: 'var(--color-danger)',
    'dañado': 'var(--color-danger)', vencido: '#FFB800',
};

const nombreCliente = (d: DevolucionRow) =>
    d.venta?.cliente?.razon_social
        ?? (d.venta?.cliente ? `${d.venta.cliente.nombres} ${d.venta.cliente.apellidos ?? ''}`.trim() : '—');

export default function ReportesDevoluciones({
    devoluciones, kpis, serie_diaria, por_motivo, por_estado, por_forma,
    motivos, locales, usuarios, filters, flash,
}: Props) {
    const [abiertas, setAbiertas] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.devoluciones'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.devoluciones'), {}, { preserveState: true, replace: true });

    const tieneFiltros = !!(filters.estado || filters.motivo_id || filters.forma || filters.local_id || filters.user_id);

    function toggle(id: number) {
        setAbiertas(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Reporte de devoluciones">
            <PageHeader
                icon={<Undo2 size={22} />}
                title="Reporte de devoluciones"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${fmtInt(kpis.total_devoluciones)} devoluciones`}
            />

            {/* Filtros */}
            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                <FieldSelect label="Estado" value={filters.estado ?? ''}
                    onChange={v => filtrar({ estado: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'pendiente', label: 'Pendientes' },
                        { value: 'aprobada', label: 'Aprobadas' },
                        { value: 'completada', label: 'Completadas' },
                        { value: 'rechazada', label: 'Rechazadas' },
                        { value: 'anulada', label: 'Anuladas' },
                    ]} />
                <FieldSelect label="Motivo" value={filters.motivo_id ?? ''}
                    onChange={v => filtrar({ motivo_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...motivos.map(m => ({ value: String(m.id), label: m.nombre }))]} />
                <FieldSelect label="Reembolso" value={filters.forma ?? ''}
                    onChange={v => filtrar({ forma: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...Object.entries(FORMA_LABEL).map(([v, l]) => ({ value: v, label: l }))]} />
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
                <FieldSelect label="Usuario" value={filters.user_id ?? ''}
                    onChange={v => filtrar({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
            </FiltrosReporte>

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<Undo2 size={18} />} label="Devoluciones" value={fmtInt(kpis.total_devoluciones)}
                    sub={`${fmtInt(kpis.completadas)} completadas · ${fmtInt(kpis.rechazadas)} rechazadas`}
                    color="var(--color-primary)" />
                <Kpi icon={<ListChecks size={18} />} label="Monto devuelto" value={fmtS(kpis.monto_devuelto)}
                    sub="aprobadas + completadas"
                    color="var(--color-danger)" />
                <Kpi icon={<HandCoins size={18} />} label="Reembolsado" value={fmtS(kpis.monto_reembolsado)}
                    color="#8b5cf6" />
                <Kpi icon={<AlertCircle size={18} />} label="Pendientes" value={fmtInt(kpis.pendientes)}
                    sub={kpis.pendientes > 0 ? 'requieren atención' : 'todo al día'}
                    subColor={kpis.pendientes > 0 ? 'var(--color-warning)' : 'var(--color-success)'}
                    color={kpis.pendientes > 0 ? 'var(--color-warning)' : 'var(--color-success)'} />
                <Kpi icon={<Percent size={18} />} label="Tasa de devolución"
                    value={kpis.tasa !== null ? `${kpis.tasa}%` : '—'}
                    sub={`sobre ${fmtS(kpis.ventas_rango)} vendidos`}
                    color={kpis.tasa !== null && kpis.tasa > 5 ? 'var(--color-danger)' : 'var(--color-success)'} />
            </div>

            {/* Serie + motivo */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<Calendar size={14} />} title="Devoluciones por día">
                    <AreaChart
                        data={serieChart}
                        series={[{ key: 'monto', label: 'Monto devuelto', color: 'var(--color-danger)', relleno: true }]}
                        height={220}
                    />
                </ReportCard>
                <ReportCard icon={<Tags size={14} />} title="Por motivo" accent="var(--color-danger)">
                    <DonutChart
                        data={por_motivo.map(m => ({ label: `${m.nombre} (${m.count})`, valor: m.total }))}
                        centro={{ valor: fmtS(por_motivo.reduce((s, m) => s + m.total, 0)), label: 'devuelto' }}
                        vertical
                    />
                </ReportCard>
            </div>

            {/* Estado + forma de reembolso */}
            <div className="grid md:grid-cols-2 gap-4 mb-4">
                <ReportCard icon={<CheckCircle2 size={14} />} title="Por estado" accent="var(--color-warning)">
                    <BarList
                        money={false}
                        data={por_estado.map(e => ({
                            label: e.estado.charAt(0).toUpperCase() + e.estado.slice(1),
                            valor: e.count,
                            extra: fmtS(e.total),
                            color: ESTADO_COLOR[e.estado] ?? 'var(--color-primary)',
                        }))}
                    />
                </ReportCard>
                <ReportCard icon={<HandCoins size={14} />} title="Por forma de reembolso" accent="#8b5cf6">
                    <BarList
                        money={false}
                        data={por_forma.map(f => ({
                            label: FORMA_LABEL[f.forma] ?? f.forma,
                            valor: f.count,
                            extra: fmtS(f.total),
                        }))}
                        multicolor
                    />
                </ReportCard>
            </div>

            {/* Listado con detalle expandible */}
            <ReportCard icon={<Undo2 size={14} />} title="Detalle de devoluciones" badge={fmtInt(devoluciones.total)} sinPadding>
                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={theadStyle}>
                                <Th className="w-8" />
                                <Th>Fecha</Th><Th>Número</Th><Th>Venta</Th><Th>Cliente</Th>
                                <Th>Motivo</Th><Th>Reembolso</Th><Th>Usuario</Th><Th>Estado</Th>
                                <Th right>Devuelto</Th><Th right>Reembolsado</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {devoluciones.data.map((d, idx) => (
                                <Fragment key={d.id}>
                                    <tr onClick={() => toggle(d.id)}
                                        className="cursor-pointer transition-colors hover:bg-black/[0.03]"
                                        style={zebra(idx)}>
                                        <td className="pl-3 py-2.5" style={{ color: 'var(--color-primary)' }}>
                                            {abiertas.has(d.id) ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                        </td>
                                        <td className="px-3 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fechaHora(d.fecha)}</td>
                                        <td className="px-3 py-2.5 font-mono text-xs font-semibold" style={{ color: 'var(--color-text)' }}>{d.numero}</td>
                                        <td className="px-3 py-2.5">
                                            {d.venta ? (
                                                <Link href={route('ventas.show', d.venta.id)} onClick={e => e.stopPropagation()}
                                                    className="font-mono text-xs font-semibold hover:underline"
                                                    style={{ color: 'var(--color-primary)' }}>
                                                    {d.venta.numero}
                                                </Link>
                                            ) : '—'}
                                        </td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{nombreCliente(d)}</td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{d.motivo?.nombre ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>{FORMA_LABEL[d.forma_reembolso] ?? d.forma_reembolso}</td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{d.user?.name ?? '—'}</td>
                                        <td className="px-3 py-2.5"><Badge variant={ESTADO_BADGE[d.estado] ?? 'secondary'}>{d.estado}</Badge></td>
                                        <td className="px-3 py-2.5 text-right font-bold text-xs whitespace-nowrap" style={{ color: 'var(--color-danger)' }}>
                                            {fmtS(parseFloat(d.monto_devolucion))}
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                            {fmtS(parseFloat(d.monto_reembolso))}
                                        </td>
                                    </tr>
                                    {abiertas.has(d.id) && <DetalleDevolucion devolucion={d} colSpan={11} />}
                                </Fragment>
                            ))}
                            {devoluciones.data.length === 0 && (
                                <tr>
                                    <td colSpan={11} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <Undo2 size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron devoluciones</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 p-3">
                    {devoluciones.data.map(d => (
                        <div key={d.id} className="rounded-xl p-3"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                border: '1px solid var(--color-border)',
                            }}
                            onClick={() => toggle(d.id)}>
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="font-mono text-sm font-semibold" style={{ color: 'var(--color-primary)' }}>{d.numero}</p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{fechaHora(d.fecha)} · {d.motivo?.nombre ?? '—'}</p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text)' }}>{nombreCliente(d)}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-sm" style={{ color: 'var(--color-danger)' }}>{fmtS(parseFloat(d.monto_devolucion))}</p>
                                    <Badge variant={ESTADO_BADGE[d.estado] ?? 'secondary'}>{d.estado}</Badge>
                                </div>
                            </div>
                            {abiertas.has(d.id) && (
                                <div className="mt-2 pt-2 space-y-1" style={{ borderTop: '1px dashed var(--color-border)' }}>
                                    {(d.detalles ?? []).map(det => (
                                        <div key={det.id} className="flex justify-between text-[11px]">
                                            <span style={{ color: 'var(--color-text)' }}>
                                                {fmtCant(parseFloat(det.cantidad))} × {det.venta_item?.producto_nombre ?? det.producto?.nombre ?? '—'}
                                            </span>
                                            <span className="font-semibold" style={{ color: 'var(--color-text)' }}>{fmtS(parseFloat(det.subtotal))}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                    {devoluciones.data.length === 0 && <Empty text="No se encontraron devoluciones" />}
                </div>

                <Paginacion paginado={devoluciones} ruta="reportes.devoluciones" filters={filters as unknown as Record<string, unknown>} />
            </ReportCard>
        </AppLayout>
    );
}

/* ── Fila expandida: productos devueltos + aprobación + pagos ──────────── */
function DetalleDevolucion({ devolucion: d, colSpan }: { devolucion: DevolucionRow; colSpan: number }) {
    const detalles = d.detalles ?? [];
    const pagos    = d.pagos ?? [];
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-3"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-danger) 4%, var(--color-surface))',
                    borderTop: '1px dashed var(--color-border)',
                }}>
                <div className="grid md:grid-cols-[1fr_280px] gap-4">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                            Productos devueltos ({detalles.length})
                        </p>
                        <table className="w-full text-[11px]">
                            <thead>
                                <tr style={{ color: 'var(--color-text-muted)' }}>
                                    <th className="text-left py-1 font-semibold">Producto</th>
                                    <th className="text-right py-1 font-semibold">Cant.</th>
                                    <th className="text-right py-1 font-semibold">P.U.</th>
                                    <th className="text-right py-1 font-semibold">Subtotal</th>
                                    <th className="text-center py-1 font-semibold">Condición</th>
                                    <th className="text-center py-1 font-semibold">Restock</th>
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map(det => (
                                    <tr key={det.id} style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                        <td className="py-1.5" style={{ color: 'var(--color-text)' }}>
                                            {det.venta_item?.producto_nombre ?? det.producto?.nombre ?? '—'}
                                            {det.venta_item?.unidad_nombre && (
                                                <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>({det.venta_item.unidad_nombre})</span>
                                            )}
                                        </td>
                                        <td className="py-1.5 text-right" style={{ color: 'var(--color-text)' }}>{fmtCant(parseFloat(det.cantidad))}</td>
                                        <td className="py-1.5 text-right" style={{ color: 'var(--color-text)' }}>{fmtS(parseFloat(det.precio_unitario))}</td>
                                        <td className="py-1.5 text-right font-semibold" style={{ color: 'var(--color-text)' }}>{fmtS(parseFloat(det.subtotal))}</td>
                                        <td className="py-1.5 text-center">
                                            {det.estado_producto ? (
                                                <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                                    style={{
                                                        color: ESTADO_PRODUCTO_COLOR[det.estado_producto] ?? 'var(--color-text-muted)',
                                                        backgroundColor: `color-mix(in srgb, ${ESTADO_PRODUCTO_COLOR[det.estado_producto] ?? 'var(--color-text-muted)'} 12%, transparent)`,
                                                    }}>
                                                    {det.estado_producto}
                                                </span>
                                            ) : '—'}
                                        </td>
                                        <td className="py-1.5 text-center">
                                            {det.restock
                                                ? <PackageCheck size={13} className="inline" style={{ color: 'var(--color-success)' }} />
                                                : <PackageX size={13} className="inline" style={{ color: 'var(--color-text-muted)' }} />}
                                        </td>
                                    </tr>
                                ))}
                                {detalles.length === 0 && (
                                    <tr><td colSpan={6} className="py-2 text-center" style={{ color: 'var(--color-text-muted)' }}>Sin detalle registrado</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="space-y-2">
                        {(d.user_aprobacion || d.fecha_aprobacion) && (
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider mb-1" style={{ color: 'var(--vp-navy)' }}>Aprobación</p>
                                <p className="text-[11px]" style={{ color: 'var(--color-text)' }}>
                                    {d.user_aprobacion?.name ?? '—'}
                                    {d.fecha_aprobacion && <span style={{ color: 'var(--color-text-muted)' }}> · {fechaHora(d.fecha_aprobacion)}</span>}
                                </p>
                                {d.observacion_aprobacion && (
                                    <p className="text-[11px] italic mt-0.5" style={{ color: 'var(--color-text-muted)' }}>“{d.observacion_aprobacion}”</p>
                                )}
                            </div>
                        )}
                        {pagos.length > 0 && (
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider mb-1" style={{ color: 'var(--vp-navy)' }}>Reembolsos</p>
                                <div className="space-y-1">
                                    {pagos.map(p => (
                                        <div key={p.id} className="flex items-center justify-between text-[11px] rounded-lg px-2 py-1.5"
                                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 7%, transparent)' }}>
                                            <span style={{ color: 'var(--color-text)' }}>
                                                {p.metodo_pago?.nombre ?? '—'}
                                                {p.referencia && <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>· {p.referencia}</span>}
                                            </span>
                                            <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{fmtS(parseFloat(p.monto))}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        {d.observacion && (
                            <p className="text-[11px] italic" style={{ color: 'var(--color-text-muted)' }}>“{d.observacion}”</p>
                        )}
                    </div>
                </div>
            </td>
        </tr>
    );
}
