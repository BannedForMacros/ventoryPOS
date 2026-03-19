import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Trash2 } from 'lucide-react';
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
import type { GastoConcepto, GastoTipo, PageProps } from '@/types';

const CATEGORIAS = [
    { value: 'administrativo', label: 'Administrativo' },
    { value: 'operativo',      label: 'Operativo' },
    { value: 'otro',           label: 'Otro' },
];

const CATEGORIA_VARIANT: Record<string, 'primary' | 'success' | 'secondary'> = {
    administrativo: 'primary',
    operativo:      'success',
    otro:           'secondary',
};

interface ConceptoForm {
    id?:    number;
    nombre: string;
    activo: boolean;
}

interface TipoForm {
    nombre:     string;
    categoria:  string;
    activo:     boolean;
    conceptos:  ConceptoForm[];
}

const emptyTipo = (): TipoForm => ({
    nombre:    '',
    categoria: 'administrativo',
    activo:    true,
    conceptos: [],
});

interface Props extends PageProps {
    tipos: GastoTipo[];
}

export default function GastosTipos({ tipos }: Props) {
    const { flash } = usePage<Props>().props;
    const [modal, setModal]         = useState(false);
    const [editing, setEditing]     = useState<GastoTipo | null>(null);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [form, setForm]           = useState<TipoForm>(emptyTipo());
    const [errors, setErrors]       = useState<Record<string, string>>({});
    const [saving, setSaving]       = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function openCreate() {
        setEditing(null); setForm(emptyTipo()); setErrors({}); setModal(true);
    }

    function openEdit(t: GastoTipo) {
        setEditing(t);
        setForm({
            nombre:    t.nombre,
            categoria: t.categoria,
            activo:    t.activo,
            conceptos: (t.conceptos ?? []).map((c: GastoConcepto) => ({
                id:     c.id,
                nombre: c.nombre,
                activo: c.activo,
            })),
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
            router.put(route('configuracion.gastos.tipos.update', editing.id), form as any, opts);
        } else {
            router.post(route('configuracion.gastos.tipos.store'), form as any, opts);
        }
    }

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.gastos.tipos.destroy', id));
    }

    // ── Gestión de conceptos dentro del modal ──
    function agregarConcepto() {
        setForm(f => ({ ...f, conceptos: [...f.conceptos, { nombre: '', activo: true }] }));
    }

    function updateConcepto(idx: number, campo: keyof ConceptoForm, valor: string | boolean) {
        setForm(f => ({
            ...f,
            conceptos: f.conceptos.map((c, i) => i === idx ? { ...c, [campo]: valor } : c),
        }));
    }

    function removeConcepto(idx: number) {
        setForm(f => ({ ...f, conceptos: f.conceptos.filter((_, i) => i !== idx) }));
    }

    const columns: Column<GastoTipo>[] = [
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (t) => <span className="font-medium">{t.nombre}</span>,
        },
        {
            key: 'categoria', label: 'Categoría', sortable: true,
            render: (t) => (
                <Badge variant={CATEGORIA_VARIANT[t.categoria] ?? 'secondary'}>
                    {CATEGORIAS.find(c => c.value === t.categoria)?.label ?? t.categoria}
                </Badge>
            ),
        },
        {
            key: 'conceptos', label: 'Conceptos',
            render: (t) => (
                <span style={{ color: 'var(--color-text-muted)' }}>
                    {(t.conceptos ?? []).filter((c: GastoConcepto) => c.activo).length} activos
                </span>
            ),
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (t) => (
                <Badge variant={t.activo ? 'success' : 'secondary'}>
                    {t.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (t) => (
                <TableActions
                    onEdit={() => openEdit(t)}
                    onDelete={t.activo ? () => setConfirmId(t.id) : undefined}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Tipos de gasto">
            <PageHeader
                title="Tipos de gasto"
                subtitle="Configura las categorías y conceptos de gastos de tu empresa"
                actions={
                    <Button onClick={openCreate}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo tipo
                    </Button>
                }
            />

            <Table
                data={tipos}
                columns={columns}
                searchPlaceholder="Buscar tipo de gasto..."
                emptyMessage="No hay tipos de gasto configurados"
            />

            {/* Modal crear / editar */}
            <Modal
                isOpen={modal}
                onClose={() => setModal(false)}
                title={editing ? 'Editar tipo de gasto' : 'Nuevo tipo de gasto'}
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
                        label="Categoría del sistema"
                        required
                        value={form.categoria}
                        onChange={v => setForm(f => ({ ...f, categoria: String(v) }))}
                        options={CATEGORIAS}
                        error={errors.categoria}
                        disabled={saving}
                    />
                    <Switch
                        label="Activo"
                        checked={form.activo}
                        onChange={e => setForm(f => ({ ...f, activo: (e.target as HTMLInputElement).checked }))}
                        disabled={saving}
                    />

                    {/* ── Conceptos ── */}
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                Conceptos
                            </p>
                            <button
                                type="button"
                                onClick={agregarConcepto}
                                disabled={saving}
                                className="flex items-center gap-1 text-xs font-medium"
                                style={{ color: 'var(--color-primary)' }}
                            >
                                <Plus size={13} />Agregar concepto
                            </button>
                        </div>

                        {form.conceptos.length === 0 ? (
                            <p className="text-xs py-3 text-center" style={{ color: 'var(--color-text-muted)' }}>
                                Sin conceptos. Agrega al menos uno.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {form.conceptos.map((c, idx) => (
                                    <div key={idx} className="flex items-center gap-2">
                                        <div className="flex-1">
                                            <input
                                                type="text"
                                                value={c.nombre}
                                                onChange={e => updateConcepto(idx, 'nombre', e.target.value)}
                                                placeholder="Nombre del concepto"
                                                disabled={saving}
                                                className="w-full rounded-xl px-3 py-2 text-sm"
                                                style={{
                                                    border:          '1px solid var(--color-border)',
                                                    backgroundColor: 'var(--color-surface)',
                                                    color:           'var(--color-text)',
                                                }}
                                            />
                                            {errors[`conceptos.${idx}.nombre`] && (
                                                <p className="text-xs mt-0.5" style={{ color: 'var(--color-danger)' }}>
                                                    {errors[`conceptos.${idx}.nombre`]}
                                                </p>
                                            )}
                                        </div>
                                        <label className="flex items-center gap-1 text-xs whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                            <input
                                                type="checkbox"
                                                checked={c.activo}
                                                onChange={e => updateConcepto(idx, 'activo', e.target.checked)}
                                                disabled={saving}
                                            />
                                            Activo
                                        </label>
                                        {!c.id && (
                                            <button
                                                type="button"
                                                onClick={() => removeConcepto(idx)}
                                                disabled={saving}
                                                className="p-1 rounded"
                                                style={{ color: 'var(--color-danger)' }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </Modal>

            {/* Confirmar desactivar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar tipo de gasto"
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
                    El tipo y todos sus conceptos serán marcados como inactivos.
                </p>
            </Modal>
        </AppLayout>
    );
}
