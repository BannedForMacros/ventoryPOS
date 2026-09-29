import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { ArrowDown, ArrowUp, Check, Eye, LayoutList, Printer, ReceiptText, Settings2, Sparkles, TriangleAlert, Type, UserRound } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Switch from '@/Components/UI/Switch';
import VistaPreviaTicket from '@/Components/Tickets/VistaPreviaTicket';
import VistaPreviaEstandar from '@/Components/Tickets/VistaPreviaEstandar';
import { estadoAgente, type EstadoAgente, type TicketPayload } from '@/lib/ticketPrinter';
import type { Bloque } from '@/lib/ticketBloques';
import type { PageProps } from '@/types';

interface Seccion { clave: string; activa: boolean; }
interface Plantilla {
    plantilla: 'estandar' | 'detallada';
    secciones: Seccion[];
    textos: Record<string, string>;
    opciones: Record<string, boolean>;
}
interface Muestra { clave: string; nombre: string; bloques: Bloque[]; }
interface MuestraEstandar { clave: string; nombre: string; ticket: TicketPayload; }
interface Def { clave: string; nombre: string; detalle?: string; }

interface Props extends PageProps {
    plantilla: Plantilla;
    catalogo: {
        plantillas: Def[];
        secciones: (Def & { docs: string[]; fija?: boolean })[];
        textos: (Def & { defecto: string; max: number })[];
        opciones: (Def & { defecto: boolean })[];
    };
    posDatosCliente: boolean;
    vistaPrevia: Muestra[];
    /** Las mismas muestras con el ticket de siempre. */
    vistaEstandar: MuestraEstandar[];
    puedeEditar: boolean;
}

