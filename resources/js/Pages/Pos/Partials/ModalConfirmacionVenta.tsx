import React from 'react';
import { ShoppingBag, User, Receipt, CreditCard, CheckCircle2 } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import type { Cliente, DescuentoConcepto, MetodoPago } from '@/types';
import type { LineaCarrito } from './CarritoItem';
import type { LineaPago } from './PanelPago';

interface Props {
    isOpen:              boolean;
    onClose:             () => void;
    onConfirmar:         () => void;
    loading:             boolean;
    items:               LineaCarrito[];
    pagos:               LineaPago[];
    cliente:             Cliente | null;
    descuentoTotal:      number;
    descuentoConceptoId: number | null;
    tipoComprobante:     string;
    subtotal:            number;
    igv:                 number;
    total:               number;
    metodosPago:         MetodoPago[];
    conceptos:           DescuentoConcepto[];
}

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
    items, pagos, cliente, descuentoTotal, descuentoConceptoId, tipoComprobante,
    subtotal, igv, total, metodosPago, conceptos,
}: Props) {
    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Confirmar venta"
            size="lg"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={loading}>Cancelar</Button>
                    <Button
                        variant="success"
                        onClick={onConfirmar}
                        loading={loading}
                        startContent={!loading ? <CheckCircle2 size={16} /> : undefined}
                    >
                        Confirmar y guardar
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4 text-sm">
                {/* Cliente y Comprobante en fila */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div
                        className="rounded-xl p-3"
                        style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                    >
                        <SectionLabel icon={User} label="Cliente" />
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                            {cliente
                                ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
                                : 'Cliente general'}
                        </p>
                        {cliente?.numero_documento && (
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                {cliente.tipo_documento}: {cliente.numero_documento}
                            </p>
                        )}
                    </div>
                    <div
                        className="rounded-xl p-3"
                        style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                    >
                        <SectionLabel icon={Receipt} label="Comprobante" />
                        <p className="font-medium capitalize" style={{ color: 'var(--color-text)' }}>{tipoComprobante}</p>
                    </div>
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
                                </div>
                                <span className="font-bold ml-3 flex-shrink-0">S/ {item.subtotal.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>
                </div>

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
