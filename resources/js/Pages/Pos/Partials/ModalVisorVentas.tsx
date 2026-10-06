import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import {
    AlertTriangle, ArrowRight, Camera, CheckCircle2, ClipboardPaste, ImagePlus, Loader2, RotateCcw,
    Search, ShoppingCart, Sparkles, Trash2,
} from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Callout from '@/Components/UI/Callout';

/** Producto del catálogo sugerido o elegido (precio y costo de la unidad base). */
export interface FichaProducto {
    producto_id: number;
    nombre:      string;
    precio:      number;
    costo:       number;
    puntaje?:    number;
}

/** Un renglón leído del cuaderno, ya cruzado con el catálogo (ver VisorVentasService). */
export interface ItemLeido {
    texto:          string;
    interpretacion: string | null;
    cantidad:       number | null;
    estado:         'verde' | 'ambar' | 'rojo';
    aprendido?:     boolean;
    candidatos:     FichaProducto[];
    // Lo que decide la cajera en la revisión:
    elegido?:       FichaProducto | null;
    quitado?:       boolean;
}

export interface VentaLeida {
    fecha:       string | null;
    total:       number | null;
    items:       ItemLeido[];
    /** Posición en la página (para saber si ESTA venta ya se cobró). */
    indice?:     number;
    /** Esta venta de esta lectura ya se cobró: se muestra cerrada. */
    ya_cobrada?: { venta: string; cuando: string; por: string | null } | null;
    /** Otra foto de la misma página la cobró: aviso que pide confirmar. */
    posible_cobrada?: { venta: string; cuando: string; por: string | null } | null;
    /** Huella de lo leído (vuelve al cobrar para reconocer la misma página). */
    huella?:     string;
    // Decisiones de la cajera:
    ajuste?:     'cuaderno' | 'lista';
    cargada?:    boolean;
    saltada?:    boolean;
    /** Ya se cobró, pero la cajera decidió cargarla otra vez. */
    forzar?:     boolean;
}

export interface LecturaVisor {
    /** La lectura (sesión) de donde salen: cada cobro se anota con ella y la posición. */
    sesion?: number;
    ventas: VentaLeida[];
}

/** Lo que llega del servidor, listo para revisar: lo verde llega ya elegido. */
export function prepararLectura(ventas: VentaLeida[], sesion?: number): LecturaVisor {
    return {
        sesion,
        ventas: ventas.map(v => ({
            ...v,
            items: v.items.map(i => ({ ...i, elegido: i.estado === 'verde' && i.candidatos[0] ? i.candidatos[0] : null })),
        })),
    };
}

/** "05/10/26" → ¿es de otro día que hoy? (null si no se entiende la fecha). */
function esDeOtroDia(fecha: string | null): boolean {
    const m = fecha?.match(/(\d{1,2})\s*[/.-]\s*(\d{1,2})(?:\s*[/.-]\s*(\d{2,4}))?/);
    if (!m) return false;
    const hoy = new Date();
    const anio = m[3] ? Number(m[3].length === 2 ? `20${m[3]}` : m[3]) : hoy.getFullYear();
    return Number(m[1]) !== hoy.getDate() || Number(m[2]) !== hoy.getMonth() + 1 || anio !== hoy.getFullYear();
}

