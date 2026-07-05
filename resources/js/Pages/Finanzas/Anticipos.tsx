import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Eye, PackageCheck, Ban } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Tabs from '@/Components/UI/Tabs';
import type { PageProps } from '@/types';

interface Aplicacion {
    id: number;
    fecha: string;
    monto: string;
    cantidad: string | null;
    observacion: string | null;
    venta?: { numero: string | null } | null;
    user?: { name: string } | null;
}

interface Anticipo extends Record<string, unknown> {
    id: number;
    fecha: string;
    monto: string;
    saldo: string;
    tipo_valorizacion: 'monto' | 'material';
    cantidad: string | null;
    cantidad_pendiente: string | null;
    estado: string;
    observacion: string | null;
    cliente?: { id: number; nombres?: string; apellidos?: string; razon_social?: string } | null;
    producto?: { id: number; nombre: string; precio_venta: string } | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    aplicaciones: Aplicacion[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    anticipos: Paginado<Anticipo>;
    totalPasivo: number;
    estado: string;
    clientes: { id: number; nombres?: string; apellidos?: string; razon_social?: string }[];
    productos: { id: number; nombre: string; precio_venta: string }[];
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
}

const hoy = () => new Date().toISOString().slice(0, 10);
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreCliente = (c?: { nombres?: string; apellidos?: string; razon_social?: string } | null) =>
    c?.razon_social ?? (`${c?.nombres ?? ''} ${c?.apellidos ?? ''}`.trim() || '—');

/** Pasivo actual del anticipo: material → cantidad pendiente × precio del día. */
const valorHoy = (a: Anticipo) =>
    a.tipo_valorizacion === 'material' && a.producto && a.cantidad_pendiente !== null
        ? Number(a.cantidad_pendiente) * Number(a.producto.precio_venta)
        : Number(a.saldo);

const emptyForm = () => ({
    cliente_id: '', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '',
    tipo_valorizacion: 'monto', producto_id: '', cantidad: '', observacion: '',
});

export default function Anticipos({ anticipos, totalPasivo, estado, clientes, productos, metodosPago, cuentas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo]   = useState(false);
    const [aplicando, setAplicando]     = useState<Anticipo | null>(null);
    const [anulando, setAnulando]       = useState<Anticipo | null>(null);
    const [detalle, setDetalle]         = useState<Anticipo | null>(null);
    const [saving, setSaving]           = useState(false);
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [form, setForm]               = useState(emptyForm());
    const [formAplicar, setFormAplicar] = useState({ fecha: hoy(), monto: '', cantidad: '', observacion: '' });
    const [formAnular, setFormAnular]   = useState({ accion: 'devuelto', motivo: '' });

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
        return cuentas;
    }

