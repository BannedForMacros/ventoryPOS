import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Search, ShoppingCart, User, X, ArrowLeft, ChevronDown,
    Package, Receipt, Layers, AlertTriangle, ShoppingBag, ChevronUp,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import Button from '@/Components/UI/Button';
import CarritoItem, { LineaCarrito } from './Partials/CarritoItem';
import PanelPago, { LineaPago } from './Partials/PanelPago';
import PanelDescuento from './Partials/PanelDescuento';
import ModalClienteRapido from './Partials/ModalClienteRapido';
import ModalConfirmacionVenta from './Partials/ModalConfirmacionVenta';
import ModalSelectorPresentacion from './Partials/ModalSelectorPresentacion';
import type { Cliente, DescuentoConcepto, MetodoPago, Cuenta, Producto, ProductoUnidad, Turno, PageProps } from '@/types';

interface MetodoPagoConCuentas extends MetodoPago { cuentas?: Cuenta[]; }

interface CitaPrellenadaItem {
    producto_id:        number;
    producto_unidad_id: number;
    producto_nombre:    string;
    unidad_nombre:      string;
    cantidad:           number;
    precio_unitario:    number;
    incluye_igv:        boolean;
    // Flags de frescura: el producto o la unidad pueden haber sido desactivados
    // entre el agendamiento y el cobro. El cajero debe verlo y resolverlo.
    producto_activo:    boolean;
    unidad_activa:      boolean;
    inactivo:           boolean;
}

interface CitaPrellenada {
    id:               number;
    numero:           string;
    sujeto_label:     string | null;
    sujeto:           string | null;
    cliente:          Cliente;
    items:            CitaPrellenadaItem[];
    tiene_inactivos:  boolean;
}

interface Props extends PageProps {
    turno:              Turno;
    productos:          Producto[];
    clientes:           Cliente[];
    metodosPago:        MetodoPagoConCuentas[];
    conceptosDescuento: DescuentoConcepto[];
    citaPrellenada?:    CitaPrellenada | null;
    // A14: el backend valida que el usuario pueda operar el POS al CARGAR la
    // pantalla (admin sin local_id en modo central_y_local, almacén
    // desactivado, etc.). Si puedeVender=false bloqueamos el botón cobrar
    // desde el principio en vez de fallar al final con un 422.
    puedeVender:        boolean;
    razonNoVender:      string | null;
}

type TipoComprobante = 'ticket' | 'boleta' | 'factura';

function uid() { return Math.random().toString(36).slice(2); }

/**
 * Token unico de la venta-en-construccion. Se genera al abrir la confirmacion y
 * acompana cada intento de submit. El backend usa este key para garantizar que
 * un doble click o un reintento por timeout NO genere ventas duplicadas.
 * Usa crypto.randomUUID si esta disponible (browsers modernos), si no, fallback.
 */
function generarIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

/**
 * Espejo en TS del calculo del backend (Venta::calcularTotales).
 * Separa base gravada (afecta IGV) de base exonerada (no afecta IGV) y
 * prorratea el descuento_total entre ambas bases. Debe coincidir centavo
 * a centavo con el backend para que el cajero no vea un total y el backend
 * cobre otro.
 */
function calcularTotales(items: LineaCarrito[], descuentoTotal: number, tasaPorcentaje: number) {
    const tasa = tasaPorcentaje / 100;
    const subtotal = items.reduce((s, i) => s + i.subtotal, 0);

    let baseGravadaRaw = 0;
    let baseExonerada  = 0;
    for (const i of items) {
        if (i.incluye_igv) {
            baseGravadaRaw += tasa > 0 ? i.subtotal / (1 + tasa) : i.subtotal;
        } else {
            baseExonerada += i.subtotal;
        }
    }

    const totalBases = baseGravadaRaw + baseExonerada;
    let descGravado = 0;
    let descExon    = 0;
    if (totalBases > 0 && descuentoTotal > 0) {
        descGravado = descuentoTotal * (baseGravadaRaw / totalBases);
        descExon    = descuentoTotal * (baseExonerada  / totalBases);
    }

    const baseGravadaFinal = Math.max(0, baseGravadaRaw - descGravado);
    const baseExonFinal    = Math.max(0, baseExonerada  - descExon);

    const igv   = Math.round(baseGravadaFinal * tasa * 100) / 100;
    const total = Math.round((baseGravadaFinal + igv + baseExonFinal) * 100) / 100;
    return { subtotal, igv, total };
}

