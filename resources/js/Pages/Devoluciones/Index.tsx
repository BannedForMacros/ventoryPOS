import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Eye, CheckCircle, XCircle, Ban } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';

type EstadoDev = 'pendiente' | 'aprobada' | 'rechazada' | 'completada' | 'anulada';

interface Devolucion extends Record<string, unknown> {
    id: number;
    numero: string | null;
    fecha: string;
    estado: EstadoDev;
    forma_reembolso: string;
    monto_devolucion: string;
    monto_reembolso: string;
    venta?: { id: number; numero: string; total: string; fecha_venta: string };
    motivo?: { nombre: string };
    user?: { name: string };
    local?: { nombre: string };
    requiere_aprobacion: boolean;
    fue_aprobada: boolean;
    nota_credito_estado: EstadoNC | null;
}

// M19: el backend ahora paginé estos listados; el FE consume {data, links, meta}.
interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

type EstadoNC = 'no_aplica' | 'pendiente' | 'esperando' | 'emitida' | 'fallida';

interface Props extends PageProps {
    /** Cuántas notas de crédito quedaron sin emitir en toda la empresa. */
    ncFallidas: number;
    devoluciones: Paginado<Devolucion>;
    filters: Record<string, string>;
    buscar?: string;
}

const ESTADO_VARIANT: Record<EstadoDev, 'warning' | 'primary' | 'success' | 'secondary' | 'danger'> = {
    pendiente:  'warning',
    aprobada:   'primary',
    rechazada:  'danger',
    completada: 'success',
    anulada:    'secondary',
};
const ESTADO_LABEL: Record<EstadoDev, string> = {
    pendiente:  'Pendiente',
    aprobada:   'Aprobada',
    rechazada:  'Rechazada',
    completada: 'Completada',
    anulada:    'Anulada',
};
const FORMA_LABEL: Record<string, string> = {
    efectivo:        'Efectivo',
    mismo_metodo:    'Mismo método',
    vale_credito:    'Vale / Crédito',
    cambio_producto: 'Cambio de producto',
    sin_reembolso:   'Sin reembolso',
};

