import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { IdCard, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import Callout from '@/Components/UI/Callout';
import { LicenciaBadge, Pastilla, type EstadoLicencia } from '@/Components/Flota/FlotaUI';
import type { PageProps } from '@/types';

type Tipo = 'chofer' | 'ayudante';

interface Conductor extends Record<string, unknown> {
    id: number;
    tipo: Tipo;
    nombres: string;
    apellidos: string | null;
    nombre_completo: string;
    tipo_documento: string;
    numero_documento: string | null;
    telefono: string | null;
    licencia: string | null;
    licencia_categoria: string | null;
    licencia_vence: string | null;
    estado_licencia: EstadoLicencia;
    observacion: string | null;
    activo: boolean;
    vehiculos: string[];
    viajes_mes: number;
}

interface Props extends PageProps {
    conductores: Conductor[];
    puede: { crear: boolean; editar: boolean; eliminar: boolean };
}

interface Form {
    tipo: Tipo; nombres: string; apellidos: string; tipo_documento: string; numero_documento: string;
    telefono: string; licencia: string; licencia_categoria: string; licencia_vence: string;
    observacion: string; activo: boolean;
}

const vacio = (tipo: Tipo = 'chofer'): Form => ({
    tipo, nombres: '', apellidos: '', tipo_documento: 'DNI', numero_documento: '', telefono: '',
    licencia: '', licencia_categoria: '', licencia_vence: '', observacion: '', activo: true,
});

/** Categorías de licencia en Perú para transporte de carga. */
const CATEGORIAS = ['A-I', 'A-IIa', 'A-IIb', 'A-IIIa', 'A-IIIb', 'A-IIIc'];

export default function Conductores({ conductores, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<Conductor | null>(null);
    const [form, setForm] = useState<Form>(vacio());
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const [borrar, setBorrar] = useState<Conductor | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm(f => ({ ...f, [k]: v }));
    const esChofer = form.tipo === 'chofer';

    const alertas = useMemo(() => conductores.filter(c => c.activo && c.tipo === 'chofer'
        && (c.estado_licencia === 'vencida' || c.estado_licencia === 'por_vencer')), [conductores]);

    function nuevo() { setEditando(null); setForm(vacio()); setErrores({}); setAbierto(true); }

    function editar(c: Conductor) {
        setEditando(c);
        setForm({
            tipo: c.tipo, nombres: c.nombres, apellidos: c.apellidos ?? '', tipo_documento: c.tipo_documento,
            numero_documento: c.numero_documento ?? '', telefono: c.telefono ?? '', licencia: c.licencia ?? '',
            licencia_categoria: c.licencia_categoria ?? '', licencia_vence: c.licencia_vence ?? '',
            observacion: c.observacion ?? '', activo: c.activo,
        });
        setErrores({}); setAbierto(true);
    }

    function guardar() {
        setGuardando(true);
        const opts = {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
            onError: (e: Record<string, string>) => setErrores(e),
            onFinish: () => setGuardando(false),
        };
        const payload = { ...form, licencia_vence: form.licencia_vence || null };
        editando
            ? router.put(route('flota.conductores.update', editando.id), payload, opts)
            : router.post(route('flota.conductores.store'), payload, opts);
    }

    const columnas: Column<Conductor>[] = [
        {
            key: 'nombre_completo', label: 'Nombre',
            render: c => (
                <div className="min-w-0">
                    <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{c.nombre_completo}</p>
                    <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                        {[c.numero_documento ? `${c.tipo_documento} ${c.numero_documento}` : null, c.telefono].filter(Boolean).join(' · ') || '—'}
                    </p>
                </div>
            ),
        },
        {
            key: 'tipo', label: 'Función',
            render: c => <Pastilla color={c.tipo === 'chofer' ? 'var(--vp-navy)' : 'var(--color-text-muted)'}>{c.tipo === 'chofer' ? 'Chofer' : 'Ayudante'}</Pastilla>,
        },
        {
            key: 'licencia', label: 'Licencia',
            render: c => c.tipo !== 'chofer' ? <span style={{ color: 'var(--color-text-muted)' }}>—</span> : (
                <div className="space-y-1">
                    <p className="text-[13px] font-mono" style={{ color: 'var(--color-text)' }}>
                        {c.licencia ?? '—'}{c.licencia_categoria ? ` · ${c.licencia_categoria}` : ''}
                    </p>
                    <LicenciaBadge estado={c.estado_licencia} vence={c.licencia_vence} />
                </div>
            ),
            sortKey: 'licencia_vence',
        },
        {
            key: 'vehiculos', label: 'Maneja', sortable: false,
            render: c => c.vehiculos.length ? <span className="font-mono text-[13px]">{c.vehiculos.join(', ')}</span> : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
            exportValue: c => c.vehiculos.join(', '),
        },
        {
            key: 'viajes_mes', label: 'Viajes del mes', align: 'right',
            render: c => <span className="tabular-nums font-semibold">{c.viajes_mes}</span>,
        },
        {
            key: 'acciones', label: '', sortable: false,
            render: c => (
                <TableActions
                    onEdit={puede.editar ? () => editar(c) : undefined}
                    onDelete={puede.eliminar && c.activo ? () => setBorrar(c) : undefined}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Conductores">
            <PageHeader
                title="Conductores"
                subtitle="Choferes y ayudantes de reparto. El DNI y la licencia del chofer salen solos en la guía de remisión."
                icon={<IdCard size={20} />}
                actions={puede.crear && <Button onClick={nuevo} startContent={<Plus size={15} />}>Nuevo conductor</Button>}
            />

            {alertas.length > 0 && (
                <Callout variant={alertas.some(a => a.estado_licencia === 'vencida') ? 'danger' : 'warning'} className="mb-4"
                    title={alertas.length === 1 ? 'Una licencia necesita atención' : `${alertas.length} licencias necesitan atención`}>
                    {alertas.map(a => `${a.nombre_completo} (${a.estado_licencia === 'vencida' ? 'vencida' : 'vence pronto'})`).join(', ')}.
                </Callout>
            )}

            <Table
                data={conductores}
                columns={columnas}
                searchPlaceholder="Buscar nombre, DNI o licencia…"
                emptyMessage="Todavía no hay conductores. Agrega a tus choferes y ayudantes."
                rowClassName={c => (c.activo ? '' : 'opacity-60')}
                exportable
                exportFilename="conductores"
            />

            <Modal isOpen={abierto} onClose={() => setAbierto(false)} size="xl"
                title={editando ? `Editar a ${editando.nombre_completo}` : 'Nuevo conductor'}
                footer={<>
                    <Button variant="ghost" onClick={() => setAbierto(false)} disabled={guardando}>Cancelar</Button>
                    <Button onClick={guardar} loading={guardando}>Guardar</Button>
                </>}>
                <div className="space-y-4">
                    <div role="radiogroup" aria-label="Función" className="grid grid-cols-2 gap-2">
                        {(['chofer', 'ayudante'] as Tipo[]).map(t => {
                            const activo = form.tipo === t;
                            return (
                                <button key={t} type="button" role="radio" aria-checked={activo} onClick={() => set('tipo', t)}
                                    className="rounded-xl px-3 py-2.5 text-left border transition-colors duration-150"
                                    style={{
                                        borderColor: activo ? 'var(--vp-navy)' : 'var(--color-border)',
                                        backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 7%, var(--color-surface))' : 'var(--color-surface)',
                                    }}>
                                    <span className="block text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{t === 'chofer' ? 'Chofer' : 'Ayudante'}</span>
                                    <span className="block text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                        {t === 'chofer' ? 'Maneja el camión; lleva licencia' : 'Va en el viaje para cargar y descargar'}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label="Nombres" required value={form.nombres} onChange={e => set('nombres', e.target.value)} error={errores.nombres} autoFocus />
                        <Input label="Apellidos" value={form.apellidos} onChange={e => set('apellidos', e.target.value)} error={errores.apellidos} />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Select label="Documento" value={form.tipo_documento} onChange={v => set('tipo_documento', String(v))}
                            options={[{ value: 'DNI', label: 'DNI' }, { value: 'CE', label: 'Carné de extranjería' }, { value: 'PASAPORTE', label: 'Pasaporte' }]} />
                        <Input label="Número" required={esChofer} inputMode={form.tipo_documento === 'DNI' ? 'numeric' : undefined}
                            value={form.numero_documento} error={errores.numero_documento}
                            onChange={e => set('numero_documento', form.tipo_documento === 'DNI' ? e.target.value.replace(/\D/g, '').slice(0, 8) : e.target.value)} />
                        <Input label="Teléfono" type="tel" inputMode="tel" value={form.telefono} onChange={e => set('telefono', e.target.value)} error={errores.telefono} />
                    </div>

                    {esChofer && (
                        <div className="rounded-xl p-3 space-y-3" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                            <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Licencia de conducir</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Input label="N.º de licencia" required value={form.licencia} onChange={e => set('licencia', e.target.value.toUpperCase())} error={errores.licencia} />
                                <Select label="Categoría" value={form.licencia_categoria} placeholder="Elegir…"
                                    onChange={v => set('licencia_categoria', String(v))}
                                    options={[{ value: '', label: 'Sin indicar' }, ...CATEGORIAS.map(c => ({ value: c, label: c }))]} />
                                <Input label="Vence" type="date" value={form.licencia_vence} onChange={e => set('licencia_vence', e.target.value)} error={errores.licencia_vence}
                                    hint="Avisa 30 días antes" />
                            </div>
                        </div>
                    )}

                    <Input label="Observación" value={form.observacion} onChange={e => set('observacion', e.target.value)} error={errores.observacion} />
                    {errores.tipo && <p className="text-sm" style={{ color: 'var(--color-danger)' }}>{errores.tipo}</p>}
                    {editando && <Switch label="Activo" checked={form.activo} onChange={v => set('activo', v)} />}
                </div>
            </Modal>

            <Modal isOpen={!!borrar} onClose={() => setBorrar(null)} size="sm" title={`Quitar a ${borrar?.nombre_completo ?? ''}`}
                footer={<>
                    <Button variant="ghost" onClick={() => setBorrar(null)}>Cancelar</Button>
                    <Button variant="danger" onClick={() => borrar && router.delete(route('flota.conductores.destroy', borrar.id), { preserveScroll: true, onFinish: () => setBorrar(null) })}>Quitar</Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Si ya fue en algún viaje, se desactiva y sigue en el historial. Si no, se elimina.
                </p>
            </Modal>
        </AppLayout>
    );
}