export default function PosIndex({ turno, productos, clientes, metodosPago, conceptosDescuento, flash, citaPrellenada, puedeVender, razonNoVender }: Props) {
    // Tasa de IGV de la empresa (configurable por tenant). Default 18% si no llega.
    const empresaAuth = usePage().props.auth?.user?.empresa as { tasa_igv?: number | string } | undefined;
    const tasaIgv = Number(empresaAuth?.tasa_igv ?? 18);

    // A15: identificar el Cliente General por la flag `es_cliente_general`
    // (no por DNI mágico). Fallback al DNI legado para compatibilidad si el
    // backend no envía la flag por alguna razón.
    const clienteGeneral =
        clientes.find(c => (c as Cliente & { es_cliente_general?: boolean }).es_cliente_general)
        ?? clientes.find(c => c.numero_documento === '99999999')
        ?? null;

    // Si venimos desde una cita, prellenar carrito y cliente automaticamente.
    // Cada linea propaga su flag `inactivo` para que CarritoItem la pinte en rojo
    // y el boton de cobrar quede deshabilitado mientras existan inactivos.
    const carritoInicial: LineaCarrito[] = citaPrellenada?.items.map(it => {
        const subtotal = it.precio_unitario * it.cantidad;
        const motivo = !it.producto_activo
            ? `El producto "${it.producto_nombre}" fue desactivado.`
            : !it.unidad_activa
                ? `La presentación "${it.unidad_nombre}" fue desactivada.`
                : undefined;
        return {
            key: `${it.producto_id}-${it.producto_unidad_id}`,
            producto_id:           it.producto_id,
            producto_unidad_id:    it.producto_unidad_id,
            producto_nombre:       it.producto_nombre,
            unidad_nombre:         it.unidad_nombre,
            precio_unitario:       it.precio_unitario,
            precio_original:       it.precio_unitario,
            cantidad:              it.cantidad,
            descuento_item:        0,
            descuento_concepto_id: null,
            subtotal,
            incluye_igv:           it.incluye_igv,
            inactivo:              it.inactivo,
            motivo_inactivo:       motivo,
        };
    }) ?? [];

    const clienteInicial: Cliente | null = citaPrellenada?.cliente ?? clienteGeneral;

    const [busqueda, setBusqueda]           = useState('');
    const [carrito, setCarrito]             = useState<LineaCarrito[]>(carritoInicial);
    const [pagos, setPagos]                 = useState<LineaPago[]>([]);
    const [cliente, setCliente]             = useState<Cliente | null>(clienteInicial);
    const [descuentoTotal, setDescuentoTotal]       = useState(0);
    const [descuentoConceptoId, setDescuentoConceptoId] = useState<number | null>(null);
    const [tipoComprobante, setTipoComprobante]     = useState<TipoComprobante>('ticket');
    const [modalCliente, setModalCliente]   = useState(false);
    const [modalConfirm, setModalConfirm]   = useState(false);
    const [loading, setLoading]             = useState(false);
    const [carritoAbierto, setCarritoAbierto] = useState(false);
    const [categoriaActiva, setCategoriaActiva] = useState<string | null>(null);
    // Token de idempotencia: se genera al abrir el modal de confirmacion y se
    // mantiene mientras siga la misma venta-en-construccion. Se renueva al
    // limpiar el carrito tras un OK exitoso.
    const [idempotencyKey, setIdempotencyKey] = useState<string>(() => generarIdempotencyKey());
    // Producto pendiente de elegir presentacion (cuando tiene 2+ unidades)
    const [productoEnSeleccion, setProductoEnSeleccion] = useState<Producto | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // Aviso inmediato al cajero cuando se abre el POS desde una cita con items
    // desactivados. Solo se dispara una vez al montar; despues se ven los flags
    // por linea y el banner persistente.
    useEffect(() => {
        if (citaPrellenada?.tiene_inactivos) {
            const inactivos = citaPrellenada.items.filter(i => i.inactivo);
            toast.error(
                `Esta cita tiene ${inactivos.length} ítem(s) desactivado(s) desde que se agendó. ` +
                'Revisa el carrito antes de cobrar.',
                { duration: 6000 },
            );
        }
    }, []);

    const { subtotal, igv, total } = calcularTotales(carrito, descuentoTotal, tasaIgv);

    // Auto-agregar pago en efectivo por defecto cuando hay items y no hay pagos.
    // Buscamos el método con tipo.slug === 'efectivo' como conveniencia inicial.
    // El flag `admite_vuelto` se lee del método (BD).
    const efectivo = metodosPago.find(m => m.tipo?.slug === 'efectivo');
    useEffect(() => {
        if (carrito.length > 0 && pagos.length === 0 && efectivo) {
            setPagos([{
                key:                   uid(),
                metodo_pago_id:        efectivo.id,
                cuenta_metodo_pago_id: null,
                monto:                 parseFloat(total.toFixed(2)),
                referencia:            '',
                admite_vuelto:         !!efectivo.admite_vuelto,
                es_efectivo:           true,
            }]);
        }
    }, [carrito.length]);

    // Auto-actualizar monto del pago si es el único y admite vuelto (efectivo
    // por defecto). Si no admite vuelto el monto debe ser exacto, lo dejamos.
    useEffect(() => {
        if (pagos.length === 1 && pagos[0].admite_vuelto && total > 0) {
            setPagos(prev => [{ ...prev[0], monto: parseFloat(total.toFixed(2)) }]);
        }
    }, [total]);

    // Categorías únicas
    const categorias = useMemo(() => {
        const cats = new Set<string>();
        productos.forEach(p => {
            if (p.categoria?.nombre) cats.add(p.categoria.nombre);
        });
        return Array.from(cats).sort();
    }, [productos]);

    const productosFiltrados = useMemo(() => {
        let filtrados = productos;

        if (categoriaActiva) {
            filtrados = filtrados.filter(p => p.categoria?.nombre === categoriaActiva);
        }

        const q = busqueda.toLowerCase();
        if (q) {
            filtrados = filtrados.filter(p =>
                p.nombre.toLowerCase().includes(q) ||
                (p.codigo ?? '').toLowerCase().includes(q) ||
                (p.categoria?.nombre ?? '').toLowerCase().includes(q)
            );
        }
        return filtrados;
    }, [busqueda, productos, categoriaActiva]);

    /**
     * Punto de entrada al hacer click en un producto del catalogo.
     * - Si tiene 1 sola presentacion (caso tipico) → agrega directo al carrito.
     * - Si tiene 2+ presentaciones (talla P/M/G en servicios, presentaciones
     *   de bebida en bodega) → abre el modal selector para que el cajero elija.
     */
    function agregarProducto(producto: Producto) {
        const unidadesActivas = (producto.unidades ?? []).filter(u => u.activo !== false);

        if (unidadesActivas.length === 0) {
            toast.error('El producto no tiene presentaciones configuradas.');
            return;
        }

        if (unidadesActivas.length === 1) {
            agregarConPresentacion(producto, unidadesActivas[0]);
            return;
        }

        // 2+ presentaciones → abrir modal selector
        setProductoEnSeleccion(producto);
    }

    /**
     * Agrega al carrito una linea con la presentacion ya elegida.
     * Si esa misma presentacion ya esta en el carrito, suma cantidad.
     */
    function agregarConPresentacion(producto: Producto, unidad: ProductoUnidad) {
        const key = `${producto.id}-${unidad.id}`;
        const existente = carrito.find(i => i.key === key);

        if (existente) {
            cambiarCantidad(key, 1);
        } else {
            const precio = parseFloat(unidad.precio_venta);
            const item: LineaCarrito = {
                key,
                producto_id:          producto.id,
                producto_unidad_id:   unidad.id,
                producto_nombre:      producto.nombre,
                unidad_nombre:        unidad.unidad_medida?.nombre ?? '',
                precio_unitario:      precio,
                precio_original:      precio,
                cantidad:             1,
                descuento_item:       0,
                descuento_concepto_id: null,
                subtotal:             precio,
                incluye_igv:          producto.incluye_igv,
            };
            setCarrito(prev => [...prev, item]);
        }

        const nombreCompleto = unidad.unidad_medida?.nombre
            ? `${producto.nombre} (${unidad.unidad_medida.nombre})`
            : producto.nombre;
        toast.success(`${nombreCompleto} agregado`, { duration: 1000 });
    }

    function cambiarCantidad(key: string, delta: number) {
        setCarrito(prev => prev.map(i => {
            if (i.key !== key) return i;
            const cantidad = Math.max(1, i.cantidad + delta);
            return { ...i, cantidad, subtotal: Math.round((i.precio_unitario - i.descuento_item) * cantidad * 100) / 100 };
        }));
    }

    function aplicarDescuentoItem(key: string, descuento: number, conceptoId: number | null) {
        setCarrito(prev => prev.map(i => {
            if (i.key !== key) return i;
            const d = Math.min(descuento, i.precio_unitario);
            return {
                ...i,
                descuento_item:        d,
                descuento_concepto_id: conceptoId,
                subtotal:              Math.round((i.precio_unitario - d) * i.cantidad * 100) / 100,
            };
        }));
    }

    function eliminarItem(key: string) {
        setCarrito(prev => prev.filter(i => i.key !== key));
    }

    function limpiarCarrito() {
        setCarrito([]);
        setPagos([]);
        setCliente(clienteGeneral);
        setDescuentoTotal(0);
        setDescuentoConceptoId(null);
        setTipoComprobante('ticket');
    }

    // Lineas que vienen de una cita con producto/unidad desactivada. Si hay,
    // no permitimos confirmar la venta hasta que el cajero las elimine o pida
    // al admin reactivarlas. El backend tambien lo rechaza, pero queremos UX
    // clara en lugar de un error 422 al final del flujo.
    const itemsInactivos = carrito.filter(i => i.inactivo);
    const hayInactivos   = itemsInactivos.length > 0;

    function confirmarVenta() {
        if (carrito.length === 0) { toast.error('El carrito está vacío.'); return; }
        if (hayInactivos) {
            toast.error(`Hay ${itemsInactivos.length} ítem(s) inactivo(s). Elimínalos del carrito antes de cobrar.`);
            return;
        }
        if (pagos.length === 0) { toast.error('Agrega al menos un método de pago.'); return; }
        const totalPagado = pagos.reduce((s, p) => s + p.monto, 0);
        if (totalPagado < total - 0.009) { toast.error(`Faltan S/ ${(total - totalPagado).toFixed(2)} por cubrir.`); return; }
        setModalConfirm(true);
    }

    function submitVenta() {
        // Anti-doble-click: si ya hay una venta en proceso, ignorar nuevos intentos.
        // El boton del modal ya se deshabilita visualmente, pero esta guarda cubre
        // casos como tecla Enter o clicks muy rapidos antes del re-render.
        if (loading) return;

        setLoading(true);

        const payload = {
            cliente_id:            cliente?.id ?? null,
            tipo_comprobante:      tipoComprobante,
            descuento_total:       descuentoTotal,
            descuento_concepto_id: descuentoConceptoId,
            // Se reenvia el mismo key en cada reintento. El backend desduplica.
            idempotency_key:       idempotencyKey,
            // Si vino de una cita, lo enviamos para que el backend la vincule.
            cita_id:               citaPrellenada?.id ?? null,
            items: carrito.map(i => ({
                producto_id:           i.producto_id,
                producto_unidad_id:    i.producto_unidad_id,
                cantidad:              i.cantidad,
                precio_unitario:       i.precio_unitario,
                descuento_item:        i.descuento_item,
                descuento_concepto_id: i.descuento_concepto_id,
                incluye_igv:           i.incluye_igv,
            })),
            pagos: pagos.map(p => ({
                metodo_pago_id:        p.metodo_pago_id,
                cuenta_metodo_pago_id: p.cuenta_metodo_pago_id,
                monto:                 p.monto,
                referencia:            p.referencia,
                // El backend ignora estos flags y deriva la decisión desde
                // metodos_pago.admite_vuelto en BD. Los enviamos por compatibilidad.
                admite_vuelto:         p.admite_vuelto,
                es_efectivo:           p.es_efectivo,
            })),
        };

        router.post(route('ventas.store'), payload as any, {
            onSuccess: () => {
                setLoading(false);
                setModalConfirm(false);
                limpiarCarrito();
                setCarritoAbierto(false);
                // Renovar key para la proxima venta (la actual ya quedo persistida).
                setIdempotencyKey(generarIdempotencyKey());
            },
            onError: (errors) => {
                setLoading(false);
                const msg = Object.values(errors)[0];
                if (msg) toast.error(msg as string);
            },
        });
    }

    const cantidadItems = carrito.reduce((s, i) => s + i.cantidad, 0);

    return (
        <PosLayout>
            {/* Banner de cita activa (cuando se llega desde la agenda) */}
            {citaPrellenada && (
                <div
                    className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-shrink-0 border-b text-sm"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, var(--color-bg))',
                        borderColor: 'var(--color-primary)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                            style={{ backgroundColor: 'var(--color-primary)', color: '#fff' }}>
                            Cita {citaPrellenada.numero}
                        </span>
                        <span style={{ color: 'var(--color-text)' }}>
                            <strong>
                                {citaPrellenada.cliente.razon_social
                                    ?? `${citaPrellenada.cliente.nombres ?? ''} ${citaPrellenada.cliente.apellidos ?? ''}`.trim()}
                            </strong>
                        </span>
                        {citaPrellenada.sujeto && citaPrellenada.sujeto_label && (
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                · {citaPrellenada.sujeto_label}: <strong>{citaPrellenada.sujeto}</strong>
                            </span>
                        )}
                        <span className="text-xs opacity-70" style={{ color: 'var(--color-text-muted)' }}>
                            · Carrito prellenado, puedes agregar/quitar antes de cobrar
                        </span>
                    </div>
                    <Link href={route('agenda.show', citaPrellenada.id)}
                        className="text-xs font-medium underline hover:opacity-80"
                        style={{ color: 'var(--color-primary)' }}>
                        Ver cita
                    </Link>
                </div>
            )}

            {/* ── Barra superior ─────────────────────────────────────────── */}
            <div
                className="flex items-center justify-between px-3 sm:px-4 py-2.5 flex-shrink-0"
                style={{
                    backgroundColor: 'var(--color-primary)',
                    color: '#fff',
                }}
            >
                <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                    <Link
                        href={route('dashboard')}
                        aria-label="Volver al dashboard"
                        className="flex items-center justify-center h-9 w-9 rounded-lg hover:bg-white/15 active:bg-white/25 transition-colors flex-shrink-0"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <div className="hidden sm:block w-px h-5 bg-white/20 flex-shrink-0" />
                    <div className="min-w-0">
                        <p className="font-bold text-sm leading-tight truncate">
                            <span className="sm:hidden">POS</span>
                            <span className="hidden sm:inline">POS · {turno.caja?.nombre ?? 'Caja'}</span>
                        </p>
                        <p className="hidden sm:block text-[10px] opacity-70 leading-tight">
                            Turno #{turno.id}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2 flex-shrink-0">
                    {/* Comprobante (solo desktop/tablet) */}
                    <div className="hidden sm:flex items-center gap-1.5">
                        <Receipt size={14} className="opacity-70" />
                        <select
                            value={tipoComprobante}
                            onChange={e => setTipoComprobante(e.target.value as TipoComprobante)}
                            className="text-xs bg-white/15 border-0 rounded-lg px-2 py-1.5 text-white focus:outline-none focus:ring-2 focus:ring-white/30"
                        >
                            <option value="ticket" className="text-gray-900">Sin comprobante</option>
                            <option value="boleta" className="text-gray-900">Boleta</option>
                            <option value="factura" className="text-gray-900">Factura</option>
                        </select>
                    </div>

                    {/* Cliente — siempre visible (incluso en móvil/PWA) */}
                    <button
                        onClick={() => setModalCliente(true)}
                        aria-label="Cambiar cliente"
                        className="flex items-center gap-2 text-xs px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 active:bg-white/30 transition-colors min-h-[36px] max-w-[180px] sm:max-w-[220px]"
                    >
                        <User size={14} className="flex-shrink-0" />
                        <span className="truncate font-medium text-[13px]">
                            {cliente
                                ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
                                : 'Cliente general'}
                        </span>
                        <ChevronDown size={12} className="opacity-60 flex-shrink-0" />
                    </button>
                </div>
            </div>

            {/* ── Contenido principal ─────────────────────────────────── */}
            <div className="flex flex-1 overflow-hidden relative">
                {/* ── Panel izquierdo: productos ──────────────────────── */}
                <div className="flex-1 flex flex-col overflow-hidden">
                    {/* Header de productos + buscador + categorías */}
                    <div className="px-3 sm:px-4 py-3 flex flex-col gap-2.5 flex-shrink-0" style={{ backgroundColor: 'var(--color-surface)', borderBottom: '1px solid var(--color-border)' }}>
                        <div className="flex items-center gap-2">
                            <Package size={16} style={{ color: 'var(--color-primary)' }} />
                            <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                                Productos
                            </span>
                            <span
                                className="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                    color: 'var(--color-primary)',
                                }}
                            >
                                {productosFiltrados.length}
                            </span>
                        </div>

                        <div className="relative">
                            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input
                                type="search"
                                inputMode="search"
                                enterKeyHint="search"
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                                placeholder="Buscar por nombre o código..."
                                autoFocus
                                autoComplete="off"
                                className="w-full pl-10 pr-3 py-2.5 text-sm border rounded-xl focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                } as React.CSSProperties}
                            />
                            {busqueda && (
                                <button
                                    onClick={() => setBusqueda('')}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 p-0.5 rounded hover:bg-black/5"
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    <X size={14} />
                                </button>
                            )}
                        </div>

                        {/* Chips de categorías */}
                        {categorias.length > 0 && (
                            <div className="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-hide">
                                <button
                                    onClick={() => setCategoriaActiva(null)}
                                    className="flex-shrink-0 text-xs font-medium px-3 py-1.5 rounded-full transition-all whitespace-nowrap"
                                    style={{
                                        backgroundColor: !categoriaActiva ? 'var(--color-primary)' : 'var(--color-bg)',
                                        color: !categoriaActiva ? '#fff' : 'var(--color-text-muted)',
                                        border: `1px solid ${!categoriaActiva ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                    }}
                                >
                                    <Layers size={11} className="inline mr-1" />
                                    Todos
                                </button>
                                {categorias.map(cat => (
                                    <button
                                        key={cat}
                                        onClick={() => setCategoriaActiva(cat === categoriaActiva ? null : cat)}
                                        className="flex-shrink-0 text-xs font-medium px-3 py-1.5 rounded-full transition-all whitespace-nowrap"
                                        style={{
                                            backgroundColor: categoriaActiva === cat ? 'var(--color-primary)' : 'var(--color-bg)',
                                            color: categoriaActiva === cat ? '#fff' : 'var(--color-text-muted)',
                                            border: `1px solid ${categoriaActiva === cat ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                        }}
                                    >
                                        {cat}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Grid de productos */}
                    <div
                        className="flex-1 overflow-y-auto px-3 sm:px-4 py-3 pb-[calc(96px+env(safe-area-inset-bottom,0px))] lg:pb-3"
                        style={{ overscrollBehavior: 'contain' }}
                    >
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2 sm:gap-3">
                            {productosFiltrados.map(producto => {
                                const enCarrito = carrito.find(i => i.producto_id === producto.id);
                                return (
                                    <button
                                        key={producto.id}
                                        onClick={() => agregarProducto(producto)}
                                        className="text-left p-3 sm:p-4 rounded-xl border transition-all hover:shadow-lg active:scale-[0.97] relative group"
                                        style={{
                                            backgroundColor: 'var(--color-surface)',
                                            borderColor: enCarrito ? 'var(--color-primary)' : 'var(--color-border)',
                                            boxShadow: enCarrito ? '0 0 0 1px var(--color-primary), 0 2px 8px rgba(26,115,200,0.1)' : '0 1px 3px rgba(0,0,0,0.05)',
                                        }}
                                    >
                                        {enCarrito && (
                                            <span
                                                className="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center text-white shadow-sm"
                                                style={{ backgroundColor: 'var(--color-primary)' }}
                                            >
                                                {enCarrito.cantidad}
                                            </span>
                                        )}
                                        <div className="flex items-start justify-between gap-1">
                                            <p className="text-sm font-semibold leading-snug line-clamp-2" style={{ color: 'var(--color-text)' }}>
                                                {producto.nombre}
                                            </p>
                                        </div>
                                        {producto.codigo && (
                                            <p className="text-[10px] font-mono mt-0.5 opacity-50" style={{ color: 'var(--color-text-muted)' }}>
                                                {producto.codigo}
                                            </p>
                                        )}
                                        <div className="flex items-center justify-between mt-2">
                                            <span
                                                className="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                                                style={{
                                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)',
                                                    color: 'var(--color-text-muted)',
                                                }}
                                            >
                                                {producto.categoria?.nombre ?? 'General'}
                                            </span>
                                            {(() => {
                                                const unidadesActivas = (producto.unidades ?? []).filter(u => u.activo !== false);
                                                if (unidadesActivas.length > 1) {
                                                    const precios = unidadesActivas.map(u => parseFloat(u.precio_venta));
                                                    const min = Math.min(...precios);
                                                    return (
                                                        <span className="text-sm font-bold flex items-center gap-1" style={{ color: 'var(--color-primary)' }}>
                                                            <span className="text-[9px] font-medium opacity-70 uppercase tracking-wider">desde</span>
                                                            S/ {min.toFixed(2)}
                                                        </span>
                                                    );
                                                }
                                                return (
                                                    <span className="text-sm font-bold" style={{ color: 'var(--color-primary)' }}>
                                                        S/ {parseFloat(
                                                            producto.unidad_base?.precio_venta
                                                            ?? producto.unidades?.find(u => u.es_base)?.precio_venta
                                                            ?? producto.precio_venta
                                                        ).toFixed(2)}
                                                    </span>
                                                );
                                            })()}
                                        </div>
                                    </button>
                                );
                            })}
                            {productosFiltrados.length === 0 && (
                                <div className="col-span-full flex flex-col items-center justify-center py-16 gap-3" style={{ color: 'var(--color-text-muted)' }}>
                                    <Package size={48} className="opacity-20" />
                                    <p className="text-sm">No se encontraron productos</p>
                                    {busqueda && (
                                        <button
                                            onClick={() => { setBusqueda(''); setCategoriaActiva(null); }}
                                            className="text-xs underline"
                                            style={{ color: 'var(--color-primary)' }}
                                        >
                                            Limpiar filtros
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Comprobante en móvil (debajo de productos) */}
                    <div className="sm:hidden px-3 py-2 flex-shrink-0" style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <div className="flex items-center gap-2">
                            <Receipt size={14} style={{ color: 'var(--color-text-muted)' }} />
                            <select
                                value={tipoComprobante}
                                onChange={e => setTipoComprobante(e.target.value as TipoComprobante)}
                                className="flex-1 text-xs border rounded-lg px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            >
                                <option value="ticket">Sin comprobante</option>
                                <option value="boleta">Boleta</option>
                                <option value="factura">Factura</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* ── Separador vertical (desktop) ───────────────────── */}
                <div className="w-px flex-shrink-0 hidden lg:block" style={{ backgroundColor: 'var(--color-border)' }} />

                {/* ── Panel derecho: carrito (desktop) ───────────────── */}
                <div
                    className="hidden lg:flex w-[400px] xl:w-[440px] flex-col overflow-hidden flex-shrink-0"
                    style={{ backgroundColor: 'var(--color-bg)' }}
                >
                    <CarritoPanel
                        carrito={carrito}
                        pagos={pagos}
                        conceptosDescuento={conceptosDescuento}
                        metodosPago={metodosPago}
                        cliente={cliente}
                        onAbrirCliente={() => setModalCliente(true)}
                        descuentoTotal={descuentoTotal}
                        descuentoConceptoId={descuentoConceptoId}
                        subtotal={subtotal}
                        igv={igv}
                        total={total}
                        tasaIgv={tasaIgv}
                        inactivosCount={itemsInactivos.length}
                        onCambiarCantidad={cambiarCantidad}
                        onAplicarDescuentoItem={aplicarDescuentoItem}
                        onEliminarItem={eliminarItem}
                        onLimpiarCarrito={limpiarCarrito}
                        onSetDescuento={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                        onSetPagos={setPagos}
                        onConfirmar={confirmarVenta}
                        puedeVender={puedeVender}
                        razonNoVender={razonNoVender}
                    />
                </div>

                {/* ── Drawer del carrito (móvil/tablet) ────────────────
                    En móvil: bottom-sheet (slide up) con drag handle.
                    En tablet: side drawer derecho. Ambos con safe-area. */}
                {carritoAbierto && (
                    <div className="lg:hidden fixed inset-0 z-50 flex">
                        <div
                            className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm animate-fade-in"
                            onClick={() => setCarritoAbierto(false)}
                        />
                        <div
                            className="
                                relative flex flex-col overflow-hidden
                                w-full bottom-sheet
                                mt-auto rounded-t-3xl
                                md:mt-0 md:ml-auto md:rounded-t-none md:rounded-l-3xl md:max-w-md md:h-full md:side-drawer
                            "
                            style={{
                                backgroundColor: 'var(--color-bg)',
                                maxHeight: '92dvh',
                                boxShadow: '0 -20px 50px -10px rgba(15,23,42,0.25)',
                            }}
                        >
                            {/* Drag handle (móvil) + Cerrar (tablet) */}
                            <div className="relative flex items-center justify-center flex-shrink-0 pt-2 pb-1 md:hidden">
                                <span
                                    className="block h-1.5 w-10 rounded-full"
                                    style={{ backgroundColor: 'var(--color-border)' }}
                                />
                            </div>
                            <button
                                onClick={() => setCarritoAbierto(false)}
                                aria-label="Cerrar carrito"
                                className="hidden md:flex absolute top-3 left-3 items-center justify-center w-10 h-10 rounded-lg z-10 hover:bg-black/5 transition-colors"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <X size={20} />
                            </button>
                            <CarritoPanel
                                carrito={carrito}
                                pagos={pagos}
                                conceptosDescuento={conceptosDescuento}
                                metodosPago={metodosPago}
                                cliente={cliente}
                                onAbrirCliente={() => setModalCliente(true)}
                                descuentoTotal={descuentoTotal}
                                descuentoConceptoId={descuentoConceptoId}
                                subtotal={subtotal}
                                igv={igv}
                                total={total}
                                tasaIgv={tasaIgv}
                                inactivosCount={itemsInactivos.length}
                                onCambiarCantidad={cambiarCantidad}
                                onAplicarDescuentoItem={aplicarDescuentoItem}
                                onEliminarItem={eliminarItem}
                                onLimpiarCarrito={limpiarCarrito}
                                onSetDescuento={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                                onSetPagos={setPagos}
                                onConfirmar={confirmarVenta}
                                puedeVender={puedeVender}
                                razonNoVender={razonNoVender}
                            />
                        </div>
                    </div>
                )}

                {/* ── Barra de acción inferior (móvil/tablet) ────────
                    Se mantiene siempre visible para que el pulgar la
                    alcance sin estirar la mano. Reemplaza al FAB de esquina. */}
                {!carritoAbierto && (
                    <button
                        onClick={() => setCarritoAbierto(true)}
                        className="lg:hidden fixed left-3 right-3 z-40 flex items-center justify-between gap-3 px-4 py-3.5 rounded-2xl shadow-2xl transition-all active:scale-[0.98]"
                        style={{
                            bottom: 'calc(env(safe-area-inset-bottom, 0px) + 12px)',
                            backgroundColor: 'var(--color-primary)',
                            color: '#fff',
                            boxShadow: '0 10px 40px -10px rgba(15,23,42,0.45), 0 4px 12px rgba(15,23,42,0.15)',
                        }}
                    >
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="relative flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/15">
                                <ShoppingCart size={18} />
                                {cantidadItems > 0 && (
                                    <span
                                        className="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-white text-[10px] font-bold flex items-center justify-center"
                                        style={{ color: 'var(--color-primary)' }}
                                    >
                                        {cantidadItems}
                                    </span>
                                )}
                            </div>
                            <div className="flex flex-col items-start leading-tight min-w-0">
                                <span className="text-[10px] font-medium uppercase tracking-wider opacity-80">
                                    {cantidadItems === 0 ? 'Carrito vacío' : `${cantidadItems} ${cantidadItems === 1 ? 'item' : 'items'} · Tocar para cobrar`}
                                </span>
                                <span className="text-base font-bold">S/ {total.toFixed(2)}</span>
                            </div>
                        </div>
                        <ChevronUp size={20} className="flex-shrink-0 opacity-80" />
                    </button>
                )}
            </div>

            {/* Modales */}
            <ModalClienteRapido
                isOpen={modalCliente}
                onClose={() => setModalCliente(false)}
                clientes={clientes}
                selected={cliente}
                onSelect={setCliente}
            />

            <ModalConfirmacionVenta
                isOpen={modalConfirm}
                onClose={() => setModalConfirm(false)}
                onConfirmar={submitVenta}
                loading={loading}
                items={carrito}
                pagos={pagos}
                cliente={cliente}
                descuentoTotal={descuentoTotal}
                descuentoConceptoId={descuentoConceptoId}
                tipoComprobante={tipoComprobante}
                subtotal={subtotal}
                igv={igv}
                total={total}
                metodosPago={metodosPago}
                conceptos={conceptosDescuento}
            />

            <ModalSelectorPresentacion
                isOpen={productoEnSeleccion !== null}
                onClose={() => setProductoEnSeleccion(null)}
                producto={productoEnSeleccion}
                onElegir={agregarConPresentacion}
            />

            {/* CSS para animaciones del drawer */}
            <style>{`
                @keyframes slideUp {
                    from { transform: translateY(100%); }
                    to { transform: translateY(0); }
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                .bottom-sheet {
                    animation: slideUp 0.32s cubic-bezier(0.32, 0.72, 0.0, 1);
                }
                @media (min-width: 768px) {
                    .side-drawer {
                        animation: slideInRight 0.32s cubic-bezier(0.32, 0.72, 0.0, 1);
                    }
                }
                .animate-fade-in {
                    animation: fadeIn 0.25s ease-out;
                }
                .scrollbar-hide::-webkit-scrollbar { display: none; }
                .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
                .line-clamp-2 {
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
            `}</style>
        </PosLayout>
    );
}

/* ─── Componente interno: Panel del carrito ─────────────────────────────────── */

interface CarritoPanelProps {
    carrito: LineaCarrito[];
    pagos: LineaPago[];
    conceptosDescuento: DescuentoConcepto[];
    metodosPago: (MetodoPago & { cuentas?: any[] })[];
    cliente: Cliente | null;
    onAbrirCliente: () => void;
    descuentoTotal: number;
    descuentoConceptoId: number | null;
    subtotal: number;
    igv: number;
    total: number;
    tasaIgv: number;
    inactivosCount: number;
    onCambiarCantidad: (key: string, delta: number) => void;
    onAplicarDescuentoItem: (key: string, desc: number, cid: number | null) => void;
    onEliminarItem: (key: string) => void;
    onLimpiarCarrito: () => void;
    onSetDescuento: (d: number, cid: number | null) => void;
    onSetPagos: (pagos: LineaPago[]) => void;
    onConfirmar: () => void;
    // A14: bandera de bloqueo del POS (admin sin local, almacén desactivado, etc.)
    puedeVender: boolean;
    razonNoVender: string | null;
}

function CarritoPanel({
    carrito, pagos, conceptosDescuento, metodosPago,
    cliente, onAbrirCliente,
    descuentoTotal, descuentoConceptoId,
    subtotal, igv, total, tasaIgv, inactivosCount,
    onCambiarCantidad, onAplicarDescuentoItem, onEliminarItem,
    onLimpiarCarrito, onSetDescuento, onSetPagos, onConfirmar,
    puedeVender, razonNoVender,
}: CarritoPanelProps) {
    const hayInactivos = inactivosCount > 0;

    const clienteNombre = cliente
        ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
        : 'Cliente general';
    const clienteDoc = cliente?.numero_documento
        ? `${cliente.tipo_documento ?? 'DOC'}: ${cliente.numero_documento}`
        : null;
    const clienteInicial = (clienteNombre || 'C').charAt(0).toUpperCase();
    const esClienteGeneral = !cliente
        || (cliente as Cliente & { es_cliente_general?: boolean }).es_cliente_general
        || cliente.numero_documento === '99999999';

    return (
        <>
            {/* Cabecera carrito */}
            <div
                className="flex items-center justify-between px-4 py-3 flex-shrink-0"
                style={{ borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
            >
                <div className="flex items-center gap-2">
                    <ShoppingCart size={16} style={{ color: 'var(--color-primary)' }} />
                    <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                        Carrito
                    </span>
                    {carrito.length > 0 && (
                        <span
                            className="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                color: 'var(--color-primary)',
                            }}
                        >
                            {carrito.reduce((s, i) => s + i.cantidad, 0)} items
                        </span>
                    )}
                </div>
                {carrito.length > 0 && (
                    <button
                        onClick={onLimpiarCarrito}
                        className="text-xs font-medium px-2 py-1 rounded-lg transition-colors hover:bg-red-50"
                        style={{ color: 'var(--color-danger)' }}
                    >
                        Limpiar
                    </button>
                )}
            </div>

            {/* Cliente activo — siempre visible mientras se revisa el carrito.
                Tappable para cambiarlo. Se distingue visualmente entre Cliente
                general (neutro) y un cliente identificado (acento primary). */}
            <button
                onClick={onAbrirCliente}
                className="flex items-center gap-3 px-4 py-2.5 w-full text-left flex-shrink-0 transition-colors hover:bg-black/5 active:bg-black/10"
                style={{
                    borderBottom: '1px solid var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                }}
            >
                <div
                    className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-bold"
                    style={{
                        backgroundColor: esClienteGeneral ? 'var(--color-text-muted)' : 'var(--color-primary)',
                    }}
                >
                    {esClienteGeneral ? <User size={16} /> : clienteInicial}
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                        Facturando a
                    </p>
                    <p className="text-sm font-semibold truncate leading-tight" style={{ color: 'var(--color-text)' }}>
                        {clienteNombre}
                    </p>
                    {clienteDoc && (
                        <p className="text-[11px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                            {clienteDoc}
                        </p>
                    )}
                </div>
                <ChevronDown size={14} className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }} />
            </button>

            {/* Banner persistente: items inactivos en la cita prellenada.
                Se mantiene visible mientras el cajero no resuelva los ítems
                (eliminándolos o pidiendo al admin reactivar el catálogo). */}
            {hayInactivos && (
                <div
                    className="mx-3 mt-3 mb-1 rounded-lg p-3 flex items-start gap-2"
                    style={{
                        backgroundColor: 'rgba(239,68,68,0.10)',
                        border: '1px solid var(--color-danger)',
                    }}
                >
                    <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" style={{ color: 'var(--color-danger)' }} />
                    <div className="text-xs leading-tight" style={{ color: 'var(--color-danger)' }}>
                        <p className="font-bold mb-0.5">
                            {inactivosCount} ítem(s) inactivo(s) en este carrito
                        </p>
                        <p className="opacity-90">
                            No podrás cobrar hasta que los elimines del carrito o pidas al administrador reactivar el producto/presentación.
                        </p>
                    </div>
                </div>
            )}

            {/* Lista de items */}
            <div className="flex-1 overflow-y-auto px-3 py-2">
                {carrito.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full gap-3 py-10" style={{ color: 'var(--color-text-muted)' }}>
                        <ShoppingCart size={48} className="opacity-15" />
                        <div className="text-center">
                            <p className="text-sm font-medium">Carrito vacío</p>
                            <p className="text-xs mt-0.5 opacity-70">Toca un producto para agregarlo</p>
                        </div>
                    </div>
                ) : (
                    carrito.map(item => (
                        <CarritoItem
                            key={item.key}
                            item={item}
                            conceptos={conceptosDescuento}
                            onCantidad={onCambiarCantidad}
                            onDescuento={onAplicarDescuentoItem}
                            onEliminar={onEliminarItem}
                        />
                    ))
                )}
            </div>

            {/* Footer del carrito */}
            <div
                className="flex-shrink-0 px-3 py-3 flex flex-col gap-3"
                style={{
                    borderTop: '2px solid var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                    paddingBottom: 'calc(12px + env(safe-area-inset-bottom, 0px))',
                }}
            >
                {/* Descuento global */}
                <PanelDescuento
                    descuentoTotal={descuentoTotal}
                    descuentoConceptoId={descuentoConceptoId}
                    conceptos={conceptosDescuento}
                    onChange={onSetDescuento}
                />

                {/* Pagos */}
                <PanelPago
                    pagos={pagos}
                    metodosPago={metodosPago}
                    total={total}
                    onChange={onSetPagos}
                />

                {/* Resumen financiero */}
                <div className="space-y-1 px-1">
                    <div className="flex justify-between text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        <span>Subtotal</span>
                        <span className="font-medium" style={{ color: 'var(--color-text)' }}>S/ {subtotal.toFixed(2)}</span>
                    </div>
                    {descuentoTotal > 0 && (
                        <div className="flex justify-between text-xs">
                            <span style={{ color: 'var(--color-text-muted)' }}>Descuento</span>
                            <span className="font-medium" style={{ color: 'var(--color-danger)' }}>-S/ {descuentoTotal.toFixed(2)}</span>
                        </div>
                    )}
                    <div className="flex justify-between text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        <span>IGV ({tasaIgv.toFixed(tasaIgv % 1 === 0 ? 0 : 2)}%)</span>
                        <span className="font-medium" style={{ color: 'var(--color-text)' }}>S/ {igv.toFixed(2)}</span>
                    </div>
                </div>

                {/* Total grande */}
                <div
                    className="flex items-center justify-between px-4 py-3 rounded-xl font-bold text-lg"
                    style={{
                        background: 'linear-gradient(135deg, var(--color-primary), var(--color-primary-hover))',
                        color: '#fff',
                        boxShadow: '0 4px 15px rgba(26,115,200,0.25)',
                    }}
                >
                    <span>TOTAL</span>
                    <span>S/ {total.toFixed(2)}</span>
                </div>

                {/* A14: banner rojo cuando el backend dice que no puede vender */}
                {!puedeVender && razonNoVender && (
                    <div
                        className="flex items-start gap-2 px-3 py-2 rounded-lg text-sm font-medium border"
                        style={{
                            background: '#fef2f2',
                            color: '#991b1b',
                            borderColor: '#fecaca',
                        }}
                    >
                        <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" />
                        <span>{razonNoVender}</span>
                    </div>
                )}

                {/* Botón cobrar */}
                <Button
                    variant="success"
                    size="lg"
                    radius="lg"
                    className="w-full !py-3 !text-base !font-bold"
                    onClick={onConfirmar}
                    disabled={carrito.length === 0 || hayInactivos || !puedeVender}
                    title={
                        !puedeVender ? (razonNoVender ?? 'No puedes registrar ventas en este momento.')
                        : hayInactivos ? 'Hay ítems inactivos en el carrito. Elimínalos para cobrar.'
                        : undefined
                    }
                >
                    {!puedeVender ? 'POS bloqueado'
                     : hayInactivos ? 'Resuelve ítems inactivos'
                     : 'Cobrar venta'}
                </Button>
            </div>
        </>
    );
}
