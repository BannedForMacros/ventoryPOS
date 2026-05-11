import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Calendar, Clock, User as UserIcon, Briefcase, FileText,
    CheckCircle2, PlayCircle, XCircle, AlertCircle, ShoppingCart, Pencil,
    Receipt,
} from 'lucide-react';
import toast from 'react-hot-toast';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface CitaDetalle {
    id: number;
    numero: string;
    fecha_hora: string;
    duracion_min: number;
    estado: string;
    estado_label?: string;
    observaciones: string | null;
    sujeto_nombre: string | null;
    sujeto_descripcion: string | null;
    motivo_cancelacion: string | null;
    confirmada_at: string | null;
    iniciada_at: string | null;
    completada_at: string | null;
    cancelada_at: string | null;
    venta_id: number | null;
    cliente: { id: number; nombres: string | null; apellidos: string | null; razon_social: string | null; tipo_documento: string | null; numero_documento: string | null; telefono: string | null; email: string | null; };
    profesional: { id: number; name: string; email: string } | null;
    creador: { id: number; name: string } | null;
    local: { id: number; nombre: string };
    venta: { id: number; numero: string; total: string; fecha_venta: string; estado: string } | null;
    items: Array<{
        id: number; cantidad: string; duracion_min: number; precio_estimado: string; observaciones: string | null;
        producto: { id: number; nombre: string; codigo: string | null; tipo: string };
        producto_unidad: { id: number; unidad_medida: { id: number; nombre: string; abreviatura: string } | null } | null;
    }>;
}

interface Props extends PageProps {
    cita: CitaDetalle;
    agendaConfig: { sujeto_label: string | null; sujeto_requerido: boolean };
}

const ESTADOS_LABELS: Record<string, string> = {
    programada: 'Programada', confirmada: 'Confirmada', en_atencion: 'En atención',
    completada: 'Completada', no_asistio: 'No asistió', cancelada: 'Cancelada',
};

const ESTADO_COLORES: Record<string, string> = {
    programada:  '#1e40af', confirmada: '#065f46', en_atencion: '#92400e',
    completada:  '#14532d', no_asistio: '#991b1b', cancelada: '#6b7280',
};

function nombreCliente(c: CitaDetalle['cliente']): string {
    if (c.razon_social) return c.razon_social;
    return [c.nombres, c.apellidos].filter(Boolean).join(' ') || 'Cliente';
}

const fechaCompleta = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const fechaCorta = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';

