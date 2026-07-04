import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { CalendarDays, ArrowRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface Balance extends Record<string, unknown> {
    id: number;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    total_favor: string;
    total_contra: string;
    balance_neto: string;
    diferencia: string | null;
    gastos_dia: string;
    utilidad_real: string | null;
    user?: { name: string } | null;
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    balances: Paginado<Balance>;
    hoy: string;
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const signed = (v: unknown) => {
    const n = Number(v ?? 0);
    return (
        <span className="font-bold" style={{ color: n >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}>
            {n >= 0 ? '+' : ''}{money(n).replace('S/ ', 'S/ ')}
        </span>
    );
};

export default function BalanceDiario({ balances, hoy }: Props) {
    const { flash } = usePage<Props>().props;
    const [fecha, setFecha] = useState(hoy);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const columns: Column<Balance>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (b) => <span className="font-medium">{new Date(b.fecha + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })}</span>,
        },
        {
            key: 'estado', label: 'Estado',
            render: (b) => (
                <Badge variant={b.estado === 'confirmado' ? 'success' : 'warning'}>
                    {b.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        { key: 'total_favor',  label: 'A favor',   render: (b) => <span style={{ color: 'var(--color-success)' }}>{money(b.total_favor)}</span> },
        { key: 'total_contra', label: 'En contra', render: (b) => <span style={{ color: 'var(--color-danger)' }}>{money(b.total_contra)}</span> },
        { key: 'balance_neto', label: 'Balance',   render: (b) => <span className="font-bold">{money(b.balance_neto)}</span> },
        {
            key: 'diferencia', label: 'vs. ayer',
            render: (b) => b.diferencia !== null ? signed(b.diferencia) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        { key: 'gastos_dia', label: 'Gastos', render: (b) => <span>{money(b.gastos_dia)}</span> },
        {
            key: 'utilidad_real', label: 'Utilidad real',
            render: (b) => b.utilidad_real !== null ? signed(b.utilidad_real) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'acciones', label: '',
            render: (b) => (
                <Button variant="ghost" onClick={() => router.visit(route('finanzas.balance.show', b.fecha))}>
                    Abrir<ArrowRight size={14} className="ml-1" />
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Balance diario">
            <PageHeader
                title="Balance diario"
                subtitle="Foto patrimonial del negocio: a favor − en contra, comparado con el día anterior"
                actions={
                    <div className="flex items-center gap-2">
                        <input
                            type="date"
                            value={fecha}
                            onChange={e => setFecha(e.target.value)}
                            className="text-sm rounded-lg px-3 py-2 border outline-none"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                            }}
                        />
                        <Button onClick={() => fecha && router.visit(route('finanzas.balance.show', fecha))}>
                            <CalendarDays size={15} className="mr-1 flex-shrink-0" />
                            {fecha === hoy ? 'Balance de hoy' : 'Abrir balance'}
                        </Button>
                    </div>
                }
            />

            <Table
                data={balances.data}
                columns={columns}
                searchPlaceholder="Buscar fecha..."
                emptyMessage="Aún no hay balances. Genera el balance de hoy con el botón de arriba."
            />
        </AppLayout>
    );
}
