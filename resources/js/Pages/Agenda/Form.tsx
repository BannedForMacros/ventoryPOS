import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2, Calendar, User as UserIcon, Briefcase, FileText, Clock } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import type { PageProps } from '@/types';

interface ClienteOpt { id: number; nombres: string | null; apellidos: string | null; razon_social: string | null; tipo_documento: string | null; numero_documento: string | null; }
interface ProductoOpt { id: number; nombre: string; codigo: string | null; tipo: 'producto' | 'servicio';
    unidades?: Array<{ id: number; precio_venta: string; activo: boolean; unidad_medida?: { id: number; nombre: string; abreviatura: string } | null }>;
}
interface ItemRow {
    id?: number;
    producto_id: number | '';
    producto_unidad_id: number | '';
    cantidad: string;
    duracion_min: string;
    observaciones: string;
}

interface CitaExistente {
    id: number;
    numero: string;
    local_id: number;
    cliente_id: number;
    profesional_id: number | null;
    fecha_hora: string;
    duracion_min: number;
    observaciones: string | null;
    sujeto_nombre: string | null;
    sujeto_descripcion: string | null;
    items: Array<{
        id: number; producto_id: number; producto_unidad_id: number;
        cantidad: string; duracion_min: number; observaciones: string | null;
    }>;
}

interface Props extends PageProps {
    cita?: CitaExistente;
    locales: { id: number; nombre: string }[];
    profesionales: { id: number; name: string }[];
    clientes: ClienteOpt[];
    productos: ProductoOpt[];
    agendaConfig: { sujeto_label: string | null; sujeto_requerido: boolean };
    currentLocalId: number | null;
    currentUserId: number;
}

function nombreCliente(c: ClienteOpt): string {
    if (c.razon_social) return c.razon_social;
    return [c.nombres, c.apellidos].filter(Boolean).join(' ') || 'Cliente';
}

const emptyItem = (): ItemRow => ({
    producto_id: '', producto_unidad_id: '',
    cantidad: '1', duracion_min: '30', observaciones: '',
});

/** Convierte ISO a "YYYY-MM-DDTHH:mm" para <input type="datetime-local"> */
function toLocalInput(iso?: string): string {
    if (!iso) {
        const d = new Date();
        d.setMinutes(0, 0, 0); d.setHours(d.getHours() + 1);
        return d.toISOString().slice(0, 16);
    }
    return iso.slice(0, 16);
}

