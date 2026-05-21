import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Trash2, AlertCircle, AlertTriangle, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Switch from '@/Components/UI/Switch';
import type { PageProps } from '@/types';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; }

interface EntradaDetalleData {
    id?: number;
    producto_id: number;
    unidad_medida_id: number;
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    numero_documento: string | null;
    producto?: Producto;
    unidad_medida?: UnidadMedida;
}

interface Proveedor { id: number; razon_social: string | null; nombre_comercial: string | null; numero_documento: string | null; tipo_documento: string; }
interface CuentaMP { id: number; nombre: string; banco: string | null; numero_cuenta: string | null; }
interface MetodoPagoForm { id: number; nombre: string; cuentas: CuentaMP[]; }
interface StockRow { almacen_id: number; producto_id: number; cantidad: string; }

interface EntradaData {
    id: number;
    almacen_id: number;
    proveedor_id: number | null;
    proveedor: string | null;
    numero_documento: string | null;
    tipo: string;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    observacion: string | null;
    estado_pago: 'pendiente' | 'pagado';
    metodo_pago_id: number | null;
    cuenta_id: number | null;
    detalles: EntradaDetalleData[];
}

interface Props extends PageProps {
    entrada: EntradaData;
    almacenes: Almacen[];
    productos: Producto[];
    proveedores: Proveedor[];
    metodosPago: MetodoPagoForm[];
    stocks: StockRow[];
    mostrarSelector: boolean;
    modoAlmacen: 'simple' | 'central_y_local';
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    numero_documento: string;
}

