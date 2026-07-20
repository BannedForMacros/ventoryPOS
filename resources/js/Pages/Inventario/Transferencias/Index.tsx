import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Send, PackageCheck, Ban, Pencil, Eye } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import type { PageProps } from '@/types';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }
type EstadoTransfer = 'borrador' | 'enviada' | 'recibida' | 'anulada';

interface Transferencia extends Record<string, unknown> {
    id: number;
    fecha: string;
    almacen_origen: Almacen;
    almacen_destino: Almacen;
    user: { name: string };
    user_envio?: { name: string } | null;
    user_recepcion?: { name: string } | null;
    estado: EstadoTransfer;
    observacion_envio: string | null;
    observacion_recepcion: string | null;
    fecha_envio: string | null;
    fecha_recepcion: string | null;
}

// M19: paginado server-side.
interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Props extends PageProps {
    transferencias: Paginado<Transferencia>;
    almacenes: Almacen[];
    filters: Record<string, string>;
    buscar?: string;
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

export default function TransferenciasIndex({ transferencias, filters, buscar }: Props) {
    const { flash } = usePage<Props>().props;
    const [enviarId, setEnviarId]   = useState<number | null>(null);
    const [anularId, setAnularId]   = useState<number | null>(null);
    const [deleteId, setDeleteId]   = useState<number | null>(null);
    const [filtrEstado, setFiltrEstado] = useState(filters.estado ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function enviar(id: number)   { setEnviarId(null); router.post(route('inventario.transferencias.enviar', id)); }
    function anular(id: number)   { setAnularId(null); router.post(route('inventario.transferencias.anular', id)); }
    function eliminar(id: number) { setDeleteId(null); router.delete(route('inventario.transferencias.destroy', id)); }

    const columns: Column<Transferencia>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (t) => <span className="text-sm">{t.fecha}</span>,
        },
        {
            key: 'almacen_origen', label: 'Origen', sortKey: 'almacen_origen.nombre',
            render: (t) => (
                <span className="text-sm">
                    {t.almacen_origen.nombre}
                </span>
            ),
        },
        {
            key: 'almacen_destino', label: 'Destino', sortKey: 'almacen_destino.nombre',
            render: (t) => (
                <span className="text-sm">
                    {t.almacen_destino.nombre}
                    {t.almacen_destino.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {t.almacen_destino.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'user', label: 'Creado por',
            render: (t) => <span className="text-sm">{t.user.name}</span>,
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (t) => (
                <Badge variant={ESTADO_VARIANT[t.estado]}>
                    {ESTADO_LABEL[t.estado]}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (t) => (
                <div className="flex items-center gap-1">
                    <button type="button" onClick={() => router.visit(route('inventario.transferencias.show', t.id))}
                        className="rounded-lg p-1.5 transition-colors" style={{ color: 'var(--color-primary)' }}
                        title="Ver detalle">
                        <Eye size={14} />
                    </button>

                    {t.estado !== 'anulada' && (
                        <button type="button" onClick={() => router.visit(route('inventario.transferencias.edit', t.id))}
                            className="rounded-lg p-1.5 transition-colors" style={{ color: 'var(--color-text)' }}
                            title="Editar">
                            <Pencil size={14} />
                        </button>
                    )}

                    {t.estado === 'borrador' && (
                        <>
                            <button type="button" onClick={() => setEnviarId(t.id)}
                                className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium"
                                style={{ color: 'var(--color-primary)', backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)' }}
                                title="Enviar (despachar)">
                                <Send size={12} />Enviar
                            </button>
                            <button type="button" onClick={() => setDeleteId(t.id)}
                                className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}
                                title="Eliminar borrador">
                                <Ban size={14} />
                            </button>
                        </>
                    )}

                    {t.estado === 'enviada' && (
                        <Link href={route('inventario.transferencias.show', t.id)}>
                            <button type="button"
                                className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium"
                                style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' }}
                                title="Confirmar recepción">
                                <PackageCheck size={12} />Recibir
                            </button>
                        </Link>
                    )}

                    {(t.estado === 'enviada' || t.estado === 'recibida') && (
                        <button type="button" onClick={() => setAnularId(t.id)}
                            className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}
                            title="Anular (revierte stock)">
                            <Ban size={14} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    const filtered = transferencias.data.filter(t =>
        !filtrEstado || t.estado === filtrEstado
    );

    return (
        <AppLayout title="Transferencias">
            <PageHeader
                title="Transferencias de stock"
                subtitle="Despacho del almacén central a los almacenes locales (con confirmación de recepción)"
                actions={
                    <Link href={route('inventario.transferencias.create')}>
                        <Button><Plus size={15} className="mr-1" />Nueva transferencia</Button>
                    </Link>
                }
            />

            <FiltrosCard cols={3} tieneFiltros={!!filtrEstado} onClear={() => setFiltrEstado('')}>
                <Select
                    label="Estado"
                    value={filtrEstado}
                    onChange={v => setFiltrEstado(String(v))}
                    options={[
                        { value: '',         label: 'Todos los estados' },
                        { value: 'borrador', label: 'Borrador' },
                        { value: 'enviada',  label: 'Enviada (en tránsito)' },
                        { value: 'recibida', label: 'Recibida' },
                        { value: 'anulada',  label: 'Anulada' },
                    ]}
                />
            </FiltrosCard>

            <Table data={filtered} columns={columns} emptyMessage="No hay transferencias registradas"
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('inventario.transferencias.index'),
                    { ...filters, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })} />

            {/* Paginación (M19) */}
            {transferencias.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: transferencias.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('inventario.transferencias.index'), { ...filters, buscar: buscar || undefined, page }, { preserveState: true })}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === transferencias.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === transferencias.current_page ? '#fff' : 'var(--color-text-muted)',
                            }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}

            {/* Modal: enviar (despachar) */}
            <Modal isOpen={enviarId !== null} onClose={() => setEnviarId(null)}
                title="Enviar transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEnviarId(null)}>Cancelar</Button>
                        <Button variant="primary" onClick={() => enviarId && enviar(enviarId)}>
                            <Send size={14} className="mr-1" />Enviar
                        </Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Al enviar, se descontará el stock del <strong>almacén origen (central)</strong>. El destino aún NO recibirá stock — el local debe confirmar la recepción.
                </p>
            </Modal>

            {/* Modal: anular */}
            <Modal isOpen={anularId !== null} onClose={() => setAnularId(null)}
                title="Anular transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnularId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => anularId && anular(anularId)}>
                            <Ban size={14} className="mr-1" />Anular
                        </Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se revertirán los movimientos de stock aplicados (devolverá stock al origen, descontará del destino si ya estaba recibida).
                </p>
            </Modal>

            {/* Modal: eliminar borrador */}
            <Modal isOpen={deleteId !== null} onClose={() => setDeleteId(null)}
                title="Eliminar transferencia" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDeleteId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => deleteId && eliminar(deleteId)}>Eliminar</Button>
                    </>
                }>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se eliminará el borrador. Esta acción no afecta stock.
                </p>
            </Modal>
        </AppLayout>
    );
}
