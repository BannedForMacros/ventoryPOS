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

interface SalidaDetalleData {
    id?: number;
    producto_id: number;
    unidad_medida_id: number;
    cantidad: string;
    factor_conversion: string;
    observacion: string | null;
}

interface SalidaData {
    id: number;
    almacen_id: number;
    salida_tipo_id: number;
    numero_documento: string | null;
    fecha: string;
    observacion: string | null;
    detalles: SalidaDetalleData[];
}

interface Props extends PageProps {
    salida: SalidaData;
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

export default function SalidaEdit({ salida, almacenes, tipos, productos }: Props) {
    const [almacenId, setAlmacenId]     = useState<number | ''>(salida.almacen_id);
    const [tipoId, setTipoId]           = useState<number | ''>(salida.salida_tipo_id);
    const [nroDoc, setNroDoc]           = useState(salida.numero_documento ?? '');
    const [fecha, setFecha]             = useState(salida.fecha);
    const [observacion, setObservacion] = useState(salida.observacion ?? '');
    const [detalles, setDetalles]       = useState<DetalleRow[]>(
        salida.detalles.map(d => ({
            producto_id:       d.producto_id,
            unidad_medida_id:  d.unidad_medida_id,
            cantidad:          String(d.cantidad),
            factor_conversion: String(d.factor_conversion),
            observacion:       d.observacion ?? '',
        }))
    );
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
                updated[i].factor_conversion = '1';
            }
            if (field === 'unidad_medida_id') {
                const unidades = unidadesDeProducto(updated[i].producto_id);
                const unidad   = unidades.find(u => u.unidad_medida_id === Number(value));
                updated[i].factor_conversion = unidad ? String(unidad.factor_conversion) : '1';
            }
            return updated;
        });
    }

    function addDetalle()    { setDetalles(d => [...d, { producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', observacion: '' }]); }
    function removeDetalle(i: number) { setDetalles(d => d.filter((_, idx) => idx !== i)); }

    function cantidadBase(d: DetalleRow): number {
        const qty    = parseFloat(d.cantidad)          || 0;
        const factor = parseFloat(d.factor_conversion) || 1;
        return Math.round(qty * factor * 10000) / 10000;
    }

    function submit() {
        setProcessing(true);
        router.put(route('inventario.salidas.update', salida.id), {
            almacen_id:        almacenId,
            salida_tipo_id:    tipoId,
            numero_documento:  nroDoc,
            fecha,
            observacion,
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
        <AppLayout title={`Editar salida #${salida.id}`}>
            <PageHeader
                title={`Editar salida #${salida.id}`}
                subtitle="Solo se pueden editar salidas en borrador"
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
                        <Input label="N° documento" value={nroDoc} onChange={e => setNroDoc(e.target.value)} />
                        <Input label="Fecha" type="date" required value={fecha} onChange={e => setFecha(e.target.value)} />
                    </div>

                    <Input label="Observación" value={observacion} onChange={e => setObservacion(e.target.value)} />
                </section>

                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Detalles</h2>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar línea
                        </Button>
                    </div>

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
                    <Button loading={processing} onClick={submit}>
                        Guardar cambios
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
