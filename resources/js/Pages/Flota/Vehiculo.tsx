import { useEffect, useState, type ReactNode } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { AlertTriangle, FileText, Fuel, Gauge, Paperclip, Plus, Receipt, Route as RutaIcono, Trash2, Truck, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import ModalGasto, { type PresetGasto } from '@/Pages/Gastos/Partials/ModalGasto';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import {
    EstadoDocumentoBadge, EstadoViajeBadge, MantenimientoChip, Pastilla, TipoViajeBadge, fmtDia, fmtKg, fmtKm,
    type EstadoMantenimiento, type EstadoViaje, type TipoViaje,
} from '@/Components/Flota/FlotaUI';
import { soles } from '@/lib/dinero';
import { avisoError } from '@/lib/avisoError';
import type { FlotaGasto, GastoTipo, Local, MetodoPagoConCuentas, PageProps, Turno } from '@/types';

interface Carga {
    id: number; gasto_id: number; fecha: string; km: number; km_recorridos: number | null; galones: number; monto: number;
    tanque_lleno: boolean; grifo: string | null; conductor: string | null; rendimiento: number | null; costo_km: number | null; anormal: boolean;
    /** Km, galones y soles del tramo entre dos llenadas (la cuenta del rendimiento). */
    tramo: { km: number; galones: number; monto: number } | null;
}
interface Mant { id: number; gasto_id: number; tipo: 'preventivo' | 'correctivo'; fecha: string; monto: number; km: number | null; taller: string | null; detalle: string | null; dias_parado: number | null }
interface Documento { id: number; tipo: string; numero: string | null; emision: string | null; vence: string | null; estado: 'vigente' | 'por_vencer' | 'vencido' | null; archivo: boolean; archivo_nombre: string | null; observacion: string | null }
interface ViajeFila { id: number; numero: string; tipo: TipoViaje; estado: EstadoViaje; fecha: string; chofer: string | null; paradas: number }
interface Utilidad { margen: number; combustible: number; mantenimiento: number; otros: number; utilidad: number; viajes: number; km: number }

interface Props extends PageProps {
    vehiculo: {
        id: number; placa: string; marca: string | null; modelo: string | null; anio: number | null; tipo: string | null;
        estado: 'disponible' | 'taller' | 'fuera_servicio'; activo: boolean; capacidad_kg: number | null;
        conductor: string | null; conductor_id: number | null; km_actual: number; en_ruta: string | null; mant_intervalo_km: number;
    };
    mantenimiento: EstadoMantenimiento;
    combustible: { cargas: Carga[]; promedio_km_gal: number | null; costo_km: number | null };
    mantenimientos: Mant[];
    documentos: Documento[];
    tiposDocumento: string[];
    capacidades: { producto_id: number; producto: string | null; max_unidades: number; peso_kg: number | null }[];
    productos: { value: number; label: string; peso_kg: number | null }[];
    viajes: ViajeFila[];
    otrosGastos: { id: number; fecha: string; monto: number; concepto: string | null; comentario: string | null }[];
    utilidad: Utilidad | null;
    periodo: { desde: string; hasta: string };
    gasto: { tipos: GastoTipo[]; locales: Local[]; turnosAbiertos: Turno[]; esAdmin: boolean; metodosPago: MetodoPagoConCuentas[]; flota: FlotaGasto | null };
    conceptosFlota: { id: number; gasto_tipo_id: number; flota_uso: 'combustible' | 'mantenimiento' }[];
    puede: { editar: boolean; gastos: boolean };
}

type Pestana = 'combustible' | 'mantenimiento' | 'documentos' | 'viajes' | 'gastos' | 'capacidad';

const fecha = (ymd: string) => {
    const [y, m, d] = ymd.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: '2-digit' });
};

