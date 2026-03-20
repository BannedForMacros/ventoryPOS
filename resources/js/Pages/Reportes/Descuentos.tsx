import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import type { DescuentoConcepto, DescuentoLog, PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; }

interface Filters {
    concepto_id?: string;
    user_id?:     string;
    fecha_desde?: string;
    fecha_hasta?: string;
}

interface Props extends PageProps {
    logs:      Paginado<DescuentoLog>;
    conceptos: DescuentoConcepto[];
    filters:   Filters;
}

export default function ReportesDescuentos({ logs, conceptos, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.descuentos'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    const columns: Column<DescuentoLog>[] = [
        {
            key: 'created_at',
            label: 'Fecha',
            render: l => new Date(l.created_at).toLocaleString('es-PE'),
        },
        {
            key: 'concepto',
            label: 'Concepto',
            render: l => (l.concepto as any)?.nombre ?? '—',
        },
        {
            key: 'monto_descuento',
            label: 'Monto',
            render: l => <span className="font-semibold text-red-500">-S/ {parseFloat(l.monto_descuento).toFixed(2)}</span>,
        },
        {
            key: 'venta',
            label: 'Venta',
            render: l => l.venta ? <span className="font-mono">{(l.venta as any).numero}</span> : '—',
        },
        {
            key: 'user',
            label: 'Vendedor',
            render: l => (l.user as any)?.name ?? '—',
        },
        {
            key: 'requeria_aprobacion',
            label: 'Aprobación',
            render: l => (
                <Badge variant={l.requeria_aprobacion ? 'warning' : 'secondary'}>
                    {l.requeria_aprobacion ? 'Sí' : 'No'}
                </Badge>
            ),
        },
        {
            key: 'notificacion_enviada',
            label: 'Notif.',
            render: l => l.notificacion_enviada
                ? <Badge variant="success">Enviada</Badge>
                : <Badge variant="secondary">—</Badge>,
        },
    ];

    const totalDescuentos = logs.data.reduce((s, l) => s + parseFloat(l.monto_descuento), 0);

    return (
        <AppLayout title="Reporte de descuentos">
            <PageHeader
                title="Reporte de descuentos"
                subtitle={`${logs.total} registros · Total descontado: S/ ${totalDescuentos.toFixed(2)}`}
            />

            {/* Filtros */}
            <div
                className="flex flex-wrap gap-3 mb-4 p-3 rounded-lg"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
            >
                <select
                    value={filters.concepto_id ?? ''}
                    onChange={e => filtrar({ concepto_id: e.target.value || undefined })}
                    className="text-sm border rounded-lg px-2 py-1.5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                >
                    <option value="">Todos los conceptos</option>
                    {conceptos.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
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
            </div>

            <Table data={logs.data} columns={columns} />
        </AppLayout>
    );
}
