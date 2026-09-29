import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Check, Columns3, ArrowUp, ArrowDown, Trash2 } from 'lucide-react';
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
    planilla_columna_id: number | null;
}

/** Columna de la planilla de caja (función opcional por empresa). */
interface PlanillaColumna { id: number; nombre: string; orden: number; }

interface FormState {
    nombre:        string;
    tipo_id:       number | '';
    admite_vuelto: boolean;
    activo:        boolean;
    cuenta_ids:    number[];
    planilla_columna_id: number | '';
}

interface Props extends PageProps {
    metodos:          MetodoPago[];
    cuentas:          CuentaMin[];
    tiposMetodoPago:  TipoMetodoPago[];
    usaPlanilla?:     boolean;
    planillaColumnas?: PlanillaColumna[];
}

const emptyForm = (): FormState => ({
    nombre: '', tipo_id: '', admite_vuelto: false, activo: true, cuenta_ids: [], planilla_columna_id: '',
});

export default function MetodosPago({ metodos, cuentas, tiposMetodoPago, usaPlanilla = false, planillaColumnas = [] }: Props) {
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
            planilla_columna_id: m.planilla_columna_id ?? '',
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
        ...(usaPlanilla ? [{
            key: 'planilla', label: 'En la planilla', sortable: false,
            render: (m: MetodoPago) => {
                const col = planillaColumnas.find(c => c.id === m.planilla_columna_id);
                return col
                    ? <Badge variant="info">{col.nombre}</Badge>
                    : <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Columna propia</span>;
            },
        } as Column<MetodoPago>] : []),
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

            {usaPlanilla && <ColumnasPlanilla columnas={planillaColumnas} metodos={metodos} />}

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
                    {usaPlanilla && (
                        <div>
                            <Select
                                label="Columna en la planilla de caja"
                                value={form.planilla_columna_id === '' ? '' : String(form.planilla_columna_id)}
                                onChange={v => setForm(f => ({ ...f, planilla_columna_id: v ? Number(v) : '' }))}
                                options={[
                                    { value: '', label: 'Su propia columna (con su nombre)' },
                                    ...planillaColumnas.map(c => ({ value: String(c.id), label: c.nombre })),
                                ]}
                                disabled={saving}
                            />
                            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                En el reporte de caja del turno, lo cobrado con este medio suma en esa columna. Varios medios pueden ir en la misma.
                            </p>
                            {errors.planilla_columna_id && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.planilla_columna_id}</p>}
                        </div>
                    )}
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

/* ── Columnas de la planilla de caja ──────────────────────────────────── */
/**
 * Las columnas de dinero del reporte de caja del turno. Cada medio de pago
 * elige en su formulario a qué columna suma; aquí se crean, renombran,
 * ordenan y borran. "Anticipo" y "Créditos" las arma el sistema solo.
 */
