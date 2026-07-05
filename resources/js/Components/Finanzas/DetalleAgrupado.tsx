import { useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, Search, UserRound } from 'lucide-react';

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
    extra?: string | null;
    monto: number;
    tipo?: 'ingreso' | 'egreso' | string | null;
    user?: string | null;
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
    emptyMessage?: string;
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const fFecha = (s: string) =>
    new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

const colorDe = (tipo?: string | null) =>
    tipo === 'ingreso' ? 'var(--color-success)' : tipo === 'egreso' ? 'var(--color-danger)' : 'var(--color-text)';

const cardColor = (c?: DetalleCard['color']) => c ? `var(--color-${c})` : 'var(--color-text)';

export default function DetalleAgrupado({ cards, grupos, emptyMessage = 'Sin datos' }: Props) {
    const [q, setQ] = useState('');
    const [abiertos, setAbiertos] = useState<Set<string>>(new Set());

    const filtrados = useMemo(() => {
        if (!q.trim()) return grupos;
        const t = q.toLowerCase();
        return grupos
            .map(g => {
                const items = g.items.filter(i =>
                    `${i.descripcion} ${i.extra ?? ''} ${i.user ?? ''}`.toLowerCase().includes(t));
                const grupoMatch = `${g.titulo} ${g.subtitulo ?? ''}`.toLowerCase().includes(t);
                if (!grupoMatch && items.length === 0) return null;
                return { ...g, items: grupoMatch ? g.items : items };
            })
            .filter(Boolean) as DetalleGrupo[];
    }, [grupos, q]);

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
                                            ? (abierto ? <ChevronDown size={16} className="flex-shrink-0" style={{ color: 'var(--color-primary)' }} />
                                                       : <ChevronRight size={16} className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }} />)
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

                                {/* Sub-tabla del detalle: Descripción | Detalle | Usuario | Monto */}
                                {abierto && desplegable && (
                                    <div style={{ backgroundColor: 'color-mix(in srgb, var(--color-surface) 60%, var(--color-bg))' }}>
                                        <div className={`grid ${hayUsuarios ? 'grid-cols-12' : 'grid-cols-10'} gap-2 px-4 py-1.5 pl-10 text-[10px] font-bold uppercase tracking-wider`}
                                            style={{ color: 'var(--color-text-muted)', borderTop: '1px dashed var(--color-border)' }}>
                                            <span className="col-span-4">Descripción</span>
                                            <span className="col-span-3">Detalle</span>
                                            {hayUsuarios && <span className="col-span-2">Usuario</span>}
                                            <span className="col-span-3 text-right">Monto</span>
                                        </div>
                                        {g.items.map((it, i) => (
                                            <div key={i}
                                                className={`grid ${hayUsuarios ? 'grid-cols-12' : 'grid-cols-10'} gap-2 items-center px-4 py-2 pl-10`}
                                                style={{ borderTop: '1px dashed var(--color-border)' }}>
                                                <span className="col-span-4 text-sm truncate" title={it.descripcion} style={{ color: 'var(--color-text)' }}>
                                                    {it.descripcion}
                                                </span>
                                                <span className="col-span-3 text-[12px] truncate" title={it.extra ?? ''} style={{ color: 'var(--color-text-muted)' }}>
                                                    {it.extra ?? '—'}
                                                </span>
                                                {hayUsuarios && (
                                                    <span className="col-span-2 min-w-0">
                                                        {it.user ? (
                                                            <span className="inline-flex items-center gap-1 max-w-full text-[11px] px-1.5 py-0.5 rounded-full truncate"
                                                                title={`Registrado por ${it.user}`}
                                                                style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)', border: '1px solid var(--color-border)' }}>
                                                                <UserRound size={11} className="flex-shrink-0" />
                                                                <span className="truncate">{it.user}</span>
                                                            </span>
                                                        ) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                                    </span>
                                                )}
                                                <span className="col-span-3 text-right text-sm font-semibold" style={{ color: colorDe(it.tipo) }}>
                                                    {it.tipo === 'ingreso' ? '+' : it.tipo === 'egreso' ? '−' : ''}{money(it.monto)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
