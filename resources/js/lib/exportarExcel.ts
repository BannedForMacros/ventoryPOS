import * as XLSX from 'xlsx';

/**
 * Exportación REAL a .xlsx (hoja de cálculo estructurada) en el navegador.
 * Reemplaza a los CSV "disfrazados" de Excel: al abrir en Excel (cualquier
 * configuración regional) cada campo cae en su propia columna — texto, montos
 * y usuarios — sin depender del delimitador del sistema.
 *
 * La fila 0 es la cabecera. Los montos deben ir como NÚMEROS para que Excel
 * permita sumar; los textos con ceros a la izquierda (códigos, correlativos)
 * deben seguir como strings para no perder el formato.
 */

export type CeldaExcel = string | number | boolean | null | undefined;

function anchoDe(v: unknown): number {
    const s = v === null || v === undefined ? '' : String(v);
    const simple = s.replace(/[\u0300-\u036f]/g, '').length;
    const ancho = Array.from(s).reduce((acc, ch) => {
        if (ch === '\t' || ch === '\n') return acc;
        return acc + (ch.codePointAt(0)! > 255 ? 2 : 1);
    }, 0);
    return Math.max(simple, ancho);
}

/**
 * Normaliza un valor para la celda:
 *  - números JS → número (sumables en Excel)
 *  - texto tipo monto "S/ 1,234.56" / "1,234.56" → número (mantiene la coma
 *    de miles, por eso es inequívocamente dinero y no un código)
 *  - el resto (códigos "0001", fechas, textos) se mantiene como texto
 */
export function valorCelda(v: unknown): CeldaExcel {
    if (v === null || v === undefined) return '';
    if (typeof v === 'number') return Number.isFinite(v) ? v : String(v);
    if (typeof v === 'boolean') return v;
    if (typeof v === 'string') {
        const t = v.trim();
        if (t === '') return '';
        const m = t.match(/^-?\s*(?:S\/\s*)?([\d,]+(?:\.\d{1,2})?)$/);
        if (m && (m[1].includes(',') || (m[1].includes('.') && m[1].split('.')[1].length === 2))) {
            const n = parseFloat(m[1].replace(/,/g, ''));
            if (!Number.isNaN(n)) return n;
        }
        return v;
    }
    if (typeof v === 'object') {
        try { return JSON.stringify(v); } catch { return String(v); }
    }
    return String(v);
}

export interface DescargarExcelOptions {
    hoja?: string;
    /** Índices (0-based) de columnas que son montos → formato #,##0.00. */
    moneyCols?: number[];
}

/**
 * Descarga un libro .xlsx con anchos de columna automáticos. `aoa` incluye la
 * fila de cabecera (fila 0). Si falta cabecera usa 'Fila 1', 'Fila 2'...
 */
export function descargarExcel(nombre: string, aoa: CeldaExcel[][], opts: DescargarExcelOptions = {}): void {
    const filas = aoa.filter(fila => fila.some(c => c !== '' && c !== null && c !== undefined));
    if (filas.length === 0) return;

    const nCols = Math.max(...filas.map(f => f.length), 1);

    // Cabecera por defecto si el llamador pasó solo datos.
    const conCabecera = filas[0].every(c => typeof c === 'string' || typeof c === 'number');
    const header = conCabecera ? filas[0] : Array.from({ length: nCols }, (_, i) => `Columna ${i + 1}`);

    const ws = XLSX.utils.aoa_to_sheet(filas);

    // Anchos aproximados (con acentos contando doble para que no corten).
    const widths: { wch: number }[] = [];
    for (let c = 0; c < nCols; c++) {
        let max = 0;
        filas.forEach(f => { max = Math.max(max, anchoDe(f[c])); });
        widths.push({ wch: Math.min(Math.max(max + 2, 8), 50) });
    }
    ws['!cols'] = widths;

    // Formato de moneda para las columnas indicadas.
    const money = new Set(opts.moneyCols ?? []);
    const refs = XLSX.utils.decode_range(ws['!ref'] ?? 'A1');
    for (let r = refs.s.r; r <= refs.e.r; r++) {
        for (let c = refs.s.c; c <= refs.e.c; c++) {
            if (!money.has(c)) continue;
            const addr = XLSX.utils.encode_cell({ r, c });
            const celda = ws[addr];
            if (celda && typeof celda.v === 'number') celda.z = '#,##0.00';
        }
    }

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, (opts.hoja ?? 'Reporte').slice(0, 31));
    XLSX.writeFile(wb, /\.xlsx$/i.test(nombre) ? nombre : `${nombre}.xlsx`);
}
