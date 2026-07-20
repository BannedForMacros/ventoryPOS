import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Check } from 'lucide-react';
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
import DynamicIcon from '@/Components/DynamicIcon';
import type { PageProps } from '@/types';

interface CuentaMin {
    id:            number;
    nombre:        string;
    numero_cuenta: string | null;
    banco:         string | null;
}

interface TipoMetodoPago {
    id:                       number;
    slug:                     string;
    nombre:                   string;
    icono:                    string | null;
    admite_vuelto_default:    boolean;
    requiere_referencia:      boolean;
}

interface MetodoPago extends Record<string, unknown> {
    id:             number;
    nombre:         string;
    tipo_id:        number;
    tipo:           TipoMetodoPago | null;
    admite_vuelto:  boolean;
    activo:         boolean;
    cuentas:        CuentaMin[];
}

interface FormState {
    nombre:        string;
    tipo_id:       number | '';
    admite_vuelto: boolean;
    activo:        boolean;
    cuenta_ids:    number[];
}

interface Props extends PageProps {
    metodos:         MetodoPago[];
    cuentas:         CuentaMin[];
    tiposMetodoPago: TipoMetodoPago[];
}

const emptyForm = (): FormState => ({
    nombre: '', tipo_id: '', admite_vuelto: false, activo: true, cuenta_ids: [],
});

export default function MetodosPago({ metodos, cuentas, tiposMetodoPago }: Props) {
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
            nombre:        m.nombre,
            tipo_id:       m.tipo_id,
            admite_vuelto: m.admite_vuelto ?? !!m.tipo?.admite_vuelto_default,
            activo:        m.activo,
            cuenta_ids:    (m.cuentas as CuentaMin[]).map(c => c.id),
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

    // Al cambiar el tipo, sugerimos sus defaults (admite_vuelto). El admin
    // puede sobreescribirlos abajo en el form.
    function handleTipoChange(tipoId: number) {
        const tipo = tiposMetodoPago.find(t => t.id === tipoId);
        setForm(f => ({
            ...f,
            tipo_id:       tipoId,
            admite_vuelto: tipo?.admite_vuelto_default ?? false,
            cuenta_ids:    [],
        }));
    }

    const tipoSeleccionado = tiposMetodoPago.find(t => t.id === form.tipo_id);
    const mostrarCuentas   = tipoSeleccionado ? tipoSeleccionado.slug !== 'efectivo' : false;

    const columns: Column<MetodoPago>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (m) => <span className="font-medium">{m.nombre}</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortKey: 'tipo.nombre',
            render: (m) => (
                <Badge variant="primary">
                    <span className="flex items-center gap-1">
                        {m.tipo?.icono && <DynamicIcon name={m.tipo.icono} size={14} />}
                        {m.tipo?.nombre ?? '—'}
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
            key: 'acciones', label: 'Acciones', sortable: false,
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
                        value={form.tipo_id}
                        onChange={v => handleTipoChange(Number(v))}
                        options={tiposMetodoPago.map(t => ({ value: t.id, label: t.nombre }))}
                        disabled={saving}
                    />
                    {errors.tipo_id && (
                        <p className="text-xs -mt-2" style={{ color: 'var(--color-danger)' }}>{errors.tipo_id}</p>
                    )}
                    <div>
                        <Switch
                            label="¿Admite vuelto?"
                            checked={form.admite_vuelto}
                            onChange={v => setForm(f => ({ ...f, admite_vuelto: v }))}
                            disabled={saving}
                        />
                        <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                            Si está activo, el POS permitirá sobrepagar con este método y calculará vuelto.
                            Marca esto solo en métodos donde el cajero puede devolver el excedente físicamente.
                        </p>
                    </div>
                    <Switch
                        label="Activo"
                        checked={form.activo}
                        onChange={v => setForm(f => ({ ...f, activo: v }))}
                        disabled={saving}
                    />

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
