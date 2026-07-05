import React from 'react';
import { UserRound } from 'lucide-react';
import Badge from '@/Components/UI/Badge';

/**
 * Timeline — historial de movimientos (abonos, pagos, entregas, cuotas).
 *
 * Reglas de diseño:
 *  - Línea vertical + punto por evento, orden cronológico.
 *  - Cada evento: fecha, badge del tipo, detalle, chip de usuario (auditoría)
 *    y monto a la derecha con color semántico.
 */
export interface TimelineItem {
    fecha: string;                 // ya formateada (d/m/Y)
    titulo?: React.ReactNode;      // texto principal (opcional si basta el badge)
    badge?: { texto: string; variant: 'primary' | 'success' | 'danger' | 'warning' | 'secondary' };
    detalle?: React.ReactNode;     // método · cuenta · referencia...
    user?: string | null;
    monto?: number;
    /** ingreso = verde con +, egreso = rojo con −, neutro = sin signo */
    tipo?: 'ingreso' | 'egreso' | 'neutro';
}

const money = (v: number) => `S/ ${v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Timeline({ items, emptyMessage = 'Sin movimientos registrados' }: { items: TimelineItem[]; emptyMessage?: string }) {
    if (items.length === 0) {
        return <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>{emptyMessage}</p>;
    }

    return (
        <div className="relative pl-4">
            {/* Línea vertical */}
            <span className="absolute left-[5px] top-2 bottom-2 w-px" style={{ backgroundColor: 'var(--color-border)' }} />

            <div className="space-y-3">
                {items.map((it, i) => {
                    const color = it.tipo === 'ingreso' ? 'var(--color-success, #16a34a)'
                        : it.tipo === 'egreso' ? 'var(--color-danger)'
                        : 'var(--color-text)';
                    return (
                        <div key={i} className="relative flex items-start gap-3">
                            {/* Punto */}
                            <span className="absolute -left-4 top-1.5 h-[9px] w-[9px] rounded-full ring-2"
                                style={{ backgroundColor: color, ['--tw-ring-color' as any]: 'var(--color-surface)' } as React.CSSProperties} />

                            <div className="flex-1 min-w-0">
                                <p className="text-sm leading-snug" style={{ color: 'var(--color-text)' }}>
                                    <span className="font-medium" style={{ color: 'var(--color-text-muted)' }}>{it.fecha}</span>
                                    {it.badge && <span className="ml-1.5 align-middle"><Badge variant={it.badge.variant}>{it.badge.texto}</Badge></span>}
                                    {it.titulo && <span className="ml-1.5">{it.titulo}</span>}
                                </p>
                                {(it.detalle || it.user) && (
                                    <p className="text-[12px] mt-0.5 flex items-center gap-1.5 flex-wrap" style={{ color: 'var(--color-text-muted)' }}>
                                        {it.detalle}
                                        {it.user && (
                                            <span className="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-full"
                                                style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                                                <UserRound size={10} />{it.user}
                                            </span>
                                        )}
                                    </p>
                                )}
                            </div>

                            {it.monto !== undefined && (
                                <span className="text-sm font-bold whitespace-nowrap" style={{ color }}>
                                    {it.tipo === 'ingreso' ? '+' : it.tipo === 'egreso' ? '−' : ''}{money(it.monto)}
                                </span>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
