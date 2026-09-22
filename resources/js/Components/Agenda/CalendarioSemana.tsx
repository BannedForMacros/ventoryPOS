import { useEffect, useMemo, useRef } from 'react';
import { Clock, User as UserIcon } from 'lucide-react';

/**
 * Calendario semanal, al estilo de una agenda de toda la vida: los días en
 * columnas, las horas en filas, y cada cita como un bloque colocado donde
 * empieza y tan alto como dura.
 *
 * POR QUÉ ASÍ Y NO UNA LISTA: la pregunta que se hace quien maneja una agenda
 * no es "qué citas hay" sino "dónde tengo hueco" y "quién está saturado". Eso
 * una lista no lo contesta: hay que leerla entera y reconstruir el día en la
 * cabeza. En una rejilla, el hueco se ve porque está vacío.
 *
 * DOS DECISIONES QUE SE NOTAN:
 *
 *  · El rango de horas NO es fijo de 00:00 a 24:00. Se calcula desde las citas
 *    de la semana, con un margen. Un día de 24 horas deja el trabajo real
 *    apretado en una franja diminuta y obliga a hacer scroll por horas vacías.
 *
 *  · Los solapes se reparten el ancho en vez de taparse. Dos profesionales
 *    atendiendo a la misma hora es lo normal en este negocio, y una cita
 *    escondida detrás de otra es una cita que no existe para quien mira.
 */

export interface CitaCal {
    id: number;
    numero: string;
    fecha_hora: string;
    duracion_min: number;
    estado: string;
    cliente: { nombres: string | null; apellidos: string | null; razon_social: string | null };
    profesional: { id: number; name: string } | null;
}

interface Props {
    citas: CitaCal[];
    /** Lunes de la semana mostrada, en YYYY-MM-DD. */
    inicioSemana: string;
    colores: Record<string, { bg: string; text: string; border: string }>;
    onAbrir: (id: number) => void;
}

/** Alto de una hora, en píxeles. Debajo de ~44 px no cabe el texto de media hora. */
const PX_HORA = 56;
const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

