import { useEffect } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ArrowLeft, XCircle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { PageProps, Venta, VentaItem, VentaPago, DescuentoLog } from '@/types';

interface Props extends PageProps {
    venta: Venta;
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-baseline justify-between py-1.5 border-b text-sm" style={{ borderColor: 'var(--color-border)' }}>
            <span style={{ color: 'var(--color-text-muted)' }}>{label}</span>
            <span className="font-medium text-right" style={{ color: 'var(--color-text)' }}>{value}</span>
        </div>
    );
}

export default function VentasShow({ venta, flash }: Props) {
    const { auth } = usePage<Props>().props;
    const esAdmin  = auth.user.rol?.es_admin ?? false;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function anular() {
        if (!confirm('¿Confirmas la anulación de esta venta? Esta acción restaurará el stock.')) return;
        router.post(route('ventas.anular', venta.id));
    }

    const items    = (venta.items   ?? []) as VentaItem[];
    const pagos    = (venta.pagos   ?? []) as VentaPago[];
    const descLogs = (venta.descuentos_log ?? []) as DescuentoLog[];

    return (
        <AppLayout title={`Venta ${venta.numero}`}>
            <PageHeader
                title={`Venta ${venta.numero}`}
                actions={
                    <div className="flex gap-2">
                        <Link href={route('ventas.index')}>
                            <Button variant="ghost" startContent={<ArrowLeft size={15} />} size="sm">Volver</Button>
                        </Link>
                        {esAdmin && venta.estado !== 'anulada' && (
                            <Button variant="danger" size="sm" startContent={<XCircle size={15} />} onClick={anular}>
                                Anular venta
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {/* Columna principal */}
                <div className="lg:col-span-2 flex flex-col gap-4">
                    {/* Datos generales */}
                    <div
                        className="rounded-lg p-4"
                        style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
                    >
                        <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Datos de la venta</h3>
                        <InfoRow label="Número" value={<span className="font-mono">{venta.numero}</span>} />
                        <InfoRow label="Fecha" value={new Date(venta.fecha_venta).toLocaleString('es-PE')} />
                        <InfoRow label="Estado" value={
                            <Badge variant={venta.estado === 'completada' ? 'success' : 'danger'}>
                                {venta.estado === 'completada' ? 'Completada' : 'Anulada'}
                            </Badge>
                        } />
                        <InfoRow label="Comprobante" value={<span className="capitalize">{venta.tipo_comprobante}</span>} />
                        <InfoRow label="Cliente" value={
                            venta.cliente
                                ? `${(venta.cliente as any).nombre} ${(venta.cliente as any).apellido ?? ''}`
                                : 'Cliente general'
                        } />
                        <InfoRow label="Vendedor" value={(venta.user as any)?.name ?? '—'} />
                        <InfoRow label="Caja" value={(venta.caja as any)?.nombre ?? '—'} />
                        {venta.observacion && <InfoRow label="Observación" value={venta.observacion} />}
                    </div>

                    {/* Items */}
                    <div
                        className="rounded-lg overflow-hidden"
                        style={{ border: '1px solid var(--color-border)' }}
                    >
                        <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <h3 className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Productos</h3>
                        </div>
                        <table className="w-full text-sm" style={{ backgroundColor: 'var(--color-surface)' }}>
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                    {['Producto', 'Unidad', 'Cant.', 'P. Unit.', 'Desc.', 'Subtotal'].map(h => (
                                        <th key={h} className="px-3 py-2 text-left text-xs font-semibold" style={{ color: 'var(--color-text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map(item => (
                                    <tr key={item.id} style={{ borderBottom: '1px solid var(--color-border)' }}>
                                        <td className="px-3 py-2" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</td>
                                        <td className="px-3 py-2" style={{ color: 'var(--color-text-muted)' }}>{item.unidad_nombre}</td>
                                        <td className="px-3 py-2" style={{ color: 'var(--color-text)' }}>{parseFloat(item.cantidad).toFixed(2)}</td>
                                        <td className="px-3 py-2" style={{ color: 'var(--color-text)' }}>S/ {parseFloat(item.precio_unitario).toFixed(2)}</td>
                                        <td className="px-3 py-2" style={{ color: parseFloat(item.descuento_item) > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                            {parseFloat(item.descuento_item) > 0 ? `-S/ ${parseFloat(item.descuento_item).toFixed(2)}` : '—'}
                                        </td>
                                        <td className="px-3 py-2 font-semibold" style={{ color: 'var(--color-text)' }}>
                                            S/ {parseFloat(item.subtotal).toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Columna lateral */}
                <div className="flex flex-col gap-4">
                    {/* Resumen financiero */}
                    <div
                        className="rounded-lg p-4"
                        style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
                    >
                        <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Resumen</h3>
                        <InfoRow label="Subtotal" value={`S/ ${parseFloat(venta.subtotal).toFixed(2)}`} />
                        {parseFloat(venta.descuento_total) > 0 && (
                            <InfoRow label="Descuento" value={<span className="text-red-500">-S/ {parseFloat(venta.descuento_total).toFixed(2)}</span>} />
                        )}
                        <InfoRow label="IGV (18%)" value={`S/ ${parseFloat(venta.igv).toFixed(2)}`} />
                        <div className="flex items-baseline justify-between pt-2 text-base font-bold">
                            <span style={{ color: 'var(--color-text)' }}>Total</span>
                            <span style={{ color: 'var(--color-primary)' }}>S/ {parseFloat(venta.total).toFixed(2)}</span>
                        </div>
                    </div>

                    {/* Pagos */}
                    <div
                        className="rounded-lg p-4"
                        style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
                    >
                        <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Pagos</h3>
                        {pagos.map(pago => (
                            <div key={pago.id} className="flex justify-between text-sm py-1.5 border-b" style={{ borderColor: 'var(--color-border)' }}>
                                <div>
                                    <p style={{ color: 'var(--color-text)' }}>{(pago.metodo_pago as any)?.nombre ?? '—'}</p>
                                    {pago.referencia && <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{pago.referencia}</p>}
                                </div>
                                <div className="text-right">
                                    <p className="font-semibold" style={{ color: 'var(--color-text)' }}>S/ {parseFloat(pago.monto).toFixed(2)}</p>
                                    {parseFloat(pago.vuelto) > 0 && (
                                        <p className="text-xs" style={{ color: 'var(--color-success)' }}>Vuelto: S/ {parseFloat(pago.vuelto).toFixed(2)}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Logs de descuento */}
                    {descLogs.length > 0 && (
                        <div
                            className="rounded-lg p-4"
                            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
                        >
                            <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Descuentos aplicados</h3>
                            {descLogs.map(log => (
                                <div key={log.id} className="text-xs py-1.5 border-b" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}>
                                    <p className="font-medium">{(log.concepto as any)?.nombre ?? '—'}</p>
                                    <p style={{ color: 'var(--color-text-muted)' }}>
                                        S/ {parseFloat(log.monto_descuento).toFixed(2)} · {(log.user as any)?.name ?? '—'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
