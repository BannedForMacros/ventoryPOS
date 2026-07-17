import React from 'react';

/**
 * StatGrid — resumen de cifras clave en la cabecera de un modal o de una página.
 *
 * Reglas de diseño:
 *  - Siempre arriba del formulario/detalle, como contexto de la entidad.
 *  - En páginas: los totales van DEBAJO del PageHeader con size="lg" y
 *    cols="grid-cols-2 sm:grid-cols-3 lg:grid-cols-4"; en `actions` del header
 *    solo van botones, nunca cifras.
 *  - El color semántico lo cargan el chip del ícono y el acento de la card;
 *    el valor usa un tinte profundo (mezclado con el ink) para no verse lavado.
 *  - Colores solo semánticos (success = a favor, danger = deuda/pendiente).
 */
export interface Stat {
    label: string;
    valor: React.ReactNode;
    color?: 'success' | 'danger' | 'warning' | 'primary' | 'muted';
    /** Resalta la card completa (para la cifra protagonista). */
    destacado?: boolean;
    /** Ícono lucide (~18-20px). Se muestra en un chip tintado a la derecha. */
    icon?: React.ReactNode;
    /** Línea de apoyo bajo el valor (contexto, no otra cifra). */
    sub?: React.ReactNode;
    /** Hace la card clicable (cursor + hover); p.ej. abrir un detalle/auditoría. */
    onClick?: () => void;
}

const COLOR: Record<NonNullable<Stat['color']>, string> = {
    success: 'var(--color-success, #16a34a)',
    danger:  'var(--color-danger)',
    warning: 'var(--color-warning, #f59e0b)',
    primary: 'var(--color-primary)',
    muted:   'var(--color-text-muted)',
};

interface StatGridProps {
    stats: Stat[];
    /** Override de la grilla (clases tailwind). En páginas: "grid-cols-2 sm:grid-cols-3 lg:grid-cols-4". */
    cols?: string;
    /** 'md' = modal (default) · 'lg' = página (cards más altas, hover, animación de entrada). */
    size?: 'md' | 'lg';
}

export default function StatGrid({ stats, cols: colsProp, size = 'md' }: StatGridProps) {
    const cols = colsProp ?? (stats.length <= 2 ? 'grid-cols-2' : stats.length === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3');
    const lg = size === 'lg';

    return (
        <div className={`grid ${cols} ${lg ? 'gap-3' : 'gap-2.5'}`}>
            {stats.map((s, i) => {
                const accent = s.color ? COLOR[s.color] : 'var(--color-primary)';
                const valueColor = s.color
                    ? `color-mix(in srgb, ${COLOR[s.color]} 80%, var(--color-text))`
                    : 'var(--color-text)';
                return (
                    <div
                        key={i}
                        onClick={s.onClick}
                        role={s.onClick ? 'button' : undefined}
                        className={`min-w-0 ${s.onClick ? 'cursor-pointer' : ''} ${lg
                            ? 'rounded-2xl px-5 py-4 vp-fade-up transition-all duration-200 hover:shadow-md hover:-translate-y-0.5'
                            : `rounded-xl px-3 py-2.5${s.onClick ? ' transition-all duration-150 hover:shadow-md hover:-translate-y-0.5' : ''}`}`}
                        style={{
                            border: s.destacado
                                ? `1.5px solid color-mix(in srgb, ${accent} 45%, var(--color-border))`
                                : '1px solid var(--color-border)',
                            background: s.destacado
                                ? `linear-gradient(135deg, color-mix(in srgb, ${accent} 9%, var(--color-surface)) 0%, var(--color-surface) 70%)`
                                : 'var(--color-surface)',
                            boxShadow: lg ? '0 1px 4px 0 rgb(0 0 0 / 0.04)' : undefined,
                            animationDelay: lg ? `${i * 60}ms` : undefined,
                        }}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider truncate"
                                    style={{ color: 'var(--color-text-muted)' }}>
                                    {s.label}
                                </p>
                                <p className={`font-bold truncate ${lg ? 'text-2xl mt-1.5' : 'text-base mt-0.5'}`}
                                    style={{ color: valueColor }}>
                                    {s.valor}
                                </p>
                                {s.sub && (
                                    <p className="text-xs mt-1 truncate" style={{ color: 'var(--color-text-muted)' }}>
                                        {s.sub}
                                    </p>
                                )}
                            </div>
                            {s.icon && (
                                <span
                                    className={`flex items-center justify-center flex-shrink-0 rounded-xl ${lg ? 'h-10 w-10' : 'h-8 w-8'}`}
                                    style={{
                                        color: accent,
                                        backgroundColor: `color-mix(in srgb, ${accent} 12%, transparent)`,
                                    }}
                                >
                                    {s.icon}
                                </span>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
