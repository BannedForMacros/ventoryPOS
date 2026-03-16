import React from 'react';
import { Loader2 } from 'lucide-react';

type Variant = 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'ghost';
type Size = 'xs' | 'sm' | 'md' | 'lg' | 'xl';
type Radius = 'none' | 'sm' | 'md' | 'lg' | 'full';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    radius?: Radius;
    /** Modo flat: fondo tintado suave + borde del mismo color (inspirado en HeroUI) */
    flat?: boolean;
    /** Ícono al inicio del botón */
    startContent?: React.ReactNode;
    /** Ícono al final del botón */
    endContent?: React.ReactNode;
    loading?: boolean;
    /** Muestra solo el ícono, aplica padding cuadrado */
    iconOnly?: boolean;
    children?: React.ReactNode;
}

// ─── Colores base por variante ────────────────────────────────────────────────
const variantTokens: Record<Variant, { bg: string; text: string; border: string; ring: string }> = {
    primary:   { bg: 'var(--color-primary)',   text: '#fff', border: 'var(--color-primary)',   ring: 'var(--color-primary)' },
    secondary: { bg: 'var(--color-secondary)', text: '#fff', border: 'var(--color-secondary)', ring: 'var(--color-secondary)' },
    success:   { bg: 'var(--color-success)',   text: '#fff', border: 'var(--color-success)',   ring: 'var(--color-success)' },
    danger:    { bg: 'var(--color-danger)',     text: '#fff', border: 'var(--color-danger)',    ring: 'var(--color-danger)' },
    warning:   { bg: 'var(--color-warning)',   text: '#fff', border: 'var(--color-warning)',   ring: 'var(--color-warning)' },
    ghost:     { bg: 'transparent',            text: 'var(--color-text)', border: 'var(--color-border)', ring: 'var(--color-border)' },
};

// ─── Clases hover por modo ────────────────────────────────────────────────────
const solidHover: Record<Variant, string> = {
    primary:   'hover:brightness-110 hover:shadow-md',
    secondary: 'hover:brightness-110 hover:shadow-md',
    success:   'hover:brightness-110 hover:shadow-md',
    danger:    'hover:brightness-110 hover:shadow-md',
    warning:   'hover:brightness-110 hover:shadow-md',
    ghost:     'hover:bg-black/5 dark:hover:bg-white/10',
};

const flatHover: Record<Variant, string> = {
    primary:   'hover:brightness-95',
    secondary: 'hover:brightness-95',
    success:   'hover:brightness-95',
    danger:    'hover:brightness-95',
    warning:   'hover:brightness-95',
    ghost:     'hover:bg-black/5 dark:hover:bg-white/10',
};

// ─── Tamaños ──────────────────────────────────────────────────────────────────
const sizeClasses: Record<Size, string> = {
    xs: 'px-2.5 py-1   text-xs   gap-1',
    sm: 'px-3   py-1.5 text-xs   gap-1.5',
    md: 'px-4   py-2   text-sm   gap-2',
    lg: 'px-5   py-2.5 text-base gap-2',
    xl: 'px-6   py-3   text-base gap-2.5',
};

const iconOnlySizeClasses: Record<Size, string> = {
    xs: 'p-1   text-xs',
    sm: 'p-1.5 text-xs',
    md: 'p-2   text-sm',
    lg: 'p-2.5 text-base',
    xl: 'p-3   text-base',
};

const iconSize: Record<Size, number> = {
    xs: 12, sm: 14, md: 16, lg: 18, xl: 20,
};

// ─── Radios ───────────────────────────────────────────────────────────────────
const radiusClasses: Record<Radius, string> = {
    none: 'rounded-none',
    sm:   'rounded-md',
    md:   'rounded-lg',
    lg:   'rounded-xl',
    full: 'rounded-full',
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
function hexToRgb(hex: string): string | null {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result
        ? `${parseInt(result[1], 16)} ${parseInt(result[2], 16)} ${parseInt(result[3], 16)}`
        : null;
}

function buildStyles(variant: Variant, flat: boolean): React.CSSProperties {
    const token = variantTokens[variant];

    if (flat && variant !== 'ghost') {
        // Para flat usamos una variable CSS con el color base como RGB
        // y construimos el fondo/borde con opacidad via inline style
        return {
            '--btn-color': token.bg,
            backgroundColor: `color-mix(in srgb, ${token.bg} 12%, transparent)`,
            color: token.bg,
            borderColor: `color-mix(in srgb, ${token.bg} 30%, transparent)`,
            '--btn-ring': token.ring,
        } as React.CSSProperties;
    }

    return {
        backgroundColor: token.bg,
        color: token.text,
        borderColor: token.border,
        '--btn-ring': token.ring,
    } as React.CSSProperties;
}

// ─── Componente ───────────────────────────────────────────────────────────────
export default function Button({
    variant = 'primary',
    size = 'md',
    radius = 'lg',
    flat = false,
    loading = false,
    iconOnly = false,
    startContent,
    endContent,
    disabled,
    children,
    className = '',
    ...props
}: ButtonProps) {
    const iSize = iconSize[size];
    const hoverClass = flat ? flatHover[variant] : solidHover[variant];
    const paddingClass = iconOnly ? iconOnlySizeClasses[size] : sizeClasses[size];

    return (
        <button
            disabled={disabled || loading}
            className={[
                'inline-flex items-center justify-center border font-medium outline-none',
                'transition-all duration-200 cursor-pointer select-none whitespace-nowrap',
                'active:scale-[0.97]',
                'disabled:active:scale-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:brightness-100',
                'focus-visible:ring-4 focus-visible:ring-[color-mix(in_srgb,var(--btn-ring)_30%,transparent)]',
                radiusClasses[radius],
                paddingClass,
                hoverClass,
                className,
            ].join(' ')}
            style={buildStyles(variant, flat)}
            {...props}
        >
            {/* Spinner de carga */}
            {loading && (
                <Loader2
                    size={iSize}
                    className="animate-spin flex-shrink-0"
                    aria-hidden="true"
                />
            )}

            {/* Ícono de inicio (oculto durante carga) */}
            {!loading && startContent && (
                <span className="flex-shrink-0 flex items-center" aria-hidden="true">
                    {startContent}
                </span>
            )}

            {/* Contenido principal */}
            {children && (
                <span className={loading ? 'opacity-70' : undefined}>
                    {children}
                </span>
            )}

            {/* Ícono de fin */}
            {!loading && endContent && (
                <span className="flex-shrink-0 flex items-center" aria-hidden="true">
                    {endContent}
                </span>
            )}
        </button>
    );
}