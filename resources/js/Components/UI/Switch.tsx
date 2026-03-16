import React, { InputHTMLAttributes, useId } from 'react';

interface SwitchProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    description?: string;
}

export default function Switch({
    label,
    description,
    className = '',
    id,
    ...props
}: SwitchProps) {
    // ID único para enlazar el label y el input oculto
    const uniqueId = useId();
    const switchId = id || uniqueId;

    return (
        <div className={`flex items-start gap-3 ${className}`}>
            {/* Contenedor del Switch interactivo */}
            <label 
                htmlFor={switchId} 
                className="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5 group"
            >
                {/* Input nativo oculto. Usamos 'peer' para que sus estados (checked, focus) afecten a los divs hermanos */}
                <input
                    type="checkbox"
                    id={switchId}
                    className="sr-only peer"
                    {...props}
                />
                
                {/* La "Pista" (Fondo del Switch) */}
                <div 
                    className={`
                        w-11 h-6 rounded-full transition-colors duration-200 ease-in-out
                        peer-disabled:cursor-not-allowed peer-disabled:opacity-50
                        peer-focus-visible:outline-none peer-focus-visible:ring-4
                        peer-focus-visible:ring-[color-mix(in_srgb,var(--color-primary)_20%,transparent)]
                        bg-[var(--color-border,#e5e7eb)] 
                        peer-checked:bg-[var(--color-primary,#3b82f6)]
                    `}
                ></div>

                {/* El "Pulgar" (Círculo blanco que se desliza) */}
                <div 
                    className={`
                        absolute left-0.5 top-0.5 w-5 h-5 rounded-full shadow-sm
                        transition-transform duration-200 ease-in-out
                        bg-[var(--color-surface,#ffffff)] 
                        peer-checked:translate-x-5
                        group-active:w-6 peer-checked:group-active:translate-x-4
                    `}
                ></div>
            </label>

            {/* Textos: Label y Descripción */}
            {(label || description) && (
                <div className="flex flex-col">
                    {label && (
                        <label 
                            htmlFor={switchId} 
                            className="text-sm font-medium cursor-pointer select-none transition-colors peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            style={{ color: 'var(--color-text, #111827)' }}
                        >
                            {label}
                        </label>
                    )}
                    {description && (
                        <p className="text-xs select-none mt-0.5" style={{ color: 'var(--color-text-muted, #6b7280)' }}>
                            {description}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}