const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`;

/** Lo que el POS pone en el carrito: una línea por producto, con su precio final. */
export interface LineaPlan {
    producto_id: number;
    nombre:      string;
    cantidad:    number;
    precio:      number;
}

interface Props {
    isOpen:       boolean;
    onClose:      () => void;
    limite:       number;
    restantes:    number;
    onRestantes:  (n: number) => void;
    /** La lectura vive en el POS: cerrar el modal para cobrar no la pierde ni gasta otra. */
    lectura:      LecturaVisor | null;
    onLectura:    (l: LecturaVisor | null) => void;
    carritoVacio: boolean;
    /** La empresa permite el mismo producto en dos líneas (si no, se suman). */
    permiteDuplicar: boolean;
    onCargar:     (venta: VentaLeida, indice: number, lineas: LineaPlan[]) => Promise<boolean>;
}

const ESTILO = {
    verde: { color: 'var(--vp-mint)',  tinta: 'var(--vp-mint-ink)',  texto: 'Entendí' },
    ambar: { color: 'var(--vp-amber)', tinta: 'var(--vp-amber-ink)', texto: 'Dudoso: elige' },
    rojo:  { color: 'var(--color-danger)', tinta: 'var(--vp-coral-ink)', texto: 'No lo encontré' },
} as const;

const S = (n: number) => `S/ ${n.toFixed(2)}`;
const r2 = (n: number) => Math.round(n * 100) / 100;

/**
 * Reduce la foto antes de subirla (lado mayor 1568 px, JPEG): Claude no lee
 * mejor una imagen más grande, y así sube rápido y pesa poco.
 */
async function prepararFoto(archivo: File): Promise<Blob> {
    const url = URL.createObjectURL(archivo);
    try {
        const img = await new Promise<HTMLImageElement>((ok, mal) => {
            const i = new Image();
            i.onload = () => ok(i);
            i.onerror = mal;
            i.src = url;
        });
        const escala = Math.min(1, 1568 / Math.max(img.width, img.height));
        const lienzo = document.createElement('canvas');
        lienzo.width = Math.round(img.width * escala);
        lienzo.height = Math.round(img.height * escala);
        lienzo.getContext('2d')!.drawImage(img, 0, 0, lienzo.width, lienzo.height);
        return await new Promise<Blob>((ok, mal) => lienzo.toBlob(b => (b ? ok(b) : mal(new Error('foto'))), 'image/jpeg', 0.85));
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** Un renglón está listo si tiene producto y cantidad, o si se quitó. */
const itemListo = (i: ItemLeido) => i.quitado || (!!i.elegido && (i.cantidad ?? 0) > 0);

/** Cómo suma el POS: cada línea precio × cantidad redondeado a céntimos (ver recalcularLinea). */
const subtotalPos = (precio: number, cantidad: number) => Math.round(precio * cantidad * 100) / 100;
const sumaPos = (ls: LineaPlan[]) => r2(ls.reduce((s, l) => s + subtotalPos(l.precio, l.cantidad), 0));

/**
 * Precios para que la venta sume el total del cuaderno. Primero el mismo
 * factor para todos; como el precio va en céntimos, 3 × S/ 3.33 da 9.99 y no
 * 10: se mueven unos céntimos en hasta 3 líneas (las de menor cantidad) hasta
 * dar el total exacto, o lo más cerca posible si con esas cantidades no se puede.
 */
function ajustarAlTotal(lineas: LineaPlan[], lista: number, total: number): LineaPlan[] {
    const factor = total / lista;
    const base = lineas.map(l => ({ ...l, precio: r2(l.precio * factor) }));
    const meta = Math.round(total * 100);
    const centavos = (ls: LineaPlan[]) => Math.round(sumaPos(ls) * 100);
    if (centavos(base) === meta) return base;

    const elegidas = base.map((_, i) => i).sort((a, b) => base[a].cantidad - base[b].cantidad).slice(0, 3);
    const resto = centavos(base.filter((_, i) => !elegidas.includes(i)));
    const R = 15;
    // Por cada línea elegida: [movimiento en céntimos, subtotal en céntimos].
    const opciones = elegidas.map(i => {
        const op: [number, number][] = [];
        for (let c = -R; c <= R; c++) {
            const precio = r2(base[i].precio + c / 100);
            if (precio > 0) op.push([c, Math.round(subtotalPos(precio, base[i].cantidad) * 100)]);
        }
        return op;
    });

    let mejor: number[] = elegidas.map(() => 0);
    let mejorDif = Math.abs(meta - centavos(base));
    let mejorMov = 0;
    const probar = (k: number, suma: number, mov: number, actual: number[]) => {
        if (k === opciones.length) {
            const dif = Math.abs(meta - suma);
            if (dif < mejorDif || (dif === mejorDif && mov < mejorMov)) { mejor = [...actual]; mejorDif = dif; mejorMov = mov; }
            return;
        }
        for (const [c, sub] of opciones[k]) {
            actual[k] = c;
            probar(k + 1, suma + sub, mov + Math.abs(c), actual);
        }
    };
    probar(0, resto, 0, []);

    return base.map((l, i) => {
        const k = elegidas.indexOf(i);
        return k < 0 ? l : { ...l, precio: r2(l.precio + mejor[k] / 100) };
    });
}

/**
 * Todo lo que la revisión necesita saber de una venta: qué falta, cuánto suma
 * a precio de lista, si coincide con el cuaderno, si se puede ajustar sin
 * quedar bajo el costo, y las líneas que irían al carrito.
 */
function analizar(venta: VentaLeida, permiteDuplicar: boolean) {
    const activos = venta.items.filter(i => !i.quitado);
    const sinProducto = activos.filter(i => !i.elegido).length;
    const sinCantidad = activos.filter(i => i.elegido && !((i.cantidad ?? 0) > 0)).length;
    const listos = activos.length > 0 && sinProducto === 0 && sinCantidad === 0;

    // Repetidos dentro de la venta: se suman en una línea (como el POS), salvo
    // que la empresa permita el mismo producto en dos líneas.
    const veces = new Map<number, number>();
    activos.forEach(i => i.elegido && veces.set(i.elegido.producto_id, (veces.get(i.elegido.producto_id) ?? 0) + 1));

    let lineas: LineaPlan[] = [];
    if (listos) {
        for (const i of activos) {
            const p = i.elegido!;
            const previa = !permiteDuplicar ? lineas.find(l => l.producto_id === p.producto_id) : undefined;
            if (previa) previa.cantidad = r2(previa.cantidad + i.cantidad!);
            else lineas.push({ producto_id: p.producto_id, nombre: p.nombre, cantidad: i.cantidad!, precio: p.precio });
        }
    }

    const lista = sumaPos(lineas);
    const diferencia = venta.total != null && listos ? r2(venta.total - lista) : 0;
    const coincide = Math.abs(diferencia) < 0.01;

    // Ajuste al total del cuaderno: mismo factor para todos los precios y luego
    // el cuadre al céntimo (ver ajustarAlTotal).
    let ajustadas: LineaPlan[] | null = null;
    let alcanzado: number | null = null;
    let bajoCosto: string | null = null;
    const sinPrecio = listos && lista <= 0;
    // Muy lejos del precio de lista: suele ser una cantidad mal leída (por caja, no por unidad).
    const lejos = listos && venta.total != null && lista > 0 && !coincide && (venta.total / lista < 0.5 || venta.total / lista > 1.5);
    if (listos && venta.total != null && !coincide && lista > 0) {
        ajustadas = ajustarAlTotal(lineas, lista, venta.total);
        alcanzado = sumaPos(ajustadas);
        const fichas = new Map(activos.map(i => [i.elegido!.producto_id, i.elegido!]));
        const debajo = ajustadas.find(l => (fichas.get(l.producto_id)?.costo ?? 0) > 0 && l.precio < (fichas.get(l.producto_id)!.costo - 0.009));
        if (debajo) bajoCosto = debajo.nombre;
    }

    return { activos, sinProducto, sinCantidad, listos, veces, lineas, lista, diferencia, coincide, ajustadas, alcanzado, bajoCosto, sinPrecio, lejos };
}

export default function ModalVisorVentas({ isOpen, onClose, limite, restantes, onRestantes, lectura, onLectura, carritoVacio, permiteDuplicar, onCargar }: Props) {
    const [foto, setFoto] = useState<{ blob: Blob; vista: string } | null>(null);
    const [leyendo, setLeyendo] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [aviso, setAviso] = useState<string | null>(null);
    // "Leer otra foto" con ventas sin cargar: se confirma antes de perder la revisión.
    const [confirmarOtra, setConfirmarOtra] = useState(false);
    const [cargando, setCargando] = useState<number | null>(null);
    // Venta que ya parece cobrada y espera confirmación para cargarse de nuevo.
    const [confirmarRepetida, setConfirmarRepetida] = useState<{ indice: number; info: NonNullable<VentaLeida['ya_cobrada']> } | null>(null);
    const camaraRef = useRef<HTMLInputElement>(null);
    const galeriaRef = useRef<HTMLInputElement>(null);

    useEffect(() => () => { if (foto) URL.revokeObjectURL(foto.vista); }, [foto]);

    // Pegar la foto con Ctrl+V (p. ej. copiada de WhatsApp Web o una captura).
    const puedeElegir = isOpen && !lectura && !leyendo && restantes > 0;
    useEffect(() => {
        if (!puedeElegir) return;
        function alPegar(e: ClipboardEvent) {
            const archivo = [...(e.clipboardData?.items ?? [])].find(i => i.type.startsWith('image/'))?.getAsFile();
            if (archivo) {
                e.preventDefault();
                void elegir(archivo);
            }
        }
        window.addEventListener('paste', alPegar);
        return () => window.removeEventListener('paste', alPegar);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [puedeElegir]);
    const [arrastrando, setArrastrando] = useState(false);

    async function elegir(archivo?: File) {
        if (!archivo) return;
        setError(null);
        try {
            const blob = await prepararFoto(archivo);
            setFoto({ blob, vista: URL.createObjectURL(blob) });
        } catch {
            setError('No se pudo abrir esa imagen. Prueba con otra foto.');
        }
    }

    async function leer() {
        if (!foto) return;
        setLeyendo(true);
        setError(null);
        const datos = new FormData();
        datos.append('foto', foto.blob, 'cuaderno.jpg');
        try {
            const { data } = await axios.post(route('pos.visor-ventas'), datos);
            onRestantes(data.restantes);
            onLectura(prepararLectura(data.ventas as VentaLeida[], data.sesion));
            setAviso(data.aviso ?? null);
            setFoto(null);
        } catch (e: unknown) {
            const r = axios.isAxiosError(e) ? e.response : undefined;
            const resp = r?.data;
            if (typeof resp?.restantes === 'number') onRestantes(resp.restantes);
            // Solo los mensajes propios llegan a la cajera; nunca "Server Error" ni textos en inglés.
            setError(
                r?.status === 422 ? (resp?.errors?.foto?.[0] ?? resp?.message ?? 'Revisa la foto e intenta de nuevo.')
                : r?.status === 429 ? (resp?.message ?? 'Vas muy rápido. Espera un minuto y vuelve a intentar.')
                : r?.status === 504 || r?.status === 502 || !r
                    ? 'La lectura está tardando más de lo normal. Espera un minuto y vuelve a tocar "Leer ventas" con la misma foto: si ya se leyó, no gasta otra lectura.'
                : 'No se pudo leer la foto en este momento. Intenta de nuevo en unos minutos.',
            );
        } finally {
            setLeyendo(false);
        }
    }

    function cambiarVenta(v: number, cambio: Partial<VentaLeida>) {
        if (!lectura) return;
        onLectura({ ...lectura, ventas: lectura.ventas.map((venta, vi) => (vi === v ? { ...venta, ...cambio } : venta)) });
    }

    function cambiarItem(v: number, i: number, cambio: Partial<ItemLeido>) {
        if (!lectura) return;
        onLectura({
            ...lectura,
            ventas: lectura.ventas.map((venta, vi) => vi !== v ? venta : {
                ...venta,
                // Cambiar un producto o una cantidad invalida la decisión del total.
                ajuste: undefined,
                items: venta.items.map((item, ii) => (ii !== i ? item : { ...item, ...cambio })),
            }),
        });
    }

    async function cargar(v: number, aunqueRepetida = false) {
        if (!lectura) return;
        const venta = lectura.ventas[v];
        const a = analizar(venta, permiteDuplicar);
        const lineas = venta.ajuste === 'cuaderno' && a.ajustadas ? a.ajustadas : a.lineas;
        setCargando(v);
        try {
            if (!aunqueRepetida && !venta.forzar) {
                // Otra foto de la misma página: lo cobrado ahí parece ser esta venta.
                if (venta.posible_cobrada) {
                    setConfirmarRepetida({ indice: v, info: venta.posible_cobrada });
                    return;
                }
                // ¿Otra cajera cobró ESTA venta de ESTA lectura mientras tanto?
                if (lectura.sesion != null) {
                    const { data } = await axios.post(route('pos.visor-ventas.verificar'), { sesion: lectura.sesion, indice: venta.indice ?? v });
                    if (data.ya_cobrada) {
                        setConfirmarRepetida({ indice: v, info: data.ya_cobrada });
                        return;
                    }
                }
            }
            setConfirmarRepetida(null);
            await onCargar(venta, v, lineas);
        } catch {
            setError('No se pudo comprobar si esta venta ya se cobró. Revisa tu conexión e intenta de nuevo.');
        } finally {
            setCargando(null);
        }
    }

    /** Lleva al primer renglón que falta resolver de una venta. */
    function irAPendiente(v: number) {
        const el = document.querySelector<HTMLElement>(`[data-visor-pendiente="${v}"]`);
        el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        // Primero lo que falta: el producto (sugerencias o buscador); si no, la cantidad.
        (el?.querySelector<HTMLElement>('[data-visor-elegir] button, input[aria-label="Buscar producto"]')
            ?? el?.querySelector<HTMLElement>('input'))?.focus();
    }

    const ventas = lectura?.ventas ?? [];
    const cobradaAntes = (v: VentaLeida) => !!v.ya_cobrada && !v.forzar && !v.cargada;
    const cargadas = ventas.filter(v => v.cargada).length;
    const saltadas = ventas.filter(v => v.saltada && !v.cargada).length;
    const yaCobradas = ventas.filter(v => cobradaAntes(v) && !v.saltada).length;
    const terminadas = cargadas + saltadas + yaCobradas;
    const listas = ventas.filter(v => !v.cargada && !v.saltada && !cobradaAntes(v) && analizar(v, permiteDuplicar).listos).length;
    const sinCargar = ventas.length - terminadas;

    return (
        <Modal isOpen={isOpen} onClose={() => !leyendo && onClose()} title="Leer ventas del cuaderno" size="3xl"
            footer={
                <div className="flex w-full items-center justify-between gap-3">
                    <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Te quedan <strong>{restantes}</strong> de {limite} lecturas hoy.
                    </span>
                    {lectura ? (
                        confirmarOtra ? (
                            <span className="flex items-center gap-2">
                                <span className="text-xs font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>
                                    {plural(sinCargar, 'venta queda', 'ventas quedan')} sin cargar. ¿Leer otra foto igual?
                                </span>
                                <Button size="sm" variant="ghost" onClick={() => setConfirmarOtra(false)}>No</Button>
                                <Button size="sm" onClick={() => { onLectura(null); setError(null); setAviso(null); setConfirmarOtra(false); }}>Sí, leer otra</Button>
                            </span>
                        ) : (
                            <Button variant="ghost" disabled={restantes <= 0}
                                onClick={() => {
                                    if (sinCargar > 0) { setConfirmarOtra(true); return; }
                                    onLectura(null); setError(null); setAviso(null);
                                }}>
                                <RotateCcw size={15} className="mr-1" />Leer otra foto
                            </Button>
                        )
                    ) : (
                        <Button onClick={leer} disabled={!foto || leyendo || restantes <= 0} loading={leyendo}>
                            {leyendo ? 'Leyendo…' : 'Leer ventas'}
                        </Button>
                    )}
                </div>
            }
        >
            {error && <Callout variant="danger" className="mb-3">{error}</Callout>}
            {aviso && lectura && <Callout variant="info" className="mb-3">{aviso}</Callout>}

            {!lectura && (
                <div className="space-y-4">
                    {restantes <= 0 && !error && (
                        <Callout variant="warning">Su plan es solo para {limite} sesiones máximas por día.</Callout>
                    )}
                    <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                        Toma la foto de frente, con buena luz y la página completa. Luego revisas lo que se entendió antes de cobrar.
                    </p>
                    <input ref={camaraRef} type="file" accept="image/*" capture="environment" className="hidden"
                        onChange={e => { void elegir(e.target.files?.[0]); e.target.value = ''; }} />
                    <input ref={galeriaRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden"
                        onChange={e => { void elegir(e.target.files?.[0]); e.target.value = ''; }} />
                    <div
                        className="grid sm:grid-cols-2 gap-3 rounded-2xl transition-shadow"
                        onDragOver={e => { if (puedeElegir) { e.preventDefault(); setArrastrando(true); } }}
                        onDragLeave={() => setArrastrando(false)}
                        onDrop={e => {
                            e.preventDefault();
                            setArrastrando(false);
                            const archivo = [...e.dataTransfer.files].find(f => f.type.startsWith('image/'));
                            if (puedeElegir && archivo) void elegir(archivo);
                        }}
                        style={{ boxShadow: arrastrando ? '0 0 0 3px color-mix(in srgb, var(--color-primary) 35%, transparent)' : undefined }}
                    >
                        <BotonGrande Icono={Camera} titulo="Tomar foto" ayuda="Abre la cámara" onClick={() => camaraRef.current?.click()} disabled={leyendo || restantes <= 0} />
                        <BotonGrande Icono={ImagePlus} titulo="Elegir una foto" ayuda="Desde la galería o la PC" onClick={() => galeriaRef.current?.click()} disabled={leyendo || restantes <= 0} />
                    </div>
                    {restantes > 0 && (
                        <p className="flex items-center justify-center gap-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            <ClipboardPaste size={14} /> También puedes <strong>pegar</strong> la foto con Ctrl+V o <strong>arrastrarla</strong> aquí.
                        </p>
                    )}
                    {foto && (
                        <div className="relative rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            <img src={foto.vista} alt="Foto del cuaderno" className="w-full max-h-[50vh] object-contain" style={{ backgroundColor: 'var(--color-bg)' }} />
                            {leyendo && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm font-semibold"
                                    style={{ backgroundColor: 'rgb(255 255 255 / 0.75)', color: 'var(--vp-navy)' }}>
                                    <Loader2 size={26} className="animate-spin" />
                                    Leyendo el cuaderno… puede tardar unos segundos.
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {lectura && (
                <div className="space-y-3">
                    {ventas.length === 0 ? (
                        <Callout variant="warning">No se encontraron ventas en la foto. Toma otra de frente y con la página completa.</Callout>
                    ) : (
                        <>
                            {/* Avance: cuánto falta de la página. */}
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                                    <strong>{plural(ventas.length, 'venta leída', 'ventas leídas')}</strong>
                                    <span style={{ color: 'var(--color-text-muted)' }}>
                                        {' · '}{plural(cargadas, 'cargada', 'cargadas')}
                                        {yaCobradas > 0 && <> · {plural(yaCobradas, 'ya cobrada', 'ya cobradas')}</>}
                                        {saltadas > 0 && <> · {plural(saltadas, 'saltada', 'saltadas')}</>}
                                        {' · '}{listas === 1 ? '1 lista para cargar' : `${listas} listas para cargar`}
                                    </span>
                                </p>
                                <div className="h-2 w-40 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-bg)' }}
                                    role="progressbar" aria-valuenow={terminadas} aria-valuemin={0} aria-valuemax={ventas.length}>
                                    <div className="h-full rounded-full transition-[width] duration-300"
                                        style={{ width: `${(terminadas / ventas.length) * 100}%`, backgroundColor: 'var(--vp-mint)' }} />
                                </div>
                            </div>
                            {!carritoVacio && (
                                <Callout variant="info">Cobra o vacía el carrito actual antes de cargar otra venta.</Callout>
                            )}
                        </>
                    )}

                    {ventas.map((venta, v) => (
                        <TarjetaVenta key={v} indice={v} venta={venta} permiteDuplicar={permiteDuplicar}
                            carritoVacio={carritoVacio} cargando={cargando === v} ocupado={cargando !== null}
                            confirmarRepetida={confirmarRepetida?.indice === v ? confirmarRepetida.info : null}
                            onCambioVenta={c => cambiarVenta(v, c)}
                            onCambioItem={(i, c) => cambiarItem(v, i, c)}
                            onCargar={aunque => cargar(v, aunque)}
                            onSaltar={() => { setConfirmarRepetida(null); cambiarVenta(v, { saltada: true }); }}
                            onIrAPendiente={() => irAPendiente(v)} />
                    ))}
                </div>
            )}
        </Modal>
    );
}

/** Una venta del cuaderno: renglones, comparación con el total anotado y el botón para cargarla. */
function TarjetaVenta({ indice, venta, permiteDuplicar, carritoVacio, cargando, ocupado, confirmarRepetida, onCambioVenta, onCambioItem, onCargar, onSaltar, onIrAPendiente }: {
    indice: number;
    venta: VentaLeida;
    permiteDuplicar: boolean;
    carritoVacio: boolean;
    cargando: boolean;
    ocupado: boolean;
    confirmarRepetida: NonNullable<VentaLeida['ya_cobrada']> | null;
    onCambioVenta: (c: Partial<VentaLeida>) => void;
    onCambioItem: (i: number, c: Partial<ItemLeido>) => void;
    onCargar: (aunqueRepetida?: boolean) => void;
    onSaltar: () => void;
    onIrAPendiente: () => void;
}) {
    const a = analizar(venta, permiteDuplicar);
    // Ya se cobró (otra foto de la página, o se volvió al POS tras cobrar): cerrada salvo que decida cargarla otra vez.
    const cobradaAntes = !!venta.ya_cobrada && !venta.forzar && !venta.cargada;
    const cerrada = !!venta.cargada || !!venta.saltada || cobradaAntes;
    const primerPendiente = venta.items.findIndex(i => !itemListo(i));
    // Hay que decidir qué hacer con la diferencia antes de cargar.
    const faltaDecidirTotal = a.listos && venta.total != null && !a.coincide && !venta.ajuste;

    const pendiente = a.sinProducto > 0
        ? (a.sinProducto === 1 ? 'Falta elegir 1 producto' : `Falta elegir ${a.sinProducto} productos`)
        : a.sinCantidad > 0
            ? (a.sinCantidad === 1 ? 'Falta 1 cantidad' : `Faltan ${a.sinCantidad} cantidades`)
            : a.activos.length === 0 ? 'Quitaste todos los renglones'
            : faltaDecidirTotal ? 'Decide qué hacer con la diferencia del total'
            : null;

    return (
        <section className="rounded-xl overflow-hidden"
            style={{ border: `1px solid ${venta.ya_cobrada && !cerrada ? 'var(--vp-amber)' : 'var(--color-border)'}`, opacity: cerrada ? 0.6 : 1 }}>
            <header className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-3 py-2"
                style={{ backgroundColor: 'var(--color-bg)', borderBottom: '1px solid var(--color-border)' }}>
                <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                    Venta {indice + 1}{venta.fecha && <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}> · {venta.fecha}</span>}
                    {venta.fecha && esDeOtroDia(venta.fecha) && !cerrada && (
                        <span className="ml-2 text-xs font-semibold" style={{ color: 'var(--vp-amber-ink)' }}
                            title="Las ventas se registran con la fecha y el turno en que se cobran">
                            No es de hoy: se registrará con la fecha de hoy
                        </span>
                    )}
                </span>
                <span className="text-sm tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                    Cuaderno <strong style={{ color: 'var(--color-text)' }}>{venta.total != null ? S(venta.total) : 'sin total'}</strong>
                    {a.listos && <> · Catálogo <strong style={{ color: a.coincide || venta.total == null ? 'var(--color-text)' : 'var(--vp-amber-ink)' }}>{S(a.lista)}</strong></>}
                </span>
            </header>

            {/* Misma página leída otra vez: se avisa antes de nada. */}
            {((venta.ya_cobrada && venta.forzar) || (venta.posible_cobrada && !venta.ya_cobrada)) && !venta.cargada && !venta.saltada && (
                <div className="px-3 pt-2">
                    <Callout variant="warning" title="Parece que esta venta ya se cobró">
                        {(() => { const c = (venta.ya_cobrada ?? venta.posible_cobrada)!; return <>Coincide con la venta {c.venta} del {c.cuando}{c.por ? ` (${c.por})` : ''}{venta.ya_cobrada ? '' : ', de otra foto de esta página'}. Revisa antes de cobrarla otra vez.</>; })()}
                    </Callout>
                </div>
            )}

            <ul>
                {venta.items.map((item, i) => (
                    <FilaItem key={i} item={item} deshabilitado={cerrada}
                        repetido={!permiteDuplicar && !item.quitado && item.elegido ? (a.veces.get(item.elegido.producto_id) ?? 0) : 0}
                        marcaPendiente={i === primerPendiente ? indice : null}
                        onCambio={c => onCambioItem(i, c)} />
                ))}
            </ul>

            {/* La diferencia con el total anotado: sugerencia que la cajera acepta o rechaza. */}
            {!cerrada && a.listos && venta.total != null && !a.coincide && (
                <div className="px-3 pb-2">
                    <div className="rounded-lg p-3 space-y-2" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 10%, var(--color-surface))', border: '1px solid color-mix(in srgb, var(--vp-amber) 40%, transparent)' }}>
                        <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                            {a.diferencia < 0
                                ? <>En el cuaderno cobraste <strong>{S(-a.diferencia)} menos</strong> que el precio de lista ({S(a.lista)}).</>
                                : <>En el cuaderno cobraste <strong>{S(a.diferencia)} más</strong> que el precio de lista ({S(a.lista)}).</>}
                            {' '}¿Con cuánto la cobro?
                        </p>
                        {a.lejos && (
                            <p className="flex items-start gap-1.5 text-xs font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>
                                <AlertTriangle size={14} className="flex-shrink-0 mt-px" />
                                Es muy distinto del precio de lista: revisa las cantidades (¿se anotó por caja y no por unidad?).
                            </p>
                        )}
                        {a.sinPrecio && (
                            <p className="flex items-start gap-1.5 text-xs font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>
                                <AlertTriangle size={14} className="flex-shrink-0 mt-px" />
                                Estos productos no tienen precio en el catálogo: cárgala y pon el precio en el carrito.
                            </p>
                        )}
                        {a.alcanzado != null && Math.abs(a.alcanzado - venta.total) >= 0.01 && !a.bajoCosto && (
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Con estas cantidades no se llega exacto: lo más cercano es {S(a.alcanzado)}.
                            </p>
                        )}
                        {a.bajoCosto && (
                            <p className="flex items-start gap-1.5 text-xs font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>
                                <AlertTriangle size={14} className="flex-shrink-0 mt-px" />
                                No se puede usar {S(venta.total)}: {a.bajoCosto} quedaría bajo su costo. Revisa las cantidades o cóbrala a precio de lista.
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Precio a cobrar">
                            <OpcionTotal activo={venta.ajuste === 'cuaderno'} deshabilitado={!!a.bajoCosto || a.sinPrecio}
                                onClick={() => onCambioVenta({ ajuste: 'cuaderno' })}
                                titulo={`Usar ${S(venta.total)} del cuaderno`} ayuda="Ajusta los precios para que sume eso" />
                            <OpcionTotal activo={venta.ajuste === 'lista'}
                                onClick={() => onCambioVenta({ ajuste: 'lista' })}
                                titulo={`Cobrar ${S(a.lista)} de lista`} ayuda="Deja los precios del catálogo" />
                        </div>
                    </div>
                </div>
            )}

            <footer className="flex flex-wrap items-center justify-between gap-2 px-3 py-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                {venta.cargada ? (
                    <span className="flex items-center gap-1.5 text-sm font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>
                        <CheckCircle2 size={16} /> Cargada al carrito
                    </span>
                ) : cobradaAntes && !venta.saltada ? (
                    <span className="flex w-full flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="flex items-center gap-1.5 font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>
                            <CheckCircle2 size={16} /> Ya cobrada en {venta.ya_cobrada!.venta} ({venta.ya_cobrada!.cuando})
                        </span>
                        <button type="button" className="text-xs font-semibold underline" style={{ color: 'var(--color-text-muted)', opacity: 1 }}
                            onClick={() => onCambioVenta({ forzar: true })}>
                            No es la misma: cargarla
                        </button>
                    </span>
                ) : venta.saltada ? (
                    <span className="flex items-center gap-2 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                        Saltada
                        <button type="button" className="text-xs font-semibold underline" onClick={() => onCambioVenta({ saltada: false })}>Deshacer</button>
                    </span>
                ) : confirmarRepetida ? (
                    <div className="flex w-full flex-wrap items-center justify-between gap-2">
                        <span className="text-sm font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>
                            Ya se cobró una venta igual ({confirmarRepetida.venta}, {confirmarRepetida.cuando}). ¿Cargarla otra vez?
                        </span>
                        <span className="flex gap-2">
                            <Button size="sm" variant="ghost" onClick={onSaltar}>No, saltarla</Button>
                            <Button size="sm" onClick={() => onCargar(true)} loading={cargando} disabled={!carritoVacio || ocupado}
                                title={!carritoVacio ? 'Cobra o vacía el carrito primero' : undefined}>Sí, cargar</Button>
                        </span>
                    </div>
                ) : (
                    <>
                        {pendiente ? (
                            <button type="button" onClick={onIrAPendiente} disabled={a.activos.length === 0 || faltaDecidirTotal}
                                className="inline-flex items-center gap-1.5 text-sm font-semibold hover:underline disabled:no-underline"
                                style={{ color: 'var(--vp-amber-ink)' }}>
                                <AlertTriangle size={15} /> {pendiente}
                                {!faltaDecidirTotal && a.activos.length > 0 && <ArrowRight size={14} />}
                            </button>
                        ) : (
                            <span className="text-sm" style={{ color: 'var(--vp-mint-ink)' }}>
                                Lista para cargar · {S(venta.ajuste === 'cuaderno' && a.alcanzado != null ? a.alcanzado : a.lista)}
                            </span>
                        )}
                        <span className="flex items-center gap-2">
                            <button type="button" onClick={onSaltar} className="text-xs font-semibold px-2 py-1 rounded-md hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>
                                Saltar
                            </button>
                            <Button size="sm" onClick={() => onCargar()} disabled={!!pendiente || !carritoVacio || ocupado} loading={cargando}>
                                <ShoppingCart size={14} className="mr-1" />Cargar al carrito
                            </Button>
                        </span>
                    </>
                )}
            </footer>
        </section>
    );
}

function OpcionTotal({ activo, deshabilitado, onClick, titulo, ayuda }: { activo: boolean; deshabilitado?: boolean; onClick: () => void; titulo: string; ayuda: string }) {
    return (
        <button type="button" role="radio" aria-checked={activo} disabled={deshabilitado} onClick={onClick}
            className="flex-1 min-w-[12rem] text-left rounded-lg px-3 py-2 transition-colors disabled:opacity-50"
            style={{
                border: `1.5px solid ${activo ? 'var(--vp-navy)' : 'var(--color-border)'}`,
                backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 7%, var(--color-surface))' : 'var(--color-surface)',
            }}>
            <span className="flex items-center justify-between gap-2 text-sm font-bold" style={{ color: 'var(--color-text)' }}>
                {titulo} {activo && <CheckCircle2 size={15} style={{ color: 'var(--vp-navy)' }} />}
            </span>
            <span className="block text-xs" style={{ color: 'var(--color-text-muted)' }}>{ayuda}</span>
        </button>
    );
}

function BotonGrande({ Icono, titulo, ayuda, onClick, disabled }: { Icono: typeof Camera; titulo: string; ayuda: string; onClick: () => void; disabled?: boolean }) {
    return (
        <button type="button" onClick={onClick} disabled={disabled}
            className="flex items-center gap-3 rounded-xl p-4 text-left transition-colors hover:bg-black/[0.03] disabled:opacity-50"
            style={{ border: '1.5px dashed var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
            <span className="flex h-11 w-11 items-center justify-center rounded-xl flex-shrink-0"
                style={{ backgroundColor: 'var(--vp-sky-light)', color: 'var(--vp-sky)' }}>
                <Icono size={22} />
            </span>
            <span>
                <span className="block text-sm font-bold" style={{ color: 'var(--color-text)' }}>{titulo}</span>
                <span className="block text-xs" style={{ color: 'var(--color-text-muted)' }}>{ayuda}</span>
            </span>
        </button>
    );
}

/** Un renglón: lo que se escribió, la cantidad y el producto elegido (o a elegir). */
function FilaItem({ item, onCambio, deshabilitado, repetido, marcaPendiente }: {
    item: ItemLeido;
    onCambio: (c: Partial<ItemLeido>) => void;
    deshabilitado: boolean;
    /** Veces que este producto aparece en la venta (si son 2+, se suma). */
    repetido: number;
    /** Índice de la venta si este es su primer renglón pendiente (para "ir a lo que falta"). */
    marcaPendiente: number | null;
}) {
    const e = ESTILO[item.elegido && item.estado !== 'verde' ? 'verde' : item.estado];
    const [buscando, setBuscando] = useState(item.estado === 'rojo');

    if (item.quitado) {
        return (
            <li className="flex items-center justify-between gap-3 px-3 py-2 text-sm" style={{ borderTop: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                <span className="line-through">{item.texto}</span>
                {!deshabilitado && <button type="button" className="text-xs font-semibold underline" onClick={() => onCambio({ quitado: false })}>Volver a incluir</button>}
            </li>
        );
    }

    return (
        <li className="px-3 py-2.5 space-y-2 scroll-mt-4" style={{ borderTop: '1px solid var(--color-border)' }}
            data-visor-pendiente={marcaPendiente ?? undefined}>
            <div className="flex items-center gap-2.5">
                <span className="h-2.5 w-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: e.color }} title={e.texto} />
                <input type="number" inputMode="decimal" min="0" step="any" value={item.cantidad ?? ''} disabled={deshabilitado}
                    onChange={ev => onCambio({ cantidad: ev.target.value === '' ? null : Number(ev.target.value) })}
                    aria-label={`Cantidad de ${item.texto}`} placeholder="?"
                    className="w-16 h-8 rounded-lg border text-center text-sm font-bold tabular-nums"
                    style={{ borderColor: (item.cantidad ?? 0) > 0 ? 'var(--color-border)' : 'var(--vp-amber)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                <span className="min-w-0 flex-1 text-sm">
                    <span className="flex items-center gap-1.5 font-semibold" style={{ color: 'var(--color-text)' }}>
                        {item.elegido ? item.elegido.nombre : <span style={{ color: e.tinta }}>{e.texto}</span>}
                        {item.elegido && <span className="font-normal tabular-nums text-xs" style={{ color: 'var(--color-text-muted)' }}>{S(item.elegido.precio)} c/u</span>}
                        {item.aprendido && item.elegido?.producto_id === item.candidatos[0]?.producto_id && (
                            <span className="inline-flex items-center gap-0.5 text-[11px] font-semibold px-1.5 py-px rounded-full"
                                style={{ backgroundColor: 'var(--vp-sky-light)', color: 'var(--vp-navy)' }} title="Lo corregiste así en una venta anterior">
                                <Sparkles size={11} /> recordado
                            </span>
                        )}
                    </span>
                    <span className="block text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                        Escrito: “{item.texto}”{item.interpretacion && item.interpretacion !== item.texto ? ` · ¿${item.interpretacion}?` : ''}
                    </span>
                    {repetido > 1 && (
                        <span className="block text-xs font-semibold" style={{ color: 'var(--vp-navy)' }}>
                            Aparece {repetido} veces en esta venta: se cargará como una sola línea con la cantidad sumada.
                        </span>
                    )}
                </span>
                {!deshabilitado && (
                    <>
                        <button type="button" onClick={() => setBuscando(b => !b)} title="Buscar otro producto" aria-label="Buscar otro producto"
                            className="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>
                            <Search size={15} />
                        </button>
                        <button type="button" onClick={() => onCambio({ quitado: true })} title="Quitar este renglón" aria-label="Quitar este renglón"
                            className="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-red-50" style={{ color: 'var(--color-text-muted)' }}>
                            <Trash2 size={15} />
                        </button>
                    </>
                )}
            </div>

            {/* Dudoso: los productos más parecidos de su catálogo, a un toque. */}
            {!deshabilitado && item.candidatos.length > 0 && item.estado !== 'verde' && (
                <div className="flex flex-wrap gap-1.5 pl-[18px]" data-visor-elegir>
                    {item.candidatos.map(c => {
                        const activo = item.elegido?.producto_id === c.producto_id;
                        return (
                            <button key={c.producto_id} type="button" onClick={() => onCambio({ elegido: c })}
                                className="h-7 px-2.5 rounded-lg text-xs font-semibold transition-colors"
                                style={{
                                    border: `1px solid ${activo ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                    backgroundColor: activo ? 'color-mix(in srgb, var(--color-primary) 10%, var(--color-surface))' : 'var(--color-surface)',
                                    color: activo ? 'var(--color-primary)' : 'var(--color-text)',
                                }}>
                                {c.nombre} <span className="font-normal tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{S(c.precio)}</span>
                            </button>
                        );
                    })}
                </div>
            )}

            {!deshabilitado && buscando && (
                <BuscadorProducto inicial={item.interpretacion ?? item.texto}
                    onElegir={p => { onCambio({ elegido: p }); setBuscando(false); }} />
            )}
        </li>
    );
}

