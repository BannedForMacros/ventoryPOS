import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Package, Boxes, Coins, Percent, Star, Layers, Trophy } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Table, { Column } from '@/Components/UI/Table';
import { DonutChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect,
    fmtS, fmtInt, fmtCant, fieldStyle,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Categoria, Local, PageProps } from '@/types';

interface ProductoRow extends Record<string, unknown> {
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

/** Fila de la tabla: el producto + su puesto y participación ya calculados. */
interface ProductoFila extends ProductoRow { rank: number; participacion: number; }

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

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // Al filtrar se vuelve a la página 1 (si no, se queda en una página que ya
    // no existe) y se conserva el scroll: antes la pantalla saltaba al inicio
    // y había que bajar de nuevo hasta la tabla.
    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.productos'), { ...filters, ...patch, page: undefined }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }
    const limpiar = () => router.get(route('reportes.productos'), {}, {
        preserveState: true, preserveScroll: true, replace: true,
    });

    const tieneFiltros = !!(filters.local_id || filters.categoria_id || filters.buscar || filters.orden);

    // La tabla estándar recibe el paginador del servidor tal cual (navega páginas
    // reales); solo se le agrega a cada fila su puesto y su participación.
    const rankBase = (productos.from ?? 1) - 1;
    const filas: ProductoFila[] = productos.data.map((p, i) => ({
        ...p,
        rank: rankBase + i + 1,
        participacion: kpis.monto_total > 0 ? (p.monto_total / kpis.monto_total) * 100 : 0,
    }));

    const columnas: Column<ProductoFila>[] = [
        { key: 'rank', label: '#', render: p => <span style={{ color: 'var(--color-text-muted)' }}>{p.rank}</span> },
        { key: 'producto_nombre', label: 'Producto', render: p => <span className="font-medium">{p.producto_nombre}</span> },
        { key: 'categoria_nombre', label: 'Categoría', render: p => <span style={{ color: 'var(--color-text-muted)' }}>{p.categoria_nombre ?? 'Sin categoría'}</span> },
        { key: 'cantidad_total', label: 'Cant.', align: 'right', render: p => fmtCant(p.cantidad_total) },
        { key: 'ventas_distintas', label: 'N° ventas', align: 'right', render: p => fmtInt(p.ventas_distintas) },
        { key: 'precio_promedio', label: 'P. promedio', align: 'right', render: p => fmtS(p.precio_promedio) },
        {
            key: 'descuento_total', label: 'Descuento', align: 'right',
            render: p => p.descuento_total > 0
                ? <span style={{ color: 'var(--color-danger)' }}>-{fmtS(p.descuento_total)}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        { key: 'monto_total', label: 'Total', align: 'right', render: p => <span className="font-bold" style={{ color: 'var(--color-success)' }}>{fmtS(p.monto_total)}</span> },
        {
            key: 'participacion', label: 'Part.', align: 'right',
            render: p => (
                <span className="font-bold px-1.5 py-0.5 rounded" style={{
                    color: 'var(--color-primary)',
                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                }}>{p.participacion.toFixed(1)}%</span>
            ),
        },
    ];

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

            {/* Ranking completo — tabla estándar: busca al escribir (sin Enter),
                pagina contra el servidor y despliega el detalle de cada fila. */}
            <ReportCard icon={<Package size={14} />} title="Ranking de productos" badge={fmtInt(productos.total)} sinPadding
                actions={
                    <select value={filters.orden ?? ''} onChange={e => filtrar({ orden: e.target.value || undefined })}
                        className="text-xs rounded-lg px-2.5 py-1.5 border outline-none"
                        style={fieldStyle}>
                        <option value="">Más vendidos (S/)</option>
                        <option value="cantidad">Más vendidos (und)</option>
                        <option value="ventas">Más ventas distintas</option>
                        <option value="precio">Mayor precio prom.</option>
                        <option value="descuento">Más descontados</option>
                    </select>
                }>
                <div className="p-3">
                    <Table
                        data={{ ...productos, data: filas }}
                        columns={columnas}
                        sortable={false}
                        searchPlaceholder="Buscar producto…"
                        emptyMessage="Sin productos vendidos en el rango"
                        initialSearch={filters.buscar ?? ''}
                        onServerSearch={t => filtrar({ buscar: t || undefined })}
                        renderExpandedRow={p => (
                            <div className="flex flex-wrap gap-x-8 gap-y-2 text-[11px]">
                                <Dato label="Unidades usadas" valor={p.unidades ?? '—'} />
                                <Dato label="Promedio por venta" valor={p.ventas_distintas > 0 ? fmtS(p.monto_total / p.ventas_distintas) : '—'} />
                                <Dato label="Cantidad prom. por venta" valor={p.ventas_distintas > 0 ? fmtCant(p.cantidad_total / p.ventas_distintas) : '—'} />
                                <Dato label="Descuento acumulado" valor={p.descuento_total > 0 ? `-${fmtS(p.descuento_total)}` : 'Sin descuentos'} />
                                <Dato label="Categoría" valor={p.categoria_nombre ?? 'Sin categoría'} />
                            </div>
                        )}
                    />
                </div>
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
