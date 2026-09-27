import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

/**
 * La confirmación de venta cobrada: pantalla verde, check que se dibuja,
 * «ding» y el vuelto bien grande. Como Apple Pay al pasar el iPhone o el
 * verde de PedidosYa: el cajero sabe sin leer nada que la venta entró.
 *
 * Es solo visual y sonido: no toca la venta ni el carrito. Se monta FUERA del
 * árbol de la página para sobrevivir a la redirección que hace Inertia al
 * guardar, y se retira sola (1,6 s; 3 s si hay vuelto que decir en voz alta)
 * o con cualquier toque o tecla: nunca frena la venta siguiente.
 *
 * Autónomo a propósito (estilos en línea, sin Tailwind): se puede copiar tal
 * cual a otro proyecto.
 */

const URL_SONIDO = '/sounds/venta-cobrada.mp3';
// El MP3 viene grabado bajo (pico ~0,25): x2,2 lo deja lleno sin saturar.
const VOLUMEN_SONIDO = 2.2;

// ── Sonido ───────────────────────────────────────────────────────────

let ctx: AudioContext | null = null;
let buffer: Promise<AudioBuffer | null> | null = null;
// Se descarga al cargar el POS: el primer cobro no espera la red.
const bytes: Promise<ArrayBuffer | null> = typeof window !== 'undefined'
    ? fetch(URL_SONIDO).then((r) => (r.ok ? r.arrayBuffer() : null)).catch(() => null)
    : Promise.resolve(null);

function contexto(): AudioContext | null {
    if (ctx) return ctx;
    const AC = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    ctx = AC ? new AC() : null;
    return ctx;
}

/** Plan B si el MP3 no carga: dos golpes de campanita que suben (Mi6 → Si6). */
function campanita(audio: AudioContext) {
    const parciales: [number, number, number][] = [[1, 1, 1.4], [2, 0.42, 0.7], [2.76, 0.2, 0.45], [5.4, 0.08, 0.18]];
    const golpe = (freq: number, t: number, vol: number) => {
        for (const [ratio, rel, decae] of parciales) {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.frequency.setValueAtTime(freq * ratio, t);
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(vol * rel, t + 0.004);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + decae);
            osc.connect(gain).connect(audio.destination);
            osc.start(t);
            osc.stop(t + decae + 0.05);
        }
    };
    const t = audio.currentTime;
    golpe(1318.5, t, 0.2);
    golpe(1975.5, t + 0.11, 0.24);
}

function sonar() {
    const audio = contexto();
    if (!audio) return;
    const tocar = async () => {
        if (!buffer) {
            buffer = bytes.then((b) => (b ? audio.decodeAudioData(b.slice(0)) : null)).catch(() => null);
        }
        const buf = await buffer;
        if (!buf) return campanita(audio);
        const src = audio.createBufferSource();
        src.buffer = buf;
        const gain = audio.createGain();
        gain.gain.value = VOLUMEN_SONIDO;
        src.connect(gain).connect(audio.destination);
        src.start();
    };
    if (audio.state === 'running') tocar();
    else audio.resume().then(tocar).catch(() => {});
}

// ── Pantalla ─────────────────────────────────────────────────────────

