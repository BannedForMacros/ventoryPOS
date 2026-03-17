import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import type { PageProps } from '@/types';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; local?: { nombre: string } | null; }

interface Props extends PageProps {
    almacenes: Almacen[];
    productos: Producto[];
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
}

export default function TransferenciaCreate({ almacenes, productos }: Props) {
    const [origenId, setOrigenId]       = useState<number | ''>('');
    const [destinoId, setDestinoId]     = useState<number | ''>('');
    const [fecha, setFecha]             = useState(new Date().toISOString().split('T')[0]);
    const [observacion, setObservacion] = useState('');
    const [detalles, setDetalles]       = useState<DetalleRow[]>([{ producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1' }]);
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [processing, setProcessing]   = useState(false);

    function unidadesDeProducto(productoId: number | ''): ProductoUnidad[] {
        if (!productoId) return [];
        return productos.find(p => p.id === productoId)?.unidades ?? [];
    }

    function setDetalle(i: number, field: keyof DetalleRow, value: string | number) {
        setDetalles(prev => {
            const updated = prev.map((d, idx) => idx !== i ? d : { ...d, [field]: value });
            if (field === 'producto_id') {
                const base = unidadesDeProducto(value as number).find(u => u.es_base);
                updated[i].unidad_medida_id  = base?.unidad_medida_id ?? '';
                updated[i].factor_conversion = '1';
            }
            if (field === 'unidad_medida_id') {
                const unidad = unidadesDeProducto(updated[i].producto_id).find(u => u.unidad_medida_id === Number(value));
                updated[i].factor_conversion = unidad ? String(unidad.factor_conversion) : '1';
            }
            return updated;
        });
    }

    function addDetalle()    { setDetalles(d => [...d, { producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1' }]); }
    function removeDetalle(i: number) { setDetalles(d => d.filter((_, idx) => idx !== i)); }

    function cantidadBase(d: DetalleRow): number {
        return Math.round((parseFloat(d.cantidad) || 0) * (parseFloat(d.factor_conversion) || 1) * 10000) / 10000;
    }

    function submit(confirmar: boolean) {
        setProcessing(true);
        router.post(route('inventario.transferencias.store'), {
            almacen_origen_id:  origenId,
            almacen_destino_id: destinoId,
            fecha,
            observacion,
            confirmar,
            detalles: detalles.map(d => ({
                producto_id:       d.producto_id,
                unidad_medida_id:  d.unidad_medida_id,
                cantidad:          d.cantidad,
                factor_conversion: d.factor_conversion,
            })),
        }, {
            onSuccess: () => setProcessing(false),
            onError:   (e) => { setErrors(e); setProcessing(false); },
        });
    }

    const almacenOptions = almacenes.map(a => ({
        value: a.id,
        label: a.local ? `${a.nombre} · ${a.local.nombre}` : a.nombre,
    }));

    return (
        <AppLayout title="Nueva transferencia">
            <PageHeader
                title="Nueva transferencia de stock"
                subtitle="Mueve mercadería entre almacenes"
                backHref={route('inventario.transferencias.index')}
            />

            <div className="max-w-5xl mx-auto space-y-8">

                <section className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Datos de la transferencia
                    </h2>
                    <div className="grid grid-cols-2 gap-4">
                        <Select label="Almacén origen" required value={origenId}
                            onChange={v => setOrigenId(v === '' ? '' : Number(v))}
                            options={almacenOptions.filter(a => a.value !== destinoId)}
                            error={errors.almacen_origen_id} />
                        <Select label="Almacén destino" required value={destinoId}
                            onChange={v => setDestinoId(v === '' ? '' : Number(v))}
                            options={almacenOptions.filter(a => a.value !== origenId)}
                            error={errors.almacen_destino_id} />
                        <Input label="Fecha" required type="date" value={fecha}
                            onChange={e => setFecha(e.target.value)} error={errors.fecha} />
                    </div>
                    <div>
                        <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Observación</label>
                        <textarea rows={2} value={observacion} onChange={e => setObservacion(e.target.value)}
                            className="w-full rounded-xl border px-3 py-2 text-sm outline-none resize-none transition-all"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            onFocus={e => e.currentTarget.style.borderColor = 'var(--color-primary)'}
                            onBlur={e => e.currentTarget.style.borderColor = 'var(--color-border)'} />
                    </div>
                </section>

                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Productos a transferir</h2>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                El costo unitario se capturará del stock actual del almacén origen
                            </p>
                        </div>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar producto
                        </Button>
                    </div>

                    <div className="hidden md:grid grid-cols-12 gap-2 text-xs font-semibold uppercase tracking-wide px-1"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <div className="col-span-4">Producto</div>
                        <div className="col-span-3">Unidad</div>
                        <div className="col-span-3">Cantidad</div>
                        <div className="col-span-1 text-right">Factor</div>
                        <div className="col-span-1 text-right">Cant. base</div>
                    </div>

                    {detalles.map((d, i) => {
                        const unidades = unidadesDeProducto(d.producto_id);
                        return (
                            <div key={i} className="grid grid-cols-12 gap-2 items-end rounded-xl p-3"
                                style={{ backgroundColor: 'var(--color-bg)' }}>
                                <div className="col-span-4">
                                    <Select placeholder="Buscar producto..." value={d.producto_id}
                                        onChange={v => setDetalle(i, 'producto_id', Number(v))}
                                        options={productos.map(p => ({ value: p.id, label: p.codigo ? `[${p.codigo}] ${p.nombre}` : p.nombre }))}
                                        error={(errors)[`detalles.${i}.producto_id`]} />
                                </div>
                                <div className="col-span-3">
                                    <Select placeholder="Unidad" value={d.unidad_medida_id}
                                        onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                        options={unidades.map(u => ({
                                            value: u.unidad_medida_id,
                                            label: u.unidad_medida ? `${u.unidad_medida.abreviatura}${u.es_base ? ' (base)' : ''}` : String(u.unidad_medida_id),
                                        }))}
                                        error={(errors)[`detalles.${i}.unidad_medida_id`]} />
                                </div>
                                <div className="col-span-3">
                                    <Input placeholder="0" type="number" min="0.0001" step="0.0001"
                                        value={d.cantidad} onChange={e => setDetalle(i, 'cantidad', e.target.value)}
                                        error={(errors)[`detalles.${i}.cantidad`]} />
                                </div>
                                <div className="col-span-1 text-right">
                                    <p className="text-xs font-mono pb-1" style={{ color: 'var(--color-text-muted)' }}>×{d.factor_conversion}</p>
                                </div>
                                <div className="col-span-1 text-right flex items-end justify-end gap-1">
                                    <p className="text-xs font-mono pb-1" style={{ color: 'var(--color-text-muted)' }}>{cantidadBase(d).toFixed(4)}</p>
                                    {detalles.length > 1 && (
                                        <button type="button" onClick={() => removeDetalle(i)} className="mb-1 rounded p-0.5" style={{ color: 'var(--color-danger)' }}>
                                            <Trash2 size={14} />
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </section>

                <div className="flex gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('inventario.transferencias.index'))}>
                        Cancelar
                    </Button>
                    <Button type="button" variant="secondary" loading={processing} onClick={() => submit(false)}>
                        Guardar borrador
                    </Button>
                    <Button type="button" loading={processing} onClick={() => submit(true)}>
                        Guardar y confirmar
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
