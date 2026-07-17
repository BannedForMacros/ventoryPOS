import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Banknote, Eye, Pencil, ReceiptText, Trash2 } from 'lucide-react';
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
import type { PageProps } from '@/types';

interface Pago {
    id: number;
    fecha: string;
    monto: string;
    referencia: string | null;
    observacion?: string | null;
    metodo_pago_id?: number | null;
    cuenta_id?: number | null;
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
    esAdmin?: boolean;
    estado: string;
    buscar?: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    adelantos: AdelantoMin[];
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
// Normaliza fechas del backend ("2026-07-04" o "2026-07-04T00:00:00.000000Z")
const fdate = (s: string) => new Date(s.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE');
const nombreProveedor = (e: EntradaCxp) =>
    e.proveedor_rel?.razon_social ?? e.proveedor_rel?.nombre_comercial ?? e.proveedor ?? '—';
const saldoDe = (e: EntradaCxp) => Math.max(0, Number(e.total) - Number(e.monto_pagado));

export default function CuentasPorPagar({ entradas, totalPendiente, esAdmin, estado, buscar, metodosPago, cuentas, adelantos }: Props) {
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
    // Edición / anulación de un pago ya registrado (solo admin).
    const [editandoPago, setEditandoPago]   = useState<Pago | null>(null);
    const [anulandoPago, setAnulandoPago]   = useState<Pago | null>(null);
    const [motivoAnular, setMotivoAnular]   = useState('');
    const [formPago, setFormPago] = useState({
        monto: '', fecha: '', metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
    });

    function abrirEditarPago(p: Pago) {
        setErrors({});
        setFormPago({
            monto:          String(Number(p.monto)),
            fecha:          p.fecha.slice(0, 10),
            metodo_pago_id: p.metodo_pago_id ? String(p.metodo_pago_id) : '',
            cuenta_id:      p.cuenta_id ? String(p.cuenta_id) : '',
            referencia:     p.referencia ?? '',
            observacion:    p.observacion ?? '',
        });
        setEditandoPago(p);
    }

    function submitEditarPago() {
        if (!editandoPago) return;
        setSaving(true);
        const esAdelantoPago = !!editandoPago.proveedor_adelanto_id;
        router.put(route('finanzas.cxp.pagos.update', editandoPago.id), {
            // Vía adelanto el monto no se envía (el backend lo prohíbe).
            ...(esAdelantoPago ? {} : { monto: formPago.monto }),
            fecha:          formPago.fecha,
            metodo_pago_id: esAdelantoPago ? null : (formPago.metodo_pago_id || null),
            cuenta_id:      esAdelantoPago ? null : (formPago.cuenta_id || null),
            referencia:     formPago.referencia || null,
            observacion:    formPago.observacion || null,
        } as any, {
            onSuccess: () => { setEditandoPago(null); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnularPago() {
        if (!anulandoPago) return;
        setSaving(true);
        router.delete(route('finanzas.cxp.pagos.destroy', anulandoPago.id), {
            data: { motivo: motivoAnular.trim() },
            onSuccess: () => { setAnulandoPago(null); setMotivoAnular(''); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        } as any);
    }

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
        { key: 'total', label: 'Total', align: 'right', render: (e) => <span>{money(e.total)}</span> },
        { key: 'monto_pagado', label: 'Pagado', align: 'right', render: (e) => <span style={{ color: 'var(--color-success, #16a34a)' }}>{money(e.monto_pagado)}</span> },
        {
            key: 'saldo', label: 'Saldo', align: 'right',
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
                icon={<ReceiptText size={22} />}
                title="Cuentas por pagar"
                subtitle="Deudas con proveedores y pagos parciales"
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 sm:grid-cols-3 lg:grid-cols-4" stats={[
                    {
                        label: 'Total por pagar', valor: money(totalPendiente), color: 'danger', destacado: true,
                        icon: <Banknote size={19} />, sub: 'Compras con saldo pendiente',
                    },
                ]} />
            </div>

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'pendientes', label: 'Con saldo pendiente' },
                        { value: 'todas',      label: 'Todas las entradas' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.cxp.index'), { estado: v, buscar: buscar || undefined }, { preserveState: true, replace: true })}
                />
            </div>

            <Table
                data={entradas}
                columns={columns}
                searchPlaceholder="Buscar proveedor o documento (toda la base)..."
                emptyMessage="No hay cuentas por pagar"
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('finanzas.cxp.index'),
                    { estado, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })}
            />

            {/* Modal pagar */}
            <Modal
                isOpen={abonando !== null}
                onClose={() => setAbonando(null)}
                title={abonando ? `Pagar a ${nombreProveedor(abonando)}` : ''}
                size="lg"
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

            {/* Modal historial de pagos (con editar/anular para admin) */}
            <Modal
                isOpen={detalle !== null}
                onClose={() => setDetalle(null)}
                title={detalle ? `Pagos — ${nombreProveedor(detalle)}${detalle.numero_documento ? ` · ${detalle.numero_documento}` : ''}` : ''}
                size="2xl"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <div className="space-y-4">
                        <StatGrid stats={[
                            { label: 'Total', valor: money(detalle.total) },
                            { label: 'Pagado', valor: money(detalle.monto_pagado), color: 'success' },
                            { label: 'Saldo', valor: money(saldoDe(detalle)), color: 'danger' },
                        ]} />
                        {detalle.pagos_parciales.length === 0 ? (
                            <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin pagos registrados</p>
                        ) : (
                            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                {detalle.pagos_parciales.map((p, idx) => (
                                    <div key={p.id} className="flex items-center gap-3 px-4 py-2.5"
                                        style={{
                                            borderBottom: idx < detalle.pagos_parciales.length - 1 ? '1px solid var(--color-border)' : undefined,
                                            backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                        }}>
                                        <Badge variant={p.proveedor_adelanto_id ? 'warning' : 'success'}>
                                            {p.proveedor_adelanto_id ? 'Adelanto' : 'Pago'}
                                        </Badge>
                                        <div className="flex-1 min-w-0 text-xs">
                                            <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                                {fdate(p.fecha)}
                                                <span className="ml-2 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                    {p.proveedor_adelanto_id
                                                        ? `Consumió adelanto #${p.proveedor_adelanto_id}`
                                                        : [p.metodo_pago?.nombre, p.cuenta?.nombre, p.referencia].filter(Boolean).join(' · ') || '—'}
                                                </span>
                                            </p>
                                            {(p.observacion || p.user?.name) && (
                                                <p className="truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                    {[p.observacion, p.user?.name ? `por ${p.user.name}` : null].filter(Boolean).join(' · ')}
                                                </p>
                                            )}
                                        </div>
                                        <span className="font-bold text-sm whitespace-nowrap" style={{ color: 'var(--color-danger)' }}>
                                            −{money(p.monto)}
                                        </span>
                                        {esAdmin && (
                                            <div className="flex items-center gap-1 flex-shrink-0">
                                                <button onClick={() => abrirEditarPago(p)}
                                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Editar pago"
                                                    style={{ color: 'var(--color-primary)' }}>
                                                    <Pencil size={14} />
                                                </button>
                                                <button onClick={() => { setErrors({}); setMotivoAnular(''); setAnulandoPago(p); }}
                                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Anular pago"
                                                    style={{ color: 'var(--color-danger)' }}>
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                        {esAdmin && detalle.pagos_parciales.length > 0 && (
                            <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                Editar o anular un pago recalcula tesorería y el saldo de la compra automáticamente. Todo queda en auditoría.
                            </p>
                        )}
                    </div>
                )}
            </Modal>

            {/* Modal editar pago (admin) */}
            <Modal isOpen={editandoPago !== null} onClose={() => setEditandoPago(null)}
                title={editandoPago ? `Editar pago — ${money(editandoPago.monto)} del ${fdate(editandoPago.fecha)}` : ''} size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditandoPago(null)}>Cancelar</Button>
                        <Button onClick={submitEditarPago} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</Button>
                    </>
                }
            >
                {editandoPago && (
                    <div className="space-y-4">
                        {editandoPago.proveedor_adelanto_id ? (
                            <Callout variant="warning">
                                Este pago consumió el adelanto #{editandoPago.proveedor_adelanto_id}: su <strong>monto no se edita</strong> (anúlalo y regístralo de nuevo si el monto está mal). Puedes corregir fecha, referencia y observación.
                            </Callout>
                        ) : (
                            <Callout variant="info">
                                Al guardar, el egreso en tesorería se revierte y se vuelve a asentar con los datos nuevos; el saldo de la compra se recalcula.
                            </Callout>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Monto" required type="number" min="0.01" step="0.01"
                                value={formPago.monto}
                                disabled={!!editandoPago.proveedor_adelanto_id}
                                onChange={e => setFormPago(f => ({ ...f, monto: e.target.value }))}
                                error={errors.monto}
                            />
                            <Input label="Fecha" required type="date" value={formPago.fecha}
                                onChange={e => setFormPago(f => ({ ...f, fecha: e.target.value }))}
                                error={errors.fecha}
                            />
                        </div>
                        {!editandoPago.proveedor_adelanto_id && (
                            <>
                                <Select label="Método de pago"
                                    options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                                    value={formPago.metodo_pago_id}
                                    onChange={v => {
                                        const cts = cuentasDeMetodo(String(v));
                                        setFormPago(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' }));
                                    }}
                                    placeholder="— Seleccionar —"
                                    error={errors.metodo_pago_id}
                                />
                                {cuentasDeMetodo(formPago.metodo_pago_id).length > 0 && (
                                    <Select label="Cuenta origen"
                                        options={cuentasDeMetodo(formPago.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                        value={formPago.cuenta_id}
                                        onChange={v => setFormPago(f => ({ ...f, cuenta_id: String(v) }))}
                                        placeholder="— Seleccionar —"
                                        error={errors.cuenta_id}
                                    />
                                )}
                            </>
                        )}
                        <Input label="Referencia (operación, voucher...)" value={formPago.referencia}
                            onChange={e => setFormPago(f => ({ ...f, referencia: e.target.value }))} />
                        <Input label="Observación" value={formPago.observacion}
                            onChange={e => setFormPago(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal anular pago (admin) */}
            <Modal isOpen={anulandoPago !== null} onClose={() => setAnulandoPago(null)}
                title={anulandoPago ? `Anular pago — ${money(anulandoPago.monto)} del ${fdate(anulandoPago.fecha)}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulandoPago(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitAnularPago} disabled={saving || motivoAnular.trim().length < 5}>
                            {saving ? 'Anulando...' : 'Sí, anular pago'}
                        </Button>
                    </>
                }
            >
                {anulandoPago && (
                    <div className="space-y-3">
                        <Callout variant="warning">
                            {anulandoPago.proveedor_adelanto_id
                                ? <>El monto volverá como saldo del <strong>adelanto #{anulandoPago.proveedor_adelanto_id}</strong> y la compra quedará con más saldo pendiente.</>
                                : <>Se revierte el egreso en tesorería (el dinero "vuelve" a la cuenta) y la compra queda con más saldo pendiente.</>}
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivoAnular}
                            onChange={e => setMotivoAnular(e.target.value)}
                            placeholder="Ej.: se registró doble / monto equivocado"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
