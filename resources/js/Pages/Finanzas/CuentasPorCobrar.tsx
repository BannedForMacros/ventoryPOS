import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { HandCoins, Eye, Receipt, ChevronDown, ChevronRight, Search, Pencil, Trash2 } from 'lucide-react';
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

interface Abono {
    id: number;
    fecha: string;
    monto: string;
    referencia: string | null;
    observacion: string | null;
    metodo_pago_id?: number | null;
    cuenta_id?: number | null;
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

interface VentaItemCxc {
    id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: string;
    precio_unitario: string;
    descuento_item: string;
    subtotal: string;
}

interface VentaCxc extends Record<string, unknown> {
    id: number;
    numero: string | null;
    fecha_venta: string;
    fecha_vencimiento: string | null;
    tipo_comprobante?: string;
    total: string;
    subtotal?: string;
    igv?: string;
    descuento_total?: string;
    observacion?: string | null;
    monto_pagado: string;
    saldo_pendiente: string;
    cliente?: { id: number; nombres?: string; apellidos?: string; razon_social?: string } | null;
    user?: { name: string } | null;
    caja?: { nombre: string } | null;
    items?: VentaItemCxc[];
    abonos: Abono[];
    pagos: PagoInicial[];
}

interface TurnoLite {
    id: number; user_id: number; caja_id: number; fecha_apertura: string;
    estado: 'abierto' | 'cerrado';
    user?: { id: number; name: string } | null;
    caja?: { id: number; nombre: string } | null;
}

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Props extends PageProps {
    ventas: Paginado<VentaCxc>;
    totalPendiente: number;
    estado: string;
    busqueda: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    puede: { editar: boolean; eliminar: boolean };
    turnos: TurnoLite[];
    turnoActivoId: number | null;
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreCliente = (v: VentaCxc) =>
    v.cliente?.razon_social ?? (`${v.cliente?.nombres ?? ''} ${v.cliente?.apellidos ?? ''}`.trim() || '—');

export default function CuentasPorCobrar({ ventas, totalPendiente, estado, busqueda, metodosPago, cuentas, puede, turnos, turnoActivoId }: Props) {
    const { flash } = usePage<Props>().props;
    // "Afecta caja a:" — texto del turno (#id · fecha hora · usuario · caja · abierto).
    const turnoLabel = (t: TurnoLite) => {
        const f = new Date(t.fecha_apertura).toLocaleDateString('es-PE');
        const hora = new Date(t.fecha_apertura).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        return [`#${t.id}`, `${f} ${hora}`, t.user?.name, t.caja?.nombre].filter(Boolean).join(' · ')
            + (t.estado === 'abierto' ? ' · abierto' : '');
    };
    const [abonando, setAbonando] = useState<VentaCxc | null>(null);
    const [detalle, setDetalle]   = useState<VentaCxc | null>(null);
    // Colapsable de la venta relacionada dentro del modal de detalle.
    const [ventaAbierta, setVentaAbierta] = useState(false);
    const [saving, setSaving]     = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        monto: '', fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '', turno_id: '',
    });
    // Edición / anulación de un abono ya registrado (según permisos).
    const [editandoAbono, setEditandoAbono] = useState<Abono | null>(null);
    const [anulandoAbono, setAnulandoAbono] = useState<Abono | null>(null);
    const [motivoAnular, setMotivoAnular]   = useState('');
    const [formAbono, setFormAbono] = useState({
        monto: '', fecha: '', metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
    });

    /** Tope al editar un abono: saldo pendiente de la venta + monto actual del abono. */
    const topeEditar = detalle && editandoAbono
        ? Math.round((Number(detalle.saldo_pendiente) + Number(editandoAbono.monto)) * 100) / 100
        : 0;

    function abrirEditarAbono(a: Abono) {
        setErrors({});
        setFormAbono({
            monto:          String(Number(a.monto)),
            fecha:          a.fecha.slice(0, 10),
            metodo_pago_id: a.metodo_pago_id ? String(a.metodo_pago_id) : '',
            cuenta_id:      a.cuenta_id ? String(a.cuenta_id) : '',
            referencia:     a.referencia ?? '',
            observacion:    a.observacion ?? '',
        });
        setEditandoAbono(a);
    }

