import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Banknote, Eye } from 'lucide-react';
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
    monto: string;
    referencia: string | null;
    proveedor_adelanto_id: number | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    user?: { name: string } | null;
}

interface EntradaCxp extends Record<string, unknown> {
    id: number;
    fecha: string;
    numero_documento: string | null;
    proveedor: string | null;
    proveedor_id: number | null;
    total: string;
    monto_pagado: string;
    estado_pago: string;
    proveedor_rel?: { id: number; razon_social?: string; nombre_comercial?: string } | null;
    almacen?: { nombre: string } | null;
    pagos_parciales: Pago[];
}

interface AdelantoMin { id: number; proveedor_id: number; saldo: string; }

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    entradas: Paginado<EntradaCxp>;
    totalPendiente: number;
    estado: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    adelantos: AdelantoMin[];
}

const hoy = () => new Date().toISOString().slice(0, 10);
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
// Normaliza fechas del backend ("2026-07-04" o "2026-07-04T00:00:00.000000Z")
const fdate = (s: string) => new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE');
const nombreProveedor = (e: EntradaCxp) =>
    e.proveedor_rel?.razon_social ?? e.proveedor_rel?.nombre_comercial ?? e.proveedor ?? '—';
const saldoDe = (e: EntradaCxp) => Math.max(0, Number(e.total) - Number(e.monto_pagado));

