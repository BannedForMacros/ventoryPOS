import { useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Filter, Wallet, TrendingDown, ClipboardList, X, PieChart,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import type { Gasto, GastoTipo, GastoConcepto, Local, User, PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface PorTipo {
    gasto_tipo_id: number;
    nombre:        string;
    categoria:     string;
    total:         number;
    count:         number;
}

interface Kpis {
    total: number;
    count: number;
    admin: number;
    turno: number;
}

interface Filters {
    fecha_desde:  string;
    fecha_hasta:  string;
    tipo_id?:     string;
    concepto_id?: string;
    local_id?:    string;
    user_id?:     string;
    origen?:      string;
}

interface Props extends PageProps {
    gastos:   Paginado<Gasto>;
    kpis:     Kpis;
    por_tipo: PorTipo[];
    tipos:    (GastoTipo & { conceptos: GastoConcepto[] })[];
    locales:  Local[];
    usuarios: Pick<User, 'id' | 'name'>[];
    filters:  Filters;
}

const fmt = (n: number) => 'S/ ' + n.toFixed(2);

export default function ReportesGastos({ gastos, kpis, por_tipo, tipos, locales, usuarios, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.gastos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    function limpiar() {
        router.get(route('reportes.gastos'), {}, { preserveState: true, replace: true });
    }

    const tieneFiltros = !!(filters.tipo_id || filters.concepto_id || filters.local_id || filters.user_id || filters.origen);

    const ringStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    const conceptosFiltrados = useMemo(() => {
        if (!filters.tipo_id) return [];
        return tipos.find(t => String(t.id) === filters.tipo_id)?.conceptos ?? [];
    }, [filters.tipo_id, tipos]);

    const maxTipo = Math.max(1, ...por_tipo.map(t => t.total));

    return (
        <AppLayout title="Reporte de gastos">
            <PageHeader
                title="Reporte de gastos"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${kpis.count} gastos`}
            />

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <KpiCard icon={<TrendingDown size={18} />} label="Total gastado" value={fmt(kpis.total)} color="danger" />
                <KpiCard icon={<ClipboardList size={18} />} label="Cantidad" value={kpis.count.toLocaleString('es-PE')} color="primary" />
                <KpiCard icon={<Wallet size={18} />} label="De turnos" value={fmt(kpis.turno)} color="warning" />
                <KpiCard icon={<Wallet size={18} />} label="Administrativos" value={fmt(kpis.admin)} color="primary" />
            </div>

            {/* Filtros */}
            <div className="rounded-xl p-3 sm:p-4 mb-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="flex items-center gap-2 mb-3">
                    <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Filtros</span>
                    {tieneFiltros && (
                        <button onClick={limpiar} className="ml-auto inline-flex items-center gap-1 text-xs font-medium hover:underline" style={{ color: 'var(--color-primary)' }}>
                            <X size={12} /> Limpiar
                        </button>
                    )}
                </div>
                <div className="grid grid-cols-2 lg:grid-cols-7 gap-2">
                    <Field label="Desde">
                        <input type="date" value={filters.fecha_desde} onChange={e => filtrar({ fecha_desde: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    <Field label="Hasta">
                        <input type="date" value={filters.fecha_hasta} onChange={e => filtrar({ fecha_hasta: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    <Field label="Tipo">
                        <select value={filters.tipo_id ?? ''} onChange={e => filtrar({ tipo_id: e.target.value || undefined, concepto_id: undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            {tipos.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                        </select>
                    </Field>
                    <Field label="Concepto">
                        <select value={filters.concepto_id ?? ''} onChange={e => filtrar({ concepto_id: e.target.value || undefined })}
                                disabled={!filters.tipo_id}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 disabled:opacity-50" style={ringStyle}>
                            <option value="">Todos</option>
                            {conceptosFiltrados.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                        </select>
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
                    <Field label="Usuario">
                        <select value={filters.user_id ?? ''} onChange={e => filtrar({ user_id: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            {usuarios.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Origen">
                        <select value={filters.origen ?? ''} onChange={e => filtrar({ origen: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            <option value="turno">De turno</option>
                            <option value="admin">Administrativo</option>
                        </select>
                    </Field>
                </div>
            </div>

            {/* Distribución por tipo */}
            <div className="rounded-xl p-3 sm:p-4 mb-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="flex items-center gap-2 mb-2">
                    <PieChart size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Distribución por tipo
                    </span>
                </div>
                {por_tipo.length === 0 ? (
                    <p className="text-center py-4 text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
                ) : (
                    <div className="space-y-2">
                        {por_tipo.map(t => (
                            <div key={t.gasto_tipo_id}>
                                <div className="flex items-center justify-between text-xs mb-1">
                                    <span className="flex items-center gap-2" style={{ color: 'var(--color-text)' }}>
                                        {t.nombre}
                                        <Badge variant={t.categoria === 'operativo' ? 'warning' : 'primary'}>{t.categoria}</Badge>
                                    </span>
                                    <span className="font-semibold" style={{ color: 'var(--color-danger)' }}>
                                        {fmt(t.total)} <span style={{ color: 'var(--color-text-muted)' }}>· {t.count}</span>
                                    </span>
                                </div>
                                <div className="h-2 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-bg)' }}>
                                    <div className="h-full rounded-full"
                                         style={{ width: `${(t.total / maxTipo) * 100}%`, backgroundColor: 'var(--color-danger)' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Tabla detalle */}
            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <table className="w-full text-sm">
                    <thead>
                        <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                            {['Fecha', 'Tipo', 'Concepto', 'Comentario', 'Origen', 'Usuario', 'Local', 'Monto'].map((h, i) => (
                                <th key={h} className={`px-3 py-2.5 text-[10px] font-bold uppercase tracking-wide ${i === 7 ? 'text-right' : 'text-left'}`}
                                    style={{ color: 'var(--color-text-muted)' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {gastos.data.map((g, idx) => (
                            <tr key={g.id} style={{ borderBottom: idx < gastos.data.length - 1 ? '1px solid var(--color-border)' : undefined }}>
                                <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                    {new Date(g.fecha).toLocaleDateString('es-PE')}
                                </td>
                                <td className="px-3 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                    {(g.tipo as any)?.nombre ?? '—'}
                                </td>
                                <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                    {(g.concepto as any)?.nombre ?? '—'}
                                </td>
                                <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    {g.comentario ?? '—'}
                                </td>
                                <td className="px-3 py-2.5">
                                    <Badge variant={g.turno_id ? 'warning' : 'primary'}>
                                        {g.turno_id ? 'Turno' : 'Admin'}
                                    </Badge>
                                </td>
                                <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                    {(g.user as any)?.name ?? '—'}
                                </td>
                                <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    {(g.local as any)?.nombre ?? '—'}
                                </td>
                                <td className="px-3 py-2.5 text-sm text-right font-semibold" style={{ color: 'var(--color-danger)' }}>
                                    {fmt(parseFloat(g.monto))}
                                </td>
                            </tr>
                        ))}
                        {gastos.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    <Wallet size={36} className="mx-auto mb-2 opacity-20" />
                                    <p className="text-sm">No se encontraron gastos</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {gastos.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: gastos.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('reportes.gastos'), { ...filters, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === gastos.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === gastos.current_page ? '#fff' : 'var(--color-text-muted)',
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
        <div className="rounded-xl p-3 flex items-center gap-3" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style={{ backgroundColor: `color-mix(in srgb, var(--color-${color}) 10%, transparent)`, color: `var(--color-${color})` }}>
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-medium uppercase tracking-wide truncate" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                <p className="text-base font-bold truncate" style={{ color: 'var(--color-text)' }}>{value}</p>
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
