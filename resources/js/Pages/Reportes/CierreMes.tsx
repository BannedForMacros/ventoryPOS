import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    CalendarRange, TrendingUp, TrendingDown, Receipt, Percent, CreditCard,
    HandCoins, Package, Scale, Landmark, FileText, Wallet, ShoppingBag,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import { AreaChart, DonutChart, BarList, CHART_COLORS } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Empty, Th,
    theadStyle, zebra, fmtS, fmtInt, diaLabel,
} from '@/Components/Reportes/ReportUI';
import type { Local, PageProps } from '@/types';

interface Kpis {
    ventas: number; ventas_count: number; ticket_promedio: number;
    anuladas_count: number; anuladas_monto: number;
    descuentos: number; igv: number;
    costo: number; utilidad_bruta: number; margen_bruto: number | null;
    gastos: number; gastos_count: number;
    devoluciones: number; devoluciones_count: number;
    utilidad_neta: number; margen_neto: number | null;
    credito_otorgado: number; credito_count: number; credito_cobrado: number;
    por_cobrar: number; por_cobrar_count: number;
    compras: number; compras_count: number; compras_pagado: number; compras_pendiente: number;
    prev_ventas: number; prev_gastos: number; variacion_ventas: number | null;
    rango_anterior: { desde: string; hasta: string };
}

interface SerieDia    { dia: string; ventas: number; gastos: number; neta: number; }
interface PorComprob  { tipo: string; emitidos: number; total: number; anulados: number; primer_numero: string | null; ultimo_numero: string | null; }
interface Electronico { estado: string; count: number; }
interface PorMetodo   { metodo_pago_id: number; nombre: string; ventas: number; abonos: number; total: number; }
interface GastoTipo   { nombre: string; categoria: string; total: number; count: number; }
interface GastoCuenta { nombre: string; total: number; count: number; }
interface Deudor      { nombre: string; saldo: number; ventas: number; }
interface CompraProv  { nombre: string; total: number; pagado: number; pendiente: number; count: number; }

interface Props extends PageProps {
    kpis: Kpis;
    serie_diaria: SerieDia[];
    por_comprobante: PorComprob[];
    electronicos: Electronico[];
    por_metodo: PorMetodo[];
    gastos_por_tipo: GastoTipo[];
    gastos_por_cuenta: GastoCuenta[];
    top_deudores: Deudor[];
    compras_por_proveedor: CompraProv[];
    locales: Local[];
    filters: { fecha_desde: string; fecha_hasta: string; local_id?: string };
}

const COMPROBANTE_LABEL: Record<string, string> = {
    ticket:           'Ticket',
    boleta:           'Boleta',
    factura:          'Factura',
    boleta_externa:   'Boleta externa',
    factura_externa:  'Factura externa',
};

const ESTADO_SUNAT_LABEL: Record<string, string> = {
    aceptado:          'Aceptados por SUNAT',
    enviando:          'Enviando',
    enviado:           'Enviados (por confirmar)',
    pendiente_resumen: 'Pendientes de resumen diario',
    en_resumen:        'En resumen diario',
    pendiente:         'Pendientes de envío',
    error_envio:       'Error de envío',
    error_mapeo:       'Error de datos',
    rechazado:         'Rechazados por SUNAT',
    anulado:           'Anulados (nota de crédito)',
    no_emitido:        'No emitidos',
    simulado:          'Simulados (prueba)',
};

const mesPasado = () => {
    const h = new Date();
    const primero = new Date(h.getFullYear(), h.getMonth() - 1, 1);
    const ultimo  = new Date(h.getFullYear(), h.getMonth(), 0);
    const iso = (d: Date) => {
        const off = d.getTimezoneOffset() * 60000;
        return new Date(d.getTime() - off).toISOString().slice(0, 10);
    };
    return { fecha_desde: iso(primero), fecha_hasta: iso(ultimo) };
};

