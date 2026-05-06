import { useEffect, useMemo } from 'react';
import { router, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Filter, ShoppingCart, TrendingUp, Receipt, Percent, CreditCard,
    Package, Calendar, X,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import type { Local, MetodoPago, User, Venta, PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface Kpis {
    total_ventas:    number;
    total_anuladas:  number;
    monto_total:     number;
    monto_subtotal:  number;
    monto_descuento: number;
    monto_igv:       number;
    ticket_promedio: number;
}

interface PorMetodo {
    metodo_pago_id: number;
    nombre:         string;
    tipo:           string;
    total:          number;
    ocurrencias:    number;
}

interface TopProducto {
    producto_id:     number;
    producto_nombre: string;
    cantidad:        number;
    total:           number;
}

interface SerieDia {
    dia:    string;
    total:  number;
    ventas: number;
}

interface Filters {
    fecha_desde:     string;
    fecha_hasta:     string;
    estado?:         string;
    local_id?:       string;
    user_id?:        string;
    metodo_pago_id?: string;
}

interface Props extends PageProps {
    ventas:        Paginado<Venta>;
    kpis:          Kpis;
    por_metodo:    PorMetodo[];
    top_productos: TopProducto[];
    serie_diaria:  SerieDia[];
    locales:       Local[];
    usuarios:      Pick<User, 'id' | 'name'>[];
    metodos_pago:  MetodoPago[];
    filters:       Filters;
}

const fmt = (n: number) => 'S/ ' + n.toFixed(2);
const fmtInt = (n: number) => n.toLocaleString('es-PE');

export default function ReportesVentas({
    ventas, kpis, por_metodo, top_productos, serie_diaria,
    locales, usuarios, metodos_pago, filters, flash,
}: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.ventas'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    function limpiar() {
        router.get(route('reportes.ventas'), {}, { preserveState: true, replace: true });
    }

    const tieneFiltros =
        !!(filters.estado || filters.local_id || filters.user_id || filters.metodo_pago_id);

    const maxSerie = useMemo(
        () => Math.max(1, ...serie_diaria.map(s => s.total)),
        [serie_diaria]
    );
    const maxMetodo = useMemo(
        () => Math.max(1, ...por_metodo.map(s => s.total)),
        [por_metodo]
    );

    return (
        <AppLayout title="Reporte de ventas">
            <PageHeader
                title="Reporte de ventas"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${kpis.total_ventas} ventas completadas`}
            />

            {/* KPIs */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <Kpi
                    icon={<TrendingUp size={18} />}
                    label="Total vendido"
                    value={fmt(kpis.monto_total)}
                    color="success"
                />
                <Kpi
                    icon={<ShoppingCart size={18} />}
                    label="Ventas"
                    value={fmtInt(kpis.total_ventas)}
                    sub={kpis.total_anuladas > 0 ? `${kpis.total_anuladas} anuladas` : undefined}
                    color="primary"
                />
                <Kpi
                    icon={<Receipt size={18} />}
                    label="Ticket promedio"
                    value={fmt(kpis.ticket_promedio)}
                    color="primary"
                />
                <Kpi
                    icon={<Percent size={18} />}
                    label="Descuentos"
                    value={fmt(kpis.monto_descuento)}
                    sub={`IGV: ${fmt(kpis.monto_igv)}`}
                    color="danger"
                />
            </div>

            {/* Filtros */}
            <FiltersBox
                filters={filters}
                tieneFiltros={tieneFiltros}
                onChange={filtrar}
                onClear={limpiar}
                locales={locales}
                usuarios={usuarios}
                metodosPago={metodos_pago}
            />

            {/* Gráficos resumen */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
                {/* Serie diaria */}
                <Card className="lg:col-span-2">
                    <CardTitle icon={<Calendar size={14} />} title="Ventas por día" />
                    {serie_diaria.length === 0 ? (
                        <Empty text="Sin datos en el rango" />
                    ) : (
                        <div className="flex items-end gap-1 h-32 mt-2">
                            {serie_diaria.map(s => (
                                <div key={s.dia} className="flex-1 flex flex-col items-center gap-1 group">
                                    <div className="w-full relative" style={{ height: '100%' }}>
                                        <div
                                            className="absolute bottom-0 left-0 right-0 rounded-t transition-all"
                                            style={{
                                                height: `${(s.total / maxSerie) * 100}%`,
                                                backgroundColor: 'var(--color-primary)',
                                                opacity: 0.85,
                                            }}
                                            title={`${s.dia}: ${fmt(s.total)} (${s.ventas})`}
                                        />
                                    </div>
                                    <span
                                        className="text-[9px] truncate w-full text-center"
                                        style={{ color: 'var(--color-text-muted)' }}
                                    >
                                        {s.dia.slice(5)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                {/* Métodos de pago */}
                <Card>
                    <CardTitle icon={<CreditCard size={14} />} title="Por método de pago" />
                    {por_metodo.length === 0 ? (
                        <Empty text="Sin pagos" />
                    ) : (
                        <div className="space-y-2 mt-2">
                            {por_metodo.map(m => (
                                <div key={m.metodo_pago_id}>
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span style={{ color: 'var(--color-text)' }}>{m.nombre}</span>
                                        <span className="font-semibold" style={{ color: 'var(--color-text)' }}>
                                            {fmt(m.total)}
                                        </span>
                                    </div>
                                    <div
                                        className="h-2 rounded-full overflow-hidden"
                                        style={{ backgroundColor: 'var(--color-bg)' }}
                                    >
                                        <div
                                            className="h-full rounded-full"
                                            style={{
                                                width: `${(m.total / maxMetodo) * 100}%`,
                                                backgroundColor: 'var(--color-primary)',
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Top productos */}
            <Card className="mb-4">
                <CardTitle icon={<Package size={14} />} title="Top productos del rango" />
                {top_productos.length === 0 ? (
                    <Empty text="Sin productos vendidos" />
                ) : (
                    <div className="overflow-x-auto -mx-3">
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                    <th className="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>#</th>
                                    <th className="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Producto</th>
                                    <th className="px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Cantidad</th>
                                    <th className="px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {top_productos.map((p, i) => (
                                    <tr key={p.producto_id} style={{ borderBottom: '1px solid var(--color-border)' }}>
                                        <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{i + 1}</td>
                                        <td className="px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</td>
                                        <td className="px-3 py-2 text-xs text-right" style={{ color: 'var(--color-text)' }}>{p.cantidad.toFixed(2)}</td>
                                        <td className="px-3 py-2 text-xs text-right font-semibold" style={{ color: 'var(--color-success)' }}>{fmt(p.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Listado ventas */}
            <Card>
                <CardTitle icon={<Receipt size={14} />} title={`Ventas (${ventas.total})`} />
                <div
                    className="hidden md:block rounded-xl overflow-hidden mt-2"
                    style={{ border: '1px solid var(--color-border)' }}
                >
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                                {['Fecha', 'Número', 'Cliente', 'Vendedor', 'Local', 'Estado', 'Total'].map(h => (
                                    <th key={h} className="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {ventas.data.map((v, idx) => (
                                <tr
                                    key={v.id}
                                    className="transition-colors hover:bg-black/[0.02]"
                                    style={{ borderBottom: idx < ventas.data.length - 1 ? '1px solid var(--color-border)' : undefined }}
                                >
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {new Date(v.fecha_venta).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Link
                                            href={route('ventas.show', v.id)}
                                            className="font-mono text-xs font-medium hover:underline"
                                            style={{ color: 'var(--color-primary)' }}
                                        >
                                            {v.numero}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {(v.cliente as any)?.razon_social
                                            ?? (v.cliente ? `${(v.cliente as any).nombres} ${(v.cliente as any).apellidos ?? ''}` : '—')}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text)' }}>
                                        {(v.user as any)?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        {(v.local as any)?.nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                                            {v.estado}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-bold text-sm" style={{ color: v.estado === 'anulada' ? 'var(--color-text-muted)' : 'var(--color-success)' }}>
                                        {fmt(parseFloat(v.total))}
                                    </td>
                                </tr>
                            ))}
                            {ventas.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                        <ShoppingCart size={36} className="mx-auto mb-2 opacity-20" />
                                        <p className="text-sm">No se encontraron ventas</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Cards móvil */}
                <div className="md:hidden flex flex-col gap-2 mt-2">
                    {ventas.data.map(v => (
                        <Link
                            key={v.id}
                            href={route('ventas.show', v.id)}
                            className="rounded-xl p-3 block"
                            style={{
                                backgroundColor: 'var(--color-surface)',
                                border: '1px solid var(--color-border)',
                            }}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="font-mono text-sm font-semibold" style={{ color: 'var(--color-primary)' }}>
                                        {v.numero}
                                    </p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        {new Date(v.fecha_venta).toLocaleString('es-PE')}
                                    </p>
                                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text)' }}>
                                        {(v.user as any)?.name ?? '—'}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-sm" style={{ color: v.estado === 'anulada' ? 'var(--color-text-muted)' : 'var(--color-success)' }}>
                                        {fmt(parseFloat(v.total))}
                                    </p>
                                    <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>{v.estado}</Badge>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>

                <Pagination paginado={ventas} ruta="reportes.ventas" filters={filters} />
            </Card>
        </AppLayout>
    );
}

// ── Helpers ────────────────────────────────────────────────────────────────

function Kpi({ icon, label, value, sub, color }: {
    icon: React.ReactNode; label: string; value: string; sub?: string;
    color: 'primary' | 'success' | 'danger' | 'warning';
}) {
    return (
        <div
            className="rounded-xl p-3 flex items-center gap-3"
            style={{
                backgroundColor: 'var(--color-surface)',
                border: '1px solid var(--color-border)',
            }}
        >
            <div
                className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                style={{
                    backgroundColor: `color-mix(in srgb, var(--color-${color}) 10%, transparent)`,
                    color: `var(--color-${color})`,
                }}
            >
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-medium uppercase tracking-wide truncate" style={{ color: 'var(--color-text-muted)' }}>
                    {label}
                </p>
                <p className="text-base font-bold truncate" style={{ color: 'var(--color-text)' }}>
                    {value}
                </p>
                {sub && (
                    <p className="text-[10px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                        {sub}
                    </p>
                )}
            </div>
        </div>
    );
}

function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div
            className={`rounded-xl p-3 sm:p-4 ${className}`}
            style={{
                backgroundColor: 'var(--color-surface)',
                border: '1px solid var(--color-border)',
            }}
        >
            {children}
        </div>
    );
}

function CardTitle({ icon, title }: { icon: React.ReactNode; title: string }) {
    return (
        <div className="flex items-center gap-2 mb-1">
            <span style={{ color: 'var(--color-text-muted)' }}>{icon}</span>
            <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                {title}
            </span>
        </div>
    );
}

function Empty({ text }: { text: string }) {
    return (
        <p className="text-center py-8 text-xs" style={{ color: 'var(--color-text-muted)' }}>
            {text}
        </p>
    );
}

function FiltersBox({
    filters, tieneFiltros, onChange, onClear, locales, usuarios, metodosPago,
}: {
    filters: Filters;
    tieneFiltros: boolean;
    onChange: (p: Partial<Filters>) => void;
    onClear: () => void;
    locales: Local[];
    usuarios: Pick<User, 'id' | 'name'>[];
    metodosPago: MetodoPago[];
}) {
    const ringStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    return (
        <div
            className="rounded-xl p-3 sm:p-4 mb-4"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center gap-2 mb-3">
                <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                    Filtros
                </span>
                {tieneFiltros && (
                    <button
                        onClick={onClear}
                        className="ml-auto inline-flex items-center gap-1 text-xs font-medium hover:underline"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        <X size={12} /> Limpiar
                    </button>
                )}
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                <FieldDate label="Desde" value={filters.fecha_desde} onChange={v => onChange({ fecha_desde: v })} style={ringStyle} />
                <FieldDate label="Hasta" value={filters.fecha_hasta} onChange={v => onChange({ fecha_hasta: v })} style={ringStyle} />
                <FieldSelect
                    label="Estado"
                    value={filters.estado ?? ''}
                    onChange={v => onChange({ estado: v || undefined })}
                    options={[
                        { value: '', label: 'Todos' },
                        { value: 'completada', label: 'Completadas' },
                        { value: 'anulada', label: 'Anuladas' },
                    ]}
                    style={ringStyle}
                />
                {locales.length > 1 && (
                    <FieldSelect
                        label="Local"
                        value={filters.local_id ?? ''}
                        onChange={v => onChange({ local_id: v || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]}
                        style={ringStyle}
                    />
                )}
                <FieldSelect
                    label="Vendedor"
                    value={filters.user_id ?? ''}
                    onChange={v => onChange({ user_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...usuarios.map(u => ({ value: String(u.id), label: u.name }))]}
                    style={ringStyle}
                />
                <FieldSelect
                    label="Método pago"
                    value={filters.metodo_pago_id ?? ''}
                    onChange={v => onChange({ metodo_pago_id: v || undefined })}
                    options={[{ value: '', label: 'Todos' }, ...metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))]}
                    style={ringStyle}
                />
            </div>
        </div>
    );
}

function FieldDate({ label, value, onChange, style }: { label: string; value: string; onChange: (v: string) => void; style: React.CSSProperties }) {
    return (
        <div>
            <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            <input
                type="date"
                value={value}
                onChange={e => onChange(e.target.value)}
                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2"
                style={style}
            />
        </div>
    );
}

function FieldSelect({ label, value, onChange, options, style }: {
    label: string; value: string; onChange: (v: string) => void;
    options: { value: string; label: string }[]; style: React.CSSProperties;
}) {
    return (
        <div>
            <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            <select
                value={value}
                onChange={e => onChange(e.target.value)}
                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2"
                style={style}
            >
                {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
        </div>
    );
}

function Pagination<T>({ paginado, ruta, filters }: { paginado: Paginado<T>; ruta: string; filters: Record<string, unknown> }) {
    if (paginado.last_page <= 1) return null;
    return (
        <div className="flex items-center justify-center gap-1 mt-4">
            {Array.from({ length: paginado.last_page }, (_, i) => i + 1).map(page => (
                <button
                    key={page}
                    onClick={() => router.get(route(ruta), { ...filters, page }, { preserveState: true })}
                    className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                    style={{
                        backgroundColor: page === paginado.current_page ? 'var(--color-primary)' : 'transparent',
                        color: page === paginado.current_page ? '#fff' : 'var(--color-text-muted)',
                    }}
                >
                    {page}
                </button>
            ))}
        </div>
    );
}
