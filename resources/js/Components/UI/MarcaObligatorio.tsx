import { Check } from 'lucide-react';

/**
 * Marca de campo obligatorio, junto a la etiqueta:
 *  - vacío → punto ámbar que late: "esto te falta".
 *  - lleno → check verde: "listo".
 * Así quien llena el formulario ve de un vistazo qué le falta, sin esperar
 * al error del servidor. Respeta "reducir movimiento" del sistema.
 */
export default function MarcaObligatorio({ lleno }: { lleno: boolean }) {
    return lleno ? (
        <Check size={13} strokeWidth={3} aria-hidden className="vp-req-ok inline-block ml-1.5 -mt-0.5" style={{ color: 'var(--vp-mint-ink)' }} />
    ) : (
        <>
            <span className="vp-req" aria-hidden title="Obligatorio" />
            <span className="sr-only"> (obligatorio)</span>
        </>
    );
}

/** ¿El valor cuenta como lleno? (0 sí cuenta; '', null y undefined no). */
export function estaLleno(valor: unknown): boolean {
    if (valor === null || valor === undefined) return false;
    if (Array.isArray(valor)) return valor.length > 0;
    return String(valor).trim() !== '';
}
