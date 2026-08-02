import { useEffect, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ArrowLeft } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
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
    empresa: Empresa;
    usuarios: User[];
    locales: Local[];
    roles: Rol[];
}

type FormData = {
    local_id: string;
    rol_id: string;
    name: string;
    email: string;
    password: string;
    activo: boolean;
};

const emptyForm: FormData = {
    local_id: '',
    rol_id: '',
    name: '',
    email: '',
    password: '',
    activo: true,
};

export default function Usuarios({ empresa, usuarios, locales, roles }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<User | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>(emptyForm);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const nombreEmpresa = empresa.nombre_comercial ?? empresa.razon_social;

    function openCreate() {
        setEditing(null);
        reset();
        setModalOpen(true);
    }

    function openEdit(u: User) {
        setEditing(u);
        setData({
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
            put(route('admin.usuarios.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('admin.empresas.usuarios.store', empresa.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('admin.usuarios.destroy', id));
    }

    const localOptions = [
        { value: '', label: 'Sin local' },
        ...locales.map(l => ({ value: String(l.id), label: l.nombre })),
    ];

    const rolOptions = roles.map(r => ({
        value: String(r.id),
        label: r.es_admin ? `${r.nombre} (admin)` : r.nombre,
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
            key: 'rol',
            label: 'Rol',
            sortKey: 'rol.nombre',
            render: (u) => u.rol
                ? (u.rol.es_admin ? <Badge variant="primary">{u.rol.nombre}</Badge> : u.rol.nombre)
                : '—',
        },
        {
            key: 'local',
            label: 'Local',
            sortKey: 'local.nombre',
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
            sortable: false,
            render: (u) => (
                <TableActions
                    onEdit={() => openEdit(u)}
                    onDelete={() => setConfirmId(u.id)}
                />
            ),
        },
    ];

    return (
        <AdminLayout title={`Usuarios — ${nombreEmpresa}`}>
            <div className="mb-3">
                <Link
                    href={route('admin.empresas.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium transition-opacity hover:opacity-75"
                    style={{ color: 'var(--color-text-muted)' }}
                >
                    <ArrowLeft size={15} />
                    Empresas
                </Link>
            </div>

            <PageHeader
                title={`Usuarios de ${nombreEmpresa}`}
                subtitle={`RUC ${empresa.ruc}`}
                actions={<Button onClick={openCreate}>+ Nuevo Usuario</Button>}
            />

            <Table
                data={usuarios}
                columns={columns}
                searchPlaceholder="Buscar usuario..."
                emptyMessage="Esta empresa no tiene usuarios"
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
        </AdminLayout>
    );
}
