import { useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Plus, Calendar, Clock, User as UserIcon, Briefcase, Filter,
    CheckCircle2, PlayCircle, XCircle, AlertCircle, ShoppingCart,
    ChevronLeft, ChevronRight,
} from 'lucide-react';
import toast from 'react-hot-toast';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';
import { hoyLocal, fechaLocal } from '@/lib/fechas';

interface Cita {
    id: number;
    numero: string;
    fecha_hora: string;
    duracion_min: number;
    estado: string;
    sujeto_nombre: string | null;
    venta_id: number | null;
    cliente: { id: number; nombres: string | null; apellidos: string | null; razon_social: string | null; numero_documento: string | null };
    profesional: { id: number; name: string } | null;
    local: { id: number; nombre: string };
    venta: { id: number; numero: string; total: string } | null;
    items: Array<{
        id: number;
        producto: { id: number; nombre: string; codigo: string | null };
        producto_unidad: { id: number; unidad_medida: { id: number; nombre: string } | null } | null;
    }>;
}

interface Resumen {
    total: number; programadas: number; confirmadas: number;
    en_atencion: number; completadas: number; canceladas: number; no_asistio: number;
}

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    estado: string | null; profesional_id: string | null; local_id: number | null;
}

interface Props extends PageProps {
    citas: Cita[];
    resumen: Resumen;
    profesionales: { id: number; name: string }[];
    locales: { id: number; nombre: string }[];
    agendaConfig: { sujeto_label: string | null; sujeto_requerido: boolean };
    filters: Filters;
    estadosLabels: Record<string, string>;
}

const fechaCorta = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleString('es-PE', { hour: '2-digit', minute: '2-digit' });
};

const fechaTitulo = (yyyymmdd: string) => {
    const d = new Date(yyyymmdd + 'T00:00:00');
    return d.toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
};

function nombreCliente(c: Cita['cliente']): string {
    if (c.razon_social) return c.razon_social;
    return [c.nombres, c.apellidos].filter(Boolean).join(' ') || 'Cliente';
}

const ESTADO_COLORES: Record<string, { bg: string; text: string; border: string }> = {
    programada:  { bg: '#dbeafe', text: '#1e40af', border: '#93c5fd' },
    confirmada:  { bg: '#d1fae5', text: '#065f46', border: '#6ee7b7' },
    en_atencion: { bg: '#fef3c7', text: '#92400e', border: '#fcd34d' },
    completada:  { bg: '#dcfce7', text: '#14532d', border: '#86efac' },
    no_asistio:  { bg: '#fee2e2', text: '#991b1b', border: '#fca5a5' },
    cancelada:   { bg: '#f3f4f6', text: '#6b7280', border: '#d1d5db' },
};

function ChipResumen({ label, count, active, onClick, color }: {
    label: string; count: number; active: boolean; onClick: () => void; color: string;
}) {
    return (
        <button
            onClick={onClick}
            className="rounded-lg border px-3 py-2 text-left transition-all hover:shadow-sm"
            style={{
                borderColor: active ? color : 'var(--color-border)',
                backgroundColor: active ? color + '20' : 'var(--color-surface)',
            }}
        >
            <div className="text-xs font-medium" style={{ color: active ? color : 'var(--color-text-muted)' }}>{label}</div>
            <div className="text-xl font-bold mt-0.5" style={{ color: active ? color : 'var(--color-text)' }}>{count}</div>
        </button>
    );
}

