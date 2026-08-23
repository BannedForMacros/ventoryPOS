import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Plus, Eye, PackageCheck, Ban, PiggyBank, Printer, FileDown, UserPlus, Pencil, Users, Package, Coins, Trash2 } from 'lucide-react';
import { imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
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
import Checkbox from '@/Components/UI/Checkbox';
import StatGrid from '@/Components/UI/StatGrid';
import AfectaCajaSelect from '@/Components/AfectaCajaSelect';
import Timeline from '@/Components/UI/Timeline';
import ModalCrearCliente from '@/Pages/Pos/Partials/ModalCrearCliente';
import type { PageProps, Cliente } from '@/types';

interface AplicacionItem {
    id: number;
    cantidad: string;
    item?: { id: number; producto_nombre: string; unidad_nombre: string; cantidad_pendiente: string } | null;
}

interface Aplicacion {
    id: number;
    numero: string | null;
    fecha: string;
    monto: string;
    cantidad: string | null;
    observacion: string | null;
    venta?: { numero: string | null } | null;
    user?: { name: string } | null;
    items?: AplicacionItem[];
    metodo_pago_id?: number | null;
    cuenta_id?: number | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
}

interface Cancelacion {
    id: number;
    fecha: string;
    cantidad: string;
    monto: string;
    motivo: string;
    observacion: string | null;
    turno?: { id: number; fecha_apertura?: string } | null;
    caja?: { id: number; nombre?: string } | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
}

/** Ítem de un anticipo multi-producto (pendiente por entregar del POS). */
interface AnticipoItem {
    id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: string;
    cantidad_pendiente: string;
    precio_unitario: string;
    producto?: { id: number; nombre: string; precio_venta: string } | null;
    unidad?: { id: number; precio_venta: string } | null;
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
    turno_id?: number | null;
    fecha_entrega_estimada?: string | null;
    valor_pasivo?: number;
    cliente?: { id: number; nombres?: string; apellidos?: string; razon_social?: string; es_cliente_general?: boolean } | null;
    producto?: { id: number; nombre: string; precio_venta: string } | null;
    venta?: { id: number; numero: string; es_credito?: boolean } | null;
    metodo_pago_id?: number | null;
    cuenta_id?: number | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    aplicaciones: Aplicacion[];
    items?: AnticipoItem[];
    cancelaciones?: Cancelacion[];
}

interface Paginado<T> { data: T[]; total: number; }

interface TurnoLite {
    id: number; user_id: number; caja_id: number; fecha_apertura: string;
    estado: 'abierto' | 'cerrado';
    user?: { id: number; name: string } | null;
    caja?: { id: number; nombre: string } | null;
}

interface Props extends PageProps {
    anticipos: Paginado<Anticipo>;
    totalPasivo: number;
    kpis: { activos: number; clientes: number; material: number; dinero: number };
    estado: string;
    buscar?: string;
    clientes: { id: number; nombres?: string; apellidos?: string; razon_social?: string; es_cliente_general?: boolean }[];
    productos: { id: number; nombre: string; precio_venta: string }[];
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    turnos: TurnoLite[];
    turnoActivoId: number | null;
    puede?: { editar: boolean };
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreCliente = (c?: { nombres?: string; apellidos?: string; razon_social?: string; es_cliente_general?: boolean } | null) =>
    c?.es_cliente_general
        ? 'Clientes varios'
        : (c?.razon_social ?? (`${c?.nombres ?? ''} ${c?.apellidos ?? ''}`.trim() || '—'));

const esMultiItem = (a: Anticipo) => (a.items?.length ?? 0) > 0;

/** Suma de unidades aún pendientes de un anticipo multi-producto. */
const pendienteTotal = (a: Anticipo) =>
    (a.items ?? []).reduce((s, i) => s + Number(i.cantidad_pendiente), 0);

/** Pasivo actual del anticipo: el backend lo calcula (multi-item o clásico). */
const valorHoy = (a: Anticipo) => {
    if (a.valor_pasivo !== undefined && a.valor_pasivo !== null) return Number(a.valor_pasivo);
    // Pendiente del POS: se debe lo PAGADO no entregado (precio congelado de
    // la venta), no la revalorización a precio del día.
    if (esMultiItem(a)) return Number(a.saldo);
    return a.tipo_valorizacion === 'material' && a.producto && a.cantidad_pendiente !== null
        ? Number(a.cantidad_pendiente) * Number(a.producto.precio_venta)
        : Number(a.saldo);
};

const emptyForm = () => ({
    cliente_id: '', fecha: hoy(), monto: '', metodo_pago_id: '', cuenta_id: '',
    tipo_valorizacion: 'monto', producto_id: '', cantidad: '', observacion: '',
    turno_id: '',
});

export default function Anticipos({ anticipos, totalPasivo, kpis, estado, buscar, clientes, productos, metodosPago, cuentas, turnos, turnoActivoId, puede }: Props) {
    const puedeEditarEntregas = puede?.editar ?? false;
    const { flash } = usePage<Props>().props;
    const [modalNuevo, setModalNuevo]   = useState(false);
    const [editando, setEditando]       = useState<Anticipo | null>(null);
    const [modalCrearCliente, setModalCrearCliente] = useState(false);
    const [aplicando, setAplicando]     = useState<Anticipo | null>(null);
    const [anulando, setAnulando]       = useState<Anticipo | null>(null);
    const [detalle, setDetalle]         = useState<Anticipo | null>(null);
    const [saving, setSaving]           = useState(false);
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [form, setForm]               = useState(emptyForm());
    const [formAplicar, setFormAplicar] = useState({ fecha: hoy(), monto: '', cantidad: '', observacion: '', metodo_pago_id: '', cuenta_id: '' });
    // Entregas por ítem (anticipos multi-producto del POS): item.id → cantidad a entregar.
    const [entregas, setEntregas]       = useState<Record<number, string>>({});
    const [excesoACxc, setExcesoACxc]   = useState(false);
    const [formAnular, setFormAnular]   = useState({ accion: 'devuelto', motivo: '', metodo_pago_id: '', cuenta_id: '', fecha: hoy() });
    // Editar / anular una ENTREGA de dinero ya registrada.
    const [editandoEntrega, setEditandoEntrega] = useState<Aplicacion | null>(null);
    const [editandoEntregaMaterial, setEditandoEntregaMaterial] = useState<Aplicacion | null>(null);
    const [anulandoEntrega, setAnulandoEntrega] = useState<Aplicacion | null>(null);
    const [formEntrega, setFormEntrega]         = useState({ monto: '', fecha: hoy(), observacion: '', metodo_pago_id: '', cuenta_id: '' });
    const [formEntregaMaterial, setFormEntregaMaterial] = useState<{ fecha: string; observacion: string; items: Record<number, string> }>({ fecha: hoy(), observacion: '', items: {} });
    const [motivoEntrega, setMotivoEntrega]     = useState('');

    // Cambiar producto de una línea pendiente (anticipo material del POS).
    const [cambiandoItem, setCambiandoItem] = useState<{ anticipo: Anticipo; item: AnticipoItem } | null>(null);
    const [formCambiar, setFormCambiar] = useState({ producto_id: '', cantidad: '', motivo: '' });

    // Cancelar pendiente de una línea (anticipo material del POS).
    const [cancelandoItem, setCancelandoItem] = useState<{ anticipo: Anticipo; item: AnticipoItem } | null>(null);
    const [formCancelar, setFormCancelar] = useState({
        cantidad: '', motivo: '', fecha: hoy(), observacion: '',
        metodo_pago_id: '', cuenta_id: '', turno_id: '',
    });

    function abrirCancelarPendiente(anticipo: Anticipo, item: AnticipoItem) {
        setCancelandoItem({ anticipo, item });
        setFormCancelar({
            cantidad: String(Number(item.cantidad_pendiente)),
            motivo: '',
            fecha: hoy(),
            observacion: '',
            metodo_pago_id: anticipo.metodo_pago_id ? String(anticipo.metodo_pago_id) : '',
            cuenta_id: anticipo.cuenta_id ? String(anticipo.cuenta_id) : '',
            turno_id: turnoActivoId ? String(turnoActivoId) : '',
        });
        setErrors({});
    }

    function submitCancelarPendiente() {
        if (!cancelandoItem) return;
        setSaving(true);
        router.post(
            route('finanzas.anticipos.items.cancelar-pendiente', [cancelandoItem.anticipo.id, cancelandoItem.item.id]),
            {
                cantidad: formCancelar.cantidad,
                motivo: formCancelar.motivo,
                fecha: formCancelar.fecha,
                observacion: formCancelar.observacion || null,
                metodo_pago_id: formCancelar.metodo_pago_id || null,
                cuenta_id: formCancelar.cuenta_id || null,
                turno_id: formCancelar.turno_id || null,
            } as any,
            {
                onSuccess: () => {
                    setCancelandoItem(null);
                    setFormCancelar({ cantidad: '', motivo: '', fecha: hoy(), observacion: '', metodo_pago_id: '', cuenta_id: '', turno_id: '' });
                    setSaving(false);
                    setDetalle(null);
                },
                onError: (errs: any) => { setErrors(errs); setSaving(false); },
            },
        );
    }

    function abrirCambiarProducto(anticipo: Anticipo, item: AnticipoItem) {
        setCambiandoItem({ anticipo, item });
        setFormCambiar({ producto_id: '', cantidad: String(Number(item.cantidad_pendiente)), motivo: '' });
        setErrors({});
    }

    function submitCambiarProducto() {
        if (!cambiandoItem) return;
        setSaving(true);
        router.post(route('finanzas.anticipos.items.cambiar-producto', [cambiandoItem.anticipo.id, cambiandoItem.item.id]), {
            nuevo_producto_id: formCambiar.producto_id || null,
            cantidad: formCambiar.cantidad,
            motivo: formCambiar.motivo,
        } as any, {
            onSuccess: () => {
                setCambiandoItem(null);
                setFormCambiar({ producto_id: '', cantidad: '', motivo: '' });
                setSaving(false);
                setDetalle(null);
            },
            onError: (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    // Cliente general ("Clientes varios"): para anticipos/depósitos sin dueño identificado.
    const clienteGeneral = useMemo(
        () => clientes.find(c => c.es_cliente_general) ?? null,
        [clientes],
    );

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

    // ── Impresión de una ENTREGA (aplicación) ──────────────────────────
    async function imprimirEntrega(ap: Aplicacion) {
        const tid = toast.loading(`Enviando entrega ${ap.numero ?? ''}...`);
        try {
            const { data } = await axios.get<TicketPayload>(route('finanzas.anticipos.entrega.ticket', ap.id));
            if (!data?.token) { toast.error('Esta caja no tiene ticketera configurada.', { id: tid }); return; }
            const ok = await imprimirTicket(data);
            if (ok) toast.success(`Entrega ${ap.numero ?? ''} enviada`, { id: tid });
            else    toast.error('No se pudo imprimir. Revisa VentoryPrint en esta PC.', { id: tid });
        } catch {
            toast.error('No se pudo obtener la entrega.', { id: tid });
        }
    }
    function verDocumentoEntrega(ap: Aplicacion) {
        window.open(route('finanzas.anticipos.entrega.documento', ap.id), '_blank');
    }

    function submitNuevo() {
        setSaving(true);
        router.post(route('finanzas.anticipos.store'), {
            ...form,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
            producto_id:    form.tipo_valorizacion === 'material' ? (form.producto_id || null) : null,
            cantidad:       form.tipo_valorizacion === 'material' ? (form.cantidad || null) : null,
            turno_id:       form.turno_id || null,
        } as any, {
            onSuccess: () => { setModalNuevo(false); setForm(emptyForm()); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    /** Solo se editan anticipos EN DINERO, activos, sin entregas y que no vengan del POS. */
    const puedeEditar = (a: Anticipo) =>
        a.estado === 'activo' && a.tipo_valorizacion === 'monto'
        && !esMultiItem(a) && !a.venta && (a.aplicaciones?.length ?? 0) === 0;

    /** Abre el modal de edición sembrando el formulario con el anticipo. */
    function abrirEditar(a: Anticipo) {
        setErrors({});
        setForm({
            ...emptyForm(),
            cliente_id:     String(a.cliente?.id ?? ''),
            fecha:          a.fecha,
            monto:          String(a.monto),
            metodo_pago_id: a.metodo_pago_id ? String(a.metodo_pago_id) : '',
            cuenta_id:      a.cuenta_id ? String(a.cuenta_id) : '',
            turno_id:       a.turno_id ? String(a.turno_id) : '',
            observacion:    a.observacion ?? '',
        });
        setEditando(a);
    }

    function submitEditar() {
        if (!editando) return;
        setSaving(true);
        router.put(route('finanzas.anticipos.update', editando.id), {
            cliente_id:     form.cliente_id,
            fecha:          form.fecha,
            monto:          form.monto,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
            turno_id:       form.turno_id || null,
            observacion:    form.observacion,
        } as any, {
            onSuccess: () => { setEditando(null); setForm(emptyForm()); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    /** Cobertura y excedente de la entrega, en vivo (espejo del backend). */
    function calcularDespacho(a: Anticipo) {
        if (a.tipo_valorizacion === 'material') {
            const pendiente = Number(a.cantidad_pendiente ?? 0);
            const entregada = parseFloat(formAplicar.cantidad) || 0;
            const excesoCant = Math.max(0, entregada - pendiente);
            const precioDia = Number(a.producto?.precio_venta ?? 0);
            return {
                excesoCant,
                excesoMonto: Math.round(excesoCant * precioDia * 100) / 100,
                precioDia,
            };
        }
        const saldo = Number(a.saldo);
        const valor = parseFloat(formAplicar.monto) || 0;
        return { excesoCant: 0, excesoMonto: Math.round(Math.max(0, valor - saldo) * 100) / 100, precioDia: 0 };
    }

    function submitAplicar() {
        if (!aplicando) return;
        setSaving(true);

        // Anticipo multi-producto (pendiente por entregar del POS): entrega
        // parcial por ítem — "solo te doy tanto de esto, lo demás queda".
        if (esMultiItem(aplicando)) {
            router.post(route('finanzas.anticipos.aplicar', aplicando.id), {
                fecha:       formAplicar.fecha,
                observacion: formAplicar.observacion,
                items: (aplicando.items ?? [])
                    .map(i => ({ id: i.id, cantidad: parseFloat(entregas[i.id] ?? '') || 0 }))
                    .filter(i => i.cantidad > 0),
            } as any, {
                onSuccess: () => { setAplicando(null); setSaving(false); },
                onError:   (errs: any) => { setErrors(errs); setSaving(false); },
            });
            return;
        }

        router.post(route('finanzas.anticipos.aplicar', aplicando.id), {
            ...formAplicar,
            // En material el monto lo calcula el backend (prorrata del anticipo).
            monto:        aplicando.tipo_valorizacion === 'material' ? null : formAplicar.monto,
            cantidad:     aplicando.tipo_valorizacion === 'material' ? (formAplicar.cantidad || null) : null,
            // Método/cuenta solo en dinero (de dónde sale el egreso de caja).
            metodo_pago_id: aplicando.tipo_valorizacion === 'material' ? null : (formAplicar.metodo_pago_id || null),
            cuenta_id:      aplicando.tipo_valorizacion === 'material' ? null : (formAplicar.cuenta_id || null),
            exceso_a_cxc: excesoACxc,
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

    /** Abre el modal de edición de una entrega de dinero. */
    function abrirEditarEntrega(ap: Aplicacion) {
        setErrors({});
        setFormEntrega({
            monto: String(ap.monto), fecha: ap.fecha, observacion: ap.observacion ?? '',
            metodo_pago_id: ap.metodo_pago_id ? String(ap.metodo_pago_id) : '',
            cuenta_id:      ap.cuenta_id ? String(ap.cuenta_id) : '',
        });
        setEditandoEntrega(ap);
    }

    function submitEditarEntrega() {
        if (!editandoEntrega) return;
        setSaving(true);
        router.put(route('finanzas.anticipos.entrega.editar', editandoEntrega.id), formEntrega as any, {
            onSuccess: () => { setEditandoEntrega(null); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    /** Abre el modal de edición de una entrega MATERIAL del POS. */
    function abrirEditarEntregaMaterial(ap: Aplicacion) {
        setErrors({});
        const itemsInicial: Record<number, string> = {};
        (ap.items ?? []).forEach(ai => {
            itemsInicial[ai.id] = String(Number(ai.cantidad));
        });
        setFormEntregaMaterial({
            fecha:       ap.fecha,
            observacion: ap.observacion ?? '',
            items:       itemsInicial,
        });
        setEditandoEntregaMaterial(ap);
    }

    function submitEditarEntregaMaterial() {
        if (!editandoEntregaMaterial) return;
        setSaving(true);
        router.put(route('finanzas.anticipos.entrega.editar', editandoEntregaMaterial.id), {
            fecha:       formEntregaMaterial.fecha,
            observacion: formEntregaMaterial.observacion,
            items: (editandoEntregaMaterial.items ?? []).map(ai => ({
                id:       ai.id,
                cantidad: parseFloat(formEntregaMaterial.items[ai.id] ?? '') || 0,
            })).filter(i => i.cantidad > 0),
        } as any, {
            onSuccess: () => { setEditandoEntregaMaterial(null); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnularEntrega() {
        if (!anulandoEntrega) return;
        setSaving(true);
        router.post(route('finanzas.anticipos.entrega.anular', anulandoEntrega.id), { motivo: motivoEntrega } as any, {
            onSuccess: () => { setAnulandoEntrega(null); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<Anticipo>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (a) => <span className="text-sm">{new Date(a.fecha + 'T00:00:00').toLocaleDateString('es-PE')}</span>,
        },
        { key: 'cliente', label: 'Cliente', sortKey: 'cliente.nombres', render: (a) => <span className="font-medium">{nombreCliente(a.cliente)}</span> },
        {
            key: 'tipo_valorizacion', label: 'Modalidad',
            render: (a) => {
                // Ya entregado (estado 'aplicado') → VERDE, para no confundir con
                // lo que aún está por entregar (amarillo).
                const entregado = a.estado === 'aplicado';
                if (esMultiItem(a)) {
                    return (
                        <Badge variant={entregado ? 'success' : 'warning'}>
                            {entregado ? 'Entregado' : 'Por entregar'}{a.venta?.numero ? ` · Venta ${a.venta.numero}` : ''} ({a.items!.length} prod.)
                        </Badge>
                    );
                }
                if (a.tipo_valorizacion === 'material') {
                    return <Badge variant={entregado ? 'success' : 'primary'}>Material: {a.producto?.nombre ?? '—'}</Badge>;
                }
                return <Badge variant={entregado ? 'success' : 'secondary'}>Dinero</Badge>;
            },
        },
        { key: 'monto', label: 'Recibido', align: 'right', render: (a) => <span>{money(a.monto)}</span> },
        {
            key: 'pendiente', label: 'Pendiente', sortable: false, align: 'right',
            render: (a) => esMultiItem(a)
                ? (
                    <div className="text-sm leading-tight">
                        <div>{pendienteTotal(a)} und</div>
                        {a.fecha_entrega_estimada && a.estado === 'activo' && (
                            <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                                entrega est. {new Date(a.fecha_entrega_estimada + 'T00:00:00').toLocaleDateString('es-PE')}
                            </div>
                        )}
                    </div>
                )
                : a.tipo_valorizacion === 'material'
                    ? <span className="text-sm">{Number(a.cantidad_pendiente ?? 0)} und</span>
                    : <span className="text-sm">{money(a.saldo)}</span>,
        },
        {
            key: 'valor_hoy', label: 'Pasivo', sortKey: 'valor_pasivo', align: 'right',
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
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (a) => (
                <div className="flex items-center gap-1.5">
                    <button onClick={() => setDetalle(a)} className="p-1.5 rounded-lg hover:bg-black/5" title="Ver aplicaciones"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <Eye size={15} />
                    </button>
                    {puedeEditar(a) && (
                        <button onClick={() => abrirEditar(a)}
                            className="p-1.5 rounded-lg hover:bg-black/5" title="Editar anticipo (solo en dinero)"
                            style={{ color: 'var(--color-text-muted)' }}>
                            <Pencil size={15} />
                        </button>
                    )}
                    {a.estado === 'activo' && (
                        <>
                            <button onClick={() => {
                                setErrors({}); setExcesoACxc(false);
                                setFormAplicar({ fecha: hoy(), monto: a.tipo_valorizacion === 'material' ? '' : String(a.saldo), cantidad: '', observacion: '', metodo_pago_id: a.metodo_pago_id ? String(a.metodo_pago_id) : '', cuenta_id: a.cuenta_id ? String(a.cuenta_id) : '' });
                                // Multi-producto: por defecto se entrega TODO lo pendiente;
                                // el usuario puede bajar cantidades ("solo te doy tanto").
                                setEntregas(Object.fromEntries((a.items ?? []).map(i => [i.id, String(Number(i.cantidad_pendiente))])));
                                setAplicando(a);
                            }}
                                className="p-1.5 rounded-lg hover:bg-black/5" title="Registrar entrega/aplicación"
                                style={{ color: 'var(--color-primary)' }}>
                                <PackageCheck size={15} />
                            </button>
                            <button onClick={() => { setErrors({}); setFormAnular({ accion: 'devuelto', motivo: '', metodo_pago_id: a.metodo_pago_id ? String(a.metodo_pago_id) : '', cuenta_id: a.cuenta_id ? String(a.cuenta_id) : '', fecha: hoy() }); setAnulando(a); }}
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
                icon={<PiggyBank size={22} />}
                title="Anticipos de clientes"
                subtitle="Dinero recibido por adelantado a cambio de mercadería futura"
                actions={
                    <Button onClick={() => { setErrors({}); setForm({ ...emptyForm(), turno_id: turnoActivoId ? String(turnoActivoId) : '' }); setModalNuevo(true); }}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nuevo anticipo
                    </Button>
                }
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 lg:grid-cols-4" stats={[
                    {
                        label: 'Pasivo a precio del día', valor: money(totalPasivo), color: 'danger', destacado: true,
                        icon: <PiggyBank size={19} />, sub: 'Se revaloriza con el precio de venta de hoy',
                    },
                    {
                        label: 'En mercadería', valor: money(kpis.material), color: 'warning',
                        icon: <Package size={19} />, sub: 'Pendiente por entregar (material)',
                    },
                    {
                        label: 'En dinero', valor: money(kpis.dinero), color: 'primary',
                        icon: <Coins size={19} />, sub: 'Saldo en soles por aplicar',
                    },
                    {
                        label: 'Clientes con anticipo', valor: kpis.clientes, color: 'primary',
                        icon: <Users size={19} />, sub: `${kpis.activos} anticipo${kpis.activos !== 1 ? 's' : ''} activo${kpis.activos !== 1 ? 's' : ''}`,
                    },
                ]} />
            </div>

            <FiltrosCard cols={3}>
                <Select label="Estado" value={estado}
                    onChange={(v) => router.get(route('finanzas.anticipos.index'), { estado: v, buscar: buscar || undefined }, { preserveState: true, replace: true })}
                    options={[
                        { value: 'activos',  label: 'Activos' },
                        { value: 'aplicado', label: 'Aplicados' },
                        { value: 'devuelto', label: 'Devueltos' },
                        { value: 'anulado',  label: 'Anulados' },
                        { value: 'todos',    label: 'Todos' },
                    ]} />
            </FiltrosCard>

            <Table data={anticipos} columns={columns}
                exportFilename="anticipos"
                searchPlaceholder="Buscar cliente..." emptyMessage="No hay anticipos registrados"
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('finanzas.anticipos.index'),
                    { estado, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })}
                onExportExcel={() => {
                    const params = new URLSearchParams();
                    if (estado && estado !== 'todos') params.set('estado', estado);
                    if (buscar) params.set('buscar', buscar);
                    const url = route('finanzas.anticipos.exportar') + (params.toString() ? `?${params.toString()}` : '');
                    window.open(url, '_blank');
                }} />

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
                    <div>
                        <div className="flex items-end gap-2">
                            <div className="flex-1 min-w-0">
                                <SearchableSelect label="Cliente" required
                                    options={clientes.map(c => ({ value: String(c.id), label: nombreCliente(c) }))}
                                    value={form.cliente_id}
                                    onChange={v => setForm(f => ({ ...f, cliente_id: String(v) }))}
                                    placeholder="— Seleccionar cliente —"
                                    searchPlaceholder="Buscar por nombre..."
                                    error={errors.cliente_id}
                                />
                            </div>
                            {/* Alta rápida de cliente sin salir del anticipo */}
                            <Button type="button" variant="secondary" startContent={<UserPlus size={15} />}
                                onClick={() => setModalCrearCliente(true)}
                                title="Crear nuevo cliente">
                                Nuevo
                            </Button>
                        </div>
                        {/* Acceso rápido a "Clientes varios" (depósito sin dueño identificado) */}
                        {clienteGeneral && form.cliente_id !== String(clienteGeneral.id) && (
                            <button type="button"
                                onClick={() => setForm(f => ({ ...f, cliente_id: String(clienteGeneral.id) }))}
                                className="mt-1 text-[11px] font-medium hover:underline"
                                style={{ color: 'var(--color-primary)' }}>
                                Usar «Clientes varios»
                            </button>
                        )}
                    </div>
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
                    {cuentasDeMetodo(form.metodo_pago_id).length > 0 ? (
                        <Select label="Cuenta destino"
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
                    {/* "Afecta caja a:" — a qué caja/turno entra el dinero del anticipo.
                        Por defecto el turno activo del cajero; se puede elegir "Sin turno"
                        u otro. Solo el efectivo con turno suma al esperado de esa caja. */}
                    <AfectaCajaSelect
                        modulo="anticipos" modo="libre" formato="largo"
                        label="Afecta caja a (turno)"
                        sinTurnoLabel="Sin turno (no afecta caja)"
                        turnos={turnos}
                        value={form.turno_id === '' ? '' : Number(form.turno_id)}
                        onChange={v => setForm(f => ({ ...f, turno_id: v === '' ? '' : String(v) }))}
                        hint="Si el anticipo es en efectivo, entra a la caja de este turno y suma a su efectivo esperado. «Sin turno» no afecta ninguna caja."
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

            {/* Modal editar anticipo (solo en dinero) */}
            <Modal isOpen={editando !== null} onClose={() => setEditando(null)}
                title={editando ? `Editar anticipo — ${nombreCliente(editando.cliente)}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button onClick={submitEditar} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambios'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Callout variant="info">
                        Editar reasienta el dinero en tesorería con los nuevos datos. Si el anticipo es en efectivo con turno, ajusta la caja de ese turno.
                    </Callout>
                    <SearchableSelect label="Cliente" required
                        options={clientes.map(c => ({ value: String(c.id), label: nombreCliente(c) }))}
                        value={form.cliente_id}
                        onChange={v => setForm(f => ({ ...f, cliente_id: String(v) }))}
                        placeholder="— Seleccionar cliente —"
                        searchPlaceholder="Buscar por nombre..."
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
                    {cuentasDeMetodo(form.metodo_pago_id).length > 0 && (
                        <Select label="Cuenta destino"
                            options={cuentasDeMetodo(form.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                            value={form.cuenta_id}
                            onChange={v => setForm(f => ({ ...f, cuenta_id: String(v) }))}
                            placeholder="— Seleccionar —"
                        />
                    )}
                    <AfectaCajaSelect
                        modulo="anticipos" modo="libre" formato="largo"
                        label="Afecta caja a (turno)"
                        sinTurnoLabel="Sin turno (no afecta caja)"
                        turnos={turnos}
                        value={form.turno_id === '' ? '' : Number(form.turno_id)}
                        onChange={v => setForm(f => ({ ...f, turno_id: v === '' ? '' : String(v) }))}
                        hint="Si el anticipo es en efectivo, entra a la caja de este turno y suma a su efectivo esperado. «Sin turno» no afecta ninguna caja."
                    />
                    <Input label="Observación" value={form.observacion}
                        onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))} />
                    {errors.anticipo && <Callout variant="danger">{errors.anticipo}</Callout>}
                </div>
            </Modal>

            {/* Modal aplicar (entrega de material / uso del anticipo) */}
            <Modal isOpen={aplicando !== null} onClose={() => setAplicando(null)}
                title={aplicando ? `Aplicar anticipo — ${nombreCliente(aplicando.cliente)}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAplicando(null)}>Cancelar</Button>
                        <Button onClick={submitAplicar}
                            disabled={saving
                                || (!!aplicando && !esMultiItem(aplicando) && calcularDespacho(aplicando).excesoMonto > 0 && !excesoACxc)
                                || (!!aplicando && esMultiItem(aplicando) && !(aplicando.items ?? []).some(i => (parseFloat(entregas[i.id] ?? '') || 0) > 0))}
                            title={!!aplicando && !esMultiItem(aplicando) && calcularDespacho(aplicando).excesoMonto > 0 && !excesoACxc
                                ? 'Confirma qué hacer con el excedente para continuar' : undefined}>
                            {saving ? 'Guardando...' : 'Registrar'}
                        </Button>
                    </>
                }
            >
                {/* Entrega de anticipo multi-producto (pendiente del POS):
                    cantidad por ítem ("solo te doy tanto, lo demás queda")
                    y fecha de ESTA entrega. El stock sale del almacén aquí. */}
                {aplicando && esMultiItem(aplicando) && (
                    <div className="space-y-4">
                        <StatGrid stats={[
                            { label: 'Pendiente por entregar', valor: `${pendienteTotal(aplicando)} und`, color: 'warning', destacado: true },
                            ...(aplicando.venta?.numero ? [{ label: 'Venta origen', valor: aplicando.venta.numero }] : []),
                        ]} />
                        <Input label="Fecha de la entrega" required type="date" value={formAplicar.fecha}
                            onChange={e => setFormAplicar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <div>
                            <p className="text-xs font-semibold mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                ¿Cuánto entregas de cada producto? (baja la cantidad si solo entregas parte)
                            </p>
                            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                {(aplicando.items ?? []).map((it, idx) => {
                                    const pendiente = Number(it.cantidad_pendiente);
                                    const valor     = entregas[it.id] ?? '';
                                    const num       = parseFloat(valor) || 0;
                                    const excedido  = num > pendiente + 0.00009;
                                    return (
                                        <div key={it.id} className="flex items-center gap-2 px-3 py-2 text-xs"
                                            style={{
                                                borderBottom: idx < (aplicando.items!.length - 1) ? '1px solid var(--color-border)' : undefined,
                                                backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                opacity: pendiente <= 0.0001 ? 0.5 : 1,
                                            }}>
                                            <div className="flex-1 min-w-0">
                                                <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</p>
                                                <p style={{ color: 'var(--color-text-muted)' }}>
                                                    pendiente: <strong>{pendiente}</strong> {it.unidad_nombre || 'und'}
                                                </p>
                                            </div>
                                            {pendiente > 0.0001 ? (
                                                <>
                                                    <input
                                                        type="number" min={0} max={pendiente} step="any" value={valor}
                                                        onChange={e => setEntregas(prev => ({ ...prev, [it.id]: e.target.value }))}
                                                        className="w-20 text-right rounded-lg px-2 py-1.5 border outline-none flex-shrink-0"
                                                        style={{
                                                            borderColor: excedido ? 'var(--color-danger)' : 'var(--color-border)',
                                                            backgroundColor: 'var(--color-bg)',
                                                            color: 'var(--color-text)',
                                                        }}
                                                    />
                                                    <span className="flex-shrink-0 w-20 text-right" style={{ color: excedido ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                                        {excedido ? `máx ${pendiente}` : num > 0 && num < pendiente - 0.00009 ? `quedan ${Math.round((pendiente - num) * 10000) / 10000}` : num > 0 ? 'completo' : 'no entrega'}
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="flex-shrink-0" style={{ color: 'var(--color-success)' }}>Entregado ✓</span>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                            {errors.items && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.items}</p>}
                            {Object.entries(errors).filter(([k]) => k.startsWith('items.')).map(([k, v]) => (
                                <p key={k} className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{v}</p>
                            ))}
                        </div>
                        <Callout variant="info">
                            Lo entregado sale del stock del almacén recién ahora (no salió al vender). Lo que no entregues queda pendiente para otra fecha.
                        </Callout>
                        <Input label="Observación" value={formAplicar.observacion}
                            onChange={e => setFormAplicar(f => ({ ...f, observacion: e.target.value }))} />
                    </div>
                )}

                {aplicando && !esMultiItem(aplicando) && (() => {
                    const { excesoCant, excesoMonto, precioDia } = calcularDespacho(aplicando);
                    return (
                        <div className="space-y-4">
                            <StatGrid stats={
                                aplicando.tipo_valorizacion === 'material'
                                    ? [
                                        { label: `Pendiente de ${aplicando.producto?.nombre ?? 'material'}`, valor: `${Number(aplicando.cantidad_pendiente ?? 0)} und`, color: 'warning', destacado: true },
                                        { label: 'Precio del día', valor: money(precioDia) },
                                    ]
                                    : [{ label: 'Saldo del anticipo', valor: money(aplicando.saldo), color: 'danger', destacado: true }]
                            } />
                            <Input label="Fecha" required type="date" value={formAplicar.fecha}
                                onChange={e => setFormAplicar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                            {aplicando.tipo_valorizacion === 'material' ? (
                                <Input label="Cantidad entregada" required type="number" min="0.0001" step="any" value={formAplicar.cantidad}
                                    onChange={e => setFormAplicar(f => ({ ...f, cantidad: e.target.value }))} error={errors.cantidad}
                                    hint="El monto del anticipo se descuenta solo, a prorrata de lo anticipado." />
                            ) : (
                                <>
                                    <Input label="Valor de la entrega (S/)" required type="number" min="0.01" step="0.01" value={formAplicar.monto}
                                        onChange={e => setFormAplicar(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                                    <Select label="Método de pago (por dónde sale el dinero)"
                                        options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                                        value={formAplicar.metodo_pago_id}
                                        onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormAplicar(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                                        error={errors.metodo_pago_id}
                                    />
                                    {cuentasDeMetodo(formAplicar.metodo_pago_id).length > 0 && (
                                        <Select label="Cuenta"
                                            options={cuentasDeMetodo(formAplicar.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                            value={formAplicar.cuenta_id}
                                            onChange={v => setFormAplicar(f => ({ ...f, cuenta_id: String(v) }))}
                                            error={errors.cuenta_id}
                                        />
                                    )}
                                </>
                            )}

                            {/* Excedente: se pregunta, nunca pasa a ciegas */}
                            {excesoMonto > 0 && (
                                <Callout variant="warning" title="La entrega excede lo anticipado"
                                    aside={money(excesoMonto)}>
                                    {aplicando.tipo_valorizacion === 'material'
                                        ? `Estás entregando ${excesoCant} und más de las anticipadas (valorizadas a precio de hoy: ${money(excesoMonto)}).`
                                        : `El valor entregado supera el saldo del anticipo por ${money(excesoMonto)}.`}
                                    <div className="mt-3">
                                        <Checkbox
                                            label="Registrar el excedente como cuenta por cobrar del cliente"
                                            description="Se crea una deuda por cobrar a su nombre (aparece en Deudas y préstamos y suma al balance)."
                                            checked={excesoACxc}
                                            onChange={e => setExcesoACxc(e.target.checked)}
                                        />
                                    </div>
                                </Callout>
                            )}
                            {errors.exceso && <Callout variant="danger">{errors.exceso}</Callout>}

                            <Input label="Observación" value={formAplicar.observacion}
                                onChange={e => setFormAplicar(f => ({ ...f, observacion: e.target.value }))} />
                        </div>
                    );
                })()}
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
                    {anulando && esMultiItem(anulando) && formAnular.accion === 'anulado' && (
                        <Callout variant="warning">
                            Este pendiente proviene de una venta del POS{anulando.venta?.numero ? ` (${anulando.venta.numero})` : ''}. Para revertirlo, anula esa venta desde el historial: eso ajusta stock y tesorería juntos.
                        </Callout>
                    )}
                    {errors.accion && <Callout variant="danger">{errors.accion}</Callout>}

                    {/* Devolución de dinero: monto a devolver + por dónde y cuándo sale. */}
                    {anulando && formAnular.accion === 'devuelto' && (
                        <>
                            <div className="rounded-lg px-3 py-2 flex items-center justify-between"
                                 style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 8%, transparent)' }}>
                                <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Monto a devolver</span>
                                <span className="text-lg font-bold" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                    S/ {Number(anulando.saldo).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                            <Select label="Método de pago (por dónde sale el dinero)"
                                options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                                value={formAnular.metodo_pago_id}
                                onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormAnular(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                                error={errors.metodo_pago_id}
                            />
                            {cuentasDeMetodo(formAnular.metodo_pago_id).length > 0 && (
                                <Select label="Cuenta"
                                    options={cuentasDeMetodo(formAnular.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                    value={formAnular.cuenta_id}
                                    onChange={v => setFormAnular(f => ({ ...f, cuenta_id: String(v) }))}
                                    error={errors.cuenta_id}
                                />
                            )}
                            <Input type="date" label="Fecha de la devolución" value={formAnular.fecha}
                                onChange={e => setFormAnular(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                            {/* La caja afectada no se pregunta: se deriva de quién registra la
                                devolución y de su turno abierto en ese momento. Si devuelves en
                                efectivo estando en turno, sale de tu cajón. */}
                            {turnoActivoId && (
                                <Callout variant="info">
                                    Si devuelves en efectivo, el billete sale de la caja de tu turno abierto
                                    y se resta de su efectivo esperado.
                                </Callout>
                            )}
                        </>
                    )}

                    <Input label="Motivo" required value={formAnular.motivo}
                        onChange={e => setFormAnular(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                </div>
            </Modal>

            {/* Modal detalle aplicaciones */}
            <Modal isOpen={detalle !== null} onClose={() => setDetalle(null)}
                title={detalle ? `Aplicaciones — ${nombreCliente(detalle.cliente)}` : ''} size="3xl"
                footer={<Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <div className="space-y-4">
                        {/* Desglose de productos pendientes (anticipos del POS) */}
                        {esMultiItem(detalle) && (
                            <div>
                                <p className="text-xs font-semibold mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Productos {detalle.venta?.numero ? `de la venta ${detalle.venta.numero}` : 'comprometidos'}
                                    {detalle.fecha_entrega_estimada
                                        ? ` · entrega estimada ${new Date(detalle.fecha_entrega_estimada + 'T00:00:00').toLocaleDateString('es-PE')}`
                                        : ''}
                                </p>
                                <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                    {(detalle.items ?? []).map((it, idx) => {
                                        const pendiente = Number(it.cantidad_pendiente);
                                        return (
                                            <div key={it.id} className="flex items-center justify-between gap-2 px-3 py-2 text-xs"
                                                style={{
                                                    borderBottom: idx < (detalle.items!.length - 1) ? '1px solid var(--color-border)' : undefined,
                                                    backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                }}>
                                                <span className="font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                                    {it.producto_nombre} <span style={{ color: 'var(--color-text-muted)' }}>× {Number(it.cantidad)} {it.unidad_nombre || 'und'}</span>
                                                </span>
                                                {pendiente > 0.0001 ? (
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="warning">quedan {pendiente}</Badge>
                                                    {puede?.editar && detalle.estado === 'activo' && (
                                                        <>
                                                            <button onClick={() => abrirCambiarProducto(detalle, it)} title="Cambiar producto de esta línea pendiente"
                                                                className="inline-flex items-center gap-1 rounded-lg px-1.5 py-0.5 text-[10px] font-medium border transition-colors hover:bg-black/5"
                                                                style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}>
                                                                <Pencil size={10} /> Cambiar producto
                                                            </button>
                                                            <button onClick={() => abrirCancelarPendiente(detalle, it)} title="Cancelar el pendiente de esta línea"
                                                                className="inline-flex items-center gap-1 rounded-lg px-1.5 py-0.5 text-[10px] font-medium border transition-colors hover:bg-black/5"
                                                                style={{ borderColor: 'var(--color-danger)', color: 'var(--color-danger)' }}>
                                                                <Ban size={10} /> Cancelar pendiente
                                                            </button>
                                                        </>
                                                    )}
                                                    </div>
                                                ) : (
                                                    <Badge variant="success">entregado</Badge>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {detalle.cancelaciones && detalle.cancelaciones.length > 0 && (
                            <div>
                                <p className="text-xs font-semibold mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Cancelaciones registradas
                                </p>
                                <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                    {detalle.cancelaciones.map((c, idx) => (
                                        <div key={c.id} className="flex flex-col gap-1 px-3 py-2 text-xs"
                                            style={{
                                                borderBottom: idx < (detalle.cancelaciones!.length - 1) ? '1px solid var(--color-border)' : undefined,
                                                backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                            }}>
                                            <div className="flex items-center justify-between gap-2">
                                                <span style={{ color: 'var(--color-text)' }}>
                                                    {new Date(c.fecha + 'T00:00:00').toLocaleDateString('es-PE')} · {Number(c.cantidad)} und
                                                </span>
                                                <span className="font-medium" style={{ color: 'var(--color-danger)' }}>{money(c.monto)}</span>
                                            </div>
                                            <div className="flex items-center justify-between gap-2" style={{ color: 'var(--color-text-muted)' }}>
                                                <span className="truncate">{c.motivo}</span>
                                                <span className="flex-shrink-0">{c.turno ? `#T${c.turno.id}${c.caja?.nombre ? ` · ${c.caja.nombre}` : ''}` : 'Sin turno'}</span>
                                            </div>
                                            {(c.metodo_pago || c.cuenta) && (
                                                <div style={{ color: 'var(--color-text-muted)' }}>
                                                    {c.metodo_pago?.nombre ?? '—'}{c.cuenta ? ` · ${c.cuenta.nombre}` : ''}
                                                </div>
                                            )}
                                            {c.observacion && <div style={{ color: 'var(--color-text-muted)' }}>{c.observacion}</div>}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <Timeline
                            emptyMessage="Sin aplicaciones registradas"
                            items={detalle.aplicaciones.map(ap => {
                                const info = [
                                    ap.items?.length
                                        ? ap.items.map(ai => `${Number(ai.cantidad)} × ${ai.item?.producto_nombre ?? 'ítem'}`).join(', ')
                                        : (ap.cantidad ? `${Number(ap.cantidad)} und` : null),
                                    ap.venta?.numero ? `Venta ${ap.venta.numero}` : null,
                                    ap.observacion,
                                ].filter(Boolean).join(' · ');
                                return {
                                    fecha: new Date(ap.fecha + 'T00:00:00').toLocaleDateString('es-PE'),
                                    badge: { texto: ap.numero ?? 'Entrega', variant: 'success' as const },
                                    tipo: 'neutro' as const,
                                    detalle: (
                                        <div className="flex items-center gap-2 flex-wrap">
                                            {info && <span className="mr-1">{info}</span>}
                                            <button onClick={() => imprimirEntrega(ap)} title="Imprimir ticket térmico de la entrega"
                                                className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium border transition-colors hover:bg-black/5"
                                                style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}>
                                                <Printer size={13} /> Ticket
                                            </button>
                                            <button onClick={() => verDocumentoEntrega(ap)} title="Abrir documento A4 / Guardar PDF"
                                                className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium border transition-colors hover:bg-black/5"
                                                style={{ borderColor: 'var(--color-primary)', color: 'var(--color-primary)' }}>
                                                <FileDown size={13} /> PDF A4
                                            </button>
                                            {/* Editar / anular la entrega: dinero (no POS) o material multi-producto del POS. */}
                                            {puedeEditarEntregas && (
                                                (detalle.tipo_valorizacion === 'monto' && !esMultiItem(detalle) && !detalle.venta) ||
                                                (esMultiItem(detalle) && detalle.tipo_valorizacion === 'material')
                                            ) && (
                                                <>
                                                    <button onClick={() => esMultiItem(detalle) ? abrirEditarEntregaMaterial(ap) : abrirEditarEntrega(ap)} title={esMultiItem(detalle) ? 'Editar cantidades entregadas' : 'Editar esta entrega (monto/fecha)'}
                                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium border transition-colors hover:bg-black/5"
                                                        style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}>
                                                        <Pencil size={13} /> Editar
                                                    </button>
                                                    <button onClick={() => { setErrors({}); setMotivoEntrega(''); setAnulandoEntrega(ap); }} title="Anular esta entrega (el anticipo vuelve a estar disponible)"
                                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium border transition-colors hover:bg-black/5"
                                                        style={{ borderColor: 'var(--color-danger)', color: 'var(--color-danger)' }}>
                                                        <Trash2 size={13} /> Anular
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    ),
                                    user: ap.user?.name,
                                    monto: Number(ap.monto),
                                };
                            })}
                        />
                    </div>
                )}
            </Modal>

            {/* Modal EDITAR entrega de dinero */}
            <Modal isOpen={editandoEntrega !== null} onClose={() => setEditandoEntrega(null)}
                title={editandoEntrega ? `Editar entrega ${editandoEntrega.numero ?? ''}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditandoEntrega(null)}>Cancelar</Button>
                        <Button onClick={submitEditarEntrega} disabled={saving}>{saving ? 'Guardando...' : 'Guardar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Callout variant="info">
                        Al cambiar el monto se recalcula el saldo del anticipo y se reasienta el egreso de caja por el método/cuenta que elijas.
                    </Callout>
                    <Input type="number" step="0.01" label="Monto entregado" required value={formEntrega.monto}
                        onChange={e => setFormEntrega(f => ({ ...f, monto: e.target.value }))} error={errors.monto} />
                    <Select label="Método de pago (por dónde sale el dinero)"
                        options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                        value={formEntrega.metodo_pago_id}
                        onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormEntrega(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                        error={errors.metodo_pago_id}
                    />
                    {cuentasDeMetodo(formEntrega.metodo_pago_id).length > 0 && (
                        <Select label="Cuenta"
                            options={cuentasDeMetodo(formEntrega.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                            value={formEntrega.cuenta_id}
                            onChange={v => setFormEntrega(f => ({ ...f, cuenta_id: String(v) }))}
                            error={errors.cuenta_id}
                        />
                    )}
                    <Input type="date" label="Fecha" required value={formEntrega.fecha}
                        onChange={e => setFormEntrega(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                    <Input label="Observación" value={formEntrega.observacion}
                        onChange={e => setFormEntrega(f => ({ ...f, observacion: e.target.value }))} error={errors.observacion} />
                    {errors.entrega && <Callout variant="danger">{errors.entrega}</Callout>}
                </div>
            </Modal>

            {/* Modal EDITAR entrega MATERIAL del POS */}
            <Modal isOpen={editandoEntregaMaterial !== null} onClose={() => setEditandoEntregaMaterial(null)}
                title={editandoEntregaMaterial ? `Editar entrega ${editandoEntregaMaterial.numero ?? ''}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditandoEntregaMaterial(null)}>Cancelar</Button>
                        <Button onClick={submitEditarEntregaMaterial} disabled={saving}>{saving ? 'Guardando...' : 'Guardar'}</Button>
                    </>
                }
            >
                {editandoEntregaMaterial && (
                    <div className="space-y-4">
                        <Callout variant="info">
                            Puedes corregir las cantidades entregadas de cada producto. El stock y el pendiente del anticipo se ajustan automáticamente.
                        </Callout>
                        <Input type="date" label="Fecha" required value={formEntregaMaterial.fecha}
                            onChange={e => setFormEntregaMaterial(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <div>
                            <p className="text-xs font-semibold mb-1.5" style={{ color: 'var(--color-text-muted)' }}>Cantidades entregadas</p>
                            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                {(editandoEntregaMaterial.items ?? []).map((ai, idx) => {
                                    const maximo = Number(ai.item?.cantidad_pendiente ?? 0) + Number(ai.cantidad);
                                    const valor = formEntregaMaterial.items[ai.id] ?? '';
                                    const num = parseFloat(valor) || 0;
                                    const excedido = num > maximo + 0.00009;
                                    return (
                                        <div key={ai.id} className="flex items-center gap-2 px-3 py-2 text-xs"
                                            style={{
                                                borderBottom: idx < ((editandoEntregaMaterial.items!.length) - 1) ? '1px solid var(--color-border)' : undefined,
                                                backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                            }}>
                                            <div className="flex-1 min-w-0">
                                                <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{ai.item?.producto_nombre ?? 'ítem'}</p>
                                                <p style={{ color: 'var(--color-text-muted)' }}>máx. {maximo} {ai.item?.unidad_nombre || 'und'}</p>
                                            </div>
                                            <input
                                                type="number" min={0} max={maximo} step="any" value={valor}
                                                onChange={e => setFormEntregaMaterial(f => ({ ...f, items: { ...f.items, [ai.id]: e.target.value } }))}
                                                className="w-20 text-right rounded-lg px-2 py-1.5 border outline-none flex-shrink-0"
                                                style={{
                                                    borderColor: excedido ? 'var(--color-danger)' : 'var(--color-border)',
                                                    backgroundColor: 'var(--color-bg)',
                                                    color: 'var(--color-text)',
                                                }}
                                            />
                                            <span className="flex-shrink-0 w-20 text-right" style={{ color: excedido ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                                {excedido ? `máx ${maximo}` : num > 0 ? 'entrega' : 'anula'}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                            {errors.items && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.items}</p>}
                        </div>
                        <Input label="Observación" value={formEntregaMaterial.observacion}
                            onChange={e => setFormEntregaMaterial(f => ({ ...f, observacion: e.target.value }))} error={errors.observacion} />
                        {errors.entrega && <Callout variant="danger">{errors.entrega}</Callout>}
                    </div>
                )}
            </Modal>

            {/* Modal ANULAR entrega de dinero */}
            <Modal isOpen={anulandoEntrega !== null} onClose={() => setAnulandoEntrega(null)}
                title={anulandoEntrega ? `Anular entrega ${anulandoEntrega.numero ?? ''}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulandoEntrega(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitAnularEntrega} disabled={saving}>{saving ? 'Anulando...' : 'Anular entrega'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {anulandoEntrega && (
                        <div className="rounded-lg px-3 py-2 flex items-center justify-between"
                             style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 8%, transparent)' }}>
                            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Se restituye al anticipo</span>
                            <span className="text-lg font-bold" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                S/ {Number(anulandoEntrega.monto).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </span>
                        </div>
                    )}
                    <Callout variant="warning">
                        La entrega se elimina y el anticipo vuelve a estar disponible por ese monto (no es una devolución al cliente). Queda registrado en auditoría.
                    </Callout>
                    <Input label="Motivo" required value={motivoEntrega}
                        onChange={e => setMotivoEntrega(e.target.value)} error={errors.motivo || errors.entrega} />
                </div>
            </Modal>

            {/* Modal CAMBIAR PRODUCTO de línea pendiente (anticipo material del POS). */}
            <Modal isOpen={cambiandoItem !== null} onClose={() => setCambiandoItem(null)}
                title={cambiandoItem ? `Cambiar producto — ${cambiandoItem.item.producto_nombre}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCambiandoItem(null)}>Cancelar</Button>
                        <Button onClick={submitCambiarProducto} disabled={saving}>{saving ? 'Guardando...' : 'Guardar cambio'}</Button>
                    </>
                }
            >
                {cambiandoItem && (
                    <div className="space-y-4">
                        <Callout variant="info">
                            El cliente pagó <strong>{Number(cambiandoItem.item.cantidad_pendiente)} {cambiandoItem.item.unidad_nombre || 'und'}</strong> pendientes de <strong>{cambiandoItem.item.producto_nombre}</strong>. Aquí puedes indicar que una parte se entregará con otro producto/marca.
                        </Callout>
                        <SearchableSelect label="Nuevo producto" required
                            options={productos.map(p => ({ value: String(p.id), label: `${p.nombre} (venta: ${money(p.precio_venta)})` }))}
                            value={formCambiar.producto_id}
                            onChange={v => setFormCambiar(f => ({ ...f, producto_id: String(v) }))}
                            placeholder="— Seleccionar producto destino —"
                            searchPlaceholder="Buscar producto..."
                            error={errors.nuevo_producto_id}
                        />
                        <Input label="Cantidad a cambiar" required type="number" min="0.0001" step="any"
                            value={formCambiar.cantidad}
                            onChange={e => setFormCambiar(f => ({ ...f, cantidad: e.target.value }))}
                            error={errors.cantidad}
                        />
                        <Input label="Motivo" required value={formCambiar.motivo}
                            onChange={e => setFormCambiar(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                    </div>
                )}
            </Modal>

            {/* Modal CANCELAR PENDIENTE de línea (anticipo material del POS). */}
            <Modal isOpen={cancelandoItem !== null} onClose={() => setCancelandoItem(null)}
                title={cancelandoItem ? `Cancelar pendiente — ${cancelandoItem.item.producto_nombre}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCancelandoItem(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={submitCancelarPendiente} disabled={saving}>{saving ? 'Cancelando...' : 'Confirmar cancelación'}</Button>
                    </>
                }
            >
                {cancelandoItem && (
                    <div className="space-y-4">
                        <Callout variant={cancelandoItem.anticipo.venta?.es_credito ? 'warning' : 'info'}>
                            {cancelandoItem.anticipo.venta?.es_credito
                                ? 'La venta es a CRÉDITO. No se devuelve dinero; se reduce la deuda del cliente.'
                                : 'La venta fue de CONTADO. Se devolverá dinero al cliente por el monto cancelado.'}
                        </Callout>
                        <div className="rounded-lg px-3 py-2 flex items-center justify-between"
                             style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 8%, transparent)' }}>
                            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Pendiente original</span>
                            <span className="text-lg font-bold" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                {Number(cancelandoItem.item.cantidad_pendiente).toLocaleString('es-PE')} {cancelandoItem.item.unidad_nombre || 'und'}
                            </span>
                        </div>
                        <Input label="Cantidad a cancelar" required type="number" min="0.0001" step="any"
                            max={Number(cancelandoItem.item.cantidad_pendiente)}
                            value={formCancelar.cantidad}
                            onChange={e => setFormCancelar(f => ({ ...f, cantidad: e.target.value }))}
                            error={errors.cantidad}
                        />
                        <div className="rounded-lg px-3 py-2 flex items-center justify-between"
                             style={{ backgroundColor: 'var(--color-surface)' }}>
                            <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Monto a devolver / reducir</span>
                            <span className="text-lg font-bold" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                {money(Math.min(
                                    Number(formCancelar.cantidad || 0),
                                    Number(cancelandoItem.item.cantidad_pendiente),
                                ) * Number(cancelandoItem.item.precio_unitario))}
                            </span>
                        </div>
                        <AfectaCajaSelect
                            modulo="anticipos_cancelacion" modo="libre" formato="largo"
                            label="Afecta caja a (turno)"
                            sinTurnoLabel="Sin turno (no afecta caja)"
                            turnos={turnos}
                            value={formCancelar.turno_id === '' ? '' : Number(formCancelar.turno_id)}
                            onChange={v => setFormCancelar(f => ({ ...f, turno_id: v === '' ? '' : String(v) }))}
                            hint="Si devuelves efectivo, el egreso se imputa a la caja de este turno. «Sin turno» solo lo registra sin afectar caja."
                        />
                        {!cancelandoItem.anticipo.venta?.es_credito && (
                            <>
                                <Select label="Método de pago (por dónde sale el dinero)"
                                    options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                                    value={formCancelar.metodo_pago_id}
                                    onChange={v => { const cts = cuentasDeMetodo(String(v)); setFormCancelar(f => ({ ...f, metodo_pago_id: String(v), cuenta_id: cts.length === 1 ? String(cts[0].id) : '' })); }}
                                    error={errors.metodo_pago_id}
                                />
                                {cuentasDeMetodo(formCancelar.metodo_pago_id).length > 0 && (
                                    <Select label="Cuenta"
                                        options={cuentasDeMetodo(formCancelar.metodo_pago_id).map(c => ({ value: String(c.id), label: c.nombre }))}
                                        value={formCancelar.cuenta_id}
                                        onChange={v => setFormCancelar(f => ({ ...f, cuenta_id: String(v) }))}
                                        error={errors.cuenta_id}
                                    />
                                )}
                            </>
                        )}
                        <Input type="date" label="Fecha de la cancelación" required value={formCancelar.fecha}
                            onChange={e => setFormCancelar(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Motivo" required value={formCancelar.motivo}
                            onChange={e => setFormCancelar(f => ({ ...f, motivo: e.target.value }))} error={errors.motivo} />
                        <Input label="Observación" value={formCancelar.observacion}
                            onChange={e => setFormCancelar(f => ({ ...f, observacion: e.target.value }))} />
                        {errors.anticipo && <Callout variant="danger">{errors.anticipo}</Callout>}
                    </div>
                )}
            </Modal>

            {/* Alta de cliente sin salir del anticipo (reutiliza el modal del POS).
                Al guardar, Inertia refresca la lista `clientes` y auto-seleccionamos
                el nuevo en el formulario en curso. */}
            <ModalCrearCliente
                isOpen={modalCrearCliente}
                onClose={() => setModalCrearCliente(false)}
                onCreated={(c: Cliente) => {
                    setForm(f => ({ ...f, cliente_id: String(c.id) }));
                    setModalCrearCliente(false);
                    toast.success(`Cliente "${nombreCliente(c as unknown as { nombres?: string; apellidos?: string; razon_social?: string })}" creado y seleccionado.`);
                }}
            />
        </AppLayout>
    );
}