export default function AgendaShow({ cita, agendaConfig }: Props) {
    const { flash } = usePage<Props>().props;
    const [confirmAccion, setConfirmAccion] = useState<{ accion: string; titulo: string; pideMotivo?: boolean } | null>(null);
    const [motivo, setMotivo] = useState('');

    if (flash?.success) { toast.success(flash.success); flash.success = null as any; }
    if (flash?.error)   { toast.error(flash.error);     flash.error = null as any; }

    function ejecutarAccion(accion: string, payload: Record<string, string> = {}) {
        const routes: Record<string, string> = {
            confirmar:  route('agenda.confirmar', cita.id),
            iniciar:    route('agenda.iniciar', cita.id),
            cancelar:   route('agenda.cancelar', cita.id),
            no_asistio: route('agenda.no_asistio', cita.id),
            completar:  route('agenda.completar', cita.id),
        };
        router.post(routes[accion], payload, { preserveScroll: true });
    }

    const totalEstimado = cita.items.reduce(
        (s, it) => s + Number(it.precio_estimado) * Number(it.cantidad), 0
    );

    const esActiva = ['programada', 'confirmada', 'en_atencion'].includes(cita.estado);
    const horaFin = new Date(new Date(cita.fecha_hora).getTime() + cita.duracion_min * 60000);

    return (
        <AppLayout title={`Cita ${cita.numero}`}>
            <PageHeader
                title={`Cita ${cita.numero}`}
                subtitle={fechaCompleta(cita.fecha_hora)}
                backHref={route('agenda.index')}
                actions={
                    esActiva && (
                        <Link href={route('agenda.edit', cita.id)}>
                            <Button variant="ghost" startContent={<Pencil size={14} />}>Editar</Button>
                        </Link>
                    )
                }
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 max-w-6xl mx-auto">
                {/* ── Columna principal: detalles ── */}
                <div className="lg:col-span-2 space-y-4">

                    {/* Estado + horario */}
                    <div className="rounded-2xl border p-5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <div className="flex items-start justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-semibold uppercase" style={{ color: 'var(--color-text-muted)' }}>Estado</span>
                                </div>
                                <div className="mt-1">
                                    <span className="inline-block text-base font-bold rounded-full px-3 py-1"
                                        style={{
                                            backgroundColor: ESTADO_COLORES[cita.estado] + '20',
                                            color: ESTADO_COLORES[cita.estado],
                                        }}>
                                        {ESTADOS_LABELS[cita.estado]}
                                    </span>
                                </div>
                                {cita.motivo_cancelacion && (
                                    <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>
                                        Motivo: <em>{cita.motivo_cancelacion}</em>
                                    </p>
                                )}
                            </div>
                            <div className="text-right">
                                <div className="flex items-center gap-1.5 text-sm justify-end" style={{ color: 'var(--color-text-muted)' }}>
                                    <Clock size={14} />
                                    {new Date(cita.fecha_hora).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                    {' → '}
                                    {horaFin.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                </div>
                                <div className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    {cita.duracion_min} minutos · {cita.local.nombre}
                                </div>
                            </div>
                        </div>

                        {/* Línea de tiempo de transiciones */}
                        <div className="mt-4 pt-4 border-t flex flex-wrap gap-x-6 gap-y-1 text-xs"
                            style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                            {cita.confirmada_at && <span>✓ Confirmada: {fechaCorta(cita.confirmada_at)}</span>}
                            {cita.iniciada_at   && <span>▶ Iniciada: {fechaCorta(cita.iniciada_at)}</span>}
                            {cita.completada_at && <span>✓ Completada: {fechaCorta(cita.completada_at)}</span>}
                            {cita.cancelada_at  && <span>✗ Finalizada: {fechaCorta(cita.cancelada_at)}</span>}
                        </div>
                    </div>

                    {/* Cliente */}
                    <div className="rounded-2xl border p-5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <h3 className="text-sm font-semibold uppercase mb-3" style={{ color: 'var(--color-text-muted)' }}>
                            <UserIcon size={14} className="inline mr-1.5 -mt-0.5" />Cliente
                        </h3>
                        <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{nombreCliente(cita.cliente)}</p>
                        <div className="text-sm mt-1 space-y-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            {cita.cliente.numero_documento && <p>{cita.cliente.tipo_documento}: {cita.cliente.numero_documento}</p>}
                            {cita.cliente.telefono && <p>📞 {cita.cliente.telefono}</p>}
                            {cita.cliente.email && <p>✉ {cita.cliente.email}</p>}
                        </div>
                    </div>

                    {/* Sujeto (si configurado) */}
                    {agendaConfig.sujeto_label && cita.sujeto_nombre && (
                        <div className="rounded-2xl border p-5"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <h3 className="text-sm font-semibold uppercase mb-3" style={{ color: 'var(--color-text-muted)' }}>
                                {agendaConfig.sujeto_label}
                            </h3>
                            <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{cita.sujeto_nombre}</p>
                            {cita.sujeto_descripcion && (
                                <p className="text-sm mt-1 whitespace-pre-wrap" style={{ color: 'var(--color-text-muted)' }}>
                                    {cita.sujeto_descripcion}
                                </p>
                            )}
                        </div>
                    )}

                    {/* Items reservados */}
                    <div className="rounded-2xl border p-5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <h3 className="text-sm font-semibold uppercase mb-3" style={{ color: 'var(--color-text-muted)' }}>
                            <Briefcase size={14} className="inline mr-1.5 -mt-0.5" />
                            Servicios reservados ({cita.items.length})
                        </h3>
                        <div className="rounded-lg overflow-hidden border" style={{ borderColor: 'var(--color-border)' }}>
                            <table className="w-full text-sm">
                                <thead style={{ backgroundColor: 'var(--color-bg)' }}>
                                    <tr style={{ color: 'var(--color-text-muted)' }}>
                                        <th className="text-left px-3 py-2 text-xs font-semibold uppercase">Producto / Servicio</th>
                                        <th className="text-left px-3 py-2 text-xs font-semibold uppercase">Presentación</th>
                                        <th className="text-right px-3 py-2 text-xs font-semibold uppercase">Cant.</th>
                                        <th className="text-right px-3 py-2 text-xs font-semibold uppercase">Min</th>
                                        <th className="text-right px-3 py-2 text-xs font-semibold uppercase">P. Estim.</th>
                                        <th className="text-right px-3 py-2 text-xs font-semibold uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cita.items.map(it => (
                                        <tr key={it.id} className="border-t" style={{ borderColor: 'var(--color-border)' }}>
                                            <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>
                                                {it.producto.nombre}
                                                {it.producto.tipo === 'servicio' && (
                                                    <span className="ml-1.5 text-[10px] uppercase tracking-wide opacity-60">Servicio</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {it.producto_unidad?.unidad_medida?.nombre ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>
                                                {Number(it.cantidad).toFixed(0)}
                                            </td>
                                            <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text-muted)' }}>
                                                {it.duracion_min}
                                            </td>
                                            <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text)' }}>
                                                S/. {Number(it.precio_estimado).toFixed(2)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold" style={{ color: 'var(--color-text)' }}>
                                                S/. {(Number(it.precio_estimado) * Number(it.cantidad)).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                        <td colSpan={5} className="px-3 py-2 text-right text-xs font-semibold uppercase"
                                            style={{ color: 'var(--color-text-muted)' }}>Total estimado</td>
                                        <td className="px-3 py-2 text-right text-base font-bold" style={{ color: 'var(--color-primary)' }}>
                                            S/. {totalEstimado.toFixed(2)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p className="text-xs mt-2" style={{ color: 'var(--color-text-muted)' }}>
                            * Este es el precio reservado. Al cobrar puedes agregar/quitar items y el total final puede variar.
                        </p>
                    </div>

                    {cita.observaciones && (
                        <div className="rounded-2xl border p-5"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <h3 className="text-sm font-semibold uppercase mb-2" style={{ color: 'var(--color-text-muted)' }}>
                                <FileText size={14} className="inline mr-1.5 -mt-0.5" />Observaciones
                            </h3>
                            <p className="text-sm whitespace-pre-wrap" style={{ color: 'var(--color-text)' }}>{cita.observaciones}</p>
                        </div>
                    )}
                </div>

                {/* ── Sidebar derecho: acciones e info ── */}
                <div className="space-y-4">
                    {/* Acciones según estado */}
                    {esActiva && (
                        <div className="rounded-2xl border p-5"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <h3 className="text-sm font-semibold uppercase mb-3" style={{ color: 'var(--color-text-muted)' }}>Acciones</h3>
                            <div className="space-y-2">
                                {cita.estado === 'programada' && (
                                    <Button variant="success" className="w-full" startContent={<CheckCircle2 size={14} />}
                                        onClick={() => ejecutarAccion('confirmar')}>
                                        Confirmar cita
                                    </Button>
                                )}
                                {(cita.estado === 'programada' || cita.estado === 'confirmada') && (
                                    <Button variant="warning" className="w-full" startContent={<PlayCircle size={14} />}
                                        onClick={() => ejecutarAccion('iniciar')}>
                                        Iniciar atención
                                    </Button>
                                )}
                                {cita.estado === 'en_atencion' && (
                                    <Button variant="primary" className="w-full" startContent={<ShoppingCart size={14} />}
                                        onClick={() => ejecutarAccion('completar')}>
                                        Completar y cobrar
                                    </Button>
                                )}
                                {(cita.estado === 'programada' || cita.estado === 'confirmada') && (
                                    <Button variant="ghost" className="w-full" startContent={<AlertCircle size={14} />}
                                        onClick={() => setConfirmAccion({ accion: 'no_asistio', titulo: 'Marcar como no asistió' })}>
                                        Marcar no asistió
                                    </Button>
                                )}
                                <Button variant="danger" className="w-full" startContent={<XCircle size={14} />}
                                    onClick={() => setConfirmAccion({ accion: 'cancelar', titulo: 'Cancelar cita', pideMotivo: true })}>
                                    Cancelar cita
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Vínculo a venta si ya se cobró */}
                    {cita.venta && (
                        <div className="rounded-2xl border p-5"
                            style={{
                                borderColor: 'var(--color-success)',
                                backgroundColor: 'color-mix(in srgb, var(--color-success) 5%, var(--color-surface))',
                            }}>
                            <h3 className="text-sm font-semibold uppercase mb-2" style={{ color: 'var(--color-success)' }}>
                                <Receipt size={14} className="inline mr-1.5 -mt-0.5" />Venta generada
                            </h3>
                            <Link href={route('ventas.show', cita.venta.id)}>
                                <Button variant="success" className="w-full">
                                    {cita.venta.numero} · S/. {Number(cita.venta.total).toFixed(2)}
                                </Button>
                            </Link>
                        </div>
                    )}

                    {/* Asignaciones */}
                    <div className="rounded-2xl border p-5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <h3 className="text-sm font-semibold uppercase mb-3" style={{ color: 'var(--color-text-muted)' }}>Asignaciones</h3>
                        <div className="space-y-2 text-sm">
                            <div>
                                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Profesional</p>
                                <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                    {cita.profesional?.name ?? <em style={{ color: 'var(--color-text-muted)' }}>Sin asignar</em>}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Creada por</p>
                                <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                    {cita.creador?.name ?? '—'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal de confirmación */}
            {confirmAccion && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
                    onClick={() => setConfirmAccion(null)}>
                    <div className="rounded-lg border p-5 max-w-md w-full"
                        onClick={e => e.stopPropagation()}
                        style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
                        <h3 className="font-semibold text-lg" style={{ color: 'var(--color-text)' }}>{confirmAccion.titulo}</h3>
                        <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>
                            Cita: <strong>{cita.numero}</strong>
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
                                    ejecutarAccion(confirmAccion.accion, confirmAccion.pideMotivo ? { motivo } : {});
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
