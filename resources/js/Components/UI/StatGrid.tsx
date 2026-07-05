import React from 'react';

/**
 * StatGrid — resumen de cifras clave en la cabecera de un modal.
 *
 * Reglas de diseño:
 *  - Siempre arriba del formulario/detalle, como contexto de la entidad.
 *  - Mini-cards uniformes: label uppercase pequeño + valor bold.
 *  - Colores solo semánticos (success = a favor, danger = deuda/pendiente).
 */
export interface Stat {
    label: string;
    valor: React.ReactNode;
    color?: 'success' | 'danger' | 'warning' | 'primary' | 'muted';
    /** Resalta la card completa (para la cifra protagonista). */
    destacado?: boolean;
}

const COLOR: Record<NonNullable<Stat['color']>, string> = {
    success: 'var(--color-success, #16a34a)',
    danger:  'var(--color-danger)',
    warning: 'var(--color-warning, #f59e0b)',
    primary: 'var(--color-primary)',
    muted:   'var(--color-text-muted)',
};

export default function StatGrid({ stats }: { stats: Stat[] }) {
    const cols = stats.length <= 2 ? 'grid-cols-2' : stats.length === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3';

    return (
        <div className={`grid ${cols} gap-2.5`}>
            {stats.map((s, i) => {
                const color = s.color ? COLOR[s.color] : 'var(--color-text)';
                return (
                    <div
                        key={i}
                        className="rounded-xl px-3 py-2.5 min-w-0"
                        style={{
                            border: s.destacado
                                ? `1.5px solid ${color}`
                                : '1px solid var(--color-border)',
                            backgroundColor: s.destacado
                                ? `color-mix(in srgb, ${color} 7%, var(--color-surface))`
                                : 'var(--color-surface)',
                        }}
                    >
                        <p className="text-[10px] font-semibold uppercase tracking-wider truncate"
                            style={{ color: 'var(--color-text-muted)' }}>
                            {s.label}
                        </p>
                        <p className="text-base font-bold truncate mt-0.5" style={{ color }}>
                            {s.valor}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}
