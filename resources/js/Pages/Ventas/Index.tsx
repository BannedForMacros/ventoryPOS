import { useEffect, useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import {
    Eye, ShoppingCart, Calendar, Receipt, Pencil, Trash2, KeyRound,
    AlertTriangle, Search, Printer, Wallet, CreditCard, TrendingDown, Coins, Clock, HandCoins, ListChecks, PackageMinus,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import { imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
import type { Local, PageProps, Venta } from '@/types';

// Ventana de edición (debe coincidir con VentaController::EDIT_WINDOW_SECONDS).
const EDIT_WINDOW_MS = 3 * 60 * 1000;

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Filters {
    estado?:      string;
    fecha_desde?: string;
    fecha_hasta?: string;
    local_id?:    string;
    turno_id?:    string;
    q?:           string;
}

interface TurnoLite {
    id:             number;
    user_id:        number;
    caja_id:        number;
    local_id:       number;
    fecha_apertura: string;
    estado:         'abierto' | 'cerrado';
    user?:          { id: number; name: string } | null;
    caja?:          { id: number; nombre: string } | null;
}

interface ResumenMetodoCuenta {
    cuenta: string | null;
    banco:  string | null;
    total:  number;
}

interface ResumenMetodo {
    metodo:      string;
    slug:        string | null;
    es_efectivo: boolean;
    total:       number;
    cuentas:     ResumenMetodoCuenta[];
}

interface Resumen {
    turno:            { id: number; fecha: string; cajera: string | null; caja: string | null; estado: string };
    metodos:          ResumenMetodo[];
    cobrado:          number;   // suma de métodos de pago
    total_efectivo:   number;
    efectivo_ventas:  number;   // efectivo de ventas del turno
    efectivo_abonos:  number;   // efectivo de abonos de crédito del turno
    anticipos_efectivo: number; // anticipos de clientes en efectivo (entran a caja)
    total_vendido:    number;   // cobrado + por cobrar
    por_cobrar:       number;   // crédito pendiente del turno
    abonos:           number;   // cobros de crédito recibidos en el turno
    gastos:           number;
    reembolsos:       number;   // devoluciones en efectivo
    compras:          number;   // pagos de entradas en efectivo (salen de caja)
    deuda_ingreso:    number;   // desembolsos/cobros de deuda en efectivo (entran)
    deuda_egreso:     number;   // cuotas/préstamos de deuda en efectivo (salen)
    apertura:         number;
    efectivo_en_caja: number;
    abonos_detalle:   { cliente: string; venta: string | null; metodo: string; monto: number }[];
    anticipos_detalle:{ cliente: string; metodo: string; monto: number }[];
    gastos_detalle:   { concepto: string; monto: number }[];
    compras_detalle:  { proveedor: string; documento: string | null; monto: number }[];
    deuda_detalle:    { descripcion: string; tipo: string; monto: number }[];
}

interface Props extends PageProps {
    ventas:  Paginado<Venta>;
    locales: Local[];
    turnos:  TurnoLite[];
    resumen: Resumen | null;
    filters: Filters;
}

const money = (v: number) => `S/ ${Number(v ?? 0).toFixed(2)}`;

export default function VentasIndex({ ventas, locales, turnos, resumen, filters, flash }: Props) {
    const { auth } = usePage<Props>().props;
    const esAdmin  = auth.user.rol?.es_admin ?? false;

    // Reloj para que el gate de 3 min se actualice en vivo (edición desaparece).
    const [ahora, setAhora] = useState(() => Date.now());
    useEffect(() => {
        const t = setInterval(() => setAhora(Date.now()), 15000);
        return () => clearInterval(t);
    }, []);

    // ── Filtros: estado LOCAL. No se dispara ninguna consulta hasta pulsar
    // «Aplicar» (o Enter en la búsqueda). Antes filtraba en cada tecla/cambio. ──
    const [local, setLocal] = useState<Filters>({
        estado:      filters.estado ?? '',
        fecha_desde: filters.fecha_desde ?? '',
        fecha_hasta: filters.fecha_hasta ?? '',
        local_id:    filters.local_id ?? '',
        turno_id:    filters.turno_id ?? '',
        q:           filters.q ?? '',
    });
    function set<K extends keyof Filters>(k: K, v: string) {
        setLocal(prev => ({ ...prev, [k]: v }));
    }

    // Estado del modal de anulación.
    const [anular, setAnular]   = useState<Venta | null>(null);
    const [motivo, setMotivo]   = useState('');
    const [codigo, setCodigo]   = useState('');
    const [saving, setSaving]   = useState(false);
    const [errAnular, setErrAnular] = useState<Record<string, string>>({});
    const [imprimiendo, setImprimiendo] = useState<number | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // ¿La venta sigue dentro del plazo de 3 min desde su creación?
    function dentroPlazo(v: Venta): boolean {
        return ahora - new Date(v.created_at).getTime() < EDIT_WINDOW_MS;
    }
    // El admin edita/anula sin límite; la cajera solo dentro de los 3 min.
    function puedeEditar(v: Venta): boolean {
        return v.estado === 'completada' && (esAdmin || dentroPlazo(v));
    }
    // La cajera necesita código de admin para anular pasado el plazo.
    function requiereCodigo(v: Venta): boolean {
        return !esAdmin && !dentroPlazo(v);
    }

    function abrirAnular(v: Venta) {
        setAnular(v);
        setMotivo('');
        setCodigo('');
        setErrAnular({});
    }

    function confirmarAnular() {
        if (!anular) return;
        setSaving(true);
        setErrAnular({});
        router.post(route('ventas.anular', anular.id), {
            motivo,
            codigo_autorizacion: requiereCodigo(anular) ? codigo : undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSaving(false); setAnular(null); },
            onError:   (errs) => { setSaving(false); setErrAnular(errs as Record<string, string>); },
        });
    }

    // ── Imprimir desde la lista: pide el payload al backend y lo manda al agente. ──
    async function imprimir(v: Venta) {
        setImprimiendo(v.id);
        const tid = toast.loading(`Enviando ticket ${v.numero}...`);
        try {
            const { data } = await axios.get<TicketPayload>(route('ventas.ticket', v.id));
            if (!data?.token) {
                toast.error('Esta caja no tiene ticketera configurada.', { id: tid });
                return;
            }
            const ok = await imprimirTicket(data);
            if (ok) toast.success(`Ticket ${v.numero} enviado`, { id: tid });
            else    toast.error('No se pudo imprimir. Revisa VentoryPrint en esta PC.', { id: tid });
        } catch {
            toast.error('No se pudo obtener el ticket.', { id: tid });
        } finally {
            setImprimiendo(null);
        }
    }

    // ── Aplicar / limpiar filtros ───────────────────────────────────────
    function aplicar() {
        const params = Object.fromEntries(
            Object.entries(local).filter(([, v]) => v !== '' && v != null),
        );
        router.get(route('ventas.index'), params, { preserveState: true, replace: true, preserveScroll: true });
    }
    function limpiar() {
        setLocal({ estado: '', fecha_desde: '', fecha_hasta: '', local_id: '', turno_id: '', q: '' });
        router.get(route('ventas.index'), {}, { preserveState: true, replace: true });
    }

    const tienesFiltros = !!(filters.estado || filters.local_id || filters.turno_id || filters.q
        || filters.fecha_desde || filters.fecha_hasta);

    function clienteNombre(v: Venta) {
        if (!v.cliente) return 'General';
        const c = v.cliente as any;
        return c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim();
    }

    // Venta al crédito con saldo pendiente (cuenta por cobrar).
    const saldo = (v: Venta) => Number((v as any).saldo_pendiente ?? 0);
    const esCredito = (v: Venta) => Boolean((v as any).es_credito) && saldo(v) > 0;

    // Con qué pagó: nombres únicos de los métodos de los pagos de la venta.
    function metodosDePago(v: Venta): string[] {
        const pagos = ((v.pagos ?? []) as { metodo_pago?: { nombre?: string } | null }[]);
        return [...new Set(pagos.map(p => p.metodo_pago?.nombre).filter((n): n is string => !!n))];
    }

    function turnoLabel(t: TurnoLite): string {
        const f = new Date(t.fecha_apertura).toLocaleDateString('es-PE');
        const partes = [`#${t.id}`, f, t.user?.name, t.caja?.nombre].filter(Boolean);
        return partes.join(' · ') + (t.estado === 'abierto' ? ' · abierto' : '');
    }

    const inputCls = 'w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2';
    const inputStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    return (
        <AppLayout title="Ventas">
            <PageHeader
                title="Historial de ventas"
                subtitle={`${ventas.total} ventas registradas`}
                actions={
                    <Link href={route('pos.index')}>
                        <Button variant="primary" startContent={<ShoppingCart size={16} />}>
                            Ir al POS
                        </Button>
                    </Link>
                }
            />

            {/* ── Cards de resumen del turno ───────────────────────────── */}
            {resumen && (
                <ResumenCards resumen={resumen} />
            )}

            {/* ── Filtros (aplican con el botón o Enter, no al tipear) ── */}
            <FiltrosCard
                cols={4}
                tieneFiltros={tienesFiltros}
                onClear={limpiar}
                actions={
                    <Button variant="primary" size="sm" onClick={aplicar} startContent={<Search size={13} />}>
                        Aplicar filtros
                    </Button>
                }
            >
                <div className="col-span-2">
                    <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Buscar</label>
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input
                            value={local.q ?? ''}
                            onChange={e => set('q', e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter') aplicar(); }}
                            placeholder="N° de venta, cliente, documento o monto..."
                            className="w-full text-sm border rounded-lg pl-9 pr-3 py-2 focus:outline-none focus:ring-2"
                            style={inputStyle}
                        />
                    </div>
                </div>

                <div>
                    <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Estado</label>
                    <select value={local.estado ?? ''} onChange={e => set('estado', e.target.value)} className={inputCls} style={inputStyle}>
                        <option value="">Todos</option>
                        <option value="completada">Completadas</option>
                        <option value="anulada">Anuladas</option>
                    </select>
                </div>

                <div>
                    <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Desde</label>
                    <input type="date" value={local.fecha_desde ?? ''} onChange={e => set('fecha_desde', e.target.value)} className={inputCls} style={inputStyle} />
                </div>

                <div>
                    <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Hasta</label>
                    <input type="date" value={local.fecha_hasta ?? ''} onChange={e => set('fecha_hasta', e.target.value)} className={inputCls} style={inputStyle} />
                </div>

                {/* Filtro por turno: clave para el admin (ya no ve todo mezclado). */}
                {esAdmin && turnos.length > 0 && (
                    <div>
                        <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Turno</label>
                        <select value={local.turno_id ?? ''} onChange={e => set('turno_id', e.target.value)} className={inputCls} style={inputStyle}>
                            <option value="">Todos los turnos</option>
                            {turnos.map(t => <option key={t.id} value={t.id}>{turnoLabel(t)}</option>)}
                        </select>
                    </div>
                )}

                {esAdmin && locales.length > 1 && (
                    <div>
                        <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Local</label>
                        <select value={local.local_id ?? ''} onChange={e => set('local_id', e.target.value)} className={inputCls} style={inputStyle}>
                            <option value="">Todos los locales</option>
                            {locales.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                        </select>
                    </div>
                )}
            </FiltrosCard>

            {/* ── Tabla desktop ─────────────────────────────────────── */}
            <div
                className="hidden md:block rounded-xl overflow-hidden"
                style={{ border: '1px solid var(--color-border)' }}
            >
                <table className="w-full text-sm" style={{ backgroundColor: 'var(--color-surface)' }}>
                    <thead>
                        <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                            {['N°', 'Fecha', 'Cliente', 'Comprobante', 'Pago', 'Estado', 'Total', ''].map(h => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {ventas.data.map((v, idx) => (
                            <tr
                                key={v.id}
                                className="transition-colors hover:bg-black/[0.02]"
                                style={{ borderBottom: idx < ventas.data.length - 1 ? '1px solid var(--color-border)' : undefined }}
                            >
                                <td className="px-4 py-3">
                                    <span className="font-mono font-bold text-xs px-2 py-1 rounded-md"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}>
                                        {v.numero}
                                    </span>
                                </td>
                                <td className="px-4 py-3" style={{ color: 'var(--color-text)' }}>
                                    <div className="text-xs">
                                        {new Date(v.fecha_venta).toLocaleDateString('es-PE')}
                                    </div>
                                    <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                                        {new Date(v.fecha_venta).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                    </div>
                                </td>
                                <td className="px-4 py-3 max-w-[180px]">
                                    <span className="truncate block text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                        {clienteNombre(v)}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="capitalize text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        {v.tipo_comprobante}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <ChipsPago metodos={metodosDePago(v)} esCredito={Boolean(v.es_credito)} debe={saldo(v)} />
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                                        {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                                        S/ {parseFloat(v.total).toFixed(2)}
                                    </span>
                                    {esCredito(v) && (
                                        <div className="mt-1">
                                            <Badge variant="warning">
                                                Por cobrar S/ {saldo(v).toFixed(2)}
                                            </Badge>
                                        </div>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route('ventas.show', v.id)}
                                            title="Ver detalle"
                                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}
                                        >
                                            <Eye size={14} />
                                        </Link>
                                        {v.estado === 'completada' && (
                                            <button
                                                onClick={() => imprimir(v)}
                                                disabled={imprimiendo === v.id}
                                                title="Imprimir ticket"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80 disabled:opacity-40"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-text-muted) 12%, transparent)', color: 'var(--color-text)' }}
                                            >
                                                <Printer size={14} />
                                            </button>
                                        )}
                                        {puedeEditar(v) && (
                                            <Link
                                                href={route('pos.index', { venta_id: v.id })}
                                                title="Editar venta"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 12%, transparent)', color: 'var(--color-warning)' }}
                                            >
                                                <Pencil size={14} />
                                            </Link>
                                        )}
                                        {v.estado === 'completada' && (
                                            <button
                                                onClick={() => abrirAnular(v)}
                                                title={requiereCodigo(v) ? 'Anular (requiere código de admin)' : 'Anular venta'}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {ventas.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    <Receipt size={40} className="mx-auto mb-3 opacity-20" />
                                    <p className="text-sm">No se encontraron ventas</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* ── Cards móvil ──────────────────────────────────────── */}
            <div className="md:hidden flex flex-col gap-2">
                {ventas.data.map(v => (
                    <div
                        key={v.id}
                        className="rounded-xl p-3"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            border: '1px solid var(--color-border)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                        }}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <span className="font-mono font-bold text-xs px-1.5 py-0.5 rounded"
                                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}>
                                        {v.numero}
                                    </span>
                                    <Badge variant={v.estado === 'completada' ? 'success' : 'danger'}>
                                        {v.estado === 'completada' ? 'Completada' : 'Anulada'}
                                    </Badge>
                                </div>
                                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                    {clienteNombre(v)}
                                </p>
                                <div className="flex items-center gap-3 mt-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="flex items-center gap-1">
                                        <Calendar size={11} />
                                        {new Date(v.fecha_venta).toLocaleDateString('es-PE')}
                                    </span>
                                    <span className="capitalize">{v.tipo_comprobante}</span>
                                </div>
                                <div className="mt-1.5">
                                    <ChipsPago metodos={metodosDePago(v)} esCredito={Boolean(v.es_credito)} debe={saldo(v)} />
                                </div>
                            </div>
                            <div className="text-right flex-shrink-0">
                                <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>
                                    S/ {parseFloat(v.total).toFixed(2)}
                                </p>
                                {esCredito(v) && (
                                    <div className="mt-1 flex justify-end">
                                        <Badge variant="warning">Por cobrar S/ {saldo(v).toFixed(2)}</Badge>
                                    </div>
                                )}
                                <p className="text-[10px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    {new Date(v.fecha_venta).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                </p>
                            </div>
                        </div>

                        {/* Acciones */}
                        <div className="flex flex-wrap items-center gap-2 mt-3 pt-3" style={{ borderTop: '1px solid var(--color-border)' }}>
                            <Link
                                href={route('ventas.show', v.id)}
                                className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-primary)' }}
                            >
                                <Eye size={14} /> Ver
                            </Link>
                            {v.estado === 'completada' && (
                                <button
                                    onClick={() => imprimir(v)}
                                    disabled={imprimiendo === v.id}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg disabled:opacity-40"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-text-muted) 12%, transparent)', color: 'var(--color-text)' }}
                                >
                                    <Printer size={14} /> Imprimir
                                </button>
                            )}
                            {puedeEditar(v) && (
                                <Link
                                    href={route('pos.index', { venta_id: v.id })}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 12%, transparent)', color: 'var(--color-warning)' }}
                                >
                                    <Pencil size={14} /> Editar
                                </Link>
                            )}
                            {v.estado === 'completada' && (
                                <button
                                    onClick={() => abrirAnular(v)}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                                >
                                    <Trash2 size={14} /> Anular
                                </button>
                            )}
                        </div>
                    </div>
                ))}
                {ventas.data.length === 0 && (
                    <div className="text-center py-16" style={{ color: 'var(--color-text-muted)' }}>
                        <Receipt size={40} className="mx-auto mb-3 opacity-20" />
                        <p className="text-sm">No se encontraron ventas</p>
                    </div>
                )}
            </div>

            {/* ── Paginación (con elipsis: no revienta con muchas páginas) ── */}
            {ventas.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {paginasVisibles(ventas.current_page, ventas.last_page).map((page, i) =>
                        page === '…' ? (
                            <span key={`e-${i}`} className="px-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>…</span>
                        ) : (
                            <button
                                key={page}
                                onClick={() => router.get(route('ventas.index'), { ...filters, page }, { preserveState: true, preserveScroll: true })}
                                className="min-w-8 h-8 px-1.5 rounded-lg text-xs font-semibold transition-colors"
                                style={{
                                    backgroundColor: page === ventas.current_page
                                        ? 'var(--color-primary)'
                                        : 'color-mix(in srgb, var(--color-primary) 6%, transparent)',
                                    color: page === ventas.current_page ? '#fff' : 'var(--color-text-muted)',
                                }}
                            >
                                {page}
                            </button>
                        )
                    )}
                </div>
            )}

            {/* ── Modal anular ─────────────────────────────────────── */}
            <Modal
                isOpen={anular !== null}
                onClose={() => setAnular(null)}
                title={`Anular venta ${anular?.numero ?? ''}`}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnular(null)} disabled={saving}>Cancelar</Button>
                        <Button variant="danger" onClick={confirmarAnular} loading={saving}>Anular venta</Button>
                    </>
                }
            >
                {anular && (
                    <div className="space-y-3">
                        <div className="flex items-start gap-2 rounded-lg px-3 py-2 text-sm"
                            style={{ backgroundColor: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)' }}>
                            <AlertTriangle size={16} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-danger)' }} />
                            <p style={{ color: 'var(--color-text)' }}>
                                Anular revierte el stock y el dinero de esta venta. Es una acción irreversible.
                            </p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                Motivo de la anulación <span style={{ color: 'var(--color-danger)' }}>*</span>
                            </label>
                            <textarea
                                rows={2}
                                value={motivo}
                                onChange={e => setMotivo(e.target.value)}
                                disabled={saving}
                                placeholder="Describe por qué se anula (mín. 10 caracteres)"
                                className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            />
                            {errAnular.motivo && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errAnular.motivo}</p>}
                        </div>

                        {/* Código de admin: solo cuando la cajera anula fuera del plazo de 3 min */}
                        {requiereCodigo(anular) && (
                            <div>
                                <label className="flex items-center gap-1.5 text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                    <KeyRound size={14} style={{ color: 'var(--color-warning)' }} />
                                    Código de autorización
                                </label>
                                <p className="text-xs mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Pasaron más de 3 minutos. Pide a un administrador su código de autorización para anular.
                                    <span className="italic"> (Envío por WhatsApp: pendiente de implementar; por ahora es la clave de un admin.)</span>
                                </p>
                                <input
                                    type="password"
                                    value={codigo}
                                    onChange={e => setCodigo(e.target.value)}
                                    disabled={saving}
                                    placeholder="Código / clave de administrador"
                                    className="w-full rounded-xl px-3 py-2 text-sm"
                                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                                />
                                {errAnular.codigo_autorizacion && (
                                    <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errAnular.codigo_autorizacion}</p>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

// ── Helpers ─────────────────────────────────────────────────────────────────

/** Páginas a mostrar con elipsis: 1 … (actual−1, actual, actual+1) … última. */
function paginasVisibles(actual: number, ultima: number): (number | '…')[] {
    const pages: (number | '…')[] = [];
    for (let p = 1; p <= ultima; p++) {
        if (p === 1 || p === ultima || Math.abs(p - actual) <= 1) pages.push(p);
        else if (pages[pages.length - 1] !== '…') pages.push('…');
    }
    return pages;
}

/** Chips "con qué pagó": métodos de pago + crédito (con deuda si queda saldo). */
function ChipsPago({ metodos, esCredito, debe }: { metodos: string[]; esCredito: boolean; debe: number }) {
    if (metodos.length === 0 && !esCredito) {
        return <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>—</span>;
    }
    return (
        <div className="flex flex-wrap items-center gap-1">
            {metodos.map(m => (
                <span key={m}
                    className="text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap"
                    style={{
                        color: 'var(--color-primary)',
                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                    }}>
                    {m}
                </span>
            ))}
            {esCredito && (
                <span
                    className="text-[10px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap"
                    style={{
                        color: '#b45309',
                        backgroundColor: 'rgba(234,179,8,0.14)',
                    }}>
                    Crédito{debe > 0 ? ` · debe ${money(debe)}` : ''}
                </span>
            )}
        </div>
    );
}

// ── Cards de resumen del turno ──────────────────────────────────────────────

const subCuenta = (m: ResumenMetodo) =>
    m.cuentas.length === 1
        ? (m.cuentas[0].cuenta ?? 'Cuenta no especificada al cobrar')
        : `${m.cuentas.length} cuentas`;

function ResumenCards({ resumen }: { resumen: Resumen }) {
    const [detalle, setDetalle] = useState(false);
    const fecha = new Date(resumen.turno.fecha).toLocaleDateString('es-PE');
    // Solo métodos no-efectivo como cards individuales; el efectivo va en su card grande.
    const metodosNoEfectivo = resumen.metodos.filter(m => !m.es_efectivo);

    // Detalle del efectivo en caja: apertura + ventas (+ abonos) − gastos.
    const efectivoSub = [
        `Apertura ${money(resumen.apertura)}`,
        `ventas ${money(resumen.efectivo_ventas)}`,
        resumen.efectivo_abonos > 0 ? `abonos ${money(resumen.efectivo_abonos)}` : null,
        resumen.anticipos_efectivo > 0 ? `anticipos ${money(resumen.anticipos_efectivo)}` : null,
        resumen.gastos > 0 ? `− gastos ${money(resumen.gastos)}` : null,
        resumen.compras > 0 ? `− compras ${money(resumen.compras)}` : null,
        resumen.deuda_ingreso > 0 ? `deuda ${money(resumen.deuda_ingreso)}` : null,
        resumen.deuda_egreso > 0 ? `− deuda ${money(resumen.deuda_egreso)}` : null,
    ].filter(Boolean).join(' · ');

    return (
        <div className="mb-5">
            <div className="flex items-center gap-2 mb-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                <Clock size={13} />
                <span className="font-semibold uppercase tracking-wide">
                    {resumen.turno.id === 0 ? 'Caja — todas las abiertas' : `Caja del turno #${resumen.turno.id}`}
                </span>
                <span className="hidden sm:inline">
                    · {fecha}{resumen.turno.cajera ? ` · ${resumen.turno.cajera}` : ''}{resumen.turno.caja ? ` · ${resumen.turno.caja}` : ''}
                </span>
                <Badge variant={resumen.turno.estado === 'abierto' ? 'success' : 'secondary'}>
                    {resumen.turno.estado === 'abierto' ? 'Abierto' : 'Cerrado'}
                </Badge>
                <button onClick={() => setDetalle(true)}
                    className="ml-auto inline-flex items-center gap-1 font-semibold hover:underline"
                    style={{ color: 'var(--color-primary)' }}>
                    <ListChecks size={13} /> Ver detalle
                </button>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                {/* Efectivo en caja — destacado y clickable para ver el desglose */}
                <Card
                    destacado
                    icon={<Wallet size={18} />}
                    label="Efectivo en caja"
                    valor={money(resumen.efectivo_en_caja)}
                    sub={efectivoSub}
                    color="primary"
                    onClick={() => setDetalle(true)}
                />

                {/* Un card por MÉTODO (Yape, Transferencia...); cuentas en el detalle */}
                {metodosNoEfectivo.map((m, i) => (
                    <Card
                        key={`${m.metodo}-${i}`}
                        icon={<CreditCard size={18} />}
                        label={m.metodo}
                        valor={money(m.total)}
                        sub={subCuenta(m)}
                        onClick={() => setDetalle(true)}
                    />
                ))}

                <Card
                    icon={<Clock size={18} />}
                    label="Cuentas por cobrar"
                    valor={money(resumen.por_cobrar)}
                    sub="Ventas al crédito (pendiente)"
                    color="warning"
                />

                <Card
                    icon={<HandCoins size={18} />}
                    label="Abonos recibidos"
                    valor={money(resumen.abonos)}
                    sub="Cobros de crédito en este turno"
                    color="success"
                    onClick={() => setDetalle(true)}
                />

                <Card
                    icon={<TrendingDown size={18} />}
                    label="Gastos del turno"
                    valor={money(resumen.gastos)}
                    color="danger"
                    onClick={() => setDetalle(true)}
                />

                {resumen.compras > 0 && (
                    <Card
                        icon={<PackageMinus size={18} />}
                        label="Compras (efectivo)"
                        valor={money(resumen.compras)}
                        sub="Pagadas desde la caja"
                        color="danger"
                        onClick={() => setDetalle(true)}
                    />
                )}

                <Card
                    icon={<Coins size={18} />}
                    label="Total vendido"
                    valor={money(resumen.total_vendido)}
                    sub={`Cobrado ${money(resumen.cobrado)} + por cobrar ${money(resumen.por_cobrar)}`}
                />
            </div>

            <DetalleCajaModal open={detalle} onClose={() => setDetalle(false)} resumen={resumen} />
        </div>
    );
}

function Card({ icon, label, valor, sub, color, destacado, onClick }: {
    icon: React.ReactNode;
    label: string;
    valor: string;
    sub?: string;
    color?: 'primary' | 'danger' | 'warning' | 'success';
    destacado?: boolean;
    onClick?: () => void;
}) {
    const accent = color === 'danger' ? 'var(--color-danger)'
        : color === 'warning' ? 'var(--color-warning)'
        : color === 'success' ? 'var(--color-success)'
        : color === 'primary' ? 'var(--color-primary)'
        : 'var(--color-text-muted)';
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={!onClick}
            className={`text-left rounded-xl p-3.5 flex flex-col gap-1.5 transition-shadow ${onClick ? 'hover:shadow-md cursor-pointer' : 'cursor-default'}`}
            style={{
                backgroundColor: destacado ? 'color-mix(in srgb, var(--color-primary) 6%, var(--color-surface))' : 'var(--color-surface)',
                border: destacado ? '1px solid color-mix(in srgb, var(--color-primary) 35%, transparent)' : '1px solid var(--color-border)',
            }}
        >
            <div className="flex items-center gap-1.5" style={{ color: accent }}>
                {icon}
                <span className="text-[11px] font-semibold uppercase tracking-wide truncate">{label}</span>
            </div>
            <span className="text-lg font-bold leading-none" style={{ color: 'var(--color-text)' }}>{valor}</span>
            {/* Sin truncate: en un reporte no puede aparecer "..." cortando montos. */}
            {sub && <span className="text-[10px] leading-tight" style={{ color: 'var(--color-text-muted)' }}>{sub}</span>}
        </button>
    );
}

// ── Modal: detalle de caja del turno (para corroborar) ──────────────────────
function DetalleCajaModal({ open, onClose, resumen }: { open: boolean; onClose: () => void; resumen: Resumen }) {
    const fila = (etiqueta: string, valor: number, signo: '+' | '−' | '=' = '+') => (
        <div className="flex items-center justify-between py-1.5 text-sm">
            <span style={{ color: 'var(--color-text-muted)' }}>{signo !== '=' && <span className="mr-1">{signo}</span>}{etiqueta}</span>
            <span className={`font-mono ${signo === '=' ? 'font-bold' : ''}`} style={{ color: 'var(--color-text)' }}>{money(valor)}</span>
        </div>
    );

    return (
        <Modal isOpen={open} onClose={onClose} size="2xl"
            title={resumen.turno.id === 0 ? 'Detalle de caja — todas las abiertas' : `Detalle de caja — turno #${resumen.turno.id}`}
            footer={<Button variant="ghost" onClick={onClose}>Cerrar</Button>}>
            <div className="space-y-5">
                {/* Reconciliación del efectivo */}
                <section>
                    <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>Efectivo en caja</p>
                    <div className="rounded-xl px-3 py-1" style={{ border: '1px solid var(--color-border)' }}>
                        {fila('Apertura', resumen.apertura)}
                        {fila('Ventas en efectivo', resumen.efectivo_ventas)}
                        {resumen.efectivo_abonos > 0 && fila('Abonos en efectivo', resumen.efectivo_abonos)}
                        {resumen.anticipos_efectivo > 0 && fila('Anticipos en efectivo', resumen.anticipos_efectivo)}
                        {resumen.gastos > 0 && fila('Gastos', resumen.gastos, '−')}
                        {resumen.compras > 0 && fila('Compras pagadas en efectivo', resumen.compras, '−')}
                        {resumen.reembolsos > 0 && fila('Reembolsos (devoluciones)', resumen.reembolsos, '−')}
                        {resumen.deuda_ingreso > 0 && fila('Deuda / préstamo cobrado en efectivo', resumen.deuda_ingreso)}
                        {resumen.deuda_egreso > 0 && fila('Deuda / préstamo pagado en efectivo', resumen.deuda_egreso, '−')}
                        <div className="border-t mt-1 pt-1" style={{ borderColor: 'var(--color-border)' }}>
                            {fila('Efectivo esperado en caja', resumen.efectivo_en_caja, '=')}
                        </div>
                    </div>
                </section>

                {/* Desglose por método y cuenta */}
                <section>
                    <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>Cobros por método y cuenta</p>
                    <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                        {resumen.metodos.map((m, i) => (
                            <div key={i} className="px-3 py-2" style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined }}>
                                <div className="flex items-center justify-between text-sm font-semibold">
                                    <span style={{ color: 'var(--color-text)' }}>{m.metodo}</span>
                                    <span className="font-mono">{money(m.total)}</span>
                                </div>
                                {m.cuentas.map((c, j) => (
                                    <div key={j} className="flex items-center justify-between text-xs mt-0.5 pl-3" style={{ color: 'var(--color-text-muted)' }}>
                                        <span>{c.cuenta ?? 'Cuenta no especificada al cobrar'}{c.banco ? ` · ${c.banco}` : ''}</span>
                                        <span className="font-mono">{money(c.total)}</span>
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                </section>

                {/* Abonos recibidos */}
                {resumen.abonos_detalle.length > 0 && (
                    <section>
                        <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>
                            Abonos de crédito recibidos ({money(resumen.abonos)})
                        </p>
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {resumen.abonos_detalle.map((a, i) => (
                                <div key={i} className="flex items-center justify-between px-3 py-1.5 text-xs" style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined }}>
                                    <span style={{ color: 'var(--color-text)' }}>
                                        {a.cliente}{a.venta ? ` · ${a.venta}` : ''} <span style={{ color: 'var(--color-text-muted)' }}>({a.metodo})</span>
                                    </span>
                                    <span className="font-mono font-semibold">{money(a.monto)}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Anticipos recibidos */}
                {resumen.anticipos_detalle.length > 0 && (
                    <section>
                        <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>
                            Anticipos de clientes recibidos
                        </p>
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {resumen.anticipos_detalle.map((a, i) => (
                                <div key={i} className="flex items-center justify-between px-3 py-1.5 text-xs" style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined }}>
                                    <span style={{ color: 'var(--color-text)' }}>
                                        {a.cliente} <span style={{ color: 'var(--color-text-muted)' }}>({a.metodo})</span>
                                    </span>
                                    <span className="font-mono font-semibold">{money(a.monto)}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Gastos */}
                {resumen.gastos_detalle.length > 0 && (
                    <section>
                        <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>
                            Gastos del turno ({money(resumen.gastos)})
                        </p>
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {resumen.gastos_detalle.map((g, i) => (
                                <div key={i} className="flex items-center justify-between px-3 py-1.5 text-xs" style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined }}>
                                    <span style={{ color: 'var(--color-text)' }}>{g.concepto}</span>
                                    <span className="font-mono" style={{ color: 'var(--color-danger)' }}>− {money(g.monto)}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Compras (entradas) pagadas en efectivo desde la caja */}
                {resumen.compras_detalle.length > 0 && (
                    <section>
                        <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>
                            Compras pagadas en efectivo ({money(resumen.compras)})
                        </p>
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {resumen.compras_detalle.map((c, i) => (
                                <div key={i} className="flex items-center justify-between px-3 py-1.5 text-xs" style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined }}>
                                    <span style={{ color: 'var(--color-text)' }}>{c.proveedor}{c.documento ? ` · ${c.documento}` : ''}</span>
                                    <span className="font-mono" style={{ color: 'var(--color-danger)' }}>− {money(c.monto)}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </Modal>
    );
}
