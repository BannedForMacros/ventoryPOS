import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Users, ArrowDownLeft, ArrowUpRight, Scale, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Table, { Column } from '@/Components/UI/Table';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Select from '@/Components/UI/Select';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import { fmtS } from '@/Components/Reportes/ReportUI';

interface Tercero extends Record<string, unknown> {
    clave: string;
    nombre: string;
    documento: string | null;
    cliente_id: number | null;
    proveedor_id: number | null;
    nos_debe: number;
    le_debemos: number;
    su_anticipo: number;
    nuestro_adelanto: number;
    neto: number;
    rol: 'cliente' | 'proveedor' | 'ambos';
    docs_por_cobrar: number;
    docs_por_pagar: number;
    dias_antiguedad: number | null;
    sin_identificar: boolean;
}

interface Props {
    terceros: Tercero[];
    totales: {
        nos_debe: number;
        le_debemos: number;
        su_anticipo: number;
        nuestro_adelanto: number;
        neto: number;
    };
    kpis: {
        terceros: number;
        nos_deben: number;
        les_debemos: number;
        ambos_roles: number;
        sin_identificar: number;
    };
}

const ROL_LABEL: Record<Tercero['rol'], { texto: string; variant: 'primary' | 'danger' | 'warning' }> = {
    cliente:    { texto: 'Cliente',    variant: 'primary' },
    proveedor:  { texto: 'Proveedor',  variant: 'danger'  },
    ambos:      { texto: 'Ambos',      variant: 'warning' },
};

/** Celda de monto: vacía cuando es cero, para que la tabla no sea un muro de 0.00 */
function Monto({ valor, color }: { valor: number; color?: string }) {
    if (!valor) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;

    return <span style={{ color, fontVariantNumeric: 'tabular-nums' }}>{fmtS(valor)}</span>;
}

