import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    CircleDollarSign, TrendingUp, TrendingDown, Percent, Wallet,
    Search, X, Calendar, Info,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Callout from '@/Components/UI/Callout';
import { AreaChart, DonutChart, CHART_COLORS } from '@/Components/UI/Charts';
import type { Local, PageProps } from '@/types';

interface Kpis {
    ventas:         number;
    costo:          number;
    utilidad_bruta: number;
    margen_bruto:   number | null;
    gastos:         number;
    devoluciones:   number;
    utilidad_neta:  number;
    margen_neto:    number | null;
}

interface SerieDia { dia: string; ventas: number; costo: number; bruta: number; neta: number; }

interface ProductoRow {
    producto_id:     number;
    producto_nombre: string;
    categoria:       string;
    cantidad:        number;
    ventas:          number;
    costo:           number;
    utilidad:        number;
    margen:          number | null;
}

interface CategoriaRow { label: string; ventas: number; utilidad: number; }

interface Filters {
    fecha_desde: string;
    fecha_hasta: string;
    local_id?:   string;
    orden:       string;
    buscar?:     string;
}

interface Props extends PageProps {
    kpis:       Kpis;
    serie:      SerieDia[];
    productos:  ProductoRow[];
    categorias: CategoriaRow[];
    locales:    Local[];
    filters:    Filters;
}

const fmt = (n: number) => 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtCant = (n: number) => Number(n).toLocaleString('es-PE', { maximumFractionDigits: 2 });
const diaLabel = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });

function Kpi({ icon, label, value, sub, color }: { icon: React.ReactNode; label: string; value: string; sub?: string; color: string }) {
    return (
        <div className="rounded-2xl px-4 py-3.5 flex items-start gap-3"
            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
            <div className="p-2 rounded-xl flex-shrink-0"
                style={{ backgroundColor: `color-mix(in srgb, ${color} 12%, transparent)`, color }}>
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                <p className="text-lg font-bold leading-tight truncate" style={{ color: 'var(--color-text)' }}>{value}</p>
                {sub && <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>{sub}</p>}
            </div>
        </div>
    );
}

