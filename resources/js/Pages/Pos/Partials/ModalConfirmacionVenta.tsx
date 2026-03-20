import React from 'react';
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
                    <Button variant="success" onClick={onConfirmar} loading={loading}>
                        Confirmar y guardar
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4 text-sm">
                {/* Cliente */}
                <div>
                    <span className="text-xs font-semibold uppercase" style={{ color: 'var(--color-text-muted)' }}>Cliente</span>
                    <p className="mt-0.5" style={{ color: 'var(--color-text)' }}>
                        {cliente
                            ? `${cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim()} (${cliente.numero_documento ?? '—'})`
                            : 'Cliente general'}
                    </p>
                </div>

                {/* Comprobante */}
                <div>
                    <span className="text-xs font-semibold uppercase" style={{ color: 'var(--color-text-muted)' }}>Comprobante</span>
                    <p className="mt-0.5 capitalize" style={{ color: 'var(--color-text)' }}>{tipoComprobante}</p>
                </div>

                {/* Items */}
                <div>
                    <span className="text-xs font-semibold uppercase" style={{ color: 'var(--color-text-muted)' }}>Productos</span>
                    <div className="mt-1 rounded-lg overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                        {items.map(item => (
                            <div
                                key={item.key}
                                className="flex justify-between px-3 py-2 text-xs"
                                style={{ borderBottom: '1px solid var(--color-border)', color: 'var(--color-text)' }}
                            >
                                <span className="truncate flex-1">{item.producto_nombre} × {item.cantidad} ({item.unidad_nombre})</span>
                                <span className="font-semibold ml-2">S/ {item.subtotal.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Totales */}
                <div
                    className="rounded-lg px-3 py-2 space-y-1"
                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                >
                    <div className="flex justify-between">
                        <span style={{ color: 'var(--color-text-muted)' }}>Subtotal</span>
                        <span style={{ color: 'var(--color-text)' }}>S/ {subtotal.toFixed(2)}</span>
                    </div>
                    {descuentoTotal > 0 && (
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                Descuento ({conceptos.find(c => c.id === descuentoConceptoId)?.nombre ?? 'global'})
                            </span>
                            <span className="text-red-500">-S/ {descuentoTotal.toFixed(2)}</span>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <span style={{ color: 'var(--color-text-muted)' }}>IGV (18%)</span>
                        <span style={{ color: 'var(--color-text)' }}>S/ {igv.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between font-bold text-base pt-1 border-t" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}>
                        <span>Total</span>
                        <span style={{ color: 'var(--color-primary)' }}>S/ {total.toFixed(2)}</span>
                    </div>
                </div>

                {/* Pagos */}
                <div>
                    <span className="text-xs font-semibold uppercase" style={{ color: 'var(--color-text-muted)' }}>Pagos</span>
                    <div className="mt-1 space-y-1">
                        {pagos.map(p => {
                            const metodo = metodosPago.find(m => m.id === p.metodo_pago_id);
                            return (
                                <div key={p.key} className="flex justify-between text-xs" style={{ color: 'var(--color-text)' }}>
                                    <span>{metodo?.nombre ?? '—'}{p.referencia && ` · ${p.referencia}`}</span>
                                    <span className="font-semibold">S/ {p.monto.toFixed(2)}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </Modal>
    );
}
