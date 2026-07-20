import React from 'react';
import { Filter, X } from 'lucide-react';

interface FiltrosCardProps {
    /** Campos del panel (Select, Input, fechas…). Cada hijo ocupa una celda del grid. */
    children: React.ReactNode;
    /** Botón "Limpiar" (visible solo si tieneFiltros). */
    onClear?: () => void;
    tieneFiltros?: boolean;
    /** Acciones extra a la derecha del título (p. ej. botón "Aplicar filtros"). */
    actions?: React.ReactNode;
    /** Columnas del grid en desktop. Default 4; usa 6 en paneles densos, 3 en simples. */
    cols?: 3 | 4 | 6;
    className?: string;
}

const colClasses: Record<3 | 4 | 6, string> = {
    3: 'grid grid-cols-2 sm:grid-cols-3 gap-2.5 items-end',
    4: 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 items-end',
    6: 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 items-end',
};

/**
 * Panel de filtros normalizado de la app: card tintada con título "Filtros",
 * campos en grid simétrico (mismos anchos, alineados por la base) y acciones
 * a la derecha. Es el equivalente genérico del FiltrosReporte de los reportes:
 * úsalo en todos los index para que los filtros se vean iguales en todas partes.
 */
export default function FiltrosCard({ children, onClear, tieneFiltros, actions, cols = 4, className = '' }: FiltrosCardProps) {
    return (
        <div
            className={`rounded-2xl px-4 py-3 mb-4 ${className}`}
            style={{
                background: 'linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)) 0%, var(--color-surface) 60%)',
                border: '1px solid var(--color-border)',
            }}
        >
            <div className="flex items-center gap-2 mb-2.5">
                <Filter size={13} style={{ color: 'var(--color-primary)' }} />
                <span className="text-[10px] font-bold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                    Filtros
                </span>
                <div className="ml-auto flex items-center gap-1.5 flex-wrap">
                    {actions}
                    {tieneFiltros && onClear && (
                        <button
                            onClick={onClear}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full transition-colors hover:opacity-80"
                            style={{ color: 'var(--color-danger)', backgroundColor: 'color-mix(in srgb, var(--color-danger) 9%, transparent)' }}
                        >
                            <X size={11} /> Limpiar
                        </button>
                    )}
                </div>
            </div>
            <div className={colClasses[cols]}>
                {children}
            </div>
        </div>
    );
}
