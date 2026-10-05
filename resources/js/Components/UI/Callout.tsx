import React from 'react';
import { Info, CheckCircle2, AlertTriangle, XCircle, ArrowRight, type LucideIcon } from 'lucide-react';

/**
 * Callout — el aviso estándar de TODO el sistema (páginas, modales, POS).
 *
 * Reglas:
 *  - Un solo estilo por variante; nada de divs de colores sueltos.
 *  - El texto dice el problema Y qué hacer. Si hay una acción, va como botón
 *    (`accion`) y lleva al lugar donde se corrige.
 *  - Texto en las tintas legibles de la marca (--vp-*-ink): el ámbar o el
 *    menta puros sobre fondo claro no se leen.
 *
 * Escala tipográfica del sistema (la misma del POS):
 *   11 px meta mínima · 12 px secundario · 13 px cuerpo de aviso · 14 px cuerpo
 *   · 15 px importes de línea · títulos con font-display.
 */
type Variant = 'info' | 'success' | 'danger' | 'warning' | 'neutral';

interface CalloutProps {
    variant?: Variant;
    title?: React.ReactNode;
    children?: React.ReactNode;
    /** Contenido a la derecha (ej. un monto grande). */
    aside?: React.ReactNode;
    /** Acción para resolverlo (ej. "Corregir" → enfoca el campo). */
    accion?: { label: string; onClick: () => void };
    className?: string;
}

const CONFIG: Record<Variant, { color: string; tinta: string; icon: LucideIcon }> = {
    info:    { color: 'var(--vp-sky)',             tinta: 'var(--vp-navy)',        icon: Info },
    success: { color: 'var(--vp-mint)',            tinta: 'var(--vp-mint-ink)',    icon: CheckCircle2 },
    danger:  { color: 'var(--color-danger)',       tinta: 'var(--vp-coral-ink)',   icon: XCircle },
    warning: { color: 'var(--vp-amber)',           tinta: 'var(--vp-amber-ink)',   icon: AlertTriangle },
    neutral: { color: 'var(--color-text-muted)',   tinta: 'var(--color-text)',     icon: Info },
};

export default function Callout({ variant = 'info', title, children, aside, accion, className = '' }: CalloutProps) {
    const { color, tinta, icon: Icon } = CONFIG[variant];

    return (
        <div
            role={variant === 'danger' || variant === 'warning' ? 'alert' : 'status'}
            className={`flex items-start gap-2.5 rounded-xl px-3.5 py-2.5 ${className}`}
            style={{
                backgroundColor: `color-mix(in srgb, ${color} 10%, var(--color-surface))`,
                border: `1px solid color-mix(in srgb, ${color} 35%, transparent)`,
            }}
        >
            <Icon size={16} className="flex-shrink-0 mt-0.5" style={{ color: tinta }} />
            <div className="flex-1 min-w-0 text-[13px] leading-snug" style={{ color: 'var(--color-text)' }}>
                {title && <p className="text-sm font-semibold" style={{ color: tinta }}>{title}</p>}
                {children && <div className={title ? 'mt-0.5' : ''}>{children}</div>}
            </div>
            {aside && (
                <div className="flex-shrink-0 text-right font-bold tabular-nums" style={{ color: tinta }}>
                    {aside}
                </div>
            )}
            {accion && (
                <button
                    type="button"
                    onClick={accion.onClick}
                    className="flex-shrink-0 inline-flex items-center gap-1 self-center text-[13px] font-bold underline-offset-2 hover:underline"
                    style={{ color: tinta }}
                >
                    {accion.label} <ArrowRight size={14} />
                </button>
            )}
        </div>
    );
}
