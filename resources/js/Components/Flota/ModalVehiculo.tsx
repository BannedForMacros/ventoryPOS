import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Modal from '@/Components/UI/Modal';

/**
 * Alta / edición de un vehículo. La usan Flota → Vehículos y el formulario del
 * viaje («+ Nuevo camión»), para no tener que salir a otra pantalla.
 *
 * `rapido` deja a la vista solo lo que el viaje y la guía necesitan; el
 * kilometraje y el preventivo quedan plegados en «Más datos».
 */

export type EstadoVehiculo = 'disponible' | 'taller' | 'fuera_servicio';

export interface VehiculoEditable {
    id: number; placa: string; marca: string | null; modelo: string | null; anio: number | null; tipo: string | null;
    capacidad_kg: number | null; tarjeta_circulacion: string | null; conductor_id: number | null;
    estado: EstadoVehiculo; km_ficha: number | null; observacion: string | null; activo: boolean;
    mant_intervalo_km: number; mant_base_km: number | null;
    soat?: DocumentoClave | null; revision?: DocumentoClave | null;
}

/** SOAT o revisión técnica vigente (se guardan en la pestaña Documentos). */
export interface DocumentoClave { vence: string | null; numero: string | null; estado: 'vigente' | 'por_vencer' | 'vencido' | null }

interface Form {
    placa: string; marca: string; modelo: string; anio: string; tipo: string;
    capacidad_kg: string; tarjeta_circulacion: string; conductor_id: number | '';
    estado: EstadoVehiculo; km_actual: string; observacion: string; activo: boolean;
    mant_intervalo_km: string; mant_base_km: string;
    soat_vence: string; soat_numero: string; revision_vence: string; revision_numero: string;
}

const vacio = (conductorId: number | '' = ''): Form => ({
    placa: '', marca: '', modelo: '', anio: '', tipo: '', capacidad_kg: '', tarjeta_circulacion: '',
    conductor_id: conductorId, estado: 'disponible', km_actual: '', observacion: '', activo: true,
    mant_intervalo_km: '8000', mant_base_km: '',
    soat_vence: '', soat_numero: '', revision_vence: '', revision_numero: '',
});

const deVehiculo = (v: VehiculoEditable): Form => ({
    placa: v.placa, marca: v.marca ?? '', modelo: v.modelo ?? '', anio: v.anio ? String(v.anio) : '',
    tipo: v.tipo ?? '', capacidad_kg: v.capacidad_kg != null ? String(v.capacidad_kg) : '',
    tarjeta_circulacion: v.tarjeta_circulacion ?? '', conductor_id: v.conductor_id ?? '',
    estado: v.estado, km_actual: v.km_ficha != null ? String(v.km_ficha) : '',
    observacion: v.observacion ?? '', activo: v.activo,
    mant_intervalo_km: String(v.mant_intervalo_km ?? 8000), mant_base_km: v.mant_base_km != null ? String(v.mant_base_km) : '',
    soat_vence: v.soat?.vence ?? '', soat_numero: v.soat?.numero ?? '',
    revision_vence: v.revision?.vence ?? '', revision_numero: v.revision?.numero ?? '',
});

/** "Vence en 12 días", "Venció hace 3 días"… para que la fecha diga algo. */
function plazo(vence: string): { texto: string; color: string } | null {
    const [y, m, d] = vence.split('-').map(Number);
    if (!y || !m || !d) return null;
    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    const dias = Math.round((new Date(y, m - 1, d).getTime() - hoy.getTime()) / 86_400_000);
    if (dias < 0) return { texto: `Venció hace ${-dias} ${-dias === 1 ? 'día' : 'días'}`, color: 'var(--vp-coral-ink)' };
    if (dias === 0) return { texto: 'Vence hoy', color: 'var(--vp-coral-ink)' };
    if (dias <= 30) return { texto: `Vence en ${dias} ${dias === 1 ? 'día' : 'días'}: avisará en Flota`, color: 'var(--vp-amber-ink)' };
    return { texto: `Vigente · faltan ${dias} días`, color: 'var(--vp-mint-ink)' };
}

export const ESTADOS_VEHICULO: { value: EstadoVehiculo; label: string }[] = [
    { value: 'disponible', label: 'Disponible' },
    { value: 'taller', label: 'En taller' },
    { value: 'fuera_servicio', label: 'Fuera de servicio' },
];

interface Props {
    isOpen: boolean;
    onClose: () => void;
    choferes: { value: number; label: string }[];
    editando?: VehiculoEditable | null;
    rapido?: boolean;
    /** Chofer propuesto al abrir (p. ej. el ya elegido en el viaje). */
    conductorInicial?: number | null;
    /** Recargar solo estas props al guardar (el viaje no pierde lo cargado). */
    only?: string[];
    onGuardado?: (page: { props: Record<string, unknown> }) => void;
}

