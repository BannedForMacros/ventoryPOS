import { Fragment, useMemo, useState } from 'react';
import { ChevronDown, History, Search, UserRound } from 'lucide-react';
import Collapse from '@/Components/UI/Collapse';

/**
 * F11 — Componente NORMALIZADO para los detalles financieros.
 *
 * Estructura única para todos los modales del balance:
 *  - Cards de resumen arriba.
 *  - Buscador propio (filtra grupos e items).
 *  - TABLA de grupos: Fecha | Operaciones | Monto. Cada fila se despliega
 *    y muestra la sub-tabla: Descripción | Detalle | Usuario | Monto.
 *  - Cada fila con su AUDITORÍA (quién la registró). Trazabilidad total.
 */

export interface DetalleCard {
    label: string;
    valor: number | string;
    color?: 'success' | 'danger' | 'warning' | 'primary';
    esNumero?: boolean; // conteo, no dinero
    esTexto?: boolean;  // texto libre (nombre de proveedor, etc.)
}

export interface DetalleItem {
    descripcion: string;
    sub?: string | null;   // línea secundaria bajo la primera columna (ej. fecha)
    monto: number;
    tipo?: 'ingreso' | 'egreso' | string | null;
    user?: string | null;
    /** Mini-historial (ej. abonos de la venta) para trazabilidad perfecta. */
    historial?: { fecha: string; descripcion: string; monto: number; user?: string | null }[];
    [campo: string]: any; // columnas a medida por categoría (itemCols)
}

/** Columnas propias de cada categoría (las define el backend). */
export interface DetalleCol {
    campo: string;
    label: string;
}

export interface DetalleGrupo {
    id: string;
    titulo: string;      // fecha Y-m-d (esFecha) o nombre libre
    subtitulo?: string;
    esFecha?: boolean;
    monto: number;
    tipo?: 'ingreso' | 'egreso' | 'neutro' | string | null;
    items: DetalleItem[];
}

