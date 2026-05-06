import { useEffect } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Filter, Wallet, TrendingUp, TrendingDown, Scale, X, ClipboardList,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import type { Caja, Local, User, PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface TurnoRow {
    id:                       number;
    caja:                     Pick<Caja, 'id' | 'nombre'> | null;
    local:                    Pick<Local, 'id' | 'nombre'> | null;
    user:                     Pick<User, 'id' | 'name'> | null;
    user_cierre:              Pick<User, 'id' | 'name'> | null;
    estado:                   'abierto' | 'cerrado';
    fecha_apertura:           string;
    fecha_cierre:             string | null;
    monto_apertura:           number;
    monto_cierre_declarado:   number | null;
    monto_cierre_esperado:    number | null;
    diferencia:               number | null;
    ventas_count:             number;
    ventas_total:             number;
    gastos_total:             number;
}

interface Kpis {
    total_turnos:    number;
    turnos_abiertos: number;
    turnos_cerrados: number;
    sobrantes:       number;
    faltantes:       number;
    diferencia_neta: number;
}

interface Filters {
    fecha_desde: string;
    fecha_hasta: string;
    estado?:     string;
    local_id?:   string;
    caja_id?:    string;
    user_id?:    string;
}

interface Props extends PageProps {
    turnos:   Paginado<TurnoRow>;
    kpis:     Kpis;
    locales:  Local[];
    cajas:    Pick<Caja, 'id' | 'nombre' | 'local_id'>[];
    usuarios: Pick<User, 'id' | 'name'>[];
    filters:  Filters;
}

const fmt = (n: number | null) => n === null ? '—' : 'S/ ' + n.toFixed(2);

export default function ReportesCaja({ turnos, kpis, locales, cajas, usuarios, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.caja'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    function limpiar() {
        router.get(route('reportes.caja'), {}, { preserveState: true, replace: true });
    }

    const tieneFiltros = !!(filters.estado || filters.local_id || filters.caja_id || filters.user_id);

    const ringStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    return (
        <AppLayout title="Reporte de caja">
            <PageHeader
                title="Reporte de caja"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${kpis.total_turnos} turnos`}
            />

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <KpiCard icon={<Wallet size={18} />} label="Turnos cerrados" value={kpis.turnos_cerrados.toLocaleString('es-PE')}
                         sub={kpis.turnos_abiertos > 0 ? `${kpis.turnos_abiertos} abiertos` : undefined} color="primary" />
                <KpiCard icon={<TrendingUp size={18} />} label="Sobrantes" value={fmt(kpis.sobrantes)} color="success" />
                <KpiCard icon={<TrendingDown size={18} />} label="Faltantes" value={fmt(kpis.faltantes)} color="danger" />
                <KpiCard icon={<Scale size={18} />} label="Diferencia neta" value={fmt(kpis.diferencia_neta)}
                         color={kpis.diferencia_neta < 0 ? 'danger' : kpis.diferencia_neta > 0 ? 'success' : 'primary'} />
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
                <div className="grid grid-cols-2 lg:grid-cols-6 gap-2">
                    <Field label="Desde">
                        <input type="date" value={filters.fecha_desde} onChange={e => filtrar({ fecha_desde: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    <Field label="Hasta">
                        <input type="date" value={filters.fecha_hasta} onChange={e => filtrar({ fecha_hasta: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    <Field label="Estado">
                        <select value={filters.estado ?? ''} onChange={e => filtrar({ estado: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            <option value="abierto">Abiertos</option>
                            <option value="cerrado">Cerrados</option>
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
                    <Field label="Caja">
                        <select value={filters.caja_id ?? ''} onChange={e => filtrar({ caja_id: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todas</option>
                            {cajas.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                        </select>
                    </Field>
                    <Field label="Cajero">
                        <select value={filters.user_id ?? ''} onChange={e => filtrar({ user_id: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            {usuarios.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                        </select>
                    </Field>
                </div>
            </div>

            {/* Tabla */}
            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[1100px]">
                        <thead>
                            <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                                {['Apertura', 'Cierre', 'Caja / Local', 'Cajero', 'Apert.', 'Ventas', 'Gastos', 'Esperado', 'Declarado', 'Diferencia', 'Estado'].map((h, i) => (
                                    <th key={h} className={`px-3 py-2.5 text-[10px] font-bold uppercase tracking-wide ${i >= 4 && i <= 9 ? 'text-right' : 'text-left'}`}
                                        style={{ color: 'var(--color-text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {turnos.data.map((t, idx) => (
                                <tr key={t.id} className="hover:bg-black/[0.02]"
                                    style={{ borderBottom: idx < turnos.data.length - 1 ? '1px solid var(--color-border)' : undefined }}>
                                    <td className="px-3 py-2.5">
                                        <Link href={route('turnos.show', t.id)} className="text-xs font-medium hover:underline" style={{ color: 'var(--color-primary)' }}>
                                            {new Date(t.fecha_apertura).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        {t.fecha_cierre ? new Date(t.fecha_cierre).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="text-xs font-medium" style={{ color: 'var(--color-text)' }}>{t.caja?.nombre ?? '—'}</div>
                                        <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{t.local?.nombre ?? '—'}</div>
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {t.user?.name ?? '—'}
                                        {t.user_cierre && t.user_cierre.id !== t.user?.id && (
                                            <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>cierre: {t.user_cierre.name}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs text-right" style={{ color: 'var(--color-text-muted)' }}>{fmt(t.monto_apertura)}</td>
                                    <td className="px-3 py-2.5 text-xs text-right">
                                        <div style={{ color: 'var(--color-success)' }}>{fmt(t.ventas_total)}</div>
                                        <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{t.ventas_count} vts.</div>
                                    </td>
                                    <td className="px-3 py-2.5 text-xs text-right" style={{ color: 'var(--color-danger)' }}>{fmt(t.gastos_total)}</td>
                                    <td className="px-3 py-2.5 text-xs text-right" style={{ color: 'var(--color-text)' }}>{fmt(t.monto_cierre_esperado)}</td>
                                    <td className="px-3 py-2.5 text-xs text-right" style={{ color: 'var(--color-text)' }}>{fmt(t.monto_cierre_declarado)}</td>
                                    <td className="px-3 py-2.5 text-xs text-right font-semibold"
                                        style={{ color: t.diferencia === null ? 'var(--color-text-muted)' : t.diferencia < 0 ? 'var(--color-danger)' : t.diferencia > 0 ? 'var(--color-warning)' : 'var(--color-success)' }}>
                                        {fmt(t.diferencia)}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Badge variant={t.estado === 'cerrado' ? 'success' : 'warning'}>{t.estado}</Badge>
                                    </td>
                                </tr>
                            ))}
                            {turnos.data.length === 0 && (
                                <tr>
                                    <td colSpan={11} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <ClipboardList size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron turnos</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {turnos.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: turnos.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('reportes.caja'), { ...filters, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === turnos.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === turnos.current_page ? '#fff' : 'var(--color-text-muted)',
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

function KpiCard({ icon, label, value, sub, color }: {
    icon: React.ReactNode; label: string; value: string; sub?: string;
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
                {sub && <p className="text-[10px] truncate" style={{ color: 'var(--color-text-muted)' }}>{sub}</p>}
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
