/**
 * Ticket por plantilla: tipos de los bloques y el mismo reparto de líneas que
 * hace el agente de impresión (Printing/BloqueRenderer.cs de VentoryPrint), para
 * que la vista previa en pantalla coincida con el papel de 80 y de 58 mm.
 *
 * CONTRATO: Models/Bloque.cs del agente. Si cambia allá, cambia aquí.
 */

export type Tamano = 'normal' | 'alto' | 'ancho' | 'grande';
export type Alinear = 'izq' | 'centro' | 'der';

export interface LineaBloque { texto?: string; tamano?: Tamano; negrita?: boolean; alinear?: Alinear; separador?: boolean; }
export interface ParBloque { etiqueta?: string; valor?: string | number | null; negrita?: boolean; tamano?: Tamano; }
export interface ColumnaBloque { titulo?: string; alinear?: Alinear; flexible?: boolean; }

export interface Bloque {
    tipo: 'texto' | 'pares' | 'tabla' | 'recuadro' | 'banda' | 'linea' | 'espacio' | 'qr' | 'logo' | string;
    texto?: string;
    alinear?: Alinear;
    tamano?: Tamano;
    negrita?: boolean;
    invertido?: boolean;
    items?: ParBloque[];
    estilo?: string;
    columnas?: ColumnaBloque[];
    filas?: (string | number | null)[][];
    encabezado?: boolean;
    lineas?: LineaBloque[];
    modo?: 'imagen' | 'texto';
    borde?: string;
    caracter?: string;
    n?: number;
    datos?: string;
    escala?: number;
}

/** Columnas de texto del papel: 80 mm = 48, 58 mm = 32. */
export const COLUMNAS: Record<80 | 58, number> = { 80: 48, 58: 32 };

export const escala = (t?: Tamano): [number, number] =>
    t === 'alto' ? [1, 2] : t === 'ancho' ? [2, 1] : t === 'grande' ? [2, 2] : [1, 1];

/** Respeta palabras; una palabra más larga que el ancho se corta en trozos. */
export function partir(texto: string, ancho: number): string[] {
    const palabras: string[] = [];
    for (const p of texto.split(' ').filter(Boolean)) {
        if (p.length <= ancho) palabras.push(p);
        else for (let i = 0; i < p.length; i += ancho) palabras.push(p.slice(i, i + ancho));
    }
    const res: string[] = [];
    let actual = '';
    for (const p of palabras) {
        if (!actual) actual = p;
        else if (actual.length + 1 + p.length <= ancho) actual += ' ' + p;
        else { res.push(actual); actual = p; }
    }
    if (actual) res.push(actual);
    return res.length ? res : [''];
}

export const parrafos = (texto: string, ancho: number): string[] =>
    texto.replace(/\r/g, '').split('\n').flatMap(p => partir(p.trim(), ancho));

function conSangria(etiqueta: string, valor: string, col: number, ancho: number): string[] {
    const c = Math.max(col, etiqueta.length + 1);
    if (ancho - c < 8) return [`${etiqueta} ${valor}`];
    const partes = partir(valor, ancho - c);
    return [etiqueta.padEnd(c) + partes[0], ...partes.slice(1).map(p => ' '.repeat(c) + p)];
}

/** Etiqueta y valor en columnas (ver BloqueRenderer.EnColumna). */
export function enColumna(etiqueta: string, valor: string, col: number, ancho: number): string[] {
    if (!etiqueta) return partir(valor, ancho);
    const c = Math.max(col, etiqueta.length + 1);
    if (c + valor.length <= ancho) return [etiqueta.padEnd(c) + valor];

    const propia = etiqueta.length + 1;
    if (propia + valor.length <= ancho) return [`${etiqueta} ${valor}`];
    if (propia > ancho * 0.4) return [etiqueta, ...partir(valor, ancho)];
    return conSangria(etiqueta, valor, propia, ancho);
}