export default function AgendaForm({
    cita, locales, profesionales, clientes, productos, agendaConfig, currentLocalId, currentUserId,
}: Props) {
    const editando = !!cita;

    const { data, setData, processing, errors } = useForm<{
        local_id: number | '';
        cliente_id: number | '';
        profesional_id: number | '';
        fecha_hora: string;
        observaciones: string;
        sujeto_nombre: string;
        sujeto_descripcion: string;
        items: ItemRow[];
    }>({
        local_id:        cita?.local_id ?? currentLocalId ?? (locales[0]?.id ?? ''),
        cliente_id:      cita?.cliente_id ?? '',
        profesional_id:  cita?.profesional_id ?? currentUserId,
        fecha_hora:      toLocalInput(cita?.fecha_hora),
        observaciones:   cita?.observaciones ?? '',
        sujeto_nombre:   cita?.sujeto_nombre ?? '',
        sujeto_descripcion: cita?.sujeto_descripcion ?? '',
        items: cita?.items.length
            ? cita.items.map(it => ({
                id: it.id,
                producto_id: it.producto_id,
                producto_unidad_id: it.producto_unidad_id,
                cantidad: String(it.cantidad),
                duracion_min: String(it.duracion_min),
                observaciones: it.observaciones ?? '',
            }))
            : [emptyItem()],
    });

    const [busquedaCliente, setBusquedaCliente] = useState('');

    function setItem(i: number, field: keyof ItemRow, value: string | number) {
        const next = data.items.map((it, idx) => idx !== i ? it : { ...it, [field]: value });
        setData('items', next);
    }

    function addItem()    { setData('items', [...data.items, emptyItem()]); }
    function removeItem(i: number) { setData('items', data.items.filter((_, idx) => idx !== i)); }

    function onProductoChange(i: number, productoId: number) {
        const prod = productos.find(p => p.id === productoId);
        const unidadBase = prod?.unidades?.find(u => u.activo) ?? prod?.unidades?.[0];
        const next = data.items.map((it, idx) => idx !== i ? it : {
            ...it,
            producto_id: productoId,
            producto_unidad_id: unidadBase?.id ?? '',
        });
        setData('items', next);
    }

    const duracionTotal = useMemo(() =>
        data.items.reduce((s, it) => s + (Number(it.duracion_min) || 0) * (Number(it.cantidad) || 0), 0),
    [data.items]);

    const precioEstimado = useMemo(() =>
        data.items.reduce((s, it) => {
            const prod = productos.find(p => p.id === it.producto_id);
            const unit = prod?.unidades?.find(u => u.id === it.producto_unidad_id);
            return s + (Number(unit?.precio_venta ?? 0)) * (Number(it.cantidad) || 0);
        }, 0),
    [data.items, productos]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        // Convertir fecha local a ISO con :00 segundos
        const payload = { ...data, fecha_hora: data.fecha_hora.replace('T', ' ') + ':00' };
        if (editando && cita) {
            router.put(route('agenda.update', cita.id), payload as any);
        } else {
            router.post(route('agenda.store'), payload as any);
        }
    }

    return (
        <AppLayout title={editando ? 'Editar cita' : 'Nueva cita'}>
            <PageHeader
                title={editando ? `Editar cita ${cita?.numero}` : 'Nueva cita'}
                subtitle={editando ? 'Modifica los datos de la cita' : 'Programa una nueva visita'}
                backHref={route('agenda.index')}
            />

            <form onSubmit={submit} className="max-w-4xl mx-auto space-y-5">

                {/* ── Sección 1: Datos generales ── */}
                <section className="rounded-2xl border p-5 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        <Calendar size={14} className="inline mr-1.5 -mt-0.5" />
                        Datos generales
                    </h2>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <SearchableSelect
                            label="Cliente"
                            required
                            value={data.cliente_id}
                            onChange={v => setData('cliente_id', Number(v))}
                            options={clientes.map(c => ({
                                value: c.id,
                                label: `${nombreCliente(c)}${c.numero_documento ? ` — ${c.tipo_documento} ${c.numero_documento}` : ''}`
                            }))}
                            searchPlaceholder="Buscar por nombre o documento..."
                            error={errors.cliente_id}
                        />

                        <div>
                            <label className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                Fecha y hora <span style={{ color: 'var(--color-danger)' }}>*</span>
                            </label>
                            <input type="datetime-local" value={data.fecha_hora}
                                onChange={e => setData('fecha_hora', e.target.value)}
                                className="block w-full mt-1 rounded-xl border px-3 py-2 text-sm outline-none"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            {errors.fecha_hora && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.fecha_hora}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {locales.length > 1 && (
                            <SearchableSelect
                                label="Local"
                                required
                                value={data.local_id}
                                onChange={v => setData('local_id', Number(v))}
                                options={locales.map(l => ({ value: l.id, label: l.nombre }))}
                                error={errors.local_id}
                            />
                        )}
                        <SearchableSelect
                            label="Profesional asignado"
                            value={data.profesional_id}
                            onChange={v => setData('profesional_id', v === '' ? '' : Number(v))}
                            options={profesionales.map(p => ({ value: p.id, label: p.name }))}
                            placeholder="Sin asignar"
                            searchPlaceholder="Buscar..."
                            error={errors.profesional_id}
                        />
                    </div>

                    <Input label="Observaciones generales"
                        value={data.observaciones}
                        onChange={e => setData('observaciones', e.target.value)}
                        placeholder="Notas internas de la cita..."
                        error={errors.observaciones} />
                </section>

                {/* ── Sección 2: Sujeto multidisciplina (solo si la empresa lo configura) ── */}
                {agendaConfig.sujeto_label && (
                    <section className="rounded-2xl border p-5 space-y-4"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                            <UserIcon size={14} className="inline mr-1.5 -mt-0.5" />
                            {agendaConfig.sujeto_label}
                        </h2>

                        <Input
                            label={`Nombre ${agendaConfig.sujeto_label.toLowerCase()}`}
                            required={agendaConfig.sujeto_requerido}
                            value={data.sujeto_nombre}
                            onChange={e => setData('sujeto_nombre', e.target.value)}
                            placeholder={`Ej: Toby`}
                            error={errors.sujeto_nombre}
                        />

                        <div>
                            <label className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                Descripción / notas
                            </label>
                            <textarea rows={3} value={data.sujeto_descripcion}
                                onChange={e => setData('sujeto_descripcion', e.target.value)}
                                placeholder="Ej: Labrador macho, 5 años, alérgico a la penicilina"
                                className="block w-full mt-1 rounded-xl border px-3 py-2 text-sm outline-none resize-none"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            {errors.sujeto_descripcion && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.sujeto_descripcion}</p>}
                        </div>
                    </section>
                )}

                {/* ── Sección 3: Servicios y productos ── */}
                <section className="rounded-2xl border p-5 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                <Briefcase size={14} className="inline mr-1.5 -mt-0.5" />
                                Servicios reservados
                            </h2>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                Lo que el cliente reserva. En el momento del cobro podrás agregar más cosas.
                            </p>
                        </div>
                        <Button type="button" variant="ghost" onClick={addItem} startContent={<Plus size={14} />}>
                            Agregar servicio
                        </Button>
                    </div>

                    {errors.items && (
                        <div className="rounded-lg px-3 py-2 text-sm"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}>
                            {errors.items}
                        </div>
                    )}

                    {data.items.map((item, i) => {
                        const prod = productos.find(p => p.id === item.producto_id);
                        const unidadesActivas = (prod?.unidades ?? []).filter(u => u.activo);

                        return (
                            <div key={i} className="rounded-xl border p-3 space-y-3"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                <div className="flex justify-between items-start">
                                    <span className="text-xs font-semibold uppercase" style={{ color: 'var(--color-text-muted)' }}>
                                        Servicio #{i + 1}
                                    </span>
                                    {data.items.length > 1 && (
                                        <button type="button" onClick={() => removeItem(i)}
                                            className="rounded p-1 hover:bg-black/5" style={{ color: 'var(--color-danger)' }}>
                                            <Trash2 size={14} />
                                        </button>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <SearchableSelect
                                        label="Producto / servicio"
                                        required
                                        value={item.producto_id}
                                        onChange={v => onProductoChange(i, Number(v))}
                                        options={productos.map(p => ({
                                            value: p.id,
                                            label: `${p.nombre}${p.codigo ? ` (${p.codigo})` : ''}${p.tipo === 'servicio' ? ' • Servicio' : ''}`,
                                        }))}
                                        searchPlaceholder="Buscar producto o servicio..."
                                        error={(errors as any)[`items.${i}.producto_id`]}
                                    />
                                    <SearchableSelect
                                        label="Presentación"
                                        required
                                        value={item.producto_unidad_id}
                                        onChange={v => setItem(i, 'producto_unidad_id', Number(v))}
                                        options={unidadesActivas.map(u => ({
                                            value: u.id,
                                            label: `${u.unidad_medida?.nombre ?? '—'} — S/. ${Number(u.precio_venta).toFixed(2)}`,
                                        }))}
                                        searchPlaceholder="Buscar..."
                                        error={(errors as any)[`items.${i}.producto_unidad_id`]}
                                    />
                                </div>

                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <Input label="Cantidad" type="number" min="0.0001" step="0.01"
                                        value={item.cantidad}
                                        onChange={e => setItem(i, 'cantidad', e.target.value)}
                                        error={(errors as any)[`items.${i}.cantidad`]} />
                                    <Input label="Duración (min)" type="number" min="1" step="1"
                                        value={item.duracion_min}
                                        onChange={e => setItem(i, 'duracion_min', e.target.value)}
                                        error={(errors as any)[`items.${i}.duracion_min`]} />
                                    <Input label="Observaciones"
                                        value={item.observaciones}
                                        onChange={e => setItem(i, 'observaciones', e.target.value)}
                                        placeholder="Notas..." />
                                </div>
                            </div>
                        );
                    })}

                    {/* Resumen de duración y precio estimado */}
                    <div className="flex justify-between items-center pt-3 border-t" style={{ borderColor: 'var(--color-border)' }}>
                        <div className="flex items-center gap-2 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            <Clock size={14} />
                            Duración total: <strong style={{ color: 'var(--color-text)' }}>{duracionTotal} min</strong>
                        </div>
                        <div className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            Precio estimado: <strong style={{ color: 'var(--color-primary)' }}>S/. {precioEstimado.toFixed(2)}</strong>
                        </div>
                    </div>
                </section>

                {/* ── Acciones ── */}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('agenda.index'))}>
                        Cancelar
                    </Button>
                    <Button type="submit" loading={processing}>
                        {editando ? 'Guardar cambios' : 'Crear cita'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
