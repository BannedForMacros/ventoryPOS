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
import Select from '@/Components/UI/Select';
import Checkbox from '@/Components/UI/Checkbox';
import TableActions from '@/Components/UI/TableActions';
import type { Empresa, Local, PageProps } from '@/types';

interface Props extends PageProps {
    locales: Local[];
    empresas: Empresa[];
}

type FormData = {
    empresa_id: string;
    nombre: string;
    direccion: string;
    telefono: string;
    tipo: string;
    activo: boolean;
};

const emptyForm: FormData = {
    empresa_id: '',
    nombre: '',
    direccion: '',
    telefono: '',
    tipo: '',
    activo: true,
};

const TIPO_OPTIONS = [
    { value: 'almacen', label: 'Almacén' },
    { value: 'tienda', label: 'Tienda' },
];

export default function Locales({ locales, empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Local | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>(emptyForm);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        reset();
        setModalOpen(true);
    }

    function openEdit(local: Local) {
        setEditing(local);
        setData({
            empresa_id: String(local.empresa_id),
            nombre: local.nombre,
            direccion: local.direccion ?? '',
            telefono: local.telefono ?? '',
            tipo: local.tipo ?? '',
            activo: local.activo,
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.locales.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.locales.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.locales.destroy', id));
    }

    const empresaOptions = empresas.map(e => ({
        value: String(e.id),
        label: e.nombre_comercial ?? e.razon_social,
    }));

    const columns: Column<Local>[] = [
        {
            key: 'nombre',
            label: 'Nombre',
            sortable: true,
            render: (local) => <span className="font-medium">{local.nombre}</span>,
        },
        {
            key: 'empresa',
            label: 'Empresa',
            sortable: true,
            searchKey: 'empresa_id',
            render: (local) => local.empresa
                ? (local.empresa.nombre_comercial ?? local.empresa.razon_social)
                : '—',
        },
        {
            key: 'direccion',
            label: 'Dirección',
            render: (local) => local.direccion ?? '—',
        },
        {
            key: 'tipo',
            label: 'Tipo',
            sortable: true,
            render: (local) => local.tipo
                ? TIPO_OPTIONS.find(t => t.value === local.tipo)?.label ?? local.tipo
                : '—',
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (local) => (
                <Badge variant={local.activo ? 'success' : 'secondary'}>
                    {local.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (local) => (
                <TableActions
                    onEdit={() => openEdit(local)}
                    onDelete={() => setConfirmId(local.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Locales">
            <PageHeader
                title="Locales"
                subtitle="Gestión de locales o sucursales"
                actions={<Button onClick={openCreate}>+ Nuevo Local</Button>}
            />

            <Table
                data={locales}
                columns={columns}
                searchPlaceholder="Buscar local..."
                emptyMessage="No hay locales registrados"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Local' : 'Nuevo Local'}
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear local'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <Select
                        label="Empresa"
                        required
                        options={empresaOptions}
                        value={data.empresa_id}
                        onChange={(value) => setData('empresa_id', value as string)}
                        placeholder="Seleccione empresa"
                        error={errors.empresa_id}
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Nombre"
                            required
                            value={data.nombre}
                            onChange={e => setData('nombre', e.target.value)}
                            error={errors.nombre}
                        />
                        <Select
                            label="Tipo"
                            required
                            options={TIPO_OPTIONS}
                            value={data.tipo}
                            onChange={(value) => setData('tipo', value as string)}
                            placeholder="Seleccione tipo"
                            error={errors.tipo}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Dirección"
                            value={data.direccion}
                            onChange={e => setData('direccion', e.target.value)}
                            error={errors.direccion}
                        />
                        <Input
                            label="Teléfono"
                            value={data.telefono}
                            onChange={e => setData('telefono', e.target.value)}
                            error={errors.telefono}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                        />
                        Local activo
                    </label>
                </form>
            </Modal>

            {/* Confirm Delete */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Confirmar eliminación"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && destroy(confirmId)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    ¿Estás seguro de que deseas eliminar este local? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
