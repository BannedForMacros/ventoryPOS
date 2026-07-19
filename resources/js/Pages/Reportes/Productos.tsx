import { Fragment, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Package, Boxes, Coins, Percent, Star, Layers, Search, X,
    ChevronDown, ChevronUp, Trophy,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import { DonutChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Empty, Th,
    theadStyle, zebra, fmtS, fmtInt, fmtCant, fieldStyle,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Categoria, Local, PageProps } from '@/types';

interface ProductoRow {
    producto_id:      number;
    producto_nombre:  string;
    cantidad_total:   number;
    monto_total:      number;
    descuento_total:  number;
    ventas_distintas: number;
    precio_promedio:  number;
    unidades:         string | null;
    categoria_id:     number | null;
    categoria_nombre: string | null;
}

interface PorCategoria { categoria: string; total: number; cantidad: number; }
interface TopProducto  { producto_id: number; producto_nombre: string; total: number; cantidad: number; }

interface Kpis {
    productos_distintos: number;
    cantidad_vendida:    number;
    monto_total:         number;
    descuento_total:     number;
    estrella:            TopProducto | null;
    categoria_lider:     PorCategoria | null;
}

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    local_id?: string; categoria_id?: string; buscar?: string; orden?: string | null;
}

interface Props extends PageProps {
    productos:     Paginado<ProductoRow>;
    kpis:          Kpis;
    por_categoria: PorCategoria[];
    top_productos: TopProducto[];
    categorias:    Pick<Categoria, 'id' | 'nombre'>[];
    locales:       Local[];
    filters:       Filters;
}

