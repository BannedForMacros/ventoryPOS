import { useEffect, useRef, useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import {
    ArrowLeft, XCircle, Receipt, User, ShoppingBag,
    CreditCard, Percent, Calendar, Store, UserCheck, Printer,
    FileCheck2, Download, RefreshCw, KeyRound, FileText, PackageOpen, History, Undo2,
    RotateCcw,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import ModalModificarPedido from '@/Components/Ventas/ModalModificarPedido';
import { agenteActivo, imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
import {
    rutaComprobante, metaEstado, estadoEnCurso, puedeReintentar, etiquetaTipoSunat, etiquetaComprobante,
    type EstadoComprobanteResp,
} from '@/lib/comprobanteElectronico';
import type { PageProps, Venta, VentaItem, VentaPago, DescuentoLog, ComprobanteElectronico } from '@/types';
import { useTiempoReal } from '@/lib/useTiempoReal';

/** Registro de auditoría de una modificación del pedido pendiente. */
interface ModificacionPedido {
    id: number;
    user_name: string | null;
    created_at: string;
    contexto: {
        motivo?: string;
        total_antes?: number;
        total_nuevo?: number;
        diferencia?: number;
        liquidacion?: { tipo: string; del_anticipo?: number; pago?: number; al_credito?: number; excedente?: number; anticipo_id?: number };
    } | null;
}

interface Props extends PageProps {
    venta: Venta;
    ticketImpresion?: TicketPayload | null;
    puedeModificarPedido?: boolean;
    modificacionesPedido?: ModificacionPedido[];
    /**
     * Por qué esta venta ya no se puede anular ni editar; null si sí se puede.
     * Lo decide el servidor (VentaService::motivoBloqueoFiscal): la pantalla NO
     * tiene su propia lista de estados, porque dos listas separadas es como
     * nacieron los bugs fiscales de este módulo.
     */
    bloqueoFiscal?: string | null;
    /** Comprobante en SUNAT: anular emite la Nota de Crédito (cuánto vuelve y por qué medio). */
    /** Solo para el admin, con la venta anulada: null = puede restablecerla; texto = por qué no. */
    restablecer?: { bloqueo: string | null } | null;
    anulacionNc?: { comprobante: string; reembolso: number; cxc: number; pagos: { metodo: string; monto: number }[]; bloqueo: string | null } | null;
    /** Factura/boleta emitida fuera del sistema: se avisa, no se bloquea. */
    avisoExterno?: string | null;
    /** Cuánto dejó la venta; null si el usuario no puede ver utilidad o si está anulada. */
    utilidad?: UtilidadVenta | null;
}

/** Cuánto dejó la venta (lo manda el servidor solo a quien puede ver la utilidad). */
interface UtilidadLinea { costo: number; utilidad: number; margen: number | null; sin_costo: boolean; }
interface UtilidadVenta {
    items:          Record<number, UtilidadLinea>;
    total:          number;
    costo:          number;
    utilidad_bruta: number;
    devuelto:       number;
    recuperado:     number;
    utilidad:       number;
    margen:         number | null;
    sin_costo:      number;
}

const num = (v: string | number | null | undefined) => parseFloat(String(v ?? 0)) || 0;
const fmtS = (n: number) => 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtCant = (n: number) => n.toLocaleString('es-PE', { maximumFractionDigits: 3 });
const fmtPct = (n: number | null) => (n === null ? '—' : `${n.toLocaleString('es-PE', { maximumFractionDigits: 1 })}%`);

/** Tarjeta de la página: título legible con ícono (sin mayúsculas diminutas). */
function SectionCard({ icon: Icon, title, children, flush = false }: { icon: React.ElementType; title: string; children: React.ReactNode; flush?: boolean }) {
    return (
        <section className="rounded-2xl overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
            <header className="flex items-center gap-2.5 px-4 pt-4 pb-3">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 9%, var(--color-surface))', color: 'var(--vp-navy)' }}>
                    <Icon size={16} />
                </span>
                <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{title}</h2>
            </header>
            <div className={flush ? '' : 'px-4 pb-4'}>{children}</div>
        </section>
    );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-2" style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
            <dt className="text-[13px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>{label}</dt>
            <dd className="text-sm font-medium text-right min-w-0" style={{ color: 'var(--color-text)' }}>{value}</dd>
        </div>
    );
}

function Resumen({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="px-5 py-4 min-w-0 border-b sm:border-b-0 sm:border-l sm:first:border-l-0" style={{ borderColor: 'var(--color-border)' }}>
            <p className="text-[13px] font-semibold mb-1" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            {children}
        </div>
    );
}

function EstadoVenta({ anulada }: { anulada: boolean }) {
    const [texto, color, tinta] = anulada
        ? ['Anulada', 'var(--vp-coral)', 'var(--vp-coral-ink)']
        : ['Completada', 'var(--vp-mint)', 'var(--vp-mint-ink)'];
    return (
        <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold px-2.5 py-1 rounded-full"
            style={{ color: tinta, backgroundColor: `color-mix(in srgb, ${color} 14%, transparent)` }}>
            <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} /> {texto}
        </span>
    );
}

