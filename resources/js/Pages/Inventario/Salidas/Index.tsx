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
interface TipoSalida { id: number; nombre: string; slug: string; }

interface Salida extends Record<string, unknown> {
    id: number;
    fecha: string;
    numero_documento: string | null;
    almacen: Almacen;
    user: UserItem;
    tipo: TipoSalida;
    total: string;
    estado: 'borrador' | 'confirmado';
    turno_id: number | null;
}

// M19: paginado server-side.
interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Props extends PageProps {
    salidas: Paginado<Salida>;
    almacenes: Almacen[];
    tipos: TipoSalida[];
    mostrarSelector: boolean;
    filters: Record<string, string>;
}

export default function SalidasIndex({ salidas, almacenes, tipos, mostrarSelector, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [confirmId, setConfirmId]   = useState<number | null>(null);
    const [deleteId, setDeleteId]     = useState<number | null>(null);
    const [filtrAlmacen, setFiltrAlmacen] = useState(filters.almacen_id ?? '');
    const [filtrTipo, setFiltrTipo]   = useState(filters.salida_tipo_id ?? '');
    const [filtrEstado, setFiltrEstado] = useState(filters.estado ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function confirmar(id: number) {
        setConfirmId(null);
        router.post(route('inventario.salidas.confirmar', id));
    }

    function eliminar(id: number) {
        setDeleteId(null);
        router.delete(route('inventario.salidas.destroy', id));
    }

    const columns: Column<Salida>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (s) => <span className="text-sm">{s.fecha}</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (s) => <Badge variant="primary">{s.tipo?.nombre ?? '—'}</Badge>,
        },
        {
            key: 'almacen', label: 'Almacén', sortable: true,
            render: (s) => (
                <span className="text-sm">
                    {s.almacen.nombre}
                    {s.almacen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {s.almacen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'user', label: 'Responsable',
            render: (s) => <span className="text-sm">{s.user.name}</span>,
        },
        {
            key: 'numero_documento', label: 'Nro. doc.',
            render: (s) => s.numero_documento
                ? <span className="font-mono text-xs">{s.numero_documento}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'turno_id', label: 'Origen',
            render: (s) => s.turno_id
                ? <Badge variant="secondary">Turno #{s.turno_id}</Badge>
                : <Badge variant="secondary">Ad-hoc</Badge>,
        },
        {
            key: 'total', label: 'Total valorizado',
            render: (s) => <span className="font-mono text-sm">S/ {Number(s.total).toFixed(2)}</span>,
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (s) => (
                <Badge variant={s.estado === 'confirmado' ? 'success' : 'warning'}>
                    {s.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (s) => (
                <div className="flex items-center gap-1">
                    {s.estado === 'borrador' && (
                        <button
                            type="button"
                            onClick={() => setConfirmId(s.id)}
                            className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                            style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' }}
                            title="Confirmar salida"
                        >
                            <CheckCircle size={13} />Confirmar
                        </button>
                    )}
                    <TableActions
                        onEdit={s.estado === 'borrador' ? () => router.visit(route('inventario.salidas.edit', s.id)) : undefined}
                        onDelete={s.estado === 'borrador' ? () => setDeleteId(s.id) : undefined}
                    />
                </div>
            ),
        },
    ];

    const filtered = salidas.data.filter(s => {
        if (filtrAlmacen && s.almacen.id !== Number(filtrAlmacen)) return false;
        if (filtrTipo    && s.tipo?.id !== Number(filtrTipo))      return false;
        if (filtrEstado  && s.estado !== filtrEstado)              return false;
        return true;
    });

    return (
        <AppLayout title="Salidas de inventario">
            <PageHeader
                title="Salidas de inventario"
                subtitle="Mermas, ajustes, bajas, consumo interno y otros egresos de almacén"
                actions={
                    <Link href={route('inventario.salidas.create')}>
                        <Button><Plus size={15} className="mr-1" />Nueva salida</Button>
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
                <div className="w-52">
                    <Select
                        placeholder="Todos los tipos"
                        value={filtrTipo}
                        onChange={v => setFiltrTipo(String(v))}
                        options={[
                            { value: '', label: 'Todos los tipos' },
                            ...tipos.map(t => ({ value: t.id, label: t.nombre })),
                        ]}
                    />
                </div>
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
                {(filtrAlmacen || filtrTipo || filtrEstado) && (
                    <Button variant="ghost" onClick={() => { setFiltrAlmacen(''); setFiltrTipo(''); setFiltrEstado(''); }}>
                        Limpiar filtros
                    </Button>
                )}
            </div>

            <Table
                data={filtered}
                columns={columns}
                searchPlaceholder="Buscar por documento..."
                emptyMessage="No hay salidas registradas"
            />

            {/* Paginación (M19) */}
            {salidas.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: salidas.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('inventario.salidas.index'), { page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === salidas.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === salidas.current_page ? '#fff' : 'var(--color-text-muted)',
                            }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}

            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Confirmar salida"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="success" onClick={() => confirmId && confirmar(confirmId)}>
                            Confirmar y descontar stock
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Al confirmar, el stock se descontará automáticamente y se capturará el costo unitario actual del producto.
                    Esta acción es <strong>irreversible</strong>.
                </p>
            </Modal>

            <Modal
                isOpen={deleteId !== null}
                onClose={() => setDeleteId(null)}
                title="Eliminar salida"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDeleteId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => deleteId && eliminar(deleteId)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se eliminará la salida en borrador. Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
