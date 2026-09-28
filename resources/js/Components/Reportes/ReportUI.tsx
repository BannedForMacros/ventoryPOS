import { router } from '@inertiajs/react';
import { useState } from 'react';
import { AlertTriangle, Calendar, Filter, SlidersHorizontal, X } from 'lucide-react';
import Select from '@/Components/UI/Select';

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
            <label className="block text-[13px] font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            <input type="date" value={value} onChange={e => onChange(e.target.value)} aria-label={label}
                className="w-full text-sm rounded-xl px-3 py-2 border outline-none focus:ring-[3px] focus:border-[var(--color-primary)]"
                style={{ ...fieldStyle, backgroundColor: 'var(--color-surface)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 15%, transparent)' } as React.CSSProperties} />
        </div>
    );
}

export function FieldSelect({ label, value, onChange, options }: {
    label: string; value: string; onChange: (v: string) => void;
    options: { value: string; label: string }[];
}) {
    return (
        <div>
            <label className="block text-[13px] font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            <Select value={value} onChange={v => onChange(String(v))} options={options} ariaLabel={label} />
        </div>
    );
}

export function FiltrosReporte({ fechaDesde, fechaHasta, onChange, onClear, tieneFiltros, rangosExtra = [], children }: {
    fechaDesde: string; fechaHasta: string;
    onChange: (patch: Record<string, string | undefined>) => void;
    onClear?: () => void; tieneFiltros?: boolean;
    /** Rangos rápidos adicionales (ej. ['Mes pasado', ...]) tras los por defecto. */
    rangosExtra?: Array<[string, () => { fecha_desde: string; fecha_hasta: string }]>;
    /** Selects adicionales (FieldSelect / inputs propios del reporte). */
    children?: React.ReactNode;
}) {
    const rangos = [...rangosBase(), ...rangosExtra];

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

/** Rangos rápidos por defecto de todos los reportes. */
export function rangosBase(): Array<[string, () => { fecha_desde: string; fecha_hasta: string }]> {
    return [
        ['Hoy', () => { const h = hoyISO(); return { fecha_desde: h, fecha_hasta: h }; }],
        ['7 días', () => rango(6)],
        ['Este mes', () => { const h = new Date(); return { fecha_desde: iso(new Date(h.getFullYear(), h.getMonth(), 1)), fecha_hasta: hoyISO() }; }],
        ['30 días', () => rango(29)],
    ];
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

/* ── Recarga parcial de tablas ────────────────────────────────────────── */
/**
 * Pide al servidor SOLO las props indicadas (partial reload de Inertia),
 * sin mover el scroll ni recalcular el resto del reporte. `cargando` sirve
 * para atenuar la tabla mientras llega la respuesta.
 */
export function useRecargaTabla(ruta: string, only: string[]) {
    const [cargando, setCargando] = useState(false);
    const recargar = (params: Record<string, unknown>) =>
        router.get(route(ruta), params as Record<string, string>, {
            only, preserveState: true, preserveScroll: true, replace: true,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    return { cargando, recargar };
}

/* ── Paginación con elipsis ───────────────────────────────────────────── */
export interface Paginado<T> {
    data: T[]; total: number; current_page: number; last_page: number;
    from?: number | null; to?: number | null;
}

export function Paginacion<T>({ paginado, ruta, filters, only, onIr }: {
    paginado: Paginado<T>; ruta: string; filters: Record<string, unknown>;
    /** Props a recargar al cambiar de página (partial reload). Sin esto recarga todo. */
    only?: string[];
    /** Alternativa: la página decide cómo navegar (ej. con useRecargaTabla). */
    onIr?: (page: number) => void;
}) {
    const { current_page: cur, last_page: last, total, from, to } = paginado;
    if (last <= 1) return null;

    const pages: (number | '…')[] = [];
    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || Math.abs(p - cur) <= 1) pages.push(p);
        else if (pages[pages.length - 1] !== '…') pages.push('…');
    }

    const ir = (page: number) => onIr
        ? onIr(page)
        : router.get(route(ruta), { ...filters, page } as unknown as Record<string, string>, { preserveState: true, preserveScroll: true, ...(only ? { only } : {}) });

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

/* ── Panel blanco del reporte con ícono de color ──────────────────────── */
/**
 * `color` identifica el tema del panel (pagos = mint, personas = sky, …);
 * `tinta` es su versión oscura para íconos claros que no se leen en blanco.
 */
export function Panel({ icon, titulo, detalle, color = 'var(--color-primary)', tinta, children, className = '', sinPadding = false }: {
    icon?: React.ReactNode; titulo?: string; detalle?: React.ReactNode;
    color?: string; tinta?: string;
    children: React.ReactNode; className?: string; sinPadding?: boolean;
}) {
    return (
        <div className={`rounded-2xl overflow-hidden flex flex-col ${className}`}
            style={{
                backgroundColor: 'var(--color-surface)',
                border: '1px solid color-mix(in srgb, var(--color-border) 85%, transparent)',
                boxShadow: '0 1px 2px 0 rgb(15 76 129 / 0.05), 0 6px 16px -10px rgb(15 76 129 / 0.12)',
            }}>
            {titulo && (
                <div className="flex items-center gap-2.5 px-4 pt-4 pb-3">
                    {icon && (
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg flex-shrink-0"
                            style={{ backgroundColor: `color-mix(in srgb, ${color} 16%, var(--color-surface))`, color: tinta ?? color }}>
                            {icon}
                        </span>
                    )}
                    <h3 className="text-base font-bold flex-1 min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{titulo}</h3>
                    {detalle && <span className="text-[13px] tabular-nums flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>{detalle}</span>}
                </div>
            )}
            <div className={`flex-1 flex flex-col ${sinPadding ? '' : 'px-4 pb-4'}`}>{children}</div>
        </div>
    );
}

/* ── Fechas y textos comunes ──────────────────────────────────────────── */
const aFecha = (d: string) => new Date(d + 'T00:00:00');
export const fechaLarga = (d: string) => aFecha(d).toLocaleDateString('es-PE', { day: 'numeric', month: 'long', year: 'numeric' });
export const fechaCorta = (d: string) => aFecha(d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short' });
export const pct = (parte: number, total: number) => (total > 0 ? Math.round((parte / total) * 100) : 0);
export const plural = (n: number, uno: string, varios: string) => `${fmtInt(n)} ${n === 1 ? uno : varios}`;

/** Rango rápido que coincide con las fechas filtradas ('Hoy', 'Este mes', …) o undefined. */
export function rangoActivo(desde: string, hasta: string): string | undefined {
    return rangosBase().find(([, calc]) => {
        const r = calc();
        return r.fecha_desde === desde && r.fecha_hasta === hasta;
    })?.[0];
}

/** "hoy", "este mes", "en los últimos 7 días"… para frases como "Vendiste este mes". */
export function frasePeriodo(desde: string, hasta: string): string {
    const r = rangoActivo(desde, hasta);
    return r === 'Hoy' ? 'hoy'
        : r === 'Este mes' ? 'este mes'
        : r ? `en los últimos ${r}`
        : 'en el periodo';
}

/* ── Encabezado de reporte: título, periodo, rangos rápidos y filtros ─── */
/**
 * Los filtros propios del reporte van como `children` dentro de "Más filtros"
 * (las fechas ya vienen incluidas). `avanzados` = cuántos de esos filtros
 * están activos: abre el panel al entrar y muestra el contador.
 */
export function EncabezadoReporte({ titulo, fechaDesde, fechaHasta, filtrar, avanzados = 0, onLimpiar, children }: {
    titulo: string; fechaDesde: string; fechaHasta: string;
    filtrar: (patch: Record<string, string | undefined>) => void;
    avanzados?: number; onLimpiar?: () => void;
    children?: React.ReactNode;
}) {
    const [abierto, setAbierto] = useState(avanzados > 0);
    const activo = rangoActivo(fechaDesde, fechaHasta);
    const periodo = fechaDesde === fechaHasta ? fechaLarga(fechaDesde)
        : `Del ${fechaLarga(fechaDesde)} al ${fechaLarga(fechaHasta)}`;

    return (
        <>
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>
                        {titulo}
                    </h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>{periodo}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex rounded-xl p-1" role="group" aria-label="Periodo rápido"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 9%, var(--color-surface))' }}>
                        {rangosBase().map(([label, calc]) => (
                            <button key={label} onClick={() => filtrar(calc())} aria-pressed={activo === label}
                                className="text-sm font-semibold px-3.5 py-1.5 rounded-lg transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1"
                                style={{
                                    backgroundColor: activo === label ? 'var(--vp-navy)' : 'transparent',
                                    color: activo === label ? '#fff' : 'var(--vp-navy)',
                                    outlineColor: 'var(--color-primary)',
                                }}>
                                {label}
                            </button>
                        ))}
                    </div>
                    <button onClick={() => setAbierto(v => !v)} aria-expanded={abierto}
                        className="inline-flex items-center gap-2 text-sm font-semibold px-3.5 py-2 rounded-xl border transition-colors hover:bg-black/[0.03] focus-visible:outline focus-visible:outline-2"
                        style={{
                            borderColor: abierto ? 'var(--color-primary)' : 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)', color: 'var(--color-text)',
                            outlineColor: 'var(--color-primary)',
                        }}>
                        <SlidersHorizontal size={16} style={{ color: 'var(--color-primary)' }} />
                        Más filtros
                        {avanzados > 0 && (
                            <span className="min-w-5 h-5 px-1.5 rounded-full text-xs font-bold inline-flex items-center justify-center text-white"
                                style={{ backgroundColor: 'var(--color-primary)' }}>
                                {avanzados}
                            </span>
                        )}
                    </button>
                </div>
            </div>

            {abierto && (
                <div className="rounded-2xl p-4 mb-3 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 items-end"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <FieldDate label="Desde" value={fechaDesde} onChange={v => filtrar({ fecha_desde: v })} />
                    <FieldDate label="Hasta" value={fechaHasta} onChange={v => filtrar({ fecha_hasta: v })} />
                    {children}
                    {avanzados > 0 && onLimpiar && (
                        <button onClick={onLimpiar}
                            className="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg transition-colors hover:opacity-80"
                            style={{ color: 'var(--vp-coral-ink)', backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)' }}>
                            <X size={15} /> Quitar filtros
                        </button>
                    )}
                </div>
            )}
        </>
    );
}

/* ── Banda navy de resumen y sus avisos ───────────────────────────────── */
export function Banda({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <section className={`rounded-[22px] overflow-hidden text-white flex flex-col ${className}`}
            style={{ backgroundColor: 'var(--vp-navy)', boxShadow: '0 14px 30px -18px rgb(15 76 129 / 0.8)' }}>
            {children}
        </section>
    );
}

export function AvisoBanda({ color, onClick, children }: { color: string; onClick: () => void; children: React.ReactNode }) {
    return (
        <button onClick={onClick}
            className="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[13px] font-semibold text-left transition-colors hover:bg-white/15 focus-visible:outline focus-visible:outline-2"
            style={{ backgroundColor: 'rgb(255 255 255 / 0.1)', border: `1px solid color-mix(in srgb, ${color} 55%, transparent)`, color: '#fff', outlineColor: color }}>
            <AlertTriangle size={14} className="flex-shrink-0" style={{ color }} />
            <span>{children}</span>
            <span className="underline underline-offset-2" style={{ color }}>Ver</span>
        </button>
    );
}

/* ── Filas con barra de participación (medios, vendedores, clientes…) ─── */
export function Filas({ items, avatar = false, vacio, derecha, enColumnas = false }: {
    items: { clave: string | number; label: string; valor: number; color: string; nota?: string }[];
    avatar?: boolean; vacio: string;
    /** Dos columnas desde md: para paneles a todo el ancho. */
    enColumnas?: boolean;
    /** Reemplaza el % junto a la barra (ej. el margen de la categoría). */
    derecha?: (i: number) => React.ReactNode;
}) {
    const total = items.reduce((s, it) => s + Math.max(0, it.valor), 0);
    if (items.length === 0 || total <= 0) return <Empty text={vacio} />;
    return (
        <ul className={enColumnas ? 'grid md:grid-cols-2 gap-x-10 gap-y-3' : 'space-y-3'}>
            {items.map((it, i) => {
                const part = pct(Math.max(0, it.valor), total);
                return (
                    <li key={it.clave} className="flex items-center gap-3">
                        {avatar ? (
                            <span className="h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
                                style={{ backgroundColor: it.color }}>
                                {it.label.trim().charAt(0).toUpperCase()}
                            </span>
                        ) : (
                            <span className="h-3 w-3 rounded flex-shrink-0" style={{ backgroundColor: it.color }} />
                        )}
                        <div className="min-w-0 flex-1">
                            <div className="flex items-baseline justify-between gap-3">
                                <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }} title={it.label}>{it.label}</p>
                                <p className="text-[15px] font-bold tabular-nums whitespace-nowrap"
                                    style={{ color: it.valor < 0 ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}>{fmtS(it.valor)}</p>
                            </div>
                            <div className="flex items-center gap-2 mt-1">
                                <div className="h-1.5 flex-1 rounded-full overflow-hidden" style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 65%, transparent)' }}>
                                    <div className="h-full rounded-full" style={{ width: `${Math.max(2, part)}%`, backgroundColor: it.color }} />
                                </div>
                                <span className="text-xs font-semibold tabular-nums min-w-9 text-right whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                    {derecha ? derecha(i) : `${part}%`}
                                </span>
                            </div>
                            {it.nota && <p className="text-[13px] mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>{it.nota}</p>}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/* ── Barras por periodo (día a día / hora a hora) ─────────────────────── */
export interface Punto {
    clave: string;
    /** Rótulo corto del eje ("5", "14"). */
    eje: string;
    /** Rótulo del detalle ("5 set.", "14:00"). */
    titulo: string;
    valor: number;
    /** Complemento del detalle: "en 53 ventas", "sobre S/ 1,200 vendidos". */
    detalle?: string;
}

const isoDia = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/** Todos los días del periodo (hasta 93), para rellenar con 0 los días sin datos. */
export function diasDelPeriodo(desde: string, hasta: string): { clave: string; eje: string; titulo: string }[] {
    const out: { clave: string; eje: string; titulo: string }[] = [];
    const fin = aFecha(hasta);
    for (let d = aFecha(desde), n = 0; d <= fin && n < 93; d.setDate(d.getDate() + 1), n++) {
        const k = isoDia(d);
        out.push({ clave: k, eje: String(d.getDate()), titulo: fechaCorta(k) });
    }
    return out;
}

/**
 * Barras verticales con el mejor punto destacado y detalle al pasar el mouse.
 * Soporta negativos (pérdidas): crecen hacia abajo desde la línea de cero, en coral.
 */
export function Barras({ puntos, titulo, oscuro = false, alto = 'h-36', vacio, etiquetaMejor = 'Mejor momento' }: {
    puntos: Punto[]; titulo?: string; oscuro?: boolean; alto?: string; vacio: string; etiquetaMejor?: string;
}) {
    const [hover, setHover] = useState<number | null>(null);
    const tenue = oscuro ? 'rgb(255 255 255 / 0.65)' : 'var(--color-text-muted)';
    const fuerte = oscuro ? '#fff' : 'var(--color-text)';

    if (!puntos.some(p => p.valor !== 0)) {
        return (
            <div className={`flex items-center justify-center rounded-xl text-sm ${alto}`}
                style={{ color: tenue, border: `1px dashed ${oscuro ? 'rgb(255 255 255 / 0.2)' : 'var(--color-border)'}` }}>
                {vacio}
            </div>
        );
    }

    const maxPos = Math.max(0, ...puntos.map(p => p.valor));
    const maxNeg = Math.max(0, ...puntos.map(p => -p.valor));
    const rango = maxPos + maxNeg;
    // Fracción del alto que ocupa la parte positiva; la negativa queda debajo del cero.
    const zonaPos = rango > 0 ? maxPos / rango : 1;
    const mejorIdx = puntos.reduce((m, p, i) => (p.valor > puntos[m].valor ? i : m), 0);
    const focoIdx = hover ?? mejorIdx;
    const foco = puntos[focoIdx];
    const paso = puntos.length > 16 ? Math.ceil(puntos.length / 8) : 1;

    const color = (i: number, p: Punto) => {
        if (p.valor < 0) return i === focoIdx ? 'var(--vp-coral)' : 'color-mix(in srgb, var(--vp-coral) 70%, transparent)';
        if (i === focoIdx) return oscuro ? 'var(--vp-mint)' : 'var(--vp-navy)';
        if (p.valor === 0) return oscuro ? 'rgb(255 255 255 / 0.1)' : 'color-mix(in srgb, var(--color-border) 70%, transparent)';
        return oscuro ? 'rgb(255 255 255 / 0.42)' : 'color-mix(in srgb, var(--vp-sky) 45%, var(--color-surface))';
    };

    return (
        <div className="min-w-0 flex flex-col h-full">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 mb-3">
                {titulo && <p className="text-sm font-semibold" style={{ color: fuerte }}>{titulo}</p>}
                <p className="text-[13px] tabular-nums" style={{ color: tenue }}>
                    {hover === null ? `${etiquetaMejor}: ` : ''}
                    <strong className="font-semibold" style={{ color: fuerte }}>{foco.titulo}</strong>
                    {', '}
                    <span style={{ color: foco.valor < 0 ? (oscuro ? '#FFB199' : 'var(--vp-coral-ink)') : undefined }}>{fmtS(foco.valor)}</span>
                    {foco.detalle ? ` ${foco.detalle}` : ''}
                </p>
            </div>
            <div className={`relative flex gap-[3px] ${alto}`} onMouseLeave={() => setHover(null)}>
                {maxNeg > 0 && (
                    <div className="absolute inset-x-0 pointer-events-none" aria-hidden
                        style={{ top: `${zonaPos * 100}%`, borderTop: `1px solid ${oscuro ? 'rgb(255 255 255 / 0.35)' : 'var(--color-border)'}` }} />
                )}
                {puntos.map((p, i) => (
                    <div key={p.clave} className="flex-1 h-full flex flex-col" onMouseEnter={() => setHover(i)}>
                        <div className="flex items-end" style={{ height: `${zonaPos * 100}%` }}>
                            {p.valor >= 0 && (
                                <div className="w-full rounded-t-[4px] transition-colors duration-150"
                                    style={{ height: `${p.valor > 0 ? Math.max(4, (p.valor / maxPos) * 100) : 3}%`, backgroundColor: color(i, p) }} />
                            )}
                        </div>
                        <div className="flex items-start flex-1">
                            {p.valor < 0 && (
                                <div className="w-full rounded-b-[4px] transition-colors duration-150"
                                    style={{ height: `${Math.max(6, (-p.valor / maxNeg) * 100)}%`, backgroundColor: color(i, p) }} />
                            )}
                        </div>
                    </div>
                ))}
            </div>
            <div className="flex gap-[3px] mt-1.5">
                {puntos.map((p, i) => (
                    <span key={p.clave} className="flex-1 text-center text-xs tabular-nums"
                        style={{ color: i === focoIdx ? (oscuro ? '#fff' : 'var(--vp-navy)') : tenue, fontWeight: i === focoIdx ? 700 : 400 }}>
                        {i % paso === 0 || i === focoIdx ? p.eje : ''}
                    </span>
                ))}
            </div>
        </div>
    );
}