export default function ModalVehiculo({ isOpen, onClose, choferes, editando = null, rapido = false, conductorInicial = null, only, onGuardado }: Props) {
    const [form, setForm] = useState<Form>(vacio());
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const [masDatos, setMasDatos] = useState(false);

    useEffect(() => {
        if (!isOpen) return;
        setForm(editando ? deVehiculo(editando) : vacio(conductorInicial ?? ''));
        setErrores({});
        setMasDatos(false);
    }, [isOpen, editando]); // eslint-disable-line react-hooks/exhaustive-deps

    const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm(f => ({ ...f, [k]: v }));

    function guardar() {
        setGuardando(true);
        const payload = { ...form, conductor_id: form.conductor_id || null };
        const opts = {
            preserveScroll: true,
            preserveState: true,
            ...(only ? { only } : {}),
            onSuccess: (page: any) => { onClose(); onGuardado?.(page); },
            onError: (e: Record<string, string>) => {
                setErrores(e);
                // Un error escondido en «Más datos» no debe pasar desapercibido.
                if (['km_actual', 'mant_intervalo_km', 'mant_base_km', 'observacion', 'estado', 'anio', 'soat_vence', 'soat_numero', 'revision_vence', 'revision_numero'].some(k => e[k])) setMasDatos(true);
            },
            onFinish: () => setGuardando(false),
        };
        editando
            ? router.put(route('flota.vehiculos.update', editando.id), payload, opts)
            : router.post(route('flota.vehiculos.store'), payload, opts);
    }

    const documentos = (
        <div className="rounded-xl p-3 space-y-3" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
            <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                Documentos <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}>· avisa 30 días antes de que venzan</span>
            </p>
            {([['soat', 'SOAT'], ['revision', 'Revisión técnica']] as const).map(([k, t]) => {
                const vence = form[`${k}_vence`];
                const p = vence ? plazo(vence) : null;
                return (
                    <div key={k} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label={`${t}: vence el`} type="date" value={vence} error={errores[`${k}_vence`]}
                            onChange={e => set(`${k}_vence`, e.target.value)}
                            hint={p ? undefined : k === 'soat' ? 'Fecha de fin de la póliza' : 'Fecha de la próxima revisión'} />
                        <Input label={`N.º de ${k === 'soat' ? 'póliza' : 'certificado'}`} value={form[`${k}_numero`]} placeholder="Opcional" error={errores[`${k}_numero`]}
                            onChange={e => set(`${k}_numero`, e.target.value)} />
                        {p && <p className="sm:col-span-2 -mt-2 text-[13px] font-medium" style={{ color: p.color }}>{t}: {p.texto}</p>}
                    </div>
                );
            })}
            {editando && (
                <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                    Al renovar, pon la nueva fecha: el documento anterior queda en el historial de la pestaña Documentos.
                </p>
            )}
        </div>
    );

    const extras = (
        <>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Select label="Estado" value={form.estado} onChange={v => set('estado', v as EstadoVehiculo)}
                    options={ESTADOS_VEHICULO} error={errores.estado} hint="En taller o fuera de servicio no se le pueden armar viajes" />
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
        </>
    );

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="xl"
            title={editando ? `Editar ${editando.placa}` : 'Nuevo vehículo'}
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={guardar} loading={guardando}>{rapido && !editando ? 'Guardar y usar en el viaje' : 'Guardar'}</Button>
            </>}>
            <form className="space-y-4" onSubmit={e => { e.preventDefault(); guardar(); }}>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Input label="Placa" required value={form.placa} placeholder="M9C-822" autoFocus
                        onChange={e => set('placa', e.target.value.toUpperCase())} error={errores.placa} />
                    <Input label="Marca" value={form.marca} placeholder="Hyundai" onChange={e => set('marca', e.target.value)} error={errores.marca} />
                    <Input label="Modelo" value={form.modelo} placeholder="EX10" onChange={e => set('modelo', e.target.value)} error={errores.modelo} />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Input label="Tipo" value={form.tipo} placeholder="Baranda, volquete, furgón…" onChange={e => set('tipo', e.target.value)} error={errores.tipo} />
                    {rapido
                        ? <Input label="Tarjeta / habilitación MTC" value={form.tarjeta_circulacion} hint="Para la guía, si la piden"
                            onChange={e => set('tarjeta_circulacion', e.target.value)} error={errores.tarjeta_circulacion} />
                        : <Input label="Año" type="number" inputMode="numeric" value={form.anio} onChange={e => set('anio', e.target.value)} error={errores.anio} />}
                    <Input label="Carga máxima (kg)" type="number" inputMode="decimal" min={0} value={form.capacidad_kg} placeholder="13000"
                        hint="Para el % de carga del viaje" onChange={e => set('capacidad_kg', e.target.value)} error={errores.capacidad_kg} />
                </div>
                <div className={`grid grid-cols-1 gap-3 ${rapido ? '' : 'sm:grid-cols-2'}`}>
                    <Select label="Chofer asignado" value={form.conductor_id} placeholder="Sin asignar"
                        onChange={v => set('conductor_id', v === '' ? '' : Number(v))}
                        options={[{ value: '', label: 'Sin asignar' }, ...choferes]} error={errores.conductor_id}
                        hint="Sale propuesto al armar un viaje con este vehículo" />
                    {!rapido && (
                        <Input label="Tarjeta de circulación / habilitación MTC" value={form.tarjeta_circulacion}
                            hint="Va en la guía de remisión si la piden" onChange={e => set('tarjeta_circulacion', e.target.value)} error={errores.tarjeta_circulacion} />
                    )}
                </div>

                {rapido ? (
                    <div className="rounded-xl" style={{ border: '1px solid var(--color-border)' }}>
                        <button type="button" onClick={() => setMasDatos(m => !m)} aria-expanded={masDatos}
                            className="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold"
                            style={{ color: 'var(--color-text-muted)' }}>
                            Más datos (opcional): SOAT, revisión técnica, kilometraje, preventivo
                            <ChevronDown size={16} className={`transition-transform duration-200 ${masDatos ? 'rotate-180' : ''}`} />
                        </button>
                        {masDatos && <div className="px-3 pb-3 space-y-3 vp-fade-up">{documentos}{extras}</div>}
                    </div>
                ) : <>{documentos}{extras}</>}

                {editando && <Switch label="Activo" checked={form.activo} onChange={v => set('activo', v)} />}
                <button type="submit" className="hidden" aria-hidden tabIndex={-1} />
            </form>
        </Modal>
    );
}
