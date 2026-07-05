import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Coins, Landmark, Scale, ArrowDownCircle, ArrowUpCircle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import type { PageProps } from '@/types';

interface CuentaSaldo {
    id: number;
    nombre: string;
    banco: string | null;
    es_efectivo: boolean;
    saldo: number;
}

interface Movimiento extends Record<string, unknown> {
    id: number;
    fecha: string;
    tipo: 'ingreso' | 'egreso';
    monto: string;
    descripcion: string;
    ref_tipo: string | null;
    ref_id: number | null;
    user?: { name: string } | null;
    created_at: string;
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    cuentas: CuentaSaldo[];
    cuentaId: number;
    movimientos: Paginado<Movimiento>;
}

const hoy = () => new Date().toISOString().slice(0, 10);
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const fdate = (s: string) => new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE');

const ORIGEN_LABEL: Record<string, string> = {
    venta:                         'Venta',
    venta_abono:                   'Abono de cliente',
    gasto:                         'Gasto',
    entrada_pago:                  'Pago a proveedor',
    entrada:                       'Pago a proveedor',
    cliente_anticipo:              'Anticipo de cliente',
    cliente_anticipo_devolucion:   'Devolución de anticipo',
    proveedor_adelanto:            'Adelanto a proveedor',
    proveedor_adelanto_devolucion: 'Devolución de adelanto',
    deuda_pago:                    'Deuda / préstamo',
    devolucion:                    'Reembolso devolución',
    ajuste:                        'Ajuste manual',
};

export default function Tesoreria({ cuentas, cuentaId, movimientos }: Props) {
    const { flash } = usePage<Props>().props;
    const [ajustando, setAjustando] = useState(false);
    const [saving, setSaving]       = useState(false);
    const [errors, setErrors]       = useState<Record<string, string>>({});
    const [form, setForm]           = useState({ fecha: hoy(), saldo_real: '', motivo: '' });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const cuentaActiva = cuentas.find(c => c.id === cuentaId);

    function cambiarCuenta(id: number) {
        router.get(route('finanzas.tesoreria.index'), { cuenta_id: id }, { preserveState: true, replace: true });
    }

    function submitAjuste() {
        setSaving(true);
        router.post(route('finanzas.tesoreria.ajustar'), {
            ...form, cuenta_id: cuentaId,
        } as any, {
            onSuccess: () => { setAjustando(false); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<Movimiento>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (m) => <span className="text-sm">{fdate(m.fecha)}</span>,
        },
        {
            key: 'tipo', label: 'Tipo',
            render: (m) => m.tipo === 'ingreso'
                ? <span className="inline-flex items-center gap-1 text-sm font-medium" style={{ color: 'var(--color-success)' }}><ArrowDownCircle size={14} />Ingreso</span>
                : <span className="inline-flex items-center gap-1 text-sm font-medium" style={{ color: 'var(--color-danger)' }}><ArrowUpCircle size={14} />Egreso</span>,
        },
        { key: 'descripcion', label: 'Descripción', render: (m) => <span className="text-sm">{m.descripcion}</span> },
        {
            key: 'ref_tipo', label: 'Origen',
            render: (m) => m.ref_tipo
                ? <Badge variant={m.ref_tipo === 'ajuste' ? 'warning' : 'primary'}>{ORIGEN_LABEL[m.ref_tipo] ?? m.ref_tipo}</Badge>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'monto', label: 'Monto',
            render: (m) => (
                <span className="font-bold" style={{ color: m.tipo === 'ingreso' ? 'var(--color-success)' : 'var(--color-danger)' }}>
                    {m.tipo === 'ingreso' ? '+' : '−'}{money(m.monto)}
                </span>
            ),
        },
        {
            key: 'user', label: 'Registrado por',
            render: (m) => <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>{m.user?.name ?? '—'}</span>,
        },
    ];

    return (
        <AppLayout title="Tesorería">
            <PageHeader
                title="Tesorería"
                subtitle="Cada sol que entra o sale, con su origen. Nada se digita a mano."
                actions={
                    <Button onClick={() => { setErrors({}); setForm({ fecha: hoy(), saldo_real: '', motivo: '' }); setAjustando(true); }}>
                        <Scale size={15} className="mr-1 flex-shrink-0" />Ajustar saldo
                    </Button>
                }
            />

            {/* Tarjetas de cuentas con saldo */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-5">
                {cuentas.map(c => (
                    <button
                        key={c.id}
                        onClick={() => cambiarCuenta(c.id)}
                        className="text-left rounded-2xl px-4 py-3 transition-all"
                        style={{
                            border: c.id === cuentaId ? '2px solid var(--color-primary)' : '1px solid var(--color-border)',
                            backgroundColor: c.id === cuentaId
                                ? 'color-mix(in srgb, var(--color-primary) 6%, var(--color-surface))'
                                : 'var(--color-surface)',
                        }}
                    >
                        <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                            {c.es_efectivo ? <Coins size={13} /> : <Landmark size={13} />}
                            {c.nombre}{c.banco ? ` · ${c.banco}` : ''}
                        </p>
                        <p className="text-lg font-bold mt-1" style={{ color: c.saldo >= 0 ? 'var(--color-text)' : 'var(--color-danger)' }}>
                            {money(c.saldo)}
                        </p>
                    </button>
                ))}
            </div>

            <Table
                data={movimientos.data}
                columns={columns}
                searchPlaceholder="Buscar movimiento..."
                emptyMessage={`Sin movimientos en ${cuentaActiva?.nombre ?? 'esta cuenta'}. Se registran solos al vender, gastar, abonar, etc.`}
            />

            {/* Modal ajuste de saldo */}
            <Modal isOpen={ajustando} onClose={() => setAjustando(false)}
                title={`Ajustar saldo — ${cuentaActiva?.nombre ?? ''}`} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAjustando(false)}>Cancelar</Button>
                        <Button onClick={submitAjuste} disabled={saving}>{saving ? 'Guardando...' : 'Registrar ajuste'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <StatGrid stats={[
                        { label: 'Saldo según sistema', valor: money(cuentaActiva?.saldo), destacado: true },
                    ]} />
                    <Callout variant="warning">
                        Ingresa el saldo REAL contado y el sistema registrará la diferencia como
                        movimiento de ajuste, con tu usuario y motivo en auditoría.
                    </Callout>
                    <Input label="Fecha" required type="date" value={form.fecha}
                        onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                    <Input label="Saldo real contado (S/)" required type="number" min="0" step="0.01" value={form.saldo_real}
                        onChange={e => setForm(f => ({ ...f, saldo_real: e.target.value }))} error={errors.saldo_real} />
                    {form.saldo_real !== '' && cuentaActiva && (() => {
                        const dif = Number(form.saldo_real) - cuentaActiva.saldo;
                        return (
                            <Callout variant={dif >= 0 ? 'success' : 'danger'} title="Diferencia a registrar"
                                aside={`${dif >= 0 ? '+' : ''}${money(dif)}`} />
                        );
                    })()}
                    <Input label="Motivo (obligatorio)" required
                        placeholder='Ej: "Saldo inicial al implementar el sistema", "Sencillo no registrado"'
                        value={form.motivo}
                        onChange={e => setForm(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                </div>
            </Modal>
        </AppLayout>
    );
}
