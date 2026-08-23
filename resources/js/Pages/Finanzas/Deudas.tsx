import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Plus, Eye, Ban, Coins, CreditCard, TrendingUp, TrendingDown, Pencil, Trash2, RotateCcw, CalendarClock, Scale } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import Checkbox from '@/Components/UI/Checkbox';
import StatGrid from '@/Components/UI/StatGrid';
import Timeline from '@/Components/UI/Timeline';
import AfectaCajaSelect, { TurnoLite } from '@/Components/AfectaCajaSelect';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import type { PageProps } from '@/types';

interface Pago {
    id: number;
    fecha: string;
    tipo: 'amortizacion' | 'incremento' | 'compensacion';
    monto: string;
    observacion: string | null;
    metodo_pago_id?: number | null;
    metodo_pago?: { id: number; nombre: string } | null;
    cuenta_id?: number | null;
    cuenta?: { id: number; nombre: string } | null;
    turno_id?: number | null;
    user?: { name: string } | null;
    compensacion_deuda?: { id: number; nombre: string } | null;
    eliminado?: boolean;
    deleted_at?: string | null;
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
    desembolso?: { cuenta?: { nombre: string } | null } | null;
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    deudas: Paginado<Deuda>;
    totales: { por_pagar: number; por_cobrar: number; activas: number; vencidas: number; monto_vencido: number };
    estado: string;
    buscar?: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    turnos: TurnoLite[];
    puede: { editar: boolean; eliminar: boolean; compensar: boolean };
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
    // Desembolso: por defecto SÍ mueve el dinero en caja al crear la deuda.
    registrar_caja: true, metodo_pago_id: '', cuenta_id: '',
    // "Afecta caja a:" turno (solo lo usa el admin; el cajero se imputa solo).
    turno_afecta: '' as number | '',
});