export default function CuentasPorPagar({ entradas, totalPendiente, estado, metodosPago, cuentas, adelantos }: Props) {
    const { flash } = usePage<Props>().props;
    const [abonando, setAbonando] = useState<EntradaCxp | null>(null);
    const [detalle, setDetalle]   = useState<EntradaCxp | null>(null);
    const [saving, setSaving]     = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});
    const [usarAdelanto, setUsarAdelanto] = useState(false);
    const [form, setForm] = useState({
        monto: '', fecha: hoy(), metodo_pago_id: '', cuenta_id: '',
        proveedor_adelanto_id: '', referencia: '', observacion: '',
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const adelantosDisponibles = abonando
        ? adelantos.filter(a => a.proveedor_id === abonando.proveedor_id && Number(a.saldo) > 0)
        : [];

    /** Cuentas válidas para el método elegido (vinculadas; efectivo → caja Efectivo). */
    function cuentasDeMetodo(mid: string) {
        const m = metodosPago.find(x => String(x.id) === mid);
        if (!m) return cuentas;
        if (m.cuentas?.length) return m.cuentas;
        if (m.tipo_slug === 'efectivo') return cuentas.filter(c => c.es_efectivo);
        return []; // electronico sin cuenta vinculada: se crea sola con el nombre del metodo
    }

    function abrirAbono(e: EntradaCxp) {
        setAbonando(e);
        setErrors({});
        setUsarAdelanto(false);
        setForm({
            monto: saldoDe(e).toFixed(2), fecha: hoy(), metodo_pago_id: '', cuenta_id: '',
            proveedor_adelanto_id: '', referencia: '', observacion: '',
        });
    }

    function submitAbono() {
        if (!abonando) return;
        setSaving(true);
        router.post(route('finanzas.cxp.abonar', abonando.id), {
            ...form,
            metodo_pago_id:        usarAdelanto ? null : (form.metodo_pago_id || null),
            cuenta_id:             usarAdelanto ? null : (form.cuenta_id || null),
            proveedor_adelanto_id: usarAdelanto ? (form.proveedor_adelanto_id || null) : null,
        } as any, {
            onSuccess: () => { setAbonando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<EntradaCxp>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (e) => <span className="text-sm">{fdate(e.fecha)}</span>,
        },
        { key: 'numero_documento', label: 'Documento', render: (e) => <span className="font-mono text-sm">{e.numero_documento ?? '—'}</span> },
        { key: 'proveedor', label: 'Proveedor', render: (e) => <span className="font-medium">{nombreProveedor(e)}</span> },
        { key: 'total', label: 'Total', render: (e) => <span>{money(e.total)}</span> },
        { key: 'monto_pagado', label: 'Pagado', render: (e) => <span style={{ color: 'var(--color-success, #16a34a)' }}>{money(e.monto_pagado)}</span> },
        {
            key: 'saldo', label: 'Saldo',
            render: (e) => saldoDe(e) > 0
                ? <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(saldoDe(e))}</span>
                : <Badge variant="success">Pagada</Badge>,
        },
        {
            key: 'estado_pago', label: 'Estado',
            render: (e) => (
                <Badge variant={e.estado_pago === 'pagado' ? 'success' : e.estado_pago === 'parcial' ? 'warning' : 'secondary'}>
                    {e.estado_pago === 'pagado' ? 'Pagado' : e.estado_pago === 'parcial' ? 'Parcial' : 'Pendiente'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (e) => (
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setDetalle(e)}
                        className="p-1.5 rounded-lg hover:bg-black/5"
                        title="Ver pagos"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Eye size={15} />
                    </button>
                    {saldoDe(e) > 0 && (
                        <button
                            onClick={() => abrirAbono(e)}
                            className="p-1.5 rounded-lg hover:bg-black/5"
                            title="Pagar"
                            style={{ color: 'var(--color-success)' }}
                        >
                            <Banknote size={15} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Cuentas por pagar">
            <PageHeader
                title="Cuentas por pagar"
                subtitle="Deudas con proveedores y pagos parciales"
                actions={
                    <div
                        className="px-4 py-2 rounded-xl text-right"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, var(--color-bg))' }}
                    >
                        <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                            Total por pagar
                        </p>
                        <p className="text-lg font-bold" style={{ color: 'var(--color-danger)' }}>{money(totalPendiente)}</p>
                    </div>
                }
            />

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'pendientes', label: 'Con saldo pendiente' },
                        { value: 'todas',      label: 'Todas las entradas' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.cxp.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table
                data={entradas.data}
                columns={columns}
                searchPlaceholder="Buscar proveedor o documento..."
                emptyMessage="No hay cuentas por pagar"
            />

            {/* Modal pagar */}
            <Modal
                isOpen={abonando !== null}
                onClose={() => setAbonando(null)}
                title={abonando ? `Pagar a ${nombreProveedor(abonando)}` : ''}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAbonando(null)}>Cancelar</Button>
                        <Button onClick={submitAbono}
                            disabled={saving || form.monto === '' || Number(form.monto) <= 0
                                || (abonando !== null && Number(form.monto) > saldoDe(abonando) + 0.009)}>
                            {saving ? 'Guardando...' : 'Registrar pago'}
                        </Button>
                    </>
                }
            >
                {abonando && (
                    <div className="space-y-4">
                        <StatGrid stats={[
                            { label: 'Total compra', valor: money(abonando.total) },
                            { label: 'Pagado', valor: money(abonando.monto_pagado), color: 'success' },
                            { label: 'Saldo', valor: money(saldoDe(abonando)), color: 'danger', destacado: true },
                        ]} />
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Monto del pago" required type="number" min="0.01" step="0.01"
                                max={saldoDe(abonando)}
                                value={form.monto}
                                onChange={e => setForm(f => ({ ...f, monto: e.target.value }))}
                                error={errors.monto}
                            />
                            <Input label="Fecha" required type="date"
                                value={form.fecha}
                                onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))}
                                error={errors.fecha}
                            />
                        </div>

                        {/* Nuevo saldo en vivo + validación de tope */}
                        {form.monto !== '' && Number(form.monto) > 0 && (() => {
                            const saldo = saldoDe(abonando);
                            const montoNum = Number(form.monto);
                            const nuevo = Math.round((saldo - montoNum) * 100) / 100;
                            if (montoNum > saldo + 0.009) {
                                return (
                                    <Callout variant="danger">
                                        El pago ({money(montoNum)}) no puede superar el saldo pendiente ({money(saldo)}).
                                    </Callout>
                                );
                            }
                            return nuevo <= 0.009
                                ? <Callout variant="success" title="Con este pago la compra queda PAGADA" />
                                : <Callout variant="info" title="Nuevo saldo pendiente" aside={money(nuevo)} />;
                        })()}

                        {adelantosDisponibles.length > 0 && (
                            <Callout variant="info">
                                <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                    <input type="checkbox" checked={usarAdelanto}
                                        onChange={e => setUsarAdelanto(e.target.checked)}
                                        className="h-4 w-4 accent-[var(--color-primary)]"
                                    />
                                    <span style={{ color: 'var(--color-text)' }}>Pagar consumiendo un adelanto entregado al proveedor</span>
                                </label>
                            </Callout>
                        )}

                        {usarAdelanto ? (
                            <Select label="Adelanto a consumir" required
                                options={adelantosDisponibles.map(a => ({ value: String(a.id), label: `Adelanto #${a.id} — saldo ${money(a.saldo)}` }))}
                                value={form.proveedor_adelanto_id}
                                onChange={v => setForm(f => ({ ...f, proveedor_adelanto_id: String(v) }))}
                                placeholder="— Seleccionar —"
                                error={errors.proveedor_adelanto_id}
                            />
                        ) : (
                            <>
                                <Select label="Método de pago"
                                    options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                                    value={form.metodo_pago_id}
                                    onChange={v => {
                                        const cts = cuentasDeMetodo(String(v));
                                        setForm(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' }));
                                    }}
                                    placeholder="— Seleccionar —"
                                    error={errors.metodo_pago_id}
                                />
                                {cuentasDeMetodo(form.metodo_pago_id).length > 0 ? (
                                    <Select label="Cuenta origen"
                                        options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                        value={form.cuenta_id}
                                        onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                                        placeholder="— Seleccionar —"
                                        hint={form.metodo_pago_id ? 'Solo las cuentas vinculadas al método elegido' : undefined}
                                        error={errors.cuenta_id}
                                    />
                                ) : (
                                    <Callout variant="info">
                                        El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === form.metodo_pago_id)?.nombre}»</strong>,
                                        que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                                    </Callout>
                                )}
                            </>
                        )}

                        <Input label="Referencia (operación, voucher...)"
                            value={form.referencia}
                            onChange={e => setForm(f => ({ ...f, referencia: e.target.value }))}
                        />
                        <Input label="Observación"
                            value={form.observacion}
                            onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))}
                        />
                    </div>
                )}
            </Modal>

            {/* Modal historial de pagos */}
            <Modal
                isOpen={detalle !== null}
                onClose={() => setDetalle(null)}
                title={detalle ? `Pagos — ${nombreProveedor(detalle)}` : ''}
                size="md"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <div className="space-y-4">
                        <StatGrid stats={[
                            { label: 'Total', valor: money(detalle.total) },
                            { label: 'Pagado', valor: money(detalle.monto_pagado), color: 'success' },
                            { label: 'Saldo', valor: money(saldoDe(detalle)), color: 'danger' },
                        ]} />
                        <Timeline
                            emptyMessage="Sin pagos registrados"
                            items={detalle.pagos_parciales.map(p => ({
                                fecha: fdate(p.fecha),
                                badge: p.proveedor_adelanto_id
                                    ? { texto: 'Adelanto', variant: 'warning' as const }
                                    : { texto: 'Pago', variant: 'success' as const },
                                tipo: 'egreso' as const,
                                detalle: p.proveedor_adelanto_id
                                    ? `Consumió adelanto #${p.proveedor_adelanto_id}`
                                    : [p.metodo_pago?.nombre, p.cuenta?.nombre, p.referencia].filter(Boolean).join(' · ') || undefined,
                                user: p.user?.name,
                                monto: Number(p.monto),
                            }))}
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
