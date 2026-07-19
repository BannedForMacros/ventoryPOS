import { Fragment, useEffect, useState } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    ShoppingCart, TrendingUp, Receipt, Percent, CreditCard,
    Package, Calendar, Users, Clock, UserRound, ChevronDown, ChevronUp,
    FileText, Search, X, Banknote,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import { AreaChart, DonutChart, BarList, CHART_COLORS } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Empty, Th,
    theadStyle, zebra, fmtS, fmtInt, fmtCant, diaLabel, fechaHora, fieldStyle,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Local, MetodoPago, User, Venta, PageProps } from '@/types';

interface Kpis {
    total_ventas:       number;
    total_anuladas:     number;
    monto_anuladas:     number;
    monto_total:        number;
    monto_descuento:    number;
    monto_igv:          number;
    monto_contado:      number;
    monto_credito:      number;
    credito_pendiente:  number;
    clientes_distintos: number;
    ticket_promedio:    number;
    prev_monto:         number;
    prev_ventas:        number;
    variacion:          number | null;
}

interface SerieDia   { dia: string; total: number; ventas: number; descuento: number; }
interface PorHora    { hora: number; total: number; ventas: number; }
interface PorMetodo  { metodo_pago_id: number; nombre: string; total: number; ocurrencias: number; }
interface PorVendedor{ user_id: number; nombre: string; total: number; ventas: number; }
interface PorComprob { tipo: string; total: number; ventas: number; }
interface TopProducto{ producto_id: number; producto_nombre: string; cantidad: number; total: number; }
interface TopCliente { cliente_id: number; nombre: string; total: number; ventas: number; }

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    estado?: string; local_id?: string; user_id?: string;
    metodo_pago_id?: string; tipo?: string; comprobante?: string; buscar?: string;
}

interface Props extends PageProps {
    ventas:          Paginado<Venta>;
    kpis:            Kpis;
    serie_diaria:    SerieDia[];
    por_hora:        PorHora[];
    por_metodo:      PorMetodo[];
    por_vendedor:    PorVendedor[];
    por_comprobante: PorComprob[];
    top_productos:   TopProducto[];
    top_clientes:    TopCliente[];
    locales:         Local[];
    usuarios:        Pick<User, 'id' | 'name'>[];
    metodos_pago:    MetodoPago[];
    rango_anterior:  { desde: string; hasta: string };
    filters:         Filters;
}

const COMPROBANTE_LABEL: Record<string, string> = { ticket: 'Ticket', boleta: 'Boleta', factura: 'Factura' };

const nombreCliente = (v: Venta) =>
    (v.cliente as any)?.razon_social
        ?? (v.cliente ? `${(v.cliente as any).nombres} ${(v.cliente as any).apellidos ?? ''}`.trim() : '—');

