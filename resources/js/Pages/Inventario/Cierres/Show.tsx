import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import toast from 'react-hot-toast';
import { CheckCircle, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface CierreItem {
    id: number;
    producto_id: number;
    stock_sistema: string;
    stock_declarado: string;
    diferencia: string;
    observacion: string | null;
    producto: { id: number; nombre: string; codigo: string | null };
}

interface Cierre {
    id: number;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    observacion: string | null;
    total_items: number;
    total_diferencias: number;
    turno_id: number | null;
    almacen: { id: number; nombre: string; local?: { nombre: string } | null };
    user: { id: number; name: string };
    items: CierreItem[];
}

interface Props extends PageProps {
    cierre: Cierre;
}

export default function CierreShow({ cierre }: Props) {
    const { flash } = usePage<Props>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function confirmar() {
        if (!confirm('Al confirmar se ajustará el stock automáticamente. ¿Continuar?')) return;
        router.post(route('inventario.cierres.confirmar', cierre.id));
    }

    function eliminar() {
        if (!confirm('¿Eliminar este cierre en borrador? Esta acción no se puede deshacer.')) return;
        router.delete(route('inventario.cierres.destroy', cierre.id));
    }

    return (
        <AppLayout title={`Cierre #${cierre.id}`}>
            <PageHeader
                title={`Cierre de inventario #${cierre.id}`}
                subtitle={`${cierre.almacen.nombre}${cierre.almacen.local ? ' · ' + cierre.almacen.local.nombre : ''} — ${cierre.fecha}`}
                backHref={route('inventario.cierres.index')}
                actions={
                    cierre.estado === 'borrador' && (
                        <div className="flex gap-2">
                            <Button variant="ghost" onClick={eliminar}>
                                <Trash2 size={15} className="mr-1" /> Eliminar
                            </Button>
                            <Button onClick={confirmar}>
                                <CheckCircle size={15} className="mr-1" /> Confirmar y ajustar stock
                            </Button>
                        </div>
                    )
                }
            />

            <div className="space-y-6 max-w-6xl">
                <section className="rounded-2xl border p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Estado</p>
                        <Badge variant={cierre.estado === 'confirmado' ? 'success' : 'secondary'}>
                            {cierre.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                        </Badge>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Responsable</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{cierre.user.name}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Productos</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{cierre.total_items}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Con diferencia</p>
                        {cierre.total_diferencias > 0
                            ? <Badge variant="warning">{cierre.total_diferencias}</Badge>
                            : <span className="font-medium" style={{ color: 'var(--color-text)' }}>0</span>}
                    </div>
                    {cierre.observacion && (
                        <div className="col-span-2 md:col-span-4">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación</p>
                            <p className="text-sm" style={{ color: 'var(--color-text)' }}>{cierre.observacion}</p>
                        </div>
                    )}
                    {cierre.turno_id && (
                        <div className="col-span-2 md:col-span-4">
                            <Badge variant="primary">Cierre asociado al turno #{cierre.turno_id}</Badge>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border p-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Detalle por producto</h3>

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
                                {cierre.items.map(it => {
                                    const diff = parseFloat(it.diferencia);
                                    return (
                                        <tr key={it.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <td className="py-2 px-2">
                                                <div className="font-medium" style={{ color: 'var(--color-text)' }}>{it.producto.nombre}</div>
                                                {it.producto.codigo && (
                                                    <div className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{it.producto.codigo}</div>
                                                )}
                                            </td>
                                            <td className="py-2 px-2 text-right tabular-nums">{parseFloat(it.stock_sistema).toFixed(2)}</td>
                                            <td className="py-2 px-2 text-right tabular-nums">{parseFloat(it.stock_declarado).toFixed(2)}</td>
                                            <td className="py-2 px-2 text-right tabular-nums">
                                                {diff === 0 ? (
                                                    <span style={{ color: 'var(--color-success)' }}>0</span>
                                                ) : (
                                                    <span style={{ color: diff < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>
                                                        {diff > 0 ? '+' : ''}{diff.toFixed(2)}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                {it.observacion ?? '—'}
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
