import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Eye, Ban, Coins, CreditCard, TrendingUp, TrendingDown } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Tabs from '@/Components/UI/Tabs';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import Timeline from '@/Components/UI/Timeline';
import type { PageProps } from '@/types';

interface Pago {
    id: number;
    fecha: string;
    tipo: 'amortizacion' | 'incremento';
    monto: string;
    observacion: string | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    user?: { name: string } | null;
}

interface Deuda extends Record<string, unknown> {
    id: number;
    direccion: 'por_pagar' | 'por_cobrar';
    tipo: string;
    nombre: string;
    monto_original: string;
    saldo: string;
    fecha_inicio: string;
    fecha_vencimiento: string | null;
    estado: string;
    observacion: string | null;
    pagos: Pago[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    deudas: Paginado<Deuda>;
    totales: { por_pagar: number; por_cobrar: number };
    estado: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;

const TIPO_LABEL: Record<string, string> = {
    bancaria: 'Bancaria', personal: 'Personal', trabajador: 'Al personal', otro: 'Otro',
};

const emptyForm = () => ({
    direccion: 'por_pagar', tipo: 'bancaria', nombre: '',
    monto_original: '', fecha_inicio: hoy(), fecha_vencimiento: '', observacion: '',
});

export default function Deudas({ deudas, totales, estado, metodosPago, cuentas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo] = useState(false);
    const [pagando, setPagando]       = useState<Deuda | null>(null);
    const [anulando, setAnulando]     = useState<Deuda | null>(null);
    const [detalle, setDetalle]       = useState<Deuda | null>(null);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});
    const [form, setForm]             = useState(emptyForm());
    const [formPago, setFormPago]     = useState({ tipo: 'amortizacion', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '', observacion: '' });
    const [motivoAnular, setMotivoAnular] = useState('');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    /** Cuentas validas para el metodo elegido (vinculadas; efectivo -> caja Efectivo). */
    function cuentasDeMetodo(mid: string) {
        const m = metodosPago.find(x => String(x.id) === mid);
        if (!m) return cuentas;
        if (m.cuentas?.length) return m.cuentas;
        if (m.tipo_slug === 'efectivo') return cuentas.filter(c => c.es_efectivo);
        return []; // electronico sin cuenta vinculada: se crea sola con el nombre del metodo
    }

