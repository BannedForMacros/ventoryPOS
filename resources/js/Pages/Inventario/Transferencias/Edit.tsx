import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Trash2, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; local?: { nombre: string } | null; }

type EstadoTransfer = 'borrador' | 'enviada' | 'recibida' | 'anulada';

interface DetalleData {
    id: number;
    producto_id: number;
    unidad_medida_id: number;
    cantidad_enviada: string;
    factor_conversion: string;
    cantidad_recibida: string | null;
    observacion: string | null;
}

interface TransferenciaData {
    id: number;
    almacen_origen_id: number;
    almacen_destino_id: number;
    fecha: string;
    estado: EstadoTransfer;
    observacion_envio: string | null;
    observacion_recepcion: string | null;
    detalles: DetalleData[];
}

interface Props extends PageProps {
    transferencia: TransferenciaData;
    almacenesOrigen: Almacen[];
    almacenesDestino: Almacen[];
    productos: Producto[];
}

interface DetalleRow {
    id?: number;
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    cantidad_recibida: string;
    observacion: string;
}

export default function TransferenciaEdit({ transferencia: t, almacenesOrigen, almacenesDestino, productos }: Props) {
    const [origenId, setOrigenId]       = useState<number | ''>(t.almacen_origen_id);
    const [destinoId, setDestinoId]     = useState<number | ''>(t.almacen_destino_id);
    const [fecha, setFecha]             = useState(t.fecha);
    const [obsEnvio, setObsEnvio]       = useState(t.observacion_envio ?? '');
    const [obsRecepcion, setObsRecepcion] = useState(t.observacion_recepcion ?? '');
    const [detalles, setDetalles]       = useState<DetalleRow[]>(
        t.detalles.map(d => ({
            id: d.id,
            producto_id: d.producto_id,
            unidad_medida_id: d.unidad_medida_id,
            cantidad: String(d.cantidad_enviada),
            factor_conversion: String(d.factor_conversion),
            cantidad_recibida: d.cantidad_recibida ? String(d.cantidad_recibida) : '',
            observacion: d.observacion ?? '',
        }))
    );
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [processing, setProcessing]   = useState(false);

    function unidadesDeProducto(productoId: number | ''): ProductoUnidad[] {
        if (!productoId) return [];
        return productos.find(p => p.id === productoId)?.unidades ?? [];
    }

    function setDetalle(i: number, field: keyof DetalleRow, value: string | number) {
        setDetalles(prev => {
            const updated = prev.map((d, idx) => idx !== i ? d : { ...d, [field]: value });
            if (field === 'producto_id') {
                const base = unidadesDeProducto(value as number).find(u => u.es_base);
                updated[i].unidad_medida_id  = base?.unidad_medida_id ?? '';
                updated[i].factor_conversion = '1';
            }
            if (field === 'unidad_medida_id') {
                const unidad = unidadesDeProducto(updated[i].producto_id).find(u => u.unidad_medida_id === Number(value));
                updated[i].factor_conversion = unidad ? String(unidad.factor_conversion) : '1';
            }
            return updated;
        });
    }

    function addDetalle()    { setDetalles(d => [...d, { producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', cantidad_recibida: '', observacion: '' }]); }
    function removeDetalle(i: number) { setDetalles(d => d.filter((_, idx) => idx !== i)); }

    function submit() {
        setProcessing(true);

        // Si está en estado recibida y se editó cantidad_recibida, mandarla
        const cantidadesRecibidas: Record<number, number> = {};
        if (t.estado === 'recibida') {
            detalles.forEach(d => {
                if (d.id && d.cantidad_recibida) {
                    cantidadesRecibidas[d.id] = parseFloat(d.cantidad_recibida) || 0;
                }
            });
        }

        router.put(route('inventario.transferencias.update', t.id), {
            almacen_origen_id:  origenId,
            almacen_destino_id: destinoId,
            fecha,
            observacion_envio: obsEnvio,
            observacion_recepcion: obsRecepcion,
            detalles: detalles.map(d => ({
                producto_id:       d.producto_id,
                unidad_medida_id:  d.unidad_medida_id,
                cantidad:          d.cantidad,
                factor_conversion: d.factor_conversion,
                observacion:       d.observacion,
            })),
            cantidades_recibidas: cantidadesRecibidas,
        }, {
            onSuccess: () => setProcessing(false),
            onError:   (e) => { setErrors(e); setProcessing(false); },
        });
    }

    const origenFijo = almacenesOrigen.length === 1 ? almacenesOrigen[0] : null;

    return (
        <AppLayout title={`Editar transferencia #${t.id}`}>
            <PageHeader
                title={`Editar transferencia #${t.id}`}
                subtitle="Edición flexible — los movimientos de stock se recalcularán al guardar"
                backHref={route('inventario.transferencias.show', t.id)}
                actions={<Badge variant="primary">{t.estado.toUpperCase()}</Badge>}
            />

            <div className="space-y-6 max-w-5xl">
                {(t.estado === 'enviada' || t.estado === 'recibida') && (
                    <div className="flex items-start gap-2 rounded-xl px-4 py-3 text-sm"
                        style={{ backgroundColor: 'rgba(234,179,8,0.08)', border: '1px solid rgba(234,179,8,0.3)' }}>
                        <AlertTriangle size={16} className="mt-0.5" style={{ color: '#b45309' }} />
                        <div style={{ color: 'var(--color-text)' }}>
                            <strong>Atención:</strong> al guardar, el sistema revierte los movimientos de stock previos y los reaplica con los nuevos valores. Si no hay stock suficiente en el origen, la operación será rechazada.
                        </div>
                    </div>
                )}

                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Datos
                    </h2>
                    <div className="grid grid-cols-2 gap-4">
                        {origenFijo ? (
                            <div>
                                <p className="text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>Origen (central)</p>
                                <div className="rounded-xl border px-3 py-2 text-sm"
                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                    {origenFijo.nombre}
                                </div>
                            </div>
                        ) : (
                            <Select label="Origen (central)" required value={origenId}
                                onChange={v => setOrigenId(v === '' ? '' : Number(v))}
                                options={almacenesOrigen.map(a => ({ value: a.id, label: a.nombre }))}
                                error={errors.almacen_origen_id} />
                        )}
                        <Select label="Destino (local)" required value={destinoId}
                            onChange={v => setDestinoId(v === '' ? '' : Number(v))}
                            options={almacenesDestino.map(a => ({ value: a.id, label: `${a.nombre}${a.local ? ' · ' + a.local.nombre : ''}` }))}
                            error={errors.almacen_destino_id} />
                        <Input label="Fecha" required type="date" value={fecha} onChange={e => setFecha(e.target.value)} />
                    </div>
                    <Input label="Observación de envío" value={obsEnvio} onChange={e => setObsEnvio(e.target.value)} />
                    {t.estado === 'recibida' && (
                        <Input label="Observación de recepción" value={obsRecepcion} onChange={e => setObsRecepcion(e.target.value)} />
                    )}
                </section>

                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Detalles</h2>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar línea
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium w-1/3">Producto</th>
                                    <th className="text-left py-2 px-2 font-medium">Unidad</th>
                                    <th className="text-right py-2 px-2 font-medium">Enviado</th>
                                    {t.estado === 'recibida' && (
                                        <th className="text-right py-2 px-2 font-medium">Recibido</th>
                                    )}
                                    <th className="text-left py-2 px-2 font-medium">Obs.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((d, i) => {
                                    const unidades = unidadesDeProducto(d.producto_id);
                                    return (
                                        <tr key={i} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <td className="py-2 px-2">
                                                <Select value={d.producto_id}
                                                    onChange={v => setDetalle(i, 'producto_id', v === '' ? '' : Number(v))}
                                                    options={productos.map(p => ({ value: p.id, label: `${p.codigo ? `[${p.codigo}] ` : ''}${p.nombre}` }))}
                                                    placeholder="Seleccionar" />
                                            </td>
                                            <td className="py-2 px-2">
                                                <Select value={d.unidad_medida_id}
                                                    onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                                    options={unidades.map(u => ({ value: u.unidad_medida_id, label: u.unidad_medida?.nombre ?? '' }))}
                                                    placeholder="—" disabled={!d.producto_id} />
                                            </td>
                                            <td className="py-2 px-2 w-28">
                                                <input type="number" step="0.0001" min="0"
                                                    value={d.cantidad}
                                                    onChange={e => setDetalle(i, 'cantidad', e.target.value)}
                                                    className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                            </td>
                                            {t.estado === 'recibida' && (
                                                <td className="py-2 px-2 w-28">
                                                    <input type="number" step="0.0001" min="0"
                                                        value={d.cantidad_recibida}
                                                        onChange={e => setDetalle(i, 'cantidad_recibida', e.target.value)}
                                                        className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                                </td>
                                            )}
                                            <td className="py-2 px-2 w-44">
                                                <input type="text" value={d.observacion}
                                                    onChange={e => setDetalle(i, 'observacion', e.target.value)}
                                                    placeholder="—"
                                                    className="w-full rounded-lg border px-2 py-1 text-xs"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                            </td>
                                            <td className="py-2 px-1 w-10">
                                                {detalles.length > 1 && (
                                                    <button type="button" onClick={() => removeDetalle(i)}
                                                        className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}>
                                                        <Trash2 size={14} />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {errors.stock && (
                        <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{errors.stock}</p>
                    )}
                </section>

                <div className="flex gap-3">
                    <Button variant="ghost" onClick={() => router.visit(route('inventario.transferencias.show', t.id))}>
                        Cancelar
                    </Button>
                    <Button loading={processing} onClick={submit}>
                        Guardar cambios
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
