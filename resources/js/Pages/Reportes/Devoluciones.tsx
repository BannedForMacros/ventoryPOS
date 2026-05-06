import { useEffect } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Filter, Undo2, X, AlertCircle, CheckCircle2, ListChecks, BarChart3,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import type { Local, User, PageProps, Venta } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface DevolucionRow {
    id:                  number;
    numero:              string;
    fecha:               string;
    motivo_id:           number;
    estado:              'pendiente' | 'aprobada' | 'completada' | 'rechazada' | 'anulada';
    forma_reembolso:     string;
    monto_devolucion:    string;
    monto_reembolso:     string;
    requiere_aprobacion: boolean;
    fue_aprobada:        boolean;
    user?:               Pick<User, 'id' | 'name'>;
    user_aprobacion?:    Pick<User, 'id' | 'name'> | null;
    motivo?:             { id: number; nombre: string };
    local?:              Pick<Local, 'id' | 'nombre'>;
    venta?:              Pick<Venta, 'id' | 'numero' | 'fecha_venta' | 'total'>;
}

interface PorMotivo {
    motivo_id: number | null;
    nombre:    string;
    count:     number;
    total:     number;
}

interface Kpis {
    total_devoluciones: number;
    monto_devuelto:     number;
    monto_reembolsado:  number;
    pendientes:         number;
    completadas:        number;
    rechazadas:         number;
    anuladas:           number;
}

interface Filters {
    fecha_desde: string;
    fecha_hasta: string;
    estado?:     string;
    motivo_id?:  string;
    local_id?:   string;
    user_id?:    string;
}

interface Props extends PageProps {
    devoluciones: Paginado<DevolucionRow>;
    kpis:         Kpis;
    por_motivo:   PorMotivo[];
    motivos:      { id: number; nombre: string }[];
    locales:      Local[];
    usuarios:     Pick<User, 'id' | 'name'>[];
    filters:      Filters;
}

const fmt = (n: number) => 'S/ ' + n.toFixed(2);

const estadoVariant = (e: string) =>
    e === 'completada' ? 'success'
    : e === 'aprobada' ? 'success'
    : e === 'pendiente' ? 'warning'
    : e === 'rechazada' ? 'danger'
    : 'secondary';

export default function ReportesDevoluciones({ devoluciones, kpis, por_motivo, motivos, locales, usuarios, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.devoluciones'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    function limpiar() {
        router.get(route('reportes.devoluciones'), {}, { preserveState: true, replace: true });
    }

    const tieneFiltros = !!(filters.estado || filters.motivo_id || filters.local_id || filters.user_id);

    const ringStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    const maxMotivo = Math.max(1, ...por_motivo.map(m => m.count));

    return (
        <AppLayout title="Reporte de devoluciones">
            <PageHeader
                title="Reporte de devoluciones"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${kpis.total_devoluciones} devoluciones`}
            />

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <KpiCard icon={<Undo2 size={18} />} label="Devoluciones" value={kpis.total_devoluciones.toLocaleString('es-PE')} color="primary"
                         sub={kpis.pendientes > 0 ? `${kpis.pendientes} pendientes` : undefined} />
                <KpiCard icon={<CheckCircle2 size={18} />} label="Completadas" value={kpis.completadas.toLocaleString('es-PE')} color="success" />
                <KpiCard icon={<AlertCircle size={18} />} label="Monto devuelto" value={fmt(kpis.monto_devuelto)} color="danger" />
                <KpiCard icon={<ListChecks size={18} />} label="Reembolsado" value={fmt(kpis.monto_reembolsado)} color="warning" />
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
                            <option value="pendiente">Pendientes</option>
                            <option value="aprobada">Aprobadas</option>
                            <option value="completada">Completadas</option>
                            <option value="rechazada">Rechazadas</option>
                            <option value="anulada">Anuladas</option>
                        </select>
                    </Field>
                    <Field label="Motivo">
                        <select value={filters.motivo_id ?? ''} onChange={e => filtrar({ motivo_id: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            {motivos.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}
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
                </div>
            </div>

            {/* Distribución por motivo */}
            <div className="rounded-xl p-3 sm:p-4 mb-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="flex items-center gap-2 mb-2">
                    <BarChart3 size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Distribución por motivo
                    </span>
                </div>
                {por_motivo.length === 0 ? (
                    <p className="text-center py-4 text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
                ) : (
                    <div className="space-y-2">
                        {por_motivo.map(m => (
                            <div key={m.motivo_id ?? 'null'}>
                                <div className="flex items-center justify-between text-xs mb-1">
                                    <span style={{ color: 'var(--color-text)' }}>{m.nombre}</span>
                                    <span className="font-semibold" style={{ color: 'var(--color-text)' }}>
                                        {m.count} <span style={{ color: 'var(--color-text-muted)' }}>· {fmt(m.total)}</span>
                                    </span>
                                </div>
                                <div className="h-2 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-bg)' }}>
                                    <div className="h-full rounded-full"
                                         style={{ width: `${(m.count / maxMotivo) * 100}%`, backgroundColor: 'var(--color-primary)' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Tabla */}
            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[1100px]">
                        <thead>
                            <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                                {['Fecha', 'Número', 'Venta', 'Motivo', 'Reembolso', 'Usuario', 'Aprobación', 'Local', 'Monto', 'Estado'].map((h, i) => (
                                    <th key={h} className={`px-3 py-2.5 text-[10px] font-bold uppercase tracking-wide ${i === 8 ? 'text-right' : 'text-left'}`}
                                        style={{ color: 'var(--color-text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {devoluciones.data.map((d, idx) => (
                                <tr key={d.id} style={{ borderBottom: idx < devoluciones.data.length - 1 ? '1px solid var(--color-border)' : undefined }}>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {new Date(d.fecha).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Link href={route('devoluciones.show', d.id)} className="font-mono text-xs font-medium hover:underline" style={{ color: 'var(--color-primary)' }}>
                                            {d.numero}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {d.venta ? (
                                            <Link href={route('ventas.show', d.venta.id)} className="font-mono text-xs hover:underline" style={{ color: 'var(--color-primary)' }}>
                                                {d.venta.numero}
                                            </Link>
                                        ) : (
                                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>—</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {d.motivo?.nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        {d.forma_reembolso}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {d.user?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {d.requiere_aprobacion ? (
                                            d.user_aprobacion ? (
                                                <span className="text-xs" style={{ color: 'var(--color-success)' }}>{d.user_aprobacion.name}</span>
                                            ) : (
                                                <Badge variant="warning">Pendiente</Badge>
                                            )
                                        ) : (
                                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>No requería</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        {d.local?.nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5 text-sm text-right font-semibold" style={{ color: 'var(--color-danger)' }}>
                                        {fmt(parseFloat(d.monto_devolucion))}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Badge variant={estadoVariant(d.estado)}>{d.estado}</Badge>
                                    </td>
                                </tr>
                            ))}
                            {devoluciones.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <Undo2 size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron devoluciones</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {devoluciones.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: devoluciones.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('reportes.devoluciones'), { ...filters, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === devoluciones.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === devoluciones.current_page ? '#fff' : 'var(--color-text-muted)',
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
