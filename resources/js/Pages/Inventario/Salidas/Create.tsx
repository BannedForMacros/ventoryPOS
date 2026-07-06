import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Trash2, Package, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import type { PageProps } from '@/types';
import { hoyLocal } from '@/lib/fechas';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; local?: { nombre: string } | null; }
interface TipoSalida { id: number; nombre: string; slug: string; }
interface StockRow { almacen_id: number; producto_id: number; cantidad: string; }

interface Props extends PageProps {
    almacenes: Almacen[];
    tipos: TipoSalida[];
    productos: Producto[];
    stocks: StockRow[];
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

export default function SalidaCreate({ almacenes, tipos, productos, stocks, mostrarSelector }: Props) {
    const [almacenId, setAlmacenId]     = useState<number | ''>(almacenes.length === 1 ? almacenes[0].id : '');
    const [tipoId, setTipoId]           = useState<number | ''>(tipos[0]?.id ?? '');
    const [nroDoc, setNroDoc]           = useState('');
    const [fecha, setFecha]             = useState(hoyLocal());
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

    // Map (almacen-producto) → cantidad disponible en base. Lookup O(1) por linea.
    const stockMap = useMemo(() => {
        const m = new Map<string, number>();
        stocks.forEach(s => m.set(`${s.almacen_id}-${s.producto_id}`, parseFloat(s.cantidad)));
        return m;
    }, [stocks]);

    function stockDisponible(productoId: number | ''): number | null {
        if (!almacenId || !productoId) return null;
        return stockMap.get(`${almacenId}-${productoId}`) ?? 0;
    }

    // True si la cantidad solicitada (base) supera el stock disponible.
    function excedeStock(d: DetalleRow): boolean {
        if (!d.producto_id) return false;
        const disp = stockDisponible(d.producto_id);
        if (disp === null) return false;
        return cantidadBase(d) > disp + 0.0001; // tolerancia float
    }

    function submit(confirmar: boolean) {
        // Solo bloqueamos al confirmar (que mueve stock). Para borrador permitimos
        // guardar aunque exceda — el user puede estar planeando una salida futura
        // que se va a cubrir con una entrada posterior.
        if (confirmar) {
            const lineasExcedidas = detalles
                .map((d, i) => ({ d, i }))
                .filter(({ d }) => excedeStock(d));
            if (lineasExcedidas.length > 0) {
                const productosFalta = lineasExcedidas.map(({ d, i }) => {
                    const p = productos.find(p => p.id === d.producto_id);
                    return `Línea ${i + 1}${p ? ` (${p.nombre})` : ''}`;
                }).join(', ');
                toast.error(`No se puede confirmar: stock insuficiente en ${productosFalta}.`, { duration: 5000 });
                return;
            }
        }
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

            <div className="max-w-5xl mx-auto space-y-8">

                {/* ── Cabecera ── */}
                <section className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Datos de la salida
                    </h2>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

                {/* ── Detalle ── */}
                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                Productos a sacar
                            </h2>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                El costo unitario se captura al confirmar (costo promedio actual del producto).
                            </p>
                        </div>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar línea
                        </Button>
                    </div>

                    {errors.detalles && (
                        <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{errors.detalles}</p>
                    )}

                    {/* Header strip — solo desktop. En mobile cada line tiene labels propias. */}
                    <div className="hidden md:grid grid-cols-12 gap-2 text-xs font-semibold uppercase tracking-wide px-1"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <div className="col-span-4">Producto</div>
                        <div className="col-span-2">Unidad</div>
                        <div className="col-span-2">Cantidad</div>
                        <div className="col-span-3">Observación</div>
                        <div className="col-span-1 text-right">Cant. base</div>
                    </div>

                    {detalles.map((d, i) => {
                        const unidades = unidadesDeProducto(d.producto_id);
                        return (
                            <div key={i} className="rounded-xl p-3 space-y-3 md:space-y-2"
                                style={{ backgroundColor: 'var(--color-bg)' }}>
                                {/* Header mobile: numero + cant. base + remove. En desktop oculto. */}
                                <div className="flex items-center justify-between md:hidden">
                                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                        Producto #{i + 1}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                            {cantidadBase(d).toFixed(4)} base
                                        </span>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)}
                                                className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={15} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3 md:grid md:grid-cols-12 md:gap-2 md:items-end md:space-y-0">
                                    {/* Producto — full width mobile, col-span-4 desktop */}
                                    <div className="md:col-span-4">
                                        <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Producto</label>
                                        <SearchableSelect
                                            placeholder="Buscar producto..."
                                            searchPlaceholder="Buscar por nombre o código..."
                                            emptyMessage="No hay productos que coincidan"
                                            value={d.producto_id}
                                            onChange={v => setDetalle(i, 'producto_id', v === '' ? '' : Number(v))}
                                            options={productos.map(p => ({
                                                value: p.id,
                                                label: `${p.codigo ? `[${p.codigo}] ` : ''}${p.nombre}`,
                                            }))}
                                            error={(errors as Record<string, string>)[`detalles.${i}.producto_id`]}
                                        />
                                    </div>

                                    {/* Pair: Unidad + Cantidad — 2-up en mobile */}
                                    <div className="grid grid-cols-2 gap-2 md:contents">
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Unidad</label>
                                            <Select
                                                placeholder="—"
                                                value={d.unidad_medida_id}
                                                onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                                options={unidades.map(u => ({
                                                    value: u.unidad_medida_id,
                                                    label: u.unidad_medida ? `${u.unidad_medida.abreviatura}${u.es_base ? ' (base)' : ''}` : String(u.unidad_medida_id),
                                                }))}
                                                disabled={!d.producto_id}
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

                                    {/* Observación */}
                                    <div className="md:col-span-3">
                                        <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Observación</label>
                                        <Input
                                            placeholder="Opcional"
                                            value={d.observacion}
                                            onChange={e => setDetalle(i, 'observacion', e.target.value)}
                                        />
                                    </div>

                                    {/* Cant. base + remove — solo desktop. En mobile ya esta arriba. */}
                                    <div className="hidden md:flex md:col-span-1 md:text-right md:items-end md:justify-end md:gap-1">
                                        <p className="text-xs font-mono pb-2 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                            {cantidadBase(d).toFixed(4)}
                                        </p>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)}
                                                className="mb-2 rounded p-0.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Meta: factor + stock disponible. Si la cantidad excede el
                                    stock, se pinta en rojo + icono — el usuario ve al toque
                                    que no puede sacar tanto. */}
                                {(() => {
                                    const disp = stockDisponible(d.producto_id);
                                    const excede = excedeStock(d);
                                    return (
                                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs px-1"
                                            style={{ color: 'var(--color-text-muted)' }}>
                                            <span className="font-mono">×{d.factor_conversion}</span>
                                            {!almacenId && d.producto_id && (
                                                <span className="italic">Selecciona un almacén para ver stock</span>
                                            )}
                                            {disp !== null && (
                                                excede ? (
                                                    <span className="flex items-center gap-1 font-medium" style={{ color: 'var(--color-danger)' }}>
                                                        <AlertTriangle size={11} />
                                                        Excede stock: disponible {disp.toFixed(2)} base, solicitas {cantidadBase(d).toFixed(2)}
                                                    </span>
                                                ) : disp <= 0 ? (
                                                    <span className="flex items-center gap-1 font-medium" style={{ color: 'var(--color-danger)' }}>
                                                        <AlertTriangle size={11} />Sin stock
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1" style={{ color: '#16a34a' }}>
                                                        <Package size={11} />
                                                        Disponible: {disp.toFixed(2)} (base)
                                                    </span>
                                                )
                                            )}
                                        </div>
                                    );
                                })()}
                            </div>
                        );
                    })}
                </section>

                {/* ── Acciones ── */}
                <div className="flex flex-col sm:flex-row gap-3">
                    <Button variant="ghost" onClick={() => router.visit(route('inventario.salidas.index'))}>
                        Cancelar
                    </Button>
                    <Button variant="secondary" loading={processing} onClick={() => submit(false)}>
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
