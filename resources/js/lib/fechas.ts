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
