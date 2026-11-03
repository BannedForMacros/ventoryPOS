import { useEffect, useState, type ReactNode } from 'react';
import axios from 'axios';
import { AlertTriangle, Calculator, Gauge } from 'lucide-react';
import Checkbox from '@/Components/UI/Checkbox';
import { soles } from '@/lib/dinero';

/**
 * Revisión del km y del combustible mientras se escribe el gasto, para que un
 * número en la casilla equivocada (el monto en el km, los galones en el monto,
 * un cero de más) se note ANTES de guardar.
 *
 * - Bloquea lo imposible (las mismas reglas que FlotaService): km menor a la
 *   última lectura, mayor a una posterior, precio por galón absurdo.
 * - Avisa lo raro y pide confirmarlo: un salto de km enorme, un precio por
 *   galón lejos del habitual, el km igual al monto, demasiados galones.
 */

export interface LecturaKm { km: number; fecha: string | null; origen: 'carga' | 'mantenimiento' | 'ficha' }
/** Último tanque lleno antes de esta carga y lo cargado en parciales desde entonces. */
export interface UltimoLleno { km: number; fecha: string; galones_despues: number; monto_despues: number }
export interface ReferenciaKm { previo: LecturaKm | null; siguiente: LecturaKm | null; precio_galon: number | null; lleno: UltimoLleno | null }

/** Mismos topes que FlotaService::PRECIO_GALON_MIN / MAX. */
const PRECIO_MIN = 2;
const PRECIO_MAX = 100;
/** Sin historial, lo normal en Perú (GLP ≈ S/ 8, diésel ≈ S/ 16–20). */
const PRECIO_HABITUAL = { min: 7, max: 30 };
/** Un camión no pasa de esto por día; con menos de 3 000 km no se avisa. */
const KM_POR_DIA = 1200;
const KM_SALTO_MINIMO = 3000;
const GALONES_RAROS = 300;
/** Ni un auto chico pasa de esto; un camión anda por 8–15 km/gal. */
const KM_POR_GALON_MAX = 60;

const km = (n: number) => `${Math.round(n).toLocaleString('en-US')} km`;
const ddmm = (f: string | null) => (f ? `${f.slice(8, 10)}/${f.slice(5, 7)}` : '');
const ORIGEN = { carga: 'carga', mantenimiento: 'mantenimiento', ficha: 'ficha del vehículo' };

export function describirLectura(l: LecturaKm): string {
    return `${km(l.km)} (${ORIGEN[l.origen]}${l.fecha ? ` del ${ddmm(l.fecha)}` : ''})`;
}

/** Trae la referencia del vehículo para esa fecha; null mientras no hay placa o fecha. */
export function useReferenciaKm(vehiculoId: number | '', fecha: string, gastoId: number | null, activo: boolean): ReferenciaKm | null {
    const [ref, setRef] = useState<ReferenciaKm | null>(null);

    useEffect(() => {
        setRef(null);
        if (!activo || !vehiculoId || !fecha) return;
        const ctrl = new AbortController();
        const t = setTimeout(() => {
            axios.get(route('gastos.km-referencia'), { params: { vehiculo_id: vehiculoId, fecha, gasto_id: gastoId ?? undefined }, signal: ctrl.signal })
                .then(r => setRef(r.data))
                // Sin referencia se sigue pudiendo guardar: el servidor vuelve a revisar.
                .catch(() => {});
        }, 250);
        return () => { clearTimeout(t); ctrl.abort(); };
    }, [vehiculoId, fecha, gastoId, activo]);

    return ref;
}

export interface Revision {
    /** Bloquea guardar, en la casilla indicada. */
    error: { campo: string; texto: string } | null;
    /** Cosas raras que hay que confirmar. */
    avisos: string[];
    /** Lo que ayuda a escribir bien: "Recorrió 420 km", "S/ 16.40 por galón". */
    notas: string[];
}