export default function AgendaIndex({
    citas, resumen, profesionales, locales, agendaConfig, filters, estadosLabels,
}: Props) {
    const { flash } = usePage<Props>().props;
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [confirmAccion, setConfirmAccion] = useState<{ cita: Cita; accion: string; titulo: string; pideMotivo?: boolean } | null>(null);
    const [motivo, setMotivo] = useState('');

    if (flash?.success) { toast.success(flash.success); flash.success = null as any; }
    if (flash?.error)   { toast.error(flash.error);     flash.error = null as any; }

    function aplicarFiltros() {
        router.get(route('agenda.index'), localFilters as any, { preserveState: true, preserveScroll: true });
    }

    function cambiarDia(delta: number) {
        const d = new Date(localFilters.fecha_desde + 'T00:00:00');
        d.setDate(d.getDate() + delta);
        const iso = fechaLocal(d);
        const nf = { ...localFilters, fecha_desde: iso, fecha_hasta: iso };
        setLocalFilters(nf);
        router.get(route('agenda.index'), nf as any, { preserveState: true, preserveScroll: true });
    }

    function irAHoy() {
        const iso = hoyLocal();
        const nf = { ...localFilters, fecha_desde: iso, fecha_hasta: iso };
        setLocalFilters(nf);
        router.get(route('agenda.index'), nf as any, { preserveState: true, preserveScroll: true });
    }

    function ejecutarAccion(cita: Cita, accion: string, payload: Record<string, string> = {}) {
        const routes: Record<string, string> = {
            confirmar:  route('agenda.confirmar', cita.id),
            iniciar:    route('agenda.iniciar', cita.id),
            cancelar:   route('agenda.cancelar', cita.id),
            no_asistio: route('agenda.no_asistio', cita.id),
            completar:  route('agenda.completar', cita.id),
        };
        router.post(routes[accion], payload, { preserveScroll: true });
    }

    const titulo = useMemo(() => {
        if (localFilters.fecha_desde === localFilters.fecha_hasta) return fechaTitulo(localFilters.fecha_desde);
        return `${fechaTitulo(localFilters.fecha_desde)} → ${fechaTitulo(localFilters.fecha_hasta)}`;
    }, [localFilters.fecha_desde, localFilters.fecha_hasta]);

    return (
        <AppLayout title="Agenda">
            <PageHeader
                title="Agenda"
                subtitle={`${resumen.total} cita${resumen.total !== 1 ? 's' : ''} en el rango seleccionado`}
                actions={
                    <Link href={route('agenda.create')}>
                        <Button startContent={<Plus size={14} />}>Nueva cita</Button>
                    </Link>
                }
            />

            {/* Navegación por día */}
            <div className="flex items-center gap-2 mb-4">
                <Button variant="ghost" size="sm" onClick={() => cambiarDia(-1)} startContent={<ChevronLeft size={14} />}>
                    Anterior
                </Button>
                <Button variant="ghost" size="sm" onClick={irAHoy} startContent={<Calendar size={14} />}>
                    Hoy
                </Button>
                <Button variant="ghost" size="sm" onClick={() => cambiarDia(1)} endContent={<ChevronRight size={14} />}>
                    Siguiente
                </Button>
                <h2 className="text-lg font-semibold ml-2 capitalize" style={{ color: 'var(--color-text)' }}>
                    {titulo}
                </h2>
            </div>

            {/* Resumen por estado (chips clickeables) */}
            <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-4">
                <ChipResumen
                    label="Total" count={resumen.total} color="var(--color-primary)"
                    active={!localFilters.estado}
                    onClick={() => { const nf = { ...localFilters, estado: null }; setLocalFilters(nf); router.get(route('agenda.index'), nf as any, { preserveState: true, preserveScroll: true }); }}
                />
                {Object.entries(estadosLabels).map(([key, label]) => (
                    <ChipResumen
                        key={key}
                        label={label}
                        count={(resumen as any)[key === 'no_asistio' ? 'no_asistio' : key + 's'] ?? (resumen as any)[key] ?? 0}
                        color={ESTADO_COLORES[key]?.text ?? '#666'}
                        active={localFilters.estado === key}
                        onClick={() => {
                            const nf = { ...localFilters, estado: localFilters.estado === key ? null : key };
                            setLocalFilters(nf);
                            router.get(route('agenda.index'), nf as any, { preserveState: true, preserveScroll: true });
                        }}
                    />
                ))}
            </div>

            {/* Filtros adicionales */}
            <div className="rounded-lg border p-3 mb-4 flex flex-wrap items-end gap-3"
                style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
                <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                <div>
                    <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Desde</label>
                    <input type="date" value={localFilters.fecha_desde}
                        onChange={e => setLocalFilters(f => ({ ...f, fecha_desde: e.target.value }))}
                        className="block mt-0.5 rounded-md border px-2 py-1 text-sm"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                </div>
                <div>
                    <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Hasta</label>
                    <input type="date" value={localFilters.fecha_hasta}
                        onChange={e => setLocalFilters(f => ({ ...f, fecha_hasta: e.target.value }))}
                        className="block mt-0.5 rounded-md border px-2 py-1 text-sm"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                </div>
                {profesionales.length > 1 && (
                    <div>
                        <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Profesional</label>
                        <select value={localFilters.profesional_id ?? ''}
                            onChange={e => setLocalFilters(f => ({ ...f, profesional_id: e.target.value || null }))}
                            className="block mt-0.5 rounded-md border px-2 py-1 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}>
                            <option value="">Todos</option>
                            {profesionales.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    </div>
                )}
                {locales.length > 1 && (
                    <div>
                        <label className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Local</label>
                        <select value={localFilters.local_id ?? ''}
                            onChange={e => setLocalFilters(f => ({ ...f, local_id: e.target.value ? Number(e.target.value) : null }))}
                            className="block mt-0.5 rounded-md border px-2 py-1 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}>
                            <option value="">Todos</option>
                            {locales.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                        </select>
                    </div>
                )}
                <Button size="sm" onClick={aplicarFiltros}>Aplicar</Button>
            </div>

            {/* Lista de citas */}
            {citas.length === 0 ? (
                <div className="rounded-lg border p-12 text-center"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
                    <Calendar size={40} className="mx-auto opacity-30 mb-3" style={{ color: 'var(--color-text-muted)' }} />
                    <p className="font-medium" style={{ color: 'var(--color-text)' }}>No hay citas en este rango.</p>
                    <Link href={route('agenda.create')}>
                        <Button variant="primary" className="mt-3" startContent={<Plus size={14} />}>Crear primera cita</Button>
                    </Link>
                </div>
            ) : (
                <div className="space-y-2">
                    {citas.map(cita => {
                        const colores = ESTADO_COLORES[cita.estado] ?? ESTADO_COLORES.programada;
                        const horaFin = new Date(new Date(cita.fecha_hora).getTime() + cita.duracion_min * 60000);

                        return (
                            <div key={cita.id} className="rounded-lg border overflow-hidden"
                                style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
                                <div className="flex flex-col sm:flex-row">
                                    {/* Banda de hora a la izquierda */}
                                    <div className="px-4 py-3 flex sm:flex-col items-center sm:items-start gap-2 sm:gap-0 sm:w-32 border-b sm:border-b-0 sm:border-r"
                                        style={{ backgroundColor: colores.bg, borderColor: 'var(--color-border)' }}>
                                        <Clock size={14} style={{ color: colores.text }} />
                                        <div>
                                            <div className="text-base font-bold" style={{ color: colores.text }}>
                                                {fechaCorta(cita.fecha_hora)}
                                            </div>
                                            <div className="text-xs" style={{ color: colores.text }}>
                                                hasta {horaFin.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                            </div>
                                            <div className="text-[10px] mt-0.5" style={{ color: colores.text }}>
                                                {cita.duracion_min} min
                                            </div>
                                        </div>
                                    </div>

                                    {/* Cuerpo */}
                                    <div className="flex-1 p-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <Link href={route('agenda.show', cita.id)}
                                                        className="text-sm font-bold hover:underline" style={{ color: 'var(--color-text)' }}>
                                                        {cita.numero}
                                                    </Link>
                                                    <Badge variant="secondary">{estadosLabels[cita.estado]}</Badge>
                                                </div>
                                                <div className="flex items-center gap-2 mt-1 text-sm">
                                                    <UserIcon size={12} style={{ color: 'var(--color-text-muted)' }} />
                                                    <span style={{ color: 'var(--color-text)' }}>{nombreCliente(cita.cliente)}</span>
                                                    {cita.sujeto_nombre && (
                                                        <>
                                                            <span style={{ color: 'var(--color-text-muted)' }}>·</span>
                                                            <span className="font-medium" style={{ color: 'var(--color-primary)' }}>
                                                                {agendaConfig.sujeto_label ?? 'Sujeto'}: {cita.sujeto_nombre}
                                                            </span>
                                                        </>
                                                    )}
                                                </div>
                                                {cita.profesional && (
                                                    <div className="flex items-center gap-2 mt-0.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                        <Briefcase size={10} />
                                                        Atiende: {cita.profesional.name}
                                                    </div>
                                                )}
                                                <div className="mt-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                    {cita.items.map(it => (
                                                        <span key={it.id} className="inline-block mr-2">
                                                            • {it.producto.nombre}
                                                            {it.producto_unidad?.unidad_medida && (
                                                                <span className="opacity-70"> ({it.producto_unidad.unidad_medida.nombre})</span>
                                                            )}
                                                        </span>
                                                    ))}
                                                </div>
                                                {cita.venta && (
                                                    <div className="mt-1 text-xs">
                                                        <Link href={route('ventas.show', cita.venta.id)} className="hover:underline" style={{ color: 'var(--color-success)' }}>
                                                            ✓ Cobrado en {cita.venta.numero} (S/. {Number(cita.venta.total).toFixed(2)})
                                                        </Link>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Acciones según estado */}
                                            <div className="flex flex-col gap-1.5">
                                                {cita.estado === 'programada' && (
                                                    <>
                                                        <Button size="xs" variant="success" startContent={<CheckCircle2 size={10} />}
                                                            onClick={() => ejecutarAccion(cita, 'confirmar')}>Confirmar</Button>
                                                        <Button size="xs" variant="warning" startContent={<PlayCircle size={10} />}
                                                            onClick={() => ejecutarAccion(cita, 'iniciar')}>Atender</Button>
                                                        <Button size="xs" variant="ghost" startContent={<AlertCircle size={10} />}
                                                            onClick={() => setConfirmAccion({ cita, accion: 'no_asistio', titulo: 'Marcar como no asistió' })}>No asistió</Button>
                                                    </>
                                                )}
                                                {cita.estado === 'confirmada' && (
                                                    <>
                                                        <Button size="xs" variant="warning" startContent={<PlayCircle size={10} />}
                                                            onClick={() => ejecutarAccion(cita, 'iniciar')}>Atender</Button>
                                                        <Button size="xs" variant="ghost" startContent={<AlertCircle size={10} />}
                                                            onClick={() => setConfirmAccion({ cita, accion: 'no_asistio', titulo: 'Marcar como no asistió' })}>No asistió</Button>
                                                    </>
                                                )}
                                                {cita.estado === 'en_atencion' && (
                                                    <Button size="xs" variant="primary" startContent={<ShoppingCart size={10} />}
                                                        onClick={() => ejecutarAccion(cita, 'completar')}>Cobrar</Button>
                                                )}
                                                {(cita.estado === 'programada' || cita.estado === 'confirmada' || cita.estado === 'en_atencion') && (
                                                    <Button size="xs" variant="danger" startContent={<XCircle size={10} />}
                                                        onClick={() => setConfirmAccion({ cita, accion: 'cancelar', titulo: 'Cancelar cita', pideMotivo: true })}>Cancelar</Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Modal de confirmación de acción destructiva */}
            {confirmAccion && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
                    onClick={() => setConfirmAccion(null)}>
                    <div className="rounded-lg border p-5 max-w-md w-full"
                        onClick={e => e.stopPropagation()}
                        style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
                        <h3 className="font-semibold text-lg" style={{ color: 'var(--color-text)' }}>{confirmAccion.titulo}</h3>
                        <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>
                            Cita: <strong>{confirmAccion.cita.numero}</strong> — {nombreCliente(confirmAccion.cita.cliente)}
                        </p>
                        {confirmAccion.pideMotivo && (
                            <div className="mt-3">
                                <label className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Motivo *</label>
                                <textarea value={motivo} onChange={e => setMotivo(e.target.value)}
                                    rows={3} placeholder="Ej: Cliente llamó a último momento"
                                    className="block w-full mt-1 rounded-md border px-3 py-2 text-sm"
                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                            </div>
                        )}
                        <div className="flex justify-end gap-2 mt-4">
                            <Button variant="ghost" onClick={() => { setConfirmAccion(null); setMotivo(''); }}>Cancelar</Button>
                            <Button variant="danger"
                                onClick={() => {
                                    if (confirmAccion.pideMotivo && !motivo.trim()) { toast.error('El motivo es obligatorio.'); return; }
                                    ejecutarAccion(confirmAccion.cita, confirmAccion.accion, confirmAccion.pideMotivo ? { motivo } : {});
                                    setConfirmAccion(null);
                                    setMotivo('');
                                }}>
                                Confirmar
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
