import { Link } from '@inertiajs/react';
import {
    AlertTriangle, ArrowRight, ArrowUpRight, Banknote, ClipboardList,
    Coins, CreditCard, Package, ShoppingCart, TrendingUp, Undo2, Users,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import type { PageProps } from '@/types';

interface KpiBlock { cant: number; total: number; }
interface Kpis {
    ventas_hoy: KpiBlock;
    ventas_mes: KpiBlock;
    devoluciones_mes: KpiBlock;
    gastos_mes: number;
    stock_valorizado: number;
    diferencia_caja_mes: number;
}
interface StockBajoRow { id: number; nombre: string; codigo: string | null; cantidad: string; almacen_id: number; }
interface TopProductoRow { producto_id: number; nombre: string; cantidad: string; total: string; }
interface MetodoRow { nombre: string; tipo: string; total: string; }
interface TurnoAbiertoRow {
    id: number; fecha_apertura: string;
    user: { id: number; name: string };
    caja: { id: number; nombre: string };
    local: { id: number; nombre: string };
}
interface UltimaVentaRow {
    id: number; numero: string; total: string; fecha_venta: string; tipo_comprobante: string;
    user: { id: number; name: string };
    cliente: { id: number; nombres: string | null; apellidos: string | null; razon_social: string | null } | null;
}

interface Props extends PageProps {
    kpis: Kpis;
    stockBajo: StockBajoRow[];
    topProductos: TopProductoRow[];
    ventasPorMetodo: MetodoRow[];
    turnosAbiertos: TurnoAbiertoRow[];
    ultimasVentas: UltimaVentaRow[];
}

const sol = (n: number | string) =>
    `S/. ${Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const fechaCorta = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

function nombreCliente(c: UltimaVentaRow['cliente']): string {
    if (!c) return 'Cliente general';
    if (c.razon_social) return c.razon_social;
    return [c.nombres, c.apellidos].filter(Boolean).join(' ') || 'Cliente';
}

interface KpiCardProps {
    label: string;
    value: string;
    sub?: string;
    icon: React.ReactNode;
    color: string;
}
function KpiCard({ label, value, sub, icon, color }: KpiCardProps) {
    return (
        <div
            className="rounded-lg border p-5"
            style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
        >
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                    <p className="mt-1.5 text-2xl font-bold" style={{ color: 'var(--color-text)' }}>{value}</p>
                    {sub && <p className="mt-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>{sub}</p>}
                </div>
                <div
                    className="flex h-10 w-10 items-center justify-center rounded-lg"
                    style={{ backgroundColor: color + '18', color }}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}

export default function DashboardAdmin({
    kpis, stockBajo, topProductos, ventasPorMetodo, turnosAbiertos, ultimasVentas,
}: Props) {
    const maxTopTotal = Math.max(1, ...topProductos.map(p => Number(p.total)));
    const totalMetodos = ventasPorMetodo.reduce((acc, m) => acc + Number(m.total), 0);

    return (
        <AppLayout title="Dashboard">
            <div className="mb-6">
                <h2 className="text-lg font-semibold" style={{ color: 'var(--color-text)' }}>Panel administrativo</h2>
                <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                    Resumen del mes y operaciones recientes
                </p>
            </div>

            {/* KPIs */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <KpiCard
                    label="Ventas hoy"
                    value={sol(kpis.ventas_hoy.total)}
                    sub={`${kpis.ventas_hoy.cant} comprobantes`}
                    icon={<ShoppingCart size={20} />}
                    color="var(--color-primary)"
                />
                <KpiCard
                    label="Ventas del mes"
                    value={sol(kpis.ventas_mes.total)}
                    sub={`${kpis.ventas_mes.cant} comprobantes`}
                    icon={<TrendingUp size={20} />}
                    color="var(--color-success)"
                />
                <KpiCard
                    label="Stock valorizado"
                    value={sol(kpis.stock_valorizado)}
                    sub="Costo promedio × cantidad"
                    icon={<Package size={20} />}
                    color="var(--color-secondary)"
                />
                <KpiCard
                    label="Devoluciones del mes"
                    value={sol(kpis.devoluciones_mes.total)}
                    sub={`${kpis.devoluciones_mes.cant} devoluciones`}
                    icon={<Undo2 size={20} />}
                    color="var(--color-warning)"
                />
                <KpiCard
                    label="Gastos del mes"
                    value={sol(kpis.gastos_mes)}
                    sub="Total operativo"
                    icon={<Banknote size={20} />}
                    color="#f97316"
                />
                <KpiCard
                    label="Diferencia de caja del mes"
                    value={sol(kpis.diferencia_caja_mes)}
                    sub={kpis.diferencia_caja_mes < 0 ? 'Faltante acumulado' : kpis.diferencia_caja_mes > 0 ? 'Sobrante acumulado' : 'Sin diferencias'}
                    icon={<Coins size={20} />}
                    color={kpis.diferencia_caja_mes < 0 ? 'var(--color-danger, #dc2626)' : 'var(--color-success)'}
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Top productos */}
                <div
                    className="lg:col-span-2 rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold" style={{ color: 'var(--color-text)' }}>
                            Top productos del mes
                        </h3>
                        <Link
                            href={route('reportes.productos')}
                            className="text-xs font-medium inline-flex items-center gap-1 hover:underline"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            Ver reporte <ArrowRight size={12} />
                        </Link>
                    </div>
                    {topProductos.length === 0 ? (
                        <p className="text-sm text-center py-8" style={{ color: 'var(--color-text-muted)' }}>
                            Aún no hay ventas este mes.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {topProductos.map(p => {
                                const pct = (Number(p.total) / maxTopTotal) * 100;
                                return (
                                    <div key={p.producto_id}>
                                        <div className="flex justify-between text-sm mb-1">
                                            <span className="font-medium truncate pr-3" style={{ color: 'var(--color-text)' }}>
                                                {p.nombre}
                                            </span>
                                            <span className="font-semibold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                                {sol(p.total)}
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-border)' }}>
                                            <div
                                                className="h-full rounded-full transition-all"
                                                style={{ width: `${pct}%`, backgroundColor: 'var(--color-primary)' }}
                                            />
                                        </div>
                                        <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                            {Number(p.cantidad).toFixed(0)} unidades
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Ventas por método */}
                <div
                    className="rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <h3 className="font-semibold mb-4" style={{ color: 'var(--color-text)' }}>
                        Ventas por método (mes)
                    </h3>
                    {ventasPorMetodo.length === 0 ? (
                        <p className="text-sm text-center py-8" style={{ color: 'var(--color-text-muted)' }}>
                            Sin datos.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {ventasPorMetodo.map(m => {
                                const pct = totalMetodos > 0 ? (Number(m.total) / totalMetodos) * 100 : 0;
                                return (
                                    <div key={m.nombre}>
                                        <div className="flex items-center gap-2 text-sm mb-1">
                                            <CreditCard size={14} style={{ color: 'var(--color-text-muted)' }} />
                                            <span className="flex-1 font-medium" style={{ color: 'var(--color-text)' }}>{m.nombre}</span>
                                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {pct.toFixed(0)}%
                                            </span>
                                        </div>
                                        <div className="flex items-baseline gap-2">
                                            <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>{sol(m.total)}</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                {/* Turnos abiertos */}
                <div
                    className="rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold" style={{ color: 'var(--color-text)' }}>
                            Turnos abiertos
                        </h3>
                        <Users size={16} style={{ color: 'var(--color-text-muted)' }} />
                    </div>
                    {turnosAbiertos.length === 0 ? (
                        <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>
                            Ningún turno abierto ahora.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {turnosAbiertos.map(t => (
                                <Link
                                    key={t.id}
                                    href={route('turnos.show', t.id)}
                                    className="block rounded-md border p-3 hover:opacity-90 transition-opacity"
                                    style={{ borderColor: 'var(--color-border)' }}
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                                {t.user.name}
                                            </p>
                                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {t.local.nombre} · {t.caja.nombre}
                                            </p>
                                        </div>
                                        <ArrowUpRight size={14} style={{ color: 'var(--color-text-muted)' }} />
                                    </div>
                                    <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                        Desde {fechaCorta(t.fecha_apertura)}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {/* Stock bajo */}
                <div
                    className="rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold" style={{ color: 'var(--color-text)' }}>
                            Alertas de stock
                        </h3>
                        <AlertTriangle size={16} style={{ color: 'var(--color-warning)' }} />
                    </div>
                    {stockBajo.length === 0 ? (
                        <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>
                            Stock saludable.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {stockBajo.map((s, idx) => (
                                <li key={`${s.id}-${s.almacen_id}-${idx}`} className="flex items-center justify-between text-sm">
                                    <span className="truncate pr-2" style={{ color: 'var(--color-text)' }}>{s.nombre}</span>
                                    <span
                                        className="text-xs font-semibold rounded px-1.5 py-0.5 whitespace-nowrap"
                                        style={{
                                            backgroundColor: Number(s.cantidad) <= 0 ? 'var(--color-danger, #dc2626)18' : 'var(--color-warning)18',
                                            color: Number(s.cantidad) <= 0 ? 'var(--color-danger, #dc2626)' : 'var(--color-warning)',
                                        }}
                                    >
                                        {Number(s.cantidad).toFixed(0)} u
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Link
                        href={route('inventario.stock.index')}
                        className="mt-4 inline-flex items-center gap-1 text-xs font-medium hover:underline"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        Ver inventario completo <ArrowRight size={12} />
                    </Link>
                </div>

                {/* Últimas ventas */}
                <div
                    className="rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold" style={{ color: 'var(--color-text)' }}>
                            Últimas ventas
                        </h3>
                        <ClipboardList size={16} style={{ color: 'var(--color-text-muted)' }} />
                    </div>
                    {ultimasVentas.length === 0 ? (
                        <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>
                            Sin movimientos.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {ultimasVentas.map(v => (
                                <li key={v.id}>
                                    <Link
                                        href={route('ventas.show', v.id)}
                                        className="flex items-center justify-between text-sm py-1 hover:opacity-80"
                                    >
                                        <div className="min-w-0 pr-2">
                                            <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                                {v.numero}
                                            </p>
                                            <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                {nombreCliente(v.cliente)} · {v.user.name}
                                            </p>
                                        </div>
                                        <span className="font-semibold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                            {sol(v.total)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Link
                        href={route('ventas.index')}
                        className="mt-4 inline-flex items-center gap-1 text-xs font-medium hover:underline"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        Ver todas las ventas <ArrowRight size={12} />
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
