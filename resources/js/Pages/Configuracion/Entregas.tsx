import { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ArrowDown, ArrowUp, BellRing, Check, ListChecks, MapPinned, Pencil, Plus, Trash2, Truck, Type, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Switch from '@/Components/UI/Switch';
import Modal from '@/Components/UI/Modal';
import { plural } from '@/Components/Reportes/ReportUI';
import type { PageProps } from '@/types';

interface Config {
    activo: boolean;
    aviso_monto: number | null;
    ruta_obligatoria: boolean;
    fecha_obligatoria: boolean;
    envio_sale_al_entregar: boolean;
    textos: Record<string, string>;
}
interface DefTexto { clave: string; nombre: string; defecto: string; max: number; }
interface Ruta { id: number; nombre: string; zona: string | null; orden: number; activo: boolean; ventas_count: number; }

interface Props extends PageProps {
    config: Config;
    catalogoTextos: DefTexto[];
    rutas: Ruta[];
    usaDespachoAlmacen: boolean;
    puedeEditar: boolean;
}

export default function Entregas({ config, catalogoTextos, rutas, puedeEditar }: Props) {
    const { flash } = usePage<Props>().props;
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const [form, setForm] = useState({ ...config, aviso: config.aviso_monto !== null, monto: config.aviso_monto !== null ? String(config.aviso_monto) : '500' });
    const [guardando, setGuardando] = useState(false);
    const hayCambios = useMemo(() =>
        form.activo !== config.activo
        || form.ruta_obligatoria !== config.ruta_obligatoria
        || form.fecha_obligatoria !== config.fecha_obligatoria
        || form.envio_sale_al_entregar !== config.envio_sale_al_entregar
        || (form.aviso ? Number(form.monto) || 0 : null) !== config.aviso_monto
        || catalogoTextos.some(t => (form.textos[t.clave] ?? '').trim() !== (config.textos[t.clave] ?? '')),
    [form, config, catalogoTextos]);

    function guardar() {
        setGuardando(true);
        router.put(route('configuracion.entregas.update'), {
            activo: form.activo,
            aviso_monto: form.aviso ? Number(form.monto) || 0 : null,
            ruta_obligatoria: form.ruta_obligatoria,
            fecha_obligatoria: form.fecha_obligatoria,
            envio_sale_al_entregar: form.envio_sale_al_entregar,
            textos: form.textos,
        }, {
            preserveScroll: true,
            onError: e => toast.error((Object.values(e)[0] as string) ?? 'No se pudo guardar.'),
            onFinish: () => setGuardando(false),
        });
    }

    return (
        <AppLayout title="Entregas">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Entregas</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Para negocios que reparten: en cada venta se marca si el cliente recoge en tienda o se le envía, con su ruta y la hora programada.
                    </p>
                </div>
                {puedeEditar && (
                    <div className="flex items-center gap-3">
                        {hayCambios && <span className="text-sm font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>Hay cambios sin guardar</span>}
                        <Button onClick={guardar} loading={guardando} disabled={!hayCambios} startContent={<Check size={16} />}>Guardar</Button>
                    </div>
                )}
            </div>

            {/* ── Interruptor principal ── */}
            <section className="rounded-2xl p-5 mb-4 flex flex-wrap items-center justify-between gap-4 text-white"
                style={{ backgroundColor: form.activo ? 'var(--vp-navy)' : 'color-mix(in srgb, var(--vp-navy) 55%, #64748b)' }}>
                <div className="flex items-center gap-3 min-w-0">
                    <span className="flex h-11 w-11 items-center justify-center rounded-xl flex-shrink-0" style={{ backgroundColor: 'rgb(255 255 255 / 0.14)' }}><Truck size={22} /></span>
                    <div className="min-w-0">
                        <p className="font-display text-lg font-bold leading-tight">{form.activo ? 'Entregas activadas' : 'Entregas desactivadas'}</p>
                        <p className="text-sm opacity-85">
                            {form.activo
                                ? 'La venta pregunta si el cliente recoge o se le envía.'
                                : 'La venta, el stock y el ticket funcionan como siempre.'}
                        </p>
                    </div>
                </div>
                <Switch checked={form.activo} disabled={!puedeEditar} aria-label="Activar entregas"
                    onChange={v => setForm(f => ({ ...f, activo: v }))} />
            </section>

            <div className="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start" style={{ opacity: form.activo ? 1 : 0.6 }}>
                <div className="xl:col-span-6 space-y-4 min-w-0">
                    <Tarjeta icono={<ListChecks size={18} />} titulo="Al registrar un envío" color="var(--vp-sky)" tinta="var(--vp-navy)">
                        <div className="space-y-3.5">
                            <Switch checked={form.ruta_obligatoria} disabled={!puedeEditar} onChange={v => setForm(f => ({ ...f, ruta_obligatoria: v }))}
                                label="La ruta es obligatoria" description="Solo se exige si ya registraste rutas." />
                            <Switch checked={form.fecha_obligatoria} disabled={!puedeEditar} onChange={v => setForm(f => ({ ...f, fecha_obligatoria: v }))}
                                label="La fecha y la hora programadas son obligatorias" description="Con ellas se ordena la lista de despachos del día." />
                            <Switch checked={form.envio_sale_al_entregar} disabled={!puedeEditar} onChange={v => setForm(f => ({ ...f, envio_sale_al_entregar: v }))}
                                label="La mercadería de un envío sale del stock al entregarse"
                                description="Queda pendiente en Despachos hasta que se confirma la entrega. Si lo apagas, sale del stock al vender, como en un recojo." />
                        </div>
                        <p className="text-[13px] mt-4" style={{ color: 'var(--color-text-muted)' }}>
                            La dirección de entrega siempre se exige. El teléfono, la dirección y la observación se piden en la venta si lo activas en{' '}
                            <Link href={route('configuracion.ticket.index')} className="font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>Ticket</Link>.
                        </p>
                    </Tarjeta>

                    <Tarjeta icono={<BellRing size={18} />} titulo="Aviso para no olvidar el envío" color="var(--vp-amber)" tinta="var(--vp-amber-ink)">
                        <Switch checked={form.aviso} disabled={!puedeEditar} onChange={v => setForm(f => ({ ...f, aviso: v }))}
                            label="Preguntar antes de guardar una venta grande marcada como recojo"
                            description="Las ventas nuevas empiezan como recojo. Si el monto es alto, el sistema pregunta si en realidad es un envío." />
                        {form.aviso && (
                            <label className="mt-3.5 flex flex-wrap items-center gap-2.5 text-sm" style={{ color: 'var(--color-text)' }}>
                                <span className="font-semibold">Preguntar desde</span>
                                <span className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                    <input type="number" min={1} step="any" inputMode="decimal" disabled={!puedeEditar} value={form.monto}
                                        onChange={e => setForm(f => ({ ...f, monto: e.target.value }))} aria-label="Monto del aviso"
                                        className="w-36 rounded-xl border pl-9 pr-3 py-2 text-sm text-right tabular-nums outline-none focus:ring-2"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                </span>
                            </label>
                        )}
                    </Tarjeta>

                    <Tarjeta icono={<Type size={18} />} titulo="Cómo lo llama tu negocio" color="var(--vp-mint)" tinta="var(--vp-mint-ink)">
                        <p className="text-[13px] -mt-1 mb-3" style={{ color: 'var(--color-text-muted)' }}>
                            Salen en los botones de la venta, en el ticket y en el ticket de despacho. Vacío vuelve al texto de ejemplo.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
                            {catalogoTextos.map(t => (
                                <label key={t.clave} className="block">
                                    <span className="block text-sm font-semibold mb-1" style={{ color: 'var(--color-text)' }}>{t.nombre}</span>
                                    <input type="text" maxLength={t.max} disabled={!puedeEditar}
                                        value={form.textos[t.clave] ?? ''} placeholder={t.defecto}
                                        onChange={e => setForm(f => ({ ...f, textos: { ...f.textos, [t.clave]: e.target.value } }))}
                                        className="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                </label>
                            ))}
                        </div>
                    </Tarjeta>
                </div>

                <div className="xl:col-span-6 min-w-0">
                    <Rutas rutas={rutas} puedeEditar={puedeEditar} />
                </div>
            </div>
        </AppLayout>
    );
}

/* ── Rutas ─────────────────────────────────────────────────────────────── */

function Rutas({ rutas, puedeEditar }: { rutas: Ruta[]; puedeEditar: boolean }) {
    const [editando, setEditando] = useState<number | 'nueva' | null>(null);
    const [form, setForm] = useState({ nombre: '', zona: '' });
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [borrando, setBorrando] = useState<Ruta | null>(null);

    function abrir(r: Ruta | null) {
        setErrores({});
        setForm({ nombre: r?.nombre ?? '', zona: r?.zona ?? '' });
        setEditando(r ? r.id : 'nueva');
    }

    function guardar() {
        const opts = {
            preserveScroll: true,
            onSuccess: () => setEditando(null),
            onError: (e: Record<string, string>) => setErrores(e),
        };
        if (editando === 'nueva') router.post(route('configuracion.entregas.rutas.store'), form, opts);
        else if (editando) router.put(route('configuracion.entregas.rutas.update', editando), form, opts);
    }

    function mover(i: number, d: -1 | 1) {
        const a = rutas[i], b = rutas[i + d];
        if (!a || !b) return;
        // Se intercambian los órdenes; si eran iguales, se separan.
        const oa = b.orden === a.orden ? a.orden + d : b.orden;
        router.put(route('configuracion.entregas.rutas.update', a.id), { nombre: a.nombre, zona: a.zona, orden: oa }, {
            preserveScroll: true,
            onSuccess: () => router.put(route('configuracion.entregas.rutas.update', b.id), { nombre: b.nombre, zona: b.zona, orden: a.orden }, { preserveScroll: true }),
        });
    }

    const Formulario = (
        <div className="px-4 py-3" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 7%, var(--color-surface))', borderTop: '1px solid var(--color-border)' }}>
            <div className="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2 items-start">
                <div>
                    <input autoFocus value={form.nombre} maxLength={60} placeholder="Nombre (ej.: Ruta 3)" aria-label="Nombre de la ruta"
                        onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))} onKeyDown={e => e.key === 'Enter' && guardar()}
                        className="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2"
                        style={{ borderColor: errores.nombre ? 'var(--color-danger)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                    {errores.nombre && <p className="text-[13px] mt-1" style={{ color: 'var(--color-danger)' }}>{errores.nombre}</p>}
                </div>
                <input value={form.zona} maxLength={120} placeholder="Zona (ej.: Pomalca)" aria-label="Zona de la ruta"
                    onChange={e => setForm(f => ({ ...f, zona: e.target.value }))} onKeyDown={e => e.key === 'Enter' && guardar()}
                    className="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                <div className="flex gap-1.5">
                    <Button size="sm" onClick={guardar} disabled={!form.nombre.trim()}>Guardar</Button>
                    <button type="button" onClick={() => setEditando(null)} aria-label="Cancelar" className="p-2 rounded-lg hover:opacity-70" style={{ color: 'var(--color-text-muted)' }}><X size={16} /></button>
                </div>
            </div>
        </div>
    );

    return (
        <section className="rounded-2xl overflow-hidden" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <header className="flex items-center gap-3 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="flex h-9 w-9 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 16%, transparent)', color: 'var(--vp-mint-ink)' }}><MapPinned size={18} /></span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-display text-[17px] font-bold leading-tight" style={{ color: 'var(--color-text)' }}>Rutas de reparto</h2>
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                        {rutas.length ? `${plural(rutas.filter(r => r.activo).length, 'ruta activa', 'rutas activas')}, en el orden en que las ve la cajera` : 'La cajera elige una al registrar un envío'}
                    </p>
                </div>
                {puedeEditar && editando !== 'nueva' && (
                    <Button size="sm" variant="secondary" onClick={() => abrir(null)} startContent={<Plus size={15} />}>Agregar</Button>
                )}
            </header>

            {rutas.length === 0 && editando !== 'nueva' && (
                <div className="px-4 py-10 text-center">
                    <p className="text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>Todavía no hay rutas</p>
                    <p className="text-sm mt-1 max-w-sm mx-auto" style={{ color: 'var(--color-text-muted)' }}>
                        Sin rutas, el envío solo pide la dirección y la fecha. Agrégalas si repartes por zonas.
                    </p>
                </div>
            )}

            <ul className="max-h-[520px] overflow-y-auto">
                {rutas.map((r, i) => editando === r.id ? <li key={r.id}>{Formulario}</li> : (
                    <li key={r.id} className="flex items-center gap-3 px-4 py-2.5"
                        style={{ borderTop: i ? '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' : undefined, opacity: r.activo ? 1 : 0.55 }}>
                        {puedeEditar && (
                            <span className="flex flex-col">
                                <Flecha etiqueta={`Subir ${r.nombre}`} disabled={i === 0} onClick={() => mover(i, -1)}><ArrowUp size={14} /></Flecha>
                                <Flecha etiqueta={`Bajar ${r.nombre}`} disabled={i === rutas.length - 1} onClick={() => mover(i, 1)}><ArrowDown size={14} /></Flecha>
                            </span>
                        )}
                        <span className="min-w-0 flex-1">
                            <span className="block text-[15px] font-semibold truncate" style={{ color: 'var(--color-text)' }}>{r.nombre}</span>
                            <span className="block text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                {[r.zona, r.ventas_count ? plural(r.ventas_count, 'venta', 'ventas') : null, !r.activo ? 'desactivada' : null].filter(Boolean).join(', ') || 'Sin zona'}
                            </span>
                        </span>
                        {puedeEditar && (
                            <>
                                {!r.activo && (
                                    <button type="button" className="text-[13px] font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}
                                        onClick={() => router.put(route('configuracion.entregas.rutas.update', r.id), { nombre: r.nombre, zona: r.zona, activo: true }, { preserveScroll: true })}>
                                        Activar
                                    </button>
                                )}
                                <button type="button" onClick={() => abrir(r)} aria-label={`Editar ${r.nombre}`} title="Editar" className="p-2 rounded-lg hover:opacity-70" style={{ color: 'var(--color-text-muted)' }}><Pencil size={15} /></button>
                                {r.activo && (
                                    <button type="button" onClick={() => setBorrando(r)} aria-label={`Quitar ${r.nombre}`} title="Quitar" className="p-2 rounded-lg hover:opacity-70" style={{ color: 'var(--color-text-muted)' }}><Trash2 size={15} /></button>
                                )}
                            </>
                        )}
                    </li>
                ))}
            </ul>
            {editando === 'nueva' && Formulario}

            <Modal isOpen={!!borrando} onClose={() => setBorrando(null)} title="Quitar ruta" size="sm"
                footer={<>
                    <Button variant="ghost" onClick={() => setBorrando(null)}>Cancelar</Button>
                    <Button variant="danger" onClick={() => borrando && router.delete(route('configuracion.entregas.rutas.destroy', borrando.id), { preserveScroll: true, onFinish: () => setBorrando(null) })}>Quitar</Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    {borrando?.ventas_count
                        ? <><strong>{borrando.nombre}</strong> tiene {plural(borrando.ventas_count, 'venta', 'ventas')}: se desactivará para que no aparezca en ventas nuevas, y seguirá en su historial.</>
                        : <>Se eliminará <strong>{borrando?.nombre}</strong>. No tiene ventas.</>}
                </p>
            </Modal>
        </section>
    );
}

function Flecha({ etiqueta, disabled, onClick, children }: { etiqueta: string; disabled: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <button type="button" aria-label={etiqueta} title={etiqueta} disabled={disabled} onClick={onClick}
            className="flex h-5 w-7 items-center justify-center rounded transition-colors enabled:hover:bg-black/5 disabled:opacity-25"
            style={{ color: 'var(--color-text-muted)' }}>
            {children}
        </button>
    );
}

function Tarjeta({ icono, titulo, color, tinta, children }: { icono: React.ReactNode; titulo: string; color: string; tinta?: string; children: React.ReactNode }) {
    return (
        <section className="rounded-2xl overflow-hidden" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <header className="flex items-center gap-3 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="flex h-9 w-9 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: `color-mix(in srgb, ${color} 16%, transparent)`, color: tinta ?? color }}>{icono}</span>
                <h2 className="font-display text-[17px] font-bold leading-tight" style={{ color: 'var(--color-text)' }}>{titulo}</h2>
            </header>
            <div className="p-4">{children}</div>
        </section>
    );
}
