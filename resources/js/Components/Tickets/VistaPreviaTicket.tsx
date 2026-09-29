import { QrCode, Image as ImageIcon } from 'lucide-react';
import {
    COLUMNAS, enColumna, escala, extremos, lineasTabla, parrafos,
    type Alinear, type Bloque, type LineaBloque, type Tamano,
} from '@/lib/ticketBloques';

/**
 * El ticket como saldrá en el papel. Usa el mismo reparto de líneas que el
 * agente de impresión, en letra de ancho fijo y con las columnas del papel
 * elegido, así lo que cabe aquí cabe en la ticketera.
 */
export default function VistaPreviaTicket({ bloques, papel = 80 }: { bloques: Bloque[]; papel?: 80 | 58 }) {
    return (
        <Papel papel={papel} etiqueta="Vista previa del ticket">
            {bloques.map((b, i) => <BloqueVista key={i} b={b} ancho={COLUMNAS[papel]} />)}
        </Papel>
    );
}

/** La tira de papel: letra de ancho fijo y exactamente las columnas de la ticketera. */
export function Papel({ papel, etiqueta, children }: { papel: 80 | 58; etiqueta: string; children: React.ReactNode }) {
    const ancho = COLUMNAS[papel];

    return (
        <div className="mx-auto bg-white text-black shadow-[0_10px_30px_-12px_rgb(15_23_42/0.45)]"
            style={{
                width: `calc(${ancho}ch + 28px)`, maxWidth: '100%', padding: '18px 14px 26px',
                fontFamily: 'Consolas, "Lucida Console", "Courier New", monospace', fontSize: papel === 80 ? 12.5 : 14, lineHeight: `${ALTO}em`,
                clipPath: CORTE,
            }}
            aria-label={etiqueta}>
            <div style={{ width: `${ancho}ch`, maxWidth: '100%', overflow: 'hidden' }}>{children}</div>
        </div>
    );
}

const ALTO = 1.32;

/** Borde inferior dentado, como el corte del papel. */
const CORTE = `polygon(0 0, 100% 0, ${Array.from({ length: 41 }, (_, i) =>
    `${100 - i * 2.5}% ${i % 2 ? '100%' : 'calc(100% - 6px)'}`).join(', ')})`;

function BloqueVista({ b, ancho }: { b: Bloque; ancho: number }) {
    switch (b.tipo) {
        case 'texto': {
            if (!b.texto?.trim()) return null;
            const [w] = escala(b.tamano);
            return <>{parrafos(b.texto, Math.max(1, Math.floor(ancho / w))).map((t, i) =>
                <Linea key={i} texto={t} tamano={b.tamano} negrita={b.negrita} alinear={b.alinear} invertido={b.invertido} />)}</>;
        }
        case 'pares': {
            const items = (b.items ?? []).filter(i => String(i.valor ?? '').trim() !== '');
            if (!items.length) return null;
            const col = Math.min(Math.max(...items.map(i => (i.etiqueta ?? '').length)) + 1, Math.max(4, Math.floor(ancho / 2)));
            return <>{items.flatMap((it, i) => {
                const tamano = it.tamano ?? b.tamano;
                const a = Math.max(1, Math.floor(ancho / escala(tamano)[0]));
                const etiqueta = (it.etiqueta ?? '').trim();
                const valor = String(it.valor).trim();
                const lineas = b.estilo === 'extremos' ? extremos(etiqueta, valor, a) : enColumna(etiqueta, valor, col, a);
                return lineas.map((t, j) => <Linea key={`${i}-${j}`} texto={t} tamano={tamano} negrita={it.negrita || b.negrita} />);
            })}</>;
        }
        case 'tabla':
            return <>{lineasTabla(b, ancho).map((l, i) => <Linea key={i} texto={l.texto} negrita={l.negrita} />)}</>;
        case 'linea':
            return <Linea texto={(b.caracter?.[0] ?? '-').repeat(ancho)} />;
        case 'espacio':
            return <div style={{ height: `${ALTO * Math.min(Math.max(b.n ?? 1, 1), 10)}em` }} />;
        case 'recuadro':
        case 'banda':
            return <Caja b={b} banda={b.tipo === 'banda'} />;
        case 'qr':
            return b.datos ? <div className="flex justify-center py-2"><QrCode size={84} strokeWidth={1.2} /></div> : null;
        case 'logo':
            return (
                <div className="flex justify-center pb-2">
                    <span className="flex items-center gap-1.5 px-5 py-3 text-[11px]" style={{ border: '1px dashed #9ca3af', color: '#6b7280', fontFamily: 'inherit' }}>
                        <ImageIcon size={14} /> Logo
                    </span>
                </div>
            );
        default:
            return null;   // un bloque que esta vista no conoce
    }
}

export function Linea({ texto, tamano, negrita, alinear = 'izq', invertido }: {
    texto: string; tamano?: Tamano; negrita?: boolean; alinear?: Alinear; invertido?: boolean;
}) {
    const [w, h] = escala(tamano);
    const origen = alinear === 'centro' ? 'center' : alinear === 'der' ? 'right' : 'left';
    return (
        <div style={{
            height: `${ALTO * h}em`, whiteSpace: 'pre', overflow: 'hidden',
            textAlign: alinear === 'centro' ? 'center' : alinear === 'der' ? 'right' : 'left',
            backgroundColor: invertido ? '#000' : undefined, color: invertido ? '#fff' : undefined,
        }}>
            <span style={{
                display: 'inline-block', fontWeight: negrita ? 700 : 400,
                transform: w > 1 || h > 1 ? `scale(${w}, ${h})` : undefined, transformOrigin: `${origen} top`,
            }}>
                {texto || ' '}
            </span>
        </div>
    );
}

/** Recuadro y banda: el agente los imprime como imagen, con estas proporciones. */
const LETRA: Record<Tamano, string> = { normal: '1.08em', alto: '1.5em', ancho: '1.5em', grande: '2em' };

function Caja({ b, banda }: { b: Bloque; banda: boolean }) {
    const lineas: LineaBloque[] = (b.lineas ?? []).filter(l => l.separador || l.texto?.trim());
    if (!lineas.length && b.texto?.trim()) lineas.push({ texto: b.texto, tamano: b.tamano, negrita: b.negrita });
    if (!lineas.length) return null;

    return (
        <div style={{
            margin: '2px 0', padding: banda ? '7px 10px' : '6px 10px',
            backgroundColor: banda ? '#000' : '#fff', color: banda ? '#fff' : '#000',
            border: banda ? undefined : '2px solid #000',
        }}>
            {lineas.map((l, i) => l.separador ? (
                <div key={i} style={{ borderTop: `1.5px dashed ${banda ? '#fff' : '#000'}`, margin: '5px 0' }} />
            ) : (
                <div key={i} style={{
                    fontSize: LETRA[l.tamano ?? 'normal'], lineHeight: 1.22, fontWeight: l.negrita ? 700 : 400,
                    textAlign: ({ izq: 'left', centro: 'center', der: 'right' } as const)[l.alinear ?? b.alinear ?? (banda ? 'centro' : 'izq')],
                    overflowWrap: 'anywhere',
                }}>
                    {l.texto}
                </div>
            ))}
        </div>
    );
}
