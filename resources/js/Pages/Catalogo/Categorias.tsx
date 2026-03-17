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

interface Categoria extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    nombre: string;
    descripcion: string | null;
    activo: boolean;
}

interface Props extends PageProps {
    categorias: Categoria[];
}

type FormData = {
    nombre: string;
    descripcion: string;
    activo: boolean;
};

const empty: FormData = { nombre: '', descripcion: '', activo: true };

export default function Categorias({ categorias }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing]     = useState<Categoria | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>(empty);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        reset();
        setModalOpen(true);
    }

    function openEdit(cat: Categoria) {
        setEditing(cat);
        setData({ nombre: cat.nombre, descripcion: cat.descripcion ?? '', activo: cat.activo });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('catalogo.categorias.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('catalogo.categorias.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('catalogo.categorias.destroy', id));
    }

    const columns: Column<Categoria>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (c) => <span className="font-medium">{c.nombre}</span>,
        },
        {
            key: 'descripcion', label: 'Descripción',
            render: (c) => <span style={{ color: 'var(--color-text-muted)' }}>{c.descripcion ?? '—'}</span>,
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (c) => (
                <Badge variant={c.activo ? 'success' : 'secondary'}>
                    {c.activo ? 'Activa' : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (c) => (
                <TableActions onEdit={() => openEdit(c)} onDelete={() => setConfirmId(c.id)} />
            ),
        },
    ];

    return (
        <AppLayout title="Categorías">
            <PageHeader
                title="Categorías"
                subtitle="Clasificación de productos y servicios"
                actions={<Button onClick={openCreate}>+ Nueva Categoría</Button>}
            />

            <Table
                data={categorias}
                columns={columns}
                searchPlaceholder="Buscar categoría..."
                emptyMessage="No hay categorías registradas"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Categoría' : 'Nueva Categoría'}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear categoría'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label="Nombre"
                        required
                        value={data.nombre}
                        onChange={e => setData('nombre', e.target.value)}
                        error={errors.nombre}
                    />
                    <Input
                        label="Descripción"
                        value={data.descripcion}
                        onChange={e => setData('descripcion', e.target.value)}
                        error={errors.descripcion}
                    />
                    <Switch
                        label="Categoría activa"
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
                    Si la categoría tiene productos asociados, se desactivará. Si no, se eliminará permanentemente.
                </p>
            </Modal>
        </AppLayout>
    );
}
