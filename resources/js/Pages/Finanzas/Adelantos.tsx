import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Eye, Ban } from 'lucide-react';
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
    observacion: string | null;
    entrada?: { id: number; numero_documento: string | null } | null;
    user?: { name: string } | null;
}

interface Adelanto extends Record<string, unknown> {
    id: number;
    fecha: string;
    monto: string;
    saldo: string;
    estado: string;
    referencia: string | null;
    observacion: string | null;
    proveedor?: { id: number; razon_social?: string; nombre_comercial?: string } | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    aplicaciones: Aplicacion[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    adelantos: Paginado<Adelanto>;
    totalActivo: number;
    estado: string;
    proveedores: { id: number; razon_social?: string; nombre_comercial?: string }[];
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
}

const hoy = () => new Date().toISOString().slice(0, 10);
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreProveedor = (p?: { razon_social?: string; nombre_comercial?: string } | null) =>
    p?.razon_social ?? p?.nombre_comercial ?? '—';

const emptyForm = () => ({
    proveedor_id: '', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
});

export default function Adelantos({ adelantos, totalActivo, estado, proveedores, metodosPago, cuentas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo] = useState(false);
    const [anulando, setAnulando]     = useState<Adelanto | null>(null);
    const [detalle, setDetalle]       = useState<Adelanto | null>(null);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});
    const [form, setForm]             = useState(emptyForm());
    const [formAnular, setFormAnular] = useState({ accion: 'devuelto', motivo: '' });

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
        router.post(route('finanzas.adelantos.store'), {
            ...form,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
        } as any, {
            onSuccess: () => { setModalNuevo(false); setForm(emptyForm()); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnular() {
        if (!anulando) return;
        setSaving(true);
        router.post(route('finanzas.adelantos.anular', anulando.id), formAnular as any, {
            onSuccess: () => { setAnulando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<Adelanto>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (a) => <span className="text-sm">{new Date(a.fecha + 'T00:00:00').toLocaleDateString('es-PE')}</span>,
        },
        { key: 'proveedor', label: 'Proveedor', render: (a) => <span className="font-medium">{nombreProveedor(a.proveedor)}</span> },
        { key: 'monto', label: 'Entregado', render: (a) => <span>{money(a.monto)}</span> },
        {
            key: 'saldo', label: 'Saldo a favor',
            render: (a) => a.estado === 'activo'
                ? <span className="font-bold" style={{ color: 'var(--color-success)' }}>{money(a.saldo)}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>{money(a.saldo)}</span>,
        },
        {
            key: 'estado', label: 'Estado',
            render: (a) => (
                <Badge variant={a.estado === 'activo' ? 'primary' : a.estado === 'aplicado' ? 'success' : 'secondary'}>
                    {a.estado === 'activo' ? 'Activo' : a.estado === 'aplicado' ? 'Aplicado' : a.estado === 'devuelto' ? 'Devuelto' : 'Anulado'}
                </Badge>
            ),
        },
        {
            key: 'referencia', label: 'Referencia',
            render: (a) => a.referencia
                ? <span className="text-sm font-mono">{a.referencia}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
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
                        <button onClick={() => { setErrors({}); setFormAnular({ accion: 'devuelto', motivo: '' }); setAnulando(a); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Devolver / anular"
                            style={{ color: 'var(--color-danger)' }}>
                            <Ban size={15} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Adelantos a proveedores">
            <PageHeader
                title="Adelantos a proveedores"
                subtitle="Dinero entregado antes de recibir el material (activo a favor)"
                actions={
                    <div className="flex items-center gap-3">
                        <div className="px-4 py-2 rounded-xl text-right"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-success, #16a34a) 10%, var(--color-bg))' }}>
                            <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                Saldo a favor
                            </p>
                            <p className="text-lg font-bold" style={{ color: 'var(--color-success)' }}>{money(totalActivo)}</p>
                        </div>
                        <Button onClick={() => { setErrors({}); setForm(emptyForm()); setModalNuevo(true); }}>
                            <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo adelanto
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
                    onChange={(v) => router.get(route('finanzas.adelantos.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table data={adelantos.data} columns={columns}
                searchPlaceholder="Buscar proveedor..." emptyMessage="No hay adelantos registrados" />

            {/* Modal nuevo adelanto */}
            <Modal isOpen={modalNuevo} onClose={() => setModalNuevo(false)} title="Nuevo adelanto a proveedor" size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalNuevo(false)}>Cancelar</Button>
                        <Button onClick={submitNuevo} disabled={saving}>{saving ? 'Guardando...' : 'Guardar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <SearchableSelect label="Proveedor" required
                        options={proveedores.map(p => ({ value: String(p.id), label: nombreProveedor(p) }))}
                        value={form.proveedor_id}
                        onChange={v => setForm(f => ({ ...f, proveedor_id: String(v) }))}
                        placeholder="— Seleccionar proveedor —"
                        error={errors.proveedor_id}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Fecha" required type="date" value={form.fecha}
                            onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Monto entregado" required type="number" min="0.01" step="0.01" value={form.monto}
                            onChange={e => setForm(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                    </div>
                    <Select label="Método de pago"
                        options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                        value={form.metodo_pago_id}
                        onChange={v => { const cts = cuentasDeMetodo(String(v)); setForm(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                        placeholder="— Seleccionar —"
                    />
                    <Select label="Cuenta origen"
                        options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                        value={form.cuenta_id}
                        onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                        placeholder="— Seleccionar —"
                    />
                    <Input label="Referencia (operación, voucher...)" value={form.referencia}
                        onChange={e => setForm(f => ({ ...f, referencia: e.target.value }))} />
                    <Input label="Observación" value={form.observacion}
                        onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))} />
                </div>
            </Modal>

            {/* Modal devolver / anular */}
            <Modal isOpen={anulando !== null} onClose={() => setAnulando(null)}
                title={anulando ? `Cerrar adelanto — ${nombreProveedor(anulando.proveedor)}` : ''} size="sm"
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
                            { value: 'devuelto', label: 'El proveedor devolvió el dinero' },
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
                title={detalle ? `Aplicaciones — ${nombreProveedor(detalle.proveedor)}` : ''} size="md"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    detalle.aplicaciones.length === 0
                        ? <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Aún no se aplicó contra ninguna entrada</p>
                        : (
                            <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                {detalle.aplicaciones.map(ap => (
                                    <div key={ap.id} className="py-2 flex justify-between items-start text-sm">
                                        <div>
                                            <p className="font-medium">{new Date(ap.fecha + 'T00:00:00').toLocaleDateString('es-PE')}</p>
                                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {[ap.entrada ? `Entrada ${ap.entrada.numero_documento ?? '#' + ap.entrada.id}` : null, ap.user?.name, ap.observacion].filter(Boolean).join(' · ') || '—'}
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
