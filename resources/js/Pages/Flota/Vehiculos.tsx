import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Truck, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import { MantenimientoChip, Pastilla, fmtKg, type EstadoMantenimiento } from '@/Components/Flota/FlotaUI';
import type { PageProps } from '@/types';

type EstadoVehiculo = 'disponible' | 'taller' | 'fuera_servicio';

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
}

interface Props extends PageProps {
    vehiculos: Vehiculo[];
    choferes: { value: number; label: string }[];
    puede: { crear: boolean; editar: boolean; eliminar: boolean };
}

interface Form {
    placa: string; marca: string; modelo: string; anio: string; tipo: string;
    capacidad_kg: string; tarjeta_circulacion: string; conductor_id: number | '';
    estado: EstadoVehiculo; km_actual: string; observacion: string; activo: boolean;
    mant_intervalo_km: string; mant_base_km: string;
}

const vacio = (): Form => ({
    placa: '', marca: '', modelo: '', anio: '', tipo: '', capacidad_kg: '', tarjeta_circulacion: '',
    conductor_id: '', estado: 'disponible', km_actual: '', observacion: '', activo: true,
    mant_intervalo_km: '8000', mant_base_km: '',
});

const ESTADOS: { value: EstadoVehiculo; label: string }[] = [
    { value: 'disponible', label: 'Disponible' },
    { value: 'taller', label: 'En taller' },
    { value: 'fuera_servicio', label: 'Fuera de servicio' },
];

export default function Vehiculos({ vehiculos, choferes, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<Vehiculo | null>(null);
    const [form, setForm] = useState<Form>(vacio());
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const [borrar, setBorrar] = useState<Vehiculo | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm(f => ({ ...f, [k]: v }));

    function nuevo() {
        setEditando(null); setForm(vacio()); setErrores({}); setAbierto(true);
    }

    function editar(v: Vehiculo) {
        setEditando(v);
        setForm({
            placa: v.placa, marca: v.marca ?? '', modelo: v.modelo ?? '', anio: v.anio ? String(v.anio) : '',
            tipo: v.tipo ?? '', capacidad_kg: v.capacidad_kg != null ? String(v.capacidad_kg) : '',
            tarjeta_circulacion: v.tarjeta_circulacion ?? '', conductor_id: v.conductor_id ?? '',
            estado: v.estado, km_actual: v.km_ficha != null ? String(v.km_ficha) : '',
            observacion: v.observacion ?? '', activo: v.activo,
            mant_intervalo_km: String(v.mant_intervalo_km ?? 8000), mant_base_km: v.mant_base_km != null ? String(v.mant_base_km) : '',
        });
        setErrores({}); setAbierto(true);
    }

    function guardar() {
        setGuardando(true);
        const payload = { ...form, conductor_id: form.conductor_id || null };
        const opts = {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
            onError: (e: Record<string, string>) => setErrores(e),
            onFinish: () => setGuardando(false),
        };
        editando
            ? router.put(route('flota.vehiculos.update', editando.id), payload, opts)
            : router.post(route('flota.vehiculos.store'), payload, opts);
    }

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

            <Modal isOpen={abierto} onClose={() => setAbierto(false)} size="xl"
                title={editando ? `Editar ${editando.placa}` : 'Nuevo vehículo'}
                footer={<>
                    <Button variant="ghost" onClick={() => setAbierto(false)} disabled={guardando}>Cancelar</Button>
                    <Button onClick={guardar} loading={guardando}>Guardar</Button>
                </>}>
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Input label="Placa" required value={form.placa} placeholder="M9C-822" autoFocus
                            onChange={e => set('placa', e.target.value.toUpperCase())} error={errores.placa} />
                        <Input label="Marca" value={form.marca} placeholder="Hyundai" onChange={e => set('marca', e.target.value)} error={errores.marca} />
                        <Input label="Modelo" value={form.modelo} placeholder="EX10" onChange={e => set('modelo', e.target.value)} error={errores.modelo} />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Input label="Tipo" value={form.tipo} placeholder="Baranda, volquete, furgón…" onChange={e => set('tipo', e.target.value)} error={errores.tipo} />
                        <Input label="Año" type="number" inputMode="numeric" value={form.anio} onChange={e => set('anio', e.target.value)} error={errores.anio} />
                        <Input label="Carga máxima (kg)" type="number" inputMode="decimal" min={0} value={form.capacidad_kg} placeholder="13000"
                            hint="Para el % de carga del viaje" onChange={e => set('capacidad_kg', e.target.value)} error={errores.capacidad_kg} />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Select label="Chofer asignado" value={form.conductor_id} placeholder="Sin asignar"
                            onChange={v => set('conductor_id', v === '' ? '' : Number(v))}
                            options={[{ value: '', label: 'Sin asignar' }, ...choferes]} error={errores.conductor_id}
                            hint="Sale propuesto al armar un viaje con este vehículo" />
                        <Select label="Estado" value={form.estado} onChange={v => set('estado', v as EstadoVehiculo)}
                            options={ESTADOS} error={errores.estado} hint="En taller o fuera de servicio no se le pueden armar viajes" />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label="Tarjeta de circulación / habilitación MTC" value={form.tarjeta_circulacion}
                            hint="Va en la guía de remisión si la piden" onChange={e => set('tarjeta_circulacion', e.target.value)} error={errores.tarjeta_circulacion} />
                        <Input label="Kilometraje actual" type="number" inputMode="decimal" min={0} value={form.km_actual}
                            hint="Después sube solo con cada carga de combustible" onChange={e => set('km_actual', e.target.value)} error={errores.km_actual} />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label="Preventivo cada (km)" type="number" inputMode="numeric" min={500} value={form.mant_intervalo_km}
                            onChange={e => set('mant_intervalo_km', e.target.value)} error={errores.mant_intervalo_km} />
                        <Input label="Km del último preventivo" type="number" inputMode="decimal" min={0} value={form.mant_base_km}
                            hint="Solo para empezar: luego se toma del último mantenimiento registrado"
                            onChange={e => set('mant_base_km', e.target.value)} error={errores.mant_base_km} />
                    </div>
                    <Input label="Observación" value={form.observacion} onChange={e => set('observacion', e.target.value)} error={errores.observacion} />
                    {editando && <Switch label="Activo" checked={form.activo} onChange={v => set('activo', v)} />}
                </div>
            </Modal>

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
