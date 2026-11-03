import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Truck, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import { EstadoDocumentoBadge, MantenimientoChip, Pastilla, fmtKg, type EstadoMantenimiento } from '@/Components/Flota/FlotaUI';
import ModalVehiculo, { ESTADOS_VEHICULO as ESTADOS, type DocumentoClave, type EstadoVehiculo } from '@/Components/Flota/ModalVehiculo';
import type { PageProps } from '@/types';

interface Vehiculo extends Record<string, unknown> {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    anio: number | null;
    tipo: string | null;
    capacidad_kg: number | null;
    tarjeta_circulacion: string | null;
    conductor_id: number | null;
    conductor: string | null;
    estado: EstadoVehiculo;
    km_actual: number | null;
    km_ficha: number | null;
    mant_intervalo_km: number;
    mant_base_km: number | null;
    mantenimiento: EstadoMantenimiento;
    observacion: string | null;
    activo: boolean;
    en_ruta: string | null;
    viajes_mes: number;
    soat: DocumentoClave | null;
    revision: DocumentoClave | null;
}

interface Props extends PageProps {
    vehiculos: Vehiculo[];
    choferes: { value: number; label: string }[];
    puede: { crear: boolean; editar: boolean; eliminar: boolean };
}

export default function Vehiculos({ vehiculos, choferes, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<Vehiculo | null>(null);
    const [borrar, setBorrar] = useState<Vehiculo | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    function nuevo() { setEditando(null); setAbierto(true); }
    function editar(v: Vehiculo) { setEditando(v); setAbierto(true); }

    const columnas: Column<Vehiculo>[] = [
        {
            key: 'placa', label: 'Vehículo',
            render: v => (
                <div className="flex items-center gap-3 min-w-0">
                    <span className="font-mono text-[13px] font-bold tracking-wider px-2 py-1 rounded-md border-2 flex-shrink-0"
                        style={{ borderColor: 'var(--vp-navy)', color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 18%, var(--color-surface))' }}>
                        {v.placa}
                    </span>
                    <div className="min-w-0">
                        <p className="font-medium truncate hover:underline" style={{ color: 'var(--color-text)' }}>
                            {[v.marca, v.modelo].filter(Boolean).join(' ') || '—'}{v.anio ? <span style={{ color: 'var(--color-text-muted)' }}> · {v.anio}</span> : null}
                        </p>
                        {v.tipo && <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{v.tipo}</p>}
                    </div>
                </div>
            ),
            exportValue: v => v.placa,
        },
        {
            key: 'capacidad_kg', label: 'Carga máx.', align: 'right',
            render: v => <span className="tabular-nums">{v.capacidad_kg ? fmtKg(v.capacidad_kg) : '—'}</span>,
        },
        {
            key: 'conductor', label: 'Chofer asignado',
            render: v => v.conductor ?? <span style={{ color: 'var(--color-text-muted)' }}>Sin asignar</span>,
        },
        {
            key: 'estado', label: 'Estado',
            render: v => !v.activo ? <Pastilla color="var(--color-text-muted)">Desactivado</Pastilla>
                : v.en_ruta ? <Pastilla color="var(--vp-navy)" icono={<Truck size={13} />}>En ruta · {v.en_ruta}</Pastilla>
                : v.estado === 'taller' ? <Pastilla color="var(--vp-amber-ink)" icono={<Wrench size={13} />}>En taller</Pastilla>
                : v.estado === 'fuera_servicio' ? <Pastilla color="var(--vp-coral-ink)">Fuera de servicio</Pastilla>
                : <Pastilla color="var(--vp-mint-ink)">Disponible</Pastilla>,
            exportValue: v => v.en_ruta ? 'En ruta' : ESTADOS.find(e => e.value === v.estado)?.label,
        },
        {
            key: 'km_actual', label: 'Km', align: 'right',
            render: v => <span className="tabular-nums">{v.km_actual != null ? Number(v.km_actual).toLocaleString('es-PE') : '—'}</span>,
        },
        {
            key: 'mantenimiento', label: 'Preventivo', sortKey: 'mantenimiento.faltan',
            render: v => <MantenimientoChip m={v.mantenimiento} />,
            exportValue: v => v.mantenimiento.faltan ?? '',
        },
        {
            key: 'soat', label: 'SOAT', sortKey: 'soat.vence',
            render: v => v.soat?.vence ? <EstadoDocumentoBadge estado={v.soat.estado} vence={v.soat.vence} /> : <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Sin registrar</span>,
            exportValue: v => v.soat?.vence ?? '',
        },
        {
            key: 'revision', label: 'Revisión técnica', sortKey: 'revision.vence',
            render: v => v.revision?.vence ? <EstadoDocumentoBadge estado={v.revision.estado} vence={v.revision.vence} /> : <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Sin registrar</span>,
            exportValue: v => v.revision?.vence ?? '',
        },
        {
            key: 'viajes_mes', label: 'Viajes del mes', align: 'right',
            render: v => <span className="tabular-nums font-semibold">{v.viajes_mes}</span>,
        },
        {
            key: 'acciones', label: '', sortable: false,
            render: v => (
                // La fila abre la ficha: las acciones no deben abrirla también.
                <span onClick={e => e.stopPropagation()}>
                    <TableActions
                        onEdit={puede.editar ? () => editar(v) : undefined}
                        onDelete={puede.eliminar && v.activo ? () => setBorrar(v) : undefined}
                    />
                </span>
            ),
        },
    ];

    return (
        <AppLayout title="Vehículos">
            <PageHeader
                title="Vehículos"
                subtitle="Los camiones de la empresa, su capacidad y quién los maneja. La capacidad sirve para ver cuánto se carga en cada viaje."
                icon={<Truck size={20} />}
                actions={puede.crear && <Button onClick={nuevo} startContent={<Plus size={15} />}>Nuevo vehículo</Button>}
            />

            <Table
                data={vehiculos}
                columns={columnas}
                searchPlaceholder="Buscar placa, marca o chofer…"
                emptyMessage="Todavía no hay vehículos. Agrega el primero con «Nuevo vehículo»."
                rowClassName={v => (v.activo ? 'cursor-pointer' : 'opacity-60 cursor-pointer')}
                onRowClick={v => router.visit(route('flota.vehiculos.show', v.id))}
                exportable
                exportFilename="vehiculos"
            />

            <ModalVehiculo isOpen={abierto} onClose={() => setAbierto(false)} choferes={choferes} editando={editando} />

            <Modal isOpen={!!borrar} onClose={() => setBorrar(null)} size="sm" title={`Quitar ${borrar?.placa ?? ''}`}
                footer={<>
                    <Button variant="ghost" onClick={() => setBorrar(null)}>Cancelar</Button>
                    <Button variant="danger" onClick={() => borrar && router.delete(route('flota.vehiculos.destroy', borrar.id), { preserveScroll: true, onFinish: () => setBorrar(null) })}>Quitar</Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Si ya hizo viajes, se desactiva y sigue en el historial. Si no tiene ninguno, se elimina.
                </p>
            </Modal>
        </AppLayout>
    );
}
