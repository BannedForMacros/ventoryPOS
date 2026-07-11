import React, { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import { Trash2, Minus, Plus, Percent, X, AlertTriangle } from 'lucide-react';
import type { DescuentoConcepto } from '@/types';

export interface LineaCarrito {
    key:                  string;
    producto_id:          number;
    producto_unidad_id:   number;
    producto_nombre:      string;
    unidad_nombre:        string;
    precio_unitario:      number;
    precio_original:      number;
    // Piso del precio editable: costo de la presentación (costo de la unidad,
    // o costo base del producto × factor de conversión). 0 = sin costo definido,
    // en ese caso no se valida piso.
    costo_minimo:         number;
    cantidad:             number;
    descuento_item:       number;
    descuento_concepto_id: number | null;
    subtotal:             number;
    incluye_igv:          boolean;
    // Flags opcionales (solo presentes en items que vienen de cita prellenada).
    // Cuando true, la linea NO se puede vender y se renderiza marcada en rojo.
    inactivo?:            boolean;
    motivo_inactivo?:     string;
}

interface Props {
    item:               LineaCarrito;
    conceptos:          DescuentoConcepto[];
    onCantidad:         (key: string, delta: number) => void;
    onCantidadExacta:   (key: string, cantidad: number) => void;
    onPrecio:           (key: string, precio: number) => void;
    onDescuento:        (key: string, descuento: number, conceptoId: number | null) => void;
    onEliminar:         (key: string) => void;
}

export default function CarritoItem({ item, conceptos, onCantidad, onCantidadExacta, onPrecio, onDescuento, onEliminar }: Props) {
    const [showDescuento, setShowDescuento] = useState(item.descuento_item > 0);
    const [descuentoVal, setDescuentoVal]   = useState(String(item.descuento_item || ''));
    const [conceptoId, setConceptoId]       = useState<number | null>(item.descuento_concepto_id);
    // Borradores locales de cantidad y precio: se escriben libremente y se
    // aplican al carrito al salir del input (blur) o con Enter.
    const [cantidadVal, setCantidadVal]     = useState(String(item.cantidad));
    const [precioVal, setPrecioVal]         = useState(item.precio_unitario.toFixed(2));

    useEffect(() => {
        setDescuentoVal(String(item.descuento_item || ''));
        setConceptoId(item.descuento_concepto_id);
        if (item.descuento_item > 0) setShowDescuento(true);
    }, [item.descuento_item, item.descuento_concepto_id]);

    useEffect(() => { setCantidadVal(String(item.cantidad)); }, [item.cantidad]);
    useEffect(() => { setPrecioVal(item.precio_unitario.toFixed(2)); }, [item.precio_unitario]);

    function aplicarDescuento() {
        const val = parseFloat(descuentoVal) || 0;
        onDescuento(item.key, val, val > 0 ? conceptoId : null);
        if (val === 0) setShowDescuento(false);
    }

    function aplicarCantidad() {
        const val = parseFloat(cantidadVal);
        if (!isFinite(val) || val <= 0) {
            setCantidadVal(String(item.cantidad));
            return;
        }
        onCantidadExacta(item.key, val);
    }

    function aplicarPrecio() {
        const val = Math.round((parseFloat(precioVal) || 0) * 100) / 100;
        if (val <= 0) {
            setPrecioVal(item.precio_unitario.toFixed(2));
            return;
        }
        if (item.costo_minimo > 0 && val < item.costo_minimo - 0.009) {
            toast.error(
                `El precio de "${item.producto_nombre}" no puede ser menor al costo: S/ ${item.costo_minimo.toFixed(2)}.`,
                { duration: 4000 },
            );
            setPrecioVal(item.precio_unitario.toFixed(2));
            return;
        }
        onPrecio(item.key, val);
    }

    const esInactivo = !!item.inactivo;

    return (
        <div
            className="rounded-xl p-3 mb-2 transition-all"
            style={{
                backgroundColor: esInactivo ? 'rgba(239,68,68,0.06)' : 'var(--color-surface)',
                border: esInactivo
                    ? '1px solid var(--color-danger)'
                    : '1px solid var(--color-border)',
                boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
            }}
        >
            {esInactivo && (
                <div
                    className="flex items-start gap-2 mb-2 p-2 rounded-lg"
                    style={{ backgroundColor: 'rgba(239,68,68,0.10)' }}
                >
                    <AlertTriangle size={14} style={{ color: 'var(--color-danger)' }} className="flex-shrink-0 mt-0.5" />
                    <div className="text-[11px] leading-tight" style={{ color: 'var(--color-danger)' }}>
                        <p className="font-semibold">No se puede vender este ítem.</p>
                        <p className="opacity-90">
                            {item.motivo_inactivo ?? 'Producto o presentación desactivada desde que se agendó la cita.'}
                            {' '}Elimínalo del carrito o pide al admin reactivarlo.
                        </p>
                    </div>
                </div>
            )}

            {/* Fila 1: Nombre + Subtotal */}
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <p
                        className="text-sm font-semibold truncate"
                        style={{ color: esInactivo ? 'var(--color-danger)' : 'var(--color-text)' }}
                    >
                        {item.producto_nombre}
                        {esInactivo && <span className="ml-1 text-[10px] font-normal opacity-80">(inactivo)</span>}
                    </p>
                    <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                        <span className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                            {item.unidad_nombre}
                        </span>
                        <span className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>·</span>
                        {/* Precio editable: la cajera puede subirlo o bajarlo, pero nunca
                            por debajo del costo (validado aquí y en el backend). */}
                        <span className="inline-flex items-center gap-0.5 text-[11px] font-medium" style={{ color: 'var(--color-text-muted)' }}>
                            S/
                            <input
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="0.01"
                                value={precioVal}
                                onChange={e => setPrecioVal(e.target.value)}
                                onBlur={aplicarPrecio}
                                onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                                onFocus={e => e.target.select()}
                                aria-label="Precio de venta"
                                className="w-16 px-1 py-0.5 text-[11px] font-semibold border rounded-md text-right focus:outline-none focus:ring-1"
                                style={{
                                    borderColor: item.precio_unitario !== item.precio_original
                                        ? 'var(--color-warning)'
                                        : 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'var(--color-primary)',
                                } as React.CSSProperties}
                            />
                        </span>
                        {item.precio_unitario !== item.precio_original && (
                            <span className="text-[10px] line-through opacity-60" style={{ color: 'var(--color-text-muted)' }}>
                                S/ {item.precio_original.toFixed(2)}
                            </span>
                        )}
                        {item.descuento_item > 0 && (
                            <>
                                <span className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>·</span>
                                <span className="text-[11px] font-medium" style={{ color: 'var(--color-danger)' }}>
                                    -{item.descuento_item.toFixed(2)}
                                </span>
                            </>
                        )}
                    </div>
                </div>
                <span className="text-sm font-bold whitespace-nowrap" style={{ color: 'var(--color-primary)' }}>
                    S/ {item.subtotal.toFixed(2)}
                </span>
            </div>

            {/* Fila 2: Cantidad + Acciones */}
            <div className="flex items-center justify-between mt-2 gap-2">
                <div
                    className="flex items-center rounded-xl overflow-hidden select-none"
                    style={{ border: '1px solid var(--color-border)' }}
                >
                    <button
                        onClick={() => onCantidad(item.key, -1)}
                        aria-label="Disminuir cantidad"
                        className="flex items-center justify-center w-9 h-9 transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Minus size={15} />
                    </button>
                    {/* Cantidad editable: se puede teclear directo (soporta decimales
                        para productos por metro/kilo), ademas de los botones +/-. */}
                    <input
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="any"
                        value={cantidadVal}
                        onChange={e => setCantidadVal(e.target.value)}
                        onBlur={aplicarCantidad}
                        onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                        onFocus={e => e.target.select()}
                        aria-label="Cantidad"
                        className="text-sm font-bold w-14 text-center px-1 h-9 border-0 focus:outline-none focus:ring-1"
                        style={{
                            color: 'var(--color-text)',
                            backgroundColor: 'var(--color-bg)',
                            '--tw-ring-color': 'var(--color-primary)',
                        } as React.CSSProperties}
                    />
                    <button
                        onClick={() => onCantidad(item.key, 1)}
                        aria-label="Aumentar cantidad"
                        className="flex items-center justify-center w-9 h-9 transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        <Plus size={15} />
                    </button>
                </div>

                <div className="flex items-center gap-1">
                    {!showDescuento && (
                        <button
                            onClick={() => setShowDescuento(true)}
                            className="flex items-center gap-1 text-xs px-2.5 py-2 rounded-lg transition-colors hover:bg-black/5 active:bg-black/10"
                            style={{ color: 'var(--color-text-muted)' }}
                        >
                            <Percent size={12} />
                            Dcto
                        </button>
                    )}
                    <button
                        onClick={() => onEliminar(item.key)}
                        aria-label="Eliminar del carrito"
                        className="flex items-center justify-center w-9 h-9 rounded-lg transition-colors hover:bg-red-50 active:bg-red-100 group"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Trash2 size={15} className="group-hover:text-red-500 transition-colors" />
                    </button>
                </div>
            </div>

            {/* Fila 3: Descuento (expandible) */}
            {showDescuento && (
                <div
                    className="flex flex-wrap items-center gap-2 mt-2 pt-2"
                    style={{ borderTop: '1px dashed var(--color-border)' }}
                >
                    <Percent size={12} style={{ color: 'var(--color-warning)', flexShrink: 0 }} />
                    <div className="relative w-20 flex-shrink-0">
                        <span className="absolute left-2 top-1/2 -translate-y-1/2 text-[10px]" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                        <input
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            value={descuentoVal}
                            onChange={e => setDescuentoVal(e.target.value)}
                            onBlur={aplicarDescuento}
                            onKeyDown={e => e.key === 'Enter' && aplicarDescuento()}
                            placeholder="0.00"
                            className="w-full pl-6 pr-1 py-1.5 text-xs border rounded-lg focus:outline-none focus:ring-2"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'color-mix(in srgb, var(--color-warning) 40%, transparent)',
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
                        className="flex-1 min-w-[100px] text-xs border rounded-lg px-1.5 py-1 focus:outline-none focus:ring-1"
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
                        className="p-1 rounded hover:bg-black/5 transition-colors flex-shrink-0"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <X size={12} />
                    </button>
                </div>
            )}
        </div>
    );
}
