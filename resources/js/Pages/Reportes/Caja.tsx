import { Fragment, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Wallet, Calendar, ChevronDown, ChevronUp, Landmark, Scale,
    TrendingDown, TrendingUp, UserRound, Banknote, ClipboardList, HandCoins,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import { AreaChart, BarList } from '@/Components/UI/Charts';
import {
    Kpi, ReportCard, FiltrosReporte, FieldSelect, Paginacion, Empty, Th,
    theadStyle, zebra, fmtS, fmtInt, diaLabel, fechaHora,
    type Paginado,
} from '@/Components/Reportes/ReportUI';
import type { Local, User, PageProps } from '@/types';

interface Kpis {
    total_turnos:    number;
    turnos_abiertos: number;
    turnos_cerrados: number;
    ventas_total:    number;
    ventas_count:    number;
    gastos_total:    number;
    sobrantes:       number;
    faltantes:       number;
    diferencia_neta: number;
    con_descuadre:   number;
    retiros_total:   number;
}

interface SerieDia  { dia: string; ventas: number; gastos: number; diferencia: number; }
interface PorCaja   { nombre: string; total: number; turnos: number; }
interface PorCajero { nombre: string; total: number; turnos: number; diferencia: number; }

interface ArqueoMetodo { id: number; nombre: string; monto_declarado: number; }
interface GastoTurno   { id: number; concepto: string; monto: number; }

interface RetiroTurno {
    id:       number;
    concepto: string;
    monto:    number;
    momento:  'turno' | 'cierre';
    estado:   'registrado' | 'aprobado';
    usuario:  string;
    fecha:    string | null;
}

interface TurnoRow {
    id: number;
    caja:  { id: number; nombre: string } | null;
    local: { id: number; nombre: string } | null;
    user:  { id: number; name: string } | null;
    user_cierre: { id: number; name: string } | null;
    estado: 'abierto' | 'cerrado';
    fecha_apertura: string;
    fecha_cierre:   string | null;
    monto_apertura:         number;
    monto_fondos_adicionales: number;
    monto_caja_chica:       number;
    monto_cierre_declarado: number | null;
    monto_cierre_esperado:  number | null;
    diferencia:             number | null;
    ventas_count: number;
    ventas_total: number;
    gastos_total: number;
    observacion_apertura: string | null;
    observacion_cierre:   string | null;
    arqueo_metodos: ArqueoMetodo[];
    gastos:         GastoTurno[];
    efectivo_arrastre: number | null;
    destino_efectivo:  'caja' | 'administracion' | 'parcial' | null;
    retiros_total:     number;
    retiros:           RetiroTurno[];
}

interface Filters {
    fecha_desde: string; fecha_hasta: string;
    estado?: string; local_id?: string; caja_id?: string; user_id?: string;
}

interface Props extends PageProps {
    turnos:       Paginado<TurnoRow>;
    kpis:         Kpis;
    serie_diaria: SerieDia[];
    por_caja:     PorCaja[];
    por_cajero:   PorCajero[];
    locales:      Local[];
    cajas:        { id: number; nombre: string; local_id: number }[];
    usuarios:     Pick<User, 'id' | 'name'>[];
    filters:      Filters;
}

const colorDif = (d: number | null) =>
    d === null ? 'var(--color-text-muted)'
        : Math.abs(d) < 0.01 ? 'var(--color-success)'
        : d > 0 ? 'var(--color-warning)' : 'var(--color-danger)';

export default function ReportesCaja({
    turnos, kpis, serie_diaria, por_caja, por_cajero, locales, cajas, usuarios, filters, flash,
}: Props) {
    const [abiertos, setAbiertos] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Record<string, string | undefined>) {
        router.get(route('reportes.caja'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    const limpiar = () => router.get(route('reportes.caja'), {}, { preserveState: true, replace: true });
    const tieneFiltros = !!(filters.estado || filters.local_id || filters.caja_id || filters.user_id);

    function toggle(id: number) {
        setAbiertos(prev => {
            const s = new Set(prev);
            s.has(id) ? s.delete(id) : s.add(id);
            return s;
        });
    }

    const serieChart = serie_diaria.map(s => ({ label: diaLabel(s.dia), ...s }));

    return (
        <AppLayout title="Reporte de caja">
            <PageHeader
                icon={<Wallet size={22} />}
                title="Reporte de caja"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${fmtInt(kpis.total_turnos)} turnos`}
            />

            <FiltrosReporte
                fechaDesde={filters.fecha_desde} fechaHasta={filters.fecha_hasta}
                onChange={filtrar} onClear={limpiar} tieneFiltros={tieneFiltros}>
                <FieldSelect label="Estado" value={filters.estado ?? ''}
                    onChange={v => filtrar({ estado: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'abierto', label: 'Abiertos' },
                        { value: 'cerrado', label: 'Cerrados' },
                    ]} />
                {locales.length > 1 && (
                    <FieldSelect label="Local" value={filters.local_id ?? ''}
                        onChange={v => filtrar({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                )}
                <FieldSelect label="Caja" value={filters.caja_id ?? ''}
                    onChange={v => filtrar({ caja_id: v || undefined })}
                    options={[{ value: '', label: 'Todas' }, ...cajas.map(c => ({ value: String(c.id), label: c.nombre }))]} />
                <FieldSelect label="Cajero" value={filters.user_id ?? ''}
                    onChange={v => filtrar({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]} />
            </FiltrosReporte>

            {/* KPIs */}
            <div className={`grid grid-cols-2 gap-3 mb-5 ${kpis.retiros_total > 0 ? 'lg:grid-cols-3 xl:grid-cols-6' : 'lg:grid-cols-5'}`}>
                <Kpi icon={<ClipboardList size={18} />} label="Turnos" value={fmtInt(kpis.total_turnos)}
                    sub={`${kpis.turnos_abiertos} abiertos · ${kpis.turnos_cerrados} cerrados`}
                    color="var(--color-primary)" />
                <Kpi icon={<TrendingUp size={18} />} label="Vendido en turnos" value={fmtS(kpis.ventas_total)}
                    sub={`${fmtInt(kpis.ventas_count)} ventas completadas`}
                    color="var(--color-success)" />
                <Kpi icon={<TrendingDown size={18} />} label="Gastos desde caja" value={fmtS(kpis.gastos_total)}
                    color="var(--color-danger)" />
                <Kpi icon={<Banknote size={18} />} label="Sobrantes / Faltantes"
                    value={fmtS(kpis.sobrantes)}
                    sub={`faltantes: ${fmtS(kpis.faltantes)}`}
                    subColor={kpis.faltantes > 0 ? 'var(--color-danger)' : undefined}
                    color="var(--color-warning)" />
                <Kpi icon={<Scale size={18} />} label="Diferencia neta"
                    value={fmtS(kpis.diferencia_neta)}
                    sub={kpis.con_descuadre > 0 ? `${kpis.con_descuadre} turnos con descuadre` : 'todo cuadrado'}
                    subColor={kpis.con_descuadre > 0 ? 'var(--color-warning)' : 'var(--color-success)'}
                    color={kpis.diferencia_neta >= 0 ? 'var(--color-success)' : 'var(--color-danger)'} />
                {kpis.retiros_total > 0 && (
                    <Kpi icon={<HandCoins size={18} />} label="Retiros a administración"
                        value={fmtS(kpis.retiros_total)}
                        sub="efectivo entregado desde cajas"
                        color="var(--vp-navy)" />
                )}
            </div>

            {/* Serie + por caja */}
            <div className="grid lg:grid-cols-3 gap-4 mb-4">
                <ReportCard className="lg:col-span-2" icon={<Calendar size={14} />} title="Movimiento por día">
                    <AreaChart
                        data={serieChart}
                        series={[
                            { key: 'ventas', label: 'Vendido', relleno: true },
                            { key: 'gastos', label: 'Gastos', color: '#ef4444' },
                            { key: 'diferencia', label: 'Diferencia de caja', color: '#f59e0b' },
                        ]}
                        height={230}
                    />
                </ReportCard>
                <ReportCard icon={<Landmark size={14} />} title="Por caja" accent="#8b5cf6">
                    <BarList
                        data={por_caja.map(c => ({ label: c.nombre, valor: c.total, extra: `${c.turnos} turnos` }))}
                        multicolor
                    />
                </ReportCard>
            </div>

            {/* Por cajero (accountability) */}
            <ReportCard className="mb-4" icon={<UserRound size={14} />} title="Por cajero" accent="var(--vp-navy)" sinPadding>
                {por_cajero.length === 0 ? <Empty /> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={theadStyle}>
                                    <Th>Cajero</Th><Th right>Turnos</Th><Th right>Vendido</Th><Th right>Descuadre acumulado</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {por_cajero.map((c, i) => (
                                    <tr key={i} style={zebra(i)}>
                                        <td className="px-3 py-2 font-medium" style={{ color: 'var(--color-text)' }}>{c.nombre}</td>
                                        <td className="px-3 py-2 text-right" style={{ color: 'var(--color-text-muted)' }}>{c.turnos}</td>
                                        <td className="px-3 py-2 text-right font-semibold" style={{ color: 'var(--color-text)' }}>{fmtS(c.total)}</td>
                                        <td className="px-3 py-2 text-right">
                                            <span className="font-bold px-1.5 py-0.5 rounded"
                                                style={{
                                                    color: colorDif(c.diferencia),
                                                    backgroundColor: `color-mix(in srgb, ${colorDif(c.diferencia)} 10%, transparent)`,
                                                }}>
                                                {Math.abs(c.diferencia) < 0.01 ? 'Cuadrado' : fmtS(c.diferencia)}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </ReportCard>

            {/* Listado de turnos con detalle expandible */}
            <ReportCard icon={<Wallet size={14} />} title="Detalle de turnos" badge={fmtInt(turnos.total)} sinPadding>
                <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={theadStyle}>
                                <Th className="w-8" />
                                <Th>Apertura</Th><Th>Caja</Th><Th>Cajero</Th><Th>Estado</Th>
                                <Th right>Ventas</Th><Th right>Esperado</Th><Th right>Declarado</Th><Th right>Diferencia</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {turnos.data.map((t, idx) => (
                                <Fragment key={t.id}>
                                    <tr onClick={() => toggle(t.id)}
                                        className="cursor-pointer transition-colors hover:bg-black/[0.03]"
                                        style={zebra(idx)}>
                                        <td className="pl-3 py-2.5" style={{ color: 'var(--color-primary)' }}>
                                            {abiertos.has(t.id) ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                        </td>
                                        <td className="px-3 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                            {fechaHora(t.fecha_apertura)}
                                        </td>
                                        <td className="px-3 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                            {t.caja?.nombre ?? '—'}
                                            {t.local && <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>· {t.local.nombre}</span>}
                                        </td>
                                        <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>{t.user?.name ?? '—'}</td>
                                        <td className="px-3 py-2.5">
                                            <Badge variant={t.estado === 'abierto' ? 'warning' : 'success'}>{t.estado}</Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-xs whitespace-nowrap">
                                            <span className="font-bold" style={{ color: 'var(--color-success)' }}>{fmtS(t.ventas_total)}</span>
                                            <span className="ml-1" style={{ color: 'var(--color-text-muted)' }}>({t.ventas_count})</span>
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                            {t.monto_cierre_esperado !== null ? fmtS(t.monto_cierre_esperado) : '—'}
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-xs whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                            {t.monto_cierre_declarado !== null ? fmtS(t.monto_cierre_declarado) : '—'}
                                        </td>
                                        <td className="px-3 py-2.5 text-right">
                                            {t.diferencia !== null ? (
                                                <span className="text-xs font-bold px-1.5 py-0.5 rounded whitespace-nowrap"
                                                    style={{
                                                        color: colorDif(t.diferencia),
                                                        backgroundColor: `color-mix(in srgb, ${colorDif(t.diferencia)} 10%, transparent)`,
                                                    }}>
                                                    {Math.abs(t.diferencia) < 0.01 ? 'OK' : fmtS(t.diferencia)}
                                                </span>
                                            ) : <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                        </td>
                                    </tr>
                                    {abiertos.has(t.id) && <DetalleTurno turno={t} colSpan={9} />}
                                </Fragment>
                            ))}
                            {turnos.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <Wallet size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron turnos</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 p-3">
                    {turnos.data.map(t => (
                        <div key={t.id} className="rounded-xl p-3"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-bg) 55%, var(--color-surface))',
                                border: '1px solid var(--color-border)',
                            }}
                            onClick={() => toggle(t.id)}>
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                        {t.caja?.nombre ?? '—'} · {t.user?.name ?? '—'}
                                    </p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{fechaHora(t.fecha_apertura)}</p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-success)' }}>
                                        {fmtS(t.ventas_total)} <span style={{ color: 'var(--color-text-muted)' }}>({t.ventas_count} ventas)</span>
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Badge variant={t.estado === 'abierto' ? 'warning' : 'success'}>{t.estado}</Badge>
                                    {t.diferencia !== null && (
                                        <p className="text-xs font-bold mt-1" style={{ color: colorDif(t.diferencia) }}>
                                            {Math.abs(t.diferencia) < 0.01 ? 'Cuadrado' : fmtS(t.diferencia)}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {abiertos.has(t.id) && (
                                <div className="mt-2 pt-2 text-[11px] space-y-1" style={{ borderTop: '1px dashed var(--color-border)' }}>
                                    <p style={{ color: 'var(--color-text)' }}>
                                        Apertura: <b>{fmtS(t.monto_apertura)}</b>
                                        {t.monto_fondos_adicionales > 0.009 && (
                                            <span> (arrastre <b>{fmtS(t.monto_apertura - t.monto_fondos_adicionales)}</b> + adic. <b>{fmtS(t.monto_fondos_adicionales)}</b>)</span>
                                        )}
                                        {' · '}Caja chica: <b>{fmtS(t.monto_caja_chica)}</b>
                                    </p>
                                    {t.monto_cierre_esperado !== null && (
                                        <p style={{ color: 'var(--color-text)' }}>Esperado: <b>{fmtS(t.monto_cierre_esperado)}</b> · Declarado: <b>{fmtS(t.monto_cierre_declarado ?? 0)}</b></p>
                                    )}
                                    {t.gastos_total > 0 && <p style={{ color: 'var(--color-danger)' }}>Gastos: {fmtS(t.gastos_total)}</p>}
                                    {t.retiros_total > 0 && <p style={{ color: 'var(--vp-navy)' }}>Retiros a administración: <b>{fmtS(t.retiros_total)}</b></p>}
                                    {t.destino_efectivo && (t.destino_efectivo === 'administracion'
                                        ? <p style={{ color: 'var(--vp-navy)' }}>Efectivo entregado a administración al cierre</p>
                                        : <p style={{ color: 'var(--color-success)' }}>Quedó en caja: <b>{fmtS(t.efectivo_arrastre ?? 0)}</b></p>)}
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                <Paginacion paginado={turnos} ruta="reportes.caja" filters={filters as unknown as Record<string, unknown>} />
            </ReportCard>
        </AppLayout>
    );
}

/* ── Destino del efectivo al cierre (quedó en caja / entregado a admin) ── */
function DestinoEfectivo({ turno: t }: { turno: TurnoRow }) {
    if (!t.destino_efectivo) return null;
    const entregadoCierre = t.retiros.filter(r => r.momento === 'cierre').reduce((s, r) => s + r.monto, 0);
    return (
        <div className="mt-2 space-y-1 text-[11px]">
            {(t.destino_efectivo === 'caja' || t.destino_efectivo === 'parcial') && (
                <div className="flex items-center justify-between rounded-lg px-2.5 py-1.5"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-success) 9%, transparent)' }}>
                    <span style={{ color: 'var(--color-text)' }}>Quedó en caja para el siguiente turno</span>
                    <span className="font-bold" style={{ color: 'var(--color-success)' }}>
                        {t.efectivo_arrastre !== null ? fmtS(t.efectivo_arrastre) : '—'}
                    </span>
                </div>
            )}
            {(t.destino_efectivo === 'administracion' || t.destino_efectivo === 'parcial') && (
                <div className="flex items-center justify-between rounded-lg px-2.5 py-1.5"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 9%, transparent)' }}>
                    <span style={{ color: 'var(--color-text)' }}>Entregado a administración</span>
                    <span className="font-bold" style={{ color: 'var(--vp-navy)' }}>
                        {entregadoCierre > 0 ? fmtS(entregadoCierre) : ''}
                    </span>
                </div>
            )}
        </div>
    );
}

/* ── Fila expandida: cierre, arqueo por método, gastos y retiros ───────── */
function DetalleTurno({ turno: t, colSpan }: { turno: TurnoRow; colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-3"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 4%, var(--color-surface))',
                    borderTop: '1px dashed var(--color-border)',
                }}>
                <div className={`grid gap-4 ${t.retiros.length > 0 ? 'md:grid-cols-2 xl:grid-cols-4' : 'md:grid-cols-3'}`}>
                    {/* Apertura / cierre */}
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>Turno</p>
                        <div className="space-y-1 text-[11px]" style={{ color: 'var(--color-text)' }}>
                            <p>Abrió: <b>{t.user?.name ?? '—'}</b> · {fechaHora(t.fecha_apertura)}</p>
                            {t.fecha_cierre && (
                                <p>Cerró: <b>{t.user_cierre?.name ?? t.user?.name ?? '—'}</b> · {fechaHora(t.fecha_cierre)}</p>
                            )}
                            <p>Monto apertura: <b>{fmtS(t.monto_apertura)}</b></p>
                            {t.monto_fondos_adicionales > 0.009 && (
                                <p>Fondos adicionales: <b>{fmtS(t.monto_fondos_adicionales)}</b> · Arrastre: <b>{fmtS(t.monto_apertura - t.monto_fondos_adicionales)}</b></p>
                            )}
                            {t.monto_caja_chica > 0 && <p>Caja chica: <b>{fmtS(t.monto_caja_chica)}</b></p>}
                            {t.observacion_apertura && (
                                <p className="italic" style={{ color: 'var(--color-text-muted)' }}>“{t.observacion_apertura}”</p>
                            )}
                        </div>
                        {t.estado === 'cerrado' && (
                            <div className="mt-2 rounded-lg px-2.5 py-2 text-[11px] space-y-0.5"
                                style={{ backgroundColor: `color-mix(in srgb, ${colorDif(t.diferencia)} 8%, transparent)`, color: 'var(--color-text)' }}>
                                <div className="flex justify-between"><span>Esperado</span><b>{t.monto_cierre_esperado !== null ? fmtS(t.monto_cierre_esperado) : '—'}</b></div>
                                <div className="flex justify-between"><span>Declarado</span><b>{t.monto_cierre_declarado !== null ? fmtS(t.monto_cierre_declarado) : '—'}</b></div>
                                <div className="flex justify-between font-bold" style={{ color: colorDif(t.diferencia) }}>
                                    <span>Diferencia</span>
                                    <span>{t.diferencia !== null ? fmtS(t.diferencia) : '—'}</span>
                                </div>
                                {t.observacion_cierre && (
                                    <p className="italic pt-1" style={{ color: 'var(--color-text-muted)' }}>“{t.observacion_cierre}”</p>
                                )}
                            </div>
                        )}
                        <DestinoEfectivo turno={t} />
                    </div>

                    {/* Arqueo por método */}
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                            Declarado por método
                        </p>
                        {t.arqueo_metodos.length === 0 ? (
                            <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                {t.estado === 'abierto' ? 'Turno aún abierto' : 'Sin declaración por método'}
                            </p>
                        ) : (
                            <div className="space-y-1">
                                {t.arqueo_metodos.map(m => (
                                    <div key={m.id} className="flex items-center justify-between text-[11px] rounded-lg px-2 py-1.5"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 7%, transparent)' }}>
                                        <span style={{ color: 'var(--color-text)' }}>{m.nombre}</span>
                                        <span className="font-bold" style={{ color: 'var(--color-primary)' }}>{fmtS(m.monto_declarado)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Gastos del turno */}
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                            Gastos del turno {t.gastos_total > 0 && <span style={{ color: 'var(--color-danger)' }}>({fmtS(t.gastos_total)})</span>}
                        </p>
                        {t.gastos.length === 0 ? (
                            <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>Sin gastos</p>
                        ) : (
                            <div className="space-y-1">
                                {t.gastos.map(g => (
                                    <div key={g.id} className="flex items-center justify-between text-[11px] rounded-lg px-2 py-1.5"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 7%, transparent)' }}>
                                        <span style={{ color: 'var(--color-text)' }}>{g.concepto}</span>
                                        <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{fmtS(g.monto)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Retiros de efectivo (sangrías / entrega a administración) */}
                    {t.retiros.length > 0 && (
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-wider mb-1.5" style={{ color: 'var(--vp-navy)' }}>
                                Retiros de efectivo <span style={{ color: 'var(--vp-navy)' }}>({fmtS(t.retiros_total)})</span>
                            </p>
                            <div className="space-y-1">
                                {t.retiros.map(r => (
                                    <div key={r.id} className="text-[11px] rounded-lg px-2 py-1.5"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 7%, transparent)' }}>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{r.concepto}</span>
                                            <span className="font-bold whitespace-nowrap" style={{ color: 'var(--vp-navy)' }}>{fmtS(r.monto)}</span>
                                        </div>
                                        <div className="flex items-center gap-1.5 mt-0.5">
                                            <span style={{ color: 'var(--color-text-muted)' }}>{r.usuario}</span>
                                            {r.momento === 'cierre' && (
                                                <span className="text-[9px] font-bold px-1.5 py-px rounded-full"
                                                    style={{
                                                        color: 'var(--color-primary)',
                                                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                                    }}>
                                                    al cierre
                                                </span>
                                            )}
                                            {r.estado === 'registrado' && (
                                                <span className="text-[9px] font-bold px-1.5 py-px rounded-full"
                                                    style={{
                                                        color: 'var(--color-warning)',
                                                        backgroundColor: 'color-mix(in srgb, var(--color-warning) 14%, transparent)',
                                                    }}>
                                                    sin aprobar
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </td>
        </tr>
    );
}
