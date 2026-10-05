import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Trash2, CheckCircle, Wallet, UserPlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Switch from '@/Components/UI/Switch';
import Tabs from '@/Components/UI/Tabs';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import DetalleProductos from './Partials/DetalleProductos';
import ModalCrearProveedor, { ProveedorLite } from './Partials/ModalCrearProveedor';
import ModalCrearCliente from '@/Pages/Pos/Partials/ModalCrearCliente';
import AfectaCajaSelect from '@/Components/AfectaCajaSelect';
import type { PageProps } from '@/types';
import { hoyLocal } from '@/lib/fechas';
import BotonGuardar, { ErroresSueltos, useProblema, type Problema } from '@/Components/UI/BotonGuardar';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; }
interface Proveedor { id: number; razon_social: string | null; nombre_comercial: string | null; numero_documento: string | null; tipo_documento: string; }
interface ClienteLite { id: number; tipo_documento: string | null; numero_documento: string | null; nombres: string | null; apellidos: string | null; razon_social: string | null; es_cliente_general?: boolean; }
interface CuentaMP { id: number; nombre: string; banco: string | null; numero_cuenta: string | null; }
interface MetodoPagoForm { id: number; nombre: string; cuentas: CuentaMP[]; }

interface TurnoLite {
    id: number; user_id: number; caja_id: number; fecha_apertura: string;
    estado: 'abierto' | 'cerrado';
    user?: { id: number; name: string } | null;
    caja?: { id: number; nombre: string } | null;
}

interface Adelanto { id: number; proveedor_id: number; saldo: string; }

interface Props extends PageProps {
    almacenes: Almacen[];
    productos: Producto[];
    proveedores: Proveedor[];
    clientes: ClienteLite[];
    metodosPago: MetodoPagoForm[];
    turnos: TurnoLite[];
    turnoActivoId: number | null;
    mostrarSelector: boolean;
    modoAlmacen: 'simple' | 'central_y_local';
    /** La empresa habilitó registrar compras que todavía no llegan. */
    usaTransito: boolean;
    /** Adelantos con saldo por proveedor para pagar con ellos. */
    adelantos: Adelanto[];
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

const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;

export default function EntradaCreate({ almacenes, productos, proveedores, clientes, metodosPago, turnos, turnoActivoId, mostrarSelector, modoAlmacen, usaTransito, adelantos }: Props) {
    // Compra despachada pero que aún no llega: no toca stock hasta que se reciba.
    const [enTransito, setEnTransito] = useState(false);
    const [fechaLlegada, setFechaLlegada] = useState('');
    // "Afecta caja a:" — por defecto NO afecta ninguna caja ('' = Sin turno). El
    // pago solo descuenta de una caja si el usuario elige explícitamente su turno.
    // Así una entrada registrada por la cajera no descuadra su caja sin querer.
    // (turnoActivoId queda disponible como atajo "usar mi caja", pero no es default.)
    const [turnoIdCaja, setTurnoIdCaja] = useState<number | ''>('');
    void turnoActivoId;
    const [almacenId, setAlmacenId]     = useState<number | ''>(almacenes.length === 1 ? almacenes[0].id : '');
    // Lista local de proveedores (para poder agregar uno nuevo sin recargar la página).
    const [listaProveedores, setListaProveedores] = useState<Proveedor[]>(proveedores);
    const [modalProveedor, setModalProveedor]     = useState(false);
    const [proveedorId, setProveedorId] = useState<number | ''>('');
    // Factura a NOMBRE de un cliente del negocio (informativo/filtro): la
    // deuda con el proveedor sigue siendo de la empresa, CxP normal.
    const [facturadaACliente, setFacturadaACliente] = useState(false);
    const [clienteId, setClienteId]                 = useState<number | ''>('');
    const [listaClientes, setListaClientes]         = useState<ClienteLite[]>(clientes ?? []);
    const [modalCliente, setModalCliente]           = useState(false);
    const nombreCliente = (c: ClienteLite) => {
        const nombre = c.razon_social || [c.nombres, c.apellidos].filter(Boolean).join(' ') || '—';
        return `${nombre}${c.numero_documento ? ` · ${c.tipo_documento ?? ''} ${c.numero_documento}`.trimEnd() : ''}`;
    };
    const [nroDoc, setNroDoc]           = useState('');
    const [tipo, setTipo]               = useState<string>('compra');
    const [fecha, setFecha]             = useState(hoyLocal());
    const [observacion, setObservacion] = useState('');
    // Arranca vacío: los productos se agregan desde el buscador de la sección.
    const [detalles, setDetalles]       = useState<DetalleRow[]>([]);
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
    type ModoPago = 'efectivo' | 'adelanto';
    interface LineaPago { key: string; modo: ModoPago; metodo_pago_id: number | ''; cuenta_id: number | ''; proveedor_adelanto_id: number | ''; monto: string; fecha: string; }
    const nuevaLinea = (monto = '', modo: ModoPago = 'efectivo'): LineaPago =>
        ({ key: Math.random().toString(36).slice(2), modo, metodo_pago_id: '', cuenta_id: '', proveedor_adelanto_id: '', monto, fecha: hoyLocal() });

    const [estadoPago, setEstadoPago] = useState<EstadoPago>('pendiente');
    const [pagos, setPagos]           = useState<LineaPago[]>([]);

    const cuentasDeLinea = (l: LineaPago) =>
        metodosPago.find(m => m.id === l.metodo_pago_id)?.cuentas ?? [];
    // Cuenta por defecto: 1 cuenta → se autoselecciona; 2+ → obligatorio elegir.
    const cuentaDefaultDe = (metodoId: number | ''): number | '' => {
        const cts = metodosPago.find(m => m.id === Number(metodoId))?.cuentas ?? [];
        return cts.length === 1 ? cts[0].id : '';
    };
    const adelantosDisponibles = proveedorId
        ? adelantos.filter(a => a.proveedor_id === proveedorId && Number(a.saldo) > 0)
        : [];
    const saldoAdelantosDisponibles = adelantosDisponibles.reduce((s, a) => s + Number(a.saldo), 0);
    const totalPagado = pagos.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);

