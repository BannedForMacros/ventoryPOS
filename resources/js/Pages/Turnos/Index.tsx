import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Clock, Plus, ShoppingCart, TrendingDown, Wallet, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import ModalAbrirTurno from './Partials/ModalAbrirTurno';
import type { Caja, Gasto, MetodoPago, PageProps, Turno, Venta } from '@/types';

interface CajaDisponible extends Caja {
    tiene_turno_abierto: boolean;
}

interface Paginado<T> {
    data:          T[];
    current_page:  number;
    last_page:     number;
    total:         number;
}

interface Props extends PageProps {
    turnos:           Paginado<Turno>;
    cajasDisponibles: CajaDisponible[];
    metodosPago:      MetodoPago[];
    turnoActivo:      Turno | null;
}

export default function TurnosIndex({ turnos, cajasDisponibles, metodosPago, turnoActivo }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalAbrir, setModalAbrir] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const totalGastosTurno = (turnoActivo?.gastos ?? [])
        .reduce((sum: number, g: Gasto) => sum + parseFloat(g.monto), 0);

    const totalVentasTurno = (turnoActivo?.ventas ?? [])
        .reduce((sum: number, v: Venta) => sum + parseFloat(v.total), 0);

    // ── Columnas historial ──
    function verDetalle(turno: Turno) {
        router.visit(route('turnos.show', turno.id));
    }

    const columnasTurnos: Column<Turno>[] = [
        {
            key: 'fecha_apertura', label: 'Apertura', sortable: true,
            render: (t) => (
                <span className="text-sm">{new Date(t.fecha_apertura).toLocaleString('es-PE')}</span>
            ),
        },
        {
            key: 'caja', label: 'Caja',
            render: (t) => <span>{t.caja?.nombre ?? '—'}</span>,
        },
        {
            key: 'user', label: 'Abrió',
            render: (t) => <span>{t.user?.name ?? '—'}</span>,
        },
        {
            key: 'user_cierre', label: 'Cerró',
            render: (t) => <span>{t.user_cierre?.name ?? <span style={{ color: 'var(--color-text-muted)' }}>—</span>}</span>,
        },
        {
            key: 'monto_apertura', label: 'Apertura (S/)',
            render: (t) => <span>S/ {parseFloat(t.monto_apertura).toFixed(2)}</span>,
        },
        {
            key: 'diferencia', label: 'Diferencia',
            render: (t) => {
                if (t.diferencia === null) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
                const diff = parseFloat(t.diferencia);
                return (
                    <Badge variant={diff === 0 ? 'success' : diff > 0 ? 'warning' : 'danger'}>
                        {diff >= 0 ? '+' : ''}S/ {diff.toFixed(2)}
                    </Badge>
                );
            },
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (t) => (
                <Badge variant={t.estado === 'abierto' ? 'success' : 'secondary'}>
                    {t.estado === 'abierto' ? 'Abierto' : 'Cerrado'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: '',
            render: (t) => (
                <button
                    onClick={() => verDetalle(t)}
                    className="text-xs px-2.5 py-1 rounded-lg font-medium transition-colors"
                    style={{ color: 'var(--color-primary)', border: '1px solid var(--color-primary)' }}
                >
                    Ver detalle
                </button>
            ),
        },
    ];

    // ── Columnas gastos del turno activo ──
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
            key: 'comentario', label: 'Comentario',
            render: (g) => g.comentario
                ? <span className="text-sm">{g.comentario as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
    ];

    // ── Columnas ventas del turno activo ──
    const columnasVentas: Column<Venta>[] = [
        {
            key: 'numero', label: 'Número',
            render: (v) => <span className="text-sm font-medium">{v.numero}</span>,
        },
        {
            key: 'fecha_venta', label: 'Hora',
            render: (v) => <span className="text-sm">{new Date(v.fecha_venta).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}</span>,
        },
        {
            key: 'total', label: 'Total',
            render: (v) => <span className="font-medium">S/ {parseFloat(v.total).toFixed(2)}</span>,
        },
        {
            key: 'estado', label: 'Estado',
            render: (v) => (
                <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                    {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                </Badge>
            ),
        },
        {
            key: 'pagos', label: 'Método de pago',
            render: (v) => (
                <span className="text-sm">
                    {(v.pagos ?? []).map(p => p.metodo_pago?.nombre ?? '—').join(', ') || '—'}
                </span>
            ),
        },
    ];

    return (
        <AppLayout title="Turnos">
            <PageHeader
                title="Turnos"
                subtitle="Gestión de apertura y cierre de caja"
                actions={
                    !turnoActivo ? (
                        <Button onClick={() => setModalAbrir(true)}>
                            <Plus size={15} className="mr-1 flex-shrink-0" />Abrir turno
                        </Button>
                    ) : (
                        <Button variant="danger" onClick={() => router.visit(route('turnos.cerrar.page', turnoActivo.id))}>
                            <X size={15} className="mr-1 flex-shrink-0" />Cerrar turno
                        </Button>
                    )
                }
            />

            {/* ── Turno activo ── */}
            {turnoActivo ? (
                <div className="space-y-6 mb-8">
                    {/* Card turno */}
                    <div
                        className="rounded-2xl p-5 grid grid-cols-2 gap-4 sm:grid-cols-4"
                        style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                    >
                        <InfoCard
                            icon={<Clock size={18} style={{ color: 'var(--color-primary)' }} />}
                            label="Apertura"
                            valor={new Date(turnoActivo.fecha_apertura).toLocaleString('es-PE')}
                        />
                        <InfoCard
                            icon={<Wallet size={18} style={{ color: 'var(--color-success)' }} />}
                            label="Monto apertura"
                            valor={`S/ ${parseFloat(turnoActivo.monto_apertura).toFixed(2)}`}
                        />
                        <InfoCard
                            icon={<ShoppingCart size={18} style={{ color: 'var(--color-primary)' }} />}
                            label={`Ventas (${(turnoActivo.ventas ?? []).length})`}
                            valor={`S/ ${totalVentasTurno.toFixed(2)}`}
                        />
                        <InfoCard
                            icon={<TrendingDown size={18} style={{ color: 'var(--color-danger)' }} />}
                            label="Gastos del turno"
                            valor={`S/ ${totalGastosTurno.toFixed(2)}`}
                        />

                        {/* Caja chica — solo si aplica */}
                        {turnoActivo.caja?.caja_chica_activa && (
                            <div
                                className="rounded-xl px-4 py-3 flex flex-col justify-between"
                                style={{ backgroundColor: 'rgba(234,179,8,0.08)', border: '1px solid rgba(234,179,8,0.3)' }}
                            >
                                <p className="text-xs font-medium" style={{ color: '#b45309' }}>Caja chica</p>
                                <p className="text-lg font-bold mt-1" style={{ color: '#b45309' }}>
                                    S/ {parseFloat(turnoActivo.monto_caja_chica).toFixed(2)}
                                </p>
                                <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>Independiente del arqueo</p>
                            </div>
                        )}
                    </div>

                    {/* Ventas del turno */}
                    <div>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                            Ventas del turno
                        </p>
                        <Table
                            data={(turnoActivo.ventas ?? []) as Venta[]}
                            columns={columnasVentas}
                            emptyMessage="Sin ventas en este turno"
                            searchPlaceholder="Buscar venta..."
                        />
                    </div>

                    {/* Gastos del turno */}
                    <div>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                            Gastos del turno
                        </p>
                        <Table
                            data={(turnoActivo.gastos ?? []) as Gasto[]}
                            columns={columnasGastos}
                            emptyMessage="Sin gastos en este turno"
                            searchPlaceholder="Buscar gasto..."
                        />
                    </div>
                </div>
            ) : (
                <div
                    className="rounded-2xl flex flex-col items-center justify-center py-16 mb-8"
                    style={{ border: '2px dashed var(--color-border)', backgroundColor: 'var(--color-bg)' }}
                >
                    <Clock size={40} style={{ color: 'var(--color-text-muted)' }} className="mb-3" />
                    <p className="font-semibold text-lg mb-1" style={{ color: 'var(--color-text)' }}>
                        No tienes un turno abierto
                    </p>
                    <p className="text-sm mb-4" style={{ color: 'var(--color-text-muted)' }}>
                        Abre un turno para comenzar a registrar ventas y gastos
                    </p>
                    <Button onClick={() => setModalAbrir(true)}>
                        <Plus size={15} className="mr-1" />Abrir turno
                    </Button>
                </div>
            )}

            {/* ── Historial de turnos ── */}
            <div>
                <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                    Historial de turnos
                </p>
                <Table
                    data={turnos.data}
                    columns={columnasTurnos}
                    emptyMessage="Sin historial de turnos"
                    searchPlaceholder="Buscar en historial..."
                />
            </div>

            {/* Modales */}
            <ModalAbrirTurno
                isOpen={modalAbrir}
                onClose={() => setModalAbrir(false)}
                cajasDisponibles={cajasDisponibles}
            />
        </AppLayout>
    );
}

function InfoCard({ icon, label, valor }: { icon: React.ReactNode; label: string; valor: string }) {
    return (
        <div
            className="rounded-xl px-4 py-3 flex flex-col justify-between"
            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center gap-2 mb-1">{icon}<p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</p></div>
            <p className="font-semibold text-base" style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}
