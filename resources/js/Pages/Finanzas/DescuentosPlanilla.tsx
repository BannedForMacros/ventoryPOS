import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, CheckCircle2, Ban, UserMinus, Pencil, Undo2, RotateCcw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Tabs from '@/Components/UI/Tabs';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';

interface Descuento extends Record<string, unknown> {
    id: number;
    fecha: string;
    monto: string;
    motivo: string;
    ref_tipo: string | null;
    estado: string;
    fecha_aplicacion: string | null;
    observacion: string | null;
    trabajador?: { id: number; name: string } | null;
    registrado_por_user?: { name: string } | null;
    registrado_por?: unknown;
    aplicado_por_user?: { name: string } | null;
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    descuentos: Paginado<Descuento>;
    porTrabajador: { user_id: number; nombre: string | null; total: number }[];
    estado: string;
    trabajadores: { id: number; name: string }[];
    puede: { editar: boolean; eliminar: boolean };
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const fdate = (s: string) => new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE');

export default function DescuentosPlanilla({ descuentos, porTrabajador, estado, trabajadores, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo] = useState(false);
    const [aplicando, setAplicando]   = useState<Descuento | null>(null);
    const [anulando, setAnulando]     = useState<Descuento | null>(null);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});
    const [form, setForm]             = useState({ user_id: '', fecha: hoy(), monto: '', motivo: '' });
    const [fechaAplicacion, setFechaAplicacion] = useState(hoy());
    const [motivoAnular, setMotivoAnular]       = useState('');
    // Edición / desaplicación / reactivación (según permisos).
    const [editando, setEditando]         = useState<Descuento | null>(null);
    const [desaplicando, setDesaplicando] = useState<Descuento | null>(null);
    const [reactivando, setReactivando]   = useState<Descuento | null>(null);
    const [motivoAccion, setMotivoAccion] = useState('');
    const [formEditar, setFormEditar]     = useState({ user_id: '', fecha: hoy(), monto: '', motivo: '' });

    function abrirEditar(d: Descuento) {
        setErrors({});
        setFormEditar({
            user_id: d.trabajador ? String(d.trabajador.id) : '',
            fecha:   d.fecha.slice(0, 10),
            monto:   String(Number(d.monto)),
            motivo:  d.motivo,
        });
        setEditando(d);
    }

    function submitEditar() {
        if (!editando) return;
        setSaving(true);
        router.put(route('finanzas.planilla-descuentos.update', editando.id), formEditar as any, {
            onSuccess: () => { setEditando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitDesaplicar() {
        if (!desaplicando) return;
        setSaving(true);
        router.post(route('finanzas.planilla-descuentos.desaplicar', desaplicando.id), { motivo: motivoAccion.trim() } as any, {
            onSuccess: () => { setDesaplicando(null); setMotivoAccion(''); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitReactivar() {
        if (!reactivando) return;
        setSaving(true);
        router.post(route('finanzas.planilla-descuentos.reactivar', reactivando.id), { motivo: motivoAccion.trim() } as any, {
            onSuccess: () => { setReactivando(null); setMotivoAccion(''); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function submitNuevo() {
        setSaving(true);
        router.post(route('finanzas.planilla-descuentos.store'), form as any, {
            onSuccess: () => { setModalNuevo(false); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAplicar() {
        if (!aplicando) return;
        setSaving(true);
        router.post(route('finanzas.planilla-descuentos.aplicar', aplicando.id), { fecha_aplicacion: fechaAplicacion } as any, {
            onSuccess: () => { setAplicando(null); setSaving(false); },
            onError:   () => setSaving(false),
        });
    }

    function submitAnular() {
        if (!anulando) return;
        setSaving(true);
        router.post(route('finanzas.planilla-descuentos.anular', anulando.id), { motivo: motivoAnular } as any, {
            onSuccess: () => { setAnulando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<Descuento>[] = [
        { key: 'fecha', label: 'Fecha', sortable: true, render: (d) => <span className="text-sm">{fdate(d.fecha)}</span> },
        { key: 'trabajador', label: 'Trabajador', render: (d) => <span className="font-medium">{d.trabajador?.name ?? '—'}</span> },
        {
            key: 'motivo', label: 'Motivo',
            render: (d) => (
                <span className="text-sm">
                    {d.motivo}
                    {d.ref_tipo === 'turno_consolidacion' && <Badge variant="primary" className="ml-1">Consolidación</Badge>}
                </span>
            ),
        },
        { key: 'monto', label: 'Monto', align: 'right', render: (d) => <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(d.monto)}</span> },
        {
            key: 'estado', label: 'Estado',
            render: (d) => (
                <Badge variant={d.estado === 'pendiente' ? 'warning' : d.estado === 'aplicado' ? 'success' : 'secondary'}>
                    {d.estado === 'pendiente' ? 'Pendiente' : d.estado === 'aplicado' ? `Aplicado ${d.fecha_aplicacion ? fdate(d.fecha_aplicacion) : ''}` : 'Anulado'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (d) => (
                <div className="flex items-center gap-1.5">
                    {d.estado === 'pendiente' && (
                        <>
                            <button onClick={() => { setFechaAplicacion(hoy()); setAplicando(d); }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Marcar como aplicado en planilla"
                                style={{ color: 'var(--color-success)' }}>
                                <CheckCircle2 size={15} />
                            </button>
                            {puede.editar && (
                                <button onClick={() => abrirEditar(d)}
                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Editar descuento"
                                    style={{ color: 'var(--color-primary)' }}>
                                    <Pencil size={15} />
                                </button>
                            )}
                            <button onClick={() => { setErrors({}); setMotivoAnular(''); setAnulando(d); }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Anular"
                                style={{ color: 'var(--color-danger)' }}>
                                <Ban size={15} />
                            </button>
                        </>
                    )}
                    {d.estado === 'aplicado' && puede.editar && (
                        <button onClick={() => { setErrors({}); setMotivoAccion(''); setDesaplicando(d); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Desaplicar (volver a pendiente)"
                            style={{ color: 'var(--color-warning, #d97706)' }}>
                            <Undo2 size={15} />
                        </button>
                    )}
                    {d.estado === 'anulado' && puede.editar && (
                        <button onClick={() => { setErrors({}); setMotivoAccion(''); setReactivando(d); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Reactivar descuento"
                            style={{ color: 'var(--color-primary)' }}>
                            <RotateCcw size={15} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Descuentos de planilla">
            <PageHeader
                icon={<UserMinus size={22} />}
                title="Descuentos de planilla"
                subtitle="Faltantes de caja y otros cargos a descontar al pagar la planilla"
                actions={
                    <Button onClick={() => { setErrors({}); setForm({ user_id: '', fecha: hoy(), monto: '', motivo: '' }); setModalNuevo(true); }}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo descuento
                    </Button>
                }
            />

            {/* Resumen pendiente por trabajador */}
            {porTrabajador.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                    {porTrabajador.map(t => (
                        <div key={t.user_id} className="rounded-2xl px-4 py-3"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <p className="text-[11px] font-semibold uppercase tracking-wider truncate" style={{ color: 'var(--color-text-muted)' }}>
                                {t.nombre ?? '—'}
                            </p>
                            <p className="text-base font-bold" style={{ color: 'var(--color-danger)' }}>{money(t.total)}</p>
                            <p className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>pendiente de descontar</p>
                        </div>
                    ))}
                </div>
            )}

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'pendientes', label: 'Pendientes' },
                        { value: 'todos',      label: 'Todos' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.planilla-descuentos.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table data={descuentos.data} columns={columns}
                searchPlaceholder="Buscar trabajador o motivo..."
                emptyMessage="No hay descuentos registrados" />

            {/* Modal nuevo */}
            <Modal isOpen={modalNuevo} onClose={() => setModalNuevo(false)} title="Nuevo descuento de planilla" size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalNuevo(false)}>Cancelar</Button>
                        <Button onClick={submitNuevo} disabled={saving}>{saving ? 'Guardando...' : 'Guardar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Select label="Trabajador" required
                        options={trabajadores.map(t => ({ value: String(t.id), label: t.name }))}
                        value={form.user_id}
                        onChange={v => setForm(f => ({ ...f, user_id: String(v) }))}
                        placeholder="— Seleccionar —"
                        error={errors.user_id}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Fecha" required type="date" value={form.fecha}
                            onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Monto" required type="number" min="0.01" step="0.01" value={form.monto}
                            onChange={e => setForm(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                    </div>
                    <Input label="Motivo" required placeholder='Ej: "Préstamo personal", "Rotura de mercadería"'
                        value={form.motivo}
                        onChange={e => setForm(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                </div>
            </Modal>

            {/* Modal aplicar */}
            <Modal isOpen={aplicando !== null} onClose={() => setAplicando(null)}
                title={aplicando ? `Aplicar descuento — ${aplicando.trabajador?.name}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAplicando(null)}>Cancelar</Button>
                        <Button onClick={submitAplicar} disabled={saving}>{saving ? 'Guardando...' : 'Confirmar'}</Button>
                    </>
                }
            >
                {aplicando && (
                    <div className="space-y-4">
                        <Callout variant="success" title="Confirmar aplicación" aside={money(aplicando.monto)}>
                            Confirmas que el descuento ya se aplicó en la planilla pagada a <strong>{aplicando.trabajador?.name}</strong>.
                        </Callout>
                        <Input label="Fecha de aplicación" required type="date" value={fechaAplicacion}
                            onChange={e => setFechaAplicacion(e.target.value)} />
                    </div>
                )}
            </Modal>

            {/* Modal anular */}
            <Modal isOpen={anulando !== null} onClose={() => setAnulando(null)}
                title="Anular descuento" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulando(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitAnular} disabled={saving}>{saving ? 'Anulando...' : 'Anular'}</Button>
                    </>
                }
            >
                <Input label="Motivo" required value={motivoAnular}
                    onChange={e => setMotivoAnular(e.target.value)} error={errors.motivo} />
            </Modal>

            {/* Modal editar (solo pendientes) */}
            <Modal isOpen={editando !== null} onClose={() => setEditando(null)}
                title={editando ? `Editar descuento — ${editando.trabajador?.name ?? ''}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button onClick={submitEditar} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</Button>
                    </>
                }
            >
                {editando && (
                    <div className="space-y-4">
                        <Select label="Trabajador" required
                            options={trabajadores.map(t => ({ value: String(t.id), label: t.name }))}
                            value={formEditar.user_id}
                            onChange={v => setFormEditar(f => ({ ...f, user_id: String(v) }))}
                            placeholder="— Seleccionar —"
                            error={errors.user_id}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Fecha" required type="date" value={formEditar.fecha}
                                onChange={e => setFormEditar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                            <Input label="Monto" required type="number" min="0.01" step="0.01" value={formEditar.monto}
                                onChange={e => setFormEditar(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                        </div>
                        <Input label="Motivo" required placeholder='Ej: "Préstamo personal", "Rotura de mercadería"'
                            value={formEditar.motivo}
                            onChange={e => setFormEditar(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                    </div>
                )}
            </Modal>

            {/* Modal desaplicar (solo aplicados) */}
            <Modal isOpen={desaplicando !== null} onClose={() => setDesaplicando(null)}
                title={desaplicando ? `Desaplicar descuento — ${desaplicando.trabajador?.name ?? ''}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDesaplicando(null)}>Cancelar</Button>
                        <Button onClick={submitDesaplicar} disabled={saving || motivoAccion.trim().length < 5}>
                            {saving ? 'Guardando...' : 'Desaplicar'}
                        </Button>
                    </>
                }
            >
                {desaplicando && (
                    <div className="space-y-3">
                        <Callout variant="warning" aside={money(desaplicando.monto)}>
                            El descuento volverá a estado <strong>pendiente</strong> (no se aplicó en la planilla).
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivoAccion}
                            onChange={e => setMotivoAccion(e.target.value)}
                            placeholder="Ej.: se marcó aplicado por error"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>

            {/* Modal reactivar (solo anulados) */}
            <Modal isOpen={reactivando !== null} onClose={() => setReactivando(null)}
                title={reactivando ? `Reactivar descuento — ${reactivando.trabajador?.name ?? ''}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setReactivando(null)}>Cancelar</Button>
                        <Button onClick={submitReactivar} disabled={saving || motivoAccion.trim().length < 5}>
                            {saving ? 'Guardando...' : 'Reactivar'}
                        </Button>
                    </>
                }
            >
                {reactivando && (
                    <div className="space-y-3">
                        <Callout variant="info" aside={money(reactivando.monto)}>
                            El descuento volverá a estado <strong>pendiente</strong> de descontar en planilla.
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivoAccion}
                            onChange={e => setMotivoAccion(e.target.value)}
                            placeholder="Ej.: se anuló por error"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
