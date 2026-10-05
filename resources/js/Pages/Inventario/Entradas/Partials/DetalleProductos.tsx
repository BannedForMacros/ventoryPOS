import React, { useEffect, useMemo, useRef, useState } from 'react';
import { PackagePlus, Search, Trash2, CornerDownLeft } from 'lucide-react';
import Select from '@/Components/UI/Select';

/**
 * Sección "Productos" de Nueva / Editar entrada, pensada para cargar muchos
 * productos rápido y con el teclado:
 *   buscar (↑↓ Enter) → Cantidad (Enter) → Precio (Enter) → vuelve al buscador.
 * Filas compactas en forma de tabla con el nombre COMPLETO (sin cortar), para
 * monitores pequeños. Si el producto ya está en la lista, lleva a su fila en
 * vez de duplicarlo.
 */

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
export interface ProductoEntrada { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }

export interface DetalleEntradaRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    precio_modo: 'unitario' | 'total';
    precio_total: string;
    numero_documento: string;
}

interface Props {
    productos:      ProductoEntrada[];
    detalles:       DetalleEntradaRow[];
    setDetalles:    React.Dispatch<React.SetStateAction<DetalleEntradaRow[]>>;
    setDetalle:     (i: number, field: keyof DetalleEntradaRow, value: string | number) => void;
    setPrecioModo:  (i: number, modo: 'unitario' | 'total') => void;
    removeDetalle:  (i: number) => void;
    subtotal:       (d: DetalleEntradaRow) => number;
    cantidadBase:   (d: DetalleEntradaRow) => number;
    facturaPorItem: boolean;
    errors:         Record<string, string>;
    total:          number;
}

/** Minúsculas y sin tildes: "cañería" encuentra "CANERIA" y al revés. */
const normalizar = (t: string) => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

const inputCls = 'w-full h-9 rounded-lg border px-2.5 text-sm outline-none transition-colors focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[color-mix(in_srgb,var(--color-primary)_25%,transparent)]';