export default function FlotaVehiculo(props: Props) {
    const { vehiculo, mantenimiento, combustible, mantenimientos, documentos, viajes, otrosGastos, utilidad, gasto, conceptosFlota, puede } = props;
    const { flash, turno_activo } = usePage<Props>().props;
    const [pestana, setPestana] = useState<Pestana>('combustible');
    const [preset, setPreset] = useState<PresetGasto | null>(null);
    const [doc, setDoc] = useState<Documento | 'nuevo' | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    // Abre el MISMO modal de gastos, con la placa y el concepto puestos.
    function nuevoGasto(uso: 'combustible' | 'mantenimiento' | null) {
        const c = uso ? conceptosFlota.find(x => x.flota_uso === uso) : null;
        if (uso && !c) {
            toast.error('Primero indica qué concepto de gasto es ' + uso + ' (Resumen de flota → Conceptos de gasto).');
            return;
        }
        setPreset({ vehiculo_id: vehiculo.id, gasto_tipo_id: c?.gasto_tipo_id, gasto_concepto_id: c?.id });
    }

    const docsAlerta = documentos.filter(d => d.estado === 'vencido' || d.estado === 'por_vencer').length;
    const mantColor = mantenimiento.estado === 'vencido' ? 'var(--vp-coral)' : mantenimiento.estado === 'pronto' ? 'var(--vp-amber)' : 'var(--vp-mint)';
    const avance = mantenimiento.faltan !== null ? Math.min(1, Math.max(0, 1 - mantenimiento.faltan / mantenimiento.intervalo)) : 0;

    const pestanas: [Pestana, string, number | null][] = [
        ['combustible', 'Combustible', combustible.cargas.length],
        ['mantenimiento', 'Mantenimiento', mantenimientos.length],
        ['documentos', 'Documentos', documentos.length],
        ['viajes', 'Viajes', viajes.length],
        ['gastos', 'Otros gastos', otrosGastos.length],
        ['capacidad', 'Capacidad', props.capacidades.length],
    ];

    return (
        <AppLayout title={`Vehículo ${vehiculo.placa}`}>
            {/* ── Cabecera ── */}
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-5">
                <div className="min-w-0">
                    <Link href={route('flota.vehiculos.index')} className="text-sm font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>← Vehículos</Link>
                    <div className="flex flex-wrap items-center gap-3 mt-1.5">
                        <span className="font-mono text-xl font-extrabold tracking-wider px-3 py-1 rounded-lg border-[3px]"
                            style={{ borderColor: 'var(--vp-navy)', color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 22%, var(--color-surface))' }}>
                            {vehiculo.placa}
                        </span>
                        <div>
                            <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>
                                {[vehiculo.marca, vehiculo.modelo].filter(Boolean).join(' ') || '—'}{vehiculo.anio ? ` · ${vehiculo.anio}` : ''}
                            </p>
                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                {[vehiculo.tipo, vehiculo.capacidad_kg ? `carga ${fmtKg(vehiculo.capacidad_kg)}` : null, vehiculo.conductor ? `chofer ${vehiculo.conductor}` : null].filter(Boolean).join(' · ')}
                            </p>
                        </div>
                        {vehiculo.en_ruta ? <Pastilla color="var(--vp-navy)" icono={<Truck size={13} />}>En ruta · {vehiculo.en_ruta}</Pastilla>
                            : vehiculo.estado === 'taller' ? <Pastilla color="var(--vp-amber-ink)" icono={<Wrench size={13} />}>En taller</Pastilla>
                            : vehiculo.estado === 'fuera_servicio' ? <Pastilla color="var(--vp-coral-ink)">Fuera de servicio</Pastilla>
                            : <Pastilla color="var(--vp-mint-ink)">Disponible</Pastilla>}
                    </div>
                </div>
                {puede.gastos && (
                    <div className="flex flex-wrap gap-2">
                        <Button onClick={() => nuevoGasto('combustible')} startContent={<Fuel size={15} />}>Cargar combustible</Button>
                        <Button variant="secondary" onClick={() => nuevoGasto('mantenimiento')} startContent={<Wrench size={15} />}>Mantenimiento</Button>
                        <Button variant="ghost" onClick={() => nuevoGasto(null)} startContent={<Receipt size={15} />}>Otro gasto</Button>
                    </div>
                )}
            </div>

            {/* ── Indicadores ── */}
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
                <Tarjeta titulo="Kilometraje" icono={<Gauge size={15} />}>
                    <p className="font-display text-2xl font-extrabold tabular-nums" style={{ color: 'var(--vp-navy)' }}>{vehiculo.km_actual ? fmtKm(vehiculo.km_actual) : '—'}</p>
                    <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>Se actualiza con cada carga de combustible</p>
                </Tarjeta>
                <Tarjeta titulo="Próximo preventivo" icono={<Wrench size={15} />}>
                    <MantenimientoChip m={mantenimiento} />
                    {mantenimiento.proximo_km !== null && (
                        <>
                            <div className="mt-2 h-2 rounded-full overflow-hidden" style={{ backgroundColor: 'color-mix(in srgb, var(--color-text-muted) 15%, transparent)' }}
                                role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(avance * 100)} aria-label="Avance hacia el próximo preventivo">
                                <div className="h-full rounded-full origin-left transition-transform duration-300 ease-out" style={{ backgroundColor: mantColor, transform: `scaleX(${avance})` }} />
                            </div>
                            <p className="text-[12px] mt-1 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                A los {fmtKm(mantenimiento.proximo_km)} · cada {fmtKm(mantenimiento.intervalo)}
                            </p>
                        </>
                    )}
                </Tarjeta>
                <Tarjeta titulo="Rendimiento" icono={<Fuel size={15} />}>
                    <p className="font-display text-2xl font-extrabold tabular-nums" style={{ color: 'var(--vp-navy)' }}>
                        {combustible.promedio_km_gal !== null ? `${combustible.promedio_km_gal} km/gal` : '—'}
                    </p>
                    <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                        {combustible.costo_km !== null ? `Petróleo: ${soles(combustible.costo_km)} por km` : 'Se calcula entre dos tanques llenos'}
                    </p>
                </Tarjeta>
                <Tarjeta titulo="Utilidad del mes" icono={<RutaIcono size={15} />}>
                    <p className="font-display text-2xl font-extrabold tabular-nums" style={{ color: (utilidad?.utilidad ?? 0) >= 0 ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)' }}>
                        {soles(utilidad?.utilidad ?? 0)}
                    </p>
                    <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                        Margen {soles(utilidad?.margen ?? 0)} − gastos {soles((utilidad?.combustible ?? 0) + (utilidad?.mantenimiento ?? 0) + (utilidad?.otros ?? 0))}
                    </p>
                </Tarjeta>
            </div>

            {/* ── Pestañas ── */}
            <div role="tablist" aria-label="Historial del vehículo" className="flex flex-wrap gap-1 mb-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                {pestanas.map(([v, t, n]) => {
                    const activo = pestana === v;
                    return (
                        <button key={v} role="tab" aria-selected={activo} onClick={() => setPestana(v)}
                            className="px-3 py-2 text-sm font-semibold -mb-px border-b-2 transition-colors duration-150"
                            style={{ borderColor: activo ? 'var(--vp-navy)' : 'transparent', color: activo ? 'var(--vp-navy)' : 'var(--color-text-muted)' }}>
                            {t}{n ? <span className="ml-1 tabular-nums opacity-70">{n}</span> : null}
                            {v === 'documentos' && docsAlerta > 0 && <AlertTriangle size={13} className="inline ml-1 -mt-0.5" style={{ color: 'var(--vp-coral-ink)' }} />}
                        </button>
                    );
                })}
            </div>

            <section className="rounded-2xl overflow-hidden" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                {pestana === 'combustible' && <ComoSeCalcula cargas={combustible.cargas} />}
                {pestana === 'combustible' && (
                    <Lista vacio="Todavía no hay cargas de combustible de esta placa." filas={combustible.cargas} render={c => (
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-1">
                            <div className="w-24 flex-shrink-0">
                                <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{fecha(c.fecha)}</p>
                                <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>{c.grifo ?? ''}</p>
                            </div>
                            <Dato titulo="Km" valor={fmtKm(c.km)} sub={c.km_recorridos !== null ? `+${fmtKm(c.km_recorridos)}` : undefined} />
                            <Dato titulo="Galones" valor={`${c.galones}`} sub={c.tanque_lleno ? 'tanque lleno' : 'parcial'} />
                            <Dato titulo="Monto" valor={soles(c.monto)} />
                            <Dato titulo="Rendimiento" valor={c.rendimiento !== null ? `${c.rendimiento} km/gal` : '—'}
                                sub={c.costo_km !== null ? `${soles(c.costo_km)}/km` : c.tanque_lleno ? 'primera llenada' : 'se suma a la próxima llenada'} alerta={c.anormal} />
                            {c.tramo && (
                                <span className="text-[12px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                    {fmtKm(c.tramo.km)} ÷ {c.tramo.galones} gal · {soles(c.tramo.monto)} ÷ {fmtKm(c.tramo.km)}
                                </span>
                            )}
                            <span className="text-[13px] ml-auto" style={{ color: 'var(--color-text-muted)' }}>{c.conductor ?? ''}</span>
                            {c.anormal && <Pastilla color="var(--vp-coral-ink)" icono={<AlertTriangle size={12} />}>Consumo alto</Pastilla>}
                        </div>
                    )} />
                )}

                {pestana === 'mantenimiento' && (
                    <Lista vacio="Todavía no hay mantenimientos de esta placa." filas={mantenimientos} render={m => (
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-1">
                            <div className="w-24 flex-shrink-0">
                                <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{fecha(m.fecha)}</p>
                                <Pastilla color={m.tipo === 'preventivo' ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)'}>{m.tipo === 'preventivo' ? 'Preventivo' : 'Correctivo'}</Pastilla>
                            </div>
                            <Dato titulo="Km" valor={m.km !== null ? fmtKm(m.km) : '—'} />
                            <Dato titulo="Costo" valor={soles(m.monto)} />
                            {m.dias_parado ? <Dato titulo="Parado" valor={`${m.dias_parado} ${m.dias_parado === 1 ? 'día' : 'días'}`} /> : null}
                            <div className="min-w-0 flex-1">
                                <p className="text-sm truncate" style={{ color: 'var(--color-text)' }}>{m.detalle ?? '—'}</p>
                                {m.taller && <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>{m.taller}</p>}
                            </div>
                        </div>
                    )} />
                )}

                {pestana === 'documentos' && (
                    <>
                        {puede.editar && (
                            <div className="px-4 pt-3 flex justify-end">
                                <Button size="sm" variant="secondary" onClick={() => setDoc('nuevo')} startContent={<Plus size={14} />}>Agregar documento</Button>
                            </div>
                        )}
                        <Lista vacio="Registra el SOAT, la revisión técnica y los demás documentos para que el sistema avise antes de que venzan." filas={documentos} render={d => (
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-1">
                                <div className="min-w-[10rem] flex-1">
                                    <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{d.tipo}</p>
                                    <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>{[d.numero, d.emision ? `emitido ${fecha(d.emision)}` : null].filter(Boolean).join(' · ') || '—'}</p>
                                </div>
                                <EstadoDocumentoBadge estado={d.estado} vence={d.vence} />
                                {d.archivo && (
                                    <a href={route('flota.documentos.archivo', d.id)} target="_blank" rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-[13px] font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                                        <Paperclip size={13} /> Ver archivo
                                    </a>
                                )}
                                {puede.editar && <Button size="sm" variant="ghost" onClick={() => setDoc(d)}>Editar</Button>}
                            </div>
                        )} />
                    </>
                )}

                {pestana === 'viajes' && (
                    <Lista vacio="Este vehículo todavía no tiene viajes." filas={viajes} render={v => (
                        <Link href={route('viajes.show', v.id)} className="flex flex-wrap items-center gap-x-4 gap-y-1 -m-1 p-1 rounded-lg hover:bg-black/[0.02]">
                            <span className="text-sm font-bold w-20" style={{ color: 'var(--color-text)' }}>{v.numero}</span>
                            <span className="text-[13px] w-28" style={{ color: 'var(--color-text-muted)' }}>{fmtDia(v.fecha)}</span>
                            <span className="text-[13px] flex-1 min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{v.chofer ?? 'Sin chofer'} · {v.paradas} {v.paradas === 1 ? 'parada' : 'paradas'}</span>
                            {v.tipo !== 'reparto' && <TipoViajeBadge tipo={v.tipo} />}
                            <EstadoViajeBadge estado={v.estado} />
                        </Link>
                    )} />
                )}

                {pestana === 'capacidad' && (
                    <Capacidades vehiculoId={vehiculo.id} capacidadKg={vehiculo.capacidad_kg} inicial={props.capacidades}
                        productos={props.productos} puedeEditar={puede.editar} />
                )}

                {pestana === 'gastos' && (
                    <Lista vacio="Sin otros gastos cargados a esta placa (peajes, viáticos, letras…)." filas={otrosGastos} render={g => (
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-1">
                            <span className="text-sm font-semibold w-24" style={{ color: 'var(--color-text)' }}>{fecha(g.fecha)}</span>
                            <span className="text-sm flex-1 min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{g.concepto ?? '—'}{g.comentario ? <span style={{ color: 'var(--color-text-muted)' }}> · {g.comentario}</span> : null}</span>
                            <span className="text-sm font-semibold tabular-nums" style={{ color: 'var(--color-text)' }}>{soles(g.monto)}</span>
                        </div>
                    )} />
                )}
            </section>

            <p className="mt-4 text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                El combustible y el mantenimiento se registran como gastos: salen de caja y tesorería, y se corrigen o eliminan desde Gastos.
            </p>

            <ModalGasto
                isOpen={!!preset}
                onClose={() => setPreset(null)}
                tipos={gasto.tipos}
                turnoActivo={turno_activo ?? null}
                locales={gasto.locales}
                esAdmin={gasto.esAdmin}
                turnosAbiertos={gasto.turnosAbiertos ?? []}
                metodosPago={gasto.metodosPago ?? []}
                flota={gasto.flota}
                preset={preset}
            />
            <ModalDocumento vehiculoId={vehiculo.id} doc={doc} tipos={props.tiposDocumento} onClose={() => setDoc(null)} />
        </AppLayout>
    );
}

