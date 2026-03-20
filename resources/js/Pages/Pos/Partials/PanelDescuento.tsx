import React, { useEffect, useState } from 'react';
import { Percent, X } from 'lucide-react';
import type { DescuentoConcepto } from '@/types';

interface Props {
    descuentoTotal:       number;
    descuentoConceptoId:  number | null;
    conceptos:            DescuentoConcepto[];
    onChange:             (descuento: number, conceptoId: number | null) => void;
}

export default function PanelDescuento({ descuentoTotal, descuentoConceptoId, conceptos, onChange }: Props) {
    const [val, setVal] = useState(String(descuentoTotal || ''));
    const [cid, setCid] = useState<number | null>(descuentoConceptoId);
    const [abierto, setAbierto] = useState(descuentoTotal > 0);

    // Sincronizar estado local cuando el padre cambia (ej. limpiarCarrito)
    useEffect(() => {
        setVal(String(descuentoTotal || ''));
        setCid(descuentoConceptoId);
        if (descuentoTotal === 0) setAbierto(false);
    }, [descuentoTotal, descuentoConceptoId]);

    function aplicar() {
        const d = parseFloat(val) || 0;
        onChange(d, d > 0 ? cid : null);
        if (d === 0) setAbierto(false);
    }

    function quitar() {
        setVal('');
        setCid(null);
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

            <div className="flex gap-2">
                <div className="relative w-28">
                    <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={val}
                        onChange={e => setVal(e.target.value)}
                        onBlur={aplicar}
                        onKeyDown={e => e.key === 'Enter' && aplicar()}
                        placeholder="0.00"
                        className="w-full pl-7 pr-2 py-1.5 text-sm border rounded-lg focus:outline-none focus:ring-2"
                        style={{
                            borderColor: 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            color: 'var(--color-text)',
                            '--tw-ring-color': 'color-mix(in srgb, var(--color-warning) 40%, transparent)',
                        } as React.CSSProperties}
                    />
                </div>
                <select
                    value={cid ?? ''}
                    onChange={e => {
                        const newCid = e.target.value ? Number(e.target.value) : null;
                        setCid(newCid);
                        const d = parseFloat(val) || 0;
                        if (d > 0) onChange(d, newCid);
                    }}
                    className="flex-1 text-sm border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2"
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
                <div className="flex items-center gap-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                    <span>Descuento activo:</span>
                    <span className="font-bold" style={{ color: 'var(--color-danger)' }}>-S/ {descuentoTotal.toFixed(2)}</span>
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
