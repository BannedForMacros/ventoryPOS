import { useEffect, useRef, useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import {
    Eye, ShoppingCart, Calendar, Receipt, Pencil, Trash2, KeyRound,
    AlertTriangle, Search, Printer, Wallet, CreditCard, TrendingDown, Coins, Clock, HandCoins, ListChecks, PackageMinus,
    Send, MessageCircle, Mail, ArrowLeftRight, FileSpreadsheet, RotateCcw, ChevronLeft, ChevronRight, CalendarRange,
} from 'lucide-react';
import { fechaLocal, hoyLocal } from '@/lib/fechas';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import { imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
import {
    rutaComprobante, metaEstado, estadoEnCurso, comprobanteEmitido, comprobanteConProblema,
    etiquetaTipoSunat, etiquetaComprobante, type EstadoComprobanteResp,
} from '@/lib/comprobanteElectronico';
import type { ComprobanteElectronico, Local, PageProps, Venta } from '@/types';
import Select from '@/Components/UI/Select';
import { useTiempoReal } from '@/lib/useTiempoReal';
import Callout from '@/Components/UI/Callout';
import { soles } from '@/lib/dinero';
import ModalCorregirPago from '@/Components/Ventas/ModalCorregirPago';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Filters {
    estado?:      string;
    fecha_desde?: string;
    fecha_hasta?: string;
    local_id?:    string;
    turno_id?:    string;
    q?:           string;
    // Las fechas son el "hoy" que pone el servidor (nadie las eligió).
    fechas_por_defecto?: boolean;
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
    /** Solo en el resumen por fechas: de dónde sale el total. */
    ventas?:       number;
    abonos?:       number;
    devoluciones?: number;
    cuentas:     ResumenMetodoCuenta[];
}

/** Turno abierto desde un día anterior: su caja suma varios días. */
interface TurnoAbiertoAntes { id: number; cajera: string | null; desde: string }

/** Cards cuando se filtra por FECHAS: lo vendido esos días (no la caja de un turno). */
interface ResumenRango {
    modo:          'rango';
    rango:         { desde: string | null; hasta: string | null };
    metodos:       ResumenMetodo[];
    total_vendido: number;
    devoluciones:  number;
    total_neto:    number;
    cobrado:       number;
    por_cobrar:    number;
    num_ventas:    number;
    num_anuladas:  number;
    abonos:        number;
    gastos:        number;
    turnos_abiertos_antes: TurnoAbiertoAntes[];
}

interface Resumen {
    modo?:            undefined;
    turnos_abiertos_antes?: TurnoAbiertoAntes[];
    turno:            { id: number; fecha: string; cajera: string | null; caja: string | null; estado: string };
    metodos:          ResumenMetodo[];
    cobrado:          number;   // suma de métodos de pago
    total_efectivo:   number;
    efectivo_ventas:  number;   // efectivo de ventas del turno
    efectivo_abonos:  number;   // efectivo de abonos de crédito del turno
    anticipos_efectivo: number; // anticipos de clientes en efectivo (entran a caja)
    total_vendido:    number;   // cobrado + por cobrar
    devoluciones?:    number;   // devoluciones / anulaciones con NC de estas cajas
    total_neto?:      number;   // total vendido − devoluciones
    por_cobrar:       number;   // crédito pendiente del turno
    abonos:           number;   // cobros de crédito recibidos en el turno
    gastos:           number;
    reembolsos:       number;   // devoluciones en efectivo
    compras:          number;   // pagos de entradas en efectivo (salen de caja)
    deuda_ingreso:    number;   // desembolsos/cobros de deuda en efectivo (entran)
    deuda_egreso:     number;   // cuotas/préstamos de deuda en efectivo (salen)
    devolucion_anticipos: number; // devoluciones de anticipo en efectivo (salen de caja)
    apertura:         number;
    efectivo_en_caja: number;
    abonos_detalle:   { cliente: string; venta: string | null; metodo: string; monto: number }[];
    anticipos_detalle:{ cliente: string; metodo: string; monto: number }[];
    gastos_detalle:   { concepto: string; monto: number }[];
    compras_detalle:  { proveedor: string; documento: string | null; monto: number }[];
    deuda_detalle:    { descripcion: string; tipo: string; monto: number }[];
    devolucion_anticipos_detalle: { anticipo: number; cliente: string; monto: number }[];
}

interface Props extends PageProps {
    ventas:  Paginado<Venta>;
    locales: Local[];
    turnos:  TurnoLite[];
    resumen: Resumen | ResumenRango | null;
    filters: Filters;
}

const money = (v: unknown) => soles(v);
export default function VentasIndex({ ventas, locales, turnos, resumen, filters, flash }: Props) {
    const { auth } = usePage<Props>().props;

    // Tiempo real: una venta nueva, editada o anulada en otra caja aparece sola.
    useTiempoReal(['ventas'], () => router.reload({ only: ['ventas', 'resumen'] }));
    const esAdmin  = auth.user.rol?.es_admin ?? false;

    // Configuración de empresa: permisos de edición/anulación de cajeras.
    const empresa = auth.user.empresa as {
        cajera_puede_editar?: boolean;
        venta_edicion_con_contador?: boolean;
        venta_edicion_minutos?: number;
        cajera_puede_anular?: boolean;
        venta_whatsapp_pdf?: boolean;
    } | undefined;
    const cajeraPuedeEditar = empresa?.cajera_puede_editar ?? true;
    const edicionConContador = empresa?.venta_edicion_con_contador ?? true;
    const editWindowMs = (Number(empresa?.venta_edicion_minutos ?? 3) || 0) * 60 * 1000;
    const cajeraPuedeAnular = empresa?.cajera_puede_anular ?? true;
    // Botón WhatsApp (PDF de la venta): opcional por empresa.
    const usaWhatsappPdf = empresa?.venta_whatsapp_pdf ?? false;
    // Corregir solo el método de pago (también con boleta/factura emitida).
    const [corregirPago, setCorregirPago] = useState<number | null>(null);
    const puedeCorregirPago = (v: Venta) => vigente(v) && (esAdmin || cajeraPuedeEditar);

    // Reloj para que el gate de edición se actualice en vivo (edición desaparece).
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
    // El "hoy" que puso el servidor (no la cajera). Se recuerda aparte: después
    // de la primera búsqueda el servidor ya no lo marca, y las fechas de hoy que
    // siguen en el formulario se reenviaban limitando la búsqueda a hoy.
    const hoyPorDefecto = useRef<{ desde?: string | null; hasta?: string | null } | null>(
        filters.fechas_por_defecto ? { desde: filters.fecha_desde, hasta: filters.fecha_hasta } : null,
    );
    useEffect(() => {
        if (filters.fechas_por_defecto) hoyPorDefecto.current = { desde: filters.fecha_desde, hasta: filters.fecha_hasta };
    }, [filters.fechas_por_defecto, filters.fecha_desde, filters.fecha_hasta]);
    // Cada filtro se aplica al cambiarlo (sin botón "Aplicar"); la búsqueda de
    // texto espera a que dejen de escribir (ver efecto de abajo).
    function set<K extends keyof Filters>(k: K, v: string) {
        if (k === 'fecha_desde' || k === 'fecha_hasta') hoyPorDefecto.current = null;   // las eligió ella
        const nuevo = { ...local, [k]: v };
        setLocal(nuevo);
        if (k !== 'q') aplicar(nuevo);
    }

    // Buscar mientras escribe: medio segundo sin teclear y busca (Enter, al toque).
    const [buscando, setBuscando] = useState(false);
    useEffect(() => {
        if ((local.q ?? '') === (filters.q ?? '')) return;
        setBuscando(true);
        const t = setTimeout(() => aplicar(), 500);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [local.q]);

    // Estado del modal de anulación.
    const [anular, setAnular]   = useState<Venta | null>(null);
    // Restablecer una venta anulada por error (solo admin; lo decide el servidor).
    const [restablecer, setRestablecer] = useState<Venta | null>(null);
    const [motivoRest, setMotivoRest]   = useState('');
    const [errRest, setErrRest]         = useState<Record<string, string>>({});
    const [restableciendo, setRestableciendo] = useState(false);
    // undefined = no es anulada o no es admin (no se muestra); null = se puede; texto = por qué no.
    const bloqueoRest = (v: Venta) => v.restablecer_bloqueo as string | null | undefined;
    function abrirRestablecer(v: Venta) {
        setRestablecer(v); setMotivoRest(''); setErrRest({});
    }
    function confirmarRestablecer() {
        if (!restablecer) return;
        setRestableciendo(true);
        setErrRest({});
        router.post(route('ventas.restablecer', restablecer.id), { motivo: motivoRest }, {
            preserveScroll: true,
            onSuccess: () => { setRestableciendo(false); setRestablecer(null); },
            onError:   (errs) => { setRestableciendo(false); setErrRest(errs as Record<string, string>); },
        });
    }
    const [motivo, setMotivo]   = useState('');
    const [codigo, setCodigo]   = useState('');
    const [saving, setSaving]   = useState(false);
    const [errAnular, setErrAnular] = useState<Record<string, string>>({});
    const [imprimiendo, setImprimiendo] = useState<number | null>(null);
    // Venta cuyo comprobante se está compartiendo (null = diálogo cerrado).
    const [compartir, setCompartir] = useState<Venta | null>(null);
    // Venta cuyo PDF se está mandando por WhatsApp (null = diálogo cerrado).
    const [whatsapp, setWhatsapp] = useState<Venta | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // ¿La venta sigue dentro del plazo de edición configurable de la empresa?
    // Solo aplica cuando la empresa usa contador.
    function dentroPlazo(v: Venta): boolean {
        if (!edicionConContador || editWindowMs <= 0) return false;
        return ahora - new Date(v.created_at).getTime() < editWindowMs;
    }
    // El admin edita/anula sin límite; la cajera solo si la empresa se lo permite.
    // Vigente = completada y NO anulada con nota de crédito (que por dentro sigue
    // 'completada' con su devolución total, pero para la cajera está anulada).
    const vigente = (v: Venta) => v.estado === 'completada' && !v.anulada_nc_devolucion_id;
    function puedeEditar(v: Venta): boolean {
        if (!vigente(v)) return false;
        if (esAdmin) return true;
        if (!cajeraPuedeEditar) return false;
        return !edicionConContador || dentroPlazo(v);
    }
    // ¿Puede anular esta venta? Admin siempre; cajera según config y plazo.
    function puedeAnular(v: Venta): boolean {
        if (!vigente(v)) return false;
        if (esAdmin) return true;
        return cajeraPuedeAnular;
    }
    // La cajera necesita código de admin para anular fuera del plazo de edición.
    function requiereCodigo(v: Venta): boolean {
        return !esAdmin && cajeraPuedeAnular && !dentroPlazo(v);
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
    // El "hoy" automático no se reenvía si la cajera no tocó las fechas: con una
    // búsqueda (V-0123) o un turno elegido, el servidor ya busca en todos los días.
    function sinFechasPorDefecto(f: Filters): Filters {
        const { fechas_por_defecto: _omit, ...resto } = f;
        const hoy = hoyPorDefecto.current;
        if (hoy && resto.fecha_desde === hoy.desde && resto.fecha_hasta === hoy.hasta) {
            delete resto.fecha_desde;
            delete resto.fecha_hasta;
        }
        return resto;
    }

    function aplicar(filtros: Filters = local) {
        const params = Object.fromEntries(
            Object.entries(sinFechasPorDefecto(filtros)).filter(([, v]) => v !== '' && v != null),
        );
        router.get(route('ventas.index'), params, {
            preserveState: true, replace: true, preserveScroll: true,
            onFinish: () => setBuscando(false),
        });
    }

    // ── Fecha: por defecto UN día (con flechas al anterior/siguiente); el
    //    botón "Rango de fechas" abre el Desde / Hasta. Cambiar el día filtra al
    //    toque: es lo que más se hace ("¿cuánto vendí el 09?").
    const [porRango, setPorRango] = useState(
        !!filters.fecha_desde && !!filters.fecha_hasta && filters.fecha_desde !== filters.fecha_hasta,
    );
    const diaActual = local.fecha_desde || local.fecha_hasta || hoyLocal();
    function irAlDia(dia: string) {
        hoyPorDefecto.current = null;   // lo eligió ella
        const nuevo = { ...local, fecha_desde: dia, fecha_hasta: dia };
        setLocal(nuevo);
        aplicar(nuevo);
    }
    function moverDia(delta: number) {
        const [y, m, d] = diaActual.split('-').map(Number);
        irAlDia(fechaLocal(new Date(y, m - 1, d + delta)));
    }
    function modoUnDia() {
        setPorRango(false);
        irAlDia(local.fecha_hasta || local.fecha_desde || hoyLocal());
    }
    function limpiar() {
        setLocal({ estado: '', fecha_desde: '', fecha_hasta: '', local_id: '', turno_id: '', q: '' });
        setPorRango(false);
        router.get(route('ventas.index'), {}, { preserveState: true, replace: true });
    }

    function exportarExcel() {
        const params = new URLSearchParams();
        Object.entries(sinFechasPorDefecto(filters)).forEach(([k, v]) => {
            if (v !== '' && v != null) params.set(k, String(v));
        });
        const base = route('ventas.exportar');
        window.location.href = params.toString() ? `${base}?${params.toString()}` : base;
    }

    const tienesFiltros = !!(filters.estado || filters.local_id || filters.turno_id || filters.q
        || (!filters.fechas_por_defecto && (filters.fecha_desde || filters.fecha_hasta)));

    function clienteNombre(v: Venta) {
        if (!v.cliente) return 'General';
        const c = v.cliente as any;
        return c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim();
    }

    // Comprobante electrónico de la venta (null en `ticket` y en el histórico
    // anterior a la integración: esas filas se pintan exactamente como siempre).
    const ce = (v: Venta): ComprobanteElectronico | null => v.comprobante_electronico ?? null;
    // Solo se comparte lo que el cliente ya puede recibir: con número y emitido.
    const puedeCompartir = (v: Venta) => comprobanteEmitido(ce(v)?.estado);

    // Venta al crédito con saldo pendiente (cuenta por cobrar).
    const saldo = (v: Venta) => Number((v as any).saldo_pendiente ?? 0);
    const montoPagado = (v: Venta) => Number((v as any).monto_pagado ?? 0);
    const esCredito = (v: Venta) => Boolean((v as any).es_credito);

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
            {resumen && resumen.turnos_abiertos_antes && resumen.turnos_abiertos_antes.length > 0 && (
                <AvisoTurnosAbiertos turnos={resumen.turnos_abiertos_antes} />
            )}
            {resumen && (resumen.modo === 'rango'
                ? <ResumenRangoCards resumen={resumen} />
                : <ResumenCards resumen={resumen} />)}

            {/* ── Filtros (aplican con el botón o Enter, no al tipear) ── */}
            <FiltrosCard
                cols={4}
                tieneFiltros={tienesFiltros}
                onClear={limpiar}
                actions={
                    <>
                        <Button variant="secondary" size="sm" onClick={exportarExcel} startContent={<FileSpreadsheet size={13} />}>
                            Excel
                        </Button>
                    </>
                }
            >
                <div>
                    <label className="text-xs font-semibold mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Estado</label>
                    <Select ariaLabel="Estado" value={local.estado ?? ''} onChange={v => set('estado', String(v))}
                        options={[
                            { value: '', label: 'Todos' },
                            { value: 'completada', label: 'Completadas' },
                            { value: 'anulada', label: 'Anuladas' },
                        ]} />
                </div>

                {porRango ? (
                    <>
                        <div>
                            <label className="text-xs font-semibold mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Desde</label>
                            <input type="date" value={local.fecha_desde ?? ''} onChange={e => set('fecha_desde', e.target.value)} className={inputCls} style={inputStyle} />
                        </div>
                        <div>
                            <label className="flex items-center justify-between text-xs font-semibold mb-1" style={{ color: 'var(--color-text-muted)' }}>
                                Hasta
                                <button type="button" onClick={modoUnDia} className="font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                                    Un solo día
                                </button>
                            </label>
                            <input type="date" value={local.fecha_hasta ?? ''} onChange={e => set('fecha_hasta', e.target.value)} className={inputCls} style={inputStyle} />
                        </div>
                    </>
                ) : (
                    <div>
                        <label className="flex items-center justify-between text-xs font-semibold mb-1" style={{ color: 'var(--color-text-muted)' }}>
                            Día
                            <button type="button" onClick={() => setPorRango(true)}
                                className="inline-flex items-center gap-1 font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                                <CalendarRange size={12} /> Rango de fechas
                            </button>
                        </label>
                        <div className="flex items-center gap-1.5">
                            <button type="button" onClick={() => moverDia(-1)} aria-label="Día anterior" title="Día anterior"
                                className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border hover:bg-black/5" style={inputStyle}>
                                <ChevronLeft size={16} />
                            </button>
                            <input type="date" value={diaActual} max={hoyLocal()} onChange={e => e.target.value && irAlDia(e.target.value)}
                                aria-label="Día" className={inputCls} style={inputStyle} />
                            <button type="button" onClick={() => moverDia(1)} aria-label="Día siguiente" title="Día siguiente"
                                disabled={diaActual >= hoyLocal()}
                                className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border hover:bg-black/5 disabled:opacity-30" style={inputStyle}>
                                <ChevronRight size={16} />
                            </button>
                        </div>
                    </div>
                )}

                {/* Filtro por turno: clave para el admin (ya no ve todo mezclado). */}
                {esAdmin && turnos.length > 0 && (
                    <div>
                        <label className="text-xs font-semibold mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Turno</label>
                        <Select ariaLabel="Turno" value={String(local.turno_id ?? '')} onChange={v => set('turno_id', String(v))}
                            options={[{ value: '', label: 'Todos los turnos' }, ...turnos.map(t => ({ value: String(t.id), label: turnoLabel(t) }))]} />
                    </div>
                )}

                {esAdmin && locales.length > 1 && (
                    <div>
                        <label className="text-xs font-semibold mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Local</label>
                        <Select ariaLabel="Local" value={String(local.local_id ?? '')} onChange={v => set('local_id', String(v))}
                            options={[{ value: '', label: 'Todos los locales' }, ...locales.map(l => ({ value: String(l.id), label: l.nombre }))]} />
                    </div>
                )}
            </FiltrosCard>

            {/* Buscador junto a la tabla: busca solo, mientras escribes. */}
            <div className="relative mb-3">
                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                <input
                    value={local.q ?? ''}
                    onChange={e => set('q', e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter') aplicar(); }}
                    placeholder="Buscar por N° de venta, cliente, documento o monto…"
                    aria-label="Buscar ventas"
                    className="w-full text-sm border rounded-xl pl-10 pr-24 h-11 focus:outline-none focus:ring-2"
                    style={inputStyle}
                />
                {buscando && (
                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs" style={{ color: 'var(--color-text-muted)' }}>Buscando…</span>
                )}
            </div>

            {/* ── Tabla desktop ─────────────────────────────────────── */}
            <div
                className="hidden md:block rounded-xl overflow-hidden"
                style={{ border: '1px solid var(--color-border)' }}
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm" style={{ backgroundColor: 'var(--color-surface)' }}>
                        <thead>
                            <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                                {['N°', 'Fecha', 'Cliente', 'Comprobante', 'Pago', 'Estado', 'Total', ''].map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
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
                                    <div className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
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
                                        {etiquetaComprobante(v.tipo_comprobante as any)}
                                    </span>
                                    {v.numero_comprobante && (
                                        <span className="block text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                            {v.numero_comprobante}
                                        </span>
                                    )}
                                    {/* El sello solo aparece si hay CPE: un `ticket` se ve igual que siempre. */}
                                    <SelloComprobante ce={ce(v)} />
                                </td>
                                <td className="px-4 py-3">
                                    <ChipsPago metodos={metodosDePago(v)} esCredito={Boolean(v.es_credito)} debe={saldo(v)} />
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={vigente(v) ? 'success' : 'danger'}>
                                        {vigente(v) ? 'Completada' : v.anulada_nc_devolucion_id ? 'Anulada · NC' : 'Anulada'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                                        S/ {parseFloat(v.total).toFixed(2)}
                                    </span>
                                    {esCredito(v) && (
                                        <div className="mt-1 flex flex-col gap-1">
                                            {saldo(v) > 0.01 ? (
                                                <>
                                                    {montoPagado(v) > 0.01 && (
                                                        <Badge variant="primary">
                                                            Pagada parcialmente
                                                        </Badge>
                                                    )}
                                                    <Badge variant="warning">
                                                        Por cobrar S/ {saldo(v).toFixed(2)}
                                                    </Badge>
                                                </>
                                            ) : (
                                                <Badge variant="success">
                                                    Pagada totalmente
                                                </Badge>
                                            )}
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
                                        {puedeCompartir(v) && (
                                            <button
                                                onClick={() => setCompartir(v)}
                                                title={`Enviar ${ce(v)?.numero ?? 'el comprobante'} al cliente`}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-success) 12%, transparent)', color: 'var(--color-success)' }}
                                            >
                                                <Send size={14} />
                                            </button>
                                        )}
                                        {usaWhatsappPdf && vigente(v) && (
                                            <button
                                                onClick={() => setWhatsapp(v)}
                                                title="Enviar el PDF de la venta por WhatsApp"
                                                aria-label={`Enviar venta ${v.numero} por WhatsApp`}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, #25D366 14%, transparent)', color: '#1da851' }}
                                            >
                                                <MessageCircle size={14} />
                                            </button>
                                        )}
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
                                        {puedeCorregirPago(v) && (
                                            <button
                                                onClick={() => setCorregirPago(v.id)}
                                                title="Corregir solo el método de pago (el comprobante no cambia)"
                                                aria-label={`Corregir método de pago de la venta ${v.numero}`}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 10%, transparent)', color: 'var(--vp-navy)' }}
                                            >
                                                <ArrowLeftRight size={14} />
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
                                        {puedeAnular(v) && (
                                            <button
                                                onClick={() => abrirAnular(v)}
                                                title={requiereCodigo(v) ? 'Anular (requiere código de admin)' : 'Anular venta'}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                        {esAdmin && bloqueoRest(v) !== undefined && (
                                            <button
                                                onClick={() => abrirRestablecer(v)}
                                                disabled={!!bloqueoRest(v)}
                                                title={bloqueoRest(v) ?? 'Restablecer venta (deshace la anulación)'}
                                                aria-label={`Restablecer venta ${v.numero}`}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80 disabled:opacity-35 disabled:cursor-not-allowed"
                                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)', color: 'var(--color-primary)' }}
                                            >
                                                <RotateCcw size={14} />
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
                                    <Badge variant={vigente(v) ? 'success' : 'danger'}>
                                        {vigente(v) ? 'Completada' : v.anulada_nc_devolucion_id ? 'Anulada · NC' : 'Anulada'}
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
                                    <span className="capitalize">{etiquetaComprobante(v.tipo_comprobante as any)}</span>
                                    {v.numero_comprobante && (
                                        <span> · {v.numero_comprobante}</span>
                                    )}
                                </div>
                                {/* Mismo sello que en la tabla: la cajera de tablet ve lo mismo. */}
                                <SelloComprobante ce={ce(v)} />
                                <div className="mt-1.5">
                                    <ChipsPago metodos={metodosDePago(v)} esCredito={Boolean(v.es_credito)} debe={saldo(v)} />
                                </div>
                            </div>
                            <div className="text-right flex-shrink-0">
                                <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>
                                    S/ {parseFloat(v.total).toFixed(2)}
                                </p>
                                {esCredito(v) && (
                                    <div className="mt-1 flex flex-col items-end gap-1">
                                        {saldo(v) > 0.01 ? (
                                            <>
                                                {montoPagado(v) > 0.01 && (
                                                    <Badge variant="primary">Pagada parcialmente</Badge>
                                                )}
                                                <Badge variant="warning">Por cobrar S/ {saldo(v).toFixed(2)}</Badge>
                                            </>
                                        ) : (
                                            <Badge variant="success">Pagada totalmente</Badge>
                                        )}
                                    </div>
                                )}
                                <p className="text-[11px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
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
                            {puedeCompartir(v) && (
                                <button
                                    onClick={() => setCompartir(v)}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-success) 12%, transparent)', color: 'var(--color-success)' }}
                                >
                                    <Send size={14} /> Enviar
                                </button>
                            )}
                            {usaWhatsappPdf && vigente(v) && (
                                <button
                                    onClick={() => setWhatsapp(v)}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, #25D366 14%, transparent)', color: '#1da851' }}
                                >
                                    <MessageCircle size={14} /> WhatsApp
                                </button>
                            )}
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
                            {puedeCorregirPago(v) && (
                                <button
                                    onClick={() => setCorregirPago(v.id)}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 10%, transparent)', color: 'var(--vp-navy)' }}
                                >
                                    <ArrowLeftRight size={14} /> Pago
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
                            {puedeAnular(v) && (
                                <button
                                    onClick={() => abrirAnular(v)}
                                    title={requiereCodigo(v) ? 'Anular (requiere código de admin)' : 'Anular venta'}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}
                                >
                                    <Trash2 size={14} /> Anular
                                </button>
                            )}
                            {esAdmin && bloqueoRest(v) !== undefined && (
                                <button
                                    onClick={() => abrirRestablecer(v)}
                                    disabled={!!bloqueoRest(v)}
                                    title={bloqueoRest(v) ?? 'Restablecer venta (deshace la anulación)'}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-medium py-2 rounded-lg disabled:opacity-35 disabled:cursor-not-allowed"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)', color: 'var(--color-primary)' }}
                                >
                                    <RotateCcw size={14} /> Restablecer
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
                                onClick={() => router.get(route('ventas.index'), { ...sinFechasPorDefecto(filters), page }, { preserveState: true, preserveScroll: true })}
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

            {/* ── Modal compartir comprobante ──────────────────────── */}
            <Modal
                isOpen={compartir !== null}
                onClose={() => setCompartir(null)}
                title={compartir ? `Enviar ${ce(compartir)?.numero ?? 'comprobante'}` : 'Enviar comprobante'}
                size="sm"
                footer={<Button variant="ghost" onClick={() => setCompartir(null)}>Cerrar</Button>}
            >
                {/* key: al abrir otra venta se remonta y vuelve a consultar. */}
                {compartir && (
                    <CompartirComprobanteBody
                        key={compartir.id}
                        venta={compartir}
                        onEnviado={() => setCompartir(null)}
                    />
                )}
            </Modal>

            {/* ── Modal WhatsApp (PDF de la venta) ─────────────────── */}
            <Modal
                isOpen={whatsapp !== null}
                onClose={() => setWhatsapp(null)}
                title={whatsapp ? `Enviar ${whatsapp.numero} por WhatsApp` : 'Enviar por WhatsApp'}
                size="sm"
                footer={<Button variant="ghost" onClick={() => setWhatsapp(null)}>Cerrar</Button>}
            >
                {whatsapp && <WhatsappVentaBody key={whatsapp.id} venta={whatsapp} />}
            </Modal>

            {/* ── Modal anular ─────────────────────────────────────── */}
            <Modal
                isOpen={restablecer !== null}
                onClose={() => { if (!restableciendo) setRestablecer(null); }}
                title={`Restablecer venta ${restablecer?.numero ?? ''}`}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setRestablecer(null)} disabled={restableciendo}>Cancelar</Button>
                        <Button variant="primary" onClick={confirmarRestablecer} loading={restableciendo}>Restablecer venta</Button>
                    </>
                }
            >
                {restablecer && (
                    <div className="space-y-3">
                        <Callout variant="info">
                            <p style={{ color: 'var(--color-text)' }}>
                                La venta vuelve a quedar como el día que se cobró: se descuenta otra vez el stock, se registra
                                de nuevo su dinero (con su fecha original){restablecer.es_credito ? ' y vuelve la deuda del cliente' : ''}.
                            </p>
                        </Callout>
                        <div>
                            <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                Motivo <span style={{ color: 'var(--color-danger)' }}>*</span>
                            </label>
                            <textarea rows={2} value={motivoRest} onChange={e => setMotivoRest(e.target.value)} disabled={restableciendo}
                                placeholder="Por qué se restablece (mín. 10 caracteres)"
                                className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            {errRest.motivo && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errRest.motivo}</p>}
                            {(errRest.venta || errRest.aviso) && (
                                <p className="text-sm mt-2 font-semibold" role="alert" style={{ color: 'var(--color-danger)' }}>{errRest.venta || errRest.aviso}</p>
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                isOpen={anular !== null}
                onClose={() => setAnular(null)}
                title={`Anular venta ${anular?.numero ?? ''}`}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnular(null)} disabled={saving}>Cancelar</Button>
                        <Button variant="danger" onClick={confirmarAnular} loading={saving}>
                            {anular?.comprobante_electronico?.emitido ? 'Anular con nota de crédito' : 'Anular venta'}
                        </Button>
                    </>
                }
            >
                {anular && (
                    <div className="space-y-3">
                        {anular.comprobante_electronico?.emitido ? (
                            <Callout variant="warning" title={`Se emitirá la nota de crédito de ${anular.comprobante_electronico.numero}`}>
                                <p style={{ color: 'var(--color-text)' }}>
                                    El comprobante ya está en SUNAT. Al anular, el sistema registra la devolución de todo,
                                    devuelve el stock, saca el dinero de tu caja por el mismo medio con que se pagó y envía
                                    la nota de crédito a SUNAT. Es una acción irreversible.
                                </p>
                            </Callout>
                        ) : (
                            <Callout variant="danger">
                                <p style={{ color: 'var(--color-text)' }}>
                                    Anular revierte el stock y el dinero de esta venta. Es una acción irreversible.
                                </p>
                            </Callout>
                        )}

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
                            {/* Rechazos del servidor que no son de un campo (p. ej. "tiene pendientes por entregar"). */}
                            {(errAnular.venta || errAnular.aviso) && (
                                <p className="text-sm mt-2 font-semibold" role="alert" style={{ color: 'var(--color-danger)' }}>{errAnular.venta || errAnular.aviso}</p>
                            )}
                        </div>

                        {/* Código de admin: solo cuando la cajera anula fuera del plazo de edición */}
                        {requiereCodigo(anular) && (
                            <div>
                                <label className="flex items-center gap-1.5 text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                    <KeyRound size={14} style={{ color: 'var(--color-warning)' }} />
                                    Código de autorización
                                </label>
                                <p className="text-xs mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                    {editWindowMs > 0
                                        ? `Pasaron más de ${Math.round(editWindowMs / 60000)} minutos. Pide a un administrador su código de autorización para anular.`
                                        : 'La empresa no permite a las cajeras anular ventas sin autorización. Pide a un administrador su código.'}
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
            <ModalCorregirPago ventaId={corregirPago} onClose={() => setCorregirPago(null)} />
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

/**
 * Sello del comprobante electrónico en la lista.
 *
 * Regla de oro: si la venta no tiene CPE (todos los `ticket` y todo el
 * histórico anterior a la integración) NO se pinta nada, la fila queda idéntica.
 *
 * El caso normal es un punto de color + el número oficial, que es el dato que
 * el cliente menciona por teléfono. Los estados con problema escalan a pastilla
 * roja con etiqueta: repasando cien filas, esas son las que hay que encontrar.
 * El porqué del estado va en el `title` para no gastar ancho de tabla.
 */
function SelloComprobante({ ce }: { ce: ComprobanteElectronico | null }) {
    if (!ce) return null;

    const meta     = metaEstado(ce.estado);
    const enCurso  = estadoEnCurso(ce.estado);
    const problema = comprobanteConProblema(ce.estado);
    // Un comprobante que falló al mapear nunca llegó a numerarse.
    const numero   = ce.numero ?? 'Sin numerar';
    const tooltip  = [etiquetaTipoSunat(ce.tipo), meta.label, meta.detalle].filter(Boolean).join(' · ');

    if (problema) {
        return (
            <div className="mt-1 flex flex-col items-start gap-0.5" title={tooltip}>
                <span
                    className="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-danger) 12%, transparent)',
                        color: 'var(--color-danger)',
                    }}
                >
                    <AlertTriangle size={11} className="flex-shrink-0" />
                    <span className="font-mono text-[11px] font-bold">{numero}</span>
                </span>
                <span className="text-[11px] font-semibold leading-tight" style={{ color: 'var(--color-danger)' }}>
                    {meta.label}
                </span>
            </div>
        );
    }

    return (
        <div className="mt-1 flex items-center gap-1.5" title={tooltip}>
            <span
                className={`inline-block h-1.5 w-1.5 rounded-full flex-shrink-0 ${enCurso ? 'animate-pulse' : ''}`}
                style={{ backgroundColor: meta.color }}
            />
            <span className="font-mono text-[11px] leading-none" style={{ color: 'var(--color-text)' }}>
                {numero}
            </span>
        </div>
    );
}

/**
 * Contenido del diálogo «Enviar comprobante».
 *
 * Los enlaces para compartir (wa.me firmado + PDF público) los arma el backend
 * y viajan dentro de GET /comprobante/estado. Se piden AL ABRIR el diálogo y no
 * al cargar la lista: precargarlos serían 25 peticiones por página para algo
 * que casi nunca se usa.
 *
 * Si el backend todavía no envía el bloque `compartir`, las dos acciones quedan
 * deshabilitadas con su explicación en vez de fallar al pulsarlas.
 */
function CompartirComprobanteBody({ venta, onEnviado }: { venta: Venta; onEnviado: () => void }) {
    const [resp, setResp]         = useState<EstadoComprobanteResp | null>(null);
    const [cargando, setCargando] = useState(true);
    const [fallo, setFallo]       = useState(false);
    const [email, setEmail]       = useState('');
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        let vivo = true;
        (async () => {
            try {
                const { data } = await axios.get<EstadoComprobanteResp>(rutaComprobante.estado(venta.id));
                if (!vivo) return;
                setResp(data);
                setEmail(data?.compartir?.email_cliente ?? '');
            } catch {
                if (vivo) setFallo(true);
            } finally {
                if (vivo) setCargando(false);
            }
        })();
        return () => { vivo = false; };
    }, [venta.id]);

    const compartir = resp?.compartir ?? null;
    const numero    = resp?.numero ?? venta.comprobante_electronico?.numero ?? null;

    // wa.me abre WhatsApp con el mensaje ya escrito; el envío lo hace la persona.
    function abrirWhatsapp() {
        if (!compartir?.whatsapp) return;
        window.open(compartir.whatsapp, '_blank', 'noopener,noreferrer');
    }

    async function enviarCorreo() {
        const destino = email.trim();
        if (!destino || enviando) return;
        setEnviando(true);
        const tid = toast.loading(`Enviando a ${destino}...`);
        try {
            const { data } = await axios.post<{ message?: string }>(
                rutaComprobante.enviarCorreo(venta.id), { email: destino },
            );
            // Se prefiere el mensaje del backend: es el mismo texto que ve la
            // cajera si el envío se hace desde el detalle de la venta.
            toast.success(data?.message ?? `Comprobante enviado a ${destino}`, { id: tid });
            onEnviado();
        } catch (e) {
            const msg = (axios.isAxiosError(e)
                ? (e.response?.data as { message?: string } | undefined)?.message
                : null)
                ?? 'No se pudo enviar el correo. Revisa la dirección e inténtalo de nuevo.';
            toast.error(msg, { id: tid });
        } finally {
            setEnviando(false);
        }
    }

    if (cargando) {
        return (
            <p className="text-sm py-2" style={{ color: 'var(--color-text-muted)' }}>
                Preparando el envío...
            </p>
        );
    }

    if (fallo) {
        return (
            <p className="text-sm py-2" style={{ color: 'var(--color-danger)' }}>
                No se pudo leer el comprobante. Revisa la conexión y vuelve a abrir el envío.
            </p>
        );
    }

    const sinEnlaces = !compartir;

    return (
        <div className="space-y-4">
            <div>
                <p className="font-mono text-base font-bold leading-tight" style={{ color: 'var(--color-text)' }}>
                    {numero ?? 'Sin numerar'}
                </p>
                <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                    {etiquetaTipoSunat(resp?.tipo)} · {venta.numero}
                </p>
            </div>

            {sinEnlaces && (
                <p className="text-xs leading-snug rounded-lg px-3 py-2"
                    style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                    El envío al cliente todavía no está disponible para este comprobante.
                    Mientras tanto puedes descargar el PDF desde el detalle de la venta.
                </p>
            )}

            {/* WhatsApp: enlace wa.me, sin APIs de por medio. */}
            <Button
                variant="success"
                flat
                className="w-full"
                startContent={<MessageCircle size={15} />}
                onClick={abrirWhatsapp}
                disabled={!compartir?.whatsapp}
                title={compartir?.whatsapp
                    ? 'Abre WhatsApp con el mensaje y el enlace ya escritos'
                    : 'El cliente no tiene teléfono registrado'}
            >
                Enviar por WhatsApp
            </Button>

            {/* Correo: si el cliente no tiene uno guardado, se pide aquí mismo. */}
            <div>
                <label className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                    Correo del cliente
                </label>
                <div className="flex gap-2">
                    <input
                        type="email"
                        value={email}
                        onChange={e => setEmail(e.target.value)}
                        onKeyDown={e => { if (e.key === 'Enter') void enviarCorreo(); }}
                        disabled={sinEnlaces || enviando}
                        placeholder="cliente@correo.pe"
                        className="flex-1 min-w-0 rounded-xl px-3 py-2 text-sm disabled:opacity-50"
                        style={{
                            border: '1px solid var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            color: 'var(--color-text)',
                        }}
                    />
                    <Button
                        variant="primary"
                        startContent={<Mail size={15} />}
                        onClick={() => void enviarCorreo()}
                        loading={enviando}
                        disabled={sinEnlaces || !email.trim()}
                        title={sinEnlaces ? 'El envío por correo aún no está disponible' : 'Enviar el PDF a este correo'}
                    >
                        Enviar
                    </Button>
                </div>
                {!sinEnlaces && !compartir?.email_cliente && (
                    <p className="text-[11px] mt-1" style={{ color: 'var(--color-text-muted)' }}>
                        Este cliente no tiene correo guardado. Escribe uno para enviarle el comprobante.
                    </p>
                )}
            </div>
        </div>
    );
}

type FormatoPdf = 'a4' | 'ticket';
interface WhatsappVentaResp {
    formato:  FormatoPdf;
    enlaces:  Record<FormatoPdf, { pdf: string; mensaje: string }>;
    telefono: string;
    cliente:  string | null;
}

/**
 * `wa.me` exige el número internacional sin '+': un móvil peruano de 9 dígitos
 * sin el 51 abre un chat con un contacto inexistente. Mismo criterio que
 * CompartirComprobante::normalizarTelefono en el backend.
 */
function numeroWhatsapp(telefono: string): string {
    const d = telefono.replace(/\D/g, '');
    return d.length === 9 && !d.startsWith('51') ? `51${d}` : d;
}

/**
 * Contenido del diálogo «Enviar por WhatsApp» (PDF de la venta).
 *
 * Dos caminos, porque WhatsApp no deja adjuntar un archivo a un número desde
 * un enlace:
 *   · «Abrir WhatsApp» (todos los dispositivos): wa.me con el número y el
 *     mensaje ya escritos; el mensaje lleva el enlace firmado al PDF.
 *   · «Compartir el archivo PDF» (solo donde el navegador puede compartir
 *     archivos, sobre todo celulares): manda el PDF como adjunto; el contacto
 *     se elige dentro de WhatsApp.
 * El PDF se descarga al abrir el diálogo (y al cambiar de formato) para que
 * `navigator.share` se llame dentro del clic: Safari lo rechaza si antes hay
 * una espera. Se descarga el MISMO enlace firmado que recibe el cliente.
 */
function WhatsappVentaBody({ venta }: { venta: Venta }) {
    const [resp, setResp]         = useState<WhatsappVentaResp | null>(null);
    const [fallo, setFallo]       = useState<string | null>(null);
    const [telefono, setTelefono] = useState('');
    const [formato, setFormato]   = useState<FormatoPdf>('a4');
    const [archivo, setArchivo]   = useState<File | null>(null);
    const puedeCompartir = typeof navigator !== 'undefined' && typeof navigator.canShare === 'function'
        && navigator.canShare({ files: [new File([''], 'x.pdf', { type: 'application/pdf' })] });

    useEffect(() => {
        let vivo = true;
        axios.get<WhatsappVentaResp>(route('ventas.whatsapp-pdf', venta.id))
            .then(({ data }) => {
                if (!vivo) return;
                setResp(data); setTelefono(data.telefono ?? ''); setFormato(data.formato ?? 'a4');
            })
            .catch(e => {
                if (!vivo) return;
                const msg = axios.isAxiosError(e) ? (e.response?.data as { message?: string } | undefined)?.message : null;
                setFallo(msg || 'No se pudo preparar el envío. Revisa la conexión e inténtalo de nuevo.');
            });
        return () => { vivo = false; };
    }, [venta.id]);

    const enlacePdf = resp?.enlaces?.[formato]?.pdf ?? null;
    useEffect(() => {
        if (!puedeCompartir || !enlacePdf) return;
        let vivo = true;
        setArchivo(null);
        axios.get<Blob>(enlacePdf, { responseType: 'blob' })
            .then(({ data }) => {
                if (vivo && data.type.includes('pdf')) {
                    setArchivo(new File([data], `Venta-${venta.numero_comprobante || venta.numero}.pdf`, { type: 'application/pdf' }));
                }
            })
            .catch(() => { /* sin archivo: queda el enlace por wa.me */ });
        return () => { vivo = false; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enlacePdf]);

    async function compartirArchivo() {
        if (!archivo) return;
        try {
            await navigator.share({ files: [archivo], title: `Venta ${venta.numero}` });
        } catch (e) {
            if ((e as DOMException)?.name !== 'AbortError') toast.error('No se pudo compartir el archivo.');
        }
    }

    if (fallo) {
        return <p className="text-sm py-2" style={{ color: 'var(--color-danger)' }}>{fallo}</p>;
    }
    if (!resp) {
        return <p className="text-sm py-2" style={{ color: 'var(--color-text-muted)' }}>Preparando el envío...</p>;
    }

    const numero  = numeroWhatsapp(telefono);
    const mensaje = resp.enlaces[formato]?.mensaje ?? '';
    const enlace  = `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`;

    return (
        <div className="space-y-4">
            <div>
                <p className="font-mono text-base font-bold leading-tight" style={{ color: 'var(--color-text)' }}>
                    {venta.numero_comprobante || venta.numero}
                </p>
                <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                    {resp.cliente ?? 'Cliente general'} · S/ {parseFloat(venta.total).toFixed(2)}
                </p>
            </div>

            <div>
                <span className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text)' }}>Formato del PDF</span>
                <div className="grid grid-cols-2 gap-2">
                    {([['a4', 'Hoja A4'], ['ticket', 'Ticket 80 mm']] as const).map(([valor, texto]) => (
                        <button
                            key={valor}
                            type="button"
                            onClick={() => setFormato(valor)}
                            className="rounded-xl px-3 py-2 text-sm font-medium transition-colors"
                            style={formato === valor
                                ? { backgroundColor: 'var(--color-primary)', color: '#fff', border: '1px solid var(--color-primary)' }
                                : { backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', border: '1px solid var(--color-border)' }}
                        >
                            {texto}
                        </button>
                    ))}
                </div>
                <a href={resp.enlaces[formato]?.pdf} target="_blank" rel="noopener noreferrer"
                    className="inline-block text-[11px] mt-1 underline" style={{ color: 'var(--color-primary)' }}>
                    Ver cómo queda el PDF
                </a>
            </div>

            <div>
                <label className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                    Número de WhatsApp
                </label>
                <input
                    type="tel"
                    inputMode="tel"
                    value={telefono}
                    onChange={e => setTelefono(e.target.value)}
                    placeholder="987 654 321"
                    className="w-full rounded-xl px-3 py-2 text-sm"
                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                />
                <p className="text-[11px] mt-1" style={{ color: 'var(--color-text-muted)' }}>
                    {numero
                        ? `Se abrirá el chat con +${numero}.`
                        : 'Sin número: WhatsApp te dejará elegir el contacto.'}
                </p>
            </div>

            <a
                href={enlace}
                target="_blank"
                rel="noopener noreferrer"
                className="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                style={{ backgroundColor: '#25D366' }}
            >
                <MessageCircle size={16} /> Abrir WhatsApp
            </a>

            {puedeCompartir && (
                <Button
                    variant="ghost"
                    className="w-full"
                    onClick={() => void compartirArchivo()}
                    disabled={!archivo}
                    title="Comparte el archivo PDF; el contacto se elige en WhatsApp"
                >
                    {archivo ? 'Compartir el archivo PDF' : 'Preparando el PDF...'}
                </Button>
            )}

            <p className="text-[11px] leading-snug" style={{ color: 'var(--color-text-muted)' }}>
                El mensaje lleva un enlace al PDF válido 30 días. El envío lo haces tú desde WhatsApp.
            </p>
        </div>
    );
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
                    className="text-[11px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap"
                    style={{
                        color: 'var(--color-primary)',
                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                    }}>
                    {m}
                </span>
            ))}
            {esCredito && (
                <span
                    className="text-[11px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap"
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

// ── Turno que quedó abierto de un día anterior ──────────────────────────────

const fechaCorta = (f: string | null) => f ? new Date(f.length <= 10 ? `${f}T00:00:00` : f).toLocaleDateString('es-PE') : '';

/**
 * El turno que no se cerró junta en su caja el dinero de varios días: es la
 * causa típica de "las cards no cuadran con lo vendido hoy". Se dice cuál, desde
 * cuándo, y se lleva a cerrarlo.
 */
function AvisoTurnosAbiertos({ turnos }: { turnos: TurnoAbiertoAntes[] }) {
    return (
        <Callout variant="warning" className="mb-4"
            title={turnos.length === 1 ? 'Hay un turno abierto desde otro día' : `Hay ${turnos.length} turnos abiertos desde otro día`}>
            <ul className="space-y-1">
                {turnos.map(t => (
                    <li key={t.id} className="flex flex-wrap items-center gap-x-2">
                        <span>
                            Turno <strong>#{t.id}</strong>{t.cajera ? ` de ${t.cajera}` : ''} sigue abierto desde el <strong>{fechaCorta(t.desde)}</strong>:
                            su caja suma el dinero de todos esos días.
                        </span>
                        <Link href={route('turnos.cerrar.page', t.id)} className="font-semibold underline">Cerrar ese turno →</Link>
                    </li>
                ))}
            </ul>
            <span className="block mt-1 text-xs">Ciérralo al final de cada día para que la caja de cada día salga sola.</span>
        </Callout>
    );
}

/**
 * Resumen de un rango de fechas. Arriba, cuatro cifras (lo vendido, lo cobrado,
 * lo que falta cobrar y los gastos); abajo, SIEMPRE, cómo entró el dinero por
 * cada medio de pago: ventas + cobros de crédito − devoluciones.
 */
function ResumenRangoCards({ resumen }: { resumen: ResumenRango }) {
    const [detalle, setDetalle] = useState(false);
    const { desde, hasta } = resumen.rango;
    const titulo = desde && hasta && desde === hasta
        ? `Ventas del ${fechaCorta(desde)}`
        : `Ventas del ${fechaCorta(desde) || '…'} al ${fechaCorta(hasta) || 'hoy'}`;
    const hayDevoluciones = resumen.devoluciones > 0;

    return (
        <div className="mb-5 space-y-3">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                <Calendar size={13} />
                <span className="font-semibold uppercase tracking-wide">{titulo}</span>
                <span>
                    · {resumen.num_ventas} {resumen.num_ventas === 1 ? 'venta' : 'ventas'}
                    {resumen.num_anuladas > 0 && <> · {resumen.num_anuladas} {resumen.num_anuladas === 1 ? 'anulada' : 'anuladas'}</>}
                </span>
                <button onClick={() => setDetalle(true)} className="ml-auto inline-flex items-center gap-1 font-semibold hover:underline"
                    style={{ color: 'var(--color-primary)' }}>
                    <ListChecks size={13} /> Ver detalle
                </button>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <Card destacado icon={<Coins size={18} />} label="Total vendido" valor={money(resumen.total_neto)} color="primary"
                    sub={hayDevoluciones
                        ? `Vendido ${money(resumen.total_vendido)} − devoluciones ${money(resumen.devoluciones)}`
                        : 'Toca para ver el detalle'}
                    onClick={() => setDetalle(true)} />
                <Card icon={<Wallet size={18} />} label="Cobrado" valor={money(resumen.cobrado)}
                    sub="Por todos los medios de pago" color="success" onClick={() => setDetalle(true)} />
                <Card icon={<Clock size={18} />} label="Por cobrar" valor={money(resumen.por_cobrar)} sub="Ventas al crédito pendientes" color="warning" />
                <Card icon={<TrendingDown size={18} />} label="Gastos" valor={money(resumen.gastos)} sub="Registrados en esas fechas" color="danger" />
            </div>

            <MediosDePago metodos={resumen.metodos} onVer={() => setDetalle(true)} />

            <Modal isOpen={detalle} onClose={() => setDetalle(false)} title={`Detalle — ${titulo}`} size="lg">
                <div className="space-y-4 text-sm">
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-1.5 tabular-nums">
                        <dt style={{ color: 'var(--color-text-muted)' }}>Vendido ({resumen.num_ventas} {resumen.num_ventas === 1 ? 'venta' : 'ventas'})</dt>
                        <dd className="text-right font-semibold">{money(resumen.total_vendido)}</dd>
                        {hayDevoluciones && <>
                            <dt style={{ color: 'var(--color-text-muted)' }}>Devoluciones y anulaciones con nota de crédito</dt>
                            <dd className="text-right font-semibold" style={{ color: 'var(--color-danger)' }}>−{money(resumen.devoluciones)}</dd>
                        </>}
                        <dt className="font-bold">Total vendido</dt>
                        <dd className="text-right font-bold">{money(resumen.total_neto)}</dd>
                        <dt style={{ color: 'var(--color-text-muted)' }}>Cobrado (todos los medios)</dt>
                        <dd className="text-right">{money(resumen.cobrado)}</dd>
                        <dt style={{ color: 'var(--color-text-muted)' }}>Por cobrar (crédito)</dt>
                        <dd className="text-right">{money(resumen.por_cobrar)}</dd>
                        {resumen.abonos > 0 && <>
                            <dt style={{ color: 'var(--color-text-muted)' }}>Incluye cobros de crédito</dt>
                            <dd className="text-right">{money(resumen.abonos)}</dd>
                        </>}
                        <dt style={{ color: 'var(--color-text-muted)' }}>Gastos</dt>
                        <dd className="text-right">{money(resumen.gastos)}</dd>
                    </dl>
                    <MediosDePago metodos={resumen.metodos} conCuentas />
                </div>
            </Modal>
        </div>
    );
}

/** Tabla de medios de pago: ventas + cobros de crédito − devoluciones = total. */
function MediosDePago({ metodos, onVer, conCuentas = false }: { metodos: ResumenMetodo[]; onVer?: () => void; conCuentas?: boolean }) {
    const total = metodos.reduce((s, m) => s + m.total, 0);
    const hayAbonos = metodos.some(m => (m.abonos ?? 0) > 0);
    const hayDev = metodos.some(m => (m.devoluciones ?? 0) > 0);
    return (
        <section className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
            <header className="flex items-center justify-between px-4 py-2.5" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="flex items-center gap-2 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                    <CreditCard size={15} /> Medios de pago
                </span>
                {onVer && (
                    <button onClick={onVer} className="text-xs font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>Ver detalle</button>
                )}
            </header>
            {metodos.length === 0 ? (
                <p className="px-4 py-4 text-sm" style={{ color: 'var(--color-text-muted)' }}>No hubo cobros en estas fechas.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm tabular-nums">
                        <thead>
                            <tr className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                <th className="text-left font-semibold px-4 py-2">Medio</th>
                                <th className="text-right font-semibold px-3 py-2">Ventas</th>
                                {hayAbonos && <th className="text-right font-semibold px-3 py-2">Cobros de crédito</th>}
                                {hayDev && <th className="text-right font-semibold px-3 py-2">Devoluciones</th>}
                                <th className="text-right font-semibold px-4 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {metodos.map(m => (
                                <tr key={m.metodo} style={{ borderTop: '1px solid var(--color-border)' }}>
                                    <td className="px-4 py-2">
                                        <span className="font-semibold" style={{ color: 'var(--color-text)' }}>{m.metodo}</span>
                                        {(conCuentas || m.cuentas.length === 1) && m.cuentas.some(c => c.cuenta) && (
                                            <span className="block text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {m.cuentas.map(c => conCuentas && m.cuentas.length > 1 ? `${c.cuenta ?? 'Sin cuenta'} ${money(c.total)}` : (c.cuenta ?? 'Sin cuenta')).join(' · ')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-right px-3 py-2">{money(m.ventas ?? m.total)}</td>
                                    {hayAbonos && <td className="text-right px-3 py-2">{(m.abonos ?? 0) > 0 ? money(m.abonos!) : '—'}</td>}
                                    {hayDev && <td className="text-right px-3 py-2" style={{ color: (m.devoluciones ?? 0) > 0 ? 'var(--color-danger)' : undefined }}>
                                        {(m.devoluciones ?? 0) > 0 ? `−${money(m.devoluciones!)}` : '—'}
                                    </td>}
                                    <td className="text-right px-4 py-2 font-bold" style={{ color: 'var(--color-text)' }}>{money(m.total)}</td>
                                </tr>
                            ))}
                            <tr style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                <td className="px-4 py-2 font-bold" colSpan={1 + 1 + (hayAbonos ? 1 : 0) + (hayDev ? 1 : 0)}>Total cobrado</td>
                                <td className="text-right px-4 py-2 font-bold">{money(total)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            )}
        </section>
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
        resumen.devolucion_anticipos > 0 ? `− devol. anticipos ${money(resumen.devolucion_anticipos)}` : null,
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
                    valor={money(resumen.total_neto ?? resumen.total_vendido)}
                    sub={(resumen.devoluciones ?? 0) > 0
                        ? `Vendido ${money(resumen.total_vendido)} − devoluciones ${money(resumen.devoluciones!)}`
                        : `Cobrado ${money(resumen.cobrado)} + por cobrar ${money(resumen.por_cobrar)}`}
                    onClick={() => setDetalle(true)}
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
            {sub && <span className="text-[11px] leading-tight" style={{ color: 'var(--color-text-muted)' }}>{sub}</span>}
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
                        {resumen.devolucion_anticipos > 0 && fila('Devoluciones de anticipo en efectivo', resumen.devolucion_anticipos, '−')}
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

                {/* Devoluciones de anticipo pagadas desde esta caja. El anticipo pudo
                    haber entrado en otro turno (u otro día): lo que se descuenta aquí
                    es el billete que salió de ESTE cajón. */}
                {resumen.devolucion_anticipos_detalle.length > 0 && (
                    <section>
                        <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>
                            Devoluciones de anticipo ({money(resumen.devolucion_anticipos)})
                        </p>
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {resumen.devolucion_anticipos_detalle.map((d, i) => (
                                <div key={i} className="flex items-center justify-between px-3 py-1.5 text-xs" style={{ borderTop: i > 0 ? '1px solid var(--color-border)' : undefined }}>
                                    <span style={{ color: 'var(--color-text)' }}>
                                        {d.cliente} <span style={{ color: 'var(--color-text-muted)' }}>(anticipo #{d.anticipo})</span>
                                    </span>
                                    {d.monto > 0 ? (
                                        <span className="font-mono" style={{ color: 'var(--color-danger)' }}>− {money(d.monto)}</span>
                                    ) : (
                                        <span style={{ color: 'var(--color-text-muted)' }}>no salió en efectivo</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </Modal>
    );
}
