import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Landmark } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface MetodoPagoMin {
    id:     number;
    nombre: string;
    tipo:   string;
}

interface Cuenta extends Record<string, unknown> {
    id:            number;
    nombre:        string;
    numero_cuenta: string | null;
    banco:         string | null;
    cci:           string | null;
    titular:       string | null;
    activo:        boolean;
    metodos_pago:  MetodoPagoMin[];
}

interface FormState {
    nombre:        string;
    numero_cuenta: string;
    banco:         string;
    cci:           string;
    titular:       string;
    activo:        boolean;
}

interface Props extends PageProps { cuentas: Cuenta[]; }

const empty = (): FormState => ({
    nombre: '', numero_cuenta: '', banco: '', cci: '', titular: '', activo: true,
});

export default function Cuentas({ cuentas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]         = useState(false);
    const [editing, setEditing]     = useState<Cuenta | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]           = useState<FormState>(empty());
    const [errors, setErrors]       = useState<Partial<Record<keyof FormState, string>>>({});
    const [saving, setSaving]       = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null); setForm(empty()); setErrors({}); setModal(true);
    }

    function openEdit(c: Cuenta) {
        setEditing(c);
        setForm({
            nombre:        c.nombre,
            numero_cuenta: c.numero_cuenta ?? '',
            banco:         c.banco         ?? '',
            cci:           c.cci           ?? '',
            titular:       c.titular       ?? '',
            activo:        c.activo,
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
            router.put(route('configuracion.cuentas.update', editing.id), form as any, opts);
        } else {
            router.post(route('configuracion.cuentas.store'), form as any, opts);
        }
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.cuentas.destroy', id));
    }

    const columns: Column<Cuenta>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (c) => (
                <span className="flex items-center gap-2">
                    <Landmark size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="font-medium">{c.nombre}</span>
                </span>
            ),
        },
        {
            key: 'numero_cuenta', label: 'Número / Celular',
            render: (c) => c.numero_cuenta
                ? <span className="text-sm font-mono">{c.numero_cuenta as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'banco', label: 'Banco',
            render: (c) => c.banco
                ? <span className="text-sm">{c.banco as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'titular', label: 'Titular',
            render: (c) => c.titular
                ? <span className="text-sm">{c.titular as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'metodos_pago', label: 'Métodos de pago',
            render: (c) => {
                const metodos = c.metodos_pago as MetodoPagoMin[];
                if (!metodos.length) {
                    return <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin asignar</span>;
                }
                return (
                    <div className="flex flex-wrap gap-1">
                        {metodos.map(m => (
                            <Badge key={m.id} variant="primary">{m.nombre}</Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (c) => (
                <Badge variant={c.activo ? 'success' : 'secondary'}>
                    {c.activo ? 'Activa' : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (c) => (
                <TableActions onEdit={() => openEdit(c)} onDelete={() => setConfirmId(c.id)} />
            ),
        },
    ];

    return (
        <AppLayout title="Cuentas">
            <PageHeader
                title="Cuentas"
                subtitle="Gestiona las cuentas bancarias y de pago de tu empresa"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nueva cuenta
                    </Button>
                }
            />

            <Table
                data={cuentas}
                columns={columns}
                searchPlaceholder="Buscar cuenta..."
                emptyMessage="No hay cuentas registradas"
            />

            {/* Modal crear / editar */}
            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar cuenta' : 'Nueva cuenta'}
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
                <div className="space-y-4">
                    <Input
                        label="Nombre"
                        required
                        value={form.nombre}
                        onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                        error={errors.nombre}
                        placeholder='Ej: "Cuenta BCP soles", "Yape principal"'
                        disabled={saving}
                    />
                    <Input
                        label="Número de cuenta / celular"
                        value={form.numero_cuenta}
                        onChange={e => setForm(f => ({ ...f, numero_cuenta: e.target.value }))}
                        disabled={saving}
                    />
                    <Input
                        label="Banco"
                        value={form.banco}
                        onChange={e => setForm(f => ({ ...f, banco: e.target.value }))}
                        placeholder='Ej: "BCP", "Interbank", "BBVA"'
                        disabled={saving}
                    />
                    <Input
                        label="CCI"
                        value={form.cci}
                        onChange={e => setForm(f => ({ ...f, cci: e.target.value }))}
                        disabled={saving}
                    />
                    <Input
                        label="Titular"
                        value={form.titular}
                        onChange={e => setForm(f => ({ ...f, titular: e.target.value }))}
                        disabled={saving}
                    />
                    <Switch
                        label="Activa"
                        checked={form.activo}
                        onChange={e => setForm(f => ({ ...f, activo: (e.target as HTMLInputElement).checked }))}
                        disabled={saving}
                    />
                </div>
            </Modal>

            {/* Confirmar desactivar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar cuenta"
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
                    La cuenta será marcada como inactiva. Las asignaciones a métodos de pago se mantendrán.
                </p>
            </Modal>
        </AppLayout>
    );
}
