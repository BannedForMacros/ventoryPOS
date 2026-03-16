import React, { InputHTMLAttributes, useId } from 'react';

interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    description?: string;
}

export default function Checkbox({
    label,
    description,
    className = '',
    id,
    ...props
}: CheckboxProps) {
    // Generamos un ID por si no se pasa uno, garantizando que el click en el texto active el checkbox
    const uniqueId = useId();
    const checkboxId = id || uniqueId;

    return (
        <div className="flex items-start gap-2.5">
            {/* Contenedor relativo para superponer el SVG sobre el input */}
            <div className="relative flex items-center justify-center mt-0.5 flex-shrink-0">
                <input
                    type="checkbox"
                    id={checkboxId}
                    className={`
                        peer appearance-none w-5 h-5 rounded-md border-2 
                        transition-all duration-200 cursor-pointer
                        disabled:cursor-not-allowed disabled:opacity-50
                        hover:border-[var(--color-primary)]
                        focus:outline-none focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-primary)_20%,transparent)]
                        ${className}
                    `}
                    style={{
                        backgroundColor: 'var(--color-surface, #ffffff)',
                        borderColor: 'var(--color-border, #e5e7eb)',
                        // Estas variables sobrescriben los estilos en línea cuando está "checked"
                    }}
                    {...props}
                />
                
                {/* Capa invisible para manejar el color cuando está checked (usando CSS puro para las variables) */}
                <style>{`
                    #${checkboxId.replace(/:/g, '\\:')}:checked {
                        background-color: var(--color-primary, #3b82f6) !important;
                        border-color: var(--color-primary, #3b82f6) !important;
                    }
                `}</style>

                {/* Icono de check (SVG). Se anima combinando las clases peer de Tailwind */}
                <svg
                    className="absolute w-3.5 h-3.5 pointer-events-none opacity-0 scale-50 peer-checked:opacity-100 peer-checked:scale-100 transition-all duration-200 ease-out"
                    style={{ color: 'var(--color-surface, #ffffff)' }} 
                    viewBox="0 0 17 12"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    <polyline points="1 6.5 6 11.5 16 1.5" />
                </svg>
            </div>
            
            {/* Textos: Label y Descripción (opcional) */}
            {(label || description) && (
                <div className="flex flex-col">
                    {label && (
                        <label 
                            htmlFor={checkboxId} 
                            className="text-sm font-medium cursor-pointer select-none transition-colors peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            style={{ color: 'var(--color-text, #111827)' }}
                        >
                            {label}
                        </label>
                    )}
                    {description && (
                        <p className="text-xs select-none" style={{ color: 'var(--color-text-muted, #6b7280)' }}>
                            {description}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}