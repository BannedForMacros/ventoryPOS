import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { FolderTree, Package, Search, X, Info, ChevronDown, Check, Scale } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Select from '@/Components/UI/Select';
import {
    Panel, FieldSelect, EncabezadoReporte, useRecargaTabla, Banda, AvisoBanda, Empty, Paginacion,
    fmtS, fmtCant, frasePeriodo, plural, fieldStyle,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Local, PageProps } from '@/types';

interface Kpis {
    ventas:         number;
    costo:          number;
    utilidad_bruta: number;
    margen_bruto:   number | null;
    descuentos:     number;
    gastos:         number;
    /** Dinero devuelto a clientes (ya restado de `ventas`). */
    devuelto:       number;
    /** Costo de la mercadería que volvió al stock (ya restado de `costo`). */
    recuperado:     number;
    /** Costo de mercadería devuelta dañada: la única pérdida real de una devolución. */
    costo_danado:   number;
    utilidad_neta:  number;
    margen_neto:    number | null;
}

interface ProductoRow {
    producto_id:     number;
    producto_nombre: string;
    categoria:       string;
    cantidad:        number;
    ventas:          number;
    costo:           number;
    utilidad:        number;
    margen:          number | null;
}

interface MasVendido { producto_id: number; producto_nombre: string; ventas: number; costo: number; utilidad: number; margen: number | null; }

interface CategoriaRow { id: string; label: string; productos: number; ventas: number; utilidad: number; }

/** Contadores del periodo completo (no dependen de la búsqueda, la categoría ni la página). */
interface Conteos { total: number; perdida: number; bajo: number; sincosto: number; margen: number | null; }

type Vista = 'perdida' | 'bajo' | 'sincosto';

interface Filters {
    fecha_desde: string;
    fecha_hasta: string;
    local_id?:   string;
    orden:       string;
    vista?:      Vista | null;
    categoria?:  string | null;
    buscar?:     string | null;
}

interface Props extends PageProps {
    kpis:       Kpis;
    productos:  Paginado<ProductoRow>;
    conteos:    Conteos;
    mas_vendidos: MasVendido[];
    categorias: CategoriaRow[];
    locales:    Local[];
    filters:    Filters;
}

const ORDENES = [
    { value: 'utilidad', label: 'Más utilidad' },
    { value: 'margen', label: 'Mejor margen' },
    { value: 'ventas', label: 'Más vendidos en soles' },
    { value: 'cantidad', label: 'Más vendidos en unidades' },
];

const margenDe = (utilidad: number, ventas: number) => (ventas > 0 ? (utilidad / ventas) * 100 : null);
const fmtPct = (n: number | null) => (n === null ? '—' : `${n.toLocaleString('es-PE', { maximumFractionDigits: 1 })}%`);

/** Vendido sin costo registrado: su utilidad sale inflada (100% de margen). */
const sinCosto = (p: { ventas: number; costo: number }) => p.ventas > 0 && p.costo <= 0;

/** Tono del margen frente al margen promedio de los productos del periodo. */
function tonoMargen(margen: number | null, promedio: number | null) {
    if (margen === null) return { color: 'var(--color-text-muted)', fondo: 'var(--color-border)', texto: 'Sin ventas' };
    if (margen < 0) return { color: 'var(--vp-coral-ink)', fondo: 'var(--vp-coral)', texto: 'Pierdes' };
    if (promedio !== null && margen < promedio) return { color: 'var(--vp-amber-ink)', fondo: 'var(--vp-amber)', texto: 'Bajo tu promedio' };
    return { color: 'var(--vp-mint-ink)', fondo: 'var(--vp-mint)', texto: 'Sobre tu promedio' };
}