    function cambiarEstadoPago(v: EstadoPago) {
        setEstadoPago(v);
        if (v === 'pendiente') setPagos([]);
        if (v === 'parcial' && pagos.length === 0) setPagos([nuevaLinea()]);
        if (v === 'pagado') setPagos(prev => {
            if (prev.length === 0) return [nuevaLinea(total.toFixed(2))];
            // Si ya hay líneas, dejarlas intactas (pueden ser mixtas adelanto + efectivo).
            return prev;
        });
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
     * Qué impide guardar AHORA, en palabras de quien registra (mismas reglas que
     * EntradaController@store). Devuelve el PRIMER problema, en el orden de la
     * pantalla; el botón de guardar lo muestra y "Corregir" lleva al campo.
     */
    function problemaEntrada(): Problema | null {
        const nombreDe = (d: DetalleRow, n: number) =>
            productos.find(p => p.id === d.producto_id)?.nombre ?? `el producto #${n}`;

        if (almacenes.length === 0) return { texto: 'No tienes un almacén disponible para registrar entradas' };
        if (!almacenId) return { texto: 'Elige el almacén destino', campo: 'almacen_id' };
        if (!tipo)      return { texto: 'Elige el tipo de entrada', campo: 'tipo' };
        if (!fecha)     return { texto: 'Elige la fecha de la entrada', campo: 'fecha' };
        if (facturadaACliente && !clienteId) return { texto: 'Elige el cliente al que se facturó la compra', campo: 'cliente_id' };

        const conProducto = detalles.filter(d => d.producto_id !== '');
        if (conProducto.length === 0) return { texto: 'Agrega al menos un producto', campo: 'detalles' };
        for (let i = 0; i < detalles.length; i++) {
            const d = detalles[i];
            if (d.producto_id === '') continue;
            const nombre = nombreDe(d, i + 1);
            if (!d.unidad_medida_id) return { texto: `Elige la unidad de ${nombre}`, campo: `detalles.${i}.unidad_medida_id` };
            const qty = parseFloat(d.cantidad);
            if (d.cantidad.trim() === '') return { texto: `Escribe la cantidad de ${nombre}`, campo: `detalles.${i}.cantidad` };
            if (isNaN(qty) || qty <= 0) return { texto: `La cantidad de ${nombre} debe ser mayor a 0`, campo: `detalles.${i}.cantidad` };
            const cost = parseFloat(d.precio_costo);
            const precioTecleado = d.precio_modo === 'total' ? d.precio_total : d.precio_costo;
            if (precioTecleado.trim() === '' || d.precio_costo === '') return { texto: `Escribe el precio de ${nombre}`, campo: `detalles.${i}.precio_costo` };
            if (isNaN(cost) || cost < 0) return { texto: `El precio de ${nombre} no puede ser negativo`, campo: `detalles.${i}.precio_costo` };
        }

        // Pagos (parcial o pagado): cada línea con método (o adelanto), cuenta si
        // el método tiene cuentas, y monto > 0; la suma cuadra con el modo.
        if (estadoPago !== 'pendiente') {
            if (pagos.length === 0) return { texto: 'Agrega una línea de pago', campo: 'pagos-agregar' };
            for (let i = 0; i < pagos.length; i++) {
                const p = pagos[i];
                const n = pagos.length > 1 ? ` (pago #${i + 1})` : '';
                if (p.modo === 'efectivo') {
                    if (!p.metodo_pago_id) return { texto: `Elige el método de pago${n}`, campo: `pagos.${i}.metodo_pago_id` };
                    if (cuentasDeLinea(p).length > 0 && !p.cuenta_id) {
                        const metodo = metodosPago.find(m => m.id === p.metodo_pago_id)?.nombre ?? 'este método';
                        return { texto: `Elige la cuenta de ${metodo}${n}`, campo: `pagos.${i}.cuenta_id` };
                    }
                } else {
                    if (!p.proveedor_adelanto_id) return { texto: `Elige el adelanto a consumir${n}`, campo: `pagos.${i}.proveedor_adelanto_id` };
                }
                if (p.monto.trim() === '') return { texto: `Escribe el monto del pago${n}`, campo: `pagos.${i}.monto` };
                const m = parseFloat(p.monto);
                if (isNaN(m) || m < 0.01) return { texto: `El monto del pago${n} debe ser mayor a S/ 0.00`, campo: `pagos.${i}.monto` };
                if (p.modo === 'adelanto') {
                    const adelanto = adelantosDisponibles.find(a => a.id === p.proveedor_adelanto_id);
                    if (adelanto && m > Number(adelanto.saldo) + 0.009) {
                        return { texto: `El monto${n} no puede pasar del saldo del adelanto ${money(adelanto.saldo)}`, campo: `pagos.${i}.monto` };
                    }
                }
            }
            const suma = Math.round(totalPagado * 100) / 100;
            const tot  = Math.round(total * 100) / 100;
            const ultimo = `pagos.${pagos.length - 1}.monto`;
            if (suma > tot + 0.009) {
                return { texto: `Los pagos (${money(suma)}) no pueden pasar del total de la compra ${money(tot)}`, campo: ultimo };
            }
            if (estadoPago === 'pagado' && Math.abs(suma - tot) > 0.01) {
                return { texto: `En "Pagado" los pagos deben sumar ${money(tot)} (van ${money(suma)}); si es a cuenta, elige "Pago parcial"`, campo: ultimo };
            }
            if (estadoPago === 'parcial' && suma >= tot - 0.01 && tot > 0) {
                return { texto: `El pago (${money(suma)}) cubre todo el total: elige "Pagado"` };
            }
        }
        return null;
    }
    const problema = problemaEntrada();
    const { err, errs, corregir } = useProblema(problema, errors);
    // Errores para la tabla de productos: los del servidor + el problema actual si es de una fila.
    const erroresDetalle = { ...errors, ...(problema?.campo ? errs(problema.campo) : {}) };

    function intentarGuardar(confirmar: boolean) {
        if (problema) { corregir(); return; }
        // El modal de "esto mueve stock" solo aplica a la confirmación real.
        // Guardar algo que viene en camino no toca stock, así que va directo.
        if (confirmar && !enTransito) {
            setShowConfirmModal(true);
        } else {
            enviar(confirmar);
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
            confirmar: confirmar && !enTransito,
            en_transito: enTransito,
            fecha_estimada_llegada: enTransito ? (fechaLlegada || null) : null,
            facturada_a_cliente: facturadaACliente,
            cliente_id: facturadaACliente ? (clienteId || null) : null,
            estado_pago:       estadoPago,
            pagos: estadoPago === 'pendiente' ? [] : pagos.map(p => ({
                metodo_pago_id: p.modo === 'adelanto' ? null : p.metodo_pago_id,
                cuenta_id:      p.modo === 'adelanto' ? null : (p.cuenta_id || null),
                proveedor_adelanto_id: p.modo === 'adelanto' ? p.proveedor_adelanto_id : null,
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
                // Lo que el servidor rechace se escribe junto a su campo (y lo que
                // no tenga campo, en el aviso de abajo, junto a los botones).
                setErrors(e);
                setProcessing(false);
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
                                triggerAttrs={{ 'data-campo': 'almacen_id' }}
                                value={almacenId}
                                onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                                options={almacenes.map(a => ({ value: a.id, label: a.nombre }))}
                                error={err('almacen_id')}
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
                            triggerAttrs={{ 'data-campo': 'tipo' }}
                            value={tipo}
                            onChange={v => setTipo(String(v))}
                            options={[
                                { value: 'compra',     label: 'Compra' },
                                { value: 'ajuste',     label: 'Ajuste' },
                                { value: 'devolucion', label: 'Devolución' },
                                { value: 'otro',       label: 'Otro' },
                            ]}
                            error={err('tipo')}
                        />
                        <div>
                            <div className="flex items-end gap-2">
                                <div className="flex-1 min-w-0">
                                    <SearchableSelect
                                        label="Proveedor"
                                        placeholder="Sin proveedor"
                                        searchPlaceholder="Buscar por nombre o RUC..."
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
                            <Input label="Nro. documento" value={nroDoc} maxLength={50} onChange={e => setNroDoc(e.target.value)} placeholder="Ej: F001-0001234" error={errors.numero_documento} />
                        )}
                        <Input label="Fecha" required type="date" data-campo="fecha" value={fecha} onChange={e => setFecha(e.target.value)} error={err('fecha')} />
                    </div>

                    {/* Compra ya facturada pero que llega días después. Mientras esté
                        en camino no suma stock: recién entra cuando se marca la
                        recepción desde el listado de Entradas. */}
                    {usaTransito && (
                        <div className="space-y-3">
                            <Switch
                                label="La mercadería aún no llega"
                                description="La compra queda registrada como en camino. El stock no se moverá hasta que marques la recepción, pero la deuda al proveedor cuenta desde ya si dejas saldo pendiente."
                                checked={enTransito}
                                onChange={setEnTransito}
                            />
                            {enTransito && (
                                <div className="pl-1 max-w-xs">
                                    <Input
                                        label="Fecha estimada de llegada"
                                        type="date"
                                        value={fechaLlegada}
                                        onChange={e => setFechaLlegada(e.target.value)}
                                    />
                                    <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                        Opcional. Si la pones y se pasa sin que llegue, la entrada se marca como atrasada.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Compra cuya factura salió a NOMBRE de un cliente del negocio.
                        Solo informativo/filtro: la deuda con el proveedor sigue siendo
                        de la empresa y cuenta normal en CxP y balance. */}
                    <div className="space-y-3">
                        <Switch
                            label="Facturada directamente al cliente"
                            description="La factura del proveedor sale a NOMBRE de un cliente del negocio. Es solo informativo: la deuda y el pago al proveedor siguen siendo de la empresa (Cuentas por Pagar normal)."
                            checked={facturadaACliente}
                            onChange={v => { setFacturadaACliente(v); if (!v) setClienteId(''); }}
                        />
                        {facturadaACliente && (
                            <div className="flex items-end gap-2 max-w-lg">
                                <div className="flex-1 min-w-0" data-campo="cliente_id">
                                    <SearchableSelect
                                        label="Cliente facturado"
                                        required
                                        placeholder="— Seleccionar cliente —"
                                        searchPlaceholder="Buscar por nombre o documento..."
                                        value={clienteId}
                                        onChange={v => setClienteId(v === '' ? '' : Number(v))}
                                        options={listaClientes.map(c => ({ value: c.id, label: nombreCliente(c) }))}
                                        error={err('cliente_id')}
                                    />
                                </div>
                                <Button type="button" variant="secondary" onClick={() => setModalCliente(true)}
                                    title="Crear nuevo cliente">
                                    <UserPlus size={15} className="mr-1" /> Nuevo
                                </Button>
                            </div>
                        )}
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
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Productos
                    </h2>

                    <DetalleProductos
                        productos={productos}
                        detalles={detalles}
                        setDetalles={setDetalles}
                        setDetalle={setDetalle}
                        setPrecioModo={setPrecioModo}
                        removeDetalle={removeDetalle}
                        subtotal={subtotal}
                        cantidadBase={cantidadBase}
                        facturaPorItem={facturaPorItem}
                        errors={erroresDetalle}
                        total={total}
                    />
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
                            {/* Info de adelantos disponibles */}
                            {adelantosDisponibles.length > 0 && (
                                <Callout variant="info" title="Adelantos disponibles" aside={money(saldoAdelantosDisponibles)}>
                                    Este proveedor tiene {adelantosDisponibles.length} adelanto(s) con saldo. Puedes pagar una línea usando el adelanto y completar el resto en efectivo/cuenta.
                                </Callout>
                            )}

                            {pagos.map((p, idx) => {
                                const cuentas = cuentasDeLinea(p);
                                const adelantoSel = adelantosDisponibles.find(a => a.id === p.proveedor_adelanto_id);
                                return (
                                    <div key={p.key} className="rounded-xl border p-3 space-y-3"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                                Pago #{idx + 1}
                                            </span>
                                            {adelantosDisponibles.length > 0 && (
                                                <div className="inline-flex rounded-md border overflow-hidden text-[11px] font-semibold leading-none"
                                                    style={{ borderColor: 'var(--color-border)' }}>
                                                    {(['efectivo', 'adelanto'] as const).map(m => (
                                                        <button key={m} type="button"
                                                            onClick={() => setPago(p.key, {
                                                                modo: m,
                                                                metodo_pago_id: '',
                                                                cuenta_id: '',
                                                                proveedor_adelanto_id: '',
                                                                monto: m === 'adelanto'
                                                                    ? String(Math.min(
                                                                        Math.max(0, Number((total - totalPagado + Number(p.monto)).toFixed(2))),
                                                                        Number(adelantosDisponibles[0]?.saldo ?? 0)
                                                                    ))
                                                                    : p.monto,
                                                            })}
                                                            className="px-2 py-1 transition-colors"
                                                            style={{
                                                                backgroundColor: p.modo === m ? 'var(--color-primary)' : 'transparent',
                                                                color: p.modo === m ? '#fff' : 'var(--color-text-muted)',
                                                            }}>
                                                            {m === 'efectivo' ? 'Efectivo / Cuenta' : 'Adelanto'}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => setPagos(prev => prev.filter(x => x.key !== p.key))}
                                                disabled={pagos.length === 1}
                                                className="p-1.5 rounded-lg hover:bg-black/5 disabled:opacity-30 ml-auto"
                                                title="Quitar línea de pago"
                                                style={{ color: 'var(--color-danger)' }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-[1fr_1fr_150px_140px] gap-3 items-end">
                                            {p.modo === 'adelanto' ? (
                                                <Select
                                                    label="Adelanto disponible"
                                                    required
                                                    triggerAttrs={{ 'data-campo': `pagos.${idx}.proveedor_adelanto_id` }}
                                                    placeholder={adelantosDisponibles.length ? '— Seleccionar adelanto —' : 'Sin adelantos'}
                                                    value={p.proveedor_adelanto_id}
                                                    onChange={v => {
                                                        const aid = v === '' ? '' : Number(v);
                                                        const a = adelantosDisponibles.find(x => x.id === aid);
                                                        const saldoA = a ? Number(a.saldo) : 0;
                                                        const saldoPendiente = Math.max(0, Number((total - totalPagado + Number(p.monto)).toFixed(2)));
                                                        setPago(p.key, {
                                                            proveedor_adelanto_id: aid,
                                                            monto: String(Math.min(saldoPendiente, saldoA) || ''),
                                                        });
                                                    }}
                                                    options={adelantosDisponibles.map(a => ({
                                                        value: a.id,
                                                        label: `Adelanto #${a.id} — saldo ${money(a.saldo)}`,
                                                    }))}
                                                    disabled={adelantosDisponibles.length === 0}
                                                    error={err(`pagos.${idx}.proveedor_adelanto_id`)}
                                                />
                                            ) : (
                                                <Select
                                                    label="Método de pago"
                                                    required
                                                    triggerAttrs={{ 'data-campo': `pagos.${idx}.metodo_pago_id` }}
                                                    placeholder="Seleccionar método"
                                                    value={p.metodo_pago_id}
                                                    onChange={v => setPago(p.key, { metodo_pago_id: v === '' ? '' : Number(v), cuenta_id: cuentaDefaultDe(v === '' ? '' : Number(v)) })}
                                                    options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                                                    error={err(`pagos.${idx}.metodo_pago_id`)}
                                                />
                                            )}
                                            {p.modo === 'adelanto' ? (
                                                <div className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                                    {adelantoSel
                                                        ? <span>Saldo: <strong style={{ color: 'var(--color-success)' }}>{money(adelantoSel.saldo)}</strong></span>
                                                        : <span>Selecciona un adelanto</span>}
                                                </div>
                                            ) : (
                                                <Select
                                                    label="Cuenta"
                                                    required={cuentas.length > 0}
                                                    triggerAttrs={{ 'data-campo': `pagos.${idx}.cuenta_id` }}
                                                    placeholder={cuentas.length ? '— Selecciona una cuenta —' : 'Se asigna sola'}
                                                    value={p.cuenta_id}
                                                    onChange={v => setPago(p.key, { cuenta_id: v === '' ? '' : Number(v) })}
                                                    options={cuentas.map(c => ({
                                                        value: c.id,
                                                        label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                                                    }))}
                                                    disabled={cuentas.length === 0}
                                                    error={err(`pagos.${idx}.cuenta_id`) ?? (cuentas.length > 0 && !p.cuenta_id ? 'Elige la cuenta' : undefined)}
                                                />
                                            )}
                                            <Input
                                                label="Fecha"
                                                required type="date"
                                                value={p.fecha}
                                                onChange={e => setPago(p.key, { fecha: e.target.value })}
                                                error={errors[`pagos.${idx}.fecha`]}
                                            />
                                            <Input
                                                label={`Monto (S/)${p.modo === 'adelanto' && adelantoSel ? ` · máx. ${money(adelantoSel.saldo)}` : ''}`}
                                                required type="number" min="0.01" step="0.01"
                                                data-campo={`pagos.${idx}.monto`}
                                                value={p.monto}
                                                onChange={e => setPago(p.key, { monto: e.target.value })}
                                                error={err(`pagos.${idx}.monto`)}
                                            />
                                        </div>
                                    </div>
                                );
                            })}

                            <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                <Button type="button" variant="ghost" size="sm" data-campo="pagos-agregar" onClick={() => setPagos(prev => [...prev, nuevaLinea()])}>
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
                            {errors.pagos && <Callout variant="danger">{errors.pagos}</Callout>}
                        </div>
                    )}
                </section>

                {/* Errores del servidor que no tienen un campo visible donde mostrarse. */}
                <ErroresSueltos errors={errors} visibles={[
                    'almacen_id', 'tipo', 'fecha', 'numero_documento', 'cliente_id', 'detalles', 'detalles.*',
                    ...(estadoPago !== 'pendiente' ? ['pagos', 'pagos.*'] : []),
                ]} />

                {/* ── Acciones ── Si falta algo, el botón LO DICE y lleva al campo. */}
                <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('inventario.entradas.index'))}>
                        Cancelar
                    </Button>
                    {!problema && (
                        <Button type="button" variant="secondary" loading={processing} onClick={() => intentarGuardar(false)}>
                            Guardar borrador
                        </Button>
                    )}
                    <BotonGuardar problema={problema} onGuardar={() => intentarGuardar(true)} onCorregir={corregir} guardando={processing}>
                        {enTransito ? 'Guardar como en camino' : 'Guardar y confirmar'}
                    </BotonGuardar>
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

            {/* Alta de cliente sin salir de la entrada (facturación al cliente) */}
            <ModalCrearCliente
                isOpen={modalCliente}
                onClose={() => setModalCliente(false)}
                onCreated={nuevo => {
                    setListaClientes(prev =>
                        prev.some(c => c.id === nuevo.id) ? prev : [nuevo as unknown as ClienteLite, ...prev]);
                    setClienteId(nuevo.id);
                    setModalCliente(false);
                }}
            />
        </AppLayout>
    );
}
