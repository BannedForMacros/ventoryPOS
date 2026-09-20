import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import toast from 'react-hot-toast';
import { CheckCircle, XCircle, Ban, RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';

type EstadoDev = 'pendiente' | 'aprobada' | 'rechazada' | 'completada' | 'anulada';
type EstadoNC = 'no_aplica' | 'pendiente' | 'esperando' | 'emitida' | 'fallida';

interface Detalle {
    id: number;
    producto_id: number;
    cantidad: string;
    precio_unitario: string;
    subtotal: string;
    estado_producto: string;
    restock: boolean;
    observacion: string | null;
    producto: { nombre: string; codigo: string | null };
    motivo: { nombre: string } | null;
}

interface Pago {
    id: number;
    monto: string;
    referencia: string | null;
    metodo_pago: { nombre: string };
}

interface Devolucion {
    id: number;
    numero: string | null;
    fecha: string;
    estado: EstadoDev;
    forma_reembolso: string;
    monto_devolucion: string;
    monto_reembolso: string;
    observacion: string | null;
    requiere_aprobacion: boolean;
    fue_aprobada: boolean;
    fecha_aprobacion: string | null;
    observacion_aprobacion: string | null;
    venta: { id: number; numero: string; total: string; fecha_venta: string };
    /**
     * En qué quedó la nota de crédito ante SUNAT. `null` = devolución anterior a
     * que esto se registrara: no se sabe, y decir "no aplica" sería inventarlo.
     */
    nota_credito_estado: EstadoNC | null;
    nota_credito_numero: string | null;
    nota_credito_error: string | null;
    motivo: { nombre: string };
    user: { name: string };
    user_aprobacion: { name: string } | null;
    detalles: Detalle[];
    pagos: Pago[];
}

interface Props extends PageProps {
    devolucion: Devolucion;
}

const ESTADO_VARIANT: Record<EstadoDev, 'warning' | 'primary' | 'success' | 'secondary' | 'danger'> = {
    pendiente: 'warning', aprobada: 'primary', rechazada: 'danger',
    completada: 'success', anulada: 'secondary',
};
const FORMA_LABEL: Record<string, string> = {
    efectivo: 'Efectivo', mismo_metodo: 'Mismo método', vale_credito: 'Vale / Crédito',
    cambio_producto: 'Cambio de producto', sin_reembolso: 'Sin reembolso',
};

/**
 * Cómo se cuenta cada estado de la nota de crédito.
 *
 * `esperando` NO es un problema y se dice así: una boleta no llega a SUNAT hasta
 * el Resumen Diario de las 23:55, de modo que casi toda devolución del día pasa
 * por ahí. Pintarla de rojo enseñaría a ignorar el rojo, que es justo lo que no
 * puede pasar con `fallida` —ahí sí hay un importe declarado de más—.
 */
const NC_INFO: Record<EstadoNC, { variant: 'info' | 'success' | 'warning' | 'danger'; titulo: string; texto: string }> = {
    no_aplica: { variant: 'info', titulo: 'Sin nota de crédito', texto: 'Esta venta no tenía comprobante enviado a SUNAT, así que no hay nada que acreditar.' },
    pendiente: { variant: 'info', titulo: 'Nota de crédito en camino', texto: 'Se está emitiendo. En unos minutos aparecerá aquí el resultado.' },
    esperando: { variant: 'info', titulo: 'Nota de crédito en espera', texto: 'El comprobante todavía no llegó a SUNAT. Las boletas viajan en el Resumen Diario de las 23:55; se reintenta solo.' },
    emitida:   { variant: 'success', titulo: 'Nota de crédito emitida', texto: 'SUNAT ya tiene la nota de crédito de esta devolución.' },
    fallida:   { variant: 'danger', titulo: 'La nota de crédito NO se emitió', texto: 'La devolución está hecha y es correcta, pero SUNAT sigue viendo declarado el importe original de la venta. Hay que emitirla para que la declaración cuadre.' },
};

export default function DevolucionShow({ devolucion: d }: Props) {
    const { flash, auth } = usePage<Props>().props;
    const esAdmin = (auth.user as { rol?: { es_admin?: boolean } } | undefined)?.rol?.es_admin ?? false;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const nc = d.nota_credito_estado ? NC_INFO[d.nota_credito_estado] : null;
    // Solo lo que quedó a medias se puede reintentar: una emitida ya existe y
    // una que no aplica no tiene nada que emitir.
    const puedeReintentarNC = esAdmin
        && ['pendiente', 'esperando', 'fallida'].includes(d.nota_credito_estado ?? '');

    function reintentarNC() {
        router.post(route('devoluciones.nota-credito.reintentar', d.id), {}, { preserveScroll: true });
    }

    return (
        <AppLayout title={`Devolución #${d.id}`}>
            <PageHeader
                title={d.numero ?? `Devolución #${d.id}`}
                subtitle={`Origen: venta ${d.venta.numero}`}
                backHref={route('devoluciones.index')}
                actions={
                    <div className="flex gap-2">
                        {d.estado === 'pendiente' && esAdmin && (
                            <>
                                <Button variant="success" onClick={() => router.post(route('devoluciones.aprobar', d.id))}>
                                    <CheckCircle size={14} className="mr-1" />Aprobar
                                </Button>
                                <Button variant="danger" onClick={() => router.post(route('devoluciones.rechazar', d.id))}>
                                    <XCircle size={14} className="mr-1" />Rechazar
                                </Button>
                            </>
                        )}
                        {(d.estado === 'completada' || d.estado === 'aprobada') && (
                            <Button variant="danger" onClick={() => router.post(route('devoluciones.anular', d.id))}>
                                <Ban size={14} className="mr-1" />Anular
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="space-y-6 max-w-6xl">
                {/* El estado ante SUNAT va ARRIBA del todo y solo cuando hay algo
                    que contar: una nota fallida deja la declaración descuadrada y
                    antes solo se veía en el log, donde nadie la iba a buscar. */}
                {nc && (
                    <Callout
                        variant={nc.variant}
                        title={nc.titulo}
                        aside={d.nota_credito_numero ?? undefined}
                    >
                        {nc.texto}
                        {d.nota_credito_estado === 'fallida' && d.nota_credito_error && (
                            <span className="block mt-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Motivo: {d.nota_credito_error}
                            </span>
                        )}
                        {puedeReintentarNC && (
                            <span className="block mt-3">
                                <Button variant="primary" onClick={reintentarNC}>
                                    <RefreshCw size={14} className="mr-1" />Reintentar nota de crédito
                                </Button>
                            </span>
                        )}
                    </Callout>
                )}

                <section className="rounded-2xl border p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Estado</p>
                        <Badge variant={ESTADO_VARIANT[d.estado]}>{d.estado.toUpperCase()}</Badge>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Fecha</p>
                        <p className="font-medium">{new Date(d.fecha).toLocaleString('es-PE')}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Motivo</p>
                        <p className="font-medium">{d.motivo.nombre}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Reembolso</p>
                        <Badge variant="primary">{FORMA_LABEL[d.forma_reembolso] ?? d.forma_reembolso}</Badge>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Monto devuelto</p>
                        <p className="font-bold">S/ {Number(d.monto_devolucion).toFixed(2)}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Monto reembolsado</p>
                        <p className="font-bold">S/ {Number(d.monto_reembolso).toFixed(2)}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Cajero</p>
                        <p className="font-medium">{d.user.name}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Aprobada por</p>
                        <p className="font-medium">{d.user_aprobacion?.name ?? <span style={{ color: 'var(--color-text-muted)' }}>—</span>}</p>
                    </div>
                    {d.observacion && (
                        <div className="col-span-full">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación</p>
                            <p>{d.observacion}</p>
                        </div>
                    )}
                    {d.observacion_aprobacion && (
                        <div className="col-span-full">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación de aprobación</p>
                            <p>{d.observacion_aprobacion}</p>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border p-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h3 className="text-sm font-semibold mb-3">Productos devueltos</h3>
                    <table className="w-full text-sm">
                        <thead style={{ color: 'var(--color-text-muted)' }}>
                            <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                <th className="text-left py-2 px-2 font-medium">Producto</th>
                                <th className="text-right py-2 px-2 font-medium">Cantidad</th>
                                <th className="text-right py-2 px-2 font-medium">Precio</th>
                                <th className="text-right py-2 px-2 font-medium">Subtotal</th>
                                <th className="text-left py-2 px-2 font-medium">Estado</th>
                                <th className="text-center py-2 px-2 font-medium">Restock</th>
                            </tr>
                        </thead>
                        <tbody>
                            {d.detalles.map(it => (
                                <tr key={it.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <td className="py-2 px-2 font-medium">{it.producto.nombre}</td>
                                    <td className="py-2 px-2 text-right tabular-nums">{Number(it.cantidad).toFixed(2)}</td>
                                    <td className="py-2 px-2 text-right tabular-nums">S/ {Number(it.precio_unitario).toFixed(2)}</td>
                                    <td className="py-2 px-2 text-right tabular-nums">S/ {Number(it.subtotal).toFixed(2)}</td>
                                    <td className="py-2 px-2">{it.estado_producto}</td>
                                    <td className="py-2 px-2 text-center">{it.restock ? '✓' : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                {d.pagos.length > 0 && (
                    <section className="rounded-2xl border p-5"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <h3 className="text-sm font-semibold mb-3">Pagos del reembolso</h3>
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium">Método</th>
                                    <th className="text-left py-2 px-2 font-medium">Referencia</th>
                                    <th className="text-right py-2 px-2 font-medium">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                {d.pagos.map(p => (
                                    <tr key={p.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                        <td className="py-2 px-2">{p.metodo_pago.nombre}</td>
                                        <td className="py-2 px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{p.referencia ?? '—'}</td>
                                        <td className="py-2 px-2 text-right tabular-nums">S/ {Number(p.monto).toFixed(2)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
