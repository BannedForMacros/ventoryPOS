import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Banknote, Clock, MapPin, PackageCheck, Phone, Search, Store, Truck, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Checkbox from '@/Components/UI/Checkbox';
import { Empty, fmtInt, fmtS, plural } from '@/Components/Reportes/ReportUI';
import { agenteActivo, imprimirTicket, type TicketPayload } from '@/lib/ticketPrinter';
import { hoyLocal } from '@/lib/fechas';
import type { PageProps } from '@/types';
import { useTiempoReal } from '@/lib/useTiempoReal';
import { avisoError } from '@/lib/avisoError';

interface ClienteLite {
    id: number;
    nombres?: string | null;
    apellidos?: string | null;
    razon_social?: string | null;
    telefono?: string | null;
    direccion?: string | null;
    es_cliente_general?: boolean;
}

interface DespachoItem {
    id: number;
    producto_id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: string | number;
    cantidad_pendiente: string | number;
    factor_conversion: string | number;
    precio_unitario: string | number;
}

interface VentaLite {
    id: number;
    numero: string;
    fecha_venta?: string | null;
    tipo_entrega?: 'recojo' | 'envio' | null;
    entrega_programada?: string | null;
    cliente_telefono?: string | null;
    cliente_direccion?: string | null;
    observacion?: string | null;
    saldo_pendiente?: string | number | null;
    local?: { id: number; nombre: string } | null;
    ruta_entrega?: { id: number; nombre: string; zona: string | null } | null;
}

interface Pendiente extends Record<string, unknown> {
    id: number;
    fecha: string;
    observacion: string | null;
    fecha_entrega_estimada?: string | null;
    cliente?: ClienteLite | null;
    user?: { id: number; name: string } | null;
    venta?: VentaLite | null;
    items: DespachoItem[];
}