function Th({ children, right = false }: { children: React.ReactNode; right?: boolean }) {
    return (
        <th className={`px-3 first:px-4 last:px-4 py-2.5 text-[13px] font-semibold whitespace-nowrap ${right ? 'text-right' : 'text-left'}`}
            style={{ color: 'var(--color-text-muted)' }}>
            {children}
        </th>
    );
}

function FilaTotal({ label, valor, color, tenue = false }: { label: string; valor: string; color?: string; tenue?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt style={{ color: 'var(--color-text-muted)' }}>{label}</dt>
            <dd className="tabular-nums font-semibold" style={{ color: color ?? (tenue ? 'var(--color-text-muted)' : 'var(--color-text)') }}>{valor}</dd>
        </div>
    );
}

/** Ganancia de una línea: monto y margen, o aviso si falta el costo. */
function GananciaLinea({ linea, conTexto = false }: { linea?: UtilidadLinea; conTexto?: boolean }) {
    if (!linea) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
    if (linea.sin_costo) {
        return <span className="font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>{conTexto ? 'Falta registrar el costo de este producto' : 'falta el costo'}</span>;
    }
    const color = linea.utilidad < 0 ? 'var(--vp-coral-ink)' : 'var(--vp-mint-ink)';
    return (
        <span className="tabular-nums" style={{ color }}>
            {conTexto && (linea.utilidad < 0 ? 'Perdiste ' : 'Ganaste ')}
            <strong>{fmtS(conTexto ? Math.abs(linea.utilidad) : linea.utilidad)}</strong>
            {linea.margen !== null && <span className="ml-1 text-[13px]">({fmtPct(linea.margen)})</span>}
        </span>
    );
}

