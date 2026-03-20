import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Search, ShoppingCart, User, X, ArrowLeft, ChevronDown,
    Package, Receipt, Layers,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import Button from '@/Components/UI/Button';
import CarritoItem, { LineaCarrito } from './Partials/CarritoItem';
import PanelPago, { LineaPago } from './Partials/PanelPago';
import PanelDescuento from './Partials/PanelDescuento';
import ModalClienteRapido from './Partials/ModalClienteRapido';
import ModalConfirmacionVenta from './Partials/ModalConfirmacionVenta';
import type { Cliente, DescuentoConcepto, MetodoPago, Cuenta, Producto, Turno, PageProps } from '@/types';

interface MetodoPagoConCuentas extends MetodoPago { cuentas?: Cuenta[]; }

interface Props extends PageProps {
    turno:              Turno;
    productos:          Producto[];
    clientes:           Cliente[];
    metodosPago:        MetodoPagoConCuentas[];
    conceptosDescuento: DescuentoConcepto[];
}

type TipoComprobante = 'ticket' | 'boleta' | 'factura';

function uid() { return Math.random().toString(36).slice(2); }

function calcularTotales(items: LineaCarrito[], descuentoTotal: number) {
    const subtotal  = items.reduce((s, i) => s + i.subtotal, 0);
    const baseItems = items.reduce((s, i) =>
        s + (i.incluye_igv ? i.subtotal / 1.18 : i.subtotal), 0);
    const base  = Math.max(0, baseItems - descuentoTotal);
    const igv   = Math.round(base * 0.18 * 100) / 100;
    const total = Math.round((base + igv) * 100) / 100;
    return { subtotal, igv, total };
}

