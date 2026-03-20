import { useEffect } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Eye, ShoppingCart } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import type { Local, PageProps, Venta } from '@/types';

interface Paginado<T> { data: T[]; total: number; }

interface Filters {
    estado?:      string;
    fecha_desde?: string;
    fecha_hasta?: string;
    local_id?:    string;
}

interface Props extends PageProps {
    ventas:  Paginado<Venta>;
    locales: Local[];
    filters: Filters;
}

export default function VentasIndex({ ventas, locales, filters, flash }: Props) {
    const { auth } = usePage<Props>().props;
    const esAdmin  = auth.user.rol?.es_admin ?? false;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('ventas.index'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    const columns: Column<Venta>[] = [
        { key: 'numero',        label: 'N°',        render: v => <span className="font-mono font-semibold">{v.numero}</span> },
        { key: 'fecha_venta',   label: 'Fecha',      render: v => new Date(v.fecha_venta).toLocaleString('es-PE') },
        { key: 'cliente',       label: 'Cliente',    render: v => v.cliente ? ((v.cliente as any).razon_social ?? `${(v.cliente as any).nombres} ${(v.cliente as any).apellidos ?? ''}`.trim()) : 'General' },
        { key: 'tipo_comprobante', label: 'Comprobante', render: v => <span className="capitalize">{v.tipo_comprobante}</span> },
        {
            key: 'estado',
            label: 'Estado',
            render: v => (
                <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                    {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                </Badge>
            ),
        },
        { key: 'total', label: 'Total', render: v => <span className="font-bold">S/ {parseFloat(v.total).toFixed(2)}</span> },
        {
            key: 'id',
            label: '',
            render: v => (
                <Link
                    href={route('ventas.show', v.id)}
                    className="flex items-center gap-1 text-xs px-2 py-1 rounded hover:bg-black/5 transition-colors"
                    style={{ color: 'var(--color-primary)' }}
                >
                    <Eye size={13} /> Ver
                </Link>
            ),
        },
    ];

    return (
        <AppLayout title="Ventas">
            <PageHeader
                title="Ventas"
                subtitle={`${ventas.total} registros`}
                actions={
                    <Link href={route('pos.index')}>
                        <Button variant="primary" startContent={<ShoppingCart size={16} />}>
                            Ir al POS
                        </Button>
                    </Link>
                }
            />

            {/* Filtros */}
            <div
                className="flex flex-wrap gap-3 mb-4 p-3 rounded-lg"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
            >
                <select
                    value={filters.estado ?? ''}
                    onChange={e => filtrar({ estado: e.target.value || undefined })}
                    className="text-sm border rounded-lg px-2 py-1.5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                >
                    <option value="">Todos los estados</option>
                    <option value="completada">Completadas</option>
                    <option value="anulada">Anuladas</option>
                </select>

                <input
                    type="date"
                    value={filters.fecha_desde ?? ''}
                    onChange={e => filtrar({ fecha_desde: e.target.value || undefined })}
                    className="text-sm border rounded-lg px-2 py-1.5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                />
                <input
                    type="date"
                    value={filters.fecha_hasta ?? ''}
                    onChange={e => filtrar({ fecha_hasta: e.target.value || undefined })}
                    className="text-sm border rounded-lg px-2 py-1.5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                />

                {esAdmin && locales.length > 1 && (
                    <select
                        value={filters.local_id ?? ''}
                        onChange={e => filtrar({ local_id: e.target.value || undefined })}
                        className="text-sm border rounded-lg px-2 py-1.5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                    >
                        <option value="">Todos los locales</option>
                        {locales.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                    </select>
                )}
            </div>

            <Table data={ventas.data} columns={columns} />
        </AppLayout>
    );
}
