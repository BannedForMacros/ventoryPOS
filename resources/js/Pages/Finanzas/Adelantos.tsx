import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Eye, Ban, Handshake, TrendingUp, Pencil, RotateCcw, Users, PackageCheck } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Table, { Column } from '@/Components/UI/Table';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import Timeline from '@/Components/UI/Timeline';
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
    metodo_pago_id?: number | null;
    cuenta_id?: number | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    aplicaciones: Aplicacion[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    adelantos: Paginado<Adelanto>;
    totalActivo: number;
    kpis: { activos: number; proveedores: number; aplicado: number };
    estado: string;
    buscar?: string;
    proveedores: { id: number; razon_social?: string; nombre_comercial?: string }[];
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    puede: { editar: boolean; eliminar: boolean };
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreProveedor = (p?: { razon_social?: string; nombre_comercial?: string } | null) =>
    p?.razon_social ?? p?.nombre_comercial ?? '—';

const emptyForm = () => ({
    proveedor_id: '', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
});

export default function Adelantos({ adelantos, totalActivo, kpis, estado, buscar, proveedores, metodosPago, cuentas, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo] = useState(false);
    const [anulando, setAnulando]     = useState<Adelanto | null>(null);
    const [detalle, setDetalle]       = useState<Adelanto | null>(null);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});
    const [form, setForm]             = useState(emptyForm());
    const [formAnular, setFormAnular] = useState({ accion: 'devuelto', motivo: '' });
    // Edición / reactivación (según permisos).
    const [editando, setEditando]         = useState<Adelanto | null>(null);
    const [reactivando, setReactivando]   = useState<Adelanto | null>(null);
    const [motivoReactivar, setMotivoReactivar] = useState('');
    const [formEditar, setFormEditar] = useState({
        monto: '', fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
    });

    function abrirEditar(a: Adelanto) {
        setErrors({});
        setFormEditar({
            monto:          String(Number(a.monto)),
            fecha:          a.fecha.slice(0, 10),
            metodo_pago_id: a.metodo_pago_id ? String(a.metodo_pago_id) : '',
            cuenta_id:      a.cuenta_id ? String(a.cuenta_id) : '',
            referencia:     a.referencia ?? '',
            observacion:    a.observacion ?? '',
        });
        setEditando(a);
    }

    function submitEditar() {
        if (!editando) return;
        setSaving(true);
        const tieneConsumos = editando.aplicaciones.length > 0;
        router.put(route('finanzas.adelantos.update', editando.id), {
            // Con consumos el monto no se edita (el backend lo prohíbe).
            ...(tieneConsumos ? {} : { monto: formEditar.monto }),
            fecha:          formEditar.fecha,
            metodo_pago_id: formEditar.metodo_pago_id || null,
            cuenta_id:      formEditar.cuenta_id || null,
            referencia:     formEditar.referencia || null,
            observacion:    formEditar.observacion || null,
        } as any, {
            onSuccess: () => { setEditando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitReactivar() {
        if (!reactivando) return;
        setSaving(true);
        router.post(route('finanzas.adelantos.reactivar', reactivando.id), { motivo: motivoReactivar.trim() } as any, {
            onSuccess: () => { setReactivando(null); setMotivoReactivar(''); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

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
        { key: 'proveedor', label: 'Proveedor', sortKey: 'proveedor.razon_social', render: (a) => <span className="font-medium">{nombreProveedor(a.proveedor)}</span> },
        { key: 'monto', label: 'Entregado', align: 'right', render: (a) => <span>{money(a.monto)}</span> },
        {
            key: 'saldo', label: 'Saldo a favor', align: 'right',
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
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (a) => (
                <div className="flex items-center gap-1.5">
                    <button onClick={() => setDetalle(a)} className="p-1.5 rounded-lg hover:bg-black/5" title="Ver aplicaciones"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <Eye size={15} />
                    </button>
                    {a.estado === 'activo' && puede.editar && (
                        <button onClick={() => abrirEditar(a)}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Editar adelanto"
                            style={{ color: 'var(--color-primary)' }}>
                            <Pencil size={15} />
                        </button>
                    )}
                    {a.estado === 'activo' && (
                        <button onClick={() => { setErrors({}); setFormAnular({ accion: 'devuelto', motivo: '' }); setAnulando(a); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Devolver / anular"
                            style={{ color: 'var(--color-danger)' }}>
                            <Ban size={15} />
                        </button>
                    )}
                    {(a.estado === 'anulado' || a.estado === 'devuelto') && puede.editar && (
                        <button onClick={() => { setErrors({}); setMotivoReactivar(''); setReactivando(a); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Reactivar adelanto"
                            style={{ color: 'var(--color-primary)' }}>
                            <RotateCcw size={15} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Adelantos a proveedores">
            <PageHeader
                icon={<Handshake size={22} />}
                title="Adelantos a proveedores"
                subtitle="Dinero entregado antes de recibir el material (activo a favor)"
                actions={
                    <Button onClick={() => { setErrors({}); setForm(emptyForm()); setModalNuevo(true); }}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo adelanto
                    </Button>
                }
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 lg:grid-cols-4" stats={[
                    {
                        label: 'Saldo a favor', valor: money(totalActivo), color: 'success', destacado: true,
                        icon: <TrendingUp size={19} />, sub: 'Pendiente de recibir material',
                    },
                    {
                        label: 'Adelantos activos', valor: kpis.activos, color: 'primary',
                        icon: <Handshake size={19} />, sub: 'Con saldo por aplicar',
                    },
                    {
                        label: 'Proveedores', valor: kpis.proveedores, color: 'primary',
                        icon: <Users size={19} />, sub: 'Con adelanto vigente',
                    },
                    {
                        label: 'Aplicado histórico', valor: money(kpis.aplicado), color: 'muted',
                        icon: <PackageCheck size={19} />, sub: 'Adelantos ya consumidos en compras',
                    },
                ]} />
            </div>

            <FiltrosCard cols={3}>
                <Select label="Estado" value={estado}
                    onChange={(v) => router.get(route('finanzas.adelantos.index'), { estado: v, buscar: buscar || undefined }, { preserveState: true, replace: true })}
                    options={[
                        { value: 'activos',  label: 'Activos' },
                        { value: 'aplicado', label: 'Aplicados' },
                        { value: 'devuelto', label: 'Devueltos' },
                        { value: 'anulado',  label: 'Anulados' },
                        { value: 'todos',    label: 'Todos' },
                    ]} />
            </FiltrosCard>

            <Table data={adelantos} columns={columns}
                searchPlaceholder="Buscar proveedor..." emptyMessage="No hay adelantos registrados"
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('finanzas.adelantos.index'),
                    { estado, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })} />

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
                    {cuentasDeMetodo(form.metodo_pago_id).length > 0 ? (
                        <Select label="Cuenta origen"
                            options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                            value={form.cuenta_id}
                            onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                            placeholder="— Seleccionar —"
                        />
                    ) : (
                        <Callout variant="info">
                            El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === form.metodo_pago_id)?.nombre}»</strong>,
                            que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                        </Callout>
                    )}
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
                    {errors.accion && (
                        <Callout variant="danger">{errors.accion}</Callout>
                    )}
                    <Input label="Motivo" required value={formAnular.motivo}
                        onChange={e => setFormAnular(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                </div>
            </Modal>

            {/* Modal editar adelanto */}
            <Modal isOpen={editando !== null} onClose={() => setEditando(null)}
                title={editando ? `Editar adelanto — ${nombreProveedor(editando.proveedor)}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button onClick={submitEditar} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</Button>
                    </>
                }
            >
                {editando && (
                    <div className="space-y-4">
                        {editando.aplicaciones.length > 0 ? (
                            <Callout variant="warning">
                                Este adelanto ya tiene consumos: el monto no se edita. Puedes corregir fecha, método, cuenta, referencia y observación.
                            </Callout>
                        ) : (
                            <Callout variant="info">
                                Al guardar, el egreso en tesorería se revierte y se vuelve a asentar con los datos nuevos.
                            </Callout>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Fecha" required type="date" value={formEditar.fecha}
                                onChange={e => setFormEditar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                            <Input label="Monto entregado" required type="number" min="0.01" step="0.01"
                                value={formEditar.monto}
                                disabled={editando.aplicaciones.length > 0}
                                onChange={e => setFormEditar(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                        </div>
                        <Select label="Método de pago"
                            options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                            value={formEditar.metodo_pago_id}
                            onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormEditar(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                            placeholder="— Seleccionar —"
                            error={errors.metodo_pago_id}
                        />
                        {cuentasDeMetodo(formEditar.metodo_pago_id).length > 0 && (
                            <Select label="Cuenta origen"
                                options={cuentasDeMetodo(formEditar.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                value={formEditar.cuenta_id}
                                onChange={v => setFormEditar(f => ({ ...f, cuenta_id: String(v) }))}
                                placeholder="— Seleccionar —"
                                error={errors.cuenta_id}
                            />
                        )}
                        <Input label="Referencia (operación, voucher...)" value={formEditar.referencia}
                            onChange={e => setFormEditar(f => ({ ...f, referencia: e.target.value }))} />
                        <Input label="Observación" value={formEditar.observacion}
                            onChange={e => setFormEditar(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal reactivar adelanto */}
            <Modal isOpen={reactivando !== null} onClose={() => setReactivando(null)}
                title={reactivando ? `Reactivar adelanto — ${nombreProveedor(reactivando.proveedor)}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setReactivando(null)}>Cancelar</Button>
                        <Button onClick={submitReactivar} disabled={saving || motivoReactivar.trim().length < 5}>
                            {saving ? 'Reactivando...' : 'Reactivar'}
                        </Button>
                    </>
                }
            >
                {reactivando && (
                    <div className="space-y-3">
                        <Callout variant="info">
                            {reactivando.estado === 'anulado'
                                ? <>El adelanto estaba <strong>anulado</strong>: al reactivarlo se vuelve a asentar el egreso en tesorería (el dinero vuelve a salir de la cuenta).</>
                                : <>El adelanto estaba <strong>devuelto</strong>: al reactivarlo se revierte el ingreso de la devolución en tesorería.</>}
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivoReactivar}
                            onChange={e => setMotivoReactivar(e.target.value)}
                            placeholder="Ej.: se cerró por error"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>

            {/* Modal detalle aplicaciones */}
            <Modal isOpen={detalle !== null} onClose={() => setDetalle(null)}
                title={detalle ? `Aplicaciones — ${nombreProveedor(detalle.proveedor)}` : ''} size="md"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <Timeline
                        emptyMessage="Aún no se aplicó contra ninguna entrada"
                        items={detalle.aplicaciones.map(ap => ({
                            fecha: new Date(ap.fecha + 'T00:00:00').toLocaleDateString('es-PE'),
                            badge: { texto: 'Aplicación', variant: 'primary' as const },
                            tipo: 'egreso' as const,
                            detalle: [ap.entrada ? `Entrada ${ap.entrada.numero_documento ?? '#' + ap.entrada.id}` : null, ap.observacion].filter(Boolean).join(' · ') || undefined,
                            user: ap.user?.name,
                            monto: Number(ap.monto),
                        }))}
                    />
                )}
            </Modal>
        </AppLayout>
    );
}