    function submitNuevo() {
        setSaving(true);
        router.post(route('finanzas.deudas.store'), {
            ...form,
            fecha_vencimiento: form.fecha_vencimiento || null,
        } as any, {
            onSuccess: () => { setModalNuevo(false); setForm(emptyForm()); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitPago() {
        if (!pagando) return;
        setSaving(true);
        router.post(route('finanzas.deudas.pago', pagando.id), {
            ...formPago,
            metodo_pago_id: formPago.metodo_pago_id || null,
            cuenta_id:      formPago.cuenta_id || null,
        } as any, {
            onSuccess: () => { setPagando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnular() {
        if (!anulando) return;
        setSaving(true);
        router.post(route('finanzas.deudas.anular', anulando.id), { motivo: motivoAnular } as any, {
            onSuccess: () => { setAnulando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<Deuda>[] = [
        {
            key: 'direccion', label: 'Dirección',
            render: (d) => d.direccion === 'por_pagar'
                ? <Badge variant="danger">Debemos</Badge>
                : <Badge variant="success">Nos deben</Badge>,
        },
        { key: 'nombre', label: 'Nombre', sortable: true, render: (d) => <span className="font-medium">{d.nombre}</span> },
        { key: 'tipo', label: 'Tipo', render: (d) => <span className="text-sm">{TIPO_LABEL[d.tipo] ?? d.tipo}</span> },
        { key: 'monto_original', label: 'Original', align: 'right', render: (d) => <span>{money(d.monto_original)}</span> },
        {
            key: 'saldo', label: 'Saldo', sortable: true, align: 'right',
            render: (d) => (
                <span className="font-bold" style={{ color: d.direccion === 'por_pagar' ? 'var(--color-danger)' : 'var(--color-success)' }}>
                    {money(d.saldo)}
                </span>
            ),
        },
        {
            key: 'estado', label: 'Estado',
            render: (d) => (
                <Badge variant={d.estado === 'activa' ? 'warning' : d.estado === 'pagada' ? 'success' : 'secondary'}>
                    {d.estado === 'activa' ? 'Activa' : d.estado === 'pagada' ? 'Pagada' : 'Anulada'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (d) => (
                <div className="flex items-center gap-1.5">
                    <button onClick={() => setDetalle(d)} className="p-1.5 rounded-lg hover:bg-black/5" title="Ver movimientos"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <Eye size={15} />
                    </button>
                    {d.estado === 'activa' && (
                        <>
                            <button onClick={() => { setErrors({}); setFormPago({ tipo: 'amortizacion', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '', observacion: '' }); setPagando(d); }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Registrar movimiento"
                                style={{ color: 'var(--color-primary)' }}>
                                <Coins size={15} />
                            </button>
                            <button onClick={() => { setErrors({}); setMotivoAnular(''); setAnulando(d); }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Anular"
                                style={{ color: 'var(--color-danger)' }}>
                                <Ban size={15} />
                            </button>
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Deudas y préstamos">
            <PageHeader
                icon={<CreditCard size={22} />}
                title="Deudas y préstamos"
                subtitle="Deudas bancarias, personales, al personal y préstamos otorgados"
                actions={
                    <Button onClick={() => { setErrors({}); setForm(emptyForm()); setModalNuevo(true); }}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nueva deuda
                    </Button>
                }
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 sm:grid-cols-3 lg:grid-cols-4" stats={[
                    {
                        label: 'Debemos', valor: money(totales.por_pagar), color: 'danger', destacado: true,
                        icon: <TrendingDown size={19} />, sub: 'Bancos, personas y personal',
                    },
                    {
                        label: 'Nos deben', valor: money(totales.por_cobrar), color: 'success',
                        icon: <TrendingUp size={19} />, sub: 'Préstamos otorgados a terceros',
                    },
                ]} />
            </div>

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'activas', label: 'Activas' },
                        { value: 'todas',   label: 'Todas' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.deudas.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table data={deudas.data} columns={columns}
                searchPlaceholder="Buscar deuda..." emptyMessage="No hay deudas registradas" />

            {/* Modal nueva deuda */}
            <Modal isOpen={modalNuevo} onClose={() => setModalNuevo(false)} title="Nueva deuda / préstamo" size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalNuevo(false)}>Cancelar</Button>
                        <Button onClick={submitNuevo} disabled={saving}>{saving ? 'Guardando...' : 'Guardar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Select label="Dirección" required
                        options={[
                            { value: 'por_pagar',  label: 'Debemos (pasivo — en contra)' },
                            { value: 'por_cobrar', label: 'Nos deben (activo — a favor)' },
                        ]}
                        value={form.direccion}
                        onChange={v => setForm(f => ({ ...f, direccion: String(v) }))}
                        error={errors.direccion}
                    />
                    <Select label="Tipo" required
                        options={[
                            { value: 'bancaria',   label: 'Bancaria (préstamo de banco)' },
                            { value: 'personal',   label: 'Personal (persona natural / tercero)' },
                            { value: 'trabajador', label: 'Al personal (sueldos, adelantos de personal)' },
                            { value: 'otro',       label: 'Otro' },
                        ]}
                        value={form.tipo}
                        onChange={v => setForm(f => ({ ...f, tipo: String(v) }))}
                        error={errors.tipo}
                    />
                    <Input label="Nombre / descripción" required placeholder='Ej: "Deuda BCP 1 - 7630", "Jeiner Herrera"'
                        value={form.nombre}
                        onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                        error={errors.nombre}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Monto" required type="number" min="0.01" step="0.01" value={form.monto_original}
                            onChange={e => setForm(f => ({ ...f, monto_original: e.target.value }))} error={errors.monto_original} />
                        <Input label="Fecha de inicio" required type="date" value={form.fecha_inicio}
                            onChange={e => setForm(f => ({ ...f, fecha_inicio: e.target.value }))} error={errors.fecha_inicio} />
                    </div>
                    <Input label="Fecha de vencimiento (opcional)" type="date" value={form.fecha_vencimiento}
                        onChange={e => setForm(f => ({ ...f, fecha_vencimiento: e.target.value }))} error={errors.fecha_vencimiento} />
                    <Input label="Observación" value={form.observacion}
                        onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))} />
                </div>
            </Modal>

            {/* Modal movimiento (amortización / incremento) */}
            <Modal isOpen={pagando !== null} onClose={() => setPagando(null)}
                title={pagando ? `Movimiento — ${pagando.nombre}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setPagando(null)}>Cancelar</Button>
                        <Button onClick={submitPago} disabled={saving}>{saving ? 'Guardando...' : 'Registrar'}</Button>
                    </>
                }
            >
                {pagando && (
                    <div className="space-y-4">
                        <StatGrid stats={[
                            { label: 'Saldo actual', valor: money(pagando.saldo), destacado: true },
                        ]} />
                        <Select label="Tipo de movimiento" required
                            options={[
                                { value: 'amortizacion', label: pagando.direccion === 'por_pagar' ? 'Pago de cuota (baja el saldo)' : 'Nos abonaron (baja el saldo)' },
                                { value: 'incremento',   label: 'Incremento (sube el saldo)' },
                            ]}
                            value={formPago.tipo}
                            onChange={v => setFormPago(f => ({ ...f, tipo: String(v) }))}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Fecha" required type="date" value={formPago.fecha}
                                onChange={e => setFormPago(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                            <Input label="Monto" required type="number" min="0.01" step="0.01" value={formPago.monto}
                                onChange={e => setFormPago(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                        </div>
                        <Select label="Método de pago"
                            options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                            value={formPago.metodo_pago_id}
                            onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormPago(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                            placeholder="— Seleccionar —"
                        />
                        {cuentasDeMetodo(formPago.metodo_pago_id).length > 0 ? (
                            <Select label="Cuenta"
                                options={cuentasDeMetodo(formPago.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                value={formPago.cuenta_id}
                                onChange={v => setFormPago(f => ({ ...f, cuenta_id: String(v) }))}
                                placeholder="— Seleccionar —"
                            />
                        ) : (
                            <Callout variant="info">
                                El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === formPago.metodo_pago_id)?.nombre}»</strong>,
                                que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                            </Callout>
                        )}
                        <Input label="Observación" value={formPago.observacion}
                            onChange={e => setFormPago(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal anular */}
            <Modal isOpen={anulando !== null} onClose={() => setAnulando(null)}
                title={anulando ? `Anular — ${anulando.nombre}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulando(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitAnular} disabled={saving}>{saving ? 'Anulando...' : 'Anular'}</Button>
                    </>
                }
            >
                <Input label="Motivo" required value={motivoAnular}
                    onChange={e => setMotivoAnular(e.target.value)} error={errors.motivo} />
            </Modal>

            {/* Modal detalle movimientos */}
            <Modal isOpen={detalle !== null} onClose={() => setDetalle(null)}
                title={detalle ? `Movimientos — ${detalle.nombre}` : ''} size="md"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <Timeline
                        emptyMessage="Sin movimientos registrados"
                        items={[
                            ...detalle.pagos.map(p => ({
                                fecha: new Date(p.fecha + 'T00:00:00').toLocaleDateString('es-PE'),
                                badge: p.tipo === 'amortizacion'
                                    ? { texto: 'Amortización', variant: 'success' as const }
                                    : { texto: 'Incremento', variant: 'warning' as const },
                                tipo: p.tipo === 'amortizacion' ? 'ingreso' as const : 'egreso' as const,
                                detalle: [p.metodo_pago?.nombre, p.cuenta?.nombre, p.observacion].filter(Boolean).join(' · ') || undefined,
                                user: p.user?.name,
                                monto: Number(p.monto),
                            })),
                            // El historial siempre termina en el origen: registro de la deuda.
                            {
                                fecha: new Date(detalle.fecha_inicio.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE'),
                                badge: { texto: 'Registro', variant: 'primary' as const },
                                tipo: (detalle.direccion === 'por_cobrar' ? 'ingreso' : 'egreso') as 'ingreso' | 'egreso',
                                detalle: detalle.observacion ?? 'Saldo inicial de la deuda',
                                monto: Number(detalle.monto_original),
                            },
                        ]}
                    />
                )}
            </Modal>
        </AppLayout>
    );
}