export default function ReporteUtilidad({ kpis, productos, conteos, mas_vendidos, categorias, locales, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.utilidad'), { ...filters, ...patch, page: undefined } as unknown as Record<string, string>,
            { preserveState: true, preserveScroll: true, replace: true });
    }
    // Buscar, ordenar, cambiar de vista o categoría y paginar solo tocan la
    // tabla: el resumen, la comparación y las categorías no se recalculan.
    const tabla = useRecargaTabla('reportes.utilidad', ['productos', 'filters']);
    const filtrarTabla = (patch: Record<string, string | number | null | undefined>) =>
        tabla.recargar({ ...filters, page: undefined, ...patch });

    const vista = filters.vista ?? null;
    const gano = kpis.utilidad_neta >= 0;
    const promedio = conteos.margen;
    const categoriaActiva = categorias.find(c => c.id === filters.categoria);

    const irA = (v: Vista) => {
        filtrarTabla({ vista: v, categoria: undefined });
        document.getElementById('productos')?.scrollIntoView({ behavior: 'smooth' });
    };

    return (
        <AppLayout title="Reporte de utilidad">
            <EncabezadoReporte titulo="Reporte de utilidad" fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                filtrar={filtrar} avanzados={filters.local_id ? 1 : 0} onLimpiar={() => filtrar({ local_id: undefined })}>
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
            </EncabezadoReporte>

            {/* ── Banda: cuánto ganaste, qué revisar y la cuenta completa ────── */}
            <Banda className="mb-3">
                <div className="flex flex-wrap items-start justify-between gap-x-10 gap-y-5 p-5 sm:p-6">
                    <div className="min-w-0">
                        <p className="text-[15px] font-medium" style={{ color: 'rgb(255 255 255 / 0.8)' }}>
                            {gano ? 'Ganaste' : 'Perdiste'} {frasePeriodo(filters.fecha_desde, filters.fecha_hasta)}
                        </p>
                        <p className="font-display text-[36px] sm:text-[46px] font-extrabold tracking-tight leading-[1.05] tabular-nums mt-1 whitespace-nowrap"
                            style={{ color: gano ? '#fff' : '#FFB199' }}>
                            {fmtS(Math.abs(kpis.utilidad_neta))}
                        </p>
                        <p className="text-sm mt-2.5" style={{ color: 'rgb(255 255 255 / 0.75)' }}>
                            {kpis.margen_neto !== null
                                ? <>Es el <strong className="text-white">{fmtPct(kpis.margen_neto)}</strong> de lo que vendiste, ya descontados costos y gastos.</>
                                : 'No hubo ventas en este periodo.'}
                        </p>
                    </div>
                    {(conteos.perdida > 0 || conteos.sincosto > 0) && (
                        <div className="flex flex-col items-start sm:items-end gap-1.5 w-full sm:w-auto">
                            <p className="text-[13px] font-semibold mb-0.5" style={{ color: 'rgb(255 255 255 / 0.75)' }}>Para revisar</p>
                            {conteos.perdida > 0 && (
                                <AvisoBanda color="var(--vp-coral)" onClick={() => irA('perdida')}>
                                    {plural(conteos.perdida, 'producto se vendió', 'productos se vendieron')} por debajo del costo
                                </AvisoBanda>
                            )}
                            {conteos.sincosto > 0 && (
                                <AvisoBanda color="var(--vp-amber)" onClick={() => irA('sincosto')}>
                                    {plural(conteos.sincosto, 'producto no tiene', 'productos no tienen')} costo registrado: su ganancia sale inflada
                                </AvisoBanda>
                            )}
                        </div>
                    )}
                </div>

                {/* La cuenta completa: vendido − costo = bruta − gastos = neta.
                    Las devoluciones ya van restadas de lo vendido y del costo. */}
                <dl className="grid grid-cols-2 sm:grid-cols-5" style={{ borderTop: '1px solid rgb(255 255 255 / 0.12)' }}>
                    <Paso label="Vendiste" valor={fmtS(kpis.ventas)} nota={notaVendido(kpis)} />
                    <Paso signo="−" label="Costo de lo vendido" valor={fmtS(kpis.costo)} nota={notaCosto(kpis)} />
                    <Paso signo="=" label="Utilidad bruta" valor={fmtS(kpis.utilidad_bruta)}
                        nota={kpis.margen_bruto !== null ? `margen ${fmtPct(kpis.margen_bruto)}` : undefined} />
                    <Paso signo="−" label="Gastos" valor={fmtS(kpis.gastos)} />
                    <Paso signo="=" label="Utilidad neta" valor={fmtS(kpis.utilidad_neta)} resalta={gano ? 'var(--vp-mint)' : 'var(--vp-coral)'}
                        nota={kpis.margen_neto !== null ? `margen ${fmtPct(kpis.margen_neto)}` : undefined} />
                </dl>
            </Banda>

            {/* ── Lo que más vendes y cuánto te deja ───────────────────────── */}
            {mas_vendidos.length > 0 && <VendidoVsGanancia productos={mas_vendidos} promedio={promedio} />}

            {/* ── Categorías (filtro) + productos (paginados) ──────────────── */}
            <div id="productos" className="grid xl:grid-cols-12 gap-3 mb-3 scroll-mt-20 items-start">
                <Categorias categorias={categorias} total={conteos.total} activa={filters.categoria ?? null} promedio={promedio}
                    onElegir={id => filtrarTabla({ categoria: id ?? undefined, vista: undefined })} />

                <section className="xl:col-span-8 rounded-2xl overflow-hidden"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
                    <header className="flex flex-wrap items-center gap-3 px-4 py-3.5"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))', borderBottom: '1px solid var(--color-border)' }}>
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-navy)' }}>
                            <Package size={18} />
                        </span>
                        <div className="min-w-0 flex-1">
                            <h2 className="font-display text-[17px] font-bold leading-tight truncate" style={{ color: 'var(--vp-navy)' }}>
                                {categoriaActiva ? `Productos de ${categoriaActiva.label}` : 'Utilidad por producto'}
                            </h2>
                            <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                {promedio !== null
                                    ? <>Tu margen promedio es <strong style={{ color: 'var(--color-text)' }}>{fmtPct(promedio)}</strong>; cada producto se compara contra él.</>
                                    : 'Sin ventas en el periodo.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                            <div className="relative w-full sm:w-auto sm:flex-1 lg:flex-none lg:w-60">
                                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                                <input type="search" defaultValue={filters.buscar ?? ''} key={filters.buscar ?? ''}
                                    placeholder="Buscar por nombre o código" aria-label="Buscar producto"
                                    onKeyDown={e => { if (e.key === 'Enter') filtrarTabla({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                                    className="w-full text-sm rounded-xl pl-9 pr-8 py-2 border outline-none focus:ring-2"
                                    style={{ ...fieldStyle, backgroundColor: 'var(--color-surface)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 35%, transparent)' } as React.CSSProperties} />
                                {filters.buscar && (
                                    <button onClick={() => filtrarTabla({ buscar: undefined })} aria-label="Quitar búsqueda"
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 rounded" style={{ color: 'var(--color-text-muted)' }}>
                                        <X size={15} />
                                    </button>
                                )}
                            </div>
                            <Select className="w-full sm:w-52" ariaLabel="Ordenar productos" value={filters.orden}
                                onChange={v => filtrarTabla({ orden: String(v) })} options={ORDENES} />
                        </div>
                    </header>

                    {/* Vistas rápidas: cuentan TODO el periodo */}
                    <div className="flex flex-wrap items-center gap-2 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }} role="group" aria-label="Filtrar productos">
                        <Chip activo={vista === null} onClick={() => filtrarTabla({ vista: undefined })} color="var(--vp-navy)">
                            Todos <b className="tabular-nums">{conteos.total}</b>
                        </Chip>
                        <Chip activo={vista === 'perdida'} onClick={() => filtrarTabla({ vista: 'perdida' })} color="var(--vp-coral)" tinta="var(--vp-coral-ink)" deshabilitado={conteos.perdida === 0}>
                            Con pérdida <b className="tabular-nums">{conteos.perdida}</b>
                        </Chip>
                        <Chip activo={vista === 'bajo'} onClick={() => filtrarTabla({ vista: 'bajo' })} color="var(--vp-amber)" tinta="var(--vp-amber-ink)" deshabilitado={conteos.bajo === 0}>
                            Bajo tu promedio <b className="tabular-nums">{conteos.bajo}</b>
                        </Chip>
                        {conteos.sincosto > 0 && (
                            <Chip activo={vista === 'sincosto'} onClick={() => filtrarTabla({ vista: 'sincosto' })} color="var(--vp-amber)" tinta="var(--vp-amber-ink)">
                                Sin costo registrado <b className="tabular-nums">{conteos.sincosto}</b>
                            </Chip>
                        )}
                        {categoriaActiva && (
                            <button onClick={() => filtrarTabla({ categoria: undefined })}
                                className="ml-auto inline-flex items-center gap-1.5 text-[13px] font-semibold px-3 py-1.5 rounded-full"
                                style={{ color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-navy) 8%, transparent)' }}>
                                {categoriaActiva.label} <X size={14} />
                            </button>
                        )}
                    </div>

                    <div className="transition-opacity duration-150" style={{ opacity: tabla.cargando ? 0.5 : 1 }} aria-busy={tabla.cargando}>
                        <div className="hidden md:block overflow-x-auto">
                            <table className="w-full text-sm table-fixed min-w-[720px]">
                                <colgroup>
                                    <col /><col className="w-[10%]" /><col className="w-[15%]" /><col className="w-[15%]" /><col className="w-[15%]" /><col className="w-[17%]" />
                                </colgroup>
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                        <ThU>Producto</ThU><ThU right>Cantidad</ThU><ThU right>Vendiste</ThU><ThU right>Te costó</ThU><ThU right>Ganaste</ThU><ThU right>Margen</ThU>
                                    </tr>
                                </thead>
                                <tbody>
                                    {productos.data.map(p => (
                                        <tr key={p.producto_id} style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                            <td className="px-4 py-2.5">
                                                <p className="font-semibold truncate" style={{ color: 'var(--color-text)' }} title={p.producto_nombre}>{p.producto_nombre}</p>
                                                <p className="text-[13px] truncate mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{p.categoria}</p>
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtCant(p.cantidad)}</td>
                                            <td className="px-3 py-2.5 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtS(p.ventas)}</td>
                                            <td className="px-3 py-2.5 text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{fmtS(p.costo)}</td>
                                            <td className="px-3 py-2.5 text-right">
                                                <span className="font-display text-[15px] font-bold tabular-nums"
                                                    style={{ color: p.utilidad < 0 ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}>
                                                    {fmtS(p.utilidad)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2.5">{sinCosto(p) ? <FaltaCosto /> : <MargenBarra margen={p.margen} tono={tonoMargen(p.margen, promedio)} />}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Móvil */}
                        <ul className="md:hidden">
                            {productos.data.map(p => (
                                <li key={p.producto_id} className="px-4 py-3" style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                    <div className="flex items-baseline justify-between gap-3">
                                        <p className="font-semibold min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</p>
                                        <span className="font-display font-bold tabular-nums whitespace-nowrap"
                                            style={{ color: p.utilidad < 0 ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}>{fmtS(p.utilidad)}</span>
                                    </div>
                                    <p className="text-[13px] mt-0.5 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                        {fmtCant(p.cantidad)} und., vendiste {fmtS(p.ventas)}, costó {fmtS(p.costo)}
                                    </p>
                                    <div className="mt-2">{sinCosto(p) ? <FaltaCosto /> : <MargenBarra margen={p.margen} tono={tonoMargen(p.margen, promedio)} />}</div>
                                </li>
                            ))}
                        </ul>

                        {productos.data.length === 0 && (
                            <Empty text={filters.buscar ? 'Ningún producto coincide con la búsqueda.'
                                : vista || categoriaActiva ? 'Ningún producto en esta vista.'
                                : 'No hubo ventas en este periodo. Elige otro rango de fechas arriba.'} />
                        )}
                    </div>

                    <Paginacion paginado={productos} ruta="reportes.utilidad" filters={filters as unknown as Record<string, unknown>}
                        onIr={page => tabla.recargar({ ...filters, page })} />
                </section>
            </div>

            <details className="group rounded-2xl px-4 py-3 text-sm"
                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-sky) 7%, var(--color-surface))', border: '1px solid color-mix(in srgb, var(--vp-sky) 20%, transparent)' }}>
                <summary className="flex items-center gap-2 cursor-pointer font-semibold list-none" style={{ color: 'var(--vp-navy)' }}>
                    <Info size={16} /> Cómo se calcula la utilidad
                    <ChevronDown size={16} className="ml-auto transition-transform group-open:rotate-180" />
                </summary>
                <ul className="mt-2.5 space-y-1.5 list-disc pl-5" style={{ color: 'var(--color-text-muted)' }}>
                    <li>El costo de cada venta queda <strong style={{ color: 'var(--color-text)' }}>congelado el día que se vende</strong>: si el fierro sube después, la utilidad del pasado no cambia.</li>
                    <li>Todos los montos incluyen IGV, igual que el balance diario.</li>
                    <li>Lo vendido ya viene con los descuentos restados. La utilidad por producto descuenta los de cada línea, pero no el descuento global de la venta.</li>
                    <li><strong style={{ color: 'var(--color-text)' }}>Devoluciones:</strong> no son un gasto, deshacen la venta. El dinero devuelto se resta de lo vendido y lo que volvió al stock se resta del costo: si te devuelven un producto en buen estado y devuelves su dinero, quedas igual que si no se hubiera vendido. Solo pierdes cuando vuelve dañado, porque su costo no se recupera. Cuentan solo las devoluciones completadas.</li>
                </ul>
            </details>
        </AppLayout>
    );
}

/* ── Lo que más vendes y cuánto te deja ─────────────────────────────── */
/**
 * Una barra por producto con lo vendido y, DENTRO, la parte que quedó de
 * ganancia. Vender mucho con un pedacito verde = vendes bien pero ganas poco.
 * Todas las barras usan la misma escala (el más vendido), así se comparan.
 */
function VendidoVsGanancia({ productos, promedio }: { productos: MasVendido[]; promedio: number | null }) {
    const max = Math.max(...productos.map(p => p.ventas));
    const conCosto = productos.filter(p => !sinCosto(p) && p.margen !== null);
    const peor = conCosto.reduce<MasVendido | null>((a, p) => (!a || (p.margen ?? 0) < (a.margen ?? 0) ? p : a), null);
    const mejor = conCosto.reduce<MasVendido | null>((a, p) => (!a || (p.margen ?? 0) > (a.margen ?? 0) ? p : a), null);

    return (
        <Panel icon={<Scale size={17} />} titulo="Lo que más vendes y cuánto te deja" color="var(--vp-mint)" tinta="var(--vp-mint-ink)"
            detalle={
                <span className="hidden sm:inline-flex items-center gap-3">
                    <Leyenda color="color-mix(in srgb, var(--vp-navy) 16%, var(--color-surface))">Lo que vendiste</Leyenda>
                    <Leyenda color="var(--vp-mint)">Lo que te quedó</Leyenda>
                </span>
            }
            className="mb-3">
            {peor && mejor && peor.producto_id !== mejor.producto_id && promedio !== null && (peor.margen ?? 0) < promedio && (
                <p className="text-sm mb-4 -mt-1" style={{ color: 'var(--color-text-muted)' }}>
                    <strong style={{ color: 'var(--color-text)' }}>{peor.producto_nombre}</strong> está entre lo que más vendes, pero solo te deja
                    el <strong style={{ color: 'var(--vp-amber-ink)' }}>{fmtPct(peor.margen)}</strong>. En cambio, <strong style={{ color: 'var(--color-text)' }}>{mejor.producto_nombre}</strong> te
                    deja el <strong style={{ color: 'var(--vp-mint-ink)' }}>{fmtPct(mejor.margen)}</strong>.
                </p>
            )}
            <div className="flex flex-wrap gap-3 mb-3 sm:hidden">
                <Leyenda color="color-mix(in srgb, var(--vp-navy) 16%, var(--color-surface))">Lo que vendiste</Leyenda>
                <Leyenda color="var(--vp-mint)">Lo que te quedó</Leyenda>
            </div>
            <ul className="grid md:grid-cols-2 gap-x-10 gap-y-4">
                {productos.map(p => {
                    const falta = sinCosto(p);
                    const pierde = p.utilidad < 0;
                    const tono = tonoMargen(p.margen, promedio);
                    return (
                        <li key={p.producto_id} className="min-w-0">
                            <div className="flex items-baseline justify-between gap-3">
                                <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }} title={p.producto_nombre}>{p.producto_nombre}</p>
                                <p className="text-sm tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                    vendiste <strong style={{ color: 'var(--color-text)' }}>{fmtS(p.ventas)}</strong>
                                </p>
                            </div>
                            <div className="relative h-3.5 mt-1.5 rounded-md overflow-hidden" role="img"
                                aria-label={`Vendiste ${fmtS(p.ventas)}, te quedó ${fmtS(p.utilidad)}`}
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 45%, transparent)' }}>
                                <div className="absolute inset-y-0 left-0 rounded-md"
                                    style={{ width: `${(p.ventas / max) * 100}%`, backgroundColor: 'color-mix(in srgb, var(--vp-navy) 16%, var(--color-surface))' }} />
                                {!falta && !pierde && (
                                    <div className="absolute inset-y-0 left-0 rounded-md"
                                        style={{ width: `max(3px, ${(p.utilidad / max) * 100}%)`, backgroundColor: 'var(--vp-mint)' }} />
                                )}
                            </div>
                            <p className="text-[13px] mt-1 tabular-nums" style={{ color: falta ? 'var(--vp-amber-ink)' : pierde ? 'var(--vp-coral-ink)' : tono.color }}>
                                {falta ? 'Falta registrar su costo: no se sabe cuánto deja'
                                    : pierde ? `Perdiste ${fmtS(Math.abs(p.utilidad))}: se vendió por debajo del costo`
                                    : <>Te quedó <strong>{fmtS(p.utilidad)}</strong> ({fmtPct(p.margen)})</>}
                            </p>
                        </li>
                    );
                })}
            </ul>
        </Panel>
    );
}

function Leyenda({ color, children }: { color: string; children: React.ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
            <span className="h-2.5 w-4 rounded-sm" style={{ backgroundColor: color }} />
            {children}
        </span>
    );
}

/* ── La cuenta ─────────────────────────────────────────────────────────── */
function Paso({ signo, label, valor, resalta, nota }: { signo?: string; label: string; valor: string; resalta?: string; nota?: string }) {
    return (
        <div className="relative px-5 sm:px-6 py-3.5 sm:border-l sm:first:border-l-0 min-w-0"
            style={{ borderColor: 'rgb(255 255 255 / 0.12)', backgroundColor: resalta ? 'rgb(255 255 255 / 0.06)' : undefined }}>
            {signo && (
                <span className="hidden sm:flex absolute -left-3 top-1/2 -translate-y-1/2 h-6 w-6 items-center justify-center rounded-full text-sm font-bold"
                    style={{ backgroundColor: 'var(--vp-navy)', border: '1px solid rgb(255 255 255 / 0.25)', color: '#fff' }} aria-hidden>
                    {signo}
                </span>
            )}
            <dt className="text-[13px] sm:truncate" style={{ color: 'rgb(255 255 255 / 0.68)' }} title={label}>{label}</dt>
            <dd className="font-display text-lg font-bold tabular-nums leading-tight mt-0.5 truncate" style={{ color: resalta ?? '#fff' }}>{valor}</dd>
            {nota && <dd className="text-xs mt-0.5" style={{ color: 'rgb(255 255 255 / 0.6)' }}>{nota}</dd>}
        </div>
    );
}

/** "ya sin S/ 652.39 de descuentos ni S/ 338.00 devueltos" */
function notaVendido(k: Kpis): string {
    const partes = [
        k.descuentos > 0 ? `${fmtS(k.descuentos)} de descuentos` : null,
        k.devuelto > 0 ? `${fmtS(k.devuelto)} devueltos` : null,
    ].filter(Boolean);
    return partes.length ? `ya sin ${partes.join(' ni ')}` : 'sin descuentos ni devoluciones';
}

function notaCosto(k: Kpis): string | undefined {
    const partes = [
        k.recuperado > 0 ? `sin ${fmtS(k.recuperado)} que volvió al stock` : null,
        k.costo_danado > 0 ? `incluye ${fmtS(k.costo_danado)} devuelto dañado` : null,
    ].filter(Boolean);
    return partes.length ? partes.join(', ') : undefined;
}

/* ── Categorías: lista compacta con scroll propio que filtra la tabla ──── */
/**
 * Altura fija y scroll interno: se ve igual con 5 que con 50 categorías.
 * Tocar una filtra la tabla de productos; tocarla de nuevo (o "Todas") quita el filtro.
 */
function Categorias({ categorias, total, activa, promedio, onElegir }: {
    categorias: CategoriaRow[]; total: number; activa: string | null; promedio: number | null;
    onElegir: (id: string | null) => void;
}) {
    const max = Math.max(1, ...categorias.map(c => Math.abs(c.utilidad)));
    return (
        <section className="xl:col-span-4 rounded-2xl overflow-hidden flex flex-col xl:sticky xl:top-20"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
            <header className="flex items-center gap-2.5 px-4 pt-4 pb-3">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 16%, var(--color-surface))', color: 'var(--vp-amber-ink)' }}>
                    <FolderTree size={17} />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>Qué categorías dejan más</h3>
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Toca una para ver sus productos</p>
                </div>
                <span className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{categorias.length}</span>
            </header>

            {categorias.length === 0 ? <Empty text="Sin categorías con ventas en este periodo." /> : (
                <ul className="overflow-y-auto max-h-[420px] xl:max-h-[calc(100vh-11rem)] px-2 pb-2" role="listbox" aria-label="Filtrar por categoría"
                    style={{ overscrollBehavior: 'contain' }}>
                    <FilaCategoria activa={activa === null} onClick={() => onElegir(null)}
                        titulo="Todas las categorías" subtitulo={plural(total, 'producto', 'productos')} />
                    {categorias.map(c => {
                        const m = margenDe(c.utilidad, c.ventas);
                        const tono = tonoMargen(m === null ? null : Math.round(m * 10) / 10, promedio);
                        return (
                            <FilaCategoria key={c.id} activa={activa === c.id} onClick={() => onElegir(activa === c.id ? null : c.id)}
                                titulo={c.label} subtitulo={`${plural(c.productos, 'producto', 'productos')}, vendiste ${fmtS(c.ventas)}`}
                                monto={c.utilidad} margen={m === null ? null : Math.round(m * 10) / 10} tono={tono}
                                ancho={(Math.abs(c.utilidad) / max) * 100} />
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

function FilaCategoria({ activa, onClick, titulo, subtitulo, monto, margen, tono, ancho }: {
    activa: boolean; onClick: () => void; titulo: string; subtitulo: string;
    monto?: number; margen?: number | null; tono?: ReturnType<typeof tonoMargen>; ancho?: number;
}) {
    return (
        <li role="option" aria-selected={activa}>
            <button onClick={onClick}
                className="w-full text-left rounded-xl px-3 py-2.5 transition-colors hover:bg-[color-mix(in_srgb,var(--vp-navy)_5%,transparent)] focus-visible:outline focus-visible:outline-2"
                style={{
                    backgroundColor: activa ? 'color-mix(in srgb, var(--vp-navy) 9%, var(--color-surface))' : undefined,
                    boxShadow: activa ? 'inset 0 0 0 1px color-mix(in srgb, var(--vp-navy) 30%, transparent)' : undefined,
                    outlineColor: 'var(--color-primary)',
                }}>
                <div className="flex items-baseline justify-between gap-3">
                    <span className="text-sm font-semibold truncate flex items-center gap-1.5" style={{ color: 'var(--color-text)' }}>
                        {activa && <Check size={14} className="flex-shrink-0" style={{ color: 'var(--vp-navy)' }} />}
                        {titulo}
                    </span>
                    {monto !== undefined && (
                        <span className="text-[15px] font-bold tabular-nums whitespace-nowrap" style={{ color: monto < 0 ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}>
                            {fmtS(monto)}
                        </span>
                    )}
                </div>
                {ancho !== undefined && tono && (
                    <div className="flex items-center gap-2 mt-1.5">
                        <div className="h-1.5 flex-1 rounded-full overflow-hidden" style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 65%, transparent)' }}>
                            <div className="h-full rounded-full" style={{ width: `${Math.max(2, ancho)}%`, backgroundColor: tono.fondo }} />
                        </div>
                        <span className="text-xs font-semibold tabular-nums whitespace-nowrap" style={{ color: tono.color }}>margen {fmtPct(margen ?? null)}</span>
                    </div>
                )}
                <p className="text-[13px] mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>{subtitulo}</p>
            </button>
        </li>
    );
}

/* ── Tabla ────────────────────────────────────────────────────────────── */
function ThU({ children, right = false }: { children: React.ReactNode; right?: boolean }) {
    return (
        <th className={`px-3 first:px-4 last:px-4 py-2.5 text-[13px] font-semibold whitespace-nowrap ${right ? 'text-right' : 'text-left'}`}
            style={{ color: 'var(--color-text-muted)' }}>
            {children}
        </th>
    );
}

function MargenBarra({ margen, tono }: { margen: number | null; tono: ReturnType<typeof tonoMargen> }) {
    // La barra llega llena al 30% de margen; más allá solo cambia el número.
    const ancho = margen === null ? 0 : Math.min(100, (Math.abs(margen) / 30) * 100);
    return (
        <div className="flex items-center gap-2.5 justify-end" title={tono.texto}>
            <div className="h-1.5 w-14 rounded-full overflow-hidden" style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 65%, transparent)' }}>
                <div className="h-full rounded-full" style={{ width: `${Math.max(margen === null ? 0 : 4, ancho)}%`, backgroundColor: tono.fondo }} />
            </div>
            <span className="text-sm font-bold tabular-nums w-14 text-right" style={{ color: tono.color }}>{fmtPct(margen)}</span>
        </div>
    );
}

function FaltaCosto() {
    return (
        <div className="flex justify-end">
            <span className="text-[13px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap"
                title="Registra el costo del producto para ver su utilidad real"
                style={{ color: 'var(--vp-amber-ink)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 16%, transparent)' }}>
                Falta el costo
            </span>
        </div>
    );
}

function Chip({ activo, onClick, color, tinta, deshabilitado = false, children }: {
    activo: boolean; onClick: () => void; color: string; tinta?: string; deshabilitado?: boolean; children: React.ReactNode;
}) {
    return (
        <button onClick={onClick} disabled={deshabilitado} aria-pressed={activo}
            className="inline-flex items-center gap-1.5 text-[13px] font-semibold px-3 py-1.5 rounded-full border transition-colors disabled:opacity-45 disabled:cursor-not-allowed focus-visible:outline focus-visible:outline-2"
            style={{
                backgroundColor: activo ? color : 'var(--color-surface)',
                borderColor: activo ? color : `color-mix(in srgb, ${color} 35%, var(--color-border))`,
                color: activo ? (color === 'var(--vp-navy)' ? '#fff' : 'var(--vp-midnight)') : (tinta ?? color),
                outlineColor: color,
            }}>
            {children}
        </button>
    );
}
