import React, { useEffect, useState } from 'react';
import { Trash2, Minus, Plus, Percent } from 'lucide-react';
import type { DescuentoConcepto } from '@/types';

export interface LineaCarrito {
    key:                  string;
    producto_id:          number;
    producto_unidad_id:   number;
    producto_nombre:      string;
    unidad_nombre:        string;
    precio_unitario:      number;
    precio_original:      number;
    cantidad:             number;
    descuento_item:       number;
    descuento_concepto_id: number | null;
    subtotal:             number;
}

interface Props {
    item:               LineaCarrito;
    conceptos:          DescuentoConcepto[];
    onCantidad:         (key: string, delta: number) => void;
    onDescuento:        (key: string, descuento: number, conceptoId: number | null) => void;
    onEliminar:         (key: string) => void;
}

export default function CarritoItem({ item, conceptos, onCantidad, onDescuento, onEliminar }: Props) {
    const [showDescuento, setShowDescuento] = useState(item.descuento_item > 0);
    const [descuentoVal, setDescuentoVal]   = useState(String(item.descuento_item || ''));
    const [conceptoId, setConceptoId]       = useState<number | null>(item.descuento_concepto_id);

    // Sincronizar estado local con props
    useEffect(() => {
        setDescuentoVal(String(item.descuento_item || ''));
        setConceptoId(item.descuento_concepto_id);
        if (item.descuento_item > 0) setShowDescuento(true);
    }, [item.descuento_item, item.descuento_concepto_id]);

    function aplicarDescuento() {
        const val = parseFloat(descuentoVal) || 0;
        onDescuento(item.key, val, val > 0 ? conceptoId : null);
        if (val === 0) setShowDescuento(false);
    }

    return (
        <div
            className="rounded-xl p-3 mb-2 transition-all"
            style={{
                backgroundColor: 'var(--color-surface)',
                border: '1px solid var(--color-border)',
                boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
            }}
        >
            {/* Fila principal */}
            <div className="flex items-center gap-3">
                {/* Info del producto */}
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>
                        {item.producto_nombre}
                    </p>
                    <div className="flex items-center gap-1.5 mt-0.5">
                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            {item.unidad_nombre}
                        </span>
                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>·</span>
                        <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>
                            S/ {item.precio_unitario.toFixed(2)}
                        </span>
                        {item.descuento_item > 0 && (
                            <>
                                <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>·</span>
                                <span className="text-xs font-medium" style={{ color: 'var(--color-danger)' }}>
                                    -{item.descuento_item.toFixed(2)}
                                </span>
                            </>
                        )}
                    </div>
                </div>

                {/* Controles cantidad */}
                <div className="flex items-center gap-0.5 rounded-lg overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                    <button
                        onClick={() => onCantidad(item.key, -1)}
                        className="p-1.5 transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Minus size={13} />
                    </button>
                    <span
                        className="text-sm font-bold min-w-[32px] text-center py-1"
                        style={{ color: 'var(--color-text)', backgroundColor: 'var(--color-bg)' }}
                    >
                        {item.cantidad}
                    </span>
                    <button
                        onClick={() => onCantidad(item.key, 1)}
                        className="p-1.5 transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        <Plus size={13} />
                    </button>
                </div>

                {/* Subtotal */}
                <span className="text-sm font-bold min-w-[70px] text-right" style={{ color: 'var(--color-primary)' }}>
                    S/ {item.subtotal.toFixed(2)}
                </span>

                {/* Eliminar */}
                <button
                    onClick={() => onEliminar(item.key)}
                    className="p-1.5 rounded-lg transition-colors hover:bg-red-50 group"
                    style={{ color: 'var(--color-text-muted)' }}
                >
                    <Trash2 size={14} className="group-hover:text-red-500 transition-colors" />
                </button>
            </div>

            {/* Descuento por item */}
            {showDescuento ? (
                <div
                    className="flex items-center gap-2 mt-2 pt-2"
                    style={{ borderTop: '1px dashed var(--color-border)' }}
                >
                    <Percent size={12} style={{ color: 'var(--color-warning)', flexShrink: 0 }} />
                    <div className="relative w-20">
                        <span className="absolute left-2 top-1/2 -translate-y-1/2 text-[10px]" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={descuentoVal}
                            onChange={e => setDescuentoVal(e.target.value)}
                            onBlur={aplicarDescuento}
                            onKeyDown={e => e.key === 'Enter' && aplicarDescuento()}
                            placeholder="0.00"
                            className="w-full pl-6 pr-1 py-1 text-xs border rounded-lg focus:outline-none focus:ring-1"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'var(--color-warning)',
                            } as React.CSSProperties}
                        />
                    </div>
                    <select
                        value={conceptoId ?? ''}
                        onChange={e => {
                            const newCid = e.target.value ? Number(e.target.value) : null;
                            setConceptoId(newCid);
                            const val = parseFloat(descuentoVal) || 0;
                            if (val > 0) onDescuento(item.key, val, newCid);
                        }}
                        className="flex-1 text-xs border rounded-lg px-1.5 py-1 focus:outline-none focus:ring-1"
                        style={{
                            borderColor: 'var(--color-border)',
                            backgroundColor: 'var(--color-bg)',
                            color: 'var(--color-text)',
                            '--tw-ring-color': 'var(--color-warning)',
                        } as React.CSSProperties}
                    >
                        <option value="">Concepto...</option>
                        {conceptos.map(c => (
                            <option key={c.id} value={c.id}>{c.nombre}</option>
                        ))}
                    </select>
                    <button
                        onClick={() => {
                            setDescuentoVal('');
                            setConceptoId(null);
                            onDescuento(item.key, 0, null);
                            setShowDescuento(false);
                        }}
                        className="p-1 rounded hover:bg-black/5 transition-colors"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Trash2 size={12} />
                    </button>
                </div>
            ) : (
                <button
                    onClick={() => setShowDescuento(true)}
                    className="flex items-center gap-1 text-[11px] mt-1.5 transition-colors hover:opacity-70"
                    style={{ color: 'var(--color-text-muted)' }}
                >
                    <Percent size={10} />
                    Descuento
                </button>
            )}
        </div>
    );
}
