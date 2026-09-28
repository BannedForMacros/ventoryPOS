import { Fragment, useEffect, useMemo, useState } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    ShoppingCart, Wallet, Package, UserRound, ChevronDown, Search, X,
    SlidersHorizontal, AlertTriangle, Clock3, Receipt, FileText, Scale, Users,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { CHART_COLORS } from '@/Components/UI/Charts';
import {
    Panel, FieldDate, FieldSelect, Paginacion, Empty, rangosBase,
    fmtS, fmtInt, fmtCant, fieldStyle,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Local, MetodoPago, User, Venta, PageProps } from '@/types';

interface Kpis {
    total_ventas:       number;
    total_anuladas:     number;
    monto_anuladas:     number;
    monto_total:        number;
    monto_descuento:    number;
    monto_igv:          number;
    monto_contado:      number;
    monto_credito:      number;
    credito_pendiente:  number;
    clientes_distintos: number;
    ticket_promedio:    number;
    prev_monto:         number;
    prev_ventas:        number;
    variacion:          number | null;
}

interface SerieDia   { dia: string; total: number; ventas: number; descuento: number; }
interface PorHora    { hora: number; total: number; ventas: number; }
interface PorMetodo  { metodo_pago_id: number; nombre: string; total: number; ocurrencias: number; }
interface PorVendedor{ user_id: number; nombre: string; total: number; ventas: number; }
interface PorComprob { tipo: string; total: number; ventas: number; }
interface TopProducto{ producto_id: number; producto_nombre: string; cantidad: number; total: number; }
interface TopCliente { cliente_id: number; nombre: string; total: number; ventas: number; }

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    estado?: string; local_id?: string; user_id?: string;
    metodo_pago_id?: string; tipo?: string; comprobante?: string; buscar?: string;
}

interface Props extends PageProps {
    ventas:          Paginado<Venta>;
    kpis:            Kpis;
    serie_diaria:    SerieDia[];
    por_hora:        PorHora[];
    por_metodo:      PorMetodo[];
    por_vendedor:    PorVendedor[];
    por_comprobante: PorComprob[];
    top_productos:   TopProducto[];
    top_clientes:    TopCliente[];
    locales:         Local[];
    usuarios:        Pick<User, 'id' | 'name'>[];
    metodos_pago:    MetodoPago[];
    rango_anterior:  { desde: string; hasta: string };
    filters:         Filters;
}

/** Comprobantes: nombre, plural y color. Factura, boleta y ticket se muestran siempre (aunque sean 0). */
const COMPROBANTES: Record<string, { label: string; plural: string; color: string; tinta: string; fijo?: boolean }> = {
    factura:         { label: 'Factura', plural: 'Facturas', color: 'var(--vp-sky)',   tinta: 'var(--vp-navy)',      fijo: true },
    boleta:          { label: 'Boleta',  plural: 'Boletas',  color: 'var(--vp-mint)',  tinta: 'var(--vp-mint-ink)',  fijo: true },
    ticket:          { label: 'Ticket',  plural: 'Tickets',  color: 'var(--vp-amber)', tinta: 'var(--vp-amber-ink)', fijo: true },
    factura_externa: { label: 'Factura electrónica externa', plural: 'Facturas externas', color: '#8b5cf6', tinta: '#5b21b6' },
    boleta_externa:  { label: 'Boleta electrónica externa',  plural: 'Boletas externas',  color: '#ec4899', tinta: '#9d174d' },
};

const TITULO_RANGO: Record<string, string> = {
    'Hoy': 'Vendiste hoy', '7 días': 'Vendiste en los últimos 7 días',
    'Este mes': 'Vendiste este mes', '30 días': 'Vendiste en los últimos 30 días',
};

const nombreCliente = (v: Venta) =>
    (v.cliente as any)?.razon_social
        ?? (v.cliente ? `${(v.cliente as any).nombres} ${(v.cliente as any).apellidos ?? ''}`.trim() : 'Clientes varios');

