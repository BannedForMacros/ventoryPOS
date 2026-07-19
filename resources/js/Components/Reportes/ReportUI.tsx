import { router } from '@inertiajs/react';
import { Calendar, Filter, X } from 'lucide-react';

/**
 * Kit compartido de los reportes: KPIs con fondo tintado (nada de cards
 * blancas planas), cards con cabecera acentuada, filtros con rangos rápidos
 * y paginación con elipsis. Toda la paleta sale de las variables --color-*.
 */

/* ── Formatos ─────────────────────────────────────────────────────────── */
export const fmtS = (n: number) =>
    'S/ ' + Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const fmtInt = (n: number) => Number(n).toLocaleString('es-PE');
export const fmtCant = (n: number) => Number(n).toLocaleString('es-PE', { maximumFractionDigits: 2 });
export const diaLabel = (d: string) =>
    new Date(d + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });
export const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });

/* ── KPI con vida: degradado tintado + chip sólido ────────────────────── */
export function Kpi({ icon, label, value, sub, color, subColor }: {
    icon: React.ReactNode; label: string; value: string; sub?: string;
    /** Color CSS (var(--color-success), '#f59e0b', …). */
    color: string;
    subColor?: string;
}) {
    return (
        <div className="rounded-2xl px-4 py-3.5 flex items-start gap-3"
            style={{
                background: `linear-gradient(135deg, color-mix(in srgb, ${color} 14%, var(--color-surface)) 0%, var(--color-surface) 72%)`,
                border: `1px solid color-mix(in srgb, ${color} 30%, var(--color-border))`,
            }}>
            <div className="p-2 rounded-xl flex-shrink-0 shadow-sm"
                style={{ backgroundColor: color, color: '#fff' }}>
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                <p className="text-lg font-bold leading-tight truncate" style={{ color: 'var(--color-text)' }}>{value}</p>
                {sub && <p className="text-[11px] truncate" style={{ color: subColor ?? 'var(--color-text-muted)' }}>{sub}</p>}
            </div>
        </div>
    );
}

/* ── Card con cabecera acentuada ──────────────────────────────────────── */
export function ReportCard({ icon, title, badge, accent = 'var(--color-primary)', actions, children, className = '', sinPadding = false }: {
    icon?: React.ReactNode; title: string; badge?: string;
    accent?: string; actions?: React.ReactNode;
    children: React.ReactNode; className?: string;
    /** true para tablas que ocupan todo el ancho de la card. */
    sinPadding?: boolean;
}) {
    return (
        <div className={`rounded-2xl overflow-hidden ${className}`}
            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
            <div className="px-4 py-2.5 flex flex-wrap items-center gap-2"
                style={{
                    background: `linear-gradient(90deg, color-mix(in srgb, ${accent} 11%, var(--color-surface)) 0%, var(--color-surface) 85%)`,
                    borderBottom: '1px solid var(--color-border)',
                }}>
                {icon && (
                    <span className="p-1.5 rounded-lg flex-shrink-0"
                        style={{ backgroundColor: `color-mix(in srgb, ${accent} 15%, transparent)`, color: accent }}>
                        {icon}
                    </span>
                )}
                <span className="text-sm font-bold" style={{ color: 'var(--color-text)' }}>{title}</span>
                {badge && (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                        style={{ backgroundColor: `color-mix(in srgb, ${accent} 14%, transparent)`, color: accent }}>
                        {badge}
                    </span>
                )}
                {actions && <div className="ml-auto flex items-center gap-2">{actions}</div>}
            </div>
            <div className={sinPadding ? '' : 'p-4'}>{children}</div>
        </div>
    );
}

/* ── Tabla: estilos compartidos ───────────────────────────────────────── */
export function Th({ children, right = false, className = '' }: { children?: React.ReactNode; right?: boolean; className?: string }) {
    return (
        <th className={`px-3 py-2.5 text-[10px] font-bold uppercase tracking-wide whitespace-nowrap ${right ? 'text-right' : 'text-left'} ${className}`}
            style={{ color: 'var(--vp-navy)' }}>
            {children}
        </th>
    );
}

export const theadStyle: React.CSSProperties = {
    backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-surface))',
    borderBottom: '2px solid color-mix(in srgb, var(--color-primary) 20%, var(--color-border))',
};

/** Fondo cebra para filas: par → surface, impar → cloud suave. */
export const zebra = (i: number): React.CSSProperties => ({
    backgroundColor: i % 2 === 0 ? 'var(--color-surface)' : 'color-mix(in srgb, var(--color-bg) 65%, var(--color-surface))',
    borderTop: '1px solid var(--color-border)',
});

export function Empty({ text = 'Sin datos en el período' }: { text?: string }) {
    return <p className="text-center py-10 text-xs" style={{ color: 'var(--color-text-muted)' }}>{text}</p>;
}

/* ── Filtros: fechas + rangos rápidos + selects extra ─────────────────── */
export const fieldStyle: React.CSSProperties = {
    borderColor: 'var(--color-border)',
    backgroundColor: 'var(--color-bg)',
    color: 'var(--color-text)',
};