function Tarjeta({ titulo, icono, children }: { titulo: string; icono: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl p-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <p className="text-[13px] font-semibold flex items-center gap-1.5 mb-2" style={{ color: 'var(--color-text-muted)' }}>{icono}{titulo}</p>
            {children}
        </div>
    );
}

function Dato({ titulo, valor, sub, alerta = false }: { titulo: string; valor: string; sub?: string; alerta?: boolean }) {
    return (
        <div className="min-w-[5.5rem]">
            <p className="text-[11px] uppercase tracking-wide font-semibold" style={{ color: 'var(--color-text-muted)' }}>{titulo}</p>
            <p className="text-sm font-semibold tabular-nums" style={{ color: alerta ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}>{valor}</p>
            {sub && <p className="text-[12px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{sub}</p>}
        </div>
    );
}

function Lista<T extends { id: number }>({ filas, render, vacio }: { filas: T[]; render: (f: T) => React.ReactNode; vacio: string }) {
    if (filas.length === 0) return <p className="px-4 py-10 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>{vacio}</p>;
    return (
        <ul>
            {filas.map((f, i) => (
                <li key={f.id} className="px-4 py-3 vp-fade-up" style={{ borderTop: i ? '1px solid var(--color-border)' : undefined, animationDelay: `${Math.min(i, 8) * 25}ms` }}>
                    {render(f)}
                </li>
            ))}
        </ul>
    );
}

function ModalDocumento({ vehiculoId, doc, tipos, onClose }: { vehiculoId: number; doc: Documento | 'nuevo' | null; tipos: string[]; onClose: () => void }) {
    const editando = doc && doc !== 'nuevo' ? doc : null;
    const [form, setForm] = useState({ tipo: '', numero: '', emision: '', vence: '', observacion: '' });
    const [archivo, setArchivo] = useState<File | null>(null);
    const [quitar, setQuitar] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const [borrar, setBorrar] = useState(false);

    useEffect(() => {
        if (!doc) return;
        setForm(editando
            ? { tipo: editando.tipo, numero: editando.numero ?? '', emision: editando.emision ?? '', vence: editando.vence ?? '', observacion: editando.observacion ?? '' }
            : { tipo: tipos[0] ?? '', numero: '', emision: '', vence: '', observacion: '' });
        setArchivo(null); setQuitar(false); setErrores({}); setBorrar(false);
    }, [doc]); // eslint-disable-line react-hooks/exhaustive-deps

    function guardar() {
        setGuardando(true);
        const url = editando ? route('flota.documentos.update', [vehiculoId, editando.id]) : route('flota.documentos.store', vehiculoId);
        router.post(url, { ...form, archivo, quitar_archivo: quitar }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: e => { setErrores(e as Record<string, string>); avisoError(Object.values(e)[0]); },
            onFinish: () => setGuardando(false),
        });
    }

    function eliminar() {
        if (!editando) return;
        router.delete(route('flota.documentos.destroy', editando.id), { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal isOpen={!!doc} onClose={onClose} size="md" title={editando ? `Editar ${editando.tipo}` : 'Agregar documento'}
            footer={<>
                {editando && (borrar
                    ? <Button variant="danger" onClick={eliminar} startContent={<Trash2 size={14} />}>Sí, eliminar</Button>
                    : <Button variant="ghost" onClick={() => setBorrar(true)} startContent={<Trash2 size={14} />}>Eliminar</Button>)}
                <span className="flex-1" />
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={guardar} loading={guardando}>Guardar</Button>
            </>}>
            <div className="space-y-3">
                <div>
                    <Input label="Documento" required list="tipos-documento" value={form.tipo} onChange={e => setForm(f => ({ ...f, tipo: e.target.value }))} error={errores.tipo} />
                    <datalist id="tipos-documento">{tipos.map(t => <option key={t} value={t} />)}</datalist>
                </div>
                <Input label="Número" value={form.numero} onChange={e => setForm(f => ({ ...f, numero: e.target.value }))} placeholder="Opcional" error={errores.numero} />
                <div className="grid grid-cols-2 gap-3">
                    <Input label="Emisión" type="date" value={form.emision} onChange={e => setForm(f => ({ ...f, emision: e.target.value }))} error={errores.emision} />
                    <Input label="Vence" type="date" value={form.vence} onChange={e => setForm(f => ({ ...f, vence: e.target.value }))} error={errores.vence} hint="Avisa 30 días antes" />
                </div>
                <div>
                    <p className="text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>Archivo escaneado (PDF o foto)</p>
                    {editando?.archivo && !quitar && !archivo && (
                        <p className="text-[13px] mb-1.5 flex items-center gap-2" style={{ color: 'var(--color-text-muted)' }}>
                            <FileText size={13} /> {editando.archivo_nombre ?? 'archivo'}
                            <button type="button" className="font-semibold hover:underline" style={{ color: 'var(--color-danger)' }} onClick={() => setQuitar(true)}>Quitar</button>
                        </p>
                    )}
                    <input type="file" accept="application/pdf,image/*" onChange={e => { setArchivo(e.target.files?.[0] ?? null); setQuitar(false); }}
                        className="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-2 file:font-semibold"
                        style={{ color: 'var(--color-text-muted)' }} />
                    {errores.archivo && <p className="text-[12px] mt-1" style={{ color: 'var(--color-danger)' }}>{errores.archivo}</p>}
                </div>
                <Input label="Observación" value={form.observacion} onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))} placeholder="Opcional" />
            </div>
        </Modal>
    );
}

