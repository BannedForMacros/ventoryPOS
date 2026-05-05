import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import Modal from '@/Components/UI/Modal';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface Tipo extends Record<string, unknown> {
    id: number;
    nombre: string;
    slug: string;
    es_sistema: boolean;
    activo: boolean;
    orden: number;
}

interface Props extends PageProps {
    tipos: Tipo[];
}

interface FormState {
    nombre: string;
    activo: boolean;
    orden: number | '';
}

const empty = (): FormState => ({ nombre: '', activo: true, orden: 100 });

export default function SalidasTipos({ tipos }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]     = useState(false);
    const [editing, setEditing] = useState<Tipo | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]       = useState<FormState>(empty());
    const [errors, setErrors]   = useState<Partial<Record<keyof FormState, string>>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        setForm(empty());
        setErrors({});
        setModal(true);
    }

    function openEdit(t: Tipo) {
        setEditing(t);
        setForm({ nombre: t.nombre, activo: t.activo, orden: t.orden });
        setErrors({});
        setModal(true);
    }

    function submit() {
        const url = editing
            ? route('configuracion.salidas-tipos.update', editing.id)
            : route('configuracion.salidas-tipos.store');

        const payload = { nombre: form.nombre, activo: form.activo, orden: form.orden };

        const action = editing
            ? (cb: object) => router.put(url, payload, cb as Parameters<typeof router.put>[2])
            : (cb: object) => router.post(url, payload, cb as Parameters<typeof router.post>[2]);

        action({
            onSuccess: () => setModal(false),
            onError:   (errs: Record<string, string>) => setErrors(errs),
        });
    }

    function eliminar(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.salidas-tipos.destroy', id));
    }

    const columns: Column<Tipo>[] = [
        {
            key: 'orden', label: '#', sortable: true,
            render: (t) => <span className="text-sm font-mono">{t.orden}</span>,
        },
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (t) => <span className="font-medium">{t.nombre}</span>,
        },
        {
            key: 'slug', label: 'Slug',
            render: (t) => <span className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{t.slug}</span>,
        },
        {
            key: 'es_sistema', label: 'Origen',
            render: (t) => t.es_sistema
                ? <Badge variant="primary">Sistema</Badge>
                : <Badge variant="secondary">Personalizado</Badge>,
        },
        {
            key: 'activo', label: 'Estado',
            render: (t) => (
                <Badge variant={t.activo ? 'success' : 'secondary'}>
                    {t.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (t) => (
                <TableActions
                    onEdit={() => openEdit(t)}
                    onDelete={t.es_sistema ? undefined : () => setConfirmId(t.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Tipos de salida">
            <PageHeader
                title="Tipos de salida"
                subtitle="Catálogo de motivos para salidas de almacén (mermas, ajustes, bajas, consumo interno, etc.)"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1" /> Nuevo tipo
                    </Button>
                }
            />

            <Table
                data={tipos}
                columns={columns}
                searchPlaceholder="Buscar tipo..."
                emptyMessage="No hay tipos de salida registrados"
            />

            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar tipo' : 'Nuevo tipo'}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModal(false)}>Cancelar</Button>
                        <Button onClick={submit}>Guardar</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Input
                        label="Nombre"
                        required
                        value={form.nombre}
                        onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                        error={errors.nombre}
                    />
                    <Input
                        label="Orden"
                        type="number"
                        min="0"
                        value={form.orden}
                        onChange={e => setForm(f => ({ ...f, orden: e.target.value === '' ? '' : Number(e.target.value) }))}
                        hint="Define el orden en el que aparece en los listados"
                    />
                    <Switch label="Activo" checked={form.activo} onChange={v => setForm(f => ({ ...f, activo: v }))} />

                    {editing?.es_sistema && (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            Este tipo es del sistema. Puedes editar su nombre y orden, pero no eliminarlo.
                        </p>
                    )}
                </div>
            </Modal>

            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Eliminar tipo"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && eliminar(confirmId)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Si el tipo tiene salidas registradas se desactivará en lugar de eliminarse.
                </p>
            </Modal>
        </AppLayout>
    );
}