export default function TicketPlantilla({ plantilla, catalogo, posDatosCliente, vistaPrevia, vistaEstandar, puedeEditar }: Props) {
    const { flash } = usePage<Props>().props;
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const [form, setForm] = useState<Plantilla>(plantilla);
    const [pideDatos, setPideDatos] = useState(posDatosCliente);
    const [muestras, setMuestras] = useState<Muestra[]>(vistaPrevia);
    const [muestra, setMuestra] = useState(vistaPrevia[1]?.clave ?? vistaPrevia[0]?.clave ?? 'pagada');
    const [papel, setPapel] = useState<80 | 58>(80);
    const [guardando, setGuardando] = useState(false);
    const [agente, setAgente] = useState<EstadoAgente | null>(null);

    const detallada = form.plantilla === 'detallada';
    const hayCambios = useMemo(
        () => JSON.stringify(form) !== JSON.stringify(plantilla) || pideDatos !== posDatosCliente,
        [form, plantilla, pideDatos, posDatosCliente],
    );

    useEffect(() => { estadoAgente().then(setAgente); }, []);

    // La vista previa la arma el servidor, con el mismo código que imprime.
    const primera = useRef(true);
    useEffect(() => {
        if (primera.current) { primera.current = false; return; }
        const t = setTimeout(async () => {
            try {
                const { data } = await axios.post(route('configuracion.ticket.vista-previa'), form);
                setMuestras(data.vistaPrevia);
            } catch { /* se conserva la vista anterior */ }
        }, 250);
        return () => clearTimeout(t);
    }, [form]);

    const def = (clave: string) => catalogo.secciones.find(s => s.clave === clave)!;

    function mover(i: number, d: -1 | 1) {
        setForm(f => {
            const s = [...f.secciones];
            const j = i + d;
            if (j < 0 || j >= s.length) return f;
            [s[i], s[j]] = [s[j], s[i]];
            return { ...f, secciones: s };
        });
    }

    function guardar() {
        setGuardando(true);
        router.put(route('configuracion.ticket.update'), { ...form, pos_datos_cliente: pideDatos } as never, {
            preserveScroll: true,
            onError: e => toast.error((Object.values(e)[0] as string) ?? 'No se pudo guardar.'),
            onFinish: () => setGuardando(false),
        });
    }

    const actual = muestras.find(m => m.clave === muestra) ?? muestras[0];
    const actualEstandar = vistaEstandar.find(m => m.clave === muestra) ?? vistaEstandar[0];

    return (
        <AppLayout title="Ticket">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Ticket</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Elige cómo sale el ticket impreso: qué secciones lleva, en qué orden y con qué textos. A la derecha ves cómo queda en el papel.
                    </p>
                </div>
                {puedeEditar && (
                    <div className="flex items-center gap-3">
                        {hayCambios && <span className="text-sm font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>Hay cambios sin guardar</span>}
                        <Button onClick={guardar} loading={guardando} disabled={!hayCambios} startContent={<Check size={16} />}>Guardar</Button>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
                {/* ── Configuración ── */}
                <div className="xl:col-span-7 space-y-4 min-w-0">
                    <Tarjeta icono={<ReceiptText size={18} />} titulo="Plantilla" color="var(--vp-navy)">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {catalogo.plantillas.map(p => {
                                const elegida = form.plantilla === p.clave;
                                return (
                                    <button key={p.clave} type="button" disabled={!puedeEditar}
                                        onClick={() => setForm(f => ({ ...f, plantilla: p.clave as Plantilla['plantilla'] }))}
                                        aria-pressed={elegida}
                                        className="text-left rounded-xl p-4 transition-colors"
                                        style={{
                                            border: `2px solid ${elegida ? 'var(--vp-navy)' : 'var(--color-border)'}`,
                                            backgroundColor: elegida ? 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))' : 'var(--color-surface)',
                                        }}>
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="font-display text-base font-bold" style={{ color: 'var(--color-text)' }}>{p.nombre}</span>
                                            {elegida && <span className="flex h-5 w-5 items-center justify-center rounded-full text-white" style={{ backgroundColor: 'var(--vp-navy)' }}><Check size={13} /></span>}
                                        </span>
                                        <span className="block text-sm mt-1" style={{ color: 'var(--color-text-muted)' }}>{p.detalle}</span>
                                    </button>
                                );
                            })}
                        </div>
                        {detallada && agente?.activo && agente.bloques < 1 && (
                            <Aviso>
                                El programa de impresión de esta PC es la versión {agente.version ?? 'anterior'} y todavía no imprime plantillas:
                                hasta que se actualice a la 1.3.0, aquí seguirá saliendo el ticket estándar.
                            </Aviso>
                        )}
                    </Tarjeta>

                    {!detallada && (
                        <Tarjeta icono={<Sparkles size={18} />} titulo="Qué agrega la Detallada" color="var(--vp-mint)" tinta="var(--vp-mint-ink)"
                            detalle="Tu ticket de hoy no cambia mientras sigas en Estándar">
                            <ul className="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-3">
                                {[
                                    ['Estado de pago en grande', 'Pagado, o por cancelar con el monto a cobrar.'],
                                    ['Pago por cada medio', 'Cuánto entró por efectivo, Yape o tarjeta, y el saldo.'],
                                    ['Datos del cliente destacados', 'Teléfono y dirección en un recuadro, y la observación.'],
                                    ['Celular de quien atendió', 'Para que el cliente sepa a quién llamar.'],
                                    ['Secciones a tu medida', 'Apagas las que no usas y las ordenas.'],
                                    ['Tus propios textos', 'Por ejemplo "Cobrar en obra" o "Cobrar al entregar".'],
                                ].map(([titulo, texto]) => (
                                    <li key={titulo} className="flex items-start gap-2.5">
                                        <span className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full"
                                            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 18%, transparent)', color: 'var(--vp-mint-ink)' }}>
                                            <Check size={13} />
                                        </span>
                                        <span>
                                            <span className="block text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>{titulo}</span>
                                            <span className="block text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{texto}</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            {puedeEditar && (
                                <div className="mt-4">
                                    <Button variant="secondary" onClick={() => setForm(f => ({ ...f, plantilla: 'detallada' }))} startContent={<Eye size={15} />}>
                                        Ver cómo quedaría
                                    </Button>
                                    <span className="ml-3 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>No se aplica hasta que guardes.</span>
                                </div>
                            )}
                        </Tarjeta>
                    )}

                    {detallada && (
                        <>
                            <Tarjeta icono={<LayoutList size={18} />} titulo="Secciones" color="var(--vp-sky)" tinta="var(--vp-navy)"
                                detalle="Apaga las que no quieras y ordénalas con las flechas" sinRelleno>
                                <ul>
                                    {form.secciones.map((s, i) => {
                                        const d = def(s.clave);
                                        return (
                                            <li key={s.clave} className="flex items-center gap-3 px-4 py-2.5"
                                                style={{ borderTop: i ? '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' : undefined, opacity: s.activa ? 1 : 0.6 }}>
                                                <span className="flex flex-col">
                                                    <BotonOrden etiqueta={`Subir ${d.nombre}`} disabled={!puedeEditar || i === 0} onClick={() => mover(i, -1)}><ArrowUp size={14} /></BotonOrden>
                                                    <BotonOrden etiqueta={`Bajar ${d.nombre}`} disabled={!puedeEditar || i === form.secciones.length - 1} onClick={() => mover(i, 1)}><ArrowDown size={14} /></BotonOrden>
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>
                                                        {d.nombre}
                                                        {!d.docs.includes('cotizacion') && <span className="ml-2 text-[13px] font-normal" style={{ color: 'var(--color-text-muted)' }}>solo en ventas</span>}
                                                    </span>
                                                    <span className="block text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{d.detalle}</span>
                                                </span>
                                                {d.fija ? (
                                                    <span className="text-[13px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>Siempre sale</span>
                                                ) : (
                                                    <Switch checked={s.activa} disabled={!puedeEditar} aria-label={`Imprimir ${d.nombre}`}
                                                        onChange={v => setForm(f => ({ ...f, secciones: f.secciones.map(x => x.clave === s.clave ? { ...x, activa: v } : x) }))} />
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </Tarjeta>

                            <Tarjeta icono={<Type size={18} />} titulo="Textos" color="var(--vp-mint)" tinta="var(--vp-mint-ink)"
                                detalle="Escríbelos como los dice tu negocio">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
                                    {catalogo.textos.map(t => (
                                        <label key={t.clave} className="block">
                                            <span className="block text-sm font-semibold mb-1" style={{ color: 'var(--color-text)' }}>{t.nombre}</span>
                                            <input type="text" maxLength={t.max} disabled={!puedeEditar}
                                                value={form.textos[t.clave] ?? ''} placeholder={t.defecto || 'Ej.: COBRAR AL ENTREGAR'}
                                                onChange={e => setForm(f => ({ ...f, textos: { ...f.textos, [t.clave]: e.target.value } }))}
                                                className="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2"
                                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                        </label>
                                    ))}
                                </div>
                            </Tarjeta>

                            <Tarjeta icono={<Settings2 size={18} />} titulo="Opciones" color="var(--vp-amber)" tinta="var(--vp-amber-ink)">
                                <div className="space-y-3.5">
                                    {catalogo.opciones.map(o => (
                                        <Switch key={o.clave} label={o.nombre} description={o.detalle} disabled={!puedeEditar}
                                            checked={form.opciones[o.clave] ?? o.defecto}
                                            onChange={v => setForm(f => ({ ...f, opciones: { ...f.opciones, [o.clave]: v } }))} />
                                    ))}
                                </div>
                            </Tarjeta>
                        </>
                    )}

                    <Tarjeta icono={<UserRound size={18} />} titulo="Datos del cliente al vender" color="var(--vp-coral)" tinta="var(--vp-coral-ink)">
                        <Switch checked={pideDatos} onChange={setPideDatos} disabled={!puedeEditar}
                            label="Pedir teléfono, dirección y observación en la venta y en la cotización"
                            description="Si el cliente ya los tiene registrados se llenan solos, y se pueden cambiar solo para esa venta (por ejemplo, la dirección de la obra)." />
                    </Tarjeta>

                    <p className="text-sm px-1" style={{ color: 'var(--color-text-muted)' }}>
                        El RUC, el cajero, la caja, el IGV, el logo y el mensaje final se configuran en{' '}
                        <Link href={route('configuracion.empresas.index')} className="font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>Empresas</Link>, y valen para las dos plantillas.
                    </p>
                </div>

                {/* ── Vista previa ── */}
                <aside className="xl:col-span-5 xl:sticky xl:top-4 rounded-2xl overflow-hidden min-w-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 88%, #000)' }}>
                    <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-white">
                        <span className="flex items-center gap-2 font-display text-base font-bold"><Eye size={17} /> Vista previa</span>
                        <Segmento valor={String(papel)} onChange={v => setPapel(Number(v) as 80 | 58)}
                            opciones={[['80', '80 mm'], ['58', '58 mm']]} etiqueta="Ancho del papel" />
                    </div>
                    <div className="px-4 pb-3">
                        <Segmento valor={muestra} onChange={setMuestra} etiqueta="Ticket de muestra" ancho
                            opciones={muestras.map(m => [m.clave, m.nombre] as [string, string])} />
                    </div>
                    <p className="mx-4 mb-3 rounded-xl px-3 py-2 text-[13px] text-white" style={{ backgroundColor: 'rgb(255 255 255 / 0.12)' }}>
                        {detallada
                            ? 'Plantilla Detallada: así saldrá con lo que elijas a la izquierda.'
                            : 'Plantilla Estándar: el ticket de siempre, sin ningún cambio.'}
                    </p>
                    <div className="px-3 pb-5 max-h-[calc(100vh-13rem)] overflow-y-auto" style={{ overscrollBehavior: 'contain' }}>
                        {detallada
                            ? actual && <VistaPreviaTicket bloques={actual.bloques} papel={papel} />
                            : actualEstandar && <VistaPreviaEstandar ticket={actualEstandar.ticket} papel={papel} />}
                    </div>
                    <p className="flex items-center gap-1.5 px-4 py-2.5 text-[13px] text-white/75" style={{ borderTop: '1px solid rgb(255 255 255 / 0.12)' }}>
                        <Printer size={14} /> Datos de muestra con el nombre y la dirección de tu negocio.
                    </p>
                </aside>
            </div>
        </AppLayout>
    );
}

function Tarjeta({ icono, titulo, detalle, color, tinta, sinRelleno, children }: {
    icono: React.ReactNode; titulo: string; detalle?: string; color: string; tinta?: string; sinRelleno?: boolean; children: React.ReactNode;
}) {
    return (
        <section className="rounded-2xl overflow-hidden" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <header className="flex items-center gap-3 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="flex h-9 w-9 items-center justify-center rounded-lg flex-shrink-0"
                    style={{ backgroundColor: `color-mix(in srgb, ${color} 16%, transparent)`, color: tinta ?? color }}>{icono}</span>
                <div className="min-w-0">
                    <h2 className="font-display text-[17px] font-bold leading-tight" style={{ color: 'var(--color-text)' }}>{titulo}</h2>
                    {detalle && <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{detalle}</p>}
                </div>
            </header>
            <div className={sinRelleno ? '' : 'p-4'}>{children}</div>
        </section>
    );
}

function BotonOrden({ etiqueta, disabled, onClick, children }: { etiqueta: string; disabled: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <button type="button" aria-label={etiqueta} title={etiqueta} disabled={disabled} onClick={onClick}
            className="flex h-5 w-7 items-center justify-center rounded transition-colors enabled:hover:bg-black/5 disabled:opacity-25"
            style={{ color: 'var(--color-text-muted)' }}>
            {children}
        </button>
    );
}

function Segmento({ valor, onChange, opciones, etiqueta, ancho }: {
    valor: string; onChange: (v: string) => void; opciones: [string, string][]; etiqueta: string; ancho?: boolean;
}) {
    return (
        <div role="group" aria-label={etiqueta} className={`${ancho ? 'flex' : 'inline-flex'} rounded-xl p-0.5`} style={{ backgroundColor: 'rgb(255 255 255 / 0.12)' }}>
            {opciones.map(([v, t]) => (
                <button key={v} type="button" onClick={() => onChange(v)} aria-pressed={valor === v}
                    className={`${ancho ? 'flex-1' : ''} px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors`}
                    style={{ backgroundColor: valor === v ? '#fff' : 'transparent', color: valor === v ? 'var(--vp-navy)' : 'rgb(255 255 255 / 0.85)' }}>
                    {t}
                </button>
            ))}
        </div>
    );
}

function Aviso({ children }: { children: React.ReactNode }) {
    return (
        <p className="mt-3 flex items-start gap-2 rounded-xl px-3 py-2.5 text-sm"
            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 12%, var(--color-surface))', color: 'var(--vp-amber-ink)' }}>
            <TriangleAlert size={16} className="mt-0.5 flex-shrink-0" /> <span>{children}</span>
        </p>
    );
}