    function submitNuevo() {
        setSaving(true);
        router.post(route('finanzas.anticipos.store'), {
            ...form,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
            producto_id:    form.tipo_valorizacion === 'material' ? (form.producto_id || null) : null,
            cantidad:       form.tipo_valorizacion === 'material' ? (form.cantidad || null) : null,
        } as any, {
            onSuccess: () => { setModalNuevo(false); setForm(emptyForm()); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAplicar() {
        if (!aplicando) return;
        setSaving(true);
        router.post(route('finanzas.anticipos.aplicar', aplicando.id), {
            ...formAplicar,
            cantidad: aplicando.tipo_valorizacion === 'material' ? (formAplicar.cantidad || null) : null,
        } as any, {
            onSuccess: () => { setAplicando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnular() {
        if (!anulando) return;
        setSaving(true);
        router.post(route('finanzas.anticipos.anular', anulando.id), formAnular as any, {
            onSuccess: () => { setAnulando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<Anticipo>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (a) => <span className="text-sm">{new Date(a.fecha + 'T00:00:00').toLocaleDateString('es-PE')}</span>,
        },
        { key: 'cliente', label: 'Cliente', render: (a) => <span className="font-medium">{nombreCliente(a.cliente)}</span> },
        {
            key: 'tipo_valorizacion', label: 'Modalidad',
            render: (a) => a.tipo_valorizacion === 'material'
                ? <Badge variant="primary">Material: {a.producto?.nombre ?? '—'}</Badge>
                : <Badge variant="secondary">Dinero</Badge>,
        },
        { key: 'monto', label: 'Recibido', render: (a) => <span>{money(a.monto)}</span> },
        {
            key: 'pendiente', label: 'Pendiente',
            render: (a) => a.tipo_valorizacion === 'material'
                ? <span className="text-sm">{Number(a.cantidad_pendiente ?? 0)} und</span>
                : <span className="text-sm">{money(a.saldo)}</span>,
        },
        {
            key: 'valor_hoy', label: 'Pasivo a hoy',
            render: (a) => a.estado === 'activo'
                ? <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(valorHoy(a))}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'estado', label: 'Estado',
            render: (a) => (
                <Badge variant={a.estado === 'activo' ? 'warning' : a.estado === 'aplicado' ? 'success' : 'secondary'}>
                    {a.estado === 'activo' ? 'Activo' : a.estado === 'aplicado' ? 'Aplicado' : a.estado === 'devuelto' ? 'Devuelto' : 'Anulado'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (a) => (
                <div className="flex items-center gap-1.5">
                    <button onClick={() => setDetalle(a)} className="p-1.5 rounded-lg hover:bg-black/5" title="Ver aplicaciones"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <Eye size={15} />
                    </button>
                    {a.estado === 'activo' && (
                        <>
                            <button onClick={() => { setErrors({}); setFormAplicar({ fecha: hoy(), monto: String(a.saldo), cantidad: '', observacion: '' }); setAplicando(a); }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Registrar entrega/aplicación"
                                style={{ color: 'var(--color-primary)' }}>
                                <PackageCheck size={15} />
                            </button>
                            <button onClick={() => { setErrors({}); setFormAnular({ accion: 'devuelto', motivo: '' }); setAnulando(a); }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Devolver / anular"
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
        <AppLayout title="Anticipos de clientes">
            <PageHeader
                title="Anticipos de clientes"
                subtitle="Dinero recibido por adelantado a cambio de mercadería futura"
                actions={
                    <div className="flex items-center gap-3">
                        <div className="px-4 py-2 rounded-xl text-right"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, var(--color-bg))' }}>
                            <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                Pasivo a precio del día
                            </p>
                            <p className="text-lg font-bold" style={{ color: 'var(--color-danger)' }}>{money(totalPasivo)}</p>
                        </div>
                        <Button onClick={() => { setErrors({}); setForm(emptyForm()); setModalNuevo(true); }}>
                            <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo anticipo
                        </Button>
                    </div>
                }
            />

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'activos', label: 'Activos' },
                        { value: 'todos',   label: 'Todos' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.anticipos.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table data={anticipos.data} columns={columns}
                searchPlaceholder="Buscar cliente..." emptyMessage="No hay anticipos registrados" />

            {/* Modal nuevo anticipo */}
            <Modal isOpen={modalNuevo} onClose={() => setModalNuevo(false)} title="Nuevo anticipo de cliente" size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalNuevo(false)}>Cancelar</Button>
                        <Button onClick={submitNuevo} disabled={saving}>{saving ? 'Guardando...' : 'Guardar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <SearchableSelect label="Cliente" required
                        options={clientes.map(c => ({ value: String(c.id), label: nombreCliente(c) }))}
                        value={form.cliente_id}
                        onChange={v => setForm(f => ({ ...f, cliente_id: String(v) }))}
                        placeholder="— Seleccionar cliente —"
                        error={errors.cliente_id}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Fecha" required type="date" value={form.fecha}
                            onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Monto recibido" required type="number" min="0.01" step="0.01" value={form.monto}
                            onChange={e => setForm(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                    </div>
                    <Select label="Método de pago"
                        options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                        value={form.metodo_pago_id}
                        onChange={v => { const cts = cuentasDeMetodo(String(v)); setForm(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                        placeholder="— Seleccionar —"
                    />
                    <Select label="Cuenta destino"
                        options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                        value={form.cuenta_id}
                        onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                        placeholder="— Seleccionar —"
                    />
                    <Select label="Modalidad" required
                        options={[
                            { value: 'monto',    label: 'Dinero (el pasivo es el saldo en soles)' },
                            { value: 'material', label: 'Material (se valoriza a precio del día)' },
                        ]}
                        value={form.tipo_valorizacion}
                        onChange={v => setForm(f => ({ ...f, tipo_valorizacion: String(v) }))}
                        error={errors.tipo_valorizacion}
                    />
                    {form.tipo_valorizacion === 'material' && (
                        <>
                            <SearchableSelect label="Producto comprometido" required
                                options={productos.map(p => ({ value: String(p.id), label: `${p.nombre} (venta: ${money(p.precio_venta)})` }))}
                                value={form.producto_id}
                                onChange={v => setForm(f => ({ ...f, producto_id: String(v) }))}
                                placeholder="— Seleccionar producto —"
                                error={errors.producto_id}
                            />
                            <Input label="Cantidad comprometida" required type="number" min="0.0001" step="any" value={form.cantidad}
                                onChange={e => setForm(f => ({ ...f, cantidad: e.target.value }))} error={errors.cantidad} />
                        </>
                    )}
                    <Input label="Observación" value={form.observacion}
                        onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))} />
                </div>
            </Modal>

            {/* Modal aplicar (entrega de material / uso del anticipo) */}
            <Modal isOpen={aplicando !== null} onClose={() => setAplicando(null)}
                title={aplicando ? `Aplicar anticipo — ${nombreCliente(aplicando.cliente)}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAplicando(null)}>Cancelar</Button>
                        <Button onClick={submitAplicar} disabled={saving}>{saving ? 'Guardando...' : 'Registrar'}</Button>
                    </>
                }
            >
                {aplicando && (
                    <div className="space-y-4">
                        <div className="flex justify-between text-sm px-1">
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                {aplicando.tipo_valorizacion === 'material'
                                    ? `Pendiente: ${Number(aplicando.cantidad_pendiente ?? 0)} und de ${aplicando.producto?.nombre}`
                                    : `Saldo del anticipo: ${money(aplicando.saldo)}`}
                            </span>
                        </div>
                        <Input label="Fecha" required type="date" value={formAplicar.fecha}
                            onChange={e => setFormAplicar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Monto aplicado (S/)" required type="number" min="0.01" step="0.01" value={formAplicar.monto}
                            onChange={e => setFormAplicar(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                        {aplicando.tipo_valorizacion === 'material' && (
                            <Input label="Cantidad entregada" required type="number" min="0.0001" step="any" value={formAplicar.cantidad}
                                onChange={e => setFormAplicar(f => ({ ...f, cantidad: e.target.value }))} error={errors.cantidad} />
                        )}
                        <Input label="Observación" value={formAplicar.observacion}
                            onChange={e => setFormAplicar(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal devolver / anular */}
            <Modal isOpen={anulando !== null} onClose={() => setAnulando(null)}
                title={anulando ? `Cerrar anticipo — ${nombreCliente(anulando.cliente)}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulando(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitAnular} disabled={saving}>{saving ? 'Guardando...' : 'Confirmar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Select label="Acción" required
                        options={[
                            { value: 'devuelto', label: 'Se devolvió el dinero al cliente' },
                            { value: 'anulado',  label: 'Anular (registro erróneo)' },
                        ]}
                        value={formAnular.accion}
                        onChange={v => setFormAnular(f => ({ ...f, accion: String(v) }))}
                    />
                    <Input label="Motivo" required value={formAnular.motivo}
                        onChange={e => setFormAnular(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                </div>
            </Modal>

            {/* Modal detalle aplicaciones */}
            <Modal isOpen={detalle !== null} onClose={() => setDetalle(null)}
                title={detalle ? `Aplicaciones — ${nombreCliente(detalle.cliente)}` : ''} size="md"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    detalle.aplicaciones.length === 0
                        ? <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Sin aplicaciones registradas</p>
                        : (
                            <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                {detalle.aplicaciones.map(ap => (
                                    <div key={ap.id} className="py-2 flex justify-between items-start text-sm">
                                        <div>
                                            <p className="font-medium">{new Date(ap.fecha + 'T00:00:00').toLocaleDateString('es-PE')}</p>
                                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {[ap.cantidad ? `${Number(ap.cantidad)} und` : null, ap.venta?.numero ? `Venta ${ap.venta.numero}` : null, ap.user?.name, ap.observacion].filter(Boolean).join(' · ') || '—'}
                                            </p>
                                        </div>
                                        <span className="font-bold">{money(ap.monto)}</span>
                                    </div>
                                ))}
                            </div>
                        )
                )}
            </Modal>
        </AppLayout>
    );
}