export default function ReportesProductos({
    productos, kpis, por_categoria, top_productos, categorias, locales, filters, flash,
}: Props) {
    const [abiertos, setAbiertos] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.productos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.productos'), {}, { preserveState: true, replace: true });

    const tieneFiltros = !!(filters.local_id || filters.categoria_id || filters.buscar || filters.orden);

    function toggle(id: number) {
        setAbiertos(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const rankBase = (productos.from ?? 1) - 1;

    return (
        <AppLayout title="Reporte de productos">
            <PageHeader
                icon={<Package size={22} />}
                title="Reporte de productos"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · qué se vende, cuánto y en qué categoría`}
            />

            {/* Filtros */}
            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
                <FieldSelect label="Categoría" value={filters.categoria_id ?? ''}
                    onChange={v => filtrar({ categoria_id: v || undefined })}
                    options={[{ value: '', label: 'Todas' }, ...categorias.map(c => ({ value: String(c.id), label: c.nombre }))]} />
            </FiltrosReporte>

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<Coins size={18} />} label="Total vendido" value={fmtS(kpis.monto_total)}
                    sub={`${fmtInt(kpis.productos_distintos)} productos distintos`}
                    color="var(--color-success)" />
                <Kpi icon={<Boxes size={18} />} label="Unidades vendidas" value={fmtCant(kpis.cantidad_vendida)}
                    color="var(--color-primary)" />
                <Kpi icon={<Percent size={18} />} label="Descuentos aplicados" value={fmtS(kpis.descuento_total)}
                    sub={kpis.monto_total > 0 ? `${((kpis.descuento_total / (kpis.monto_total + kpis.descuento_total)) * 100).toFixed(1)}% del precio original` : undefined}
                    color="var(--color-danger)" />
                <Kpi icon={<Star size={18} />} label="Producto estrella"
                    value={kpis.estrella?.producto_nombre ?? '—'}
                    sub={kpis.estrella ? `${fmtS(kpis.estrella.total)} · ${fmtCant(kpis.estrella.cantidad)} und` : 'sin ventas'}
                    color="var(--color-warning)" />
                <Kpi icon={<Layers size={18} />} label="Categoría líder"
                    value={kpis.categoria_lider?.categoria ?? '—'}
                    sub={kpis.categoria_lider ? fmtS(kpis.categoria_lider.total) : 'sin ventas'}
                    color="#8b5cf6" />
            </div>

            {/* Dona por categoría + top 10 */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard icon={<Layers size={14} />} title="Ventas por categoría" accent="#8b5cf6">
                    <DonutChart
                        data={por_categoria.map(c => ({ label: c.categoria, valor: c.total }))}
                        centro={{ valor: fmtS(por_categoria.reduce((s, c) => s + c.total, 0)), label: 'vendido' }}
                        vertical
                    />
                </ReportCard>
                <ReportCard className="lg:col-span-2" icon={<Trophy size={14} />} title="Top 10 productos" accent="var(--color-warning)">
                    <BarList
                        data={top_productos.map(p => ({
                            label: p.producto_nombre,
                            valor: p.total,
                            extra: `${fmtCant(p.cantidad)} und`,
                        }))}
                        multicolor
                    />
                </ReportCard>
            </div>

            {/* Ranking completo */}
            <ReportCard icon={<Package size={14} />} title="Ranking de productos" badge={fmtInt(productos.total)} sinPadding
                actions={
                    <>
                        <div className="relative">
                            <Search size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input type="search" defaultValue={filters.buscar ?? ''} placeholder="Buscar producto…"
                                onKeyDown={e => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                                className="text-xs rounded-lg pl-7 pr-6 py-1.5 border outline-none w-44"
                                style={fieldStyle} />
                            {filters.buscar && (
                                <button onClick={() => filtrar({ buscar: undefined })}
                                    className="absolute right-2 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }}>
                                    <X size={11} />
                                </button>
                            )}
                        </div>
                        <select value={filters.orden ?? ''} onChange={e => filtrar({ orden: e.target.value || undefined })}
                            className="text-xs rounded-lg px-2.5 py-1.5 border outline-none"
                            style={fieldStyle}>
                            <option value="">Más vendidos (S/)</option>
                            <option value="cantidad">Más vendidos (und)</option>
                            <option value="ventas">Más ventas distintas</option>
                            <option value="precio">Mayor precio prom.</option>
                            <option value="descuento">Más descontados</option>
                        </select>
                    </>
                }>
                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={theadStyle}>
                                <Th className="w-8" />
                                <Th>#</Th><Th>Producto</Th><Th>Categoría</Th>
                                <Th right>Cant.</Th><Th right>N° ventas</Th><Th right>P. promedio</Th>
                                <Th right>Descuento</Th><Th right>Total</Th><Th right>Part.</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {productos.data.map((p, i) => (
                                <Fragment key={p.producto_id}>
                                    <tr onClick={() => toggle(p.producto_id)}
                                        className="cursor-pointer transition-colors hover:bg-black/[0.03]"
                                        style={zebra(i)}>
                                        <td className="pl-3 py-2" style={{ color: 'var(--color-primary)' }}>
                                            {abiertos.has(p.producto_id) ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
                                        </td>
                                        <td className="px-3 py-2" style={{ color: 'var(--color-text-muted)' }}>{rankBase + i + 1}</td>
                                        <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</td>
                                        <td className="px-3 py-2" style={{ color: 'var(--color-text-muted)' }}>{p.categoria_nombre ?? 'Sin categoría'}</td>
                                        <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>{fmtCant(p.cantidad_total)}</td>
                                        <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>{fmtInt(p.ventas_distintas)}</td>
                                        <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>{fmtS(p.precio_promedio)}</td>
                                        <td className="px-3 py-2 text-right" style={{ color: p.descuento_total > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                            {p.descuento_total > 0 ? `-${fmtS(p.descuento_total)}` : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold" style={{ color: 'var(--color-success)' }}>{fmtS(p.monto_total)}</td>
                                        <td className="px-3 py-2 text-right">
                                            <span className="font-bold px-1.5 py-0.5 rounded"
                                                style={{
                                                    color: 'var(--color-primary)',
                                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                                                }}>
                                                {kpis.monto_total > 0 ? ((p.monto_total / kpis.monto_total) * 100).toFixed(1) : '0.0'}%
                                            </span>
                                        </td>
                                    </tr>
                                    {abiertos.has(p.producto_id) && (
                                        <tr>
                                            <td colSpan={10} className="px-6 py-3"
                                                style={{
                                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 4%, var(--color-surface))',
                                                    borderTop: '1px dashed var(--color-border)',
                                                }}>
                                                <div className="flex flex-wrap gap-x-8 gap-y-2 text-[11px]">
                                                    <Dato label="Unidades usadas" valor={p.unidades ?? '—'} />
                                                    <Dato label="Promedio por venta" valor={p.ventas_distintas > 0 ? fmtS(p.monto_total / p.ventas_distintas) : '—'} />
                                                    <Dato label="Cantidad prom. por venta" valor={p.ventas_distintas > 0 ? fmtCant(p.cantidad_total / p.ventas_distintas) : '—'} />
                                                    <Dato label="Descuento acumulado" valor={p.descuento_total > 0 ? `-${fmtS(p.descuento_total)}` : 'Sin descuentos'} />
                                                    <Dato label="Categoría" valor={p.categoria_nombre ?? 'Sin categoría'} />
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                            {productos.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <Package size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">Sin productos vendidos en el rango</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 p-3">
                    {productos.data.map((p, i) => (
                        <div key={p.producto_id} className="rounded-xl p-3"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                border: '1px solid var(--color-border)',
                            }}>
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                        <span style={{ color: 'var(--color-text-muted)' }}>#{rankBase + i + 1}</span> {p.producto_nombre}
                                    </p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        {p.categoria_nombre ?? 'Sin categoría'} · {fmtCant(p.cantidad_total)} und · {fmtInt(p.ventas_distintas)} ventas
                                    </p>
                                </div>
                                <p className="font-bold text-sm" style={{ color: 'var(--color-success)' }}>{fmtS(p.monto_total)}</p>
                            </div>
                        </div>
                    ))}
                    {productos.data.length === 0 && <Empty text="Sin productos vendidos en el rango" />}
                </div>

                <Paginacion paginado={productos} ruta="reportes.productos" filters={filters as unknown as Record<string, unknown>} />
            </ReportCard>
        </AppLayout>
    );
}

function Dato({ label, valor }: { label: string; valor: string }) {
    return (
        <div>
            <p className="font-bold uppercase tracking-wider text-[9px]" style={{ color: 'var(--vp-navy)' }}>{label}</p>
            <p style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}
