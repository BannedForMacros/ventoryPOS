import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import TableActions from '@/Components/UI/TableActions';
import Modal from '@/Components/UI/Modal';
import type { DescuentoConcepto, PageProps } from '@/types';

interface Props extends PageProps {
    conceptos: DescuentoConcepto[];
}

interface Form {
    nombre:              string;
    requiere_aprobacion: boolean;
    activo:              boolean;
}

const emptyForm = (): Form => ({ nombre: '', requiere_aprobacion: false, activo: true });

export default function DescuentoConceptos({ conceptos, flash }: Props) {
    const [modalOpen, setModalOpen]   = useState(false);
    const [editing, setEditing]       = useState<DescuentoConcepto | null>(null);
    const [form, setForm]             = useState<Form>(emptyForm());
    const [confirmId, setConfirmId]   = useState<number | null>(null);
    const [loading, setLoading]       = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm());
        setModalOpen(true);
    }

    function openEdit(c: DescuentoConcepto) {
        setEditing(c);
        setForm({ nombre: c.nombre, requiere_aprobacion: c.requiere_aprobacion, activo: c.activo });
        setModalOpen(true);
    }

    function handleSubmit() {
        setLoading(true);
        if (editing) {
            router.put(route('configuracion.descuento-conceptos.update', editing.id), form as any, {
                onFinish: () => { setLoading(false); setModalOpen(false); },
            });
        } else {
            router.post(route('configuracion.descuento-conceptos.store'), form as any, {
                onFinish: () => { setLoading(false); setModalOpen(false); },
            });
        }
    }

    function handleDelete(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.descuento-conceptos.destroy', id));
    }

    const columns: Column<DescuentoConcepto>[] = [
        { key: 'nombre', label: 'Nombre' },
        {
            key: 'requiere_aprobacion',
            label: 'Requiere aprobación',
            render: c => (
                <Badge variant={c.requiere_aprobacion ? 'warning' : 'secondary'}>
                    {c.requiere_aprobacion ? 'Sí' : 'No'}
                </Badge>
            ),
        },
        {
            key: 'activo',
            label: 'Estado',
            render: c => <Badge variant={c.activo ? 'success' : 'danger'}>{c.activo ? 'Activo' : 'Inactivo'}</Badge>,
        },
        {
            key: 'id',
            label: '',
            render: c => (
                <TableActions
                    onEdit={() => openEdit(c)}
                    onDelete={() => setConfirmId(c.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Conceptos de descuento">
            <PageHeader
                title="Conceptos de descuento"
                subtitle={`${conceptos.length} conceptos`}
                actions={
                    <Button variant="primary" startContent={<Plus size={16} />} onClick={openCreate}>
                        Nuevo concepto
                    </Button>
                }
            />

            <Table data={conceptos} columns={columns} />

            {/* Modal form */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar concepto' : 'Nuevo concepto'}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button variant="primary" onClick={handleSubmit} loading={loading}>
                            {editing ? 'Guardar cambios' : 'Crear'}
                        </Button>
                    </>
                }
            >
                <div className="flex flex-col gap-4">
                    <div>
                        <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Nombre</label>
                        <input
                            type="text"
                            value={form.nombre}
                            onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                            className="w-full border rounded-lg px-3 py-2 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            placeholder="Ej: Descuento por volumen"
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Requiere aprobación</p>
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>El descuento necesita ser aprobado por un supervisor</p>
                        </div>
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                className="sr-only peer"
                                checked={form.requiere_aprobacion}
                                onChange={e => setForm(f => ({ ...f, requiere_aprobacion: e.target.checked }))}
                            />
                            <div className="w-11 h-6 rounded-full bg-gray-200 peer-checked:bg-blue-500 transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:transition-transform after:duration-200 peer-checked:after:translate-x-5" />
                        </label>
                    </div>

                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Activo</p>
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                className="sr-only peer"
                                checked={form.activo}
                                onChange={e => setForm(f => ({ ...f, activo: e.target.checked }))}
                            />
                            <div className="w-11 h-6 rounded-full bg-gray-200 peer-checked:bg-blue-500 transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:transition-transform after:duration-200 peer-checked:after:translate-x-5" />
                        </label>
                    </div>
                </div>
            </Modal>

            {/* Confirmación de eliminación */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Eliminar concepto"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => handleDelete(confirmId!)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    ¿Estás seguro de que deseas eliminar este concepto? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
