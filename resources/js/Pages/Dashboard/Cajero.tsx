import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    ArrowRight, Banknote, ClipboardList, Clock, CreditCard, DoorOpen,
    PlayCircle, Receipt, ShoppingCart, Undo2, Wallet,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import type { PageProps } from '@/types';

interface MetodoRow { nombre: string; tipo: string; total: string; }
interface TurnoStats {
    ventas_cant:        number;
    ventas_total:       number;
    gastos_total:       number;
    devoluciones_cant:  number;
    devoluciones_total: number;
    monto_esperado:     number;
    por_metodo:         MetodoRow[];
}
interface TurnoActivo {
    id: number;
    fecha_apertura: string;
    monto_apertura: string;
    monto_caja_chica: string;
    caja: { id: number; nombre: string };
    local: { id: number; nombre: string };
}
interface UltimaVentaRow {
    id: number; numero: string; total: string; fecha_venta: string; tipo_comprobante: string;
    cliente: { id: number; nombres: string | null; apellidos: string | null; razon_social: string | null } | null;
}

interface Props extends PageProps {
    turnoActivo:   TurnoActivo | null;
    turnoStats:    TurnoStats | null;
    misVentasHoy:  { cant: number; total: number };
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

/** Cronómetro vivo desde la apertura del turno */
function useTurnoTimer(fechaApertura: string | undefined): string {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!fechaApertura) return;
        const id = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, [fechaApertura]);

    if (!fechaApertura) return '—';
    const diff = Math.max(0, Math.floor((now - new Date(fechaApertura).getTime()) / 1000));
    const h = String(Math.floor(diff / 3600)).padStart(2, '0');
    const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
    const s = String(diff % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

interface MiniCardProps { label: string; value: string; icon: React.ReactNode; color: string; }
function MiniCard({ label, value, icon, color }: MiniCardProps) {
    return (
        <div
            className="rounded-lg border p-4"
            style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
        >
            <div className="flex items-center gap-3">
                <div
                    className="flex h-10 w-10 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: color + '18', color }}
                >
                    {icon}
                </div>
                <div className="min-w-0">
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                    <p className="text-lg font-bold truncate" style={{ color: 'var(--color-text)' }}>{value}</p>
                </div>
            </div>
        </div>
    );
}

export default function DashboardCajero({
    auth, turnoActivo, turnoStats, misVentasHoy, ultimasVentas,
}: Props) {
    const tiempo = useTurnoTimer(turnoActivo?.fecha_apertura);

    return (
        <AppLayout title="Dashboard">
            <div className="mb-6">
                <h2 className="text-lg font-semibold" style={{ color: 'var(--color-text)' }}>
                    Hola, {auth.user.name.split(' ')[0]}
                </h2>
                <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                    {turnoActivo ? 'Tu turno está abierto' : 'No tienes ningún turno abierto'}
                </p>
            </div>

            {/* Estado del turno */}
            {turnoActivo && turnoStats ? (
                <div
                    className="rounded-lg border overflow-hidden mb-6"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <div
                        className="px-5 py-4 border-b flex items-center justify-between"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-primary)18' }}
                    >
                        <div>
                            <p className="text-xs uppercase tracking-wider font-semibold" style={{ color: 'var(--color-primary)' }}>
                                Turno activo
                            </p>
                            <p className="font-semibold mt-0.5" style={{ color: 'var(--color-text)' }}>
                                {turnoActivo.local.nombre} · {turnoActivo.caja.nombre}
                            </p>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                Abierto el {fechaCorta(turnoActivo.fecha_apertura)}
                            </p>
                        </div>
                        <div className="text-right">
                            <div className="flex items-center gap-2 justify-end">
                                <Clock size={16} style={{ color: 'var(--color-primary)' }} />
                                <span className="text-2xl font-bold tabular-nums" style={{ color: 'var(--color-text)' }}>
                                    {tiempo}
                                </span>
                            </div>
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>tiempo en turno</p>
                        </div>
                    </div>

                    <div className="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <MiniCard
                            label="Ventas del turno"
                            value={`${turnoStats.ventas_cant}`}
                            icon={<Receipt size={20} />}
                            color="var(--color-primary)"
                        />
                        <MiniCard
                            label="Total vendido"
                            value={sol(turnoStats.ventas_total)}
                            icon={<ShoppingCart size={20} />}
                            color="var(--color-success)"
                        />
                        <MiniCard
                            label="Monto esperado en caja"
                            value={sol(turnoStats.monto_esperado)}
                            icon={<Wallet size={20} />}
                            color="var(--color-secondary)"
                        />
                        <MiniCard
                            label="Gastos del turno"
                            value={sol(turnoStats.gastos_total)}
                            icon={<Banknote size={20} />}
                            color="#f97316"
                        />
                    </div>

                    {turnoStats.devoluciones_cant > 0 && (
                        <div className="px-5 pb-3">
                            <div
                                className="rounded-md border px-3 py-2 flex items-center gap-2 text-sm"
                                style={{ borderColor: 'var(--color-border)' }}
                            >
                                <Undo2 size={14} style={{ color: 'var(--color-warning)' }} />
                                <span style={{ color: 'var(--color-text)' }}>
                                    <strong>{turnoStats.devoluciones_cant}</strong> devolución(es) por {sol(turnoStats.devoluciones_total)}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="px-5 pb-5 flex flex-col sm:flex-row gap-3">
                        <Link
                            href={route('pos.index')}
                            className="flex-1 inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-90"
                            style={{ backgroundColor: 'var(--color-primary)' }}
                        >
                            <PlayCircle size={18} />
                            Ir al POS
                        </Link>
                        <Link
                            href={route('turnos.cerrar.page', turnoActivo.id)}
                            className="inline-flex items-center justify-center gap-2 rounded-md border px-4 py-2.5 text-sm font-medium transition hover:bg-gray-50"
                            style={{
                                borderColor: 'var(--color-border)',
                                color: 'var(--color-text)',
                                backgroundColor: 'var(--color-surface)',
                            }}
                        >
                            <DoorOpen size={18} />
                            Cerrar turno
                        </Link>
                    </div>

                    {/* Ventas por método del turno */}
                    {turnoStats.por_metodo.length > 0 && (
                        <div
                            className="px-5 pb-5 pt-4 border-t"
                            style={{ borderColor: 'var(--color-border)' }}
                        >
                            <p className="text-xs font-semibold uppercase tracking-wider mb-3" style={{ color: 'var(--color-text-muted)' }}>
                                Recaudado por método
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {turnoStats.por_metodo.map(m => (
                                    <div
                                        key={m.nombre}
                                        className="rounded-md border px-3 py-2"
                                        style={{ borderColor: 'var(--color-border)' }}
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <CreditCard size={12} style={{ color: 'var(--color-text-muted)' }} />
                                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{m.nombre}</span>
                                        </div>
                                        <p className="text-sm font-semibold mt-0.5" style={{ color: 'var(--color-text)' }}>
                                            {sol(m.total)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <div
                    className="rounded-lg border p-8 text-center mb-6"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <DoorOpen size={48} className="mx-auto mb-3" style={{ color: 'var(--color-text-muted)' }} />
                    <p className="font-semibold mb-1" style={{ color: 'var(--color-text)' }}>
                        No tienes ningún turno abierto
                    </p>
                    <p className="text-sm mb-4" style={{ color: 'var(--color-text-muted)' }}>
                        Abre un turno para empezar a registrar ventas en el POS.
                    </p>
                    <Link
                        href={route('turnos.index')}
                        className="inline-flex items-center justify-center gap-2 rounded-md px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-90"
                        style={{ backgroundColor: 'var(--color-primary)' }}
                    >
                        <PlayCircle size={18} />
                        Abrir turno
                    </Link>
                </div>
            )}

            {/* Mis ventas del día + Últimas ventas */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div
                    className="rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <p className="text-sm font-medium" style={{ color: 'var(--color-text-muted)' }}>
                        Mis ventas de hoy
                    </p>
                    <p className="mt-2 text-3xl font-bold" style={{ color: 'var(--color-text)' }}>
                        {sol(misVentasHoy.total)}
                    </p>
                    <p className="text-sm mt-1" style={{ color: 'var(--color-text-muted)' }}>
                        {misVentasHoy.cant} comprobantes emitidos
                    </p>
                </div>

                <div
                    className="lg:col-span-2 rounded-lg border p-5"
                    style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold" style={{ color: 'var(--color-text)' }}>
                            Mis últimas ventas
                        </h3>
                        <ClipboardList size={16} style={{ color: 'var(--color-text-muted)' }} />
                    </div>
                    {ultimasVentas.length === 0 ? (
                        <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>
                            Aún no has registrado ventas.
                        </p>
                    ) : (
                        <ul className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                            {ultimasVentas.map(v => (
                                <li key={v.id}>
                                    <Link
                                        href={route('ventas.show', v.id)}
                                        className="flex items-center justify-between py-2.5 hover:opacity-80"
                                    >
                                        <div className="min-w-0 pr-3">
                                            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                                {v.numero}{' '}
                                                <span
                                                    className="text-xs font-normal capitalize ml-1 rounded px-1.5 py-0.5"
                                                    style={{ backgroundColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}
                                                >
                                                    {v.tipo_comprobante}
                                                </span>
                                            </p>
                                            <p className="text-xs truncate mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                                {nombreCliente(v.cliente)} · {fechaCorta(v.fecha_venta)}
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
                        Ver todas mis ventas <ArrowRight size={12} />
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
