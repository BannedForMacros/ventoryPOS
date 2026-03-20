import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import TableActions from '@/Components/UI/TableActions';
import Tabs from '@/Components/UI/Tabs';
import Modal from '@/Components/UI/Modal';
import ModalGasto from './Partials/ModalGasto';
import type { Gasto, GastoTipo, Local, PageProps } from '@/types';

type Scope = 'turno' | 'administrativo';

interface Paginado<T> {
    data:  T[];
    total: number;
}

interface Props extends PageProps {
    gastos:  Paginado<Gasto>;
    tipos:   GastoTipo[];
    scope:   Scope;
    locales: Local[];
}

const TABS = [
    { value: 'turno' as Scope,          label: 'Gastos del turno' },
    { value: 'administrativo' as Scope, label: 'Gastos administrativos' },
];

export default function GastosIndex({ gastos, tipos, scope, locales }: Props) {
    const { flash, turno_activo, auth } = usePage<Props>().props;
    const [tab, setTab]                 = useState<Scope>(scope);
    const [modalGasto, setModalGasto]   = useState(false);
    const [confirmId, setConfirmId]     = useState<number | null>(null);

    const esAdmin = auth.user.rol?.es_admin ?? false;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function handleTabChange(value: Scope) {
        setTab(value);
        router.get(route('gastos.index'), { scope: value }, { preserveState: true, replace: true });
    }

    function deleteGasto(id: number) {
        setConfirmId(null);
        router.delete(route('gastos.destroy', id));
    }

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
            render: (g) => (
                <TableActions onDelete={() => setConfirmId(g.id)} />
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
                        onClick={() => setModalGasto(true)}
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

            <div className="mb-5">
                <Tabs tabs={TABS} value={tab} onChange={handleTabChange} />
            </div>

            <Table
                data={gastos.data}
                columns={columns}
                searchPlaceholder="Buscar gasto..."
                emptyMessage={
                    tab === 'turno'
                        ? 'No hay gastos en este turno'
                        : 'No hay gastos administrativos registrados'
                }
            />

            {/* Modal nuevo gasto */}
            <ModalGasto
                isOpen={modalGasto}
                onClose={() => setModalGasto(false)}
                tipos={tipos}
                turnoActivo={tab === 'turno' ? (turno_activo ?? null) : null}
                locales={locales}
                esAdmin={esAdmin}
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
                    ¿Confirmas que deseas eliminar este gasto? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
