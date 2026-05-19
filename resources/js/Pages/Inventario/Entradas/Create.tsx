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
interface Proveedor { id: number; razon_social: string | null; nombre_comercial: string | null; numero_documento: string | null; tipo_documento: string; }

interface Props extends PageProps {
    almacenes: Almacen[];
    productos: Producto[];
    proveedores: Proveedor[];
    mostrarSelector: boolean;
    modoAlmacen: 'simple' | 'central_y_local';
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    // Vacío = hereda numero_documento de la cabecera. Si el proveedor facturó
    // la mercadería en varias facturas, cada item puede tener la suya propia.
    numero_documento: string;
}

const emptyDetalle = (): DetalleRow => ({
    producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', precio_costo: '', numero_documento: '',
});

export default function EntradaCreate({ almacenes, productos, proveedores, mostrarSelector, modoAlmacen }: Props) {
    const [almacenId, setAlmacenId]     = useState<number | ''>(almacenes.length === 1 ? almacenes[0].id : '');
    const [proveedorId, setProveedorId] = useState<number | ''>('');
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
            proveedor_id:      proveedorId || null,
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
                numero_documento:  d.numero_documento.trim() || null,
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

                    {modoAlmacen === 'central_y_local' && (
                        <div className="rounded-xl px-4 py-3 text-sm"
                            style={{ backgroundColor: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.2)', color: 'var(--color-text)' }}>
                            Las entradas (compras) ingresan al <strong>almacén central</strong>. Para mover stock a un local usa el módulo de <strong>Transferencias</strong>.
                        </div>
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {almacenes.length > 1 ? (
                            <Select
                                label="Almacén destino"
                                required
                                value={almacenId}
                                onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                                options={almacenes.map(a => ({ value: a.id, label: a.nombre }))}
                                error={errors.almacen_id}
                            />
                        ) : almacenes.length === 1 ? (
                            <div>
                                <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Almacén destino</label>
                                <div className="rounded-xl border px-3 py-2 text-sm" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                    {almacenes[0].nombre}
                                </div>
                            </div>
                        ) : null}
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
                        <Select
                            label="Proveedor"
                            placeholder="Sin proveedor"
                            value={proveedorId}
                            onChange={v => setProveedorId(v === '' ? '' : Number(v))}
                            options={proveedores.map(p => ({
                                value: p.id,
                                label: `${p.razon_social ?? p.nombre_comercial ?? '—'}${p.numero_documento ? ` · ${p.tipo_documento} ${p.numero_documento}` : ''}`,
                            }))}
                            error={errors.proveedor_id}
                        />
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
                        <div className="col-span-2">Factura</div>
                        <div className="col-span-1 text-right">Subtotal</div>
                    </div>

                    {detalles.map((d, i) => {
                        const unidades = unidadesDeProducto(d.producto_id);
                        return (
                            <div key={i} className="rounded-xl p-3 space-y-3 md:space-y-2"
                                style={{ backgroundColor: 'var(--color-bg)' }}>
                                {/* Header de item solo en mobile: numero + subtotal + remove arriba */}
                                <div className="flex items-center justify-between md:hidden">
                                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                        Producto #{i + 1}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <span className="text-base font-mono font-semibold" style={{ color: 'var(--color-text)' }}>
                                            S/ {subtotal(d).toFixed(2)}
                                        </span>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)}
                                                className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={15} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Layout: stack vertical en mobile, grid 12-col en md+. */}
                                {/* md:contents en pairs colapsa el wrapper en md+ para que sus hijos sean grid children. */}
                                <div className="flex flex-col gap-3 md:grid md:grid-cols-12 md:gap-2 md:items-end md:space-y-0">
                                    {/* Producto */}
                                    <div className="md:col-span-3">
                                        <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Producto</label>
                                        <Select
                                            placeholder="Buscar producto..."
                                            value={d.producto_id}
                                            onChange={v => setDetalle(i, 'producto_id', Number(v))}
                                            options={productos.map(p => ({ value: p.id, label: p.codigo ? `[${p.codigo}] ${p.nombre}` : p.nombre }))}
                                            error={(errors as Record<string, string>)[`detalles.${i}.producto_id`]}
                                        />
                                    </div>

                                    {/* Pair: Unidad + Cantidad — 2-up en mobile */}
                                    <div className="grid grid-cols-2 gap-2 md:contents">
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Unidad</label>
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
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Cantidad</label>
                                            <Input
                                                placeholder="0"
                                                type="number" min="0" step="any" inputMode="decimal"
                                                value={d.cantidad}
                                                onChange={e => setDetalle(i, 'cantidad', e.target.value)}
                                                error={(errors as Record<string, string>)[`detalles.${i}.cantidad`]}
                                            />
                                        </div>
                                    </div>

                                    {/* Pair: Precio costo + Factura — 2-up en mobile */}
                                    <div className="grid grid-cols-2 gap-2 md:contents">
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Precio costo</label>
                                            <Input
                                                placeholder="0.00"
                                                type="number" min="0" step="0.0001" inputMode="decimal"
                                                value={d.precio_costo}
                                                onChange={e => setDetalle(i, 'precio_costo', e.target.value)}
                                                error={(errors as Record<string, string>)[`detalles.${i}.precio_costo`]}
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Factura</label>
                                            <Input
                                                placeholder={nroDoc ? nroDoc : 'F001-...'}
                                                value={d.numero_documento}
                                                onChange={e => setDetalle(i, 'numero_documento', e.target.value)}
                                                error={(errors as Record<string, string>)[`detalles.${i}.numero_documento`]}
                                            />
                                        </div>
                                    </div>

                                    {/* Subtotal + remove — solo md+. En mobile ya está arriba. */}
                                    <div className="hidden md:flex md:col-span-1 md:text-right md:items-end md:justify-end md:gap-1">
                                        <p className="text-sm font-mono font-semibold pb-2" style={{ color: 'var(--color-text)' }}>
                                            S/ {subtotal(d).toFixed(2)}
                                        </p>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)}
                                                className="mb-2 rounded p-0.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Meta-fila: factor + cant. base + hint herencia factura */}
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs px-1" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="font-mono">×{d.factor_conversion}</span>
                                    <span className="font-mono">= {cantidadBase(d).toFixed(4)} base</span>
                                    {!d.numero_documento.trim() && nroDoc && (
                                        <span className="italic">Hereda factura {nroDoc}</span>
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
