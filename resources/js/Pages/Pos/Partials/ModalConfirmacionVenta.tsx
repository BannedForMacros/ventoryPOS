import React from 'react';
import { ShoppingBag, User, Receipt, CreditCard, CheckCircle2, Truck, Printer, Package } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import type { Cliente, DescuentoConcepto, MetodoPago } from '@/types';
import type { LineaCarrito } from './CarritoItem';
import type { LineaPago } from './PanelPago';
import { etiquetaComprobante } from '@/lib/comprobanteElectronico';

interface Props {
    isOpen:              boolean;
    onClose:             () => void;
    // imprimir = true → crea la venta Y la imprime; false → solo la crea.
    onConfirmar:         (imprimir: boolean) => void;
    loading:             boolean;
    items:               LineaCarrito[];
    pagos:               LineaPago[];
    cliente:             Cliente | null;
    descuentoTotal:      number;
    descuentoConceptoId: number | null;
    tipoComprobante:     string;
    numeroComprobante?:  string;
    subtotal:            number;
    igv:                 number;
    total:               number;
    metodosPago:         MetodoPago[];
    conceptos:           DescuentoConcepto[];
    // Pendiente por entregar: pagó todo pero se lleva solo parte.
    entregaPendiente?:   boolean;
    pendienteDe?:        (item: LineaCarrito) => number;
    fechaEntrega?:       string;
    // Despacho en almacén: toda la venta queda pendiente de entrega.
    despachoAlmacen?:    boolean;
    // Monto cubierto con anticipo de efectivo del cliente.
    anticipoMonto?:      number;
}

function money(n: number) { return `S/ ${n.toFixed(2)}`; }

function SectionLabel({ icon: Icon, label }: { icon: React.ElementType; label: string }) {
    return (
        <div className="flex items-center gap-1.5 mb-2">
            <Icon size={13} style={{ color: 'var(--color-primary)' }} />
            <span className="text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                {label}
            </span>
        </div>
    );
}

