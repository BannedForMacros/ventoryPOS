import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Star } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';
import FormCliente, { ClienteForm, emptyCliente } from './Partials/FormCliente';

interface Cliente extends Record<string, unknown> {
    id:               number;
    tipo_documento:   string;
    numero_documento: string | null;
    nombres:          string | null;
    apellidos:        string | null;
    razon_social:     string | null;
    telefono:         string | null;
    email:            string | null;
    direccion:        string | null;
    fecha_nacimiento: string | null;
    activo:           boolean;
    es_cliente_general: boolean;
    nombre_completo:  string;
}

interface Paginated { data: Cliente[]; }

interface Props extends PageProps {
    clientes: Paginated;
    busqueda: string;
}

export default function ClientesIndex({ clientes, busqueda }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]           = useState(false);
    const [editing, setEditing]       = useState<Cliente | null>(null);
    const [confirmId, setConfirmId]   = useState<number | null>(null);
    const [form, setForm]             = useState<ClienteForm>(emptyCliente());
    const [errors, setErrors]         = useState<Partial<Record<keyof ClienteForm, string>>>({});
    const [saving, setSaving]         = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null); setForm(emptyCliente()); setErrors({}); setModal(true);
    }

    function openEdit(c: Cliente) {
        setEditing(c);
        setForm({
            tipo_documento:   c.tipo_documento,
            numero_documento: c.numero_documento ?? '',
            nombres:          c.nombres          ?? '',
            apellidos:        c.apellidos        ?? '',
            razon_social:     c.razon_social     ?? '',
            telefono:         c.telefono         ?? '',
            email:            c.email            ?? '',
            direccion:        c.direccion        ?? '',
            fecha_nacimiento: c.fecha_nacimiento ?? '',
            activo:           c.activo,
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
            router.put(route('clientes.update', editing.id), form as any, opts);
        } else {
            router.post(route('clientes.store'), form as any, opts);
        }
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('clientes.destroy', id));
    }

    const columns: Column<Cliente>[] = [
        {
            key: 'tipo_documento', label: 'Documento', sortable: true,
            render: (c) => (
                <span className="text-sm">
                    <span className="font-medium">{c.tipo_documento}</span>
                    {c.numero_documento && (
                        <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>{c.numero_documento as string}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'nombre_completo', label: 'Nombre / Razón social', sortable: true,
            render: (c) => (
                <span className="flex items-center gap-1.5">
                    {c.es_cliente_general && (
                        <Star size={13} style={{ color: 'var(--color-warning)' }} fill="currentColor" />
                    )}
                    <span className="font-medium">{c.nombre_completo as string}</span>
                </span>
            ),
        },
        {
            key: 'telefono', label: 'Teléfono',
            render: (c) => c.telefono
                ? <span>{c.telefono as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'email', label: 'Email',
            render: (c) => c.email
                ? <span>{c.email as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
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
            render: (c) => c.es_cliente_general ? (
                <span className="text-xs px-2" style={{ color: 'var(--color-text-muted)' }}>—</span>
            ) : (
                <TableActions
                    onEdit={() => openEdit(c)}
                    onDelete={() => setConfirmId(c.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Clientes">
            <PageHeader
                title="Clientes"
                subtitle="Gestiona los clientes de tu empresa"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo cliente
                    </Button>
                }
            />

            <Table
                data={clientes.data}
                columns={columns}
                searchPlaceholder="Buscar por nombre o documento..."
                emptyMessage="No hay clientes registrados"
            />

            {/* Modal crear / editar */}
            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar cliente' : 'Nuevo cliente'}
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
                <FormCliente form={form} setForm={setForm} errors={errors} disabled={saving} />
            </Modal>

            {/* Confirmar desactivar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar cliente"
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
                    El cliente será marcado como inactivo y no aparecerá en nuevas ventas.
                </p>
            </Modal>
        </AppLayout>
    );
}
