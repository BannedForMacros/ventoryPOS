import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Banknote, CreditCard, Smartphone, ArrowLeftRight, Wallet, Trash2 } from 'lucide-react';
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

interface Cuenta {
    id?:           number;
    nombre:        string;
    numero_cuenta: string;
    banco:         string;
    cci:           string;
    titular:       string;
    activo:        boolean;
}

interface MetodoPago extends Record<string, unknown> {
    id:      number;
    nombre:  string;
    tipo:    string;
    activo:  boolean;
    cuentas: Cuenta[];
}

interface FormState {
    nombre:  string;
    tipo:    string;
    activo:  boolean;
    cuentas: Cuenta[];
}

interface Props extends PageProps { metodos: MetodoPago[]; }

const emptyCuenta = (): Cuenta => ({
    nombre: '', numero_cuenta: '', banco: '', cci: '', titular: '', activo: true,
});

const emptyForm = (): FormState => ({
    nombre: '', tipo: 'efectivo', activo: true, cuentas: [],
});

export default function MetodosPago({ metodos }: Props) {
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
        setForm({ nombre: m.nombre, tipo: m.tipo, activo: m.activo, cuentas: m.cuentas.map(c => ({ ...c })) });
        setErrors({}); setModal(true);
    }

    function submit() {
        setSaving(true);
        const url    = editing ? route('configuracion.metodos-pago.update', editing.id) : route('configuracion.metodos-pago.store');
        const method = editing ? router.put : router.post;

        method(url, form as any, {
            onSuccess: () => { setModal(false); setSaving(false); },
            onError:   (errs) => { setErrors(errs as Record<string, string>); setSaving(false); },
        });
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.metodos-pago.destroy', id));
    }

    function addCuenta() {
        setForm(f => ({ ...f, cuentas: [...f.cuentas, emptyCuenta()] }));
    }

    function removeCuenta(idx: number) {
        setForm(f => ({ ...f, cuentas: f.cuentas.filter((_, i) => i !== idx) }));
    }

    function updateCuenta(idx: number, field: keyof Cuenta, value: string | boolean) {
        setForm(f => ({
            ...f,
            cuentas: f.cuentas.map((c, i) => i === idx ? { ...c, [field]: value } : c),
        }));
    }

    const mostrarCuentas = form.tipo !== 'efectivo';
    const mostrarBanco   = ['transferencia', 'tarjeta_debito', 'tarjeta_credito'].includes(form.tipo);
    const mostrarCci     = form.tipo === 'transferencia';

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
            key: 'cuentas', label: 'Cuentas',
            render: (m) => {
                const activas = (m.cuentas as Cuenta[]).filter(c => c.activo).length;
                return activas > 0
                    ? <span className="text-sm">{activas} {activas === 1 ? 'cuenta' : 'cuentas'}</span>
                    : <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin cuentas</span>;
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
                    {/* Sección 1 — Datos del método */}
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
                        onChange={v => setForm(f => ({ ...f, tipo: String(v), cuentas: [] }))}
                        options={TIPOS}
                        disabled={saving}
                    />
                    <Switch
                        label="Activo"
                        checked={form.activo}
                        onChange={e => setForm(f => ({ ...f, activo: (e.target as HTMLInputElement).checked }))}
                        disabled={saving}
                    />

                    {/* Sección 2 — Cuentas asociadas */}
                    {mostrarCuentas && (
                        <div className="pt-2 space-y-3">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                    Cuentas asociadas{' '}
                                    <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>(opcional)</span>
                                </p>
                                <Button variant="ghost" onClick={addCuenta} disabled={saving}>
                                    <Plus size={13} className="mr-1" />Agregar cuenta
                                </Button>
                            </div>

                            {form.cuentas.length === 0 && (
                                <p className="text-xs text-center py-3" style={{ color: 'var(--color-text-muted)' }}>
                                    Sin cuentas. Puedes agregar una o más.
                                </p>
                            )}

                            {form.cuentas.map((cuenta, idx) => (
                                <div
                                    key={idx}
                                    className="rounded-lg p-3 space-y-3"
                                    style={{ backgroundColor: 'var(--color-background)', border: '1px solid var(--color-border)' }}
                                >
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>
                                            Cuenta {idx + 1}
                                        </span>
                                        <button
                                            onClick={() => removeCuenta(idx)}
                                            className="p-1 rounded transition-colors hover:bg-red-50"
                                            style={{ color: 'var(--color-danger)' }}
                                        >
                                            <Trash2 size={13} />
                                        </button>
                                    </div>
                                    <Input
                                        label="Nombre de la cuenta"
                                        required
                                        value={cuenta.nombre}
                                        onChange={e => updateCuenta(idx, 'nombre', e.target.value)}
                                        error={errors[`cuentas.${idx}.nombre`]}
                                        placeholder='Ej: "Yape principal", "Cuenta BCP soles"'
                                        disabled={saving}
                                    />
                                    <Input
                                        label="Número de cuenta / celular"
                                        value={cuenta.numero_cuenta}
                                        onChange={e => updateCuenta(idx, 'numero_cuenta', e.target.value)}
                                        disabled={saving}
                                    />
                                    {mostrarBanco && (
                                        <Input
                                            label="Banco"
                                            value={cuenta.banco}
                                            onChange={e => updateCuenta(idx, 'banco', e.target.value)}
                                            placeholder='Ej: "BCP", "Interbank"'
                                            disabled={saving}
                                        />
                                    )}
                                    {mostrarCci && (
                                        <Input
                                            label="CCI"
                                            value={cuenta.cci}
                                            onChange={e => updateCuenta(idx, 'cci', e.target.value)}
                                            disabled={saving}
                                        />
                                    )}
                                    <Input
                                        label="Titular"
                                        value={cuenta.titular}
                                        onChange={e => updateCuenta(idx, 'titular', e.target.value)}
                                        disabled={saving}
                                    />
                                </div>
                            ))}
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