export default function ModalConfirmacionVenta({
    isOpen, onClose, onConfirmar, loading,
    items, pagos, cliente, descuentoTotal, descuentoConceptoId, tipoComprobante, numeroComprobante = '',
    subtotal, igv, total, metodosPago, conceptos,
    entregaPendiente, pendienteDe, fechaEntrega, despachoAlmacen,
    anticipoMonto = 0,
}: Props) {
    const pendientes = entregaPendiente && pendienteDe
        ? items.map(i => ({ item: i, pendiente: pendienteDe(i) })).filter(p => p.pendiente > 0)
        : [];
    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Confirmar venta"
            size="lg"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={loading}>Cancelar</Button>
                    {/* Secundario: crea la venta Y la imprime. */}
                    <Button
                        variant="secondary"
                        onClick={() => onConfirmar(true)}
                        disabled={loading}
                        startContent={<Printer size={16} />}
                    >
                        Crear e imprimir
                    </Button>
                    {/* Principal (verde): SOLO crea la venta, sin imprimir. */}
                    <Button
                        variant="success"
                        onClick={() => onConfirmar(false)}
                        loading={loading}
                        startContent={!loading ? <CheckCircle2 size={16} /> : undefined}
                    >
                        Confirmar venta
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4 text-sm">
                {/* Cliente — destacado al tope con avatar */}
                {(() => {
                    const clienteNombre = cliente
                        ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
                        : 'Cliente general';
                    const esGeneral = !cliente
                        || (cliente as Cliente & { es_cliente_general?: boolean }).es_cliente_general
                        || cliente.numero_documento === '99999999';
                    const inicial = (clienteNombre || 'C').charAt(0).toUpperCase();
                    return (
                        <div
                            className="rounded-xl p-3 flex items-center gap-3"
                            style={{
                                backgroundColor: esGeneral
                                    ? 'var(--color-bg)'
                                    : 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))',
                                border: `1px solid ${esGeneral ? 'var(--color-border)' : 'color-mix(in srgb, var(--color-primary) 35%, transparent)'}`,
                            }}
                        >
                            <div
                                className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full text-white text-base font-bold shadow-sm"
                                style={{
                                    backgroundColor: esGeneral ? 'var(--color-text-muted)' : 'var(--color-primary)',
                                }}
                            >
                                {esGeneral ? <User size={18} /> : inicial}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p
                                    className="text-[10px] font-bold uppercase tracking-wider"
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    Facturando a
                                </p>
                                <p className="text-base font-bold truncate leading-tight" style={{ color: 'var(--color-text)' }}>
                                    {clienteNombre}
                                </p>
                                {cliente?.numero_documento && (
                                    <p className="text-xs mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>
                                        {cliente.tipo_documento}: {cliente.numero_documento}
                                    </p>
                                )}
                            </div>
                            <div
                                className="hidden sm:flex flex-col items-end flex-shrink-0 pl-3 ml-auto border-l"
                                style={{ borderColor: 'var(--color-border)' }}
                            >
                                <span
                                    className="text-[10px] font-bold uppercase tracking-wider"
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    Comprobante
                                </span>
                                <span className="text-sm font-semibold capitalize flex items-center gap-1.5" style={{ color: 'var(--color-text)' }}>
                                    <Receipt size={13} style={{ color: 'var(--color-primary)' }} />
                                    {etiquetaComprobante(tipoComprobante as any)}
                                    {numeroComprobante && (
                                        <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                            ({numeroComprobante})
                                        </span>
                                    )}
                                </span>
                            </div>
                        </div>
                    );
                })()}

                {/* Comprobante (en móvil, fila separada) */}
                <div
                    className="sm:hidden rounded-xl p-3 flex items-center justify-between"
                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                >
                    <span className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        <Receipt size={13} style={{ color: 'var(--color-primary)' }} />
                        Comprobante
                    </span>
                    <span className="font-semibold" style={{ color: 'var(--color-text)' }}>
                        {etiquetaComprobante(tipoComprobante as any)}
                        {numeroComprobante && (
                            <span className="text-xs font-normal ml-1" style={{ color: 'var(--color-text-muted)' }}>
                                ({numeroComprobante})
                            </span>
                        )}
                    </span>
                </div>

                {/* Items */}
                <div>
                    <SectionLabel icon={ShoppingBag} label={`Productos (${items.length})`} />
                    <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                        {items.map((item, idx) => (
                            <div
                                key={item.key}
                                className="flex items-center justify-between px-3 py-2.5 text-xs"
                                style={{
                                    borderBottom: idx < items.length - 1 ? '1px solid var(--color-border)' : undefined,
                                    backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                }}
                            >
                                <div className="flex-1 min-w-0">
                                    <span className="font-medium">{item.producto_nombre}</span>
                                    <span className="mx-1.5" style={{ color: 'var(--color-text-muted)' }}>×</span>
                                    <span className="font-bold">{item.cantidad}</span>
                                    <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>({item.unidad_nombre})</span>
                                    {item.descuento_item > 0 && (
                                        <span className="ml-1.5 text-[10px] font-medium" style={{ color: 'var(--color-danger)' }}>
                                            -S/{item.descuento_item.toFixed(2)}/u
                                        </span>
                                    )}
                                    {entregaPendiente && pendienteDe && pendienteDe(item) > 0 && (
                                        <span className="ml-1.5 text-[10px] font-bold" style={{ color: 'var(--color-warning)' }}>
                                            · queda {pendienteDe(item)} por entregar
                                        </span>
                                    )}
                                </div>
                                <span className="font-bold ml-3 flex-shrink-0">S/ {item.subtotal.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Pendiente por entregar: resumen de lo que NO se lleva hoy */}
                {pendientes.length > 0 && (
                    <div
                        className="rounded-xl p-3"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, var(--color-bg))',
                            border: '1px solid var(--color-warning)',
                        }}
                    >
                        <div className="flex items-center gap-1.5 mb-1.5">
                            <Truck size={14} style={{ color: 'var(--color-warning)' }} />
                            <span className="text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-warning)' }}>
                                Queda pendiente por entregar
                            </span>
                        </div>
                        <ul className="text-xs space-y-0.5" style={{ color: 'var(--color-text)' }}>
                            {pendientes.map(({ item, pendiente }) => (
                                <li key={item.key}>
                                    <strong>{pendiente}</strong> × {item.producto_nombre} ({item.unidad_nombre})
                                </li>
                            ))}
                        </ul>
                        <p className="text-[11px] mt-1.5" style={{ color: 'var(--color-text-muted)' }}>
                            {fechaEntrega
                                ? `Entrega estimada: ${new Date(fechaEntrega + 'T00:00:00').toLocaleDateString('es-PE')}. `
                                : ''}
                            Se registrará automáticamente en Finanzas → Anticipos; el stock pendiente sale del almacén al entregarse.
                        </p>
                    </div>
                )}

                {/* Despacho en almacén: resumen de lo que queda pendiente */}
                {despachoAlmacen && (
                    <div
                        className="rounded-xl p-3"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, var(--color-bg))',
                            border: '1px solid var(--color-primary)',
                        }}
                    >
                        <div className="flex items-center gap-1.5 mb-1.5">
                            <Package size={14} style={{ color: 'var(--color-primary)' }} />
                            <span className="text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-primary)' }}>
                                Despacho en almacén
                            </span>
                        </div>
                        <ul className="text-xs space-y-0.5" style={{ color: 'var(--color-text)' }}>
                            {items.map(item => (
                                <li key={item.key}>
                                    <strong>{item.cantidad}</strong> × {item.producto_nombre} ({item.unidad_nombre})
                                </li>
                            ))}
                        </ul>
                        <p className="text-[11px] mt-1.5" style={{ color: 'var(--color-text-muted)' }}>
                            {fechaEntrega
                                ? `Entrega estimada: ${new Date(fechaEntrega + 'T00:00:00').toLocaleDateString('es-PE')}. `
                                : ''}
                            El almacenero confirmará el despacho y recién ahí saldrá el stock del almacén.
                        </p>
                    </div>
                )}

                {/* Totales */}
                <div
                    className="rounded-xl p-3 space-y-1.5"
                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                >
                    <div className="flex justify-between text-xs">
                        <span style={{ color: 'var(--color-text-muted)' }}>Subtotal</span>
                        <span style={{ color: 'var(--color-text)' }}>S/ {subtotal.toFixed(2)}</span>
                    </div>
                    {descuentoTotal > 0 && (
                        <div className="flex justify-between text-xs">
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                Descuento
                                {descuentoConceptoId && (
                                    <span className="ml-1 text-[10px]">({conceptos.find(c => c.id === descuentoConceptoId)?.nombre})</span>
                                )}
                            </span>
                            <span className="font-medium" style={{ color: 'var(--color-danger)' }}>-S/ {descuentoTotal.toFixed(2)}</span>
                        </div>
                    )}
                    <div className="flex justify-between text-xs">
                        <span style={{ color: 'var(--color-text-muted)' }}>IGV (18%)</span>
                        <span style={{ color: 'var(--color-text)' }}>S/ {igv.toFixed(2)}</span>
                    </div>
                    <div
                        className="flex justify-between font-bold text-base pt-2 mt-1"
                        style={{ borderTop: '2px solid var(--color-border)', color: 'var(--color-text)' }}
                    >
                        <span>Total a cobrar</span>
                        <span style={{ color: 'var(--color-primary)' }}>S/ {total.toFixed(2)}</span>
                    </div>
                </div>

                {/* Pagos */}
                <div>
                    <SectionLabel icon={CreditCard} label="Métodos de pago" />
                    <div className="space-y-1.5">
                        {anticipoMonto > 0.009 && (
                            <div
                                className="flex justify-between text-xs px-3 py-2 rounded-lg"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 8%, var(--color-bg))', border: '1px solid var(--color-border)', color: 'var(--color-text)' }}
                            >
                                <span className="font-medium">Anticipo de cliente</span>
                                <span className="font-bold" style={{ color: 'var(--color-warning)' }}>{money(anticipoMonto)}</span>
                            </div>
                        )}
                        {pagos.map(p => {
                            const metodo = metodosPago.find(m => m.id === p.metodo_pago_id);
                            return (
                                <div
                                    key={p.key}
                                    className="flex justify-between text-xs px-3 py-2 rounded-lg"
                                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)', color: 'var(--color-text)' }}
                                >
                                    <span className="font-medium">
                                        {metodo?.nombre ?? '—'}
                                        {p.referencia && <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>· {p.referencia}</span>}
                                    </span>
                                    <span className="font-bold" style={{ color: 'var(--color-success)' }}>S/ {p.monto.toFixed(2)}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </Modal>
    );
}
