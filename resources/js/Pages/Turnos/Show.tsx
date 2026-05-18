import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    AlertTriangle, ArrowLeft, ArrowRight, Clock,
    RotateCcw, ShoppingCart, TrendingDown, Wallet,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import type { PageProps, Turno } from '@/types';

interface Props extends PageProps {
    turno:            Turno;
    ventasPorMetodo:  Record<string, number>;
    totalVentas:      number;
    totalGastos:      number;
    esAdmin:          boolean;
}

export default function TurnoShow({ turno, totalVentas, totalGastos, esAdmin }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalReabrir, setModalReabrir] = useState(false);
    const [reabriendo, setReabriendo]     = useState(false);
    // A8: motivo obligatorio. El backend exige min 10 chars; bloqueamos el
    // submit aqui mismo para feedback inmediato sin viajar al server.
    const [motivoReabrir, setMotivoReabrir] = useState('');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const esCerrado = turno.estado === 'cerrado';
    const motivoValido = motivoReabrir.trim().length >= 10;

    function reabrir() {
        if (!motivoValido) {
            toast.error('El motivo es obligatorio (mínimo 10 caracteres).');
            return;
        }
        setReabriendo(true);
        router.post(route('turnos.reabrir', turno.id), { motivo: motivoReabrir.trim() }, {
            onFinish: () => { setReabriendo(false); },
            onSuccess: () => {
                setModalReabrir(false);
                setMotivoReabrir('');
            },
        });
    }

    // ── Agregar productos vendidos de ventas completadas ──
    const productosVendidos = useMemo(() => {
        const mapa: Record<string, { nombre: string; cantidad: number; total: number }> = {};
        (turno.ventas ?? [])
            .filter(v => v.estado === 'completada')
            .forEach(v => {
                (v.items ?? []).forEach(item => {
                    const key = item.producto_nombre as string;
                    if (!mapa[key]) mapa[key] = { nombre: key, cantidad: 0, total: 0 };
                    mapa[key].cantidad += parseFloat(item.cantidad as string);
                    mapa[key].total    += parseFloat(item.subtotal as string);
                });
            });
        return Object.values(mapa).sort((a, b) => b.total - a.total);
    }, [turno.ventas]);

    const totalEfectivoArqueo = useMemo(() =>
        (turno.arqueo ?? []).reduce((s, r) => s + r.denominacion * r.cantidad, 0),
    [turno.arqueo]);

    const ventasCompletadas = (turno.ventas ?? []).filter(v => v.estado === 'completada').length;
    const ventasAnuladas    = (turno.ventas ?? []).filter(v => v.estado === 'anulada').length;

    return (
        <AppLayout title={`Turno #${turno.id}`}>
            <PageHeader
                title={`Turno — ${turno.caja?.nombre ?? '—'}`}
                subtitle={
                    `${turno.user?.name ?? '—'} · ` +
                    `${new Date(turno.fecha_apertura).toLocaleString('es-PE')}` +
                    (turno.fecha_cierre ? ` → ${new Date(turno.fecha_cierre).toLocaleString('es-PE')}` : '')
                }
                actions={
                    <div className="flex gap-2">
                        {esAdmin && esCerrado && (
                            <Button variant="ghost" onClick={() => setModalReabrir(true)}>
                                <RotateCcw size={14} className="mr-1" />Reabrir turno
                            </Button>
                        )}
                        <Button variant="ghost" onClick={() => router.visit(route('turnos.index'))}>
                            <ArrowLeft size={14} className="mr-1" />Volver
                        </Button>
                    </div>
                }
            />

            {/* ── Cards resumen ── */}
            <div
                className="rounded-2xl p-5 grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
            >
                <InfoCard
                    icon={<Clock size={16} style={{ color: 'var(--color-primary)' }} />}
                    label="Apertura"
                    valor={new Date(turno.fecha_apertura).toLocaleString('es-PE')}
                />
                <InfoCard
                    icon={<Wallet size={16} style={{ color: 'var(--color-success)' }} />}
                    label="Monto apertura"
                    valor={`S/ ${parseFloat(turno.monto_apertura).toFixed(2)}`}
                />
                <InfoCard
                    icon={<ShoppingCart size={16} style={{ color: 'var(--color-primary)' }} />}
                    label={`Ventas completadas`}
                    valor={`${ventasCompletadas}${ventasAnuladas > 0 ? ` (${ventasAnuladas} anuladas)` : ''}`}
                    subvalor={`S/ ${totalVentas.toFixed(2)}`}
                />
                <InfoCard
                    icon={<TrendingDown size={16} style={{ color: 'var(--color-danger)' }} />}
                    label={`Gastos (${(turno.gastos ?? []).length})`}
                    valor={`S/ ${totalGastos.toFixed(2)}`}
                />

                {/* Estado */}
                <div
                    className="col-span-2 sm:col-span-4 flex items-center justify-between rounded-xl px-4 py-3"
                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                >
                    <div className="flex items-center gap-3">
                        <Badge variant={esCerrado ? 'secondary' : 'success'}>
                            {esCerrado ? 'Cerrado' : 'Abierto'}
                        </Badge>
                        {esCerrado && turno.user_cierre && (
                            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                Cerrado por <strong style={{ color: 'var(--color-text)' }}>{turno.user_cierre.name}</strong>
                            </span>
                        )}
                        {turno.caja?.caja_chica_activa && (
                            <span className="text-sm ml-2" style={{ color: '#b45309' }}>
                                Caja chica: <strong>S/ {parseFloat(turno.monto_caja_chica).toFixed(2)}</strong>
                            </span>
                        )}
                    </div>
                    {esCerrado && (
                        <div className="flex items-center gap-6">
                            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                Declarado: <strong style={{ color: 'var(--color-text)' }}>S/ {parseFloat(turno.monto_cierre_declarado ?? '0').toFixed(2)}</strong>
                            </span>
                            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                Esperado: <strong style={{ color: 'var(--color-text)' }}>S/ {parseFloat(turno.monto_cierre_esperado ?? '0').toFixed(2)}</strong>
                            </span>
                            <DiferenciaBadge diferencia={parseFloat(turno.diferencia ?? '0')} />
                        </div>
                    )}
                </div>
            </div>

            {/* ── Productos vendidos + Gastos ── */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                {/* Productos vendidos */}
                <Section
                    title="Productos vendidos"
                    action={
                        <button
                            onClick={() => router.visit(route('ventas.index'))}
                            className="flex items-center gap-1 text-xs"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            Ver ventas <ArrowRight size={12} />
                        </button>
                    }
                >
                    {productosVendidos.length === 0 ? (
                        <p className="text-sm py-4 text-center" style={{ color: 'var(--color-text-muted)' }}>
                            Sin ventas en este turno.
                        </p>
                    ) : (
                        <div
                            className="rounded-xl overflow-hidden"
                            style={{ border: '1px solid var(--color-border)' }}
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                        <th className="text-left px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Producto</th>
                                        <th className="text-center px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Cant.</th>
                                        <th className="text-right px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                    {productosVendidos.map(p => (
                                        <tr key={p.nombre}>
                                            <td className="px-3 py-2" style={{ color: 'var(--color-text)' }}>{p.nombre}</td>
                                            <td className="px-3 py-2 text-center" style={{ color: 'var(--color-text-muted)' }}>
                                                {Number.isInteger(p.cantidad) ? p.cantidad : p.cantidad.toFixed(2)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-medium" style={{ color: 'var(--color-text)' }}>
                                                S/ {p.total.toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ backgroundColor: 'var(--color-bg)', borderTop: '2px solid var(--color-border)' }}>
                                        <td colSpan={2} className="px-3 py-2 font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                                            Total ventas
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold text-sm" style={{ color: 'var(--color-primary)' }}>
                                            S/ {totalVentas.toFixed(2)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </Section>

                {/* Resumen gastos */}
                <Section
                    title="Gastos del turno"
                    action={
                        <button
                            onClick={() => router.visit(route('gastos.index'))}
                            className="flex items-center gap-1 text-xs"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            Ver gastos <ArrowRight size={12} />
                        </button>
                    }
                >
                    {(turno.gastos ?? []).length === 0 ? (
                        <p className="text-sm py-4 text-center" style={{ color: 'var(--color-text-muted)' }}>
                            Sin gastos en este turno.
                        </p>
                    ) : (
                        <div
                            className="rounded-xl overflow-hidden"
                            style={{ border: '1px solid var(--color-border)' }}
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                        <th className="text-left px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Tipo</th>
                                        <th className="text-left px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Concepto</th>
                                        <th className="text-right px-3 py-2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                    {(turno.gastos ?? []).map(g => (
                                        <tr key={g.id as number}>
                                            <td className="px-3 py-2" style={{ color: 'var(--color-text-muted)' }}>
                                                {g.tipo?.nombre ?? '—'}
                                            </td>
                                            <td className="px-3 py-2" style={{ color: 'var(--color-text)' }}>
                                                {g.concepto?.nombre ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right font-medium" style={{ color: 'var(--color-danger)' }}>
                                                S/ {parseFloat(g.monto).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ backgroundColor: 'var(--color-bg)', borderTop: '2px solid var(--color-border)' }}>
                                        <td colSpan={2} className="px-3 py-2 font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                                            Total gastos
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold text-sm" style={{ color: 'var(--color-danger)' }}>
                                            S/ {totalGastos.toFixed(2)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </Section>
            </div>

            {/* ── Arqueo de cierre (solo si cerrado) ── */}
            {esCerrado && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                    {/* Denominaciones */}
                    <Section title="Arqueo — denominaciones declaradas">
                        {(turno.arqueo ?? []).filter(r => r.cantidad > 0).length === 0 ? (
                            <p className="text-sm py-2 text-center" style={{ color: 'var(--color-text-muted)' }}>
                                Sin arqueo registrado.
                            </p>
                        ) : (
                            <div
                                className="rounded-xl overflow-hidden"
                                style={{ border: '1px solid var(--color-border)' }}
                            >
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                            <th className="text-left px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Denominación</th>
                                            <th className="text-center px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Cantidad</th>
                                            <th className="text-right px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                        {(turno.arqueo ?? [])
                                            .filter(r => r.cantidad > 0)
                                            .map(row => (
                                                <tr key={row.id}>
                                                    <td className="px-4 py-2.5 font-medium" style={{ color: 'var(--color-text)' }}>
                                                        S/ {Number(row.denominacion).toFixed(2)}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-center" style={{ color: 'var(--color-text-muted)' }}>
                                                        × {row.cantidad}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right font-medium" style={{ color: 'var(--color-text)' }}>
                                                        S/ {(row.denominacion * row.cantidad).toFixed(2)}
                                                    </td>
                                                </tr>
                                            ))}
                                    </tbody>
                                    <tfoot>
                                        <tr style={{ backgroundColor: 'var(--color-bg)', borderTop: '2px solid var(--color-border)' }}>
                                            <td colSpan={2} className="px-4 py-2.5 font-semibold" style={{ color: 'var(--color-text)' }}>
                                                Total efectivo
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-bold" style={{ color: 'var(--color-primary)' }}>
                                                S/ {totalEfectivoArqueo.toFixed(2)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}
                    </Section>

                    {/* Resumen de cierre + otros métodos */}
                    <div className="space-y-4">

                        {/* Otros métodos declarados */}
                        {(turno.arqueo_metodos ?? []).length > 0 && (
                            <Section title="Otros métodos declarados">
                                <div
                                    className="rounded-xl overflow-hidden"
                                    style={{ border: '1px solid var(--color-border)' }}
                                >
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                                <th className="text-left px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Método</th>
                                                <th className="text-right px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Monto declarado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                            {(turno.arqueo_metodos ?? []).map(m => (
                                                <tr key={m.id}>
                                                    <td className="px-4 py-2.5 font-medium" style={{ color: 'var(--color-text)' }}>
                                                        {m.metodo_pago?.nombre ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right" style={{ color: 'var(--color-text)' }}>
                                                        S/ {parseFloat(m.monto_declarado).toFixed(2)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Section>
                        )}

                        {/* Resultado del cierre */}
                        <Section title="Resultado del cierre">
                            <div
                                className="rounded-xl overflow-hidden"
                                style={{ border: '1px solid var(--color-border)' }}
                            >
                                <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                    <FilaCierre
                                        label="Efectivo declarado (arqueo)"
                                        valor={totalEfectivoArqueo}
                                        color="var(--color-text)"
                                    />
                                    <FilaCierre
                                        label="Monto esperado (sistema)"
                                        valor={parseFloat(turno.monto_cierre_esperado ?? '0')}
                                        color="var(--color-text)"
                                    />
                                    <FilaCierreResultado
                                        diferencia={parseFloat(turno.diferencia ?? '0')}
                                    />
                                </div>
                            </div>
                            {turno.observacion_cierre && (
                                <div
                                    className="mt-3 rounded-xl px-4 py-3 text-sm"
                                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}
                                >
                                    <span className="font-medium" style={{ color: 'var(--color-text)' }}>Observación: </span>
                                    {turno.observacion_cierre as string}
                                </div>
                            )}
                        </Section>
                    </div>
                </div>
            )}

            {/* Modal confirmar reabrir */}
            <Modal
                isOpen={modalReabrir}
                onClose={() => { setModalReabrir(false); setMotivoReabrir(''); }}
                title="Reabrir turno"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => { setModalReabrir(false); setMotivoReabrir(''); }}>
                            Cancelar
                        </Button>
                        <Button variant="danger" onClick={reabrir} disabled={reabriendo || !motivoValido}>
                            {reabriendo ? 'Reabriendo...' : 'Sí, reabrir'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <div
                        className="flex items-start gap-2 rounded-xl px-4 py-3 text-sm"
                        style={{ backgroundColor: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)' }}
                    >
                        <AlertTriangle size={15} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-danger)' }} />
                        <p style={{ color: 'var(--color-text)' }}>
                            Se eliminará el arqueo de cierre y se anulará el cierre de inventario asociado.
                            El cajero podrá registrar ventas y gastos nuevamente hasta que se vuelva a cerrar.
                        </p>
                    </div>

                    {/* A8: motivo obligatorio para auditoria */}
                    <div>
                        <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                            Motivo de la reapertura <span style={{ color: 'var(--color-danger)' }}>*</span>
                        </label>
                        <textarea
                            value={motivoReabrir}
                            onChange={(e) => setMotivoReabrir(e.target.value)}
                            rows={3}
                            maxLength={500}
                            placeholder="Ej: El cajero olvidó declarar un pago Yape de S/ 150"
                            className="w-full rounded-lg border px-3 py-2 text-sm"
                            style={{
                                borderColor: motivoReabrir.length > 0 && !motivoValido
                                    ? 'var(--color-danger)'
                                    : 'var(--color-border)',
                                background: 'var(--color-surface)',
                                color: 'var(--color-text)',
                            }}
                        />
                        <div className="flex justify-between mt-1 text-xs">
                            <span style={{ color: motivoValido ? 'var(--color-text-muted)' : 'var(--color-danger)' }}>
                                {motivoValido
                                    ? 'Mínimo 10 caracteres ✓'
                                    : `Mínimo 10 caracteres (${motivoReabrir.trim().length}/10)`}
                            </span>
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                {motivoReabrir.length}/500
                            </span>
                        </div>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}

// ── Componentes auxiliares ──────────────────────────────────────────────────

function Section({ title, children, action }: { title: string; children: React.ReactNode; action?: React.ReactNode }) {
    return (
        <div
            className="rounded-xl p-4"
            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
        >
            <div className="flex items-center justify-between mb-3">
                <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{title}</p>
                {action}
            </div>
            {children}
        </div>
    );
}

function InfoCard({ icon, label, valor, subvalor }: { icon: React.ReactNode; label: string; valor: string; subvalor?: string }) {
    return (
        <div
            className="rounded-xl px-4 py-3"
            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center gap-2 mb-1">
                {icon}
                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            </div>
            <p className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>{valor}</p>
            {subvalor && <p className="text-xs mt-0.5 font-medium" style={{ color: 'var(--color-primary)' }}>{subvalor}</p>}
        </div>
    );
}

function DiferenciaBadge({ diferencia }: { diferencia: number }) {
    const variant = diferencia === 0 ? 'success' : diferencia > 0 ? 'warning' : 'danger';
    const label   = diferencia === 0 ? 'Cuadrado' : diferencia > 0 ? `Sobrante S/ ${diferencia.toFixed(2)}` : `Faltante S/ ${Math.abs(diferencia).toFixed(2)}`;
    return <Badge variant={variant}>{label}</Badge>;
}

function FilaCierre({ label, valor, color }: { label: string; valor: number; color: string }) {
    return (
        <div className="flex items-center justify-between px-4 py-3">
            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>{label}</span>
            <span className="font-semibold text-sm" style={{ color }}> S/ {valor.toFixed(2)}</span>
        </div>
    );
}

function FilaCierreResultado({ diferencia }: { diferencia: number }) {
    const color = diferencia === 0 ? 'var(--color-success)' : diferencia > 0 ? '#b45309' : 'var(--color-danger)';
    const label = diferencia === 0 ? 'Sin diferencia' : diferencia > 0 ? 'Sobrante' : 'Faltante';
    return (
        <div
            className="flex items-center justify-between px-4 py-3"
            style={{ backgroundColor: 'var(--color-bg)' }}
        >
            <span className="text-sm font-semibold" style={{ color }}>Diferencia ({label})</span>
            <span className="font-bold text-sm" style={{ color }}>S/ {Math.abs(diferencia).toFixed(2)}</span>
        </div>
    );
}