/** "TOTAL:        S/ 2,110.10"; si no cabe, el valor baja a su línea, a la derecha. */
export function extremos(etiqueta: string, valor: string, ancho: number): string[] {
    if (etiqueta.length + 1 + valor.length <= ancho) return [etiqueta.padEnd(ancho - valor.length) + valor];
    return [...partir(etiqueta, ancho), ...partir(valor, ancho).map(v => v.padStart(ancho))];
}

export interface LineaTexto { texto: string; negrita: boolean; }

function casillas(valores: string[], titulos: string[], ancho: number): string[] {
    const k = valores.length;
    if (!k) return [];
    const casilla = Math.floor(ancho / k);
    if (valores.every(v => v.length < casilla)) {
        return [valores.map((v, i) => v.padStart(i === k - 1 ? ancho - casilla * (k - 1) : casilla)).join('')];
    }
    return valores
        .map((v, i) => ({ v, t: titulos[i] }))
        .filter(x => x.v.length > 0)
        .flatMap(x => extremos(x.t && x.t !== x.v ? `  ${x.t}:` : '', x.v, ancho));
}

/** Reparto de columnas de una tabla (ver BloqueRenderer.LineasTabla). */
export function lineasTabla(bl: Bloque, ancho: number): LineaTexto[] {
    const cols = bl.columnas ?? [];
    if (!cols.length) return [];

    const filas = bl.filas ?? [];
    const n = cols.length;
    let flex = cols.findIndex(c => c.flexible);
    if (flex < 0) flex = 0;
    const encabezado = bl.encabezado ?? true;

    const cel = (f: (string | number | null)[], i: number) => String(f[i] ?? '').trim();
    const titulo = (i: number) => (cols[i].titulo ?? '').trim();
    const derecha = (i: number) => cols[i].alinear ? cols[i].alinear!.startsWith('d') : i !== flex;

    const anchos = cols.map((_, i) => Math.max(encabezado ? titulo(i).length : 0, ...filas.map(f => cel(f, i).length)));
    const fijos = cols.map((_, i) => i).filter(i => i !== flex);
    const anchoFlex = ancho - fijos.reduce((s, i) => s + anchos[i], 0) - (n - 1);

    const estilo = (bl.estilo ?? 'auto').trim().toLowerCase();
    const dosLineas = n > 1 && (
        estilo === 'doslineas' ? true
        : estilo === 'fila' ? anchoFlex < 4
        : anchoFlex < 12 || filas.some(f => partir(cel(f, flex), anchoFlex).length > 2)
    );

    const res: LineaTexto[] = [];
    if (!dosLineas) {
        const fila = (celda: (i: number) => string) => cols.map((_, i) => {
            const a = i === flex ? Math.max(1, anchoFlex) : anchos[i];
            const t = celda(i).slice(0, a);
            return derecha(i) ? t.padStart(a) : t.padEnd(a);
        }).join(' ').trimEnd();

        if (encabezado) {
            res.push({ texto: fila(titulo), negrita: true }, { texto: '-'.repeat(ancho), negrita: false });
        }
        for (const f of filas) {
            const partes = partir(cel(f, flex), Math.max(1, anchoFlex));
            res.push({ texto: fila(i => i === flex ? partes[0] : cel(f, i)), negrita: false });
            partes.slice(1).forEach(p => res.push({ texto: fila(i => i === flex ? p : ''), negrita: false }));
        }
        return res;
    }

    const titulos = fijos.map(titulo);
    if (encabezado) {
        if (titulo(flex)) res.push({ texto: titulo(flex), negrita: true });
        casillas(titulos, titulos, ancho).forEach(t => res.push({ texto: t, negrita: true }));
        res.push({ texto: '-'.repeat(ancho), negrita: false });
    }
    for (const f of filas) {
        partir(cel(f, flex), ancho).forEach(t => res.push({ texto: t, negrita: false }));
        const valores = fijos.map(i => cel(f, i));
        if (valores.some(v => v.length > 0)) casillas(valores, titulos, ancho).forEach(t => res.push({ texto: t, negrita: false }));
    }
    return res;
}
