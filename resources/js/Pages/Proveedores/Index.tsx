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
import type { PageProps } from '@/types';
import FormProveedor, { ProveedorForm, emptyProveedor } from './Partials/FormProveedor';

interface Proveedor extends Record<string, unknown> {
    id:               number;
    tipo_documento:   string;
    numero_documento: string | null;
    razon_social:     string | null;
    nombre_comercial: string | null;
    contacto:         string | null;
    telefono:         string | null;
    email:            string | null;
    direccion:        string | null;
    observacion:      string | null;
    activo:           boolean;
}

interface Paginated { data: Proveedor[]; }

interface Props extends PageProps {
    proveedores: Paginated;
    busqueda:    string;
}

export default function ProveedoresIndex({ proveedores, busqueda }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]         = useState(false);
    const [editing, setEditing]     = useState<Proveedor | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]           = useState<ProveedorForm>(emptyProveedor());
    const [errors, setErrors]       = useState<Partial<Record<keyof ProveedorForm, string>>>({});
    const [saving, setSaving]       = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null); setForm(emptyProveedor()); setErrors({}); setModal(true);
    }

    function openEdit(p: Proveedor) {
        setEditing(p);
        setForm({
            tipo_documento:   p.tipo_documento,
            numero_documento: p.numero_documento ?? '',
            razon_social:     p.razon_social     ?? '',
            nombre_comercial: p.nombre_comercial ?? '',
            contacto:         p.contacto         ?? '',
            telefono:         p.telefono         ?? '',
            email:            p.email            ?? '',
            direccion:        p.direccion        ?? '',
            observacion:      p.observacion      ?? '',
            activo:           p.activo,
        });
        setErrors({}); setModal(true);
    }

    function submit() {
        setSaving(true);
        const opts = {
            onSuccess: () => { setModal(false); setSaving(false); },
            onError:   (errs: Record<string, string>) => { setErrors(errs); setSaving(false); },
        };

        if (editing) {
            router.put(route('proveedores.update', editing.id), form as never, opts);
        } else {
            router.post(route('proveedores.store'), form as never, opts);
        }
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('proveedores.destroy', id));
    }

    const columns: Column<Proveedor>[] = [
        {
            key: 'tipo_documento', label: 'Documento', sortable: true,
            render: (p) => (
                <span className="text-sm">
                    <span className="font-medium">{p.tipo_documento}</span>
                    {p.numero_documento && (
                        <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>{p.numero_documento}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'razon_social', label: 'Razón social', sortable: true,
            render: (p) => (
                <div className="text-sm">
                    <div className="font-medium">{p.razon_social ?? p.nombre_comercial ?? '—'}</div>
                    {p.nombre_comercial && p.razon_social && (
                        <div className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{p.nombre_comercial}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'contacto', label: 'Contacto',
            render: (p) => p.contacto
                ? <span>{p.contacto}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'telefono', label: 'Teléfono',
            render: (p) => p.telefono
                ? <span>{p.telefono}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (p) => (
                <Badge variant={p.activo ? 'success' : 'secondary'}>
                    {p.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (p) => (
                <TableActions
                    onEdit={() => openEdit(p)}
                    onDelete={() => setConfirmId(p.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Proveedores">
            <PageHeader
                title="Proveedores"
                subtitle="Gestiona los proveedores de tu empresa. Las compras (entradas) se vinculan a un proveedor."
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo proveedor
                    </Button>
                }
            />

            <Table
                data={proveedores.data}
                columns={columns}
                searchPlaceholder="Buscar por razón social, RUC o contacto..."
                emptyMessage="No hay proveedores registrados"
                initialSearch={busqueda}
                onServerSearch={(t) => router.get(route('proveedores.index'),
                    { busqueda: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })}
            />

            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar proveedor' : 'Nuevo proveedor'}
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModal(false)}>Cancelar</Button>
                        <Button onClick={submit} disabled={saving}>
                            {saving ? 'Guardando...' : 'Guardar'}
                        </Button>
                    </>
                }
            >
                <FormProveedor form={form} setForm={setForm} errors={errors} disabled={saving} />
            </Modal>

            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar proveedor"
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
                    Si tiene entradas registradas se marcará como inactivo. De lo contrario, se eliminará.
                </p>
            </Modal>
        </AppLayout>
    );
}
