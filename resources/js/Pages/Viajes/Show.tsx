import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Ban, Banknote, CheckCircle2, Factory, FileText, Flag, MapPin, Pencil, Phone, Printer,
    Truck, Undo2, User, Users, XCircle,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import {
    BarraCarga, EstadoViajeBadge, LicenciaBadge, Pastilla, TipoViajeBadge, fmtCantidad, fmtDia, fmtKg,
    type Carga, type EstadoLicencia, type EstadoViaje, type TipoViaje,
} from '@/Components/Flota/FlotaUI';
import { hoyLocal } from '@/lib/fechas';
import { soles } from '@/lib/dinero';
import { avisoError } from '@/lib/avisoError';
import { useTiempoReal } from '@/lib/useTiempoReal';
import type { PageProps } from '@/types';

type EstadoParada = 'por_entregar' | 'entregado' | 'no_entregado' | 'anulada';

interface ItemParada {
    id: number; producto_nombre: string; unidad_nombre: string; cantidad: number;
    entregado: number | null; peso_kg: number | null;
}

interface Parada {
    id: number;
    modo: 'pendiente' | 'directa';
    estado: EstadoParada;
    motivo: string | null;
    entregado_at: string | null;
    entrega: { numero: string } | null;
    venta: { id: number; numero: string; saldo: number; observacion: string | null } | null;
    cliente: string;
    direccion: string | null;
    telefono: string | null;
    peso_kg: number;
    guia: { id: number; numero: string | null; estado: string } | null;
    items: ItemParada[];
}

interface Viaje {
    id: number; numero: string; tipo: TipoViaje; estado: EstadoViaje; fecha: string; hora_salida: string | null;
    salida_at: string | null; cierre_at: string | null; origen: string | null; destino: string | null;
    observacion: string | null; motivo_anulacion: string | null; almacen: string | null; ruta: string | null;
    proveedor: string | null; entrada: { id: number; numero: string | null } | null; registrado_por: string | null;
    vehiculo: { placa: string; descripcion: string; estado: string } | null;
    chofer: { nombre: string; documento: string | null; licencia: string; telefono: string | null; estado_licencia: EstadoLicencia } | null;
    ayudantes: string[];
    paradas: Parada[];
}

interface Props extends PageProps {
    viaje: Viaje;
    carga: Carga;
    puede: { editar: boolean; guia: boolean };
}

const hora = (iso: string) => new Date(iso).toLocaleTimeString('es-PE', { hour: 'numeric', minute: '2-digit' });

