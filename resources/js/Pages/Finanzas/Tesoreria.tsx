import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Coins, Landmark, Scale, ArrowDownCircle, ArrowUpCircle, ArrowLeftRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
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
    puede: { ajustar: boolean; crear: boolean };
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
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

export default function Tesoreria({ cuentas, cuentaId, movimientos, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [ajustando, setAjustando] = useState(false);
    const [saving, setSaving]       = useState(false);
    const [errors, setErrors]       = useState<Record<string, string>>({});
    const [form, setForm]           = useState({ fecha: hoy(), saldo_real: '', motivo: '' });
    // Movimiento manual de ajuste con monto exacto.
    const [modalMovimiento, setModalMovimiento] = useState(false);
    const [formMov, setFormMov] = useState({ cuenta_id: '', fecha: hoy(), tipo: 'ingreso', monto: '', descripcion: '' });

    function abrirMovimiento() {
        setErrors({});
        setFormMov({ cuenta_id: String(cuentaId), fecha: hoy(), tipo: 'ingreso', monto: '', descripcion: '' });
        setModalMovimiento(true);
    }

    function submitMovimiento() {
        setSaving(true);
        router.post(route('finanzas.tesoreria.movimiento'), formMov as any, {
            onSuccess: () => { setModalMovimiento(false); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

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
            key: 'monto', label: 'Monto', align: 'right',
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
                icon={<Landmark size={22} />}
                title="Tesorería"
                subtitle="Cada sol que entra o sale, con su origen. Nada se digita a mano."
                actions={
                    <div className="flex items-center gap-2">
                        {puede.crear && (
                            <Button variant="secondary" onClick={abrirMovimiento}>
                                <ArrowLeftRight size={15} className="mr-1 flex-shrink-0" />Movimiento manual
                            </Button>
                        )}
                        {puede.ajustar && (
                            <Button onClick={() => { setErrors({}); setForm({ fecha: hoy(), saldo_real: '', motivo: '' }); setAjustando(true); }}>
                                <Scale size={15} className="mr-1 flex-shrink-0" />Ajustar saldo
                            </Button>
                        )}
                    </div>
                }
            />

            {/* Tarjetas de cuentas con saldo */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-5">
                {cuentas.map((c, i) => (
                    <button
                        key={c.id}
                        onClick={() => cambiarCuenta(c.id)}
                        className="text-left rounded-2xl px-4 py-3 vp-fade-up transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
                        style={{
                            animationDelay: `${i * 50}ms`,
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

            {/* Modal movimiento manual (monto exacto) */}
            <Modal isOpen={modalMovimiento} onClose={() => setModalMovimiento(false)}
                title="Movimiento manual" size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalMovimiento(false)}>Cancelar</Button>
                        <Button onClick={submitMovimiento}
                            disabled={saving || formMov.cuenta_id === '' || formMov.monto === ''
                                || Number(formMov.monto) <= 0 || formMov.descripcion.trim() === ''}>
                            {saving ? 'Guardando...' : 'Registrar movimiento'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Callout variant="info">
                        Queda auditado con tu usuario y aparece como «Ajuste manual» en el libro.
                    </Callout>
                    <Select label="Cuenta" required
                        options={cuentas.map(c => ({ value: String(c.id), label: c.nombre }))}
                        value={formMov.cuenta_id}
                        onChange={v => setFormMov(f => ({ ...f, cuenta_id: String(v) }))}
                        placeholder="— Seleccionar —"
                        error={errors.cuenta_id}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Fecha" required type="date" value={formMov.fecha}
                            onChange={e => setFormMov(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Select label="Tipo" required
                            options={[
                                { value: 'ingreso', label: 'Ingreso (+)' },
                                { value: 'egreso',  label: 'Egreso (−)' },
                            ]}
                            value={formMov.tipo}
                            onChange={v => setFormMov(f => ({ ...f, tipo: String(v) }))}
                            error={errors.tipo}
                        />
                    </div>
                    <Input label="Monto (S/)" required type="number" min="0.01" step="0.01" value={formMov.monto}
                        onChange={e => setFormMov(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                    <Input label="Descripción" required
                        placeholder="Ej.: ajuste de cuenta — no cuadra el arqueo"
                        value={formMov.descripcion}
                        onChange={e => setFormMov(f => ({ ...f, descripcion: e.target.value }))} error={errors.descripcion} />
                </div>
            </Modal>
        </AppLayout>
    );
}
