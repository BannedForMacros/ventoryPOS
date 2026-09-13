import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { CalendarDays, ArrowRight, Scale, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import CambiosCierreModal from '@/Components/Finanzas/CambiosCierreModal';
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

interface Verificacion { fecha: string; diferencia: number; relevante: boolean; verificable: boolean; }

export default function BalanceDiario({ balances, hoy }: Props) {
    const { flash } = usePage<Props>().props;
    const [fecha, setFecha] = useState(hoy);
    // Días cerrados que cambiaron después del cierre (se piden aparte para no
    // demorar la carga de la lista).
    const [verificacion, setVerificacion] = useState<Record<number, Verificacion>>({});
    const [cambiosFecha, setCambiosFecha] = useState<string | null>(null);

    useEffect(() => {
        const ids = balances.data.filter(b => b.estado === 'confirmado').map(b => b.id);
        if (!ids.length) return;
        let vigente = true;
        fetch(route('finanzas.balance.verificacion', { ids: ids.join(',') }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => (r.ok ? r.json() : {}))
            .then(d => { if (vigente) setVerificacion(d); })
            .catch(() => {});
        return () => { vigente = false; };
    }, [balances.data]);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const columns: Column<Balance>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (b) => {
                const v = verificacion[b.id];
                return (
                    <div>
                        <span className="font-medium">{new Date(b.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })}</span>
                        {v?.relevante && (
                            <button
                                onClick={(e) => { e.stopPropagation(); setCambiosFecha(b.fecha.slice(0, 10)); }}
                                className="mt-0.5 flex items-center gap-1 text-[11px] font-semibold"
                                style={{ color: 'var(--color-warning, #d97706)' }}
                                title="Se registraron o corrigieron datos de este día después de cerrarlo. El día no se modifica."
                            >
                                <AlertTriangle size={11} />
                                Cambió después del cierre: {v.diferencia > 0 ? '+' : '−'}{money(Math.abs(v.diferencia))}
                            </button>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'estado', label: 'Estado',
            render: (b) => (
                <Badge variant={b.estado === 'confirmado' ? 'success' : 'warning'}>
                    {b.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        { key: 'total_favor',  label: 'A favor',   align: 'right', render: (b) => <span style={{ color: 'var(--color-success)' }}>{money(b.total_favor)}</span> },
        { key: 'total_contra', label: 'En contra', align: 'right', render: (b) => <span style={{ color: 'var(--color-danger)' }}>{money(b.total_contra)}</span> },
        { key: 'balance_neto', label: 'Balance',   align: 'right', render: (b) => <span className="font-bold">{money(b.balance_neto)}</span> },
        {
            key: 'diferencia', label: 'vs. ayer', align: 'right',
            render: (b) => b.diferencia !== null ? signed(b.diferencia) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        { key: 'gastos_dia', label: 'Gastos', align: 'right', render: (b) => <span>{money(b.gastos_dia)}</span> },
        {
            key: 'utilidad_real', label: 'Utilidad real', align: 'right',
            render: (b) => b.utilidad_real !== null ? signed(b.utilidad_real) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'acciones', label: '',
            render: (b) => (
                <Button variant="ghost" onClick={() => router.visit(route('finanzas.balance.show', b.fecha.slice(0, 10)))}>
                    Abrir<ArrowRight size={14} className="ml-1" />
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Balance diario">
            <PageHeader
                icon={<Scale size={22} />}
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
                data={balances}
                columns={columns}
                searchPlaceholder="Buscar fecha..."
                emptyMessage="Aún no hay balances. Genera el balance de hoy con el botón de arriba."
            />

            <CambiosCierreModal fecha={cambiosFecha} onClose={() => setCambiosFecha(null)} />
        </AppLayout>
    );
}
