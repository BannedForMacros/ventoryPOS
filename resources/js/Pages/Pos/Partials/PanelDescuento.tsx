import React, { useEffect, useRef, useState } from 'react';
import { Percent, X } from 'lucide-react';
import type { DescuentoConcepto } from '@/types';
import type { DescTipo } from './CarritoItem';

interface Props {
    descuentoTotal:       number;   // descuento aplicado, SIEMPRE en soles
    descuentoConceptoId:  number | null;
    // Base sobre la que se calcula el % (el subtotal del carrito). Permite que
    // un descuento en % siga vivo si el carrito cambia después de tecJEarlo.
    base:                 number;
    conceptos:            DescuentoConcepto[];
    onChange:             (descuentoSoles: number, conceptoId: number | null) => void;
}

export default function PanelDescuento({ descuentoTotal, descuentoConceptoId, base, conceptos, onChange }: Props) {
    // `val` es lo que teclea el usuario en la unidad del tipo elegido: soles si
    // tipo='monto', porcentaje si tipo='porcentaje'. El padre siempre recibe soles.
    const [val, setVal]     = useState(String(descuentoTotal || ''));
    const [tipo, setTipo]   = useState<DescTipo>('monto');
    const [cid, setCid]     = useState<number | null>(descuentoConceptoId);
    const [abierto, setAbierto] = useState(descuentoTotal > 0);
    const [focused, setFocused] = useState(false);

    // Convierte el valor tecleado (según el tipo) a soles.
    function solesDe(v: number, t: DescTipo): number {
        if (!v || v <= 0) return 0;
        const s = t === 'porcentaje' ? base * (v / 100) : v;
        return Math.round(s * 100) / 100;
    }

    // Sincronizar con el padre. En modo % NO reescribimos `val` desde
    // `descuentoTotal` (que son soles) para no pisar el porcentaje tecleado.
    useEffect(() => {
        setCid(descuentoConceptoId);
        if (descuentoTotal === 0) {
            if (!focused) { setVal(''); setTipo('monto'); setAbierto(false); }
        } else if (tipo === 'monto' && !focused) {
            setVal(String(descuentoTotal));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [descuentoTotal, descuentoConceptoId]);

    // Si el carrito cambia y el descuento es en %, recomputar los soles.
    const prevBase = useRef(base);
    useEffect(() => {
        if (prevBase.current !== base && tipo === 'porcentaje') {
            const v = parseFloat(val) || 0;
            if (v > 0) {
                const soles = solesDe(v, 'porcentaje');
                if (Math.abs(soles - descuentoTotal) > 0.005) onChange(soles, cid);
            }
        }
        prevBase.current = base;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [base]);

    function emitir(valStr: string, t: DescTipo, conceptoId: number | null) {
        const soles = solesDe(parseFloat(valStr) || 0, t);
        onChange(soles, soles > 0 ? conceptoId : null);
    }

    function onCambioVal(v: string) {
        setVal(v);
        emitir(v, tipo, cid);
    }

    function cambiarTipo(t: DescTipo) {
        setTipo(t);
        emitir(val, t, cid);
    }

    function cambiarConcepto(conceptoId: number | null) {
        setCid(conceptoId);
        if ((parseFloat(val) || 0) > 0) emitir(val, tipo, conceptoId);
    }

    function quitar() {
        setVal('');
        setCid(null);
        setTipo('monto');
        onChange(0, null);
        setAbierto(false);
    }

    if (!abierto && descuentoTotal === 0) {
        return (
            <button
                onClick={() => setAbierto(true)}
                className="flex items-center gap-1.5 text-xs font-medium py-2 px-3 rounded-lg transition-colors hover:opacity-80 w-full justify-center"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, transparent)',
                    color: 'var(--color-warning)',
                    border: '1px dashed color-mix(in srgb, var(--color-warning) 40%, transparent)',
                }}
            >
                <Percent size={13} />
                Agregar descuento global
            </button>
        );
    }

    return (
        <div
            className="rounded-lg p-3 space-y-2"
            style={{
                backgroundColor: 'color-mix(in srgb, var(--color-warning) 5%, var(--color-surface))',
                border: '1px solid color-mix(in srgb, var(--color-warning) 25%, transparent)',
            }}
        >
            <div className="flex items-center justify-between">
                <p className="text-xs font-semibold flex items-center gap-1.5" style={{ color: 'var(--color-warning)' }}>
                    <Percent size={12} />
                    Descuento global
                </p>
                <button
                    onClick={quitar}
                    className="p-0.5 rounded hover:bg-black/10 transition-colors"
                    style={{ color: 'var(--color-text-muted)' }}
                >
                    <X size={14} />
                </button>
            </div>

            <div className="flex gap-2 items-center">
                {/* Tipo: soles o porcentaje */}
                <div className="inline-flex rounded-lg overflow-hidden flex-shrink-0" style={{ border: '1px solid var(--color-border)' }}>
                    {(['monto', 'porcentaje'] as DescTipo[]).map(t => (
                        <button
                            key={t}
                            onClick={() => cambiarTipo(t)}
                            className="text-xs font-bold px-3 py-1.5 transition-colors"
                            title={t === 'monto' ? 'Descuento en soles' : 'Descuento en porcentaje'}
                            style={{
                                backgroundColor: tipo === t ? 'var(--color-warning)' : 'transparent',
                                color: tipo === t ? '#fff' : 'var(--color-text-muted)',
                            }}
                        >
                            {t === 'monto' ? 'S/' : '%'}
                        </button>
                    ))}
                </div>

                {/* Valor */}
                <div className="relative w-24 flex-shrink-0">
                    {tipo === 'monto' && (
                        <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                    )}
                    <input
                        type="number"
                        min="0"
                        step={tipo === 'porcentaje' ? '0.1' : '0.01'}
                        max={tipo === 'porcentaje' ? '100' : undefined}
                        value={val}
                        onChange={e => onCambioVal(e.target.value)}
                        onFocus={e => { setFocused(true); e.target.select(); }}
                        onBlur={() => setFocused(false)}
                        placeholder={tipo === 'porcentaje' ? '0' : '0.00'}
                        className={`w-full ${tipo === 'monto' ? 'pl-7' : 'pl-2.5'} pr-6 py-1.5 text-sm border rounded-lg focus:outline-none focus:ring-2 text-right`}
                        style={{
                            borderColor: 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            color: 'var(--color-text)',
                            '--tw-ring-color': 'color-mix(in srgb, var(--color-warning) 40%, transparent)',
                        } as React.CSSProperties}
                    />
                    {tipo === 'porcentaje' && (
                        <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>%</span>
                    )}
                </div>

                <select
                    value={cid ?? ''}
                    onChange={e => cambiarConcepto(e.target.value ? Number(e.target.value) : null)}
                    className="flex-1 min-w-0 text-sm border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2"
                    style={{
                        borderColor: 'var(--color-border)',
                        backgroundColor: 'var(--color-surface)',
                        color: 'var(--color-text)',
                        '--tw-ring-color': 'color-mix(in srgb, var(--color-warning) 40%, transparent)',
                    } as React.CSSProperties}
                >
                    <option value="">Seleccionar concepto</option>
                    {conceptos.map(c => (
                        <option key={c.id} value={c.id}>{c.nombre}{c.requiere_aprobacion ? ' (req. aprob.)' : ''}</option>
                    ))}
                </select>
            </div>

            {descuentoTotal > 0 && (
                <div className="flex items-center gap-1.5 text-xs flex-wrap" style={{ color: 'var(--color-text-muted)' }}>
                    <span>Descuento activo:</span>
                    <span className="font-bold" style={{ color: 'var(--color-danger)' }}>-S/ {descuentoTotal.toFixed(2)}</span>
                    {tipo === 'porcentaje' && (parseFloat(val) || 0) > 0 && (
                        <span className="opacity-80">({val}% de S/ {base.toFixed(2)})</span>
                    )}
                    {descuentoConceptoId && (
                        <span className="px-1.5 py-0.5 rounded-full text-[10px] font-medium"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 15%, transparent)', color: 'var(--color-warning)' }}>
                            {conceptos.find(c => c.id === descuentoConceptoId)?.nombre}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
