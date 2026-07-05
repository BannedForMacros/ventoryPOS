import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { HandCoins, Eye } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Tabs from '@/Components/UI/Tabs';
import type { PageProps } from '@/types';

interface Abono {
    id: number;
    fecha: string;
    monto: string;
    referencia: string | null;
    observacion: string | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    user?: { name: string } | null;
}

interface PagoInicial {
    id: number;
    monto: string;
    vuelto: string;
    metodo_pago?: { nombre: string } | null;
}

interface VentaCxc extends Record<string, unknown> {
    id: number;
    numero: string | null;
    fecha_venta: string;
    fecha_vencimiento: string | null;
    total: string;
    monto_pagado: string;
    saldo_pendiente: string;
    cliente?: { id: number; nombres?: string; apellidos?: string; razon_social?: string } | null;
    user?: { name: string } | null;
    abonos: Abono[];
    pagos: PagoInicial[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    ventas: Paginado<VentaCxc>;
    totalPendiente: number;
    estado: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
}

const hoy = () => new Date().toISOString().slice(0, 10);
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreCliente = (v: VentaCxc) =>
    v.cliente?.razon_social ?? (`${v.cliente?.nombres ?? ''} ${v.cliente?.apellidos ?? ''}`.trim() || '—');

export default function CuentasPorCobrar({ ventas, totalPendiente, estado, metodosPago, cuentas }: Props) {
    const { flash } = usePage<Props>().props;
    const [abonando, setAbonando] = useState<VentaCxc | null>(null);
    const [detalle, setDetalle]   = useState<VentaCxc | null>(null);
    const [saving, setSaving]     = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        monto: '', fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    /** Cuentas válidas para el método elegido (vinculadas; efectivo → caja Efectivo). */
    function cuentasDeMetodo(mid: string) {
        const m = metodosPago.find(x => String(x.id) === mid);
        if (!m) return cuentas;
        if (m.cuentas?.length) return m.cuentas;
        if (m.tipo_slug === 'efectivo') return cuentas.filter(c => c.es_efectivo);
        return []; // electronico sin cuenta vinculada: se crea sola con el nombre del metodo
    }

    function abrirAbono(v: VentaCxc) {
        setAbonando(v);
        setErrors({});
        setForm({ monto: String(v.saldo_pendiente), fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '' });
    }

    function submitAbono() {
        if (!abonando) return;
        setSaving(true);
        router.post(route('finanzas.cxc.abonar', abonando.id), {
            ...form,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
        } as any, {
            onSuccess: () => { setAbonando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<VentaCxc>[] = [
        {
            key: 'fecha_venta', label: 'Fecha', sortable: true,
            render: (v) => <span className="text-sm">{new Date(v.fecha_venta).toLocaleDateString('es-PE')}</span>,
        },
        { key: 'numero', label: 'N°', render: (v) => <span className="font-mono text-sm">{v.numero ?? '—'}</span> },
        { key: 'cliente', label: 'Cliente', render: (v) => <span className="font-medium">{nombreCliente(v)}</span> },
        { key: 'total', label: 'Total', render: (v) => <span>{money(v.total)}</span> },
        { key: 'monto_pagado', label: 'Pagado', render: (v) => <span style={{ color: 'var(--color-success, #16a34a)' }}>{money(v.monto_pagado)}</span> },
        {
            key: 'saldo_pendiente', label: 'Saldo', sortable: true,
            render: (v) => Number(v.saldo_pendiente) > 0
                ? <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(v.saldo_pendiente)}</span>
                : <Badge variant="success">Saldada</Badge>,
        },
        {
            key: 'fecha_vencimiento', label: 'Vence',
            render: (v) => {
                if (!v.fecha_vencimiento) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
                const vencida = Number(v.saldo_pendiente) > 0 && v.fecha_vencimiento < hoy();
                return (
                    <span className="text-sm" style={{ color: vencida ? 'var(--color-danger)' : 'var(--color-text)' }}>
                        {new Date(v.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-PE')}
                        {vencida && <Badge variant="danger">Vencida</Badge>}
                    </span>
                );
            },
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (v) => (
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setDetalle(v)}
                        className="p-1.5 rounded-lg hover:bg-black/5"
                        title="Ver abonos"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Eye size={15} />
                    </button>
                    {Number(v.saldo_pendiente) > 0 && (
                        <button
                            onClick={() => abrirAbono(v)}
                            className="p-1.5 rounded-lg hover:bg-black/5"
                            title="Abonar"
                            style={{ color: 'var(--color-success)' }}
                        >
                            <HandCoins size={15} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Cuentas por cobrar">
            <PageHeader
                title="Cuentas por cobrar"
                subtitle="Ventas a crédito y abonos de clientes"
                actions={
                    <div
                        className="px-4 py-2 rounded-xl text-right"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, var(--color-bg))' }}
                    >
                        <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                            Total por cobrar
                        </p>
                        <p className="text-lg font-bold" style={{ color: 'var(--color-danger)' }}>{money(totalPendiente)}</p>
                    </div>
                }
            />

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'pendientes', label: 'Con saldo pendiente' },
                        { value: 'todas',      label: 'Todas las ventas a crédito' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.cxc.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table
                data={ventas.data}
                columns={columns}
                searchPlaceholder="Buscar cliente o número..."
                emptyMessage="No hay ventas a crédito"
            />

            {/* Modal abonar */}
            <Modal
                isOpen={abonando !== null}
                onClose={() => setAbonando(null)}
                title={abonando ? `Abonar venta ${abonando.numero ?? ''} — ${nombreCliente(abonando)}` : ''}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAbonando(null)}>Cancelar</Button>
                        <Button onClick={submitAbono}
                            disabled={saving || form.monto === '' || Number(form.monto) <= 0
                                || Number(form.monto) > Number(abonando?.saldo_pendiente ?? 0) + 0.009}>
                            {saving ? 'Guardando...' : 'Registrar abono'}
                        </Button>
                    </>
                }
            >
                {abonando && (
                    <div className="space-y-4">
                        <div className="flex justify-between text-sm px-1">
                            <span style={{ color: 'var(--color-text-muted)' }}>Saldo pendiente</span>
                            <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(abonando.saldo_pendiente)}</span>
                        </div>
                        <Input label="Monto del abono" required type="number" min="0.01" step="0.01"
                            max={Number(abonando.saldo_pendiente)}
                            value={form.monto}
                            onChange={e => setForm(f => ({ ...f, monto: e.target.value }))}
                            error={errors.monto}
                        />

                        {/* Nuevo saldo en vivo + validación de tope */}
                        {form.monto !== '' && Number(form.monto) > 0 && (() => {
                            const saldo = Number(abonando.saldo_pendiente);
                            const montoNum = Number(form.monto);
                            const nuevo = Math.round((saldo - montoNum) * 100) / 100;
                            if (montoNum > saldo + 0.009) {
                                return (
                                    <div className="rounded-xl px-3 py-2 text-sm font-semibold"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, var(--color-bg))', color: 'var(--color-danger)' }}>
                                        ⚠ El abono ({money(montoNum)}) no puede superar el saldo pendiente ({money(saldo)}).
                                    </div>
                                );
                            }
                            return (
                                <div className="flex justify-between items-center rounded-xl px-3 py-2 text-sm"
                                    style={{ backgroundColor: nuevo <= 0.009
                                        ? 'color-mix(in srgb, var(--color-success, #16a34a) 12%, var(--color-bg))'
                                        : 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))' }}>
                                    <span style={{ color: 'var(--color-text)' }}>
                                        {nuevo <= 0.009 ? '✓ Con este abono la venta queda SALDADA' : 'Nuevo saldo pendiente'}
                                    </span>
                                    <strong style={{ color: nuevo <= 0.009 ? 'var(--color-success)' : 'var(--color-primary)' }}>
                                        {money(Math.max(0, nuevo))}
                                    </strong>
                                </div>
                            );
                        })()}
                        <Input label="Fecha" required type="date"
                            value={form.fecha}
                            onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))}
                            error={errors.fecha}
                        />
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
                            <Select label="Cuenta destino"
                                options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                value={form.cuenta_id}
                                onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                                placeholder="— Seleccionar —"
                                hint={form.metodo_pago_id ? 'Solo las cuentas vinculadas al método elegido' : undefined}
                                error={errors.cuenta_id}
                            />
                        ) : (
                            <div className="rounded-xl px-3 py-2 text-sm"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))', color: 'var(--color-text)' }}>
                                El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === form.metodo_pago_id)?.nombre}»</strong>,
                                que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                            </div>
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

