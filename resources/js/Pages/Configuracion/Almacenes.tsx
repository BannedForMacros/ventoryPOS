import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface LocalItem  { id: number; nombre: string; }
interface Almacen extends Record<string, unknown> {
    id: number;
    nombre: string;
    tipo: 'central' | 'local';
    local_id: number | null;
    local?: LocalItem | null;
    activo: boolean;
}

interface Props extends PageProps {
    almacenes: Almacen[];
    locales: LocalItem[];
}

interface FormState {
    nombre: string;
    tipo: 'central' | 'local';
    local_id: number | '';
    activo: boolean;
}

const empty = (): FormState => ({ nombre: '', tipo: 'central', local_id: '', activo: true });

export default function Almacenes({ almacenes, locales }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]       = useState(false);
    const [editing, setEditing]   = useState<Almacen | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]         = useState<FormState>(empty());
    const [errors, setErrors]     = useState<Partial<FormState & { local_id: string }>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        setForm(empty());
        setErrors({});
        setModal(true);
    }

    function openEdit(a: Almacen) {
        setEditing(a);
        setForm({ nombre: a.nombre, tipo: a.tipo, local_id: a.local_id ?? '', activo: a.activo });
        setErrors({});
        setModal(true);
    }

    function submit() {
        const url  = editing ? route('configuracion.almacenes.update', editing.id) : route('configuracion.almacenes.store');
        const payload = { nombre: form.nombre, tipo: form.tipo, local_id: form.local_id, activo: form.activo };

        const action = editing
            ? (cb: object) => router.put(url, payload, cb as Parameters<typeof router.put>[2])
            : (cb: object) => router.post(url, payload, cb as Parameters<typeof router.post>[2]);

        action({
            onSuccess: () => { setModal(false); },
            onError: (errs: Record<string, string>) => setErrors(errs),
        });
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.almacenes.destroy', id));
    }

    const columns: Column<Almacen>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (a) => <span className="font-medium">{a.nombre}</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (a) => (
                <Badge variant={a.tipo === 'central' ? 'primary' : 'success'}>
                    {a.tipo === 'central' ? 'Central' : 'Local'}
                </Badge>
            ),
        },
        {
            key: 'local', label: 'Local asociado', sortable: true,
            render: (a) => a.local
                ? <span>{a.local.nombre}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (a) => (
                <Badge variant={a.activo ? 'success' : 'secondary'}>
                    {a.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (a) => (
                <TableActions
                    onEdit={() => openEdit(a)}
                    onDelete={() => setConfirmId(a.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Almacenes">
            <PageHeader
                title="Almacenes"
                subtitle="Configura los almacenes de tu empresa"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo almacén
                    </Button>
                }
            />

            <Table
                data={almacenes}
                columns={columns}
                searchPlaceholder="Buscar almacén..."
                emptyMessage="No hay almacenes registrados"
            />

            {/* Modal crear / editar */}
            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar almacén' : 'Nuevo almacén'}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModal(false)}>Cancelar</Button>
                        <Button onClick={submit}>Guardar</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Input
                        label="Nombre"
                        required
                        value={form.nombre}
                        onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                        error={errors.nombre}
                    />

                    <div>
                        <p className="text-sm font-medium mb-2" style={{ color: 'var(--color-text)' }}>
                            Tipo <span style={{ color: 'var(--color-danger)' }}>*</span>
                        </p>
                        <div className="flex flex-col gap-2">
                            {([
                                { value: 'central', label: 'Central', hint: 'Bodega principal, no pertenece a un local específico' },
                                { value: 'local',   label: 'Local',   hint: 'Almacén de un local/sucursal, desde aquí se despacha para ventas' },
                            ] as const).map(opt => (
                                <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                    <input type="radio" name="tipo_almacen"
                                        checked={form.tipo === opt.value}
                                        onChange={() => setForm(f => ({ ...f, tipo: opt.value, local_id: '' }))}
                                        className="mt-0.5 accent-[var(--color-primary)]" />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{opt.label}</span>
                                        <span className="ml-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{opt.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    {form.tipo === 'local' && (
                        <Select
                            label="Local asociado"
                            required
                            value={form.local_id}
                            onChange={v => setForm(f => ({ ...f, local_id: v === '' ? '' : Number(v) }))}
                            options={locales.map(l => ({ value: l.id, label: l.nombre }))}
                            error={errors.local_id}
                        />
                    )}

                    <Switch
                        label="Activo"
                        checked={form.activo}
                        onChange={e => setForm(f => ({ ...f, activo: (e.target as HTMLInputElement).checked }))}
                    />
                </div>
            </Modal>

            {/* Confirmar desactivar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar almacén"
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
                    Si el almacén tiene movimientos registrados, se marcará como inactivo. De lo contrario, se eliminará.
                </p>
            </Modal>
        </AppLayout>
    );
}
