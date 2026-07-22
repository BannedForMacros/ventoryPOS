import { useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import toast from 'react-hot-toast';
import { Save } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';

interface ItemEdit {
    producto_id: number;
    codigo: string | null;
    nombre: string;
    stock_sistema: number;   // limpio, fresco (sin este cierre)
    stock_declarado: number;
    diferencia: number;
    costo: number;
    observacion: string | null;
}

interface Cierre { id: number; fecha: string; estado: 'borrador' | 'confirmado'; observacion: string | null; almacen: string | null; }

interface Props extends PageProps {
    cierre: Cierre;
    items: ItemEdit[];
    precarga: boolean;
}

const num = (n: number) => Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });

export default function CierreEdit({ cierre, items, precarga }: Props) {
    const { flash } = usePage<Props>().props;
    const [filas, setFilas] = useState(() =>
        items.map(it => ({ ...it, declarado: String(it.stock_declarado), obs: it.observacion ?? '' })),
    );
    const [observacion, setObservacion] = useState(cierre.observacion ?? '');
    const [filtro, setFiltro] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const setDeclarado = (pid: number, v: string) => setFilas(prev => prev.map(f => f.producto_id === pid ? { ...f, declarado: v } : f));
    const setObs       = (pid: number, v: string) => setFilas(prev => prev.map(f => f.producto_id === pid ? { ...f, obs: v } : f));

    const dif = (f: typeof filas[number]) => f.declarado === '' ? null : +(parseFloat(f.declarado) - f.stock_sistema).toFixed(4);
    const conDif = filas.filter(f => { const d = dif(f); return d !== null && d !== 0; }).length;

    const visibles = useMemo(() => filas.filter(f =>
        !filtro || f.nombre.toLowerCase().includes(filtro.toLowerCase()) || (f.codigo ?? '').toLowerCase().includes(filtro.toLowerCase()),
    ), [filas, filtro]);

    function guardar() {
        const itemsArr = filas.filter(f => f.declarado !== '').map(f => ({
            producto_id: f.producto_id, stock_declarado: f.declarado, observacion: f.obs || null,
        }));
        if (itemsArr.length === 0) { toast.error('Declara al menos un producto.'); return; }
        setSaving(true);
        router.put(route('inventario.cierres.update', cierre.id), { observacion, items: itemsArr } as any, {
            onFinish: () => setSaving(false),
        });
    }

    return (
        <AppLayout title={`Editar cierre #${cierre.id}`}>
            <PageHeader
                title={`Editar cierre #${cierre.id}`}
                subtitle={`${cierre.almacen ?? ''} — ${cierre.fecha}`}
                backHref={route('inventario.cierres.show', cierre.id)}
                actions={<Button loading={saving} onClick={guardar}><Save size={15} className="mr-1" /> Guardar cambios</Button>}
            />

            <div className="space-y-5 max-w-6xl">
                {cierre.estado === 'confirmado' && (
                    <Callout variant="info">
                        Este cierre ya estaba confirmado. El stock del sistema se muestra <strong>ya sin el efecto de este cierre</strong> (recalculado con las ventas/entradas actuales). Al guardar, se reasienta el stock y las diferencias se recalculan solas — sin pasos manuales.
                    </Callout>
                )}

                <section className="rounded-2xl border p-5 space-y-3" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <Input label="Observación" value={observacion} onChange={e => setObservacion(e.target.value)} />
                    <div className="flex flex-wrap gap-3 items-end">
                        <div className="flex-1 min-w-[200px]">
                            <Input label="Buscar producto" placeholder="Nombre o código" value={filtro} onChange={e => setFiltro(e.target.value)} />
                        </div>
                        <Badge variant="primary">{visibles.length} productos</Badge>
                        {conDif > 0 && <Badge variant="warning">{conDif} con diferencia</Badge>}
                    </div>
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        {precarga ? 'Corrige solo lo que contaste distinto; el resto queda sin diferencia.' : 'Ingresa la cantidad real de los productos.'}
                    </p>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium">Producto</th>
                                    <th className="text-right py-2 px-2 font-medium">Stock sistema</th>
                                    <th className="text-right py-2 px-2 font-medium">Stock declarado</th>
                                    <th className="text-right py-2 px-2 font-medium">Diferencia</th>
                                    <th className="text-left py-2 px-2 font-medium">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visibles.map(f => {
                                    const d = dif(f);
                                    return (
                                        <tr key={f.producto_id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <td className="py-2 px-2">
                                                <div className="font-medium" style={{ color: 'var(--color-text)' }}>{f.nombre}</div>
                                                {f.codigo && <div className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{f.codigo}</div>}
                                            </td>
                                            <td className="py-2 px-2 text-right tabular-nums">{num(f.stock_sistema)}</td>
                                            <td className="py-2 px-2 w-32">
                                                <input type="number" step="0.0001" min="0" value={f.declarado}
                                                    onChange={e => setDeclarado(f.producto_id, e.target.value)}
                                                    className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                            </td>
                                            <td className="py-2 px-2 text-right tabular-nums">
                                                {d === null ? <span style={{ color: 'var(--color-text-muted)' }}>—</span>
                                                    : d === 0 ? <span style={{ color: 'var(--color-success)' }}>0</span>
                                                    : <span style={{ color: d < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>{d > 0 ? '+' : ''}{d.toFixed(2)}</span>}
                                            </td>
                                            <td className="py-2 px-2 w-40">
                                                <input type="text" value={f.obs} onChange={e => setObs(f.producto_id, e.target.value)} placeholder="—"
                                                    className="w-full rounded-lg border px-2 py-1 text-xs"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
