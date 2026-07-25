import { useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Trash2, AlertCircle, CheckCircle, Wallet, UserPlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Switch from '@/Components/UI/Switch';
import Tabs from '@/Components/UI/Tabs';
import Modal from '@/Components/UI/Modal';
import ModalCrearProveedor, { ProveedorLite } from './Partials/ModalCrearProveedor';
import AfectaCajaSelect from '@/Components/AfectaCajaSelect';
import type { PageProps } from '@/types';
import { hoyLocal } from '@/lib/fechas';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; }
interface Proveedor { id: number; razon_social: string | null; nombre_comercial: string | null; numero_documento: string | null; tipo_documento: string; }
interface CuentaMP { id: number; nombre: string; banco: string | null; numero_cuenta: string | null; }
interface MetodoPagoForm { id: number; nombre: string; cuentas: CuentaMP[]; }

interface TurnoLite {
    id: number; user_id: number; caja_id: number; fecha_apertura: string;
    estado: 'abierto' | 'cerrado';
    user?: { id: number; name: string } | null;
    caja?: { id: number; nombre: string } | null;
}

interface Props extends PageProps {
    almacenes: Almacen[];
    productos: Producto[];
    proveedores: Proveedor[];
    metodosPago: MetodoPagoForm[];
    turnos: TurnoLite[];
    turnoActivoId: number | null;
    mostrarSelector: boolean;
    modoAlmacen: 'simple' | 'central_y_local';
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    // Cómo ingresa el usuario el precio de esta línea:
    //  'unitario' → teclea el precio por unidad (precio_costo directo).
    //  'total'    → teclea lo que pagó por TODA la línea (precio_total) y el
    //               sistema calcula el unitario hacia atrás. Útil cuando el
    //               unitario × cantidad no cuadra con el monto real pagado.
    // Sea cual sea el modo, precio_costo SIEMPRE queda sincronizado: es lo que
    // se envía al backend y alimenta subtotal, costo promedio y kardex.
    precio_modo: 'unitario' | 'total';
    precio_total: string;
    // Vacío = hereda numero_documento de la cabecera. Si el proveedor facturó
    // la mercadería en varias facturas, cada item puede tener la suya propia.
    numero_documento: string;
}

const emptyDetalle = (): DetalleRow => ({
    producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1',
    precio_costo: '', precio_modo: 'unitario', precio_total: '', numero_documento: '',
});

/**
 * Deriva el precio unitario a partir del total de la línea y la cantidad.
 * Devuelve '' si aún no hay datos suficientes (cantidad 0/ausente). Redondea a
 * 4 decimales, que es la precisión de precio_costo en la BD (numeric 12,4).
 */
function costoDesdeTotal(totalStr: string, cantidadStr: string): string {
    const t = parseFloat(totalStr);
    const q = parseFloat(cantidadStr);
    if (!isFinite(t) || !isFinite(q) || q <= 0) return '';
    return String(Math.round((t / q) * 10000) / 10000);
}