export function FieldDate({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
    return (
        <div>
            <label className="block text-[10px] font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            <input type="date" value={value} onChange={e => onChange(e.target.value)}
                className="w-full text-sm rounded-lg px-2.5 py-1.5 border outline-none focus:ring-2"
                style={{ ...fieldStyle, '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 35%, transparent)' } as React.CSSProperties} />
        </div>
    );
}

export function FieldSelect({ label, value, onChange, options }: {
    label: string; value: string; onChange: (v: string) => void;
    options: { value: string; label: string }[];
}) {
    return (
        <div>
            <label className="block text-[10px] font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)}
                className="w-full text-sm rounded-lg px-2.5 py-1.5 border outline-none focus:ring-2"
                style={{ ...fieldStyle, '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 35%, transparent)' } as React.CSSProperties}>
                {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
        </div>
    );
}

export function FiltrosReporte({ fechaDesde, fechaHasta, onChange, onClear, tieneFiltros, children }: {
    fechaDesde: string; fechaHasta: string;
    onChange: (patch: Record<string, string | undefined>) => void;
    onClear?: () => void; tieneFiltros?: boolean;
    /** Selects adicionales (FieldSelect / inputs propios del reporte). */
    children?: React.ReactNode;
}) {
    const rangos: Array<[string, () => { fecha_desde: string; fecha_hasta: string }]> = [
        ['Hoy', () => { const h = hoyISO(); return { fecha_desde: h, fecha_hasta: h }; }],
        ['7 días', () => rango(6)],
        ['Este mes', () => { const h = new Date(); return { fecha_desde: iso(new Date(h.getFullYear(), h.getMonth(), 1)), fecha_hasta: hoyISO() }; }],
        ['30 días', () => rango(29)],
    ];

    return (
        <div className="rounded-2xl px-4 py-3 mb-4"
            style={{
                background: 'linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)) 0%, var(--color-surface) 60%)',
                border: '1px solid var(--color-border)',
            }}>
            <div className="flex items-center gap-2 mb-2.5">
                <Filter size={13} style={{ color: 'var(--color-primary)' }} />
                <span className="text-[10px] font-bold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>Filtros</span>
                <div className="ml-auto flex items-center gap-1.5 flex-wrap">
                    {rangos.map(([label, calc]) => (
                        <button key={label} onClick={() => onChange(calc())}
                            className="text-[11px] font-semibold px-2.5 py-1 rounded-full border transition-colors hover:opacity-80"
                            style={{
                                borderColor: 'color-mix(in srgb, var(--color-primary) 25%, var(--color-border))',
                                color: 'var(--color-primary)',
                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 7%, transparent)',
                            }}>
                            {label}
                        </button>
                    ))}
                    {tieneFiltros && onClear && (
                        <button onClick={onClear}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full transition-colors hover:opacity-80"
                            style={{ color: 'var(--color-danger)', backgroundColor: 'color-mix(in srgb, var(--color-danger) 9%, transparent)' }}>
                            <X size={11} /> Limpiar
                        </button>
                    )}
                </div>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                <FieldDate label="Desde" value={fechaDesde} onChange={v => onChange({ fecha_desde: v })} />
                <FieldDate label="Hasta" value={fechaHasta} onChange={v => onChange({ fecha_hasta: v })} />
                {children}
            </div>
        </div>
    );
}

const iso = (d: Date) => {
    const off = d.getTimezoneOffset() * 60000;
    return new Date(d.getTime() - off).toISOString().slice(0, 10);
};
const hoyISO = () => iso(new Date());
const rango = (dias: number) => {
    const h = new Date(); const de = new Date(h); de.setDate(h.getDate() - dias);
    return { fecha_desde: iso(de), fecha_hasta: iso(h) };
};

/* ── Paginación con elipsis ───────────────────────────────────────────── */
export interface Paginado<T> {
    data: T[]; total: number; current_page: number; last_page: number;
    from?: number | null; to?: number | null;
}

export function Paginacion<T>({ paginado, ruta, filters }: {
    paginado: Paginado<T>; ruta: string; filters: Record<string, unknown>;
}) {
    const { current_page: cur, last_page: last, total, from, to } = paginado;
    if (last <= 1) return null;

    const pages: (number | '…')[] = [];
    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || Math.abs(p - cur) <= 1) pages.push(p);
        else if (pages[pages.length - 1] !== '…') pages.push('…');
    }

    const ir = (page: number) =>
        router.get(route(ruta), { ...filters, page }, { preserveState: true, preserveScroll: true });

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            style={{ borderTop: '1px solid var(--color-border)' }}>
            <span className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                {from != null && to != null ? `${from}–${to} de ${fmtInt(total)}` : `${fmtInt(total)} registros`}
            </span>
            <div className="flex items-center gap-1">
                {pages.map((p, i) => p === '…' ? (
                    <span key={`e${i}`} className="px-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>…</span>
                ) : (
                    <button key={p} onClick={() => ir(p)}
                        className="min-w-8 h-8 px-1.5 rounded-lg text-xs font-semibold transition-colors"
                        style={{
                            backgroundColor: p === cur ? 'var(--color-primary)' : 'color-mix(in srgb, var(--color-primary) 6%, transparent)',
                            color: p === cur ? '#fff' : 'var(--color-text-muted)',
                        }}>
                        {p}
                    </button>
                ))}
            </div>
        </div>
    );
}
