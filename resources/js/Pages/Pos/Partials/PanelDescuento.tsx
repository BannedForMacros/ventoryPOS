import React from 'react';
import type { DescuentoConcepto } from '@/types';

interface Props {
    descuentoTotal:       number;
    descuentoConceptoId:  number | null;
    conceptos:            DescuentoConcepto[];
    onChange:             (descuento: number, conceptoId: number | null) => void;
}

export default function PanelDescuento({ descuentoTotal, descuentoConceptoId, conceptos, onChange }: Props) {
    const [val, setVal]         = React.useState(String(descuentoTotal || ''));
    const [cid, setCid]         = React.useState<number | null>(descuentoConceptoId);

    function aplicar() {
        const d = parseFloat(val) || 0;
        onChange(d, d > 0 ? cid : null);
    }

    return (
        <div
            className="rounded-lg p-3"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
        >
            <p className="text-xs font-semibold mb-2" style={{ color: 'var(--color-text-muted)' }}>
                DESCUENTO GLOBAL
            </p>
            <div className="flex gap-2">
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={val}
                    onChange={e => setVal(e.target.value)}
                    placeholder="S/ 0.00"
                    className="w-28 text-sm border rounded px-2 py-1.5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                />
                <select
                    value={cid ?? ''}
                    onChange={e => setCid(e.target.value ? Number(e.target.value) : null)}
                    className="flex-1 text-sm border rounded px-2 py-1.5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                >
                    <option value="">Concepto de descuento</option>
                    {conceptos.map(c => (
                        <option key={c.id} value={c.id}>{c.nombre}{c.requiere_aprobacion ? ' ⚠️' : ''}</option>
                    ))}
                </select>
                <button
                    onClick={aplicar}
                    className="text-sm px-3 py-1.5 rounded font-medium"
                    style={{ backgroundColor: 'var(--color-primary)', color: '#fff' }}
                >
                    Aplicar
                </button>
            </div>
            {descuentoTotal > 0 && (
                <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                    Descuento activo: <span className="font-semibold text-red-500">-S/ {descuentoTotal.toFixed(2)}</span>
                    {descuentoConceptoId && (
                        <span> · {conceptos.find(c => c.id === descuentoConceptoId)?.nombre}</span>
                    )}
                </p>
            )}
        </div>
    );
}
