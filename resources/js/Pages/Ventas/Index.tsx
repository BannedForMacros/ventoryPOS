import { useEffect, useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Eye, ShoppingCart, Filter, Calendar, Receipt, Pencil, Trash2, KeyRound, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import type { Local, PageProps, Venta } from '@/types';

// Ventana de edición (debe coincidir con VentaController::EDIT_WINDOW_SECONDS).
const EDIT_WINDOW_MS = 3 * 60 * 1000;

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

    // Reloj para que el gate de 3 min se actualice en vivo (edición desaparece).
    const [ahora, setAhora] = useState(() => Date.now());
    useEffect(() => {
        const t = setInterval(() => setAhora(Date.now()), 15000);
        return () => clearInterval(t);
    }, []);

    // Estado del modal de anulación.
    const [anular, setAnular]   = useState<Venta | null>(null);
    const [motivo, setMotivo]   = useState('');
    const [codigo, setCodigo]   = useState('');
    const [saving, setSaving]   = useState(false);
    const [errAnular, setErrAnular] = useState<Record<string, string>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // ¿La venta sigue dentro del plazo de 3 min desde su creación?
    function dentroPlazo(v: Venta): boolean {
        return ahora - new Date(v.created_at).getTime() < EDIT_WINDOW_MS;
    }
    // El admin edita/anula sin límite; la cajera solo dentro de los 3 min.
    function puedeEditar(v: Venta): boolean {
        return v.estado === 'completada' && (esAdmin || dentroPlazo(v));
    }
    // La cajera necesita código de admin para anular pasado el plazo.
    function requiereCodigo(v: Venta): boolean {
        return !esAdmin && !dentroPlazo(v);
    }

    function abrirAnular(v: Venta) {
        setAnular(v);
        setMotivo('');
        setCodigo('');
        setErrAnular({});
    }

    function confirmarAnular() {
        if (!anular) return;
        setSaving(true);
        setErrAnular({});
        router.post(route('ventas.anular', anular.id), {
            motivo,
            codigo_autorizacion: requiereCodigo(anular) ? codigo : undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSaving(false); setAnular(null); },
            onError:   (errs) => { setSaving(false); setErrAnular(errs as Record<string, string>); },
        });
    }

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
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route('ventas.show', v.id)}
                                            title="Ver detalle"
                                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}
                                        >
                                            <Eye size={14} />
                                        </Link>
                                        {puedeEditar(v) && (
                                            <Link
                                                href={route('pos.index', { venta_id: v.id })}
                                                title="Editar venta"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 12%, transparent)', color: 'var(--color-warning)' }}
                                            >
                                                <Pencil size={14} />
                                            </Link>
                                        )}
                                        {v.estado === 'completada' && (
                                            <button
                                                onClick={() => abrirAnular(v)}
                                                title={requiereCodigo(v) ? 'Anular (requiere código de admin)' : 'Anular venta'}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
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
                    <div
                        key={v.id}
                        className="rounded-xl p-3"
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

                        {/* Acciones */}
                        <div className="flex items-center gap-2 mt-3 pt-3" style={{ borderTop: '1px solid var(--color-border)' }}>
                            <Link
                                href={route('ventas.show', v.id)}
                                className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}
                            >
                                <Eye size={14} /> Ver
                            </Link>
                            {puedeEditar(v) && (
                                <Link
                                    href={route('pos.index', { venta_id: v.id })}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 12%, transparent)', color: 'var(--color-warning)' }}
                                >
                                    <Pencil size={14} /> Editar
                                </Link>
                            )}
                            {v.estado === 'completada' && (
                                <button
                                    onClick={() => abrirAnular(v)}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                                >
                                    <Trash2 size={14} /> Anular
                                </button>
                            )}
                        </div>
                    </div>
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

            {/* ── Modal anular ─────────────────────────────────────── */}
            <Modal
                isOpen={anular !== null}
                onClose={() => setAnular(null)}
                title={`Anular venta ${anular?.numero ?? ''}`}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnular(null)} disabled={saving}>Cancelar</Button>
                        <Button variant="danger" onClick={confirmarAnular} loading={saving}>Anular venta</Button>
                    </>
                }
            >
                {anular && (
                    <div className="space-y-3">
                        <div className="flex items-start gap-2 rounded-lg px-3 py-2 text-sm"
                            style={{ backgroundColor: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)' }}>
                            <AlertTriangle size={16} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-danger)' }} />
                            <p style={{ color: 'var(--color-text)' }}>
                                Anular revierte el stock y el dinero de esta venta. Es una acción irreversible.
                            </p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                Motivo de la anulación <span style={{ color: 'var(--color-danger)' }}>*</span>
                            </label>
                            <textarea
                                rows={2}
                                value={motivo}
                                onChange={e => setMotivo(e.target.value)}
                                disabled={saving}
                                placeholder="Describe por qué se anula (mín. 10 caracteres)"
                                className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            />
                            {errAnular.motivo && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errAnular.motivo}</p>}
                        </div>

                        {/* Código de admin: solo cuando la cajera anula fuera del plazo de 3 min */}
                        {requiereCodigo(anular) && (
                            <div>
                                <label className="flex items-center gap-1.5 text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                    <KeyRound size={14} style={{ color: 'var(--color-warning)' }} />
                                    Código de autorización
                                </label>
                                <p className="text-xs mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Pasaron más de 3 minutos. Pide a un administrador su código de autorización para anular.
                                    <span className="italic"> (Envío por WhatsApp: pendiente de implementar; por ahora es la clave de un admin.)</span>
                                </p>
                                <input
                                    type="password"
                                    value={codigo}
                                    onChange={e => setCodigo(e.target.value)}
                                    disabled={saving}
                                    placeholder="Código / clave de administrador"
                                    className="w-full rounded-xl px-3 py-2 text-sm"
                                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                                />
                                {errAnular.codigo_autorizacion && (
                                    <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errAnular.codigo_autorizacion}</p>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
