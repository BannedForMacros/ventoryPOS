import { useEffect } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Filter, Percent, Calendar, UserCheck, Receipt, AlertTriangle, Bell } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import type { DescuentoConcepto, DescuentoLog, PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface Filters {
    concepto_id?: string;
    user_id?:     string;
    fecha_desde?: string;
    fecha_hasta?: string;
}

interface Props extends PageProps {
    logs:      Paginado<DescuentoLog>;
    conceptos: DescuentoConcepto[];
    filters:   Filters;
}

export default function ReportesDescuentos({ logs, conceptos, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.descuentos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    function limpiarFiltros() {
        router.get(route('reportes.descuentos'), {}, { preserveState: true, replace: true });
    }

    const tienesFiltros = !!(filters.concepto_id || filters.fecha_desde || filters.fecha_hasta);
    const totalDescuentos = logs.data.reduce((s, l) => s + parseFloat(l.monto_descuento), 0);

    return (
        <AppLayout title="Reporte de descuentos">
            <PageHeader
                title="Reporte de descuentos"
                subtitle={`${logs.total} registros`}
            />

            {/* Resumen */}
            <div
                className="rounded-xl p-4 mb-4 flex items-center gap-4"
                style={{
                    background: 'linear-gradient(135deg, color-mix(in srgb, var(--color-danger) 8%, var(--color-surface)), var(--color-surface))',
                    border: '1px solid color-mix(in srgb, var(--color-danger) 15%, var(--color-border))',
                }}
            >
                <div
                    className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                >
                    <Percent size={20} />
                </div>
                <div>
                    <p className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>
                        Total descontado (página actual)
                    </p>
                    <p className="text-xl font-bold" style={{ color: 'var(--color-danger)' }}>
                        -S/ {totalDescuentos.toFixed(2)}
                    </p>
                </div>
            </div>

            {/* Filtros */}
            <div
                className="rounded-xl p-3 sm:p-4 mb-4"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
            >
                <div className="flex items-center gap-2 mb-3">
                    <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Filtros
                    </span>
                    {tienesFiltros && (
                        <button
                            onClick={limpiarFiltros}
                            className="ml-auto text-xs font-medium hover:underline"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            Limpiar
                        </button>
                    )}
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3">
                    <div>
                        <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Concepto</label>
                        <select
                            value={filters.concepto_id ?? ''}
                            onChange={e => filtrar({ concepto_id: e.target.value || undefined })}
                            className="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                            } as React.CSSProperties}
                        >
                            <option value="">Todos</option>
                            {conceptos.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Desde</label>
                        <input
                            type="date"
                            value={filters.fecha_desde ?? ''}
                            onChange={e => filtrar({ fecha_desde: e.target.value || undefined })}
                            className="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                            } as React.CSSProperties}
                        />
                    </div>
                    <div>
                        <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Hasta</label>
                        <input
                            type="date"
                            value={filters.fecha_hasta ?? ''}
                            onChange={e => filtrar({ fecha_hasta: e.target.value || undefined })}
                            className="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                            } as React.CSSProperties}
                        />
                    </div>
                </div>
            </div>

            {/* Tabla desktop */}
            <div
                className="hidden md:block rounded-xl overflow-hidden"
                style={{ border: '1px solid var(--color-border)' }}
            >
                <table className="w-full text-sm" style={{ backgroundColor: 'var(--color-surface)' }}>
                    <thead>
                        <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                            {['Fecha', 'Concepto', 'Monto', 'Venta', 'Vendedor', 'Aprobación', 'Notif.'].map(h => (
                                <th key={h} className="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {logs.data.map((l, idx) => (
                            <tr
                                key={l.id}
                                className="transition-colors hover:bg-black/[0.02]"
                                style={{
                                    borderBottom: idx < logs.data.length - 1 ? '1px solid var(--color-border)' : undefined,
                                }}
                            >
                                <td className="px-4 py-3">
                                    <div className="text-xs" style={{ color: 'var(--color-text)' }}>
                                        {new Date(l.created_at).toLocaleDateString('es-PE')}
                                    </div>
                                    <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                                        {new Date(l.created_at).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                        {(l.concepto as any)?.nombre ?? '—'}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="font-bold text-sm" style={{ color: 'var(--color-danger)' }}>
                                        -S/ {parseFloat(l.monto_descuento).toFixed(2)}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    {l.venta ? (
                                        <Link
                                            href={route('ventas.show', (l.venta as any).id)}
                                            className="font-mono text-xs font-medium hover:underline"
                                            style={{ color: 'var(--color-primary)' }}
                                        >
                                            {(l.venta as any).numero}
                                        </Link>
                                    ) : (
                                        <span style={{ color: 'var(--color-text-muted)' }}>—</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--color-text)' }}>
                                    {(l.user as any)?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={l.requeria_aprobacion ? 'warning' : 'secondary'}>
                                        {l.requeria_aprobacion ? 'Requerida' : 'No'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    {l.notificacion_enviada ? (
                                        <Badge variant="success">Enviada</Badge>
                                    ) : (
                                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>—</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {logs.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    <Percent size={40} className="mx-auto mb-3 opacity-20" />
                                    <p className="text-sm">No se encontraron descuentos</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Cards móvil */}
            <div className="md:hidden flex flex-col gap-2">
                {logs.data.map(l => (
                    <div
                        key={l.id}
                        className="rounded-xl p-3"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            border: '1px solid var(--color-border)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                        }}
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                    {(l.concepto as any)?.nombre ?? '—'}
                                </p>
                                <div className="flex items-center gap-2 mt-1 text-xs flex-wrap" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="flex items-center gap-1">
                                        <Calendar size={11} />
                                        {new Date(l.created_at).toLocaleDateString('es-PE')}
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <UserCheck size={11} />
                                        {(l.user as any)?.name ?? '—'}
                                    </span>
                                    {l.venta && (
                                        <Link
                                            href={route('ventas.show', (l.venta as any).id)}
                                            className="flex items-center gap-1 font-mono hover:underline"
                                            style={{ color: 'var(--color-primary)' }}
                                        >
                                            <Receipt size={11} />
                                            {(l.venta as any).numero}
                                        </Link>
                                    )}
                                </div>
                                <div className="flex items-center gap-1.5 mt-1.5">
                                    {l.requeria_aprobacion && (
                                        <Badge variant="warning">Req. Aprobación</Badge>
                                    )}
                                    {l.notificacion_enviada && (
                                        <Badge variant="success">Notificada</Badge>
                                    )}
                                </div>
                            </div>
                            <span className="text-base font-bold flex-shrink-0" style={{ color: 'var(--color-danger)' }}>
                                -S/ {parseFloat(l.monto_descuento).toFixed(2)}
                            </span>
                        </div>
                    </div>
                ))}
                {logs.data.length === 0 && (
                    <div className="text-center py-16" style={{ color: 'var(--color-text-muted)' }}>
                        <Percent size={40} className="mx-auto mb-3 opacity-20" />
                        <p className="text-sm">No se encontraron descuentos</p>
                    </div>
                )}
            </div>

            {/* Paginación */}
            {logs.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: logs.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('reportes.descuentos'), { ...filters, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === logs.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === logs.current_page ? '#fff' : 'var(--color-text-muted)',
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