export default function EntradaCreate({ almacenes, productos, proveedores, metodosPago, turnos, turnoActivoId, mostrarSelector, modoAlmacen }: Props) {
    // "Afecta caja a:" — por defecto NO afecta ninguna caja ('' = Sin turno). El
    // pago solo descuenta de una caja si el usuario elige explícitamente su turno.
    // Así una entrada registrada por la cajera no descuadra su caja sin querer.
    // (turnoActivoId queda disponible como atajo "usar mi caja", pero no es default.)
    const [turnoIdCaja, setTurnoIdCaja] = useState<number | ''>('');
    void turnoActivoId;
    const turnoLabel = (t: TurnoLite) => {
        const f = new Date(t.fecha_apertura).toLocaleDateString('es-PE');
        const hora = new Date(t.fecha_apertura).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        return [`#${t.id}`, `${f} ${hora}`, t.user?.name, t.caja?.nombre].filter(Boolean).join(' · ')
            + (t.estado === 'abierto' ? ' · abierto' : '');
    };
    const [almacenId, setAlmacenId]     = useState<number | ''>(almacenes.length === 1 ? almacenes[0].id : '');
    // Lista local de proveedores (para poder agregar uno nuevo sin recargar la página).
    const [listaProveedores, setListaProveedores] = useState<Proveedor[]>(proveedores);
    const [modalProveedor, setModalProveedor]     = useState(false);
    const [proveedorId, setProveedorId] = useState<number | ''>('');
    const [nroDoc, setNroDoc]           = useState('');
    const [tipo, setTipo]               = useState<string>('compra');
    const [fecha, setFecha]             = useState(hoyLocal());
    const [observacion, setObservacion] = useState('');
    const [detalles, setDetalles]       = useState<DetalleRow[]>([emptyDetalle()]);
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [processing, setProcessing]   = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    // OFF (default) = una sola factura para toda la entrada (cabecera).
    // ON = cada producto tiene su propia factura (input por línea); cabecera oculta.
    const [facturaPorItem, setFacturaPorItem] = useState(false);

    // Pago: pendiente (todo queda como deuda), parcial (pago inicial + saldo
    // como CxP) o pagado (total). Parcial/pagado aceptan VARIAS líneas de pago
    // (método + cuenta + monto), igual que el POS. Independiente del estado
    // borrador/confirmado de la entrada.
    type EstadoPago = 'pendiente' | 'parcial' | 'pagado';
    interface LineaPago { key: string; metodo_pago_id: number | ''; cuenta_id: number | ''; monto: string; fecha: string; }
    const nuevaLinea = (monto = ''): LineaPago =>
        ({ key: Math.random().toString(36).slice(2), metodo_pago_id: '', cuenta_id: '', monto, fecha: hoyLocal() });

    const [estadoPago, setEstadoPago] = useState<EstadoPago>('pendiente');
    const [pagos, setPagos]           = useState<LineaPago[]>([]);

    const cuentasDeLinea = (l: LineaPago) =>
        metodosPago.find(m => m.id === l.metodo_pago_id)?.cuentas ?? [];
    const totalPagado = pagos.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);

    function cambiarEstadoPago(v: EstadoPago) {
        setEstadoPago(v);
        if (v === 'pendiente') setPagos([]);
        if (v === 'parcial' && pagos.length === 0) setPagos([nuevaLinea()]);
        if (v === 'pagado') setPagos(prev => prev.length === 0 ? [nuevaLinea(total.toFixed(2))] : prev);
    }

    function setPago(key: string, patch: Partial<LineaPago>) {
        setPagos(prev => prev.map(p => p.key === key ? { ...p, ...patch } : p));
    }

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
            // Si la línea ingresa por TOTAL, mantenemos precio_costo derivado
            // cada vez que cambia el total tecleado o la cantidad.
            if (updated[i].precio_modo === 'total' && (field === 'precio_total' || field === 'cantidad')) {
                updated[i].precio_costo = costoDesdeTotal(updated[i].precio_total, updated[i].cantidad);
            }
            return updated;
        });
    }

    /** Cambia entre ingresar por precio unitario o por total de la línea. */
    function setPrecioModo(i: number, modo: 'unitario' | 'total') {
        setDetalles(prev => prev.map((d, idx) => {
            if (idx !== i || d.precio_modo === modo) return d;
            if (modo === 'total') {
                // Al pasar a "total" precargamos el total con el subtotal actual
                // (cantidad × unitario) para no perder lo ya tecleado.
                const precio_total = subtotal(d) > 0 ? String(subtotal(d)) : '';
                return { ...d, precio_modo: 'total', precio_total,
                    precio_costo: costoDesdeTotal(precio_total, d.cantidad) || d.precio_costo };
            }
            // Volver a "unitario": precio_costo ya está sincronizado.
            return { ...d, precio_modo: 'unitario' };
        }));
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

    /**
     * Valida el formulario en cliente ANTES de enviar al backend. La idea es que
     * el usuario sepa exactamente qué le falta sin tener que scrollear y buscar
     * los inputs marcados en rojo. Devuelve lista de mensajes humanos; si está
     * vacía, todo OK.
     */
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
                // El número de factura/comprobante es OPCIONAL siempre. Si el
                // usuario activó el modo "por item" puede llenar las que tenga
                // a la mano y dejar el resto en blanco — no lo bloqueamos.
            });
        }

        // Pagos (parcial o pagado): cada línea con método y monto; la suma
        // debe cuadrar con el modo elegido.
        if (estadoPago !== 'pendiente') {
            if (pagos.length === 0) {
                errs.push('Agrega al menos una línea de pago (método y monto)');
            }
            pagos.forEach((p, idx) => {
                const n = idx + 1;
                if (!p.metodo_pago_id) errs.push(`Pago #${n}: falta el método de pago`);
                const m = parseFloat(p.monto);
                if (!p.monto || isNaN(m) || m <= 0) errs.push(`Pago #${n}: el monto debe ser mayor a 0`);
            });
            const suma = Math.round(totalPagado * 100) / 100;
            const tot  = Math.round(total * 100) / 100;
            if (estadoPago === 'pagado' && Math.abs(suma - tot) > 0.01) {
                errs.push(`En "Pagado" los pagos (S/ ${suma.toFixed(2)}) deben cubrir exactamente el total (S/ ${tot.toFixed(2)}); usa "Pago parcial" si es a cuenta`);
            }
            if (estadoPago === 'parcial') {
                if (suma >= tot - 0.01 && tot > 0) errs.push(`El pago parcial (S/ ${suma.toFixed(2)}) cubre el total: usa "Pagado"`);
                if (suma <= 0) errs.push('El pago parcial debe ser mayor a 0');
            }
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

    /**
     * Punto de entrada de los botones. Valida primero; si todo OK:
     * - borrador: envía directo
     * - confirmar: abre el modal de confirmación (acción irreversible que mueve stock)
     */
    function intentarGuardar(confirmar: boolean) {
        const errs = validar();
        if (errs.length > 0) {
            mostrarErroresValidacion(errs);
            return;
        }
        if (confirmar) {
            setShowConfirmModal(true);
        } else {
            enviar(false);
        }
    }

    function enviar(confirmar: boolean) {
        setShowConfirmModal(false);
        setProcessing(true);
        router.post(route('inventario.entradas.store'), {
            almacen_id:        almacenId,
            proveedor_id:      proveedorId || null,
            // En modo "factura por producto" la cabecera no tiene número (los items lo aportan).
            numero_documento:  facturaPorItem ? null : (nroDoc || null),
            tipo,
            fecha,
            observacion,
            confirmar,
            estado_pago:       estadoPago,
            pagos: estadoPago === 'pendiente' ? [] : pagos.map(p => ({
                metodo_pago_id: p.metodo_pago_id,
                cuenta_id:      p.cuenta_id || null,
                monto:          p.monto,
                fecha:          p.fecha,
            })),
            turno_id: estadoPago === 'pendiente' ? null : (turnoIdCaja || null),
            detalles: detalles.map(d => ({
                producto_id:       d.producto_id,
                unidad_medida_id:  d.unidad_medida_id,
                cantidad:          d.cantidad,
                factor_conversion: d.factor_conversion,
                precio_costo:      d.precio_costo,
                // En modo "factura única" el item siempre va null (hereda cabecera).
                numero_documento:  facturaPorItem ? (d.numero_documento.trim() || null) : null,
            })),
        }, {
            onSuccess: () => setProcessing(false),
            onError: (e) => {
                setErrors(e);
                setProcessing(false);
                // Backend rechazó algo que el client-validate no atrapó (ej: regla de
                // negocio del controller). Le avisamos al usuario con toast para que
                // no se quede mirando un form aparentemente exitoso.
                const first = Object.values(e)[0];
                toast.error(typeof first === 'string' ? first : 'Revisa los campos marcados.');
            },
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
                        <div>
                            <div className="flex items-end gap-2">
                                <div className="flex-1 min-w-0">
                                    <Select
                                        label="Proveedor"
                                        placeholder="Sin proveedor"
                                        value={proveedorId}
                                        onChange={v => setProveedorId(v === '' ? '' : Number(v))}
                                        options={listaProveedores.map(p => ({
                                            value: p.id,
                                            label: `${p.razon_social ?? p.nombre_comercial ?? '—'}${p.numero_documento ? ` · ${p.tipo_documento} ${p.numero_documento}` : ''}`,
                                        }))}
                                        error={errors.proveedor_id}
                                    />
                                </div>
                                <Button type="button" variant="secondary" onClick={() => setModalProveedor(true)}
                                    title="Crear nuevo proveedor">
                                    <UserPlus size={15} className="mr-1" /> Nuevo
                                </Button>
                            </div>
                        </div>
                        {/* Nro. documento solo aparece en modo "factura única". En modo "por item"
                            cada línea del detalle aporta su número y la cabecera queda sin uno. */}
                        {!facturaPorItem && (
                            <Input label="Nro. documento" value={nroDoc} onChange={e => setNroDoc(e.target.value)} placeholder="Ej: F001-0001234" />
                        )}
                        <Input label="Fecha" required type="date" value={fecha} onChange={e => setFecha(e.target.value)} error={errors.fecha} />
                    </div>

                    {/* Switch: modo factura. Decide dónde aparece el input de nro. documento. */}
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

                    {/* Cabecera tabla — la columna Factura solo aparece en modo "por item". */}
                    <div className="hidden md:grid grid-cols-12 gap-2 text-xs font-semibold uppercase tracking-wide px-1"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <div className={facturaPorItem ? 'col-span-3' : 'col-span-5'}>Producto</div>
                        <div className="col-span-2">Unidad</div>
                        <div className="col-span-2">Cantidad</div>
                        <div className="col-span-2">Precio</div>
                        {facturaPorItem && <div className="col-span-2">Factura</div>}
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
                                    {/* Producto — col-span adapta al modo factura */}
                                    <div className={facturaPorItem ? 'md:col-span-3' : 'md:col-span-5'}>
                                        <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Producto</label>
                                        <SearchableSelect
                                            placeholder="Buscar producto..."
                                            searchPlaceholder="Buscar por nombre o código..."
                                            emptyMessage="No hay productos que coincidan"
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

                                    {/* Pair: Precio + (Factura si modo por item). En modo único Precio queda solo. */}
                                    <div className={`grid ${facturaPorItem ? 'grid-cols-2' : 'grid-cols-1'} gap-2 md:contents`}>
                                        <div className="md:col-span-2">
                                            {/* Fila de encabezado del precio: label (solo mobile) + toggle P.U/Total */}
                                            <div className="flex items-center justify-between md:justify-end gap-2 mb-1">
                                                <label className="md:hidden text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                                    {d.precio_modo === 'total' ? 'Precio total' : 'Precio costo'}
                                                </label>
                                                <div className="inline-flex rounded-md border overflow-hidden text-[10px] font-semibold leading-none"
                                                    style={{ borderColor: 'var(--color-border)' }}>
                                                    {(['unitario', 'total'] as const).map(m => (
                                                        <button key={m} type="button" onClick={() => setPrecioModo(i, m)}
                                                            className="px-1.5 py-1 transition-colors"
                                                            style={{
                                                                backgroundColor: d.precio_modo === m ? 'var(--color-primary)' : 'transparent',
                                                                color: d.precio_modo === m ? '#fff' : 'var(--color-text-muted)',
                                                            }}>
                                                            {m === 'unitario' ? 'P.U.' : 'Total'}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                            {d.precio_modo === 'total' ? (
                                                <Input
                                                    placeholder="0.00"
                                                    type="number" min="0" step="0.01" inputMode="decimal"
                                                    value={d.precio_total}
                                                    onChange={e => setDetalle(i, 'precio_total', e.target.value)}
                                                />
                                            ) : (
                                                <Input
                                                    placeholder="0.00"
                                                    type="number" min="0" step="0.0001" inputMode="decimal"
                                                    value={d.precio_costo}
                                                    onChange={e => setDetalle(i, 'precio_costo', e.target.value)}
                                                    error={(errors as Record<string, string>)[`detalles.${i}.precio_costo`]}
                                                />
                                            )}
                                            {/* Al ingresar por total, mostramos el unitario derivado como confirmación. */}
                                            {d.precio_modo === 'total' && d.precio_costo !== '' && (
                                                <p className="mt-0.5 text-[11px] font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                                    = S/ {d.precio_costo} c/u
                                                </p>
                                            )}
                                        </div>
                                        {facturaPorItem && (
                                            <div className="md:col-span-2">
                                                <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Factura</label>
                                                <Input
                                                    placeholder="F001-..."
                                                    value={d.numero_documento}
                                                    onChange={e => setDetalle(i, 'numero_documento', e.target.value)}
                                                    error={(errors as Record<string, string>)[`detalles.${i}.numero_documento`]}
                                                />
                                            </div>
                                        )}
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

                                {/* Meta-fila: factor + cant. base */}
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs px-1" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="font-mono">×{d.factor_conversion}</span>
                                    <span className="font-mono">= {cantidadBase(d).toFixed(4)} base</span>
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

                {/* ── Pago ── */}
                <section
                    className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Wallet size={16} style={{ color: 'var(--color-text-muted)' }} />
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                Pago al proveedor
                            </h2>
                        </div>
                        <Tabs
                            tabs={[
                                { value: 'pendiente', label: 'Pendiente' },
                                { value: 'parcial',   label: 'Pago parcial' },
                                { value: 'pagado',    label: 'Pagado' },
                            ]}
                            value={estadoPago}
                            onChange={v => cambiarEstadoPago(v as EstadoPago)}
                        />
                    </div>

                    {estadoPago === 'pendiente' ? (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            La compra completa queda como deuda al proveedor (Cuentas por pagar). Puedes abonar o pagarla en cualquier momento.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {/* "Afecta caja a:" — de qué caja sale el efectivo (opt-in,
                                modo libre). Se auto-oculta si la empresa apaga 'entradas'. */}
                            <div className="sm:max-w-md">
                                <AfectaCajaSelect
                                    modulo="entradas" modo="libre" formato="largo"
                                    label="Afecta caja a (turno)"
                                    sinTurnoLabel="Sin turno / no sale de caja"
                                    turnos={turnos}
                                    value={turnoIdCaja}
                                    onChange={setTurnoIdCaja}
                                    hint="Por defecto NO sale de ninguna caja. Solo si pagaste en efectivo desde tu caja, elige tu turno para que la consolidación lo descuente."
                                />
                            </div>
                            {pagos.map((p, idx) => {
                                const cuentas = cuentasDeLinea(p);
                                return (
                                    <div key={p.key} className="grid grid-cols-1 sm:grid-cols-[1fr_1fr_150px_140px_auto] gap-3 items-end">
                                        <Select
                                            label={idx === 0 ? 'Método de pago' : undefined}
                                            required
                                            placeholder="Seleccionar método"
                                            value={p.metodo_pago_id}
                                            onChange={v => setPago(p.key, { metodo_pago_id: v === '' ? '' : Number(v), cuenta_id: '' })}
                                            options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                                        />
                                        <Select
                                            label={idx === 0 ? 'Cuenta' : undefined}
                                            placeholder={cuentas.length ? '(Opcional) elegir cuenta' : 'Se asigna sola'}
                                            value={p.cuenta_id}
                                            onChange={v => setPago(p.key, { cuenta_id: v === '' ? '' : Number(v) })}
                                            options={cuentas.map(c => ({
                                                value: c.id,
                                                label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                                            }))}
                                            disabled={cuentas.length === 0}
                                        />
                                        <Input
                                            label={idx === 0 ? 'Fecha' : undefined}
                                            required type="date"
                                            value={p.fecha}
                                            onChange={e => setPago(p.key, { fecha: e.target.value })}
                                        />
                                        <Input
                                            label={idx === 0 ? 'Monto (S/)' : undefined}
                                            required type="number" min="0.01" step="0.01"
                                            value={p.monto}
                                            onChange={e => setPago(p.key, { monto: e.target.value })}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setPagos(prev => prev.filter(x => x.key !== p.key))}
                                            disabled={pagos.length === 1}
                                            className="p-2 mb-0.5 rounded-lg hover:bg-black/5 disabled:opacity-30"
                                            title="Quitar línea de pago"
                                            style={{ color: 'var(--color-danger)' }}
                                        >
                                            <Trash2 size={15} />
                                        </button>
                                    </div>
                                );
                            })}

                            <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setPagos(prev => [...prev, nuevaLinea()])}>
                                    <Plus size={14} className="mr-1" />Agregar otro método
                                </Button>
                                <div className="flex items-center gap-5 text-sm">
                                    <span style={{ color: 'var(--color-text-muted)' }}>
                                        Pagado:{' '}
                                        <strong style={{ color: 'var(--color-success)' }}>S/ {totalPagado.toFixed(2)}</strong>
                                    </span>
                                    <span style={{ color: 'var(--color-text-muted)' }}>
                                        Queda como deuda:{' '}
                                        <strong style={{ color: Math.max(0, total - totalPagado) > 0 ? 'var(--color-danger)' : 'var(--color-text)' }}>
                                            S/ {Math.max(0, total - totalPagado).toFixed(2)}
                                        </strong>
                                    </span>
                                </div>
                            </div>
                            {errors.pagos && <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{errors.pagos}</p>}
                        </div>
                    )}
                </section>

                {/* ── Acciones ── */}
                <div className="flex flex-col sm:flex-row gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('inventario.entradas.index'))}>
                        Cancelar
                    </Button>
                    <Button type="button" variant="secondary" loading={processing} onClick={() => intentarGuardar(false)}>
                        Guardar borrador
                    </Button>
                    <Button type="button" loading={processing} onClick={() => intentarGuardar(true)}>
                        Guardar y confirmar
                    </Button>
                </div>
            </div>

            {/* Modal: confirmar entrada (actualiza stock, irreversible) */}
            <Modal
                isOpen={showConfirmModal}
                onClose={() => setShowConfirmModal(false)}
                title="Confirmar entrada"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setShowConfirmModal(false)} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button variant="success" loading={processing} onClick={() => enviar(true)}>
                            <CheckCircle size={14} className="mr-1.5" />
                            Sí, confirmar
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                        Al confirmar se actualizará el stock automáticamente. Esta acción <strong>no se puede deshacer</strong>.
                    </p>

                    {/* Resumen para que el usuario verifique antes de comprometer el stock */}
                    <div className="rounded-xl border p-3 space-y-1.5 text-sm"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--color-text-muted)' }}>Productos</span>
                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{detalles.length}</span>
                        </div>
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--color-text-muted)' }}>Tipo</span>
                            <span className="font-medium capitalize" style={{ color: 'var(--color-text)' }}>{tipo}</span>
                        </div>
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--color-text-muted)' }}>Fecha</span>
                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{fecha}</span>
                        </div>
                        <div className="flex justify-between pt-1.5 border-t" style={{ borderColor: 'var(--color-border)' }}>
                            <span style={{ color: 'var(--color-text-muted)' }}>Total</span>
                            <span className="font-mono font-bold" style={{ color: 'var(--color-text)' }}>
                                S/ {total.toFixed(2)}
                            </span>
                        </div>
                    </div>
                </div>
            </Modal>

            {/* Alta de proveedor sin salir de la entrada */}
            <ModalCrearProveedor
                isOpen={modalProveedor}
                onClose={() => setModalProveedor(false)}
                onCreated={(nuevo: ProveedorLite) => {
                    // Agregar a la lista (si no estaba) y seleccionarlo.
                    setListaProveedores(prev =>
                        prev.some(p => p.id === nuevo.id) ? prev : [nuevo as Proveedor, ...prev]);
                    setProveedorId(nuevo.id);
                    setModalProveedor(false);
                }}
            />
        </AppLayout>
    );
}
