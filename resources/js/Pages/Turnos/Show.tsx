import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { AlertTriangle, ArrowLeft, Clock, RotateCcw, ShoppingCart, TrendingDown, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import type { Gasto, PageProps, Turno, Venta } from '@/types';

interface Props extends PageProps {
    turno:            Turno;
    ventasPorMetodo:  Record<string, number>;
    totalVentas:      number;
    totalGastos:      number;
    esAdmin:          boolean;
}

export default function TurnoShow({ turno, ventasPorMetodo, totalVentas, totalGastos, esAdmin }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalReabrir, setModalReabrir] = useState(false);
    const [reabriendo, setReabriendo]     = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const esCerrado = turno.estado === 'cerrado';

    function reabrir() {
        setReabriendo(true);
        router.post(route('turnos.reabrir', turno.id), {}, {
            onFinish: () => { setReabriendo(false); setModalReabrir(false); },
        });
    }

    // ── Columnas ventas ──
    const columnasVentas: Column<Venta>[] = [
        {
            key: 'numero', label: 'N°',
            render: (v) => <span className="font-medium text-sm">{v.numero}</span>,
        },
        {
            key: 'fecha_venta', label: 'Hora',
            render: (v) => <span className="text-sm">{new Date(v.fecha_venta).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}</span>,
        },
        {
            key: 'cliente', label: 'Cliente',
            render: (v) => {
                const c = v.cliente;
                if (!c) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
                const nombre = c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim();
                return <span className="text-sm">{nombre}</span>;
            },
        },
        {
            key: 'items', label: 'Ítems',
            render: (v) => <span className="text-sm">{(v.items ?? []).length}</span>,
        },
        {
            key: 'total', label: 'Total',
            render: (v) => <span className="font-medium">S/ {parseFloat(v.total).toFixed(2)}</span>,
        },
        {
            key: 'pagos', label: 'Métodos',
            render: (v) => (
                <span className="text-sm">
                    {(v.pagos ?? []).map(p => p.metodo_pago?.nombre ?? '—').join(', ') || '—'}
                </span>
            ),
        },
        {
            key: 'estado', label: 'Estado',
            render: (v) => (
                <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                    {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                </Badge>
            ),
        },
    ];

    // ── Columnas gastos ──
    const columnasGastos: Column<Gasto>[] = [
        {
            key: 'fecha', label: 'Fecha',
            render: (g) => <span className="text-sm">{new Date(g.fecha).toLocaleDateString('es-PE')}</span>,
        },
        {
            key: 'tipo', label: 'Tipo',
            render: (g) => <span>{g.tipo?.nombre ?? '—'}</span>,
        },
        {
            key: 'concepto', label: 'Concepto',
            render: (g) => <span>{g.concepto?.nombre ?? '—'}</span>,
        },
        {
            key: 'monto', label: 'Monto',
            render: (g) => <span className="font-medium">S/ {parseFloat(g.monto).toFixed(2)}</span>,
        },
        {
            key: 'user', label: 'Registrado por',
            render: (g) => <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>{g.user?.name ?? '—'}</span>,
        },
        {
            key: 'comentario', label: 'Comentario',
            render: (g) => g.comentario
                ? <span className="text-sm">{g.comentario as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
    ];

    const ventasCompletadas = (turno.ventas ?? []).filter(v => v.estado === 'completada');
    const ventasAnuladas    = (turno.ventas ?? []).filter(v => v.estado === 'anulada');

    return (
        <AppLayout title={`Turno #${turno.id}`}>
            <PageHeader
                title={`Turno — ${turno.caja?.nombre ?? '—'}`}
                subtitle={`${turno.user?.name ?? '—'} · ${new Date(turno.fecha_apertura).toLocaleString('es-PE')}${turno.fecha_cierre ? ` → ${new Date(turno.fecha_cierre).toLocaleString('es-PE')}` : ''}`}
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

            {/* ── Resumen general ── */}
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
                    label={`Ventas (${ventasCompletadas.length})`}
                    valor={`S/ ${totalVentas.toFixed(2)}`}
                />
                <InfoCard
                    icon={<TrendingDown size={16} style={{ color: 'var(--color-danger)' }} />}
                    label={`Gastos (${(turno.gastos ?? []).length})`}
                    valor={`S/ ${totalGastos.toFixed(2)}`}
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {/* Ventas por método */}
                <Section title="Resumen de ventas por método">
                    {Object.keys(ventasPorMetodo).length === 0 ? (
                        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin ventas en este turno.</p>
                    ) : (
                        <div className="space-y-2">
                            {Object.entries(ventasPorMetodo).map(([metodo, monto]) => (
                                <div key={metodo} className="flex justify-between text-sm">
                                    <span style={{ color: 'var(--color-text)' }}>{metodo}</span>
                                    <span className="font-medium" style={{ color: 'var(--color-text)' }}>
                                        S/ {monto.toFixed(2)}
                                    </span>
                                </div>
                            ))}
                            <div
                                className="flex justify-between text-sm font-semibold pt-2 mt-1"
                                style={{ borderTop: '1px solid var(--color-border)', color: 'var(--color-primary)' }}
                            >
                                <span>Total</span>
                                <span>S/ {totalVentas.toFixed(2)}</span>
                            </div>
                        </div>
                    )}
                </Section>

                {/* Arqueo de cierre (solo si está cerrado) */}
                {esCerrado && (
                    <Section title="Arqueo de cierre">
                        <div className="space-y-2">
                            <FilaResumen label="Total declarado" valor={parseFloat(turno.monto_cierre_declarado ?? '0')} />
                            <FilaResumen label="Total esperado" valor={parseFloat(turno.monto_cierre_esperado ?? '0')} />
                            <FilaDiferencia diferencia={parseFloat(turno.diferencia ?? '0')} />
                            {turno.observacion_cierre && (
                                <p className="text-xs pt-2" style={{ color: 'var(--color-text-muted)' }}>
                                    Obs: {turno.observacion_cierre as string}
                                </p>
                            )}
                        </div>

                        {/* Denominaciones */}
                        {(turno.arqueo ?? []).length > 0 && (
                            <div className="mt-4">
                                <p className="text-xs font-medium mb-2" style={{ color: 'var(--color-text-muted)' }}>
                                    Denominaciones declaradas
                                </p>
                                <div
                                    className="rounded-xl overflow-hidden"
                                    style={{ border: '1px solid var(--color-border)' }}
                                >
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                                <th className="text-left px-3 py-1.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Denominación</th>
                                                <th className="text-center px-3 py-1.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Cantidad</th>
                                                <th className="text-right px-3 py-1.5 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                            {(turno.arqueo ?? []).filter(r => r.cantidad > 0).map(row => (
                                                <tr key={row.id}>
                                                    <td className="px-3 py-1.5" style={{ color: 'var(--color-text)' }}>S/ {Number(row.denominacion).toFixed(2)}</td>
                                                    <td className="px-3 py-1.5 text-center" style={{ color: 'var(--color-text)' }}>{row.cantidad}</td>
                                                    <td className="px-3 py-1.5 text-right" style={{ color: 'var(--color-text)' }}>
                                                        S/ {(row.denominacion * row.cantidad).toFixed(2)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Arqueo otros métodos */}
                        {(turno.arqueo_metodos ?? []).length > 0 && (
                            <div className="mt-3 space-y-1">
                                <p className="text-xs font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                                    Otros métodos declarados
                                </p>
                                {(turno.arqueo_metodos ?? []).map(m => (
                                    <div key={m.id} className="flex justify-between text-sm">
                                        <span style={{ color: 'var(--color-text)' }}>{m.metodo_pago?.nombre ?? '—'}</span>
                                        <span style={{ color: 'var(--color-text)' }}>S/ {parseFloat(m.monto_declarado).toFixed(2)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>
                )}

                {/* Caja chica */}
                {turno.caja?.caja_chica_activa && (
                    <div
                        className="rounded-xl px-4 py-3 flex items-center justify-between"
                        style={{ backgroundColor: 'rgba(234,179,8,0.08)', border: '1px solid rgba(234,179,8,0.3)' }}
                    >
                        <div>
                            <p className="text-sm font-medium" style={{ color: '#b45309' }}>Caja chica</p>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>No incluida en el arqueo</p>
                        </div>
                        <span className="font-bold" style={{ color: '#b45309' }}>
                            S/ {parseFloat(turno.monto_caja_chica).toFixed(2)}
                        </span>
                    </div>
                )}
            </div>

            {/* ── Ventas del turno ── */}
            <div className="mb-6">
                <div className="flex items-center justify-between mb-3">
                    <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                        Ventas del turno
                        {ventasAnuladas.length > 0 && (
                            <span className="ml-2 text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                ({ventasAnuladas.length} anuladas)
                            </span>
                        )}
                    </p>
                </div>
                <Table
                    data={(turno.ventas ?? []) as Venta[]}
                    columns={columnasVentas}
                    emptyMessage="Sin ventas en este turno"
                    searchPlaceholder="Buscar venta..."
                />
            </div>

            {/* ── Gastos del turno ── */}
            <div className="mb-6">
                <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                    Gastos del turno
                </p>
                <Table
                    data={(turno.gastos ?? []) as Gasto[]}
                    columns={columnasGastos}
                    emptyMessage="Sin gastos en este turno"
                    searchPlaceholder="Buscar gasto..."
                />
            </div>

            {/* Modal confirmar reabrir */}
            <Modal
                isOpen={modalReabrir}
                onClose={() => setModalReabrir(false)}
                title="Reabrir turno"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalReabrir(false)}>Cancelar</Button>
                        <Button variant="danger" onClick={reabrir} disabled={reabriendo}>
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
                            Se eliminará el arqueo de cierre. El cajero podrá registrar ventas y gastos nuevamente.
                        </p>
                    </div>
                    <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                        ¿Confirmas que deseas reabrir este turno?
                    </p>
                </div>
            </Modal>
        </AppLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div
            className="rounded-xl p-4"
            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
        >
            <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>{title}</p>
            {children}
        </div>
    );
}

function InfoCard({ icon, label, valor }: { icon: React.ReactNode; label: string; valor: string }) {
    return (
        <div
            className="rounded-xl px-4 py-3"
            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center gap-2 mb-1">{icon}<p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</p></div>
            <p className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}

function FilaResumen({ label, valor }: { label: string; valor: number }) {
    return (
        <div className="flex items-center justify-between py-1">
            <span className="text-sm" style={{ color: 'var(--color-text)' }}>{label}</span>
            <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>S/ {valor.toFixed(2)}</span>
        </div>
    );
}

function FilaDiferencia({ diferencia }: { diferencia: number }) {
    const color = diferencia === 0 ? 'var(--color-success)' : diferencia > 0 ? '#b45309' : 'var(--color-danger)';
    const label = diferencia === 0 ? 'Sin diferencia' : diferencia > 0 ? 'Sobrante' : 'Faltante';
    return (
        <div className="flex items-center justify-between py-1">
            <span className="text-sm font-semibold" style={{ color }}>Diferencia ({label})</span>
            <span className="font-bold text-sm" style={{ color }}>S/ {Math.abs(diferencia).toFixed(2)}</span>
        </div>
    );
}
