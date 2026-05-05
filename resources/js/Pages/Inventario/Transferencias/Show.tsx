import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ArrowLeft, Pencil, Send, PackageCheck, Ban } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import type { PageProps } from '@/types';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }
type EstadoTransfer = 'borrador' | 'enviada' | 'recibida' | 'anulada';

interface Detalle {
    id: number;
    producto_id: number;
    unidad_medida_id: number;
    cantidad_enviada: string;
    factor_conversion: string;
    cantidad_base_enviada: string;
    cantidad_recibida: string | null;
    cantidad_base_recibida: string | null;
    diferencia_base: string | null;
    costo_unitario: string;
    observacion: string | null;
    producto: { id: number; nombre: string; codigo: string | null };
    unidad_medida: { nombre: string; abreviatura: string };
}

interface Transferencia {
    id: number;
    fecha: string;
    estado: EstadoTransfer;
    almacen_origen: Almacen;
    almacen_destino: Almacen;
    user: { name: string };
    user_envio: { name: string } | null;
    user_recepcion: { name: string } | null;
    fecha_envio: string | null;
    fecha_recepcion: string | null;
    observacion_envio: string | null;
    observacion_recepcion: string | null;
    detalles: Detalle[];
}

interface Props extends PageProps {
    transferencia: Transferencia;
}

const ESTADO_LABEL: Record<EstadoTransfer, string> = {
    borrador: 'Borrador',
    enviada:  'Enviada (en tránsito)',
    recibida: 'Recibida',
    anulada:  'Anulada',
};
const ESTADO_VARIANT: Record<EstadoTransfer, 'warning' | 'primary' | 'success' | 'secondary'> = {
    borrador: 'warning',
    enviada:  'primary',
    recibida: 'success',
    anulada:  'secondary',
};

