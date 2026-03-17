import { useEffect, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import Badge from '@/Components/UI/Badge';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface UnidadMedida extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    nombre: string;
    abreviatura: string;
    activo: boolean;
}

interface Props extends PageProps {
    unidades: UnidadMedida[];
}

type FormData = {
    nombre: string;
    abreviatura: string;
    activo: boolean;
};

const empty: FormData = { nombre: '', abreviatura: '', activo: true };

export default function UnidadesMedida({ unidades }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing]     = useState<UnidadMedida | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>(empty);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    }, [flash]);

    function openCreate() { setEditing(null); reset(); setModalOpen(true); }

    function openEdit(u: UnidadMedida) {
        setEditing(u);
        setData({ nombre: u.nombre, abreviatura: u.abreviatura, activo: u.activo });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('catalogo.unidades-medida.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('catalogo.unidades-medida.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('catalogo.unidades-medida.destroy', id));
    }

    const columns: Column<UnidadMedida>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (u) => <span className="font-medium">{u.nombre}</span>,
        },
        {
            key: 'abreviatura', label: 'Abreviatura', sortable: true,
            render: (u) => (
                <span
                    className="font-mono text-xs px-2 py-0.5 rounded"
                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                >
                    {u.abreviatura}
                </span>
            ),
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (u) => (
                <Badge variant={u.activo ? 'success' : 'secondary'}>
                    {u.activo ? 'Activa' : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (u) => (
                <TableActions onEdit={() => openEdit(u)} onDelete={() => setConfirmId(u.id)} />
            ),
        },
    ];

    return (
        <AppLayout title="Unidades de medida">
            <PageHeader
                title="Unidades de medida"
                subtitle="Unidades usadas en productos del catálogo"
                actions={<Button onClick={openCreate}>+ Nueva Unidad</Button>}
            />

            <Table
                data={unidades}
                columns={columns}
                searchPlaceholder="Buscar unidad..."
                emptyMessage="No hay unidades de medida registradas"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Unidad' : 'Nueva Unidad de Medida'}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear unidad'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label="Nombre"
                        required
                        placeholder="ej: Kilogramo, Litro, Unidad"
                        value={data.nombre}
                        onChange={e => setData('nombre', e.target.value)}
                        error={errors.nombre}
                    />
                    <Input
                        label="Abreviatura"
                        required
                        placeholder="ej: kg, L, und"
                        value={data.abreviatura}
                        onChange={e => setData('abreviatura', e.target.value)}
                        error={errors.abreviatura}
                    />
                    <Switch
                        label="Unidad activa"
                        checked={data.activo}
                        onChange={v => setData('activo', v)}
                    />
                </form>
            </Modal>

            {/* Confirm Delete */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Confirmar acción"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && destroy(confirmId)}>
                            Confirmar
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Si la unidad está en uso por algún producto, se desactivará. Si no, se eliminará permanentemente.
                </p>
            </Modal>
        </AppLayout>
    );
}