export default function ReporteUtilidad({ kpis, serie, productos, categorias, locales, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.utilidad'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    const serieChart = serie.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Reporte de utilidad">
            <PageHeader
                icon={<CircleDollarSign size={22} />}
                title="Reporte de utilidad"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · Ventas − costo de lo vendido − gastos`}
            />

            {/* Filtros */}
            <div className="rounded-2xl px-4 py-3 mb-4 flex flex-wrap items-end gap-3"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <div>
                    <label className="block text-[10px] font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--color-text-muted)' }}>
                        <Calendar size={11} className="inline mr-1" />Desde
                    </label>
                    <input type="date" value={filters.fecha_desde} onChange={e => filtrar({ fecha_desde: e.target.value })}
                        className="text-sm rounded-lg px-2.5 py-1.5 border outline-none"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                </div>
                <div>
                    <label className="block text-[10px] font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--color-text-muted)' }}>Hasta</label>
                    <input type="date" value={filters.fecha_hasta} onChange={e => filtrar({ fecha_hasta: e.target.value })}
                        className="text-sm rounded-lg px-2.5 py-1.5 border outline-none"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                </div>
                {locales.length > 1 && (
                    <div>
                        <label className="block text-[10px] font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--color-text-muted)' }}>Local</label>
                        <select value={filters.local_id ?? ''} onChange={e => filtrar({ local_id: e.target.value || undefined })}
                            className="text-sm rounded-lg px-2.5 py-1.5 border outline-none"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}>
                            <option value="">Todos</option>
                            {locales.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                        </select>
                    </div>
                )}
                {/* Rangos rápidos */}
                <div className="flex gap-1.5 ml-auto">
                    {([
                        ['Hoy',      0,  'dia'],
                        ['7 días',   6,  'rango'],
                        ['Este mes', 0,  'mes'],
                        ['30 días',  29, 'rango'],
                    ] as const).map(([label, dias, tipo]) => (
                        <button key={label}
                            onClick={() => {
                                const hoy = new Date();
                                const toISO = (d: Date) => d.toISOString().slice(0, 10);
                                if (tipo === 'mes') {
                                    filtrar({ fecha_desde: toISO(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), fecha_hasta: toISO(hoy) });
                                } else {
                                    const desde = new Date(hoy); desde.setDate(hoy.getDate() - dias);
                                    filtrar({ fecha_desde: toISO(desde), fecha_hasta: toISO(hoy) });
                                }
                            }}
                            className="text-xs font-medium px-3 py-1.5 rounded-full border transition-colors hover:bg-black/5"
                            style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            {/* KPIs principales */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<TrendingUp size={18} />} label="Ventas" value={fmt(kpis.ventas)} color="var(--color-primary)" />
                <Kpi icon={<Wallet size={18} />} label="Costo de lo vendido" value={fmt(kpis.costo)} color="#f59e0b" />
                <Kpi icon={<CircleDollarSign size={18} />} label="Utilidad bruta" value={fmt(kpis.utilidad_bruta)}
                    sub={kpis.margen_bruto !== null ? `margen ${kpis.margen_bruto}%` : undefined} color="var(--color-success)" />
                <Kpi icon={<TrendingDown size={18} />} label="Gastos + devoluciones" value={fmt(kpis.gastos + kpis.devoluciones)}
                    sub={kpis.devoluciones > 0 ? `${fmt(kpis.gastos)} gastos · ${fmt(kpis.devoluciones)} dev.` : undefined} color="var(--color-danger)" />
                <Kpi icon={<Percent size={18} />} label="Utilidad neta" value={fmt(kpis.utilidad_neta)}
                    sub={kpis.margen_neto !== null ? `margen ${kpis.margen_neto}%` : undefined}
                    color={kpis.utilidad_neta >= 0 ? 'var(--color-success)' : 'var(--color-danger)'} />
            </div>

            {/* Serie diaria + dona por categoría */}
            <div className="grid lg:grid-cols-3 gap-4 mb-5">
                <div className="lg:col-span-2 rounded-2xl p-4"
                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <p className="text-sm font-bold mb-2" style={{ color: 'var(--color-text)' }}>Utilidad por día</p>
                    <AreaChart
                        data={serieChart}
                        series={[
                            { key: 'ventas', label: 'Ventas', color: CHART_COLORS[0], relleno: true },
                            { key: 'bruta',  label: 'Utilidad bruta', color: '#10b981' },
                            { key: 'neta',   label: 'Utilidad neta', color: '#8b5cf6' },
                        ]}
                        height={240}
                    />
                </div>
                <div className="rounded-2xl p-4"
                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <p className="text-sm font-bold mb-2" style={{ color: 'var(--color-text)' }}>Utilidad por categoría</p>
                    <DonutChart
                        data={categorias.filter(c => c.utilidad > 0).map(c => ({ label: c.label, valor: c.utilidad }))}
                        centro={{ valor: fmt(kpis.utilidad_bruta), label: 'bruta' }}
                        size={150}
                    />
                </div>
            </div>

            {/* Tabla por producto */}
            <div className="rounded-2xl overflow-hidden mb-4"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <div className="flex flex-wrap items-center gap-3 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                    <p className="text-sm font-bold" style={{ color: 'var(--color-text)' }}>
                        Utilidad por producto <span className="font-normal text-xs" style={{ color: 'var(--color-text-muted)' }}>({productos.length})</span>
                    </p>
                    <div className="relative ml-auto">
                        <Search size={13} className="absolute left-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input
                            type="search" defaultValue={filters.buscar ?? ''} placeholder="Buscar producto…"
                            onKeyDown={e => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                            className="text-xs rounded-lg pl-8 pr-7 py-1.5 border outline-none w-52"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                        />
                        {filters.buscar && (
                            <button onClick={() => filtrar({ buscar: undefined })}
                                className="absolute right-2 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }}>
                                <X size={12} />
                            </button>
                        )}
                    </div>
                    <select value={filters.orden} onChange={e => filtrar({ orden: e.target.value })}
                        className="text-xs rounded-lg px-2.5 py-1.5 border outline-none"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}>
                        <option value="utilidad">Más utilidad</option>
                        <option value="margen">Mejor margen %</option>
                        <option value="ventas">Más vendidos (S/)</option>
                        <option value="cantidad">Más vendidos (und)</option>
                    </select>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                                <th className="text-left px-4 py-2 font-semibold">Producto</th>
                                <th className="text-left px-3 py-2 font-semibold hidden sm:table-cell">Categoría</th>
                                <th className="text-right px-3 py-2 font-semibold">Cant.</th>
                                <th className="text-right px-3 py-2 font-semibold">Ventas</th>
                                <th className="text-right px-3 py-2 font-semibold">Costo</th>
                                <th className="text-right px-3 py-2 font-semibold">Utilidad</th>
                                <th className="text-right px-4 py-2 font-semibold">Margen</th>
                            </tr>
                        </thead>
                        <tbody>
                            {productos.map((p, i) => (
                                <tr key={p.producto_id}
                                    style={{ borderTop: '1px solid var(--color-border)', backgroundColor: i % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)' }}>
                                    <td className="px-4 py-2 font-medium" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</td>
                                    <td className="px-3 py-2 hidden sm:table-cell" style={{ color: 'var(--color-text-muted)' }}>{p.categoria}</td>
                                    <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>{fmtCant(p.cantidad)}</td>
                                    <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>{fmt(p.ventas)}</td>
                                    <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text-muted)' }}>{fmt(p.costo)}</td>
                                    <td className="px-3 py-2 text-right font-bold"
                                        style={{ color: p.utilidad >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}>
                                        {fmt(p.utilidad)}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {p.margen !== null ? (
                                            <span className="font-bold px-1.5 py-0.5 rounded"
                                                style={{
                                                    color: p.margen >= 0 ? 'var(--color-success)' : 'var(--color-danger)',
                                                    backgroundColor: `color-mix(in srgb, ${p.margen >= 0 ? 'var(--color-success)' : 'var(--color-danger)'} 10%, transparent)`,
                                                }}>
                                                {p.margen}%
                                            </span>
                                        ) : '—'}
                                    </td>
                                </tr>
                            ))}
                            {productos.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center" style={{ color: 'var(--color-text-muted)' }}>
                                    Sin ventas en el período seleccionado
                                </td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Callout variant="info">
                <span className="flex items-start gap-1.5">
                    <Info size={13} className="flex-shrink-0 mt-0.5" />
                    <span>
                        El costo de cada venta queda <strong>congelado el día que se vende</strong> (si el fierro sube después, la utilidad
                        del pasado no cambia). Ventas anteriores a esta función usan el costo actual como aproximación. Todos los montos
                        incluyen IGV, igual que el balance diario. La utilidad por producto no descuenta los descuentos globales de venta
                        (sí los descuentos por línea).
                    </span>
                </span>
            </Callout>
        </AppLayout>
    );
}