export default function DetalleProductos({
    productos, detalles, setDetalles, setDetalle, setPrecioModo, removeDetalle,
    subtotal, cantidadBase, facturaPorItem, errors, total,
}: Props) {
    const [q, setQ] = useState('');
    const [abierto, setAbierto] = useState(false);
    const [activo, setActivo] = useState(0);
    const [destacada, setDestacada] = useState<number | null>(null);
    const buscadorRef = useRef<HTMLInputElement>(null);
    const [enfocar, setEnfocar] = useState<{ fila: number; campo: 'cant' | 'precio' } | null>(null);

    const porId = useMemo(() => new Map(productos.map(p => [p.id, p])), [productos]);
    const indice = useMemo(
        () => productos.map(p => ({ p, texto: normalizar(`${p.codigo ?? ''} ${p.nombre}`) })),
        [productos],
    );

    // Búsqueda local (el catálogo ya viene cargado): cada palabra debe aparecer,
    // en cualquier orden. El código exacto va primero.
    const resultados = useMemo(() => {
        const t = normalizar(q.trim());
        if (!t) return [];
        const palabras = t.split(/\s+/);
        const hits = indice.filter(x => palabras.every(w => x.texto.includes(w)));
        // Orden: código exacto → el nombre EMPIEZA con lo buscado → empieza con
        // la primera palabra → el resto; a igualdad, el nombre más corto.
        // Así "fierro 1/2" trae "Fierro 1/2 …" antes que "Broca para fierro 1/2".
        const puntaje = (x: { p: ProductoEntrada }) => {
            const nombre = normalizar(x.p.nombre);
            if (normalizar(x.p.codigo ?? '') === t) return 0;
            if (nombre.startsWith(t)) return 1;
            if (nombre.startsWith(palabras[0])) return 2;
            return 3;
        };
        hits.sort((a, b) => puntaje(a) - puntaje(b) || a.p.nombre.length - b.p.nombre.length);
        return hits.slice(0, 30).map(x => x.p);
    }, [q, indice]);

    useEffect(() => { setActivo(0); }, [q]);

    // Con ↑↓ la lista se desplaza para que el resultado elegido siempre se vea.
    const listaRef = useRef<HTMLUListElement>(null);
    useEffect(() => {
        listaRef.current?.querySelector<HTMLElement>(`[data-idx="${activo}"]`)?.scrollIntoView({ block: 'nearest' });
    }, [activo]);

    // Mover el foco después de que la fila existe en el DOM.
    useEffect(() => {
        if (!enfocar) return;
        const el = document.querySelector<HTMLInputElement>(`[data-entrada-${enfocar.campo}="${enfocar.fila}"]`);
        if (el) { el.focus(); el.select(); el.scrollIntoView({ block: 'nearest' }); }
        setEnfocar(null);
    }, [enfocar, detalles.length]);

    // Resalta un momento la fila recién agregada o la que ya existía.
    useEffect(() => {
        if (destacada === null) return;
        const t = setTimeout(() => setDestacada(null), 1400);
        return () => clearTimeout(t);
    }, [destacada]);

    function agregar(p: ProductoEntrada) {
        setQ('');
        setAbierto(false);
        // Repetido: lleva a su fila en vez de duplicar. Con "factura por ítem"
        // SÍ se agrega otra línea: el mismo producto puede venir en otra factura.
        const existente = facturaPorItem ? -1 : detalles.findIndex(d => d.producto_id === p.id);
        if (existente >= 0) {
            setDestacada(existente);
            setEnfocar({ fila: existente, campo: 'cant' });
            return;
        }
        const base = p.unidades.find(u => u.es_base) ?? p.unidades[0];
        const nueva: DetalleEntradaRow = {
            producto_id: p.id,
            unidad_medida_id: base?.unidad_medida_id ?? '',
            cantidad: '',
            factor_conversion: base ? String(base.factor_conversion ?? '1') : '1',
            precio_costo: '',
            // Casi siempre se registra lo pagado por toda la línea (la factura
            // trae el total): arranca en "Total" y el unitario se calcula solo.
            precio_modo: 'total',
            precio_total: '',
            numero_documento: '',
        };
        // Se descartan filas vacías que hubieran quedado (sin producto).
        const idx = detalles.filter(d => d.producto_id !== '').length;
        setDetalles(prev => [...prev.filter(d => d.producto_id !== ''), nueva]);
        setDestacada(idx);
        setEnfocar({ fila: idx, campo: 'cant' });
    }

    function onBuscarKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setAbierto(true); setActivo(a => Math.min(a + 1, resultados.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActivo(a => Math.max(a - 1, 0)); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const p = resultados[activo];
            if (p) agregar(p);
        } else if (e.key === 'Escape') { setAbierto(false); }
    }

    /** Enter en Cantidad → Precio; Enter en Precio → de vuelta al buscador. */
    function onCampoKeyDown(e: React.KeyboardEvent<HTMLInputElement>, fila: number, campo: 'cant' | 'precio' | 'factura') {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (campo === 'cant') setEnfocar({ fila, campo: 'precio' });
        else buscadorRef.current?.focus();
    }

    const filas = detalles;
    const cols = facturaPorItem
        ? 'md:grid-cols-[1.75rem_minmax(0,1fr)_6.5rem_5.5rem_9.5rem_7rem_6.5rem_1.75rem]'
        : 'md:grid-cols-[1.75rem_minmax(0,1fr)_6.5rem_5.5rem_9.5rem_6.5rem_1.75rem]';

    return (
        <div className="space-y-3">
            {/* ── Buscador para agregar ─────────────────────────────────── */}
            <div className="relative">
                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" style={{ color: 'var(--color-text-muted)' }} />
                <input
                    ref={buscadorRef}
                    data-campo="detalles"
                    type="text"
                    value={q}
                    onChange={e => { setQ(e.target.value); setAbierto(true); }}
                    onFocus={() => setAbierto(true)}
                    onBlur={() => setTimeout(() => setAbierto(false), 150)}
                    onKeyDown={onBuscarKeyDown}
                    placeholder="Agregar producto: escribe el nombre o el código y presiona Enter…"
                    autoComplete="off"
                    className="w-full h-11 rounded-xl border pl-10 pr-24 text-sm outline-none transition-colors focus:ring-2"
                    style={{
                        borderColor: 'color-mix(in srgb, var(--color-primary) 35%, var(--color-border))',
                        backgroundColor: 'var(--color-surface)',
                        color: 'var(--color-text)',
                        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 25%, transparent)',
                    } as React.CSSProperties}
                />
                <span className="hidden md:flex absolute right-3 top-1/2 -translate-y-1/2 items-center gap-1 text-[11px] font-medium" style={{ color: 'var(--color-text-muted)' }}>
                    <CornerDownLeft size={12} /> agrega
                </span>

                {abierto && q.trim() !== '' && (
                    <ul
                        ref={listaRef}
                        className="absolute z-30 left-0 right-0 mt-1 rounded-xl border py-1 max-h-80 overflow-y-auto"
                        style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)', boxShadow: '0 12px 28px -8px rgba(15,23,42,0.25)' }}
                    >
                        {resultados.length === 0 && (
                            <li className="px-3 py-2.5 text-sm" style={{ color: 'var(--color-text-muted)' }}>No hay productos que coincidan con «{q}».</li>
                        )}
                        {resultados.map((p, i) => {
                            const yaEsta = detalles.some(d => d.producto_id === p.id);
                            return (
                                <li key={p.id} data-idx={i}>
                                    <button
                                        type="button"
                                        onMouseDown={e => { e.preventDefault(); agregar(p); }}
                                        onMouseEnter={() => setActivo(i)}
                                        className="w-full flex items-center gap-3 px-3 py-2 text-left"
                                        style={{ backgroundColor: i === activo ? 'color-mix(in srgb, var(--color-primary) 9%, transparent)' : 'transparent' }}
                                    >
                                        <span className="flex-1 min-w-0">
                                            {/* Nombre COMPLETO: si es largo, baja de línea en vez de cortarse. */}
                                            <span className="block text-sm font-medium leading-snug" style={{ color: 'var(--color-text)' }}>{p.nombre}</span>
                                            {p.codigo && <span className="block text-[11px] font-mono" style={{ color: 'var(--color-text-muted)' }}>{p.codigo}</span>}
                                        </span>
                                        {yaEsta && (
                                            <span className="flex-shrink-0 text-[11px] font-bold uppercase px-1.5 py-0.5 rounded" style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                                                {facturaPorItem ? 'agregar otra línea' : 'ya en la lista'}
                                            </span>
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {errors.detalles && <p className="text-[13px] font-medium whitespace-pre-line" style={{ color: 'var(--color-danger)' }}>{errors.detalles}</p>}

            {/* ── Lista ─────────────────────────────────────────────────── */}
            {filas.filter(d => d.producto_id !== '').length === 0 ? (
                <div className="flex flex-col items-center gap-1.5 rounded-xl border border-dashed py-8 text-center" style={{ borderColor: 'var(--color-border)' }}>
                    <PackagePlus size={26} style={{ color: 'var(--color-text-muted)' }} />
                    <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Aún no hay productos en esta entrada</p>
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Búscalos arriba: Enter agrega, Enter en cantidad pasa al precio, Enter en precio vuelve al buscador.</p>
                </div>
            ) : (
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--color-border)' }}>
                    {/* Cabecera */}
                    <div className={`hidden md:grid ${cols} gap-2 items-center px-3 py-2 text-[11px] font-semibold uppercase tracking-wide`}
                        style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                        <span>#</span>
                        <span>Producto</span>
                        <span>Unidad</span>
                        <span className="text-right">Cantidad</span>
                        <span>Precio</span>
                        {facturaPorItem && <span>Factura</span>}
                        <span className="text-right">Subtotal</span>
                        <span />
                    </div>

                    {filas.map((d, i) => {
                        if (d.producto_id === '') return null;
                        const prod = porId.get(d.producto_id as number);
                        const unidades = prod?.unidades ?? [];
                        const unidad = unidades.find(u => u.unidad_medida_id === d.unidad_medida_id);
                        const factor = parseFloat(d.factor_conversion) || 1;
                        const err = (campo: string) => errors[`detalles.${i}.${campo}`];
                        // Mensajes de esta fila (validación en pantalla o del servidor),
                        // escritos debajo de la fila — no solo el borde en rojo.
                        // (La unidad con selector ya escribe su propio error debajo.)
                        const mensajesFila = ['producto_id', ...(unidades.length > 1 ? [] : ['unidad_medida_id']), 'cantidad', 'factor_conversion', 'precio_costo', 'numero_documento']
                            .map(err).filter((m): m is string => !!m);
                        return (
                            <div
                                key={i}
                                className={`grid grid-cols-2 ${cols} gap-2 items-center px-3 py-2 border-t transition-colors`}
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: destacada === i ? 'color-mix(in srgb, var(--color-primary) 8%, var(--color-surface))' : 'var(--color-surface)',
                                }}
                            >
                                <span className="hidden md:block text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{i + 1}</span>

                                {/* Producto: nombre completo + código */}
                                <div className="col-span-2 md:col-span-1 min-w-0">
                                    <p className="text-sm font-medium leading-snug" style={{ color: 'var(--color-text)' }}>{prod?.nombre ?? '—'}</p>
                                    {prod?.codigo && <p className="text-[11px] font-mono" style={{ color: 'var(--color-text-muted)' }}>{prod.codigo}</p>}
                                </div>

                                {/* Unidad: solo selector si hay más de una */}
                                <div>
                                    {unidades.length > 1 ? (
                                        <Select
                                            triggerAttrs={{ 'data-campo': `detalles.${i}.unidad_medida_id` }}
                                            value={d.unidad_medida_id}
                                            onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                            options={unidades.map(u => ({
                                                value: u.unidad_medida_id,
                                                label: u.unidad_medida ? `${u.unidad_medida.abreviatura}${u.es_base ? ' (base)' : ''}` : String(u.unidad_medida_id),
                                            }))}
                                            error={err('unidad_medida_id')}
                                        />
                                    ) : (
                                        <span className="text-sm" style={{ color: 'var(--color-text)' }}>{unidad?.unidad_medida?.abreviatura ?? 'UND'}</span>
                                    )}
                                    {factor !== 1 && (
                                        <p className="mt-0.5 text-[11px] font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                            = {cantidadBase(d).toLocaleString('es-PE', { maximumFractionDigits: 4 })} base
                                        </p>
                                    )}
                                </div>

                                {/* Cantidad */}
                                <input
                                    data-entrada-cant={i}
                                    data-campo={`detalles.${i}.cantidad`}
                                    type="number" min="0" step="any" inputMode="decimal"
                                    value={d.cantidad}
                                    onChange={e => setDetalle(i, 'cantidad', e.target.value)}
                                    onKeyDown={e => onCampoKeyDown(e, i, 'cant')}
                                    placeholder="0"
                                    className={`${inputCls} text-right tabular-nums font-semibold`}
                                    style={{ borderColor: err('cantidad') ? 'var(--color-danger)' : 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                />

                                {/* Precio: P.U. / Total + monto, en una sola línea */}
                                <div className="col-span-2 md:col-span-1 flex items-center gap-1">
                                    <div className="inline-flex flex-shrink-0 rounded-md border overflow-hidden text-[11px] font-bold leading-none" style={{ borderColor: 'var(--color-border)' }}>
                                        {(['total', 'unitario'] as const).map(m => (
                                            <button key={m} type="button" tabIndex={-1} onClick={() => setPrecioModo(i, m)}
                                                title={m === 'unitario' ? 'Precio por unidad' : 'Lo pagado por toda la línea'}
                                                className="px-1.5 py-1.5 transition-colors"
                                                style={{
                                                    backgroundColor: d.precio_modo === m ? 'var(--color-primary)' : 'transparent',
                                                    color: d.precio_modo === m ? '#fff' : 'var(--color-text-muted)',
                                                }}>
                                                {m === 'unitario' ? 'P.U.' : 'Total'}
                                            </button>
                                        ))}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <input
                                            data-entrada-precio={i}
                                            data-campo={`detalles.${i}.precio_costo`}
                                            type="number" min="0" inputMode="decimal"
                                            step={d.precio_modo === 'total' ? '0.01' : '0.0001'}
                                            value={d.precio_modo === 'total' ? d.precio_total : d.precio_costo}
                                            onChange={e => setDetalle(i, d.precio_modo === 'total' ? 'precio_total' : 'precio_costo', e.target.value)}
                                            onKeyDown={e => onCampoKeyDown(e, i, 'precio')}
                                            placeholder="0.00"
                                            className={`${inputCls} text-right tabular-nums`}
                                            style={{ borderColor: err('precio_costo') ? 'var(--color-danger)' : 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                        />
                                        {d.precio_modo === 'total' && d.precio_costo !== '' && (
                                            <p className="mt-0.5 text-[11px] font-mono text-right" style={{ color: 'var(--color-text-muted)' }}>= S/ {d.precio_costo} c/u</p>
                                        )}
                                    </div>
                                </div>

                                {facturaPorItem && (
                                    <input
                                        value={d.numero_documento}
                                        onChange={e => setDetalle(i, 'numero_documento', e.target.value)}
                                        onKeyDown={e => onCampoKeyDown(e, i, 'factura')}
                                        placeholder="F001-…"
                                        className={inputCls}
                                        style={{ borderColor: err('numero_documento') ? 'var(--color-danger)' : 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                    />
                                )}

                                <span className="text-sm font-mono font-semibold text-right tabular-nums" style={{ color: 'var(--color-text)' }}>
                                    S/ {subtotal(d).toFixed(2)}
                                </span>

                                <button type="button" tabIndex={-1} onClick={() => removeDetalle(i)} title="Quitar producto"
                                    className="justify-self-end rounded-md p-1 transition-colors hover:bg-red-50" style={{ color: 'var(--color-text-muted)' }}>
                                    <Trash2 size={15} />
                                </button>

                                {mensajesFila.length > 0 && (
                                    <p className="col-span-full text-[12px] font-medium" style={{ color: 'var(--color-danger)' }}>
                                        {[...new Set(mensajesFila)].join(' · ')}
                                    </p>
                                )}
                            </div>
                        );
                    })}

                    {/* Total */}
                    <div className="flex items-center justify-between gap-4 px-3 py-2.5 border-t" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                        <span className="text-xs font-semibold" style={{ color: 'var(--color-text-muted)' }}>
                            {filas.filter(d => d.producto_id !== '').length} producto(s)
                        </span>
                        <span className="text-lg font-bold font-mono" style={{ color: 'var(--color-text)' }}>Total S/ {total.toFixed(2)}</span>
                    </div>
                </div>
            )}
        </div>
    );
}
