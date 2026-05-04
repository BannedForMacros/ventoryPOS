import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, ClipboardCheck } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }
interface UserItem { id: number; name: string; }

interface Cierre extends Record<string, unknown> {
    id: number;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    total_items: number;
    total_diferencias: number;
    turno_id: number | null;
    almacen: Almacen;
    user: UserItem;
    observacion: string | null;
}

interface Props extends PageProps {
    cierres: Cierre[];
    almacenes: Almacen[];
    mostrarSelector: boolean;
    filters: Record<string, string>;
}

export default function CierresIndex({ cierres, almacenes, mostrarSelector, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [filtrAlmacen, setFiltrAlmacen] = useState(filters.almacen_id ?? '');
    const [filtrEstado, setFiltrEstado]   = useState(filters.estado ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function aplicarFiltros() {
        router.get(route('inventario.cierres.index'), {
            almacen_id: filtrAlmacen || undefined,
            estado: filtrEstado || undefined,
        }, { preserveState: true, replace: true });
    }

    const columns: Column<Cierre>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (c) => <span className="text-sm">{c.fecha}</span>,
        },
        {
            key: 'almacen', label: 'Almacén', sortable: true,
            render: (c) => (
                <span className="text-sm">
                    {c.almacen.nombre}
                    {c.almacen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {c.almacen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'user', label: 'Responsable',
            render: (c) => <span className="text-sm">{c.user.name}</span>,
        },
        {
            key: 'total_items', label: 'Productos',
            render: (c) => <span className="text-sm">{c.total_items}</span>,
        },
        {
            key: 'total_diferencias', label: 'Diferencias',
            render: (c) => c.total_diferencias > 0
                ? <Badge variant="warning">{c.total_diferencias}</Badge>
                : <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>0</span>,
        },
        {
            key: 'turno_id', label: 'Origen',
            render: (c) => c.turno_id
                ? <Badge variant="primary">Turno #{c.turno_id}</Badge>
                : <Badge variant="secondary">Ad-hoc</Badge>,
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (c) => (
                <Badge variant={c.estado === 'confirmado' ? 'success' : 'secondary'}>
                    {c.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (c) => (
                <TableActions
                    onView={() => router.visit(route('inventario.cierres.show', c.id))}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Cierres de inventario">
            <PageHeader
                title="Cierres de inventario"
                subtitle="Declara stock real y registra diferencias contra el sistema"
                actions={
                    <Link href={route('inventario.cierres.create')}>
                        <Button>
                            <Plus size={15} className="mr-1" /> Nuevo cierre
                        </Button>
                    </Link>
                }
            />

            {(mostrarSelector || true) && (
                <div className="mb-4 flex flex-wrap gap-3 items-end">
                    {mostrarSelector && (
                        <div className="min-w-[220px]">
                            <Select
                                label="Almacén"
                                value={filtrAlmacen}
                                onChange={v => setFiltrAlmacen(String(v))}
                                options={[
                                    { value: '', label: 'Todos' },
                                    ...almacenes.map(a => ({ value: String(a.id), label: a.nombre })),
                                ]}
                            />
                        </div>
                    )}
                    <div className="min-w-[180px]">
                        <Select
                            label="Estado"
                            value={filtrEstado}
                            onChange={v => setFiltrEstado(String(v))}
                            options={[
                                { value: '',           label: 'Todos' },
                                { value: 'borrador',   label: 'Borrador' },
                                { value: 'confirmado', label: 'Confirmado' },
                            ]}
                        />
                    </div>
                    <Button variant="ghost" onClick={aplicarFiltros}>Filtrar</Button>
                </div>
            )}

            <Table
                data={cierres}
                columns={columns}
                searchPlaceholder="Buscar..."
                emptyMessage="No hay cierres de inventario registrados"
            />

            {cierres.length === 0 && (
                <div className="mt-6 flex items-center gap-3 rounded-2xl border p-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <ClipboardCheck size={20} style={{ color: 'var(--color-text-muted)' }} />
                    <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                        Cuando hagas un cierre, declaras el stock real contado y el sistema registra las diferencias contra lo que tenía calculado. Las diferencias se aplican automáticamente como ajuste de stock al confirmar.
                    </p>
                </div>
            )}
        </AppLayout>
    );
}