export default function ReportesVentas({
    ventas, kpis, serie_diaria, por_hora, por_metodo, por_vendedor, por_comprobante,
    top_productos, top_clientes, locales, usuarios, metodos_pago, rango_anterior, filters, flash,
}: Props) {
    const [abiertas, setAbiertas] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.ventas'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.ventas'), {}, { preserveState: true, replace: true });

    const tieneFiltros = !!(filters.estado || filters.local_id || filters.user_id
        || filters.metodo_pago_id || filters.tipo || filters.comprobante || filters.buscar);

    function toggle(id: number) {
        setAbiertas(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));
    const totalTop = top_productos.reduce((s, p) => s + p.total, 0);

    return (
        <AppLayout title="Reporte de ventas">
            <PageHeader
                icon={<ShoppingCart size={22} />}
                title="Reporte de ventas"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${fmtInt(kpis.total_ventas)} ventas completadas`}
            />

            {/* Filtros */}
            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                <FieldSelect label="Estado" value={filters.estado ?? ''}
                    onChange={v => filtrar({ estado: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'completada', label: 'Completadas' },
                        { value: 'anulada', label: 'Anuladas' },
                    ]} />
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
                <FieldSelect label="Vendedor" value={filters.user_id ?? ''}
                    onChange={v => filtrar({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
                <FieldSelect label="Método pago" value={filters.metodo_pago_id ?? ''}
                    onChange={v => filtrar({ metodo_pago_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...metodos_pago.map(m => ({ value: String(m.id), label: m.nombre as string }))]} />
                <FieldSelect label="Tipo" value={filters.tipo ?? ''}
                    onChange={v => filtrar({ tipo: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'contado', label: 'Contado' },
                        { value: 'credito', label: 'Crédito' },
                    ]} />
                <FieldSelect label="Comprobante" value={filters.comprobante ?? ''}
                    onChange={v => filtrar({ comprobante: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'ticket', label: 'Ticket' },
                        { value: 'boleta', label: 'Boleta' },
                        { value: 'factura', label: 'Factura' },
                    ]} />
                <div className="col-span-2 sm:col-span-1">
                    <label className="block text-[10px] font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--color-text-muted)' }}>Buscar</label>
                    <div className="relative">
                        <Search size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input type="search" defaultValue={filters.buscar ?? ''} placeholder="N° o cliente…"
                            onKeyDown={e => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                            className="w-full text-sm rounded-lg pl-7 pr-6 py-1.5 border outline-none"
                            style={fieldStyle} />
                        {filters.buscar && (
                            <button onClick={() => filtrar({ buscar: undefined })}
                                className="absolute right-2 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }}>
                                <X size={11} />
                            </button>
                        )}
                    </div>
                </div>
            </FiltrosReporte>

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<TrendingUp size={18} />} label="Total vendido" value={fmtS(kpis.monto_total)}
                    sub={kpis.variacion !== null
                        ? `${kpis.variacion >= 0 ? '▲' : '▼'} ${Math.abs(kpis.variacion)}% vs período anterior`
                        : `período anterior: ${fmtS(kpis.prev_monto)}`}
                    subColor={kpis.variacion !== null ? (kpis.variacion >= 0 ? 'var(--color-success)' : 'var(--color-danger)') : undefined}
                    color="var(--color-success)" />
                <Kpi icon={<ShoppingCart size={18} />} label="Ventas" value={fmtInt(kpis.total_ventas)}
                    sub={kpis.total_anuladas > 0 ? `${kpis.total_anuladas} anuladas (${fmtS(kpis.monto_anuladas)})` : 'sin anulaciones'}
                    subColor={kpis.total_anuladas > 0 ? 'var(--color-danger)' : undefined}
                    color="var(--color-primary)" />
                <Kpi icon={<Receipt size={18} />} label="Ticket promedio" value={fmtS(kpis.ticket_promedio)}
                    sub={`${fmtInt(kpis.clientes_distintos)} clientes distintos`}
                    color="#8b5cf6" />
                <Kpi icon={<Banknote size={18} />} label="Contado / Crédito"
                    value={fmtS(kpis.monto_contado)}
                    sub={kpis.monto_credito > 0
                        ? `crédito ${fmtS(kpis.monto_credito)} · por cobrar ${fmtS(kpis.credito_pendiente)}`
                        : 'sin ventas a crédito'}
                    subColor={kpis.credito_pendiente > 0 ? 'var(--color-warning)' : undefined}
                    color="var(--vp-navy)" />
                <Kpi icon={<Percent size={18} />} label="Descuentos" value={fmtS(kpis.monto_descuento)}
                    sub={`IGV: ${fmtS(kpis.monto_igv)}`}
                    color="var(--color-danger)" />
            </div>

            {/* Serie diaria + métodos de pago */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<Calendar size={14} />} title="Ventas por día"
                    badge={`vs ${rango_anterior.desde} → ${rango_anterior.hasta}`}>
                    <AreaChart
                        data={serieChart}
                        series={[{ key: 'total', label: 'Vendido', relleno: true }]}
                        height={230}
                    />
                </ReportCard>
                <ReportCard icon={<CreditCard size={14} />} title="Por método de pago" accent="#8b5cf6">
                    <DonutChart
                        data={por_metodo.map(m => ({ label: m.nombre, valor: m.total }))}
                        centro={{ valor: fmtS(por_metodo.reduce((s, m) => s + m.total, 0)), label: 'cobrado' }}
                        vertical
                    />
                </ReportCard>
            </div>

            {/* Vendedores + comprobantes + horas */}
            <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
                <ReportCard icon={<UserRound size={14} />} title="Por vendedor" accent="var(--color-success)">
                    <BarList
                        data={por_vendedor.map(v => ({ label: v.nombre, valor: v.total, extra: `${v.ventas} ventas` }))}
                        multicolor
                    />
                </ReportCard>
                <ReportCard icon={<FileText size={14} />} title="Por comprobante" accent="var(--color-warning)">
                    <DonutChart
                        data={por_comprobante.map((c, i) => ({
                            label: `${COMPROBANTE_LABEL[c.tipo] ?? c.tipo} (${c.ventas})`,
                            valor: c.total,
                            color: CHART_COLORS[i % CHART_COLORS.length],
                        }))}
                        centro={{ valor: fmtInt(kpis.total_ventas), label: 'comprobantes' }}
                        vertical
                    />
                </ReportCard>
                <ReportCard className="md:col-span-2 xl:col-span-1" icon={<Clock size={14} />} title="Ventas por hora" accent="#06b6d4">
                    <PorHoraChart data={por_hora} />
                </ReportCard>
            </div>

            {/* Top productos + top clientes */}
            <div className="grid lg:grid-cols-2 gap-4 mb-4">
                <ReportCard icon={<Package size={14} />} title="Top productos" badge={`${top_productos.length}`} sinPadding>
                    {top_productos.length === 0 ? <Empty text="Sin productos vendidos" /> : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={theadStyle}>
                                        <Th>#</Th><Th>Producto</Th><Th right>Cant.</Th><Th right>Total</Th><Th right>Part.</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_productos.map((p, i) => (
                                        <tr key={p.producto_id} style={zebra(i)}>
                                            <td className="px-3 py-2" style={{ color: 'var(--color-text-muted)' }}>{i + 1}</td>
                                            <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</td>
                                            <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>{fmtCant(p.cantidad)}</td>
                                            <td className="px-3 py-2 text-right font-bold" style={{ color: 'var(--color-success)' }}>{fmtS(p.total)}</td>
                                            <td className="px-3 py-2 text-right">
                                                <span className="font-bold px-1.5 py-0.5 rounded"
                                                    style={{
                                                        color: 'var(--color-primary)',
                                                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                                                    }}>
                                                    {totalTop > 0 ? Math.round((p.total / totalTop) * 100) : 0}%
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ReportCard>
                <ReportCard icon={<Users size={14} />} title="Top clientes" accent="var(--vp-navy)">
                    <BarList
                        data={top_clientes.map(c => ({ label: c.nombre, valor: c.total, extra: `${c.ventas} compras` }))}
                    />
                </ReportCard>
            </div>

            {/* Listado con detalle expandible */}
            <ReportCard icon={<Receipt size={14} />} title="Detalle de ventas" badge={fmtInt(ventas.total)} sinPadding>
                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={theadStyle}>
                                <Th className="w-8" />
                                <Th>Fecha</Th><Th>Número</Th><Th>Cliente</Th><Th>Vendedor</Th>
                                <Th>Comprobante</Th><Th>Tipo</Th><Th>Estado</Th><Th right>Total</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {ventas.data.map((v, idx) => (
                                <Fragment key={v.id}>
                                    <tr onClick={() => toggle(v.id)}
                                        className="cursor-pointer transition-colors hover:bg-black/[0.03]"
                                        style={zebra(idx)}>
                                        <td className="pl-3 py-2.5" style={{ color: 'var(--color-primary)' }}>
                                            {abiertas.has(v.id) ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                        </td>
                                        <td className="px-3 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                            {fechaHora(v.fecha_venta)}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Link href={route('ventas.show', v.id)} onClick={e => e.stopPropagation()}
                                                className="font-mono text-xs font-semibold hover:underline"
                                                style={{ color: 'var(--color-primary)' }}>
                                                {v.numero}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{nombreCliente(v)}</td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{(v.user as any)?.name ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                            {COMPROBANTE_LABEL[v.tipo_comprobante] ?? v.tipo_comprobante}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {v.es_credito ? (
                                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                    style={{
                                                        color: 'var(--color-warning)',
                                                        backgroundColor: 'color-mix(in srgb, var(--color-warning) 14%, transparent)',
                                                    }}>
                                                    Crédito{parseFloat(String(v.saldo_pendiente ?? 0)) > 0 ? ` · debe ${fmtS(parseFloat(String(v.saldo_pendiente)))}` : ''}
                                                </span>
                                            ) : (
                                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                    style={{
                                                        color: 'var(--color-success)',
                                                        backgroundColor: 'color-mix(in srgb, var(--color-success) 12%, transparent)',
                                                    }}>
                                                    Contado
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>{v.estado}</Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-bold text-sm whitespace-nowrap"
                                            style={{ color: v.estado === 'anulada' ? 'var(--color-text-muted)' : 'var(--color-success)' }}>
                                            {fmtS(parseFloat(v.total))}
                                        </td>
                                    </tr>
                                    {abiertas.has(v.id) && <DetalleVenta venta={v} colSpan={9} />}
                                </Fragment>
                            ))}
                            {ventas.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <ShoppingCart size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron ventas</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 p-3">
                    {ventas.data.map(v => (
                        <div key={v.id} className="rounded-xl p-3"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                border: '1px solid var(--color-border)',
                            }}
                            onClick={() => toggle(v.id)}>
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <Link href={route('ventas.show', v.id)} onClick={e => e.stopPropagation()}
                                        className="font-mono text-sm font-semibold" style={{ color: 'var(--color-primary)' }}>
                                        {v.numero}
                                    </Link>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{fechaHora(v.fecha_venta)}</p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text)' }}>{nombreCliente(v)}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-sm" style={{ color: v.estado === 'anulada' ? 'var(--color-text-muted)' : 'var(--color-success)' }}>
                                        {fmtS(parseFloat(v.total))}
                                    </p>
                                    <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>{v.estado}</Badge>
                                </div>
                            </div>
                            {abiertas.has(v.id) && (
                                <div className="mt-2 pt-2 space-y-1" style={{ borderTop: '1px dashed var(--color-border)' }}>
                                    {((v.items as any[]) ?? []).map(it => (
                                        <div key={it.id} className="flex justify-between text-[11px]">
                                            <span style={{ color: 'var(--color-text)' }}>{fmtCant(parseFloat(it.cantidad))} × {it.producto_nombre}</span>
                                            <span className="font-semibold" style={{ color: 'var(--color-text)' }}>{fmtS(parseFloat(it.subtotal))}</span>
                                        </div>
                                    ))}
                                    <div className="flex flex-wrap gap-1 pt-1">
                                        {((v.pagos as any[]) ?? []).map(p => (
                                            <span key={p.id} className="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                                style={{ color: 'var(--color-primary)', backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)' }}>
                                                {p.metodo_pago?.nombre ?? '—'}: {fmtS(parseFloat(p.monto))}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                <Paginacion paginado={ventas} ruta="reportes.ventas" filters={filters as unknown as Record<string, unknown>} />
            </ReportCard>
        </AppLayout>
    );
}

/* ── Fila expandida: items + pagos de la venta ─────────────────────────── */
function DetalleVenta({ venta, colSpan }: { venta: Venta; colSpan: number }) {
    const items = (venta.items as any[]) ?? [];
    const pagos = (venta.pagos as any[]) ?? [];
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-3"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 4%, var(--color-surface))',
                    borderTop: '1px dashed var(--color-border)',
                }}>
                <div className="grid md:grid-cols-[1fr_260px] gap-4">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                            Productos ({items.length})
                        </p>
                        <table className="w-full text-[11px]">
                            <thead>
                                <tr style={{ color: 'var(--color-text-muted)' }}>
                                    <th className="text-left py-1 font-semibold">Producto</th>
                                    <th className="text-right py-1 font-semibold">Cant.</th>
                                    <th className="text-right py-1 font-semibold">P.U.</th>
                                    <th className="text-right py-1 font-semibold">Dscto.</th>
                                    <th className="text-right py-1 font-semibold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map(it => (
                                    <tr key={it.id} style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                        <td className="py-1.5" style={{ color: 'var(--color-text)' }}>
                                            {it.producto_nombre}
                                            <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>({it.unidad_nombre})</span>
                                        </td>
                                        <td className="py-1.5 text-right" style={{ color: 'var(--color-text)' }}>{fmtCant(parseFloat(it.cantidad))}</td>
                                        <td className="py-1.5 text-right" style={{ color: 'var(--color-text)' }}>{fmtS(parseFloat(it.precio_unitario))}</td>
                                        <td className="py-1.5 text-right" style={{ color: parseFloat(it.descuento_item) > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                            {parseFloat(it.descuento_item) > 0 ? `-${fmtS(parseFloat(it.descuento_item))}` : '—'}
                                        </td>
                                        <td className="py-1.5 text-right font-semibold" style={{ color: 'var(--color-text)' }}>{fmtS(parseFloat(it.subtotal))}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>Pagos</p>
                        <div className="space-y-1">
                            {pagos.length === 0 && (
                                <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                    {venta.es_credito ? 'Venta a crédito sin pago inicial' : 'Sin pagos registrados'}
                                </p>
                            )}
                            {pagos.map(p => (
                                <div key={p.id} className="flex items-center justify-between text-[11px] rounded-lg px-2 py-1.5"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-success) 7%, transparent)' }}>
                                    <span style={{ color: 'var(--color-text)' }}>
                                        {p.metodo_pago?.nombre ?? '—'}
                                        {p.referencia && <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>· {p.referencia}</span>}
                                    </span>
                                    <span className="font-bold" style={{ color: 'var(--color-success)' }}>{fmtS(parseFloat(p.monto))}</span>
                                </div>
                            ))}
                            {venta.es_credito && parseFloat(String(venta.saldo_pendiente ?? 0)) > 0 && (
                                <div className="flex items-center justify-between text-[11px] rounded-lg px-2 py-1.5"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, transparent)' }}>
                                    <span style={{ color: 'var(--color-text)' }}>Saldo por cobrar</span>
                                    <span className="font-bold" style={{ color: 'var(--color-warning)' }}>{fmtS(parseFloat(String(venta.saldo_pendiente)))}</span>
                                </div>
                            )}
                        </div>
                        {venta.observacion && (
                            <p className="text-[11px] mt-2 italic" style={{ color: 'var(--color-text-muted)' }}>“{venta.observacion}”</p>
                        )}
                    </div>
                </div>
            </td>
        </tr>
    );
}

/* ── Mini gráfico de barras verticales por hora ────────────────────────── */
function PorHoraChart({ data }: { data: PorHora[] }) {
    const [hover, setHover] = useState<number | null>(null);
    if (data.length === 0) return <Empty />;
    const max = Math.max(1, ...data.map(d => d.total));
    // Rango continuo entre la primera y última hora con ventas
    const horas: PorHora[] = [];
    const desde = Math.min(...data.map(d => d.hora));
    const hasta = Math.max(...data.map(d => d.hora));
    for (let h = desde; h <= hasta; h++) {
        horas.push(data.find(d => d.hora === h) ?? { hora: h, total: 0, ventas: 0 });
    }
    return (
        <div>
            <div className="flex items-end gap-1 h-32">
                {horas.map((h, i) => (
                    <div key={h.hora} className="flex-1 flex flex-col items-center h-full justify-end"
                        onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                        <div className="w-full rounded-t transition-all"
                            style={{
                                height: `${Math.max(2, (h.total / max) * 100)}%`,
                                background: hover === i
                                    ? 'var(--color-primary-hover)'
                                    : 'linear-gradient(180deg, var(--color-primary) 0%, color-mix(in srgb, var(--color-primary) 55%, #fff) 100%)',
                                opacity: h.total === 0 ? 0.15 : 1,
                            }} />
                    </div>
                ))}
            </div>
            <div className="flex gap-1 mt-1">
                {horas.map(h => (
                    <span key={h.hora} className="flex-1 text-center text-[9px]" style={{ color: 'var(--color-text-muted)' }}>
                        {h.hora}
                    </span>
                ))}
            </div>
            <p className="text-center text-[11px] mt-2 h-4 font-medium" style={{ color: 'var(--color-text)' }}>
                {hover !== null && horas[hover].total > 0
                    ? `${horas[hover].hora}:00 — ${fmtS(horas[hover].total)} (${horas[hover].ventas} ventas)`
                    : ''}
            </p>
        </div>
    );
}