interface Paginado<T> {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

type Cuando = 'todos' | 'hoy' | 'manana' | 'atrasados' | 'sin_fecha' | 'fecha';

interface Props extends PageProps {
    pendientes: Paginado<Pendiente>;
    buscar?: string;
    filtros: { cuando: Cuando; fecha: string | null; ruta_id: number | null };
    usaEntregas: boolean;
    conteos: Record<Exclude<Cuando, 'fecha'>, number> | null;
    rutas: { id: number; nombre: string; zona: string | null }[];
}

function nombreCliente(c?: ClienteLite | null): string {
    if (!c || c.es_cliente_general) return 'Cliente general';
    return (c.razon_social ?? `${c.nombres ?? ''} ${c.apellidos ?? ''}`.trim()) || '—';
}

function fmtCantidad(v: string | number | undefined): string {
    const n = Number(v ?? 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
}

const hora = (iso: string) => new Date(iso).toLocaleTimeString('es-PE', { hour: 'numeric', minute: '2-digit' });
const dia = (iso: string) => new Date(iso).toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' });
const esHoy = (iso: string) => new Date(iso).toDateString() === new Date().toDateString();
const atrasado = (iso: string) => new Date(iso).getTime() < new Date().setHours(0, 0, 0, 0);

export default function Despachos({ pendientes, buscar = '', filtros, usaEntregas, conteos, rutas }: Props) {
    const { flash } = usePage<Props>().props;

    // Tiempo real: un pedido nuevo o una entrega hecha en otra PC aparece sola.
    useTiempoReal(['despachos'], () => router.reload({ only: ['pendientes', 'conteos'] }));
    const [q, setQ] = useState(buscar);
    const [despachando, setDespachando] = useState<Pendiente | null>(null);
    const [cantidades, setCantidades] = useState<Record<number, string>>({});
    const [fecha, setFecha] = useState(hoyLocal);
    const [observacion, setObservacion] = useState('');
    const [imprimir, setImprimir] = useState(true);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // Ticket de la entrega recién confirmada (una sola vez).
    const impresa = useRef<number | null>(null);
    useEffect(() => {
        const id = flash?.despacho_entrega;
        if (!id || impresa.current === id) return;
        impresa.current = id;
        void imprimirEntrega(id);
    }, [flash?.despacho_entrega]);

    async function imprimirEntrega(id: number) {
        if (!(await agenteActivo())) {
            toast.error('El despacho se guardó, pero el programa de impresión no está activo en esta PC.');
            return;
        }
        try {
            const { data } = await axios.get<TicketPayload>(route('despachos.entrega.ticket', id));
            if (await imprimirTicket(data)) toast.success('Ticket de despacho enviado a la impresora');
            else toast.error('No se pudo imprimir el ticket de despacho.');
        } catch {
            toast.error('No se pudo obtener el ticket de despacho.');
        }
    }

    const params = (extra: Record<string, unknown> = {}) => {
        const p: Record<string, unknown> = {
            buscar: q || undefined,
            cuando: filtros.cuando !== 'todos' && filtros.cuando !== 'fecha' ? filtros.cuando : undefined,
            fecha: filtros.fecha ?? undefined,
            ruta_id: filtros.ruta_id ?? undefined,
            ...extra,
        };
        Object.keys(p).forEach(k => p[k] === undefined && delete p[k]);
        return p as Record<string, string>;
    };
    const ir = (extra: Record<string, unknown>) =>
        router.get(route('despachos.index'), params(extra), { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (q === buscar) return;
        const t = setTimeout(() => ir({ buscar: q || undefined }), 400);
        return () => clearTimeout(t);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    function abrirDespacho(p: Pendiente) {
        setDespachando(p);
        setCantidades(Object.fromEntries(p.items.map(i => [i.id, fmtCantidad(i.cantidad_pendiente)])));
        setFecha(hoyLocal());
        setObservacion('');
        setErrors({});
    }

    function confirmarDespacho() {
        if (!despachando) return;
        setSaving(true);
        const items = despachando.items
            .map(item => ({ id: item.id, cantidad: Number(cantidades[item.id] ?? 0) }))
            .filter(i => i.cantidad > 0.00009);

        router.post(route('despachos.confirmar', despachando.id), { fecha, observacion, items, imprimir }, {
            preserveScroll: true,
            onSuccess: () => setDespachando(null),
            onError: errs => {
                setErrors(errs as Record<string, string>);
                const first = Object.values(errs)[0];
                avisoError(first);
            },
            onFinish: () => setSaving(false),
        });
    }

    const totalADespachar = useMemo(() => !despachando ? 0 : despachando.items.reduce(
        (sum, item) => sum + Math.max(0, Number(cantidades[item.id] ?? 0)) * Number(item.precio_unitario), 0), [despachando, cantidades]);
    const quedaAlgo = !!despachando && despachando.items.some(i => Number(cantidades[i.id] ?? 0) < Number(i.cantidad_pendiente) - 0.00009);

    const chips: [Exclude<Cuando, 'fecha'>, string][] = [
        ['todos', 'Todos'], ['hoy', 'Hoy'], ['manana', 'Mañana'], ['atrasados', 'Atrasados'], ['sin_fecha', 'Sin fecha'],
    ];
    const hayFiltros = !!(q || filtros.ruta_id || filtros.fecha || filtros.cuando !== 'todos');

    return (
        <AppLayout title="Despachos">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Despachos</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Mercadería vendida que falta entregar{usaEntregas ? ', ordenada por la hora programada' : ''}. Al confirmar una entrega, sale del stock.
                    </p>
                </div>
                <p className="font-display text-lg font-bold tabular-nums" style={{ color: 'var(--vp-navy)' }}>
                    {plural(pendientes.total, 'pendiente', 'pendientes')}
                </p>
            </div>

            {/* ── Filtros ── */}
            <section className="rounded-2xl p-3 mb-4 flex flex-wrap items-center gap-3"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                {usaEntregas && conteos && (
                    <div className="flex flex-wrap rounded-xl p-0.5" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 5%, transparent)' }}>
                        {chips.map(([v, t]) => {
                            const activo = filtros.cuando === v;
                            const alerta = v === 'atrasados' && conteos.atrasados > 0;
                            return (
                                <button key={v} onClick={() => ir({ cuando: v === 'todos' ? undefined : v, fecha: undefined, page: undefined })} aria-pressed={activo}
                                    className="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                    style={{
                                        backgroundColor: activo ? 'var(--color-surface)' : 'transparent',
                                        color: activo ? 'var(--vp-navy)' : alerta ? 'var(--vp-coral-ink)' : 'var(--color-text-muted)',
                                        boxShadow: activo ? '0 1px 3px rgb(0 0 0 / 0.1)' : undefined,
                                    }}>
                                    {t} <span className="tabular-nums opacity-70">{fmtInt(conteos[v])}</span>
                                </button>
                            );
                        })}
                    </div>
                )}
                {usaEntregas && (
                    <input type="date" value={filtros.fecha ?? ''} aria-label="Ver los despachos de un día"
                        onChange={e => ir({ fecha: e.target.value || undefined, cuando: undefined, page: undefined })}
                        className="rounded-xl border px-3 py-2 text-sm"
                        style={{ borderColor: filtros.fecha ? 'var(--vp-navy)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                )}
                {usaEntregas && rutas.length > 0 && (
                    <div className="w-52">
                        <Select size="sm" ariaLabel="Ruta" value={filtros.ruta_id ?? ''}
                            onChange={v => ir({ ruta_id: v || undefined, page: undefined })}
                            options={[{ value: '', label: 'Todas las rutas' }, ...rutas.map(r => ({ value: r.id, label: r.zona ? `${r.nombre}, ${r.zona}` : r.nombre }))]} />
                    </div>
                )}
                <div className="relative flex-1 min-w-[220px]">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Buscar nota, cliente, dirección o producto" aria-label="Buscar despacho"
                        className="w-full text-sm rounded-xl pl-9 pr-3 py-2 border outline-none focus:ring-2"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                </div>
                {hayFiltros && (
                    <button onClick={() => { setQ(''); router.get(route('despachos.index'), {}, { preserveScroll: true }); }}
                        className="inline-flex items-center gap-1 text-sm font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <X size={14} /> Quitar filtros
                    </button>
                )}
            </section>

            {/* ── Lista ── */}
            {pendientes.data.length === 0 ? (
                <section className="rounded-2xl" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <Empty text={hayFiltros ? 'No hay despachos con esos filtros.' : 'No hay despachos pendientes.'} />
                </section>
            ) : (
                <ul className="grid grid-cols-1 2xl:grid-cols-2 gap-3">
                    {pendientes.data.map(p => <Tarjeta key={p.id} p={p} usaEntregas={usaEntregas} onDespachar={() => abrirDespacho(p)} />)}
                </ul>
            )}

            {pendientes.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-1">
                    {pendientes.links.map((link, idx) => (
                        <button key={idx} disabled={!link.url || link.active}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className="min-w-9 h-9 px-3 text-sm font-semibold rounded-lg disabled:opacity-60"
                            style={{
                                backgroundColor: link.active ? 'var(--color-primary)' : 'color-mix(in srgb, var(--color-primary) 6%, transparent)',
                                color: link.active ? '#fff' : 'var(--color-text-muted)',
                            }}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            )}

            {/* ── Confirmar entrega ── */}
            <Modal isOpen={!!despachando} onClose={() => setDespachando(null)} size="md"
                title={despachando ? `Entregar ${despachando.venta?.numero ?? `#${despachando.id}`}` : 'Entregar'}
                footer={<>
                    <Button variant="ghost" onClick={() => setDespachando(null)} disabled={saving}>Cancelar</Button>
                    <Button onClick={confirmarDespacho} loading={saving} startContent={<PackageCheck size={15} />}>Confirmar entrega</Button>
                </>}>
                {despachando && (
                    <div className="space-y-4">
                        <div>
                            <p className="text-[15px] font-bold" style={{ color: 'var(--color-text)' }}>{nombreCliente(despachando.cliente)}</p>
                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                {[despachando.venta?.cliente_direccion ?? despachando.cliente?.direccion, despachando.venta?.local?.nombre].filter(Boolean).join(', ')}
                            </p>
                        </div>

                        <div>
                            <p className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text)' }}>¿Cuánto se entrega ahora?</p>
                            <div className="space-y-2">
                                {despachando.items.map(item => (
                                    <div key={item.id} className="flex items-center gap-3">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</p>
                                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                                Vendió {fmtCantidad(item.cantidad)}, falta entregar {fmtCantidad(item.cantidad_pendiente)} {item.unidad_nombre}
                                            </p>
                                        </div>
                                        <Input type="number" min={0} max={Number(item.cantidad_pendiente)} step="any"
                                            aria-label={`Cantidad a entregar de ${item.producto_nombre}`}
                                            value={cantidades[item.id] ?? '0'}
                                            onChange={e => setCantidades(prev => ({ ...prev, [item.id]: e.target.value }))}
                                            error={errors[`items.${item.id}.cantidad`] ?? errors.items}
                                            className="w-24 text-right" />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Fecha de entrega" type="date" value={fecha} onChange={e => setFecha(e.target.value)} error={errors.fecha} />
                            <Input label="Observación" value={observacion} onChange={e => setObservacion(e.target.value)} placeholder="Opcional" />
                        </div>

                        <label className="flex items-center gap-2 cursor-pointer">
                            <Checkbox checked={imprimir} onChange={e => setImprimir(e.target.checked)} />
                            <span className="text-sm" style={{ color: 'var(--color-text)' }}>Imprimir el ticket de despacho</span>
                        </label>

                        <div className="rounded-xl px-3 py-2.5 text-sm" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Valor de lo que se entrega</span>
                                <span className="font-bold tabular-nums" style={{ color: 'var(--vp-navy)' }}>{fmtS(totalADespachar)}</span>
                            </div>
                            <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                {quedaAlgo ? 'Sale del stock lo que entregas ahora; el resto sigue pendiente en esta lista.' : 'Sale del stock y el despacho queda completo.'}
                            </p>
                            {Number(despachando.venta?.saldo_pendiente ?? 0) > 0.009 && (
                                <p className="text-[13px] mt-1.5 font-semibold flex items-center gap-1.5" style={{ color: 'var(--vp-coral-ink)' }}>
                                    <Banknote size={14} /> El cliente debe {fmtS(Number(despachando.venta?.saldo_pendiente))}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

function Tarjeta({ p, usaEntregas, onDespachar }: { p: Pendiente; usaEntregas: boolean; onDespachar: () => void }) {
    const v = p.venta;
    const prog = v?.entrega_programada ?? null;
    const envio = v?.tipo_entrega === 'envio';
    const tarde = !!prog && atrasado(prog);
    const direccion = v?.cliente_direccion ?? p.cliente?.direccion;
    const telefono = v?.cliente_telefono ?? p.cliente?.telefono;
    const saldo = Number(v?.saldo_pendiente ?? 0);
    const acento = tarde ? 'var(--vp-coral)' : envio ? 'var(--vp-navy)' : 'var(--vp-mint)';

    return (
        <li className="rounded-2xl overflow-hidden flex"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: `inset 4px 0 0 ${acento}` }}>
            {/* Cuándo */}
            {usaEntregas && (
                <div className="w-24 sm:w-28 flex-shrink-0 flex flex-col items-center justify-center text-center px-2 py-3"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 4%, var(--color-surface))', borderRight: '1px solid var(--color-border)' }}>
                    {prog ? (
                        <>
                            <span className="font-display text-xl font-extrabold tabular-nums leading-tight" style={{ color: tarde ? 'var(--vp-coral-ink)' : 'var(--vp-navy)' }}>{hora(prog)}</span>
                            <span className="text-[13px] font-semibold" style={{ color: tarde ? 'var(--vp-coral-ink)' : 'var(--color-text-muted)' }}>
                                {esHoy(prog) ? 'hoy' : dia(prog)}
                            </span>
                            {tarde && <span className="text-[12px] font-semibold mt-0.5" style={{ color: 'var(--vp-coral-ink)' }}>atrasado</span>}
                        </>
                    ) : (
                        <>
                            <Clock size={18} style={{ color: 'var(--color-text-muted)' }} />
                            <span className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>Sin hora</span>
                        </>
                    )}
                </div>
            )}

            <div className="flex-1 min-w-0 p-3.5">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-base font-bold truncate" style={{ color: 'var(--color-text)' }}>{nombreCliente(p.cliente)}</p>
                        <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                            <Link href={v?.id ? route('ventas.show', v.id) : '#'} className="font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                                {v?.numero ?? `Despacho #${p.id}`}
                            </Link>
                            {[p.user?.name, v?.local?.nombre].filter(Boolean).map(x => `, ${x}`).join('')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        {usaEntregas && v?.tipo_entrega && (
                            <Chip color={envio ? 'var(--vp-navy)' : 'var(--vp-mint-ink)'} icono={envio ? <Truck size={13} /> : <Store size={13} />}>
                                {envio ? 'Envío' : 'Recojo'}
                            </Chip>
                        )}
                        {v?.ruta_entrega && (
                            <Chip color="var(--vp-amber-ink)">{v.ruta_entrega.nombre}{v.ruta_entrega.zona ? `, ${v.ruta_entrega.zona}` : ''}</Chip>
                        )}
                    </div>
                </div>

                {(direccion || telefono) && (envio || !usaEntregas) && (
                    <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" style={{ color: 'var(--color-text)' }}>
                        {direccion && <span className="inline-flex items-center gap-1.5 min-w-0"><MapPin size={14} className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }} /> {direccion}</span>}
                        {telefono && <span className="inline-flex items-center gap-1.5"><Phone size={14} style={{ color: 'var(--color-text-muted)' }} /> {telefono}</span>}
                    </p>
                )}
                {v?.observacion && <p className="mt-1 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{v.observacion}</p>}

                <ul className="mt-2.5 space-y-0.5">
                    {p.items.filter(i => Number(i.cantidad_pendiente) > 0.00009).map(item => (
                        <li key={item.id} className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</span>
                            <span className="flex-shrink-0 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                <strong style={{ color: 'var(--color-text)' }}>{fmtCantidad(item.cantidad_pendiente)}</strong>
                                {Number(item.cantidad_pendiente) < Number(item.cantidad) - 0.00009 ? ` de ${fmtCantidad(item.cantidad)}` : ''} {item.unidad_nombre}
                            </span>
                        </li>
                    ))}
                </ul>

                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                    {saldo > 0.009 ? (
                        <span className="inline-flex items-center gap-1.5 text-sm font-bold" style={{ color: 'var(--vp-coral-ink)' }}>
                            <Banknote size={15} /> Cobrar {fmtS(saldo)}
                        </span>
                    ) : (
                        <span className="text-[13px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>Pagado</span>
                    )}
                    <div className="flex gap-1.5">
                        {/* La guía va aparte del despacho: despachar saca la mercadería del
                            almacén; la guía es el documento que ampara su viaje. */}
                        {v?.id && (
                            <Link href={route('guias.create', { venta: v.id })} title="Emitir guía de remisión">
                                <Button size="sm" variant="secondary" startContent={<Truck size={14} />}>Guía</Button>
                            </Link>
                        )}
                        <Button size="sm" onClick={onDespachar} startContent={<PackageCheck size={14} />}>Entregar</Button>
                    </div>
                </div>
            </div>
        </li>
    );
}

function Chip({ color, icono, children }: { color: string; icono?: React.ReactNode; children: React.ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1 text-[13px] font-semibold px-2.5 py-1 rounded-full"
            style={{ color, backgroundColor: `color-mix(in srgb, ${color} 12%, transparent)` }}>
            {icono}{children}
        </span>
    );
}
