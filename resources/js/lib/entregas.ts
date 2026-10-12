/** Cómo programa una empresa sus envíos (Configuración → Entregas). */
export type MomentoEntrega = 'hora' | 'franja';

/** Un momento del día: se registra con su hora, y en el ticket sale con su frase. */
export type FranjaEntrega = {
    nombre: string;
    hora:   string; // HH:MM
    frase:  string;
};

/** La franja a la que pertenece una hora (HH:MM): la última que ya empezó; antes de la primera, la primera. */
export function franjaDe(hora: string, franjas: FranjaEntrega[]): FranjaEntrega | null {
    if (!franjas.length) return null;
    return franjas.reduce((sel, f) => (f.hora <= hora ? f : sel), franjas[0]);
}

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

/** "Jueves por la tarde" a partir de una fecha ISO (se lee en hora local, como el resto de Despachos). */
export function etiquetaFranja(programada: string, franjas: FranjaEntrega[]): string {
    const d = new Date(programada);
    const f = franjaDe(d.toTimeString().slice(0, 5), franjas);
    return `${DIAS[d.getDay()]} ${f?.frase ?? ''}`.trim();
}
