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
import type { Empresa, Local, PageProps, Rol, User } from '@/types';

interface Props extends PageProps {
    usuarios: User[];
    empresas: Empresa[];
    locales: Local[];
    roles: Rol[];
}

type FormData = {
    empresa_id: string;
    local_id: string;
    rol_id: string;
    name: string;
    email: string;
    password: string;
    activo: boolean;
};

const emptyForm: FormData = {
    empresa_id: '',
    local_id: '',
    rol_id: '',
    name: '',
    email: '',
    password: '',
    activo: true,
};

export default function Usuarios({ usuarios, empresas, locales, roles }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<User | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>(emptyForm);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const filteredLocales = data.empresa_id
        ? locales.filter(l => String(l.empresa_id) === data.empresa_id)
        : locales;

    const filteredRoles = data.empresa_id
        ? roles.filter(r => String(r.empresa_id) === data.empresa_id)
        : roles;

    function openCreate() {
        setEditing(null);
        reset();
        setModalOpen(true);
    }

    function openEdit(u: User) {
        setEditing(u);
        setData({
            empresa_id: String(u.empresa_id),
            local_id: u.local_id ? String(u.local_id) : '',
            rol_id: u.rol_id ? String(u.rol_id) : '',
            name: u.name,
            email: u.email,
            password: '',
            activo: u.activo,
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.usuarios.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.usuarios.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.usuarios.destroy', id));
    }

    const empresaOptions = empresas.map(e => ({
        value: String(e.id),
        label: e.nombre_comercial ?? e.razon_social,
    }));

    const localOptions = [
        { value: '', label: 'Sin local' },
        ...filteredLocales.map(l => ({ value: String(l.id), label: l.nombre })),
    ];

    const rolOptions = filteredRoles.map(r => ({
        value: String(r.id),
        label: r.nombre,
    }));

    const columns: Column<User>[] = [
        {
            key: 'name',
            label: 'Nombre',
            sortable: true,
            render: (u) => <span className="font-medium">{u.name}</span>,
        },
        {
            key: 'email',
            label: 'Email',
            sortable: true,
            render: (u) => <span className="font-mono text-xs">{u.email}</span>,
        },
        {
            key: 'empresa',
            label: 'Empresa',
            searchKey: 'empresa_id',
            render: (u) => u.empresa
                ? (u.empresa.nombre_comercial ?? u.empresa.razon_social)
                : '—',
        },
        {
            key: 'rol',
            label: 'Rol',
            render: (u) => u.rol ? u.rol.nombre : '—',
        },
        {
            key: 'local',
            label: 'Local',
            render: (u) => u.local ? u.local.nombre : '—',
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (u) => (
                <Badge variant={u.activo ? 'success' : 'secondary'}>
                    {u.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (u) => (
                <TableActions
                    onEdit={() => openEdit(u)}
                    onDelete={() => setConfirmId(u.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Usuarios">
            <PageHeader
                title="Usuarios"
                subtitle="Gestión de usuarios del sistema"
                actions={<Button onClick={openCreate}>+ Nuevo Usuario</Button>}
            />

            <Table
                data={usuarios}
                columns={columns}
                searchPlaceholder="Buscar usuario..."
                emptyMessage="No hay usuarios registrados"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Usuario' : 'Nuevo Usuario'}
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear usuario'}
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
                        onChange={(value) => {
                            setData('empresa_id', value as string);
                            setData('local_id', '');
                            setData('rol_id', '');
                        }}
                        placeholder="Seleccione empresa"
                        error={errors.empresa_id}
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Select
                            label="Local"
                            options={localOptions}
                            value={data.local_id}
                            onChange={(value) => setData('local_id', value as string)}
                            placeholder="Sin local"
                            error={errors.local_id}
                        />
                        <Select
                            label="Rol"
                            required
                            options={rolOptions}
                            value={data.rol_id}
                            onChange={(value) => setData('rol_id', value as string)}
                            placeholder="Seleccione rol"
                            error={errors.rol_id}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Nombre"
                            required
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            error={errors.name}
                        />
                        <Input
                            label="Email"
                            type="email"
                            required
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            error={errors.email}
                        />
                    </div>
                    {!editing && (
                        <Input
                            label="Contraseña"
                            type="password"
                            required
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            error={errors.password}
                        />
                    )}
                    {editing && (
                        <Input
                            label="Nueva contraseña (dejar en blanco para no cambiar)"
                            type="password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            error={errors.password}
                        />
                    )}
                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                        />
                        Usuario activo
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
                    ¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
