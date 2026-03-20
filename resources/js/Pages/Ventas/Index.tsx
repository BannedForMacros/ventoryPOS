import { useEffect } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Eye, ShoppingCart, Filter, Calendar, MapPin, Receipt } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { Local, PageProps, Venta } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Filters {
    estado?:      string;
    fecha_desde?: string;
    fecha_hasta?: string;
    local_id?:    string;
}

interface Props extends PageProps {
    ventas:  Paginado<Venta>;
    locales: Local[];
    filters: Filters;
}

export default function VentasIndex({ ventas, locales, filters, flash }: Props) {
    const { auth } = usePage<Props>().props;
    const esAdmin  = auth.user.rol?.es_admin ?? false;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('ventas.index'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    function limpiarFiltros() {
        router.get(route('ventas.index'), {}, { preserveState: true, replace: true });
    }

    const tienesFiltros = !!(filters.estado || filters.fecha_desde || filters.fecha_hasta || filters.local_id);

    function clienteNombre(v: Venta) {
        if (!v.cliente) return 'General';
        const c = v.cliente as any;
        return c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim();
    }

    return (
        <AppLayout title="Ventas">
            <PageHeader
                title="Historial de ventas"
                subtitle={`${ventas.total} ventas registradas`}
                actions={
                    <Link href={route('pos.index')}>
                        <Button variant="primary" startContent={<ShoppingCart size={16} />}>
                            Ir al POS
                        </Button>
                    </Link>
                }
            />

            {/* ── Filtros ──────────────────────────────────────────────── */}
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
                            Limpiar filtros
                        </button>
                    )}
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                    <div>
                        <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Estado</label>
                        <select
                            value={filters.estado ?? ''}
                            onChange={e => filtrar({ estado: e.target.value || undefined })}
                            className="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                            } as React.CSSProperties}
                        >
                            <option value="">Todos</option>
                            <option value="completada">Completadas</option>
                            <option value="anulada">Anuladas</option>
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

                    {esAdmin && locales.length > 1 && (
                        <div>
                            <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Local</label>
                            <select
                                value={filters.local_id ?? ''}
                                onChange={e => filtrar({ local_id: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                } as React.CSSProperties}
                            >
                                <option value="">Todos los locales</option>
                                {locales.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                            </select>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Tabla desktop ─────────────────────────────────────── */}
            <div
                className="hidden md:block rounded-xl overflow-hidden"
                style={{ border: '1px solid var(--color-border)' }}
            >
                <table className="w-full text-sm" style={{ backgroundColor: 'var(--color-surface)' }}>
                    <thead>
                        <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                            {['N°', 'Fecha', 'Cliente', 'Comprobante', 'Estado', 'Total', ''].map(h => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {ventas.data.map((v, idx) => (
                            <tr
                                key={v.id}
                                className="transition-colors hover:bg-black/[0.02]"
                                style={{ borderBottom: idx < ventas.data.length - 1 ? '1px solid var(--color-border)' : undefined }}
                            >
                                <td className="px-4 py-3">
                                    <span className="font-mono font-bold text-xs px-2 py-1 rounded-md"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}>
                                        {v.numero}
                                    </span>
                                </td>
                                <td className="px-4 py-3" style={{ color: 'var(--color-text)' }}>
                                    <div className="text-xs">
                                        {new Date(v.fecha_venta).toLocaleDateString('es-PE')}
                                    </div>
                                    <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                                        {new Date(v.fecha_venta).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                    </div>
                                </td>
                                <td className="px-4 py-3 max-w-[180px]">
                                    <span className="truncate block text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                        {clienteNombre(v)}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="capitalize text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        {v.tipo_comprobante}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                                        {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                                        S/ {parseFloat(v.total).toFixed(2)}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <Link
                                        href={route('ventas.show', v.id)}
                                        className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors hover:opacity-80"
                                        style={{
                                            backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)',
                                            color: 'var(--color-primary)',
                                        }}
                                    >
                                        <Eye size={13} /> Ver
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {ventas.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    <Receipt size={40} className="mx-auto mb-3 opacity-20" />
                                    <p className="text-sm">No se encontraron ventas</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* ── Cards móvil ──────────────────────────────────────── */}
            <div className="md:hidden flex flex-col gap-2">
                {ventas.data.map(v => (
                    <Link
                        key={v.id}
                        href={route('ventas.show', v.id)}
                        className="rounded-xl p-3 transition-all active:scale-[0.99]"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            border: '1px solid var(--color-border)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                        }}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <span className="font-mono font-bold text-xs px-1.5 py-0.5 rounded"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}>
                                        {v.numero}
                                    </span>
                                    <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                                        {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                                    </Badge>
                                </div>
                                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                    {clienteNombre(v)}
                                </p>
                                <div className="flex items-center gap-3 mt-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="flex items-center gap-1">
                                        <Calendar size={11} />
                                        {new Date(v.fecha_venta).toLocaleDateString('es-PE')}
                                    </span>
                                    <span className="capitalize">{v.tipo_comprobante}</span>
                                </div>
                            </div>
                            <div className="text-right flex-shrink-0">
                                <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>
                                    S/ {parseFloat(v.total).toFixed(2)}
                                </p>
                                <p className="text-[10px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    {new Date(v.fecha_venta).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                </p>
                            </div>
                        </div>
                    </Link>
                ))}
                {ventas.data.length === 0 && (
                    <div className="text-center py-16" style={{ color: 'var(--color-text-muted)' }}>
                        <Receipt size={40} className="mx-auto mb-3 opacity-20" />
                        <p className="text-sm">No se encontraron ventas</p>
                    </div>
                )}
            </div>

            {/* ── Paginación ───────────────────────────────────────── */}
            {ventas.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: ventas.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('ventas.index'), { ...filters, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === ventas.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === ventas.current_page ? '#fff' : 'var(--color-text-muted)',
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