const hhmm = (d: Date) =>
    `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

function nombreCorto(c: CitaCal['cliente']): string {
    if (c.razon_social) return c.razon_social;
    return [c.nombres, c.apellidos].filter(Boolean).join(' ') || 'Cliente';
}

/**
 * Reparte en columnas las citas que se pisan.
 *
 * Recorre el día en orden y arrastra un grupo mientras las citas se solapen
 * entre sí; al cerrarse el grupo, todas sus citas se reparten el ancho a
 * partes iguales. Es lo que hace que dos citas a la misma hora se vean
 * LAS DOS, una al lado de la otra, en vez de una encima de la otra.
 */
function repartir(citas: Array<CitaCal & { ini: number; fin: number }>) {
    const orden = [...citas].sort((a, b) => a.ini - b.ini || a.fin - b.fin);
    const salida: Array<CitaCal & { ini: number; fin: number; col: number; cols: number }> = [];

    let grupo: Array<CitaCal & { ini: number; fin: number; col: number }> = [];
    let finGrupo = -1;

    const cerrar = () => {
        const cols = grupo.length === 0 ? 1 : Math.max(...grupo.map(g => g.col)) + 1;
        grupo.forEach(g => salida.push({ ...g, cols }));
        grupo = [];
        finGrupo = -1;
    };

    for (const cita of orden) {
        // Arranca grupo nuevo en cuanto esta cita ya no pisa a ninguna anterior.
        if (cita.ini >= finGrupo) cerrar();

        // La primera columna libre: las que ya terminaron dejan su sitio.
        const ocupadas = new Set(grupo.filter(g => g.fin > cita.ini).map(g => g.col));
        let col = 0;
        while (ocupadas.has(col)) col++;

        grupo.push({ ...cita, col });
        finGrupo = Math.max(finGrupo, cita.fin);
    }
    cerrar();

    return salida;
}

export default function CalendarioSemana({ citas, inicioSemana, colores, onAbrir }: Props) {
    const scrollRef = useRef<HTMLDivElement>(null);

    const lunes = useMemo(() => new Date(inicioSemana + 'T00:00:00'), [inicioSemana]);
    const hoyISO = useMemo(() => {
        const h = new Date();
        return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`;
    }, []);

    const dias = useMemo(() => Array.from({ length: 7 }, (_, i) => {
        const d = new Date(lunes);
        d.setDate(lunes.getDate() + i);
        const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        return { fecha: d, iso, esHoy: iso === hoyISO };
    }), [lunes, hoyISO]);

    // Rango de horas: lo que ocupan las citas, con una hora de aire a cada lado.
    // Sin citas cae a 8–20, que es una jornada creíble y no una pantalla vacía.
    const { horaIni, horaFin } = useMemo(() => {
        if (citas.length === 0) return { horaIni: 8, horaFin: 20 };
        let min = 24, max = 0;
        for (const c of citas) {
            const d = new Date(c.fecha_hora);
            const finMin = d.getHours() * 60 + d.getMinutes() + c.duracion_min;
            min = Math.min(min, d.getHours());
            max = Math.max(max, Math.ceil(finMin / 60));
        }
        return { horaIni: Math.max(0, min - 1), horaFin: Math.min(24, Math.max(max + 1, min + 4)) };
    }, [citas]);

    const horas = useMemo(
        () => Array.from({ length: horaFin - horaIni }, (_, i) => horaIni + i),
        [horaIni, horaFin],
    );
    const altoTotal = horas.length * PX_HORA;

    // Citas por día, ya repartidas en columnas.
    const porDia = useMemo(() => {
        const mapa: Record<string, ReturnType<typeof repartir>> = {};
        for (const d of dias) {
            const delDia = citas
                .filter(c => c.fecha_hora.slice(0, 10) === d.iso)
                .map(c => {
                    const f = new Date(c.fecha_hora);
                    const ini = f.getHours() * 60 + f.getMinutes();
                    return { ...c, ini, fin: ini + Math.max(15, c.duracion_min) };
                });
            mapa[d.iso] = repartir(delDia);
        }
        return mapa;
    }, [citas, dias]);

    // La línea de "ahora", solo si hoy cae dentro de la semana y del rango visible.
    const ahora = new Date();
    const ahoraMin = ahora.getHours() * 60 + ahora.getMinutes();
    const mostrarAhora = dias.some(d => d.esHoy) && ahoraMin >= horaIni * 60 && ahoraMin <= horaFin * 60;

    // Al abrir, la vista arranca donde empieza el trabajo, no en una franja vacía.
    useEffect(() => {
        if (!scrollRef.current) return;
        const objetivo = mostrarAhora ? ahoraMin : horaIni * 60 + 60;
        scrollRef.current.scrollTop = Math.max(0, ((objetivo - horaIni * 60) / 60) * PX_HORA - PX_HORA);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [inicioSemana, horaIni]);

    const posicion = (min: number) => ((min - horaIni * 60) / 60) * PX_HORA;

    return (
        <div className="rounded-2xl border overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
            {/* La rejilla es ancha por naturaleza: en móvil se desplaza en
                horizontal en vez de espachurrar siete columnas ilegibles. */}
            <div className="overflow-x-auto">
                <div style={{ minWidth: 720 }}>
                    {/* Cabecera de días: se queda fija al hacer scroll vertical */}
                    <div className="grid sticky top-0 z-20"
                        style={{
                            gridTemplateColumns: '56px repeat(7, minmax(0, 1fr))',
                            backgroundColor: 'var(--color-surface)',
                            borderBottom: '1px solid var(--color-border)',
                        }}>
                        <div />
                        {dias.map((d, i) => (
                            <div key={d.iso} className="px-2 py-2 text-center"
                                style={{ borderLeft: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                <div className="text-[10px] font-bold uppercase tracking-wider"
                                    style={{ color: d.esHoy ? 'var(--color-primary)' : 'var(--color-text-muted)' }}>
                                    {DIAS[i]}
                                </div>
                                <div className="mt-0.5 inline-flex items-center justify-center rounded-full text-sm font-bold"
                                    style={{
                                        width: 26, height: 26,
                                        backgroundColor: d.esHoy ? 'var(--color-primary)' : 'transparent',
                                        color: d.esHoy ? '#fff' : 'var(--color-text)',
                                    }}>
                                    {d.fecha.getDate()}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Rejilla de horas + bloques */}
                    <div ref={scrollRef} className="overflow-y-auto" style={{ maxHeight: '65vh' }}>
                        <div className="grid relative"
                            style={{ gridTemplateColumns: '56px repeat(7, minmax(0, 1fr))', height: altoTotal }}>
                            {/* Columna de horas */}
                            <div className="relative">
                                {horas.map((h, i) => (
                                    <div key={h} className="absolute right-2 text-[10px] font-medium"
                                        style={{ top: i * PX_HORA - 6, color: 'var(--color-text-muted)' }}>
                                        {String(h).padStart(2, '0')}:00
                                    </div>
                                ))}
                            </div>

                            {dias.map(d => (
                                <div key={d.iso} className="relative"
                                    style={{
                                        borderLeft: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)',
                                        backgroundColor: d.esHoy
                                            ? 'color-mix(in srgb, var(--color-primary) 4%, transparent)'
                                            : 'transparent',
                                    }}>
                                    {/* Líneas de hora: la referencia visual que convierte
                                        una columna en una regla de tiempo. */}
                                    {horas.map((h, i) => (
                                        <div key={h} className="absolute left-0 right-0"
                                            style={{
                                                top: i * PX_HORA,
                                                borderTop: '1px solid color-mix(in srgb, var(--color-border) 45%, transparent)',
                                            }} />
                                    ))}

                                    {d.esHoy && mostrarAhora && (
                                        <div className="absolute left-0 right-0 z-10 pointer-events-none"
                                            style={{ top: posicion(ahoraMin) }}>
                                            <div style={{ borderTop: '2px solid var(--color-danger)' }} />
                                            <div className="rounded-full"
                                                style={{
                                                    width: 7, height: 7, marginTop: -4.5, marginLeft: -3.5,
                                                    backgroundColor: 'var(--color-danger)',
                                                }} />
                                        </div>
                                    )}

                                    {porDia[d.iso]?.map(c => {
                                        const col = colores[c.estado] ?? colores.programada;
                                        const alto = Math.max(22, ((c.fin - c.ini) / 60) * PX_HORA - 2);
                                        const ancho = 100 / c.cols;
                                        const cancelada = c.estado === 'cancelada' || c.estado === 'no_asistio';

                                        return (
                                            <button
                                                key={c.id}
                                                onClick={() => onAbrir(c.id)}
                                                title={`${hhmm(new Date(c.fecha_hora))} · ${nombreCorto(c.cliente)}${c.profesional ? ` · ${c.profesional.name}` : ''}`}
                                                className="absolute rounded-md px-1.5 py-1 text-left overflow-hidden transition-shadow hover:shadow-md hover:z-10"
                                                style={{
                                                    top: posicion(c.ini) + 1,
                                                    height: alto,
                                                    left: `calc(${c.col * ancho}% + 2px)`,
                                                    width: `calc(${ancho}% - 4px)`,
                                                    backgroundColor: col.bg,
                                                    // El estado ya se lee en el fondo y en el texto.
                                                    // El borde solo recorta el bloque contra las
                                                    // líneas de la rejilla; repetir el color en una
                                                    // pestaña lateral gruesa no añadía información.
                                                    border: `1px solid ${col.border}`,
                                                    color: col.text,
                                                    // Lo que ya no va a pasar no compite por la atención
                                                    // con lo que sí: se ve, pero apagado.
                                                    opacity: cancelada ? 0.55 : 1,
                                                    textDecoration: cancelada ? 'line-through' : undefined,
                                                }}
                                            >
                                                {/* Una cita de 30 minutos mide 26 px: dos líneas no
                                                    caben y el nombre salía cortado por la mitad. Ahí
                                                    la hora y el nombre van en la MISMA línea, como en
                                                    cualquier agenda de verdad. */}
                                                {alto < 34 ? (
                                                    <div className="text-[10px] leading-tight truncate">
                                                        <span className="font-bold">{hhmm(new Date(c.fecha_hora))}</span>
                                                        {' '}{nombreCorto(c.cliente)}
                                                    </div>
                                                ) : (
                                                    <>
                                                        <div className="text-[10px] font-bold leading-tight">
                                                            {hhmm(new Date(c.fecha_hora))}
                                                        </div>
                                                        <div className="text-[11px] font-semibold leading-tight truncate">
                                                            {nombreCorto(c.cliente)}
                                                        </div>
                                                        {/* El profesional solo cabe si el bloque es alto:
                                                            meterlo siempre produce texto cortado. */}
                                                        {alto >= 54 && c.profesional && (
                                                            <div className="text-[10px] leading-tight truncate flex items-center gap-0.5 opacity-80">
                                                                <UserIcon size={9} />{c.profesional.name}
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {citas.length === 0 && (
                <div className="py-6 text-center text-sm flex items-center justify-center gap-2"
                    style={{ color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)' }}>
                    <Clock size={14} className="opacity-50" />
                    No hay citas esta semana
                </div>
            )}
        </div>
    );
}
