import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Filter, Package, TrendingUp, Hash, X, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import type { Categoria, Local, PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface ProductoRow {
    producto_id:       number;
    producto_nombre:   string;
    cantidad_total:    number;
    monto_total:       number;
    ventas_distintas:  number;
    precio_promedio:   number;
    categoria_id:      number | null;
    categoria_nombre:  string | null;
}

interface Kpis {
    productos_distintos: number;
    cantidad_vendida:    number;
    monto_total:         number;
}

interface Filters {
    fecha_desde:   string;
    fecha_hasta:   string;
    local_id?:     string;
    categoria_id?: string;
    buscar?:       string;
    orden?:        string | null;
}

interface Props extends PageProps {
    productos:  Paginado<ProductoRow>;
    kpis:       Kpis;
    categorias: Pick<Categoria, 'id' | 'nombre'>[];
    locales:    Local[];
    filters:    Filters;
}

const fmt = (n: number) => 'S/ ' + n.toFixed(2);

export default function ReportesProductos({ productos, kpis, categorias, locales, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.productos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    function limpiar() {
        router.get(route('reportes.productos'), {}, { preserveState: true, replace: true });
    }

    const tieneFiltros = !!(filters.local_id || filters.categoria_id || filters.buscar || filters.orden);

    const ringStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    const maxMonto = Math.max(1, ...productos.data.map(p => p.monto_total));

    return (
        <AppLayout title="Productos más vendidos">
            <PageHeader
                title="Productos más vendidos"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${kpis.productos_distintos} productos`}
            />

            {/* KPIs */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <KpiCard icon={<Package size={18} />} label="Productos distintos" value={kpis.productos_distintos.toLocaleString('es-PE')} color="primary" />
                <KpiCard icon={<Hash size={18} />} label="Unidades vendidas" value={kpis.cantidad_vendida.toFixed(2)} color="primary" />
                <KpiCard icon={<TrendingUp size={18} />} label="Monto total" value={fmt(kpis.monto_total)} color="success" />
            </div>

            {/* Filtros */}
            <div
                className="rounded-xl p-3 sm:p-4 mb-4"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
            >
                <div className="flex items-center gap-2 mb-3">
                    <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Filtros</span>
                    {tieneFiltros && (
                        <button onClick={limpiar} className="ml-auto inline-flex items-center gap-1 text-xs font-medium hover:underline" style={{ color: 'var(--color-primary)' }}>
                            <X size={12} /> Limpiar
                        </button>
                    )}
                </div>
                <div className="grid grid-cols-2 lg:grid-cols-6 gap-2">
                    <Field label="Desde">
                        <input type="date" value={filters.fecha_desde} onChange={e => filtrar({ fecha_desde: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    <Field label="Hasta">
                        <input type="date" value={filters.fecha_hasta} onChange={e => filtrar({ fecha_hasta: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    {locales.length > 1 && (
                        <Field label="Local">
                            <select value={filters.local_id ?? ''} onChange={e => filtrar({ local_id: e.target.value || undefined })}
                                    className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                                <option value="">Todos</option>
                                {locales.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                            </select>
                        </Field>
                    )}
                    <Field label="Categoría">
                        <select value={filters.categoria_id ?? ''} onChange={e => filtrar({ categoria_id: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todas</option>
                            {categorias.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                        </select>
                    </Field>
                    <Field label="Ordenar por">
                        <select value={filters.orden ?? ''} onChange={e => filtrar({ orden: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Monto vendido</option>
                            <option value="cantidad">Cantidad</option>
                            <option value="ventas">Nº ventas</option>
                            <option value="precio">Precio promedio</option>
                        </select>
                    </Field>
                    <Field label="Buscar">
                        <div className="relative">
                            <Search size={12} className="absolute left-2 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input type="text" placeholder="Producto..." defaultValue={filters.buscar ?? ''}
                                   onKeyDown={e => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                                   className="w-full text-sm border rounded-lg pl-7 pr-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                        </div>
                    </Field>
                </div>
            </div>

            {/* Tabla */}
            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <table className="w-full text-sm">
                    <thead>
                        <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                            {['#', 'Producto', 'Categoría', 'Cantidad', 'Ventas', 'Precio prom.', 'Total'].map((h, i) => (
                                <th
                                    key={h}
                                    className={`px-3 py-2.5 text-[10px] font-bold uppercase tracking-wide ${i >= 3 ? 'text-right' : 'text-left'}`}
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {productos.data.map((p, idx) => {
                            const pos = (productos.current_page - 1) * 30 + idx + 1;
                            const pct = (p.monto_total / maxMonto) * 100;
                            return (
                                <tr key={p.producto_id} style={{ borderBottom: '1px solid var(--color-border)', position: 'relative' }}>
                                    <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{pos}</td>
                                    <td className="px-3 py-2 relative">
                                        <div
                                            className="absolute inset-y-0 left-0"
                                            style={{
                                                width: `${pct}%`,
                                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 6%, transparent)',
                                                pointerEvents: 'none',
                                            }}
                                        />
                                        <span className="relative text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                            {p.producto_nombre}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{p.categoria_nombre ?? '—'}</td>
                                    <td className="px-3 py-2 text-xs text-right" style={{ color: 'var(--color-text)' }}>{p.cantidad_total.toFixed(2)}</td>
                                    <td className="px-3 py-2 text-xs text-right" style={{ color: 'var(--color-text-muted)' }}>{p.ventas_distintas}</td>
                                    <td className="px-3 py-2 text-xs text-right" style={{ color: 'var(--color-text-muted)' }}>{fmt(p.precio_promedio)}</td>
                                    <td className="px-3 py-2 text-sm text-right font-semibold" style={{ color: 'var(--color-success)' }}>{fmt(p.monto_total)}</td>
                                </tr>
                            );
                        })}
                        {productos.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    <Package size={36} className="mx-auto mb-2 opacity-20" />
                                    <p className="text-sm">No se encontraron productos vendidos</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Paginación */}
            {productos.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: productos.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('reportes.productos'), { ...filters, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === productos.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === productos.current_page ? '#fff' : 'var(--color-text-muted)',
                            }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function KpiCard({ icon, label, value, color }: {
    icon: React.ReactNode; label: string; value: string;
    color: 'primary' | 'success' | 'danger' | 'warning';
}) {
    return (
        <div
            className="rounded-xl p-3 flex items-center gap-3"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
        >
            <div
                className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                style={{
                    backgroundColor: `color-mix(in srgb, var(--color-${color}) 10%, transparent)`,
                    color: `var(--color-${color})`,
                }}
            >
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-medium uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{value}</p>
            </div>
        </div>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            {children}
        </div>
    );
}
