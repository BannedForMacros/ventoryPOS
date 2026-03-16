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
import type { Modulo, PageProps } from '@/types';

interface Props extends PageProps {
    modulos: Modulo[];
}

type FormData = {
    padre_id: string;
    nombre: string;
    slug: string;
    icono: string;
    ruta: string;
    orden: string;
    activo: boolean;
};

const emptyForm: FormData = {
    padre_id: '',
    nombre: '',
    slug: '',
    icono: '',
    ruta: '',
    orden: '0',
    activo: true,
};

function toSlug(str: string): string {
    return str
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s.]/g, '')
        .replace(/\s+/g, '.');
}

export default function Modulos({ modulos }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Modulo | null>(null);

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

    function openEdit(mod: Modulo) {
        setEditing(mod);
        setData({
            padre_id: mod.padre_id ? String(mod.padre_id) : '',
            nombre: mod.nombre,
            slug: mod.slug,
            icono: mod.icono ?? '',
            ruta: mod.ruta ?? '',
            orden: String(mod.orden),
            activo: mod.activo,
        });
        setModalOpen(true);
    }

    function handleNombreChange(nombre: string) {
        setData(prev => ({ ...prev, nombre, slug: toSlug(nombre) }));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.modulos.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.modulos.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.modulos.destroy', id));
    }

    const raices = modulos.filter(m => !m.padre_id);

    const padreOptions = [
        { value: '', label: 'Sin padre (módulo raíz)' },
        ...raices.map(m => ({ value: String(m.id), label: m.nombre })),
    ];

    const columns: Column<Modulo>[] = [
        {
            key: 'orden',
            label: 'Ord.',
            sortable: true,
            render: (mod) => <span className="font-mono text-xs">{mod.orden}</span>,
        },
        {
            key: 'nombre',
            label: 'Nombre',
            sortable: true,
            render: (mod) => <span className="font-medium">{mod.nombre}</span>,
        },
        {
            key: 'slug',
            label: 'Slug',
            render: (mod) => <span className="font-mono text-xs">{mod.slug}</span>,
        },
        {
            key: 'padre',
            label: 'Padre',
            render: (mod) => mod.padre ? mod.padre.nombre : '—',
        },
        {
            key: 'ruta',
            label: 'Ruta',
            render: (mod) => <span className="text-xs">{mod.ruta ?? '—'}</span>,
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (mod) => (
                <Badge variant={mod.activo ? 'success' : 'secondary'}>
                    {mod.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (mod) => (
                <TableActions
                    onEdit={() => openEdit(mod)}
                    onDelete={() => setConfirmId(mod.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Módulos">
            <PageHeader
                title="Módulos"
                subtitle="Estructura de módulos del sistema"
                actions={<Button onClick={openCreate}>+ Nuevo Módulo</Button>}
            />

            <Table
                data={modulos}
                columns={columns}
                searchPlaceholder="Buscar módulo..."
                emptyMessage="No hay módulos registrados"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Módulo' : 'Nuevo Módulo'}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear módulo'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <Select
                        label="Módulo padre"
                        options={padreOptions}
                        value={data.padre_id}
                        onChange={(value) => setData('padre_id', value as string)}
                        placeholder="Sin padre (módulo raíz)"
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Nombre"
                            required
                            value={data.nombre}
                            onChange={e => handleNombreChange(e.target.value)}
                            error={errors.nombre}
                        />
                        <Input
                            label="Slug"
                            required
                            value={data.slug}
                            onChange={e => setData('slug', e.target.value)}
                            error={errors.slug}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Ícono (lucide)"
                            value={data.icono}
                            onChange={e => setData('icono', e.target.value)}
                            hint="Ej: LayoutDashboard"
                        />
                        <Input
                            label="Ruta"
                            value={data.ruta}
                            onChange={e => setData('ruta', e.target.value)}
                            hint="Ej: /configuracion/empresas"
                        />
                    </div>
                    <Input
                        label="Orden"
                        type="number"
                        value={data.orden}
                        onChange={e => setData('orden', e.target.value)}
                        error={errors.orden}
                    />
                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                        />
                        Módulo activo
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
                    ¿Estás seguro de que deseas eliminar este módulo? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
