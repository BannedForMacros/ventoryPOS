import React from 'react';
import { Trash2, Minus, Plus } from 'lucide-react';
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
    const [showDescuento, setShowDescuento] = React.useState(item.descuento_item > 0);
    const [descuentoVal, setDescuentoVal]   = React.useState(String(item.descuento_item));
    const [conceptoId, setConceptoId]       = React.useState<number | null>(item.descuento_concepto_id);

    function aplicarDescuento() {
        const val = parseFloat(descuentoVal) || 0;
        onDescuento(item.key, val, val > 0 ? conceptoId : null);
        if (val === 0) setShowDescuento(false);
    }

    return (
        <div
            className="rounded-lg p-3 mb-2 flex flex-col gap-2"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
        >
            {/* Fila principal */}
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                        {item.producto_nombre}
                    </p>
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        {item.unidad_nombre} · S/ {item.precio_unitario.toFixed(2)}
                        {item.descuento_item > 0 && (
                            <span className="ml-1 text-red-500">-S/ {item.descuento_item.toFixed(2)}</span>
                        )}
                    </p>
                </div>

                {/* Controles cantidad */}
                <div className="flex items-center gap-1">
                    <button
                        onClick={() => onCantidad(item.key, -1)}
                        className="rounded p-0.5 hover:bg-black/10 transition-colors"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Minus size={14} />
                    </button>
                    <span className="text-sm font-semibold min-w-[28px] text-center" style={{ color: 'var(--color-text)' }}>
                        {item.cantidad}
                    </span>
                    <button
                        onClick={() => onCantidad(item.key, 1)}
                        className="rounded p-0.5 hover:bg-black/10 transition-colors"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Plus size={14} />
                    </button>
                </div>

                {/* Subtotal + eliminar */}
                <div className="flex items-center gap-2">
                    <span className="text-sm font-bold" style={{ color: 'var(--color-primary)' }}>
                        S/ {item.subtotal.toFixed(2)}
                    </span>
                    <button
                        onClick={() => onEliminar(item.key)}
                        className="rounded p-1 hover:text-red-500 transition-colors"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Trash2 size={14} />
                    </button>
                </div>
            </div>

            {/* Descuento por item */}
            {showDescuento ? (
                <div className="flex items-center gap-2 pt-1 border-t" style={{ borderColor: 'var(--color-border)' }}>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={descuentoVal}
                        onChange={e => setDescuentoVal(e.target.value)}
                        placeholder="Desc. S/"
                        className="w-24 text-xs border rounded px-2 py-1"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                    />
                    <select
                        value={conceptoId ?? ''}
                        onChange={e => setConceptoId(e.target.value ? Number(e.target.value) : null)}
                        className="flex-1 text-xs border rounded px-2 py-1"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                    >
                        <option value="">Concepto...</option>
                        {conceptos.map(c => (
                            <option key={c.id} value={c.id}>{c.nombre}</option>
                        ))}
                    </select>
                    <button
                        onClick={aplicarDescuento}
                        className="text-xs px-2 py-1 rounded font-medium"
                        style={{ backgroundColor: 'var(--color-primary)', color: '#fff' }}
                    >
                        OK
                    </button>
                </div>
            ) : (
                <button
                    onClick={() => setShowDescuento(true)}
                    className="text-xs self-start"
                    style={{ color: 'var(--color-text-muted)' }}
                >
                    + Añadir descuento
                </button>
            )}
        </div>
    );
}