    function submitEditarAbono() {
        if (!editandoAbono) return;
        setSaving(true);
        router.put(route('finanzas.cxc.abonos.update', editandoAbono.id), {
            monto:          formAbono.monto,
            fecha:          formAbono.fecha,
            metodo_pago_id: formAbono.metodo_pago_id || null,
            cuenta_id:      formAbono.cuenta_id || null,
            referencia:     formAbono.referencia || null,
            observacion:    formAbono.observacion || null,
        } as any, {
            onSuccess: () => { setEditandoAbono(null); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnularAbono() {
        if (!anulandoAbono) return;
        setSaving(true);
        router.delete(route('finanzas.cxc.abonos.destroy', anulandoAbono.id), {
            data: { motivo: motivoAnular.trim() },
            onSuccess: () => { setAnulandoAbono(null); setMotivoAnular(''); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        } as any);
    }

    // Búsqueda del lado del SERVIDOR (con debounce): busca en TODO el histórico,
    // no solo en la página cargada. Así una cuenta ya saldada (venta antigua)
    // aparece al buscarla por número o cliente aunque esté en otra página.
    const [busq, setBusq] = useState(busqueda ?? '');
    useEffect(() => {
        const t = setTimeout(() => {
            if ((busq ?? '') !== (busqueda ?? '')) {
                router.get(route('finanzas.cxc.index'),
                    { estado, busqueda: busq || undefined },
                    { preserveState: true, replace: true, preserveScroll: true });
            }
        }, 400);
        return () => clearTimeout(t);
    }, [busq]);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function irAPagina(page: number) {
        router.get(route('finanzas.cxc.index'),
            { estado, busqueda: busq || undefined, page },
            { preserveState: true, replace: true, preserveScroll: true });
    }

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
        setForm({
            monto: String(v.saldo_pendiente), fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
            // Cobro entra normalmente a la caja del cajero: preselecciona el turno activo.
            turno_id: turnoActivoId ? String(turnoActivoId) : '',
        });
    }

    function submitAbono() {
        if (!abonando) return;
        setSaving(true);
        router.post(route('finanzas.cxc.abonar', abonando.id), {
            ...form,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
            turno_id:       form.turno_id || null,
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
        { key: 'total', label: 'Total', align: 'right', render: (v) => <span>{money(v.total)}</span> },
        { key: 'monto_pagado', label: 'Pagado', align: 'right', render: (v) => <span style={{ color: 'var(--color-success, #16a34a)' }}>{money(v.monto_pagado)}</span> },
        {
            key: 'saldo_pendiente', label: 'Saldo', sortable: true, align: 'right',
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
                        onClick={() => { setDetalle(v); setVentaAbierta(false); }}
                        className="p-1.5 rounded-lg hover:bg-black/5"
                        title="Ver detalle y trazabilidad"
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
                icon={<HandCoins size={22} />}
                title="Cuentas por cobrar"
                subtitle="Ventas a crédito y abonos de clientes"
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 sm:grid-cols-3 lg:grid-cols-4" stats={[
                    {
                        label: 'Total por cobrar', valor: money(totalPendiente), color: 'danger', destacado: true,
                        icon: <HandCoins size={19} />, sub: 'Saldo pendiente de ventas a crédito',
                    },
                ]} />
            </div>

            <div className="mb-4">
                <Tabs
                    tabs={[
                        { value: 'pendientes', label: 'Con saldo pendiente' },
                        { value: 'todas',      label: 'Todas las ventas a crédito' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.cxc.index'), { estado: v, busqueda: busq || undefined }, { preserveState: true, replace: true })}
                />
            </div>

            {/* Buscador (servidor): encuentra la venta en todo el histórico */}
            <div className="relative mb-4 max-w-md">
                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                <input
                    type="search"
                    value={busq}
                    onChange={e => setBusq(e.target.value)}
                    placeholder="Buscar por número de venta o cliente..."
                    className="w-full pl-9 pr-3 py-2.5 text-sm border rounded-xl focus:outline-none focus:ring-2"
                    style={{
                        borderColor: 'var(--color-border)',
                        backgroundColor: 'var(--color-bg)',
                        color: 'var(--color-text)',
                        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                    } as React.CSSProperties}
                />
            </div>

            <Table
                data={ventas.data}
                columns={columns}
                searchable={false}
                pagination={false}
                emptyMessage={busq ? `No se encontraron cuentas para «${busq}»` : 'No hay ventas a crédito'}
            />

            {/* Paginación del servidor (recorre TODO el histórico, no solo 25) */}
            {ventas.last_page > 1 && (
                <div className="flex items-center justify-between gap-2 mt-4 flex-wrap">
                    <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Página {ventas.current_page} de {ventas.last_page} · {ventas.total} cuenta(s)
                    </span>
                    <div className="flex items-center gap-1">
                        {Array.from({ length: ventas.last_page }, (_, i) => i + 1)
                            .filter(p => p === 1 || p === ventas.last_page || Math.abs(p - ventas.current_page) <= 2)
                            .map((p, idx, arr) => (
                                <span key={p} className="flex items-center gap-1">
                                    {idx > 0 && arr[idx - 1] !== p - 1 && (
                                        <span className="px-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>…</span>
                                    )}
                                    <button
                                        onClick={() => irAPagina(p)}
                                        className="min-w-8 h-8 px-2 rounded-lg text-xs font-medium transition-colors"
                                        style={{
                                            backgroundColor: p === ventas.current_page ? 'var(--color-primary)' : 'transparent',
                                            color: p === ventas.current_page ? '#fff' : 'var(--color-text-muted)',
                                            border: `1px solid ${p === ventas.current_page ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                        }}
                                    >
                                        {p}
                                    </button>
                                </span>
                            ))}
                    </div>
                </div>
            )}

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
                        <StatGrid stats={[
                            { label: 'Total', valor: money(abonando.total) },
                            { label: 'Pagado', valor: money(abonando.monto_pagado), color: 'success' },
                            { label: 'Saldo pendiente', valor: money(abonando.saldo_pendiente), color: 'danger', destacado: true },
                        ]} />
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Monto del abono" required type="number" min="0.01" step="0.01"
                                max={Number(abonando.saldo_pendiente)}
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
                            const saldo = Number(abonando.saldo_pendiente);
                            const montoNum = Number(form.monto);
                            const nuevo = Math.round((saldo - montoNum) * 100) / 100;
                            if (montoNum > saldo + 0.009) {
                                return (
                                    <Callout variant="danger">
                                        El abono ({money(montoNum)}) no puede superar el saldo pendiente ({money(saldo)}).
                                    </Callout>
                                );
                            }
                            return nuevo <= 0.009
                                ? <Callout variant="success" title="Con este abono la venta queda SALDADA" />
                                : <Callout variant="info" title="Nuevo saldo pendiente" aside={money(nuevo)} />;
                        })()}
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
                            <Callout variant="info">
                                El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === form.metodo_pago_id)?.nombre}»</strong>,
                                que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                            </Callout>
                        )}
                        <Input label="Referencia (operación, voucher...)"
                            value={form.referencia}
                            onChange={e => setForm(f => ({ ...f, referencia: e.target.value }))}
                        />
                        <Input label="Observación"
                            value={form.observacion}
                            onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))}
                        />

                        {/* "Afecta caja a:" — a qué caja/turno entra el cobro. Por defecto
                            el turno activo del cajero; se puede elegir "Sin turno" u otro. */}
                        {turnos.length > 0 && (
                            <Select label="Afecta caja a (turno)"
                                options={[
                                    { value: '', label: 'Sin turno (no afecta caja)' },
                                    ...turnos.map(t => ({ value: String(t.id), label: turnoLabel(t) })),
                                ]}
                                value={form.turno_id}
                                onChange={v => setForm(f => ({ ...f, turno_id: String(v) }))}
                                hint="A qué caja entra el efectivo, para que la consolidación de ese turno lo sume."
                            />
                        )}
                    </div>
                )}
            </Modal>

            {/* Modal detalle de la cuenta por cobrar — trazabilidad completa */}
            <Modal
                isOpen={detalle !== null}
                onClose={() => setDetalle(null)}
                title={detalle ? `Detalle — venta ${detalle.numero ?? ''} · ${nombreCliente(detalle)}` : ''}
                size="3xl"
                footer={
                    <>
                        {detalle && Number(detalle.saldo_pendiente) > 0 && (
                            <Button variant="primary" onClick={() => { const d = detalle; setDetalle(null); abrirAbono(d); }}>
                                <HandCoins size={15} className="mr-1.5" /> Registrar abono
                            </Button>
                        )}
                        <Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>
                    </>
                }
            >
                {detalle && (
                    <div className="space-y-4">
                        {/* Resumen del crédito */}
                        <StatGrid stats={[
                            { label: 'Total', valor: money(detalle.total) },
                            { label: 'Pagado', valor: money(detalle.monto_pagado), color: 'success' },
                            { label: 'Saldo pendiente', valor: money(detalle.saldo_pendiente), color: 'danger', destacado: true },
                        ]} />

                        {/* Datos generales / trazabilidad */}
                        <div className="rounded-xl p-3 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2.5"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <Dato label="Cliente" valor={nombreCliente(detalle)} />
                            <Dato label="Comprobante" valor={detalle.tipo_comprobante ?? '—'} capitalize />
                            <Dato label="Vendedor" valor={detalle.user?.name ?? '—'} />
                            <Dato label="Fecha de venta" valor={new Date(detalle.fecha_venta).toLocaleString('es-PE')} />
                            <Dato label="Vencimiento" valor={detalle.fecha_vencimiento
                                ? new Date(detalle.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-PE') : '—'} />
                            <Dato label="Caja" valor={detalle.caja?.nombre ?? '—'} />
                        </div>

                        {/* Venta relacionada — minimizada, se despliega con clic */}
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            <button
                                onClick={() => setVentaAbierta(v => !v)}
                                className="w-full flex items-center justify-between px-3 py-2.5 transition-colors hover:bg-black/[0.03]"
                                style={{ backgroundColor: 'var(--color-bg)' }}
                            >
                                <span className="flex items-center gap-2 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                    <Receipt size={15} style={{ color: 'var(--color-primary)' }} />
                                    Venta relacionada {detalle.numero ?? ''}
                                    <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                        · {detalle.items?.length ?? 0} producto(s)
                                    </span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="font-bold text-sm" style={{ color: 'var(--color-primary)' }}>{money(detalle.total)}</span>
                                    {ventaAbierta ? <ChevronDown size={16} style={{ color: 'var(--color-text-muted)' }} /> : <ChevronRight size={16} style={{ color: 'var(--color-text-muted)' }} />}
                                </span>
                            </button>

                            {ventaAbierta && (
                                <div className="p-3 space-y-3" style={{ borderTop: '1px solid var(--color-border)' }}>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr style={{ color: 'var(--color-text-muted)' }}>
                                                    <th className="text-left py-1.5 font-medium">Producto</th>
                                                    <th className="text-right py-1.5 font-medium">Cant.</th>
                                                    <th className="text-right py-1.5 font-medium">P. Unit.</th>
                                                    <th className="text-right py-1.5 font-medium">Dcto.</th>
                                                    <th className="text-right py-1.5 font-medium">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(detalle.items ?? []).map(it => (
                                                    <tr key={it.id} style={{ borderTop: '1px solid var(--color-border)' }}>
                                                        <td className="py-1.5">
                                                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</span>
                                                            <span className="block text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{it.unidad_nombre}</span>
                                                        </td>
                                                        <td className="text-right" style={{ color: 'var(--color-text)' }}>{Number(it.cantidad)}</td>
                                                        <td className="text-right" style={{ color: 'var(--color-text)' }}>{money(it.precio_unitario)}</td>
                                                        <td className="text-right" style={{ color: Number(it.descuento_item) > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                                            {Number(it.descuento_item) > 0 ? '- ' + money(it.descuento_item) : '—'}
                                                        </td>
                                                        <td className="text-right font-medium" style={{ color: 'var(--color-text)' }}>{money(it.subtotal)}</td>
                                                    </tr>
                                                ))}
                                                {(detalle.items?.length ?? 0) === 0 && (
                                                    <tr><td colSpan={5} className="py-3 text-center" style={{ color: 'var(--color-text-muted)' }}>Sin productos</td></tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Resumen financiero de la venta */}
                                    <div className="flex flex-col gap-1 items-end pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                                        <Resumen label="Subtotal" valor={money(detalle.subtotal ?? 0)} />
                                        {Number(detalle.descuento_total ?? 0) > 0 && <Resumen label="Descuento" valor={'- ' + money(detalle.descuento_total)} danger />}
                                        <Resumen label="IGV" valor={money(detalle.igv ?? 0)} />
                                        <Resumen label="Total" valor={money(detalle.total)} bold />
                                    </div>

                                    {detalle.observacion && (
                                        <p className="text-xs pt-1" style={{ color: 'var(--color-text-muted)' }}>
                                            <span className="font-medium">Observación:</span> {detalle.observacion}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Historial de pagos y abonos */}
                        <div>
                            <p className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text)' }}>
                                Historial de pagos y abonos
                            </p>
                            {(puede.editar || puede.eliminar) ? (
                                (() => {
                                    const pagosIniciales = detalle.pagos.filter(p => Number(p.monto) - Number(p.vuelto) > 0);
                                    const totalFilas = pagosIniciales.length + detalle.abonos.length;
                                    if (totalFilas === 0) {
                                        return <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin pagos registrados</p>;
                                    }
                                    return (
                                        <div className="space-y-3">
                                            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                                {pagosIniciales.map((p, idx) => (
                                                    <div key={`pi-${p.id}`} className="flex items-center gap-3 px-4 py-2.5"
                                                        style={{
                                                            borderBottom: idx < totalFilas - 1 ? '1px solid var(--color-border)' : undefined,
                                                            backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                        }}>
                                                        <Badge variant="primary">Pago inicial</Badge>
                                                        <div className="flex-1 min-w-0 text-xs">
                                                            <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                                                {new Date(detalle.fecha_venta).toLocaleDateString('es-PE')}
                                                                <span className="ml-2 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                                    {[p.metodo_pago?.nombre, 'al momento de la venta'].filter(Boolean).join(' · ')}
                                                                </span>
                                                            </p>
                                                            {detalle.user?.name && (
                                                                <p className="truncate" style={{ color: 'var(--color-text-muted)' }}>por {detalle.user.name}</p>
                                                            )}
                                                        </div>
                                                        <span className="font-bold text-sm whitespace-nowrap" style={{ color: 'var(--color-success)' }}>
                                                            +{money(Number(p.monto) - Number(p.vuelto))}
                                                        </span>
                                                    </div>
                                                ))}
                                                {detalle.abonos.map((a, idx) => (
                                                    <div key={`ab-${a.id}`} className="flex items-center gap-3 px-4 py-2.5"
                                                        style={{
                                                            borderBottom: pagosIniciales.length + idx < totalFilas - 1 ? '1px solid var(--color-border)' : undefined,
                                                            backgroundColor: (pagosIniciales.length + idx) % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                        }}>
                                                        <Badge variant="success">Abono</Badge>
                                                        <div className="flex-1 min-w-0 text-xs">
                                                            <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                                                {new Date(a.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}
                                                                <span className="ml-2 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                                    {[a.metodo_pago?.nombre, a.cuenta?.nombre, a.referencia].filter(Boolean).join(' · ') || '—'}
                                                                </span>
                                                            </p>
                                                            {(a.observacion || a.user?.name) && (
                                                                <p className="truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                                    {[a.observacion, a.user?.name ? `por ${a.user.name}` : null].filter(Boolean).join(' · ')}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <span className="font-bold text-sm whitespace-nowrap" style={{ color: 'var(--color-success)' }}>
                                                            +{money(a.monto)}
                                                        </span>
                                                        <div className="flex items-center gap-1 flex-shrink-0">
                                                            {puede.editar && (
                                                                <button onClick={() => abrirEditarAbono(a)}
                                                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Editar abono"
                                                                    style={{ color: 'var(--color-primary)' }}>
                                                                    <Pencil size={14} />
                                                                </button>
                                                            )}
                                                            {puede.eliminar && (
                                                                <button onClick={() => { setErrors({}); setMotivoAnular(''); setAnulandoAbono(a); }}
                                                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Anular abono"
                                                                    style={{ color: 'var(--color-danger)' }}>
                                                                    <Trash2 size={14} />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {detalle.abonos.length > 0 && (
                                                <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                                    Editar o anular un abono recalcula tesorería y el saldo pendiente de la venta automáticamente. Todo queda en auditoría.
                                                </p>
                                            )}
                                        </div>
                                    );
                                })()
                            ) : (
                                <Timeline
                                    emptyMessage="Sin pagos registrados"
                                    items={[
                                        ...detalle.pagos.filter(p => Number(p.monto) - Number(p.vuelto) > 0).map(p => ({
                                            fecha: new Date(detalle.fecha_venta).toLocaleDateString('es-PE'),
                                            badge: { texto: 'Pago inicial', variant: 'primary' as const },
                                            tipo: 'ingreso' as const,
                                            detalle: [p.metodo_pago?.nombre, 'al momento de la venta'].filter(Boolean).join(' · '),
                                            user: detalle.user?.name,
                                            monto: Number(p.monto) - Number(p.vuelto),
                                        })),
                                        ...detalle.abonos.map(a => ({
                                            fecha: new Date(a.fecha + 'T00:00:00').toLocaleDateString('es-PE'),
                                            badge: { texto: 'Abono', variant: 'success' as const },
                                            tipo: 'ingreso' as const,
                                            detalle: [a.metodo_pago?.nombre, a.cuenta?.nombre, a.referencia].filter(Boolean).join(' · ') || undefined,
                                            user: a.user?.name,
                                            monto: Number(a.monto),
                                        })),
                                    ]}
                                />
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            {/* Modal editar abono */}
            <Modal isOpen={editandoAbono !== null} onClose={() => setEditandoAbono(null)}
                title={editandoAbono ? `Editar abono — ${money(editandoAbono.monto)} del ${new Date(editandoAbono.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}` : ''} size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditandoAbono(null)}>Cancelar</Button>
                        <Button onClick={submitEditarAbono}
                            disabled={saving || formAbono.monto === '' || Number(formAbono.monto) <= 0
                                || Number(formAbono.monto) > topeEditar + 0.009}>
                            {saving ? 'Guardando...' : 'Guardar cambios'}
                        </Button>
                    </>
                }
            >
                {editandoAbono && (
                    <div className="space-y-4">
                        <Callout variant="info">
                            Al guardar, el ingreso en tesorería se revierte y se vuelve a asentar con los datos nuevos; el saldo pendiente de la venta se recalcula.
                        </Callout>
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Monto" required type="number" min="0.01" step="0.01"
                                max={topeEditar}
                                value={formAbono.monto}
                                onChange={e => setFormAbono(f => ({ ...f, monto: e.target.value }))}
                                error={errors.monto}
                            />
                            <Input label="Fecha" required type="date" value={formAbono.fecha}
                                onChange={e => setFormAbono(f => ({ ...f, fecha: e.target.value }))}
                                error={errors.fecha}
                            />
                        </div>
                        {/* Validación en vivo del tope */}
                        {formAbono.monto !== '' && Number(formAbono.monto) > 0 && (
                            Number(formAbono.monto) > topeEditar + 0.009
                                ? (
                                    <Callout variant="danger">
                                        El abono ({money(formAbono.monto)}) no puede superar el máximo permitido ({money(topeEditar)} = saldo pendiente + monto actual del abono).
                                    </Callout>
                                )
                                : <Callout variant="info" title="Máximo permitido" aside={money(topeEditar)} />
                        )}
                        <Select label="Método de pago"
                            options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                            value={formAbono.metodo_pago_id}
                            onChange={v => {
                                const cts = cuentasDeMetodo(String(v));
                                setFormAbono(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' }));
                            }}
                            placeholder="— Seleccionar —"
                            error={errors.metodo_pago_id}
                        />
                        {cuentasDeMetodo(formAbono.metodo_pago_id).length > 0 && (
                            <Select label="Cuenta destino"
                                options={cuentasDeMetodo(formAbono.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                value={formAbono.cuenta_id}
                                onChange={v => setFormAbono(f => ({ ...f, cuenta_id: String(v) }))}
                                placeholder="— Seleccionar —"
                                error={errors.cuenta_id}
                            />
                        )}
                        <Input label="Referencia (operación, voucher...)" value={formAbono.referencia}
                            onChange={e => setFormAbono(f => ({ ...f, referencia: e.target.value }))} />
                        <Input label="Observación" value={formAbono.observacion}
                            onChange={e => setFormAbono(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal anular abono */}
            <Modal isOpen={anulandoAbono !== null} onClose={() => setAnulandoAbono(null)}
                title={anulandoAbono ? `Anular abono — ${money(anulandoAbono.monto)} del ${new Date(anulandoAbono.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulandoAbono(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitAnularAbono} disabled={saving || motivoAnular.trim().length < 5}>
                            {saving ? 'Anulando...' : 'Sí, anular abono'}
                        </Button>
                    </>
                }
            >
                {anulandoAbono && (
                    <div className="space-y-3">
                        <Callout variant="warning">
                            Se revierte el ingreso en tesorería y la venta recupera su saldo pendiente.
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

/** Celda etiqueta/valor para la trazabilidad. */
function Dato({ label, valor, capitalize }: { label: string; valor: string; capitalize?: boolean }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-medium uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className={`text-sm truncate ${capitalize ? 'capitalize' : ''}`} style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}

/** Fila del resumen financiero de la venta. */
function Resumen({ label, valor, bold, danger }: { label: string; valor: string; bold?: boolean; danger?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-8 text-xs w-full max-w-[220px]">
            <span style={{ color: 'var(--color-text-muted)' }}>{label}</span>
            <span className={bold ? 'font-bold text-sm' : 'font-medium'}
                style={{ color: danger ? 'var(--color-danger)' : 'var(--color-text)' }}>
                {valor}
            </span>
        </div>
    );
}
