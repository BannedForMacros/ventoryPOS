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
import Checkbox from '@/Components/UI/Checkbox';
import TableActions from '@/Components/UI/TableActions';
import type { Empresa, PageProps } from '@/types';

interface Props extends PageProps {
    empresas: Empresa[];
}

type FormData = {
    razon_social: string;
    nombre_comercial: string;
    ruc: string;
    direccion: string;
    telefono: string;
    email: string;
    activo: boolean;
};

const emptyForm: FormData = {
    razon_social: '',
    nombre_comercial: '',
    ruc: '',
    direccion: '',
    telefono: '',
    email: '',
    activo: true,
};

export default function Empresas({ empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Empresa | null>(null);

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

    function openEdit(emp: Empresa) {
        setEditing(emp);
        setData({
            razon_social: emp.razon_social,
            nombre_comercial: emp.nombre_comercial ?? '',
            ruc: emp.ruc,
            direccion: emp.direccion ?? '',
            telefono: emp.telefono ?? '',
            email: emp.email ?? '',
            activo: emp.activo,
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.empresas.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.empresas.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.empresas.destroy', id));
    }

    const columns: Column<Empresa>[] = [
        {
            key: 'ruc',
            label: 'RUC',
            sortable: true,
            render: (emp) => (
                <span className="font-mono text-xs">{emp.ruc}</span>
            ),
        },
        {
            key: 'razon_social',
            label: 'Razón Social',
            sortable: true,
            render: (emp) => (
                <span className="font-medium">{emp.razon_social}</span>
            ),
        },
        {
            key: 'nombre_comercial',
            label: 'Nombre Comercial',
            sortable: true,
            render: (emp) => emp.nombre_comercial ?? '—',
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (emp) => (
                <Badge variant={emp.activo ? 'success' : 'secondary'}>
                    {emp.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (emp) => (
                <TableActions
                    onEdit={() => openEdit(emp)}
                    onDelete={() => setConfirmId(emp.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Empresas">
            <PageHeader
                title="Empresas"
                subtitle="Gestión de empresas registradas"
                actions={<Button onClick={openCreate}>+ Nueva Empresa</Button>}
            />

            <Table
                data={empresas}
                columns={columns}
                searchPlaceholder="Buscar empresa..."
                emptyMessage="No hay empresas registradas"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Empresa' : 'Nueva Empresa'}
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear empresa'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Razón Social" required value={data.razon_social} onChange={e => setData('razon_social', e.target.value)} error={errors.razon_social} />
                        <Input label="Nombre Comercial" value={data.nombre_comercial} onChange={e => setData('nombre_comercial', e.target.value)} error={errors.nombre_comercial} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="RUC" required maxLength={11} value={data.ruc} onChange={e => setData('ruc', e.target.value)} error={errors.ruc} />
                        <Input label="Email" type="email" value={data.email} onChange={e => setData('email', e.target.value)} error={errors.email} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Dirección" value={data.direccion} onChange={e => setData('direccion', e.target.value)} error={errors.direccion} />
                        <Input label="Teléfono" value={data.telefono} onChange={e => setData('telefono', e.target.value)} error={errors.telefono} />
                    </div>
                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                        />
                        Empresa activa
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
                    ¿Estás seguro de que deseas eliminar esta empresa? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}