interface ProductoBuscado {
    id: number;
    nombre: string;
    unidades?: { es_base?: boolean; precio_venta: string; precio_costo?: string | null; activo?: boolean }[];
}

/** Busca en el catálogo con el mismo buscador del POS (para lo que salió en rojo). */
function BuscadorProducto({ inicial, onElegir }: { inicial: string; onElegir: (p: FichaProducto) => void }) {
    const [q, setQ] = useState(inicial.replace(/^[\d\s.,x*]+/i, ''));
    const [resultados, setResultados] = useState<ProductoBuscado[]>([]);

    useEffect(() => {
        if (q.trim().length < 2) { setResultados([]); return; }
        const t = setTimeout(() => {
            axios.get(route('pos.productos'), { params: { q } })
                .then(r => setResultados((r.data.productos ?? []).slice(0, 6)))
                .catch(() => setResultados([]));
        }, 300);
        return () => clearTimeout(t);
    }, [q]);

    // Precio y costo de la unidad base (el cuaderno cuenta unidades sueltas).
    const ficha = (p: ProductoBuscado): FichaProducto => {
        const u = p.unidades?.find(x => x.es_base) ?? p.unidades?.[0];
        return { producto_id: p.id, nombre: p.nombre, precio: Number(u?.precio_venta ?? 0), costo: Number(u?.precio_costo ?? 0) };
    };

    return (
        <div className="pl-[18px] space-y-1.5">
            <input value={q} onChange={e => setQ(e.target.value)} placeholder="Escribe el nombre del producto"
                aria-label="Buscar producto" autoFocus
                className="w-full h-9 rounded-lg border px-3 text-sm"
                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
            {resultados.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                    {resultados.map(p => (
                        <button key={p.id} type="button" onClick={() => onElegir(ficha(p))}
                            className="h-7 px-2.5 rounded-lg text-xs font-semibold hover:opacity-80"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}>
                            {p.nombre} <span className="font-normal tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{S(ficha(p).precio)}</span>
                        </button>
                    ))}
                </div>
            ) : q.trim().length >= 2 && (
                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin resultados. Prueba con otra parte del nombre.</p>
            )}
        </div>
    );
}