export default function TransferenciaShow({ transferencia: t }: Props) {
    const { flash } = usePage<Props>().props;
    const [recibirOpen, setRecibirOpen] = useState(false);
    const [enviarOpen, setEnviarOpen] = useState(false);
    const [anularOpen, setAnularOpen] = useState(false);
    const [obsRecepcion, setObsRecepcion] = useState('');
    const [cantidades, setCantidades] = useState<Record<number, string>>(
        Object.fromEntries(t.detalles.map(d => [d.id, d.cantidad_enviada]))
    );

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function recibir() {
        const cantidadesNumber: Record<number, number> = {};
        for (const [k, v] of Object.entries(cantidades)) {
            cantidadesNumber[Number(k)] = parseFloat(v) || 0;
        }
        router.post(route('inventario.transferencias.recibir', t.id), {
            cantidades: cantidadesNumber,
            observacion_recepcion: obsRecepcion,
        }, {
            onSuccess: () => setRecibirOpen(false),
        });
    }

    function enviar() {
        router.post(route('inventario.transferencias.enviar', t.id), {}, {
            onSuccess: () => setEnviarOpen(false),
        });
    }

    function anular() {
        router.post(route('inventario.transferencias.anular', t.id), {}, {
            onSuccess: () => setAnularOpen(false),
        });
    }

    return (
        <AppLayout title={`Transferencia #${t.id}`}>
            <PageHeader
                title={`Transferencia #${t.id}`}
                subtitle={`${t.almacen_origen.nombre} → ${t.almacen_destino.nombre}${t.almacen_destino.local ? ' · ' + t.almacen_destino.local.nombre : ''}`}
                backHref={route('inventario.transferencias.index')}
                actions={
                    <div className="flex gap-2">
                        {t.estado !== 'anulada' && (
                            <Button variant="ghost" onClick={() => router.visit(route('inventario.transferencias.edit', t.id))}>
                                <Pencil size={14} className="mr-1" />Editar
                            </Button>
                        )}
                        {t.estado === 'borrador' && (
                            <Button variant="primary" onClick={() => setEnviarOpen(true)}>
                                <Send size={14} className="mr-1" />Enviar
                            </Button>
                        )}
                        {t.estado === 'enviada' && (
                            <Button variant="success" onClick={() => setRecibirOpen(true)}>
                                <PackageCheck size={14} className="mr-1" />Confirmar recepción
                            </Button>
                        )}
                        {(t.estado === 'enviada' || t.estado === 'recibida') && (
                            <Button variant="danger" onClick={() => setAnularOpen(true)}>
                                <Ban size={14} className="mr-1" />Anular
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="space-y-6 max-w-6xl">
                {/* Cabecera */}
                <section className="rounded-2xl border p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Estado</p>
                        <Badge variant={ESTADO_VARIANT[t.estado]}>{ESTADO_LABEL[t.estado]}</Badge>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Fecha</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{t.fecha}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Origen</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{t.almacen_origen.nombre}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Destino</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{t.almacen_destino.nombre}</p>
                    </div>

                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Creada por</p>
                        <p className="text-sm" style={{ color: 'var(--color-text)' }}>{t.user.name}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Enviada por</p>
                        <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                            {t.user_envio?.name ?? <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                            {t.fecha_envio && <span className="block text-xs" style={{ color: 'var(--color-text-muted)' }}>{new Date(t.fecha_envio).toLocaleString('es-PE')}</span>}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Recibida por</p>
                        <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                            {t.user_recepcion?.name ?? <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                            {t.fecha_recepcion && <span className="block text-xs" style={{ color: 'var(--color-text-muted)' }}>{new Date(t.fecha_recepcion).toLocaleString('es-PE')}</span>}
                        </p>
                    </div>
                    <div>{/* spacer */}</div>

                    {t.observacion_envio && (
                        <div className="col-span-2 md:col-span-4">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación de envío</p>
                            <p className="text-sm" style={{ color: 'var(--color-text)' }}>{t.observacion_envio}</p>
                        </div>
                    )}
                    {t.observacion_recepcion && (
                        <div className="col-span-2 md:col-span-4">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación de recepción</p>
                            <p className="text-sm" style={{ color: 'var(--color-text)' }}>{t.observacion_recepcion}</p>
                        </div>
                    )}
                </section>

                {/* Detalles */}
                <section className="rounded-2xl border p-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Productos</h3>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium">Producto</th>
                                    <th className="text-left py-2 px-2 font-medium">Unidad</th>
                                    <th className="text-right py-2 px-2 font-medium">Enviado</th>
                                    <th className="text-right py-2 px-2 font-medium">Recibido</th>
                                    <th className="text-right py-2 px-2 font-medium">Diferencia</th>
                                    <th className="text-left py-2 px-2 font-medium">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                {t.detalles.map(d => {
                                    const diff = d.diferencia_base ? parseFloat(d.diferencia_base) : null;
                                    return (
                                        <tr key={d.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <td className="py-2 px-2">
                                                <div className="font-medium" style={{ color: 'var(--color-text)' }}>{d.producto.nombre}</div>
                                                {d.producto.codigo && (
                                                    <div className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{d.producto.codigo}</div>
                                                )}
                                            </td>
                                            <td className="py-2 px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{d.unidad_medida.abreviatura}</td>
                                            <td className="py-2 px-2 text-right tabular-nums">{parseFloat(d.cantidad_enviada).toFixed(2)}</td>
                                            <td className="py-2 px-2 text-right tabular-nums">
                                                {d.cantidad_recibida !== null
                                                    ? parseFloat(d.cantidad_recibida).toFixed(2)
                                                    : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                            </td>
                                            <td className="py-2 px-2 text-right tabular-nums">
                                                {diff === null ? (
                                                    <span style={{ color: 'var(--color-text-muted)' }}>—</span>
                                                ) : diff === 0 ? (
                                                    <span style={{ color: 'var(--color-success)' }}>0</span>
                                                ) : (
                                                    <span style={{ color: diff < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>
                                                        {diff > 0 ? '+' : ''}{diff.toFixed(2)}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {d.observacion ?? '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {/* Modal: Confirmar recepción */}
            <Modal isOpen={recibirOpen} onClose={() => setRecibirOpen(false)}
                title="Confirmar recepción de transferencia" size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setRecibirOpen(false)}>Cancelar</Button>
                        <Button variant="success" onClick={recibir}>
                            <PackageCheck size={14} className="mr-1" />Confirmar recepción
                        </Button>
                    </>
                }>
                <div className="space-y-4">
                    <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                        Declara las cantidades realmente recibidas. Si difieren de lo enviado, se registrará la diferencia (pérdida/ganancia en tránsito).
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium">Producto</th>
                                    <th className="text-right py-2 px-2 font-medium">Enviado</th>
                                    <th className="text-right py-2 px-2 font-medium">Recibido</th>
                                </tr>
                            </thead>
                            <tbody>
                                {t.detalles.map(d => (
                                    <tr key={d.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                        <td className="py-2 px-2">
                                            <div className="font-medium" style={{ color: 'var(--color-text)' }}>{d.producto.nombre}</div>
                                            <div className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{d.unidad_medida.nombre}</div>
                                        </td>
                                        <td className="py-2 px-2 text-right tabular-nums">{parseFloat(d.cantidad_enviada).toFixed(2)}</td>
                                        <td className="py-2 px-2 w-32">
                                            <input type="number" step="0.0001" min="0"
                                                value={cantidades[d.id]}
                                                onChange={e => setCantidades({ ...cantidades, [d.id]: e.target.value })}
                                                className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>Observación de recepción</label>
                        <textarea rows={2} value={obsRecepcion} onChange={e => setObsRecepcion(e.target.value)}
                            className="w-full rounded-xl border px-3 py-2 text-sm resize-none"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                    </div>
                </div>
            </Modal>

            {/* Modal: enviar */}
            <Modal isOpen={enviarOpen} onClose={() => setEnviarOpen(false)} title="Enviar transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEnviarOpen(false)}>Cancelar</Button>
                        <Button variant="primary" onClick={enviar}><Send size={14} className="mr-1" />Enviar</Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se descontará el stock del almacén origen. El destino aún NO recibirá stock — debe confirmarse en este local.
                </p>
            </Modal>

            {/* Modal: anular */}
            <Modal isOpen={anularOpen} onClose={() => setAnularOpen(false)} title="Anular transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnularOpen(false)}>Cancelar</Button>
                        <Button variant="danger" onClick={anular}><Ban size={14} className="mr-1" />Anular</Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se revertirán todos los movimientos de stock aplicados (devolverá al origen, descontará del destino si ya estaba recibida).
                </p>
            </Modal>
        </AppLayout>
    );
}
