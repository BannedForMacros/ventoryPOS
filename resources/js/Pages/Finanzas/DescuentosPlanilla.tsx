import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, CheckCircle2, Ban } from 'lucide-react';
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
}

const hoy = () => new Date().toISOString().slice(0, 10);
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const fdate = (s: string) => new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE');

export default function DescuentosPlanilla({ descuentos, porTrabajador, estado, trabajadores }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo] = useState(false);
    const [aplicando, setAplicando]   = useState<Descuento | null>(null);
    const [anulando, setAnulando]     = useState<Descuento | null>(null);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});
    const [form, setForm]             = useState({ user_id: '', fecha: hoy(), monto: '', motivo: '' });
    const [fechaAplicacion, setFechaAplicacion] = useState(hoy());
    const [motivoAnular, setMotivoAnular]       = useState('');

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
        { key: 'monto', label: 'Monto', render: (d) => <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(d.monto)}</span> },
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
            render: (d) => d.estado === 'pendiente' && (
                <div className="flex items-center gap-1.5">
                    <button onClick={() => { setFechaAplicacion(hoy()); setAplicando(d); }}
                        className="p-1.5 rounded-lg hover:bg-black/5" title="Marcar como aplicado en planilla"
                        style={{ color: 'var(--color-success)' }}>
                        <CheckCircle2 size={15} />
                    </button>
                    <button onClick={() => { setErrors({}); setMotivoAnular(''); setAnulando(d); }}
                        className="p-1.5 rounded-lg hover:bg-black/5" title="Anular"
                        style={{ color: 'var(--color-danger)' }}>
                        <Ban size={15} />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Descuentos de planilla">
            <PageHeader
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
        </AppLayout>
    );
}
