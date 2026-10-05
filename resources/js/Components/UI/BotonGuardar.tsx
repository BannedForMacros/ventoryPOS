import React, { useState } from 'react';
import { AlertTriangle, ArrowRight } from 'lucide-react';
import Button from '@/Components/UI/Button';
import Callout from '@/Components/UI/Callout';

/**
 * Validación en pantalla de los formularios de dinero — el mismo patrón que el
 * botón "Cobrar" del POS:
 *
 *  - Cada formulario tiene una función que devuelve el PRIMER problema que
 *    impide guardar, en palabras de la cajera (`Problema`), o null.
 *  - El botón de guardar, si hay problema, se pinta en ámbar con ese texto y
 *    "Corregir →": al pulsarlo lleva al campo (scroll + foco) y deja el mensaje
 *    escrito junto al campo. Sin problema, es el botón normal.
 *  - Los campos se marcan con `data-campo="..."` (directo en el input, o en un
 *    div que lo envuelve: se enfoca el primer control que haya dentro).
 */
export interface Problema {
    texto: string;
    /** Clave del campo (`data-campo`) al que lleva "Corregir" y bajo el que se escribe el texto. */
    campo?: string;
    /**
     * Dónde poner el foco si no es `[data-campo=campo]`: otro contenedor
     * `data-campo` y el n-ésimo control dentro (p. ej. la cuenta, 2.º select
     * de un formulario de pago compartido que no acepta data-*).
     */
    foco?: { campo: string; indice?: number };
    /** Acción propia en vez de enfocar `campo` (p. ej. abrir otro modal). */
    resolver?: () => void;
}

/** Lleva al campo marcado con `data-campo="<campo>"`: scroll + foco. */
export function enfocarCampo(campo: string, indice = 0) {
    const el = document.querySelector<HTMLElement>(`[data-campo="${campo}"]`);
    if (!el) return;
    const control = el.matches('input, select, textarea, button')
        ? el
        : el.querySelectorAll<HTMLElement>('input:not([type="hidden"]):not([disabled]), select, textarea, button:not([disabled])')[indice] ?? null;
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    (control ?? el).focus({ preventScroll: true });
}

/**
 * Estado del "ya intentó guardar": hasta que la cajera pulsa "Corregir", los
 * campos no se pintan en rojo (no se regaña antes de tiempo). Después, el
 * texto del problema aparece bajo su campo y se va solo cuando lo corrige.
 */
export function useProblema(problema: Problema | null, errors: Record<string, string | undefined> = {}) {
    const [visto, setVisto] = useState(false);

    /** Error a mostrar bajo un campo: el del servidor, o el problema actual si es de ese campo. */
    function err(campo: string): string | undefined {
        return errors[campo] ?? (visto && problema?.campo === campo ? problema.texto : undefined);
    }

    function corregir() {
        setVisto(true);
        if (!problema) return;
        if (problema.resolver) problema.resolver();
        else if (problema.foco) enfocarCampo(problema.foco.campo, problema.foco.indice);
        else if (problema.campo) enfocarCampo(problema.campo);
    }

    /** Igual que `err`, pero para pasar un mapa de errores a un componente que lee `errors[campo]`. */
    function errs(...campos: string[]): Record<string, string> {
        const out: Record<string, string> = {};
        for (const c of campos) { const e = err(c); if (e) out[c] = e; }
        return out;
    }

    return { err, errs, corregir, reiniciar: () => setVisto(false) };
}

interface BotonGuardarProps {
    problema: Problema | null;
    onGuardar: () => void;
    onCorregir: () => void;
    guardando?: boolean;
    disabled?: boolean;
    variant?: 'primary' | 'success' | 'danger';
    className?: string;
    children: React.ReactNode;
}

/** Botón de guardar que, si falta algo, LO DICE y lleva al campo. */
export default function BotonGuardar({
    problema, onGuardar, onCorregir, guardando = false, disabled = false, variant = 'primary', className = '', children,
}: BotonGuardarProps) {
    if (problema && !guardando) {
        return (
            <button
                type="button"
                onClick={onCorregir}
                className={`inline-flex items-center gap-2 min-h-[38px] max-w-[440px] px-3 py-1.5 rounded-xl text-[13px] font-bold text-left transition-colors hover:brightness-[0.98] ${className}`}
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--vp-amber) 16%, var(--color-surface))',
                    border: '1.5px solid var(--vp-amber)',
                    color: 'var(--vp-amber-ink)',
                }}
            >
                <AlertTriangle size={16} className="flex-shrink-0" />
                <span className="flex-1 min-w-0 leading-tight line-clamp-2">{problema.texto}</span>
                <span className="flex items-center gap-0.5 text-[12px] flex-shrink-0 opacity-90">
                    Corregir <ArrowRight size={14} />
                </span>
            </button>
        );
    }
    return (
        <Button variant={variant} onClick={onGuardar} loading={guardando} disabled={disabled} className={className}>
            {children}
        </Button>
    );
}

/**
 * Errores del servidor que NO tienen un campo visible donde mostrarse (p. ej.
 * el campo está oculto, o es un error general). Así nunca se pierden en un
 * toast genérico ni quedan invisibles.
 */
export function ErroresSueltos({ errors, visibles, className = '' }: {
    errors: Record<string, string | undefined>;
    /** Claves que ya se muestran junto a su campo (`detalles.*` = prefijo). */
    visibles: string[];
    className?: string;
}) {
    const sueltos = Object.entries(errors)
        .filter(([k, v]) => !!v && !visibles.some(c => c.endsWith('*') ? k.startsWith(c.slice(0, -1)) : c === k))
        .map(([, v]) => v as string);
    if (sueltos.length === 0) return null;
    return (
        <Callout variant="danger" title="No se pudo guardar" className={className}>
            {sueltos.length === 1
                ? sueltos[0]
                : <ul className="list-disc pl-4 space-y-0.5">{sueltos.map((t, i) => <li key={i}>{t}</li>)}</ul>}
        </Callout>
    );
}