interface Props {
    cards: DetalleCard[];
    grupos: DetalleGrupo[];
    itemCols?: DetalleCol[];   // columnas del detalle, a medida por categoría
    montoLabel?: string;       // "Monto", "Saldo", "Pasivo hoy"...
    emptyMessage?: string;
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const fFecha = (s: string) =>
    new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

const colorDe = (tipo?: string | null) =>
    tipo === 'ingreso' ? 'var(--color-success)' : tipo === 'egreso' ? 'var(--color-danger)' : 'var(--color-text)';

const cardColor = (c?: DetalleCard['color']) => c ? `var(--color-${c})` : 'var(--color-text)';

export default function DetalleAgrupado({ cards, grupos, itemCols, montoLabel = 'Monto', emptyMessage = 'Sin datos' }: Props) {
    const cols: DetalleCol[] = itemCols?.length ? itemCols : [{ campo: 'descripcion', label: 'Descripción' }];
    const [q, setQ] = useState('');
    const [abiertos, setAbiertos] = useState<Set<string>>(new Set());
    // Historiales colapsados por defecto (imagina 100 ventas: solo abres el que te interesa)
    const [histAbiertos, setHistAbiertos] = useState<Set<string>>(new Set());

    function toggleHist(key: string) {
        setHistAbiertos(prev => {
            const n = new Set(prev);
            n.has(key) ? n.delete(key) : n.add(key);
            return n;
        });
    }

    const filtrados = useMemo(() => {
        if (!q.trim()) return grupos;
        const t = q.toLowerCase();
        return grupos
            .map(g => {
                const items = g.items.filter(i =>
                    (cols.map(c => String(i[c.campo] ?? '')).join(' ') + ' ' + (i.user ?? '')).toLowerCase().includes(t));
                const grupoMatch = `${g.titulo} ${g.subtitulo ?? ''}`.toLowerCase().includes(t);
                if (!grupoMatch && items.length === 0) return null;
                return { ...g, items: grupoMatch ? g.items : items };
            })
            .filter(Boolean) as DetalleGrupo[];
    }, [grupos, q, cols]);

    function toggle(id: string) {
        setAbiertos(prev => {
            const n = new Set(prev);
            n.has(id) ? n.delete(id) : n.add(id);
            return n;
        });
    }

    const hayUsuarios = grupos.some(g => g.items.some(i => i.user));

    return (
        <div className="space-y-4">
            {/* Cards de resumen */}
            {cards.length > 0 && (
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    {cards.map((c, i) => (
                        <div key={i} className="rounded-xl px-4 py-3"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <p className="text-[10px] font-semibold uppercase tracking-wider truncate" style={{ color: 'var(--color-text-muted)' }}>
                                {c.label}
                            </p>
                            <p className={`font-bold truncate ${c.esTexto ? 'text-sm mt-1' : 'text-xl'}`} style={{ color: cardColor(c.color) }}>
                                {c.esTexto ? c.valor : c.esNumero ? c.valor : money(c.valor)}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            {/* Buscador propio del componente */}
            <div className="relative">
                <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                <input
                    value={q}
                    onChange={e => setQ(e.target.value)}
                    placeholder="Buscar por descripción, usuario o fecha..."
                    className="w-full text-sm rounded-xl pl-9 pr-3 py-2.5 border outline-none"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                />
            </div>

            {/* TABLA de grupos */}
            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                {/* Cabecera */}
                <div className="grid grid-cols-12 gap-2 px-4 py-2.5 text-[11px] font-bold uppercase tracking-wider"
                    style={{ backgroundColor: 'var(--color-surface)', color: 'var(--color-text-muted)', borderBottom: '1px solid var(--color-border)' }}>
                    <span className="col-span-7">Fecha / Concepto</span>
                    <span className="col-span-2 text-center">Operaciones</span>
                    <span className="col-span-3 text-right">Monto</span>
                </div>

                <div className="max-h-[55vh] overflow-y-auto">
                    {filtrados.length === 0 && (
                        <p className="text-sm text-center py-10" style={{ color: 'var(--color-text-muted)' }}>{emptyMessage}</p>
                    )}
                    {filtrados.map((g, idx) => {
                        const abierto = abiertos.has(g.id) || (!!q.trim() && g.items.length > 0);
                        const desplegable = g.items.length > 0;
                        return (
                            <div key={g.id} style={{ borderTop: idx > 0 ? '1px solid var(--color-border)' : 'none' }}>
                                {/* Fila del grupo */}
                                <button
                                    onClick={() => desplegable && toggle(g.id)}
                                    className={`w-full grid grid-cols-12 gap-2 items-center px-4 py-3 text-left transition-colors ${desplegable ? 'hover:bg-black/[0.03] cursor-pointer' : 'cursor-default'}`}
                                    style={{ backgroundColor: abierto ? 'color-mix(in srgb, var(--color-primary) 4%, var(--color-bg))' : 'transparent' }}
                                >
                                    <span className="col-span-7 flex items-center gap-2 min-w-0">
                                        {desplegable
                                            ? <ChevronDown size={16}
                                                className={`flex-shrink-0 transition-transform duration-300 ${abierto ? '' : '-rotate-90'}`}
                                                style={{ color: abierto ? 'var(--color-primary)' : 'var(--color-text-muted)' }} />
                                            : <span className="w-4 flex-shrink-0" />}
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold capitalize truncate" style={{ color: 'var(--color-text)' }}>
                                                {g.esFecha ? fFecha(g.titulo) : g.titulo}
                                            </span>
                                            {g.subtitulo && (
                                                <span className="block text-[11px] truncate" style={{ color: 'var(--color-text-muted)' }}>{g.subtitulo}</span>
                                            )}
                                        </span>
                                    </span>
                                    <span className="col-span-2 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                        {desplegable ? g.items.length : '—'}
                                    </span>
                                    <span className="col-span-3 text-right text-sm font-bold" style={{ color: colorDe(g.tipo) }}>
                                        {g.tipo === 'ingreso' ? '+' : g.tipo === 'egreso' ? '−' : ''}{money(g.monto)}
                                    </span>
                                </button>

                                {/* Sub-tabla del detalle: columnas A MEDIDA de la categoría */}
                                {desplegable && (
                                    <Collapse open={abierto}>
                                    <div className="overflow-x-auto"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-surface) 60%, var(--color-bg))', borderTop: '1px dashed var(--color-border)' }}>
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="text-[10px] font-bold uppercase tracking-wider"
                                                    style={{ color: 'var(--color-text-muted)' }}>
                                                    {cols.map(c => (
                                                        <th key={c.campo} className="text-left font-bold px-3 py-1.5 first:pl-10">{c.label}</th>
                                                    ))}
                                                    {hayUsuarios && <th className="text-left font-bold px-3 py-1.5">Usuario</th>}
                                                    <th className="text-right font-bold px-3 py-1.5 pr-4">{montoLabel}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {g.items.map((it, i) => (
                                                    <Fragment key={i}>
                                                        <tr style={{ borderTop: '1px dashed var(--color-border)' }}>
                                                            {cols.map((c, ci) => (
                                                                <td key={c.campo}
                                                                    className={`px-3 py-2 first:pl-10 align-top whitespace-normal break-words ${ci === 0 ? '' : 'text-[12px]'}`}
                                                                    style={{ color: ci === 0 ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                                                                    {it[c.campo] ?? '—'}
                                                                    {/* Sub-línea (fecha, contexto) bajo la primera columna */}
                                                                    {ci === 0 && it.sub && (
                                                                        <span className="block text-[11px] mt-0.5 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                                            {it.sub}
                                                                        </span>
                                                                    )}
                                                                    {/* Botón desplegable del historial (colapsado por defecto) */}
                                                                    {ci === 0 && (it.historial?.length ?? 0) > 0 && (
                                                                        <button
                                                                            onClick={() => toggleHist(`${g.id}-${i}`)}
                                                                            className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full transition-colors hover:opacity-80"
                                                                            style={{
                                                                                color: 'var(--color-primary)',
                                                                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))',
                                                                                border: '1px solid color-mix(in srgb, var(--color-primary) 25%, transparent)',
                                                                            }}
                                                                        >
                                                                            <History size={11} className="flex-shrink-0" />
                                                                            Historial ({it.historial!.length})
                                                                            <ChevronDown size={11}
                                                                                className={`flex-shrink-0 transition-transform duration-300 ${histAbiertos.has(`${g.id}-${i}`) ? '' : '-rotate-90'}`} />
                                                                        </button>
                                                                    )}
                                                                </td>
                                                            ))}
                                                            {hayUsuarios && (
                                                                <td className="px-3 py-2 align-top">
                                                                    {it.user ? (
                                                                        <span className="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-full"
                                                                            title={`Registrado por ${it.user}`}
                                                                            style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)', border: '1px solid var(--color-border)' }}>
                                                                            <UserRound size={11} className="flex-shrink-0" />
                                                                            <span className="truncate max-w-[90px]">{it.user}</span>
                                                                        </span>
                                                                    ) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                                                </td>
                                                            )}
                                                            <td className="px-3 py-2 pr-4 text-right font-semibold whitespace-nowrap align-top"
                                                                style={{ color: colorDe(it.tipo) }}>
                                                                {it.tipo === 'ingreso' ? '+' : it.tipo === 'egreso' ? '−' : ''}{money(it.monto)}
                                                            </td>
                                                        </tr>

                                                        {/* Mini-historial (abonos/pagos) — desplegable animado, tabla simétrica */}
                                                        {(it.historial?.length ?? 0) > 0 && (
                                                            <tr>
                                                                <td colSpan={cols.length + (hayUsuarios ? 2 : 1)} className="p-0">
                                                                    <Collapse open={histAbiertos.has(`${g.id}-${i}`)}>
                                                                    <div className="mx-3 mb-2 ml-14 rounded-lg px-3 py-1.5 space-y-1"
                                                                        style={{ backgroundColor: 'var(--color-bg)', border: '1px dashed var(--color-border)' }}>
                                                                        {/* Cabecera de columnas del historial */}
                                                                        <div className="grid grid-cols-[78px_1fr_minmax(90px,auto)_96px] gap-2 text-[10px] font-bold uppercase tracking-wider pb-1"
                                                                            style={{ color: 'var(--color-text-muted)', borderBottom: '1px solid var(--color-border)' }}>
                                                                            <span>Fecha</span>
                                                                            <span>Detalle</span>
                                                                            <span>Usuario</span>
                                                                            <span className="text-right">Monto</span>
                                                                        </div>
                                                                        {it.historial!.map((h, hi) => (
                                                                            <div key={hi}
                                                                                className="grid grid-cols-[78px_1fr_minmax(90px,auto)_96px] gap-2 items-center text-[12px]"
                                                                                style={{ borderTop: hi > 0 ? '1px dashed var(--color-border)' : 'none', paddingTop: hi > 0 ? 4 : 0 }}>
                                                                                <span className="whitespace-nowrap font-medium" style={{ color: 'var(--color-text-muted)' }}>{h.fecha}</span>
                                                                                <span className="min-w-0 truncate" title={h.descripcion} style={{ color: 'var(--color-text)' }}>{h.descripcion}</span>
                                                                                <span className="min-w-0">
                                                                                    {h.user ? (
                                                                                        <span className="inline-flex items-center gap-1 max-w-full text-[11px] px-1.5 py-0.5 rounded-full"
                                                                                            title={`Registrado por ${h.user}`}
                                                                                            style={{ backgroundColor: 'var(--color-surface)', color: 'var(--color-text-muted)', border: '1px solid var(--color-border)' }}>
                                                                                            <UserRound size={10} className="flex-shrink-0" />
                                                                                            <span className="truncate">{h.user}</span>
                                                                                        </span>
                                                                                    ) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                                                                </span>
                                                                                <span className="font-semibold whitespace-nowrap text-right" style={{ color: 'var(--color-success)' }}>
                                                                                    +{money(h.monto)}
                                                                                </span>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                    </Collapse>
                                                                </td>
                                                            </tr>
                                                        )}
                                                    </Fragment>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    </Collapse>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