export default function ViajeShow({ viaje, carga, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [entregando, setEntregando] = useState<Parada | null>(null);
    const [noEntregada, setNoEntregada] = useState<Parada | null>(null);
    const [anulando, setAnulando] = useState(false);
    const [cerrando, setCerrando] = useState(false);
    const [ocupado, setOcupado] = useState(false);

    useTiempoReal(['viajes'], () => router.reload({ only: ['viaje', 'carga'] }));

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const abierto = viaje.estado === 'programado' || viaje.estado === 'en_ruta';
    const editable = puede.editar && abierto;
    const porEntregar = viaje.paradas.filter(p => p.modo === 'pendiente' && p.estado === 'por_entregar');
    const entregadas = viaje.paradas.filter(p => p.estado === 'entregado').length;

    const accion = (ruta: string, datos: Record<string, unknown> = {}, alTerminar?: () => void) => {
        setOcupado(true);
        router.post(ruta, datos as Record<string, string>, {
            preserveScroll: true,
            onSuccess: () => alTerminar?.(),
            onError: e => avisoError(Object.values(e)[0]),
            onFinish: () => setOcupado(false),
        });
    };

    return (
        <AppLayout title={`Viaje ${viaje.numero}`}>
            {/* ── Cabecera ── */}
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-5">
                <div className="min-w-0">
                    <Link href={route('viajes.index')} className="text-sm font-semibold hover:underline print:hidden" style={{ color: 'var(--color-primary)' }}>← Viajes</Link>
                    <div className="flex flex-wrap items-center gap-2 mt-1">
                        <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>{viaje.numero}</h1>
                        <EstadoViajeBadge estado={viaje.estado} />
                        {viaje.tipo !== 'reparto' && <TipoViajeBadge tipo={viaje.tipo} />}
                    </div>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        {fmtDia(viaje.fecha)}{viaje.hora_salida ? `, sale ${viaje.hora_salida}` : ''}
                        {viaje.salida_at ? ` · salió ${hora(viaje.salida_at)}` : ''}
                        {viaje.cierre_at && viaje.estado === 'cerrado' ? ` · volvió ${hora(viaje.cierre_at)}` : ''}
                        {viaje.ruta ? ` · ${viaje.ruta}` : ''}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2 print:hidden">
                    <Button variant="ghost" onClick={() => window.print()} startContent={<Printer size={15} />}>Hoja de ruta</Button>
                    {editable && (
                        <Link href={route('viajes.edit', viaje.id)}><Button variant="ghost" startContent={<Pencil size={15} />}>Editar</Button></Link>
                    )}
                    {editable && (
                        <Button variant="ghost" onClick={() => setAnulando(true)} startContent={<Ban size={15} />}>Anular</Button>
                    )}
                    {puede.editar && viaje.estado === 'programado' && (
                        <Button onClick={() => accion(route('viajes.iniciar', viaje.id))} loading={ocupado} startContent={<Truck size={15} />}>Marcar salida</Button>
                    )}
                    {puede.editar && viaje.estado === 'en_ruta' && (
                        <Button variant="success" onClick={() => porEntregar.length ? setCerrando(true) : accion(route('viajes.cerrar', viaje.id))}
                            loading={ocupado} startContent={<Flag size={15} />}>Cerrar viaje</Button>
                    )}
                </div>
            </div>

            {viaje.estado === 'anulado' && (
                <Callout variant="neutral" className="mb-4" title="Viaje anulado">{viaje.motivo_anulacion}</Callout>
            )}

            {/* ── Resumen ── */}
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
                <Bloque titulo="Vehículo" icono={<Truck size={15} />}>
                    {viaje.vehiculo ? (
                        <>
                            <span className="font-mono text-sm font-bold tracking-wider px-2 py-0.5 rounded border-2 inline-block"
                                style={{ borderColor: 'var(--vp-navy)', color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 18%, var(--color-surface))' }}>
                                {viaje.vehiculo.placa}
                            </span>
                            <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>{viaje.vehiculo.descripcion || '—'}</p>
                        </>
                    ) : '—'}
                </Bloque>
                <Bloque titulo="Chofer" icono={<User size={15} />}>
                    {viaje.chofer ? (
                        <>
                            <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{viaje.chofer.nombre}</p>
                            <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                {[viaje.chofer.documento && `DNI ${viaje.chofer.documento}`, viaje.chofer.licencia && `Lic. ${viaje.chofer.licencia}`].filter(Boolean).join(' · ')}
                            </p>
                            {(viaje.chofer.estado_licencia === 'vencida' || viaje.chofer.estado_licencia === 'por_vencer') && (
                                <div className="mt-1"><LicenciaBadge estado={viaje.chofer.estado_licencia} /></div>
                            )}
                        </>
                    ) : <p className="text-sm" style={{ color: 'var(--vp-amber-ink)' }}>Sin chofer: asígnalo para que el viaje salga.</p>}
                </Bloque>
                <Bloque titulo="Ayudantes" icono={<Users size={15} />}>
                    <p className="text-sm" style={{ color: viaje.ayudantes.length ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                        {viaje.ayudantes.length ? viaje.ayudantes.join(', ') : 'Nadie más'}
                    </p>
                </Bloque>
                <Bloque titulo="Carga" icono={<Truck size={15} />}>
                    <BarraCarga carga={carga} />
                </Bloque>
            </div>

            {viaje.tipo === 'abastecimiento' && (
                <Callout variant="info" className="mb-5" title={<span className="inline-flex items-center gap-1.5"><Factory size={15} /> Trae carga {viaje.proveedor ? `de ${viaje.proveedor}` : 'al almacén'}</span>}>
                    {[viaje.origen && `Desde ${viaje.origen}`, viaje.almacen && `al ${viaje.almacen}`].filter(Boolean).join(' ')}
                    {viaje.entrada
                        ? <> · Entrada <Link href={route('inventario.entradas.edit', viaje.entrada.id)} className="font-semibold underline">{viaje.entrada.numero ?? `#${viaje.entrada.id}`}</Link>: el stock lo suma la entrada.</>
                        : ' · Cuando llegue, registra la compra en Entradas para que sume al stock.'}
                </Callout>
            )}
            {viaje.observacion && <p className="text-sm mb-4" style={{ color: 'var(--color-text-muted)' }}>{viaje.observacion}</p>}

            {/* ── Paradas ── */}
            {viaje.tipo !== 'abastecimiento' && (
                <section>
                    <div className="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                        <h2 className="text-lg font-bold" style={{ color: 'var(--color-text)' }}>Entregas</h2>
                        <p className="text-sm tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                            {entregadas} de {viaje.paradas.length} entregadas
                            {viaje.destino ? ` · destino ${viaje.destino}` : ''}
                        </p>
                    </div>
                    {viaje.paradas.length === 0 ? (
                        <div className="rounded-2xl border border-dashed py-10 text-center text-sm" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                            Este viaje no lleva entregas.{editable && <> <Link href={route('viajes.edit', viaje.id)} className="font-semibold" style={{ color: 'var(--color-primary)' }}>Agregar pedidos</Link></>}
                        </div>
                    ) : (
                        <ol className="space-y-3">
                            {viaje.paradas.map((p, i) => (
                                <TarjetaParada key={p.id} p={p} n={i + 1} abierto={abierto && puede.editar} puedeGuia={puede.guia}
                                    onEntregar={() => setEntregando(p)} onNoEntregada={() => setNoEntregada(p)} />
                            ))}
                        </ol>
                    )}
                </section>
            )}

            <p className="mt-6 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                {viaje.registrado_por ? `Registrado por ${viaje.registrado_por}.` : ''} Nada de este viaje mueve el stock por sí solo: sale al marcar cada entrega, igual que en Despachos.
            </p>

            <ModalEntregar parada={entregando} onClose={() => setEntregando(null)} />
            <ModalMotivo
                abierto={!!noEntregada} titulo={`No se entregó a ${noEntregada?.cliente ?? ''}`} onClose={() => setNoEntregada(null)}
                ayuda="Lo cargado vuelve a quedar por entregar en Despachos para otro viaje." placeholder="Cliente ausente, obra cerrada, sin acceso…"
                boton="Anotar" variante="warning"
                onConfirmar={(motivo, fin) => noEntregada && router.post(route('viajes.paradas.no-entregada', noEntregada.id), { motivo }, {
                    preserveScroll: true, onSuccess: () => setNoEntregada(null), onError: e => avisoError(Object.values(e)[0]), onFinish: fin,
                })} />
            <ModalMotivo
                abierto={anulando} titulo={`Anular el viaje ${viaje.numero}`} onClose={() => setAnulando(false)}
                ayuda="Solo se puede si no entregó nada. Lo que llevaba vuelve a quedar por entregar." placeholder="Motivo de la anulación"
                boton="Anular viaje" variante="danger"
                onConfirmar={(motivo, fin) => router.post(route('viajes.anular', viaje.id), { motivo }, {
                    preserveScroll: true, onSuccess: () => setAnulando(false), onError: e => avisoError(Object.values(e)[0]), onFinish: fin,
                })} />
            <Modal isOpen={cerrando} onClose={() => setCerrando(false)} size="sm" title={`Cerrar el viaje ${viaje.numero}`}
                footer={<>
                    <Button variant="ghost" onClick={() => setCerrando(false)}>Seguir en ruta</Button>
                    <Button variant="success" loading={ocupado} onClick={() => accion(route('viajes.cerrar', viaje.id), {}, () => setCerrando(false))}>Cerrar viaje</Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    {porEntregar.length === 1 ? 'Una entrega sigue' : `${porEntregar.length} entregas siguen`} sin marcar:{' '}
                    <strong>{porEntregar.map(p => p.cliente).join(', ')}</strong>. Al cerrar quedan como «volvió sin entregar» y regresan a Despachos.
                </p>
            </Modal>
        </AppLayout>
    );
}

function Bloque({ titulo, icono, children }: { titulo: string; icono: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl p-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <p className="text-[13px] font-semibold flex items-center gap-1.5 mb-2" style={{ color: 'var(--color-text-muted)' }}>{icono}{titulo}</p>
            {children}
        </div>
    );
}

function TarjetaParada({ p, n, abierto, puedeGuia, onEntregar, onNoEntregada }: {
    p: Parada; n: number; abierto: boolean; puedeGuia: boolean; onEntregar: () => void; onNoEntregada: () => void;
}) {
    const color = p.estado === 'entregado' ? 'var(--vp-mint)' : p.estado === 'por_entregar' ? 'var(--vp-amber)' : 'var(--color-border)';
    const mapa = p.direccion ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(p.direccion)}` : null;

    return (
        <li className="vp-fade-up rounded-2xl overflow-hidden flex break-inside-avoid" style={{ animationDelay: `${Math.min(n, 8) * 30}ms`, backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: `inset 4px 0 0 ${color}` }}>
            <div className="w-12 flex-shrink-0 flex items-start justify-center pt-4">
                <span className="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold tabular-nums"
                    style={{ backgroundColor: p.estado === 'entregado' ? 'var(--vp-mint)' : 'var(--vp-navy)', color: '#fff' }}>
                    {p.estado === 'entregado' ? <CheckCircle2 size={16} /> : n}
                </span>
            </div>
            <div className="flex-1 min-w-0 py-3.5 pr-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{p.cliente}</p>
                        <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                            {p.venta && <Link href={route('ventas.show', p.venta.id)} className="font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>{p.venta.numero}</Link>}
                            {p.peso_kg > 0 && <span className="tabular-nums"> · {fmtKg(p.peso_kg)}</span>}
                            {p.modo === 'directa' && ' · entregada al vender'}
                        </p>
                    </div>
                    <EstadoParadaBadge p={p} />
                </div>

                {(p.direccion || p.telefono) && (
                    <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" style={{ color: 'var(--color-text)' }}>
                        {p.direccion && (
                            <a href={mapa!} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 min-w-0 hover:underline">
                                <MapPin size={14} className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }} /> {p.direccion}
                            </a>
                        )}
                        {p.telefono && (
                            <a href={`tel:${p.telefono}`} className="inline-flex items-center gap-1.5 hover:underline">
                                <Phone size={14} style={{ color: 'var(--color-text-muted)' }} /> {p.telefono}
                            </a>
                        )}
                    </p>
                )}
                {p.venta?.observacion && <p className="mt-1 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{p.venta.observacion}</p>}

                <ul className="mt-2.5 space-y-0.5">
                    {p.items.map(it => (
                        <li key={it.id} className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</span>
                            <span className="flex-shrink-0 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                {it.entregado !== null && it.entregado < it.cantidad - 0.00009
                                    ? <><strong style={{ color: 'var(--color-text)' }}>{fmtCantidad(it.entregado)}</strong> de {fmtCantidad(it.cantidad)}</>
                                    : <strong style={{ color: 'var(--color-text)' }}>{fmtCantidad(it.cantidad)}</strong>} {it.unidad_nombre}
                            </span>
                        </li>
                    ))}
                </ul>
                {p.motivo && <p className="mt-1.5 text-[13px]" style={{ color: 'var(--vp-amber-ink)' }}>{p.motivo}</p>}

                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                    {p.venta && p.venta.saldo > 0.009 ? (
                        <span className="inline-flex items-center gap-1.5 text-sm font-bold" style={{ color: 'var(--vp-coral-ink)' }}>
                            <Banknote size={15} /> Cobrar {soles(p.venta.saldo)}
                        </span>
                    ) : <span className="text-[13px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>{p.venta ? 'Pagado' : ''}</span>}

                    <div className="flex flex-wrap gap-1.5 print:hidden">
                        {p.guia ? (
                            <Link href={route('guias.show', p.guia.id)}>
                                <Button size="sm" variant="ghost" startContent={<FileText size={14} />}>Guía {p.guia.numero ?? ''}</Button>
                            </Link>
                        ) : puedeGuia && p.venta && p.estado !== 'no_entregado' && p.estado !== 'anulada' && (
                            <Link href={route('guias.create', { parada: p.id })} title="Guía de remisión con la placa, el chofer y el peso ya puestos">
                                <Button size="sm" variant="ghost" startContent={<FileText size={14} />}>Guía</Button>
                            </Link>
                        )}
                        {abierto && p.modo === 'pendiente' && p.estado === 'por_entregar' && (
                            <>
                                <Button size="sm" variant="ghost" onClick={onNoEntregada} startContent={<Undo2 size={14} />}>No se entregó</Button>
                                <Button size="sm" onClick={onEntregar} startContent={<CheckCircle2 size={14} />}>Entregado</Button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </li>
    );
}

function EstadoParadaBadge({ p }: { p: Parada }) {
    if (p.estado === 'anulada') return <Pastilla color="var(--color-text-muted)" icono={<XCircle size={13} />}>Venta anulada</Pastilla>;
    if (p.estado === 'entregado') return (
        <Pastilla color="var(--vp-mint-ink)" icono={<CheckCircle2 size={13} />}>
            Entregado{p.entregado_at && p.modo === 'pendiente' ? ` ${hora(p.entregado_at)}` : ''}{p.entrega ? ` · ${p.entrega.numero}` : ''}
        </Pastilla>
    );
    if (p.estado === 'no_entregado') return <Pastilla color="var(--vp-coral-ink)" icono={<Undo2 size={13} />}>No entregado</Pastilla>;
    return <Pastilla color="var(--vp-amber-ink)">Por entregar</Pastilla>;
}

function ModalEntregar({ parada, onClose }: { parada: Parada | null; onClose: () => void }) {
    const [cantidades, setCantidades] = useState<Record<number, string>>({});
    const [fecha, setFecha] = useState(hoyLocal());
    const [observacion, setObservacion] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (!parada) return;
        setCantidades(Object.fromEntries(parada.items.map(i => [i.id, String(i.cantidad)])));
        setFecha(hoyLocal()); setObservacion(''); setErrores({});
    }, [parada]);

    if (!parada) return <Modal isOpen={false} onClose={onClose} title="">{null}</Modal>;

    const parcial = parada.items.some(i => Number(cantidades[i.id] ?? 0) < i.cantidad - 0.00009);

    function confirmar() {
        setGuardando(true);
        router.post(route('viajes.paradas.entregar', parada!.id), {
            fecha, observacion, items: parada!.items.map(i => ({ id: i.id, cantidad: Number(cantidades[i.id] ?? 0) })),
        } as unknown as Record<string, string>, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: e => { setErrores(e as Record<string, string>); avisoError(Object.values(e)[0]); },
            onFinish: () => setGuardando(false),
        });
    }

    return (
        <Modal isOpen onClose={onClose} size="md" title={`Entregado a ${parada.cliente}`}
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={confirmar} loading={guardando} startContent={<CheckCircle2 size={15} />}>Confirmar entrega</Button>
            </>}>
            <div className="space-y-4">
                <div>
                    <p className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text)' }}>¿Cuánto recibió?</p>
                    <div className="space-y-2">
                        {parada.items.map((it, j) => (
                            <div key={it.id} className="flex items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</p>
                                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Se cargaron {fmtCantidad(it.cantidad)} {it.unidad_nombre}</p>
                                </div>
                                <div className="flex-shrink-0 text-right">
                                    <input type="number" min={0} max={it.cantidad} step="any" inputMode="decimal"
                                        aria-label={`Cantidad entregada de ${it.producto_nombre}`}
                                        value={cantidades[it.id] ?? '0'}
                                        onChange={e => setCantidades(prev => ({ ...prev, [it.id]: e.target.value }))}
                                        className="w-24 h-10 rounded-lg border px-2 text-right text-sm tabular-nums outline-none focus:ring-2"
                                        style={{ borderColor: errores[`items.${j}.cantidad`] ? 'var(--color-danger)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                    {errores[`items.${j}.cantidad`] && <p className="text-[12px] mt-0.5 max-w-[12rem]" style={{ color: 'var(--color-danger)' }}>{errores[`items.${j}.cantidad`]}</p>}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Input label="Fecha" type="date" value={fecha} onChange={e => setFecha(e.target.value)} error={errores.fecha} />
                    <Input label="Observación" value={observacion} onChange={e => setObservacion(e.target.value)} placeholder="Opcional" />
                </div>
                <p className="rounded-xl px-3 py-2.5 text-[13px]" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                    {parcial
                        ? 'Sale del stock lo que recibió; lo demás vuelve a quedar por entregar en Despachos.'
                        : 'Sale del stock y el pedido queda entregado.'}
                    {parada.venta && parada.venta.saldo > 0.009 && (
                        <span className="block mt-1 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>El cliente debe {soles(parada.venta.saldo)}: el cobro se registra en la venta.</span>
                    )}
                </p>
            </div>
        </Modal>
    );
}

function ModalMotivo({ abierto, titulo, ayuda, placeholder, boton, variante, onClose, onConfirmar }: {
    abierto: boolean; titulo: string; ayuda: string; placeholder: string; boton: string;
    variante: 'danger' | 'warning'; onClose: () => void; onConfirmar: (motivo: string, fin: () => void) => void;
}) {
    const [motivo, setMotivo] = useState('');
    const [enviando, setEnviando] = useState(false);
    useEffect(() => { if (abierto) setMotivo(''); }, [abierto]);

    return (
        <Modal isOpen={abierto} onClose={onClose} size="sm" title={titulo}
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={enviando}>Cancelar</Button>
                <Button variant={variante} loading={enviando} disabled={!motivo.trim()}
                    onClick={() => { setEnviando(true); onConfirmar(motivo.trim(), () => setEnviando(false)); }}>{boton}</Button>
            </>}>
            <div className="space-y-3">
                <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>{ayuda}</p>
                <Input label="Motivo" required value={motivo} onChange={e => setMotivo(e.target.value)} placeholder={placeholder} autoFocus />
            </div>
        </Modal>
    );
}
