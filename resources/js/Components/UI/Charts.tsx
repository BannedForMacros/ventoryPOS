import { useMemo, useRef, useState } from 'react';

/**
 * Gráficos SVG propios (sin dependencias): área/línea con tooltip y dona.
 * Theme-aware vía variables CSS (--color-*). Pensados para dashboard y
 * reportes: series diarias de ventas/utilidad y distribuciones.
 */

/* ── Paleta por defecto para series/segmentos ─────────────────────────── */
export const CHART_COLORS = [
    'var(--color-primary)', '#10b981', '#f59e0b', '#8b5cf6',
    '#ef4444', '#06b6d4', '#ec4899', '#84cc16', '#64748b',
];

const fmtMoney = (v: number) => `S/ ${v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtCompact = (v: number) => {
    const abs = Math.abs(v);
    if (abs >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `${(v / 1_000).toFixed(abs >= 10_000 ? 0 : 1)}k`;
    return `${Math.round(v)}`;
};

/* ═══ ÁREA / LÍNEAS con tooltip ════════════════════════════════════════ */

export interface AreaSerie {
    key: string;          // campo numérico dentro de cada punto
    label: string;
    color?: string;
    relleno?: boolean;    // pintar área bajo la línea (solo recomendable en 1 serie)
}

interface AreaChartProps {
    data: Array<Record<string, any> & { label: string }>;
    series: AreaSerie[];
    height?: number;
    money?: boolean;      // formatear tooltip/eje como soles
}

export function AreaChart({ data, series, height = 220, money = true }: AreaChartProps) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [hover, setHover] = useState<number | null>(null);

    const W = 800, H = height, padL = 44, padR = 10, padT = 12, padB = 24;
    const innerW = W - padL - padR, innerH = H - padT - padB;

    const { max, min } = useMemo(() => {
        let max = 0, min = 0;
        data.forEach(d => series.forEach(s => {
            const v = Number(d[s.key] ?? 0);
            if (v > max) max = v;
            if (v < min) min = v;
        }));
        if (max === 0 && min === 0) max = 1;
        return { max: max * 1.08, min: min < 0 ? min * 1.08 : 0 };
    }, [data, series]);

    const x = (i: number) => padL + (data.length <= 1 ? innerW / 2 : (i / (data.length - 1)) * innerW);
    const y = (v: number) => padT + innerH - ((v - min) / (max - min)) * innerH;

    const paths = useMemo(() => series.map(s => {
        const pts = data.map((d, i) => `${x(i).toFixed(1)},${y(Number(d[s.key] ?? 0)).toFixed(1)}`);
        const linea = `M${pts.join(' L')}`;
        const area = `M${x(0).toFixed(1)},${y(0).toFixed(1)} L${pts.join(' L')} L${x(data.length - 1).toFixed(1)},${y(0).toFixed(1)} Z`;
        return { linea, area };
    }), [data, series, max, min]);

    // Gridlines del eje Y (4 divisiones)
    const gridVals = useMemo(() => Array.from({ length: 5 }, (_, i) => min + ((max - min) / 4) * i), [max, min]);

    function onMove(e: React.MouseEvent) {
        if (!wrapRef.current || data.length === 0) return;
        const rect = wrapRef.current.getBoundingClientRect();
        const px = ((e.clientX - rect.left) / rect.width) * W;
        const i = Math.round(((px - padL) / innerW) * (data.length - 1));
        setHover(Math.min(data.length - 1, Math.max(0, i)));
    }

    if (data.length === 0) {
        return <div className="flex items-center justify-center text-xs py-10" style={{ color: 'var(--color-text-muted)' }}>Sin datos en el período</div>;
    }

    const hoverDatum = hover !== null ? data[hover] : null;
    const fmt = money ? fmtMoney : (v: number) => v.toLocaleString('es-PE');

    return (
        <div ref={wrapRef} className="relative w-full" onMouseMove={onMove} onMouseLeave={() => setHover(null)}>
            <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-auto block" preserveAspectRatio="none" aria-hidden>
                {/* Grid + etiquetas Y */}
                {gridVals.map((v, i) => (
                    <g key={i}>
                        <line x1={padL} x2={W - padR} y1={y(v)} y2={y(v)} stroke="var(--color-border)" strokeWidth={1} strokeDasharray={i === 0 ? undefined : '3 4'} />
                        <text x={padL - 6} y={y(v) + 3} textAnchor="end" fontSize={10} fill="var(--color-text-muted)">
                            {money ? fmtCompact(v) : fmtCompact(v)}
                        </text>
                    </g>
                ))}
                {/* Áreas y líneas */}
                {series.map((s, si) => (
                    <g key={s.key}>
                        {s.relleno && <path d={paths[si].area} fill={s.color ?? CHART_COLORS[si]} opacity={0.12} />}
                        <path d={paths[si].linea} fill="none" stroke={s.color ?? CHART_COLORS[si]} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
                    </g>
                ))}
                {/* Hover: guía vertical + puntos */}
                {hover !== null && (
                    <g>
                        <line x1={x(hover)} x2={x(hover)} y1={padT} y2={padT + innerH} stroke="var(--color-text-muted)" strokeWidth={1} strokeDasharray="3 3" opacity={0.6} />
                        {series.map((s, si) => (
                            <circle key={s.key} cx={x(hover)} cy={y(Number(data[hover][s.key] ?? 0))} r={3.5}
                                fill={s.color ?? CHART_COLORS[si]} stroke="var(--color-surface)" strokeWidth={1.5} />
                        ))}
                    </g>
                )}
                {/* Etiquetas X: primera, medio, última (evita amontonar) */}
                {[0, Math.floor((data.length - 1) / 2), data.length - 1].filter((v, i, a) => a.indexOf(v) === i).map(i => (
                    <text key={i} x={x(i)} y={H - 6} textAnchor={i === 0 ? 'start' : i === data.length - 1 ? 'end' : 'middle'}
                        fontSize={10} fill="var(--color-text-muted)">
                        {data[i].label}
                    </text>
                ))}
            </svg>

            {/* Tooltip */}
            {hoverDatum && (
                <div className="absolute pointer-events-none z-10 rounded-lg px-2.5 py-1.5 text-xs shadow-lg"
                    style={{
                        left: `${(x(hover!) / W) * 100}%`,
                        top: 0,
                        transform: `translateX(${hover! > data.length / 2 ? '-105%' : '8px'})`,
                        backgroundColor: 'var(--color-surface)',
                        border: '1px solid var(--color-border)',
                        color: 'var(--color-text)',
                    }}>
                    <p className="font-bold mb-0.5">{hoverDatum.label}</p>
                    {series.map((s, si) => (
                        <p key={s.key} className="flex items-center gap-1.5 whitespace-nowrap">
                            <span className="inline-block w-2 h-2 rounded-full" style={{ backgroundColor: s.color ?? CHART_COLORS[si] }} />
                            {s.label}: <strong>{fmt(Number(hoverDatum[s.key] ?? 0))}</strong>
                        </p>
                    ))}
                </div>
            )}

            {/* Leyenda */}
            {series.length > 1 && (
                <div className="flex flex-wrap gap-3 mt-1.5 px-1">
                    {series.map((s, si) => (
                        <span key={s.key} className="flex items-center gap-1.5 text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                            <span className="inline-block w-2.5 h-2.5 rounded-full" style={{ backgroundColor: s.color ?? CHART_COLORS[si] }} />
                            {s.label}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ═══ DONA con leyenda ═════════════════════════════════════════════════ */

interface DonutChartProps {
    data: Array<{ label: string; valor: number; color?: string }>;
    /** Ancho MÁXIMO de la dona: crece hasta llenar la card (responsive). */
    size?: number;
    money?: boolean;
    /** Texto central (p. ej. el total). */
    centro?: { valor: string; label?: string };
    /** Apilar: dona arriba centrada, leyenda debajo a lo ancho (cards angostas). */
    vertical?: boolean;
}

const truncar = (s: string, n: number) => (s.length > n ? s.slice(0, n - 1) + '…' : s);

export function DonutChart({ data, size, money = true, centro, vertical = false }: DonutChartProps) {
    const [hover, setHover] = useState<number | null>(null);
    // En layout vertical (dona arriba, leyenda debajo) la dona es la protagonista
    // de la card: crece bastante más antes de toparse con el límite.
    const maxDona = size ?? (vertical ? 360 : 250);
    const total = data.reduce((s, d) => s + Math.max(0, d.valor), 0);
    const R = 50, STROKE = 17, C = 2 * Math.PI * R;

    if (total <= 0) {
        return <div className="flex items-center justify-center text-xs py-8" style={{ color: 'var(--color-text-muted)' }}>Sin datos en el período</div>;
    }

    let acc = 0;
    const segs = data.map((d, i) => {
        const frac = Math.max(0, d.valor) / total;
        const seg = { ...d, frac, offset: acc, color: d.color ?? CHART_COLORS[i % CHART_COLORS.length] };
        acc += frac;
        return seg;
    });

    const fmt = money ? fmtMoney : (v: number) => v.toLocaleString('es-PE');

    // Texto central: dentro del SVG para que escale con la dona.
    const centroTop  = hover !== null ? truncar(segs[hover].label, 18) : (centro?.label ?? '');
    const centroBig  = hover !== null ? `${Math.round(segs[hover].frac * 100)}%` : (centro?.valor ?? '');
    const bigFont    = Math.min(11, 105 / Math.max(1, centroBig.length));

    return (
        <div className={vertical
            ? 'flex flex-col items-center gap-4'
            : 'flex flex-wrap items-center justify-center gap-x-6 gap-y-3'}>
            <div className="relative w-full flex-1 mx-auto" style={{ minWidth: 150, maxWidth: maxDona }}>
                <svg viewBox="0 0 120 120" className="w-full h-auto block">
                    <g transform="rotate(-90 60 60)">
                        {segs.map((s, i) => (
                            <circle key={i} cx={60} cy={60} r={R} fill="none"
                                stroke={s.color} strokeWidth={hover === i ? STROKE + 3 : STROKE}
                                strokeDasharray={`${(s.frac * C).toFixed(2)} ${C.toFixed(2)}`}
                                strokeDashoffset={(-s.offset * C).toFixed(2)}
                                style={{ transition: 'stroke-width 120ms', cursor: 'pointer', opacity: hover === null || hover === i ? 1 : 0.35 }}
                                onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}
                            />
                        ))}
                    </g>
                    {hover !== null ? (
                        <>
                            <text x={60} y={56} textAnchor="middle" fontSize={6.5} fontWeight={600}
                                fill="var(--color-text-muted)">{centroTop}</text>
                            <text x={60} y={68} textAnchor="middle" fontSize={bigFont} fontWeight={700}
                                fill="var(--color-text)">{centroBig}</text>
                        </>
                    ) : centro ? (
                        <>
                            <text x={60} y={centro.label ? 58 : 63} textAnchor="middle" fontSize={bigFont} fontWeight={700}
                                fill="var(--color-text)">{centro.valor}</text>
                            {centro.label && (
                                <text x={60} y={68} textAnchor="middle" fontSize={6}
                                    fill="var(--color-text-muted)">{centro.label}</text>
                            )}
                        </>
                    ) : null}
                </svg>
            </div>

            <div className={vertical ? 'w-full space-y-1' : 'flex-1 min-w-[190px] w-full space-y-1'}>
                {segs.map((s, i) => (
                    <div key={i} className="flex items-center gap-2 text-xs cursor-default rounded px-1 py-0.5"
                        style={{ backgroundColor: hover === i ? 'color-mix(in srgb, var(--color-primary) 8%, transparent)' : undefined }}
                        onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                        <span className="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: s.color }} />
                        <span className="flex-1 truncate" style={{ color: 'var(--color-text)' }}>{s.label}</span>
                        <span className="font-medium whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>{Math.round(s.frac * 100)}%</span>
                        <span className="font-bold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmt(s.valor)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ═══ BARRAS HORIZONTALES (ranking / distribución) ═════════════════════ */

interface BarListProps {
    data: Array<{ label: string; valor: number; extra?: string; color?: string }>;
    money?: boolean;
    /** Pintar cada barra con un color de la paleta (si no, todas primary). */
    multicolor?: boolean;
    /** Mostrar % de participación junto al valor. */
    porcentaje?: boolean;
    maxItems?: number;
}

export function BarList({ data, money = true, multicolor = false, porcentaje = true, maxItems }: BarListProps) {
    const [hover, setHover] = useState<number | null>(null);
    const items = maxItems ? data.slice(0, maxItems) : data;
    const total = data.reduce((s, d) => s + Math.max(0, d.valor), 0);
    const max = Math.max(1, ...items.map(d => d.valor));
    const fmt = money ? fmtMoney : (v: number) => v.toLocaleString('es-PE');

    if (items.length === 0) {
        return <div className="flex items-center justify-center text-xs py-8" style={{ color: 'var(--color-text-muted)' }}>Sin datos en el período</div>;
    }

    return (
        <div className="space-y-2.5">
            {items.map((d, i) => {
                const color = d.color ?? (multicolor ? CHART_COLORS[i % CHART_COLORS.length] : 'var(--color-primary)');
                const pct = total > 0 ? Math.round((Math.max(0, d.valor) / total) * 100) : 0;
                return (
                    <div key={i} className="group cursor-default rounded-lg px-1.5 py-1 -mx-1.5 transition-colors"
                        style={{ backgroundColor: hover === i ? 'color-mix(in srgb, var(--color-primary) 6%, transparent)' : undefined }}
                        onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                        <div className="flex items-baseline justify-between gap-2 mb-1">
                            <span className="text-xs font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                {d.label}
                                {d.extra && <span className="ml-1.5 font-normal text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{d.extra}</span>}
                            </span>
                            <span className="text-xs font-bold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                {fmt(d.valor)}
                                {porcentaje && <span className="ml-1 font-medium text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{pct}%</span>}
                            </span>
                        </div>
                        <div className="h-2 rounded-full overflow-hidden"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 55%, transparent)' }}>
                            <div className="h-full rounded-full transition-all"
                                style={{
                                    width: `${(Math.max(0, d.valor) / max) * 100}%`,
                                    background: `linear-gradient(90deg, color-mix(in srgb, ${color} 78%, #fff) 0%, ${color} 100%)`,
                                }} />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
