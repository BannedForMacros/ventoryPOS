import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import {
    Plus, Eye, Pencil, FileText, PhoneCall, CheckCircle2, XCircle, Ban,
    ShoppingCart, Search, Trash2, Phone, MessageCircle, Clock, AlertTriangle,
    CircleDollarSign, Printer, FileDown,
} from 'lucide-react';
import { imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
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
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import { hoyLocal } from '@/lib/fechas';
import type { PageProps } from '@/types';

// ── Tipos ──────────────────────────────────────────────────────────────────

interface ClienteLite {
    id: number;
    nombres?: string | null;
    apellidos?: string | null;
    razon_social?: string | null;
    telefono?: string | null;
}

interface UnidadCatalogo {
    id: number;
    nombre: string;
    precio_venta: number;
    es_base: boolean;
}

interface ProductoCatalogo {
    id: number;
    nombre: string;
    codigo: string | null;
    incluye_igv: boolean;
    unidades: UnidadCatalogo[];
}

interface CotizacionItem {
    id: number;
    producto_id: number;
    producto_unidad_id: number | null;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: string;
    precio_unitario: string;
    descuento_item: string;
    subtotal: string;
}

interface Cotizacion extends Record<string, unknown> {
    id: number;
    numero: string;
    referencia: string | null;
    estado: 'vigente' | 'aceptada' | 'rechazada' | 'vencida' | 'convertida' | 'anulada';
    fecha: string;
    fecha_vencimiento: string | null;
    fecha_entrega_estimada: string | null;
    subtotal: string;
    descuento_total: string;
    igv: string;
    total: string;
    observacion: string | null;
    notas_seguimiento: string | null;
    ultimo_contacto: string | null;
    cliente?: ClienteLite | null;
    user?: { id: number; name: string } | null;
    venta?: { id: number; numero: string } | null;
    items: CotizacionItem[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    cotizaciones: Paginado<Cotizacion>;
    kpis: {
        vigentes: number;
        monto_vigente: number;
        por_vencer: number;
        vencidas_sin_respuesta: number;
    };
    estado: string;
    q: string;
    clientes: ClienteLite[];
    productos: ProductoCatalogo[];
}

// ── Helpers ────────────────────────────────────────────────────────────────

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const fmtFecha = (f?: string | null) =>
    f ? new Date(f + 'T00:00:00').toLocaleDateString('es-PE') : '—';
const nombreCliente = (c?: ClienteLite | null) =>
    c?.razon_social ?? (`${c?.nombres ?? ''} ${c?.apellidos ?? ''}`.trim() || '—');

/** Días que faltan para una fecha (negativo = ya pasó). */
function diasPara(fecha: string): number {
    const a = new Date(hoy() + 'T00:00:00').getTime();
    const b = new Date(fecha + 'T00:00:00').getTime();
    return Math.round((b - a) / 86400000);
}

/** Número peruano → URL de WhatsApp (agrega 51 si viene sin código de país). */
function waLink(telefono: string): string {
    const digitos = telefono.replace(/\D/g, '');
    return `https://wa.me/${digitos.startsWith('51') && digitos.length > 9 ? digitos : `51${digitos}`}`;
}

const ESTADO_BADGE: Record<Cotizacion['estado'], { variant: 'primary' | 'success' | 'danger' | 'warning' | 'secondary'; label: string }> = {
    vigente:    { variant: 'primary', label: 'Vigente' },
    aceptada:   { variant: 'success', label: 'Aceptada' },
    rechazada:  { variant: 'danger',  label: 'Rechazada' },
    vencida:    { variant: 'warning', label: 'Vencida' },
    convertida: { variant: 'success', label: 'Convertida' },
    anulada:    { variant: 'danger',  label: 'Anulada' },
};

// Línea del editor de ítems (strings porque son inputs controlados).
interface LineaForm {
    key: string;
    producto_id: string;
    producto_unidad_id: string;
    cantidad: string;
    precio_unitario: string;
    descuento_item: string;
}

const uid = () => Math.random().toString(36).slice(2);

const lineaVacia = (): LineaForm => ({
    key: uid(), producto_id: '', producto_unidad_id: '', cantidad: '1', precio_unitario: '', descuento_item: '',
});

const emptyForm = () => ({
    cliente_id: '', referencia: '', fecha: hoy(), fecha_vencimiento: '', fecha_entrega_estimada: '', observacion: '',
});

// ── Página ─────────────────────────────────────────────────────────────────

export default function Cotizaciones({ cotizaciones, kpis, estado, q, clientes, productos }: Props) {
    const { flash, auth } = usePage<Props>().props;
    const tasaIgv = Number(auth?.user?.empresa?.tasa_igv ?? 18);

    const [modalForm, setModalForm]     = useState(false);
    const [editando, setEditando]       = useState<Cotizacion | null>(null);
    const [detalle, setDetalle]         = useState<Cotizacion | null>(null);
    const [contactando, setContactando] = useState<Cotizacion | null>(null);
    // Cambio de estado manual: cotización + estado destino.
    const [cambiando, setCambiando]     = useState<{ cot: Cotizacion; estado: 'aceptada' | 'rechazada' | 'anulada' } | null>(null);
    const [saving, setSaving]           = useState(false);
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [form, setForm]               = useState(emptyForm());
    const [lineas, setLineas]           = useState<LineaForm[]>([lineaVacia()]);
    const [formContacto, setFormContacto] = useState({ fecha: hoy(), nota: '' });
    const [motivo, setMotivo]           = useState('');
    const [busqueda, setBusqueda]       = useState(q ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const productosById = useMemo(
        () => new Map(productos.map(p => [String(p.id), p])),
        [productos],
    );

    // ── Totales en vivo del formulario (espejo de Cotizacion::recalcularTotales) ──
    const totales = useMemo(() => {
        const tasa = tasaIgv / 100;
        let subtotal = 0, descuentos = 0, baseGravada = 0;
        for (const l of lineas) {
            const cantidad = parseFloat(l.cantidad) || 0;
            const precio   = parseFloat(l.precio_unitario) || 0;
            const desc     = parseFloat(l.descuento_item) || 0;
            const importe  = (precio - desc) * cantidad;
            subtotal   += precio * cantidad;
            descuentos += desc * cantidad;
            const prod = productosById.get(l.producto_id);
            if (prod?.incluye_igv ?? true) {
                baseGravada += tasa > 0 ? importe / (1 + tasa) : importe;
            }
        }
        return {
            subtotal:   Math.round(subtotal * 100) / 100,
            descuentos: Math.round(descuentos * 100) / 100,
            igv:        Math.round(baseGravada * tasa * 100) / 100,
            total:      Math.round((subtotal - descuentos) * 100) / 100,
        };
    }, [lineas, productosById, tasaIgv]);

    // ── Handlers del editor de ítems ────────────────────────────────────
    function setLinea(key: string, patch: Partial<LineaForm>) {
        setLineas(prev => prev.map(l => l.key === key ? { ...l, ...patch } : l));
    }

    function elegirProducto(key: string, productoId: string) {
        const prod = productosById.get(productoId);
        const base = prod?.unidades.find(u => u.es_base) ?? prod?.unidades[0];
        setLineas(prev => {
            const next = prev.map(l => l.key === key ? {
                ...l,
                producto_id:        productoId,
                producto_unidad_id: base ? String(base.id) : '',
                precio_unitario:    base ? String(base.precio_venta) : '',
            } : l);
            // Carga en cadena: si se llenó la última línea, dejamos una vacía
            // lista para el siguiente producto sin tener que pulsar "Agregar línea".
            if (productoId && next[next.length - 1].producto_id) next.push(lineaVacia());
            return next;
        });
    }

    function elegirUnidad(key: string, linea: LineaForm, unidadId: string) {
        const prod   = productosById.get(linea.producto_id);
        const unidad = prod?.unidades.find(u => String(u.id) === unidadId);
        setLinea(key, {
            producto_unidad_id: unidadId,
            precio_unitario:    unidad ? String(unidad.precio_venta) : linea.precio_unitario,
        });
    }

    // ── Abrir modales ───────────────────────────────────────────────────
    function abrirNueva() {
        setErrors({});
        setEditando(null);
        setForm(emptyForm());
        setLineas([lineaVacia()]);
        setModalForm(true);
    }

    function abrirEdicion(c: Cotizacion) {
        setErrors({});
        setEditando(c);
        setForm({
            cliente_id:             String(c.cliente?.id ?? ''),
            referencia:             c.referencia ?? '',
            fecha:                  c.fecha,
            fecha_vencimiento:      c.fecha_vencimiento ?? '',
            fecha_entrega_estimada: c.fecha_entrega_estimada ?? '',
            observacion:            c.observacion ?? '',
        });
        setLineas(c.items.map(it => ({
            key:                uid(),
            producto_id:        String(it.producto_id),
            producto_unidad_id: String(it.producto_unidad_id ?? ''),
            cantidad:           String(Number(it.cantidad)),
            precio_unitario:    String(Number(it.precio_unitario)),
            descuento_item:     Number(it.descuento_item) > 0 ? String(Number(it.descuento_item)) : '',
        })));
        setModalForm(true);
    }

    // ── Submits ─────────────────────────────────────────────────────────
    function submitForm() {
        const items = lineas
            .filter(l => l.producto_id && l.producto_unidad_id)
            .map(l => ({
                producto_id:        Number(l.producto_id),
                producto_unidad_id: Number(l.producto_unidad_id),
                cantidad:           parseFloat(l.cantidad) || 0,
                precio_unitario:    parseFloat(l.precio_unitario) || 0,
                descuento_item:     parseFloat(l.descuento_item) || 0,
            }));

        if (items.length === 0) {
            toast.error('Agrega al menos un producto a la cotización.');
            return;
        }

        setSaving(true);
        const payload = {
            ...form,
            fecha_vencimiento:      form.fecha_vencimiento || null,
            fecha_entrega_estimada: form.fecha_entrega_estimada || null,
            referencia:             form.referencia || null,
            observacion:            form.observacion || null,
            items,
        };
        const opts = {
            onSuccess: () => { setModalForm(false); setSaving(false); },
            onError:   (errs: Record<string, string>) => { setErrors(errs); setSaving(false); },
        };
        if (editando) {
            router.put(route('cotizaciones.update', editando.id), payload as never, opts);
        } else {
            router.post(route('cotizaciones.store'), payload as never, opts);
        }
    }

    function submitContacto() {
        if (!contactando) return;
        setSaving(true);
        router.post(route('cotizaciones.contacto', contactando.id), formContacto as never, {
            onSuccess: () => { setContactando(null); setSaving(false); },
            onError:   (errs: Record<string, string>) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitEstado() {
        if (!cambiando) return;
        setSaving(true);
        router.post(route('cotizaciones.estado', cambiando.cot.id), { estado: cambiando.estado, motivo: motivo || null } as never, {
            onSuccess: () => { setCambiando(null); setSaving(false); },
            onError:   (errs: Record<string, string>) => { setErrors(errs); setSaving(false); },
        });
    }

    function buscar() {
        router.get(route('cotizaciones.index'), { estado, q: busqueda || undefined }, { preserveState: true, replace: true });
    }

    function convertirEnVenta(c: Cotizacion) {
        router.visit(route('pos.index', { cotizacion_id: c.id }));
    }

    // Imprimir la cotización en la ticketera (mismo flujo que en Ventas).
    async function imprimirTicketCot(c: Cotizacion) {
        const tid = toast.loading(`Enviando cotización ${c.numero}...`);
        try {
            const { data } = await axios.get<TicketPayload>(route('cotizaciones.ticket', c.id));
            if (!data?.token) {
                toast.error('No hay ticketera configurada para esta caja.', { id: tid });
                return;
            }
            const ok = await imprimirTicket(data);
            if (ok) toast.success(`Cotización ${c.numero} enviada`, { id: tid });
            else    toast.error('No se pudo imprimir. Revisa VentoryPrint en esta PC.', { id: tid });
        } catch {
            toast.error('No se pudo obtener la cotización.', { id: tid });
        }
    }

    // Abre la proforma A4 (HTML) en otra pestaña: imprimir o "Guardar como PDF".
    function verProforma(c: Cotizacion) {
        window.open(route('cotizaciones.proforma', c.id), '_blank');
    }

    // ── Columnas ────────────────────────────────────────────────────────
    const columns: Column<Cotizacion>[] = [
        {
            key: 'numero', label: 'Número', sortable: true,
            render: (c) => (
                <div className="leading-tight">
                    <span className="font-semibold">{c.numero}</span>
                    <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{fmtFecha(c.fecha)}</div>
                </div>
            ),
        },
        {
            key: 'cliente', label: 'Cliente',
            render: (c) => (
                <div className="leading-tight">
                    <span className="font-medium">{nombreCliente(c.cliente)}</span>
                    {c.cliente?.telefono && (
                        <div className="flex items-center gap-1.5 mt-0.5">
                            <a href={`tel:${c.cliente.telefono}`} title={`Llamar al ${c.cliente.telefono}`}
                                className="inline-flex items-center gap-1 text-[11px] hover:underline"
                                style={{ color: 'var(--color-primary)' }}>
                                <Phone size={11} />{c.cliente.telefono}
                            </a>
                            <a href={waLink(c.cliente.telefono)} target="_blank" rel="noopener noreferrer" title="Escribir por WhatsApp"
                                className="inline-flex items-center hover:opacity-80"
                                style={{ color: 'var(--color-success)' }}>
                                <MessageCircle size={12} />
                            </a>
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'referencia', label: 'Referencia / Obra',
            render: (c) => c.referencia
                ? <span className="text-sm">{c.referencia}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'total', label: 'Total', align: 'right',
            render: (c) => <span className="font-semibold">{money(c.total)}</span>,
        },
        {
            key: 'fecha_vencimiento', label: 'Vence', sortable: true,
            render: (c) => {
                if (!c.fecha_vencimiento) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
                const dias = diasPara(c.fecha_vencimiento);
                const activa = c.estado === 'vigente' || c.estado === 'vencida';
                if (activa && (dias < 0 || c.estado === 'vencida')) {
                    return <Badge variant="danger">Venció {fmtFecha(c.fecha_vencimiento)}</Badge>;
                }
                if (activa && dias <= 3) {
                    return <Badge variant="warning">{dias === 0 ? 'Vence hoy' : `Vence en ${dias} día${dias === 1 ? '' : 's'}`}</Badge>;
                }
                return <span className="text-sm">{fmtFecha(c.fecha_vencimiento)}</span>;
            },
        },
        {
            key: 'fecha_entrega_estimada', label: 'Entrega est.',
            render: (c) => <span className="text-sm">{fmtFecha(c.fecha_entrega_estimada)}</span>,
        },
        {
            key: 'estado', label: 'Estado',
            render: (c) => (
                <div className="leading-tight">
                    <Badge variant={ESTADO_BADGE[c.estado].variant}>{ESTADO_BADGE[c.estado].label}</Badge>
                    {c.estado === 'convertida' && c.venta?.numero && (
                        <div className="text-[10px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            Venta {c.venta.numero}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'ultimo_contacto', label: 'Últ. contacto',
            render: (c) => c.ultimo_contacto
                ? <span className="text-sm">{fmtFecha(c.ultimo_contacto)}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'acciones', label: 'Acciones',
            render: (c) => {
                const editable    = c.estado === 'vigente' || c.estado === 'vencida';
                const seguible    = ['vigente', 'vencida', 'aceptada'].includes(c.estado);
                const aceptable   = c.estado === 'vigente' || c.estado === 'vencida';
                const rechazable  = seguible;
                const anulable    = !['convertida', 'anulada'].includes(c.estado);
                const convertible = c.estado === 'vigente' || c.estado === 'aceptada';
                const btn = 'p-1.5 rounded-lg hover:bg-black/5';
                return (
                    <div className="flex items-center gap-0.5">
                        <button onClick={() => setDetalle(c)} className={btn} title="Ver detalle"
                            style={{ color: 'var(--color-text-muted)' }}><Eye size={15} /></button>
                        <button onClick={() => imprimirTicketCot(c)} className={btn} title="Imprimir ticket"
                            style={{ color: 'var(--color-text-muted)' }}><Printer size={15} /></button>
                        <button onClick={() => verProforma(c)} className={btn} title="Proforma PDF (A4)"
                            style={{ color: 'var(--color-primary)' }}><FileDown size={15} /></button>
                        {editable && (
                            <button onClick={() => abrirEdicion(c)} className={btn} title="Editar"
                                style={{ color: 'var(--color-warning)' }}><Pencil size={15} /></button>
                        )}
                        {seguible && (
                            <button onClick={() => { setErrors({}); setFormContacto({ fecha: hoy(), nota: '' }); setContactando(c); }}
                                className={btn} title="Registrar contacto"
                                style={{ color: 'var(--color-primary)' }}><PhoneCall size={15} /></button>
                        )}
                        {aceptable && (
                            <button onClick={() => { setErrors({}); setMotivo(''); setCambiando({ cot: c, estado: 'aceptada' }); }}
                                className={btn} title="Marcar aceptada"
                                style={{ color: 'var(--color-success)' }}><CheckCircle2 size={15} /></button>
                        )}
                        {rechazable && (
                            <button onClick={() => { setErrors({}); setMotivo(''); setCambiando({ cot: c, estado: 'rechazada' }); }}
                                className={btn} title="Marcar rechazada"
                                style={{ color: 'var(--color-danger)' }}><XCircle size={15} /></button>
                        )}
                        {anulable && (
                            <button onClick={() => { setErrors({}); setMotivo(''); setCambiando({ cot: c, estado: 'anulada' }); }}
                                className={btn} title="Anular"
                                style={{ color: 'var(--color-text-muted)' }}><Ban size={15} /></button>
                        )}
                        {convertible && (
                            <button onClick={() => convertirEnVenta(c)} className={btn} title="Convertir en venta (abre el POS)"
                                style={{ color: 'var(--color-success)' }}><ShoppingCart size={15} /></button>
                        )}
                    </div>
                );
            },
        },
    ];

    const TITULO_ESTADO: Record<string, string> = {
        aceptada:  'Marcar como aceptada',
        rechazada: 'Marcar como rechazada',
        anulada:   'Anular cotización',
    };

    return (
        <AppLayout title="Cotizaciones">
            <PageHeader
                icon={<FileText size={22} />}
                title="Cotizaciones"
                subtitle="Proformas con precios congelados, vigencia y seguimiento hasta convertirlas en venta"
                actions={
                    <Button onClick={abrirNueva}>
                        <Plus size={15} className="mr-1 flex-shrink-0" />Nueva cotización
                    </Button>
                }
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 sm:grid-cols-2 lg:grid-cols-4" stats={[
                    { label: 'Vigentes', valor: String(kpis.vigentes), icon: <FileText size={19} /> },
                    { label: 'Monto vigente', valor: money(kpis.monto_vigente), color: 'primary', destacado: true, icon: <CircleDollarSign size={19} />, sub: 'Total de cotizaciones vigentes' },
                    { label: 'Por vencer (≤3 días)', valor: String(kpis.por_vencer), color: 'warning', icon: <Clock size={19} /> },
                    { label: 'Vencidas sin respuesta', valor: String(kpis.vencidas_sin_respuesta), color: 'danger', icon: <AlertTriangle size={19} />, sub: 'Sin contacto tras el vencimiento' },
                ]} />
            </div>

            <div className="mb-5 flex flex-wrap items-center gap-3">
                <Tabs
                    tabs={[
                        { value: 'vigentes', label: 'Vigentes' },
                        { value: 'alerta',   label: 'Por vencer y vencidas' },
                        { value: 'todas',    label: 'Todas' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('cotizaciones.index'), { estado: v, q: busqueda || undefined }, { preserveState: true, replace: true })}
                />
                <div className="flex items-center gap-2 ml-auto">
                    <div className="relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input
                            value={busqueda}
                            onChange={e => setBusqueda(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter') buscar(); }}
                            placeholder="N°, cliente o referencia..."
                            className="pl-9 pr-3 py-2 text-sm rounded-lg border outline-none w-56"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                        />
                    </div>
                    <Button variant="ghost" onClick={buscar}>Buscar</Button>
                </div>
            </div>

            <Table data={cotizaciones.data} columns={columns} searchable={false}
                emptyMessage="No hay cotizaciones en este filtro" />

            {/* ── Modal crear / editar ─────────────────────────────────── */}
            <Modal isOpen={modalForm} onClose={() => setModalForm(false)}
                title={editando ? `Editar cotización ${editando.numero}` : 'Nueva cotización'} size="5xl"
                footer={
                    <>
                        <div className="flex-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            <span>Subtotal: <strong style={{ color: 'var(--color-text)' }}>{money(totales.subtotal)}</strong></span>
                            <span>Descuentos: <strong style={{ color: 'var(--color-danger)' }}>− {money(totales.descuentos)}</strong></span>
                            <span>IGV ({tasaIgv}%) incluido: <strong style={{ color: 'var(--color-text)' }}>{money(totales.igv)}</strong></span>
                            <span className="text-sm">Total: <strong style={{ color: 'var(--color-primary)' }}>{money(totales.total)}</strong></span>
                        </div>
                        <Button variant="ghost" onClick={() => setModalForm(false)}>Cancelar</Button>
                        <Button onClick={submitForm} disabled={saving}>
                            {saving ? 'Guardando...' : editando ? 'Guardar cambios' : 'Guardar cotización'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <SearchableSelect label="Cliente" required
                            options={clientes.map(c => ({ value: String(c.id), label: nombreCliente(c) }))}
                            value={form.cliente_id}
                            onChange={v => setForm(f => ({ ...f, cliente_id: String(v) }))}
                            placeholder="— Seleccionar cliente —"
                            error={errors.cliente_id}
                        />
                        <Input label="Referencia / Obra" value={form.referencia}
                            onChange={e => setForm(f => ({ ...f, referencia: e.target.value }))}
                            error={errors.referencia}
                            hint='Etiqueta libre según tu rubro: "Obra Av. Grau", "Paciente Firulais", "Proyecto X"...' />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Input label="Fecha" required type="date" value={form.fecha}
                            onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Válida hasta" type="date" value={form.fecha_vencimiento}
                            onChange={e => setForm(f => ({ ...f, fecha_vencimiento: e.target.value }))}
                            error={errors.fecha_vencimiento} hint="Vencida esta fecha, los precios dejan de estar garantizados" />
                        <Input label="Entrega estimada" type="date" value={form.fecha_entrega_estimada}
                            onChange={e => setForm(f => ({ ...f, fecha_entrega_estimada: e.target.value }))}
                            error={errors.fecha_entrega_estimada} />
                    </div>

                    {/* Editor de ítems */}
                    <div>
                        <p className="text-xs font-semibold mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                            Productos cotizados (el precio queda congelado hasta el vencimiento)
                        </p>
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {/* Cabecera del editor (solo desktop) */}
                            <div className="hidden md:grid grid-cols-[1fr_130px_80px_100px_100px_90px_32px] gap-2 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide"
                                style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)', borderBottom: '1px solid var(--color-border)' }}>
                                <span>Producto</span><span>Presentación</span><span className="text-right">Cant.</span>
                                <span className="text-right">Precio</span><span className="text-right">Desc./und</span>
                                <span className="text-right">Subtotal</span><span />
                            </div>
                            {lineas.map((l) => {
                                const prod = productosById.get(l.producto_id);
                                const cantidad = parseFloat(l.cantidad) || 0;
                                const precio   = parseFloat(l.precio_unitario) || 0;
                                const desc     = parseFloat(l.descuento_item) || 0;
                                const subtotal = Math.round((precio - desc) * cantidad * 100) / 100;
                                const inputCls = 'w-full text-right rounded-lg px-2 py-1.5 border outline-none text-sm';
                                const inputStyle = { borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' } as const;
                                return (
                                    <div key={l.key}
                                        className="grid grid-cols-2 md:grid-cols-[1fr_130px_80px_100px_100px_90px_32px] gap-2 px-3 py-2 items-center"
                                        style={{ borderBottom: '1px solid var(--color-border)' }}>
                                        <div className="col-span-2 md:col-span-1">
                                            <SearchableSelect
                                                options={productos.map(p => ({ value: String(p.id), label: p.codigo ? `${p.nombre} (${p.codigo})` : p.nombre }))}
                                                value={l.producto_id}
                                                onChange={v => elegirProducto(l.key, String(v))}
                                                placeholder="— Producto —"
                                            />
                                        </div>
                                        <Select
                                            options={(prod?.unidades ?? []).map(u => ({ value: String(u.id), label: u.nombre || 'Unidad' }))}
                                            value={l.producto_unidad_id}
                                            onChange={v => elegirUnidad(l.key, l, String(v))}
                                            placeholder="—"
                                        />
                                        <input type="number" min={0.0001} step="any" value={l.cantidad} title="Cantidad"
                                            onChange={e => setLinea(l.key, { cantidad: e.target.value })}
                                            className={inputCls} style={inputStyle} />
                                        <input type="number" min={0} step="0.01" value={l.precio_unitario} title="Precio unitario cotizado"
                                            onChange={e => setLinea(l.key, { precio_unitario: e.target.value })}
                                            className={inputCls} style={inputStyle} />
                                        <input type="number" min={0} step="0.01" value={l.descuento_item} placeholder="0.00" title="Descuento por unidad"
                                            onChange={e => setLinea(l.key, { descuento_item: e.target.value })}
                                            className={inputCls} style={inputStyle} />
                                        <span className="text-right text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                            {money(subtotal)}
                                        </span>
                                        <button onClick={() => setLineas(prev => prev.length > 1 ? prev.filter(x => x.key !== l.key) : prev)}
                                            className="p-1.5 rounded-lg hover:bg-black/5 justify-self-end" title="Quitar línea"
                                            disabled={lineas.length <= 1}
                                            style={{ color: lineas.length <= 1 ? 'var(--color-text-muted)' : 'var(--color-danger)', opacity: lineas.length <= 1 ? 0.4 : 1 }}>
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                );
                            })}
                            <div className="px-3 py-2" style={{ backgroundColor: 'var(--color-bg)' }}>
                                <Button variant="ghost" onClick={() => setLineas(prev => [...prev, lineaVacia()])}>
                                    <Plus size={14} className="mr-1" />Agregar línea
                                </Button>
                            </div>
                        </div>
                        {errors.items && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.items}</p>}
                        {Object.entries(errors).filter(([k]) => k.startsWith('items.')).map(([k, v]) => (
                            <p key={k} className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{v}</p>
                        ))}
                    </div>

                    <Input label="Observación" value={form.observacion}
                        onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))} error={errors.observacion}
                        hint="Condiciones, forma de pago, validez de la oferta, etc. (aparece en la cotización)" />
                </div>
            </Modal>

            {/* ── Modal registrar contacto ─────────────────────────────── */}
            <Modal isOpen={contactando !== null} onClose={() => setContactando(null)}
                title={contactando ? `Registrar contacto — ${contactando.numero}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setContactando(null)}>Cancelar</Button>
                        <Button onClick={submitContacto} disabled={saving}>{saving ? 'Guardando...' : 'Registrar'}</Button>
                    </>
                }
            >
                {contactando && (
                    <div className="space-y-4">
                        {contactando.cliente?.telefono && (
                            <Callout variant="info">
                                <span className="flex items-center gap-2 flex-wrap">
                                    Teléfono de {nombreCliente(contactando.cliente)}:
                                    <a href={`tel:${contactando.cliente.telefono}`} className="font-semibold hover:underline"
                                        style={{ color: 'var(--color-primary)' }}>{contactando.cliente.telefono}</a>
                                    <a href={waLink(contactando.cliente.telefono)} target="_blank" rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 font-semibold hover:opacity-80"
                                        style={{ color: 'var(--color-success)' }}>
                                        <MessageCircle size={13} />WhatsApp
                                    </a>
                                </span>
                            </Callout>
                        )}
                        <Input label="Fecha del contacto" required type="date" value={formContacto.fecha}
                            onChange={e => setFormContacto(f => ({ ...f, fecha: e.target.value }))} error={errors.fecha} />
                        <Input label="Nota" required value={formContacto.nota}
                            onChange={e => setFormContacto(f => ({ ...f, nota: e.target.value }))} error={errors.nota}
                            hint='Ej: "Lo llamé, revisa la propuesta el lunes"' />
                    </div>
                )}
            </Modal>

            {/* ── Modal cambiar estado (aceptar / rechazar / anular) ───── */}
            <Modal isOpen={cambiando !== null} onClose={() => setCambiando(null)}
                title={cambiando ? `${TITULO_ESTADO[cambiando.estado]} — ${cambiando.cot.numero}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCambiando(null)}>Cancelar</Button>
                        <Button variant={cambiando?.estado === 'aceptada' ? 'primary' : 'danger'} onClick={submitEstado} disabled={saving}>
                            {saving ? 'Guardando...' : 'Confirmar'}
                        </Button>
                    </>
                }
            >
                {cambiando && (
                    <div className="space-y-4">
                        {cambiando.estado === 'aceptada' && (
                            <Callout variant="info">
                                El cliente aceptó la cotización. Cuando venga a pagar, usa
                                «Convertir en venta» para cobrarla en el POS con los precios cotizados.
                            </Callout>
                        )}
                        {errors.estado && <Callout variant="danger">{errors.estado}</Callout>}
                        <Input label={cambiando.estado === 'aceptada' ? 'Nota (opcional)' : 'Motivo'}
                            required={cambiando.estado !== 'aceptada'} value={motivo}
                            onChange={e => setMotivo(e.target.value)} error={errors.motivo} />
                    </div>
                )}
            </Modal>

            {/* ── Modal detalle (solo lectura) ──────────────────────────── */}
            <Modal isOpen={detalle !== null} onClose={() => setDetalle(null)}
                title={detalle ? `Cotización ${detalle.numero} — ${nombreCliente(detalle.cliente)}` : ''} size="2xl"
                footer={detalle ? (
                    <>
                        <Button variant="ghost" onClick={() => imprimirTicketCot(detalle)}>
                            <Printer size={15} className="mr-1.5" />Imprimir ticket
                        </Button>
                        <Button variant="ghost" onClick={() => verProforma(detalle)}>
                            <FileDown size={15} className="mr-1.5" />Proforma PDF
                        </Button>
                        <Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>
                    </>
                ) : <Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>}
            >
                {detalle && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={ESTADO_BADGE[detalle.estado].variant}>{ESTADO_BADGE[detalle.estado].label}</Badge>
                            {detalle.referencia && <Badge variant="secondary">{detalle.referencia}</Badge>}
                            {detalle.venta?.numero && <Badge variant="success">Venta {detalle.venta.numero}</Badge>}
                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Emitida {fmtFecha(detalle.fecha)}
                                {detalle.fecha_vencimiento ? ` · vence ${fmtFecha(detalle.fecha_vencimiento)}` : ''}
                                {detalle.fecha_entrega_estimada ? ` · entrega est. ${fmtFecha(detalle.fecha_entrega_estimada)}` : ''}
                                {detalle.user?.name ? ` · por ${detalle.user.name}` : ''}
                            </span>
                        </div>

                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {detalle.items.map((it, idx) => (
                                <div key={it.id} className="flex items-center justify-between gap-2 px-3 py-2 text-xs"
                                    style={{
                                        borderBottom: idx < detalle.items.length - 1 ? '1px solid var(--color-border)' : undefined,
                                        backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                    }}>
                                    <span className="font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                        {it.producto_nombre}{' '}
                                        <span style={{ color: 'var(--color-text-muted)' }}>
                                            × {Number(it.cantidad)} {it.unidad_nombre || 'und'} a {money(it.precio_unitario)}
                                            {Number(it.descuento_item) > 0 ? ` (− ${money(it.descuento_item)}/und)` : ''}
                                        </span>
                                    </span>
                                    <span className="font-semibold flex-shrink-0">{money(it.subtotal)}</span>
                                </div>
                            ))}
                            <div className="px-3 py-2 text-xs flex flex-wrap justify-end gap-x-4 gap-y-1"
                                style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                                <span>Subtotal {money(detalle.subtotal)}</span>
                                {Number(detalle.descuento_total) > 0 && <span>Descuentos − {money(detalle.descuento_total)}</span>}
                                <span>IGV incluido {money(detalle.igv)}</span>
                                <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>Total {money(detalle.total)}</span>
                            </div>
                        </div>

                        {detalle.observacion && (
                            <Callout variant="info" title="Observación">{detalle.observacion}</Callout>
                        )}

                        {detalle.notas_seguimiento && (
                            <div>
                                <p className="text-xs font-semibold mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Seguimiento{detalle.ultimo_contacto ? ` · último contacto ${fmtFecha(detalle.ultimo_contacto)}` : ''}
                                </p>
                                <pre className="text-xs whitespace-pre-wrap rounded-xl px-3 py-2 font-sans"
                                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}>
                                    {detalle.notas_seguimiento}
                                </pre>
                            </div>
                        )}

                        {(detalle.estado === 'vigente' || detalle.estado === 'aceptada') && (
                            <div className="flex justify-end">
                                <Button onClick={() => convertirEnVenta(detalle)}>
                                    <ShoppingCart size={15} className="mr-1.5" />Convertir en venta
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
