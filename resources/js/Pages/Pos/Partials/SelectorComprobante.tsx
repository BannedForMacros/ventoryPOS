import React from 'react';
import { FileText, Receipt, ScrollText } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type TipoComprobanteSel = 'ticket' | 'boleta' | 'factura' | 'boleta_externa' | 'factura_externa';

interface Opcion {
    valor:  TipoComprobanteSel;
    label:  string;
    ayuda:  string;
    Icono:  LucideIcon;
}

interface Props {
    valor:     TipoComprobanteSel;
    // Con facturación electrónica propia se emite boleta/factura; sin ella se
    // registra el número de una emitida en otro sistema (externa).
    feActiva:  boolean;
    onChange:  (v: TipoComprobanteSel) => void;
    // 'primario': sobre la barra azul del POS. 'superficie': sobre fondo claro (móvil).
    variante?: 'primario' | 'superficie';
}

/**
 * Selector de comprobante del POS: botones segmentados en vez del <select>
 * nativo. Tres opciones a la vista, un toque para cambiar, y la elegida se ve
 * sin abrir nada.
 */
export default function SelectorComprobante({ valor, feActiva, onChange, variante = 'superficie' }: Props) {
    const opciones: Opcion[] = [
        { valor: 'ticket', label: 'Sin comprobante', ayuda: 'Solo ticket interno, no se declara a SUNAT', Icono: Receipt },
        feActiva
            ? { valor: 'boleta', label: 'Boleta', ayuda: 'Boleta electrónica (se envía a SUNAT)', Icono: ScrollText }
            : { valor: 'boleta_externa', label: 'Boleta', ayuda: 'Boleta emitida en otro sistema: anota su número', Icono: ScrollText },
        feActiva
            ? { valor: 'factura', label: 'Factura', ayuda: 'Factura electrónica (requiere RUC del cliente)', Icono: FileText }
            : { valor: 'factura_externa', label: 'Factura', ayuda: 'Factura emitida en otro sistema: anota su número', Icono: FileText },
    ];

    const primario = variante === 'primario';

    return (
        <div
            role="radiogroup"
            aria-label="Tipo de comprobante"
            className={`inline-flex items-center gap-0.5 rounded-lg p-0.5 ${primario ? 'bg-white/15' : ''}`}
            style={primario ? undefined : { backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
        >
            {opciones.map(({ valor: v, label, ayuda, Icono }) => {
                const activo = v === valor;
                return (
                    <button
                        key={v}
                        type="button"
                        role="radio"
                        aria-checked={activo}
                        title={ayuda}
                        onClick={() => onChange(v)}
                        className="flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-md transition-colors whitespace-nowrap"
                        style={primario
                            ? {
                                backgroundColor: activo ? '#fff' : 'transparent',
                                color: activo ? 'var(--color-primary)' : 'rgba(255,255,255,0.85)',
                            }
                            : {
                                backgroundColor: activo ? 'var(--color-primary)' : 'transparent',
                                color: activo ? '#fff' : 'var(--color-text-muted)',
                            }}
                    >
                        <Icono size={13} />
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