            {/* Modal historial de abonos */}
            <Modal
                isOpen={detalle !== null}
                onClose={() => setDetalle(null)}
                title={detalle ? `Abonos — venta ${detalle.numero ?? ''}` : ''}
                size="md"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-3 gap-2 text-center text-sm">
                            <div><p style={{ color: 'var(--color-text-muted)' }}>Total</p><p className="font-bold">{money(detalle.total)}</p></div>
                            <div><p style={{ color: 'var(--color-text-muted)' }}>Pagado</p><p className="font-bold">{money(detalle.monto_pagado)}</p></div>
                            <div><p style={{ color: 'var(--color-text-muted)' }}>Saldo</p><p className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(detalle.saldo_pendiente)}</p></div>
                        </div>
                        <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                            {/* Pago inicial hecho en el POS al momento de la venta */}
                            {detalle.pagos.filter(p => Number(p.monto) - Number(p.vuelto) > 0).map(p => (
                                <div key={`ini-${p.id}`} className="py-2 flex justify-between items-start text-sm">
                                    <div>
                                        <p className="font-medium">
                                            {new Date(detalle.fecha_venta).toLocaleDateString('es-PE')}{' '}
                                            <Badge variant="primary">Pago inicial</Badge>
                                        </p>
                                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                            {[p.metodo_pago?.nombre, 'al momento de la venta', detalle.user?.name].filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                    <span className="font-bold">{money(Number(p.monto) - Number(p.vuelto))}</span>
                                </div>
                            ))}

                            {/* Abonos posteriores */}
                            {detalle.abonos.map(a => (
                                <div key={a.id} className="py-2 flex justify-between items-start text-sm">
                                    <div>
                                        <p className="font-medium">
                                            {new Date(a.fecha + 'T00:00:00').toLocaleDateString('es-PE')}{' '}
                                            <Badge variant="success">Abono</Badge>
                                        </p>
                                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                            {[a.metodo_pago?.nombre, a.cuenta?.nombre, a.referencia, a.user?.name].filter(Boolean).join(' · ') || '—'}
                                        </p>
                                    </div>
                                    <span className="font-bold">{money(a.monto)}</span>
                                </div>
                            ))}

                            {detalle.abonos.length === 0 && detalle.pagos.filter(p => Number(p.monto) - Number(p.vuelto) > 0).length === 0 && (
                                <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Sin pagos registrados</p>
                            )}
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