export default function DevolucionesIndex({ devoluciones, filters, buscar, ncFallidas }: Props) {
    const { flash, auth } = usePage<Props>().props;
    const esAdmin = (auth.user as { rol?: { es_admin?: boolean } } | undefined)?.rol?.es_admin ?? false;
    const viendoNcPendientes = !!filters.nc_pendiente;

    const [filtrEstado, setFiltrEstado] = useState(filters.estado ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function aprobar(id: number) { router.post(route('devoluciones.aprobar', id)); }
    function rechazar(id: number) { router.post(route('devoluciones.rechazar', id)); }
    function anular(id: number) { router.post(route('devoluciones.anular', id)); }

    const columns: Column<Devolucion>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (d) => <span className="text-sm">{new Date(d.fecha).toLocaleString('es-PE')}</span>,
        },
        {
            key: 'numero', label: 'N° devolución',
            render: (d) => <span className="font-mono text-xs">{d.numero ?? '—'}</span>,
        },
        {
            key: 'venta', label: 'Venta origen',
            render: (d) => d.venta
                ? <Link href={route('ventas.show', d.venta.id)}><span className="font-mono text-xs" style={{ color: 'var(--color-primary)' }}>{d.venta.numero}</span></Link>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'motivo', label: 'Motivo',
            render: (d) => <span className="text-sm">{d.motivo?.nombre ?? '—'}</span>,
        },
        {
            key: 'forma_reembolso', label: 'Reembolso',
            render: (d) => <Badge variant="primary">{FORMA_LABEL[d.forma_reembolso] ?? d.forma_reembolso}</Badge>,
        },
        {
            key: 'monto_devolucion', label: 'Monto',
            render: (d) => <span className="font-mono text-sm">S/ {Number(d.monto_devolucion).toFixed(2)}</span>,
            // Nota de crédito: dinero que sale/se descuenta → al Excel en negativo.
            exportValue: (d) => -Number(d.monto_devolucion ?? 0),
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (d) => (
                <div className="flex items-center gap-1.5 flex-wrap">
                    <Badge variant={ESTADO_VARIANT[d.estado]}>
                        {ESTADO_LABEL[d.estado]}
                    </Badge>
                    {/* Solo se marca lo que pide atención. Una NC emitida, en
                        espera o que no hacía falta no ensucia la lista: si todo
                        se marcara, la marca dejaría de significar algo. */}
                    {d.nota_credito_estado === 'fallida' && (
                        <Badge variant="danger">SIN N. CRÉDITO</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'user', label: 'Cajero',
            render: (d) => <span className="text-sm">{d.user?.name ?? '—'}</span>,
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (d) => (
                <div className="flex items-center gap-1">
                    <button type="button" onClick={() => router.visit(route('devoluciones.show', d.id))}
                        className="rounded-lg p-1.5" style={{ color: 'var(--color-primary)' }} title="Ver">
                        <Eye size={14} />
                    </button>

                    {d.estado === 'pendiente' && esAdmin && (
                        <>
                            <button type="button" onClick={() => aprobar(d.id)}
                                className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium"
                                style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' }}
                                title="Aprobar">
                                <CheckCircle size={12} />Aprobar
                            </button>
                            <button type="button" onClick={() => rechazar(d.id)}
                                className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }} title="Rechazar">
                                <XCircle size={14} />
                            </button>
                        </>
                    )}

                    {(d.estado === 'completada' || d.estado === 'aprobada') && (
                        <button type="button" onClick={() => anular(d.id)}
                            className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }} title="Anular">
                            <Ban size={14} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Devoluciones">
            <PageHeader
                title="Devoluciones"
                subtitle="Gestiona las devoluciones de ventas con sus motivos, restock y reembolsos"
                actions={
                    <Link href={route('devoluciones.create')}>
                        <Button><Plus size={15} className="mr-1" />Nueva devolución</Button>
                    </Link>
                }
            />

            {/* Una nota de crédito que no salió deja la declaración descuadrada:
                la devolución está hecha y SUNAT sigue viendo el importe original.
                Antes solo constaba en el log, así que nadie se enteraba. */}
            {ncFallidas > 0 && !viendoNcPendientes && (
                <Callout
                    variant="danger"
                    title={ncFallidas === 1
                        ? 'Hay 1 devolución sin su nota de crédito'
                        : `Hay ${ncFallidas} devoluciones sin su nota de crédito`}
                    className="mb-4"
                >
                    SUNAT sigue viendo declarado el importe original de esas ventas.
                    <span className="block mt-2">
                        <Button variant="danger" onClick={() => router.get(route('devoluciones.index'), { nc_pendiente: 1 })}>
                            Ver cuáles son
                        </Button>
                    </span>
                </Callout>
            )}

            <FiltrosCard
                cols={3}
                tieneFiltros={!!filtrEstado}
                onClear={() => {
                    setFiltrEstado('');
                    router.get(route('devoluciones.index'), { buscar: buscar || undefined }, { preserveState: true, replace: true });
                }}
            >
                <Select
                    label="Estado"
                    value={filtrEstado}
                    onChange={v => {
                        const val = String(v);
                        setFiltrEstado(val);
                        router.get(route('devoluciones.index'), { estado: val || undefined, buscar: buscar || undefined }, { preserveState: true, replace: true });
                    }}
                    options={[
                        { value: '',           label: 'Todos los estados' },
                        { value: 'pendiente',  label: 'Pendiente' },
                        { value: 'aprobada',   label: 'Aprobada' },
                        { value: 'completada', label: 'Completada' },
                        { value: 'rechazada',  label: 'Rechazada' },
                        { value: 'anulada',    label: 'Anulada' },
                    ]}
                />
            </FiltrosCard>

            <Table data={devoluciones.data} columns={columns} emptyMessage="No hay devoluciones registradas"
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('devoluciones.index'),
                    { ...filters, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })} />

            {/* Paginación (M19) */}
            {devoluciones.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: devoluciones.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('devoluciones.index'), { ...filters, buscar: buscar || undefined, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === devoluciones.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === devoluciones.current_page ? '#fff' : 'var(--color-text-muted)',
                            }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