export default function Deudas({ deudas, totales, estado, buscar, metodosPago, cuentas, turnos, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo] = useState(false);
    const [pagando, setPagando]       = useState<Deuda | null>(null);
    const [anulando, setAnulando]     = useState<Deuda | null>(null);
    const [detalle, setDetalle]       = useState<Deuda | null>(null);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});
    const [form, setForm]             = useState(emptyForm());
    const [formPago, setFormPago]     = useState({ tipo: 'amortizacion', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '', observacion: '', turno_afecta: '' as number | '' });
    const [motivoAnular, setMotivoAnular] = useState('');
    // Edición / eliminación / reactivación (según permisos).
    const [editando, setEditando]         = useState<Deuda | null>(null);
    const [formEditar, setFormEditar]     = useState({ tipo: 'bancaria', nombre: '', monto_original: '', fecha_inicio: hoy(), fecha_vencimiento: '', observacion: '' });
    const [eliminando, setEliminando]     = useState<Deuda | null>(null);
    const [reactivando, setReactivando]   = useState<Deuda | null>(null);
    const [eliminandoPago, setEliminandoPago] = useState<Pago | null>(null);
    const [editandoPago, setEditandoPago] = useState<Pago | null>(null);
    const [formEditarPago, setFormEditarPago] = useState({
        tipo: 'amortizacion' as 'amortizacion' | 'incremento',
        fecha: hoy(),
        monto: '',
        metodo_pago_id: '',
        cuenta_id: '',
        observacion: '',
        turno_afecta: '' as number | '',
    });
    const [motivo, setMotivo]             = useState('');
    const [verEliminados, setVerEliminados] = useState(false);
    const [movimientosExtra, setMovimientosExtra] = useState<Pago[]>([]);
    const [cargandoMovimientos, setCargandoMovimientos] = useState(false);

    // Compensación entre deudas por pagar y por cobrar.
    const [compensando, setCompensando] = useState(false);
    const [cargandoOpciones, setCargandoOpciones] = useState(false);
    const [opcionesPorPagar, setOpcionesPorPagar] = useState<{ value: string | number; label: string }[]>([]);
    const [opcionesPorCobrar, setOpcionesPorCobrar] = useState<{ value: string | number; label: string }[]>([]);
    const [formCompensar, setFormCompensar] = useState({
        deuda_por_pagar_id: '' as string | number,
        deuda_por_cobrar_id: '' as string | number,
        fecha: hoy(),
        monto: '',
        observacion: '',
    });
    const [maximoCompensar, setMaximoCompensar] = useState<number | null>(null);


    function abrirEditar(d: Deuda) {
        setErrors({});
        setFormEditar({
            tipo:              d.tipo,
            nombre:            d.nombre,
            monto_original:    String(Number(d.monto_original)),
            fecha_inicio:      d.fecha_inicio.slice(0, 10),
            fecha_vencimiento: d.fecha_vencimiento ? d.fecha_vencimiento.slice(0, 10) : '',
            observacion:       d.observacion ?? '',
        });
        setEditando(d);
    }

    function submitEditar() {
        if (!editando) return;
        setSaving(true);
        router.put(route('finanzas.deudas.update', editando.id), {
            ...formEditar,
            fecha_vencimiento: formEditar.fecha_vencimiento || null,
            observacion:       formEditar.observacion || null,
        } as any, {
            onSuccess: () => { setEditando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitEliminar() {
        if (!eliminando) return;
        setSaving(true);
        router.delete(route('finanzas.deudas.destroy', eliminando.id), {
            data: { motivo: motivo.trim() },
            onSuccess: () => { setEliminando(null); setMotivo(''); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        } as any);
    }

    function submitReactivar() {
        if (!reactivando) return;
        setSaving(true);
        router.post(route('finanzas.deudas.reactivar', reactivando.id), { motivo: motivo.trim() } as any, {
            onSuccess: () => { setReactivando(null); setMotivo(''); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitEliminarPago() {
        if (!eliminandoPago) return;
        setSaving(true);
        router.delete(route('finanzas.deudas.pagos.destroy', eliminandoPago.id), {
            data: { motivo: motivo.trim() },
            onSuccess: () => { setEliminandoPago(null); setMotivo(''); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        } as any);
    }

    /** Carga hasta 100 deudas activas por dirección para el select de compensación. */
    function cargarOpcionesCompensacion() {
        setCargandoOpciones(true);
        Promise.all([
            axios.get<{ id: number; nombre: string; saldo: string }[]>(route('finanzas.deudas.activas'), { params: { direccion: 'por_pagar' } }),
            axios.get<{ id: number; nombre: string; saldo: string }[]>(route('finanzas.deudas.activas'), { params: { direccion: 'por_cobrar' } }),
        ])
            .then(([pp, pc]) => {
                setOpcionesPorPagar(pp.data.map(d => ({ value: d.id, label: `${d.nombre} — ${money(d.saldo)}` })));
                setOpcionesPorCobrar(pc.data.map(d => ({ value: d.id, label: `${d.nombre} — ${money(d.saldo)}` })));
            })
            .catch(() => toast.error('No se pudieron cargar las deudas activas'))
            .finally(() => setCargandoOpciones(false));
    }

    function abrirCompensar() {
        setErrors({});
        setFormCompensar({ deuda_por_pagar_id: '', deuda_por_cobrar_id: '', fecha: hoy(), monto: '', observacion: '' });
        setMaximoCompensar(null);
        setCompensando(true);
        cargarOpcionesCompensacion();
    }

    function calcularMaximoCompensacion(ppId: string | number, pcId: string | number) {
        const pp = opcionesPorPagar.find(o => o.value == ppId);
        const pc = opcionesPorCobrar.find(o => o.value == pcId);
        if (!pp || !pc) {
            setMaximoCompensar(null);
            return;
        }
        const saldoPp = Number((pp.label.match(/S\/\s*([\d,.]+)/)?.[1] ?? '0').replace(/,/g, ''));
        const saldoPc = Number((pc.label.match(/S\/\s*([\d,.]+)/)?.[1] ?? '0').replace(/,/g, ''));
        const max = Math.min(saldoPp, saldoPc);
        setMaximoCompensar(max > 0 ? max : null);
        setFormCompensar(f => ({ ...f, monto: max > 0 ? String(max.toFixed(2)) : '' }));
    }

    function submitCompensar() {
        setSaving(true);
        router.post(route('finanzas.deudas.compensar'), {
            ...formCompensar,
            monto: formCompensar.monto || null,
            observacion: formCompensar.observacion || null,
        } as any, {
            onSuccess: () => { setCompensando(false); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // Cargar movimientos eliminados (y todos) cuando el modal pida verlos.
    useEffect(() => {
        if (!detalle || !verEliminados) {
            setMovimientosExtra([]);
            return;
        }
        setCargandoMovimientos(true);
        axios.get<Pago[]>(route('finanzas.deudas.movimientos', detalle.id), { params: { todos: 1 } })
            .then(res => setMovimientosExtra(res.data))
            .catch(() => toast.error('No se pudieron cargar los movimientos eliminados'))
            .finally(() => setCargandoMovimientos(false));
    }, [detalle, verEliminados]);

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
            metodo_pago_id: form.registrar_caja ? (form.metodo_pago_id || null) : null,
            cuenta_id:      form.registrar_caja ? (form.cuenta_id || null) : null,
            // Solo se imputa turno si el desembolso mueve caja.
            turno_id:       form.registrar_caja ? (form.turno_afecta || null) : null,
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
            turno_id:       formPago.turno_afecta || null,
        } as any, {
            onSuccess: () => { setPagando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function abrirEditarPago(p: Pago) {
        if (p.tipo === 'compensacion') return;
        setErrors({});
        setFormEditarPago({
            tipo: p.tipo,
            fecha: p.fecha.slice(0, 10),
            monto: String(Number(p.monto)),
            metodo_pago_id: p.metodo_pago_id ? String(p.metodo_pago_id) : '',
            cuenta_id: p.cuenta_id ? String(p.cuenta_id) : '',
            observacion: p.observacion ?? '',
            turno_afecta: p.turno_id ?? '',
        });
        setEditandoPago(p);
    }

    function submitEditarPago() {
        if (!editandoPago) return;
        setSaving(true);
        router.put(route('finanzas.deudas.pagos.update', editandoPago.id), {
            ...formEditarPago,
            metodo_pago_id: formEditarPago.metodo_pago_id || null,
            cuenta_id:      formEditarPago.cuenta_id || null,
            turno_id:       formEditarPago.turno_afecta || null,
        } as any, {
            onSuccess: () => { setEditandoPago(null); setSaving(false); },
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

    const exportUrl = () => {
        const params = new URLSearchParams(window.location.search);
        window.location.href = route('finanzas.deudas.exportar') + (params.toString() ? '?' + params.toString() : '');
    };

    const columns: Column<Deuda>[] = [
        {
            key: 'direccion', label: 'Dirección',
            render: (d) => d.direccion === 'por_pagar'
                ? <Badge variant="danger">Debemos</Badge>
                : <Badge variant="success">Nos deben</Badge>,
        },
        { key: 'nombre', label: 'Nombre', sortable: true, render: (d) => <span className="font-medium">{d.nombre}</span> },
        { key: 'tipo', label: 'Tipo', render: (d) => <span className="text-sm">{TIPO_LABEL[d.tipo] ?? d.tipo}</span> },
        {
            // Método(s) de pago usados en los movimientos de esta deuda/préstamo.
            key: 'metodo', label: 'Método de pago', sortable: false,
            render: (d) => {
                const metodos = [...new Set((d.pagos ?? []).map(p => p.metodo_pago?.nombre).filter(Boolean))];
                return metodos.length > 0
                    ? <span className="text-sm">{metodos.join(' · ')}</span>
                    : <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>—</span>;
            },
        },
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
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (d) => (
                <div className="flex items-center gap-1.5">
                    <button onClick={() => setDetalle(d)} className="p-1.5 rounded-lg hover:bg-black/5" title="Ver movimientos"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <Eye size={15} />
                    </button>
                    {d.estado === 'activa' && (
                        <>
                            <button onClick={() => { setErrors({}); setFormPago({ tipo: 'amortizacion', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '', observacion: '', turno_afecta: '' }); setPagando(d); }}
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
                    {puede.editar && d.estado !== 'anulada' && (
                        <button onClick={() => abrirEditar(d)}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Editar deuda"
                            style={{ color: 'var(--color-primary)' }}>
                            <Pencil size={15} />
                        </button>
                    )}
                    {puede.editar && d.estado === 'anulada' && (
                        <button onClick={() => { setErrors({}); setMotivo(''); setReactivando(d); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Reactivar deuda"
                            style={{ color: 'var(--color-primary)' }}>
                            <RotateCcw size={15} />
                        </button>
                    )}
                    {puede.eliminar && (
                        <button onClick={() => { setErrors({}); setMotivo(''); setEliminando(d); }}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Eliminar deuda"
                            style={{ color: 'var(--color-danger)' }}>
                            <Trash2 size={15} />
                        </button>
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
                    <div className="flex items-center gap-2">
                        {puede.compensar && (
                            <Button variant="secondary" onClick={abrirCompensar}>
                                <Scale size={15} className="mr-1 flex-shrink-0" />Compensar
                            </Button>
                        )}
                        <Button onClick={() => { setErrors({}); setForm(emptyForm()); setModalNuevo(true); }}>
                            <Plus size={15} className="mr-1 flex-shrink-0" />Nueva deuda
                        </Button>
                    </div>
                }
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 lg:grid-cols-4" stats={[
                    {
                        label: 'Debemos', valor: money(totales.por_pagar), color: 'danger', destacado: true,
                        icon: <TrendingDown size={19} />, sub: 'Bancos, personas y personal',
                    },
                    {
                        label: 'Nos deben', valor: money(totales.por_cobrar), color: 'success',
                        icon: <TrendingUp size={19} />, sub: 'Préstamos otorgados a terceros',
                    },
                    {
                        label: 'Deudas activas', valor: totales.activas, color: 'primary',
                        icon: <Coins size={19} />, sub: 'En ambas direcciones',
                    },
                    {
                        label: 'Vencidas', valor: totales.vencidas, color: 'warning',
                        icon: <CalendarClock size={19} />,
                        sub: totales.vencidas > 0 ? `${money(totales.monto_vencido)} pasada la fecha` : 'Nada fuera de fecha',
                    },
                ]} />
            </div>

            <FiltrosCard cols={3}>
                <Select label="Estado" value={estado}
                    onChange={(v) => router.get(route('finanzas.deudas.index'), { estado: v, buscar: buscar || undefined }, { preserveState: true, replace: true })}
                    options={[
                        { value: 'activas',  label: 'Activas' },
                        { value: 'pagadas',  label: 'Pagadas' },
                        { value: 'anuladas', label: 'Anuladas' },
                        { value: 'todas',    label: 'Todas' },
                    ]} />
            </FiltrosCard>

            <Table data={deudas} columns={columns}
                searchPlaceholder="Buscar deuda..." emptyMessage="No hay deudas registradas"
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('finanzas.deudas.index'),
                    { estado, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })}
                onExportExcel={exportUrl} />

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

                    {/* Desembolso: mueve el dinero en tesorería al crear la deuda.
                        por_pagar → INGRESO (nos entra el préstamo);
                        por_cobrar → EGRESO (sale lo que prestamos). */}
                    <div className="rounded-xl p-3 space-y-3" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                        <Checkbox
                            checked={form.registrar_caja}
                            onChange={e => setForm(f => ({ ...f, registrar_caja: e.target.checked }))}
                            label={form.direccion === 'por_pagar' ? '¿A qué cuenta entró el dinero?' : '¿De qué cuenta salió el dinero?'}
                            description={form.registrar_caja
                                ? (form.direccion === 'por_pagar'
                                    ? 'Se registrará un INGRESO por el monto a la cuenta elegida.'
                                    : 'Se registrará un EGRESO por el monto de la cuenta elegida.')
                                : 'Desactivado: solo se registra la deuda, sin mover caja (préstamo histórico ya gastado).'}
                        />
                        {form.registrar_caja && (
                            <div className="space-y-3">
                                <Select label="Método de pago"
                                    options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                                    value={form.metodo_pago_id}
                                    onChange={v => { const cts = cuentasDeMetodo(String(v)); setForm(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                                    placeholder="— Seleccionar —"
                                />
                                {cuentasDeMetodo(form.metodo_pago_id).length > 0 ? (
                                    <Select label="Cuenta"
                                        options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                        value={form.cuenta_id}
                                        onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                                        placeholder="— Seleccionar —"
                                        error={errors.cuenta_id}
                                    />
                                ) : form.metodo_pago_id ? (
                                    <Callout variant="info">
                                        El dinero se registrará en la cuenta <strong>«{metodosPago.find(x => String(x.id) === form.metodo_pago_id)?.nombre}»</strong>,
                                        que el sistema crea y vincula automáticamente a este método.
                                    </Callout>
                                ) : (
                                    <Callout variant="info">
                                        Sin método: el desembolso irá a la caja <strong>Efectivo</strong>.
                                    </Callout>
                                )}
                                <AfectaCajaSelect
                                    modulo="deuda"
                                    turnos={turnos}
                                    value={form.turno_afecta}
                                    onChange={v => setForm(f => ({ ...f, turno_afecta: v }))}
                                    error={errors.turno_id}
                                    hint='El desembolso en efectivo entra/sale de la caja de este turno. "Sin turno" solo lo registra.'
                                />
                            </div>
                        )}
                    </div>
                </div>
            </Modal>

            {/* Modal compensar deudas */}
            <Modal isOpen={compensando} onClose={() => setCompensando(false)} title="Compensar deudas" size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCompensando(false)}>Cancelar</Button>
                        <Button onClick={submitCompensar} disabled={saving || !formCompensar.deuda_por_pagar_id || !formCompensar.deuda_por_cobrar_id}>
                            {saving ? 'Guardando...' : 'Compensar'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Callout variant="info">
                        Crea un movimiento tipo <strong>compensación</strong> en ambas deudas. No mueve dinero en caja; reduce ambos saldos por el mismo monto.
                    </Callout>
                    {cargandoOpciones ? (
                        <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Cargando deudas activas…</p>
                    ) : (
                        <>
                            <SearchableSelect label="Deuda por pagar (debemos)" required placeholder="Buscar deuda…"
                                options={opcionesPorPagar}
                                value={formCompensar.deuda_por_pagar_id}
                                onChange={v => { setFormCompensar(f => ({ ...f, deuda_por_pagar_id: v })); calcularMaximoCompensacion(v, formCompensar.deuda_por_cobrar_id); }}
                                error={errors.deuda_por_pagar_id}
                            />
                            <SearchableSelect label="Deuda por cobrar (nos deben)" required placeholder="Buscar deuda…"
                                options={opcionesPorCobrar}
                                value={formCompensar.deuda_por_cobrar_id}
                                onChange={v => { setFormCompensar(f => ({ ...f, deuda_por_cobrar_id: v })); calcularMaximoCompensacion(formCompensar.deuda_por_pagar_id, v); }}
                                error={errors.deuda_por_cobrar_id}
                            />
                            {maximoCompensar !== null && (
                                <div className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    Monto máximo sugerido: <strong>{money(maximoCompensar)}</strong>
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-3">
                                <Input label="Fecha" required type="date" value={formCompensar.fecha}
                                    onChange={e => setFormCompensar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                                <Input label="Monto" required type="number" min="0.01" step="0.01" value={formCompensar.monto}
                                    onChange={e => setFormCompensar(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                            </div>
                            <Input label="Observación" value={formCompensar.observacion}
                                onChange={e => setFormCompensar(f => ({ ...f, observacion: e.target.value }))} error={errors.observacion} />
                        </>
                    )}
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
                            <Select label="Cuenta" required
                                options={cuentasDeMetodo(formPago.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                value={formPago.cuenta_id}
                                onChange={v => setFormPago(f => ({ ...f, cuenta_id: String(v) }))}
                                placeholder="— Selecciona una cuenta —"
                                error={errors.cuenta_id}
                            />
                        ) : (
                            <Callout variant="info">
                                El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === formPago.metodo_pago_id)?.nombre}»</strong>,
                                que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                            </Callout>
                        )}
                        <AfectaCajaSelect
                            modulo="deuda"
                            turnos={turnos}
                            value={formPago.turno_afecta}
                            onChange={v => setFormPago(f => ({ ...f, turno_afecta: v }))}
                            error={errors.turno_id}
                            hint='La cuota en efectivo entra/sale de la caja de este turno. "Sin turno" solo la registra.'
                        />
                        <Input label="Observación" value={formPago.observacion}
                            onChange={e => setFormPago(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal editar movimiento */}
            <Modal isOpen={editandoPago !== null} onClose={() => setEditandoPago(null)}
                title={editandoPago ? `Editar movimiento — ${detalle?.nombre ?? ''}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditandoPago(null)}>Cancelar</Button>
                        <Button onClick={submitEditarPago} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</Button>
                    </>
                }
            >
                {editandoPago && detalle && (
                    <div className="space-y-4">
                        <StatGrid stats={[
                            { label: 'Saldo actual', valor: money(detalle.saldo), destacado: true },
                        ]} />
                        <Select label="Tipo de movimiento" required
                            options={[
                                { value: 'amortizacion', label: detalle.direccion === 'por_pagar' ? 'Pago de cuota (baja el saldo)' : 'Nos abonaron (baja el saldo)' },
                                { value: 'incremento',   label: 'Incremento (sube el saldo)' },
                            ]}
                            value={formEditarPago.tipo}
                            onChange={v => setFormEditarPago(f => ({ ...f, tipo: String(v) as 'amortizacion' | 'incremento' }))}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Fecha" required type="date" value={formEditarPago.fecha}
                                onChange={e => setFormEditarPago(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                            <Input label="Monto" required type="number" min="0.01" step="0.01" value={formEditarPago.monto}
                                onChange={e => setFormEditarPago(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                        </div>
                        <Select label="Método de pago"
                            options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                            value={formEditarPago.metodo_pago_id}
                            onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormEditarPago(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                            placeholder="— Seleccionar —"
                        />
                        {cuentasDeMetodo(formEditarPago.metodo_pago_id).length > 0 ? (
                            <Select label="Cuenta" required
                                options={cuentasDeMetodo(formEditarPago.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                value={formEditarPago.cuenta_id}
                                onChange={v => setFormEditarPago(f => ({ ...f, cuenta_id: String(v) }))}
                                placeholder="— Selecciona una cuenta —"
                                error={errors.cuenta_id}
                            />
                        ) : (
                            <Callout variant="info">
                                El dinero se registrara en la cuenta <strong>«{metodosPago.find(x => String(x.id) === formEditarPago.metodo_pago_id)?.nombre}»</strong>,
                                que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                            </Callout>
                        )}
                        <AfectaCajaSelect
                            modulo="deuda"
                            turnos={turnos}
                            value={formEditarPago.turno_afecta}
                            onChange={v => setFormEditarPago(f => ({ ...f, turno_afecta: v }))}
                            error={errors.turno_id}
                            hint='La cuota en efectivo entra/sale de la caja de este turno. "Sin turno" solo la registra.'
                        />
                        <Input label="Observación" value={formEditarPago.observacion}
                            onChange={e => setFormEditarPago(f => ({ ...f, observacion: e.target.value }))} />
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
            <Modal isOpen={detalle !== null} onClose={() => { setDetalle(null); setVerEliminados(false); setMovimientosExtra([]); }}
                title={detalle ? `Movimientos — ${detalle.nombre}` : ''} size="2xl"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (puede.eliminar ? (
                    <div className="space-y-4">
                        <div className="flex items-center justify-end">
                            <label className="inline-flex items-center gap-2 text-xs cursor-pointer select-none"
                                style={{ color: 'var(--color-text)' }}>
                                <input
                                    type="checkbox"
                                    checked={verEliminados}
                                    onChange={e => setVerEliminados(e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                Ver movimientos eliminados
                            </label>
                        </div>
                        {(() => {
                            const movimientosMostrados = verEliminados ? movimientosExtra : detalle.pagos;
                            if (cargandoMovimientos) {
                                return <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Cargando movimientos…</p>;
                            }
                            if (movimientosMostrados.length === 0) {
                                return <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin movimientos{verEliminados ? ' eliminados' : ''} registrados</p>;
                            }
                            return (
                                <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                    {movimientosMostrados.map((p, idx) => (
                                        <div key={`${p.id}-${p.eliminado ? 'del' : 'act'}`} className="flex items-center gap-3 px-4 py-2.5"
                                            style={{
                                                borderBottom: idx < movimientosMostrados.length - 1 ? '1px solid var(--color-border)' : undefined,
                                                backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                opacity: p.eliminado ? 0.55 : 1,
                                            }}>
                                            <Badge variant={p.tipo === 'amortizacion' ? 'success' : p.tipo === 'compensacion' ? 'info' : 'warning'}>
                                                {p.tipo === 'amortizacion' ? 'Amortización' : p.tipo === 'compensacion' ? 'Compensación' : 'Incremento'}
                                            </Badge>
                                            {p.eliminado && (
                                                <Badge variant="secondary">Eliminado</Badge>
                                            )}
                                            <div className="flex-1 min-w-0 text-xs">
                                                <p className="font-medium" style={{ color: 'var(--color-text)', textDecoration: p.eliminado ? 'line-through' : undefined }}>
                                                    {new Date(p.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}
                                                    <span className="ml-2 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                        {[p.tipo === 'compensacion' ? `Con ${p.compensacion_deuda?.nombre ?? '—'}` : p.metodo_pago?.nombre, p.cuenta?.nombre].filter(Boolean).join(' · ') || '—'}
                                                    </span>
                                                </p>
                                                {(p.observacion || p.user?.name || p.deleted_at) && (
                                                    <p className="truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                        {[
                                                            p.observacion,
                                                            p.user?.name ? `por ${p.user.name}` : null,
                                                            p.deleted_at ? `eliminado el ${p.deleted_at}` : null,
                                                        ].filter(Boolean).join(' · ')}
                                                    </p>
                                                )}
                                            </div>
                                            <span className="font-bold text-sm whitespace-nowrap"
                                                style={{ color: p.tipo === 'amortizacion' ? 'var(--color-success)' : p.tipo === 'compensacion' ? 'var(--color-primary)' : 'var(--color-danger)' }}>
                                                {p.tipo === 'incremento' ? '+' : '−'}{money(p.monto)}
                                            </span>
                                            {!p.eliminado && (
                                                <div className="flex items-center gap-1">
                                                    {p.tipo !== 'compensacion' && (
                                                        <button onClick={() => abrirEditarPago(p)}
                                                            className="p-1.5 rounded-lg hover:bg-black/5 flex-shrink-0" title="Editar movimiento"
                                                            style={{ color: 'var(--color-primary)' }}>
                                                            <Pencil size={14} />
                                                        </button>
                                                    )}
                                                    <button onClick={() => { setErrors({}); setMotivo(''); setEliminandoPago(p); }}
                                                        className="p-1.5 rounded-lg hover:bg-black/5 flex-shrink-0" title="Eliminar movimiento"
                                                        style={{ color: 'var(--color-danger)' }}>
                                                        <Trash2 size={14} />
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            );
                        })()}
                        <Callout variant="info" title="Registro de la deuda"
                            aside={money(detalle.monto_original)}>
                            {new Date(detalle.fecha_inicio.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}
                            {' · '}
                            {detalle.desembolso?.cuenta?.nombre
                                ? `Desembolso en ${detalle.desembolso.cuenta.nombre}`
                                : 'Sin desembolso en caja'}
                            {detalle.observacion && (
                                <span className="block text-[11px]" style={{ color: 'var(--color-text-muted)' }}>{detalle.observacion}</span>
                            )}
                        </Callout>
                        <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                            {verEliminados
                                ? 'Los movimientos eliminados se muestran solo como referencia; no afectan el saldo actual de la deuda.'
                                : 'Eliminar un movimiento lo oculta y revierte su efecto en tesorería, pero queda registrado para consulta.'}
                        </p>
                    </div>
                ) : (
                    <Timeline
                        emptyMessage="Sin movimientos registrados"
                        items={[
                            ...detalle.pagos.map(p => ({
                                fecha: new Date(p.fecha + 'T00:00:00').toLocaleDateString('es-PE'),
                                badge: p.tipo === 'amortizacion'
                                    ? { texto: 'Amortización', variant: 'success' as const }
                                    : p.tipo === 'compensacion'
                                        ? { texto: 'Compensación', variant: 'info' as const }
                                        : { texto: 'Incremento', variant: 'warning' as const },
                                tipo: p.tipo === 'incremento' ? 'egreso' as const : 'ingreso' as const,
                                detalle: [p.tipo === 'compensacion' ? `Con ${p.compensacion_deuda?.nombre ?? '—'}` : p.metodo_pago?.nombre, p.cuenta?.nombre, p.observacion].filter(Boolean).join(' · ') || undefined,
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
                ))}
            </Modal>

            {/* Modal editar deuda */}
            <Modal isOpen={editando !== null} onClose={() => setEditando(null)}
                title={editando ? `Editar deuda — ${editando.nombre}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button onClick={submitEditar} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</Button>
                    </>
                }
            >
                {editando && (
                    <div className="space-y-4">
                        <Callout variant="info">
                            El saldo se ajustará por la diferencia del monto original.
                        </Callout>
                        <Select label="Tipo" required
                            options={[
                                { value: 'bancaria',   label: 'Bancaria (préstamo de banco)' },
                                { value: 'personal',   label: 'Personal (persona natural / tercero)' },
                                { value: 'trabajador', label: 'Al personal (sueldos, adelantos de personal)' },
                                { value: 'otro',       label: 'Otro' },
                            ]}
                            value={formEditar.tipo}
                            onChange={v => setFormEditar(f => ({ ...f, tipo: String(v) }))}
                            error={errors.tipo}
                        />
                        <Input label="Nombre / descripción" required placeholder='Ej: "Deuda BCP 1 - 7630", "Jeiner Herrera"'
                            value={formEditar.nombre}
                            onChange={e => setFormEditar(f => ({ ...f, nombre: e.target.value }))}
                            error={errors.nombre}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Monto original" required type="number" min="0.01" step="0.01" value={formEditar.monto_original}
                                onChange={e => setFormEditar(f => ({ ...f, monto_original: e.target.value }))} error={errors.monto_original} />
                            <Input label="Fecha de inicio" required type="date" value={formEditar.fecha_inicio}
                                onChange={e => setFormEditar(f => ({ ...f, fecha_inicio: e.target.value }))} error={errors.fecha_inicio} />
                        </div>
                        <Input label="Fecha de vencimiento (opcional)" type="date" value={formEditar.fecha_vencimiento}
                            onChange={e => setFormEditar(f => ({ ...f, fecha_vencimiento: e.target.value }))} error={errors.fecha_vencimiento} />
                        <Input label="Observación" value={formEditar.observacion}
                            onChange={e => setFormEditar(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}
            </Modal>

            {/* Modal eliminar deuda */}
            <Modal isOpen={eliminando !== null} onClose={() => setEliminando(null)}
                title={eliminando ? `Eliminar deuda — ${eliminando.nombre}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEliminando(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitEliminar} disabled={saving || motivo.trim().length < 5}>
                            {saving ? 'Eliminando...' : 'Sí, eliminar deuda'}
                        </Button>
                    </>
                }
            >
                {eliminando && (
                    <div className="space-y-3">
                        <Callout variant="warning">
                            Se revertirán los movimientos de tesorería de sus cuotas.
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivo}
                            onChange={e => setMotivo(e.target.value)}
                            placeholder="Ej.: se registró doble / deuda inexistente"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>

            {/* Modal reactivar deuda anulada */}
            <Modal isOpen={reactivando !== null} onClose={() => setReactivando(null)}
                title={reactivando ? `Reactivar deuda — ${reactivando.nombre}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setReactivando(null)}>Cancelar</Button>
                        <Button onClick={submitReactivar} disabled={saving || motivo.trim().length < 5}>
                            {saving ? 'Reactivando...' : 'Reactivar'}
                        </Button>
                    </>
                }
            >
                {reactivando && (
                    <div className="space-y-3">
                        <Callout variant="info">
                            La deuda volverá a estar activa con su saldo anterior.
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivo}
                            onChange={e => setMotivo(e.target.value)}
                            placeholder="Ej.: se anuló por error"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>

            {/* Modal eliminar movimiento de deuda */}
            <Modal isOpen={eliminandoPago !== null} onClose={() => setEliminandoPago(null)}
                title={eliminandoPago ? `Eliminar movimiento — ${money(eliminandoPago.monto)} del ${new Date(eliminandoPago.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEliminandoPago(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitEliminarPago} disabled={saving || motivo.trim().length < 5}>
                            {saving ? 'Eliminando...' : 'Sí, eliminar movimiento'}
                        </Button>
                    </>
                }
            >
                {eliminandoPago && (
                    <div className="space-y-3">
                        <Callout variant="warning">
                            {eliminandoPago?.tipo === 'compensacion'
                                ? 'Se elimina el par de compensación y se restauran los saldos de ambas deudas.'
                                : 'Se revierte su efecto en tesorería y el saldo de la deuda se recalcula.'}
                        </Callout>
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivo}
                            onChange={e => setMotivo(e.target.value)}
                            placeholder="Ej.: se registró doble / monto equivocado"
                            error={errors.motivo}
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
