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
interface Almacen  { id: number; nombre: string; tipo: string; }

interface Props extends PageProps {
    almacenes: Almacen[];
    productos: Producto[];
    mostrarSelector: boolean;
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
}

const emptyDetalle = (): DetalleRow => ({
    producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', precio_costo: '',
});

export default function EntradaCreate({ almacenes, productos, mostrarSelector }: Props) {
    const [almacenId, setAlmacenId]     = useState<number | ''>('');
    const [proveedor, setProveedor]     = useState('');
    const [nroDoc, setNroDoc]           = useState('');
    const [tipo, setTipo]               = useState<string>('compra');
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

    function subtotal(d: DetalleRow): number {
        const qty   = parseFloat(d.cantidad)     || 0;
        const cost  = parseFloat(d.precio_costo) || 0;
        return Math.round(qty * cost * 100) / 100;
    }

    function cantidadBase(d: DetalleRow): number {
        const qty    = parseFloat(d.cantidad)          || 0;
        const factor = parseFloat(d.factor_conversion) || 1;
        return Math.round(qty * factor * 10000) / 10000;
    }

    const total = detalles.reduce((sum, d) => sum + subtotal(d), 0);

    function submit(confirmar: boolean) {
        setProcessing(true);
        router.post(route('inventario.entradas.store'), {
            almacen_id:        almacenId,
            proveedor,
            numero_documento:  nroDoc,
            tipo,
            fecha,
            observacion,
            confirmar,
            detalles: detalles.map(d => ({
                producto_id:       d.producto_id,
                unidad_medida_id:  d.unidad_medida_id,
                cantidad:          d.cantidad,
                factor_conversion: d.factor_conversion,
                precio_costo:      d.precio_costo,
            })),
        }, {
            onSuccess: () => setProcessing(false),
            onError: (e) => { setErrors(e); setProcessing(false); },
        });
    }

    return (
        <AppLayout title="Nueva entrada">
            <PageHeader
                title="Nueva entrada de inventario"
                subtitle="Registra el ingreso de mercadería a un almacén"
                backHref={route('inventario.entradas.index')}
            />

            <div className="max-w-5xl mx-auto space-y-8">

                {/* ── Cabecera ── */}
                <section
                    className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                >
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Datos de la entrada
                    </h2>

                    <div className="grid grid-cols-2 gap-4">
                        {(mostrarSelector || almacenes.length > 1) && (
                            <Select
                                label="Almacén destino"
                                required
                                value={almacenId}
                                onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                                options={almacenes.map(a => ({ value: a.id, label: a.nombre }))}
                                error={errors.almacen_id}
                            />
                        )}
                        <Select
                            label="Tipo"
                            required
                            value={tipo}
                            onChange={v => setTipo(String(v))}
                            options={[
                                { value: 'compra',     label: 'Compra' },
                                { value: 'ajuste',     label: 'Ajuste' },
                                { value: 'devolucion', label: 'Devolución' },
                                { value: 'otro',       label: 'Otro' },
                            ]}
                        />
                        <Input label="Proveedor" value={proveedor} onChange={e => setProveedor(e.target.value)} placeholder="Nombre del proveedor" />
                        <Input label="Nro. documento" value={nroDoc} onChange={e => setNroDoc(e.target.value)} placeholder="Ej: F001-0001234" />
                        <Input label="Fecha" required type="date" value={fecha} onChange={e => setFecha(e.target.value)} error={errors.fecha} />
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

                {/* ── Detalle ── */}
                <section
                    className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                >
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                            Productos
                        </h2>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar producto
                        </Button>
                    </div>

                    {errors.detalles && (
                        <p className="text-sm" style={{ color: 'var(--color-danger)' }}>{errors.detalles}</p>
                    )}

                    {/* Cabecera tabla */}
                    <div className="hidden md:grid grid-cols-12 gap-2 text-xs font-semibold uppercase tracking-wide px-1"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <div className="col-span-3">Producto</div>
                        <div className="col-span-2">Unidad</div>
                        <div className="col-span-2">Cantidad</div>
                        <div className="col-span-2">Precio costo</div>
                        <div className="col-span-1 text-right">Factor</div>
                        <div className="col-span-1 text-right">Cant. base</div>
                        <div className="col-span-1 text-right">Subtotal</div>
                    </div>

                    {detalles.map((d, i) => {
                        const unidades = unidadesDeProducto(d.producto_id);
                        return (
                            <div key={i} className="grid grid-cols-12 gap-2 items-end rounded-xl p-3"
                                style={{ backgroundColor: 'var(--color-bg)' }}>
                                <div className="col-span-3">
                                    <Select
                                        placeholder="Buscar producto..."
                                        value={d.producto_id}
                                        onChange={v => setDetalle(i, 'producto_id', Number(v))}
                                        options={productos.map(p => ({ value: p.id, label: p.codigo ? `[${p.codigo}] ${p.nombre}` : p.nombre }))}
                                        error={(errors as Record<string, string>)[`detalles.${i}.producto_id`]}
                                    />
                                </div>
                                <div className="col-span-2">
                                    <Select
                                        placeholder="Unidad"
                                        value={d.unidad_medida_id}
                                        onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                        options={unidades.map(u => ({
                                            value: u.unidad_medida_id,
                                            label: u.unidad_medida ? `${u.unidad_medida.abreviatura}${u.es_base ? ' (base)' : ''}` : String(u.unidad_medida_id),
                                        }))}
                                        error={(errors as Record<string, string>)[`detalles.${i}.unidad_medida_id`]}
                                    />
                                </div>
                                <div className="col-span-2">
                                    <Input
                                        placeholder="0"
                                        type="number" min="0.0001" step="0.0001"
                                        value={d.cantidad}
                                        onChange={e => setDetalle(i, 'cantidad', e.target.value)}
                                        error={(errors as Record<string, string>)[`detalles.${i}.cantidad`]}
                                    />
                                </div>
                                <div className="col-span-2">
                                    <Input
                                        placeholder="0.00"
                                        type="number" min="0" step="0.0001"
                                        value={d.precio_costo}
                                        onChange={e => setDetalle(i, 'precio_costo', e.target.value)}
                                        error={(errors as Record<string, string>)[`detalles.${i}.precio_costo`]}
                                    />
                                </div>
                                {/* Factor y cantidad_base — informativos */}
                                <div className="col-span-1 text-right">
                                    <p className="text-xs font-mono pb-1" style={{ color: 'var(--color-text-muted)' }}>
                                        ×{d.factor_conversion}
                                    </p>
                                </div>
                                <div className="col-span-1 text-right">
                                    <p className="text-xs font-mono pb-1" style={{ color: 'var(--color-text-muted)' }}>
                                        {cantidadBase(d).toFixed(4)}
                                    </p>
                                </div>
                                <div className="col-span-1 text-right flex items-end justify-end gap-1">
                                    <p className="text-sm font-mono font-semibold pb-1" style={{ color: 'var(--color-text)' }}>
                                        S/ {subtotal(d).toFixed(2)}
                                    </p>
                                    {detalles.length > 1 && (
                                        <button type="button" onClick={() => removeDetalle(i)}
                                            className="mb-1 rounded p-0.5" style={{ color: 'var(--color-danger)' }}>
                                            <Trash2 size={14} />
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {/* Total */}
                    <div className="flex justify-end pt-2 border-t" style={{ borderColor: 'var(--color-border)' }}>
                        <div className="text-right">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Total</p>
                            <p className="text-xl font-bold font-mono" style={{ color: 'var(--color-text)' }}>
                                S/ {total.toFixed(2)}
                            </p>
                        </div>
                    </div>
                </section>

                {/* ── Acciones ── */}
                <div className="flex gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('inventario.entradas.index'))}>
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
