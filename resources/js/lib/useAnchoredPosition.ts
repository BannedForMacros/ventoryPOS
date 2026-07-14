import { useLayoutEffect, useState, type RefObject, type CSSProperties } from 'react';

export interface AnchoredPosition {
    style: CSSProperties;
    /** Espacio disponible (px) para la lista scrolleable del dropdown. */
    maxHeight: number;
    /** Hacia dónde se abrió: debajo o encima del trigger. */
    placement: 'bottom' | 'top';
}

/**
 * Ancla un dropdown (renderizado en un portal, `position: fixed`) al elemento
 * `anchorRef`, para que ESCAPE cualquier ancestro con `overflow` recortado
 * (modales con scroll, contenedores `overflow-hidden`, celdas de tabla...).
 *
 * Sin esto, un dropdown `position: absolute` se "esconde"/recorta al llegar al
 * borde del contenedor scrolleable. Aquí calculamos la posición en viewport y
 * la recomputamos ante scroll (en captura, para atrapar scroll de ancestros) y
 * resize. Además elegimos abrir hacia arriba si abajo no hay espacio.
 *
 * @param anchorRef  Referencia al trigger (el botón del select).
 * @param open       Si el dropdown está visible.
 * @param gap        Separación en px entre trigger y dropdown.
 */
export function useAnchoredPosition(
    anchorRef: RefObject<HTMLElement>,
    open: boolean,
    gap = 6,
): AnchoredPosition | null {
    const [pos, setPos] = useState<AnchoredPosition | null>(null);

    useLayoutEffect(() => {
        if (!open) { setPos(null); return; }
        const el = anchorRef.current;
        if (!el) return;

        const compute = () => {
            const r = el.getBoundingClientRect();
            const vh = window.innerHeight;
            const spaceBelow = vh - r.bottom - gap;
            const spaceAbove = r.top - gap;

            // Preferimos abajo; solo subimos si abajo hay poco y arriba hay más.
            const abrirArriba = spaceBelow < 200 && spaceAbove > spaceBelow;
            const maxHeight = Math.min(340, Math.max(120, abrirArriba ? spaceAbove : spaceBelow));

            const base: CSSProperties = {
                position: 'fixed',
                left: Math.round(r.left),
                width: Math.round(r.width),
                zIndex: 1000,
            };
            const style: CSSProperties = abrirArriba
                ? { ...base, bottom: Math.round(vh - r.top + gap) }
                : { ...base, top: Math.round(r.bottom + gap) };

            setPos({ style, maxHeight, placement: abrirArriba ? 'top' : 'bottom' });
        };

        compute();
        // `true` (captura) para reaccionar al scroll de CUALQUIER ancestro,
        // no solo del window (el cuerpo del modal scrollea, por ejemplo).
        window.addEventListener('scroll', compute, true);
        window.addEventListener('resize', compute);
        return () => {
            window.removeEventListener('scroll', compute, true);
            window.removeEventListener('resize', compute);
        };
    }, [open, anchorRef, gap]);

    return pos;
}
