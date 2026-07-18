import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, RotateCcw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import TableActions from '@/Components/UI/TableActions';
import Tabs from '@/Components/UI/Tabs';
import Modal from '@/Components/UI/Modal';
import ModalGasto from './Partials/ModalGasto';
import type { Gasto, GastoTipo, Local, MetodoPagoConCuentas, PageProps, Turno } from '@/types';

type Scope = 'turno' | 'administrativo';
type Mostrar = 'activos' | 'eliminados';

interface Paginado<T> {
    data:  T[];
    total: number;
}

interface Props extends PageProps {
    gastos:          Paginado<Gasto>;
    tipos:           GastoTipo[];
    scope:           Scope;
    mostrar:         Mostrar;
    buscar?:         string;
    locales:         Local[];
    turnosAbiertos:  Turno[];
    esAdmin:         boolean;
    metodosPago:     MetodoPagoConCuentas[];
}

const ALL_TABS = [
    { value: 'turno' as Scope,          label: 'Gastos del turno' },
    { value: 'administrativo' as Scope, label: 'Gastos administrativos' },
];

const MOSTRAR_TABS = [
    { value: 'activos' as Mostrar,    label: 'Activos' },
    { value: 'eliminados' as Mostrar, label: 'Eliminados' },
];

export default function GastosIndex({ gastos, tipos, scope, mostrar, buscar, locales, turnosAbiertos, esAdmin, metodosPago }: Props) {
    const { flash, turno_activo } = usePage<Props>().props;
    const [tab, setTab]                 = useState<Scope>(scope);
    const [modalGasto, setModalGasto]   = useState(false);
    const [gastoEditar, setGastoEditar] = useState<Gasto | null>(null);
    const [confirmId, setConfirmId]     = useState<number | null>(null);

    const TABS = esAdmin ? ALL_TABS : ALL_TABS.filter(t => t.value !== 'administrativo');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function handleTabChange(value: Scope) {
        setTab(value);
        router.get(route('gastos.index'), { scope: value, mostrar, buscar: buscar || undefined }, { preserveState: true, replace: true });
    }

    function handleMostrarChange(value: Mostrar) {
        router.get(route('gastos.index'), { scope: tab, mostrar: value, buscar: buscar || undefined }, { preserveState: true, replace: true });
    }

    function deleteGasto(id: number) {
        setConfirmId(null);
        router.delete(route('gastos.destroy', id), { preserveScroll: true });
    }

    function restaurarGasto(id: number) {
        router.post(route('gastos.restore', id), {}, { preserveScroll: true });
    }

    function abrirNuevo() {
        setGastoEditar(null);
        setModalGasto(true);
    }

    function abrirEditar(g: Gasto) {
        setGastoEditar(g);
        setModalGasto(true);
    }

    // Editar/eliminar/reactivar: admin con cualquiera; no-admin solo sus gastos
    // de turno mientras el turno siga abierto (igual que valida el backend).
    const puedeModificar = (g: Gasto) =>
        esAdmin || (g.turno_id !== null && (g.turno as any)?.estado === 'abierto');

    const puedeNuevoTurno   = !!turno_activo;
    const puedeNuevoAdmin   = esAdmin;

    const columns: Column<Gasto>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
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
        ...(tab === 'turno' ? [] : [{
            key: 'local', label: 'Local',
            render: (g: Gasto) => <span>{g.local?.nombre ?? '—'}</span>,
        } as Column<Gasto>]),
        {
            key: 'monto', label: 'Monto',
            render: (g) => (
                <span className="font-semibold">S/ {parseFloat(g.monto).toFixed(2)}</span>
            ),
        },
        {
            key: 'user', label: 'Registrado por',
            render: (g) => (
                <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    {g.user?.name ?? '—'}
                </span>
            ),
        },
        {
            key: 'comentario', label: 'Comentario',
            render: (g) => g.comentario
                ? <span className="text-sm">{g.comentario as string}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (g) => mostrar === 'eliminados' ? (
                <TableActions extra={[{
                    variant: 'custom',
                    icon: RotateCcw,
                    label: 'Reactivar',
                    onClick: () => restaurarGasto(g.id),
                    disabled: !puedeModificar(g),
                }]} />
            ) : (
                <TableActions
                    onEdit={() => abrirEditar(g)}
                    onDelete={() => setConfirmId(g.id)}
                    disableEdit={!puedeModificar(g)}
                    disableDelete={!puedeModificar(g)}
                />
            ),
        },
    ];

    const puedeAgregarGasto = tab === 'turno' ? puedeNuevoTurno : puedeNuevoAdmin;

    return (
        <AppLayout title="Gastos">
            <PageHeader
                title="Gastos"
                subtitle="Registro de gastos del negocio"
                actions={
                    <Button
                        onClick={abrirNuevo}
                        disabled={!puedeAgregarGasto}
                        title={
                            tab === 'turno' && !puedeNuevoTurno
                                ? 'Debes tener un turno abierto'
                                : tab === 'administrativo' && !puedeNuevoAdmin
                                    ? 'Solo administradores pueden crear gastos administrativos'
                                    : undefined
                        }
                    >
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo gasto
                    </Button>
                }
            />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <Tabs tabs={TABS} value={tab} onChange={handleTabChange} />
                <Tabs tabs={MOSTRAR_TABS} value={mostrar} onChange={handleMostrarChange} />
            </div>

            <Table
                data={gastos}
                columns={columns}
                searchPlaceholder="Buscar gasto..."
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('gastos.index'),
                    { scope: tab, mostrar, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })}
                emptyMessage={
                    mostrar === 'eliminados'
                        ? 'No hay gastos eliminados'
                        : tab === 'turno'
                            ? 'No hay gastos en este turno'
                            : 'No hay gastos administrativos registrados'
                }
            />

            {/* Modal nuevo / editar gasto */}
            <ModalGasto
                isOpen={modalGasto}
                onClose={() => { setModalGasto(false); setGastoEditar(null); }}
                tipos={tipos}
                turnoActivo={tab === 'turno' ? (turno_activo ?? null) : null}
                locales={locales}
                esAdmin={esAdmin}
                turnosAbiertos={turnosAbiertos ?? []}
                metodosPago={metodosPago ?? []}
                gastoEditar={gastoEditar}
            />

            {/* Confirmar eliminar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Eliminar gasto"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && deleteGasto(confirmId)}>
                            Eliminar
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    ¿Confirmas que deseas eliminar este gasto? Se revertirá su egreso de tesorería.
                    Podrás verlo en el filtro «Eliminados» y reactivarlo si te equivocaste.
                </p>
            </Modal>
        </AppLayout>
    );
}