function ColumnasPlanilla({ columnas, metodos }: { columnas: PlanillaColumna[]; metodos: MetodoPago[] }) {
    const [nueva, setNueva] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [nombres, setNombres] = useState<Record<number, string>>({});
    const ordenadas = [...columnas].sort((a, b) => a.orden - b.orden || a.id - b.id);

    function crear() {
        if (!nueva.trim()) return;
        router.post(route('configuracion.planilla-columnas.store'), { nombre: nueva.trim() }, {
            preserveScroll: true,
            onSuccess: () => { setNueva(''); setError(null); },
            onError: e => setError((e as Record<string, string>).nombre ?? 'No se pudo crear la columna.'),
        });
    }

    function renombrar(c: PlanillaColumna) {
        const nombre = (nombres[c.id] ?? c.nombre).trim();
        if (!nombre || nombre === c.nombre) return;
        router.put(route('configuracion.planilla-columnas.update', c.id), { nombre, orden: c.orden }, {
            preserveScroll: true,
            onError: e => { toast.error((e as Record<string, string>).nombre ?? 'No se pudo renombrar.'); setNombres(n => ({ ...n, [c.id]: c.nombre })); },
        });
    }

    /** Intercambia el orden con la vecina (arriba o abajo). */
    function mover(i: number, dir: -1 | 1) {
        const a = ordenadas[i], b = ordenadas[i + dir];
        if (!b) return;
        const ordenA = a.orden === b.orden ? i + dir : b.orden;
        const ordenB = a.orden === b.orden ? i : a.orden;
        router.put(route('configuracion.planilla-columnas.update', a.id), { nombre: a.nombre, orden: ordenA }, {
            preserveScroll: true,
            onSuccess: () => router.put(route('configuracion.planilla-columnas.update', b.id), { nombre: b.nombre, orden: ordenB }, { preserveScroll: true }),
        });
    }

    function borrar(c: PlanillaColumna) {
        if (!confirm(`¿Borrar la columna "${c.nombre}"? Sus medios de pago pasarán a mostrarse con su propio nombre.`)) return;
        router.delete(route('configuracion.planilla-columnas.destroy', c.id), { preserveScroll: true });
    }

    return (
        <section className="rounded-2xl mb-5 overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <header className="flex items-start gap-3 px-4 pt-4 pb-3">
                <span className="flex h-9 w-9 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 9%, var(--color-surface))', color: 'var(--vp-navy)' }}>
                    <Columns3 size={18} />
                </span>
                <div>
                    <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>Columnas de la planilla de caja</h2>
                    <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                        Las columnas de dinero del reporte de caja de cada turno, en este orden. Asigna cada medio de pago a su columna al editarlo.
                        "Anticipo" y "Créditos" se agregan solas, y una columna sin montos en el turno no se muestra.
                    </p>
                </div>
            </header>

            <ul>
                {ordenadas.length === 0 && (
                    <li className="px-4 py-3 text-sm" style={{ color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)' }}>
                        Todavía no hay columnas: cada medio de pago sale con su propio nombre.
                    </li>
                )}
                {ordenadas.map((c, i) => {
                    const suyos = metodos.filter(m => m.planilla_columna_id === c.id);
                    return (
                        <li key={c.id} className="flex flex-wrap items-center gap-3 px-4 py-2.5" style={{ borderTop: '1px solid var(--color-border)' }}>
                            <div className="flex flex-col">
                                <button type="button" onClick={() => mover(i, -1)} disabled={i === 0} aria-label={`Subir ${c.nombre}`}
                                    className="p-0.5 rounded disabled:opacity-25 hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}><ArrowUp size={14} /></button>
                                <button type="button" onClick={() => mover(i, 1)} disabled={i === ordenadas.length - 1} aria-label={`Bajar ${c.nombre}`}
                                    className="p-0.5 rounded disabled:opacity-25 hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}><ArrowDown size={14} /></button>
                            </div>
                            <input value={nombres[c.id] ?? c.nombre} aria-label="Nombre de la columna" maxLength={60}
                                onChange={e => setNombres(n => ({ ...n, [c.id]: e.target.value }))}
                                onBlur={() => renombrar(c)}
                                onKeyDown={e => { if (e.key === 'Enter') (e.target as HTMLInputElement).blur(); }}
                                className="w-56 rounded-lg px-3 py-1.5 text-sm font-semibold border outline-none focus:ring-2"
                                style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)', backgroundColor: 'var(--color-surface)' }} />
                            <div className="flex flex-wrap gap-1.5 flex-1 min-w-0">
                                {suyos.length === 0
                                    ? <span className="text-[13px]" style={{ color: 'var(--vp-amber-ink)' }}>Ningún medio de pago asignado</span>
                                    : suyos.map(m => <Badge key={m.id} variant="secondary">{m.nombre}</Badge>)}
                            </div>
                            <button type="button" onClick={() => borrar(c)} aria-label={`Borrar ${c.nombre}`}
                                className="p-1.5 rounded-lg hover:bg-black/5" style={{ color: 'var(--color-danger)' }}><Trash2 size={15} /></button>
                        </li>
                    );
                })}
            </ul>

            <div className="flex flex-wrap items-center gap-2 px-4 py-3" style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                <input value={nueva} onChange={e => setNueva(e.target.value)} placeholder='Nueva columna, ej. "Depósitos"' maxLength={60}
                    onKeyDown={e => { if (e.key === 'Enter') crear(); }} aria-label="Nombre de la nueva columna"
                    className="w-64 rounded-lg px-3 py-1.5 text-sm border outline-none focus:ring-2"
                    style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)', backgroundColor: 'var(--color-surface)' }} />
                <Button size="sm" onClick={crear} disabled={!nueva.trim()}>
                    <Plus size={14} className="mr-1" />Agregar columna
                </Button>
                {error && <span className="text-xs" style={{ color: 'var(--color-danger)' }}>{error}</span>}
            </div>
        </section>
    );
}
