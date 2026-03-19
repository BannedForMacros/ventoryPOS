import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Search, ShoppingCart, User, X, ArrowLeft } from 'lucide-react';
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

type TipoComprobante = 'ninguno' | 'boleta' | 'factura';

function uid() { return Math.random().toString(36).slice(2); }

function calcularTotales(items: LineaCarrito[], descuentoTotal: number) {
    const subtotal = items.reduce((s, i) => s + i.subtotal, 0);
    const base     = Math.max(0, subtotal - descuentoTotal);
    const igv      = Math.round(base * 0.18 * 100) / 100;
    const total    = Math.round((base + igv) * 100) / 100;
    return { subtotal, igv, total };
}

export default function PosIndex({ turno, productos, clientes, metodosPago, conceptosDescuento, flash }: Props) {
    const [busqueda, setBusqueda]           = useState('');
    const [carrito, setCarrito]             = useState<LineaCarrito[]>([]);
    const [pagos, setPagos]                 = useState<LineaPago[]>([]);
    const [cliente, setCliente]             = useState<Cliente | null>(null);
    const [descuentoTotal, setDescuentoTotal]       = useState(0);
    const [descuentoConceptoId, setDescuentoConceptoId] = useState<number | null>(null);
    const [tipoComprobante, setTipoComprobante]     = useState<TipoComprobante>('ninguno');
    const [modalCliente, setModalCliente]   = useState(false);
    const [modalConfirm, setModalConfirm]   = useState(false);
    const [loading, setLoading]             = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const { subtotal, igv, total } = calcularTotales(carrito, descuentoTotal);

    const productosFiltrados = useMemo(() => {
        const q = busqueda.toLowerCase();
        if (!q) return productos;
        return productos.filter(p =>
            p.nombre.toLowerCase().includes(q) ||
            (p.codigo ?? '').toLowerCase().includes(q) ||
            (p.categoria?.nombre ?? '').toLowerCase().includes(q)
        );
    }, [busqueda, productos]);

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
            };
            setCarrito(prev => [...prev, item]);
        }
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
        setCliente(null);
        setDescuentoTotal(0);
        setDescuentoConceptoId(null);
        setTipoComprobante('ninguno');
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
            },
            onError: (errors) => {
                setLoading(false);
                const msg = Object.values(errors)[0];
                if (msg) toast.error(msg as string);
            },
        });
    }

    return (
        <PosLayout>
            {/* Barra superior */}
            <div
                className="flex items-center justify-between px-4 py-2 flex-shrink-0"
                style={{ backgroundColor: 'var(--color-surface)', borderBottom: '1px solid var(--color-border)' }}
            >
                <div className="flex items-center gap-3">
                    <Link href={route('dashboard')} className="p-1.5 rounded hover:bg-black/5 transition-colors" style={{ color: 'var(--color-text-muted)' }}>
                        <ArrowLeft size={18} />
                    </Link>
                    <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                        POS · {turno.caja?.nombre ?? 'Caja'}
                    </span>
                </div>

                <div className="flex items-center gap-2">
                    {/* Tipo de comprobante */}
                    <select
                        value={tipoComprobante}
                        onChange={e => setTipoComprobante(e.target.value as TipoComprobante)}
                        className="text-sm border rounded-lg px-2 py-1.5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                    >
                        <option value="ninguno">Sin comprobante</option>
                        <option value="boleta">Boleta</option>
                        <option value="factura">Factura</option>
                    </select>

                    {/* Selector de cliente */}
                    <button
                        onClick={() => setModalCliente(true)}
                        className="flex items-center gap-2 text-sm px-3 py-1.5 rounded-lg border transition-colors hover:bg-black/5"
                        style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}
                    >
                        <User size={15} />
                        <span className="max-w-[140px] truncate">
                            {cliente ? `${cliente.nombre} ${cliente.apellido ?? ''}` : 'Cliente general'}
                        </span>
                    </button>
                </div>
            </div>

            {/* Contenido principal */}
            <div className="flex flex-1 overflow-hidden">
                {/* Panel izquierdo: productos */}
                <div className="flex-1 flex flex-col overflow-hidden">
                    {/* Buscador */}
                    <div className="p-3 flex-shrink-0">
                        <div className="relative">
                            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input
                                type="text"
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                                placeholder="Buscar producto..."
                                className="w-full pl-9 pr-3 py-2 text-sm border rounded-lg"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            />
                        </div>
                    </div>

                    {/* Grid de productos */}
                    <div className="flex-1 overflow-y-auto px-3 pb-3">
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                            {productosFiltrados.map(producto => (
                                <button
                                    key={producto.id}
                                    onClick={() => agregarProducto(producto)}
                                    className="text-left p-3 rounded-lg border transition-all hover:shadow-md hover:border-primary active:scale-95"
                                    style={{
                                        backgroundColor: 'var(--color-surface)',
                                        borderColor: 'var(--color-border)',
                                        '--color-primary-hover': 'var(--color-primary)',
                                    } as React.CSSProperties}
                                >
                                    <p className="text-sm font-medium leading-snug truncate" style={{ color: 'var(--color-text)' }}>
                                        {producto.nombre}
                                    </p>
                                    <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                        {producto.categoria?.nombre ?? '—'}
                                    </p>
                                    <p className="text-sm font-bold mt-1" style={{ color: 'var(--color-primary)' }}>
                                        S/ {parseFloat(producto.precio_venta).toFixed(2)}
                                    </p>
                                </button>
                            ))}
                            {productosFiltrados.length === 0 && (
                                <div className="col-span-full text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    No se encontraron productos
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Separador vertical */}
                <div className="w-px flex-shrink-0" style={{ backgroundColor: 'var(--color-border)' }} />

                {/* Panel derecho: carrito */}
                <div className="w-80 xl:w-96 flex flex-col overflow-hidden" style={{ backgroundColor: 'var(--color-bg)' }}>
                    {/* Cabecera carrito */}
                    <div
                        className="flex items-center justify-between px-4 py-3 flex-shrink-0"
                        style={{ borderBottom: '1px solid var(--color-border)' }}
                    >
                        <div className="flex items-center gap-2">
                            <ShoppingCart size={16} style={{ color: 'var(--color-primary)' }} />
                            <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                                Carrito ({carrito.length})
                            </span>
                        </div>
                        {carrito.length > 0 && (
                            <button onClick={limpiarCarrito} className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Limpiar
                            </button>
                        )}
                    </div>

                    {/* Lista de items */}
                    <div className="flex-1 overflow-y-auto px-3 py-2">
                        {carrito.length === 0 ? (
                            <div className="flex flex-col items-center justify-center h-full gap-2 py-10" style={{ color: 'var(--color-text-muted)' }}>
                                <ShoppingCart size={36} className="opacity-30" />
                                <p className="text-sm">El carrito está vacío</p>
                            </div>
                        ) : (
                            carrito.map(item => (
                                <CarritoItem
                                    key={item.key}
                                    item={item}
                                    conceptos={conceptosDescuento}
                                    onCantidad={cambiarCantidad}
                                    onDescuento={aplicarDescuentoItem}
                                    onEliminar={eliminarItem}
                                />
                            ))
                        )}
                    </div>

                    {/* Footer del carrito */}
                    <div
                        className="flex-shrink-0 px-3 py-3 flex flex-col gap-3"
                        style={{ borderTop: '1px solid var(--color-border)' }}
                    >
                        {/* Descuento global */}
                        <PanelDescuento
                            descuentoTotal={descuentoTotal}
                            descuentoConceptoId={descuentoConceptoId}
                            conceptos={conceptosDescuento}
                            onChange={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                        />

                        {/* Pagos */}
                        <PanelPago
                            pagos={pagos}
                            metodosPago={metodosPago}
                            total={total}
                            onChange={setPagos}
                        />

                        {/* Totales rápidos */}
                        <div
                            className="flex items-center justify-between px-3 py-2 rounded-lg font-bold"
                            style={{ backgroundColor: 'var(--color-primary)', color: '#fff' }}
                        >
                            <span>TOTAL</span>
                            <span>S/ {total.toFixed(2)}</span>
                        </div>

                        {/* Botón cobrar */}
                        <Button
                            variant="success"
                            size="lg"
                            className="w-full"
                            onClick={confirmarVenta}
                            disabled={carrito.length === 0}
                        >
                            Cobrar
                        </Button>
                    </div>
                </div>
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
        </PosLayout>
    );
}
