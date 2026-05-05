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
interface TipoSalida { id: number; nombre: string; slug: string; }

interface Props extends PageProps {
    almacenes: Almacen[];
    tipos: TipoSalida[];
    productos: Producto[];
    mostrarSelector: boolean;
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    observacion: string;
}

const emptyDetalle = (): DetalleRow => ({
    producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', observacion: '',
});

export default function SalidaCreate({ almacenes, tipos, productos, mostrarSelector }: Props) {
    const [almacenId, setAlmacenId]     = useState<number | ''>(almacenes.length === 1 ? almacenes[0].id : '');
    const [tipoId, setTipoId]           = useState<number | ''>(tipos[0]?.id ?? '');
    const [nroDoc, setNroDoc]           = useState('');
    const [fecha, setFecha]             = useState(new Date().toISOString().split('T')[0]);
    const [observacion, setObservacion] = useState('');
    const [detalles, setDetalles]       = useState<DetalleRow[]>([emptyDetalle()]);
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
                const unidades = unidadesDeProducto(value as number);
                const base = unidades.find(u => u.es_base);
                updated[i].unidad_medida_id  = base?.unidad_medida_id ?? '';
                updated[i].factor_conversion = base ? '1' : '1';
            }
            if (field === 'unidad_medida_id') {
                const unidades = unidadesDeProducto(updated[i].producto_id);
                const unidad   = unidades.find(u => u.unidad_medida_id === Number(value));
                updated[i].factor_conversion = unidad ? String(unidad.factor_conversion) : '1';
            }
            return updated;
        });
    }

    function addDetalle()    { setDetalles(d => [...d, emptyDetalle()]); }
    function removeDetalle(i: number) { setDetalles(d => d.filter((_, idx) => idx !== i)); }

    function cantidadBase(d: DetalleRow): number {
        const qty    = parseFloat(d.cantidad)          || 0;
        const factor = parseFloat(d.factor_conversion) || 1;
        return Math.round(qty * factor * 10000) / 10000;
    }

    function submit(confirmar: boolean) {
        setProcessing(true);
        router.post(route('inventario.salidas.store'), {
            almacen_id:        almacenId,
            salida_tipo_id:    tipoId,
            numero_documento:  nroDoc,
            fecha,
            observacion,
            confirmar,
            detalles: detalles.map(d => ({
                producto_id:       d.producto_id,
                unidad_medida_id:  d.unidad_medida_id,
                cantidad:          d.cantidad,
                factor_conversion: d.factor_conversion,
                observacion:       d.observacion,
            })),
        }, {
            onSuccess: () => setProcessing(false),
            onError: (e) => { setErrors(e); setProcessing(false); },
        });
    }

    return (
        <AppLayout title="Nueva salida">
            <PageHeader
                title="Nueva salida de inventario"
                subtitle="Merma, ajuste, baja, consumo interno u otro motivo"
                backHref={route('inventario.salidas.index')}
            />

            <div className="space-y-6 max-w-5xl">
                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Select
                            label="Almacén"
                            required
                            value={almacenId}
                            onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                            options={almacenes.map(a => ({
                                value: a.id,
                                label: `${a.nombre}${a.local ? ' · ' + a.local.nombre : ''}`,
                            }))}
                            error={errors.almacen_id}
                        />
                        <Select
                            label="Tipo de salida"
                            required
                            value={tipoId}
                            onChange={v => setTipoId(v === '' ? '' : Number(v))}
                            options={tipos.map(t => ({ value: t.id, label: t.nombre }))}
                            error={errors.salida_tipo_id}
                        />
                        <Input
                            label="N° documento (opcional)"
                            value={nroDoc}
                            onChange={e => setNroDoc(e.target.value)}
                            error={errors.numero_documento}
                        />
                        <Input
                            label="Fecha"
                            type="date"
                            required
                            value={fecha}
                            onChange={e => setFecha(e.target.value)}
                            error={errors.fecha}
                        />
                    </div>

                    <Input
                        label="Observación general (opcional)"
                        value={observacion}
                        onChange={e => setObservacion(e.target.value)}
                    />
                </section>

                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                Productos a sacar
                            </h2>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                El costo unitario se captura al confirmar la salida (costo promedio actual del producto).
                            </p>
                        </div>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar línea
                        </Button>
                    </div>

                    {errors.detalles && (
                        <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{errors.detalles}</p>
                    )}

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium w-1/3">Producto</th>
                                    <th className="text-left py-2 px-2 font-medium">Unidad</th>
                                    <th className="text-right py-2 px-2 font-medium">Cantidad</th>
                                    <th className="text-right py-2 px-2 font-medium">Cant. base</th>
                                    <th className="text-left py-2 px-2 font-medium">Obs.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((d, i) => {
                                    const unidades = unidadesDeProducto(d.producto_id);
                                    return (
                                        <tr key={i} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <td className="py-2 px-2">
                                                <Select
                                                    value={d.producto_id}
                                                    onChange={v => setDetalle(i, 'producto_id', v === '' ? '' : Number(v))}
                                                    options={productos.map(p => ({
                                                        value: p.id,
                                                        label: `${p.codigo ? `[${p.codigo}] ` : ''}${p.nombre}`,
                                                    }))}
                                                    placeholder="Seleccionar"
                                                />
                                            </td>
                                            <td className="py-2 px-2">
                                                <Select
                                                    value={d.unidad_medida_id}
                                                    onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                                    options={unidades.map(u => ({
                                                        value: u.unidad_medida_id,
                                                        label: u.unidad_medida?.nombre ?? '',
                                                    }))}
                                                    placeholder="—"
                                                    disabled={!d.producto_id}
                                                />
                                            </td>
                                            <td className="py-2 px-2 w-28">
                                                <input
                                                    type="number" step="0.0001" min="0"
                                                    value={d.cantidad}
                                                    onChange={e => setDetalle(i, 'cantidad', e.target.value)}
                                                    className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                                />
                                            </td>
                                            <td className="py-2 px-2 text-right text-xs tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                                {cantidadBase(d).toFixed(4)}
                                            </td>
                                            <td className="py-2 px-2 w-44">
                                                <input
                                                    type="text"
                                                    value={d.observacion}
                                                    onChange={e => setDetalle(i, 'observacion', e.target.value)}
                                                    placeholder="—"
                                                    className="w-full rounded-lg border px-2 py-1 text-xs"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                                />
                                            </td>
                                            <td className="py-2 px-1 w-10">
                                                {detalles.length > 1 && (
                                                    <button type="button" onClick={() => removeDetalle(i)}
                                                        className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}>
                                                        <Trash2 size={14} />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="flex gap-3">
                    <Button variant="ghost" onClick={() => router.visit(route('inventario.salidas.index'))}>
                        Cancelar
                    </Button>
                    <Button variant="ghost" loading={processing} onClick={() => submit(false)}>
                        Guardar borrador
                    </Button>
                    <Button loading={processing} onClick={() => submit(true)}>
                        Confirmar y descontar stock
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
