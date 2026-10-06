/**
 * El ÚNICO formato de dinero del sistema: "S/ 1,234.50" (separador de miles,
 * dos decimales, punto decimal como en Perú). Antes cada pantalla tenía el suyo
 * ("S/." en el dashboard, "S/ 45591.30" sin miles en Deudas) y confundía.
 */
const FORMATO = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Solo el número: "1,234.50". */
export function numero(v: unknown): string {
    const n = Number(v ?? 0);
    return FORMATO.format(Number.isFinite(n) ? n : 0);
}

/** Con símbolo: "S/ 1,234.50" (o "US$ 1,234.50"). El signo va delante: "-S/ 50.00". */
export function soles(v: unknown, simbolo = 'S/'): string {
    const n = Number(v ?? 0);
    const seguro = Number.isFinite(n) ? n : 0;
    return `${seguro < 0 ? '-' : ''}${simbolo} ${FORMATO.format(Math.abs(seguro))}`;
}
