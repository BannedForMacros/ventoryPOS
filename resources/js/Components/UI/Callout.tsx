import React from 'react';
import { Info, CheckCircle2, AlertTriangle, XCircle, type LucideIcon } from 'lucide-react';

/**
 * Callout — caja de feedback/contexto estandarizada para modales y páginas.
 *
 * Reglas de diseño:
 *  - Un solo estilo por variante en TODO el sistema (nada de divs ad-hoc).
 *  - Fondo suave (10% del color), icono a la izquierda, texto compacto.
 */
type Variant = 'info' | 'success' | 'danger' | 'warning' | 'neutral';

interface CalloutProps {
    variant?: Variant;
    title?: React.ReactNode;
    children?: React.ReactNode;
    /** Contenido a la derecha (ej. un monto grande). */
    aside?: React.ReactNode;
    className?: string;
}

const CONFIG: Record<Variant, { color: string; icon: LucideIcon }> = {
    info:    { color: 'var(--color-primary)',              icon: Info },
    success: { color: 'var(--color-success, #16a34a)',     icon: CheckCircle2 },
    danger:  { color: 'var(--color-danger)',               icon: XCircle },
    warning: { color: 'var(--color-warning, #f59e0b)',     icon: AlertTriangle },
    neutral: { color: 'var(--color-text-muted)',           icon: Info },
};

export default function Callout({ variant = 'info', title, children, aside, className = '' }: CalloutProps) {
    const { color, icon: Icon } = CONFIG[variant];

    return (
        <div
            className={`flex items-start gap-2.5 rounded-xl px-3.5 py-2.5 ${className}`}
            style={{
                backgroundColor: `color-mix(in srgb, ${color} 9%, var(--color-bg))`,
                border: `1px solid color-mix(in srgb, ${color} 25%, transparent)`,
            }}
        >
            <Icon size={16} className="flex-shrink-0 mt-0.5" style={{ color }} />
            <div className="flex-1 min-w-0 text-sm leading-snug" style={{ color: 'var(--color-text)' }}>
                {title && <p className="font-semibold" style={{ color }}>{title}</p>}
                {children && <div className={title ? 'mt-0.5 text-[13px]' : ''}>{children}</div>}
            </div>
            {aside && (
                <div className="flex-shrink-0 text-right font-bold" style={{ color }}>
                    {aside}
                </div>
            )}
        </div>
    );
}
