import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Clock, HandCoins, Pencil, Plus, Printer, ShoppingCart, TrendingDown, Wallet, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import ModalAbrirTurno from './Partials/ModalAbrirTurno';
import ModalEditarApertura from './Partials/ModalEditarApertura';
import ModalRetiro from './Partials/ModalRetiro';
import { imprimirCierreTurno, type ShiftClosurePayload } from '@/lib/ticketPrinter';
import type { Caja, Gasto, MetodoPago, PageProps, Turno, TurnoRetiro, Venta } from '@/types';

interface CajaDisponible extends Caja {
    tiene_turno_abierto: boolean;
}

interface Paginado<T> {
    data:          T[];
    current_page:  number;
    last_page:     number;
    total:         number;
}

interface ConfigFondosLocal {
    usa_fondos_iniciales: boolean;
    fondos_iniciales_en_declaracion: boolean;
}

interface ConfigEfectivo {
    modo_apertura_caja:         'libre' | 'arrastre' | 'fondo_fijo';
    apertura_editable:          boolean;
    usa_retiros_caja:           boolean;
    retiro_requiere_aprobacion: boolean;
}

interface Props extends PageProps {
    turnos:           Paginado<Turno>;
    buscar?:          string;
    cajasDisponibles: CajaDisponible[];
    metodosPago:      MetodoPago[];
    turnoActivo:      Turno | null;
    configFondos:     Record<number, ConfigFondosLocal>;
    configEfectivo:   ConfigEfectivo;
}

export default function TurnosIndex({ turnos, buscar, cajasDisponibles, metodosPago, turnoActivo, configFondos, configEfectivo }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalAbrir, setModalAbrir] = useState(false);
    const [modalEditarApertura, setModalEditarApertura] = useState(false);
    const [modalRetiro, setModalRetiro] = useState(false);
    const [imprimiendo, setImprimiendo] = useState<number | null>(null);

    const retirosTurno = (turnoActivo?.retiros ?? []) as TurnoRetiro[];
    const totalRetirosTurno = retirosTurno.reduce((s, r) => s + parseFloat(r.monto), 0);

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

    async function imprimir(t: Turno) {
        setImprimiendo(t.id);
        const tid = toast.loading(`Obteniendo cierre del turno #${t.id}...`);
        try {
            const { data } = await axios.get<ShiftClosurePayload>(route('turnos.cierre-ticket', t.id));
            if (!data?.token) {
                toast.error('Esta caja no tiene ticketera configurada.', { id: tid });
                return;
            }
            const ok = await imprimirCierreTurno(data);
            if (ok) toast.success(`Cierre del turno #${t.id} enviado a la impresora`, { id: tid });
            else    toast.error('No se pudo imprimir. Revisa VentoryPrint en esta PC.', { id: tid });
        } catch {
            toast.error('No se pudo obtener el reporte de cierre.', { id: tid });
        } finally {
            setImprimiendo(null);
        }
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
                <div className="flex items-center gap-2">
                    <Badge variant={t.estado === 'abierto' ? 'success' : 'secondary'}>
                        {t.estado === 'abierto' ? 'Abierto' : 'Cerrado'}
                    </Badge>
                    {t.estado === 'cerrado' && (
                        <button
                            onClick={() => imprimir(t)}
                            disabled={imprimiendo === t.id}
                            title="Reimprimir cierre de turno"
                            className="inline-flex items-center justify-center w-7 h-7 rounded-lg transition-colors hover:opacity-80 disabled:opacity-40"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}
                        >
                            <Printer size={14} />
                        </button>
                    )}
                </div>
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
                            action={
                                <button
                                    onClick={() => setModalEditarApertura(true)}
                                    title="Editar monto de apertura"
                                    className="inline-flex items-center justify-center w-6 h-6 rounded-md transition-colors hover:opacity-80"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}
                                >
                                    <Pencil size={12} />
                                </button>
                            }
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

                    {/* Retiros de efectivo (config de empresa) */}
                    {configEfectivo.usa_retiros_caja && (
                        <div>
                            <div className="flex items-center justify-between mb-3">
                                <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                    Retiros de efectivo
                                    {totalRetirosTurno > 0 && (
                                        <span className="ml-2 font-normal text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                            total S/ {totalRetirosTurno.toFixed(2)}
                                        </span>
                                    )}
                                </p>
                                <Button variant="secondary" onClick={() => setModalRetiro(true)}>
                                    <HandCoins size={15} className="mr-1" />Retiro de efectivo
                                </Button>
                            </div>
                            {retirosTurno.length === 0 ? (
                                <p className="text-sm rounded-xl px-4 py-3"
                                    style={{ color: 'var(--color-text-muted)', border: '1px dashed var(--color-border)' }}>
                                    Sin retiros en este turno. Usa "Retiro de efectivo" cuando entregues dinero a administración.
                                </p>
                            ) : (
                                <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                    {retirosTurno.map((r, i) => (
                                        <div key={r.id} className="flex items-center justify-between gap-3 px-4 py-2.5"
                                            style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined, backgroundColor: 'var(--color-surface)' }}>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>{r.concepto}</p>
                                                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                    {new Date(r.created_at).toLocaleString('es-PE')} · {r.user?.name ?? '—'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2 flex-shrink-0">
                                                {r.estado === 'registrado' && (
                                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                        style={{ color: '#b45309', backgroundColor: 'rgba(234,179,8,0.12)' }}>
                                                        sin aprobar
                                                    </span>
                                                )}
                                                <span className="font-bold text-sm" style={{ color: 'var(--color-danger)' }}>
                                                    − S/ {parseFloat(r.monto).toFixed(2)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
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
                    data={turnos}
                    columns={columnasTurnos}
                    emptyMessage="Sin historial de turnos"
                    searchPlaceholder="Buscar en historial..."
                    initialSearch={buscar}
                    onServerSearch={(t) => router.get(route('turnos.index'),
                        { buscar: t || undefined },
                        { preserveState: true, preserveScroll: true, replace: true })}
                />
            </div>

            {/* Modales */}
            <ModalAbrirTurno
                isOpen={modalAbrir}
                onClose={() => setModalAbrir(false)}
                cajasDisponibles={cajasDisponibles}
                configFondos={configFondos}
                configEfectivo={configEfectivo}
            />
            {turnoActivo && (
                <ModalEditarApertura
                    isOpen={modalEditarApertura}
                    onClose={() => setModalEditarApertura(false)}
                    turnoId={turnoActivo.id}
                    montoActual={turnoActivo.monto_apertura}
                    editable={configEfectivo.apertura_editable}
                />
            )}
            {turnoActivo && (
                <ModalRetiro
                    isOpen={modalRetiro}
                    onClose={() => setModalRetiro(false)}
                    turnoId={turnoActivo.id}
                    requiereAprobacion={configEfectivo.retiro_requiere_aprobacion}
                />
            )}
        </AppLayout>
    );
}

function InfoCard({ icon, label, valor, action }: { icon: React.ReactNode; label: string; valor: string; action?: React.ReactNode }) {
    return (
        <div
            className="rounded-xl px-4 py-3 flex flex-col justify-between"
            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center justify-between mb-1">
                <div className="flex items-center gap-2">{icon}<p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</p></div>
                {action}
            </div>
            <p className="font-semibold text-base" style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}
