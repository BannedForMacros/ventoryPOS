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
import type { Empresa, PageProps, Rol } from '@/types';

interface Props extends PageProps {
    roles: Rol[];
    empresas: Empresa[];
}

type FormData = {
    empresa_id: string;
    nombre: string;
    descripcion: string;
    es_admin: boolean;
    activo: boolean;
};

const emptyForm: FormData = {
    empresa_id: '',
    nombre: '',
    descripcion: '',
    es_admin: false,
    activo: true,
};

export default function Roles({ roles, empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Rol | null>(null);

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

    function openEdit(rol: Rol) {
        setEditing(rol);
        setData({
            empresa_id: String(rol.empresa_id),
            nombre: rol.nombre,
            descripcion: rol.descripcion ?? '',
            es_admin: rol.es_admin,
            activo: rol.activo,
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.roles.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.roles.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.roles.destroy', id));
    }

    const empresaOptions = empresas.map(e => ({
        value: String(e.id),
        label: e.nombre_comercial ?? e.razon_social,
    }));

    const columns: Column<Rol>[] = [
        {
            key: 'empresa',
            label: 'Empresa',
            sortable: true,
            searchKey: 'empresa_id',
            render: (rol) => rol.empresa
                ? (rol.empresa.nombre_comercial ?? rol.empresa.razon_social)
                : '—',
        },
        {
            key: 'nombre',
            label: 'Nombre',
            sortable: true,
            render: (rol) => <span className="font-medium">{rol.nombre}</span>,
        },
        {
            key: 'descripcion',
            label: 'Descripción',
            render: (rol) => rol.descripcion ?? '—',
        },
        {
            key: 'es_admin',
            label: 'Admin Global',
            sortable: true,
            render: (rol) => (
                <Badge variant={rol.es_admin ? 'warning' : 'secondary'}>
                    {rol.es_admin ? 'Sí' : 'No'}
                </Badge>
            ),
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (rol) => (
                <Badge variant={rol.activo ? 'success' : 'secondary'}>
                    {rol.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (rol) => (
                <TableActions
                    onEdit={() => openEdit(rol)}
                    onDelete={() => setConfirmId(rol.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Roles">
            <PageHeader
                title="Roles"
                subtitle="Gestión de roles de acceso"
                actions={<Button onClick={openCreate}>+ Nuevo Rol</Button>}
            />

            <Table
                data={roles}
                columns={columns}
                searchPlaceholder="Buscar rol..."
                emptyMessage="No hay roles registrados"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Rol' : 'Nuevo Rol'}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear rol'}
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
                    <div className="flex gap-6">
                        <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                            <Checkbox
                                name="es_admin"
                                checked={data.es_admin}
                                onChange={e => setData('es_admin', e.target.checked)}
                            />
                            Admin global
                        </label>
                        <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                            <Checkbox
                                name="activo"
                                checked={data.activo}
                                onChange={e => setData('activo', e.target.checked)}
                            />
                            Rol activo
                        </label>
                    </div>
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
                    ¿Estás seguro de que deseas eliminar este rol? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