export default function PosIndex({ turno, productos, clientes, metodosPago, conceptosDescuento, flash }: Props) {
    const clienteGeneral = clientes.find(c => c.numero_documento === '99999999') ?? null;

    const [busqueda, setBusqueda]           = useState('');
    const [carrito, setCarrito]             = useState<LineaCarrito[]>([]);
    const [pagos, setPagos]                 = useState<LineaPago[]>([]);
    const [cliente, setCliente]             = useState<Cliente | null>(clienteGeneral);
    const [descuentoTotal, setDescuentoTotal]       = useState(0);
    const [descuentoConceptoId, setDescuentoConceptoId] = useState<number | null>(null);
    const [tipoComprobante, setTipoComprobante]     = useState<TipoComprobante>('ticket');
    const [modalCliente, setModalCliente]   = useState(false);
    const [modalConfirm, setModalConfirm]   = useState(false);
    const [loading, setLoading]             = useState(false);
    const [carritoAbierto, setCarritoAbierto] = useState(false);
    const [categoriaActiva, setCategoriaActiva] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const { subtotal, igv, total } = calcularTotales(carrito, descuentoTotal);

    // Auto-agregar pago en efectivo por defecto cuando hay items y no hay pagos
    const efectivo = metodosPago.find(m => m.tipo === 'efectivo');
    useEffect(() => {
        if (carrito.length > 0 && pagos.length === 0 && efectivo) {
            setPagos([{
                key:                   uid(),
                metodo_pago_id:        efectivo.id,
                cuenta_metodo_pago_id: null,
                monto:                 parseFloat(total.toFixed(2)),
                referencia:            '',
                es_efectivo:           true,
            }]);
        }
    }, [carrito.length]);

    // Auto-actualizar monto del pago si es el único (efectivo por defecto)
    useEffect(() => {
        if (pagos.length === 1 && pagos[0].es_efectivo && total > 0) {
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

    function agregarProducto(producto: Producto) {
        const unidadBase = producto.unidades?.find(u => u.es_base) ?? producto.unidades?.[0];
        if (!unidadBase) { toast.error('El producto no tiene unidades configuradas.'); return; }

        const key = `${producto.id}-${unidadBase.id}`;
        const existente = carrito.find(i => i.key === key);

        if (existente) {
            cambiarCantidad(key, 1);
        } else {
            const precio = parseFloat(unidadBase.precio_venta);
            const item: LineaCarrito = {
                key,
                producto_id:          producto.id,
                producto_unidad_id:   unidadBase.id,
                producto_nombre:      producto.nombre,
                unidad_nombre:        unidadBase.unidad_medida?.nombre ?? '',
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

        // Feedback visual
        toast.success(`${producto.nombre} agregado`, { duration: 1000, icon: '🛒' });
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

    function confirmarVenta() {
        if (carrito.length === 0) { toast.error('El carrito está vacío.'); return; }
        if (pagos.length === 0) { toast.error('Agrega al menos un método de pago.'); return; }
        const totalPagado = pagos.reduce((s, p) => s + p.monto, 0);
        if (totalPagado < total - 0.009) { toast.error(`Faltan S/ ${(total - totalPagado).toFixed(2)} por cubrir.`); return; }
        setModalConfirm(true);
    }

    function submitVenta() {
        setLoading(true);

        const payload = {
            cliente_id:            cliente?.id ?? null,
            tipo_comprobante:      tipoComprobante,
            descuento_total:       descuentoTotal,
            descuento_concepto_id: descuentoConceptoId,
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
                es_efectivo:           p.es_efectivo,
            })),
        };

        router.post(route('ventas.store'), payload as any, {
            onSuccess: () => {
                setLoading(false);
                setModalConfirm(false);
                limpiarCarrito();
                setCarritoAbierto(false);
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
            {/* ── Barra superior ─────────────────────────────────────────── */}
            <div
                className="flex items-center justify-between px-3 sm:px-4 py-2.5 flex-shrink-0"
                style={{
                    backgroundColor: 'var(--color-primary)',
                    color: '#fff',
                }}
            >
                <div className="flex items-center gap-2 sm:gap-3">
                    <Link
                        href={route('dashboard')}
                        className="p-1.5 rounded-lg hover:bg-white/15 transition-colors"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <div className="hidden sm:block w-px h-5 bg-white/20" />
                    <div>
                        <p className="font-bold text-sm leading-tight">
                            POS · {turno.caja?.nombre ?? 'Caja'}
                        </p>
                        <p className="text-[10px] opacity-70 leading-tight">
                            Turno #{turno.id}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {/* Comprobante */}
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

                    {/* Cliente */}
                    <button
                        onClick={() => setModalCliente(true)}
                        className="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 transition-colors"
                    >
                        <User size={14} />
                        <span className="hidden sm:inline max-w-[120px] truncate">
                            {cliente
                                ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
                                : 'General'}
                        </span>
                        <ChevronDown size={12} className="opacity-60" />
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
                                type="text"
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                                placeholder="Buscar por nombre o código..."
                                autoFocus
                                className="w-full pl-10 pr-3 py-2 text-sm border rounded-xl focus:outline-none focus:ring-2"
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
                    <div className="flex-1 overflow-y-auto px-3 sm:px-4 py-3">
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
                                            <span className="text-sm font-bold" style={{ color: 'var(--color-primary)' }}>
                                                S/ {parseFloat(
                                                    producto.unidad_base?.precio_venta
                                                    ?? producto.unidades?.find(u => u.es_base)?.precio_venta
                                                    ?? producto.precio_venta
                                                ).toFixed(2)}
                                            </span>
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
                        descuentoTotal={descuentoTotal}
                        descuentoConceptoId={descuentoConceptoId}
                        subtotal={subtotal}
                        igv={igv}
                        total={total}
                        onCambiarCantidad={cambiarCantidad}
                        onAplicarDescuentoItem={aplicarDescuentoItem}
                        onEliminarItem={eliminarItem}
                        onLimpiarCarrito={limpiarCarrito}
                        onSetDescuento={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                        onSetPagos={setPagos}
                        onConfirmar={confirmarVenta}
                    />
                </div>

                {/* ── Drawer del carrito (móvil/tablet) ──────────────── */}
                {carritoAbierto && (
                    <div className="lg:hidden fixed inset-0 z-50 flex">
                        {/* Overlay */}
                        <div
                            className="absolute inset-0 bg-black/40 transition-opacity"
                            onClick={() => setCarritoAbierto(false)}
                        />
                        {/* Panel */}
                        <div
                            className="relative ml-auto w-full max-w-md flex flex-col animate-slide-in-right"
                            style={{ backgroundColor: 'var(--color-bg)' }}
                        >
                            {/* Botón cerrar */}
                            <button
                                onClick={() => setCarritoAbierto(false)}
                                className="absolute top-3 left-3 p-1.5 rounded-lg z-10 hover:bg-black/5 transition-colors"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <X size={20} />
                            </button>
                            <CarritoPanel
                                carrito={carrito}
                                pagos={pagos}
                                conceptosDescuento={conceptosDescuento}
                                metodosPago={metodosPago}
                                descuentoTotal={descuentoTotal}
                                descuentoConceptoId={descuentoConceptoId}
                                subtotal={subtotal}
                                igv={igv}
                                total={total}
                                onCambiarCantidad={cambiarCantidad}
                                onAplicarDescuentoItem={aplicarDescuentoItem}
                                onEliminarItem={eliminarItem}
                                onLimpiarCarrito={limpiarCarrito}
                                onSetDescuento={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                                onSetPagos={setPagos}
                                onConfirmar={confirmarVenta}
                            />
                        </div>
                    </div>
                )}

                {/* ── FAB del carrito (móvil/tablet) ─────────────────── */}
                {!carritoAbierto && (
                    <button
                        onClick={() => setCarritoAbierto(true)}
                        className="lg:hidden fixed bottom-4 right-4 z-40 flex items-center gap-2 pl-4 pr-5 py-3 rounded-2xl shadow-2xl transition-all active:scale-95"
                        style={{
                            backgroundColor: 'var(--color-primary)',
                            color: '#fff',
                            boxShadow: '0 8px 30px rgba(26,115,200,0.35)',
                        }}
                    >
                        <div className="relative">
                            <ShoppingCart size={20} />
                            {cantidadItems > 0 && (
                                <span className="absolute -top-2 -right-2 w-4 h-4 rounded-full bg-white text-[10px] font-bold flex items-center justify-center"
                                    style={{ color: 'var(--color-primary)' }}>
                                    {cantidadItems}
                                </span>
                            )}
                        </div>
                        <span className="font-bold text-sm">
                            S/ {total.toFixed(2)}
                        </span>
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

            {/* CSS para animación del drawer */}
            <style>{`
                @keyframes slideInRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                .animate-slide-in-right {
                    animation: slideInRight 0.25s ease-out;
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
    descuentoTotal: number;
    descuentoConceptoId: number | null;
    subtotal: number;
    igv: number;
    total: number;
    onCambiarCantidad: (key: string, delta: number) => void;
    onAplicarDescuentoItem: (key: string, desc: number, cid: number | null) => void;
    onEliminarItem: (key: string) => void;
    onLimpiarCarrito: () => void;
    onSetDescuento: (d: number, cid: number | null) => void;
    onSetPagos: (pagos: LineaPago[]) => void;
    onConfirmar: () => void;
}

function CarritoPanel({
    carrito, pagos, conceptosDescuento, metodosPago,
    descuentoTotal, descuentoConceptoId,
    subtotal, igv, total,
    onCambiarCantidad, onAplicarDescuentoItem, onEliminarItem,
    onLimpiarCarrito, onSetDescuento, onSetPagos, onConfirmar,
}: CarritoPanelProps) {
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
                style={{ borderTop: '2px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
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
                        <span>IGV (18%)</span>
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

                {/* Botón cobrar */}
                <Button
                    variant="success"
                    size="lg"
                    radius="lg"
                    className="w-full !py-3 !text-base !font-bold"
                    onClick={onConfirmar}
                    disabled={carrito.length === 0}
                >
                    Cobrar venta
                </Button>
            </div>
        </>
    );
}
