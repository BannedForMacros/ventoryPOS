import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { SlidersHorizontal, Ban, ArrowDownCircle, ArrowUpCircle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import { fmtFecha } from '@/lib/fechas';
import type { PageProps } from '@/types';

interface AjusteRow extends Record<string, unknown> {
    id: number;
    numero: string | null;
    fecha: string | null;
    tipo: 'ingreso' | 'salida';
    cantidad_base: number;
    estado: 'borrador' | 'confirmado' | 'anulado';
    motivo: string | null;
    almacen: string;
    producto: string;
    producto_codigo: string | null;
    usuario: string | null;
}

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }
interface Filters { almacen_id?: string; tipo?: string; estado?: string; fecha_desde?: string; fecha_hasta?: string; buscar?: string; }

interface Props extends PageProps {
    ajustes: Paginado<AjusteRow>;
    almacenes: { id: number; nombre: string }[];
    mostrarSelector: boolean;
    puede?: { editar: boolean };
    filters: Filters;
}

const num = (n: number) => Number(n ?? 0).toLocaleString('es-PE', { maximumFractionDigits: 4 });

export default function AjustesIndex({ ajustes, almacenes, mostrarSelector, puede, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const puedeEditar = puede?.editar ?? false;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const [anulando, setAnulando] = useState<AjusteRow | null>(null);
    const [motivo, setMotivo]     = useState('');
    const [errors, setErrors]     = useState<Record<string, string>>({});
    const [saving, setSaving]     = useState(false);

    function filtrar(patch: Partial<Filters>) {
        router.get(route('inventario.ajustes.index'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    function submitAnular() {
        if (!anulando) return;
        setSaving(true);
        router.post(route('inventario.ajustes.anular', anulando.id), { motivo }, {
            preserveScroll: true,
            onSuccess: () => { setAnulando(null); setMotivo(''); setSaving(false); },
            onError:   (e) => { setErrors(e as Record<string, string>); setSaving(false); },
        });
    }

    const columns: Column<AjusteRow>[] = [
        { key: 'numero', label: 'N°', render: (a) => <span className="font-mono text-sm">{a.numero ?? '—'}</span> },
        { key: 'fecha', label: 'Fecha', render: (a) => <span className="text-sm">{a.fecha ? fmtFecha(a.fecha) : '—'}</span> },
        {
            key: 'tipo', label: 'Tipo', render: (a) => (
                <Badge variant={a.tipo === 'ingreso' ? 'success' : 'danger'}>
                    {a.tipo === 'ingreso'
                        ? <><ArrowUpCircle size={12} className="inline mr-1" />Ingreso (+)</>
                        : <><ArrowDownCircle size={12} className="inline mr-1" />Salida (−)</>}
                </Badge>
            ),
        },
        {
            key: 'producto', label: 'Producto', render: (a) => (
                <div>
                    <span className="text-sm font-medium">{a.producto}</span>
                    {a.producto_codigo && <span className="ml-1.5 font-mono text-xs" style={{ color: 'var(--color-text-muted)' }}>{a.producto_codigo}</span>}
                    {mostrarSelector && <div className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{a.almacen}</div>}
                </div>
            ),
        },
        { key: 'cantidad_base', label: 'Cantidad', align: 'right', render: (a) => <span className="font-semibold" style={{ fontVariantNumeric: 'tabular-nums' }}>{num(a.cantidad_base)}</span> },
        { key: 'motivo', label: 'Motivo', render: (a) => <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{a.motivo ?? '—'}</span> },
        {
            key: 'estado', label: 'Estado', render: (a) => (
                <Badge variant={a.estado === 'confirmado' ? 'success' : a.estado === 'anulado' ? 'danger' : 'warning'}>
                    {a.estado === 'confirmado' ? 'Confirmado' : a.estado === 'anulado' ? 'Anulado' : 'Borrador'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: '', sortable: false, render: (a) => (
                puedeEditar && a.estado === 'confirmado' ? (
                    <button onClick={() => { setErrors({}); setMotivo(''); setAnulando(a); }}
                        title="Anular ajuste (revierte el stock)"
                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-[color-mix(in_srgb,var(--color-danger)_12%,transparent)]"
                        style={{ color: 'var(--color-danger)' }}>
                        <Ban size={16} />
                    </button>
                ) : null
            ),
        },
    ];

    return (
        <AppLayout title="Ajustes de inventario">
            <PageHeader
                title="Ajustes de inventario"
                subtitle="Ingresos y salidas de stock por ajuste — sin dinero, con trazabilidad en el kardex"
            />

            <FiltrosCard>
                <Select label="Tipo" value={filters.tipo ?? ''} onChange={(v) => filtrar({ tipo: String(v) || undefined })}
                    options={[{ value: '', label: 'Todos' }, { value: 'ingreso', label: 'Ingreso (+)' }, { value: 'salida', label: 'Salida (−)' }]} />
                <Select label="Estado" value={filters.estado ?? ''} onChange={(v) => filtrar({ estado: String(v) || undefined })}
                    options={[{ value: '', label: 'Todos' }, { value: 'confirmado', label: 'Confirmado' }, { value: 'anulado', label: 'Anulado' }]} />
                {mostrarSelector && (
                    <Select label="Almacén" value={filters.almacen_id ?? ''} onChange={(v) => filtrar({ almacen_id: String(v) || undefined })}
                        options={[{ value: '', label: 'Todos' }, ...almacenes.map((a) => ({ value: String(a.id), label: a.nombre }))]} />
                )}
                <Input label="Desde" type="date" value={filters.fecha_desde ?? ''} onChange={(e) => filtrar({ fecha_desde: e.target.value || undefined })} />
                <Input label="Hasta" type="date" value={filters.fecha_hasta ?? ''} onChange={(e) => filtrar({ fecha_hasta: e.target.value || undefined })} />
                <Input label="Buscar" placeholder="N°, producto o motivo" defaultValue={filters.buscar ?? ''}
                    onKeyDown={(e) => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }} />
            </FiltrosCard>

            <Table
                data={ajustes}
                columns={columns}
                searchable={false}
                sortable={false}
                emptyMessage="No hay ajustes registrados. Puedes crear uno desde la lista de Stock (botón Ajustar)."
            />

            <Modal isOpen={anulando !== null} onClose={() => setAnulando(null)}
                title={anulando ? `Anular ajuste ${anulando.numero ?? ''}` : ''} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulando(null)}>Cancelar</Button>
                        <Button variant="danger" loading={saving} onClick={submitAnular}>Anular ajuste</Button>
                    </>
                }
            >
                {anulando && (
                    <div className="space-y-4">
                        <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                            Se revertirá el stock de <strong>{anulando.producto}</strong>: el ajuste de{' '}
                            <strong>{anulando.tipo === 'ingreso' ? '+' : '−'}{num(anulando.cantidad_base)}</strong> se deshace.
                        </p>
                        <Input label="Motivo" required value={motivo} onChange={(e) => setMotivo(e.target.value)} error={errors.motivo} />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