export function revisarLectura(opts: {
    ref: ReferenciaKm | null; campoKm: string; kmTexto: string; fecha: string;
    monto?: string; galones?: string;
}): Revision {
    const { ref, campoKm, kmTexto, fecha } = opts;
    const r: Revision = { error: null, avisos: [], notas: [] };
    const valor = kmTexto.trim() === '' ? null : Number(kmTexto);

    if (valor !== null && Number.isFinite(valor) && ref) {
        if (ref.previo && valor < ref.previo.km) {
            r.error = { campo: campoKm, texto: `Es menor al último km registrado: ${describirLectura(ref.previo)}. Revisa el odómetro.` };
        } else if (ref.siguiente && valor > ref.siguiente.km) {
            r.error = { campo: campoKm, texto: `Es mayor al de un registro posterior: ${describirLectura(ref.siguiente)}.` };
        } else if (ref.previo) {
            const recorrido = valor - ref.previo.km;
            r.notas.push(`Recorrió ${km(recorrido)} desde la última lectura`);
            const dias = ref.previo.fecha
                ? Math.max(1, Math.round((Date.parse(fecha) - Date.parse(ref.previo.fecha)) / 86_400_000))
                : 1;
            if (recorrido > Math.max(KM_SALTO_MINIMO, KM_POR_DIA * dias)) {
                r.avisos.push(`Son ${km(recorrido)} más que la última lectura (${describirLectura(ref.previo)}). ¿Un dígito de más?`);
            }
        }
    }

    const monto = Number(opts.monto);
    const galones = Number(opts.galones);
    if (opts.galones !== undefined && galones > 0 && monto > 0) {
        const precio = monto / galones;
        if (precio < PRECIO_MIN || precio > PRECIO_MAX) {
            r.error ??= { campo: 'combustible.galones', texto: `${soles(precio)} por galón no cuadra: ¿el monto y los galones están cruzados?` };
        } else {
            r.notas.push(`${soles(precio)} por galón`);
            const habitual = ref?.precio_galon;
            const fuera = habitual
                ? precio < habitual * 0.65 || precio > habitual * 1.5
                : precio < PRECIO_HABITUAL.min || precio > PRECIO_HABITUAL.max;
            if (fuera) {
                r.avisos.push(habitual
                    ? `${soles(precio)} por galón, y este vehículo suele cargar a ${soles(habitual)}. Revisa el monto y los galones.`
                    : `${soles(precio)} por galón es raro. Revisa el monto y los galones.`);
            }
        }
        if (galones > GALONES_RAROS) r.avisos.push(`${galones.toLocaleString('en-US')} galones es mucho para una carga.`);
        // Rendimiento aproximado desde la carga anterior: un camión no hace 4,000 km por galón.
        if (!r.error && valor !== null && ref?.previo?.origen === 'carga' && valor > ref.previo.km) {
            const rinde = (valor - ref.previo.km) / galones;
            if (rinde > KM_POR_GALON_MAX) {
                r.avisos.push(`Saldría ${Math.round(rinde).toLocaleString('en-US')} km por galón, y eso no es posible. Revisa el km y los galones.`);
            }
        }
    }
    if (valor !== null && monto > 0 && Math.abs(valor - monto) < 0.01) {
        r.avisos.push('El km es igual al monto: ¿lo escribiste en la casilla equivocada?');
    }

    return r;
}

/** Línea gris bajo las casillas: la última lectura y lo calculado. */
export function PistaKm({ referencia: ref, notas }: { referencia: ReferenciaKm | null; notas: string[] }) {
    if (!ref?.previo && notas.length === 0) return null;
    return (
        <p className="text-[13px] flex items-start gap-1.5 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
            <Gauge size={14} className="mt-0.5 flex-shrink-0" />
            <span>
                {ref?.previo ? <>Última lectura: <b style={{ color: 'var(--color-text)' }}>{describirLectura(ref.previo)}</b></> : 'Primera lectura de este vehículo'}
                {notas.length > 0 && <> · {notas.join(' · ')}</>}
            </span>
        </p>
    );
}

