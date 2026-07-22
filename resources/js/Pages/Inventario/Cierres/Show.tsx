import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import { CheckCircle, Trash2, Pencil, Ban, TrendingDown, TrendingUp } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Input from '@/Components/UI/Input';
import type { PageProps } from '@/types';

interface CierreItem {
    producto_id: number;
    codigo: string | null;
    nombre: string;
    stock_sistema: number;
    stock_declarado: number;
    diferencia: number;
    costo: number;
    valor_diferencia: number;
    observacion: string | null;
}

interface Cierre {
    id: number;
    fecha: string;
    estado: 'borrador' | 'confirmado' | 'anulado';
    observacion: string | null;
    almacen: string;
    local: string | null;
    usuario: string;
    total_items: number;
    total_diferencias: number;
}

interface Resumen { faltante: number; sobrante: number; neto: number; con_dif: number; }

interface Props extends PageProps {
    cierre: Cierre;
    items: CierreItem[];
    resumen: Resumen;
}

const sol = (n: number) => `S/ ${Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const num = (n: number) => Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });

export default function CierreShow({ cierre, items, resumen }: Props) {
    const { flash } = usePage<Props>().props;
    const [anulando, setAnulando] = useState(false);
    const [motivo, setMotivo]     = useState('');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const anulado = cierre.estado === 'anulado';

    return (
        <AppLayout title={`Cierre #${cierre.id}`}>
            <PageHeader
                title={`Cierre de inventario #${cierre.id}`}
                subtitle={`${cierre.almacen}${cierre.local ? ' · ' + cierre.local : ''} — ${cierre.fecha}`}
                backHref={route('inventario.cierres.index')}
                actions={!anulado && (
                    <div className="flex gap-2">
                        {cierre.estado === 'borrador' && (
                            <Button variant="ghost" onClick={() => { if (confirm('¿Eliminar este cierre en borrador?')) router.delete(route('inventario.cierres.destroy', cierre.id)); }}>
                                <Trash2 size={15} className="mr-1" /> Eliminar
                            </Button>
                        )}
                        <Button variant="ghost" onClick={() => router.visit(route('inventario.cierres.edit', cierre.id))}>
                            <Pencil size={15} className="mr-1" /> Editar / actualizar
                        </Button>
                        {cierre.estado === 'borrador' ? (
                            <Button onClick={() => { if (confirm('Al confirmar se ajustará el stock a lo declarado. ¿Continuar?')) router.post(route('inventario.cierres.confirmar', cierre.id)); }}>
                                <CheckCircle size={15} className="mr-1" /> Confirmar y ajustar stock
                            </Button>
                        ) : (
                            <Button variant="danger" onClick={() => { setMotivo(''); setAnulando(true); }}>
                                <Ban size={15} className="mr-1" /> Anular
                            </Button>
                        )}
                    </div>
                )}
            />

            <div className="space-y-6 max-w-6xl">
                {/* Cabecera */}
                <section className="rounded-2xl border p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Estado</p>
                        <Badge variant={cierre.estado === 'confirmado' ? 'success' : anulado ? 'danger' : 'secondary'}>
                            {cierre.estado === 'confirmado' ? 'Confirmado' : anulado ? 'Anulado' : 'Borrador'}
                        </Badge>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Responsable</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{cierre.usuario}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Productos</p>
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>{cierre.total_items}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Con diferencia</p>
                        {resumen.con_dif > 0
                            ? <Badge variant="warning">{resumen.con_dif}</Badge>
                            : <span className="font-medium" style={{ color: 'var(--color-success)' }}>Todo cuadra</span>}
                    </div>
                    {cierre.observacion && (
                        <div className="col-span-2 md:col-span-4">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación</p>
                            <p className="text-sm" style={{ color: 'var(--color-text)' }}>{cierre.observacion}</p>
                        </div>
                    )}
                </section>

                {/* Panel de discrepancias valorizado (auditoría del dueño) */}
                <section className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div className="rounded-xl border p-4" style={{ borderColor: 'var(--color-border)', backgroundColor: 'color-mix(in srgb, var(--color-danger) 6%, transparent)' }}>
                        <p className="text-xs uppercase tracking-wide flex items-center gap-1" style={{ color: 'var(--color-text-muted)' }}><TrendingDown size={13} /> Faltante (valorizado)</p>
                        <p className="text-lg font-bold" style={{ color: 'var(--color-danger)' }}>{sol(resumen.faltante)}</p>
                    </div>
                    <div className="rounded-xl border p-4" style={{ borderColor: 'var(--color-border)', backgroundColor: 'color-mix(in srgb, var(--color-success) 6%, transparent)' }}>
                        <p className="text-xs uppercase tracking-wide flex items-center gap-1" style={{ color: 'var(--color-text-muted)' }}><TrendingUp size={13} /> Sobrante (valorizado)</p>
                        <p className="text-lg font-bold" style={{ color: 'var(--color-success)' }}>{sol(resumen.sobrante)}</p>
                    </div>
                    <div className="rounded-xl border p-4" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Neto</p>
                        <p className="text-lg font-bold" style={{ color: resumen.neto < 0 ? 'var(--color-danger)' : 'var(--color-text)' }}>{sol(resumen.neto)}</p>
                    </div>
                </section>

                {/* Detalle */}
                <section className="rounded-2xl border p-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Detalle por producto</h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium">Producto</th>
                                    <th className="text-right py-2 px-2 font-medium">Sistema</th>
                                    <th className="text-right py-2 px-2 font-medium">Declarado</th>
                                    <th className="text-right py-2 px-2 font-medium">Diferencia</th>
                                    <th className="text-right py-2 px-2 font-medium">Valorizado</th>
                                    <th className="text-left py-2 px-2 font-medium">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map(it => (
                                    <tr key={it.producto_id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                        <td className="py-2 px-2">
                                            <div className="font-medium" style={{ color: 'var(--color-text)' }}>{it.nombre}</div>
                                            {it.codigo && <div className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{it.codigo}</div>}
                                        </td>
                                        <td className="py-2 px-2 text-right tabular-nums">{num(it.stock_sistema)}</td>
                                        <td className="py-2 px-2 text-right tabular-nums">{num(it.stock_declarado)}</td>
                                        <td className="py-2 px-2 text-right tabular-nums">
                                            {it.diferencia === 0
                                                ? <span style={{ color: 'var(--color-success)' }}>0</span>
                                                : <span style={{ color: it.diferencia < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>{it.diferencia > 0 ? '+' : ''}{num(it.diferencia)}</span>}
                                        </td>
                                        <td className="py-2 px-2 text-right tabular-nums" style={{ color: it.valor_diferencia < 0 ? 'var(--color-danger)' : it.valor_diferencia > 0 ? 'var(--color-success)' : 'var(--color-text-muted)' }}>
                                            {it.valor_diferencia === 0 ? '—' : sol(it.valor_diferencia)}
                                        </td>
                                        <td className="py-2 px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{it.observacion ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Modal isOpen={anulando} onClose={() => setAnulando(false)} title={`Anular cierre #${cierre.id}`} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulando(false)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => router.post(route('inventario.cierres.anular', cierre.id), { motivo }, { onSuccess: () => setAnulando(false) })}>Anular cierre</Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <p className="text-sm" style={{ color: 'var(--color-text)' }}>Se revierte el ajuste de stock y kardex de este cierre. Queda auditado.</p>
                    <Input label="Motivo" required value={motivo} onChange={e => setMotivo(e.target.value)} />
                </div>
            </Modal>
        </AppLayout>
    );
}