/**
 * Cómo salen el rendimiento y el costo por km, con la última llenada como
 * ejemplo. Sin esto el número «12 km/gal» no se entiende ni se confía en él.
 */
function ComoSeCalcula({ cargas }: { cargas: Carga[] }) {
    const ej = cargas.find(c => c.tramo);
    const pasos: [string, ReactNode][] = [
        ['1. Cada vez que le echan', <>Registra el <b>km del tablero</b> y los <b>galones</b> del voucher, con lo pagado.</>],
        ['2. Km por galón', ej?.tramo
            ? <>De una llenada a la otra: <b className="tabular-nums">{fmtKm(ej.tramo.km)} ÷ {ej.tramo.galones} gal = {ej.rendimiento} km/gal</b></>
            : <>De una llenada hasta el tope a la siguiente: km recorridos ÷ galones echados en ese tramo.</>],
        ['3. Costo por km', ej?.tramo
            ? <>Lo pagado en ese tramo: <b className="tabular-nums">{soles(ej.tramo.monto)} ÷ {fmtKm(ej.tramo.km)} = {soles(ej.costo_km)} por km</b></>
            : <>Soles pagados en ese tramo ÷ km recorridos.</>],
    ];
    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-px" style={{ backgroundColor: 'var(--color-border)', borderBottom: '1px solid var(--color-border)' }}>
            {pasos.map(([t, d]) => (
                <div key={t} className="p-3" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 4%, var(--color-surface))' }}>
                    <p className="text-[12px] font-bold uppercase tracking-wide" style={{ color: 'var(--vp-navy)' }}>{t}</p>
                    <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{d}</p>
                </div>
            ))}
            {!ej && (
                <p className="md:col-span-3 px-3 py-2 text-[12px]" style={{ backgroundColor: 'var(--color-surface)', color: 'var(--color-text-muted)' }}>
                    Para medir hacen falta <b>dos llenadas hasta el tope</b>. Las cargas parciales de en medio se suman a la siguiente llenada.
                </p>
            )}
        </div>
    );
}

