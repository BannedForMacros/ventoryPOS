import { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { AlertTriangle, BadgeDollarSign, CalendarRange, Fuel, Gauge, Settings2, Trophy, Truck, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import Checkbox from '@/Components/UI/Checkbox';
import Callout from '@/Components/UI/Callout';
import { Kpi } from '@/Components/Reportes/ReportUI';
import { Pastilla, fmtKm } from '@/Components/Flota/FlotaUI';
import { soles } from '@/lib/dinero';
import { avisoError } from '@/lib/avisoError';
import type { PageProps } from '@/types';

type Periodo = 'semana' | 'semana_pasada' | 'mes' | 'mes_pasado' | 'rango';

interface Alerta { tipo: 'mantenimiento' | 'documento' | 'licencia'; nivel: 'rojo' | 'ambar'; titulo: string; detalle: string; vehiculo_id: number | null }

interface FilaUtilidad {
    vehiculo_id: number; placa: string; descripcion: string; viajes: number; vueltas: number;
    venta: number; margen: number; combustible: number; mantenimiento: number; otros: number; gastos: number;
    utilidad: number; km: number; galones: number; costo_km: number | null; mant_km: number | null;
}

interface Concepto { id: number; nombre: string; tipo: string | null; flota_uso: 'combustible' | 'mantenimiento' | null }

interface Props extends PageProps {
    alertas: Alerta[];
    utilidad: FilaUtilidad[];
    periodo: { clave: Periodo; desde: string; hasta: string };
    conceptos: Concepto[];
    puede: { editar: boolean };
}

const PERIODOS: [Periodo, string][] = [['semana', 'Esta semana'], ['semana_pasada', 'Semana pasada'], ['mes', 'Este mes'], ['mes_pasado', 'Mes pasado']];

const fecha = (ymd: string) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short' });
};

export default function FlotaResumen({ alertas, utilidad, periodo, conceptos, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [config, setConfig] = useState(false);
    const [desde, setDesde] = useState(periodo.desde);
    const [hasta, setHasta] = useState(periodo.hasta);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const ir = (p: Record<string, string>) => router.get(route('flota.resumen'), p, { preserveScroll: true, preserveState: true, only: ['utilidad', 'periodo'] });

    const tot = useMemo(() => utilidad.reduce((a, f) => ({
        utilidad: a.utilidad + f.utilidad, margen: a.margen + f.margen, combustible: a.combustible + f.combustible,
        mantenimiento: a.mantenimiento + f.mantenimiento, viajes: a.viajes + f.viajes,
    }), { utilidad: 0, margen: 0, combustible: 0, mantenimiento: 0, viajes: 0 }), [utilidad]);

    // Solo se destaca un camión que de verdad dejó ganancia.
    const mejor = utilidad.find(f => f.utilidad > 0);
    const masMant = [...utilidad].sort((a, b) => b.mantenimiento - a.mantenimiento)[0];
    const sinConceptos = !conceptos.some(c => c.flota_uso);

    return (
        <AppLayout title="Resumen de flota">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-5">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Resumen de flota</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Cuánto dejó cada camión: el margen de lo que despachó menos su petróleo, mantenimiento y demás gastos.
                    </p>
                </div>
                {puede.editar && (
                    <Button variant="ghost" onClick={() => setConfig(true)} startContent={<Settings2 size={15} />}>Conceptos de gasto</Button>
                )}
            </div>

            {sinConceptos && (
                <Callout variant="warning" className="mb-4" title="Indica qué gastos son combustible y mantenimiento"
                    accion={puede.editar ? { label: 'Configurar', onClick: () => setConfig(true) } : undefined}>
                    Así, al registrar ese gasto, se piden el km y los galones (o el taller), y el rendimiento y el contador del preventivo se calculan solos.
                </Callout>
            )}

            {/* ── Alertas ── */}
            <section className="mb-5">
                <h2 className="text-base font-bold mb-2 flex items-center gap-2" style={{ color: 'var(--color-text)' }}>
                    <AlertTriangle size={16} style={{ color: alertas.length ? 'var(--vp-coral-ink)' : 'var(--vp-mint-ink)' }} />
                    {alertas.length ? `${alertas.length} ${alertas.length === 1 ? 'cosa por atender' : 'cosas por atender'}` : 'Todo al día'}
                </h2>
                {alertas.length === 0 ? (
                    <p className="text-sm rounded-2xl px-4 py-3" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                        Sin documentos, licencias ni mantenimientos por vencer.
                    </p>
                ) : (
                    <ul className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                        {alertas.map((a, i) => {
                            const color = a.nivel === 'rojo' ? 'var(--vp-coral)' : 'var(--vp-amber)';
                            const Icono = a.tipo === 'mantenimiento' ? Wrench : a.tipo === 'licencia' ? AlertTriangle : CalendarRange;
                            const contenido = (
                                <div className="flex items-start gap-3 rounded-xl px-3.5 py-3 h-full transition-transform duration-150 ease-out hover:-translate-y-0.5"
                                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: `inset 4px 0 0 ${color}` }}>
                                    <Icono size={16} className="flex-shrink-0 mt-0.5" style={{ color: a.nivel === 'rojo' ? 'var(--vp-coral-ink)' : 'var(--vp-amber-ink)' }} />
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>{a.titulo}</p>
                                        <p className="text-[13px]" style={{ color: a.nivel === 'rojo' ? 'var(--vp-coral-ink)' : 'var(--vp-amber-ink)' }}>{a.detalle}</p>
                                    </div>
                                </div>
                            );
                            return (
                                <li key={i} className="vp-fade-up" style={{ animationDelay: `${Math.min(i, 8) * 30}ms` }}>
                                    {a.vehiculo_id ? <Link href={route('flota.vehiculos.show', a.vehiculo_id)} className="block h-full">{contenido}</Link>
                                        : <Link href={route('flota.conductores.index')} className="block h-full">{contenido}</Link>}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            {/* ── Período ── */}
            <section className="rounded-2xl p-3 mb-4 flex flex-wrap items-center gap-3"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="flex flex-wrap rounded-xl p-0.5" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 5%, transparent)' }}>
                    {PERIODOS.map(([v, t]) => {
                        const activo = periodo.clave === v;
                        return (
                            <button key={v} onClick={() => ir({ periodo: v })} aria-pressed={activo}
                                className="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                style={{ backgroundColor: activo ? 'var(--color-surface)' : 'transparent', color: activo ? 'var(--vp-navy)' : 'var(--color-text-muted)', boxShadow: activo ? '0 1px 3px rgb(0 0 0 / 0.1)' : undefined }}>
                                {t}
                            </button>
                        );
                    })}
                </div>
                <div className="flex items-center gap-2">
                    <input type="date" value={desde} onChange={e => setDesde(e.target.value)} aria-label="Desde"
                        className="rounded-xl border px-3 py-2 text-sm" style={{ borderColor: periodo.clave === 'rango' ? 'var(--vp-navy)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                    <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>al</span>
                    <input type="date" value={hasta} onChange={e => setHasta(e.target.value)} aria-label="Hasta"
                        className="rounded-xl border px-3 py-2 text-sm" style={{ borderColor: periodo.clave === 'rango' ? 'var(--vp-navy)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                    <Button size="sm" variant="ghost" onClick={() => ir({ periodo: 'rango', desde, hasta })}>Ver</Button>
                </div>
                <span className="text-[13px] ml-auto" style={{ color: 'var(--color-text-muted)' }}>{fecha(periodo.desde)} al {fecha(periodo.hasta)}</span>
            </section>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <Kpi icon={<BadgeDollarSign size={18} />} label="Utilidad de la flota" value={soles(tot.utilidad)}
                    sub={`${tot.viajes} viajes`} color={tot.utilidad >= 0 ? 'var(--vp-mint)' : 'var(--vp-coral)'} />
                <Kpi icon={<Fuel size={18} />} label="Combustible" value={soles(tot.combustible)} color="var(--vp-amber)" />
                <Kpi icon={<Wrench size={18} />} label="Mantenimiento" value={soles(tot.mantenimiento)}
                    sub={masMant && masMant.mantenimiento > 0 ? `Más gasta: ${masMant.placa}` : undefined} color="var(--vp-coral)" />
                <Kpi icon={<Trophy size={18} />} label="Camión que más dejó" value={mejor ? mejor.placa : '—'}
                    sub={mejor ? soles(mejor.utilidad) : 'Ninguno con utilidad en el período'} color="var(--vp-navy)" />
            </div>

            {/* ── Utilidad por placa ── */}
            <section className="rounded-2xl overflow-hidden" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="px-4 py-3 flex items-center gap-2" style={{ borderBottom: '1px solid var(--color-border)' }}>
                    <Gauge size={16} style={{ color: 'var(--vp-navy)' }} />
                    <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>Utilidad por camión</h2>
                </div>
                {utilidad.length === 0 ? (
                    <p className="px-4 py-10 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>
                        Registra tus vehículos para ver cuánto deja cada uno.
                    </p>
                ) : (
                    <>
                        {/* Escritorio: tabla */}
                        <div className="hidden md:block overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead style={{ backgroundColor: 'var(--color-bg)' }}>
                                    <tr className="text-left text-[12px] uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                        <th className="px-4 py-2.5 font-semibold">Placa</th>
                                        <th className="px-3 py-2.5 font-semibold text-right">Viajes</th>
                                        <th className="px-3 py-2.5 font-semibold text-right">Margen despachado</th>
                                        <th className="px-3 py-2.5 font-semibold text-right">Combustible</th>
                                        <th className="px-3 py-2.5 font-semibold text-right">Mantenimiento</th>
                                        <th className="px-3 py-2.5 font-semibold text-right">Otros gastos</th>
                                        <th className="px-3 py-2.5 font-semibold text-right">Km · costo/km</th>
                                        <th className="px-4 py-2.5 font-semibold text-right">Utilidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {utilidad.map((f, i) => (
                                        <tr key={f.vehiculo_id} className="transition-colors hover:bg-black/[0.02] cursor-pointer"
                                            style={{ borderTop: i ? '1px solid var(--color-border)' : undefined }}
                                            onClick={() => router.visit(route('flota.vehiculos.show', f.vehiculo_id))}>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2.5">
                                                    {f === mejor && <Trophy size={14} style={{ color: 'var(--vp-amber-ink)' }} aria-label="Primero" />}
                                                    <span className="font-mono text-[13px] font-bold px-1.5 py-0.5 rounded border-2"
                                                        style={{ borderColor: 'var(--vp-navy)', color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 18%, var(--color-surface))' }}>{f.placa}</span>
                                                    <span className="text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>{f.descripcion}</span>
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums">{f.viajes}{f.vueltas ? <span style={{ color: 'var(--color-text-muted)' }}> · {f.vueltas} vueltas</span> : null}</td>
                                            <td className="px-3 py-3 text-right tabular-nums">{soles(f.margen)}</td>
                                            <td className="px-3 py-3 text-right tabular-nums" style={{ color: f.combustible ? 'var(--color-text)' : 'var(--color-text-muted)' }}>{soles(f.combustible)}</td>
                                            <td className="px-3 py-3 text-right tabular-nums" style={{ color: f.mantenimiento ? 'var(--color-text)' : 'var(--color-text-muted)' }}>{soles(f.mantenimiento)}</td>
                                            <td className="px-3 py-3 text-right tabular-nums" style={{ color: f.otros ? 'var(--color-text)' : 'var(--color-text-muted)' }}>{soles(f.otros)}</td>
                                            <td className="px-3 py-3 text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                                {f.km > 0 ? <>{fmtKm(f.km)}{f.costo_km !== null ? ` · ${soles(f.costo_km)}` : ''}</> : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums font-bold" style={{ color: f.utilidad >= 0 ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)' }}>{soles(f.utilidad)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {/* Celular: tarjetas */}
                        <ul className="md:hidden divide-y" style={{ borderColor: 'var(--color-border)' }}>
                            {utilidad.map(f => (
                                <li key={f.vehiculo_id}>
                                    <Link href={route('flota.vehiculos.show', f.vehiculo_id)} className="block px-4 py-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-mono text-[13px] font-bold" style={{ color: 'var(--vp-navy)' }}>{f.placa}</span>
                                            <span className="tabular-nums font-bold" style={{ color: f.utilidad >= 0 ? 'var(--vp-mint-ink)' : 'var(--vp-coral-ink)' }}>{soles(f.utilidad)}</span>
                                        </div>
                                        <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                            {f.viajes} viajes · margen {soles(f.margen)} · combustible {soles(f.combustible)} · mant. {soles(f.mantenimiento)}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
                <p className="px-4 py-3 text-[12px]" style={{ borderTop: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                    Margen = lo cobrado por lo que el camión entregó menos su costo. Los gastos son los que se registraron con su placa (combustible, mantenimiento, peajes…).
                    El pago a choferes y ayudantes se sumará en la siguiente fase.
                </p>
            </section>

            <ModalConceptos abierto={config} onClose={() => setConfig(false)} conceptos={conceptos} />
        </AppLayout>
    );
}

function ModalConceptos({ abierto, onClose, conceptos }: { abierto: boolean; onClose: () => void; conceptos: Concepto[] }) {
    const [uso, setUso] = useState<Record<number, 'combustible' | 'mantenimiento' | null>>({});
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (abierto) setUso(Object.fromEntries(conceptos.map(c => [c.id, c.flota_uso])));
    }, [abierto, conceptos]);

    const alternar = (id: number, v: 'combustible' | 'mantenimiento') => setUso(u => ({ ...u, [id]: u[id] === v ? null : v }));

    function guardar() {
        setGuardando(true);
        router.put(route('flota.conceptos'), {
            combustible: Object.entries(uso).filter(([, v]) => v === 'combustible').map(([k]) => Number(k)),
            mantenimiento: Object.entries(uso).filter(([, v]) => v === 'mantenimiento').map(([k]) => Number(k)),
        }, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: e => avisoError(Object.values(e)[0]),
            onFinish: () => setGuardando(false),
        });
    }

    return (
        <Modal isOpen={abierto} onClose={onClose} size="lg" title="¿Qué gastos son de la flota?"
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={guardar} loading={guardando}>Guardar</Button>
            </>}>
            <p className="text-sm mb-3" style={{ color: 'var(--color-text-muted)' }}>
                Marca los conceptos de gasto que usas para el petróleo y para el mantenimiento. Al registrarlos se pedirá la placa y su ficha (km, galones, taller).
            </p>
            {conceptos.length === 0 ? (
                <p className="text-sm py-6 text-center" style={{ color: 'var(--color-text-muted)' }}>No tienes conceptos de gasto. Créalos en Configuración → Tipos de gasto.</p>
            ) : (
                <ul className="max-h-[50vh] overflow-y-auto divide-y" style={{ borderColor: 'var(--color-border)' }}>
                    {conceptos.map(c => (
                        <li key={c.id} className="flex items-center gap-3 py-2">
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>{c.nombre}</p>
                                {c.tipo && <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>{c.tipo}</p>}
                            </div>
                            <label className="flex items-center gap-1.5 text-[13px] cursor-pointer" style={{ color: 'var(--color-text)' }}>
                                <Checkbox checked={uso[c.id] === 'combustible'} onChange={() => alternar(c.id, 'combustible')} /> <Fuel size={13} /> Combustible
                            </label>
                            <label className="flex items-center gap-1.5 text-[13px] cursor-pointer" style={{ color: 'var(--color-text)' }}>
                                <Checkbox checked={uso[c.id] === 'mantenimiento'} onChange={() => alternar(c.id, 'mantenimiento')} /> <Wrench size={13} /> Mantenimiento
                            </label>
                        </li>
                    ))}
                </ul>
            )}
            <div className="mt-3"><Pastilla color="var(--color-text-muted)" icono={<Truck size={12} />}>Los demás gastos también se pueden cargar a una placa, sin ficha.</Pastilla></div>
        </Modal>
    );
}
