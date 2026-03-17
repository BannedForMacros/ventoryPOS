import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, CheckCircle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }
interface Transferencia extends Record<string, unknown> {
    id: number;
    fecha: string;
    almacen_origen: Almacen;
    almacen_destino: Almacen;
    user: { name: string };
    estado: 'borrador' | 'confirmado';
    observacion: string | null;
}

interface Props extends PageProps {
    transferencias: Transferencia[];
    almacenes: Almacen[];
    filters: Record<string, string>;
}

export default function TransferenciasIndex({ transferencias, almacenes, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [deleteId, setDeleteId]   = useState<number | null>(null);
    const [filtrEstado, setFiltrEstado] = useState(filters.estado ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function confirmar(id: number) {
        setConfirmId(null);
        router.post(route('inventario.transferencias.confirmar', id));
    }

    function eliminar(id: number) {
        setDeleteId(null);
        router.delete(route('inventario.transferencias.destroy', id));
    }

    const columns: Column<Transferencia>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (t) => <span className="text-sm">{t.fecha}</span>,
        },
        {
            key: 'almacen_origen', label: 'Origen', sortable: true,
            render: (t) => (
                <span className="text-sm">
                    {t.almacen_origen.nombre}
                    {t.almacen_origen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {t.almacen_origen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'almacen_destino', label: 'Destino', sortable: true,
            render: (t) => (
                <span className="text-sm">
                    {t.almacen_destino.nombre}
                    {t.almacen_destino.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {t.almacen_destino.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'user', label: 'Registrado por', sortable: false,
            render: (t) => <span className="text-sm">{t.user.name}</span>,
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (t) => (
                <Badge variant={t.estado === 'confirmado' ? 'success' : 'warning'}>
                    {t.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (t) => (
                <div className="flex items-center gap-1">
                    {t.estado === 'borrador' && (
                        <button type="button" onClick={() => setConfirmId(t.id)}
                            className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                            style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' }}>
                            <CheckCircle size={13} />Confirmar
                        </button>
                    )}
                    <TableActions
                        onDelete={t.estado === 'borrador' ? () => setDeleteId(t.id) : undefined}
                    />
                </div>
            ),
        },
    ];

    const filtered = transferencias.filter(t =>
        !filtrEstado || t.estado === filtrEstado
    );

    return (
        <AppLayout title="Transferencias">
            <PageHeader
                title="Transferencias de stock"
                subtitle="Movimientos entre almacenes"
                actions={
                    <Link href={route('inventario.transferencias.create')}>
                        <Button><Plus size={15} className="mr-1 flex-shrink-0" />Nueva transferencia</Button>
                    </Link>
                }
            />

            <div className="mb-4 flex flex-wrap gap-3">
                <div className="w-44">
                    <Select
                        placeholder="Todos los estados"
                        value={filtrEstado}
                        onChange={v => setFiltrEstado(String(v))}
                        options={[
                            { value: '',           label: 'Todos los estados' },
                            { value: 'borrador',   label: 'Borrador' },
                            { value: 'confirmado', label: 'Confirmado' },
                        ]}
                    />
                </div>
                {filtrEstado && (
                    <Button variant="ghost" onClick={() => setFiltrEstado('')}>Limpiar</Button>
                )}
            </div>

            <Table data={filtered} columns={columns} emptyMessage="No hay transferencias registradas" />

            <Modal isOpen={confirmId !== null} onClose={() => setConfirmId(null)}
                title="Confirmar transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="success" onClick={() => confirmId && confirmar(confirmId)}>
                            Confirmar transferencia
                        </Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se descontará el stock del almacén origen y se sumará al destino. Esta acción es <strong>irreversible</strong>.
                    Si no hay stock suficiente en el origen, la operación será rechazada.
                </p>
            </Modal>

            <Modal isOpen={deleteId !== null} onClose={() => setDeleteId(null)}
                title="Eliminar transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDeleteId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => deleteId && eliminar(deleteId)}>Eliminar</Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se eliminará la transferencia en borrador.
                </p>
            </Modal>
        </AppLayout>
    );
}
