import { useEffect } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    ArrowLeft, XCircle, Receipt, User, ShoppingBag,
    CreditCard, Percent, Calendar, Store, UserCheck,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { PageProps, Venta, VentaItem, VentaPago, DescuentoLog } from '@/types';

interface Props extends PageProps {
    venta: Venta;
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
                        {esAdmin && venta.estado !== 'anulada' && (
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
                            <InfoRow label="Fecha" value={
                                <span className="flex items-center gap-1">
                                    <Calendar size={12} className="opacity-50" />
                                    {new Date(venta.fecha_venta).toLocaleString('es-PE')}
                                </span>
                            } />
                            <InfoRow label="Comprobante" value={<span className="capitalize">{venta.tipo_comprobante}</span>} />
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
                                        {['Producto', 'Unidad', 'Cant.', 'P. Unit.', 'Desc.', 'Subtotal'].map(h => (
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
        </AppLayout>
    );
}