export default function EntradaEdit({ entrada, almacenes, productos, proveedores, metodosPago, stocks, mostrarSelector, modoAlmacen }: Props) {
    const [almacenId, setAlmacenId]     = useState<number | ''>(entrada.almacen_id);
    const [proveedorId, setProveedorId] = useState<number | ''>(entrada.proveedor_id ?? '');
    const [nroDoc, setNroDoc]           = useState(entrada.numero_documento ?? '');
    const [tipo, setTipo]               = useState(entrada.tipo);
    // Misma trampa de zona horaria que en Index.fmtFecha: el backend manda ISO UTC.
    // El <input type="date"> espera "YYYY-MM-DD" — extraemos solo la porción de fecha
    // para evitar que se corra al día anterior en zonas con offset negativo.
    const [fecha, setFecha]             = useState(entrada.fecha?.slice(0, 10) ?? '');
    const [observacion, setObservacion] = useState(entrada.observacion ?? '');
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [processing, setProcessing]   = useState(false);
    // Modo inicial: si la entrada original tiene algún item con factura propia,
    // arrancamos en modo "por item" para no perder esos datos al renderizar.
    const [facturaPorItem, setFacturaPorItem] = useState(
        entrada.detalles.some(d => !!d.numero_documento)
    );

    const [pagado, setPagado]               = useState(entrada.estado_pago === 'pagado');
    const [metodoPagoId, setMetodoPagoId]   = useState<number | ''>(entrada.metodo_pago_id ?? '');
    const [cuentaId, setCuentaId]           = useState<number | ''>(entrada.cuenta_id ?? '');

    const metodoSeleccionado = metodosPago.find(m => m.id === metodoPagoId) ?? null;
    const cuentasDelMetodo   = metodoSeleccionado?.cuentas ?? [];

    const [detalles, setDetalles] = useState<DetalleRow[]>(
        entrada.detalles.map(d => ({
            producto_id:       d.producto_id,
            unidad_medida_id:  d.unidad_medida_id,
            cantidad:          String(d.cantidad),
            factor_conversion: String(d.factor_conversion),
            precio_costo:      String(d.precio_costo),
            numero_documento:  d.numero_documento ?? '',
        }))
    );

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

    function addDetalle()    { setDetalles(d => [...d, { producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', precio_costo: '', numero_documento: '' }]); }
    function removeDetalle(i: number) { setDetalles(d => d.filter((_, idx) => idx !== i)); }

    function subtotal(d: DetalleRow): number {
        return Math.round((parseFloat(d.cantidad) || 0) * (parseFloat(d.precio_costo) || 0) * 100) / 100;
    }

    function cantidadBase(d: DetalleRow): number {
        return Math.round((parseFloat(d.cantidad) || 0) * (parseFloat(d.factor_conversion) || 1) * 10000) / 10000;
    }

    const total = detalles.reduce((sum, d) => sum + subtotal(d), 0);

    /**
     * Restricciones: si la entrada estaba confirmada, calculamos para cada producto
     * cuanto puede reducirse sin dejar stock negativo. Si la entrada original aporto
     * 10 unidades base y el stock actual es 3, significa que ya se consumieron 7 ⇒
     * el minimo total permitido es 7 (no se puede bajar de ahi).
     *
     * Devuelve: Map producto_id → { nombre, minimoBase, consumidoBase }
     */
    const restriccionesPorProducto = useMemo(() => {
        const m = new Map<number, { nombre: string; minimoBase: number; consumidoBase: number; aportoOriginal: number }>();
        if (entrada.estado !== 'confirmado') return m;

        // Stock actual indexado por producto en el almacen de la entrada
        const stockMap = new Map<number, number>();
        stocks.forEach(s => stockMap.set(s.producto_id, parseFloat(s.cantidad)));

        // Agrupar detalles originales por producto_id (puede haber varias lineas del mismo)
        const originalPorProducto = new Map<number, number>();
        entrada.detalles.forEach(d => {
            const cantBase = Number(d.cantidad) * Number(d.factor_conversion);
            originalPorProducto.set(d.producto_id, (originalPorProducto.get(d.producto_id) ?? 0) + cantBase);
        });

        originalPorProducto.forEach((aporto, prodId) => {
            const stockAct = stockMap.get(prodId) ?? 0;
            const consumido = Math.max(0, aporto - stockAct);
            if (consumido > 0) {
                const prod = productos.find(p => p.id === prodId);
                m.set(prodId, {
                    nombre:        prod?.nombre ?? `Producto #${prodId}`,
                    minimoBase:    consumido,        // total mínimo permitido en cualquier estado
                    consumidoBase: consumido,
                    aportoOriginal: aporto,
                });
            }
        });

        return m;
    }, [entrada, stocks, productos]);

    /** Suma de cantidad_base por producto en el ESTADO ACTUAL del form (no el original). */
    const sumaActualPorProducto = useMemo(() => {
        const m = new Map<number, number>();
        detalles.forEach(d => {
            if (typeof d.producto_id !== 'number') return;
            m.set(d.producto_id, (m.get(d.producto_id) ?? 0) + cantidadBase(d));
        });
        return m;
    }, [detalles]);

    /** Lista de productos cuya suma actual no cumple el mínimo. Vacio = todo OK. */
    const violaciones = useMemo(() => {
        const out: Array<{ nombre: string; minimo: number; actual: number }> = [];
        restriccionesPorProducto.forEach((r, prodId) => {
            const actual = sumaActualPorProducto.get(prodId) ?? 0;
            if (actual + 0.0001 < r.minimoBase) {
                out.push({ nombre: r.nombre, minimo: r.minimoBase, actual });
            }
        });
        return out;
    }, [restriccionesPorProducto, sumaActualPorProducto]);

    function validar(): string[] {
        const errs: string[] = [];
        if (!almacenId) errs.push('Selecciona el almacén destino');
        if (!tipo)      errs.push('Selecciona el tipo de entrada');
        if (!fecha)     errs.push('Indica la fecha');

        if (detalles.length === 0) {
            errs.push('Agrega al menos un producto al detalle');
        } else {
            detalles.forEach((d, idx) => {
                const n = idx + 1;
                if (!d.producto_id)       errs.push(`Producto #${n}: falta seleccionar el producto`);
                if (!d.unidad_medida_id)  errs.push(`Producto #${n}: falta seleccionar la unidad`);
                const qty = parseFloat(d.cantidad);
                if (!d.cantidad || isNaN(qty) || qty <= 0) {
                    errs.push(`Producto #${n}: la cantidad debe ser mayor a 0`);
                }
                const cost = parseFloat(d.precio_costo);
                if (d.precio_costo === '' || isNaN(cost) || cost < 0) {
                    errs.push(`Producto #${n}: precio de costo inválido`);
                }
                // Factura/comprobante siempre OPCIONAL — no bloqueamos.
            });
        }
        return errs;
    }

    function mostrarErroresValidacion(errs: string[]) {
        toast.error(
            () => (
                <div className="flex flex-col gap-1.5 max-w-xs">
                    <div className="flex items-center gap-2 font-semibold text-sm">
                        <AlertCircle size={15} />
                        <span>Faltan datos para guardar</span>
                    </div>
                    <ul className="text-xs space-y-0.5 list-disc list-inside opacity-95">
                        {errs.slice(0, 5).map((e, i) => <li key={i}>{e}</li>)}
                        {errs.length > 5 && (
                            <li className="opacity-70 list-none">…y {errs.length - 5} más</li>
                        )}
                    </ul>
                </div>
            ),
            { duration: 5500 }
        );
    }

    function submit() {
        const errs = validar();
        if (errs.length > 0) {
            mostrarErroresValidacion(errs);
            return;
        }
        // Bloqueo explicito si hay violaciones de stock — backend tambien valida,
        // pero atajamos aca para evitar el roundtrip y dar mensaje accionable.
        if (violaciones.length > 0) {
            mostrarErroresValidacion(violaciones.map(v =>
                `${v.nombre}: tienes ${v.actual.toFixed(2)} base, debes mantener al menos ${v.minimo.toFixed(2)} base`
            ));
            return;
        }
        setProcessing(true);
        router.put(route('inventario.entradas.update', entrada.id), {
            almacen_id: almacenId, proveedor_id: proveedorId || null,
            numero_documento: facturaPorItem ? null : (nroDoc || null),
            tipo, fecha, observacion,
            estado_pago:    pagado ? 'pagado' : 'pendiente',
            metodo_pago_id: pagado ? (metodoPagoId || null) : null,
            cuenta_id:      pagado ? (cuentaId || null) : null,
            detalles: detalles.map(d => ({
                producto_id: d.producto_id, unidad_medida_id: d.unidad_medida_id,
                cantidad: d.cantidad, factor_conversion: d.factor_conversion, precio_costo: d.precio_costo,
                numero_documento: facturaPorItem ? (d.numero_documento.trim() || null) : null,
            })),
        }, {
            onSuccess: () => setProcessing(false),
            onError:   (e) => {
                setErrors(e);
                setProcessing(false);
                const first = Object.values(e)[0];
                toast.error(typeof first === 'string' ? first : 'Revisa los campos marcados.');
            },
        });
    }

    return (
        <AppLayout title="Editar entrada">
            <PageHeader
                title="Editar entrada"
                subtitle={entrada.estado === 'confirmado' ? 'Entrada confirmada — cambios recalcularán stock y costo promedio' : 'Entrada en borrador — los cambios no afectan stock'}
                backHref={route('inventario.entradas.index')}
            />

            {entrada.estado === 'confirmado' && (
                <div className="max-w-5xl mx-auto mb-5 rounded-2xl border p-4 flex items-start gap-3"
                    style={{
                        borderColor: 'color-mix(in srgb, var(--color-warning) 40%, var(--color-border))',
                        backgroundColor: 'color-mix(in srgb, var(--color-warning) 8%, transparent)',
                    }}>
                    <AlertCircle size={18} className="flex-shrink-0 mt-0.5" style={{ color: 'var(--color-warning)' }} />
                    <div className="text-sm" style={{ color: 'var(--color-text)' }}>
                        <p className="font-semibold mb-1">Esta entrada ya fue confirmada.</p>
                        <p style={{ color: 'var(--color-text-muted)' }}>
                            Al guardar: el stock se ajustará automáticamente con la diferencia y el <strong>costo promedio se recalculará</strong>.
                            Si reduces la cantidad de un producto que ya tiene ventas o transferencias posteriores, el sistema bloqueará el cambio
                            con un mensaje indicando la cantidad mínima permitida.
                        </p>
                    </div>
                </div>
            )}

            <div className="max-w-5xl mx-auto space-y-8">
                <section className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
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
                            <Select label="Almacén destino" required value={almacenId}
                                onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                                options={almacenes.map(a => ({ value: a.id, label: a.nombre }))}
                                error={errors.almacen_id} />
                        ) : almacenes.length === 1 ? (
                            <div>
                                <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Almacén destino</label>
                                <div className="rounded-xl border px-3 py-2 text-sm" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                    {almacenes[0].nombre}
                                </div>
                            </div>
                        ) : null}
                        <Select label="Tipo" required value={tipo} onChange={v => setTipo(String(v))}
                            options={[
                                { value: 'compra', label: 'Compra' }, { value: 'ajuste', label: 'Ajuste' },
                                { value: 'devolucion', label: 'Devolución' }, { value: 'otro', label: 'Otro' },
                            ]} />
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
                        {!facturaPorItem && (
                            <Input label="Nro. documento" value={nroDoc} onChange={e => setNroDoc(e.target.value)} />
                        )}
                        <Input label="Fecha" required type="date" value={fecha} onChange={e => setFecha(e.target.value)} />
                    </div>

                    <Switch
                        label="Cada producto tiene su propia factura"
                        description="Útil cuando el proveedor entregó la mercadería con varias facturas distintas. Si está apagado, todos los productos comparten el número de la cabecera."
                        checked={facturaPorItem}
                        onChange={setFacturaPorItem}
                    />

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
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Productos</h2>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar producto
                        </Button>
                    </div>

                    {/* Banner de restricciones de stock (solo si la entrada original era confirmada
                        y algunos productos ya tuvieron movimientos posteriores). Educa al usuario
                        sobre por que no puede reducir esos productos por debajo del minimo. */}
                    {restriccionesPorProducto.size > 0 && (
                        <div className="rounded-xl border p-3 space-y-2"
                            style={{
                                borderColor: 'color-mix(in srgb, var(--color-warning) 40%, var(--color-border))',
                                backgroundColor: 'color-mix(in srgb, var(--color-warning) 6%, transparent)',
                            }}>
                            <div className="flex items-center gap-2 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                <AlertTriangle size={15} style={{ color: 'var(--color-warning)' }} />
                                Productos con movimientos posteriores
                            </div>
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Estos productos ya tuvieron salidas/ventas después de confirmar esta entrada. Puedes redistribuir entre líneas, pero la <strong>suma total por producto</strong> debe ser ≥ al mínimo indicado. Si necesitas quitar uno, agrega otra línea del mismo producto con la cantidad restante.
                            </p>
                            <ul className="text-xs space-y-1 mt-1">
                                {[...restriccionesPorProducto.entries()].map(([prodId, r]) => {
                                    const actual = sumaActualPorProducto.get(prodId) ?? 0;
                                    const cumple = actual + 0.0001 >= r.minimoBase;
                                    return (
                                        <li key={prodId} className="flex items-center justify-between gap-2 px-2 py-1 rounded"
                                            style={{ backgroundColor: cumple ? 'transparent' : 'color-mix(in srgb, var(--color-danger) 10%, transparent)' }}>
                                            <span style={{ color: 'var(--color-text)' }}>{r.nombre}</span>
                                            <span className="font-mono" style={{ color: cumple ? '#16a34a' : 'var(--color-danger)' }}>
                                                {actual.toFixed(2)} / mín. {r.minimoBase.toFixed(2)} base
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <div className="hidden md:grid grid-cols-12 gap-2 text-xs font-semibold uppercase tracking-wide px-1" style={{ color: 'var(--color-text-muted)' }}>
                        <div className={facturaPorItem ? 'col-span-3' : 'col-span-5'}>Producto</div>
                        <div className="col-span-2">Unidad</div>
                        <div className="col-span-2">Cantidad</div>
                        <div className="col-span-2">Precio costo</div>
                        {facturaPorItem && <div className="col-span-2">Factura</div>}
                        <div className="col-span-1 text-right">Subtotal</div>
                    </div>

                    {detalles.map((d, i) => {
                        const unidades = unidadesDeProducto(d.producto_id);
                        return (
                            <div key={i} className="rounded-xl p-3 space-y-3 md:space-y-2" style={{ backgroundColor: 'var(--color-bg)' }}>
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

                                <div className="flex flex-col gap-3 md:grid md:grid-cols-12 md:gap-2 md:items-end md:space-y-0">
                                    <div className={facturaPorItem ? 'md:col-span-3' : 'md:col-span-5'}>
                                        <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Producto</label>
                                        <SearchableSelect
                                            placeholder="Buscar producto..."
                                            searchPlaceholder="Buscar por nombre o código..."
                                            emptyMessage="No hay productos que coincidan"
                                            value={d.producto_id}
                                            onChange={v => setDetalle(i, 'producto_id', Number(v))}
                                            options={productos.map(p => ({ value: p.id, label: p.codigo ? `[${p.codigo}] ${p.nombre}` : p.nombre }))}
                                            error={(errors)[`detalles.${i}.producto_id`]} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 md:contents">
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Unidad</label>
                                            <Select placeholder="Unidad" value={d.unidad_medida_id}
                                                onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                                options={unidades.map(u => ({
                                                    value: u.unidad_medida_id,
                                                    label: u.unidad_medida ? `${u.unidad_medida.abreviatura}${u.es_base ? ' (base)' : ''}` : String(u.unidad_medida_id),
                                                }))} />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Cantidad</label>
                                            <Input placeholder="0" type="number" min="0" step="any" inputMode="decimal"
                                                value={d.cantidad} onChange={e => setDetalle(i, 'cantidad', e.target.value)} />
                                        </div>
                                    </div>

                                    <div className={`grid ${facturaPorItem ? 'grid-cols-2' : 'grid-cols-1'} gap-2 md:contents`}>
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Precio costo</label>
                                            <Input placeholder="0.00" type="number" min="0" step="0.0001" inputMode="decimal"
                                                value={d.precio_costo} onChange={e => setDetalle(i, 'precio_costo', e.target.value)} />
                                        </div>
                                        {facturaPorItem && (
                                            <div className="md:col-span-2">
                                                <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Factura</label>
                                                <Input placeholder="F001-..."
                                                    value={d.numero_documento}
                                                    onChange={e => setDetalle(i, 'numero_documento', e.target.value)}
                                                    error={(errors)[`detalles.${i}.numero_documento`]} />
                                            </div>
                                        )}
                                    </div>

                                    <div className="hidden md:flex md:col-span-1 md:text-right md:items-end md:justify-end md:gap-1">
                                        <p className="text-sm font-mono font-semibold pb-2" style={{ color: 'var(--color-text)' }}>S/ {subtotal(d).toFixed(2)}</p>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)} className="mb-2 rounded p-0.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs px-1" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="font-mono">×{d.factor_conversion}</span>
                                    <span className="font-mono">= {cantidadBase(d).toFixed(4)} base</span>
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex justify-end pt-2 border-t" style={{ borderColor: 'var(--color-border)' }}>
                        <div className="text-right">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Total</p>
                            <p className="text-xl font-bold font-mono" style={{ color: 'var(--color-text)' }}>S/ {total.toFixed(2)}</p>
                        </div>
                    </div>
                </section>

                {/* ── Pago ── */}
                <section
                    className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                >
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Wallet size={16} style={{ color: 'var(--color-text-muted)' }} />
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                Pago al proveedor
                            </h2>
                        </div>
                        <Switch
                            label={pagado ? 'Pagado' : 'Pendiente'}
                            checked={pagado}
                            onChange={v => {
                                setPagado(v);
                                if (!v) { setMetodoPagoId(''); setCuentaId(''); }
                            }}
                        />
                    </div>

                    {pagado ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Select
                                label="Método de pago"
                                required
                                placeholder="Seleccionar método"
                                value={metodoPagoId}
                                onChange={v => {
                                    const newId = v === '' ? '' : Number(v);
                                    setMetodoPagoId(newId);
                                    setCuentaId('');
                                }}
                                options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                                error={errors.metodo_pago_id}
                            />
                            {cuentasDelMetodo.length > 0 && (
                                <Select
                                    label="Cuenta"
                                    placeholder="(Opcional) elegir cuenta"
                                    value={cuentaId}
                                    onChange={v => setCuentaId(v === '' ? '' : Number(v))}
                                    options={cuentasDelMetodo.map(c => ({
                                        value: c.id,
                                        label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                                    }))}
                                    error={errors.cuenta_id}
                                    hint="Útil si tienes varias cuentas del mismo método."
                                />
                            )}
                        </div>
                    ) : (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            La entrada queda como deuda al proveedor. Puedes marcarla como pagada en cualquier momento.
                        </p>
                    )}
                </section>

                <div className="flex gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('inventario.entradas.index'))}>Cancelar</Button>
                    <Button type="button" loading={processing} onClick={submit}>Guardar cambios</Button>
                </div>
            </div>
        </AppLayout>
    );
}
