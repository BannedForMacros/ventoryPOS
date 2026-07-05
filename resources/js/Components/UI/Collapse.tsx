import React from 'react';

/**
 * Collapse — despliegue/repliegue con animación suave para contenido de
 * altura variable (técnica grid-template-rows: 0fr → 1fr).
 *
 * Regla de diseño: TODO desplegable del sistema usa este componente para
 * que abrir y cerrar se sienta igual en todas partes (300ms ease).
 */
export default function Collapse({ open, children }: { open: boolean; children: React.ReactNode }) {
    return (
        <div
            className="grid transition-[grid-template-rows] duration-300 ease-in-out"
            style={{ gridTemplateRows: open ? '1fr' : '0fr' }}
        >
            <div className="overflow-hidden min-h-0">
                {children}
            </div>
        </div>
    );
}