/** La cuenta de la venta: vendido − costo = ganancia (y lo que deshicieron las devoluciones). */
function CuantoGanaste({ u }: { u: UtilidadVenta }) {
    const hubo = u.devuelto > 0 || u.recuperado > 0;
    const color = u.utilidad >= 0 ? 'var(--vp-mint)' : 'var(--vp-coral)';
    return (
        <section className="rounded-2xl overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', border: `1px solid color-mix(in srgb, ${color} 35%, var(--color-border))`, boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
            <header className="px-4 pt-4 pb-3" style={{ backgroundColor: `color-mix(in srgb, ${color} 10%, var(--color-surface))` }}>
                <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>Cuánto ganaste con esta venta</h2>
                <p className="font-display text-[26px] font-extrabold tabular-nums leading-tight mt-1"
                    style={{ color: u.utilidad >= 0 ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)' }}>
                    {fmtS(u.utilidad)}
                </p>
                {u.margen !== null && (
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{fmtPct(u.margen)} de lo que cobraste</p>
                )}
            </header>
            <dl className="px-4 py-3 space-y-1.5 text-sm">
                <FilaTotal label="Cobraste" valor={fmtS(u.total)} />
                <FilaTotal label="Te costó la mercadería" valor={`−${fmtS(u.costo)}`} color="var(--color-text-muted)" />
                {hubo && (
                    <>
                        <FilaTotal label="Devolviste al cliente" valor={`−${fmtS(u.devuelto)}`} color="var(--vp-coral-ink)" />
                        {u.recuperado > 0 && <FilaTotal label="Volvió al stock" valor={`+${fmtS(u.recuperado)}`} color="var(--vp-mint-ink)" />}
                    </>
                )}
                <div className="flex items-baseline justify-between pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                    <dt className="font-bold" style={{ color: 'var(--color-text)' }}>Ganancia</dt>
                    <dd className="font-bold tabular-nums" style={{ color: u.utilidad >= 0 ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)' }}>{fmtS(u.utilidad)}</dd>
                </div>
            </dl>
            <div className="px-4 pb-4 space-y-2">
                {u.sin_costo > 0 && (
                    <p className="rounded-xl px-3 py-2 text-[13px] font-semibold"
                        style={{ color: 'var(--vp-amber-ink)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 13%, var(--color-surface))' }}>
                        {u.sin_costo === 1 ? 'Un producto no tiene' : `${u.sin_costo} productos no tienen`} costo registrado: la ganancia sale más alta de lo real.
                    </p>
                )}
                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                    El costo es el del día de la venta. {hubo ? 'Las devoluciones deshacen esa parte de la venta; lo que volvió al stock no es pérdida.' : ''}
                </p>
            </div>
        </section>
    );
}

export default function VentasShow({ venta, flash, ticketImpresion, puedeModificarPedido = false, modificacionesPedido = [], bloqueoFiscal = null, anulacionNc = null, restablecer = null, avisoExterno = null, utilidad = null }: Props) {
    // Tiempo real: la respuesta de SUNAT, un abono o una edición se ven sin recargar.
    useTiempoReal(['ventas'], () => router.reload());
    const [modalPedido, setModalPedido] = useState(false);
    const { auth } = usePage<Props>().props;
    const esAdmin  = auth.user.rol?.es_admin ?? false;
    const empresa  = auth.user.empresa as { venta_edicion_minutos?: number; cajera_puede_anular?: boolean } | undefined;
    const editWindowMs = (Number(empresa?.venta_edicion_minutos ?? 3) || 0) * 60 * 1000;
    const cajeraPuedeAnular = empresa?.cajera_puede_anular ?? true;

    // Estados para anular desde el detalle.
    const [modalAnular, setModalAnular] = useState(false);
    // Restablecer una venta anulada por error (solo admin).
    const [modalRestablecer, setModalRestablecer] = useState(false);
    const [motivoRest, setMotivoRest] = useState('');
    const [restableciendo, setRestableciendo] = useState(false);
    const [errRest, setErrRest] = useState<Record<string, string>>({});
    function confirmarRestablecer() {
        setRestableciendo(true);
        setErrRest({});
        router.post(route('ventas.restablecer', venta.id), { motivo: motivoRest }, {
            preserveScroll: true,
            onSuccess: () => { setRestableciendo(false); setModalRestablecer(false); },
            onError:   (errs) => { setRestableciendo(false); setErrRest(errs as Record<string, string>); },
        });
    }
    const [motivoAnular, setMotivoAnular] = useState('');
    const [codigoAnular, setCodigoAnular] = useState('');
    const [errAnular, setErrAnular] = useState<Record<string, string>>({});
    const [anulando, setAnulando] = useState(false);

    // Evita doble impresión por re-render / StrictMode
    const autoImpreso = useRef(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function dentroPlazo(): boolean {
        if (editWindowMs <= 0) return false;
        return Date.now() - new Date(venta.created_at).getTime() < editWindowMs;
    }

    /**
     * Con la venta ya informada a SUNAT no se ofrece anular: el camino correcto
     * es una devolución total, que es la que emite la nota de crédito. Antes el
     * botón se ofrecía igual y el servidor lo rechazaba DESPUÉS de escribir el
     * motivo y de pedirle el código a un administrador.
     */
    function puedeAnular(): boolean {
        if (bloqueoFiscal) return false;
        if (venta.estado !== 'completada') return false;
        if (esAdmin) return true;
        return cajeraPuedeAnular;
    }

    function requiereCodigo(): boolean {
        return !esAdmin && cajeraPuedeAnular && !dentroPlazo();
    }

    function abrirAnular() {
        setMotivoAnular('');
        setCodigoAnular('');
        setErrAnular({});
        setModalAnular(true);
    }

    function confirmarAnular() {
        setAnulando(true);
        setErrAnular({});
        router.post(route('ventas.anular', venta.id), {
            motivo: motivoAnular,
            codigo_autorizacion: requiereCodigo() ? codigoAnular : undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => { setAnulando(false); setModalAnular(false); },
            onError:   (errs) => { setAnulando(false); setErrAnular(errs as Record<string, string>); },
        });
    }

    async function imprimir(auto = false) {
        if (!ticketImpresion) return;
        if (!(await agenteActivo())) {
            if (!auto) toast.error('El agente de impresión no está activo en esta PC (VentoryPrint)');
            return;
        }
        // Pedir el payload FRESCO al backend: el prop `ticketImpresion` se horneó
        // al cargar la página, así que si el cliente se editó después (p. ej. le
        // agregaron el celular) la reimpresión seguía saliendo con datos viejos.
        let data: TicketPayload;
        try {
            ({ data } = await axios.get<TicketPayload>(route('ventas.ticket', venta.id)));
        } catch {
            toast.error('No se pudo obtener el ticket actualizado.');
            return;
        }
        const ok = await imprimirTicket(data);
        if (ok) toast.success('Ticket enviado a la impresora');
        else    toast.error('No se pudo imprimir el ticket. Revisa VentoryPrint en esta PC.');
    }

    // Auto-imprimir una sola vez cuando la venta se acaba de registrar
    useEffect(() => {
        if (autoImpreso.current) return;
        if (flash?.imprimir_ticket === true && ticketImpresion) {
            autoImpreso.current = true;
            void imprimir(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Siempre por el modal: el servidor exige el motivo (y, con comprobante en
    // SUNAT, el modal dice qué nota de crédito se emitirá y cuánto vuelve).
    function anular() {
        abrirAnular();
    }

    const items    = (venta.items   ?? []) as VentaItem[];
    const pagos    = (venta.pagos   ?? []) as VentaPago[];
    const descLogs = (venta.descuentos_log ?? []) as DescuentoLog[];
    const anulada  = venta.estado === 'anulada';

    function clienteNombre() {
        if (!venta.cliente) return 'Cliente general';
        const c = venta.cliente as any;
        return c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim();
    }

    const fecha = new Date(venta.fecha_venta).toLocaleString('es-PE', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    return (
        <AppLayout title={`Venta ${venta.numero}`}>
            {/* ── Encabezado ─────────────────────────────────────────────────── */}
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0">
                    <Link href={route('ventas.index')}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold mb-2 hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <ArrowLeft size={15} /> Volver a ventas
                    </Link>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none tabular-nums" style={{ color: 'var(--vp-navy)' }}>
                            Venta {venta.numero}
                        </h1>
                        <EstadoVenta anulada={anulada} />
                        {/* ID interno: útil para buscar la venta al hacer una devolución. */}
                        <span className="text-[13px] tabular-nums px-2 py-0.5 rounded-md" title="ID interno de la venta (para devoluciones)"
                            style={{ color: 'var(--color-text-muted)', border: '1px solid var(--color-border)' }}>
                            ID {venta.id}
                        </span>
                    </div>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        {fecha}, a <strong style={{ color: 'var(--color-text)' }}>{clienteNombre()}</strong>
                        {(venta.user as any)?.name && <>. Atendió {(venta.user as any).name}</>}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        startContent={<Printer size={15} />}
                        onClick={() => void imprimir()}
                        disabled={!ticketImpresion || !ticketImpresion.token}
                        title={
                            !ticketImpresion || !ticketImpresion.token
                                ? 'La caja no tiene token de impresora configurado'
                                : 'Imprimir ticket en la ticketera de esta caja'
                        }
                    >
                        <span className="hidden sm:inline">Imprimir ticket</span>
                    </Button>
                    <a href={route('ventas.pdf', venta.id)} target="_blank" rel="noopener noreferrer">
                        <Button variant="secondary" size="sm" startContent={<FileText size={15} />} title="PDF de la venta. Si tiene factura o boleta electrónica emitida, abre la representación oficial de SUNAT">
                            PDF
                        </Button>
                    </a>
                    {puedeModificarPedido && (
                        <Button variant="primary" size="sm" startContent={<PackageOpen size={15} />} onClick={() => setModalPedido(true)}
                            title="Cambiar lo que el cliente dejó pendiente por entregar">
                            <span className="hidden sm:inline">Modificar pedido</span>
                        </Button>
                    )}
                    {puedeAnular() && !anulada && (
                        <Button variant="danger" size="sm" startContent={<XCircle size={15} />} onClick={anular}>
                            <span className="hidden sm:inline">Anular</span>
                        </Button>
                    )}
                    {anulada && restablecer && !restablecer.bloqueo && (
                        <Button variant="primary" size="sm" startContent={<RotateCcw size={15} />}
                            onClick={() => { setMotivoRest(''); setErrRest({}); setModalRestablecer(true); }}
                            title="Deshace la anulación: vuelve a descontar el stock y a registrar el dinero">
                            <span className="hidden sm:inline">Restablecer venta</span>
                        </Button>
                    )}
                    {/* La salida, en el mismo sitio donde antes estaba Anular:
                        quien viene a corregir la venta encuentra qué hacer, en
                        vez de un botón que le va a decir que no. */}
                    {!!bloqueoFiscal && !anulada && (
                        <Link href={route('devoluciones.create', { venta_id: venta.id })}>
                            <Button variant="primary" size="sm" startContent={<Undo2 size={15} />}
                                title="La corrección de una venta ya declarada se hace con una nota de crédito, y esa nace de una devolución">
                                <span className="hidden sm:inline">Devolver / Nota de crédito</span>
                            </Button>
                        </Link>
                    )}
                </div>
            </div>

            {/* Por qué esta venta ya no se toca, dicho ANTES de intentarlo. El
                texto viene del servidor: es el mismo que cortaría la operación. */}
            {!!bloqueoFiscal && !anulada && (
                <Callout variant="warning" title="Esta venta ya no se puede anular ni editar" className="mb-4">
                    {bloqueoFiscal}
                    <span className="block mt-1">
                        Para corregirla registra una <strong>devolución</strong>: si devuelves todo,
                        la nota de crédito anula el comprobante completo.
                    </span>
                </Callout>
            )}

            {/* Comprobante de fuera: no bloquea nada, pero conviene saberlo antes
                de anular, no después de que el cliente ya tenga el papel. */}
            {anulada && restablecer?.bloqueo && (
                <Callout variant="info" title="Esta venta anulada no se puede restablecer" className="mb-4">
                    {restablecer.bloqueo}
                </Callout>
            )}

            {!!avisoExterno && !anulada && (
                <Callout variant="info" title="Ojo: el comprobante de esta venta se emitió fuera del sistema" className="mb-4">
                    {avisoExterno}
                </Callout>
            )}

            {/* ── Resumen ────────────────────────────────────────────────────── */}
            <section className="rounded-2xl mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 overflow-hidden"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.14)' }}>
                <Resumen label={anulada ? 'Total (anulada)' : 'Total cobrado'}>
                    <p className={`font-display text-[28px] font-extrabold tabular-nums leading-tight ${anulada ? 'line-through' : ''}`}
                        style={{ color: anulada ? 'var(--color-text-muted)' : 'var(--vp-navy)' }}>
                        {fmtS(num(venta.total))}
                    </p>
                    <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                        IGV incluido {fmtS(num(venta.igv))}
                    </p>
                </Resumen>
                {utilidad ? (
                    <Resumen label="Ganaste">
                        <p className="font-display text-[28px] font-extrabold tabular-nums leading-tight"
                            style={{ color: utilidad.utilidad >= 0 ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)' }}>
                            {fmtS(utilidad.utilidad)}
                        </p>
                        <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                            {utilidad.margen !== null ? `${fmtPct(utilidad.margen)} de lo vendido` : 'sin venta neta'}
                            {utilidad.sin_costo > 0 && <span style={{ color: 'var(--vp-amber-ink)' }}>, falta un costo</span>}
                        </p>
                    </Resumen>
                ) : (
                    <Resumen label="Productos">
                        <p className="font-display text-[28px] font-extrabold tabular-nums leading-tight" style={{ color: 'var(--color-text)' }}>
                            {items.length}
                        </p>
                        <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                            {items.length === 1 ? 'línea en la venta' : 'líneas en la venta'}
                        </p>
                    </Resumen>
                )}
                <Resumen label="Pagó con">
                    {pagos.length === 0 ? (
                        <p className="text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>{venta.es_credito ? 'Crédito, sin pago inicial' : 'Sin pagos'}</p>
                    ) : (
                        <ul className="space-y-0.5">
                            {pagos.map(p => (
                                <li key={p.id} className="flex items-baseline justify-between gap-3 text-[15px]">
                                    <span className="font-semibold truncate" style={{ color: 'var(--color-text)' }}>{(p.metodo_pago as any)?.nombre ?? '—'}</span>
                                    <span className="tabular-nums font-bold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(num(p.monto))}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {venta.es_credito && num(venta.saldo_pendiente) > 0 && (
                        <p className="text-[13px] mt-1 font-semibold tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>
                            Debe {fmtS(num(venta.saldo_pendiente))}
                        </p>
                    )}
                </Resumen>
                <Resumen label="Comprobante">
                    <p className="text-[15px] font-bold" style={{ color: 'var(--color-text)' }}>{etiquetaComprobante(venta.tipo_comprobante as any)}</p>
                    <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                        {venta.numero_comprobante || `Caja ${(venta.caja as any)?.nombre ?? '—'}`}
                    </p>
                </Resumen>
            </section>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                {/* ── Columna principal ──────────────────────────────── */}
                <div className="lg:col-span-2 flex flex-col gap-4 min-w-0">
                    <SectionCard icon={ShoppingBag} title={`Productos (${items.length})`} flush>
                        {/* Tabla desktop */}
                        <div className="hidden sm:block overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                        <Th>Producto</Th><Th right>Cant.</Th><Th right>P. unit.</Th><Th right>Desc.</Th><Th right>Subtotal</Th>
                                        {utilidad && <><Th right>Costo</Th><Th right>Ganancia</Th></>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map(item => {
                                        const u = utilidad?.items[item.id];
                                        return (
                                            <tr key={item.id} style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                                <td className="px-4 py-3">
                                                    <p className="font-semibold" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</p>
                                                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{item.unidad_nombre}</p>
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums font-semibold" style={{ color: 'var(--color-text)' }}>{fmtCant(num(item.cantidad))}</td>
                                                <td className="px-3 py-3 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtS(num(item.precio_unitario))}</td>
                                                <td className="px-3 py-3 text-right tabular-nums">
                                                    {num(item.descuento_item) > 0
                                                        ? <span style={{ color: 'var(--vp-coral-ink)' }}>−{fmtS(num(item.descuento_item))} c/u</span>
                                                        : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums font-bold" style={{ color: 'var(--color-text)' }}>{fmtS(num(item.subtotal))}</td>
                                                {utilidad && (
                                                    <>
                                                        <td className="px-3 py-3 text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                                            {u?.sin_costo ? <span style={{ color: 'var(--vp-amber-ink)' }}>sin costo</span> : fmtS(u?.costo ?? 0)}
                                                        </td>
                                                        <td className="px-4 py-3 text-right tabular-nums whitespace-nowrap">
                                                            <GananciaLinea linea={u} />
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Móvil */}
                        <ul className="sm:hidden">
                            {items.map(item => {
                                const u = utilidad?.items[item.id];
                                return (
                                    <li key={item.id} className="px-4 py-3" style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                        <div className="flex items-baseline justify-between gap-3">
                                            <p className="text-sm font-semibold min-w-0" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</p>
                                            <span className="text-sm font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(num(item.subtotal))}</span>
                                        </div>
                                        <p className="text-[13px] mt-0.5 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                            {fmtCant(num(item.cantidad))} {item.unidad_nombre} a {fmtS(num(item.precio_unitario))}
                                            {num(item.descuento_item) > 0 && <span style={{ color: 'var(--vp-coral-ink)' }}>, desc. {fmtS(num(item.descuento_item))} c/u</span>}
                                        </p>
                                        {utilidad && <p className="text-[13px] mt-1"><GananciaLinea linea={u} conTexto /></p>}
                                    </li>
                                );
                            })}
                        </ul>

                        {/* Totales */}
                        <dl className="px-4 py-3 space-y-1.5 text-sm" style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'color-mix(in srgb, var(--vp-navy) 3%, var(--color-surface))' }}>
                            <FilaTotal label="Subtotal" valor={fmtS(num(venta.subtotal))} />
                            {num(venta.descuento_total) > 0 && (
                                <FilaTotal label="Descuento de la venta" valor={`−${fmtS(num(venta.descuento_total))}`} color="var(--vp-coral-ink)" />
                            )}
                            <FilaTotal label="IGV incluido" valor={fmtS(num(venta.igv))} tenue />
                            <div className="flex items-baseline justify-between pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                                <dt className="text-[15px] font-bold" style={{ color: 'var(--color-text)' }}>Total</dt>
                                <dd className="font-display text-xl font-extrabold tabular-nums" style={{ color: 'var(--vp-navy)' }}>{fmtS(num(venta.total))}</dd>
                            </div>
                        </dl>
                    </SectionCard>

                    {/* Historial de modificaciones del pedido pendiente */}
                    {modificacionesPedido.length > 0 && (
                        <SectionCard icon={History} title={`Modificaciones del pedido (${modificacionesPedido.length})`}>
                            <div className="space-y-2">
                                {modificacionesPedido.map(m => {
                                    const c = m.contexto ?? {};
                                    const l = c.liquidacion;
                                    const dinero = !l ? '' : l.tipo === 'cobro'
                                        ? `Cobrado ${fmtS((l.del_anticipo ?? 0) + (l.pago ?? 0))}${(l.al_credito ?? 0) > 0.009 ? `, al crédito ${fmtS(l.al_credito ?? 0)}` : ''}`
                                        : l.tipo === 'saldo_favor' ? `${fmtS(l.excedente ?? 0)} a favor (anticipo #${l.anticipo_id})`
                                        : l.tipo === 'devolver' ? `Devuelto ${fmtS(l.excedente ?? 0)}`
                                        : 'Sin diferencia de dinero';
                                    return (
                                        <div key={m.id} className="rounded-xl px-3 py-2.5 text-sm" style={{ border: '1px solid var(--color-border)' }}>
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="font-semibold" style={{ color: 'var(--color-text)' }}>{c.motivo ?? 'Modificación'}</span>
                                                <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                                    {new Date(m.created_at).toLocaleString('es-PE')}, {m.user_name ?? '—'}
                                                </span>
                                            </div>
                                            <p className="text-[13px] mt-0.5 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                                Total {fmtS(Number(c.total_antes ?? 0))} → {fmtS(Number(c.total_nuevo ?? 0))}. {dinero}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </SectionCard>
                    )}
                </div>

                {/* ── Columna lateral ────────────────────────────────── */}
                <div className="flex flex-col gap-4 min-w-0">
                    {utilidad && <CuantoGanaste u={utilidad} />}

                    <SectionCard icon={Receipt} title="Datos de la venta">
                        <dl>
                            <InfoRow label="Fecha" value={<span className="flex items-center gap-1.5"><Calendar size={13} className="opacity-50" />{new Date(venta.fecha_venta).toLocaleString('es-PE')}</span>} />
                            <InfoRow label="Cliente" value={<span className="flex items-center gap-1.5"><User size={13} className="opacity-50" />{clienteNombre()}</span>} />
                            <InfoRow label="Atendió" value={<span className="flex items-center gap-1.5"><UserCheck size={13} className="opacity-50" />{(venta.user as any)?.name ?? '—'}</span>} />
                            <InfoRow label="Caja" value={<span className="flex items-center gap-1.5"><Store size={13} className="opacity-50" />{(venta.caja as any)?.nombre ?? '—'}</span>} />
                            <InfoRow label="Tipo de venta" value={venta.es_credito ? 'Al crédito' : 'Al contado'} />
                            {venta.observacion && <InfoRow label="Observación" value={venta.observacion} />}
                        </dl>
                    </SectionCard>

                    {/* V11 — Comprobante electrónico. Las ventas `ticket` son notas
                        de venta internas: no se consulta nada y no se pinta nada. */}
                    {venta.tipo_comprobante !== 'ticket' && !['boleta_externa', 'factura_externa'].includes(venta.tipo_comprobante) && (
                        <BloqueComprobanteElectronico
                            ventaId={venta.id}
                            inicial={venta.comprobante_electronico ?? null}
                        />
                    )}

                    <SectionCard icon={CreditCard} title="Pagos">
                        {pagos.length === 0 ? (
                            <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                {venta.es_credito ? 'Venta al crédito sin pago inicial.' : 'Sin pagos registrados.'}
                            </p>
                        ) : (
                            <ul>
                                {pagos.map((pago, i) => (
                                    <li key={pago.id} className="flex justify-between items-start gap-3 py-2.5"
                                        style={{ borderTop: i ? '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' : undefined }}>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{(pago.metodo_pago as any)?.nombre ?? '—'}</p>
                                            {pago.referencia && <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Ref. {pago.referencia}</p>}
                                        </div>
                                        <div className="text-right">
                                            <p className="text-[15px] font-bold tabular-nums" style={{ color: 'var(--vp-mint-ink)' }}>{fmtS(num(pago.monto))}</p>
                                            {num(pago.vuelto) > 0 && (
                                                <p className="text-[13px] font-semibold tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>vuelto {fmtS(num(pago.vuelto))}</p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>

                    {descLogs.length > 0 && (
                        <SectionCard icon={Percent} title="Descuentos aplicados">
                            <ul>
                                {descLogs.map((log, i) => (
                                    <li key={log.id} className="py-2.5" style={{ borderTop: i ? '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' : undefined }}>
                                        <div className="flex justify-between items-baseline gap-3">
                                            <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{(log.concepto as any)?.nombre ?? '—'}</span>
                                            <span className="text-[15px] font-bold tabular-nums" style={{ color: 'var(--vp-coral-ink)' }}>−{fmtS(num(log.monto_descuento))}</span>
                                        </div>
                                        <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>Lo aplicó {(log.user as any)?.name ?? '—'}</p>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}
                </div>
            </div>

            <Modal
                isOpen={modalRestablecer}
                onClose={() => { if (!restableciendo) setModalRestablecer(false); }}
                title={`Restablecer venta ${venta.numero}`}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalRestablecer(false)} disabled={restableciendo}>Cancelar</Button>
                        <Button variant="primary" onClick={confirmarRestablecer} loading={restableciendo}>Restablecer venta</Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <Callout variant="info">
                        <p style={{ color: 'var(--color-text)' }}>
                            La venta vuelve a quedar como el día que se cobró: se descuenta otra vez el stock, se registra
                            de nuevo su dinero (con su fecha original){venta.es_credito ? ' y vuelve la deuda del cliente' : ''}.
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
            </Modal>

            <Modal
                isOpen={modalAnular}
                onClose={() => { if (!anulando) setModalAnular(false); }}
                title={`Anular venta ${venta.numero}`}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalAnular(false)} disabled={anulando}>Cancelar</Button>
                        <Button variant="danger" onClick={confirmarAnular} loading={anulando}>
                            {anulacionNc ? 'Anular con nota de crédito' : 'Anular venta'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    {anulacionNc ? (
                        <Callout variant="warning" title={`Se emitirá la nota de crédito de ${anulacionNc.comprobante}`}>
                            <p style={{ color: 'var(--color-text)' }}>
                                El comprobante ya está en SUNAT. Al anular se registra la devolución de todo, vuelve el stock
                                {anulacionNc.pagos.length > 0
                                    ? <>, sale de tu caja <strong>{anulacionNc.pagos.map(p => `${p.metodo} S/ ${p.monto.toFixed(2)}`).join(' + ')}</strong></>
                                    : null}
                                {anulacionNc.cxc > 0.009 ? <> y se cancela la deuda de <strong>S/ {anulacionNc.cxc.toFixed(2)}</strong></> : null}
                                {' '}y la nota de crédito se envía a SUNAT. Es una acción irreversible.
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
                            value={motivoAnular}
                            onChange={e => setMotivoAnular(e.target.value)}
                            disabled={anulando}
                            placeholder="Describe por qué se anula"
                            className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                        />
                        {errAnular.motivo && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errAnular.motivo}</p>}
                        {/* Rechazos del servidor que no son de un campo (p. ej. "tiene pendientes por entregar"). */}
                        {(errAnular.venta || errAnular.aviso) && (
                            <p className="text-sm mt-2 font-semibold" role="alert" style={{ color: 'var(--color-danger)' }}>{errAnular.venta || errAnular.aviso}</p>
                        )}
                    </div>

                    {requiereCodigo() && (
                        <div>
                            <label className="flex items-center gap-1.5 text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                                <KeyRound size={14} style={{ color: 'var(--color-warning)' }} />
                                Código de autorización
                            </label>
                            <p className="text-xs mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                {editWindowMs > 0
                                    ? `Pasaron más de ${Math.round(editWindowMs / 60000)} minutos. Pide a un administrador su código de autorización para anular.`
                                    : 'La empresa no permite a las cajeras anular ventas sin autorización. Pide a un administrador su código.'}
                            </p>
                            <input
                                type="password"
                                value={codigoAnular}
                                onChange={e => setCodigoAnular(e.target.value)}
                                disabled={anulando}
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
            </Modal>
            <ModalModificarPedido isOpen={modalPedido} onClose={() => setModalPedido(false)} ventaId={venta.id} />
        </AppLayout>
    );
}

/* ─── V11 · Comprobante electrónico (SUNAT) ──────────────────────────────────
   Solo se monta en ventas boleta/factura (las `ticket` ni siquiera preguntan).
   El estado se toma de GET /ventas/{id}/comprobante/estado y se refresca
   mientras siga en curso; el intervalo se corta al llegar a un estado final, al
   agotar el tope de consultas o al desmontar el componente. */

/** Cada cuánto se consulta el estado mientras el CPE sigue en curso. */
const POLL_MS = 10000;
/** Tope de consultas (~5 min): una pestaña olvidada no puede preguntar eternamente. */
const POLL_MAX = 30;

function BloqueComprobanteElectronico({ ventaId, inicial }: {
    ventaId: number;
    inicial: ComprobanteElectronico | null;
}) {
    const [ce, setCe] = useState<EstadoComprobanteResp | null>(
        inicial ? { tiene_comprobante: true, emitible: true, ...inicial } : null,
    );
    const [reintentando, setReint] = useState(false);
    // Cambia al reintentar para relanzar la consulta aunque el estado anterior
    // ya fuera final (rechazado → enviando).
    const [ciclo, setCiclo] = useState(0);

    useEffect(() => {
        let vivo = true;
        let consultas = 0;
        let timer: number | undefined;

        const detener = () => {
            if (timer !== undefined) { window.clearInterval(timer); timer = undefined; }
        };

        const consultar = async () => {
            try {
                const r = await axios.get(rutaComprobante.estado(ventaId));
                const data = r.data as EstadoComprobanteResp | null;
                if (!vivo || !data) return;
                setCe(data);
                const sigue = data.tiene_comprobante ? estadoEnCurso(data.estado) : data.emitible;
                if (!sigue) detener();
            } catch {
                // Un fallo puntual de red no debe romper la pantalla: se
                // reintenta en el siguiente tick.
            }
        };

        // Consulta inmediata: el detalle de la venta no precarga la relación.
        void consultar();

        timer = window.setInterval(() => {
            consultas += 1;
            if (consultas > POLL_MAX) { detener(); return; }
            void consultar();
        }, POLL_MS);

        return () => { vivo = false; detener(); };
    }, [ventaId, ciclo]);

    function reintentar() {
        if (reintentando) return;
        setReint(true);
        router.post(rutaComprobante.reintentar(ventaId), {}, {
            preserveScroll: true,
            onFinish: () => { setReint(false); setCiclo(c => c + 1); },
        });
    }

    // Aún consultando, o la venta no llegó a generar comprobante y tampoco va a
    // hacerlo (módulo apagado): no se pinta nada, la pantalla queda como hoy.
    if (!ce) return null;

    if (!ce.tiene_comprobante) {
        if (!ce.emitible) return null;
        return (
            <SectionCard icon={FileCheck2} title="Comprobante electrónico">
                <p className="text-xs leading-snug -mt-1" style={{ color: 'var(--color-text-muted)' }}>
                    {ce.mensaje ?? 'La emisión está en cola.'} La venta ya está cerrada; el número
                    del comprobante aparecerá acá en cuanto se emita.
                </p>
            </SectionCard>
        );
    }

    const meta    = metaEstado(ce.estado);
    const enCurso = estadoEnCurso(ce.estado);

    return (
        <SectionCard icon={FileCheck2} title="Comprobante electrónico">
            <div className="flex flex-col gap-3 -mt-1">
                {/* Número + tipo + estado */}
                <div className="flex items-start justify-between gap-2 flex-wrap">
                    <div className="min-w-0">
                        <p className="font-mono text-base font-bold leading-tight" style={{ color: 'var(--color-text)' }}>
                            {ce.numero}
                        </p>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            {etiquetaTipoSunat(ce.tipo)}
                        </p>
                    </div>
                    <span
                        className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold flex-shrink-0"
                        style={{
                            backgroundColor: `color-mix(in srgb, ${meta.color} 14%, transparent)`,
                            color: meta.color,
                        }}
                    >
                        {enCurso && (
                            <span
                                className="inline-block h-1.5 w-1.5 rounded-full animate-pulse"
                                style={{ backgroundColor: meta.color }}
                            />
                        )}
                        {meta.label}
                    </span>
                </div>

                {/* Qué significa el estado (la cajera no debe creer que falló algo) */}
                {meta.detalle && (
                    <p className="text-xs leading-snug" style={{ color: 'var(--color-text-muted)' }}>
                        {meta.detalle}
                    </p>
                )}

                {/* Respuesta de SUNAT / error del envío */}
                {ce.sunat_descripcion && (
                    <div
                        className="rounded-lg px-3 py-2 text-xs leading-snug"
                        style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                    >
                        <span className="font-semibold">SUNAT{ce.sunat_codigo ? ` ${ce.sunat_codigo}` : ''}:</span>{' '}
                        {ce.sunat_descripcion}
                    </div>
                )}
                {ce.error && (
                    <div
                        className="rounded-lg px-3 py-2 text-xs leading-snug"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)',
                            color: 'var(--color-danger)',
                        }}
                    >
                        {ce.error}
                    </div>
                )}

                {ce.hash_cpe && (
                    <p className="text-[11px] font-mono break-all" style={{ color: 'var(--color-text-muted)' }}>
                        Hash: {ce.hash_cpe}
                    </p>
                )}

                {/* Acciones */}
                <div className="flex gap-2 flex-wrap">
                    {(ce.tiene_pdf ?? true) && (
                        <a href={rutaComprobante.pdf(ventaId)} target="_blank" rel="noopener noreferrer">
                            <Button variant="secondary" size="sm" startContent={<Download size={14} />}>
                                Descargar PDF
                            </Button>
                        </a>
                    )}
                    {(ce.puede_reintentar ?? puedeReintentar(ce.estado)) && (
                        <Button
                            variant="primary"
                            size="sm"
                            startContent={<RefreshCw size={14} className={reintentando ? 'animate-spin' : ''} />}
                            onClick={reintentar}
                            disabled={reintentando}
                        >
                            {reintentando ? 'Reintentando…' : 'Reintentar'}
                        </Button>
                    )}
                </div>
            </div>
        </SectionCard>
    );
}
