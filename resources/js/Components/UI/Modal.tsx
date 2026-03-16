import React, { useEffect, useState } from 'react';
import { X } from 'lucide-react';

type Size = 'sm' | 'md' | 'lg' | 'xl';

interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    size?: Size;
    children: React.ReactNode;
    footer?: React.ReactNode;
}

const sizeClasses: Record<Size, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
};

export default function Modal({ isOpen, onClose, title, size = 'md', children, footer }: ModalProps) {
    const [visible, setVisible] = useState(false);
    const [rendered, setRendered] = useState(false);

    useEffect(() => {
        let rAF1: number;
        let rAF2: number;
        let exitTimer: ReturnType<typeof setTimeout>;

        if (isOpen) {
            // 1. Montamos el componente en el DOM (opacity 0, scale 0.95)
            setRendered(true);
            
            // 2. Esperamos al primer frame para asegurar que el DOM inicial se pinte
            rAF1 = requestAnimationFrame(() => {
                // 3. En el siguiente frame, cambiamos a visible (opacity 1, scale 1) 
                // para detonar la transición CSS
                rAF2 = requestAnimationFrame(() => {
                    setVisible(true);
                });
            });
        } else {
            // 1. Iniciamos la transición de salida
            setVisible(false);
            
            // 2. Esperamos a que termine la animación CSS (200ms + 20ms de margen) 
            // antes de desmontar el componente del DOM
            exitTimer = setTimeout(() => setRendered(false), 220); 
        }

        // Limpieza para evitar fugas de memoria o errores si el componente se desmonta antes de tiempo
        return () => {
            if (rAF1) cancelAnimationFrame(rAF1);
            if (rAF2) cancelAnimationFrame(rAF2);
            if (exitTimer) clearTimeout(exitTimer);
        };
    }, [isOpen]);

    // Si no está renderizado, no devolvemos nada al DOM
    if (!rendered) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Overlay (Fondo oscuro) */}
            <div
                className="absolute inset-0 transition-opacity duration-200 ease-out"
                style={{
                    backgroundColor: 'rgba(0,0,0,0.4)',
                    opacity: visible ? 1 : 0,
                }}
                onClick={onClose}
            />

            {/* Panel del Modal */}
            <div
                className={`relative w-full ${sizeClasses[size]} rounded-lg shadow-xl transition-all duration-200 ease-out flex flex-col`}
                style={{
                    backgroundColor: 'var(--color-surface, #ffffff)', // Fallback a blanco si no hay variable CSS
                    border: '1px solid var(--color-border, #e5e7eb)',
                    opacity: visible ? 1 : 0,
                    transform: visible ? 'scale(1)' : 'scale(0.95)',
                    maxHeight: '90vh',
                }}
            >
                {/* Header */}
                <div
                    className="flex items-center justify-between px-5 py-4 border-b flex-shrink-0"
                    style={{ borderColor: 'var(--color-border, #e5e7eb)' }}
                >
                    <h3 className="text-base font-semibold" style={{ color: 'var(--color-text, #111827)' }}>
                        {title}
                    </h3>
                    <button
                        onClick={onClose}
                        className="rounded p-1 transition-colors duration-150 hover:bg-black/5 dark:hover:bg-white/10"
                        style={{ color: 'var(--color-text-muted, #6b7280)' }}
                        aria-label="Cerrar modal"
                    >
                        <X size={18} />
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto px-5 py-4">
                    {children}
                </div>

                {/* Footer */}
                {footer && (
                    <div
                        className="flex items-center justify-end gap-2 px-5 py-4 border-t flex-shrink-0"
                        style={{ borderColor: 'var(--color-border, #e5e7eb)' }}
                    >
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}