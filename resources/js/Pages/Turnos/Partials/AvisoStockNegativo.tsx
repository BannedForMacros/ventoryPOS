import { useState } from 'react';
import { ChevronDown, PackageX } from 'lucide-react';
import { plural } from '@/Components/Reportes/ReportUI';

// Producto vendido en el turno cuyo stock quedó negativo (aviso al cierre).
export interface ProductoStockNegativo {
    producto_id:      number;
    producto_nombre:  string;
    cantidad_vendida: number;
    stock_actual:     number;
}

/** Aviso plegado: una línea que se despliega con la lista de productos. */
export default function AvisoStockNegativo({ productos }: { productos: ProductoStockNegativo[] }) {
    const [abierto, setAbierto] = useState(false);
    return (
        <section className="mb-3 rounded-2xl overflow-hidden"
            style={{ border: '1px solid color-mix(in srgb, var(--vp-amber) 45%, transparent)', backgroundColor: 'var(--color-surface)' }}>
            <button type="button" onClick={() => setAbierto(a => !a)} aria-expanded={abierto}
                className="w-full flex items-center gap-3 px-4 py-2.5 text-left"
                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 10%, var(--color-surface))' }}>
                <PackageX size={18} className="flex-shrink-0" style={{ color: 'var(--vp-amber-ink)' }} />
                <span className="flex-1 min-w-0 text-sm font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>
                    {plural(productos.length, 'producto quedó', 'productos quedaron')} con stock negativo
                    <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}> · no impide cerrar</span>
                </span>
                <span className="text-[13px] font-semibold whitespace-nowrap" style={{ color: 'var(--vp-amber-ink)' }}>{abierto ? 'Ocultar' : 'Ver'}</span>
                <ChevronDown size={16} className={`flex-shrink-0 transition-transform ${abierto ? 'rotate-180' : ''}`} style={{ color: 'var(--vp-amber-ink)' }} />
            </button>
            {abierto && (
                <>
                    <p className="px-4 pt-2.5 pb-1 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                        Se vendió más de lo registrado. Regulariza con una entrada o transferencia.
                    </p>
                    <ul className="max-h-64 overflow-y-auto">
                        {productos.map(p => (
                            <li key={p.producto_id} className="flex items-center justify-between gap-3 px-4 py-1.5 text-sm"
                                style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                <span className="font-semibold min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</span>
                                <span className="tabular-nums whitespace-nowrap text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                    vendiste {Number(p.cantidad_vendida)}, quedan <strong style={{ color: 'var(--vp-coral-ink)' }}>{Number(p.stock_actual)}</strong>
                                </span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}