/** Caja ámbar con lo raro y la casilla para confirmar que está bien. */
export function AvisosLectura({ avisos, confirmado, onConfirmar }: { avisos: string[]; confirmado: boolean; onConfirmar: (v: boolean) => void }) {
    if (avisos.length === 0) return null;
    return (
        <div data-campo="flota.confirmar" className="rounded-lg p-3 space-y-2 vp-fade-up"
            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 12%, var(--color-surface))', border: '1px solid color-mix(in srgb, var(--vp-amber) 45%, transparent)' }}>
            <ul className="space-y-1">
                {avisos.map(a => (
                    <li key={a} className="text-[13px] flex items-start gap-1.5" style={{ color: 'var(--vp-amber-ink)' }}>
                        <AlertTriangle size={14} className="mt-0.5 flex-shrink-0" /> {a}
                    </li>
                ))}
            </ul>
            <Checkbox label="Revisé los datos y están bien" checked={confirmado} onChange={e => onConfirmar(e.target.checked)} />
        </div>
    );
}

/**
 * Cuánto rinde, con la cuenta a la vista. Igual que el historial
 * (FlotaService::rendimiento): de un tanque lleno al siguiente, km recorridos
 * ÷ galones echados en ese tramo, y soles pagados ÷ km recorridos.
 */
export function CalculoRendimiento({ referencia: ref, kmTexto, galonesTexto, montoTexto, tanqueLleno }: {
    referencia: ReferenciaKm | null; kmTexto: string; galonesTexto: string; montoTexto: string; tanqueLleno: boolean;
}) {
    const kmNum = Number(kmTexto);
    const gal = Number(galonesTexto);
    const monto = Number(montoTexto);
    const lleno = ref?.lleno ?? null;
    const listo = kmTexto.trim() !== '' && gal > 0 && monto > 0;

    let cuerpo: ReactNode;
    if (!tanqueLleno) {
        cuerpo = <>Carga parcial: no alcanza para medir. Sus galones y su monto se suman a la <b>próxima vez que llenen el tanque</b>, y ahí sale el rendimiento.</>;
    } else if (!ref) {
        cuerpo = <>Elige el vehículo y escribe el km para ver cuánto rinde.</>;
    } else if (!lleno) {
        cuerpo = <>Es la <b>primera llenada</b> registrada: desde aquí se empieza a medir. En la próxima llenada verás cuántos km rinde por galón y cuánto cuesta cada km.</>;
    } else if (!listo || kmNum <= lleno.km) {
        cuerpo = <>Se mide desde la última llenada ({km(lleno.km)} el {ddmm(lleno.fecha)}). Escribe km, galones y monto y aquí sale la cuenta.</>;
    } else {
        const tramo = kmNum - lleno.km;
        const galTramo = gal + lleno.galones_despues;
        const montoTramo = monto + lleno.monto_despues;
        const parciales = lleno.galones_despues > 0;
        cuerpo = (
            <span className="block space-y-1 tabular-nums">
                <span className="block">
                    Recorrió <b>{km(tramo)}</b> desde la última llenada ({km(lleno.km)} el {ddmm(lleno.fecha)}).
                </span>
                <span className="block">
                    <b>Rinde:</b> {km(tramo)} ÷ {fmtGal(galTramo)} gal{parciales ? ` (estos ${fmtGal(gal)} + ${fmtGal(lleno.galones_despues)} de cargas parciales)` : ''} = <b style={{ color: 'var(--vp-navy)' }}>{(tramo / galTramo).toFixed(1)} km por galón</b>
                </span>
                <span className="block">
                    <b>Cuesta:</b> {soles(montoTramo)} ÷ {km(tramo)} = <b style={{ color: 'var(--vp-navy)' }}>{soles(montoTramo / tramo)} por km</b>
                </span>
            </span>
        );
    }

    return (
        <div className="rounded-lg p-3 text-[13px] flex items-start gap-2"
            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 5%, var(--color-surface))', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
            <Calculator size={15} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--vp-navy)' }} />
            <span className="min-w-0">{cuerpo}</span>
        </div>
    );
}

const fmtGal = (n: number) => Number(n.toFixed(3)).toLocaleString('en-US');