const ESTILOS = `
@keyframes gv-ok-fondo { from { clip-path: circle(0% at 50% 50%); } to { clip-path: circle(150% at 50% 50%); } }
@keyframes gv-ok-disco { 0% { transform: scale(.4); opacity: 0; } 60% { transform: scale(1.08); opacity: 1; } 100% { transform: scale(1); } }
@keyframes gv-ok-trazo { to { stroke-dashoffset: 0; } }
@keyframes gv-ok-onda { from { transform: scale(1); opacity: .55; } to { transform: scale(2.1); opacity: 0; } }
@keyframes gv-ok-sube { from { transform: translateY(14px); opacity: 0; } to { transform: none; opacity: 1; } }
@keyframes gv-ok-sale { to { opacity: 0; transform: scale(1.03); } }
.gv-ok { position: fixed; inset: 0; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 0 24px; text-align: center; cursor: pointer; color: #fff; font-family: inherit;
  background: radial-gradient(circle at 50% 42%, #22c55e 0%, #16a34a 55%, #15803d 100%);
  animation: gv-ok-fondo .45s cubic-bezier(.2,.8,.2,1) both; }
.gv-ok.gv-saliendo { animation: gv-ok-sale .28s ease-in both; }
.gv-ok-circulo { position: relative; }
.gv-ok-onda { position: absolute; inset: 0; border-radius: 9999px; background: rgba(255,255,255,.6); animation: gv-ok-onda .9s .45s ease-out both; }
.gv-ok-disco { position: relative; display: flex; align-items: center; justify-content: center; width: 128px; height: 128px;
  border-radius: 9999px; background: #fff; box-shadow: 0 25px 50px -12px rgba(20,83,45,.35);
  animation: gv-ok-disco .5s .12s cubic-bezier(.22,1,.36,1) both; }
.gv-ok-trazo { stroke-dasharray: 60; stroke-dashoffset: 60; animation: gv-ok-trazo .38s .38s cubic-bezier(.65,0,.35,1) forwards; }
.gv-ok-texto { margin-top: 32px; animation: gv-ok-sube .4s .5s cubic-bezier(.2,.8,.2,1) both; }
.gv-ok-titulo { font-size: 34px; font-weight: 900; letter-spacing: -.02em; line-height: 1.1; }
.gv-ok-total { margin-top: 4px; font-size: 20px; font-weight: 600; color: #f0fdf4; font-variant-numeric: tabular-nums; }
.gv-ok-vuelto { margin-top: 32px; padding: 20px 40px; border-radius: 24px; background: #fff; box-shadow: 0 25px 50px -12px rgba(20,83,45,.35);
  animation: gv-ok-sube .4s .65s cubic-bezier(.2,.8,.2,1) both; }
.gv-ok-vuelto small { display: block; font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #15803d; }
.gv-ok-vuelto b { display: block; font-size: 60px; font-weight: 900; line-height: 1; color: #166534; font-variant-numeric: tabular-nums; }
.gv-ok-pie { position: absolute; bottom: 32px; font-size: 12px; font-weight: 500; color: rgba(220,252,231,.8); animation: gv-ok-sube .4s .5s both; }
@media (prefers-reduced-motion: reduce) {
  .gv-ok, .gv-ok-disco, .gv-ok-onda, .gv-ok-texto, .gv-ok-vuelto, .gv-ok-pie { animation: none; }
  .gv-ok-onda { display: none; }
  .gv-ok-trazo { animation: none; stroke-dashoffset: 0; }
}
`;

const soles = (n: number) => `S/ ${Number(n || 0).toFixed(2)}`;

interface Props {
    total: number;
    vuelto: number | null;
    titulo: string;
    alTerminar: () => void;
}

function PantallaExito({ total, vuelto, titulo, alTerminar }: Props) {
    const [saliendo, setSaliendo] = useState(false);
    const hayVuelto = vuelto !== null && vuelto > 0.004;

    useEffect(() => {
        const cerrar = () => setSaliendo(true);
        const t = setTimeout(cerrar, hayVuelto ? 3000 : 1600);
        window.addEventListener('keydown', cerrar);
        return () => { clearTimeout(t); window.removeEventListener('keydown', cerrar); };
    }, [hayVuelto]);

    return (
        <div
            role="status"
            aria-live="assertive"
            onPointerDown={() => setSaliendo(true)}
            onAnimationEnd={(e) => { if (saliendo && e.target === e.currentTarget) alTerminar(); }}
            className={`gv-ok${saliendo ? ' gv-saliendo' : ''}`}
        >
            <div className="gv-ok-circulo">
                <span className="gv-ok-onda" />
                <span className="gv-ok-disco">
                    <svg viewBox="0 0 52 52" width="80" height="80" aria-hidden="true">
                        <path className="gv-ok-trazo" d="M14 27 l8 8 l16 -18" fill="none" stroke="#16a34a"
                            strokeWidth="5.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </span>
            </div>

            <div className="gv-ok-texto">
                <div className="gv-ok-titulo">{titulo}</div>
                {total > 0 && <div className="gv-ok-total">{soles(total)}</div>}
            </div>

            {hayVuelto && (
                <div className="gv-ok-vuelto">
                    <small>Vuelto</small>
                    <b>{soles(vuelto as number)}</b>
                </div>
            )}

            <div className="gv-ok-pie">Toca para continuar</div>
        </div>
    );
}

let estilosPuestos = false;

/** Lanza la celebración. `vuelto` solo se muestra si hay que dar cambio. */
export function celebrarVenta({ total = 0, vuelto = null, titulo = '¡Venta registrada!' }: {
    total?: number; vuelto?: number | null; titulo?: string;
} = {}) {
    if (typeof document === 'undefined') return;

    if (!estilosPuestos) {
        const style = document.createElement('style');
        style.textContent = ESTILOS;
        document.head.appendChild(style);
        estilosPuestos = true;
    }

    const contenedor = document.createElement('div');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    const retirar = () => { root.unmount(); contenedor.remove(); };

    root.render(<PantallaExito total={total} vuelto={vuelto} titulo={titulo} alTerminar={retirar} />);

    sonar();
    try { navigator.vibrate?.([18, 40, 28]); } catch { /* sin vibración: da igual */ }
}
