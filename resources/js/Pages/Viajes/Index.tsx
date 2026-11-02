import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { CalendarClock, CheckCircle2, MapPin, PackageOpen, Plus, Search, Truck, User, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import { Empty, Kpi, Paginacion, type Paginado } from '@/Components/Reportes/ReportUI';
import {
    BarraCarga, EstadoViajeBadge, TipoViajeBadge, fmtDia,
    type Carga, type EstadoViaje, type TipoViaje,
} from '@/Components/Flota/FlotaUI';
import { useTiempoReal } from '@/lib/useTiempoReal';
import { hoyLocal } from '@/lib/fechas';
import type { PageProps } from '@/types';

interface ViajeFila {
    id: number;
    numero: string;
    tipo: TipoViaje;
    fecha: string;
    hora_salida: string | null;
    estado: EstadoViaje;
    placa: string | null;
    vehiculo: string;
    chofer: string | null;
    ruta: string | null;
    destino: string | null;
    paradas: number;
    entregadas: number;
    por_entregar: number;
    carga: Carga;
}

interface Props extends PageProps {
    viajes: Paginado<ViajeFila> & { links: { url: string | null; label: string; active: boolean }[] };
    filtros: { fecha: string | null; estado: EstadoViaje | null; vehiculo_id: number | null; buscar: string; agenda: boolean };
    resumen: { en_ruta: number; programados: number; cerrados: number; por_cargar: number };
    vehiculos: { value: number; label: string }[];
    puede: { crear: boolean };
}

const ESTADOS: { value: EstadoViaje | ''; label: string }[] = [
    { value: '', label: 'Todos los estados' },
    { value: 'programado', label: 'Programados' },
    { value: 'en_ruta', label: 'En ruta' },
    { value: 'cerrado', label: 'Cerrados' },
    { value: 'anulado', label: 'Anulados' },
];

export default function ViajesIndex({ viajes, filtros, resumen, vehiculos, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [q, setQ] = useState(filtros.buscar ?? '');

    useTiempoReal(['viajes'], () => router.reload({ only: ['viajes', 'resumen'] }));

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const ir = (extra: Record<string, unknown>) => {
        const p: Record<string, unknown> = {
            fecha: filtros.fecha ?? undefined, estado: filtros.estado ?? undefined,
            vehiculo_id: filtros.vehiculo_id ?? undefined, buscar: q || undefined, ...extra,
        };
        Object.keys(p).forEach(k => (p[k] === undefined || p[k] === '') && delete p[k]);
        router.get(route('viajes.index'), p as Record<string, string>, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        if (q === (filtros.buscar ?? '')) return;
        const t = setTimeout(() => ir({ buscar: q || undefined, page: undefined }), 400);
        return () => clearTimeout(t);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const hayFiltros = !filtros.agenda;

    return (
        <AppLayout title="Viajes">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-5">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Viajes</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Qué camión sale, con qué chofer y qué entregas lleva. La mercadería sale del stock al marcar cada entrega.
                    </p>
                </div>
                {puede.crear && (
                    <Link href={route('viajes.create')}>
                        <Button startContent={<Plus size={15} />}>Nuevo viaje</Button>
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <BotonKpi onClick={() => ir({ estado: 'en_ruta', fecha: undefined, page: undefined })}>
                    <Kpi icon={<Truck size={18} />} label="En ruta ahora" value={String(resumen.en_ruta)} color="var(--vp-navy)" />
                </BotonKpi>
                <BotonKpi onClick={() => ir({ estado: 'programado', fecha: undefined, page: undefined })}>
                    <Kpi icon={<CalendarClock size={18} />} label="Programados hoy" value={String(resumen.programados)} color="var(--vp-amber)" />
                </BotonKpi>
                <BotonKpi onClick={() => ir({ estado: 'cerrado', fecha: hoyLocal(), page: undefined })}>
                    <Kpi icon={<CheckCircle2 size={18} />} label="Cerrados hoy" value={String(resumen.cerrados)} color="var(--vp-mint)" />
                </BotonKpi>
                <Link href={route('despachos.index')} className="block rounded-2xl transition-transform duration-150 ease-out hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                    <Kpi icon={<PackageOpen size={18} />} label="Pedidos sin cargar" value={String(resumen.por_cargar)}
                        sub={resumen.por_cargar > 0 ? 'Ver en Despachos' : 'Todo está cargado'} color="var(--vp-coral)" />
                </Link>
            </div>

            <section className="rounded-2xl p-3 mb-4 flex flex-wrap items-center gap-3"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <input type="date" value={filtros.fecha ?? ''} aria-label="Ver los viajes de un día"
                    onChange={e => ir({ fecha: e.target.value || undefined, page: undefined })}
                    className="rounded-xl border px-3 py-2 text-sm"
                    style={{ borderColor: filtros.fecha ? 'var(--vp-navy)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                <div className="w-48">
                    <Select size="sm" ariaLabel="Estado" value={filtros.estado ?? ''} options={ESTADOS}
                        onChange={v => ir({ estado: v || undefined, page: undefined })} />
                </div>
                {vehiculos.length > 1 && (
                    <div className="w-44">
                        <Select size="sm" ariaLabel="Vehículo" value={filtros.vehiculo_id ?? ''}
                            options={[{ value: '', label: 'Todos los vehículos' }, ...vehiculos]}
                            onChange={v => ir({ vehiculo_id: v || undefined, page: undefined })} />
                    </div>
                )}
                <div className="relative flex-1 min-w-[220px]">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Buscar viaje, venta o destino" aria-label="Buscar viaje"
                        className="w-full text-sm rounded-xl pl-9 pr-3 py-2 border outline-none focus:ring-2"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                </div>
                {hayFiltros ? (
                    <button onClick={() => { setQ(''); router.get(route('viajes.index'), {}, { preserveScroll: true }); }}
                        className="inline-flex items-center gap-1 text-sm font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <X size={14} /> Volver a la agenda
                    </button>
                ) : (
                    <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Hoy y lo que sigue abierto</span>
                )}
            </section>

            {viajes.data.length === 0 ? (
                <section className="rounded-2xl" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <Empty text={hayFiltros ? 'No hay viajes con esos filtros.' : 'No hay viajes para hoy. Arma uno con «Nuevo viaje».'} />
                </section>
            ) : (
                <ul className="grid grid-cols-1 xl:grid-cols-2 gap-3">
                    {viajes.data.map((v, i) => <TarjetaViaje key={v.id} v={v} indice={i} />)}
                </ul>
            )}

            <div className="mt-4">
                <Paginacion paginado={viajes} ruta="viajes.index" filters={{ ...filtros, buscar: q || undefined }} only={['viajes']} />
            </div>
        </AppLayout>
    );
}

function BotonKpi({ onClick, children }: { onClick: () => void; children: React.ReactNode }) {
    return (
        <button type="button" onClick={onClick}
            className="text-left rounded-2xl transition-transform duration-150 ease-out hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
            {children}
        </button>
    );
}

function TarjetaViaje({ v, indice }: { v: ViajeFila; indice: number }) {
    const acento = v.estado === 'en_ruta' ? 'var(--vp-navy)' : v.estado === 'programado' ? 'var(--vp-amber)' : v.estado === 'cerrado' ? 'var(--vp-mint)' : 'var(--color-border)';
    const pedidos = v.paradas;

    return (
        <li className="vp-fade-up" style={{ animationDelay: `${Math.min(indice, 8) * 30}ms` }}>
            <Link href={route('viajes.show', v.id)}
                className="group flex rounded-2xl overflow-hidden h-full transition-[transform,box-shadow] duration-150 ease-out hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: `inset 4px 0 0 ${acento}`, opacity: v.estado === 'anulado' ? 0.6 : 1 }}>
                <div className="w-24 sm:w-28 flex-shrink-0 flex flex-col items-center justify-center text-center px-2 py-3 gap-1"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 4%, var(--color-surface))', borderRight: '1px solid var(--color-border)' }}>
                    <span className="font-mono text-[13px] font-bold tracking-wider px-1.5 py-0.5 rounded border-2"
                        style={{ borderColor: 'var(--vp-navy)', color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 18%, var(--color-surface))' }}>
                        {v.placa ?? '—'}
                    </span>
                    <span className="font-display text-lg font-extrabold tabular-nums leading-tight" style={{ color: 'var(--vp-navy)' }}>{v.hora_salida ?? '—'}</span>
                    <span className="text-[13px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>{fmtDia(v.fecha)}</span>
                </div>

                <div className="flex-1 min-w-0 p-3.5 flex flex-col gap-2.5">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{v.numero}</p>
                            <p className="text-[13px] flex flex-wrap gap-x-3" style={{ color: 'var(--color-text-muted)' }}>
                                <span className="inline-flex items-center gap-1"><User size={13} />{v.chofer ?? 'Sin chofer'}</span>
                                {(v.ruta || v.destino) && <span className="inline-flex items-center gap-1 min-w-0"><MapPin size={13} />{v.ruta ?? v.destino}</span>}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-1.5">
                            {v.tipo !== 'reparto' && <TipoViajeBadge tipo={v.tipo} />}
                            <EstadoViajeBadge estado={v.estado} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4 items-end mt-auto">
                        <div>
                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Entregas</p>
                            <p className="text-sm font-semibold tabular-nums" style={{ color: 'var(--color-text)' }}>
                                {pedidos === 0 ? 'Sin paradas' : `${v.entregadas} de ${pedidos}`}
                                {v.por_entregar > 0 && v.estado !== 'programado' && (
                                    <span className="font-normal" style={{ color: 'var(--vp-amber-ink)' }}> · faltan {v.por_entregar}</span>
                                )}
                            </p>
                        </div>
                        <BarraCarga carga={v.carga} compacta />
                    </div>
                </div>
            </Link>
        </li>
    );
}