/**
 * Unidades máximas por producto en este camión (volumen de tolva o baranda).
 * El viaje usa el menor entre esto y lo que permite el peso.
 */
function Capacidades({ vehiculoId, capacidadKg, inicial, productos, puedeEditar }: {
    vehiculoId: number; capacidadKg: number | null; inicial: Props['capacidades']; productos: Props['productos']; puedeEditar: boolean;
}) {
    const [filas, setFilas] = useState(inicial.map(c => ({ producto_id: c.producto_id as number | '', max_unidades: String(c.max_unidades) })));
    const [guardando, setGuardando] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});

    function guardar() {
        setGuardando(true);
        router.put(route('flota.vehiculos.capacidades', vehiculoId), {
            capacidades: filas.filter(f => f.producto_id && Number(f.max_unidades) > 0).map(f => ({ producto_id: f.producto_id, max_unidades: Number(f.max_unidades) })),
        } as unknown as Record<string, string>, {
            preserveScroll: true,
            onError: e => { setErrores(e as Record<string, string>); avisoError(Object.values(e)[0]); },
            onFinish: () => setGuardando(false),
        });
    }

    return (
        <div className="p-4 space-y-3">
            <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                Cuántas unidades de cada producto entran por volumen (tolva o baranda). Al armar un viaje se usa el menor entre esto y lo que permite el peso
                {capacidadKg ? ` (${fmtKg(capacidadKg)})` : ''}: en el ladrillo de techo el camión se llena antes de llegar al peso.
            </p>
            {filas.length === 0 && <p className="text-sm py-4 text-center" style={{ color: 'var(--color-text-muted)' }}>Sin límites por volumen: se usa solo el peso.</p>}
            {filas.map((f, i) => {
                const prod = productos.find(p => p.value === f.producto_id);
                const porPeso = prod?.peso_kg && capacidadKg ? Math.floor(capacidadKg / prod.peso_kg) : null;
                return (
                    <div key={i} className="flex flex-wrap items-center gap-3">
                        <div className="flex-1 min-w-[14rem]">
                            <SearchableSelect value={f.producto_id} placeholder="Elegir producto…" options={productos} disabled={!puedeEditar}
                                error={errores[`capacidades.${i}.producto_id`]}
                                onChange={(v: string | number) => setFilas(fs => fs.map((x, j) => j === i ? { ...x, producto_id: Number(v) || '' } : x))} />
                        </div>
                        <input type="number" min={1} inputMode="numeric" aria-label="Unidades máximas por volumen" value={f.max_unidades} disabled={!puedeEditar}
                            onChange={e => setFilas(fs => fs.map((x, j) => j === i ? { ...x, max_unidades: e.target.value } : x))}
                            className="w-32 h-10 rounded-lg border px-2 text-right text-sm tabular-nums outline-none focus:ring-2"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                        <span className="text-[13px] w-40" style={{ color: 'var(--color-text-muted)' }}>
                            {porPeso ? `por peso entrarían ${porPeso.toLocaleString('es-PE')}` : 'unidades'}
                        </span>
                        {puedeEditar && (
                            <button type="button" aria-label="Quitar" onClick={() => setFilas(fs => fs.filter((_, j) => j !== i))}
                                className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-black/5" style={{ color: 'var(--color-danger)' }}><Trash2 size={15} /></button>
                        )}
                    </div>
                );
            })}
            {puedeEditar && (
                <div className="flex justify-between pt-1">
                    <Button size="sm" variant="ghost" onClick={() => setFilas(fs => [...fs, { producto_id: '', max_unidades: '' }])} startContent={<Plus size={14} />}>Agregar producto</Button>
                    <Button size="sm" onClick={guardar} loading={guardando}>Guardar capacidad</Button>
                </div>
            )}
        </div>
    );
}
