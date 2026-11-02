import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { IdCard, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import Callout from '@/Components/UI/Callout';
import { LicenciaBadge, Pastilla, type EstadoLicencia } from '@/Components/Flota/FlotaUI';
import ModalConductor, { type TipoConductor as Tipo } from '@/Components/Flota/ModalConductor';
import type { PageProps } from '@/types';

interface Conductor extends Record<string, unknown> {
    id: number;
    tipo: Tipo;
    nombres: string;
    apellidos: string | null;
    nombre_completo: string;
    tipo_documento: string;
    numero_documento: string | null;
    telefono: string | null;
    licencia: string | null;
    licencia_categoria: string | null;
    licencia_vence: string | null;
    estado_licencia: EstadoLicencia;
    observacion: string | null;
    activo: boolean;
    vehiculos: string[];
    viajes_mes: number;
}

interface Props extends PageProps {
    conductores: Conductor[];
    puede: { crear: boolean; editar: boolean; eliminar: boolean };
}

export default function Conductores({ conductores, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<Conductor | null>(null);
    const [borrar, setBorrar] = useState<Conductor | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const alertas = useMemo(() => conductores.filter(c => c.activo && c.tipo === 'chofer'
        && (c.estado_licencia === 'vencida' || c.estado_licencia === 'por_vencer')), [conductores]);

    function nuevo() { setEditando(null); setAbierto(true); }
    function editar(c: Conductor) { setEditando(c); setAbierto(true); }

    const columnas: Column<Conductor>[] = [
        {
            key: 'nombre_completo', label: 'Nombre',
            render: c => (
                <div className="min-w-0">
                    <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{c.nombre_completo}</p>
                    <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                        {[c.numero_documento ? `${c.tipo_documento} ${c.numero_documento}` : null, c.telefono].filter(Boolean).join(' · ') || '—'}
                    </p>
                </div>
            ),
        },
        {
            key: 'tipo', label: 'Función',
            render: c => <Pastilla color={c.tipo === 'chofer' ? 'var(--vp-navy)' : 'var(--color-text-muted)'}>{c.tipo === 'chofer' ? 'Chofer' : 'Ayudante'}</Pastilla>,
        },
        {
            key: 'licencia', label: 'Licencia',
            render: c => c.tipo !== 'chofer' ? <span style={{ color: 'var(--color-text-muted)' }}>—</span> : (
                <div className="space-y-1">
                    <p className="text-[13px] font-mono" style={{ color: 'var(--color-text)' }}>
                        {c.licencia ?? '—'}{c.licencia_categoria ? ` · ${c.licencia_categoria}` : ''}
                    </p>
                    <LicenciaBadge estado={c.estado_licencia} vence={c.licencia_vence} />
                </div>
            ),
            sortKey: 'licencia_vence',
        },
        {
            key: 'vehiculos', label: 'Maneja', sortable: false,
            render: c => c.vehiculos.length ? <span className="font-mono text-[13px]">{c.vehiculos.join(', ')}</span> : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
            exportValue: c => c.vehiculos.join(', '),
        },
        {
            key: 'viajes_mes', label: 'Viajes del mes', align: 'right',
            render: c => <span className="tabular-nums font-semibold">{c.viajes_mes}</span>,
        },
        {
            key: 'acciones', label: '', sortable: false,
            render: c => (
                <TableActions
                    onEdit={puede.editar ? () => editar(c) : undefined}
                    onDelete={puede.eliminar && c.activo ? () => setBorrar(c) : undefined}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Conductores">
            <PageHeader
                title="Conductores"
                subtitle="Choferes y ayudantes de reparto. El DNI y la licencia del chofer salen solos en la guía de remisión."
                icon={<IdCard size={20} />}
                actions={puede.crear && <Button onClick={nuevo} startContent={<Plus size={15} />}>Nuevo conductor</Button>}
            />

            {alertas.length > 0 && (
                <Callout variant={alertas.some(a => a.estado_licencia === 'vencida') ? 'danger' : 'warning'} className="mb-4"
                    title={alertas.length === 1 ? 'Una licencia necesita atención' : `${alertas.length} licencias necesitan atención`}>
                    {alertas.map(a => `${a.nombre_completo} (${a.estado_licencia === 'vencida' ? 'vencida' : 'vence pronto'})`).join(', ')}.
                </Callout>
            )}

            <Table
                data={conductores}
                columns={columnas}
                searchPlaceholder="Buscar nombre, DNI o licencia…"
                emptyMessage="Todavía no hay conductores. Agrega a tus choferes y ayudantes."
                rowClassName={c => (c.activo ? '' : 'opacity-60')}
                exportable
                exportFilename="conductores"
            />

            <ModalConductor isOpen={abierto} onClose={() => setAbierto(false)} editando={editando} />

            <Modal isOpen={!!borrar} onClose={() => setBorrar(null)} size="sm" title={`Quitar a ${borrar?.nombre_completo ?? ''}`}
                footer={<>
                    <Button variant="ghost" onClick={() => setBorrar(null)}>Cancelar</Button>
                    <Button variant="danger" onClick={() => borrar && router.delete(route('flota.conductores.destroy', borrar.id), { preserveScroll: true, onFinish: () => setBorrar(null) })}>Quitar</Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Si ya fue en algún viaje, se desactiva y sigue en el historial. Si no, se elimina.
                </p>
            </Modal>
        </AppLayout>
    );
}