export default function EstadoCuenta({ terceros, totales, kpis }: Props) {
    const [filtro, setFiltro] = useState<string>('todos');

    const filtrados = useMemo(() => {
        switch (filtro) {
            case 'nos_deben':   return terceros.filter((t) => t.nos_debe > 0);
            case 'les_debemos': return terceros.filter((t) => t.le_debemos > 0);
            case 'anticipos':   return terceros.filter((t) => t.su_anticipo > 0 || t.nuestro_adelanto > 0);
            case 'ambos':       return terceros.filter((t) => t.rol === 'ambos');
            default:            return terceros;
        }
    }, [terceros, filtro]);

    // Los totales del pie reflejan lo que se está viendo; los KPIs de arriba,
    // el universo completo. Son dos preguntas distintas.
    const totalesVista = useMemo(() => ({
        nos_debe:         filtrados.reduce((s, t) => s + t.nos_debe, 0),
        le_debemos:       filtrados.reduce((s, t) => s + t.le_debemos, 0),
        su_anticipo:      filtrados.reduce((s, t) => s + t.su_anticipo, 0),
        nuestro_adelanto: filtrados.reduce((s, t) => s + t.nuestro_adelanto, 0),
        neto:             filtrados.reduce((s, t) => s + t.neto, 0),
    }), [filtrados]);

    const columns: Column<Tercero>[] = [
        {
            key: 'nombre',
            label: 'Tercero',
            sortable: true,
            searchKey: 'nombre',
            render: (t) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-medium flex items-center gap-1.5">
                        {t.nombre}
                        {t.sin_identificar && (
                            <AlertTriangle size={13} style={{ color: 'var(--color-warning)' }} />
                        )}
                    </span>
                    <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        {t.documento || 'Sin documento'}
                        {t.dias_antiguedad !== null && ` · ${t.dias_antiguedad} días`}
                    </span>
                </div>
            ),
        },
        {
            key: 'rol',
            label: 'Rol',
            sortable: true,
            render: (t) => <Badge variant={ROL_LABEL[t.rol].variant}>{ROL_LABEL[t.rol].texto}</Badge>,
        },
        {
            key: 'nos_debe',
            label: 'Nos debe',
            align: 'right',
            sortable: true,
            render: (t) => <Monto valor={t.nos_debe} color="var(--color-success)" />,
        },
        {
            key: 'le_debemos',
            label: 'Le debemos',
            align: 'right',
            sortable: true,
            render: (t) => <Monto valor={t.le_debemos} color="var(--color-danger)" />,
        },
        {
            key: 'su_anticipo',
            label: 'Su anticipo',
            align: 'right',
            sortable: true,
            render: (t) => <Monto valor={t.su_anticipo} color="var(--color-warning)" />,
        },
        {
            key: 'nuestro_adelanto',
            label: 'Ntro. adelanto',
            align: 'right',
            sortable: true,
            render: (t) => <Monto valor={t.nuestro_adelanto} color="var(--color-primary)" />,
        },
        {
            key: 'neto',
            label: 'Neto',
            align: 'right',
            sortable: true,
            render: (t) => (
                <span
                    className="font-semibold"
                    style={{
                        color: t.neto > 0 ? 'var(--color-success)' : t.neto < 0 ? 'var(--color-danger)' : 'var(--color-text-muted)',
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {fmtS(t.neto)}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <PageHeader
                icon={<Users size={22} />}
                title="Estado de cuenta"
                subtitle="Todo lo que cada tercero debe y se le debe, en una sola fila"
            />

            <div className="p-4 sm:p-6 flex flex-col gap-4">
                <StatGrid
                    size="lg"
                    stats={[
                        {
                            label: 'Nos deben',
                            valor: fmtS(totales.nos_debe),
                            color: 'success',
                            icon: <ArrowDownLeft size={18} />,
                            sub: `${kpis.nos_deben} terceros`,
                        },
                        {
                            label: 'Les debemos',
                            valor: fmtS(totales.le_debemos),
                            color: 'danger',
                            icon: <ArrowUpRight size={18} />,
                            sub: `${kpis.les_debemos} terceros`,
                        },
                        {
                            label: 'Anticipos de clientes',
                            valor: fmtS(totales.su_anticipo),
                            color: 'warning',
                            sub: 'Pagado y no entregado',
                        },
                        {
                            label: 'Adelantos a proveedores',
                            valor: fmtS(totales.nuestro_adelanto),
                            color: 'primary',
                            sub: 'Puesto y no consumido',
                        },
                        {
                            label: 'Neto general',
                            valor: fmtS(totales.neto),
                            color: totales.neto >= 0 ? 'success' : 'danger',
                            icon: <Scale size={18} />,
                            destacado: true,
                            sub: totales.neto >= 0 ? 'A nuestro favor' : 'En nuestra contra',
                        },
                    ]}
                />

                {kpis.sin_identificar > 0 && (
                    <Callout variant="warning">
                        Hay <strong>{kpis.sin_identificar}</strong> proveedor(es) registrados solo como texto libre
                        en las compras, sin ficha de proveedor. Sus saldos se agrupan por nombre y pueden
                        duplicarse si el nombre se escribió distinto. Están marcados con ⚠ en la lista.
                    </Callout>
                )}

                <FiltrosCard cols={4} tieneFiltros={filtro !== 'todos'} onClear={() => setFiltro('todos')}>
                    <Select
                        label="Mostrar"
                        value={filtro}
                        onChange={(v) => setFiltro(String(v))}
                        options={[
                            { value: 'todos',       label: `Todos (${terceros.length})` },
                            { value: 'nos_deben',   label: `Nos deben (${kpis.nos_deben})` },
                            { value: 'les_debemos', label: `Les debemos (${kpis.les_debemos})` },
                            { value: 'anticipos',   label: 'Con anticipo o adelanto' },
                            { value: 'ambos',       label: `Cliente y proveedor (${kpis.ambos_roles})` },
                        ]}
                    />
                </FiltrosCard>

                <Table
                    data={filtrados}
                    columns={columns}
                    searchable
                    searchPlaceholder="Buscar por nombre o documento…"
                    itemsPerPage={25}
                    emptyMessage="Ningún tercero con saldo pendiente."
                    rowClassName={() => 'cursor-pointer'}
                    onRowClick={(t) => router.visit(`/finanzas/estado-cuenta/${encodeURIComponent(t.clave)}`)}
                />

                <div
                    className="rounded-lg px-4 py-3 flex flex-wrap gap-x-8 gap-y-2 justify-end text-sm"
                    style={{
                        backgroundColor: 'var(--color-surface)',
                        border: '1px solid var(--color-border)',
                    }}
                >
                    <span style={{ color: 'var(--color-text-muted)' }}>
                        {filtrados.length} de {terceros.length} terceros
                    </span>
                    <span>Nos deben <strong style={{ color: 'var(--color-success)' }}>{fmtS(totalesVista.nos_debe)}</strong></span>
                    <span>Les debemos <strong style={{ color: 'var(--color-danger)' }}>{fmtS(totalesVista.le_debemos)}</strong></span>
                    <span>
                        Neto{' '}
                        <strong style={{ color: totalesVista.neto >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}>
                            {fmtS(totalesVista.neto)}
                        </strong>
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