export default function ReportesCierreMes({
    kpis, serie_diaria, por_comprobante, electronicos, por_metodo,
    gastos_por_tipo, gastos_por_cuenta, top_deudores, compras_por_proveedor,
    locales, filters, flash,
}: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.cierre-mes'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.cierre-mes'), {}, { preserveState: true, replace: true });

    const cobradoTotal = por_metodo.reduce((s, m) => s + m.total, 0);
    const totalComprobantes = por_comprobante.reduce((s, c) => s + c.emitidos, 0);
    const totalElectronicos = electronicos.reduce((s, e) => s + e.count, 0);

    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Cierre de mes">
            <PageHeader
                icon={<CalendarRange size={22} />}
                title="Cierre de mes"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · estado consolidado del período`}
            />

            {/* Filtros: rango seleccionable + atajos de mes */}
            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={!!filters.local_id}
                rangosExtra={[
                    ['Mes pasado', mesPasado],
                    ['Este año', () => {
                        const h = new Date();
                        const iso = (d: Date) => {
                            const off = d.getTimezoneOffset() * 60000;
                            return new Date(d.getTime() - off).toISOString().slice(0, 10);
                        };
                        return { fecha_desde: iso(new Date(h.getFullYear(), 0, 1)), fecha_hasta: iso(h) };
                    }],
                ]}>
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
            </FiltrosReporte>

            {/* KPIs principales */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<TrendingUp size={18} />} label="Ventas" value={fmtS(kpis.ventas)}
                    sub={kpis.variacion_ventas !== null
                        ? `${kpis.variacion_ventas >= 0 ? '▲' : '▼'} ${Math.abs(kpis.variacion_ventas)}% vs período anterior · ${fmtInt(kpis.ventas_count)} ventas`
                        : `${fmtInt(kpis.ventas_count)} ventas · ticket prom. ${fmtS(kpis.ticket_promedio)}`}
                    subColor={kpis.variacion_ventas !== null ? (kpis.variacion_ventas >= 0 ? 'var(--color-success)' : 'var(--color-danger)') : undefined}
                    color="var(--color-success)" />
                <Kpi icon={<Scale size={18} />} label="Utilidad neta" value={fmtS(kpis.utilidad_neta)}
                    sub={kpis.margen_neto !== null ? `margen ${kpis.margen_neto}% · bruta ${fmtS(kpis.utilidad_bruta)}` : `bruta ${fmtS(kpis.utilidad_bruta)}`}
                    subColor={kpis.utilidad_neta < 0 ? 'var(--color-danger)' : undefined}
                    color={kpis.utilidad_neta >= 0 ? 'var(--color-primary)' : 'var(--color-danger)'} />
                <Kpi icon={<TrendingDown size={18} />} label="Gastos" value={fmtS(kpis.gastos)}
                    sub={`${fmtInt(kpis.gastos_count)} gastos${kpis.devoluciones > 0 ? ` · devoluciones ${fmtS(kpis.devoluciones)}` : ''}`}
                    color="var(--color-danger)" />
                <Kpi icon={<HandCoins size={18} />} label="Por cobrar (créditos)" value={fmtS(kpis.por_cobrar)}
                    sub={kpis.credito_otorgado > 0
                        ? `otorgado ${fmtS(kpis.credito_otorgado)} · cobrado ${fmtS(kpis.credito_cobrado)}`
                        : `${fmtInt(kpis.por_cobrar_count)} ventas con saldo`}
                    subColor={kpis.por_cobrar > 0 ? 'var(--color-warning)' : undefined}
                    color="#8b5cf6" />
                <Kpi icon={<ShoppingBag size={18} />} label="Compras" value={fmtS(kpis.compras)}
                    sub={kpis.compras_count > 0
                        ? `pagado ${fmtS(kpis.compras_pagado)} · por pagar ${fmtS(kpis.compras_pendiente)}`
                        : 'sin compras en el período'}
                    subColor={kpis.compras_pendiente > 0 ? 'var(--color-warning)' : undefined}
                    color="var(--vp-navy)" />
            </div>

            {/* Estado de resultados */}
            <ReportCard className="mb-4" icon={<Scale size={14} />} title="Estado de resultados del período"
                badge={`anterior: ${kpis.rango_anterior.desde} → ${kpis.rango_anterior.hasta}`} sinPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <tbody>
                            {[
                                { label: 'Ventas (netas de vuelto)', monto: kpis.ventas, nota: `${fmtInt(kpis.ventas_count)} comprobantes · IGV incluido ${fmtS(kpis.igv)}`, color: 'var(--color-success)' },
                                { label: 'Costo de lo vendido', monto: -kpis.costo, nota: 'costo congelado al momento de cada venta', color: 'var(--color-text-muted)' },
                                { label: 'Utilidad bruta', monto: kpis.utilidad_bruta, nota: kpis.margen_bruto !== null ? `margen ${kpis.margen_bruto}%` : '', color: 'var(--color-primary)', fuerte: true },
                                { label: 'Gastos operativos', monto: -kpis.gastos, nota: `${fmtInt(kpis.gastos_count)} gastos registrados`, color: 'var(--color-danger)' },
                                { label: 'Devoluciones', monto: -kpis.devoluciones, nota: kpis.devoluciones_count > 0 ? `${fmtInt(kpis.devoluciones_count)} devoluciones` : 'sin devoluciones', color: 'var(--color-warning)' },
                                { label: 'Utilidad neta del período', monto: kpis.utilidad_neta, nota: kpis.margen_neto !== null ? `margen neto ${kpis.margen_neto}%` : '', color: kpis.utilidad_neta >= 0 ? 'var(--color-success)' : 'var(--color-danger)', fuerte: true },
                            ].map((f, i) => (
                                <tr key={f.label} style={{
                                    ...zebra(i),
                                    ...(f.fuerte ? { backgroundColor: `color-mix(in srgb, ${f.color} 8%, var(--color-surface))` } : {}),
                                }}>
                                    <td className={`px-4 py-2.5 ${f.fuerte ? 'font-bold' : 'font-medium'}`} style={{ color: 'var(--color-text)' }}>
                                        {f.label}
                                        {f.nota && <span className="block text-[11px] font-normal" style={{ color: 'var(--color-text-muted)' }}>{f.nota}</span>}
                                    </td>
                                    <td className={`px-4 py-2.5 text-right tabular-nums ${f.fuerte ? 'text-base font-extrabold' : 'font-bold'}`}
                                        style={{ color: f.color }}>
                                        {f.monto < 0 ? `−${fmtS(Math.abs(f.monto))}` : fmtS(f.monto)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </ReportCard>

            {/* Serie diaria + cobros por método */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<CalendarRange size={14} />} title="Ventas, gastos y utilidad por día">
                    <AreaChart
                        data={serieChart}
                        series={[
                            { key: 'ventas', label: 'Ventas', color: 'var(--color-success)', relleno: true },
                            { key: 'gastos', label: 'Gastos', color: 'var(--color-danger)' },
                            { key: 'neta',   label: 'Utilidad neta', color: 'var(--color-primary)' },
                        ]}
                        height={240}
                    />
                </ReportCard>
                <ReportCard icon={<Wallet size={14} />} title="Cobrado por método de pago" accent="#8b5cf6" sinPadding>
                    {por_metodo.length === 0 ? <Empty text="Sin cobros en el período" /> : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={theadStyle}>
                                        <Th>Método</Th><Th right>Ventas</Th><Th right>Abonos</Th><Th right>Total</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {por_metodo.map((m, i) => (
                                        <tr key={m.metodo_pago_id} style={zebra(i)}>
                                            <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>{m.nombre}</td>
                                            <td className="px-3 py-2 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtS(m.ventas)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums" style={{ color: m.abonos > 0 ? 'var(--color-primary)' : 'var(--color-text-muted)' }}>
                                                {m.abonos > 0 ? fmtS(m.abonos) : '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums font-bold" style={{ color: '#8b5cf6' }}>{fmtS(m.total)}</td>
                                        </tr>
                                    ))}
                                    <tr style={{ borderTop: '2px solid var(--color-border)', backgroundColor: 'color-mix(in srgb, #8b5cf6 7%, var(--color-surface))' }}>
                                        <td className="px-3 py-2 font-bold" style={{ color: 'var(--color-text)' }}>Total cobrado</td>
                                        <td colSpan={2} />
                                        <td className="px-3 py-2 text-right tabular-nums font-extrabold" style={{ color: '#8b5cf6' }}>{fmtS(cobradoTotal)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}
                </ReportCard>
            </div>

            {/* Comprobantes + estado SUNAT */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<FileText size={14} />} title="Comprobantes emitidos por tipo"
                    badge={`${fmtInt(totalComprobantes)} comprobantes`} accent="var(--color-warning)" sinPadding>
                    {por_comprobante.length === 0 ? <Empty text="Sin comprobantes en el período" /> : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={theadStyle}>
                                        <Th>Tipo</Th><Th right>Emitidos</Th><Th right>Anulados</Th><Th>Numeración</Th><Th right>Total</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {por_comprobante.map((c, i) => (
                                        <tr key={c.tipo} style={zebra(i)}>
                                            <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>
                                                {COMPROBANTE_LABEL[c.tipo] ?? c.tipo}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtInt(c.emitidos)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums" style={{ color: c.anulados > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                                {c.anulados > 0 ? fmtInt(c.anulados) : '—'}
                                            </td>
                                            <td className="px-3 py-2 text-[11px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                                {c.primer_numero
                                                    ? c.primer_numero === c.ultimo_numero ? c.primer_numero : `${c.primer_numero} → ${c.ultimo_numero}`
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums font-bold" style={{ color: 'var(--color-success)' }}>{fmtS(c.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ReportCard>
                <ReportCard icon={<Landmark size={14} />} title="Comprobantes electrónicos SUNAT"
                    badge={`${fmtInt(totalElectronicos)}`} accent="var(--color-primary)">
                    {electronicos.length === 0 ? <Empty text="Sin comprobantes electrónicos en el período" /> : (
                        <div className="space-y-1.5">
                            {electronicos.map(e => {
                                const ok  = e.estado === 'aceptado';
                                const mal = ['rechazado', 'error_envio', 'error_mapeo'].includes(e.estado);
                                const color = ok ? 'var(--color-success)' : mal ? 'var(--color-danger)' : 'var(--color-warning)';
                                return (
                                    <div key={e.estado} className="flex items-center justify-between rounded-xl px-3 py-2"
                                        style={{ backgroundColor: `color-mix(in srgb, ${color} 8%, var(--color-surface))`, border: `1px solid color-mix(in srgb, ${color} 22%, var(--color-border))` }}>
                                        <span className="text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                            {ESTADO_SUNAT_LABEL[e.estado] ?? e.estado}
                                        </span>
                                        <span className="text-xs font-extrabold tabular-nums px-2 py-0.5 rounded-full"
                                            style={{ color, backgroundColor: `color-mix(in srgb, ${color} 13%, transparent)` }}>
                                            {fmtInt(e.count)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </ReportCard>
            </div>

            {/* Gastos + créditos + compras */}
            <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
                <ReportCard icon={<TrendingDown size={14} />} title="Gastos por tipo" accent="var(--color-danger)">
                    {gastos_por_tipo.length === 0 ? <Empty text="Sin gastos en el período" /> : (
                        <>
                            <BarList
                                data={gastos_por_tipo.map(g => ({ label: g.nombre, valor: g.total, extra: `${g.count} ×` }))}
                                multicolor
                            />
                            {gastos_por_cuenta.length > 0 && (
                                <div className="mt-3 pt-3" style={{ borderTop: '1px dashed var(--color-border)' }}>
                                    <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                        Pagado desde
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {gastos_por_cuenta.map(c => (
                                            <span key={c.nombre} className="text-[11px] font-semibold px-2 py-1 rounded-full"
                                                style={{ color: 'var(--color-danger)', backgroundColor: 'color-mix(in srgb, var(--color-danger) 8%, transparent)' }}>
                                                {c.nombre}: {fmtS(c.total)}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </ReportCard>
                <ReportCard icon={<HandCoins size={14} />} title="Créditos: mayores deudores al corte"
                    badge={`por cobrar ${fmtS(kpis.por_cobrar)}`} accent="#8b5cf6">
                    {top_deudores.length === 0 ? <Empty text="Sin deudas por cobrar al corte" /> : (
                        <BarList
                            data={top_deudores.map(d => ({ label: d.nombre, valor: d.saldo, extra: `${d.ventas} ventas` }))}
                        />
                    )}
                </ReportCard>
                <ReportCard icon={<Package size={14} />} title="Compras por proveedor"
                    badge={kpis.compras_pendiente > 0 ? `por pagar ${fmtS(kpis.compras_pendiente)}` : undefined}
                    accent="var(--vp-navy)" sinPadding>
                    {compras_por_proveedor.length === 0 ? <Empty text="Sin compras en el período" /> : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={theadStyle}>
                                        <Th>Proveedor</Th><Th right>Total</Th><Th right>Pagado</Th><Th right>Pendiente</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {compras_por_proveedor.map((p, i) => (
                                        <tr key={p.nombre} style={zebra(i)}>
                                            <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>
                                                {p.nombre}
                                                <span className="block text-[10px] font-normal" style={{ color: 'var(--color-text-muted)' }}>{p.count} compras</span>
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtS(p.total)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums" style={{ color: 'var(--color-success)' }}>{fmtS(p.pagado)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums font-bold"
                                                style={{ color: p.pendiente > 0 ? 'var(--color-warning)' : 'var(--color-text-muted)' }}>
                                                {p.pendiente > 0 ? fmtS(p.pendiente) : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ReportCard>
            </div>

            {/* Resumen final para los dueños */}
            <ReportCard icon={<Receipt size={14} />} title="Resumen del cierre" accent="var(--color-success)">
                <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    {[
                        { label: 'Cobrado en el período', valor: fmtS(cobradoTotal), nota: 'ventas de contado + abonos a créditos, por método', color: 'var(--color-success)' },
                        { label: 'Vendido a crédito', valor: fmtS(kpis.credito_otorgado), nota: `${fmtInt(kpis.credito_count)} ventas nuevas a crédito`, color: '#8b5cf6' },
                        { label: 'Descuentos otorgados', valor: fmtS(kpis.descuentos), nota: 'sobre ventas completadas', color: 'var(--color-warning)' },
                        { label: 'Ventas anuladas', valor: fmtInt(kpis.anuladas_count), nota: kpis.anuladas_monto > 0 ? fmtS(kpis.anuladas_monto) + ' revertidos' : 'sin monto', color: kpis.anuladas_count > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' },
                    ].map(x => (
                        <div key={x.label} className="rounded-xl px-3.5 py-3"
                            style={{ backgroundColor: `color-mix(in srgb, ${x.color} 8%, var(--color-surface))`, border: `1px solid color-mix(in srgb, ${x.color} 20%, var(--color-border))` }}>
                            <p className="text-[10px] font-bold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>{x.label}</p>
                            <p className="text-lg font-extrabold tabular-nums" style={{ color: x.color }}>{x.valor}</p>
                            <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>{x.nota}</p>
                        </div>
                    ))}
                </div>
            </ReportCard>
        </AppLayout>
    );
}
