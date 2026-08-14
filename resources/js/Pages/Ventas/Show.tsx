import { useEffect, useRef, useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import {
    ArrowLeft, XCircle, Receipt, User, ShoppingBag,
    CreditCard, Percent, Calendar, Store, UserCheck, Printer,
    FileCheck2, Download, RefreshCw, KeyRound, AlertTriangle,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import { agenteActivo, imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
import {
    rutaComprobante, metaEstado, estadoEnCurso, puedeReintentar, etiquetaTipoSunat, etiquetaComprobante,
    type EstadoComprobanteResp,
} from '@/lib/comprobanteElectronico';
import type { PageProps, Venta, VentaItem, VentaPago, DescuentoLog, ComprobanteElectronico } from '@/types';

interface Props extends PageProps {
    venta: Venta;
    ticketImpresion?: TicketPayload | null;
}

function SectionCard({ icon: Icon, title, children }: { icon: React.ElementType; title: string; children: React.ReactNode }) {
    return (
        <div
            className="rounded-xl overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center gap-2 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <Icon size={14} style={{ color: 'var(--color-primary)' }} />
                <h3 className="text-xs font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                    {title}
                </h3>
            </div>
            <div className="p-4">
                {children}
            </div>
        </div>
    );
}

function InfoRow({ label, value, muted }: { label: string; value: React.ReactNode; muted?: boolean }) {
    return (
        <div className="flex items-baseline justify-between py-2 text-sm" style={{ borderBottom: '1px solid color-mix(in srgb, var(--color-border) 50%, transparent)' }}>
            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</span>
            <span className={`font-medium text-right ${muted ? 'text-xs' : ''}`} style={{ color: muted ? 'var(--color-text-muted)' : 'var(--color-text)' }}>
                {value}
            </span>
        </div>
    );
}

export default function VentasShow({ venta, flash, ticketImpresion }: Props) {
    const { auth } = usePage<Props>().props;
    const esAdmin  = auth.user.rol?.es_admin ?? false;
    const empresa  = auth.user.empresa as { venta_edicion_minutos?: number; cajera_puede_anular?: boolean } | undefined;
    const editWindowMs = (Number(empresa?.venta_edicion_minutos ?? 3) || 0) * 60 * 1000;
    const cajeraPuedeAnular = empresa?.cajera_puede_anular ?? true;

    // Estados para anular desde el detalle.
    const [modalAnular, setModalAnular] = useState(false);
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

    function puedeAnular(): boolean {
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

    function anular() {
        if (requiereCodigo()) {
            abrirAnular();
            return;
        }
        if (!confirm('¿Confirmas la anulación de esta venta? Esta acción restaurará el stock.')) return;
        confirmarAnular();
    }

    const items    = (venta.items   ?? []) as VentaItem[];
    const pagos    = (venta.pagos   ?? []) as VentaPago[];
    const descLogs = (venta.descuentos_log ?? []) as DescuentoLog[];

    function clienteNombre() {
        if (!venta.cliente) return 'Cliente general';
        const c = venta.cliente as any;
        return c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim();
    }

    return (
        <AppLayout title={`Venta ${venta.numero}`}>
            <PageHeader
                title={
                    <div className="flex items-center gap-3 flex-wrap">
                        <span>Venta</span>
                        <span
                            className="font-mono text-sm px-2.5 py-1 rounded-lg"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                                color: 'var(--color-primary)',
                            }}
                        >
                            {venta.numero}
                        </span>
                        {/* ID interno — útil para buscar la venta al hacer una devolución. */}
                        <span
                            className="font-mono text-xs px-2 py-1 rounded-lg"
                            style={{
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text-muted)',
                                border: '1px solid var(--color-border)',
                            }}
                            title="ID interno de la venta (para devoluciones)"
                        >
                            ID: {venta.id}
                        </span>
                        <Badge variant={venta.estado === 'completada' ? 'success' : 'danger'}>
                            {venta.estado === 'completada' ? 'Completada' : 'Anulada'}
                        </Badge>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        <Link href={route('ventas.index')}>
                            <Button variant="ghost" startContent={<ArrowLeft size={15} />} size="sm">
                                <span className="hidden sm:inline">Volver</span>
                            </Button>
                        </Link>
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
                        {puedeAnular() && venta.estado !== 'anulada' && (
                            <Button variant="danger" size="sm" startContent={<XCircle size={15} />} onClick={anular}>
                                <span className="hidden sm:inline">Anular</span>
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {/* ── Columna principal ──────────────────────────────── */}
                <div className="lg:col-span-2 flex flex-col gap-4">
                    {/* Datos generales */}
                    <SectionCard icon={Receipt} title="Datos de la venta">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                            <InfoRow label="Número" value={<span className="font-mono">{venta.numero}</span>} />
                            <InfoRow label="ID (para devolución)" value={<span className="font-mono">{venta.id}</span>} />
                            <InfoRow label="Fecha" value={
                                <span className="flex items-center gap-1">
                                    <Calendar size={12} className="opacity-50" />
                                    {new Date(venta.fecha_venta).toLocaleString('es-PE')}
                                </span>
                            } />
                            <InfoRow label="Comprobante" value={
                                <span className="capitalize">
                                    {etiquetaComprobante(venta.tipo_comprobante as any)}
                                    {venta.numero_comprobante && (
                                        <span className="block text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                            {venta.numero_comprobante}
                                        </span>
                                    )}
                                </span>
                            } />
                            <InfoRow label="Cliente" value={
                                <span className="flex items-center gap-1">
                                    <User size={12} className="opacity-50" />
                                    {clienteNombre()}
                                </span>
                            } />
                            <InfoRow label="Vendedor" value={
                                <span className="flex items-center gap-1">
                                    <UserCheck size={12} className="opacity-50" />
                                    {(venta.user as any)?.name ?? '—'}
                                </span>
                            } />
                            <InfoRow label="Caja" value={
                                <span className="flex items-center gap-1">
                                    <Store size={12} className="opacity-50" />
                                    {(venta.caja as any)?.nombre ?? '—'}
                                </span>
                            } />
                            {venta.observacion && (
                                <div className="sm:col-span-2">
                                    <InfoRow label="Observación" value={venta.observacion} />
                                </div>
                            )}
                        </div>
                    </SectionCard>

                    {/* Items */}
                    <SectionCard icon={ShoppingBag} title={`Productos (${items.length})`}>
                        {/* Tabla desktop */}
                        <div className="hidden sm:block -mx-4 -mb-4">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '2px solid var(--color-border)' }}>
                                        {['Producto', 'Presentación', 'Cant.', 'P. Unit.', 'Desc.', 'Subtotal'].map(h => (
                                            <th key={h} className="px-4 py-2.5 text-left text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item, idx) => (
                                        <tr
                                            key={item.id}
                                            style={{
                                                borderBottom: idx < items.length - 1 ? '1px solid var(--color-border)' : undefined,
                                                backgroundColor: idx % 2 === 0 ? 'transparent' : 'var(--color-bg)',
                                            }}
                                        >
                                            <td className="px-4 py-2.5 font-medium" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>{item.unidad_nombre}</td>
                                            <td className="px-4 py-2.5 font-semibold" style={{ color: 'var(--color-text)' }}>{parseFloat(item.cantidad).toFixed(0)}</td>
                                            <td className="px-4 py-2.5" style={{ color: 'var(--color-text)' }}>S/ {parseFloat(item.precio_unitario).toFixed(2)}</td>
                                            <td className="px-4 py-2.5">
                                                {parseFloat(item.descuento_item) > 0 ? (
                                                    <span className="font-medium" style={{ color: 'var(--color-danger)' }}>
                                                        -S/ {parseFloat(item.descuento_item).toFixed(2)}
                                                    </span>
                                                ) : (
                                                    <span style={{ color: 'var(--color-text-muted)' }}>—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2.5 font-bold" style={{ color: 'var(--color-text)' }}>
                                                S/ {parseFloat(item.subtotal).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Cards móvil */}
                        <div className="sm:hidden flex flex-col gap-2 -mx-1">
                            {items.map(item => (
                                <div
                                    key={item.id}
                                    className="rounded-lg p-3"
                                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                                >
                                    <div className="flex justify-between items-start">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>
                                                {item.producto_nombre}
                                            </p>
                                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                                {item.unidad_nombre} · S/ {parseFloat(item.precio_unitario).toFixed(2)} × {parseFloat(item.cantidad).toFixed(0)}
                                                {parseFloat(item.descuento_item) > 0 && (
                                                    <span className="ml-1" style={{ color: 'var(--color-danger)' }}>
                                                        -S/ {parseFloat(item.descuento_item).toFixed(2)}/u
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        <span className="text-sm font-bold flex-shrink-0 ml-2" style={{ color: 'var(--color-primary)' }}>
                                            S/ {parseFloat(item.subtotal).toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </SectionCard>
                </div>

                {/* ── Columna lateral ────────────────────────────────── */}
                <div className="flex flex-col gap-4">
                    {/* Resumen financiero */}
                    <div
                        className="rounded-xl overflow-hidden"
                        style={{ border: '1px solid var(--color-border)' }}
                    >
                        <div
                            className="px-4 py-3"
                            style={{
                                background: 'linear-gradient(135deg, var(--color-primary), var(--color-primary-hover))',
                                color: '#fff',
                            }}
                        >
                            <p className="text-xs font-medium opacity-80">Total de la venta</p>
                            <p className="text-2xl font-bold mt-0.5">S/ {parseFloat(venta.total).toFixed(2)}</p>
                        </div>
                        <div className="p-4 space-y-0" style={{ backgroundColor: 'var(--color-surface)' }}>
                            <InfoRow label="Subtotal" value={`S/ ${parseFloat(venta.subtotal).toFixed(2)}`} />
                            {parseFloat(venta.descuento_total) > 0 && (
                                <InfoRow label="Descuento" value={
                                    <span style={{ color: 'var(--color-danger)' }}>-S/ {parseFloat(venta.descuento_total).toFixed(2)}</span>
                                } />
                            )}
                            <InfoRow label="IGV (18%)" value={`S/ ${parseFloat(venta.igv).toFixed(2)}`} />
                        </div>
                    </div>

                    {/* V11 — Comprobante electrónico. Las ventas `ticket` son notas
                        de venta internas: no se consulta nada y no se pinta nada. */}
                    {venta.tipo_comprobante !== 'ticket' && !['boleta_externa', 'factura_externa'].includes(venta.tipo_comprobante) && (
                        <BloqueComprobanteElectronico
                            ventaId={venta.id}
                            inicial={venta.comprobante_electronico ?? null}
                        />
                    )}

                    {/* Pagos */}
                    <SectionCard icon={CreditCard} title="Pagos">
                        <div className="flex flex-col gap-2 -mt-1">
                            {pagos.map(pago => (
                                <div
                                    key={pago.id}
                                    className="flex justify-between items-center text-sm py-2"
                                    style={{ borderBottom: '1px solid color-mix(in srgb, var(--color-border) 50%, transparent)' }}
                                >
                                    <div>
                                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                            {(pago.metodo_pago as any)?.nombre ?? '—'}
                                        </p>
                                        {pago.referencia && (
                                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                Ref: {pago.referencia}
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold" style={{ color: 'var(--color-success)' }}>
                                            S/ {parseFloat(pago.monto).toFixed(2)}
                                        </p>
                                        {parseFloat(pago.vuelto) > 0 && (
                                            <p className="text-xs font-medium" style={{ color: 'var(--color-warning)' }}>
                                                Vuelto: S/ {parseFloat(pago.vuelto).toFixed(2)}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </SectionCard>

                    {/* Logs de descuento */}
                    {descLogs.length > 0 && (
                        <SectionCard icon={Percent} title="Descuentos aplicados">
                            <div className="flex flex-col gap-2 -mt-1">
                                {descLogs.map(log => (
                                    <div
                                        key={log.id}
                                        className="py-2"
                                        style={{ borderBottom: '1px solid color-mix(in srgb, var(--color-border) 50%, transparent)' }}
                                    >
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                                {(log.concepto as any)?.nombre ?? '—'}
                                            </span>
                                            <span className="text-sm font-bold" style={{ color: 'var(--color-danger)' }}>
                                                -S/ {parseFloat(log.monto_descuento).toFixed(2)}
                                            </span>
                                        </div>
                                        <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                            Por: {(log.user as any)?.name ?? '—'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </SectionCard>
                    )}
                </div>
            </div>

            <Modal
                isOpen={modalAnular}
                onClose={() => { if (!anulando) setModalAnular(false); }}
                title={`Anular venta ${venta.numero}`}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalAnular(false)} disabled={anulando}>Cancelar</Button>
                        <Button variant="danger" onClick={confirmarAnular} loading={anulando}>Anular venta</Button>
                    </>
                }
            >
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
                            value={motivoAnular}
                            onChange={e => setMotivoAnular(e.target.value)}
                            disabled={anulando}
                            placeholder="Describe por qué se anula"
                            className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                        />
                        {errAnular.motivo && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errAnular.motivo}</p>}
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
                    <p className="text-[10px] font-mono break-all" style={{ color: 'var(--color-text-muted)' }}>
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
