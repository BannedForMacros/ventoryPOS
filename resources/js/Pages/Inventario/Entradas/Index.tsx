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
interface UserItem { id: number; name: string; }
interface Entrada extends Record<string, unknown> {
    id: number;
    fecha: string;
    tipo: string;
    proveedor: string | null;
    numero_documento: string | null;
    almacen: Almacen;
    user: UserItem;
    total: string;
    estado: 'borrador' | 'confirmado';
}

interface Props extends PageProps {
    entradas: Entrada[];
    almacenes: Almacen[];
    mostrarSelector: boolean;
    filters: Record<string, string>;
}

const TIPOS: Record<string, string> = {
    compra: 'Compra', ajuste: 'Ajuste', devolucion: 'Devolución', otro: 'Otro',
};

export default function EntradasIndex({ entradas, almacenes, mostrarSelector, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [confirmId, setConfirmId]   = useState<number | null>(null);
    const [deleteId, setDeleteId]     = useState<number | null>(null);
    const [filtrAlmacen, setFiltrAlmacen] = useState(filters.almacen_id ?? '');
    const [filtrEstado, setFiltrEstado]   = useState(filters.estado ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function confirmar(id: number) {
        setConfirmId(null);
        router.post(route('inventario.entradas.confirmar', id));
    }

    function eliminar(id: number) {
        setDeleteId(null);
        router.delete(route('inventario.entradas.destroy', id));
    }

    const columns: Column<Entrada>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (e) => <span className="text-sm">{e.fecha}</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (e) => <Badge variant="primary">{TIPOS[e.tipo] ?? e.tipo}</Badge>,
        },
        {
            key: 'almacen', label: 'Almacén', sortable: true,
            render: (e) => (
                <span className="text-sm">
                    {e.almacen.nombre}
                    {e.almacen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {e.almacen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'proveedor', label: 'Proveedor', sortable: true,
            render: (e) => e.proveedor
                ? <span className="text-sm">{e.proveedor}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'numero_documento', label: 'Nro. doc.', sortable: true,
            render: (e) => e.numero_documento
                ? <span className="font-mono text-xs">{e.numero_documento}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'total', label: 'Total', sortable: true,
            render: (e) => <span className="font-mono text-sm">S/ {Number(e.total).toFixed(2)}</span>,
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (e) => (
                <Badge variant={e.estado === 'confirmado' ? 'success' : 'warning'}>
                    {e.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (e) => (
                <div className="flex items-center gap-1">
                    {e.estado === 'borrador' && (
                        <button
                            type="button"
                            onClick={() => setConfirmId(e.id)}
                            className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                            style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' }}
                            title="Confirmar entrada"
                        >
                            <CheckCircle size={13} />Confirmar
                        </button>
                    )}
                    <TableActions
                        onEdit={e.estado === 'borrador' ? () => router.visit(route('inventario.entradas.edit', e.id)) : undefined}
                        onDelete={e.estado === 'borrador' ? () => setDeleteId(e.id) : undefined}
                    />
                </div>
            ),
        },
    ];

    const filtered = entradas.filter(e => {
        if (filtrAlmacen && e.almacen.id !== Number(filtrAlmacen)) return false;
        if (filtrEstado  && e.estado !== filtrEstado) return false;
        return true;
    });

    return (
        <AppLayout title="Entradas">
            <PageHeader
                title="Entradas de inventario"
                subtitle="Registro de ingresos de mercadería"
                actions={
                    <Link href={route('inventario.entradas.create')}>
                        <Button><Plus size={15} className="mr-1 flex-shrink-0" />Nueva entrada</Button>
                    </Link>
                }
            />

            <div className="mb-4 flex flex-wrap gap-3">
                {mostrarSelector && (
                    <div className="w-52">
                        <Select
                            placeholder="Todos los almacenes"
                            value={filtrAlmacen}
                            onChange={v => setFiltrAlmacen(String(v))}
                            options={[
                                { value: '', label: 'Todos los almacenes' },
                                ...almacenes.map(a => ({ value: a.id, label: a.nombre })),
                            ]}
                        />
                    </div>
                )}
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
                {(filtrAlmacen || filtrEstado) && (
                    <Button variant="ghost" onClick={() => { setFiltrAlmacen(''); setFiltrEstado(''); }}>
                        Limpiar filtros
                    </Button>
                )}
            </div>

            <Table
                data={filtered}
                columns={columns}
                searchPlaceholder="Buscar por proveedor o documento..."
                emptyMessage="No hay entradas registradas"
            />

            {/* Modal confirmar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Confirmar entrada"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="success" onClick={() => confirmId && confirmar(confirmId)}>
                            Confirmar y actualizar stock
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Al confirmar, el stock se actualizará automáticamente y el costo promedio se recalculará.
                    Esta acción es <strong>irreversible</strong>.
                </p>
            </Modal>

            {/* Modal eliminar */}
            <Modal
                isOpen={deleteId !== null}
                onClose={() => setDeleteId(null)}
                title="Eliminar entrada"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDeleteId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => deleteId && eliminar(deleteId)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se eliminará la entrada en borrador. Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
