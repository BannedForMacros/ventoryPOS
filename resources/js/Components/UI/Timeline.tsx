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
    badge?: { texto: string; variant: 'primary' | 'success' | 'danger' | 'warning' | 'secondary' | 'info' };
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

    const hayUsuarios = items.some(i => i.user);
    // Columnas FIJAS para lectura simétrica: punto | fecha | tipo | detalle | usuario | monto
    const gridCols = hayUsuarios
        ? 'grid-cols-[14px_78px_100px_1fr_minmax(90px,auto)_96px]'
        : 'grid-cols-[14px_78px_100px_1fr_96px]';

    return (
        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
            {/* Cabecera de columnas */}
            <div className={`grid ${gridCols} gap-2 items-center px-3 py-2 text-[10px] font-bold uppercase tracking-wider`}
                style={{ backgroundColor: 'var(--color-surface)', color: 'var(--color-text-muted)', borderBottom: '1px solid var(--color-border)' }}>
                <span />
                <span>Fecha</span>
                <span>Tipo</span>
                <span>Detalle</span>
                {hayUsuarios && <span>Usuario</span>}
                <span className="text-right">Monto</span>
            </div>

            <div className="relative">
                {/* Línea vertical que une los puntos */}
                <span className="absolute left-[18px] top-3 bottom-3 w-px" style={{ backgroundColor: 'var(--color-border)' }} />

                {items.map((it, i) => {
                    const color = it.tipo === 'ingreso' ? 'var(--color-success, #16a34a)'
                        : it.tipo === 'egreso' ? 'var(--color-danger)'
                        : 'var(--color-text)';
                    return (
                        <div key={i}
                            className={`grid ${gridCols} gap-2 items-center px-3 py-2`}
                            style={{ borderTop: i > 0 ? '1px dashed var(--color-border)' : 'none' }}>
                            {/* Punto sobre la línea */}
                            <span className="relative z-10 h-[9px] w-[9px] rounded-full justify-self-center ring-2"
                                style={{ backgroundColor: color, ['--tw-ring-color' as any]: 'var(--color-surface)' } as React.CSSProperties} />

                            <span className="text-[12px] font-medium whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                {it.fecha}
                            </span>

                            <span>
                                {it.badge
                                    ? <Badge variant={it.badge.variant}>{it.badge.texto}</Badge>
                                    : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                            </span>

                            <span className="min-w-0 text-[13px] truncate"
                                title={typeof it.detalle === 'string' ? it.detalle : undefined}
                                style={{ color: 'var(--color-text)' }}>
                                {it.titulo}{it.titulo && it.detalle ? ' · ' : ''}
                                <span style={{ color: 'var(--color-text-muted)' }}>{it.detalle ?? (it.titulo ? '' : '—')}</span>
                            </span>

                            {hayUsuarios && (
                                <span className="min-w-0">
                                    {it.user ? (
                                        <span className="inline-flex items-center gap-1 max-w-full text-[11px] px-1.5 py-0.5 rounded-full"
                                            title={`Registrado por ${it.user}`}
                                            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                                            <UserRound size={10} className="flex-shrink-0" />
                                            <span className="truncate">{it.user}</span>
                                        </span>
                                    ) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                </span>
                            )}

                            <span className="text-sm font-bold whitespace-nowrap text-right" style={{ color }}>
                                {it.monto !== undefined
                                    ? <>{it.tipo === 'ingreso' ? '+' : it.tipo === 'egreso' ? '−' : ''}{money(it.monto)}</>
                                    : '—'}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
