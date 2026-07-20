import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Modal from '@/Components/UI/Modal';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

type AfectaRestock = 'permite' | 'impide' | 'obliga_merma';

interface Motivo extends Record<string, unknown> {
    id: number;
    nombre: string;
    slug: string;
    afecta_restock_default: AfectaRestock;
    es_sistema: boolean;
    activo: boolean;
    orden: number;
}

interface Props extends PageProps {
    motivos: Motivo[];
}

interface FormState {
    nombre: string;
    afecta_restock_default: AfectaRestock;
    activo: boolean;
    orden: number | '';
}

const empty = (): FormState => ({ nombre: '', afecta_restock_default: 'permite', activo: true, orden: 100 });

const AFECTA_LABEL: Record<AfectaRestock, string> = {
    permite:      'Permite restock',
    impide:       'Impide restock',
    obliga_merma: 'Obliga merma',
};

export default function DevolucionMotivos({ motivos }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]         = useState(false);
    const [editing, setEditing]     = useState<Motivo | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]           = useState<FormState>(empty());
    const [errors, setErrors]       = useState<Partial<Record<keyof FormState, string>>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() { setEditing(null); setForm(empty()); setErrors({}); setModal(true); }
    function openEdit(m: Motivo) {
        setEditing(m);
        setForm({ nombre: m.nombre, afecta_restock_default: m.afecta_restock_default, activo: m.activo, orden: m.orden });
        setErrors({}); setModal(true);
    }

    function submit() {
        const url = editing
            ? route('configuracion.devolucion-motivos.update', editing.id)
            : route('configuracion.devolucion-motivos.store');
        const action = editing
            ? (cb: object) => router.put(url, form as never, cb as Parameters<typeof router.put>[2])
            : (cb: object) => router.post(url, form as never, cb as Parameters<typeof router.post>[2]);

        action({
            onSuccess: () => setModal(false),
            onError:   (errs: Record<string, string>) => setErrors(errs),
        });
    }

    function eliminar(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.devolucion-motivos.destroy', id));
    }

    const columns: Column<Motivo>[] = [
        { key: 'orden', label: '#', sortable: true, render: (m) => <span className="font-mono text-xs">{m.orden}</span> },
        { key: 'nombre', label: 'Nombre', sortable: true, render: (m) => <span className="font-medium">{m.nombre}</span> },
        { key: 'afecta_restock_default', label: 'Afecta restock',
          render: (m) => <Badge variant={m.afecta_restock_default === 'permite' ? 'success' : 'warning'}>
              {AFECTA_LABEL[m.afecta_restock_default]}
          </Badge> },
        { key: 'es_sistema', label: 'Origen',
          render: (m) => m.es_sistema ? <Badge variant="primary">Sistema</Badge> : <Badge variant="secondary">Personalizado</Badge> },
        { key: 'activo', label: 'Estado',
          render: (m) => <Badge variant={m.activo ? 'success' : 'secondary'}>{m.activo ? 'Activo' : 'Inactivo'}</Badge> },
        { key: 'acciones', label: 'Acciones', sortable: false,
          render: (m) => <TableActions onEdit={() => openEdit(m)} onDelete={m.es_sistema ? undefined : () => setConfirmId(m.id)} /> },
    ];

    return (
        <AppLayout title="Motivos de devolución">
            <PageHeader
                title="Motivos de devolución"
                subtitle='Catálogo configurable. "Afecta restock": permite (vuelve al stock), impide (queda aparte), obliga merma (no vuelve y registra pérdida).'
                actions={<Button onClick={openCreate}><Plus size={15} className="mr-1" />Nuevo motivo</Button>}
            />

            <Table data={motivos} columns={columns} emptyMessage="No hay motivos registrados" />

            <Modal isOpen={modal} onClose={() => setModal(false)}
                title={editing ? 'Editar motivo' : 'Nuevo motivo'}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModal(false)}>Cancelar</Button>
                        <Button onClick={submit}>Guardar</Button>
                    </>
                }>
                <div className="space-y-4">
                    <Input label="Nombre" required value={form.nombre} onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))} error={errors.nombre} />
                    <Select
                        label="Afecta restock por defecto"
                        value={form.afecta_restock_default}
                        onChange={v => setForm(f => ({ ...f, afecta_restock_default: v as AfectaRestock }))}
                        options={[
                            { value: 'permite',      label: 'Permite — el producto puede volver al stock' },
                            { value: 'impide',       label: 'Impide — no vuelve al stock automáticamente' },
                            { value: 'obliga_merma', label: 'Obliga merma — queda registrado como pérdida' },
                        ]}
                    />
                    <Input label="Orden" type="number" min="0"
                        value={form.orden}
                        onChange={e => setForm(f => ({ ...f, orden: e.target.value === '' ? '' : Number(e.target.value) }))} />
                    <Switch label="Activo" checked={form.activo} onChange={v => setForm(f => ({ ...f, activo: v }))} />
                    {editing?.es_sistema && (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            Motivo del sistema. Puedes editarlo pero no eliminarlo.
                        </p>
                    )}
                </div>
            </Modal>

            <Modal isOpen={confirmId !== null} onClose={() => setConfirmId(null)} title="Eliminar motivo" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && eliminar(confirmId)}>Eliminar</Button>
                    </>
                }>
                <p className="text-sm">Si tiene devoluciones registradas se desactivará en lugar de eliminarse.</p>
            </Modal>
        </AppLayout>
    );
}
