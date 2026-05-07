import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import {
    Calendar, ChevronDown, ChevronUp, Filter, ShieldCheck,
    User as UserIcon, X,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import type { PageProps } from '@/types';

interface Registro {
    id:           number;
    created_at:   string;
    accion:       string;
    accion_label: string;
    user_id:      number | null;
    user_name:    string;
    user_email:   string | null;
    modelo_tipo:  string | null;
    modelo_id:    number | null;
    contexto:     Record<string, unknown> | null;
    ip:           string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
    accion?:      string;
    user_id?:     string;
    fecha_desde?: string;
    fecha_hasta?: string;
}

interface Props extends PageProps {
    registros: Paginated<Registro>;
    usuarios:  Array<{ id: number; name: string }>;
    acciones:  Record<string, string>;
    filters:   Filters;
}

const fechaCompleta = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleString('es-PE', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
};

// Colores por familia de accion para identificar visualmente el tipo de evento
function colorAccion(accion: string): { bg: string; text: string } {
    if (accion.startsWith('venta.'))         return { bg: '#fde68a30', text: '#92400e' };
    if (accion.startsWith('devolucion.'))    return { bg: '#fbbf2430', text: '#92400e' };
    if (accion.startsWith('turno.'))         return { bg: '#a7f3d030', text: '#065f46' };
    if (accion.startsWith('stock.'))         return { bg: '#bfdbfe30', text: '#1e40af' };
    if (accion.startsWith('permisos.'))      return { bg: '#fecaca30', text: '#991b1b' };
    if (accion.startsWith('usuario.'))       return { bg: '#ddd6fe30', text: '#5b21b6' };
    if (accion.startsWith('producto.'))      return { bg: '#fed7aa30', text: '#9a3412' };
    if (accion.startsWith('transferencia.')) return { bg: '#bae6fd30', text: '#075985' };
    return { bg: 'var(--color-border)', text: 'var(--color-text-muted)' };
}

function FilaAuditoria({ registro }: { registro: Registro }) {
    const [expandido, setExpandido] = useState(false);
    const color = colorAccion(registro.accion);
    const tieneContexto = registro.contexto && Object.keys(registro.contexto).length > 0;

    return (
        <>
            <tr
                className="border-t hover:bg-black/[0.015]"
                style={{ borderColor: 'var(--color-border)' }}
            >
                <td className="px-3 py-2 text-xs whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                    {fechaCompleta(registro.created_at)}
                </td>
                <td className="px-3 py-2">
                    <span
                        className="inline-block text-xs font-medium rounded px-2 py-0.5"
                        style={{ backgroundColor: color.bg, color: color.text }}
                    >
                        {registro.accion_label}
                    </span>
                    <p className="text-[10px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                        {registro.accion}
                    </p>
                </td>
                <td className="px-3 py-2 text-sm" style={{ color: 'var(--color-text)' }}>
                    <div className="flex items-center gap-1.5">
                        <UserIcon size={12} style={{ color: 'var(--color-text-muted)' }} />
                        <span className="font-medium">{registro.user_name}</span>
                    </div>
                    {registro.user_email && (
                        <p className="text-[10px] ml-4" style={{ color: 'var(--color-text-muted)' }}>
                            {registro.user_email}
                        </p>
                    )}
                </td>
                <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                    {registro.modelo_tipo ? `${registro.modelo_tipo}#${registro.modelo_id ?? '?'}` : '—'}
                </td>
                <td className="px-3 py-2 text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>
                    {registro.ip ?? '—'}
                </td>
                <td className="px-3 py-2 text-right">
                    {tieneContexto && (
                        <button
                            onClick={() => setExpandido(e => !e)}
                            className="inline-flex items-center gap-0.5 text-xs font-medium hover:underline"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            {expandido ? 'Ocultar' : 'Detalle'}
                            {expandido ? <ChevronUp size={12} /> : <ChevronDown size={12} />}
                        </button>
                    )}
                </td>
            </tr>
            {expandido && tieneContexto && (
                <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                    <td colSpan={6} className="px-3 py-3">
                        <pre
                            className="text-xs overflow-auto rounded p-3 whitespace-pre-wrap break-words"
                            style={{
                                backgroundColor: 'var(--color-surface)',
                                border: '1px solid var(--color-border)',
                                color: 'var(--color-text)',
                                maxHeight: '320px',
                            }}
                        >{JSON.stringify(registro.contexto, null, 2)}</pre>
                    </td>
                </tr>
            )}
        </>
    );
}

export default function ReporteAuditoria({ registros, usuarios, acciones, filters }: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    function aplicar() {
        router.get(route('reportes.auditoria'), localFilters as Record<string, string>, {
            preserveState: true, preserveScroll: true,
        });
    }

    function limpiar() {
        setLocalFilters({});
        router.get(route('reportes.auditoria'), {}, { preserveState: true, preserveScroll: true });
    }

    const hayFiltros = Object.values(filters).some(v => v && v !== '');

    return (
        <AppLayout title="Auditoría">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-semibold flex items-center gap-2" style={{ color: 'var(--color-text)' }}>
                        <ShieldCheck size={20} style={{ color: 'var(--color-primary)' }} />
                        Auditoría de acciones sensibles
                    </h2>
                    <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                        Quién hizo qué y cuándo. {registros.total} registro{registros.total !== 1 ? 's' : ''} encontrado{registros.total !== 1 ? 's' : ''}.
                    </p>
                </div>
            </div>

            {/* Filtros */}
            <div
                className="rounded-lg border p-4 mb-4"
                style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
            >
                <div className="flex items-center gap-2 mb-3">
                    <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                        Filtros
                    </span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Acción</label>
                        <select
                            value={localFilters.accion ?? ''}
                            onChange={e => setLocalFilters(f => ({ ...f, accion: e.target.value || undefined }))}
                            className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                        >
                            <option value="">Todas</option>
                            {Object.entries(acciones).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Usuario</label>
                        <select
                            value={localFilters.user_id ?? ''}
                            onChange={e => setLocalFilters(f => ({ ...f, user_id: e.target.value || undefined }))}
                            className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                        >
                            <option value="">Todos</option>
                            {usuarios.map(u => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Desde</label>
                        <input
                            type="date"
                            value={localFilters.fecha_desde ?? ''}
                            onChange={e => setLocalFilters(f => ({ ...f, fecha_desde: e.target.value || undefined }))}
                            className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Hasta</label>
                        <input
                            type="date"
                            value={localFilters.fecha_hasta ?? ''}
                            onChange={e => setLocalFilters(f => ({ ...f, fecha_hasta: e.target.value || undefined }))}
                            className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                        />
                    </div>
                </div>
                <div className="mt-3 flex gap-2 justify-end">
                    {hayFiltros && (
                        <Button variant="ghost" size="sm" onClick={limpiar} startContent={<X size={12} />}>
                            Limpiar
                        </Button>
                    )}
                    <Button variant="primary" size="sm" onClick={aplicar}>
                        Aplicar filtros
                    </Button>
                </div>
            </div>

            {/* Tabla */}
            <div
                className="rounded-lg border overflow-hidden"
                style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
            >
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead style={{ backgroundColor: 'var(--color-bg)' }}>
                            <tr className="text-left text-xs uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                <th className="px-3 py-2 font-semibold flex items-center gap-1">
                                    <Calendar size={12} />Fecha
                                </th>
                                <th className="px-3 py-2 font-semibold">Acción</th>
                                <th className="px-3 py-2 font-semibold">Usuario</th>
                                <th className="px-3 py-2 font-semibold">Modelo</th>
                                <th className="px-3 py-2 font-semibold">IP</th>
                                <th className="px-3 py-2 font-semibold text-right">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            {registros.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-12 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                        Sin registros que coincidan con los filtros.
                                    </td>
                                </tr>
                            ) : registros.data.map(r => (
                                <FilaAuditoria key={r.id} registro={r} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Paginación */}
            {registros.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Página {registros.current_page} de {registros.last_page}
                    </p>
                    <div className="flex gap-1">
                        {registros.links.map((link, i) => (
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    className="px-3 py-1 text-xs rounded border"
                                    style={{
                                        backgroundColor: link.active ? 'var(--color-primary)' : 'var(--color-surface)',
                                        color: link.active ? '#fff' : 'var(--color-text)',
                                        borderColor: 'var(--color-border)',
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="px-3 py-1 text-xs rounded border opacity-40"
                                    style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        ))}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
