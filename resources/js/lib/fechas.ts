/**
 * Fechas en hora LOCAL del navegador (America/Lima).
 *
 * NUNCA usar `new Date().toISOString()` para fechas de formularios:
 * devuelve la fecha en UTC, y en Lima (UTC−5) a partir de las 7:00 pm
 * eso ya es "mañana" — los <input type="date"> salían con un día de más.
 */

/** YYYY-MM-DD de un Date, en hora local. */
export function fechaLocal(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/** Fecha de HOY local en YYYY-MM-DD (para <input type="date">). */
export function hoyLocal(): string {
    return fechaLocal(new Date());
}

/** "YYYY-MM-DDTHH:mm" local (para <input type="datetime-local">). */
export function ahoraLocalInput(d: Date = new Date()): string {
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${fechaLocal(d)}T${hh}:${mm}`;
}

/**
 * Formatea para MOSTRAR una fecha que el backend serializa como ISO
 * ("2026-07-18T00:00:00.000000Z") → "18 jul 2026".
 *
 * OJO con zona horaria: Laravel manda las fechas "puras" como UTC midnight.
 * Si se pasan directo a `new Date()` y a `toLocaleDateString`, en Lima (UTC−5)
 * retroceden al día anterior (el usuario crea el 18 y lo ve como 17). Por eso
 * extraemos YYYY-MM-DD y construimos un Date local con los componentes: así no
 * hay conversión de zona horaria. Devuelve '' si el valor es vacío/nulo y el
 * ISO original si no se puede parsear.
 */
export function fmtFecha(iso?: string | null): string {
    if (!iso) return '';
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) return iso;
    const date = new Date(y, m - 1, d);
    if (isNaN(date.getTime())) return iso;
    return date.toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' });
}