const aFecha = (d: string) => new Date(d + 'T00:00:00');
const fechaLarga = (d: string) => aFecha(d).toLocaleDateString('es-PE', { day: 'numeric', month: 'long', year: 'numeric' });
const fechaCorta = (d: string) => aFecha(d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short' });
const soloFecha = (iso: string) => new Date(iso).toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
const soloHora = (iso: string) => new Date(iso).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
const pct = (parte: number, total: number) => (total > 0 ? Math.round((parte / total) * 100) : 0);
const plural = (n: number, uno: string, varios: string) => `${fmtInt(n)} ${n === 1 ? uno : varios}`;
const isoDia = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

interface Punto { clave: string; eje: string; titulo: string; total: number; ventas: number; }

/** Días del periodo con ventas en 0 incluidos, para que el gráfico no invente tendencias. */
function puntosPorDia(serie: SerieDia[], desde: string, hasta: string): Punto[] {
    const mapa = new Map(serie.map(s => [s.dia, s]));
    const out: Punto[] = [];
    const fin = aFecha(hasta);
    for (let d = aFecha(desde), n = 0; d <= fin && n < 93; d.setDate(d.getDate() + 1), n++) {
        const k = isoDia(d);
        const s = mapa.get(k);
        out.push({ clave: k, eje: String(d.getDate()), titulo: fechaCorta(k), total: s?.total ?? 0, ventas: s?.ventas ?? 0 });
    }
    return out;
}

/** Horas con ventas, extendidas al horario comercial típico (8 a 20 h). */
function puntosPorHora(data: PorHora[]): Punto[] {
    if (data.length === 0) return [];
    const desde = Math.min(8, ...data.map(d => d.hora));
    const hasta = Math.max(20, ...data.map(d => d.hora));
    const out: Punto[] = [];
    for (let h = desde; h <= hasta; h++) {
        const d = data.find(x => x.hora === h);
        out.push({ clave: String(h), eje: String(h), titulo: `${h}:00`, total: d?.total ?? 0, ventas: d?.ventas ?? 0 });
    }
    return out;
}

export default function ReportesVentas({
    ventas, kpis, serie_diaria, por_hora, por_metodo, por_vendedor, por_comprobante,
    top_productos, top_clientes, locales, usuarios, metodos_pago, rango_anterior, filters, flash,
}: Props) {
    const [abiertas, setAbiertas] = useState<Set<number>>(new Set());

    const filtrosAvanzados = [filters.estado, filters.local_id, filters.user_id,
        filters.metodo_pago_id, filters.tipo, filters.comprobante].filter(Boolean).length;
    const [verFiltros, setVerFiltros] = useState(filtrosAvanzados > 0);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.ventas'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.ventas'), {
        fecha_desde: filters.fecha_desde, fecha_hasta: filters.fecha_hasta,
    }, { preserveState: true, replace: true });

    function toggle(id: number) {
        setAbiertas(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const unDia = filters.fecha_desde === filters.fecha_hasta;
    const rangoActivo = rangosBase().find(([, calc]) => {
        const r = calc();
        return r.fecha_desde === filters.fecha_desde && r.fecha_hasta === filters.fecha_hasta;
    })?.[0];
    const periodo = unDia ? fechaLarga(filters.fecha_desde)
        : `Del ${fechaLarga(filters.fecha_desde)} al ${fechaLarga(filters.fecha_hasta)}`;

    // Un solo día: el gráfico principal pasa a mostrar las horas.
    const puntosBanda = useMemo(
        () => (unDia ? puntosPorHora(por_hora) : puntosPorDia(serie_diaria, filters.fecha_desde, filters.fecha_hasta)),
        [unDia, por_hora, serie_diaria, filters.fecha_desde, filters.fecha_hasta],
    );
    const puntosHora = useMemo(() => puntosPorHora(por_hora), [por_hora]);

    const totalCobro = por_metodo.reduce((s, m) => s + m.total, 0);
    const totalTop   = top_productos.reduce((s, p) => s + p.total, 0);

    return (
        <AppLayout title="Reporte de ventas">
            {/* ── Encabezado + periodo ─────────────────────────────────────── */}
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>
                        Reporte de ventas
                    </h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>{periodo}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex rounded-xl p-1" role="group" aria-label="Periodo rápido"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 9%, var(--color-surface))' }}>
                        {rangosBase().map(([label, calc]) => {
                            const activo = rangoActivo === label;
                            return (
                                <button key={label} onClick={() => filtrar(calc())} aria-pressed={activo}
                                    className="text-sm font-semibold px-3.5 py-1.5 rounded-lg transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1"
                                    style={{
                                        backgroundColor: activo ? 'var(--vp-navy)' : 'transparent',
                                        color: activo ? '#fff' : 'var(--vp-navy)',
                                        outlineColor: 'var(--color-primary)',
                                    }}>
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                    <button onClick={() => setVerFiltros(v => !v)} aria-expanded={verFiltros}
                        className="inline-flex items-center gap-2 text-sm font-semibold px-3.5 py-2 rounded-xl border transition-colors hover:bg-black/[0.03] focus-visible:outline focus-visible:outline-2"
                        style={{
                            borderColor: verFiltros ? 'var(--color-primary)' : 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)', color: 'var(--color-text)',
                            outlineColor: 'var(--color-primary)',
                        }}>
                        <SlidersHorizontal size={16} style={{ color: 'var(--color-primary)' }} />
                        Más filtros
                        {filtrosAvanzados > 0 && (
                            <span className="min-w-5 h-5 px-1.5 rounded-full text-xs font-bold inline-flex items-center justify-center text-white"
                                style={{ backgroundColor: 'var(--color-primary)' }}>
                                {filtrosAvanzados}
                            </span>
                        )}
                    </button>
                </div>
            </div>

            {verFiltros && (
                <div className="rounded-2xl p-4 mb-3 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 items-end"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <FieldDate label="Desde" value={filters.fecha_desde} onChange={v => filtrar({ fecha_desde: v })} />
                    <FieldDate label="Hasta" value={filters.fecha_hasta} onChange={v => filtrar({ fecha_hasta: v })} />
                    <FieldSelect label="Estado" value={filters.estado ?? ''}
                        onChange={v => filtrar({ estado: v || undefined })}
                        options={[
                            { value: '', label: 'Todas' },
                            { value: 'completada', label: 'Completadas' },
                            { value: 'anulada', label: 'Anuladas' },
                        ]} />
                    {locales.length > 1 && (
                        <FieldSelect label="Local" value={filters.local_id ?? ''}
                            onChange={v => filtrar({ local_id: v || undefined })}
                            options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                    )}
                    <FieldSelect label="Vendedor" value={filters.user_id ?? ''}
                        onChange={v => filtrar({ user_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
                    <FieldSelect label="Método de pago" value={filters.metodo_pago_id ?? ''}
                        onChange={v => filtrar({ metodo_pago_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...metodos_pago.map(m => ({ value: String(m.id), label: m.nombre as string }))]} />
                    <FieldSelect label="Contado o crédito" value={filters.tipo ?? ''}
                        onChange={v => filtrar({ tipo: v || undefined })}
                        options={[
                            { value: '', label: 'Ambos' },
                            { value: 'contado', label: 'Contado' },
                            { value: 'credito', label: 'Crédito' },
                        ]} />
                    <FieldSelect label="Comprobante" value={filters.comprobante ?? ''}
                        onChange={v => filtrar({ comprobante: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...Object.entries(COMPROBANTES).map(([value, c]) => ({ value, label: c.label }))]} />
                    {filtrosAvanzados > 0 && (
                        <button onClick={limpiar}
                            className="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg transition-colors hover:opacity-80"
                            style={{ color: 'var(--vp-coral-ink)', backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)' }}>
                            <X size={15} /> Quitar filtros
                        </button>
                    )}
                </div>
            )}

            {/* ── Fila 1: cuánto vendiste + comprobantes ───────────────────── */}
            <div className="grid xl:grid-cols-12 gap-3 mb-3">
                <section className="xl:col-span-8 rounded-[22px] overflow-hidden text-white flex flex-col"
                    style={{ backgroundColor: 'var(--vp-navy)', boxShadow: '0 14px 30px -18px rgb(15 76 129 / 0.8)' }}>
                    <div className="flex-1 grid md:grid-cols-[minmax(0,auto)_minmax(0,1fr)] gap-x-8 gap-y-5 p-5 sm:p-6">
                        <div className="min-w-0">
                            <p className="text-[15px] font-medium" style={{ color: 'rgb(255 255 255 / 0.8)' }}>
                                {rangoActivo ? TITULO_RANGO[rangoActivo] : 'Vendiste en el periodo'}
                            </p>
                            <p className="font-display text-[36px] sm:text-[46px] font-extrabold tracking-tight leading-[1.05] tabular-nums mt-1 whitespace-nowrap">
                                {fmtS(kpis.monto_total)}
                            </p>
                            <Comparacion kpis={kpis} rango={rango_anterior} />
                            {(kpis.total_anuladas > 0 || kpis.credito_pendiente > 0) && (
                                <div className="flex flex-col items-start gap-1.5 mt-4">
                                    {kpis.total_anuladas > 0 && (
                                        <Aviso color="var(--vp-coral)" onClick={() => filtrar({ estado: 'anulada' })}>
                                            {plural(kpis.total_anuladas, 'venta anulada', 'ventas anuladas')} por {fmtS(kpis.monto_anuladas)}
                                        </Aviso>
                                    )}
                                    {kpis.credito_pendiente > 0 && (
                                        <Aviso color="var(--vp-amber)" onClick={() => filtrar({ tipo: 'credito' })}>
                                            Te deben {fmtS(kpis.credito_pendiente)} en créditos
                                        </Aviso>
                                    )}
                                </div>
                            )}
                        </div>
                        <Barras puntos={puntosBanda} oscuro
                            titulo={unDia ? 'Hora a hora' : 'Día a día'}
                            vacio={unDia ? 'Todavía no hay ventas hoy.' : 'Sin ventas en este periodo.'} />
                    </div>
                    <dl className="grid grid-cols-2 sm:grid-cols-5" style={{ borderTop: '1px solid rgb(255 255 255 / 0.12)' }}>
                        <Dato label="Ventas" valor={fmtInt(kpis.total_ventas)} />
                        <Dato label="Ticket promedio" valor={fmtS(kpis.ticket_promedio)} />
                        <Dato label="Clientes" valor={fmtInt(kpis.clientes_distintos)} />
                        <Dato label="Descuentos" valor={fmtS(kpis.monto_descuento)} />
                        <Dato label="IGV incluido" valor={fmtS(kpis.monto_igv)} />
                    </dl>
                </section>

                <Comprobantes data={por_comprobante} className="xl:col-span-4" />
            </div>

            {/* ── Fila 2: cobro, personas y horario ────────────────────────── */}
            <div className={`grid md:grid-cols-2 gap-3 mb-3 ${unDia ? 'xl:grid-cols-3' : 'xl:grid-cols-4'}`}>
                <Panel icon={<Wallet size={17} />} titulo="Medios de pago" color="var(--vp-mint)" tinta="var(--vp-mint-ink)"
                    detalle={fmtS(totalCobro)}>
                    <Filas items={por_metodo.map((m, i) => ({
                        clave: m.metodo_pago_id, label: m.nombre, valor: m.total,
                        color: CHART_COLORS[i % CHART_COLORS.length],
                        nota: plural(m.ocurrencias, 'pago', 'pagos'),
                    }))} vacio="Sin cobros en este periodo." />
                </Panel>
                <Panel icon={<Scale size={17} />} titulo="Contado y crédito" color="var(--vp-amber)" tinta="var(--vp-amber-ink)">
                    <ContadoCredito contado={kpis.monto_contado} credito={kpis.monto_credito} pendiente={kpis.credito_pendiente} />
                </Panel>
                <Panel icon={<UserRound size={17} />} titulo="Vendedores" color="var(--vp-sky)" tinta="var(--vp-navy)"
                    detalle={por_vendedor.length > 0 ? plural(por_vendedor.length, 'persona', 'personas') : undefined}>
                    <Filas avatar items={por_vendedor.map((v, i) => ({
                        clave: v.user_id, label: v.nombre, valor: v.total,
                        color: CHART_COLORS[(i + 3) % CHART_COLORS.length],
                        nota: `${plural(v.ventas, 'venta', 'ventas')}, ticket de ${fmtS(v.ventas > 0 ? v.total / v.ventas : 0)}`,
                    }))} vacio="Nadie vendió en este periodo." />
                </Panel>
                {!unDia && (
                    <Panel icon={<Clock3 size={17} />} titulo="Horas de más venta" color="var(--vp-sky)" tinta="var(--vp-navy)">
                        <Barras puntos={puntosHora} alto="flex-1 min-h-28" vacio="Sin ventas registradas por hora." />
                    </Panel>
                )}
            </div>

            {/* ── Fila 3: productos y clientes ─────────────────────────────── */}
            <div className="grid lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] gap-3 mb-3">
                <Panel icon={<Package size={17} />} titulo="Productos más vendidos" color="var(--vp-amber)" tinta="var(--vp-amber-ink)"
                    detalle={top_productos.length > 0 ? `Top ${top_productos.length}` : undefined} sinPadding>
                    {top_productos.length === 0 ? <Empty text="Todavía no hay productos vendidos en este periodo." /> : (
                        <ol className="pb-1">
                            {top_productos.map((p, i) => {
                                const part = pct(p.total, totalTop);
                                return (
                                    <li key={p.producto_id} className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-x-3 px-4 py-2.5"
                                        style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                        <span className="font-display h-8 w-8 rounded-lg flex items-center justify-center text-sm font-bold tabular-nums"
                                            style={i < 3
                                                ? { backgroundColor: 'var(--vp-amber)', color: 'var(--vp-midnight)' }
                                                : { backgroundColor: 'color-mix(in srgb, var(--color-border) 60%, transparent)', color: 'var(--color-text-muted)' }}>
                                            {i + 1}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }} title={p.producto_nombre}>{p.producto_nombre}</p>
                                            <p className="text-[13px] tabular-nums mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                                {fmtCant(p.cantidad)} und. vendidas
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-[15px] font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(p.total)}</p>
                                            <p className="text-[13px] tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>{part}% del top</p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    )}
                </Panel>
                <Panel icon={<Users size={17} />} titulo="Mejores clientes" color="var(--vp-navy)">
                    <Filas avatar items={top_clientes.map(c => ({
                        clave: c.cliente_id, label: c.nombre, valor: c.total, color: 'var(--vp-navy)',
                        nota: plural(c.ventas, 'compra', 'compras'),
                    }))} vacio="Sin clientes identificados en este periodo." />
                </Panel>
            </div>

            {/* ── Venta por venta ──────────────────────────────────────────── */}
            <section className="rounded-2xl overflow-hidden"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
                <header className="flex flex-wrap items-center gap-3 px-4 py-3.5"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))', borderBottom: '1px solid var(--color-border)' }}>
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-navy)' }}>
                        <Receipt size={18} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <h2 className="font-display text-[17px] font-bold leading-tight" style={{ color: 'var(--vp-navy)' }}>Venta por venta</h2>
                        <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            {plural(ventas.total, 'venta', 'ventas')}. Toca una fila para ver productos y pagos.
                        </p>
                    </div>
                    <div className="relative w-full sm:w-80">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input type="search" defaultValue={filters.buscar ?? ''} key={filters.buscar ?? ''}
                            placeholder="Buscar por número o cliente" aria-label="Buscar venta"
                            onKeyDown={e => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                            className="w-full text-sm rounded-xl pl-9 pr-8 py-2 border outline-none focus:ring-2"
                            style={{ ...fieldStyle, backgroundColor: 'var(--color-surface)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 35%, transparent)' } as React.CSSProperties} />
                        {filters.buscar && (
                            <button onClick={() => filtrar({ buscar: undefined })} aria-label="Quitar búsqueda"
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 rounded" style={{ color: 'var(--color-text-muted)' }}>
                                <X size={15} />
                            </button>
                        )}
                    </div>
                </header>

                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-sm table-fixed min-w-[760px]">
                        <colgroup>
                            <col className="w-11" /><col className="w-[16%]" /><col className="w-[13%]" />
                            <col /><col className="w-[22%]" /><col className="w-[14%]" />
                        </colgroup>
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                <ThV /><ThV>Venta</ThV><ThV>Fecha</ThV><ThV>Cliente</ThV><ThV>Pago</ThV><ThV right>Total</ThV>
                            </tr>
                        </thead>
                        <tbody>
                            {ventas.data.map(v => {
                                const abierta = abiertas.has(v.id);
                                const anulada = v.estado === 'anulada';
                                return (
                                    <Fragment key={v.id}>
                                        <tr onClick={() => toggle(v.id)} aria-expanded={abierta}
                                            className="cursor-pointer transition-colors hover:bg-[color-mix(in_srgb,var(--color-primary)_4%,transparent)]"
                                            style={{
                                                borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)',
                                                backgroundColor: abierta ? 'color-mix(in srgb, var(--color-primary) 5%, var(--color-surface))' : undefined,
                                            }}>
                                            <td className="pl-4 py-3" style={{ color: 'var(--color-primary)' }}>
                                                <ChevronDown size={17} className="transition-transform duration-200" style={{ transform: abierta ? 'rotate(180deg)' : undefined }} />
                                            </td>
                                            <td className="px-3 py-3">
                                                <Link href={route('ventas.show', v.id)} onClick={e => e.stopPropagation()}
                                                    className="text-[15px] font-bold tabular-nums hover:underline" style={{ color: 'var(--color-primary)' }}>
                                                    {v.numero}
                                                </Link>
                                                <p className="text-[13px] mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                    {COMPROBANTES[v.tipo_comprobante]?.label ?? v.tipo_comprobante}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3 whitespace-nowrap">
                                                <p className="font-semibold" style={{ color: 'var(--color-text)' }}>{soloFecha(v.fecha_venta)}</p>
                                                <p className="text-[13px] tabular-nums mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{soloHora(v.fecha_venta)}</p>
                                            </td>
                                            <td className="px-3 py-3">
                                                <p className="font-semibold truncate" style={{ color: 'var(--color-text)' }} title={nombreCliente(v)}>{nombreCliente(v)}</p>
                                                <p className="text-[13px] mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                    Atendió {(v.user as any)?.name ?? '—'}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3"><EstadoPago venta={v} /></td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                <span className={`font-display text-base font-bold tabular-nums ${anulada ? 'line-through' : ''}`}
                                                    style={{ color: anulada ? 'var(--color-text-muted)' : 'var(--color-text)' }}>
                                                    {fmtS(parseFloat(v.total))}
                                                </span>
                                            </td>
                                        </tr>
                                        {abierta && <DetalleVenta venta={v} colSpan={6} />}
                                    </Fragment>
                                );
                            })}
                            {ventas.data.length === 0 && (
                                <tr><td colSpan={6}><SinVentas buscando={!!filters.buscar || filtrosAvanzados > 0} /></td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Móvil */}
                <div className="md:hidden">
                    {ventas.data.length === 0 && <SinVentas buscando={!!filters.buscar || filtrosAvanzados > 0} />}
                    {ventas.data.map(v => {
                        const abierta = abiertas.has(v.id);
                        const anulada = v.estado === 'anulada';
                        return (
                            <div key={v.id} style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                <button onClick={() => toggle(v.id)} aria-expanded={abierta} className="w-full text-left px-4 py-3 flex items-start gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold truncate" style={{ color: 'var(--color-text)' }}>{nombreCliente(v)}</p>
                                        <p className="text-[13px] mt-0.5 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                            {v.numero}, {soloFecha(v.fecha_venta)} {soloHora(v.fecha_venta)}
                                        </p>
                                        <div className="mt-1.5"><EstadoPago venta={v} /></div>
                                    </div>
                                    <span className={`font-display text-base font-bold tabular-nums whitespace-nowrap ${anulada ? 'line-through' : ''}`}
                                        style={{ color: anulada ? 'var(--color-text-muted)' : 'var(--color-text)' }}>
                                        {fmtS(parseFloat(v.total))}
                                    </span>
                                </button>
                                {abierta && (
                                    <div className="px-4 pb-3">
                                        <DetalleContenido venta={v} />
                                        <Link href={route('ventas.show', v.id)} className="inline-block mt-2 text-sm font-semibold" style={{ color: 'var(--color-primary)' }}>
                                            Abrir la venta
                                        </Link>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                <Paginacion paginado={ventas} ruta="reportes.ventas" filters={filters as unknown as Record<string, unknown>} />
            </section>
        </AppLayout>
    );
}

/* ── Banda principal ───────────────────────────────────────────────────── */
function Comparacion({ kpis, rango }: { kpis: Kpis; rango: { desde: string; hasta: string } }) {
    const periodoPrev = `del ${fechaCorta(rango.desde)} al ${fechaCorta(rango.hasta)}`;
    if (kpis.variacion === null || kpis.prev_monto <= 0) {
        return (
            <p className="text-sm mt-2.5" style={{ color: 'rgb(255 255 255 / 0.72)' }}>
                No hubo ventas {periodoPrev} para comparar.
            </p>
        );
    }
    const sube = kpis.variacion >= 0;
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 mt-2.5">
            <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-bold tabular-nums"
                style={{ backgroundColor: sube ? 'var(--vp-mint)' : 'var(--vp-coral)', color: sube ? '#003B2B' : '#3B1200' }}>
                {sube ? '+' : '−'}{Math.abs(kpis.variacion)}%
            </span>
            <span className="text-sm" style={{ color: 'rgb(255 255 255 / 0.75)' }}>
                frente a {fmtS(kpis.prev_monto)} {periodoPrev}
            </span>
        </div>
    );
}

function Dato({ label, valor }: { label: string; valor: string }) {
    return (
        <div className="px-5 sm:px-6 py-3.5 sm:border-l sm:first:border-l-0" style={{ borderColor: 'rgb(255 255 255 / 0.12)' }}>
            <dt className="text-[13px]" style={{ color: 'rgb(255 255 255 / 0.68)' }}>{label}</dt>
            <dd className="font-display text-lg font-bold tabular-nums leading-tight mt-0.5 truncate">{valor}</dd>
        </div>
    );
}

function Aviso({ color, onClick, children }: { color: string; onClick: () => void; children: React.ReactNode }) {
    return (
        <button onClick={onClick}
            className="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[13px] font-semibold transition-colors hover:bg-white/15 focus-visible:outline focus-visible:outline-2"
            style={{ backgroundColor: 'rgb(255 255 255 / 0.1)', border: `1px solid color-mix(in srgb, ${color} 55%, transparent)`, color: '#fff', outlineColor: color }}>
            <AlertTriangle size={14} style={{ color }} />
            {children}
            <span className="underline underline-offset-2" style={{ color }}>Ver</span>
        </button>
    );
}

/* ── Barras (día a día / hora a hora) con el mejor punto destacado ─────── */
function Barras({ puntos, titulo, oscuro = false, alto = 'h-36', vacio }: {
    puntos: Punto[]; titulo?: string; oscuro?: boolean; alto?: string; vacio: string;
}) {
    const [hover, setHover] = useState<number | null>(null);
    const conVentas = puntos.some(p => p.total > 0);
    const tenue = oscuro ? 'rgb(255 255 255 / 0.65)' : 'var(--color-text-muted)';

    if (!conVentas) {
        return (
            <div className={`flex items-center justify-center rounded-xl text-sm ${alto}`}
                style={{ color: tenue, border: `1px dashed ${oscuro ? 'rgb(255 255 255 / 0.2)' : 'var(--color-border)'}` }}>
                {vacio}
            </div>
        );
    }

    const max = Math.max(...puntos.map(p => p.total));
    const mejorIdx = puntos.reduce((m, p, i) => (p.total > puntos[m].total ? i : m), 0);
    const focoIdx = hover ?? mejorIdx;
    const foco = puntos[focoIdx];
    // Con muchas barras solo rotulamos algunas para que el eje respire.
    const paso = puntos.length > 16 ? Math.ceil(puntos.length / 8) : 1;

    const barra = (i: number, p: Punto) => i === focoIdx
        ? (oscuro ? 'var(--vp-mint)' : 'var(--vp-navy)')
        : p.total === 0
            ? (oscuro ? 'rgb(255 255 255 / 0.1)' : 'color-mix(in srgb, var(--color-border) 70%, transparent)')
            : (oscuro ? 'rgb(255 255 255 / 0.42)' : 'color-mix(in srgb, var(--vp-sky) 45%, var(--color-surface))');

    return (
        <div className="min-w-0 flex flex-col h-full">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 mb-3">
                {titulo && <p className="text-sm font-semibold" style={{ color: oscuro ? '#fff' : 'var(--color-text)' }}>{titulo}</p>}
                <p className="text-[13px] tabular-nums" style={{ color: tenue }}>
                    {hover === null ? 'Mejor momento: ' : ''}
                    <strong className="font-semibold" style={{ color: oscuro ? '#fff' : 'var(--color-text)' }}>{foco.titulo}</strong>
                    {', '}{fmtS(foco.total)} en {plural(foco.ventas, 'venta', 'ventas')}
                </p>
            </div>
            <div className={`flex items-end gap-[3px] ${alto}`} onMouseLeave={() => setHover(null)}>
                {puntos.map((p, i) => (
                    <div key={p.clave} className="flex-1 h-full flex items-end" onMouseEnter={() => setHover(i)}>
                        <div className="w-full rounded-t-[4px] transition-colors duration-150"
                            style={{ height: `${p.total > 0 ? Math.max(4, (p.total / max) * 100) : 3}%`, backgroundColor: barra(i, p) }} />
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

/* ── Comprobantes emitidos: bloque protagonista ────────────────────────── */
function Comprobantes({ data, className = '' }: { data: PorComprob[]; className?: string }) {
    const porTipo = new Map(data.map(d => [d.tipo, d]));
    const tipos = Object.keys(COMPROBANTES).filter(t => COMPROBANTES[t].fijo || porTipo.has(t));
    // Tipos que no conocemos también se listan, para no esconder nada.
    data.forEach(d => { if (!COMPROBANTES[d.tipo] && !tipos.includes(d.tipo)) tipos.push(d.tipo); });
    const totalDocs = data.reduce((s, d) => s + d.ventas, 0);
    const totalMonto = data.reduce((s, d) => s + d.total, 0);

    return (
        <Panel icon={<FileText size={17} />} titulo="Comprobantes emitidos" color="var(--vp-navy)" className={className}
            detalle={<span><strong className="font-display text-base" style={{ color: 'var(--color-text)' }}>{fmtInt(totalDocs)}</strong><span className="hidden sm:inline"> en total</span></span>}>
            <div className="grid gap-2 h-full auto-rows-fr">
                {tipos.map(t => {
                    const c = COMPROBANTES[t] ?? { label: t, plural: t, color: 'var(--color-secondary)', tinta: 'var(--color-text)' };
                    const d = porTipo.get(t);
                    const n = d?.ventas ?? 0;
                    const monto = d?.total ?? 0;
                    const part = pct(monto, totalMonto);
                    return (
                        <div key={t} className="rounded-xl px-4 py-3 grid grid-cols-[minmax(3rem,auto)_minmax(0,1fr)] items-center gap-3.5"
                            style={{
                                backgroundColor: n > 0 ? `color-mix(in srgb, ${c.color} 12%, var(--color-surface))` : 'color-mix(in srgb, var(--color-bg) 70%, var(--color-surface))',
                                border: `1px solid ${n > 0 ? `color-mix(in srgb, ${c.color} 30%, transparent)` : 'var(--color-border)'}`,
                            }}>
                            <span className="font-display text-[26px] sm:text-[32px] font-extrabold leading-none tabular-nums text-center"
                                style={{ color: n > 0 ? c.tinta : 'color-mix(in srgb, var(--color-text-muted) 55%, transparent)' }}>
                                {fmtInt(n)}
                            </span>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                                    <p className="text-[15px] font-bold" style={{ color: n > 0 ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                                        {n === 1 ? c.label : c.plural}
                                    </p>
                                    <span className="font-display text-base font-bold tabular-nums whitespace-nowrap"
                                        style={{ color: n > 0 ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                                        {fmtS(monto)}
                                    </span>
                                </div>
                                {n > 0 ? (
                                    <div className="flex items-center gap-2 mt-1.5">
                                        <div className="h-1.5 flex-1 rounded-full overflow-hidden" style={{ backgroundColor: `color-mix(in srgb, ${c.color} 18%, transparent)` }}>
                                            <div className="h-full rounded-full" style={{ width: `${Math.max(3, part)}%`, backgroundColor: c.color }} />
                                        </div>
                                        <span className="text-xs font-semibold tabular-nums" style={{ color: c.tinta }}>{part}% de lo vendido</span>
                                    </div>
                                ) : (
                                    <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>No se emitieron</p>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </Panel>
    );
}

/* ── Filas con barra de participación (medios, vendedores, clientes) ───── */
function Filas({ items, avatar = false, vacio }: {
    items: { clave: string | number; label: string; valor: number; color: string; nota?: string }[];
    avatar?: boolean; vacio: string;
}) {
    const total = items.reduce((s, it) => s + Math.max(0, it.valor), 0);
    if (items.length === 0 || total <= 0) return <Empty text={vacio} />;
    return (
        <ul className="space-y-3">
            {items.map(it => {
                const part = pct(it.valor, total);
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
                                <p className="text-[15px] font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(it.valor)}</p>
                            </div>
                            <div className="flex items-center gap-2 mt-1">
                                <div className="h-1.5 flex-1 rounded-full overflow-hidden" style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 65%, transparent)' }}>
                                    <div className="h-full rounded-full" style={{ width: `${Math.max(2, part)}%`, backgroundColor: it.color }} />
                                </div>
                                <span className="text-xs font-semibold tabular-nums w-9 text-right" style={{ color: 'var(--color-text-muted)' }}>{part}%</span>
                            </div>
                            {it.nota && <p className="text-[13px] mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>{it.nota}</p>}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function FilaMonto({ label, valor, tinta, fondo }: { label: string; valor: number; tinta: string; fondo: string }) {
    return (
        <div className="rounded-xl px-3 py-2.5 flex items-center justify-between gap-2"
            style={{ backgroundColor: `color-mix(in srgb, ${fondo} 13%, var(--color-surface))` }}>
            <span className="text-[13px] font-semibold" style={{ color: tinta }}>{label}</span>
            <span className="text-[15px] font-bold tabular-nums" style={{ color: tinta }}>{fmtS(valor)}</span>
        </div>
    );
}

function ContadoCredito({ contado, credito, pendiente }: { contado: number; credito: number; pendiente: number }) {
    const total = contado + credito;
    if (total <= 0) return <Empty text="Sin ventas en este periodo." />;
    const pC = pct(contado, total);
    return (
        <div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <p className="text-[13px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>Contado</p>
                    <p className="font-display text-xl font-bold tabular-nums leading-tight mt-0.5" style={{ color: 'var(--color-text)' }}>{fmtS(contado)}</p>
                </div>
                <div className="text-right">
                    <p className="text-[13px] font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>Crédito</p>
                    <p className="font-display text-xl font-bold tabular-nums leading-tight mt-0.5" style={{ color: 'var(--color-text)' }}>{fmtS(credito)}</p>
                </div>
            </div>
            <div className="flex h-2.5 rounded-full overflow-hidden gap-0.5 mt-3 mb-1.5" role="img" aria-label={`Contado ${pC}%, crédito ${100 - pC}%`}>
                {pC > 0 && <div style={{ width: `${pC}%`, backgroundColor: 'var(--vp-mint)' }} />}
                {pC < 100 && <div style={{ width: `${100 - pC}%`, backgroundColor: 'var(--vp-amber)' }} />}
            </div>
            <div className="flex justify-between text-xs font-semibold tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                <span>{pC}%</span><span>{100 - pC}%</span>
            </div>
            {credito > 0 ? (
                <div className="mt-4 space-y-1.5">
                    <p className="text-[13px] font-semibold" style={{ color: 'var(--color-text)' }}>De lo vendido al crédito</p>
                    <FilaMonto label="Ya te pagaron" valor={Math.max(0, credito - pendiente)} tinta="var(--vp-mint-ink)" fondo="var(--vp-mint)" />
                    <FilaMonto label="Aún por cobrar" valor={pendiente} tinta="var(--vp-amber-ink)" fondo="var(--vp-amber)" />
                </div>
            ) : (
                <div className="mt-4 rounded-xl px-3 py-2.5 text-[13px] font-semibold"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 11%, var(--color-surface))', color: 'var(--vp-mint-ink)' }}>
                    Todo se vendió al contado.
                </div>
            )}
        </div>
    );
}

/* ── Tabla de ventas ──────────────────────────────────────────────────── */
function ThV({ children, right = false }: { children?: React.ReactNode; right?: boolean }) {
    return (
        <th className={`px-3 py-2.5 text-[13px] font-semibold whitespace-nowrap ${right ? 'text-right pr-4' : 'text-left'}`}
            style={{ color: 'var(--color-text-muted)' }}>
            {children}
        </th>
    );
}

function EstadoPago({ venta }: { venta: Venta }) {
    const saldo = parseFloat(String(venta.saldo_pendiente ?? 0));
    const [texto, color, tinta] = venta.estado === 'anulada'
        ? ['Anulada', 'var(--vp-coral)', 'var(--vp-coral-ink)']
        : venta.es_credito
            ? [saldo > 0 ? `Crédito, debe ${fmtS(saldo)}` : 'Crédito pagado', 'var(--vp-amber)', 'var(--vp-amber-ink)']
            : ['Contado', 'var(--vp-mint)', 'var(--vp-mint-ink)'];
    return (
        <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap tabular-nums"
            style={{ color: tinta, backgroundColor: `color-mix(in srgb, ${color} 14%, transparent)` }}>
            <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: color }} />
            {texto}
        </span>
    );
}

function SinVentas({ buscando }: { buscando: boolean }) {
    return (
        <div className="text-center py-12 px-4">
            <ShoppingCart size={34} className="mx-auto mb-3" style={{ color: 'color-mix(in srgb, var(--vp-navy) 30%, transparent)' }} />
            <p className="text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>
                {buscando ? 'Ninguna venta coincide con la búsqueda' : 'No hubo ventas en este periodo'}
            </p>
            <p className="text-sm mt-1" style={{ color: 'var(--color-text-muted)' }}>
                {buscando ? 'Prueba con otro número o quita los filtros.' : 'Elige otro rango de fechas arriba.'}
            </p>
        </div>
    );
}

/* ── Fila expandida: productos + pagos de la venta ─────────────────────── */
function DetalleVenta({ venta, colSpan }: { venta: Venta; colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-5 pb-4 pt-1"
                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 5%, var(--color-surface))' }}>
                <DetalleContenido venta={venta} />
            </td>
        </tr>
    );
}

function DetalleContenido({ venta }: { venta: Venta }) {
    const items = (venta.items as any[]) ?? [];
    const pagos = (venta.pagos as any[]) ?? [];
    return (
        <div className="grid md:grid-cols-[minmax(0,1fr)_300px] gap-3">
            <div className="rounded-xl p-3.5" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <p className="text-sm font-bold mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                    {plural(items.length, 'producto', 'productos')}
                </p>
                <ul>
                    {items.map(it => {
                        const dscto = parseFloat(it.descuento_item);
                        return (
                            <li key={it.id} className="flex items-baseline gap-3 py-2 text-sm"
                                style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                <span className="tabular-nums font-bold w-12 flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
                                    {fmtCant(parseFloat(it.cantidad))}×
                                </span>
                                <span className="min-w-0 flex-1" style={{ color: 'var(--color-text)' }}>
                                    {it.producto_nombre}
                                    <span className="text-[13px] ml-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                        {it.unidad_nombre}, {fmtS(parseFloat(it.precio_unitario))} c/u
                                    </span>
                                    {dscto > 0 && (
                                        <span className="text-[13px] ml-1.5 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>
                                            desc. {fmtS(dscto)}
                                        </span>
                                    )}
                                </span>
                                <span className="font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                    {fmtS(parseFloat(it.subtotal))}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            </div>
            <div className="rounded-xl p-3.5" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <p className="text-sm font-bold mb-2" style={{ color: 'var(--vp-navy)' }}>Pagos</p>
                <div className="space-y-1.5">
                    {pagos.length === 0 && (
                        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            {venta.es_credito ? 'Venta a crédito sin pago inicial.' : 'Sin pagos registrados.'}
                        </p>
                    )}
                    {pagos.map(p => (
                        <div key={p.id} className="flex items-center justify-between gap-2 text-sm rounded-lg px-3 py-2"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 10%, var(--color-surface))' }}>
                            <span className="min-w-0 truncate" style={{ color: 'var(--color-text)' }}>
                                {p.metodo_pago?.nombre ?? '—'}
                                {p.referencia && <span className="ml-1 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>ref. {p.referencia}</span>}
                            </span>
                            <span className="font-bold tabular-nums" style={{ color: 'var(--vp-mint-ink)' }}>{fmtS(parseFloat(p.monto))}</span>
                        </div>
                    ))}
                    {venta.es_credito && parseFloat(String(venta.saldo_pendiente ?? 0)) > 0 && (
                        <div className="flex items-center justify-between text-sm rounded-lg px-3 py-2"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 14%, var(--color-surface))' }}>
                            <span style={{ color: 'var(--color-text)' }}>Saldo por cobrar</span>
                            <span className="font-bold tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>{fmtS(parseFloat(String(venta.saldo_pendiente)))}</span>
                        </div>
                    )}
                </div>
                {venta.observacion && (
                    <p className="text-sm mt-2.5" style={{ color: 'var(--color-text-muted)' }}>Nota: {venta.observacion}</p>
                )}
            </div>
        </div>
    );
}
