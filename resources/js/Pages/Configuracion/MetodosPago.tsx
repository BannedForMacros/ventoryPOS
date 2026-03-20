import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Banknote, CreditCard, Smartphone, ArrowLeftRight, Wallet, Check } from 'lucide-react';
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

const TIPOS = [
    { value: 'efectivo',        label: 'Efectivo' },
    { value: 'tarjeta_debito',  label: 'Tarjeta débito' },
    { value: 'tarjeta_credito', label: 'Tarjeta crédito' },
    { value: 'transferencia',   label: 'Transferencia' },
    { value: 'yape',            label: 'Yape' },
    { value: 'plin',            label: 'Plin' },
    { value: 'otro',            label: 'Otro' },
];

const TIPO_ICON: Record<string, React.ReactNode> = {
    efectivo:        <Banknote size={14} />,
    tarjeta_debito:  <CreditCard size={14} />,
    tarjeta_credito: <CreditCard size={14} />,
    transferencia:   <ArrowLeftRight size={14} />,
    yape:            <Smartphone size={14} />,
    plin:            <Smartphone size={14} />,
    otro:            <Wallet size={14} />,
};

const TIPO_LABEL: Record<string, string> = {
    efectivo:        'Efectivo',
    tarjeta_debito:  'Tarjeta débito',
    tarjeta_credito: 'Tarjeta crédito',
    transferencia:   'Transferencia',
    yape:            'Yape',
    plin:            'Plin',
    otro:            'Otro',
};

interface CuentaMin {
    id:            number;
    nombre:        string;
    numero_cuenta: string | null;
    banco:         string | null;
}

interface MetodoPago extends Record<string, unknown> {
    id:         number;
    nombre:     string;
    tipo:       string;
    activo:     boolean;
    cuentas:    CuentaMin[];
}

interface FormState {
    nombre:     string;
    tipo:       string;
    activo:     boolean;
    cuenta_ids: number[];
}

interface Props extends PageProps {
    metodos: MetodoPago[];
    cuentas: CuentaMin[];
}

const emptyForm = (): FormState => ({
    nombre: '', tipo: 'efectivo', activo: true, cuenta_ids: [],
});

export default function MetodosPago({ metodos, cuentas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]         = useState(false);
    const [editing, setEditing]     = useState<MetodoPago | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]           = useState<FormState>(emptyForm());
    const [errors, setErrors]       = useState<Record<string, string>>({});
    const [saving, setSaving]       = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null); setForm(emptyForm()); setErrors({}); setModal(true);
    }

    function openEdit(m: MetodoPago) {
        setEditing(m);
        setForm({
            nombre:     m.nombre,
            tipo:       m.tipo,
            activo:     m.activo,
            cuenta_ids: (m.cuentas as CuentaMin[]).map(c => c.id),
        });
        setErrors({}); setModal(true);
    }

    function submit() {
        setSaving(true);
        const opts = {
            onSuccess: () => { setModal(false); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs as Record<string, string>); setSaving(false); },
        };

        if (editing) {
            router.put(route('configuracion.metodos-pago.update', editing.id), form as any, opts);
        } else {
            router.post(route('configuracion.metodos-pago.store'), form as any, opts);
        }
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.metodos-pago.destroy', id));
    }

    function toggleCuenta(id: number) {
        setForm(f => ({
            ...f,
            cuenta_ids: f.cuenta_ids.includes(id)
                ? f.cuenta_ids.filter(c => c !== id)
                : [...f.cuenta_ids, id],
        }));
    }

    const mostrarCuentas = form.tipo !== 'efectivo';

    const columns: Column<MetodoPago>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (m) => <span className="font-medium">{m.nombre}</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (m) => (
                <Badge variant="primary">
                    <span className="flex items-center gap-1">
                        {TIPO_ICON[m.tipo]}{TIPO_LABEL[m.tipo] ?? m.tipo}
                    </span>
                </Badge>
            ),
        },
        {
            key: 'cuentas', label: 'Cuentas asignadas',
            render: (m) => {
                const cs = m.cuentas as CuentaMin[];
                if (!cs.length) {
                    return <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin cuentas</span>;
                }
                return (
                    <div className="flex flex-wrap gap-1">
                        {cs.map(c => (
                            <Badge key={c.id} variant="secondary">{c.nombre}</Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (m) => (
                <Badge variant={m.activo ? 'success' : 'secondary'}>
                    {m.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (m) => (
                <TableActions onEdit={() => openEdit(m)} onDelete={() => setConfirmId(m.id)} />
            ),
        },
    ];

    return (
        <AppLayout title="Métodos de pago">
            <PageHeader
                title="Métodos de pago"
                subtitle="Configura los métodos de pago aceptados"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo método
                    </Button>
                }
            />

            <Table
                data={metodos}
                columns={columns}
                searchPlaceholder="Buscar método de pago..."
                emptyMessage="No hay métodos de pago configurados"
            />

            {/* Modal crear / editar */}
            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar método de pago' : 'Nuevo método de pago'}
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
                <div className="space-y-4">
                    <Input
                        label="Nombre"
                        required
                        value={form.nombre}
                        onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                        error={errors.nombre}
                        disabled={saving}
                    />
                    <Select
                        label="Tipo"
                        required
                        value={form.tipo}
                        onChange={v => setForm(f => ({ ...f, tipo: String(v), cuenta_ids: [] }))}
                        options={TIPOS}
                        disabled={saving}
                    />
                    <Switch
                        label="Activo"
                        checked={form.activo}
                        onChange={v => setForm(f => ({ ...f, activo: v }))}
                        disabled={saving}
                    />

                    {/* Cuentas asignadas */}
                    {mostrarCuentas && (
                        <div className="pt-1">
                            <p className="text-sm font-medium mb-2" style={{ color: 'var(--color-text)' }}>
                                Cuentas asignadas{' '}
                                <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>(opcional)</span>
                            </p>

                            {cuentas.length === 0 ? (
                                <p className="text-xs py-3 text-center" style={{ color: 'var(--color-text-muted)' }}>
                                    No hay cuentas disponibles. Crea una en{' '}
                                    <span className="font-medium">Configuración → Cuentas</span>.
                                </p>
                            ) : (
                                <div className="rounded-lg divide-y overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                    {cuentas.map(cuenta => {
                                        const selected = form.cuenta_ids.includes(cuenta.id);
                                        return (
                                            <button
                                                key={cuenta.id}
                                                type="button"
                                                onClick={() => toggleCuenta(cuenta.id)}
                                                disabled={saving}
                                                className="w-full flex items-center justify-between px-4 py-2.5 text-left transition-colors"
                                                style={{
                                                    backgroundColor: selected ? 'rgba(59,130,246,0.06)' : 'transparent',
                                                    borderColor: 'var(--color-border)',
                                                }}
                                            >
                                                <div>
                                                    <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                                        {cuenta.nombre}
                                                    </p>
                                                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                        {[cuenta.banco, cuenta.numero_cuenta].filter(Boolean).join(' · ') || 'Sin detalles'}
                                                    </p>
                                                </div>
                                                {selected && (
                                                    <Check size={16} style={{ color: 'var(--color-primary)', flexShrink: 0 }} />
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </Modal>

            {/* Confirmar desactivar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar método de pago"
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
                    El método de pago será marcado como inactivo y no estará disponible en nuevas ventas.
                </p>
            </Modal>
        </AppLayout>
    );
}
