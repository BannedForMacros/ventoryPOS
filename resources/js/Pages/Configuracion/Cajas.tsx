import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import FormCaja, { CajaForm, emptyCaja } from './Partials/FormCaja';
import type { Caja, Local, PageProps } from '@/types';

interface Props extends PageProps {
    cajas:   Caja[];
    locales: Local[];
}

export default function Cajas({ cajas, locales }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]         = useState(false);
    const [editing, setEditing]     = useState<Caja | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]           = useState<CajaForm>(emptyCaja());
    const [errors, setErrors]       = useState<Partial<Record<keyof CajaForm, string>>>({});
    const [saving, setSaving]       = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null); setForm(emptyCaja()); setErrors({}); setModal(true);
    }

    function openEdit(c: Caja) {
        setEditing(c);
        setForm({
            local_id:                  c.local_id,
            nombre:                    c.nombre,
            caja_chica_activa:         c.caja_chica_activa,
            caja_chica_monto_sugerido: c.caja_chica_monto_sugerido,
            caja_chica_en_arqueo:      c.caja_chica_en_arqueo,
            activo:                    c.activo,
        });
        setErrors({}); setModal(true);
    }

    function submit() {
        setSaving(true);
        const opts = {
            onSuccess: () => { setModal(false); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        };

        if (editing) {
            router.put(route('configuracion.cajas.update', editing.id), form as any, opts);
        } else {
            router.post(route('configuracion.cajas.store'), form as any, opts);
        }
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.cajas.destroy', id));
    }

    const columns: Column<Caja>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (c) => <span className="font-medium">{c.nombre}</span>,
        },
        {
            key: 'local', label: 'Local',
            render: (c) => <span>{c.local?.nombre ?? '—'}</span>,
        },
        {
            key: 'caja_chica_activa', label: 'Caja chica',
            render: (c) => (
                <Badge variant={c.caja_chica_activa ? 'warning' : 'secondary'}>
                    {c.caja_chica_activa ? 'Activa' : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'turno', label: 'Turno',
            render: (c) => (
                <Badge variant={(c as any).tiene_turno_abierto ? 'success' : 'secondary'}>
                    {(c as any).tiene_turno_abierto ? 'Abierto' : 'Cerrado'}
                </Badge>
            ),
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (c) => (
                <Badge variant={c.activo ? 'success' : 'secondary'}>
                    {c.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (c) => (
                <TableActions
                    onEdit={() => openEdit(c)}
                    onDelete={c.activo ? () => setConfirmId(c.id) : undefined}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Cajas">
            <PageHeader
                title="Cajas"
                subtitle="Administra los puntos de cobro de tus locales"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nueva caja
                    </Button>
                }
            />

            <Table
                data={cajas}
                columns={columns}
                searchPlaceholder="Buscar caja..."
                emptyMessage="No hay cajas configuradas"
            />

            {/* Modal crear / editar */}
            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar caja' : 'Nueva caja'}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModal(false)}>Cancelar</Button>
                        <Button onClick={submit} disabled={saving}>
                            {saving ? 'Guardando...' : 'Guardar'}
                        </Button>
                    </>
                }
            >
                <FormCaja
                    form={form}
                    setForm={setForm}
                    errors={errors}
                    locales={locales}
                    editando={!!editing}
                    disabled={saving}
                />
            </Modal>

            {/* Confirmar desactivar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar caja"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && deactivate(confirmId)}>
                            Desactivar
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    La caja será marcada como inactiva. No podrá abrirse un turno en ella.
                </p>
            </Modal>
        </AppLayout>
    );
}
